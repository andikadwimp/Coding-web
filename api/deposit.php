<?php
// ═══════════════════════════════════════════════════════════════════════
// DEPOSIT HANDLER — SIMPLE & BULLETPROOF
// Ikutin spec SquadOnyx: paid → credit, selesai.
// ═══════════════════════════════════════════════════════════════════════
require_once __DIR__.'/../includes/config.php';

$d=input();
$action=$d['action']??$_GET['action']??'';

// Auto-detect callback kalau URL tanpa ?action=callback
if(!$action){
    $_raw=file_get_contents('php://input');
    $_body=json_decode($_raw,true);
    if(is_array($_body)){
        $_isCallback=(!empty($_body['event'])&&strpos($_body['event'],'payment')!==false)
                   ||(!empty($_body['tx_id'])&&strtolower($_body['status']??'')==='paid');
        if($_isCallback){$action='callback';$d=$_body;}
    }
}

// Schema safety (auto-run silent)
try{$db->exec("ALTER TABLE transactions ADD COLUMN note TEXT DEFAULT NULL AFTER ref_id");}catch(Exception $e){}
try{$db->exec("CREATE INDEX idx_ref_type ON transactions(ref_id,type)");}catch(Exception $e){}
try{$db->exec("CREATE INDEX idx_user_type_created ON transactions(user_id,type,created_at)");}catch(Exception $e){}
try{$db->exec("CREATE INDEX idx_status_created ON deposits(status,created_at)");}catch(Exception $e){}
// Ensure kolom deposits lengkap (kalau belum jalanin setup.php)
try{$db->exec("ALTER TABLE deposits ADD COLUMN pay_url TEXT DEFAULT NULL");}catch(Exception $e){}
try{$db->exec("ALTER TABLE deposits ADD COLUMN pay_data TEXT DEFAULT NULL");}catch(Exception $e){}
try{$db->exec("ALTER TABLE deposits ADD COLUMN turnover_at_deposit BIGINT DEFAULT 0");}catch(Exception $e){}
try{$db->exec("ALTER TABLE deposits ADD COLUMN turnover_met TINYINT DEFAULT 0");}catch(Exception $e){}
try{$db->exec("ALTER TABLE deposits ADD COLUMN paid_at DATETIME DEFAULT NULL");}catch(Exception $e){}
try{$db->exec("ALTER TABLE deposits ADD COLUMN pay_amount BIGINT DEFAULT 0");}catch(Exception $e){}
try{$db->exec("CREATE TABLE IF NOT EXISTS deposit_credits (
    tx_id VARCHAR(50) PRIMARY KEY,
    user_id INT NOT NULL,
    source VARCHAR(30) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user (user_id)
) ENGINE=InnoDB");}catch(Exception $e){}

// ═══════════════════════════════════════════════════════════════════════
// CREDIT FUNCTION — idempotent, atomic
// ═══════════════════════════════════════════════════════════════════════
function creditIfPaid($db,$txId,$source='unknown'){
    $s=$db->prepare("SELECT * FROM deposits WHERE tx_id=?");
    $s->execute([$txId]);
    $dep=$s->fetch();
    if(!$dep)return['status'=>'not_found'];

    // Cek udah ter-credit
    if($dep['status']==='paid'){
        $chk=$db->prepare("SELECT id FROM transactions WHERE ref_id=? AND type='deposit'");
        $chk->execute([$txId]);
        if($chk->fetch())return['status'=>'already_credited','dep'=>$dep];
    }

    // Lock via deposit_credits PRIMARY KEY (anti race)
    try{
        $db->prepare("INSERT INTO deposit_credits(tx_id,user_id,source) VALUES(?,?,?)")
           ->execute([$txId,$dep['user_id'],$source]);
    }catch(PDOException $e){
        $db->prepare("UPDATE deposits SET status='paid' WHERE id=? AND status!='paid'")
           ->execute([$dep['id']]);
        return['status'=>'race_avoided','dep'=>$dep];
    }

    $db->beginTransaction();
    try{
        // Re-check idempotent di dalam transaction
        $chk=$db->prepare("SELECT id FROM transactions WHERE ref_id=? AND type='deposit' FOR UPDATE");
        $chk->execute([$txId]);
        if($chk->fetch()){$db->commit();return['status'=>'already_credited','dep'=>$dep];}

        // Update status paid + turnover snapshot
        if($dep['bonus_id']){
            $q=$db->prepare("SELECT total_turnover FROM users WHERE id=?");$q->execute([$dep['user_id']]);
            $cTO=intval($q->fetchColumn());
            $db->prepare("UPDATE deposits SET status='paid',paid_at=NOW(),turnover_at_deposit=? WHERE id=?")
               ->execute([$cTO,$dep['id']]);
        }else{
            $db->prepare("UPDATE deposits SET status='paid',paid_at=NOW() WHERE id=?")
               ->execute([$dep['id']]);
        }

        // Credit saldo
        $u=$db->prepare("SELECT balance FROM users WHERE id=? FOR UPDATE");
        $u->execute([$dep['user_id']]);
        $bal=intval($u->fetchColumn());
        $after=$bal+intval($dep['nominal']);
        $db->prepare("INSERT INTO transactions(user_id,type,amount,balance_before,balance_after,ref_id,note) VALUES(?,?,?,?,?,?,?)")
           ->execute([$dep['user_id'],'deposit',$dep['nominal'],$bal,$after,$txId,'Deposit '.$dep['method']]);
        $db->prepare("UPDATE users SET balance=? WHERE id=?")->execute([$after,$dep['user_id']]);

        // Bonus
        if($dep['bonus_amount']>0){
            $bal2=$after;$after2=$bal2+intval($dep['bonus_amount']);
            $db->prepare("INSERT INTO transactions(user_id,type,amount,balance_before,balance_after,ref_id,note) VALUES(?,?,?,?,?,?,?)")
               ->execute([$dep['user_id'],'bonus',$dep['bonus_amount'],$bal2,$after2,$txId,'Bonus deposit']);
            $db->prepare("UPDATE users SET balance=? WHERE id=?")->execute([$after2,$dep['user_id']]);
        }

        $db->prepare("UPDATE users SET total_deposit=total_deposit+? WHERE id=?")
           ->execute([$dep['nominal'],$dep['user_id']]);

        // Referral bonus (first depo only)
        $u2=$db->prepare("SELECT referred_by,total_deposit FROM users WHERE id=?");
        $u2->execute([$dep['user_id']]);$usr=$u2->fetch();
        if($usr['referred_by']&&intval($usr['total_deposit'])===intval($dep['nominal'])){
            $ref=$db->prepare("SELECT id,balance FROM users WHERE ref_code=? FOR UPDATE");
            $ref->execute([$usr['referred_by']]);$refUser=$ref->fetch();
            if($refUser){
                $rate=0.03;$maxCap=500000;
                try{
                    $rs=$db->query("SELECT `value` FROM settings WHERE `key`='referral_bonus_pct'");
                    $v=$rs?$rs->fetchColumn():'';
                    if(is_numeric($v))$rate=min(0.5,max(0,floatval($v)/100));
                }catch(Exception $e){}
                try{
                    $rs=$db->query("SELECT `value` FROM settings WHERE `key`='referral_bonus_max'");
                    $v=$rs?$rs->fetchColumn():'';
                    if(is_numeric($v))$maxCap=intval($v);
                }catch(Exception $e){}
                $refBonus=min(intval($dep['nominal']*$rate),$maxCap);
                if($refBonus>0){
                    $refBal=intval($refUser['balance']);$refAfter=$refBal+$refBonus;
                    $db->prepare("INSERT INTO transactions(user_id,type,amount,balance_before,balance_after,ref_id,note) VALUES(?,?,?,?,?,?,?)")
                       ->execute([$refUser['id'],'referral',$refBonus,$refBal,$refAfter,$txId,'Referral bonus dari deposit pertama downline']);
                    $db->prepare("UPDATE users SET balance=? WHERE id=?")->execute([$refAfter,$refUser['id']]);
                }
            }
        }

        $db->commit();

        // ═══ PUSH NOTIFICATION: notify user deposit success ═══
        try{
            require_once __DIR__.'/../includes/webpush.php';
            $nominalFmt=number_format($dep['nominal'],0,',','.');
            $title='💰 Deposit Berhasil!';
            $body="Saldo Rp $nominalFmt sudah masuk ke akunmu. Yuk main sekarang!";
            if($dep['bonus_amount']>0){
                $bonusFmt=number_format($dep['bonus_amount'],0,',','.');
                $body="Saldo Rp $nominalFmt + Bonus Rp $bonusFmt sudah masuk!";
            }
            pushNotify($db,$dep['user_id'],$title,$body,'/dashboard.php');
        }catch(Exception $e){/* jangan blokir */}

    }catch(Exception $e){
        $db->rollBack();
        try{$db->prepare("DELETE FROM deposit_credits WHERE tx_id=?")->execute([$txId]);}catch(Exception $e2){}
        @file_put_contents(__DIR__.'/../deposit_log.txt',
            date('Y-m-d H:i:s')." [$source] CREDIT_FAIL tx=$txId: ".$e->getMessage()."\n",FILE_APPEND);
        return['status'=>'error','error'=>$e->getMessage()];
    }

    @file_put_contents(__DIR__.'/../deposit_log.txt',
        date('Y-m-d H:i:s')." [$source] CREDITED tx=$txId uid=".$dep['user_id']." nominal=".$dep['nominal']."\n",FILE_APPEND);

    // Post-credit (non-critical)
    try{
        autoMemo($db,$dep['user_id'],'Deposit Berhasil','Deposit Rp '.number_format($dep['nominal'],0,',','.').' berhasil masuk ke saldo.'.($dep['bonus_amount']>0?' Bonus: Rp '.number_format($dep['bonus_amount'],0,',','.'):''));
        $db->exec("CREATE TABLE IF NOT EXISTS spin_tickets (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,used TINYINT DEFAULT 0,deposit_id INT UNSIGNED DEFAULT NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
        $db->prepare("INSERT INTO spin_tickets(user_id,deposit_id) VALUES(?,?)")->execute([$dep['user_id'],$dep['id']]);
    }catch(Exception $e){}

    return['status'=>'credited','dep'=>$dep];
}

// ═══════════════════════════════════════════════════════════════════════
// CHECK SQX & CREDIT — polling helper
// ═══════════════════════════════════════════════════════════════════════
function checkSqxAndCredit($db,$dep,$source='check'){
    $txId=$dep['tx_id'];

    if($dep['status']==='paid'){
        creditIfPaid($db,$txId,$source.'_already_paid');
        return'paid';
    }

    $url=SQX_URL.'?action=check&merchant_uid='.SQX_MERCHANT.'&tx_id='.urlencode($txId);
    $ch=curl_init($url);
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_TIMEOUT=>15,
        CURLOPT_CONNECTTIMEOUT=>5,
        CURLOPT_SSL_VERIFYPEER=>false
    ]);
    $raw=curl_exec($ch);
    $errMsg=curl_error($ch);
    curl_close($ch);

    if(!$raw){
        @file_put_contents(__DIR__.'/../callback_log.txt',
            "[".date('Y-m-d H:i:s')."] CHECK_ERR tx=$txId curl_err=$errMsg\n",FILE_APPEND);
        return'error';
    }
    $res=json_decode($raw,true);
    if(!$res){
        @file_put_contents(__DIR__.'/../callback_log.txt',
            "[".date('Y-m-d H:i:s')."] CHECK_BADJSON tx=$txId raw=".substr($raw,0,200)."\n",FILE_APPEND);
        return'error';
    }

    $status=strtolower($res['status']??'');
    $code=strtoupper($res['code']??'');

    // Log poll juga (biar bisa trace)
    @file_put_contents(__DIR__.'/../callback_log.txt',
        "[".date('Y-m-d H:i:s')."] CHECK tx=$txId status=$status code=$code source=$source\n",FILE_APPEND);

    // PAID detection — multiple format
    $isPaid=($status==='paid'||$status==='success'||$status==='completed'||$code==='TX_PAID');

    if($isPaid){creditIfPaid($db,$txId,$source);return'paid';}
    if($status==='expired'||$code==='TX_EXPIRED'){
        $db->prepare("UPDATE deposits SET status='expired' WHERE id=? AND status='pending'")->execute([$dep['id']]);
        return'expired';
    }
    return'pending';
}

// ═══════════════════════════════════════════════════════════════════════
// ACTIONS
// ═══════════════════════════════════════════════════════════════════════

if($action==='create'){
    $uid=auth();
    $nominal=intval($d['nominal']??0);
    $methodPicked=strtolower($d['method']??'qris');  // method yg user pilih (untuk display)
    $type=$d['type']??'regular';
    $bonusId=intval($d['bonus_id']??0);

    if($nominal<10000)err('Minimal deposit Rp 10.000');
    if($nominal>10000000)err('Maksimal deposit Rp 10.000.000');

    $pending=$db->prepare("SELECT COUNT(*) FROM deposits WHERE user_id=? AND status='pending'");
    $pending->execute([$uid]);
    if($pending->fetchColumn()>=3)err('Maksimal 3 deposit pending. Selesaikan pembayaran yang ada dulu.');

    $bonusAmt=0;
    if($bonusId>0){
        $bq=$db->prepare("SELECT * FROM bonuses WHERE id=? AND active=1");$bq->execute([$bonusId]);
        $bonus=$bq->fetch();
        if($bonus){
            $pct=floatval($bonus['percent']??0);
            $max=intval($bonus['max_amount']??0);
            $bonusAmt=intval($nominal*$pct/100);
            if($max>0&&$bonusAmt>$max)$bonusAmt=$max;
        }
    }

    // ═══ FORCE QRIS: apapun method yg user pilih, kirim ke SQX sebagai QRIS ═══
    // QRIS bisa di-scan dari SEMUA app (DANA, OVO, GoPay, Mobile Banking, dll)
    // Jadi user pilih DANA → tetap dapat QR, tinggal scan via DANA
    $method='qris';

    $payload=['action'=>'create','merchant_uid'=>SQX_MERCHANT,'nominal'=>$nominal];
    // Sengaja TIDAK kirim 'method' field → SQX default ke QRIS

    $ch=curl_init(SQX_URL);
    curl_setopt_array($ch,[
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>json_encode($payload),
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_CONNECTTIMEOUT=>5,
        CURLOPT_TIMEOUT=>15,
        CURLOPT_SSL_VERIFYPEER=>false
    ]);
    $res=curl_exec($ch);curl_close($ch);
    $sqx=json_decode($res,true);

    if(!$sqx||empty($sqx['success'])){
        err($sqx['message']??'Payment gateway sedang bermasalah, coba lagi');
    }

    $txId=$sqx['tx_id']??'';
    if(!$txId)err('SQX tidak memberikan tx_id');

    // Spec terbaru:
    // - "nominal" = nominal asli (yg user request)
    // - "pay_amount" = yang HARUS dibayar customer (untuk e-wallet bisa berbeda 1-3 rupiah karena offset/identifier)
    $payAmount=intval($sqx['pay_amount']??$sqx['nominal']??$nominal);
    $sqxOffset=intval($sqx['offset']??($payAmount-$nominal));
    // Spec: timeout default 60 menit, tapi merchant bisa di-config sampai 720
    $expires=$sqx['expires_at']??date('Y-m-d H:i:s',strtotime('+60 minutes'));

    $payData=[
        'tx_id'=>$txId,'method'=>$method,'nominal'=>$nominal,
        'pay_amount'=>$payAmount,'offset'=>$sqxOffset,'expires_at'=>$expires
    ];
    if($method==='qris'){
        $payData['qr_url']=$sqx['qr_url']??null;
        $payData['qris_string']=$sqx['qris_string']??null;
        $payData['pay_type']='qris';
    }else{
        $payData['account_name']=$sqx['account_name']??null;
        $payData['account_number']=$sqx['account_number']??null;
        $payData['pay_type']='transfer';
    }

    $payUrl=$payData['qr_url']??null;
    $db->prepare("INSERT INTO deposits(user_id,tx_id,method,type,nominal,pay_amount,bonus_id,bonus_amount,pay_url,pay_data,status,expires_at) VALUES(?,?,?,?,?,?,?,?,?,?,'pending',?)")
       ->execute([$uid,$txId,$method,$type,$nominal,$payAmount,$bonusId>0?$bonusId:null,$bonusAmt,$payUrl,json_encode($payData),$expires]);
    $depId=$db->lastInsertId();

    autoMemo($db,$uid,'Deposit Pending','Deposit Rp '.number_format($nominal,0,',','.').' via '.strtoupper($method).' menunggu pembayaran. Bayar: Rp '.number_format($payAmount,0,',','.'));

    // timeout menit dari SQX (720 per spec terbaru) — frontend pake buat timer
    $sqxTimeout=intval($sqx['timeout']??720);
    ok(['deposit_id'=>$depId,'tx_id'=>$txId,'nominal'=>$nominal,'bonus'=>$bonusAmt,'expires_at'=>$expires,'timeout'=>$sqxTimeout]+$payData);
}

// ─── CALLBACK WEBHOOK dari SQX ───
// Spec terbaru body callback:
// {event:"payment.success", tx_id, nominal, nominal_paid, status:"paid", paid_at, paid_via, merchant_id, metadata}
if($action==='callback'){
    // WAJIB return HTTP 200 per spec SQX, atau mereka retry 5x
    http_response_code(200);
    header('Content-Type: application/json');

    // RAW BODY for debug
    $rawBody=file_get_contents('php://input');

    $txId=trim($d['tx_id']??'');
    $status=strtolower($d['status']??'');
    $event=$d['event']??'';
    $paidVia=trim($d['paid_via']??'');
    $paidAt=$d['paid_at']??null;
    $offset=intval($d['offset']??0);
    $methodCb=trim($d['method']??''); // qris/gopay/dana/dll — per spec
    // SQX spec terbaru: callback body kirim 'nominal' = jumlah yg dibayar customer (sudah +offset)
    // Backward-compat: lama pakai 'nominal_paid' / 'pay_amount'
    $nominalPaid=intval($d['nominal_paid']??$d['pay_amount']??$d['nominal']??0);
    $merchantId=trim($d['merchant_id']??'');

    // Log selalu (raw body biar keliatan kalau format aneh)
    @file_put_contents(__DIR__.'/../callback_log.txt',
        "[".date('Y-m-d H:i:s')."] CB tx=$txId status=$status event=$event via=$paidVia paid=$nominalPaid mid=$merchantId\n  RAW: ".substr($rawBody,0,500)."\n",FILE_APPEND);

    if(!$txId){echo json_encode(['ok'=>true,'note'=>'missing_tx_id']);exit;}

    // Simpan meta
    try{
        $s=$db->prepare("SELECT pay_data FROM deposits WHERE tx_id=?");$s->execute([$txId]);
        $row=$s->fetch();
        if($row){
            $pd=json_decode($row['pay_data']??'{}',true)?:[];
            $pd['paid_via']=$paidVia;$pd['paid_at_gateway']=$paidAt;$pd['offset']=$offset;
            $pd['callback_at']=date('Y-m-d H:i:s');
            $pd['callback_status']=$status;
            $pd['callback_event']=$event;
            $pd['callback_method']=$methodCb;
            $pd['nominal_paid']=$nominalPaid;
            $pd['merchant_id']=$merchantId;
            $db->prepare("UPDATE deposits SET pay_data=? WHERE tx_id=?")
               ->execute([json_encode($pd),$txId]);
        }else{
            @file_put_contents(__DIR__.'/../callback_log.txt',
                "  WARN: tx_id $txId tidak ada di DB!\n",FILE_APPEND);
        }
    }catch(Exception $e){
        @file_put_contents(__DIR__.'/../callback_log.txt',"  EXC: ".$e->getMessage()."\n",FILE_APPEND);
    }

    // PAID detection — handle multiple format SQX
    $isPaid=($status==='paid'||$status==='success'||$status==='completed'
            ||$event==='payment.success'||$event==='payment.paid'
            ||strtolower($event)==='payment.completed');

    if($isPaid){
        $r=creditIfPaid($db,$txId,'callback');
        @file_put_contents(__DIR__.'/../callback_log.txt',
            "  RESULT: ".$r['status']."\n",FILE_APPEND);
        echo json_encode(['ok'=>true,'result'=>$r['status']]);exit;
    }

    if($status==='expired'||$event==='payment.expired'){
        $db->prepare("UPDATE deposits SET status='expired' WHERE tx_id=? AND status='pending'")->execute([$txId]);
        echo json_encode(['ok'=>true,'note'=>'marked_expired']);exit;
    }

    echo json_encode(['ok'=>true,'note'=>'ignored','status'=>$status,'event'=>$event]);exit;
}

if($action==='check'){
    $txId=$d['tx_id']??$_GET['tx_id']??'';
    if(!$txId)err('tx_id wajib');
    $s=$db->prepare("SELECT * FROM deposits WHERE tx_id=?");$s->execute([$txId]);
    $dep=$s->fetch();
    if(!$dep)err('Not found');
    $result=checkSqxAndCredit($db,$dep,'poll');
    $s2=$db->prepare("SELECT status,paid_at FROM deposits WHERE id=?");$s2->execute([$dep['id']]);
    $now=$s2->fetch();
    ok(['status'=>$now['status']??$result,'paid_at'=>$now['paid_at']??null]);
}

if($action==='confirm'){
    auth();
    $txId=$d['tx_id']??'';
    if(!$txId)err('tx_id wajib');
    $r=creditIfPaid($db,$txId,'manual_confirm');
    ok(['result'=>$r['status']]);
}

if($action==='list'){
    $uid=auth();
    $s=$db->prepare("SELECT * FROM deposits WHERE user_id=? ORDER BY created_at DESC LIMIT 50");
    $s->execute([$uid]);
    ok(['deposits'=>$s->fetchAll()]);
}

if($action==='resume'){
    $uid=auth();
    $txId=$d['tx_id']??'';
    if(!$txId)err('tx_id wajib');
    $s=$db->prepare("SELECT * FROM deposits WHERE user_id=? AND tx_id=?");
    $s->execute([$uid,$txId]);
    $dep=$s->fetch();
    if(!$dep)err('Deposit tidak ditemukan');

    if($dep['status']==='pending'){
        checkSqxAndCredit($db,$dep,'resume');
        $s=$db->prepare("SELECT * FROM deposits WHERE id=?");$s->execute([$dep['id']]);
        $dep=$s->fetch();
    }
    if($dep['status']==='paid')creditIfPaid($db,$txId,'resume_paid');

    $dep['pay_data_parsed']=json_decode($dep['pay_data']??'{}',true);
    ok(['deposit'=>$dep]);
}

if($action==='pending'){
    $uid=auth();
    $s=$db->prepare("SELECT * FROM deposits WHERE user_id=? AND status='pending' ORDER BY created_at DESC LIMIT 10");
    $s->execute([$uid]);
    $list=$s->fetchAll();
    foreach($list as &$dep)checkSqxAndCredit($db,$dep,'pending_list');
    $s2=$db->prepare("SELECT tx_id,method,type,nominal,pay_amount,bonus_amount,status,expires_at,created_at,pay_data FROM deposits WHERE user_id=? AND status='pending' ORDER BY created_at DESC LIMIT 10");
    $s2->execute([$uid]);
    $rows=$s2->fetchAll();
    foreach($rows as &$r)$r['pay_data_parsed']=json_decode($r['pay_data']??'{}',true);
    // Return key 'deposits' biar match dengan frontend loadPendingDeposits()
    ok(['deposits'=>$rows]);
}

if($action==='reconcile_mine'){
    $uid=auth();
    $s=$db->prepare("SELECT * FROM deposits WHERE user_id=? AND status='pending' ORDER BY created_at DESC LIMIT 20");
    $s->execute([$uid]);
    $list=$s->fetchAll();
    $summary=['total'=>count($list),'paid'=>0,'expired'=>0,'pending'=>0,'error'=>0];
    foreach($list as $dep){
        $r=checkSqxAndCredit($db,$dep,'user_reconcile');
        $summary[$r]=($summary[$r]??0)+1;
    }
    // Auto-expire >24 jam (spec SQX timeout 12 jam, kasih buffer 2x)
    try{
        $db->prepare("UPDATE deposits SET status='expired'
                      WHERE user_id=? AND status='pending'
                        AND expires_at IS NOT NULL
                        AND expires_at < NOW() - INTERVAL 24 HOUR")->execute([$uid]);
    }catch(Exception $e){}
    ok(['summary'=>$summary]);
}

if($action==='ping_gateway'){
    $ch=curl_init(SQX_URL.'?action=ping');
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>5]);
    $r=json_decode(curl_exec($ch),true);curl_close($ch);
    if($r)ok(['status'=>$r['status']??'ok']);
    err('SquadOnyx tidak merespons');
}

// ─── METHODS: list metode pembayaran dari SQX ───
if($action==='methods'){
    $ch=curl_init(SQX_URL.'?action=methods&merchant_uid='.SQX_MERCHANT);
    curl_setopt_array($ch,[
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_TIMEOUT=>8,
        CURLOPT_SSL_VERIFYPEER=>false
    ]);
    $raw=curl_exec($ch);curl_close($ch);
    $r=json_decode($raw,true);
    // Fallback default kalau SQX ga balas
    if(!$r||!is_array($r)){
        ok(['methods'=>['qris'=>['name'=>'QRIS','status'=>1]]]);
    }
    // Bisa format {success,methods:[...]} atau langsung methods, normalize
    if(isset($r['methods'])){ok(['methods'=>$r['methods']]);}
    elseif(isset($r['data'])){ok(['methods'=>$r['data']]);}
    else{ok(['methods'=>$r]);}
}

if($action==='test_callback'){
    $proto=(($_SERVER['HTTPS']??'')==='on'||($_SERVER['SERVER_PORT']??'')==='443')?'https':'http';
    $host=$_SERVER['HTTP_HOST']??'localhost';
    // Pake path absolute /api/deposit.php?action=callback (bukan dirname yg bisa salah)
    $url=$proto.'://'.$host.'/api/deposit.php?action=callback';
    ok([
        'callback_url'=>$url,
        'note'=>'Copy URL ini, paste ke panel SquadOnyx → Settings → Callback URL → Save',
        'merchant_uid'=>defined('SQX_MERCHANT')?SQX_MERCHANT:'NOT_SET',
        'sqx_endpoint'=>defined('SQX_URL')?SQX_URL:'NOT_SET',
        'instructions'=>[
            '1. Login ke panel.squadonyx.biz.id',
            '2. Settings → Callback URL → paste URL di atas',
            '3. Save',
            '4. Test: bayar deposit → cek di Log CB harus ada entry "CB tx=..."',
        ]
    ]);
}

// ─── LIHAT LOG CALLBACK (admin only) ───
if($action==='callback_log'){
    $uid=auth();
    $u=$db->prepare("SELECT role FROM users WHERE id=?");$u->execute([$uid]);
    if(($u->fetchColumn()??'')!=='admin')err('ADMIN_ONLY');
    $logFile=__DIR__.'/../callback_log.txt';
    if(!file_exists($logFile))$log='(belum ada callback masuk)';
    else{
        $lines=file($logFile,FILE_IGNORE_NEW_LINES);
        $log=implode("\n",array_slice($lines,-200));
    }
    $proto=(($_SERVER['HTTPS']??'')==='on'||($_SERVER['SERVER_PORT']??'')==='443')?'https':'http';
    $cbUrl=$proto.'://'.($_SERVER['HTTP_HOST']??'localhost').'/api/deposit.php?action=callback';
    $totalLines=isset($lines)?count($lines):0;
    ok(['log'=>$log,'total_lines'=>$totalLines,'callback_url'=>$cbUrl]);
}

// ─── CLEAR LOG CALLBACK ───
if($action==='clear_callback_log'){
    $uid=auth();
    $u=$db->prepare("SELECT role FROM users WHERE id=?");$u->execute([$uid]);
    if(($u->fetchColumn()??'')!=='admin')err('ADMIN_ONLY');
    @unlink(__DIR__.'/../callback_log.txt');
    ok(['cleared'=>true]);
}

// ─── FORCE CHECK ALL PENDING — admin tool buat sweep ulang semua pending depo ke SQX ───
if($action==='force_check_all'){
    $uid=auth();
    $u=$db->prepare("SELECT role FROM users WHERE id=?");$u->execute([$uid]);
    if(($u->fetchColumn()??'')!=='admin')err('ADMIN_ONLY');

    $q=$db->query("SELECT * FROM deposits WHERE status='pending' ORDER BY created_at DESC LIMIT 50");
    $results=['paid'=>0,'expired'=>0,'pending'=>0,'error'=>0,'detail'=>[]];
    foreach($q->fetchAll() as $dep){
        $r=checkSqxAndCredit($db,$dep,'admin_force');
        $results[$r]=($results[$r]??0)+1;
        $results['detail'][]=['tx_id'=>$dep['tx_id'],'user_id'=>$dep['user_id'],'nominal'=>$dep['nominal'],'result'=>$r,'created_at'=>$dep['created_at']];
    }
    ok($results);
}

err('INVALID_ACTION');
