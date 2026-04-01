<?php

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit(1);
}

require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/app/checker.php';
require_once __DIR__ . '/app/mailer.php';

$monitors = db_all('SELECT * FROM monitors WHERE enabled = 1');
$now      = time();

foreach ($monitors as $monitor) {
    // For HTTP/TCP: skip if interval hasn't elapsed since last check
    if ($monitor['type'] !== 'agent') {
        $lastChecked = (int) ($monitor['last_checked'] ?? 0);
        $interval    = (int) ($monitor['interval_sec'] ?? 60);
        if ($now - $lastChecked < $interval) {
            continue;
        }
    }

    $result = check_monitor($monitor);
    record_result((int) $monitor['id'], $result);

    $label = strtoupper($result['status']);
    $ms    = $result['response_ms'] !== null ? ' (' . format_ms($result['response_ms']) . ')' : '';
    echo date('Y-m-d H:i:s') . " [{$label}]{$ms} {$monitor['name']} — {$result['detail']}\n";
}
