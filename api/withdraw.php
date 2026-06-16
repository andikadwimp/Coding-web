<?php
require_once __DIR__.'/../includes/config.php';
$d=input();$action=$d['action']??$_GET['action']??'';

if($action==='create'){
    $uid=auth();
    $amount=intval($d['amount']??0);
    $minWd=intval($db->query("SELECT value FROM settings WHERE `key`='min_withdraw'")->fetchColumn()?:50000);
    if($amount<$minWd)err('Minimal withdraw Rp '.number_format($minWd,0,',','.'));

    // ═══ FILE LOCK: cegah double-click dari user yg sama ═══
    $lockFile=sys_get_temp_dir()."/lx_wdlock_$uid.lock";
    $lockFp=@fopen($lockFile,'c');
    if(!$lockFp)err('Sistem sibuk, coba lagi sebentar');
    if(!flock($lockFp,LOCK_EX|LOCK_NB)){
        fclose($lockFp);
        err('Sedang memproses withdraw sebelumnya. Tunggu beberapa detik lalu refresh.');
    }

    try{
        // ═══ DB TRANSACTION dengan row lock ═══
        $db->beginTransaction();

        // Lock user row supaya saldo kebaca atomic
        $u=$db->prepare("SELECT username,balance,total_turnover FROM users WHERE id=? FOR UPDATE");
        $u->execute([$uid]);$user=$u->fetch();
        if(!$user){$db->rollBack();flock($lockFp,LOCK_UN);fclose($lockFp);err('User tidak ditemukan');}
        if(intval($user['balance'])<$amount){$db->rollBack();flock($lockFp,LOCK_UN);fclose($lockFp);err('Saldo tidak cukup');}

        // Get bank account
        try{
            $ba=$db->prepare("SELECT * FROM user_banks WHERE user_id=? ORDER BY id DESC LIMIT 1");
            $ba->execute([$uid]);$bank=$ba->fetch();
        }catch(Exception $e){$bank=null;}
        if(!$bank){$db->rollBack();flock($lockFp,LOCK_UN);fclose($lockFp);err('Tambahkan rekening penarikan terlebih dahulu');}

        // Check pending withdraw (DOUBLE CHECK dalam transaction)
        $p=$db->prepare("SELECT COUNT(*) FROM withdrawals WHERE user_id=? AND status='pending'");
        $p->execute([$uid]);
        if($p->fetchColumn()>0){$db->rollBack();flock($lockFp,LOCK_UN);fclose($lockFp);err('Masih ada withdraw pending, tunggu diproses dulu');}

        // Sync turnover (ini call ke Nexus API, tapi kita udah dalam transaction — OK, cuma read)
        $totalBet=0;$anySuccess=false;
        $startDate=date('Y-m-d',strtotime($user['username'] ? ($user['created_at']??date('Y-m-d H:i:s')) : date('Y-m-d H:i:s'))).' 00:00:00';
        // Get created_at separate (simpler)
        $uStart=$db->prepare("SELECT created_at FROM users WHERE id=?");$uStart->execute([$uid]);
        $regDate=$uStart->fetchColumn()?:date('Y-m-d H:i:s');
        $startDate=date('Y-m-d',strtotime($regDate)).' 00:00:00';
        foreach(['slot','casino'] as $gt){
            $pg=0;$cnt=0;
            do{
                $body=json_encode(['method'=>'get_game_log','agent_code'=>NEXUS_AGENT,'agent_token'=>NEXUS_TOKEN,'user_code'=>$user['username'],'game_type'=>$gt,'start'=>$startDate,'end'=>date('Y-m-d 23:59:59'),'page'=>$pg,'perPage'=>100]);
                $ch=curl_init(NEXUS_URL);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15]);
                $r=json_decode(curl_exec($ch),true);curl_close($ch);
                if(($r['status']??0)!=1)break;
                $anySuccess=true;
                $logs=$r[$gt]??[];
                foreach($logs as $l){
                    $txType=$l['txn_type']??'debit_credit';
                    if($txType!=='credit')$totalBet+=abs(floatval($l['bet_money']??$l['bet']??0));
                }
                $cnt+=count($logs);$pg++;
            }while($cnt<($r['total_count']??0)&&$pg<20);
        }
        if($anySuccess&&$totalBet>0){
            $existing=intval($user['total_turnover']);
            $newTO=max($existing,intval($totalBet));
            if($newTO!==$existing){
                $db->prepare("UPDATE users SET total_turnover=? WHERE id=?")->execute([$newTO,$uid]);
            }
            $user['total_turnover']=$newTO;
        }

        // ═══ UNIFIED TURNOVER CHECK ═══
        // Gabung TO dari: (1) deposit+bonus_id yg belum met, (2) bonus klaim event (check-in, apresiasi, dll)
        // Total target = jumlah semua sisa TO yg belum terpenuhi
        // User tinggal main sampai total bet melewati total target → WD allowed
        require_once __DIR__.'/../includes/bonus_to.php';
        bonusTO_ensureTable($db);

        $curTO=intval($user['total_turnover']);
        $totalRemaining=0;
        $pendingDeps=[]; // deposit+bonus yg belum met, buat update setelah pass

        // (1) Deposit+bonus_id pending TO
        $bonusDeps=$db->prepare("SELECT d.id,d.nominal,d.bonus_amount,d.turnover_at_deposit,d.turnover_met,b.turnover_x
            FROM deposits d LEFT JOIN bonuses b ON b.id=d.bonus_id
            WHERE d.user_id=? AND d.status='paid' AND d.bonus_id IS NOT NULL AND b.turnover_x>0 AND d.turnover_met=0
            ORDER BY d.created_at ASC");
        $bonusDeps->execute([$uid]);
        foreach($bonusDeps->fetchAll() as $bd){
            $toAtDep=intval($bd['turnover_at_deposit']);
            $target=($bd['nominal']+$bd['bonus_amount'])*$bd['turnover_x'];
            $done=max(0,$curTO-$toAtDep);
            $remaining=max(0,$target-$done);
            if($remaining<=0){
                // Udah met — tandain langsung
                $db->prepare("UPDATE deposits SET turnover_met=1 WHERE id=?")->execute([$bd['id']]);
            }else{
                $totalRemaining+=$remaining;
                $pendingDeps[]=['id'=>$bd['id'],'remaining'=>$remaining];
            }
        }

        // (2) Bonus klaim event (check-in, bonus_depo, apresiasi, misteri, bantuan)
        bonusTO_update($db,$uid);
        $q=$db->prepare("SELECT id,target,turnover_done FROM bonus_turnover WHERE user_id=? AND met=0");
        $q->execute([$uid]);
        foreach($q->fetchAll() as $bc){
            $rem=max(0,intval($bc['target'])-intval($bc['turnover_done']));
            $totalRemaining+=$rem;
        }

        // Kalau masih ada sisa TO total → block WD
        if($totalRemaining>0){
            $db->rollBack();flock($lockFp,LOCK_UN);fclose($lockFp);
            err('Sisa turnover yang harus dimainkan: Rp '.number_format($totalRemaining,0,',','.').'. Silakan main dulu sebelum tarik dana.');
        }

        // All TO met → tandain semua deposit pending biar ga di-recheck
        foreach($pendingDeps as $pd){
            $db->prepare("UPDATE deposits SET turnover_met=1 WHERE id=?")->execute([$pd['id']]);
        }

        // Deduct balance via UPDATE explicit (atomic dengan check)
        $upd=$db->prepare("UPDATE users SET balance = balance - ? WHERE id=? AND balance >= ?");
        $upd->execute([$amount,$uid,$amount]);
        if($upd->rowCount()===0){
            $db->rollBack();flock($lockFp,LOCK_UN);fclose($lockFp);
            err('Saldo berubah, silakan refresh halaman.');
        }
        // Log transaction
        $newBal=intval($user['balance'])-$amount;
        $db->prepare("INSERT INTO transactions(user_id,type,amount,balance_before,balance_after,note) VALUES(?,?,?,?,?,?)")
           ->execute([$uid,'withdraw',-$amount,intval($user['balance']),$newBal,'Penarikan ke '.$bank['bank_name'].' '.$bank['acc_number']]);

        // Create withdrawal record
        try{$db->exec("CREATE TABLE IF NOT EXISTS withdrawals(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,amount BIGINT UNSIGNED,bank_name VARCHAR(50),acc_name VARCHAR(100),acc_number VARCHAR(50),status VARCHAR(20) DEFAULT 'pending',admin_note TEXT,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,processed_at DATETIME DEFAULT NULL) ENGINE=InnoDB");}catch(Exception $e){}
        $db->prepare("INSERT INTO withdrawals(user_id,amount,bank_name,acc_name,acc_number) VALUES(?,?,?,?,?)")
           ->execute([$uid,$amount,$bank['bank_name'],$bank['acc_name'],$bank['acc_number']]);
        $wdId=$db->lastInsertId();

        $db->commit();

        // Post-transaction actions (non-critical)
        updateVipLevel($db,$uid);
        autoMemo($db,$uid,'Penarikan Diajukan','Penarikan Rp '.number_format($amount,0,',','.').' ke '.$bank['bank_name'].' '.$bank['acc_number'].' sedang diproses.');

        flock($lockFp,LOCK_UN);fclose($lockFp);
        ok(['withdraw_id'=>$wdId,'balance'=>$newBal]);

    }catch(Exception $e){
        try{$db->rollBack();}catch(Exception $ee){}
        @flock($lockFp,LOCK_UN);@fclose($lockFp);
        err('Transaksi gagal: '.$e->getMessage());
    }
}

if($action==='list'){
    $uid=auth();
    $s=$db->prepare("SELECT id,amount,bank_name,acc_name,acc_number,status,admin_note,created_at,processed_at FROM withdrawals WHERE user_id=? ORDER BY created_at DESC LIMIT 50");
    $s->execute([$uid]);
    ok(['withdrawals'=>$s->fetchAll()]);
}

err('INVALID_ACTION');
