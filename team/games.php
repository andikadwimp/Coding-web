<?php require_once 'layout.php'; adminHeader('Games & Providers');

// Auto-add columns
try{$db->exec("ALTER TABLE games ADD COLUMN featured TINYINT DEFAULT 0 AFTER status");}catch(Exception $e){}
try{$db->exec("ALTER TABLE games ADD COLUMN sort_order INT DEFAULT 0 AFTER featured");}catch(Exception $e){}
try{$db->exec("ALTER TABLE providers ADD COLUMN sort_order INT DEFAULT 0 AFTER status");}catch(Exception $e){}

$msg='';$msgOk=false;

// Sync NexusGGR
if(isset($_GET['sync'])){
    try{
        $db->exec("CREATE TABLE IF NOT EXISTS providers(code VARCHAR(50) PRIMARY KEY,name VARCHAR(100),logo VARCHAR(500) DEFAULT '',status INT DEFAULT 1,sort_order INT DEFAULT 0,game_count INT DEFAULT 0) ENGINE=InnoDB");
        $db->exec("CREATE TABLE IF NOT EXISTS games(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,provider_code VARCHAR(50),game_code VARCHAR(100),game_name VARCHAR(200),game_type VARCHAR(20) DEFAULT 'slot',banner VARCHAR(500) DEFAULT '',status INT DEFAULT 1,featured TINYINT DEFAULT 0,sort_order INT DEFAULT 0,UNIQUE KEY uq(provider_code,game_code)) ENGINE=InnoDB");
        $ch=curl_init(NEXUS_URL);
        curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['method'=>'provider_list','agent_code'=>NEXUS_AGENT,'agent_token'=>NEXUS_TOKEN]),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>60]);
        $res=json_decode(curl_exec($ch),true);curl_close($ch);
        if($res&&$res['status']==1){
            $tp=0;$tg=0;
            foreach($res['providers'] as $p){
                $db->prepare("INSERT INTO providers(code,name) VALUES(?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)")->execute([$p['code'],$p['name']]);$tp++;
                $ch2=curl_init(NEXUS_URL);curl_setopt_array($ch2,[CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>json_encode(['method'=>'game_list','agent_code'=>NEXUS_AGENT,'agent_token'=>NEXUS_TOKEN,'provider_code'=>$p['code']]),CURLOPT_HTTPHEADER=>['Content-Type: application/json'],CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>30]);
                $gr=json_decode(curl_exec($ch2),true);curl_close($ch2);
                if($gr&&$gr['status']==1&&!empty($gr['games'])){
                    $gc=0;foreach($gr['games'] as $g){
                        $nm=is_array($g['game_name']??'')?($g['game_name']['en']??array_values($g['game_name'])[0]??''):($g['game_name']??'');
                        try{$db->prepare("INSERT INTO games(provider_code,game_code,game_name,banner) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE game_name=VALUES(game_name),banner=VALUES(banner)")->execute([$p['code'],$g['game_code'],$nm,$g['banner']??'']);$gc++;}catch(Exception $e){}
                    }
                    $db->prepare("UPDATE providers SET game_count=? WHERE code=?")->execute([$gc,$p['code']]);$tg+=$gc;
                }usleep(100000);
            }
            $msg="Sync OK: $tp provider, $tg games.";$msgOk=true;
        }
    }catch(Exception $e){$msg="Error: ".$e->getMessage();}
}

// Update provider
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['act']??'')==='update_prov'){
    $db->prepare("UPDATE providers SET logo=?,status=? WHERE code=?")->execute([$_POST['logo']??'',intval($_POST['status']??1),$_POST['code']??'']);
    $msg='Provider updated!';$msgOk=true;
}

// ═══ REORDER PROVIDERS via drag-and-drop (AJAX, JSON response) ═══
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['act']??'')==='reorder_prov'){
    header('Content-Type: application/json');
    $codes=$_POST['codes']??[];
    if(!is_array($codes)){echo json_encode(['ok'=>false,'error'=>'invalid payload']);exit;}
    try{
        $db->beginTransaction();
        $stmt=$db->prepare("UPDATE providers SET sort_order=? WHERE code=?");
        foreach($codes as $i=>$code){
            $stmt->execute([intval($i)+1,$code]);
        }
        $db->commit();
        echo json_encode(['ok'=>true,'count'=>count($codes)]);
    }catch(Exception $e){
        try{$db->rollBack();}catch(Exception $ee){}
        echo json_encode(['ok'=>false,'error'=>$e->getMessage()]);
    }
    exit;
}

// Upload logo per provider
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['act']??'')==='upload_logo'&&!empty($_FILES['logo']['tmp_name'])){
    $code=$_POST['code']??'';
    $ext=strtolower(pathinfo($_FILES['logo']['name'],PATHINFO_EXTENSION));
    if(in_array($ext,['png','jpg','jpeg','webp','svg'])){
        $dir=__DIR__.'/../asset/uploads/provider/';
        if(!is_dir($dir))@mkdir($dir,0755,true);
        $fn='prov_'.strtolower(preg_replace('/[^a-z0-9]/i','',$code)).'_'.time().'.'.$ext;
        if(move_uploaded_file($_FILES['logo']['tmp_name'],$dir.$fn)){
            $logoPath='asset/uploads/provider/'.$fn;
            $db->prepare("UPDATE providers SET logo=? WHERE code=?")->execute([$logoPath,$code]);
            $msg='Logo berhasil diupload';$msgOk=true;
        }else{$msg='Upload gagal';$msgOk=false;}
    }else{$msg='Format tidak valid (png/jpg/webp/svg)';$msgOk=false;}
}

// Hapus logo provider
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['act']??'')==='remove_logo'){
    $db->prepare("UPDATE providers SET logo='' WHERE code=?")->execute([$_POST['code']??'']);
    $msg='Logo dihapus';$msgOk=true;
}

// Auto-match icons dari folder asset/uploads/provider/ by filename
if(isset($_GET['automatch'])){
    $dir=__DIR__.'/../asset/uploads/provider/';
    $files=is_dir($dir)?glob($dir.'*.{png,jpg,jpeg,webp,svg,PNG,JPG}',GLOB_BRACE):[];
    $provs=$db->query("SELECT code,name,logo FROM providers")->fetchAll();
    $matched=0;
    foreach($provs as $p){
        if(!empty($p['logo']))continue; // skip udah ada
        $needle=strtolower(preg_replace('/[^a-z0-9]/i','',$p['code']));
        $needleName=strtolower(preg_replace('/[^a-z0-9]/i','',$p['name']));
        foreach($files as $f){
            $base=strtolower(pathinfo($f,PATHINFO_FILENAME));
            $baseClean=preg_replace('/[^a-z0-9]/','',$base);
            if(strpos($baseClean,$needle)!==false||strpos($baseClean,$needleName)!==false||strpos($needle,$baseClean)!==false){
                $logoPath='asset/uploads/provider/'.basename($f);
                $db->prepare("UPDATE providers SET logo=? WHERE code=?")->execute([$logoPath,$p['code']]);
                $matched++;
                break;
            }
        }
    }
    $msg="Auto-match selesai. $matched provider mendapat logo.";$msgOk=true;
}

// Toggle featured game
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['act']??'')==='toggle_featured'){
    $id=intval($_POST['id']??0);
    $db->prepare("UPDATE games SET featured=1-featured WHERE id=?")->execute([$id]);
    header('Content-Type: application/json');echo json_encode(['ok'=>true]);exit;
}

// Update game sort
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['act']??'')==='update_game_sort'){
    $id=intval($_POST['id']??0);$sort=intval($_POST['sort']??0);
    $db->prepare("UPDATE games SET sort_order=? WHERE id=?")->execute([$sort,$id]);
    header('Content-Type: application/json');echo json_encode(['ok'=>true]);exit;
}

// Toggle game status (active/inactive — soft delete)
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['act']??'')==='toggle_game_status'){
    $id=intval($_POST['id']??0);
    $db->prepare("UPDATE games SET status=1-status WHERE id=?")->execute([$id]);
    $r=$db->prepare("SELECT status FROM games WHERE id=?");$r->execute([$id]);
    header('Content-Type: application/json');echo json_encode(['ok'=>true,'status'=>(int)$r->fetchColumn()]);exit;
}

// Delete game (HARD delete)
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['act']??'')==='delete_game'){
    $id=intval($_POST['id']??0);
    if($id>0){
        $db->prepare("DELETE FROM games WHERE id=?")->execute([$id]);
        // Refresh game count untuk provider
        $g=$db->prepare("SELECT provider_code FROM games WHERE id=?");$g->execute([$id]);
        try{$db->exec("UPDATE providers p SET game_count=(SELECT COUNT(*) FROM games g WHERE g.provider_code=p.code AND g.status=1)");}catch(Exception $e){}
    }
    header('Content-Type: application/json');echo json_encode(['ok'=>true]);exit;
}

// List games per provider (AJAX)
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['act']??'')==='list_games'){
    $code=$_POST['code']??'';
    $rows=[];
    try{
        $s=$db->prepare("SELECT id,game_code,game_name,banner,status,featured,sort_order FROM games WHERE provider_code=? ORDER BY featured DESC,sort_order ASC,game_name ASC LIMIT 500");
        $s->execute([$code]);$rows=$s->fetchAll();
    }catch(Exception $e){}
    header('Content-Type: application/json');echo json_encode(['ok'=>true,'games'=>$rows]);exit;
}

// Bulk update game (status/featured/sort) - dipake dari modal manager
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['act']??'')==='update_game'){
    $id=intval($_POST['id']??0);
    $fields=[];$vals=[];
    if(isset($_POST['featured'])){$fields[]='featured=?';$vals[]=intval($_POST['featured']);}
    if(isset($_POST['status'])){$fields[]='status=?';$vals[]=intval($_POST['status']);}
    if(isset($_POST['sort_order'])){$fields[]='sort_order=?';$vals[]=intval($_POST['sort_order']);}
    if($fields&&$id){$vals[]=$id;$db->prepare("UPDATE games SET ".implode(',',$fields)." WHERE id=?")->execute($vals);}
    header('Content-Type: application/json');echo json_encode(['ok'=>true]);exit;
}

$provs=[];try{$provs=$db->query("SELECT * FROM providers ORDER BY sort_order ASC,name")->fetchAll();}catch(Exception $e){}
$featured=[];try{$featured=$db->query("SELECT g.*,g.id as gid FROM games g WHERE g.featured=1 ORDER BY g.sort_order ASC,g.game_name LIMIT 100")->fetchAll();}catch(Exception $e){}
?>
<?php if($msg): ?><div class="msg <?=$msgOk?'msg-ok':'msg-err'?>"><?=htmlspecialchars($msg)?></div><?php endif; ?>

<div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap">
  <a class="btn btn-pri" href="?sync">↻ Sync NexusGGR</a>
  <a class="btn btn-sec" href="?automatch" onclick="return confirm('Auto-match icon dari folder asset/uploads/provider/ berdasarkan nama file?')">Auto-Match Logo</a>
</div>

<!-- PROVIDER ORDER -->
<div style="background:var(--bg2);border:1px solid var(--bd);border-radius:12px;padding:16px;margin-bottom:20px">
  <h3 style="font-size:.85rem;font-weight:700;margin-bottom:6px">Provider &amp; Logo</h3>
  <p style="font-size:.7rem;color:var(--t3);margin-bottom:6px">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13" style="display:inline-block;vertical-align:-2px;margin-right:3px"><polyline points="8 6 12 2 16 6"/><polyline points="8 18 12 22 16 18"/><line x1="12" y1="2" x2="12" y2="22"/></svg>
    <b>Tahan &amp; geser</b> icon di kiri untuk ubah urutan. Perubahan tersimpan otomatis.
  </p>
  <div id="reorderStatus" style="font-size:.65rem;color:var(--t3);margin-bottom:12px;min-height:16px"></div>

  <div id="provList">
  <?php foreach($provs as $p): ?>
  <div class="prov-row" data-code="<?=htmlspecialchars($p['code'])?>" style="margin-bottom:10px;padding:10px;background:var(--bg);border-radius:8px;display:flex;align-items:center;gap:10px;transition:all .2s">
    <!-- Drag handle -->
    <div class="drag-handle" style="cursor:grab;padding:10px 6px;color:var(--t3);flex-shrink:0;touch-action:none;user-select:none" title="Geser untuk ubah urutan">
      <svg viewBox="0 0 24 24" fill="currentColor" width="18" height="18"><circle cx="8" cy="6" r="1.5"/><circle cx="16" cy="6" r="1.5"/><circle cx="8" cy="12" r="1.5"/><circle cx="16" cy="12" r="1.5"/><circle cx="8" cy="18" r="1.5"/><circle cx="16" cy="18" r="1.5"/></svg>
    </div>

    <!-- Logo preview -->
    <div style="width:52px;height:52px;background:var(--s);border-radius:10px;display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;border:1px solid var(--bd)">
      <?php if(!empty($p['logo'])): ?>
        <img src="../<?=htmlspecialchars($p['logo'])?>?v=<?=time()?>" style="width:90%;height:90%;object-fit:contain">
      <?php else: ?>
        <span style="font-size:.55rem;font-weight:900;color:var(--t3);letter-spacing:1px"><?=htmlspecialchars(substr($p['code'],0,3))?></span>
      <?php endif; ?>
    </div>

    <!-- Info -->
    <div style="flex:1;min-width:0">
      <div style="font-size:.78rem;font-weight:700"><?=htmlspecialchars($p['name'])?></div>
      <div style="font-size:.62rem;color:var(--t3);margin-top:2px"><?=$p['game_count']??0?> games &middot; <code><?=$p['code']?></code></div>
    </div>

    <!-- Actions -->
    <div style="display:flex;flex-direction:column;gap:5px;flex-shrink:0">
      <!-- Upload logo -->
      <form method="POST" enctype="multipart/form-data" style="display:flex;gap:4px;align-items:center">
        <input type="hidden" name="act" value="upload_logo">
        <input type="hidden" name="code" value="<?=$p['code']?>">
        <label style="padding:5px 10px;background:var(--pri);color:#fff;border-radius:6px;font-size:.62rem;font-weight:700;cursor:pointer;display:inline-block;margin:0">
          Upload Logo
          <input type="file" name="logo" accept="image/*" style="display:none" onchange="this.form.submit()">
        </label>
        <?php if(!empty($p['logo'])): ?>
          <button type="submit" name="act" value="remove_logo" style="padding:5px 8px;background:var(--red);color:#fff;border:none;border-radius:6px;font-size:.62rem;font-weight:700;cursor:pointer" title="Hapus logo" onclick="return confirm('Hapus logo?')">×</button>
        <?php endif; ?>
      </form>

      <!-- Kelola Games + Status -->
      <div style="display:flex;gap:4px;align-items:center">
        <button type="button" class="btn btn-pri" style="padding:5px 10px;font-size:.62rem" onclick="openGM('<?=htmlspecialchars($p['code'],ENT_QUOTES)?>','<?=htmlspecialchars($p['name'],ENT_QUOTES)?>')">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" width="11" height="11" style="vertical-align:-1px"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 010 2.83 2 2 0 01-2.83 0l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 01-4 0v-.09A1.65 1.65 0 009 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 01-2.83-2.83l.06-.06A1.65 1.65 0 004.68 15a1.65 1.65 0 00-1.51-1H3a2 2 0 010-4h.09A1.65 1.65 0 004.6 9a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 012.83-2.83l.06.06A1.65 1.65 0 009 4.68a1.65 1.65 0 001-1.51V3a2 2 0 014 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 012.83 2.83l-.06.06A1.65 1.65 0 0019.4 9a1.65 1.65 0 001.51 1H21a2 2 0 010 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
          Kelola Games
        </button>
        <form method="POST" style="display:flex;gap:4px;align-items:center;margin:0">
          <input type="hidden" name="act" value="update_prov">
          <input type="hidden" name="code" value="<?=$p['code']?>">
          <input type="hidden" name="logo" value="<?=htmlspecialchars($p['logo']??'')?>">
          <select name="status" onchange="this.form.submit()" style="padding:5px 8px;background:var(--s);border:1px solid var(--bd);border-radius:5px;color:var(--t);font-size:.62rem">
            <option value="1" <?=$p['status']==1?'selected':''?>>Aktif</option>
            <option value="0" <?=$p['status']==0?'selected':''?>>Maint.</option>
          </select>
        </form>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
</div>

<!-- ═══════════════════════════════════════════════════════════════
     GAME MANAGER MODAL — kelola game per provider
     (toggle featured/status, sort order, delete)
     ═══════════════════════════════════════════════════════════════ -->
<style>
.gm-overlay{position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(4px);z-index:9000;display:none;align-items:flex-start;justify-content:center;padding:5vh 16px;animation:gmFadeIn .2s ease-out both}
.gm-overlay.show{display:flex}
@keyframes gmFadeIn{from{opacity:0}to{opacity:1}}
@keyframes gmSlideIn{from{opacity:0;transform:translateY(20px) scale(.96)}to{opacity:1;transform:translateY(0) scale(1)}}
.gm-modal{background:#fff;border-radius:14px;width:100%;max-width:740px;max-height:88vh;display:flex;flex-direction:column;box-shadow:0 20px 60px rgba(0,0,0,.25);animation:gmSlideIn .3s cubic-bezier(.16,1,.3,1) both}
.gm-hdr{display:flex;align-items:center;justify-content:space-between;padding:16px 18px;border-bottom:1px solid var(--bd)}
.gm-hdr h3{font-size:.95rem;font-weight:800;letter-spacing:-.015em;color:var(--t)}
.gm-hdr h3 small{display:block;font-size:.65rem;font-weight:600;color:var(--t3);margin-top:2px;letter-spacing:.3px}
.gm-close{width:32px;height:32px;border:none;background:var(--bg3);border-radius:8px;color:var(--t2);cursor:pointer;display:flex;align-items:center;justify-content:center;transition:background .15s}
.gm-close:hover{background:#fee;color:var(--red)}
.gm-toolbar{display:flex;gap:8px;align-items:center;padding:12px 18px;background:var(--bg);border-bottom:1px solid var(--bd);flex-wrap:wrap}
.gm-search{flex:1;min-width:140px;padding:7px 11px;border:1px solid var(--bd2);border-radius:7px;font-size:.78rem;font-family:inherit;outline:none;background:#fff;transition:border-color .15s}
.gm-search:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(var(--accent-rgb),.12)}
.gm-filter{display:flex;gap:4px;background:var(--bg3);border-radius:7px;padding:2px}
.gm-filter button{padding:6px 11px;border:none;background:transparent;border-radius:5px;font-size:.7rem;font-weight:600;color:var(--t3);cursor:pointer;font-family:inherit;transition:all .15s}
.gm-filter button.on{background:#fff;color:var(--t);box-shadow:0 1px 2px rgba(0,0,0,.04)}
.gm-list{flex:1;overflow-y:auto;padding:12px 18px}
.gm-row{display:grid;grid-template-columns:48px 1fr auto auto auto auto;gap:10px;align-items:center;padding:8px;background:#fff;border:1px solid var(--bd);border-radius:9px;margin-bottom:6px;transition:border-color .15s,background .15s}
.gm-row:hover{border-color:var(--accent);background:var(--accent-l)}
.gm-row.inactive{opacity:.55}
.gm-row .gm-banner{width:48px;height:48px;border-radius:7px;overflow:hidden;background:var(--bg3);position:relative;flex-shrink:0}
.gm-row .gm-banner img{width:100%;height:100%;object-fit:cover}
.gm-row .gm-info{min-width:0}
.gm-row .gm-name{font-size:.78rem;font-weight:700;color:var(--t);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;letter-spacing:-.005em}
.gm-row .gm-code{font-size:.62rem;color:var(--t3);font-family:'JetBrains Mono',monospace;margin-top:1px}
.gm-row .gm-sort{width:54px;padding:5px 8px;border:1px solid var(--bd2);border-radius:6px;font-size:.7rem;text-align:center;font-family:'JetBrains Mono',monospace;outline:none;background:#fff}
.gm-row .gm-sort:focus{border-color:var(--accent)}
.gm-btn{padding:5px 9px;border:1px solid var(--bd2);background:#fff;color:var(--t2);border-radius:6px;font-size:.62rem;font-weight:700;cursor:pointer;transition:all .15s;font-family:inherit;display:inline-flex;align-items:center;gap:3px;letter-spacing:.2px}
.gm-btn:hover{border-color:var(--accent);color:var(--accent)}
.gm-btn.on{background:linear-gradient(135deg,#fbbf24,#f59e0b);color:#fff;border-color:#f59e0b}
.gm-btn.on-status{background:#dcfce7;color:#15803d;border-color:#86efac}
.gm-btn.off-status{background:#fee2e2;color:#991b1b;border-color:#fca5a5}
.gm-btn.del{color:var(--red);border-color:#fecaca;background:#fff}
.gm-btn.del:hover{background:#fee;border-color:var(--red)}
.gm-empty{text-align:center;padding:40px 20px;color:var(--t3);font-size:.82rem}
.gm-stats{padding:10px 18px;background:var(--bg);border-top:1px solid var(--bd);font-size:.7rem;color:var(--t3);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px}
.gm-stats b{color:var(--t);font-weight:700;font-family:'JetBrains Mono',monospace}
@media(max-width:520px){
  .gm-row{grid-template-columns:48px 1fr;gap:8px}
  .gm-row > :nth-child(n+3){grid-column:1/-1;justify-self:start}
  .gm-row .gm-banner{grid-row:span 2}
}
</style>

<div class="gm-overlay" id="gmOverlay" onclick="if(event.target===this)closeGM()">
  <div class="gm-modal" role="dialog" aria-modal="true">
    <div class="gm-hdr">
      <h3 id="gmTitle">Kelola Games<small id="gmSub"></small></h3>
      <button class="gm-close" onclick="closeGM()" aria-label="Close">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="gm-toolbar">
      <input class="gm-search" id="gmSearch" placeholder="Cari game…" oninput="renderGM()">
      <div class="gm-filter">
        <button data-f="all" class="on" onclick="gmFilter(this,'all')">Semua</button>
        <button data-f="featured" onclick="gmFilter(this,'featured')">⭐ Populer</button>
        <button data-f="active" onclick="gmFilter(this,'active')">Aktif</button>
        <button data-f="inactive" onclick="gmFilter(this,'inactive')">Nonaktif</button>
      </div>
    </div>
    <div class="gm-list" id="gmList"><div class="gm-empty">Memuat…</div></div>
    <div class="gm-stats">
      <span><b id="gmCount">0</b> game · <b id="gmFeat">0</b> populer · <b id="gmActive">0</b> aktif</span>
      <span style="font-size:.62rem">Sort lebih kecil = tampil duluan · Klik ⭐ untuk masukkan ke <b>Populer</b> di beranda</span>
    </div>
  </div>
</div>

<script>
var GM={code:'',name:'',games:[],filter:'all'};
function openGM(code,name){
  GM.code=code;GM.name=name;
  document.getElementById('gmTitle').firstChild.textContent='Kelola Games · '+name;
  document.getElementById('gmSub').textContent=code.toUpperCase();
  document.getElementById('gmOverlay').classList.add('show');
  document.body.style.overflow='hidden';
  loadGM();
}
function closeGM(){
  document.getElementById('gmOverlay').classList.remove('show');
  document.body.style.overflow='';
}
function loadGM(){
  document.getElementById('gmList').innerHTML='<div class="gm-empty">Memuat…</div>';
  var fd=new FormData();fd.append('act','list_games');fd.append('code',GM.code);
  fetch('games.php',{method:'POST',body:fd}).then(r=>r.json()).then(function(d){
    if(!d.ok){document.getElementById('gmList').innerHTML='<div class="gm-empty">Gagal load</div>';return;}
    GM.games=d.games||[];renderGM();
  }).catch(function(){document.getElementById('gmList').innerHTML='<div class="gm-empty">Error koneksi</div>';});
}
function gmFilter(btn,f){
  document.querySelectorAll('.gm-filter button').forEach(b=>b.classList.remove('on'));
  btn.classList.add('on');GM.filter=f;renderGM();
}
function renderGM(){
  var q=(document.getElementById('gmSearch').value||'').toLowerCase().trim();
  var list=GM.games.slice();
  if(q)list=list.filter(g=>(g.game_name||'').toLowerCase().includes(q)||(g.game_code||'').toLowerCase().includes(q));
  if(GM.filter==='featured')list=list.filter(g=>+g.featured===1);
  else if(GM.filter==='active')list=list.filter(g=>+g.status===1);
  else if(GM.filter==='inactive')list=list.filter(g=>+g.status===0);
  var feat=GM.games.filter(g=>+g.featured===1).length;
  var active=GM.games.filter(g=>+g.status===1).length;
  document.getElementById('gmCount').textContent=GM.games.length;
  document.getElementById('gmFeat').textContent=feat;
  document.getElementById('gmActive').textContent=active;
  if(!list.length){document.getElementById('gmList').innerHTML='<div class="gm-empty">Tidak ada game</div>';return;}
  var h='';
  list.forEach(function(g){
    var inactive=+g.status===0;
    var feat=+g.featured===1;
    h+='<div class="gm-row'+(inactive?' inactive':'')+'" data-id="'+g.id+'">'+
      '<div class="gm-banner">'+(g.banner?'<img src="'+(''+g.banner).replace(/"/g,'&quot;')+'" loading="lazy" onerror="this.style.display=\'none\'">':'')+'</div>'+
      '<div class="gm-info"><div class="gm-name">'+escapeHtml(g.game_name||g.game_code)+'</div><div class="gm-code">'+escapeHtml(g.game_code)+'</div></div>'+
      '<input class="gm-sort" type="number" value="'+(g.sort_order||0)+'" min="0" onchange="gmSort('+g.id+',this.value)" title="Urutan (kecil = duluan)">'+
      '<button class="gm-btn'+(feat?' on':'')+'" onclick="gmFeat('+g.id+',this)" title="Tampil di Populer di beranda">'+(feat?'⭐ Populer':'☆ Pop')+'</button>'+
      '<button class="gm-btn '+(inactive?'off-status':'on-status')+'" onclick="gmStat('+g.id+',this)">'+(inactive?'OFF':'ON')+'</button>'+
      '<button class="gm-btn del" onclick="gmDel('+g.id+',this)" title="Hapus permanen">×</button>'+
    '</div>';
  });
  document.getElementById('gmList').innerHTML=h;
}
function escapeHtml(s){return (''+s).replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}
function gmFeat(id,btn){
  var fd=new FormData();fd.append('act','toggle_featured');fd.append('id',id);
  fetch('games.php',{method:'POST',body:fd}).then(r=>r.json()).then(function(d){
    if(d.ok){
      var g=GM.games.find(x=>+x.id===+id);if(g){g.featured=+g.featured===1?0:1;renderGM();}
    }
  });
}
function gmStat(id,btn){
  var fd=new FormData();fd.append('act','toggle_game_status');fd.append('id',id);
  fetch('games.php',{method:'POST',body:fd}).then(r=>r.json()).then(function(d){
    if(d.ok){var g=GM.games.find(x=>+x.id===+id);if(g){g.status=d.status;renderGM();}}
  });
}
function gmSort(id,val){
  var fd=new FormData();fd.append('act','update_game_sort');fd.append('id',id);fd.append('sort',parseInt(val)||0);
  fetch('games.php',{method:'POST',body:fd}).then(r=>r.json()).then(function(d){
    if(d.ok){var g=GM.games.find(x=>+x.id===+id);if(g)g.sort_order=parseInt(val)||0;}
  });
}
function gmDel(id,btn){
  if(!confirm('Hapus PERMANEN game ini? (Tidak bisa dibatalkan — sync ulang Nexus untuk balikin)'))return;
  var fd=new FormData();fd.append('act','delete_game');fd.append('id',id);
  fetch('games.php',{method:'POST',body:fd}).then(r=>r.json()).then(function(d){
    if(d.ok){GM.games=GM.games.filter(x=>+x.id!==+id);renderGM();}
  });
}
// ESC closes modal
document.addEventListener('keydown',function(e){if(e.key==='Escape'&&document.getElementById('gmOverlay').classList.contains('show'))closeGM();});
</script>

<!-- SortableJS dari CDN untuk drag-and-drop -->
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
<script>
(function(){
  var list=document.getElementById('provList');
  if(!list||typeof Sortable==='undefined')return;

  var statusEl=document.getElementById('reorderStatus');
  var saveTimer=null;

  function showStatus(txt,color){
    statusEl.innerHTML=txt;
    statusEl.style.color=color||'var(--t3)';
  }

  function saveOrder(){
    var codes=Array.from(list.querySelectorAll('.prov-row')).map(function(r){return r.getAttribute('data-code')});
    showStatus('<span style="color:#f59e0b">⟳ Menyimpan urutan...</span>');
    var fd=new FormData();
    fd.append('act','reorder_prov');
    codes.forEach(function(c){fd.append('codes[]',c)});
    fetch(location.pathname,{method:'POST',body:fd,credentials:'same-origin'})
      .then(function(r){return r.json()}).then(function(d){
        if(d.ok){
          showStatus('<span style="color:#10b981">✓ Urutan tersimpan ('+d.count+' provider)</span>');
          setTimeout(function(){showStatus('')},2500);
        }else{
          showStatus('<span style="color:#ef4444">❌ Gagal simpan: '+(d.error||'unknown')+'</span>');
        }
      }).catch(function(e){
        showStatus('<span style="color:#ef4444">❌ Network error</span>');
      });
  }

  new Sortable(list,{
    handle:'.drag-handle',
    animation:180,
    ghostClass:'sortable-ghost',
    chosenClass:'sortable-chosen',
    dragClass:'sortable-drag',
    forceFallback:true,  // pake fallback biar work di mobile/touch
    fallbackTolerance:4,
    onEnd:function(){
      // Debounce 300ms kalau user drag2 cepet
      clearTimeout(saveTimer);
      saveTimer=setTimeout(saveOrder,300);
    }
  });
})();
</script>
<style>
.sortable-ghost{opacity:.4;background:var(--pri-l)!important}
.sortable-chosen{background:var(--pri-l)!important;box-shadow:0 4px 16px rgba(37,99,235,.25)!important;transform:scale(1.01)}
.sortable-drag{opacity:.95;cursor:grabbing!important;box-shadow:0 10px 30px rgba(30,41,59,.3)!important}
.drag-handle:active{cursor:grabbing!important;color:var(--pri)!important}
.prov-row:hover .drag-handle{color:var(--pri)}
</style>

<!-- FEATURED GAMES -->
<div style="background:var(--bg2);border:1px solid var(--bd);border-radius:12px;padding:16px;margin-bottom:20px">
  <h3 style="font-size:.85rem;font-weight:700;margin-bottom:4px">Game Teratas (Featured)</h3>
  <p style="font-size:.68rem;color:var(--t3);margin-bottom:14px">Game ini muncul di bagian "Populer" homepage. Cari game lalu tandai sebagai featured.</p>

  <!-- Search game -->
  <div style="display:flex;gap:8px;margin-bottom:14px">
    <input id="gameSearch" placeholder="Cari nama game..." style="flex:1;padding:9px 12px;background:var(--bg);border:1px solid var(--bd);border-radius:8px;color:var(--t);font-size:.78rem" oninput="searchGames()">
  </div>
  <div id="searchResults" style="margin-bottom:16px"></div>

  <h4 style="font-size:.75rem;font-weight:700;color:var(--pri);margin-bottom:10px">Game Teratas Sekarang (<?=count($featured)?>)</h4>
  <div id="featuredList">
  <?php foreach($featured as $g): ?>
  <div class="item-row" style="margin-bottom:6px" id="frow_<?=$g['gid']?>">
    <?php if($g['banner']): ?><img src="<?=htmlspecialchars($g['banner'])?>" style="width:36px;height:36px;border-radius:6px;object-fit:cover;flex-shrink:0"><?php endif; ?>
    <div class="ir-info">
      <div class="ir-title" style="font-size:.72rem"><?=htmlspecialchars($g['game_name'])?></div>
      <div class="ir-sub"><?=htmlspecialchars($g['provider_code'])?></div>
    </div>
    <div style="display:flex;align-items:center;gap:6px;flex-shrink:0">
      <input type="number" value="<?=$g['sort_order']??0?>" style="width:46px;padding:4px;background:var(--bg);border:1px solid var(--bd);border-radius:6px;color:var(--t);font-size:.65rem;text-align:center" onchange="updateSort(<?=$g['gid']?>,this.value)">
      <button class="btn btn-red btn-sm" onclick="toggleFeatured(<?=$g['gid']?>,this)">Hapus</button>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
</div>

<script>
var searchTimer=null;
function searchGames(){
  clearTimeout(searchTimer);
  var q=document.getElementById('gameSearch').value.trim();
  if(q.length<2){document.getElementById('searchResults').innerHTML='';return;}
  searchTimer=setTimeout(function(){
    fetch('../api/admin.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',
      body:JSON.stringify({action:'search_games',q:q})})
    .then(r=>r.json()).then(function(d){
      if(!d.ok||!d.games.length){document.getElementById('searchResults').innerHTML='<div style="font-size:.72rem;color:var(--t3);padding:8px 0">Tidak ditemukan</div>';return;}
      var h='<div style="display:flex;flex-direction:column;gap:5px">';
      d.games.forEach(function(g){
        h+='<div style="display:flex;align-items:center;gap:10px;padding:8px 10px;background:var(--s);border-radius:8px">';
        if(g.banner)h+='<img src="'+g.banner+'" style="width:32px;height:32px;border-radius:4px;object-fit:cover;flex-shrink:0">';
        h+='<div style="flex:1;min-width:0"><div style="font-size:.72rem;font-weight:600">'+g.game_name+'</div>';
        h+='<div style="font-size:.6rem;color:var(--t3)">'+g.provider_code+'</div></div>';
        if(g.featured){
          h+='<span style="font-size:.6rem;color:var(--green);font-weight:700">✓ Featured</span>';
        }else{
          h+='<button class="btn btn-sec btn-sm" onclick="addFeatured('+g.id+',this)">+ Tambah</button>';
        }
        h+='</div>';
      });
      h+='</div>';
      document.getElementById('searchResults').innerHTML=h;
    });
  },400);
}

function addFeatured(id,btn){
  btn.disabled=true;btn.textContent='...';
  var fd=new FormData();fd.append('act','toggle_featured');fd.append('id',id);
  fetch('games.php',{method:'POST',credentials:'same-origin',body:fd})
  .then(r=>r.json()).then(function(){btn.textContent='✓ Featured';btn.style.color='var(--green)';location.reload();});
}

function toggleFeatured(id,btn){
  btn.disabled=true;
  var fd=new FormData();fd.append('act','toggle_featured');fd.append('id',id);
  fetch('games.php',{method:'POST',credentials:'same-origin',body:fd})
  .then(r=>r.json()).then(function(){
    var row=document.getElementById('frow_'+id);if(row)row.remove();
  });
}

function updateSort(id,val){
  var fd=new FormData();fd.append('act','update_game_sort');fd.append('id',id);fd.append('sort',val);
  fetch('games.php',{method:'POST',credentials:'same-origin',body:fd});
}

function uploadLogo(inputEl,code){
  var file=inputEl.files[0];if(!file)return;
  var fd=new FormData();fd.append('file',file);fd.append('type','provider');
  var span=inputEl.previousElementSibling;if(span)span.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>';
  fetch('../api/admin.php?action=upload',{method:'POST',credentials:'same-origin',body:fd})
  .then(r=>r.json()).then(function(d){
    if(span)span.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M22 19a2 2 0 01-2 2H4a2 2 0 01-2-2V5a2 2 0 012-2h5l2 3h9a2 2 0 012 2z"/></svg>';
    if(d.ok){var inp=document.getElementById('logo_'+code);if(inp)inp.value=d.url;alert('Upload OK: '+d.url);}
    else alert('Gagal: '+(d.error||'error'));
  });
}
</script>
<?php adminFooter(); ?>
