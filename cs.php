<?php
require_once 'includes/config.php';
header('Content-Type: text/html; charset=UTF-8');
$isLoggedIn = (bool)getUid();  // guest-friendly: page accessible, action gated by JS

$sets=[];
try{$st=$db->query("SELECT `key`,`value` FROM settings");foreach($st->fetchAll() as $r)$sets[$r['key']]=$r['value'];normSettings($sets);}catch(Exception $e){}
$sn=$sets['site_name']??'Situs';
try{ensureDailyRedeem($db);}catch(Exception $e){}
$uid=getUid();
$notifCount=0;$blogCount=0;
try{
  if($uid){$nc=$db->prepare("SELECT COUNT(*) FROM memos WHERE (type='all' OR (type='target' AND to_user_id=?)) AND is_read=0");$nc->execute([$uid]);$notifCount=$nc->fetchColumn();}
  else{$notifCount=$db->query("SELECT COUNT(*) FROM memos WHERE type='all'")->fetchColumn();}
  $blogCount=$db->query("SELECT COUNT(*) FROM memos WHERE type='blog'")->fetchColumn();
}catch(Exception $e){}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<?php require_once dirname(__FILE__).'/pwa_head.php'; ?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Pusat Berita - <?php echo $sn; ?></title>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="theme.php?v=<?=time()?>">
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;-webkit-text-size-adjust:100%;text-size-adjust:100%}
html{font-size:16px!important;overflow-x:hidden}
body{overflow-x:hidden;max-width:100vw;font-family:'Poppins',sans-serif;background:var(--bg);color:var(--t);min-height:100vh}
.hdr{display:flex;align-items:center;padding:16px;position:sticky;top:0;z-index:10;background:var(--bg)}
.hdr button{width:32px;height:32px;display:flex;align-items:center;justify-content:center;background:none;border:none;color:var(--t2);cursor:pointer}
.hdr button svg{width:22px;height:22px}
.hdr h1{flex:1;text-align:center;font-size:1.1rem;font-weight:700}
.tabs{display:flex;padding:0 12px;border-bottom:1px solid rgba(var(--sec-rgb,56,189,248),.25)}
.tab-i{flex:1;text-align:center;padding:13px 6px;font-size:.78rem;font-weight:700;color:var(--t3);cursor:pointer;position:relative;display:flex;align-items:center;justify-content:center;gap:5px;white-space:nowrap}
.tab-i.on{color:var(--sec)}
.tab-i.on::after{content:'';position:absolute;bottom:-1px;left:10px;right:10px;height:3px;background:var(--sec);border-radius:2px}
.tab-i .badge{background:#ef4444;color:#fff;font-size:.55rem;font-weight:800;padding:2px 6px;border-radius:10px;min-width:18px;text-align:center}
.panel{display:none;padding:0 0 30px}.panel.on{display:block}
.hero{padding:20px 16px 16px;background:linear-gradient(135deg,rgba(var(--sec-rgb,56,189,248),.06),rgba(var(--sec-rgb,56,189,248),.02));position:relative;overflow:hidden}
.hero-top{display:flex;align-items:center;gap:14px;margin-bottom:16px}
.hero img{width:120px;height:auto;flex-shrink:0}
.hero-txt h2{font-size:1.05rem;font-weight:700;line-height:1.3}
.hero-txt h2 span{color:var(--pri)}
.hero-txt p{font-size:.78rem;color:var(--t2);margin-top:6px;line-height:1.5}
.hero-btn{display:block;padding:14px;text-align:center;font-size:.88rem;font-weight:700;color:#ffffff;background:linear-gradient(135deg,var(--sec) 0%,var(--sec-d) 100%);border:1.5px solid rgba(var(--sec-rgb),.5);box-shadow:0 0 14px rgba(var(--sec-rgb),.4);border-radius:10px;text-decoration:none;cursor:pointer;font-family:inherit}
.cards{padding:0 16px;display:flex;flex-direction:column;gap:12px}
.card{display:flex;align-items:center;gap:14px;padding:18px 16px;background:rgba(var(--sec-rgb,56,189,248),.06);border:1px solid rgba(var(--sec-rgb,56,189,248),.2);border-radius:12px}
.card-icon{width:52px;height:52px;flex-shrink:0}.card-icon svg{width:52px;height:52px}
.card-info{flex:1;min-width:0}.card-info h3{font-size:.88rem;font-weight:700;line-height:1.3}.card-info p{font-size:.68rem;color:var(--t3);margin-top:2px}
.card-btn{padding:10px 14px;font-size:.68rem;font-weight:700;color:var(--sec);background:rgba(var(--sec-rgb,56,189,248),.12);border:1px solid rgba(var(--sec-rgb,56,189,248),.3);border-radius:8px;text-decoration:none;white-space:nowrap;flex-shrink:0}
.mark-all{display:flex;justify-content:center;padding:12px 16px}
.mark-all button{display:flex;align-items:center;gap:6px;padding:10px 20px;border-radius:20px;background:rgba(var(--sec-rgb,56,189,248),.1);border:1px solid rgba(var(--sec-rgb,56,189,248),.3);color:var(--sec);font-size:.78rem;font-weight:700;cursor:pointer;font-family:inherit}
.nlist{padding:0 16px;display:flex;flex-direction:column;gap:10px}
.nitem{padding:16px;background:rgba(var(--sec-rgb,56,189,248),.05);border:1px solid rgba(var(--sec-rgb,56,189,248),.15);border-radius:12px;cursor:pointer}
.ni-hdr{display:flex;align-items:center;gap:10px;margin-bottom:6px}
.ni-hdr .ni-icon{width:36px;height:36px;border-radius:8px;background:rgba(var(--sec-rgb,56,189,248),.1);display:flex;align-items:center;justify-content:center;flex-shrink:0}
.ni-hdr .ni-title{flex:1;font-size:.82rem;font-weight:700}
.ni-hdr .ni-dot{width:10px;height:10px;border-radius:50%;background:#ef4444;flex-shrink:0}
.ni-hdr .ni-dot.read{background:transparent}
.ni-date{font-size:.62rem;color:var(--t3);margin-bottom:6px}
.ni-body{font-size:.75rem;color:var(--t2);line-height:1.6;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.nitem.open .ni-body{-webkit-line-clamp:unset;overflow:visible}
.nitem.open .ni-body img{max-width:100%;border-radius:8px;margin:8px 0}
.empty{text-align:center;padding:40px;color:var(--t3);font-size:.8rem}
</style>
</head>
<body>
<div class="hdr"><button onclick="history.back()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button><h1>Pusat Berita</h1><div style="width:32px"></div></div>
<div class="tabs">
  <div class="tab-i on" onclick="switchTab(0,this)">Layanan Pelanggan</div>
  <div class="tab-i" onclick="switchTab(1,this)">Pemberitahuan <span class="badge" id="nBadge"><?php echo $notifCount; ?></span></div>
  <div class="tab-i" onclick="switchTab(2,this)">Blogger <span class="badge" id="bBadge"><?php echo $blogCount; ?></span></div>
</div>
<div class="panel on" id="p0">
  <div class="hero">
    <div class="hero-top"><img src="asset/cs_avatar.png" alt="CS"><div class="hero-txt"><h2>Layanan Pelanggan Online <span>7×24</span></h2><p>Layanan online layanan pelanggan yang profesional, memperhatikan masalah Anda.</p></div></div>
    <a class="hero-btn" id="mainCs" href="cs_chat.php">Hubungi Layanan Pelanggan</a>
  </div>
  <div class="cards" id="csCards"></div>
</div>
<div class="panel" id="p1">
  <div class="mark-all"><button onclick="markAll()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg> Tandai Semua Sudah Dibaca</button></div>
  <div class="nlist" id="nList"><div class="empty">Memuat...</div></div>
</div>
<div class="panel" id="p2">
  <div class="nlist" id="bList"><div class="empty">Memuat...</div></div>
</div>
<script>
var SI=<?php echo json_encode($sets); ?>;
function switchTab(i,el){document.querySelectorAll('.tab-i').forEach(function(t){t.classList.remove('on')});document.querySelectorAll('.panel').forEach(function(p){p.classList.remove('on')});el.classList.add('on');document.getElementById('p'+i).classList.add('on');if(i===1&&!nLoaded)loadNotifs();if(i===2&&!bLoaded)loadBlogs();}
var svgTg='<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="12" fill="#229ED9"/><path d="M8 11.5l8.5-3.3c.4-.14.74.1.61.7l-1.46 6.86c-.1.48-.39.6-.79.37l-2.18-1.6-1.05 1c-.12.12-.21.21-.44.21l.16-2.21 4.03-3.64c.17-.15-.04-.24-.27-.09L9.1 13.47l-2.14-.67c-.47-.15-.48-.47.1-.69z" fill="#fff"/></svg>';
var svgWa='<svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="12" fill="#25D366"/><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.16-.17.2-.35.22-.64.07-.3-.15-1.26-.46-2.4-1.47-.88-.79-1.48-1.76-1.65-2.06-.17-.3-.02-.46.13-.6.13-.14.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.03-.52s-.67-1.61-.92-2.2c-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37s-1.04 1.02-1.04 2.48 1.07 2.88 1.21 3.07c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35M12.05 21.78h-.01a9.87 9.87 0 01-5.03-1.38l-.36-.21-3.74.98 1-3.65-.24-.37a9.86 9.86 0 01-1.51-5.26c0-5.45 4.44-9.88 9.89-9.88 2.64 0 5.12 1.03 6.99 2.9a9.83 9.83 0 012.89 6.99c0 5.45-4.44 9.88-9.89 9.88" fill="#fff"/></svg>';
if(SI.cs_main)document.getElementById('mainCs').href=SI.cs_main;
var ch='';
[{icon:'tg',t:'Layanan Telegram',d:'Layanan Online 24 Jam',k:'cs_telegram'},{icon:'tg',t:'Channel Telegram Resmi',d:'Follow & Dapatkan Bonus',k:'cs_telegram_channel'},{icon:'wa',t:'Channel Whatsapp Resmi',d:'Follow & Dapatkan Bonus',k:'cs_whatsapp'}].forEach(function(c){
  var link=SI[c.k]||'#';
  ch+='<div class="card"><div class="card-icon">'+(c.icon==='tg'?svgTg:svgWa)+'</div><div class="card-info"><h3>'+c.t+'</h3><p>'+c.d+'</p></div><a class="card-btn" href="'+link+'" target="_blank">Hubungi Layanan Pelanggan</a></div>';
});
document.getElementById('csCards').innerHTML=ch;
var nLoaded=false;
function loadNotifs(){nLoaded=true;
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'get_memos'})}).then(function(r){return r.json()}).then(function(d){
    var list=d.memos||[];if(!list.length){document.getElementById('nList').innerHTML='<div class="empty">Belum ada pemberitahuan</div>';return}
    var h='';list.forEach(function(n){
      h+='<div class="nitem" onclick="this.classList.toggle(\'open\')"><div class="ni-hdr"><div class="ni-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></div><div class="ni-title">'+n.title+'</div><div class="ni-dot'+(n.is_read?' read':'')+'"></div></div><div class="ni-date">'+n.created_at+'</div><div class="ni-body">'+n.body+'</div></div>';
    });document.getElementById('nList').innerHTML=h;
  }).catch(function(){document.getElementById('nList').innerHTML='<div class="empty">Login untuk melihat</div>';})
}
function markAll(){fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'mark_all_read'})}).then(function(r){return r.json()}).then(function(){document.querySelectorAll('.ni-dot').forEach(function(d){d.classList.add('read')});document.getElementById('nBadge').textContent='0';})}
var bLoaded=false;
function loadBlogs(){bLoaded=true;
  fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'get_blogs'})}).then(function(r){return r.json()}).then(function(d){
    var list=d.blogs||[];if(!list.length){document.getElementById('bList').innerHTML='<div class="empty">Belum ada blog</div>';return}
    var h='';list.forEach(function(b){
      h+='<div class="nitem" onclick="this.classList.toggle(\'open\')"><div class="ni-hdr"><div class="ni-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M4 22h16a2 2 0 002-2V4a2 2 0 00-2-2H8a2 2 0 00-2 2v16a2 2 0 01-2 2zm0 0a2 2 0 01-2-2v-9c0-1.1.9-2 2-2h2"/></svg></div><div class="ni-title">'+b.title+'</div></div><div class="ni-date">'+b.created_at+'</div><div class="ni-body">'+b.body+'</div></div>';
    });document.getElementById('bList').innerHTML=h;
  }).catch(function(){document.getElementById('bList').innerHTML='<div class="empty">Gagal memuat</div>';})
}
</script>
</body>
</html>
