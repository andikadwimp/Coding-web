<?php
require_once 'includes/config.php';
header('Content-Type: text/html; charset=UTF-8');

// Entry sticky: user yg landing di download.php (dari iklan TikTok) → cookie lx_entry=download
// Jangan override kalau user udah pernah pake invite (invite prioritas tertinggi)
if(empty($_COOKIE['lx_entry'])||$_COOKIE['lx_entry']==='gate'){
    setcookie('lx_entry','download',time()+2592000,'/','',false,false);
}

$sets=[];
try{$r=$db->query("SELECT `key`,`value` FROM settings");foreach($r->fetchAll() as $row)$sets[$row['key']]=$row['value'];}catch(Exception $e){}
$sn   = $sets['site_name'] ?? 'Situs';
$logo = $sets['logo_url']  ?? '';
$host = $_SERVER['HTTP_HOST'] ?? '';
$shots=[
  ['image_url'=>'asset/promo/p1.png'],
  ['image_url'=>'asset/promo/p2.png'],
  ['image_url'=>'asset/promo/p3.png'],
  ['image_url'=>'asset/promo/p4.png'],
  ['image_url'=>'asset/promo/p5.png'],
  ['image_url'=>'asset/promo/p6.jpg'],
];
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<script>
(function(){
  if(window.matchMedia('(display-mode: standalone)').matches ||
     window.navigator.standalone === true ||
     document.referrer.indexOf('android-app://') === 0){
    document.cookie='pwa_app=1;path=/;max-age=31536000;SameSite=Lax';
    location.replace('/index.php');
  }
})();
</script>
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<title><?=htmlspecialchars($sn)?> - Unduh Aplikasi</title>
<link rel="manifest" href="/manifest.php">
<meta name="theme-color" content="#fff">
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
body{font-family:Roboto,'Noto Sans','Helvetica Neue',Arial,sans-serif;background:#fff;color:#202124;max-width:480px;margin:0 auto;font-size:14px;overflow-x:hidden;padding-bottom:56px}

/* ─ Header ─ */
.gp-top{padding:16px 16px 0;display:flex;gap:16px;align-items:flex-start}
.gp-logo{width:64px;height:64px;border-radius:16px;flex-shrink:0;object-fit:cover;background:#eee}
.gp-info h1{font-size:20px;font-weight:400;color:#202124;line-height:1.3;margin-bottom:2px}
.gp-dev{font-size:13px;color:#1a73e8;font-weight:400}
.gp-verified{font-size:11px;color:#5f6368;display:flex;align-items:center;gap:3px;margin-top:2px}
.gp-verified svg{width:12px;height:12px}

/* ─ Stats ─ */
.gp-stats{display:flex;padding:16px 16px 0;border-bottom:1px solid #e0e0e0}
.gp-stat{flex:1;text-align:center;padding-bottom:12px;border-right:1px solid #e0e0e0}
.gp-stat:last-child{border:none}
.gp-sv{font-size:13px;font-weight:500;color:#202124;display:flex;align-items:center;justify-content:center;gap:2px;line-height:1.2}
.gp-sv svg{width:12px;height:12px;fill:#fabb05}
.gp-sl{font-size:11px;color:#5f6368;margin-top:2px}

/* ─ Install ─ */
.gp-inst{padding:16px 16px 0}
.gp-ibtn-row{display:flex;gap:8px;margin-bottom:6px}
.gp-ibtn{flex:1;height:36px;background:#1558d6;color:#fff;border:none;border-radius:18px;font-size:14px;font-weight:400;cursor:pointer;font-family:inherit;position:relative;overflow:hidden;transition:background .2s}
.gp-ibtn:active{background:#1149b5}
.gp-ibtn.loading{background:#c2d3fb;color:#1558d6}
.gp-ibtn.done{background:#c2d3fb;color:#1558d6}
.gp-idd{width:36px;height:36px;background:#e8f0fe;border:none;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0}
.gp-idd svg{fill:#1558d6}
.gp-inote{font-size:11px;color:#5f6368;margin-bottom:12px}

/* ─ Actions ─ */
.gp-act{display:flex;padding:8px 0 12px;border-bottom:1px solid #e0e0e0;margin:0 16px}
.gp-abtn{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;font-size:11px;color:#1a73e8;cursor:pointer}
.gp-abtn svg{width:20px;height:20px;fill:#1a73e8}

/* ─ Screenshots ─ */
.gp-shots{padding:16px 0 0 16px;display:flex;gap:8px;overflow-x:auto;scrollbar-width:none;border-bottom:1px solid #e0e0e0;padding-bottom:16px}
.gp-shots::-webkit-scrollbar{display:none}
.gp-shot{width:130px;height:220px;border-radius:8px;overflow:hidden;flex-shrink:0;border:1px solid #e0e0e0;background:#f8f9fa;display:flex;align-items:center;justify-content:center}
.gp-shot img{width:100%;height:100%;object-fit:cover}
.gp-shot-ph{font-size:11px;color:#9aa0a6;display:flex;flex-direction:column;align-items:center;gap:6px}
.gp-shot-ph svg{width:28px;height:28px;fill:#dadce0}

/* ─ Section ─ */
.gp-sec{padding:16px 16px 0;border-bottom:1px solid #e0e0e0;padding-bottom:16px}
.gp-sec-title{font-size:16px;font-weight:400;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center}
.gp-sec-arrow{width:20px;height:20px;fill:#5f6368}
.gp-desc{font-size:13px;color:#202124;line-height:1.6}
.gp-tags{display:flex;flex-wrap:wrap;gap:6px;margin-top:10px}
.gp-tag{padding:4px 12px;border:1px solid #dadce0;border-radius:12px;font-size:12px;color:#5f6368}

/* ─ Data safety ─ */
.ds-card{border:1px solid #dadce0;border-radius:8px;padding:12px;margin-top:8px}
.ds-row{display:flex;align-items:flex-start;gap:10px;padding:8px 0;border-bottom:1px solid #f1f3f4}
.ds-row:last-child{border:none;padding-bottom:0}
.ds-row svg{width:20px;height:20px;fill:#5f6368;flex-shrink:0;margin-top:1px}
.ds-row p{font-size:12px;color:#3c4043;line-height:1.5}
.ds-link{color:#1a73e8;font-size:12px;margin-top:8px;display:block}

/* ─ Ratings ─ */
.rt-row{display:flex;gap:16px;align-items:center;margin-bottom:16px}
.rt-big{font-size:48px;font-weight:300;color:#202124;line-height:1}
.rt-info{flex:1}
.rt-stars{display:flex;gap:2px;margin:3px 0}
.rt-stars svg{width:14px;height:14px;fill:#1a73e8}
.rt-count{font-size:11px;color:#5f6368}
.rt-bar{display:flex;align-items:center;gap:6px;margin-bottom:3px}
.rt-bar span{font-size:11px;color:#5f6368;width:8px}
.rt-btrack{flex:1;height:4px;background:#e0e0e0;border-radius:2px}
.rt-bfill{height:100%;background:#1a73e8;border-radius:2px}
.rt-tabs{display:flex;gap:8px;margin-bottom:16px}
.rt-tab{padding:6px 12px;border:1px solid #dadce0;border-radius:16px;font-size:12px;cursor:pointer;display:flex;align-items:center;gap:5px;color:#3c4043}
.rt-tab.active{background:#e8f0fe;border-color:#1a73e8;color:#1a73e8}
.rt-tab svg{width:14px;height:14px}
/* review card */
.rv{margin-bottom:20px}
.rv-head{display:flex;align-items:center;gap:8px;margin-bottom:6px}
.rv-av{width:32px;height:32px;border-radius:50%;background:#e8f0fe;color:#1a73e8;font-size:13px;font-weight:500;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.rv-name{font-size:13px;font-weight:400;flex:1}
.rv-menu svg{width:18px;height:18px;fill:#5f6368}
.rv-stars{display:flex;gap:1px;margin-bottom:4px}
.rv-stars svg{width:12px;height:12px;fill:#1a73e8}
.rv-date{font-size:11px;color:#5f6368}
.rv-text{font-size:13px;color:#202124;line-height:1.5;margin-bottom:6px}
.rv-helpful{font-size:11px;color:#5f6368;margin-bottom:4px}
.rv-vote{display:flex;align-items:center;gap:6px;font-size:11px;color:#5f6368}
.rv-ybtn{padding:4px 14px;border:1px solid #dadce0;border-radius:10px;background:#fff;font-size:12px;color:#3c4043;cursor:pointer}
.rv-more{color:#1a73e8;font-size:13px;margin-top:4px;display:block}

/* ─ Similar games ─ */
.sim-item{display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #f1f3f4}
.sim-item:last-child{border:none}
.sim-icon{width:48px;height:48px;border-radius:10px;object-fit:cover;background:#eee;flex-shrink:0}
.sim-info{flex:1;min-width:0}
.sim-name{font-size:13px;color:#202124;font-weight:400;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sim-dev{font-size:11px;color:#5f6368;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.sim-rat{font-size:11px;color:#5f6368;display:flex;align-items:center;gap:2px}
.sim-rat svg{width:10px;height:10px;fill:#5f6368}

/* ─ Footer ─ */
.gp-footer{padding:16px;font-size:12px;color:#5f6368}
.gp-footer a{color:#5f6368;text-decoration:none;display:block;margin-bottom:8px}
.gp-footer-group{margin-bottom:14px}
.gp-footer-group b{font-size:11px;font-weight:500;color:#202124;text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:6px}
.gp-footer-links{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:8px}
.gp-footer-links a{color:#5f6368;font-size:12px;text-decoration:none}
.gp-flag{display:flex;align-items:center;gap:6px;margin-top:12px;font-size:12px}
.gp-flag img{width:20px;height:14px;border-radius:2px}

/* ─ Bottom nav ─ */
.gp-nav{position:fixed;bottom:0;left:0;right:0;background:#fff;border-top:1px solid #e0e0e0;display:flex;max-width:480px;margin:0 auto;z-index:10}
.gp-ni{flex:1;display:flex;flex-direction:column;align-items:center;padding:8px 0 6px;gap:3px;font-size:10px;color:#5f6368;cursor:pointer}
.gp-ni.active{color:#1558d6}
.gp-ni svg{width:22px;height:22px}

/* ─ Confirm overlay ─ */
#overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:100;align-items:flex-end;justify-content:center}
#overlay.open{display:flex}
.ov-sheet{background:#fff;border-radius:20px 20px 0 0;width:100%;max-width:480px;padding:20px 20px 32px}
.ov-handle{width:32px;height:4px;background:#dadce0;border-radius:2px;margin:0 auto 18px}
.ov-app{display:flex;align-items:center;gap:14px;margin-bottom:20px}
.ov-app img{width:54px;height:54px;border-radius:14px;object-fit:cover}
.ov-app-name{font-size:17px;font-weight:400;color:#202124}
.ov-install{width:100%;padding:12px;background:#1558d6;border:none;border-radius:24px;color:#fff;font-size:14px;cursor:pointer;font-family:inherit;margin-bottom:8px}
.ov-cancel{width:100%;padding:10px;background:transparent;border:none;font-size:13px;color:#5f6368;cursor:pointer;font-family:inherit}

/* ─ Progress ─ */
#progress{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:200;align-items:center;justify-content:center}
#progress.show{display:flex}
.pr-box{background:#fff;border-radius:16px;width:calc(100% - 48px);max-width:280px;padding:24px 20px}
.pr-head{display:flex;align-items:center;gap:10px;margin-bottom:20px}
.pr-icon{width:32px;height:32px;background:linear-gradient(135deg,#1a73e8,#4fc3f7);border-radius:50%;display:flex;align-items:center;justify-content:center}
.pr-icon svg{fill:#fff;width:16px;height:16px}
.pr-title{font-size:16px;font-weight:400;color:#202124}
/* phase1 circular */
.pr-circ{display:flex;justify-content:center;align-items:center;height:90px}
.pr-circ-wrap{position:relative;width:72px;height:72px}
.pr-circ-wrap svg{width:72px;height:72px;transform:rotate(-90deg)}
.pr-circ-wrap circle.t{fill:none;stroke:#e0e0e0;stroke-width:5}
.pr-circ-wrap circle.p{fill:none;stroke:#1a73e8;stroke-width:5;stroke-linecap:round;stroke-dasharray:188;stroke-dashoffset:188}
.pr-pct{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:500;color:#1a73e8}
/* phase2 bar */
.pr-p2{display:none}
.pr-badge{background:#e8f5e9;border-radius:20px;padding:8px 16px;display:flex;align-items:center;justify-content:center;gap:6px;margin-bottom:10px}
.pr-badge svg{fill:#34a853;width:16px;height:16px}
.pr-badge span{font-size:13px;color:#34a853;font-weight:500}
.pr-bar{background:#e0e0e0;border-radius:20px;height:28px;overflow:hidden;position:relative}
.pr-bar-fill{height:100%;background:#1558d6;border-radius:20px;width:0%;transition:width .08s}
.pr-bar-pct{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:500;color:#fff}
.pr-install-btn{width:100%;padding:11px;background:#1558d6;border:none;border-radius:20px;color:#fff;font-size:14px;cursor:pointer;font-family:inherit;margin-top:10px;display:none}
</style>
</head>
<body>
<!-- Header -->
<div class="gp-top">
  <img class="gp-logo" id="appLogo"
    src="<?=htmlspecialchars($logo)?>"
    onerror="this.onerror=null;this.src='https://via.placeholder.com/64/1558d6/ffffff?text=<?=urlencode(strtoupper(substr($sn,0,2)))?>'">
  <div class="gp-info">
    <h1><?=htmlspecialchars($sn)?>.COM</h1>
    <div class="gp-dev"><?=htmlspecialchars($sn)?>.COM LTD CO.</div>
    <div class="gp-verified">
      <svg viewBox="0 0 24 24" fill="#34a853"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
      Telah di Verifikasi
    </div>
  </div>
</div>

<!-- Stats -->
<div class="gp-stats">
  <div class="gp-stat">
    <div class="gp-sv">4.9<svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg></div>
    <div class="gp-sl">46K Ulasan</div>
  </div>
  <div class="gp-stat">
    <div class="gp-sv">50 k+</div>
    <div class="gp-sl">Unduhan</div>
  </div>
  <div class="gp-stat">
    <div class="gp-sv"><span style="border:1px solid #34a853;color:#34a853;font-size:11px;padding:1px 5px;border-radius:3px">L</span></div>
    <div class="gp-sl">Untuk umur 18+</div>
  </div>
</div>

<!-- Install button -->
<div class="gp-inst">
  <div class="gp-ibtn-row">
    <button class="gp-ibtn loading" id="installBtn" onclick="doInstall()">Install Aplikasi</button>
    <button class="gp-idd" onclick="doInstall()"><svg viewBox="0 0 24 24" width="18" height="18"><path d="M7 10l5 5 5-5z"/></svg></button>
  </div>
  <div class="gp-inote" id="installNote">Instal di ponsel. Tersedia perangkat lainnya.</div>
</div>

<!-- Actions -->
<div class="gp-act">
  <div class="gp-abtn" onclick="doShare()">
    <svg viewBox="0 0 24 24"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16c-.05.21-.08.43-.08.65 0 1.61 1.31 2.92 2.92 2.92 1.61 0 2.92-1.31 2.92-2.92s-1.31-2.92-2.92-2.92z"/></svg>
    Bagikan
  </div>
  <div class="gp-abtn">
    <svg viewBox="0 0 24 24"><path fill-rule="evenodd" d="M7 3h10c1.1 0 2 .9 2 2v16l-8-4-8 4V5c0-1.1.9-2 2-2zm5 12.82l5 2.18V5H7v13l5-2.18zM13 7v2h2v2h-2v2h-2v-2H9V9h2V7h2z"/></svg>
    Tambah ke daftar keinginan
  </div>
</div>

<!-- Screenshots -->
<div class="gp-shots">
  <?php if($shots): foreach($shots as $sc): ?>
  <div class="gp-shot"><img src="<?=htmlspecialchars($sc['image_url'])?>" loading="lazy"></div>
  <?php endforeach; else: for($i=0;$i<3;$i++): ?>
  <div class="gp-shot"><div class="gp-shot-ph"><svg viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>Screenshot</div></div>
  <?php endfor; endif; ?>
</div>

<!-- About -->
<div class="gp-sec">
  <div class="gp-sec-title">Tentang game ini <svg class="gp-sec-arrow" viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg></div>
  <div class="gp-desc">Situs Slot Casino Online Terpopuler dengan serunya suara putaran slot, cahaya dan grafik yang memukau, dan serunya kemenangan! Kami menghadirkan keseruan slot kasino Las Vegas dan Makau di ujung jari Anda! Game ini juga menghadirkan suara dan visual kasino yang akan membuat kemenangan Anda melonjak! Semakin sering Anda bermain, semakin banyak kemenangan Anda!</div>
  <div style="font-size:11px;color:#5f6368;margin-top:10px"><b>Updated on</b><br>Feb 01, 2026</div>
  <div class="gp-tags"><span class="gp-tag">Kasino</span><span class="gp-tag">Slot</span><span class="gp-tag">Single player</span><span class="gp-tag">Bergaya unik</span></div>
</div>

<!-- Data Safety -->
<div class="gp-sec">
  <div class="gp-sec-title">Keamanan Data <svg class="gp-sec-arrow" viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg></div>
  <p style="font-size:12px;color:#3c4043;line-height:1.6;margin-bottom:8px">Keamanan dimulai dengan memahami cara developer mengumpulkan dan membagikan data Anda. Praktik privasi dan keamanan data dapat bervariasi berdasarkan penggunaan, wilayah, dan usia Anda.</p>
  <div class="ds-card">
    <div class="ds-row"><svg viewBox="0 0 24 24"><path d="M18 16.08c-.76 0-1.44.3-1.96.77L8.91 12.7c.05-.23.09-.46.09-.7s-.04-.47-.09-.7l7.05-4.11c.54.5 1.25.81 2.04.81 1.66 0 3-1.34 3-3s-1.34-3-3-3-3 1.34-3 3c0 .24.04.47.09.7L8.04 9.81C7.5 9.31 6.79 9 6 9c-1.66 0-3 1.34-3 3s1.34 3 3 3c.79 0 1.5-.31 2.04-.81l7.12 4.16z"/></svg><p>Aplikasi ini dapat membagikan jenis data ini kepada pihak ketiga Lokasi, Info pribadi, dan 4 lainnya<br><a href="#" class="ds-link">Learn more about how developers declare sharing</a></p></div>
    <div class="ds-row"><svg viewBox="0 0 24 24"><path d="M19.35 10.04A7.49 7.49 0 0012 4C9.11 4 6.6 5.64 5.35 8.04A5.994 5.994 0 000 14c0 3.31 2.69 6 6 6h13c2.76 0 5-2.24 5-5 0-2.64-2.05-4.78-4.65-4.96z"/></svg><p>Aplikasi ini dapat mengumpulkan jenis data berikut Lokasi, Info pribadi, dan 6 lainnya</p></div>
    <div class="ds-row"><svg viewBox="0 0 24 24"><path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/></svg><p>Data dienkripsi saat dalam pengiriman</p></div>
    <div class="ds-row"><svg viewBox="0 0 24 24"><path d="M6 19c0 1.1.9 2 2 2h8c1.1 0 2-.9 2-2V7H6v12zm2.46-7.12l1.41-1.41L12 12.59l2.12-2.12 1.41 1.41L13.41 14l2.12 2.12-1.41 1.41L12 15.41l-2.12 2.12-1.41-1.41L10.59 14l-2.13-2.12zM15.5 4l-1-1h-5l-1 1H5v2h14V4z"/></svg><p>Penghapusan akun tersedia</p></div>
    <a href="#" class="ds-link" style="display:block;margin-top:4px">Lihat Detail</a>
  </div>
</div>

<!-- Ratings & Reviews -->
<div class="gp-sec">
  <div class="gp-sec-title">Rating dan ulasan <svg class="gp-sec-arrow" viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg></div>
  <p style="font-size:11px;color:#5f6368;margin-bottom:12px">Rating dan ulasan diverifikasi dan berasal dari orang yang menggunakan jenis perangkat yang sama dengan yang Anda gunakan !</p>
  <div class="rt-tabs">
    <div class="rt-tab active"><svg viewBox="0 0 24 24"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="12" y1="18" x2="12" y2="18" stroke-width="2" stroke="currentColor"/></svg>Handphone</div>
    <div class="rt-tab"><svg viewBox="0 0 24 24"><rect x="2" y="5" width="20" height="14" rx="2"/></svg>Tablet</div>
  </div>
  <div class="rt-row">
    <div style="text-align:center">
      <div class="rt-big">4,9</div>
      <div class="rt-stars"><?php for($i=0;$i<5;$i++): ?><svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><?php endfor;?></div>
      <div class="rt-count">1.91K reviews</div>
    </div>
    <div style="flex:1">
      <?php foreach([['5',93],['4',4],['3',1],['2',1],['1',1]] as [$n,$w]): ?>
      <div class="rt-bar"><span><?=$n?></span><div class="rt-btrack"><div class="rt-bfill" style="width:<?=$w?>%"></div></div></div>
      <?php endforeach; ?>
    </div>
  </div>
  <!-- Reviews -->
  <?php foreach([
    ['L','Lara Liras','September 5, 2024','Game nya real, permainannya bagus, tidak pernah ada kendala, sangat bagus untuk bermain sama teman dan bersenang-senang','84'],
    ['D','Dewi Sandra','August 19, 2024','Sangat seru mainnya, jackpotnya benar-benar ada. Bonusnya juga sangat banyak.','60'],
    ['A','Andika Hengky','June 9, 2024','Game yang pertama kali saya main tanpa iklan, lebih baiknya lagi prosesnya juga sangat cepat','18'],
  ] as [$av,$name,$date,$text,$likes]): ?>
  <div class="rv">
    <div class="rv-head"><div class="rv-av"><?=$av?></div><div class="rv-name"><?=$name?></div><div class="rv-menu"><svg viewBox="0 0 24 24"><path d="M12 8c1.1 0 2-.9 2-2s-.9-2-2-2-2 .9-2 2 .9 2 2 2zm0 2c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2zm0 6c-1.1 0-2 .9-2 2s.9 2 2 2 2-.9 2-2-.9-2-2-2z"/></svg></div></div>
    <div class="rv-stars"><?php for($i=0;$i<5;$i++): ?><svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><?php endfor;?>
    <span class="rv-date" style="margin-left:6px"><?=$date?></span></div>
    <div class="rv-text"><?=$text?></div>
    <div class="rv-helpful"><?=$likes?> orang merasa ulasan ini berguna.</div>
    <div class="rv-vote">Apakah ulasan ini membantu? <button class="rv-ybtn">Ya</button><button class="rv-ybtn">Tidak</button></div>
  </div>
  <?php endforeach; ?>
  <a href="#" class="rv-more">Lihat semua ulasan</a>
</div>

<!-- What's new -->
<div class="gp-sec">
  <div class="gp-sec-title">Apa yang baru</div>
  <div class="gp-desc">Halo Pemain Slot Sejati<br>Update terbaru:<br><br>- Meningkatkan perfoma permainan<br><br>Selamat menikmati permainan !</div>
</div>

<!-- Similar games -->
<div class="gp-sec">
  <div class="gp-sec-title">Game serupa <svg class="gp-sec-arrow" viewBox="0 0 24 24"><path d="M10 6L8.59 7.41 13.17 12l-4.58 4.59L10 18l6-6z"/></svg></div>
  <?php
  $sims=[
    ['Cash Craze: Casino Slots','Casual Joy Games','4.5','https://play-lh.googleusercontent.com/R-vnEPSmYsHxJQk1DmLqYSR3tlE0iUmUb3uOEFv_bR7fBqWqxqhFfpuFEf_jFDpjzQ=s96-rw'],
    ['Charge Buffalo Slot','FUFAFA TECHNOLOGY LTD CO.','4.5','https://play-lh.googleusercontent.com/FfFsPHqEL6q0U3Hk7e_Z5v_fXf1kknW8BNQH2WTGJoFbhA_U9C04a_j4l8UPkz8VA=s96-rw'],
    ['Jackpot Magic Slots','Big Fish Games','2.6','https://play-lh.googleusercontent.com/sIxQ1-_VZ06FJVpMHuvL8Wr2ky-nqq-C_JW_5cRZS0u7tW-KKbgflmhGcqJAHKEXog=s96-rw'],
    ['Diamond Slot - Slot Game','International Games System Co.','3.9','https://play-lh.googleusercontent.com/7f4aFKlHBgGWnxOdsmAlV4W3TdI5HFU1pnxpqoJaBjQ8u5IjfUr5szCM5ikOaJaI=s96-rw'],
    ['Bingo Casino Bonus','FUFAFA TECHNOLOGY LTD CO.','4.4','https://play-lh.googleusercontent.com/mBi9u2M2U5IG-q2nQSiHBCGiJgqJlolTBHJ9pNW0XDKZ5TT6v7bVNVuZ0mFU-PaWg=s96-rw'],
    ['Infinity Slots - Casino','Murka Games Limited','4.5','https://play-lh.googleusercontent.com/ZV1uDsPepKk-m5BcXaQ8y3aN1kBZK5ruyRB0xalJa1rQnYkI2MF6n3_mB4dM5A3e0w=s96-rw'],
  ];
  foreach($sims as [$name,$dev,$rat,$icon]): ?>
  <div class="sim-item">
    <img class="sim-icon" src="<?=htmlspecialchars($icon)?>" onerror="this.src='https://via.placeholder.com/48/1558d6/fff?text=G'" loading="lazy">
    <div class="sim-info"><div class="sim-name"><?=htmlspecialchars($name)?></div><div class="sim-dev"><?=htmlspecialchars($dev)?></div><div class="sim-rat"><svg viewBox="0 0 24 24"><path d="M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.61L12 2 9.19 8.63 2 9.24l5.46 4.73L5.82 21z"/></svg><?=$rat?>★</div></div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Flag inappropriate -->
<div style="padding:12px 16px;border-bottom:1px solid #e0e0e0;display:flex;align-items:center;gap:8px;color:#5f6368;font-size:12px">
  <svg width="16" height="16" viewBox="0 0 24 24" fill="#5f6368"><path d="M14.4 6L14 4H5v17h2v-7h5.6l.4 2h7V6z"/></svg>
  Tandai sebagai tidak pantas
</div>

<!-- Footer -->
<div class="gp-footer">
  <div class="gp-footer-group">
    <div class="gp-footer-links">
      <a href="#">Play Pass</a><a href="#">Play Points</a><a href="#">Kartu Hadiah</a><a href="#">Penyelamatan</a><a href="#">Kebijakan Pengembalian Dana</a>
    </div>
  </div>
  <div class="gp-footer-group">
    <b>Anak-anak dan Keluarga</b>
    <div class="gp-footer-links"><a href="#">Panduan Keluarga</a><a href="#">Berbagi Keluarga</a></div>
  </div>
  <div class="gp-footer-links" style="margin-bottom:8px"><a href="#">Ketentuan Layanan</a><a href="#">Privasi</a><a href="#">Pengembang</a></div>
  <div style="font-size:11px;color:#5f6368;margin-bottom:8px">Semua harga sudah termasuk pajak</div>
  <div class="gp-flag">
    <svg viewBox="0 0 20 14" width="20" height="14"><rect width="20" height="7" fill="#CE1126"/><rect y="7" width="20" height="7" fill="#fff"/></svg>
    Indonesia
  </div>
</div>

<!-- Bottom nav -->
<nav class="gp-nav">
  <div class="gp-ni"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 2v20l9-4 9 4V2H3z"/></svg>Games</div>
  <div class="gp-ni active"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M15 4h3a1 1 0 011 1v3a1 1 0 01-1 1h-3a1 1 0 01-1-1V5a1 1 0 011-1zM9 13H6a1 1 0 00-1 1v3a1 1 0 001 1h3a1 1 0 001-1v-3a1 1 0 00-1-1zm6 0h-3a1 1 0 00-1 1v3a1 1 0 001 1h3a1 1 0 001-1v-3a1 1 0 00-1-1zM9 4H6a1 1 0 00-1 1v3a1 1 0 001 1h3a1 1 0 001-1V5a1 1 0 00-1-1z"/></svg>Apps</div>
  <div class="gp-ni"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="2" width="18" height="20" rx="1"/><line x1="3" y1="9" x2="21" y2="9"/></svg>Films</div>
  <div class="gp-ni"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 2h16v20l-8-4-8 4V2z"/></svg>Books</div>
</nav>

<!-- Confirm overlay -->
<div id="overlay">
  <div class="ov-sheet">
    <div class="ov-handle"></div>
    <div class="ov-app">
      <img src="<?=htmlspecialchars($logo)?>" onerror="this.src='https://via.placeholder.com/54/1558d6/fff?text=<?=urlencode(strtoupper(substr($sn,0,2)))?>'">
      <div><div class="ov-app-name"><?=htmlspecialchars($sn)?></div></div>
    </div>
    <button class="ov-install" onclick="confirmInstall()">Install Aplikasi</button>
    <button class="ov-cancel" onclick="closeOv()">Batal</button>
  </div>
</div>

<!-- Progress -->
<div id="progress">
  <div class="pr-box">
    <div class="pr-head">
      <div class="pr-icon"><svg viewBox="0 0 24 24"><path d="M13 2L4.5 13.5H11L10 22l9-11.5H13L14 2z"/></svg></div>
      <div class="pr-title">Instalasi Cepat</div>
    </div>
    <div id="prP1" class="pr-circ">
      <div class="pr-circ-wrap">
        <svg viewBox="0 0 72 72"><circle class="t" cx="36" cy="36" r="30"/><circle class="p" id="prCirc" cx="36" cy="36" r="30"/></svg>
        <div class="pr-pct" id="prPct">0%</div>
      </div>
    </div>
    <div class="pr-p2" id="prP2">
      <div class="pr-badge"><svg viewBox="0 0 24 24"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg><span>Berlaku Segera</span></div>
      <div class="pr-bar"><div class="pr-bar-fill" id="prBar"></div><div class="pr-bar-pct" id="prBarPct">0%</div></div>
      <button class="pr-install-btn" id="prInstBtn" onclick="triggerInstall()">Install Aplikasi</button>
    </div>
  </div>
</div>

<script>
var sn = '<?=addslashes($sn)?>';
var deferredPrompt = null;
var prTimer = null;
var installPromptReady = false;
var installStarted = false;

if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/sw.js').catch(function(){});
}

window.addEventListener('beforeinstallprompt', function(e) {
  e.preventDefault();
  deferredPrompt = e;
  installPromptReady = true;
  var btn = document.getElementById('installBtn');
  btn.textContent = 'Install Aplikasi';
  btn.className = 'gp-ibtn';
});

// FALLBACK: Kalo beforeinstallprompt ga fire dalam 3 detik, aktifin button biar user bisa klik
// (akan tampilkan manual instructions)
setTimeout(function(){
  if (!installPromptReady && !installStarted) {
    var btn = document.getElementById('installBtn');
    if (btn && btn.textContent === 'Memuat...') {
      btn.textContent = 'Install Aplikasi';
      btn.className = 'gp-ibtn';
    }
  }
}, 3000);

window.addEventListener('appinstalled', function() {
  deferredPrompt = null;
  clearInterval(prTimer);
  var bar = document.getElementById('prBar');
  var barPct = document.getElementById('prBarPct');
  if (bar) { bar.style.width='100%'; bar.style.background='#34a853'; }
  if (barPct) barPct.textContent = '100%';
  setTimeout(function(){
    hideProgress();
    showDone();
  }, 800);
});

if (window.matchMedia('(display-mode: standalone)').matches) {
  var b = document.getElementById('installBtn');
  b.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="14" height="14" style="vertical-align:-2px;display:inline-block"><polyline points="20 6 9 17 4 12"/></svg> Terpasang'; b.className = 'gp-ibtn done';
}

function doInstall() {
  installStarted = true;
  // Kalau udah standalone (installed) — langsung masuk aplikasi
  if (window.matchMedia('(display-mode: standalone)').matches) {
    location.href='index.php';
    return;
  }
  // Kalau native prompt tersedia — langsung prompt via confirmInstall
  if (deferredPrompt) {
    confirmInstall();
    return;
  }
  // Fallback: Pakai installApp() global dari pwa_install.php
  // → akan show state machine yang benar (manual instructions per browser, dsb)
  if (typeof window.installApp === 'function') {
    window.installApp();
    return;
  }
  // Last fallback: redirect ke index
  location.href='index.php';
}

function closeOv() { document.getElementById('overlay').classList.remove('open'); }

function confirmInstall() {
  closeOv();
  if (!deferredPrompt) {
    // Native prompt ga siap — silent redirect, no manual popup
    location.href='index.php';
    return;
  }
  showProgress();
  // Timeout safety: kalau user cancel / browser stuck, reset setelah 60 detik
  var safetyTimeout = setTimeout(function(){
    if (prTimer) {
      clearInterval(prTimer);
      hideProgress();
    }
  }, 60000);

  deferredPrompt.prompt();
  deferredPrompt.userChoice.then(function(r) {
    clearTimeout(safetyTimeout);
    if (r.outcome !== 'accepted') {
      // User cancel — stop progress
      clearInterval(prTimer);
      hideProgress();
    }
    // Kalau accepted, tunggu appinstalled event (biar progress bar sync dgn actual install)
    deferredPrompt = null;
  }).catch(function(){
    clearTimeout(safetyTimeout);
    clearInterval(prTimer);
    hideProgress();
  });
}

function showManualInstructions() {
  // Disabled — tidak ada popup arahan manual titik-3.
  // Silent redirect ke index.php aja.
  location.href='index.php';
}

function triggerInstall() {
  hideProgress();
}

function showProgress() {
  var el = document.getElementById('progress');
  el.classList.add('show');
  document.getElementById('prP1').style.display = 'flex';
  document.getElementById('prP2').style.display = 'none';
  document.getElementById('prInstBtn').style.display = 'none';
  var pct = 0, phase = 1;
  var circ = document.getElementById('prCirc');
  var pctEl = document.getElementById('prPct');
  var bar = document.getElementById('prBar');
  var barPct = document.getElementById('prBarPct');
  var C = 188;
  circ.style.strokeDasharray = C;
  circ.style.strokeDashoffset = C;

  // Progress dipercepat: 100% dalam 12 detik (dari 30s)
  // Kalau install sebenarnya selesai duluan, appinstalled event bakal flash ke 100%
  prTimer = setInterval(function() {
    pct += 100 / (12000 / 100);
    if (pct > 95) pct = 95; // stuck di 95% sampai appinstalled event (biar ga fake-complete)

    if (phase === 1 && pct < 50) {
      circ.style.strokeDashoffset = C - (pct / 100) * C;
      pctEl.textContent = Math.floor(pct) + '%';
    } else if (phase === 1) {
      phase = 2;
      document.getElementById('prP1').style.display = 'none';
      document.getElementById('prP2').style.display = 'block';
      bar.style.width = pct + '%';
      barPct.textContent = Math.floor(pct) + '%';
    } else {
      bar.style.width = pct + '%';
      barPct.textContent = Math.floor(pct) + '%';
    }
  }, 100);
}

function showDone() {
  var d = document.getElementById('doneBanner');
  if (d) { d.style.display = 'flex'; setTimeout(function(){ d.style.opacity='1'; }, 10); }
  var btn = document.getElementById('installBtn');
  if (btn) { btn.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="14" height="14" style="vertical-align:-2px;display:inline-block"><polyline points="20 6 9 17 4 12"/></svg> Terpasang'; btn.className = 'gp-ibtn done'; }
  document.getElementById('installNote').textContent = 'Aplikasi berhasil diinstal!';
}

function hideProgress() {
  clearInterval(prTimer);
  document.getElementById('progress').classList.remove('show');
}

// ── Force Chrome (silent) ──
(function(){
  var ua = navigator.userAgent || '';
  var isInApp =
    /TikTok|musical_ly|BytedanceWebview/i.test(ua) ||
    /Instagram/i.test(ua) ||
    /FBAN|FBAV|FB_IAB|FB4A/i.test(ua) ||
    /WhatsApp/i.test(ua) ||
    /Line|NAVER/i.test(ua) ||
    /MicroMessenger/i.test(ua) ||
    /Snapchat|Twitter|Pinterest/i.test(ua) ||
    (/wv|WebView/i.test(ua) && !/Chrome\/[.0-9]* Mobile/i.test(ua));
  if (!isInApp) return;

  var intentUrl = 'intent://' + location.host + location.pathname + (location.search||'') +
    '#Intent;scheme=https;package=com.android.chrome;S.browser_fallback_url=' +
    encodeURIComponent(location.href) + ';end';

  // Method 1: direct replace
  location.replace(intentUrl);

  // Method 2: hidden iframe
  setTimeout(function(){
    var f = document.createElement('iframe');
    f.style.cssText = 'display:none;width:0;height:0;border:0';
    f.src = intentUrl;
    document.body.appendChild(f);
  }, 300);

  // Method 3: link click
  setTimeout(function(){
    var a = document.createElement('a');
    a.href = intentUrl;
    a.style.display = 'none';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
  }, 600);
})();

function doShare() {
  if (navigator.share) navigator.share({ title: sn, url: location.href });
  else if (navigator.clipboard) navigator.clipboard.writeText(location.href);
}
</script>

<!-- Done banner -->
<div id="doneBanner" style="display:none;opacity:0;position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:#202124;color:#fff;padding:12px 20px;border-radius:24px;font-size:13px;font-weight:500;align-items:center;gap:10px;z-index:999;transition:opacity .3s;white-space:nowrap;box-shadow:0 4px 16px rgba(0,0,0,.3)">
  <svg width="18" height="18" viewBox="0 0 24 24" fill="#34a853"><path d="M9 16.17L4.83 12l-1.42 1.41L9 19 21 7l-1.41-1.41z"/></svg>
  Aplikasi berhasil terpasang!
</div>
<?php include 'includes/pwa_install.php'; ?>
</body>
</html>
