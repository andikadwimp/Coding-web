<?php
require_once 'includes/config.php';
header('Content-Type: text/html; charset=UTF-8');
$isLoggedIn = (bool)getUid();  // guest-friendly: page accessible, action gated by JS

$PROVS_JSON='[]';
try{
    // Ensure game_clicks table exist
    try{$db->exec("CREATE TABLE IF NOT EXISTS game_clicks (game_code VARCHAR(100) NOT NULL,provider_code VARCHAR(50) NOT NULL,click_count BIGINT UNSIGNED DEFAULT 0,last_clicked DATETIME DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (provider_code, game_code),INDEX idx_clicks (click_count DESC)) ENGINE=InnoDB");}catch(Exception $e){}
    $provs=$db->query("SELECT code,name,logo,game_count FROM providers WHERE status=1 AND game_count>0 ORDER BY sort_order ASC, game_count DESC")->fetchAll();
    $result=[];
    foreach($provs as $p){
        $p['logo']=normUrl($p['logo']??'');
        // WAJIB banner ada — game tanpa banner di-skip (dianggap maintenance)
        $g=$db->prepare("SELECT g.game_code,g.game_name,g.banner,COALESCE(c.click_count,0) as clicks
                         FROM games g LEFT JOIN game_clicks c ON c.provider_code=g.provider_code AND c.game_code=g.game_code
                         WHERE g.provider_code=? AND g.status=1
                           AND g.banner IS NOT NULL AND g.banner != ''
                         ORDER BY clicks DESC, g.game_name ASC");
        $g->execute([$p['code']]);
        $games=$g->fetchAll();
        foreach($games as &$gm){$gm['banner']=normUrl($gm['banner']??'');}
        $p['games']=$games;$result[]=$p;
    }
    $PROVS_JSON=json_encode($result);
}catch(Exception $e){}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="apple-mobile-web-app-capable" content="yes"><meta name="apple-mobile-web-app-status-bar-style" content="black-translucent"><meta name="mobile-web-app-capable" content="yes"><meta name="theme-color" content="var(--bg)"><link rel="manifest" href="/manifest.php"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>Permainan</title>
<link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="theme.php?v=<?=time()?>">
<style>
*{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;-webkit-text-size-adjust:100%;text-size-adjust:100%}
html{font-size:16px!important;overflow-x:hidden}
body{font-family:'Poppins',sans-serif;background:var(--bg);color:var(--t);overflow-x:hidden;max-width:100vw}
a{color:inherit;text-decoration:none}
.hdr{display:flex;align-items:center;padding:14px 16px;background:var(--bg);position:sticky;top:0;z-index:50;border-bottom:1px solid var(--bd)}
.hdr a{width:30px;height:30px;display:flex;align-items:center;justify-content:center;color:var(--t2)}
.hdr a svg{width:20px;height:20px}
.hdr h1{font-family:'Chakra Petch',sans-serif;font-size:1.1rem;font-weight:700;flex:1;text-align:center;margin-right:30px}
.search{padding:10px 14px;background:var(--bg)}
.search-in{display:flex;align-items:center;gap:8px;background:var(--bg2);border:1.5px solid var(--bd);border-radius:12px;padding:12px 16px;transition:all .15s}
.search-in:focus-within{border-color:var(--pri);box-shadow:0 0 0 3px var(--pri-l)}
.search-in input{flex:1;border:none;background:transparent;outline:none;font-family:'Poppins';font-size:.82rem;color:var(--t);-webkit-appearance:none;appearance:none}
.search-in input::-webkit-search-cancel-button,.search-in input::-webkit-search-decoration,.search-in input::-webkit-search-results-button,.search-in input::-webkit-search-results-decoration{-webkit-appearance:none;display:none}
.search-in input::placeholder{color:var(--t3)}
.search-in svg{width:18px;height:18px;color:var(--t3);flex-shrink:0}
.filters{display:flex;gap:6px;padding:6px 14px 10px;overflow-x:auto;scrollbar-width:none}.filters::-webkit-scrollbar{display:none}
.fil{flex-shrink:0;padding:9px 18px;border-radius:9px;font-size:.74rem;font-weight:700;cursor:pointer;border:1.5px solid var(--bd);color:var(--t2);background:var(--bg2);transition:all .15s;letter-spacing:.2px}
.fil:hover{border-color:var(--pri);color:var(--pri)}
.fil.active{background:linear-gradient(135deg,var(--pri),var(--pri-d));color:#fff;border-color:var(--pri);box-shadow:0 4px 12px rgba(var(--pri-rgb),.25)}

/* ═══ PROVIDER STRIP — horizontal scroll di ATAS, bukan sidebar kiri ═══ */
.prov-strip{display:flex;gap:6px;overflow-x:auto;overflow-y:hidden;padding:6px 14px 10px;scrollbar-width:none;-webkit-overflow-scrolling:touch;scroll-behavior:smooth;border-bottom:1px solid var(--bd);margin-bottom:8px}
.prov-strip::-webkit-scrollbar{display:none}
.prov-item{flex:0 0 auto;display:flex;flex-direction:column;align-items:center;justify-content:center;min-width:64px;max-width:80px;padding:8px 6px;border-radius:12px;cursor:pointer;opacity:.55;transition:all .2s;background:transparent;border:1.5px solid transparent;box-sizing:border-box}
.prov-item.active{opacity:1}
.prov-item.active .p-logo{background:linear-gradient(135deg,var(--pri),var(--pri-d));border-color:var(--pri);box-shadow:0 4px 12px rgba(var(--pri-rgb),.3)}
.prov-item.active .p-logo span,.prov-item.active .p-logo img{filter:brightness(0) invert(1)}
.prov-item.active .p-name{color:var(--pri);font-weight:800}
.prov-item:active{opacity:.75}
.prov-item .p-logo{width:44px;height:44px;border-radius:11px;background:var(--bg2);border:1px solid var(--bd);display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;margin-bottom:5px;transition:all .2s}
.prov-item .p-logo img{width:32px;height:32px;object-fit:contain}
.prov-item .p-logo span{font-size:.44rem;font-weight:800;color:var(--pri,var(--sec))}
.prov-item .p-name{font-size:.55rem;font-weight:700;color:var(--pri,var(--sec));line-height:1.2;text-align:center;width:100%;max-height:2.4em;overflow:hidden}
.prov-item.active .p-name{font-weight:800}

/* ═══ CONTENT FULL — tanpa sidebar kiri ═══ */
.content-full{padding:6px 6px 90px;overflow-y:auto;min-height:calc(100vh - 220px)}

/* ═══ GAMES GRID — 3 kolom, card 1:1 square HARDCODE ═══ */
.games-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:6px;width:100%}
.gwrap{display:flex;flex-direction:column;gap:0;width:100%;min-width:0}
/* Compact card */
.gc{
  position:relative;width:100%;height:auto;
  border:1.5px solid var(--bd);
  border-radius:12px;overflow:hidden;cursor:pointer;
  background:var(--bg2);display:block;padding:0;box-sizing:border-box;
  transition:all .18s cubic-bezier(.4,0,.2,1);
  box-shadow:0 1px 3px rgba(0,0,0,.04);
}
.gc:hover{
  border-color:var(--pri);
  box-shadow:0 4px 14px rgba(var(--pri-rgb),.15);
  transform:translateY(-2px);
}
.gc:active{transform:translateY(0) scale(.97)}
/* True 1:1 wrapper — works in all browsers */
.gc-img-wrap{position:relative;width:100%;padding-bottom:100%;overflow:hidden;background:var(--tint-1)}
.gc > img,.gc > .gc-img-wrap > img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block}
.gc .thumb{display:none}
.gwrap.no-image .gc > img{display:none}
.gwrap.no-image .gc .thumb{display:flex}
.gc .rtp{position:absolute;top:3px;left:3px;background:rgba(0,0,0,.8);color:#4ade80;font-size:.5rem;font-weight:800;padding:1px 4px;border-radius:4px;z-index:3;letter-spacing:.2px;backdrop-filter:blur(4px)}
.gc .fav{position:absolute;top:3px;right:3px;width:18px;height:18px;background:rgba(0,0,0,.55);border-radius:50%;display:flex;align-items:center;justify-content:center;z-index:3;cursor:pointer;border:none;padding:0;backdrop-filter:blur(4px)}
.gc .fav:hover{background:rgba(0,0,0,.75)}
/* Text strip — compact */
.gc .gn{position:static;background:var(--bg2);background-image:none;padding:4px 5px 5px;z-index:1;display:block;border-top:1px solid var(--bd)}
.gc .gn-name{font-size:.6rem;font-weight:700;color:var(--t);line-height:1.15;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;letter-spacing:-.01em}
.gc .gn-prov{display:none}
.empty{text-align:center;padding:40px;color:var(--t3);font-size:.75rem;grid-column:1/-1}

.game-overlay{position:fixed;inset:0;z-index:9000;background:#000;display:none}
.game-overlay.show{display:block}
.game-overlay iframe{width:100%;height:100%;border:none}
.fl-drag{position:fixed;z-index:9999;touch-action:none;cursor:grab;user-select:none;-webkit-user-select:none}
.fl-btn{display:flex;flex-direction:column;align-items:center;gap:2px;padding:8px;border-radius:14px;background:rgba(0,0,0,.45);-webkit-border:1px solid var(--tint-3)}
.fl-btn .fl-icon{width:32px;height:32px;border-radius:50%;background:var(--tint-3);display:flex;align-items:center;justify-content:center}
.fl-btn .fl-icon svg{width:18px;height:18px;color:#fff}
.fl-btn .fl-label{font-size:.6rem;color:rgba(255,255,255,.85);font-weight:700;font-family:'Poppins'}

.game-loading{position:fixed;inset:0;z-index:9500;background:rgba(10,15,28,.55);backdrop-filter:blur(22px) saturate(140%);-webkit-backdrop-filter:blur(22px) saturate(140%);display:none;flex-direction:column;align-items:center;justify-content:center;gap:18px;opacity:0;transition:opacity .35s cubic-bezier(.16,1,.3,1)}
.game-loading.show{display:flex;opacity:1}
.gl-spin{width:140px;height:2px;background:rgba(255,255,255,.12);border-radius:2px;overflow:hidden;position:relative}
.gl-spin::before{content:"";position:absolute;left:0;top:0;width:35%;height:100%;background:linear-gradient(90deg,transparent,var(--pri) 50%,transparent);animation:lxShimmer 1.4s cubic-bezier(.4,0,.2,1) infinite;border-radius:2px}
@keyframes lxShimmer{0%{transform:translateX(-110%)}100%{transform:translateX(310%)}}
.gl-text{font-family:'Chakra Petch';font-size:.85rem;color:#fff;font-weight:600;letter-spacing:.5px;text-shadow:0 1px 6px rgba(0,0,0,.45)}
.app-toast{position:fixed;top:20px;left:50%;transform:translate(-50%,-30px);z-index:99999;background:rgba(15,23,42,.78);backdrop-filter:blur(16px) saturate(160%);-webkit-backdrop-filter:blur(16px) saturate(160%);border:1px solid rgba(255,255,255,.1);color:#fff;padding:11px 22px;border-radius:11px;font-family:Poppins,sans-serif;font-size:.78rem;font-weight:600;letter-spacing:.1px;opacity:0;transition:opacity .35s cubic-bezier(.16,1,.3,1),transform .35s cubic-bezier(.16,1,.3,1);pointer-events:none;max-width:85%;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,.35)}
.app-toast.show{opacity:1;transform:translate(-50%,0)}


/* ═══════════════════════════════════════════════════════════════
   HARD OVERRIDE — force compact grid + 1:1 cards regardless of anything
   ═══════════════════════════════════════════════════════════════ */

/* Force NO sidebar layout — body & content full width */
body{display:block!important}
.content-full,.content,#gamesGrid,.games-grid{
  margin-left:0!important;margin-right:0!important;
  padding-left:6px!important;padding-right:6px!important;
  width:100%!important;max-width:100%!important;
}

/* Force 4 columns mobile, 5 tablet, 6 larger */
.games-grid{
  display:grid!important;
  grid-template-columns:repeat(3,minmax(0,1fr))!important;
  gap:6px!important;
  width:100%!important;
}

/* Force card 1:1 — use padding-bottom trick (works in ALL browsers) */
.gwrap{width:100%!important;display:block!important;min-width:0!important}
.gc{
  position:relative!important;
  width:100%!important;
  height:0!important;
  padding-bottom:100%!important;
  overflow:hidden!important;
  border-radius:8px!important;
  border:1.5px solid var(--bd)!important;
  background:var(--bg2)!important;
  box-shadow:0 1px 4px rgba(0,0,0,.15)!important;
  display:block!important;
  cursor:pointer!important;
  margin:0!important;
}
.gc:hover{transform:translateY(-2px);transition:all .18s}
.gc:active{transform:scale(.96)}

/* Image fills the square completely, cropping if needed */
.gc > img,.gc img{
  position:absolute!important;
  top:0!important;left:0!important;right:0!important;bottom:0!important;
  width:100%!important;
  height:100%!important;
  object-fit:cover!important;
  object-position:center!important;
  display:block!important;
  border-radius:0!important;
}

/* Hide bottom name strip on small cards (no room) */
.gc .gn,.gc .gn-name,.gc .gn-prov{display:none!important}

/* RTP & fav badges — tiny */
.gc .rtp{
  position:absolute!important;
  top:3px!important;left:3px!important;
  background:rgba(0,0,0,.85)!important;
  color:#4ade80!important;
  font-size:.5rem!important;
  font-weight:800!important;
  padding:1px 4px!important;
  border-radius:3px!important;
  z-index:3!important;
  letter-spacing:.2px!important;
  line-height:1.1!important;
}
.gc .fav{
  position:absolute!important;
  top:3px!important;right:3px!important;
  width:18px!important;height:18px!important;
  background:rgba(0,0,0,.5)!important;
  border-radius:50%!important;
  display:flex!important;
  align-items:center!important;
  justify-content:center!important;
  z-index:3!important;
  cursor:pointer!important;
  border:none!important;
  padding:0!important;
}
.gc .fav svg{width:11px!important;height:11px!important}

/* Provider strip — horizontal scroll, NEVER vertical */
.prov-strip{
  display:flex!important;
  flex-direction:row!important;
  overflow-x:auto!important;
  overflow-y:hidden!important;
  white-space:nowrap!important;
  padding:6px 10px!important;
  gap:6px!important;
}
.prov-item{
  flex:0 0 auto!important;
  display:inline-flex!important;
  flex-direction:column!important;
  width:auto!important;
}


@keyframes skelSweep{0%{background-position:-200% 0}100%{background-position:200% 0}}
.skel-games-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;padding:12px}
.skel-g-card{aspect-ratio:1/1;border-radius:10px;background:linear-gradient(90deg,var(--bg2) 25%,var(--tint-2) 50%,var(--bg2) 75%);background-size:200% 100%;animation:skelSweep 1.4s ease-in-out infinite}
.g-skel{aspect-ratio:1/1;border-radius:10px;background:linear-gradient(90deg,var(--bg2) 25%,var(--tint-2) 50%,var(--bg2) 75%);background-size:200% 100%;animation:skelSweep 1.4s ease-in-out infinite}
</style>
<link rel="stylesheet" href="theme.php?v=<?=time()?>">
</head>
<body>
<div class="hdr"><a href="index.php"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></a><h1>Permainan</h1></div>
<div class="search"><div class="search-in"><input placeholder="Pencarian Permainan" id="searchInp" oninput="filterGames()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></div></div>
<div class="filters"><div class="fil active" onclick="setFilter('all',this)">Semua</div><div class="fil" onclick="setFilter('popular',this)">Populer</div><div class="fil" onclick="setFilter('recent',this)">Terkini</div><div class="fil" onclick="setFilter('fav',this)">Favorit</div></div>
<!-- Provider list — HORIZONTAL SCROLL di atas -->
<div class="prov-strip" id="provStrip"></div>
<!-- Games grid full-width -->
<div class="content-full"><div class="games-grid" id="gamesGrid"><div class="g-skel"></div><div class="g-skel"></div><div class="g-skel"></div><div class="g-skel"></div><div class="g-skel"></div><div class="g-skel"></div><div class="g-skel"></div><div class="g-skel"></div><div class="g-skel"></div></div></div>
<script>
function toast(msg){var t=document.getElementById("appToast");if(!t){t=document.createElement("div");t.id="appToast";t.className="app-toast";document.body.appendChild(t)}t.textContent=msg;t.classList.add("show");clearTimeout(t._tm);t._tm=setTimeout(function(){t.classList.remove("show")},2500)}
var user=JSON.parse(localStorage.getItem('app_user')||'null');
var IS_LOGGED_IN=window.IS_LOGGED_IN===true;
var PROVS=[],curProv=null,allGames=[],curFilter='all';
var GC=['linear-gradient(135deg,#0c4a6e,#0a1628)','linear-gradient(135deg,#1a365d,#0c4a6e)','linear-gradient(135deg,#4c1d95,#2d1b69)','linear-gradient(135deg,#7f1d1d,#991b1b)','linear-gradient(135deg,#713f12,#a16207)','linear-gradient(135deg,#0c4a6e,#047857)'];
var favs=JSON.parse(localStorage.getItem('app_favs')||'{}');
var HEART='<svg viewBox="0 0 24 24" width="13" height="13"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>';
function tFav(e,k){e.preventDefault();e.stopPropagation();if(favs[k])delete favs[k];else favs[k]=1;localStorage.setItem('app_favs',JSON.stringify(favs));var s=e.currentTarget.querySelector('span');s.innerHTML=favs[k]?HEART.replace('<svg','<svg fill="#ef4444" stroke="#ef4444" stroke-width="2"'):HEART.replace('<svg','<svg fill="none" stroke="var(--t3)" stroke-width="2"')}
var _isLaunching=false;

async function launchGame(prov,code){
  if(typeof requireLogin==='function'&&!requireLogin('main game'))return;
  if(_isLaunching)return;
  _isLaunching=true;
  document.getElementById('gameLoading').classList.add('show');
  document.getElementById('gameOverlay').classList.add('show');
  document.getElementById('gameFrame').src='';
  try{
    var r=await fetch('api/game.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'launch',provider:prov,game_code:code})});
    var d=await r.json();
    if(d.ok&&d.launch_url){
      var fr=document.getElementById('gameFrame');
      fr.onload=function(){document.getElementById('gameLoading').classList.remove('show');_isLaunching=false};
      fr.src=d.launch_url;
    }else{
      document.getElementById('gameOverlay').classList.remove('show');
      document.getElementById('gameLoading').classList.remove('show');
      _isLaunching=false;
      toast(d.error||'Gagal membuka game');
    }
  }catch(e){
    document.getElementById('gameOverlay').classList.remove('show');
    document.getElementById('gameLoading').classList.remove('show');
    _isLaunching=false;
    toast('Server error');
  }
}

async function pullBal(){
  if(_isLaunching)return; // Jangan tarik balance saat game sedang loading!
  try{
    await fetch('api/game.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'withdraw_game'})});
  }catch(e){}
}
function closeGame(){_isLaunching=false;document.getElementById('gameOverlay').classList.remove('show');document.getElementById('gameFrame').src='';document.getElementById('gameLoading').classList.add('show');pullBal();}

function init(){
    PROVS=<?php echo $PROVS_JSON; ?>;
    renderProvStrip();
    var urlP=new URLSearchParams(location.search).get('provider');
    if(urlP){var found=PROVS.find(function(p){return p.code===urlP});if(found){curProv=found;highlightProv(urlP)}}
    renderGames();
}

function renderProvStrip(){
    var h='';
    // "Semua" button dulu — view all games
    h+='<div class="prov-item'+(!curProv?' active':'')+'" data-code="" onclick="selectProv(this,\'\')"><div class="p-logo"><span>ALL</span></div><div class="p-name">Semua</div></div>';
    PROVS.forEach(function(p,i){
        var logo=p.logo?'<img src="'+p.logo+'" alt="">':'<span>'+(p.name||p.code).substring(0,2).toUpperCase()+'</span>';
        h+='<div class="prov-item'+(curProv&&curProv.code===p.code?' active':'')+'" data-code="'+p.code+'" onclick="selectProv(this,\''+p.code+'\')"><div class="p-logo">'+logo+'</div><div class="p-name">'+(p.name||p.code)+'</div></div>';
    });
    document.getElementById('provStrip').innerHTML=h;
}

function highlightProv(code){
    document.querySelectorAll('.prov-item').forEach(function(b){
      var isActive=b.dataset.code===code;
      b.classList.toggle('active',isActive);
    });
}

function selectProv(el,code){
    if(code===''){curProv=null;}
    else{curProv=PROVS.find(function(p){return p.code===code})||null;}
    document.querySelectorAll('.prov-item').forEach(function(b){b.classList.remove('active')});
    el.classList.add('active');
    // Scroll active item ke view
    el.scrollIntoView({behavior:'smooth',block:'nearest',inline:'center'});
    renderGames();
}

function setFilter(f,el){
    curFilter=f;
    document.querySelectorAll('.fil').forEach(function(b){b.classList.remove('active')});
    el.classList.add('active');
    renderGames();
}

function filterGames(){renderGames()}

function renderGames(){
    var grid=document.getElementById('gamesGrid');
    var search=document.getElementById('searchInp').value.toLowerCase().trim();
    var games=[];

    if(curProv){
        (curProv.games||[]).forEach(function(g){games.push({name:g.game_name,banner:g.banner,code:g.game_code,clicks:(g.clicks||0),prov:curProv})});
    }else{
        PROVS.forEach(function(p){(p.games||[]).forEach(function(g){games.push({name:g.game_name,banner:g.banner,code:g.game_code,clicks:(g.clicks||0),prov:p})})});
    }

    if(search){games=games.filter(function(g){return g.name.toLowerCase().indexOf(search)>-1})}

    // Filter OUT game tanpa banner (maintenance) — JANGAN tampilkan sama sekali
    games=games.filter(function(g){return g.banner && g.banner.length > 3});

    if(curFilter==='popular'){
        games.sort(function(a,b){return (b.clicks||0)-(a.clicks||0)});
        games=games.slice(0,20);
    }else if(curFilter==='recent'){
        games=games.reverse();
        games=games.slice(0,20);
    }else if(curFilter==='fav'){
        games=games.filter(function(g){return favs[g.prov.code+'_'+g.code]});
    }else{
        // 'all' — populer naik ke atas (stable sort: clicks DESC, nama ASC)
        games.sort(function(a,b){
            var d=(b.clicks||0)-(a.clicks||0);
            if(d!==0)return d;
            return a.name.localeCompare(b.name);
        });
    }

    if(!games.length){grid.innerHTML='<div class="empty">Tidak ada game ditemukan</div>';return}

    var h='';
    games.forEach(function(g,i){
        var bn=g.banner||'';var gn=g.name||'Game';var rtp=(Math.random()*5+95).toFixed(1);
        var pn=(g.prov.name||g.prov.code);
        // Game tanpa banner di-skip total (maintenance)
        if(!bn || bn.length < 4) return;
        h+='<div class="gwrap"><a class="gc" href="#" onclick="launchGame(\''+g.prov.code+'\',\''+g.code+'\');return false">';
        // onload: kalau image dimensi kecil (1x1 blank) → hapus card
        // onerror: image gagal load → hapus card
        h+='<img src="'+bn+'" alt="'+gn+'" loading="lazy" '+
            'onload="if(this.naturalWidth<50||this.naturalHeight<50){this.onerror();}" '+
            'onerror="this.onerror=null;var c=this.closest(\'.gwrap\');if(c)c.remove();">';
        h+='<div class="rtp">'+rtp+'%</div>';
        var fk=g.prov.code+'_'+g.code;
        h+='<button class="fav" onclick="tFav(event,\''+fk+'\')"><span>'+(favs[fk]?HEART.replace('<svg','<svg fill="#ef4444" stroke="#ef4444" stroke-width="2"'):HEART.replace('<svg','<svg fill="none" stroke="#fff" stroke-width="2"'))+'</span></button>';
        h+='<div class="gn"><div class="gn-name">'+gn+'</div><div class="gn-prov">'+pn+'</div></div>';
        h+='</a></div>';
    });
    grid.innerHTML=h;
}

document.querySelectorAll('.fl-drag').forEach(function(el){
    var sx,sy,ox,oy,drag=false;
    el.addEventListener('touchstart',function(e){sx=e.touches[0].clientX;sy=e.touches[0].clientY;var r=el.getBoundingClientRect();ox=sx-r.left;oy=sy-r.top;drag=false},{passive:true});
    el.addEventListener('touchmove',function(e){drag=true;var x=e.touches[0].clientX-ox;var y=e.touches[0].clientY-oy;x=Math.max(0,Math.min(window.innerWidth-el.offsetWidth,x));y=Math.max(0,Math.min(window.innerHeight-el.offsetHeight,y));el.style.left=x+'px';el.style.top=y+'px';el.style.bottom='auto';el.style.right='auto';e.preventDefault()},{passive:false});
    el.addEventListener('touchend',function(e){if(drag)e.preventDefault()});
});
init();
</script>

<div class="game-overlay" id="gameOverlay"><div class="game-loading show" id="gameLoading"><div class="gl-spin"></div><div class="gl-text">Memuat permainan...</div></div>
<iframe id="gameFrame" src=""></iframe>
<div class="fl-drag" id="flLobby" style="left:10px;top:80px" onclick="closeGame()">
<div class="fl-btn"><div class="fl-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div><span class="fl-label">Lobby</span></div>
</div>
<div class="fl-drag" id="flDepo" style="left:10px;top:160px" onclick="location.href='deposit.php'">
<div class="fl-btn"><div class="fl-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M2 10h20"/></svg></div><span class="fl-label">Deposit</span></div>
</div>
</div>
<?php include 'includes/credit_notify.php'; ?>
</body>
</html>
