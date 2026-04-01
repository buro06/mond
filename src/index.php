<?php

require_once __DIR__ . '/app/db.php';
require_once __DIR__ . '/app/helpers.php';
require_once __DIR__ . '/app/auth.php';
require_once __DIR__ . '/app/checker.php';
require_once __DIR__ . '/app/mailer.php';
require_once __DIR__ . '/app/views/layout.php';

auth_start();

//Router setup

$uri      = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/';
$uri      = '/' . trim($uri, '/');
$method   = $_SERVER['REQUEST_METHOD'];
$segments = explode('/', trim($uri, '/'));

$s0 = $segments[0] ?? '';
$s1 = $segments[1] ?? '';
$s2 = $segments[2] ?? '';

$params = [];



// Agent heartbeat (no auth)
if ($s0 === 'agent' && $s1 !== '' && $method === 'POST') {
    header('Content-Type: application/json');
    $monitor = db_row(
        "SELECT * FROM monitors WHERE agent_token = ? AND type = 'agent' AND enabled = 1",
        [$s1]
    );
    if (!$monitor) {
        http_response_code(404);
        echo json_encode(['ok' => false, 'error' => 'Unknown token']);
        exit;
    }
    $now = time();
    db_query(
        'UPDATE monitors SET last_heartbeat = ?, last_checked = ? WHERE id = ?',
        [$now, $now, $monitor['id']]
    );
    $result = check_agent(array_merge($monitor, ['last_heartbeat' => $now]));
    try {
        record_result((int)$monitor['id'], $result);
    } catch (Exception $e) {

    }
    echo json_encode(['ok' => true, 'status' => $result['status']]);
    exit;
}

// Public status page (no auth)
if ($s0 === 'status' && $s1 !== '') {
    $params['slug'] = $s1;
    require __DIR__ . '/app/views/status.php';
    exit;
}

// Login / logout
if ($s0 === 'login') {
    require __DIR__ . '/app/views/login.php';
    exit;
}

if ($s0 === 'logout') {
    auth_logout();
    redirect('/login');
}

// Everything below requires authentication
auth_require();

// Try dashboard if authenticated
if ($uri === '/') {
    redirect('/dashboard');
}

// Dashboard
if ($s0 === 'dashboard') {
    require __DIR__ . '/app/views/dashboard.php';
    exit;
}

// Companies
if ($s0 === 'companies') {
    if ($s1 === 'new') {
        $params['action'] = 'new';
        require __DIR__ . '/app/views/company.php';
        exit;
    }

    if (ctype_digit($s1)) {
        if ($s2 === 'delete' && $method === 'POST') {
            csrf_verify();
            db_query('DELETE FROM companies WHERE id = ?', [(int) $s1]);
            redirect('/dashboard');
        }
        $params['action'] = 'edit';
        $params['id']     = (int) $s1;
        require __DIR__ . '/app/views/company.php';
        exit;
    }

    redirect('/dashboard');
}

// Monitors
if ($s0 === 'monitors') {
    if ($s1 === 'new') {
        $params['id'] = null;
        require __DIR__ . '/app/views/monitor_form.php';
        exit;
    }

    if (ctype_digit($s1)) {
        if ($s2 === 'edit') {
            $params['id'] = (int) $s1;
            require __DIR__ . '/app/views/monitor_form.php';
            exit;
        }

        if ($s2 === 'delete' && $method === 'POST') {
            csrf_verify();
            $m = db_row('SELECT company_id FROM monitors WHERE id = ?', [(int) $s1]);
            db_query('DELETE FROM monitors WHERE id = ?', [(int) $s1]);
            redirect($m ? '/companies/' . $m['company_id'] : '/dashboard');
        }
    }

    redirect('/dashboard');
}

// Account
if ($s0 === 'account' && $s1 === 'password') {
    require __DIR__ . '/app/views/change_password.php';
    exit;
}

// ── 404 ───────────────────────────────────────────────────────────────────────
http_response_code(404);
page_start('Not Found');
echo '<h1>404 Not Found</h1>';
page_end();
