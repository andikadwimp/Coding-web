<?php
// ═══════════════════════════════════════════════════════════════
// DYNAMIC THEME GENERATOR
// Baca warna & glass settings dari tabel `settings` (key prefix: theme_*)
// Default fallback ke Sky Blue palette kalau settings belum ada.
// Atur dari panel admin: /team/theme.php
// ═══════════════════════════════════════════════════════════════
require_once __DIR__.'/includes/config.php';
header('Content-Type: text/css; charset=UTF-8');
header('Cache-Control: public, max-age=300');

// ─── Default palette (Sky Blue, sama dgn sebelum dynamic) ───
$defaults = [
    // Default: Ocean Depth (blue dark) — premium casino feel
    'theme_primary'    => '#0ea5e9',
    'theme_primary_d'  => '#0284c7',
    'theme_primary_l'  => '#7dd3fc',
    'theme_secondary'  => '#0ea5e9',
    'theme_secondary_d'=> '#0284c7',
    'theme_bg'         => '#0a1628',
    'theme_bg2'        => '#14233f',
    'theme_bg3'        => '#1d3057',
    'theme_surface'    => '#14233f',
    'theme_surface2'   => '#1d3057',
    'theme_surface3'   => '#263d6e',
    'theme_nav_bg'     => '#050d1a',
    'theme_text'       => '#ffffff',
    'theme_text2'      => '#b5c5d4',
    'theme_text3'      => '#7a8ea3',
    'theme_glass_enabled'   => '0',
    'theme_glass_blur'      => '14',
    'theme_glass_opacity'   => '0.55',
    'theme_glass_bg_image'  => '',
];

// ─── Load from DB ───
$th = $defaults;
try {
    $rows = $db->query("SELECT `key`,`value` FROM settings WHERE `key` LIKE 'theme_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach ($defaults as $k => $v) {
        if (isset($rows[$k]) && $rows[$k] !== '') $th[$k] = $rows[$k];
    }
} catch (Exception $e) { /* fallback to defaults */ }

// ─── Helpers ───
function hex2rgb($hex){
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) return '255,255,255';
    return hexdec(substr($hex,0,2)).','.hexdec(substr($hex,2,2)).','.hexdec(substr($hex,4,2));
}

$priRgb = hex2rgb($th['theme_primary']);
$secRgb = hex2rgb($th['theme_secondary']);
$bgRgb  = hex2rgb($th['theme_bg']);
$bg2Rgb = hex2rgb($th['theme_bg2']);
$sRgb   = hex2rgb($th['theme_surface']);
$navRgb = hex2rgb($th['theme_nav_bg']);

$glass = ($th['theme_glass_enabled'] === '1' || $th['theme_glass_enabled'] === 1 || $th['theme_glass_enabled'] === true);
$blur  = max(0, min(40, intval($th['theme_glass_blur'])));
$opaq  = max(0.05, min(0.95, floatval($th['theme_glass_opacity'])));
$bgImg = trim($th['theme_glass_bg_image']);
$bgMode = trim($th['theme_bg_mode']??'cover'); // 'tile' | 'cover' | 'contain'

// Compute glass surface colors (semi-transparent)
$gSurface  = $glass ? "rgba($sRgb,$opaq)"   : $th['theme_surface'];
$gSurface2 = $glass ? "rgba($sRgb,".min(0.95,$opaq+0.1).")" : $th['theme_surface2'];
$gBg2      = $glass ? "rgba($bg2Rgb,$opaq)" : $th['theme_bg2'];
$gNav      = $glass ? "rgba($navRgb,".max(0.5,$opaq+0.15).")" : $th['theme_nav_bg'];

// ─── Detect dark vs light theme from bg luminance ───
// Pakai luminance formula approximate: 0.299*R + 0.587*G + 0.114*B
$bgRgbParts = explode(',', $bgRgb);
$bgLum = (0.299*intval($bgRgbParts[0]) + 0.587*intval($bgRgbParts[1]) + 0.114*intval($bgRgbParts[2])) / 255;
$isLight = $bgLum > 0.5;

// ─── AUTO-CONTRAST: prevent text invisible if user picks bad combo ───
// If text color doesn't contrast vs bg (both light or both dark), force flip
$tRgbParts = explode(',', hex2rgb($th['theme_text']));
$tLum = (0.299*intval($tRgbParts[0]) + 0.587*intval($tRgbParts[1]) + 0.114*intval($tRgbParts[2])) / 255;
if (abs($tLum - $bgLum) < 0.4) {
    // Bad contrast — auto-flip text to opposite of bg
    $th['theme_text']  = $isLight ? '#0a0a0a' : '#ffffff';
    $th['theme_text2'] = $isLight ? '#3a3a3a' : '#cccccc';
    $th['theme_text3'] = $isLight ? '#6a6a6a' : '#9a9a9a';
}

// Border & tints auto-adapt: pakai text RGB. Di dark theme text=white → bd jadi white-alpha;
// Di light theme text=dark → bd jadi dark-alpha. Selalu kontras sama bg.
$tRgb = hex2rgb($th['theme_text']);
// Untuk light theme, text adalah dark (e.g. #0f172a = "15,23,42"), jadi bd dark-alpha cocok di light bg.
// Untuk dark theme, text adalah white (e.g. "255,255,255"), jadi bd white-alpha cocok di dark bg.
$bdAlpha  = $glass ? ($isLight ? .12 : .18) : ($isLight ? .08 : .1);
$bd2Alpha = $glass ? ($isLight ? .18 : .25) : ($isLight ? .14 : .15);
$tintLow  = $isLight ? .03 : .04;  // very subtle tint
$tintMid  = $isLight ? .05 : .06;
$tintHi   = $isLight ? .08 : .1;
?>
/* ═══ FONT IMPORTS — site-wide typography stack ═══ */
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Outfit:wght@400;500;600;700;800;900&family=JetBrains+Mono:wght@500;600;700&family=Chakra+Petch:wght@500;600;700;800&display=swap');

:root{
  --pri: <?=$th['theme_primary']?>;
  --pri-d: <?=$th['theme_primary_d']?>;
  --pri-l: rgba(<?=$priRgb?>,.15);
  --pri2: <?=$th['theme_primary_l']?>;
  --sec: <?=$th['theme_secondary']?>;
  --sec-d: <?=$th['theme_secondary_d']?>;
  --pri-rgb: <?=$priRgb?>;
  --sec-rgb: <?=$secRgb?>;

  --bg: <?=$th['theme_bg']?>;
  --bg2: <?=$gBg2?>;
  --bg3: <?=$th['theme_bg3']?>;

  --t: <?=$th['theme_text']?>;
  --t2: <?=$th['theme_text2']?>;
  --t3: <?=$th['theme_text3']?>;
  --t-rgb: <?=$tRgb?>;

  --bd: rgba(<?=$tRgb?>,<?=$bdAlpha?>);
  --bd2: rgba(<?=$tRgb?>,<?=$bd2Alpha?>);
  /* Tint stack — input bg, hover bg, dll. Auto kontras vs bg utama */
  --tint-1: rgba(<?=$tRgb?>,<?=$tintLow?>);
  --tint-2: rgba(<?=$tRgb?>,<?=$tintMid?>);
  --tint-3: rgba(<?=$tRgb?>,<?=$tintHi?>);

  --nav-bg: <?=$gNav?>;
  --s: <?=$gSurface?>;
  --s2: <?=$gSurface2?>;
  --s3: <?=$th['theme_surface3']?>;
  --g: <?=$th['theme_primary']?>;
  --gl: <?=$th['theme_primary_l']?>;

  --green: #10b981;
  --red: #ef4444;
  --orange: #f59e0b;
  --blue: #3b82f6;

  --glass-blur: <?=$blur?>px;
  --glass-on: <?=$glass ? '1' : '0'?>;
  --is-light: <?=$isLight ? '1' : '0'?>;
}

<?php if ($bgImg): /* Bg image works ALWAYS, regardless of glass mode */
  $bgSize = ($bgMode==='tile') ? '80px 80px' : $bgMode;
  $bgRepeat = ($bgMode==='tile') ? 'repeat' : 'no-repeat';
?>
html,body{
  background-color:<?=$th['theme_bg']?>;
  background-image:url('<?=htmlspecialchars($bgImg)?>');
  background-size:<?=$bgSize?>;
  background-repeat:<?=$bgRepeat?>;
  background-position:center;
  background-attachment:fixed;
  color:var(--t);
}
body::before{
  content:'';
  position:fixed;inset:0;
  background:rgba(<?=$bgRgb?>,<?=$glass?'.55':'.78'?>);
  z-index:-1;
  pointer-events:none;
}
<?php else: ?>
html,body{
  background:var(--bg);
  color:var(--t);
  /* Atmospheric depth — subtle radial gradient hint of primary color */
  background-image:
    radial-gradient(1200px 800px at 0% 0%, rgba(var(--pri-rgb),<?=$isLight?'.03':'.05'?>) 0%, transparent 60%),
    radial-gradient(1000px 700px at 100% 100%, rgba(var(--pri-rgb),<?=$isLight?'.02':'.04'?>) 0%, transparent 55%);
  background-attachment:fixed;
}
<?php endif; ?>

/* Selection color follows theme */
::selection{background:rgba(var(--pri-rgb),.3);color:var(--t)}
::-moz-selection{background:rgba(var(--pri-rgb),.3);color:var(--t)}

/* Smooth scroll */
html{scroll-behavior:smooth;-webkit-text-size-adjust:100%}

body{
  font-family:'Plus Jakarta Sans','Outfit','Poppins','Segoe UI',Arial,sans-serif;
  margin:0;padding:0;min-height:100vh;
  -webkit-font-smoothing:antialiased;
  -moz-osx-font-smoothing:grayscale;
  text-rendering:optimizeLegibility;
}

/* Focus visible — accessibility + theme glow */
*:focus-visible{
  outline:2px solid var(--pri);
  outline-offset:2px;
  border-radius:4px;
}

.btn-pri,.btn.btn-pri{
  background:linear-gradient(135deg,var(--pri) 0%,var(--pri-d) 100%);
  color:#ffffff;font-weight:800;border:none;border-radius:10px;
  padding:11px 20px;cursor:pointer;font-family:inherit;font-size:.88rem;letter-spacing:.3px;
  transition:all .25s cubic-bezier(.4,0,.2,1);
  box-shadow:0 4px 14px rgba(var(--pri-rgb),.45),inset 0 1px 0 rgba(255,255,255,.25);
  text-shadow:0 1px 2px rgba(0,0,0,.15);
}
.btn-pri:hover,.btn.btn-pri:hover{
  transform:translateY(-1px);
  background:linear-gradient(135deg,var(--pri2) 0%,var(--pri) 100%);
  box-shadow:0 6px 20px rgba(var(--pri-rgb),.6),inset 0 1px 0 rgba(255,255,255,.3);
}
.btn-pri:active,.btn.btn-pri:active{transform:translateY(0);box-shadow:0 2px 8px rgba(var(--pri-rgb),.4)}

.btn-sec,.btn.btn-sec{
  background:transparent;color:var(--pri);border:1.5px solid var(--pri);
  border-radius:8px;padding:10px 20px;cursor:pointer;font-family:inherit;font-size:.88rem;
  font-weight:600;transition:all .15s;
}
.btn-sec:hover,.btn.btn-sec:hover{background:var(--pri-l);box-shadow:0 0 12px rgba(var(--pri-rgb),.3)}

.btn-red{background:var(--red);color:#fff;border:none;border-radius:8px;padding:10px 20px;cursor:pointer;font-family:inherit;font-size:.88rem;font-weight:600}
.btn-red:hover{opacity:.85}
.btn-sm{padding:5px 12px;font-size:.72rem}

input,textarea,select{
  background:var(--tint-1);color:var(--t);
  border:1.5px solid var(--bd);border-radius:8px;
  padding:11px 13px;font-size:.88rem;font-family:inherit;
  width:100%;outline:none;box-sizing:border-box;
}
input:focus,textarea:focus,select:focus{border-color:var(--pri);background:var(--tint-2);box-shadow:0 0 0 3px rgba(var(--pri-rgb),.15);transition:all .15s cubic-bezier(.4,0,.2,1)}

label{font-size:.75rem;color:var(--t2);font-weight:600;margin-bottom:4px;display:block}
.fg{margin-bottom:14px}
.fg-row{display:flex;gap:10px}
.fg-row .fg{flex:1}

.msg{padding:10px 12px;border-radius:8px;font-size:.78rem;margin-bottom:12px}
.msg-ok{background:rgba(16,185,129,.1);color:var(--green);border:1px solid rgba(16,185,129,.3)}
.msg-err{background:rgba(239,68,68,.1);color:var(--red);border:1px solid rgba(239,68,68,.3)}
.msg-warn{background:rgba(245,158,11,.1);color:var(--orange);border:1px solid rgba(245,158,11,.3)}

/* ═══ Surface uniform sesuai tema ═══ */
.hdr{background:var(--bg);border-bottom:1px solid var(--bd)}
<?php if ($glass): ?>
.hdr{background:rgba(<?=$bgRgb?>,.7)!important;backdrop-filter:blur(<?=$blur?>px) saturate(140%);-webkit-backdrop-filter:blur(<?=$blur?>px) saturate(140%)}
<?php else: ?>
.hdr{background:var(--bg)!important}
<?php endif; ?>

.hdr button{background:var(--s)!important;border:1px solid var(--bd)!important;color:var(--t)!important}
.hdr h1{color:var(--t)!important}

.card,.box,.panel,.row{background:var(--s);color:var(--t)}
.item-row{background:var(--s)!important;border:1px solid var(--bd)}

.ftab,.chip,.uc-tag,.dt-tab{background:var(--bg2);color:var(--t2);border:1px solid var(--bd)}
.ftab.on,.chip.on,.dt-tab.on{background:var(--pri)!important;color:#fff!important}

#rwTabs > div{background:var(--s)!important;color:var(--t2)!important;border:1px solid var(--bd)}
#rwTabs > div[style*="--sec"]{background:var(--pri)!important;color:#fff!important;border-color:var(--pri)!important}

.bar,.progress-bg,.tomeric{background:var(--bg2)!important}
input,select,textarea{background:var(--bg2);color:var(--t);border:1px solid var(--bd)}

.bnav,.bottom-nav,nav.bottom{background:var(--nav-bg)!important;border-top:1px solid var(--bd)}
.modal-box,.k-box,.pay-overlay,.pop,.popup,.sheet{background:var(--s)!important;border:1px solid var(--bd)}
body,.wrap,.content,main{background:var(--bg)}
.empty,.kosong{background:var(--s);color:var(--t3);border:1px solid var(--bd);border-radius:10px;padding:24px;text-align:center}

.vip-card,.vc-box,.vip-banner{background:var(--s)!important;border:1px solid var(--bd)!important}
.vc-badge{background:var(--bg2)!important;border:1px solid var(--bd)!important}
.vc-badge .vbi{background:var(--pri)!important;color:#fff!important}
.vc-detail{background:var(--pri)!important;color:#fff!important}
.vc-bar{background:var(--bg2)!important;border:1px solid var(--bd)}
.vc-bar .fill{background:var(--pri)!important}

.notif-card,.memo-card,.alert-card{background:var(--s)!important;border:1px solid var(--bd)}
.sb-main,.search-box,.sb-search{background:var(--bg2)!important;border:1px solid var(--bd)!important}
.b-overlay{background:transparent!important}
.qa-card .qa-fb{background:var(--s)!important;border:1px solid var(--bd)}

.prov-pill,.cat-pill,.gen-pill{background:var(--bg2)!important;border:1px solid var(--bd)}
.prov-pill.on,.cat-pill.on,.gen-pill.on{background:var(--pri)!important;color:#fff!important;border-color:var(--pri)!important}

.opt,.dropdown-item{background:var(--s)!important;color:var(--t)}
.opt:hover,.dropdown-item:hover{background:var(--bg2)!important}

.toast-box,.alert,.snack{background:var(--s)!important;color:var(--t);border:1px solid var(--bd)}
.modal,.overlay,.toast-overlay{background:rgba(0,0,0,.65)!important}

.mth,.method-card,.pay-method,.bank-item{background:var(--s)!important;border:1px solid var(--bd)!important;color:var(--t)}
.mth.sel,.method-card.sel,.pay-method.sel,.bank-item.sel{border-color:var(--pri)!important;background:var(--bg2)!important}
.mth .m-name,.method-card .name{color:var(--t)!important}

.qk,.nominal-pill{background:var(--bg2)!important;color:var(--t)!important;border:1px solid var(--bd)!important}
.qk.sel,.nominal-pill.sel{background:var(--pri)!important;color:#fff!important;border-color:var(--pri)!important}

.bon-sel,.bonus-card{background:var(--bg2)!important;color:var(--t)!important;border:1px solid var(--bd)!important}
.bon-sel.sel,.bonus-card.sel{background:var(--pri)!important;color:#fff!important;border-color:var(--pri)!important}

.tx-row,.hist-row,.row-item{background:var(--s)!important;border:1px solid var(--bd);border-radius:10px;margin-bottom:6px;padding:12px}
hr{border:none;border-top:1px solid var(--bd)}

.km-box,.sg-item,.data-sub-card,.stat-item,.inv-box,.reward-box,.tier-box,.dn-item{background:var(--bg2)!important;border:1px solid var(--bd);color:var(--t)}
.km-box .km-val,.sg-item .sg-val,.stat-item .sv{color:var(--pri)!important}
.km-box .km-lbl,.sg-item .sg-lbl{color:var(--t2)!important}

.share-card,.promo-card,.reward-card,.bonus-banner{background:var(--s)!important;border:1px solid var(--bd);color:var(--t)}
.share-card::before,.promo-card::before{display:none!important}
.link-url{color:var(--pri)!important}

.tabs{background:var(--bg)!important;border-bottom:1px solid var(--bd)}
.tab{color:var(--t3)!important;background:transparent!important;border:none!important;border-bottom:2px solid transparent!important}
.tab.on{color:var(--pri)!important;background:transparent!important;border-bottom:2px solid var(--pri)!important}

/* ═══ BOTTOM NAV — base rules (.bnav-i was missing styles) ═══ */
.bnav{
  overflow:visible!important;
  display:flex!important;
  align-items:flex-end!important;
  height:64px;
  padding:6px 4px env(safe-area-inset-bottom,6px)!important;
}
.bnav-i{
  flex:1;
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:flex-end;
  gap:3px;
  padding:6px 0 4px;
  color:var(--t3);
  font-size:.6rem;
  font-weight:600;
  text-decoration:none;
  letter-spacing:.2px;
  transition:color .15s;
  min-width:0;
}
.bnav-i:hover{color:var(--t2)}
.bnav-i.active{color:var(--pri)}
.bnav-i > svg{
  width:24px!important;
  height:24px!important;
  flex-shrink:0;
  display:block;
}
.bnav-i > span{
  font-size:.62rem;
  line-height:1;
  white-space:nowrap;
  overflow:hidden;
  text-overflow:ellipsis;
  max-width:100%;
}

/* ═══ FAB middle button (Undang) ═══ */
.bnav-i.bnav-fab{
  flex:0 0 86px;
  position:relative;
  align-self:flex-start;
  margin-top:-8px;
}
.bnav-i.bnav-fab .bnav-fab-circle{
  width:62px;height:62px;
  border-radius:50%;
  background:linear-gradient(135deg,var(--pri) 0%,var(--pri-d) 100%);
  display:flex;align-items:center;justify-content:center;
  margin:-22px auto 0;
  box-shadow:0 6px 18px rgba(var(--pri-rgb),.5),0 0 0 4px var(--bg);
  position:relative;
  transition:all .25s cubic-bezier(.4,0,.2,1);
  animation:fabPulse 2.4s ease-in-out infinite;
}
@keyframes fabPulse{
  0%,100%{box-shadow:0 6px 18px rgba(var(--pri-rgb),.5),0 0 0 4px var(--bg),0 0 0 0 rgba(var(--pri-rgb),.5)}
  50%{box-shadow:0 6px 18px rgba(var(--pri-rgb),.6),0 0 0 4px var(--bg),0 0 0 10px rgba(var(--pri-rgb),0)}
}
.bnav-i.bnav-fab:active .bnav-fab-circle{transform:scale(.94)}
.bnav-i.bnav-fab .bnav-fab-circle svg{
  width:28px!important;height:28px!important;
  color:#fff!important;
  fill:none!important;stroke:#fff!important;stroke-width:2.4!important;
}
.bnav-i.bnav-fab.active .bnav-fab-circle{
  box-shadow:0 8px 24px rgba(var(--pri-rgb),.65),0 0 0 4px var(--bg);
  animation:none;
}
.bnav-i.bnav-fab.active .bnav-fab-circle svg{
  fill:rgba(255,255,255,.18)!important;
}
.bnav-i.bnav-fab > span{
  margin-top:6px!important;
  color:var(--pri)!important;
  font-weight:800!important;
  font-size:.6rem!important;
  letter-spacing:.3px;
  text-align:center;
  width:100%;
}
.bnav-i.bnav-fab.active > span{color:var(--pri-d)!important}

/* Game card */
.gc,a.gc{display:block;position:relative;width:100%;background:var(--s);border:2px solid var(--pri);border-radius:10px;overflow:hidden;padding:0;text-decoration:none;color:inherit}
.gc > img{display:block;width:100%;height:120px;object-fit:cover}
.gc .thumb{display:none;width:100%;height:120px;align-items:center;justify-content:center;text-align:center;font-size:.65rem;color:var(--t3);background:var(--tint-1);padding:6px;font-weight:600}
.gwrap.no-image .gc > img{display:none}
.gwrap.no-image .gc .thumb{display:flex}
.gc .rtp,.gc .gc-rtp{position:absolute;top:6px;left:6px;background:rgba(0,0,0,.75);color:var(--green);font-size:.62rem;font-weight:800;padding:3px 7px;border-radius:4px;z-index:3}
.gc .fav,.gc .gc-fav{position:absolute;top:6px;right:6px;width:24px;height:24px;background:rgba(0,0,0,.55);border:none;border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;z-index:3;padding:0}
.gc .gn{position:static;display:block;padding:6px 8px 8px;background:var(--s);background-image:none;border-top:1px solid var(--tint-1)}
.gc .gn-name{font-size:.7rem;font-weight:700;color:var(--t);line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.gc .gn-prov{font-size:.58rem;font-weight:500;color:var(--t3);line-height:1.2;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-top:2px}
.gprov,.gname-out{display:none!important}
.gwrap.no-image .gc{opacity:.65;border-color:rgba(148,163,184,.25)}

<?php if ($glass): ?>
/* ═══ GLASS MODE — backdrop blur untuk surface utama ═══ */
.bnav,.bottom-nav,nav.bottom,
.hdr,
.card,.box,.panel,
.modal-box,.k-box,.pay-overlay,.pop,.popup,.sheet,
.notif-card,.memo-card,.alert-card,
.vip-card,.vc-box,.vip-banner,
.qa-card .qa-fb,
.tx-row,.hist-row,.row-item,
.share-card,.promo-card,.reward-card,.bonus-banner,
.km-box,.sg-item,.data-sub-card,.stat-item,.inv-box,.reward-box,.tier-box,.dn-item,
.mth,.method-card,.pay-method,.bank-item,
.qk,.nominal-pill,
.bon-sel,.bonus-card,
.ftab,.chip,.uc-tag,.dt-tab,
.prov-pill,.cat-pill,.gen-pill,
.sb-main,.search-box,.sb-search,
.toast-box,.alert,.snack,
.empty,.kosong,
.opt,.dropdown-item,
.bar,.progress-bg,
.pay-card,.pay-status,.pay-tf,
.amt-input,.pdd-wrap,.pdd-list,.bon-dd,.bon-list,
.info,.pay-guide,
.gc,a.gc{
  backdrop-filter:blur(<?=$blur?>px) saturate(140%)!important;
  -webkit-backdrop-filter:blur(<?=$blur?>px) saturate(140%)!important;
}
<?php else: ?>
/* Glass OFF — paksa solid */
*{backdrop-filter:none!important;-webkit-backdrop-filter:none!important}
/* bg-image preserved — only strip backdrop-filter when glass OFF */
<?php endif; ?>

/* ═══ FW66 LAYOUT MATCHING — site-wide visual unity ═══ */

/* Subtle diamond/wajik pattern fallback if no custom bg */
<?php if (!$bgImg): ?>
body::after{
  content:'';position:fixed;inset:0;z-index:-1;pointer-events:none;
  background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32' width='32' height='32'><path d='M16 4 L28 16 L16 28 L4 16 Z' fill='none' stroke='rgba(<?=$isLight?'0,0,0':'255,255,255'?>,.025)' stroke-width='.6'/></svg>");
  background-size:32px 32px;opacity:.7;
}
<?php endif; ?>

/* Page header — FW66 style: back arrow left + center title */
.hdr,header.hdr,.page-hdr{
  display:flex;align-items:center;justify-content:space-between;
  padding:14px 16px;
  background:transparent !important;
  border-bottom:none !important;
}
.hdr h1,.page-hdr h1,.hdr-title{
  flex:1;text-align:center;
  font-family:'Poppins',sans-serif;
  font-size:1.05rem;font-weight:700;color:var(--t);
  letter-spacing:-.005em;
}
.hdr button.back-btn,.hdr-back{
  width:36px;height:36px;border-radius:8px;
  background:rgba(<?=$isLight?'0,0,0':'255,255,255'?>,.05);
  border:none;color:var(--t2);
  display:flex;align-items:center;justify-content:center;
}

/* Section label — small caps with hairline bottom */
.sec-label,.section-title{
  font-size:.95rem;font-weight:700;color:var(--t);
  padding-bottom:8px;margin-bottom:12px;
  border-bottom:1px solid rgba(var(--pri-rgb),.18);
  letter-spacing:.2px;
}

/* Pill buttons — nominal grid (deposit page) */
.pill-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px}
.pill-btn{
  padding:14px 8px;
  background:rgba(<?=$isLight?'0,0,0':'255,255,255'?>,.04);
  border:1px solid rgba(<?=$isLight?'0,0,0':'255,255,255'?>,.08);
  border-radius:999px;
  font-family:'Poppins',sans-serif;
  font-size:.85rem;font-weight:600;color:var(--t2);
  text-align:center;cursor:pointer;
  transition:all .15s ease;
}
.pill-btn:hover{border-color:rgba(var(--pri-rgb),.4);color:var(--t)}
.pill-btn.active,.pill-btn.on{
  background:linear-gradient(135deg,rgba(var(--pri-rgb),.18),rgba(var(--pri-rgb),.08));
  border-color:rgba(var(--pri-rgb),.5);
  color:var(--pri);
  box-shadow:inset 0 0 0 1px rgba(var(--pri-rgb),.25);
}

/* Big CTA — gold gradient (Deposit/Withdraw button) */
.cta-gold{
  width:100%;padding:14px;
  background:linear-gradient(135deg,var(--pri) 0%,var(--pri-d) 100%);
  background-size:200% auto;
  border:none;border-radius:10px;
  color:#fff;font-family:'Plus Jakarta Sans',sans-serif;
  font-size:1rem;font-weight:700;letter-spacing:.5px;
  cursor:pointer;text-decoration:none;display:flex;align-items:center;justify-content:center;
  transition:background-position .25s ease,box-shadow .15s;
  box-shadow:0 4px 14px rgba(var(--pri-rgb),.3);
}
.cta-gold:hover{background-position:right center;box-shadow:0 6px 20px rgba(var(--pri-rgb),.45)}

/* Promo card — image on top, footer with icon+title+pill button */
.promo-card{
  background:rgba(<?=$isLight?'0,0,0':'255,255,255'?>,.03);
  border:1px solid rgba(<?=$isLight?'0,0,0':'255,255,255'?>,.06);
  border-radius:14px;
  overflow:hidden;
  margin-bottom:12px;
}
.promo-card .pc-img{width:100%;display:block;aspect-ratio:5/2;object-fit:cover}
.promo-card .pc-foot{
  display:flex;align-items:center;gap:10px;
  padding:10px 12px;
}
.promo-card .pc-ic{
  width:34px;height:34px;flex-shrink:0;
  border-radius:8px;
  display:flex;align-items:center;justify-content:center;
}
.promo-card .pc-ic img{width:100%;height:100%;object-fit:contain;border-radius:8px}
.promo-card .pc-title{flex:1;font-size:.86rem;font-weight:600;color:var(--t);letter-spacing:-.005em}
.promo-card .pc-btn{
  padding:7px 14px;
  background:linear-gradient(135deg,var(--pri),var(--pri-d));
  background-size:200% auto;
  color:#fff;
  border-radius:999px;
  font-size:.74rem;font-weight:700;
  text-decoration:none;flex-shrink:0;
  transition:background-position .2s ease;
}
.promo-card .pc-btn:hover{background-position:right center}

/* List menu (profil/saya) — icon + label + chevron */
.list-menu{
  background:rgba(<?=$isLight?'0,0,0':'255,255,255'?>,.03);
  border:1px solid rgba(<?=$isLight?'0,0,0':'255,255,255'?>,.06);
  border-radius:12px;
  overflow:hidden;
}
.list-menu-i{
  display:flex;align-items:center;gap:14px;
  padding:16px 18px;
  color:var(--t);text-decoration:none;
  border-bottom:1px solid rgba(<?=$isLight?'0,0,0':'255,255,255'?>,.05);
  transition:background .15s ease;
}
.list-menu-i:last-child{border-bottom:none}
.list-menu-i:hover{background:rgba(<?=$isLight?'0,0,0':'255,255,255'?>,.04)}
.list-menu-i .lm-ic{
  width:24px;height:24px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
  color:var(--pri);
}
.list-menu-i .lm-ic svg{width:20px;height:20px}
.list-menu-i .lm-label{flex:1;font-size:.92rem;font-weight:500;letter-spacing:-.005em}
.list-menu-i .lm-meta{font-size:.78rem;color:var(--t3);margin-right:8px}
.list-menu-i .lm-chev{color:var(--t3);opacity:.7}

/* VIP progress card */
.vip-card{
  background:linear-gradient(135deg,rgba(var(--pri-rgb),.12),rgba(var(--pri-rgb),.04));
  border:1px solid rgba(var(--pri-rgb),.22);
  border-radius:14px;
  padding:18px;
  display:flex;align-items:center;gap:16px;
  margin-bottom:14px;
}
.vip-card .vc-ic{
  width:60px;height:60px;flex-shrink:0;
  display:flex;align-items:center;justify-content:center;
  background:rgba(var(--pri-rgb),.12);
  border-radius:12px;
}
.vip-card .vc-ic svg{width:42px;height:42px;color:var(--pri)}
.vip-card .vc-body{flex:1;min-width:0}
.vip-card .vc-row{display:flex;align-items:center;justify-content:space-between;margin-bottom:6px}
.vip-card .vc-tier{font-size:.78rem;font-weight:700;color:var(--t2);padding:3px 10px;background:rgba(<?=$isLight?'0,0,0':'255,255,255'?>,.08);border-radius:5px}
.vip-card .vc-target{font-size:.86rem;font-weight:700;color:var(--pri)}
.vip-card .vc-prog{font-size:.78rem;color:var(--t2);margin-bottom:6px}
.vip-card .vc-bar{height:6px;background:rgba(<?=$isLight?'0,0,0':'255,255,255'?>,.06);border-radius:3px;overflow:hidden}
.vip-card .vc-bar-fill{height:100%;background:linear-gradient(90deg,var(--pri),var(--pri-d));border-radius:3px;transition:width .4s ease}

/* Profile header card (saya page) */
.prof-hdr{
  background:rgba(<?=$isLight?'0,0,0':'255,255,255'?>,.03);
  border:1px solid rgba(<?=$isLight?'0,0,0':'255,255,255'?>,.06);
  border-radius:14px;
  padding:18px;
  margin-bottom:14px;
}
.prof-row{display:flex;align-items:center;gap:14px;margin-bottom:14px}
.prof-av{width:64px;height:64px;border-radius:50%;background:var(--bg2);border:2px solid rgba(var(--pri-rgb),.3);overflow:hidden}
.prof-av img{width:100%;height:100%;object-fit:cover}
.prof-info{flex:1;min-width:0}
.prof-info .pi-id{font-size:.78rem;color:var(--t2);font-family:'JetBrains Mono',monospace;margin-bottom:2px}
.prof-info .pi-uname{font-size:.78rem;color:var(--t2);font-family:'JetBrains Mono',monospace;margin-bottom:6px}
.prof-info .pi-bal{display:inline-flex;align-items:center;gap:6px;background:rgba(<?=$isLight?'0,0,0':'255,255,255'?>,.08);padding:5px 11px;border-radius:999px;font-family:'Chakra Petch',monospace;font-size:.86rem;font-weight:700;color:var(--t)}
.prof-actions{display:grid;grid-template-columns:1fr 1fr;gap:10px}
.prof-actions a{
  padding:13px;border-radius:10px;text-align:center;
  font-family:'Poppins',sans-serif;font-size:.86rem;font-weight:700;
  text-decoration:none;display:flex;align-items:center;justify-content:center;gap:8px;
  transition:transform .12s ease;
}
.prof-actions a:active{transform:scale(.97)}
.prof-actions a.pa-wd{background:transparent;border:1.5px solid rgba(var(--pri-rgb),.4);color:var(--t)}
.prof-actions a.pa-dep{background:linear-gradient(135deg,var(--pri),var(--pri-d));color:#fff;border:1.5px solid var(--pri)}

/* Tabs (Agen page) */
.fw-tabs{
  display:flex;
  border-bottom:1px solid rgba(var(--pri-rgb),.3);
  margin-bottom:14px;
  overflow-x:auto;
  scrollbar-width:none;
}
.fw-tabs::-webkit-scrollbar{display:none}
.fw-tab{
  padding:13px 16px;
  font-family:'Poppins',sans-serif;
  font-size:.85rem;font-weight:600;
  color:var(--t3);
  cursor:pointer;
  white-space:nowrap;
  position:relative;
  background:none;border:none;
  letter-spacing:-.005em;
}
.fw-tab.on,.fw-tab.active{
  color:var(--pri);font-weight:700;
  background:linear-gradient(180deg,rgba(var(--pri-rgb),.08),transparent);
}
.fw-tab.on::after,.fw-tab.active::after{
  content:'';position:absolute;
  bottom:-1px;left:8px;right:8px;
  height:2px;background:var(--pri);border-radius:2px;
}

/* Bullet badge for tabs */
.fw-tab .badge,.tab-badge{
  position:absolute;top:6px;right:4px;
  background:#ef4444;color:#fff;
  font-size:.55rem;font-weight:800;
  min-width:16px;height:16px;
  border-radius:8px;
  display:flex;align-items:center;justify-content:center;
  padding:0 4px;
}

/* Section divider with center label (FW66 "Deskripsi Promosi") */
.sec-divider{
  display:flex;align-items:center;gap:14px;
  margin:24px 0 16px;
}
.sec-divider::before,.sec-divider::after{
  content:'';flex:1;height:1px;
  background:linear-gradient(90deg,transparent,rgba(var(--pri-rgb),.3),transparent);
}
.sec-divider span{
  font-family:'Poppins',sans-serif;
  font-size:.85rem;font-weight:700;color:var(--t);
  letter-spacing:.3px;
}

