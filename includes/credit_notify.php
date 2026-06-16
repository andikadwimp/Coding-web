<?php
// ═══════════════════════════════════════════════════════════════════════
// GLOBAL CREDIT NOTIFICATION WIDGET
// Include di semua page yg user sudah login — poll tiap 5s
// Show toast pop-up kanan-atas pas ada credit baru (deposit/bonus/referral/rebate/dll)
// ═══════════════════════════════════════════════════════════════════════
?>
<style>
#creditToastContainer{
  position:fixed;top:16px;right:16px;z-index:99999;
  display:flex;flex-direction:column;gap:10px;
  pointer-events:none;
  max-width:calc(100vw - 32px);
}
.cr-toast{
  background:linear-gradient(180deg,#1e293b 0%,#0f172a 100%);
  border:1.5px solid #22c55e;
  border-radius:12px;
  padding:12px 14px 0;
  min-width:240px;max-width:320px;
  box-shadow:0 10px 30px rgba(0,0,0,.5),0 0 20px rgba(34,197,94,.2);
  color:#fff;
  pointer-events:auto;
  overflow:hidden;
  animation:crToastIn .4s cubic-bezier(.2,.8,.3,1.2) forwards;
  transform:translateX(380px);opacity:0;
  font-family:inherit;
}
@keyframes crToastIn{
  to{transform:translateX(0);opacity:1}
}
@keyframes crToastOut{
  to{transform:translateX(380px);opacity:0}
}
.cr-toast.closing{animation:crToastOut .3s ease-in forwards}
.cr-top{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:6px}
.cr-ic{
  width:34px;height:34px;border-radius:50%;
  background:radial-gradient(circle at 30% 30%,#fde68a,#fbbf24 40%,#d97706 80%,#92400e);
  display:flex;align-items:center;justify-content:center;
  font-weight:900;color:#78350f;font-size:18px;
  box-shadow:0 2px 6px rgba(0,0,0,.4),inset 0 1px 2px rgba(255,255,255,.4);
  flex-shrink:0;
}
.cr-ic svg{width:20px;height:20px}
.cr-amt{
  flex:1;text-align:right;
  font-weight:900;font-size:1.02rem;
  color:#4ade80;
  text-shadow:0 0 8px rgba(74,222,128,.4);
  letter-spacing:.3px;
}
.cr-close{
  background:none;border:none;
  color:#94a3b8;cursor:pointer;
  padding:2px 4px;font-size:1.1rem;line-height:1;
  font-family:inherit;
  flex-shrink:0;
}
.cr-close:hover{color:#fff}
.cr-body{font-size:.78rem;color:#e2e8f0;line-height:1.45;padding-bottom:10px}
.cr-body b{color:#fbbf24;font-weight:700}
.cr-bar{
  height:3px;width:100%;background:rgba(255,255,255,.08);
  margin:0 -14px;width:calc(100% + 28px);
  overflow:hidden;border-radius:0 0 12px 12px;
}
.cr-bar-fill{
  height:100%;background:linear-gradient(90deg,#22c55e,#4ade80);
  width:100%;
  animation:crBarCount 10s linear forwards;
}
@keyframes crBarCount{
  from{width:100%}to{width:0%}
}
</style>

<div id="creditToastContainer"></div>

<script>
(function(){
  // Skip kalau user belum login (cek cookie lx_token)
  if(!document.cookie.match(/lx_token=/))return;

  var POLL_INTERVAL=5000; // 5 detik
  var TOAST_DURATION=10000; // 10 detik

  var ICON_COIN='<svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="10" fill="#fbbf24" stroke="#78350f" stroke-width="1.5"/><text x="12" y="17" font-size="13" font-weight="900" text-anchor="middle" fill="#78350f" font-family="Arial">$</text></svg>';

  function fmtRp(n){
    n=Math.round(Number(n)||0);
    return 'Rp '+n.toLocaleString('id-ID');
  }

  function showCreditToast(credit){
    var c=document.getElementById('creditToastContainer');
    if(!c)return;
    var t=document.createElement('div');
    t.className='cr-toast';

    var noteText=credit.note||('Saldo dari '+credit.label);
    var labelText=credit.label||'Saldo';

    t.innerHTML=
      '<div class="cr-top">'+
        '<div class="cr-ic">'+ICON_COIN+'</div>'+
        '<div class="cr-amt">+'+fmtRp(credit.amount)+'</div>'+
        '<button class="cr-close" aria-label="Tutup">&times;</button>'+
      '</div>'+
      '<div class="cr-body">Saldo dari <b>'+labelText+'</b> berhasil di claim</div>'+
      '<div class="cr-bar"><div class="cr-bar-fill"></div></div>';

    c.appendChild(t);

    var closeBtn=t.querySelector('.cr-close');
    function dismiss(){
      if(t._dismissed)return;t._dismissed=true;
      t.classList.add('closing');
      setTimeout(function(){if(t.parentNode)t.parentNode.removeChild(t)},320);
    }
    closeBtn.addEventListener('click',dismiss);
    setTimeout(dismiss,TOAST_DURATION);
  }

  function pollCredits(){
    fetch('api/data.php',{
      method:'POST',
      headers:{'Content-Type':'application/json'},
      credentials:'same-origin',
      body:JSON.stringify({action:'check_new_credits'})
    }).then(function(r){return r.json()}).then(function(d){
      if(d&&d.ok&&d.credits&&d.credits.length){
        // Kalau banyak credit sekaligus, tampilkan bertahap (delay 500ms tiap)
        d.credits.forEach(function(c,i){
          setTimeout(function(){showCreditToast(c)},i*500);
        });
      }
    }).catch(function(){});
  }

  // First poll setelah 2 detik (kasih waktu page load selesai)
  setTimeout(pollCredits,2000);
  // Then poll every 5 seconds
  setInterval(pollCredits,POLL_INTERVAL);
})();
</script>
