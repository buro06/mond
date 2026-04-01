<?php

function check_http(string $url, int $timeout = 10): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_CONNECTTIMEOUT => $timeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 5,
        CURLOPT_USERAGENT      => 'monD/1.0',
        CURLOPT_SSL_VERIFYPEER => false,
    ]);

    $start    = microtime(true);
    curl_exec($ch);
    $ms       = (int) ((microtime(true) - $start) * 1000);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['status' => 'down', 'response_ms' => $ms, 'detail' => $error];
    }

    $up = $code >= 200 && $code < 400;
    return ['status' => $up ? 'up' : 'down', 'response_ms' => $ms, 'detail' => 'HTTP ' . $code];
}

function check_tcp(string $host, int $port, int $timeout = 5): array {
    $start = microtime(true);
    $fp    = @fsockopen($host, $port, $errno, $errstr, $timeout);
    $ms    = (int) ((microtime(true) - $start) * 1000);

    if ($fp) {
        fclose($fp);
        return ['status' => 'up', 'response_ms' => $ms, 'detail' => 'TCP OK'];
    }

    return ['status' => 'down', 'response_ms' => $ms, 'detail' => $errstr ?: 'Connection refused'];
}

function check_agent(array $monitor): array {
    $last    = (int) ($monitor['last_heartbeat'] ?? 0);
    $timeout = (int) ($monitor['agent_timeout_sec'] ?? 300);

    if ($last === 0) {
        return ['status' => 'down', 'response_ms' => null, 'detail' => 'No heartbeat received yet'];
    }

    $age = time() - $last;
    if ($age > $timeout) {
        return ['status' => 'down', 'response_ms' => null, 'detail' => 'Last heartbeat ' . time_ago($last)];
    }

    return ['status' => 'up', 'response_ms' => null, 'detail' => 'Last heartbeat ' . time_ago($last)];
}

function check_monitor(array $monitor): array {
    return match ($monitor['type']) {
        'http'  => check_http($monitor['target']),
        'tcp'   => check_tcp($monitor['tcp_host'], (int) $monitor['tcp_port']),
        'agent' => check_agent($monitor),
        default => ['status' => 'down', 'response_ms' => null, 'detail' => 'Unknown monitor type'],
    };
}

function get_notification_direction(int $monitorId, string $newStatus): ?string {
    $monitor   = db_row('SELECT current_status FROM monitors WHERE id = ?', [$monitorId]);
    $oldStatus = $monitor['current_status'] ?? 'unknown';

    if ($newStatus === $oldStatus) {
        return null;
    }

    $last = db_row(
        'SELECT direction FROM notification_log WHERE monitor_id = ? ORDER BY sent_at DESC LIMIT 1',
        [$monitorId]
    );

    if ($last && $last['direction'] === $newStatus) {
        return null;
    }

    return $newStatus;
}

function record_result(int $monitorId, array $result): void {
    $pdo = db();
    $now = time();

    $direction = get_notification_direction($monitorId, $result['status']);

    $monitor       = db_row('SELECT current_status FROM monitors WHERE id = ?', [$monitorId]);
    $statusChanged = $monitor && $monitor['current_status'] !== $result['status'];

    $pdo->beginTransaction();
    try {
        db_query(
            'INSERT INTO check_results (monitor_id, checked_at, status, response_ms, detail) VALUES (?, ?, ?, ?, ?)',
            [$monitorId, $now, $result['status'], $result['response_ms'], $result['detail']]
        );

        if ($statusChanged) {
            db_query(
                'UPDATE monitors SET current_status = ?, last_checked = ?, last_status_change = ? WHERE id = ?',
                [$result['status'], $now, $now, $monitorId]
            );
        } else {
            db_query(
                'UPDATE monitors SET current_status = ?, last_checked = ? WHERE id = ?',
                [$result['status'], $now, $monitorId]
            );
        }

        $pdo->commit();
    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

    if ($direction !== null) {
        send_alert($monitorId, $direction);
        db_query(
            'INSERT INTO notification_log (monitor_id, sent_at, direction) VALUES (?, ?, ?)',
            [$monitorId, $now, $direction]
        );
    }
}
