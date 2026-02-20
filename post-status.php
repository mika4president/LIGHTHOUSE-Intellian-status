<?php
/**
 * Ontvangt status-POST van lighthouse.py / lh4.py en slaat deze op per scheepsnaam.
 * Verwachte body: JSON met o.a. "ship", "last_update", "devices" (en optioneel "ship_position").
 *
 * URL: POST naar /post-status.php
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

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (json_last_error() !== JSON_ERROR_NONE || !isset($data['ship']) || !is_array($data['devices'] ?? null)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Ongeldige JSON of ontbrekende velden: ship, devices']);
    exit;
}

$dataDir = __DIR__ . DIRECTORY_SEPARATOR . 'data';
if (!is_dir($dataDir)) {
    if (!@mkdir($dataDir, 0777, true)) {
        http_response_code(500);
        echo json_encode([
            'ok' => false,
            'error' => 'Data-directory kon niet worden aangemaakt.',
            'path' => $dataDir,
            'hint' => 'Op de server (links): cd naar de map van post-status.php, voer uit: mkdir -p data && chmod 777 data (of: sudo -u www-data mkdir -p data)'
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
        'hint' => 'Voer uit: chmod 777 data (of chown naar webserver-user, bijv. www-data)'
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

$ship = trim((string) $data['ship']);
if ($ship === '') {
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => 'Lege scheepsnaam']);
    exit;
}

// Alleen relevante velden bewaren
$all[$ship] = [
    'ship'          => $ship,
    'last_update'   => $data['last_update'] ?? date('Y-m-d H:i:s'),
    'ship_position' => $data['ship_position'] ?? null,
    'devices'       => $data['devices'],
];

$json = json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if (@file_put_contents($file, $json) === false) {
    http_response_code(500);
    $hint = file_exists($file) ? 'Bestand status.json niet beschrijfbaar.' : 'Kan geen bestand in data/ aanmaken.';
    echo json_encode(['ok' => false, 'error' => 'Kon status niet wegschrijven. ' . $hint]);
    exit;
}

http_response_code(200);
echo json_encode(['ok' => true, 'ship' => $ship]);
