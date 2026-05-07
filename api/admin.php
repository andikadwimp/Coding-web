<?php
require_once '../includes/config.php';
require_once '../includes/deposit_lib.php';
header('Content-Type: application/json');

// Auth check
if(!getUid()){echo json_encode(['ok'=>false,'error'=>'NOT_LOGGED_IN']);exit;}
$u=$db->prepare("SELECT * FROM users WHERE id=?");$u->execute([getUid()]);$user=$u->fetch();
if(!$user||$user['role']!=='admin'){echo json_encode(['ok'=>false,'error'=>'FORBIDDEN']);exit;}

$d=input();$action=$d['action']??$_GET['action']??'';

// ═══════════════════════════════════════
// SETTINGS
// ═══════════════════════════════════════
if($action==='get_settings'){
    $rows=$db->query("SELECT `key`,`value` FROM settings")->fetchAll();
    $s=[];foreach($rows as $r)$s[$r['key']]=$r['value'];
    echo json_encode(['ok'=>true,'settings'=>$s]);exit;
}

if($action==='save_settings'){
    
    $pairs=$d['settings']??[];
    $st=$db->prepare("INSERT INTO settings(`key`,`value`) VALUES(?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
    foreach($pairs as $k=>$v)$st->execute([$k,$v]);
    echo json_encode(['ok'=>true]);exit;
}

if($action==='save_provider_logo'){
    $code=$d['code']??'';$logo=$d['logo']??'';
    if(!$code)echo json_encode(['ok'=>false,'error'=>'Missing code']);
    else{$db->prepare("UPDATE providers SET logo=? WHERE code=?")->execute([$logo,$code]);
    echo json_encode(['ok'=>true]);}exit;
}

if($action==='delete_setting'){
    
    $key=$d['key']??'';
    if($key){$db->prepare("DELETE FROM settings WHERE `key`=?")->execute([$key]);}
    echo json_encode(['ok'=>true]);exit;
}

// ═══════════════════════════════════════
// IMAGE UPLOAD
// ═══════════════════════════════════════
if($action==='upload'){
    // Auth check
    $tok=$_COOKIE['lx_token']??'';
    if($tok){$chk=$db->prepare("SELECT role FROM users WHERE auth_token=?");$chk->execute([$tok]);$row=$chk->fetch();if(!$row||$row['role']!=='admin'){echo json_encode(['ok'=>false,'error'=>'Forbidden']);exit;}}
    if(!isset($_FILES['file'])){echo json_encode(['ok'=>false,'error'=>'No file']);exit;}
    $f=$_FILES['file'];
    $ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
    $allowed=['jpg','jpeg','png','gif','webp','svg','apk','aab'];
    if(!in_array($ext,$allowed)){echo json_encode(['ok'=>false,'error'=>'Format tidak didukung: '.$ext]);exit;}
    if($f['size']>50*1024*1024){echo json_encode(['ok'=>false,'error'=>'Maksimal 50MB']);exit;}
    // Determine subfolder by type param
    $type=$_POST['type']??'general';
    $allowed_types=['banner','promo','logo','provider','general','apk'];
    if(!in_array($type,$allowed_types))$type='general';
    $dir=realpath(__DIR__.'/../asset').'/uploads/'.$type.'/';
    if(!is_dir($dir))mkdir($dir,0755,true);
    $name=date('Ymd').'_'.time().'_'.rand(100,999).'.'.$ext;
    $dest=$dir.$name;
    if(!move_uploaded_file($f['tmp_name'],$dest)){echo json_encode(['ok'=>false,'error'=>'Upload gagal, cek permission folder asset/uploads/']);exit;}
    $url='/asset/uploads/'.$type.'/'.$name;
    echo json_encode(['ok'=>true,'url'=>$url,'full_url'=>(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https':'http').'://'.$_SERVER['HTTP_HOST'].$url]);exit;
}

// ═══════════════════════════════════════
// UPLOAD ASSET (file hardcoded, fixed path — replace langsung)
// asset/char1.png, char2.png, char3.png, coin.png, cs_avatar.png, icon_penyedia.png,
// bank_icon.png, misteri_banner.jpg, promo/p1.png..p6.jpg
// ═══════════════════════════════════════
if($action==='upload_asset'){
    // Auth check
    $tok=$_COOKIE['lx_token']??'';
    if($tok){$chk=$db->prepare("SELECT role FROM users WHERE auth_token=?");$chk->execute([$tok]);$row=$chk->fetch();if(!$row||$row['role']!=='admin'){echo json_encode(['ok'=>false,'error'=>'Forbidden']);exit;}}
    if(!isset($_FILES['file'])){echo json_encode(['ok'=>false,'error'=>'No file']);exit;}
    $f=$_FILES['file'];
    if($f['size']>20*1024*1024){echo json_encode(['ok'=>false,'error'=>'Maksimal 20MB']);exit;}
    // Target file name (fixed, whitelist strict)
    $target=$_POST['target']??'';
    // Whitelist: map target key → relative path from root
    $assetMap=[
        'char1'=>'asset/char1.png',
        'char2'=>'asset/char2.png',
        'char3'=>'asset/char3.png',
        'coin'=>'asset/coin.png',
        'cs_avatar'=>'asset/cs_avatar.png',
        'icon_penyedia'=>'asset/icon_penyedia.png',
        'bank_icon'=>'asset/bank_icon.png',
        'misteri_banner'=>'asset/misteri_banner.jpg',
        'apresiasi_banner'=>'asset/apresiasi_banner.jpg',
        'bantuan_banner'=>'asset/bantuan_banner.jpg',
        'bonusdepo_banner'=>'asset/bonusdepo_banner.jpg',
        'checkin_banner'=>'asset/checkin_banner.jpg',
        'roulette_banner'=>'asset/roulette_banner.jpg',
        'promo_p1'=>'asset/promo/p1.png',
        'promo_p2'=>'asset/promo/p2.png',
        'promo_p3'=>'asset/promo/p3.png',
        'promo_p4'=>'asset/promo/p4.png',
        'promo_p5'=>'asset/promo/p5.png',
        'promo_p6'=>'asset/promo/p6.jpg',
        'nav_beranda'=>'img/nav_beranda.png',
        'nav_beranda_on'=>'img/nav_beranda_on.png',
        'nav_promosi'=>'img/nav_promosi.png',
        'nav_promosi_on'=>'img/nav_promosi_on.png',
        'nav_undang'=>'img/nav_undang.png',
        'nav_undang_on'=>'img/nav_undang_on.png',
        'nav_deposit'=>'img/nav_deposit.png',
        'nav_deposit_on'=>'img/nav_deposit_on.png',
        'nav_profil'=>'img/nav_profil.png',
        'nav_profil_on'=>'img/nav_profil_on.png',
    ];
    if(!isset($assetMap[$target])){echo json_encode(['ok'=>false,'error'=>'Target tidak valid: '.$target]);exit;}
    $relPath=$assetMap[$target];
    // Enforce file extension match (bisa .jpg atau .png)
    $expectedExt=strtolower(pathinfo($relPath,PATHINFO_EXTENSION));
    $uploadExt=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
    $allowedExt=['jpg','jpeg','png','gif','webp'];
    if(!in_array($uploadExt,$allowedExt)){echo json_encode(['ok'=>false,'error'=>'Format tidak didukung']);exit;}
    // Path absolut
    $absPath=realpath(__DIR__.'/..').'/'.$relPath;
    $absDir=dirname($absPath);
    if(!is_dir($absDir))mkdir($absDir,0755,true);
    // Backup file lama kalau ada
    if(file_exists($absPath))@copy($absPath,$absPath.'.bak');
    // Move uploaded
    if(!move_uploaded_file($f['tmp_name'],$absPath)){echo json_encode(['ok'=>false,'error'=>'Upload gagal, cek permission folder']);exit;}
    @chmod($absPath,0644);
    // Return URL + cache buster
    echo json_encode(['ok'=>true,'url'=>$relPath,'target'=>$target,'cache_buster'=>time()]);exit;
}


// ═══════════════════════════════════════
// BANNERS
// ═══════════════════════════════════════
if($action==='get_banners'){
    $rows=$db->query("SELECT * FROM banners ORDER BY sort_order ASC, id DESC")->fetchAll();
    echo json_encode(['ok'=>true,'banners'=>$rows]);exit;
}

if($action==='save_banner'){
    try{
        $db->exec("CREATE TABLE IF NOT EXISTS banners(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,title VARCHAR(200) DEFAULT '',subtitle VARCHAR(200) DEFAULT '',description TEXT,image_url VARCHAR(500),link VARCHAR(500),button_text VARCHAR(50) DEFAULT 'Proses',category VARCHAR(30) DEFAULT 'banner',sort_order INT DEFAULT 0,status VARCHAR(10) DEFAULT 'active',created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
        try{$db->exec("ALTER TABLE banners ADD COLUMN description TEXT AFTER subtitle");}catch(Exception $e){}
        try{$db->exec("ALTER TABLE banners ADD COLUMN button_text VARCHAR(50) DEFAULT 'Proses' AFTER link");}catch(Exception $e){}
        try{$db->exec("ALTER TABLE banners ADD COLUMN category VARCHAR(30) DEFAULT 'banner' AFTER button_text");}catch(Exception $e){}
        $id=$d['id']??null;
        $imgUrl=trim($d['image_url']??'');
        if(!$imgUrl){echo json_encode(['ok'=>false,'error'=>'URL gambar kosong']);exit;}
        if($id){
            $st=$db->prepare("UPDATE banners SET title=?,description=?,image_url=?,link=?,button_text=?,category=?,sort_order=?,status=? WHERE id=?");
            $st->execute([$d['title']??'',$d['description']??'',$imgUrl,$d['link']??'',$d['button_text']??'Proses',$d['category']??'banner',$d['sort_order']??0,$d['status']??'active',intval($id)]);
        }else{
            $st=$db->prepare("INSERT INTO banners(title,description,image_url,link,button_text,category,sort_order,status) VALUES(?,?,?,?,?,?,?,?)");
            $st->execute([$d['title']??'',$d['description']??'',$imgUrl,$d['link']??'',$d['button_text']??'Proses',$d['category']??'banner',$d['sort_order']??0,$d['status']??'active']);
            $id=$db->lastInsertId();
        }
        echo json_encode(['ok'=>true,'id'=>$id]);
    }catch(Exception $e){
        echo json_encode(['ok'=>false,'error'=>'DB error: '.$e->getMessage()]);
    }
    exit;
}

if($action==='delete_banner'){
    
    $db->prepare("DELETE FROM banners WHERE id=?")->execute([$d['id']??0]);
    echo json_encode(['ok'=>true]);exit;
}

// ═══════════════════════════════════════
// PROMOS (Cards)
// ═══════════════════════════════════════
if($action==='get_promos'){
    $rows=$db->query("SELECT * FROM promos ORDER BY created_at DESC")->fetchAll();
    echo json_encode(['ok'=>true,'promos'=>$rows]);exit;
}

if($action==='save_promo'){
    
    $id=$d['id']??null;
    // Auto-add columns
    try{$db->exec("ALTER TABLE promos ADD COLUMN category VARCHAR(30) DEFAULT 'promosi' AFTER status");}catch(Exception $e){}
    try{$db->exec("ALTER TABLE promos ADD COLUMN link VARCHAR(500) DEFAULT '#' AFTER image_url");}catch(Exception $e){}
    try{$db->exec("ALTER TABLE promos ADD COLUMN button_text VARCHAR(50) DEFAULT 'Proses' AFTER link");}catch(Exception $e){}
    try{$db->exec("ALTER TABLE promos ADD COLUMN description TEXT AFTER title");}catch(Exception $e){}
    if($id){
        $st=$db->prepare("UPDATE promos SET title=?,description=?,image_url=?,link=?,button_text=?,category=?,status=? WHERE id=?");
        $st->execute([$d['title']??'Promo',$d['description']??'',$d['image_url']??'',$d['link']??'#',$d['button_text']??'Proses',$d['category']??'promosi',$d['status']??'active',$id]);
    }else{
        $st=$db->prepare("INSERT INTO promos(title,description,image_url,link,button_text,category,status) VALUES(?,?,?,?,?,?,?)");
        $st->execute([$d['title']??'Promo',$d['description']??'',$d['image_url']??'',$d['link']??'#',$d['button_text']??'Proses',$d['category']??'promosi',$d['status']??'active']);
        $id=$db->lastInsertId();
    }
    echo json_encode(['ok'=>true,'id'=>$id]);exit;
}

if($action==='delete_promo'){
    
    $db->prepare("DELETE FROM promos WHERE id=?")->execute([$d['id']??0]);
    echo json_encode(['ok'=>true]);exit;
}

// ═══════════════════════════════════════
// USERS
// ═══════════════════════════════════════
if($action==='get_users'){
    try{$rows=$db->query("SELECT id,username,phone,balance,role,vip_level,ref_code,display_id,created_at FROM users ORDER BY id DESC")->fetchAll();}
    catch(Exception $e){$rows=$db->query("SELECT id,username,phone,balance,role,created_at FROM users ORDER BY id DESC")->fetchAll();}
    echo json_encode(['ok'=>true,'users'=>$rows]);exit;
}

if($action==='update_user'){
    $id=$d['id']??0;
    $fields=[];$vals=[];
    foreach(['role','vip_level','balance'] as $f){
        if(isset($d[$f])){$fields[]="`$f`=?";$vals[]=$d[$f];}
    }
    if($fields&&$id){$vals[]=$id;$db->prepare("UPDATE users SET ".implode(',',$fields)." WHERE id=?")->execute($vals);}
    echo json_encode(['ok'=>true]);exit;
}

// ═══════════════════════════════════════
// DASHBOARD STATS
// ═══════════════════════════════════════
if($action==='stats'){
    $s=[];
    $s['users']=$db->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
    $s['balance']=$db->query("SELECT COALESCE(SUM(balance),0) FROM users")->fetchColumn();
    try{$s['deposits']=$db->query("SELECT COUNT(*) FROM deposits WHERE status='paid'")->fetchColumn();}catch(Exception $e){$s['deposits']=0;}
    try{$s['withdrawals']=$db->query("SELECT COUNT(*) FROM withdrawals WHERE status='pending'")->fetchColumn();}catch(Exception $e){$s['withdrawals']=0;}
    $s['banners']=$db->query("SELECT COUNT(*) FROM banners WHERE status='active'")->fetchColumn();
    $s['promos']=$db->query("SELECT COUNT(*) FROM promos WHERE status='active'")->fetchColumn();
    $s['providers']=$db->query("SELECT COUNT(*) FROM providers WHERE status=1")->fetchColumn();
    $s['games']=$db->query("SELECT COUNT(*) FROM games WHERE status=1")->fetchColumn();
    echo json_encode(['ok'=>true,'stats'=>$s]);exit;
}


// ═══ NOTIFICATIONS (Pemberitahuan) ═══
if($action==='get_notifs'){
    $rows=$db->query("SELECT * FROM memos WHERE type IN('all','notif') ORDER BY created_at DESC LIMIT 100")->fetchAll();
    echo json_encode(['ok'=>true,'notifs'=>$rows]);exit;
}
if($action==='save_notif'){
    
    $id=$d['id']??null;
    $title=$d['title']??'';
    $body=$d['body']??'';
    $isNew=!$id;
    if($id){
        $db->prepare("UPDATE memos SET title=?,body=? WHERE id=?")->execute([$title,$body,$id]);
    }else{
        $db->prepare("INSERT INTO memos(type,title,body) VALUES('all',?,?)")->execute([$title,$body]);
        $id=$db->lastInsertId();
    }

    // ═══ PUSH NOTIFICATION ke SEMUA user (kalau notif baru) ═══
    if($isNew&&$title){
        try{
            require_once __DIR__.'/../includes/webpush.php';
            $vapid=webpush_get_vapid($db);
            if($vapid){
                $vapid['subject']='mailto:admin@'.($_SERVER['HTTP_HOST']??'example.com');
                $subs=$db->query("SELECT * FROM push_subscriptions")->fetchAll();
                $payload=json_encode(['title'=>'📢 '.$title,'body'=>mb_substr($body,0,120),'url'=>'/notifs.php','icon'=>'/icon-192.png']);
                $removeIds=[];
                foreach($subs as $s){
                    $r=webpush_send(['endpoint'=>$s['endpoint'],'p256dh'=>$s['p256dh'],'auth'=>$s['auth']],$payload,$vapid);
                    if(!$r['ok']&&(($r['status']??0)===404||($r['status']??0)===410))$removeIds[]=$s['id'];
                }
                if($removeIds)$db->query("DELETE FROM push_subscriptions WHERE id IN (".implode(',',array_map('intval',$removeIds)).")");
            }
        }catch(Exception $e){}
    }

    echo json_encode(['ok'=>true,'id'=>$id]);exit;
}
if($action==='delete_notif'){
    
    $db->prepare("DELETE FROM memos WHERE id=?")->execute([$d['id']??0]);
    echo json_encode(['ok'=>true]);exit;
}

// ═══ BLOG ═══
if($action==='get_blog_posts'){
    $rows=$db->query("SELECT * FROM memos WHERE type='blog' ORDER BY created_at DESC LIMIT 100")->fetchAll();
    echo json_encode(['ok'=>true,'blogs'=>$rows]);exit;
}
if($action==='save_blog'){
    
    $id=$d['id']??null;
    $title=$d['title']??'';
    $body=$d['body']??'';
    $isNew=!$id;
    if($id){
        $db->prepare("UPDATE memos SET title=?,body=? WHERE id=?")->execute([$title,$body,$id]);
    }else{
        $db->prepare("INSERT INTO memos(type,title,body) VALUES('blog',?,?)")->execute([$title,$body]);
        $id=$db->lastInsertId();
    }

    // ═══ PUSH + TELEGRAM ke SEMUA user (kalau blog baru) ═══
    if($isNew&&$title){
        // Push ke user app
        try{
            require_once __DIR__.'/../includes/webpush.php';
            $vapid=webpush_get_vapid($db);
            if($vapid){
                $vapid['subject']='mailto:admin@'.($_SERVER['HTTP_HOST']??'example.com');
                $subs=$db->query("SELECT * FROM push_subscriptions")->fetchAll();
                $preview=strip_tags($body);
                $payload=json_encode(['title'=>'📰 '.$title,'body'=>mb_substr($preview,0,120),'url'=>'/notifs.php','icon'=>'/icon-192.png']);
                $removeIds=[];
                foreach($subs as $s){
                    $r=webpush_send(['endpoint'=>$s['endpoint'],'p256dh'=>$s['p256dh'],'auth'=>$s['auth']],$payload,$vapid);
                    if(!$r['ok']&&(($r['status']??0)===404||($r['status']??0)===410))$removeIds[]=$s['id'];
                }
                if($removeIds)$db->query("DELETE FROM push_subscriptions WHERE id IN (".implode(',',array_map('intval',$removeIds)).")");
            }
        }catch(Exception $e){}

        // [REMOVED] Telegram auto-post — sesuai permintaan, telegram cuma untuk live chat
    }

    echo json_encode(['ok'=>true,'id'=>$id]);exit;
}
if($action==='delete_blog'){
    
    $db->prepare("DELETE FROM memos WHERE id=?")->execute([$d['id']??0]);
    echo json_encode(['ok'=>true]);exit;
}

// ═══ THEME COLORS (DIHAPUS — warna hardcoded di theme.php) ═══
if($action==='get_theme'||$action==='save_theme'){
    echo json_encode(['ok'=>false,'error'=>'Theme sekarang hardcoded. Edit warna di file theme.php langsung.']);exit;
}

// ═══ REDEEM CODES ═══
if($action==='get_redeems'){
    $rows=$db->query("SELECT * FROM redeem_codes ORDER BY created_at DESC LIMIT 100")->fetchAll();
    echo json_encode(['ok'=>true,'codes'=>$rows]);exit;
}
if($action==='save_redeem'){
    
    $id=$d['id']??null;
    if($id){
        $db->prepare("UPDATE redeem_codes SET code=?,amount=?,max_uses=?,status=?,expires_at=? WHERE id=?")->execute([$d['code']??'',$d['amount']??0,$d['max_uses']??0,$d['status']??'active',$d['expires_at']??null,$id]);
    }else{
        $db->prepare("INSERT INTO redeem_codes(code,amount,max_uses,status,expires_at) VALUES(?,?,?,?,?)")->execute([$d['code']??'',$d['amount']??0,$d['max_uses']??0,$d['status']??'active',$d['expires_at']??null]);
        $id=$db->lastInsertId();
    }
    echo json_encode(['ok'=>true,'id'=>$id]);exit;
}
if($action==='delete_redeem'){
    
    $db->prepare("DELETE FROM redeem_codes WHERE id=?")->execute([$d['id']??0]);
    echo json_encode(['ok'=>true]);exit;
}

// ═══ BONUSES ═══
if($action==='get_bonuses'){
    $rows=$db->query("SELECT * FROM bonuses ORDER BY id")->fetchAll();
    echo json_encode(['ok'=>true,'bonuses'=>$rows]);exit;
}
if($action==='save_bonus'){
    
    $id=$d['id']??null;
    if($id){
        $db->prepare("UPDATE bonuses SET name=?,percentage=?,max_amount=?,turnover_x=?,min_deposit=?,status=? WHERE id=?")->execute([$d['name']??'',$d['percentage']??0,$d['max_amount']??0,$d['turnover_x']??1,$d['min_deposit']??0,$d['status']??'active',$id]);
    }else{
        $db->prepare("INSERT INTO bonuses(name,percentage,max_amount,turnover_x,min_deposit,status) VALUES(?,?,?,?,?,?)")->execute([$d['name']??'',$d['percentage']??0,$d['max_amount']??0,$d['turnover_x']??1,$d['min_deposit']??0,$d['status']??'active']);
        $id=$db->lastInsertId();
    }
    echo json_encode(['ok'=>true,'id'=>$id]);exit;
}
if($action==='delete_bonus'){
    
    $db->prepare("DELETE FROM bonuses WHERE id=?")->execute([$d['id']??0]);
    echo json_encode(['ok'=>true]);exit;
}

// ═══ DEPOSITS ═══
if($action==='get_deposits'){
    // Auto-expire stale pending (lewat 30 menit dari expires_at) setiap admin buka halaman
    try{
        $db->exec("UPDATE deposits SET status='expired'
                   WHERE status='pending' AND expires_at IS NOT NULL
                     AND expires_at < NOW() - INTERVAL 24 HOUR");
    }catch(Exception $e){}
    $search=trim($d['search']??$_GET['search']??'');
    $uidFilter=intval($d['uid']??$_GET['uid']??0);
    try{
        if($uidFilter>0){
            // EXACT match by user_id — dari tombol Riwayat di users.php
            $stm=$db->prepare("SELECT d.*,u.username,u.phone,u.display_id
                               FROM deposits d LEFT JOIN users u ON d.user_id=u.id
                               WHERE d.user_id=? ORDER BY d.created_at DESC LIMIT 500");
            $stm->execute([$uidFilter]);
            $rows=$stm->fetchAll();
        }elseif($search!==''){
            // Search LIKE — untuk keyword manual di search box
            $sq='%'.$search.'%';
            $stm=$db->prepare("SELECT d.*,u.username,u.phone,u.display_id
                               FROM deposits d LEFT JOIN users u ON d.user_id=u.id
                               WHERE d.tx_id LIKE ? OR u.username LIKE ? OR u.phone LIKE ?
                                  OR u.display_id LIKE ? OR d.nominal LIKE ?
                               ORDER BY d.created_at DESC LIMIT 500");
            $stm->execute([$sq,$sq,$sq,$sq,$sq]);
            $rows=$stm->fetchAll();
        }else{
            $rows=$db->query("SELECT d.*,u.username,u.phone,u.display_id FROM deposits d LEFT JOIN users u ON d.user_id=u.id ORDER BY d.created_at DESC LIMIT 100")->fetchAll();
        }
    }catch(Exception $e){$rows=[];}
    echo json_encode(['ok'=>true,'deposits'=>$rows]);exit;
}
if($action==='approve_deposit'){
    // Force approve — work untuk pending DAN expired (admin override)
    $id=$d['id']??0;
    $dep=$db->prepare("SELECT * FROM deposits WHERE id=? AND status IN ('pending','expired','failed','cancelled')");
    $dep->execute([$id]);$dep=$dep->fetch();
    if(!$dep){echo json_encode(['ok'=>false,'error'=>'Not found atau sudah paid']);exit;}
    $wasStatus=$dep['status'];
    // Credit via shared lib — pake lock table, anti-race vs callback (double safety)
    $ok=creditDeposit($db,$dep,null,'admin_force_'.$wasStatus);
    if(!$ok){echo json_encode(['ok'=>false,'error'=>'Gagal credit — cek deposit_log.txt']);exit;}
    echo json_encode(['ok'=>true,'from_status'=>$wasStatus]);exit;
}
if($action==='force_expire_deposit'){
    // Admin paksa set status=expired untuk deposit pending
    $id=$d['id']??0;
    $dep=$db->prepare("SELECT * FROM deposits WHERE id=? AND status='pending'");
    $dep->execute([$id]);$dep=$dep->fetch();
    if(!$dep){echo json_encode(['ok'=>false,'error'=>'Not found atau bukan pending']);exit;}
    $db->prepare("UPDATE deposits SET status='expired' WHERE id=?")->execute([$id]);
    echo json_encode(['ok'=>true]);exit;
}
if($action==='revert_deposit'){
    // Admin revert deposit expired/cancelled/failed → balik ke pending (biar user bisa bayar lagi)
    // Perpanjang expires_at 12 jam dari sekarang
    $id=$d['id']??0;
    $dep=$db->prepare("SELECT * FROM deposits WHERE id=? AND status IN ('expired','failed','cancelled')");
    $dep->execute([$id]);$dep=$dep->fetch();
    if(!$dep){echo json_encode(['ok'=>false,'error'=>'Not found atau status tidak bisa di-revert']);exit;}
    $newExpires=date('Y-m-d H:i:s',strtotime('+12 hours'));
    $db->prepare("UPDATE deposits SET status='pending',expires_at=? WHERE id=?")->execute([$newExpires,$id]);
    echo json_encode(['ok'=>true,'new_expires_at'=>$newExpires]);exit;
}
if($action==='reject_deposit'){
    // Admin mark pending → failed (gagal, ga bakal di-credit)
    $id=$d['id']??0;$note=trim($d['note']??'Ditolak admin');
    $dep=$db->prepare("SELECT * FROM deposits WHERE id=? AND status IN ('pending','expired')");
    $dep->execute([$id]);$dep=$dep->fetch();
    if(!$dep){echo json_encode(['ok'=>false,'error'=>'Not found']);exit;}
    $db->prepare("UPDATE deposits SET status='failed' WHERE id=?")->execute([$id]);
    // Kirim notif ke user via memos
    try{
        $db->prepare("INSERT INTO memos(type,to_user_id,title,body) VALUES('target',?,?,?)")
           ->execute([$dep['user_id'],'Deposit Ditolak','Deposit #'.$id.' ditolak oleh admin.<br>Alasan: '.htmlspecialchars($note)]);
    }catch(Exception $e){}
    echo json_encode(['ok'=>true]);exit;
}

// ═══ WITHDRAWALS ═══
if($action==='get_withdrawals'){
    try{$rows=$db->query("SELECT w.*,u.username,u.phone FROM withdrawals w LEFT JOIN users u ON w.user_id=u.id ORDER BY w.created_at DESC LIMIT 100")->fetchAll();}
    catch(Exception $e){$rows=[];}
    echo json_encode(['ok'=>true,'withdrawals'=>$rows]);exit;
}
if($action==='process_withdraw'){
    $id=$d['id']??0;$status=$d['status']??'';$note=$d['note']??'';
    if(!in_array($status,['approved','rejected'])){echo json_encode(['ok'=>false,'error'=>'Invalid status']);exit;}
    $wd=$db->prepare("SELECT * FROM withdrawals WHERE id=? AND status='pending'");$wd->execute([$id]);$wd=$wd->fetch();
    if(!$wd){echo json_encode(['ok'=>false,'error'=>'Not found']);exit;}
    if($status==='rejected'){
        // Refund balance
        logTx($db,$wd['user_id'],'refund',$wd['amount'],'Penarikan ditolak: '.$note);
    }
    $db->prepare("UPDATE withdrawals SET status=?,processed_at=NOW(),admin_note=? WHERE id=?")->execute([$status,$note,$id]);
    autoMemo($db,$wd['user_id'],$status==='approved'?'Penarikan Disetujui':'Penarikan Ditolak',$status==='approved'?'Penarikan Rp '.number_format($wd['amount'],0,',','.').' telah diproses.':'Penarikan Rp '.number_format($wd['amount'],0,',','.').' ditolak. '.$note);

    // ═══ PUSH NOTIFICATION ═══
    try{
        require_once __DIR__.'/../includes/webpush.php';
        $amtFmt=number_format($wd['amount'],0,',','.');
        if($status==='approved'){
            pushNotify($db,$wd['user_id'],'✅ Penarikan Disetujui','Penarikan Rp '.$amtFmt.' sedang diproses ke rekening kamu.','/withdraw.php');
        }else{
            pushNotify($db,$wd['user_id'],'❌ Penarikan Ditolak','Penarikan Rp '.$amtFmt.' ditolak. '.($note?:'Cek detail di aplikasi.'),'/withdraw.php');
        }
    }catch(Exception $e){}

    // ═══ UPDATE TELEGRAM MESSAGE (hapus tombol + tampilkan hasil) ═══
    try{
        require_once __DIR__.'/../includes/tg_wd.php';
        tgWdMarkResolved($db,$id,$status,$note,false);
    }catch(Exception $e){}

    echo json_encode(['ok'=>true]);exit;
}

// ═══ GAMES / PROVIDERS ═══
if($action==='get_providers'){
    try{$rows=$db->query("SELECT * FROM providers ORDER BY sort_order,name")->fetchAll();}catch(Exception $e){$rows=[];}
    echo json_encode(['ok'=>true,'providers'=>$rows]);exit;
}
if($action==='update_provider'){
    $code=$d['code']??'';
    $db->prepare("UPDATE providers SET logo=?,sort_order=?,status=? WHERE code=?")->execute([$d['logo']??'',$d['sort_order']??0,$d['status']??1,$code]);
    echo json_encode(['ok'=>true]);exit;
}
if($action==='sync_games'){
    try{
        if(!NEXUS_URL){echo json_encode(['ok'=>false,'error'=>'NEXUS_URL belum diset']);exit;}
        // Ensure tables exist
        $db->exec("CREATE TABLE IF NOT EXISTS providers(code VARCHAR(50) PRIMARY KEY,name VARCHAR(100),logo VARCHAR(500) DEFAULT '',status INT DEFAULT 1,sort_order INT DEFAULT 0,game_count INT DEFAULT 0,updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
        $db->exec("CREATE TABLE IF NOT EXISTS games(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,provider_code VARCHAR(50),game_code VARCHAR(100),game_name VARCHAR(200),game_type VARCHAR(20) DEFAULT 'slot',banner VARCHAR(500) DEFAULT '',status INT DEFAULT 1,updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,UNIQUE KEY uq_game(provider_code,game_code)) ENGINE=InnoDB");
        
        $ch=curl_init(NEXUS_URL);
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['method'=>'provider_list','agent_code'=>NEXUS_AGENT,'agent_token'=>NEXUS_TOKEN]),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30]);
        $raw=curl_exec($ch);$err=curl_error($ch);curl_close($ch);
        if(!$raw){echo json_encode(['ok'=>false,'error'=>'Tidak merespon: '.$err]);exit;}
        $res=json_decode($raw,true);
        if(!$res){echo json_encode(['ok'=>false,'error'=>'Bukan JSON: '.substr($raw,0,200)]);exit;}
        if(($res['status']??'')!='1'&&($res['status']??0)!=1){echo json_encode(['ok'=>false,'error'=>$res['message']??$res['msg']??json_encode($res)]);exit;}
        
        $totalP=0;$totalG=0;
        foreach($res['providers'] as $p){
            $db->prepare("INSERT INTO providers(code,name) VALUES(?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)")->execute([$p['code'],$p['name']]);
            $totalP++;
            // Fetch games
            $ch2=curl_init(NEXUS_URL);
            curl_setopt_array($ch2,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['method'=>'game_list','agent_code'=>NEXUS_AGENT,'agent_token'=>NEXUS_TOKEN,'provider_code'=>$p['code']]),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30]);
            $raw2=curl_exec($ch2);curl_close($ch2);
            $gr=json_decode($raw2,true);
            if($gr&&($gr['status']==1||$gr['status']==='1')&&!empty($gr['games'])){
                $gc=0;
                foreach($gr['games'] as $g){
                    $name=is_array($g['game_name']??'')?($g['game_name']['en']??array_values($g['game_name'])[0]??''):($g['game_name']??'');
                    try{$db->prepare("INSERT INTO games(provider_code,game_code,game_name,banner) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE game_name=VALUES(game_name),banner=VALUES(banner)")->execute([$p['code'],$g['game_code'],$name,$g['banner']??'']);$gc++;}catch(Exception $e2){}
                    $totalG++;
                }
                try{$db->prepare("UPDATE providers SET game_count=? WHERE code=?")->execute([$gc,$p['code']]);}catch(Exception $e2){}
            }
            usleep(100000);
        }
        echo json_encode(['ok'=>true,'providers'=>$totalP,'games'=>$totalG]);exit;
    }catch(Exception $e){
        echo json_encode(['ok'=>false,'error'=>'PHP Error: '.$e->getMessage()]);exit;
    }
}

// ═══ USER DETAIL — riwayat lengkap dengan summary ═══
if($action==='user_detail'){
    $uid=intval($d['user_id']??0);
    $filter=$d['filter']??'all';
    if(!$uid){echo json_encode(['ok'=>false,'error'=>'user_id wajib']);exit;}
    // Summary
    $summary=['total_deposit'=>0,'deposit_count'=>0,'total_withdraw'=>0,'withdraw_count'=>0];
    try{
        $sd=$db->prepare("SELECT COALESCE(SUM(amount),0) AS s,COUNT(*) AS c FROM transactions WHERE user_id=? AND type='deposit'");
        $sd->execute([$uid]);$r=$sd->fetch();$summary['total_deposit']=intval($r['s']);$summary['deposit_count']=intval($r['c']);
    }catch(Exception $e){}
    try{
        $sw=$db->prepare("SELECT COALESCE(SUM(ABS(amount)),0) AS s,COUNT(*) AS c FROM transactions WHERE user_id=? AND type='withdraw'");
        $sw->execute([$uid]);$r=$sw->fetch();$summary['total_withdraw']=intval($r['s']);$summary['withdraw_count']=intval($r['c']);
    }catch(Exception $e){}
    // Filter transactions
    $whereExtra='';
    if($filter==='deposit')$whereExtra=" AND type='deposit'";
    elseif($filter==='withdraw')$whereExtra=" AND type='withdraw'";
    elseif($filter==='bonus')$whereExtra=" AND type IN('bonus','referral','cashback','inject')";
    $tx=[];
    try{
        $st=$db->prepare("SELECT type,amount,balance_before,balance_after,ref_id,note,created_at FROM transactions WHERE user_id=? $whereExtra ORDER BY id DESC LIMIT 100");
        $st->execute([$uid]);$tx=$st->fetchAll();
    }catch(Exception $e){}
    // User basic info
    $user=null;
    try{
        $us=$db->prepare("SELECT id,username,phone,display_id,balance,total_deposit,ref_code,referred_by,created_at,last_login,role FROM users WHERE id=?");
        $us->execute([$uid]);$user=$us->fetch()?:null;
    }catch(Exception $e){}
    echo json_encode(['ok'=>true,'user'=>$user,'summary'=>$summary,'transactions'=>$tx]);exit;
}

// ═══ INJECT BALANCE ═══
if($action==='inject_balance'){
    $userId=$d['user_id']??0;$amount=intval($d['amount']??0);$note=$d['note']??'Admin inject';
    if(!$userId||!$amount){echo json_encode(['ok'=>false,'error'=>'Invalid']);exit;}
    // Validate user exist dulu
    $chk=$db->prepare("SELECT id FROM users WHERE id=?");$chk->execute([$userId]);
    if(!$chk->fetch()){echo json_encode(['ok'=>false,'error'=>'User tidak ditemukan']);exit;}
    $r=logTx($db,$userId,'inject',$amount,$note);
    if($r===false){echo json_encode(['ok'=>false,'error'=>'Gagal update saldo']);exit;}
    echo json_encode(['ok'=>true,'new_balance'=>$r]);exit;
}

// ═══ TEST NEXUS CONNECTION ═══
if($action==='test_nexus'){
    if(!NEXUS_URL){echo json_encode(['ok'=>false,'error'=>'NEXUS_URL kosong']);exit;}
    $ch=curl_init(NEXUS_URL);
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['method'=>'provider_list','agent_code'=>NEXUS_AGENT,'agent_token'=>NEXUS_TOKEN]),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>15]);
    $raw=curl_exec($ch);$err=curl_error($ch);curl_close($ch);
    if(!$raw){echo json_encode(['ok'=>false,'error'=>'Gagal konek: '.$err]);exit;}
    $res=json_decode($raw,true);
    if(!$res){echo json_encode(['ok'=>false,'error'=>'Response bukan JSON: '.substr($raw,0,300)]);exit;}
    if(($res['status']??0)==1||$res['status']==='1'){
        echo json_encode(['ok'=>true,'msg'=>'Koneksi OK! '.count($res['providers']??[]).' providers']);
    }else{
        echo json_encode(['ok'=>false,'error'=>$res['message']??$res['msg']??json_encode($res)]);
    }
    exit;
}

// ═══ SEARCH GAMES ═══
if($action==='search_games'){
    $q=trim($d['q']??'');
    if(strlen($q)<2){echo json_encode(['ok'=>true,'games'=>[]]);exit;}
    try{$db->exec("ALTER TABLE games ADD COLUMN featured TINYINT DEFAULT 0 AFTER status");}catch(Exception $e){}
    try{$db->exec("ALTER TABLE games ADD COLUMN sort_order INT DEFAULT 0 AFTER featured");}catch(Exception $e){}
    $st=$db->prepare("SELECT id,provider_code,game_code,game_name,banner,featured FROM games WHERE game_name LIKE ? AND status=1 ORDER BY featured DESC,game_name LIMIT 20");
    $st->execute(['%'.$q.'%']);
    echo json_encode(['ok'=>true,'games'=>$st->fetchAll()]);exit;
}

// ══════ PUSH BROADCAST ══════
if($action==='push_broadcast'){
    require_once __DIR__.'/../includes/webpush.php';
    $title=trim($d['title']??'');$body=trim($d['body']??'');$url=trim($d['url']??'/dashboard.php');
    if(!$title)echo json_encode(['ok'=>false,'error'=>'Title required']);
    if(!$title){exit;}
    $vapid=webpush_get_vapid($db);
    if(!$vapid){echo json_encode(['ok'=>false,'error'=>'VAPID not configured']);exit;}
    $vapid['subject']='mailto:admin@'.($_SERVER['HTTP_HOST']??'example.com');
    try{$subs=$db->query("SELECT * FROM push_subscriptions")->fetchAll();}catch(Exception $e){$subs=[];}
    $payload=json_encode(['title'=>$title,'body'=>$body,'url'=>$url,'icon'=>'/icon-192.png']);
    $sent=0;$failed=0;$removeIds=[];
    foreach($subs as $s){
        $r=webpush_send(['endpoint'=>$s['endpoint'],'p256dh'=>$s['p256dh'],'auth'=>$s['auth']],$payload,$vapid);
        if($r['ok'])$sent++;
        else{$failed++;if(($r['status']??0)===404||($r['status']??0)===410)$removeIds[]=$s['id'];}
    }
    if($removeIds){$db->query("DELETE FROM push_subscriptions WHERE id IN (".implode(',',array_map('intval',$removeIds)).")");}
    echo json_encode(['ok'=>true,'sent'=>$sent,'failed'=>$failed,'total'=>count($subs)]);exit;
}

if($action==='vapid_status'){
    require_once __DIR__.'/../includes/webpush.php';
    $v=webpush_get_vapid($db);
    try{$cnt=(int)$db->query("SELECT COUNT(*) FROM push_subscriptions")->fetchColumn();}catch(Exception $e){$cnt=0;}
    echo json_encode(['ok'=>true,'has_vapid'=>!!($v&&$v['public']),'public_key'=>$v['public']??'','subscribers'=>$cnt]);exit;
}

if($action==='ref_detail'){
    $uid=(int)($d['user_id']??0);
    if(!$uid){echo json_encode(['ok'=>false,'error'=>'Missing user_id']);exit;}
    try{
        $u=$db->prepare("SELECT ref_code,username,phone FROM users WHERE id=?");$u->execute([$uid]);
        $usr=$u->fetch();
        if(!$usr){echo json_encode(['ok'=>false,'error'=>'User not found']);exit;}
        $rcRaw=(string)($usr['ref_code']??'');  // JANGAN trim — biar match persis sama top query
        $rcTrim=trim($rcRaw);

        $list=[];
        $matchBy='';
        // Try 1: exact match (sama dgn top query)
        if($rcRaw!==''){
            $dl=$db->prepare("SELECT id,username,phone,created_at,total_deposit,last_login FROM users WHERE referred_by=? ORDER BY total_deposit DESC,created_at DESC LIMIT 500");
            $dl->execute([$rcRaw]);
            $list=$dl->fetchAll();
            if(count($list)>0)$matchBy='exact';
        }
        // Try 2: trimmed match (kalo DB punya trailing space)
        if(count($list)===0 && $rcTrim!==''){
            $dl=$db->prepare("SELECT id,username,phone,created_at,total_deposit,last_login FROM users WHERE TRIM(referred_by)=? ORDER BY total_deposit DESC,created_at DESC LIMIT 500");
            $dl->execute([$rcTrim]);
            $list=$dl->fetchAll();
            if(count($list)>0)$matchBy='trimmed';
        }
        // Try 3: case-insensitive match (kalo case beda)
        if(count($list)===0 && $rcTrim!==''){
            $dl=$db->prepare("SELECT id,username,phone,created_at,total_deposit,last_login FROM users WHERE LOWER(TRIM(referred_by))=LOWER(?) ORDER BY total_deposit DESC,created_at DESC LIMIT 500");
            $dl->execute([$rcTrim]);
            $list=$dl->fetchAll();
            if(count($list)>0)$matchBy='case-insensitive';
        }
        // Try 4: kalo skema pake user_id sbg referred_by
        if(count($list)===0){
            try{
                $dl=$db->prepare("SELECT id,username,phone,created_at,total_deposit,last_login FROM users WHERE referred_by=? OR referred_by=? ORDER BY total_deposit DESC,created_at DESC LIMIT 500");
                $dl->execute([(string)$uid,$uid]);
                $list=$dl->fetchAll();
                if(count($list)>0)$matchBy='user_id';
            }catch(Exception $e){}
        }

        $total=count($list);
        $active=0;foreach($list as $r)if(($r['total_deposit']??0)>0)$active++;
        $ea=$db->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND type='referral'");
        $ea->execute([$uid]);$earned=(int)$ea->fetchColumn();

        // Cross-check: berapa user pake ref_code ini sbg referred_by (dari top query HAVING)
        $totalRefsCheck=0;
        if($rcRaw!==''){
            $chk=$db->prepare("SELECT COUNT(*) FROM users WHERE referred_by=?");
            $chk->execute([$rcRaw]);
            $totalRefsCheck=(int)$chk->fetchColumn();
        }

        echo json_encode([
            'ok'=>true,
            'total'=>$total,
            'active'=>$active,
            'earned'=>$earned,
            'downlines'=>$list,
            'ref_code'=>$rcTrim,
            'username'=>$usr['username'],
            '_debug'=>[
                'user_id'=>$uid,
                'ref_code_raw'=>$rcRaw,
                'ref_code_raw_len'=>strlen($rcRaw),
                'ref_code_raw_bytes'=>bin2hex($rcRaw),
                'ref_code_trimmed'=>$rcTrim,
                'match_method'=>$matchBy?:'NONE',
                'top_query_count'=>$totalRefsCheck
            ]
        ]);
    }catch(Exception $e){
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

// Export referral data — CSV atau Excel
if($action==='export_referrals'){
    $fmt=$_GET['format']??'csv';
    try{
        $sql="SELECT u.id,u.username,u.phone,u.ref_code,u.total_deposit,u.created_at,
                    (SELECT COUNT(*) FROM users d WHERE d.referred_by=u.ref_code) AS total_downline,
                    (SELECT COUNT(*) FROM users d WHERE d.referred_by=u.ref_code AND d.total_deposit>0) AS active_downline,
                    COALESCE((SELECT SUM(amount) FROM transactions t WHERE t.user_id=u.id AND t.type='referral'),0) AS total_earned,
                    COALESCE((SELECT SUM(d.total_deposit) FROM users d WHERE d.referred_by=u.ref_code),0) AS downline_dep
              FROM users u
              WHERE u.ref_code IS NOT NULL AND u.ref_code!=''
                AND EXISTS(SELECT 1 FROM users d WHERE d.referred_by=u.ref_code)
              ORDER BY total_earned DESC, downline_dep DESC";
        $rows=$db->query($sql)->fetchAll();

        if($fmt==='excel'||$fmt==='xls'){
            // HTML-based XLS (opens in Excel)
            header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
            header('Content-Disposition: attachment; filename="referrals_'.date('Ymd_His').'.xls"');
            echo "\xEF\xBB\xBF"; // UTF-8 BOM
            echo '<html><head><meta charset="UTF-8"></head><body><table border="1">';
            echo '<tr style="background:#2563eb;color:#fff;font-weight:bold">'
                .'<th>ID</th><th>Username</th><th>Phone</th><th>Ref Code</th>'
                .'<th>Total Deposit</th><th>Downline</th><th>Active Downline</th>'
                .'<th>Downline Depo</th><th>Earned</th><th>Daftar</th></tr>';
            foreach($rows as $r){
                echo '<tr>'
                    .'<td>'.intval($r['id']).'</td>'
                    .'<td>'.htmlspecialchars($r['username']??'').'</td>'
                    .'<td>'.htmlspecialchars($r['phone']??'').'</td>'
                    .'<td>'.htmlspecialchars($r['ref_code']??'').'</td>'
                    .'<td>'.intval($r['total_deposit']).'</td>'
                    .'<td>'.intval($r['total_downline']).'</td>'
                    .'<td>'.intval($r['active_downline']).'</td>'
                    .'<td>'.intval($r['downline_dep']).'</td>'
                    .'<td>'.intval($r['total_earned']).'</td>'
                    .'<td>'.htmlspecialchars($r['created_at']??'').'</td>'
                    .'</tr>';
            }
            echo '</table></body></html>';
            exit;
        }
        // Default: CSV
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="referrals_'.date('Ymd_His').'.csv"');
        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        $out=fopen('php://output','w');
        fputcsv($out,['ID','Username','Phone','Ref Code','Total Deposit','Downline','Active Downline','Downline Depo','Earned','Tanggal Daftar']);
        foreach($rows as $r){
            fputcsv($out,[
                $r['id'],$r['username']??'',$r['phone']??'',$r['ref_code']??'',
                $r['total_deposit'],$r['total_downline'],$r['active_downline'],
                $r['downline_dep'],$r['total_earned'],$r['created_at']??''
            ]);
        }
        fclose($out);
        exit;
    }catch(Exception $e){
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);exit;
    }
}

// ═══════════════════════════════════════
// NEXUS GGR — RTP CONTROL & CALL (FORCE WIN)
// ═══════════════════════════════════════

// Helper: panggil NexusGGR
function nexusCall($method,$extra=[]){
    if(!defined('NEXUS_URL')||!NEXUS_URL)return['status'=>0,'msg'=>'NEXUS_URL tidak dikonfigurasi'];
    $body=array_merge(['method'=>$method,'agent_code'=>NEXUS_AGENT,'agent_token'=>NEXUS_TOKEN],$extra);
    $ch=curl_init(NEXUS_URL);
    curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode($body),
        CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,
        CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>20]);
    $raw=curl_exec($ch);curl_close($ch);
    $res=json_decode($raw,true);
    return $res?:['status'=>0,'msg'=>'NO_RESPONSE','raw'=>substr((string)$raw,0,200)];
}

// Ambil list user + RTP status
if($action==='nexus_players'){
    $r=nexusCall('call_players');
    echo json_encode(['ok'=>($r['status']??0)==1,'data'=>$r['data']??[],'error'=>$r['msg']??null]);exit;
}

// Set RTP satu user
if($action==='nexus_set_rtp'){
    $userCode=trim($d['user_code']??'');
    $provider=trim($d['provider_code']??'PRAGMATIC');
    $rtp=intval($d['rtp']??92);
    if(!$userCode){echo json_encode(['ok'=>false,'error'=>'user_code wajib']);exit;}
    if($rtp<1||$rtp>95){echo json_encode(['ok'=>false,'error'=>'RTP harus 1-95']);exit;}
    $r=nexusCall('control_rtp',['user_code'=>$userCode,'provider_code'=>$provider,'rtp'=>$rtp]);
    echo json_encode(['ok'=>($r['status']??0)==1,'changed_rtp'=>$r['changed_rtp']??null,'error'=>$r['msg']??$r['detail']??null]);exit;
}

// Set RTP banyak user sekaligus
if($action==='nexus_set_bulk_rtp'){
    $userCodes=$d['user_codes']??[];
    $rtp=intval($d['rtp']??92);
    if(!is_array($userCodes)||empty($userCodes)){echo json_encode(['ok'=>false,'error'=>'user_codes wajib (array)']);exit;}
    if($rtp<1||$rtp>95){echo json_encode(['ok'=>false,'error'=>'RTP harus 1-95']);exit;}
    // NexusGGR butuh stringified JSON
    $r=nexusCall('control_users_rtp',['user_codes'=>json_encode(array_values($userCodes)),'rtp'=>$rtp]);
    echo json_encode(['ok'=>($r['status']??0)==1,'changed_rtp'=>$r['changed_rtp']??null,'error'=>$r['msg']??$r['detail']??null]);exit;
}

// List call yang tersedia untuk force win
if($action==='nexus_call_list'){
    $provider=trim($d['provider_code']??'PRAGMATIC');
    $game=trim($d['game_code']??'');
    if(!$game){echo json_encode(['ok'=>false,'error'=>'game_code wajib']);exit;}
    $r=nexusCall('call_list',['provider_code'=>$provider,'game_code'=>$game]);
    echo json_encode(['ok'=>($r['status']??0)==1,'calls'=>$r['calls']??[],'error'=>$r['msg']??null]);exit;
}

// Apply call (force win)
if($action==='nexus_call_apply'){
    $userCode=trim($d['user_code']??'');
    $provider=trim($d['provider_code']??'PRAGMATIC');
    $game=trim($d['game_code']??'');
    $callRtp=intval($d['call_rtp']??0);
    $callType=intval($d['call_type']??1); // 1=Common Free, 2=Buy Bonus Free
    if(!$userCode||!$game||!$callRtp){echo json_encode(['ok'=>false,'error'=>'Parameter tidak lengkap']);exit;}
    $r=nexusCall('call_apply',['user_code'=>$userCode,'provider_code'=>$provider,'game_code'=>$game,'call_rtp'=>$callRtp,'call_type'=>$callType]);
    echo json_encode(['ok'=>($r['status']??0)==1,'called_money'=>$r['called_money']??0,'error'=>$r['msg']??null]);exit;
}

// History call
if($action==='nexus_call_history'){
    $offset=intval($d['offset']??0);
    $limit=intval($d['limit']??50);
    $r=nexusCall('call_history',['offset'=>$offset,'limit'=>$limit]);
    echo json_encode(['ok'=>($r['status']??0)==1,'data'=>$r['data']??[],'error'=>$r['msg']??null]);exit;
}

// Cancel call
if($action==='nexus_call_cancel'){
    $callId=intval($d['call_id']??0);
    if(!$callId){echo json_encode(['ok'=>false,'error'=>'call_id wajib']);exit;}
    $r=nexusCall('call_cancel',['call_id'=>$callId]);
    echo json_encode(['ok'=>($r['status']??0)==1,'canceled_money'=>$r['canceled_money']??0,'error'=>$r['msg']??null]);exit;
}

// List providers
if($action==='nexus_providers'){
    $r=nexusCall('provider_list');
    echo json_encode(['ok'=>($r['status']??0)==1,'providers'=>$r['providers']??[],'error'=>$r['msg']??null]);exit;
}

// ═══════════════════════════════════════
// DEPOSIT RECOVERY — fix deposit "paid" yang saldo-nya belum masuk
// ═══════════════════════════════════════
if($action==='deposit_find_stuck'){
    // Cari semua deposit status=paid tapi belum ada record di transactions
    try{$db->exec("ALTER TABLE transactions ADD COLUMN note TEXT DEFAULT NULL AFTER ref_id");}catch(Exception $e){}
    $rows=$db->query("SELECT d.id,d.tx_id,d.user_id,d.nominal,d.bonus_amount,d.pay_amount,d.method,d.status,d.paid_at,u.username,u.phone,u.balance
                      FROM deposits d
                      LEFT JOIN users u ON u.id=d.user_id
                      WHERE d.status='paid'
                        AND NOT EXISTS (SELECT 1 FROM transactions t WHERE t.ref_id=d.tx_id AND t.type='deposit')
                      ORDER BY d.paid_at DESC LIMIT 100")->fetchAll();
    echo json_encode(['ok'=>true,'stuck'=>$rows,'count'=>count($rows)]);exit;
}

if($action==='deposit_find_unpaid'){
    // Cari deposit pending/expired DB tapi UDAH PAID di SQX (callback gagal/hilang/lambat)
    // Scan SEMUA status=pending dalam 30 hari + status=expired dalam 7 hari (bug sebelumnya
    // mungkin salah-expire deposit yang sebenernya udah paid di SQX)
    require_once __DIR__."/../includes/deposit_lib.php";
    $pending=$db->query("SELECT d.*,u.username,u.phone FROM deposits d
                         LEFT JOIN users u ON u.id=d.user_id
                         WHERE d.nominal>=1000
                           AND (
                             (d.status='pending' AND d.created_at > NOW() - INTERVAL 30 DAY)
                             OR
                             (d.status='expired' AND d.created_at > NOW() - INTERVAL 7 DAY)
                             OR
                             (d.status='failed' AND d.created_at > NOW() - INTERVAL 7 DAY)
                           )
                         ORDER BY d.created_at DESC LIMIT 300")->fetchAll();
    $found=[];$checked=0;$reconciled=0;$startTime=microtime(true);
    foreach($pending as $dep){
        // Stop scan kalau udah 45 detik biar PHP ga timeout
        if(microtime(true)-$startTime>45)break;
        $checked++;
        $r=reconcileDeposit($db,$dep,'admin_find_unpaid');
        if($r==='paid'){
            $reconciled++;
            $found[]=[
                'id'=>$dep['id'],
                'tx_id'=>$dep['tx_id'],
                'username'=>$dep['username'],
                'phone'=>$dep['phone'],
                'nominal'=>intval($dep['nominal']),
                'bonus'=>intval($dep['bonus_amount']),
                'was_status'=>$dep['status'],
                'created_at'=>$dep['created_at']
            ];
            @file_put_contents(__DIR__."/../deposit_recovery.log",
                date('Y-m-d H:i:s')." RECOVERED from=".$dep['status']." tx=".$dep['tx_id']." uid=".$dep['user_id']." nominal=".$dep['nominal']."\n",
                FILE_APPEND);
        }
    }
    $elapsed=round(microtime(true)-$startTime,1);
    echo json_encode(['ok'=>true,'checked'=>$checked,'recovered'=>$reconciled,'found'=>$found,'total_pending'=>count($pending),'elapsed'=>$elapsed]);exit;
}

if($action==='deposit_recredit_bulk'){
    // Ambil semua stuck deposits (status=paid tapi balance belum kena), retry via shared lib
    try{$db->exec("ALTER TABLE transactions ADD COLUMN note TEXT DEFAULT NULL AFTER ref_id");}catch(Exception $e){}
    $rows=$db->query("SELECT d.* FROM deposits d
                      WHERE d.status='paid'
                        AND NOT EXISTS (SELECT 1 FROM transactions t WHERE t.ref_id=d.tx_id AND t.type='deposit')
                      ORDER BY d.paid_at DESC LIMIT 50")->fetchAll();
    $fixed=0;$failed=0;$details=[];
    foreach($rows as $dep){
        $ok=creditDeposit($db,$dep,null,'admin_recredit');
        if($ok){
            $fixed++;
            $details[]=['tx_id'=>$dep['tx_id'],'nominal'=>$dep['nominal'],'status'=>'fixed'];
        }else{
            $failed++;
            $details[]=['tx_id'=>$dep['tx_id'],'nominal'=>$dep['nominal'],'status'=>'failed'];
        }
    }
    echo json_encode(['ok'=>true,'fixed'=>$fixed,'failed'=>$failed,'details'=>$details]);exit;
}
