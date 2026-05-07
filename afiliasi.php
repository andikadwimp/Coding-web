<?php
require_once 'includes/config.php';
header('Content-Type: text/html; charset=UTF-8');
$isLoggedIn = (bool)getUid();  // guest-friendly: page accessible, action gated by JS

$sets=[];try{$st=$db->query("SELECT `key`,`value` FROM settings");foreach($st->fetchAll() as $r)$sets[$r['key']]=$r['value'];normSettings($sets);}catch(Exception $e){}
$sn=$sets['site_name']??'Situs';$uid=getUid();
$user=null;$refCode='';$downCount=0;$validCount=0;$stats=[];
if($uid){
  try{$u=$db->prepare("SELECT * FROM users WHERE id=?");$u->execute([$uid]);$user=$u->fetch();$refCode=$user['ref_code']??'';}catch(Exception $e){}
  if($refCode){
    try{
      // Auto-create column untuk rate-limit (pertama kali)
      try{$db->exec("ALTER TABLE users ADD COLUMN last_turnover_sync DATETIME DEFAULT NULL AFTER total_turnover");}catch(Exception $e){}
      $dc=$db->prepare("SELECT COUNT(*) FROM users WHERE referred_by=?");$dc->execute([$refCode]);$downCount=$dc->fetchColumn();
      $vc=$db->prepare("SELECT COUNT(*) FROM users WHERE referred_by=? AND total_deposit>=50000 AND total_turnover>=1000000");$vc->execute([$refCode]);$validCount=$vc->fetchColumn();
      // Stats
      $st=$db->prepare("SELECT COALESCE(SUM(total_deposit),0) as dep,COALESCE(SUM(total_turnover),0) as turn FROM users WHERE referred_by=?");$st->execute([$refCode]);$stats=$st->fetch();
      // Komisi diterima
      $km=$db->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND type='referral'");$km->execute([$uid]);$totalKomisi=$km->fetchColumn();
      $kmToday=$db->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND type='referral' AND DATE(created_at)=CURDATE()");$kmToday->execute([$uid]);$komisiToday=$kmToday->fetchColumn();
    }catch(Exception $e){$totalKomisi=0;$komisiToday=0;}
  }
}
$domain=$_SERVER['HTTP_HOST']??'cuanvvipgg.xyz';
$refLink='https://'.$domain.'/invite.php?ref='.$refCode;
?>
<!DOCTYPE html>
<html lang="id"><head>
<meta charset="UTF-8">
<?php require_once 'pwa_head.php'; ?>
<meta name="viewport" content="width=390, user-scalable=no">
<title>PromosiPusat - <?php echo $sn; ?></title>
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
.tabs{display:flex;padding:0 12px;position:sticky;top:50px;z-index:9;background:var(--bg);border-bottom:2px solid var(--bd)}
.tab{flex:1;text-align:center;padding:12px 4px;font-size:.78rem;font-weight:600;color:var(--t3);cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-2px;white-space:nowrap}
.tab.on{color:var(--sec);border-color:var(--sec)}
.panel{display:none;padding:16px}.panel.on{display:block}
/* Promosi Saya */
.share-card{background:var(--s);border:1px solid var(--bd);border-radius:14px;padding:20px;margin-bottom:16px;position:relative;overflow:hidden}
.share-card::before{content:'';position:absolute;top:-20px;right:-20px;width:80px;height:80px;border-radius:50%;background:rgba(var(--sec-rgb,56,189,248),.06)}
.share-card h3{font-size:1.2rem;font-weight:800;margin-bottom:4px}
.share-card .ref-id{font-size:.72rem;color:var(--t3);margin-bottom:16px}
.qr-row{display:flex;gap:16px;align-items:flex-start;margin-bottom:16px}
.qr-box{background:#fff;border-radius:10px;padding:8px;flex-shrink:0}
.qr-box img{width:100px;height:100px;display:block}
.link-area{flex:1;display:flex;flex-direction:column;gap:10px}
.link-url{font-size:.7rem;color:var(--sec);word-break:break-all;line-height:1.4}
.btn-copy{display:flex;align-items:center;justify-content:center;gap:6px;padding:10px;background:var(--bg2);border:1px solid var(--bd);border-radius:8px;color:var(--t);font-size:.75rem;font-weight:700;cursor:pointer;font-family:inherit;width:100%}
.btn-copy svg{width:16px;height:16px}
.socials{display:flex;gap:8px;flex-wrap:wrap}
.soc{width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;text-decoration:none}
.soc svg{width:18px;height:18px}
/* Komisi */
.komisi-card{background:var(--s);border:1px solid var(--bd);border-radius:14px;padding:18px;margin-bottom:16px}
.komisi-card h4{font-size:.88rem;font-weight:700;display:flex;align-items:center;gap:6px;margin-bottom:14px}
.km-box{background:rgba(var(--sec-rgb,56,189,248),.06);border-radius:10px;padding:16px;display:flex;align-items:center;gap:14px;margin-bottom:14px}
.km-box .km-val{font-size:1.5rem;font-weight:800;color:var(--sec)}
.km-box .km-lbl{font-size:.72rem;color:var(--t2)}
.km-today{display:flex;align-items:center;justify-content:space-between}
.km-today span{font-size:.78rem;color:var(--t2)}
.km-today b{color:var(--pri);font-weight:800}
.btn-klaim{padding:10px 24px;background:linear-gradient(135deg,var(--sec),var(--sec));border:none;border-radius:8px;color:#fff;font-size:.78rem;font-weight:700;cursor:pointer;font-family:inherit}
.km-note{font-size:.62rem;color:var(--t3);margin-top:10px}
/* Data saya */
.data-card{background:var(--s);border:1px solid var(--bd);border-radius:14px;padding:18px;margin-bottom:16px}
.data-card h4{font-size:.88rem;font-weight:700;margin-bottom:14px;display:flex;justify-content:space-between;align-items:center}
.data-card .dc-sub{font-size:.72rem;font-weight:700;margin-bottom:10px;display:flex;align-items:center;gap:6px}
.stat-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.sg-item{background:rgba(var(--sec-rgb,56,189,248),.06);border-radius:8px;padding:12px;text-align:center}
.sg-item .sg-lbl{font-size:.62rem;color:var(--t3);margin-bottom:4px}
.sg-item .sg-val{font-size:1rem;font-weight:800}
/* Tutorial */
.tut-card{background:var(--s);border:1px solid var(--bd);border-radius:14px;padding:18px;margin-bottom:16px}
.tut-card h4{font-size:.88rem;font-weight:700;margin-bottom:12px}
.tut-card p{font-size:.75rem;color:var(--t2);line-height:1.7;margin-bottom:10px}
.tut-card b{color:var(--t)}
.tut-card .hl{color:var(--sec);font-weight:800;font-style:italic}
/* Kinerja */
.filter-row{display:flex;gap:8px;margin-bottom:14px}
.filter-row input,.filter-row select{flex:1;padding:10px 12px;background:var(--s);border:1px solid var(--bd);border-radius:8px;color:var(--t);font-family:inherit;font-size:.78rem}
.tbl-head{display:flex;background:rgba(var(--sec-rgb,56,189,248),.15);border-radius:8px;padding:10px 0;margin-bottom:8px}
.tbl-head span{flex:1;text-align:center;font-size:.62rem;font-weight:700}
.tbl-row{display:flex;padding:10px 0;border-bottom:1px solid rgba(var(--sec-rgb,56,189,248),.08)}
.tbl-row span{flex:1;text-align:center;font-size:.65rem;color:var(--t2)}
.empty{text-align:center;padding:50px 20px;color:var(--t3);font-size:.82rem}
.toast-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9998}
.toast-box{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--s);border:1px solid var(--bd);border-radius:14px;padding:24px 30px;text-align:center;z-index:9999;max-width:280px}
.toast-box p{font-size:.85rem;font-weight:600;margin-bottom:16px}
.toast-box button{padding:8px 30px;background:var(--sec);border:none;border-radius:8px;color:#fff;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit}
</style></head><body>

<div class="hdr"><button onclick="history.back()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button><h1>PromosiPusat</h1><div style="width:32px"></div></div>

<div class="tabs">
<div class="tab on" onclick="swTab(0,this)">Promosi Saya</div>
<div class="tab" onclick="swTab(1,this)">Tutorial Promosi</div>
<div class="tab" onclick="swTab(2,this)">Kinerja saya</div>
</div>

<!-- TAB 0: Promosi Saya -->
<div class="panel on" id="p0">

<div class="share-card">
<h3>Bagikan Info</h3>
<div class="ref-id">ID Referral: <?php echo $refCode; ?></div>
<div class="qr-row">
<div class="qr-box"><img src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=<?php echo urlencode($refLink); ?>" alt="QR"></div>
<div class="link-area">
<div class="link-url"><?php echo $refLink; ?></div>
<button class="btn-copy" onclick="navigator.clipboard.writeText('<?php echo $refLink; ?>');showToast('Link disalin')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg> Salin</button>
</div>
</div>
<div class="socials">
<a class="soc" style="background:#25D366" href="https://wa.me/?text=<?php echo urlencode('Main di '.$sn.'! '.$refLink); ?>" target="_blank"><svg viewBox="0 0 24 24"><path style="fill:#fff" d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2.05 22l5.25-1.38c1.45.79 3.08 1.21 4.74 1.21 5.46 0 9.91-4.45 9.91-9.91C21.95 6.45 17.5 2 12.04 2zm5.82 14.01c-.24.68-1.42 1.3-1.96 1.38-.5.08-.96.11-3.28-.7-2.8-1-4.57-3.85-4.71-4.03-.14-.18-1.1-1.47-1.1-2.81 0-1.34.69-2 .94-2.27.24-.27.53-.34.71-.34.18 0 .36 0 .51.01.18.01.41-.06.64.49.24.56.8 1.97.87 2.11.07.14.12.31.02.49-.09.19-.14.3-.28.47-.14.16-.3.36-.43.49-.14.14-.29.29-.12.57.16.28.73 1.21 1.57 1.96 1.08.97 2 1.27 2.28 1.41.28.14.45.12.62-.07.16-.19.71-.83.9-1.12.19-.28.38-.24.64-.14.27.09 1.68.79 1.97.94.28.14.47.21.54.33.07.12.07.71-.17 1.39z"/></svg></a>
<a class="soc" style="background:#1877F2" href="#"><svg viewBox="0 0 24 24"><path style="fill:#fff" d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg></a>
<a class="soc" style="background:#229ED9" href="#"><svg viewBox="0 0 24 24"><path style="fill:#fff" d="M11.944 0A12 12 0 000 12a12 12 0 0012 12 12 12 0 0012-12A12 12 0 0012.056 0zM8.016 10.18l8.544-3.293c.396-.144.742.097.613.7l-1.457 6.858c-.108.48-.39.597-.79.37l-2.18-1.607-1.051 1.013c-.116.116-.214.214-.439.214l.156-2.213 4.026-3.637c.176-.156-.038-.243-.272-.087l-4.974 3.132-2.143-.668c-.466-.146-.475-.466.097-.69z"/></svg></a>
<a class="soc" style="background:#000" href="#"><svg viewBox="0 0 24 24"><path style="fill:#fff" d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>
<a class="soc" style="background:linear-gradient(45deg,#f09433,#e6683c,#dc2743,#cc2366,#bc1888)" href="#"><svg viewBox="0 0 24 24"><path style="fill:#fff" d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg></a>
<a class="soc" style="background:#000" href="#"><svg viewBox="0 0 24 24"><path style="fill:#fff" d="M12.525.02c1.31-.02 2.61.01 3.91.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg></a>
</div>
</div>

<div class="komisi-card">
<h4>Komisi <svg viewBox="0 0 24 24" fill="none" stroke="var(--t3)" stroke-width="2" width="16" height="16"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg></h4>
<div class="km-box">
<div><div class="km-lbl">Komisi yang sudah diterima</div><div class="km-val"><?php echo number_format(($totalKomisi??0)/1000,2); ?></div></div>
</div>
<div class="km-today">
<span>Komisi Hari Ini: <b><?php echo number_format(($komisiToday??0)/1000,2); ?></b></span>
<button class="btn-klaim" onclick="showToast('Komisi otomatis masuk saat downline bermain')">Klaim</button>
</div>
<div class="km-note">1.00 sebenarnya adalah 1 K, 1K = Rp 1,000</div>
</div>

<div class="data-card">
<h4>Data saya</h4>
<div class="dc-sub"><svg viewBox="0 0 24 24" fill="none" stroke="var(--sec)" stroke-width="2" width="16" height="16"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/></svg> Bawahan baru <b style="color:var(--sec);margin-left:4px"><?php echo $downCount; ?></b></div>
<div class="stat-grid">
<div class="sg-item"><div class="sg-lbl">Bawahan langsung</div><div class="sg-val"><?php echo $downCount; ?></div></div>
<div class="sg-item"><div class="sg-lbl">Bawahan valid</div><div class="sg-val"><?php echo $validCount; ?></div></div>
<div class="sg-item"><div class="sg-lbl">Jumlah deposit</div><div class="sg-val"><?php echo number_format(($stats['dep']??0)/1000,2); ?></div></div>
<div class="sg-item"><div class="sg-lbl">Jumlah taruhan</div><div class="sg-val"><?php echo number_format(($stats['turn']??0)/1000,2); ?></div></div>
<div class="sg-item"><div class="sg-lbl">Deposit pertama</div><div class="sg-val">0.00</div></div>
<div class="sg-item"><div class="sg-lbl">Daftar + deposit</div><div class="sg-val"><?php echo $validCount; ?></div></div>
</div>
</div>
</div>

<!-- TAB 1: Tutorial Promosi -->
<div class="panel" id="p1">
<div class="tut-card">
<h4>Cara Kerja Komisi</h4>
<p>Sistem komisi berdasarkan <b>taruhan efektif</b> bawahan Anda. Semakin banyak bawahan yang aktif bermain, semakin besar komisi yang Anda dapatkan.</p>
<p><b>Tingkat Rebate:</b></p>
<p>Taruhan 0 - 10,000K: Rebate <span class="hl">1%</span></p>
<p>Taruhan 10,000K - 50,000K: Rebate <span class="hl">2%</span></p>
<p>Taruhan 50,000K+: Rebate <span class="hl">3%</span></p>
</div>
<div class="tut-card">
<h4>Contoh Perhitungan</h4>
<p>Anda mengajak <b>B1</b>, <b>B2</b>, <b>B3</b> untuk bergabung.</p>
<p>B1 taruhan efektif: <span class="hl">500</span>, B2: <span class="hl">3000</span>, B3: <span class="hl">2000</span></p>
<p>Total taruhan langsung: <b>5,500</b></p>
<p>Komisi langsung (3%): <span class="hl">165</span></p>
<p>B3 mengajak C1, C2, C3. C3 taruhan efektif <span class="hl">20,000</span></p>
<p>Kontribusi dari bawahan lain: <span class="hl">60</span></p>
<p><b>Total komisi Anda: <span class="hl">225</span></b></p>
</div>
<div class="tut-card">
<h4>Ringkasan</h4>
<p><b>(1) Tim langsung</b>: bawahan yang Anda ajak sendiri (tingkat 1).</p>
<p><b>(2) Tim lain</b>: bawahan dari bawahan Anda (tingkat 2+). Pengembangan tidak dibatasi.</p>
<p>Tidak peduli kapan bawahan bergabung, penghasilan Anda tidak akan terpengaruh. Model agensi yang adil dan tidak memihak.</p>
</div>
</div>

<!-- TAB 2: Kinerja saya -->
<div class="panel" id="p2">
<div class="filter-row">
<input type="date" id="kDate" value="<?php echo date('Y-m-d'); ?>">
<input type="text" id="kSearch" placeholder="ID">
</div>
<div class="tbl-head"><span>ID</span><span>Jumlah bawahan</span><span>Taruhan</span><span>Kinerja</span><span>Setor</span></div>
<div id="kBody"><div class="empty">Tidak ada catatan</div></div>
</div>

<script>
function swTab(i,el){
  document.querySelectorAll('.tab').forEach(function(t){t.classList.remove('on')});
  document.querySelectorAll('.panel').forEach(function(p){p.classList.remove('on')});
  el.classList.add('on');
  document.getElementById('p'+i).classList.add('on');
  if(i===2)loadKinerja();
}
function loadKinerja(){
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'referral_kinerja',date:document.getElementById('kDate').value,search:document.getElementById('kSearch').value})})
  .then(function(r){return r.json()}).then(function(d){
    if(!d.ok||!d.list||!d.list.length){document.getElementById('kBody').innerHTML='<div class="empty">Tidak ada catatan</div>';return}
    var h='';d.list.forEach(function(r){
      h+='<div class="tbl-row"><span>'+r.username+'</span><span>'+r.down_count+'</span><span>'+Number(r.total_turnover/1000).toFixed(2)+'</span><span>'+Number(r.total_turnover/1000).toFixed(2)+'</span><span>'+Number(r.total_deposit/1000).toFixed(2)+'</span></div>';
    });
    document.getElementById('kBody').innerHTML=h;
  }).catch(function(){});
}
document.getElementById('kDate').onchange=loadKinerja;
document.getElementById('kSearch').oninput=loadKinerja;
function showToast(m){var ov=document.createElement('div');ov.className='toast-overlay';var bx=document.createElement('div');bx.className='toast-box';var p=document.createElement('p');p.textContent=m;var btn=document.createElement('button');btn.textContent='Oke';btn.onclick=function(){bx.remove();ov.remove()};bx.appendChild(p);bx.appendChild(btn);document.body.appendChild(ov);document.body.appendChild(bx);ov.onclick=function(){bx.remove();ov.remove()}}
</script>
<?php include 'includes/credit_notify.php'; ?>
</body></html>
