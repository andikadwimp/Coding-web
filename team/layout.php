<?php
require_once __DIR__.'/../includes/config.php';
// Auth check
$adminUid=getUid();$isAdmin=false;
if($adminUid){try{$r=$db->prepare("SELECT role FROM users WHERE id=?");$r->execute([$adminUid]);$row=$r->fetch();if($row&&$row['role']==='admin')$isAdmin=true;}catch(Exception $e){}}
if(!$isAdmin&&basename($_SERVER['PHP_SELF'])!=='index.php'){header('Location:index.php');exit;}

function adminHeader($title='Dashboard'){
    // ─── Inject admin accent dari theme settings (ikut warna user-facing) ───
    global $db;
    $_at=['theme_primary'=>'#2563eb','theme_primary_d'=>'#1d4ed8','theme_primary_l'=>'#eff6ff'];
    try{
        $_r=$db->query("SELECT `key`,`value` FROM settings WHERE `key` IN('theme_primary','theme_primary_d','theme_primary_l')")->fetchAll(PDO::FETCH_KEY_PAIR);
        foreach($_at as $_k=>$_v)if(!empty($_r[$_k]))$_at[$_k]=$_r[$_k];
    }catch(Exception $_e){}
    if(!function_exists('admHex2Rgb')){
        function admHex2Rgb($hex){$hex=ltrim($hex,'#');if(strlen($hex)===3)$hex=$hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];if(strlen($hex)!==6||!ctype_xdigit($hex))return '37,99,235';return hexdec(substr($hex,0,2)).','.hexdec(substr($hex,2,2)).','.hexdec(substr($hex,4,2));}
        function admHexLighten($hex,$amt=.92){$hex=ltrim($hex,'#');if(strlen($hex)===3)$hex=$hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];$r=hexdec(substr($hex,0,2));$g=hexdec(substr($hex,2,2));$b=hexdec(substr($hex,4,2));$r=intval($r+(255-$r)*$amt);$g=intval($g+(255-$g)*$amt);$b=intval($b+(255-$b)*$amt);return sprintf('#%02x%02x%02x',$r,$g,$b);}
    }
    $_atRgb=admHex2Rgb($_at['theme_primary']);
    $_atSoft=admHexLighten($_at['theme_primary'],.92); // very-light tint untuk bg
?>
<!DOCTYPE html><html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1">
<title><?=$title?> - Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<style>
/* ═══ ADMIN — Editorial Minimal · accent ikut tema user-facing ═══ */
:root{
  --bg:#fafaf9;            /* warm off-white surface */
  --bg2:#ffffff;
  --bg3:#f4f4f3;           /* hover */
  --s:#fafaf9;
  --pri:#1f2937;           /* charcoal — primary action (kontras text) */
  --pri-d:#111827;
  --pri-l:#f3f4f6;
  --accent:<?=htmlspecialchars($_at['theme_primary'])?>;       /* dari theme_primary settings */
  --accent-d:<?=htmlspecialchars($_at['theme_primary_d'])?>;
  --accent-l:<?=htmlspecialchars($_atSoft)?>;
  --accent-rgb:<?=$_atRgb?>;
  --sec:<?=htmlspecialchars($_at['theme_primary'])?>;
  --sec-d:<?=htmlspecialchars($_at['theme_primary_d'])?>;
  --sec-rgb:<?=$_atRgb?>;
  --pri-rgb:31,41,55;
  --t:#0a0a0a;             /* near-black text */
  --t2:#404040;
  --t3:#737373;            /* muted */
  --t4:#a3a3a3;            /* very muted */
  --bd:#e5e5e4;            /* hairline */
  --bd2:#d4d4d3;
  --red:#dc2626;
  --green:#16a34a;
  --orange:#ea580c;
  --grad-pri:none;
  --grad-success:none;
  --grad-warn:none;
  --grad-danger:none;
  --shadow-sm:0 0 0 1px rgba(0,0,0,.04);
  --shadow:0 0 0 1px rgba(0,0,0,.06);
  --shadow-lg:0 1px 3px rgba(0,0,0,.05),0 0 0 1px rgba(0,0,0,.04);
  --radius-sm:6px;
  --radius:8px;
  --radius-lg:12px;
}
*{margin:0;padding:0;box-sizing:border-box}
html,body{background:var(--bg)}
body{font-family:'Plus Jakarta Sans',-apple-system,BlinkMacSystemFont,system-ui,sans-serif;color:var(--t);display:flex;min-height:100vh;min-height:100dvh;background:var(--bg);font-feature-settings:'cv11','ss01';-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}
::selection{background:var(--pri);color:#fff}

/* ── Sidebar ── */
.sb{width:228px;background:#fff;border-right:1px solid var(--bd);position:fixed;top:0;left:0;height:100%;z-index:50;display:flex;flex-direction:column;transition:left .25s ease}
.sb-hdr{padding:22px 20px 18px;border-bottom:1px solid var(--bd);display:flex;align-items:center;gap:11px;background:#fff;color:var(--t)}
.sb-hdr .sb-logo{width:32px;height:32px;border-radius:8px;background:var(--pri);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:.92rem;letter-spacing:-.02em}
.sb-hdr .sb-name{font-size:.92rem;font-weight:800;color:var(--t);letter-spacing:-.02em;line-height:1.2}
.sb-hdr .sb-name small{display:block;font-size:.6rem;color:var(--t3);font-weight:500;letter-spacing:.4px;text-transform:uppercase;margin-top:3px}
.sb-menu{flex:1;padding:6px 10px 10px;overflow-y:auto}
.sb-menu::-webkit-scrollbar{width:4px}.sb-menu::-webkit-scrollbar-thumb{background:var(--bd2);border-radius:4px}
.sb-sec{font-size:.6rem;font-weight:700;color:var(--t4);text-transform:uppercase;letter-spacing:1.2px;padding:18px 10px 6px}
.sb-sec:first-child{padding-top:8px}
.sb-i{display:flex;align-items:center;gap:11px;padding:8px 10px;color:var(--t2);font-size:.795rem;font-weight:550;cursor:pointer;text-decoration:none;border-radius:6px;margin-bottom:1px;transition:background .12s,color .12s;position:relative;letter-spacing:-.005em}
.sb-i:hover{background:var(--bg3);color:var(--t)}
.sb-i.on{color:var(--t);background:var(--bg3);font-weight:700}
.sb-i.on::before{content:'';position:absolute;left:-10px;top:7px;bottom:7px;width:2px;background:var(--accent);border-radius:2px}
.sb-i svg{width:15px;height:15px;flex-shrink:0;opacity:.7;color:var(--t3)}
.sb-i.on svg{opacity:1;color:var(--accent)}
.sb-i:hover svg{opacity:.95;color:var(--t2)}
.sb-foot{padding:14px 18px;border-top:1px solid var(--bd);font-size:.7rem;color:var(--t3);display:flex;gap:12px;align-items:center;font-weight:500}
.sb-foot a{font-weight:600;text-decoration:none;color:var(--t2);transition:color .12s}
.sb-foot a:hover{color:var(--accent)}

/* ── Main + Topbar ── */
.main{flex:1;margin-left:228px;min-height:100vh;position:relative;z-index:1;background:var(--bg)}
.topbar{display:flex;align-items:center;justify-content:space-between;padding:18px 32px;background:#fff;border-bottom:1px solid var(--bd);position:sticky;top:0;z-index:40}
.topbar h1{font-size:1.02rem;font-weight:800;color:var(--t);letter-spacing:-.025em}
.topbar .badge{font-size:.6rem;padding:5px 10px;border-radius:5px;background:var(--pri-l);color:var(--t2);font-weight:700;letter-spacing:.5px;text-transform:uppercase;border:1px solid var(--bd)}
.content{padding:28px 32px;max-width:1180px}

/* ── Forms ── */
.fg{margin-bottom:18px}
.fg label{display:block;font-size:.75rem;font-weight:700;color:var(--t);margin-bottom:6px;letter-spacing:-.005em}
.fg input,.fg select,.fg textarea{width:100%;padding:10px 13px;background:#fff;border:1px solid var(--bd2);border-radius:7px;color:var(--t);font-family:inherit;font-size:.85rem;font-weight:500;outline:none;transition:border-color .12s,box-shadow .12s;letter-spacing:-.005em}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(var(--accent-rgb),.12)}
.fg input::placeholder,.fg textarea::placeholder{color:var(--t4);font-weight:400}
.fg textarea{resize:vertical;min-height:80px;font-family:inherit}
.fg-row{display:flex;gap:12px}.fg-row .fg{flex:1}
.fg-help,.fg small,.fg .help-text{display:block;font-size:.7rem;color:var(--t3);margin-top:5px;line-height:1.55;font-weight:500}

/* ── Cards ── */
.card,.section-card{background:#fff;border:1px solid var(--bd);border-radius:10px;padding:22px;margin-bottom:14px}
.card h2,.card h3,.section-title{font-size:.92rem;font-weight:800;color:var(--t);margin-bottom:16px;display:flex;align-items:center;gap:8px;letter-spacing:-.015em}
.card h2 svg,.section-title svg{color:var(--t3);width:16px;height:16px}

/* ── Buttons ── */
.btn{padding:8px 14px;border-radius:7px;font-weight:600;font-size:.78rem;border:1px solid transparent;cursor:pointer;font-family:inherit;display:inline-flex;align-items:center;gap:6px;transition:background .12s,border-color .12s,color .12s;letter-spacing:-.005em;line-height:1}
.btn-pri{background:var(--pri);color:#fff;border-color:var(--pri)}
.btn-pri:hover{background:var(--pri-d);border-color:var(--pri-d)}
.btn-sec{background:#fff;color:var(--t);border-color:var(--bd2)}
.btn-sec:hover{background:var(--bg3);border-color:var(--t4)}
.btn-red{background:#fff;color:var(--red);border-color:#fecaca}
.btn-red:hover{background:#fef2f2;border-color:#f87171}
.btn-green{background:#fff;color:var(--green);border-color:#bbf7d0}
.btn-green:hover{background:#f0fdf4}
.btn-ghost{background:transparent;color:var(--t2);border-color:transparent}
.btn-ghost:hover{background:var(--bg3);color:var(--t)}
.btn-sm{padding:6px 10px;font-size:.7rem;border-radius:5px}

/* ── Tables ── */
.tbl{width:100%;border-collapse:collapse;font-size:.815rem;background:#fff;border-radius:10px;overflow:hidden;border:1px solid var(--bd);transition:border-color .2s,box-shadow .25s}
.tbl:hover{box-shadow:0 4px 14px rgba(0,0,0,.04)}
.tbl th{text-align:left;padding:11px 14px;background:var(--bg);color:var(--t3);font-size:.65rem;text-transform:uppercase;letter-spacing:.7px;font-weight:700;border-bottom:1px solid var(--bd);position:sticky;top:0;z-index:1}
.tbl td{padding:13px 14px;border-bottom:1px solid var(--bd);color:var(--t);font-weight:500;letter-spacing:-.005em;transition:background .15s,color .15s}
.tbl tr{transition:background .15s ease}
.tbl tr:hover td{background:var(--bg);color:var(--t)}
.tbl tr:last-child td{border-bottom:none}
.tbl .num,.tbl td b{font-family:'JetBrains Mono','SF Mono',monospace;font-feature-settings:'tnum';font-weight:600}
/* Custom thin scrollbar in admin */
.main *::-webkit-scrollbar,.content *::-webkit-scrollbar{width:6px;height:6px}
.main *::-webkit-scrollbar-thumb,.content *::-webkit-scrollbar-thumb{background:var(--bd2);border-radius:3px}
.main *::-webkit-scrollbar-thumb:hover,.content *::-webkit-scrollbar-thumb:hover{background:var(--t4)}

/* ── Item rows ── */
.item-row{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;background:#fff;border:1px solid var(--bd);border-radius:8px;margin-bottom:8px;transition:border-color .12s}
.item-row:hover{border-color:var(--t4)}
.item-info{display:flex;flex-direction:column;gap:2px}
.item-info b{font-size:.86rem;color:var(--t);font-weight:700;letter-spacing:-.005em}
.item-info small{font-size:.72rem;color:var(--t3);font-weight:500}

/* ── Upload zone ── */
.upload-zone{border:1.5px dashed var(--bd2);border-radius:8px;padding:22px;text-align:center;cursor:pointer;position:relative;font-size:.8rem;color:var(--t3);background:#fff;transition:border-color .12s,color .12s,background .12s;font-weight:500}
.upload-zone:hover{border-color:var(--accent);color:var(--accent);background:var(--accent-l)}
.upload-zone input{position:absolute;inset:0;opacity:0;cursor:pointer}

/* ── Stat cards (refined — tabular figures, no rainbow stripe) ── */
.stat-card{background:#fff;border:1px solid var(--bd);border-radius:10px;padding:18px 20px;position:relative;overflow:hidden;transition:border-color .12s}
.stat-card:hover{border-color:var(--t4)}
.stat-card svg{position:absolute;right:14px;top:14px;width:16px;height:16px;color:var(--t4)}
.stat-card .sv{font-family:'JetBrains Mono','SF Mono',monospace;font-size:1.45rem;font-weight:700;color:var(--t);letter-spacing:-.02em;font-feature-settings:'tnum'}
.stat-card .sl{font-size:.66rem;color:var(--t3);text-transform:uppercase;letter-spacing:.85px;font-weight:700;margin-top:4px}

/* ── Tabs ── */
.tab-bar,.tabs{display:flex;gap:2px;padding:3px;background:var(--pri-l);border:1px solid var(--bd);border-radius:8px;margin-bottom:18px}
.tab-item,.tab{flex:1;padding:8px 14px;border-radius:5px;text-align:center;font-size:.76rem;font-weight:600;color:var(--t3);cursor:pointer;transition:background .12s,color .12s;background:transparent;border:none;font-family:inherit;letter-spacing:-.005em}
.tab-item:hover,.tab:hover{color:var(--t)}
.tab-item.active,.tab.active,.tab-item.on,.tab.on{background:#fff;color:var(--t);box-shadow:0 1px 2px rgba(0,0,0,.05),0 0 0 1px rgba(0,0,0,.04)}

/* ── Mobile ── */
.mob-tog{display:none;position:fixed;top:14px;left:14px;z-index:60;width:38px;height:38px;border-radius:7px;background:#fff;border:1px solid var(--bd2);color:var(--t);align-items:center;justify-content:center;cursor:pointer;font-size:1rem}
.sb-overlay{display:none;position:fixed;inset:0;background:rgba(15,23,42,.4);z-index:45}
@media(max-width:768px){
  .sb{left:-228px}
  .sb.open{left:0}
  .sb.open~.sb-overlay{display:block}
  .main{margin-left:0}
  .mob-tog{display:flex}
  .fg-row{flex-direction:column}
  .content{padding:20px 18px}
  .topbar{padding:14px 18px 14px 60px}
}

/* ═══ ADMIN ENTER/EXIT MOTION ═══ */
@keyframes adminFadeIn{from{opacity:0;transform:translateY(6px);filter:blur(4px)}to{opacity:1;transform:translateY(0);filter:blur(0)}}
@keyframes adminPageBlur{from{opacity:0;filter:blur(8px)}to{opacity:1;filter:blur(0)}}
.content > *{animation:adminFadeIn .45s cubic-bezier(.16,1,.3,1) both}
.content > *:nth-child(1){animation-delay:.02s}
.content > *:nth-child(2){animation-delay:.07s}
.content > *:nth-child(3){animation-delay:.12s}
.content > *:nth-child(4){animation-delay:.17s}
.content > *:nth-child(5){animation-delay:.22s}
.content > *:nth-child(6){animation-delay:.27s}
.stat-card{transition:border-color .15s,transform .25s cubic-bezier(.16,1,.3,1),box-shadow .25s ease}
.stat-card:hover{transform:translateY(-2px);box-shadow:0 4px 14px rgba(0,0,0,.05)}
.btn{transition:background .15s,border-color .15s,color .15s,transform .15s ease}
.btn:active{transform:scale(.97)}
.sb-i{position:relative;overflow:hidden}
.sb-i::after{content:'';position:absolute;inset:0;background:radial-gradient(circle at center,rgba(var(--accent-rgb),.08),transparent 70%);opacity:0;transition:opacity .25s ease;pointer-events:none}
.sb-i:hover::after{opacity:1}
.tbl tr{transition:background .15s ease}
/* Admin loader (frosted glass shimmer) */
@keyframes adminShim{0%{transform:translateX(-110%)}100%{transform:translateX(310%)}}
#adminLoader{position:fixed;inset:0;background:rgba(255,255,255,.6);backdrop-filter:blur(14px) saturate(140%);-webkit-backdrop-filter:blur(14px) saturate(140%);z-index:99998;display:none;align-items:center;justify-content:center;flex-direction:column;gap:14px;opacity:0;transition:opacity .25s ease}
#adminLoader.show{display:flex;opacity:1}
#adminLoader .lx-bar{width:140px;height:2px;background:rgba(0,0,0,.08);border-radius:2px;overflow:hidden;position:relative}
#adminLoader .lx-bar::before{content:"";position:absolute;left:0;top:0;width:35%;height:100%;background:linear-gradient(90deg,transparent,var(--accent) 50%,transparent);animation:adminShim 1.4s cubic-bezier(.4,0,.2,1) infinite;border-radius:2px}
#adminLoader .lx-tag{font-size:.62rem;font-weight:700;color:var(--t3);letter-spacing:1.5px;text-transform:uppercase}

/* ═══ SIDEBAR PREMIUM POLISH — stats + badges + profile ═══ */
.sb-online-dot{display:inline-block;width:6px;height:6px;border-radius:50%;background:#10b981;box-shadow:0 0 0 2px rgba(16,185,129,.2);margin-right:4px;animation:sbOnline 2s ease-in-out infinite;vertical-align:1px}
@keyframes sbOnline{0%,100%{box-shadow:0 0 0 2px rgba(16,185,129,.2)}50%{box-shadow:0 0 0 5px rgba(16,185,129,.05)}}
.sb-hdr .sb-name small{display:flex;align-items:center;font-size:.6rem;color:var(--t3);font-weight:500;letter-spacing:.4px;text-transform:uppercase;margin-top:3px;line-height:1}
/* Stats block */
.sb-stats{padding:14px 16px;border-bottom:1px solid var(--bd);background:linear-gradient(180deg,var(--bg) 0%,#fff 100%)}
.sb-stat{margin-bottom:10px}
.sb-stat-l{font-size:.6rem;color:var(--t3);text-transform:uppercase;letter-spacing:.85px;font-weight:700;margin-bottom:3px}
.sb-stat-v{font-family:'JetBrains Mono','SF Mono',monospace;font-size:1.05rem;font-weight:700;color:var(--t);letter-spacing:-.015em;font-feature-settings:'tnum'}
.sb-stat-row{display:grid;grid-template-columns:1fr 1fr 1fr;gap:6px;padding-top:8px;border-top:1px solid var(--bd)}
.sb-stat-mini{text-align:center}
.sb-stat-mini-v{font-family:'JetBrains Mono','SF Mono',monospace;font-size:.82rem;font-weight:700;color:var(--t);letter-spacing:-.01em;line-height:1.1;font-feature-settings:'tnum'}
.sb-stat-mini-l{font-size:.55rem;color:var(--t3);text-transform:uppercase;letter-spacing:.6px;font-weight:600;margin-top:3px}
/* Sidebar item — refined to fit badge */
.sb-i{display:flex;align-items:center;gap:11px;padding:8px 10px;color:var(--t2);font-size:.795rem;font-weight:550;cursor:pointer;text-decoration:none;border-radius:6px;margin-bottom:1px;transition:background .12s,color .12s,padding-left .15s ease;position:relative;letter-spacing:-.005em}
.sb-i .sb-i-label{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sb-i:hover{background:var(--bg3);color:var(--t);padding-left:13px}
.sb-i.on{color:var(--t);background:var(--bg3);font-weight:700}
.sb-i.on::before{content:'';position:absolute;left:-10px;top:7px;bottom:7px;width:2px;background:var(--accent);border-radius:2px}
/* Badges */
.sb-badge{flex-shrink:0;font-family:'JetBrains Mono','SF Mono',monospace;font-size:.62rem;font-weight:700;padding:2px 7px;border-radius:10px;background:var(--bg3);color:var(--t2);letter-spacing:-.01em;line-height:1.4;border:1px solid var(--bd);font-feature-settings:'tnum';min-width:24px;text-align:center}
.sb-badge-soft{background:var(--bg);color:var(--t3);border:1px solid var(--bd)}
.sb-badge-info{background:var(--accent-l);color:var(--accent-d);border-color:rgba(var(--accent-rgb),.25)}
.sb-badge-urgent{background:#fef2f2;color:#dc2626;border-color:#fecaca;animation:sbPulse 1.6s ease-in-out infinite;position:relative}
.sb-badge-urgent::before{content:'';position:absolute;inset:-2px;border-radius:12px;border:1.5px solid rgba(220,38,38,.4);animation:sbPulseRing 1.6s ease-out infinite;pointer-events:none}
@keyframes sbPulse{0%,100%{transform:scale(1)}50%{transform:scale(1.05)}}
@keyframes sbPulseRing{0%{transform:scale(1);opacity:1}100%{transform:scale(1.4);opacity:0}}
/* Profile footer */
.sb-foot{padding:12px;border-top:1px solid var(--bd);background:#fff}
.sb-prof{display:flex;align-items:center;gap:10px;padding:6px;border-radius:8px;transition:background .15s ease}
.sb-prof:hover{background:var(--bg3)}
.sb-prof-av{width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,var(--pri),#374151);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:.86rem;letter-spacing:-.02em;flex-shrink:0}
.sb-prof-info{flex:1;min-width:0}
.sb-prof-name{font-size:.78rem;font-weight:700;color:var(--t);line-height:1.2;letter-spacing:-.005em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.sb-prof-role{font-size:.62rem;color:var(--t3);font-weight:500;letter-spacing:.3px;margin-top:1px}
.sb-prof-actions{display:flex;gap:4px;flex-shrink:0}
.sb-prof-actions a{width:26px;height:26px;border-radius:6px;display:flex;align-items:center;justify-content:center;color:var(--t3);text-decoration:none;transition:background .12s,color .12s}
.sb-prof-actions a:hover{background:var(--bg3);color:var(--t)}
.sb-prof-actions a:last-child:hover{color:var(--red);background:#fef2f2}
</style></head><body>
<div id="adminLoader"><div class="lx-bar"></div><div class="lx-tag">Memuat</div></div>
<script>
(function(){
  // Show admin loader on internal nav
  document.addEventListener('click',function(e){
    var a=e.target.closest('a');
    if(!a)return;
    var h=a.getAttribute('href');
    if(!h||h.startsWith('#')||h.startsWith('javascript:')||a.target==='_blank')return;
    if(h.startsWith('http')){try{var u=new URL(h);if(u.host!==location.host)return}catch(e){return}}
    var l=document.getElementById('adminLoader');if(l)l.classList.add('show');
  },true);
  // Hide on form submit
  document.addEventListener('submit',function(e){
    var f=e.target;if(f&&f.tagName==='FORM'){var l=document.getElementById('adminLoader');if(l)l.classList.add('show')}
  });
  window.addEventListener('pageshow',function(){var l=document.getElementById('adminLoader');if(l)l.classList.remove('show')});
})();
</script>

<button class="mob-tog" onclick="document.getElementById('sb').classList.toggle('open');document.getElementById('sbOv').style.display=document.getElementById('sb').classList.contains('open')?'block':'none'">&#9776;</button>
<div class="sb-overlay" id="sbOv" onclick="document.getElementById('sb').classList.remove('open');this.style.display='none'"></div>
<?php
global $db;
$sbStats=['dep_pending'=>0,'wd_pending'=>0,'wd_amt'=>0,'unread_chat'=>0,'users_today'=>0,'users_total'=>0,'vip_count'=>0,'balance_total'=>0];
try{
  $sbStats['dep_pending'] = (int)$db->query("SELECT COUNT(*) FROM deposits WHERE status='pending'")->fetchColumn();
  $sbStats['wd_pending']  = (int)$db->query("SELECT COUNT(*) FROM withdrawals WHERE status='pending'")->fetchColumn();
  $sbStats['wd_amt']      = (float)$db->query("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE status='pending'")->fetchColumn();
  $sbStats['unread_chat'] = (int)$db->query("SELECT COUNT(*) FROM cs_messages WHERE sender='user' AND is_read=0")->fetchColumn();
  $sbStats['users_today'] = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='user' AND DATE(created_at)=CURDATE()")->fetchColumn();
  $sbStats['users_total'] = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
  $sbStats['vip_count']   = (int)$db->query("SELECT COUNT(*) FROM users WHERE role='user' AND vip_level>0")->fetchColumn();
  $sbStats['balance_total'] = (float)$db->query("SELECT COALESCE(SUM(balance),0) FROM users WHERE role='user'")->fetchColumn();
}catch(Exception $e){}
function sbFmt($n){if($n>=1e9)return number_format($n/1e9,1,',','.').'B';if($n>=1e6)return number_format($n/1e6,1,',','.').'M';if($n>=1e3)return number_format($n/1e3,0,',','.').'K';return number_format($n,0,',','.');}
$adminName = '';
try{ $r=$db->prepare("SELECT username FROM users WHERE id=?"); $r->execute([$GLOBALS['adminUid']??0]); $rr=$r->fetch(); $adminName=$rr?$rr['username']:'admin'; }catch(Exception $e){}
?>
<aside class="sb" id="sb">
<div class="sb-hdr">
  <div class="sb-logo">A</div>
  <div class="sb-name">Admin Panel<small><span class="sb-online-dot"></span> Online</small></div>
</div>
<!-- Stats block -->
<div class="sb-stats">
  <div class="sb-stat">
    <div class="sb-stat-l">Saldo Sistem</div>
    <div class="sb-stat-v">Rp <?=sbFmt($sbStats['balance_total'])?></div>
  </div>
  <div class="sb-stat-row">
    <div class="sb-stat-mini">
      <div class="sb-stat-mini-v"><?=number_format($sbStats['users_total'],0,',','.')?></div>
      <div class="sb-stat-mini-l">User</div>
    </div>
    <div class="sb-stat-mini">
      <div class="sb-stat-mini-v" style="color:#d97706"><?=$sbStats['vip_count']?></div>
      <div class="sb-stat-mini-l">VIP</div>
    </div>
    <div class="sb-stat-mini">
      <div class="sb-stat-mini-v" style="color:var(--green)">+<?=$sbStats['users_today']?></div>
      <div class="sb-stat-mini-l">Hari Ini</div>
    </div>
  </div>
</div>
<div class="sb-menu">
<?php
$menu = [
    // ─── Overview ───
    ['__section__','Overview'],
    ['index.php','Dashboard','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>'],

    // ─── Transaksi ───
    ['__section__','Transaksi'],
    ['deposits.php','Deposit','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v14m-5-5l5 5 5-5"/><path d="M5 20h14"/></svg>','dep_pending'],
    ['withdrawals.php','Withdraw','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 16V2m5 5l-5-5-5 5"/><path d="M5 20h14"/></svg>','wd_pending'],
    ['bonuses.php','Bonus Deposit','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/></svg>'],
    ['payment.php','Payment Gateway','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>'],

    // ─── Member ───
    ['__section__','Member'],
    ['users.php','Users','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>','users_total_compact'],
    ['referrals.php','Referral','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>'],
    ['chat_settings.php','Live Chat','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>','unread_chat'],

    // ─── Game ───
    ['__section__','Game'],
    ['games.php','Games','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 12h4M8 10v4"/><circle cx="17" cy="10" r="1"/><circle cx="15" cy="13" r="1"/></svg>'],
    ['rtp.php','RTP Control','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/></svg>'],
    ['spin.php','Lucky Spin','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="3"/></svg>'],

    // ─── Marketing ───
    ['__section__','Marketing'],
    ['promos.php','Banner & Promo','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>'],
    ['redeems.php','Kode Redeem','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 5l-2 2m-7.61 7.61a5.5 5.5 0 11-7.778 7.778 5.5 5.5 0 017.777-7.777z"/></svg>'],
    ['notifs.php','Notifikasi','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>'],

    // ─── Sistem ───
    ['__section__','Sistem'],
    ['theme.php','Tampilan & Tema','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="13.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="10.5" r="2.5"/><circle cx="8.5" cy="7.5" r="2.5"/><circle cx="6.5" cy="12.5" r="2.5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.83 0 1.5-.67 1.5-1.5 0-.39-.15-.74-.39-1.01-.23-.26-.38-.61-.38-.99 0-.83.67-1.5 1.5-1.5H16c3.31 0 6-2.69 6-6 0-5.52-4.48-10-10-10z"/></svg>'],
    ['settings.php','Settings','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>'],
];
$cur=basename($_SERVER['PHP_SELF']);
foreach($menu as $m){
    if($m[0]==='__section__'){
        echo '<div class="sb-sec">'.htmlspecialchars($m[1]).'</div>';
        continue;
    }
    $on=$cur===$m[0]?' on':'';
    $badge='';
    if(isset($m[3])){
        $key=$m[3];
        if($key==='users_total_compact'){
            $v=$sbStats['users_total'];
            if($v>0) $badge='<span class="sb-badge sb-badge-soft">'.sbFmt($v).'</span>';
        }elseif(isset($sbStats[$key]) && $sbStats[$key]>0){
            $v=$sbStats[$key];
            $cls='sb-badge';
            if($key==='wd_pending'||$key==='dep_pending') $cls.=' sb-badge-urgent';
            elseif($key==='unread_chat') $cls.=' sb-badge-info';
            $badge='<span class="'.$cls.'">'.sbFmt($v).'</span>';
        }
    }
    echo '<a class="sb-i'.$on.'" href="'.$m[0].'">'.$m[2].'<span class="sb-i-label">'.$m[1].'</span>'.$badge.'</a>';
}
?>
</div>
<div class="sb-foot">
  <div class="sb-prof">
    <div class="sb-prof-av"><?=strtoupper(substr($adminName,0,1))?></div>
    <div class="sb-prof-info">
      <div class="sb-prof-name"><?=htmlspecialchars($adminName)?></div>
      <div class="sb-prof-role">Administrator</div>
    </div>
    <div class="sb-prof-actions">
      <a href="../dashboard.php?pwa=1" target="_blank" title="Ke Situs"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg></a>
      <a href="?logout=1" onclick="return confirm('Logout?')" title="Logout"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></a>
    </div>
  </div>
</div>
</aside>
<div class="main"><div class="topbar"><h1><?=$title?></h1><span class="badge">Admin</span></div><div class="content">
<?php } // end adminHeader

function adminFooter(){ ?>
</div></div>

<!-- ═══ GLOBAL UPLOAD HELPER ═══ -->
<!-- Pakai: <input class="upl-target" data-upl-type="banner" name="..."> di sebelahnya
     muncul tombol "📷 Unggah". Atau panggil window.admUpload(input, type) manual. -->
<style>
.upl-row{display:flex;gap:8px;align-items:stretch}
.upl-row input{flex:1}
.upl-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 13px;background:#fff;border:1px solid var(--bd2);border-radius:7px;color:var(--t);font-family:inherit;font-size:.78rem;font-weight:600;cursor:pointer;transition:background .15s,border-color .15s,color .15s,transform .12s;letter-spacing:-.005em;white-space:nowrap}
.upl-btn:hover{background:var(--accent-l);border-color:var(--accent);color:var(--accent)}
.upl-btn:active{transform:scale(.97)}
.upl-btn svg{width:14px;height:14px}
.upl-btn.busy{pointer-events:none;opacity:.6}
.upl-prev{margin-top:6px;display:flex;align-items:center;gap:8px;font-size:.7rem;color:var(--t3)}
.upl-prev img{width:48px;height:48px;border-radius:6px;object-fit:cover;border:1px solid var(--bd);background:var(--bg3)}
.upl-prev a{color:var(--accent);font-weight:600;text-decoration:none;letter-spacing:-.005em}
.upl-toast{position:fixed;bottom:18px;left:50%;transform:translateX(-50%) translateY(20px);background:var(--t);color:#fff;padding:10px 16px;border-radius:8px;font-size:.78rem;font-weight:600;z-index:10000;opacity:0;transition:opacity .25s,transform .25s cubic-bezier(.16,1,.3,1)}
.upl-toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
.upl-toast.err{background:var(--red)}
.upl-toast.ok{background:var(--green)}
</style>
<script>
window.admUpload=function(targetInput,type){
  type=type||targetInput.getAttribute('data-upl-type')||'general';
  var fi=document.createElement('input');fi.type='file';fi.accept='image/*,.gif,.png,.jpg,.jpeg,.webp,.svg,.apk,.aab';fi.style.display='none';
  document.body.appendChild(fi);
  fi.onchange=function(){
    var f=fi.files[0];if(!f){fi.remove();return;}
    if(f.size>50*1024*1024){admToast('File terlalu besar (max 50MB)','err');fi.remove();return;}
    var btn=targetInput.parentElement.querySelector('.upl-btn');var orig=btn?btn.innerHTML:'';
    if(btn){btn.classList.add('busy');btn.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10" stroke-dasharray="60" stroke-dashoffset="20"><animateTransform attributeName="transform" type="rotate" from="0 12 12" to="360 12 12" dur="1s" repeatCount="indefinite"/></circle></svg> Mengunggah';}
    var fd=new FormData();fd.append('file',f);fd.append('type',type);fd.append('action','upload');
    fetch('/api/admin.php?action=upload',{method:'POST',body:fd,credentials:'same-origin'})
      .then(function(r){return r.json();}).then(function(d){
        if(btn){btn.classList.remove('busy');btn.innerHTML=orig;}
        if(d&&d.ok&&d.url){
          targetInput.value=d.url;
          targetInput.dispatchEvent(new Event('input',{bubbles:true}));
          targetInput.dispatchEvent(new Event('change',{bubbles:true}));
          admUpdatePreview(targetInput);
          admToast('Berhasil diunggah','ok');
        }else{admToast('Gagal: '+(d&&d.error||'unknown'),'err');}
      }).catch(function(e){
        if(btn){btn.classList.remove('busy');btn.innerHTML=orig;}
        admToast('Error: '+e.message,'err');
      }).finally(function(){fi.remove();});
  };
  fi.click();
};
window.admUpdatePreview=function(input){
  var url=input.value.trim();
  var prev=input.parentElement.parentElement.querySelector('.upl-prev');
  // Resolusi rekomendasi berdasarkan name input
  var n=((input.name||'')+' '+(input.id||'')).toLowerCase();
  var dim='Belum ada gambar';var label='Gambar';
  if(/favicon/.test(n)){dim='64×64px';label='Favicon';}
  else if(/pwa.?icon|icon_192/.test(n)){dim='192×192px';label='PWA Icon';}
  else if(/icon_512|appicon/.test(n)){dim='512×512px';label='App Icon';}
  else if(/^logo|logo_url|footer_logo/.test(n)){dim='400×120px (atau 4:1)';label='Logo';}
  else if(/banner|hero|cover|undang_banner|promo_image|misteri_banner/.test(n)){dim='1200×400px (atau 3:1)';label='Banner';}
  else if(/avatar|profile_pic/.test(n)){dim='200×200px (1:1)';label='Avatar';}
  else if(/qr|qris/.test(n)){dim='400×400px (1:1)';label='QR Code';}
  else if(/provider/.test(n)){dim='80×80px (1:1)';label='Provider Logo';}
  else if(/promo|p\d+/.test(n)){dim='800×400px (atau 2:1)';label='Promo Image';}
  else if(/bg|background/.test(n)){dim='1920×1080px';label='Background';}
  if(!url){
    if(!prev){prev=document.createElement('div');prev.className='upl-prev';input.parentElement.parentElement.appendChild(prev);}
    prev.innerHTML='<div class="img-placeholder" style="width:48px;height:48px;border-radius:8px;padding:4px;flex-shrink:0;border-width:1.5px"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" style="width:16px;height:16px;margin:0;opacity:.6"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg></div><div><div style="font-weight:700;color:var(--t2);font-size:.72rem;line-height:1.3">'+label+' belum diunggah</div><div style="font-size:.62rem;color:var(--t3);font-family:\'Chakra Petch\',monospace;letter-spacing:.4px;margin-top:1px">Rekomendasi: '+dim+'</div></div>';
    return;
  }
  if(!prev){prev=document.createElement('div');prev.className='upl-prev';input.parentElement.parentElement.appendChild(prev);}
  prev.innerHTML='<img src="'+url.replace(/"/g,'&quot;')+'" onerror="this.style.display=\'none\'"><a href="'+url.replace(/"/g,'&quot;')+'" target="_blank">Buka</a><span style="font-size:.6rem;color:var(--t3);margin-left:auto;font-family:\'Chakra Petch\',monospace">'+label+'</span>';
};
window.admToast=function(msg,kind){
  var t=document.getElementById('admToast');if(!t){t=document.createElement('div');t.id='admToast';t.className='upl-toast';document.body.appendChild(t);}
  t.className='upl-toast '+(kind||'');t.textContent=msg;
  setTimeout(function(){t.classList.add('show');},10);
  clearTimeout(window.__admToastT);
  window.__admToastT=setTimeout(function(){t.classList.remove('show');},2400);
};
// Auto-wire: input dengan class .upl-target ATAU name yg cocok pattern image/logo/banner
(function(){
  function isUploadField(input){
    if(input.tagName!=='INPUT')return false;
    if(input.type==='file'||input.type==='hidden'||input.type==='submit'||input.type==='button')return false;
    if(input.classList.contains('upl-target'))return true;
    if(input.hasAttribute('data-upload'))return true;
    if(input.classList.contains('no-upload'))return false; // opt-out
    var n=((input.name||'')+' '+(input.id||'')).toLowerCase();
    if(/(image_url|logo|banner|favicon|^icon$|background|bg_image|image$|qr_url|^url$|cover_url|thumb_url|avatar)/.test(n))return true;
    return false;
  }
  function wire(input){
    if(input.dataset.uplWired)return;
    input.dataset.uplWired='1';
    // Wrap input dengan .upl-row kalau belum
    var par=input.parentElement;
    if(!par.classList.contains('upl-row')){
      var row=document.createElement('div');row.className='upl-row';
      par.insertBefore(row,input);row.appendChild(input);
    }
    var btn=document.createElement('button');btn.type='button';btn.className='upl-btn';
    btn.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg> Unggah';
    btn.onclick=function(){window.admUpload(input)};
    input.parentElement.appendChild(btn);
    admUpdatePreview(input); // always call — shows placeholder when empty
    input.addEventListener('input',function(){admUpdatePreview(input);});
  }
  function scan(){document.querySelectorAll('input').forEach(function(i){if(isUploadField(i))wire(i);});}
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',scan);else scan();
  // Re-scan when content changes (mutation observer light)
  var obs=new MutationObserver(function(muts){for(var m of muts){if(m.addedNodes.length){scan();break;}}});
  obs.observe(document.body,{childList:true,subtree:true});
})();
</script>
</body></html>
<?php } // end adminFooter

// Logout
if(isset($_GET['logout'])){
    $token=$_COOKIE['lx_token']??'';
    if($token){try{$db->prepare('UPDATE users SET auth_token=NULL WHERE auth_token=?')->execute([$token]);}catch(Exception $e){}}
    setcookie('lx_token','',time()-3600,'/');
    header('Location:index.php');exit;
}

// Upload helper
function adminUpload(){
    if(empty($_FILES['file']))return['ok'=>false,'error'=>'No file'];
    $f=$_FILES['file'];$ext=strtolower(pathinfo($f['name'],PATHINFO_EXTENSION));
    if(!in_array($ext,['jpg','jpeg','png','gif','webp','svg']))return['ok'=>false,'error'=>'Format tidak didukung'];
    $dir=__DIR__.'/../asset/uploads/';if(!is_dir($dir))mkdir($dir,0755,true);
    $name=uniqid().'.'.$ext;move_uploaded_file($f['tmp_name'],$dir.$name);
    return['ok'=>true,'url'=>'asset/uploads/'.$name];
}
