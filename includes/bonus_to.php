<?php
/**
 * BONUS TURNOVER TRACKER
 * 
 * Semua bonus klaim (check-in, bonus depo, apresiasi, misteri, bantuan, rebate)
 * WAJIB 1x turnover sebelum bisa ditarik.
 * 
 * Logic:
 * - Saat bonus diklaim → insert ke `bonus_turnover` dengan target = bonus_amount * 1
 * - Saat user betting (game_transfer out) → accumulate ke `turnover_done` di semua bonus yg belum met
 * - Saat WD → block kalau ada bonus yg turnover_met = 0
 */

function bonusTO_ensureTable($db){
    try{
        $db->exec("CREATE TABLE IF NOT EXISTS bonus_turnover (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            bonus_type VARCHAR(30) NOT NULL,
            bonus_amount BIGINT UNSIGNED NOT NULL,
            target BIGINT UNSIGNED NOT NULL,
            turnover_at_claim BIGINT UNSIGNED DEFAULT 0,
            turnover_done BIGINT UNSIGNED DEFAULT 0,
            met TINYINT DEFAULT 0,
            note VARCHAR(200) DEFAULT '',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            met_at DATETIME DEFAULT NULL,
            INDEX idx_user_met(user_id,met)
        ) ENGINE=InnoDB");
    }catch(Exception $e){}
}

/**
 * Track bonus — dipanggil setelah bonus diklaim/dikredit
 * @param int $turnoverMultiplier — default 1x
 */
function bonusTO_track($db,$uid,$bonusType,$bonusAmount,$note='',$turnoverMultiplier=1){
    bonusTO_ensureTable($db);

    // Get current user turnover total (kumulatif dari transactions game_transfer out)
    $curTO=0;
    try{
        $q=$db->prepare("SELECT COALESCE(SUM(ABS(amount)),0) FROM transactions WHERE user_id=? AND type='game_transfer' AND amount<0");
        $q->execute([$uid]);
        $curTO=(int)$q->fetchColumn();
    }catch(Exception $e){}

    $target=intval($bonusAmount*$turnoverMultiplier);

    try{
        $db->prepare("INSERT INTO bonus_turnover(user_id,bonus_type,bonus_amount,target,turnover_at_claim,note) VALUES(?,?,?,?,?,?)")
           ->execute([$uid,$bonusType,$bonusAmount,$target,$curTO,$note]);
    }catch(Exception $e){}
}

/**
 * Update TO progress untuk semua bonus user yg belum met.
 * Dipanggil otomatis saat user transfer ke game (bet = turnover).
 */
function bonusTO_update($db,$uid){
    bonusTO_ensureTable($db);

    // Current total TO
    $curTO=0;
    try{
        $q=$db->prepare("SELECT COALESCE(SUM(ABS(amount)),0) FROM transactions WHERE user_id=? AND type='game_transfer' AND amount<0");
        $q->execute([$uid]);
        $curTO=(int)$q->fetchColumn();
    }catch(Exception $e){return;}

    // Fetch bonus yang belum met
    $rows=$db->prepare("SELECT id,target,turnover_at_claim FROM bonus_turnover WHERE user_id=? AND met=0");
    $rows->execute([$uid]);

    foreach($rows->fetchAll() as $r){
        $done=max(0,$curTO-intval($r['turnover_at_claim']));
        if($done>=intval($r['target'])){
            $db->prepare("UPDATE bonus_turnover SET met=1,turnover_done=?,met_at=NOW() WHERE id=?")
               ->execute([$done,$r['id']]);
        }else{
            $db->prepare("UPDATE bonus_turnover SET turnover_done=? WHERE id=?")
               ->execute([$done,$r['id']]);
        }
    }
}

/**
 * Cek apakah user masih punya bonus yg belum met TO-nya
 * @return array ['ok'=>bool, 'pending_count'=>int, 'total_remaining'=>int, 'total_target'=>int]
 */
function bonusTO_status($db,$uid){
    bonusTO_ensureTable($db);

    // Update dulu before check
    bonusTO_update($db,$uid);

    $q=$db->prepare("SELECT bonus_type,bonus_amount,target,turnover_done,note FROM bonus_turnover WHERE user_id=? AND met=0");
    $q->execute([$uid]);
    $pending=$q->fetchAll();

    $totalRemaining=0;$totalTarget=0;
    $list=[];
    foreach($pending as $p){
        $remaining=max(0,intval($p['target'])-intval($p['turnover_done']));
        $totalRemaining+=$remaining;
        $totalTarget+=intval($p['target']);
        $list[]=[
            'type'=>$p['bonus_type'],
            'amount'=>intval($p['bonus_amount']),
            'target'=>intval($p['target']),
            'done'=>intval($p['turnover_done']),
            'remaining'=>$remaining,
            'note'=>$p['note']
        ];
    }

    return [
        'ok'=>($totalRemaining===0),
        'pending_count'=>count($pending),
        'total_remaining'=>$totalRemaining,
        'total_target'=>$totalTarget,
        'pending'=>$list,
    ];
}
