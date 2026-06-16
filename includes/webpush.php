<?php
/**
 * Minimal Web Push implementation (RFC 8291 + VAPID)
 * Pure PHP, no external dependencies (requires openssl extension)
 */

function webpush_base64url_encode($d){return rtrim(strtr(base64_encode($d),'+/','-_'),'=');}
function webpush_base64url_decode($d){return base64_decode(strtr($d,'-_','+/').str_repeat('=',(4-strlen($d)%4)%4));}

/**
 * Generate a new VAPID key pair (P-256 ECDSA)
 * Returns ['public'=>base64url, 'private'=>base64url]
 */
function webpush_generate_vapid_keys(){
    $res=openssl_pkey_new(['curve_name'=>'prime256v1','private_key_type'=>OPENSSL_KEYTYPE_EC]);
    if(!$res)return false;
    openssl_pkey_export($res,$priv);
    $det=openssl_pkey_get_details($res);
    // Public key = uncompressed point: 0x04 || X || Y (65 bytes)
    $pub="\x04".$det['ec']['x'].$det['ec']['y'];
    // Private key (raw 32 bytes)
    $pkey=openssl_pkey_get_private($priv);
    $det2=openssl_pkey_get_details($pkey);
    $privRaw=$det2['ec']['d'];
    return ['public'=>webpush_base64url_encode($pub),'private'=>webpush_base64url_encode($privRaw),'pem'=>$priv];
}

/**
 * Create PEM private key from raw 32-byte private key
 */
function webpush_priv_to_pem($privRaw,$pubRaw=null){
    // Build EC private key DER manually
    // SEQUENCE { INTEGER 1, OCTET STRING priv, [0] OID P-256, [1] BIT STRING pub }
    $oid=hex2bin('06082A8648CE3D030107'); // OID 1.2.840.10045.3.1.7 (P-256)
    $parts='020101'; // INTEGER 1
    $parts.='0420'.bin2hex($privRaw); // OCTET STRING(32) priv
    $parts.='A00A'.bin2hex($oid); // [0] OID
    if($pubRaw){
        $bitStr='00'.bin2hex($pubRaw); // BIT STRING: unused bits + pub
        $parts.='A1'.sprintf('%02X',strlen($bitStr)/2+2).'03'.sprintf('%02X',strlen($bitStr)/2).$bitStr;
    }
    $body=hex2bin($parts);
    $der=chr(0x30).chr(strlen($body)).$body;
    $pem="-----BEGIN EC PRIVATE KEY-----\n".chunk_split(base64_encode($der),64,"\n")."-----END EC PRIVATE KEY-----\n";
    return $pem;
}

/**
 * Sign VAPID JWT with ECDSA P-256 (ES256)
 */
function webpush_vapid_jwt($aud,$sub,$privPEM,$expSeconds=43200){
    $header=webpush_base64url_encode(json_encode(['typ'=>'JWT','alg'=>'ES256']));
    $claims=webpush_base64url_encode(json_encode(['aud'=>$aud,'exp'=>time()+$expSeconds,'sub'=>$sub]));
    $data=$header.'.'.$claims;
    $key=openssl_pkey_get_private($privPEM);
    if(!$key)return false;
    openssl_sign($data,$derSig,$key,OPENSSL_ALGO_SHA256);
    // Convert DER signature to raw r||s (64 bytes)
    $raw=webpush_der_to_raw($derSig);
    return $data.'.'.webpush_base64url_encode($raw);
}

function webpush_der_to_raw($der){
    // Parse DER: SEQUENCE { INTEGER r, INTEGER s }
    $pos=0;
    if(ord($der[$pos])!==0x30)return false;$pos++;
    $seqLen=ord($der[$pos]);
    if($seqLen&0x80){$n=$seqLen&0x7F;$pos++;$pos+=$n;}else{$pos++;}
    if(ord($der[$pos])!==0x02)return false;$pos++;
    $rLen=ord($der[$pos]);$pos++;
    $r=substr($der,$pos,$rLen);$pos+=$rLen;
    if(ord($der[$pos])!==0x02)return false;$pos++;
    $sLen=ord($der[$pos]);$pos++;
    $s=substr($der,$pos,$sLen);
    // Strip leading zero (DER signed int) and left-pad to 32 bytes
    $r=ltrim($r,"\x00");$s=ltrim($s,"\x00");
    return str_pad($r,32,"\x00",STR_PAD_LEFT).str_pad($s,32,"\x00",STR_PAD_LEFT);
}

/**
 * HKDF (RFC 5869)
 */
function webpush_hkdf($salt,$ikm,$info,$len){
    $prk=hash_hmac('sha256',$ikm,$salt,true);
    $t='';$okm='';$i=1;
    while(strlen($okm)<$len){
        $t=hash_hmac('sha256',$t.$info.chr($i),$prk,true);
        $okm.=$t;$i++;
    }
    return substr($okm,0,$len);
}

/**
 * Encrypt payload using aes128gcm content encoding (RFC 8188 + 8291)
 * Returns binary body for POST
 */
function webpush_encrypt($payload,$userPublicKeyB64,$userAuthB64){
    $userPub=webpush_base64url_decode($userPublicKeyB64);   // 65 bytes
    $userAuth=webpush_base64url_decode($userAuthB64);       // 16 bytes
    // Generate ephemeral ES P-256 key
    $eph=openssl_pkey_new(['curve_name'=>'prime256v1','private_key_type'=>OPENSSL_KEYTYPE_EC]);
    $det=openssl_pkey_get_details($eph);
    $ephPub="\x04".$det['ec']['x'].$det['ec']['y'];
    // ECDH
    $userPubPEM=webpush_rawpub_to_pem($userPub);
    $userPubRes=openssl_pkey_get_public($userPubPEM);
    $shared=openssl_pkey_derive($userPubRes,$eph,32);
    if(!$shared)return false;
    // Salt (16 random bytes)
    $salt=random_bytes(16);
    // key_info = "WebPush: info" || 0x00 || ua_public || as_public
    $keyInfo="WebPush: info\x00".$userPub.$ephPub;
    $ikm=webpush_hkdf($userAuth,$shared,$keyInfo,32);
    // Derive CEK and nonce
    $cek=webpush_hkdf($salt,$ikm,"Content-Encoding: aes128gcm\x00",16);
    $nonce=webpush_hkdf($salt,$ikm,"Content-Encoding: nonce\x00",12);
    // Pad: payload || 0x02 (final delimiter)
    $plain=$payload."\x02";
    // Encrypt
    $tag='';$ct=openssl_encrypt($plain,'aes-128-gcm',$cek,OPENSSL_RAW_DATA,$nonce,$tag);
    if($ct===false)return false;
    // Build body: salt(16) || rs(4, 4096) || idlen(1) || keyid(ephPub 65) || ciphertext||tag
    $rs=pack('N',4096);
    $keyid=$ephPub;
    $body=$salt.$rs.chr(strlen($keyid)).$keyid.$ct.$tag;
    return $body;
}

function webpush_rawpub_to_pem($raw){
    // Build SubjectPublicKeyInfo DER for P-256
    $oid=hex2bin('06072A8648CE3D020106082A8648CE3D030107'); // ecPublicKey + P-256
    $algId='30'.sprintf('%02X',strlen($oid)).bin2hex($oid);
    $bitStr='00'.bin2hex($raw);
    $pub='03'.sprintf('%02X',strlen($bitStr)/2).$bitStr;
    $body=hex2bin($algId.$pub);
    $der=chr(0x30).chr(strlen($body)).$body;
    return "-----BEGIN PUBLIC KEY-----\n".chunk_split(base64_encode($der),64,"\n")."-----END PUBLIC KEY-----\n";
}

/**
 * Send push notification
 * $sub = ['endpoint'=>..., 'p256dh'=>..., 'auth'=>...]
 * $payload = string (JSON or text)
 * $vapid = ['public'=>..., 'private'=>..., 'subject'=>'mailto:...']
 * Returns ['ok'=>bool, 'status'=>int, 'error'=>string, 'body'=>string]
 */
function webpush_send($sub,$payload,$vapid){
    $endpoint=$sub['endpoint'];
    $p256=$sub['p256dh']??$sub['keys']['p256dh']??'';
    $auth=$sub['auth']??$sub['keys']['auth']??'';
    if(!$endpoint||!$p256||!$auth)return ['ok'=>false,'error'=>'Invalid subscription'];
    // Audience = scheme://host
    $urlParts=parse_url($endpoint);
    $aud=$urlParts['scheme'].'://'.$urlParts['host'];
    // VAPID keys
    $privRaw=webpush_base64url_decode($vapid['private']);
    $pubRaw=webpush_base64url_decode($vapid['public']);
    $privPEM=webpush_priv_to_pem($privRaw,$pubRaw);
    $jwt=webpush_vapid_jwt($aud,$vapid['subject']??'mailto:admin@example.com',$privPEM);
    if(!$jwt)return ['ok'=>false,'error'=>'VAPID JWT sign failed'];
    // Encrypt payload
    $body=webpush_encrypt($payload,$p256,$auth);
    if($body===false)return ['ok'=>false,'error'=>'Encryption failed'];
    $headers=[
        'Content-Type: application/octet-stream',
        'Content-Encoding: aes128gcm',
        'TTL: 86400',
        'Urgency: normal',
        'Authorization: vapid t='.$jwt.', k='.$vapid['public'],
    ];
    $ch=curl_init($endpoint);
    curl_setopt_array($ch,[
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>$body,
        CURLOPT_HTTPHEADER=>$headers,
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_TIMEOUT=>10,
        CURLOPT_SSL_VERIFYPEER=>false,
        CURLOPT_SSL_VERIFYHOST=>0,
    ]);
    $res=curl_exec($ch);
    $status=curl_getinfo($ch,CURLINFO_HTTP_CODE);
    $err=curl_error($ch);
    curl_close($ch);
    // Log push send for debugging (rotate: keep last 100 lines)
    @file_put_contents(__DIR__.'/../push_log.txt',
        date('Y-m-d H:i:s')." status=$status ".(strlen($err)?"err=$err ":"").substr($endpoint,0,80)."\n",
        FILE_APPEND);
    return ['ok'=>($status>=200&&$status<300),'status'=>$status,'error'=>$err,'body'=>$res];
}

/**
 * Get or generate VAPID keys (stored in settings)
 */
function webpush_get_vapid($db){
    $pub='';$priv='';$sub='mailto:admin@example.com';
    try{
        $s=$db->prepare("SELECT `value` FROM settings WHERE `key`=?");
        $s->execute(['vapid_public']);$pub=$s->fetchColumn();
        $s->execute(['vapid_private']);$priv=$s->fetchColumn();
        $s->execute(['vapid_subject']);$r=$s->fetchColumn();if($r)$sub=$r;
    }catch(Exception $e){}
    if(!$pub||!$priv){
        $keys=webpush_generate_vapid_keys();
        if(!$keys)return false;
        $pub=$keys['public'];$priv=$keys['private'];
        try{
            $ins=$db->prepare("INSERT INTO settings(`key`,`value`) VALUES(?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
            $ins->execute(['vapid_public',$pub]);
            $ins->execute(['vapid_private',$priv]);
        }catch(Exception $e){}
    }
    return ['public'=>$pub,'private'=>$priv,'subject'=>$sub];
}

/**
 * Kirim notif push ke SEMUA subscription user tertentu
 * Auto-cleanup subscription yang expired (410/404).
 * Non-blocking: fail silently — aman dipanggil dari flow deposit/withdraw/dll
 *
 * @param PDO $db
 * @param int $uid user_id
 * @param string $title judul notif
 * @param string $body isi notif (plain text)
 * @param string $url url yang dibuka saat notif diklik (default: /dashboard.php)
 * @return array ['sent'=>int, 'failed'=>int, 'removed'=>int]
 */
function pushNotify($db,$uid,$title,$body,$url='/dashboard.php'){
    $result=['sent'=>0,'failed'=>0,'removed'=>0];
    try{
        $vapid=webpush_get_vapid($db);
        if(!$vapid)return $result;
        $vapid['subject']='mailto:admin@'.($_SERVER['HTTP_HOST']??'example.com');
        $q=$db->prepare("SELECT id,endpoint,p256dh,auth FROM push_subscriptions WHERE user_id=?");
        $q->execute([$uid]);
        $subs=$q->fetchAll();
        if(empty($subs))return $result;
        $payload=json_encode([
            'title'=>$title,
            'body'=>$body,
            'url'=>$url,
            'icon'=>'/icon-192.png',
            'badge'=>'/icon-192.png',
            'tag'=>'u'.$uid.'-'.time()
        ]);
        $removeIds=[];
        foreach($subs as $s){
            $r=webpush_send(['endpoint'=>$s['endpoint'],'p256dh'=>$s['p256dh'],'auth'=>$s['auth']],$payload,$vapid);
            if($r['ok']){$result['sent']++;}
            else{
                $result['failed']++;
                // Auto-cleanup expired/invalid subscriptions
                $st=$r['status']??0;
                if($st===404||$st===410)$removeIds[]=$s['id'];
            }
        }
        if($removeIds){
            try{
                $db->query("DELETE FROM push_subscriptions WHERE id IN (".implode(',',array_map('intval',$removeIds)).")");
                $result['removed']=count($removeIds);
            }catch(Exception $e){}
        }
    }catch(Exception $e){}
    return $result;
}
