<?php
header('Content-Type: text/html; charset=UTF-8');
require_once 'layout.php';adminHeader('Kode Redeem');
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
  <p style="font-size:.72rem;color:var(--t3)">Kode yang bisa ditukar user untuk mendapatkan saldo</p>
  <button class="btn btn-pri" onclick="openForm()">+ Buat Kode</button>
</div>
<div id="redeemList"></div>
<div id="statusMsg"></div>

<div id="formModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:200;align-items:center;justify-content:center">
<div style="background:var(--s);border:1px solid var(--bd);border-radius:14px;padding:24px 20px;width:calc(100% - 40px);max-width:420px">
  <h3 id="formTitle" style="font-size:.95rem;font-weight:700;margin-bottom:16px">Buat Kode Redeem</h3>
  <input type="hidden" id="fId">
  <div class="fg"><label>Kode</label>
    <div style="display:flex;gap:6px">
      <input id="fCode" placeholder="KODE123" style="text-transform:uppercase;font-family:monospace;font-weight:800;font-size:1rem" oninput="this.value=this.value.toUpperCase()">
      <button class="btn btn-sec" onclick="genCode()">Generate</button>
    </div>
  </div>
  <div class="fg-row">
    <div class="fg"><label>Bonus (Rp)</label><input id="fAmount" type="number" placeholder="10000" value="10000"></div>
    <div class="fg"><label>Max Pakai</label><input id="fMaxUses" type="number" placeholder="100" value="100"></div>
  </div>
  <div class="fg"><label>Expired At (kosong = tidak ada)</label><input id="fExpires" type="datetime-local"></div>
  <div class="fg"><label>Status</label><select id="fStatus"><option value="active">Active</option><option value="inactive">Inactive</option><option value="expired">Expired</option></select></div>
  <div style="display:flex;gap:8px;margin-top:4px">
    <button class="btn btn-pri" onclick="saveRedeem()" style="flex:1">Simpan</button>
    <button class="btn btn-sec" onclick="closeForm()" style="flex:1">Batal</button>
  </div>
</div>
</div>

<script>
var API='../api/admin.php';
var allCodes=[];

function api(body){return fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify(body)}).then(r=>r.json())}
function msg(txt,ok){var el=document.getElementById('statusMsg');el.innerHTML='<div class="msg '+(ok?'msg-ok':'msg-err')+'">'+txt+'</div>';setTimeout(()=>el.innerHTML='',3000)}

function genCode(){
  var chars='ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
  var code='';for(var i=0;i<8;i++)code+=chars[Math.floor(Math.random()*chars.length)];
  document.getElementById('fCode').value=code;
}

function loadRedeems(){
  api({action:'get_redeems'}).then(d=>{
    if(!d.ok)return;
    allCodes=d.codes||[];
    var h='';
    if(!allCodes.length){h='<div style="color:var(--t3);font-size:.8rem;padding:20px 0">Belum ada kode redeem.</div>';}

    // Summary bar
    var active=allCodes.filter(c=>c.status==='active').length;
    h+='<div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">';
    h+='<div style="background:var(--bg2);border:1px solid var(--bd);border-radius:8px;padding:8px 14px;font-size:.72rem"><span style="color:var(--t3)">Total: </span><b>'+allCodes.length+'</b></div>';
    h+='<div style="background:var(--bg2);border:1px solid rgba(74,222,128,.2);border-radius:8px;padding:8px 14px;font-size:.72rem"><span style="color:var(--t3)">Aktif: </span><b style="color:var(--green)">'+active+'</b></div>';
    h+='</div>';

    h+='<div style="overflow-x:auto"><table class="tbl"><thead><tr><th>Kode</th><th>Bonus</th><th>Dipakai</th><th>Max</th><th>Expired</th><th>Status</th><th></th></tr></thead><tbody>';
    allCodes.forEach(c=>{
      var pct=c.max_uses>0?Math.round(c.used_count/c.max_uses*100):0;
      h+='<tr>';
      h+='<td><b style="font-family:monospace;font-size:.85rem;color:var(--pri)">'+c.code+'</b></td>';
      h+='<td style="font-weight:700">'+Number(c.amount).toLocaleString('id')+'</td>';
      h+='<td>'+c.used_count+' <span style="font-size:.6rem;color:'+(pct>=100?'var(--red)':'var(--t3)')+'">'+pct+'%</span></td>';
      h+='<td>'+c.max_uses+'</td>';
      h+='<td style="font-size:.65rem">'+(c.expires_at?c.expires_at.substring(0,16):'—')+'</td>';
      h+='<td><span style="color:'+(c.status==='active'?'var(--green)':c.status==='expired'?'var(--t3)':'var(--red)')+'">'+c.status+'</span></td>';
      h+='<td style="white-space:nowrap"><button class="btn btn-sec btn-sm" onclick="editCode('+c.id+')">Edit</button> ';
      h+='<button class="btn btn-red btn-sm" onclick="delCode('+c.id+')">×</button></td>';
      h+='</tr>';
    });
    h+='</tbody></table></div>';
    document.getElementById('redeemList').innerHTML=h;
  });
}

function openForm(c){
  document.getElementById('formTitle').textContent=c?'Edit Kode':'Buat Kode Redeem';
  document.getElementById('fId').value=c?c.id:'';
  document.getElementById('fCode').value=c?c.code:'';
  document.getElementById('fAmount').value=c?c.amount:10000;
  document.getElementById('fMaxUses').value=c?c.max_uses:100;
  document.getElementById('fStatus').value=c?c.status:'active';
  document.getElementById('fExpires').value=c&&c.expires_at?c.expires_at.replace(' ','T').substring(0,16):'';
  document.getElementById('formModal').style.display='flex';
}
function editCode(id){var c=allCodes.find(x=>x.id==id);if(c)openForm(c);}
function closeForm(){document.getElementById('formModal').style.display='none'}

function saveRedeem(){
  var code=document.getElementById('fCode').value.trim().toUpperCase();
  var amount=parseInt(document.getElementById('fAmount').value)||0;
  var maxUses=parseInt(document.getElementById('fMaxUses').value)||0;
  var expires=document.getElementById('fExpires').value;
  if(!code)return msg('Kode wajib diisi',false);
  if(amount<1)return msg('Bonus minimal 1',false);
  var payload={action:'save_redeem',code,amount,max_uses:maxUses,status:document.getElementById('fStatus').value,expires_at:expires?expires.replace('T',' '):null};
  var id=document.getElementById('fId').value;if(id)payload.id=parseInt(id);
  api(payload).then(d=>{if(d.ok){closeForm();loadRedeems();msg('Tersimpan!',true);}else msg(d.error||'Gagal',false)});
}

function delCode(id){
  if(!confirm('Hapus kode ini?'))return;
  api({action:'delete_redeem',id}).then(d=>{if(d.ok){loadRedeems();msg('Dihapus!',true);}});
}

loadRedeems();
</script>
<?php adminFooter(); ?>
