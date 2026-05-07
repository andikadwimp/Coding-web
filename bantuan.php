<?php
require_once 'includes/config.php';
$isLoggedIn = (bool)getUid();  // guest-friendly

header('Content-Type: text/html; charset=UTF-8');
$uid=getUid();$sets=[];
try{$st=$db->query("SELECT `key`,`value` FROM settings");foreach($st->fetchAll() as $r)$sets[$r['key']]=$r['value'];normSettings($sets);}catch(Exception $e){}
$sn=$sets['site_name']??'Situs';
if(!$uid){header('Location: index.php');exit;}
$TIERS=[[100,2],[15000,3],[150000,5],[1500000,10],[6000000,20],[9000000,30]];
$weekStart=date('Y-m-d',strtotime('monday this week'));$weekEnd=date('Y-m-d',strtotime('sunday this week'));$weekId=date('Y-W');
$weekLoss=0;
try{$q=$db->prepare("SELECT COALESCE(SUM(CASE WHEN type='game_transfer' THEN ABS(amount) ELSE 0 END),0) as tb,COALESCE(SUM(CASE WHEN type='game_win' THEN amount ELSE 0 END),0) as tw FROM transactions WHERE user_id=? AND type IN('game_transfer','game_win') AND DATE(created_at)>=? AND DATE(created_at)<=?");$q->execute([$uid,$weekStart,$weekEnd]);$r=$q->fetch();$weekLoss=max(0,($r['tb']-$r['tw'])/1000);}catch(Exception $e){}
$pct=0;foreach($TIERS as $t){if($weekLoss>=$t[0])$pct=$t[1];}
$cashback=round($weekLoss*$pct/100,2);$claimed=false;$claimedAmt=0;
try{$chk=$db->prepare("SELECT amount FROM vip_claims WHERE user_id=? AND claim_type='bantuan' AND period=?");$chk->execute([$uid,$weekId]);$row=$chk->fetch();if($row){$claimed=true;$claimedAmt=$row['amount']/1000;}}catch(Exception $e){}
$nextMon=strtotime('next monday');$rem=max(0,$nextMon-time());
$rD=floor($rem/86400);$rH=floor(($rem%86400)/3600);$rM=floor(($rem%3600)/60);$rS=$rem%60;
?><!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">
<?php require_once 'pwa_head.php'; ?>
<meta name="viewport" content="width=390,user-scalable=no">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&family=Chakra+Petch:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="theme.php?v=<?=time()?>">
<title>Dana Bantuan - <?=$sn?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}html{font-size:16px!important;-webkit-text-size-adjust:100%;text-size-adjust:100%}
body{font-family:'Poppins',sans-serif;background:var(--bg);color:var(--t);min-height:100vh}
.hdr{display:flex;align-items:center;padding:14px 16px;position:sticky;top:0;z-index:10;background:linear-gradient(180deg,var(--bg) 85%,transparent);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px)}.hdr button{width:34px;height:34px;background:var(--tint-1);border:1px solid var(--tint-2);border-radius:10px;color:var(--t2);cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0}.hdr button:active{transform:scale(.92)}.hdr h1{flex:1;text-align:center;font-size:1rem;font-weight:800;font-family:"Chakra Petch",sans-serif;color:var(--pri,var(--sec));letter-spacing:.5px}
.hero{background:linear-gradient(135deg,var(--bg2) 0%,var(--s) 50%,var(--bg2) 100%);padding:26px 20px 30px;text-align:center;position:relative;overflow:hidden;border-radius:0 0 24px 24px;border-bottom:1px solid rgba(var(--pri-rgb,56,189,248),.2)}
.hero::before{content:'';position:absolute;top:-40%;right:-20%;width:280px;height:280px;border-radius:50%;background:radial-gradient(circle,rgba(var(--pri-rgb,56,189,248),.12),transparent 70%);pointer-events:none}
.hero::after{content:'';position:absolute;bottom:-40%;left:-20%;width:240px;height:240px;border-radius:50%;background:radial-gradient(circle,rgba(var(--sec-rgb,56,189,248),.1),transparent 70%);pointer-events:none}
.hero .hc{position:absolute;right:-5px;bottom:0;width:135px;height:135px;background-image:url('asset/char1.png');background-size:contain;background-repeat:no-repeat;background-position:bottom right;opacity:.55;z-index:1;pointer-events:none;animation:heroFloat 4s ease-in-out infinite}
.hero .hcoin{position:absolute;width:36px;height:36px;background-image:url('asset/coin.png');background-size:contain;background-repeat:no-repeat;opacity:.4;pointer-events:none;z-index:0}
.hero .hcoin.c1{top:15px;left:20px;width:32px;height:32px;transform:rotate(-20deg)}
.hero .hcoin.c2{bottom:20px;left:55px;width:26px;height:26px;opacity:.35;transform:rotate(30deg)}
.hero .hcoin.c3{top:60px;left:80px;width:22px;height:22px;opacity:.3;transform:rotate(-10deg)}
@keyframes heroFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-8px)}}
.hero>div{position:relative;z-index:2}
.hero .ht{font-family:'Chakra Petch';font-size:1.6rem;font-weight:900;color:var(--pri,var(--sec));letter-spacing:1.5px;text-shadow:0 2px 12px rgba(var(--pri-rgb,56,189,248),.35)}
.hero .hn{font-family:'Chakra Petch';font-size:2.8rem;font-weight:900;color:#fff;margin-top:4px;letter-spacing:2px;text-shadow:0 2px 12px rgba(var(--pri-rgb,56,189,248),.3)}
.hero .hs{font-size:.68rem;color:rgba(255,255,255,.65);margin-top:6px;font-weight:600}
.tmr{display:flex;justify-content:center;gap:4px;padding:14px 0}.tmr .td{background:var(--tint-2);color:var(--t);padding:6px 10px;border-radius:6px;font-family:'Chakra Petch';font-size:.88rem;font-weight:800;min-width:34px;text-align:center}.tmr .sep{color:var(--t3);font-weight:800;line-height:32px}.tmr .lbl{font-size:.42rem;color:var(--t3);text-align:center;display:block;margin-top:1px}
.wrap{padding:0 16px 110px}.perm{display:flex;justify-content:space-between;padding:12px 14px;border:1px solid var(--bd,var(--bg3));border-radius:10px;margin-bottom:14px;font-size:.72rem}.perm span:first-child{color:var(--t3)}.perm span:last-child{font-weight:800}
.cols{display:flex;justify-content:space-between;padding:0 4px;margin-bottom:8px;font-size:.72rem;font-weight:700}
.tbl{display:flex;flex-direction:column;gap:8px;margin-bottom:16px}.row{display:flex;align-items:center;padding:14px 18px;background:var(--s);border:1px solid var(--bd,var(--bg3));border-radius:10px}.row .ls{flex:1;font-family:'Chakra Petch';font-size:.85rem;font-weight:700}.row .pc{font-size:.85rem;font-weight:800;color:var(--pri,var(--sec))}
.cds{display:flex;gap:10px;margin-bottom:20px}.cd{flex:1;padding:14px;background:var(--s);border:1px solid var(--bd,var(--bg3));border-radius:10px;text-align:center}.cd .cv{font-family:'Chakra Petch';font-size:1.1rem;font-weight:800}.cd .cl{font-size:.62rem;color:var(--t3);margin-top:4px}
.rul{padding:16px;background:var(--s);border:1px solid var(--bd,var(--bg3));border-radius:10px}.rul h3{font-size:.78rem;font-weight:700;text-align:center;margin-bottom:12px}.rul .nt{background:rgba(251,191,36,.06);border:1px solid rgba(251,191,36,.12);border-radius:6px;padding:8px 10px;font-size:.65rem;color:var(--pri,var(--sec));margin-bottom:10px;line-height:1.5}.rul p{font-size:.68rem;color:var(--t3);line-height:1.6;margin-bottom:8px}
.ft{position:fixed;bottom:0;left:0;right:0;padding:12px 16px;background:linear-gradient(180deg,transparent,var(--bg) 30%);z-index:5}.ft button{width:100%;padding:15px;border:none;border-radius:12px;font-size:.9rem;font-weight:800;cursor:pointer;font-family:inherit;color:#fff;background:var(--sec,var(--sec))}.ft button:disabled{opacity:.4}.ft .off{background:var(--s);color:var(--t3);border:1px solid var(--bd,var(--bg3))}
</style></head><body>

<?php if(!$isLoggedIn){ $guestBannerLabel = 'bantuan mingguan'; require __DIR__.'/includes/guest_banner.php'; } ?>
<div class="hdr"><button onclick="history.back()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><polyline points="15 18 9 12 15 6"/></svg></button><h1>Uang bantuan mingguan</h1><div style="width:32px"></div></div>
<div class="hero"><div class="hcoin c1"></div><div class="hcoin c2"></div><div class="hcoin c3"></div><div class="hc"></div><div class="ht">DANA BANTUAN</div><div class="hn">888</div><div class="hs">Cashback mingguan untuk kerugian Anda</div></div>
<div class="tmr"><div><div class="td" id="tD"><?=str_pad($rD,2,'0',STR_PAD_LEFT)?></div><span class="lbl">DAY</span></div><div class="sep">:</div><div><div class="td" id="tH"><?=str_pad($rH,2,'0',STR_PAD_LEFT)?></div><span class="lbl">HRS</span></div><div class="sep">:</div><div><div class="td" id="tM"><?=str_pad($rM,2,'0',STR_PAD_LEFT)?></div><span class="lbl">MIN</span></div><div class="sep">:</div><div><div class="td" id="tS"><?=str_pad($rS,2,'0',STR_PAD_LEFT)?></div><span class="lbl">SEC</span></div></div>
<div class="wrap">
<div class="perm"><span>Hitung Mundur Akhir Aktivitas:</span><span>Permanen</span></div>
<div class="cols"><span>Jumlah kerugian</span><span>Dapat diambil</span></div>
<div class="tbl"><?php foreach($TIERS as $t):?><div class="row"><div class="ls"><?=number_format($t[0],2,',','.')?></div><div class="pc">+<?=number_format($t[1],2,',','.')?>%</div></div><?php endforeach;?></div>
<div class="cds"><div class="cd"><div class="cv"><?=number_format($weekLoss,2,',','.')?></div><div class="cl">Kerugian saya</div></div><div class="cd"><div class="cv" style="color:var(--pri,var(--sec))"><?=$claimed?number_format($claimedAmt,2,',','.'):number_format($cashback,2,',','.')?></div><div class="cl">Dapat diambil</div></div></div>
<div class="rul"><h3>Peraturan Acara</h3><div class="nt">&#9733; 1.00 sebenarnya adalah 1 K, 1K = Rp 1,000</div>
<p>1. Acara ini adalah acara dana bantuan mingguan. Anggota yang kehilangan uang minggu lalu bisa mendapatkan dana bantuan;</p>
<p>2. Bonus harus diklaim secara manual dan akan hangus setelah kedaluwarsa;</p>
<p>3. Bonus (tidak termasuk pokok) memerlukan 1 kali taruhan yang valid untuk ditarik;</p>
<p>4. Hanya pemilik akun yang dapat melakukan operasi manual normal;</p>
<p>5. Platform memiliki hak akhir untuk menafsirkan aktivitas ini.</p></div></div>
<div class="ft"><?php if($claimed):?><button class="off" disabled>Sudah Diklaim</button><?php elseif($cashback<=0):?><button class="off" disabled>Tidak ada kerugian minggu ini</button><?php else:?><button id="cb" onclick="doC()">Klaim <?=number_format($cashback,2,',','.')?>K</button><?php endif;?></div>
<script>var eT=<?=($nextMon*1000)?>;setInterval(function(){var l=Math.max(0,eT-Date.now());document.getElementById('tD').textContent=String(Math.floor(l/864e5)).padStart(2,'0');document.getElementById('tH').textContent=String(Math.floor((l%864e5)/36e5)).padStart(2,'0');document.getElementById('tM').textContent=String(Math.floor((l%36e5)/6e4)).padStart(2,'0');document.getElementById('tS').textContent=String(Math.floor((l%6e4)/1e3)).padStart(2,'0')},1000);
function doC(){var b=document.getElementById('cb');if(!b)return;b.disabled=true;b.textContent='Memproses...';fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'claim_bantuan'})}).then(function(r){return r.json()}).then(function(d){if(d.ok){b.className='off';b.textContent='Berhasil!';setTimeout(function(){location.reload()},1500)}else{b.disabled=false;b.textContent='Klaim';alert(d.error||'Gagal')}}).catch(function(){b.disabled=false;b.textContent='Klaim';alert('Gagal')})}</script><?php include 'includes/credit_notify.php'; ?>
</body></html>
