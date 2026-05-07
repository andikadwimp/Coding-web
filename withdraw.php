<?php
require_once 'includes/config.php';
header('Content-Type: text/html; charset=UTF-8');
$isLoggedIn = (bool)getUid();  // guest-friendly: page accessible, action gated by JS

$uid=getUid();$user=null;$hasPin=false;$bankAcc=null;
if($uid){
  try{$u=$db->prepare("SELECT * FROM users WHERE id=?");$u->execute([$uid]);$user=$u->fetch();$hasPin=!empty($user['fund_pin']);}catch(Exception $e){}
  try{$ba=$db->prepare("SELECT * FROM user_banks WHERE user_id=? ORDER BY id DESC LIMIT 1");$ba->execute([$uid]);$bankAcc=$ba->fetch();}catch(Exception $e){}
}
$bal=intval($user['balance']??0);$balK=$bal/1000;
$sets=[];try{$st=$db->query("SELECT `key`,`value` FROM settings");foreach($st->fetchAll() as $r)$sets[$r['key']]=$r['value'];normSettings($sets);}catch(Exception $e){}
$sn=$sets['site_name']??'Situs';
?>
<!DOCTYPE html><html lang="id"><head>
<?php require_once dirname(__FILE__).'/pwa_head.php'; ?>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Penarikan - <?php echo $sn; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="theme.php?v=<?=time()?>">
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;-webkit-text-size-adjust:100%;text-size-adjust:100%}
html{font-size:16px!important;overflow-x:hidden}body{font-family:'Poppins',sans-serif;background:var(--bg);color:var(--t);min-height:100vh;overflow-x:hidden;max-width:100vw;padding-bottom:70px}
.hdr{display:flex;align-items:center;gap:10px;padding:20px 16px 16px}.hdr h1{font-size:1.3rem;font-weight:800}.hdr .h-icon{width:34px;height:34px;border-radius:50%;background:var(--sec);display:flex;align-items:center;justify-content:center}.hdr .h-icon svg{width:18px;height:18px}
.info{margin:0 16px 20px;padding:14px 16px;background:linear-gradient(135deg,rgba(var(--sec-rgb,56,189,248),.08),rgba(var(--sec-rgb,56,189,248),.03));border:1px solid var(--bd);border-radius:12px}.info p{font-size:.75rem;color:var(--t2);line-height:1.6}.info a{color:var(--sec);font-weight:700;text-decoration:none}
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
/* Amount */
.amt-card{margin:0 16px 16px;background:var(--s);border:1px solid var(--bd);border-radius:14px;padding:16px}
.amt-card h4{font-size:.88rem;font-weight:700;margin-bottom:12px;display:flex;justify-content:space-between;align-items:center}
.amt-card h4 span{font-size:.62rem;color:var(--t3);font-weight:400;background:var(--bg2);padding:4px 10px;border-radius:6px}
.quick{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:16px}
.qk{padding:14px 8px;text-align:center;background:linear-gradient(135deg,rgba(var(--sec-rgb,56,189,248),.08),rgba(var(--sec-rgb,56,189,248),.03));border:1.5px solid rgba(var(--sec-rgb,56,189,248),.3);border-radius:12px;font-size:.88rem;font-weight:800;color:var(--sec);cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.2),inset 0 1px 0 var(--tint-1);transition:all .15s}.qk:active{transform:scale(.95);background:linear-gradient(135deg,var(--sec),var(--sec-d,var(--sec-d)));color:#fff;border-color:var(--sec);box-shadow:0 0 16px rgba(var(--sec-rgb,56,189,248),.5)}
.amt-input{display:flex;align-items:center;background:var(--bg2);border:1px solid var(--bd);border-radius:10px;padding:0 12px}
.amt-input input{flex:1;background:none;border:none;color:var(--t);font-family:inherit;font-size:1rem;font-weight:700;padding:13px 0;outline:none}
.amt-input input::placeholder{color:var(--t3);font-weight:400}
.amt-input .k-u{font-size:.88rem;font-weight:800;color:var(--pri);margin-right:8px}
.amt-input .btn-max{padding:6px 12px;background:var(--bg);border:1px solid var(--bd);border-radius:6px;color:var(--t2);font-size:.68rem;font-weight:700;cursor:pointer;font-family:inherit}
/* Bank account */
.bank-sec{margin:0 16px 16px}
.bank-sec h4{font-size:.82rem;font-weight:700;margin-bottom:10px;display:flex;justify-content:space-between;align-items:center}
.bank-sec h4 a{font-size:.72rem;color:var(--sec);text-decoration:none;font-weight:700}
.bank-empty{background:var(--s);border:1px solid var(--bd);border-radius:14px;padding:24px;text-align:center}
.bank-empty p{font-size:.78rem;color:var(--t2);margin-bottom:14px}
.bank-empty button{padding:10px 20px;background:transparent;border:1px solid var(--sec);border-radius:8px;color:var(--sec);font-size:.78rem;font-weight:700;cursor:pointer;font-family:inherit}
.bank-info{background:var(--s);border:1px solid var(--bd);border-radius:14px;padding:16px;display:flex;align-items:center;gap:14px}
.bank-info .bi-icon{width:40px;height:40px;border-radius:8px;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden}
.bank-info .bi-text{flex:1}.bank-info .bi-text .bt-name{font-size:.82rem;font-weight:700}.bank-info .bi-text .bt-num{font-size:.72rem;color:var(--t3)}
/* Submit */
.submit{display:block;width:calc(100% - 32px);margin:20px 16px;padding:15px;text-align:center;font-size:.95rem;font-weight:800;color:#ffffff;background:linear-gradient(135deg,var(--sec) 0%,var(--sec-d) 100%);border:1.5px solid rgba(var(--sec-rgb),.5);border-radius:12px;cursor:pointer;font-family:inherit;box-shadow:0 0 16px rgba(var(--sec-rgb),.45),0 4px 14px rgba(var(--sec-rgb),.3);transition:all .2s;letter-spacing:.3px}
.submit:hover{box-shadow:0 0 22px rgba(var(--sec-rgb),.7),0 6px 18px rgba(var(--sec-rgb),.4);border-color:rgba(var(--sec-rgb),.8);transform:translateY(-1px)}
.submit:active{transform:translateY(0)}
/* Overlays */
.ov{position:fixed;inset:0;z-index:200;display:none;flex-direction:column;background:var(--bg)}.ov.open{display:flex}
.ov-hdr{display:flex;align-items:center;padding:14px 16px;flex-shrink:0}.ov-hdr button{width:32px;height:32px;background:none;border:none;color:var(--t2);cursor:pointer;display:flex;align-items:center;justify-content:center}.ov-hdr button svg{width:22px;height:22px}.ov-hdr h2{flex:1;text-align:center;font-size:1rem;font-weight:700}
.ov-body{flex:1;overflow-y:auto;padding:16px}
/* PIN boxes */
.pin-label{font-size:.82rem;font-weight:700;color:var(--pri);margin-bottom:10px}
.pin-row{display:flex;gap:8px;margin-bottom:20px}
.pin-box{flex:1;width:0;height:54px;background:var(--bg2);border:2px solid var(--sec);border-radius:10px;font-size:1.5rem;font-weight:800;text-align:center;color:var(--t);font-family:inherit;outline:none;caret-color:var(--sec);-webkit-appearance:none}
.pin-box:focus{border-color:var(--pri);background:rgba(var(--sec-rgb,56,189,248),.08)}
.pin-box::placeholder{color:var(--t3);font-size:1.5rem;font-weight:300}
.pin-warn{font-size:.72rem;color:var(--pri);margin-bottom:20px;line-height:1.5}
.pin-btn{display:block;width:100%;padding:15px;background:var(--sec);border:none;border-radius:12px;color:#fff;font-size:.95rem;font-weight:700;cursor:pointer;font-family:inherit}
/* Success */
.success-wrap{text-align:center;padding:60px 20px}
.success-wrap .check{width:80px;height:80px;border-radius:50%;background:#4ade80;display:flex;align-items:center;justify-content:center;margin:0 auto 20px}
.success-wrap .check svg{width:40px;height:40px;color:#fff}
.success-wrap h3{font-size:1.1rem;font-weight:700;margin-bottom:8px}
.success-wrap p{font-size:.78rem;color:var(--pri);line-height:1.5;margin-bottom:30px}
.success-wrap .btn-row{display:flex;gap:10px}
.success-wrap .btn-row a,.success-wrap .btn-row button{flex:1;padding:14px;border-radius:10px;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit;text-decoration:none;text-align:center;border:none}
.btn-dark{background:var(--bg2);color:var(--t);border:1px solid var(--bd)!important}
.btn-teal{background:var(--sec);color:#fff}
/* Add bank modal */
.modal{position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:300;display:none;align-items:center;justify-content:center}.modal.open{display:flex}
.modal-box{background:var(--s);border:1px solid var(--bd);border-radius:16px;padding:24px 20px;width:calc(100% - 40px);max-width:350px}
.modal-box h3{font-size:1rem;font-weight:700;margin-bottom:16px}
.fg{margin-bottom:14px}.fg label{font-size:.72rem;font-weight:600;color:var(--t2);margin-bottom:6px;display:block}
.fg select,.fg input{width:100%;padding:12px;background:var(--bg2);border:1px solid var(--bd);border-radius:10px;color:var(--t);font-family:inherit;font-size:.85rem;outline:none;-webkit-appearance:none}
.mth-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;margin-bottom:14px}
.mth-btn{padding:10px 4px;background:var(--bg2);border:1.5px solid var(--bd);border-radius:10px;text-align:center;font-size:.72rem;font-weight:700;color:var(--t2);cursor:pointer;font-family:inherit;transition:all .15s}
.mth-btn:active{transform:scale(.96)}
.mth-btn.on{border-color:var(--sec);background:rgba(var(--sec-rgb,56,189,248),.08);color:var(--t)}
.mbtn-row{display:flex;gap:8px}.mbtn{flex:1;padding:12px;border:none;border-radius:10px;font-size:.85rem;font-weight:700;cursor:pointer;font-family:inherit}.mbtn-pri{background:var(--sec);color:#fff}.mbtn-sec{background:var(--bg2);color:var(--t2);border:1px solid var(--bd)!important}
/* PIN verify popup */
.pin-popup{position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:300;display:none;align-items:center;justify-content:center}.pin-popup.open{display:flex}
.pp-box{background:var(--s);border:1px solid var(--bd);border-radius:16px;padding:24px 20px;width:calc(100% - 40px);max-width:320px;text-align:center}
.pp-box h3{font-size:.95rem;font-weight:700;margin-bottom:14px}
/* No-PIN popup */
.npin{position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:250;display:none;align-items:center;justify-content:center}.npin.open{display:flex}
.npin-box{background:var(--s);border:1px solid var(--bd);border-radius:16px;padding:20px;width:calc(100% - 40px);max-width:350px;text-align:center;overflow:hidden}
.npin-box img{width:100%;height:160px;object-fit:cover;border-radius:10px;margin-bottom:16px}
.npin-box p{font-size:.85rem;color:var(--t2);line-height:1.6;margin-bottom:20px}
.npin-box button{padding:14px 40px;background:var(--sec);border:none;border-radius:10px;color:#fff;font-size:.88rem;font-weight:700;cursor:pointer;font-family:inherit}
/* sec-label override removed; uses unified style above */
.wdh-item{display:flex;align-items:center;gap:12px;padding:13px 0;border-bottom:1px solid rgba(var(--sec-rgb,56,189,248),.08)}
.wdh-item:last-child{border:none}
.wdh-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.wdh-info{flex:1;min-width:0}
.wdh-name{font-size:.78rem;font-weight:700}
.wdh-sub{font-size:.62rem;color:var(--t3);margin-top:2px}
.wdh-right{text-align:right}
.wdh-amt{font-size:.82rem;font-weight:800;color:#ef4444}
.wdh-status{font-size:.6rem;font-weight:700;padding:2px 8px;border-radius:10px;margin-top:3px;display:inline-block}
.st-pending{background:rgba(251,191,36,.1);color:#fbbf24}
.st-approved{background:rgba(74,222,128,.1);color:#4ade80}
.st-rejected{background:rgba(239,68,68,.1);color:#ef4444}
/* Nav */
.bnav{position:fixed;bottom:0;left:0;right:0;z-index:100;display:flex;background:var(--nav-bg,#0a1628);padding:8px 0 env(safe-area-inset-bottom,6px);overflow:hidden;border-radius:14px 14px 0 0;box-shadow:0 -4px 20px rgba(0,0,0,.5);border-top:1px solid rgba(var(--sec-rgb,56,189,248),.2)}
.bnav::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent 2%,var(--pri) 15%,var(--sec-l, var(--pri-l)) 50%,var(--pri) 85%,transparent 98%)}
.bnav-i{flex:1;display:flex;flex-direction:column;align-items:center;gap:2px;padding:6px 0;color:var(--t3);font-size:.6rem;font-weight:600;text-decoration:none}.bnav-i.active{color:var(--sec)}.bnav-i img{width:36px;height:36px;object-fit:contain}
.to-item{background:rgba(var(--sec-rgb,56,189,248),.06);border:1px solid var(--bd);border-radius:10px;padding:14px;margin-bottom:10px}
.to-item h5{font-size:.78rem;font-weight:700;margin-bottom:4px;color:var(--pri)}
.to-item .to-info{font-size:.65rem;color:var(--t3);margin-bottom:10px;line-height:1.5}
.to-item .to-bar{height:8px;background:var(--tint-2);border-radius:4px;overflow:hidden;margin-bottom:6px}
.to-item .to-bar .to-fill{height:100%;border-radius:4px;background:linear-gradient(90deg,var(--sec),#4ade80);transition:width .3s}
.to-item .to-nums{display:flex;justify-content:space-between;font-size:.62rem;color:var(--t3)}
.to-item .to-nums b{color:var(--sec)}
.to-item .to-remain{font-size:.72rem;font-weight:700;color:#ef4444;text-align:center;margin-top:8px}
.toast-overlay{position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9998}.toast-box{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:var(--s);border:1px solid var(--bd);border-radius:14px;padding:24px 30px;text-align:center;z-index:9999;max-width:280px}.toast-box p{font-size:.85rem;font-weight:600;margin-bottom:16px}.toast-box button{padding:8px 30px;background:var(--sec);border:none;border-radius:8px;color:#fff;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit}
</style></head><body>

<div class="hdr"><h1>Penarikan</h1><div class="h-icon"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="M2 10h20"/></svg></div></div>
<div class="info"><p>Jika Anda memiliki pertanyaan atau masalah, silakan hubungi layanan pelanggan. Terima kasih!</p><p style="margin-top:6px"><svg viewBox="0 0 24 24" fill="none" stroke="var(--sec)" stroke-width="2" width="14" height="14" style="display:inline-block;vertical-align:middle"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6A19.79 19.79 0 012.12 4.11 2 2 0 014.11 2h3"/></svg> <a href="cs.php">Layanan Pelanggan Online.</a></p></div>

<div class="amt-card">
<h4>Tarik Saldo <span>1K = 1,000.00 Rp</span></h4>
<div class="quick">
<div class="qk" onclick="setAmt(50)">50K</div>
<div class="qk" onclick="setAmt(100)">100K</div>
<div class="qk" onclick="setAmt(200)">200K</div>
<div class="qk" onclick="setAmt(500)">500K</div>
<div class="qk" onclick="setAmt(1000)">1,000K</div>
<div class="qk" onclick="setAmt(5000)">5,000K</div>
</div>
<div class="amt-input">
<input type="tel" id="wdAmt" placeholder="50 - 50,000" inputmode="numeric" pattern="[0-9]*" autocomplete="off">
<div class="k-u">K</div>
<button class="btn-max" onclick="setAmt(<?php echo floor($balK); ?>)">Maksimal</button>
</div>
</div>

<div class="bank-sec">
<h4>Rekening penarikan <a href="#" onclick="openAddBank();return false">+ Tambah</a></h4>
<div id="bankDisplay">
<?php if($bankAcc): ?>
<div class="bank-info" id="bankItem_<?php echo $bankAcc['id']; ?>">
<div class="bi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" style="width:40px;height:40px;color:var(--pri)"><path d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11M20 10v11M8 14v3M12 14v3M16 14v3"/></svg></div>
<div class="bi-text"><div class="bt-name"><?php echo $bankAcc['bank_name'].' - '.$bankAcc['acc_name']; ?></div><div class="bt-num"><?php echo $bankAcc['acc_number']; ?></div></div>
<button onclick="delBank(<?php echo $bankAcc['id']; ?>)" style="background:none;border:none;color:#ef4444;cursor:pointer;padding:4px;display:flex;margin-left:auto" title="Hapus"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg></button>
</div>
<?php else: ?>
<div class="bank-empty" id="bankEmpty"><p>Harap tambahkan akun bank untuk penarikan</p><button onclick="openAddBank()">+ tambahkan akun</button></div>
<?php endif; ?>
</div>
</div>

<button class="submit" onclick="startWithdraw()">Ambil uang segera</button>

<!-- No PIN Popup -->
<div class="npin" id="noPinPopup">
<div class="npin-box">
<div style="height:140px;background:linear-gradient(135deg,#1a1a2e,#2d2d44);border-radius:10px;margin-bottom:16px;display:flex;align-items:center;justify-content:center"><svg viewBox="0 0 24 24" fill="none" stroke="var(--pri)" stroke-width="1.5" width="60" height="60"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/><circle cx="12" cy="16" r="1"/></svg></div>
<p>Demi keamanan dana Anda, silakan buat kata sandi untuk dana Anda terlebih dahulu!</p>
<button onclick="openSetPin()">Konfirmasi</button>
</div>
</div>

<!-- Set PIN Overlay -->
<div class="ov" id="setPinOv">
<div class="ov-hdr"><button onclick="closePinOv()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button><h2>Penarikan Kata sandi</h2><div style="width:32px"></div></div>
<div class="ov-body">
<h3 style="font-size:1rem;font-weight:700;margin-bottom:20px;color:var(--t)">Pengaturan Penarikan Kata sandi</h3>
<div class="pin-label">Baru Penarikan Kata sandi</div>
<div class="pin-row" id="pinRow1"></div>
<div class="pin-label">Konfirmasi Baru Kata sandi</div>
<div class="pin-row" id="pinRow2"></div>
<div class="pin-warn">Anda baru pertama kali melakukan penarikan, Anda perlu menyetel kata sandi penarikan terlebih dahulu</div>
<button class="pin-btn" onclick="submitSetPin()">Ambil uang segera</button>
</div>
</div>

<!-- PIN Success Overlay -->
<div class="ov" id="pinSuccessOv">
<div class="ov-hdr"><button onclick="document.getElementById('pinSuccessOv').classList.remove('open')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button><h2>kode pendanaan</h2><div style="width:32px"></div></div>
<div class="ov-body">
<div class="success-wrap">
<div class="check"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><polyline points="20 6 9 17 4 12"/></svg></div>
<h3>Berhasil menyiapkan dengan sukses</h3>
<p>Pengaturan kata sandi baru selesai, harap ingat kata sandi Anda</p>
<div class="btn-row">
<a href="dashboard.php" class="btn-dark">Kembali ke halaman beranda</a>
<button class="btn-teal" onclick="document.getElementById('pinSuccessOv').classList.remove('open')">Terus tarik uang tunai Anda.</button>
</div>
</div>
</div>
</div>

<!-- Verify PIN Popup -->
<div class="pin-popup" id="verifyPinPopup">
<div class="pp-box">
<h3>Masukkan Sandi Dana</h3>
<div class="pin-row" id="pinRowV" style="margin-bottom:14px"></div>
<div class="mbtn-row"><button class="mbtn mbtn-sec" onclick="document.getElementById('verifyPinPopup').classList.remove('open')">Batal</button><button class="mbtn mbtn-pri" onclick="verifyAndWithdraw()">Konfirmasi</button></div>
</div>
</div>

<!-- Add Bank Modal -->
<div class="modal" id="addBankModal">
<div class="modal-box">
<h3>Tambah Rekening</h3>
<div class="fg"><label>Metode Penarikan</label></div>
<div class="mth-grid" id="abMethodGrid">
<div class="mth-btn" onclick="pickAbMethod(this,'DANA')">DANA</div>
<div class="mth-btn" onclick="pickAbMethod(this,'OVO')">OVO</div>
<div class="mth-btn" onclick="pickAbMethod(this,'GoPay')">GoPay</div>
<div class="mth-btn" onclick="pickAbMethod(this,'ShopeePay')">ShopeePay</div>
<div class="mth-btn" onclick="pickAbMethod(this,'LinkAja')">LinkAja</div>
<div class="mth-btn" onclick="pickAbMethod(this,'BCA')">BCA</div>
<div class="mth-btn" onclick="pickAbMethod(this,'BRI')">BRI</div>
<div class="mth-btn" onclick="pickAbMethod(this,'BNI')">BNI</div>
<div class="mth-btn" onclick="pickAbMethod(this,'Mandiri')">Mandiri</div>
<div class="mth-btn" onclick="pickAbMethod(this,'CIMB')">CIMB</div>
<div class="mth-btn" onclick="pickAbMethod(this,'Permata')">Permata</div>
</div>
<input type="hidden" id="abMethod" value="">
<div class="fg"><label>Nama Pemilik</label><input type="text" id="abName" placeholder="Nama sesuai rekening"></div>
<div class="fg"><label>Nomor Rekening / E-Wallet</label><input type="text" id="abNum" placeholder="Nomor rekening" inputmode="numeric"></div>
<div class="mbtn-row"><button class="mbtn mbtn-sec" onclick="document.getElementById('addBankModal').classList.remove('open')">Batal</button><button class="mbtn mbtn-pri" onclick="saveBank()">Simpan</button></div>
</div>
</div>

<!-- Turnover Popup -->
<div class="modal" id="toPopup">
<div class="modal-box" style="max-width:340px">
<h3 style="text-align:center;margin-bottom:4px">Target Turnover Belum Tercapai</h3>
<p style="font-size:.72rem;color:var(--t3);text-align:center;margin-bottom:16px">Selesaikan target taruhan untuk dapat melakukan penarikan</p>
<div id="toList"></div>
<div class="mbtn-row" style="margin-top:16px"><button class="mbtn mbtn-pri" onclick="document.getElementById('toPopup').classList.remove('open')" style="width:100%">Mengerti</button></div>
</div>
</div>

<!-- Riwayat Penarikan Section -->
<div style="padding:0 16px;margin-bottom:16px">
<div class="sec-label" style="padding:0;margin-bottom:10px">Riwayat Penarikan</div>
<div id="wdHistList"><div style="text-align:center;padding:20px;color:var(--t3);font-size:.75rem">Memuat...</div></div>
</div>

<!-- WD Success Overlay -->
<div id="wdSuccessOv" style="position:fixed;inset:0;background:var(--bg);z-index:300;display:none;flex-direction:column;align-items:center;justify-content:center;padding:30px">
<div style="text-align:center;max-width:280px">
  <div style="width:80px;height:80px;border-radius:50%;background:rgba(74,222,128,.15);border:2px solid #4ade80;display:flex;align-items:center;justify-content:center;margin:0 auto 20px">
    <svg viewBox="0 0 24 24" fill="none" stroke="#4ade80" stroke-width="2.5" width="40" height="40"><polyline points="20 6 9 17 4 12"/></svg>
  </div>
  <h3 style="font-size:1.1rem;font-weight:800;margin-bottom:10px">Penarikan Diajukan!</h3>
  <p id="wdSuccessAmt" style="font-size:.82rem;color:var(--t2);line-height:1.6;margin-bottom:8px"></p>
  <p style="font-size:.72rem;color:var(--t3);margin-bottom:24px">Saldo baru Anda: <span id="wdNewBal" style="color:var(--sec);font-weight:700"></span></p>
  <div style="display:flex;gap:10px">
    <button onclick="closeWdSuccess()" style="flex:1;padding:13px;background:var(--s);border:1px solid var(--bd);border-radius:10px;color:var(--t);font-size:.85rem;font-weight:700;cursor:pointer;font-family:inherit">Tutup</button>
    <a href="profil.php" style="flex:1;padding:13px;background:var(--sec);border:none;border-radius:10px;color:#fff;font-size:.85rem;font-weight:700;cursor:pointer;font-family:inherit;text-decoration:none;text-align:center;display:block">Profil</a>
  </div>
</div>
</div>

<?php echo renderBnav($db,"deposit", $isLoggedIn); ?>

<script>
var HAS_PIN=<?php echo $hasPin?'true':'false'; ?>;
var HAS_BANK=<?php echo $bankAcc?'true':'false'; ?>;
var BAL_K=<?php echo $balK; ?>;

function setAmt(k){document.getElementById('wdAmt').value=k}

// PIN input boxes
function makePinBoxes(id,count){
  var h='';for(var i=0;i<count;i++)h+='<input class="pin-box" type="password" maxlength="1" inputmode="numeric" placeholder="_" oninput="pinNext(this)" onkeydown="pinBack(event,this)">';
  document.getElementById(id).innerHTML=h;
}
function pinNext(el){if(el.value&&el.nextElementSibling)el.nextElementSibling.focus()}
function pinBack(e,el){if(e.key==='Backspace'&&!el.value&&el.previousElementSibling){el.previousElementSibling.focus();el.previousElementSibling.value=''}}
function getPinVal(id){var pins=document.querySelectorAll('#'+id+' .pin-box');var v='';pins.forEach(function(p){v+=p.value});return v}

makePinBoxes('pinRow1',6);makePinBoxes('pinRow2',6);makePinBoxes('pinRowV',6);
if(!HAS_PIN){document.getElementById('noPinPopup').classList.add('open')}

// No PIN flow
function startWithdraw(){
  if(typeof requireLogin==='function'&&!requireLogin('withdraw'))return;
  var k=parseFloat(document.getElementById('wdAmt').value);
  if(!k||k<50)return showToast('Minimum penarikan Rp 50.000 (50K)');
  if(k>BAL_K)return showToast('Saldo tidak cukup');
  if(!HAS_BANK)return showToast('Tambahkan rekening penarikan terlebih dahulu');
  if(!HAS_PIN){document.getElementById('noPinPopup').classList.add('open');return}
  // Check turnover requirement
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'check_turnover'})})
  .then(function(r){return r.json()}).then(function(d){
    if(!d.ok)return showToast(d.error||'Error');
    if(!d.can_withdraw&&d.pending&&d.pending.length>0){
      var s=d.summary||{total_target:0,total_done:0,remaining:0,pct:0,count:d.pending.length};
      var h='';
      // Summary progress (cumulative)
      h+='<div class="to-item">';
      h+='<h5>Turnover Wajib ('+s.count+' deposit)</h5>';
      h+='<div class="to-info">Total taruhan valid sejak deposit terlama</div>';
      h+='<div class="to-bar"><div class="to-fill" style="width:'+s.pct+'%"></div></div>';
      h+='<div class="to-nums"><span>'+Number(s.total_done/1000).toFixed(2)+'K</span><b>'+Number(s.total_target/1000).toFixed(2)+'K</b></div>';
      h+='<div class="to-remain">Kurang '+Number(s.remaining/1000).toFixed(2)+'K lagi</div>';
      h+='</div>';
      // List deposit kontributor (ringkas)
      h+='<div style="margin-top:10px;font-size:.68rem;color:var(--t3);font-weight:600">Deposit yang belum clear TO:</div>';
      d.pending.forEach(function(p){
        var depTxt=p.bonus?'Rp '+Number(p.deposit).toLocaleString('id')+' + Bonus '+Number(p.bonus).toLocaleString('id'):'Rp '+Number(p.deposit).toLocaleString('id');
        h+='<div style="display:flex;justify-content:space-between;padding:8px 10px;background:var(--bg2);border:1px solid var(--bd);border-radius:8px;margin-top:6px;font-size:.7rem">';
        h+='<span>'+p.bonus_name+'</span>';
        h+='<b style="color:var(--pri)">'+Number(p.target).toLocaleString('id')+' ('+p.turnover_x+'x)</b>';
        h+='</div>';
      });
      document.getElementById('toList').innerHTML=h;
      document.getElementById('toPopup').classList.add('open');
      return;
    }
    // OK - show PIN
    document.getElementById('verifyPinPopup').classList.add('open');
    document.querySelectorAll('#pinRowV .pin-box').forEach(function(p){p.value=''});
    document.querySelector('#pinRowV .pin-box').focus();
  }).catch(function(){showToast('Gagal mengecek status')});
}

function openSetPin(){
  document.getElementById('noPinPopup').classList.remove('open');
  document.getElementById('setPinOv').classList.add('open');
  document.querySelectorAll('#pinRow1 .pin-box, #pinRow2 .pin-box').forEach(function(p){p.value=''});
  document.querySelector('#pinRow1 .pin-box').focus();
}
function closePinOv(){document.getElementById('setPinOv').classList.remove('open')}

function submitSetPin(){
  var p1=getPinVal('pinRow1'),p2=getPinVal('pinRow2');
  if(p1.length!==6)return showToast('PIN harus 6 angka');
  if(p1!==p2)return showToast('Konfirmasi PIN tidak cocok');
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'set_fund_pin',old_pin:'',new_pin:p1})})
  .then(function(r){return r.json()}).then(function(d){
    if(d.ok){HAS_PIN=true;closePinOv();document.getElementById('pinSuccessOv').classList.add('open')}
    else showToast(d.error||'Gagal');
  });
}

function verifyAndWithdraw(){
  var pin=getPinVal('pinRowV');
  if(pin.length!==6)return showToast('Masukkan 6 angka PIN');
  var k=parseFloat(document.getElementById('wdAmt').value);
  if(!k||k<50)return showToast('Minimal penarikan Rp 50.000 (50K)');
  var nominal=Math.round(k*1000);
  var btn=document.querySelector('#verifyPinPopup .mbtn-pri');
  if(btn){btn.disabled=true;btn.textContent='Memproses...';}
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'withdraw',amount:nominal,pin:pin})})
  .then(function(r){return r.json()}).then(function(d){
    if(btn){btn.disabled=false;btn.textContent='Konfirmasi';}
    if(d.ok){
      document.getElementById('verifyPinPopup').classList.remove('open');
      document.querySelectorAll('#pinRowV .pin-box').forEach(function(p){p.value=''});
      showWdSuccess(nominal,d.balance);
      loadWdHistory();
    } else showToast(d.error||'Gagal');
  }).catch(function(){
    if(btn){btn.disabled=false;btn.textContent='Konfirmasi';}
    showToast('Gagal terhubung ke server');
  });
}

// Add bank
function openAddBank(){document.getElementById('addBankModal').classList.add('open')}
function pickAbMethod(el,val){
  document.querySelectorAll('.mth-btn').forEach(function(b){b.classList.remove('on')});
  el.classList.add('on');
  document.getElementById('abMethod').value=val;
}
function saveBank(){
  var m=document.getElementById('abMethod').value,n=document.getElementById('abName').value.trim(),num=document.getElementById('abNum').value.trim();
  if(!m)return showToast('Pilih metode');if(!n)return showToast('Masukkan nama');if(!num)return showToast('Masukkan nomor');
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'save_bank',bank_name:m,acc_name:n,acc_number:num})})
  .then(function(r){return r.json()}).then(function(d){
    if(d.ok){
      document.getElementById('addBankModal').classList.remove('open');
      document.getElementById('abMethod').value='';
      document.getElementById('abName').value='';
      document.getElementById('abNum').value='';
      HAS_BANK=true;
      refreshBankList();
      showToast('Rekening berhasil ditambahkan');
    }else showToast(d.error||'Gagal');
  });
}

function refreshBankList(){
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'get_banks'})})
  .then(function(r){return r.json()}).then(function(d){
    if(!d.ok)return;
    var banks=d.banks||[];
    HAS_BANK=banks.length>0;
    if(!banks.length){
      document.getElementById('bankDisplay').innerHTML='<div class="bank-empty" id="bankEmpty"><p>Harap tambahkan akun bank untuk penarikan</p><button onclick="openAddBank()">+ tambahkan akun</button></div>';
      return;
    }
    var h='';
    banks.forEach(function(b){
      var abbr='';
      h+='<div class="bank-info" id="bankItem_'+b.id+'" style="margin-bottom:8px">';
      h+='<div class="bi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" style="width:40px;height:40px;color:var(--pri)"><path d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11M20 10v11M8 14v3M12 14v3M16 14v3"/></svg></div>';
      h+='<div class="bi-text"><div class="bt-name">'+escW(b.bank_name)+' - '+escW(b.acc_name)+'</div><div class="bt-num">'+escW(b.acc_number)+'</div></div>';
      h+='<button onclick="delBank('+b.id+')" style="background:none;border:none;color:#ef4444;cursor:pointer;padding:4px;display:flex;margin-left:auto" title="Hapus"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 01-2 2H8a2 2 0 01-2-2L5 6"/><path d="M10 11v6M14 11v6"/></svg></button>';
      h+='</div>';
    });
    document.getElementById('bankDisplay').innerHTML=h;
  }).catch(function(){});
}

function delBank(id){
  if(!confirm('Hapus rekening ini?'))return;
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'delete_bank',id:id})})
  .then(function(r){return r.json()}).then(function(d){
    if(d.ok){refreshBankList();showToast('Rekening dihapus');}
    else showToast(d.error||'Gagal');
  });
}

// Load banks on start
refreshBankList();

function showWdSuccess(nominal,newBalance){
  document.getElementById('wdSuccessAmt').textContent='Penarikan Rp '+Number(nominal).toLocaleString('id')+' berhasil diajukan. Admin akan memproses dalam 1×24 jam.';
  document.getElementById('wdNewBal').textContent=Number(newBalance/1000).toFixed(2)+'K';
  document.getElementById('wdSuccessOv').style.display='flex';
  // Reset form
  document.getElementById('wdAmt').value='';
  BAL_K=newBalance/1000;
}
function closeWdSuccess(){document.getElementById('wdSuccessOv').style.display='none'}

function loadWdHistory(){
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'withdraw_history'})})
  .then(function(r){return r.json()}).then(function(d){
    var el=document.getElementById('wdHistList');
    if(!el)return;
    if(!d.ok||!d.withdrawals.length){
      el.innerHTML='<div style="text-align:center;padding:20px;color:var(--t3);font-size:.75rem">Belum ada riwayat penarikan</div>';
      return;
    }
    var h='';
    d.withdrawals.forEach(function(w){
      var sc=w.status==='approved'?'st-approved':w.status==='rejected'?'st-rejected':'st-pending';
      var sl=w.status==='approved'?'Disetujui':w.status==='rejected'?'Ditolak':'Menunggu';
      var dt=w.created_at?w.created_at.substring(0,16).replace('T',' '):'-';
      var bankAbbr='';
      h+='<div class="wdh-item">';
      h+='<div class="wdh-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" style="width:36px;height:36px;color:var(--pri)"><path d="M3 21h18M3 10h18M5 6l7-3 7 3M4 10v11M20 10v11M8 14v3M12 14v3M16 14v3"/></svg></div>';
      h+='<div class="wdh-info"><div class="wdh-name">'+escW(w.bank_name)+' · '+escW(w.acc_name)+'</div><div class="wdh-sub">'+escW(w.acc_number)+' · '+dt+'</div>';
      if(w.admin_note&&w.status==='rejected')h+='<div class="wdh-sub" style="color:#ef4444">'+escW(w.admin_note)+'</div>';
      h+='</div>';
      h+='<div class="wdh-right"><div class="wdh-amt">-Rp '+Number(w.amount).toLocaleString('id')+'</div><span class="wdh-status '+sc+'">'+sl+'</span></div>';
      h+='</div>';
    });
    el.innerHTML=h;
  }).catch(function(){});
}
function escW(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

// Load history on page load
loadWdHistory();

function showToast(m){var ov=document.createElement('div');ov.className='toast-overlay';var bx=document.createElement('div');bx.className='toast-box';var p=document.createElement('p');p.textContent=m;var btn=document.createElement('button');btn.textContent='Oke';btn.onclick=function(){bx.remove();ov.remove()};bx.appendChild(p);bx.appendChild(btn);document.body.appendChild(ov);document.body.appendChild(bx);ov.onclick=function(){bx.remove();ov.remove()}}
</script>
<?php include 'includes/credit_notify.php'; ?>
</body></html>
