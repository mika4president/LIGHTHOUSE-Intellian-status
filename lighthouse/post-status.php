<?php
/**
 * Ontvangt status-POST van lighthouse.py / lh4.py en slaat deze op per scheepsnaam.
 * Verwachte body: JSON met o.a. "ship", "last_update", "devices" (en optioneel "ship_position").
 * Logt het WAN-IP van de verzendende server (X-Forwarded-For / X-Real-IP / REMOTE_ADDR).
 *
 * Bij 500: zorg dat de map data/ bestaat en beschrijfbaar is (chmod 755 data, eigenaar = webserver).
 */
@ini_set('display_errors', 0);
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Alleen POST toegestaan']);
    exit;
}

/**
 * Bepaal het WAN-/client-IP (achter proxy of direct).
 */
function get_client_wan_ip() {
    $headers = [
        'HTTP_CF_CONNECTING_IP',   // Cloudflare
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_REAL_IP',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR',
    ];
    foreach ($headers as $h) {
        if (empty($_SERVER[$h])) {
            continue;
        }
        $val = trim($_SERVER[$h]);
        if ($val === '') {
            continue;
        }
        // X-Forwarded-For kan "client, proxy1, proxy2" zijn
        if (strpos($val, ',') !== false) {
            $val = trim(explode(',', $val)[0]);
        }
        if (filter_var($val, FILTER_VALIDATE_IP)) {
            return $val;
        }
    }
    return isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : null;
}

$raw = file_get_contents('php://input');
if ($raw === false || $raw === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Lege body']);
    exit;
}

$data = json_decode($raw, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Ongeldige JSON: ' . json_last_error_msg()]);
    exit;
}

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Body moet een JSON-object zijn']);
    exit;
}

if (!isset($data['ship']) || !is_string($data['ship'])) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Ontbrekend of ongeldig veld: ship']);
    exit;
}

$devices = $data['devices'] ?? null;
if (!is_array($devices)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Ontbrekend of ongeldig veld: devices (moet een object/array zijn)']);
    exit;
}

$dataDir = __DIR__ . DIRECTORY_SEPARATOR . 'data';
if (!is_dir($dataDir)) {
    if (!@mkdir($dataDir, 0775, true)) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'error' => 'Data-directory kon niet worden aangemaakt.',
            'path' => $dataDir,
            'hint' => 'Op de server: mkdir -p ' . $dataDir . ' && chmod 775 ' . $dataDir . ' && chown www-data ' . $dataDir
        ]);
        exit;
    }
}
if (!is_writable($dataDir)) {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'error' => 'Map data/ is niet beschrijfbaar.',
        'path' => $dataDir,
        'hint' => 'chmod 775 ' . $dataDir . ' en/of chown www-data ' . $dataDir
    ]);
    exit;
}

$file = $dataDir . '/status.json';
$all = [];
if (file_exists($file)) {
    $content = @file_get_contents($file);
    if ($content !== false) {
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            $all = $decoded;
        }
    }
}

// Scheepsnaam: trim, max 200 tekens, geen null-bytes
$ship = trim((string) $data['ship']);
$ship = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $ship);
if (mb_strlen($ship) > 200) {
    $ship = mb_substr($ship, 0, 200);
}
if ($ship === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Lege scheepsnaam']);
    exit;
}

// Normaliseer devices: alleen naam => {ip, status} bewaren
$devicesClean = [];
foreach ($devices as $devName => $dev) {
    if (!is_array($dev)) {
        continue;
    }
    $key = is_string($devName) ? trim($devName) : (string) $devName;
    if ($key === '' || mb_strlen($key) > 255) {
        continue;
    }
    $devicesClean[$key] = [
        'ip'     => isset($dev['ip']) ? trim((string) $dev['ip']) : '',
        'status' => isset($dev['status']) ? trim((string) $dev['status']) : '',
    ];
}

$wanIp = get_client_wan_ip();
$lastUpdate = isset($data['last_update']) && is_string($data['last_update'])
    ? trim($data['last_update'])
    : date('Y-m-d H:i:s');
$shipPosition = null;
if (isset($data['ship_position']) && (is_string($data['ship_position']) || is_numeric($data['ship_position']))) {
    $shipPosition = trim((string) $data['ship_position']);
    if ($shipPosition === '') {
        $shipPosition = null;
    }
}

$all[$ship] = [
    'ship'          => $ship,
    'last_update'   => $lastUpdate,
    'source_ip'     => $wanIp,
    'ship_position' => $shipPosition,
    'devices'       => $devicesClean,
];

$json = json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if ($json === false) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Kon data niet encoderen']);
    exit;
}
if (@file_put_contents($file, $json) === false) {
    http_response_code(500);
    $hint = file_exists($file) ? 'Bestand status.json niet beschrijfbaar.' : 'Kan geen bestand in data/ aanmaken.';
    echo json_encode(['ok' => false, 'error' => 'Kon status niet wegschrijven. ' . $hint]);
    exit;
}

http_response_code(200);
echo json_encode(['ok' => true, 'ship' => $ship, 'source_ip' => $wanIp]);
