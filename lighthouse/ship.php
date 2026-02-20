<?php
/**
 * Statuspagina voor één schip (laatste opgeslagen status).
 */
$dataDir = __DIR__ . DIRECTORY_SEPARATOR . 'data';
$file = $dataDir . '/status.json';
$shipName = isset($_GET['ship']) ? trim((string) $_GET['ship']) : '';
$shipData = null;

if ($shipName !== '' && file_exists($file)) {
    $content = @file_get_contents($file);
    if ($content !== false) {
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            foreach ($decoded as $name => $data) {
                if (rawurlencode($name) === $shipName || $name === $shipName) {
                    $shipData = $data;
                    $shipName = $name;
                    break;
                }
            }
        }
    }
}

if ($shipData === null) {
    http_response_code(404);
    $pageTitle = 'Schip niet gevonden';
} else {
    $pageTitle = 'Lighthouse – ' . htmlspecialchars($shipData['ship'] ?? $shipName);
}

function time_ago_nl($datetimeStr) {
    if ($datetimeStr === null || $datetimeStr === '') return '–';
    $ts = @strtotime($datetimeStr);
    if ($ts === false) return $datetimeStr;
    $diff = time() - $ts;
    if ($diff < 60) return 'zojuist';
    if ($diff < 3600) return (int) floor($diff / 60) . ' minuten geleden';
    if ($diff < 86400) return (int) floor($diff / 3600) . ' uur geleden';
    if ($diff < 604800) return (int) floor($diff / 86400) . ' dagen geleden';
    return date('d-m-Y H:i', $ts);
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <style>
        :root {
            --bg: #0c1219;
            --card: #1a2332;
            --text: #e6edf3;
            --muted: #8b949e;
            --ok: #3fb950;
            --warn: #d29922;
            --err: #f85149;
            --border: #30363d;
            --accent: #58a6ff;
        }
        * { box-sizing: border-box; }
        body { font-family: 'Segoe UI', system-ui, sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 1.5rem; line-height: 1.5; }
        .wrap { max-width: 900px; margin: 0 auto; }
        h1 { font-size: 1.5rem; font-weight: 600; margin: 0 0 1rem; }
        .back { font-size: 0.9rem; margin-bottom: 1rem; }
        .back a { color: var(--accent); text-decoration: none; }
        .meta { color: var(--muted); font-size: 0.875rem; margin-bottom: 1rem; }
        .ship-card { background: var(--card); border: 1px solid var(--border); border-radius: 8px; overflow: hidden; }
        .ship-header { padding: 0.75rem 1rem; border-bottom: 1px solid var(--border); font-weight: 600; }
        .devices { padding: 0.75rem 1rem; }
        table { width: 100%; border-collapse: collapse; font-size: 0.9rem; }
        th, td { text-align: left; padding: 0.4rem 0.5rem; border-bottom: 1px solid var(--border); }
        th { color: var(--muted); font-weight: 500; }
        tr:last-child td { border-bottom: none; }
        .status-tracking { color: var(--ok); }
        .status-idle, .status-unlock { color: var(--muted); }
        .status-searching, .status-wrapping { color: var(--warn); }
        .status-offline, .status-error, .status-unknown { color: var(--err); }
        .empty { color: var(--muted); padding: 1rem; text-align: center; }
    </style>
</head>
<body>
    <div class="wrap">
        <p class="back"><a href="index.php">← Statusindex</a></p>

        <?php if ($shipData === null): ?>
            <h1>Schip niet gevonden</h1>
            <p class="meta">Het opgevraagde schip staat niet in de status of de link is ongeldig.</p>
        <?php else: ?>
            <h1><?php echo htmlspecialchars($shipData['ship']); ?></h1>
            <p class="meta">
                Laatste update: <?php echo htmlspecialchars($shipData['last_update'] ?? '–'); ?>
                (<?php echo time_ago_nl($shipData['last_update'] ?? null); ?>)
                <?php if (!empty($shipData['source_ip'])): ?>
                    · WAN-IP: <code><?php echo htmlspecialchars($shipData['source_ip']); ?></code>
                <?php endif; ?>
                <?php if (!empty($shipData['ship_position'])): ?>
                    · Positie: <?php echo htmlspecialchars($shipData['ship_position']); ?>
                <?php endif; ?>
            </p>
            <div class="ship-card">
                <div class="devices">
                    <?php
                    $devices = isset($shipData['devices']) && is_array($shipData['devices']) ? $shipData['devices'] : [];
                    if (empty($devices)): ?>
                        <p class="empty">Geen devices</p>
                    <?php else: ?>
                        <table>
                            <thead><tr><th>Schotel</th><th>IP</th><th>Status</th></tr></thead>
                            <tbody>
                                <?php foreach ($devices as $devName => $dev): ?>
                                    <?php
                                    $status = isset($dev['status']) ? $dev['status'] : '–';
                                    $cls = 'status-tracking';
                                    if (stripos($status, 'TRACKING') !== false) $cls = 'status-tracking';
                                    elseif (stripos($status, 'IDLE') !== false || stripos($status, 'UNLOCK') !== false) $cls = 'status-idle';
                                    elseif (stripos($status, 'SEARCHING') !== false || stripos($status, 'WRAPPING') !== false) $cls = 'status-searching';
                                    else $cls = 'status-offline';
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($devName); ?></td>
                                        <td><?php echo htmlspecialchars($dev['ip'] ?? '–'); ?></td>
                                        <td class="<?php echo $cls; ?>"><?php echo htmlspecialchars($status); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
