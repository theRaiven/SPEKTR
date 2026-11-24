<?php
require_once dirname(__DIR__) . '/src/page.php';
require_roles(['admin']);

$id = (int) ($_GET['id'] ?? 0);
$data = app_data();
$device = app_lookup($data['devices'], $id);
if (!$device) {
    http_response_code(404);
    exit('Устройство не найдено');
}
$model = app_lookup($data['models'], $device['model_id']);
$deviceOrders = app_device_orders($id);
$customers = [];
foreach ($deviceOrders as $repair) {
    $customers[$repair['client_id']] = app_lookup($data['clients'], $repair['client_id']);
}
page_start('Карточка устройства');
?>
<div class="page-header">
    <div>
        <span class="section-kicker">УСТРОЙСТВО №<?= h($id) ?></span>
        <h1>
            <?= h($model['brand'].' '.$model['name']) ?>
        </h1>
        <p>
            <?= h($model['type']) ?> · серийный номер <?= h($device['serial']) ?>
        </p>
    </div>
    <a href="/devices.php" class="btn btn-secondary">← Все устройства</a>
</div>
<div class="order-overview">
    <section class="card">
        <h2>
            Об устройстве
        </h2>
        <dl class="detail-list">
            <div>
                <dt>
                    Тип
                </dt>
                <dd>
                    <?= h($model['type']) ?>
                </dd>
            </div>
            <div>
                <dt>
                    Производитель
                </dt>
                <dd>
                    <?= h($model['brand']) ?>
                </dd>
            </div>
            <div>
                <dt>
                    Модель
                </dt>
                <dd>
                    <?= h($model['name']) ?>
                </dd>
            </div>
            <div>
                <dt>
                    Серийный номер
                </dt>
                <dd>
                    <?= h($device['serial']) ?>
                </dd>
            </div>
        </dl>
    </section>
    <section class="card">
        <h2>
            Клиенты, обращавшиеся с устройством
        </h2>
        <div class="simple-list">
            <?php foreach ($customers as $client): ?>
                <?php if (!$client) continue; ?>
                <div class="list-line">
                    <span><a class="table-link" href="/client.php?id=<?= h($client['id']) ?>"><?= h(name_view($client)) ?></a><small>Обращался с этим устройством</small></span>
                    <span class="badge status-neutral">Заказчик</span>
                </div>
            <?php endforeach; ?>
        </div>
    </section>
</div>
<section class="card">
    <div class="card-title-row">
        <h2>
            Ремонты устройства
        </h2>
        <a href="/order_new.php" class="btn btn-secondary">+ Новый заказ</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Заказ</th><th>Неисправность</th><th>Статус</th><th>Дата</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach(app_device_orders($id) as $o): ?>
                <tr>
                    <td><a href="/order.php?id=<?= h($o['id']) ?>">№<?= h($o['id']) ?></a></td><td><?= h($o['problem']) ?></td><td><span class="badge <?= h(status_class($o['status'])) ?>"><?= h($o['status']) ?></span></td><td><?= h(short_date($o['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
<?php if(!app_device_orders($id)): ?>
                <tr>
                    <td colspan="4" class="empty-row">Ремонтов пока не было</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php page_end(); ?>
