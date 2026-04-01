<?php

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    if (auth_login(trim($_POST['username'] ?? ''), $_POST['password'] ?? '')) {
        redirect('/dashboard');
    }
    $error = 'Invalid username or password.';
}

page_start('Login', true);
?>
<h1>monD Login</h1>
<?php if ($error): ?>
    <div class="error"><?= h($error) ?></div><?php endif; ?>
<form class="main" method="post" action="/login">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
    <div class="field">
        <label for="username">Username</label>
        <input id="username" type="text" name="username" value="<?= h($_POST['username'] ?? '') ?>" autofocus
               autocomplete="username">
    </div>
    <div class="field">
        <label for="password">Password</label>
        <input id="password" type="password" name="password" autocomplete="current-password">
    </div>
    <button type="submit" class="btn">Login</button>
</form>
<?php page_end(); ?>
