<?php
header('Content-Type: text/html; charset=UTF-8');
require_once 'layout.php';adminHeader('Deposits');
?>
<style>
.ftab.on{background:var(--sec)!important;color:#fff!important;border-color:var(--sec)!important}
</style>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px">
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <button class="btn btn-sec ftab" onclick="setFilter('all',this)">Semua</button>
    <button class="btn btn-sec ftab on" onclick="setFilter('paid',this)">✓ Sukses</button>
    <button class="btn btn-sec ftab" onclick="setFilter('pending',this)">Pending</button>
    <button class="btn btn-sec ftab" onclick="setFilter('expired',this)">Expired</button>
    <button class="btn btn-sec ftab" onclick="setFilter('failed',this)">Failed</button>
  </div>
  <div style="display:flex;gap:6px;flex-wrap:wrap">
    <button class="btn btn-red" onclick="findStuck()" title="Deteksi deposit 'paid' tapi saldo belum masuk">🔧 Recovery Saldo</button>
    <button class="btn btn-sec" onclick="findUnpaid()" title="Cek deposit pending yang sebenernya udah dibayar (callback hilang)">🔍 Cek SQX</button>
    <button class="btn btn-sec" onclick="viewCallbackLog()" title="Lihat log callback dari SquadOnyx (debug)">📋 Log CB</button>
    <button class="btn btn-sec" onclick="load()">↺ Refresh</button>
  </div>
</div>
<div style="position:relative;margin-bottom:14px">
  <input type="text" id="depSearch" placeholder="Cari TX ID / ID Akun / HP / Username / Nominal..." oninput="onSearchInput()" style="width:100%;padding:10px 38px 10px 38px;background:var(--bg2);border:1px solid var(--bd);border-radius:8px;color:var(--t);font-size:.78rem;font-family:inherit;box-sizing:border-box">
  <svg viewBox="0 0 24 24" fill="none" stroke="var(--t3)" stroke-width="2" width="16" height="16" style="position:absolute;left:12px;top:50%;transform:translateY(-50%);pointer-events:none"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
  <button onclick="clearSearch()" id="depSearchClr" style="position:absolute;right:8px;top:50%;transform:translateY(-50%);background:rgba(255,255,255,.06);border:none;width:24px;height:24px;border-radius:6px;color:var(--t3);cursor:pointer;display:none;align-items:center;justify-content:center" title="Hapus pencarian">&times;</button>
</div>
<div id="recoveryPanel" style="display:none;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.25);border-radius:10px;padding:14px;margin-bottom:14px">
  <div style="font-weight:700;font-size:.82rem;margin-bottom:8px;color:#ef4444">🔧 Deposit Stuck (paid tapi saldo belum masuk)</div>
  <div id="stuckList" style="font-size:.72rem;color:var(--t2);line-height:1.6"></div>
  <button class="btn btn-red" onclick="recreditAll()" style="margin-top:10px" id="fixAllBtn">⚡ Fix Semua Sekaligus</button>
  <button class="btn btn-sec" onclick="document.getElementById('recoveryPanel').style.display='none'" style="margin-top:10px">Tutup</button>
</div>
<div id="statusMsg"></div>
<div id="depTable"><div style="color:var(--t3);font-size:.78rem;padding:20px 0">Memuat...</div></div>

<script>
var API='../api/admin.php';
var allDeps=[];var curFilter='paid';

function api(body){return fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify(body)}).then(r=>r.json())}
function msg(txt,ok){var el=document.getElementById('statusMsg');el.innerHTML='<div class="msg '+(ok?'msg-ok':'msg-err')+'">'+txt+'</div>';setTimeout(()=>el.innerHTML='',4000)}

function viewCallbackLog(){
  var p=document.getElementById('recoveryPanel');var l=document.getElementById('stuckList');
  l.innerHTML='<div style="color:var(--t2)">⏳ Loading log...</div>';
  p.style.display='block';
  document.getElementById('fixAllBtn').style.display='none';
  fetch('../api/deposit.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'callback_log'})})
  .then(r=>r.json()).then(d=>{
    if(!d.ok){l.innerHTML='<span style="color:#ef4444">Error: '+(d.error||'gagal')+'</span>';return;}
    var h='<div style="margin-bottom:10px;padding:10px;background:rgba(56,189,248,.08);border:1px solid rgba(56,189,248,.25);border-radius:6px">';
    h+='<div style="font-size:.75rem;color:var(--t2);margin-bottom:4px"><b>URL Callback (set ini di SquadOnyx panel):</b></div>';
    h+='<div style="font-family:monospace;font-size:.7rem;color:var(--pri);word-break:break-all;background:rgba(0,0,0,.3);padding:6px;border-radius:4px">'+(d.callback_url||'(unknown)')+'</div>';
    h+='<button onclick="navigator.clipboard.writeText(\''+(d.callback_url||'')+'\');msg(\'Copied!\',1)" style="margin-top:6px;padding:4px 10px;background:var(--pri);color:#fff;border:none;border-radius:5px;font-size:.7rem;cursor:pointer">Copy URL</button>';
    h+='</div>';
    h+='<div style="display:flex;justify-content:space-between;margin-bottom:8px">';
    h+='<span style="font-size:.72rem;color:var(--t3)">Total log: '+(d.total_lines||0)+' baris (200 terakhir)</span>';
    h+='<button onclick="clearCallbackLog()" style="padding:4px 10px;background:rgba(239,68,68,.15);color:#ef4444;border:1px solid rgba(239,68,68,.3);border-radius:5px;font-size:.7rem;cursor:pointer">Clear Log</button>';
    h+='</div>';
    var log=d.log||'(kosong)';
    h+='<pre style="max-height:380px;overflow-y:auto;background:#0a0a0a;color:#a0e8a0;padding:10px;border-radius:6px;font-size:.66rem;line-height:1.5;white-space:pre-wrap;word-break:break-all">'+escapeHtml(log)+'</pre>';
    if(!log||log.indexOf('CB tx=')===-1){
      h+='<div style="margin-top:10px;padding:10px;background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);border-radius:6px;font-size:.72rem;color:var(--t2)"><b style="color:#f59e0b">⚠ TIDAK ADA CALLBACK DARI SQX!</b><br>Kemungkinan callback URL belum diset di panel SquadOnyx. Set URL di atas, lalu test deposit lagi.</div>';
    }
    l.innerHTML=h;
  }).catch(e=>{l.innerHTML='<span style="color:#ef4444">Error: '+e.message+'</span>'});
}

function clearCallbackLog(){
  if(!confirm('Hapus log callback?'))return;
  fetch('../api/deposit.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'clear_callback_log'})})
  .then(r=>r.json()).then(d=>{if(d.ok){msg('Log dihapus',1);viewCallbackLog();}else msg('Gagal: '+(d.error||''),0)});
}
function escapeHtml(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}

function findUnpaid(){
  var p=document.getElementById('recoveryPanel');var l=document.getElementById('stuckList');
  l.innerHTML='<div style="color:var(--t2)">⏳ Mengecek sampai 200 deposit pending ke SquadOnyx... (bisa 30-60 detik)</div>';
  p.style.display='block';
  document.getElementById('fixAllBtn').style.display='none';
  api({action:'deposit_find_unpaid'}).then(d=>{
    if(!d.ok){l.innerHTML='<span style="color:#ef4444">Error: '+(d.error||'gagal')+'</span>';return;}
    if(!d.recovered){
      l.innerHTML='<span style="color:#4ade80">✓ Sudah dicek '+d.checked+' deposit pending ('+d.elapsed+'s). Semua status di SQX masih pending/expired. Tidak ada yang perlu di-recover.</span>';
      return;
    }
    var h='<b style="color:#4ade80">✓ AUTO-RECOVERED '+d.recovered+' deposit!</b><br>';
    h+='<span style="color:var(--t3);font-size:.7rem">Dicek '+d.checked+' dari '+d.total_pending+' pending ('+d.elapsed+'s). '+d.recovered+' di antaranya UDAH PAID di SQX tapi callback hilang/gagal. Saldo sudah otomatis di-credit ke user.</span><br><br>';
    h+='<div style="max-height:280px;overflow-y:auto;border:1px solid rgba(74,222,128,.2);border-radius:6px;padding:8px;background:rgba(0,0,0,.2)">';
    d.found.forEach(s=>{
      h+='<div style="padding:6px 8px;border-bottom:1px solid rgba(255,255,255,.05);font-size:.7rem">';
      h+='✓ <b>'+esc(s.username||'-')+'</b> ('+(s.phone||'-')+') • <span style="color:var(--sec)">'+esc(s.tx_id)+'</span> • '+fmtRp(s.nominal);
      if(s.bonus>0)h+=' + bonus '+fmtRp(s.bonus);
      h+='</div>';
    });
    h+='</div>';
    if(d.checked<d.total_pending){
      h+='<div style="margin-top:8px;padding:8px;background:rgba(251,191,36,.1);border-radius:6px;font-size:.7rem;color:var(--t2)">Masih ada '+(d.total_pending-d.checked)+' pending belum dicek. Klik Cek SQX lagi untuk lanjutkan scan.</div>';
    }
    l.innerHTML=h;
    setTimeout(load,500);
  }).catch(e=>{l.innerHTML='<span style="color:#ef4444">Gagal koneksi</span>'});
}

function findStuck(){
  var p=document.getElementById('recoveryPanel');var l=document.getElementById('stuckList');
  l.innerHTML='Mendeteksi...';p.style.display='block';
  api({action:'deposit_find_stuck'}).then(d=>{
    if(!d.ok){l.innerHTML='<span style="color:#ef4444">Error: '+(d.error||'gagal')+'</span>';return;}
    if(!d.count){l.innerHTML='<span style="color:#4ade80">✓ Tidak ada deposit stuck. Semua sudah ter-credit dengan benar.</span>';document.getElementById('fixAllBtn').style.display='none';return;}
    document.getElementById('fixAllBtn').style.display='inline-block';
    var h='<b>Ditemukan '+d.count+' deposit yang status-nya <code>paid</code> tapi saldo BELUM ter-credit:</b><br><br>';
    h+='<div style="max-height:240px;overflow-y:auto;border:1px solid rgba(239,68,68,.2);border-radius:6px;padding:8px;background:rgba(0,0,0,.2)">';
    d.stuck.forEach(s=>{
      h+='<div style="padding:6px 8px;border-bottom:1px solid rgba(255,255,255,.05);font-size:.7rem">';
      h+='<b>'+esc(s.username||'-')+'</b> ('+(s.phone||'-')+') • <span style="color:var(--sec)">'+esc(s.tx_id)+'</span> • '+fmtRp(s.nominal);
      if(parseInt(s.bonus_amount)>0)h+=' + bonus '+fmtRp(s.bonus_amount);
      h+=' • <span style="color:var(--t3)">paid: '+(s.paid_at||'-').substring(0,16)+'</span>';
      h+=' • saldo skrg: '+fmtRp(s.balance);
      h+='</div>';
    });
    h+='</div>';
    l.innerHTML=h;
  }).catch(e=>{l.innerHTML='<span style="color:#ef4444">Network error: '+e+'</span>';});
}

function recreditAll(){
  if(!confirm('Credit ulang SEMUA deposit stuck (max 50)?\nIni akan menambahkan saldo ke user yang depositnya belum ter-credit.\n\nLanjut?'))return;
  var btn=document.getElementById('fixAllBtn');btn.disabled=true;btn.textContent='Memproses...';
  api({action:'deposit_recredit_bulk'}).then(d=>{
    btn.disabled=false;btn.textContent='⚡ Fix Semua Sekaligus';
    if(!d.ok){msg('Gagal: '+(d.error||'Unknown'),false);return;}
    msg('✓ Selesai. Fixed: '+d.fixed+' • Failed: '+d.failed,true);
    findStuck(); // refresh
    load(); // refresh deposit list
  }).catch(e=>{btn.disabled=false;btn.textContent='⚡ Fix Semua Sekaligus';msg('Network error',false);});
}
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}
function fmtRp(v){return 'Rp '+Number(v||0).toLocaleString('id')}

function setFilter(f,btn){
  curFilter=f;
  document.querySelectorAll('.ftab').forEach(t=>t.classList.remove('on'));
  btn.classList.add('on');
  render();
}

function load(keyword){
  document.getElementById('depTable').innerHTML='<div style="color:var(--t3);font-size:.78rem;padding:20px 0">Memuat...</div>';
  var payload={action:'get_deposits'};
  if(keyword)payload.search=keyword;
  api(payload).then(d=>{
    if(!d.ok){msg(d.error||'Gagal',false);return;}
    allDeps=d.deposits||[];
    // Update badge counts
    var counts={all:allDeps.length,paid:0,pending:0,expired:0,failed:0};
    allDeps.forEach(x=>{if(counts[x.status]!==undefined)counts[x.status]++});
    var tabs=[['all','Semua'],['paid','✓ Sukses'],['pending','Pending'],['expired','Expired'],['failed','Failed']];
    document.querySelectorAll('.ftab').forEach((btn,i)=>{
      btn.textContent=tabs[i][1]+(counts[tabs[i][0]]>0?' ('+counts[tabs[i][0]]+')':'');
    });
    render();
  });
}

// Search handler: debounce 350ms lalu reload dari server (biar cari semua history, bukan cuma 100 terbaru)
var searchDebTimer=null;
function onSearchInput(){
  var q=(document.getElementById('depSearch').value||'').trim();
  document.getElementById('depSearchClr').style.display=q?'flex':'none';
  clearTimeout(searchDebTimer);
  searchDebTimer=setTimeout(function(){load(q);},350);
}
function clearSearch(){
  document.getElementById('depSearch').value='';
  document.getElementById('depSearch').readOnly=false;
  document.getElementById('depSearchClr').style.display='none';
  window._uidFilter=null;
  // Hapus URL param biar refresh ga restore uid filter
  if(window.history.replaceState){
    window.history.replaceState({},'',window.location.pathname);
  }
  load();
}

function render(){
  var list=curFilter==='all'?allDeps:allDeps.filter(d=>d.status===curFilter);
  // Search udah handled di backend (onSearchInput -> load(keyword))
  var q=(document.getElementById('depSearch').value||'').trim().toLowerCase();

  if(!list.length){
    document.getElementById('depTable').innerHTML='<div style="color:var(--t3);font-size:.8rem;padding:20px 0">Tidak ada data'+(q?' untuk pencarian "'+q+'"':( curFilter!=='all'?' dengan status '+curFilter:''))+'.</div>';
    return;
  }

  // Summary
  var total=list.reduce((a,d)=>a+parseInt(d.nominal||0),0);
  var bonus=list.reduce((a,d)=>a+parseInt(d.bonus_amount||0),0);
  var h='<div style="display:flex;gap:8px;margin-bottom:12px;flex-wrap:wrap">';
  h+='<div style="background:var(--bg2);border:1px solid var(--bd);border-radius:8px;padding:8px 14px;font-size:.72rem"><span style="color:var(--t3)">Transaksi: </span><b>'+list.length+'</b></div>';
  if(curFilter==='paid'||curFilter==='all')h+='<div style="background:rgba(74,222,128,.06);border:1px solid rgba(74,222,128,.2);border-radius:8px;padding:8px 14px;font-size:.72rem"><span style="color:var(--t3)">Income: </span><b style="color:var(--green)">'+fmtRp(total)+'</b></div>';
  if(bonus>0)h+='<div style="background:rgba(var(--pri-rgb,56,189,248),.06);border:1px solid rgba(var(--pri-rgb,56,189,248),.2);border-radius:8px;padding:8px 14px;font-size:.72rem"><span style="color:var(--t3)">Bonus: </span><b style="color:var(--pri)">+'+fmtRp(bonus)+'</b></div>';
  h+='</div>';

  h+='<div style="overflow-x:auto"><table class="tbl"><thead><tr>';
  h+='<th>TX ID</th><th>User</th><th>Nominal</th><th>Bonus</th><th>Metode</th>';
  if(curFilter==='all'||curFilter==='paid')h+='<th>Dibayar</th>';
  if(curFilter==='all'||curFilter==='pending')h+='<th>Expired</th>';
  h+='<th>Status</th><th style="min-width:180px">Aksi</th>';
  h+='</tr></thead><tbody>';

  list.forEach(d=>{
    var sc=d.status==='paid'?'var(--green)':d.status==='pending'?'var(--pri)':d.status==='expired'?'var(--orange,#f59e0b)':'var(--t3)';
    h+='<tr>';
    h+='<td style="font-size:.6rem;font-family:monospace;max-width:120px;overflow:hidden;text-overflow:ellipsis">'+esc(d.tx_id)+'</td>';
    // Username clickable → open user detail
    if(d.user_id){
      h+='<td><a href="users.php?uid='+d.user_id+'" style="color:var(--pri);font-weight:700;text-decoration:none;border-bottom:1px dashed var(--pri)" title="Lihat profil & riwayat user">'+esc(d.username||'-')+'</a>'+(d.display_id?'<div style="font-size:.55rem;color:var(--t3);font-family:monospace;margin-top:2px">ID: '+esc(d.display_id)+'</div>':'')+'</td>';
    }else{
      h+='<td><b>'+esc(d.username||'-')+'</b>'+(d.display_id?'<div style="font-size:.55rem;color:var(--t3);font-family:monospace;margin-top:2px">ID: '+esc(d.display_id)+'</div>':'')+'</td>';
    }
    h+='<td style="font-weight:700;white-space:nowrap">'+fmtRp(d.nominal)+'</td>';
    h+='<td style="color:var(--pri)">'+((d.bonus_amount||0)>0?'+'+fmtRp(d.bonus_amount):'-')+'</td>';
    h+='<td>'+esc((d.method||d.type||'').toUpperCase())+'</td>';
    if(curFilter==='all'||curFilter==='paid')h+='<td style="font-size:.65rem">'+esc((d.paid_at||'').substring(0,16))+'</td>';
    if(curFilter==='all'||curFilter==='pending')h+='<td style="font-size:.65rem">'+esc((d.expires_at||'').substring(0,16))+'</td>';
    h+='<td><span style="color:'+sc+';font-weight:700">'+d.status+'</span></td>';
    // ═══ AKSI per status ═══
    h+='<td style="white-space:nowrap">';
    if(d.status==='pending'){
      h+='<button class="btn btn-sec btn-sm" onclick="adminAction('+d.id+',\'approve\',\''+esc(d.username||'')+'\',\''+fmtRp(d.nominal)+'\')" title="Approve & credit saldo">✓ Approve</button> ';
      h+='<button class="btn btn-sec btn-sm" onclick="adminAction('+d.id+',\'expire\',\''+esc(d.username||'')+'\',\''+fmtRp(d.nominal)+'\')" title="Mark expired tanpa credit">⏱ Expire</button> ';
      h+='<button class="btn btn-red btn-sm" onclick="adminAction('+d.id+',\'reject\',\''+esc(d.username||'')+'\',\''+fmtRp(d.nominal)+'\')" title="Tolak deposit">✗ Reject</button>';
    } else if(d.status==='expired' || d.status==='failed' || d.status==='cancelled'){
      h+='<button class="btn btn-sec btn-sm" onclick="adminAction('+d.id+',\'approve\',\''+esc(d.username||'')+'\',\''+fmtRp(d.nominal)+'\')" title="Force credit (jaga-jaga user trouble)">✓ Force Approve</button> ';
      h+='<button class="btn btn-sec btn-sm" onclick="adminAction('+d.id+',\'revert\',\''+esc(d.username||'')+'\',\''+fmtRp(d.nominal)+'\')" title="Balikin ke pending + extend 12 jam">↺ Revert Pending</button>';
    } else {
      h+='<span style="color:var(--t3);font-size:.62rem">—</span>';
    }
    h+='</td>';
    h+='</tr>';
  });
  h+='</tbody></table></div>';
  document.getElementById('depTable').innerHTML=h;
}

function adminAction(id,act,user,nominal){
  var confirmMsg='',apiAction='',extraData={};
  if(act==='approve'){
    confirmMsg='Approve deposit #'+id+'?\nUser: '+user+' • '+nominal+'\n\nSaldo akan ditambahkan ke user.';
    apiAction='approve_deposit';
  } else if(act==='expire'){
    confirmMsg='Mark expired deposit #'+id+'?\nUser: '+user+' • '+nominal+'\n\nStatus akan jadi expired TANPA credit.';
    apiAction='force_expire_deposit';
  } else if(act==='revert'){
    confirmMsg='Revert deposit #'+id+' ke pending?\nUser: '+user+' • '+nominal+'\n\nExpires_at akan di-extend 12 jam, user bisa bayar lagi.';
    apiAction='revert_deposit';
  } else if(act==='reject'){
    var reason=prompt('Alasan reject deposit #'+id+' ('+user+' • '+nominal+'):','Ditolak admin');
    if(reason===null)return;
    confirmMsg='Reject deposit #'+id+' dengan alasan: "'+reason+'"?';
    apiAction='reject_deposit';
    extraData.note=reason;
  }
  if(!confirm(confirmMsg))return;
  api(Object.assign({action:apiAction,id:id},extraData)).then(d=>{
    if(d.ok){
      msg('Sukses — deposit #'+id+' ter-'+act,true);
      load();
    } else msg(d.error||'Gagal',false);
  }).catch(e=>msg('Network error',false));
}

// Backward compat (kalau ada call lama)
function approveDeposit(id){adminAction(id,'approve','','');}

// Cek URL param
// ?uid=X      → exact match by user_id (dari tombol Riwayat di team/users.php)
// ?search=X   → search by keyword (manual)
(function(){
  try{
    var params=new URLSearchParams(window.location.search);
    var uid=parseInt(params.get('uid')||0);
    if(uid>0){
      // Exact match by uid — simpan globally biar load() pake param uid
      window._uidFilter=uid;
      var uname=params.get('uname')||'';
      if(uname){
        document.getElementById('depSearch').value='Riwayat: '+uname+' (ID internal #'+uid+')';
        document.getElementById('depSearch').readOnly=true;
        document.getElementById('depSearchClr').style.display='flex';
      }
      loadByUid(uid);
      return;
    }
    var kw=params.get('search')||params.get('user')||'';
    if(kw){
      document.getElementById('depSearch').value=kw;
      document.getElementById('depSearchClr').style.display='flex';
      load(kw);
      return;
    }
  }catch(e){}
  load();
})();
function loadByUid(uid){
  document.getElementById('depTable').innerHTML='<div style="color:var(--t3);font-size:.78rem;padding:20px 0">Memuat...</div>';
  api({action:'get_deposits',uid:uid}).then(d=>{
    if(!d.ok){msg(d.error||'Gagal',false);return;}
    allDeps=d.deposits||[];
    var counts={all:allDeps.length,paid:0,pending:0,expired:0,failed:0};
    allDeps.forEach(x=>{if(counts[x.status]!==undefined)counts[x.status]++});
    var tabs=[['all','Semua'],['paid','✓ Sukses'],['pending','Pending'],['expired','Expired'],['failed','Failed']];
    document.querySelectorAll('.ftab').forEach((btn,i)=>{
      btn.textContent=tabs[i][1]+(counts[tabs[i][0]]>0?' ('+counts[tabs[i][0]]+')':'');
    });
    render();
  });
}
setInterval(function(){
  if(allDeps.some(d=>d.status==='pending')){
    if(window._uidFilter)loadByUid(window._uidFilter);
    else{var kw=(document.getElementById('depSearch').value||'').trim();load(kw||null);}
  }
},30000);
</script>
<?php adminFooter(); ?>
