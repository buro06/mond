<?php

function page_start(string $title, bool $public = false): void
{
    $isLoggedIn = !$public && auth_check();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width,initial-scale=1">
        <title><?= h($title) ?> — monD</title>
        <link rel="stylesheet" href="/public/style.css">
    </head>
<body>
    <nav>
        <a class="logo" href="<?= $isLoggedIn ? '/dashboard' : '/' ?>">monD</a>
        <?php if ($isLoggedIn): ?>
            <a href="/dashboard">Dashboard</a>
            <span class="spacer"></span>
            <span class="muted" style="color:#aaa"><?= h($_SESSION['username'] ?? '') ?></span>
            <a href="/account/password">Change Password</a>
            <a href="/logout">Logout</a>
        <?php endif; ?>
    </nav>
    <div class="wrap">
    <?php
}

function page_end(): void
{
    echo '</div></body></html>';
}
