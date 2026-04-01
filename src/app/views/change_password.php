<?php

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $current  = $_POST['current_password'] ?? '';
    $new      = $_POST['new_password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    $user = db_row('SELECT * FROM users WHERE id = ?', [$_SESSION['user_id']]);

    if (!$user || !password_verify($current, $user['password'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($new) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new !== $confirm) {
        $error = 'New passwords do not match.';
    } else {
        $hash = password_hash($new, PASSWORD_BCRYPT);
        db_query('UPDATE users SET password = ? WHERE id = ?', [$hash, $_SESSION['user_id']]);
        $success = 'Password changed successfully.';
    }
}

page_start('Change Password');
?>

<h1>Change Password</h1>

<?php if ($error): ?>
    <p class="error"><?= h($error) ?></p>
<?php endif; ?>
<?php if ($success): ?>
    <p class="success"><?= h($success) ?></p>
<?php endif; ?>

<form method="post" action="/account/password">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

    <label>Current Password
        <input type="password" name="current_password" required autofocus>
    </label>

    <label>New Password
        <input type="password" name="new_password" required minlength="8">
    </label>

    <label>Confirm New Password
        <input type="password" name="confirm_password" required minlength="8">
    </label>

    <button type="submit">Change Password</button>
</form>

<?php page_end(); ?>