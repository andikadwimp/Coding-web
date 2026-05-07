<?php
header('Content-Type: text/html; charset=UTF-8');
require_once 'layout.php'; adminHeader('Tampilan & Tema');

$msg=''; $msgType='ok';

// ─── Save handler ───
if($_SERVER['REQUEST_METHOD']==='POST'){
    $st=$db->prepare("INSERT INTO settings(`key`,`value`) VALUES(?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");

    $themeKeys=[
        'theme_primary','theme_primary_d','theme_primary_l',
        'theme_secondary','theme_secondary_d',
        'theme_bg','theme_bg2','theme_bg3',
        'theme_surface','theme_surface2','theme_surface3',
        'theme_nav_bg',
        'theme_text','theme_text2','theme_text3',
        'theme_glass_enabled','theme_glass_blur','theme_glass_opacity',
        'theme_glass_bg_image',
        'theme_bg_mode',
    ];

    foreach($themeKeys as $k){
        if(isset($_POST[$k])){
            $v=trim($_POST[$k]);
            // Sanitize hex colors
            if(strpos($k,'_primary')!==false || strpos($k,'_secondary')!==false ||
               strpos($k,'_bg')!==false || strpos($k,'_surface')!==false ||
               strpos($k,'_nav_bg')!==false || strpos($k,'_text')!==false){
                if($k!=='theme_glass_bg_image' && $k!=='theme_bg_mode' && !preg_match('/^#[0-9a-fA-F]{3,6}$/',$v)){
                    continue; // skip invalid hex
                }
            }
            $st->execute([$k,$v]);
        }
    }
    // Glass enabled checkbox handling (kalau ga di-submit = unchecked = '0')
    if(!isset($_POST['theme_glass_enabled'])){
        $st->execute(['theme_glass_enabled','0']);
    }
    $msg='Tema berhasil disimpan! Refresh halaman frontend (Ctrl+F5) untuk lihat perubahan.';
}

// ─── Load current values ───
$defaults=[
    'theme_primary'    => '#38bdf8',
    'theme_primary_d'  => '#0ea5e9',
    'theme_primary_l'  => '#7dd3fc',
    'theme_secondary'  => '#38bdf8',
    'theme_secondary_d'=> '#0ea5e9',
    'theme_bg'         => '#0f172a',
    'theme_bg2'        => '#1e293b',
    'theme_bg3'        => '#334155',
    'theme_surface'    => '#1e293b',
    'theme_surface2'   => '#334155',
    'theme_surface3'   => '#475569',
    'theme_nav_bg'     => '#0a1628',
    'theme_text'       => '#ffffff',
    'theme_text2'      => '#cbd5e1',
    'theme_text3'      => '#94a3b8',
    'theme_glass_enabled'   => '0',
    'theme_glass_blur'      => '14',
    'theme_glass_opacity'   => '0.55',
    'theme_glass_bg_image'  => '',
    'theme_bg_mode'         => 'cover',
];
$th=$defaults;
try{
    $rows=$db->query("SELECT `key`,`value` FROM settings WHERE `key` LIKE 'theme_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
    foreach($defaults as $k=>$v){if(isset($rows[$k])&&$rows[$k]!=='')$th[$k]=$rows[$k];}
}catch(Exception $e){}
?>

<style>
/* ═══ LAYOUT ═══ */
.theme-wrap{display:grid;grid-template-columns:1fr 400px;gap:24px;align-items:start}
@media(max-width:1180px){.theme-wrap{grid-template-columns:1fr}}

/* ═══ HEADER BANNER ═══ */
.theme-banner{margin-bottom:20px;padding:18px 22px;background:linear-gradient(135deg,var(--pri),var(--pri-d));color:#fff;border-radius:14px;display:flex;align-items:center;gap:16px;box-shadow:0 6px 20px rgba(37,99,235,.25)}
.theme-banner .tb-icon{width:50px;height:50px;background:rgba(255,255,255,.2);border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:24px}
.theme-banner .tb-text{flex:1;min-width:0}
.theme-banner .tb-title{font-size:1.05rem;font-weight:800;margin-bottom:3px;letter-spacing:-.01em}
.theme-banner .tb-sub{font-size:.78rem;opacity:.9;font-weight:500}

/* ═══ TABS ═══ */
.tabs-row{display:flex;gap:4px;margin-bottom:20px;background:var(--bg2);border:1px solid var(--bd);border-radius:12px;padding:4px;box-shadow:var(--shadow-sm)}
.tab-btn{flex:1;padding:11px 14px;background:transparent;border:none;border-radius:9px;font-size:.82rem;font-weight:700;color:var(--t3);cursor:pointer;font-family:inherit;transition:all .2s;display:flex;align-items:center;justify-content:center;gap:6px;letter-spacing:-.01em}
.tab-btn:hover{color:var(--pri);background:var(--pri-l)}
.tab-btn.active{color:#fff;background:var(--pri);box-shadow:0 2px 6px rgba(37,99,235,.3)}
.tab-btn .tab-icon{font-size:1rem}
.tab-section{display:none;animation:fadeUp .3s ease}
.tab-section.active{display:block}
@keyframes fadeUp{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}

/* ═══ PRESET SEARCH ═══ */
.preset-grid{margin-bottom:8px}
.preset-search{position:sticky;top:64px;background:#fff;padding:14px 0 10px;margin-bottom:14px;z-index:5;border-bottom:1px solid var(--bd)}
.preset-search-wrap{position:relative}
.preset-search-wrap svg{position:absolute;left:14px;top:50%;transform:translateY(-50%);width:18px;height:18px;color:var(--t3);pointer-events:none}
.preset-search input{width:100%;padding:11px 14px 11px 42px;font-size:.88rem;border:1.5px solid var(--bd2);border-radius:10px;outline:none;transition:all .15s;background:var(--bg)}
.preset-search input:focus{border-color:var(--pri);background:#fff;box-shadow:0 0 0 3px var(--pri-l)}
.preset-search-info{font-size:.7rem;color:var(--t3);margin-top:8px;font-weight:600;display:flex;align-items:center;gap:4px}

/* ═══ PRESET GROUP HEADERS ═══ */
.preset-group-title{font-size:.95rem;font-weight:800;color:var(--t);margin:22px 0 12px;letter-spacing:-.01em;display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:8px 14px;border-left:3px solid var(--bd);border-radius:0 8px 8px 0;background:var(--bg2);transition:border-color .2s}
.preset-group-title:first-child{margin-top:0}
.preset-group-title[data-family="red"]{border-left-color:#ef4444}
.preset-group-title[data-family="blue"]{border-left-color:#3b82f6}
.preset-group-title[data-family="purple"]{border-left-color:#a855f7}
.preset-group-title[data-family="green"]{border-left-color:#22c55e}
.preset-group-title .gt-emoji{font-size:1.15rem}
.preset-group-title .gt-count{font-size:.6rem;font-weight:800;color:#fff;background:var(--pri);padding:3px 9px;border-radius:10px;letter-spacing:.3px}
.preset-group-title .gt-desc{font-size:.7rem;font-weight:500;color:var(--t3);font-style:italic;margin-left:auto}
@media(max-width:520px){
  .preset-group-title{font-size:.85rem;gap:8px;padding:6px 10px}
  .preset-group-title .gt-desc{display:none}
}

/* ═══ PRESET ROW (grid of cards) ═══ */
.preset-row{display:grid;grid-template-columns:repeat(auto-fill,minmax(165px,1fr));gap:12px;margin-bottom:8px}

/* ═══ PRESET CARD with MINI MOCKUP PREVIEW ═══ */
.preset-card{
  background:#fff;
  border:2px solid var(--bd);
  border-radius:12px;
  padding:0;
  cursor:pointer;
  transition:all .2s cubic-bezier(.4,0,.2,1);
  position:relative;
  overflow:hidden;
}
.preset-card:hover{
  border-color:var(--pri);
  box-shadow:0 8px 24px rgba(15,23,42,.08);
  transform:translateY(-2px);
}
.preset-card.active{
  border-color:var(--pri);
  box-shadow:0 0 0 3px var(--pri-l),0 6px 18px rgba(37,99,235,.12);
}
.preset-card.active::after{
  content:'✓';
  position:absolute;top:6px;right:6px;
  width:22px;height:22px;
  background:var(--pri);color:#fff;
  border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-size:.78rem;font-weight:800;
  box-shadow:0 2px 6px rgba(37,99,235,.4);
  z-index:2;
}
.preset-card.hidden{display:none}

/* Mini mockup */
.pp-mock{
  height:110px;
  position:relative;
  overflow:hidden;
  border-bottom:1px solid var(--bd);
  display:flex;
  flex-direction:column;
  padding:10px;
  gap:8px;
}
.pp-bar{
  height:18px;
  border-radius:5px;
  display:flex;align-items:center;
  padding:0 6px;gap:5px;
  flex-shrink:0;
}
.pp-bar-dot{width:7px;height:7px;border-radius:50%;flex-shrink:0}
.pp-bar-line{flex:1;height:3px;border-radius:2px;opacity:.5}
.pp-content{
  flex:1;
  border-radius:6px;
  padding:8px;
  display:flex;
  flex-direction:column;
  gap:5px;
  position:relative;
  overflow:hidden;
}
.pp-h{height:6px;border-radius:2px;width:60%}
.pp-sub{height:4px;border-radius:2px;width:80%;opacity:.55}
.pp-btn{
  height:14px;
  border-radius:4px;
  width:55%;
  margin-top:auto;
  display:flex;align-items:center;justify-content:center;
}
.pp-btn::before{
  content:'';width:30%;height:3px;background:rgba(255,255,255,.7);border-radius:2px;
}

/* Theme name footer */
.preset-name-row{
  padding:8px 10px;
  display:flex;align-items:center;justify-content:space-between;gap:6px;
  background:#fff;
}
.preset-name{
  font-size:.74rem;font-weight:700;color:var(--t);
  white-space:nowrap;overflow:hidden;text-overflow:ellipsis;
  flex:1;min-width:0;
}
.preset-tag{
  font-size:.55rem;font-weight:800;
  padding:2px 6px;border-radius:5px;
  text-transform:uppercase;letter-spacing:.5px;
  flex-shrink:0;
}
.preset-tag.light{background:#fef3c7;color:#92400e}
.preset-tag.dark{background:#1e293b;color:#cbd5e1}

/* ═══ COLOR PICKER ROWS ═══ */
.cp-section-title{font-size:.85rem;font-weight:800;color:var(--t);margin:0 0 12px;display:flex;align-items:center;gap:8px}
.cp-section-title::before{content:'';width:3px;height:14px;background:var(--pri);border-radius:2px}
.cp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:10px}
.cp-row{
  display:flex;align-items:center;gap:10px;
  padding:11px 12px;
  background:#fff;
  border:1.5px solid var(--bd);
  border-radius:10px;
  transition:border-color .15s;
}
.cp-row:hover{border-color:var(--pri-l)}
.cp-row:focus-within{border-color:var(--pri);box-shadow:0 0 0 2px var(--pri-l)}
.cp-info{flex:1;min-width:0}
.cp-label{font-size:.78rem;font-weight:700;color:var(--t);letter-spacing:-.01em}
.cp-desc{font-size:.65rem;color:var(--t3);margin-top:2px;font-weight:500}
.cp-row input[type=color]{
  width:42px;height:38px;
  padding:2px;border:1.5px solid var(--bd);
  border-radius:8px;cursor:pointer;background:#fff;flex-shrink:0;
}
.cp-row input[type=text]{
  width:90px;
  font-family:'JetBrains Mono',monospace;
  font-size:.72rem;font-weight:600;
  padding:8px 9px;
  text-transform:uppercase;
  border:1.5px solid var(--bd);
  border-radius:7px;
  outline:none;
  transition:border-color .15s;
}
.cp-row input[type=text]:focus{border-color:var(--pri)}

/* ═══ GLASS TOGGLE & SLIDERS ═══ */
.glass-toggle{
  display:flex;align-items:center;gap:14px;
  padding:18px 20px;
  background:linear-gradient(135deg,#dbeafe,#e0f2fe);
  border:1.5px solid #bfdbfe;
  border-radius:12px;
  margin-bottom:16px;
  transition:all .2s;
}
.glass-toggle:has(input:checked){
  background:linear-gradient(135deg,var(--pri),var(--pri-d));
  border-color:var(--pri);
  color:#fff;
}
.glass-toggle:has(input:checked) .gt-desc{color:rgba(255,255,255,.85)}
.glass-toggle label{font-size:.92rem;font-weight:800;color:var(--t);margin:0;cursor:pointer}
.glass-toggle:has(input:checked) label{color:#fff}
.glass-toggle input[type=checkbox]{
  width:46px;height:26px;cursor:pointer;
  appearance:none;-webkit-appearance:none;
  background:#cbd5e1;border-radius:13px;position:relative;
  transition:background .2s;flex-shrink:0;margin:0;
}
.glass-toggle input[type=checkbox]::after{
  content:'';position:absolute;top:3px;left:3px;
  width:20px;height:20px;background:#fff;border-radius:50%;
  transition:transform .2s;box-shadow:0 1px 3px rgba(0,0,0,.2);
}
.glass-toggle input[type=checkbox]:checked{background:#fff}
.glass-toggle input[type=checkbox]:checked::after{transform:translateX(20px);background:var(--pri)}
.glass-toggle .gt-info{flex:1}
.glass-toggle .gt-desc{font-size:.72rem;color:var(--t2);margin-top:3px;font-weight:500}

.slider-row{
  display:flex;align-items:center;gap:14px;
  padding:13px 16px;
  background:#fff;
  border:1.5px solid var(--bd);
  border-radius:10px;
  margin-bottom:10px;
}
.slider-row label{flex:0 0 130px;margin:0;font-size:.78rem;font-weight:700;color:var(--t)}
.slider-row input[type=range]{
  flex:1;cursor:pointer;height:6px;
  appearance:none;-webkit-appearance:none;
  background:linear-gradient(to right,var(--pri) 0%,var(--pri) 50%,var(--bd) 50%,var(--bd) 100%);
  border-radius:3px;outline:none;
}
.slider-row input[type=range]::-webkit-slider-thumb{
  appearance:none;-webkit-appearance:none;
  width:18px;height:18px;background:var(--pri);
  border:3px solid #fff;border-radius:50%;cursor:pointer;
  box-shadow:0 2px 6px rgba(37,99,235,.4);
}
.slider-row input[type=range]::-moz-range-thumb{
  width:18px;height:18px;background:var(--pri);
  border:3px solid #fff;border-radius:50%;cursor:pointer;
  box-shadow:0 2px 6px rgba(37,99,235,.4);
}
.slider-row .sv{
  font-family:'JetBrains Mono',monospace;
  font-size:.78rem;font-weight:800;
  color:var(--pri);
  min-width:55px;text-align:right;
  background:var(--pri-l);
  padding:5px 10px;border-radius:6px;
}

/* ═══ LIVE PREVIEW PANEL ═══ */
.preview-box{
  position:sticky;top:80px;
  background:#fff;
  border:1px solid var(--bd);
  border-radius:14px;
  overflow:hidden;
  box-shadow:0 12px 36px rgba(15,23,42,.08);
}
.preview-hdr{
  padding:14px 18px;
  background:linear-gradient(135deg,var(--pri),var(--pri-d));
  color:#fff;font-size:.82rem;font-weight:800;
  display:flex;align-items:center;gap:8px;
  letter-spacing:-.01em;
}
.preview-hdr svg{width:16px;height:16px}
.preview-frame{width:100%;height:620px;border:none;display:block;background:#0f172a}
.preview-actions{
  padding:12px 14px;
  background:#fafafa;
  border-top:1px solid var(--bd);
  display:flex;gap:8px;align-items:center;
}
.preview-actions select.url-pick{
  flex:1;font-size:.74rem;font-weight:600;
  padding:8px 12px;
  border:1.5px solid var(--bd);border-radius:8px;
  background:#fff;color:var(--t);
  cursor:pointer;outline:none;
}
.preview-actions select.url-pick:focus{border-color:var(--pri)}
.preview-actions button.btn-refresh{
  padding:8px 14px;
  background:var(--pri-l);
  border:1px solid rgba(37,99,235,.2);
  border-radius:8px;
  color:var(--pri);
  font-weight:700;font-size:.74rem;
  cursor:pointer;
  transition:all .15s;
  display:flex;align-items:center;gap:5px;
}
.preview-actions button.btn-refresh:hover{background:#bfdbfe}
.preview-actions button.btn-refresh svg{width:14px;height:14px;transition:transform .3s}
.preview-actions button.btn-refresh:hover svg{transform:rotate(180deg)}

/* ═══ SAVE BAR (floating, prominent) ═══ */
.save-bar{
  position:sticky;bottom:14px;
  margin-top:24px;
  background:#fff;
  border:1.5px solid var(--bd);
  border-radius:14px;
  padding:14px 18px;
  display:flex;gap:10px;align-items:center;
  box-shadow:0 12px 36px rgba(15,23,42,.12);
  z-index:10;
}
.save-bar .save-info{
  flex:1;font-size:.78rem;color:var(--t2);
  display:flex;align-items:center;gap:8px;
}
.save-bar .save-info svg{width:16px;height:16px;color:var(--orange);flex-shrink:0}
.save-bar button{font-size:.86rem;padding:11px 22px;letter-spacing:.2px}

.reset-btn{
  padding:9px 14px;font-size:.74rem;
  background:transparent;
  border:1.5px solid var(--bd2);
  border-radius:8px;
  color:var(--t2);cursor:pointer;
  font-family:inherit;font-weight:700;
  transition:all .15s;
  display:flex;align-items:center;gap:5px;
}
.reset-btn:hover{background:#fee2e2;border-color:#fecaca;color:var(--red)}

/* ═══ UPLOAD BG IMAGE ═══ */
.upload-bg{display:flex;gap:8px;align-items:center}
.upload-bg input[type=text]{flex:1;padding:10px 12px;border:1.5px solid var(--bd);border-radius:8px;font-size:.78rem}
.upload-bg-btn{
  padding:10px 14px;
  background:var(--pri-l);color:var(--pri);
  border:1px solid rgba(37,99,235,.3);
  border-radius:8px;cursor:pointer;
  font-family:inherit;font-size:.74rem;font-weight:700;
  white-space:nowrap;display:flex;align-items:center;gap:5px;
  transition:background .15s;
}
.upload-bg-btn:hover{background:#bfdbfe}
.upload-bg-preview{
  margin-top:10px;height:90px;
  background:var(--bg);
  border:1.5px dashed var(--bd2);
  border-radius:10px;
  background-size:cover;background-position:center;
  display:flex;align-items:center;justify-content:center;
  font-size:.72rem;color:var(--t3);font-weight:600;
}

/* ═══ TOAST / MESSAGE ═══ */
.theme-msg{
  padding:14px 18px;
  border-radius:12px;
  margin-bottom:18px;
  font-size:.85rem;font-weight:600;
  display:flex;align-items:center;gap:10px;
  animation:slideDown .35s ease;
}
.theme-msg.ok{background:rgba(22,163,74,.08);color:var(--green);border:1.5px solid rgba(22,163,74,.25)}
.theme-msg svg{width:20px;height:20px;flex-shrink:0}
@keyframes slideDown{from{opacity:0;transform:translateY(-12px)}to{opacity:1;transform:translateY(0)}}

/* Sliders for preset row scrollbar  */
.preset-grid::-webkit-scrollbar{width:6px}
.preset-grid::-webkit-scrollbar-thumb{background:var(--bd2);border-radius:3px}

.bg-mode-opt{display:flex;flex-direction:column;align-items:center;gap:4px;padding:10px 6px;background:var(--bg);border:1.5px solid var(--bd);border-radius:8px;cursor:pointer;text-align:center;transition:border-color .15s,background .15s}
.bg-mode-opt:hover{border-color:var(--t3)}
.bg-mode-opt.on{border-color:var(--accent);background:var(--accent-l)}
.bg-mode-opt .bm-ic{color:var(--t3);transition:color .15s}
.bg-mode-opt.on .bm-ic{color:var(--accent)}
.bg-mode-opt b{font-size:.74rem;font-weight:700;color:var(--t);letter-spacing:-.005em}
.bg-mode-opt span{font-size:.62rem;color:var(--t3);font-weight:500;line-height:1.3}
</style>

<!-- BANNER -->
<div class="theme-banner">
  <div class="tb-icon">🎨</div>
  <div class="tb-text">
    <div class="tb-title">Tampilan & Tema</div>
    <div class="tb-sub">Customize warna situs, glass effect, &amp; preview real-time. 100+ tema siap pakai.</div>
  </div>
</div>

<?php if($msg): ?>
<div class="theme-msg ok">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
  <span><?=htmlspecialchars($msg)?></span>
</div>
<?php endif; ?>

<form method="POST" id="themeForm">
<div class="theme-wrap">

  <!-- LEFT: Settings -->
  <div>
    <div class="tabs-row">
      <button type="button" class="tab-btn active" data-tab="preset"><span class="tab-icon">🎨</span> Preset Tema</button>
      <button type="button" class="tab-btn" data-tab="custom"><span class="tab-icon">🎯</span> Custom Warna</button>
      <button type="button" class="tab-btn" data-tab="glass"><span class="tab-icon">✨</span> Efek Glass</button>
    </div>

    <!-- ─── TAB: PRESET ─── -->
    <div class="tab-section active" id="tab-preset">
      <div class="card">
        <h2>Pilih Tema Preset</h2>
        <p style="font-size:.78rem;color:var(--t2);margin-bottom:14px">100+ tema siap pakai. Klik untuk apply, lalu bisa di-customize lebih lanjut di tab "Custom Warna".</p>
        <div class="preset-search">
          <div class="preset-search-wrap">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="presetSearch" placeholder="Cari tema… coba: blue, dark, mint, gold, light, ruby" oninput="filterPresets()">
          </div>
          <div class="preset-search-info" id="presetSearchInfo"></div>
        </div>
        <div class="preset-grid" id="presetGrid"></div>
      </div>
    </div>

    <!-- ─── TAB: CUSTOM ─── -->
    <div class="tab-section" id="tab-custom">
      <div class="card">
        <div class="cp-section-title">Warna Utama (Aksen)</div>
        <div class="cp-grid">
          <?php
          $colorFields=[
              ['theme_primary','Primary','Tombol utama, link aktif, aksen'],
              ['theme_primary_d','Primary Dark','Hover state primary'],
              ['theme_primary_l','Primary Light','Highlight terang / shimmer'],
              ['theme_secondary','Secondary','Saldo, aksen kedua'],
              ['theme_secondary_d','Secondary Dark','Hover secondary'],
          ];
          foreach($colorFields as $f):
              [$k,$lbl,$desc]=$f;
          ?>
          <div class="cp-row">
            <input type="color" name="<?=$k?>" value="<?=htmlspecialchars($th[$k])?>" data-target="<?=$k?>_text" oninput="syncColor(this)">
            <div class="cp-info">
              <div class="cp-label"><?=$lbl?></div>
              <div class="cp-desc"><?=$desc?></div>
            </div>
            <input type="text" id="<?=$k?>_text" value="<?=htmlspecialchars($th[$k])?>" oninput="syncText(this,'<?=$k?>')">
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card">
        <div class="cp-section-title">Background</div>
        <div class="cp-grid">
          <?php
          $bgFields=[
              ['theme_bg','Background Utama','Layar paling belakang'],
              ['theme_bg2','Background Card','Card secondary'],
              ['theme_bg3','Background Hover','Hover/active state'],
              ['theme_nav_bg','Bottom Nav','Nav bawah mobile'],
          ];
          foreach($bgFields as $f):
              [$k,$lbl,$desc]=$f;
          ?>
          <div class="cp-row">
            <input type="color" name="<?=$k?>" value="<?=htmlspecialchars($th[$k])?>" data-target="<?=$k?>_text" oninput="syncColor(this)">
            <div class="cp-info">
              <div class="cp-label"><?=$lbl?></div>
              <div class="cp-desc"><?=$desc?></div>
            </div>
            <input type="text" id="<?=$k?>_text" value="<?=htmlspecialchars($th[$k])?>" oninput="syncText(this,'<?=$k?>')">
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card">
        <div class="cp-section-title">Surface (Card & Modal)</div>
        <div class="cp-grid">
          <?php
          $sFields=[
              ['theme_surface','Surface Utama','Card, modal, popup'],
              ['theme_surface2','Surface Hover','Hover surface'],
              ['theme_surface3','Surface Elevated','Modal layer atas'],
          ];
          foreach($sFields as $f):
              [$k,$lbl,$desc]=$f;
          ?>
          <div class="cp-row">
            <input type="color" name="<?=$k?>" value="<?=htmlspecialchars($th[$k])?>" data-target="<?=$k?>_text" oninput="syncColor(this)">
            <div class="cp-info">
              <div class="cp-label"><?=$lbl?></div>
              <div class="cp-desc"><?=$desc?></div>
            </div>
            <input type="text" id="<?=$k?>_text" value="<?=htmlspecialchars($th[$k])?>" oninput="syncText(this,'<?=$k?>')">
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="card">
        <div class="cp-section-title">Teks</div>
        <div class="cp-grid">
          <?php
          $tFields=[
              ['theme_text','Teks Utama','Heading, body utama'],
              ['theme_text2','Teks Sekunder','Subteks, label'],
              ['theme_text3','Teks Hint','Placeholder, muted'],
          ];
          foreach($tFields as $f):
              [$k,$lbl,$desc]=$f;
          ?>
          <div class="cp-row">
            <input type="color" name="<?=$k?>" value="<?=htmlspecialchars($th[$k])?>" data-target="<?=$k?>_text" oninput="syncColor(this)">
            <div class="cp-info">
              <div class="cp-label"><?=$lbl?></div>
              <div class="cp-desc"><?=$desc?></div>
            </div>
            <input type="text" id="<?=$k?>_text" value="<?=htmlspecialchars($th[$k])?>" oninput="syncText(this,'<?=$k?>')">
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- ─── TAB: GLASS ─── -->
    <div class="tab-section" id="tab-glass">
      <div class="card">
        <h2>Efek Glass / Background Transparan Blur</h2>
        <p style="font-size:.78rem;color:var(--t2);margin-bottom:14px">Bikin card & nav semi-transparan dengan efek blur (glassmorphism). Tetap ngikut warna tema.</p>

        <div class="glass-toggle">
          <input type="checkbox" name="theme_glass_enabled" id="glassToggle" value="1" <?=$th['theme_glass_enabled']==='1'?'checked':''?>>
          <div class="gt-info">
            <label for="glassToggle">Aktifkan Glass Effect</label>
            <div class="gt-desc">Card, nav, modal jadi transparan dengan blur. Tampak premium di belakang background image.</div>
          </div>
        </div>

        <div class="slider-row">
          <label for="glassBlur">Intensitas Blur</label>
          <input type="range" name="theme_glass_blur" id="glassBlur" min="0" max="40" step="1" value="<?=intval($th['theme_glass_blur'])?>" oninput="document.getElementById('glassBlurVal').textContent=this.value+'px'">
          <span class="sv" id="glassBlurVal"><?=intval($th['theme_glass_blur'])?>px</span>
        </div>

        <div class="slider-row">
          <label for="glassOpacity">Opacity Surface</label>
          <input type="range" name="theme_glass_opacity" id="glassOpacity" min="10" max="95" step="5" value="<?=intval(floatval($th['theme_glass_opacity'])*100)?>" oninput="this.nextElementSibling.textContent=(this.value/100).toFixed(2)" data-real-value>
          <span class="sv"><?=number_format(floatval($th['theme_glass_opacity']),2)?></span>
        </div>
        <input type="hidden" name="theme_glass_opacity" id="glassOpacityHidden" value="<?=$th['theme_glass_opacity']?>">

        <div style="margin-top:18px">
          <h3 style="font-size:.85rem;font-weight:800;color:var(--t);margin-bottom:8px">Background Site (Wajik / Texture / Foto)</h3>
          <p style="font-size:.7rem;color:var(--t3);margin-bottom:10px">Upload gambar (PNG/JPG) untuk dipake sebagai background <b>seluruh halaman</b> situs. Cocok buat pattern wajik, texture noise, atau foto. Pilih mode di bawah.</p>
          <div class="upload-bg">
            <input type="text" name="theme_glass_bg_image" id="bgImageUrl" value="<?=htmlspecialchars($th['theme_glass_bg_image'])?>" placeholder="https://example.com/bg.jpg atau /asset/uploads/bg.jpg" oninput="updateBgPreview()">
            <button type="button" class="upload-bg-btn" onclick="document.getElementById('bgFileInput').click()">📁 Upload</button>
            <input type="file" id="bgFileInput" accept="image/*" style="display:none" onchange="uploadBg(this)">
          </div>
          <div style="margin-top:12px">
            <label style="font-size:.72rem;font-weight:700;color:var(--t);display:block;margin-bottom:6px">Mode Tampilan</label>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:6px">
              <?php $bgMode=$th['theme_bg_mode']??'cover'; ?>
              <label class="bg-mode-opt<?=$bgMode==='tile'?' on':''?>"><input type="radio" name="theme_bg_mode" value="tile"<?=$bgMode==='tile'?' checked':''?> style="display:none"><div class="bm-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="20" height="20"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg></div><b>Tile / Repeat</b><span>Buat pattern wajik / texture</span></label>
              <label class="bg-mode-opt<?=$bgMode==='cover'?' on':''?>"><input type="radio" name="theme_bg_mode" value="cover"<?=$bgMode==='cover'?' checked':''?> style="display:none"><div class="bm-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="20" height="20"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 12h18"/></svg></div><b>Cover</b><span>Penuhin layar (foto)</span></label>
              <label class="bg-mode-opt<?=$bgMode==='contain'?' on':''?>"><input type="radio" name="theme_bg_mode" value="contain"<?=$bgMode==='contain'?' checked':''?> style="display:none"><div class="bm-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="20" height="20"><rect x="3" y="3" width="18" height="18" rx="2"/><rect x="7" y="7" width="10" height="10" rx="1"/></svg></div><b>Contain</b><span>Tampil utuh, no crop</span></label>
            </div>
          </div>
          <div class="upload-bg-preview" id="bgPreview" style="background-image:<?=$th['theme_glass_bg_image']?'url('.htmlspecialchars($th['theme_glass_bg_image']).')':'none'?>;background-size:<?=$bgMode==='tile'?'80px 80px':$bgMode?>;background-repeat:<?=$bgMode==='tile'?'repeat':'no-repeat'?>;background-position:center"><?=$th['theme_glass_bg_image']?'':'Belum ada background image'?></div>
        </div>
      </div>
    </div>

    <!-- Hidden form fields untuk save -->
    <div class="save-bar">
      <div class="save-info">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        Setelah simpan, refresh frontend (Ctrl+F5) buat lihat perubahan
      </div>
      <button type="button" class="reset-btn" onclick="if(confirm('Reset ke default Sky Blue Dark?'))resetToDefault()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
        Reset Default
      </button>
      <button type="submit" class="btn btn-pri">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="15" height="15"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        Simpan Tema
      </button>
    </div>
  </div>

  <!-- RIGHT: Live preview -->
  <div>
    <div class="preview-box">
      <div class="preview-hdr">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M2 3h20a2 2 0 012 2v11a2 2 0 01-2 2h-7l3 3v1H8v-1l3-3H2a2 2 0 01-2-2V5a2 2 0 012-2z"/><circle cx="12" cy="10" r="2.5" fill="currentColor"/></svg>
        Live Preview · Real-time
      </div>
      <iframe class="preview-frame" id="previewFrame" src="../dashboard.php?preview=1"></iframe>
      <div class="preview-actions">
        <select class="url-pick" id="previewUrl" onchange="document.getElementById('previewFrame').src=this.value">
          <option value="../dashboard.php?preview=1">Dashboard</option>
          <option value="../deposit.php?preview=1">Deposit</option>
          <option value="../games.php?preview=1">Games</option>
          <option value="../profil.php?preview=1">Profil</option>
          <option value="../promo.php?preview=1">Promo</option>
        </select>
        <button type="button" class="btn-refresh" onclick="document.getElementById('previewFrame').src=document.getElementById('previewFrame').src">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 4 23 10 17 10"/><path d="M3.51 15a9 9 0 102.13-9.36L1 10"/></svg>
          Refresh
        </button>
      </div>
    </div>
  </div>

</div>
</form>

<script>
// ═══════════════ PRESET THEMES ═══════════════
var PRESETS = [
  {id:'crimson_royale_dark',family:'red',name:'Crimson Royale 🌙',colors:{theme_primary:'#ef4444',theme_primary_d:'#dc2626',theme_primary_l:'#fca5a5',theme_secondary:'#ef4444',theme_secondary_d:'#dc2626',theme_bg:'#170a0d',theme_bg2:'#2a1417',theme_bg3:'#3d1d22',theme_surface:'#2a1417',theme_surface2:'#3d1d22',theme_surface3:'#4f262d',theme_nav_bg:'#0d0608',theme_text:'#ffffff',theme_text2:'#d4b5b8',theme_text3:'#9c7a80'}},
  {id:'rose_garden_light',family:'red',name:'Rose Garden ☀️',colors:{theme_primary:'#e11d48',theme_primary_d:'#be123c',theme_primary_l:'#fda4af',theme_secondary:'#e11d48',theme_secondary_d:'#be123c',theme_bg:'#fdf2f6',theme_bg2:'#ffffff',theme_bg3:'#f7e1e8',theme_surface:'#ffffff',theme_surface2:'#fef0f4',theme_surface3:'#f7e1e8',theme_nav_bg:'#ffffff',theme_text:'#1f0a10',theme_text2:'#6b3344',theme_text3:'#a07b85'}},
  {id:'wine_cellar_dark',family:'red',name:'Wine Cellar 🌙',colors:{theme_primary:'#f43f5e',theme_primary_d:'#e11d48',theme_primary_l:'#fda4af',theme_secondary:'#f43f5e',theme_secondary_d:'#e11d48',theme_bg:'#1c0a12',theme_bg2:'#2e1320',theme_bg3:'#421b2c',theme_surface:'#2e1320',theme_surface2:'#421b2c',theme_surface3:'#552537',theme_nav_bg:'#100509',theme_text:'#ffffff',theme_text2:'#d4b5c2',theme_text3:'#9c7a8a'}},
  {id:'sakura_petal_light',family:'red',name:'Sakura Petal ☀️',colors:{theme_primary:'#ec4899',theme_primary_d:'#db2777',theme_primary_l:'#f9a8d4',theme_secondary:'#ec4899',theme_secondary_d:'#db2777',theme_bg:'#fdf4f8',theme_bg2:'#ffffff',theme_bg3:'#f9e1ec',theme_surface:'#ffffff',theme_surface2:'#fef0f7',theme_surface3:'#f9e1ec',theme_nav_bg:'#ffffff',theme_text:'#1f0a17',theme_text2:'#6b3354',theme_text3:'#a07b8f'}},
  {id:'arctic_ice_light',family:'blue',name:'Arctic Ice ☀️',colors:{theme_primary:'#0284c7',theme_primary_d:'#0369a1',theme_primary_l:'#7dd3fc',theme_secondary:'#0284c7',theme_secondary_d:'#0369a1',theme_bg:'#f0f9ff',theme_bg2:'#ffffff',theme_bg3:'#e0f2fe',theme_surface:'#ffffff',theme_surface2:'#ecf6fc',theme_surface3:'#d6ebf8',theme_nav_bg:'#ffffff',theme_text:'#0a1929',theme_text2:'#3d5670',theme_text3:'#6b8194'}},
  {id:'ocean_depth_dark',family:'blue',name:'Ocean Depth 🌙',colors:{theme_primary:'#0ea5e9',theme_primary_d:'#0284c7',theme_primary_l:'#7dd3fc',theme_secondary:'#0ea5e9',theme_secondary_d:'#0284c7',theme_bg:'#0a1628',theme_bg2:'#14233f',theme_bg3:'#1d3057',theme_surface:'#14233f',theme_surface2:'#1d3057',theme_surface3:'#263d6e',theme_nav_bg:'#050d1a',theme_text:'#ffffff',theme_text2:'#b5c5d4',theme_text3:'#7a8ea3'}},
  {id:'glacier_frost_light',family:'blue',name:'Glacier Frost ☀️',colors:{theme_primary:'#0891b2',theme_primary_d:'#0e7490',theme_primary_l:'#67e8f9',theme_secondary:'#0891b2',theme_secondary_d:'#0e7490',theme_bg:'#ecfeff',theme_bg2:'#ffffff',theme_bg3:'#cffafe',theme_surface:'#ffffff',theme_surface2:'#e6fafd',theme_surface3:'#c1f2f7',theme_nav_bg:'#ffffff',theme_text:'#08222a',theme_text2:'#325a66',theme_text3:'#5e7e88'}},
  {id:'midnight_aurora_dark',family:'blue',name:'Midnight Aurora 🌙',colors:{theme_primary:'#3b82f6',theme_primary_d:'#2563eb',theme_primary_l:'#93c5fd',theme_secondary:'#3b82f6',theme_secondary_d:'#2563eb',theme_bg:'#0c1322',theme_bg2:'#161f37',theme_bg3:'#1f2c4d',theme_surface:'#161f37',theme_surface2:'#1f2c4d',theme_surface3:'#283964',theme_nav_bg:'#060a14',theme_text:'#ffffff',theme_text2:'#b8c4d9',theme_text3:'#7d8aa3'}},
  {id:'royal_amethyst_dark',family:'purple',name:'Royal Amethyst 🌙',colors:{theme_primary:'#a855f7',theme_primary_d:'#9333ea',theme_primary_l:'#d8b4fe',theme_secondary:'#a855f7',theme_secondary_d:'#9333ea',theme_bg:'#1a0d1f',theme_bg2:'#2a1635',theme_bg3:'#3d2049',theme_surface:'#2a1635',theme_surface2:'#3d2049',theme_surface3:'#4f2a5d',theme_nav_bg:'#0e0612',theme_text:'#ffffff',theme_text2:'#c8b5d1',theme_text3:'#957aa3'}},
  {id:'lavender_dream_light',family:'purple',name:'Lavender Dream ☀️',colors:{theme_primary:'#9333ea',theme_primary_d:'#7e22ce',theme_primary_l:'#d8b4fe',theme_secondary:'#9333ea',theme_secondary_d:'#7e22ce',theme_bg:'#faf5ff',theme_bg2:'#ffffff',theme_bg3:'#f3e8ff',theme_surface:'#ffffff',theme_surface2:'#f7f0fc',theme_surface3:'#ebdcf7',theme_nav_bg:'#ffffff',theme_text:'#1a0a22',theme_text2:'#503370',theme_text3:'#7e6a94'}},
  {id:'deep_plum_dark',family:'purple',name:'Deep Plum 🌙',colors:{theme_primary:'#7c3aed',theme_primary_d:'#6d28d9',theme_primary_l:'#c4b5fd',theme_secondary:'#7c3aed',theme_secondary_d:'#6d28d9',theme_bg:'#150a1f',theme_bg2:'#241432',theme_bg3:'#341e48',theme_surface:'#241432',theme_surface2:'#341e48',theme_surface3:'#44285c',theme_nav_bg:'#0a0512',theme_text:'#ffffff',theme_text2:'#c4b5d4',theme_text3:'#8e7da3'}},
  {id:'iris_bloom_light',family:'purple',name:'Iris Bloom ☀️',colors:{theme_primary:'#8b5cf6',theme_primary_d:'#7c3aed',theme_primary_l:'#c4b5fd',theme_secondary:'#8b5cf6',theme_secondary_d:'#7c3aed',theme_bg:'#f5f3ff',theme_bg2:'#ffffff',theme_bg3:'#ede9fe',theme_surface:'#ffffff',theme_surface2:'#f6f4fc',theme_surface3:'#e3def7',theme_nav_bg:'#ffffff',theme_text:'#170a22',theme_text2:'#463370',theme_text3:'#7a6a94'}},
  {id:'emerald_forest_dark',family:'green',name:'Emerald Forest 🌙',colors:{theme_primary:'#10b981',theme_primary_d:'#059669',theme_primary_l:'#6ee7b7',theme_secondary:'#10b981',theme_secondary_d:'#059669',theme_bg:'#0a1f17',theme_bg2:'#143526',theme_bg3:'#1d4938',theme_surface:'#143526',theme_surface2:'#1d4938',theme_surface3:'#265d4a',theme_nav_bg:'#051208',theme_text:'#ffffff',theme_text2:'#b5d4c4',theme_text3:'#7aa38e'}},
  {id:'mint_fresh_light',family:'green',name:'Mint Fresh ☀️',colors:{theme_primary:'#059669',theme_primary_d:'#047857',theme_primary_l:'#6ee7b7',theme_secondary:'#059669',theme_secondary_d:'#047857',theme_bg:'#ecfdf5',theme_bg2:'#ffffff',theme_bg3:'#d1fae5',theme_surface:'#ffffff',theme_surface2:'#e6fbf0',theme_surface3:'#c0f2dd',theme_nav_bg:'#ffffff',theme_text:'#062a1f',theme_text2:'#2c5e4a',theme_text3:'#5a8675'}},
  {id:'jade_royale_dark',family:'green',name:'Jade Royale 🌙',colors:{theme_primary:'#14b8a6',theme_primary_d:'#0d9488',theme_primary_l:'#5eead4',theme_secondary:'#14b8a6',theme_secondary_d:'#0d9488',theme_bg:'#0a1a1f',theme_bg2:'#142d35',theme_bg3:'#1d404b',theme_surface:'#142d35',theme_surface2:'#1d404b',theme_surface3:'#265360',theme_nav_bg:'#050f12',theme_text:'#ffffff',theme_text2:'#b5d0d4',theme_text3:'#7a9aa3'}},
  {id:'sage_garden_light',family:'green',name:'Sage Garden ☀️',colors:{theme_primary:'#16a34a',theme_primary_d:'#15803d',theme_primary_l:'#86efac',theme_secondary:'#16a34a',theme_secondary_d:'#15803d',theme_bg:'#f0fdf4',theme_bg2:'#ffffff',theme_bg3:'#dcfce7',theme_surface:'#ffffff',theme_surface2:'#e8f7ec',theme_surface3:'#cef2d7',theme_nav_bg:'#ffffff',theme_text:'#052a17',theme_text2:'#2c5e3e',theme_text3:'#5a8669'}},
];

// Detect light theme from background luminance
function isLightTheme(p){
  var bg=p.colors.theme_bg.replace('#','');
  if(bg.length===3)bg=bg[0]+bg[0]+bg[1]+bg[1]+bg[2]+bg[2];
  var r=parseInt(bg.substr(0,2),16),g=parseInt(bg.substr(2,2),16),b=parseInt(bg.substr(4,2),16);
  var lum=(0.299*r+0.587*g+0.114*b)/255;
  return lum>0.5;
}

// Render preset cards — group by FAMILY (red/blue/purple/green)
function renderPresets(){
  var grid=document.getElementById('presetGrid');

  function cardHtml(p){
    var c=p.colors;
    var light=isLightTheme(p);
    var tagClass=light?'light':'dark';
    var tagText=light?'☀️':'🌙';
    var h='<div class="preset-card" data-preset="'+p.id+'" data-name="'+p.name.toLowerCase()+'" data-family="'+p.family+'" onclick="applyPreset(\''+p.id+'\')">';
    // Mini mockup
    h+='<div class="pp-mock" style="background:'+c.theme_bg+'">';
    h+='<div class="pp-bar" style="background:'+c.theme_surface+'">';
    h+='<div class="pp-bar-dot" style="background:'+c.theme_primary+'"></div>';
    h+='<div class="pp-bar-line" style="background:'+c.theme_text2+'"></div>';
    h+='</div>';
    h+='<div class="pp-content" style="background:'+c.theme_surface+'">';
    h+='<div class="pp-h" style="background:'+c.theme_text+'"></div>';
    h+='<div class="pp-sub" style="background:'+c.theme_text3+'"></div>';
    h+='<div class="pp-btn" style="background:'+c.theme_primary+'"></div>';
    h+='</div>';
    h+='</div>';
    h+='<div class="preset-name-row">';
    h+='<div class="preset-name">'+p.name+'</div>';
    h+='<span class="preset-tag '+tagClass+'">'+tagText+'</span>';
    h+='</div>';
    h+='</div>';
    return h;
  }

  var FAMILIES=[
    {key:'red',    label:'Merah / Pink', emoji:'🔴', desc:'merah, pink, cherry, ruby, wine'},
    {key:'blue',   label:'Biru / Ice',   emoji:'🔵', desc:'biru, ice, ocean, sapphire, navy'},
    {key:'purple', label:'Ungu',         emoji:'🟣', desc:'ungu, lavender, lilac, plum, violet'},
    {key:'green',  label:'Hijau',        emoji:'🟢', desc:'hijau, mint, sage, forest, emerald'},
  ];
  
  var html='';
  FAMILIES.forEach(function(fam){
    var members=PRESETS.filter(function(p){return p.family===fam.key});
    if(!members.length)return;
    // Sort: light first, dark second
    members.sort(function(a,b){return (isLightTheme(a)?0:1)-(isLightTheme(b)?0:1)});
    html+='<div class="preset-group-title" data-family="'+fam.key+'"><span class="gt-emoji">'+fam.emoji+'</span> '+fam.label+' <span class="gt-count">'+members.length+'</span><span class="gt-desc">'+fam.desc+'</span></div>';
    html+='<div class="preset-row" data-group="'+fam.key+'">';
    members.forEach(function(p){html+=cardHtml(p)});
    html+='</div>';
  });
  grid.innerHTML=html;
  updateSearchInfo();
}

// Filter presets by search query
function filterPresets(){
  var q=(document.getElementById('presetSearch').value||'').trim().toLowerCase();
  var cards=document.querySelectorAll('.preset-card');
  var visible=0;
  cards.forEach(function(c){
    var name=c.dataset.name||'';
    var match=!q||name.indexOf(q)!==-1;
    c.classList.toggle('hidden',!match);
    if(match)visible++;
  });
  // Hide group title if all its cards are hidden
  document.querySelectorAll('.preset-row').forEach(function(row){
    var anyVis=row.querySelectorAll('.preset-card:not(.hidden)').length>0;
    var title=row.previousElementSibling;
    if(title&&title.classList.contains('preset-group-title')){
      title.style.display=anyVis?'':'none';
    }
    row.style.display=anyVis?'':'none';
  });
  updateSearchInfo(visible);
}

function updateSearchInfo(visible){
  var info=document.getElementById('presetSearchInfo');
  if(!info)return;
  if(typeof visible==='undefined')visible=PRESETS.length;
  if(visible===PRESETS.length){
    info.textContent='Menampilkan semua '+PRESETS.length+' tema';
  }else if(visible===0){
    info.innerHTML='<span style="color:var(--red)">Tidak ditemukan. Coba kata lain (blue, dark, gold, light, mint…)</span>';
  }else{
    info.textContent=visible+' dari '+PRESETS.length+' tema cocok';
  }
}

function applyPreset(id){
  var p=PRESETS.find(function(x){return x.id===id});
  if(!p)return;
  Object.keys(p.colors).forEach(function(k){
    var colorInput=document.querySelector('input[name="'+k+'"][type=color]');
    var textInput=document.getElementById(k+'_text');
    if(colorInput){colorInput.value=p.colors[k];}
    if(textInput){textInput.value=p.colors[k];}
  });
  // Mark active
  document.querySelectorAll('.preset-card').forEach(function(c){c.classList.remove('active')});
  var card=document.querySelector('[data-preset="'+id+'"]');
  if(card)card.classList.add('active');
}

function resetToDefault(){
  // Default = Sky Blue Dark (palette asli site sebelum dynamic theme)
  var def=PRESETS.find(function(p){return p.id==='sapphire_dark'})||PRESETS[0];
  if(def)applyPreset(def.id);
}

// ═══════════════ TAB SWITCHER ═══════════════
document.querySelectorAll('.tab-btn').forEach(function(btn){
  btn.addEventListener('click',function(){
    var t=this.dataset.tab;
    document.querySelectorAll('.tab-btn').forEach(function(b){b.classList.remove('active')});
    this.classList.add('active');
    document.querySelectorAll('.tab-section').forEach(function(s){s.classList.remove('active')});
    document.getElementById('tab-'+t).classList.add('active');
  });
});

// ═══════════════ COLOR INPUT SYNC ═══════════════
function syncColor(el){
  var target=document.getElementById(el.dataset.target);
  if(target)target.value=el.value;
}
function syncText(el,name){
  var v=el.value.trim();
  if(!/^#[0-9a-fA-F]{6}$/.test(v))return;
  var cp=document.querySelector('input[name="'+name+'"][type=color]');
  if(cp)cp.value=v;
}

// ═══════════════ GLASS OPACITY (slider 10-95 -> 0.1-0.95) ═══════════════
var opSlider=document.getElementById('glassOpacity');
var opHidden=document.getElementById('glassOpacityHidden');
opSlider.addEventListener('input',function(){
  opHidden.value=(this.value/100).toFixed(2);
});
// Remove the slider's name so only hidden input submits
opSlider.removeAttribute('name');
opSlider.setAttribute('data-display','1');

// ═══════════════ BG IMAGE PREVIEW ═══════════════
function updateBgPreview(){
  var url=document.getElementById('bgImageUrl').value.trim();
  var prev=document.getElementById('bgPreview');
  var modeRad=document.querySelector('input[name="theme_bg_mode"]:checked');
  var mode=modeRad?modeRad.value:'cover';
  if(url){
    prev.style.backgroundImage='url('+url+')';
    prev.textContent='';
    prev.style.backgroundSize=(mode==='tile')?'80px 80px':mode;
    prev.style.backgroundRepeat=(mode==='tile')?'repeat':'no-repeat';
    prev.style.backgroundPosition='center';
  }else{
    prev.style.backgroundImage='none';
    prev.textContent='Belum ada background image';
  }
}
// Re-render preview + toggle radio "on" class on mode change
document.addEventListener('change',function(e){
  if(e.target.name==='theme_bg_mode'){
    document.querySelectorAll('.bg-mode-opt').forEach(function(el){el.classList.remove('on')});
    var lab=e.target.closest('.bg-mode-opt');if(lab)lab.classList.add('on');
    updateBgPreview();
  }
});

// ═══════════════ UPLOAD BG ═══════════════
function uploadBg(input){
  if(!input.files||!input.files[0])return;
  var fd=new FormData();
  fd.append('file',input.files[0]);
  fd.append('type','general');
  fetch('../api/admin.php?action=upload',{method:'POST',body:fd,credentials:'same-origin'})
    .then(function(r){return r.json()})
    .then(function(d){
      if(d.ok&&d.url){
        document.getElementById('bgImageUrl').value=d.url;
        updateBgPreview();
        alert('Upload sukses!');
      }else{
        alert('Upload gagal: '+(d.error||'unknown'));
      }
    })
    .catch(function(){alert('Upload error')});
}

// ═══════════════ INIT ═══════════════
renderPresets();

// Detect current preset (cocokan dengan settings sekarang)
(function(){
  var current={
    <?php foreach($defaults as $k=>$v) if(strpos($k,'theme_glass')===false) echo "'$k':'".addslashes($th[$k])."',\n      "; ?>
  };
  PRESETS.forEach(function(p){
    var match=true;
    Object.keys(p.colors).forEach(function(k){
      if((p.colors[k]||'').toLowerCase()!==(current[k]||'').toLowerCase())match=false;
    });
    if(match){
      var card=document.querySelector('[data-preset="'+p.id+'"]');
      if(card)card.classList.add('active');
    }
  });
})();
</script>

<?php adminFooter(); ?>
