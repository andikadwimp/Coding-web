<?php
require_once 'includes/config.php';header('Content-Type: text/html; charset=UTF-8');
$uid=getUid();$sets=[];
$isLoggedIn=(bool)$uid;  // guest-friendly check
try{$st=$db->query("SELECT `key`,`value` FROM settings");foreach($st->fetchAll() as $r)$sets[$r['key']]=$r['value'];normSettings($sets);}catch(Exception $e){}
$sn=$sets['site_name']??'Situs';if(!$uid){header('Location: index.php');exit;}
$MILESTONES=[2,3,7,15,30];
$DTIERS=[[60,0.72,258],[200,1.45,588],[400,2.90,1188],[800,7.25,1688],[1700,14.50,2588],[2900,21.75,5688],[5800,50.75,8888],[14500,127.60,25778]];
$regDate='';$daysSinceReg=0;$totalCycleDepo=0;
try{$u=$db->prepare("SELECT created_at,total_deposit FROM users WHERE id=?");$u->execute([$uid]);$r=$u->fetch();$regDate=$r['created_at']??date('Y-m-d');}catch(Exception $e){}

// Calculate current cycle (same logic as backend — 32-day cycle from regDate)
$regTs=strtotime($regDate);$cycleStart=$regTs;$now=time();
while($cycleStart+32*86400<$now)$cycleStart+=32*86400;
$dayInCycle=max(1,floor(($now-$cycleStart)/86400)+1);
$cycleId=date('Y-m-d',$cycleStart);

// Deposit in current cycle (bukan total_deposit all-time)
try{
    $md=$db->prepare("SELECT COALESCE(SUM(nominal),0) FROM deposits WHERE user_id=? AND status='paid' AND created_at>=?");
    $md->execute([$uid,date('Y-m-d H:i:s',$cycleStart)]);
    $totalCycleDepo=floor($md->fetchColumn()/1000);
}catch(Exception $e){}

$daysSinceReg=$dayInCycle; // override old logic
$currentMs=0;foreach($MILESTONES as $m){if($daysSinceReg>=$m)$currentMs=$m;}
$depTier=0;$bonusMin=0;$bonusMax=0;
foreach($DTIERS as $dt){if($totalCycleDepo>=$dt[0]){$depTier=$dt[0];$bonusMin=$dt[1];$bonusMax=$dt[2];}}

// Milestones udah diklaim di cycle ini
$claimedMs=[];
try{
    $q=$db->prepare("SELECT vip_level FROM vip_claims WHERE user_id=? AND claim_type='misteri' AND period=?");
    $q->execute([$uid,$cycleId]);
    foreach($q->fetchAll() as $r)$claimedMs[(int)$r['vip_level']]=true;
}catch(Exception $e){}
$msClaimedCount=count($claimedMs);
?><!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">
<?php require_once 'pwa_head.php'; ?>
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&family=Chakra+Petch:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="theme.php?v=<?=time()?>">
<title>Bonus Misteri - <?=$sn?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}html{font-size:16px!important;-webkit-text-size-adjust:100%;text-size-adjust:100%}body{font-family:'Poppins',sans-serif;background:var(--bg);color:var(--t);min-height:100vh}
.hdr{display:flex;align-items:center;padding:14px 16px;position:sticky;top:0;z-index:10;background:linear-gradient(180deg,var(--bg) 85%,transparent);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)}.hdr button{width:34px;height:34px;background:var(--tint-1);border:1px solid var(--tint-2);border-radius:10px;color:var(--t2);cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0}.hdr button:active{transform:scale(.92)}.hdr h1{flex:1;text-align:center;font-size:1rem;font-weight:800;font-family:"Chakra Petch",sans-serif;color:var(--pri,var(--sec));letter-spacing:.5px}
.hero{background:linear-gradient(135deg,var(--bg2) 0%,var(--s) 50%,var(--bg2) 100%);padding:26px 20px 30px;text-align:center;position:relative;overflow:hidden;border-radius:0 0 24px 24px;border-bottom:1px solid rgba(var(--pri-rgb,56,189,248),.2)}
.hero::before{content:'';position:absolute;top:-40%;right:-20%;width:280px;height:280px;border-radius:50%;background:radial-gradient(circle,rgba(var(--pri-rgb,56,189,248),.12),transparent 70%);pointer-events:none}
.hero::after{content:'';position:absolute;bottom:-40%;left:-20%;width:240px;height:240px;border-radius:50%;background:radial-gradient(circle,rgba(var(--sec-rgb,56,189,248),.1),transparent 70%);pointer-events:none}
.hero .hc{position:absolute;right:-5px;bottom:0;width:140px;height:140px;background-image:url('asset/char3.png');background-size:contain;background-repeat:no-repeat;background-position:bottom right;opacity:.55;z-index:1;pointer-events:none;animation:heroFloat 4s ease-in-out infinite}
.hero .hcoin{position:absolute;width:36px;height:36px;background-image:url('asset/coin.png');background-size:contain;background-repeat:no-repeat;opacity:.4;pointer-events:none;z-index:0}
.hero .hcoin.c1{top:20px;left:15px;width:28px;height:28px;transform:rotate(-20deg)}
.hero .hcoin.c2{bottom:15px;left:40px;width:22px;height:22px;opacity:.3;transform:rotate(30deg)}
@keyframes heroFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
.hero>div{position:relative;z-index:2}
.hero .ht{font-family:'Chakra Petch';font-size:1.55rem;font-weight:900;color:var(--pri,var(--sec));letter-spacing:1.5px;text-shadow:0 2px 12px rgba(var(--pri-rgb,56,189,248),.35)}
.hero .hv{font-family:'Chakra Petch';font-size:1.5rem;font-weight:900;color:var(--pri,var(--sec));margin-top:6px;text-shadow:0 2px 12px rgba(var(--pri-rgb,56,189,248),.35)}
.wrap{padding:16px 16px 30px}
.miles{display:flex;justify-content:space-between;margin-bottom:16px;position:relative;padding:0 4px}.miles::after{content:'';position:absolute;top:14px;left:8%;right:8%;height:3px;background:var(--bd,var(--bg3))}.mil{text-align:center;position:relative;z-index:1}.mil .dot{width:28px;height:28px;border-radius:50%;background:var(--s);border:2px solid var(--bd,var(--bg3));margin:0 auto 4px;display:flex;align-items:center;justify-content:center;font-size:.5rem}.mil .dot.on{background:var(--sec,var(--sec));border-color:var(--sec,var(--sec));color:#fff}.mil .lb{font-size:.62rem;font-weight:700;color:var(--t3)}.mil .lb.on{color:var(--sec,var(--sec))}
.box{background:rgba(251,191,36,.04);border:2px solid rgba(251,191,36,.15);border-radius:14px;padding:16px;text-align:center;margin-bottom:16px}.box .bl{font-size:.68rem;color:var(--t3)}.box .bv{font-family:'Chakra Petch';font-size:1.5rem;font-weight:900;margin-top:4px}.box .bt{font-size:.72rem;font-weight:600;margin-top:6px}
.info{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px}.inf{flex:1;min-width:45%;text-align:center;padding:10px;background:var(--s);border:1px solid var(--bd,var(--bg3));border-radius:10px}.inf .il{font-size:.62rem;color:var(--t3)}.inf .iv{font-family:'Chakra Petch';font-size:.88rem;font-weight:800;margin-top:2px}
.tabs{display:flex;border:1px solid var(--bd,var(--bg3));border-radius:10px;overflow:hidden;margin-bottom:12px}.tab{flex:1;padding:10px;text-align:center;font-size:.72rem;font-weight:700;cursor:pointer;background:transparent;color:var(--t3);border:none;font-family:inherit}.tab.on{background:rgba(var(--sec-rgb,56,189,248),.1);color:var(--sec,var(--sec))}
.pan{display:none}.pan.on{display:block}
.tbl{border:1px solid var(--bd,var(--bg3));border-radius:12px;overflow:hidden;margin-bottom:16px}.th{display:flex;padding:10px 16px;background:rgba(var(--sec-rgb,56,189,248),.06);border-bottom:1px solid var(--bd,var(--bg3))}.th span{flex:1;font-size:.68rem;font-weight:700;color:var(--t3)}.th span:last-child{text-align:right}.tr{display:flex;padding:10px 16px;border-bottom:1px solid var(--tint-1)}.tr:last-child{border:none}.tr span{flex:1;font-size:.72rem}.tr span:last-child{text-align:right;font-family:'Chakra Petch';font-weight:700}
.rul{padding:16px;background:var(--s);border:1px solid var(--bd,var(--bg3));border-radius:10px}.rul h3{font-size:.78rem;font-weight:700;text-align:center;margin-bottom:12px}.rul .nt{background:rgba(251,191,36,.06);border:1px solid rgba(251,191,36,.12);border-radius:6px;padding:8px 10px;font-size:.65rem;color:var(--pri,var(--sec));margin-bottom:10px;line-height:1.5}.rul p{font-size:.68rem;color:var(--t3);line-height:1.6;margin-bottom:8px}
.mbtn{padding:6px 14px;background:var(--pri);border:none;border-radius:6px;color:#fff;font-size:.7rem;font-weight:700;cursor:pointer;font-family:inherit}
.mbtn:disabled{background:var(--bd);color:var(--t3);cursor:not-allowed}
.mbtn.done{background:transparent;color:var(--pri);border:1px solid var(--pri)}
.mbtn:active:not(:disabled){transform:scale(.96)}
</style></head><body>

<?php if(!$isLoggedIn){ $guestBannerLabel = 'bonus misteri'; require __DIR__.'/includes/guest_banner.php'; } ?>
<div class="hdr"><button onclick="history.back()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><polyline points="15 18 9 12 15 6"/></svg></button><h1>Acara Bonus Misteri</h1><div style="width:32px"></div></div>
<div class="hero"><div class="hcoin c1"></div><div class="hcoin c2"></div><div class="hc"></div><div class="ht">Bonus Misteri</div><div class="hv"><?=number_format($bonusMax,2,',','.')?></div></div>
<div class="wrap">
<div class="miles"><?php foreach($MILESTONES as $m):$on=$daysSinceReg>=$m;?><div class="mil"><div class="dot<?=$on?' on':''?>"><?=$on?'&#10003;':''?></div><div class="lb<?=$on?' on':''?>">Hari <?=$m?></div></div><?php endforeach;?></div>
<div class="box"><div class="bl">Saat bonus dapat diklaim:</div><div class="bv"><?=number_format($bonusMin,2,',','.')?> ~ <?=number_format($bonusMax,2,',','.')?></div><div class="bt">Bonus Misteri</div></div>
<div class="info"><div class="inf"><div class="il">Waktu Pendaftaran</div><div class="iv"><?=date('Y-m-d',strtotime($regDate))?></div></div><div class="inf"><div class="il">Hari ke-</div><div class="iv"><?=$daysSinceReg?></div></div><div class="inf"><div class="il">Total Setoran Siklus Ini</div><div class="iv"><?=number_format($totalCycleDepo,2,',','.')?></div></div><div class="inf"><div class="il">Milestone Diklaim</div><div class="iv"><?=$msClaimedCount?>/5</div></div></div>
<div class="tabs"><button class="tab on" onclick="sw(0,this)">Lingkup setoran</button><button class="tab" onclick="sw(1,this)">Bonus Misteri</button></div>
<div class="pan on" id="p0"><div class="tbl"><div class="th"><span>Lingkup setoran</span><span>Bonus Misteri</span></div>
<?php foreach($DTIERS as $dt):?><div class="tr"><span><?=number_format($dt[0],2,',','.')?></span><span><?=number_format($dt[1],2,',','.')?>~<?=number_format($dt[2],2,',','.')?></span></div><?php endforeach;?></div></div>
<div class="pan" id="p1"><div class="tbl"><div class="th"><span>Milestone</span><span>Status</span></div>
<?php foreach($MILESTONES as $m):
    $reached=$daysSinceReg>=$m;
    $claimed=isset($claimedMs[$m]);
    $hasDepo=$bonusMax>0;
?>
<div class="tr">
  <span>Hari <?=$m?></span>
  <span>
    <?php if($claimed):?>
      <button class="mbtn done" disabled><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="14" height="14" style="vertical-align:-2px;display:inline-block"><polyline points="20 6 9 17 4 12"/></svg> Diklaim</button>
    <?php elseif(!$reached):?>
      <button class="mbtn" disabled>Hari <?=$m?></button>
    <?php elseif(!$hasDepo):?>
      <button class="mbtn" disabled>Butuh depo</button>
    <?php else:?>
      <button class="mbtn" onclick="claimMisteri(<?=$m?>,this)">Klaim</button>
    <?php endif;?>
  </span>
</div>
<?php endforeach;?>
</div></div>
<div class="rul"><h3>Peraturan Acara</h3><div class="nt">&#9733; 1.00 sebenarnya adalah 1 K, 1K = Rp 1,000</div>
<p>1. Bonus misteri berdasarkan total deposit dan lama bermain;</p>
<p>2. Bonus acak antara nilai minimum dan maksimum sesuai tier;</p>
<p>3. Bonus harus diklaim secara manual pada setiap milestone;</p>
<p>4. Hanya pemilik akun yang dapat melakukan operasi manual normal;</p>
<p>5. Platform memiliki hak akhir untuk menafsirkan aktivitas ini.</p></div></div>
<script>
function sw(i,el){document.querySelectorAll('.tab').forEach(function(t){t.classList.remove('on')});document.querySelectorAll('.pan').forEach(function(p){p.classList.remove('on')});el.classList.add('on');document.getElementById('p'+i).classList.add('on')}

function claimMisteri(day,btn){
  btn.disabled=true;btn.textContent='Proses...';
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'claim_misteri',day:day})})
    .then(function(r){return r.json()})
    .then(function(d){
      if(d.ok){showToastMs('Dapat Rp '+(d.amount||0).toLocaleString('id-ID')+'!');setTimeout(function(){location.reload()},1500)}
      else{btn.disabled=false;btn.textContent='Klaim';showToastMs(d.error||'Gagal')}
    }).catch(function(){btn.disabled=false;btn.textContent='Klaim';showToastMs('Gagal terhubung')});
}
function showToastMs(msg){
  var t=document.createElement('div');
  t.style.cssText='position:fixed;top:20%;left:50%;transform:translateX(-50%);background:var(--s);color:var(--t);padding:12px 20px;border-radius:10px;font-size:.85rem;font-weight:600;z-index:9999;box-shadow:0 4px 16px rgba(0,0,0,.3);border:1px solid var(--bd);max-width:300px;text-align:center';
  t.textContent=msg;document.body.appendChild(t);setTimeout(function(){t.remove()},2500);
}
</script><?php include 'includes/credit_notify.php'; ?>
</body></html>
