<?php

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $path = __DIR__ . '/../db/mond.db';
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        db_init($pdo);
    }
    return $pdo;
}

function db_init(PDO $pdo): void {
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec('PRAGMA foreign_keys = ON');

    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id       INTEGER PRIMARY KEY,
        username TEXT NOT NULL UNIQUE,
        password TEXT NOT NULL
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS companies (
        id   INTEGER PRIMARY KEY,
        name TEXT NOT NULL,
        slug TEXT NOT NULL UNIQUE
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS smtp_configs (
        id         INTEGER PRIMARY KEY,
        company_id INTEGER NOT NULL UNIQUE REFERENCES companies(id) ON DELETE CASCADE,
        host       TEXT,
        port       INTEGER DEFAULT 587,
        username   TEXT,
        password   TEXT,
        from_addr  TEXT,
        to_addr    TEXT
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS monitors (
        id                INTEGER PRIMARY KEY,
        company_id        INTEGER NOT NULL REFERENCES companies(id) ON DELETE CASCADE,
        name              TEXT NOT NULL,
        type              TEXT NOT NULL CHECK(type IN (\'http\',\'tcp\',\'agent\')),
        target            TEXT,
        tcp_host          TEXT,
        tcp_port          INTEGER,
        agent_token       TEXT UNIQUE,
        interval_sec      INTEGER NOT NULL DEFAULT 60,
        agent_timeout_sec INTEGER DEFAULT 300,
        current_status    TEXT NOT NULL DEFAULT \'unknown\',
        last_checked      INTEGER,
        last_heartbeat    INTEGER,
        last_status_change INTEGER,
        enabled           INTEGER NOT NULL DEFAULT 1
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS check_results (
        id          INTEGER PRIMARY KEY,
        monitor_id  INTEGER NOT NULL REFERENCES monitors(id) ON DELETE CASCADE,
        checked_at  INTEGER NOT NULL,
        status      TEXT NOT NULL CHECK(status IN (\'up\',\'down\')),
        response_ms INTEGER,
        detail      TEXT
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS notification_log (
        id         INTEGER PRIMARY KEY,
        monitor_id INTEGER NOT NULL REFERENCES monitors(id) ON DELETE CASCADE,
        sent_at    INTEGER NOT NULL,
        direction  TEXT NOT NULL CHECK(direction IN (\'up\',\'down\'))
    )');

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_results_monitor_time
        ON check_results(monitor_id, checked_at DESC)');

    $count = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
    if ($count === 0) {
        $hash = password_hash('password', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO users (username, password) VALUES (?, ?)');
        $stmt->execute(['admin', $hash]);
    }
}

function db_query(string $sql, array $params = []): PDOStatement {
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function db_row(string $sql, array $params = []): array|false {
    return db_query($sql, $params)->fetch();
}

function db_all(string $sql, array $params = []): array {
    return db_query($sql, $params)->fetchAll();
}

function db_insert(string $sql, array $params = []): int {
    db_query($sql, $params);
    return (int) db()->lastInsertId();
}
