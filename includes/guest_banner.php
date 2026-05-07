<?php
/**
 * Guest banner — included by action-pages when $isLoggedIn is false.
 * Renders top notice with login CTA. Page content stays visible but actions
 * gated by JS via requireLogin() helper from pwa_head.php.
 *
 * Usage:
 *   <?php if(!$isLoggedIn) require __DIR__.'/includes/guest_banner.php'; ?>
 *
 * Optional: set $guestBannerLabel before include for custom action label.
 */
$guestBannerLabel = $guestBannerLabel ?? 'fitur ini';
?>
<style>
.gst-bn{
  margin:14px 14px 20px;
  padding:16px 18px;
  background:linear-gradient(135deg,var(--pri-l),rgba(var(--pri-rgb),.06));
  border:1.5px solid rgba(var(--pri-rgb),.25);
  border-radius:14px;
  display:flex;align-items:center;gap:14px;
  position:relative;overflow:hidden;
  box-shadow:0 4px 14px rgba(var(--pri-rgb),.08);
}
.gst-bn::before{
  content:'';position:absolute;top:0;left:0;width:4px;height:100%;
  background:linear-gradient(180deg,var(--pri),var(--pri-d));
}
.gst-bn-icon{
  width:42px;height:42px;flex-shrink:0;
  background:linear-gradient(135deg,var(--pri),var(--pri-d));
  border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  box-shadow:0 4px 10px rgba(var(--pri-rgb),.3);
}
.gst-bn-icon svg{width:22px;height:22px;color:#fff}
.gst-bn-text{flex:1;min-width:0}
.gst-bn-title{
  font-size:.82rem;font-weight:800;color:var(--t);
  margin-bottom:2px;letter-spacing:-.01em;
}
.gst-bn-sub{font-size:.7rem;color:var(--t2);font-weight:500;line-height:1.35}
.gst-bn-btn{
  flex-shrink:0;
  padding:9px 16px;
  background:var(--pri);
  color:#fff;border:none;
  border-radius:9px;
  font-weight:800;font-size:.78rem;
  cursor:pointer;font-family:inherit;
  letter-spacing:.2px;
  box-shadow:0 4px 10px rgba(var(--pri-rgb),.25);
  transition:all .15s;
  text-decoration:none;
  display:inline-flex;align-items:center;gap:5px;
  white-space:nowrap;
}
.gst-bn-btn:hover{background:var(--pri-d);transform:translateY(-1px);box-shadow:0 6px 14px rgba(var(--pri-rgb),.35)}
.gst-bn-btn:active{transform:scale(.96)}
@media(max-width:380px){
  .gst-bn{padding:14px 14px;gap:10px}
  .gst-bn-icon{width:36px;height:36px}
  .gst-bn-icon svg{width:18px;height:18px}
  .gst-bn-title{font-size:.76rem}
  .gst-bn-sub{font-size:.65rem}
  .gst-bn-btn{padding:8px 12px;font-size:.72rem}
}
</style>
<div class="gst-bn">
  <div class="gst-bn-icon">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4"/><polyline points="10 17 15 12 10 7"/><line x1="15" y1="12" x2="3" y2="12"/></svg>
  </div>
  <div class="gst-bn-text">
    <div class="gst-bn-title">Login Dulu</div>
    <div class="gst-bn-sub">Masuk untuk akses <?=htmlspecialchars($guestBannerLabel)?>. Daftar gratis &lt; 30 detik.</div>
  </div>
  <a class="gst-bn-btn" href="index.php?login=1&from=<?=urlencode($_SERVER['REQUEST_URI']??'/')?>">
    Masuk
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="13" height="13"><polyline points="9 18 15 12 9 6"/></svg>
  </a>
</div>
