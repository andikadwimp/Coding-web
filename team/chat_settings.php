<?php
header('Content-Type: text/html; charset=UTF-8');
require_once 'layout.php'; adminHeader('Live Chat & Bot Telegram');

// Auto-create tables (sama dengan api/cs.php)
try{$db->exec("CREATE TABLE IF NOT EXISTS cs_buttons(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,label VARCHAR(100) NOT NULL,reply_text TEXT,reply_image VARCHAR(500) DEFAULT NULL,sort_order INT DEFAULT 0,is_active TINYINT DEFAULT 1,created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");}catch(Exception $e){}
try{$db->exec("CREATE TABLE IF NOT EXISTS cs_messages(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,sender ENUM('user','admin','bot') NOT NULL,message TEXT,image_url VARCHAR(500) DEFAULT NULL,tg_message_id BIGINT DEFAULT NULL,is_read TINYINT DEFAULT 0,created_at DATETIME DEFAULT CURRENT_TIMESTAMP,INDEX idx_user (user_id, id)) ENGINE=InnoDB");}catch(Exception $e){}
try{$db->exec("CREATE TABLE IF NOT EXISTS cs_user_tg_map(user_id INT UNSIGNED PRIMARY KEY,last_tg_msg_id BIGINT DEFAULT NULL,updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP) ENGINE=InnoDB");}catch(Exception $e){}

$flash=''; $flashType='success';

// Save settings
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['_act']??'')==='save_tg'){
    $token=trim($_POST['cs_tg_token']??'');
    $chatId=trim($_POST['cs_tg_chat_id']??'');
    $greeting=trim($_POST['cs_greeting']??'');
    $st=$db->prepare("INSERT INTO settings(`key`,`value`) VALUES(?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
    $st->execute(['cs_tg_token',$token]);
    $st->execute(['cs_tg_chat_id',$chatId]);
    $st->execute(['cs_greeting',$greeting]);
    // Auto-register webhook
    if($token){
        $domain=$_SERVER['HTTP_HOST']??'';
        if($domain){
            $whUrl="https://$domain/api/tg_webhook.php";
            $ch=curl_init("https://api.telegram.org/bot$token/setWebhook");
            curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['url'=>$whUrl]),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>8,CURLOPT_SSL_VERIFYPEER=>false]);
            $whRes=json_decode(curl_exec($ch),true);curl_close($ch);
            if($whRes&&!empty($whRes['ok'])){$flash='Tersimpan & Webhook Telegram aktif!';}
            else{$flash='Tersimpan, tapi webhook gagal: '.($whRes['description']??'unknown error'); $flashType='error';}
        }else{$flash='Tersimpan. Webhook tidak diset (domain kosong).';}
    }else{$flash='Tersimpan. Isi token Telegram untuk aktifkan bot.';}
}

// ── Button CRUD ──
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['_act']??'')==='save_btn'){
    $id=intval($_POST['id']??0);
    $label=trim($_POST['label']??'');
    $reply=trim($_POST['reply_text']??'');
    $img=trim($_POST['reply_image']??'');
    $sort=intval($_POST['sort_order']??0);
    $active=isset($_POST['is_active'])?1:0;
    if($label===''){$flash='Label wajib diisi';$flashType='error';}
    else{
        if($id>0){
            $db->prepare("UPDATE cs_buttons SET label=?,reply_text=?,reply_image=?,sort_order=?,is_active=? WHERE id=?")
               ->execute([$label,$reply,$img,$sort,$active,$id]);
            $flash='Button diupdate';
        }else{
            $db->prepare("INSERT INTO cs_buttons(label,reply_text,reply_image,sort_order,is_active) VALUES(?,?,?,?,?)")
               ->execute([$label,$reply,$img,$sort,$active]);
            $flash='Button baru ditambahkan';
        }
    }
}
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['_act']??'')==='del_btn'){
    $db->prepare("DELETE FROM cs_buttons WHERE id=?")->execute([intval($_POST['id']??0)]);
    $flash='Button dihapus';
}

// Test connection
$testResult='';
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['_act']??'')==='test_tg'){
    $s=[];try{$q=$db->query("SELECT `key`,`value` FROM settings WHERE `key` IN('cs_tg_token','cs_tg_chat_id')");foreach($q->fetchAll() as $r)$s[$r['key']]=$r['value'];}catch(Exception $e){}
    $token=$s['cs_tg_token']??'';$chatId=$s['cs_tg_chat_id']??'';
    if($token&&$chatId){
        $ch=curl_init("https://api.telegram.org/bot$token/sendMessage");
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query(['chat_id'=>$chatId,'text'=>'✅ Test koneksi dari admin panel. Bot aktif!']),CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>8,CURLOPT_SSL_VERIFYPEER=>false]);
        $res=json_decode(curl_exec($ch),true);curl_close($ch);
        $testResult=$res&&!empty($res['ok'])?'✅ Test berhasil! Cek Telegram.':'❌ Gagal: '.($res['description']??'unknown');
    }else{$testResult='⚠️ Token & Chat ID wajib diisi dulu';}
}

// Fetch data
$s=[];try{$q=$db->query("SELECT `key`,`value` FROM settings WHERE `key` IN('cs_tg_token','cs_tg_chat_id','cs_greeting')");foreach($q->fetchAll() as $r)$s[$r['key']]=$r['value'];}catch(Exception $e){}

// Auto-generate cron secret kalau belum ada
if(empty($s['cron_secret_key'])){
    $s['cron_secret_key']=bin2hex(random_bytes(16));
    try{$db->prepare("INSERT INTO settings(`key`,`value`) VALUES('cron_secret_key',?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)")->execute([$s['cron_secret_key']]);}catch(Exception $e){}
}
$cronDomain=$_SERVER['HTTP_HOST']??'cuanvvipgg.xyz';

$buttons=$db->query("SELECT * FROM cs_buttons ORDER BY sort_order ASC, id ASC")->fetchAll();
$editBtn=null;
if(isset($_GET['edit'])){
    $eb=$db->prepare("SELECT * FROM cs_buttons WHERE id=?");$eb->execute([intval($_GET['edit'])]);$editBtn=$eb->fetch()?:null;
}

// Stats
$totalChats=intval($db->query("SELECT COUNT(DISTINCT user_id) FROM cs_messages")->fetchColumn());
$unreadAdmin=intval($db->query("SELECT COUNT(*) FROM cs_messages WHERE sender='user' AND is_read=0")->fetchColumn());
?>
<style>
.cs-adm{max-width:900px;margin:0 auto}
.cs-card{background:#fff;border:1px solid var(--bd);border-radius:12px;padding:18px;margin-bottom:16px;box-shadow:0 1px 3px rgba(0,0,0,.04)}
.cs-card h3{font-size:1rem;margin-bottom:14px;color:var(--t);display:flex;align-items:center;gap:8px}
.cs-card h3 svg{width:20px;height:20px;color:var(--pri)}
.cs-fld{margin-bottom:12px}
.cs-fld label{display:block;font-size:.78rem;font-weight:600;margin-bottom:5px;color:var(--t)}
.cs-fld input[type=text],.cs-fld input[type=number],.cs-fld textarea{width:100%;padding:10px 12px;border:1.5px solid var(--bd);border-radius:8px;font-family:inherit;font-size:.85rem;outline:none;background:#fff;color:var(--t)}
.cs-fld textarea{min-height:90px;resize:vertical;font-family:inherit}
.cs-fld input:focus,.cs-fld textarea:focus{border-color:var(--pri)}
.cs-fld .hint{font-size:.7rem;color:#6b7280;margin-top:4px;line-height:1.4}
.cs-btn-row{display:flex;gap:8px;margin-top:14px;flex-wrap:wrap}
.cs-btn-a{padding:9px 18px;background:var(--pri);color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-size:.82rem}
.cs-btn-a:hover{opacity:.9}
.cs-btn-b{padding:9px 18px;background:#fff;color:var(--t);border:1.5px solid var(--bd);border-radius:8px;font-weight:700;cursor:pointer;font-size:.82rem;text-decoration:none;display:inline-block}
.cs-btn-d{padding:9px 18px;background:#dc2626;color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-size:.82rem}
.cs-flash{padding:10px 14px;border-radius:8px;margin-bottom:14px;font-weight:600;font-size:.85rem}
.cs-flash.success{background:rgba(34,197,94,.1);color:#15803d;border:1px solid rgba(34,197,94,.3)}
.cs-flash.error{background:rgba(220,38,38,.1);color:#991b1b;border:1px solid rgba(220,38,38,.3)}

.cs-tbl{width:100%;border-collapse:collapse}
.cs-tbl th,.cs-tbl td{padding:10px 8px;text-align:left;border-bottom:1px solid var(--bd);font-size:.82rem}
.cs-tbl th{background:#f9fafb;font-weight:700;color:var(--t);font-size:.72rem;text-transform:uppercase;letter-spacing:.5px}
.cs-tbl td .xs{font-size:.72rem;color:#6b7280}
.cs-stat{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px}
.cs-stat .sb{background:#fff;border:1px solid var(--bd);border-radius:10px;padding:14px;text-align:center}
.cs-stat .sb-v{font-size:1.4rem;font-weight:800;color:var(--pri)}
.cs-stat .sb-l{font-size:.68rem;color:#6b7280;margin-top:3px;text-transform:uppercase;letter-spacing:.5px}

.tab-wrap{display:flex;gap:2px;background:#f3f4f6;padding:4px;border-radius:10px;margin-bottom:16px}
.tab-wrap button{flex:1;padding:10px;background:none;border:none;font-weight:700;font-size:.82rem;cursor:pointer;border-radius:7px;color:#6b7280;font-family:inherit}
.tab-wrap button.on{background:#fff;color:var(--pri);box-shadow:0 1px 3px rgba(0,0,0,.08)}
.tab-pane{display:none}
.tab-pane.on{display:block}

.cs-pill{display:inline-block;padding:3px 9px;border-radius:10px;font-size:.66rem;font-weight:700}
.cs-pill.on{background:rgba(34,197,94,.15);color:#15803d}
.cs-pill.off{background:rgba(148,163,184,.2);color:#475569}
</style>

<div class="cs-adm">

<?php if($flash):?><div class="cs-flash <?=$flashType?>"><?=htmlspecialchars($flash)?></div><?php endif;?>
<?php if($testResult):?><div class="cs-flash <?=strpos($testResult,'✅')!==false?'success':'error'?>"><?=htmlspecialchars($testResult)?></div><?php endif;?>

<div class="cs-stat">
  <div class="sb"><div class="sb-v"><?=$totalChats?></div><div class="sb-l">Total User Chat</div></div>
  <div class="sb"><div class="sb-v" style="color:<?=$unreadAdmin>0?'#dc2626':'#22c55e'?>"><?=$unreadAdmin?></div><div class="sb-l">Belum Dibalas</div></div>
  <div class="sb"><div class="sb-v"><?=count($buttons)?></div><div class="sb-l">Total Button</div></div>
</div>

<div class="tab-wrap">
  <button class="on" onclick="setTab(0,this)">📋 Button Menu</button>
  <button onclick="setTab(1,this)">🤖 Telegram Bot</button>
  <button onclick="setTab(2,this)">📖 Panduan Setup</button>
</div>

<!-- TAB 0: Buttons -->
<div class="tab-pane on">

  <!-- Edit/Add button form -->
  <div class="cs-card">
    <h3>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
      <?=$editBtn?'Edit Button #'.$editBtn['id']:'Tambah Button Baru'?>
    </h3>
    <form method="post">
      <input type="hidden" name="_act" value="save_btn">
      <input type="hidden" name="id" value="<?=$editBtn['id']??0?>">
      <div class="cs-fld">
        <label>Label Button *</label>
        <input type="text" name="label" value="<?=htmlspecialchars($editBtn['label']??'')?>" placeholder="Misal: Deposit, Withdraw, Lupa Password" required maxlength="100">
      </div>
      <div class="cs-fld">
        <label>Auto-Reply (Text)</label>
        <textarea name="reply_text" placeholder="Teks yang dibalas saat user klik button ini. Boleh multi-line."><?=htmlspecialchars($editBtn['reply_text']??'')?></textarea>
        <div class="hint">Balasan otomatis yang ditampilkan ketika user klik button ini. Bisa pake emoji, enter multi-line, dll.</div>
      </div>
      <div class="cs-fld">
        <label>URL Gambar Petunjuk (opsional)</label>
        <input type="text" name="reply_image" value="<?=htmlspecialchars($editBtn['reply_image']??'')?>" placeholder="https://... atau upload/...png">
        <div class="hint">Gambar akan ditampilkan di bawah text reply. Bisa jpg/png/webp. URL full atau relative.</div>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
        <div class="cs-fld">
          <label>Urutan (kecil = atas)</label>
          <input type="number" name="sort_order" value="<?=intval($editBtn['sort_order']??0)?>" min="0">
        </div>
        <div class="cs-fld" style="display:flex;align-items:center;gap:8px;padding-top:22px">
          <input type="checkbox" name="is_active" id="f_active" <?=($editBtn['is_active']??1)?'checked':''?> style="width:18px;height:18px;cursor:pointer">
          <label for="f_active" style="margin:0;cursor:pointer">Button Aktif</label>
        </div>
      </div>
      <div class="cs-btn-row">
        <button class="cs-btn-a" type="submit"><?=$editBtn?'💾 Update':'+ Tambah Button'?></button>
        <?php if($editBtn):?><a class="cs-btn-b" href="chat_settings.php">Batal</a><?php endif;?>
      </div>
    </form>
  </div>

  <!-- List buttons -->
  <div class="cs-card">
    <h3>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
      Daftar Button (<?=count($buttons)?>)
    </h3>
    <?php if(!$buttons):?>
      <p style="color:#6b7280;text-align:center;padding:20px">Belum ada button. Tambah di form atas.</p>
    <?php else:?>
    <table class="cs-tbl">
      <thead><tr><th>#</th><th>Label</th><th>Reply Preview</th><th>Gambar</th><th>Urut</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach($buttons as $b):?>
        <tr>
          <td><?=$b['id']?></td>
          <td><b><?=htmlspecialchars($b['label'])?></b></td>
          <td><div style="max-width:250px;color:#6b7280;font-size:.78rem;line-height:1.4"><?=htmlspecialchars(mb_substr($b['reply_text']??'',0,80))?><?=mb_strlen($b['reply_text']??'')>80?'...':''?></div></td>
          <td><?=$b['reply_image']?'<img src="'.htmlspecialchars($b['reply_image']).'" style="width:40px;height:40px;object-fit:cover;border-radius:5px">':'<span class="xs">-</span>'?></td>
          <td><?=$b['sort_order']?></td>
          <td><span class="cs-pill <?=$b['is_active']?'on':'off'?>"><?=$b['is_active']?'Aktif':'Off'?></span></td>
          <td style="white-space:nowrap">
            <a href="?edit=<?=$b['id']?>" class="cs-btn-b" style="padding:6px 12px;font-size:.75rem">Edit</a>
            <form method="post" style="display:inline" onsubmit="return confirm('Hapus button '+<?=json_encode($b['label'])?>+'?')">
              <input type="hidden" name="_act" value="del_btn">
              <input type="hidden" name="id" value="<?=$b['id']?>">
              <button class="cs-btn-d" type="submit" style="padding:6px 12px;font-size:.75rem">Hapus</button>
            </form>
          </td>
        </tr>
      <?php endforeach;?>
      </tbody>
    </table>
    <?php endif;?>
  </div>

</div>

<!-- TAB 1: Telegram -->
<div class="tab-pane">
  <div class="cs-card">
    <h3>
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.5 15.5c-1 1-1.5 1.5-2 2-1 1-2 2-3.5 2s-2.5-1-3.5-2l-8-8c-1-1-2-2-2-3.5s1-2.5 2-3.5c.5-.5 1-1 2-2"/><path d="M8 21l1-5 11-11 4 4-11 11z"/></svg>
      Konfigurasi Bot Telegram
    </h3>
    <form method="post">
      <input type="hidden" name="_act" value="save_tg">
      <div class="cs-fld">
        <label>Greeting Awal</label>
        <input type="text" name="cs_greeting" value="<?=htmlspecialchars($s['cs_greeting']??'')?>" placeholder="Ada kendala apa ya bosku?" maxlength="200">
        <div class="hint">Pesan pembuka yang muncul paling atas chat user.</div>
      </div>
      <div class="cs-fld">
        <label>Bot Token *</label>
        <input type="text" name="cs_tg_token" value="<?=htmlspecialchars($s['cs_tg_token']??'')?>" placeholder="1234567890:AAH...">
        <div class="hint">Token dari <b>@BotFather</b> di Telegram. Lihat tab <b>Panduan Setup</b> untuk cara dapetin.</div>
      </div>
      <div class="cs-fld">
        <label>Chat ID Admin (Live Chat) *</label>
        <input type="text" name="cs_tg_chat_id" value="<?=htmlspecialchars($s['cs_tg_chat_id']??'')?>" placeholder="-1001234567890 atau 123456789">
        <div class="hint">ID chat admin (angka) atau grup. Chat pribadi: angka positif. Group: angka negatif. Lihat panduan.</div>
      </div>
      <div class="cs-btn-row">
        <button class="cs-btn-a" type="submit">💾 Simpan & Aktifkan Webhook</button>
      </div>
    </form>
  </div>

  <div class="cs-card">
    <h3>🔌 Test Koneksi</h3>
    <p style="font-size:.82rem;color:#6b7280;margin-bottom:12px">Klik tombol di bawah untuk kirim pesan test ke Telegram. Kalau berhasil, bot lu udah setup dengan benar.</p>
    <form method="post">
      <input type="hidden" name="_act" value="test_tg">
      <button class="cs-btn-a" type="submit">📤 Kirim Pesan Test</button>
    </form>
  </div>
</div>

<!-- TAB 2: Panduan -->
<div class="tab-pane">
  <div class="cs-card">
    <h3>📖 Cara Setup Bot Telegram</h3>
    <div style="font-size:.85rem;line-height:1.7;color:var(--t)">
      <p style="margin-bottom:12px"><b>1. Bikin Bot Telegram:</b></p>
      <ul style="padding-left:22px;margin-bottom:16px">
        <li>Buka Telegram, cari <b>@BotFather</b></li>
        <li>Ketik <code style="background:#f3f4f6;padding:2px 6px;border-radius:4px">/newbot</code></li>
        <li>Kasih nama bot (bebas)</li>
        <li>Kasih username bot (harus diakhiri <code>_bot</code>)</li>
        <li>BotFather akan kasih <b>Token</b> (formatnya <code>1234:ABC...</code>). Copy.</li>
      </ul>

      <p style="margin-bottom:12px"><b>2. Dapetin Chat ID:</b></p>
      <p style="margin-bottom:8px"><b>Opsi A — Chat Pribadi:</b></p>
      <ul style="padding-left:22px;margin-bottom:14px">
        <li>Start chat dengan bot lu (klik link <code>t.me/NAMA_BOT_nya</code>)</li>
        <li>Kirim pesan apa aja ke bot (misal: "halo")</li>
        <li>Buka di browser: <code style="background:#f3f4f6;padding:2px 6px;border-radius:4px;word-break:break-all">https://api.telegram.org/bot{TOKEN}/getUpdates</code></li>
        <li>Cari <code>"chat":{"id":<b>123456</b>}</code> — angka itu Chat ID lu</li>
      </ul>

      <p style="margin-bottom:8px"><b>Opsi B — Group:</b></p>
      <ul style="padding-left:22px;margin-bottom:14px">
        <li>Bikin group Telegram, invite bot lu</li>
        <li>Kirim pesan di group</li>
        <li>Buka <code>https://api.telegram.org/bot{TOKEN}/getUpdates</code></li>
        <li>Chat ID group biasanya <b>negatif</b> (misal <code>-1001234567890</code>)</li>
      </ul>

      <p style="margin-bottom:12px"><b>3. Isi di Tab Telegram Bot:</b></p>
      <ul style="padding-left:22px;margin-bottom:14px">
        <li>Paste Token & Chat ID</li>
        <li>Klik Simpan — webhook otomatis di-register</li>
        <li>Klik <b>Test Koneksi</b> — pastikan dapet pesan di Telegram</li>
      </ul>

      <p style="margin-bottom:12px"><b>4. Cara Balas User:</b></p>
      <ul style="padding-left:22px">
        <li>Saat user ngetik pesan di live chat, lu dapet notifikasi di Telegram</li>
        <li><b>Reply</b> message di Telegram (tahan lama → Reply)</li>
        <li>Balasan otomatis masuk ke chat user di web</li>
        <li>Tanda ✅ muncul kalau berhasil terkirim</li>
      </ul>
    </div>
  </div>

  <div class="cs-card" style="background:rgba(251,191,36,.08);border-color:#fbbf24">
    <h3 style="color:#92400e">⚠️ Penting</h3>
    <ul style="font-size:.82rem;line-height:1.7;padding-left:20px;color:#78350f">
      <li>Jangan share bot token ke siapa-siapa</li>
      <li>Kalau tokennya bocor, langsung revoke di @BotFather (/revoke)</li>
      <li>Webhook otomatis aktif saat lu klik Simpan — ga perlu setup manual</li>
      <li>Server lu harus HTTPS (SSL) biar webhook jalan</li>
      <li>Admin harus pake fitur <b>Reply</b> di Telegram, bukan kirim message biasa</li>
    </ul>
  </div>
</div>

</div>

<script>
function setTab(i,el){
  document.querySelectorAll('.tab-wrap button').forEach(function(b){b.classList.remove('on')});
  document.querySelectorAll('.tab-pane').forEach(function(p){p.classList.remove('on')});
  el.classList.add('on');
  document.querySelectorAll('.tab-pane')[i].classList.add('on');
}
</script>

<?php adminFooter();?>
