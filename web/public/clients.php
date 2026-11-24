<?php
require_once dirname(__DIR__) . '/src/page.php';
require_roles(['admin']);

$search = trim((string) ($_GET['search'] ?? ''));
$clients = app_data()['clients'];
if ($search !== '') {
    $clients = array_values(array_filter(
        $clients,
        fn($client) => stripos(
            name_view($client) . ' ' . $client['phone'] . ' ' . $client['email'],
            $search
        ) !== false
    ));
}
usort($clients, fn($first, $second) => strcmp($first['last_name'], $second['last_name']));
page_start('Клиенты');
?>
<div class="page-header">
    <div>
        <span class="section-kicker">БАЗА КЛИЕНТОВ</span>
        <h1>
            Клиенты
        </h1>
        <p>
            Контакты и история обращений
        </p>
    </div>
    <a class="btn btn-primary" href="/client_new.php">+ Новый клиент</a>
</div>
<div class="card table-card">
    <div class="table-heading">
        <div>
            <h2>
                Список клиентов
            </h2>
            <p>
                Всего: <?= count($clients) ?>
            </p>
        </div>
    </div>
    <form class="filter-bar" method="get">
        <div class="field search-grow">
            <label for="search">Поиск</label><input name="search" id="search" placeholder="Фамилия, телефон, почта" value="<?= h($search) ?>">
        </div>
        <button class="btn btn-primary">Найти</button><a href="/clients.php" class="btn btn-secondary">Сбросить</a>
    </form>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Клиент</th><th>Телефон</th><th>Электронная почта</th><th>Заказов</th><th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($clients as $client): ?>
                <tr>
                    <td><a class="table-link" href="/client.php?id=<?= h($client['id']) ?>"><?= h(name_view($client)) ?></a></td><td><?= h($client['phone']) ?></td><td><?= h($client['email']?:' - ') ?></td><td><?= count(app_client_orders($client['id'])) ?></td><td><a href="/client.php?id=<?= h($client['id']) ?>" aria-label="Открыть клиента">↗</a></td>
                </tr>
                <?php endforeach; ?>
<?php if(!$clients): ?>
                <tr>
                    <td colspan="5" class="empty-row">Клиенты не найдены</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php page_end(); ?>
