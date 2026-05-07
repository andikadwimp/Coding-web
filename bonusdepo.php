<?php
require_once 'includes/config.php';header('Content-Type: text/html; charset=UTF-8');
$uid=getUid();$sets=[];
$isLoggedIn=(bool)$uid;  // guest-friendly check
try{$st=$db->query("SELECT `key`,`value` FROM settings");foreach($st->fetchAll() as $r)$sets[$r['key']]=$r['value'];normSettings($sets);}catch(Exception $e){}
$sn=$sets['site_name']??'Situs';if(!$uid){header('Location: index.php');exit;}
$todayDepoRp=0;
try{$td=$db->prepare("SELECT COALESCE(SUM(nominal),0) FROM deposits WHERE user_id=? AND status='paid' AND DATE(created_at)=CURDATE()");$td->execute([$uid]);$todayDepoRp=(int)$td->fetchColumn();}catch(Exception $e){}
$todayDepo=floor($todayDepoRp/1000);
$TIERS=[[50,5],[300,18],[500,38],[1000,68],[3000,188],[8000,388],[30000,1888],[50000,3888]];
$currentBonus=0;foreach($TIERS as $t){if($todayDepo>=$t[0])$currentBonus=$t[1];}

// Cek udah klaim hari ini
$alreadyClaimed=false;
try{
    $chk=$db->prepare("SELECT amount FROM vip_claims WHERE user_id=? AND claim_type='bonus_depo' AND period=?");
    $chk->execute([$uid,date('Y-m-d')]);
    $cRow=$chk->fetch();
    if($cRow)$alreadyClaimed=true;
}catch(Exception $e){}

// TOTAL TO gabungan (deposit+bonus_id + bonus klaim event)
$toStatus=null;
try{
    require_once __DIR__.'/includes/bonus_to.php';
    $bcs=bonusTO_status($db,$uid); // cuma bonus klaim event

    // Plus TO dari deposit+bonus_id
    $depoRem=0;$depoCount=0;
    $curTO=(int)$db->query("SELECT total_turnover FROM users WHERE id=$uid")->fetchColumn();
    $bd=$db->prepare("SELECT d.nominal,d.bonus_amount,d.turnover_at_deposit,b.turnover_x
        FROM deposits d LEFT JOIN bonuses b ON b.id=d.bonus_id
        WHERE d.user_id=? AND d.status='paid' AND d.bonus_id IS NOT NULL AND b.turnover_x>0 AND d.turnover_met=0");
    $bd->execute([$uid]);
    foreach($bd->fetchAll() as $r){
        $target=($r['nominal']+$r['bonus_amount'])*$r['turnover_x'];
        $done=max(0,$curTO-$r['turnover_at_deposit']);
        $rem=max(0,$target-$done);
        if($rem>0){$depoRem+=$rem;$depoCount++;}
    }

    $totalRem=$bcs['total_remaining']+$depoRem;
    $totalCount=$bcs['pending_count']+$depoCount;
    $toStatus=['ok'=>($totalRem===0),'total_remaining'=>$totalRem,'pending_count'=>$totalCount];
}catch(Exception $e){}
?><!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">
<?php require_once 'pwa_head.php'; ?>
<meta name="viewport" content="width=390,user-scalable=no">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&family=Chakra+Petch:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="theme.php?v=<?=time()?>">
<title>Bonus Deposit - <?=$sn?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}html{font-size:16px!important;-webkit-text-size-adjust:100%;text-size-adjust:100%}body{font-family:'Poppins',sans-serif;background:var(--bg);color:var(--t);min-height:100vh}
.hdr{display:flex;align-items:center;padding:14px 16px;position:sticky;top:0;z-index:10;background:linear-gradient(180deg,var(--bg) 85%,transparent);height:34px;background:var(--tint-1);border:1px solid var(--tint-2);border-radius:10px;color:var(--t2,#8cc);cursor:pointer;display:flex;align-items:center;justify-content:center}.hdr button:active{transform:scale(.92)}.hdr h1{flex:1;text-align:center;font-size:.92rem;font-weight:800;font-family:"Chakra Petch",sans-serif;color:var(--pri,var(--sec));letter-spacing:.5px}
.hero{background:linear-gradient(135deg,var(--bg2) 0%,var(--s) 50%,var(--bg2) 100%);padding:26px 20px 30px;text-align:center;position:relative;overflow:hidden;border-radius:0 0 24px 24px;border-bottom:1px solid rgba(var(--pri-rgb,56,189,248),.2)}
.hero::before{content:'';position:absolute;top:-40%;right:-20%;width:280px;height:280px;border-radius:50%;background:radial-gradient(circle,rgba(var(--pri-rgb,56,189,248),.12),transparent 70%);pointer-events:none}
.hero::after{content:'';position:absolute;bottom:-40%;left:-20%;width:240px;height:240px;border-radius:50%;background:radial-gradient(circle,rgba(var(--pri-rgb,56,189,248),.12),transparent 70%);pointer-events:none}
.hero .hc{position:absolute;right:-10px;bottom:0;width:130px;height:130px;background-image:url('asset/char1.png');background-size:contain;background-repeat:no-repeat;background-position:bottom right;opacity:.55;z-index:1;pointer-events:none;animation:heroFloat 4s ease-in-out infinite}
.hero .hcoin{position:absolute;background-image:url('asset/coin.png');background-size:contain;background-repeat:no-repeat;opacity:.4;pointer-events:none;z-index:0}
.hero .hcoin.c1{top:15px;left:20px;width:34px;height:34px;transform:rotate(-15deg)}
.hero .hcoin.c2{bottom:20px;left:50px;width:26px;height:26px;opacity:.35;transform:rotate(25deg)}
.hero .hcoin.c3{top:55px;left:85px;width:20px;height:20px;opacity:.3;transform:rotate(-30deg)}
@keyframes heroFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
.hero>div{position:relative;z-index:2}
.hero .ht{font-family:'Chakra Petch';font-size:1.5rem;font-weight:900;color:var(--pri,var(--sec));letter-spacing:1.5px;text-shadow:0 2px 12px rgba(var(--pri-rgb,56,189,248),.35)}
.hero .hs{font-size:.7rem;color:rgba(255,255,255,.7);margin-top:6px;font-weight:600}
.pill{display:flex;align-items:center;gap:10px;justify-content:center;margin:-20px auto 0;position:relative;z-index:2;background:linear-gradient(135deg,#7c1d1d,#991b1b);border:3px solid #fbbf24;border-radius:30px;padding:10px 28px;width:fit-content}.pill .ic{width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,#fbbf24,#f59e0b);display:flex;align-items:center;justify-content:center;font-size:.8rem}.pill .l1{font-size:.68rem;color:rgba(255,255,255,.8)}.pill .l2{font-family:'Chakra Petch';font-size:1rem;font-weight:900;color:var(--pri,var(--sec))}
.wrap{padding:16px 16px 110px}
.akum{display:flex;justify-content:space-between;align-items:center;padding:14px 16px;border:1px solid var(--bd,var(--bg3));border-radius:10px;margin-bottom:14px}.akum .al{font-size:.82rem;font-weight:700}.akum .av{font-family:'Chakra Petch';font-size:.88rem;font-weight:800;color:var(--pri,var(--sec))}
.tbl{border:1px solid var(--bd,var(--bg3));border-radius:12px;overflow:hidden;margin-bottom:16px}.th{display:flex;padding:12px 16px;border-bottom:1px solid var(--bd,var(--bg3))}.th span{flex:1;font-size:.72rem;font-weight:700;color:var(--t3)}.th span:last-child{text-align:right}
.tr{display:flex;align-items:center;padding:12px 16px;border-bottom:1px solid var(--tint-1)}.tr:nth-child(even){background:rgba(var(--sec-rgb,56,189,248),.03)}.tr:last-child{border:none}.tr .dp{flex:1;display:flex;align-items:center;gap:6px;font-family:'Chakra Petch';font-size:.82rem;font-weight:700}.tr .dp .ig{color:var(--pri,var(--sec));font-size:.7rem}.tr .rw{flex:1;text-align:right;font-family:'Chakra Petch';font-size:.82rem;font-weight:800;color:var(--sec,var(--sec))}
.rul{padding:16px;background:var(--s);border:1px solid var(--bd,var(--bg3));border-radius:10px;margin-bottom:16px}.rul h3{font-size:.78rem;font-weight:700;text-align:center;margin-bottom:12px}.rul .nt{background:rgba(251,191,36,.06);border:1px solid rgba(251,191,36,.12);border-radius:6px;padding:8px 10px;font-size:.65rem;color:var(--pri,var(--sec));margin-bottom:10px;line-height:1.5}.rul p{font-size:.68rem;color:var(--t3);line-height:1.6;margin-bottom:8px}
.ft{position:fixed;bottom:0;left:0;right:0;padding:12px 16px;background:linear-gradient(180deg,transparent,var(--bg) 30%);z-index:5}.ft button{width:100%;padding:15px;border:none;border-radius:12px;font-size:.9rem;font-weight:800;cursor:pointer;font-family:inherit;color:#fff;background:var(--sec,var(--sec))}.ft button:disabled{opacity:.4}.ft .off{background:var(--s);color:var(--t3);border:1px solid var(--bd,var(--bg3))}
</style></head><body>

<?php if(!$isLoggedIn){ $guestBannerLabel = 'bonus deposit'; require __DIR__.'/includes/guest_banner.php'; } ?>
<div class="hdr"><button onclick="history.back()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><polyline points="15 18 9 12 15 6"/></svg></button><h1>Bonus tambahan untuk deposit</h1><div style="width:32px"></div></div>
<div class="hero"><div class="hcoin c1"></div><div class="hcoin c2"></div><div class="hcoin c3"></div><div class="hc"></div><div class="ht">BONUS DEPOSIT</div><div class="hs">Deposit lebih banyak, bonus lebih besar!</div></div>
<div class="pill"><div class="ic">&#9733;</div><div><div class="l1">Dapat diambil</div><div class="l2"><?=number_format($currentBonus,2,',','.')?></div></div></div>
<div class="wrap">
<div class="akum"><div class="al">Setor Akumulasi</div><div class="av"><?=number_format($todayDepo,2,',','.')?></div></div>

<?php if($toStatus&&!$toStatus['ok']):?>
<?php // TO display dipindah ke withdraw.php — di menu bonus ini ga perlu ditampilkan ?>
<?php endif;?>

<div class="tbl"><div class="th"><span>Jumlah deposit</span><span>Jumlah Hadiah</span></div>
<?php foreach($TIERS as $t):?><div class="tr"><div class="dp"><span class="ig">&#9670;</span> <?=number_format($t[0],2,',','.')?></div><div class="rw">+<?=number_format($t[1],2,',','.')?></div></div><?php endforeach;?></div>
<div class="rul"><h3>Peraturan Acara</h3><div class="nt">&#9733; 1.00 sebenarnya adalah 1 K, 1K = Rp 1,000</div>
<p>Bonus Akumulasi Deposit Harian. Semakin banyak total deposit Anda dalam satu hari, semakin besar bonus yang bisa Anda klaim.</p>
<p>1. Bonus harus memenuhi turnover 1x (sama dengan jumlah bonus) sebelum dapat ditarik.</p>
<p>2. Bonus hanya berlaku pada hari yang sama dan tidak dapat diakumulasi.</p>
<p>3. Bonus akan hangus jika tidak diklaim pada hari yang sama.</p>
<p>4. Platform berhak mengubah atau membatalkan promo kapan saja.</p>
<p>5. Semua keputusan platform bersifat final.</p></div></div>
<div class="ft">
<?php if($alreadyClaimed):?>
  <button class="off" disabled><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="14" height="14" style="vertical-align:-2px;display:inline-block"><polyline points="20 6 9 17 4 12"/></svg> Sudah Diklaim Hari Ini</button>
<?php elseif($currentBonus>0):?>
  <button id="btnClaim" onclick="doClaim(this)">Klaim Rp <?=number_format($currentBonus*1000,0,',','.')?></button>
<?php else:?>
  <button class="off" disabled>Deposit dulu untuk mendapatkan bonus</button>
<?php endif;?>
</div>

<script>
function doClaim(btn){
  btn.disabled=true;btn.textContent='Memproses...';
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'claim_bonus_depo'})})
    .then(function(r){return r.json()})
    .then(function(d){
      if(d.ok){
        btn.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" width="14" height="14" style="vertical-align:-2px;display:inline-block"><polyline points="20 6 9 17 4 12"/></svg> Berhasil Klaim!';
        btn.className='off';
        showToast('Bonus Rp '+(d.amount||0).toLocaleString('id-ID')+' masuk ke saldo');
        setTimeout(function(){location.reload()},1800);
      }else{
        btn.disabled=false;btn.textContent='Klaim';
        showToast(d.error||'Gagal klaim');
      }
    })
    .catch(function(){btn.disabled=false;btn.textContent='Klaim';showToast('Gagal terhubung')});
}
function showToast(msg){
  var t=document.createElement('div');
  t.style.cssText='position:fixed;top:20%;left:50%;transform:translateX(-50%);background:var(--s);color:var(--t);padding:12px 20px;border-radius:10px;font-size:.85rem;font-weight:600;z-index:9999;box-shadow:0 4px 16px rgba(0,0,0,.3);border:1px solid var(--bd);max-width:300px;text-align:center';
  t.textContent=msg;
  document.body.appendChild(t);
  setTimeout(function(){t.remove()},2500);
}
</script>
<?php include 'includes/credit_notify.php'; ?>
</body></html>
