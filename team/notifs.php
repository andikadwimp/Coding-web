<?php
header('Content-Type: text/html; charset=UTF-8');
require_once 'layout.php';adminHeader('Pemberitahuan');
?>
<div id="pushStatus" style="padding:12px 14px;background:rgba(var(--sec-rgb,56,189,248),.08);border:1px solid rgba(var(--sec-rgb,56,189,248),.2);border-radius:10px;margin-bottom:14px;font-size:.72rem;color:var(--t2);display:flex;align-items:center;gap:10px">
  <svg viewBox="0 0 24 24" fill="none" stroke="var(--sec)" stroke-width="2" width="18" height="18"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
  <span id="pushCount">Memuat status push...</span>
</div>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
  <p style="font-size:.72rem;color:var(--t3)">Notifikasi tampil di dalam aplikasi & push ke HP user yang sudah install</p>
  <button class="btn btn-pri" onclick="openForm()">+ Kirim Notif</button>
</div>
<div id="notifList"></div>
<div id="statusMsg"></div>

<div id="formModal" style="display:none;position:fixed;inset:0;background:rgba(30,41,59,.5);z-index:200;align-items:center;justify-content:center;padding:16px">
<div style="background:#ffffff;border:1px solid var(--bd);border-radius:14px;padding:24px 20px;width:100%;max-width:500px;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(30,41,59,.25)">
  <h3 id="formTitle" style="font-size:.95rem;font-weight:700;margin-bottom:16px;color:var(--t)">Kirim Notifikasi</h3>
  <input type="hidden" id="fId">
  <div class="fg"><label>Judul Notifikasi</label><input id="fTitle" placeholder="Contoh: Bonus Spesial Hari Ini!"></div>
  <div class="fg"><label>Isi Pesan</label><textarea id="fBody" rows="4" placeholder="Isi pesan notifikasi..."></textarea></div>
  <div class="fg"><label>Link URL (opsional)</label><input id="fUrl" placeholder="/promo.php atau https://..." value="/dashboard.php"><div class="hint">User akan diarahkan ke URL ini saat klik notifikasi</div></div>
  <div class="fg">
    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.72rem;color:var(--t2);text-transform:none;letter-spacing:0">
      <input type="checkbox" id="fPush" checked style="width:auto"> Kirim juga sebagai Push Notification ke HP user
    </label>
  </div>
  <p style="font-size:.65rem;color:var(--t3);margin-bottom:14px">Push notif akan muncul di HP user walaupun app tertutup (selama PWA terinstall)</p>
  <div style="display:flex;gap:8px">
    <button class="btn btn-pri" onclick="saveNotif()" style="flex:1">Kirim Sekarang</button>
    <button class="btn btn-sec" onclick="closeForm()" style="flex:1">Batal</button>
  </div>
</div>
</div>

<script>
var API='../api/admin.php';
var allNotifs=[];

function api(body){return fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify(body)}).then(r=>r.json())}
function msg(txt,ok){var el=document.getElementById('statusMsg');el.innerHTML='<div class="msg '+(ok?'msg-ok':'msg-err')+'">'+txt+'</div>';setTimeout(function(){el.innerHTML=''},5000)}
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}

function checkPushStatus(){
  api({action:'vapid_status'}).then(function(d){
    if(d.ok){
      var txt='<b>'+d.subscribers+'</b> user sudah berlangganan push notification';
      if(!d.has_vapid)txt='VAPID belum siap — push belum bisa dikirim';
      document.getElementById('pushCount').innerHTML=txt;
    }
  });
}

function loadNotifs(){
  api({action:'get_notifs'}).then(function(d){
    if(!d.ok)return;
    allNotifs=d.notifs||[];
    var h='';
    if(!allNotifs.length){h='<div style="color:var(--t3);font-size:.8rem;padding:20px 0">Belum ada notifikasi yang dikirim.</div>';}
    allNotifs.forEach(function(n){
      h+='<div class="item-row" style="align-items:flex-start"><div class="ir-info">';
      h+='<div class="ir-title">'+esc(n.title)+'</div>';
      h+='<div class="ir-sub" style="line-height:1.6;max-width:500px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+esc((n.body||'').substring(0,80))+'</div>';
      h+='<div class="ir-sub" style="margin-top:2px;color:var(--t3)">'+fmtDate(n.created_at)+'</div>';
      h+='</div><div class="ir-actions">';
      h+='<button class="btn btn-sec btn-sm" onclick="editNotif('+n.id+')">Edit</button>';
      h+='<button class="btn btn-red btn-sm" onclick="delNotif('+n.id+')">×</button>';
      h+='</div></div>';
    });
    document.getElementById('notifList').innerHTML=h;
  });
}

function fmtDate(dt){if(!dt)return'-';return dt.substring(0,16).replace('T',' ');}

function openForm(n){
  document.getElementById('formTitle').textContent=n?'Edit Notifikasi':'Kirim Notifikasi';
  document.getElementById('fId').value=n?n.id:'';
  document.getElementById('fTitle').value=n?n.title:'';
  document.getElementById('fBody').value=n?n.body:'';
  document.getElementById('fUrl').value=n?(n.url||'/dashboard.php'):'/dashboard.php';
  document.getElementById('fPush').checked=!n;
  document.getElementById('formModal').style.display='flex';
}
function editNotif(id){var n=allNotifs.find(function(x){return x.id==id});if(n)openForm(n);}
function closeForm(){document.getElementById('formModal').style.display='none'}

function saveNotif(){
  var title=document.getElementById('fTitle').value.trim();
  var body=document.getElementById('fBody').value.trim();
  var url=document.getElementById('fUrl').value.trim()||'/dashboard.php';
  var pushIt=document.getElementById('fPush').checked;
  if(!title)return msg('Judul wajib diisi',false);
  var payload={action:'save_notif',title:title,body:body,url:url};
  var id=document.getElementById('fId').value;if(id)payload.id=parseInt(id);
  api(payload).then(function(d){
    if(!d.ok){msg(d.error||'Gagal simpan',false);return}
    if(pushIt&&!id){
      msg('Tersimpan. Mengirim push...',true);
      api({action:'push_broadcast',title:title,body:body,url:url}).then(function(p){
        if(p.ok){msg('Push terkirim: '+p.sent+'/'+p.total+' (gagal: '+p.failed+')',true);checkPushStatus();}
        else msg('Push error: '+(p.error||'unknown'),false);
        closeForm();loadNotifs();
      });
    }else{closeForm();loadNotifs();msg(id?'Diperbarui!':'Tersimpan!',true);}
  });
}

function delNotif(id){
  if(!confirm('Hapus notifikasi ini?'))return;
  api({action:'delete_notif',id:id}).then(function(d){if(d.ok){loadNotifs();msg('Dihapus!',true);}});
}

loadNotifs();checkPushStatus();
</script>
<?php adminFooter(); ?>
