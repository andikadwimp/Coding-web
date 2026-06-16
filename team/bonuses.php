<?php
header('Content-Type: text/html; charset=UTF-8');
require_once 'layout.php';adminHeader('Bonus Deposit');
// Ensure table exists
try{$db->exec("CREATE TABLE IF NOT EXISTS bonuses(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,name VARCHAR(100) NOT NULL,percentage DECIMAL(5,2) DEFAULT 0,max_amount BIGINT UNSIGNED DEFAULT 0,turnover_x INT DEFAULT 1,min_deposit BIGINT UNSIGNED DEFAULT 0,status VARCHAR(10) DEFAULT 'active',created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");}catch(Exception $e){}
try{$db->exec("ALTER TABLE bonuses ADD COLUMN min_deposit BIGINT UNSIGNED DEFAULT 0 AFTER turnover_x");}catch(Exception $e){}
?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px">
  <p style="font-size:.72rem;color:var(--t3)">Atur bonus yang ditampilkan saat user deposit (persentase + TO requirement)</p>
  <button class="btn btn-pri" onclick="openForm()">+ Tambah Bonus</button>
</div>

<div style="background:rgba(var(--sec-rgb,56,189,248),.06);border:1px solid rgba(var(--sec-rgb,56,189,248),.2);border-radius:10px;padding:12px 14px;margin-bottom:14px;font-size:.7rem;color:var(--t2);line-height:1.6">
  <b style="color:var(--sec)">Cara kerja:</b><br>
  • <b>Persentase</b>: % bonus dari nominal deposit (cth: 20 = +20%)<br>
  • <b>Max Bonus</b>: batas maksimal bonus rupiah (0 = tanpa batas)<br>
  • <b>Turnover x</b>: kelipatan TO yang harus dicapai sebelum bisa WD. Rumus: (deposit + bonus) × turnover_x<br>
  • <b>Min Deposit</b>: deposit minimal agar bonus ini bisa dipilih (0 = tanpa batas)
</div>

<div id="bonusList"></div>
<div id="statusMsg"></div>

<div id="formModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:200;align-items:center;justify-content:center;padding:16px">
<div style="background:var(--s);border:1px solid var(--bd);border-radius:14px;padding:24px 20px;width:100%;max-width:460px;max-height:92vh;overflow-y:auto">
  <h3 id="formTitle" style="font-size:.95rem;font-weight:700;margin-bottom:16px">Tambah Bonus</h3>
  <input type="hidden" id="fId">
  <div class="fg"><label>Nama Bonus</label><input id="fName" placeholder="Bonus Harian 20%" maxlength="100"></div>
  <div class="fg-row">
    <div class="fg"><label>Persentase (%)</label><input id="fPercentage" type="number" step="0.01" placeholder="20" value="0"></div>
    <div class="fg"><label>Turnover x</label><input id="fTurnover" type="number" placeholder="3" value="1"></div>
  </div>
  <div class="fg-row">
    <div class="fg"><label>Max Bonus (Rp)</label><input id="fMaxAmount" type="number" placeholder="100000" value="0"></div>
    <div class="fg"><label>Min Deposit (Rp)</label><input id="fMinDeposit" type="number" placeholder="50000" value="0"></div>
  </div>
  <div class="fg"><label>Status</label><select id="fStatus"><option value="active">Active</option><option value="inactive">Inactive</option></select></div>
  <div style="display:flex;gap:8px;margin-top:4px">
    <button class="btn btn-pri" onclick="saveBonus()" style="flex:1">Simpan</button>
    <button class="btn btn-sec" onclick="closeForm()" style="flex:1">Batal</button>
  </div>
</div>
</div>

<script>
var API='../api/admin.php';
var allBonuses=[];

function api(body){return fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify(body)}).then(r=>r.json())}
function msg(txt,ok){var el=document.getElementById('statusMsg');el.innerHTML='<div class="msg '+(ok?'msg-ok':'msg-err')+'">'+txt+'</div>';setTimeout(()=>el.innerHTML='',3000)}
function fmt(v){return Number(v||0).toLocaleString('id')}

function loadBonuses(){
  api({action:'get_bonuses'}).then(d=>{
    if(!d.ok)return msg(d.error||'Gagal memuat',false);
    allBonuses=d.bonuses||[];
    var h='';
    var active=allBonuses.filter(b=>b.status==='active').length;
    h+='<div style="display:flex;gap:8px;margin-bottom:14px;flex-wrap:wrap">';
    h+='<div style="background:var(--bg2);border:1px solid var(--bd);border-radius:8px;padding:8px 14px;font-size:.72rem"><span style="color:var(--t3)">Total: </span><b>'+allBonuses.length+'</b></div>';
    h+='<div style="background:var(--bg2);border:1px solid rgba(74,222,128,.2);border-radius:8px;padding:8px 14px;font-size:.72rem"><span style="color:var(--t3)">Aktif: </span><b style="color:var(--green)">'+active+'</b></div>';
    h+='</div>';

    if(!allBonuses.length){
      h+='<div style="color:var(--t3);font-size:.8rem;padding:20px 0;text-align:center">Belum ada bonus. Klik <b>+ Tambah Bonus</b> untuk menambah.</div>';
      document.getElementById('bonusList').innerHTML=h;return;
    }

    h+='<div style="overflow-x:auto"><table class="tbl"><thead><tr><th>Nama</th><th>%</th><th>Max Bonus</th><th>TO x</th><th>Min Depo</th><th>Status</th><th></th></tr></thead><tbody>';
    allBonuses.forEach(b=>{
      h+='<tr>';
      h+='<td><b>'+(b.name||'-')+'</b></td>';
      h+='<td style="font-weight:700;color:var(--pri)">'+(parseFloat(b.percentage)||0)+'%</td>';
      h+='<td>'+(+b.max_amount>0?'Rp '+fmt(b.max_amount):'<span style="color:var(--t3)">Bebas</span>')+'</td>';
      h+='<td><b>'+b.turnover_x+'x</b></td>';
      h+='<td>'+(+b.min_deposit>0?'Rp '+fmt(b.min_deposit):'<span style="color:var(--t3)">—</span>')+'</td>';
      h+='<td><span style="color:'+(b.status==='active'?'var(--green)':'var(--t3)')+'">'+b.status+'</span></td>';
      h+='<td style="white-space:nowrap"><button class="btn btn-sec btn-sm" onclick="editBonus('+b.id+')">Edit</button> ';
      h+='<button class="btn btn-red btn-sm" onclick="delBonus('+b.id+')">×</button></td>';
      h+='</tr>';
    });
    h+='</tbody></table></div>';
    document.getElementById('bonusList').innerHTML=h;
  });
}

function openForm(b){
  document.getElementById('formTitle').textContent=b?'Edit Bonus':'Tambah Bonus';
  document.getElementById('fId').value=b?b.id:'';
  document.getElementById('fName').value=b?b.name:'';
  document.getElementById('fPercentage').value=b?b.percentage:0;
  document.getElementById('fTurnover').value=b?b.turnover_x:1;
  document.getElementById('fMaxAmount').value=b?b.max_amount:0;
  document.getElementById('fMinDeposit').value=b?b.min_deposit:0;
  document.getElementById('fStatus').value=b?b.status:'active';
  document.getElementById('formModal').style.display='flex';
}
function editBonus(id){var b=allBonuses.find(x=>x.id==id);if(b)openForm(b);}
function closeForm(){document.getElementById('formModal').style.display='none'}

function saveBonus(){
  var name=document.getElementById('fName').value.trim();
  var percentage=parseFloat(document.getElementById('fPercentage').value)||0;
  var turnover_x=parseInt(document.getElementById('fTurnover').value)||1;
  var max_amount=parseInt(document.getElementById('fMaxAmount').value)||0;
  var min_deposit=parseInt(document.getElementById('fMinDeposit').value)||0;
  if(!name)return msg('Nama bonus wajib diisi',false);
  if(percentage<0||percentage>1000)return msg('Persentase 0-1000',false);
  if(turnover_x<0)return msg('Turnover tidak boleh negatif',false);
  var payload={action:'save_bonus',name,percentage,max_amount,turnover_x,min_deposit,status:document.getElementById('fStatus').value};
  var id=document.getElementById('fId').value;if(id)payload.id=parseInt(id);
  api(payload).then(d=>{if(d.ok){closeForm();loadBonuses();msg('Tersimpan!',true);}else msg(d.error||'Gagal',false)});
}

function delBonus(id){
  if(!confirm('Hapus bonus ini? Deposit lama yang pakai bonus ini tidak terpengaruh.'))return;
  api({action:'delete_bonus',id}).then(d=>{if(d.ok){loadBonuses();msg('Dihapus!',true);}else msg(d.error||'Gagal',false)});
}

loadBonuses();
</script>
<?php adminFooter(); ?>
