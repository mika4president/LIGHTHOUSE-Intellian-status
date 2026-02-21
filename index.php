<?php
/**
 * Statuspagina: toont alle schepen en hun schotelstatus uit data/status.json.
 */
$dataDir = __DIR__ . '/data';
$file = $dataDir . '/status.json';
$ships = [];

if (file_exists($file)) {
    $content = @file_get_contents($file);
    if ($content !== false) {
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            $ships = $decoded;
        }
    }
}

// Sorteer op scheepsnaam
ksort($ships);

$pageTitle = 'Lighthouse – Schotelstatus';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
    <style>
        :root {
            --bg: #0f1419;
            --card: #1a2332;
            --text: #e6edf3;
            --muted: #8b949e;
            --ok: #3fb950;
            --warn: #d29922;
            --err: #f85149;
            --border: #30363d;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 1.5rem;
            line-height: 1.5;
        }
        .page-header {
            display: flex;
            align-items: center;
            gap: 0.6rem;
            margin-bottom: 1rem;
        }
        .page-header .app-icon {
            width: 28px;
            height: 28px;
            object-fit: contain;
            transform: rotate(-6deg);
            opacity: 0.85;
            flex-shrink: 0;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0;
            color: var(--text);
        }
        .meta {
            color: var(--muted);
            font-size: 0.875rem;
            margin-bottom: 1.5rem;
        }
        .ships {
            display: grid;
            gap: 1rem;
            max-width: 900px;
        }
        .ship {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 8px;
            overflow: hidden;
        }
        .ship-header {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border);
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.5rem;
        }
        .ship-name { font-size: 1.1rem; }
        .ship-update {
            font-size: 0.8rem;
            font-weight: normal;
            color: var(--muted);
        }
        .ship-position {
            font-size: 0.8rem;
            color: var(--muted);
            width: 100%;
            margin-top: 0.25rem;
        }
        .devices {
            padding: 0.75rem 1rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.9rem;
        }
        th, td {
            text-align: left;
            padding: 0.4rem 0.5rem;
            border-bottom: 1px solid var(--border);
        }
        th {
            color: var(--muted);
            font-weight: 500;
        }
        tr:last-child td { border-bottom: none; }
        .status {
            font-weight: 500;
        }
        .status-tracking { color: var(--ok); }
        .status-idle, .status-unlock { color: var(--muted); }
        .status-searching, .status-wrapping { color: var(--warn); }
        .status-offline, .status-error, .status-unknown, .status-no.data { color: var(--err); }
        .empty {
            color: var(--muted);
            padding: 2rem;
            text-align: center;
        }
        .refresh {
            font-size: 0.85rem;
            color: var(--muted);
        }
        .refresh a { color: var(--muted); }
        .ship-map-wrap {
            margin-top: 0.75rem;
            border-radius: 6px;
            overflow: hidden;
            border: 1px solid var(--border);
        }
        .ship-map {
            width: 100%;
            height: 220px;
            background: var(--bg);
        }
        .line-of-sight {
            margin-top: 1rem;
            padding: 1rem;
            background: var(--bg);
            border-radius: 8px;
            border: 1px solid var(--border);
        }
        .line-of-sight h3 {
            font-size: 0.95rem;
            margin: 0 0 0.75rem;
            color: var(--muted);
        }
        .los-content {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
        }
        .los-compass {
            width: 140px;
            height: 140px;
            position: relative;
            flex-shrink: 0;
        }
        .los-compass svg {
            width: 100%;
            height: 100%;
        }
        .los-values {
            font-size: 0.9rem;
        }
        .los-values p {
            margin: 0.25rem 0;
        }
        .los-values strong {
            color: var(--ok);
        }
    </style>
</head>
<body>
    <div class="status-page">
    <header class="page-header">
        <?php if (file_exists(__DIR__ . '/appicon.png')): ?>
        <img src="appicon.png" alt="" class="app-icon" width="28" height="28">
        <?php endif; ?>
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
    </header>
    <p class="meta">Overzicht van alle schepen en hun Intellian-schotelstatus. <a href="lighthouse/">Status per schip (laatste + hoe lang geleden)</a></p>

    <?php if (empty($ships)): ?>
        <p class="empty">Nog geen status ontvangen. Zet op elk schip <code>BACKEND_URL</code> naar deze server (<code>lighthouse/post-status.php</code>) en run het script (of cron). <a href="lighthouse/">Status per schip</a></p>
    <?php else: ?>
        <div class="ships">
            <?php $shipIndex = 0; foreach ($ships as $shipData): ?>
                <?php
                $name = isset($shipData['ship']) ? $shipData['ship'] : 'Onbekend';
                $lastUpdate = isset($shipData['last_update']) ? $shipData['last_update'] : '–';
                $position = isset($shipData['ship_position']) && $shipData['ship_position'] !== null && $shipData['ship_position'] !== '' ? $shipData['ship_position'] : null;
                $sourceIp = isset($shipData['source_ip']) && $shipData['source_ip'] !== '' ? $shipData['source_ip'] : null;
                $devices = isset($shipData['devices']) && is_array($shipData['devices']) ? $shipData['devices'] : [];
                $coords = null;
                if ($position !== null && $position !== '' && stripos($position, 'N/A') === false) {
                    $parts = array_map('trim', explode(',', $position));
                    if (count($parts) >= 2) {
                        $lat = filter_var($parts[0], FILTER_VALIDATE_FLOAT);
                        $lon = filter_var($parts[1], FILTER_VALIDATE_FLOAT);
                        if ($lat !== false && $lon !== false && $lat >= -90 && $lat <= 90 && $lon >= -180 && $lon <= 180) {
                            $coords = ['lat' => (float)$lat, 'lon' => (float)$lon];
                        }
                    }
                }
                ?>
                <section class="ship">
                    <div class="ship-header">
                        <span class="ship-name"><?php echo htmlspecialchars($name); ?></span>
                        <span class="ship-update">Laatste update: <?php echo htmlspecialchars($lastUpdate); ?></span>
                        <?php if ($sourceIp !== null): ?>
                            <span class="ship-position">WAN-IP: <?php echo htmlspecialchars($sourceIp); ?></span>
                        <?php endif; ?>
                        <?php if ($position !== null): ?>
                            <span class="ship-position">Positie: <?php echo htmlspecialchars($position); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="devices">
                        <?php if (empty($devices)): ?>
                            <p class="empty">Geen devices</p>
                        <?php else: ?>
                            <table>
                                <thead>
                                    <tr>
                                        <th>Schotel</th>
                                        <th>IP</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($devices as $devName => $dev): ?>
                                        <?php
                                        $status = isset($dev['status']) ? $dev['status'] : '–';
                                        $statusClass = 'status';
                                        if (stripos($status, 'TRACKING') !== false) $statusClass .= ' status-tracking';
                                        elseif (stripos($status, 'IDLE') !== false || stripos($status, 'UNLOCK') !== false) $statusClass .= ' status-idle';
                                        elseif (stripos($status, 'SEARCHING') !== false || stripos($status, 'WRAPPING') !== false) $statusClass .= ' status-searching';
                                        else $statusClass .= ' status-offline';
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($devName); ?></td>
                                            <td><?php echo htmlspecialchars(isset($dev['ip']) ? $dev['ip'] : '–'); ?></td>
                                            <td class="<?php echo $statusClass; ?>"><?php echo htmlspecialchars($status); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                        <?php if ($coords !== null): ?>
                            <div class="ship-map-wrap">
                                <div id="ship-map-<?php echo $shipIndex; ?>" class="ship-map" data-lat="<?php echo $coords['lat']; ?>" data-lon="<?php echo $coords['lon']; ?>" data-name="<?php echo htmlspecialchars($name); ?>"></div>
                            </div>
                            <div class="line-of-sight" id="los-<?php echo $shipIndex; ?>" data-lat="<?php echo $coords['lat']; ?>" data-lon="<?php echo $coords['lon']; ?>">
                                <h3>Line-of-sight Astra 1 (19.2°E)</h3>
                                <div class="los-content">
                                    <div class="los-compass">
                                        <svg viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
                                            <circle cx="50" cy="50" r="45" fill="none" stroke="var(--border)" stroke-width="1.5"/>
                                            <text x="50" y="18" text-anchor="middle" fill="var(--muted)" font-size="12" font-weight="600">N</text>
                                            <text x="82" y="54" text-anchor="middle" fill="var(--muted)" font-size="10">E</text>
                                            <text x="50" y="88" text-anchor="middle" fill="var(--muted)" font-size="10">Z</text>
                                            <text x="16" y="54" text-anchor="middle" fill="var(--muted)" font-size="10">W</text>
                                            <line id="los-needle-<?php echo $shipIndex; ?>" x1="50" y1="50" x2="50" y2="15" stroke="var(--ok)" stroke-width="2.5" stroke-linecap="round"/>
                                        </svg>
                                    </div>
                                    <div class="los-values">
                                        <p><strong id="los-az-<?php echo $shipIndex; ?>">–</strong> azimuth (richting schotel)</p>
                                        <p><strong id="los-el-<?php echo $shipIndex; ?>">–</strong> elevatie (hoogte boven horizon)</p>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            <?php $shipIndex++; endforeach; ?>
        </div>
    <?php endif; ?>

    <p class="refresh">Pagina vernieuwen: <a href="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'index.php'); ?>">herladen</a> · <a href="lighthouse/">Status per schip</a></p>
    </div>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
    document.querySelectorAll('.ship-map').forEach(function(el) {
        var lat = parseFloat(el.getAttribute('data-lat'));
        var lon = parseFloat(el.getAttribute('data-lon'));
        var name = el.getAttribute('data-name') || 'Schip';
        if (isNaN(lat) || isNaN(lon)) return;
        var map = L.map(el).setView([lat, lon], 17);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(map);
        L.marker([lat, lon]).addTo(map).bindPopup(name);
    });

    (function() {
        var ASTRA1_LON = 19.2;
        var R_earth = 6371;
        var R_sat = 42164;
        function toRad(d) { return d * Math.PI / 180; }
        function toDeg(r) { return r * 180 / Math.PI; }
        function elevationAzimuth(lat, lon) {
            var latRad = toRad(lat);
            var dLonRad = toRad(ASTRA1_LON - lon);
            var cosLat = Math.cos(latRad);
            var cosD = Math.cos(dLonRad);
            var ratio = R_earth / R_sat;
            var N = cosLat * cosD - ratio;
            var D = Math.sqrt(1 - cosLat * cosLat * cosD * cosD);
            var elRad = Math.atan2(N, D);
            var el = toDeg(elRad);
            var tanEl = Math.tan(elRad);
            var azRad = Math.atan2(Math.sin(dLonRad), cosLat * tanEl / cosD - Math.tan(latRad));
            var az = toDeg(azRad);
            if (az < 0) az += 360;
            return { elevation: el, azimuth: az };
        }
        document.querySelectorAll('.line-of-sight').forEach(function(block) {
            var lat = parseFloat(block.getAttribute('data-lat'));
            var lon = parseFloat(block.getAttribute('data-lon'));
            if (isNaN(lat) || isNaN(lon)) return;
            var id = block.id.replace('los-', '');
            var needle = document.getElementById('los-needle-' + id);
            var azEl = document.getElementById('los-az-' + id);
            var elEl = document.getElementById('los-el-' + id);
            var angles = elevationAzimuth(lat, lon);
            if (needle) needle.setAttribute('transform', 'rotate(' + angles.azimuth + ' 50 50)');
            if (azEl) azEl.textContent = Math.round(angles.azimuth) + '°';
            if (elEl) {
                elEl.textContent = angles.elevation < 0 ? 'onder horizon (niet zichtbaar)' : Math.round(angles.elevation) + '°';
                if (angles.elevation < 0) elEl.style.color = 'var(--muted)';
            }
        });
    })();
    </script>
</body>
</html>
