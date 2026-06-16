<?php
header('Content-Type: text/html; charset=UTF-8');
require_once 'layout.php'; adminHeader('Pengaturan');

$msg=''; $msgType='ok';

if($_SERVER['REQUEST_METHOD']==='POST'){
    $section=$_POST['section']??'';
    $st=$db->prepare("INSERT INTO settings(`key`,`value`) VALUES(?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
    
    $allKeys=[
        'site'=>['site_name','logo_url','pwa_icon','min_deposit','jackpot_img','jackpot_amount','redeem_banner','popup_img_1','popup_img_2','popup_img_3','float_invite_img','float_spin_img','float_cs_img','roulette_chars_img','apk_url','app_gate_enabled','fb_url','tg_url','tg_livechat','tt_url','wa_url','ig_url','x_url','marquee_text','menu_icon_beranda','menu_icon_games','menu_icon_deposit','menu_icon_withdraw','menu_icon_promosi','menu_icon_vip','menu_icon_apresiasi','menu_icon_misteri','menu_icon_bantuan','menu_icon_roulette','menu_icon_checkin','menu_icon_bonusdepo','menu_icon_undang','menu_icon_rebate','menu_icon_cs','menu_icon_profil','wm_size','wm_bottom','wm_right','wm_opacity','wm_stroke','undang_theme','referral_bonus_pct','referral_bonus_max','ref_tier1_min_deposit','ref_tier1_min_turnover','ref_tier2_min_deposit','ref_tier2_min_turnover','nexus_username_pattern','promo3_c1_a','promo3_c1_b','promo3_c1_link','promo3_c1_login_required','promo3_c2_a','promo3_c2_b','promo3_c2_link','promo3_c2_login_required','promo3_c3_a','promo3_c3_b','promo3_c3_link','promo3_c3_login_required'],
        'cs'=>['cs_telegram','cs_whatsapp','cs_telegram_channel','cs_main'],
        'nexus'=>['nexus_agent','nexus_token','nexus_url'],
        'payment'=>['squadonyx_merchant','squadonyx_url'],
        'bot'=>['bot_token','bot_admin_chat_id','bot_welcome','bot_smart_reply'],
    ];
    
    $keys=$allKeys[$section]??[];
    foreach($keys as $k){if(isset($_POST[$k]))$st->execute([$k,$_POST[$k]]);}
    
    // Register webhook if bot section saved
    if($section==='bot'&&!empty($_POST['bot_token'])){
        $token=trim($_POST['bot_token']);
        $domain=$_SERVER['HTTP_HOST']??'';
        if($domain){
            $wh="https://api.telegram.org/bot$token/setWebhook";
            $wurl="https://$domain/api/chat.php?action=webhook";
            $ch=curl_init($wh);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>['url'=>$wurl],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>5]);
            $wr=json_decode(curl_exec($ch),true);curl_close($ch);
            $msg='Tersimpan! Webhook: '.($wr['description']??'');
        }
    }
    if(!$msg)$msg='Tersimpan!';
}

$s=[];try{$rows=$db->query("SELECT `key`,`value` FROM settings")->fetchAll();foreach($rows as $r)$s[$r['key']]=$r['value'];}catch(Exception $e){}
?>

<style>
.settings-tabs{display:flex;gap:4px;margin-bottom:24px;flex-wrap:wrap}
.stab{padding:8px 16px;border-radius:8px;font-size:.75rem;font-weight:700;cursor:pointer;border:1.5px solid var(--bd);color:var(--t3);background:transparent;font-family:inherit;transition:all .15s}
.stab.active{background:var(--sec);color:#fff;border-color:var(--sec)}

.settings-section{display:none}.settings-section.active{display:block}

.sg{background:var(--bg2);border:1px solid var(--bd);border-radius:14px;padding:20px;margin-bottom:16px}
.sg-title{font-size:.8rem;font-weight:800;color:var(--sec);text-transform:uppercase;letter-spacing:.8px;margin-bottom:16px;display:flex;align-items:center;gap:8px}
.sg-title svg{width:16px;height:16px}

.field-row{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px}
@media(max-width:600px){.field-row{grid-template-columns:1fr}}
.field-row .fg{margin-bottom:0}
.fg{margin-bottom:12px}
.fg label{font-size:.65rem;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.5px;display:block;margin-bottom:5px}
.fg input,.fg select,.fg textarea{width:100%;padding:10px 12px;background:var(--bg);border:1.5px solid var(--bd);border-radius:8px;color:var(--t);font-family:inherit;font-size:.8rem;outline:none;transition:border-color .15s}
.fg input:focus,.fg select:focus,.fg textarea:focus{border-color:var(--sec)}
.fg .hint{font-size:.6rem;color:var(--t3);margin-top:4px}

.upload-btn{display:inline-flex;align-items:center;gap:5px;padding:7px 12px;background:rgba(var(--sec-rgb,56,189,248),.1);border:1px solid rgba(var(--sec-rgb,56,189,248),.3);border-radius:6px;font-size:.68rem;font-weight:700;color:var(--sec);cursor:pointer;flex-shrink:0}
.upload-btn svg{width:13px;height:13px}
.input-upload{display:flex;gap:6px;align-items:flex-start}
.input-upload input{flex:1}

.save-btn{display:flex;align-items:center;gap:8px;padding:11px 24px;background:linear-gradient(135deg,var(--sec),var(--sec-d,#38bdf8));border:none;border-radius:10px;color:#fff;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit;margin-top:4px}
.save-btn svg{width:16px;height:16px}

.api-test{display:flex;align-items:center;gap:8px;margin-top:12px;padding:10px 14px;background:rgba(var(--sec-rgb,56,189,248),.04);border:1px solid rgba(var(--sec-rgb,56,189,248),.15);border-radius:8px;font-size:.72rem;color:var(--t2)}
.api-test button{padding:5px 12px;background:rgba(var(--sec-rgb,56,189,248),.15);border:1px solid var(--sec);border-radius:6px;color:var(--sec);font-size:.65rem;font-weight:700;cursor:pointer;font-family:inherit;margin-left:auto}
#testResult{font-size:.7rem;margin-top:8px;padding:8px;border-radius:6px;display:none}
#testResult.ok{background:rgba(74,222,128,.08);color:#4ade80;border:1px solid rgba(74,222,128,.2)}
#testResult.err{background:rgba(239,68,68,.08);color:#ef4444;border:1px solid rgba(239,68,68,.15)}

.upload-msg{font-size:.68rem;margin-top:6px;min-height:18px}
</style>

<?php if($msg): ?>
<div class="msg msg-ok"><?=htmlspecialchars($msg)?></div>
<?php endif; ?>

<!-- Tabs -->
<div class="settings-tabs">
  <button type="button" class="stab active" onclick="switchTab('site',this)">Situs</button>
  <button type="button" class="stab" onclick="switchTab('cs',this)">Layanan CS</button>
  <button type="button" class="stab" onclick="switchTab('nexus',this)">NexusGGR</button>
  <button type="button" class="stab" onclick="switchTab('payment',this)">Pembayaran</button>
  <button type="button" class="stab" onclick="switchTab('bot',this)">Telegram Bot</button>
  <button type="button" class="stab" onclick="switchTab('menuicons',this)">Icon Menu</button>
  <button type="button" class="stab" onclick="switchTab('assets',this)">Gambar Statis</button>
</div>

<!-- ─── SITE ─── -->
<div class="settings-section active" id="tab-site">
<form method="POST"><input type="hidden" name="section" value="site">

<div class="sg">
  <div class="sg-title">
    <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
    Mode Aplikasi (App Gate)
  </div>
  <div class="fg">
    <label>Akses Situs</label>
    <select name="app_gate_enabled">
      <option value="1" <?=($s['app_gate_enabled']??'1')!=='0'?'selected':''?>>Khusus App — Non-app diredirect ke Download (DEFAULT: AKTIF)</option>
      <option value="0" <?=($s['app_gate_enabled']??'1')==='0'?'selected':''?>>Terbuka — Semua bisa akses via browser (tidak direkomendasikan)</option>
    </select>
    <div class="hint"><b style="color:var(--pri)">PENTING:</b> Toggle ini mengunci semua halaman kecuali Download &amp; Invite. User harus install APK/PWA dulu untuk akses. Setelah di-"Simpan", tes di browser incognito untuk memastikan.</div>
  </div>
</div>

<div class="sg">
  <div class="sg-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22 6 12 13 2 6"/></svg>Social Media & Link</div>
  <div class="field-row">
    <div class="fg"><label>Facebook URL</label><input name="fb_url" value="<?=htmlspecialchars($s['fb_url']??'')?>" placeholder="https://facebook.com/..."></div>
    <div class="fg"><label>Telegram URL (umum)</label><input name="tg_url" value="<?=htmlspecialchars($s['tg_url']??'')?>" placeholder="https://t.me/..."></div>
  </div>
  <div class="fg"><label>Telegram Live Chat URL</label><input name="tg_livechat" value="<?=htmlspecialchars($s['tg_livechat']??'')?>" placeholder="https://t.me/+xxxxx atau username admin live chat"><small style="font-size:.7rem;color:var(--t3);margin-top:4px;display:block">Tombol Telegram di sidebar diarahkan ke link ini (live chat khusus, BUKAN auto-post)</small></div>
  <div class="fg"><label>WhatsApp URL</label><input name="wa_url" value="<?=htmlspecialchars($s['wa_url']??'')?>" placeholder="https://wa.me/628..."></div>
  <div class="field-row">
    <div class="fg"><label>Instagram URL</label><input name="ig_url" value="<?=htmlspecialchars($s['ig_url']??'')?>" placeholder="https://instagram.com/..."></div>
    <div class="fg"><label>TikTok URL</label><input name="tt_url" value="<?=htmlspecialchars($s['tt_url']??'')?>" placeholder="https://tiktok.com/@..."></div>
  </div>
  <div class="fg"><label>Twitter / X URL</label><input name="x_url" value="<?=htmlspecialchars($s['x_url']??'')?>" placeholder="https://x.com/..."></div>
</div>

<div class="sg">
  <div class="sg-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>Icon Menu Sidebar (Custom)</div>
  <p style="font-size:.72rem;color:var(--t3);margin-bottom:10px;line-height:1.5">Upload gambar custom untuk icon menu sidebar. Kalau kosong, akan pakai icon SVG default. Disarankan ukuran 64×64 PNG transparan.</p>
  <?php
  $menu_keys = [
    'beranda'=>'Beranda','games'=>'Permainan','deposit'=>'Deposit','withdraw'=>'Penarikan',
    'promosi'=>'Promosi','vip'=>'VIP','apresiasi'=>'Apresiasi Anggota','misteri'=>'Bonus Misteri',
    'bantuan'=>'Bantuan Mingguan','roulette'=>'100K Gratis','checkin'=>'Hadiah Masuk',
    'bonusdepo'=>'Bonus Deposit','undang'=>'Undang Teman','rebate'=>'Rebate','cs'=>'Live Chat','profil'=>'Profil',
  ];
  ?>
  <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:10px">
  <?php foreach($menu_keys as $k=>$lbl): $val=$s["menu_icon_$k"]??''; ?>
    <div class="fg" style="margin:0">
      <label style="display:flex;align-items:center;gap:8px;font-size:.74rem">
        <?php if($val):?><img src="<?=htmlspecialchars($val)?>" style="width:22px;height:22px;object-fit:contain;border-radius:4px;background:var(--bg)"><?php else:?><span style="display:inline-block;width:22px;height:22px;border:1px dashed var(--bd2);border-radius:4px;text-align:center;line-height:20px;font-size:.6rem;color:var(--t4)">—</span><?php endif;?>
        <?=htmlspecialchars($lbl)?>
      </label>
      <input name="menu_icon_<?=$k?>" value="<?=htmlspecialchars($val)?>" placeholder="URL gambar atau path /uploads/..." style="font-size:.7rem">
    </div>
  <?php endforeach;?>
  </div>
</div>

<div class="sg">
  <div class="sg-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>Watermark Logo di Banner</div>

  <?php
  // Get first banner for preview
  $prevBanner='';
  try{$pb=$db->query("SELECT image_url FROM promos WHERE status='active' AND image_url IS NOT NULL AND image_url!='' LIMIT 1")->fetch();if($pb)$prevBanner=$pb['image_url'];}catch(Exception $e){}
  $logoUrl=$s['logo_url']??'';
  ?>

  <!-- Preview -->
  <div style="position:relative;width:100%;aspect-ratio:16/9;border-radius:10px;overflow:hidden;margin-bottom:14px;border:1px solid var(--bd);background:#1a1a2e" id="wmPreviewBox">
    <?php if($prevBanner):?>
    <img src="<?=htmlspecialchars(substr($prevBanner,0,4)==='http'?$prevBanner:'../'.$prevBanner)?>" style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0" onerror="this.style.display='none'">
    <?php else:?>
    <div style="position:absolute;inset:0;background:linear-gradient(135deg,#1a3a5c,#2d1b69);display:flex;align-items:center;justify-content:center;font-size:.65rem;color:rgba(255,255,255,.25)">Upload banner dulu untuk preview</div>
    <?php endif;?>
    <?php if($logoUrl):?>
    <img id="wmPrevLogo" src="<?=htmlspecialchars(substr($logoUrl,0,4)==='http'?$logoUrl:'../'.$logoUrl)?>" style="position:absolute;object-fit:contain;pointer-events:none;filter:none" onerror="this.style.display='none'">
    <?php else:?>
    <div id="wmPrevLogo" style="position:absolute;width:60px;height:60px;background:var(--pri);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.45rem;font-weight:800;color:#000">LOGO</div>
    <?php endif;?>
  </div>

  <!-- Arrow pad + sliders -->
  <div style="display:flex;gap:14px;align-items:flex-start;margin-bottom:14px">
    <!-- D-pad -->
    <div style="flex-shrink:0">
      <div style="font-size:.6rem;color:var(--t3);font-weight:700;text-transform:uppercase;margin-bottom:6px;text-align:center">Geser</div>
      <div style="display:grid;grid-template-columns:36px 36px 36px;grid-template-rows:36px 36px 36px;gap:3px">
        <div></div>
        <button type="button" class="btn btn-sec" style="width:36px;height:36px;padding:0;display:flex;align-items:center;justify-content:center;font-size:1rem;border-radius:8px" onclick="wmNudge('up')">&#9650;</button>
        <div></div>
        <button type="button" class="btn btn-sec" style="width:36px;height:36px;padding:0;display:flex;align-items:center;justify-content:center;font-size:1rem;border-radius:8px" onclick="wmNudge('left')">&#9664;</button>
        <button type="button" class="btn btn-pri" style="width:36px;height:36px;padding:0;display:flex;align-items:center;justify-content:center;font-size:.5rem;font-weight:800;border-radius:8px" onclick="wmReset()">RST</button>
        <button type="button" class="btn btn-sec" style="width:36px;height:36px;padding:0;display:flex;align-items:center;justify-content:center;font-size:1rem;border-radius:8px" onclick="wmNudge('right')">&#9654;</button>
        <div></div>
        <button type="button" class="btn btn-sec" style="width:36px;height:36px;padding:0;display:flex;align-items:center;justify-content:center;font-size:1rem;border-radius:8px" onclick="wmNudge('down')">&#9660;</button>
        <div></div>
      </div>
      <div style="display:flex;gap:3px;margin-top:6px">
        <button type="button" class="btn btn-sec" style="flex:1;font-size:.55rem;padding:6px 0" onclick="wmNudge('smaller')">&#8722; Kecil</button>
        <button type="button" class="btn btn-sec" style="flex:1;font-size:.55rem;padding:6px 0" onclick="wmNudge('bigger')">&#43; Besar</button>
      </div>
    </div>
    <!-- Values -->
    <div style="flex:1">
      <div class="fg" style="margin-bottom:8px"><label>Ukuran (px)</label><input name="wm_size" id="wmSize" type="number" value="<?=htmlspecialchars($s['wm_size']??'100')?>" min="30" max="250" oninput="wmUpdate()"></div>
      <div class="fg" style="margin-bottom:8px"><label>Opacity (%)</label><input name="wm_opacity" id="wmOpacity" type="number" value="<?=htmlspecialchars($s['wm_opacity']??'90')?>" min="10" max="100" oninput="wmUpdate()"></div>
      <div class="fg" style="margin-bottom:8px"><label>Bawah (px)</label><input name="wm_bottom" id="wmBottom" type="number" value="<?=htmlspecialchars($s['wm_bottom']??'0')?>" min="-50" max="200" oninput="wmUpdate()"></div>
      <div class="fg" style="margin-bottom:0"><label>Kanan (px)</label><input name="wm_right" id="wmRight" type="number" value="<?=htmlspecialchars($s['wm_right']??'0')?>" min="-50" max="200" oninput="wmUpdate()"></div>
      <div class="fg" style="margin-bottom:0;margin-top:8px"><label>Warna Stroke</label>
        <div style="display:flex;gap:6px;align-items:center">
          <input type="color" name="wm_stroke" id="wmStroke" value="<?=htmlspecialchars($s['wm_stroke']??'#ffffff')?>" style="width:40px;height:34px;border:1px solid var(--bd);border-radius:6px;background:var(--bg);cursor:pointer;padding:2px" oninput="wmUpdate()">
          <input type="text" id="wmStrokeText" value="<?=htmlspecialchars($s['wm_stroke']??'#ffffff')?>" style="flex:1;padding:8px;background:var(--bg);border:1px solid var(--bd);border-radius:6px;color:var(--t);font-size:.75rem" oninput="document.getElementById('wmStroke').value=this.value;wmUpdate()">
        </div>
      </div>
    </div>
  </div>
  <div class="hint">Pakai panah untuk geser logo. Klik Simpan di bawah untuk menyimpan posisi.</div>
</div>

<button class="save-btn" type="submit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>Simpan Pengaturan Situs</button>
</form>
</div>

<!-- ─── CS ─── -->
<div class="settings-section" id="tab-cs">
<form method="POST"><input type="hidden" name="section" value="cs">
<div class="sg">
  <div class="sg-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 015.11 12 19.79 19.79 0 012.12 3.18 2 2 0 014.11 1h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>Kontak Layanan Pelanggan</div>
  <div class="field-row">
    <div class="fg"><label>CS Telegram Username</label><input name="cs_telegram" value="<?=htmlspecialchars($s['cs_telegram']??'')?>" placeholder="@username atau t.me/username"><div class="hint">Tanpa https:// — cukup @username</div></div>
    <div class="fg"><label>CS WhatsApp Nomor</label><input name="cs_whatsapp" value="<?=htmlspecialchars($s['cs_whatsapp']??'')?>" placeholder="628123456789"><div class="hint">Format internasional tanpa +</div></div>
  </div>
  <div class="field-row">
    <div class="fg"><label>Channel Telegram Resmi</label><input name="cs_telegram_channel" value="<?=htmlspecialchars($s['cs_telegram_channel']??'')?>" placeholder="@channelname"></div>
    <div class="fg"><label>Link CS Utama (Tombol Hero)</label><input name="cs_main" value="<?=htmlspecialchars($s['cs_main']??'')?>" placeholder="https://t.me/..."></div>
  </div>
</div>
<button class="save-btn" type="submit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>Simpan Pengaturan CS</button>
</form>
</div>

<!-- ─── NEXUSGGR ─── -->
<div class="settings-section" id="tab-nexus">
<form method="POST"><input type="hidden" name="section" value="nexus">
<div class="sg">
  <div class="sg-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="3"/><circle cx="8" cy="12" r="2"/><circle cx="16" cy="12" r="2"/></svg>Kredensial NexusGGR API</div>
  <div class="fg"><label>Agent Code</label><input name="nexus_agent" value="<?=htmlspecialchars($s['nexus_agent']??NEXUS_AGENT??'')?>" placeholder="your_agent_code"><div class="hint">Agent code dari NexusGGR dashboard</div></div>
  <div class="fg"><label>Agent Token</label><input name="nexus_token" type="password" id="nexus_token_field" value="<?=htmlspecialchars($s['nexus_token']??NEXUS_TOKEN??'')?>">
    <div style="display:flex;align-items:center;gap:8px;margin-top:4px">
      <button type="button" onclick="togglePw('nexus_token_field')" style="font-size:.62rem;color:var(--sec);background:none;border:none;cursor:pointer;padding:0">Tampilkan</button>
    </div>
  </div>
  <div class="fg"><label>API URL</label><input name="nexus_url" value="<?=htmlspecialchars($s['nexus_url']??NEXUS_URL??'https://api.nexusggr.com')?>" placeholder="https://api.nexusggr.com"></div>

  <div class="api-test">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>
    Simpan dulu sebelum test koneksi
    <button type="button" onclick="testNexus()">Test Koneksi</button>
  </div>
  <div id="testResult"></div>
</div>
<button class="save-btn" type="submit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>Simpan Kredensial NexusGGR</button>
</form>
</div>

<!-- ─── PAYMENT ─── -->
<div class="settings-section" id="tab-payment">
<form method="POST"><input type="hidden" name="section" value="payment">
<div class="sg">
  <div class="sg-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>SquadOnyx Payment Gateway</div>
  <div class="fg"><label>Merchant UID</label><input name="squadonyx_merchant" value="<?=htmlspecialchars($s['squadonyx_merchant']??SQUADONYX_MERCHANT??'')?>" placeholder="muid_xxxx..."><div class="hint">Merchant UID dari panel SquadOnyx</div></div>
  <div class="fg"><label>API URL</label><input name="squadonyx_url" value="<?=htmlspecialchars($s['squadonyx_url']??SQUADONYX_URL??'https://panel.squadonyx.biz.id/api.php')?>" placeholder="https://panel.squadonyx.biz.id/api.php"></div>
  <div class="api-test">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
    <span>Merchant: <b><?=htmlspecialchars($s['squadonyx_merchant']??SQUADONYX_MERCHANT??'-')?></b></span>
    <button type="button" onclick="testSquad()">Ping API</button>
  </div>
  <div id="squadResult"></div>
</div>
<button class="save-btn" type="submit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>Simpan Pengaturan Pembayaran</button>
</form>
</div>

<!-- ─── BOT ─── -->
<div class="settings-section" id="tab-bot">
<form method="POST"><input type="hidden" name="section" value="bot">
<div class="sg">
  <div class="sg-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/><line x1="12" y1="15" x2="12" y2="17"/></svg>Telegram Bot Config</div>
  <div class="fg"><label>Bot Token</label>
    <div class="input-upload">
      <input name="bot_token" type="password" id="bot_token_field" value="<?=htmlspecialchars($s['bot_token']??'')?>" placeholder="123456789:AABBccDDeeFF...">
      <button type="button" onclick="togglePw('bot_token_field')" class="upload-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="vertical-align:-2px;margin-right:4px"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>Show</button>
    </div>
    <div class="hint">Dari @BotFather di Telegram</div>
  </div>
  <div class="fg"><label>Admin Chat ID</label><input name="bot_admin_chat_id" value="<?=htmlspecialchars($s['bot_admin_chat_id']??'')?>" placeholder="123456789"><div class="hint">Kirim /start ke @userinfobot untuk dapat ID kamu</div></div>
  <div class="fg"><label>Pesan Sambutan (Welcome Message)</label>
    <textarea name="bot_welcome" rows="3" placeholder="Halo! Terima kasih sudah menghubungi kami..."><?=htmlspecialchars($s['bot_welcome']??'')?></textarea>
    <div class="hint">Gunakan #a teks|link untuk buat link klik. Contoh: #a cek promo|https://...</div>
  </div>
  <div class="fg"><label>Smart Auto-Reply</label>
    <select name="bot_smart_reply">
      <option value="1" <?=($s['bot_smart_reply']??'1')==='1'?'selected':''?>>Aktif — belajar dari percakapan</option>
      <option value="0" <?=($s['bot_smart_reply']??'1')==='0'?'selected':''?>>Nonaktif</option>
    </select>
  </div>
</div>
<button class="save-btn" type="submit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>Simpan & Daftar Webhook</button>
</form>
</div>

<!-- ─── ASSETS (gambar statis) ─── -->
<!-- ─── MENU ICONS ─── -->
<div class="settings-section" id="tab-menuicons">
<div style="background:var(--bg2);border:1px solid var(--bd);border-radius:10px;padding:14px 16px;margin-bottom:20px;font-size:.72rem;color:var(--t2);line-height:1.6">
  <b style="color:var(--sec);font-size:.78rem">Icon Menu Sidebar</b><br>
  Upload gambar PNG buat ngegantiin icon SVG default di sidebar member. Kalau dikosongin, icon SVG yang dipake. Format: PNG/WEBP/SVG. Disarankan persegi (1:1), min 64×64px, transparent bg.
</div>
<form method="POST" enctype="multipart/form-data"><input type="hidden" name="section" value="site">
<div class="sg">
  <div class="sg-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/></svg>Icon Override (URL Gambar)</div>
  <?php
    $menuItems=[
      ['beranda','Beranda'],['games','Permainan'],['deposit','Deposit'],['withdraw','Penarikan'],
      ['promosi','Promosi'],['vip','VIP'],
      ['apresiasi','Apresiasi'],['misteri','Bonus Misteri'],['bantuan','Bantuan Mingguan'],
      ['roulette','100K Gratis'],['checkin','Check-in'],['bonusdepo','Bonus Deposit'],
      ['undang','Undang Teman'],['rebate','Rebate'],
      ['cs','Live Chat'],['profil','Profil'],
    ];
  ?>
  <div class="field-row" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
  <?php foreach($menuItems as $mi): $k='menu_icon_'.$mi[0]; $v=$s[$k]??''; ?>
    <div class="fg">
      <label style="display:flex;align-items:center;gap:8px">
        <span style="width:24px;height:24px;border-radius:50%;background:var(--bg);border:1px solid var(--bd);display:inline-flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden">
          <?php if($v): ?><img src="<?=htmlspecialchars($v)?>" style="width:16px;height:16px;object-fit:contain"><?php else: ?><svg viewBox="0 0 24 24" fill="none" stroke="var(--t3)" stroke-width="2" width="12" height="12"><circle cx="12" cy="12" r="10"/></svg><?php endif; ?>
        </span>
        <?=htmlspecialchars($mi[1])?>
      </label>
      <input type="url" name="<?=$k?>" value="<?=htmlspecialchars($v)?>" placeholder="https://... atau kosongin">
    </div>
  <?php endforeach; ?>
  </div>
  <div class="hint" style="margin-top:8px">Kalo udah upload gambar lewat tab <b>Gambar Statis</b> atau pake URL eksternal, paste URL-nya di sini. Kosongin = pake icon default SVG.</div>
</div>
<button class="save-btn" type="submit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/></svg>Simpan Icon Menu</button>
</form>
</div>

<div class="settings-section" id="tab-assets">

<div style="background:var(--bg2);border:1px solid var(--bd);border-radius:10px;padding:14px 16px;margin-bottom:20px;font-size:.72rem;color:var(--t2);line-height:1.6">
  <b style="color:var(--sec);font-size:.78rem"><svg viewBox="0 0 24 24" fill="none" stroke="var(--sec)" stroke-width="2" width="14" height="14" style="vertical-align:-2px;margin-right:4px"><path d="M12 2a7 7 0 017 7c0 2.5-1.5 4.5-3 5.5V17h-8v-2.5C6.5 13.5 5 11.5 5 9a7 7 0 017-7z"/></svg>Upload Gambar Statis</b><br>
  Gambar di bawah ini dipanggil langsung oleh sistem via path yang fixed — misalnya karakter di halaman Apresiasi, banner Misteri, icon navigasi bottom bar, dll. Upload di sini bakal <b>langsung replace file lama</b> (file lama di-backup otomatis sbg .bak).<br>
  <span style="color:var(--t3)">Format: JPG, PNG, GIF, WEBP. Max 20 MB per file.</span>
</div>

<?php
// Define asset groups
$assetGroups=[
    'Karakter & Icon Umum'=>[
        ['key'=>'char1','label'=>'Karakter 1 — Halaman Bantuan','path'=>'asset/char1.png','desc'=>'Gambar karakter yg muncul di halaman Bantuan (bantuan.php)','format'=>'PNG'],
        ['key'=>'char2','label'=>'Karakter 2 — Halaman Apresiasi','path'=>'asset/char2.png','desc'=>'Gambar karakter di halaman Apresiasi (apresiasi.php)','format'=>'PNG'],
        ['key'=>'char3','label'=>'Karakter 3 — Halaman Misteri','path'=>'asset/char3.png','desc'=>'Gambar karakter di halaman Misteri (misteri.php)','format'=>'PNG'],
        ['key'=>'roulette_chars','label'=>'Karakter — Halaman Roulette','path'=>'img/roulette/chars1.png','desc'=>'Karakter di belakang wheel roulette (roulette.php). Disarankan transparent PNG portrait.','format'=>'PNG'],
        ['key'=>'coin','label'=>'Koin — Halaman Misteri','path'=>'asset/coin.png','desc'=>'Icon koin di halaman Misteri (misteri.php)','format'=>'PNG'],
        ['key'=>'cs_avatar','label'=>'Avatar CS','path'=>'asset/cs_avatar.png','desc'=>'Avatar default untuk Customer Service','format'=>'PNG'],
        ['key'=>'icon_penyedia','label'=>'Icon Provider/Penyedia','path'=>'asset/icon_penyedia.png','desc'=>'Icon kecil buat label provider di games','format'=>'PNG'],
        ['key'=>'bank_icon','label'=>'Icon Bank','path'=>'asset/bank_icon.png','desc'=>'Icon default bank di halaman withdraw/deposit','format'=>'PNG'],
    ],
    'Banner Halaman'=>[
        ['key'=>'misteri_banner','label'=>'Banner Halaman Misteri','path'=>'asset/misteri_banner.jpg','desc'=>'Banner utama halaman Misteri','format'=>'JPG'],
        ['key'=>'apresiasi_banner','label'=>'Banner Halaman Apresiasi','path'=>'asset/apresiasi_banner.jpg','desc'=>'Banner utama halaman Apresiasi','format'=>'JPG'],
        ['key'=>'bantuan_banner','label'=>'Banner Halaman Bantuan','path'=>'asset/bantuan_banner.jpg','desc'=>'Banner utama halaman Bantuan','format'=>'JPG'],
        ['key'=>'bonusdepo_banner','label'=>'Banner Halaman Bonus Deposit','path'=>'asset/bonusdepo_banner.jpg','desc'=>'Banner utama halaman Bonus Deposit','format'=>'JPG'],
        ['key'=>'checkin_banner','label'=>'Banner Halaman Check-in','path'=>'asset/checkin_banner.jpg','desc'=>'Banner utama halaman Check-in Harian','format'=>'JPG'],
        ['key'=>'roulette_banner','label'=>'Banner Halaman Roulette','path'=>'asset/roulette_banner.jpg','desc'=>'Banner utama halaman Roulette','format'=>'JPG'],
    ],
    'Promo Carousel (Halaman Download/Invite)'=>[
        ['key'=>'promo_p1','label'=>'Promo Gambar 1','path'=>'asset/promo/p1.png','desc'=>'Slide promo ke-1 di halaman Download & Invite','format'=>'PNG'],
        ['key'=>'promo_p2','label'=>'Promo Gambar 2','path'=>'asset/promo/p2.png','desc'=>'Slide promo ke-2','format'=>'PNG'],
        ['key'=>'promo_p3','label'=>'Promo Gambar 3','path'=>'asset/promo/p3.png','desc'=>'Slide promo ke-3','format'=>'PNG'],
        ['key'=>'promo_p4','label'=>'Promo Gambar 4','path'=>'asset/promo/p4.png','desc'=>'Slide promo ke-4','format'=>'PNG'],
        ['key'=>'promo_p5','label'=>'Promo Gambar 5','path'=>'asset/promo/p5.png','desc'=>'Slide promo ke-5','format'=>'PNG'],
        ['key'=>'promo_p6','label'=>'Promo Gambar 6','path'=>'asset/promo/p6.jpg','desc'=>'Slide promo ke-6','format'=>'JPG'],
    ],
    // Note: Icon Navigasi Bawah dihapus dari sini — sekarang full SVG inline yang otomatis ngikut warna tema (lihat menu Tampilan & Tema).
];
$rootPath=realpath(__DIR__.'/..');
foreach($assetGroups as $groupName=>$items):
?>
<h3 style="font-size:.85rem;font-weight:700;margin:24px 0 12px;color:var(--pri);border-bottom:1px solid var(--bd);padding-bottom:6px"><?=htmlspecialchars($groupName)?></h3>
<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:12px">
<?php foreach($items as $it):
  $absFile=$rootPath.'/'.$it['path'];
  $exists=file_exists($absFile);
  $size=$exists?filesize($absFile):0;
  $mtime=$exists?filemtime($absFile):0;
  $bust=$mtime?'?v='.$mtime:'';
?>
<div style="background:var(--bg2);border:1px solid var(--bd);border-radius:10px;padding:12px">
  <div style="display:flex;gap:10px;align-items:flex-start;margin-bottom:8px">
    <div style="width:60px;height:60px;background:var(--bg);border:1px solid var(--bd);border-radius:6px;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden">
      <?php if($exists): ?>
        <img id="preview_<?=$it['key']?>" src="/<?=htmlspecialchars($it['path'])?><?=$bust?>" style="max-width:100%;max-height:100%;object-fit:contain" onerror="this.style.display='none';this.nextElementSibling.style.display='block'">
        <span style="display:none;font-size:.55rem;color:var(--t3)">Error</span>
      <?php else: ?>
        <span id="preview_<?=$it['key']?>" style="font-size:.55rem;color:var(--t3);text-align:center;padding:4px">Belum Ada</span>
      <?php endif; ?>
    </div>
    <div style="flex:1;min-width:0">
      <div style="font-size:.75rem;font-weight:700;color:var(--t);margin-bottom:2px"><?=htmlspecialchars($it['label'])?></div>
      <div style="font-size:.58rem;color:var(--t3);line-height:1.4;margin-bottom:4px"><?=htmlspecialchars($it['desc'])?></div>
      <div style="font-size:.55rem;color:var(--t3);font-family:monospace">
        <?=htmlspecialchars($it['path'])?>
        <?php if($exists): ?>
          <span style="color:var(--green)">• <?=number_format($size/1024,1)?> KB</span>
        <?php else: ?>
          <span style="color:#fbbf24">• tidak ada</span>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <label class="upload-btn" style="display:flex;align-items:center;justify-content:center;gap:6px;padding:8px 12px;background:var(--sec);color:#fff;border-radius:6px;font-size:.7rem;font-weight:700;cursor:pointer;text-align:center">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4M17 8l-5-5-5 5M12 3v12"/></svg>
    <span>Upload (<?=$it['format']?>)</span>
    <input type="file" accept="image/png,image/jpeg,image/gif,image/webp" style="display:none" onchange="uploadAsset(this,'<?=$it['key']?>','<?=htmlspecialchars($it['path'])?>')">
  </label>
  <div id="status_<?=$it['key']?>" style="font-size:.6rem;margin-top:4px;text-align:center;color:var(--t3);min-height:14px"></div>
</div>
<?php endforeach; ?>
</div>
<?php endforeach; ?>

</div>

<script>
function switchTab(id,btn){
  document.querySelectorAll('.settings-section').forEach(function(s){s.classList.remove('active')});
  document.querySelectorAll('.stab').forEach(function(b){b.classList.remove('active')});
  document.getElementById('tab-'+id).classList.add('active');
  btn.classList.add('active');
}

function uploadAsset(inp,targetKey,relPath){
  var f=inp.files[0];if(!f)return;
  var st=document.getElementById('status_'+targetKey);
  st.style.color='var(--sec)';st.textContent='Uploading...';
  var fd=new FormData();fd.append('file',f);fd.append('target',targetKey);
  fetch('../api/admin.php?action=upload_asset',{method:'POST',credentials:'same-origin',body:fd})
  .then(function(r){return r.json()}).then(function(d){
    if(d.ok){
      st.style.color='var(--green)';st.textContent='✓ Tersimpan';
      // Refresh preview
      var prev=document.getElementById('preview_'+targetKey);
      if(prev){
        if(prev.tagName==='IMG'){
          prev.src='/'+relPath+'?v='+d.cache_buster;
        }else{
          // Ganti span jadi img
          var img=document.createElement('img');
          img.id='preview_'+targetKey;
          img.src='/'+relPath+'?v='+d.cache_buster;
          img.style.maxWidth='100%';img.style.maxHeight='100%';img.style.objectFit='contain';
          prev.parentNode.replaceChild(img,prev);
        }
      }
      setTimeout(function(){st.textContent=''},3000);
    }else{
      st.style.color='#ef4444';st.textContent='✗ '+(d.error||'Gagal');
    }
    inp.value='';
  }).catch(function(e){
    st.style.color='#ef4444';st.textContent='✗ Error koneksi';
    inp.value='';
  });
}

function togglePw(id){
  var el=document.getElementById(id);
  el.type=el.type==='password'?'text':'password';
}

function qUploadAPK(input){
  var file=input.files[0];if(!file)return;
  var fd=new FormData();fd.append('file',file);fd.append('type','apk');
  var fg=input.closest('.fg');
  var msgEl=fg.querySelector('.upload-msg');
  if(!msgEl){msgEl=document.createElement('div');msgEl.className='upload-msg';fg.appendChild(msgEl);}
  msgEl.style.color='var(--pri)';msgEl.textContent='Uploading APK ('+Math.round(file.size/1024/1024)+'MB)...';
  fetch('../api/admin.php?action=upload',{method:'POST',credentials:'same-origin',body:fd})
  .then(function(r){return r.json()}).then(function(d){
    if(!d.ok){msgEl.style.color='#ef4444';msgEl.textContent='Error: '+(d.error||'Gagal');return;}
    document.getElementById('f_apk_url').value=d.url;
    var settings={apk_url:d.url};
    fetch('../api/admin.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'save_settings',settings:settings})})
    .then(function(r){return r.json()}).then(function(s){
      msgEl.style.color=s.ok?'#4ade80':'#ef4444';
      msgEl.textContent=s.ok?'APK Tersimpan! ('+d.url+')':'Upload OK tapi gagal simpan';
    });
  }).catch(function(){msgEl.style.color='#ef4444';msgEl.textContent='Koneksi error';});
}

function qUpload(input,type,fieldId){
  var file=input.files[0];if(!file)return;
  var fd=new FormData();fd.append('file',file);fd.append('type',type);
  // Find or create msg element
  var msgId='um_'+fieldId.replace('f_','');
  var msgEl=document.getElementById(msgId);
  if(!msgEl){
    msgEl=document.createElement('div');msgEl.id=msgId;msgEl.className='upload-msg';
    input.closest('.fg').appendChild(msgEl);
  }
  msgEl.style.color='var(--pri)';msgEl.textContent='Uploading...';
  // Show local preview
  var prevId='pv_'+fieldId.replace('f_','');
  var prevEl=document.getElementById(prevId);
  if(!prevEl){
    prevEl=document.createElement('img');prevEl.id=prevId;prevEl.style.cssText='max-width:120px;max-height:80px;border-radius:6px;margin-top:6px;display:block;border:1px solid var(--bd)';
    input.closest('.fg').appendChild(prevEl);
  }
  prevEl.src=URL.createObjectURL(file);
  
  fetch('../api/admin.php?action=upload',{method:'POST',credentials:'same-origin',body:fd})
  .then(function(r){return r.json()}).then(function(d){
    if(!d.ok){msgEl.style.color='#ef4444';msgEl.textContent='Error: '+(d.error||'Gagal');return;}
    var inp=document.getElementById(fieldId);if(inp)inp.value=d.url;
    // Auto-save to settings
    var settings={};settings[fieldId.replace('f_','')]=d.url;
    fetch('../api/admin.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'save_settings',settings:settings})})
    .then(function(r){return r.json()}).then(function(s){
      msgEl.style.color=s.ok?'#4ade80':'#ef4444';
      msgEl.textContent=s.ok?'Tersimpan!':'Upload OK tapi gagal simpan';
      setTimeout(function(){msgEl.textContent=''},3000);
    });
  }).catch(function(){msgEl.style.color='#ef4444';msgEl.textContent='Koneksi error';});
}

function testNexus(){
  var res=document.getElementById('testResult');
  res.style.display='block';res.className='';res.textContent='Testing...';
  fetch('../api/game.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'nexus_status'})})
  .then(r=>r.json()).then(function(d){
    if(d.ok){res.className='ok';res.textContent='✓ Terhubung! Agent: '+d.agent_code+' | Balance: Rp '+Number(d.agent_balance||0).toLocaleString('id');}
    else{res.className='err';res.textContent='✗ Gagal: '+(d.error||'?');}
  }).catch(function(e){res.className='err';res.textContent='✗ Koneksi error: '+e.message;});
}

function testSquad(){
  var res=document.getElementById('squadResult');
  res.style.display='block';res.style.cssText='display:block;font-size:.7rem;margin-top:6px;padding:8px;border-radius:6px;color:var(--t3)';
  res.textContent='Pinging...';
  fetch('../api/deposit.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'ping_gateway'})})
  .then(r=>r.json()).then(function(d){
    res.style.color=d.ok?'#4ade80':'#ef4444';
    res.textContent=d.ok?'✓ SquadOnyx Online! Status: '+d.status:'✗ Gagal: '+(d.error||'?');
  }).catch(function(e){res.style.color='#ef4444';res.textContent='✗ Error: '+e.message;});
}

function wmUpdate(){
  var img=document.getElementById('wmPrevLogo');if(!img)return;
  var sz=parseInt(document.getElementById('wmSize').value)||100;
  var bt=parseInt(document.getElementById('wmBottom').value)||0;
  var rt=parseInt(document.getElementById('wmRight').value)||0;
  var op=parseInt(document.getElementById('wmOpacity').value)||90;
  var sc=document.getElementById('wmStroke').value||'#ffffff';
  document.getElementById('wmStrokeText').value=sc;
  img.style.width=sz+'px';img.style.height=sz+'px';
  img.style.bottom=bt+'px';img.style.right=rt+'px';
  img.style.opacity=(op/100);
  img.style.filter='drop-shadow(2px 0 0 '+sc+') drop-shadow(-2px 0 0 '+sc+') drop-shadow(0 2px 0 '+sc+') drop-shadow(0 -2px 0 '+sc+') drop-shadow(0 2px 6px rgba(0,0,0,.5))';
}
function wmNudge(dir){
  var step=2;
  var bt=document.getElementById('wmBottom');
  var rt=document.getElementById('wmRight');
  var sz=document.getElementById('wmSize');
  if(dir==='up')bt.value=parseInt(bt.value)+step;
  if(dir==='down')bt.value=parseInt(bt.value)-step;
  if(dir==='left')rt.value=parseInt(rt.value)+step;
  if(dir==='right')rt.value=parseInt(rt.value)-step;
  if(dir==='bigger')sz.value=parseInt(sz.value)+5;
  if(dir==='smaller')sz.value=Math.max(20,parseInt(sz.value)-5);
  wmUpdate();
}
function wmReset(){
  document.getElementById('wmSize').value=100;
  document.getElementById('wmBottom').value=0;
  document.getElementById('wmRight').value=0;
  document.getElementById('wmOpacity').value=90;
  wmUpdate();
}
wmUpdate();

// Open tab from URL hash
var hash=location.hash.replace('#','');
if(hash){var btn=document.querySelector('[onclick*="'+hash+'"]');if(btn)btn.click();}

// Auto-show previews for existing images
document.querySelectorAll('.input-upload input[id^="f_"]').forEach(function(inp){
  var v=inp.value;if(!v||!v.match(/\.(jpg|jpeg|png|gif|webp|svg)/i))return;
  var src=v.startsWith('http')?v:'../'+v;
  var pv=document.createElement('img');
  pv.src=src;pv.style.cssText='max-width:100px;max-height:60px;border-radius:6px;margin-top:6px;display:block;border:1px solid var(--bd)';
  pv.onerror=function(){this.style.display='none'};
  inp.closest('.fg').appendChild(pv);
});
</script>
<?php adminFooter(); ?>
