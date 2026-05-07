<?php
// ═══════════════════════════════════════════════════════════════
// PWA INSTALL — STATE MACHINE YANG BENAR
// State: standalone | ready-to-install | installable-manual | unsupported
// ═══════════════════════════════════════════════════════════════
?>
<style>
#instModal{position:fixed;inset:0;background:rgba(0,0,0,.82);z-index:99999;display:none;align-items:center;justify-content:center;padding:20px;font-family:system-ui,-apple-system,Arial,sans-serif}
#instModal.show{display:flex;animation:instFade .2s ease}
@keyframes instFade{from{opacity:0}to{opacity:1}}
#instModal .im-box{background:#fff;border-radius:16px;padding:28px 24px;max-width:360px;width:100%;text-align:center;box-shadow:0 20px 50px rgba(0,0,0,.5)}
#instModal .im-ico{width:60px;height:60px;margin:0 auto 14px;display:flex;align-items:center;justify-content:center}
#instModal .im-title{margin:0 0 8px;color:#111;font-size:18px;font-weight:800}
#instModal .im-sub{margin:0 0 18px;color:#555;font-size:13px;line-height:1.5}
#instModal .im-bar{background:#e8eaed;height:8px;border-radius:4px;overflow:hidden;margin-bottom:10px;display:none}
#instModal .im-bar-fill{background:#2563eb;height:100%;width:5%;border-radius:4px;transition:width .4s ease}
#instModal .im-pct{font-size:13px;color:#2563eb;font-weight:700;margin-bottom:14px;display:none}
#instModal .im-btn{display:block;width:100%;padding:12px 20px;background:#2563eb;color:#fff;border:none;border-radius:10px;font-weight:800;font-size:15px;cursor:pointer;font-family:inherit;margin-top:4px}
#instModal .im-btn:active{background:#1d4ed8}
#instModal .im-btn-sec{display:block;width:100%;padding:11px 20px;background:transparent;color:#666;border:1px solid #ddd;border-radius:10px;font-weight:600;font-size:13px;cursor:pointer;font-family:inherit;margin-top:8px}
#instModal.show-progress .im-bar,#instModal.show-progress .im-pct{display:block}
#instModal .im-steps{text-align:left;font-size:13px;color:#444;line-height:1.7;margin-top:4px;padding:12px 14px;background:#f9fafb;border-radius:10px;border:1px solid #e5e7eb}
#instModal .im-steps b{color:#2563eb}
#instModal .im-steps .st-row{display:flex;align-items:flex-start;gap:8px;margin-bottom:6px}
#instModal .im-steps .st-row:last-child{margin-bottom:0}
#instModal .im-steps .st-num{flex-shrink:0;width:22px;height:22px;border-radius:50%;background:#2563eb;color:#fff;font-weight:800;font-size:11px;display:flex;align-items:center;justify-content:center}
</style>

<div id="instModal">
  <div class="im-box">
    <div class="im-ico" id="imIco"></div>
    <div class="im-title" id="imTitle">Memasang Aplikasi</div>
    <div class="im-sub" id="imSub">Mohon tunggu sebentar...</div>
    <div class="im-bar"><div class="im-bar-fill" id="imBarFill"></div></div>
    <div class="im-pct" id="imPct">5%</div>
    <div id="imBody"></div>
    <button class="im-btn" id="imBtn" style="display:none">Lanjut</button>
    <button class="im-btn-sec" id="imBtnClose" style="display:none">Tutup</button>
  </div>
</div>

<script>
(function(){
  // ═══ SVG icons ═══
  var ICO_PHONE='<svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" width="56" height="56"><rect x="5" y="2" width="14" height="20" rx="2"/><line x1="11" y1="18" x2="13" y2="18"/></svg>';
  var ICO_DONE='<svg viewBox="0 0 24 24" fill="none" stroke="#16a34a" stroke-width="2.5" width="56" height="56"><circle cx="12" cy="12" r="10" fill="rgba(22,163,74,.12)"/><polyline points="7 12 11 16 17 9"/></svg>';
  var ICO_INSTALL='<svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" width="56" height="56"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>';
  var ICO_INFO='<svg viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" width="56" height="56"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>';
  var ICO_ERROR='<svg viewBox="0 0 24 24" fill="none" stroke="#dc2626" stroke-width="2" width="56" height="56"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>';

  function $(id){return document.getElementById(id);}
  var progressTimer=null;

  function show(){ $('instModal').classList.add('show'); }
  function hide(){
    $('instModal').classList.remove('show','show-progress');
    if(progressTimer){clearInterval(progressTimer);progressTimer=null;}
  }
  function reset(){
    $('imBody').innerHTML='';
    $('imBtn').style.display='none';
    $('imBtnClose').style.display='none';
    $('instModal').classList.remove('show-progress');
  }

  // ═══ STATE: STANDALONE (udah dibuka dari PWA) ═══
  function isStandalone(){
    return window.matchMedia('(display-mode: standalone)').matches ||
           window.navigator.standalone === true ||
           document.referrer.indexOf('android-app://') === 0;
  }

  // ═══ Real PWA check via getInstalledRelatedApps (Chrome Android) ═══
  async function checkReallyInstalled(){
    try{
      if(!('getInstalledRelatedApps' in navigator))return null; // API ga support
      var apps = await navigator.getInstalledRelatedApps();
      return apps && apps.length > 0;
    }catch(e){return null;}
  }

  // ═══ OS / Browser detection ═══
  function detectPlatform(){
    var ua = navigator.userAgent || '';
    var isIOS = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
    var isSafari = /Safari/.test(ua) && !/Chrome|CriOS|FxiOS|EdgiOS/.test(ua);
    var isChrome = /Chrome|CriOS/.test(ua) && !/Edge|EdgA|OPR/.test(ua);
    var isFirefox = /Firefox|FxiOS/.test(ua);
    var isAndroid = /Android/.test(ua);
    var isSamsung = /SamsungBrowser/.test(ua);
    return {ua:ua,isIOS:isIOS,isSafari:isSafari,isChrome:isChrome,isFirefox:isFirefox,isAndroid:isAndroid,isSamsung:isSamsung};
  }

  // ═══ STATE: ALREADY INSTALLED ═══
  function showAlreadyInstalled(){
    reset();
    $('imIco').innerHTML=ICO_DONE;
    $('imTitle').textContent='Aplikasi Sudah Terpasang';
    $('imSub').textContent='Aplikasi ditemukan di perangkat Anda. Silakan buka dari ikon di layar utama.';
    $('imBtn').textContent='Lanjut ke Aplikasi';
    $('imBtn').style.display='block';
    $('imBtn').onclick=goMain;
    $('imBtnClose').style.display='block';
    $('imBtnClose').textContent='Tutup';
    $('imBtnClose').onclick=hide;
    show();
  }

  // ═══ STATE: INSTALL IN PROGRESS ═══
  function showInstalling(){
    reset();
    $('imIco').innerHTML=ICO_PHONE;
    $('imTitle').textContent='Memasang Aplikasi';
    $('imSub').textContent='Mohon tunggu, sedang memasang...';
    $('instModal').classList.add('show-progress');
    $('imBarFill').style.width='5%';
    $('imPct').textContent='5%';
    var p=5;
    if(progressTimer)clearInterval(progressTimer);
    progressTimer=setInterval(function(){
      p+=Math.random()*5+2;
      if(p>=88){p=88;clearInterval(progressTimer);progressTimer=null;}
      $('imBarFill').style.width=p+'%';
      $('imPct').textContent=Math.floor(p)+'%';
    },300);
    show();
  }

  function fillComplete(){
    if(progressTimer){clearInterval(progressTimer);progressTimer=null;}
    $('imBarFill').style.width='100%';
    $('imPct').textContent='100%';
  }

  // ═══ STATE: USER CANCELED / PROMPT DISMISSED ═══
  function showCanceled(){
    reset();
    $('imIco').innerHTML=ICO_ERROR;
    $('imTitle').textContent='Pemasangan Dibatalkan';
    $('imSub').textContent='Anda membatalkan pemasangan. Coba lagi kapan saja.';
    $('imBtn').textContent='Coba Lagi';
    $('imBtn').style.display='block';
    $('imBtn').onclick=function(){hide();setTimeout(window.installApp,200);};
    $('imBtnClose').style.display='block';
    $('imBtnClose').textContent='Tutup';
    $('imBtnClose').onclick=hide;
    show();
  }

  // ═══ STATE: MANUAL INSTALL INSTRUCTIONS (per browser) ═══
  function showManualInstructions(){
    reset();
    var p = detectPlatform();
    var steps = '';
    $('imIco').innerHTML=ICO_INFO;

    if(p.isIOS && p.isSafari){
      $('imTitle').textContent='Pasang di iPhone/iPad';
      $('imSub').textContent='Ikuti 3 langkah berikut untuk memasang aplikasi di Safari:';
      steps =
        '<div class="st-row"><div class="st-num">1</div><div>Tap tombol <b>Bagikan</b> <svg viewBox="0 0 24 24" fill="none" stroke="#2563eb" stroke-width="2" width="14" height="14" style="vertical-align:-2px"><path d="M4 12v8a2 2 0 002 2h12a2 2 0 002-2v-8"/><polyline points="16 6 12 2 8 6"/><line x1="12" y1="2" x2="12" y2="15"/></svg> di bawah layar Safari</div></div>'+
        '<div class="st-row"><div class="st-num">2</div><div>Scroll ke bawah, pilih <b>Add to Home Screen</b> atau <b>Tambah ke Layar Utama</b></div></div>'+
        '<div class="st-row"><div class="st-num">3</div><div>Tap <b>Add</b> atau <b>Tambah</b> di kanan atas</div></div>';
    } else if(p.isIOS){
      $('imTitle').textContent='Buka di Safari';
      $('imSub').textContent='Untuk memasang aplikasi di iPhone/iPad, wajib buka dulu di browser <b>Safari</b>:';
      steps =
        '<div class="st-row"><div class="st-num">1</div><div>Salin link website ini</div></div>'+
        '<div class="st-row"><div class="st-num">2</div><div>Buka aplikasi <b>Safari</b></div></div>'+
        '<div class="st-row"><div class="st-num">3</div><div>Paste link, lalu pasang dari menu Bagikan → Add to Home Screen</div></div>';
    } else if(p.isAndroid && (p.isChrome || p.isSamsung)){
      $('imTitle').textContent='Pasang di Android';
      $('imSub').textContent='Ikuti langkah berikut untuk memasang aplikasi:';
      steps =
        '<div class="st-row"><div class="st-num">1</div><div>Tap menu <b>⋮</b> (titik tiga) di pojok kanan atas browser</div></div>'+
        '<div class="st-row"><div class="st-num">2</div><div>Pilih <b>Install app</b> atau <b>Tambahkan ke layar utama</b></div></div>'+
        '<div class="st-row"><div class="st-num">3</div><div>Tap <b>Install</b> untuk konfirmasi</div></div>';
    } else if(p.isFirefox){
      $('imTitle').textContent='Browser Tidak Didukung';
      $('imSub').textContent='Firefox belum mendukung pemasangan web app. Silakan buka di <b>Chrome</b> atau <b>Edge</b> untuk pengalaman terbaik.';
      steps = '<div class="st-row"><div class="st-num">!</div><div>Rekomendasi: buka website ini di Chrome atau Edge, lalu klik menu <b>Install</b>.</div></div>';
    } else {
      $('imTitle').textContent='Pasang Aplikasi';
      $('imSub').textContent='Gunakan menu browser untuk memasang aplikasi:';
      steps =
        '<div class="st-row"><div class="st-num">1</div><div>Tap menu browser (<b>⋮</b> atau <b>☰</b>)</div></div>'+
        '<div class="st-row"><div class="st-num">2</div><div>Pilih <b>Install</b> atau <b>Add to Home Screen</b></div></div>'+
        '<div class="st-row"><div class="st-num">3</div><div>Ikuti instruksi untuk menyelesaikan pemasangan</div></div>';
    }

    $('imBody').innerHTML='<div class="im-steps">'+steps+'</div>';
    $('imBtn').textContent='Lanjut ke Aplikasi';
    $('imBtn').style.display='block';
    $('imBtn').onclick=goMain;
    $('imBtnClose').style.display='block';
    $('imBtnClose').textContent='Tutup';
    $('imBtnClose').onclick=hide;
    show();
  }

  function goMain(){
    var url='index.php';
    try{
      var ref='';
      var m=document.cookie.match(/(?:^|;\s*)ref_code=([^;]+)/);
      if(m)ref=decodeURIComponent(m[1]);
      if(ref)url+='?ref='+encodeURIComponent(ref);
    }catch(e){}
    location.href=url;
  }
  window.__pwaGoMain=goMain;

  // ═══ MAIN ENTRY: installApp() ═══
  window.installApp = async function(){
    // 1. Udah di standalone mode → langsung masuk aplikasi
    if(isStandalone()){
      goMain();
      return;
    }

    // 2. Real check via getInstalledRelatedApps (Chrome Android 80+)
    var reallyInstalled = await checkReallyInstalled();
    if(reallyInstalled === true){
      showAlreadyInstalled();
      return;
    }

    // 3. Native prompt tersedia (beforeinstallprompt captured) → trigger
    if(window.__pwaPrompt){
      showInstalling();
      try{
        window.__pwaPrompt.prompt();
        var result = await window.__pwaPrompt.userChoice;
        if(result.outcome === 'accepted'){
          // User accept — tunggu appinstalled event atau fallback timeout
          setTimeout(function(){
            fillComplete();
            setTimeout(goMain, 700);
          }, 1200);
          // Prompt hanya bisa dipake 1x
          window.__pwaPrompt = null;
        } else {
          // User dismiss
          showCanceled();
          window.__pwaPrompt = null;
        }
      } catch(e){
        showCanceled();
      }
      return;
    }

    // 4. Prompt ga ada, ga standalone, ga reallyInstalled → manual install instructions
    // (Bisa karena: browser ga support, atau criteria PWA belum kepenuhan, atau iOS)
    showManualInstructions();
  };

  // appinstalled event — fire saat install selesai (entah trigger dari prompt atau manual)
  window.addEventListener('appinstalled', function(){
    fillComplete();
    setTimeout(goMain, 700);
  });
})();
</script>
