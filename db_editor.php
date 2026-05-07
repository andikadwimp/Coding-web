<?php
/**
 * DB Config Editor — standalone tool buat ganti kredensial database
 * Akses: /db_editor.php
 *
 * CARA PAKAI:
 * 1. Akses file ini di browser
 * 2. Isi password admin (default: 'admin123' — GANTI di baris $ADMIN_PASS di bawah)
 * 3. Masukin kredensial DB baru → test connection → simpan
 * 4. File db_config.php akan di-overwrite dengan credential baru
 *
 * ⚠️  SETELAH SELESAI: HAPUS atau protect file ini!
 */

// ═══ PASSWORD PROTECTION — GANTI INI SEBELUM UPLOAD ═══
$ADMIN_PASS = 'admin123';

// ═════════════════════════════════════════════════════
session_start();
header('Content-Type: text/html; charset=UTF-8');

$configFile = __DIR__ . '/db_config.php';
$msg = '';
$msgType = '';
$testResult = null;

// Logout
if (isset($_GET['logout'])) {
    $_SESSION['db_editor_auth'] = false;
    header('Location: db_editor.php');
    exit;
}

// Login
if (!empty($_POST['password']) && empty($_SESSION['db_editor_auth'])) {
    if (hash_equals($ADMIN_PASS, $_POST['password'])) {
        $_SESSION['db_editor_auth'] = true;
    } else {
        $msg = 'Password salah';
        $msgType = 'error';
    }
}

// Auth check
if (empty($_SESSION['db_editor_auth'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>DB Config Editor — Login</title>
    <style>
    *{margin:0;padding:0;box-sizing:border-box;font-family:system-ui,-apple-system,sans-serif}
    body{background:#0f172a;color:#fff;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
    .card{background:#1e293b;border:1px solid rgba(56,189,248,.2);border-radius:16px;padding:32px;max-width:380px;width:100%;box-shadow:0 10px 40px rgba(0,0,0,.4)}
    h1{font-size:1.3rem;margin-bottom:6px;color:#38bdf8}
    .sub{font-size:.8rem;color:#cbd5e1;margin-bottom:24px}
    label{display:block;font-size:.78rem;font-weight:600;margin-bottom:6px;color:#cbd5e1}
    input{width:100%;padding:12px 14px;background:#0f172a;border:1.5px solid rgba(56,189,248,.2);border-radius:8px;color:#fff;font-size:.9rem;outline:none}
    input:focus{border-color:#38bdf8}
    button{width:100%;padding:13px;background:linear-gradient(135deg,#38bdf8,#0284c7);border:none;border-radius:8px;color:#fff;font-size:.92rem;font-weight:700;cursor:pointer;margin-top:16px}
    button:active{transform:scale(.98)}
    .msg{padding:10px 12px;border-radius:8px;font-size:.78rem;margin-bottom:16px}
    .msg.error{background:rgba(239,68,68,.12);border:1px solid rgba(239,68,68,.3);color:#fca5a5}
    </style>
    </head>
    <body>
    <div class="card">
    <h1>🔐 DB Config Editor</h1>
    <div class="sub">Masukkan password admin untuk mengakses tool edit database.</div>
    <?php if ($msg): ?>
    <div class="msg <?= $msgType ?>"><?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <form method="POST">
    <label>Password Admin</label>
    <input type="password" name="password" required autofocus autocomplete="current-password">
    <button type="submit">Masuk</button>
    </form>
    </div>
    </body>
    </html>
    <?php
    exit;
}

// Load current config
$current = [
    'DB_HOST' => 'localhost',
    'DB_NAME' => '',
    'DB_USER' => '',
    'DB_PASS' => '',
];
if (file_exists($configFile)) {
    $contents = file_get_contents($configFile);
    foreach ($current as $key => &$val) {
        if (preg_match("/define\s*\(\s*['\"]" . preg_quote($key, '/') . "['\"]\s*,\s*['\"]((?:\\\\.|[^'\"\\\\])*)['\"]\s*\)/", $contents, $m)) {
            $val = stripslashes($m[1]);
        }
    }
    unset($val);
} else {
    // Coba load dari config.php default
    $configMain = __DIR__ . '/includes/config.php';
    if (file_exists($configMain)) {
        $contents = file_get_contents($configMain);
        foreach ($current as $key => &$val) {
            if (preg_match("/define\s*\(\s*['\"]" . preg_quote($key, '/') . "['\"]\s*,\s*['\"]((?:\\\\.|[^'\"\\\\])*)['\"]\s*\)/", $contents, $m)) {
                $val = stripslashes($m[1]);
            }
        }
        unset($val);
    }
}

// Test connection
function testDbConnection($host, $name, $user, $pass) {
    try {
        $dsn = "mysql:host=$host;dbname=$name;charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $ver = $pdo->query("SELECT VERSION()")->fetchColumn();
        // Hitung tabel
        $tblCnt = (int)$pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
        return ['ok' => true, 'version' => $ver, 'tables' => $tblCnt];
    } catch (Exception $e) {
        return ['ok' => false, 'error' => $e->getMessage()];
    }
}

// Save config
if (!empty($_POST['action']) && $_POST['action'] === 'save') {
    $newHost = trim($_POST['db_host'] ?? '');
    $newName = trim($_POST['db_name'] ?? '');
    $newUser = trim($_POST['db_user'] ?? '');
    $newPass = $_POST['db_pass'] ?? '';

    if (!$newHost || !$newName || !$newUser) {
        $msg = 'Host, Nama DB, dan User wajib diisi';
        $msgType = 'error';
    } else {
        // Test connection dulu
        $test = testDbConnection($newHost, $newName, $newUser, $newPass);
        if (!$test['ok']) {
            $msg = 'Gagal konek: ' . $test['error'];
            $msgType = 'error';
            $current = ['DB_HOST' => $newHost, 'DB_NAME' => $newName, 'DB_USER' => $newUser, 'DB_PASS' => $newPass];
        } else {
            // Tulis file db_config.php
            $esc = function ($s) { return str_replace(["\\", "'"], ["\\\\", "\\'"], $s); };
            $phpCode = "<?php\n";
            $phpCode .= "// Database credentials — di-generate otomatis oleh db_editor.php\n";
            $phpCode .= "// Jangan edit manual kecuali tahu apa yang dilakukan.\n";
            $phpCode .= "// Timestamp: " . date('Y-m-d H:i:s') . "\n\n";
            $phpCode .= "define('DB_HOST', '" . $esc($newHost) . "');\n";
            $phpCode .= "define('DB_NAME', '" . $esc($newName) . "');\n";
            $phpCode .= "define('DB_USER', '" . $esc($newUser) . "');\n";
            $phpCode .= "define('DB_PASS', '" . $esc($newPass) . "');\n";

            // Backup file lama
            if (file_exists($configFile)) {
                @copy($configFile, $configFile . '.bak.' . date('YmdHis'));
            }
            if (file_put_contents($configFile, $phpCode) === false) {
                $msg = 'Gagal menulis file db_config.php — cek permission folder!';
                $msgType = 'error';
            } else {
                @chmod($configFile, 0644);
                $msg = '✓ Berhasil disimpan. Connection test OK. MySQL ' . htmlspecialchars($test['version']) . ' • ' . $test['tables'] . ' tabel.';
                $msgType = 'success';
                $current = ['DB_HOST' => $newHost, 'DB_NAME' => $newName, 'DB_USER' => $newUser, 'DB_PASS' => $newPass];
            }
        }
    }
}

// Test only
if (!empty($_POST['action']) && $_POST['action'] === 'test') {
    $testResult = testDbConnection(
        trim($_POST['db_host'] ?? ''),
        trim($_POST['db_name'] ?? ''),
        trim($_POST['db_user'] ?? ''),
        $_POST['db_pass'] ?? ''
    );
    $current = [
        'DB_HOST' => trim($_POST['db_host'] ?? ''),
        'DB_NAME' => trim($_POST['db_name'] ?? ''),
        'DB_USER' => trim($_POST['db_user'] ?? ''),
        'DB_PASS' => $_POST['db_pass'] ?? '',
    ];
}

$configExists = file_exists($configFile);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>DB Config Editor</title>
<style>
*{margin:0;padding:0;box-sizing:border-box;font-family:system-ui,-apple-system,sans-serif}
body{background:#0f172a;color:#fff;min-height:100vh;padding:20px}
.wrap{max-width:600px;margin:0 auto}
.hdr{display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;padding:16px 20px;background:#1e293b;border:1px solid rgba(56,189,248,.2);border-radius:12px}
h1{font-size:1.15rem;color:#38bdf8;font-weight:700}
.logout-btn{padding:8px 16px;background:rgba(239,68,68,.15);border:1px solid rgba(239,68,68,.3);border-radius:8px;color:#fca5a5;font-size:.75rem;font-weight:700;cursor:pointer;text-decoration:none}
.logout-btn:hover{background:rgba(239,68,68,.25)}
.card{background:#1e293b;border:1px solid rgba(56,189,248,.2);border-radius:16px;padding:24px;margin-bottom:16px}
.card h2{font-size:.92rem;color:#38bdf8;margin-bottom:6px;font-weight:700}
.card .card-sub{font-size:.72rem;color:#64748b;margin-bottom:18px;line-height:1.5}
label{display:block;font-size:.72rem;font-weight:600;margin-bottom:5px;color:#cbd5e1}
input{width:100%;padding:11px 14px;background:#0f172a;border:1.5px solid rgba(56,189,248,.2);border-radius:8px;color:#fff;font-size:.88rem;outline:none;margin-bottom:12px;font-family:monospace}
input:focus{border-color:#38bdf8}
.btn-row{display:flex;gap:10px;margin-top:16px}
button{flex:1;padding:12px;border:none;border-radius:8px;font-size:.82rem;font-weight:700;cursor:pointer;font-family:inherit}
.btn-save{background:linear-gradient(135deg,#38bdf8,#0284c7);color:#fff}
.btn-test{background:rgba(56,189,248,.12);border:1.5px solid rgba(56,189,248,.35);color:#38bdf8}
button:active{transform:scale(.98)}
.msg{padding:12px 14px;border-radius:10px;font-size:.82rem;margin-bottom:16px;line-height:1.5}
.msg.success{background:rgba(74,222,128,.1);border:1px solid rgba(74,222,128,.3);color:#86efac}
.msg.error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5}
.info{background:rgba(56,189,248,.06);border:1px solid rgba(56,189,248,.18);border-radius:10px;padding:12px 14px;font-size:.74rem;color:#cbd5e1;line-height:1.5;margin-bottom:16px}
.info b{color:#38bdf8}
.warn{background:rgba(251,191,36,.08);border:1px solid rgba(251,191,36,.3);border-radius:10px;padding:12px 14px;font-size:.72rem;color:#fbbf24;line-height:1.5}
.current{background:#0f172a;border:1px dashed rgba(56,189,248,.3);border-radius:8px;padding:10px 14px;font-size:.7rem;color:#cbd5e1;font-family:monospace;margin-bottom:16px;line-height:1.6}
.current b{color:#4ade80}
.help-inline{font-size:.62rem;color:#64748b;margin:-8px 0 12px;line-height:1.4}
a{color:#38bdf8}
</style>
</head>
<body>
<div class="wrap">

<div class="hdr">
  <h1>🔐 DB Config Editor</h1>
  <a href="?logout=1" class="logout-btn">Keluar</a>
</div>

<?php if ($msg): ?>
<div class="msg <?= $msgType ?>"><?= $msg ?></div>
<?php endif; ?>

<?php if ($testResult !== null): ?>
<div class="msg <?= $testResult['ok'] ? 'success' : 'error' ?>">
<?php if ($testResult['ok']): ?>
✓ <b>Connection OK!</b> MySQL <?= htmlspecialchars($testResult['version']) ?> • <?= $testResult['tables'] ?> tabel.
<?php else: ?>
✗ <b>Gagal konek:</b> <?= htmlspecialchars($testResult['error']) ?>
<?php endif; ?>
</div>
<?php endif; ?>

<div class="info">
<b>ℹ️  Cara Pakai</b><br>
1. Isi form di bawah dengan kredensial DB baru<br>
2. Klik <b>"Test Koneksi"</b> dulu untuk cek sebelum simpan<br>
3. Kalau OK, klik <b>"Simpan & Terapkan"</b><br>
4. File <code>db_config.php</code> akan di-generate otomatis dan langsung dipakai oleh aplikasi<br>
5. File lama di-backup dengan timestamp
</div>

<div class="card">
<h2>Konfigurasi Database</h2>
<div class="card-sub">Status: <?= $configExists ? '<span style="color:#4ade80">File db_config.php sudah ada</span>' : '<span style="color:#fbbf24">Pakai credentials default dari includes/config.php</span>' ?></div>

<?php if ($configExists): ?>
<div class="current">
Saat ini:<br>
HOST: <b><?= htmlspecialchars($current['DB_HOST']) ?></b><br>
DB: <b><?= htmlspecialchars($current['DB_NAME']) ?></b><br>
USER: <b><?= htmlspecialchars($current['DB_USER']) ?></b><br>
PASS: <b><?= str_repeat('•', max(4, strlen($current['DB_PASS']))) ?></b>
</div>
<?php endif; ?>

<form method="POST">
<input type="hidden" name="action" id="actionField" value="save">

<label>Host DB</label>
<input type="text" name="db_host" value="<?= htmlspecialchars($current['DB_HOST']) ?>" required placeholder="localhost">
<div class="help-inline">Biasanya "localhost". Di beberapa hosting bisa "127.0.0.1" atau IP server.</div>

<label>Nama Database</label>
<input type="text" name="db_name" value="<?= htmlspecialchars($current['DB_NAME']) ?>" required placeholder="contoh: mysite_prod">
<div class="help-inline">Nama database persis seperti di cPanel/hosting.</div>

<label>User DB</label>
<input type="text" name="db_user" value="<?= htmlspecialchars($current['DB_USER']) ?>" required placeholder="contoh: mysite_user">
<div class="help-inline">Username yang punya akses ke database.</div>

<label>Password DB</label>
<input type="password" name="db_pass" value="<?= htmlspecialchars($current['DB_PASS']) ?>" placeholder="password">
<div class="help-inline">Password user database. Boleh kosong kalau emang ga pake password.</div>

<div class="btn-row">
<button type="submit" class="btn-test" onclick="document.getElementById('actionField').value='test'">Test Koneksi</button>
<button type="submit" class="btn-save" onclick="document.getElementById('actionField').value='save'">Simpan & Terapkan</button>
</div>
</form>
</div>

<div class="warn">
⚠️ <b>PENTING — Setelah Selesai:</b><br>
• HAPUS atau rename file <code>db_editor.php</code> ini (biar ga bisa diakses orang lain)<br>
• Atau minimal ganti password di baris <code>$ADMIN_PASS</code> di dalam file<br>
• Atau tambah htaccess password protect ke file ini
</div>

</div>
</body>
</html>
