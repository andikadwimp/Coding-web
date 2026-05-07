<?php
/**
 * CLEAR CACHE endpoint
 * URL: /clearcache.php atau /clearcache (via .htaccess)
 * 
 * Fungsi:
 * - Unregister service worker
 * - Delete semua cache API (PWA cache)
 * - Clear localStorage + sessionStorage
 * - HAPUS semua cookie kecuali lx_token (login) — user ga perlu login ulang
 * - Reload ke home dengan cache-busting query string
 * 
 * Bisa diakses tanpa auth, ga kena gate redirect.
 */
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate');
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Clear Cache</title>
<style>
@keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent}
html,body{height:100%;font-family:system-ui,-apple-system,sans-serif;background:#0c2f2e;color:#fff}
body{display:flex;align-items:center;justify-content:center;padding:20px}
.box{background:#0f3a39;border:1px solid rgba(34,197,94,.3);border-radius:16px;padding:32px 24px;max-width:360px;width:100%;text-align:center;box-shadow:0 4px 20px rgba(0,0,0,.4)}
.ico{font-size:48px;margin-bottom:16px}
h1{font-size:18px;font-weight:700;margin-bottom:8px;color:#22c55e}
p{font-size:13px;color:#cbd5e1;line-height:1.6;margin-bottom:20px}
.steps{text-align:left;margin:16px 0;padding:14px 18px;background:rgba(0,0,0,.25);border-radius:10px;font-size:12px;line-height:1.9;color:#94a3b8}
.steps .done{color:#22c55e;font-weight:600}
.steps .doing{color:#fbbf24;font-weight:600}
.bar{background:rgba(0,0,0,.35);height:8px;border-radius:4px;overflow:hidden;margin:18px 0 12px}
.bar-fill{background:linear-gradient(90deg,#22c55e,#4ade80);height:100%;width:0%;border-radius:4px;transition:width .4s ease}
.pct{font-size:14px;font-weight:700;color:#22c55e;margin-bottom:18px}
.btn{display:inline-block;width:100%;padding:12px 18px;border:none;border-radius:10px;background:#22c55e;color:#fff;font-weight:700;font-size:14px;cursor:pointer;font-family:inherit}
.btn:active{opacity:.85}
.btn:disabled{opacity:.5;cursor:not-allowed}
.btn.secondary{background:transparent;border:1px solid rgba(255,255,255,.25);color:#fff;margin-top:8px}
.done-ico{color:#22c55e;font-size:56px;margin-bottom:12px}
.hide{display:none}
</style>
</head>
<body>
<div class="box" id="boxStart">
  <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" width="52" height="52"><path d="M3 6h18M8 6V4a2 2 0 012-2h4a2 2 0 012 2v2M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6" stroke-width="1.5"/></svg></div>
  <h1>Clear Cache</h1>
  <p>Menghapus cache aplikasi & service worker. Login & data akun Anda tidak akan terhapus.</p>
  <button class="btn" id="startBtn" onclick="startClear()">Mulai Clear Cache</button>
  <a href="index.php" class="btn secondary" style="text-decoration:none">Batal</a>
</div>

<div class="box hide" id="boxProgress">
  <div class="ico"><svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" width="52" height="52" style="animation:spin 1.5s linear infinite"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg></div>
  <h1>Membersihkan...</h1>
  <div class="bar"><div class="bar-fill" id="barFill"></div></div>
  <div class="pct" id="pctText">0%</div>
  <div class="steps" id="stepsList">
    <div id="s1"><span class="stp-m">•</span> Unregister Service Worker</div>
    <div id="s2"><span class="stp-m">•</span> Hapus Cache PWA</div>
    <div id="s3"><span class="stp-m">•</span> Hapus localStorage</div>
    <div id="s4"><span class="stp-m">•</span> Hapus sessionStorage</div>
    <div id="s5"><span class="stp-m">•</span> Hapus Cookie Cache</div>
  </div>
</div>

<div class="box hide" id="boxDone">
  <div class="done-ico"><svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="3" width="52" height="52"><circle cx="12" cy="12" r="10" fill="rgba(22,163,74,.1)" stroke="#16a34a" stroke-width="2"/><polyline points="7 12 11 16 17 9"/></svg></div>
  <h1>Cache Berhasil Dihapus</h1>
  <p>Aplikasi akan di-refresh dalam <span id="cntDown">3</span> detik...</p>
  <button class="btn" onclick="goHome()">Refresh Sekarang</button>
</div>

<script>
// Simpan cookie penting yang ga boleh dihapus
var KEEP_COOKIES = ['lx_token'];

var DONE_MARK = '<svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="3" width="12" height="12" style="vertical-align:-2px"><polyline points="20 6 9 17 4 12"/></svg>';
var DOING_MARK = '<svg viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2.5" width="12" height="12" style="vertical-align:-2px;animation:spin 1.2s linear infinite"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';

function setStepDone(id){
  var el=document.getElementById(id);
  if(el){
    el.classList.add('done');
    var m=el.querySelector('.stp-m');if(m)m.innerHTML=DONE_MARK;
  }
}
function setStepDoing(id){
  var el=document.getElementById(id);
  if(el){
    el.classList.add('doing');
    var m=el.querySelector('.stp-m');if(m)m.innerHTML=DOING_MARK;
  }
}
function setProgress(p){
  document.getElementById('barFill').style.width=p+'%';
  document.getElementById('pctText').textContent=p+'%';
}

async function startClear(){
  document.getElementById('boxStart').classList.add('hide');
  document.getElementById('boxProgress').classList.remove('hide');

  // 1. Unregister service workers
  setStepDoing('s1');
  setProgress(10);
  try{
    if('serviceWorker' in navigator){
      var regs=await navigator.serviceWorker.getRegistrations();
      for(var i=0;i<regs.length;i++){
        try{await regs[i].unregister();}catch(e){}
      }
    }
  }catch(e){}
  setStepDone('s1');
  setProgress(25);
  await sleep(300);

  // 2. Delete all cache API caches
  setStepDoing('s2');
  try{
    if('caches' in window){
      var keys=await caches.keys();
      for(var j=0;j<keys.length;j++){
        try{await caches.delete(keys[j]);}catch(e){}
      }
    }
  }catch(e){}
  setStepDone('s2');
  setProgress(50);
  await sleep(300);

  // 3. Clear localStorage
  setStepDoing('s3');
  try{
    // Preserve auth-related keys kalau ada
    var keepLS={};
    var keepKeys=['app_user','app_favs']; // data user yg berguna
    for(var k=0;k<keepKeys.length;k++){
      try{var v=localStorage.getItem(keepKeys[k]);if(v)keepLS[keepKeys[k]]=v;}catch(e){}
    }
    localStorage.clear();
    // Restore
    for(var kk in keepLS){try{localStorage.setItem(kk,keepLS[kk]);}catch(e){}}
  }catch(e){}
  setStepDone('s3');
  setProgress(70);
  await sleep(300);

  // 4. Clear sessionStorage
  setStepDoing('s4');
  try{sessionStorage.clear();}catch(e){}
  setStepDone('s4');
  setProgress(85);
  await sleep(300);

  // 5. Clear cookies (except login token)
  setStepDoing('s5');
  try{
    var cookies=document.cookie.split(';');
    for(var c=0;c<cookies.length;c++){
      var name=cookies[c].split('=')[0].trim();
      if(!name)continue;
      if(KEEP_COOKIES.indexOf(name)>-1)continue;
      // Hapus cookie
      document.cookie=name+'=;path=/;max-age=0;SameSite=Lax';
      document.cookie=name+'=;path=/;max-age=0;domain='+location.hostname+';SameSite=Lax';
    }
  }catch(e){}
  setStepDone('s5');
  setProgress(100);
  await sleep(500);

  // Done screen
  document.getElementById('boxProgress').classList.add('hide');
  document.getElementById('boxDone').classList.remove('hide');

  // Countdown 3 detik lalu redirect
  var n=3;
  var cd=document.getElementById('cntDown');
  var timer=setInterval(function(){
    n--;
    if(cd)cd.textContent=n;
    if(n<=0){clearInterval(timer);goHome();}
  },1000);
}

function goHome(){
  // Bust cache with timestamp
  location.href='index.php?_cb='+Date.now();
}

function sleep(ms){return new Promise(function(r){setTimeout(r,ms);});}
</script>
</body>
</html>
