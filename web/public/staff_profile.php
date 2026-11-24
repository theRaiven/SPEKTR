<?php
require_once dirname(__DIR__) . '/src/page.php';
require_roles(['admin', 'master']);

$person = app_find('staff', current_user()['id']);
$isAdmin = has_role(['admin']);
$orders = app_orders();

if (!$isAdmin) {
    $assignedIds = [];
    foreach (app_data()['assignments'] as $assignment) {
        if ($assignment['master_id'] === $person['id']) {
            $assignedIds[] = $assignment['order_id'];
        }
    }
    $orders = array_values(array_filter(
        $orders,
        fn($order) => in_array($order['id'], $assignedIds, true)
    ));
}

$pending = count(array_filter($orders, fn($order) => $order['status'] === 'Заявка'));
$active = count(array_filter($orders, fn($order) => !in_array(
    $order['status'],
    ['Выдан', 'Закрыт', 'Отказано', 'Отменен'],
    true
)));
$ready = count(array_filter($orders, fn($order) => $order['status'] === 'Готов к выдаче'));
$specializations = app_rows_for('specializations', 'staff_id', $person['id']);

page_start('Мой профиль');
?>
<div class="page-header">
    <div>
        <h1><?= $isAdmin ? 'Кабинет администратора' : 'Кабинет мастера' ?></h1>
        <p><?= h(name_view($person)) ?></p>
    </div>
    <a class="btn btn-primary" href="/orders.php"><?= $isAdmin ? 'Открыть заказы' : 'Мои заказы' ?> →</a>
</div>
<div class="dashboard-summary">
    <a href="/orders.php" class="summary-item">
        <span><?= $isAdmin ? 'Всего заказов' : 'Моих заказов' ?></span>
        <strong><?= count($orders) ?></strong>
        <small>Посмотреть список →</small>
    </a>
    <a href="<?= $isAdmin ? '/orders.php?status=' . rawurlencode('Заявка') : '/orders.php' ?>" class="summary-item">
        <span><?= $isAdmin ? 'Новые заявки' : 'Активные ремонты' ?></span>
        <strong><?= $isAdmin ? $pending : $active ?></strong>
        <small><?= $isAdmin ? 'Ожидают решения' : 'Текущая работа' ?></small>
    </a>
    <a href="/orders.php?status=<?= rawurlencode('Готов к выдаче') ?>" class="summary-item">
        <span>Готовы к выдаче</span>
        <strong><?= $ready ?></strong>
        <small>Проверить заказы →</small>
    </a>
</div>
<div class="dashboard-detail-grid">
    <section class="card table-card">
        <div class="table-heading">
            <div>
                <h2><?= $isAdmin ? 'Последние обращения' : 'Мои назначения' ?></h2>
                <p><?= $isAdmin ? 'Откройте заявку для принятия или назначения мастера' : 'Все изменения сохраняются в истории заказа' ?></p>
            </div>
            <a href="/orders.php" class="text-link">Все заказы →</a>
        </div>
        <div class="client-order-list">
            <?php foreach (array_slice($orders, 0, 6) as $order): ?>
                <a class="client-order-item" href="/order.php?id=<?= h($order['id']) ?>">
                    <strong class="client-order-no">№<?= h($order['id']) ?></strong>
                    <span class="client-order-device">
                        <?= h($order['device']) ?>
                        <small><?= h(short_date($order['created_at'])) ?></small>
                    </span>
                    <span class="badge <?= h(status_class($order['status'])) ?>"><?= h($order['status']) ?></span>
                    <span class="client-order-arrow" aria-hidden="true">↗</span>
                </a>
            <?php endforeach; ?>
            <?php if (!$orders): ?><p class="empty-state">Назначений пока нет</p><?php endif; ?>
        </div>
    </section>
    <section class="card staff-info-card">
        <div class="card-title-row"><h2>Данные сотрудника</h2></div>
        <dl class="detail-list">
            <div><dt>ФИО</dt><dd><?= h(name_view($person)) ?></dd></div>
            <div><dt>Должность</dt><dd><?= $isAdmin ? 'Администратор' : 'Мастер' ?></dd></div>
            <div><dt>Телефон</dt><dd><?= h($person['phone']) ?></dd></div>
            <?php if (!$isAdmin): ?>
                <div><dt>Стаж</dt><dd><?= h(app_master_experience($person['id'])) ?></dd></div>
            <?php endif; ?>
        </dl>
        <?php if ($specializations): ?>
            <h3>Специализации</h3>
            <?php foreach ($specializations as $spec): ?>
                <div class="list-line"><span><?= h($spec['name']) ?></span><span class="mini-pill"><?= h($spec['level']) ?></span></div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</div>
<?php page_end(); ?>
