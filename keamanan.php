<?php
require_once 'includes/config.php';
header('Content-Type: text/html; charset=UTF-8');
$isLoggedIn = (bool)getUid();  // guest-friendly: page accessible, action gated by JS

$uid=getUid();$user=null;
if($uid){try{$u=$db->prepare("SELECT * FROM users WHERE id=?");$u->execute([$uid]);$user=$u->fetch();}catch(Exception $e){}}
$ph=$user['phone']??'';if(substr($ph,0,1)==='8')$ph='0'.$ph;
$hasPin=!empty($user['fund_pin']);
$sets=[];try{$st=$db->query("SELECT `key`,`value` FROM settings");foreach($st->fetchAll() as $r)$sets[$r['key']]=$r['value'];normSettings($sets);}catch(Exception $e){}
$sn=$sets['site_name']??'Situs';
?>
<!DOCTYPE html>
<html lang="id"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Pusat Keamanan - <?php echo $sn; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="theme.php?v=<?=time()?>">
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;-webkit-text-size-adjust:100%;text-size-adjust:100%}
html{font-size:16px!important;overflow-x:hidden}
body{font-family:'Poppins',sans-serif;background:var(--bg);color:var(--t);min-height:100vh;overflow-x:hidden;max-width:100vw}
.hdr{display:flex;align-items:center;padding:14px 16px;position:sticky;top:0;z-index:10;background:var(--bg)}
.hdr button{width:32px;height:32px;background:none;border:none;color:var(--t2);cursor:pointer;display:flex;align-items:center;justify-content:center}
.hdr button svg{width:22px;height:22px}
.hdr h1{flex:1;text-align:center;font-size:1.05rem;font-weight:700}
.card{margin:16px;background:var(--s);border:1px solid var(--bd);border-radius:14px;overflow:hidden}
.row{display:flex;align-items:center;gap:12px;padding:18px 16px;border-bottom:1px solid rgba(var(--sec-rgb,56,189,248),.1)}
.row:last-child{border:none}
.row .r-icon{width:28px;height:28px;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--t3)}
.row .r-icon svg{width:22px;height:22px}
.row .r-label{flex:1;font-size:.82rem;font-weight:700}
.row .r-val{font-size:.82rem;color:var(--t2);text-align:right}
.row .r-arr{color:var(--t3);cursor:pointer;display:flex;align-items:center}
.row .r-arr svg{width:16px;height:16px}
.row .r-status{font-size:.75rem;color:var(--t2)}
.row .r-status.set{color:var(--sec)}
.row .r-status.unset{color:#ef4444}
/* Modal */
.modal{position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:200;display:none;align-items:center;justify-content:center}
.modal.open{display:flex}
.modal-box{background:var(--s);border:1px solid var(--bd);border-radius:16px;padding:24px 20px;width:calc(100% - 40px);max-width:350px}
.modal-box h3{font-size:1rem;font-weight:700;margin-bottom:6px}
.modal-box p{font-size:.72rem;color:var(--t3);margin-bottom:18px}
.modal-box .fg{margin-bottom:14px}
.modal-box .fg label{font-size:.72rem;font-weight:600;color:var(--t2);margin-bottom:6px;display:block}
.modal-box .fg input{width:100%;padding:12px;background:var(--bg2);border:1px solid var(--bd);border-radius:10px;color:var(--t);font-family:inherit;font-size:.88rem;font-weight:700;text-align:center;letter-spacing:8px;outline:none}
.modal-box .fg input::placeholder{letter-spacing:0;font-weight:400;color:var(--t3)}
.modal-box .btn-row{display:flex;gap:8px}
.modal-box .btn{flex:1;padding:12px;border:none;border-radius:10px;font-size:.85rem;font-weight:700;cursor:pointer;font-family:inherit}
.modal-box .btn-pri{background:var(--sec);color:#fff}
.modal-box .btn-sec{background:var(--bg2);color:var(--t2);border:1px solid var(--bd)}
.toast-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9998}
.toast-box{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--bg2);border:1px solid var(--bd);border-radius:14px;padding:24px 30px;text-align:center;z-index:9999;max-width:280px}
.toast-box p{font-size:.85rem;font-weight:600;margin-bottom:16px}
.toast-box button{padding:8px 30px;background:var(--sec);border:none;border-radius:8px;color:#fff;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit}
</style></head><body>

<div class="hdr"><button onclick="history.back()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button><h1>Pusat keamanan</h1><div style="width:32px"></div></div>

<div class="card">
<div class="row">
<div class="r-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="5"/><path d="M20 21a8 8 0 00-16 0"/></svg></div>
<div class="r-label">Nomor akun</div>
<div class="r-val"><?php echo $ph; ?></div>
</div>
<div class="row">
<div class="r-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6A19.79 19.79 0 012.12 4.11 2 2 0 014.11 2h3"/></svg></div>
<div class="r-label">Nomor ponsel</div>
<div class="r-val"><?php echo $ph; ?></div>
</div>
<div class="row">
<div class="r-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M22 7l-8.97 5.7a1.94 1.94 0 01-2.06 0L2 7"/></svg></div>
<div class="r-label">Surel</div>
<div class="r-val">Tidak terikat</div>
</div>
</div>

<div class="card">
<div class="row" onclick="openModal('pw')" style="cursor:pointer">
<div class="r-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 11-7.778 7.778 5.5 5.5 0 017.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg></div>
<div class="r-label">Sandi masuk</div>
<div class="r-status set">Dikonfigurasi</div>
<div class="r-arr"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></div>
</div>
<div class="row" onclick="openModal('pin')" style="cursor:pointer">
<div class="r-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg></div>
<div class="r-label">Sandi dana</div>
<div class="r-status <?php echo $hasPin?'set':'unset'; ?>"><?php echo $hasPin?'Dikonfigurasi':'Belum diatur'; ?></div>
<div class="r-arr"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></div>
</div>
</div>

<!-- Change Password Modal -->
<div class="modal" id="pwModal">
<div class="modal-box">
<h3>Ubah Sandi Masuk</h3>
<p>Masukkan sandi lama dan sandi baru Anda</p>
<div class="fg"><label>Sandi lama</label><input type="password" id="pwOld" placeholder="Sandi lama" style="letter-spacing:2px"></div>
<div class="fg"><label>Sandi baru</label><input type="password" id="pwNew" placeholder="Min 6 karakter" style="letter-spacing:2px"></div>
<div class="fg"><label>Konfirmasi sandi baru</label><input type="password" id="pwConf" placeholder="Ulangi sandi baru" style="letter-spacing:2px"></div>
<div class="btn-row"><button class="btn btn-sec" onclick="closeModal()">Batal</button><button class="btn btn-pri" onclick="changePw()">Simpan</button></div>
</div>
</div>

<!-- PIN Modal -->
<div class="modal" id="pinModal">
<div class="modal-box">
<h3 id="pinTitle"><?php echo $hasPin?'Ubah Sandi Dana':'Buat Sandi Dana'; ?></h3>
<p>PIN 6 angka untuk keamanan penarikan dana</p>
<?php if($hasPin): ?>
<div class="fg"><label>PIN lama</label><input type="password" id="pinOld" maxlength="6" inputmode="numeric" placeholder="6 angka"></div>
<?php endif; ?>
<div class="fg"><label>PIN baru</label><input type="password" id="pinNew" maxlength="6" inputmode="numeric" placeholder="6 angka"></div>
<div class="fg"><label>Konfirmasi PIN</label><input type="password" id="pinConf" maxlength="6" inputmode="numeric" placeholder="Ulangi 6 angka"></div>
<div class="btn-row"><button class="btn btn-sec" onclick="closeModal()">Batal</button><button class="btn btn-pri" onclick="savePin()">Simpan</button></div>
</div>
</div>

<script>
var HAS_PIN=<?php echo $hasPin?'true':'false'; ?>;
function openModal(t){document.getElementById(t+'Modal').classList.add('open')}
function closeModal(){document.querySelectorAll('.modal').forEach(function(m){m.classList.remove('open')})}
function changePw(){
  var o=document.getElementById('pwOld').value,n=document.getElementById('pwNew').value,c=document.getElementById('pwConf').value;
  if(!o)return showToast('Masukkan sandi lama');
  if(n.length<6)return showToast('Sandi baru minimal 6 karakter');
  if(n!==c)return showToast('Konfirmasi tidak cocok');
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'change_password',old_password:o,new_password:n})})
  .then(function(r){return r.json()}).then(function(d){
    if(d.ok){showToast('Sandi berhasil diubah');closeModal()}
    else showToast(d.error||'Gagal');
  });
}
function savePin(){
  var o=HAS_PIN?document.getElementById('pinOld').value:'';
  var n=document.getElementById('pinNew').value,c=document.getElementById('pinConf').value;
  if(HAS_PIN&&!o)return showToast('Masukkan PIN lama');
  if(!/^\d{6}$/.test(n))return showToast('PIN harus 6 angka');
  if(n!==c)return showToast('Konfirmasi PIN tidak cocok');
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'set_fund_pin',old_pin:o,new_pin:n})})
  .then(function(r){return r.json()}).then(function(d){
    if(d.ok){showToast('Sandi dana berhasil disimpan');HAS_PIN=true;closeModal();location.reload()}
    else showToast(d.error||'Gagal');
  });
}
function showToast(m){var ov=document.createElement('div');ov.className='toast-overlay';var bx=document.createElement('div');bx.className='toast-box';var p=document.createElement('p');p.textContent=m;var btn=document.createElement('button');btn.textContent='Oke';btn.onclick=function(){bx.remove();ov.remove()};bx.appendChild(p);bx.appendChild(btn);document.body.appendChild(ov);document.body.appendChild(bx);ov.onclick=function(){bx.remove();ov.remove()}}
</script>
<?php include 'includes/credit_notify.php'; ?>
</body></html>
