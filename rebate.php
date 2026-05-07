<?php
require_once 'includes/config.php';
header('Content-Type: text/html; charset=UTF-8');
$isLoggedIn = (bool)getUid();  // guest-friendly: page accessible, action gated by JS

$sets=[];
try{$st=$db->query("SELECT `key`,`value` FROM settings");foreach($st->fetchAll() as $r)$sets[$r['key']]=$r['value'];normSettings($sets);}catch(Exception $e){}
$sn=$sets['site_name']??'Situs';
// Auto-add column
try{$db->exec("ALTER TABLE users ADD COLUMN rebate_claimed_to BIGINT UNSIGNED DEFAULT 0 AFTER total_turnover");}catch(Exception $e){}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php require_once dirname(__FILE__).'/pwa_head.php'; ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Rebate - <?php echo $sn; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="theme.php?v=<?=time()?>">
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;-webkit-text-size-adjust:100%;text-size-adjust:100%}
html{font-size:16px!important}
body{font-family:'Poppins',sans-serif;background:var(--bg);color:var(--t);min-height:100vh}
.hdr{display:flex;align-items:center;padding:16px;position:sticky;top:0;z-index:10;background:var(--bg)}
.hdr button{width:32px;height:32px;display:flex;align-items:center;justify-content:center;background:none;border:none;color:var(--t2);cursor:pointer}
.hdr button svg{width:22px;height:22px}
.hdr h1{flex:1;text-align:center;font-size:1.1rem;font-weight:700}
.rb-box{margin:0 16px 20px;border:1.5px solid rgba(var(--sec-rgb,56,189,248),.35);border-radius:14px;padding:18px 20px;background:rgba(var(--sec-rgb,56,189,248),.04)}
.rb-top{display:flex;justify-content:space-between;margin-bottom:14px}
.rb-top .rt-l{font-size:.82rem;color:var(--t2)}
.rb-top .rt-l span{color:#ffd253;font-weight:800;font-size:1rem}
.rb-top .rt-r{font-size:.82rem;color:var(--t2)}
.rb-top .rt-r span{color:#4ade80;font-weight:800;font-size:1rem}
.rb-claim{display:flex;align-items:center;gap:14px;margin-bottom:14px;background:rgba(0,0,0,.2);border-radius:10px;padding:12px 16px}
.rb-claim .rc-icon{width:48px;height:48px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.rb-claim .rc-amount{flex:1;font-size:1.5rem;font-weight:800;color:#ffd253}
.rb-claim .rc-btn{padding:10px 22px;border-radius:8px;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit;border:1px solid rgba(var(--sec-rgb,56,189,248),.4);background:rgba(var(--sec-rgb,56,189,248),.12);color:var(--sec,var(--sec))}
.rb-claim .rc-btn:active{background:var(--sec);color:#fff}
.rb-bar{margin-bottom:4px;height:6px;border-radius:3px;background:var(--tint-2);overflow:hidden}
.rb-bar .fill{height:100%;border-radius:3px;background:linear-gradient(90deg,var(--sec),#4ade80);transition:width .3s}
.rb-range{display:flex;justify-content:space-between;font-size:.65rem;color:var(--t3)}
.rb-msg{text-align:center;padding:10px;font-size:.78rem;color:#4ade80;font-weight:600;display:none}
.rb-tbl{margin:0 16px 20px;border-radius:14px;overflow:hidden;border:1px solid rgba(var(--sec-rgb,56,189,248),.2);background:rgba(var(--sec-rgb,56,189,248),.04)}
.rb-tbl-hdr{display:flex;align-items:center;gap:8px;padding:16px 18px;font-size:.92rem;font-weight:700}
.rb-tbl-hdr svg{width:20px;height:20px}
.rb-tbl table{width:100%;border-collapse:collapse}
.rb-tbl th{padding:12px 16px;font-size:.72rem;font-weight:700;color:var(--t3);text-align:center;background:rgba(var(--sec-rgb,56,189,248),.08)}
.rb-tbl td{padding:14px 16px;font-size:.82rem;font-weight:600;text-align:center;border-top:1px solid rgba(var(--sec-rgb,56,189,248),.1)}
.rb-tbl tr.active{background:rgba(var(--sec-rgb,56,189,248),.1)}
.rb-tbl tr.active td{color:var(--sec)}
.rb-desc{margin:0 16px 30px}
.rb-desc h3{font-size:.88rem;font-weight:700;text-align:center;margin-bottom:14px;display:flex;align-items:center;gap:8px;justify-content:center}
.rb-desc h3::before,.rb-desc h3::after{content:'';flex:1;height:1px;background:rgba(var(--sec-rgb,56,189,248),.3);max-width:60px}
.rb-desc p{font-size:.75rem;color:var(--t2);line-height:1.7;margin-bottom:6px}
.login-msg{text-align:center;padding:60px 20px;color:var(--t3);font-size:.88rem}
.login-msg a{color:var(--sec);font-weight:700}
.toast-box{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--s);border:1px solid var(--bd);border-radius:14px;padding:24px 30px;text-align:center;z-index:9999;max-width:300px;box-shadow:0 10px 40px rgba(0,0,0,.5);animation:fadeIn .2s}
.toast-box p{font-size:.85rem;font-weight:600;color:var(--t);line-height:1.5;margin-bottom:16px}
.toast-box button{padding:8px 30px;background:var(--sec);border:none;border-radius:8px;color:#fff;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit}
.toast-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9998}
@keyframes fadeIn{from{opacity:0;transform:translate(-50%,-50%) scale(.9)}to{opacity:1;transform:translate(-50%,-50%) scale(1)}}
@keyframes spin{to{transform:rotate(360deg)}}
</style>
</head>
<body>
<div class="hdr"><button onclick="history.back()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button><h1>Rebate Turnover</h1><div style="width:32px"></div></div>

<div id="rbContent"></div>

<script>
var LEVELS=[
  [1,1000,0.3],[2,10000000,0.5],[3,50000000,0.6],[4,100000000,0.8],
  [5,500000000,1],[6,1000000000,2],[7,10000000000,3]
];
function getLevel(to){var lv=0,pct=0;for(var i=LEVELS.length-1;i>=0;i--){if(to>=LEVELS[i][1]){lv=LEVELS[i][0];pct=LEVELS[i][2];break;}}return{level:lv,pct:pct}}
function getNextThresh(to){for(var i=0;i<LEVELS.length;i++){if(to<LEVELS[i][1])return LEVELS[i][1]}return LEVELS[LEVELS.length-1][1]}
function fmtK(v){return (v/1000).toLocaleString('en',{minimumFractionDigits:2,maximumFractionDigits:2})}

function load(){
  document.getElementById('rbContent').innerHTML='<div style="text-align:center;padding:40px;color:var(--t3)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="30" height="30" style="animation:spin 1s linear infinite;display:block;margin:0 auto 12px"><path d="M21 12a9 9 0 11-6.219-8.56"/></svg>Menyinkronkan data turnover...</div>';
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'rebate_info'})})
  .then(function(r){return r.json()}).then(function(d){
    if(!d.ok){
      if(d.error==='NOT_LOGGED_IN'){
        document.getElementById('rbContent').innerHTML='<div class="login-msg">Silakan <a href="index.php">login</a> untuk melihat rebate Anda.</div>';
      }else{
        document.getElementById('rbContent').innerHTML='<div class="login-msg">Error: '+(d.error||'Gagal memuat')+'. <a href="javascript:load()">Coba lagi</a></div>';
      }
      return;
    }
    render(d);
  }).catch(function(e){document.getElementById('rbContent').innerHTML='<div class="login-msg">Gagal terhubung. <a href="javascript:load()">Coba lagi</a></div>';});
}

function render(d){
  var to=d.total_turnover||0;
  var claimed=d.rebate_claimed_to||0;
  var unclaimed=Math.max(0,to-claimed);
  var info=getLevel(to);
  var rebateAmt=Math.floor(unclaimed*info.pct/100);
  var rebateK=rebateAmt/1000;
  var nextT=getNextThresh(to);
  var pct=Math.min(100,Math.max(0,(to/nextT)*100));

  var h='<div class="rb-box">';
  h+='<div class="rb-top"><div class="rt-l">Amount <span>'+fmtK(to)+'</span></div><div class="rt-r">Rebate <span>'+info.pct+'%</span></div></div>';
  h+='<div class="rb-claim"><div class="rc-icon"><img src="asset/coin.png" style="width:48px;height:48px;object-fit:contain;" alt="coin"></div><div class="rc-amount">'+fmtK(rebateAmt)+'</div><button class="rc-btn" id="claimBtn" onclick="claimRebate()">Claim</button></div>';
  h+='<div class="rb-bar"><div class="fill" style="width:'+pct+'%"></div></div>';
  h+='<div class="rb-range"><span>'+fmtK(to)+'</span><span>'+fmtK(nextT)+'</span></div>';
  h+='<div class="rb-msg" id="rbMsg"></div>';
  h+='</div>';

  h+='<div class="rb-tbl"><div class="rb-tbl-hdr"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg> Tabel Referensi Rebate</div><table><tr><th>Level</th><th>Jumlah Taruhan</th><th>Rebate</th></tr>';
  LEVELS.forEach(function(l){
    var active=info.level===l[0]?' class="active"':'';
    h+='<tr'+active+'><td>'+l[0]+'</td><td>'+fmtK(l[1])+'</td><td>'+l[2]+'%</td></tr>';
  });
  h+='</table></div>';

  h+='<div class="rb-desc"><h3>Keterangan Aktivitas</h3>';
  h+='<p>1. Contoh Perhitungan: Jumlah Taruhan 3,000K maka 3,000K x 0.5% = 15K (Wajib mencapai x1 Turnover sebelum PENARIKAN).</p>';
  h+='<p>2. Rebate Turnover dapat di klaim kapanpun tanpa batasan waktu. (Jumlah Taruhan akan di reset setiap 00:00:00 WIB).</p>';
  h+='<p>3. Pihak situs tetap menjaga keadilan, kejujuran dan transparan, serta memiliki penjelasan terakhir, dan berwenang untuk menghentikan/mengubah promosi tanpa pemberitahuan dulu.</p>';
  h+='</div>';

  document.getElementById('rbContent').innerHTML=h;
}

function claimRebate(){
  var btn=document.getElementById('claimBtn');
  btn.textContent='...';btn.disabled=true;
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'claim_rebate'})})
  .then(function(r){return r.json()}).then(function(d){
    if(d.ok){
      var msg=document.getElementById('rbMsg');
      msg.innerHTML='<svg viewBox="0 0 24 24" fill="#4ade80" width="14" height="14" style="display:inline-block;vertical-align:middle"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><path d="M22 4L12 14.01 9 11.01" fill="none" stroke="#4ade80" stroke-width="2"/></svg> Saldo rebate Rp '+Number(d.amount).toLocaleString('id')+' berhasil masuk ke saldo!';
      msg.style.display='block';
      setTimeout(load,1500);
    }else{
      btn.textContent='Claim';btn.disabled=false;
      showToast(d.error||'Gagal klaim');
    }
  });
}

load();
function showToast(m){
  var ov=document.createElement('div');ov.className='toast-overlay';
  var bx=document.createElement('div');bx.className='toast-box';
  var p=document.createElement('p');p.textContent=m;
  var btn=document.createElement('button');btn.textContent='Oke';
  btn.onclick=function(){bx.remove();ov.remove()};
  bx.appendChild(p);bx.appendChild(btn);
  document.body.appendChild(ov);document.body.appendChild(bx);
  ov.onclick=function(){bx.remove();ov.remove()}
}
</script>
<?php include 'includes/credit_notify.php'; ?>
</body>
</html>
