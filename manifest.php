<?php
// Manifest PWA — PRIMARY icon WAJIB pake /icon-192.png + /icon-512.png di root
// Karena Chrome STRICT: kalau icon size beda dari yg di-declare (192/512), manifest dianggap invalid
// → beforeinstallprompt ga fire → install ga muncul
//
// Jadi: icon dari setting user (pwa_icon) cuma dipake sebagai EXTRA icon, bukan primary
header('Content-Type: application/manifest+json; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');

$host = $_SERVER['HTTP_HOST'] ?? 'App';
$parts = explode('.', $host);
$defaultName = strtoupper($parts[0] ?? 'App');

$sn = $defaultName;
$bg = '#0c2f2e';
$extraIcon = ''; // logo upload user — optional

// Coba load settings DB
try{
    require_once __DIR__.'/includes/config.php';
    if(isset($db)){
        $sets = [];
        try{
            $st = $db->query("SELECT `key`,`value` FROM settings");
            foreach($st->fetchAll() as $r) $sets[$r['key']] = $r['value'];
        }catch(Exception $e){}

        $siteName = trim($sets['site_name'] ?? '');
        if($siteName !== '' && $siteName !== 'Situs') $sn = $siteName;

        if(!empty($sets['theme_bg'])) $bg = $sets['theme_bg'];

        // Optional extra icon dari setting (TIDAK menggantikan icon-192/512 standar)
        $candidateIcon = trim($sets['pwa_icon'] ?? '');
        if($candidateIcon === '') $candidateIcon = trim($sets['logo_url'] ?? '');
        if($candidateIcon !== ''){
            if(strpos($candidateIcon, 'http') !== 0 && $candidateIcon[0] !== '/'){
                $candidateIcon = '/'.$candidateIcon;
            }
            $extraIcon = $candidateIcon;
        }
    }
}catch(Exception $e){}

$shortName = function_exists('mb_substr') ? mb_substr($sn, 0, 12) : substr($sn, 0, 12);

// === PRIMARY ICONS ===
// WAJIB: icon-192.png + icon-512.png di root server
// Kalau salah satu ga ada → warning di header (browser console ada clue)
$iconRoot = __DIR__;
$has192 = file_exists($iconRoot.'/icon-192.png');
$has512 = file_exists($iconRoot.'/icon-512.png');

$icons = [];
if($has192){
    $icons[] = ["src"=>"/icon-192.png","sizes"=>"192x192","type"=>"image/png","purpose"=>"any"];
    $icons[] = ["src"=>"/icon-192.png","sizes"=>"192x192","type"=>"image/png","purpose"=>"maskable"];
}
if($has512){
    $icons[] = ["src"=>"/icon-512.png","sizes"=>"512x512","type"=>"image/png","purpose"=>"any"];
    $icons[] = ["src"=>"/icon-512.png","sizes"=>"512x512","type"=>"image/png","purpose"=>"maskable"];
}

// Fallback: kalau icon-192/512 ga ada di root, pake extraIcon (DB) dengan sizes="any"
// Supaya manifest tetap valid & install prompt bisa muncul
if(empty($icons) && $extraIcon !== ''){
    $ext = strtolower(pathinfo(parse_url($extraIcon, PHP_URL_PATH) ?: $extraIcon, PATHINFO_EXTENSION));
    $mime = 'image/png';
    if($ext === 'jpg' || $ext === 'jpeg') $mime = 'image/jpeg';
    elseif($ext === 'webp') $mime = 'image/webp';
    elseif($ext === 'svg') $mime = 'image/svg+xml';
    $icons[] = ["src"=>$extraIcon,"sizes"=>"192x192","type"=>$mime,"purpose"=>"any"];
    $icons[] = ["src"=>$extraIcon,"sizes"=>"512x512","type"=>$mime,"purpose"=>"any"];
    $icons[] = ["src"=>$extraIcon,"sizes"=>"any","type"=>$mime,"purpose"=>"maskable"];
}

// Last resort: kalau semuanya ga ada, pake data URI biar manifest valid
// (install prompt tetep muncul, tapi icon bakal default/kosong)
if(empty($icons)){
    // 1x1 blue PNG base64 — minimal valid icon
    $fallbackData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNgYAAAAAMAASsJTYQAAAAASUVORK5CYII=';
    $icons[] = ["src"=>$fallbackData,"sizes"=>"192x192","type"=>"image/png","purpose"=>"any"];
    $icons[] = ["src"=>$fallbackData,"sizes"=>"512x512","type"=>"image/png","purpose"=>"any"];
}

// Tambah extra icon dari DB sebagai bonus (kalau icon-192/512 udah ada)
if(($has192 || $has512) && $extraIcon !== ''){
    $ext = strtolower(pathinfo(parse_url($extraIcon, PHP_URL_PATH) ?: $extraIcon, PATHINFO_EXTENSION));
    $mime = 'image/png';
    if($ext === 'jpg' || $ext === 'jpeg') $mime = 'image/jpeg';
    elseif($ext === 'webp') $mime = 'image/webp';
    elseif($ext === 'svg') $mime = 'image/svg+xml';
    $icons[] = ["src"=>$extraIcon,"sizes"=>"any","type"=>$mime,"purpose"=>"any"];
}

$manifest = [
    "name"=>$sn,
    "short_name"=>$shortName,
    "id"=>"/",
    "description"=>"Platform gaming online $sn",
    "start_url"=>"/index.php?pwa=1",
    "scope"=>"/",
    "display"=>"standalone",
    "display_override"=>["standalone","minimal-ui"],
    "orientation"=>"portrait",
    "background_color"=>$bg,
    "theme_color"=>$bg,
    "lang"=>"id",
    "dir"=>"ltr",
    "categories"=>["games","entertainment"],
    "prefer_related_applications"=>false,
    "icons"=>$icons,
];

echo json_encode($manifest, JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT);
exit;
