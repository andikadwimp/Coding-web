<?php
require_once __DIR__.'/../includes/config.php';
$d=input();$action=$d['action']??$_GET['action']??'';

// ── Ambil total bet dari NexusGGR sejak tanggal tertentu ──
function getBetsSince($username, $sinceDate){
    $totalBet=0;
    $start=date('Y-m-d',strtotime($sinceDate)).' 00:00:00';
    $end=date('Y-m-d 23:59:59');
    foreach(['slot','casino'] as $gt){
        $page=0;$count=0;
        do{
            $body=json_encode(['method'=>'get_game_log','agent_code'=>NEXUS_AGENT,'agent_token'=>NEXUS_TOKEN,
                'user_code'=>$username,'game_type'=>$gt,'start'=>$start,'end'=>$end,
                'page'=>$page,'perPage'=>200]);
            $ch=curl_init(NEXUS_URL);
            curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,
                CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10]);
            $raw=curl_exec($ch);curl_close($ch);
            $r=json_decode($raw,true);
            if(($r['status']??0)!=1)break;
            foreach($r[$gt]??[] as $l){
                if(($l['txn_type']??'dc')!=='credit')
                    $totalBet+=abs(floatval($l['bet_money']??$l['bet']??0));
            }
            $count+=count($r[$gt]??[]);$page++;
        }while($count<($r['total_count']??0)&&$page<10);
    }
    return intval($totalBet);
}

// ── Sync total_turnover rebate (dari tanggal daftar sampai sekarang) ──
function syncTurnoverFast($db,$uid,$username){
    // Ambil tanggal daftar user
    $q=$db->prepare("SELECT created_at,total_turnover FROM users WHERE id=?");$q->execute([$uid]);
    $row=$q->fetch();
    $regDate=date('Y-m-d',strtotime($row['created_at']??date('Y-m-d')));
    $existing=intval($row['total_turnover']??0);
    // Sync semua bet dari tanggal daftar sampai sekarang
    $bet=getBetsSince($username,$regDate);
    if($bet>0){
        // Pakai nilai terbesar (tidak pernah turun)
        $newTO=max($existing,$bet);
        $db->prepare("UPDATE users SET total_turnover=? WHERE id=?")->execute([$newTO,$uid]);
        return $newTO;
    }
    return $existing;
}


// ─── MEMO ───
if($action==='memo_inbox'){
    $uid=auth();
    $s=$db->prepare("SELECT m.*,mr.read_at FROM memos m LEFT JOIN memo_reads mr ON mr.memo_id=m.id AND mr.user_id=? WHERE m.type='all' OR m.type='permanent' OR (m.type='target' AND m.to_user_id=?) ORDER BY m.created_at DESC LIMIT 50");
    $s->execute([$uid,$uid]);
    ok(['memos'=>$s->fetchAll()]);
}
if($action==='memo_read'){
    $uid=auth();$mid=intval($d['memo_id']??0);
    $db->prepare("INSERT IGNORE INTO memo_reads(memo_id,user_id) VALUES(?,?)")->execute([$mid,$uid]);
    ok();
}
if($action==='memo_announcements'){
    $uid=auth();
    $s=$db->query("SELECT * FROM memos WHERE type='permanent' ORDER BY created_at DESC");
    ok(['memos'=>$s->fetchAll()]);
}

// ─── REFERRAL ───
if($action==='referral_downline'){
    $uid=auth();
    $u=$db->prepare("SELECT ref_code FROM users WHERE id=?");$u->execute([$uid]);$ref=$u->fetchColumn();
    if(!$ref)ok(['downlines'=>[],'ref_code'=>'']);

    // Auto-add kolom last_turnover_sync
    try{$db->exec("ALTER TABLE users ADD COLUMN last_turnover_sync DATETIME DEFAULT NULL AFTER total_turnover");}catch(Exception $e){}

    // Sync TO untuk downline yang udah depo tapi belum pernah/lama di-sync (max 10 per call, rate-limit 5 menit)
    if(defined('NEXUS_URL')&&NEXUS_URL){
        $sy=$db->prepare("SELECT id,username FROM users WHERE referred_by=? AND total_deposit>0 AND (last_turnover_sync IS NULL OR last_turnover_sync<DATE_SUB(NOW(),INTERVAL 5 MINUTE)) ORDER BY last_turnover_sync ASC LIMIT 10");
        $sy->execute([$ref]);
        foreach($sy->fetchAll() as $dl){
            syncTurnoverFast($db,$dl['id'],$dl['username']);
            $db->prepare("UPDATE users SET last_turnover_sync=NOW() WHERE id=?")->execute([$dl['id']]);
        }
    }

    // Query downline + total_turnover (udah fresh dari sync di atas)
    $s=$db->prepare("SELECT id,username,created_at,total_deposit,total_turnover,last_login,status FROM users WHERE referred_by=? ORDER BY created_at DESC");
    $s->execute([$ref]);
    $list=[];
    foreach($s->fetchAll() as $r){
        $fd=$db->prepare("SELECT MIN(created_at) FROM deposits WHERE user_id=? AND status='paid'");$fd->execute([$r['id']]);
        $ld=$db->prepare("SELECT MAX(created_at) FROM deposits WHERE user_id=? AND status='paid'");$ld->execute([$r['id']]);
        $r['first_deposit']=$fd->fetchColumn();
        $r['last_deposit']=$ld->fetchColumn();
        $list[]=$r;
    }
    ok(['downlines'=>$list,'ref_code'=>$ref]);
}
if($action==='referral_bonus'){
    $uid=auth();
    $s=$db->prepare("SELECT * FROM transactions WHERE user_id=? AND type='referral' ORDER BY created_at DESC LIMIT 50");
    $s->execute([$uid]);
    ok(['bonuses'=>$s->fetchAll()]);
}

// ─── TRANSACTIONS ───

// ─── LEVEL ───
if($action==='level_info'){
    $uid=auth();
    $u=$db->prepare("SELECT username,vip_level,total_deposit,total_turnover,created_at FROM users WHERE id=?");$u->execute([$uid]);$user=$u->fetch();
    // Quick sync turnover from NexusGGR
    $totalBet=0;$totalWin=0;$totalRecords=0;
    foreach(['slot','casino'] as $gt){
        $page=0;$count=0;
        do{
            $body=json_encode(['method'=>'get_game_log','agent_code'=>NEXUS_AGENT,'agent_token'=>NEXUS_TOKEN,'user_code'=>$user['username'],'game_type'=>$gt,'start'=>$user['created_at']?:'2026-01-01 00:00:00','end'=>date('Y-m-d 23:59:59'),'page'=>$page,'perPage'=>100]);
            $ch=curl_init(NEXUS_URL);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15]);
            $r=json_decode(curl_exec($ch),true);curl_close($ch);
            if(($r['status']??0)!=1)break;
            $logs=$r[$gt]??[];
            foreach($logs as $l){$totalBet+=abs(floatval(($l['bet_money']??$l['bet']??0)));$totalWin+=abs(floatval(($l['win_money']??$l['win']??0)));}
            $count+=count($logs);$totalRecords+=count($logs);$page++;
        }while($count<($r['total_count']??0)&&$page<20);
    }
    if($totalBet>0){
        $db->prepare("UPDATE users SET total_turnover=? WHERE id=?")->execute([intval($totalBet),$uid]);
        $user['total_turnover']=intval($totalBet);
    }
    // Update VIP level
    $lvResult=updateVipLevel($db,$uid);
    $vipLevel=$lvResult[1];
    // Check claim status
    $claims=[];
    foreach(['daily','weekly','monthly'] as $ct){
        if($ct==='daily')$period=date('Y-m-d');
        elseif($ct==='weekly')$period=date('Y-W');
        else $period=date('Y-m');
        $chk=$db->prepare("SELECT id FROM vip_claims WHERE user_id=? AND claim_type=? AND period=?");
        $chk->execute([$uid,$ct,$period]);
        $claims[$ct]=$chk->fetch()?true:false;
    }
    // Fetch current balance
    $ub=$db->prepare("SELECT balance FROM users WHERE id=?");$ub->execute([$uid]);$balance=intval($ub->fetchColumn());
    ok(['level'=>['username'=>$user['username'],'vip_level'=>$vipLevel,'total_deposit'=>intval($user['total_deposit']),'total_turnover'=>intval($user['total_turnover']),'total_win'=>intval($totalWin),'game_records'=>$totalRecords,'created_at'=>$user['created_at']],'balance'=>$balance]);
}

if($action==='claim_vip'){
    err('Fitur gaji VIP tidak tersedia. Bonus hanya diberikan saat naik level.');
    $uid=auth();
    $type=trim($d['type']??'');
    if(!in_array($type,['daily','weekly','monthly']))err('Tipe klaim tidak valid');
    $result=claimVipBonus($db,$uid,$type);
    if($result['ok']){
        // Refresh user data
        $u=$db->prepare("SELECT * FROM users WHERE id=?");$u->execute([$uid]);$usr=$u->fetch();
        unset($usr['password']);
        ok(['amount'=>$result['amount'],'balance'=>$result['balance'],'vip_level'=>$result['vip_level'],'user'=>$usr]);
    }else{
        err($result['error']);
    }
}

// ─── CLAIM APRESIASI ANGGOTA ───
if($action==='claim_apresiasi'){
    $uid=auth();
    $period=date('Y-m');

    // Check already claimed
    $chk=$db->prepare("SELECT id FROM vip_claims WHERE user_id=? AND claim_type='apresiasi' AND period=?");
    $chk->execute([$uid,$period]);
    if($chk->fetch())err('Sudah diklaim bulan ini');

    // Tiers (harus sama persis dengan apresiasi.php display)
    $TIERS=[[100,5],[300,18],[500,35],[1000,80],[2000,180],[3000,300],[5000,550],[8000,950],[15000,1800],[30000,3800],[50000,6800],[80000,10800],[150000,22000],[300000,48000],[500000,88000],[800000,168000],[1000000,258000]];

    // Get monthly deposit
    $md=$db->prepare("SELECT COALESCE(SUM(nominal),0) FROM deposits WHERE user_id=? AND status='paid' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())");
    $md->execute([$uid]);
    $depoK=floor($md->fetchColumn()/1000);

    $reward=0;
    foreach($TIERS as $t){if($depoK>=$t[0])$reward=$t[1];}
    if($reward<=0)err('Deposit bulan ini belum mencukupi');

    $rewardRp=$reward*1000;

    $db->beginTransaction();
    try{
        // Insert claim record
        $ins=$db->prepare("INSERT INTO vip_claims(user_id,claim_type,period,vip_level,amount) VALUES(?,?,?,?,?)");
        $ins->execute([$uid,'apresiasi',$period,0,$rewardRp]);

        // Add balance
        $db->prepare("UPDATE users SET balance=balance+? WHERE id=?")->execute([$rewardRp,$uid]);

        // Log transaction
        $db->prepare("INSERT INTO transactions(user_id,type,amount,note,created_at) VALUES(?,?,?,?,NOW())")->execute([$uid,'bonus',$rewardRp,'Apresiasi Anggota '.$period]);

        // Track TO — bonus wajib 1x turnover sebelum WD
        require_once __DIR__.'/../includes/bonus_to.php';
        bonusTO_track($db,$uid,'apresiasi',$rewardRp,'Apresiasi '.$period,1);

        $db->commit();

        $bal=$db->prepare("SELECT balance FROM users WHERE id=?");$bal->execute([$uid]);
        ok(['amount'=>$rewardRp,'balance'=>$bal->fetchColumn()]);
    }catch(Exception $e){
        $db->rollBack();
        err('Gagal memproses klaim');
    }
}

// ─── CLAIM BONUS MISTERI ───
if($action==='claim_misteri'){
    $uid=auth();
    $day=(int)($d['day']??0);
    $validDays=[2,3,7,15,30];
    if(!in_array($day,$validDays))err('Milestone tidak valid');

    // User reg date
    $u=$db->prepare("SELECT created_at FROM users WHERE id=?");$u->execute([$uid]);$usr=$u->fetch();
    $regTs=strtotime($usr['created_at']);

    // Calculate cycle
    $cycleStart=$regTs;$now=time();
    while($cycleStart+32*86400<$now)$cycleStart+=32*86400;
    $dayInCycle=max(1,floor(($now-$cycleStart)/86400)+1);
    $cycleId=date('Y-m-d',$cycleStart);

    if($dayInCycle<$day)err('Belum mencapai hari ke-'.$day);

    // Already claimed?
    $chk=$db->prepare("SELECT id FROM vip_claims WHERE user_id=? AND claim_type='misteri' AND period=? AND vip_level=?");
    $chk->execute([$uid,$cycleId,$day]);
    if($chk->fetch())err('Milestone hari ke-'.$day.' sudah diklaim');

    // Deposit tiers
    $TIERS=[[60,0.72,258],[200,1.45,588],[400,2.90,1188],[800,7.25,1688],[1700,14.50,2588],[2900,21.75,5688],[5800,50.75,8888],[14500,127.60,25778]];

    $md=$db->prepare("SELECT COALESCE(SUM(nominal),0) FROM deposits WHERE user_id=? AND status='paid' AND created_at>=?");
    $md->execute([$uid,date('Y-m-d H:i:s',$cycleStart)]);
    $depoK=floor($md->fetchColumn()/1000);

    $minB=0;$maxB=0;
    foreach($TIERS as $t){if($depoK>=$t[0]){$minB=$t[1];$maxB=$t[2];}}
    if($maxB<=0)err('Deposit belum mencukupi');

    // Random bonus between min and max
    $bonusK=$minB+(mt_rand()/mt_getrandmax())*($maxB-$minB);
    $bonusK=round($bonusK,2);
    $bonusRp=round($bonusK*1000);

    $db->beginTransaction();
    try{
        $db->prepare("INSERT INTO vip_claims(user_id,claim_type,period,vip_level,amount) VALUES(?,?,?,?,?)")->execute([$uid,'misteri',$cycleId,$day,$bonusRp]);
        $db->prepare("UPDATE users SET balance=balance+? WHERE id=?")->execute([$bonusRp,$uid]);
        $db->prepare("INSERT INTO transactions(user_id,type,amount,note,created_at) VALUES(?,?,?,?,NOW())")->execute([$uid,'bonus',$bonusRp,'Bonus Misteri Hari '.$day]);

        require_once __DIR__.'/../includes/bonus_to.php';
        bonusTO_track($db,$uid,'misteri',$bonusRp,'Misteri Hari '.$day,1);

        $db->commit();
        $bal=$db->prepare("SELECT balance FROM users WHERE id=?");$bal->execute([$uid]);
        ok(['amount'=>$bonusRp,'balance'=>$bal->fetchColumn()]);
    }catch(Exception $e){$db->rollBack();err('Gagal memproses');}
}

// ─── CLAIM BANTUAN MINGGUAN ───
if($action==='claim_bantuan'){
    $uid=auth();
    $weekId=date('Y-W');
    $weekStart=date('Y-m-d',strtotime('monday this week'));
    $weekEnd=date('Y-m-d',strtotime('sunday this week'));

    // Already claimed?
    $chk=$db->prepare("SELECT id FROM vip_claims WHERE user_id=? AND claim_type='bantuan' AND period=?");
    $chk->execute([$uid,$weekId]);
    if($chk->fetch())err('Sudah diklaim minggu ini');

    // Calculate net loss
    $q=$db->prepare("SELECT 
      COALESCE(SUM(CASE WHEN type='game_transfer' THEN ABS(amount) ELSE 0 END),0) as total_bet,
      COALESCE(SUM(CASE WHEN type='game_win' THEN amount ELSE 0 END),0) as total_win
      FROM transactions WHERE user_id=? AND type IN('game_transfer','game_win') AND DATE(created_at)>=? AND DATE(created_at)<=?");
    $q->execute([$uid,$weekStart,$weekEnd]);
    $r=$q->fetch();
    $lossK=max(0,($r['total_bet']-$r['total_win'])/1000);

    $TIERS=[[100,2],[15000,3],[150000,5],[1500000,10],[6000000,20],[9000000,30]];
    $pct=0;foreach($TIERS as $t){if($lossK>=$t[0])$pct=$t[1];}
    if($pct<=0)err('Tidak ada kerugian yang memenuhi syarat');

    $cashbackK=round($lossK*$pct/100,2);
    $cashbackRp=round($cashbackK*1000);

    $db->beginTransaction();
    try{
        $db->prepare("INSERT INTO vip_claims(user_id,claim_type,period,vip_level,amount) VALUES(?,?,?,?,?)")->execute([$uid,'bantuan',$weekId,0,$cashbackRp]);
        $db->prepare("UPDATE users SET balance=balance+? WHERE id=?")->execute([$cashbackRp,$uid]);
        $db->prepare("INSERT INTO transactions(user_id,type,amount,note,created_at) VALUES(?,?,?,?,NOW())")->execute([$uid,'bonus',$cashbackRp,'Dana Bantuan Mingguan W'.date('W')]);

        require_once __DIR__.'/../includes/bonus_to.php';
        bonusTO_track($db,$uid,'bantuan',$cashbackRp,'Dana Bantuan W'.date('W'),1);

        $db->commit();
        $bal=$db->prepare("SELECT balance FROM users WHERE id=?");$bal->execute([$uid]);
        ok(['amount'=>$cashbackRp,'balance'=>$bal->fetchColumn()]);
    }catch(Exception $e){$db->rollBack();err('Gagal memproses');}
}

// ─── CLAIM BONUS DEPOSIT HARIAN ───
if($action==='claim_bonus_depo'){
    $uid=auth();
    $period=date('Y-m-d'); // per hari

    // Auto-migrate schema (vip_claims kolom pendek kalo DB lama)
    try{$db->exec("ALTER TABLE vip_claims MODIFY claim_type VARCHAR(30)");}catch(Exception $e){}
    try{$db->exec("ALTER TABLE vip_claims MODIFY period VARCHAR(30)");}catch(Exception $e){}
    try{$db->exec("ALTER TABLE vip_claims DROP INDEX uq_claim");}catch(Exception $e){}
    try{$db->exec("ALTER TABLE vip_claims ADD UNIQUE KEY uq_claim2(user_id,claim_type,period,vip_level)");}catch(Exception $e){}

    // Tiers harus sama persis dgn yg di bonusdepo.php
    // [min deposit K, bonus K]
    $TIERS=[[50,5],[300,18],[500,38],[1000,68],[3000,188],[8000,388],[30000,1888],[50000,3888]];

    // Cek udah klaim hari ini?
    $chk=$db->prepare("SELECT id FROM vip_claims WHERE user_id=? AND claim_type='bonus_depo' AND period=?");
    $chk->execute([$uid,$period]);
    if($chk->fetch())err('Sudah klaim bonus deposit hari ini. Kembali besok!');

    // Hitung total deposit hari ini (status=paid)
    $td=$db->prepare("SELECT COALESCE(SUM(nominal),0) FROM deposits WHERE user_id=? AND status='paid' AND DATE(created_at)=CURDATE()");
    $td->execute([$uid]);
    $todayDepoRp=(int)$td->fetchColumn();
    $todayDepoK=floor($todayDepoRp/1000);

    // Tentukan tier reward tertinggi yg dicapai
    $reward=0;
    foreach($TIERS as $t){if($todayDepoK>=$t[0])$reward=$t[1];}
    if($reward<=0)err('Deposit hari ini belum mencukupi. Minimal Rp '.number_format($TIERS[0][0]*1000,0,',','.').'. Deposit kamu hari ini: Rp '.number_format($todayDepoRp,0,',','.'));

    $rewardRp=$reward*1000;

    $db->beginTransaction();
    try{
        // Record claim
        $db->prepare("INSERT INTO vip_claims(user_id,claim_type,period,vip_level,amount) VALUES(?,?,?,?,?)")
           ->execute([$uid,'bonus_depo',$period,0,$rewardRp]);

        // Kredit saldo
        $u=$db->prepare("SELECT balance FROM users WHERE id=? FOR UPDATE");$u->execute([$uid]);
        $bal=(int)$u->fetchColumn();$after=$bal+$rewardRp;
        $db->prepare("INSERT INTO transactions(user_id,type,amount,balance_before,balance_after,note) VALUES(?,?,?,?,?,?)")
           ->execute([$uid,'bonus',$rewardRp,$bal,$after,'Bonus Deposit Harian '.$period]);
        $db->prepare("UPDATE users SET balance=? WHERE id=?")->execute([$after,$uid]);

        // ═══ TRACK TURNOVER — bonus wajib 1x TO ═══
        require_once __DIR__.'/../includes/bonus_to.php';
        bonusTO_track($db,$uid,'bonus_depo',$rewardRp,'Bonus Deposit Harian '.$period,1);

        $db->commit();

        // Push notif
        try{
            require_once __DIR__.'/../includes/webpush.php';
            pushNotify($db,$uid,'🎁 Bonus Deposit Diklaim!','Bonus Rp '.number_format($rewardRp,0,',','.').' masuk. Wajib 1x TO.','/bonusdepo.php');
        }catch(Exception $e){}

        ok(['amount'=>$rewardRp,'balance'=>$after,'to_target'=>$rewardRp]);
    }catch(Exception $e){
        $db->rollBack();
        err('Gagal memproses klaim: '.$e->getMessage());
    }
}

// ─── DAILY CHECK-IN ───
if($action==='daily_checkin'){
    $uid=auth();

    // Pastiin table
    try{$db->exec("CREATE TABLE IF NOT EXISTS daily_checkin(
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        checkin_date DATE NOT NULL,
        streak_day INT NOT NULL,
        vip_level INT DEFAULT 0,
        reward INT DEFAULT 0,
        turnover_at_checkin BIGINT DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_date(user_id,checkin_date)
    ) ENGINE=InnoDB");}catch(Exception $e){}

    // Hitung VIP level dari total deposit
    $VIP_DEPO=[0,1500000,3000000,15000000,30000000,150000000];
    $tot=(int)$db->query("SELECT total_deposit FROM users WHERE id=$uid")->fetchColumn();
    $vipLv=0;for($i=count($VIP_DEPO)-1;$i>=0;$i--){if($tot>=$VIP_DEPO[$i]){$vipLv=$i;break;}}

    // Reward table — angka dalam satuan "K" / ribu
    // V0 sengaja kecil (user belum depo, anti-abuse)
    $REWARDS=[
        [1,3,5],        // V0 — belum depo
        [12,38,78],     // V1
        [18,58,118],    // V2
        [28,78,178],    // V3
        [38,118,258],   // V4
        [58,178,378],   // V5
    ];
    $rw=$REWARDS[$vipLv];
    // Day 1-5 = rw[0], Day 6 = rw[1], Day 7 = rw[2]
    $dayRewards=[$rw[0],$rw[0],$rw[0],$rw[0],$rw[0],$rw[1],$rw[2]];

    $today=date('Y-m-d');
    $yesterday=date('Y-m-d',strtotime('-1 day'));

    // Cek udah check-in hari ini?
    $exist=$db->prepare("SELECT * FROM daily_checkin WHERE user_id=? AND checkin_date=?");
    $exist->execute([$uid,$today]);
    $todayRow=$exist->fetch();

    // Ambil entry terakhir buat hitung streak
    $last=$db->prepare("SELECT checkin_date,streak_day FROM daily_checkin WHERE user_id=? ORDER BY checkin_date DESC LIMIT 1");
    $last->execute([$uid]);
    $lastRow=$last->fetch();

    // Syarat turnover: minimal 1 kali bet valid dalam 7 hari terakhir (mencegah abuse)
    $toReq=(int)$db->prepare("SELECT COALESCE(SUM(ABS(amount)),0) FROM transactions WHERE user_id=? AND type='game_transfer' AND created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)");
    $toReq->execute([$uid]);
    $turnover7d=(int)$toReq->fetchColumn();

    // Action = "status" (default): tampilin state
    // Action = "claim": klaim hadiah hari ini
    $doClaim=!empty($d['claim']);

    if($doClaim){
        if($todayRow)err('Sudah check-in hari ini. Balik lagi besok!');
        if($turnover7d<=0)err('Perlu minimal 1 kali bet valid dalam 7 hari terakhir.');

        // Tentukan streak_day
        $streakDay=1;
        if($lastRow&&$lastRow['checkin_date']===$yesterday){
            $streakDay=min(7,(int)$lastRow['streak_day']+1);
        }

        // Reward hari ini (konversi K → Rupiah = *1000)
        $rewardK=$dayRewards[$streakDay-1];
        $rewardRp=$rewardK*1000;

        $db->beginTransaction();
        try{
            // Insert checkin
            $ins=$db->prepare("INSERT INTO daily_checkin(user_id,checkin_date,streak_day,vip_level,reward,turnover_at_checkin) VALUES(?,?,?,?,?,?)");
            $ins->execute([$uid,$today,$streakDay,$vipLv,$rewardRp,$turnover7d]);

            // Kredit saldo
            $u=$db->prepare("SELECT balance FROM users WHERE id=? FOR UPDATE");$u->execute([$uid]);
            $bal=(int)$u->fetchColumn();$after=$bal+$rewardRp;
            $db->prepare("INSERT INTO transactions(user_id,type,amount,balance_before,balance_after,note) VALUES(?,?,?,?,?,?)")
               ->execute([$uid,'bonus',$rewardRp,$bal,$after,"Bonus check-in hari $streakDay"]);
            $db->prepare("UPDATE users SET balance=? WHERE id=?")->execute([$after,$uid]);

            // Track TO — bonus wajib 1x turnover
            require_once __DIR__.'/../includes/bonus_to.php';
            bonusTO_track($db,$uid,'checkin',$rewardRp,"Check-in hari $streakDay",1);

            $db->commit();

            // Push notif
            try{
                require_once __DIR__.'/../includes/webpush.php';
                pushNotify($db,$uid,'🎁 Check-in Berhasil!','Bonus Rp '.number_format($rewardRp,0,',','.').' hari ke-'.$streakDay.' masuk!','/checkin.php');
            }catch(Exception $e){}

            ok(['claimed'=>true,'streak_day'=>$streakDay,'reward'=>$rewardRp,'balance'=>$after]);
        }catch(Exception $e){$db->rollBack();err('Gagal klaim: '.$e->getMessage());}
    }

    // STATUS mode: return data streak untuk display
    // Kumpulin 7 hari terakhir yang udah claimed
    $hist=$db->prepare("SELECT checkin_date,streak_day,reward FROM daily_checkin WHERE user_id=? AND checkin_date>=DATE_SUB(CURDATE(),INTERVAL 7 DAY) ORDER BY checkin_date ASC");
    $hist->execute([$uid]);
    $historyRows=$hist->fetchAll();

    // Current streak_day yg BISA di-claim hari ini
    $canClaim=!$todayRow;
    $nextStreakDay=1;
    if($lastRow){
        if($lastRow['checkin_date']===$today){
            $nextStreakDay=(int)$lastRow['streak_day']; // udah claimed hari ini
        }elseif($lastRow['checkin_date']===$yesterday){
            $nextStreakDay=min(7,(int)$lastRow['streak_day']+1);
        }else{
            // Streak putus → reset ke 1
            $nextStreakDay=1;
        }
    }

    ok([
        'vip_level'=>$vipLv,
        'total_deposit'=>$tot,
        'turnover_7d'=>$turnover7d,
        'day_rewards'=>$dayRewards, // array 7 angka (satuan K)
        'next_streak_day'=>$nextStreakDay,
        'can_claim'=>$canClaim,
        'already_today'=>!!$todayRow,
        'today_reward'=>$todayRow?(int)$todayRow['reward']:0,
        'history'=>$historyRows,
    ]);
}

// ─── ROULETTE SPIN ───
if($action==='roulette_spin'){
    $uid=auth();

    // Auto-fix vip_claims columns (claim_type/period might be too short)
    try{$db->exec("ALTER TABLE vip_claims MODIFY claim_type VARCHAR(30)");}catch(Exception $e){}
    try{$db->exec("ALTER TABLE vip_claims MODIFY period VARCHAR(30)");}catch(Exception $e){}
    try{$db->exec("ALTER TABLE vip_claims DROP INDEX uq_claim");}catch(Exception $e){}
    try{$db->exec("ALTER TABLE vip_claims DROP INDEX uq_claim2");}catch(Exception $e){}

    // Check active session
    $ss=$db->prepare("SELECT * FROM vip_claims WHERE user_id=? AND claim_type='roulette_session' ORDER BY id DESC LIMIT 1");
    $ss->execute([$uid]);$session=$ss->fetch();
    if(!$session)err('Sesi tidak ditemukan');
    $cycleEnd=strtotime($session['period'].' +3 days');
    if($cycleEnd<time())err('Sesi kedaluwarsa. Refresh halaman.');

    // Check spins di session ini (bukan per hari) — 2 spin gratis HANYA di awal session
    $ts=$db->prepare("SELECT COUNT(*) FROM vip_claims WHERE user_id=? AND claim_type='roulette_spin' AND created_at>=?");
    $ts->execute([$uid,$session['period']]);
    $sessionSpins=(int)$ts->fetchColumn();

    // ═══ REF BONUS SPIN ═══
    // Aturan: setiap downline yg PERNAH DEPOSIT (status=paid) → owner dapat 1 spin tambahan
    // Spin invite PERSISTENT (ga reset), claimed per downline
    try{$db->exec("CREATE TABLE IF NOT EXISTS roulette_invite_claims (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, owner_id INT UNSIGNED NOT NULL, downline_id INT UNSIGNED NOT NULL, used_at DATETIME DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_inv (owner_id, downline_id)) ENGINE=InnoDB");}catch(Exception $e){}

    $refSpinsAvailable=0;
    try{
      $myRef=$db->prepare("SELECT ref_code FROM users WHERE id=?");$myRef->execute([$uid]);$myCode=$myRef->fetchColumn();
      if($myCode){
        $newDls=$db->prepare("SELECT u.id FROM users u WHERE u.referred_by=? AND EXISTS(SELECT 1 FROM deposits d WHERE d.user_id=u.id AND d.status='paid' LIMIT 1) AND u.id NOT IN (SELECT downline_id FROM roulette_invite_claims WHERE owner_id=?)");
        $newDls->execute([$myCode,$uid]);
        foreach($newDls->fetchAll() as $dl){
          try{
            $db->prepare("INSERT IGNORE INTO roulette_invite_claims (owner_id, downline_id) VALUES (?,?)")->execute([$uid,$dl['id']]);
          }catch(Exception $e){}
        }
        $rs=$db->prepare("SELECT COUNT(*) FROM roulette_invite_claims WHERE owner_id=? AND used_at IS NULL");
        $rs->execute([$uid]);$refSpinsAvailable=(int)$rs->fetchColumn();
      }
    }catch(Exception $e){}

    // 2 spin gratis CUMA DI AWAL session (ga reset harian)
    // Setelah 2 spin pertama habis, user HARUS undang teman buat dapat spin tambahan
    $maxSpins=2+$refSpinsAvailable;
    if($sessionSpins>=$maxSpins)err('Putaran habis. Undang teman & ajak deposit untuk dapat spin tambahan!');

    // ═══ PRIZE LOGIC ═══
    $accK=$session['amount']/1000;
    $totalSessionSpins=$sessionSpins; // alias biar ga ubah kode bawah

    $target=100;
    $remaining=max(0,$target-$accK);

    if($totalSessionSpins===0){
      // SPIN 1: big hook 90-99 (guaranteed)
      $winAmt=round(90+mt_rand(0,999)/100,2); // 90.00-99.99
      if($winAmt>99.99)$winAmt=99.99;
      $winIdx=mt_rand(0,7); // random segment (semua ???/emoji)
    }elseif($totalSessionSpins===1){
      // SPIN 2: kecil, 1-5
      $winAmt=round(mt_rand(100,500)/100,2); // 1.00-5.00
      if($winAmt>$remaining)$winAmt=max(0.01,$remaining-0.5); // jangan lewatin target
      $winIdx=mt_rand(0,7);
    }else{
      // SPIN 3+ (dari invite): grinding kecil banget
      if($remaining<=0.05){
        $winAmt=round($remaining*0.3,2);
        if($winAmt<0.01)$winAmt=0.01;
      }else{
        // Mean target ~0.12 per spin (75 spin × 0.12 = 9)
        $luck=mt_rand(1,1000);
        if($luck<=5){
          // 0.5% chance: 0.40-0.80 (jackpot mini)
          $winAmt=round(mt_rand(40,80)/100,2);
        }elseif($luck<=50){
          // 4.5% chance: 0.20-0.40
          $winAmt=round(mt_rand(20,40)/100,2);
        }elseif($luck<=300){
          // 25% chance: 0.08-0.20
          $winAmt=round(mt_rand(8,20)/100,2);
        }else{
          // 70% chance: 0.01-0.08
          $winAmt=round(mt_rand(1,8)/100,2);
        }
        // Never overshoot target
        if($winAmt>$remaining)$winAmt=max(0.01,$remaining);
      }
      $winIdx=mt_rand(0,7);
    }

    $winRp=round($winAmt*1000);

    $db->beginTransaction();
    try{
        // Record spin
        $db->prepare("INSERT INTO vip_claims(user_id,claim_type,period,vip_level,amount) VALUES(?,?,?,?,?)")->execute([$uid,'roulette_spin',date('Y-m-d H:i:s'),$winIdx,$winRp]);

        // Update session accumulated
        $db->prepare("UPDATE vip_claims SET amount=amount+? WHERE id=?")->execute([$winRp,$session['id']]);

        // Kalau spin yg ini pakai jatah invite (sessionSpins >= 2), tandai 1 claim sebagai used
        if($sessionSpins>=2 && $refSpinsAvailable>0){
          try{$db->exec("UPDATE roulette_invite_claims SET used_at=NOW() WHERE owner_id=$uid AND used_at IS NULL ORDER BY id ASC LIMIT 1");}catch(Exception $e){}
        }

        // Get updated accumulated
        $ua=$db->prepare("SELECT amount FROM vip_claims WHERE id=?");$ua->execute([$session['id']]);
        $newAcc=(int)$ua->fetchColumn();

        $db->commit();
        ok(['segment'=>$winIdx,'amount'=>$winRp,'accumulated'=>$newAcc,'spins_left'=>max(0,$maxSpins-$sessionSpins-1)]);
    }catch(Exception $e){$db->rollBack();err('Gagal memproses');}
}

// ─── BANNERS ───
if($action==='banners'){
    $s=$db->query("SELECT * FROM banners WHERE status='active' ORDER BY sort_order");
    ok(['banners'=>$s->fetchAll()]);
}

// ─── PROMOS ───
if($action==='promos'){
    $s=$db->query("SELECT * FROM promos WHERE status='active' ORDER BY created_at DESC");
    ok(['promos'=>$s->fetchAll()]);
}

// ─── SETTINGS (public) ───
if($action==='settings'){
    $s=$db->query("SELECT `key`,`value` FROM settings");$out=[];
    foreach($s->fetchAll() as $r)$out[$r['key']]=$r['value'];
    ok(['settings'=>$out]);
}

// ─── MEMO INBOX ───


// ─── MEMO MARK READ ───


// ─── MEMO MARK ALL READ ───


// ─── MEMO DELETE ───


// ─── REBATE HISTORY ───
if($action==='my_rebate'){
    $uid=auth();
    try{
        $s=$db->prepare("SELECT week_start,week_end,total_bet,rebate_pct,rebate_amount,status,paid_at FROM rebates WHERE user_id=? ORDER BY week_start DESC LIMIT 12");
        $s->execute([$uid]);
        ok(['rebates'=>$s->fetchAll()]);
    }catch(Exception $e){
        ok(['rebates'=>[]]);
    }
}

// ═══ NOTIFICATION: cek transaksi baru yg belum di-notify user ═══
// Dipanggil global oleh JS polling tiap 5 detik — return list credit baru
if($action==='check_new_credits'){
    $uid=auth();
    // Auto-add kolom notified kalau belum ada
    try{$db->exec("ALTER TABLE transactions ADD COLUMN notified TINYINT(1) DEFAULT 0");}catch(Exception $e){}
    try{$db->exec("CREATE INDEX idx_notified ON transactions(user_id,notified,type)");}catch(Exception $e){}

    // Cari credit baru (positive amount, belum notify) — max 10 sekali panggil
    $s=$db->prepare("SELECT id,type,amount,note,created_at FROM transactions
                     WHERE user_id=? AND notified=0 AND amount>0
                       AND type IN('deposit','bonus','referral','rebate','cashback','commission')
                     ORDER BY id ASC LIMIT 10");
    $s->execute([$uid]);
    $rows=$s->fetchAll();

    // Mark as notified biar ga dikirim lagi
    if(!empty($rows)){
        $ids=array_column($rows,'id');
        $placeholders=implode(',',array_fill(0,count($ids),'?'));
        $db->prepare("UPDATE transactions SET notified=1 WHERE id IN($placeholders)")->execute($ids);
    }

    // Map type → label Indonesian
    $labelMap=[
        'deposit'=>'Deposit',
        'bonus'=>'Bonus',
        'referral'=>'Bonus Referral',
        'rebate'=>'Rebate Mingguan',
        'cashback'=>'Cashback',
        'commission'=>'Komisi Referral',
    ];
    $out=[];
    foreach($rows as $r){
        $out[]=[
            'id'=>$r['id'],
            'type'=>$r['type'],
            'amount'=>intval($r['amount']),
            'label'=>$labelMap[$r['type']]??'Saldo',
            'note'=>$r['note']??'',
            'created_at'=>$r['created_at'],
        ];
    }
    ok(['credits'=>$out]);
}

// ─── TRANSACTION HISTORY ───
if($action==='history'){
    $uid=auth();
    $days=intval($d['days']??0);
    $type=$d['type']??'all';

    // ═══ AUTO-SWEEP: cek pending depo user ke SQX (max 3 deposit, async-style) ═══
    // Kalau callback gagal/lambat, polling history ini akan auto-credit deposit yg ternyata sudah paid di SQX
    try{
        require_once __DIR__.'/../includes/deposit_lib.php';
        $pendQ=$db->prepare("SELECT * FROM deposits WHERE user_id=? AND status='pending' AND created_at > NOW() - INTERVAL 24 HOUR ORDER BY created_at DESC LIMIT 3");
        $pendQ->execute([$uid]);
        foreach($pendQ->fetchAll() as $pendDep){
            @checkSqxAndCredit($db,$pendDep,'history_autosweep');
        }
    }catch(Exception $e){}

    // Auto-ensure schema biar query ga fail kalo kolom belum ada
    try{$db->exec("ALTER TABLE deposits ADD COLUMN paid_at DATETIME DEFAULT NULL");}catch(Exception $e){}
    try{$db->exec("ALTER TABLE deposits ADD COLUMN expires_at DATETIME DEFAULT NULL");}catch(Exception $e){}
    try{$db->exec("ALTER TABLE deposits ADD COLUMN note TEXT DEFAULT NULL");}catch(Exception $e){}
    try{$db->exec("ALTER TABLE withdrawals ADD COLUMN note TEXT DEFAULT NULL");}catch(Exception $e){}
    try{$db->exec("ALTER TABLE withdrawals ADD COLUMN processed_at DATETIME DEFAULT NULL");}catch(Exception $e){}
    try{$db->exec("ALTER TABLE transactions ADD COLUMN ref_id VARCHAR(100) DEFAULT NULL");}catch(Exception $e){}
    try{$db->exec("ALTER TABLE transactions ADD COLUMN note TEXT DEFAULT NULL");}catch(Exception $e){}

    $dateFilter='';$params=[$uid];
    if($days>0){$dateFilter=' AND t.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)';$params[]=$days;}

    // ═══ Gabung data dari 3 sumber, SEMUA filter user_id=$uid ═══
    $items=[];

    // 1. Transactions — saldo masuk/keluar (semua type kecuali noisy game_transfer)
    $typeFilter='';
    if($type==='deposit')$typeFilter=" AND t.type='deposit'";
    elseif($type==='withdraw')$typeFilter=" AND t.type='withdraw'";
    elseif($type==='bonus')$typeFilter=" AND t.type IN('bonus','rebate','spin_win','inject')";
    elseif($type==='referral')$typeFilter=" AND t.type='referral'";
    elseif($type==='game')$typeFilter=" AND t.type IN('game_transfer','game_win','game_refund')";
    elseif($type==='undangan')$typeFilter=" AND t.type='bonus' AND t.note LIKE '%Hadiah Undangan%'";
    elseif($type==='all')$typeFilter=" AND t.type NOT IN('game_transfer','game_win','game_refund')";

    $sql="SELECT t.id, t.type, t.amount, t.balance_after, t.note, t.created_at, t.ref_id, g.game_name
        FROM transactions t
        LEFT JOIN games g ON (
            t.type IN('game_transfer','game_win','game_refund') AND
            g.game_code = SUBSTRING_INDEX(SUBSTRING_INDEX(t.note,'/',-1),' ',1)
        )
        WHERE t.user_id=?".$dateFilter.$typeFilter."
        ORDER BY t.created_at DESC LIMIT 200";
    try{
        $s=$db->prepare($sql);$s->execute($params);
        $rows=$s->fetchAll();
    }catch(Exception $e){
        // Fallback: kalau table games ga ada / kolom game_code missing → query tanpa join
        $sql2="SELECT id,type,amount,balance_after,note,created_at,ref_id,NULL as game_name
               FROM transactions WHERE user_id=?".$dateFilter.str_replace('t.','',$typeFilter)."
               ORDER BY created_at DESC LIMIT 200";
        try{$s=$db->prepare($sql2);$s->execute($params);$rows=$s->fetchAll();}catch(Exception $e2){$rows=[];}
    }
    foreach($rows as $tx){
        if(!empty($tx['game_name'])&&preg_match('/^(Transfer ke game|Refund).*\/(\S+)/',$tx['note'],$m)){
            $tx['note']=str_replace('/'.$m[2],'/'.$tx['game_name'],$tx['note']);
        }
        // Kategori untuk filter tampilan
        $cat='transaction';
        if(strpos($tx['note']??'','Hadiah Undangan')!==false)$cat='undangan';
        elseif($tx['type']==='referral')$cat='referral';
        elseif(in_array($tx['type'],['bonus','rebate','spin_win','inject']))$cat='bonus';
        elseif($tx['type']==='deposit')$cat='deposit';
        elseif($tx['type']==='withdraw')$cat='withdraw';

        $items[]=[
            'id'=>'tx_'.$tx['id'],
            'type'=>$tx['type'],
            'cat'=>$cat,
            'amount'=>intval($tx['amount']),
            'balance_after'=>intval($tx['balance_after']),
            'note'=>$tx['note'],
            'ref_id'=>$tx['ref_id']??null,
            'status'=>'ok',
            'created_at'=>$tx['created_at']
        ];
    }

    // 2. Deposits — SEMUA status (pending, paid, expired, failed, cancelled) biar user liat yg ditolak/expired juga
    if($type==='all' || $type==='deposit'){
        try{
            $q=$db->prepare("SELECT id,tx_id,nominal,bonus_amount,method,status,note,created_at,paid_at,expires_at
                FROM deposits WHERE user_id=? ORDER BY created_at DESC LIMIT 100");
            $q->execute([$uid]);
            $depRows=$q->fetchAll();
        }catch(Exception $e){
            // Fallback: kolom expires_at/paid_at/note belum ada
            try{
                $q=$db->prepare("SELECT id,tx_id,nominal,bonus_amount,method,status,created_at FROM deposits WHERE user_id=? ORDER BY created_at DESC LIMIT 100");
                $q->execute([$uid]);$depRows=$q->fetchAll();
            }catch(Exception $e2){$depRows=[];}
        }
        foreach($depRows as $dep){
            // Skip deposit 'paid' — sudah ada di transactions (hindari dobel)
            if($dep['status']==='paid')continue;
            $statusLbl=[
                'pending'=>'Menunggu pembayaran',
                'expired'=>'Kedaluwarsa',
                'failed'=>'Ditolak / Gagal',
                'cancelled'=>'Dibatalkan',
            ][$dep['status']]??$dep['status'];
            $note='Deposit '.strtoupper($dep['method']??'qris').' • '.$statusLbl;
            if(!empty($dep['note']))$note.=' • '.substr($dep['note'],0,40);
            $items[]=[
                'id'=>'dep_'.$dep['id'],
                'type'=>'deposit',
                'cat'=>'deposit',
                'amount'=>intval($dep['nominal']),
                'balance_after'=>null,
                'note'=>$note,
                'method'=>$dep['method']??'qris',
                'ref_id'=>$dep['tx_id']??null,
                'status'=>$dep['status'],
                'created_at'=>$dep['created_at']
            ];
        }
    }

    // 3. Withdrawals — SEMUA status (pending, approved, rejected)
    if($type==='all' || $type==='withdraw'){
        try{
            $q=$db->prepare("SELECT id,amount,status,note,created_at,processed_at
                FROM withdrawals WHERE user_id=? ORDER BY created_at DESC LIMIT 100");
            $q->execute([$uid]);
            $wdRows=$q->fetchAll();
        }catch(Exception $e){
            try{
                $q=$db->prepare("SELECT id,amount,status,created_at FROM withdrawals WHERE user_id=? ORDER BY created_at DESC LIMIT 100");
                $q->execute([$uid]);$wdRows=$q->fetchAll();
            }catch(Exception $e2){$wdRows=[];}
        }
        foreach($wdRows as $wd){
            // Skip approved — ada di transactions type='withdraw' + refund rejected juga ada sbg transaction
            if($wd['status']==='approved')continue;
            $statusLbl=[
                'pending'=>'Menunggu diproses',
                'rejected'=>'Ditolak (saldo dikembalikan)',
            ][$wd['status']]??$wd['status'];
            $note='Penarikan • '.$statusLbl;
            if(!empty($wd['note'])&&$wd['status']==='rejected')$note.=' • '.substr($wd['note'],0,40);
            $items[]=[
                'id'=>'wd_'.$wd['id'],
                'type'=>'withdraw',
                'cat'=>'withdraw',
                'amount'=>intval($wd['amount']),
                'balance_after'=>null,
                'note'=>$note,
                'status'=>$wd['status'],
                'created_at'=>$wd['created_at']
            ];
        }
    }

    // Sort gabungan by created_at DESC
    usort($items,function($a,$b){return strcmp($b['created_at'],$a['created_at']);});
    // Limit final
    $items=array_slice($items,0,200);

    // Summaries (tetap dari transactions, buat total uang beneran masuk/keluar)
    $s2=$db->prepare("SELECT COALESCE(SUM(CASE WHEN type='deposit' THEN amount ELSE 0 END),0) as total_dep, COALESCE(SUM(CASE WHEN type='withdraw' THEN ABS(amount) ELSE 0 END),0) as total_wd, COALESCE(SUM(CASE WHEN type IN('bonus','referral','inject','rebate','spin_win') THEN amount ELSE 0 END),0) as total_bonus FROM transactions WHERE user_id=?");
    $s2->execute([$uid]);$sum=$s2->fetch();

    // Game stats
    $s3=$db->prepare("SELECT COUNT(*) as bet_count, COALESCE(SUM(CASE WHEN type='game_bet' THEN ABS(amount) ELSE 0 END),0) as total_bet, COALESCE(SUM(CASE WHEN type='game_win' THEN amount ELSE 0 END),0) as total_win FROM transactions WHERE user_id=? AND type IN('game_bet','game_win')");
    $s3->execute([$uid]);$game=$s3->fetch();

    // User info buat header riwayat (biar jelas ini riwayat SIAPA)
    $uq=$db->prepare("SELECT id,username,display_id,balance,vip_level,created_at FROM users WHERE id=?");
    $uq->execute([$uid]);$uinfo=$uq->fetch();

    ok([
        'transactions'=>$items,
        'user'=>[
            'id'=>intval($uinfo['id']??0),
            'username'=>$uinfo['username']??'-',
            'display_id'=>$uinfo['display_id']??null,
            'balance'=>intval($uinfo['balance']??0),
            'vip_level'=>intval($uinfo['vip_level']??0),
            'created_at'=>$uinfo['created_at']??null,
        ],
        'summary'=>[
            'total_deposit'=>intval($sum['total_dep']),
            'total_withdraw'=>intval($sum['total_wd']),
            'total_bonus'=>intval($sum['total_bonus']),
            'bet_count'=>intval($game['bet_count']),
            'total_bet'=>intval($game['total_bet']),
            'total_win'=>intval($game['total_win']),
            'net_win'=>intval($game['total_win'])-intval($game['total_bet'])
        ]
    ]);
}

// ─── MY PROMO (active bonus with TO progress) ───
if($action==='my_promo'){
    $uid=auth();
    // Find last deposit with bonus that hasn't met TO
    $s=$db->prepare("SELECT d.id,d.nominal,d.bonus_amount,d.bonus_id,d.turnover_at_deposit,d.turnover_met,d.created_at as dep_date,b.name,b.percentage,b.turnover_x 
        FROM deposits d 
        LEFT JOIN bonuses b ON b.id=d.bonus_id 
        WHERE d.user_id=? AND d.status='paid' AND d.bonus_id IS NOT NULL AND b.turnover_x>0 AND d.turnover_met=0
        ORDER BY d.created_at DESC LIMIT 1");
    $s->execute([$uid]);
    $dep=$s->fetch();
    
    if(!$dep){
        ok(['promo'=>null]);
    }
    
    // Hitung TO sejak tanggal deposit — mulai dari 0 saat bonus diterima
    $uname2=$db->prepare("SELECT username FROM users WHERE id=?");$uname2->execute([$uid]);$un2=$uname2->fetchColumn();
    $depDate=date('Y-m-d',strtotime($dep['dep_date']));
    $turnoverSinceDep=getBetsSince($un2,$depDate);
    $targetTO=intval(($dep['nominal']+$dep['bonus_amount'])*$dep['turnover_x']);
    $completed=$turnoverSinceDep>=$targetTO;
    
    if($completed){
        $db->prepare("UPDATE deposits SET turnover_met=1 WHERE id=?")->execute([$dep['id']]);
    }
    
    ok(['promo'=>[
        'bonus_name'=>$dep['name']??'Bonus',
        'percentage'=>floatval($dep['percentage']),
        'deposit'=>intval($dep['nominal']),
        'bonus_amount'=>intval($dep['bonus_amount']),
        'turnover_x'=>intval($dep['turnover_x']),
        'target_to'=>intval($targetTO),
        'current_to'=>max(0,$turnoverSinceDep),
        'completed'=>$completed
    ]]);
}

// ─── DASHBOARD GAMES (from DB) ───
if($action==='dashboard_games'){
    try{
        $providers=$db->query("SELECT code,name,logo,game_count FROM providers WHERE status=1 AND game_count>0 ORDER BY sort_order ASC, game_count DESC")->fetchAll();
    }catch(Exception $e){
        $providers=[];
    }
    $result=[];
    foreach($providers as $p){
        $games=$db->prepare("SELECT g.game_code,g.game_name,g.banner,g.featured,g.sort_order,COALESCE(c.click_count,0) as clicks
                             FROM games g LEFT JOIN game_clicks c ON c.provider_code=g.provider_code AND c.game_code=g.game_code
                             WHERE g.provider_code=? AND g.status=1
                             ORDER BY g.featured DESC, clicks DESC, g.sort_order ASC, g.game_name ASC LIMIT 12");
        $games->execute([$p['code']]);
        $p['games']=$games->fetchAll();
        $result[]=$p;
    }
    ok(['sections'=>$result]);
}

if($action==='bonuses'){
    // Auto-seed default "Cashback 4%" kalau belum ada bonus aktif sama sekali
    try{
        $cnt=(int)$db->query("SELECT COUNT(*) FROM bonuses WHERE status='active'")->fetchColumn();
        if($cnt===0){
            $db->prepare("INSERT INTO bonuses(name,percentage,max_amount,turnover_x,min_deposit,status) VALUES(?,?,?,?,?,?)")
               ->execute(['Cashback 4%',4,0,5,0,'active']);
        }
    }catch(Exception $e){}
    $s=$db->query("SELECT id,name,percentage,max_amount,turnover_x FROM bonuses WHERE status='active' ORDER BY id");
    ok(['bonuses'=>$s->fetchAll()]);
}

if($action==='redeem_code'){
    $uid=auth();
    $code=strtoupper(trim($d['code']??''));
    if(!$code||strlen($code)<3)err('Masukkan kode yang valid');
    // Check code exists
    $s=$db->prepare("SELECT * FROM redeem_codes WHERE code=? AND status='active' AND (max_uses=0 OR used_count<max_uses) AND (expires_at IS NULL OR expires_at>NOW())");
    $s->execute([$code]);$rc=$s->fetch();
    if(!$rc)err('Kode tidak valid atau sudah kadaluarsa');
    // Check if user already used
    $s2=$db->prepare("SELECT id FROM redeem_usage WHERE code_id=? AND user_id=?");$s2->execute([$rc['id'],$uid]);
    if($s2->fetch())err('Anda sudah menggunakan kode ini');
    // Apply bonus
    $amount=intval($rc['amount']);
    $after=logTx($db,$uid,'bonus',$amount,'Kode Redeem: '.$code,$code);
    $db->prepare("INSERT INTO redeem_usage(code_id,user_id) VALUES(?,?)")->execute([$rc['id'],$uid]);
    $db->prepare("UPDATE redeem_codes SET used_count=used_count+1 WHERE id=?")->execute([$rc['id']]);
    autoMemo($db,$uid,'Kode Redeem Berhasil','Kode '.$code.' berhasil ditukar! Bonus Rp '.number_format($amount,0,',','.').' telah ditambahkan.');
    ok(['amount'=>$amount,'balance'=>$after]);
}

// ═══ REBATE ═══
if($action==='rebate_info'){
    $uid=auth();
    try{$db->exec("ALTER TABLE users ADD COLUMN rebate_claimed_to BIGINT UNSIGNED DEFAULT 0 AFTER total_turnover");}catch(Exception $e){}
    $u=$db->prepare("SELECT username,total_turnover,rebate_claimed_to,created_at FROM users WHERE id=?");$u->execute([$uid]);$user=$u->fetch();
    $dbTurnover=intval($user['total_turnover']??0);
    // Sync semua bet dari tanggal daftar sampai sekarang
    $regDate=date('Y-m-d',strtotime($user['created_at']??date('Y-m-d')));
    $totalBet=getBetsSince($user['username'],$regDate);
    if($totalBet>0){
        $newTO=max($dbTurnover,$totalBet);
        $db->prepare("UPDATE users SET total_turnover=? WHERE id=?")->execute([$newTO,$uid]);
        $to=$newTO;
    }else{
        $to=$dbTurnover;
    }
    ok(['total_turnover'=>$to,'rebate_claimed_to'=>intval($user['rebate_claimed_to']??0),'synced'=>$totalBet>0]);
}

if($action==='claim_rebate'){
    $uid=auth();
    try{$db->exec("ALTER TABLE users ADD COLUMN rebate_claimed_to BIGINT UNSIGNED DEFAULT 0 AFTER total_turnover");}catch(Exception $e){}
    // Sync turnover dulu dari NexusGGR sebelum klaim
    $uInfo=$db->prepare("SELECT username,total_turnover,rebate_claimed_to,balance,created_at FROM users WHERE id=?");$uInfo->execute([$uid]);$user=$uInfo->fetch();
    if(NEXUS_URL){
        $totalBet=0;$syncOk=false;
        $start=date('Y-m-d',strtotime('-90 days'));
        foreach(['slot','casino'] as $gt){
            $page=0;$count=0;
            do{
                $body=json_encode(['method'=>'get_game_log','agent_code'=>NEXUS_AGENT,'agent_token'=>NEXUS_TOKEN,
                    'user_code'=>$user['username'],'game_type'=>$gt,
                    'start'=>$start.' 00:00:00','end'=>date('Y-m-d 23:59:59'),'page'=>$page,'perPage'=>100]);
                $ch=curl_init(NEXUS_URL);
                curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,
                    CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
                    CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10]);
                $raw=curl_exec($ch);curl_close($ch);
                $r=json_decode($raw,true);
                if(($r['status']??0)!=1)break;
                $syncOk=true;
                $logs=$r[$gt]??[];
                foreach($logs as $l){$txType=$l['txn_type']??'debit_credit';if($txType!=='credit')$totalBet+=abs(floatval($l['bet_money']??$l['bet']??0));}
                $count+=count($logs);$page++;
            }while($count<($r['total_count']??0)&&$page<20);
        }
        if($syncOk&&$totalBet>0)$db->prepare("UPDATE users SET total_turnover=? WHERE id=?")->execute([intval($totalBet),$uid]);
    }
    // Re-read updated value
    $u=$db->prepare("SELECT total_turnover,rebate_claimed_to,balance FROM users WHERE id=?");$u->execute([$uid]);$user=$u->fetch();
    $to=intval($user['total_turnover']);
    $claimed=intval($user['rebate_claimed_to']??0);
    $unclaimed=$to-$claimed;
    if($unclaimed<=0)err('Tidak ada rebate yang bisa diklaim');
    // Calculate rebate level — sistem K (1K = Rp 1.000)
    $levels=[[1,1000,0.3],[2,10000000,0.5],[3,50000000,0.6],[4,100000000,0.8],[5,500000000,1],[6,1000000000,2],[7,10000000000,3]];
    $pct=0;
    for($i=count($levels)-1;$i>=0;$i--){if($to>=$levels[$i][1]){$pct=$levels[$i][2];break;}}
    if($pct<=0)err('Level rebate belum tercapai');
    $rebateAmt=intval(floor($unclaimed*$pct/100));
    if($rebateAmt<=0)err('Jumlah rebate terlalu kecil');
    // Credit to balance
    $after=logTx($db,$uid,'bonus',$rebateAmt,'Rebate Turnover '.$pct.'% dari Rp '.number_format($unclaimed,0,',','.'));
    $db->prepare("UPDATE users SET rebate_claimed_to=? WHERE id=?")->execute([$to,$uid]);
    autoMemo($db,$uid,'<svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16" style="display:inline-block;vertical-align:middle"><circle cx="12" cy="12" r="10" fill="#38bdf8"/><text x="12" y="16" text-anchor="middle" fill="#1a1200" font-size="12" font-weight="900">$</text></svg> Rebate Diklaim!','Rebate turnover '.$pct.'% sebesar Rp '.number_format($rebateAmt,0,',','.').' telah masuk ke saldo Anda.');
    ok(['amount'=>$rebateAmt,'balance'=>$after]);
}

// ═══ MEMOS / NOTIFICATIONS ═══
if($action==='get_memos'){
    $uid=getUid();
    if($uid){
        $st=$db->prepare("SELECT id,title,body,is_read,created_at FROM memos WHERE type IN('all','notif') OR (type='target' AND to_user_id=?) ORDER BY created_at DESC LIMIT 50");
        $st->execute([$uid]);
    }else{
        $st=$db->query("SELECT id,title,body,0 as is_read,created_at FROM memos WHERE type IN('all','notif') ORDER BY created_at DESC LIMIT 50");
    }
    ok(['memos'=>$st->fetchAll()]);
}

if($action==='mark_all_read'){
    $uid=getUid();
    if($uid){
        $db->prepare("UPDATE memos SET is_read=1 WHERE (type='all' OR (type='target' AND to_user_id=?)) AND is_read=0")->execute([$uid]);
    }
    ok([]);
}

if($action==='get_blogs'){
    $st=$db->query("SELECT id,title,body,created_at FROM memos WHERE type='blog' ORDER BY created_at DESC LIMIT 50");
    ok(['blogs'=>$st->fetchAll()]);
}


// ─── GET BALANCE (fast, no sync) ───
if($action==='get_balance'){
    $uid=auth();
    $u=$db->prepare("SELECT balance,vip_level FROM users WHERE id=?");$u->execute([$uid]);$r=$u->fetch();
    ok(['balance'=>intval($r['balance']??0),'vip_level'=>intval($r['vip_level']??0)]);
}

// ─── WITHDRAW HISTORY ───
if($action==='withdraw_history'){
    $uid=auth();
    try{
        $s=$db->prepare("SELECT id,amount,bank_name,acc_name,acc_number,status,admin_note,created_at,processed_at FROM withdrawals WHERE user_id=? ORDER BY created_at DESC LIMIT 30");
        $s->execute([$uid]);
        ok(['withdrawals'=>$s->fetchAll()]);
    }catch(Exception $e){
        ok(['withdrawals'=>[]]);
    }
}

// ─── LIST BANK ACCOUNTS ───
if($action==='get_banks'){
    $uid=auth();
    try{
        $s=$db->prepare("SELECT id,bank_name,acc_name,acc_number,created_at FROM user_banks WHERE user_id=? ORDER BY id DESC");
        $s->execute([$uid]);
        ok(['banks'=>$s->fetchAll()]);
    }catch(Exception $e){
        ok(['banks'=>[]]);
    }
}

// ─── DELETE BANK ACCOUNT ───
if($action==='delete_bank'){
    $uid=auth();
    $id=intval($d['id']??0);
    if(!$id)err('ID wajib');
    $db->prepare("DELETE FROM user_banks WHERE id=? AND user_id=?")->execute([$id,$uid]);
    ok();
}

// ref_claims table auto-create
try{$db->exec("CREATE TABLE IF NOT EXISTS ref_claims (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,tier INT NOT NULL,amount BIGINT UNSIGNED NOT NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_ref_claim (user_id, tier)) ENGINE=InnoDB");}catch(Exception $e){}

if($action==='ref_reward_status'){
    $uid=auth();
    // Auto-create ref_claims table
    try{$db->exec("CREATE TABLE IF NOT EXISTS ref_claims(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,tier INT NOT NULL,amount BIGINT UNSIGNED DEFAULT 0,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_ref_claim(user_id,tier)) ENGINE=InnoDB");}catch(Exception $e){}
    // Kolom last_turnover_sync untuk rate-limit NexusGGR call
    try{$db->exec("ALTER TABLE users ADD COLUMN last_turnover_sync DATETIME DEFAULT NULL AFTER total_turnover");}catch(Exception $e){}

    // ═══ Ambil syarat tier dari settings (admin-configurable) ═══
    $st_t1_dep=intval(getSetting($db,'ref_tier1_min_deposit',100000));
    $st_t1_to =intval(getSetting($db,'ref_tier1_min_turnover',0));
    $st_t2_dep=intval(getSetting($db,'ref_tier2_min_deposit',50000));
    $st_t2_to =intval(getSetting($db,'ref_tier2_min_turnover',1000000));

    $u=$db->prepare("SELECT ref_code FROM users WHERE id=?");$u->execute([$uid]);$refCode=$u->fetchColumn();
    if(!$refCode)ok(['valid_count'=>0,'total_count'=>0,'claimed'=>[],'synced'=>0,'tier1_count'=>0]);

    // Sync downline yang sudah memenuhi depo tapi TO belum cukup.
    // Rate-limit 5 menit per downline. Max 5 downline per request.
    if($st_t2_to>0){
        $needSync=$db->prepare("SELECT id,username FROM users WHERE referred_by=? AND total_deposit>=? AND total_turnover<? AND (last_turnover_sync IS NULL OR last_turnover_sync<DATE_SUB(NOW(),INTERVAL 5 MINUTE)) ORDER BY last_turnover_sync ASC LIMIT 5");
        $needSync->execute([$refCode,$st_t2_dep,$st_t2_to]);
    }else{
        // Kalau ga ada syarat TO, ga perlu sync
        $needSync=$db->prepare("SELECT id,username FROM users WHERE 1=0");
        $needSync->execute();
    }
    $syncedCount=0;
    if(defined('NEXUS_URL')&&NEXUS_URL){
        foreach($needSync->fetchAll() as $dl){
            syncTurnoverFast($db,$dl['id'],$dl['username']);
            $db->prepare("UPDATE users SET last_turnover_sync=NOW() WHERE id=?")->execute([$dl['id']]);
            $syncedCount++;
        }
    }

    // Claimed tiers
    $cl=$db->prepare("SELECT tier FROM ref_claims WHERE user_id=?");$cl->execute([$uid]);
    $claimed=[];foreach($cl->fetchAll() as $r)$claimed[]=$r['tier'];
    $tier1Claimed=in_array(1,$claimed);

    // Hitung downline valid versi tier 2+ (syarat normal dari admin)
    $sql2="SELECT COUNT(*) FROM users WHERE referred_by=? AND total_deposit>=?";
    $params2=[$refCode,$st_t2_dep];
    if($st_t2_to>0){$sql2.=" AND total_turnover>=?";$params2[]=$st_t2_to;}
    $vc=$db->prepare($sql2);$vc->execute($params2);$validCount=intval($vc->fetchColumn());

    // Hitung downline valid versi tier 1 (syarat relaxed dari admin)
    $sql1="SELECT COUNT(*) FROM users WHERE referred_by=? AND total_deposit>=?";
    $params1=[$refCode,$st_t1_dep];
    if($st_t1_to>0){$sql1.=" AND total_turnover>=?";$params1[]=$st_t1_to;}
    $vt1=$db->prepare($sql1);$vt1->execute($params1);$tier1Count=intval($vt1->fetchColumn());

    $tc=$db->prepare("SELECT COUNT(*) FROM users WHERE referred_by=?");
    $tc->execute([$refCode]);$totalCount=intval($tc->fetchColumn());

    // Count downline yang hampir valid tier 2 (udah depo, TO belum cukup)
    if($st_t2_to>0){
        $nt=$db->prepare("SELECT COUNT(*) FROM users WHERE referred_by=? AND total_deposit>=? AND total_turnover<?");
        $nt->execute([$refCode,$st_t2_dep,$st_t2_to]);$needTurnover=intval($nt->fetchColumn());
    }else{$needTurnover=0;}

    ok([
        'valid_count'=>$validCount,
        'tier1_count'=>$tier1Count,
        'tier1_claimed'=>$tier1Claimed,
        'total_count'=>$totalCount,
        'need_turnover'=>$needTurnover,
        'claimed'=>$claimed,
        'synced'=>$syncedCount
    ]);
}

if($action==='claim_ref_reward'){
    $uid=auth();
    $tier=intval($d['tier']??0);
    $rewards=[[1,50],[2,50],[3,50],[4,50],[5,50],[6,52],[7,54],[8,56],[9,58],[10,60],[20,500],[30,500],[40,500],[50,500],[60,500],[70,500],[80,500],[90,500],[100,500],[110,580],[120,580],[130,580],[140,580],[150,580],[160,580],[170,580],[180,580],[200,580],[300,5000],[400,5000],[500,8000],[600,8000],[700,8000],[800,13800],[900,13800],[1000,13800],[5000,29000],[10000,58000],[20000,138000]];
    $found=null;foreach($rewards as $r){if($r[0]===$tier){$found=$r;break;}}
    if(!$found)err('Tier tidak valid');
    $amount=$found[1]*1000;

    // Ambil syarat tier dari settings
    $st_t1_dep=intval(getSetting($db,'ref_tier1_min_deposit',100000));
    $st_t1_to =intval(getSetting($db,'ref_tier1_min_turnover',0));
    $st_t2_dep=intval(getSetting($db,'ref_tier2_min_deposit',50000));
    $st_t2_to =intval(getSetting($db,'ref_tier2_min_turnover',1000000));

    $u=$db->prepare("SELECT ref_code FROM users WHERE id=?");$u->execute([$uid]);$refCode=$u->fetchColumn();
    try{$db->exec("ALTER TABLE users ADD COLUMN last_turnover_sync DATETIME DEFAULT NULL AFTER total_turnover");}catch(Exception $e){}

    // Sync downlines (tier 2+ butuh TO)
    if($st_t2_to>0){
        $needSync=$db->prepare("SELECT id,username FROM users WHERE referred_by=? AND total_deposit>=? AND total_turnover<? AND (last_turnover_sync IS NULL OR last_turnover_sync<DATE_SUB(NOW(),INTERVAL 5 MINUTE)) LIMIT 10");
        $needSync->execute([$refCode,$st_t2_dep,$st_t2_to]);
        foreach($needSync->fetchAll() as $dl){
            syncTurnoverFast($db,$dl['id'],$dl['username']);
            $db->prepare("UPDATE users SET last_turnover_sync=NOW() WHERE id=?")->execute([$dl['id']]);
        }
    }

    if($tier===1){
        // TIER 1: pake syarat relaxed dari admin
        $sql="SELECT COUNT(*) FROM users WHERE referred_by=? AND total_deposit>=?";
        $params=[$refCode,$st_t1_dep];
        if($st_t1_to>0){$sql.=" AND total_turnover>=?";$params[]=$st_t1_to;}
        $vc=$db->prepare($sql);$vc->execute($params);$validCount=intval($vc->fetchColumn());
        if($validCount<1)err('Belum ada downline dengan deposit minimal Rp '.number_format($st_t1_dep,0,',','.'));
    }else{
        // TIER 2+: pake syarat normal dari admin
        $sql="SELECT COUNT(*) FROM users WHERE referred_by=? AND total_deposit>=?";
        $params=[$refCode,$st_t2_dep];
        if($st_t2_to>0){$sql.=" AND total_turnover>=?";$params[]=$st_t2_to;}
        $vc=$db->prepare($sql);$vc->execute($params);$validCount=intval($vc->fetchColumn());
        if($validCount<$tier)err('Belum cukup downline valid ('.$validCount.'/'.$tier.')');
    }
    $chk=$db->prepare("SELECT id FROM ref_claims WHERE user_id=? AND tier=?");$chk->execute([$uid,$tier]);
    if($chk->fetch())err('Reward tier ini sudah diklaim');
    $after=logTx($db,$uid,'bonus',$amount,'Hadiah Undangan - Promosi '.$tier.' Orang');
    $db->prepare("INSERT INTO ref_claims(user_id,tier,amount) VALUES(?,?,?)")->execute([$uid,$tier,$amount]);
    autoMemo($db,$uid,'Hadiah Undangan Diklaim','Selamat! Reward promosi '.$tier.' orang sebesar Rp '.number_format($amount,0,',','.').' telah masuk ke saldo Anda.');
    ok(['amount'=>$amount,'balance'=>$after,'tier'=>$tier]);
}

if($action==='referral_kinerja'){
    $uid=auth();
    $u=$db->prepare("SELECT ref_code FROM users WHERE id=?");$u->execute([$uid]);$rc=$u->fetchColumn();
    // Auto-create rate-limit column
    try{$db->exec("ALTER TABLE users ADD COLUMN last_turnover_sync DATETIME DEFAULT NULL AFTER total_turnover");}catch(Exception $e){}
    // Sync 5 downline yang hampir memenuhi TO (rate-limited 5 menit)
    if($rc&&defined('NEXUS_URL')&&NEXUS_URL){
        $needSync=$db->prepare("SELECT id,username FROM users WHERE referred_by=? AND total_deposit>=50000 AND total_turnover<1000000 AND (last_turnover_sync IS NULL OR last_turnover_sync<DATE_SUB(NOW(),INTERVAL 5 MINUTE)) ORDER BY last_turnover_sync ASC LIMIT 5");
        $needSync->execute([$rc]);
        foreach($needSync->fetchAll() as $dl){
            syncTurnoverFast($db,$dl['id'],$dl['username']);
            $db->prepare("UPDATE users SET last_turnover_sync=NOW() WHERE id=?")->execute([$dl['id']]);
        }
    }
    $search=$d['search']??'';
    $sql="SELECT username,total_deposit,total_turnover,created_at FROM users WHERE referred_by=?";
    $params=[$rc];
    if($search){$sql.=" AND username LIKE ?";$params[]='%'.$search.'%';}
    $sql.=" ORDER BY created_at DESC LIMIT 100";
    $s=$db->prepare($sql);$s->execute($params);$list=$s->fetchAll();
    // Add down_count for each
    foreach($list as &$r){
        $dc=$db->prepare("SELECT COUNT(*) FROM users WHERE referred_by=(SELECT ref_code FROM users WHERE username=?)");
        $dc->execute([$r['username']]);$r['down_count']=$dc->fetchColumn();
    }
    ok(['list'=>$list]);
}

// ─── CHANGE PASSWORD ───
if($action==='change_password'){
    $uid=auth();
    $old=$d['old_password']??'';$new=$d['new_password']??'';
    if(!$old||strlen($new)<6)err('Password minimal 6 karakter');
    $u=$db->prepare("SELECT password FROM users WHERE id=?");$u->execute([$uid]);$hash=$u->fetchColumn();
    if(!password_verify($old,$hash))err('Sandi lama salah');
    $db->prepare("UPDATE users SET password=? WHERE id=?")->execute([password_hash($new,PASSWORD_BCRYPT),$uid]);
    ok();
}

// ─── SET/CHANGE FUND PIN ───
if($action==='set_fund_pin'){
    $uid=auth();
    $oldPin=$d['old_pin']??'';$newPin=$d['new_pin']??'';
    if(!preg_match('/^\d{6}$/',$newPin))err('PIN harus 6 angka');
    $u=$db->prepare("SELECT fund_pin FROM users WHERE id=?");$u->execute([$uid]);$curPin=$u->fetchColumn();
    if($curPin){
        if(!password_verify($oldPin,$curPin))err('PIN lama salah');
    }
    $db->prepare("UPDATE users SET fund_pin=? WHERE id=?")->execute([password_hash($newPin,PASSWORD_BCRYPT),$uid]);
    ok();
}

// ─── VERIFY FUND PIN ───
if($action==='verify_fund_pin'){
    $uid=auth();
    $pin=$d['pin']??'';
    $u=$db->prepare("SELECT fund_pin FROM users WHERE id=?");$u->execute([$uid]);$hash=$u->fetchColumn();
    if(!$hash)err('Sandi dana belum diatur');
    if(!password_verify($pin,$hash))err('PIN salah');
    ok();
}

// ─── SAVE BANK ACCOUNT ───
if($action==='save_bank'){
    $uid=auth();
    $bank=$d['bank_name']??'';$name=$d['acc_name']??'';$num=$d['acc_number']??'';
    if(!$bank||!$name||!$num)err('Semua field wajib diisi');
    // Clean nomor rekening dari spasi/dash biar comparison akurat
    $numClean=preg_replace('/[^0-9]/','',$num);
    try{$db->exec("CREATE TABLE IF NOT EXISTS user_banks(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,bank_name VARCHAR(50),acc_name VARCHAR(100),acc_number VARCHAR(50),created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");}catch(Exception $e){}
    // Cek duplikat rekening di akun lain (anti multi-akun)
    $dup=$db->prepare("SELECT user_id FROM user_banks WHERE REPLACE(REPLACE(acc_number,' ',''),'-','')=? AND user_id!=? LIMIT 1");
    $dup->execute([$numClean,$uid]);
    if($dup->fetch())err('Nomor rekening ini sudah dipakai oleh akun lain. Kebijakan kami: 1 rekening = 1 akun.');
    $db->prepare("INSERT INTO user_banks(user_id,bank_name,acc_name,acc_number) VALUES(?,?,?,?)")->execute([$uid,$bank,$name,$num]);
    ok(['id'=>$db->lastInsertId()]);
}

// ─── CHECK TURNOVER REQUIREMENT ───
if($action==='check_turnover'){
    $uid=auth();
    $uname=$db->prepare("SELECT username FROM users WHERE id=?");$uname->execute([$uid]);$un=$uname->fetchColumn();
    $deps=$db->prepare("SELECT d.*,b.turnover_x,b.name as bonus_name FROM deposits d LEFT JOIN bonuses b ON d.bonus_id=b.id WHERE d.user_id=? AND d.status='paid' AND d.turnover_met=0 ORDER BY d.created_at ASC");
    $deps->execute([$uid]);
    $rows=$deps->fetchAll();
    if(empty($rows))ok(['can_withdraw'=>true,'pending'=>[],'total_turnover'=>$curTO,'summary'=>null]);
    // Cumulative calculation
    $totalTarget=0;$oldestDate=null;$pending=[];$depIds=[];
    foreach($rows as $dep){
        $hasBonus=intval($dep['bonus_amount']??0)>0 && !empty($dep['turnover_x']);
        if($hasBonus){
            $t=intval(($dep['nominal']+$dep['bonus_amount'])*$dep['turnover_x']);
            $label=$dep['bonus_name'];
            $tx=intval($dep['turnover_x']);
        }else{
            $t=intval($dep['nominal']);
            $label='Deposit Rp '.number_format($dep['nominal'],0,',','.');
            $tx=1;
        }
        $totalTarget+=$t;
        $depIds[]=$dep['id'];
        if(!$oldestDate||strtotime($dep['created_at'])<strtotime($oldestDate))$oldestDate=$dep['created_at'];
        $pending[]=['bonus_name'=>$label,'deposit'=>intval($dep['nominal']),'bonus'=>intval($dep['bonus_amount']),'turnover_x'=>$tx,'target'=>$t,'created_at'=>$dep['created_at']];
    }
    $oldDateOnly=date('Y-m-d',strtotime($oldestDate));
    $totalBets=getBetsSince($un,$oldDateOnly);
    $completed=$totalBets>=$totalTarget;
    if($completed){
        // Auto-mark semua met
        $in=implode(',',array_fill(0,count($depIds),'?'));
        $db->prepare("UPDATE deposits SET turnover_met=1 WHERE id IN($in)")->execute($depIds);
    }
    ok(['can_withdraw'=>$completed,'pending'=>$pending,'total_turnover'=>$curTO,'summary'=>[
        'total_target'=>$totalTarget,
        'total_done'=>max(0,$totalBets),
        'remaining'=>max(0,$totalTarget-$totalBets),
        'pct'=>$totalTarget>0?min(100,round(max(0,$totalBets)/$totalTarget*100)):100,
        'count'=>count($pending)
    ]]);
}

// ─── WITHDRAW ───
if($action==='withdraw'){
    $uid=auth();
    $amount=intval($d['amount']??0);$pin=$d['pin']??'';
    if($amount<50000)err('Minimum penarikan Rp 50.000');

    // ═══ FILE LOCK: cegah double-submit dari user yg sama ═══
    $lockFile=sys_get_temp_dir()."/lx_wdlock_$uid.lock";
    $lockFp=@fopen($lockFile,'c');
    if(!$lockFp)err('Sistem sibuk, coba lagi sebentar');
    if(!flock($lockFp,LOCK_EX|LOCK_NB)){
        fclose($lockFp);
        err('Sedang memproses withdraw sebelumnya. Tunggu beberapa detik lalu refresh.');
    }

    try{
        // Sync turnover dulu sebelum cek TO requirement
        $uname3=$db->prepare("SELECT username FROM users WHERE id=?");$uname3->execute([$uid]);$un3=$uname3->fetchColumn();
        $curTO=syncTurnoverFast($db,$uid,$un3);
        $deps=$db->prepare("SELECT d.*,b.turnover_x,b.name as bonus_name FROM deposits d LEFT JOIN bonuses b ON d.bonus_id=b.id WHERE d.user_id=? AND d.status='paid' AND d.turnover_met=0 ORDER BY d.created_at ASC");
        $deps->execute([$uid]);
        $pendingList=$deps->fetchAll();
        if(!empty($pendingList)){
            $totalTarget=0;$oldestDate=null;$depIds=[];$breakdown=[];
            foreach($pendingList as $dep){
                $hasBonus=intval($dep['bonus_amount']??0)>0 && !empty($dep['turnover_x']);
                if($hasBonus){
                    $t=intval(($dep['nominal']+$dep['bonus_amount'])*$dep['turnover_x']);
                    $breakdown[]=$dep['bonus_name'].' ('.$dep['turnover_x'].'x): Rp '.number_format($t,0,',','.');
                }else{
                    $t=intval($dep['nominal']); // 1x nominal
                    $breakdown[]='Depo Rp '.number_format($dep['nominal'],0,',','.').' (1x): Rp '.number_format($t,0,',','.');
                }
                $totalTarget+=$t;
                $depIds[]=$dep['id'];
                if(!$oldestDate||strtotime($dep['created_at'])<strtotime($oldestDate))$oldestDate=$dep['created_at'];
            }
            $oldDateOnly=date('Y-m-d',strtotime($oldestDate));
            $totalBets=getBetsSince($un3,$oldDateOnly);
            if($totalBets>=$totalTarget){
                // Semua TO terpenuhi, mark all
                $in=implode(',',array_fill(0,count($depIds),'?'));
                $db->prepare("UPDATE deposits SET turnover_met=1 WHERE id IN($in)")->execute($depIds);
            }else{
                $need=$totalTarget-$totalBets;
                flock($lockFp,LOCK_UN);fclose($lockFp);
                err('Turnover belum cukup. Total bet: Rp '.number_format(max(0,$totalBets),0,',','.').' / Rp '.number_format($totalTarget,0,',','.').'. Sisa: Rp '.number_format($need,0,',','.').'. Rincian: '.implode(' + ',$breakdown));
            }
        }

        // ═══ TRANSACTION dengan row lock ═══
        $db->beginTransaction();

        // Lock user row
        $u=$db->prepare("SELECT fund_pin,balance FROM users WHERE id=? FOR UPDATE");
        $u->execute([$uid]);$row=$u->fetch();
        if(!$row['fund_pin']){$db->rollBack();flock($lockFp,LOCK_UN);fclose($lockFp);err('Sandi dana belum diatur');}
        if(!password_verify($pin,$row['fund_pin'])){$db->rollBack();flock($lockFp,LOCK_UN);fclose($lockFp);err('PIN salah');}
        if(intval($row['balance'])<$amount){$db->rollBack();flock($lockFp,LOCK_UN);fclose($lockFp);err('Saldo tidak cukup');}

        // Get bank account
        try{$ba=$db->prepare("SELECT * FROM user_banks WHERE user_id=? ORDER BY id DESC LIMIT 1");$ba->execute([$uid]);$bank=$ba->fetch();}catch(Exception $e){$bank=null;}
        if(!$bank){$db->rollBack();flock($lockFp,LOCK_UN);fclose($lockFp);err('Tambahkan rekening penarikan terlebih dahulu');}

        // Cek pending withdraw (DALAM transaction supaya atomic)
        $pwd=$db->prepare("SELECT COUNT(*) FROM withdrawals WHERE user_id=? AND status='pending'");
        $pwd->execute([$uid]);
        if($pwd->fetchColumn()>0){$db->rollBack();flock($lockFp,LOCK_UN);fclose($lockFp);err('Masih ada withdraw pending, tunggu diproses dulu');}

        // Atomic deduct via UPDATE WHERE balance >= amount
        $upd=$db->prepare("UPDATE users SET balance = balance - ? WHERE id=? AND balance >= ?");
        $upd->execute([$amount,$uid,$amount]);
        if($upd->rowCount()===0){
            $db->rollBack();flock($lockFp,LOCK_UN);fclose($lockFp);
            err('Saldo berubah, silakan refresh halaman.');
        }
        $newBal=intval($row['balance'])-$amount;

        // Log transaction
        $db->prepare("INSERT INTO transactions(user_id,type,amount,balance_before,balance_after,note) VALUES(?,?,?,?,?,?)")
           ->execute([$uid,'withdraw',-$amount,intval($row['balance']),$newBal,'Penarikan ke '.$bank['bank_name'].' '.$bank['acc_number']]);

        // Record withdrawal
        try{$db->exec("CREATE TABLE IF NOT EXISTS withdrawals(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,amount BIGINT UNSIGNED,bank_name VARCHAR(50),acc_name VARCHAR(100),acc_number VARCHAR(50),status VARCHAR(20) DEFAULT 'pending',created_at DATETIME DEFAULT CURRENT_TIMESTAMP,processed_at DATETIME DEFAULT NULL) ENGINE=InnoDB");}catch(Exception $e){}
        $db->prepare("INSERT INTO withdrawals(user_id,amount,bank_name,acc_name,acc_number) VALUES(?,?,?,?,?)")
           ->execute([$uid,$amount,$bank['bank_name'],$bank['acc_name'],$bank['acc_number']]);
        $wdInsertedId=intval($db->lastInsertId());

        $db->commit();

        // Post-transaction (non-critical)
        autoMemo($db,$uid,'Penarikan Diajukan','Penarikan Rp '.number_format($amount,0,',','.').' ke '.$bank['bank_name'].' '.$bank['acc_number'].' sedang diproses.');

        // Kirim notifikasi ke admin Telegram (non-blocking, error silent)
        try{
            require_once __DIR__.'/../includes/tg_wd.php';
            tgWdNotify($db,$wdInsertedId);
        }catch(Exception $e){}

        flock($lockFp,LOCK_UN);fclose($lockFp);
        ok(['balance'=>$newBal,'amount'=>$amount]);
    }catch(Exception $e){
        try{$db->rollBack();}catch(Exception $ee){}
        @flock($lockFp,LOCK_UN);@fclose($lockFp);
        err('Transaksi gagal: '.$e->getMessage());
    }
}

// ─── DEBUG: Test game log & turnover ───
if($action==='debug_turnover'){
    $uid=auth();
    $u=$db->prepare("SELECT username,total_turnover,rebate_claimed_to,created_at FROM users WHERE id=?");
    $u->execute([$uid]);$user=$u->fetch();
    $result=['username'=>$user['username'],'db_turnover'=>$user['total_turnover'],'tests'=>[]];
    $start=date('Y-m-d',strtotime('-30 days')).' 00:00:00';
    $end=date('Y-m-d 23:59:59');
    foreach(['slot','casino'] as $gt){
        $body=json_encode(['method'=>'get_game_log','agent_code'=>NEXUS_AGENT,'agent_token'=>NEXUS_TOKEN,
            'user_code'=>$user['username'],'game_type'=>$gt,
            'start'=>$start,'end'=>$end,'page'=>0,'perPage'=>10]);
        $ch=curl_init(NEXUS_URL);
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,
            CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>10]);
        $raw=curl_exec($ch);curl_close($ch);
        $r=json_decode($raw,true);
        $result['tests'][$gt]=[
            'status'=>$r['status']??'null',
            'total_count'=>$r['total_count']??0,
            'sample_count'=>count($r[$gt]??[]),
            'sample'=>array_slice($r[$gt]??[],0,2),
            'raw_start'=>$start,
            'raw_end'=>$end
        ];
    }
    ok($result);
}

// ─── LUCKY SPIN ───
// Tiket = jumlah deposit paid / 100rb MINUS jumlah spin yang sudah dipakai

if($action==='spin_init'){
    $uid=getUid();
    // Auto-create tables
    try{$db->exec("CREATE TABLE IF NOT EXISTS spin_prizes (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,label VARCHAR(50),amount BIGINT UNSIGNED DEFAULT 0,probability DECIMAL(6,3) DEFAULT 0,color VARCHAR(20) DEFAULT '#38bdf8',sort_order INT DEFAULT 0) ENGINE=InnoDB");}catch(Exception $e){}
    try{$db->exec("CREATE TABLE IF NOT EXISTS spin_history (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,prize_id INT UNSIGNED DEFAULT NULL,label VARCHAR(50),amount BIGINT UNSIGNED DEFAULT 0,created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");}catch(Exception $e){}
    // Auto-fix: kalau kolom probability masih DECIMAL(6,3) — upgrade ke DECIMAL(10,5) buat probability sangat kecil (0.00005)
    try{$db->exec("ALTER TABLE spin_prizes MODIFY probability DECIMAL(10,5) DEFAULT 0");}catch(Exception $e){}

    // Seed prizes if empty
    $cnt=$db->query("SELECT COUNT(*) FROM spin_prizes")->fetchColumn();
    if(!$cnt){
        $db->exec("INSERT INTO spin_prizes (label,amount,probability,color,sort_order) VALUES
        ('Rp 5.000.000',5000000,0.00005,'#f59e0b',1),
        ('Rp 2.000.000',2000000,0.0001,'#ef4444',2),
        ('Rp 1.000.000',1000000,0.0005,'#fbbf24',3),
        ('Rp 500.000',500000,0.002,'#3b82f6',4),
        ('Rp 250.000',250000,0.005,'#06b6d4',5),
        ('Rp 100.000',100000,0.01,'#10b981',6),
        ('Rp 10.000',10000,0.5,'#38bdf8',7),
        ('Rp 5.000',5000,2.5,'#0284c7',8),
        ('Rp 2.000',2000,8.0,'#38bdf8',9),
        ('Rp 1.000',1000,18.982,'#0284c7',10),
        ('Zonk',0,25.0,'#6b7280',11),
        ('Zonk',0,25.0,'#4b5563',12),
        ('Zonk',0,20.0,'#374151',13)");
    }else{
        // Auto-migrate: kalau probability Rp 100rb masih >= 0.1% (dari seed lama), reset seluruh prize
        $old=$db->query("SELECT COUNT(*) FROM spin_prizes WHERE amount>=100000 AND probability>=0.1")->fetchColumn();
        if($old>0){
            $db->exec("DELETE FROM spin_prizes");
            $db->exec("INSERT INTO spin_prizes (label,amount,probability,color,sort_order) VALUES
            ('Rp 5.000.000',5000000,0.00005,'#f59e0b',1),
            ('Rp 2.000.000',2000000,0.0001,'#ef4444',2),
            ('Rp 1.000.000',1000000,0.0005,'#fbbf24',3),
            ('Rp 500.000',500000,0.002,'#3b82f6',4),
            ('Rp 250.000',250000,0.005,'#06b6d4',5),
            ('Rp 100.000',100000,0.01,'#10b981',6),
            ('Rp 10.000',10000,0.5,'#38bdf8',7),
            ('Rp 5.000',5000,2.5,'#0284c7',8),
            ('Rp 2.000',2000,8.0,'#38bdf8',9),
            ('Rp 1.000',1000,18.982,'#0284c7',10),
            ('Zonk',0,25.0,'#6b7280',11),
            ('Zonk',0,25.0,'#4b5563',12),
            ('Zonk',0,20.0,'#374151',13)");
        }
    }
    $prizes=$db->query("SELECT * FROM spin_prizes ORDER BY sort_order ASC")->fetchAll();
    $tickets=0;
    if($uid){
        // Tiket = total deposit paid dibagi 100rb, dikurangi spin yang sudah dipakai
        $totalDep=$db->prepare("SELECT COALESCE(SUM(nominal),0) FROM deposits WHERE user_id=? AND status='paid'");
        $totalDep->execute([$uid]);
        $earned=intval(floor($totalDep->fetchColumn()/100000));
        $used=$db->prepare("SELECT COUNT(*) FROM spin_history WHERE user_id=?");
        $used->execute([$uid]);
        $tickets=max(0,$earned-intval($used->fetchColumn()));
    }
    $history=[];
    if($uid){$h=$db->prepare("SELECT label,amount,created_at FROM spin_history WHERE user_id=? ORDER BY created_at DESC LIMIT 10");$h->execute([$uid]);$history=$h->fetchAll();}
    ok(['prizes'=>$prizes,'tickets'=>$tickets,'history'=>$history]);
}

if($action==='spin_do'){
    $uid=auth();
    // Auto-create tables (safety)
    try{$db->exec("CREATE TABLE IF NOT EXISTS spin_prizes (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,label VARCHAR(50),amount BIGINT UNSIGNED DEFAULT 0,probability DECIMAL(10,5) DEFAULT 0,color VARCHAR(20) DEFAULT '#38bdf8',sort_order INT DEFAULT 0) ENGINE=InnoDB");}catch(Exception $e){}
    try{$db->exec("ALTER TABLE spin_prizes MODIFY probability DECIMAL(10,5) DEFAULT 0");}catch(Exception $e){}
    try{$db->exec("CREATE TABLE IF NOT EXISTS spin_history (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,prize_id INT UNSIGNED DEFAULT NULL,label VARCHAR(50),amount BIGINT UNSIGNED DEFAULT 0,created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");}catch(Exception $e){}
    // Hitung tiket sisa langsung dari deposit
    $totalDep=$db->prepare("SELECT COALESCE(SUM(nominal),0) FROM deposits WHERE user_id=? AND status='paid'");
    $totalDep->execute([$uid]);
    $earned=intval(floor($totalDep->fetchColumn()/100000));
    $usedQ=$db->prepare("SELECT COUNT(*) FROM spin_history WHERE user_id=?");
    $usedQ->execute([$uid]);
    $usedCount=intval($usedQ->fetchColumn());
    $tickets=max(0,$earned-$usedCount);
    if($tickets<=0)err('Tidak ada tiket. Deposit min Rp 100.000 untuk dapat tiket!');

    // Get prizes - auto-seed if empty
    $prizes=$db->query("SELECT * FROM spin_prizes ORDER BY sort_order ASC")->fetchAll();
    if(empty($prizes)){
        // Label tetap tampil "Rp 5.000.000" dll di wheel (biar user tertarik)
        // Tapi probability SANGAT KECIL — user realistis dapat 1rb-10rb aja
        // Total probability harus 100%
        $db->exec("INSERT INTO spin_prizes (label,amount,probability,color,sort_order) VALUES
        ('Rp 5.000.000',5000000,0.00005,'#f59e0b',1),
        ('Rp 2.000.000',2000000,0.0001,'#ef4444',2),
        ('Rp 1.000.000',1000000,0.0005,'#fbbf24',3),
        ('Rp 500.000',500000,0.002,'#3b82f6',4),
        ('Rp 250.000',250000,0.005,'#06b6d4',5),
        ('Rp 100.000',100000,0.01,'#10b981',6),
        ('Rp 10.000',10000,0.5,'#38bdf8',7),
        ('Rp 5.000',5000,2.5,'#0284c7',8),
        ('Rp 2.000',2000,8.0,'#38bdf8',9),
        ('Rp 1.000',1000,18.982,'#0284c7',10),
        ('Zonk',0,25.0,'#6b7280',11),
        ('Zonk',0,25.0,'#4b5563',12),
        ('Zonk',0,20.0,'#374151',13)");
        $prizes=$db->query("SELECT * FROM spin_prizes ORDER BY sort_order ASC")->fetchAll();
    }
    if(empty($prizes))err('Hadiah spin belum tersedia. Hubungi admin.');

    $total=array_sum(array_column($prizes,'probability'));
    if($total<=0)$total=100; // fallback prevent div-by-zero
    $rand=mt_rand(0,intval($total*1000))/1000; // 0..$total uniform
    $cumulative=0;$winner=null;$winIdx=0;
    foreach($prizes as $idx=>$p){
        $cumulative+=floatval($p['probability']);
        if($rand<=$cumulative){$winner=$p;$winIdx=$idx;break;}
    }
    if(!$winner){$winner=$prizes[count($prizes)-1];$winIdx=count($prizes)-1;}

    // Record spin
    try{
        $db->prepare("INSERT INTO spin_history (user_id,prize_id,label,amount) VALUES (?,?,?,?)")
           ->execute([$uid,$winner['id'],$winner['label'],$winner['amount']]);
    }catch(Exception $e){
        @file_put_contents(__DIR__.'/../error_log.txt',date('Y-m-d H:i:s').' spin_history INSERT: '.$e->getMessage()."\n",FILE_APPEND);
        err('Gagal menyimpan hasil spin. Coba lagi.');
    }

    // Credit prize via logTx (handles balance update + transaction log atomically)
    if(intval($winner['amount'])>0){
        logTx($db,$uid,'spin_win',intval($winner['amount']),'Hadiah spin: '.$winner['label']);
    }

    ok(['winner'=>$winner,'tickets_left'=>max(0,$tickets-1),'prize_index'=>$winIdx]);
}

// ══════ PUSH NOTIFICATION SUBSCRIPTION ══════
if($action==='push_subscribe'){
    $uid=auth();
    $endpoint=$d['endpoint']??'';$p256=$d['p256dh']??'';$auth=$d['auth']??'';
    if(!$endpoint||!$p256||!$auth)err('Invalid subscription');
    try{$db->exec("CREATE TABLE IF NOT EXISTS push_subscriptions(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,endpoint VARCHAR(500) NOT NULL,p256dh VARCHAR(200) NOT NULL,auth VARCHAR(100) NOT NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_ep(endpoint(255))) ENGINE=InnoDB");}catch(Exception $e){}
    $st=$db->prepare("INSERT INTO push_subscriptions(user_id,endpoint,p256dh,auth) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE user_id=VALUES(user_id),p256dh=VALUES(p256dh),auth=VALUES(auth)");
    $st->execute([$uid,$endpoint,$p256,$auth]);
    ok(['subscribed'=>true]);
}

if($action==='push_unsubscribe'){
    $uid=auth();
    $endpoint=$d['endpoint']??'';
    try{$db->prepare("DELETE FROM push_subscriptions WHERE user_id=? AND endpoint=?")->execute([$uid,$endpoint]);}catch(Exception $e){}
    ok(['unsubscribed'=>true]);
}

if($action==='vapid_public_key'){
    require_once __DIR__.'/../includes/webpush.php';
    $v=webpush_get_vapid($db);
    if(!$v)err('VAPID unavailable');
    ok(['public_key'=>$v['public']]);
}

// Test push ke user sendiri (dipanggil setelah user aktifin notif — biar yakin jalan)
if($action==='push_test'){
    $uid=auth();
    require_once __DIR__.'/../includes/webpush.php';
    $r=pushNotify($db,$uid,'🔔 Notifikasi Aktif!','Kamu akan dapat info deposit, withdraw, & bonus secara real-time.','/dashboard.php');
    ok(['sent'=>$r['sent']??0,'failed'=>$r['failed']??0]);
}

err('INVALID_ACTION');
