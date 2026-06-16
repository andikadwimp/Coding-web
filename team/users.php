<?php
header('Content-Type: text/html; charset=UTF-8');
require_once 'layout.php'; adminHeader('Users');

// Handle POST actions
$flash = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';
    if ($act === 'update') {
        $id = intval($_POST['id'] ?? 0);
        $db->prepare("UPDATE users SET balance=?, role=?, vip_level=? WHERE id=?")
           ->execute([intval($_POST['balance'] ?? 0), $_POST['role'] ?? 'user', intval($_POST['vip'] ?? 0), $id]);
        $flash = 'User diperbarui.';
    } elseif ($act === 'inject') {
        $id = intval($_POST['id'] ?? 0); $amt = intval($_POST['amount'] ?? 0);
        if ($id && $amt) { logTx($db, $id, 'inject', $amt, $_POST['note'] ?? 'Admin inject'); $flash = 'Saldo di-inject.'; }
    } elseif ($act === 'toggle_status') {
        $id = intval($_POST['id'] ?? 0);
        $db->prepare("UPDATE users SET status = IF(status='active','banned','active') WHERE id=?")->execute([$id]);
        $flash = 'Status user diubah.';
    }
}

// Filters
$q = trim($_GET['q'] ?? '');
$f = $_GET['f'] ?? 'all';
$where = []; $params = [];
if ($q !== '') { $where[] = "(username LIKE ? OR phone LIKE ? OR display_id LIKE ?)"; $params[] = "%$q%"; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($f === 'admin') $where[] = "role='admin'";
elseif ($f === 'vip') $where[] = "vip_level > 0";
elseif ($f === 'banned') $where[] = "status='banned'";
elseif ($f === 'active_dep') $where[] = "total_deposit > 0";
$wh = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$users = [];
try {
    $s = $db->prepare("SELECT id,username,phone,balance,role,vip_level,display_id,total_deposit,status,created_at,last_login FROM users $wh ORDER BY id DESC LIMIT 200");
    $s->execute($params); $users = $s->fetchAll();
} catch (Exception $e) {
    try { $s = $db->prepare("SELECT id,username,phone,balance,role,created_at FROM users $wh ORDER BY id DESC LIMIT 200"); $s->execute($params); $users = $s->fetchAll(); } catch (Exception $e2) {}
}

// Stats global
$statsTotal = 0; $statsActive = 0; $statsBalance = 0; $statsToday = 0;
try {
    $statsTotal = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $statsActive = (int)$db->query("SELECT COUNT(*) FROM users WHERE total_deposit > 0")->fetchColumn();
    $statsBalance = (int)$db->query("SELECT COALESCE(SUM(balance),0) FROM users WHERE role='user'")->fetchColumn();
    $statsToday = (int)$db->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()")->fetchColumn();
} catch (Exception $e) {}

function fmtK($n){if($n>=1e9)return number_format($n/1e9,1).'B';if($n>=1e6)return number_format($n/1e6,1).'M';if($n>=1e3)return number_format($n/1e3,0).'K';return number_format($n);}
?>

<style>
.u-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px}
.u-stats .stat-card{padding:16px;position:relative}
.u-stats .stat-card .ic{position:absolute;top:14px;right:14px;width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center}
.u-stats .stat-card .ic svg{width:17px;height:17px;position:static;opacity:1;color:#fff}
.u-stats .stat-card.s1 .ic{background:var(--grad-pri);box-shadow:0 4px 12px rgba(99,102,241,.3)}
.u-stats .stat-card.s2 .ic{background:var(--grad-success);box-shadow:0 4px 12px rgba(34,197,94,.3)}
.u-stats .stat-card.s3 .ic{background:var(--grad-sec);box-shadow:0 4px 12px rgba(34,211,238,.3)}
.u-stats .stat-card.s4 .ic{background:var(--grad-warn);box-shadow:0 4px 12px rgba(251,191,36,.3)}

.u-toolbar{display:flex;gap:10px;margin-bottom:14px;flex-wrap:wrap;align-items:center}
.u-search{flex:1;min-width:240px;position:relative}
.u-search input{width:100%;padding:11px 14px 11px 40px;background:var(--bg2);border:1px solid var(--bd);border-radius:11px;color:var(--t);font-size:.78rem;font-family:inherit;outline:none;transition:all .15s}
.u-search input:focus{border-color:var(--pri);background:var(--s);box-shadow:0 0 0 3px rgba(99,102,241,.15)}
.u-search svg{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--t3);pointer-events:none}

.u-chips{display:flex;gap:6px;flex-wrap:wrap;margin-bottom:18px}
.u-chips a{padding:7px 14px;background:var(--bg2);border:1px solid var(--bd);border-radius:20px;font-size:.65rem;font-weight:700;color:var(--t3);text-decoration:none;letter-spacing:.4px;transition:all .15s}
.u-chips a:hover{color:var(--t);border-color:var(--bd2)}
.u-chips a.on{background:var(--grad-pri);border-color:transparent;color:#fff;box-shadow:0 4px 12px rgba(99,102,241,.3)}

.u-list{display:flex;flex-direction:column;gap:10px}
.u-card{background:var(--bg2);border:1px solid var(--bd);border-radius:14px;padding:14px;cursor:pointer;transition:all .2s;position:relative;overflow:hidden}
.u-card::before{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--pri);opacity:0;transition:opacity .2s}
.u-card:hover{border-color:var(--bd2);transform:translateY(-1px);box-shadow:var(--shadow-sm)}
.u-card.on{border-color:var(--pri);background:var(--s);box-shadow:var(--shadow-glow)}
.u-card.on::before{opacity:1}
.u-card.admin{border-color:rgba(248,113,113,.3)}
.u-card.banned{opacity:.55}
.uc-row1{display:flex;align-items:center;gap:12px}
.uc-avatar{width:44px;height:44px;border-radius:12px;background:var(--grad-pri);display:flex;align-items:center;justify-content:center;font-weight:800;color:#fff;font-size:.95rem;flex-shrink:0;text-transform:uppercase;box-shadow:0 4px 10px rgba(99,102,241,.25)}
.u-card.admin .uc-avatar{background:var(--grad-danger);box-shadow:0 4px 10px rgba(248,113,113,.25)}
.uc-info{flex:1;min-width:0}
.uc-name{font-size:.86rem;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;color:var(--t);letter-spacing:-.01em}
.uc-meta{font-size:.62rem;color:var(--t3);margin-top:3px;font-family:'JetBrains Mono',monospace;letter-spacing:.3px}
.uc-bal{text-align:right;flex-shrink:0}
.uc-bal .v{font-family:'JetBrains Mono',monospace;font-weight:700;font-size:1rem;color:var(--t);white-space:nowrap;letter-spacing:-.02em}
.uc-bal .l{font-size:.55rem;color:var(--t3);text-transform:uppercase;letter-spacing:.7px;font-weight:700;margin-top:2px}
.uc-tags{display:flex;gap:5px;margin-top:10px;flex-wrap:wrap}
.uc-tag{font-size:.55rem;font-weight:700;padding:3px 9px;border-radius:7px;text-transform:uppercase;letter-spacing:.5px}
.uc-tag.admin{background:rgba(248,113,113,.15);color:var(--red)}
.uc-tag.vip{background:linear-gradient(135deg,rgba(251,191,36,.18),rgba(245,158,11,.18));color:var(--orange);border:1px solid rgba(251,191,36,.3)}
.uc-tag.deposited{background:rgba(74,222,128,.12);color:var(--green)}
.uc-tag.banned{background:rgba(248,113,113,.12);color:var(--red)}
.uc-tag.new{background:rgba(34,211,238,.12);color:var(--sec)}

.u-card.on .uc-panel{display:block}
.uc-panel{display:none;margin-top:14px;padding-top:14px;border-top:1px solid var(--bd)}
.uc-fields{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:12px}
.uc-fields label{display:block;font-size:.58rem;color:var(--t3);text-transform:uppercase;letter-spacing:.5px;font-weight:700;margin-bottom:4px}
.uc-fields input,.uc-fields select{width:100%;padding:9px 11px;background:var(--bg);border:1px solid var(--bd);border-radius:8px;color:var(--t);font-size:.74rem;font-family:inherit;outline:none;transition:all .15s}
.uc-fields input:focus,.uc-fields select:focus{border-color:var(--pri);box-shadow:0 0 0 2px rgba(99,102,241,.15)}
.uc-actions{display:flex;gap:7px;flex-wrap:wrap}
.uc-actions .btn{flex:1;min-width:0;padding:9px 12px;font-size:.7rem;border:none;border-radius:9px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;font-family:inherit;transition:all .15s}
.uc-actions .btn svg{width:13px;height:13px}
.btn-sv{background:var(--grad-pri);color:#fff;box-shadow:0 3px 10px rgba(99,102,241,.25)}
.btn-sv:hover{box-shadow:0 5px 16px rgba(99,102,241,.4);transform:translateY(-1px)}
.btn-inj{background:rgba(251,191,36,.12);color:var(--orange);border:1px solid rgba(251,191,36,.3)!important}
.btn-inj:hover{background:rgba(251,191,36,.2)}
.btn-bn{background:rgba(248,113,113,.12);color:var(--red);border:1px solid rgba(248,113,113,.3)!important}
.btn-bn:hover{background:rgba(248,113,113,.2)}
.btn-dt{background:rgba(34,211,238,.1);color:var(--sec);border:1px solid rgba(34,211,238,.3)!important;text-decoration:none}
.btn-dt:hover{background:rgba(34,211,238,.2)}

.inj-modal,.dt-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:200;align-items:center;justify-content:center;padding:16px}
.inj-modal.on,.dt-modal.on{display:flex;animation:fadeIn .2s ease}
.dt-modal{align-items:flex-end;padding:0}
@media(min-width:768px){.dt-modal{align-items:center;padding:16px}}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
.inj-box{background:var(--bg2);border:1px solid var(--bd2);border-radius:18px;padding:24px;width:100%;max-width:420px;box-shadow:var(--shadow-lg);animation:slideUp .25s cubic-bezier(.34,1.56,.64,1)}
@keyframes slideUp{from{transform:translateY(20px);opacity:0}to{transform:translateY(0);opacity:1}}
.inj-box h3{font-size:.95rem;font-weight:800;margin-bottom:6px;color:var(--t)}
.inj-box p{font-size:.7rem;color:var(--t3);margin-bottom:18px}
.inj-box .fg{margin-bottom:14px}
.inj-box label{display:block;font-size:.6rem;color:var(--t3);text-transform:uppercase;letter-spacing:.6px;font-weight:700;margin-bottom:6px}
.inj-box input{width:100%;padding:11px 14px;background:var(--bg);border:1px solid var(--bd);border-radius:10px;color:var(--t);font-size:.82rem;font-family:inherit;outline:none}
.inj-box input:focus{border-color:var(--pri);box-shadow:0 0 0 3px rgba(99,102,241,.15)}
.inj-actions{display:flex;gap:10px;margin-top:8px}
.inj-actions button{flex:1;padding:11px;border:none;border-radius:10px;font-weight:700;cursor:pointer;font-family:inherit;font-size:.78rem;transition:all .15s}

.dt-box{background:var(--bg2);border:1px solid var(--bd2);border-top-left-radius:24px;border-top-right-radius:24px;width:100%;max-width:680px;max-height:90vh;display:flex;flex-direction:column;box-shadow:var(--shadow-lg);animation:slideTop .3s cubic-bezier(.34,1.56,.64,1)}
@keyframes slideTop{from{transform:translateY(100%)}to{transform:translateY(0)}}
@media(min-width:768px){.dt-box{border-radius:20px;max-height:85vh}}
.dt-h{padding:18px 22px;border-bottom:1px solid var(--bd);display:flex;align-items:center;gap:14px}
.dt-h .av{width:48px;height:48px;border-radius:12px;background:var(--grad-pri);display:flex;align-items:center;justify-content:center;font-weight:800;color:#fff;font-size:1rem;flex-shrink:0;box-shadow:0 4px 12px rgba(99,102,241,.3)}
.dt-h .ti{flex:1;min-width:0}
.dt-h .ti h3{font-size:1rem;font-weight:800;color:var(--t)}
.dt-h .ti p{font-size:.66rem;color:var(--t3);font-family:'JetBrains Mono',monospace;margin-top:3px}
.dt-h .x{width:34px;height:34px;background:rgba(255,255,255,.04);border:1px solid var(--bd);border-radius:10px;color:var(--t2);cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .15s}
.dt-h .x:hover{background:rgba(248,113,113,.1);color:var(--red)}
.dt-body{overflow-y:auto;flex:1;padding:18px 22px}
.dt-stat{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:18px}
.dt-stat .it{background:var(--bg);border:1px solid var(--bd);border-radius:10px;padding:11px 12px;text-align:center}
.dt-stat .it .v{font-family:'JetBrains Mono',monospace;font-weight:700;font-size:.92rem;color:var(--t)}
.dt-stat .it .l{font-size:.55rem;color:var(--t3);text-transform:uppercase;letter-spacing:.5px;font-weight:700;margin-top:3px}
.dt-stat .it.green .v{color:var(--green)}
.dt-stat .it.cyan .v{color:var(--sec)}
.dt-stat .it.orange .v{color:var(--orange)}
.dt-tabs{display:flex;gap:4px;margin-bottom:14px;background:var(--bg);padding:4px;border-radius:11px;border:1px solid var(--bd)}
.dt-tab{flex:1;padding:8px 12px;background:transparent;border:none;color:var(--t3);font-size:.72rem;font-weight:700;cursor:pointer;border-radius:8px;font-family:inherit;transition:all .15s}
.dt-tab:hover{color:var(--t)}
.dt-tab.on{background:var(--grad-pri);color:#fff;box-shadow:0 3px 10px rgba(99,102,241,.3)}
.dt-list{display:flex;flex-direction:column;gap:6px}
.dt-row{padding:11px 14px;background:var(--bg);border:1px solid var(--bd);border-radius:10px;display:flex;align-items:center;gap:12px;transition:all .15s}
.dt-row:hover{border-color:var(--bd2);background:var(--s)}
.dt-row .icw{width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.dt-row .icw svg{width:16px;height:16px}
.dt-row .ti{flex:1;min-width:0}
.dt-row .ti .t1{font-size:.74rem;font-weight:600;color:var(--t)}
.dt-row .ti .t2{font-size:.6rem;color:var(--t3);margin-top:2px;font-family:'JetBrains Mono',monospace}
.dt-row .am{font-family:'JetBrains Mono',monospace;font-weight:700;font-size:.85rem;text-align:right}
.dt-row .am .st{font-size:.5rem;color:var(--t3);text-transform:uppercase;letter-spacing:.5px;font-weight:700;margin-top:2px}
.dt-row.depo .icw{background:rgba(74,222,128,.12);color:var(--green)}
.dt-row.depo .am{color:var(--green)}
.dt-row.wd .icw{background:rgba(248,113,113,.12);color:var(--red)}
.dt-row.wd .am{color:var(--red)}
.dt-row.bonus .icw{background:rgba(251,191,36,.12);color:var(--orange)}
.dt-row.bonus .am{color:var(--orange)}
.dt-row.other .icw{background:rgba(34,211,238,.12);color:var(--sec)}
.dt-row.other .am{color:var(--sec)}
.dt-empty{text-align:center;padding:50px 20px;color:var(--t3);font-size:.78rem}
.dt-empty svg{width:48px;height:48px;opacity:.3;margin:0 auto 14px;display:block}

@media(max-width:640px){.u-stats{grid-template-columns:1fr 1fr}.dt-stat{grid-template-columns:1fr 1fr}}
</style>

<?php if ($flash): ?><div class="msg msg-ok">
  <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="16" height="16"><polyline points="20 6 9 17 4 12"/></svg>
  <?=htmlspecialchars($flash)?>
</div><?php endif; ?>

<div class="u-stats">
  <div class="stat-card s1">
    <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
    <div class="sv"><?=number_format($statsTotal)?></div><div class="sl">Total User</div>
  </div>
  <div class="stat-card s2">
    <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></div>
    <div class="sv"><?=number_format($statsActive)?></div><div class="sl">Aktif Depo</div>
  </div>
  <div class="stat-card s3">
    <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><rect x="2" y="6" width="20" height="12" rx="2"/><circle cx="12" cy="12" r="3"/></svg></div>
    <div class="sv"><?=fmtK($statsBalance)?></div><div class="sl">Total Saldo</div>
  </div>
  <div class="stat-card s4">
    <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><polyline points="17 11 19 13 23 9"/></svg></div>
    <div class="sv"><?=number_format($statsToday)?></div><div class="sl">Hari Ini</div>
  </div>
</div>

<form method="GET" class="u-toolbar">
  <div class="u-search">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input type="search" name="q" value="<?=htmlspecialchars($q)?>" placeholder="Cari username, phone, ID..." autocomplete="off">
  </div>
  <?php if ($f !== 'all'): ?><input type="hidden" name="f" value="<?=htmlspecialchars($f)?>"><?php endif; ?>
</form>

<div class="u-chips">
  <a href="?q=<?=urlencode($q)?>" class="<?=$f==='all'?'on':''?>">Semua</a>
  <a href="?f=active_dep&q=<?=urlencode($q)?>" class="<?=$f==='active_dep'?'on':''?>">Aktif</a>
  <a href="?f=vip&q=<?=urlencode($q)?>" class="<?=$f==='vip'?'on':''?>">VIP</a>
  <a href="?f=admin&q=<?=urlencode($q)?>" class="<?=$f==='admin'?'on':''?>">Admin</a>
  <a href="?f=banned&q=<?=urlencode($q)?>" class="<?=$f==='banned'?'on':''?>">Banned</a>
</div>

<div class="u-list">
<?php if (empty($users)): ?>
  <div style="text-align:center;color:var(--t3);font-size:.82rem;padding:60px 0">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" width="56" height="56" style="opacity:.3;margin-bottom:14px"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg>
    <div>Tidak ada user ditemukan.</div>
  </div>
<?php else: foreach ($users as $u):
    $isUserAdmin = ($u['role'] ?? '') === 'admin';
    $isBanned = ($u['status'] ?? '') === 'banned';
    $isVip = ($u['vip_level'] ?? 0) > 0;
    $hasDepo = ($u['total_deposit'] ?? 0) > 0;
    $initials = strtoupper(substr($u['username'] ?? 'U', 0, 2));
    $isNew = isset($u['created_at']) && strtotime($u['created_at']) > time() - 7*86400;
?>
  <div class="u-card <?=$isUserAdmin?'admin':''?> <?=$isBanned?'banned':''?>" id="uc-<?=$u['id']?>">
    <div class="uc-row1" onclick="toggleCard(<?=$u['id']?>)">
      <div class="uc-avatar"><?=htmlspecialchars($initials)?></div>
      <div class="uc-info">
        <div class="uc-name"><?=htmlspecialchars($u['username'] ?? '-')?></div>
        <div class="uc-meta">#<?=$u['id']?> · <?=htmlspecialchars($u['phone'] ?? '-')?></div>
      </div>
      <div class="uc-bal">
        <div class="v"><?=number_format($u['balance'] ?? 0, 0, ',', '.')?></div>
        <div class="l">Saldo</div>
      </div>
    </div>
    <div class="uc-tags">
      <?php if ($isUserAdmin): ?><span class="uc-tag admin">Admin</span><?php endif; ?>
      <?php if ($isVip): ?><span class="uc-tag vip">VIP <?=$u['vip_level']?></span><?php endif; ?>
      <?php if ($hasDepo): ?><span class="uc-tag deposited">Depo <?=fmtK($u['total_deposit'])?></span><?php endif; ?>
      <?php if ($isBanned): ?><span class="uc-tag banned">Banned</span><?php endif; ?>
      <?php if ($isNew && !$isUserAdmin): ?><span class="uc-tag new">Baru</span><?php endif; ?>
    </div>
    <div class="uc-panel">
      <form method="POST">
        <input type="hidden" name="act" value="update">
        <input type="hidden" name="id" value="<?=$u['id']?>">
        <div class="uc-fields">
          <div><label>Saldo</label><input name="balance" type="number" value="<?=$u['balance'] ?? 0?>"></div>
          <div><label>VIP Level</label><input name="vip" type="number" min="0" max="50" value="<?=$u['vip_level'] ?? 0?>"></div>
          <div style="grid-column:1/-1"><label>Role</label><select name="role"><option value="user" <?=!$isUserAdmin?'selected':''?>>User</option><option value="admin" <?=$isUserAdmin?'selected':''?>>Admin</option></select></div>
        </div>
        <div class="uc-actions">
          <button type="submit" class="btn btn-sv"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>Simpan</button>
          <button type="button" class="btn btn-inj" onclick="event.stopPropagation();openInject(<?=$u['id']?>,'<?=htmlspecialchars($u['username'] ?? '',ENT_QUOTES)?>')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>Inject</button>
        </div>
      </form>
      <div class="uc-actions" style="margin-top:7px">
        <button type="button" class="btn btn-dt" onclick="event.stopPropagation();openDetail(<?=$u['id']?>,'<?=htmlspecialchars($u['username'] ?? '',ENT_QUOTES)?>','<?=htmlspecialchars($u['phone'] ?? '',ENT_QUOTES)?>')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>Detail Riwayat</button>
        <form method="POST" style="flex:1;display:flex">
          <input type="hidden" name="act" value="toggle_status">
          <input type="hidden" name="id" value="<?=$u['id']?>">
          <button type="submit" class="btn btn-bn" style="flex:1" onclick="return confirm('<?=$isBanned?'Aktifkan':'Ban'?> user ini?')"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg><?=$isBanned?'Aktifkan':'Ban'?></button>
        </form>
      </div>
    </div>
  </div>
<?php endforeach; endif; ?>
</div>

<div class="inj-modal" id="injModal">
  <div class="inj-box">
    <h3>Inject Saldo</h3>
    <p id="injUser" style="color:var(--sec);font-family:'JetBrains Mono',monospace">—</p>
    <form method="POST">
      <input type="hidden" name="act" value="inject">
      <input type="hidden" name="id" id="injId">
      <div class="fg"><label>Jumlah (Rp)</label><input name="amount" id="injAmt" type="number" placeholder="10000" required></div>
      <div class="fg"><label>Catatan (opsional)</label><input name="note" placeholder="Bonus manual, koreksi, dll"></div>
      <div class="inj-actions">
        <button type="button" class="btn btn-ghost" onclick="closeInject()">Batal</button>
        <button type="submit" class="btn btn-sv">Inject Saldo</button>
      </div>
    </form>
  </div>
</div>

<div class="dt-modal" id="dtModal">
  <div class="dt-box">
    <div class="dt-h">
      <div class="av" id="dtAv">U</div>
      <div class="ti">
        <h3 id="dtName">—</h3>
        <p id="dtMeta">—</p>
      </div>
      <button class="x" onclick="closeDetail()">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="14" height="14"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="dt-body" id="dtBody">
      <div style="text-align:center;padding:40px 20px;color:var(--t3);font-size:.78rem">Memuat...</div>
    </div>
  </div>
</div>

<script>
function toggleCard(id){
  var c=document.getElementById('uc-'+id);
  if(!c)return;
  if(c.classList.contains('on'))c.classList.remove('on');
  else{
    document.querySelectorAll('.u-card.on').forEach(function(x){x.classList.remove('on')});
    c.classList.add('on');
  }
}
function openInject(id,name){
  document.getElementById('injId').value=id;
  document.getElementById('injAmt').value='';
  document.getElementById('injUser').textContent=name+' (#'+id+')';
  document.getElementById('injModal').classList.add('on');
  setTimeout(function(){document.getElementById('injAmt').focus()},100);
}
function closeInject(){document.getElementById('injModal').classList.remove('on')}
document.getElementById('injModal').addEventListener('click',function(e){if(e.target===this)closeInject()});

function fmtRp(n){return Number(n||0).toLocaleString('id-ID')}
function escH(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;')}

var dtCurUid=null;
function openDetail(id,name,phone){
  dtCurUid=id;
  document.getElementById('dtAv').textContent=(name||'U').substring(0,2).toUpperCase();
  document.getElementById('dtName').textContent=name||'-';
  document.getElementById('dtMeta').textContent='#'+id+' · '+(phone||'-');
  document.getElementById('dtBody').innerHTML='<div style="text-align:center;padding:40px 20px;color:var(--t3);font-size:.78rem">Memuat detail...</div>';
  document.getElementById('dtModal').classList.add('on');
  loadDetail(id,'all');
}
function closeDetail(){document.getElementById('dtModal').classList.remove('on')}
document.getElementById('dtModal').addEventListener('click',function(e){if(e.target===this)closeDetail()});

function loadDetail(uid,tab){
  fetch('../api/admin.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'user_detail',user_id:uid,filter:tab})})
  .then(function(r){return r.json()}).then(function(d){
    if(!d.ok){document.getElementById('dtBody').innerHTML='<div style="color:var(--red);padding:20px;text-align:center;font-size:.78rem">'+(d.error||'Error')+'</div>';return}
    renderDetail(d,tab);
  }).catch(function(){
    document.getElementById('dtBody').innerHTML='<div style="color:var(--red);padding:20px;text-align:center;font-size:.78rem">Koneksi gagal</div>';
  });
}

function setTab(tab){
  document.querySelectorAll('.dt-tab').forEach(function(t){t.classList.toggle('on',t.dataset.tab===tab)});
  loadDetail(dtCurUid,tab);
}

function renderDetail(d,curTab){
  var s=d.summary||{};
  var h='<div class="dt-stat">'+
    '<div class="it green"><div class="v">'+fmtRp(s.total_deposit||0)+'</div><div class="l">Total Depo</div></div>'+
    '<div class="it cyan"><div class="v">'+(s.deposit_count||0)+'</div><div class="l">Kali Depo</div></div>'+
    '<div class="it orange"><div class="v">'+fmtRp(s.total_withdraw||0)+'</div><div class="l">Total WD</div></div>'+
    '<div class="it"><div class="v">'+(s.withdraw_count||0)+'</div><div class="l">Kali WD</div></div>'+
  '</div>';

  h+='<div class="dt-tabs">'+
    '<button class="dt-tab '+(curTab==='all'?'on':'')+'" data-tab="all" onclick="setTab(\'all\')">Semua</button>'+
    '<button class="dt-tab '+(curTab==='deposit'?'on':'')+'" data-tab="deposit" onclick="setTab(\'deposit\')">Deposit</button>'+
    '<button class="dt-tab '+(curTab==='withdraw'?'on':'')+'" data-tab="withdraw" onclick="setTab(\'withdraw\')">Withdraw</button>'+
    '<button class="dt-tab '+(curTab==='bonus'?'on':'')+'" data-tab="bonus" onclick="setTab(\'bonus\')">Bonus</button>'+
  '</div>';

  var list=d.transactions||[];
  if(!list.length){
    h+='<div class="dt-empty"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/></svg>Belum ada riwayat</div>';
  }else{
    h+='<div class="dt-list">';
    list.forEach(function(t){
      var cls='other',ic='',label=t.type;var sign='';
      var amt=parseInt(t.amount||0);
      if(t.type==='deposit'){cls='depo';ic='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 2v14m-5-5l5 5 5-5"/></svg>';label='Deposit';sign='+'}
      else if(t.type==='withdraw'){cls='wd';ic='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M12 16V2m5 5l-5-5-5 5"/></svg>';label='Withdraw';sign=''}
      else if(t.type==='bonus'||t.type==='referral'||t.type==='inject'||t.type==='cashback'){cls='bonus';ic='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';label=t.type==='referral'?'Referral':(t.type==='cashback'?'Cashback':(t.type==='inject'?'Admin Inject':'Bonus'));sign='+'}
      else{ic='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"/></svg>'}
      var dt=(t.created_at||'').replace('T',' ').substring(0,16);
      h+='<div class="dt-row '+cls+'"><div class="icw">'+ic+'</div>'+
         '<div class="ti"><div class="t1">'+escH(t.note||label)+'</div><div class="t2">'+dt+(t.ref_id?' · #'+escH(t.ref_id):'')+'</div></div>'+
         '<div class="am">'+sign+fmtRp(Math.abs(amt))+'<div class="st">SALDO '+fmtRp(t.balance_after||0)+'</div></div></div>';
    });
    h+='</div>';
  }
  document.getElementById('dtBody').innerHTML=h;
}

// Auto-open user detail jika ada ?uid= di URL (dari deposits/withdrawals row click)
(function(){
  var params=new URLSearchParams(location.search);
  var uid=params.get('uid');
  if(uid){
    uid=parseInt(uid);
    // Fetch user basic info dulu, lalu open modal
    fetch('../api/admin.php',{method:'POST',headers:{'Content-Type':'application/json'},credentials:'same-origin',body:JSON.stringify({action:'user_detail',user_id:uid,filter:'all'})})
    .then(function(r){return r.json()}).then(function(d){
      if(!d.ok||!d.user)return;
      openDetail(uid,d.user.username||'User',d.user.phone||'');
    });
  }
})();
</script>

<?php adminFooter(); ?>
