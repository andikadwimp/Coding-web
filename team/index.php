<?php
require_once __DIR__.'/../includes/config.php';
header('Content-Type: text/html; charset=UTF-8');
// Login POST
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='login'){
    $u=trim($_POST['u']??'');$pw=$_POST['p']??'';
    $s=$db->prepare("SELECT * FROM users WHERE username=? AND role='admin'");$s->execute([$u]);$admin=$s->fetch();
    if($admin&&password_verify($pw,$admin['password'])){
        $token=bin2hex(random_bytes(32));
        $db->prepare('UPDATE users SET auth_token=? WHERE id=?')->execute([$token,$admin['id']]);
        setcookie('lx_token',$token,time()+86400*3650,'/','',isset($_SERVER['HTTPS']),true);
        header('Location:index.php');exit;
    }
    $err='Username atau password salah';
}
require_once 'layout.php';
if(!$isAdmin):
?>
<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>Admin Login</title>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
<style>
*{margin:0;padding:0;box-sizing:border-box}
:root{--ease-out-expo:cubic-bezier(.16,1,.3,1);--ease-spring:cubic-bezier(.34,1.56,.64,1)}
body{font-family:'Plus Jakarta Sans',-apple-system,BlinkMacSystemFont,system-ui,sans-serif;background:radial-gradient(circle at 20% 0%,#eef2ff 0%,#fafaf9 45%,#f4f4f3 100%);color:#0a0a0a;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:24px;-webkit-font-smoothing:antialiased;position:relative;overflow:hidden}
body::before{content:'';position:absolute;top:-200px;right:-200px;width:500px;height:500px;background:radial-gradient(circle,rgba(37,99,235,.08),transparent 70%);border-radius:50%;pointer-events:none}
body::after{content:'';position:absolute;bottom:-300px;left:-200px;width:600px;height:600px;background:radial-gradient(circle,rgba(31,41,55,.06),transparent 70%);border-radius:50%;pointer-events:none}
@keyframes lgIn{from{opacity:0;transform:translateY(12px) scale(.98)}to{opacity:1;transform:translateY(0) scale(1)}}
@keyframes lgFade{from{opacity:0}to{opacity:1}}
.box{background:#fff;border:1px solid #e5e5e4;border-radius:14px;padding:38px 32px 30px;width:100%;max-width:380px;position:relative;animation:lgIn .55s var(--ease-out-expo) both;box-shadow:0 20px 60px rgba(0,0,0,.06),0 1px 3px rgba(0,0,0,.04)}
.box::before{content:'';position:absolute;top:0;left:24px;right:24px;height:1px;background:linear-gradient(90deg,transparent,rgba(37,99,235,.5),transparent)}
.brand{display:flex;align-items:center;gap:12px;margin-bottom:28px;justify-content:center;animation:lgFade .6s ease .15s both}
.brand-logo{width:38px;height:38px;border-radius:9px;background:linear-gradient(135deg,#1f2937,#0f172a);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:1.05rem;letter-spacing:-.02em;box-shadow:0 4px 10px rgba(15,23,42,.18),0 0 0 1px rgba(255,255,255,.08) inset}
.brand-name{font-size:1.05rem;font-weight:800;color:#0a0a0a;letter-spacing:-.025em;line-height:1.2}
.brand-name small{display:block;font-size:.62rem;color:#737373;font-weight:500;letter-spacing:.6px;text-transform:uppercase;margin-top:3px;font-family:'JetBrains Mono',monospace}
h2{font-size:1.2rem;margin-bottom:6px;color:#0a0a0a;font-weight:800;letter-spacing:-.03em;animation:lgFade .6s ease .2s both}
.subtitle{font-size:.81rem;color:#737373;margin-bottom:26px;font-weight:500;letter-spacing:-.005em;animation:lgFade .6s ease .25s both}
.fg{margin-bottom:14px;animation:lgFade .6s ease .3s both}
.fg:nth-of-type(2){animation-delay:.35s}
.fg label{font-size:.74rem;font-weight:700;color:#0a0a0a;margin-bottom:7px;display:block;letter-spacing:-.005em}
.fg input{width:100%;padding:11px 14px;background:#fff;border:1.5px solid #d4d4d3;border-radius:8px;color:#0a0a0a;font-family:inherit;font-size:.88rem;font-weight:500;outline:none;transition:border-color .15s,box-shadow .15s,background .15s;letter-spacing:-.005em}
.fg input:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,.14);background:#fafbff}
.fg input:hover:not(:focus){border-color:#a3a3a3}
.fg input::placeholder{color:#a3a3a3;font-weight:400}
.btn{width:100%;padding:12px;background:linear-gradient(180deg,#1f2937,#111827);border:1px solid #111827;border-radius:8px;color:#fff;font-size:.88rem;font-weight:700;cursor:pointer;font-family:inherit;transition:transform .15s var(--ease-out-expo),box-shadow .25s,background .15s;letter-spacing:-.005em;margin-top:10px;position:relative;overflow:hidden;animation:lgFade .6s ease .4s both}
.btn:hover{box-shadow:0 6px 18px rgba(15,23,42,.22)}
.btn:active{transform:scale(.98)}
.btn::before{content:'';position:absolute;inset:0;background:linear-gradient(120deg,transparent 30%,rgba(255,255,255,.18) 50%,transparent 70%);transform:translateX(-100%);transition:transform .6s var(--ease-out-expo)}
.btn:hover::before{transform:translateX(100%)}
.err{color:#991b1b;font-size:.78rem;margin-bottom:14px;background:#fef2f2;border:1px solid #fecaca;padding:10px 13px;border-radius:7px;font-weight:550;letter-spacing:-.005em;animation:lgIn .35s var(--ease-spring) both;display:flex;align-items:center;gap:8px}
.err::before{content:'';width:16px;height:16px;flex-shrink:0;background:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23dc2626' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='12' cy='12' r='10'/%3E%3Cline x1='12' y1='8' x2='12' y2='12'/%3E%3Cline x1='12' y1='16' x2='12.01' y2='16'/%3E%3C/svg%3E")center/contain no-repeat}
.foot{text-align:center;margin-top:18px;font-size:.7rem;color:#a3a3a3;font-weight:500;letter-spacing:-.005em;animation:lgFade .6s ease .5s both}
@media(prefers-reduced-motion:reduce){*{animation-duration:.01ms!important;transition-duration:.01ms!important}}
</style>
</head><body><form class="box" method="POST"><input type="hidden" name="action" value="login">
<div class="brand"><div class="brand-logo">A</div><div class="brand-name">Admin Panel<small>Control Center</small></div></div>
<h2>Masuk</h2>
<p class="subtitle">Akses dashboard administrasi</p>
<?php if(isset($err)): ?><div class="err"><?=$err?></div><?php endif; ?>
<div class="fg"><label>Username</label><input type="text" name="u" required autofocus placeholder="admin" autocomplete="username"></div>
<div class="fg"><label>Password</label><input type="password" name="p" required placeholder="••••••••" autocomplete="current-password"></div>
<button class="btn" type="submit">Masuk</button>
<div class="foot">Otorisasi terbatas · Akses dimonitor</div>
</form></body></html>
<?php exit;endif;

// ─── Stats Query ───
$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
$month = date('Y-m');

// Deposit income
$dep_today     = $db->query("SELECT COALESCE(SUM(nominal),0) FROM deposits WHERE status='paid' AND DATE(paid_at)='$today'")->fetchColumn();
$dep_month     = $db->query("SELECT COALESCE(SUM(nominal),0) FROM deposits WHERE status='paid' AND DATE_FORMAT(paid_at,'%Y-%m')='$month'")->fetchColumn();
$dep_total     = $db->query("SELECT COALESCE(SUM(nominal),0) FROM deposits WHERE status='paid'")->fetchColumn();
$dep_count_today = $db->query("SELECT COUNT(*) FROM deposits WHERE status='paid' AND DATE(paid_at)='$today'")->fetchColumn();

// Withdrawals
$wd_today      = $db->query("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE status='approved' AND DATE(processed_at)='$today'")->fetchColumn();
$wd_pending    = $db->query("SELECT COUNT(*) FROM withdrawals WHERE status='pending'")->fetchColumn();
$wd_amount_pending = $db->query("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE status='pending'")->fetchColumn();
$wd_month      = $db->query("SELECT COALESCE(SUM(amount),0) FROM withdrawals WHERE status='approved' AND DATE_FORMAT(processed_at,'%Y-%m')='$month'")->fetchColumn();

// Profit
$profit_today  = $dep_today - $wd_today;
$profit_month  = $dep_month - $wd_month;

// Users
$users_total   = $db->query("SELECT COUNT(*) FROM users WHERE role='user'")->fetchColumn();
$users_today   = $db->query("SELECT COUNT(*) FROM users WHERE role='user' AND DATE(created_at)='$today'")->fetchColumn();
$users_active  = $db->query("SELECT COUNT(*) FROM users WHERE role='user' AND balance>0")->fetchColumn();

// Balance in system
$balance_total = $db->query("SELECT COALESCE(SUM(balance),0) FROM users")->fetchColumn();

// Deposits pending
$dep_pending   = $db->query("SELECT COUNT(*) FROM deposits WHERE status='pending'")->fetchColumn();

// Games
$providers     = $db->query("SELECT COUNT(*) FROM providers WHERE status=1")->fetchColumn();
$games         = $db->query("SELECT COUNT(*) FROM games WHERE status=1")->fetchColumn();

// Recent deposits (5 terbaru paid)
$recent_deps = $db->query("SELECT d.tx_id,d.nominal,d.method,d.paid_at,u.username FROM deposits d LEFT JOIN users u ON u.id=d.user_id WHERE d.status='paid' ORDER BY d.paid_at DESC LIMIT 8")->fetchAll();

// Recent registrations
$recent_users = $db->query("SELECT username,phone,created_at FROM users WHERE role='user' ORDER BY created_at DESC LIMIT 8")->fetchAll();

adminHeader('Dashboard');
?>
<style>
.stat-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;margin-bottom:24px}
.stat-card{background:#fff;border:1px solid var(--bd);border-radius:12px;padding:18px 20px;position:relative;overflow:hidden;transition:border-color .2s var(--ease-out-expo,cubic-bezier(.16,1,.3,1)),transform .25s var(--ease-out-expo,cubic-bezier(.16,1,.3,1)),box-shadow .25s ease;animation:adminFadeIn .45s var(--ease-out-expo,cubic-bezier(.16,1,.3,1)) both}
.stat-card::after{content:'';position:absolute;left:0;right:0;bottom:0;height:2px;background:linear-gradient(90deg,transparent,var(--accent),transparent);opacity:0;transition:opacity .3s ease}
.stat-card:hover{border-color:var(--t4);transform:translateY(-2px);box-shadow:0 6px 18px rgba(0,0,0,.06)}
.stat-card:hover::after{opacity:1}
.stat-card:nth-child(1){animation-delay:.04s}
.stat-card:nth-child(2){animation-delay:.08s}
.stat-card:nth-child(3){animation-delay:.12s}
.stat-card:nth-child(4){animation-delay:.16s}
.stat-card:nth-child(5){animation-delay:.20s}
.stat-card:nth-child(6){animation-delay:.24s}
.stat-card svg.ic{position:absolute;right:16px;top:16px;width:18px;height:18px;color:var(--t4);opacity:.7;transition:transform .35s var(--ease-spring,cubic-bezier(.34,1.56,.64,1)),color .25s,opacity .25s}
.stat-card:hover svg.ic{transform:rotate(8deg) scale(1.12);color:var(--accent);opacity:1}
.stat-label{font-size:.66rem;color:var(--t3);text-transform:uppercase;letter-spacing:.85px;margin-bottom:8px;font-weight:700}
.stat-val{font-family:'JetBrains Mono','SF Mono',monospace;font-size:1.45rem;font-weight:700;line-height:1.1;letter-spacing:-.02em;font-feature-settings:'tnum';color:var(--t)}
.stat-sub{font-size:.7rem;color:var(--t3);margin-top:7px;font-weight:500;letter-spacing:-.005em}
.stat-sub b{color:var(--t2);font-weight:700;font-family:'JetBrains Mono',monospace;font-feature-settings:'tnum'}
.alert-box{display:flex;align-items:center;gap:11px;padding:14px 16px;border-radius:10px;margin-bottom:22px;font-size:.83rem;font-weight:550;letter-spacing:-.005em;animation:adminFadeIn .4s var(--ease-out-expo,cubic-bezier(.16,1,.3,1)) both;position:relative;overflow:hidden}
.alert-wd{background:linear-gradient(180deg,#fef5f5,#fef2f2);border:1px solid #fecaca;color:#991b1b}
.alert-wd::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:linear-gradient(180deg,#dc2626,#991b1b)}
.alert-wd b{font-family:'JetBrains Mono',monospace;font-weight:700}
.alert-wd svg{animation:alertPulse 2s ease-in-out infinite}
@keyframes alertPulse{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.08);opacity:.85}}
.dash-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:22px}
@media(max-width:700px){.dash-grid{grid-template-columns:1fr}}
.table-box{background:#fff;border:1px solid var(--bd);border-radius:12px;overflow:hidden;transition:border-color .2s,box-shadow .25s ease}
.table-box:hover{border-color:var(--t4);box-shadow:0 4px 14px rgba(0,0,0,.04)}
.table-box h3{padding:14px 18px;font-size:.86rem;font-weight:800;border-bottom:1px solid var(--bd);display:flex;align-items:center;justify-content:space-between;letter-spacing:-.015em;color:var(--t)}
.table-box h3 a{font-size:.72rem;color:var(--accent);font-weight:600;text-decoration:none;letter-spacing:-.005em;display:inline-flex;align-items:center;gap:3px;transition:gap .2s var(--ease-out-expo,cubic-bezier(.16,1,.3,1))}
.table-box h3 a:hover{gap:6px;text-decoration:underline}
.mini-table{width:100%;font-size:.78rem;border-collapse:collapse}
.mini-table td{padding:11px 18px;border-bottom:1px solid var(--bd);letter-spacing:-.005em;transition:background .15s}
.mini-table tr:first-child td{font-size:.62rem;color:var(--t3);text-transform:uppercase;letter-spacing:.7px;font-weight:700;background:var(--bg);padding:9px 18px}
.mini-table tr:last-child td{border:none}
.mini-table tr:not(:first-child){transition:background .15s}
.mini-table tr:not(:first-child):hover td{background:var(--bg)}
.mini-table b{font-family:'JetBrains Mono',monospace;font-feature-settings:'tnum';font-weight:600}
.badge-pill{padding:3px 9px;border-radius:5px;font-size:.62rem;font-weight:700;letter-spacing:.3px}
.nexus-status{background:#fff;border:1px solid var(--bd);border-radius:10px;padding:18px}
.nexus-status h3{font-size:.86rem;font-weight:800;letter-spacing:-.015em;display:flex;align-items:center;justify-content:space-between;color:var(--t)}
.ns-row{display:flex;align-items:center;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--bd);font-size:.78rem;color:var(--t2)}
.ns-row:last-child{border:none}
.ns-row b{font-family:'JetBrains Mono',monospace;font-weight:600}
.ns-ok{color:var(--green)}
.ns-err{color:var(--red)}
.ns-warn{color:var(--orange)}
</style>

<?php if($wd_pending>0): ?>
<div class="alert-box alert-wd">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="20" height="20"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
  <span><?=$wd_pending?> penarikan menunggu persetujuan — Total: <b>Rp <?=number_format($wd_amount_pending,0,',','.')?></b></span>
  <a href="withdrawals.php" style="margin-left:auto;padding:6px 12px;background:var(--red);color:#fff;border-radius:6px;font-size:.7rem;font-weight:600;text-decoration:none;letter-spacing:-.005em">Proses →</a>
</div>
<?php endif; ?>

<!-- Stats Grid -->
<div class="stat-grid">
  <div class="stat-card">
    <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 2v14m-5-5l5 5 5-5"/><path d="M5 20h14"/></svg>
    <div class="stat-label">Deposit Hari Ini</div>
    <div class="stat-val" style="color:var(--green)">Rp <?=number_format($dep_today,0,',','.')?></div>
    <div class="stat-sub"><b><?=$dep_count_today?></b> transaksi · Bulan Rp <?=number_format($dep_month,0,',','.')?></div>
  </div>
  <div class="stat-card">
    <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 16V2m5 5l-5-5-5 5"/><path d="M5 20h14"/></svg>
    <div class="stat-label">Withdrawal Hari Ini</div>
    <div class="stat-val" style="color:var(--red)">Rp <?=number_format($wd_today,0,',','.')?></div>
    <div class="stat-sub">Bulan Rp <?=number_format($wd_month,0,',','.')?> · Pending <b style="color:var(--orange)"><?=$wd_pending?></b></div>
  </div>
  <div class="stat-card">
    <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/></svg>
    <div class="stat-label">Profit Hari Ini</div>
    <div class="stat-val" style="color:<?=$profit_today>=0?'var(--t)':'var(--red)'?>">Rp <?=number_format($profit_today,0,',','.')?></div>
    <div class="stat-sub">Bulan Rp <?=number_format($profit_month,0,',','.')?></div>
  </div>
  <div class="stat-card">
    <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
    <div class="stat-label">User Baru Hari Ini</div>
    <div class="stat-val"><?=$users_today?></div>
    <div class="stat-sub">Total <b><?=$users_total?></b> · Aktif <b><?=$users_active?></b></div>
  </div>
  <div class="stat-card">
    <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>
    <div class="stat-label">Saldo di Sistem</div>
    <div class="stat-val">Rp <?=number_format($balance_total,0,',','.')?></div>
    <div class="stat-sub">Total deposit Rp <?=number_format($dep_total,0,',','.')?></div>
  </div>
  <div class="stat-card">
    <svg class="ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 12h4M8 10v4"/><circle cx="17" cy="10" r="1"/><circle cx="15" cy="13" r="1"/></svg>
    <div class="stat-label">Game Aktif</div>
    <div class="stat-val"><?=$games?></div>
    <div class="stat-sub"><b><?=$providers?></b> provider<?=$dep_pending>0?' · Dep pending <b style="color:var(--orange)">'.$dep_pending.'</b>':''?></div>
  </div>
</div>

<!-- Recent Tables -->
<div class="dash-grid">
  <div class="table-box">
    <h3>Deposit Terbaru <a href="deposits.php">Lihat semua →</a></h3>
    <table class="mini-table">
      <tr><td style="color:var(--t3)">User</td><td style="color:var(--t3)">Nominal</td><td style="color:var(--t3)">Waktu</td></tr>
      <?php foreach($recent_deps as $d): ?>
      <tr>
        <td><b><?=htmlspecialchars($d['username']??'-')?></b></td>
        <td style="font-weight:700;color:var(--green)">Rp <?=number_format($d['nominal'],0,',','.')?></td>
        <td style="color:var(--t3)"><?=substr($d['paid_at']??'',0,16)?></td>
      </tr>
      <?php endforeach; ?>
      <?php if(!$recent_deps): ?><tr><td colspan="3" style="text-align:center;color:var(--t3);padding:20px">Belum ada</td></tr><?php endif; ?>
    </table>
  </div>
  <div class="table-box">
    <h3>User Daftar Terbaru <a href="users.php">Lihat semua →</a></h3>
    <table class="mini-table">
      <tr><td style="color:var(--t3)">Username</td><td style="color:var(--t3)">No. HP</td><td style="color:var(--t3)">Waktu</td></tr>
      <?php foreach($recent_users as $u): ?>
      <tr>
        <td><b><?=htmlspecialchars($u['username']??'-')?></b></td>
        <td style="color:var(--t3)"><?=htmlspecialchars($u['phone']??'-')?></td>
        <td style="color:var(--t3)"><?=substr($u['created_at']??'',0,16)?></td>
      </tr>
      <?php endforeach; ?>
      <?php if(!$recent_users): ?><tr><td colspan="3" style="text-align:center;color:var(--t3);padding:20px">Belum ada</td></tr><?php endif; ?>
    </table>
  </div>
</div>


<!-- NexusGGR Realtime Balance -->
<div class="nexus-status" id="nexusDiag" style="margin-top:20px">
  <h3 style="font-size:.82rem;font-weight:700;margin-bottom:10px;display:flex;align-items:center;justify-content:space-between">
    NexusGGR Agent Balance
    <div style="display:flex;align-items:center;gap:8px">
      <span id="nexusLastUpdate" style="font-size:.6rem;color:var(--t3);font-weight:400"></span>
      <button class="btn btn-sec btn-sm" onclick="checkNexus()" id="nexusBtn">↻ Refresh</button>
    </div>
  </h3>
  <div id="nexusContent" style="color:var(--t3);font-size:.75rem">Memuat...</div>
</div>
<script>
function checkNexus(){
  var btn=document.getElementById('nexusBtn');btn.textContent='...';btn.disabled=true;
  fetch('../api/game.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'nexus_status'})})
  .then(r=>r.json()).then(function(d){
    btn.textContent='↻ Refresh';btn.disabled=false;
    document.getElementById('nexusLastUpdate').textContent=new Date().toLocaleTimeString('id');
    if(!d.ok){document.getElementById('nexusContent').innerHTML='<span class="ns-err">Error: '+(d.error||'?')+'</span>';return;}
    var agBal=Number(d.agent_balance||0);
    var balColor=agBal>10000000?'var(--green)':agBal>1000000?'#fbbf24':'#ef4444';
    var h='<div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:10px">';
    h+='<div style="background:rgba(var(--sec-rgb,56,189,248),.06);border:1px solid var(--bd);border-radius:10px;padding:12px">';
    h+='<div style="font-size:.6rem;color:var(--t3);margin-bottom:4px;text-transform:uppercase">Agent Balance</div>';
    h+='<div style="font-size:1.1rem;font-weight:800;color:'+balColor+'">Rp '+agBal.toLocaleString('id')+'</div>';
    h+='<div style="font-size:.6rem;color:var(--t3);margin-top:2px">'+d.agent_code+'</div>';
    h+='</div>';
    h+='<div style="background:rgba(var(--sec-rgb,56,189,248),.06);border:1px solid var(--bd);border-radius:10px;padding:12px">';
    h+='<div style="font-size:.6rem;color:var(--t3);margin-bottom:4px;text-transform:uppercase">Status API</div>';
    h+='<div style="font-size:.88rem;font-weight:700;color:'+(d.connected?'var(--green)':'#ef4444')+'">'+(d.connected?'● Online':'● Offline')+'</div>';
    h+='<div style="font-size:.6rem;color:var(--t3);margin-top:2px">NexusGGR</div>';
    h+='</div></div>';
    h+='<div class="ns-row"><span>Deposit Test</span><b class="'+((d.test_deposit_1&&d.test_deposit_1.status==1)?'ns-ok':'ns-warn')+'">'+(d.test_deposit_1&&d.test_deposit_1.status==1?'✓ OK':'⚠ '+(d.test_deposit_1&&d.test_deposit_1.msg||'?'))+'</b></div>';
    h+='<div class="ns-row"><span>Withdraw Test</span><b class="'+((d.test_withdraw_1&&d.test_withdraw_1.status==1)?'ns-ok':'ns-warn')+'">'+(d.test_withdraw_1&&d.test_withdraw_1.status==1?'✓ OK':'⚠ '+(d.test_withdraw_1&&d.test_withdraw_1.msg||'?'))+'</b></div>';
    if(agBal<1000000)h+='<div style="margin-top:8px;padding:10px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);border-radius:8px;font-size:.72rem;color:#ef4444">⚠ Saldo agent rendah — segera top up NexusGGR!</div>';
    document.getElementById('nexusContent').innerHTML=h;
  }).catch(function(e){btn.textContent='↻ Refresh';btn.disabled=false;document.getElementById('nexusContent').innerHTML='<span class="ns-err">Gagal: '+e.message+'</span>';});
}
checkNexus();
setInterval(checkNexus,60000);
</script>
<?php adminFooter(); ?>
