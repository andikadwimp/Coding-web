<?php
header('Content-Type: text/html; charset=UTF-8');
require_once 'layout.php'; adminHeader('Lucky Spin');
// Auto-create tables
try{$db->exec("CREATE TABLE IF NOT EXISTS spin_prizes (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,label VARCHAR(50),amount BIGINT UNSIGNED DEFAULT 0,probability DECIMAL(6,3) DEFAULT 0,color VARCHAR(20) DEFAULT '#38bdf8',sort_order INT DEFAULT 0) ENGINE=InnoDB");}catch(Exception $e){}
try{$db->exec("CREATE TABLE IF NOT EXISTS spin_tickets (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,used TINYINT DEFAULT 0,deposit_id INT UNSIGNED DEFAULT NULL,created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");}catch(Exception $e){}
try{$db->exec("CREATE TABLE IF NOT EXISTS spin_history (id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,user_id INT UNSIGNED NOT NULL,prize_id INT UNSIGNED DEFAULT NULL,label VARCHAR(50),amount BIGINT UNSIGNED DEFAULT 0,created_at DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");}catch(Exception $e){}
// Seed if empty
if(!$db->query("SELECT COUNT(*) FROM spin_prizes")->fetchColumn()){
    $db->exec("INSERT INTO spin_prizes (label,amount,probability,color,sort_order) VALUES
    ('Rp 5.000.000',5000000,0.010,'#f59e0b',1),('Rp 2.000.000',2000000,0.020,'#ef4444',2),
    ('Rp 1.000.000',1000000,0.050,'#fbbf24',3),('Rp 500.000',500000,0.100,'#fbbf24',4),
    ('Rp 250.000',250000,0.200,'#06b6d4',5),('Rp 100.000',100000,0.500,'#10b981',6),
    ('Rp 10.000',10000,5.000,'#38bdf8',7),('Rp 5.000',5000,15.120,'#0284c7',8),
    ('Zonk',0,15.800,'#6b7280',9),('Zonk',0,15.800,'#4b5563',10),
    ('Zonk',0,15.800,'#374151',11),('Zonk',0,15.800,'#6b7280',12),('Zonk',0,15.800,'#4b5563',13)");
}
// Handle save
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='save'){
    foreach($_POST['prizes'] as $id=>$data){
        $db->prepare("UPDATE spin_prizes SET label=?,probability=?,color=? WHERE id=?")
           ->execute([trim($data['label']),floatval($data['prob']),$data['color'],intval($id)]);
    }
    echo '<div class="msg msg-ok">Tersimpan!</div>';
}
$prizes=$db->query("SELECT * FROM spin_prizes ORDER BY sort_order ASC")->fetchAll();
$total=array_sum(array_column($prizes,'probability'));
// Stats
$ticketsTotal=$db->query("SELECT COUNT(*) FROM spin_tickets")->fetchColumn();
$ticketsUsed=$db->query("SELECT COUNT(*) FROM spin_tickets WHERE used=1")->fetchColumn();
$winsTotal=$db->query("SELECT COALESCE(SUM(amount),0) FROM spin_history WHERE amount>0")->fetchColumn();
?>
<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:20px">
  <div class="stat-card sc-sys"><div class="stat-label">🎫 Total Tiket</div><div class="stat-val"><?=$ticketsTotal?></div><div class="stat-sub">Dipakai: <b><?=$ticketsUsed?></b></div></div>
  <div class="stat-card sc-profit"><div class="stat-label">🏆 Total Hadiah</div><div class="stat-val" style="font-size:.9rem">Rp <?=number_format($winsTotal,0,',','.')?></div></div>
  <div class="stat-card <?=$total==100?'sc-income':'sc-wd'?>"><div class="stat-label"><svg viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' width='12' height='12' style='vertical-align:-1px;margin-right:3px'><line x1='18' y1='20' x2='18' y2='10'/><line x1='12' y1='20' x2='12' y2='4'/><line x1='6' y1='20' x2='6' y2='14'/></svg>Total %</div><div class="stat-val" style="color:<?=$total==100?'var(--green)':'#ef4444'?>"><?=number_format($total,3)?>%</div><div class="stat-sub"><?=$total==100?'✓ Valid':'Harus = 100%'?></div></div>
</div>

<form method="POST">
<input type="hidden" name="action" value="save">
<div class="table-box">
  <h3 style="padding:14px 16px;font-size:.82rem;font-weight:700;border-bottom:1px solid var(--bd)">
    Hadiah & Probabilitas
    <span style="font-size:.65rem;color:var(--t3);font-weight:400">Total harus tepat 100.000%</span>
  </h3>
  <table style="width:100%;border-collapse:collapse;font-size:.78rem">
    <tr style="background:rgba(var(--sec-rgb,56,189,248),.05)">
      <th style="padding:10px 14px;text-align:left;color:var(--t3)">Hadiah</th>
      <th style="padding:10px 14px;text-align:center;color:var(--t3)">Probabilitas (%)</th>
      <th style="padding:10px 14px;text-align:center;color:var(--t3)">Warna</th>
    </tr>
    <?php foreach($prizes as $p): ?>
    <tr style="border-top:1px solid var(--bd)">
      <td style="padding:10px 14px">
        <input name="prizes[<?=$p['id']?>][label]" value="<?=htmlspecialchars($p['label'])?>" style="width:100%;padding:6px 8px;background:var(--bg);border:1px solid var(--bd);border-radius:6px;color:var(--t);font-size:.75rem">
      </td>
      <td style="padding:10px 14px;text-align:center">
        <input name="prizes[<?=$p['id']?>][prob]" type="number" step="0.001" min="0" max="100" value="<?=$p['probability']?>" style="width:90px;padding:6px 8px;background:var(--bg);border:1px solid var(--bd);border-radius:6px;color:var(--t);font-size:.75rem;text-align:center">
      </td>
      <td style="padding:10px 14px;text-align:center">
        <input name="prizes[<?=$p['id']?>][color]" type="color" value="<?=htmlspecialchars($p['color'])?>" style="width:40px;height:32px;border-radius:6px;border:1px solid var(--bd);cursor:pointer;background:none">
      </td>
    </tr>
    <?php endforeach; ?>
  </table>
</div>
<div style="margin-top:14px;padding:12px;background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.15);border-radius:10px;font-size:.72rem;color:#ef4444">
  ⚠️ Pastikan total probabilitas = <b>100.000%</b>. Saat ini: <b><?=number_format($total,3)?>%</b>
</div>
<button class="btn btn-pri" type="submit" style="margin-top:14px;width:100%">💾 Simpan Probabilitas</button>
</form>
<?php adminFooter(); ?>
