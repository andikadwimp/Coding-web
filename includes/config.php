<?php
// ─── Timezone: WAJIB Asia/Jakarta biar konsistensi expires_at vs callback SQX ───
// Kalau ga di-set, PHP pake UTC (mayoritas hosting) sementara SQX kirim waktu WIB
// → selisih 7 jam → deposit bisa salah expired / callback mismatch.
date_default_timezone_set('Asia/Jakarta');

// ─── Content-Type: hanya untuk file di folder api/ ───
// Gunakan cara sederhana yang tidak bisa gagal
$_sf = isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '';
$_sf = str_replace('\\', '/', $_sf); // normalize Windows path
$_is_api = (strpos($_sf, '/api/') !== false || strpos($_sf, '\\api\\') !== false);
if ($_is_api) {
    header('Content-Type: application/json; charset=UTF-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
} else {
    header('Content-Type: text/html; charset=UTF-8');
}

// ─── DB Credentials: prioritas db_config.php (dari db_editor) → fallback default ───
$_dbConfigFile = __DIR__ . '/../db_config.php';
if (file_exists($_dbConfigFile)) {
    require_once $_dbConfigFile;
}

if(!defined('DB_HOST'))define('DB_HOST','localhost');
if(!defined('DB_NAME'))define('DB_NAME','bjztkhfx_k7777');
if(!defined('DB_USER'))define('DB_USER','bjztkhfx_k7777');
if(!defined('DB_PASS'))define('DB_PASS','bjztkhfx_k7777');

// Credentials loaded from DB below (with fallback defaults)

try {
    $db = new PDO(
        "mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
         PDO::ATTR_EMULATE_PREPARES=>false]
    );
    // Set MySQL session timezone ke WIB biar NOW() / CURRENT_TIMESTAMP konsisten dgn PHP date()
    // Critical untuk deposit expires_at matching dengan callback SQX (WIB)
    try{ $db->exec("SET time_zone = '+07:00'"); }catch(Exception $e){}
} catch(PDOException $e) {
    @file_put_contents(__DIR__.'/../db_error.log',date('Y-m-d H:i:s')." ".$e->getMessage()."\n",FILE_APPEND);
    if ($_is_api) {
        die(json_encode(['ok'=>false,'error'=>'DB_UNAVAILABLE']));
    } else {
        die('<h2>Layanan sedang gangguan</h2><p>Silakan coba beberapa saat lagi.</p>');
    }
}

// ─── Load credentials from DB ───
try{
    $__cr=$db->query("SELECT `key`,`value` FROM settings WHERE `key` IN('nexus_agent','nexus_token','nexus_url','squadonyx_merchant','squadonyx_url')")->fetchAll(PDO::FETCH_KEY_PAIR);
}catch(Exception $e){$__cr=[];}
if(!defined('NEXUS_URL'))define('NEXUS_URL',$__cr['nexus_url']??'https://api.nexusggr.com');
if(!defined('NEXUS_AGENT'))define('NEXUS_AGENT',$__cr['nexus_agent']??'loszetas');
if(!defined('NEXUS_TOKEN'))define('NEXUS_TOKEN',$__cr['nexus_token']??'a113ecb6ab8d6d85805a0b09bff53725');
if(!defined('SQX_URL'))define('SQX_URL',$__cr['squadonyx_url']??'https://panel.squadonyx.biz.id/api.php');
if(!defined('SQX_STREAM'))define('SQX_STREAM','https://panel.squadonyx.biz.id/stream.php');
if(!defined('SQX_MERCHANT'))define('SQX_MERCHANT',$__cr['squadonyx_merchant']??'muid_4d7b920c9d1f472d7903c7387119ddfb');
if(!defined('SQUADONYX_MERCHANT'))define('SQUADONYX_MERCHANT',SQX_MERCHANT);
if(!defined('SQUADONYX_URL'))define('SQUADONYX_URL',SQX_URL);

function ok($data=[]){ echo json_encode(['ok'=>true]+$data); exit; }
function err($msg,$code=400){ http_response_code($code); echo json_encode(['ok'=>false,'error'=>$msg]); exit; }

// Helper ambil setting value dengan default fallback
function getSetting($db,$key,$default=''){
    try{
        $s=$db->prepare("SELECT `value` FROM settings WHERE `key`=? LIMIT 1");
        $s->execute([$key]);
        $v=$s->fetchColumn();
        if($v===false||$v===null||$v==='')return $default;
        return $v;
    }catch(Exception $e){return $default;}
}

// ═══ Normalize URL path — bikin semua image path absolute ═══
// Bug fix: Brave/strict browser ga resolve path relative dengan benar
// kalau user di sub-path (cth: /team/settings.php → broken)
// Fix: auto-prefix / untuk semua path yang mulai dengan 'asset/', 'img/', 'uploads/'
function normUrl($url){
    if(!$url||!is_string($url))return $url;
    $url=trim($url);
    // Sudah absolute (http/https/data) → biarkan
    if(preg_match('~^(https?:|data:|//|/)~i',$url))return $url;
    // Path relative yang perlu di-prefix
    if(preg_match('~^(asset|img|uploads)/~',$url))return '/'.$url;
    return $url;
}

// Normalisasi semua settings yang berisi URL/path gambar saat di-load
function normSettings(&$sets){
    if(!is_array($sets))return;
    foreach($sets as $k=>$v){
        if(is_string($v))$sets[$k]=normUrl($v);
    }
}
function input(){ return json_decode(file_get_contents('php://input'),true) ?: $_POST; }
function idr($n){ return 'Rp '.number_format(intval($n),0,',','.'); }

function auth(){
    $token = $_COOKIE['lx_token'] ?? '';
    if(!$token) err('NOT_LOGGED_IN', 401);
    global $db;
    $s = $db->prepare("SELECT id FROM users WHERE auth_token=? AND status='active'");
    $s->execute([$token]);
    $r = $s->fetch();
    if(!$r) err('NOT_LOGGED_IN', 401);
    return $r['id'];
}

function getUid(){
    $token = $_COOKIE['lx_token'] ?? '';
    if(!$token) return null;
    global $db;
    try {
        $s = $db->prepare("SELECT id FROM users WHERE auth_token=?");
        $s->execute([$token]);
        $r = $s->fetch();
        return $r ? $r['id'] : null;
    } catch(Exception $e){ return null; }
}

function admin(){
    $uid = auth();
    global $db;
    $u = $db->prepare("SELECT role FROM users WHERE id=?");
    $u->execute([$uid]);
    $r = $u->fetch();
    if(!$r || $r['role'] !== 'admin') err('FORBIDDEN', 403);
    return $uid;
}

function genRef(){
    global $db;
    // Generate angka random 8 digit, pastikan unik (retry max 10x)
    for($i=0;$i<10;$i++){
        $code=strval(mt_rand(10000000,99999999));
        try{
            $st=$db->prepare("SELECT 1 FROM users WHERE ref_code=? LIMIT 1");
            $st->execute([$code]);
            if(!$st->fetchColumn())return $code;
        }catch(Exception $e){return $code;} // kalo DB error, pakai aja
    }
    // Fallback: 10 digit (hampir pasti unik)
    return strval(mt_rand(1000000000,9999999999));
}

function logTx($db, $uid, $type, $amount, $note=null, $ref=null){
    try {
        $u = $db->prepare("SELECT balance FROM users WHERE id=?");
        $u->execute([$uid]);
        $bal = $u->fetchColumn();
        // Guard: kalau user ga exist, jangan proses (hindari orphan transaction)
        if($bal === false){
            @file_put_contents(__DIR__.'/../error_log.txt',
                date('Y-m-d H:i:s')." logTx SKIP: user_id=$uid not found (type=$type amount=$amount)\n",
                FILE_APPEND);
            return false;
        }
        $bal = intval($bal);
        $after = $bal + $amount;
        // Prevent negative balance for unsigned column
        if($after < 0) $after = 0;
        $db->prepare("INSERT INTO transactions(user_id,type,amount,balance_before,balance_after,ref_id,note) VALUES(?,?,?,?,?,?,?)")
           ->execute([$uid,$type,$amount,$bal,$after,$ref,$note]);
        $db->prepare("UPDATE users SET balance=? WHERE id=?")->execute([$after,$uid]);
        return $after;
    } catch(Exception $e) {
        // Log error but don't crash
        @file_put_contents(__DIR__.'/../error_log.txt',
            date('Y-m-d H:i:s')." logTx ERROR uid=$uid type=$type amount=$amount: ".$e->getMessage()."\n",
            FILE_APPEND);
        return false;
    }
}

function autoMemo($db, $uid, $title, $body){
    try {
        $db->prepare("INSERT INTO memos(type,to_user_id,title,body) VALUES('target',?,?,?)")
           ->execute([$uid,$title,$body]);
    } catch(Exception $e){}
    // Push notification — kirim ke device user kalau udah subscribe
    // Wrap try/catch + file_exists agar aman kalau webpush.php belum ter-load
    try {
        $wpLib = __DIR__ . '/webpush.php';
        if (file_exists($wpLib)) {
            require_once $wpLib;
            if (function_exists('pushNotify')) {
                // Strip HTML tags dari title (beberapa caller kirim SVG di title) + truncate body
                $plainTitle = trim(html_entity_decode(strip_tags($title)));
                $plainBody = trim(html_entity_decode(strip_tags($body)));
                if (strlen($plainBody) > 200) $plainBody = substr($plainBody, 0, 200) . '...';
                // URL default: dashboard. Bisa disesuaikan per-trigger tapi dashboard cukup general
                pushNotify($db, $uid, $plainTitle ?: 'Notifikasi', $plainBody, '/dashboard.php');
            }
        }
    } catch(Exception $e){}
}

// ─── VIP SYSTEM ───
function vipTable(){
    // Format per row: [level, totalDepositK_naik_level, bonusNaikK, gajiHarianK, gajiMingguanK, gajiBulananK]
    // 1K = Rp 1,000. Total deposit di VIP 50 = 1 miliar (1.000.000 K × 1000)
    return [
        [0,0,0,0,0,0],
        [1,200,1,0.05,0.50,1],[2,2000,3,0.10,1,2],[3,4000,5,0.15,2,3],[4,13000,8,0.30,2.50,5],
        [5,45000,18,0.80,5,8],[6,88000,30,1.30,8,15],[7,180000,30,1.80,10,20],[8,380000,100,2.50,18,30],[9,680000,150,2.90,25,50],
        [10,1100000,200,3.90,30,60],[11,2000000,300,8.10,50,80],[12,3000000,400,13.20,60,100],[13,4000000,500,17.50,80,130],[14,5000000,600,22.20,100,160],
        [15,6000000,700,30.10,120,200],[16,8000000,800,35.30,150,250],[17,10000000,1000,50,200,350],[18,12000000,1200,60,250,400],[19,14000000,1400,75,300,500],
        [20,16000000,1600,93.60,350,550],[21,18000000,1800,109,400,1600],[22,20000000,2000,126.70,500,1800],[23,23000000,2300,145,600,2100],[24,26000000,2600,157,700,2400],
        [25,30000000,3000,176,800,2700],[26,35000000,3500,192,900,3000],[27,40000000,4000,205,1000,3500],[28,45000000,4500,239,1100,4000],[29,50000000,5000,292,1200,4500],
        [30,60000000,6000,365,1300,5000],[31,70000000,7000,443,1500,6000],[32,80000000,8000,503,1700,7000],[33,90000000,9000,543,2000,8000],[34,100000000,10000,586,2500,9000],
        [35,120000000,12000,658,3000,12000],[36,140000000,14000,717,3500,14000],[37,160000000,16000,851,4000,16000],[38,180000000,18000,1053,4500,18000],[39,200000000,20000,1213,5000,20000],
        [40,230000000,23000,1458,5500,23000],[41,260000000,26000,1637,6000,26000],[42,300000000,30000,1812,7000,30000],[43,350000000,35000,2055,8000,35000],[44,400000000,40000,2196,10000,40000],
        [45,500000000,50000,2563,12000,50000],[46,600000000,60000,3195,14000,60000],[47,700000000,70000,4096,16000,70000],[48,800000000,80000,4858,18000,80000],[49,900000000,90000,5705,20000,90000],
        [50,1000000000,100000,6298,20000,100000]
    ];
}

function calcVipLevel($totalDeposit){
    $tbl=vipTable(); $lv=0;
    $toK=$totalDeposit/1000;
    for($i=50;$i>=1;$i--){
        if(isset($tbl[$i])&&$toK>=$tbl[$i][1]){$lv=$i;break;}
    }
    return $lv;
}

function updateVipLevel($db,$uid){
    try {
        $u=$db->prepare("SELECT total_deposit,vip_level FROM users WHERE id=?");$u->execute([$uid]);$r=$u->fetch();
        if(!$r)return[0,0];
        $oldLv=intval($r['vip_level']??0);
        $newLv=calcVipLevel(intval($r['total_deposit']));
        if($newLv!==$oldLv){
            $db->prepare("UPDATE users SET vip_level=? WHERE id=?")->execute([$newLv,$uid]);
            if($newLv>$oldLv){
                $tbl=vipTable();$totalBonus=0;
                for($l=$oldLv+1;$l<=$newLv;$l++){
                    if(isset($tbl[$l]))$totalBonus+=$tbl[$l][2];
                }
                if($totalBonus>0){
                    $bonusRp=$totalBonus*1000;
                    logTx($db,$uid,'bonus',$bonusRp,'Bonus naik VIP '.$oldLv.'→'.$newLv);
                    autoMemo($db,$uid,'VIP Naik Level!','VIP naik ke Level '.$newLv.'! Bonus Rp '.number_format($bonusRp,0,',','.').' masuk ke saldo.');
                }
            }
        }
        return[$oldLv,$newLv];
    } catch(Exception $e){ return[0,0]; }
}

function claimVipBonus($db,$uid,$type){
    $u=$db->prepare("SELECT vip_level,total_deposit FROM users WHERE id=?");$u->execute([$uid]);$r=$u->fetch();
    if(!$r)return['ok'=>false,'error'=>'User tidak ditemukan'];
    $vl=intval($r['vip_level']??0);
    if($vl<1)return['ok'=>false,'error'=>'VIP level 0 tidak mendapat bonus'];
    $tbl=vipTable();
    $idx=($type==='daily')?3:(($type==='weekly')?4:5);
    $bonusK=$tbl[$vl][$idx]??0;
    if($bonusK<=0)return['ok'=>false,'error'=>'Tidak ada bonus untuk level ini'];
    if($type==='daily')$period=date('Y-m-d');
    elseif($type==='weekly')$period=date('Y-W');
    else $period=date('Y-m');
    try {
        $chk=$db->prepare("SELECT id FROM vip_claims WHERE user_id=? AND claim_type=? AND period=?");
        $chk->execute([$uid,$type,$period]);
        if($chk->fetch())return['ok'=>false,'error'=>'Bonus '.$type.' sudah diklaim'];
        $bonusRp=$bonusK*1000;
        $after=logTx($db,$uid,'bonus',$bonusRp,'VIP '.$type.' bonus (VIP '.$vl.')');
        $db->prepare("INSERT INTO vip_claims(user_id,claim_type,period,vip_level,amount) VALUES(?,?,?,?,?)")
           ->execute([$uid,$type,$period,$vl,$bonusRp]);
        return['ok'=>true,'amount'=>$bonusRp,'balance'=>$after,'vip_level'=>$vl];
    } catch(Exception $e){ return['ok'=>false,'error'=>$e->getMessage()]; }
}

function ensureDailyRedeem($db){
    try {
        $today=date('Y-m-d');
        $chk=$db->prepare("SELECT id,code FROM redeem_codes WHERE DATE(created_at)=? AND amount=888 AND max_uses=100 LIMIT 1");
        $chk->execute([$today]);
        $existing=$chk->fetch();
        if($existing)return $existing['code'];
        $code=str_pad(rand(10000,99999),5,'0',STR_PAD_LEFT);
        $expires=$today.' 23:59:59';
        $db->prepare("UPDATE redeem_codes SET status='expired' WHERE amount=888 AND max_uses=100 AND DATE(created_at)<?")->execute([$today]);
        $db->prepare("INSERT INTO redeem_codes(code,amount,max_uses,used_count,status,expires_at) VALUES(?,888,100,0,'active',?)")->execute([$code,$expires]);

        // ═══ Auto-post ke blog supaya user bisa liat di tab Blogger ═══
        try {
            $dayName=['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'][date('w')];
            $dateFmt=$dayName.', '.date('d/m/Y');
            $title='🎁 Kode Redeem '.$dateFmt;
            $body='Hai member setia! Kami bagikan kode redeem khusus hari ini.<br><br>'.
                  '<div style="background:rgba(56,189,248,.08);border:1.5px solid rgba(56,189,248,.35);border-radius:10px;padding:14px;text-align:center;margin:10px 0">'.
                  '<div style="font-size:.7rem;color:#94a3b8;margin-bottom:6px">KODE</div>'.
                  '<div style="font-family:Chakra Petch,monospace;font-size:1.6rem;font-weight:800;color:#38bdf8;letter-spacing:4px">'.$code.'</div>'.
                  '<div style="font-size:.7rem;color:#cbd5e1;margin-top:6px">Bonus Rp 888</div>'.
                  '</div>'.
                  '<b>Syarat &amp; Ketentuan:</b><br>'.
                  '• Berlaku hanya hari ini sampai 23:59 WIB<br>'.
                  '• Maksimal 100 user pertama<br>'.
                  '• 1x redeem per akun<br>'.
                  '• Langsung masuk ke saldo<br><br>'.
                  'Cara pakai: Buka <b>Promosi</b> → tab <b>Kode Penukaran</b> → masukkan kode → klik Tukar. Selamat mencoba!';
            $db->prepare("INSERT INTO memos(type,title,body) VALUES('blog',?,?)")->execute([$title,$body]);
        } catch(Exception $e){ /* silent, blog post optional */ }

        return $code;
    } catch(Exception $e){ return '00000'; }
}

// App Gate check (skipped for /api/, /team/, and exempt pages)
if(file_exists(__DIR__.'/../app_gate.php'))require_once __DIR__.'/../app_gate.php';

// ═══ BOTTOM NAV RENDERER ═══
// Render bnav yang sama di index + dashboard + halaman lain
// Sekarang FULL SVG inline pakai currentColor → otomatis ngikut warna tema
// Inactive = outline (stroke), Active = filled solid (fill+stroke)
function renderBnav($db,$active='beranda',$isLoggedIn=false){
    // SVG outline (inactive state)
    $svgOff=[
        'beranda'=>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
        'promosi'=>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"/></svg>',
        'undang'=>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/></svg>',
        'deposit'=>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2v13"/><polyline points="6 9 12 15 18 9"/><line x1="4" y1="22" x2="20" y2="22"/></svg>',
        'profil'=>'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
    ];
    // SVG filled (active state) — bg fill currentColor + sedikit transparent
    $svgOn=[
        'beranda'=>'<svg viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/></svg>',
        'promosi'=>'<svg viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round"><polyline points="20 12 20 22 4 22 4 12" fill="none" stroke="currentColor" stroke-width="2"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7" stroke="#fff" stroke-width="1.5"/><path d="M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"/></svg>',
        'undang'=>'<svg viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14" stroke-width="2.5"/><line x1="22" y1="11" x2="16" y2="11" stroke-width="2.5"/></svg>',
        'deposit'=>'<svg viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 7v8" stroke="#fff" stroke-width="2.5"/><polyline points="8 11 12 15 16 11" stroke="#fff" stroke-width="2.5" fill="none"/></svg>',
        'profil'=>'<svg viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
    ];

    $bnIcon=function($key,$isActive) use($svgOff,$svgOn){
        return $isActive ? ($svgOn[$key]??$svgOff[$key]??'') : ($svgOff[$key]??'');
    };

    // Semua halaman bisa diakses tanpa login (guest mode).
    // Action button yang butuh login dihandle di per-page JS dengan requireLogin().
    $homeHref='index.php';
    $undangHref='undang.php';
    $depositHref='deposit.php';
    $profilHref='profil.php';
    $promoHref='promo.php';

    // Undang sebagai FAB middle button (circular, sticking up).
    $items=[
        ['key'=>'beranda','label'=>'Beranda','href'=>$homeHref,'fab'=>false],
        ['key'=>'promosi','label'=>'Promosi','href'=>$promoHref,'fab'=>false],
        ['key'=>'undang','label'=>'Undang','href'=>$undangHref,'fab'=>true],
        ['key'=>'deposit','label'=>'Deposit','href'=>$depositHref,'fab'=>false],
        ['key'=>'profil','label'=>'Profil','href'=>$profilHref,'fab'=>false],
    ];

    $activeKey=$active==='promo'?'promosi':$active;
    $html='<nav class="bnav">';
    foreach($items as $it){
        $isAct=$it['key']===$activeKey;
        $cls='bnav-i'.($isAct?' active':'').(!empty($it['fab'])?' bnav-fab':'');
        $html.='<a class="'.$cls.'" href="'.$it['href'].'">';
        if(!empty($it['fab'])){
            // FAB: wrap icon in circle div for styling
            $html.='<div class="bnav-fab-circle">'.$bnIcon($it['key'],true).'</div>';
        }else{
            $html.=$bnIcon($it['key'],$isAct);
        }
        $html.='<span>'.$it['label'].'</span></a>';
    }
    $html.='</nav>';
    return $html;
}


