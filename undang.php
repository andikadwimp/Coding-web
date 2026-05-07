<?php
require_once 'includes/config.php';
header('Content-Type: text/html; charset=UTF-8');
$isLoggedIn = (bool)getUid();  // guest-friendly: page accessible, action gated by JS

$sets=[];
try{$st=$db->query("SELECT `key`,`value` FROM settings");foreach($st->fetchAll() as $r)$sets[$r['key']]=$r['value'];normSettings($sets);}catch(Exception $e){}
$sn=$sets['site_name']??'Situs';
$uid=getUid();
$refCode='';$downCount=0;$downlines=[];
if($uid){
  try{$u=$db->prepare("SELECT ref_code FROM users WHERE id=?");$u->execute([$uid]);$refCode=$u->fetchColumn()??'';}catch(Exception $e){}
  try{
    // Auto-create column untuk rate-limit sync (pertama kali)
    try{$db->exec("ALTER TABLE users ADD COLUMN last_turnover_sync DATETIME DEFAULT NULL AFTER total_turnover");}catch(Exception $e){}
    $dc=$db->prepare("SELECT COUNT(*) FROM users WHERE referred_by=?");$dc->execute([$refCode]);$downCount=$dc->fetchColumn();
    $dl=$db->prepare("SELECT id,username,created_at,total_deposit,total_turnover FROM users WHERE referred_by=? ORDER BY created_at DESC LIMIT 50");$dl->execute([$refCode]);$downlines=$dl->fetchAll();
  }catch(Exception $e){}
}
$domain=$_SERVER['HTTP_HOST']??'cuanvvipgg.xyz';
$refLink='https://'.$domain.'/invite.php?ref='.$refCode;
// Theme reward card — dipilih admin di panel
// Theme baru: peti (default), angpau, tael (PNG dari img/rewards/)
// Legacy: angpao, gentong, babi (fallback ke peti)
$undangTheme=$sets['undang_theme']??'peti';
if(!in_array($undangTheme,['peti','angpau','tael']))$undangTheme='peti';

// Map theme → file PNG
$themeFiles=[
  'peti'  =>['closed'=>'img/rewards/peti_closed.png',  'open'=>'img/rewards/peti_open.png'],
  'angpau'=>['closed'=>'img/rewards/angpau_closed.png','open'=>'img/rewards/angpau_open.png'],
  'tael'  =>['closed'=>'img/rewards/tael_silver.png',  'open'=>'img/rewards/tael_gold.png'],
];
$themeImgClosed=$themeFiles[$undangTheme]['closed'];
$themeImgOpen  =$themeFiles[$undangTheme]['open'];
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php require_once dirname(__FILE__).'/pwa_head.php'; ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Undang Teman - <?php echo $sn; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="theme.php?v=<?=time()?>">
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;-webkit-text-size-adjust:100%;text-size-adjust:100%}
html{font-size:16px!important;overflow-x:hidden}
body{overflow-x:hidden;max-width:100vw;font-family:'Plus Jakarta Sans','Outfit','Poppins',sans-serif;background:var(--bg);color:var(--t);min-height:100vh;padding-bottom:calc(100px + env(safe-area-inset-bottom,0px))}

/* Header */
.hdr{display:flex;align-items:center;padding:14px 16px;position:sticky;top:0;z-index:10;background:var(--bg)}
.hdr-btn{width:32px;height:32px;display:flex;align-items:center;justify-content:center;background:none;border:none;color:var(--t2);cursor:pointer}
.hdr-btn svg{width:22px;height:22px}
.hdr h1{flex:1;text-align:center;font-size:1.05rem;font-weight:700;letter-spacing:-.3px}

/* Banner */
/* Share */
/* Hero invite — banner utama */
.u-hero{margin:0 14px 14px;padding:22px 20px 20px;background:linear-gradient(135deg,var(--pri) 0%,var(--pri-d) 100%);border-radius:16px;position:relative;overflow:hidden;box-shadow:0 8px 24px rgba(var(--pri-rgb),.35)}
.u-hero::before{content:'';position:absolute;top:-50%;right:-20%;width:260px;height:260px;background:radial-gradient(circle,var(--tint-3),transparent 70%);border-radius:50%;pointer-events:none}
.u-hero::after{content:'';position:absolute;bottom:-60%;left:-10%;width:220px;height:220px;background:radial-gradient(circle,var(--tint-2),transparent 70%);border-radius:50%;pointer-events:none}
.u-hero-in{position:relative;z-index:1}
.u-hero-eyebrow{font-size:.62rem;font-weight:800;color:#fff;letter-spacing:1.5px;text-transform:uppercase;margin-bottom:6px;opacity:.95;text-shadow:0 1px 2px rgba(0,0,0,.3)}
.u-hero-title{font-size:1.35rem;font-weight:900;color:#fff;line-height:1.15;letter-spacing:-.4px;margin-bottom:8px;text-shadow:0 2px 6px rgba(0,0,0,.4),0 1px 2px rgba(0,0,0,.3)}
.u-hero-sub{font-size:.72rem;color:#fff;opacity:.92;line-height:1.45;max-width:230px;text-shadow:0 1px 3px rgba(0,0,0,.3)}

/* Ref Link — prominent & tappable */
.ref-card{margin:0 14px 12px;padding:14px 16px;background:var(--s);border:1.5px solid var(--bd);border-radius:14px}
.ref-card-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:10px}
.ref-card-hdr .rch-lbl{font-size:.65rem;font-weight:800;color:var(--t3);letter-spacing:1px;text-transform:uppercase}
.ref-card-hdr .rch-code{font-size:.7rem;font-weight:800;color:var(--pri);background:rgba(var(--pri-rgb,56,189,248),.1);padding:3px 10px;border-radius:6px;letter-spacing:.5px;font-family:monospace}
.ref-row{display:flex;align-items:center;gap:10px}
.ref-url{flex:1;font-size:.7rem;color:var(--t2);word-break:break-all;line-height:1.45;background:var(--bg);padding:10px 12px;border-radius:8px;border:1px solid var(--bd)}
.ref-copy{width:44px;height:44px;background:var(--pri);color:#fff;border:none;border-radius:10px;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;transition:transform .12s}
.ref-copy:active{transform:scale(.92)}
.ref-copy svg{width:18px;height:18px}

/* Stats — 3 cell ringkasan */
.stat-row{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:0 14px 14px}
.stat-cell{padding:14px 10px;background:var(--s);border:1.5px solid var(--bd);border-radius:12px;text-align:center}
.stat-cell .sc-val{font-size:1.25rem;font-weight:900;color:var(--pri);line-height:1;font-family:'Chakra Petch',sans-serif;letter-spacing:-.5px}
.stat-cell .sc-lbl{font-size:.6rem;color:var(--t3);font-weight:600;margin-top:6px;letter-spacing:.3px}
.stat-cell.clickable{cursor:pointer;transition:border-color .15s}
.stat-cell.clickable:active{border-color:var(--pri);transform:scale(.97)}

/* Share section — more compact */
.share-sec{margin:0 14px 18px;padding:14px 16px;background:var(--s);border:1.5px solid var(--bd);border-radius:14px}
.share-sec .ss-title{font-size:.72rem;font-weight:800;color:var(--t2);margin-bottom:10px;letter-spacing:.5px}

.sec-title{text-align:center;font-size:.88rem;font-weight:700;color:var(--sec);padding:0 0 12px}
.socials{display:flex;justify-content:space-between;gap:8px}
.soc{flex:1;aspect-ratio:1;max-width:46px;border-radius:50%;display:flex;align-items:center;justify-content:center;text-decoration:none;transition:transform .12s}
.soc:active{transform:scale(.9)}
.soc svg,.soc svg path{fill:#fff!important}
.soc svg{width:20px;height:20px}

/* Ref Link */
.card{margin:0 16px 12px;padding:16px 18px;background:var(--s);border:1px solid var(--bd);border-radius:14px}
.card label{font-size:.72rem;font-weight:700;color:var(--t3);margin-bottom:8px;display:block;letter-spacing:.5px;text-transform:uppercase}
.ref-row{display:flex;align-items:center;gap:10px}
.ref-url{flex:1;font-size:.72rem;color:var(--sec);word-break:break-all;line-height:1.4}
.ref-copy{width:42px;height:42px;background:linear-gradient(135deg,var(--sec),var(--sec));color:#fff;border:none;border-radius:10px;display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;box-shadow:0 3px 12px rgba(var(--sec-rgb),.3)}
.ref-copy svg{width:18px;height:18px}

/* Stats */
.stat-card{margin:0 16px 24px;padding:16px 18px;background:var(--s);border:1px solid var(--bd);border-radius:14px;display:flex;align-items:center;justify-content:space-between}
.stat-card .st-l{display:flex;flex-direction:column;gap:2px}
.stat-card .st-l .st-label{font-size:.68rem;color:var(--t3);text-transform:uppercase;letter-spacing:.5px}
.stat-card .st-l .st-val{font-size:1.1rem;font-weight:800;color:var(--t)}
.stat-card .st-l .st-val b{color:var(--pri)}
.stat-card .st-l .st-sub{font-size:.62rem;color:var(--t3)}
.st-btn{padding:8px 16px;background:transparent;border:1px solid var(--sec);border-radius:8px;color:var(--sec);font-size:.72rem;font-weight:700;cursor:pointer;font-family:inherit;white-space:nowrap;flex-shrink:0}

/* Rewards — Polished, pakai var theme biar ngikut tema */
.rw-wrap{margin:0 12px 24px;border-radius:18px;overflow:hidden;border:2px solid var(--bd,rgba(var(--sec-rgb),.35));background:linear-gradient(180deg,var(--s) 0%,var(--bg2,#232960) 50%,var(--s) 100%);position:relative;box-shadow:0 8px 24px rgba(0,0,0,.4),inset 0 1px 0 var(--tint-2)}
.rw-wrap::before{content:'';position:absolute;top:0;left:0;right:0;bottom:0;background:url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffd700' fill-opacity='0.025'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");pointer-events:none;z-index:0}
.rw-hdr{position:relative;z-index:1;text-align:center;padding:18px 16px 14px}
.rw-hdr-inner{display:inline-block;padding:11px 40px;background:linear-gradient(135deg,var(--pri,#dc2626),var(--sec,#b91c1c) 60%);border-radius:24px;font-size:.96rem;font-weight:900;color:#fff;letter-spacing:.6px;box-shadow:0 6px 18px rgba(0,0,0,.35),inset 0 1px 0 var(--bd2);border:1.5px solid var(--tint-3);text-shadow:0 1px 2px rgba(0,0,0,.4)}

/* Grid — 4 kolom FIT, no overflow */
.rw-grid{position:relative;z-index:1;padding:12px 10px 16px;display:grid;grid-template-columns:repeat(4,1fr);gap:8px;width:100%;box-sizing:border-box}

/* Card */
.rw-card{text-align:center;padding:8px 4px 10px;background:var(--tint-1);border:1px solid var(--bd,rgba(var(--sec-rgb),.18));border-radius:12px;position:relative;cursor:default;transition:all .25s;display:flex;flex-direction:column;align-items:center;gap:4px;min-width:0;overflow:hidden}
.rw-card.claimable{border-color:var(--pri,#ffd700);background:radial-gradient(ellipse at top,rgba(255,215,0,.18),rgba(255,215,0,.03) 60%);cursor:pointer;box-shadow:0 0 14px rgba(255,215,0,.3),inset 0 1px 0 rgba(255,215,0,.2)}
.rw-card.claimable .rw-icon{animation:chFloat 2s ease-in-out infinite}
.rw-card.claimed{opacity:.5}
.rw-card.claimed::before{content:'Diklaim';position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-12deg);background:rgba(0,0,0,.85);color:#4ade80;font-size:.55rem;font-weight:800;padding:4px 9px;border-radius:6px;z-index:3;border:1px solid rgba(74,222,128,.5);white-space:nowrap;letter-spacing:.5px}

/* Icon — lebih kecil biar muat 4 kolom */
.rw-icon{width:54px;height:54px;display:flex;align-items:center;justify-content:center;margin:2px auto 4px;position:relative;filter:drop-shadow(0 4px 6px rgba(0,0,0,.5));flex-shrink:0}
.rw-icon img{width:100%;height:100%;object-fit:contain;display:block}

/* Value — compact (50K, 500K, 1Jt), font bisa lebih gede */
.rw-val{font-size:.78rem;font-weight:800;color:var(--pri,#ffd700);background:rgba(255,215,0,.1);border:1px solid rgba(255,215,0,.25);border-radius:8px;padding:4px 2px;margin:2px 0 0;width:100%;box-sizing:border-box;letter-spacing:-.2px;text-shadow:0 1px 2px rgba(0,0,0,.5);line-height:1.15;white-space:nowrap}
.rw-card.claimable .rw-val{background:rgba(255,215,0,.22);border-color:var(--pri,#ffd700);color:#fff}
.rw-card.claimed .rw-val{color:var(--t3);background:var(--tint-1);border-color:var(--tint-2)}

/* Label — boleh wrap kalo angka besar */
.rw-lbl{font-size:.58rem;color:var(--t2,rgba(255,255,255,.55));margin-top:3px;line-height:1.3;font-weight:500;width:100%;word-break:break-word}
.rw-lbl b{color:var(--pri,#ffd700);font-weight:800}

/* Panah antar card — garis tipis, tidak ke card terakhir di kanan */
.rw-card:not(:nth-child(4n))::after{content:'';position:absolute;right:-5px;top:28px;width:6px;height:1.5px;background:linear-gradient(90deg,var(--pri,#ffd700) 0%,transparent 100%);opacity:.5;z-index:2;pointer-events:none}
.rw-card.claimed:not(:nth-child(4n))::after{opacity:.15}

@keyframes chFloat{0%,100%{transform:translateY(0) scale(1)}50%{transform:translateY(-4px) scale(1.05)}}

/* Sparkle */
.sparkle{position:absolute;background:var(--pri,#ffd700);border-radius:50%;animation:sparkAnim 2.5s ease-in-out infinite;pointer-events:none;z-index:1}
@keyframes sparkAnim{0%,100%{opacity:0;transform:scale(0)}50%{opacity:.8;transform:scale(1)}}
.rw-lbl b{color:rgba(255,255,255,.7)}
.rw-card.claimable .rw-val{color:#ffe566}
.rw-card.claimable .rw-lbl b{color:#fff}
@keyframes chGlow{0%{filter:drop-shadow(0 3px 6px rgba(0,0,0,.3))}100%{filter:drop-shadow(0 3px 12px rgba(74,222,128,.5))}}
.rw-card.claimed::after{content:'Diklaim';position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);background:rgba(0,0,0,.8);color:#4ade80;font-size:.6rem;font-weight:800;padding:5px 10px;border-radius:6px;z-index:2;border:1px solid rgba(74,222,128,.3);letter-spacing:.3px}
.rw-icon{width:56px;height:63px;margin:0 auto 4px;animation:chFloat 3s ease-in-out infinite;filter:drop-shadow(0 3px 6px rgba(0,0,0,.3))}
.rw-card:nth-child(2n) .rw-icon{animation-delay:-.6s}
.rw-card:nth-child(3n) .rw-icon{animation-delay:-1.2s}
.rw-card:nth-child(4n) .rw-icon{animation-delay:-1.8s}
.rw-card:nth-child(5n) .rw-icon{animation-delay:-2.4s}
@keyframes chFloat{0%,100%{transform:translateY(0) rotate(0deg)}30%{transform:translateY(-3px) rotate(.4deg)}60%{transform:translateY(-1px) rotate(-.3deg)}80%{transform:translateY(-4px) rotate(.2deg)}}
.rw-val{display:inline-block;padding:2px 8px;border-radius:4px;font-size:.62rem;font-weight:800;color:#4ade80;background:rgba(74,222,128,.12);margin-bottom:2px;border:1px solid rgba(74,222,128,.15)}
.rw-lbl{font-size:.52rem;color:var(--bd2);line-height:1.3}.rw-lbl b{color:#ffd700}

/* Sparkle particles around reward area */
.rw-wrap .sparkle{position:absolute;width:4px;height:4px;background:#ffd700;border-radius:50%;animation:sparkle 2s ease-in-out infinite;z-index:0}
@keyframes sparkle{0%,100%{opacity:0;transform:scale(0)}50%{opacity:.8;transform:scale(1)}}

/* Rules */
.info-card{margin:0 16px 14px;padding:18px;background:var(--s);border:1px solid var(--bd);border-radius:14px}
.info-card h4{font-size:.85rem;font-weight:700;margin-bottom:12px;color:var(--t)}
.info-card .sub{font-size:.65rem;color:var(--t3);margin-bottom:8px}
.ir{display:flex;justify-content:space-between;padding:12px 0;border-top:1px solid rgba(var(--sec-rgb),.15);font-size:.75rem;color:var(--t2)}
.ir b{color:var(--sec);font-weight:800}

.rules{margin:0 16px 24px;padding:20px;background:var(--bg2);border:1.5px solid var(--bd);border-radius:14px}
.rules h3{font-size:.92rem;font-weight:800;text-align:center;margin-bottom:16px;position:relative;color:var(--t)}
.rules h3::before,.rules h3::after{content:'';position:absolute;top:50%;width:40px;height:1px;background:var(--tint-3)}
.rules h3::before{left:20px}.rules h3::after{right:20px}
.rules p{font-size:.74rem;color:var(--t);line-height:1.7;margin-bottom:10px;font-weight:500}
.rules p:first-of-type{font-size:.7rem;color:var(--t2);text-align:center;margin-bottom:14px;font-weight:600;font-style:italic}

/* Detail */
.dt-overlay{position:fixed;inset:0;background:var(--bg);z-index:200;display:none;flex-direction:column}
.dt-overlay.open{display:flex}
.dt-hdr{display:flex;align-items:center;padding:16px;flex-shrink:0}
.dt-hdr button{width:32px;height:32px;display:flex;align-items:center;justify-content:center;background:none;border:none;color:var(--t2);cursor:pointer}
.dt-hdr button svg{width:22px;height:22px}
.dt-hdr h2{flex:1;text-align:center;font-size:1.05rem;font-weight:700}
.dt-search{margin:0 16px 14px;display:flex;align-items:center;background:var(--s);border:1px solid var(--bd);border-radius:12px;padding:0 14px}
.dt-search input{flex:1;background:none;border:none;color:var(--t);font-family:inherit;font-size:.85rem;padding:13px 0;outline:none}
.dt-search input::placeholder{color:var(--t3)}
.dt-search svg{width:20px;height:20px;color:var(--t3);flex-shrink:0}
.dt-cols{display:flex;margin:0 16px 8px;background:rgba(var(--sec-rgb),.12);border-radius:10px;padding:11px 0}
.dt-cols span{flex:1;text-align:center;font-size:.62rem;font-weight:700;color:var(--t)}
.dt-body{overflow-x:hidden;max-width:100vw;flex:1;overflow-y:auto;padding:0 16px}
.dt-row{display:flex;padding:12px 0;border-bottom:1px solid rgba(var(--sec-rgb),.1)}
.dt-row span{flex:1;text-align:center;font-size:.65rem;color:var(--t2)}
.dt-empty{text-align:center;padding:60px 20px}
.dt-empty svg{width:80px;height:80px;color:var(--s2);margin-bottom:16px;opacity:.3}
.dt-empty p{color:var(--t3);font-size:.85rem}

/* Nav */
.bnav{position:fixed;bottom:0;left:0;right:0;z-index:100;display:flex;background:var(--nav-bg,#0a1628);padding:8px 0 env(safe-area-inset-bottom,6px);overflow:hidden;border-radius:14px 14px 0 0;box-shadow:0 -4px 20px rgba(0,0,0,.5);border-top:1px solid rgba(var(--sec-rgb,56,189,248),.2)}
.bnav::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent 2%,var(--pri,var(--sec)) 15%,var(--sec-l, var(--pri-l)) 50%,var(--pri,var(--sec)) 85%,transparent 98%)}
.bnav-i{flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;padding:6px 0;color:var(--t3);font-size:.6rem;font-weight:600;text-decoration:none}
.bnav-i.active{color:var(--sec)}
.bnav-i img{width:36px;height:36px;object-fit:contain}

/* Toast */
.toast-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9998}
.toast-box{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--s);border:1px solid var(--bd);border-radius:14px;padding:24px 30px;text-align:center;z-index:9999;max-width:280px;box-shadow:0 10px 40px rgba(0,0,0,.5)}
.toast-box p{font-size:.85rem;font-weight:600;color:var(--t);line-height:1.5;margin-bottom:16px}
.toast-box button{padding:8px 30px;background:var(--sec);border:none;border-radius:8px;color:#fff;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit}
@keyframes spin{0%{transform:rotate(0)}100%{transform:rotate(360deg)}}
</style>
</head>
<body>

<?php  ?>
<div class="hdr">
<button class="hdr-btn" onclick="history.back()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button>
<h1>Undang Teman</h1>
<button class="hdr-btn" onclick="openDetail()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 012 2v14a2 2 0 01-2 2H6a2 2 0 01-2-2V6a2 2 0 012-2h2"/><line x1="12" y1="11" x2="12" y2="17"/><line x1="9" y1="14" x2="15" y2="14"/></svg></button>
</div>

<!-- Hero banner -->
<div class="u-hero">
  <div class="u-hero-in">
    <div class="u-hero-eyebrow">Program Referral</div>
    <div class="u-hero-title">Undang Teman, Dapat Bonus!</div>
    <div class="u-hero-sub">Bagikan link referral kamu. Setiap teman yang daftar & deposit, kamu dapat bonus sampai ratusan ribu.</div>
  </div>
</div>

<!-- Ref link card -->
<div class="ref-card">
  <div class="ref-card-hdr">
    <span class="rch-lbl">Link Referral Saya</span>
    <span class="rch-code" id="refCodeTxt"><?=htmlspecialchars($refCode)?></span>
  </div>
  <div class="ref-row">
    <div class="ref-url" id="refUrl"><?=$refLink?></div>
    <button class="ref-copy" onclick="copyLink()" title="Salin link"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg></button>
  </div>
</div>

<!-- Stats 3 col -->
<div class="stat-row">
  <div class="stat-cell clickable" onclick="openDetail()">
    <div class="sc-val"><?=$downCount?></div>
    <div class="sc-lbl">Total Undangan</div>
  </div>
  <div class="stat-cell" id="statValid">
    <div class="sc-val">0</div>
    <div class="sc-lbl">Valid / Aktif</div>
  </div>
  <div class="stat-cell" id="statBonus">
    <div class="sc-val">0</div>
    <div class="sc-lbl">Bonus (K)</div>
  </div>
</div>

<div class="rw-wrap" id="rwWrap">
<div class="rw-hdr"><div class="rw-hdr-inner">Hadiah Undangan</div></div>
<div class="rw-grid" id="rwGrid" style="min-height:100px"></div>
</div>

<!-- Share section — compact, di bawah rewards -->
<div class="share-sec">
<div class="ss-title">Bagikan ke Teman</div>
<div class="socials">
<a class="soc" style="background:#25D366" href="https://wa.me/?text=<?php echo urlencode('Ayo main di '.$sn.'! Daftar: '.$refLink); ?>" target="_blank"><svg viewBox="0 0 24 24" width="20" height="20"><path style="fill:#ffffff" d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91C21.95 6.45 17.5 2 12.04 2zm5.82 14.01c-.24.68-1.42 1.3-1.96 1.38-.5.08-.96.11-3.28-.7-2.8-1-4.57-3.85-4.71-4.03-.14-.18-1.1-1.47-1.1-2.81 0-1.34.69-2 .94-2.27.24-.27.53-.34.71-.34.18 0 .36 0 .51.01.18.01.41-.06.64.49.24.56.8 1.97.87 2.11.07.14.12.31.02.49-.09.19-.14.3-.28.47-.14.16-.3.36-.43.49-.14.14-.29.29-.12.57.16.28.73 1.21 1.57 1.96 1.08.97 2 1.27 2.28 1.41.28.14.45.12.62-.07.16-.19.71-.83.9-1.12.19-.28.38-.24.64-.14.27.09 1.68.79 1.97.94.28.14.47.21.54.33.07.12.07.71-.17 1.39z"/></svg></a>
<a class="soc" style="background:#1877F2" href="#"><svg viewBox="0 0 24 24" width="20" height="20"><path style="fill:#ffffff" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>
<a class="soc" style="background:#229ED9" href="#"><svg viewBox="0 0 24 24" width="20" height="20"><path style="fill:#ffffff" d="M11.944 0A12 12 0 000 12a12 12 0 0012 12 12 12 0 0012-12A12 12 0 0012.056 0zM8.016 10.18l8.544-3.293c.396-.144.742.097.613.7l-1.457 6.858c-.108.48-.39.597-.79.37l-2.18-1.607-1.051 1.013c-.116.116-.214.214-.439.214l.156-2.213 4.026-3.637c.176-.156-.038-.243-.272-.087l-4.974 3.132-2.143-.668c-.466-.146-.475-.466.097-.69z"/></svg></a>
<a class="soc" style="background:#000" href="#"><svg viewBox="0 0 24 24" width="20" height="20"><path style="fill:#ffffff" d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>
<a class="soc" style="background:linear-gradient(45deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888)" href="#"><svg viewBox="0 0 24 24" width="20" height="20"><path style="fill:#ffffff" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg></a>
<a class="soc" style="background:#000" href="#"><svg viewBox="0 0 24 24" width="20" height="20"><path style="fill:#ffffff" d="M12.525.02c1.31-.02 2.61.01 3.91.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg></a>
</div>
</div>

<?php
// Ambil syarat dari settings (sama dengan yg dipake backend)
$t1_dep = intval($sets['ref_tier1_min_deposit'] ?? 100000);
$t1_to  = intval($sets['ref_tier1_min_turnover'] ?? 0);
$t2_dep = intval($sets['ref_tier2_min_deposit'] ?? 50000);
$t2_to  = intval($sets['ref_tier2_min_turnover'] ?? 1000000);
// Format ke K (ribuan) buat display
$fmtK = function($v){ return number_format($v/1000,2,'.',','); };
?>
<div class="info-card" id="infoCard">
<!-- State 1: Sebelum claim tier 1 — syarat tier 1 (configurable) -->
<div id="infoTier1">
<h4>Apa itu jumlah orang yang dipromosikan yang valid?</h4>
<div class="sub">(Untuk hadiah pertama)</div>
<div class="ir"><span>Akumulasi pengisian ulang oleh subordinat ini</span><b>&ge;<?=$fmtK($t1_dep)?></b></div>
<?php if($t1_to>0):?>
<div class="ir"><span>Total taruhan yang sah oleh subordinat ini</span><b>&ge;<?=$fmtK($t1_to)?></b></div>
<?php endif;?>
</div>
<!-- State 2: Setelah claim tier 1 — syarat tier 2+ (configurable) -->
<div id="infoTier2" style="display:none">
<h4>Apa itu jumlah orang yang dipromosikan yang valid?</h4>
<div class="sub">(Memenuhi syarat-syarat berikut ini secara bersamaan)</div>
<div class="ir"><span>Akumulasi pengisian ulang oleh subordinat ini</span><b>&ge;<?=$fmtK($t2_dep)?></b></div>
<?php if($t2_to>0):?>
<div class="ir"><span>Total taruhan yang sah oleh subordinat ini</span><b>&ge;<?=$fmtK($t2_to)?></b></div>
<?php endif;?>
</div>
</div>

<div class="rules">
<h3>Peraturan Acara</h3>
<p>* 1.00 sebenarnya adalah 1 K, 1K = Rp 1,000</p>
<p>1. Undang teman untuk mengklaim bonus. Semakin banyak orang yang Anda undang, semakin banyak bonus yang akan Anda dapatkan;</p>
<p>2. Bonus perlu diklaim secara manual. Setelah kedaluwarsa, bonus akan didistribusikan secara otomatis dan dapat dinikmati bersama dengan bonus dan komisi dari agen lain;</p>
<p>3. Bonus (tidak termasuk pokok) memerlukan 1 kali taruhan yang valid untuk ditarik;</p>
<p>4. Hanya pemilik akun yang dapat melakukan operasi manual normal, jika tidak, bonus akan dibatalkan atau dikurangi, dibekukan, atau bahkan masuk daftar hitam;</p>
<p>5. Untuk menghindari perbedaan pemahaman teks, platform akan memiliki hak akhir untuk menafsirkan aktivitas ini.</p>
</div>

<!-- Detail Overlay -->
<div class="dt-overlay" id="dtOverlay">
<div class="dt-hdr"><button onclick="closeDetail()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button><h2>Detail</h2><div style="width:32px"></div></div>
<div class="dt-search"><input type="text" placeholder="Masukkan ID" id="dtSearch" oninput="filterDt()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></div>
<div class="dt-cols"><span>Waktu</span><span>ID</span><span>Setor</span><span>Setoran</span><span>Taruhan</span></div>
<div class="dt-body" id="dtBody"></div>
</div>

<?php echo renderBnav($db,"undang", $isLoggedIn); ?>

<script>
var DC=<?php echo $downCount; ?>;
var DL=<?php echo json_encode($downlines); ?>;
var REWARDS=[[1,50],[2,50],[3,50],[4,50],[5,50],[6,52],[7,54],[8,56],[9,58],[10,60],[20,500],[30,500],[40,500],[50,500],[60,500],[70,500],[80,500],[90,500],[100,500],[110,580],[120,580],[130,580],[140,580],[150,580],[160,580],[170,580],[180,580],[200,580],[300,5000],[400,5000],[500,8000],[600,8000],[700,8000],[800,13800],[900,13800],[1000,13800],[5000,29000],[10000,58000],[20000,138000]];
// PNG icon dari /img/rewards/ (di-set di PHP berdasarkan tema yang dipilih admin)
var IMG_CLOSED=<?php echo json_encode($themeImgClosed.'?v='.time()); ?>;
var IMG_OPEN  =<?php echo json_encode($themeImgOpen.'?v='.time()); ?>;

var claimedTiers=[];var validCount=0;var tier1Count=0;var tier1Claimed=false;
var rwPhones=['082***4521','081***7733','085***9012','087***3456','089***6677','083***1122','088***5544','082***8899','081***2233','086***4455','087***7890','089***3344','082***5566','085***1234','083***9988'];
function loadRewards(){
  renderChests();
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'ref_reward_status'})})
  .then(function(r){return r.json()}).then(function(d){
    if(d.ok){
      validCount=d.valid_count||0;
      claimedTiers=d.claimed||[];
      tier1Count=d.tier1_count||0;
      tier1Claimed=!!d.tier1_claimed;
      DC=d.total_count||DC;
      var i1=document.getElementById('infoTier1'),i2=document.getElementById('infoTier2');
      if(i1&&i2){
        if(tier1Claimed){i1.style.display='none';i2.style.display='block';}
        else{i1.style.display='block';i2.style.display='none';}
      }
      // Update stat cells
      var sv=document.querySelector('#statValid .sc-val');
      if(sv)sv.textContent=validCount;
      // Hitung total bonus yang sudah diklaim (dalam K)
      var totalBonus=0;
      (claimedTiers||[]).forEach(function(tier){
        for(var i=0;i<REWARDS.length;i++){
          if(REWARDS[i][0]===tier){totalBonus+=REWARDS[i][1];break;}
        }
      });
      var sb=document.querySelector('#statBonus .sc-val');
      if(sb)sb.textContent=totalBonus.toLocaleString('id-ID');
    }
    renderChests();
  }).catch(function(){});
}


function renderChests(){
  // Format nominal ke compact: 50000 → "50K", 500000 → "500K", 1000000 → "1Jt"
  function fmtCompact(rp){
    if(rp>=1000000){
      var jt=rp/1000000;
      return 'Rp '+(jt%1===0?jt:jt.toFixed(1).replace(/\.0$/,''))+'Jt';
    }
    if(rp>=1000) return 'Rp '+(rp/1000)+'K';
    return 'Rp '+rp;
  }
  var h='';
  REWARDS.forEach(function(r,i){
    var isClaimed=claimedTiers.indexOf(r[0])>-1;
    var effectiveValid=(r[0]===1)?tier1Count:validCount;
    var canClaim=effectiveValid>=r[0]&&!isClaimed;
    var cls='rw-card'+(isClaimed?' claimed':canClaim?' claimable':'');
    var imgSrc=isClaimed?IMG_OPEN:IMG_CLOSED;
    var rpAmt=r[1]*1000;
    h+='<div class="'+cls+'" onclick="claimChest('+r[0]+','+r[1]+',this)">';
    h+='<div class="rw-icon"><img src="'+imgSrc+'" alt="reward" loading="lazy"></div>';
    h+='<div class="rw-val">'+fmtCompact(rpAmt)+'</div>';
    h+='<div class="rw-lbl">Undang <b>'+r[0]+'</b> orang</div>';
    h+='</div>';
  });
  document.getElementById('rwGrid').innerHTML=h;
  // Sparkles
  var wrap=document.getElementById('rwWrap');
  wrap.querySelectorAll('.sparkle').forEach(function(s){s.remove()});
  for(var i=0;i<14;i++){var sp=document.createElement('div');sp.className='sparkle';sp.style.left=(5+Math.random()*90)+'%';sp.style.top=(5+Math.random()*90)+'%';sp.style.animationDelay=(-Math.random()*2.5)+'s';sp.style.width=sp.style.height=(2+Math.random()*3)+'px';wrap.appendChild(sp)}
}
function claimChest(tier,amtK,el){
  if(el.classList.contains('claimed')){showToast('Reward ini sudah diklaim');return}
  if(!el.classList.contains('claimable')){
    if(tier===1)showToast('Belum ada downline dengan deposit minimal Rp 100.000');
    else showToast('Belum cukup downline valid untuk tier ini');
    return;
  }
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'claim_ref_reward',tier:tier})})
  .then(function(r){return r.json()}).then(function(d){
    if(d.ok){
      showToast('Reward Rp '+Number(d.amount).toLocaleString('id')+' masuk ke saldo!');
      claimedTiers.push(tier);
      // Kalau tier 1 baru diklaim, switch kotak syarat jadi versi normal
      if(tier===1){
        tier1Claimed=true;
        var i1=document.getElementById('infoTier1'),i2=document.getElementById('infoTier2');
        if(i1&&i2){i1.style.display='none';i2.style.display='block';}
      }
      renderChests();
    }else{showToast(d.error||'Gagal klaim')}
  }).catch(function(){showToast('Gagal. Silakan login.')});
}
loadRewards();



function openDetail(){
  document.getElementById('dtOverlay').classList.add('open');
  // Tampilkan loading dulu
  document.getElementById('dtBody').innerHTML='<div class="dt-empty" style="padding:40px 20px;text-align:center"><div style="width:32px;height:32px;border:3px solid rgba(var(--sec-rgb),.2);border-top-color:var(--sec,var(--sec));border-radius:50%;margin:0 auto 12px;animation:spin 1s linear infinite"></div><p style="color:var(--t2);font-size:.75rem">Sinkronisasi taruhan...</p></div>';
  // Fetch data fresh (sync TO NexusGGR di backend)
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'referral_downline'})})
  .then(function(r){return r.json()}).then(function(d){
    if(d.ok&&d.downlines){
      DL=d.downlines; // update global DL biar filterDt juga pake data fresh
      renderDt(DL);
    }else{
      renderDt(DL); // fallback
    }
  }).catch(function(){renderDt(DL);});
}
function closeDetail(){document.getElementById('dtOverlay').classList.remove('open')}
function renderDt(list){
  if(!list.length){
    document.getElementById('dtBody').innerHTML='<div class="dt-empty"><svg viewBox="0 0 120 120" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="25" y="35" width="70" height="50" rx="6"/><line x1="35" y1="50" x2="85" y2="50"/><line x1="35" y1="60" x2="75" y2="60"/><line x1="35" y1="70" x2="65" y2="70"/></svg><p>Tidak ada catatan</p></div>';return;
  }
  var h='';list.forEach(function(d){
    h+='<div class="dt-row"><span>'+d.created_at.substring(0,10)+'</span><span>'+d.username+'</span><span>'+(d.total_deposit>0?'Y':'N')+'</span><span>'+Number(d.total_deposit||0).toLocaleString('id')+'</span><span>'+Number(d.total_turnover||0).toLocaleString('id')+'</span></div>';
  });
  document.getElementById('dtBody').innerHTML=h;
}
function filterDt(){
  var q=document.getElementById('dtSearch').value.toLowerCase();
  var f=DL.filter(function(d){return d.username.toLowerCase().indexOf(q)>-1});
  renderDt(f);
}
function copyLink(){
  var url=document.getElementById('refUrl').textContent;
  if(navigator.clipboard){navigator.clipboard.writeText(url).then(function(){showToast('Link berhasil disalin!')}).catch(function(){fallbackCopy(url)})}
  else fallbackCopy(url);
}
function fallbackCopy(t){var a=document.createElement('textarea');a.value=t;document.body.appendChild(a);a.select();document.execCommand('copy');document.body.removeChild(a);showToast('Link berhasil disalin!')}
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
</script>
<?php include 'includes/credit_notify.php'; ?>
</body>
</html>
