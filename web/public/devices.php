<?php
require_once dirname(__DIR__) . '/src/page.php';
require_roles(['admin']);

$data = app_data();
$search = trim((string) ($_GET['search'] ?? ''));
$devices = [];

foreach ($data['devices'] as $device) {
    $model = app_lookup($data['models'], $device['model_id']);
    $owner = null;
    foreach (app_device_orders($device['id']) as $repair) {
        $owner = app_lookup($data['clients'], $repair['client_id']);
        break;
    }

    $row = $device;
    $row['model'] = $model['type'] . ' · ' . $model['brand'] . ' ' . $model['name'];
    $row['owner'] = $owner ? name_view($owner) : ' - ';
    $row['repairs'] = count(app_device_orders($device['id']));
    if ($search === '' || stripos(
        $row['model'] . ' ' . $row['serial'] . ' ' . $row['owner'],
        $search
    ) !== false) {
        $devices[] = $row;
    }
}
page_start('Устройства');
?>
<div class="page-header">
    <div>
        <span class="section-kicker">УЧЕТ ТЕХНИКИ</span>
        <h1>
            Устройства
        </h1>
        <p>
            Модели, серийные номера и история обращений
        </p>
    </div>
    <a href="/device_new.php" class="btn btn-primary">+ Добавить устройство</a>
</div>
<div class="card table-card">
    <div class="table-heading">
        <div>
            <h2>
                Техника в системе
            </h2>
            <p>
                Найдено: <?= count($devices) ?>
            </p>
        </div>
    </div>
    <form method="get" class="filter-bar">
        <div class="field search-grow">
            <label for="search">Поиск</label><input id="search" name="search" value="<?= h($search) ?>" placeholder="Модель, номер или клиент">
        </div>
        <button class="btn btn-primary">Найти</button><a href="/devices.php" class="btn btn-secondary">Сбросить</a>
    </form>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Устройство</th><th>Серийный номер</th><th>Заказчик последнего ремонта</th><th>Ремонтов</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($devices as $device): ?>
                <tr>
                    <td><a class="table-link" href="/device.php?id=<?= h($device['id']) ?>"><?= h($device['model']) ?></a></td><td><?= h($device['serial']) ?></td><td><?= h($device['owner']) ?></td><td><?= h($device['repairs']) ?></td>
                </tr>
                <?php endforeach; ?>
<?php if(!$devices): ?>
                <tr>
                    <td colspan="4" class="empty-row">Ничего не найдено</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php page_end(); ?>
