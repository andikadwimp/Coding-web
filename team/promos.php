<?php
header('Content-Type: text/html; charset=UTF-8');
require_once 'layout.php'; adminHeader('Banner & Promosi');
try{$db->exec("CREATE TABLE IF NOT EXISTS promos(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,title VARCHAR(200),description TEXT,image_url VARCHAR(500),link VARCHAR(500) DEFAULT '#',button_text VARCHAR(50) DEFAULT 'Proses',category VARCHAR(30) DEFAULT 'promosi',status VARCHAR(20) DEFAULT 'active',created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");}catch(Exception $e){}
try{$db->exec("ALTER TABLE promos ADD COLUMN category VARCHAR(30) DEFAULT 'promosi' AFTER status");}catch(Exception $e){}
try{$db->exec("ALTER TABLE promos ADD COLUMN link VARCHAR(500) DEFAULT '#' AFTER image_url");}catch(Exception $e){}
try{$db->exec("ALTER TABLE promos ADD COLUMN button_text VARCHAR(50) DEFAULT 'Proses' AFTER link");}catch(Exception $e){}
try{$db->exec("ALTER TABLE promos ADD COLUMN description TEXT AFTER title");}catch(Exception $e){}
?>

<style>
#formModal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:200;align-items:center;justify-content:center;padding:16px}
.form-box{background:var(--s);border:1px solid var(--bd);border-radius:14px;padding:20px;width:100%;max-width:480px;max-height:92vh;overflow-y:auto}
.form-box h3{font-size:.95rem;font-weight:700;margin-bottom:16px}
.info-box{padding:12px;background:rgba(var(--sec-rgb,56,189,248),.06);border:1px solid rgba(var(--sec-rgb,56,189,248),.15);border-radius:10px;margin-bottom:16px;font-size:.68rem;color:var(--t2);line-height:1.5}
.info-box b{color:var(--sec)}
</style>

<div class="info-box">
  <b>Cara kerja:</b> Semua data di sini muncul di <b>slider homepage</b> (yang punya gambar) dan di <b>tab Promosi</b>. Upload gambar → otomatis jadi banner slider. Tanpa gambar → hanya muncul di tab Promosi sebagai teks.
</div>

<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
  <p style="font-size:.7rem;color:var(--t3)" id="totalCount"></p>
  <button class="btn btn-pri" onclick="openForm()">+ Tambah</button>
</div>
<div id="statusMsg"></div>
<div id="promoList"></div>

<!-- Form Modal -->
<div id="formModal">
<div class="form-box">
  <h3 id="formTitle">Tambah Promosi</h3>
  <input type="hidden" id="fId">

  <div class="fg"><label>Gambar (Banner)</label>
    <div style="display:flex;gap:6px">
      <input id="fImg" placeholder="URL gambar atau upload" style="flex:1" oninput="prevImg()">
      <label style="cursor:pointer;flex-shrink:0"><div class="btn btn-sec" id="uploadBtn">Upload</div>
        <input type="file" accept="image/*" style="display:none" onchange="doUpload(this)">
      </label>
    </div>
    <img id="fPreview" style="display:none;margin-top:8px;width:100%;border-radius:8px;max-height:160px;object-fit:contain">
    <div class="hint" style="margin-top:4px">Gambar akan muncul di slider homepage DAN tab promosi</div>
  </div>

  <div class="fg"><label>Judul</label><input id="fTitle" placeholder="Contoh: Bonus Deposit 100%"></div>
  <div class="fg"><label>Deskripsi</label><textarea id="fDesc" rows="2" style="width:100%;padding:8px;background:var(--bg);border:1px solid var(--bd);border-radius:8px;color:var(--t);font-family:inherit;font-size:.78rem;resize:vertical" placeholder="Deskripsi singkat..."></textarea></div>
  <div class="fg"><label>Link (klik menuju)</label><input id="fLink" placeholder="https://... atau spin.php, deposit.php, dll"></div>

  <div class="fg-row">
    <div class="fg"><label>Teks Tombol</label><input id="fBtn" value="Proses"></div>
    <div class="fg"><label>Status</label>
      <select id="fStatus" style="width:100%;padding:8px;background:var(--bg);border:1px solid var(--bd);border-radius:8px;color:var(--t);font-size:.78rem">
        <option value="active">Aktif</option><option value="inactive">Nonaktif</option>
      </select>
    </div>
  </div>

  <div style="display:flex;gap:8px;margin-top:12px">
    <button class="btn btn-pri" onclick="saveItem()" style="flex:1">Simpan</button>
    <button class="btn btn-sec" onclick="closeForm()" style="flex:1">Batal</button>
  </div>
</div>
</div>

<script>
var API='../api/admin.php',allPromos=[];

function api(body){return fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify(body)}).then(function(r){return r.json()})}
function msg(t,ok){var e=document.getElementById('statusMsg');e.innerHTML='<div class="msg '+(ok?'msg-ok':'msg-err')+'">'+t+'</div>';setTimeout(function(){e.innerHTML=''},3000)}
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;')}

function loadPromos(){
  api({action:'get_promos'}).then(function(d){
    if(!d.ok)return;allPromos=d.promos||[];
    var withImg=0;
    var h='';
    if(!allPromos.length)h='<div style="color:var(--t3);font-size:.78rem;padding:20px 0">Belum ada data. Klik + Tambah untuk mulai.</div>';
    allPromos.forEach(function(p,i){
      var hasImg=p.image_url&&p.image_url.length>3;
      if(hasImg)withImg++;
      h+='<div class="item-row" style="align-items:flex-start;margin-bottom:6px">';
      if(hasImg)h+='<img src="'+esc(p.image_url)+'" style="width:70px;height:45px;object-fit:cover;border-radius:6px;flex-shrink:0" onerror="this.style.display=\'none\'">';
      else h+='<div style="width:70px;height:45px;background:var(--bg2);border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:.45rem;color:var(--t3);flex-shrink:0">Teks saja</div>';
      h+='<div class="ir-info"><div class="ir-title" style="font-size:.75rem">'+esc(p.title||'Tanpa judul')+'</div>';
      h+='<div class="ir-sub" style="margin-top:2px">';
      if(hasImg)h+='<span style="background:var(--sec);color:#fff;font-size:.45rem;padding:1px 5px;border-radius:4px;margin-right:4px">SLIDER</span>';
      h+='<span style="color:'+(p.status==='active'?'var(--green)':'var(--t3)')+'">'+esc(p.status)+'</span>';
      if(p.link&&p.link!=='#')h+=' · <span style="font-size:.55rem;color:var(--sec)">'+esc(p.link).substring(0,25)+'</span>';
      h+='</div></div>';
      h+='<div class="ir-actions" style="flex-shrink:0">';
      h+='<button class="btn btn-sec btn-sm" onclick="editPromo('+i+')">Edit</button>';
      h+='<button class="btn btn-red btn-sm" onclick="delPromo('+p.id+')">×</button>';
      h+='</div></div>';
    });
    document.getElementById('promoList').innerHTML=h;
    document.getElementById('totalCount').textContent=allPromos.length+' total · '+withImg+' dengan gambar (slider)';
  });
}

function openForm(data){
  document.getElementById('formTitle').textContent=data?'Edit Promosi':'Tambah Promosi';
  document.getElementById('fId').value=data?data.id:'';
  document.getElementById('fImg').value=data?data.image_url||'':'';
  document.getElementById('fTitle').value=data?data.title||'':'';
  document.getElementById('fDesc').value=data?data.description||'':'';
  document.getElementById('fLink').value=data?data.link||'':'';
  document.getElementById('fBtn').value=data?data.button_text||'Proses':'Proses';
  document.getElementById('fStatus').value=data?data.status||'active':'active';
  prevImg();
  document.getElementById('formModal').style.display='flex';
}
function editPromo(i){openForm(allPromos[i])}
function closeForm(){document.getElementById('formModal').style.display='none'}
function prevImg(){var v=document.getElementById('fImg').value;var p=document.getElementById('fPreview');if(v){p.src=v;p.style.display='block'}else p.style.display='none'}

function doUpload(input){
  var file=input.files[0];if(!file)return;
  var fd=new FormData();fd.append('file',file);fd.append('type','promo');
  document.getElementById('uploadBtn').textContent='...';
  fetch('../api/admin.php?action=upload',{method:'POST',credentials:'same-origin',body:fd})
  .then(function(r){return r.json()}).then(function(d){
    document.getElementById('uploadBtn').textContent='Upload';
    if(!d.ok){msg(d.error||'Gagal',false);return}
    document.getElementById('fImg').value=d.url;prevImg();msg('Upload OK!',true);
  }).catch(function(){document.getElementById('uploadBtn').textContent='Upload';msg('Error',false)});
}

function saveItem(){
  var payload={
    action:'save_promo',
    title:document.getElementById('fTitle').value.trim(),
    description:document.getElementById('fDesc').value.trim(),
    image_url:document.getElementById('fImg').value.trim(),
    link:document.getElementById('fLink').value.trim()||'#',
    button_text:document.getElementById('fBtn').value.trim()||'Proses',
    category:'promosi',
    status:document.getElementById('fStatus').value
  };
  var id=document.getElementById('fId').value;
  if(id)payload.id=parseInt(id);
  if(!payload.title&&!payload.image_url){msg('Isi minimal judul atau gambar',false);return}
  if(!payload.title)payload.title='Banner';
  api(payload).then(function(d){
    if(d.ok){closeForm();loadPromos();msg('Tersimpan!',true)}
    else msg(d.error||'Gagal',false);
  });
}

function delPromo(id){
  if(!confirm('Hapus promosi ini?'))return;
  api({action:'delete_promo',id:id}).then(function(d){if(d.ok){loadPromos();msg('Dihapus!',true)}});
}

loadPromos();
</script>
<?php adminFooter(); ?>
