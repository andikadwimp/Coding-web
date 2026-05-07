<?php
require_once __DIR__.'/../includes/config.php';
$d=input();$action=$d['action']??$_GET['action']??'';

// Schema migrations — run sekali via flag (sebelumnya jalan di tiap request → lock contention)
if(getSetting($db,'schema_auth_v1','')!=='1'){
    try{$db->exec("ALTER TABLE users ADD UNIQUE INDEX uq_phone (phone)");}catch(Exception $e){}
    try{$db->exec("CREATE INDEX idx_acc_number ON user_banks(acc_number)");}catch(Exception $e){}
    try{$db->prepare("INSERT INTO settings(`key`,`value`) VALUES('schema_auth_v1','1') ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)")->execute();}catch(Exception $e){}
}

// Cookie lifetime: 30 hari (sebelumnya 10 tahun → token bocor = forever pwned)
if(!defined('AUTH_COOKIE_TTL'))define('AUTH_COOKIE_TTL',86400*30);

if($action==='register'){
    $phone=trim($d['phone']??$d['username']??'');
    $pw=$d['password']??'';$pw2=$d['confirm']??'';

    // ═══ REFERRAL: prioritas form input, fallback ke cookie ref_code ═══
    // Cookie ref_code diset saat user buka link invite.php?ref=XXX
    // Sticky 30 hari, biar user yg install APK dari invite link → ref tetap ke-attach
    $refBy=strtoupper(trim($d['referral']??''));
    if(!$refBy&&!empty($_COOKIE['ref_code'])){
        $refBy=strtoupper(trim(preg_replace('/[^A-Za-z0-9_-]/','',$_COOKIE['ref_code'])));
    }

    $phone=preg_replace('/[^0-9]/','',$phone);
    if(strlen($phone)<6||strlen($phone)>15)err('Nomor telepon minimal 6 digit, maksimal 15 digit');
    if(strlen($pw)<6)err('Password minimal 6 karakter');
    if($pw!==$pw2)err('Konfirmasi password tidak cocok');

    // ═══ USERNAME PATTERN (configurable via admin panel) ═══
    // Ambil pattern dari settings.nexus_username_pattern
    // Placeholder: {phone}, {random:N}, {random_num:N}, {counter}
    // Contoh pattern:
    //   "k7777{phone}"           → k77770812345
    //   "k7777{random:6}"        → k7777A7X2P9
    //   "k7777{random_num:8}"    → k777712345678
    //   "k7777{counter}"         → k7777000001
    //   ""                       → random 8-10 digit (default lama)
    $pattern='';
    try{
        $pq=$db->prepare("SELECT `value` FROM settings WHERE `key`='nexus_username_pattern' LIMIT 1");
        $pq->execute();$pattern=trim((string)$pq->fetchColumn());
    }catch(Exception $e){}

    $genUsername=function() use ($phone,$pattern,$db){
        if($pattern===''){
            // Default behavior: random 8-10 digit angka
            $len=mt_rand(8,10);
            $u='';for($i=0;$i<$len;$i++)$u.=mt_rand(0,9);
            $u=ltrim($u,'0');if(strlen($u)<8)$u='1'.$u;
            return $u;
        }
        $u=$pattern;
        // Replace {phone}
        $u=str_replace('{phone}',preg_replace('/^0+/','',$phone),$u);
        // Replace {random:N} (alphanumeric uppercase)
        $u=preg_replace_callback('/\{random:(\d+)\}/',function($m){
            $n=max(1,min(20,intval($m[1])));
            $chars='ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // skip ambiguous: 0,O,1,I
            $s='';for($i=0;$i<$n;$i++)$s.=$chars[mt_rand(0,strlen($chars)-1)];
            return $s;
        },$u);
        // Replace {random_num:N}
        $u=preg_replace_callback('/\{random_num:(\d+)\}/',function($m){
            $n=max(1,min(20,intval($m[1])));
            $s='';for($i=0;$i<$n;$i++)$s.=mt_rand(0,9);
            return $s;
        },$u);
        // Replace {counter} (6-digit padded, next user id)
        if(strpos($u,'{counter}')!==false){
            try{
                $nx=intval($db->query("SELECT IFNULL(MAX(id),0)+1 FROM users")->fetchColumn());
                $u=str_replace('{counter}',str_pad($nx,6,'0',STR_PAD_LEFT),$u);
            }catch(Exception $e){$u=str_replace('{counter}','',$u);}
        }
        // Sanitize: hanya alphanumeric
        $u=preg_replace('/[^A-Za-z0-9]/','',$u);
        return $u;
    };

    // Generate unique username (max 10 retry biar ga loop)
    $u='';
    for($try=0;$try<10;$try++){
        $u=$genUsername();
        if(strlen($u)<3)continue; // minimal 3 chars
        $chk=$db->prepare("SELECT id FROM users WHERE username=?");$chk->execute([$u]);
        if(!$chk->fetch())break;
        $u=''; // collision, retry
    }
    if($u===''||strlen($u)<3)err('Gagal generate username. Hubungi admin.');

    $s=$db->prepare("SELECT id FROM users WHERE phone=?");$s->execute([$phone]);
    if($s->fetch())err('Nomor telepon sudah terdaftar');
    if($refBy){$r=$db->prepare("SELECT id FROM users WHERE ref_code=?");$r->execute([$refBy]);if(!$r->fetch())err('Kode referral tidak ditemukan');}
    $nexusId=$u;
    if(NEXUS_URL){
        // Auto-set default RTP jika di-set di admin settings
        // (control_users_rtp aman untuk user_code yang belum exist di Nexus —
        //  user otomatis dibuat saat user_deposit pertama, RTP akan tetap di-respect)
        try{
            $drq=$db->prepare("SELECT `value` FROM settings WHERE `key`='default_rtp' LIMIT 1");
            $drq->execute();$defaultRtp=intval($drq->fetchColumn());
            if($defaultRtp>=1&&$defaultRtp<=95){
                $rtpBody=json_encode(['method'=>'control_users_rtp','agent_code'=>NEXUS_AGENT,'agent_token'=>NEXUS_TOKEN,'user_codes'=>json_encode([$u]),'rtp'=>$defaultRtp]);
                $ch=curl_init(NEXUS_URL);
                curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$rtpBody,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10]);
                $rtpRes=curl_exec($ch);curl_close($ch);
                @file_put_contents(__DIR__.'/../nexus_log.txt',date('Y-m-d H:i:s')." default_rtp $u -> $defaultRtp%: ".$rtpRes."\n",FILE_APPEND);
            }
        }catch(Exception $e){/* jangan blokir register */}
    }
    $hash=password_hash($pw,PASSWORD_BCRYPT);
    $ref=genRef();
    try{
        $db->prepare("INSERT INTO users(username,password,email,phone,ref_code,referred_by,display_id,display_name) VALUES(?,?,?,?,?,?,?,?)")
           ->execute([$u,$hash,$phone.'@'.strtolower(preg_replace('/[^a-z0-9]/i','',$sets['site_name']??'site')).'.local',$phone,$ref,$refBy?:null,$nexusId,'Player_'.mt_rand(10000,99999)]);
    }catch(Exception $e){err('Gagal daftar: '.$e->getMessage());}
    $uid=$db->lastInsertId();
    $token=bin2hex(random_bytes(32));
    $db->prepare('UPDATE users SET auth_token=? WHERE id=?')->execute([$token,$uid]);
    setcookie('lx_token',$token,[
        'expires'=>time()+AUTH_COOKIE_TTL,
        'path'=>'/',
        'secure'=>!empty($_SERVER['HTTPS']),
        'httponly'=>true,
        'samesite'=>'Lax'
    ]);
    $newUser=$db->prepare("SELECT * FROM users WHERE id=?");$newUser->execute([$uid]);$userData=$newUser->fetch();
    unset($userData['password']);
    ok(['user_id'=>$uid,'username'=>$u,'ref_code'=>$ref,'user'=>$userData]);
}

if($action==='login'){
    $u=trim($d['username']??'');$pw=$d['password']??'';
    if(!$u||!$pw)err('Username dan password wajib');
    $cleanPh=preg_replace('/[^0-9]/','',''.$u);$tryUser='lx'.$cleanPh;
    $s=$db->prepare("SELECT * FROM users WHERE username=? OR phone=? OR username=?");$s->execute([$u,$cleanPh,$tryUser]);$user=$s->fetch();
    if(!$user||!password_verify($pw,$user['password']))err('Nomor atau password salah');
    if($user['status']==='banned')err('Akun diblokir');
    // Auto-fill display_id/display_name for legacy users
    try{
        if(empty($user['display_id'])){
            $did=mt_rand(1000000000,9999999999);
            $dn='Guest_'.mt_rand(10000,99999);
            $db->prepare("UPDATE users SET display_id=?,display_name=? WHERE id=?")->execute([$did,$dn,$user['id']]);
            $user['display_id']=$did;$user['display_name']=$dn;
        }
    }catch(Exception $e){}
    try{
        if(empty($user['avatar'])){
            $db->prepare("UPDATE users SET avatar='m1' WHERE id=?")->execute([$user['id']]);
            $user['avatar']='m1';
        }
    }catch(Exception $e){}
    // Auto-calculate VIP level
    try{
        if(!isset($user['vip_level'])||$user['vip_level']===null){
            $vl=function_exists('calcVipLevel')?calcVipLevel(intval($user['total_deposit']??0)):0;
            $db->prepare("UPDATE users SET vip_level=? WHERE id=?")->execute([$vl,$user['id']]);
            $user['vip_level']=$vl;
        }
    }catch(Exception $e){$user['vip_level']=0;}
    $db->prepare("UPDATE users SET last_login=NOW() WHERE id=?")->execute([$user['id']]);
    $token=bin2hex(random_bytes(32));
    $db->prepare('UPDATE users SET auth_token=? WHERE id=?')->execute([$token,$user['id']]);
    setcookie('lx_token',$token,[
        'expires'=>time()+AUTH_COOKIE_TTL,
        'path'=>'/',
        'secure'=>!empty($_SERVER['HTTPS']),
        'httponly'=>true,
        'samesite'=>'Lax'
    ]);
    unset($user['password']);
    ok(['user'=>$user]);
}

if($action==='logout'){
    $token=$_COOKIE['lx_token']??'';
    if($token){$db->prepare('UPDATE users SET auth_token=NULL WHERE auth_token=?')->execute([$token]);}
    setcookie('lx_token','',time()-3600,'/');
    ok();
}

if($action==='me'){
    $uid=auth();
    $s=$db->prepare("SELECT * FROM users WHERE id=?");$s->execute([$uid]);$user=$s->fetch();
    if(!$user)err('User not found');
    unset($user['password']);
    ok(['user'=>$user]);
}

if($action==='update_profile'){
    $uid=auth();
    $email=trim($d['email']??'');$phone=trim($d['phone']??'');
    if(!$email)err('Email wajib');
    $db->prepare("UPDATE users SET email=?,phone=? WHERE id=?")->execute([$email,$phone,$uid]);
    ok();
}

if($action==='change_password'){
    $uid=auth();
    $old=$d['old_password']??'';$new=$d['new_password']??'';$cf=$d['confirm']??'';
    $s=$db->prepare("SELECT password FROM users WHERE id=?");$s->execute([$uid]);$hash=$s->fetchColumn();
    if(!password_verify($old,$hash))err('Password lama salah');
    if(strlen($new)<6)err('Password baru minimal 6 karakter');
    if($new!==$cf)err('Konfirmasi tidak cocok');
    // Rotate token: token lama di-invalidate, generate baru biar sesi attacker (kalau bocor) putus
    $newToken=bin2hex(random_bytes(32));
    $db->prepare("UPDATE users SET password=?,auth_token=? WHERE id=?")
       ->execute([password_hash($new,PASSWORD_BCRYPT),$newToken,$uid]);
    setcookie('lx_token',$newToken,[
        'expires'=>time()+AUTH_COOKIE_TTL,
        'path'=>'/',
        'secure'=>!empty($_SERVER['HTTPS']),
        'httponly'=>true,
        'samesite'=>'Lax'
    ]);
    ok();
}

if($action==='update_bank'){
    $uid=auth();
    $bank=$d['bank']??'';$name=trim($d['acc_name']??'');$num=trim($d['acc_num']??'');
    if(!$bank||!$name||!$num)err('Semua field wajib');
    $db->prepare("UPDATE users SET bank=?,acc_name=?,acc_num=? WHERE id=?")->execute([$bank,$name,$num,$uid]);
    ok();
}

if($action==='update_avatar'){
    $uid=auth();
    $avatar=trim($d['avatar']??'');
    if(!preg_match('/^[mf]\d{1,2}$/',$avatar))err('Avatar tidak valid');
    $n=intval(substr($avatar,1));
    if($n<1||$n>20)err('Avatar tidak ditemukan');
    try{
        $db->prepare("UPDATE users SET avatar=? WHERE id=?")->execute([$avatar,$uid]);
    }catch(Exception $e){
        // Column might not exist, try adding it
        try{$db->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(10) DEFAULT 'm1'");}catch(Exception $e2){}
        try{$db->prepare("UPDATE users SET avatar=? WHERE id=?")->execute([$avatar,$uid]);}catch(Exception $e3){}
    }
    $s=$db->prepare("SELECT * FROM users WHERE id=?");$s->execute([$uid]);$user=$s->fetch();
    unset($user['password']);
    if(!isset($user['avatar']))$user['avatar']=$avatar;
    ok(['user'=>$user]);
}

err('INVALID_ACTION');
