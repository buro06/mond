<?php

$slug = $params['slug'] ?? '';
$company = db_row('SELECT * FROM companies WHERE slug = ?', [$slug]);

if (!$company) {
    http_response_code(404);
    page_start('Not Found', true);
    echo '<h1>Status Page Not Found</h1><p>No company with that slug exists.</p>';
    page_end();
    exit;
}

$monitors = db_all(
        'SELECT * FROM monitors WHERE company_id = ? AND enabled = 1 ORDER BY name',
        [$company['id']]
);

$history = [];
$uptime = [];
$since24 = time() - 86400;

foreach ($monitors as $m) {
    $history[$m['id']] = db_all(
            'SELECT status FROM check_results WHERE monitor_id = ? ORDER BY checked_at DESC LIMIT 90',
            [$m['id']]
    );
    $row = db_row(
            'SELECT COUNT(*) AS total,
                SUM(CASE WHEN status = \'up\' THEN 1 ELSE 0 END) AS ups
         FROM check_results
         WHERE monitor_id = ? AND checked_at >= ?',
            [$m['id'], $since24]
    );
    $uptime[$m['id']] = ($row && $row['total'] > 0)
            ? number_format($row['ups'] / $row['total'] * 100, 1)
            : null;
}

$hasDown = false;
foreach ($monitors as $m) {
    if ($m['current_status'] === 'down') {
        $hasDown = true;
        break;
    }
}
$overallOk = !empty($monitors) && !$hasDown;

page_start(h($company['name']) . ' Status', true);
?>
<h1><?= h($company['name']) ?></h1>

<div class="panel" style="text-align:center;padding:18px;margin-bottom:20px;font-size:16px">
    <?php if (empty($monitors)): ?>
        <span class="unknown">No monitors configured.</span>
    <?php elseif ($overallOk): ?>
        <span class="up">&#x2714;&nbsp; All systems operational</span>
    <?php else: ?>
        <span class="down">&#x2716;&nbsp; One or more systems are down</span>
    <?php endif; ?>
</div>

<?php if (!empty($monitors)): ?>
    <table>
        <tr>
            <th>Monitor</th>
            <th>Type</th>
            <th>Status</th>
            <th>Uptime (24h)</th>
            <th>Last Checked</th>
            <th>History <span class="muted" style="font-weight:normal;font-size:11px">(last 90 checks)</span></th>
        </tr>
        <?php foreach ($monitors as $m): ?>
            <?php $bars = array_reverse($history[$m['id']]); ?>
            <tr>
                <td><?= h($m['name']) ?></td>
                <td><?= strtoupper($m['type']) ?></td>
                <td><span class="badge badge-<?= $m['current_status'] ?>"><?= $m['current_status'] ?></span></td>
                <td><?= $uptime[$m['id']] !== null ? $uptime[$m['id']] . '%' : '<span class="muted">N/A</span>' ?></td>
                <td class="muted"><?= $m['last_checked'] ? time_ago((int)$m['last_checked']) : 'never' ?></td>
                <td>
                    <div class="history">
                        <?php if (empty($bars)): ?>
                            <span class="muted" style="font-size:12px">no data</span>
                        <?php else: ?>
                            <?php foreach ($bars as $b): ?>
                                <span class="bar bar-<?= $b['status'] ?>" title="<?= $b['status'] ?>"></span>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>

<p class="muted" style="margin-top:12px">Last updated: <?= date('Y-m-d H:i:s') ?> UTC</p>
<?php page_end(); ?>
