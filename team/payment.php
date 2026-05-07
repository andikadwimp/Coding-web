<?php
header('Content-Type: text/html; charset=UTF-8');
require_once 'layout.php';adminHeader('Payment & Bonus');
$msg='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $act=$_POST['act']??'';
    if($act==='save_bonus'){
        $id=$_POST['id']??'';
        if($id){$db->prepare("UPDATE bonuses SET name=?,percentage=?,max_amount=?,turnover_x=?,min_deposit=?,status=? WHERE id=?")->execute([$_POST['name']??'',$_POST['pct']??0,$_POST['max']??0,$_POST['to']??1,$_POST['mindep']??0,$_POST['status']??'active',$id]);}
        else{$db->prepare("INSERT INTO bonuses(name,percentage,max_amount,turnover_x,min_deposit,status) VALUES(?,?,?,?,?,?)")->execute([$_POST['name']??'',$_POST['pct']??0,$_POST['max']??0,$_POST['to']??1,$_POST['mindep']??0,$_POST['status']??'active']);}
        $msg='Bonus berhasil disimpan';
    }
    if($act==='del_bonus'){$db->prepare("DELETE FROM bonuses WHERE id=?")->execute([$_POST['id']??0]);$msg='Bonus dihapus';}
    if($act==='save_sqx'){
        $st=$db->prepare("INSERT INTO settings(`key`,`value`) VALUES(?,?) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)");
        $st->execute(['sqx_merchant',trim($_POST['sqx']??'')]);
        $msg='Merchant UID tersimpan';
    }
}
$bonuses=[];try{$bonuses=$db->query("SELECT * FROM bonuses ORDER BY id")->fetchAll();}catch(Exception $e){}
$sqx='';try{$sqx=$db->query("SELECT value FROM settings WHERE `key`='sqx_merchant'")->fetchColumn();}catch(Exception $e){}
$host=$_SERVER['HTTP_HOST']??'yourdomain.com';
$cbUrl="https://$host/api/deposit.php?action=callback";
?>
<style>
.sect{background:#fff;border:1px solid var(--bd);border-radius:12px;padding:18px 20px;margin-bottom:18px;box-shadow:0 1px 3px rgba(30,41,59,.04)}
.sect-hdr{display:flex;align-items:center;gap:10px;margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid var(--bd)}
.sect-hdr svg{width:22px;height:22px;color:var(--pri);flex-shrink:0}
.sect-hdr h2{font-size:.95rem;font-weight:700;color:var(--t);margin:0}
.sect-sub{font-size:.72rem;color:var(--t2);margin-top:2px;font-weight:400}
.url-box{background:var(--bg);border:1.5px solid var(--bd);border-radius:8px;padding:12px 14px;display:flex;align-items:center;gap:10px;font-family:'SFMono-Regular',Menlo,Monaco,monospace;font-size:.78rem;color:var(--pri);word-break:break-all;line-height:1.5}
.url-box .url-txt{flex:1;min-width:0}
.url-box .copy-btn{flex-shrink:0;background:var(--pri);color:#fff;border:none;padding:7px 14px;border-radius:6px;font-size:.7rem;font-weight:700;cursor:pointer;font-family:inherit;display:flex;align-items:center;gap:5px}
.url-box .copy-btn:hover{background:var(--pri-d)}
.url-box .copy-btn svg{width:12px;height:12px}
.note-info{background:#eff6ff;border-left:3px solid var(--pri);padding:10px 12px;border-radius:6px;font-size:.72rem;color:var(--t2);line-height:1.6;margin-top:10px}
.note-info b{color:var(--pri)}
.form-group{display:flex;gap:8px;align-items:flex-end}
.form-group .fg{flex:1;margin:0}
.form-group button{margin-bottom:0}
.bonus-list{display:flex;flex-direction:column;gap:8px;margin-top:14px}
.bonus-row{display:flex;align-items:center;gap:12px;padding:12px;background:#fff;border:1px solid var(--bd);border-radius:8px}
.bonus-row .b-ico{flex-shrink:0;width:40px;height:40px;border-radius:8px;background:var(--pri-l);display:flex;align-items:center;justify-content:center;color:var(--pri)}
.bonus-row .b-ico svg{width:20px;height:20px}
.bonus-row .b-info{flex:1;min-width:0}
.bonus-row .b-name{font-size:.82rem;font-weight:700;color:var(--t)}
.bonus-row .b-meta{font-size:.68rem;color:var(--t2);margin-top:2px}
.bonus-row .b-meta span{display:inline-flex;align-items:center;gap:3px;margin-right:10px}
.bonus-row .b-status{padding:2px 8px;border-radius:6px;font-size:.65rem;font-weight:700;text-transform:uppercase}
.bonus-row .b-status.active{background:#d1fae5;color:#ffffff}
.bonus-row .b-status.inactive{background:#fee2e2;color:#991b1b}
.btn-icon{width:32px;height:32px;padding:0;display:flex;align-items:center;justify-content:center;background:#fee2e2;color:#991b1b;border:none;border-radius:6px;cursor:pointer}
.btn-icon svg{width:14px;height:14px}
.btn-icon:hover{background:#fca5a5;color:#fff}
.toast{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);background:#1e293b;color:#fff;padding:10px 20px;border-radius:8px;font-size:.78rem;font-weight:600;box-shadow:0 10px 40px rgba(0,0,0,.2);z-index:9999;opacity:0;transition:opacity .2s;pointer-events:none}
.toast.show{opacity:1}
</style>

<?php if($msg): ?>
<div class="msg msg-ok"><?=htmlspecialchars($msg)?></div>
<?php endif; ?>

<!-- ═══ Payment Gateway (SquadOnyx) ═══ -->
<div class="sect">
<div class="sect-hdr">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
  <div>
    <h2>Payment Gateway — SquadOnyx</h2>
    <div class="sect-sub">Kredensial &amp; konfigurasi provider pembayaran otomatis</div>
  </div>
</div>

<form method="POST" style="margin-bottom:14px">
<input type="hidden" name="act" value="save_sqx">
<div class="fg">
  <label>Merchant UID</label>
  <div class="form-group">
    <div class="fg">
      <input name="sqx" value="<?=htmlspecialchars($sqx)?>" placeholder="muid_xxxxxxxxxxxxxxxxxxxxxxxxx" style="font-family:'SFMono-Regular',Menlo,Monaco,monospace">
    </div>
    <button class="btn btn-pri" type="submit">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
      Simpan
    </button>
  </div>
</div>
</form>

<div class="fg">
<label>URL Callback Webhook</label>
<div class="url-box">
  <div class="url-txt" id="cbUrl"><?=htmlspecialchars($cbUrl)?></div>
  <button type="button" class="copy-btn" onclick="copyUrl(this,'cbUrl')">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg>
    Copy
  </button>
</div>
</div>

<div class="note-info">
<b>Cara pasang callback URL:</b><br>
1. Copy URL di atas<br>
2. Login ke panel SquadOnyx → <b>Settings</b> → <b>Webhook / Callback URL</b><br>
3. Paste URL &amp; simpan. SquadOnyx akan ping URL ini tiap ada pembayaran<br>
4. URL ini aman diberikan ke provider — tidak mengandung password
</div>
</div>

<!-- ═══ Bonus Deposit ═══ -->
<div class="sect">
<div class="sect-hdr">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 010-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 000-5C13 2 12 7 12 7z"/></svg>
  <div>
    <h2>Bonus Deposit</h2>
    <div class="sect-sub">Atur bonus yang bisa dipilih user saat deposit</div>
  </div>
</div>

<form method="POST" style="background:var(--bg);border:1px solid var(--bd);border-radius:8px;padding:14px;margin-bottom:6px">
<input type="hidden" name="act" value="save_bonus">
<input type="hidden" name="id" value="">
<div class="fg">
  <label>Nama Bonus</label>
  <input name="name" required placeholder="Contoh: Bonus New Member 100%">
</div>
<div class="fg-row">
  <div class="fg"><label>Persentase (%)</label><input name="pct" type="number" value="100" min="0" max="500"></div>
  <div class="fg"><label>Max Bonus (Rp)</label><input name="max" type="number" value="0" placeholder="0 = unlimited"></div>
  <div class="fg"><label>Turnover x</label><input name="to" type="number" value="5" min="1"></div>
</div>
<div class="fg-row">
  <div class="fg"><label>Min Deposit (Rp)</label><input name="mindep" type="number" value="0"></div>
  <div class="fg"><label>Status</label><select name="status"><option value="active">Aktif</option><option value="inactive">Non-aktif</option></select></div>
</div>
<button class="btn btn-pri" type="submit">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
  Tambah Bonus
</button>
</form>

<div class="note-info">
<b>Keterangan field:</b><br>
• <b>Persentase</b>: % bonus dari nominal deposit (20 = +20% bonus)<br>
• <b>Max Bonus</b>: batas maksimal bonus dalam rupiah (0 = tanpa batas)<br>
• <b>Turnover x</b>: kelipatan TO yang harus dicapai sebelum bisa WD. Rumus: (deposit + bonus) × turnover_x<br>
• <b>Min Deposit</b>: deposit minimal agar bonus ini bisa dipilih (0 = tanpa batas)
</div>

<?php if(!empty($bonuses)): ?>
<div style="font-size:.72rem;font-weight:700;color:var(--t2);margin:16px 0 6px;text-transform:uppercase;letter-spacing:.5px">Bonus Aktif (<?=count($bonuses)?>)</div>
<div class="bonus-list">
<?php foreach($bonuses as $b): ?>
<div class="bonus-row">
  <div class="b-ico">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/></svg>
  </div>
  <div class="b-info">
    <div class="b-name"><?=htmlspecialchars($b['name'])?></div>
    <div class="b-meta">
      <span><?=$b['percentage']?>%</span>
      <span>Max Rp <?=number_format($b['max_amount'],0,',','.')?></span>
      <span>TO x<?=$b['turnover_x']?></span>
      <?php if(intval($b['min_deposit'])>0): ?><span>Min Rp <?=number_format($b['min_deposit'],0,',','.')?></span><?php endif; ?>
    </div>
  </div>
  <span class="b-status <?=$b['status']?>"><?=$b['status']?></span>
  <form method="POST" onsubmit="return confirm('Hapus bonus ini?')">
    <input type="hidden" name="act" value="del_bonus">
    <input type="hidden" name="id" value="<?=$b['id']?>">
    <button class="btn-icon" type="submit" title="Hapus">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 01-2 2H7a2 2 0 01-2-2V6m3 0V4a2 2 0 012-2h4a2 2 0 012 2v2"/></svg>
    </button>
  </form>
</div>
<?php endforeach; ?>
</div>
<?php else: ?>
<div style="text-align:center;padding:30px;color:var(--t3);font-size:.8rem">Belum ada bonus. Tambahkan di atas.</div>
<?php endif; ?>
</div>

<div id="toast" class="toast"></div>

<script>
function copyUrl(btn,targetId){
  var txt=document.getElementById(targetId).textContent;
  if(navigator.clipboard){
    navigator.clipboard.writeText(txt).then(function(){showToast('URL tersalin ke clipboard')});
  }else{
    var ta=document.createElement('textarea');ta.value=txt;document.body.appendChild(ta);ta.select();
    try{document.execCommand('copy');showToast('URL tersalin')}catch(e){}
    document.body.removeChild(ta);
  }
}
function showToast(msg){
  var t=document.getElementById('toast');t.textContent=msg;t.classList.add('show');
  setTimeout(function(){t.classList.remove('show')},2000);
}
</script>

<?php adminFooter(); ?>
