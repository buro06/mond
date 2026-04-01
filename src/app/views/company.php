<?php

$action = $params['action'] ?? 'new';
$companyId = isset($params['id']) ? (int)$params['id'] : null;
$company = $companyId ? db_row('SELECT * FROM companies WHERE id = ?', [$companyId]) : null;
$smtp = $companyId ? db_row('SELECT * FROM smtp_configs WHERE company_id = ?', [$companyId]) : null;
$monitors = $companyId
        ? db_all('SELECT * FROM monitors WHERE company_id = ? ORDER BY name', [$companyId])
        : [];
$error = '';

if ($action === 'edit' && !$company) {
    http_response_code(404);
    page_start('Not Found');
    echo '<p>Company not found.</p>';
    page_end();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');

    if (!$name) {
        $error = 'Company name is required.';
    } else {
        $slug = slugify($name);
        if ($action === 'new') {
            $exists = db_row('SELECT id FROM companies WHERE slug = ?', [$slug]);
            if ($exists) $slug .= '-' . substr(generate_token(4), 0, 6);
            $newId = db_insert('INSERT INTO companies (name, slug) VALUES (?, ?)', [$name, $slug]);
            _save_smtp($newId);
            redirect('/companies/' . $newId);
        } else {
            db_query('UPDATE companies SET name = ?, slug = ? WHERE id = ?', [$name, $slug, $companyId]);
            _save_smtp($companyId);
            redirect('/companies/' . $companyId);
        }
    }
}

function _save_smtp(int $companyId): void
{
    $host = trim($_POST['smtp_host'] ?? '');
    $port = (int)($_POST['smtp_port'] ?? 587);
    $user = trim($_POST['smtp_username'] ?? '');
    $pass = trim($_POST['smtp_password'] ?? '');
    $from = trim($_POST['smtp_from_addr'] ?? '');
    $to = trim($_POST['smtp_to_addr'] ?? '');

    $existing = db_row('SELECT id FROM smtp_configs WHERE company_id = ?', [$companyId]);
    if ($existing) {
        db_query(
                'UPDATE smtp_configs SET host=?,port=?,username=?,password=?,from_addr=?,to_addr=? WHERE company_id=?',
                [$host, $port ?: 587, $user, $pass, $from, $to, $companyId]
        );
    } else {
        db_query(
                'INSERT INTO smtp_configs (company_id,host,port,username,password,from_addr,to_addr) VALUES (?,?,?,?,?,?,?)',
                [$companyId, $host, $port ?: 587, $user, $pass, $from, $to]
        );
    }
}

$pageTitle = $action === 'new' ? 'New Company' : h($company['name']);
page_start($pageTitle);
?>
<h1><?= $pageTitle ?></h1>
<?php if ($error): ?>
    <div class="error"><?= h($error) ?></div><?php endif; ?>

<form class="main" method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

    <div class="field">
        <label for="cname">Company Name</label>
        <input id="cname" type="text" name="name" value="<?= h($company['name'] ?? '') ?>" required autofocus>
    </div>

    <h2>Email Notifications (SMTP)</h2>
    <div class="field">
        <label>SMTP Host</label>
        <input type="text" name="smtp_host" value="<?= h($smtp['host'] ?? '') ?>" placeholder="smtp.example.com">
    </div>
    <div class="field">
        <label>SMTP Port</label>
        <input type="number" name="smtp_port" value="<?= h($smtp['port'] ?? '587') ?>">
    </div>
    <div class="field">
        <label>SMTP Username</label>
        <input type="text" name="smtp_username" value="<?= h($smtp['username'] ?? '') ?>" autocomplete="off">
    </div>
    <div class="field">
        <label>SMTP Password</label>
        <input type="password" name="smtp_password" value="***" autocomplete="off">
    </div>
    <div class="field">
        <label>From Address</label>
        <input type="email" name="smtp_from_addr" value="<?= h($smtp['from_addr'] ?? '') ?>"
               placeholder="alerts@example.com">
    </div>
    <div class="field">
        <label>Alert Recipient(s) <span class="muted">(comma-separated)</span></label>
        <input type="text" name="smtp_to_addr" value="<?= h($smtp['to_addr'] ?? '') ?>" placeholder="ops@example.com">
    </div>

    <div style="display:flex;gap:8px;margin-top:4px">
        <button type="submit" class="btn"><?= $action === 'new' ? 'Create Company' : 'Save Changes' ?></button>
        <a href="/dashboard" class="btn">Cancel</a>
    </div>
</form>

<?php if ($action === 'edit' && $companyId): ?>
    <h2 style="margin-top:28px">Monitors</h2>
    <p style="margin-bottom:10px">
        <a href="/monitors/new?company_id=<?= $companyId ?>" class="btn btn-sm">+ Add Monitor</a>
        &nbsp;<span class="muted">Public page: <a href="/status/<?= h($company['slug']) ?>"
                                                  target="_blank">/status/<?= h($company['slug']) ?></a></span>
    </p>

    <?php if (empty($monitors)): ?>
        <p class="muted">No monitors yet.</p>
    <?php else: ?>
        <table>
            <tr>
                <th>Name</th>
                <th>Type</th>
                <th>Target</th>
                <th>Status</th>
                <th>Last Check</th>
                <th>Actions</th>
            </tr>
            <?php foreach ($monitors as $m): ?>
                <tr>
                    <td><?= h($m['name']) ?></td>
                    <td><?= strtoupper($m['type']) ?></td>
                    <td style="font-size:12px;word-break:break-all">
                        <?php if ($m['type'] === 'http'): ?>
                            <?= h($m['target']) ?>
                        <?php elseif ($m['type'] === 'tcp'): ?>
                            <?= h($m['tcp_host'] . ':' . $m['tcp_port']) ?>
                        <?php else: ?>
                            POST /agent/<?= h($m['agent_token']) ?>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge badge-<?= $m['current_status'] ?>"><?= $m['current_status'] ?></span></td>
                    <td class="muted"><?= $m['last_checked'] ? time_ago((int)$m['last_checked']) : 'never' ?></td>
                    <td>
                        <a href="/monitors/<?= $m['id'] ?>/edit" class="btn btn-sm">Edit</a>
                        <form method="post" action="/monitors/<?= $m['id'] ?>/delete" style="display:inline"
                              onsubmit="return confirm('Delete monitor \'<?= h(addslashes($m['name'])) ?>\'?')">
                            <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                            <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    <?php endif; ?>
<?php endif; ?>
<?php page_end(); ?>
