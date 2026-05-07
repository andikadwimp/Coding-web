<?php
require_once 'includes/config.php';
$isLoggedIn = (bool)getUid();  // guest-friendly

header('Content-Type: text/html; charset=UTF-8');
$uid = getUid();
$sets=[];
try{$st=$db->query("SELECT `key`,`value` FROM settings");foreach($st->fetchAll() as $r)$sets[$r['key']]=$r['value'];normSettings($sets);}catch(Exception $e){}
$sn=$sets['site_name']??'Situs';
if(!$uid){header('Location: index.php');exit;}

// ── Load prizes directly (same logic as spin_init API) ──
$_prizes=[];$_tickets=0;$_history=[];
try{
    $db->exec("CREATE TABLE IF NOT EXISTS spin_prizes (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,label VARCHAR(50),amount BIGINT UNSIGNED DEFAULT 0,probability DECIMAL(6,3) DEFAULT 0,color VARCHAR(20) DEFAULT 'var(--sec)',sort_order INT DEFAULT 0) ENGINE=InnoDB");
    $db->exec("CREATE TABLE IF NOT EXISTS spin_history (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,prize_id INT UNSIGNED DEFAULT NULL,label VARCHAR(50),amount BIGINT UNSIGNED DEFAULT 0,created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
    $cnt=$db->query("SELECT COUNT(*) FROM spin_prizes")->fetchColumn();
    if(!$cnt){
        $db->exec("INSERT INTO spin_prizes (label,amount,probability,color,sort_order) VALUES
        ('Rp 5.000.000',5000000,0.010,'#f59e0b',1),
        ('Zonk',0,15.800,'#6b7280',2),
        ('Rp 2.000.000',2000000,0.020,'#ef4444',3),
        ('Zonk',0,15.800,'#4b5563',4),
        ('Rp 1.000.000',1000000,0.050,'#fbbf24',5),
        ('Zonk',0,15.800,'#374151',6),
        ('Rp 500.000',500000,0.100,'#3b82f6',7),
        ('Zonk',0,15.800,'#6b7280',8),
        ('Rp 250.000',250000,0.200,'#06b6d4',9),
        ('Zonk',0,15.800,'#4b5563',10),
        ('Rp 100.000',100000,0.500,'#10b981',11),
        ('Rp 10.000',10000,5.000,'var(--sec)',12),
        ('Rp 5.000',5000,15.120,'var(--sec-d)',13)");
    }
    $_rawPrizes=$db->query("SELECT * FROM spin_prizes ORDER BY sort_order ASC")->fetchAll();
    // Interleave: spread Zonks between prize segments
    $_wins=[];$_zonks=[];
    foreach($_rawPrizes as $p){
        if(strtolower($p['label'])==='zonk')$_zonks[]=$p;
        else $_wins[]=$p;
    }
    $_prizes=[];$zi=0;
    foreach($_wins as $i=>$w){
        $_prizes[]=$w;
        if($zi<count($_zonks)&&$i<count($_wins)-1){$_prizes[]=$_zonks[$zi];$zi++;}
    }
    while($zi<count($_zonks)){$_prizes[]=$_zonks[$zi];$zi++;}
    // Re-index sort_order for consistent rendering
    foreach($_prizes as $k=>&$p){$p['_idx']=$k;}
    if($uid){
        $td=$db->prepare("SELECT COALESCE(SUM(nominal),0) FROM deposits WHERE user_id=? AND status='paid'");$td->execute([$uid]);
        $earned=intval(floor($td->fetchColumn()/100000));
        $used=$db->prepare("SELECT COUNT(*) FROM spin_history WHERE user_id=?");$used->execute([$uid]);
        $_tickets=max(0,$earned-intval($used->fetchColumn()));
        $h=$db->prepare("SELECT label,amount,created_at FROM spin_history WHERE user_id=? ORDER BY created_at DESC LIMIT 10");$h->execute([$uid]);
        $_history=$h->fetchAll();
    }
}catch(Exception $e){}
$PRIZES_JSON=json_encode($_prizes);
$TICKETS_JSON=json_encode($_tickets);
$HISTORY_JSON=json_encode($_history);
?><!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=390,user-scalable=no">
<title>Lucky Spin - <?=$sn?></title>
<?php if(file_exists('pwa_head.php'))require_once 'pwa_head.php'; ?>
<link rel="stylesheet" href="theme.php?v=<?=time()?>">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800;900&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;-webkit-text-size-adjust:100%;-moz-text-size-adjust:100%;text-size-adjust:100%}
html{font-size:16px!important}
body{background:var(--bg);color:var(--t);font-family:'Poppins',sans-serif;min-height:100vh;padding-bottom:20px;overflow-x:hidden;max-width:100vw}

/* ── BG Stars ── */
body::before{content:'';position:fixed;inset:0;background:
  radial-gradient(ellipse at 20% 10%, rgba(var(--sec-rgb,56,189,248),.1) 0%, transparent 50%),
  radial-gradient(ellipse at 80% 20%, rgba(var(--pri-rgb,56,189,248),.08) 0%, transparent 50%),
  radial-gradient(ellipse at 50% 80%, rgba(var(--sec-rgb,56,189,248),.06) 0%, transparent 50%);
  pointer-events:none;z-index:0}

/* ── Header ── */
.sp-header{position:relative;z-index:1;padding:14px 16px 10px;display:flex;align-items:center;gap:12px}
.sp-back{width:38px;height:38px;border-radius:12px;background:var(--tint-2);display:flex;align-items:center;justify-content:center;text-decoration:none;border:1px solid var(--tint-2);flex-shrink:0}
.sp-back svg{width:18px;height:18px;stroke:#fff}
.sp-title-wrap h1{font-size:1.1rem;font-weight:800;background:linear-gradient(135deg,#fbbf24 0%,#f59e0b 40%,#fde68a 70%,#fbbf24 100%);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;line-height:1.2}
.sp-title-wrap p{font-size:.65rem;color:var(--bd2);margin-top:1px}

/* ── Ticket pill ── */
.ticket-pill{position:relative;z-index:1;display:flex;align-items:center;gap:10px;background:linear-gradient(135deg,rgba(251,191,36,.12),rgba(251,191,36,.06));border:1px solid rgba(251,191,36,.3);border-radius:16px;padding:10px 18px;margin:0 16px 6px;backdrop-filter:blur(10px)}
.ticket-pill::before{content:'';position:absolute;inset:0;border-radius:16px;background:linear-gradient(135deg,rgba(251,191,36,.08),transparent);pointer-events:none}
.tp-icon{width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,#fbbf24,#f59e0b);display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 12px rgba(251,191,36,.4)}
.tp-icon svg{width:20px;height:20px;fill:#92400e}
.tp-info{flex:1}
.tp-count{font-size:1.5rem;font-weight:900;color:#fbbf24;line-height:1}
.tp-label{font-size:.62rem;color:var(--bd2);margin-top:1px}
.tp-deposit{padding:7px 14px;background:linear-gradient(135deg,#fbbf24,#f59e0b);border:none;border-radius:20px;font-size:.65rem;font-weight:700;color:#92400e;cursor:pointer;font-family:inherit;white-space:nowrap;text-decoration:none;display:inline-block}

/* ── Wheel section ── */
.wheel-section{position:relative;z-index:1;padding:10px 0 0;display:flex;flex-direction:column;align-items:center}

/* Glow rings behind wheel */
.wheel-glow{position:relative;width:320px;height:320px;margin:0 auto;isolation:isolate}
/* Glow ring behind wheel - z-index -1 so never covers canvas */
.wheel-glow::before{content:'';position:absolute;inset:-16px;border-radius:50%;background:conic-gradient(from 0deg,#fbbf24,var(--sec,var(--sec)),var(--sec-d,var(--sec-d)),#fbbf24,var(--sec,var(--sec)),#fbbf24);filter:blur(18px);opacity:.35;z-index:0}
.wheel-glow.spinning::before{animation:rotateBg 6s linear infinite}
.wheel-glow::after{display:none}
@keyframes rotateBg{to{transform:rotate(360deg)}}

.wheel-frame{position:absolute;inset:0;border-radius:50%;background:var(--bg);border:3px solid rgba(251,191,36,.5);overflow:hidden;display:flex;align-items:center;justify-content:center;z-index:1}
#spinCanvas{border-radius:50%;display:block;position:relative;z-index:2;width:308px;height:308px;background:var(--bg)}

/* Pointer */
.pointer-wrap{position:absolute;top:-22px;left:50%;transform:translateX(-50%);z-index:10;filter:drop-shadow(0 4px 10px rgba(0,0,0,.8))}
.pointer-wrap svg{width:28px;height:36px}

/* Spin button */
.spin-center{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);z-index:10}
#spinBtn{width:68px;height:68px;border-radius:50%;background:linear-gradient(135deg,#fbbf24,#f59e0b,#fbbf24);border:3px solid rgba(255,255,255,.9);font-size:.6rem;font-weight:900;color:#78350f;cursor:pointer;font-family:inherit;box-shadow:0 0 20px rgba(251,191,36,.6),0 0 40px rgba(251,191,36,.3),inset 0 1px 1px var(--bd2);line-height:1.3;transition:all .2s;letter-spacing:.5px}
#spinBtn:not(:disabled):hover{transform:scale(1.06);box-shadow:0 0 30px rgba(251,191,36,.8),0 0 60px rgba(251,191,36,.4)}
#spinBtn:not(:disabled):active{transform:scale(.96)}
#spinBtn:disabled{background:linear-gradient(135deg,#374151,#1f2937);color:#6b7280;border-color:#4b5563;box-shadow:none;cursor:not-allowed}

/* Spin count indicator */
.spin-dots{display:flex;gap:6px;margin-top:14px;justify-content:center}
.spin-dot{width:8px;height:8px;border-radius:50%;background:var(--tint-3);border:1px solid var(--tint-3);transition:all .3s}
.spin-dot.active{background:#fbbf24;border-color:#fbbf24;box-shadow:0 0 8px rgba(251,191,36,.6)}

/* ── History ── */
.hist-section{position:relative;z-index:1;margin:16px 16px 0}
.hist-header{font-size:.78rem;font-weight:700;color:rgba(255,255,255,.6);margin-bottom:10px;display:flex;align-items:center;gap:6px}
.hist-header::before{content:''}
.hist-row{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:var(--tint-1);border:1px solid var(--tint-2);border-radius:10px;margin-bottom:5px}
.hr-prize{font-size:.75rem;font-weight:700}
.hr-prize.win{color:#fbbf24}
.hr-prize.zonk{color:var(--bd2)}
.hr-date{font-size:.6rem;color:var(--bd2)}

/* ── Winners Panel ── */
.win-panel{position:relative;z-index:1;margin:20px 16px 0;border-radius:14px;overflow:hidden;border:1px solid var(--bd,rgba(var(--sec-rgb,56,189,248),.25));background:var(--s)}
.win-tabs{display:flex;background:var(--bg);border-bottom:1px solid var(--bd,rgba(var(--sec-rgb,56,189,248),.25))}
.win-tab{flex:1;padding:11px;text-align:center;font-size:.75rem;font-weight:700;color:var(--t3);cursor:pointer;border-bottom:2px solid transparent;transition:all .2s}
.win-tab.active{color:var(--pri,var(--sec));border-bottom-color:var(--pri,var(--sec));background:rgba(var(--pri-rgb,56,189,248),.05)}
.win-body{height:260px;overflow:hidden;position:relative}
.win-list{display:flex;flex-direction:column}
.win-row{display:flex;align-items:center;padding:10px 14px;border-bottom:1px solid var(--tint-1);gap:8px;animation:wrSlide .4s ease both}
@keyframes wrSlide{from{opacity:0;transform:translateY(-100%)}to{opacity:1;transform:translateY(0)}}
.wr-icon{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:.7rem}
.wr-icon.win{background:rgba(251,191,36,.15)}
.wr-icon.zonk{background:var(--tint-1)}
.wr-mid{flex:1;min-width:0}
.wr-user{font-size:.72rem;font-weight:700;color:var(--t)}
.wr-prize{font-size:.6rem;font-weight:600;margin-top:1px}
.wr-prize.win{color:var(--pri,var(--sec))}
.wr-prize.zonk{color:var(--t3)}
.wr-time{font-size:.58rem;color:var(--t3);white-space:nowrap;flex-shrink:0}
.win-empty{padding:30px;text-align:center;font-size:.72rem;color:var(--t3)}
.my-row{display:flex;align-items:center;padding:10px 14px;border-bottom:1px solid var(--tint-1);gap:8px}

/* ── Info Section ── */
.spin-info{position:relative;z-index:1;margin:16px 16px 0;background:var(--s);border:1px solid var(--bd,rgba(var(--sec-rgb,56,189,248),.25));border-radius:14px;padding:16px}
.si-title{font-size:.82rem;font-weight:700;color:var(--pri,var(--sec));margin-bottom:10px;display:flex;align-items:center;gap:6px}
.si-title svg{width:16px;height:16px}
.si-item{display:flex;align-items:flex-start;gap:8px;margin-bottom:8px}
.si-num{width:20px;height:20px;border-radius:50%;background:var(--bg);border:1px solid var(--bd,rgba(var(--sec-rgb,56,189,248),.25));display:flex;align-items:center;justify-content:center;font-size:.55rem;font-weight:800;color:var(--pri,var(--sec));flex-shrink:0;margin-top:1px}
.si-text{font-size:.68rem;color:var(--t2);line-height:1.6}
.si-text b{color:var(--pri,var(--sec))}
.si-note{margin-top:10px;padding:10px;background:rgba(251,191,36,.06);border:1px solid rgba(251,191,36,.15);border-radius:8px;font-size:.62rem;color:var(--t3);line-height:1.5}

/* ── Result Overlay ── */
#resultOverlay{display:none;position:fixed;inset:0;z-index:500;align-items:flex-end;justify-content:center;padding:0}
#resultOverlay.show{display:flex}
.ro-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.75);backdrop-filter:blur(6px)}
.ro-sheet{position:relative;z-index:1;background:linear-gradient(180deg,var(--s) 0%,var(--bg) 100%);border-radius:28px 28px 0 0;width:100%;max-width:390px;padding:0 0 40px;border-top:1px solid var(--bd,rgba(var(--sec-rgb,56,189,248),.25));overflow:hidden}
.ro-sheet::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,#fbbf24,transparent)}

/* Win state */
.ro-win-bg{position:absolute;inset:0;background:radial-gradient(ellipse at 50% 0%,rgba(251,191,36,.15) 0%,transparent 60%);pointer-events:none}
.ro-handle{width:36px;height:4px;background:var(--tint-3);border-radius:2px;margin:14px auto 0}

.ro-icon-wrap{width:90px;height:90px;border-radius:50%;margin:20px auto 16px;display:flex;align-items:center;justify-content:center;position:relative}
.ro-icon-wrap.win{background:radial-gradient(circle,rgba(251,191,36,.2),transparent);border:2px solid rgba(251,191,36,.4);box-shadow:0 0 30px rgba(251,191,36,.3)}
.ro-icon-wrap.lose{background:var(--tint-1);border:2px solid var(--tint-2)}
.ro-emoji{font-size:2rem;line-height:1;color:var(--pri,var(--sec))}

.ro-title{text-align:center;font-size:.75rem;color:var(--bd2);margin-bottom:6px}
.ro-prize-name{text-align:center;font-size:2rem;font-weight:900;margin-bottom:4px;line-height:1.1}
.ro-prize-name.win{background:linear-gradient(135deg,#fbbf24,#fde68a,#fbbf24);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text}
.ro-prize-name.lose{color:var(--bd2)}
.ro-subtitle{text-align:center;font-size:.72rem;color:var(--bd2);margin-bottom:24px}

.ro-actions{padding:0 20px;display:flex;flex-direction:column;gap:8px}
.ro-btn-main{padding:14px;border:none;border-radius:24px;font-size:.88rem;font-weight:700;cursor:pointer;font-family:inherit;width:100%}
.ro-btn-main.win{background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#78350f;box-shadow:0 4px 16px rgba(251,191,36,.4)}
.ro-btn-main.lose{background:linear-gradient(135deg,#1e40af,#3b82f6);color:#fff}
.ro-btn-secondary{padding:12px;background:var(--tint-2);border:1px solid var(--tint-3);border-radius:24px;font-size:.78rem;color:rgba(255,255,255,.6);cursor:pointer;font-family:inherit;width:100%}

/* Confetti */
.cf{position:fixed;pointer-events:none;z-index:600;border-radius:2px;animation:cfFall linear forwards}
@keyframes cfFall{0%{transform:translateY(-20px) rotate(0deg);opacity:1}100%{transform:translateY(110vh) rotate(900deg);opacity:0}}

</style>
</head>
<body>


<?php if(!$isLoggedIn){ $guestBannerLabel = 'fitur Spin Roulette'; require __DIR__.'/includes/guest_banner.php'; } ?>
<!-- Header -->
<div class="sp-header">
  <a href="dashboard.php" class="sp-back">
    <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
  </a>
  <div class="sp-title-wrap">
    <h1>Lucky Spin</h1>
    <p>Putar roda, raih hadiah jutaan rupiah</p>
  </div>
</div>

<!-- Ticket pill -->
<div class="ticket-pill">
  <div class="tp-icon">
    <svg viewBox="0 0 24 24"><path d="M15 5v2M15 11v2M15 17v2M5 5h14a2 2 0 012 2v3a2 2 0 000 4v3a2 2 0 01-2 2H5a2 2 0 01-2-2v-3a2 2 0 000-4V7a2 2 0 012-2z"/></svg>
  </div>
  <div class="tp-info">
    <div class="tp-count" id="ticketCount">-</div>
    <div class="tp-label">Tiket tersisa · 1 tiket = deposit Rp 100rb</div>
  </div>
  <a href="deposit.php" class="tp-deposit">+ Deposit</a>
</div>

<!-- Wheel -->
<div class="wheel-section">
  <div class="wheel-glow">
    <div class="wheel-frame">
      <canvas id="spinCanvas" width="308" height="308"></canvas>
    </div>
    <!-- Pointer -->
    <div class="pointer-wrap">
    <svg viewBox="0 0 28 34" fill="none" xmlns="http://www.w3.org/2000/svg">
      <defs><linearGradient id="pg2" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="#fde68a"/><stop offset="100%" stop-color="#f59e0b"/></linearGradient></defs>
      <polygon points="14,34 1,6 27,6" fill="url(#pg2)" stroke="rgba(255,255,255,.8)" stroke-width="1.5" stroke-linejoin="round"/>
      <circle cx="14" cy="7" r="6.5" fill="url(#pg2)" stroke="rgba(255,255,255,.9)" stroke-width="1.5"/>
      <circle cx="14" cy="7" r="3" fill="#fff"/>
    </svg>
  </div>
    <!-- Center button -->
    <div class="spin-center">
      <button id="spinBtn" onclick="doSpin()">PUTAR!</button>
    </div>
  </div>

  <!-- Dots -->
  <div class="spin-dots" id="spinDots"></div>
</div>

<!-- Winners Panel -->
<div class="win-panel">
  <div class="win-tabs">
    <div class="win-tab active" onclick="winTab(0,this)">Daftar Pemenang</div>
    <div class="win-tab" onclick="winTab(1,this)">Catatan Saya</div>
  </div>
  <div class="win-body" id="winBody0">
    <div class="win-list" id="winLive"></div>
  </div>
  <div class="win-body" id="winBody1" style="display:none;overflow-y:auto;height:260px">
    <?php if(!empty($_history)): ?>
      <?php foreach($_history as $hi): $isW=intval($hi['amount'])>0; ?>
      <div class="my-row">
        <div class="wr-icon <?=$isW?'win':'zonk'?>" style="color:<?=$isW?'var(--pri,var(--sec))':'var(--t3)'?>"><?=$isW?'<svg viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><polygon points="12,2 15,9 22,9 16,14 18,22 12,17 6,22 8,14 2,9 9,9"/></svg>':'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="12" height="12"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>'?></div>
        <div class="wr-mid">
          <div class="wr-user"><?=htmlspecialchars($hi['label'])?></div>
          <div class="wr-prize <?=$isW?'win':'zonk'?>"><?=$isW?'+ Rp '.number_format($hi['amount'],0,',','.'):'Tidak beruntung'?></div>
        </div>
        <div class="wr-time"><?=date('d M H:i',strtotime($hi['created_at']))?></div>
      </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="win-empty">Belum ada catatan spin</div>
    <?php endif; ?>
  </div>
</div>

<!-- Cara Mendapatkan Tiket -->
<div class="spin-info">
  <div class="si-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg> Cara Mendapatkan Tiket</div>
  <div class="si-item"><div class="si-num">1</div><div class="si-text">Setiap <b>deposit Rp 100.000</b> mendapatkan <b>1 tiket</b> spin gratis.</div></div>
  <div class="si-item"><div class="si-num">2</div><div class="si-text">Tiket otomatis masuk setelah deposit berhasil dikonfirmasi.</div></div>
  <div class="si-item"><div class="si-num">3</div><div class="si-text">Hadiah langsung masuk ke <b>saldo akun</b> dan bisa digunakan untuk bermain.</div></div>
  <div class="si-item"><div class="si-num">4</div><div class="si-text">Hadiah wajib mencapai <b>x1 turnover</b> sebelum dapat ditarik.</div></div>
  <div class="si-note">Contoh: Deposit Rp 500.000 = 5 tiket spin. Deposit Rp 1.000.000 = 10 tiket spin. Semakin banyak deposit, semakin banyak kesempatan menang!</div>
</div>

<!-- Result overlay -->
<div id="resultOverlay">
  <div class="ro-backdrop" onclick="closeResult()"></div>
  <div class="ro-sheet">
    <div class="ro-win-bg" id="roWinBg" style="display:none"></div>
    <div class="ro-handle"></div>
    <div class="ro-icon-wrap" id="roIconWrap">
      <div class="ro-emoji" id="roEmoji"><svg viewBox="0 0 24 24" fill="currentColor" width="36" height="36"><polygon points="12,2 15,9 22,9 16,14 18,22 12,17 6,22 8,14 2,9 9,9"/></svg></div>
    </div>
    <div class="ro-title" id="roTitle">Selamat!</div>
    <div class="ro-prize-name" id="roPrizeName">Rp 5.000.000</div>
    <div class="ro-subtitle" id="roSubtitle">Hadiah langsung masuk ke saldo kamu</div>
    <div class="ro-actions">
      <button class="ro-btn-main" id="roMainBtn" onclick="closeResult()">Klaim Hadiah!</button>
      <button class="ro-btn-secondary" id="roAgainBtn" onclick="closeResult()" style="display:none">Putar Lagi</button>
    </div>
  </div>
</div>

<script>
var prizes=<?php echo $PRIZES_JSON; ?>;
var tickets=<?php echo $TICKETS_JSON; ?>;
var historyData=<?php echo $HISTORY_JSON; ?>;
var spinning=false, currentAngle=0;

function drawWheel(){
  var cv=document.getElementById('spinCanvas');
  var ctx=cv.getContext('2d');
  // Retina 2x for crisp text on mobile
  var dpr=window.devicePixelRatio||2;
  var cssW=308,cssH=308;
  cv.width=cssW*dpr;cv.height=cssH*dpr;
  cv.style.width=cssW+'px';cv.style.height=cssH+'px';
  ctx.scale(dpr,dpr);

  var cx=154,cy=154,r=148;
  var n=prizes.length;
  if(!n)return;

  ctx.clearRect(0,0,cssW,cssH);

  // Ambil warna tema dari CSS var (biar ikut tema aktif, ga hardcoded)
  var styles=getComputedStyle(document.documentElement);
  var themePrimary=(styles.getPropertyValue('--sec')||'var(--sec)').trim();
  var themePrimaryD=(styles.getPropertyValue('--sec-d')||'var(--sec-d)').trim();
  // Alternating 2-tone yang rapi: emas ↔ tema primary (selang-seling tiap slice)
  var palette=[
    {bg:'#fbbf24',bgD:'#f59e0b',txt:'#78350f'},  // Emas
    {bg:themePrimary,bgD:themePrimaryD,txt:'#ffffff'}, // Tema
    {bg:'#fde68a',bgD:'#fcd34d',txt:'#78350f'},  // Emas muda
    {bg:themePrimaryD,bgD:themePrimary,txt:'#ffffff'}, // Tema darker
  ];
  var slice=2*Math.PI/n;

  for(var i=0;i<n;i++){
    var a1=currentAngle+(i*slice)-(Math.PI/2);
    var a2=currentAngle+((i+1)*slice)-(Math.PI/2);
    var mid=(a1+a2)/2;
    var pal=palette[i%palette.length];
    // Override kalau prize punya warna sendiri
    var bg=prizes[i].color||pal.bg;
    var bgD=pal.bgD;
    var txtColor=pal.txt;

    // Draw slice dengan GRADIENT (dari pinggir ke tengah)
    var grad=ctx.createRadialGradient(cx,cy,r*0.3,cx,cy,r);
    grad.addColorStop(0,bgD);
    grad.addColorStop(1,bg);
    ctx.beginPath();
    ctx.moveTo(cx,cy);
    ctx.arc(cx,cy,r,a1,a2);
    ctx.closePath();
    ctx.fillStyle=grad;
    ctx.fill();
    ctx.strokeStyle='rgba(0,0,0,.5)';
    ctx.lineWidth=1.5;
    ctx.stroke();

    // Draw text radially
    var p=prizes[i];
    var isZonk=p.label==='Zonk';
    var lbl=isZonk?'ZONK':p.label.replace('Rp ','').replace('.000.000','JT').replace('.000','RB');

    ctx.save();
    ctx.translate(cx,cy);
    ctx.rotate(mid);

    ctx.textAlign='right';
    ctx.textBaseline='middle';

    var fontSize=n<=8?13:n<=10?11:10;
    ctx.font='900 '+fontSize+'px Poppins,Arial,sans-serif';

    var textR=r*0.58;

    ctx.shadowColor='rgba(0,0,0,.7)';
    ctx.shadowBlur=3;
    ctx.shadowOffsetX=1;
    ctx.shadowOffsetY=1;

    ctx.strokeStyle='rgba(0,0,0,.8)';
    ctx.lineWidth=2.5;
    ctx.lineJoin='round';

    ctx.strokeText(lbl,textR,0);
    ctx.fillStyle=isZonk?'rgba(255,255,255,.6)':'#fff';
    ctx.fillText(lbl,textR,0);

    ctx.shadowColor='transparent';
    ctx.restore();
  }

  // Gold ring
  ctx.beginPath();
  ctx.arc(cx,cy,r,0,2*Math.PI);
  ctx.strokeStyle='#fbbf24';
  ctx.lineWidth=4;
  ctx.stroke();
  // Inner subtle ring
  ctx.beginPath();
  ctx.arc(cx,cy,r-4,0,2*Math.PI);
  ctx.strokeStyle='rgba(251,191,36,.3)';
  ctx.lineWidth=1;
  ctx.stroke();

  // Light dots on rim
  for(var j=0;j<n;j++){
    var da=currentAngle+(j*slice)-(Math.PI/2);
    ctx.beginPath();
    ctx.arc(cx+(r-2)*Math.cos(da),cy+(r-2)*Math.sin(da),2.5,0,2*Math.PI);
    ctx.fillStyle='rgba(255,255,255,.85)';
    ctx.fill();
  }

  // Center circle
  var cg=ctx.createRadialGradient(cx-4,cy-4,2,cx,cy,26);
  cg.addColorStop(0,'#fde68a');cg.addColorStop(1,'#f59e0b');
  ctx.beginPath();
  ctx.arc(cx,cy,26,0,2*Math.PI);
  ctx.fillStyle=cg;
  ctx.fill();
  ctx.strokeStyle='rgba(255,255,255,.8)';
  ctx.lineWidth=2.5;
  ctx.stroke();
}

function lighten(hex,amt){
  var r=parseInt(hex.slice(1,3),16),g=parseInt(hex.slice(3,5),16),b=parseInt(hex.slice(5,7),16);
  r=Math.min(255,Math.round(r+(255-r)*amt));
  g=Math.min(255,Math.round(g+(255-g)*amt));
  b=Math.min(255,Math.round(b+(255-b)*amt));
  return '#'+[r,g,b].map(x=>x.toString(16).padStart(2,'0')).join('');
}


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
  if(tickets<=0){showToast('Tidak ada tiket! Deposit min Rp 100.000');return;}
  spinning=true;
  document.getElementById('spinBtn').disabled=true;
  document.getElementById('spinBtn').textContent='...';

  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'spin_do'})})
  .then(r=>r.json()).then(function(res){
    if(!res.ok){if(window.handleAuthError&&handleAuthError(res))return;spinning=false;document.getElementById('spinBtn').disabled=false;document.getElementById('spinBtn').textContent='PUTAR!';showToast(res.error||'Gagal');return;}
    var prizeIdx=prizes.findIndex(function(p){return p.id==res.winner.id});
    if(prizeIdx<0)prizeIdx=0;
    var n=prizes.length,arc=360/n;
    var targetSlice=-prizeIdx*arc-arc/2;
    var spins=6+Math.floor(Math.random()*4);
    animateSpin(spins*360+targetSlice,res.winner,res.tickets_left);
  }).catch(function(e){
    spinning=false;
    document.getElementById('spinBtn').disabled=false;
    document.getElementById('spinBtn').textContent='PUTAR!';
    showToast('Error: '+e.message);
  });
}

function animateSpin(deg,winner,ticketsLeft){
  document.querySelector('.wheel-glow').classList.add('spinning');
  var start=null,dur=5000,startA=currentAngle*(180/Math.PI);
  startSpinTicks(dur);  // 🔊 spin sound effect
  function ease(t){return 1-Math.pow(1-t,4);}
  function frame(ts){
    if(!start)start=ts;
    var p=Math.min((ts-start)/dur,1);
    var a=startA+deg*ease(p);
    currentAngle=(a%360)*(Math.PI/180);
    drawWheel();
    if(p<1){requestAnimationFrame(frame);}
    else{
      spinning=false;
      document.querySelector('.wheel-glow').classList.remove('spinning');
      tickets=ticketsLeft;
      updateUI();
      // 🔊 Win/lose audio cue
      if(winner.amount>0)playWinSound();else playLoseSound();
      setTimeout(function(){showResult(winner);},200);
    }
  }
  requestAnimationFrame(frame);
}

function showResult(winner){
  var isWin=winner.amount>0;
  document.getElementById('roWinBg').style.display=isWin?'block':'none';
  document.getElementById('roIconWrap').className='ro-icon-wrap '+(isWin?'win':'lose');
  document.getElementById('roEmoji').innerHTML=isWin?'<svg viewBox="0 0 24 24" fill="currentColor" width="36" height="36"><polygon points="12,2 15,9 22,9 16,14 18,22 12,17 6,22 8,14 2,9 9,9"/></svg>':'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="36" height="36"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>';
  document.getElementById('roTitle').textContent=isWin?'Selamat, kamu menang!':'Lebih beruntung lain kali!';
  var pn=document.getElementById('roPrizeName');
  pn.textContent=isWin?winner.label:'Zonk!';
  pn.className='ro-prize-name '+(isWin?'win':'lose');
  document.getElementById('roSubtitle').textContent=isWin?'Hadiah langsung masuk ke saldo akun':'Coba lagi, masih banyak kesempatan!';
  var mb=document.getElementById('roMainBtn');
  mb.className='ro-btn-main '+(isWin?'win':'lose');
  mb.textContent=isWin?'Klaim Hadiah!':'Putar Lagi';
  document.getElementById('roAgainBtn').style.display=(tickets>0&&isWin)?'block':'none';
  document.getElementById('resultOverlay').classList.add('show');
  if(isWin)confetti();
}

function closeResult(){
  document.getElementById('resultOverlay').classList.remove('show');
  loadSpin();
}

function confetti(){
  // Ambil warna tema dari CSS var biar ikut tema aktif
  var styles=getComputedStyle(document.documentElement);
  var themeCol=(styles.getPropertyValue('--sec')||'var(--sec)').trim();
  var themeColD=(styles.getPropertyValue('--sec-d')||'var(--sec-d)').trim();
  // Palette emas + tema (rapi, ga norak)
  var cols=['#fbbf24','#f59e0b','#fde68a','#fcd34d',themeCol,themeColD,'#ffffff','#fff9e6'];
  for(var i=0;i<80;i++){
    (function(i){
      setTimeout(function(){
        var el=document.createElement('div');
        el.className='cf';
        var sz=6+Math.random()*6;
        el.style.cssText='left:'+Math.random()*100+'vw;top:-20px;width:'+sz+'px;height:'+sz+'px;background:'+cols[Math.floor(Math.random()*cols.length)]+';animation-duration:'+(1.8+Math.random()*2)+'s;border-radius:'+(Math.random()>0.5?'50%':'2px');
        document.body.appendChild(el);
        setTimeout(()=>el.remove(),4500);
      },i*30);
    })(i);
  }
}

function winTab(idx,el){
  document.querySelectorAll('.win-tab').forEach(function(t){t.classList.remove('active')});
  el.classList.add('active');
  document.getElementById('winBody0').style.display=idx===0?'block':'none';
  document.getElementById('winBody1').style.display=idx===1?'block':'none';
}

// ── Live Winners Feed ──
var _livePrizes=prizes.map(function(p){return{label:p.label,amount:parseInt(p.amount)||0}});
var _liveEl=document.getElementById('winLive');
var _maxRows=7;

function rndPhone(){return'628'+('****')+Math.floor(10000+Math.random()*90000)}
function rndTime(){return'Baru saja'}
function addWinner(){
  var p=_livePrizes[Math.floor(Math.random()*_livePrizes.length)];
  var isWin=p.amount>0;
  var lbl=p.label;
  var row=document.createElement('div');row.className='win-row';
  row.innerHTML='<div class="wr-icon '+(isWin?'win':'zonk')+'">'+(isWin?'<svg viewBox="0 0 24 24" fill="var(--pri,var(--sec))" width="14" height="14"><polygon points="12,2 15,9 22,9 16,14 18,22 12,17 6,22 8,14 2,9 9,9"/></svg>':'<svg viewBox="0 0 24 24" fill="none" stroke="var(--t3)" stroke-width="2.5" width="12" height="12"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>')+'</div>'
    +'<div class="wr-mid"><div class="wr-user">'+rndPhone()+'</div>'
    +'<div class="wr-prize '+(isWin?'win':'zonk')+'">'+(isWin?lbl:'Zonk')+'</div></div>'
    +'<div class="wr-time">'+rndTime()+'</div>';
  _liveEl.insertBefore(row,_liveEl.firstChild);
  if(_liveEl.children.length>_maxRows)_liveEl.removeChild(_liveEl.lastChild);
}
// Seed initial rows
for(var _i=0;_i<_maxRows;_i++)addWinner();
// Add new winner every 2-3 seconds
setInterval(addWinner,2000+Math.floor(Math.random()*1000));

function showToast(msg){
  var t=document.createElement('div');
  t.style.cssText='position:fixed;bottom:90px;left:50%;transform:translateX(-50%);background:var(--s);color:#fff;padding:10px 20px;border-radius:20px;font-size:.78rem;font-weight:600;z-index:999;border:1px solid var(--tint-2);white-space:nowrap;box-shadow:0 4px 16px rgba(0,0,0,.4)';
  t.textContent=msg;document.body.appendChild(t);
  setTimeout(()=>t.remove(),2500);
}

function updateUI(){
  document.getElementById('ticketCount').textContent=tickets;
  var btn=document.getElementById('spinBtn');
  btn.disabled=(tickets<=0);
  btn.textContent=tickets>0?'PUTAR!':'0 TIKET';
  // dots
  var dots=document.getElementById('spinDots');
  dots.innerHTML='';
  var show=Math.min(tickets,8);
  for(var i=0;i<show;i++){
    var d=document.createElement('div');d.className='spin-dot active';dots.appendChild(d);
  }
  if(tickets>8){var more=document.createElement('span');more.style.cssText='font-size:.6rem;color:var(--bd2);margin-left:4px';more.textContent='+'+(tickets-8);dots.appendChild(more);}
}

function loadSpin(){
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'spin_init'})})
  .then(r=>r.json()).then(function(res){
    if(!res.ok)return;
    // Interleave: spread Zonks between prizes
    var wins=[],zonks=[];
    (res.prizes||[]).forEach(function(p){if(p.label==='Zonk')zonks.push(p);else wins.push(p)});
    var mixed=[],zi=0;
    wins.forEach(function(w,i){mixed.push(w);if(zi<zonks.length&&i<wins.length-1){mixed.push(zonks[zi]);zi++}});
    while(zi<zonks.length){mixed.push(zonks[zi]);zi++}
    prizes=mixed;
    tickets=res.tickets;
    updateUI();
    drawWheel();
  }).catch(function(){});
}

// ── Immediate render from PHP-embedded data ──
updateUI();
drawWheel();
</script>
<?php include 'includes/credit_notify.php'; ?>
</body>
</html>
