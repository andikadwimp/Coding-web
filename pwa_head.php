<?php
$_pwa_sn = (isset($sets) && isset($sets['site_name'])) ? $sets['site_name'] : (isset($sn) ? $sn : 'Situs');
$_pwa_icon = (isset($sets) && !empty($sets['pwa_icon'])) ? $sets['pwa_icon'] : ((isset($sets) && !empty($sets['logo_url'])) ? $sets['logo_url'] : '/icon-192.png');
?>
<link rel="manifest" href="/manifest.php">
<meta name="theme-color" content="#0f172a">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="<?=htmlspecialchars($_pwa_sn)?>">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="mobile-web-app-capable" content="yes">
<link rel="apple-touch-icon" href="<?=htmlspecialchars($_pwa_icon)?>">
<script>
if('serviceWorker' in navigator){
  window.addEventListener('load',function(){
    navigator.serviceWorker.register('/sw.js').then(function(reg){
      // Cek update tiap 10 menit
      setInterval(function(){reg.update().catch(function(){})},10*60*1000);
      // Kalau ada SW baru, auto skip & reload
      reg.addEventListener('updatefound',function(){
        var nw=reg.installing;
        if(nw){
          nw.addEventListener('statechange',function(){
            if(nw.state==='installed'&&navigator.serviceWorker.controller){
              // SW baru udah siap, reload biar pake SW baru
              window.location.reload();
            }
          });
        }
      });
    }).catch(function(){});
    // Kalau SW berubah (dari update check), reload sekali
    var refreshing=false;
    navigator.serviceWorker.addEventListener('controllerchange',function(){
      if(refreshing)return;
      refreshing=true;
      window.location.reload();
    });
  });
}
// Capture PWA install prompt globally on every page
window.addEventListener('beforeinstallprompt',function(e){
  e.preventDefault();
  window.__pwaPrompt=e;
  sessionStorage.setItem('pwa_installable','1');
});
window.addEventListener('appinstalled',function(){
  window.__pwaPrompt=null;
  sessionStorage.removeItem('pwa_installable');
});
// Listen for SW message to set app cookie
  if(navigator.serviceWorker){
    navigator.serviceWorker.addEventListener('message',function(e){
      if(e.data&&e.data.type==='SET_APP_COOKIE'){
        document.cookie='pwa_app=1;path=/;max-age=31536000;SameSite=Lax';
      }
    });
  }
  // Set cookie pwa_app kalau di standalone mode. JANGAN hapus kalau bukan —
  // biarin saja, karena PWA Chrome bisa toggle display-mode saat navigasi link
  if(window.matchMedia('(display-mode: standalone)').matches){
    document.cookie='pwa_app=1;path=/;max-age=31536000;SameSite=Lax';
  }

// ─── Push notification subscription ───
function _b64ToUint8(b){var p='='.repeat((4-b.length%4)%4);var s=(b+p).replace(/-/g,'+').replace(/_/g,'/');var r=atob(s);var a=new Uint8Array(r.length);for(var i=0;i<r.length;i++)a[i]=r.charCodeAt(i);return a;}

// Status notifikasi — bisa dipanggil dari UI mana aja
window.__notifStatus=function(){
  if(!('serviceWorker' in navigator)||!('PushManager' in window))return 'unsupported';
  if(!('Notification' in window))return 'unsupported';
  return Notification.permission; // 'default' | 'granted' | 'denied'
};

// Cek apakah udah subscribe (granted + ada subscription)
window.__notifSubscribed=async function(){
  try{
    if(window.__notifStatus()!=='granted')return false;
    var reg=await navigator.serviceWorker.ready;
    var sub=await reg.pushManager.getSubscription();
    return !!sub;
  }catch(e){return false;}
};

// Manual enable — dipanggil oleh tombol di UI (profil, dll)
// Mengembalikan promise: 'granted' | 'denied' | 'unsupported' | 'error' | 'not_logged_in'
window.__enableNotif=async function(){
  try{
    if(!('serviceWorker' in navigator)||!('PushManager' in window))return 'unsupported';
    if(!('Notification' in window))return 'unsupported';
    var isLoggedIn=document.cookie.indexOf('lx_token=')>-1;
    if(!isLoggedIn)return 'not_logged_in';

    // Kalau permission denied → instruksi ke user buat reset manually via browser
    if(Notification.permission==='denied')return 'denied';

    // Request permission (jalan kalau default, langsung return granted kalau sudah granted)
    if(Notification.permission!=='granted'){
      var p=await Notification.requestPermission();
      if(p!=='granted')return p;
    }

    var reg=await navigator.serviceWorker.ready;
    var existing=await reg.pushManager.getSubscription();
    var sub;
    if(existing){
      sub=existing;
    }else{
      // Fetch VAPID public key
      var kr=await fetch('/api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'vapid_public_key'})});
      var kd=await kr.json();
      if(!kd.ok||!kd.public_key)return 'error';
      sub=await reg.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:_b64ToUint8(kd.public_key)});
    }
    var sj=sub.toJSON();
    await fetch('/api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'push_subscribe',endpoint:sj.endpoint,p256dh:sj.keys.p256dh,auth:sj.keys.auth})});
    return 'granted';
  }catch(e){console.warn('Enable notif failed:',e);return 'error';}
};

// Auto-sync subscription ke server setiap page load (kalau udah granted)
// Tanpa prompt — cuma refresh mapping di DB
async function __subscribePush(){
  try{
    if(!('serviceWorker' in navigator)||!('PushManager' in window))return;
    if(Notification.permission!=='granted')return; // jangan prompt di background
    var isLoggedIn=document.cookie.indexOf('lx_token=')>-1;
    if(!isLoggedIn)return;
    var reg=await navigator.serviceWorker.ready;
    var existing=await reg.pushManager.getSubscription();
    if(existing){
      // Sync subscription ke server (in case DB belum tau)
      var j=existing.toJSON();
      fetch('/api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'push_subscribe',endpoint:j.endpoint,p256dh:j.keys.p256dh,auth:j.keys.auth})}).catch(function(){});
      return;
    }
    // Permission granted tapi belum subscribe (kasus: user reset app tapi permission tetep granted)
    var kr=await fetch('/api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'vapid_public_key'})});
    var kd=await kr.json();
    if(!kd.ok||!kd.public_key)return;
    var sub=await reg.pushManager.subscribe({userVisibleOnly:true,applicationServerKey:_b64ToUint8(kd.public_key)});
    var sj=sub.toJSON();
    await fetch('/api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'push_subscribe',endpoint:sj.endpoint,p256dh:sj.keys.p256dh,auth:sj.keys.auth})});
  }catch(e){console.warn('Push subscribe sync failed:',e);}
}
setTimeout(__subscribePush,3000);

// ═══════════════════════════════════════════════
// WAJIB NOTIF MODAL — muncul di SEMUA halaman kalau user udah login tapi belum aktifin notif
// Tidak bisa di-dismiss sampai user klik "Aktifkan"
// Skip kalau: belum login, halaman login/register, halaman team admin, udah granted, browser tidak support
// ═══════════════════════════════════════════════
(function(){
  // Skip kalau halaman login/register/team admin
  var path=location.pathname;
  var skipPaths=['/index.php','/login.php','/register.php','/auth.php','/cs_chat.php','/cs.php','/download.php','/invite.php','/maintenance.php','/','/team/'];
  for(var i=0;i<skipPaths.length;i++){
    if(path===skipPaths[i]||path.indexOf(skipPaths[i])===0&&skipPaths[i].endsWith('/'))return;
  }

  function injectNotifModal(){
    // Skip kalau belum login
    if(document.cookie.indexOf('lx_token=')===-1)return;
    // Skip kalau browser tidak support
    if(!('serviceWorker' in navigator)||!('PushManager' in window)||!('Notification' in window))return;

    var perm=Notification.permission;

    // Kalau sudah granted → langsung subscribe di background (no modal)
    if(perm==='granted'){
      if(typeof __subscribePush==='function')__subscribePush();
      return;
    }

    // Kalau denied → skip, JANGAN ganggu user dengan modal
    if(perm==='denied')return;

    // Kalau user udah pernah skip di session ini, jangan spam
    if(sessionStorage.getItem('lx_notif_skipped')==='1')return;

    // Tampilkan MODAL CUSTOM — user harus klik tombol buat trigger native prompt
    // Browser modern block auto-request tanpa gesture
    var ov=document.createElement('div');
    ov.id='lxNotifAsk';
    ov.innerHTML=
      '<div class="lxna-bg"></div>'+
      '<div class="lxna-box">'+
        '<div class="lxna-ic">'+
          '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2">'+
            '<path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/>'+
            '<path d="M13.73 21a2 2 0 01-3.46 0"/>'+
          '</svg>'+
        '</div>'+
        '<div class="lxna-ttl">Aktifkan Notifikasi</div>'+
        '<div class="lxna-txt">Dapatkan info <b>deposit berhasil</b>, <b>withdraw diproses</b>, <b>bonus baru</b>, dan promo terbaru langsung di HP kamu.</div>'+
        '<div class="lxna-btns">'+
          '<button class="lxna-yes" id="lxnaYes">Aktifkan Sekarang</button>'+
          '<button class="lxna-no" id="lxnaNo">Nanti</button>'+
        '</div>'+
      '</div>';
    var st=document.createElement('style');
    st.textContent=
      '#lxNotifAsk{position:fixed;inset:0;z-index:999999;display:flex;align-items:flex-end;justify-content:center;animation:lxnaIn .3s ease}'+
      '@keyframes lxnaIn{from{opacity:0}to{opacity:1}}'+
      '#lxNotifAsk .lxna-bg{position:absolute;inset:0;background:rgba(0,0,0,.65);backdrop-filter:blur(4px)}'+
      '#lxNotifAsk .lxna-box{position:relative;width:100%;max-width:390px;background:linear-gradient(180deg,#1e293b,#0f172a);border-top:2px solid var(--pri,#38bdf8);border-radius:18px 18px 0 0;padding:24px 20px 20px;color:#fff;text-align:center;animation:lxnaSlide .35s cubic-bezier(.2,.8,.3,1.1)}'+
      '@keyframes lxnaSlide{from{transform:translateY(100%)}to{transform:translateY(0)}}'+
      '#lxNotifAsk .lxna-ic{width:64px;height:64px;border-radius:50%;background:radial-gradient(circle at 30% 30%,#fde68a,#fbbf24 40%,#d97706 90%);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;box-shadow:0 8px 20px rgba(251,191,36,.4);animation:lxnaBell 2s ease-in-out infinite}'+
      '#lxNotifAsk .lxna-ic svg{width:32px;height:32px;color:#78350f}'+
      '@keyframes lxnaBell{0%,100%{transform:rotate(0)}10%,30%{transform:rotate(-12deg)}20%,40%{transform:rotate(12deg)}50%{transform:rotate(0)}}'+
      '#lxNotifAsk .lxna-ttl{font-size:1.15rem;font-weight:800;margin-bottom:8px;color:#fff}'+
      '#lxNotifAsk .lxna-txt{font-size:.84rem;color:#cbd5e1;line-height:1.55;margin-bottom:20px;padding:0 6px}'+
      '#lxNotifAsk .lxna-txt b{color:#fbbf24;font-weight:700}'+
      '#lxNotifAsk .lxna-btns{display:flex;flex-direction:column;gap:8px}'+
      '#lxNotifAsk .lxna-yes{padding:14px;background:linear-gradient(135deg,#22c55e,#16a34a);border:none;border-radius:12px;color:#fff;font-size:.95rem;font-weight:800;cursor:pointer;font-family:inherit;box-shadow:0 6px 16px rgba(34,197,94,.4)}'+
      '#lxNotifAsk .lxna-yes:active{transform:scale(.97)}'+
      '#lxNotifAsk .lxna-no{padding:12px;background:transparent;border:none;color:#94a3b8;font-size:.82rem;font-weight:600;cursor:pointer;font-family:inherit}';
    document.head.appendChild(st);
    document.body.appendChild(ov);

    document.getElementById('lxnaYes').addEventListener('click',async function(){
      var btn=this;btn.disabled=true;btn.textContent='Memproses...';
      try{
        var result=await Notification.requestPermission();
        if(result==='granted'){
          btn.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="14" height="14" style="vertical-align:-2px;display:inline-block"><polyline points="20 6 9 17 4 12"/></svg> Berhasil!';
          if(typeof __enableNotif==='function')await __enableNotif();
          setTimeout(function(){ov.remove();},500);
          // Kirim test notif dari server
          setTimeout(function(){
            fetch('/api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'push_test'})}).catch(function(){});
          },1500);
        }else{
          btn.textContent='Gagal — coba lagi di Pengaturan HP';
          setTimeout(function(){ov.remove();},2000);
        }
      }catch(e){btn.textContent='Error';setTimeout(function(){ov.remove();},1500);}
    });
    document.getElementById('lxnaNo').addEventListener('click',function(){
      sessionStorage.setItem('lx_notif_skipped','1');
      ov.remove();
    });
  }

  // Delay 1.5 detik biar halaman selesai load dulu
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',function(){setTimeout(injectNotifModal,1500)});
  else setTimeout(injectNotifModal,1500);
})();

// ═══════════════════════════════════════════════════════════════
// SMOOTH PAGE LOADER — muncul saat pindah menu / link
// ═══════════════════════════════════════════════════════════════
(function(){
  var style=document.createElement('style');
  style.textContent=''
    /* Frosted glass overlay with backdrop blur — no spinner */
    +'@keyframes lxFadeIn{from{opacity:0;backdrop-filter:blur(0)}to{opacity:1;backdrop-filter:blur(20px)}}'
    +'@keyframes lxFadeOutKf{from{opacity:1;backdrop-filter:blur(20px)}to{opacity:0;backdrop-filter:blur(0)}}'
    +'@keyframes lxPulse{0%,100%{transform:scale(1);opacity:.5}50%{transform:scale(1.04);opacity:1}}'
    +'@keyframes lxShimmer{0%{transform:translateX(-110%)}100%{transform:translateX(310%)}}'
    +'#lxPageLoader{position:fixed;inset:0;background:rgba(10,15,28,.45);backdrop-filter:blur(20px) saturate(140%);-webkit-backdrop-filter:blur(20px) saturate(140%);z-index:99998;display:none;align-items:center;justify-content:center;flex-direction:column;gap:18px;animation:lxFadeIn .35s cubic-bezier(.16,1,.3,1) forwards}'
    +'#lxPageLoader.show{display:flex}'
    +'#lxPageLoader.hiding{animation:lxFadeOutKf .25s cubic-bezier(.4,0,1,1) forwards;pointer-events:none}'
    /* Logo block — pulse breathe */
    +'#lxPageLoader .lx-logo{font-family:"Chakra Petch","Poppins",sans-serif;font-size:1.15rem;font-weight:800;color:#fff;letter-spacing:4px;text-transform:uppercase;text-shadow:0 0 24px rgba(var(--pri-rgb,56,189,248),.55),0 2px 12px rgba(0,0,0,.4);animation:lxPulse 1.6s ease-in-out infinite}'
    /* Slim shimmer bar instead of ring */
    +'#lxPageLoader .lx-bar{width:140px;height:2px;background:rgba(255,255,255,.12);border-radius:2px;overflow:hidden;position:relative}'
    +'#lxPageLoader .lx-bar::before{content:"";position:absolute;left:0;top:0;width:35%;height:100%;background:linear-gradient(90deg,transparent,var(--pri,#38bdf8) 50%,transparent);animation:lxShimmer 1.4s cubic-bezier(.4,0,.2,1) infinite;border-radius:2px}'
    +'#lxPageLoader .lx-tag{font-family:"Poppins",sans-serif;font-size:.62rem;font-weight:600;color:rgba(255,255,255,.55);letter-spacing:1.5px;text-transform:uppercase}';
  document.head.appendChild(style);

  var loader=document.createElement('div');
  loader.id='lxPageLoader';
  var siteName=(window.SI&&window.SI.site_name)||'K7777';
  loader.innerHTML='<div class="lx-logo">'+siteName+'</div><div class="lx-bar"></div><div class="lx-tag">Memuat</div>';
  if(document.body)document.body.appendChild(loader);
  else document.addEventListener('DOMContentLoaded',function(){document.body.appendChild(loader)});

  window.__showLoader=function(){var l=document.getElementById('lxPageLoader');if(l)l.classList.add('show')};
  window.__hideLoader=function(){var l=document.getElementById('lxPageLoader');if(!l)return;l.classList.add('hiding');setTimeout(function(){l.classList.remove('show');l.classList.remove('hiding')},250)};

  // Page loader on internal nav DISABLED — skeleton placeholders inside each page handle visual loading.
  // (Loader still available via window.__showLoader() for explicit cases.)

  // Show loader saat form submit juga
  document.addEventListener('submit',function(e){
    var f=e.target;
    if(f&&f.tagName==='FORM'&&!f.hasAttribute('data-no-loader')){
      window.__showLoader();
    }
  },true);

  // Hide loader saat halaman baru selesai load (pastiin ga stuck)
  window.addEventListener('pageshow',function(){window.__hideLoader()});
  // Hide saat back/forward navigation
  window.addEventListener('popstate',function(){window.__hideLoader()});
})();

// ═══════════════════════════════════════════════════════════════
// BACK BUTTON HANDLER — supaya tombol back HP ga keluar PWA
// ═══════════════════════════════════════════════════════════════
(function(){
  // Pages dimana back button harusnya keluar aplikasi (home/landing)
  var homePages=['/','/index.php','/dashboard.php','/home.php'];
  var curPath=location.pathname;
  var isHome=homePages.indexOf(curPath)!==-1||curPath==='/'||curPath.endsWith('/dashboard.php')||curPath.endsWith('/index.php');

  // Tambah history entry dummy supaya back button balik ke halaman sebelumnya (bukan keluar app)
  // Cuma berlaku di halaman home/dashboard — halaman lain normal back
  if(isHome&&!sessionStorage.getItem('lx_history_set')){
    sessionStorage.setItem('lx_history_set','1');
    history.pushState({lxHome:true},'',location.href);
  }

  // Handle popstate (back button)
  window.addEventListener('popstate',function(e){
    // Kalau ada modal/overlay terbuka, tutup dulu sebelum back
    var openModals=document.querySelectorAll('.modal-open,.mo.show,[data-modal-open="1"],#gameOverlay.active,.srch-ov.show');
    if(openModals.length>0){
      openModals.forEach(function(m){
        if(m.classList)m.classList.remove('show','active','modal-open');
        if(m.id==='gameOverlay'){
          // Khusus game overlay: trigger close agar saldo di-pull dari Nexus
          if(typeof closeGame==='function')closeGame();
        }
      });
      // Push state lagi supaya back button bisa dipake lagi
      if(isHome)history.pushState({lxHome:true},'',location.href);
      return;
    }

    // Kalau di halaman home/dashboard, user back button = mau keluar app
    // Kasih konfirmasi sekali dulu (seperti Tokopedia, Shopee, dll)
    if(isHome){
      if(window.__backConfirmShown){
        // User tekan back lagi dalam 2 detik → beneran keluar
        history.back();
        return;
      }
      window.__backConfirmShown=true;
      showBackToast();
      // Push state lagi supaya user masih di app
      history.pushState({lxHome:true},'',location.href);
      setTimeout(function(){window.__backConfirmShown=false},2000);
    }
  });

  function showBackToast(){
    var t=document.getElementById('lxBackToast');
    if(!t){
      t=document.createElement('div');
      t.id='lxBackToast';
      t.style.cssText='position:fixed;bottom:100px;left:50%;transform:translateX(-50%);background:rgba(0,0,0,.85);color:#fff;padding:12px 22px;border-radius:30px;font-size:.82rem;font-weight:600;z-index:99997;box-shadow:0 4px 20px rgba(0,0,0,.3);opacity:0;transition:opacity .2s;pointer-events:none;white-space:nowrap';
      t.textContent='Tekan sekali lagi untuk keluar';
      document.body.appendChild(t);
    }
    t.style.opacity='1';
    setTimeout(function(){t.style.opacity='0'},1800);
  }
})();

// Auto-reconcile deposit pending background — throttled 15 detik per session
// Cek kalau user logged in (ada localStorage app_user) → trigger reconcile_mine max tiap 15 detik
// Ini SAFETY NET: kalau callback SQX gagal/telat, deposit yg udah dibayar tetep ke-credit
// pas user buka halaman apapun (dashboard, profil, game, undang, dll)
(function(){
  try{
    if(!localStorage.getItem('app_user'))return; // belum login, skip
    var lastReconcile=parseInt(sessionStorage.getItem('lastReconcile')||'0');
    var now=Date.now();
    if(now-lastReconcile<15000)return; // throttle 15 detik (was 60)
    sessionStorage.setItem('lastReconcile',String(now));
    // Background fetch — silent, ga ngeganggu UI
    setTimeout(function(){
      fetch('api/deposit.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'reconcile_mine'})})
      .then(function(r){return r.json()}).then(function(d){
        // Kalau ada deposit baru paid, notify + refresh balance
        if(d&&d.ok&&d.summary&&d.summary.paid>0){
          // Toast notif
          try{
            var t=document.createElement('div');
            t.style.cssText='position:fixed;bottom:80px;left:50%;transform:translateX(-50%);background:linear-gradient(135deg,#10b981,#059669);color:#fff;padding:12px 18px;border-radius:10px;font-size:.78rem;font-weight:700;box-shadow:0 4px 20px rgba(16,185,129,.4);z-index:9999;animation:slideUp .3s';
            t.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="14" height="14" style="vertical-align:-2px;display:inline-block"><polyline points="20 6 9 17 4 12"/></svg> Deposit '+d.summary.paid+'x berhasil masuk!';
            document.body.appendChild(t);
            setTimeout(function(){t.remove()},3500);
          }catch(e){}
          // Trigger refresh balance kalo function ada, atau reload
          setTimeout(function(){
            if(typeof refreshBal==='function')refreshBal();
            else if(typeof location!=='undefined')location.reload();
          },1500);
        }
      }).catch(function(){});
    },1500); // delay 1.5 detik biar ga ganggu page load
  }catch(e){}
})();

/* ═══════════════════════════════════════════════════════════════════════
   GLOBAL AUTH HELPERS — guest-friendly site
   IS_LOGGED_IN flag derived from server (cookie auth_token)
   requireLogin(label?) — guard for login-required actions.
   Returns true if logged in, else opens login flow & returns false.
   ═══════════════════════════════════════════════════════════════════════ */
window.IS_LOGGED_IN = <?php echo getUid() ? 'true' : 'false'; ?>;


/* ═══════════════════════════════════════════════════════════════════════
   handleAuthError(d) — universal handler when API returns NOT_LOGGED_IN.
   Redirects to login + shows toast. Use after every fetch where login required.
   Returns true if was auth error (handled), false otherwise.
   ═══════════════════════════════════════════════════════════════════════ */
window.handleAuthError = function(d){
  if(d && (d.error==='NOT_LOGGED_IN' || d.error==='AUTH_REQUIRED')){
    if(typeof showToast==='function') showToast('Sesi habis, silakan login ulang');
    setTimeout(function(){
      location.href='index.php?login=1&from='+encodeURIComponent(location.pathname);
    },1000);
    return true;
  }
  return false;
};

window.requireLogin = function(actionLabel){
  if(window.IS_LOGGED_IN) return true;
  // Coba panggil openM('login') kalau di halaman index (modal udah ada di DOM)
  if(typeof openM === 'function'){ openM('login'); return false; }
  // Fallback: redirect ke index.php?login=1 — index.php auto-buka modal saat detect param ini
  var msg = actionLabel ? ('Login dulu untuk '+actionLabel+'.') : 'Login dulu untuk lanjut.';
  if(typeof showToast === 'function') showToast(msg);
  else if(typeof toast === 'function') toast(msg);
  setTimeout(function(){
    location.href = 'index.php?login=1&from='+encodeURIComponent(location.pathname);
  }, 800);
  return false;
};

// Auto-buka login modal di index.php kalau URL param login=1 ada
(function(){
  try{
    if(location.pathname.indexOf('index.php')!==-1 || location.pathname==='/' || location.pathname.endsWith('/')){
      var p = new URLSearchParams(location.search);
      if(p.get('login')==='1' && !window.IS_LOGGED_IN){
        // Tunggu modal ter-render dulu
        var tries=0;
        var iv=setInterval(function(){
          tries++;
          if(typeof openM==='function'){
            clearInterval(iv);
            openM('login');
          }else if(tries>30){clearInterval(iv);}
        }, 100);
      }
    }
  }catch(e){}
})();
</script>
<style>
/* ═══ PAGE TRANSITION — opacity-only fade in ═══ */
/* IMPORTANT: NO transform, NO filter on body — both create a containing block
   that breaks position:fixed for bnav and floating buttons. Pure opacity is
   safe. */
@keyframes pageFadeIn{
  from{opacity:0}
  to{opacity:1}
}
body{animation:pageFadeIn .3s ease-out both}
/* Disable for game iframe (in-app overlay) */
.game-overlay,#gameOverlay{animation:none !important}

/* ═══ LIVE WINNER POPUP ═══ */
.lw-pop{position:fixed;top:14px;right:14px;z-index:9000;display:none;align-items:center;gap:10px;padding:8px 12px;background:rgba(15,23,42,.92);backdrop-filter:blur(14px) saturate(160%);-webkit-backdrop-filter:blur(14px) saturate(160%);border:1px solid rgba(var(--pri-rgb,56,189,248),.35);border-radius:14px;max-width:280px;box-shadow:0 8px 22px rgba(0,0,0,.45),0 0 18px rgba(var(--pri-rgb,56,189,248),.18);overflow:hidden;pointer-events:none}
.lw-pop.show{display:flex;animation:lwIn .45s cubic-bezier(.34,1.56,.64,1) both}
.lw-pop.leaving{animation:lwOut .3s cubic-bezier(.4,0,.6,0) both}
@keyframes lwIn{from{transform:translateX(120%) scale(.85);opacity:0}to{transform:translateX(0) scale(1);opacity:1}}
@keyframes lwOut{from{transform:translateX(0) scale(1);opacity:1}to{transform:translateX(120%) scale(.9);opacity:0}}
.lw-pop::before{content:'';position:absolute;top:0;left:-100%;width:100%;height:1px;background:linear-gradient(90deg,transparent,rgba(var(--pri-rgb,56,189,248),.85),transparent);animation:lwSpark 2.5s linear infinite}
@keyframes lwSpark{from{left:-100%}to{left:100%}}
.lw-img{width:42px;height:42px;border-radius:9px;flex-shrink:0;overflow:hidden;background:rgba(255,255,255,.04);position:relative}
.lw-img img{width:100%;height:100%;object-fit:cover}
.lw-img::after{content:'';position:absolute;inset:0;background:linear-gradient(135deg,transparent 60%,rgba(var(--pri-rgb,56,189,248),.4));pointer-events:none}
.lw-info{flex:1;min-width:0;line-height:1.25}
.lw-user{font-size:.7rem;font-weight:700;color:#fff;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;letter-spacing:-.005em}
.lw-game{font-size:.6rem;color:rgba(203,213,225,.7);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:500;margin-top:1px}
.lw-amt{font-family:'Chakra Petch','Poppins',sans-serif;font-size:.82rem;font-weight:800;color:var(--pri,#38bdf8);font-variant-numeric:tabular-nums;letter-spacing:-.01em;flex-shrink:0;display:flex;flex-direction:column;align-items:flex-end;line-height:1;text-shadow:0 0 12px rgba(var(--pri-rgb,56,189,248),.4)}
.lw-amt small{font-size:.52rem;font-weight:600;color:rgba(74,222,128,.95);text-transform:uppercase;letter-spacing:.6px;margin-top:3px;text-shadow:none}
.lw-amt-prefix{color:#4ade80}
@media(max-width:480px){.lw-pop{top:8px;right:8px;max-width:240px;padding:7px 10px;gap:8px}.lw-img{width:36px;height:36px}.lw-user{font-size:.66rem}.lw-game{font-size:.56rem}.lw-amt{font-size:.76rem}}
@media(prefers-reduced-motion:reduce){.lw-pop,.lw-pop::before{animation-duration:.01ms!important}}
</style>
<script>
/* Live winner widget — append div + auto-rotate every ~5-7s */
(function(){
  var data=[];var idx=0;var timer;var fetched=0;
  function fmtRp(n){if(n>=1e9)return(n/1e9).toFixed(1).replace('.0','')+'B';if(n>=1e6)return(n/1e6).toFixed(1).replace('.0','')+'M';if(n>=1e3)return Math.floor(n/1e3)+'K';return n;}
  function ensureEl(){
    var el=document.getElementById('lwPop');
    if(!el){el=document.createElement('div');el.id='lwPop';el.className='lw-pop';document.body.appendChild(el);}
    return el;
  }
  function show(){
    if(!data.length){return;}
    var w=data[idx%data.length];idx++;
    var el=ensureEl();
    el.classList.remove('leaving');
    var imgHtml=w.banner?'<img src="'+w.banner.replace(/"/g,'&quot;')+'" loading="lazy" onerror="this.style.display=\'none\'">':'';
    el.innerHTML='<div class="lw-img">'+imgHtml+'</div>'+
      '<div class="lw-info"><div class="lw-user">'+w.username+'</div><div class="lw-game">'+w.game_name+'</div></div>'+
      '<div class="lw-amt"><span><span class="lw-amt-prefix">+</span>Rp '+fmtRp(w.amount)+'</span><small>menang</small></div>';
    el.classList.add('show');
    setTimeout(function(){
      el.classList.add('leaving');
      setTimeout(function(){el.classList.remove('show');el.classList.remove('leaving');},300);
    },4500);
  }
  function load(){
    if(fetched>10)return; // safety cap
    fetched++;
    fetch('/api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'live_winners'})})
      .then(function(r){return r.json();}).then(function(d){
        if(d&&d.ok&&d.winners&&d.winners.length){data=d.winners;idx=0;start();}
      }).catch(function(){});
  }
  function start(){
    if(timer)clearInterval(timer);
    setTimeout(show,1500); // first popup after 1.5s
    timer=setInterval(function(){
      show();
      // Refresh data every cycle of N popups
      if(idx>0 && idx%data.length===0)load();
    }, 6500); // one popup every 6.5s
  }
  function init(){
    // Skip in admin / login / iframe
    if(location.pathname.indexOf('/team/')!==-1)return;
    if(window.self!==window.top)return;
    load();
  }
  if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init);else init();
})();
</script>

