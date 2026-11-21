<?php
// proxy.php — Puente HTTPS <-> HTTP con CORS para streams HLS
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

$u = isset($_GET['u']) ? $_GET['u'] : '';
if (!$u || !preg_match('#^https?://#i', $u)) { http_response_code(400); exit('URL inválida'); }

// Solo permitir tu servidor IPTV (seguridad anti-abuso)
$allowedHosts = ['15.204.196.147'];
$host = parse_url($u, PHP_URL_HOST);
if (!in_array($host, $allowedHosts)) { http_response_code(403); exit('Host no permitido'); }

$ctx = stream_context_create(['http' => ['timeout' => 15, 'follow_location' => 1]]);

if (strpos($u, '.m3u8') !== false) {
    // Es un manifiesto: lo reescribimos para que los segmentos también pasen por el proxy
    $txt = @file_get_contents($u, false, $ctx);
    if ($txt === false) { http_response_code(502); exit('Stream caído'); }
    $base = preg_replace('#/[^/]*$#', '/', $u); // carpeta del m3u8
    $self = 'https://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/proxy.php';
    $out = '';
    foreach (explode("\n", $txt) as $line) {
        $t = trim($line);
        if ($t === '' || $t[0] === '#') { $out .= $line . "\n"; continue; }
        $abs = (preg_match('#^https?://#i', $t)) ? $t : $base . $t;
        $out .= $self . '?u=' . urlencode($abs) . "\n";
    }
    header('Content-Type: application/vnd.apple.mpegurl');
    echo $out;
} else {
    // Es un segmento .ts / .aac / imagen: pasamanos directo
    $body = @file_get_contents($u, false, $ctx);
    if ($body === false) { http_response_code(502); exit; }
    header('Content-Type: video/mp2t');
    echo $body;
}
