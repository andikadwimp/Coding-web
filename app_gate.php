<?php
/**
 * APP GATE - Block browser access, allow only PWA/APK
 * Auto-included via config.php
 *
 * DEFAULT: ENABLED (web tertutup). Admin bisa disable lewat Settings.
 */

$GATE_EXEMPT = ['download.php', 'manifest.php', 'sw.js', 'theme.php', 'pwa_head.php', 'invite.php', 'gate.php', 'app_gate.php', 'setup.php', 'db_editor.php', 'clearcache.php'];
$uri = $_SERVER['REQUEST_URI'] ?? '';
$script = $_SERVER['PHP_SELF'] ?? '';
$current = basename($script);
if (in_array($current, $GATE_EXEMPT)) return;

// Skip /api/ and /team/
if (strpos($uri, '/api/') !== false || strpos($script, '/api/') !== false) return;
if (strpos($uri, '/team/') !== false || strpos($script, '/team/') !== false) return;

// Check gate setting — DEFAULT ENABLED (kecuali admin explicit disable)
$gateEnabled = true; // default ON
try {
    global $db;
    if (!isset($db)) require_once __DIR__.'/includes/config.php';
    $setting = $db->query("SELECT `value` FROM settings WHERE `key`='app_gate_enabled'")->fetchColumn();
    // Hanya disable kalau setting explicit = '0'
    if ($setting === '0') $gateEnabled = false;
} catch (Exception $e) {}
if (!$gateEnabled) return;

// ═══ SKIP GATE KALAU USER ADMIN AKTIF ═══
// Admin perlu akses dashboard dari browser — TAPI jangan set persistent cookie
// biar ga bocor ke akun member di device yg sama
$isAdminSession = false;
try {
    $tkn = $_COOKIE['lx_token'] ?? '';
    if ($tkn && isset($db)) {
        $chk = $db->prepare("SELECT role FROM users WHERE auth_token=? LIMIT 1");
        $chk->execute([$tkn]);
        $r = $chk->fetch();
        if ($r && ($r['role'] ?? '') === 'admin') {
            $isAdminSession = true;
        }
    }
} catch (Exception $e) {}

if ($isAdminSession) {
    return; // admin bypass, TANPA set cookie persistent
}

// ═══ Kalau cookie pwa_app ada dari sesi sebelumnya dan USER SEKARANG BUKAN ADMIN ═══
// Validasi: cek apakah masih di PWA. Hint client-side via pwa_head.php akan set ulang
// kalau standalone. Kalau user buka browser langsung, gate JS di gate.php bakal redirect.

// ═══ DETECTION ═══

// 1. ?pwa=1 → set cookie and allow (PWA manifest start_url)
if (!empty($_GET['pwa'])) {
    setcookie('pwa_app', '1', time()+31536000, '/', '', false, false);
    $_COOKIE['pwa_app'] = '1';
    return;
}

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

// 2. Android WebView detection (APK built via PWABuilder/Bubblewrap/TWA)
$isWebView = (strpos($ua, ' wv') !== false) ||
             (strpos($ua, 'PWABuilder') !== false) ||
             (strpos($ua, 'TWA') !== false);
if ($isWebView) {
    setcookie('pwa_app', '1', time()+31536000, '/', '', false, false);
    return;
}

// 3. Trust cookie pwa_app kalau udah di-set (start_url /?pwa=1 atau pwa_head.php standalone detection)
if (!empty($_COOKIE['pwa_app'])) {
    return;
}

// ═══ STICKY ENTRY POINT ═══
$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'];

$ref = '';
if (!empty($_GET['ref'])) $ref = preg_replace('/[^A-Za-z0-9_-]/','',$_GET['ref']);
elseif (!empty($_COOKIE['ref_code'])) $ref = preg_replace('/[^A-Za-z0-9_-]/','',$_COOKIE['ref_code']);

$entry = $_COOKIE['lx_entry'] ?? '';

if ($entry === 'invite' || $ref) {
    if ($ref) setcookie('ref_code', $ref, time()+2592000, '/', '', false, false);
    $url = "{$proto}://{$host}/invite.php";
    if ($ref) $url .= "?ref=".urlencode($ref);
    header("Location: $url");
} elseif ($entry === 'download') {
    header("Location: {$proto}://{$host}/download.php");
} else {
    header("Location: {$proto}://{$host}/gate.php");
}
exit;
