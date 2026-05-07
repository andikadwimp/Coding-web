<?php
require_once 'includes/config.php';
header('Content-Type: text/html; charset=UTF-8');
$sets=[];
try{$st=$db->query("SELECT `key`,`value` FROM settings");foreach($st->fetchAll() as $r)$sets[$r['key']]=$r['value'];normSettings($sets);}catch(Exception $e){}
$sn=$sets['site_name']??'Situs';
$logo=$sets['logo_url']??'';
$ref=htmlspecialchars($_GET['ref']??'');
// Fallback: kalau ga ada ref di URL tapi cookie masih ada (sticky invite flow)
if(!$ref&&!empty($_COOKIE['ref_code'])){
    $ref=htmlspecialchars(preg_replace('/[^A-Za-z0-9_-]/','',$_COOKIE['ref_code']));
}
if($ref){
    setcookie('ref_code',$ref,time()+2592000,'/','',false,false);
}
// Entry sticky: user yg landing di invite.php → cookie lx_entry=invite
// Next visit ke domain root → redirect balik ke invite.php
setcookie('lx_entry','invite',time()+2592000,'/','',false,false);

$hotGames=[];
try{
  $hg=$db->query("SELECT game_code,game_name,banner,provider_code FROM games WHERE status=1 AND banner IS NOT NULL AND banner!='' ORDER BY RAND() LIMIT 8");
  $hotGames=$hg->fetchAll();
}catch(Exception $e){}

$jackpot=$sets['jackpot_amount']??'57338479876';
?><!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">
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
<?php require_once 'pwa_head.php'; ?>
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700;800;900&family=Chakra+Petch:wght@700;800;900&display=swap" rel="stylesheet">
<title><?=$sn?> - Official Mobile Site</title>
<style>
:root{--gold:#fbbf24;--gold-dark:#b45309;--primary:#2d1566;--dark:#0f0524;--dark-2:#1a0b3d}
*{margin:0;padding:0;box-sizing:border-box}
html{font-size:16px!important;-webkit-text-size-adjust:100%;text-size-adjust:100%}
body{font-family:'Outfit',sans-serif;color:#fff;background:var(--dark);overflow-x:hidden;padding-bottom:110px;min-height:100vh}

.bg-glow{position:fixed;inset:0;background:radial-gradient(circle at 50% 0%,#3b1f82 0%,var(--dark) 70%);z-index:-2}
.bg-particles{position:fixed;inset:0;z-index:-1;background-image:
  radial-gradient(1.5px 1.5px at 15% 20%,rgba(251,191,36,.5),transparent),
  radial-gradient(2px 2px at 70% 40%,var(--bd2),transparent),
  radial-gradient(1px 1px at 40% 70%,var(--bd2),transparent),
  radial-gradient(1.5px 1.5px at 85% 85%,rgba(251,191,36,.4),transparent),
  radial-gradient(1px 1px at 25% 90%,var(--bd2),transparent);
  pointer-events:none}

.hero{position:relative;padding:22px 16px 0;text-align:center;z-index:2}
.hero-logo-box{height:50px;margin-bottom:12px;display:flex;align-items:center;justify-content:center;filter:drop-shadow(0 0 14px rgba(251,191,36,.35))}
.hero-logo{height:100%;width:auto;object-fit:contain;max-width:200px}
.hero-fallback{font-family:'Chakra Petch';color:var(--gold);font-weight:900;font-size:1.9rem;letter-spacing:2px;text-shadow:0 0 30px rgba(251,191,36,.5)}
.badge-tag{background:var(--tint-1);border:1px solid var(--tint-2);padding:6px 18px;border-radius:50px;font-size:.66rem;display:inline-block;color:#cbb5ff;font-weight:600}

.chars-container{position:relative;height:340px;margin:20px -16px 0;perspective:1000px}
.chars-container img{position:absolute;bottom:0;filter:drop-shadow(0 15px 25px rgba(0,0,0,.6))}
.c-main{left:50%;transform:translateX(-50%);height:320px;z-index:10;animation:floatMain 3.5s infinite ease-in-out}
.c-side-l{left:-5px;height:240px;z-index:5;opacity:.85;animation:floatSide 4s infinite ease-in-out}
.c-side-r{right:-5px;height:240px;z-index:5;opacity:.85;animation:floatSide 4s infinite ease-in-out .5s}
.gold-pile{position:absolute;bottom:-2px;left:0;right:0;height:100px;background:linear-gradient(0deg,var(--dark) 10%,transparent);z-index:15;pointer-events:none}
@keyframes floatMain{0%,100%{transform:translateX(-50%) translateY(0)}50%{transform:translateX(-50%) translateY(-15px)}}
@keyframes floatSide{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}

.brand-title{text-align:center;margin:-10px 0 4px;position:relative;z-index:20}
.brand-title h1{font-family:'Chakra Petch';font-size:2.8rem;font-weight:900;background:linear-gradient(180deg,#fff 30%,var(--gold) 80%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;letter-spacing:3px;text-shadow:0 0 40px rgba(251,191,36,.3);line-height:1}
.brand-sub{font-size:.72rem;color:rgba(255,255,255,.6);margin-top:6px;font-weight:600;letter-spacing:.5px}

.jackpot-box{margin:20px 16px 0;background:linear-gradient(135deg,rgba(251,191,36,.08),var(--tint-1));border:1px solid rgba(251,191,36,.28);border-radius:20px;padding:22px;text-align:center;position:relative;box-shadow:inset 0 0 30px rgba(251,191,36,.06),0 10px 30px rgba(0,0,0,.3);overflow:hidden}
.jackpot-box::before{content:'';position:absolute;top:-1px;left:50%;transform:translateX(-50%);width:60%;height:1px;background:linear-gradient(90deg,transparent,var(--gold),transparent)}
.jackpot-box::after{content:'';position:absolute;inset:-50%;background:radial-gradient(circle,rgba(251,191,36,.08),transparent 60%);animation:glowPulse 4s infinite;pointer-events:none}
@keyframes glowPulse{0%,100%{opacity:.5}50%{opacity:1}}
.jp-title{font-size:.68rem;color:#999;letter-spacing:2.5px;font-weight:700;margin-bottom:4px;position:relative;z-index:1}
.jp-over{font-size:.58rem;color:var(--gold);margin-bottom:4px;text-transform:uppercase;font-weight:700;letter-spacing:1.5px;position:relative;z-index:1}
.jp-amount{font-family:'Chakra Petch';font-size:1.7rem;font-weight:900;background:linear-gradient(180deg,#fff 30%,var(--gold) 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;letter-spacing:1px;position:relative;z-index:1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.jp-subtitle{font-size:.6rem;color:var(--bd2);margin-top:8px;position:relative;z-index:1}

.spin-cta{margin:20px 16px 0;display:flex;align-items:center;gap:14px;background:linear-gradient(135deg,rgba(45,21,102,.9),rgba(30,10,74,.9));padding:14px;border-radius:18px;border:1px solid var(--tint-2);box-shadow:0 10px 30px rgba(0,0,0,.3);text-decoration:none;color:#fff;cursor:pointer}
.spin-cta:active{transform:scale(.98)}
.spin-cta .si-box{width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#ef4444,#dc2626);display:flex;align-items:center;justify-content:center;flex-shrink:0;animation:pulseBox 2s infinite}
.spin-cta .si-box svg{width:24px;height:24px;color:#fff;filter:drop-shadow(0 2px 4px rgba(0,0,0,.3))}
@keyframes pulseBox{0%,100%{box-shadow:0 0 0 0 rgba(239,68,68,.5)}50%{box-shadow:0 0 0 10px rgba(239,68,68,0)}}
.spin-cta .sc-title{font-weight:800;font-size:.85rem;color:#fff;margin-bottom:2px}
.spin-cta .sc-sub{font-size:.65rem;color:rgba(255,255,255,.55)}

.sec{padding:24px 16px 0;position:relative;z-index:2}
.sec-title{font-weight:900;font-size:.92rem;color:#fff;margin-bottom:14px;display:flex;align-items:center;gap:12px;letter-spacing:1.5px;text-transform:uppercase}
.sec-title::after{content:'';height:1px;background:linear-gradient(90deg,var(--tint-2),transparent);flex:1}
.sec-title-gold{background:linear-gradient(135deg,#fbbf24,#f59e0b,#fbbf24);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}

.game-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px}
.game-card{border-radius:12px;overflow:hidden;background:var(--dark-2);border:1px solid var(--tint-2);transition:transform .2s;position:relative;cursor:pointer}
.game-card:active{transform:scale(.93)}
.game-card img,.game-card .gc-placeholder{width:100%;aspect-ratio:1;object-fit:cover;display:block}
.gc-placeholder{display:flex;align-items:center;justify-content:center;padding:6px;text-align:center;font-size:.55rem;font-weight:800;line-height:1.2;color:#fff;text-shadow:0 1px 3px rgba(0,0,0,.5)}
.gn{padding:5px 4px;font-size:.5rem;font-weight:600;text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:#bbb;background:rgba(0,0,0,.3)}
.hot-badge{position:absolute;top:4px;right:4px;background:linear-gradient(135deg,#ef4444,#dc2626);font-size:.42rem;font-weight:800;padding:2px 5px;border-radius:4px;text-transform:uppercase;letter-spacing:.5px;box-shadow:0 2px 6px rgba(239,68,68,.4)}

.feat-card{margin-bottom:12px;padding:16px;background:linear-gradient(135deg,var(--tint-1),var(--tint-1));border:1px solid var(--tint-2);border-radius:16px;display:flex;align-items:center;gap:14px;backdrop-filter:blur(4px)}
.feat-card .fi{width:54px;height:54px;border-radius:14px;background:linear-gradient(135deg,rgba(251,191,36,.18),rgba(251,191,36,.04));border:1px solid rgba(251,191,36,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.feat-card .fi svg{width:26px;height:26px;color:var(--gold)}
.feat-card h3{font-size:.8rem;font-weight:800;margin-bottom:3px;letter-spacing:.2px}
.feat-card p{font-size:.62rem;color:var(--bd2);line-height:1.5}

.trust-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:8px}
.tb{display:flex;align-items:center;gap:8px;padding:11px;background:var(--tint-1);border:1px solid var(--tint-2);border-radius:12px}
.tb svg{width:18px;height:18px;color:var(--gold);flex-shrink:0}
.tb span{font-size:.56rem;font-weight:600;color:rgba(255,255,255,.7);line-height:1.3}

.about-h{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.about-h img{width:42px;height:42px;border-radius:11px;object-fit:contain;border:2px solid rgba(251,191,36,.25)}
.about-h h2{font-size:1rem;font-weight:800}
.about-txt{font-size:.66rem;color:var(--bd2);line-height:1.65}

.social-float{position:fixed;right:10px;top:42%;z-index:95;display:flex;flex-direction:column;gap:10px}
.social-item{width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 12px rgba(0,0,0,.4);transition:transform .2s;text-decoration:none}
.social-item:active{transform:scale(.9)}
.social-item svg{width:20px;height:20px}

.footer-cta{position:fixed;bottom:0;left:0;width:100%;padding:14px 16px 18px;background:linear-gradient(0deg,var(--dark) 60%,transparent);z-index:100}
.unduh-btn{width:100%;background:linear-gradient(135deg,#22c55e,#16a34a,#15803d);padding:18px;border-radius:18px;border:none;color:#fff;font-family:'Outfit',sans-serif;font-weight:900;font-size:1.12rem;display:flex;align-items:center;justify-content:center;gap:12px;box-shadow:0 12px 35px rgba(22,163,74,.45),0 0 0 1px var(--tint-2) inset;cursor:pointer;position:relative;overflow:hidden;letter-spacing:.3px}
.unduh-btn::before{content:'';position:absolute;top:0;left:-100%;width:40%;height:100%;background:linear-gradient(90deg,transparent,var(--bd2),transparent);animation:btnShine 2.2s infinite}
@keyframes btnShine{0%{left:-100%}60%,100%{left:150%}}
.unduh-btn::after{content:'';position:absolute;top:1px;left:10%;right:10%;height:1px;background:linear-gradient(90deg,transparent,var(--bd2),transparent)}
.unduh-btn svg{width:24px;height:24px;animation:arrowBounce 1.8s infinite}
@keyframes arrowBounce{0%,100%{transform:translateY(-3px)}50%{transform:translateY(3px)}}
</style>
</head>
<body>

<div class="bg-glow"></div>
<div class="bg-particles"></div>

<div class="social-float">
<?php if(!empty($sets['wa_url'])):?><a class="social-item" style="background:#25D366" href="<?=htmlspecialchars($sets['wa_url'])?>" target="_blank"><svg viewBox="0 0 24 24" fill="#fff"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15s-.77.97-.94 1.16c-.17.2-.35.22-.64.07-.3-.15-1.26-.46-2.39-1.47-.88-.79-1.48-1.76-1.65-2.06s-.02-.46.13-.6c.13-.14.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52s-.67-1.61-.92-2.2c-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37s-1.04 1.02-1.04 2.48 1.07 2.88 1.21 3.07c.15.2 2.1 3.2 5.08 4.49.71.3 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35z"/></svg></a><?php endif;?>
<?php if(!empty($sets['tg_url'])):?><a class="social-item" style="background:#0088CC" href="<?=htmlspecialchars($sets['tg_url'])?>" target="_blank"><svg viewBox="0 0 24 24" fill="#fff"><path d="M12 0C5.37 0 0 5.37 0 12s5.37 12 12 12 12-5.37 12-12S18.63 0 12 0zm5.95 8.16l-1.95 9.2c-.15.67-.55.83-1.11.52l-3.07-2.26-1.48 1.42c-.16.16-.3.3-.62.3l.22-3.12 5.68-5.13c.25-.22-.05-.34-.38-.13l-7.02 4.42-3.02-.94c-.66-.2-.67-.66.14-.97l11.8-4.55c.55-.2 1.03.13.81.94z"/></svg></a><?php endif;?>
<?php if(!empty($sets['fb_url'])):?><a class="social-item" style="background:#1877F2" href="<?=htmlspecialchars($sets['fb_url'])?>" target="_blank"><svg viewBox="0 0 24 24" fill="#fff"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg></a><?php endif;?>
<a class="social-item" style="background:var(--tint-2);border:1px solid var(--tint-2)" href="cs_chat.php"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><path d="M3 18v-6a9 9 0 0118 0v6"/><path d="M21 19a2 2 0 01-2 2h-1a2 2 0 01-2-2v-3a2 2 0 012-2h3zM3 19a2 2 0 002 2h1a2 2 0 002-2v-3a2 2 0 00-2-2H3z"/></svg></a>
</div>

<div class="hero">
  <div class="hero-logo-box">
    <?php if($logo):?><img class="hero-logo" src="<?=htmlspecialchars($logo)?>" alt="<?=$sn?>">
    <?php else:?><div class="hero-fallback"><?=$sn?></div>
    <?php endif;?>
  </div>
  <div class="badge-tag">Aplikasi Game Terlengkap #1 Indonesia</div>

  <div class="chars-container">
    <img src="asset/char2.png" class="c-side-l" alt="">
    <img src="asset/char3.png" class="c-main" alt="">
    <img src="asset/char1.png" class="c-side-r" alt="">
    <div class="gold-pile"></div>
  </div>
</div>

<div class="brand-title">
  <h1><?=$sn?></h1>
  <div class="brand-sub">100+ Casino &bull; Slot Gacor &bull; Live Casino</div>
</div>

<div class="jackpot-box">
  <div class="jp-title">JACKPOT TERSEDIA</div>
  <div class="jp-over">Jackpot over</div>
  <div class="jp-amount" id="jpAmt">Rp <?=number_format(intval($jackpot),0,',','.')?></div>
  <div class="jp-subtitle">Cash Daily Rank Rewards: 185.000+</div>
</div>

<a class="spin-cta" href="javascript:installApp()">
  <div class="si-box"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg></div>
  <div>
    <div class="sc-title">SPIN GRATIS + Bonus Rp 50.000!</div>
    <div class="sc-sub">Install sekarang dan klaim bonus pertama Anda</div>
  </div>
</a>

<div class="sec">
  <div class="sec-title sec-title-gold">Hot Game Hari Ini</div>
  <div class="game-grid">
    <?php if(count($hotGames)):foreach($hotGames as $i=>$g):?>
    <div class="game-card" onclick="installApp()">
      <img src="<?=htmlspecialchars($g['banner'])?>" alt="<?=htmlspecialchars($g['game_name'])?>" loading="lazy" onerror="this.parentElement.style.display='none'">
      <?php if($i<3):?><div class="hot-badge">HOT</div><?php endif;?>
      <div class="gn"><?=htmlspecialchars($g['game_name'])?></div>
    </div>
    <?php endforeach;else:
    $fallback=[
      ['Sweet Bonanza','#e91e63','#ff5722'],['Gates of Olympus','#1565c0','#42a5f5'],
      ['Starlight Princess','#7b1fa2','#ce93d8'],['Mahjong Ways','#2e7d32','#66bb6a'],
      ['Wild West Gold','#e65100','#ff9800'],['Great Rhino','#33691e','#8bc34a'],
      ['Sugar Rush','#c2185b','#f48fb1'],['Aztec Gems','#bf360c','#ff7043']
    ];
    foreach($fallback as $i=>$f):?>
    <div class="game-card" onclick="installApp()">
      <div class="gc-placeholder" style="background:linear-gradient(135deg,<?=$f[1]?>,<?=$f[2]?>)"><?=$f[0]?></div>
      <?php if($i<3):?><div class="hot-badge">HOT</div><?php endif;?>
      <div class="gn"><?=$f[0]?></div>
    </div>
    <?php endforeach;endif;?>
  </div>
</div>

<div class="sec">
  <div class="sec-title sec-title-gold">Keunggulan Kami</div>
  <div class="feat-card">
    <div class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg></div>
    <div><h3>Hadiah Melimpah</h3><p>Jackpot permainan putaran lebih dari Rp <?=number_format(intval($jackpot),0,',','.')?>. Nikmati permainan uang nyata dan bonus 100%.</p></div>
  </div>
  <div class="feat-card">
    <div class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg></div>
    <div><h3>Undang Teman, Cuan Berlipat</h3><p>Undang satu anggota valid dapat BONUS. Ditambah komisi dari setiap taruhan teman Anda.</p></div>
  </div>
  <div class="feat-card">
    <div class="fi"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
    <div><h3>Keamanan Terjamin</h3><p>Tidak ada penipuan. Transaksi 100% aman, privasi data & identitas Anda sepenuhnya dilindungi.</p></div>
  </div>
</div>

<div class="sec">
  <div class="trust-grid">
    <div class="tb"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/></svg><span>10 Juta+ Pengguna Terpercaya</span></div>
    <div class="tb"><svg viewBox="0 0 24 24" fill="currentColor"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3" stroke="#0f0524" stroke-width="2"/></svg><span>Khusus 18 Tahun Ke Atas</span></div>
    <div class="tb"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><span>Sistem RNG Adil & Terpercaya</span></div>
    <div class="tb"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg><span>Penarikan Cepat Tanpa Ribet</span></div>
  </div>
</div>

<div class="sec">
  <div class="about-h">
    <?php if($logo):?><img src="<?=htmlspecialchars($logo)?>" alt="<?=$sn?>"><?php endif;?>
    <h2>Tentang <?=$sn?></h2>
  </div>
  <p class="about-txt"><?=$sn?> adalah aplikasi game terbesar di Indonesia dengan 100+ provider, turnamen eksklusif, dan format terlengkap. Tersedia untuk pengguna berusia 18 tahun ke atas. Unduh aplikasi untuk pengalaman gaming terbaik dengan bonus harian dan jackpot besar menanti.</p>
</div>

<div class="footer-cta">
  <button class="unduh-btn" onclick="installApp()">
    Install Aplikasi
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14l-7 7m0 0l-7-7m7 7V3"/></svg>
  </button>
</div>

<script>
var ref='<?=$ref?>';
if(ref){
  document.cookie='ref_code='+ref+';path=/;max-age=2592000;SameSite=Lax';
  try{localStorage.setItem('ref_code',ref)}catch(e){}
}

var jpBase=<?=intval($jackpot)?>;
setInterval(function(){
  jpBase+=Math.floor(Math.random()*1800)+600;
  var el=document.getElementById('jpAmt');
  if(el)el.textContent='Rp '+jpBase.toLocaleString('id');
},2000);

// Set window.__refCode biar pwa_install.php bisa baca (redirect bawa ref)
window.__refCode=ref||'';
</script>

<?php include 'includes/pwa_install.php'; ?>


<?php include 'includes/credit_notify.php'; ?>
</body>
</html>
