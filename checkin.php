<?php
require_once 'includes/config.php';
$isLoggedIn = (bool)getUid();  // guest-friendly

header('Content-Type: text/html; charset=UTF-8');
$uid=getUid();
$sets=[];
try{$st=$db->query("SELECT `key`,`value` FROM settings");foreach($st->fetchAll() as $r)$sets[$r['key']]=$r['value'];normSettings($sets);}catch(Exception $e){}
$sn=$sets['site_name']??'Situs';
if(!$uid){header('Location: index.php');exit;}
?><!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">
<?php require_once 'pwa_head.php'; ?>
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="theme.php?v=<?=time()?>">
<title>Check-in 7 Hari - <?=htmlspecialchars($sn)?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
html{font-size:16px!important;-webkit-text-size-adjust:100%;text-size-adjust:100%}
body{font-family:'Poppins',sans-serif;background:var(--bg);color:var(--t);min-height:100vh;overflow-x:hidden;padding-bottom:30px}

.hdr{display:flex;align-items:center;padding:14px 16px;position:sticky;top:0;z-index:10;background:var(--bg)}
.hdr .bk{width:34px;height:34px;background:var(--s);border:1px solid var(--bd);border-radius:10px;color:var(--t2);cursor:pointer;display:flex;align-items:center;justify-content:center}
.hdr .bk:active{transform:scale(.92)}
.hdr h1{flex:1;text-align:center;font-size:1rem;font-weight:800;color:var(--t)}

.hero{margin:4px 16px 16px;padding:16px;background:var(--s);border:1px solid var(--bd);border-radius:14px}
.hero-row{display:flex;justify-content:space-between;align-items:center;gap:12px}
.hero-col{flex:1;text-align:center}
.hero-col .lbl{font-size:.6rem;color:var(--t3);font-weight:600;letter-spacing:.5px;text-transform:uppercase;margin-bottom:4px}
.hero-col .val{font-size:1rem;font-weight:800;color:var(--pri)}
.hero-col .val.sm{font-size:.82rem}
.hero-divider{width:1px;height:32px;background:var(--bd)}

.streak-info{margin:0 16px 14px;text-align:center;font-size:.78rem;color:var(--t2);font-weight:600}
.streak-info b{color:var(--pri)}

.grid7{margin:0 16px 16px;display:grid;grid-template-columns:repeat(4,1fr);gap:8px}
.dcard{position:relative;padding:10px 6px;background:var(--s);border:1.5px solid var(--bd);border-radius:12px;text-align:center;transition:all .2s;min-height:92px;display:flex;flex-direction:column;justify-content:center;align-items:center}
.dcard.done{background:var(--s);border-color:var(--pri);opacity:.65}
.dcard.today{background:var(--s);border-color:var(--pri);border-width:2px}
.dcard.today::before{content:'HARI INI';position:absolute;top:-8px;left:50%;transform:translateX(-50%);background:var(--pri);color:#fff;font-size:.52rem;font-weight:800;padding:2px 8px;border-radius:10px;letter-spacing:.5px;white-space:nowrap}
.dcard.big{grid-column:span 2;min-height:92px}
.dcard.big .d-val{font-size:1.1rem}
.dcard .d-lbl{font-size:.55rem;font-weight:700;color:var(--t3);letter-spacing:.5px;margin-bottom:5px}
.dcard.done .d-lbl,.dcard.today .d-lbl{color:var(--pri)}
.dcard .d-ico{margin:2px 0 5px;color:var(--pri);display:flex;align-items:center;justify-content:center;height:24px}
.dcard .d-val{font-size:.9rem;font-weight:800;color:var(--t);line-height:1;margin-bottom:3px}
.dcard .d-k{font-size:.55rem;color:var(--t3);font-weight:600;letter-spacing:.5px}
.dcard .check{position:absolute;top:4px;right:4px;width:16px;height:16px;background:var(--pri);border-radius:50%;display:none;align-items:center;justify-content:center;z-index:2}
.dcard.done .check{display:flex}

.btn-claim{display:block;width:calc(100% - 32px);margin:6px 16px 16px;padding:15px;background:var(--pri);border:none;border-radius:12px;color:#fff;font-size:.95rem;font-weight:800;cursor:pointer;font-family:inherit;letter-spacing:.3px}
.btn-claim:active{transform:scale(.98)}
.btn-claim:disabled{background:var(--bd);color:var(--t3);cursor:not-allowed}
.btn-claim .rw-num{font-size:1.1rem;margin-left:4px}

.rul{margin:8px 16px;padding:14px;background:var(--s);border:1px solid var(--bd);border-radius:12px}
.rul h3{font-size:.82rem;font-weight:800;text-align:center;margin-bottom:10px;color:var(--t)}
.rul .nt{background:var(--bg2);border:1px solid var(--bd);border-radius:6px;padding:8px 10px;font-size:.65rem;color:var(--pri);margin-bottom:10px;line-height:1.5;text-align:center}
.rul p{font-size:.68rem;color:var(--t2);line-height:1.6;margin-bottom:6px}

.loading{text-align:center;padding:40px 20px;color:var(--t3);font-size:.8rem}

@keyframes skelSweep{0%{background-position:-200% 0}100%{background-position:200% 0}}
.checkin-skel{padding:14px}
.checkin-skel > div{background:linear-gradient(90deg,var(--bg2) 25%,var(--tint-2) 50%,var(--bg2) 75%);background-size:200% 100%;animation:skelSweep 1.4s ease-in-out infinite;border-radius:12px}
.cs-hero{height:80px;margin-bottom:14px}
.cs-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;background:transparent !important;animation:none !important}
.cs-grid .cs-card{background:linear-gradient(90deg,var(--bg2) 25%,var(--tint-2) 50%,var(--bg2) 75%);background-size:200% 100%;animation:skelSweep 1.4s ease-in-out infinite;border-radius:10px;height:100px}
.cs-grid .cs-big{grid-column:span 3;height:80px}
.cs-btn{height:48px;margin-top:14px}
</style></head><body>


<?php if(!$isLoggedIn){ $guestBannerLabel = 'check-in harian'; require __DIR__.'/includes/guest_banner.php'; } ?>
<div class="hdr">
  <button class="bk" onclick="history.back()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><polyline points="15 18 9 12 15 6"/></svg></button>
  <h1>Check-in 7 Hari</h1>
  <div style="width:34px"></div>
</div>

<div id="root">
  <div class="checkin-skel">
    <div class="cs-hero"></div>
    <div class="cs-grid">
      <div class="cs-card"></div>
      <div class="cs-card"></div>
      <div class="cs-card"></div>
      <div class="cs-card"></div>
      <div class="cs-card"></div>
      <div class="cs-card"></div>
      <div class="cs-card cs-big"></div>
    </div>
    <div class="cs-btn"></div>
  </div>
</div>

<script>
var data={};

function fmt(n){return n.toLocaleString('id-ID')}

function render(){
  var root=document.getElementById('root');
  var vipLv=data.vip_level||0;
  var to7=Math.floor((data.turnover_7d||0)/1000);
  var rewards=data.day_rewards||[6,6,6,6,6,28,51];
  var nextDay=data.next_streak_day||1;
  var canClaim=!!data.can_claim;
  var hist=data.history||[];
  var claimedStreakDays={};
  hist.forEach(function(h){claimedStreakDays[h.streak_day]=true});

  var html='';

  html+='<div class="hero"><div class="hero-row">';
  html+='<div class="hero-col"><div class="lbl">LEVEL VIP</div><div class="val">V'+vipLv+'</div></div>';
  html+='<div class="hero-divider"></div>';
  html+='<div class="hero-col"><div class="lbl">Turnover 7 Hari</div><div class="val sm">Rp '+fmt(to7*1000)+'</div></div>';
  html+='</div></div>';

  var streakTxt=canClaim?'Check-in hari ke-<b>'+nextDay+'</b> tersedia':
                 (data.already_today?'Sudah check-in hari ini. Kembali besok!':'Check-in belum tersedia');
  html+='<div class="streak-info">'+streakTxt+'</div>';

  html+='<div class="grid7">';
  for(var d=1;d<=7;d++){
    var isBig=(d===6||d===7);
    var rewardK=rewards[d-1];
    var classes='dcard'+(isBig?' big':'');

    var isDone=!!claimedStreakDays[d];
    var isToday=(canClaim&&d===nextDay);
    if(isDone)classes+=' done';
    if(isToday)classes+=' today';

    var icon;
    if(d<=2)icon='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" width="22" height="22"><circle cx="12" cy="12" r="8"/></svg>';
    else if(d<=5)icon='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" width="22" height="22"><path d="M12 2l3 6 7 1-5 5 1 7-6-3-6 3 1-7-5-5 7-1z"/></svg>';
    else if(d===6)icon='<svg viewBox="0 0 24 24" fill="currentColor" width="24" height="24"><path d="M12 2l2.4 7.4H22l-6.2 4.5 2.4 7.4L12 16.8 5.8 21.3l2.4-7.4L2 9.4h7.6z"/></svg>';
    else icon='<svg viewBox="0 0 24 24" fill="currentColor" width="26" height="26"><path d="M20 6h-4V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2H4a1 1 0 00-1 1v11a3 3 0 003 3h12a3 3 0 003-3V7a1 1 0 00-1-1zm-9-2h2v2h-2V4z"/></svg>';

    html+='<div class="'+classes+'">';
    html+='<div class="check"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="3" width="10" height="10"><polyline points="20 6 9 17 4 12"/></svg></div>';
    html+='<div class="d-lbl">Hari '+d+'</div>';
    html+='<div class="d-ico">'+icon+'</div>';
    html+='<div class="d-val">'+rewardK+'K</div>';
    html+='<div class="d-k">= Rp '+fmt(rewardK*1000)+'</div>';
    html+='</div>';
  }
  html+='</div>';

  if(canClaim){
    var todayReward=rewards[nextDay-1];
    html+='<button class="btn-claim" onclick="doClaim(this)">Klaim Hari '+nextDay+' <span class="rw-num">Rp '+fmt(todayReward*1000)+'</span></button>';
  }else if(data.already_today){
    html+='<button class="btn-claim" disabled><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="14" height="14" style="vertical-align:-2px;display:inline-block"><polyline points="20 6 9 17 4 12"/></svg> Sudah Check-in Hari Ini</button>';
  }

  html+='<div class="rul"><h3>Peraturan Acara</h3>';
  html+='<div class="nt">1K = Rp 1.000 · Hadiah masuk ke saldo utama</div>';
  html+='<p>1. Check-in 7 hari berturut-turut, bonus makin besar di hari ke-6 & 7.</p>';
  html+='<p>2. Kalau skip 1 hari, streak reset ke hari 1 lagi.</p>';
  html+='<p>3. Wajib minimal 1x bet valid dalam 7 hari terakhir untuk klaim.</p>';
  html+='<p>4. Bonus memerlukan 1x turnover untuk bisa ditarik.</p>';
  html+='<p>5. Besar bonus disesuaikan level VIP berdasarkan total deposit.</p>';
  html+='</div>';

  root.innerHTML=html;
}

function loadData(){
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'daily_checkin'})})
    .then(function(r){return r.json()})
    .then(function(d){
      if(!d.ok){if(window.handleAuthError&&handleAuthError(d))return;document.getElementById('root').innerHTML='<div class="loading">Gagal memuat: '+(d.error||'unknown')+'</div>';return}
      data=d;
      render();
    })
    .catch(function(){document.getElementById('root').innerHTML='<div class="loading">Gagal memuat</div>'});
}

function doClaim(btn){
  btn.disabled=true;
  btn.textContent='Memproses...';
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'daily_checkin',claim:true})})
    .then(function(r){return r.json()})
    .then(function(d){
      if(!d.ok){if(window.handleAuthError&&handleAuthError(d))return;showToast(d.error||'Gagal klaim');btn.disabled=false;loadData();return}
      showToast('Berhasil klaim Rp '+(d.reward||0).toLocaleString('id-ID'));
      loadData();
    })
    .catch(function(){showToast('Gagal terhubung');btn.disabled=false});
}

function showToast(msg){
  var t=document.createElement('div');
  t.style.cssText='position:fixed;top:20%;left:50%;transform:translateX(-50%);background:var(--s);color:var(--t);padding:12px 20px;border-radius:10px;font-size:.85rem;font-weight:600;z-index:9999;box-shadow:0 4px 16px rgba(0,0,0,.3);border:1px solid var(--bd);max-width:300px;text-align:center';
  t.textContent=msg;
  document.body.appendChild(t);
  setTimeout(function(){t.remove()},2500);
}

loadData();
</script>

<?php include 'includes/credit_notify.php'; ?>
</body></html>
