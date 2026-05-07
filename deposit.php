<?php
require_once 'includes/config.php';
header('Content-Type: text/html; charset=UTF-8');
$isLoggedIn = (bool)getUid();  // guest-friendly: page accessible, action gated by JS

$sets=[];
try{$st=$db->query("SELECT `key`,`value` FROM settings");foreach($st->fetchAll() as $r)$sets[$r['key']]=$r['value'];normSettings($sets);}catch(Exception $e){}
$sn=$sets['site_name']??'Situs';
$uid=getUid();
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php require_once dirname(__FILE__).'/pwa_head.php'; ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Deposit - <?php echo $sn; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="theme.php?v=<?=time()?>">
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;-webkit-text-size-adjust:100%;text-size-adjust:100%}
html{font-size:16px!important;overflow-x:hidden}
body{overflow-x:hidden;max-width:100vw;font-family:'Poppins',sans-serif;background:var(--bg);color:var(--t);min-height:100vh;padding-bottom:calc(100px + env(safe-area-inset-bottom,0px))}
.hdr{display:flex;align-items:center;gap:10px;padding:20px 16px 16px}
.hdr h1{font-size:1.3rem;font-weight:800}
.hdr .h-icon{width:34px;height:34px;border-radius:50%;background:var(--sec);display:flex;align-items:center;justify-content:center}
.hdr .h-icon svg{width:18px;height:18px}
.info{margin:0 16px 20px;padding:14px 16px;background:linear-gradient(135deg,rgba(var(--sec-rgb,56,189,248),.06),rgba(var(--pri-rgb,56,189,248),.03));border:1.5px solid rgba(var(--sec-rgb,56,189,248),.3);border-radius:14px;box-shadow:0 2px 12px rgba(0,0,0,.2),inset 0 1px 0 var(--tint-1)}
.info p{font-size:.75rem;color:var(--t2);line-height:1.6}
.info a{color:var(--sec,var(--sec));font-weight:700;text-decoration:none}
.sec-label{
  font-size:.82rem;font-weight:800;
  color:var(--t);
  padding:0 16px;
  margin-bottom:12px;
  text-transform:capitalize;
  letter-spacing:-.01em;
  display:flex;align-items:center;gap:8px;
}
.sec-label::before{
  content:'';
  width:3px;height:14px;
  background:linear-gradient(180deg,var(--pri),var(--pri-d));
  border-radius:2px;
  flex-shrink:0;
}
.methods{display:grid;grid-template-columns:1fr 1fr;gap:12px;padding:0 16px;margin-bottom:18px}
.mth{position:relative;padding:16px 14px;background:linear-gradient(135deg,var(--s),rgba(var(--sec-rgb,56,189,248),.04));border:1.5px solid rgba(var(--sec-rgb,56,189,248),.22);border-radius:14px;display:flex;align-items:center;gap:12px;cursor:pointer;transition:transform .2s cubic-bezier(.16,1,.3,1),border-color .2s ease,box-shadow .25s ease,background .2s ease;box-shadow:0 2px 8px rgba(0,0,0,.18),inset 0 1px 0 var(--tint-1)}
.mth::before{content:'';position:absolute;inset:0;border-radius:inherit;background:radial-gradient(circle at 70% 30%,rgba(var(--sec-rgb,56,189,248),.14),transparent 65%);opacity:0;transition:opacity .3s ease;pointer-events:none}
.mth:hover{transform:translateY(-1px);border-color:rgba(var(--sec-rgb,56,189,248),.4);box-shadow:0 4px 14px rgba(0,0,0,.25),inset 0 1px 0 var(--tint-1)}
.mth:hover::before{opacity:1}
.mth:active{transform:scale(.98)}
.mth.on{border-color:var(--sec);background:linear-gradient(135deg,rgba(var(--sec-rgb,56,189,248),.16),rgba(var(--sec-rgb,56,189,248),.05));box-shadow:0 0 0 3px rgba(var(--sec-rgb,56,189,248),.18),0 6px 18px rgba(var(--sec-rgb,56,189,248),.2),inset 0 1px 0 var(--tint-2);transform:translateY(-1px)}
.mth.on::after{content:'';position:absolute;bottom:8px;right:8px;width:18px;height:18px;border-radius:50%;background:var(--sec) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23fff' stroke-width='3.5' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='20 6 9 17 4 12'/%3E%3C/svg%3E")center/11px no-repeat;animation:mthCheck .35s cubic-bezier(.34,1.56,.64,1) both;box-shadow:0 2px 8px rgba(var(--sec-rgb),.5)}
@keyframes mthCheck{from{transform:scale(0) rotate(-180deg);opacity:0}to{transform:scale(1) rotate(0);opacity:1}}
.mth .m-icon{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:800;flex-shrink:0;background:rgba(var(--sec-rgb,56,189,248),.12);border:1px solid rgba(var(--sec-rgb,56,189,248),.2);color:var(--sec,var(--sec));transition:transform .25s cubic-bezier(.34,1.56,.64,1),background .2s,border-color .2s,box-shadow .25s}
.mth:hover .m-icon{transform:scale(1.05) rotate(-3deg)}
.mth.on .m-icon{background:rgba(var(--sec-rgb,56,189,248),.28);border-color:rgba(var(--sec-rgb,56,189,248),.6);box-shadow:0 0 14px rgba(var(--sec-rgb,56,189,248),.45);transform:scale(1.04)}
.mth .m-name{font-size:.85rem;font-weight:700;letter-spacing:-.01em}
.hot-badge{position:absolute;top:-8px;right:-8px;background:linear-gradient(135deg,#ef4444,#dc2626);color:#fff;font-size:.62rem;font-weight:800;padding:3px 9px;border-radius:8px;letter-spacing:.6px;z-index:5;box-shadow:0 3px 10px rgba(239,68,68,.5),0 0 0 1px rgba(255,255,255,.1) inset;animation:hotPulse 2.2s ease-in-out infinite;line-height:1.1}
@keyframes hotPulse{0%,100%{transform:scale(1);box-shadow:0 3px 10px rgba(239,68,68,.5),0 0 0 1px rgba(255,255,255,.1) inset}50%{transform:scale(1.06);box-shadow:0 4px 14px rgba(239,68,68,.65),0 0 0 1px rgba(255,255,255,.18) inset}}
.pay-dd{padding:0 16px;margin-bottom:18px}
.pdd-wrap{display:flex;align-items:center;justify-content:space-between;padding:15px 16px;background:linear-gradient(135deg,var(--s),rgba(var(--sec-rgb,56,189,248),.04));border:2px solid rgba(var(--sec-rgb,56,189,248),.25);border-radius:14px;cursor:pointer;font-size:.82rem;font-weight:600;box-shadow:0 3px 10px rgba(0,0,0,.2),inset 0 1px 0 var(--tint-1);transition:all .2s}
.pdd-wrap:active{transform:scale(.98)}
.pdd-wrap svg{color:var(--sec,var(--sec));transition:transform .2s}
.pay-dd.open .pdd-wrap{border-color:var(--sec);box-shadow:0 0 0 3px rgba(var(--sec-rgb,56,189,248),.15),0 4px 14px rgba(var(--sec-rgb,56,189,248),.18)}
.pay-dd.open .pdd-wrap svg{transform:rotate(180deg)}
.pdd-list{display:none;border:2px solid rgba(var(--sec-rgb,56,189,248),.25);border-top:0;border-radius:0 0 14px 14px;background:var(--s);max-height:220px;overflow-y:auto;box-shadow:0 6px 16px rgba(0,0,0,.3)}
.pay-dd.open .pdd-list{display:block}
.pdd-item{display:flex;align-items:center;gap:12px;padding:14px 16px;font-size:.78rem;font-weight:600;border-bottom:1px solid rgba(var(--sec-rgb,56,189,248),.08);cursor:pointer;transition:background .15s}
.pdd-item:last-child{border:none}
.pdd-item:active,.pdd-item.on{background:rgba(var(--sec-rgb,56,189,248),.12)}
.pdd-item .di-icon{width:34px;height:34px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:.5rem;font-weight:800;color:#fff;flex-shrink:0;box-shadow:0 2px 4px rgba(0,0,0,.2)}
/* Bonus dropdown */
.bon-dd{background:linear-gradient(135deg,var(--s),rgba(var(--sec-rgb,56,189,248),.04));border:2px solid rgba(var(--sec-rgb,56,189,248),.25);border-radius:14px;overflow:hidden;box-shadow:0 3px 10px rgba(0,0,0,.2),inset 0 1px 0 var(--tint-1)}
.bon-sel{display:flex;align-items:center;justify-content:space-between;padding:14px 16px;cursor:pointer;font-size:.82rem;font-weight:600;transition:background .15s}
.bon-sel:active{background:rgba(var(--sec-rgb,56,189,248),.06)}
.bon-sel svg{color:var(--sec,var(--sec));transition:transform .2s;flex-shrink:0}
.bon-dd.open{border-color:var(--sec);box-shadow:0 0 0 3px rgba(var(--sec-rgb,56,189,248),.15),0 4px 14px rgba(var(--sec-rgb,56,189,248),.18)}
.bon-dd.open .bon-sel svg{transform:rotate(180deg)}
.bon-list{display:none;border-top:1px solid rgba(var(--sec-rgb,56,189,248),.2);max-height:220px;overflow-y:auto}
.bon-dd.open .bon-list{display:block}
.bon-item{display:flex;align-items:center;justify-content:space-between;padding:13px 14px;cursor:pointer;border-bottom:1px solid var(--tint-1);font-size:.78rem;transition:background .15s}
.bon-item:last-child{border:none}
.bon-item:active{background:rgba(var(--sec-rgb,56,189,248),.08)}
.bon-item.on{background:rgba(var(--sec-rgb,56,189,248),.1);border-left:3px solid var(--sec)}
.bon-item .bi-r{text-align:right;font-size:.72rem;color:var(--t3)}
.bon-item .bi-r b{color:var(--pri);font-weight:800;font-size:.82rem}
/* Amount */
.amt-sec{padding:0 16px;margin-bottom:14px}
.amt-input{
  display:flex;align-items:center;
  background:var(--bg2);
  border:1.5px solid var(--bd2);
  border-radius:14px;
  padding:0 18px;
  margin-bottom:10px;
  transition:all .2s;
  box-shadow:0 1px 2px rgba(0,0,0,.04);
}
.amt-input:hover{border-color:var(--bd2)}
.amt-input:focus-within{
  border-color:var(--pri);
  box-shadow:0 0 0 4px var(--pri-l);
  background:var(--bg2);
}
.amt-input input{flex:1;background:none;border:none;color:var(--t);font-family:inherit;font-size:1.05rem;font-weight:800;padding:16px 0;outline:none;-webkit-appearance:none;appearance:none;-moz-appearance:textfield}
.amt-input input::-webkit-outer-spin-button,.amt-input input::-webkit-inner-spin-button{-webkit-appearance:none;margin:0}
.amt-input input::placeholder{color:var(--t3);font-weight:400}
.amt-input .k-unit{font-size:.95rem;font-weight:800;color:var(--pri);padding-left:8px;border-left:1px solid var(--bd);margin-left:8px;line-height:1}
.amt-note{font-size:.68rem;color:var(--sec);margin-bottom:14px;display:flex;align-items:center;gap:4px}
.amt-note svg{width:14px;height:14px;flex-shrink:0}
.quick{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:22px}
.qk{
  position:relative;
  padding:14px 8px;
  text-align:center;
  background:rgba(255,255,255,.04);
  border:1px solid rgba(255,255,255,.08);
  border-radius:999px;
  font-size:.88rem;font-weight:600;
  color:var(--t2);
  cursor:pointer;
  transition:all .18s cubic-bezier(.4,0,.2,1);
  letter-spacing:-.005em;
  user-select:none;
}
.qk:hover{
  border-color:rgba(var(--pri-rgb),.4);
  color:var(--t);
}
.qk:active{
  transform:scale(.97);
}
.qk.on{
  background:linear-gradient(135deg,rgba(var(--pri-rgb),.18),rgba(var(--pri-rgb),.08));
  color:var(--pri);
  border-color:rgba(var(--pri-rgb),.5);
  box-shadow:0 6px 16px rgba(var(--pri-rgb),.4);
  transform:translateY(-1px);
}
.qk.on::after{
  content:'✓';
  position:absolute;
  top:4px;right:6px;
  font-size:.6rem;
  background:var(--bd2);
  width:14px;height:14px;
  border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  font-weight:900;
}
/* Popular badge on common amounts */
.qk[data-popular]::before{
  content:attr(data-popular);
  position:absolute;
  top:-7px;right:8px;
  background:linear-gradient(135deg,#f59e0b,#d97706);
  color:#fff;
  font-size:.55rem;font-weight:800;
  padding:2px 7px;
  border-radius:6px;
  letter-spacing:.4px;
  text-transform:uppercase;
  box-shadow:0 2px 6px rgba(245,158,11,.4);
}
.qk.on[data-popular]::before{background:rgba(255,255,255,.95);color:var(--pri-d)}
.submit{
  display:block;
  width:calc(100% - 32px);
  margin:8px 16px 20px;
  padding:16px;
  text-align:center;
  font-size:1rem;font-weight:700;
  color:#fff;
  background:linear-gradient(135deg,var(--pri) 0%,var(--pri-d) 100%);
  background-size:200% auto;
  border:none;border-radius:10px;
  cursor:pointer;font-family:inherit;
  transition:background-position .25s ease,box-shadow .15s;
  letter-spacing:.5px;
  position:relative;overflow:hidden;
  box-shadow:0 4px 14px rgba(var(--pri-rgb),.3);
}
.submit:hover{background-position:right center;box-shadow:0 6px 20px rgba(var(--pri-rgb),.45)}
.submit::before{
  content:'';position:absolute;top:0;left:-100%;width:100%;height:100%;
  background:linear-gradient(90deg,transparent,var(--bd2),transparent);
  transition:left .5s;
}
.submit:hover{box-shadow:0 12px 28px rgba(var(--pri-rgb),.45);transform:translateY(-1px)}
.submit:hover::before{left:100%}
.submit:active{transform:translateY(0) scale(.99)}
.submit:disabled{opacity:.5;cursor:not-allowed}
/* Payment modal */
.pay-overlay{position:fixed;inset:0;background:var(--bg);z-index:9500;display:none;flex-direction:column;overflow-y:auto}
.pay-overlay.open{display:flex}
.pay-hdr{display:flex;align-items:center;padding:16px;flex-shrink:0;border-bottom:1px solid var(--bd)}
.pay-hdr button{width:32px;height:32px;background:none;border:none;color:var(--t2);cursor:pointer;display:flex;align-items:center;justify-content:center}
.pay-hdr button svg{width:22px;height:22px}
.pay-hdr h2{flex:1;text-align:center;font-size:1rem;font-weight:700}
.pay-body{overflow-x:hidden;max-width:100vw;flex:1;overflow-y:auto;padding:16px}
.pay-status{display:flex;align-items:center;justify-content:center;gap:6px;font-size:.85rem;font-weight:700;padding:12px;border-radius:10px;margin-bottom:12px}
.pay-status.pending{background:rgba(251,191,36,.08);color:#fbbf24;border:1px solid rgba(251,191,36,.2)}
.pay-status.paid{background:rgba(74,222,128,.08);color:#4ade80;border:1px solid rgba(74,222,128,.2)}
.pay-status.expired{background:rgba(239,68,68,.08);color:#ef4444;border:1px solid rgba(239,68,68,.2)}
.tf-name{font-size:.72rem;color:var(--t3);margin-top:4px}
.pay-card{background:var(--s);border:1px solid var(--bd);border-radius:12px;padding:4px 16px;margin-bottom:16px}
.pc-row{display:flex;justify-content:space-between;align-items:center;padding:11px 0;border-bottom:1px solid rgba(var(--sec-rgb,56,189,248),.1)}
.pc-row:last-child{border:none}
.pc-row span{font-size:.72rem;color:var(--t3)}
.pc-row b{font-size:.82rem;font-weight:700;text-align:right}
.pay-qr-wrap{text-align:center;margin-bottom:16px}
.pay-qr{display:inline-block;background:#fff;border-radius:14px;padding:16px;box-shadow:0 4px 20px rgba(0,0,0,.2)}
.pay-qr img{width:200px;height:200px;display:block}
.pay-tf{background:var(--s);border:1px solid var(--bd);border-radius:12px;padding:16px;margin-bottom:16px}
.tf-main{text-align:center;padding-bottom:14px;border-bottom:1px solid var(--bd);margin-bottom:10px}
.tf-label{font-size:.68rem;color:var(--t3);margin-bottom:8px}
.tf-number{font-size:1.3rem;font-weight:800;color:var(--sec);letter-spacing:1px;margin-bottom:10px;font-family:monospace}
.tf-copy{display:inline-flex;align-items:center;gap:4px;padding:8px 18px;background:rgba(var(--sec-rgb,56,189,248),.1);border:1px solid var(--bd);border-radius:8px;color:var(--sec);font-size:.72rem;font-weight:700;cursor:pointer;font-family:inherit}
.tf-row{display:flex;justify-content:space-between;padding:10px 0;border-bottom:1px solid rgba(var(--sec-rgb,56,189,248),.08)}
.tf-row:last-child{border:none}
.tf-row span{font-size:.72rem;color:var(--t3)}
.tf-row b{font-size:.82rem;font-weight:700}
.pay-err{color:#ef4444;font-size:.82rem;font-weight:700;padding:14px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.15);border-radius:10px;margin-bottom:16px;text-align:center}
.pay-guide{background:var(--s);border:1px solid var(--bd);border-radius:12px;padding:16px;margin-bottom:20px}
.pay-guide h4{font-size:.85rem;font-weight:700;margin-bottom:14px}
.pg-step{display:flex;align-items:flex-start;gap:10px;margin-bottom:10px}
.pg-num{width:22px;height:22px;border-radius:50%;background:var(--sec);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:800;flex-shrink:0}
.pg-step span{font-size:.72rem;color:var(--t2);line-height:1.5;padding-top:2px}
.pg-warn{display:flex;align-items:flex-start;gap:8px;margin-top:14px;padding:12px;background:rgba(251,191,36,.06);border:1px solid rgba(251,191,36,.15);border-radius:8px;font-size:.68rem;color:#fbbf24;line-height:1.5}
.pg-warn svg{flex-shrink:0;margin-top:2px}
.pg-s{display:flex;align-items:flex-start;gap:10px;margin-bottom:8px}
.pg-n{width:20px;height:20px;border-radius:50%;background:var(--sec);color:#fff;display:flex;align-items:center;justify-content:center;font-size:.6rem;font-weight:800;flex-shrink:0}
.pg-s span{font-size:.7rem;color:var(--t2);line-height:1.5;padding-top:1px}
/* K info popup */
.k-popup{position:fixed;inset:0;z-index:9999;display:none;align-items:flex-end;justify-content:center}
.k-popup.open{display:flex}
.k-bg{position:absolute;inset:0;background:rgba(0,0,0,.92)}
.k-box{position:relative;z-index:1;width:100%;max-width:390px;background:var(--bg);border:1px solid rgba(var(--sec-rgb),.2);border-radius:20px 20px 0 0;padding:24px 20px 30px;max-height:80vh;overflow-y:auto;box-shadow:0 -10px 40px rgba(0,0,0,.8)}
.k-box .k-close{position:absolute;top:16px;right:16px;width:32px;height:32px;background:var(--tint-2);border:1px solid var(--tint-3);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;color:#fff}
.k-box h3{font-size:1.1rem;font-weight:800;margin-bottom:14px;color:#fff}
.k-box p{font-size:.78rem;color:var(--t2);line-height:1.7;margin-bottom:12px}
.k-box .k-num{display:flex;align-items:center;gap:8px;margin:8px 0}
.k-box .k-num .kn{width:28px;height:28px;border-radius:50%;background:var(--sec,var(--sec));display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:800;color:#fff;flex-shrink:0}
.k-box .k-highlight{text-align:center;font-size:1.2rem;font-weight:800;color:var(--sec,var(--sec));padding:12px;margin:12px 0;background:rgba(var(--sec-rgb,56,189,248),.08);border:1px solid rgba(var(--sec-rgb,56,189,248),.2);border-radius:10px}
.k-box .k-ok{display:block;width:100%;padding:14px;background:linear-gradient(135deg,var(--sec,var(--sec)) 0%,var(--sec-d,var(--sec-d)) 100%);color:#fff;border:none;border-radius:10px;font-size:.9rem;font-weight:700;cursor:pointer;font-family:inherit;margin-top:16px;box-shadow:0 4px 12px rgba(var(--sec-rgb,56,189,248),.3)}
/* Pending deposit item */
.pending-item{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:rgba(var(--pri-rgb,56,189,248),.06);border:1px solid rgba(var(--pri-rgb,56,189,248),.2);border-radius:10px;margin-bottom:8px}
.pi-info{flex:1}
.pi-tx{font-size:.65rem;color:var(--t3);font-family:monospace;margin-bottom:2px}
.pi-amt{font-size:.88rem;font-weight:800}
.pi-method{font-size:.68rem;color:var(--t3);margin-top:2px}
.pi-timer{font-size:.65rem;color:var(--pri);font-weight:600;margin-top:3px}
.pi-btn{padding:8px 14px;background:var(--pri);border:none;border-radius:8px;color:var(--bg);font-size:.72rem;font-weight:800;cursor:pointer;font-family:inherit;white-space:nowrap}

.bnav{position:fixed;bottom:0;left:0;right:0;z-index:100;display:flex;background:var(--nav-bg,#0a1628);padding:8px 0 env(safe-area-inset-bottom,6px);overflow:hidden;border-radius:14px 14px 0 0;box-shadow:0 -4px 20px rgba(0,0,0,.5);border-top:1px solid rgba(var(--sec-rgb,56,189,248),.2)}
.bnav::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent 2%,var(--pri) 15%,var(--sec-l, var(--pri-l)) 50%,var(--pri) 85%,transparent 98%)}
.bnav-i{flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;padding:6px 0;color:var(--t3);font-size:.6rem;font-weight:600;text-decoration:none}
.bnav-i.active{color:var(--sec)}
.bnav-i img{width:36px;height:36px;object-fit:contain}
/* Toast */
.toast-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9998}
.toast-box{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--s);border:1px solid var(--bd);border-radius:14px;padding:24px 30px;text-align:center;z-index:9999;max-width:280px;box-shadow:0 10px 40px rgba(0,0,0,.5)}
.toast-box p{font-size:.85rem;font-weight:600;color:var(--t);line-height:1.5;margin-bottom:16px}
.toast-box button{padding:8px 30px;background:var(--sec);border:none;border-radius:8px;color:#fff;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit}
</style>
</head>
<body>

<?php  ?>
<div class="hdr"><h1>Deposit</h1><div class="h-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="M2 10h20"/></svg></div></div>

<div class="info">
<p>Jika Anda memiliki pertanyaan atau masalah, silakan hubungi layanan pelanggan. Terima kasih!</p>
<p style="margin-top:6px"><svg viewBox="0 0 24 24" fill="none" stroke="var(--sec)" stroke-width="2" width="14" height="14" style="display:inline-block;vertical-align:middle"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg> <a href="cs.php">Layanan Pelanggan Online.</a></p>
</div>

<div class="sec-label">membayar</div>
<div class="methods">
<div class="mth on" id="mQris" onclick="selMethod('qris')">
<span class="hot-badge">HOT</span>
<div class="m-icon" style="background:var(--s2,var(--bg3));border:1px solid var(--bd);border-radius:10px;width:46px;height:46px;display:flex;align-items:center;justify-content:center">
<svg viewBox="0 0 24 24" fill="none" stroke="var(--t2)" stroke-width="1.5" width="28" height="28">
<rect x="2" y="2" width="8" height="8" rx="1.5"/><rect x="4" y="4" width="4" height="4" rx=".5" fill="var(--t2)"/>
<rect x="14" y="2" width="8" height="8" rx="1.5"/><rect x="16" y="4" width="4" height="4" rx=".5" fill="var(--t2)"/>
<rect x="2" y="14" width="8" height="8" rx="1.5"/><rect x="4" y="16" width="4" height="4" rx=".5" fill="var(--t2)"/>
<rect x="14" y="14" width="4" height="4" rx=".5" fill="var(--t2)"/><rect x="20" y="14" width="2" height="4" rx=".5" fill="var(--t2)"/>
<rect x="14" y="20" width="4" height="2" rx=".5" fill="var(--t2)"/><rect x="20" y="20" width="2" height="2" rx=".5" fill="var(--t2)"/>
</svg>
</div>
<div class="m-name">QRIS</div>
</div>
<div class="mth" id="mBank" onclick="selMethod('bank')">
<div class="m-icon" style="background:var(--s2,var(--bg3));border:1px solid var(--bd);border-radius:10px;width:46px;height:46px;display:flex;align-items:center;justify-content:center">
<svg viewBox="0 0 24 24" fill="none" stroke="var(--t2)" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" width="28" height="28">
<path d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11M20 10v11M8 14v3M12 14v3M16 14v3"/>
</svg>
</div>
<div class="m-name">Bank / E-Wallet</div>
</div>
</div>

<div class="pay-dd" id="payDD" style="display:none">
<div class="sec-label" style="margin-bottom:8px">Pilih Pembayaran</div>
<div class="pdd-wrap" id="pddSel" onclick="togglePayDD()">
<span id="pddLabel">Pilih metode</span>
<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="6 9 12 15 18 9"/></svg>
</div>
<div class="pdd-list" id="pddList">
<div class="pdd-item" style="color:var(--t3);justify-content:center;padding:20px">Memuat metode...</div>
</div>
</div>

<div class="amt-sec">
<div class="sec-label" style="padding:0;margin-bottom:8px">
  <span>jumlah uang</span>
  <span id="bonHint" style="display:none;margin-left:auto;font-size:.72rem;font-weight:800;color:var(--pri);background:rgba(var(--pri-rgb),.1);border:1px solid rgba(var(--pri-rgb),.25);border-radius:6px;padding:4px 10px">
    + <span id="bonHintVal">0</span> bonus
  </span>
</div>
<div class="amt-input">
<input type="tel" id="amtInput" placeholder="10 - 10,000" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
<div class="k-unit">K</div>
</div>
<div class="amt-note" onclick="openKinfo()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg> Nominal dalam K (1K = Rp1000)</div>
<div class="quick">
<div class="qk" onclick="setAmt(20)">20K</div>
<div class="qk" onclick="setAmt(50)">50K</div>
<div class="qk" data-popular="HOT" onclick="setAmt(100)">100K</div>
<div class="qk" onclick="setAmt(200)">200K</div>
<div class="qk" data-popular="HOT" onclick="setAmt(500)">500K</div>
<div class="qk" onclick="setAmt(1000)">1,000K</div>
<div class="qk" onclick="setAmt(3000)">3,000K</div>
<div class="qk" onclick="setAmt(5000)">5,000K</div>
<div class="qk" onclick="setAmt(10000)">10,000K</div>
</div>
</div>

<!-- Bonus -->
<div style="padding:0 16px;margin-bottom:16px">
<div class="sec-label" style="padding:0;margin-bottom:8px">Bonus</div>
<div class="bon-dd" id="bonDD">
<div class="bon-sel" onclick="document.getElementById('bonDD').classList.toggle('open')"><span id="bonLabel">Cashback 4%</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><polyline points="6 9 12 15 18 9"/></svg></div>
<div class="bon-list" id="bonList"></div>
</div>
</div>

<button class="submit" id="submitBtn" onclick="doDeposit()">Deposit sekarang</button>

<!-- Pending deposits section -->
<div id="pendingSection" style="display:none;padding:0 16px;margin-bottom:16px">
<div class="sec-label" style="padding:0;margin-bottom:8px;color:var(--pri)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="vertical-align:-2px;display:inline-block"><path d="M6 2h12v4l-4 4 4 4v4H6v-4l4-4-4-4z"/></svg> deposit pending</div>
<div id="pendingList"></div>
</div>

<!-- Payment overlay -->
<div class="pay-overlay" id="payOverlay">
<div class="pay-hdr"><button onclick="closePay()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button><h2>Pembayaran</h2><div style="width:32px"></div></div>
<div class="pay-body" id="payBody"></div>
</div>

<!-- K info popup -->
<div class="k-popup" id="kPopup">
<div class="k-bg" onclick="closeKinfo()"></div>
<div class="k-box">
<button class="k-close" onclick="closeKinfo()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button>
<h3>Penjelasan dengan K</h3>
<p>Agar Anda dapat melihat saldo dan jumlah transaksi dengan lebih jelas, situs kami telah mengoptimalkan 3(tiga) angka nol terakhir menjadi K:</p>
<div class="k-num"><div class="kn">1</div><div><b>Mata uang aktual</b><br><span style="font-size:.72rem;color:var(--t3)">Rupiah (Rp) menggunakan kurs aktual 1:1.</span></div></div>
<div class="k-num"><div class="kn">2</div><div><b>Tampilan Situs</b><br><span style="font-size:.72rem;color:var(--t3)">K adalah satuan penyederhanaan 1:1000.</span></div></div>
<div class="k-highlight">Rp 100,000 = 100K</div>
<p>Unit tampilan berbeda, nilainya sama! Setiap pemisah ribuan kami menggunakan koma (,).</p>
<p>Semua Deposit dan penarikan dihitung dalam Rupiah (Rp). Situs akan memproses secara otomatis, Anda akan menerima jumlah yang benar. Jika ada pertanyaan, hubungi Live Chat 24 Jam kami.</p>
<button class="k-ok" onclick="closeKinfo()">OK</button>
</div>
</div>

<?php echo renderBnav($db,"deposit", $isLoggedIn); ?>

<script>
var curMethod='qris';var curSub='';var curBonus=0;var pollTimer=null;

// Load bonuses dropdown
var bonusData=[];
fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'bonuses'})})
.then(function(r){return r.json()}).then(function(d){
  if(!d.ok)return;bonusData=d.bonuses||[];
  var el=document.getElementById('bonList');

  // Urutan: bonus aktif dari DB dulu (default item pertama di-select), "Tanpa Bonus" paling bawah
  var h='';
  if(bonusData.length){
    // Item pertama = default selected
    bonusData.forEach(function(b,idx){
      var cls=(idx===0)?'bon-item on':'bon-item';
      h+='<div class="'+cls+'" onclick="pickBon(this,'+b.id+',\''+b.name+'\')"><span>'+b.name+'</span><div class="bi-r"><b>'+b.percentage+'%</b><br>TO x'+b.turnover_x+'</div></div>';
    });
    // Auto-apply default: bonus pertama
    curBonus=bonusData[0].id;
    document.getElementById('bonLabel').textContent=bonusData[0].name;
  }
  // "Tanpa Bonus" paling bawah
  var tbClass=bonusData.length?'bon-item':'bon-item on';
  h+='<div class="'+tbClass+'" onclick="pickBon(this,0,\'Tanpa Bonus\')">Tanpa Bonus</div>';

  el.innerHTML=h;

  // Kalau ga ada bonus aktif, default ke Tanpa Bonus
  if(!bonusData.length){
    curBonus=0;
    document.getElementById('bonLabel').textContent='Tanpa Bonus';
  }
  calcBonusHint();
});
function pickBon(el,id,label){
  curBonus=id;
  document.getElementById('bonLabel').textContent=label;
  document.querySelectorAll('.bon-item').forEach(function(b){b.classList.remove('on')});
  el.classList.add('on');
  document.getElementById('bonDD').classList.remove('open');
  calcBonusHint();
}

// Hitung estimasi bonus berdasarkan nominal input + bonus yg dipilih
function calcBonusHint(){
  var amt=parseInt(document.getElementById('amtInput').value||'0',10);
  var hint=document.getElementById('bonHint');
  var val=document.getElementById('bonHintVal');
  // curBonus=0 berarti Tanpa Bonus, skip
  if(!curBonus||!amt||amt<=0){hint.style.display='none';return;}
  var b=null;
  for(var i=0;i<bonusData.length;i++){if(bonusData[i].id===curBonus){b=bonusData[i];break;}}
  if(!b){hint.style.display='none';return;}
  // Bonus = nominal (K) * percentage / 100 — hasil dalam K juga
  var bonusK=Math.floor(amt*parseFloat(b.percentage||0)/100);
  // Cap kalau ada max_bonus (dalam Rupiah, convert ke K)
  if(b.max_bonus&&b.max_bonus>0){
    var maxK=Math.floor(b.max_bonus/1000);
    if(bonusK>maxK)bonusK=maxK;
  }
  if(bonusK<=0){hint.style.display='none';return;}
  val.textContent='Rp '+bonusK.toLocaleString('id-ID')+'K';
  hint.style.display='inline-block';
}

// Listener realtime
document.addEventListener('DOMContentLoaded',function(){
  var ai=document.getElementById('amtInput');
  if(ai)ai.addEventListener('input',calcBonusHint);
});

// Load available payment methods from API
var _banks=['bca','bri','bni','mandiri','cimb','permata','danamon','bsi'];
var _walletIco='<svg viewBox="0 0 24 24" fill="none" stroke="var(--t2)" stroke-width="2" width="16" height="16"><path d="M21 18v1a2 2 0 01-2 2H5a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v1"/><path d="M10 12h11m-4-4l4 4-4 4"/></svg>';
var _bankIco='<svg viewBox="0 0 24 24" fill="none" stroke="var(--t2)" stroke-width="1.5" width="16" height="16"><path d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11M20 10v11M8 14v3M12 14v3M16 14v3"/></svg>';

fetch('api/deposit.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'methods'})})
.then(function(r){return r.json()}).then(function(d){
  if(!d.ok)return;
  var methods=d.methods||{};
  // methods bisa array atau object, normalize
  var list=[];
  if(Array.isArray(methods)){list=methods;}
  else if(methods.methods&&Array.isArray(methods.methods)){list=methods.methods;}
  else if(methods.data&&Array.isArray(methods.data)){list=methods.data;}
  else{
    // Object format: {gopay:{name:'GoPay',status:1}, ...}
    for(var k in methods){if(methods[k]&&typeof methods[k]==='object')list.push({code:k,name:methods[k].name||k,status:methods[k].status});}
    if(!list.length){
      // Might be flat: {gopay:'GoPay', dana:'DANA', ...}
      for(var k in methods){if(typeof methods[k]==='string')list.push({code:k,name:methods[k]});}
    }
  }
  
  var el=document.getElementById('pddList');
  if(!list.length){el.innerHTML='<div class="pdd-item" style="color:var(--t3);justify-content:center">Tidak ada metode tersedia</div>';return}
  
  var h='';
  list.forEach(function(m){
    var code=m.code||m.method||m.id||'';
    var name=m.name||m.label||code.toUpperCase();
    if(!code&&typeof m==='string'){code=m;name=m.toUpperCase();}
    if(!code||code.toLowerCase()==='qris')return;
    var isBank=_banks.indexOf(code.toLowerCase())!==-1;
    var ico=isBank?_bankIco:_walletIco;
    h+='<div class="pdd-item" onclick="pickPay(\''+code+'\',\''+name+'\',event)"><div class="di-icon" style="background:rgba(var(--sec-rgb,56,189,248),.15)">'+ico+'</div>'+name+'</div>';
  });
  el.innerHTML=h||'<div class="pdd-item" style="color:var(--t3);justify-content:center">Tidak ada metode tersedia</div>';
}).catch(function(){
  document.getElementById('pddList').innerHTML='<div class="pdd-item" style="color:var(--t3);justify-content:center">Gagal memuat metode</div>';
});

function selMethod(m){
  if(m==='qris'){
    curMethod='qris';curSub='';
    document.getElementById('mQris').classList.add('on');
    document.getElementById('mBank').classList.remove('on');
    document.getElementById('payDD').style.display='none';
    document.getElementById('payDD').classList.remove('open');
  }else{
    document.getElementById('mQris').classList.remove('on');
    document.getElementById('mBank').classList.add('on');
    document.getElementById('payDD').style.display='block';
    if(!curSub){document.getElementById('pddLabel').textContent='Pilih metode';}
  }
}
function togglePayDD(){document.getElementById('payDD').classList.toggle('open')}
function pickPay(m,label,e){
  curMethod=m;curSub=m;
  document.querySelectorAll('.pdd-item').forEach(function(d){d.classList.remove('on')});
  e.currentTarget.classList.add('on');
  document.getElementById('pddLabel').textContent=label;
  document.getElementById('payDD').classList.remove('open');
}
function setAmt(k){document.getElementById('amtInput').value=k;calcBonusHint();}
function openKinfo(){document.getElementById('kPopup').classList.add('open')}
function closeKinfo(){document.getElementById('kPopup').classList.remove('open')}

function doDeposit(){
  if(typeof requireLogin==='function'&&!requireLogin('deposit'))return;
  var kVal=parseFloat(document.getElementById('amtInput').value);
  if(!kVal||kVal<10){showToast('Minimum deposit 10K (Rp 10.000)');return}
  if(kVal>10000){showToast('Maximum deposit 10,000K (Rp 10.000.000)');return}
  if(curMethod!=='qris'&&!curSub){showToast('Pilih metode pembayaran terlebih dahulu');return}
  var nominal=Math.round(kVal*1000);
  var btn=document.getElementById('submitBtn');btn.disabled=true;btn.textContent='Memproses...';
  // Simpan method asli yg user pilih (buat display "Scan via DANA" dst)
  var pickedMethod=curMethod;
  var pickedSub=curSub;
  fetch('api/deposit.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'create',method:curMethod,type:'qris',nominal:nominal,bonus_id:curBonus})})
  .then(function(r){return r.json()}).then(function(d){
    btn.disabled=false;btn.textContent='Deposit sekarang';
    if(!d.ok){
      // Auth fail — redirect to login (instead of showing confusing toast)
      if(d.error==='NOT_LOGGED_IN'){
        showToast('Sesi habis, silakan login ulang');
        setTimeout(function(){location.href='index.php?login=1&from='+encodeURIComponent(location.pathname);},1200);
        return;
      }
      showToast(d.error||'Gagal membuat deposit');return;
    }
    // Inject info method yg dipilih user (untuk display)
    d._picked_method=pickedMethod;
    d._picked_sub=pickedSub;
    showPayment(d);
  }).catch(function(){btn.disabled=false;btn.textContent='Deposit sekarang';showToast('Koneksi gagal, coba lagi')});
}

var timerInterval=null;

function showPayment(d){
  if(timerInterval)clearInterval(timerInterval);
  if(pollTimer)clearInterval(pollTimer);

  var payAmt=Number(d.pay_amount||d.nominal);
  var txId=d.tx_id;
  var isPaid=(d.status==='paid');
  var isQris=(d.pay_type==='qris'||d.method==='qris')&&d.qr_url;
  var method=(d.method||'QRIS').toUpperCase();

  // Timer: use timeout minutes from now (avoid timezone parsing bugs)
  // SPEC SQX: timeout default 720 menit (12 jam)
  var timeoutMs=((d.timeout||720)*60000);
  // For resumed deposits, calc remaining from expires_at
  if(d._resumed&&d.expires_at){
    try{
      // Server WIB: append +07:00 if no timezone
      var eStr=d.expires_at.replace(' ','T');
      if(!/[Z+-]\d/.test(eStr))eStr+='+07:00';
      timeoutMs=new Date(eStr).getTime()-Date.now();
    }catch(e){timeoutMs=720*60000;}
  }
  var expTime=Date.now()+Math.max(timeoutMs,0);

  var h='';

  // ── Timer bar ──
  if(isPaid){
    h+='<div class="pay-status paid" id="payStatus">Pembayaran Berhasil</div>';
  }else{
    h+='<div class="pay-status pending" id="payStatus">Menunggu pembayaran · <b id="payTimerTxt">--:--</b></div>';
  }

  // ── QRIS: QR first ──
  if(isQris){
    h+='<div class="pay-qr-wrap"><div class="pay-qr"><img src="'+d.qr_url+'" alt="QRIS" onerror="this.src=\'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data='+encodeURIComponent(d.qris_string||txId)+'\'"></div></div>';

    // Info kecil kalau user pilih method e-wallet/bank tertentu
    var picked=(d._picked_method||'').toLowerCase();
    var pickedSub=(d._picked_sub||'').toLowerCase();
    var brandLabel='';
    var brandMap={dana:'DANA',gopay:'GoPay',ovo:'OVO',shopee:'ShopeePay',shopeepay:'ShopeePay',linkaja:'LinkAja',
                  bca:'BCA Mobile',bri:'BRImo',bni:'BNI Mobile',mandiri:'Livin Mandiri',cimb:'CIMB Niaga',
                  permata:'Permata Mobile',danamon:'D-Bank',jago:'Jago',seabank:'SeaBank',blu:'Blu BCA'};
    var bk=pickedSub||picked;
    if(bk&&bk!=='qris'&&brandMap[bk])brandLabel=brandMap[bk];
    if(brandLabel){
      h+='<div style="margin:0 16px 14px;padding:10px 12px;background:rgba(var(--sec-rgb),.08);border:1px solid rgba(var(--sec-rgb),.25);border-radius:10px;display:flex;align-items:center;gap:10px;font-size:.74rem">';
      h+='<svg viewBox="0 0 24 24" fill="none" stroke="var(--pri,var(--sec))" stroke-width="2" width="20" height="20" style="flex-shrink:0"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>';
      h+='<div><b style="color:var(--pri,var(--sec))">Scan QR ini via app '+brandLabel+'</b><div style="color:var(--t2);margin-top:2px;font-size:.7rem">Buka app '+brandLabel+' kamu → menu Bayar/Scan QR → scan QR di atas</div></div>';
      h+='</div>';
    }
  }
  // ── Transfer: Account first ──
  else if(d.account_number){
    h+='<div class="pay-tf">';
    h+='<div class="tf-main"><div class="tf-label">'+method+'</div>';
    h+='<div class="tf-number" id="accNum">'+d.account_number+'</div>';
    h+='<div class="tf-name">a.n. '+(d.account_name||'-')+'</div>';
    h+='<button class="tf-copy" onclick="copyAcc()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"/></svg> Salin</button>';
    h+='</div></div>';
  }

  // ── Amount highlight ──
  h+='<div style="text-align:center;padding:14px 0;margin:0 16px;border:1.5px dashed var(--bd);border-radius:10px;margin-bottom:14px">';
  h+='<div style="font-size:.62rem;color:var(--t3)">Bayar tepat</div>';
  h+='<div style="font-size:1.5rem;font-weight:900;color:var(--pri);margin-top:2px">Rp '+payAmt.toLocaleString('id')+'</div>';
  if(d.bonus>0)h+='<div style="font-size:.68rem;color:var(--sec);margin-top:4px">+ Bonus Rp '+Number(d.bonus).toLocaleString('id')+'</div>';
  h+='</div>';

  // ── Step by step guide ──
  if(!isPaid){
    h+='<div class="pay-guide" style="margin:0 16px 14px;background:var(--s);border:1px solid var(--bd);border-radius:10px;padding:14px">';
    h+='<div style="font-size:.78rem;font-weight:700;margin-bottom:10px">Cara Pembayaran</div>';
    if(isQris){
      h+='<div class="pg-s"><div class="pg-n">1</div><span>Buka aplikasi <b>e-wallet</b> (GoPay, OVO, DANA, ShopeePay) atau <b>m-banking</b></span></div>';
      h+='<div class="pg-s"><div class="pg-n">2</div><span>Pilih menu <b>Scan QR</b> atau <b>Bayar dengan QRIS</b></span></div>';
      h+='<div class="pg-s"><div class="pg-n">3</div><span>Arahkan kamera ke kode QR di atas</span></div>';
      h+='<div class="pg-s"><div class="pg-n">4</div><span>Nominal <b>otomatis terisi Rp '+payAmt.toLocaleString('id')+'</b>, langsung konfirmasi</span></div>';
      h+='<div class="pg-s"><div class="pg-n">5</div><span>Pembayaran <b>otomatis terkonfirmasi</b> dalam beberapa detik</span></div>';
    }else{
      h+='<div class="pg-s"><div class="pg-n">1</div><span>Buka aplikasi <b>'+method+'</b> di HP Anda</span></div>';
      h+='<div class="pg-s"><div class="pg-n">2</div><span>Pilih menu <b>Transfer</b></span></div>';
      h+='<div class="pg-s"><div class="pg-n">3</div><span>Masukkan nomor tujuan <b>'+d.account_number+'</b> (a.n. <b>'+(d.account_name||'-')+'</b>)</span></div>';
      h+='<div class="pg-s"><div class="pg-n">4</div><span>Masukkan jumlah <b>tepat Rp '+payAmt.toLocaleString('id')+'</b> — jangan dibulatkan</span></div>';
      h+='<div class="pg-s"><div class="pg-n">5</div><span>Konfirmasi transfer. Pembayaran <b>otomatis terdeteksi</b> dalam beberapa detik</span></div>';
    }
    h+='<div style="margin-top:10px;padding:8px 10px;background:rgba(251,191,36,.06);border:1px solid rgba(251,191,36,.12);border-radius:6px;font-size:.62rem;color:var(--pri);line-height:1.5">';
    if(isQris) h+='Nominal sudah otomatis terisi. Langsung konfirmasi pembayaran.';
    else h+='Wajib transfer <b>tepat Rp '+payAmt.toLocaleString('id')+'</b>. Nominal berbeda tidak terdeteksi otomatis.';
    h+='</div>';
    h+='</div>';
  }

  // ── Compact info ──
  h+='<div style="padding:0 16px;margin-bottom:12px">';
  h+='<div style="display:flex;justify-content:space-between;padding:8px 0;font-size:.68rem;border-bottom:1px solid var(--tint-1)"><span style="color:var(--t3)">ID Transaksi</span><span style="font-family:monospace;font-size:.62rem">'+txId+'</span></div>';
  h+='<div style="display:flex;justify-content:space-between;padding:8px 0;font-size:.68rem"><span style="color:var(--t3)">Metode</span><span style="font-weight:700">'+method+'</span></div>';
  h+='</div>';

  // ── Error ──
  if(d.error){
    h+='<div style="margin:0 16px 12px;padding:10px 12px;background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.15);border-radius:8px;font-size:.7rem;color:#ef4444">'+d.error+'</div>';
  }

  // ── Done button ──
  if(isPaid){
    h+='<div style="padding:16px"><button onclick="closePay();location.reload()" style="width:100%;padding:14px;background:var(--sec);border:none;border-radius:10px;color:#fff;font-size:.88rem;font-weight:700;cursor:pointer;font-family:inherit">Kembali</button></div>';
  }

  document.getElementById('payBody').innerHTML=h;
  document.getElementById('payOverlay').classList.add('open');

  if(isPaid)return;

  // ── Countdown (client-side calculated) ──
  timerInterval=setInterval(function(){
    var left=expTime-Date.now();
    var el=document.getElementById('payTimerTxt');
    var st=document.getElementById('payStatus');
    if(!el){clearInterval(timerInterval);return}
    if(left<=0){
      if(st){st.className='pay-status expired';st.innerHTML='Transaksi expired';}
      clearInterval(timerInterval);clearInterval(pollTimer);
      return;
    }
    // Format: kalau >1 jam tampil H:MM:SS, kalau <1 jam tampil MM:SS
    var hh=Math.floor(left/3600000);
    var mm=Math.floor((left%3600000)/60000);
    var ss=Math.floor((left%60000)/1000);
    if(hh>0){
      el.textContent=hh+':'+String(mm).padStart(2,'0')+':'+String(ss).padStart(2,'0');
    }else{
      el.textContent=mm+':'+String(ss).padStart(2,'0');
    }
  },1000);

  // ═══ DUAL MONITOR: SSE direct ke SQX (push 2s) + fallback polling backend (3s) ═══
  var paidHandled=false;
  function onPaid(){
    if(paidHandled)return;paidHandled=true;
    if(pollTimer)clearInterval(pollTimer);
    if(timerInterval)clearInterval(timerInterval);
    if(window._sqxSSE){try{window._sqxSSE.close()}catch(e){}}
    // Trigger backend buat credit (kalau belum)
    fetch('api/deposit.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'check',tx_id:txId})}).catch(function(){});
    var st=document.getElementById('payStatus');
    if(st){st.className='pay-status paid';st.textContent='Pembayaran Berhasil';}
    var pb=document.getElementById('payBody');
    if(pb){
      var rb=document.createElement('div');
      rb.style.cssText='padding:32px 20px;text-align:center';
      rb.innerHTML=
        '<style>'+
        '@keyframes depScalePop{0%{transform:scale(0);opacity:0}60%{transform:scale(1.1)}100%{transform:scale(1);opacity:1}}'+
        '@keyframes depDrawCheck{to{stroke-dashoffset:0}}'+
        '@keyframes depFadeUp{from{opacity:0;transform:translateY(6px)}to{opacity:1;transform:translateY(0)}}'+
        '.dep-ok-ring{width:68px;height:68px;border-radius:50%;background:rgba(74,222,128,.12);display:inline-flex;align-items:center;justify-content:center;animation:depScalePop .4s cubic-bezier(.2,.8,.3,1.2) both}'+
        '.dep-ok-check{stroke-dasharray:26;stroke-dashoffset:26;animation:depDrawCheck .35s .25s ease-out forwards}'+
        '.dep-ok-t1{animation:depFadeUp .35s .35s ease-out both}'+
        '.dep-ok-t2{animation:depFadeUp .35s .5s ease-out both}'+
        '</style>'+
        '<div class="dep-ok-ring"><svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="#4ade80" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><polyline class="dep-ok-check" points="5 12 10 17 19 8"/></svg></div>'+
        '<div class="dep-ok-t1" style="font-size:1.02rem;font-weight:700;color:#fff;margin-top:16px;letter-spacing:.2px">Deposit Berhasil</div>'+
        '<div class="dep-ok-t2" style="font-size:.78rem;color:#8a8a8a;margin-top:6px">Saldo sudah masuk</div>'+
        '<div style="width:44px;height:2px;background:var(--tint-2);border-radius:2px;overflow:hidden;margin:22px auto 0"><div id="redirBar" style="height:100%;width:0;background:#4ade80;transition:width 1.8s linear"></div></div>';
      pb.innerHTML='';pb.appendChild(rb);
      setTimeout(function(){var b=document.getElementById('redirBar');if(b)b.style.width='100%';},80);
    }
    setTimeout(function(){location.href='dashboard.php';},2000);
  }

  // ── 1. SSE direct ke SQX (server-push tiap 2 detik, paling cepat) ──
  try{
    var sseUrl='https://panel.squadonyx.biz.id/stream.php?tx_id='+encodeURIComponent(txId)+'&merchant_uid=<?=defined('SQX_MERCHANT')?SQX_MERCHANT:'';?>';
    window._sqxSSE=new EventSource(sseUrl);
    window._sqxSSE.onmessage=function(e){
      try{
        var data=JSON.parse(e.data);
        if(data.status==='paid'||data.status==='success'||data.code==='TX_PAID'){onPaid();}
        if(data.status==='expired'){if(window._sqxSSE)window._sqxSSE.close();}
      }catch(err){}
    };
    window._sqxSSE.onerror=function(){if(window._sqxSSE)window._sqxSSE.close();};
  }catch(e){}

  // ── 2. Fallback polling backend tiap 3s (jaga-jaga kalau SSE gagal) ──
  pollTimer=setInterval(function(){
    if(paidHandled)return;
    fetch('api/deposit.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'check',tx_id:txId})})
    .then(function(r){return r.json()}).then(function(res){
      if(res.status==='paid')onPaid();
    }).catch(function(){});
  },3000);
}

function copyAcc(){
  var el=document.getElementById('accNum');
  if(!el)return;
  navigator.clipboard.writeText(el.textContent).then(function(){showToast('Nomor disalin!')}).catch(function(){});
}

function closePay(){
  document.getElementById('payOverlay').classList.remove('open');
  if(pollTimer){clearInterval(pollTimer);pollTimer=null}
  if(timerInterval){clearInterval(timerInterval);timerInterval=null}
  // Clean up resume URL
  if(window.history&&history.replaceState){history.replaceState(null,'',location.pathname)}
}

// ── Resume pending deposit (dari ?resume=txId) ──
(function(){
  var params=new URLSearchParams(location.search);
  var resumeTx=params.get('resume');
  if(!resumeTx)return;
  fetch('api/deposit.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'resume',tx_id:resumeTx})})
  .then(function(r){return r.json()}).then(function(res){
    if(!res.ok){showToast(res.error||'Deposit tidak ditemukan');return}
    var dep=res.deposit;
    // Kalau udah paid (ke-reconcile sebelumnya) → redirect langsung
    if(dep.status==='paid'){
      showToast('Deposit berhasil! Saldo sudah masuk.');
      setTimeout(function(){location.href='dashboard.php'},1500);
      return;
    }
    var pd=dep.pay_data_parsed||{};
    showPayment({
      _resumed:true,
      tx_id:dep.tx_id,
      nominal:dep.nominal,
      pay_amount:dep.pay_amount,
      bonus:dep.bonus_amount||0,
      expires_at:dep.expires_at,
      method:dep.method,
      pay_type:dep.type,
      qr_url:pd.qr_url||null,
      qris_string:pd.qris_string||null,
      account_name:pd.account_name||null,
      account_number:pd.account_number||null,
      status:dep.status
    });
  }).catch(function(){});
})();

function showToast(m){
  var ov=document.createElement('div');ov.className='toast-overlay';
  var bx=document.createElement('div');bx.className='toast-box';
  var p=document.createElement('p');p.textContent=m;
  var btn=document.createElement('button');btn.textContent='Oke';
  btn.onclick=function(){bx.remove();ov.remove()};
  bx.appendChild(p);bx.appendChild(btn);
  document.body.appendChild(ov);document.body.appendChild(bx);
  ov.onclick=function(){bx.remove();ov.remove()}
}
// Auto show K info on first visit
if(!sessionStorage.getItem("k_info_seen")){openKinfo();sessionStorage.setItem("k_info_seen","1")}

// Auto-reconcile pending deposits — cek kalau ada yg sebenernya udah paid (callback gagal)
// Throttle 60 detik biar ga spam ke SquadOnyx
var lastReconcile=parseInt(sessionStorage.getItem('last_reconcile')||'0');
var shouldReconcile=(Date.now()-lastReconcile)>60000;
function loadPendingDeposits(){
  fetch('api/deposit.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'pending'})})
  .then(function(r){return r.json()}).then(function(d){
    if(!d.ok||!d.deposits||!d.deposits.length)return;
    var sec=document.getElementById('pendingSection');
    var list=document.getElementById('pendingList');
    var now=Date.now();var h='';
    d.deposits.forEach(function(dep){
      var exp=new Date(dep.expires_at.replace(' ','T')+'+07:00').getTime();
      var left=exp-now;
      var timeStr='Expired';
      if(left>0){
        var hh=Math.floor(left/3600000);
        var mm=Math.floor((left%3600000)/60000);
        var ss=Math.floor((left%60000)/1000);
        if(hh>0)timeStr=hh+'j '+mm+'m';
        else if(mm>0)timeStr=mm+'m '+String(ss).padStart(2,'0')+'s';
        else timeStr=ss+'s';
      }
      h+='<div class="pending-item">';
      h+='<div class="pi-info"><div class="pi-tx">'+dep.tx_id+'</div>';
      h+='<div class="pi-amt">Rp '+Number(dep.nominal).toLocaleString('id')+'</div>';
      h+='<div class="pi-method">'+(dep.method||'qris').toUpperCase()+(dep.bonus_amount>0?' • Bonus Rp '+Number(dep.bonus_amount).toLocaleString('id'):'')+'</div>';
      h+='<div class="pi-timer">'+timeStr+'</div>';
      h+='</div>';
      h+='<button class="pi-btn" onclick="resumeDeposit(\''+dep.tx_id+'\')">Lanjut</button>';
      h+='</div>';
    });
    list.innerHTML=h;
    sec.style.display='block';
  });
}

if(shouldReconcile){
  sessionStorage.setItem('last_reconcile',Date.now().toString());
  fetch('api/deposit.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'reconcile_mine'})})
  .then(function(r){return r.json()}).then(function(d){
    // Kalau ada yg baru ke-paid, reload supaya saldo user ke-update
    if(d&&d.summary&&d.summary.paid>0){
      showToast(d.summary.paid+' deposit ditemukan & saldo dikreditkan. Reloading...');
      setTimeout(function(){location.reload()},1500);
      return;
    }
    loadPendingDeposits();
  }).catch(function(){loadPendingDeposits()});
}else{
  loadPendingDeposits();
}

function resumeDeposit(txId){
  fetch('api/deposit.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'resume',tx_id:txId})})
  .then(function(r){return r.json()}).then(function(res){
    if(!res.ok){showToast(res.error||'Deposit tidak ditemukan');return}
    var dep=res.deposit;var pd=dep.pay_data_parsed||{};
    showPayment({
      _resumed:true,
      tx_id:dep.tx_id,nominal:dep.nominal,pay_amount:dep.pay_amount,bonus:dep.bonus_amount||0,
      expires_at:dep.expires_at,method:dep.method,pay_type:dep.type,
      qr_url:pd.qr_url||null,qris_string:pd.qris_string||null,
      account_name:pd.account_name||null,account_number:pd.account_number||null,status:dep.status
    });
  }).catch(function(){showToast('Gagal memuat deposit')});
}
</script>
<?php include 'includes/credit_notify.php'; ?>
</body>
</html>
