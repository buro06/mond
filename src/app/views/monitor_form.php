<?php

$monitorId = isset($params['id']) ? (int)$params['id'] : null;
$monitor = $monitorId ? db_row('SELECT * FROM monitors WHERE id = ?', [$monitorId]) : null;
$companyId = $monitor['company_id'] ?? (isset($_GET['company_id']) ? (int)$_GET['company_id'] : null);
$company = $companyId ? db_row('SELECT * FROM companies WHERE id = ?', [$companyId]) : null;
$action = $monitorId ? 'edit' : 'new';
$error = '';

if (!$company) redirect('/dashboard');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name = trim($_POST['name'] ?? '');
    $type = $_POST['type'] ?? 'http';
    $target = trim($_POST['target'] ?? '');
    $tcp_host = trim($_POST['tcp_host'] ?? '');
    $tcp_port = (int)($_POST['tcp_port'] ?? 0);
    $interval = max(30, (int)($_POST['interval_sec'] ?? 60));
    $agentTtl = max(60, (int)($_POST['agent_timeout_sec'] ?? 300));

    if (!$name) {
        $error = 'Name is required.';
    } elseif ($type === 'http' && !filter_var($target, FILTER_VALIDATE_URL)) {
        $error = 'A valid URL is required for HTTP monitors.';
    } elseif ($type === 'tcp' && (!$tcp_host || $tcp_port < 1 || $tcp_port > 65535)) {
        $error = 'A valid host and port (1–65535) are required for TCP monitors.';
    } else {
        if ($action === 'new') {
            $token = $type === 'agent' ? generate_token(16) : null;
            db_insert(
                    'INSERT INTO monitors (company_id,name,type,target,tcp_host,tcp_port,agent_token,interval_sec,agent_timeout_sec)
                 VALUES (?,?,?,?,?,?,?,?,?)',
                    [$company['id'], $name, $type,
                            $type === 'http' ? $target : null,
                            $type === 'tcp' ? $tcp_host : null,
                            $type === 'tcp' ? $tcp_port : null,
                            $token, $interval, $agentTtl]
            );
        } else {
            db_query(
                    'UPDATE monitors SET name=?,type=?,target=?,tcp_host=?,tcp_port=?,interval_sec=?,agent_timeout_sec=? WHERE id=?',
                    [$name, $type,
                            $type === 'http' ? $target : null,
                            $type === 'tcp' ? $tcp_host : null,
                            $type === 'tcp' ? $tcp_port : null,
                            $interval, $agentTtl, $monitorId]
            );
        }
        redirect('/companies/' . $company['id']);
    }
}

$currentType = $_POST['type'] ?? ($monitor['type'] ?? 'http');
page_start($action === 'new' ? 'Add Monitor' : 'Edit Monitor');
?>
<h1><?= $action === 'new' ? 'Add Monitor' : 'Edit Monitor' ?></h1>
<p class="muted" style="margin-bottom:14px">Company: <a
            href="/companies/<?= $company['id'] ?>"><?= h($company['name']) ?></a></p>

<?php if ($error): ?>
    <div class="error"><?= h($error) ?></div><?php endif; ?>

<form class="main" method="post">
    <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">

    <div class="field">
        <label for="mname">Name</label>
        <input id="mname" type="text" name="name" value="<?= h($_POST['name'] ?? $monitor['name'] ?? '') ?>" required
               autofocus>
    </div>

    <div class="field">
        <label for="mtype">Type</label>
        <select id="mtype" name="type" onchange="showFields(this.value)">
            <option value="http" <?= $currentType === 'http' ? 'selected' : '' ?>>HTTP</option>
            <option value="tcp" <?= $currentType === 'tcp' ? 'selected' : '' ?>>TCP</option>
            <option value="agent" <?= $currentType === 'agent' ? 'selected' : '' ?>>Agent (heartbeat)</option>
        </select>
    </div>

    <div id="f-http" class="field">
        <label>URL</label>
        <input type="url" name="target"
               value="<?= h($_POST['target'] ?? $monitor['target'] ?? '') ?>"
               placeholder="https://example.com">
    </div>

    <div id="f-tcp-host" class="field">
        <label>Host</label>
        <input type="text" name="tcp_host"
               value="<?= h($_POST['tcp_host'] ?? $monitor['tcp_host'] ?? '') ?>"
               placeholder="1.1.1.1">
    </div>

    <div id="f-tcp-port" class="field">
        <label>Port</label>
        <input type="number" name="tcp_port" min="1" max="65535"
               value="<?= h($_POST['tcp_port'] ?? $monitor['tcp_port'] ?? '') ?>"
               placeholder="443">
    </div>

    <div id="f-interval" class="field">
        <label>Check Interval (seconds, min 30)</label>
        <input type="number" name="interval_sec" min="30"
               value="<?= h($_POST['interval_sec'] ?? $monitor['interval_sec'] ?? 60) ?>">
    </div>

    <div id="f-timeout" class="field">
        <label>Agent Timeout (seconds) <span class="muted">— mark down if no heartbeat for this long</span></label>
        <input type="number" name="agent_timeout_sec" min="60"
               value="<?= h($_POST['agent_timeout_sec'] ?? $monitor['agent_timeout_sec'] ?? 300) ?>">
    </div>

    <?php if ($action === 'edit' && ($monitor['type'] ?? '') === 'agent' && $monitor['agent_token']): ?>
        <div class="panel muted" style="margin-bottom:14px">
            <strong>Agent heartbeat URL:</strong><br>
            POST <?= $_SERVER['REQUEST_SCHEME'] ?? 'http' ?>://<?= $_SERVER['HTTP_HOST'] ?>
            /agent/<?= h($monitor['agent_token']) ?>
        </div>
    <?php endif; ?>

    <div style="display:flex;gap:8px">
        <button type="submit" class="btn"><?= $action === 'new' ? 'Add Monitor' : 'Save' ?></button>
        <a href="/companies/<?= $company['id'] ?>" class="btn">Cancel</a>
    </div>
</form>

<script src="/public/app.js"></script>
<script>showFields('<?= $currentType ?>');</script>
<?php page_end(); ?>
