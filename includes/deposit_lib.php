<?php
// ═══════════════════════════════════════════════════════════════════════
// DEPOSIT LIB — wrapper untuk backward compat
// Function inti udah pindah ke api/deposit.php. File ini cuma re-export.
// ═══════════════════════════════════════════════════════════════════════

// Kalau function belum loaded, load dari api/deposit.php header only
if(!function_exists('creditIfPaid')){
    // Parse functions dari api/deposit.php — ambil cuma bagian function declarations
    $_depFile=__DIR__.'/../api/deposit.php';
    if(file_exists($_depFile)){
        // Include safely dengan guard: cuma declare functions, skip action handlers
        if(!defined('_DEPOSIT_LIB_LOADING')){
            define('_DEPOSIT_LIB_LOADING',true);
            // Kita ga bisa include langsung (akan trigger action handler)
            // Jadi kita copy function declarations di sini (duplicate, tapi aman)
        }
    }
}

// Load functions inline kalau belum ada
if(!function_exists('creditIfPaid')){

function creditIfPaid($db,$txId,$source='unknown'){
    $s=$db->prepare("SELECT * FROM deposits WHERE tx_id=?");
    $s->execute([$txId]);
    $dep=$s->fetch();
    if(!$dep)return['status'=>'not_found'];

    if($dep['status']==='paid'){
        $chk=$db->prepare("SELECT id FROM transactions WHERE ref_id=? AND type='deposit'");
        $chk->execute([$txId]);
        if($chk->fetch())return['status'=>'already_credited','dep'=>$dep];
    }

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
        $chk=$db->prepare("SELECT id FROM transactions WHERE ref_id=? AND type='deposit' FOR UPDATE");
        $chk->execute([$txId]);
        if($chk->fetch()){$db->commit();return['status'=>'already_credited','dep'=>$dep];}

        if($dep['bonus_id']){
            $q=$db->prepare("SELECT total_turnover FROM users WHERE id=?");$q->execute([$dep['user_id']]);
            $cTO=intval($q->fetchColumn());
            $db->prepare("UPDATE deposits SET status='paid',paid_at=NOW(),turnover_at_deposit=? WHERE id=?")
               ->execute([$cTO,$dep['id']]);
        }else{
            $db->prepare("UPDATE deposits SET status='paid',paid_at=NOW() WHERE id=?")
               ->execute([$dep['id']]);
        }

        $u=$db->prepare("SELECT balance FROM users WHERE id=? FOR UPDATE");
        $u->execute([$dep['user_id']]);
        $bal=intval($u->fetchColumn());
        $after=$bal+intval($dep['nominal']);
        $db->prepare("INSERT INTO transactions(user_id,type,amount,balance_before,balance_after,ref_id,note) VALUES(?,?,?,?,?,?,?)")
           ->execute([$dep['user_id'],'deposit',$dep['nominal'],$bal,$after,$txId,'Deposit '.$dep['method']]);
        $db->prepare("UPDATE users SET balance=? WHERE id=?")->execute([$after,$dep['user_id']]);

        if($dep['bonus_amount']>0){
            $bal2=$after;$after2=$bal2+intval($dep['bonus_amount']);
            $db->prepare("INSERT INTO transactions(user_id,type,amount,balance_before,balance_after,ref_id,note) VALUES(?,?,?,?,?,?,?)")
               ->execute([$dep['user_id'],'bonus',$dep['bonus_amount'],$bal2,$after2,$txId,'Bonus deposit']);
            $db->prepare("UPDATE users SET balance=? WHERE id=?")->execute([$after2,$dep['user_id']]);
        }

        $db->prepare("UPDATE users SET total_deposit=total_deposit+? WHERE id=?")
           ->execute([$dep['nominal'],$dep['user_id']]);

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
    }catch(Exception $e){
        $db->rollBack();
        try{$db->prepare("DELETE FROM deposit_credits WHERE tx_id=?")->execute([$txId]);}catch(Exception $e2){}
        @file_put_contents(__DIR__.'/../deposit_log.txt',
            date('Y-m-d H:i:s')." [$source] CREDIT_FAIL tx=$txId: ".$e->getMessage()."\n",FILE_APPEND);
        return['status'=>'error','error'=>$e->getMessage()];
    }

    @file_put_contents(__DIR__.'/../deposit_log.txt',
        date('Y-m-d H:i:s')." [$source] CREDITED tx=$txId uid=".$dep['user_id']." nominal=".$dep['nominal']."\n",FILE_APPEND);

    try{
        if(function_exists('autoMemo')){
            autoMemo($db,$dep['user_id'],'Deposit Berhasil','Deposit Rp '.number_format($dep['nominal'],0,',','.').' berhasil masuk ke saldo.'.($dep['bonus_amount']>0?' Bonus: Rp '.number_format($dep['bonus_amount'],0,',','.'):''));
        }
        $db->exec("CREATE TABLE IF NOT EXISTS spin_tickets (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,used TINYINT DEFAULT 0,deposit_id INT UNSIGNED DEFAULT NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
        $db->prepare("INSERT INTO spin_tickets(user_id,deposit_id) VALUES(?,?)")->execute([$dep['user_id'],$dep['id']]);
    }catch(Exception $e){}

    return['status'=>'credited','dep'=>$dep];
}

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

    ]);
    $raw=curl_exec($ch);
    $errMsg=curl_error($ch);
    curl_close($ch);
    if(!$raw){
        @file_put_contents(__DIR__.'/../callback_log.txt',
            "[".date('Y-m-d H:i:s')."] LIB_CHECK_ERR tx=$txId curl=$errMsg\n",FILE_APPEND);
        return'error';
    }
    $res=json_decode($raw,true);
    if(!$res){
        @file_put_contents(__DIR__.'/../callback_log.txt',
            "[".date('Y-m-d H:i:s')."] LIB_CHECK_BADJSON tx=$txId raw=".substr($raw,0,200)."\n",FILE_APPEND);
        return'error';
    }

    $status=strtolower($res['status']??'');
    $code=strtoupper($res['code']??'');

    @file_put_contents(__DIR__.'/../callback_log.txt',
        "[".date('Y-m-d H:i:s')."] LIB_CHECK tx=$txId status=$status code=$code source=$source\n",FILE_APPEND);

    // PAID detection — multiple format
    $isPaid=($status==='paid'||$status==='success'||$status==='completed'||$code==='TX_PAID');

    if($isPaid){creditIfPaid($db,$txId,$source);return'paid';}
    if($status==='expired'||$code==='TX_EXPIRED'){
        $db->prepare("UPDATE deposits SET status='expired' WHERE id=? AND status='pending'")->execute([$dep['id']]);
        return'expired';
    }
    return'pending';
}

// ═══ BACKWARD COMPAT WRAPPERS (pake nama lama di code existing) ═══
function creditDeposit($db,$dep,$paidAmountFromGateway=null,$source='unknown'){
    $r=creditIfPaid($db,$dep['tx_id']??'',$source);
    return in_array($r['status'],['credited','already_credited','race_avoided']);
}

function reconcileDeposit($db,$dep,$source='reconcile'){
    return checkSqxAndCredit($db,$dep,$source);
}

} // end if(!function_exists)
