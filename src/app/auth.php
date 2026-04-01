<?php

function auth_start(): void {
    session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function auth_check(): bool {
    return !empty($_SESSION['user_id']);
}

function auth_require(): void {
    if (!auth_check()) {
        header('Location: /login');
        exit;
    }
}

function auth_login(string $username, string $password): bool {
    $user = db_row('SELECT * FROM users WHERE username = ?', [$username]);
    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        return true;
    }
    return false;
}

function auth_logout(): void {
    session_destroy();
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrf_verify(): void {
    $token = $_POST['csrf'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(403);
        die('Invalid CSRF token');
    }
}
