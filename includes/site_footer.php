<?php
// ═══════════════════════════════════════════════════════════════
// GLOBAL SITE FOOTER (only render on beranda — flagged via constant)
// Caller must `define('RENDER_SITE_FOOTER', true);` before requiring.
// ═══════════════════════════════════════════════════════════════
if (!defined('RENDER_SITE_FOOTER')) return;

// Make sure $sets and $db are accessible
if(!isset($sets)){
  $sets=[];
  try{
    if(isset($db)){
      $st=$db->query("SELECT `key`,`value` FROM settings");
      foreach($st->fetchAll() as $r)$sets[$r['key']]=$r['value'];
      if(function_exists('normSettings'))normSettings($sets);
    }
  }catch(Exception $e){}
}
$_footLogo=$sets['footer_logo']??$sets['logo_url']??'';
$_siteName=$sets['site_name']??'Situs';

// Fetch providers (max 18) — try DB; if fail or empty, skip section
$_provs=[];
try{
  if(isset($db)){
    $_provs=$db->query("SELECT name,logo FROM providers WHERE status=1 AND game_count>0 ORDER BY sort_order ASC,game_count DESC LIMIT 18")->fetchAll();
  }
}catch(Exception $e){}
?>
<style>
.site-foot{margin:28px 0 0;padding:24px 16px 28px;background:linear-gradient(180deg,var(--bg) 0%,rgba(var(--pri-rgb,56,189,248),.05) 30%,var(--bg2) 100%);border-top:1px solid rgba(var(--pri-rgb,56,189,248),.18);position:relative;overflow:hidden}
.site-foot::before{content:'';position:absolute;top:0;left:0;right:0;height:1px;background:linear-gradient(90deg,transparent 5%,rgba(var(--pri-rgb,56,189,248),.6) 50%,transparent 95%)}
.sf-inner{max-width:540px;margin:0 auto}
.sf-app{display:grid;grid-template-columns:1fr auto;gap:14px;align-items:center;padding:14px 16px;background:linear-gradient(135deg,rgba(var(--pri-rgb,56,189,248),.18),rgba(var(--pri-rgb,56,189,248),.06));border:1px solid rgba(var(--pri-rgb,56,189,248),.35);border-radius:14px;margin-bottom:18px;position:relative;overflow:hidden}
.sf-app::before{content:'';position:absolute;top:-30px;right:-30px;width:120px;height:120px;background:radial-gradient(circle,rgba(var(--pri-rgb,56,189,248),.2),transparent 70%);pointer-events:none}
.sf-app-text{position:relative;z-index:1}
.sf-app-title{font-size:.85rem;font-weight:800;color:var(--t);margin-bottom:3px;letter-spacing:-.015em}
.sf-app-sub{font-size:.66rem;color:var(--t2);font-weight:500;line-height:1.4}
.sf-app-btn{padding:9px 14px;background:linear-gradient(135deg,var(--pri),var(--pri-d));color:#fff;border:none;border-radius:9px;font-family:inherit;font-size:.74rem;font-weight:700;cursor:pointer;display:flex;align-items:center;gap:5px;transition:transform .15s cubic-bezier(.16,1,.3,1),box-shadow .25s;box-shadow:0 3px 10px rgba(var(--pri-rgb,56,189,248),.4);letter-spacing:.2px;position:relative;z-index:1;text-decoration:none}
.sf-app-btn:hover{transform:translateY(-1px);box-shadow:0 5px 14px rgba(var(--pri-rgb,56,189,248),.55)}
.sf-app-btn svg{width:14px;height:14px}
.sf-pay{display:flex;flex-wrap:wrap;justify-content:center;gap:8px;padding:14px 0 4px;margin-bottom:14px;border-top:1px dashed var(--bd);border-bottom:1px dashed var(--bd)}
.sf-pay-item{display:flex;align-items:center;justify-content:center;min-width:48px;height:30px;padding:4px 10px;background:#fff;border-radius:6px;color:#0f172a;font-family:'Chakra Petch','Plus Jakarta Sans',sans-serif;font-weight:800;font-size:.62rem;letter-spacing:.4px;box-shadow:0 1px 3px rgba(0,0,0,.15)}
.sf-pay-item.qris{background:linear-gradient(135deg,#ed1c24,#a30303);color:#fff}
.sf-pay-item.dana{background:#118EEA;color:#fff}
.sf-pay-item.ovo{background:#4d2d9a;color:#fff}
.sf-pay-item.gopay{background:#01a4dc;color:#fff}
.sf-pay-item.bca{background:#0060ab;color:#fff}
.sf-pay-item.bri{background:#003d79;color:#fff}
.sf-pay-item.bni{background:#ee7600;color:#fff}
.sf-pay-item.mandiri{background:#003366;color:#fff}
.sf-pay-item.pulsa{background:linear-gradient(135deg,#10b981,#047857);color:#fff}
.sf-pay-item.linkaja{background:#e62a39;color:#fff}
.sf-pay-item.shopeepay{background:#ee4d2d;color:#fff}
.sf-pay-title{font-size:.6rem;font-weight:800;color:var(--t3);text-transform:uppercase;letter-spacing:1.4px;text-align:center;margin-bottom:8px;display:flex;align-items:center;gap:7px;justify-content:center}
.sf-pay-title::before,.sf-pay-title::after{content:'';flex:1;height:1px;background:linear-gradient(90deg,transparent,var(--bd),var(--bd))}
.sf-pay-title::after{background:linear-gradient(90deg,var(--bd),var(--bd),transparent)}
.sf-approval{display:flex;justify-content:center;gap:10px;flex-wrap:wrap;margin:14px 0 18px}
.sf-approval-pill{display:inline-flex;align-items:center;gap:6px;padding:7px 13px;background:linear-gradient(135deg,rgba(74,222,128,.14),rgba(74,222,128,.04));border:1px solid rgba(74,222,128,.4);border-radius:999px;font-size:.62rem;font-weight:800;color:#86efac;letter-spacing:.5px;text-transform:uppercase}
.sf-approval-pill svg{width:13px;height:13px}
.sf-approval-pill .ap-dot{width:6px;height:6px;border-radius:50%;background:#4ade80;box-shadow:0 0 8px #4ade80;animation:apDot 1.5s ease-in-out infinite}
@keyframes apDot{0%,100%{opacity:1}50%{opacity:.4}}
.sf-brand{display:flex;align-items:center;justify-content:center;gap:10px;margin-bottom:14px;text-align:center}
.sf-brand-logo{height:36px;width:auto;max-width:130px;object-fit:contain}
.sf-brand-name{font-family:'Chakra Petch','Plus Jakarta Sans',sans-serif;font-size:1.05rem;font-weight:800;letter-spacing:-.015em;color:var(--t)}
.sf-tagline{text-align:center;font-size:.78rem;color:var(--t2);font-weight:500;line-height:1.55;margin-bottom:18px;max-width:380px;margin-left:auto;margin-right:auto}
.sf-tagline b{color:var(--pri);font-weight:700}
.sf-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:18px}
.sf-info-item{padding:10px 12px;background:var(--bg2);border:1px solid var(--bd);border-radius:9px;font-size:.7rem;color:var(--t2);font-weight:600;line-height:1.45;display:flex;align-items:flex-start;gap:8px}
.sf-info-item svg{width:15px;height:15px;color:var(--pri);flex-shrink:0;margin-top:1px}
.sf-info-item b{color:var(--t);font-weight:700;display:block;font-size:.65rem;text-transform:uppercase;letter-spacing:.5px;margin-bottom:1px}
.sf-section{margin-bottom:18px}
.sf-title{font-size:.62rem;font-weight:800;color:var(--t3);text-transform:uppercase;letter-spacing:1.4px;margin-bottom:10px;display:flex;align-items:center;gap:7px}
.sf-title::before{content:'';width:3px;height:11px;background:linear-gradient(180deg,var(--pri),var(--pri-d));border-radius:2px}
.sf-prov{display:flex;flex-wrap:wrap;gap:6px}
.sf-prov-pill{display:flex;align-items:center;gap:5px;padding:6px 11px;background:var(--bg2);border:1px solid var(--bd);border-radius:8px;font-size:.66rem;font-weight:700;color:var(--t2);letter-spacing:.2px;transition:transform .2s cubic-bezier(.16,1,.3,1),border-color .15s,background .15s,color .15s}
.sf-prov-pill:hover{transform:translateY(-1px);border-color:rgba(var(--pri-rgb,56,189,248),.4);background:var(--s);color:var(--t)}
.sf-prov-pill img{width:14px;height:14px;object-fit:contain;border-radius:3px}
.sf-lic{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
.sf-badge{display:flex;flex-direction:column;align-items:center;justify-content:center;gap:5px;padding:11px 6px;background:var(--bg2);border:1px solid var(--bd);border-radius:10px;font-size:.62rem;font-weight:800;color:var(--t2);letter-spacing:.4px;transition:transform .2s cubic-bezier(.16,1,.3,1),border-color .15s,background .15s;text-align:center;line-height:1.2}
.sf-badge:hover{transform:translateY(-2px);border-color:rgba(var(--pri-rgb,56,189,248),.5);background:var(--s)}
.sf-badge.age{background:linear-gradient(135deg,rgba(239,68,68,.14),rgba(239,68,68,.04));border-color:rgba(239,68,68,.4);color:#fca5a5}
.sf-badge svg{width:18px;height:18px;color:currentColor;flex-shrink:0}
.sf-links{display:flex;flex-wrap:wrap;justify-content:center;gap:14px 18px;margin:18px 0 16px;font-size:.72rem;font-weight:600}
.sf-links a{color:var(--t2);text-decoration:none;transition:color .15s}
.sf-links a:hover{color:var(--pri)}
.sf-divider{height:1px;background:linear-gradient(90deg,transparent,var(--bd),transparent);margin:18px 0 14px}
.sf-disc{font-size:.66rem;color:var(--t3);text-align:center;line-height:1.65;font-weight:500;letter-spacing:.1px}
.sf-disc b{color:var(--t2);font-weight:700}
.sf-disc .sf-warn{color:#fca5a5;font-weight:600}
.sf-copy{text-align:center;font-size:.62rem;color:var(--t3);margin-top:10px;letter-spacing:.5px;font-weight:500;font-family:'Chakra Petch','Plus Jakarta Sans',sans-serif}
@media(max-width:380px){.sf-lic{grid-template-columns:repeat(2,1fr)}.sf-info-grid{grid-template-columns:1fr}}
</style>
<div class="site-foot">
  <div class="sf-inner">

    <!-- App Download CTA -->
    <div class="sf-app">
      <div class="sf-app-text">
        <div class="sf-app-title">📱 Pasang Aplikasi</div>
        <div class="sf-app-sub">Akses lebih cepat, notifikasi real-time, mode offline</div>
      </div>
      <a href="download.php" class="sf-app-btn">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
        Unduh
      </a>
    </div>

    <!-- Payment Methods -->
    <div class="sf-pay-title">Metode Pembayaran</div>
    <div class="sf-pay">
      <span class="sf-pay-item qris">QRIS</span>
      <span class="sf-pay-item dana">DANA</span>
      <span class="sf-pay-item ovo">OVO</span>
      <span class="sf-pay-item gopay">GOPAY</span>
      <span class="sf-pay-item shopeepay">SHOPEEPAY</span>
      <span class="sf-pay-item linkaja">LINKAJA</span>
      <span class="sf-pay-item bca">BCA</span>
      <span class="sf-pay-item bri">BRI</span>
      <span class="sf-pay-item bni">BNI</span>
      <span class="sf-pay-item mandiri">MANDIRI</span>
      <span class="sf-pay-item pulsa">PULSA</span>
    </div>

    <!-- Approval -->
    <div class="sf-approval">
      <span class="sf-approval-pill"><span class="ap-dot"></span>ONLINE</span>
      <span class="sf-approval-pill" style="background:linear-gradient(135deg,rgba(var(--pri-rgb),.14),rgba(var(--pri-rgb),.04));border-color:rgba(var(--pri-rgb),.4);color:var(--pri)">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>VERIFIED
      </span>
      <span class="sf-approval-pill" style="background:linear-gradient(135deg,rgba(251,191,36,.14),rgba(251,191,36,.04));border-color:rgba(251,191,36,.4);color:#fbbf24">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>CASINO LEADER
      </span>
    </div>

    <!-- Brand -->
    <div class="sf-brand">
      <?php if($_footLogo): ?>
        <img src="<?=htmlspecialchars(function_exists('normUrl')?normUrl($_footLogo):$_footLogo)?>" alt="<?=htmlspecialchars($_siteName)?>" class="sf-brand-logo" onerror="this.style.display='none'">
      <?php else: ?>
        <div class="sf-brand-name"><?=htmlspecialchars($_siteName)?></div>
      <?php endif; ?>
    </div>

    <!-- Tagline -->
    <p class="sf-tagline">
      Platform game terpercaya dengan <b>ratusan permainan</b> dari provider top dunia.
      Deposit &amp; withdraw cepat 24 jam, dukungan customer service ramah, transaksi aman.
    </p>

    <!-- Info grid -->
    <div class="sf-info-grid">
      <div class="sf-info-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        <span><b>Online</b>Layanan 24 / 7</span>
      </div>
      <div class="sf-info-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/><circle cx="12" cy="12" r="4"/></svg>
        <span><b>Cepat</b>Transaksi instan</span>
      </div>
      <div class="sf-info-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        <span><b>Aman</b>Enkripsi end-to-end</span>
      </div>
      <div class="sf-info-item">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6M23 11h-6"/></svg>
        <span><b>Komunitas</b>Bonus referral</span>
      </div>
    </div>

    <!-- Penyedia -->
    <?php if($_provs): ?>
    <div class="sf-section">
      <div class="sf-title">Penyedia Game Resmi</div>
      <div class="sf-prov">
        <?php foreach($_provs as $_p):
          $_lg=function_exists('normUrl')?normUrl($_p['logo']??''):($_p['logo']??'');
        ?>
        <span class="sf-prov-pill">
          <?php if($_lg): ?><img src="<?=htmlspecialchars($_lg)?>" loading="lazy" onerror="this.style.display='none'"><?php endif; ?>
          <?=htmlspecialchars($_p['name'])?>
        </span>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Lisensi -->
    <div class="sf-section">
      <div class="sf-title">Lisensi & Tanggung Jawab</div>
      <div class="sf-lic">
        <span class="sf-badge age" title="Hanya untuk usia 18 tahun ke atas">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
          <span>18+</span>
        </span>
        <span class="sf-badge age" title="Direkomendasikan 21+">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
          <span>21+</span>
        </span>
        <span class="sf-badge" title="GameAware — bermain bertanggung jawab">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="9" y1="9" x2="9.01" y2="9"/><line x1="15" y1="9" x2="15.01" y2="9"/><path d="M8 14s1.5 2 4 2 4-2 4-2"/></svg>
          <span>GameAware</span>
        </span>
        <span class="sf-badge" title="GameCare — dukungan pemain">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>
          <span>GameCare</span>
        </span>
        <span class="sf-badge" title="Lisensi GGR Gaming">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><polyline points="9 12 11 14 15 10"/></svg>
          <span>GGR Gaming</span>
        </span>
        <span class="sf-badge" title="Random Number Generator tervalidasi">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="2.18"/><path d="M7 8h.01M12 8h.01M17 8h.01M7 12h.01M12 12h.01M17 12h.01M7 16h.01M12 16h.01M17 16h.01"/></svg>
          <span>RNG Tested</span>
        </span>
      </div>
    </div>

    <div class="sf-divider"></div>

    <!-- Disclaimer -->
    <div class="sf-disc">
      <span class="sf-warn">⚠ Bermain bertanggung jawab.</span> Hanya untuk <b>18+</b>. Permainan judi dapat menyebabkan kecanduan. Mainkan dalam batas wajar dan jangan sampai mengganggu kehidupan sehari-hari, keluarga, atau pekerjaan.
      <br><br>
      Jika kamu atau orang terdekat mengalami masalah perjudian, hubungi layanan dukungan profesional. Tutup akun kapan saja melalui <b>Profil → Keluar</b>.
    </div>

    <div class="sf-copy">
      &copy; <?=date('Y')?> <?=htmlspecialchars($_siteName)?> · ALL RIGHTS RESERVED
    </div>

  </div>
</div>
