<?php
require_once dirname(__DIR__) . '/src/page.php';
require_roles(['admin', 'master']);

$data = app_data();
$masters = array_values(array_filter(
    $data['staff'],
    fn($staff) => $staff['role'] === 'master'
));
page_start('Мастера');
?>
<div class="page-header">
    <div>
        <span class="section-kicker">КОМАНДА СЕРВИСА</span>
        <h1>
            Мастера
        </h1>
        <p>
            Специализации и загрузка сотрудников
        </p>
    </div>
</div>
<div class="master-grid">
    <?php foreach ($masters as $master): ?>
    <?php
    $active = 0;
    $all = 0;
    foreach ($data['assignments'] as $assignment) {
        if ($assignment['master_id'] !== $master['id']) {
            continue;
        }
        $all++;
        $order = app_order($assignment['order_id']);
        if ($order && !in_array($order['status'], ['Выдан', 'Закрыт', 'Отказано', 'Отменен'], true)) {
            $active++;
        }
    }
    ?>
    <section class="card master-card">
        <div class="master-profile">
            <span class="profile-avatar"><?= h(mb_first_letter($master['first_name'])) ?></span>
            <div>
                <h2>
                    <?= h(name_view($master)) ?>
                </h2>
                <p>Мастер, стаж <?= h(app_master_experience($master['id'])) ?></p>
            </div>
        </div>
        <div class="master-stats">
            <div>
                <strong><?= $active ?></strong><span>Активных заказов</span>
            </div>
            <div>
                <strong><?= $all ?></strong><span>Всего назначений</span>
            </div>
        </div>
        <div class="subtle-divider">
        </div>
        <h3>
            Специализации
        </h3>
        <?php foreach (app_rows_for('specializations', 'staff_id', $master['id']) as $specialty): ?>
        <div class="list-line">
            <span><?= h($specialty['name']) ?></span><span class="mini-pill"><?= h($specialty['level']) ?></span>
        </div>
        <?php endforeach; ?>
    </section>
    <?php endforeach; ?>
</div>
<?php page_end(); ?>
