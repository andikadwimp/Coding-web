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
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
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
.btn-klaim{padding:10px 24px;background:var(--sec);border:none;border-radius:8px;color:#fff;font-size:.78rem;font-weight:700;cursor:pointer;font-family:inherit}
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
<style>
.tut-card{position:relative;overflow:hidden}
.tut-card .tc-illust{position:absolute;right:-10px;top:-10px;width:120px;height:120px;opacity:.13;pointer-events:none;color:var(--pri)}
.tut-head{display:flex;align-items:center;gap:10px;margin-bottom:10px;position:relative;z-index:1}
.tut-head .tc-num{width:28px;height:28px;border-radius:50%;background:linear-gradient(135deg,var(--pri),var(--pri-d));color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.85rem;box-shadow:0 2px 8px rgba(var(--pri-rgb,56,189,248),.35)}
.tut-card h4{font-size:.95rem;font-weight:800;letter-spacing:-.015em;margin:0;flex:1}
.tut-tier-row{display:grid;grid-template-columns:1fr auto;gap:10px;align-items:center;padding:9px 12px;background:var(--bg2);border:1px solid var(--bd);border-radius:10px;margin-top:8px;transition:transform .2s cubic-bezier(.16,1,.3,1),border-color .15s}
.tut-tier-row:hover{transform:translateX(2px);border-color:rgba(var(--pri-rgb,56,189,248),.4)}
.tut-tier-row .ttr-label{font-size:.74rem;color:var(--t2);font-weight:600}
.tut-tier-row .ttr-pct{font-family:'Chakra Petch','Poppins',sans-serif;font-weight:800;font-size:.95rem;color:var(--pri);font-variant-numeric:tabular-nums}
.tut-flow{display:flex;flex-direction:column;gap:8px;margin-top:6px;position:relative;z-index:1}
.tut-flow-row{display:grid;grid-template-columns:auto 1fr auto;gap:10px;align-items:center;padding:8px 12px;background:var(--bg2);border:1px solid var(--bd);border-radius:9px;font-size:.76rem}
.tut-flow-row .ttf-tag{font-family:'Chakra Petch',sans-serif;font-weight:800;color:var(--pri);background:rgba(var(--pri-rgb,56,189,248),.12);padding:3px 8px;border-radius:6px;font-size:.7rem;letter-spacing:.3px}
.tut-flow-row .ttf-mid{color:var(--t2);font-weight:500}
.tut-flow-row .ttf-val{font-family:'Chakra Petch','Poppins',sans-serif;font-weight:700;font-variant-numeric:tabular-nums;color:var(--t)}
.tut-total{margin-top:12px;padding:11px 14px;border-radius:10px;background:linear-gradient(135deg,rgba(var(--pri-rgb,56,189,248),.18),rgba(var(--pri-rgb,56,189,248),.06));border:1.5px solid rgba(var(--pri-rgb,56,189,248),.4);display:flex;align-items:center;justify-content:space-between;font-size:.82rem}
.tut-total b{color:var(--t);font-weight:800}
.tut-total .tt-amt{font-family:'Chakra Petch','Poppins',sans-serif;font-weight:900;color:var(--pri);font-size:1.1rem;font-variant-numeric:tabular-nums;text-shadow:0 0 12px rgba(var(--pri-rgb,56,189,248),.4)}
.tut-summary-grid{display:grid;grid-template-columns:1fr;gap:10px;margin-top:8px;position:relative;z-index:1}
.tut-summary-item{display:flex;gap:12px;padding:11px;background:var(--bg2);border:1px solid var(--bd);border-radius:10px;align-items:flex-start}
.tut-summary-item .tsi-icon{width:34px;height:34px;border-radius:9px;background:rgba(var(--pri-rgb,56,189,248),.12);border:1px solid rgba(var(--pri-rgb,56,189,248),.25);display:flex;align-items:center;justify-content:center;color:var(--pri);flex-shrink:0}
.tut-summary-item .tsi-icon svg{width:17px;height:17px}
.tut-summary-item .tsi-text{flex:1;line-height:1.5}
.tut-summary-item .tsi-text b{display:block;font-size:.82rem;font-weight:800;letter-spacing:-.005em;color:var(--t);margin-bottom:2px}
.tut-summary-item .tsi-text span{font-size:.72rem;color:var(--t2);font-weight:500}
.tut-note{margin-top:12px;padding:10px 12px;background:rgba(74,222,128,.08);border:1px solid rgba(74,222,128,.22);border-radius:9px;font-size:.7rem;color:#86efac;line-height:1.55;display:flex;gap:8px;align-items:flex-start}
.tut-note svg{width:14px;height:14px;color:#4ade80;flex-shrink:0;margin-top:2px}
</style>

<div class="panel" id="p1">

<!-- ═══ TUTOR HERO: Top stats card + 2-level downline tree ═══ -->
<style>
/* TOP CARD: avatar A + stats list */
.aff-top{background:var(--s);border:1px solid var(--bd);border-radius:14px;padding:16px;margin-bottom:14px;display:flex;gap:14px;align-items:flex-start;animation:secFadeIn .4s cubic-bezier(.16,1,.3,1) both}
.aff-top .at-av{width:74px;height:74px;border-radius:50%;background:linear-gradient(135deg,var(--bg2),var(--s2));border:2px solid var(--bd);display:flex;align-items:center;justify-content:center;flex-shrink:0;position:relative;overflow:hidden}
.aff-top .at-av img{width:100%;height:100%;object-fit:cover;display:block}
.aff-top .at-av svg{width:46px;height:46px;color:var(--t2)}
.aff-top .at-av-badge{position:absolute;bottom:-2px;right:-2px;width:24px;height:24px;border-radius:50%;background:linear-gradient(135deg,#fbbf24,#f59e0b);border:2px solid var(--s);color:#fff;display:flex;align-items:center;justify-content:center;font-family:'Chakra Petch',sans-serif;font-weight:800;font-size:.78rem;letter-spacing:-.5px;box-shadow:0 2px 6px rgba(0,0,0,.2)}
.aff-top .at-stats{flex:1;min-width:0;display:flex;flex-direction:column;gap:5px;font-size:.74rem}
.aff-top .at-row{display:flex;justify-content:space-between;align-items:center;gap:8px;line-height:1.3}
.aff-top .at-row span:first-child{color:var(--t2);font-weight:500}
.aff-top .at-row span:last-child{font-family:'Chakra Petch',sans-serif;font-weight:800;color:#fb923c;font-variant-numeric:tabular-nums;letter-spacing:.3px}
.aff-top .at-row.dim span:last-child{color:var(--t)}

/* DOWNLINE TREE: 3 columns × 2 levels */
.aff-tree{position:relative;margin-bottom:14px;animation:secFadeIn .4s cubic-bezier(.16,1,.3,1) .05s both}
.aff-tree-row{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;position:relative;z-index:2}
.aff-node{background:var(--s);border:1.5px solid var(--bd);border-radius:11px;padding:9px 6px 10px;text-align:center;position:relative;transition:transform .2s cubic-bezier(.16,1,.3,1),border-color .15s}
.aff-node:hover{transform:translateY(-2px);border-color:rgba(var(--pri-rgb),.4)}
.aff-node.lvl-b{border-color:rgba(74,222,128,.35)}
.aff-node.lvl-c{border-color:rgba(var(--pri-rgb),.32)}
.aff-node .an-top{font-size:.62rem;color:var(--t2);font-weight:600;margin-bottom:5px;line-height:1.25}
.aff-node .an-top b{display:block;color:var(--t);font-family:'Chakra Petch',sans-serif;font-weight:800;font-size:.66rem;margin-bottom:1px}
.aff-node .an-top .an-amt{font-family:'Chakra Petch',sans-serif;font-weight:800;color:#fb923c;font-variant-numeric:tabular-nums;font-size:.78rem}
.aff-node .an-av{width:48px;height:48px;border-radius:50%;background:linear-gradient(135deg,var(--bg2),var(--s2));border:1.5px solid var(--bd);display:flex;align-items:center;justify-content:center;margin:6px auto 6px;position:relative;overflow:hidden}
.aff-node .an-av img{width:100%;height:100%;object-fit:cover;display:block}
.aff-node .an-av svg{width:26px;height:26px;color:var(--t2)}
.aff-node .an-av-badge{position:absolute;bottom:-2px;right:-2px;width:18px;height:18px;border-radius:50%;border:2px solid var(--s);color:#fff;display:flex;align-items:center;justify-content:center;font-family:'Chakra Petch',sans-serif;font-weight:800;font-size:.6rem}
.aff-node.lvl-b .an-av-badge{background:linear-gradient(135deg,#4ade80,#22c55e)}
.aff-node.lvl-c .an-av-badge{background:linear-gradient(135deg,var(--pri),var(--pri-d))}
.aff-node .an-bet{background:var(--bg2);border:1px solid var(--bd);border-radius:7px;padding:5px 4px;font-size:.6rem;color:var(--t2);font-weight:600;line-height:1.3}
.aff-node .an-bet b{display:block;font-family:'Chakra Petch',sans-serif;font-weight:800;color:var(--t);font-variant-numeric:tabular-nums;font-size:.78rem;margin-top:1px}

/* ARROW STRIPS between rows */
.aff-arrows{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin:10px 0 10px;position:relative;z-index:1}
.aff-arrow{padding:6px 6px;border-radius:8px;font-size:.55rem;color:var(--t2);font-weight:600;line-height:1.35;text-align:center;background:rgba(var(--pri-rgb),.06);border:1px dashed rgba(var(--pri-rgb),.3);position:relative}
.aff-arrow b{font-family:'Chakra Petch',sans-serif;font-weight:800;color:#fb923c;font-variant-numeric:tabular-nums;font-size:.72rem}
.aff-arrow::before{content:'';position:absolute;top:-7px;left:50%;transform:translateX(-50%);border-left:5px solid transparent;border-right:5px solid transparent;border-bottom:6px solid rgba(var(--pri-rgb),.4)}
.aff-arrow.green{background:rgba(74,222,128,.06);border-color:rgba(74,222,128,.3)}
.aff-arrow.green::before{border-bottom-color:rgba(74,222,128,.4)}
.aff-arrow.dim{background:rgba(148,163,184,.06);border-color:rgba(148,163,184,.25)}
.aff-arrow.dim b{color:var(--t3)}
.aff-arrow.dim::before{border-bottom-color:rgba(148,163,184,.35)}

/* Note card */
.aff-note{margin-top:10px;padding:10px 12px;background:rgba(74,222,128,.08);border:1px solid rgba(74,222,128,.22);border-radius:9px;font-size:.7rem;color:#86efac;line-height:1.5;display:flex;gap:8px;align-items:flex-start}
.aff-note svg{width:14px;height:14px;color:#4ade80;flex-shrink:0;margin-top:2px}

/* Tier list */
.aff-tier-card{background:var(--s);border:1px solid var(--bd);border-radius:14px;padding:14px;margin-bottom:14px}
.aff-tier-head{font-size:.85rem;font-weight:800;letter-spacing:-.015em;margin-bottom:10px;display:flex;align-items:center;gap:7px}
.aff-tier-head svg{width:16px;height:16px;color:var(--pri)}
.aff-tier{display:grid;grid-template-columns:1fr auto;gap:10px;padding:9px 12px;background:var(--bg2);border:1px solid var(--bd);border-radius:9px;margin-bottom:6px;transition:transform .2s cubic-bezier(.16,1,.3,1),border-color .15s}
.aff-tier:last-child{margin-bottom:0}
.aff-tier:hover{transform:translateX(2px);border-color:rgba(var(--pri-rgb),.4)}
.aff-tier-label{font-size:.74rem;color:var(--t2);font-weight:600}
.aff-tier-pct{font-family:'Chakra Petch',sans-serif;font-weight:800;font-size:.92rem;color:var(--pri);font-variant-numeric:tabular-nums;letter-spacing:-.5px}
.aff-tier-pct .tp-bg{padding:3px 11px;background:rgba(var(--pri-rgb),.14);border-radius:7px;border:1px solid rgba(var(--pri-rgb),.3)}

/* Summary list */
.aff-sum{background:var(--s);border:1px solid var(--bd);border-radius:14px;padding:14px;margin-bottom:14px}
.aff-sum-row{display:flex;gap:11px;padding:10px;background:var(--bg2);border:1px solid var(--bd);border-radius:10px;margin-bottom:6px;align-items:flex-start;transition:transform .2s cubic-bezier(.16,1,.3,1),border-color .15s}
.aff-sum-row:last-child{margin-bottom:0}
.aff-sum-row:hover{transform:translateX(2px);border-color:rgba(var(--pri-rgb),.4)}
.aff-sum-icon{width:32px;height:32px;border-radius:9px;background:rgba(var(--pri-rgb),.12);border:1px solid rgba(var(--pri-rgb),.25);display:flex;align-items:center;justify-content:center;color:var(--pri);flex-shrink:0}
.aff-sum-icon svg{width:16px;height:16px}
.aff-sum-text{flex:1;line-height:1.45}
.aff-sum-text b{display:block;font-size:.8rem;font-weight:800;letter-spacing:-.005em;color:var(--t);margin-bottom:2px}
.aff-sum-text span{font-size:.7rem;color:var(--t2);font-weight:500}

@media(max-width:380px){
  .aff-top{padding:12px;gap:10px}
  .aff-top .at-av{width:60px;height:60px}
  .aff-top .at-av svg{width:36px;height:36px}
  .aff-top .at-stats{font-size:.68rem}
  .aff-node .an-top{font-size:.55rem}
  .aff-arrow{font-size:.5rem;padding:5px 4px}
}
</style>

<!-- ═══ HEADER: Avatar A + Performance breakdown ═══ -->
<div class="aff-top">
  <div class="at-av">
    <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=PlayerA&backgroundColor=transparent" alt="A">
    <div class="at-av-badge">A</div>
  </div>
  <div class="at-stats">
    <div class="at-row"><span>Kinerja total</span><span>28,500</span></div>
    <div class="at-row"><span>Total komisi</span><span>225</span></div>
    <div class="at-row dim"><span>Kinerja langsung</span><span>5,500</span></div>
    <div class="at-row"><span>Potongan 3% komisi langsung</span><span>165</span></div>
    <div class="at-row dim"><span>Kinerja lainnya</span><span>23,000</span></div>
    <div class="at-row"><span>2% Komisi lainnya</span><span>60</span></div>
  </div>
</div>

<!-- ═══ TREE: arrows from level-2 (C) up to A ═══ -->
<div class="aff-tree">
  <!-- Top arrows: C kontribusi via B → A -->
  <div class="aff-arrows">
    <div class="aff-arrow"><b>C1 → A: 20</b><br>Selisih 2%</div>
    <div class="aff-arrow dim"><b>C2 → A: 40</b><br>Selisih 2%</div>
    <div class="aff-arrow dim"><b>C3 → A: 0</b><br>Tanpa selisih</div>
  </div>

  <!-- Level B (direct downline) -->
  <div class="aff-tree-row">
    <div class="aff-node lvl-b">
      <div class="an-top"><b>B1 Komisi</b><span>Kontribusi <span class="an-amt">15</span></span></div>
      <div class="an-av">
        <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=DownlineB1" alt="B1">
        <div class="an-av-badge">B1</div>
      </div>
      <div class="an-bet">Taruhan efektif<b>500</b></div>
    </div>
    <div class="aff-node lvl-b">
      <div class="an-top"><b>B2 Komisi</b><span>Kontribusi <span class="an-amt">90</span></span></div>
      <div class="an-av">
        <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=DownlineB2" alt="B2">
        <div class="an-av-badge">B2</div>
      </div>
      <div class="an-bet">Taruhan efektif<b>3,000</b></div>
    </div>
    <div class="aff-node lvl-b">
      <div class="an-top"><b>B3 Komisi</b><span>Kontribusi <span class="an-amt">60</span></span></div>
      <div class="an-av">
        <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=DownlineB3" alt="B3">
        <div class="an-av-badge">B3</div>
      </div>
      <div class="an-bet">Taruhan efektif<b>2,000</b></div>
    </div>
  </div>

  <!-- Mid arrows: B owns C -->
  <div class="aff-arrows">
    <div class="aff-arrow green"><b>C1 → B1: 10</b><br>Rebate 1%</div>
    <div class="aff-arrow green"><b>C2 → B2: 20</b><br>Rebate 1%</div>
    <div class="aff-arrow green"><b>C3 → B3: 600</b><br>Rebate 3%</div>
  </div>

  <!-- Level C (level 2 downline) -->
  <div class="aff-tree-row">
    <div class="aff-node lvl-c">
      <div class="an-top"><b>C1 Komisi</b><span>Kontribusi <span class="an-amt">10</span></span></div>
      <div class="an-av">
        <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=DownlineC1" alt="C1">
        <div class="an-av-badge">C1</div>
      </div>
      <div class="an-bet">Taruhan efektif<b>1,000</b></div>
    </div>
    <div class="aff-node lvl-c">
      <div class="an-top"><b>C2 Komisi</b><span>Kontribusi <span class="an-amt">20</span></span></div>
      <div class="an-av">
        <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=DownlineC2" alt="C2">
        <div class="an-av-badge">C2</div>
      </div>
      <div class="an-bet">Taruhan efektif<b>2,000</b></div>
    </div>
    <div class="aff-node lvl-c">
      <div class="an-top"><b>C3 Komisi</b><span>Kontribusi <span class="an-amt">600</span></span></div>
      <div class="an-av">
        <img src="https://api.dicebear.com/7.x/avataaars/svg?seed=DownlineC3" alt="C3">
        <div class="an-av-badge">C3</div>
      </div>
      <div class="an-bet">Taruhan efektif<b>20,000</b></div>
    </div>
  </div>

  <div class="aff-note">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
    <span>Total komisi A = 165 (langsung) + 60 (selisih level) = <b style="color:#fff;font-family:'Chakra Petch',sans-serif">225</b>. Bawahan tidak terbatas — tim makin besar, komisi makin tinggi.</span>
  </div>
</div>

<!-- ═══ TIER REBATE TABLE ═══ -->
<div class="aff-tier-card">
  <div class="aff-tier-head">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
    Tingkat Rebate
  </div>
  <div class="aff-tier"><div class="aff-tier-label">Taruhan 0 — 10,000K</div><div class="aff-tier-pct"><span class="tp-bg">1%</span></div></div>
  <div class="aff-tier"><div class="aff-tier-label">Taruhan 10,000K — 50,000K</div><div class="aff-tier-pct"><span class="tp-bg">2%</span></div></div>
  <div class="aff-tier"><div class="aff-tier-label">Taruhan 50,000K +</div><div class="aff-tier-pct"><span class="tp-bg">3%</span></div></div>
</div>

<!-- ═══ SUMMARY LIST ═══ -->
<div class="aff-sum">
  <div class="aff-tier-head">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
    Ringkasan
  </div>
  <div class="aff-sum-row">
    <div class="aff-sum-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><path d="M20 8v6M23 11h-6"/></svg></div>
    <div class="aff-sum-text"><b>Tim Langsung (B)</b><span>Bawahan yang Anda ajak sendiri (level 1). Anda dapat rebate sesuai tier mereka.</span></div>
  </div>
  <div class="aff-sum-row">
    <div class="aff-sum-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg></div>
    <div class="aff-sum-text"><b>Tim Lain (C, D, E…)</b><span>Bawahan dari bawahan Anda (level 2+). Anda dapat <b>selisih</b> antara tier Anda dan tier upline mereka.</span></div>
  </div>
  <div class="aff-sum-row">
    <div class="aff-sum-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
    <div class="aff-sum-text"><b>Tanpa Batas Waktu</b><span>Kapanpun bawahan bergabung &amp; main, komisi tetap mengalir ke saldo Anda otomatis.</span></div>
  </div>
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
