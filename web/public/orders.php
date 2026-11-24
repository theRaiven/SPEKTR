<?php
require_once dirname(__DIR__) . '/src/page.php';
require_roles(['admin', 'master']);

$orders = app_orders();
$pending = count(array_filter($orders, fn($item) => $item['status'] === 'Заявка'));
$search = trim((string) ($_GET['search'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));

if (current_user()['role'] === 'master') {
    $assignments = app_data()['assignments'];
    $assignedOrders = array_column(
        array_filter($assignments, fn($assignment) => $assignment['master_id'] == current_user()['id']),
        'order_id'
    );
    $orders = array_values(array_filter(
        $orders,
        fn($order) => in_array($order['id'], $assignedOrders, true)
    ));
}

$counts = ['total' => count($orders), 'active' => 0, 'ready' => 0, 'issued' => 0];
foreach ($orders as $order) {
    if (!in_array($order['status'], ['Выдан', 'Закрыт', 'Отказано', 'Отменен'], true)) {
        $counts['active']++;
    }
    if ($order['status'] === 'Готов к выдаче') {
        $counts['ready']++;
    }
    if ($order['status'] === 'Выдан') {
        $counts['issued']++;
    }
}

$orders = array_values(array_filter($orders, function ($order) use ($search, $status) {
    if ($status !== '' && $order['status'] !== $status) {
        return false;
    }
    $details = $order['id'] . ' ' . $order['client'] . ' ' . $order['device'] . ' ' . $order['problem'];
    return $search === '' || stripos($details, $search) !== false;
}));

page_start('Заказы');
?>
<div class="page-header">
    <div>

        <h1>
            <?= has_role(['master']) ? 'Мои заказы' : 'Заказы и заявки' ?>
        </h1>
        <p>
            Прием, диагностика, ремонт и выдача техники
        </p>
    </div>
    <?php if(has_role(['admin'])): ?><a class="btn btn-primary" href="/order_new.php">+ Новый заказ</a><?php endif; ?>
</div>
<div class="stat-grid">
    <div class="stat-card">
        <span>Всего заказов</span><strong><?= $counts['total'] ?></strong><small>В системе</small>
    </div>
    <div class="stat-card">
        <span>В работе</span><strong><?= $counts['active'] ?></strong><small>Не закрыты</small>
    </div>
    <div class="stat-card">
        <span>Готовы к выдаче</span><strong><?= $counts['ready'] ?></strong><small>Ожидают клиента</small>
    </div>
    <div class="stat-card">
        <span><?= has_role(['admin']) ? 'Новые заявки' : 'Выданы' ?></span>
        <strong><?= has_role(['admin']) ? $pending : $counts['issued'] ?></strong>
        <small><?= has_role(['admin']) ? 'Ожидают решения' : 'Завершены' ?></small>
    </div>
</div>
<div class="card table-card">
    <div class="table-heading">
        <div>
            <h2>
                <?= has_role(['master']) ? 'Назначенные ремонты' : 'Все обращения' ?>
            </h2>
            <p>
                Найдено: <?= count($orders) ?>
            </p>
        </div>
    </div>
    <form method="get" class="filter-bar">
        <div class="field">
            <label for="search">Поиск</label><input id="search" name="search" value="<?= h($search) ?>" placeholder="Номер, клиент или устройство">
        </div>
        <div class="field">
            <label for="status">Статус</label><select id="status" name="status"><option value="">Все статусы</option><?php foreach(app_data()['statuses'] as $s): ?><option <?= $s===$status?'selected':'' ?> value="<?= h($s) ?>"><?= h($s) ?></option><?php endforeach; ?></select>
        </div>
        <button class="btn btn-primary" type="submit">Показать</button><a class="btn btn-secondary" href="/orders.php">Сбросить</a>
    </form>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Заказ</th><th>Устройство</th><th>Клиент</th><th>Дата приема</th><th>Статус</th><th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($orders as $o): ?>
                <tr>
                    <td><a class="table-link" href="/order.php?id=<?= h($o['id']) ?>">№ <?= h($o['id']) ?></a></td><td><?= h($o['device']) ?></td><td><?= h($o['client']) ?></td><td><?= h(short_date($o['created_at'])) ?></td><td><span class="badge <?= h(status_class($o['status'])) ?>"><?= h($o['status']) ?></span></td><td><a href="/order.php?id=<?= h($o['id']) ?>" aria-label="Открыть заказ">↗</a></td>
                </tr>
                <?php endforeach; ?>
<?php if (!$orders): ?>
                <tr>
                    <td colspan="6" class="empty-row">Заказы не найдены</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php page_end(); ?>
