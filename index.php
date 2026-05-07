<?php
require_once 'includes/config.php';
header('Content-Type: text/html; charset=UTF-8');
// Unified beranda — handle both guest & logged-in
$isLoggedIn = (bool)getUid();
// Admin auto-redirect to panel
if($isLoggedIn){
    try{
        $_uidx = getUid();
        $_chk = $db->prepare("SELECT role FROM users WHERE id=?");
        $_chk->execute([$_uidx]);
        $_role = $_chk->fetchColumn();
        if($_role === 'admin'){ header("Location: team/index.php"); exit; }
    }catch(Exception $_e){}
}

$SI_JSON='{}';$PROVS_JSON='[]';$BANNERS_JSON='[]';$CARDS_JSON='[]';$SBGAMES_JSON='[]';
try{
    $sets=[];
    $st=$db->query("SELECT `key`,`value` FROM settings");
    if($st)foreach($st->fetchAll() as $r)$sets[$r['key']]=$r['value'];normSettings($sets);
    $SI=$sets;  // Make settings array accessible to PHP rendering
    $SI_JSON=json_encode($sets);
    // Auto-fix game_count from actual games
    $db->exec("UPDATE providers p SET game_count=(SELECT COUNT(*) FROM games g WHERE g.provider_code=p.code AND g.status=1)");
    $provs=$db->query("SELECT code,name,logo,game_count FROM providers WHERE status=1 AND game_count>0 ORDER BY sort_order ASC, game_count DESC")->fetchAll();
    $result=[];
    // Ensure game_clicks table exist
    try{$db->exec("CREATE TABLE IF NOT EXISTS game_clicks (game_code VARCHAR(100) NOT NULL,provider_code VARCHAR(50) NOT NULL,click_count BIGINT UNSIGNED DEFAULT 0,last_clicked DATETIME DEFAULT CURRENT_TIMESTAMP,PRIMARY KEY (provider_code, game_code),INDEX idx_clicks (click_count DESC)) ENGINE=InnoDB");}catch(Exception $e){}
    foreach($provs as $p){
        $p['logo']=normUrl($p['logo']??'');
        // Sort by click_count DESC (populer dulu), fallback alphabetical
        $g=$db->prepare("SELECT g.game_code,g.game_name,g.banner,COALESCE(c.click_count,0) as clicks
                         FROM games g LEFT JOIN game_clicks c ON c.provider_code=g.provider_code AND c.game_code=g.game_code
                         WHERE g.provider_code=? AND g.status=1
                         ORDER BY clicks DESC, g.game_name ASC");
        $g->execute([$p['code']]);
        $games=$g->fetchAll();
        foreach($games as &$gm){$gm['banner']=normUrl($gm['banner']??'');}
        $p['games']=$games;$result[]=$p;
    }
    $PROVS_JSON=json_encode($result);
    try{
        $bn=$db->query("SELECT id,image_url,link FROM promos WHERE status='active' AND image_url IS NOT NULL AND image_url!='' ORDER BY created_at DESC")->fetchAll();
        foreach($bn as &$b){$b['image_url']=normUrl($b['image_url']??'');}
        $BANNERS_JSON=json_encode($bn);
    }catch(Exception $e){}
    try{$CARDS_JSON='[]';}catch(Exception $e){}
    try{
        $sbCodes=['vswaysmahwin2','vswaysmahwblck','vs20olympx','vs243goldfor','SGTheKoiGate','dragon-hatch2','vs20olympgold'];
        $in=implode(',',array_fill(0,count($sbCodes),'?'));
        $q=$db->prepare("SELECT game_code,game_name,banner,provider_code FROM games WHERE status=1 AND banner!='' AND game_code IN ($in)");
        $q->execute($sbCodes);
        $sbg=$q->fetchAll();
        foreach($sbg as &$sg){$sg['banner']=normUrl($sg['banner']??'');}
        $SBGAMES_JSON=json_encode($sbg);
    }catch(Exception $e){}
}catch(Exception $e){}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<?php require_once 'pwa_head.php'; ?><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title><?php echo htmlspecialchars($sets['site_name']??'Dashboard'); ?> — Dashboard</title>
<link href="https://fonts.googleapis.com/css2?family=Chakra+Petch:wght@400;500;600;700&family=Cinzel:wght@700;900&family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<style>
*{-webkit-tap-highlight-color:transparent;-webkit-text-size-adjust:100%;-moz-text-size-adjust:100%;text-size-adjust:100%}html{font-size:16px!important;overflow-x:hidden}
body{overflow-x:hidden;max-width:100vw;background:var(--bg);color:var(--t);padding-bottom:66px}
button,a{-webkit-tap-highlight-color:transparent;touch-action:manipulation}.hdr,.bnav{will-change:auto;-webkit-transform:translateZ(0);transform:translateZ(0)}

.hdr{display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:var(--bg);position:sticky;top:0;z-index:100}
.hdr-left{display:flex;align-items:center;gap:8px;flex:1;min-width:0;overflow:hidden}
.hdr-menu{color:var(--t2);padding:6px;background:var(--tint-1);border:none;border-radius:8px;display:flex;align-items:center;justify-content:center}.hdr-menu svg{width:20px;height:20px}
.logo-img{height:36px;max-width:140px;width:auto;object-fit:contain}
.hdr-right{display:flex;align-items:center;gap:8px}
.hdr-bal{text-align:right}
.hdr-bal .bal-val{font-family:'Chakra Petch',sans-serif;font-size:1.1rem;font-weight:700;color:var(--pri)}
.hdr-bal .bal-label{font-size:.72rem;color:var(--t2);font-weight:600}

.banner-wrap{position:relative;overflow:hidden;margin:0 12px;border-radius:14px;border:none;box-shadow:none}
.banner-track{display:flex;transition:transform .38s cubic-bezier(.25,.1,.25,1);will-change:transform;align-items:stretch}
.b-slide{min-width:100%;position:relative;overflow:visible;background:transparent;display:flex}
.b-slide .b-img{width:100%;height:auto;display:block;object-fit:cover;flex-shrink:0}
.b-slide .b-fb{width:100%;height:100%}
.b-overlay{position:absolute;inset:0;background:transparent}
.b-content{position:absolute;bottom:0;left:0;right:0;padding:14px 16px;z-index:2}
.b-tag{display:inline-flex;align-items:center;gap:4px;background:var(--pri);color:#1a1200;padding:3px 10px;border-radius:4px;font-size:.72rem;font-weight:800;margin-bottom:6px;box-shadow:0 1px 4px rgba(0,0,0,.3)}
.b-tag svg{width:10px;height:10px}
.b-content h3{font-family:'Chakra Petch',sans-serif;font-size:1.15rem;font-weight:700;line-height:1.2;color:#fff;text-shadow:0 2px 8px rgba(0,0,0,.6)}
.b-content h3 .hl{color:var(--pri2)}
.b-content p{font-size:.72rem;color:var(--bd2);margin-top:3px;font-weight:600}
.b-arr{position:absolute;top:50%;transform:translateY(-50%);width:20px;height:36px;background:rgba(0,0,0,.4);color:var(--bd2);display:flex;align-items:center;justify-content:center;z-index:3;font-size:.7rem}
.b-arr.prev{left:0;border-radius:0 5px 5px 0}.b-arr.next{right:0;border-radius:5px 0 0 5px}
.b-dots{display:flex;justify-content:center;gap:5px;padding:5px 0 4px;background:var(--bg)}
.dot{width:6px;height:6px;border-radius:50%;background:#114a48;transition:all .3s;border:none;cursor:pointer}
.dot.active{background:var(--pri);width:18px;border-radius:3px}

.search-bar{display:flex;align-items:center;gap:6px;padding:6px 14px;background:var(--bg)}
.sb-main{flex:1;display:flex;align-items:center;gap:8px;background:linear-gradient(135deg,rgba(var(--sec-rgb,56,189,248),.08),rgba(var(--sec-rgb,56,189,248),.03));border:1px solid rgba(var(--sec-rgb,56,189,248),.2);border-radius:25px;padding:8px 14px;overflow:hidden}
.sb-icon{flex-shrink:0;width:22px;height:22px;display:flex;align-items:center;justify-content:center}
.sb-marquee{flex:1;overflow:hidden;white-space:nowrap;mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent);-webkit-mask-image:linear-gradient(90deg,transparent,#000 8%,#000 92%,transparent)}
.sb-inner{display:inline-block;animation:marquee 22s linear infinite;font-size:.68rem;font-weight:600;color:var(--t2)}
@keyframes marquee{0%{transform:translateX(0)}100%{transform:translateX(-50%)}}
.sb-btn{width:36px;height:36px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--t2);background:var(--s);border:1px solid rgba(var(--sec-rgb,56,189,248),.35)}
.sb-btn svg{width:16px;height:16px}
.sb-btn.green{background:var(--sec);border-color:var(--sec);color:#fff}

.qa-row{display:flex;gap:10px;padding:8px 14px;overflow-x:auto;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;scrollbar-width:none;cursor:grab;-webkit-transform:translateZ(0);transform:translateZ(0)}.qa-row:active{cursor:grabbing}.qa-row::-webkit-scrollbar{display:none}
.qa-card{flex-shrink:0;width:42%;height:110px;border-radius:14px;position:relative;overflow:hidden;border:none;cursor:pointer;box-shadow:none;background:transparent;scroll-snap-align:start}
.qa-card .qa-img{width:100%;height:100%;object-fit:cover;position:absolute;inset:0}
.qa-card .qa-fb{width:100%;height:100%;position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:var(--s)}
.qa-label{font-size:.72rem;font-weight:800;color:#fff;line-height:1.3;text-transform:uppercase}
.qa-val{font-family:'Chakra Petch',sans-serif;font-size:1.5rem;font-weight:700;color:#fff;text-shadow:0 2px 8px rgba(0,0,0,.4)}
.qa-sub{font-size:.6rem;color:rgba(255,255,255,.65);font-weight:600;line-height:1.4}

.prov-row{display:flex;gap:12px;padding:10px 14px 8px;overflow-x:auto;scrollbar-width:none;background:var(--bg)}.prov-row::-webkit-scrollbar{display:none}
.prov-item{flex-shrink:0;display:flex;flex-direction:column;align-items:center;gap:5px;width:60px}
.prov-diamond{width:68px;height:68px;border-radius:14px;display:flex;align-items:center;justify-content:center;border:none;background:var(--s);overflow:hidden}
.prov-diamond .inner{display:flex;align-items:center;justify-content:center;width:100%;height:100%}
.prov-diamond .inner img{width:80%;height:80%;object-fit:contain;filter:brightness(0) invert(1)}
.prov-diamond .inner span{font-size:.48rem;font-weight:800;color:#fff;}
.prov-name{font-size:.72rem;font-weight:700;color:var(--t3);text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;width:100%}
@keyframes spotUp{0%,100%{box-shadow:0 -3px 10px rgba(var(--sec-rgb,56,189,248),.25),0 2px 6px rgba(0,0,0,.3)}50%{box-shadow:0 -6px 20px rgba(var(--pri-rgb),.35),0 2px 8px rgba(0,0,0,.4)}}
.prov-diamond .inner{display:flex;align-items:center;justify-content:center}
.prov-diamond .inner span{font-size:.4rem;font-weight:800;color:#fff;}
.prov-name{font-size:.72rem;font-weight:700;color:var(--t3);text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;width:100%}

.sec-h{display:flex;align-items:center;justify-content:space-between;padding:12px 14px 8px}
.sec-hl{display:flex;align-items:center;gap:6px}
.sec-hl svg{width:20px;height:20px;color:var(--pri)}
.sec-h h3{font-family:'Chakra Petch',sans-serif;font-size:.9rem;font-weight:700}
.sec-h .cnt{background:var(--s2);color:var(--t2);padding:1px 7px;border-radius:8px;font-size:.6rem;font-weight:700;margin-left:4px}
.sec-all{font-size:.75rem;color:var(--pri);font-weight:700}

.prov-sec{margin:10px 14px;border:1.5px solid rgba(var(--sec-rgb,56,189,248),.65);border-radius:14px;overflow:hidden;background:var(--bg2);box-shadow:0 4px 15px rgba(0,0,0,.25)}
.prov-sec-hdr{display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-bottom:1px solid rgba(var(--sec-rgb,56,189,248),.35)}
.prov-sec-left{display:flex;align-items:center;gap:8px}
.prov-sec-logo{display:none}
.prov-sec-logo img{width:22px;height:22px;object-fit:contain}
.prov-sec-logo span{font-size:.35rem;font-weight:800;color:var(--pri2)}
.prov-sec-hdr h4{font-family:'Chakra Petch',sans-serif;font-size:1rem;font-weight:700}
.ps-cnt{background:var(--s2);color:var(--t2);padding:1px 7px;border-radius:8px;font-size:.6rem;font-weight:700;margin-left:4px}
.ps-arr{width:32px;height:32px;border-radius:8px;background:var(--s);border:1px solid rgba(var(--sec-rgb,56,189,248),.25);color:var(--t2);display:flex;align-items:center;justify-content:center;flex-shrink:0;cursor:pointer}
.ps-arr svg{width:16px;height:16px}
.ps-arr:active{background:rgba(var(--sec-rgb,56,189,248),.15)}
.prov-sec-right{display:flex;flex-direction:row;align-items:center;gap:6px}
.ps-all{font-size:.72rem;color:var(--pri);font-weight:700}
.prov-sec-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px 8px;padding:12px 10px;align-items:start}
.gwrap{display:flex;flex-direction:column;gap:6px;text-decoration:none;color:inherit}
.gwrap .gn{display:block;font-family:'Plus Jakarta Sans','Outfit','Poppins',sans-serif;font-size:.7rem;font-weight:600;color:var(--t);line-height:1.2;letter-spacing:-.01em;text-align:center;padding:0 2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.gc-overlay{position:absolute;left:0;right:0;bottom:0;height:42%;display:flex;align-items:flex-end;justify-content:center;padding:0 0 8px;background:linear-gradient(180deg,transparent 0%,rgba(0,0,0,.55) 45%,rgba(0,0,0,.85) 100%);z-index:2;pointer-events:none}
.gc-overlay img{max-width:60%;max-height:50%;object-fit:contain;filter:drop-shadow(0 1px 3px rgba(0,0,0,.6))}
.gc-overlay .gc-prov-name{font-family:'Outfit','Plus Jakarta Sans',sans-serif;font-size:.6rem;font-weight:800;color:#fff;letter-spacing:.5px;text-transform:uppercase;text-shadow:0 1px 3px rgba(0,0,0,.7)}
.prov-sec-more{display:flex;align-items:center;justify-content:center;padding:10px;border-top:1px solid rgba(var(--sec-rgb,56,189,248),.35);cursor:pointer;gap:4px}
.prov-sec-more span{font-size:.75rem;font-weight:700;color:var(--pri)}
.prov-sec-more svg{width:12px;height:12px;color:var(--pri)}

.gc{position:relative;width:100%;aspect-ratio:1/1;border-radius:12px;overflow:hidden;cursor:pointer;-webkit-transform:translateZ(0);transform:translateZ(0);transition:transform .25s cubic-bezier(.16,1,.3,1),box-shadow .3s ease,border-color .25s ease;background:var(--s);border:1.5px solid rgba(var(--pri-rgb),.18);box-shadow:0 2px 8px rgba(0,0,0,.25),0 0 0 1px rgba(255,255,255,.03) inset;display:block;padding:0;isolation:isolate}
.gc::after{content:'';position:absolute;inset:0;background:linear-gradient(180deg,transparent 60%,rgba(0,0,0,.4) 100%);opacity:0;transition:opacity .25s ease;pointer-events:none;z-index:1}
.gc:hover::after{opacity:1}
.gc:hover{transform:translateY(-3px);border-color:rgba(var(--pri-rgb),.45);box-shadow:0 8px 20px rgba(0,0,0,.35),0 0 0 1px rgba(var(--pri-rgb),.15) inset}
.gc.featured-game{border:1.5px solid rgba(var(--pri-rgb,56,189,248),.55);box-shadow:0 0 0 1px rgba(var(--pri-rgb,56,189,248),.25) inset,0 4px 14px rgba(var(--pri-rgb,56,189,248),.18),0 2px 6px rgba(0,0,0,.3);animation:gcFeatured 3.5s ease-in-out infinite}
@keyframes gcFeatured{0%,100%{box-shadow:0 0 0 1px rgba(var(--pri-rgb,56,189,248),.25) inset,0 4px 14px rgba(var(--pri-rgb,56,189,248),.18),0 2px 6px rgba(0,0,0,.3)}50%{box-shadow:0 0 0 1px rgba(var(--pri-rgb,56,189,248),.45) inset,0 6px 22px rgba(var(--pri-rgb,56,189,248),.32),0 2px 6px rgba(0,0,0,.3)}}
.gc img{transition:transform .5s cubic-bezier(.16,1,.3,1)}
.gc:hover img{transform:scale(1.06)}
.gc:active{transform:scale(.96)}
.gc img{position:absolute!important;top:0!important;left:0!important;width:100%!important;height:100%!important;object-fit:cover!important;display:block}
.gc .thumb{position:absolute!important;top:0!important;left:0!important;width:100%!important;height:100%!important;display:flex;align-items:center;justify-content:center;font-size:.6rem;color:rgba(255,255,255,.6);text-align:center;padding:4px;font-weight:700}
.gc .rtp{position:absolute;top:3px;left:3px;background:rgba(0,0,0,.7);color:#4ade80;font-size:.38rem;font-weight:700;padding:1px 4px;border-radius:3px;z-index:2}
.gc .pbadge{position:absolute;top:3px;left:3px;width:18px;height:18px;background:rgba(0,0,0,.6);border-radius:4px;display:flex;align-items:center;justify-content:center;z-index:2}
.gc .pbadge img{width:14px;height:14px;object-fit:contain}
.gc .pbadge span{font-size:.28rem;font-weight:800;color:var(--pri2)}

/* .gc .gn deprecated — gn now sibling of .gc inside .gwrap */

/* SEARCH */
.srch-ov{position:fixed;inset:0;z-index:400;background:var(--bg);display:none;flex-direction:column;opacity:0;transform:translateY(20px);transition:opacity .3s cubic-bezier(.16,1,.3,1),transform .3s cubic-bezier(.16,1,.3,1)}
.srch-ov.open{display:flex;opacity:1;transform:translateY(0)}
.srch-ov.closing{opacity:0;transform:translateY(20px)}
.srch-hdr{display:flex;align-items:center;padding:14px 16px;gap:12px}
.srch-hdr button{width:32px;height:32px;display:flex;align-items:center;justify-content:center;color:var(--t2);background:none;border:none}
.srch-hdr button svg{width:22px;height:22px}
.srch-hdr h2{flex:1;font-size:1.1rem;font-weight:700;text-align:center}
.srch-input{margin:0 16px 10px;display:flex;align-items:center;background:var(--s);border:none;border-radius:10px;padding:0 14px;gap:8px}
.srch-input input{flex:1;border:none;background:none;color:var(--t);font-family:inherit;font-size:.9rem;padding:12px 0;outline:none}
.srch-input input::placeholder{color:var(--t3)}
.srch-input svg{width:20px;height:20px;color:var(--t3);flex-shrink:0}
.srch-pills{display:flex;padding:0 16px 10px;gap:6px;overflow-x:auto;scrollbar-width:none}
.srch-pills::-webkit-scrollbar{display:none}
.srch-pill{padding:8px 18px;border-radius:20px;font-size:.78rem;font-weight:700;white-space:nowrap;cursor:pointer;border:none;color:var(--t3);background:none;flex-shrink:0}
.srch-pill.on{background:var(--sec);color:#fff;border-color:var(--sec)}
.srch-grid{flex:1;overflow-y:auto;-webkit-overflow-scrolling:touch;padding:8px;display:grid;grid-template-columns:repeat(3,1fr);gap:8px;align-content:start}
.srch-grid .gc{position:relative;overflow:hidden;background:var(--bg2);border:1.5px solid var(--bd);border-radius:8px;width:100%;height:0;padding-bottom:100%;display:block;cursor:pointer}
.srch-grid .gc > img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block}
.srch-grid .gc .thumb{display:none}
.srch-grid .gwrap.no-image .gc > img{display:none}
.srch-grid .gwrap.no-image .gc .thumb{display:flex}
.srch-grid /* .gc .gn deprecated — gn now sibling of .gc inside .gwrap */
.srch-grid .gc .gn-name{font-size:.68rem;font-weight:700;color:var(--t);line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.srch-grid .gc .gn-prov{font-size:.55rem;font-weight:500;color:var(--t3);line-height:1.2;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.srch-grid .gc .gc-rtp{position:absolute;top:3px;left:3px;background:rgba(0,0,0,.8);color:#4ade80;font-size:.5rem;font-weight:800;padding:1px 4px;border-radius:3px;z-index:3;line-height:1.1}
.srch-grid .gc .gc-fav{position:absolute;top:3px;right:3px;width:18px;height:18px;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.6);font-size:.6rem;z-index:3;border-radius:50%;cursor:pointer}
.srch-empty{grid-column:1/-1;text-align:center;padding:40px;color:var(--t3);font-size:.8rem}
/* PROVIDER BROWSE */
.prov-ov{position:fixed;inset:0;z-index:400;background:var(--bg);display:none;flex-direction:column;opacity:0;transform:translateY(20px);transition:opacity .3s cubic-bezier(.16,1,.3,1),transform .3s cubic-bezier(.16,1,.3,1)}
.prov-ov.open{display:flex;opacity:1;transform:translateY(0)}
.prov-ov.closing{opacity:0;transform:translateY(20px)}
.prov-ov-body{overflow-x:hidden;max-width:100vw;flex:1;display:flex;min-height:0}
.prov-ov-side{width:88px;flex-shrink:0;overflow-y:auto;-webkit-overflow-scrolling:touch;overscroll-behavior:contain;touch-action:pan-y;scrollbar-width:none;border-right:1px solid var(--bd);padding:6px 0;height:100%}
.prov-ov-side::-webkit-scrollbar{display:none}
.po-prov{display:flex;flex-direction:column;align-items:center;gap:4px;padding:8px 4px;cursor:pointer;border-left:3px solid transparent}
.po-prov.on{background:rgba(var(--sec-rgb),.1);border-left-color:var(--sec)}
.po-prov .pp-logo{width:56px;height:56px;border-radius:12px;background:var(--s);border:none;display:flex;align-items:center;justify-content:center;overflow:hidden}
.po-prov.on .pp-logo{border-color:var(--sec);background:var(--sec)}
.po-prov.on .pp-logo img{filter:brightness(0) invert(1)}
.po-prov.on .pp-logo span{color:#fff}
.po-prov .pp-logo img{width:100%;height:100%;object-fit:cover}
.po-prov .pp-logo span{font-size:.55rem;font-weight:800;color:var(--t2)}
.po-prov.on .pp-logo span{color:var(--sec)}
.po-prov .pp-name{font-size:.5rem;font-weight:700;color:var(--t3);text-align:center;line-height:1.2;max-width:74px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.po-prov.on .pp-name{color:var(--sec)}
.prov-ov-games{flex:1;overflow-y:auto;-webkit-overflow-scrolling:touch;overscroll-behavior:contain;touch-action:pan-y;padding:8px;display:grid;grid-template-columns:repeat(3,1fr);gap:8px;align-content:start;height:100%}
.prov-ov-games .gc{border:1.5px solid var(--bd);border-radius:8px;position:relative;overflow:hidden;background:var(--bg2);width:100%;height:0;padding-bottom:100%;display:block;cursor:pointer}
.prov-ov-games .gc > img{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;display:block}
.prov-ov-games .gc .thumb{display:none}
.prov-ov-games .gwrap.no-image .gc > img{display:none}
.prov-ov-games .gwrap.no-image .gc .thumb{display:flex}
.prov-ov-games /* .gc .gn deprecated — gn now sibling of .gc inside .gwrap */
.prov-ov-games .gc .gn-name{font-size:.68rem;font-weight:700;color:var(--t);line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.prov-ov-games .gc .gn-prov{font-size:.55rem;font-weight:500;color:var(--t3);line-height:1.2;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.prov-ov-games .gc .gc-rtp{position:absolute;top:3px;left:3px;background:rgba(0,0,0,.8);color:#4ade80;font-size:.5rem;font-weight:800;padding:1px 4px;border-radius:3px;z-index:3;line-height:1.1}
.prov-ov-games .gc .gc-fav{position:absolute;top:3px;right:3px;width:18px;height:18px;background:rgba(0,0,0,.55);display:flex;align-items:center;justify-content:center;color:rgba(255,255,255,.6);font-size:.6rem;z-index:3;cursor:pointer;border-radius:50%}
.prov-ov-games .gc .gc-fav:hover{color:#ef4444}


/* ═══ GLOBAL ENTER/EXIT MOTION SYSTEM ═══ */
@keyframes contentFade{from{opacity:0;transform:translateY(8px);filter:blur(6px)}to{opacity:1;transform:translateY(0);filter:blur(0)}}
@keyframes pageEnter{from{opacity:0;filter:blur(8px)}to{opacity:1;filter:blur(0)}}
.banner-wrap{animation:contentFade .55s cubic-bezier(.16,1,.3,1) .05s both}
.qa-row{animation:contentFade .55s cubic-bezier(.16,1,.3,1) .12s both}
.prov-row{animation:contentFade .55s cubic-bezier(.16,1,.3,1) .19s both}

.sec-h{animation:contentFade .55s cubic-bezier(.16,1,.3,1) .33s both}
.prov-sec{animation:contentFade .55s cubic-bezier(.16,1,.3,1) .4s both}

/* Hover lift micro-interactions */
.qa-card,.prov-item,.gc{transition:transform .25s cubic-bezier(.16,1,.3,1),filter .25s ease}
.qa-card:active{transform:scale(.96)}
.prov-item:active .prov-diamond{transform:scale(.92)}
.gc:active{transform:scale(.96)}

/* Skeleton shimmer for image lazy-loads */
@keyframes skelShim{0%{background-position:-200% 0}100%{background-position:200% 0}}
.gc img:not([src]),.gc img[src=""],.b-img:not([src]),.b-img[src=""]{background:linear-gradient(90deg,var(--bg2) 25%,var(--tint-2) 50%,var(--bg2) 75%);background-size:200% 100%;animation:skelShim 1.4s ease-in-out infinite}

/* Smooth banner transition refine */
.banner-track{transition:transform .5s cubic-bezier(.16,1,.3,1) !important}


/* ═══ SKELETON LOADING SYSTEM (modern shimmer placeholder) ═══ */
@keyframes skelSweep{0%{background-position:-200% 0}100%{background-position:200% 0}}
.skel{background:linear-gradient(90deg,var(--bg2) 25%,var(--tint-2) 50%,var(--bg2) 75%);background-size:200% 100%;animation:skelSweep 1.4s ease-in-out infinite;border-radius:8px}
.skel-light{background:linear-gradient(90deg,rgba(0,0,0,.04) 25%,rgba(0,0,0,.08) 50%,rgba(0,0,0,.04) 75%);background-size:200% 100%;animation:skelSweep 1.4s ease-in-out infinite;border-radius:8px}
/* Skeleton wrappers — match real layout exactly */
.skel-banner{margin:0 12px;border-radius:14px;height:170px}
.skel-qa{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;padding:12px 14px}
.skel-qa-card{aspect-ratio:1/1;border-radius:14px}
.skel-prov{display:flex;gap:12px;padding:10px 14px 8px;overflow:hidden}
.skel-prov-item{flex-shrink:0;display:flex;flex-direction:column;align-items:center;gap:6px;width:60px}
.skel-prov-item .sk1{width:68px;height:68px;border-radius:14px}
.skel-prov-item .sk2{width:48px;height:8px;border-radius:4px}
.skel-sec-h{display:flex;align-items:center;justify-content:space-between;padding:12px 14px 8px}
.skel-sec-h .sk-title{width:120px;height:14px;border-radius:4px}
.skel-sec-h .sk-link{width:48px;height:10px;border-radius:4px}
.skel-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;padding:0 14px}
.skel-grid-card{aspect-ratio:1/1;border-radius:8px}
/* Smooth swap-in for real content */

.content-loaded{animation:contentReveal .45s cubic-bezier(.16,1,.3,1) both}
@keyframes contentReveal{from{opacity:0;filter:blur(6px)}to{opacity:1;filter:blur(0)}}
.skel-fade-out{animation:skelFadeOut .25s ease-out forwards}
@keyframes skelFadeOut{from{opacity:1}to{opacity:0}}


/* WELCOME POPUP */
.wp-overlay{position:fixed;inset:0;z-index:600;background:rgba(0,0,0,.55);display:none;flex-direction:column;align-items:center;justify-content:center;padding:20px}
.wp-overlay.show{display:flex}
.wp-box{width:100%;max-width:380px;border-radius:16px;border:2px solid rgba(var(--sec-rgb,56,189,248),.55);box-shadow:0 8px 40px rgba(0,0,0,.6);background:var(--bg2);position:relative}
.wp-close{width:42px;height:42px;border-radius:50%;background:var(--tint-3);border:2px solid var(--bd2);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;margin:14px auto 0;flex-shrink:0}
.wp-close svg{width:22px;height:22px}
.wp-content{min-height:380px;position:relative;overflow:hidden;border-radius:16px 16px 0 0}
.wp-slide{display:none;width:100%;min-height:380px;position:relative}
.wp-slide.active{display:block}
.wp-slide img{width:100%;display:block}
.wp-slide .wp-fb{min-height:380px;display:flex;align-items:center;justify-content:center;text-align:center;padding:30px}
.wp-tabs{display:flex;border-top:1px solid rgba(var(--sec-rgb,56,189,248),.35)}
.wp-tab{flex:1;padding:12px;text-align:center;font-size:.7rem;font-weight:700;color:var(--t3);cursor:pointer;position:relative}
.wp-tab.active{color:var(--pri)}
.wp-tab.active::after{content:'';position:absolute;bottom:0;left:10px;right:10px;height:2px;background:var(--pri)}
.wp-remind{display:flex;align-items:center;justify-content:center;gap:6px;padding:10px;font-size:.7rem;color:var(--t2)}
.wp-remind input{accent-color:var(--pri);width:14px;height:14px}

/* Bottom nav */
.bnav{position:fixed;bottom:0;left:0;right:0;z-index:100;display:flex;background:var(--nav-bg,#0a1628);padding:8px 0 env(safe-area-inset-bottom,6px);overflow:hidden;border-radius:14px 14px 0 0;box-shadow:0 -4px 20px rgba(0,0,0,.5);border-top:1px solid rgba(var(--sec-rgb,56,189,248),.2)}
.bnav::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent 2%,var(--pri) 15%,var(--gl,var(--pri2)) 50%,var(--pri) 85%,transparent 98%);animation:shimmer 3s linear infinite;background-size:200% 100%}
@keyframes shimmer{0%{background-position:-200% 0}100%{background-position:200% 0}}
.bnav-i{flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;padding:6px 0;color:var(--t3);font-size:.6rem;font-weight:600}.bnav-i.active{color:var(--sec)}.bnav-i svg{width:30px;height:30px}.bnav-i img{width:36px;height:36px;object-fit:contain}

.ft{padding:16px 14px 8px;background:var(--bg)}
.ft-title{font-size:.95rem;font-weight:700;color:var(--t2);margin-bottom:10px;display:flex;align-items:center;gap:6px}
.ft-title img{width:24px;height:24px;object-fit:contain}
.ft-prov{display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:16px}
.ft-p{display:flex;align-items:center;justify-content:center;padding:14px 10px;background:var(--s);border:1px solid var(--bd);border-radius:10px}
.ft-p-logo{height:32px;display:flex;align-items:center;justify-content:center}
.ft-p-logo img{height:28px;width:auto;object-fit:contain;filter:brightness(0) invert(1);opacity:.8}
.ft-p-logo span{font-size:.55rem;font-weight:800;color:var(--t2)}
.ft-p-name{font-size:.55rem;font-weight:700;color:var(--t3);text-align:center;line-height:1.2}
.ft-badges{display:flex;justify-content:center;gap:10px;margin-bottom:14px;flex-wrap:wrap}
.ft-b{display:flex;align-items:center;justify-content:center;gap:5px;padding:8px 14px;border:1px solid var(--bd);border-radius:8px;background:var(--s);font-size:.7rem;font-weight:800;color:var(--t2)}
.ft-b svg{width:18px;height:18px;flex-shrink:0}
.ft-social{display:flex;justify-content:center;gap:10px;margin-bottom:14px}
.ft-s{width:44px;height:44px;border-radius:50%;background:var(--s);border:1px solid var(--bd);display:flex;align-items:center;justify-content:center}
.ft-s svg{width:22px;height:22px;color:var(--t2)}
.ft-s:active{background:var(--s2)}
.ft-pays{display:flex;justify-content:center;gap:8px;flex-wrap:wrap;margin-bottom:14px}
.ft-py{padding:6px 10px;background:var(--s);border:1px solid var(--bd);border-radius:8px;display:flex;align-items:center;justify-content:center}
.ft-py img{height:20px;object-fit:contain}

/* ═══ Footer additions: logo, casino team, legal section ═══ */
.ft-logo-wrap{display:flex;align-items:center;justify-content:center;padding:18px 16px 8px;flex-direction:column;gap:6px}
.ft-logo{max-width:140px;max-height:80px;width:auto;height:auto;object-fit:contain;filter:drop-shadow(0 4px 14px rgba(var(--pri-rgb),.25))}
.ft-logo-text{font-family:'Chakra Petch','Poppins',sans-serif;font-size:1.25rem;font-weight:900;color:var(--pri);letter-spacing:.5px;text-transform:uppercase}
.ft-section{padding:14px 16px;border-bottom:1px solid var(--bd)}
.ft-title{font-size:.78rem;font-weight:800;color:var(--t);margin-bottom:10px;display:flex;align-items:center;gap:8px;letter-spacing:-.01em}
.ft-title svg{width:16px;height:16px;color:var(--pri);flex-shrink:0}
.ft-team{display:grid;grid-template-columns:1fr 1fr;gap:8px}
.ft-team-card{display:flex;align-items:center;gap:10px;padding:11px;background:var(--bg2);border:1px solid var(--bd);border-radius:11px;text-decoration:none;color:var(--t);transition:all .15s}
.ft-team-card:hover{border-color:var(--pri);transform:translateY(-1px);box-shadow:0 4px 12px rgba(var(--pri-rgb),.1)}
.ft-team-icon{width:34px;height:34px;border-radius:9px;background:linear-gradient(135deg,var(--pri),var(--pri-d));display:flex;align-items:center;justify-content:center;flex-shrink:0;color:#fff}
.ft-team-icon svg{width:16px;height:16px}
.ft-team-info{flex:1;min-width:0}
.ft-team-info b{font-size:.74rem;font-weight:800;color:var(--t);display:block;letter-spacing:-.01em}
.ft-team-info span{font-size:.6rem;color:var(--t3);font-weight:600;display:block;margin-top:1px}
.ft-legal-section{padding:14px 16px;border-bottom:1px solid var(--bd);text-align:center}
.ft-legal-section .ft-legal{font-size:.66rem;color:var(--t3);line-height:1.55;padding:0;border-top:none}
@media(max-width:380px){.ft-team{grid-template-columns:1fr}}
.ft-legal{text-align:center;font-size:.6rem;color:var(--t3);line-height:1.8;padding-top:10px;border-top:1px solid var(--bd)}
.ft-copy{text-align:center;font-size:.6rem;color:var(--t3);margin-top:8px}

.game-overlay{position:fixed;inset:0;z-index:9000;background:#000;display:none;opacity:0;transition:opacity .3s cubic-bezier(.16,1,.3,1)}
.game-overlay.show{display:block;opacity:1}
.game-overlay.closing{opacity:0}
.game-overlay.show{display:block}
.game-overlay iframe{width:100%;height:100%;border:none}
.fl-drag{position:fixed;z-index:9999;touch-action:auto;cursor:grab;user-select:none;-webkit-user-select:none}
.fl-btn{display:flex;flex-direction:column;align-items:center;gap:1px;padding:6px 7px;border-radius:11px;background:rgba(0,0,0,.7);border:1px solid rgba(255,215,0,.35);box-shadow:0 4px 12px rgba(0,0,0,.5);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px)}
.fl-btn .fl-icon{width:26px;height:26px;border-radius:50%;background:linear-gradient(135deg,var(--pri),var(--pri-d));display:flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(var(--pri-rgb),.5)}
.fl-btn .fl-icon svg{width:14px;height:14px;color:#fff}
.fl-btn .fl-label{font-size:.55rem;color:rgba(255,215,0,.95);font-weight:800;font-family:'Poppins';letter-spacing:.5px}

.game-loading{position:fixed;inset:0;z-index:9500;background:rgba(10,15,28,.55);backdrop-filter:blur(22px) saturate(140%);-webkit-backdrop-filter:blur(22px) saturate(140%);display:none;flex-direction:column;align-items:center;justify-content:center;gap:18px;opacity:0;transition:opacity .35s cubic-bezier(.16,1,.3,1)}
.game-loading.show{display:flex;opacity:1}
.gl-spin{width:140px;height:2px;background:rgba(255,255,255,.12);border-radius:2px;overflow:hidden;position:relative}
.gl-spin::before{content:"";position:absolute;left:0;top:0;width:35%;height:100%;background:linear-gradient(90deg,transparent,var(--pri) 50%,transparent);animation:lxShimmer 1.4s cubic-bezier(.4,0,.2,1) infinite;border-radius:2px}
@keyframes lxShimmer{0%{transform:translateX(-110%)}100%{transform:translateX(310%)}}
.gl-text{font-family:'Chakra Petch';font-size:.85rem;color:#fff;font-weight:600;letter-spacing:.5px;text-shadow:0 1px 6px rgba(0,0,0,.45)}
.app-toast{position:fixed;top:20px;left:50%;transform:translate(-50%,-30px);z-index:99999;background:rgba(15,23,42,.78);backdrop-filter:blur(16px) saturate(160%);-webkit-backdrop-filter:blur(16px) saturate(160%);border:1px solid rgba(255,255,255,.1);color:#fff;padding:11px 22px;border-radius:11px;font-family:Poppins,sans-serif;font-size:.78rem;font-weight:600;letter-spacing:.1px;opacity:0;transition:opacity .35s cubic-bezier(.16,1,.3,1),transform .35s cubic-bezier(.16,1,.3,1);pointer-events:none;max-width:85%;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,.35),0 0 0 1px rgba(var(--pri-rgb,14,165,233),.08)}
.app-toast.show{opacity:1;transform:translate(-50%,0)}
.app-toast::after{content:'';position:absolute;left:18%;right:18%;bottom:-1px;height:1.5px;background:linear-gradient(90deg,transparent,var(--pri),transparent);border-radius:1.5px;opacity:.6}

/* ═══ LOGIN/REGISTER MODALS (from index.php) ═══ */
.hdr-btns{display:flex;gap:8px;flex-shrink:0}
.btn-masuk{padding:9px 22px;border-radius:10px;font-weight:700;font-size:.85rem;background:transparent;border:1.5px solid var(--pri);color:var(--pri);cursor:pointer;transition:background .15s}
.btn-masuk:hover{background:var(--pri-l,rgba(var(--pri-rgb),.08))}
.btn-masuk:active{transform:scale(.96);background:var(--pri-l,rgba(var(--pri-rgb),.12))}
.btn-daftar{padding:9px 24px;border-radius:10px;font-weight:800;font-size:.85rem;background:var(--pri);color:#ffffff;border:none;cursor:pointer;transition:background .15s;letter-spacing:.3px}
.btn-daftar:hover{background:var(--pri-d,var(--pri))}
.btn-daftar:active{transform:scale(.95)}

.banner-wrap{position:relative;overflow:hidden;border-bottom:none;background:transparent}
.banner-track{display:flex;transition:transform .35s cubic-bezier(.25,.1,.25,1);will-change:transform}
.b-slide{min-width:100%;aspect-ratio:16/7;position:relative;overflow:hidden;background:transparent}
.b-slide .b-img{width:100%;height:100%;object-fit:cover}
.b-slide .b-fb{width:100%;height:100%;background:transparent}
.b-overlay{position:absolute;inset:0;background:transparent}
.b-content{position:absolute;bottom:0;left:0;right:0;padding:14px 16px;z-index:2}
.b-tag{display:inline-flex;align-items:center;gap:4px;background:var(--g);color:#1a1200;padding:3px 10px;border-radius:4px;font-size:.5rem;font-weight:800;letter-spacing:.5px;margin-bottom:6px;box-shadow:0 1px 4px rgba(0,0,0,.3)}
.b-tag svg{width:10px;height:10px}
.b-content h3{font-family:'Chakra Petch',sans-serif;font-size:1.15rem;font-weight:700;line-height:1.2;color:#fff;text-shadow:0 2px 8px rgba(0,0,0,.6)}
.b-content h3 .hl{color:var(--gl)}
.b-content p{font-size:.58rem;color:var(--bd2);margin-top:3px;font-weight:600}
.b-arr{position:absolute;top:50%;transform:translateY(-50%);width:20px;height:36px;background:rgba(0,0,0,.4);color:var(--bd2);display:flex;align-items:center;justify-content:center;z-index:3;font-size:.7rem}
.b-arr.prev{left:0;border-radius:0 5px 5px 0}.b-arr.next{right:0;border-radius:5px 0 0 5px}
.b-dots{display:flex;justify-content:center;gap:5px;padding:6px 0;background:var(--bg)}
.dot{width:6px;height:6px;border-radius:50%;background:var(--s3);transition:all .3s;border:none;cursor:pointer}
.dot.active{background:var(--g);width:18px;border-radius:3px;box-shadow:0 0 6px rgba(var(--sec-rgb),.55)}

.search-bar{display:flex;align-items:center;gap:8px;padding:8px 14px;background:var(--bg)}
.sb-main{flex:1;display:flex;align-items:center;gap:8px;background:var(--s);border:1px solid var(--bd);border-radius:25px;padding:8px 14px;overflow:hidden;height:44px}
.sb-icon{flex-shrink:0;width:24px;height:24px;border-radius:4px;overflow:hidden}
.sb-icon img{width:100%;height:100%;object-fit:contain}
.sb-marquee{flex:1;overflow:hidden;white-space:nowrap}
.sb-inner{display:inline-block;animation:marquee 18s linear infinite;font-size:.75rem;font-weight:600;color:var(--t2)}
.sb-btn{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--t2);background:var(--s);border:1px solid var(--bd)}
.sb-btn svg{width:20px;height:20px}
.sb-btn.gold{background:var(--s);border-color:var(--bd);color:var(--t)}

.qa-row{display:flex;gap:10px;padding:8px 14px;overflow-x:auto;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;scrollbar-width:none;cursor:grab;-webkit-transform:translateZ(0);transform:translateZ(0)}.qa-row:active{cursor:grabbing}.qa-row::-webkit-scrollbar{display:none}
.qa-card{flex-shrink:0;width:42%;height:110px;border-radius:14px;position:relative;overflow:hidden;border:none;cursor:pointer;box-shadow:none;background:transparent;scroll-snap-align:start}
.qa-card .qa-img{width:100%;height:100%;object-fit:cover;position:absolute;inset:0}
.qa-card .qa-fb{width:100%;height:100%;position:absolute;inset:0;padding:14px 12px;display:flex;flex-direction:column;justify-content:center}
.qa-card.c1 .qa-fb{background:linear-gradient(135deg,var(--sec-d,var(--sec-d)),var(--sec,var(--sec)))}.qa-card.c2 .qa-fb{background:linear-gradient(135deg,#0369a1,#34d399)}.qa-card.c3 .qa-fb{background:linear-gradient(135deg,var(--sec,var(--sec)),var(--sec-d,var(--sec-d)))}
.qa-label{font-size:.68rem;font-weight:800;color:#fff;line-height:1.3;text-transform:uppercase;text-shadow:0 1px 3px rgba(0,0,0,.3)}
.qa-val{font-family:'Chakra Petch',sans-serif;font-size:1.5rem;font-weight:700;color:#fff;text-shadow:0 2px 8px rgba(0,0,0,.4)}
.qa-sub{font-size:.52rem;color:rgba(255,255,255,.65);font-weight:600;line-height:1.4}

.prov-row{display:flex;gap:10px;padding:12px 14px 8px;overflow-x:auto;scrollbar-width:none;background:var(--bg)}.prov-row::-webkit-scrollbar{display:none}
.prov-item{flex-shrink:0;display:flex;flex-direction:column;align-items:center;gap:6px;width:80px}
.prov-diamond{width:68px;height:68px;border-radius:14px;display:flex;align-items:center;justify-content:center;border:none;background:var(--s);position:relative;overflow:hidden;transition:transform .15s}


.prov-diamond .inner{width:100%;height:100%;display:flex;align-items:center;justify-content:center}
.prov-diamond .inner img{width:80%;height:80%;object-fit:contain;filter:brightness(0) invert(1)}
.prov-diamond .inner span{font-size:.48rem;font-weight:800;color:#fff;}
.prov-name{font-size:.6rem;font-weight:700;color:var(--t3);text-align:center;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;width:100%}

.prov-sec{margin:10px 14px;border:1px solid var(--bd);border-radius:14px;overflow:hidden;background:var(--bg2);box-shadow:none}
.prov-sec-hdr{display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-bottom:1px solid var(--bd)}
.prov-sec-left{display:flex;align-items:center;gap:8px}
.prov-sec-logo{width:28px;height:28px;border-radius:6px;background:var(--s);border:1px solid var(--bd);display:flex;align-items:center;justify-content:center;overflow:hidden}
.prov-sec-logo img{width:22px;height:22px;object-fit:contain}
.prov-sec-logo span{font-size:.35rem;font-weight:800;color:var(--gl)}
.prov-sec-hdr h4{font-family:'Chakra Petch',sans-serif;font-size:.95rem;font-weight:700}
.prov-sec-hdr .ps-cnt{background:var(--s2);color:var(--t2);padding:2px 10px;border-radius:8px;font-size:.65rem;font-weight:700;margin-left:4px}
.prov-sec-right{display:flex;align-items:center;gap:5px}
.ps-arr{width:26px;height:26px;border-radius:5px;background:var(--s);border:1px solid var(--bd);color:var(--t3);display:flex;align-items:center;justify-content:center}
.ps-arr svg{width:10px;height:10px}
.ps-all{font-size:.78rem;color:var(--sec);font-weight:700}

.prov-sec-more{display:none}
.prov-sec-more span{font-size:.78rem;font-weight:700;color:var(--sec)}
.prov-sec-more svg{width:14px;height:14px;color:var(--sec)}

.sec-h{display:flex;align-items:center;justify-content:space-between;padding:14px 14px 8px}
.sec-hl{display:flex;align-items:center;gap:6px}
.sec-hl svg{width:22px;height:22px;color:var(--sec)}
.sec-h h3{font-family:'Chakra Petch',sans-serif;font-size:1rem;font-weight:700}
.sec-all{font-size:.78rem;color:var(--sec);font-weight:700}

.promo-list{display:flex;gap:10px;padding:8px 14px 10px;overflow-x:auto;scroll-snap-type:x mandatory;-webkit-overflow-scrolling:touch;scrollbar-width:none;cursor:grab;-webkit-transform:translateZ(0);transform:translateZ(0)}.promo-list:active{cursor:grabbing}.promo-list::-webkit-scrollbar{display:none}
.pc{flex-shrink:0;width:85%;border-radius:12px;overflow:hidden;border:none;background:transparent;position:relative;box-shadow:none;scroll-snap-align:start}
.pc::before{display:none}
.pc-ban{width:100%;aspect-ratio:21/9;position:relative;overflow:hidden;display:flex;align-items:center;justify-content:center}
.pc-ban>img{width:100%;height:100%;object-fit:cover;position:absolute;inset:0}
.pc-ban .pcbg{position:absolute;inset:0}
.pc-ban .pcc{position:relative;z-index:1;text-align:center;padding:10px}
.pc-ban .pci{width:32px;height:32px;margin:0 auto 4px;opacity:.5}
.pc-ban .pci img{width:100%;height:100%;object-fit:contain}
.pc-ban .pcl{font-size:.58rem;font-weight:700;color:rgba(255,255,255,.75);text-transform:uppercase;letter-spacing:.5px}
.pc-ban .pca{font-family:'Chakra Petch',sans-serif;font-size:1.7rem;font-weight:700;color:#fff;text-shadow:0 2px 12px rgba(0,0,0,.5)}
.pc-ban .pcs{font-size:.52rem;font-weight:700;color:var(--gl)}
.pc-ban .notif{position:absolute;top:8px;right:8px;width:14px;height:14px;border-radius:50%;background:#ef4444;border:2px solid var(--s);display:flex;align-items:center;justify-content:center}
.pc-ban .notif svg{width:8px;height:8px;color:#fff}
.pc-info{display:flex;align-items:center;justify-content:space-between;padding:10px 14px}
.pc-info p{font-size:.65rem;color:var(--t2);font-weight:600;flex:1;margin-right:10px}
.btn-proc{padding:7px 18px;border-radius:20px;font-size:.65rem;font-weight:700;color:var(--g);background:var(--s2);border:1px solid var(--bdg)}

.fl-cs{position:fixed;left:12px;bottom:76px;z-index:90;width:52px;height:52px;border-radius:50%;overflow:hidden;animation:csFloat 3s ease-in-out infinite;will-change:transform;cursor:pointer}
@keyframes csFloat{0%,100%{transform:translateY(0)}50%{transform:translateY(-6px)}}
.fl-cs:active{animation:none;transform:scale(.92)!important}
.fl-invite{position:fixed;right:6px;bottom:96px;z-index:90;width:110px;border-radius:10px;overflow:hidden;animation:slideR .5s ease both;border:none;box-shadow:none;background:transparent;pointer-events:auto}
.fl-invite .fi-x{position:absolute;top:4px;right:4px;width:18px;height:18px;border-radius:50%;background:rgba(0,0,0,.5);color:#fff;font-size:.55rem;display:flex;align-items:center;justify-content:center;border:none;cursor:pointer;z-index:2}
.fl-invite .fi-body{position:relative;overflow:hidden}
.fl-invite .fi-body>img{width:100%;display:block}
.fl-invite .fi-fb{background:linear-gradient(135deg,#0c4a6e,#0369a1);padding:12px 10px;text-align:center}
.fl-invite .fi-fb .fi-title{font-size:.52rem;font-weight:700;color:rgba(255,255,255,.8);text-transform:uppercase;line-height:1.3}
.fl-invite .fi-fb .fi-val{font-family:'Chakra Petch',sans-serif;font-size:1.1rem;font-weight:700;color:#fff}
.fl-invite .fi-fb .fi-sub{font-size:.42rem;color:var(--gl);font-weight:700;display:flex;align-items:center;justify-content:center;gap:3px;margin-top:2px}
.fl-invite .fi-fb .fi-sub svg{width:10px;height:10px}
.fl-mine{position:fixed;right:6px;bottom:220px;z-index:90;width:110px;border-radius:10px;overflow:hidden;animation:slideR .5s ease .2s both;box-shadow:0 4px 20px rgba(0,0,0,.5);border:none}
.fl-mine .fm-x{position:absolute;top:4px;right:4px;width:18px;height:18px;border-radius:50%;background:rgba(0,0,0,.5);color:#fff;font-size:.55rem;display:flex;align-items:center;justify-content:center;border:none;cursor:pointer;z-index:2}
.fl-mine .fm-body{position:relative;overflow:hidden}
.fl-mine .fm-body>img{width:100%;display:block}
.fl-mine .fm-fb{background:linear-gradient(135deg,#991b1b,#dc2626);padding:10px;text-align:center}
.fl-mine .fm-fb .fm-title{font-size:.52rem;font-weight:700;color:rgba(255,255,255,.85)}
.fl-mine .fm-fb .fm-timer{font-family:'Chakra Petch',sans-serif;font-size:1.15rem;font-weight:700;color:#fff;letter-spacing:2px;text-shadow:0 0 10px var(--bd2);margin-top:2px}

.ft{padding:16px 14px 8px;background:var(--bg)}
.ft-title{font-size:.95rem;font-weight:700;color:var(--t2);margin-bottom:10px;display:flex;align-items:center;gap:6px}
.ft-title svg{width:14px;height:14px}
.ft-prov{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:14px}
.ft-p{display:flex;align-items:center;justify-content:center;padding:14px 10px;background:var(--s);border:1px solid var(--bd);border-radius:10px}
.ft-p-logo{height:32px;display:flex;align-items:center;justify-content:center}
.ft-p-logo img{height:28px;width:auto;object-fit:contain;filter:brightness(0) invert(1);opacity:.8}
.ft-p-logo span{font-size:.55rem;font-weight:800;color:var(--t2)}

.ft-badges{display:flex;justify-content:center;gap:8px;margin-bottom:12px;align-items:center}
.ft-b{display:flex;align-items:center;justify-content:center;gap:5px;padding:8px 14px;border:1px solid var(--bd);border-radius:8px;background:var(--s);font-size:.7rem;font-weight:800;color:var(--t2)}
.ft-b svg{width:18px;height:18px;flex-shrink:0}
.ft-b img{height:16px;object-fit:contain}
.ft-b span{font-size:.5rem;font-weight:700;color:var(--t3)}
.ft-social{display:flex;justify-content:center;gap:8px;margin-bottom:12px}
.ft-s{width:38px;height:38px;border-radius:50%;background:var(--tint-2);border:1px solid var(--tint-3);display:flex;align-items:center;justify-content:center;transition:all .15s}
.ft-s:active{background:var(--tint-3)}
.ft-s svg{width:22px;height:22px;color:var(--t2);object-fit:contain}
.ft-pays{display:flex;justify-content:center;gap:6px;flex-wrap:wrap;margin-bottom:12px}
.ft-py{padding:5px 8px;background:var(--s);border:1px solid var(--bd);border-radius:6px;display:flex;align-items:center;justify-content:center}
.ft-py img{height:20px;object-fit:contain}
.ft-legal{text-align:center;font-size:.52rem;color:var(--t3);line-height:1.8;padding-top:10px;border-top:1px solid var(--bd)}
.ft-copy{text-align:center;font-size:.52rem;color:var(--t3);margin-top:8px}

.bnav{position:fixed;bottom:0;left:0;right:0;z-index:100;display:flex;background:var(--nav-bg,#0a1628);padding:8px 0 env(safe-area-inset-bottom,6px);overflow:hidden;border-radius:14px 14px 0 0;box-shadow:0 -4px 20px rgba(0,0,0,.5);border-top:1px solid rgba(var(--sec-rgb,56,189,248),.2)}
.bnav::before{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent 2%,var(--pri) 15%,var(--gl) 50%,var(--pri) 85%,transparent 98%);animation:shimmer 3s linear infinite;background-size:200% 100%}
.bnav-i{flex:1;display:flex;flex-direction:column;align-items:center;gap:3px;padding:6px 0;color:var(--t3);font-size:.6rem;font-weight:600}.bnav-i.active{color:var(--sec)}.bnav-i svg{width:30px;height:30px}.bnav-i img{width:36px;height:36px;object-fit:contain}

/* ═══ LOGIN/REGISTER MODAL — polished ═══ */
.mo{position:fixed;inset:0;z-index:500;background:rgba(0,0,0,0);backdrop-filter:blur(0);-webkit-backdrop-filter:blur(0);display:none;align-items:flex-end;justify-content:center;transition:background .35s cubic-bezier(.16,1,.3,1),backdrop-filter .35s cubic-bezier(.16,1,.3,1)}
.mo.active{display:flex;background:rgba(0,0,0,.45);backdrop-filter:blur(14px) saturate(140%);-webkit-backdrop-filter:blur(14px) saturate(140%)}
@keyframes mbSlide{from{transform:translateY(100%);opacity:0}60%{opacity:1}to{transform:translateY(0);opacity:1}}
@keyframes mbSlideOut{from{transform:translateY(0);opacity:1}to{transform:translateY(100%);opacity:0}}
.mo.closing{background:rgba(0,0,0,0);backdrop-filter:blur(0);-webkit-backdrop-filter:blur(0)}
.mo.closing .mb{animation:mbSlideOut .3s cubic-bezier(.4,0,1,1) forwards}
.mb{
  background:var(--bg2);
  border-top-left-radius:26px;border-top-right-radius:26px;
  width:100%;max-width:480px;max-height:92vh;
  overflow-y:auto;padding:28px 22px 36px;
  position:relative;
  border-top:1px solid var(--bd2);
  box-shadow:0 -16px 50px rgba(0,0,0,.18);
  animation:mbSlide .35s cubic-bezier(.32,.72,0,1);
}
.mb::before{content:'';position:absolute;top:8px;left:50%;transform:translateX(-50%);width:42px;height:4px;background:var(--bd2);border-radius:3px}
.mb::after{content:'';position:absolute;top:0;left:0;right:0;height:2px;background:linear-gradient(90deg,transparent,var(--pri),transparent);opacity:.6}
.mb .mx{position:absolute;top:18px;right:18px;width:34px;height:34px;border-radius:50%;background:var(--tint-1);border:1px solid var(--bd);display:flex;align-items:center;justify-content:center;color:var(--t2);cursor:pointer;transition:all .15s;z-index:2}
.mb .mx:hover{background:var(--tint-2);color:var(--t)}
.mb .mx:active{transform:scale(.92)}
.mb .mx svg{width:14px;height:14px}
.mb h2{font-family:'Chakra Petch','Poppins',sans-serif;font-size:1.35rem;font-weight:800;margin:6px 0 4px;color:var(--t);letter-spacing:-.02em}
.mb .sub{color:var(--t2);font-size:.78rem;margin-bottom:22px;font-weight:500}
.mb .sub a{color:var(--pri);font-weight:800;cursor:pointer;text-decoration:none}
.mb .sub a:hover{text-decoration:underline}

/* Input field wrappers — properly visible boxes */
.fg{margin-bottom:14px}
.iw{
  display:flex;align-items:center;
  background:var(--tint-1);
  border:1.5px solid var(--bd);
  border-radius:12px;
  overflow:hidden;
  transition:all .18s;
  position:relative;
}
.iw:hover{border-color:var(--bd2)}
.iw:focus-within{
  border-color:var(--pri);
  background:var(--bg2);
  box-shadow:0 0 0 4px var(--pri-l);
}
.iw .pfx{
  padding:0 12px;
  font-size:.74rem;font-weight:800;
  color:var(--t2);
  border-right:1.5px solid var(--bd);
  display:flex;align-items:center;gap:6px;
  flex-shrink:0;height:48px;
}
.iw .pfx .fl{width:20px;height:14px;border-radius:3px;overflow:hidden;display:flex;flex-direction:column;box-shadow:0 1px 2px rgba(0,0,0,.1)}
.iw .pfx .fl span:first-child{flex:1;background:#ef4444}
.iw .pfx .fl span:last-child{flex:1;background:#fff}
.iw input{
  border:none;background:transparent;
  padding:14px 14px;
  color:var(--t);
  font-family:'Poppins',sans-serif;
  font-size:.86rem;font-weight:500;
  width:100%;outline:none;
  letter-spacing:.01em;
}
.iw input::placeholder{color:var(--t3);font-weight:400}
.iw input:-webkit-autofill,.iw input:-webkit-autofill:hover,.iw input:-webkit-autofill:focus,.iw input:-webkit-autofill:active{
  -webkit-box-shadow:0 0 0 60px var(--bg2) inset!important;
  -webkit-text-fill-color:var(--t)!important;
  caret-color:var(--t)!important;
  transition:background-color 5000s ease-in-out 0s;
}
.iw .ico{
  padding:0 14px;color:var(--t3);
  display:flex;align-items:center;cursor:pointer;
  min-width:44px;min-height:44px;justify-content:center;
  transition:color .15s;
}
.iw .ico:hover{color:var(--pri)}
.iw .ico svg{width:18px;height:18px}

/* Phone prefix has key-icon padding fix */
.iw .pfx[style*="padding-right:0"]{padding-left:14px;padding-right:0}
.iw .pfx svg{width:18px;height:18px;color:var(--t2)}

/* Remember + forgot row */
.fg-row{display:flex;align-items:center;justify-content:space-between;margin:18px 0 20px}
.fg-row label{display:flex;align-items:center;gap:8px;font-size:.74rem;color:var(--t2);font-weight:600;cursor:pointer;user-select:none}
.fg-row label input[type=checkbox]{
  appearance:none;-webkit-appearance:none;
  width:18px;height:18px;
  background:var(--tint-1);
  border:1.5px solid var(--bd2);
  border-radius:5px;
  cursor:pointer;
  position:relative;
  transition:all .15s;
  flex-shrink:0;
}
.fg-row label input[type=checkbox]:checked{
  background:var(--pri);border-color:var(--pri);
}
.fg-row label input[type=checkbox]:checked::after{
  content:'';position:absolute;top:2px;left:5px;
  width:5px;height:9px;
  border:solid #fff;border-width:0 2.5px 2.5px 0;
  transform:rotate(45deg);
}
.fg-row a{
  font-size:.74rem;color:var(--pri);font-weight:700;
  text-decoration:none;transition:color .15s;
}
.fg-row a:hover{color:var(--pri-d);text-decoration:underline}

/* Submit button — bigger, with shadow */
.btn-sub{
  width:100%;padding:15px;
  border-radius:12px;
  font-size:.92rem;font-weight:800;
  background:linear-gradient(135deg,var(--pri),var(--pri-d));
  color:#fff;border:none;cursor:pointer;
  font-family:inherit;
  transition:all .18s;
  letter-spacing:.4px;
  box-shadow:0 6px 18px rgba(var(--pri-rgb),.32);
  position:relative;overflow:hidden;
}
.btn-sub::before{
  content:'';position:absolute;top:0;left:-100%;width:100%;height:100%;
  background:linear-gradient(90deg,transparent,var(--bd2),transparent);
  transition:left .5s;
}
.btn-sub:hover{box-shadow:0 8px 24px rgba(var(--pri-rgb),.45);transform:translateY(-1px)}
.btn-sub:hover::before{left:100%}
.btn-sub:active{transform:translateY(0) scale(.99);box-shadow:0 4px 12px rgba(var(--pri-rgb),.3)}
.btn-sub:disabled{opacity:.6;cursor:not-allowed;transform:none}

/* Hide broken logo image until JS confirms it loads */
.logo-img:not([src]),.logo-img[src=""]{display:none!important}

</style>
<link rel="stylesheet" href="theme.php?v=<?=time()?>">
</head>
<body>

<header class="hdr">
    <div class="hdr-left">
        <button class="hdr-menu" onclick="toggleSB()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" y1="6" x2="20" y2="6"/><line x1="4" y1="12" x2="14" y2="12"/><line x1="4" y1="18" x2="20" y2="18"/></svg></button>
        <img class="logo-img" id="dashLogo" alt="" style="display:none">
    </div>
    <?php if($isLoggedIn): ?>
    <div class="hdr-right"><svg id="hdrSaldoIcon" viewBox="0 0 24 24" fill="none" stroke="var(--pri)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" style="width:34px;height:34px"><circle cx="12" cy="12" r="10" fill="var(--pri-l)"/><path d="M12 7v8" stroke="var(--pri)"/><polyline points="8 11 12 15 16 11"/></svg><div class="hdr-bal"><div class="bal-val" id="hBal">0.00K</div><div class="bal-label">Saldo</div></div></div>
    <?php else: ?>
    <div class="hdr-btns"><button class="btn-masuk" onclick="openM('login')">Masuk</button><button class="btn-daftar" onclick="openM('register')">Daftar</button></div>
    <?php endif; ?>
</header>

<div class="banner-wrap"><div class="banner-track" id="bTrack"><div class="skel-banner skel" style="width:100%"></div></div><button class="b-arr prev" onclick="goS((cs-1+ts)%ts)">&#8249;</button><button class="b-arr next" onclick="goS((cs+1)%ts)">&#8250;</button></div><div class="b-dots" id="bDots"></div>

<div class="search-bar"><div class="sb-main"><div class="sb-icon"><svg viewBox="0 0 24 24" fill="var(--sec)" width="18" height="18"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0" fill="none" stroke="var(--sec)" stroke-width="2"/></svg></div><div class="sb-marquee"><span class="sb-inner" id="sbMarquee"></span></div></div><?php if($isLoggedIn): ?><button class="sb-btn" onclick="location.href='cs.php'"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M18 16v2a2 2 0 01-2 2H8a2 2 0 01-2-2v-2"/><path d="M20 10a8 8 0 10-16 0v4a2 2 0 002 2h1v-4a5 5 0 0110 0v4h1a2 2 0 002-2z"/></svg></button><?php else: ?><button class="sb-btn" onclick="openM('login')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></button><?php endif; ?><button class="sb-btn green" onclick="openSrch()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></button></div>

<div class="qa-row" id="qaRow"><div class="skel-qa" style="width:100%"><div class="skel-qa-card skel"></div><div class="skel-qa-card skel"></div><div class="skel-qa-card skel"></div><div class="skel-qa-card skel"></div></div></div>
<div class="prov-row" id="provRow"><div class="skel-prov"><div class="skel-prov-item"><div class="sk1 skel"></div><div class="sk2 skel"></div></div><div class="skel-prov-item"><div class="sk1 skel"></div><div class="sk2 skel"></div></div><div class="skel-prov-item"><div class="sk1 skel"></div><div class="sk2 skel"></div></div><div class="skel-prov-item"><div class="sk1 skel"></div><div class="sk2 skel"></div></div><div class="skel-prov-item"><div class="sk1 skel"></div><div class="sk2 skel"></div></div><div class="skel-prov-item"><div class="sk1 skel"></div><div class="sk2 skel"></div></div><div class="skel-prov-item"><div class="sk1 skel"></div><div class="sk2 skel"></div></div><div class="skel-prov-item"><div class="sk1 skel"></div><div class="sk2 skel"></div></div></div></div>

<div class="sec-h"><div class="sec-hl"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 23c-3.6 0-7-2.5-7-7 0-3.2 2-6.5 4-8.5.4-.4 1-.1 1 .4 0 1.3.8 2.5 1.6 3.2.2-.5.4-1.2.4-2 0-.8-.2-1.7-.5-2.4-.2-.4.1-.9.6-.9C15.4 6 19 10 19 14c0 5.5-3.8 9-7 9z"/></svg><h3>Populer</h3><span class="cnt" id="popCnt">0</span></div><div style="display:flex;align-items:center;gap:5px"><button class="ps-arr" onclick="scrollSec('pop',-1)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg></button><button class="ps-arr" onclick="scrollSec('pop',1)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg></button><a class="sec-all" href="games.php">Semua</a></div></div>
<div class="prov-sec" style="margin-top:0"><div class="prov-sec-grid" id="popGrid"><div class="skel-grid-card skel"></div><div class="skel-grid-card skel"></div><div class="skel-grid-card skel"></div><div class="skel-grid-card skel"></div><div class="skel-grid-card skel"></div><div class="skel-grid-card skel"></div></div><div class="prov-sec-more" id="popMore" onclick="toggleExpandPop()"><span>Semua</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg></div></div>
<!-- PER-PROVIDER GAME SECTIONS — populated via renderProvSections() -->
<div id="provSections"></div>
<!-- SEARCH OVERLAY (simple) -->
<div class="srch-ov" id="srchOv">
<div class="srch-hdr"><button onclick="closeSrch()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button><h2>Pencarian</h2><div style="width:32px"></div></div>
<div class="srch-input"><input type="text" id="srchQ" placeholder="Pencarian Permainan" oninput="doSearch()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></div>
<div class="srch-pills">
<div class="srch-pill on" onclick="srchPill('semua',this)">Semua</div>
<div class="srch-pill" onclick="srchPill('populer',this)">Populer</div>
<div class="srch-pill" onclick="srchPill('terkini',this)">Terkini</div>
<div class="srch-pill" onclick="srchPill('favorit',this)">Favorit</div>
</div>
<div class="srch-grid" id="srchGrid"></div>
</div>

<!-- PROVIDER BROWSE OVERLAY -->
<div class="prov-ov" id="provOv">
<div class="srch-hdr"><button onclick="closeProvOv()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 18 9 12 15 6"/></svg></button><h2>Permainan</h2><div style="width:32px"></div></div>
<div class="srch-input"><input type="text" id="provQ" placeholder="Pencarian Permainan" oninput="doProvSearch()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg></div>
<div class="prov-ov-body">
<div class="prov-ov-side" id="provOvSide"></div>
<div class="prov-ov-games" id="provOvGrid"></div>
</div>
</div>

<!-- WELCOME POPUP -->
<div class="wp-overlay" id="wpOverlay">
<div class="wp-box">
<div class="wp-content"></div>
<div class="wp-tabs"></div>
<div class="wp-remind"><input type="checkbox" id="wpNoShow"> Jangan ingatkan lagi hari ini</div>
</div><button class="wp-close" onclick="closeWP()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button></div>

<?php echo renderBnav($db,'beranda',$isLoggedIn); ?>

<!-- SIDEBAR -->
<style>
/* ═══ SIDEBAR — Editorial Cabinet (text-led, refined) ═══ */
.sb-overlay{position:fixed;inset:0;background:rgba(0,0,0,.42);z-index:998;opacity:0;pointer-events:none;transition:opacity .25s ease}
.sb-drawer{position:fixed;top:0;left:-300px;width:300px;height:100%;background:var(--bg2);z-index:999;transition:left .3s cubic-bezier(.32,.72,0,1);display:flex;flex-direction:column;box-shadow:1px 0 0 var(--tint-2),12px 0 36px -12px rgba(0,0,0,.35)}
.sb-drawer.open{left:0}
.sb-overlay.open{opacity:1;pointer-events:auto}
/* Subtle grain texture overlay (the "texture" requested) */
.sb-drawer::before{content:'';position:absolute;inset:0;background-image:url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='200' height='200'><filter id='n'><feTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='2' stitchTiles='stitch'/><feColorMatrix values='0 0 0 0 1 0 0 0 0 1 0 0 0 0 1 0 0 0 0.06 0'/></filter><rect width='100%' height='100%' filter='url(%23n)'/></svg>");opacity:.5;mix-blend-mode:overlay;pointer-events:none;z-index:0}
.sb-drawer > *{position:relative;z-index:1}
/* Header — minimal */
.sb-hdr{display:flex;align-items:center;padding:14px 18px;flex-shrink:0;border-bottom:1px solid var(--tint-2)}
.sb-close{width:30px;height:30px;background:transparent;border:1px solid var(--tint-2);border-radius:50%;color:var(--t2);display:flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;font-size:.8rem;transition:background .15s,border-color .15s}
.sb-close:active{background:var(--tint-2)}
.sb-hdr-title{flex:1;text-align:center;font-family:'Chakra Petch',serif;font-weight:700;letter-spacing:2px;color:var(--t);font-size:.92rem;text-transform:uppercase}
/* Profile — subtle, no shadow */
.sb-prof{display:flex;align-items:center;gap:14px;padding:18px;border-bottom:1px solid var(--tint-2);flex-shrink:0}
.sb-prof-av{width:50px;height:50px;border-radius:50%;background:var(--tint-2);display:flex;align-items:center;justify-content:center;color:var(--t);font-weight:700;font-size:1.1rem;flex-shrink:0;position:relative;font-family:'Chakra Petch',serif}
/* VIP ring — single color, refined, NO animation */
.sb-prof-av::before{content:'';position:absolute;inset:-3px;border-radius:50%;border:1.5px solid var(--vip-c,var(--tint-3));pointer-events:none}
.sb-prof-av.v0{--vip-c:var(--tint-3)}
.sb-prof-av.v1{--vip-c:#cd7f32}
.sb-prof-av.v2{--vip-c:#a5a5a5}
.sb-prof-av.v3{--vip-c:#d4af37}
.sb-prof-av.v4{--vip-c:#e5e4e2}
.sb-prof-av.v5{--vip-c:#7dd3fc}
.sb-prof-vip{position:absolute;bottom:-5px;left:50%;transform:translateX(-50%);background:var(--bg2);color:var(--vip-c);font-size:.5rem;font-weight:800;padding:1px 6px;border-radius:3px;letter-spacing:1px;line-height:1.4;white-space:nowrap;border:1px solid var(--vip-c);font-family:'Chakra Petch',serif}
.sb-prof-info{flex:1;min-width:0}
.sb-prof-name{font-size:.88rem;font-weight:700;color:var(--t);letter-spacing:-.005em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;line-height:1.25}
.sb-prof-id{font-size:.62rem;color:var(--t3);font-weight:500;margin-top:3px;font-family:'JetBrains Mono','SF Mono',monospace;letter-spacing:.3px}
/* Balance — flat, refined */
.sb-bal{display:flex;align-items:center;gap:12px;padding:14px 18px;border-bottom:1px solid var(--tint-2);flex-shrink:0}
.sb-bal-icon{width:32px;height:32px;border-radius:50%;background:var(--tint-1);border:1px solid var(--tint-2);display:flex;align-items:center;justify-content:center;color:var(--t2);flex-shrink:0}
.sb-bal-l{flex:1;min-width:0}
.sb-bal-amt{font-family:'Chakra Petch',monospace;font-size:1rem;font-weight:700;color:var(--t);letter-spacing:.3px;line-height:1.1}
.sb-bal-lbl{font-size:.55rem;color:var(--t3);margin-top:3px;letter-spacing:1.5px;text-transform:uppercase;font-weight:600}
.sb-dep-btn{background:var(--t);color:var(--bg2);padding:7px 14px;border-radius:5px;font-size:.68rem;font-weight:700;text-decoration:none;border:none;cursor:pointer;letter-spacing:.5px;flex-shrink:0;text-transform:uppercase;transition:opacity .15s}
.sb-dep-btn:active{opacity:.7}
/* Auth buttons (guest) */
.sb-auth{padding:14px 18px;display:flex;gap:8px;border-bottom:1px solid var(--tint-2);flex-shrink:0}
.sb-auth button{flex:1;padding:10px;border-radius:6px;font-size:.74rem;font-weight:700;font-family:inherit;cursor:pointer;letter-spacing:.5px;border:none;transition:opacity .12s;text-transform:uppercase}
.sb-auth button:active{opacity:.75}
.sb-auth .btn-masuk{background:transparent;color:var(--t);border:1px solid var(--tint-3)}
.sb-auth .btn-daftar{background:var(--t);color:var(--bg2)}
/* Scrollable nav — text-led list */
.sb-nav-wrap{flex:1;overflow-y:auto;-webkit-overflow-scrolling:touch;overscroll-behavior:contain;min-height:0;padding:6px 0;scrollbar-width:thin;scrollbar-color:var(--tint-2) transparent}
.sb-nav-wrap::-webkit-scrollbar{width:3px}
.sb-nav-wrap::-webkit-scrollbar-thumb{background:var(--tint-2);border-radius:3px}
/* Section label */
.sb-section-title{font-size:.55rem;font-weight:700;color:var(--t3);text-transform:uppercase;letter-spacing:1.8px;padding:14px 18px 6px;font-family:'Chakra Petch',serif}
/* 2-column grid container — text-led row style */
.sb-grid{display:grid;grid-template-columns:1fr 1fr;gap:1px;background:var(--tint-2);border-top:1px solid var(--tint-2);border-bottom:1px solid var(--tint-2);margin-bottom:6px}
/* Menu item — text-led with small icon circle, inline */
.sb-link{display:flex;align-items:center;gap:10px;padding:11px 14px;color:var(--t2);font-size:.76rem;font-weight:550;text-decoration:none;transition:background .15s,color .15s;position:relative;letter-spacing:-.005em;background:var(--bg2);min-width:0}
.sb-link:hover{background:var(--tint-1);color:var(--t)}
.sb-link.active{color:var(--t);background:var(--tint-1);font-weight:700}
.sb-link.active::before{content:'';position:absolute;left:0;top:8px;bottom:8px;width:2px;background:var(--pri);border-radius:0 2px 2px 0}
.sb-link-label{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
/* Icon block — small bulatan */
.sb-link-ic{width:26px;height:26px;border-radius:50%;background:var(--tint-1);border:1px solid var(--tint-2);display:flex;align-items:center;justify-content:center;color:var(--t);flex-shrink:0;transition:background .15s,border-color .15s}
.sb-link-ic svg{width:13px;height:13px;opacity:.85}
.sb-link-ic img{width:16px;height:16px;object-fit:contain;border-radius:50%}
.sb-link:hover .sb-link-ic{background:var(--tint-2);border-color:var(--tint-3)}
.sb-link.active .sb-link-ic{background:rgba(var(--pri-rgb),.15);border-color:rgba(var(--pri-rgb),.4);color:var(--pri)}
.sb-link.active .sb-link-ic svg{opacity:1}
/* Right meta — subtle */
.sb-link-meta{font-size:.55rem;color:var(--t3);font-weight:600;letter-spacing:.5px;font-family:'JetBrains Mono',monospace;flex-shrink:0}
/* Full-width row variant (Live Chat, Profil, Logout) */
.sb-link.full{grid-column:1/-1}
.sb-link.red{color:var(--t3)}
.sb-link.red .sb-link-ic{color:var(--t3);background:transparent}
.sb-link.red:hover{color:#ef4444;background:rgba(239,68,68,.06)}
.sb-link.red:hover .sb-link-ic{color:#ef4444;border-color:rgba(239,68,68,.3);background:rgba(239,68,68,.08)}
/* Section divider */
.sb-divider{height:1px;background:var(--tint-2);margin:8px 18px}
/* Socials — refined hairline */
.sb-socials{display:flex;gap:10px;padding:14px 18px;border-top:1px solid var(--tint-2);flex-shrink:0;justify-content:center;align-items:center}
.sb-soc{width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--t2);text-decoration:none;background:transparent;border:1px solid var(--tint-3);transition:background .15s,border-color .15s,color .15s,transform .12s}
.sb-soc:hover{border-color:var(--t3);color:var(--t)}
.sb-soc:active{transform:scale(.92)}
.sb-soc svg{width:14px;height:14px}
</style>
<div class="sb-overlay" id="sO" onclick="toggleSB()"></div>
<aside class="sb-drawer" id="sB">
<div class="sb-hdr">
<button class="sb-close" onclick="toggleSB()">&#10005;</button>
<div class="sb-hdr-title"><?=htmlspecialchars($SI['site_name']??'K7777')?></div>
<div style="width:30px"></div>
</div>

<?php
$_menuIc=[];
try{$q=$db->query("SELECT `key`,`value` FROM settings WHERE `key` LIKE 'menu_icon_%'");foreach($q->fetchAll() as $r)$_menuIc[$r['key']]=$r['value'];}catch(Exception $e){}
function _sbIc($key,$svgFallback){global $_menuIc;$img=$_menuIc['menu_icon_'.$key]??'';if($img)return '<img src="'.htmlspecialchars($img).'" alt="">';return $svgFallback;}
if($isLoggedIn):
  $uvip=0;$uname='User';$uphone='';$uid_show='';
  try{$us=$db->prepare("SELECT phone,vip_level,id FROM users WHERE id=?");$us->execute([$uid]);$urow=$us->fetch();
    if($urow){$uvip=(int)($urow['vip_level']??0);$uphone=$urow['phone']??'';$uid_show=$urow['id']??'';
    if($uphone){$uname=substr($uphone,0,4).str_repeat('*',6).substr($uphone,-2);}}
  }catch(Exception $e){}
?>
<div class="sb-prof">
  <div class="sb-prof-av v<?=min($uvip,5)?>"><?=strtoupper(substr($uname,0,1))?><div class="sb-prof-vip">VIP <?=$uvip?></div></div>
  <div class="sb-prof-info">
    <div class="sb-prof-name"><?=htmlspecialchars($uname)?></div>
    <div class="sb-prof-id">ID <?=htmlspecialchars($uid_show)?></div>
  </div>
</div>
<div class="sb-bal">
<div class="sb-bal-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" width="16" height="16"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="17" cy="12" r="2"/></svg></div>
<div class="sb-bal-l"><div class="sb-bal-amt" id="sbBal2">0.00K</div><div class="sb-bal-lbl">Saldo</div></div>
<a href="deposit.php" class="sb-dep-btn">Deposit</a>
</div>
<?php else: ?>
<div class="sb-auth">
<button class="btn-masuk" onclick="openM('login');toggleSB()">Masuk</button>
<button class="btn-daftar" onclick="openM('register');toggleSB()">Daftar</button>
</div>
<?php endif; ?>

<div class="sb-nav-wrap">
<div class="sb-section-title">Menu Utama</div>
<div class="sb-grid">
<a href="dashboard.php" class="sb-link active"><div class="sb-link-ic"><?=_sbIc('beranda','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>')?></div>Beranda</a>
<a href="games.php" class="sb-link"><div class="sb-link-ic"><?=_sbIc('games','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 12h4M8 10v4"/><circle cx="17" cy="11" r="1"/><circle cx="15" cy="13" r="1"/></svg>')?></div>Permainan</a>
<a href="deposit.php" class="sb-link"><div class="sb-link-ic"><?=_sbIc('deposit','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2v14m-5-5l5 5 5-5"/><path d="M5 20h14"/></svg>')?></div>Deposit</a>
<a href="withdraw.php" class="sb-link"><div class="sb-link-ic"><?=_sbIc('withdraw','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 16V2m5 5l-5-5-5 5"/><path d="M5 20h14"/></svg>')?></div>Penarikan</a>
<a href="promo.php" class="sb-link"><div class="sb-link-ic"><?=_sbIc('promosi','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l3 7h7l-5.5 4 2 7L12 16l-6.5 4 2-7L2 9h7z"/></svg>')?></div>Promosi</a>
<a href="promo.php?tab=vip" class="sb-link"><div class="sb-link-ic"><?=_sbIc('vip','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 16L3 5l5.5 5L12 4l3.5 6L21 5l-2 11H5z"/><path d="M5 19h14v2H5z"/></svg>')?></div><span class="sb-link-label">VIP</span><span class="sb-link-meta">LV <?= $isLoggedIn ? ($uvip ?? 0) : 0 ?></span></a>
</div>

<div class="sb-section-title">Bonus &amp; Hadiah</div>
<div class="sb-grid">
<a href="apresiasi.php" class="sb-link"><div class="sb-link-ic"><?=_sbIc('apresiasi','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 12v6a2 2 0 01-2 2H6a2 2 0 01-2-2v-6"/><path d="M2 8h20v4H2z"/><path d="M12 20V8"/></svg>')?></div>Apresiasi Anggota</a>
<a href="misteri.php" class="sb-link"><div class="sb-link-ic"><?=_sbIc('misteri','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 015.83 1c0 2-3 3-3 3"/><circle cx="12" cy="17" r=".5" fill="currentColor"/></svg>')?></div>Bonus Misteri</a>
<a href="bantuan.php" class="sb-link"><div class="sb-link-ic"><?=_sbIc('bantuan','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0016.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 002 8.5c0 2.3 1.5 4.05 3 5.5l7 7z"/></svg>')?></div>Bantuan Mingguan</a>
<a href="roulette.php" class="sb-link"><div class="sb-link-ic"><?=_sbIc('roulette','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="4"/></svg>')?></div>100K Gratis</a>
<a href="checkin.php" class="sb-link"><div class="sb-link-ic"><?=_sbIc('checkin','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M9 16l2 2 4-4"/></svg>')?></div>Hadiah Masuk</a>
<a href="bonusdepo.php" class="sb-link"><div class="sb-link-ic"><?=_sbIc('bonusdepo','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 12v6a2 2 0 01-2 2H6a2 2 0 01-2-2v-6"/><path d="M12 2v14m-5-5l5 5 5-5"/></svg>')?></div>Bonus Deposit</a>
<a href="undang.php" class="sb-link"><div class="sb-link-ic"><?=_sbIc('undang','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><path d="M8.59 13.51l6.83 3.98M15.41 6.51l-6.82 3.98"/></svg>')?></div>Undang Teman</a>
<a href="promo.php?tab=rebate" class="sb-link"><div class="sb-link-ic"><?=_sbIc('rebate','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="19" y1="5" x2="5" y2="19"/><circle cx="6.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="17.5" r="2.5"/></svg>')?></div>Rebate</a>
</div>

<div class="sb-section-title">Akun</div>
<div class="sb-grid">
<a href="cs.php" class="sb-link full"><div class="sb-link-ic"><?=_sbIc('cs','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>')?></div>Live Chat</a>
<a href="profil.php" class="sb-link full"><div class="sb-link-ic"><?=_sbIc('profil','<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="8" r="4"/><path d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>')?></div>Profil</a>
<?php if($isLoggedIn): ?>
<a href="#" onclick="doLogout();return false" class="sb-link full red"><div class="sb-link-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4M16 17l5-5-5-5M21 12H9"/></svg></div>Keluar</a>
<?php endif; ?>
</div>
<div style="height:14px"></div>
</div>

<div class="sb-socials">
<a href="<?=htmlspecialchars($SI['fb_url']??'#')?>" class="sb-soc" aria-label="Facebook"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z"/></svg></a>
<a href="<?=htmlspecialchars($SI['wa_url']??'#')?>" class="sb-soc" aria-label="WhatsApp"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347zM12 2C6.477 2 2 6.477 2 12c0 1.89.525 3.66 1.438 5.168L2 22l4.832-1.438A9.955 9.955 0 0012 22c5.523 0 10-4.477 10-10S17.523 2 12 2z"/></svg></a>
<a href="<?=htmlspecialchars($SI['tg_livechat']??$SI['tg_url']??'#')?>" class="sb-soc" aria-label="Telegram Live Chat"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0A12 12 0 000 12a12 12 0 0012 12 12 12 0 0012-12A12 12 0 0012.056 0zM8.016 10.18l8.544-3.293c.396-.144.742.097.613.7l-1.457 6.858c-.108.48-.39.597-.79.37l-2.18-1.607-1.051 1.013c-.116.116-.214.214-.439.214l.156-2.213 4.026-3.637c.176-.156-.038-.243-.272-.087l-4.974 3.132-2.143-.668c-.466-.146-.475-.466.097-.69z"/></svg></a>
<a href="<?=htmlspecialchars($SI['ig_url']??'#')?>" class="sb-soc" aria-label="Instagram"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849s-.012 3.584-.069 4.849c-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98C.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg></a>
<a href="<?=htmlspecialchars($SI['x_url']??'#')?>" class="sb-soc" aria-label="X"><svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>
</div>
</aside>

<script>
function toast(msg){var t=document.getElementById("appToast");if(!t){t=document.createElement("div");t.id="appToast";t.className="app-toast";document.body.appendChild(t)}t.textContent=msg;t.classList.add("show");clearTimeout(t._tm);t._tm=setTimeout(function(){t.classList.remove("show")},2500)}
var IS_LOGGED_IN = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
var user=JSON.parse(localStorage.getItem('app_user')||'null');
// Sync localStorage with server state — server has authoritative login status
if(IS_LOGGED_IN && !user){
    // Logged in server-side but localStorage cleared — refetch
    fetch('api/auth.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'me'})})
      .then(function(r){return r.json()}).then(function(d){if(d.ok&&d.user){localStorage.setItem('app_user',JSON.stringify(d.user));user=d.user;}});
}else if(!IS_LOGGED_IN && user){
    // Server says guest but localStorage has stale data — clear it
    localStorage.removeItem('app_user');
    user=null;
}

var SI={},PROVS=[],BANNERS=[],CARDS=[],SBGAMES=[],pageState={},favs=JSON.parse(localStorage.getItem('app_favs')||'{}');
var HEART='<svg viewBox="0 0 24 24" width="12" height="12"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg>';
var IC={facebook:'data:image/svg+xml;base64,PHN2ZyBmaWxsPSIjZmZmZmZmIiByb2xlPSJpbWciIHZpZXdCb3g9IjAgMCAyNCAyNCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48dGl0bGU+RmFjZWJvb2s8L3RpdGxlPjxwYXRoIGQ9Ik05LjEwMSAyMy42OTF2LTcuOThINi42Mjd2LTMuNjY3aDIuNDc0di0xLjU4YzAtNC4wODUgMS44NDgtNS45NzggNS44NTgtNS45NzguNDAxIDAgLjk1NS4wNDIgMS40NjguMTAzYTguNjggOC42OCAwIDAgMSAxLjE0MS4xOTV2My4zMjVhOC42MjMgOC42MjMgMCAwIDAtLjY1My0uMDM2IDI2LjgwNSAyNi44MDUgMCAwIDAtLjczMy0uMDA5Yy0uNzA3IDAtMS4yNTkuMDk2LTEuNjc1LjMwOWExLjY4NiAxLjY4NiAwIDAgMC0uNjc5LjYyMmMtLjI1OC40Mi0uMzc0Ljk5NS0uMzc0IDEuNzUydjEuMjk3aDMuOTE5bC0uMzg2IDIuMTAzLS4yODcgMS41NjRoLTMuMjQ2djguMjQ1QzE5LjM5NiAyMy4yMzggMjQgMTguMTc5IDI0IDEyLjA0NGMwLTYuNjI3LTUuMzczLTEyLTEyLTEycy0xMiA1LjM3My0xMiAxMmMwIDUuNjI4IDMuODc0IDEwLjM1IDkuMTAxIDExLjY0N1oiLz48L3N2Zz4=',whatsapp:'data:image/svg+xml;base64,PHN2ZyBmaWxsPSIjZmZmZmZmIiByb2xlPSJpbWciIHZpZXdCb3g9IjAgMCAyNCAyNCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48dGl0bGU+V2hhdHNBcHA8L3RpdGxlPjxwYXRoIGQ9Ik0xNy40NzIgMTQuMzgyYy0uMjk3LS4xNDktMS43NTgtLjg2Ny0yLjAzLS45NjctLjI3My0uMDk5LS40NzEtLjE0OC0uNjcuMTUtLjE5Ny4yOTctLjc2Ny45NjYtLjk0IDEuMTY0LS4xNzMuMTk5LS4zNDcuMjIzLS42NDQuMDc1LS4yOTctLjE1LTEuMjU1LS40NjMtMi4zOS0xLjQ3NS0uODgzLS43ODgtMS40OC0xLjc2MS0xLjY1My0yLjA1OS0uMTczLS4yOTctLjAxOC0uNDU4LjEzLS42MDYuMTM0LS4xMzMuMjk4LS4zNDcuNDQ2LS41Mi4xNDktLjE3NC4xOTgtLjI5OC4yOTgtLjQ5Ny4wOTktLjE5OC4wNS0uMzcxLS4wMjUtLjUyLS4wNzUtLjE0OS0uNjY5LTEuNjEyLS45MTYtMi4yMDctLjI0Mi0uNTc5LS40ODctLjUtLjY2OS0uNTEtLjE3My0uMDA4LS4zNzEtLjAxLS41Ny0uMDEtLjE5OCAwLS41Mi4wNzQtLjc5Mi4zNzItLjI3Mi4yOTctMS4wNCAxLjAxNi0xLjA0IDIuNDc5IDAgMS40NjIgMS4wNjUgMi44NzUgMS4yMTMgMy4wNzQuMTQ5LjE5OCAyLjA5NiAzLjIgNS4wNzcgNC40ODcuNzA5LjMwNiAxLjI2Mi40ODkgMS42OTQuNjI1LjcxMi4yMjcgMS4zNi4xOTUgMS44NzEuMTE4LjU3MS0uMDg1IDEuNzU4LS43MTkgMi4wMDYtMS40MTMuMjQ4LS42OTQuMjQ4LTEuMjg5LjE3My0xLjQxMy0uMDc0LS4xMjQtLjI3Mi0uMTk4LS41Ny0uMzQ3bS01LjQyMSA3LjQwM2gtLjAwNGE5Ljg3IDkuODcgMCAwMS01LjAzMS0xLjM3OGwtLjM2MS0uMjE0LTMuNzQxLjk4Mi45OTgtMy42NDgtLjIzNS0uMzc0YTkuODYgOS44NiAwIDAxLTEuNTEtNS4yNmMuMDAxLTUuNDUgNC40MzYtOS44ODQgOS44ODgtOS44ODQgMi42NCAwIDUuMTIyIDEuMDMgNi45ODggMi44OThhOS44MjUgOS44MjUgMCAwMTIuODkzIDYuOTk0Yy0uMDAzIDUuNDUtNC40MzcgOS44ODQtOS44ODUgOS44ODRtOC40MTMtMTguMjk3QTExLjgxNSAxMS44MTUgMCAwMDEyLjA1IDBDNS40OTUgMCAuMTYgNS4zMzUuMTU3IDExLjg5MmMwIDIuMDk2LjU0NyA0LjE0MiAxLjU4OCA1Ljk0NUwuMDU3IDI0bDYuMzA1LTEuNjU0YTExLjg4MiAxMS44ODIgMCAwMDUuNjgzIDEuNDQ4aC4wMDVjNi41NTQgMCAxMS44OS01LjMzNSAxMS44OTMtMTEuODkzYTExLjgyMSAxMS44MjEgMCAwMC0zLjQ4LTguNDEzWiIvPjwvc3ZnPg==',instagram:'data:image/svg+xml;base64,PHN2ZyBmaWxsPSIjZmZmZmZmIiByb2xlPSJpbWciIHZpZXdCb3g9IjAgMCAyNCAyNCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48dGl0bGU+SW5zdGFncmFtPC90aXRsZT48cGF0aCBkPSJNNy4wMzAxLjA4NGMtMS4yNzY4LjA2MDItMi4xNDg3LjI2NC0yLjkxMS41NjM0LS43ODg4LjMwNzUtMS40NTc1LjcyLTIuMTIyOCAxLjM4NzctLjY2NTIuNjY3Ny0xLjA3NSAxLjMzNjgtMS4zODAyIDIuMTI3LS4yOTU0Ljc2MzgtLjQ5NTYgMS42MzY1LS41NTIgMi45MTQtLjA1NjQgMS4yNzc1LS4wNjg5IDEuNjg4Mi0uMDYyNiA0Ljk0Ny4wMDYyIDMuMjU4Ni4wMjA2IDMuNjY3MS4wODI1IDQuOTQ3My4wNjEgMS4yNzY1LjI2NCAyLjE0ODIuNTYzNSAyLjkxMDcuMzA4Ljc4ODkuNzIgMS40NTczIDEuMzg4IDIuMTIyOC42Njc5LjY2NTUgMS4zMzY1IDEuMDc0MyAyLjEyODUgMS4zOC43NjMyLjI5NSAxLjYzNjEuNDk2MSAyLjkxMzQuNTUyIDEuMjc3My4wNTYgMS42ODg0LjA2OSA0Ljk0NjIuMDYyNyAzLjI1NzgtLjAwNjIgMy42NjgtLjAyMDcgNC45NDc4LS4wODE0IDEuMjgtLjA2MDcgMi4xNDctLjI2NTIgMi45MDk4LS41NjMzLjc4ODktLjMwODYgMS40NTc4LS43MiAyLjEyMjgtMS4zODgxLjY2NS0uNjY4MiAxLjA3NDUtMS4zMzc4IDEuMzc5NS0yLjEyODQuMjk1Ny0uNzYzMi40OTY2LTEuNjM2LjU1Mi0yLjkxMjQuMDU2LTEuMjgwOS4wNjkyLTEuNjg5OC4wNjMtNC45NDgtLjAwNjMtMy4yNTgzLS4wMjEtMy42NjY4LS4wODE3LTQuOTQ2NS0uMDYwNy0xLjI3OTctLjI2NC0yLjE0ODctLjU2MzMtMi45MTE3LS4zMDg0LS43ODg5LS43Mi0xLjQ1NjgtMS4zODc2LTIuMTIyOEMyMS4yOTgyIDEuMzMgMjAuNjI4LjkyMDggMTkuODM3OC42MTY1IDE5LjA3NC4zMjEgMTguMjAxNy4xMTk3IDE2LjkyNDQuMDY0NSAxNS42NDcxLjAwOTMgMTUuMjM2LS4wMDUgMTEuOTc3LjAwMTQgOC43MTguMDA3NiA4LjMxLjAyMTUgNy4wMzAxLjA4MzltLjE0MDIgMjEuNjkzMmMtMS4xNy0uMDUwOS0xLjgwNTMtLjI0NTMtMi4yMjg3LS40MDgtLjU2MDYtLjIxNi0uOTYtLjQ3NzEtMS4zODE5LS44OTUtLjQyMi0uNDE3OC0uNjgxMS0uODE4Ni0uOS0xLjM3OC0uMTY0NC0uNDIzNC0uMzYyNC0xLjA1OC0uNDE3MS0yLjIyOC0uMDU5NS0xLjI2NDUtLjA3Mi0xLjY0NDItLjA3OS00Ljg0OC0uMDA3LTMuMjAzNy4wMDUzLTMuNTgzLjA2MDctNC44NDguMDUtMS4xNjkuMjQ1Ni0xLjgwNS40MDgtMi4yMjgyLjIxNi0uNTYxMy40NzYyLS45Ni44OTUtMS4zODE2LjQxODgtLjQyMTcuODE4NC0uNjgxNCAxLjM3ODMtLjkwMDMuNDIzLS4xNjUxIDEuMDU3NS0uMzYxNCAyLjIyNy0uNDE3MSAxLjI2NTUtLjA2IDEuNjQ0Ny0uMDcyIDQuODQ4LS4wNzkgMy4yMDMzLS4wMDcgMy41ODM1LjAwNSA0Ljg0OTUuMDYwOCAxLjE2OS4wNTA4IDEuODA1My4yNDQ1IDIuMjI4LjQwOC41NjA4LjIxNi45Ni40NzU0IDEuMzgxNi44OTUuNDIxNy40MTk0LjY4MTYuODE3Ni45MDA1IDEuMzc4Ny4xNjUzLjQyMTcuMzYxNyAxLjA1Ni40MTY5IDIuMjI2My4wNjAyIDEuMjY1NS4wNzM5IDEuNjQ1LjA3OTYgNC44NDguMDA1OCAzLjIwMy0uMDA1NSAzLjU4MzQtLjA2MSA0Ljg0OC0uMDUxIDEuMTctLjI0NSAxLjgwNTUtLjQwOCAyLjIyOTQtLjIxNi41NjA0LS40NzYzLjk2LS44OTU0IDEuMzgxNC0uNDE5LjQyMTUtLjgxODEuNjgxMS0xLjM3ODMuOS0uNDIyNC4xNjQ5LTEuMDU3Ny4zNjE3LTIuMjI2Mi40MTc0LTEuMjY1Ni4wNTk1LTEuNjQ0OC4wNzItNC44NDkzLjA3OS0zLjIwNDUuMDA3LTMuNTgyNS0uMDA2LTQuODQ4LS4wNjA4TTE2Ljk1MyA1LjU4NjRBMS40NCAxLjQ0IDAgMSAwIDE4LjM5IDQuMTQ0YTEuNDQgMS40NCAwIDAgMC0xLjQzNyAxLjQ0MjRNNS44Mzg1IDEyLjAxMmMuMDA2NyAzLjQwMzIgMi43NzA2IDYuMTU1NyA2LjE3MyA2LjE0OTMgMy40MDI2LS4wMDY1IDYuMTU3LTIuNzcwMSA2LjE1MDYtNi4xNzMzLS4wMDY1LTMuNDAzMi0yLjc3MS02LjE1NjUtNi4xNzQtNi4xNDk4LTMuNDAzLjAwNjctNi4xNTYgMi43NzEtNi4xNDk2IDYuMTczOE04IDEyLjAwNzdhNCA0IDAgMSAxIDQuMDA4IDMuOTkyMUEzLjk5OTYgMy45OTk2IDAgMCAxIDggMTIuMDA3NyIvPjwvc3ZnPg==',x:'data:image/svg+xml;base64,PHN2ZyBmaWxsPSIjZmZmZmZmIiByb2xlPSJpbWciIHZpZXdCb3g9IjAgMCAyNCAyNCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48dGl0bGU+WDwvdGl0bGU+PHBhdGggZD0iTTE0LjIzNCAxMC4xNjIgMjIuOTc3IDBoLTIuMDcybC03LjU5MSA4LjgyNEw3LjI1MSAwSC4yNThsOS4xNjggMTMuMzQzTC4yNTggMjRIMi4zM2w4LjAxNi05LjMxOEwxNi43NDkgMjRoNi45OTN6bS0yLjgzNyAzLjI5OS0uOTI5LTEuMzI5TDMuMDc2IDEuNTZoMy4xODJsNS45NjUgOC41MzIuOTI5IDEuMzI5IDcuNzU0IDExLjA5aC0zLjE4MnoiLz48L3N2Zz4=',telegram:'data:image/svg+xml;base64,PHN2ZyBmaWxsPSIjZmZmZmZmIiByb2xlPSJpbWciIHZpZXdCb3g9IjAgMCAyNCAyNCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48dGl0bGU+VGVsZWdyYW08L3RpdGxlPjxwYXRoIGQ9Ik0xMS45NDQgMEExMiAxMiAwIDAgMCAwIDEyYTEyIDEyIDAgMCAwIDEyIDEyIDEyIDEyIDAgMCAwIDEyLTEyQTEyIDEyIDAgMCAwIDEyIDBhMTIgMTIgMCAwIDAtLjA1NiAwem00Ljk2MiA3LjIyNGMuMS0uMDAyLjMyMS4wMjMuNDY1LjE0YS41MDYuNTA2IDAgMCAxIC4xNzEuMzI1Yy4wMTYuMDkzLjAzNi4zMDYuMDIuNDcyLS4xOCAxLjg5OC0uOTYyIDYuNTAyLTEuMzYgOC42MjctLjE2OC45LS40OTkgMS4yMDEtLjgyIDEuMjMtLjY5Ni4wNjUtMS4yMjUtLjQ2LTEuOS0uOTAyLTEuMDU2LS42OTMtMS42NTMtMS4xMjQtMi42NzgtMS44LTEuMTg1LS43OC0uNDE3LTEuMjEuMjU4LTEuOTEuMTc3LS4xODQgMy4yNDctMi45NzcgMy4zMDctMy4yMy4wMDctLjAzMi4wMTQtLjE1LS4wNTYtLjIxMnMtLjE3NC0uMDQxLS4yNDktLjAyNGMtLjEwNi4wMjQtMS43OTMgMS4xNC01LjA2MSAzLjM0NS0uNDguMzMtLjkxMy40OS0xLjMwMi40OC0uNDI4LS4wMDgtMS4yNTItLjI0MS0xLjg2NS0uNDQtLjc1Mi0uMjQ1LTEuMzQ5LS4zNzQtMS4yOTctLjc4OS4wMjctLjIxNi4zMjUtLjQzNy44OTMtLjY2MyAzLjQ5OC0xLjUyNCA1LjgzLTIuNTI5IDYuOTk4LTMuMDE0IDMuMzMyLTEuMzg2IDQuMDI1LTEuNjI3IDQuNDc2LTEuNjM1eiIvPjwvc3ZnPg==',visa:'data:image/svg+xml;base64,PHN2ZyBmaWxsPSIjZmZmZmZmIiByb2xlPSJpbWciIHZpZXdCb3g9IjAgMCAyNCAyNCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48dGl0bGU+VmlzYTwvdGl0bGU+PHBhdGggZD0iTTkuMTEyIDguMjYyTDUuOTcgMTUuNzU4SDMuOTJMMi4zNzQgOS43NzVjLS4wOTQtLjM2OC0uMTc1LS41MDMtLjQ2MS0uNjU4QzEuNDQ3IDguODY0LjY3NyA4LjYyNyAwIDguNDc5bC4wNDYtLjIxN2gzLjNhLjkwNC45MDQgMCAwMS44OTQuNzY0bC44MTcgNC4zMzggMi4wMTgtNS4xMDJ6bTguMDMzIDUuMDQ5Yy4wMDgtMS45NzktMi43MzYtMi4wODgtMi43MTctMi45NzIuMDA2LS4yNjkuMjYyLS41NTUuODIyLS42MjhhMy42NiAzLjY2IDAgMDExLjkxMy4zMzZsLjM0LTEuNTlhNS4yMDcgNS4yMDcgMCAwMC0xLjgxNC0uMzMzYy0xLjkxNyAwLTMuMjY2IDEuMDItMy4yNzggMi40NzktLjAxMiAxLjA3OS45NjMgMS42OCAxLjY5OCAyLjA0Ljc1Ni4zNjcgMS4wMS42MDMgMS4wMDYuOTMxLS4wMDUuNTA0LS42MDIuNzI1LTEuMTYuNzM0LS45NzUuMDE1LTEuNTQtLjI2My0xLjk5Mi0uNDczbC0uMzUxIDEuNjQyYy40NTMuMjA4IDEuMjg5LjM5IDIuMTU2LjM5OCAyLjAzNyAwIDMuMzctMS4wMDYgMy4zNzctMi41NjRtNS4wNjEgMi40NDdIMjRsLTEuNTY1LTcuNDk2aC0xLjY1NmEuODgzLjg4MyAwIDAwLS44MjYuNTVsLTIuOTA5IDYuOTQ2aDIuMDM2bC40MDUtMS4xMmgyLjQ4OHptLTIuMTYzLTIuNjU2bDEuMDItMi44MTUuNTg4IDIuODE1em0tOC4xNi00Ljg0bC0xLjYwMyA3LjQ5Nkg4LjM0bDEuNjA1LTcuNDk2eiIvPjwvc3ZnPg==',mastercard:'data:image/svg+xml;base64,PHN2ZyBmaWxsPSIjZmZmZmZmIiByb2xlPSJpbWciIHZpZXdCb3g9IjAgMCAyNCAyNCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48dGl0bGU+TWFzdGVyQ2FyZDwvdGl0bGU+PHBhdGggZD0iTTExLjM0MyAxOC4wMzFjLjA1OC4wNDkuMTIuMDk4LjE4MS4xNDYtMS4xNzcuNzgzLTIuNTkgMS4yMzgtNC4xMDcgMS4yMzhDMy4zMiAxOS40MTYgMCAxNi4wOTYgMCAxMmMwLTQuMDk1IDMuMzItNy40MTYgNy40MTYtNy40MTYgMS41MTggMCAyLjkzMS40NTYgNC4xMDUgMS4yMzgtLjA2LjA1MS0uMTIuMDk4LS4xNjUuMTVDOS42IDcuNDg5IDguNTk1IDkuNjg4IDguNTk1IDEyYzAgMi4zMTEgMS4wMDEgNC41MSAyLjc0OCA2LjAzMXptNS4yNDEtMTMuNDQ3Yy0xLjUyIDAtMi45MzEuNDU2LTQuMTA1IDEuMjM4LjA2LjA1MS4xMi4wOTguMTY1LjE1QzE0LjQgNy40ODkgMTUuNDA1IDkuNjg4IDE1LjQwNSAxMmMwIDIuMzEtMS4wMDEgNC41MDctMi43NDggNi4wMzEtLjA1OC4wNDktLjEyLjA5OC0uMTgxLjE0NiAxLjE3Ny43ODMgMi41ODggMS4yMzggNC4xMDcgMS4yMzhDMjAuNjggMTkuNDE2IDI0IDE2LjA5NiAyNCAxMmMwLTQuMDk0LTMuMzItNy40MTYtNy40MTYtNy40MTZ6TTEyIDYuMTc0Yy0uMDk2LjA3NS0uMTg5LjE1LS4yOC4yMzFDMTAuMTU2IDcuNzY0IDkuMTY5IDkuNzY1IDkuMTY5IDEyYzAgMi4yMzYuOTg3IDQuMjM2IDIuNTUxIDUuNTk1LjA5LjA4LjE4NS4xNTguMjguMjMyLjA5Ni0uMDc0LjE4OS0uMTUyLjI4LS4yMzIgMS41NjMtMS4zNTkgMi41NTEtMy4zNTkgMi41NTEtNS41OTUgMC0yLjIzNS0uOTg3LTQuMjM2LTIuNTUxLTUuNTk1LS4wOS0uMDgtLjE4NC0uMTU2LS4yOC0uMjMxeiIvPjwvc3ZnPg==',paypal:'data:image/svg+xml;base64,PHN2ZyBmaWxsPSIjZmZmZmZmIiByb2xlPSJpbWciIHZpZXdCb3g9IjAgMCAyNCAyNCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48dGl0bGU+UGF5UGFsPC90aXRsZT48cGF0aCBkPSJNMTUuNjA3IDQuNjUzSDguOTQxTDYuNjQ1IDE5LjI1MUgxLjgyTDQuODYyIDBoNy45OTVjMy43NTQgMCA2LjM3NSAyLjI5NCA2LjQ3MyA1LjUxMy0uNjQ4LS40NzgtMi4xMDUtLjg2LTMuNzIyLS44Nm02LjU3IDUuNTQ2YzAgMy40MS0zLjAxIDYuODUzLTYuOTU4IDYuODUzaC0yLjQ5M0wxMS41OTUgMjRINi43NGwxLjg0NS0xMS41MzhoMy41OTJjNC4yMDggMCA3LjM0Ni0zLjYzNCA3LjE1My02Ljk0OWE1LjI0IDUuMjQgMCAwIDEgMi44NDggNC42ODZNOS42NTMgNS41NDZoNi40MDhjLjkwNyAwIDEuOTQyLjIyMiAyLjM2My41NDEtLjE5NSAyLjc0MS0yLjY1NSA1LjQ4My02LjQ0MSA1LjQ4M0g4LjcxNFoiLz48L3N2Zz4=',bitcoin:'data:image/svg+xml;base64,PHN2ZyBmaWxsPSIjZmZmZmZmIiByb2xlPSJpbWciIHZpZXdCb3g9IjAgMCAyNCAyNCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48dGl0bGU+Qml0Y29pbjwvdGl0bGU+PHBhdGggZD0iTTIzLjYzOCAxNC45MDRjLTEuNjAyIDYuNDMtOC4xMTMgMTAuMzQtMTQuNTQyIDguNzM2QzIuNjcgMjIuMDUtMS4yNDQgMTUuNTI1LjM2MiA5LjEwNSAxLjk2MiAyLjY3IDguNDc1LTEuMjQzIDE0LjkuMzU4YzYuNDMgMS42MDUgMTAuMzQyIDguMTE1IDguNzM4IDE0LjU0OHYtLjAwMnptLTYuMzUtNC42MTNjLjI0LTEuNTktLjk3NC0yLjQ1LTIuNjQtMy4wM2wuNTQtMi4xNTMtMS4zMTUtLjMzLS41MjUgMi4xMDdjLS4zNDUtLjA4Ny0uNzA1LS4xNjctMS4wNjQtLjI1bC41MjYtMi4xMjctMS4zMi0uMzMtLjU0IDIuMTY1Yy0uMjg1LS4wNjctLjU2NS0uMTMyLS44NC0uMmwtMS44MTUtLjQ1LS4zNSAxLjQwN3MuOTc1LjIyNS45NTUuMjM2Yy41MzUuMTM2LjYzLjQ4Ni42MTUuNzY2bC0xLjQ3NyA1LjkyYy0uMDc1LjE2Ni0uMjQuNDA2LS42MTQuMzE0LjAxNS4wMi0uOTYtLjI0LS45Ni0uMjRsLS42NiAxLjUxIDEuNzEuNDI2LjkzLjI0Mi0uNTQgMi4xOSAxLjMyLjMyNy41NC0yLjE3Yy4zNi4xLjcwNS4xOSAxLjA1LjI3M2wtLjUxIDIuMTU0IDEuMzIuMzMuNTQ1LTIuMTljMi4yNC40MjcgMy45My4yNTcgNC42NC0xLjc3NC41Ny0xLjYzNy0uMDMtMi41OC0xLjIxNy0zLjE5Ni44NTQtLjE5MyAxLjUtLjc2IDEuNjgtMS45M2guMDF6bS0zLjAxIDQuMjJjLS40MDQgMS42NC0zLjE1Ny43NS00LjA1LjUzbC43Mi0yLjljLjg5Ni4yMyAzLjc1Ny42NyAzLjMzIDIuMzd6bS40MS00LjI0Yy0uMzcgMS40OS0yLjY2Mi43MzUtMy40MDUuNTVsLjY1NC0yLjY0Yy43NDQuMTggMy4xMzcuNTI0IDIuNzUgMi4wODR2LjAwNnoiLz48L3N2Zz4=',tether:'data:image/svg+xml;base64,PHN2ZyBmaWxsPSIjZmZmZmZmIiByb2xlPSJpbWciIHZpZXdCb3g9IjAgMCAyNCAyNCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48dGl0bGU+VGV0aGVyPC90aXRsZT48cGF0aCBkPSJNMTguNzUzOCAxMC41MTc2YzAgLjYyNTEtMi4yMzc5IDEuMTQ4My01LjIzODEgMS4yODEybC4wMDI4LjAwMDdjLS4wODQ4LjAwNjQtLjUyMzMuMDMyNS0xLjUwMTIuMDMyNS0uNzc3OCAwLTEuMzMtLjAyMzMtMS41MjM3LS4wMzI1LTMuMDA1OS0uMTMyMi01LjI0OTUtLjY1NTUtNS4yNDk1LTEuMjgxOXMyLjI0MzYtMS4xNDkgNS4yNDk1LTEuMjgzNHYyLjA0NDJjLjE5NjUuMDE0Mi43NTk0LjA0NzQgMS41MzcyLjA0NzQuOTMzNCAwIDEuNDAwOC0uMDM4OSAxLjQ4NDktLjA0NjZWOS4yMzU2YzIuOTk5NC4xMzM3IDUuMjM4MS42NTcgNS4yMzgxIDEuMjgyem01LjE5LjU0NjZMMTIuMTI0OCAyMi4zODlhLjE4MDMuMTgwMyAwIDAgMS0uMjQ5NiAwTC4wNTYyIDExLjA2MzVhLjE3ODEuMTc4MSAwIDAgMS0uMDM4Mi0uMjA3OWw0LjM3NjItOS4xOTIxYS4xNzY3LjE3NjcgMCAwIDEgLjE2MjYtLjEwMjZoMTQuODg3OGEuMTc2OC4xNzY4IDAgMCAxIC4xNjEyLjEwMzJsNC4zNzYyIDkuMTkyMmEuMTc4Mi4xNzgyIDAgMCAxLS4wMzgyLjIwNzl6bS00LjQ3OC0uNDAzOGMwLS44MDY4LTIuNTUxNS0xLjQ3OTktNS45NDczLTEuNjM2OVY3LjE5NWg0LjE4NlY0LjQwNTVINi4zMDc2VjcuMTk1aDQuMTg1MnYxLjgyODZjLTMuNDAxOC4xNTYyLTUuOTYwMS44My01Ljk2MDEgMS42Mzc2IDAgLjgwNzUgMi41NTgzIDEuNDgwNiA1Ljk2MDEgMS42Mzc2djUuODYxOGgzLjAyNXYtNS44NjM5YzMuMzk0LS4xNTYzIDUuOTQ4LS44Mjk1IDUuOTQ4LTEuNjM2M3oiLz48L3N2Zz4=',stripe:'data:image/svg+xml;base64,PHN2ZyBmaWxsPSIjZmZmZmZmIiByb2xlPSJpbWciIHZpZXdCb3g9IjAgMCAyNCAyNCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48dGl0bGU+U3RyaXBlPC90aXRsZT48cGF0aCBkPSJNMTMuOTc2IDkuMTVjLTIuMTcyLS44MDYtMy4zNTYtMS40MjYtMy4zNTYtMi40MDkgMC0uODMxLjY4My0xLjMwNSAxLjkwMS0xLjMwNSAyLjIyNyAwIDQuNTE1Ljg1OCA2LjA5IDEuNjMxbC44OS01LjQ5NEMxOC4yNTIuOTc1IDE1LjY5NyAwIDEyLjE2NSAwIDkuNjY3IDAgNy41ODkuNjU0IDYuMTA0IDEuODcyIDQuNTYgMy4xNDcgMy43NTcgNC45OTIgMy43NTcgNy4yMThjMCA0LjAzOSAyLjQ2NyA1Ljc2IDYuNDc2IDcuMjE5IDIuNTg1LjkyIDMuNDQ1IDEuNTc0IDMuNDQ1IDIuNTgzIDAgLjk4LS44NCAxLjU0NS0yLjM1NCAxLjU0NS0xLjg3NSAwLTQuOTY1LS45MjEtNi45OS0yLjEwOWwtLjkgNS41NTVDNS4xNzUgMjIuOTkgOC4zODUgMjQgMTEuNzE0IDI0YzIuNjQxIDAgNC44NDMtLjYyNCA2LjMyOC0xLjgxMyAxLjY2NC0xLjMwNSAyLjUyNS0zLjIzNiAyLjUyNS01LjczMiAwLTQuMTI4LTIuNTI0LTUuODUxLTYuNTk0LTcuMzA1aC4wMDN6Ii8+PC9zdmc+',googlepay:'data:image/svg+xml;base64,PHN2ZyBmaWxsPSIjZmZmZmZmIiByb2xlPSJpbWciIHZpZXdCb3g9IjAgMCAyNCAyNCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48dGl0bGU+R29vZ2xlIFBheTwvdGl0bGU+PHBhdGggZD0iTTMuOTYzIDcuMjM1QTMuOTYzIDMuOTYzIDAgMDAuNDIyIDkuNDE5YTMuOTYzIDMuOTYzIDAgMDAwIDMuNTU5IDMuOTYzIDMuOTYzIDAgMDAzLjU0MSAyLjE4NGMxLjA3IDAgMS45Ny0uMzUyIDIuNjI3LS45NTcuNzQ4LS42OSAxLjE4LTEuNzEgMS4xOC0yLjkxNmE0LjcyMiA0LjcyMiAwIDAwLS4wNy0uODA2SDMuOTY0djEuNTI2aDIuMTRhMS44MzUgMS44MzUgMCAwMS0uNzkgMS4yMDVjLS4zNTYuMjQxLS44MTQuMzc5LTEuMzUuMzc5LTEuMDM0IDAtMS45MTEtLjY5Ny0yLjIyNS0xLjYzNmEyLjM3NSAyLjM3NSAwIDAxMC0xLjUxN2MuMzE0LS45NCAxLjE5MS0xLjYzNiAyLjIyNS0xLjYzNmEyLjE1MiAyLjE1MiAwIDAxMS41Mi41OTRsMS4xMzItMS4xM2EzLjgwOCAzLjgwOCAwIDAwLTIuNjUyLTEuMDMzem02LjUwMS41NXY2LjloLjg4NlYxMS44OWgxLjQ2NWMuNjAzIDAgMS4xMS0uMTk2IDEuNTIyLS41ODhhMS45MTEgMS45MTEgMCAwMC42MzUtMS40NjQgMS45MiAxLjkyIDAgMDAtLjYzNS0xLjQ1NiAyLjEyNSAyLjEyNSAwIDAwLTEuNTIyLS41OTh6bTIuNDI3Ljg1YTEuMTU2IDEuMTU2IDAgMDEuODIzLjM2NSAxLjE3NiAxLjE3NiAwIDAxMCAxLjY4NiAxLjE3MSAxLjE3MSAwIDAxLS44NzcuMzU3SDExLjM1VjguNjM1aDEuNDg3YTEuMTU2IDEuMTU2IDAgMDEuMDU0IDB6bTQuMTI0IDEuMTc1Yy0uODQyIDAtMS40NzcuMzA4LTEuOTA3LjkyNWwuNzgxLjQ5MWMuMjg4LS40MTcuNjgtLjYyNiAxLjE3NS0uNjI2YTEuMjU1IDEuMjU1IDAgMDEuODU2LjMyMyAxLjAwOSAxLjAwOSAwIDAxLjM2Ni43ODV2LjIwMmMtLjM0LS4xOTMtLjc3NC0uMjg5LTEuMy0uMjg5LS42MTcgMC0xLjExLjE0NS0xLjQ3OS40MzQtLjM3LjI4OC0uNTU0LjY3Ny0uNTU0IDEuMTY1YTEuNDc2IDEuNDc2IDAgMDAuNTI1IDEuMTU2Yy4zNS4zMDguNzg1LjQ2MyAxLjMwNS40NjMuNjEgMCAxLjA5OC0uMjcgMS40NjUtLjgxaC4wMzh2LjY1NWguODQ4di0yLjkwOWMwLS42MS0uMTktMS4wOS0uNTY4LTEuNDQtLjM4LS4zNS0uODk2LS41MjUtMS41NTEtLjUyNXptMi4yNjMuMTU0bDEuOTQ2IDQuNDIyLTEuMDk4IDIuMzhoLjkxNUwyNCA5Ljk2M2gtLjk2NWwtMS4zNjggMy4zOTFoLS4wMmwtMS40MDYtMy4zOXptLTIuMTQ2IDIuMzY4Yy40OTQgMCAuODguMTEgMS4xNTYuMzMgMCAuMzcyLS4xNDcuNjk2LS40NC45NzNhMS40MTMgMS40MTMgMCAwMS0uOTk3LjQxNCAxLjA4MSAxLjA4MSAwIDAxLS42OS0uMjMyLjcwOC43MDggMCAwMS0uMjkzLS41NzhjMC0uMjU3LjEyLS40Ny4zNjMtLjY0Ny4yNC0uMTczLjU0LS4yNi45LS4yNloiLz48L3N2Zz4=',applepay:'data:image/svg+xml;base64,PHN2ZyBmaWxsPSIjZmZmZmZmIiByb2xlPSJpbWciIHZpZXdCb3g9IjAgMCAyNCAyNCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48dGl0bGU+QXBwbGUgUGF5PC90aXRsZT48cGF0aCBkPSJNMi4xNSA0LjMxOGE0Mi4xNiA0Mi4xNiAwIDAgMC0uNDU0LjAwM2MtLjE1LjAwNS0uMzAzLjAxMy0uNDUyLjA0YTEuNDQgMS40NCAwIDAgMC0xLjA2Ljc3MmMtLjA3LjEzOC0uMTE0LjI3OC0uMTQuNDMtLjAyOC4xNDgtLjAzNy4zLS4wNC40NUExMC4yIDEwLjIgMCAwIDAgMCA2LjIyMnYxMS41NTdjMCAuMDcuMDAyLjEzOC4wMDMuMjA3LjAwNC4xNS4wMTMuMzAzLjA0LjQ1Mi4wMjcuMTUuMDcyLjI5MS4xNDIuNDI5YTEuNDM2IDEuNDM2IDAgMCAwIC42My42M2MuMTM4LjA3LjI3OC4xMTUuNDMuMTQyLjE0OC4wMjcuMy4wMzYuNDUuMDRsLjIwOC4wMDNoMjAuMTk0bC4yMDctLjAwM2MuMTUtLjAwNC4zMDMtLjAxMy40NTItLjA0LjE1LS4wMjcuMjkxLS4wNzEuNDI4LS4xNDFhMS40MzIgMS40MzIgMCAwIDAgLjYzMS0uNjMxYy4wNy0uMTM4LjExNS0uMjc4LjE0MS0uNDMuMDI3LS4xNDguMDM2LS4zLjA0LS40NS4wMDItLjA3LjAwMy0uMTM4LjAwMy0uMjA4bC4wMDEtLjI0NlY2LjIyMWMwLS4wNy0uMDAyLS4xMzgtLjAwNC0uMjA3YTIuOTk1IDIuOTk1IDAgMCAwLS4wNC0uNDUyIDEuNDQ2IDEuNDQ2IDAgMCAwLTEuMi0xLjIwMSAzLjAyMiAzLjAyMiAwIDAgMC0uNDUyLS4wNCAxMC40NDggMTAuNDQ4IDAgMCAwLS40NTMtLjAwM3ptMCAuNTEyaDE5Ljk0MmMuMDY2IDAgLjEzMS4wMDIuMTk3LjAwMy4xMTUuMDA0LjI1LjAxLjM3NS4wMzIuMTA5LjAyLjIuMDUuMjg3LjA5NGEuOTI3LjkyNyAwIDAgMSAuNDA3LjQwNy45OTcuOTk3IDAgMCAxIC4wOTQuMjg4Yy4wMjIuMTIzLjAyOC4yNTguMDMxLjM3NC4wMDIuMDY1LjAwMy4xMy4wMDMuMTk3djExLjU1MmMwIC4wNjUgMCAuMTMtLjAwMy4xOTYtLjAwMy4xMTUtLjAwOS4yNS0uMDMyLjM3NWEuOTI3LjkyNyAwIDAgMS0uNS42OTMgMS4wMDIgMS4wMDIgMCAwIDEtLjI4Ni4wOTQgMi41OTggMi41OTggMCAwIDEtLjM3My4wMzJsLS4yLjAwM0gxLjkwNmMtLjA2NiAwLS4xMzMtLjAwMi0uMTk2LS4wMDNhMi42MSAyLjYxIDAgMCAxLS4zNzUtLjAzMmMtLjEwOS0uMDItLjItLjA1LS4yODgtLjA5NGEuOTE4LjkxOCAwIDAgMS0uNDA2LS40MDcgMS4wMDYgMS4wMDYgMCAwIDEtLjA5NC0uMjg4IDIuNTMxIDIuNTMxIDAgMCAxLS4wMzItLjM3MyA5LjU4OCA5LjU4OCAwIDAgMS0uMDAyLS4xOTdWNi4yMjRjMC0uMDY1IDAtLjEzMS4wMDItLjE5Ny4wMDQtLjExNC4wMS0uMjQ4LjAzMi0uMzc1LjAyLS4xMDguMDUtLjE5OS4wOTQtLjI4N2EuOTI1LjkyNSAwIDAgMSAuNDA3LS40MDYgMS4wMyAxLjAzIDAgMCAxIC4yODctLjA5NGMuMTI1LS4wMjIuMjYtLjAyOS4zNzUtLjAzMi4wNjUtLjAwMi4xMzEtLjAwMi4xOTYtLjAwM3ptNC43MSAzLjdjLS4zLjAxNi0uNjY4LjE5OS0uODguNDU2LS4xOTEuMjItLjM2LjU4LS4zMTYuOTE4LjMzOC4wMy42NzUtLjE2OS44ODgtLjQxOC4yMDUtLjI1OC4zNDUtLjYwMy4zMDgtLjk1NXptMi4yMDcuNDJ2NS40OTNoLjg1MnYtMS44NzdoMS4xOGMxLjA3OCAwIDEuODM1LS43MzkgMS44MzUtMS44MTIgMC0xLjA3LS43NDItMS44MDUtMS44MDgtMS44MDV6bS44NTIuNzE5aC45ODJjLjczOSAwIDEuMTYxLjM5NiAxLjE2MSAxLjA4OSAwIC42OTItLjQyMiAxLjA5Mi0xLjE2NCAxLjA5MmgtLjk3OXptLTMuMTU0LjNjLS40NS4wMS0uODMuMjgtMS4wNS4yOC0uMjM1IDAtLjU5My0uMjY0LS45ODEtLjI1N2ExLjQ0NiAxLjQ0NiAwIDAgMC0xLjIzLjc0N2MtLjUyNy45MDgtLjEzOSAyLjI1NS4zNzQgMi45OTUuMjQ5LjM2Ni41NDkuNzY5Ljk0NC43NTQuMzczLS4wMTQuNTItLjI0Mi45NzMtLjI0Mi40NTQgMCAuNTg2LjI0Mi45OC4yMzUuNDEtLjAwNy42NjctLjM2Ni45MTUtLjczMy4yODYtLjQxNy40MDMtLjgyLjQxLS44NDEtLjAwNy0uMDA4LS43OS0uMzA4LS43OTctMS4yMDktLjAwOC0uNzU0LjYxNS0xLjExMy42NDQtMS4xMzUtLjM1Mi0uNTItLjktLjU3OC0xLjA5LS41OTNhMS4xMjMgMS4xMjMgMCAwIDAtLjA5Mi0uMDAyem04LjIwNC4zOTdjLS45OSAwLTEuNjA2LjUzMy0xLjY1MiAxLjI1NmguNzc3Yy4wNzItLjM1OC4zNjktLjU4Ni44NDUtLjU4Ni41MDIgMCAuODAzLjI2Ni44MDMuNzExdi4zMDlsLTEuMDk3LjA2NGMtLjk1MS4wNTQtMS40ODguNDg0LTEuNDg4IDEuMTg0IDAgLjcyLjU0OCAxLjIwNyAxLjMzMiAxLjIwNy41MjYgMCAxLjAzMi0uMjgxIDEuMjY0LS43MjdoLjAxOXYuNjU5aC43ODh2LTIuNzZjMC0uODAzLS42Mi0xLjMxNy0xLjU5MS0xLjMxN3ptMS45NC4wNzJsMS40NDYgNC4wMDljMCAuMDAzLS4wNzMuMjQtLjA3My4yNDctLjEyNS40MS0uMzMuNTcxLS43MTEuNTcxLS4wNjkgMC0uMjA2IDAtLjI2Ny0uMDE1di42NjZjLjA2LjAxMS4yNjcuMDE5LjMzNS4wMTkuODMgMCAxLjIyNi0uMzEyIDEuNTY4LTEuMjgzbDEuNS00LjIxNGgtLjg2OGwtMS4wMTIgMy4yNTloLS4wMTVsLTEuMDEzLTMuMjZ6bS0xLjE2NyAyLjE4OXYuMzE2YzAgLjUyMS0uNDUuOTE3LTEuMDI0LjkxNy0uNDQyIDAtLjczMS0uMjI4LS43MzEtLjU3OSAwLS4zNDIuMjc4LS41Ni43NjktLjU5M3oiLz48L3N2Zz4=',pix:'data:image/svg+xml;base64,PHN2ZyBmaWxsPSIjZmZmZmZmIiByb2xlPSJpbWciIHZpZXdCb3g9IjAgMCAyNCAyNCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48dGl0bGU+UGl4PC90aXRsZT48cGF0aCBkPSJNNS4yODMgMTguMzZhMy41MDUgMy41MDUgMCAwIDAgMi40OTMtMS4wMzJsMy42LTMuNmEuNjg0LjY4NCAwIDAgMSAuOTQ2IDBsMy42MTMgMy42MTNhMy41MDQgMy41MDQgMCAwIDAgMi40OTMgMS4wMzJoLjcxbC00LjU2IDQuNTZhMy42NDcgMy42NDcgMCAwIDEtNS4xNTYgMEw0Ljg1IDE4LjM2Wk0xOC40MjggNS42MjdhMy41MDUgMy41MDUgMCAwIDAtMi40OTMgMS4wMzJsLTMuNjEzIDMuNjE0YS42Ny42NyAwIDAgMS0uOTQ2IDBsLTMuNi0zLjZBMy41MDUgMy41MDUgMCAwIDAgNS4yODMgNS42NGgtLjQzNGw0LjU3My00LjU3MmEzLjY0NiAzLjY0NiAwIDAgMSA1LjE1NiAwbDQuNTU5IDQuNTU5Wk0xLjA2OCA5LjQyMiAzLjc5IDYuNjk5aDEuNDkyYTIuNDgzIDIuNDgzIDAgMCAxIDEuNzQ0LjcyMmwzLjYgMy42YTEuNzMgMS43MyAwIDAgMCAyLjQ0MyAwbDMuNjE0LTMuNjEzYTIuNDgyIDIuNDgyIDAgMCAxIDEuNzQ0LS43MjNoMS43NjdsMi43MzcgMi43MzdhMy42NDYgMy42NDYgMCAwIDEgMCA1LjE1NmwtMi43MzYgMi43MzZoLTEuNzY4YTIuNDgyIDIuNDgyIDAgMCAxLTEuNzQ0LS43MjJsLTMuNjEzLTMuNjEzYTEuNzcgMS43NyAwIDAgMC0yLjQ0NCAwbC0zLjYgMy42YTIuNDgzIDIuNDgzIDAgMCAxLTEuNzQ0LjcyMkgzLjc5MWwtMi43MjMtMi43MjNhMy42NDYgMy42NDYgMCAwIDEgMC01LjE1NiIvPjwvc3ZnPg==',};
function heartHtml(k){return '<button class="fav-btn" onclick="tFav(event,\''+k+'\')" style="position:absolute;top:3px;right:3px;width:20px;height:20px;background:rgba(0,0,0,.4);border-radius:4px;display:flex;align-items:center;justify-content:center;z-index:3;cursor:pointer;border:none;padding:0"><span style="display:flex">'+(favs[k]?HEART.replace('<svg','<svg fill="#ef4444" stroke="#ef4444" stroke-width="2"'):HEART.replace('<svg','<svg fill="none" stroke="var(--t3)" stroke-width="2"'))+'</span></button>'}
function tFav(e,k){e.preventDefault();e.stopPropagation();if(favs[k])delete favs[k];else favs[k]=1;localStorage.setItem('app_favs',JSON.stringify(favs));var b=e.currentTarget;b.querySelector('span').innerHTML=favs[k]?HEART.replace('<svg','<svg fill="#ef4444" stroke="#ef4444" stroke-width="2"'):HEART.replace('<svg','<svg fill="none" stroke="var(--t3)" stroke-width="2"')}
var GC=['linear-gradient(135deg,#0c4a6e,#0a1628)','linear-gradient(135deg,#1a365d,#0c4a6e)','linear-gradient(135deg,var(--sec-d,var(--sec-d)),var(--sec,var(--sec)))','linear-gradient(135deg,#7f1d1d,#991b1b)','linear-gradient(135deg,#713f12,#a16207)','linear-gradient(135deg,#0c4a6e,#047857)','linear-gradient(135deg,var(--bg2),var(--bg))','linear-gradient(135deg,var(--sec,var(--sec)),var(--sec-d,var(--sec-d)))'];
function logoHtml(p){  var l=p.logo||'';  var n=(p.name||p.code||'').substring(0,5).toUpperCase();  if(l)return '<img src="'+l+'" style="width:78%;height:78%;object-fit:contain" onerror="this.outerHTML=\'<span style=\'font-weight:900;font-size:.72rem;letter-spacing:1px\'>\'+this.dataset.fb+\'</span>\'" data-fb="'+n+'">';  return '<span style="font-weight:900;font-size:.72rem;letter-spacing:1px">'+n+'</span>';}
function toggleSB(){var s=document.getElementById('sB'),o=document.getElementById('sO');var open=s.classList.toggle('open');o.classList.toggle('open',open);document.body.style.overflow=open?'hidden':''}
function renderFooter(){
  var pg='';PROVS.forEach(function(p){
    var logo=p.logo?'<img src="'+p.logo+'">':'<span style="font-size:.65rem;font-weight:800;color:var(--t2)">'+(p.name||p.code)+'</span>';
    pg+='<div class="ft-p"><div class="ft-p-logo">'+logo+'</div></div>';
  });
  var svgFb='<svg viewBox="0 0 24 24" fill="currentColor"><path d="M18 2h-3a5 5 0 00-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 011-1h3z"/></svg>';
  var svgWa='<svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>';
  var svgIg='<svg viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>';
  var svgTg='<svg viewBox="0 0 24 24" fill="currentColor"><path d="M11.944 0A12 12 0 000 12a12 12 0 0012 12 12 12 0 0012-12A12 12 0 0012.056 0zM8.016 10.18l8.544-3.293c.396-.144.742.097.613.7l-1.457 6.858c-.108.48-.39.597-.79.37l-2.18-1.607-1.051 1.013c-.116.116-.214.214-.439.214l.156-2.213 4.026-3.637c.176-.156-.038-.243-.272-.087l-4.974 3.132-2.143-.668c-.466-.146-.475-.466.097-.69z"/></svg>';
  var svgX='<svg viewBox="0 0 24 24" fill="currentColor"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg>';
  // Logo: pakai SI.logo_url kalau ada, fallback ke /img/ggr_logo.png
  var ftLogoSrc = SI.logo_url || 'img/ggr_logo.png';
  var siteName = SI.site_name || 'GGR GAMING';
  var ccs = (SI.cs_phone || SI.cs_whatsapp || '').toString();
  var ccsLink = ccs ? ('https://wa.me/'+ccs.replace(/[^0-9]/g,'')) : 'cs.php';
  document.getElementById('ftArea').innerHTML=
  // ─── 1. SITE LOGO ───
  '<div class="ft-logo-wrap"><img src="'+ftLogoSrc+'" class="ft-logo" alt="'+siteName+'" onerror="this.style.display=\'none\';this.parentElement.querySelector(\'.ft-logo-text\').style.display=\'block\'"><div class="ft-logo-text" style="display:none">'+siteName+'</div></div>'+
  // ─── 2. PROVIDERS ───
  '<div class="ft-section"><div class="ft-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="3" width="20" height="6" rx="1.5"/><rect x="2" y="11" width="20" height="6" rx="1.5"/><line x1="6" y1="6" x2="6.01" y2="6"/><line x1="6" y1="14" x2="6.01" y2="14"/></svg> Penyedia Game</div><div class="ft-prov">'+pg+'</div></div>'+
  // ─── 3. CASINO TEAM / CS ───
  '<div class="ft-section"><div class="ft-title"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg> Casino Team</div>'+
  '<div class="ft-team"><a class="ft-team-card" href="'+ccsLink+'" target="_blank">'+
  '<div class="ft-team-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></div>'+
  '<div class="ft-team-info"><b>Live Chat 24/7</b><span>Online sekarang</span></div></a>'+
  '<a class="ft-team-card" href="cs.php"><div class="ft-team-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07 19.5 19.5 0 01-6-6 19.79 19.79 0 01-3.07-8.67A2 2 0 014.11 2h3a2 2 0 012 1.72 12.84 12.84 0 00.7 2.81 2 2 0 01-.45 2.11L8.09 9.91a16 16 0 006 6l1.27-1.27a2 2 0 012.11-.45 12.84 12.84 0 002.81.7A2 2 0 0122 16.92z"/></svg></div><div class="ft-team-info"><b>Customer Service</b><span>Bantuan 24 jam</span></div></a></div></div>'+
  // ─── 4. TRUST BADGES ───
  '<div class="ft-badges"><div class="ft-b"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>21+</div><div class="ft-b"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>GAMCARE</div><div class="ft-b"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>GambleAware</div><div class="ft-b"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4M12 16h.01"/></svg>18+</div></div>'+
  // ─── 5. SOCIAL ───
  '<div class="ft-social"><a class="ft-s" href="#">'+svgFb+'</a><a class="ft-s" href="#">'+svgWa+'</a><a class="ft-s" href="#">'+svgIg+'</a><a class="ft-s" href="#">'+svgX+'</a><a class="ft-s" href="#">'+svgTg+'</a></div>'+
  // ─── 6. LEGALITY ───
  '<div class="ft-legal-section"><div class="ft-title" style="margin-bottom:8px;justify-content:center"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg> Legalitas &amp; Lisensi</div><div class="ft-legal">'+siteName+'.COM dioperasikan oleh '+siteName+' N.V., dilisensikan oleh Pemerintah Cura&#231;ao.<br>Nomor Lisensi: GLH-OCCHKTW0666862023<br>Produk ini untuk 18+ dan tujuan hiburan.</div></div>'+
  '<div style="text-align:center;font-size:.65rem;color:var(--t3);padding-bottom:10px">&#169;2026 '+siteName+'.COM Seluruh hak cipta.</div>';
}
var _isGameLaunching=false;
var _gameLoadTimer=null;
async function launchGame(prov,code){
  if(!IS_LOGGED_IN){openM('login');return;}
  if(_isGameLaunching)return;
  _isGameLaunching=true;
    // Show loading overlay (with close button so user not stuck)
    var gl=document.getElementById('gameLoading');
    var go=document.getElementById('gameOverlay');
    gl.classList.add('show');go.classList.add('show');
    gl.innerHTML='<div style="text-align:center"><div class="gl-spin"></div><div class="gl-text" id="loadMsg">Menyiapkan akun game...</div><button onclick="closeGame()" style="margin-top:24px;padding:10px 22px;background:rgba(255,255,255,.1);border:1.5px solid rgba(255,255,255,.25);border-radius:10px;color:#fff;font-family:inherit;font-size:.82rem;font-weight:700;cursor:pointer">✕ Batal</button></div>';
    
    try{
        // Step 1: Ensure NexusGGR user exists
        document.getElementById('loadMsg').textContent='Menyiapkan akun...';
        var eu=await fetch('api/game.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'ensure_user'})});
        var ed=await eu.json();
        if(!ed.ok){gl.classList.remove('show');go.classList.remove('show');_isGameLaunching=false;return toast(ed.error||'Gagal menyiapkan akun')}
        
        // Step 2: Launch game (transfers balance automatically)
        document.getElementById('loadMsg').textContent='Memuat game...';
        var r=await fetch('api/game.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'launch',provider:prov,game_code:code})});
        var d=await r.json();
        
        if(d.ok&&d.launch_url){
            document.getElementById('loadMsg').textContent='Memuat game...';
            var fr=document.getElementById('gameFrame');
            // Auto-dismiss loading after 6s — many games don't fire onload (cross-origin iframe quirk)
            var dismissed=false;
            var dismiss=function(){if(dismissed)return;dismissed=true;gl.classList.remove('show');_isGameLaunching=false;if(_gameLoadTimer){clearTimeout(_gameLoadTimer);_gameLoadTimer=null}};
            fr.onload=dismiss;
            _gameLoadTimer=setTimeout(dismiss,6000);  // fallback timeout
            fr.src=d.launch_url;
            syncBal();
        }else{
            gl.classList.remove('show');go.classList.remove('show');
            _isGameLaunching=false;
            toast(d.error||'Gagal membuka game');
        }
    }catch(e){gl.classList.remove('show');go.classList.remove('show');_isGameLaunching=false;toast('Server error: '+(e.message||e))}
}
function closeGame(){
    _isGameLaunching=false;
    if(_gameLoadTimer){clearTimeout(_gameLoadTimer);_gameLoadTimer=null}
    var go_=document.getElementById('gameOverlay');var gl_=document.getElementById('gameLoading');
    go_.classList.add('closing');gl_.classList.remove('show');
    setTimeout(function(){go_.classList.remove('show');go_.classList.remove('closing');document.getElementById('gameFrame').src=''},300);
    document.getElementById('gameLoading').innerHTML='';
    // Auto-withdraw game balance back to web when closing game
    syncBal();
}

function doLogout(){fetch('api/auth.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'logout'})}).then(function(){localStorage.removeItem('app_user');location.href='index.php'})}

// Balance — only when logged in (otherwise hBal/sbBal2 elements don't exist)
if(user && IS_LOGGED_IN){
  var bal=parseInt(user.balance||user.bal||0);
  var hbEl=document.getElementById('hBal');if(hbEl)hbEl.textContent=(bal/1000).toFixed(2)+'K';
  var sbEl=document.getElementById('sbBal2');if(sbEl)sbEl.textContent=(bal/1000).toFixed(2)+'K';
}

// Banner slider
let cs=0,ts=3;
function goS(n){if(ts<=0)return;cs=n;document.getElementById('bTrack').style.transform='translateX(-'+n*100+'%)';document.querySelectorAll('.b-dots .dot').forEach((d,i)=>d.classList.toggle('active',i===n))}
setInterval(()=>{if(ts>0)goS((cs+1)%ts)},4000);

// ═══ SEARCH (simple) ═══
var srchMode='semua',allGames=[];
function buildAllGames(){if(!allGames.length)PROVS.forEach(function(p){(p.games||[]).forEach(function(g){allGames.push({name:g.game_name,banner:g.banner,code:g.game_code,prov:p})})})}
function openSrch(){
  document.getElementById('srchOv').classList.add('open');
  buildAllGames();
  srchPill('semua',document.querySelector('.srch-pill'));
  document.getElementById('srchQ').value='';
  setTimeout(function(){document.getElementById('srchQ').focus()},100);
}
function closeSrch(){var el=document.getElementById('srchOv');el.classList.add('closing');setTimeout(function(){el.classList.remove('open');el.classList.remove('closing')},300)}
function srchPill(mode,el){
  srchMode=mode;
  document.querySelectorAll('.srch-pill').forEach(function(t){t.classList.remove('on')});
  if(el)el.classList.add('on');
  doSearch();
}
function gcCard(g,onclick){
  var rtp=(Math.random()*5+95).toFixed(1);
  var gn=g.name||'Game';
  var pn=(g.prov&&(g.prov.name||g.prov.code))||'';
  var bn=g.banner||'';
  if(!bn || bn.length<4) return '';  // game tanpa banner di-skip (maintenance)
  var imgHtml='<img src="'+bn+'" loading="lazy" onload="if(this.naturalWidth<50||this.naturalHeight<50){this.onerror();}" onerror="this.onerror=null;this.style.visibility=\'hidden\';">';
  return '<div class="gwrap"><a class="gc" href="#" onclick="'+onclick+';return false">'+imgHtml+'<div class="gc-rtp">'+rtp+'%</div><div class="gc-fav"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14" style="display:inline-block;vertical-align:middle"><path d="M20.84 4.61a5.5 5.5 0 00-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 00-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 000-7.78z"/></svg></div><div class="gn"><div class="gn-name">'+gn+'</div><div class="gn-prov">'+pn+'</div></div></a></div>';
}
function doSearch(){
  var q=document.getElementById('srchQ').value.trim().toLowerCase();
  var grid=document.getElementById('srchGrid');
  var list=allGames;
  if(srchMode==='populer')list=list.slice(0,60);
  else if(srchMode==='terkini')list=list.slice().reverse().slice(0,60);
  else if(srchMode==='favorit'){
    var fv=JSON.parse(localStorage.getItem('app_favs')||'{}');
    list=list.filter(function(g){return fv[g.prov.code+'_'+g.code]});
    if(!list.length){grid.innerHTML='<div class="srch-empty">Belum ada favorit</div>';return}
  }
  if(q)list=list.filter(function(g){return g.name.toLowerCase().indexOf(q)!==-1});
  if(!list.length){grid.innerHTML='<div class="srch-empty">Tidak ditemukan</div>';return}
  var h='';list.forEach(function(g){h+=gcCard(g,"closeSrch();launchGame('"+g.prov.code+"','"+g.code+"')")});
  grid.innerHTML=h;
}
// ═══ PROVIDER BROWSE ═══
var activeProv='';
function openProvOv(code){
  document.getElementById('provOv').classList.add('open');
  buildAllGames();buildProvSidebar(code);
  document.getElementById('provQ').value='';
  doProvSearch();
}
function closeProvOv(){var el=document.getElementById('provOv');el.classList.add('closing');setTimeout(function(){el.classList.remove('open');el.classList.remove('closing')},300)}
function buildProvSidebar(activeCode){
  activeProv=activeCode||'';
  var el=document.getElementById('provOvSide');
  var h='';
  PROVS.forEach(function(p){
    if(!p.games||!p.games.length)return;
    var on=p.code===activeProv?' on':'';
    var logo=p.logo?'<img src="'+p.logo+'">':'<span style="font-size:.65rem;font-weight:800;color:var(--t2)">'+(p.name||p.code)+'</span>';
    h+='<div class="po-prov'+on+'" onclick="pickProv(\''+p.code+'\',this)"><div class="pp-logo">'+logo+'</div><div class="pp-name">'+(p.name||p.code)+'</div></div>';
  });
  el.innerHTML=h;
}
function pickProv(code,el){
  activeProv=code;
  document.querySelectorAll('.po-prov').forEach(function(p){p.classList.remove('on')});
  if(el)el.classList.add('on');
  document.getElementById('provQ').value='';
  doProvSearch();
}
function doProvSearch(){
  var q=document.getElementById('provQ').value.trim().toLowerCase();
  var grid=document.getElementById('provOvGrid');
  var list=allGames;
  if(activeProv)list=list.filter(function(g){return g.prov.code===activeProv});
  if(q)list=list.filter(function(g){return g.name.toLowerCase().indexOf(q)!==-1});
  if(!list.length){grid.innerHTML='<div class="srch-empty">Tidak ditemukan</div>';return}
  var h='';list.forEach(function(g){h+=gcCard(g,"closeProvOv();launchGame('"+g.prov.code+"','"+g.code+"')")});
  grid.innerHTML=h;
}
// Welcome popup
function wpTab(i,el){document.querySelectorAll('.wp-tab').forEach(t=>t.classList.remove('active'));document.querySelectorAll('.wp-slide').forEach(s=>s.classList.remove('active'));el.classList.add('active');document.querySelectorAll('.wp-slide')[i].classList.add('active')}
function closeWP(){var ov=document.getElementById('wpOverlay');ov.classList.remove('show');if(document.getElementById('wpNoShow').checked)localStorage.setItem('app_no_popup',new Date().toDateString())}
function showWP(){if(localStorage.getItem('app_no_popup')!==new Date().toDateString())document.getElementById('wpOverlay').classList.add('show')}

async function syncBal(){
    // Tarik sisa game balance HANYA kalau tidak sedang launching game
    if(!_isGameLaunching){
        try{await fetch('api/game.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'withdraw_game'})});}catch(e){}
    }
    // Refresh tampilan saldo
    try{
        var r=await fetch('api/auth.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'me'})});
        var d=await r.json();
        if(d.ok&&d.user){
            localStorage.setItem('app_user',JSON.stringify(d.user));
            var b=parseInt(d.user.balance)||0;
            var hb=document.getElementById('hBal');if(hb)hb.textContent=(b/1000).toFixed(2)+'K';
            var sb=document.getElementById('sbBal2');if(sb)sb.textContent=(b/1000).toFixed(2)+'K';
        }
    }catch(e){}
}

function init(){
    if(IS_LOGGED_IN)syncBal();
    SI=<?php echo $SI_JSON; ?>;
    PROVS=<?php echo $PROVS_JSON; ?>;
    BANNERS=<?php echo $BANNERS_JSON; ?>;
    CARDS=<?php echo $CARDS_JSON; ?>;
    SBGAMES=<?php echo $SBGAMES_JSON; ?>;
    // Logo: kalau ada pake gambar, kalau ga tampilin nama situs sbg text
    var logoUrl=SI.logo_url||'';var siteName=SI.site_name||'Situs';
    function applyLogo(el){
      if(!el)return;
      var nameSpan='<span style="font-family:\'Chakra Petch\',\'Poppins\',sans-serif;font-size:1.1rem;font-weight:800;color:var(--pri);letter-spacing:.5px">'+siteName+'</span>';
      if(logoUrl){
        el.onload=function(){el.style.display=''};
        el.onerror=function(){el.style.display='none';el.insertAdjacentHTML('afterend',nameSpan)};
        el.src=logoUrl;
      }else{
        el.style.display='none';
        el.insertAdjacentHTML('afterend',nameSpan);
      }
    }
    applyLogo(document.getElementById('dashLogo'));
    applyLogo(document.getElementById('sidebarLogo2'));
    renderBanners();renderQA();renderSBPop();renderProvDiamonds();renderPopular();renderProvSections();renderFooter();renderWelcome();showWP();
    // Marquee from admin settings
    var mq=document.getElementById('sbMarquee');
    if(mq){var txt=SI.marquee_text||'Selamat datang! Deposit & withdraw cepat 24 jam.';mq.textContent=txt+' \u2022 '+txt+' \u2022 ';}

    // NOTE: Reconcile pending deposits dihandle global di pwa_head.php (throttle 15s + toast notif).
    // Jadi tidak perlu duplicate di sini.
}

function renderBanners(){
    if(!BANNERS.length){var el=document.getElementById('bTrack');if(el)el.innerHTML='';document.getElementById('bDots').innerHTML='';ts=0;return;}
    ts=BANNERS.length;var el=document.getElementById('bTrack');if(!el)return;
    var h='',dots='';
    BANNERS.forEach(function(b,i){
        h+='<div class="b-slide">';
        if(b.link)h+='<a href="'+b.link+'" style="display:block;position:relative">';
        h+='<img class="b-img" src="'+b.image_url+'" style="width:100%;height:100%;object-fit:cover">';
        if(b.link)h+='</a>';
        h+='</div>';
        dots+='<button class="dot'+(i===0?' active':'')+'" onclick="goS('+i+')"></button>';
    });
    el.innerHTML=h;document.getElementById('bDots').innerHTML=dots;
    el.classList.add('content-loaded');
    // Inject watermark logo
    var logo=SI.logo_url||'';
    if(logo){
      var wmSz=parseInt(SI.wm_size)||100;
      var wmBt=parseInt(SI.wm_bottom)||0;
      var wmRt=parseInt(SI.wm_right)||0;
      var wmOp=(parseInt(SI.wm_opacity)||90)/100;
      var wmSc=SI.wm_stroke||'#ffffff';
      el.querySelectorAll('.b-slide').forEach(function(s){
        var wm=document.createElement('img');
        wm.src=logo;wm.style.cssText='position:absolute;bottom:'+wmBt+'px;right:'+wmRt+'px;width:'+wmSz+'px;height:'+wmSz+'px;object-fit:contain;opacity:'+wmOp+';z-index:5;pointer-events:none;filter:drop-shadow(2px 0 0 '+wmSc+') drop-shadow(-2px 0 0 '+wmSc+') drop-shadow(0 2px 0 '+wmSc+') drop-shadow(0 -2px 0 '+wmSc+') drop-shadow(0 2px 6px rgba(0,0,0,.5))';
        wm.onerror=function(){this.remove()};
        s.appendChild(wm);
      });
    }
}
function renderQA(){
    if(!CARDS.length){document.getElementById('qaRow').innerHTML='';return;}
    var h='';CARDS.forEach(function(c){
        h+='<div class="qa-card" style="border:none;background:transparent">';
        h+='<img class="qa-img" src="'+c.image_url+'" style="width:100%;height:100%;object-fit:cover;border-radius:14px">';
        h+='</div>';
    });
    var qa=document.getElementById('qaRow');qa.innerHTML=h;qa.classList.add('content-loaded');
}
function renderProvDiamonds(){
    var el=document.getElementById('provRow');if(!PROVS.length)return;
    var h='';PROVS.forEach(function(p){var name=(p.name||p.code||'').toUpperCase();h+='<div class="prov-item" onclick="openProvOv(\''+p.code+'\')"><div class="prov-diamond"><div class="inner">'+logoHtml(p)+'</div></div><div class="prov-name">'+name+'</div></div>'});
    el.innerHTML=h;el.classList.add('content-loaded');
}
function renderSBPop(){
    var el=document.getElementById('sbPopGames');if(!el)return;
    var games=SBGAMES.length?SBGAMES:[];
    if(!games.length&&PROVS.length){PROVS.forEach(function(p){(p.games||[]).slice(0,2).forEach(function(g){if(g.banner)games.push({game_code:g.game_code,game_name:g.game_name,banner:g.banner,provider_code:p.code})})});games=games.slice(0,8)}
    var h='';games.forEach(function(g){
        h+='<img src="'+g.banner+'" onclick="launchGame(\''+g.provider_code+'\',\''+g.game_code+'\')" style="width:56px;height:56px;border-radius:6px;object-fit:cover;flex-shrink:0;cursor:pointer" title="'+g.game_name+'">';
    });
    el.innerHTML=h;
}
function scrollSec(id,dir){
    if(!pageState[id])pageState[id]={page:0,expanded:false};
    pageState[id].page+=dir;
    if(id==='pop')renderPopular();
    else renderProvSection(id);
}
function toggleExpand(id){
    if(!pageState[id])pageState[id]={page:0,expanded:false};
    pageState[id].expanded=!pageState[id].expanded;
    pageState[id].page=0;
    renderProvSection(id);
}
function toggleExpandPop(){
    if(!pageState['pop'])pageState['pop']={page:0,expanded:false};
    pageState['pop'].expanded=!pageState['pop'].expanded;
    pageState['pop'].page=0;
    renderPopular();
}
function renderPopular(){
    var grid=document.getElementById('popGrid');var cnt=document.getElementById('popCnt');
    var more=document.getElementById('popMore');
    var all=[];var featured=[];var rest=[];
    PROVS.forEach(function(p){(p.games||[]).forEach(function(g){
        var obj={name:g.game_name,banner:g.banner,code:g.game_code,prov:p,feat:g.featured||0};
        if(g.featured)featured.push(obj);else rest.push(obj);
    })});
    all=featured.concat(rest);
    cnt.textContent=all.length;
    // Hide entire Populer section header + body when empty (avoid showing "Populer 0")
    var secH=cnt.closest('.sec-h');
    var secBody=grid.closest('.prov-sec');
    if(!all.length){
      if(secH)secH.style.display='none';
      if(secBody)secBody.style.display='none';
      grid.innerHTML='';
      if(more)more.style.display='none';
      return;
    }
    if(secH)secH.style.display='';
    if(secBody)secBody.style.display='';
    if(!pageState['pop'])pageState['pop']={page:0,expanded:false};
    var ps=pageState['pop'];
    var perPage=ps.expanded?40:7;
    var pg=ps.page;var mx=Math.ceil(all.length/perPage)-1;
    if(pg<0)pg=mx;if(pg>mx)pg=0;ps.page=pg;
    var st=pg*perPage;var en=Math.min(st+perPage,all.length);
    var h='';
    for(var i=st;i<en;i++){var g=all[i];var bn=g.banner||'';var gn=g.name||'Game';var pn=(g.prov&&(g.prov.name||g.prov.code))||'';
    if(!bn || bn.length<4) continue;
    var pl=(g.prov&&g.prov.logo)?g.prov.logo:'';
    var provBadge=pl?'<img src="'+pl+'" alt="">':'<span class="gc-prov-name">'+pn+'</span>';
    h+='<div class="gwrap" onclick="launchGame(\''+g.prov.code+'\',\''+g.code+'\');return false" style="cursor:pointer"><a class="gc" href="#" onclick="event.preventDefault();launchGame(\''+g.prov.code+'\',\''+g.code+'\');return false">';
    h+='<img src="'+bn+'" loading="lazy" onload="if(this.naturalWidth<50||this.naturalHeight<50){this.onerror();}" onerror="this.onerror=null;this.style.visibility=\'hidden\';">';
    h+='<div class="gc-overlay">'+provBadge+'</div></a><div class="gn">'+gn+'</div></div>'}
    grid.innerHTML=h;
    grid.classList.add('content-loaded');
    if(more){
      if(ps.expanded)more.innerHTML='<span>Tutup</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>';
      else more.innerHTML='<span>Semua</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>';
    }
}
function renderProvSection(id){
    var el=document.getElementById('sec_'+id);if(!el)return;
    var p=PROVS.find(function(x){return x.code===id});if(!p)return;
    var games=p.games||[];
    if(!pageState[id])pageState[id]={page:0,expanded:false};
    var ps=pageState[id];
    var perPage=ps.expanded?40:7;
    var pg=ps.page;var mx=Math.ceil(games.length/perPage)-1;
    if(pg<0)pg=mx;if(pg>mx)pg=0;ps.page=pg;
    var st=pg*perPage;var en=Math.min(st+perPage,games.length);
    var h='';
    for(var i=st;i<en;i++){var g=games[i];var bn=g.banner||'';var gn=g.game_name||'Game';var pn=p.name||p.code;
    if(!bn || bn.length<4) continue;
    var pl=p.logo||'';
    var provBadge=pl?'<img src="'+pl+'" alt="">':'<span class="gc-prov-name">'+pn+'</span>';
    h+='<div class="gwrap"><a class="gc" href="#" onclick="launchGame(\''+p.code+'\',\''+g.game_code+'\');return false">';
    h+='<img src="'+bn+'" loading="lazy" onload="if(this.naturalWidth<50||this.naturalHeight<50){this.onerror();}" onerror="this.onerror=null;this.style.visibility=\'hidden\';">';
    h+='<div class="gc-overlay">'+provBadge+'</div></a><div class="gn">'+gn+'</div></div>'}
    el.innerHTML=h;
    var btn=document.getElementById('more_'+id);
    if(btn){
      if(ps.expanded)btn.innerHTML='<span>Tutup</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="18 15 12 9 6 15"/></svg>';
      else btn.innerHTML='<span>Semua</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg>';
    }
}
function renderProvSections(){
    var el=document.getElementById('provSections');if(!PROVS.length)return;
    var h='';PROVS.forEach(function(p,pi){var games=p.games||[];if(!games.length)return;var cnt=p.game_count||games.length;
    h+='<div class="prov-sec"><div class="prov-sec-hdr"><div class="prov-sec-left"><h4>'+(p.name||p.code)+'</h4><span class="ps-cnt">'+cnt+'</span></div><div class="prov-sec-right" style="display:flex;flex-direction:row;align-items:center;gap:6px"><button class="ps-arr" onclick="scrollSec(\''+p.code+'\',-1)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="15 18 9 12 15 6"/></svg></button><button class="ps-arr" onclick="scrollSec(\''+p.code+'\',1)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="9 18 15 12 9 6"/></svg></button></div></div><div class="prov-sec-grid" id="sec_'+p.code+'">';
    var st=0;var en=Math.min(7,games.length);
    for(var i=st;i<en;i++){var g=games[i];var bn=g.banner||'';var gn=g.game_name||'Game';var pn=p.name||p.code;
    if(!bn || bn.length<4) continue;
    var pl=p.logo||'';
    var provBadge=pl?'<img src="'+pl+'" alt="">':'<span class="gc-prov-name">'+pn+'</span>';
    h+='<div class="gwrap"><a class="gc" href="#" onclick="launchGame(\''+p.code+'\',\''+g.game_code+'\');return false">';
    h+='<img src="'+bn+'" loading="lazy" onload="if(this.naturalWidth<50||this.naturalHeight<50){this.onerror();}" onerror="this.onerror=null;this.style.visibility=\'hidden\';">';
    h+='<div class="gc-overlay">'+provBadge+'</div></a><div class="gn">'+gn+'</div></div>'}

    h+='</div>';
    if(games.length>7)h+='<div class="prov-sec-more" id="more_'+p.code+'" onclick="toggleExpand(\''+p.code+'\')"><span>Semua</span><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="6 9 12 15 18 9"/></svg></div>';
    h+='</div>'});
    el.innerHTML=h;
}
function renderWelcome(){
    var popups=[
        {key:'popup_img_1',label:SI.popup_label_1||'Kode Keberuntungan'},
        {key:'popup_img_2',label:SI.popup_label_2||'Bonus Harian'},
        {key:'popup_img_3',label:SI.popup_label_3||'Promosi'}
    ].filter(function(p){return !!SI[p.key]});
    if(!popups.length){document.getElementById('wpOverlay').style.display='none';return;}
    var slides='',tabs='';
    popups.forEach(function(p,i){
        slides+='<div class="wp-slide'+(i===0?' active':'')+'"><img src="'+SI[p.key]+'" style="width:100%;display:block;border-radius:0"></div>';
        tabs+='<div class="wp-tab'+(i===0?' active':'')+'" onclick="wpTab('+i+',this)">'+p.label+'</div>';
    });
    document.querySelector('.wp-content').innerHTML=slides;
    document.querySelector('.wp-tabs').innerHTML=tabs;
}

// Draggable floating buttons
document.querySelectorAll('.fl-drag').forEach(function(el){
    var sx,sy,ox,oy,drag=false;
    el.addEventListener('touchstart',function(e){sx=e.touches[0].clientX;sy=e.touches[0].clientY;var r=el.getBoundingClientRect();ox=sx-r.left;oy=sy-r.top;drag=false},{passive:true});
    el.addEventListener('touchmove',function(e){drag=true;var x=e.touches[0].clientX-ox;var y=e.touches[0].clientY-oy;x=Math.max(0,Math.min(window.innerWidth-el.offsetWidth,x));y=Math.max(0,Math.min(window.innerHeight-el.offsetHeight,y));el.style.left=x+'px';el.style.top=y+'px';el.style.bottom='auto';el.style.right='auto';e.preventDefault()},{passive:false});
    el.addEventListener('touchend',function(e){if(drag)e.preventDefault()});
});

// ═══ LOGIN/REGISTER MODAL HANDLERS ═══
function openM(t){document.querySelectorAll('.mo').forEach(function(m){m.classList.remove('active')});var el=document.getElementById(t+'Modal');if(el)el.classList.add('active');document.body.style.overflow='hidden'}
function closeM(){document.querySelectorAll('.mo.active').forEach(function(m){m.classList.add('closing');setTimeout(function(){m.classList.remove('active');m.classList.remove('closing')},300)});document.body.style.overflow=''}
function switchM(t){document.querySelectorAll('.mo').forEach(function(m){m.classList.remove('active')});requestAnimationFrame(function(){var el=document.getElementById(t+'Modal');if(el)el.classList.add('active')})}
document.querySelectorAll('.mo').forEach(function(m){m.addEventListener('click',function(e){if(e.target===m)closeM()})});
function tPw(el){var i=el.parentElement.querySelector('input');var show=i.type==='password';i.type=show?'text':'password';el.innerHTML=show?'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>':'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/><line x1="1" y1="1" x2="23" y2="23"/></svg>'}
async function doReg(){
  var p=document.getElementById('rPh').value.trim(),w=document.getElementById('rPw').value,c=document.getElementById('rCf').value;
  function getRefCode(){
    var ri=document.getElementById('rRef');if(ri&&ri.value.trim())return ri.value.trim();
    var u=new URLSearchParams(location.search).get('ref');if(u)return u;
    var ck=document.cookie.split(';');for(var i=0;i<ck.length;i++){var pp=ck[i].trim().split('=');if(pp[0]==='ref_code'&&pp[1])return pp[1];}
    try{var ls=localStorage.getItem('ref_code');if(ls)return ls}catch(e){}
    return '';
  }
  var ref=getRefCode();
  if(!p)return toast('Nomor telepon wajib');
  if(p.length<6)return toast('Nomor telepon minimal 6 digit');
  if(!/^\d+$/.test(p))return toast('Nomor telepon hanya angka');
  if(w.length<6)return toast('Password min 6 karakter');
  if(w!==c)return toast('Konfirmasi kata sandi tidak cocok');
  var btn=document.getElementById('regBtn');
  if(btn){btn.disabled=true;btn.textContent='Mendaftar...';}
  try{
    var r=await fetch('api/auth.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'register',username:p,email:p+'',phone:p,password:w,confirm:w,referral:ref})});
    var d=await r.json();
    if(d.ok){localStorage.setItem('app_user',JSON.stringify(d.user||{username:p}));location.reload();return}
    toast(d.error||'Gagal mendaftar');
  }catch(e){toast('Server error, coba lagi');}
  if(btn){btn.disabled=false;btn.textContent='Daftar';}
}
async function doLogin(){
  var p=document.getElementById('lPh').value.trim(),w=document.getElementById('lPw').value;
  if(!p||!w)return toast('Telepon dan kata sandi wajib');
  var btn=document.querySelector('#loginModal .btn-sub');
  if(btn){btn.disabled=true;btn.textContent='Masuk...';}
  try{
    var r=await fetch('api/auth.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'login',username:p,password:w})});
    var d=await r.json();
    if(d.ok&&d.user){localStorage.setItem('app_user',JSON.stringify(d.user));if(d.user.role==='admin'){location.href='team/index.php'}else{location.reload();}return}
    toast(d.error||'Login gagal');
  }catch(e){toast('Server error');}
  if(btn){btn.disabled=false;btn.textContent='Masuk';}
}

init();

// Load site images from admin settings
(async function(){try{
    var r=await fetch('api/data.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'settings'})});
    var d=await r.json();if(!d.ok)return;var s=d.settings||{};
    // Apply settings-based images
    var map={'hdrSaldoIcon':'saldo_icon_img','sbSaldoIcon':'saldo_icon_img'};
    for(var id in map){var el=document.getElementById(id);if(el&&s[map[id]])el.src=s[map[id]];}
}catch(e){}})();
</script>

<?php if($isLoggedIn): ?>
<!-- Floating Buttons -->
<style>
@keyframes flBounce{0%,100%{transform:translateY(0) scale(1)}25%{transform:translateY(-6px) scale(1.03)}50%{transform:translateY(-2px) scale(1)}75%{transform:translateY(-8px) scale(1.05) rotate(-2deg)}}
.fl-float{position:fixed;z-index:90;cursor:pointer;animation:flBounce 3s ease-in-out infinite;will-change:transform}
.fl-float:active{animation:none;transform:scale(.92)!important}
.fl-x{position:absolute;top:4px;right:4px;width:18px;height:18px;border-radius:50%;background:rgba(0,0,0,.55);color:#fff;font-size:.65rem;display:flex;align-items:center;justify-content:center;border:none;cursor:pointer;z-index:2;line-height:1}
</style>
<!-- Floating CS (left) — shared component -->
<?php
  $flCsOnclick = 'openChat()';
  $flCsBottom = '80px';
  require __DIR__.'/includes/fl_cs.php';
?>
<!-- Floating Spin (right, above invite) -->
<a href="spin.php" class="fl-float" id="flSpin" style="right:8px;bottom:160px;width:72px;border-radius:10px;overflow:hidden;text-decoration:none;display:none;animation-delay:.4s">
<button class="fl-x" onclick="event.preventDefault();event.stopPropagation();document.getElementById('flSpin').style.display='none'">x</button>
<div id="flSpinBody"></div>
</a>
<!-- Floating Invite (right, below spin) -->
<div class="fl-float" id="flInvDash" style="right:8px;bottom:80px;width:72px;border-radius:10px;overflow:hidden;box-shadow:none;animation-delay:.8s" onclick="goUndang(event)">
<button class="fl-x" onclick="event.stopPropagation();this.parentElement.style.display='none'">x</button>
<div id="flInvBody"></div>
</div>
<script>
function openChat(){location.href='cs.php'}
function goUndang(e){if(e.target.tagName!=='BUTTON')location.href='undang.php';}
// Float CS handled by shared include (includes/fl_cs.php)
// Float spin - image from admin or default
(function(){
  var el=document.getElementById('flSpinBody');if(!el)return;
  var wrap=document.getElementById('flSpin');
  if(SI.float_spin_img){
    el.innerHTML='<img src="'+SI.float_spin_img+'" style="width:100%;display:block">';
    wrap.style.display='block';
  }else{
    el.innerHTML='<div style="background:linear-gradient(135deg,var(--pri),var(--pri-d,var(--sec-d)));padding:10px 8px;text-align:center"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" width="24" height="24" style="margin:0 auto 2px;display:block"><circle cx="12" cy="12" r="10"/><path d="M12 2v20M2 12h20"/></svg><span style="font-size:.5rem;font-weight:800;color:#fff;display:block;line-height:1">LUCKY<br>SPIN</span></div>';
    wrap.style.display='block';
  }
})();
// Float invite - image only
(function(){var el=document.getElementById('flInvBody');if(!el)return;if(SI.float_invite_img){el.innerHTML='<a href="undang.php"><img src="'+SI.float_invite_img+'" style="width:100%;display:block"></a>'}else{el.parentElement.style.display='none'}})();
// Drag-to-scroll for desktop
document.querySelectorAll('.qa-row').forEach(function(el){
  var isDown=false,startX,scrollL;
  el.addEventListener('mousedown',function(e){isDown=true;el.style.cursor='grabbing';startX=e.pageX-el.offsetLeft;scrollL=el.scrollLeft;e.preventDefault()});
  el.addEventListener('mouseleave',function(){isDown=false;el.style.cursor='grab'});
  el.addEventListener('mouseup',function(){isDown=false;el.style.cursor='grab'});
  el.addEventListener('mousemove',function(e){if(!isDown)return;e.preventDefault();el.scrollLeft=scrollL-(e.pageX-el.offsetLeft-startX)});
});
</script>
<?php endif; ?>

<!-- Notifikasi Prompt Banner (hanya muncul kalau user belum granted + belum pernah di-dismiss) -->
<div id="notifPrompt" style="display:none;position:fixed;bottom:80px;left:10px;right:10px;z-index:150;background:linear-gradient(135deg,var(--sec),var(--sec-d));border-radius:14px;padding:14px;box-shadow:0 10px 30px rgba(0,0,0,.4);animation:slideUp .4s ease">
<style>@keyframes slideUp{from{transform:translateY(100px);opacity:0}to{transform:translateY(0);opacity:1}}</style>
<div style="display:flex;gap:12px;align-items:flex-start">
  <div style="flex-shrink:0;width:40px;height:40px;background:var(--tint-3);border-radius:10px;display:flex;align-items:center;justify-content:center">
    <svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" width="22" height="22"><path d="M18 8A6 6 0 006 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 01-3.46 0"/></svg>
  </div>
  <div style="flex:1;min-width:0">
    <div style="font-weight:700;color:#fff;font-size:.85rem;margin-bottom:2px">Aktifkan Notifikasi</div>
    <div style="font-size:.68rem;color:rgba(255,255,255,.85);line-height:1.4;margin-bottom:10px">Dapat info deposit, withdraw, bonus langsung di HP</div>
    <div style="display:flex;gap:8px">
      <button onclick="dashEnableNotif()" style="flex:1;padding:8px 12px;background:#fff;border:none;border-radius:8px;font-weight:700;font-size:.7rem;color:var(--sec-d);cursor:pointer;font-family:inherit">Aktifkan</button>
      <button onclick="dashDismissNotif()" style="padding:8px 12px;background:var(--tint-3);border:none;border-radius:8px;font-size:.7rem;color:#fff;cursor:pointer;font-family:inherit">Nanti</button>
    </div>
  </div>
</div>
</div>

<div class="game-overlay" id="gameOverlay">
<div class="game-loading" id="gameLoading"></div>
<iframe id="gameFrame" src=""></iframe>
<div class="fl-drag" id="flLobby" style="right:0;top:0;left:auto" onclick="closeGame()">
<div class="fl-btn"><div class="fl-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg></div><span class="fl-label">Lobby</span></div>
</div>
</div>
<?php include 'includes/credit_notify.php'; ?>

<!-- LOGIN/REGISTER MODALS -->
<div class="mo" id="loginModal"><div class="mb"><button class="mx" onclick="closeM()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button><h2>Masuk ke akun Anda</h2><p class="sub">Belum punya akun? <a onclick="switchM('register')">Daftar</a></p><form id="loginForm" action="#" method="POST" onsubmit="event.preventDefault();doLogin();return false" autocomplete="on"><div class="fg"><div class="iw"><div class="pfx"><div class="fl"><span></span><span></span></div>+62</div><input type="tel" placeholder="Telepon" id="lPh" name="username" autocomplete="username" inputmode="numeric" required></div></div><div class="fg"><div class="iw"><div class="pfx" style="border-right:none;padding-right:0"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 11-7.778 7.778 5.5 5.5 0 017.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg></div><input type="password" placeholder="Kata sandi" id="lPw" name="password" autocomplete="current-password" required><div class="ico" onclick="tPw(this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/><line x1="1" y1="1" x2="23" y2="23"/></svg></div></div></div><div class="fg-row"><label><input type="checkbox" checked> Ingat kata sandi</label><a href="#">Lupa sandi?</a></div><button class="btn-sub" type="submit">Masuk</button></form></div></div>
<div class="mo" id="registerModal"><div class="mb"><button class="mx" onclick="closeM()"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg></button><h2>Buat akun permainan</h2><p class="sub">Sudah punya akun? <a onclick="switchM('login')">Masuk</a></p><form id="registerForm" action="#" method="POST" onsubmit="event.preventDefault();doReg();return false" autocomplete="on"><div class="fg"><div class="iw"><div class="pfx"><div class="fl"><span></span><span></span></div>+62</div><input type="tel" placeholder="Telepon (min 6 digit)" id="rPh" name="username" autocomplete="username" inputmode="numeric" minlength="6" required></div></div><div class="fg"><div class="iw"><div class="pfx" style="border-right:none;padding-right:0"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 11-7.778 7.778 5.5 5.5 0 017.777-7.777zm0 0L15.5 7.5"/></svg></div><input type="password" placeholder="Kata sandi" id="rPw" name="new-password" autocomplete="new-password" minlength="6" required><div class="ico" onclick="tPw(this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/><line x1="1" y1="1" x2="23" y2="23"/></svg></div></div></div><div class="fg"><div class="iw"><div class="pfx" style="border-right:none;padding-right:0"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 11-7.778 7.778 5.5 5.5 0 017.777-7.777zm0 0L15.5 7.5"/></svg></div><input type="password" placeholder="Masukkan kata sandi lagi" id="rCf" name="confirm-password" autocomplete="new-password" required><div class="ico" onclick="tPw(this)"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/><line x1="1" y1="1" x2="23" y2="23"/></svg></div></div></div><div class="fg"><div class="iw"><div class="pfx" style="border-right:none;padding-right:0"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg></div><input type="text" placeholder="Kode referral (opsional)" id="rRef" name="ref" autocomplete="off" style="text-transform:uppercase" oninput="this.value=this.value.toUpperCase()"></div></div><button class="btn-sub" id="regBtn" type="submit">Daftar</button></form></div></div>
</body>
</html>
