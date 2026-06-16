<?php
header('Content-Type: text/html; charset=UTF-8');
require_once 'layout.php'; adminHeader('Referral');

// Top referrers with stats
$rows = [];
try {
  $q = "SELECT
    u.id, u.username, u.phone, u.ref_code, u.display_id, u.created_at, u.total_deposit AS my_dep,
    (SELECT COUNT(*) FROM users d WHERE d.referred_by = u.ref_code) as total_refs,
    (SELECT COUNT(*) FROM users d WHERE d.referred_by = u.ref_code AND d.total_deposit > 0) as active_refs,
    COALESCE((SELECT SUM(amount) FROM transactions t WHERE t.user_id = u.id AND t.type = 'referral'),0) as total_earned,
    COALESCE((SELECT SUM(d.total_deposit) FROM users d WHERE d.referred_by = u.ref_code),0) as downline_dep
    FROM users u
    WHERE u.ref_code IS NOT NULL AND u.ref_code != ''
    HAVING total_refs > 0 OR total_earned > 0
    ORDER BY total_earned DESC, total_refs DESC
    LIMIT 200";
  $rows = $db->query($q)->fetchAll();
} catch (Exception $e) {}

// PRE-LOAD semua downlines per referrer biar ga perlu ajax
$downlinesByCode = [];
if (!empty($rows)) {
  $codes = array_filter(array_map(function($r){return $r['ref_code'];}, $rows), function($c){return $c!==null && $c!=='';});
  if (!empty($codes)) {
    try {
      $ph = implode(',', array_fill(0, count($codes), '?'));
      $st = $db->prepare("SELECT id, username, phone, referred_by, created_at, total_deposit
                          FROM users WHERE referred_by IN ($ph)
                          ORDER BY total_deposit DESC, created_at DESC");
      $st->execute(array_values($codes));
      while ($d = $st->fetch()) {
        $code = $d['referred_by'];
        if (!isset($downlinesByCode[$code])) $downlinesByCode[$code] = [];
        $downlinesByCode[$code][] = $d;
      }
    } catch (Exception $e) {}
  }
}

// Overall stats
$totalRefs = 0; $totalPaid = 0; $activeRefs = 0; $referrers = 0;
try {
  $totalRefs = (int)$db->query("SELECT COUNT(*) FROM users WHERE referred_by IS NOT NULL AND referred_by!=''")->fetchColumn();
  $activeRefs = (int)$db->query("SELECT COUNT(*) FROM users WHERE referred_by IS NOT NULL AND referred_by!='' AND total_deposit>0")->fetchColumn();
  $totalPaid = (int)$db->query("SELECT COALESCE(SUM(amount),0) FROM transactions WHERE type='referral'")->fetchColumn();
  $referrers = (int)$db->query("SELECT COUNT(DISTINCT referred_by) FROM users WHERE referred_by IS NOT NULL AND referred_by!=''")->fetchColumn();
} catch (Exception $e) {}

function fmtK($n){if($n>=1e9)return number_format($n/1e9,1).'B';if($n>=1e6)return number_format($n/1e6,1).'M';if($n>=1e3)return number_format($n/1e3,0).'K';return number_format($n);}
?>

<style>
.r-stats{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:20px}
.r-stats .stat-card{position:relative;padding:16px}
.r-stats .stat-card .ic{position:absolute;top:14px;right:14px;width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center}
.r-stats .stat-card .ic svg{width:17px;height:17px;position:static;opacity:1;color:#fff}
.r-stats .stat-card.s1 .ic{background:var(--grad-pri)}
.r-stats .stat-card.s2 .ic{background:var(--grad-success)}
.r-stats .stat-card.s3 .ic{background:var(--grad-sec)}
.r-stats .stat-card.s4 .ic{background:var(--grad-warn)}

.r-toolbar{display:flex;gap:10px;margin-bottom:14px;align-items:center}
.r-toolbar .sw{flex:1;position:relative}
.r-toolbar .sw input{width:100%;padding:11px 14px 11px 40px;background:var(--bg2);border:1px solid var(--bd);border-radius:11px;color:var(--t);font-size:.78rem;font-family:inherit;outline:none;transition:all .15s}
.r-toolbar .sw input:focus{border-color:var(--pri);background:var(--s);box-shadow:0 0 0 3px rgba(99,102,241,.15)}
.r-toolbar .sw svg{position:absolute;left:14px;top:50%;transform:translateY(-50%);color:var(--t3);pointer-events:none}
.r-count{font-size:.66rem;color:var(--t3);white-space:nowrap;font-weight:600}
.r-count b{color:var(--sec);font-family:'JetBrains Mono',monospace}

.r-list{display:flex;flex-direction:column;gap:10px}
.rcard{background:var(--bg2);border:1px solid var(--bd);border-radius:14px;padding:14px;cursor:pointer;transition:all .2s;position:relative;overflow:hidden;display:grid;grid-template-columns:1fr auto auto;align-items:center;gap:14px}
.rcard:hover{border-color:var(--bd2);transform:translateY(-1px);box-shadow:var(--shadow-sm);background:var(--s)}
.rcard::after{content:'';position:absolute;left:0;top:0;bottom:0;width:3px;background:var(--grad-pri);opacity:0;transition:opacity .2s}
.rcard:hover::after{opacity:1}

.rk-rank{position:absolute;top:0;right:0;background:var(--grad-pri);color:#fff;font-family:'JetBrains Mono',monospace;font-weight:800;font-size:.6rem;padding:3px 10px;border-bottom-left-radius:8px;letter-spacing:.5px}
.rk-rank.gold{background:linear-gradient(135deg,#fbbf24,#f59e0b)}
.rk-rank.silver{background:linear-gradient(135deg,#cbd5e1,#94a3b8)}
.rk-rank.bronze{background:linear-gradient(135deg,#f97316,#c2410c)}

.rk-user{display:flex;align-items:center;gap:12px;min-width:0}
.rk-av{width:42px;height:42px;border-radius:11px;background:var(--grad-sec);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:.92rem;flex-shrink:0;text-transform:uppercase;box-shadow:0 4px 10px rgba(34,211,238,.25)}
.rk-info{min-width:0}
.rk-name{font-size:.84rem;font-weight:700;color:var(--t);letter-spacing:-.01em;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:flex;align-items:center;gap:7px}
.rk-name .code{font-family:'JetBrains Mono',monospace;font-size:.62rem;color:var(--sec);background:rgba(34,211,238,.1);padding:2px 7px;border-radius:5px;letter-spacing:.5px;font-weight:700}
.rk-meta{font-size:.6rem;color:var(--t3);margin-top:3px;font-family:'JetBrains Mono',monospace}

.rk-counts{display:flex;gap:12px;align-items:center}
.rk-counts .ct{text-align:center;min-width:46px}
.rk-counts .ct .v{font-family:'JetBrains Mono',monospace;font-weight:700;font-size:1rem;letter-spacing:-.02em}
.rk-counts .ct .l{font-size:.5rem;color:var(--t3);text-transform:uppercase;letter-spacing:.6px;font-weight:700;margin-top:2px}
.rk-counts .ct.refs .v{color:var(--sec)}
.rk-counts .ct.act .v{color:var(--green)}

.rk-earned{text-align:right;min-width:90px}
.rk-earned .v{font-family:'JetBrains Mono',monospace;font-weight:800;font-size:.95rem;color:var(--orange);letter-spacing:-.02em}
.rk-earned .l{font-size:.52rem;color:var(--t3);text-transform:uppercase;letter-spacing:.6px;font-weight:700;margin-top:2px}

.r-empty{text-align:center;padding:60px 20px;color:var(--t3);font-size:.82rem}
.r-empty svg{width:56px;height:56px;opacity:.3;margin:0 auto 14px;display:block}

@media(max-width:768px){
  .r-stats{grid-template-columns:1fr 1fr}
  .rcard{grid-template-columns:1fr;gap:12px}
  .rk-counts{justify-content:flex-start;border-top:1px solid var(--bd);padding-top:10px}
  .rk-earned{text-align:left;border-top:1px solid var(--bd);padding-top:10px}
  .rk-earned .l{display:inline-block;margin-right:8px;margin-top:0}
}

/* Detail modal */
.rmodal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:200;align-items:flex-end;justify-content:center}
.rmodal.on{display:flex;animation:fadeIn .2s}
@keyframes fadeIn{from{opacity:0}to{opacity:1}}
@media(min-width:768px){.rmodal{align-items:center;padding:16px}}
.rmbox{background:var(--bg2);border:1px solid var(--bd2);border-top-left-radius:24px;border-top-right-radius:24px;width:100%;max-width:640px;max-height:90vh;display:flex;flex-direction:column;box-shadow:var(--shadow-lg);animation:slideTop .3s cubic-bezier(.34,1.56,.64,1)}
@media(min-width:768px){.rmbox{border-radius:20px;max-height:85vh}}
@keyframes slideTop{from{transform:translateY(100%)}to{transform:translateY(0)}}

.rmh{padding:18px 22px;border-bottom:1px solid var(--bd);display:flex;align-items:center;gap:14px}
.rmh .av{width:48px;height:48px;border-radius:12px;background:var(--grad-sec);display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:1rem;flex-shrink:0;text-transform:uppercase;box-shadow:0 4px 12px rgba(34,211,238,.3)}
.rmh .ti{flex:1;min-width:0}
.rmh .ti h3{font-size:1rem;font-weight:800;color:var(--t)}
.rmh .ti .c{font-size:.66rem;color:var(--sec);font-family:'JetBrains Mono',monospace;margin-top:3px}
.rmh .x{width:34px;height:34px;background:rgba(255,255,255,.04);border:1px solid var(--bd);border-radius:10px;color:var(--t2);cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .15s}
.rmh .x:hover{background:rgba(248,113,113,.1);color:var(--red)}

.rmbody{padding:18px 22px;overflow-y:auto;flex:1}
.rmsum{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:18px}
.rmsum .it{background:var(--bg);border:1px solid var(--bd);border-radius:11px;padding:13px;text-align:center}
.rmsum .it .v{font-family:'JetBrains Mono',monospace;font-weight:700;font-size:1.05rem;letter-spacing:-.02em}
.rmsum .it .l{font-size:.55rem;color:var(--t3);text-transform:uppercase;letter-spacing:.6px;font-weight:700;margin-top:4px}
.rmsum .it.s1 .v{color:var(--sec)}
.rmsum .it.s2 .v{color:var(--green)}
.rmsum .it.s3 .v{color:var(--orange)}

.rm-h{font-size:.7rem;font-weight:700;color:var(--t2);margin:6px 0 10px;text-transform:uppercase;letter-spacing:.6px;display:flex;align-items:center;gap:8px}
.rm-h::before{content:'';width:3px;height:14px;background:var(--grad-pri);border-radius:2px}
.rm-h .cnt{margin-left:auto;background:var(--bg);color:var(--t3);font-family:'JetBrains Mono',monospace;font-size:.6rem;padding:3px 9px;border-radius:6px;font-weight:700;letter-spacing:.4px;border:1px solid var(--bd)}

.dl-list{display:flex;flex-direction:column;gap:6px}
.dl-row{padding:11px 13px;background:var(--bg);border:1px solid var(--bd);border-radius:10px;display:flex;align-items:center;gap:11px;transition:all .15s;text-decoration:none;color:inherit}
.dl-row:hover{border-color:var(--pri);background:var(--s)}
.rcard-wrap{margin-bottom:10px}
.rcard{cursor:pointer;position:relative}
.rk-toggle{display:flex;align-items:center;justify-content:center;color:var(--t3);margin-left:8px;transition:transform .25s}
.rcard-wrap.open .rk-toggle{transform:rotate(180deg);color:var(--pri)}
.rcard-wrap.open .rcard{border-color:var(--pri);background:linear-gradient(to bottom,var(--s),var(--bg))}
.rcard-dl{display:none;padding:12px 14px 14px;background:var(--bg);border:1px solid var(--bd);border-top:none;border-radius:0 0 10px 10px;margin-top:-1px}
.rcard-wrap.open .rcard-dl{display:block;animation:slideDown .25s ease}
@keyframes slideDown{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:translateY(0)}}
.rcard-dl-h{display:flex;align-items:center;gap:8px;font-size:.7rem;font-weight:700;color:var(--t2);margin-bottom:10px;text-transform:uppercase;letter-spacing:.6px}
.rcard-dl-h svg{color:var(--pri)}
.rcard-dl-cnt{margin-left:auto;background:var(--s);color:var(--t3);font-family:'JetBrains Mono',monospace;font-size:.6rem;padding:3px 9px;border-radius:6px;font-weight:700;letter-spacing:.4px;border:1px solid var(--bd);text-transform:none}
.rcard-dl-empty{text-align:center;padding:20px;color:var(--t3);font-size:.74rem}
.dl-av{width:32px;height:32px;border-radius:9px;background:var(--bg3);display:flex;align-items:center;justify-content:center;color:var(--t3);font-weight:700;font-size:.75rem;flex-shrink:0;text-transform:uppercase}
.dl-row.active .dl-av{background:var(--grad-success);color:#fff;box-shadow:0 3px 8px rgba(74,222,128,.25)}
.dl-info{flex:1;min-width:0}
.dl-name{font-size:.74rem;font-weight:700;color:var(--t);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dl-date{font-size:.58rem;color:var(--t3);margin-top:2px;font-family:'JetBrains Mono',monospace}
.dl-dep{text-align:right;font-family:'JetBrains Mono',monospace;font-weight:700;font-size:.78rem}
.dl-dep .v{color:var(--green);letter-spacing:-.02em}
.dl-dep.none .v{color:var(--t3)}
.dl-dep .l{font-size:.5rem;color:var(--t3);text-transform:uppercase;letter-spacing:.5px;font-weight:700;margin-top:2px}

.rm-empty{text-align:center;padding:30px 20px;color:var(--t3);font-size:.74rem}
</style>

<div class="r-stats">
  <div class="stat-card s1">
    <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div>
    <div class="sv"><?=number_format($referrers)?></div><div class="sl">Referrers</div>
  </div>
  <div class="stat-card s2">
    <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg></div>
    <div class="sv"><?=number_format($totalRefs)?></div><div class="sl">Total Referred</div>
  </div>
  <div class="stat-card s3">
    <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg></div>
    <div class="sv"><?=number_format($activeRefs)?></div><div class="sl">Aktif Depo</div>
  </div>
  <div class="stat-card s4">
    <div class="ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div>
    <div class="sv"><?=fmtK($totalPaid)?></div><div class="sl">Total Komisi</div>
  </div>
</div>

<div class="r-toolbar">
  <div class="sw">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
    <input id="rqs" placeholder="Cari username, ref code, phone..." oninput="filterRefs()">
  </div>
  <div class="r-count"><b id="rCount"><?=count($rows)?></b> referrers</div>
  <div style="display:flex;gap:6px">
    <a class="btn btn-sec btn-sm" href="../api/admin.php?action=export_referrals&format=csv" title="Download CSV">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
      CSV
    </a>
    <a class="btn btn-sec btn-sm" href="../api/admin.php?action=export_referrals&format=excel" title="Download Excel">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="14" height="14"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="13" y2="17"/></svg>
      Excel
    </a>
  </div>
</div>

<div class="r-list" id="refList">
<?php if (empty($rows)): ?>
  <div class="r-empty">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M16 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
    <div>Belum ada data referral.</div>
  </div>
<?php else: foreach ($rows as $i => $r):
  $rank = $i + 1;
  $rankCls = $rank == 1 ? 'gold' : ($rank == 2 ? 'silver' : ($rank == 3 ? 'bronze' : ''));
  $initials = strtoupper(substr($r['username'] ?? 'U', 0, 2));
  $dls = $downlinesByCode[$r['ref_code']] ?? [];
?>
  <div class="rcard-wrap">
    <div class="rcard" onclick="toggleDl(this)" data-search="<?=strtolower(htmlspecialchars(($r['username'].' '.$r['ref_code'].' '.($r['phone']??''))))?>">
      <?php if ($rank <= 3): ?><div class="rk-rank <?=$rankCls?>">#<?=$rank?></div><?php endif; ?>
      <div class="rk-user">
        <div class="rk-av"><?=htmlspecialchars($initials)?></div>
        <div class="rk-info">
          <div class="rk-name"><?=htmlspecialchars($r['username'])?> <span class="code"><?=htmlspecialchars($r['ref_code'])?></span></div>
          <div class="rk-meta">#<?=$r['id']?> · downline depo: Rp <?=fmtK($r['downline_dep'])?></div>
        </div>
      </div>
      <div class="rk-counts">
        <div class="ct refs"><div class="v"><?=$r['total_refs']?></div><div class="l">Referred</div></div>
        <div class="ct act"><div class="v"><?=$r['active_refs']?></div><div class="l">Aktif</div></div>
      </div>
      <div class="rk-earned">
        <div class="v">Rp <?=fmtK($r['total_earned'])?></div>
        <div class="l">Earned</div>
      </div>
      <div class="rk-toggle"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" width="18" height="18"><polyline points="6 9 12 15 18 9"/></svg></div>
    </div>
    <div class="rcard-dl">
      <div class="rcard-dl-h">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="13" height="13"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
        <span>Downlines</span>
        <span class="rcard-dl-cnt"><?=count($dls)?> org</span>
      </div>
      <?php if (empty($dls)): ?>
        <div class="rcard-dl-empty">Belum ada yang daftar pake kode <b><?=htmlspecialchars($r['ref_code'])?></b></div>
      <?php else: ?>
        <div class="dl-list">
        <?php foreach ($dls as $d):
          $dep = (int)($d['total_deposit'] ?? 0);
          $active = $dep > 0;
          $dlInit = strtoupper(substr($d['username'] ?? 'U', 0, 2));
          $dlDate = substr(str_replace('T',' ', $d['created_at'] ?? ''), 0, 16);
        ?>
          <a class="dl-row <?=$active?'active':''?>" href="users.php?uid=<?=$d['id']?>">
            <div class="dl-av"><?=htmlspecialchars($dlInit)?></div>
            <div class="dl-info">
              <div class="dl-name"><?=htmlspecialchars($d['username'])?></div>
              <div class="dl-date"><?=$dlDate?></div>
            </div>
            <div class="dl-dep <?=$active?'':'none'?>">
              <div class="v"><?=$active?'Rp '.fmtK($dep):'-'?></div>
              <div class="l"><?=$active?'Total Depo':'Belum depo'?></div>
            </div>
          </a>
        <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; endif; ?>
</div>

<script>
function toggleDl(el){
  var wrap = el.parentElement;
  wrap.classList.toggle('open');
}
function fmtRp(n){return Number(n||0).toLocaleString('id-ID')}
function escH(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}

function filterRefs(){
  var q=document.getElementById('rqs').value.toLowerCase().trim();
  var rows=document.querySelectorAll('.rcard');var n=0;
  rows.forEach(function(r){
    var m=!q||(r.dataset.search||'').indexOf(q)>-1;
    var wrap=r.parentElement;
    wrap.style.display=m?'':'none';
    if(m)n++;
  });
  document.getElementById('rCount').textContent=n;
}
</script>

<?php adminFooter(); ?>
