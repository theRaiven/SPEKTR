<?php
require_once dirname(__DIR__) . '/src/page.php';
require_roles(['admin']);

$id = (int) ($_GET['id'] ?? 0);
$client = app_find('clients', $id);
if (!$client) {
    http_response_code(404);
    exit('Клиент не найден');
}
$data = app_data();
$clientOrders = app_client_orders($id);
$clientDevices = [];
foreach ($clientOrders as $repair) {
    $clientDevices[$repair['device_id']] = app_lookup($data['devices'], $repair['device_id']);
}
page_start('Карточка клиента');
?>
<div class="page-header">
    <div>
        <span class="section-kicker">КЛИЕНТ №<?= h($id) ?></span>
        <h1>
            <?= h(name_view($client)) ?>
        </h1>
        <p>
            Контакты, устройства и ремонты
        </p>
    </div>
    <a href="/clients.php" class="btn btn-secondary">← Все клиенты</a>
</div>
<div class="order-overview">
    <section class="card">
        <h2>
            Контакты
        </h2>
        <dl class="detail-list">
            <div>
                <dt>
                    Телефон
                </dt>
                <dd>
                    <?= h($client['phone']) ?>
                </dd>
            </div>
            <div>
                <dt>
                    Почта
                </dt>
                <dd>
                    <?= h($client['email']?:'Не указана') ?>
                </dd>
            </div>
        </dl>
    </section>
    <section class="card">
        <h2>
            Устройства в обращениях
        </h2>
        <div class="simple-list">
            <?php foreach ($clientDevices as $device): ?>
                <?php if (!$device) continue; ?>
                <?php $model = app_lookup($data['models'], $device['model_id']); ?>
                <?php if (!$model) continue; ?>
                <div class="list-line">
                    <span><a class="table-link" href="/device.php?id=<?= h($device['id']) ?>"><?= h($model['brand'].' '.$model['name']) ?></a><small><?= h($device['serial']) ?></small></span>
                    <span>Обращение клиента</span>
                </div>
            <?php endforeach; ?>
        </div>
        <?php if (!$clientDevices): ?>
        <p class="muted">
            Устройств нет
        </p>
        <?php endif; ?>
    </section>
</div>
<section class="card">
    <div class="card-title-row">
        <h2>
            История заказов
        </h2>
        <a class="btn btn-secondary" href="/device_new.php?client_id=<?= h($id) ?>">+ Устройство</a>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Номер</th><th>Устройство</th><th>Прием</th><th>Статус</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach(app_client_orders($id) as $o): ?>
                <tr>
                    <td><a href="/order.php?id=<?= h($o['id']) ?>">№<?= h($o['id']) ?></a></td><td><?= h($o['device']) ?></td><td><?= h(short_date($o['created_at'])) ?></td><td><span class="badge <?= h(status_class($o['status'])) ?>"><?= h($o['status']) ?></span></td>
                </tr>
                <?php endforeach; ?>
<?php if(!app_client_orders($id)): ?>
                <tr>
                    <td colspan="4" class="empty-row">Заказов нет</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php page_end(); ?>
