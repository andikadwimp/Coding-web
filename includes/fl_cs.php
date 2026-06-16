<?php
// Shared Floating CS bubble — identik di index.php dan dashboard.php
// Params:
//   $onclick (string) — JS handler untuk click (default: 'openChat()')
//   $bottom (string) — CSS bottom position (default: '80px')
if (!isset($flCsOnclick)) $flCsOnclick = 'openChat()';
if (!isset($flCsBottom)) $flCsBottom = '80px';
?>
<div class="fl-cs-shared" id="flCs" onclick="<?=htmlspecialchars($flCsOnclick)?>"
     style="position:fixed;left:12px;bottom:<?=htmlspecialchars($flCsBottom)?>;z-index:90;width:52px;height:52px;border-radius:50%;overflow:hidden;cursor:pointer;animation:csFloat 3s ease-in-out infinite">
  <div id="flCsBody" style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--pri);border-radius:50%;border:2px solid rgba(255,255,255,.15);box-shadow:0 3px 12px rgba(0,0,0,.25)">
    <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" width="22" height="22"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>
  </div>
</div>
<style>
@keyframes csFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
.fl-cs-shared:active{animation:none!important;transform:scale(.92)!important}
</style>
<script>
// Replace pake gambar admin kalau ada — identik di pre/post login
(function(){
  function applyImg(){
    if(typeof SI==='undefined')return setTimeout(applyImg,50);
    var el=document.getElementById('flCsBody');
    if(!el||!SI.float_cs_img)return;
    el.style.cssText='width:100%;height:100%;border-radius:50%;overflow:hidden';
    el.innerHTML='<img src="'+SI.float_cs_img+'" style="width:100%;height:100%;object-fit:cover;display:block" onerror="this.parentElement.innerHTML=\'<div style=&quot;width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--pri);border-radius:50%&quot;><svg viewBox=&quot;0 0 24 24&quot; fill=&quot;none&quot; stroke=&quot;#fff&quot; stroke-width=&quot;2&quot; width=&quot;22&quot; height=&quot;22&quot;><path d=&quot;M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z&quot;/></svg></div>\';">';
  }
  applyImg();
})();
</script>
