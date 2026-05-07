<?php
require_once 'includes/config.php';
header('Content-Type: text/html; charset=UTF-8');
$isLoggedIn = (bool)getUid();  // guest-friendly: page accessible, action gated by JS

$sets=[];
try{$st=$db->query("SELECT `key`,`value` FROM settings");foreach($st->fetchAll() as $r)$sets[$r['key']]=$r['value'];normSettings($sets);}catch(Exception $e){}
$sn=$sets['site_name']??'Situs';
$uid=getUid();
$user=null;$notifCount=0;
if($uid){
  try{$u=$db->prepare("SELECT * FROM users WHERE id=?");$u->execute([$uid]);$user=$u->fetch();}catch(Exception $e){}
  try{$nc=$db->query("SELECT COUNT(*) FROM memos WHERE type IN('all','notif') AND is_read=0");$notifCount=$nc->fetchColumn();}catch(Exception $e){}
}
$vl=$user['vip_level']??0;
$bal=($user['balance']??0)/1000;
$dep=$user['total_deposit']??0;
$depK=$dep/1000;
// VIP next level calc
$vipTbl=function_exists('vipTable')?vipTable():[];
$nextLv=min($vl+1,count($vipTbl)-1);
$nextReq=isset($vipTbl[$nextLv])?$vipTbl[$nextLv][1]:0;
$curReq=isset($vipTbl[$vl])?$vipTbl[$vl][1]:0;
$pct=$nextReq>$curReq?min(100,(($depK-$curReq)/($nextReq-$curReq))*100):100;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php require_once dirname(__FILE__).'/pwa_head.php'; ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Profil - <?php echo $sn; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="theme.php?v=<?=time()?>">
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;-webkit-text-size-adjust:100%;text-size-adjust:100%}
html{font-size:16px!important;overflow-x:hidden}
body{font-family:'Plus Jakarta Sans','Outfit','Poppins',sans-serif;background:var(--bg);color:var(--t);min-height:100vh;padding-bottom:70px;overflow-x:hidden;max-width:100vw}
/* Header */
.prof-top{padding:28px 16px 20px;background:linear-gradient(180deg,rgba(var(--sec-rgb,56,189,248),.08),transparent)}
.prof-user{display:flex;align-items:center;gap:14px;margin-bottom:20px}
.prof-avatar{width:60px;height:60px;border-radius:50%;background:linear-gradient(135deg,#667,#445);display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;border:2px solid var(--bd)}
.prof-avatar img{width:100%;height:100%;object-fit:cover}
.prof-avatar .pa-text{font-size:1.2rem;font-weight:800;color:var(--t)}
.prof-info{flex:1;min-width:0}
.prof-info .pi-name{font-size:1.05rem;font-weight:700;display:flex;align-items:center;gap:8px}
.prof-info .pi-vip{display:inline-flex;align-items:center;gap:4px;padding:2px 10px;background:rgba(var(--pri-rgb,56,189,248),.15);border:1px solid rgba(var(--pri-rgb,56,189,248),.3);border-radius:12px;font-size:.58rem;font-weight:800;color:var(--pri)}
.prof-info .pi-vip svg{width:12px;height:12px}
.prof-info .pi-id{font-size:.7rem;color:var(--t3);margin-top:3px;display:flex;align-items:center;gap:6px}
.prof-info .pi-id button{background:none;border:none;color:var(--t3);cursor:pointer;padding:0;display:flex}
.prof-info .pi-id button svg{width:14px;height:14px}
/* Balance */
.bal-row{display:flex;gap:0;margin-bottom:16px}
.bal-col{flex:1;text-align:center}
.bal-col .bl-label{font-size:.68rem;color:var(--t3)}
.bal-col .bl-val{font-size:1.35rem;font-weight:800;margin-top:2px;font-family:'Poppins',sans-serif;letter-spacing:-.5px}
.bal-col .bl-val .bl-refresh{background:none;border:none;color:var(--t3);cursor:pointer;padding:4px;display:inline-flex;vertical-align:middle}
.bal-col .bl-val .bl-refresh svg{width:16px;height:16px}
/* Buttons */
/* VIP Card — style natural casino (bukan gradient AI gradient) */
.vip-card{margin:14px 16px 16px;border-radius:12px;padding:16px;background:var(--s);position:relative;overflow:hidden;border:1px solid var(--bd);box-shadow:0 2px 8px rgba(0,0,0,.15)}
.vip-card::before{display:none}
.vc-top{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px}
.vc-badge{display:inline-flex;align-items:center;gap:8px;background:var(--bg2);border:1px solid var(--bd);border-radius:8px;padding:6px 12px}
.vc-badge .vbi{width:24px;height:24px;border-radius:50%;background:var(--pri);display:flex;align-items:center;justify-content:center;color:#fff}
.vc-badge .vbi svg{width:14px;height:14px}
.vc-badge .vbt{font-size:.78rem;font-weight:800;color:var(--t)}
.vc-label{font-size:.66rem;color:var(--t3);margin-top:1px;display:block;width:100%}
.vc-detail{padding:8px 14px;background:var(--pri);border-radius:8px;font-size:.74rem;font-weight:700;color:#fff;display:flex;align-items:center;gap:6px;cursor:pointer;text-decoration:none;border:none;font-family:inherit}
.vc-detail svg{width:14px;height:14px}
.vc-prog{display:flex;align-items:center;gap:10px;margin-bottom:8px}
.vc-bar{flex:1;height:6px;border-radius:3px;background:var(--bg2);overflow:hidden;border:1px solid var(--bd)}
.vc-bar .fill{height:100%;border-radius:3px;background:var(--pri);transition:width .4s}
.vc-next{display:flex;align-items:center;gap:5px;font-size:.62rem;color:var(--t3);font-weight:700}
.vc-next .vni{width:18px;height:18px;border-radius:50%;background:var(--bg2);border:1px solid var(--bd);display:flex;align-items:center;justify-content:center;color:var(--t3)}
.vc-next .vni svg{width:10px;height:10px}
.vc-info{font-size:.7rem;color:var(--t2);font-weight:600}
.vc-info b{font-weight:800;color:var(--t)}
.vc-info span{color:var(--pri);font-weight:800}

/* Menu — style natural casino, list rapi */
.menu{margin:0 16px 16px;border-radius:12px;overflow:hidden;border:1px solid var(--bd);background:var(--s);animation:menuIn .45s cubic-bezier(.16,1,.3,1) both}
@keyframes menuIn{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:translateY(0)}}
.menu-i{display:flex;align-items:center;gap:14px;padding:14px 16px;background:var(--s);border-bottom:1px solid var(--bd);cursor:pointer;text-decoration:none;color:var(--t);transition:background .15s,padding-left .2s cubic-bezier(.16,1,.3,1)}
.menu-i:hover{background:var(--bg2);padding-left:20px}
.menu-i:hover .mi-icon{transform:scale(1.06);color:var(--pri2,var(--pri))}
.menu-i:hover .mi-arr{transform:translateX(3px)}
.menu-i:active{background:var(--bg2);transform:scale(.99)}
.menu-i:last-child{border:none}
.menu-i .mi-icon{width:36px;height:36px;border-radius:9px;background:var(--bg2);border:1px solid var(--bd);display:flex;align-items:center;justify-content:center;flex-shrink:0;color:var(--pri);transition:transform .25s cubic-bezier(.34,1.56,.64,1),color .15s,border-color .15s}
.menu-i .mi-icon svg{width:18px;height:18px}
.menu-i .mi-arr{transition:transform .2s cubic-bezier(.16,1,.3,1)}
.menu-i .mi-text{flex:1;min-width:0}
.menu-i .mi-text .mt-title{font-size:.85rem;font-weight:700;color:var(--t)}
.menu-i .mi-text .mt-sub{font-size:.62rem;color:var(--t3);margin-top:2px}
.menu-i .mi-badge{background:#ef4444;color:#fff;font-size:.58rem;font-weight:800;padding:2px 7px;border-radius:8px;min-width:18px;text-align:center}
.menu-i .mi-arr{color:var(--t3)}
.menu-i .mi-arr svg{width:14px;height:14px}
.menu-i.red .mi-icon{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.3);color:#ef4444}
.menu-i.red .mt-title{color:#ef4444}

/* Action button (Deposit/Penarikan) — FW66 style */
.btn-row{display:flex;gap:10px;margin-bottom:0}
.btn-act{flex:1;display:flex;align-items:center;justify-content:center;gap:10px;padding:13px 10px;border-radius:10px;font-size:.88rem;font-weight:700;text-decoration:none;position:relative;overflow:hidden;transition:transform .12s;letter-spacing:-.005em}
.btn-act:active{transform:scale(.97)}
.btn-wd{background:rgba(var(--pri-rgb),.05);border:1.5px solid rgba(var(--pri-rgb),.4);color:var(--t);transition:background .15s,transform .12s}
.btn-wd .ba-icon{color:var(--t2)}
.btn-dep{background:linear-gradient(135deg,var(--pri) 0%,var(--pri-d) 100%);background-size:200% auto;color:#fff;border:1.5px solid var(--pri);transition:background-position .25s ease,transform .12s}
.btn-dep:hover{background-position:right center;box-shadow:0 4px 12px rgba(var(--pri-rgb),.35)}
.btn-dep .ba-icon{color:#fff}
.btn-act svg{width:18px;height:18px}
.btn-act .ba-icon{width:28px;height:28px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
/* Nav */
.bnav{position:fixed;bottom:0;left:0;right:0;z-index:100;display:flex;background:var(--nav-bg,#0a1628);padding:8px 0 env(safe-area-inset-bottom,6px);overflow:hidden;border-radius:14px 14px 0 0;box-shadow:0 -4px 20px rgba(0,0,0,.5);border-top:1px solid rgba(var(--sec-rgb,56,189,248),.2)}
.bnav::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent 2%,var(--pri) 15%,var(--sec-l, var(--pri-l)) 50%,var(--pri) 85%,transparent 98%)}
.bnav-i{flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;padding:6px 0;color:var(--t3);font-size:.6rem;font-weight:600;text-decoration:none}
.bnav-i.active{color:var(--sec)}
.bnav-i img{width:36px;height:36px;object-fit:contain}
.toast-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9998}
.toast-box{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--s);border:1px solid var(--bd);border-radius:14px;padding:24px 30px;text-align:center;z-index:9999;max-width:280px;box-shadow:0 10px 40px rgba(0,0,0,.5)}
.toast-box p{font-size:.85rem;font-weight:600;color:var(--t);line-height:1.5;margin-bottom:16px}
.toast-box button{padding:8px 30px;background:var(--sec);border:none;border-radius:8px;color:#fff;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit}
@keyframes spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}
</style>
</head>
<body>

<?php if($user): ?>
<div class="prof-top">
<div class="prof-user">
<div class="prof-avatar"><img src="https://api.dicebear.com/7.x/avataaars/svg?seed=<?php echo $user['id']; ?>&backgroundColor=0c2f2e,134a3a,1e1040,1a0f08,2d1b69,0a0614&radius=50" alt="avatar"></div>
<div class="prof-info">
<?php $ph=$user['phone']??$user['username'];if(substr($ph,0,1)==='8')$ph='0'.$ph;if(strlen($ph)>6)$ph=substr($ph,0,4).str_repeat('*',strlen($ph)-6).substr($ph,-2); ?>
<div class="pi-name"><?php echo $ph; ?> <span class="pi-vip"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5zm14 3c0 .6-.4 1-1 1H6c-.6 0-1-.4-1-1v-1h14v1z"/></svg> VIP <?php echo $vl; ?></span></div>
<div class="pi-id">ID: <?php echo $user['display_id']??$user['id']; ?> <button onclick="navigator.clipboard.writeText('<?php echo $user["display_id"]??$user["id"]; ?>');showToast('ID disalin')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg></button></div>
</div>
</div>

<div class="bal-row">
<div class="bal-col">
<div class="bl-label">Saldo</div>
<div class="bl-val"><span id="balAnim">0.00</span>K <button class="bl-refresh" onclick="refreshBal()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 4v6h6M23 20v-6h-6"/><path d="M20.49 9A9 9 0 005.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 013.51 15"/></svg></button></div>
</div>
<div class="bal-col">
<div class="bl-label">Bonus hari ini</div>
<div class="bl-val"><?php $todayBonus=0;try{$tb=$db->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE user_id=? AND type IN('bonus','referral') AND DATE(created_at)=CURDATE()");$tb->execute([$uid]);$todayBonus=$tb->fetchColumn()/1000;}catch(Exception $e){}echo number_format($todayBonus,2); ?>K</div>
</div>
</div>

<div class="btn-row">
<a class="btn-act btn-dep" href="deposit.php"><div class="ba-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v14a2 2 0 01-2 2z"/><path d="M12 8v8m-4-4h8"/></svg></div>Deposit</a>
<a class="btn-act btn-wd" href="withdraw.php"><div class="ba-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="M2 10h20"/><path d="M6 16h4"/></svg></div>Penarikan</a>
</div>
</div>

<!-- VIP Card -->
<a class="vip-card" href="promo.php?tab=vip" style="display:block;text-decoration:none;color:inherit">
<div class="vc-top">
<div>
<div class="vc-badge"><div class="vbi"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5zm14 3c0 .6-.4 1-1 1H6c-.6 0-1-.4-1-1v-1h14v1z"/></svg></div><span class="vbt">VIP <?php echo $vl; ?></span></div>
<div class="vc-label">Level saat ini</div>
</div>
<div class="vc-detail">Detail VIP <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></div>
</div>
<div class="vc-prog">
<div class="vc-bar"><div class="fill" style="width:<?php echo round($pct); ?>%"></div></div>
<div class="vc-next"><div class="vni"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5z"/></svg></div>VIP <?php echo $nextLv; ?></div>
</div>
<div class="vc-info"><b>Ketentuan promosi</b><br>· Aliran perlu: <span><?php echo number_format($depK,2); ?></span> (<?php echo number_format($depK,2); ?>/<?php echo number_format($nextReq,2); ?>)</div>
</a>

<!-- Menu -->
<div class="menu">
<a class="menu-i" href="cs_chat.php">
<div class="mi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 01-.9 3.8 8.5 8.5 0 01-7.6 4.7 8.38 8.38 0 01-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 01-.9-3.8 8.5 8.5 0 014.7-7.6 8.38 8.38 0 013.8-.9h.5a8.48 8.48 0 018 8v.5z"/></svg></div>
<div class="mi-text"><div class="mt-title">Layanan Pelanggan</div><div class="mt-sub">Online 24/7 siap membantu</div></div>
<?php if($notifCount>0): ?><div class="mi-badge"><?php echo $notifCount; ?></div><?php endif; ?>
<div class="mi-arr"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></div>
</a>
</div>

<div class="menu">
<a class="menu-i" href="#" onclick="openRiwayat();return false">
<div class="mi-icon" ><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 20V10M12 20V4M6 20v-6"/></svg></div>
<div class="mi-text"><div class="mt-title">Riwayat</div><div class="mt-sub">Riwayat saldo, bonus, deposit</div></div>
<div class="mi-arr"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></div>
</a>
<a class="menu-i" href="afiliasi.php">
<div class="mi-icon" ><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg></div>
<div class="mi-text"><div class="mt-title">Undang</div><div class="mt-sub">Undang Teman Klaim Bonus & Komisi</div></div>
<div class="mi-arr"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></div>
</a>
<a class="menu-i" href="promo.php?tab=kode">
<div class="mi-icon" ><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 5l-2 2m-7.61 7.61a5.5 5.5 0 11-7.778 7.778 5.5 5.5 0 017.777-7.777z"/></svg></div>
<div class="mi-text"><div class="mt-title">Kode Penukaran</div></div>
<div class="mi-arr"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></div>
</a>
<a class="menu-i" href="keamanan.php">
<div class="mi-icon" ><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg></div>
<div class="mi-text"><div class="mt-title">Pusat keamanan</div></div>
<div class="mi-arr"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></div>
</a>
<a class="menu-i red" href="#" onclick="doLogout();return false">
<div class="mi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg></div>
<div class="mi-text"><div class="mt-title">Keluar</div></div>
<div class="mi-arr"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="9 18 15 12 9 6"/></svg></div>
</a>
</div>

<?php endif; ?>

<?php if(!$user): ?>
<!-- Guest empty state -->
<div style="padding:40px 18px 24px">
  <div style="text-align:center;margin-bottom:30px">
    <div style="width:96px;height:96px;margin:0 auto 18px;border-radius:50%;background:linear-gradient(135deg,var(--pri-l),rgba(var(--pri-rgb),.05));display:flex;align-items:center;justify-content:center">
      <svg viewBox="0 0 24 24" fill="none" stroke="var(--pri)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="44" height="44"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    </div>
    <h2 style="font-family:'Chakra Petch','Poppins',sans-serif;font-size:1.3rem;font-weight:800;color:var(--t);margin-bottom:6px;letter-spacing:-.01em">Belum Login</h2>
    <p style="font-size:.82rem;color:var(--t2);max-width:280px;margin:0 auto;line-height:1.5">Masuk dulu untuk lihat saldo, riwayat transaksi, &amp; profil VIP kamu.</p>
  </div>
  <div style="max-width:340px;margin:0 auto;display:flex;flex-direction:column;gap:10px">
    <button onclick="location.href='index.php?login=1'" style="padding:14px;border:none;border-radius:12px;background:linear-gradient(135deg,var(--pri),var(--pri-d));color:#fff;font-weight:800;font-size:.92rem;cursor:pointer;font-family:inherit;letter-spacing:.3px;box-shadow:0 6px 18px rgba(var(--pri-rgb),.32)">Masuk Sekarang</button>
    <button onclick="location.href='index.php?login=1&register=1'" style="padding:14px;border:1.5px solid var(--bd2);border-radius:12px;background:var(--bg2);color:var(--t);font-weight:700;font-size:.86rem;cursor:pointer;font-family:inherit">Daftar Akun Baru</button>
  </div>
  <div style="margin-top:36px;border-top:1px solid var(--bd);padding-top:24px">
    <div style="font-size:.7rem;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;padding:0 4px">Menu Lain</div>
    <a href="cs.php" style="display:flex;align-items:center;gap:14px;padding:14px;background:var(--bg2);border:1px solid var(--bd);border-radius:12px;color:var(--t);text-decoration:none;margin-bottom:8px">
      <div style="width:36px;height:36px;border-radius:10px;background:var(--pri-l);display:flex;align-items:center;justify-content:center;flex-shrink:0"><svg viewBox="0 0 24 24" fill="none" stroke="var(--pri)" stroke-width="2" width="18" height="18"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></div>
      <div style="flex:1"><div style="font-size:.84rem;font-weight:700">Live Chat</div><div style="font-size:.68rem;color:var(--t3);margin-top:2px">CS online 24 jam</div></div>
      <svg viewBox="0 0 24 24" fill="none" stroke="var(--t3)" stroke-width="2" width="16" height="16"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
    <a href="promo.php" style="display:flex;align-items:center;gap:14px;padding:14px;background:var(--bg2);border:1px solid var(--bd);border-radius:12px;color:var(--t);text-decoration:none;margin-bottom:8px">
      <div style="width:36px;height:36px;border-radius:10px;background:var(--pri-l);display:flex;align-items:center;justify-content:center;flex-shrink:0"><svg viewBox="0 0 24 24" fill="none" stroke="var(--pri)" stroke-width="2" width="18" height="18"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg></div>
      <div style="flex:1"><div style="font-size:.84rem;font-weight:700">Promosi</div><div style="font-size:.68rem;color:var(--t3);margin-top:2px">Lihat semua bonus &amp; promo</div></div>
      <svg viewBox="0 0 24 24" fill="none" stroke="var(--t3)" stroke-width="2" width="16" height="16"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
    <a href="games.php" style="display:flex;align-items:center;gap:14px;padding:14px;background:var(--bg2);border:1px solid var(--bd);border-radius:12px;color:var(--t);text-decoration:none">
      <div style="width:36px;height:36px;border-radius:10px;background:var(--pri-l);display:flex;align-items:center;justify-content:center;flex-shrink:0"><svg viewBox="0 0 24 24" fill="none" stroke="var(--pri)" stroke-width="2" width="18" height="18"><rect x="2" y="4" width="20" height="16" rx="3"/><circle cx="8" cy="12" r="2"/><circle cx="16" cy="12" r="2"/></svg></div>
      <div style="flex:1"><div style="font-size:.84rem;font-weight:700">Permainan</div><div style="font-size:.68rem;color:var(--t3);margin-top:2px">Browse semua game tersedia</div></div>
      <svg viewBox="0 0 24 24" fill="none" stroke="var(--t3)" stroke-width="2" width="16" height="16"><polyline points="9 18 15 12 9 6"/></svg>
    </a>
  </div>
</div>
<?php endif; ?>

<!-- Riwayat Overlay -->
<div id="riwayatOv" style="position:fixed;inset:0;background:var(--bg);z-index:200;display:none;flex-direction:column">
<div style="display:flex;align-items:center;padding:16px;border-bottom:1px solid var(--bd)"><button onclick="closeRiwayat()" style="width:32px;height:32px;background:none;border:none;color:var(--t2);cursor:pointer;display:flex;align-items:center;justify-content:center"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="22" height="22"><polyline points="15 18 9 12 15 6"/></svg></button><h2 style="flex:1;text-align:center;font-size:1rem;font-weight:700">Riwayat</h2><div style="width:32px"></div></div>
<div style="display:flex;gap:0;padding:8px 12px;overflow-x:auto;scrollbar-width:none;flex-shrink:0" id="rwTabs"></div>
<div style="flex:1;overflow-y:auto;padding:0 16px 16px" id="rwBody"><div style="text-align:center;padding:40px;color:var(--t3)">Memuat...</div></div>
</div>

<?php echo renderBnav($db,"profil",$isLoggedIn); ?>

<script>
function doLogout(){
  fetch('api/auth.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'logout'})})
  .then(function(){localStorage.removeItem('app_user');location.href='index.php'})
  .catch(function(){localStorage.removeItem('app_user');location.href='index.php'});
}

// Count-up animation on load
var targetBal=<?php echo json_encode($bal); ?>;
function countUp(el,target,dur){
  var start=0;var st=null;
  function step(ts){
    if(!st)st=ts;var p=Math.min((ts-st)/dur,1);
    el.textContent=(p*target).toFixed(2);
    if(p<1)requestAnimationFrame(step);
  }
  requestAnimationFrame(step);
}
var balEl=document.getElementById('balAnim');
if(balEl)countUp(balEl,targetBal,600);

// Refresh balance
function refreshBal(){
  var btn=document.querySelector('.bl-refresh');
  if(btn)btn.style.animation='spin .6s linear infinite';
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'get_balance'})})
  .then(function(r){return r.json()}).then(function(d){
    if(btn)btn.style.animation='';
    if(d.ok){
      var balK=(d.balance||0)/1000;
      var el=document.getElementById('balAnim');
      if(el)countUp(el,balK,400);
    }
  }).catch(function(){if(btn)btn.style.animation='';});
}

// Riwayat
var rwFilter='all';
var rwData=[];
function openRiwayat(){
  document.getElementById('riwayatOv').style.display='flex';
  var tabs=[['all','Semua'],['deposit','Deposit'],['withdraw','Tarik'],['bonus','Bonus'],['referral','Referral'],['undangan','Hadiah Undangan']];
  var th='';tabs.forEach(function(t){
    var act=rwFilter===t[0];
    th+='<div style="padding:9px 16px;font-size:.74rem;font-weight:700;border-radius:8px;cursor:pointer;white-space:nowrap;flex-shrink:0;margin-right:6px;'+(act?'background:var(--pri);color:#fff;border:1px solid var(--pri)':'background:var(--bg2);color:var(--t2);border:1px solid var(--bd)')+'" onclick="filterRw(\''+t[0]+'\')">'+t[1]+'</div>';
  });
  document.getElementById('rwTabs').innerHTML=th;
  loadRiwayat();
}
function closeRiwayat(){document.getElementById('riwayatOv').style.display='none'}
function filterRw(f){rwFilter=f;openRiwayat()}
function loadRiwayat(){
  document.getElementById('rwBody').innerHTML='<div style="text-align:center;padding:40px;color:var(--t3)">Memuat...</div>';
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'history',type:rwFilter})})
  .then(function(r){return r.json()}).then(function(d){
    if(!d.ok){document.getElementById('rwBody').innerHTML='<div style="text-align:center;padding:40px;color:var(--t3)">Gagal memuat</div>';return}
    rwData=d.transactions||[];

    var headerHtml=''; // no header card — clean, langsung ke list transaksi

    if(!rwData.length){
      document.getElementById('rwBody').innerHTML=headerHtml+'<div style="text-align:center;padding:40px 20px"><svg viewBox="0 0 24 24" fill="none" stroke="var(--t3)" stroke-width="1.5" width="48" height="48" opacity=".3"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/></svg><p style="color:var(--t3);font-size:.82rem;margin-top:14px">Belum ada riwayat pada kategori ini</p></div>';return;
    }
    // SVG icon templates
    function svgIco(d){return '<svg viewBox="0 0 24 24" fill="none" stroke="var(--t2)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">'+d+'</svg>'}
    var TC={
      deposit:      {ico:svgIco('<path d="M12 2v14m-5-5l5 5 5-5"/><path d="M5 20h14"/>'), lbl:'Deposit'},
      bonus:        {ico:svgIco('<path d="M20 12v6a2 2 0 01-2 2H6a2 2 0 01-2-2v-6"/><path d="M2 8h20v4H2z"/><path d="M12 20V8"/>'), lbl:'Bonus'},
      referral:     {ico:svgIco('<path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/>'), lbl:'Referral'},
      withdraw:     {ico:svgIco('<path d="M12 16V2m5 5l-5-5-5 5"/><path d="M5 20h14"/>'), lbl:'Penarikan'},
      game_transfer:{ico:svgIco('<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>'), lbl:'Ke Game'},
      game_win:     {ico:svgIco('<rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>'), lbl:'Dari Game'},
      game_refund:  {ico:svgIco('<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 105.64-11.36L1 10"/>'), lbl:'Refund'},
      rebate:       {ico:svgIco('<line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/>'), lbl:'Rebate'},
      refund:       {ico:svgIco('<polyline points="1 4 1 10 7 10"/><path d="M3.51 15a9 9 0 105.64-11.36L1 10"/>'), lbl:'Refund'},
      inject:       {ico:svgIco('<circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/>'), lbl:'Admin'},
      spin_win:     {ico:svgIco('<circle cx="12" cy="12" r="10"/><path d="M12 2v20M2 12h20"/>'), lbl:'Spin'},
    };
    // Status badge colors
    var STATUS={
      ok:       {c:'#22c55e',lbl:'Sukses'},
      pending:  {c:'#f59e0b',lbl:'Pending'},
      expired:  {c:'var(--t3)',lbl:'Expired'},
      failed:   {c:'#ef4444',lbl:'Gagal'},
      cancelled:{c:'var(--t3)',lbl:'Dibatal'},
      rejected: {c:'#ef4444',lbl:'Tolak'},
    };
    var h='';
    rwData.forEach(function(t,idx){
      var isNonFinal=(t.status&&t.status!=='ok');
      var isPlus=t.amount>0;
      var amt=Math.abs(t.amount);
      var cfg=TC[t.type]||{ico:svgIco('<circle cx="12" cy="12" r="10"/>'),lbl:t.type};
      var note=t.note||cfg.lbl;
      var rawNote=note; // simpan full note buat detail
      // Format note khusus per tipe
      var subline=''; // baris 2 (di bawah judul) — info extra
      if(t.type==='game_transfer'){
        // "Transfer ke game PRAGMATIC/vs20doghouse" → "Masuk ke game: Sweet Bonanza"
        var gm=note.match(/Transfer ke game\s+([^\/]+)\/(.+)/);
        if(gm){
          var providerName=gm[1].trim();
          var gameName=gm[2].trim();
          note='Saldo masuk ke game';
          subline=gameName+' ('+providerName+')';
        }else{
          note='Saldo masuk ke game';
        }
      }else if(t.type==='game_win'){
        // "Saldo kembali dari game" atau "Saldo kembali dari game (pre-launch)"
        note='Saldo ditarik dari game';
        if(rawNote.indexOf('pre-launch')>-1)subline='Pre-launch';
        else if(rawNote.indexOf('via status')>-1)subline='Via status check';
      }else if(t.type==='game_refund'){
        note='Refund game';
        subline=rawNote.replace(/^Refund:?\s*/i,'');
      }else if(t.type==='deposit'){
        note='Deposit '+(t.method?t.method.toUpperCase():'').trim();
        if(t.status&&t.status!=='ok'){
          var sb={pending:'Menunggu pembayaran',expired:'Kedaluwarsa',failed:'Ditolak/Gagal',cancelled:'Dibatalkan'}[t.status];
          if(sb)subline=sb;
        }
      }else if(t.type==='withdraw'){
        note='Penarikan';
        if(t.status==='pending')subline='Menunggu diproses';
        else if(t.status==='rejected')subline='Ditolak (saldo dikembalikan)';
      }else if(t.type==='bonus'){
        if(rawNote.indexOf('Hadiah Undangan')>-1){note='Hadiah Undangan';subline=rawNote.replace(/^.*Hadiah Undangan[^A-Za-z]*/i,'').trim()||null;}
        else if(rawNote.indexOf('Bonus deposit')>-1){note='Bonus Deposit';}
        else{note='Bonus';subline=rawNote.length>50?rawNote.substring(0,48)+'..':rawNote;}
      }else if(t.type==='referral'){
        note='Bonus Referral';
        subline='Dari deposit pertama downline';
      }else if(t.type==='rebate'){
        note='Rebate Mingguan';
      }else if(t.type==='inject'){
        note='Dari Admin';
        subline=rawNote.length>60?rawNote.substring(0,58)+'..':rawNote;
      }else if(t.type==='spin_win'){
        note='Hadiah Spin';
      }
      var dt=t.created_at?t.created_at.replace('T',' ').substring(0,16):'';
      var st=STATUS[t.status||'ok']||STATUS.ok;

      // Render row — tap untuk expand/detail
      h+='<div onclick="rwExpand('+idx+')" style="display:flex;align-items:flex-start;gap:12px;padding:13px 0;border-bottom:1px solid var(--tint-1);cursor:pointer;'+(isNonFinal?'opacity:.75':'')+'">';
      h+='<div style="width:40px;height:40px;border-radius:10px;background:var(--tint-1);display:flex;align-items:center;justify-content:center;flex-shrink:0;position:relative;margin-top:2px">'+cfg.ico;
      if(isNonFinal)h+='<span style="position:absolute;bottom:-3px;right:-3px;width:14px;height:14px;border-radius:50%;background:'+st.c+';border:2px solid var(--bg);display:flex;align-items:center;justify-content:center;font-size:8px;color:#fff">!</span>';
      h+='</div>';
      h+='<div style="flex:1;min-width:0">';
      h+='<div style="font-size:.78rem;font-weight:600;color:var(--t)">'+note+'</div>';
      if(subline){
        h+='<div style="font-size:.68rem;color:var(--t2);margin-top:2px;word-break:break-word">'+subline+'</div>';
      }
      h+='<div style="font-size:.58rem;color:var(--t3);margin-top:3px">'+cfg.lbl+' · '+dt;
      if(isNonFinal)h+=' <span style="color:'+st.c+';font-weight:700">• '+st.lbl+'</span>';
      h+='</div>';
      h+='</div>';
      // Amount
      if(isNonFinal){
        h+='<div style="font-size:.72rem;font-weight:700;color:'+st.c+';flex-shrink:0;text-align:right;margin-top:4px">'+(amt/1000).toFixed(0)+'K<div style="font-size:.55rem;font-weight:600;margin-top:1px">'+st.lbl+'</div></div>';
      }else{
        h+='<div style="font-size:.82rem;font-weight:800;color:'+(isPlus?'var(--t)':'var(--t3)')+';flex-shrink:0;margin-top:4px">'+(isPlus?'+':'-')+(amt/1000).toFixed(0)+'K</div>';
      }
      h+='</div>';
    });
    document.getElementById('rwBody').innerHTML=headerHtml+h;
  }).catch(function(){document.getElementById('rwBody').innerHTML='<div style="text-align:center;padding:40px 20px;color:var(--t3)"><div style="font-size:.85rem;font-weight:700;color:#ef4444;margin-bottom:6px">Gagal memuat riwayat</div><div style="font-size:.7rem">Cek koneksi internet kamu, lalu coba lagi.</div><button onclick="loadRiwayat()" style="margin-top:14px;padding:8px 20px;background:var(--pri);color:#fff;border:none;border-radius:8px;font-size:.74rem;font-weight:700;cursor:pointer">Coba Lagi</button></div>'});
}

// Popup detail transaksi — tampilin SEMUA info raw dari DB
function rwExpand(idx){
  var t=rwData[idx];if(!t)return;
  var isPlus=t.amount>0;var amt=Math.abs(t.amount);
  var isNonFinal=(t.status&&t.status!=='ok');
  var STATUS={ok:{c:'#22c55e',lbl:'Sukses'},pending:{c:'#f59e0b',lbl:'Pending'},expired:{c:'var(--t3)',lbl:'Expired'},failed:{c:'#ef4444',lbl:'Gagal'},cancelled:{c:'var(--t3)',lbl:'Dibatal'},rejected:{c:'#ef4444',lbl:'Ditolak'}};
  var st=STATUS[t.status||'ok']||STATUS.ok;
  // Type label
  var typeLbl={deposit:'Deposit',withdraw:'Penarikan',bonus:'Bonus',referral:'Referral',rebate:'Rebate',inject:'Admin',spin_win:'Spin',game_transfer:'Transfer ke Game',game_win:'Tarik dari Game',game_refund:'Refund Game'}[t.type]||t.type;

  var h='<div style="padding:4px 0">';
  // Header
  h+='<div style="text-align:center;padding:14px 0 18px">';
  h+='<div style="font-size:.7rem;color:var(--t3);margin-bottom:6px">'+typeLbl+'</div>';
  h+='<div style="font-size:1.8rem;font-weight:800;color:'+(isNonFinal?st.c:(isPlus?'#22c55e':'var(--t)'))+'">'+(isPlus?'+':'-')+'Rp '+Number(amt).toLocaleString('id')+'</div>';
  h+='<div style="display:inline-block;padding:4px 12px;background:'+st.c+'22;color:'+st.c+';border-radius:20px;font-size:.68rem;font-weight:700;margin-top:8px">'+st.lbl.toUpperCase()+'</div>';
  h+='</div>';

  // Detail fields
  h+='<div style="background:var(--tint-1);border-radius:10px;padding:14px;font-size:.75rem;line-height:1.9">';
  h+='<div style="display:flex;justify-content:space-between;gap:10px"><span style="color:var(--t3)">Waktu</span><span style="color:var(--t);font-weight:600">'+(t.created_at?t.created_at.replace('T',' ').substring(0,19):'-')+'</span></div>';
  h+='<div style="display:flex;justify-content:space-between;gap:10px"><span style="color:var(--t3)">Tipe</span><span style="color:var(--t);font-weight:600">'+typeLbl+'</span></div>';

  // Tipe-specific details
  if(t.type==='game_transfer'){
    var gm=(t.note||'').match(/Transfer ke game\s+([^\/]+)\/(.+)/);
    if(gm){
      h+='<div style="display:flex;justify-content:space-between;gap:10px"><span style="color:var(--t3)">Provider</span><span style="color:var(--t);font-weight:600">'+gm[1].trim()+'</span></div>';
      h+='<div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start"><span style="color:var(--t3);flex-shrink:0">Game</span><span style="color:var(--t);font-weight:600;text-align:right;word-break:break-word">'+gm[2].trim()+'</span></div>';
    }
    h+='<div style="display:flex;justify-content:space-between;gap:10px"><span style="color:var(--t3)">Arah</span><span style="color:var(--t);font-weight:600">Saldo masuk ke provider game</span></div>';
  }else if(t.type==='game_win'){
    h+='<div style="display:flex;justify-content:space-between;gap:10px"><span style="color:var(--t3)">Arah</span><span style="color:var(--t);font-weight:600">Saldo ditarik kembali ke akun</span></div>';
  }else if(t.type==='game_refund'){
    h+='<div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start"><span style="color:var(--t3);flex-shrink:0">Alasan</span><span style="color:var(--t);font-weight:600;text-align:right;word-break:break-word">'+(t.note||'-').replace(/^Refund:?\s*/i,'')+'</span></div>';
  }else if(t.type==='deposit'){
    h+='<div style="display:flex;justify-content:space-between;gap:10px"><span style="color:var(--t3)">Metode</span><span style="color:var(--t);font-weight:600">'+((t.method||'QRIS')+'').toUpperCase()+'</span></div>';
    if(t.ref_id)h+='<div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start"><span style="color:var(--t3)">ID Transaksi</span><span style="color:var(--t);font-weight:600;font-family:monospace;font-size:.65rem;text-align:right;word-break:break-all">'+t.ref_id+'</span></div>';
  }else if(t.type==='withdraw'){
    if(t.status==='rejected')h+='<div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start"><span style="color:var(--t3);flex-shrink:0">Alasan Tolak</span><span style="color:var(--t);font-weight:600;text-align:right;word-break:break-word">'+(t.note||'-')+'</span></div>';
    else if(t.status==='ok')h+='<div style="display:flex;justify-content:space-between;gap:10px"><span style="color:var(--t3)">Status</span><span style="color:'+st.c+';font-weight:700">Sudah diproses</span></div>';
  }else{
    // Bonus/referral/rebate/inject — tampilin catatan
    if(t.note)h+='<div style="display:flex;justify-content:space-between;gap:10px;align-items:flex-start"><span style="color:var(--t3);flex-shrink:0">Catatan</span><span style="color:var(--t);font-weight:600;text-align:right;word-break:break-word">'+t.note+'</span></div>';
  }

  // Balance info (kalau ada)
  if(t.balance_after!==null&&t.balance_after!==undefined){
    h+='<div style="display:flex;justify-content:space-between;gap:10px"><span style="color:var(--t3)">Saldo Setelah</span><span style="color:var(--t);font-weight:600">Rp '+Number(t.balance_after).toLocaleString('id')+'</span></div>';
  }
  h+='</div>';

  // Info tambahan untuk non-final
  if(isNonFinal){
    var infoMsg={
      pending:'Transaksi ini masih dalam proses. Silakan tunggu beberapa saat.',
      expired:'Transaksi ini sudah melewati batas waktu. Saldo tidak masuk. Silakan buat deposit baru.',
      failed:'Transaksi ditolak oleh sistem. Saldo tidak berubah.',
      cancelled:'Transaksi dibatalkan. Saldo tidak berubah.',
      rejected:'Penarikan ditolak. Saldo sudah otomatis dikembalikan ke akun.'
    }[t.status];
    if(infoMsg){
      h+='<div style="margin-top:12px;padding:10px 12px;background:'+st.c+'15;border:1px solid '+st.c+'44;border-radius:8px;font-size:.7rem;color:var(--t2);line-height:1.5">'+infoMsg+'</div>';
    }
  }
  h+='</div>';

  // Tombol Hubungi CS (di bawah detail) — prefill context transaksi
  var typeLbl2={deposit:'Deposit',withdraw:'Penarikan',bonus:'Bonus',referral:'Referral',rebate:'Rebate',inject:'Admin',spin_win:'Spin',game_transfer:'Transfer ke Game',game_win:'Tarik dari Game',game_refund:'Refund Game'}[t.type]||t.type;
  // Build prefill message
  var prefillMsg='Halo, saya mau tanya terkait transaksi berikut:\n\n';
  prefillMsg+='Jenis: '+typeLbl2+'\n';
  prefillMsg+='Waktu: '+(t.created_at?t.created_at.replace('T',' ').substring(0,19):'-')+'\n';
  prefillMsg+='Nominal: Rp '+Number(amt).toLocaleString('id')+'\n';
  prefillMsg+='Status: '+st.lbl+'\n';
  if(t.ref_id)prefillMsg+='ID Transaksi: '+t.ref_id+'\n';
  if(t.type==='game_transfer'){
    var gm2=(t.note||'').match(/Transfer ke game\s+([^\/]+)\/(.+)/);
    if(gm2){prefillMsg+='Provider: '+gm2[1].trim()+'\n';prefillMsg+='Game: '+gm2[2].trim()+'\n';}
  }
  if(t.note&&t.note.length<200)prefillMsg+='Catatan: '+t.note+'\n';
  prefillMsg+='\nPertanyaan saya: ';

  h+='<div style="margin-top:14px;display:flex;gap:8px">';
  h+='<button onclick="this.closest(\'div[style*=fixed]\').remove()" style="flex:1;padding:12px;background:var(--tint-2);border:1px solid var(--tint-2);border-radius:10px;color:var(--t2);font-weight:700;cursor:pointer;font-family:inherit;font-size:.8rem">Tutup</button>';
  h+='<button onclick="rwContactCS('+JSON.stringify(prefillMsg).replace(/"/g,'&quot;')+');this.closest(\'div[style*=fixed]\').remove()" style="flex:1;padding:12px;background:linear-gradient(135deg,var(--sec,var(--sec)),var(--sec-d,var(--sec-d)));border:none;border-radius:10px;color:#fff;font-weight:800;cursor:pointer;font-family:inherit;font-size:.8rem;display:flex;align-items:center;justify-content:center;gap:6px">';
  h+='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="15" height="15"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>';
  h+='Hubungi CS</button>';
  h+='</div>';

  // Show popup
  var ov=document.createElement('div');
  ov.style.cssText='position:fixed;inset:0;background:rgba(0,0,0,.75);z-index:9999;display:flex;align-items:flex-end;justify-content:center;padding:0';
  ov.innerHTML='<div style="background:var(--bg);border-top:1px solid var(--tint-2);border-radius:20px 20px 0 0;padding:12px 20px 30px;max-width:480px;width:100%;max-height:85vh;overflow-y:auto"><div style="width:40px;height:4px;background:var(--tint-3);border-radius:2px;margin:0 auto 8px"></div><div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px"><h3 style="font-size:1rem;font-weight:700;color:var(--t)">Detail Transaksi</h3><button onclick="this.closest(\'div[style*=fixed]\').remove()" style="width:30px;height:30px;background:var(--tint-2);border:none;border-radius:8px;color:var(--t2);cursor:pointer;display:flex;align-items:center;justify-content:center"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>'+h+'</div>';
  ov.onclick=function(e){if(e.target===ov)ov.remove()};
  document.body.appendChild(ov);
}

// Redirect ke cs_chat dengan prefill pesan via sessionStorage (biar cs_chat auto-isi input)
function rwContactCS(prefillMsg){
  try{sessionStorage.setItem('cs_prefill',prefillMsg);}catch(e){}
  location.href='cs_chat.php';
}
function showToast(m){var ov=document.createElement('div');ov.className='toast-overlay';var bx=document.createElement('div');bx.className='toast-box';var p=document.createElement('p');p.textContent=m;var btn=document.createElement('button');btn.textContent='Oke';btn.onclick=function(){bx.remove();ov.remove()};bx.appendChild(p);bx.appendChild(btn);document.body.appendChild(ov);document.body.appendChild(bx);ov.onclick=function(){bx.remove();ov.remove()}}
</script>
<?php include 'includes/credit_notify.php'; ?>
</body>
</html>
