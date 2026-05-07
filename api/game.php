<?php
require_once __DIR__.'/../includes/config.php';
$d=input();$action=$d['action']??$_GET['action']??'';

function nexus($method,$extra=[]){
    $body=array_merge(['method'=>$method,'agent_code'=>NEXUS_AGENT,'agent_token'=>NEXUS_TOKEN],$extra);
    $ch=curl_init(NEXUS_URL);
    curl_setopt_array($ch,[
        CURLOPT_POST=>true,
        CURLOPT_POSTFIELDS=>json_encode($body),
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],
        CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_CONNECTTIMEOUT=>10,
        CURLOPT_TIMEOUT=>25,

    ]);
    $raw=curl_exec($ch);
    $curlErr=curl_error($ch);
    curl_close($ch);
    $result=json_decode($raw,true);
    if(!$result){
        $result=['status'=>0,'msg'=>'NO_RESPONSE','curl_error'=>$curlErr,'raw'=>substr((string)$raw,0,100)];
    }
    $logBody=array_diff_key($body,['agent_token'=>1]);
    @file_put_contents(__DIR__.'/../nexus_log.txt',
        date('Y-m-d H:i:s')." [$method] ".json_encode($logBody)." => ".json_encode($result)."\n",
        FILE_APPEND);
    return $result;
}

function ensureNexusUser($db,$uid){
    $u=$db->prepare("SELECT username FROM users WHERE id=?");$u->execute([$uid]);
    return $u->fetchColumn();
}

// ═══ LOCK SYSTEM: cegah race condition saat game transfer ═══
// Pake file-based lock karena cepat + ga butuh tabel tambahan
function gameLock($uid,$timeout=15){
    $lockFile=sys_get_temp_dir()."/lx_gamelock_$uid.lock";
    $fp=@fopen($lockFile,'c');
    if(!$fp)return null;
    $start=time();
    while(!flock($fp,LOCK_EX|LOCK_NB)){
        if(time()-$start>$timeout){fclose($fp);return null;}
        usleep(200000); // 200ms
    }
    return $fp;
}
function gameUnlock($fp){
    if($fp){flock($fp,LOCK_UN);fclose($fp);}
}

// ═══ ENSURE USER EXISTS ═══
if($action==='ensure_user'){
    $uid=auth();
    $un=ensureNexusUser($db,$uid);
    if(!$un)err('User not found');
    $info=nexus('money_info',['user_code'=>$un]);
    $gameBal=intval(floatval($info['user']['balance']??0));
    $localBal=$db->prepare("SELECT balance FROM users WHERE id=?");$localBal->execute([$uid]);$localBal=intval($localBal->fetchColumn());
    ok(['nexus_user'=>$un,'game_balance'=>$gameBal,'local_balance'=>$localBal]);
}

// ═══ GET PROVIDERS ═══
if($action==='providers'){
    try{$s=$db->query("SELECT code,name,logo,sort_order,status,game_count FROM providers WHERE status=1 ORDER BY sort_order ASC, game_count DESC");}
    catch(Exception $e){$s=$db->query("SELECT code,name,game_count FROM providers WHERE status=1 ORDER BY sort_order ASC, game_count DESC");}
    $list=$s->fetchAll();
    if(count($list)>0){ok(['providers'=>$list]);}
    $res=nexus('provider_list');
    if($res['status']==1&&!empty($res['providers'])){
        $st=$db->prepare("INSERT INTO providers(code,name,status) VALUES(?,?,1) ON DUPLICATE KEY UPDATE name=VALUES(name),updated_at=NOW()");
        foreach($res['providers'] as $p){$st->execute([$p['code'],$p['name']]);}
        $s=$db->query("SELECT code,name,logo,sort_order,status,game_count FROM providers WHERE status=1 ORDER BY name");
        ok(['providers'=>$s->fetchAll()]);
    }
    err('Gagal memuat provider');
}

// ═══ GET GAMES ═══
if($action==='games'){
    $prov=$d['provider']??$_GET['provider']??'';
    if(!$prov)err('Provider wajib');
    // Sort by click_count DESC (populer dulu) → baru game_name
    $s=$db->prepare("SELECT g.game_code,g.game_name,g.game_type,g.banner,g.status,COALESCE(c.click_count,0) as clicks
                     FROM games g LEFT JOIN game_clicks c ON c.provider_code=g.provider_code AND c.game_code=g.game_code
                     WHERE g.provider_code=? AND g.status=1
                       AND g.banner IS NOT NULL AND g.banner != ''
                     ORDER BY clicks DESC, g.game_name ASC");
    $s->execute([$prov]);$list=$s->fetchAll();
    if(count($list)>0){ok(['games'=>$list]);}
    $res=nexus('game_list',['provider_code'=>$prov]);
    if($res['status']!=1)$res=nexus('game_list_v2',['provider_code'=>$prov]);
    if($res['status']==1&&!empty($res['games'])){
        $st=$db->prepare("INSERT INTO games(provider_code,game_code,game_name,game_type,banner) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE game_name=VALUES(game_name),banner=VALUES(banner),updated_at=NOW()");
        foreach($res['games'] as $g){
            $name=is_array($g['game_name']??'')?(($g['game_name']['en']??$g['game_name']['id']??'')):($g['game_name']??'');
            $st->execute([$prov,$g['game_code']??$g['id']??'',$name,$g['game_type']??'slot',$g['banner']??'']);
        }
        $cnt=$db->prepare("SELECT COUNT(*) FROM games WHERE provider_code=?");$cnt->execute([$prov]);
        $db->prepare("UPDATE providers SET game_count=? WHERE code=?")->execute([$cnt->fetchColumn(),$prov]);
        $s=$db->prepare("SELECT g.game_code,g.game_name,g.game_type,g.banner,g.status,COALESCE(c.click_count,0) as clicks
                         FROM games g LEFT JOIN game_clicks c ON c.provider_code=g.provider_code AND c.game_code=g.game_code
                         WHERE g.provider_code=? AND g.status=1
                           AND g.banner IS NOT NULL AND g.banner != ''
                         ORDER BY clicks DESC, g.game_name ASC");
        $s->execute([$prov]);
        ok(['games'=>$s->fetchAll()]);
    }
    ok(['games'=>[]]);
}

// ═══ ADMIN: SYNC ALL ═══
if($action==='sync_all'){
    admin();
    $res=nexus('provider_list');
    if($res['status']!=1)err($res['msg']??'API error');
    $totalGames=0;
    $st=$db->prepare("INSERT INTO providers(code,name,status) VALUES(?,?,1) ON DUPLICATE KEY UPDATE name=VALUES(name),updated_at=NOW()");
    $sg=$db->prepare("INSERT INTO games(provider_code,game_code,game_name,game_type,banner) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE game_name=VALUES(game_name),banner=VALUES(banner),updated_at=NOW()");
    foreach($res['providers'] as $p){
        $st->execute([$p['code'],$p['name']]);
        $gr=nexus('game_list',['provider_code'=>$p['code']]);
        if($gr['status']!=1)$gr=nexus('game_list_v2',['provider_code'=>$p['code']]);
        if($gr['status']==1&&!empty($gr['games'])){
            foreach($gr['games'] as $g){
                $name=is_array($g['game_name']??'')?(($g['game_name']['en']??$g['game_name']['id']??'')):($g['game_name']??'');
                $sg->execute([$p['code'],$g['game_code']??$g['id']??'',$name,$g['game_type']??'slot',$g['banner']??'']);
            }
            $cnt=count($gr['games']);
            $totalGames+=$cnt;
            $db->prepare("UPDATE providers SET game_count=? WHERE code=?")->execute([$cnt,$p['code']]);
        }
    }
    ok(['providers'=>count($res['providers']),'games'=>$totalGames]);
}

if($action==='admin_providers'){
    admin();
    $s=$db->query("SELECT code,name,status,game_count,updated_at FROM providers ORDER BY name");
    $list=$s->fetchAll();
    if(count($list)==0){
        $res=nexus('provider_list');
        if($res['status']==1){
            $st=$db->prepare("INSERT INTO providers(code,name) VALUES(?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)");
            foreach($res['providers'] as $p)$st->execute([$p['code'],$p['name']]);
            $s=$db->query("SELECT code,name,status,game_count,updated_at FROM providers ORDER BY name");
            $list=$s->fetchAll();
        }
    }
    ok(['providers'=>$list]);
}

// ═══════════════════════════════════════════════════════════════════
// ═══ LAUNCH GAME — SECURE VERSION with lock, agent_sign, rollback ═══
// ═══════════════════════════════════════════════════════════════════
if($action==='launch'){
    $uid=auth();
    $provider=$d['provider']??'';$gameCode=$d['game_code']??'';
    if(!$provider||!$gameCode)err('Provider dan game_code wajib');

    // ═══ CLICK COUNTER: naik 1 tiap launch biar game populer naik urutan ═══
    try{
        $db->exec("CREATE TABLE IF NOT EXISTS game_clicks (
            game_code VARCHAR(100) NOT NULL,
            provider_code VARCHAR(50) NOT NULL,
            click_count BIGINT UNSIGNED DEFAULT 0,
            last_clicked DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (provider_code, game_code),
            INDEX idx_clicks (click_count DESC)
        ) ENGINE=InnoDB");
        $db->prepare("INSERT INTO game_clicks (provider_code,game_code,click_count,last_clicked) VALUES (?,?,1,NOW())
            ON DUPLICATE KEY UPDATE click_count=click_count+1, last_clicked=NOW()")
           ->execute([$provider,$gameCode]);
    }catch(Exception $e){}

    // ── LOCK: cegah concurrent launch dari user yang sama ──
    $lock=gameLock($uid,10);
    if(!$lock)err('Launch game lain sedang diproses. Tunggu sebentar lalu coba lagi.');

    try{
        $username=ensureNexusUser($db,$uid);
        if(!$username){gameUnlock($lock);err('User tidak ditemukan di database');}

        // Generate unique agent_sign utk idempotency antar request
        $launchSign='LCH_'.$uid.'_'.time().'_'.substr(md5(mt_rand()),0,8);

        @file_put_contents(__DIR__.'/../nexus_log.txt',
            date('Y-m-d H:i:s')." [LAUNCH_START] uid=$uid user=$username provider=$provider game=$gameCode sign=$launchSign\n",FILE_APPEND);

        // ── STEP 1: Tarik saldo game yg ada (bersihin dulu sebelum deposit baru) ──
        // Untuk user baru yang belum pernah deposit, money_info bisa balikin INVALID_USER
        // atau status 0 — itu wajar, treat sebagai 0 balance dan skip pre-pull.
        // User akan otomatis dibuat di Nexus saat user_deposit di STEP 4.
        $pullBack=0;
        $info=nexus('money_info',['user_code'=>$username]);
        $existingBal=intval(floatval($info['user']['balance']??0));

        if($existingBal>0){
            $pullSign=$launchSign.'_PULL';
            $wd=nexus('user_withdraw',['user_code'=>$username,'amount'=>$existingBal,'agent_sign'=>$pullSign]);

            if(($wd['status']??0)==1){
                // WITHDRAW SUCCESS — credit ke DB dalam transaction biar atomic
                try{
                    $db->beginTransaction();
                    logTx($db,$uid,'game_win',$existingBal,'Saldo kembali dari game (pre-launch)',$pullSign);
                    $db->commit();
                    $pullBack=$existingBal;
                }catch(Exception $e){
                    try{$db->rollBack();}catch(Exception $ee){}
                    // CRITICAL: Nexus udah kurangi saldo user di game, tapi DB fail credit
                    // Kirim balik ke Nexus sebagai deposit biar saldo ga hilang
                    @file_put_contents(__DIR__.'/../nexus_log.txt',
                        date('Y-m-d H:i:s')." [LAUNCH_PULL_FAIL_DB] logTx error, refund to nexus: ".$e->getMessage()."\n",FILE_APPEND);
                    nexus('user_deposit',['user_code'=>$username,'amount'=>$existingBal,'agent_sign'=>$pullSign.'_REFUND']);
                    gameUnlock($lock);
                    err('DB error, silakan coba lagi.');
                }
            }else{
                // WITHDRAW GAGAL — STOP, jangan launch. Saldo nyangkut di game tapi saldo lokal aman.
                // User bisa retry atau hubungi admin
                @file_put_contents(__DIR__.'/../nexus_log.txt',
                    date('Y-m-d H:i:s')." [LAUNCH_PULL_FAIL] existing=$existingBal result=".json_encode($wd)."\n",FILE_APPEND);
                gameUnlock($lock);
                err('Ada saldo di game ('.idr($existingBal).') yang belum bisa ditarik. Silakan coba lagi sebentar atau hubungi admin.');
            }
        }

        // ── STEP 2: Lock row user + ambil saldo lokal dalam transaction ──
        $db->beginTransaction();
        try{
            $u=$db->prepare("SELECT balance FROM users WHERE id=? FOR UPDATE");
            $u->execute([$uid]);
            $localBal=intval($u->fetchColumn());

            @file_put_contents(__DIR__.'/../nexus_log.txt',
                date('Y-m-d H:i:s')." [LAUNCH_BAL] local=$localBal (locked) existing_game=$existingBal pullback=$pullBack\n",FILE_APPEND);

            if($localBal>0){
                // STEP 3: Cek agent balance cukup
                $agentInfo=nexus('money_info');
                $agentBal=intval(floatval($agentInfo['agent']['balance']??0));
                if($agentBal<$localBal){
                    $db->rollBack();
                    gameUnlock($lock);
                    err("Sistem sedang tidak dapat memproses. Silakan hubungi admin.");
                }

                // STEP 4: Transfer saldo lokal → Nexus dengan agent_sign
                $depSign=$launchSign.'_DEP';
                $dr=nexus('user_deposit',['user_code'=>$username,'amount'=>$localBal,'agent_sign'=>$depSign]);

                if(($dr['status']??0)!=1){
                    $db->rollBack();
                    gameUnlock($lock);
                    err("Gagal transfer saldo ke game: ".($dr['msg']??'Unknown error'));
                }

                // STEP 4.5: Deduct saldo lokal DULU (sebelum verify) supaya ga bisa di-abuse race
                $db->prepare("UPDATE users SET balance = balance - ? WHERE id=? AND balance >= ?")
                   ->execute([$localBal,$uid,$localBal]);
                // Insert tx log (manual, bukan via logTx supaya di-include dalam transaction ini)
                $db->prepare("INSERT INTO transactions(user_id,type,amount,balance_before,balance_after,note,ref_id) VALUES(?,?,?,?,?,?,?)")
                   ->execute([$uid,'game_transfer',-$localBal,$localBal,0,"Transfer ke game $provider/$gameCode",$depSign]);

                // Update bonus TO progress (bet = turnover kontribusi)
                try{
                    require_once __DIR__.'/../includes/bonus_to.php';
                    bonusTO_update($db,$uid);
                }catch(Exception $e){}

                $db->commit();

                // STEP 5: Verify balance benar-benar masuk
                sleep(1);
                $verifyInfo=nexus('money_info',['user_code'=>$username]);
                $verifiedBal=intval(floatval($verifyInfo['user']['balance']??0));

                @file_put_contents(__DIR__.'/../nexus_log.txt',
                    date('Y-m-d H:i:s')." [LAUNCH_VERIFY] expected=$localBal actual=$verifiedBal\n",FILE_APPEND);

                if($verifiedBal < $localBal){
                    // Retry deposit sekali lagi dengan sign baru
                    $retrySign=$launchSign.'_RETRY';
                    $missing=$localBal-$verifiedBal;
                    $retry=nexus('user_deposit',['user_code'=>$username,'amount'=>$missing,'agent_sign'=>$retrySign]);
                    sleep(1);
                    $reverify=nexus('money_info',['user_code'=>$username]);
                    $reverifiedBal=intval(floatval($reverify['user']['balance']??0));

                    if($reverifiedBal<$localBal){
                        // FATAL: saldo di game ga match sampe sekarang. REFUND ke user.
                        $refundAmount=$localBal-$reverifiedBal;
                        @file_put_contents(__DIR__.'/../nexus_log.txt',
                            date('Y-m-d H:i:s')." [LAUNCH_VERIFY_FAIL] missing=$refundAmount after retry. Refunding to user.\n",FILE_APPEND);

                        // Refund ke user DB (sudah di-deduct tadi)
                        try{
                            $db->beginTransaction();
                            logTx($db,$uid,'game_refund',$refundAmount,'Refund: saldo tidak match di game',$launchSign.'_REF');
                            $db->commit();
                        }catch(Exception $e){try{$db->rollBack();}catch(Exception $ee){}}

                        // Kalau reverifiedBal > 0, itu bagian yg udah sukses — bisa dilanjutkan
                        if($reverifiedBal<=0){
                            gameUnlock($lock);
                            err('Transfer saldo ke game gagal. Saldo telah dikembalikan.');
                        }
                    }
                }
            }else{
                $db->commit(); // no-op tx, just release lock
                @file_put_contents(__DIR__.'/../nexus_log.txt',
                    date('Y-m-d H:i:s')." [LAUNCH_ZERO_BAL] Launching with 0 balance\n",FILE_APPEND);
            }
        }catch(Exception $e){
            try{$db->rollBack();}catch(Exception $ee){}
            gameUnlock($lock);
            err('Transaksi gagal: '.$e->getMessage());
        }

        // STEP 6: Launch game
        $res=nexus('game_launch',['user_code'=>$username,'provider_code'=>$provider,'game_code'=>$gameCode,'lang'=>'en']);
        @file_put_contents(__DIR__.'/../nexus_log.txt',
            date('Y-m-d H:i:s')." [LAUNCH_RESULT] ".json_encode($res)."\n",FILE_APPEND);

        if(($res['status']??0)==1 && !empty($res['launch_url'])){
            gameUnlock($lock);
            ok(['launch_url'=>$res['launch_url'],'transferred'=>$localBal,'game_balance'=>$localBal]);
        }

        // Launch gagal setelah saldo masuk → PULL BACK ke user
        $finalInfo=nexus('money_info',['user_code'=>$username]);
        $gameCur=intval(floatval($finalInfo['user']['balance']??0));
        if($gameCur>0){
            $refundSign=$launchSign.'_LNCH_REF';
            $refund=nexus('user_withdraw',['user_code'=>$username,'amount'=>$gameCur,'agent_sign'=>$refundSign]);
            if(($refund['status']??0)==1){
                try{
                    $db->beginTransaction();
                    logTx($db,$uid,'game_refund',$gameCur,'Refund: game launch gagal',$refundSign);
                    $db->commit();
                }catch(Exception $e){try{$db->rollBack();}catch(Exception $ee){}}
            }
        }
        gameUnlock($lock);
        err('Gagal membuka game: '.($res['msg']??'Unknown error').'. Saldo dikembalikan.');

    }catch(Exception $e){
        gameUnlock($lock);
        err('Launch error: '.$e->getMessage());
    }
}

// ═══ GAME BALANCE ═══
if($action==='game_balance'){
    $uid=auth();
    $u=$db->prepare("SELECT username,balance FROM users WHERE id=?");$u->execute([$uid]);$row=$u->fetch();
    $res=nexus('money_info',['user_code'=>$row['username']]);
    if(($res['status']??0)==1){
        ok([
            'local_balance'=>intval($row['balance']),
            'game_balance'=>intval(floatval($res['user']['balance']??0)),
            'total'=>intval($row['balance'])+intval(floatval($res['user']['balance']??0))
        ]);
    }
    ok(['local_balance'=>intval($row['balance']),'game_balance'=>0,'total'=>intval($row['balance'])]);
}

// ═══════════════════════════════════════════════════════════════════
// ═══ KELUAR GAME / TARIK SALDO — SECURE dengan lock + agent_sign ═══
// ═══════════════════════════════════════════════════════════════════
if($action==='withdraw_game'||$action==='pull_balance'){
    $uid=auth();

    // LOCK: cegah double-click yang bikin double credit
    $lock=gameLock($uid,10);
    if(!$lock)err('Sedang memproses penarikan saldo. Tunggu sebentar.');

    try{
        $u=$db->prepare("SELECT username,balance FROM users WHERE id=?");$u->execute([$uid]);$row=$u->fetch();
        if(!$row){gameUnlock($lock);ok(['amount'=>0,'balance'=>0]);}
        $un=$row['username'];

        // Cek saldo di Nexus
        $info=nexus('money_info',['user_code'=>$un]);
        $gb=intval(floatval($info['user']['balance']??0));

        if($gb<=0){
            gameUnlock($lock);
            ok(['amount'=>0,'balance'=>intval($row['balance'])]);
        }

        // Unique sign utk idempotency
        $pullSign='PULL_'.$uid.'_'.time().'_'.substr(md5(mt_rand()),0,8);
        $res=nexus('user_withdraw',['user_code'=>$un,'amount'=>$gb,'agent_sign'=>$pullSign]);

        if(isset($res['status'])&&$res['status']==1){
            // Success withdraw dari Nexus → credit ke DB dalam transaction
            try{
                $db->beginTransaction();
                logTx($db,$uid,'game_win',$gb,'Saldo kembali dari game',$pullSign);
                $db->commit();
            }catch(Exception $e){
                try{$db->rollBack();}catch(Exception $ee){}
                // Nexus sudah kurangi tapi DB fail → PUT BACK ke Nexus pake sign baru
                @file_put_contents(__DIR__.'/../nexus_log.txt',
                    date('Y-m-d H:i:s')." [PULL_DB_FAIL] uid=$uid amt=$gb re-deposit to nexus: ".$e->getMessage()."\n",FILE_APPEND);
                nexus('user_deposit',['user_code'=>$un,'amount'=>$gb,'agent_sign'=>$pullSign.'_BACK']);
                gameUnlock($lock);
                err('Gagal simpan transaksi. Coba lagi.');
            }

            $newBal=$db->prepare("SELECT balance FROM users WHERE id=?");$newBal->execute([$uid]);
            gameUnlock($lock);
            ok(['amount'=>$gb,'balance'=>intval($newBal->fetchColumn())]);
        }

        // Withdraw gagal → cek status via transfer_status supaya tau apakah bener-bener gagal atau udah sukses
        sleep(1);
        $statusCheck=nexus('transfer_status',['user_code'=>$un,'agent_sign'=>$pullSign]);
        if(($statusCheck['status']??0)==1&&($statusCheck['type']??'')==='user_withdraw'){
            // Ternyata sukses, cuma response awal error. Credit ke DB.
            try{
                $db->beginTransaction();
                logTx($db,$uid,'game_win',$gb,'Saldo kembali dari game (via status check)',$pullSign);
                $db->commit();
                $newBal=$db->prepare("SELECT balance FROM users WHERE id=?");$newBal->execute([$uid]);
                gameUnlock($lock);
                ok(['amount'=>$gb,'balance'=>intval($newBal->fetchColumn())]);
            }catch(Exception $e){try{$db->rollBack();}catch(Exception $ee){}}
        }

        gameUnlock($lock);
        ok(['amount'=>0,'balance'=>intval($row['balance']),'error'=>$res['msg']??'withdraw_failed']);

    }catch(Exception $e){
        gameUnlock($lock);
        err('Error: '.$e->getMessage());
    }
}

// ═══ GAME HISTORY ═══
if($action==='history'){
    $uid=auth();
    $u=$db->prepare("SELECT username FROM users WHERE id=?");$u->execute([$uid]);$un=$u->fetchColumn();
    $gt=$d['game_type']??'slot';
    $start=$d['start']??date('Y-m-d 00:00:00',strtotime('-7 days'));
    $end=$d['end']??date('Y-m-d 23:59:59');
    $res=nexus('get_game_log',['user_code'=>$un,'game_type'=>$gt,'start'=>$start,'end'=>$end,'page'=>intval($d['page']??0),'perPage'=>50]);
    if($res['status']==1)ok(['total'=>$res['total_count']??0,'history'=>$res[$gt]??[]]);
    err($res['msg']??'Failed');
}

// ═══ SYNC TURNOVER ═══
if($action==='sync_turnover'){
    $uid=auth();
    $u=$db->prepare("SELECT username FROM users WHERE id=?");$u->execute([$uid]);$un=$u->fetchColumn();
    $result=syncTurnover($db,$uid,$un);
    ok($result);
}

function syncTurnover($db,$uid,$username){
    $ui=$db->prepare("SELECT created_at,total_turnover FROM users WHERE id=?");$ui->execute([$uid]);
    $row=$ui->fetch();
    $regDate=$row['created_at']??date('Y-m-d H:i:s');
    $start=date('Y-m-d',strtotime($regDate)).' 00:00:00';
    $end=date('Y-m-d 23:59:59');
    $existing=intval($row['total_turnover']??0);
    $totalBet=0;$totalWin=0;$anySuccess=false;
    foreach(['slot','casino'] as $type){
        $page=0;$count=0;
        do{
            $res=nexus('get_game_log',['user_code'=>$username,'game_type'=>$type,'start'=>$start,'end'=>$end,'page'=>$page,'perPage'=>100]);
            if(($res['status']??0)!=1)break;
            $anySuccess=true;
            $logs=$res[$type]??[];
            foreach($logs as $l){
                $txType=$l['txn_type']??'debit_credit';
                if($txType!=='credit')$totalBet+=abs(floatval($l['bet_money']??$l['bet']??0));
                $totalWin+=abs(floatval($l['win_money']??$l['win']??0));
            }
            $count+=count($logs);$page++;
        }while($count<($res['total_count']??0)&&$page<20);
    }
    if(!$anySuccess)return['total_bet'=>$existing,'total_win'=>intval($totalWin),'vip_level'=>0];
    $to=max($existing,intval($totalBet));
    $db->prepare("UPDATE users SET total_turnover=? WHERE id=?")->execute([$to,$uid]);
    $lvResult=updateVipLevel($db,$uid);
    return['total_bet'=>$to,'total_win'=>intval($totalWin),'vip_level'=>$lvResult[1]];
}

// ═══ DEBUG: NEXUS TEST ═══
if($action==='nexus_test'){
    $uid=auth();
    $u=$db->prepare("SELECT username,balance FROM users WHERE id=?");$u->execute([$uid]);$row=$u->fetch();
    $un=$row['username'];$localBal=intval($row['balance']);
    // Probe user existence + read balance via money_info.
    // User baru akan otomatis dibuat di Nexus saat user_deposit pertama.
    $info=nexus('money_info',['user_code'=>$un]);
    $userExists=(($info['status']??0)==1);
    $gameBal=intval(floatval($info['user']['balance']??0));
    $testSign='TEST_'.time();
    $testDep=nexus('user_deposit',['user_code'=>$un,'amount'=>1,'agent_sign'=>$testSign]);
    $testWd=null;
    if(isset($testDep['status'])&&$testDep['status']==1){
        $testWd=nexus('user_withdraw',['user_code'=>$un,'amount'=>1,'agent_sign'=>$testSign.'_WD']);
    }
    ok(['username'=>$un,'local_balance'=>$localBal,'game_balance'=>$gameBal,
        'user_exists_in_nexus'=>$userExists,
        'money_info'=>$info,
        'test_deposit_1rp'=>$testDep,'test_withdraw_1rp'=>$testWd,
        'api_url'=>NEXUS_URL,'agent'=>NEXUS_AGENT]);
}

// ═══ ADMIN: NEXUS STATUS ═══
if($action==='nexus_status'){
    admin();
    $agentInfo=nexus('money_info');
    $testUser='lxdiag_'.substr(md5(time()),0,8);
    // Test user dibuat otomatis via user_deposit pertama.
    $testSign='ADM_TEST_'.time();
    $depRes=nexus('user_deposit',['user_code'=>$testUser,'amount'=>1,'agent_sign'=>$testSign]);
    $wdRes=['status'=>0,'msg'=>'skipped'];
    $createOk=(($depRes['status']??0)==1);
    if($createOk){
        $wdRes=nexus('user_withdraw',['user_code'=>$testUser,'amount'=>1,'agent_sign'=>$testSign.'_WD']);
    }
    $logFile=__DIR__.'/../nexus_log.txt';
    $logLines='';
    if(file_exists($logFile)){
        $lines=file($logFile);
        $logLines=implode('',array_slice($lines,-20));
    }
    ok([
        'agent_balance'=>intval(floatval($agentInfo['agent']['balance']??0)),
        'agent_info'=>$agentInfo,
        'auto_create_via_deposit'=>$createOk,
        'test_deposit_1'=>$depRes,
        'test_withdraw_1'=>$wdRes,
        'api_url'=>NEXUS_URL,
        'agent_code'=>NEXUS_AGENT,
        'last_log'=>$logLines
    ]);
}

err('INVALID_ACTION');
