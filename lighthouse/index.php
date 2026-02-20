<?php
/**
 * Statusindex: toont per schip alleen de laatste status met hoe lang geleden geüpdatet en WAN-IP.
 */
$dataDir = __DIR__ . DIRECTORY_SEPARATOR . 'data';
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

function time_ago_nl($datetimeStr) {
    if ($datetimeStr === null || $datetimeStr === '') {
        return '–';
    }
    $ts = @strtotime($datetimeStr);
    if ($ts === false) {
        return htmlspecialchars($datetimeStr);
    }
    $diff = time() - $ts;
    if ($diff < 0) {
        return 'zojuist';
    }
    if ($diff < 60) {
        return 'zojuist';
    }
    if ($diff < 3600) {
        $m = (int) floor($diff / 60);
        return $m . ' minuten geleden';
    }
    if ($diff < 86400) {
        $u = (int) floor($diff / 3600);
        return $u . ' uur geleden';
    }
    if ($diff < 604800) {
        $d = (int) floor($diff / 86400);
        return $d . ' dag' . ($d !== 1 ? 'en' : '') . ' geleden';
    }
    if ($diff < 2592000) {
        $w = (int) floor($diff / 604800);
        return $w . ' week geleden';
    }
    return date('d-m-Y H:i', $ts);
}

$pageTitle = 'Lighthouse – Status per schip';
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?></title>
    <style>
        :root {
            --bg: #0c1219;
            --card: #151d28;
            --card-hover: #1a2532;
            --text: #e8eef4;
            --muted: #7d8a99;
            --accent: #58a6ff;
            --accent-soft: rgba(88, 166, 255, 0.15);
            --ok: #3fb950;
            --warn: #d29922;
            --err: #f85149;
            --border: #2d3748;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--bg);
            color: var(--text);
            margin: 0;
            padding: 1.5rem;
            line-height: 1.5;
        }
        .wrap { max-width: 720px; margin: 0 auto; }
        h1 {
            font-size: 1.6rem;
            font-weight: 600;
            margin: 0 0 0.35rem;
            color: var(--text);
        }
        .sub {
            color: var(--muted);
            font-size: 0.9rem;
            margin-bottom: 1.5rem;
        }
        .sub a { color: var(--accent); text-decoration: none; }
        .sub a:hover { text-decoration: underline; }
        .ships { display: flex; flex-direction: column; gap: 0.75rem; }
        .ship-card {
            display: block;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 10px;
            padding: 1rem 1.25rem;
            text-decoration: none;
            color: inherit;
            transition: background 0.15s, border-color 0.15s;
        }
        .ship-card:hover {
            background: var(--card-hover);
            border-color: var(--accent);
        }
        .ship-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 0.5rem 1rem;
        }
        .ship-name { font-size: 1.15rem; font-weight: 600; }
        .ship-meta {
            display: flex;
            align-items: center;
            gap: 1rem;
            font-size: 0.85rem;
            color: var(--muted);
        }
        .ship-meta span { display: inline-flex; align-items: center; gap: 0.35rem; }
        .ship-meta .ip { font-family: ui-monospace, monospace; color: var(--accent); }
        .empty {
            color: var(--muted);
            padding: 2.5rem 1rem;
            text-align: center;
            border: 1px dashed var(--border);
            border-radius: 10px;
        }
        .empty code { background: var(--card); padding: 0.2rem 0.5rem; border-radius: 4px; }
        .refresh { font-size: 0.85rem; color: var(--muted); margin-top: 1.5rem; }
        .refresh a { color: var(--accent); }
    </style>
</head>
<body>
    <div class="wrap">
        <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
        <p class="sub">Laatste status per schip. <a href="<?php echo htmlspecialchars(dirname($_SERVER['SCRIPT_NAME']) . '/../index.php'); ?>">Volledig overzicht</a></p>

        <?php if (empty($ships)): ?>
            <p class="empty">Nog geen status ontvangen. Zet op elk schip <code>BACKEND_URL</code> naar <code>…/lighthouse/post-status.php</code> en run het script (of cron).</p>
        <?php else: ?>
            <div class="ships">
                <?php foreach ($ships as $shipData): ?>
                    <?php
                    $name = isset($shipData['ship']) ? $shipData['ship'] : 'Onbekend';
                    $lastUpdate = $shipData['last_update'] ?? null;
                    $sourceIp = $shipData['source_ip'] ?? null;
                    $shipSlug = rawurlencode($name);
                    ?>
                    <a href="ship.php?ship=<?php echo $shipSlug; ?>" class="ship-card">
                        <div class="ship-row">
                            <span class="ship-name"><?php echo htmlspecialchars($name); ?></span>
                            <div class="ship-meta">
                                <span title="Laatste update"><?php echo time_ago_nl($lastUpdate); ?></span>
                                <?php if ($sourceIp !== null && $sourceIp !== ''): ?>
                                    <span class="ip" title="WAN-IP"><?php echo htmlspecialchars($sourceIp); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <p class="refresh">Pagina <a href="<?php echo htmlspecialchars($_SERVER['REQUEST_URI'] ?? 'index.php'); ?>">vernieuwen</a></p>
    </div>
</body>
</html>
