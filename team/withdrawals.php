<?php
header('Content-Type: text/html; charset=UTF-8');
require_once 'layout.php';adminHeader('Withdrawals');
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
  <div style="display:flex;gap:6px" id="filterTabs">
    <button class="btn btn-sec ftab on" onclick="setFilter('all',this)">Semua</button>
    <button class="btn btn-sec ftab" onclick="setFilter('pending',this)">Pending</button>
    <button class="btn btn-sec ftab" onclick="setFilter('approved',this)">Disetujui</button>
    <button class="btn btn-sec ftab" onclick="setFilter('rejected',this)">Ditolak</button>
  </div>
  <button class="btn btn-sec" onclick="load()">↺ Refresh</button>
</div>
<div id="statusMsg"></div>

<!-- Reject note modal -->
<div id="rejectModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:200;align-items:center;justify-content:center">
<div style="background:var(--s);border:1px solid var(--bd);border-radius:14px;padding:24px 20px;width:calc(100% - 40px);max-width:360px">
  <h3 style="font-size:.9rem;font-weight:700;margin-bottom:14px">Tolak Penarikan</h3>
  <input type="hidden" id="rjId">
  <div class="fg"><label>Alasan Penolakan</label><input id="rjNote" placeholder="Contoh: Data rekening tidak valid"></div>
  <div style="display:flex;gap:8px">
    <button class="btn btn-red" onclick="confirmReject()" style="flex:1">Tolak & Refund</button>
    <button class="btn btn-sec" onclick="document.getElementById('rejectModal').style.display='none'" style="flex:1">Batal</button>
  </div>
</div>
</div>

<div id="wdTable"></div>

<script>
var API='../api/admin.php';
var allWds=[];var curFilter='all';

function api(body){return fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify(body)}).then(r=>r.json())}
function msg(txt,ok){var el=document.getElementById('statusMsg');el.innerHTML='<div class="msg '+(ok?'msg-ok':'msg-err')+'">'+txt+'</div>';setTimeout(()=>el.innerHTML='',4000)}
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}

function setFilter(f,btn){
  curFilter=f;
  document.querySelectorAll('.ftab').forEach(t=>t.classList.remove('on'));
  btn.classList.add('on');
  render();
}

function load(){
  document.getElementById('wdTable').innerHTML='<div style="color:var(--t3);font-size:.78rem;padding:20px 0">Memuat...</div>';
  api({action:'get_withdrawals'}).then(d=>{
    if(!d.ok){msg(d.error||'Gagal',false);return;}
    allWds=d.withdrawals||[];
    render();
  });
}

function render(){
  var list=curFilter==='all'?allWds:allWds.filter(w=>w.status===curFilter);
  // Counts badge
  var pending=allWds.filter(w=>w.status==='pending').length;
  if(pending>0){
    var btn=document.querySelector('.ftab:nth-child(2)');
    if(btn)btn.textContent='Pending ('+pending+')';
  }

  if(!list.length){
    document.getElementById('wdTable').innerHTML='<div style="color:var(--t3);font-size:.8rem;padding:20px 0">Tidak ada data '+curFilter+'.</div>';
    return;
  }

  var h='<div style="overflow-x:auto"><table class="tbl"><thead><tr>';
  h+='<th>ID</th><th>User</th><th>Jumlah</th><th>Bank</th><th>Rekening</th><th>Status</th><th>Waktu</th><th style="min-width:140px">Aksi</th>';
  h+='</tr></thead><tbody>';

  list.forEach(w=>{
    var sc=w.status==='approved'?'var(--green)':w.status==='pending'?'var(--pri)':'var(--red)';
    var sl=w.status==='approved'?'Disetujui':w.status==='pending'?'Menunggu':'Ditolak';
    h+='<tr id="wdRow_'+w.id+'">';
    h+='<td style="font-size:.68rem">#'+w.id+'</td>';
    if(w.user_id){
      h+='<td><a href="users.php?uid='+w.user_id+'" style="color:var(--pri);font-weight:700;text-decoration:none;border-bottom:1px dashed var(--pri)" title="Lihat profil user">'+esc(w.username||'-')+'</a>'+(w.phone?'<br><span style="font-size:.6rem;color:var(--t3)">'+esc(w.phone)+'</span>':'')+'</td>';
    }else{
      h+='<td><b>'+esc(w.username||'-')+'</b>'+(w.phone?'<br><span style="font-size:.6rem;color:var(--t3)">'+esc(w.phone)+'</span>':'')+'</td>';
    }
    h+='<td style="font-weight:800;color:var(--pri)">Rp '+Number(w.amount).toLocaleString('id')+'</td>';
    h+='<td>'+esc(w.bank_name)+'</td>';
    h+='<td style="font-size:.68rem"><b>'+esc(w.acc_name)+'</b><br>'+esc(w.acc_number)+'</td>';
    h+='<td><span style="color:'+sc+';font-weight:700">'+sl+'</span>';
    if(w.admin_note)h+='<br><span style="font-size:.6rem;color:var(--t3)">'+esc(w.admin_note)+'</span>';
    h+='</td>';
    h+='<td style="font-size:.6rem">'+esc((w.created_at||'').substring(0,16).replace('T',' '))+'</td>';
    h+='<td>';
    if(w.status==='pending'){
      h+='<button class="btn btn-sec btn-sm" onclick="approve('+w.id+')" style="margin-right:3px">✓ OK</button>';
      h+='<button class="btn btn-red btn-sm" onclick="openReject('+w.id+')">✕ Tolak</button>';
    } else {
      h+='<span style="font-size:.65rem;color:var(--t3)">'+(w.processed_at||'').substring(0,16)+'</span>';
    }
    h+='</td>';
    h+='</tr>';
  });
  h+='</tbody></table></div>';
  document.getElementById('wdTable').innerHTML=h;
}

function approve(id){
  if(!confirm('Setujui penarikan #'+id+'?'))return;
  api({action:'process_withdraw',id,status:'approved',note:''}).then(d=>{
    if(d.ok){msg('Disetujui!',true);load();}
    else msg(d.error||'Gagal',false);
  });
}

function openReject(id){
  document.getElementById('rjId').value=id;
  document.getElementById('rjNote').value='';
  document.getElementById('rejectModal').style.display='flex';
  setTimeout(()=>document.getElementById('rjNote').focus(),100);
}

function confirmReject(){
  var id=document.getElementById('rjId').value;
  var note=document.getElementById('rjNote').value.trim()||'Ditolak oleh admin';
  api({action:'process_withdraw',id:parseInt(id),status:'rejected',note}).then(d=>{
    document.getElementById('rejectModal').style.display='none';
    if(d.ok){msg('Ditolak & saldo dikembalikan!',true);load();}
    else msg(d.error||'Gagal',false);
  });
}

load();
// Auto-refresh every 30s jika ada pending
setInterval(function(){if(allWds.some(w=>w.status==='pending'))load();},30000);
</script>
<?php adminFooter(); ?>
