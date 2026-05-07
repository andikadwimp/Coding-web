<?php
require_once 'includes/config.php';
$isLoggedIn = (bool)getUid();  // guest-friendly: page accessible, action gated by JS
$sets=[];
try{$st=$db->query("SELECT `key`,`value` FROM settings");foreach($st->fetchAll() as $r)$sets[$r['key']]=$r['value'];}catch(Exception $e){}
$sn=$sets['site_name']??'Situs';
$greeting=trim($sets['cs_greeting']??'')?:'Halo! Ada yang bisa kami bantu?';
// CS avatar pake DiceBear auto — seed = site name biar konsisten per-brand
$csSeed=strtolower(preg_replace('/[^a-z0-9]/i','',$sn)).'-cs';
$csAvatar='https://api.dicebear.com/7.x/avataaars/svg?seed='.urlencode($csSeed).'&backgroundColor=0c2f2e,134a3a,1e1040,1a0f08,2d1b69,0a0614&radius=50';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php require_once 'pwa_head.php'; ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Live Chat - <?=$sn?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="theme.php?v=<?=time()?>">
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;-webkit-text-size-adjust:100%;text-size-adjust:100%}
html{font-size:16px!important;overflow:hidden!important;height:100%!important;min-height:0!important}
/* Override theme.php global body min-height (bikin cs_chat tingginya fix ke viewport) */
body{
  overflow:hidden!important;
  height:100dvh!important;
  min-height:0!important;
  max-height:100dvh!important;
  font-family:'Poppins',sans-serif;
  background:var(--bg);
  color:var(--t);
  display:flex!important;
  flex-direction:column!important;
  margin:0 auto!important;
  position:relative!important;
}

/* ═══ HEADER — avatar + name + status + action icons ═══ */
.cs-hdr{display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--nav-bg,var(--s));border-bottom:1px solid var(--bd);flex-shrink:0;position:relative;z-index:2}
.cs-back{width:32px;height:32px;background:none;border:none;color:var(--t2);cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.cs-back svg{width:22px;height:22px}
.cs-av{width:38px;height:38px;border-radius:50%;background:var(--pri);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:.95rem;flex-shrink:0;position:relative;overflow:hidden}
.cs-av img{width:100%;height:100%;object-fit:cover}
.cs-av::after{content:'';position:absolute;right:-1px;bottom:-1px;width:11px;height:11px;background:#22c55e;border:2px solid var(--nav-bg,var(--s));border-radius:50%}
.cs-info{flex:1;min-width:0}
.cs-info .cs-name{font-size:.95rem;font-weight:700;color:var(--t);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cs-info .cs-st{font-size:.68rem;color:#22c55e;font-weight:600;display:flex;align-items:center;gap:4px}
.cs-info .cs-st .dot{width:6px;height:6px;border-radius:50%;background:#22c55e;animation:pulse 1.6s ease-in-out infinite}
@keyframes pulse{0%,100%{opacity:1}50%{opacity:.35}}
.cs-hdr-act{display:flex;gap:2px}
.cs-hdr-act button{width:34px;height:34px;background:none;border:none;color:var(--t2);cursor:pointer;display:flex;align-items:center;justify-content:center;border-radius:50%}
.cs-hdr-act button:active{background:var(--tint-2)}
.cs-hdr-act button svg{width:18px;height:18px}

/* ═══ BODY — scrollable message area ═══ */
.cs-body{flex:1 1 auto;min-height:0;overflow-y:auto;overflow-x:hidden;padding:16px 14px 10px;display:flex;flex-direction:column;gap:2px;scroll-behavior:smooth;position:relative;-webkit-overflow-scrolling:touch}

/* Date separator */
.cs-date{align-self:center;background:rgba(var(--pri-rgb,34,197,94),.12);color:var(--t2);font-size:.62rem;font-weight:600;padding:4px 12px;border-radius:12px;margin:8px 0 6px}

/* Bubble wrapper (for timestamp alignment) */
.cs-row{display:flex;flex-direction:column;max-width:82%;margin-bottom:6px}
.cs-row.user{align-self:flex-end;align-items:flex-end}
.cs-row.bot,.cs-row.admin{align-self:flex-start;align-items:flex-start}

/* Message bubble */
.cs-msg{padding:9px 13px;border-radius:16px;font-size:.85rem;line-height:1.45;word-wrap:break-word;position:relative;animation:msgIn .22s ease}
@keyframes msgIn{from{opacity:0;transform:translateY(4px)}to{opacity:1;transform:translateY(0)}}
.cs-row.user .cs-msg{background:var(--pri);color:#fff;border-bottom-right-radius:4px}
.cs-row.bot .cs-msg,.cs-row.admin .cs-msg{background:var(--s);color:var(--t);border:1px solid var(--bd);border-bottom-left-radius:4px}
.cs-row.admin .cs-msg{border-color:rgba(34,197,94,.35)}

/* Sender label (admin only — bot tanpa label biar clean) */
.cs-sender{font-size:.6rem;font-weight:700;color:#22c55e;margin-bottom:4px;letter-spacing:.2px;text-transform:uppercase}

/* Text content */
.cs-txt{white-space:pre-wrap;word-wrap:break-word}

/* Inline image (petunjuk) */
.cs-img{margin-top:8px;border-radius:10px;overflow:hidden;max-width:240px;border:1px solid var(--bd)}
.cs-img img{width:100%;height:auto;display:block;cursor:zoom-in}

/* Time OUTSIDE bubble (like modern messengers) */
.cs-time{font-size:.58rem;color:var(--t3);font-weight:500;margin-top:3px;padding:0 4px;display:flex;align-items:center;gap:3px}
.cs-row.user .cs-time{padding-right:8px}
.cs-row.bot .cs-time,.cs-row.admin .cs-time{padding-left:8px}
.cs-time svg{width:11px;height:11px;color:var(--pri)}

/* Typing indicator (admin/bot sedang balas) */
.cs-typing{align-self:flex-start;padding:10px 14px;background:var(--s);border:1px solid var(--bd);border-radius:16px;border-bottom-left-radius:4px;display:none;gap:4px;margin-bottom:6px}
.cs-typing.show{display:flex}
.cs-typing span{width:7px;height:7px;border-radius:50%;background:var(--t3);animation:typBounce 1.2s infinite ease-in-out}
.cs-typing span:nth-child(2){animation-delay:.15s}
.cs-typing span:nth-child(3){animation-delay:.3s}
@keyframes typBounce{0%,60%,100%{transform:translateY(0);opacity:.5}30%{transform:translateY(-6px);opacity:1}}

/* Scroll-to-bottom FAB */
.cs-fab{position:absolute;right:12px;bottom:12px;width:38px;height:38px;border-radius:50%;background:var(--s);border:1px solid var(--bd);color:var(--t);display:none;align-items:center;justify-content:center;cursor:pointer;box-shadow:0 2px 8px rgba(0,0,0,.25);z-index:3}
.cs-fab.show{display:flex}
.cs-fab svg{width:18px;height:18px}
.cs-fab .unread{position:absolute;top:-5px;right:-5px;min-width:18px;height:18px;padding:0 5px;background:#ef4444;color:#fff;font-size:.6rem;font-weight:800;border-radius:9px;display:flex;align-items:center;justify-content:center}

/* ═══ QUICK BUTTONS — chip style ═══ */
.cs-btns-wrap{padding:6px 10px 4px;flex-shrink:0;border-top:1px solid var(--bd);background:var(--nav-bg,var(--s))}
.cs-btns-label{font-size:.62rem;color:var(--t3);font-weight:600;padding:4px 6px 6px;letter-spacing:.3px;text-transform:uppercase}
.cs-btns{display:flex;gap:6px;overflow-x:auto;padding:0 2px 6px;scrollbar-width:none}
.cs-btns::-webkit-scrollbar{display:none}
.cs-btn{flex:0 0 auto;padding:8px 14px;background:var(--bg);border:1.5px solid var(--bd);border-radius:18px;color:var(--t);font-family:inherit;font-size:.74rem;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:6px;line-height:1;white-space:nowrap;transition:border-color .15s}
.cs-btn:active{transform:scale(.96)}
.cs-btn:hover{border-color:var(--pri)}
.cs-btn svg{width:13px;height:13px;color:var(--pri);flex-shrink:0}

/* ═══ INPUT BAR ═══ */
.cs-input-wrap{padding:8px 10px 10px;background:var(--nav-bg,var(--s));border-top:1px solid var(--bd);display:flex;gap:6px;align-items:center;flex-shrink:0}
.cs-attach{width:38px;height:38px;background:none;border:none;color:var(--t2);cursor:pointer;display:flex;align-items:center;justify-content:center;border-radius:50%;flex-shrink:0}
.cs-attach:active{background:var(--tint-2)}
.cs-attach svg{width:20px;height:20px}
.cs-input-wrap input[type=text]{flex:1;padding:10px 14px;background:var(--bg);border:1.5px solid var(--bd);border-radius:20px;color:var(--t);font-family:inherit;font-size:.85rem;outline:none;min-width:0}
.cs-input-wrap input[type=text]:focus{border-color:var(--pri)}
.cs-send{width:40px;height:40px;background:var(--pri);border:none;border-radius:50%;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.cs-send:disabled{opacity:.5;cursor:not-allowed}
.cs-send svg{width:17px;height:17px;margin-left:-2px}

/* ═══ LIGHTBOX ═══ */
.cs-lb{position:fixed;inset:0;background:rgba(0,0,0,.92);z-index:9999;display:none;align-items:center;justify-content:center;padding:20px}
.cs-lb.show{display:flex}
.cs-lb img{max-width:100%;max-height:90vh;object-fit:contain}
.cs-lb-x{position:absolute;top:16px;right:16px;width:38px;height:38px;background:rgba(0,0,0,.6);border:none;border-radius:50%;color:#fff;cursor:pointer;font-size:1.5rem;line-height:1;display:flex;align-items:center;justify-content:center}
</style>
</head>
<body>

<div class="cs-hdr">
  <button class="cs-back" onclick="history.back()" aria-label="Back"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="15 18 9 12 15 6"/></svg></button>
  <div class="cs-av"><img src="<?=htmlspecialchars($csAvatar)?>" alt="CS"></div>
  <div class="cs-info">
    <div class="cs-name">Customer Service</div>
    <div class="cs-st"><span class="dot"></span>Online • Biasanya balas cepat</div>
  </div>
  <div class="cs-hdr-act">
    <button onclick="refreshChat()" aria-label="Refresh"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><polyline points="1 20 1 14 7 14"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg></button>
  </div>
</div>

<div class="cs-body" id="csBody">
  <button class="cs-fab" id="csFab" onclick="scrollBottom(true)">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>
    <span class="unread" id="csUnread" style="display:none">0</span>
  </button>
</div>

<div class="cs-btns-wrap" id="csBtnsWrap" style="display:none">
  <div class="cs-btns-label">Pilih topik</div>
  <div class="cs-btns" id="csButtons"></div>
</div>

<div class="cs-input-wrap">
  <button class="cs-attach" onclick="document.getElementById('csFile').click()" aria-label="Lampirkan"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg></button>
  <input type="file" id="csFile" accept="image/*" style="display:none" onchange="uploadImg(this)">
  <input type="text" id="csTxt" placeholder="Ketik pesan..." maxlength="2000" onkeydown="if(event.key==='Enter')sendText()">
  <button class="cs-send" id="csSendBtn" onclick="sendText()" aria-label="Kirim"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M3.4 20.4l17.45-7.48a1 1 0 000-1.84L3.4 3.6a1 1 0 00-1.4 1.01L4 11l9 1-9 1-2 6.39a1 1 0 001.4 1.01z"/></svg></button>
</div>

<div class="cs-lb" id="csLb" onclick="this.classList.remove('show')">
  <button class="cs-lb-x">&times;</button>
  <img id="csLbImg" src="" alt="">
</div>

<script>
var lastId=0;
var unreadCount=0;
var userScrolledUp=false;

function esc(t){var d=document.createElement('div');d.textContent=t||'';return d.innerHTML}
function fmtTime(s){if(!s)return '';var d=new Date(s.replace(' ','T'));var h=('0'+d.getHours()).slice(-2),m=('0'+d.getMinutes()).slice(-2);return h+':'+m}
function openImg(src){document.getElementById('csLbImg').src=src;document.getElementById('csLb').classList.add('show')}

function readCheck(sender){
  if(sender!=='user')return '';
  // Tick kecil (sent indicator) untuk pesan user
  return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>';
}

function addMsg(m, isNew){
  var b=document.getElementById('csBody');
  var row=document.createElement('div');
  row.className='cs-row '+m.sender;

  var txt=(m.message||'').replace(/^\[KLIK\] /,'');
  var senderLabel='';
  if(m.sender==='admin')senderLabel='<div class="cs-sender">Admin CS</div>';
  // Bot tanpa sender label — biar clean kayak WA bot
  var imgHtml='';
  if(m.image_url)imgHtml='<div class="cs-img"><img src="'+esc(m.image_url)+'" alt="gambar" loading="lazy" onclick="openImg(\''+esc(m.image_url)+'\')"></div>';

  var bubble='<div class="cs-msg">'+senderLabel+'<div class="cs-txt">'+esc(txt)+'</div>'+imgHtml+'</div>';
  var time='<div class="cs-time">'+fmtTime(m.created_at)+readCheck(m.sender)+'</div>';

  row.innerHTML=bubble+time;
  // Hapus fab dari dalam body dulu biar dia tetap di akhir
  var fab=document.getElementById('csFab');
  if(fab.parentNode===b)b.removeChild(fab);
  b.appendChild(row);
  b.appendChild(fab);

  if(m.id>lastId)lastId=m.id;

  if(isNew && userScrolledUp && m.sender!=='user'){
    // Admin/bot kirim pesan baru tapi user scroll ke atas → show unread badge
    unreadCount++;
    updateUnread();
  }else{
    scrollBottom();
  }
}

function showTyping(){
  var b=document.getElementById('csBody');
  var old=document.getElementById('csTyping');if(old)old.remove();
  var t=document.createElement('div');
  t.id='csTyping';
  t.className='cs-typing show';
  t.innerHTML='<span></span><span></span><span></span>';
  var fab=document.getElementById('csFab');
  if(fab.parentNode===b)b.removeChild(fab);
  b.appendChild(t);
  b.appendChild(fab);
  scrollBottom();
}
function hideTyping(){var t=document.getElementById('csTyping');if(t)t.remove()}

function scrollBottom(force){
  var b=document.getElementById('csBody');
  setTimeout(function(){
    b.scrollTop=b.scrollHeight;
    if(force){userScrolledUp=false;unreadCount=0;updateUnread()}
  },50);
}

function updateUnread(){
  var fab=document.getElementById('csFab');
  var badge=document.getElementById('csUnread');
  if(userScrolledUp){fab.classList.add('show')}else{fab.classList.remove('show')}
  if(unreadCount>0){badge.textContent=unreadCount;badge.style.display='flex'}else{badge.style.display='none'}
}

// Detect user scroll up
document.getElementById('csBody').addEventListener('scroll',function(){
  var b=this;
  var atBottom=(b.scrollHeight-b.scrollTop-b.clientHeight)<50;
  userScrolledUp=!atBottom;
  if(atBottom){unreadCount=0}
  updateUnread();
});

function loadHistory(){
  fetch('api/cs.php?action=history',{credentials:'same-origin'})
  .then(function(r){return r.json()}).then(function(d){
    if(!d.ok){if(window.handleAuthError&&handleAuthError(d))return;return;}
    var b=document.getElementById('csBody');
    // Clear (kecuali FAB)
    var fab=document.getElementById('csFab');
    b.innerHTML='';b.appendChild(fab);

    // Greeting bot
    var greetRow=document.createElement('div');
    greetRow.className='cs-row bot';
    var now=new Date();var h=('0'+now.getHours()).slice(-2),mn=('0'+now.getMinutes()).slice(-2);
    greetRow.innerHTML='<div class="cs-msg"><div class="cs-txt">'+esc(<?=json_encode($greeting)?>)+'</div></div><div class="cs-time">'+h+':'+mn+'</div>';
    b.insertBefore(greetRow,fab);

    (d.messages||[]).forEach(function(m){addMsg(m,false)});
    scrollBottom(true);
  });
}

function loadButtons(){
  fetch('api/cs.php?action=menu',{credentials:'same-origin'})
  .then(function(r){return r.json()}).then(function(d){
    if(!d.ok||!d.buttons||!d.buttons.length){document.getElementById('csBtnsWrap').style.display='none';return}
    var cont=document.getElementById('csButtons');
    var icons={
      'Deposit':'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/></svg>',
      'Withdraw':'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 2v20M17 5l-5-3-5 3M7 19l5 3 5-3"/></svg>',
      'Lupa Password':'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>',
      'Bonus & Promo':'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
      'Lainnya':'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>',
    };
    var dft='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><circle cx="12" cy="12" r="10"/><polyline points="9 12 11 14 15 10"/></svg>';
    var h='';
    d.buttons.forEach(function(b){
      var ic=icons[b.label]||dft;
      h+='<button class="cs-btn" onclick="clickBtn('+b.id+')">'+ic+'<span>'+esc(b.label)+'</span></button>';
    });
    cont.innerHTML=h;
    document.getElementById('csBtnsWrap').style.display='block';
  });
}

function clickBtn(id){
  fetch('api/cs.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'click_button',button_id:id})})
  .then(function(r){return r.json()}).then(function(d){
    if(!d.ok)return;
    var now=new Date().toISOString().replace('T',' ').substring(0,19);
    addMsg({id:lastId+1,sender:'user',message:d.label,created_at:now},true);
    showTyping();
    setTimeout(function(){
      hideTyping();
      addMsg({id:lastId+1,sender:'bot',message:d.reply,image_url:d.image,created_at:now},true);
    },650);
  });
}

function sendText(){
  var inp=document.getElementById('csTxt');
  var txt=inp.value.trim();
  if(!txt)return;
  var btn=document.getElementById('csSendBtn');btn.disabled=true;
  var now=new Date().toISOString().replace('T',' ').substring(0,19);
  // Optimistic: langsung tampil dulu
  addMsg({id:lastId+1,sender:'user',message:txt,created_at:now},true);
  inp.value='';
  showTyping();
  fetch('api/cs.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'send_text',message:txt})})
  .then(function(r){return r.json()}).then(function(d){
    btn.disabled=false;
    hideTyping();
    if(!d.ok){if(window.handleAuthError&&handleAuthError(d))return;return;}
    addMsg({id:lastId+1,sender:'bot',message:'Pesan Anda sudah diterima. Admin CS akan segera membalas. Terima kasih!',created_at:now},true);
  }).catch(function(){
    btn.disabled=false;
    hideTyping();
  });
}

function uploadImg(fi){
  if(!fi.files||!fi.files[0])return;
  var f=fi.files[0];
  if(f.size>5*1024*1024){alert('Maksimal 5MB');fi.value='';return}
  var fd=new FormData();fd.append('file',f);fd.append('action','upload_image');
  var btn=document.getElementById('csSendBtn');btn.disabled=true;
  showTyping();
  fetch('api/cs.php',{method:'POST',credentials:'same-origin',body:fd})
  .then(function(r){return r.json()}).then(function(d){
    btn.disabled=false;hideTyping();fi.value='';
    if(!d.ok){alert(d.error||'Upload gagal');return}
    var now=new Date().toISOString().replace('T',' ').substring(0,19);
    addMsg({id:lastId+1,sender:'user',message:'[Gambar]',image_url:d.url,created_at:now},true);
  }).catch(function(){btn.disabled=false;hideTyping();fi.value=''});
}

function poll(){
  fetch('api/cs.php?action=poll&last_id='+lastId,{credentials:'same-origin'})
  .then(function(r){return r.json()}).then(function(d){
    if(d.ok&&d.messages)d.messages.forEach(function(m){addMsg(m,true)});
  }).catch(function(){});
}
function refreshChat(){loadHistory();loadButtons();}

loadHistory();
loadButtons();
setInterval(poll,4000);
</script>
</body>
</html>
