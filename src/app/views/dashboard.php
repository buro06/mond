<?php

$companies = db_all(
        'SELECT c.*,
        COUNT(m.id) AS monitor_count,
        SUM(CASE WHEN m.current_status = \'up\'      THEN 1 ELSE 0 END) AS up_count,
        SUM(CASE WHEN m.current_status = \'down\'    THEN 1 ELSE 0 END) AS down_count,
        SUM(CASE WHEN m.current_status = \'unknown\' THEN 1 ELSE 0 END) AS unknown_count
     FROM companies c
     LEFT JOIN monitors m ON m.company_id = c.id AND m.enabled = 1
     GROUP BY c.id
     ORDER BY c.name'
);

page_start('Dashboard');
?>
<h1>Dashboard</h1>
<p style="margin-bottom:14px"><a href="/companies/new" class="btn">+ New Company</a></p>

<?php if (empty($companies)): ?>
    <p>No companies yet. <a href="/companies/new">Create one</a> to get started.</p>
<?php else: ?>
    <table>
        <tr>
            <th>Company</th>
            <th>Monitors</th>
            <th style="color:#00ff00">Up</th>
            <th style="color:#ff0000">Down</th>
            <th>Unknown</th>
            <th>Public Status</th>
            <th>Actions</th>
        </tr>
        <?php foreach ($companies as $c): ?>
            <tr>
                <td><a href="/companies/<?= $c['id'] ?>"><?= h($c['name']) ?></a></td>
                <td><?= (int)$c['monitor_count'] ?></td>
                <td class="up"><?= (int)$c['up_count'] ?></td>
                <td class="down"><?= (int)$c['down_count'] ?></td>
                <td class="unknown"><?= (int)$c['unknown_count'] ?></td>
                <td><a href="/status/<?= h($c['slug']) ?>" target="_blank">/status/<?= h($c['slug']) ?></a></td>
                <td>
                    <a href="/companies/<?= $c['id'] ?>" class="btn btn-sm">Manage</a>
                    <form method="post" action="/companies/<?= $c['id'] ?>/delete" style="display:inline"
                          onsubmit="return confirm('Delete company \'<?= h(addslashes($c['name'])) ?>\'?')">
                        <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
    </table>
<?php endif; ?>
<?php page_end(); ?>
