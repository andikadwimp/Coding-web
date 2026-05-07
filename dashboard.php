<?php
// ═══════════════════════════════════════════════════════════════
// dashboard.php → ALIAS ke index.php
// Sebelum patch ini, dashboard.php adalah halaman terpisah untuk user
// yang sudah login. Sekarang index.php sudah handle kedua state
// (guest + logged-in). File ini sengaja di-keep biar semua link
// existing yang ngarah ke "dashboard.php" tetep jalan tanpa redirect.
// ═══════════════════════════════════════════════════════════════
require_once __DIR__.'/index.php';
