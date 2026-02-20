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
        h1 {
            font-size: 1.5rem;
            font-weight: 600;
            margin: 0 0 1rem;
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
    </style>
</head>
<body>
    <div class="status-page">
    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
    <p class="meta">Overzicht van alle schepen en hun Intellian-schotelstatus. <a href="lighthouse/">Status per schip (laatste + hoe lang geleden)</a></p>

    <?php if (empty($ships)): ?>
        <p class="empty">Nog geen status ontvangen. Zet op elk schip <code>BACKEND_URL</code> naar deze server (<code>lighthouse/post-status.php</code>) en run het script (of cron). <a href="lighthouse/">Status per schip</a></p>
    <?php else: ?>
        <div class="ships">
            <?php foreach ($ships as $shipData): ?>
                <?php
                $name = isset($shipData['ship']) ? $shipData['ship'] : 'Onbekend';
                $lastUpdate = isset($shipData['last_update']) ? $shipData['last_update'] : '–';
                $position = isset($shipData['ship_position']) && $shipData['ship_position'] !== null && $shipData['ship_position'] !== '' ? $shipData['ship_position'] : null;
                $sourceIp = isset($shipData['source_ip']) && $shipData['source_ip'] !== '' ? $shipData['source_ip'] : null;
                $devices = isset($shipData['devices']) && is_array($shipData['devices']) ? $shipData['devices'] : [];
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
                    </div>
                </section>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <p class="refresh">Pagina vernieuwen: <a href="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'index.php'); ?>">herladen</a> · <a href="lighthouse/">Status per schip</a></p>
    </div>
</body>
</html>
