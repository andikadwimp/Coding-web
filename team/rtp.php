<?php
header('Content-Type: text/html; charset=UTF-8');
require_once 'layout.php'; adminHeader('RTP & Force Win Control');
?>
<style>
.rtp-tabs{display:flex;border:1px solid var(--bd);border-radius:10px;overflow:hidden;margin-bottom:16px}
.rtp-tab{flex:1;padding:11px;text-align:center;font-size:.76rem;font-weight:700;cursor:pointer;background:transparent;color:var(--t3);border:none;font-family:inherit}
.rtp-tab.on{background:rgba(var(--sec-rgb,56,189,248),.12);color:var(--sec)}
.rtp-pan{display:none}
.rtp-pan.on{display:block}
.rtp-row{display:flex;align-items:center;gap:12px;padding:10px 12px;border:1px solid var(--bd);border-radius:10px;background:var(--bg2);margin-bottom:8px;flex-wrap:wrap}
.rtp-pill{padding:3px 9px;font-size:.62rem;font-weight:700;border-radius:6px}
.rtp-pill.ok{background:rgba(74,222,128,.15);color:#4ade80}
.rtp-pill.warn{background:rgba(251,191,36,.15);color:#fbbf24}
.rtp-pill.bad{background:rgba(239,68,68,.15);color:#ef4444}
.rtp-input{width:72px;padding:6px 8px;background:var(--bg);border:1px solid var(--bd);color:var(--t);border-radius:6px;font-size:.76rem;font-weight:700;text-align:center}
.warning-box{background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.25);border-radius:10px;padding:12px 14px;font-size:.7rem;color:#fca5a5;line-height:1.6;margin-bottom:14px}
.info-box{background:rgba(var(--sec-rgb,56,189,248),.06);border:1px solid rgba(var(--sec-rgb,56,189,248),.2);border-radius:10px;padding:12px 14px;font-size:.7rem;color:var(--t2);line-height:1.6;margin-bottom:14px}
</style>

<div class="warning-box">
  ⚠️ <b>Peringatan:</b> Fitur ini mengubah RTP (Return to Player) dan bisa memanggil <b>force win</b> di game. Gunakan dengan bijak — data diproses langsung oleh NexusGGR. Setiap perubahan tercatat.
</div>

<div class="rtp-tabs">
  <button class="rtp-tab on" onclick="sw(0,this)">🎛️ Set RTP</button>
  <button class="rtp-tab" onclick="sw(1,this)">🎯 Force Win</button>
  <button class="rtp-tab" onclick="sw(2,this)">📋 History</button>
  <button class="rtp-tab" onclick="sw(3,this)">⚙️ Default</button>
</div>

<!-- TAB 1: RTP CONTROL -->
<div class="rtp-pan on" id="pan0">
  <div class="info-box">
    <b>RTP Control:</b> Atur persentase kemenangan user (1-95%). Target RTP dibandingkan dengan Real RTP actual user. RTP rendah = user lebih sering kalah, RTP tinggi = lebih sering menang.
  </div>

  <div style="display:flex;gap:10px;margin-bottom:12px;flex-wrap:wrap;align-items:center">
    <button class="btn btn-pri btn-sm" onclick="loadPlayers()">🔄 Refresh Players</button>
    <div style="flex:1"></div>
    <div style="display:flex;gap:6px;align-items:center">
      <label style="font-size:.7rem;color:var(--t3)">Bulk set:</label>
      <input type="number" id="bulkRtp" class="rtp-input" value="92" min="1" max="95">
      <button class="btn btn-sec btn-sm" onclick="bulkSet()">Apply ke terpilih</button>
    </div>
  </div>

  <div id="playersList"></div>
</div>

<!-- TAB 2: FORCE WIN -->
<div class="rtp-pan" id="pan1">
  <div class="info-box">
    <b>Force Win (Call):</b> Apply hadiah langsung ke user tertentu di game tertentu. Rumus: <code>called_money = bet × (rtp / 100)</code>.
    <br><b>Call type:</b> 1 = Common Free Spin, 2 = Buy Bonus Free Spin.
  </div>

  <div class="rtp-row">
    <div style="flex:1;min-width:200px">
      <label style="font-size:.6rem;color:var(--t3);display:block;margin-bottom:3px">User Code</label>
      <input id="fwUser" placeholder="username" style="width:100%;padding:7px 10px;background:var(--bg);border:1px solid var(--bd);color:var(--t);border-radius:6px;font-size:.76rem">
    </div>
    <div style="min-width:160px">
      <label style="font-size:.6rem;color:var(--t3);display:block;margin-bottom:3px">Provider</label>
      <select id="fwProvider" style="width:100%;padding:7px 10px;background:var(--bg);border:1px solid var(--bd);color:var(--t);border-radius:6px;font-size:.76rem">
        <option value="PRAGMATIC">Pragmatic</option>
        <option value="PGSOFT">PG Soft</option>
        <option value="HABANERO">Habanero</option>
        <option value="CQ9">CQ9</option>
        <option value="FATPANDA">Fat Panda</option>
        <option value="BOOONGO">Booongo</option>
        <option value="PLAYSON">Playson</option>
      </select>
    </div>
    <div style="min-width:160px">
      <label style="font-size:.6rem;color:var(--t3);display:block;margin-bottom:3px">Game Code</label>
      <input id="fwGame" placeholder="vs20doghouse" style="width:100%;padding:7px 10px;background:var(--bg);border:1px solid var(--bd);color:var(--t);border-radius:6px;font-size:.76rem">
    </div>
    <button class="btn btn-pri btn-sm" onclick="loadCalls()" style="margin-top:17px">Cek Call Available</button>
  </div>

  <div id="callList" style="margin-top:14px"></div>
</div>

<!-- TAB 3: HISTORY -->
<div class="rtp-pan" id="pan2">
  <div class="info-box">
    <b>Call History:</b> Riwayat force win. Status: 0=Waiting, 1=Processing, 2=Finished, 3=Rejected (bet changed), 4=Canceled.
  </div>
  <button class="btn btn-pri btn-sm" onclick="loadHistory()">🔄 Refresh History</button>
  <div id="historyList" style="margin-top:12px"></div>
</div>

<!-- TAB 4: DEFAULT RTP -->
<div class="rtp-pan" id="pan3">
  <div class="info-box">
    <b>Default RTP untuk User Baru:</b> Otomatis apply ke setiap user yang baru daftar. Value disimpan di DB, langsung di-push ke NexusGGR saat user register.
    <br><br>
    Pakai endpoint <code>control_users_rtp</code> NexusGGR yang set RTP <b>global semua provider</b> sekaligus.
    <br><br>
    <b>Range valid:</b> 1-95 (semakin kecil = user makin sering kalah).
    <br><b>Kosongkan</b> (atau set 0) jika mau pakai RTP default dari NexusGGR (tanpa auto-set saat register).
  </div>

  <div style="background:var(--bg2);border:1px solid var(--bd);border-radius:12px;padding:18px;max-width:500px">
    <div style="font-size:.82rem;font-weight:700;margin-bottom:14px">⚙️ Konfigurasi Default RTP</div>

    <div class="fg">
      <label>Default RTP untuk user baru</label>
      <div style="display:flex;gap:10px;align-items:center">
        <input type="number" id="defRtp" min="0" max="95" placeholder="0 = non-aktif" style="flex:1;padding:10px 12px;background:var(--bg);border:1px solid var(--bd);color:var(--t);border-radius:8px;font-size:.9rem;font-weight:700;font-family:inherit">
        <span style="font-size:.78rem;color:var(--t3)">%</span>
      </div>
      <div style="font-size:.62rem;color:var(--t3);margin-top:4px">Contoh: 20 → semua user baru otomatis set RTP 20%</div>
    </div>

    <button class="btn btn-pri" onclick="saveDefRtp()" id="saveBtn" style="width:100%;margin-top:8px">💾 Simpan</button>

    <div id="defRtpStatus" style="margin-top:12px"></div>

    <hr style="border:none;border-top:1px solid var(--bd);margin:18px 0">

    <div style="font-size:.7rem;color:var(--t2);line-height:1.7">
      <b>Contoh skenario:</b>
      <br>• Set 20% → Ucok daftar akun baru → NexusGGR otomatis set RTP Ucok jadi 20% (global semua provider) → Ucok main, menang-kalah sesuai RTP 20%.
      <br>• Set 0 / kosong → Tidak ada auto-set, RTP pakai default dari NexusGGR (biasanya 92-96%).
      <br>• Untuk ganti RTP user existing, pakai tab <b>Set RTP</b> (per user) atau bulk select.
    </div>
  </div>
</div>

<div id="statusMsg" style="margin-top:10px"></div>

<script>
var API='../api/admin.php';
var currentTab=0;
var playersData=[];
var selectedUsers=new Set();

function api(body){return fetch(API,{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify(body)}).then(r=>r.json())}
function msg(txt,ok){var el=document.getElementById('statusMsg');el.innerHTML='<div class="msg '+(ok?'msg-ok':'msg-err')+'">'+txt+'</div>';setTimeout(()=>el.innerHTML='',3500)}
function fmt(n){return Number(n||0).toLocaleString('id')}

function sw(i,btn){
  currentTab=i;
  document.querySelectorAll('.rtp-tab').forEach(t=>t.classList.remove('on'));btn.classList.add('on');
  document.querySelectorAll('.rtp-pan').forEach(p=>p.classList.remove('on'));
  document.getElementById('pan'+i).classList.add('on');
  if(i===0&&!playersData.length)loadPlayers();
  if(i===2)loadHistory();
  if(i===3)loadDefRtp();
}

function loadDefRtp(){
  api({action:'get_settings'}).then(d=>{
    if(d.ok&&d.settings){
      var v=d.settings.default_rtp||'';
      document.getElementById('defRtp').value=v;
      if(v>0){
        document.getElementById('defRtpStatus').innerHTML='<div style="background:rgba(74,222,128,.08);border:1px solid rgba(74,222,128,.2);border-radius:8px;padding:10px;font-size:.72rem;color:#4ade80">✓ Aktif: user baru otomatis set RTP <b>'+v+'%</b></div>';
      }else{
        document.getElementById('defRtpStatus').innerHTML='<div style="background:rgba(156,163,175,.08);border:1px solid rgba(156,163,175,.2);border-radius:8px;padding:10px;font-size:.72rem;color:var(--t3)">Non-aktif — NexusGGR pakai RTP default-nya sendiri</div>';
      }
    }
  });
}

function saveDefRtp(){
  var v=parseInt(document.getElementById('defRtp').value)||0;
  if(v<0||v>95)return msg('Nilai harus 0-95',false);
  var btn=document.getElementById('saveBtn');btn.disabled=true;btn.textContent='Menyimpan...';
  api({action:'save_settings',settings:{default_rtp:v}}).then(d=>{
    btn.disabled=false;btn.textContent='💾 Simpan';
    if(d.ok){
      msg('✓ Default RTP tersimpan: '+(v>0?v+'%':'non-aktif'),true);
      loadDefRtp();
    }else{
      msg('Gagal: '+(d.error||'Unknown'),false);
    }
  }).catch(()=>{btn.disabled=false;btn.textContent='💾 Simpan';msg('Network error',false)});
}

function rtpColor(real,target){
  var r=parseFloat(real)||0,t=parseFloat(target)||92;
  if(r>t+30)return'bad'; // user lagi untung banyak
  if(r<t-30)return'ok'; // user buntung
  return'warn';
}

function loadPlayers(){
  document.getElementById('playersList').innerHTML='<div style="padding:20px;text-align:center;color:var(--t3)">Memuat...</div>';
  api({action:'nexus_players'}).then(d=>{
    if(!d.ok){
      document.getElementById('playersList').innerHTML='<div class="msg msg-err">'+(d.error||'Gagal ambil data')+'</div>';
      return;
    }
    playersData=d.data||[];
    if(!playersData.length){
      document.getElementById('playersList').innerHTML='<div style="padding:20px;text-align:center;color:var(--t3)">Tidak ada user aktif saat ini.</div>';
      return;
    }
    var h='';
    playersData.forEach(p=>{
      var col=rtpColor(p.real_rtp,p.target_rtp);
      h+='<div class="rtp-row">';
      h+='<input type="checkbox" class="rtp-chk" data-uc="'+p.user_code+'" onchange="toggleSel(\''+p.user_code+'\',this.checked)">';
      h+='<div style="flex:1;min-width:180px">';
      h+='<div style="font-weight:700;font-size:.78rem">'+p.user_code+'</div>';
      h+='<div style="font-size:.6rem;color:var(--t3);margin-top:2px">'+p.provider_code+' • '+p.game_code+' • bet '+fmt(p.bet)+'</div>';
      h+='</div>';
      h+='<div style="font-size:.65rem;text-align:right;min-width:120px">';
      h+='<div>Saldo: <b>'+fmt(p.balance)+'</b></div>';
      h+='<div style="color:var(--t3)">Debit: '+fmt(p.total_debit)+' • Credit: '+fmt(p.total_credit)+'</div>';
      h+='</div>';
      h+='<div style="text-align:center;min-width:90px">';
      h+='<div style="font-size:.58rem;color:var(--t3)">Target / Real</div>';
      h+='<div><span class="rtp-pill '+col+'">'+(p.target_rtp||'-')+'% / '+(p.real_rtp?parseFloat(p.real_rtp).toFixed(1):'-')+'%</span></div>';
      h+='</div>';
      h+='<div style="display:flex;gap:6px;align-items:center">';
      h+='<input type="number" class="rtp-input" id="rtp_'+p.user_code+'" min="1" max="95" value="'+(p.target_rtp||92)+'">';
      h+='<button class="btn btn-pri btn-sm" onclick="setOne(\''+p.user_code+'\',\''+p.provider_code+'\')">Set</button>';
      h+='</div>';
      h+='</div>';
    });
    document.getElementById('playersList').innerHTML=h;
  });
}

function toggleSel(uc,checked){if(checked)selectedUsers.add(uc);else selectedUsers.delete(uc);}

function setOne(uc,provider){
  var rtp=parseInt(document.getElementById('rtp_'+uc).value)||92;
  if(rtp<1||rtp>95)return msg('RTP harus 1-95',false);
  if(!confirm('Set RTP '+uc+' di '+provider+' jadi '+rtp+'%?'))return;
  api({action:'nexus_set_rtp',user_code:uc,provider_code:provider,rtp:rtp}).then(d=>{
    if(d.ok)msg('✓ RTP '+uc+' berhasil diubah ke '+d.changed_rtp+'%',true);
    else msg('Gagal: '+(d.error||'Unknown'),false);
  });
}

function bulkSet(){
  if(!selectedUsers.size)return msg('Pilih user dulu (centang)',false);
  var rtp=parseInt(document.getElementById('bulkRtp').value)||92;
  if(rtp<1||rtp>95)return msg('RTP harus 1-95',false);
  if(!confirm('Set RTP '+selectedUsers.size+' user terpilih jadi '+rtp+'%?'))return;
  api({action:'nexus_set_bulk_rtp',user_codes:Array.from(selectedUsers),rtp:rtp}).then(d=>{
    if(d.ok){msg('✓ Bulk RTP '+selectedUsers.size+' user diubah ke '+d.changed_rtp+'%',true);selectedUsers.clear();loadPlayers();}
    else msg('Gagal: '+(d.error||'Unknown'),false);
  });
}

function loadCalls(){
  var uc=document.getElementById('fwUser').value.trim();
  var pr=document.getElementById('fwProvider').value;
  var gc=document.getElementById('fwGame').value.trim();
  if(!uc||!gc)return msg('User code & game code wajib',false);
  document.getElementById('callList').innerHTML='<div style="padding:20px;text-align:center;color:var(--t3)">Memuat...</div>';
  api({action:'nexus_call_list',provider_code:pr,game_code:gc}).then(d=>{
    if(!d.ok){
      document.getElementById('callList').innerHTML='<div class="msg msg-err">'+(d.error||'Gagal')+'</div>';
      return;
    }
    var calls=d.calls||[];
    if(!calls.length){
      document.getElementById('callList').innerHTML='<div class="info-box">Tidak ada call tersedia untuk game ini saat ini.</div>';
      return;
    }
    var h='<div style="font-size:.72rem;color:var(--t3);margin-bottom:8px">Call yang tersedia untuk <b>'+gc+'</b>:</div>';
    calls.forEach((c,i)=>{
      var pct=c.rtp/100;
      h+='<div class="rtp-row">';
      h+='<div style="flex:1"><b>'+c.call_type+'</b><div style="font-size:.62rem;color:var(--t3);margin-top:2px">RTP: '+fmt(c.rtp)+' (=bet × '+pct.toFixed(2)+'x)</div></div>';
      h+='<select id="ct_'+i+'" style="padding:6px 10px;background:var(--bg);border:1px solid var(--bd);color:var(--t);border-radius:6px;font-size:.72rem">';
      h+='<option value="1">Common Free</option><option value="2">Buy Bonus Free</option>';
      h+='</select>';
      h+='<button class="btn btn-red btn-sm" onclick="applyCall('+c.rtp+',\'ct_'+i+'\')">Apply</button>';
      h+='</div>';
    });
    document.getElementById('callList').innerHTML=h;
  });
}

function applyCall(callRtp,ctId){
  var uc=document.getElementById('fwUser').value.trim();
  var pr=document.getElementById('fwProvider').value;
  var gc=document.getElementById('fwGame').value.trim();
  var ct=parseInt(document.getElementById(ctId).value)||1;
  if(!confirm('Apply call RTP '+callRtp+' (type '+ct+') ke '+uc+' di game '+gc+'?\nINI TIDAK BISA DIUNDO!'))return;
  api({action:'nexus_call_apply',user_code:uc,provider_code:pr,game_code:gc,call_rtp:callRtp,call_type:ct}).then(d=>{
    if(d.ok)msg('✓ Call applied! Called money: Rp '+fmt(d.called_money),true);
    else msg('Gagal: '+(d.error||'Unknown'),false);
  });
}

function loadHistory(){
  document.getElementById('historyList').innerHTML='<div style="padding:20px;text-align:center;color:var(--t3)">Memuat...</div>';
  api({action:'nexus_call_history',offset:0,limit:50}).then(d=>{
    if(!d.ok){
      document.getElementById('historyList').innerHTML='<div class="msg msg-err">'+(d.error||'Gagal')+'</div>';
      return;
    }
    var list=d.data||[];
    if(!list.length){
      document.getElementById('historyList').innerHTML='<div style="padding:20px;text-align:center;color:var(--t3)">Belum ada history.</div>';
      return;
    }
    var statusLabel={0:'Waiting',1:'Processing',2:'Finished',3:'Rejected',4:'Canceled'};
    var statusColor={0:'warn',1:'warn',2:'ok',3:'bad',4:'bad'};
    var h='<div style="overflow-x:auto"><table class="tbl"><thead><tr><th>ID</th><th>User</th><th>Game</th><th>Bet</th><th>Expected</th><th>Real</th><th>RTP</th><th>Status</th><th>Waktu</th><th></th></tr></thead><tbody>';
    list.forEach(c=>{
      var st=parseInt(c.status);
      h+='<tr>';
      h+='<td>'+c.id+'</td>';
      h+='<td><b>'+c.user_code+'</b></td>';
      h+='<td style="font-size:.62rem">'+c.provider_code+'<br>'+c.game_code+'</td>';
      h+='<td>'+fmt(c.bet)+'</td>';
      h+='<td>'+fmt(c.expect)+'</td>';
      h+='<td><b>'+fmt(c.real)+'</b></td>';
      h+='<td>'+c.rtp+'%</td>';
      h+='<td><span class="rtp-pill '+statusColor[st]+'">'+(statusLabel[st]||st)+'</span></td>';
      h+='<td style="font-size:.6rem">'+(c.created_at||'').substring(0,16)+'</td>';
      h+='<td>'+(st<=1?'<button class="btn btn-red btn-sm" onclick="cancelCall('+c.id+')">×</button>':'')+'</td>';
      h+='</tr>';
    });
    h+='</tbody></table></div>';
    document.getElementById('historyList').innerHTML=h;
  });
}

function cancelCall(id){
  if(!confirm('Cancel call #'+id+'?'))return;
  api({action:'nexus_call_cancel',call_id:id}).then(d=>{
    if(d.ok){msg('✓ Call #'+id+' dibatalkan. Dana dikembalikan: Rp '+fmt(d.canceled_money),true);loadHistory();}
    else msg('Gagal: '+(d.error||'Unknown'),false);
  });
}

// Initial load
loadPlayers();
</script>
<?php adminFooter(); ?>
