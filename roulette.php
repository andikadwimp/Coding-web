<?php
require_once 'includes/config.php';header('Content-Type: text/html; charset=UTF-8');
$uid=getUid();$sets=[];
$isLoggedIn=(bool)$uid;  // guest-friendly check
try{$st=$db->query("SELECT `key`,`value` FROM settings");foreach($st->fetchAll() as $r)$sets[$r['key']]=$r['value'];normSettings($sets);}catch(Exception $e){}
$sn=$sets['site_name']??'Situs';if(!$uid){header('Location: index.php');exit;}
try{$db->exec("ALTER TABLE vip_claims MODIFY claim_type VARCHAR(30)");}catch(Exception $e){}
try{$db->exec("ALTER TABLE vip_claims MODIFY period VARCHAR(30)");}catch(Exception $e){}
try{$db->exec("ALTER TABLE vip_claims DROP INDEX uq_claim");}catch(Exception $e){}
try{$db->exec("ALTER TABLE vip_claims DROP INDEX uq_claim2");}catch(Exception $e){}
$session=null;
try{$ss=$db->prepare("SELECT * FROM vip_claims WHERE user_id=? AND claim_type='roulette_session' ORDER BY id DESC LIMIT 1");$ss->execute([$uid]);$session=$ss->fetch();}catch(Exception $e){}
$cycleEnd=0;$accumulated=0;
if($session){$cycleEnd=strtotime($session['period'].' +3 days');$accumulated=$session['amount']/1000;if($cycleEnd<time()){$accumulated=0;$session=null;}}
if(!$session){$cycleEnd=time()+3*86400;$period=date('Y-m-d');try{$db->prepare("INSERT INTO vip_claims(user_id,claim_type,period,vip_level,amount) VALUES(?,?,?,?,?)")->execute([$uid,'roulette_session',$period,0,0]);$session=['period'=>$period,'amount'=>0];}catch(Exception $e){}}
$sessionSpins=0;
try{$ts=$db->prepare("SELECT COUNT(*) FROM vip_claims WHERE user_id=? AND claim_type='roulette_spin' AND created_at>=?");$ts->execute([$uid,$session['period']]);$sessionSpins=(int)$ts->fetchColumn();}catch(Exception $e){}

// Hitung invite spin tersedia (downline yg sudah depo paid, claim belum dipakai)
try{$db->exec("CREATE TABLE IF NOT EXISTS roulette_invite_claims (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY, owner_id INT UNSIGNED NOT NULL, downline_id INT UNSIGNED NOT NULL, used_at DATETIME DEFAULT NULL, created_at DATETIME DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY uq_inv (owner_id, downline_id)) ENGINE=InnoDB");}catch(Exception $e){}
$inviteSpinsAvailable=0;$validFriends=0;
try{
  $myCode=$db->prepare("SELECT ref_code FROM users WHERE id=?");$myCode->execute([$uid]);$rc=$myCode->fetchColumn();
  if($rc){
    $newDls=$db->prepare("SELECT u.id FROM users u WHERE u.referred_by=? AND EXISTS(SELECT 1 FROM deposits d WHERE d.user_id=u.id AND d.status='paid' LIMIT 1) AND u.id NOT IN (SELECT downline_id FROM roulette_invite_claims WHERE owner_id=?)");
    $newDls->execute([$rc,$uid]);
    foreach($newDls->fetchAll() as $dl){
      try{$db->prepare("INSERT IGNORE INTO roulette_invite_claims (owner_id, downline_id) VALUES (?,?)")->execute([$uid,$dl['id']]);}catch(Exception $e){}
    }
    $cnt=$db->prepare("SELECT COUNT(*) FROM roulette_invite_claims WHERE owner_id=? AND used_at IS NULL");
    $cnt->execute([$uid]);$inviteSpinsAvailable=(int)$cnt->fetchColumn();
    $tvf=$db->prepare("SELECT COUNT(*) FROM roulette_invite_claims WHERE owner_id=?");
    $tvf->execute([$uid]);$validFriends=(int)$tvf->fetchColumn();
  }
}catch(Exception $e){}

$sessionMax=2;
$spinsLeft=max(0,$sessionMax-$sessionSpins)+$inviteSpinsAvailable;
$target=100;$remaining=max(0,$target-$accumulated);$progress=min(100,($accumulated/$target)*100);
$remTime=max(0,$cycleEnd-time());$rD=floor($remTime/86400);$rH=floor(($remTime%86400)/3600);$rM=floor(($remTime%3600)/60);$rS=$remTime%60;
?><!DOCTYPE html><html lang="id"><head><meta charset="UTF-8">
<?php require_once 'pwa_head.php'; ?>
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&family=Chakra+Petch:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="theme.php?v=<?=time()?>">
<title>Mendapatkan 100.00 Emas Gratis - <?=$sn?></title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
html{font-size:16px!important;-webkit-text-size-adjust:100%;text-size-adjust:100%}
body{font-family:'Plus Jakarta Sans','Outfit','Poppins',sans-serif;background:var(--bg);color:var(--t);min-height:100vh;overflow-x:hidden}

.rl-hdr{display:flex;align-items:center;padding:14px 16px;background:var(--bg2);position:sticky;top:0;z-index:10;border-bottom:1px solid var(--tint-2)}
.rl-hdr button{width:36px;height:36px;background:none;border:none;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;padding:0}
.rl-hdr h1{flex:1;text-align:center;font-size:1.05rem;font-weight:700;color:#fff;margin-right:36px}

.rl-info{margin:10px 12px 0;background:var(--tint-1);border:1px solid var(--tint-2);border-radius:14px;padding:14px;text-align:center}
.rl-info .pt-txt{font-size:1rem;color:#fff;font-weight:500}
.rl-info .pt-txt b{color:#f59e0b;font-weight:800;font-size:1.3rem;margin-left:8px}
.rl-info .inv-btn{display:flex;align-items:center;justify-content:center;gap:10px;background:linear-gradient(180deg,var(--pri) 0%,var(--pri-d) 100%)!important;color:#fff!important;border-radius:10px;padding:12px;margin-top:10px;text-decoration:none;font-weight:700;font-size:.88rem;box-shadow:0 4px 12px rgba(var(--pri-rgb),.3)}
.rl-info .inv-btn svg{width:20px;height:20px}

.rl-wheel-area{position:relative;width:100%;padding:160px 0 0;display:flex;flex-direction:column;align-items:center}

/* Karakter PG Soft di BELAKANG wheel — gambar portrait (675x1188)
   Karakter utama ada di tengah-bawah image (y=35-90%), atas image whitespace
   Geser image ke ATAS biar bagian karakter (kepala) ke atas wheel */
.rl-chars{position:absolute;top:-80px;left:50%;transform:translateX(-50%);width:420px;height:520px;z-index:1;pointer-events:none;overflow:hidden}
.rl-chars img{position:absolute;top:0;left:50%;transform:translateX(-50%);width:420px;height:auto;filter:drop-shadow(0 4px 12px rgba(0,0,0,.6));pointer-events:none}

.rl-coin{position:absolute;width:32px;height:32px;border-radius:50%;background:radial-gradient(circle at 30% 30%,#fde68a,#fbbf24 40%,#d97706 75%,#92400e);box-shadow:0 4px 10px rgba(0,0,0,.4),inset 0 1px 3px var(--bd2);z-index:7;pointer-events:none}
.rl-coin::before{content:'$';position:absolute;inset:0;display:flex;align-items:center;justify-content:center;color:#78350f;font-weight:900;font-size:16px}
.rl-coin.c1{left:4px;top:80px;animation:coinFloat 3s ease-in-out infinite}
.rl-coin.c2{right:4px;top:65px;animation:coinFloat 3.5s ease-in-out .5s infinite}
.rl-coin.c3{right:40px;top:200px;animation:coinFloat 2.8s ease-in-out 1s infinite}
.rl-coin.c4{left:40px;top:200px;animation:coinFloat 3.2s ease-in-out 1.5s infinite}
@keyframes coinFloat{0%,100%{transform:translateY(0) rotate(0)}50%{transform:translateY(-10px) rotate(15deg)}}

.wh-outer{position:relative;width:340px;height:340px;margin:0 auto;padding:12px;border-radius:50%;background:radial-gradient(circle at center,var(--bg2) 0%,var(--bg) 100%);box-shadow:0 10px 30px rgba(0,0,0,.5),inset 0 0 20px rgba(var(--pri-rgb),.3),0 0 0 2px rgba(var(--pri-rgb),.4);z-index:2}
.wh-outer::before{content:'';position:absolute;inset:6px;border-radius:50%;border:3px solid #3b82f6;box-shadow:inset 0 0 10px rgba(59,130,246,.4);pointer-events:none;z-index:1}
.wh-outer canvas{width:316px;height:316px;display:block;border-radius:50%;position:relative;z-index:2}

.wh-ptr{position:absolute;top:-2px;left:50%;transform:translateX(-50%);z-index:8;filter:drop-shadow(0 2px 4px rgba(0,0,0,.6))}

.wh-center{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:72px;height:72px;border-radius:50%;background:radial-gradient(circle at 35% 30%,#86efac 0%,#22c55e 45%,#15803d 100%);border:4px solid #fbbf24;box-shadow:0 6px 20px rgba(0,0,0,.5),inset 0 2px 6px var(--bd2),0 0 30px rgba(34,197,94,.4);display:flex;align-items:center;justify-content:center;font-size:1.9rem;font-weight:900;color:#fff;cursor:pointer;z-index:4;transition:transform .1s;text-shadow:0 2px 4px rgba(0,0,0,.5);font-family:'Chakra Petch'}
.wh-center:active{transform:translate(-50%,-50%) scale(.9)}
.wh-center.off{opacity:.6;pointer-events:none;filter:grayscale(.3)}

.rl-banner{width:340px;margin:-26px auto 0;position:relative;z-index:1;height:85px}
.rl-banner svg{width:100%;height:100%;display:block}

.amt-box{text-align:center;margin:18px 16px 8px}
.amt-box .val{font-family:'Chakra Petch';font-size:2.4rem;font-weight:900;color:var(--pri);letter-spacing:1px;text-shadow:0 2px 6px rgba(var(--pri-rgb),.35)}
.prog{height:8px;background:var(--tint-2);border-radius:4px;margin:6px 20px;overflow:hidden}
.prog .fill{height:100%;background:var(--pri)!important;border-radius:4px;transition:width .6s ease;box-shadow:0 0 8px rgba(var(--pri-rgb),.5)}
.rem{text-align:center;font-size:.82rem;color:var(--t3);margin:8px 16px 18px}
.rem b{color:#f59e0b;font-weight:800}

.tmr{display:flex;justify-content:center;gap:6px;margin:14px 0 10px}
.tmr .td{background:var(--tint-2);color:#fff;padding:6px 10px;border-radius:8px;font-family:'Chakra Petch';font-size:.88rem;font-weight:800;min-width:36px;text-align:center}
.tmr .sep{color:var(--t3);font-weight:800;line-height:34px;font-size:1rem}
.tmr .lbl{font-size:.45rem;color:var(--t3);text-align:center;display:block;margin-top:2px;font-weight:700;letter-spacing:.5px}

.tabs{display:flex;margin:16px 12px 10px;border-bottom:1px solid var(--tint-2)}
.tab{flex:1;padding:12px;text-align:center;font-size:.85rem;font-weight:600;cursor:pointer;background:transparent;color:var(--t3);border:none;border-bottom:2px solid transparent;font-family:inherit;transition:all .2s}
.tab.on{color:var(--pri);border-bottom-color:var(--pri);font-weight:700}
.pan{display:none;margin:0 12px}.pan.on{display:block}
.tbl{background:var(--tint-1);border-radius:10px;overflow:hidden;margin-bottom:18px;border:1px solid var(--tint-1)}
.th{display:flex;background:var(--tint-1);padding:10px 12px}
.th span{flex:1;font-size:.7rem;font-weight:700;color:var(--t3);text-align:center}
.tr{display:flex;align-items:center;padding:10px 12px;border-bottom:1px solid var(--tint-1)}
.tr:last-child{border:none}.tr span{flex:1;font-size:.75rem;text-align:center;color:#e2e8f0}
.tr .bon{color:#fbbf24;font-weight:800}

.rul{margin:8px 12px 20px;padding:14px;background:var(--tint-1);border:1px solid var(--tint-1);border-radius:10px;font-size:.74rem;color:var(--t3);line-height:1.7}
.rul h3{color:#fbbf24;font-size:.85rem;margin-bottom:8px;font-weight:700}
.rul p{margin-bottom:4px}
.rul .nt{color:var(--pri);font-weight:600;margin-bottom:8px}

.congrats{position:fixed;inset:0;background:rgba(0,0,0,.85);z-index:9999;display:none;align-items:center;justify-content:center;padding:20px}
.congrats.on{display:flex}
.congrats-inner{background:linear-gradient(180deg,var(--bg2) 0%,var(--bg) 100%);border:3px solid var(--pri);border-radius:20px;padding:24px 20px;text-align:center;max-width:320px;box-shadow:0 20px 60px rgba(0,0,0,.7);animation:popIn .3s ease-out}
@keyframes popIn{0%{transform:scale(.7);opacity:0}60%{transform:scale(1.05);opacity:1}100%{transform:scale(1)}}
.congrats-inner h2{color:#fbbf24;font-family:'Chakra Petch';font-size:1.5rem;font-weight:900;margin-bottom:8px;letter-spacing:1px}
.congrats-inner .amt{font-family:'Chakra Petch';font-size:2.8rem;font-weight:900;color:#4ade80;text-shadow:0 2px 12px rgba(74,222,128,.5);margin:10px 0}
.congrats-inner p{color:#e2e8f0;font-size:.85rem;margin-bottom:14px}
.congrats-inner button{background:linear-gradient(180deg,var(--pri),var(--pri-d));color:#fff;border:none;padding:12px 30px;border-radius:10px;font-size:.9rem;font-weight:800;cursor:pointer;font-family:inherit;width:100%}
</style>
</head>
<body>


<?php if(!$isLoggedIn){ $guestBannerLabel = 'roulette gratis'; require __DIR__.'/includes/guest_banner.php'; } ?>
<div class="rl-hdr">
<button onclick="history.back()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="24" height="24"><polyline points="15 18 9 12 15 6"/></svg></button>
<h1>Mendapatkan 100.00 Emas Gratis</h1>
</div>

<div class="rl-info">
<div class="pt-txt">Putaran Tersisa: <b id="spL"><?=$spinsLeft?></b></div>
<a class="inv-btn" href="undang.php">
  <span>Undang teman untuk membantu</span>
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/><polyline points="9 17 15 12 9 7"/></svg>
</a>
</div>

<div class="rl-wheel-area">

  <div class="rl-chars">
    <img src="img/roulette/chars1.png?v=<?=time()?>" alt="Characters" onerror="this.style.display='none'">
    <div class="rl-coin c1"></div>
    <div class="rl-coin c2"></div>
    <div class="rl-coin c3"></div>
    <div class="rl-coin c4"></div>
  </div>

  <div class="wh-outer">
    <canvas id="wC" width="640" height="640"></canvas>
    <div class="wh-ptr">
      <svg width="32" height="38" viewBox="0 0 32 38">
        <defs><linearGradient id="ptrG" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fcd34d"/><stop offset="1" stop-color="#b45309"/></linearGradient></defs>
        <path d="M16 38 L2 6 Q2 0 8 0 L24 0 Q30 0 30 6 Z" fill="url(#ptrG)" stroke="#78350f" stroke-width="1.5"/>
        <circle cx="16" cy="8" r="3" fill="#78350f"/>
      </svg>
    </div>
    <div class="wh-center<?=$spinsLeft<=0?' off':''?>" id="sBtn" onclick="doSpin()"><?=$spinsLeft?></div>
  </div>

  <div class="rl-banner">
    <svg viewBox="0 0 340 85" preserveAspectRatio="xMidYMid meet">
      <defs>
        <linearGradient id="bnG" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stop-color="#3b82f6"/>
          <stop offset="0.5" stop-color="#2563eb"/>
          <stop offset="1" stop-color="#1e40af"/>
        </linearGradient>
        <linearGradient id="bnTxt" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0" stop-color="#fcd34d"/>
          <stop offset="1" stop-color="#d97706"/>
        </linearGradient>
      </defs>
      <path d="M0 30 L26 22 L20 58 L0 50 Z" fill="#1e3a8a" stroke="#fbbf24" stroke-width="1.5"/>
      <path d="M340 30 L314 22 L320 58 L340 50 Z" fill="#1e3a8a" stroke="#fbbf24" stroke-width="1.5"/>
      <path d="M20 15 Q170 5 320 15 L320 65 Q170 75 20 65 Z" fill="url(#bnG)" stroke="#fbbf24" stroke-width="2"/>
      <text x="170" y="50" text-anchor="middle" font-family="Chakra Petch,sans-serif" font-size="24" font-weight="900" fill="url(#bnTxt)" stroke="#78350f" stroke-width="1">Spin to Win Big !!!</text>
    </svg>
  </div>

</div>

<div class="amt-box"><div class="val" id="aAmt"><?=number_format($accumulated,2)?></div></div>
<div class="prog"><div class="fill" id="pFill" style="width:<?=$progress?>%"></div></div>
<div class="rem">Anda masih membutuhkan <b id="rAmt"><?=number_format($remaining,2)?></b> Sebelum bisa menarik dana</div>

<div class="tmr">
<div><div class="td" id="rD"><?=str_pad($rD,2,'0',STR_PAD_LEFT)?></div><span class="lbl">DAY</span></div><div class="sep">:</div>
<div><div class="td" id="rH"><?=str_pad($rH,2,'0',STR_PAD_LEFT)?></div><span class="lbl">HRS</span></div><div class="sep">:</div>
<div><div class="td" id="rM"><?=str_pad($rM,2,'0',STR_PAD_LEFT)?></div><span class="lbl">MIN</span></div><div class="sep">:</div>
<div><div class="td" id="rS"><?=str_pad($rS,2,'0',STR_PAD_LEFT)?></div><span class="lbl">SEC</span></div>
</div>

<div class="tabs">
<button class="tab on" onclick="rlT(0,this)">Pengumuman</button>
<button class="tab" onclick="rlT(1,this)">Dukungan saya</button>
</div>
<div class="pan on" id="rlP0">
<div class="tbl"><div class="th"><span>ID</span><span>Deskripsi</span><span>Bonus</span></div><div id="rlW"></div></div>
</div>
<div class="pan" id="rlP1">
<div class="tbl">
<div class="th"><span>Teman Valid</span><span>Spin Tersedia</span></div>
<div class="tr"><span style="color:#4ade80;font-weight:800;font-size:1rem"><?=$validFriends?> orang</span><span class="bon"><?=$inviteSpinsAvailable?> spin</span></div>
<?php if($validFriends===0): ?>
<div style="padding:16px;text-align:center;font-size:.72rem;color:var(--t3)">Belum ada teman valid.<br>Teman dianggap valid setelah mereka <b style="color:#f59e0b">deposit berhasil</b>.</div>
<?php else: ?>
<div style="padding:12px;text-align:center;font-size:.72rem;color:var(--t3)">Setiap teman yang deposit menambah <b style="color:#4ade80">+1 spin</b> permanent.</div>
<?php endif; ?>
</div>
</div>

<div class="rul">
<h3>Peraturan Acara</h3>
<div class="nt">&#9733; 1.00 sebenarnya adalah 1 K, 1K = Rp 1.000</div>
<p>1. Anda mendapat <b>2 spin gratis</b>. Setelah itu wajib undang teman untuk spin tambahan.</p>
<p>2. Setiap teman yang deposit (status paid) menambah <b>+1 spin</b> permanent.</p>
<p>3. Target 100.00 emas butuh sekitar <b>75 teman valid</b> berdeposit.</p>
<p>4. Setiap aktivitas berlaku 3 hari. Setelah kedaluwarsa, emas akan hangus.</p>
<p>5. Bonus ini bisa ditarik tanpa syarat turnover tambahan.</p>
<p>6. Platform memiliki hak akhir untuk menafsirkan aktivitas ini.</p>
</div>

<div class="congrats" id="congrats">
  <div class="congrats-inner">
    <h2>SELAMAT!</h2>
    <div class="amt" id="congratsAmt">+0.00</div>
    <p id="congratsMsg">Emas berhasil didapat!</p>
    <button onclick="closeCongrats()">Lanjutkan</button>
  </div>
</div>

<script>
var spL=<?=$spinsLeft?>,acc=<?=$accumulated?>,tgt=100,spinning=false;

// 8 segment — label nominal TETAP tampil (estetik), tapi:
// - Segment nominal (100/50/10/5) = DECORATIVE DOANG (winnable:false)
// - Pointer SELALU berhenti di segment ???/emoji (winnable:true)
var SEGS=[
  {l:'',c:'#7e22ce',c2:'#9333ea',lc:'#fff',kind:'icon',ic:'star',winnable:false},
  {l:'',c:'#1d4ed8',c2:'#2563eb',lc:'#fff',kind:'icon',ic:'coin',winnable:false},
  {l:'',c:'#f59e0b',c2:'#fbbf24',lc:'#fff',kind:'icon',ic:'gem',winnable:false},
  {l:'???',c:'#b91c1c',c2:'#dc2626',lc:'#fff',kind:'q',winnable:true},
  {l:'',c:'#d1d5db',c2:'#e5e7eb',lc:'#000',kind:'emo1',winnable:true},
  {l:'',c:'#6d28d9',c2:'#8b5cf6',lc:'#fff',kind:'icon',ic:'smile',winnable:false},
  {l:'???',c:'#15803d',c2:'#16a34a',lc:'#fff',kind:'q',winnable:true},
  {l:'???',c:'#ea580c',c2:'#f97316',lc:'#fff',kind:'q',winnable:true}
];

// Winnable segments indices (yg boleh ditunjuk pointer)
var WINNABLE_IDX=[];
for(var _i=0;_i<SEGS.length;_i++)if(SEGS[_i].winnable)WINNABLE_IDX.push(_i);

var cv=document.getElementById('wC'),ctx=cv.getContext('2d');
var CX=320,CY=320,R=300,rot=-Math.PI/2; // segment 0 starts at TOP (pointer position)

function drawStar(cx,cy,sz){
  ctx.save();
  ctx.translate(cx,cy);
  ctx.beginPath();
  for(var i=0;i<10;i++){
    var ang=i*Math.PI/5-Math.PI/2;
    var r=(i%2===0)?sz:sz*0.42;
    var x=Math.cos(ang)*r,y=Math.sin(ang)*r;
    if(i===0)ctx.moveTo(x,y);else ctx.lineTo(x,y);
  }
  ctx.closePath();
  var g=ctx.createRadialGradient(0,-sz*0.3,0,0,0,sz);
  g.addColorStop(0,'#fef3c7');g.addColorStop(.55,'#fbbf24');g.addColorStop(1,'#b45309');
  ctx.fillStyle=g;ctx.fill();
  ctx.strokeStyle='#78350f';ctx.lineWidth=1.6;ctx.stroke();
  // sparkle highlight
  ctx.fillStyle='rgba(255,255,255,.55)';
  ctx.beginPath();ctx.arc(-sz*0.2,-sz*0.4,sz*0.15,0,Math.PI*2);ctx.fill();
  ctx.restore();
}
function drawCoin(cx,cy,sz){
  ctx.save();
  // Outer ring
  ctx.beginPath();ctx.arc(cx,cy,sz,0,Math.PI*2);
  var g=ctx.createRadialGradient(cx-sz*0.3,cy-sz*0.3,0,cx,cy,sz);
  g.addColorStop(0,'#fef3c7');g.addColorStop(.5,'#fbbf24');g.addColorStop(1,'#b45309');
  ctx.fillStyle=g;ctx.fill();
  ctx.strokeStyle='#78350f';ctx.lineWidth=2;ctx.stroke();
  // Inner ring
  ctx.beginPath();ctx.arc(cx,cy,sz*0.72,0,Math.PI*2);
  ctx.strokeStyle='#92400e';ctx.lineWidth=1.5;ctx.stroke();
  // $ symbol
  ctx.fillStyle='#78350f';
  ctx.font='900 '+Math.floor(sz*1.2)+'px "Chakra Petch",Arial,sans-serif';
  ctx.textAlign='center';ctx.textBaseline='middle';
  ctx.fillText('$',cx,cy);
  // shine highlight
  ctx.fillStyle='rgba(255,255,255,.45)';
  ctx.beginPath();ctx.ellipse(cx-sz*0.3,cy-sz*0.4,sz*0.25,sz*0.15,-0.4,0,Math.PI*2);ctx.fill();
  ctx.restore();
}
function drawGem(cx,cy,sz){
  ctx.save();
  ctx.translate(cx,cy);
  // Diamond shape
  ctx.beginPath();
  ctx.moveTo(0,-sz);
  ctx.lineTo(sz*0.85,-sz*0.25);
  ctx.lineTo(sz*0.55,sz);
  ctx.lineTo(-sz*0.55,sz);
  ctx.lineTo(-sz*0.85,-sz*0.25);
  ctx.closePath();
  var g=ctx.createLinearGradient(0,-sz,0,sz);
  g.addColorStop(0,'#a5f3fc');g.addColorStop(.5,'#06b6d4');g.addColorStop(1,'#0e7490');
  ctx.fillStyle=g;ctx.fill();
  ctx.strokeStyle='#164e63';ctx.lineWidth=1.5;ctx.stroke();
  // Top facets
  ctx.beginPath();
  ctx.moveTo(0,-sz);ctx.lineTo(-sz*0.85,-sz*0.25);ctx.lineTo(0,-sz*0.25);ctx.closePath();
  ctx.fillStyle='rgba(255,255,255,.45)';ctx.fill();
  ctx.beginPath();
  ctx.moveTo(0,-sz);ctx.lineTo(sz*0.85,-sz*0.25);ctx.lineTo(0,-sz*0.25);ctx.closePath();
  ctx.fillStyle='rgba(255,255,255,.25)';ctx.fill();
  // Highlight line
  ctx.beginPath();ctx.moveTo(-sz*0.7,-sz*0.15);ctx.lineTo(sz*0.7,-sz*0.15);
  ctx.strokeStyle='rgba(255,255,255,.4)';ctx.lineWidth=1;ctx.stroke();
  ctx.restore();
}
function drawSmile(cx,cy,sz){
  ctx.save();
  ctx.fillStyle='#fde047';
  ctx.beginPath();ctx.arc(cx,cy,sz,0,Math.PI*2);ctx.fill();
  ctx.strokeStyle='#713f12';ctx.lineWidth=2;ctx.stroke();
  // Eyes
  ctx.fillStyle='#1f2937';
  ctx.beginPath();ctx.arc(cx-sz*0.35,cy-sz*0.2,sz*0.1,0,Math.PI*2);ctx.fill();
  ctx.beginPath();ctx.arc(cx+sz*0.35,cy-sz*0.2,sz*0.1,0,Math.PI*2);ctx.fill();
  // Smile
  ctx.beginPath();ctx.arc(cx,cy+sz*0.05,sz*0.5,0.15*Math.PI,0.85*Math.PI);
  ctx.strokeStyle='#1f2937';ctx.lineWidth=2.4;ctx.lineCap='round';ctx.stroke();
  ctx.restore();
}
function drawIcon(cx,cy,which,sz){
  sz=sz||16;
  if(which==='star')drawStar(cx,cy,sz);
  else if(which==='coin')drawCoin(cx,cy,sz);
  else if(which==='gem')drawGem(cx,cy,sz);
  else if(which==='smile')drawSmile(cx,cy,sz);
}

function drawMoneyBundle(cx,cy,w,h){
  ctx.save();
  var g=ctx.createLinearGradient(cx-w,cy-h,cx+w,cy+h);
  g.addColorStop(0,'#16a34a');g.addColorStop(.5,'#22c55e');g.addColorStop(1,'#15803d');
  ctx.fillStyle=g;
  ctx.fillRect(cx-w,cy-h,w*2,h*2);
  ctx.strokeStyle='#14532d';ctx.lineWidth=1.5;ctx.strokeRect(cx-w,cy-h,w*2,h*2);
  ctx.fillStyle='#fff';ctx.fillRect(cx-w-2,cy-3,w*2+4,6);
  ctx.strokeStyle='#d1d5db';ctx.lineWidth=1;ctx.strokeRect(cx-w-2,cy-3,w*2+4,6);
  ctx.fillStyle='#fbbf24';ctx.font='bold 10px Arial';ctx.textAlign='center';ctx.textBaseline='middle';
  ctx.fillText('$',cx,cy-h+6);ctx.fillText('$',cx,cy+h-6);
  ctx.restore();
}

function drawEmoji(cx,cy,variant){
  ctx.save();
  variant=variant||1;
  // Head (yellow) - all variants
  ctx.fillStyle='#fde047';
  ctx.beginPath();ctx.arc(cx,cy,18,0,Math.PI*2);ctx.fill();
  ctx.strokeStyle='#713f12';ctx.lineWidth=2;ctx.stroke();

  if(variant===1){
    // Variant 1: Red cap
    ctx.fillStyle='#dc2626';
    ctx.beginPath();ctx.arc(cx,cy-5,18,Math.PI,Math.PI*2);ctx.closePath();ctx.fill();
    ctx.strokeStyle='#7f1d1d';ctx.stroke();
    ctx.fillStyle='#fff';ctx.fillRect(cx-18,cy-7,36,4);
    // Eyes happy
    ctx.fillStyle='#1f2937';
    ctx.beginPath();ctx.arc(cx-6,cy+2,1.8,0,Math.PI*2);ctx.fill();
    ctx.beginPath();ctx.arc(cx+6,cy+2,1.8,0,Math.PI*2);ctx.fill();
    // Smile
    ctx.strokeStyle='#1f2937';ctx.lineWidth=2;
    ctx.beginPath();ctx.arc(cx,cy+6,5,0,Math.PI);ctx.stroke();
  }else if(variant===2){
    // Variant 2: Sunglasses cool
    ctx.fillStyle='#1f2937';
    // Hat brim (black rectangle above)
    ctx.fillRect(cx-16,cy-16,32,5);
    ctx.fillStyle='#4b5563';ctx.fillRect(cx-14,cy-18,28,4);
    // Sunglasses
    ctx.fillStyle='var(--bg)';
    ctx.fillRect(cx-11,cy-2,9,6);
    ctx.fillRect(cx+2,cy-2,9,6);
    ctx.fillRect(cx-2,cy,4,2);
    // Smirk
    ctx.strokeStyle='#1f2937';ctx.lineWidth=2;
    ctx.beginPath();ctx.arc(cx-1,cy+7,4,0,Math.PI*.85);ctx.stroke();
  }else{
    // Variant 3: Winking with tongue
    // Eyes — one wink
    ctx.strokeStyle='#1f2937';ctx.lineWidth=2.2;
    ctx.beginPath();ctx.moveTo(cx-9,cy-1);ctx.lineTo(cx-3,cy-1);ctx.stroke();
    ctx.fillStyle='#1f2937';
    ctx.beginPath();ctx.arc(cx+6,cy-1,2,0,Math.PI*2);ctx.fill();
    // Open smile with tongue
    ctx.fillStyle='#1f2937';
    ctx.beginPath();ctx.arc(cx,cy+6,5,0,Math.PI);ctx.closePath();ctx.fill();
    ctx.fillStyle='#f87171';
    ctx.beginPath();ctx.arc(cx+2,cy+9,2.5,0,Math.PI*2);ctx.fill();
  }
  ctx.restore();
}

function dW(r){
  ctx.clearRect(0,0,640,640);
  var n=SEGS.length,arc=Math.PI*2/n;

  // Draw segments
  for(var i=0;i<n;i++){
    var a=r+i*arc;
    ctx.beginPath();ctx.moveTo(CX,CY);ctx.arc(CX,CY,R,a,a+arc);ctx.closePath();
    var g=ctx.createRadialGradient(CX,CY,40,CX,CY,R);
    g.addColorStop(0,SEGS[i].c2);g.addColorStop(1,SEGS[i].c);
    ctx.fillStyle=g;ctx.fill();
    ctx.strokeStyle='var(--bd2)';ctx.lineWidth=2.5;ctx.stroke();
  }

  // Labels & icons
  for(var i=0;i<n;i++){
    var a=r+i*arc+arc/2;
    ctx.save();ctx.translate(CX,CY);ctx.rotate(a);

    var s=SEGS[i];

    if(s.kind==='emo1'||s.kind==='emo2'||s.kind==='emo3'){
      // Emoji segment
      var v=s.kind==='emo1'?1:(s.kind==='emo2'?2:3);
      ctx.save();
      ctx.translate(R*0.65,0);
      ctx.rotate(-a-Math.PI/2);
      drawEmoji(0,0,v);
      ctx.restore();
    }else{
      // Money bundle icon
      ctx.save();
      ctx.translate(R*0.48,-5);
      ctx.rotate(-a-Math.PI/2);
      // Draw decoration: money bundle for emoji segs, nothing for icon segs (icon IS the decoration)
      if(s.kind!=='icon'){drawMoneyBundle(0,0,14,9);}
      ctx.restore();

      // Label / icon
      ctx.save();
      ctx.translate(R*0.78,0);
      ctx.rotate(Math.PI/2);
      if(s.kind==='icon'){
        // Draw icon (star/coin/gem/smile)
        drawIcon(0,0,s.ic,28);
      } else {
        ctx.fillStyle=s.lc;
        if(s.kind==='q'){
          ctx.font='900 36px Arial,sans-serif';
        } else {
          ctx.font='900 22px "Chakra Petch",sans-serif';
        }
        ctx.textAlign='center';ctx.textBaseline='middle';
        ctx.shadowColor='rgba(0,0,0,.6)';ctx.shadowBlur=4;ctx.shadowOffsetY=2;
        ctx.fillText(s.l,0,0);
        ctx.shadowBlur=0;
      }
      ctx.restore();
    }
    ctx.restore();
  }

  // Inner dark center
  ctx.beginPath();ctx.arc(CX,CY,52,0,Math.PI*2);
  var ig=ctx.createRadialGradient(CX,CY,0,CX,CY,52);
  ig.addColorStop(0,'#172554');ig.addColorStop(1,'#0c1e4a');
  ctx.fillStyle=ig;ctx.fill();

  // Outer dots (decorative bulbs)
  for(var i=0;i<16;i++){
    var da=i*Math.PI*2/16;
    var dx=CX+Math.cos(da)*(R-10),dy=CY+Math.sin(da)*(R-10);
    ctx.beginPath();ctx.arc(dx,dy,4,0,Math.PI*2);
    var bg=ctx.createRadialGradient(dx,dy,0,dx,dy,4);
    bg.addColorStop(0,'#fff');bg.addColorStop(.5,'#fbbf24');bg.addColorStop(1,'#b45309');
    ctx.fillStyle=bg;ctx.fill();
  }
}
dW(rot);


// ═══ SPIN AUDIO SYSTEM (Web Audio API — no external files) ═══
var _audioCtx=null,_tickActive=false;
function _aCtx(){if(!_audioCtx){try{_audioCtx=new (window.AudioContext||window.webkitAudioContext)()}catch(e){}}return _audioCtx}
function playTick(){var c=_aCtx();if(!c)return;try{var t=c.currentTime,o=c.createOscillator(),g=c.createGain();o.type='square';o.frequency.value=1400;g.gain.setValueAtTime(.12,t);g.gain.exponentialRampToValueAtTime(.001,t+.035);o.connect(g);g.connect(c.destination);o.start(t);o.stop(t+.04)}catch(e){}}
function playWinSound(){var c=_aCtx();if(!c)return;try{var t0=c.currentTime;[523.25,659.25,783.99,1046.5].forEach(function(f,i){var o=c.createOscillator(),g=c.createGain();o.type='sine';o.frequency.value=f;var t=t0+i*.11;g.gain.setValueAtTime(0,t);g.gain.linearRampToValueAtTime(.18,t+.02);g.gain.exponentialRampToValueAtTime(.001,t+.5);o.connect(g);g.connect(c.destination);o.start(t);o.stop(t+.55)})}catch(e){}}
function playLoseSound(){var c=_aCtx();if(!c)return;try{var t=c.currentTime,o=c.createOscillator(),g=c.createGain();o.type='sawtooth';o.frequency.setValueAtTime(440,t);o.frequency.exponentialRampToValueAtTime(110,t+.6);g.gain.setValueAtTime(.13,t);g.gain.exponentialRampToValueAtTime(.001,t+.7);o.connect(g);g.connect(c.destination);o.start(t);o.stop(t+.72)}catch(e){}}
function startSpinTicks(durationMs){_tickActive=true;var start=Date.now();var tick=function(){if(!_tickActive)return;var el=Date.now()-start;var t=el/durationMs;if(t>=1){_tickActive=false;return}playTick();var ease=1-Math.pow(1-t,4);var iv=45+ease*320;setTimeout(tick,iv)};tick()}
function stopSpinTicks(){_tickActive=false}

function doSpin(){
  if(spinning)return;
  if(spL<=0){
    showToast('Putaran habis! Undang teman & ajak mereka deposit untuk spin tambahan.');
    return;
  }
  spinning=true;
  var btn=document.getElementById('sBtn');
  btn.classList.add('off');

  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',
    body:JSON.stringify({action:'roulette_spin'})
  }).then(function(r){return r.json()}).then(function(d){
    if(!d.ok){spinning=false;btn.classList.remove('off');showToast(d.error||'Gagal');return}

    var wi=d.segment||0, wa=d.amount/1000;
    // FORCE: pointer HANYA berhenti di segment winnable (???/emoji)
    // Nominal 100/50/10/5 di wheel = DECORATIVE doang, ga pernah jadi target
    if(!SEGS[wi]||!SEGS[wi].winnable){
      wi=WINNABLE_IDX[Math.floor(Math.random()*WINNABLE_IDX.length)];
    }
    var arc=Math.PI*2/SEGS.length;
    // Pointer at TOP (angle -PI/2). Rotate wheel so segment wi lands at top.
    var targetAngle = -wi*arc - arc/2 - Math.PI/2;
    // Ensure totalRot is always forward (at least 7 full rotations past current)
    while(targetAngle <= rot) targetAngle += Math.PI*2;
    var totalRot = targetAngle - rot + Math.PI*2*6; // 6+ full rotations
    var startRot=rot, dur=4500, startTime=Date.now();
    startSpinTicks(dur);  // 🔊 spin sound effect

    function animate(){
      var elapsed=Date.now()-startTime;
      var t=Math.min(1,elapsed/dur);
      var ease=1-Math.pow(1-t,3.5);
      rot=startRot+ease*totalRot;
      dW(rot);
      if(t<1){requestAnimationFrame(animate)}
      else{
        playWinSound();  // 🔊 always-win for roulette (rebate)
        document.getElementById('congratsAmt').textContent='+'+wa.toFixed(2);
        var msg='Emas berhasil didapat!';
        if(wa>=50)msg='WOW! Jackpot besar!';
        else if(wa>=10)msg='Keren banget!';
        else if(wa<1)msg='Sedikit demi sedikit, lama-lama jadi bukit!';
        document.getElementById('congratsMsg').textContent=msg;
        document.getElementById('congrats').classList.add('on');

        spL=d.spins_left!=null?d.spins_left:Math.max(0,spL-1);
        acc=d.accumulated/1000;
        document.getElementById('spL').textContent=spL;
        btn.textContent=spL;
        if(spL>0)btn.classList.remove('off');
        document.getElementById('aAmt').textContent=acc.toFixed(2);
        var pct=Math.min(100,(acc/tgt)*100);
        document.getElementById('pFill').style.width=pct+'%';
        document.getElementById('rAmt').textContent=Math.max(0,tgt-acc).toFixed(2);
        spinning=false;
      }
    }
    requestAnimationFrame(animate);
  }).catch(function(e){
    spinning=false;document.getElementById('sBtn').classList.remove('off');
    showToast('Koneksi gagal, coba lagi');
  });
}

function closeCongrats(){document.getElementById('congrats').classList.remove('on')}

function showToast(msg){
  var t=document.createElement('div');
  t.textContent=msg;
  t.style.cssText='position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--bg2);color:#fff;padding:14px 22px;border-radius:10px;font-size:.8rem;z-index:10000;box-shadow:0 4px 20px rgba(0,0,0,.5);max-width:86%;text-align:center;border:1px solid #fbbf24';
  document.body.appendChild(t);
  setTimeout(function(){t.style.opacity='0';t.style.transition='opacity .3s';setTimeout(function(){t.remove()},300)},2500);
}

function rlT(i,el){
  document.querySelectorAll('.tab').forEach(function(t){t.classList.remove('on')});
  document.querySelectorAll('.pan').forEach(function(p){p.classList.remove('on')});
  el.classList.add('on');document.getElementById('rlP'+i).classList.add('on');
}

(function(){
  function genId(){var a=Math.floor(Math.random()*90+10);var b=Math.floor(Math.random()*90+10);return a+'**'+b;}
  var coinIc='<svg viewBox="0 0 24 24" width="14" height="14" style="vertical-align:-3px;margin-right:3px"><circle cx="12" cy="12" r="10" fill="#fbbf24" stroke="#92400e" stroke-width="1.5"/><text x="12" y="17" font-size="13" font-weight="900" text-anchor="middle" fill="#92400e" font-family="Arial">$</text></svg>';
  function genAmt(){return coinIc+'<span>Rp 100.000</span>'}
  function genRow(){return '<div class="tr" style="opacity:0;animation:slideIn .4s forwards"><span>'+genId()+'</span><span style="font-weight:600">Claim Sukses</span><span class="bon" style="display:inline-flex;align-items:center">'+genAmt()+'</span></div>'}
  var el=document.getElementById('rlW');
  var h='';
  for(var i=0;i<8;i++)h+=genRow().replace('animation:slideIn .4s forwards','animation:none;opacity:1');
  el.innerHTML=h;
  function nextWinner(){
    var newRow=document.createElement('div');
    newRow.innerHTML=genRow();
    el.insertBefore(newRow.firstChild,el.firstChild);
    while(el.children.length>10)el.removeChild(el.lastChild);
    setTimeout(nextWinner,2000+Math.random()*3000);
  }
  setTimeout(nextWinner,2500);
})();

var sty=document.createElement('style');
sty.textContent='@keyframes slideIn{from{opacity:0;transform:translateY(-10px)}to{opacity:1;transform:translateY(0)}}';
document.head.appendChild(sty);

var eT=<?=($cycleEnd*1000)?>;
setInterval(function(){
  var l=Math.max(0,eT-Date.now());
  document.getElementById('rD').textContent=String(Math.floor(l/864e5)).padStart(2,'0');
  document.getElementById('rH').textContent=String(Math.floor((l%864e5)/36e5)).padStart(2,'0');
  document.getElementById('rM').textContent=String(Math.floor((l%36e5)/6e4)).padStart(2,'0');
  document.getElementById('rS').textContent=String(Math.floor((l%6e4)/1e3)).padStart(2,'0');
},1000);
</script>
<?php include 'includes/credit_notify.php'; ?>
</body></html>
