<?php
require_once 'includes/config.php';
header('Content-Type: text/html; charset=UTF-8');
$isLoggedIn = (bool)getUid();  // guest-friendly: page accessible, action gated by JS

// Auto-create promos table if missing
try{$db->exec("CREATE TABLE IF NOT EXISTS promos(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,title VARCHAR(200),description TEXT,image_url VARCHAR(500),link VARCHAR(500) DEFAULT '#',button_text VARCHAR(50) DEFAULT 'Proses',category VARCHAR(30) DEFAULT 'promosi',status VARCHAR(20) DEFAULT 'active',created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");}catch(Exception $e){}
// Auto-add columns
try{$db->exec("ALTER TABLE promos ADD COLUMN category VARCHAR(30) DEFAULT 'promosi' AFTER status");}catch(Exception $e){}
try{$db->exec("ALTER TABLE promos ADD COLUMN link VARCHAR(500) DEFAULT '#' AFTER image_url");}catch(Exception $e){}
try{$db->exec("ALTER TABLE promos ADD COLUMN button_text VARCHAR(50) DEFAULT 'Proses' AFTER link");}catch(Exception $e){}
try{$db->exec("ALTER TABLE promos ADD COLUMN description TEXT AFTER title");}catch(Exception $e){}
try{$db->exec("ALTER TABLE users ADD COLUMN rebate_claimed_to BIGINT UNSIGNED DEFAULT 0 AFTER total_turnover");}catch(Exception $e){}

$sets=[];
try{$st=$db->query("SELECT `key`,`value` FROM settings");foreach($st->fetchAll() as $r)$sets[$r['key']]=$r['value'];normSettings($sets);}catch(Exception $e){}
$sn=$sets['site_name']??'Situs';
try{ensureDailyRedeem($db);}catch(Exception $e){}
$uid=getUid();
// Ambil kode harian untuk ditampilkan ke user (pake kode yg lagi aktif)
$dailyCode='';
try{
    $today=date('Y-m-d');
    $rc=$db->prepare("SELECT code FROM redeem_codes WHERE DATE(created_at)=? AND amount=888 AND max_uses=100 AND status='active' LIMIT 1");
    $rc->execute([$today]);
    $dailyCode=$rc->fetchColumn()?:'';
}catch(Exception $e){}
$cats=['promosi'=>'Promosi','rebate'=>'Rebate','vip'=>'VIP','kode'=>'Kode Penukaran'];
$promos=[];
try{
    $prows=$db->query("SELECT id,title,description,image_url,link,button_text,category FROM promos WHERE status='active' ORDER BY created_at DESC")->fetchAll();
    foreach($prows as $p){
        $c=$p['category']??'promosi';
        if($c==='cashback')$c='rebate';
        if($c==='banner')$c='promosi';
        $promos[$c][]=$p;
    }
}catch(Exception $e){}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php require_once dirname(__FILE__).'/pwa_head.php'; ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Promosi - <?php echo $sn; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="theme.php?v=<?=time()?>">
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;-webkit-text-size-adjust:100%;text-size-adjust:100%}
html{font-size:16px!important;overflow-x:hidden}
body{overflow-x:hidden;max-width:100vw;font-family:'Poppins',sans-serif;background:var(--bg);color:var(--t);min-height:100vh;padding-bottom:70px}
.tabs{display:flex;padding:10px 12px 0;gap:0;overflow-x:auto;scrollbar-width:none;position:sticky;top:0;z-index:10;background:var(--bg);border-bottom:1px solid rgba(var(--sec-rgb,56,189,248),.25)}
.tabs::-webkit-scrollbar{display:none}
.tab-i{padding:12px 14px;font-size:.78rem;font-weight:700;color:var(--t3);cursor:pointer;position:relative;white-space:nowrap;flex-shrink:0}
.tab-i.on{color:var(--sec,var(--sec))}
.tab-i.on::after{content:'';position:absolute;bottom:-1px;left:10px;right:10px;height:3px;background:var(--sec,var(--sec));border-radius:2px}
.panel{display:none;padding:16px}.panel.on{display:block}
.promo-card{margin-bottom:14px;border-radius:14px;overflow:hidden;border:1px solid rgba(255,255,255,.06);background:rgba(255,255,255,.03);transition:transform .15s ease;position:relative}
.promo-card:active{transform:scale(.98)}
.promo-card img{width:100%;display:block;height:auto;object-fit:cover;cursor:pointer}
.promo-body{padding:10px 14px;display:flex;align-items:center;gap:12px}
.promo-body .pb-icon{width:38px;height:38px;flex-shrink:0;border-radius:8px;overflow:hidden;background:rgba(255,255,255,.05);display:flex;align-items:center;justify-content:center}
.promo-body .pb-icon img{width:100%;height:100%;object-fit:contain}
.promo-body .pb-text{flex:1;min-width:0}
.promo-body .pb-text h3{font-size:.88rem;font-weight:600;line-height:1.3;color:var(--t);letter-spacing:-.005em}
.promo-body .pb-text p{font-size:.7rem;color:var(--t3);margin-top:2px;line-height:1.3}
.promo-body .pb-btn{padding:8px 16px;font-size:.74rem;font-weight:700;color:#fff;background:linear-gradient(135deg,var(--pri),var(--pri-d));background-size:200% auto;border:none;border-radius:999px;text-decoration:none;white-space:nowrap;flex-shrink:0;display:inline-flex;align-items:center;cursor:pointer;transition:background-position .25s ease,box-shadow .15s;letter-spacing:.2px;box-shadow:0 2px 8px rgba(var(--pri-rgb),.3)}
.promo-body .pb-btn:hover{background-position:right center;box-shadow:0 4px 12px rgba(var(--pri-rgb),.45)}
.promo-body .pb-btn:hover{background-position:right center}
.empty{text-align:center;padding:50px 20px;color:var(--t3);font-size:.85rem}
/* REBATE */
.rb-box{margin:0 0 20px;border:1.5px solid rgba(var(--sec-rgb,56,189,248),.35);border-radius:14px;padding:18px 20px;background:rgba(var(--sec-rgb,56,189,248),.04)}

/* VIP */
.vip-card{margin:0 0 20px;border-radius:14px;padding:20px;background:linear-gradient(135deg,#1a1a2e,#2d2d44,#1a1a2e);position:relative;overflow:hidden}
.vip-card::before{content:'';position:absolute;top:-50%;right:-30%;width:200px;height:200px;border-radius:50%;background:var(--tint-1)}
.vip-card .vc-top{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:16px}
.vip-card .vc-badge{display:inline-flex;align-items:center;gap:6px;background:var(--tint-2);border-radius:20px;padding:6px 14px 6px 6px}
.vip-card .vc-badge .vb-icon{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.7rem}
.vip-card .vc-badge .vb-text{font-size:.75rem;font-weight:800;color:#fff}
.vip-card .vc-label{font-size:.72rem;color:rgba(255,255,255,.6);margin-top:4px}
.vip-card .vc-btn{padding:8px 16px;border-radius:8px;background:var(--tint-2);border:1px solid var(--tint-3);color:#fff;font-size:.72rem;font-weight:700;cursor:pointer;font-family:inherit}
.vip-card .vc-prog{margin-bottom:10px;display:flex;align-items:center;gap:10px}
.vip-card .vc-prog-bar{flex:1;height:6px;border-radius:3px;background:var(--tint-3);overflow:hidden}
.vip-card .vc-prog-bar .fill{height:100%;border-radius:3px;background:linear-gradient(90deg,var(--pri),var(--gl,var(--sec-l, var(--pri-l))))}
.vip-card .vc-prog .vc-next{display:flex;align-items:center;gap:4px;font-size:.6rem;color:var(--bd2)}
.vip-card .vc-prog .vc-next .vn-icon{width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.5rem}
.vip-card .vc-info{font-size:.7rem;color:var(--bd2)}
.vip-card .vc-info span{color:var(--pri);font-weight:800}
.vip-title{font-size:1rem;font-weight:800;margin-bottom:14px}
.vip-stabs{display:flex;gap:0;border:1px solid var(--bd);border-radius:10px;overflow:hidden;margin-bottom:14px}
.vip-stab{flex:1;padding:10px 8px;text-align:center;font-size:.72rem;font-weight:700;color:var(--t3);cursor:pointer;background:transparent}
.vip-stab.on{background:var(--sec);color:#fff}
.vip-rows{display:flex;flex-direction:column;gap:8px;margin-bottom:16px}
.vip-row{display:flex;align-items:center;padding:14px 12px;background:rgba(var(--sec-rgb,56,189,248),.04);border:1px solid var(--bd);border-radius:10px;gap:10px}
.vip-row.on{background:rgba(var(--sec-rgb,56,189,248),.1);border-color:var(--sec)}
.vip-row .vr-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 10px 4px 4px;border-radius:16px;min-width:85px}
.vip-row .vr-badge .vri{width:24px;height:24px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:.5rem}
.vip-row .vr-badge .vrt{font-size:.68rem;font-weight:800;color:#fff}
.vip-row .vr-mid{flex:1;font-size:.78rem;font-weight:600;color:var(--t2);text-align:center}
.vip-row .vr-val{font-size:.82rem;font-weight:800;color:var(--pri);text-align:right;min-width:60px}
.vip-row .vr-prog{flex:1;display:flex;flex-direction:column;align-items:center;gap:2px}
.vip-row .vr-prog-bar{width:80%;height:4px;border-radius:2px;background:var(--tint-2);overflow:hidden}
.vip-row .vr-prog-bar .fill{height:100%;background:var(--sec);border-radius:2px}
.vip-row .vr-prog-txt{font-size:.55rem;color:var(--t3)}
.vip-rules{margin-bottom:20px}
.vip-rules h3{font-size:.95rem;font-weight:800;margin-bottom:12px}
.vip-rules p{font-size:.72rem;color:var(--t2);line-height:1.7;margin-bottom:10px}
.vip-claim-btn{display:block;width:100%;padding:14px;text-align:center;background:rgba(var(--sec-rgb,56,189,248),.15);border:1px solid rgba(var(--sec-rgb,56,189,248),.3);border-radius:10px;color:var(--sec);font-size:.88rem;font-weight:700;cursor:pointer;font-family:inherit;margin-bottom:20px}
.rb-top{display:flex;justify-content:space-between;margin-bottom:14px}
.rb-top .rt-l{font-size:.82rem;color:var(--t2)}.rb-top .rt-l span{color:#ffd253;font-weight:800;font-size:1rem}
.rb-top .rt-r{font-size:.82rem;color:var(--t2)}.rb-top .rt-r span{color:#4ade80;font-weight:800;font-size:1rem}
.rb-claim{display:flex;align-items:center;gap:14px;margin-bottom:14px;background:rgba(0,0,0,.2);border-radius:10px;padding:12px 16px}
.rb-claim .rc-icon{width:48px;height:48px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.rb-claim .rc-amount{flex:1;font-size:1.5rem;font-weight:800;color:#ffd253}
.rb-claim .rc-btn{padding:10px 22px;border-radius:8px;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit;border:1px solid rgba(var(--sec-rgb,56,189,248),.4);background:rgba(var(--sec-rgb,56,189,248),.12);color:var(--sec,var(--sec))}
.rb-bar{margin-bottom:4px;height:6px;border-radius:3px;background:var(--tint-2);overflow:hidden}
.rb-bar .fill{height:100%;border-radius:3px;background:linear-gradient(90deg,var(--sec),#4ade80)}
.rb-range{display:flex;justify-content:space-between;font-size:.65rem;color:var(--t3)}
.rb-msg{text-align:center;padding:10px;font-size:.78rem;color:#4ade80;font-weight:600;display:none}
.rb-tbl{margin:0 0 20px;border-radius:14px;overflow:hidden;border:1px solid rgba(var(--sec-rgb,56,189,248),.2);background:rgba(var(--sec-rgb,56,189,248),.04)}
.rb-tbl-hdr{display:flex;align-items:center;gap:8px;padding:16px 18px;font-size:.92rem;font-weight:700}
.rb-tbl table{width:100%;border-collapse:collapse}
.rb-tbl th{padding:12px 16px;font-size:.72rem;font-weight:700;color:var(--t3);text-align:center;background:rgba(var(--sec-rgb,56,189,248),.08)}
.rb-tbl td{padding:14px 16px;font-size:.82rem;font-weight:600;text-align:center;border-top:1px solid rgba(var(--sec-rgb,56,189,248),.1)}
.rb-tbl tr.active{background:rgba(var(--sec-rgb,56,189,248),.1)}.rb-tbl tr.active td{color:var(--sec)}
.rb-desc{margin:0 0 30px}.rb-desc h3{font-size:.88rem;font-weight:700;text-align:center;margin-bottom:14px;display:flex;align-items:center;gap:8px;justify-content:center}
.rb-desc h3::before,.rb-desc h3::after{content:'';flex:1;height:1px;background:rgba(var(--sec-rgb,56,189,248),.3);max-width:60px}
.rb-desc p{font-size:.75rem;color:var(--t2);line-height:1.7;margin-bottom:6px}
/* BNAV */
@keyframes shimmer{0%{background-position:-200% 0}100%{background-position:200% 0}}
.bnav{position:fixed;bottom:0;left:0;right:0;z-index:100;display:flex;background:var(--nav-bg,#0a1628);padding:8px 0 env(safe-area-inset-bottom,6px);overflow:hidden;border-radius:14px 14px 0 0;box-shadow:0 -4px 20px rgba(0,0,0,.5);border-top:1px solid rgba(var(--sec-rgb,56,189,248),.2)}
.bnav-i{flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;padding:6px 0;color:var(--t3);font-size:.6rem;font-weight:600;text-decoration:none}
.bnav-i.active{color:var(--sec,var(--sec))}
.bnav::before{content:"";position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent 2%,var(--pri,var(--sec)) 15%,var(--gl,var(--sec-l, var(--pri-l))) 50%,var(--pri,var(--sec)) 85%,transparent 98%);animation:shimmer 3s linear infinite;background-size:200% 100%}
.bnav-i img{width:36px;height:36px;object-fit:contain}
.toast-box{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--s);border:1px solid var(--bd);border-radius:14px;padding:24px 30px;text-align:center;z-index:9999;max-width:300px;box-shadow:0 10px 40px rgba(0,0,0,.5);animation:fadeIn .2s}
.toast-box p{font-size:.85rem;font-weight:600;color:var(--t);line-height:1.5;margin-bottom:16px}
.toast-box button{padding:8px 30px;background:var(--sec);border:none;border-radius:8px;color:#fff;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit}
.toast-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9998}
@keyframes fadeIn{from{opacity:0;transform:translate(-50%,-50%) scale(.9)}to{opacity:1;transform:translate(-50%,-50%) scale(1)}}
.sosmed-ic{width:52px;height:52px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;text-decoration:none;transition:transform .15s,box-shadow .15s;box-shadow:0 2px 8px rgba(0,0,0,.25)}
.sosmed-ic:active{transform:scale(.92)}
.sosmed-ic:hover{transform:translateY(-2px);box-shadow:0 4px 14px rgba(0,0,0,.35)}

@keyframes skelSweep{0%{background-position:-200% 0}100%{background-position:200% 0}}
.skel{background:linear-gradient(90deg,var(--bg2) 25%,rgba(var(--pri-rgb,14,165,233),.18) 50%,var(--bg2) 75%);background-size:200% 100%;animation:skelSweep 1.4s ease-in-out infinite;border-radius:8px}
.skel-promo{height:160px;background:linear-gradient(90deg,var(--bg2) 25%,rgba(var(--pri-rgb,14,165,233),.18) 50%,var(--bg2) 75%);background-size:200% 100%;animation:skelSweep 1.4s ease-in-out infinite;border-radius:14px}
</style>
</head>
<body>

<div class="tabs" id="tabs">
  <div class="skel" style="width:80px;height:32px;margin:8px;border-radius:8px"></div>
  <div class="skel" style="width:80px;height:32px;margin:8px;border-radius:8px;opacity:.6"></div>
  <div class="skel" style="width:80px;height:32px;margin:8px;border-radius:8px;opacity:.4"></div>
</div>
<div id="panels">
  <div style="padding:14px">
    <div class="skel-promo" style="margin-bottom:14px"></div>
    <div class="skel-promo" style="margin-bottom:14px"></div>
    <div class="skel-promo"></div>
  </div>
</div>

<?php echo renderBnav($db,"promo", $isLoggedIn); ?>

<script>
var cats=<?php echo json_encode($cats); ?>;
var promos=<?php echo json_encode($promos); ?>;
var SI=<?php echo json_encode($sets); ?>;
var catKeys=Object.keys(cats);
var UID=<?php echo $uid?json_encode($uid):'null'; ?>;
var DAILY_CODE=<?php echo json_encode($dailyCode); ?>;
// ─── Helper: safe promo image fallback (avoid fragile inline onerror) ───
function promoImgFail(img){
  if(img.dataset._failed)return; img.dataset._failed='1';
  img.style.display='none';
  var c = img.closest('.promo-card');
  if(!c) return;
  var lnk = c.dataset.link || '#';
  var t = c.dataset.title || 'Promosi';
  var fb = '<a href="'+lnk+'" style="display:block;text-decoration:none"><div style="padding:32px 16px;background:linear-gradient(135deg,rgba(var(--pri-rgb),.15),rgba(var(--sec-rgb),.08));min-height:80px;display:flex;align-items:center;justify-content:center;text-align:center;border-radius:12px 12px 0 0"><div style="font-size:1rem;font-weight:800;color:var(--t)">'+t+'</div></div></a>';
  c.insertAdjacentHTML('afterbegin', fb);
}


// Rebate levels
var LEVELS=[[1,1000,0.3],[2,10000000,0.5],[3,50000000,0.6],[4,100000000,0.8],[5,500000000,1],[6,1000000000,2],[7,10000000000,3]];
function getLevel(to){var lv=0,pct=0;for(var i=LEVELS.length-1;i>=0;i--){if(to>=LEVELS[i][1]){lv=LEVELS[i][0];pct=LEVELS[i][2];break;}}return{level:lv,pct:pct}}
function getNextThresh(to){for(var i=0;i<LEVELS.length;i++){if(to<LEVELS[i][1])return LEVELS[i][1]}return LEVELS[LEVELS.length-1][1]}
function fmtK(v){return(v/1000).toLocaleString('en',{minimumFractionDigits:2,maximumFractionDigits:2})}

// Render tabs — only replace skeleton if data exists
var th='';catKeys.forEach(function(k,i){
  th+='<div class="tab-i'+(i===0?' on':'')+'" onclick="switchTab('+i+',this)">'+cats[k]+'</div>';
});
if(catKeys.length){document.getElementById('tabs').innerHTML=th;}

// Render panels — bulletproof: each panel wrapped in try/catch
var ph='';
try{
catKeys.forEach(function(k,i){
  try {
  ph+='<div class="panel'+(i===0?' on':'')+'" id="pan'+i+'">';
  if(k==='rebate'){
    if(!UID){ph+='<div class="empty"><p style="margin-bottom:12px">Silakan login untuk melihat rebate</p><a href="index.php" style="color:var(--sec);font-weight:700">Masuk / Daftar</a></div>';}
    else{ph+='<div id="rbContent"><div class="empty">Memuat rebate...</div></div>';}
  }else if(k==='vip'){
    if(!UID){ph+='<div class="empty"><p style="margin-bottom:12px">Silakan login untuk melihat VIP</p><a href="index.php" style="color:var(--sec);font-weight:700">Masuk / Daftar</a></div>';}
    else{ph+='<div id="vipContent"><div class="empty">Memuat VIP...</div></div>';}
  }else if(k==='kode'){
    if(!UID){ph+='<div class="empty"><p style="margin-bottom:12px">Silakan login untuk menukar kode</p><a href="index.php" style="color:var(--sec);font-weight:700">Masuk / Daftar</a></div>';}
    else{
      var rdBanner=SI.redeem_banner||'';
      var fbU=SI.fb_url||'';var waU=SI.wa_url||'';var igU=SI.ig_url||'';var xU=SI.x_url||SI.twitter_url||'';var tgU=SI.tg_url||'';
      var sn=SI.site_name||'Situs';

      ph+='<div class="kode-wrap">';

      // Banner redeem (dari admin setting) — kalau ga ada, hide total
      if(rdBanner && rdBanner.length > 5){
        ph+='<img src="'+rdBanner+'" style="width:100%;border-radius:12px;margin-bottom:18px;display:block;aspect-ratio:3/1;object-fit:cover" onerror="this.onerror=null;this.style.display=\'none\'">';
      }

      // Input kode
      ph+='<input type="text" id="kodeInput" placeholder="Silakan masukkan kode tukar" autocomplete="off" style="width:100%;padding:16px 18px;background:var(--s);border:1.5px solid var(--bd);border-radius:10px;color:var(--t);font-family:inherit;font-size:.92rem;outline:none;margin-bottom:16px;letter-spacing:.5px;transition:border-color .15s" onfocus="this.style.borderColor=\'var(--pri)\'" onblur="this.style.borderColor=\'var(--bd)\'">';

      // Tombol Tukar
      ph+='<button onclick="tukarKode()" style="width:100%;padding:16px;background:var(--pri);color:#fff;border:none;border-radius:10px;font-family:inherit;font-size:1rem;font-weight:800;cursor:pointer;letter-spacing:.5px;margin-bottom:8px">Tukar</button>';

      ph+='<div id="kodeMsg" style="text-align:center;font-size:.82rem;font-weight:600;margin:14px 0;display:none"></div>';

      // ═══ Sosmed sharing section ═══
      ph+='<div style="margin-top:28px;text-align:center">';
      ph+='<h3 style="font-size:1.05rem;font-weight:800;color:var(--pri);margin-bottom:8px">Perangkat lunak jejaring sosial multimedia</h3>';
      ph+='<p style="font-size:.78rem;color:var(--t3);margin-bottom:20px;line-height:1.6;padding:0 14px">Anda dapat membagikan kepada lebih banyak teman melalui cara-cara berikut</p>';

      // Icon bulat sosmed — 5 circle
      ph+='<div style="display:flex;justify-content:center;align-items:center;gap:14px;flex-wrap:wrap;margin-bottom:6px">';
      if(fbU)ph+='<a href="'+fbU+'" target="_blank" class="sosmed-ic" style="background:#1877f2"><svg viewBox="0 0 24 24" fill="#fff" width="28" height="28"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>';
      if(waU)ph+='<a href="'+waU+'" target="_blank" class="sosmed-ic" style="background:#25d366"><svg viewBox="0 0 24 24" fill="#fff" width="28" height="28"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893A11.821 11.821 0 0020.885 3.488"/></svg></a>';
      if(igU)ph+='<a href="'+igU+'" target="_blank" class="sosmed-ic" style="background:linear-gradient(45deg,#f09433 0%,#e6683c 25%,#dc2743 50%,#cc2366 75%,#bc1888 100%)"><svg viewBox="0 0 24 24" fill="#fff" width="28" height="28"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg></a>';
      if(xU)ph+='<a href="'+xU+'" target="_blank" class="sosmed-ic" style="background:#000"><svg viewBox="0 0 24 24" fill="#fff" width="24" height="24"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>';
      if(tgU)ph+='<a href="'+tgU+'" target="_blank" class="sosmed-ic" style="background:#229ED9"><svg viewBox="0 0 24 24" fill="#fff" width="28" height="28"><path d="M11.944 0A12 12 0 000 12a12 12 0 0012 12 12 12 0 0012-12A12 12 0 0012 0a12 12 0 00-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 01.171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/></svg></a>';
      ph+='</div>';
      ph+='</div>';

      // ═══ Aturan ═══
      ph+='<div style="margin-top:24px;padding:16px 18px;background:var(--s);border:1px solid var(--bd);border-radius:10px;font-size:.78rem;color:var(--t2);line-height:1.7">';
      ph+='<div style="margin-bottom:8px">1. Kode Redeem adalah program penukaran hadiah yang diselenggarakan oleh platform.</div>';
      ph+='<div style="margin-bottom:8px">2. Ikuti akun resmi Telegram, WhatsApp, Instagram, Facebook, dan Twitter '+escH(sn)+' untuk mendapatkan Kode Redeem terbaru.</div>';
      ph+='<div>3. Kode Redeem dibagikan setiap hari pada waktu yang tidak tetap. Platform juga akan mengadakan berbagai promosi menarik lainnya secara berkala.</div>';
      ph+='</div>';

      ph+='</div>';
    }
  }else{
    var items=promos[k]||[];
    if(k==='promosi'){
      // Collect existing DB promo links to avoid duplicates
      var existingLinks={};
      items.forEach(function(p){if(p.link)existingLinks[p.link]=true;if(p.title)existingLinks[p.title]=true;});
      // Show DB promos first (with or without images)
      items.forEach(function(p){
        var lnk=p.link&&p.link!='#'&&p.link!=''?p.link:'#';
        ph+='<div class="promo-card">';
        if(p.image_url){
          if(lnk!='#')ph+='<a href="'+lnk+'" style="display:block;position:relative"><img src="'+p.image_url+'" loading="lazy" onerror="promoImgFail(this)"></a>';
          else ph+='<img src="'+p.image_url+'" loading="lazy" onerror="promoImgFail(this)">';
        }
        ph+='<div class="promo-body"><div class="pb-text">'+(p.description||p.title||'')+'</div><a class="pb-btn" href="'+lnk+'">'+(p.button_text||'Proses')+'</a></div></div>';
      });
      // Show feature cards only if NOT already in DB
      var features=[
        {title:'SPIN & MENANG JUTAAN!',desc:'Putar roda keberuntungan setiap hari, hadiah hingga 5 JUTA!',link:'spin.php',btn:'Putar Sekarang'},
        {title:'NAIK LEVEL, GAJI NAIK!',desc:'Member VIP dapat gaji harian + mingguan + bulanan SELAMANYA',link:'promo.php?tab=vip',btn:'Lihat Hadiah'},
        {title:'HADIAH SPESIAL MEMBER',desc:'Apresiasi setia member! Klaim bonus besar tiap tanggal 5',link:'apresiasi.php',btn:'Ambil Hadiah'},
        {title:'BUKA PETI MISTERI',desc:'Bonus rahasia menanti! Semakin lama bermain, semakin besar hadiahnya',link:'misteri.php',btn:'Buka Sekarang'},
        {title:'KALAH? KAMI GANTI!',desc:'Dana bantuan mingguan hingga 30% dari kerugian Anda',link:'bantuan.php',btn:'Klaim Cashback'},
        {title:'GRATIS 100K EMAS!',desc:'Spin roulette gratis setiap hari, kumpulkan & tarik tunai!',link:'roulette.php',btn:'Main Gratis'},
        {title:'LOGIN = CUAN!',desc:'Masuk 7 hari berturut-turut, bonus makin besar tiap hari!',link:'checkin.php',btn:'Absen Sekarang'},
        {title:'DEPOSIT DAPAT EXTRA!',desc:'Semakin banyak deposit hari ini, semakin besar bonus tambahan!',link:'bonusdepo.php',btn:'Deposit & Klaim'},
        {title:'TARUHAN = CASHBACK!',desc:'Setiap taruhan Anda menghasilkan rebate otomatis tanpa batas',link:'promo.php?tab=rebate',btn:'Lihat Rebate'},
        {title:'AJAK TEMAN DAPAT 50K!',desc:'Bagikan link, teman daftar & deposit, Anda langsung dapat bonus!',link:'undang.php',btn:'Undang Sekarang'},
      ];
      features.forEach(function(f){
        // Skip if DB already has this link or title
        if(existingLinks[f.link]||existingLinks[f.title])return;
        ph+='<div class="promo-card"><a href="'+f.link+'" style="display:block;text-decoration:none">';
        ph+='<div style="padding:20px 16px;background:linear-gradient(135deg,rgba(var(--sec-rgb,56,189,248),.12),rgba(var(--pri-rgb,56,189,248),.08));min-height:80px;display:flex;align-items:center;justify-content:center;text-align:center">';
        ph+='<div><div style="font-size:1rem;font-weight:800;color:var(--t)">'+f.title+'</div><div style="font-size:.68rem;color:var(--t3);margin-top:4px">'+f.desc+'</div></div>';
        ph+='</div></a>';
        ph+='<div class="promo-body"><div class="pb-text">'+f.desc+'</div><a class="pb-btn" href="'+f.link+'">'+f.btn+'</a></div>';
        ph+='</div>';
      });
    }else if(!items.length){ph+='<div class="empty">Belum ada promosi untuk kategori ini</div>';}
    else{items.forEach(function(p){
      var lnk=p.link&&p.link!='#'&&p.link!=''?p.link:'#';
      ph+='<div class="promo-card" data-link="'+lnk+'" data-title="'+(p.title||'Promosi').replace(/"/g,'&quot;')+'">';
      if(p.image_url){
        if(lnk!='#')ph+='<a href="'+lnk+'" style="display:block"><img src="'+p.image_url+'" loading="lazy" onerror="promoImgFail(this)"></a>';
        else ph+='<img src="'+p.image_url+'" loading="lazy" onerror="promoImgFail(this)">';
      }else{
        ph+='<a href="'+lnk+'" style="display:block;text-decoration:none"><div style="padding:24px 16px;background:linear-gradient(135deg,rgba(var(--sec-rgb,56,189,248),.12),rgba(var(--pri-rgb,56,189,248),.08));min-height:80px;display:flex;align-items:center;justify-content:center;text-align:center"><div style="font-size:1rem;font-weight:800;color:var(--t)">'+(p.title||'Promosi')+'</div></div></a>';
      }
      ph+='<div class="promo-body">';
      ph+='<div class="pb-text">'+(p.description||p.title||'')+'</div>';
      ph+='<a class="pb-btn" href="'+lnk+'">'+(p.button_text||'Proses')+'</a>';
      ph+='</div>';
      ph+='</div>';
    })}
  }
  ph+='</div>';
  } catch(e){ ph+='<div class="panel'+(i===0?' on':'')+'" id="pan'+i+'"><div class="empty">Error loading: '+(cats[k]||k)+'</div></div>'; console.error('[promo] panel render fail:', k, e); }
});
} catch(e2){ console.error('[promo] tab loop fail:', e2); }
if(catKeys.length){document.getElementById('panels').innerHTML=ph;}

var urlTab=new URLSearchParams(location.search).get("tab");
if(urlTab){var idx=catKeys.indexOf(urlTab);if(idx>-1){var t=document.querySelectorAll(".tab-i");if(t[idx])t[idx].click()}}
function imgClick(img){
  var lnk=decodeURIComponent(img.getAttribute('data-lnk')||'#');
  var hasDetail=img.getAttribute('data-has-detail')==='1';
  if(hasDetail){
    var detail=img.parentElement.querySelector('.promo-detail');
    if(!detail)return;
    detail.classList.toggle('open');
    img.style.opacity=detail.classList.contains('open')?'0.85':'1';
  }else if(lnk&&lnk!='#'){
    window.location=lnk;
  }
}
function switchTab(i,el){
  document.querySelectorAll('.tab-i').forEach(function(t){t.classList.remove('on')});
  document.querySelectorAll('.panel').forEach(function(p){p.classList.remove('on')});
  el.classList.add('on');
  document.getElementById('pan'+i).classList.add('on');
  if(catKeys[i]==='rebate'&&!rbLoaded)loadRebate();
  if(catKeys[i]==='vip'&&!vipLoaded)loadVip();
  if(catKeys[i]==='kode'&&UID)setTimeout(loadBlogs,50);
}

// ═══ REBATE ═══
var rbLoaded=false;
function loadRebate(){
  rbLoaded=true;
  
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'rebate_info'})})
  .then(function(r){return r.json()}).then(function(d){
    if(!d.ok){document.getElementById('rbContent').innerHTML='<div class="empty">'+(d.error==='NOT_LOGGED_IN'?'Silakan <a href="index.php" style="color:var(--sec)">login</a> untuk melihat rebate.':(d.error||'Error'))+'</div>';return}
    renderRebate(d);
  }).catch(function(){document.getElementById('rbContent').innerHTML='<div class="empty">Gagal memuat. <a href="index.php" style="color:var(--sec)">Login</a> terlebih dahulu.</div>'});
}
function renderRebate(d){
  var to=d.total_turnover||0;
  var claimed=d.rebate_claimed_to||0;
  var unclaimed=Math.max(0,to-claimed);
  var info=getLevel(to);
  var rebateAmt=Math.floor(unclaimed*info.pct/100);
  var nextT=getNextThresh(to);
  var pct=Math.min(100,Math.max(0,(to/nextT)*100));

  var h='<div class="rb-box">';
  h+='<div class="rb-top"><div class="rt-l">Amount <span>'+fmtK(to)+'</span></div><div class="rt-r">Rebate <span>'+info.pct+'%</span></div></div>';
  h+='<div class="rb-claim"><div class="rc-icon"><img src="asset/coin.png" style="width:48px;height:48px;object-fit:contain" alt="coin"></div><div class="rc-amount">'+fmtK(rebateAmt)+'</div><button class="rc-btn" id="claimBtn" onclick="claimRebate()">Claim</button></div>';
  h+='<div class="rb-bar"><div class="fill" style="width:'+pct+'%"></div></div>';
  h+='<div class="rb-range"><span>'+fmtK(to)+'</span><span>'+fmtK(nextT)+'</span></div>';
  h+='<div class="rb-msg" id="rbMsg"></div></div>';

  h+='<div class="rb-tbl"><div class="rb-tbl-hdr"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg> Tabel Referensi Rebate</div><table><tr><th>Level</th><th>Jumlah Taruhan</th><th>Rebate</th></tr>';
  LEVELS.forEach(function(l){
    var active=info.level===l[0]?' class="active"':'';
    h+='<tr'+active+'><td>'+l[0]+'</td><td>'+fmtK(l[1])+'</td><td>'+l[2]+'%</td></tr>';
  });
  h+='</table></div>';

  h+='<div class="rb-desc"><h3>Keterangan Aktivitas</h3>';
  h+='<p>1. Contoh Perhitungan: Jumlah Taruhan 3,000K maka 3,000K x 0.5% = 15K (Wajib mencapai x1 Turnover sebelum PENARIKAN).</p>';
  h+='<p>2. Rebate Turnover dapat di klaim kapanpun tanpa batasan waktu. (Jumlah Taruhan akan di reset setiap 00:00:00 WIB).</p>';
  h+='<p>3. Pihak situs tetap menjaga keadilan, kejujuran dan transparan, serta memiliki penjelasan terakhir, dan berwenang untuk menghentikan/mengubah promosi tanpa pemberitahuan dulu.</p>';
  h+='</div>';

  document.getElementById('rbContent').innerHTML=h;
}
function claimRebate(){
  var btn=document.getElementById('claimBtn');btn.textContent='...';btn.disabled=true;
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'claim_rebate'})})
  .then(function(r){return r.json()}).then(function(d){
    if(d.ok){
      document.getElementById('rbMsg').innerHTML='<svg viewBox="0 0 24 24" fill="#4ade80" width="14" height="14" style="display:inline-block;vertical-align:middle"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><path d="M22 4L12 14.01 9 11.01" fill="none" stroke="#4ade80" stroke-width="2"/></svg> Saldo rebate Rp '+Number(d.amount).toLocaleString('id')+' berhasil masuk ke saldo!';
      document.getElementById('rbMsg').style.display='block';
      setTimeout(function(){rbLoaded=false;loadRebate()},1500);
    }else{btn.textContent='Claim';btn.disabled=false;showToast(d.error||'Gagal klaim')}
  });
}
// ═══ VIP ═══
var vipLoaded=false;
var VIP_TBL=[[0,0,0,0,0,0],[1,200,1,0.05,0.50,1],[2,2000,3,0.10,1,2],[3,4000,5,0.15,2,3],[4,13000,8,0.30,2.50,5],[5,45000,18,0.80,5,8],[6,88000,30,1.30,8,15],[7,180000,30,1.80,10,20],[8,380000,100,2.50,18,30],[9,680000,150,2.90,25,50],[10,1100000,200,3.90,30,60],[11,2000000,300,8.10,50,80],[12,3000000,400,13.20,60,100],[13,4000000,500,17.50,80,130],[14,5000000,600,22.20,100,160],[15,6000000,700,30.10,120,200],[16,8000000,800,35.30,150,250],[17,10000000,1000,50,200,350],[18,12000000,1200,60,250,400],[19,14000000,1400,75,300,500],[20,16000000,1600,93.60,350,550],[21,18000000,1800,109,400,1600],[22,20000000,2000,126.70,500,1800],[23,23000000,2300,145,600,2100],[24,26000000,2600,157,700,2400],[25,30000000,3000,176,800,2700],[26,35000000,3500,192,900,3000],[27,40000000,4000,205,1000,3500],[28,45000000,4500,239,1100,4000],[29,50000000,5000,292,1200,4500],[30,60000000,6000,365,1300,5000],[31,70000000,7000,443,1500,6000],[32,80000000,8000,503,1700,7000],[33,90000000,9000,543,2000,8000],[34,100000000,10000,586,2500,9000],[35,120000000,12000,658,3000,12000],[36,140000000,14000,717,3500,14000],[37,160000000,16000,851,4000,16000],[38,180000000,18000,1053,4500,18000],[39,200000000,20000,1213,5000,20000],[40,230000000,23000,1458,5500,23000],[41,260000000,26000,1637,6000,26000],[42,300000000,30000,1812,7000,30000],[43,350000000,35000,2055,8000,35000],[44,400000000,40000,2196,10000,40000],[45,500000000,50000,2563,12000,50000],[46,600000000,60000,3195,14000,60000],[47,700000000,70000,4096,16000,70000],[48,800000000,80000,4858,18000,80000],[49,900000000,90000,5705,20000,90000],[50,1000000000,100000,6298,20000,100000]];
var vipSubTab=0;
function tierColor(lv){if(lv<=4)return['#8e8e93','#c7c7cc'];if(lv<=10)return['var(--sec)','var(--sec-l, var(--pri-l))'];if(lv<=16)return['#60a5fa','#93c5fd'];if(lv<=24)return['#a78bfa','#c4b5fd'];return['#4ade80','#86efac']}
function badge(lv){var c=tierColor(lv);return '<div class="vr-badge" style="background:linear-gradient(135deg,'+c[0]+','+c[1]+')"><div class="vri" style="background:var(--bd2)"><svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16" style="display:inline-block;vertical-align:middle"><path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5zm14 3c0 .6-.4 1-1 1H6c-.6 0-1-.4-1-1v-1h14v1z"/></svg></div><div class="vrt">VIP '+lv+'</div></div>'}
function fmtD(v){return Number(v).toLocaleString('en',{minimumFractionDigits:2,maximumFractionDigits:2})}

function loadVip(){
  vipLoaded=true;
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'level_info'})})
  .then(function(r){return r.json()}).then(function(d){
    if(!d.ok){document.getElementById('vipContent').innerHTML='<div class="empty">'+(d.error==='NOT_LOGGED_IN'?'Silakan <a href="index.php" style="color:var(--sec)">login</a> untuk melihat VIP.':d.error)+'</div>';return}
    renderVip(d.level);
  }).catch(function(){document.getElementById('vipContent').innerHTML='<div class="empty">Silakan login terlebih dahulu.</div>'});
}
function renderVip(lv){
  var vl=lv.vip_level||0;var dep=lv.total_deposit||0;var depK=dep/1000;
  var nextLv=Math.min(vl+1,50);var nextReq=VIP_TBL[nextLv]?VIP_TBL[nextLv][1]:VIP_TBL[50][1];
  var curReq=VIP_TBL[vl]?VIP_TBL[vl][1]:0;
  var pct=nextReq>curReq?Math.min(100,((depK-curReq)/(nextReq-curReq))*100):100;
  var c=tierColor(vl);var cn=tierColor(nextLv);

  var h='<div class="vip-card"><div class="vc-top"><div>'+badge(vl)+'<div class="vc-label">Level saat ini</div></div><button class="vc-btn" onclick="showToast(\'Bonus kenaikan level otomatis masuk saat naik level!\')">Info</button></div>';
  h+='<div class="vc-prog"><div class="vc-prog-bar"><div class="fill" style="width:'+pct.toFixed(0)+'%"></div></div><div class="vc-next"><div class="vn-icon" style="background:linear-gradient(135deg,'+cn[0]+','+cn[1]+')"><svg viewBox="0 0 24 24" fill="currentColor" width="16" height="16" style="display:inline-block;vertical-align:middle"><path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5zm14 3c0 .6-.4 1-1 1H6c-.6 0-1-.4-1-1v-1h14v1z"/></svg></div>VIP '+nextLv+'</div></div>';
  h+='<div class="vc-info">Ketentuan promosi<br>• Aliran perlu: <span>'+fmtD(depK)+'</span> ('+fmtD(depK)+'/'+fmtD(nextReq)+')</div></div>';

  h+='<div class="vip-title">Tabel Perbandingan Tingkat VIP</div>';
  h+='<div class="vip-stabs"><div class="vip-stab on" onclick="vipSub(0,this)">Bonus kenaikan level</div><div class="vip-stab" onclick="vipSub(1,this)">Gaji Harian</div><div class="vip-stab" onclick="vipSub(2,this)">Gaji Mingguan</div><div class="vip-stab" onclick="vipSub(3,this)">Gaji Bulanan</div></div>';

  // Column header
  var cols=[['Tingkat','Bonus kenaikan level'],['Tingkat','Gaji Harian'],['Tingkat','Gaji Mingguan'],['Tingkat','Gaji Bulanan']];
  h+='<div style="display:flex;padding:10px 12px;background:rgba(var(--sec-rgb,56,189,248),.08);border-radius:8px;margin-bottom:8px"><div style="flex:1;font-size:.68rem;font-weight:700;color:var(--t3)">Tingkat</div><div style="flex:1;text-align:center;font-size:.68rem;font-weight:700;color:var(--t3)" id="vipCol1">'+cols[0][0]+'</div><div style="flex:1;text-align:right;font-size:.68rem;font-weight:700;color:var(--t3)" id="vipCol2">'+cols[0][1]+'</div></div>';

  h+='<div class="vip-rows" id="vipRows">';
  VIP_TBL.forEach(function(r){
    var isMe=r[0]===vl;var isNext=r[0]===nextLv;
    h+='<div class="vip-row'+(isMe?' on':'')+'" data-lv="'+r[0]+'">';
    h+=badge(r[0]);
    if(isNext){
      h+='<div class="vr-prog"><div>'+fmtD(r[1])+'</div><div class="vr-prog-bar"><div class="fill" style="width:'+pct.toFixed(0)+'%"></div></div><div class="vr-prog-txt">'+fmtD(depK)+'/'+fmtD(r[1])+'</div></div>';
    }else{
      h+='<div class="vr-mid" data-d="'+r[1]+'" data-h="'+r[3]+'" data-w="'+r[4]+'">'+fmtD(r[1])+'</div>';
    }
    h+='<div class="vr-val" data-b="'+r[2]+'" data-dv="'+r[3]+'" data-wv="'+r[4]+'" data-mv="'+r[5]+'">'+fmtD(r[2])+'</div>';
    h+='</div>';
  });
  h+='</div>';

  // Claim button
  h+='<button class="vip-claim-btn" onclick="claimVipBonus()">Mengambil hadiah harian</button>';

  var sn=SI.site_name||'Situs';
  h+='<div class="vip-rules"><h3>Pengantar Kegiatan</h3>';
  h+='<p><svg viewBox="0 0 24 24" fill="#fbbf24" width="14" height="14" style="display:inline-block;vertical-align:middle"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg> 1.00 sebenarnya adalah 1 K, 1K = Rp 1,000</p>';
  h+='<p>Standar kenaikan level: Memenuhi persyaratan kenaikan VIP (yaitu pengisian ulang atau taruhan yang valid memenuhi syarat), maka dapat naik ke level VIP yang sesuai dan mendapatkan bonus kenaikan level yang sesuai. Jika naik beberapa level berturut-turut, semua bonus kenaikan level dapat diperoleh, dan bonus dapat diklaim secara real-time.</p>';
  h+='<p>Bonus Harian: Dengan memenuhi persyaratan deposit harian dan taruhan yang valid untuk level saat ini, Anda dapat menerima bonus harian yang sesuai. Jika Anda naik level secara berturut-turut, hanya bonus harian level saat ini yang dapat diperoleh. Bonus waktu nyata dapat diklaim;</p>';
  h+='<p>Bonus Mingguan: Dengan memenuhi persyaratan deposit dan taruhan yang valid untuk level saat ini setiap minggu, Anda dapat menerima bonus mingguan yang sesuai.</p>';
  h+='<p>Bonus Bulanan: Dengan memenuhi persyaratan deposit dan taruhan yang valid untuk level saat ini setiap bulan, Anda dapat menerima bonus gaji bulanan yang sesuai.</p>';
  h+='<p>Waktu kedaluwarsa bonus: Bonus yang diperoleh harus diklaim secara manual. Kadaluarsa tidak diambil langsung dibatalkan</p>';
  h+='<p>Penjelasan audit: Bonus yang diberikan oleh VIP memerlukan turnover 1 kali agar dapat ditarik. Taruhan hanya dibatasi pada Slot.</p>';
  h+='<p>Pernyataan kegiatan: Fitur ini hanya terbatas untuk akun pribadi yang melakukan taruhan permainan secara normal. Dilarang menyewakan akun, melakukan taruhan tanpa risiko. Jika terbukti, platform ini berhak menghentikan login anggota dan menyita bonus serta keuntungan yang tidak sah tanpa pemberitahuan khusus.</p>';
  h+='<p>Penjelasan: Ketika anggota mengklaim hadiah VIP, platform ini secara default menganggap bahwa anggota menyetujui dan mematuhi ketentuan terkait. Platform ini memiliki hak untuk memberikan penjelasan terakhir atas aktivitas ini.</p>';
  h+='</div>';

  h+='<div style="background:rgba(74,222,128,.06);border:1px solid rgba(74,222,128,.15);border-radius:10px;padding:12px 16px;margin-bottom:16px;font-size:.75rem;color:#4ade80;text-align:center">✓ Bonus naik level otomatis dikirim ke saldo Anda</div>';

  document.getElementById('vipContent').innerHTML=h;
}
function vipSub(idx,el){
  vipSubTab=idx;
  document.querySelectorAll('.vip-stab').forEach(function(t){t.classList.remove('on')});
  el.classList.add('on');
  var cols=[['Tingkat','Bonus kenaikan level'],['Tingkat','Gaji Harian'],['Tingkat','Gaji Mingguan'],['Tingkat','Gaji Bulanan']];
  var c1=document.getElementById('vipCol1');var c2=document.getElementById('vipCol2');
  if(c1)c1.textContent=cols[idx][0];if(c2)c2.textContent=cols[idx][1];
  // Update values in rows
  document.querySelectorAll('.vip-row').forEach(function(row){
    var mid=row.querySelector('.vr-mid');var val=row.querySelector('.vr-val');
    if(mid&&val){
      if(idx===0){mid.textContent=fmtD(parseFloat(mid.dataset.d));val.textContent=fmtD(parseFloat(val.dataset.b))}
      else if(idx===1){mid.textContent=fmtD(parseFloat(mid.dataset.d));val.textContent=fmtD(parseFloat(val.dataset.dv))}
      else if(idx===2){mid.textContent=fmtD(parseFloat(mid.dataset.d));val.textContent=fmtD(parseFloat(val.dataset.wv))}
      else{mid.textContent=fmtD(parseFloat(mid.dataset.d));val.textContent=fmtD(parseFloat(val.dataset.mv))}
    }
  });
  // Update claim button
  var claimBtn=document.querySelector('.vip-claim-btn');
  if(claimBtn){
    var labels=['Klaim bonus level','Mengambil hadiah harian','Mengambil hadiah mingguan','Mengambil hadiah bulanan'];
    claimBtn.textContent=labels[idx]||labels[0];
  }
}

// ═══ KODE PENUKARAN ═══
var vipSubTab=0;
function claimVipBonus(){
  var types=['level','daily','weekly','monthly'];
  var type=types[vipSubTab]||'daily';
  var btn=document.querySelector('.vip-claim-btn');
  if(btn){btn.disabled=true;btn.textContent='Memproses...';}
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'claim_vip',type:type})})
  .then(function(r){return r.json()}).then(function(d){
    var labels=['Klaim bonus level','Mengambil hadiah harian','Mengambil hadiah mingguan','Mengambil hadiah bulanan'];
    if(btn){btn.disabled=false;btn.textContent=labels[vipSubTab]||labels[0];}
    if(d.ok)showToast('Berhasil klaim Rp '+Number(d.amount||0).toLocaleString('id'));
    else showToast(d.error||'Gagal klaim bonus');
  }).catch(function(){
    if(btn){btn.disabled=false;btn.textContent='Coba lagi';}
    showToast('Gagal terhubung');
  });
}
function tukarKode(){
  var code=document.getElementById('kodeInput').value.trim();
  var msg=document.getElementById('kodeMsg');
  if(!code){msg.style.display='block';msg.style.color='#ef4444';msg.innerHTML='Masukkan kode terlebih dahulu';return}
  msg.style.display='block';msg.style.color='var(--t2)';msg.innerHTML='Memproses...';
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'redeem_code',code:code})})
  .then(function(r){return r.json()}).then(function(d){
    if(d.ok){
      msg.style.color='#4ade80';
      msg.innerHTML='<svg viewBox="0 0 24 24" fill="#4ade80" width="14" height="14" style="display:inline-block;vertical-align:middle"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><path d="M22 4L12 14.01 9 11.01" fill="none" stroke="#4ade80" stroke-width="2"/></svg> Berhasil! Bonus Rp '+Number(d.amount).toLocaleString('id')+' masuk ke saldo.';
      document.getElementById('kodeInput').value='';
    }else{
      msg.style.color='#ef4444';
      msg.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" width="14" height="14" style="display:inline-block;vertical-align:middle"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg> '+(d.error||'Kode tidak valid');
    }
  }).catch(function(){msg.style.color='#ef4444';msg.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2" width="14" height="14" style="display:inline-block;vertical-align:middle"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg> Gagal. Silakan login terlebih dahulu.'});
}

// ═══ LOAD BLOG (info & kode terbaru dari admin) ═══
function loadBlogs(){
  var el=document.getElementById('blogList');
  if(!el)return;
  // Silent loading — ga show text memuat
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'get_blogs'})})
    .then(function(r){return r.json()}).then(function(d){
      if(!d.ok||!d.blogs||d.blogs.length===0){
        // Ga ada blog — hide total
        el.innerHTML='';
        el.style.display='none';
        return;
      }
      el.style.display='block';
      var h='<h3 style="color:var(--sec);font-size:.85rem;font-weight:700;margin:0 0 12px 4px">Info Kode Terbaru</h3>';
      d.blogs.forEach(function(b){
        var date=b.created_at?b.created_at.substring(0,10):'';
        // Auto-highlight kode 5-8 digit uppercase
        var body=(b.body||'').replace(/\b([A-Z0-9]{5,8})\b/g,function(m){
          return '<span onclick="copyCode(this,\''+m+'\')" style="display:inline-block;background:linear-gradient(135deg,var(--sec) 0%,var(--sec-d) 100%);color:#ffffff;border:none;font-family:Chakra Petch,monospace;font-weight:800;padding:2px 10px;border-radius:6px;cursor:pointer;margin:0 2px;letter-spacing:1.5px;font-size:.88rem;box-shadow:0 0 10px rgba(var(--sec-rgb),.4)" title="Klik untuk salin">'+m+'</span>';
        });
        h+='<div style="background:var(--s);border:1px solid rgba(var(--sec-rgb),.15);border-radius:10px;padding:12px 14px;margin-bottom:10px">';
        h+='<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px">';
        h+='<h4 style="font-size:.82rem;font-weight:700;color:#fff;flex:1">'+esc(b.title||'')+'</h4>';
        h+='<span style="font-size:.6rem;color:var(--t3);flex-shrink:0;margin-left:8px">'+date+'</span>';
        h+='</div>';
        h+='<div style="font-size:.74rem;color:var(--t2);line-height:1.65;word-break:break-word">'+body+'</div>';
        h+='</div>';
      });
      el.innerHTML=h;
    }).catch(function(){
      el.style.display='none';
    });
}

function esc(s){return String(s||'').replace(/[&<>"']/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]})}

function copyCode(el,code){
  // Copy ke clipboard + auto isi ke input
  try{
    var inp=document.getElementById('kodeInput');
    if(inp){inp.value=code;inp.focus();}
    if(navigator.clipboard)navigator.clipboard.writeText(code);
    // Feedback visual
    var orig=el.innerHTML;
    el.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="14" height="14" style="vertical-align:-2px;display:inline-block"><polyline points="20 6 9 17 4 12"/></svg> Tersalin';
    setTimeout(function(){el.innerHTML=orig},1200);
  }catch(e){}
}
function showToast(m){
  var ov=document.createElement('div');ov.className='toast-overlay';
  var bx=document.createElement('div');bx.className='toast-box';
  var p=document.createElement('p');p.textContent=m;
  var btn=document.createElement('button');btn.textContent='Oke';
  btn.onclick=function(){bx.remove();ov.remove()};
  bx.appendChild(p);bx.appendChild(btn);
  document.body.appendChild(ov);document.body.appendChild(bx);
  ov.onclick=function(){bx.remove();ov.remove()}
}

// Watermark logo on all promo card images
(function(){
  var logo=SI.logo_url||'';
  if(!logo)return;
  var wmSz=parseInt(SI.wm_size)||100;
  var wmBt=parseInt(SI.wm_bottom)||0;
  var wmRt=parseInt(SI.wm_right)||0;
  var wmOp=(parseInt(SI.wm_opacity)||90)/100;
  var wmSc=SI.wm_stroke||'#ffffff';
  document.querySelectorAll('.promo-card').forEach(function(card){
    var img=card.querySelector('img');
    if(!img||!img.src)return;
    var container=img.parentElement;
    if(container.tagName==='A'){container.style.position='relative';container.style.display='block'}
    else{container=card;card.style.position='relative'}
    var wm=document.createElement('img');
    wm.src=logo;
    wm.style.cssText='position:absolute;bottom:'+wmBt+'px;right:'+wmRt+'px;width:'+wmSz+'px;height:'+wmSz+'px;object-fit:contain;opacity:'+wmOp+';z-index:5;pointer-events:none;filter:drop-shadow(2px 0 0 '+wmSc+') drop-shadow(-2px 0 0 '+wmSc+') drop-shadow(0 2px 0 '+wmSc+') drop-shadow(0 -2px 0 '+wmSc+') drop-shadow(0 2px 6px rgba(0,0,0,.5))';
    wm.onerror=function(){this.remove()};
    container.appendChild(wm);
  });
})();
</script>
<?php include 'includes/credit_notify.php'; ?>
</body>
</html>
