<?php
require_once 'includes/config.php';header('Content-Type: text/html; charset=UTF-8');
$uid=getUid();$sets=[];
$isLoggedIn=(bool)$uid;  // guest-friendly check
try{$st=$db->query("SELECT `key`,`value` FROM settings");foreach($st->fetchAll() as $r)$sets[$r['key']]=$r['value'];normSettings($sets);}catch(Exception $e){}
$sn=$sets['site_name']??'Situs';if(!$uid){header('Location: index.php');exit;}
$TIERS=[[100,5],[300,18],[500,35],[1000,80],[2000,180],[3000,300],[5000,550],[8000,950],[15000,1800],[30000,3800],[50000,6800],[80000,10800],[150000,22000],[300000,48000],[500000,88000],[800000,168000],[1000000,258000]];
$monthDepo=0;
try{$md=$db->prepare("SELECT COALESCE(SUM(nominal),0) FROM deposits WHERE user_id=? AND status='paid' AND MONTH(created_at)=MONTH(NOW()) AND YEAR(created_at)=YEAR(NOW())");$md->execute([$uid]);$monthDepo=floor($md->fetchColumn()/1000);}catch(Exception $e){}
$myReward=0;foreach($TIERS as $t){if($monthDepo>=$t[0])$myReward=$t[1];}
$maxReward=$TIERS[count($TIERS)-1][1];

// Cek udah klaim bulan ini?
$alreadyClaimed=false;
try{
    $chk=$db->prepare("SELECT id FROM vip_claims WHERE user_id=? AND claim_type='apresiasi' AND period=?");
    $chk->execute([$uid,date('Y-m')]);
    if($chk->fetch())$alreadyClaimed=true;
}catch(Exception $e){}
?><!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">
<?php require_once 'pwa_head.php'; ?>
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&family=Chakra+Petch:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="theme.php?v=<?=time()?>">
<title>Apresiasi Anggota - <?=$sn?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}html{font-size:16px!important;-webkit-text-size-adjust:100%;text-size-adjust:100%}body{font-family:'Poppins',sans-serif;background:var(--bg);color:var(--t);min-height:100vh}
.hdr{display:flex;align-items:center;padding:14px 16px;position:sticky;top:0;z-index:10;background:linear-gradient(180deg,var(--bg) 85%,transparent);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)}.hdr button{width:34px;height:34px;background:var(--tint-1);border:1px solid var(--tint-2);border-radius:10px;color:var(--t2);cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0}.hdr button:active{transform:scale(.92)}.hdr h1{flex:1;text-align:center;font-size:1rem;font-weight:800;font-family:"Chakra Petch",sans-serif;color:#fbbf24;letter-spacing:.5px}
.hero{background:linear-gradient(135deg,var(--bg2) 0%,var(--s) 50%,var(--bg2) 100%);padding:26px 20px 30px;text-align:center;position:relative;overflow:hidden;border-radius:0 0 24px 24px;border-bottom:1px solid rgba(var(--pri-rgb,56,189,248),.2)}
.hero::before{content:'';position:absolute;top:-30%;left:-20%;width:260px;height:260px;border-radius:50%;background:radial-gradient(circle,rgba(var(--pri-rgb,56,189,248),.12),transparent 70%);pointer-events:none}
.hero::after{content:'';position:absolute;bottom:-40%;right:-20%;width:260px;height:260px;border-radius:50%;background:radial-gradient(circle,rgba(var(--sec-rgb,56,189,248),.1),transparent 70%);pointer-events:none}
.hero .hc{position:absolute;right:-10px;bottom:0;width:130px;height:130px;background-image:url('asset/char2.png');background-size:contain;background-repeat:no-repeat;background-position:bottom right;opacity:.55;z-index:1;pointer-events:none}
.hero .hcoin{position:absolute;width:40px;height:40px;background-image:url('asset/coin.png');background-size:contain;background-repeat:no-repeat;opacity:.4;pointer-events:none;z-index:0}
.hero .hcoin.c1{top:15px;left:20px;width:30px;height:30px;transform:rotate(-15deg)}
.hero .hcoin.c2{bottom:20px;left:50px;width:24px;height:24px;opacity:.25;transform:rotate(25deg)}
.hero>div{position:relative;z-index:2}
.hero .ht{font-family:'Chakra Petch';font-size:1.55rem;font-weight:900;color:var(--pri,var(--sec));letter-spacing:1.5px;text-shadow:0 2px 12px rgba(var(--pri-rgb,56,189,248),.35)}
.hero .hs{font-size:.72rem;color:rgba(255,255,255,.7);margin-top:6px;font-weight:600}
.hero .hd{font-style:italic;color:var(--pri,var(--sec));font-size:.68rem;margin-top:4px;font-weight:600;opacity:.85}
.hero .pill{display:inline-block;padding:6px 20px;background:rgba(var(--sec-rgb,56,189,248),.08);border:1px solid rgba(var(--sec-rgb,56,189,248),.2);border-radius:20px;font-size:.65rem;color:var(--sec,var(--sec));margin-top:12px;font-weight:600}
.wrap{padding:16px 16px 110px}
.mx{background:rgba(251,191,36,.04);border:2px solid rgba(251,191,36,.15);border-radius:14px;padding:16px;text-align:center;margin-bottom:16px}.mx .ml{font-size:.72rem;font-weight:600;color:var(--t3)}.mx .mv{font-family:'Chakra Petch';font-size:1.8rem;font-weight:900}
.stats{display:flex;gap:10px;margin-bottom:16px}.stat{flex:1;text-align:center}.stat .sl{font-size:.68rem;font-weight:600}.stat .sv{font-family:'Chakra Petch';font-size:1.1rem;font-weight:800}
.sec{font-size:.82rem;font-weight:700;margin-bottom:8px;display:flex;align-items:center;gap:6px}.sec::before{content:'';display:inline-block;width:4px;height:16px;background:var(--sec,var(--sec));border-radius:2px}
.tbl{border:1px solid var(--bd,var(--bg3));border-radius:12px;overflow:hidden;margin-bottom:16px}.th{display:flex;padding:10px 16px;background:rgba(var(--sec-rgb,56,189,248),.06);border-bottom:1px solid var(--bd,var(--bg3))}.th span{flex:1;font-size:.68rem;font-weight:700;color:var(--t3)}.th span:last-child{text-align:right}.tr{display:flex;padding:10px 16px;border-bottom:1px solid var(--tint-1)}.tr:last-child{border:none}.tr span{flex:1;font-size:.75rem}.tr span:first-child{font-weight:600}.tr span:last-child{text-align:right;font-family:'Chakra Petch';font-weight:700}
.rul{padding:16px;background:var(--s);border:1px solid var(--bd,var(--bg3));border-radius:10px}.rul h3{font-size:.78rem;font-weight:700;text-align:center;margin-bottom:12px}.rul .nt{background:rgba(251,191,36,.06);border:1px solid rgba(251,191,36,.12);border-radius:6px;padding:8px 10px;font-size:.65rem;color:var(--pri,var(--sec));margin-bottom:10px;line-height:1.5}.rul p{font-size:.68rem;color:var(--t3);line-height:1.6;margin-bottom:8px}
.ft{position:fixed;bottom:0;left:0;right:0;padding:12px 16px;background:linear-gradient(180deg,transparent,var(--bg) 30%);z-index:5}.ft button{width:100%;padding:15px;border:none;border-radius:12px;font-size:.9rem;font-weight:800;cursor:pointer;font-family:inherit;color:#fff;background:var(--sec,var(--sec))}.ft button:disabled{opacity:.4}.ft .off{background:var(--s);color:var(--t3);border:1px solid var(--bd,var(--bg3))}
</style></head><body>

<?php if(!$isLoggedIn){ $guestBannerLabel = 'apresiasi VIP'; require __DIR__.'/includes/guest_banner.php'; } ?>
<div class="hdr"><button onclick="history.back()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><polyline points="15 18 9 12 15 6"/></svg></button><h1>Apresiasi Anggota</h1><div style="width:32px"></div></div>
<div class="hero"><div class="hcoin c1"></div><div class="hcoin c2"></div><div class="hc"></div><div class="ht">APRESIASI ANGGOTA</div><div class="hs">Waktu Pengambilan</div><div class="hd">Setiap tanggal 5 setiap bulannya</div><div class="pill">&#9733; Waktu Berakhirnya: Selamanya</div></div>
<div class="wrap">
<div class="mx"><div class="ml">Hadiah Maksimal</div><div class="mv"><?=number_format($maxReward,2,',','.')?></div></div>
<div class="stats"><div class="stat"><div class="sl">Deposit Bulan Ini</div><div class="sv"><?=number_format($monthDepo)?>K</div></div><div class="stat"><div class="sl">Hadiah Anda</div><div class="sv" style="color:var(--pri,var(--sec))"><?=number_format($myReward)?>K</div></div></div>
<div class="sec">Tabel Hadiah</div>
<div class="tbl"><div class="th"><span>Min. Deposit</span><span>Hadiah</span></div>
<?php foreach($TIERS as $i=>$t):if($i>10)break;?><div class="tr"><span><?=number_format($t[0])?>K</span><span><?=number_format($t[1],2,',','.')?></span></div><?php endforeach;?></div>
<div class="rul"><h3>Peraturan Acara</h3><div class="nt">&#9733; 1.00 sebenarnya adalah 1 K, 1K = Rp 1,000</div>
<p>1. Apresiasi anggota diberikan setiap tanggal 5. Bonus dihitung berdasarkan total deposit bulan sebelumnya;</p>
<p>2. Bonus harus diklaim secara manual dan akan hangus jika tidak diklaim;</p>
<p>3. Bonus (tidak termasuk pokok) memerlukan 1 kali taruhan yang valid untuk ditarik;</p>
<p>4. Hanya pemilik akun yang dapat melakukan operasi manual normal;</p>
<p>5. Platform memiliki hak akhir untuk menafsirkan aktivitas ini.</p></div></div>
<div class="ft">
<?php if($alreadyClaimed):?>
  <button class="off" disabled><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="14" height="14" style="vertical-align:-2px;display:inline-block"><polyline points="20 6 9 17 4 12"/></svg> Sudah Diklaim Bulan Ini</button>
<?php elseif($myReward>0):?>
  <button id="btnClaim" onclick="doClaim(this)">Klaim Rp <?=number_format($myReward*1000,0,',','.')?></button>
<?php else:?>
  <button class="off" disabled>Deposit dulu untuk mendapatkan hadiah</button>
<?php endif;?>
</div>

<script>
function doClaim(btn){
  btn.disabled=true;btn.textContent='Memproses...';
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'claim_apresiasi'})})
    .then(function(r){return r.json()})
    .then(function(d){
      if(d.ok){btn.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="14" height="14" style="vertical-align:-2px;display:inline-block"><polyline points="20 6 9 17 4 12"/></svg> Berhasil!';btn.className='off';
        showToast('Bonus Rp '+(d.amount||0).toLocaleString('id-ID')+' masuk saldo');
        setTimeout(function(){location.reload()},1800);
      }else{btn.disabled=false;btn.textContent='Klaim';showToast(d.error||'Gagal klaim')}
    }).catch(function(){btn.disabled=false;btn.textContent='Klaim';showToast('Gagal terhubung')});
}
function showToast(msg){
  var t=document.createElement('div');
  t.style.cssText='position:fixed;top:20%;left:50%;transform:translateX(-50%);background:var(--s);color:var(--t);padding:12px 20px;border-radius:10px;font-size:.85rem;font-weight:600;z-index:9999;box-shadow:0 4px 16px rgba(0,0,0,.3);border:1px solid var(--bd);max-width:300px;text-align:center';
  t.textContent=msg;document.body.appendChild(t);setTimeout(function(){t.remove()},2500);
}
</script>
<?php include 'includes/credit_notify.php'; ?>
</body></html>
