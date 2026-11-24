<?php
require_once dirname(__DIR__) . '/src/page.php';
require_roles(['client']);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        check_csrf();
        app_action('client_profile_update', [
            'last_name' => post_text('last_name', 100),
            'first_name' => post_text('first_name', 100),
            'middle_name' => post_text('middle_name', 100),
            'phone' => post_text('phone', 30),
            'email' => post_text('email', 254),
        ]);
        flash('Контактные данные сохранены');
        go('/profile.php');
    } catch (Throwable $exception) {
        $error = app_error($exception);
    }
}
$client = app_find('clients', current_user()['id']);
$orders = app_client_orders($client['id']);
$open = count(array_filter($orders, fn($order) => !in_array($order['status'], ['Выдан', 'Закрыт', 'Отказано', 'Отменен'], true)));

page_start('Мой профиль');
?>
<div class="page-header">
    <div>

        <h1>Здравствуйте, <?= h($client['first_name']) ?>!</h1>
        <p>Новые заявки и история ремонта</p>
    </div>
    <a class="btn btn-primary" href="/request_new.php">+ Новое обращение</a>
</div>
<?php form_error($error); ?>
<div class="stat-grid client-stats">
    <div class="stat-card"><span>Всего обращений</span><strong><?= count($orders) ?></strong><small>За все время</small></div>
    <div class="stat-card"><span>Активные</span><strong><?= $open ?></strong><small>В работе и на рассмотрении</small></div>
    <div class="stat-card"><span>Закрыты</span><strong><?= count($orders) - $open ?></strong><small>Выданы и отклонены</small></div>
</div>
<div class="two-col client-profile-cards">
    <section class="card">
        <div class="card-title-row"><h2>Мои данные</h2></div>
        <form method="post" class="stack-form">
            <?php form_csrf(); ?>
            <div class="form-row">
                <div class="field"><label for="last_name">Фамилия *</label>
                    <input id="last_name" name="last_name" maxlength="100" required value="<?= h($client['last_name']) ?>"></div>
                <div class="field"><label for="first_name">Имя *</label>
                    <input id="first_name" name="first_name" maxlength="100" required value="<?= h($client['first_name']) ?>"></div>
            </div>
            <div class="field"><label for="middle_name">Отчество</label>
                <input id="middle_name" name="middle_name" maxlength="100" value="<?= h($client['middle_name']) ?>"></div>
            <div class="field"><label for="phone">Телефон *</label>
                <input id="phone" name="phone" type="tel" maxlength="30" required value="<?= h($client['phone']) ?>"></div>
            <div class="field"><label for="email">Электронная почта</label>
                <input id="email" name="email" type="email" maxlength="254" value="<?= h($client['email']) ?>"></div>
            <button class="btn btn-secondary" type="submit">Сохранить данные</button>
        </form>
    </section>
    <section class="card profile-promo">
        <h2>Оформить заявку</h2>
        <p>Укажите устройство, неисправность и выберите мастера</p>
        <a class="btn btn-primary" href="/request_new.php">Оформить обращение →</a>
    </section>
</div>
<section class="card table-card" id="orders">
    <div class="table-heading"><div><h2>История обращений</h2><p>Каждый заказ доступен в отдельной карточке</p></div></div>
    <div class="client-order-list">
        <?php foreach ($orders as $order): ?>
        <a class="client-order-item" href="/my_order.php?id=<?= h($order['id']) ?>">
            <strong class="client-order-no">№<?= h($order['id']) ?></strong>
            <span class="client-order-device">
                <?= h($order['device']) ?>
                <small><?= h(short_date($order['created_at'])) ?></small>
            </span>
            <span class="badge <?= h(status_class($order['status'])) ?>"><?= h($order['status']) ?></span>
            <span class="client-order-arrow" aria-hidden="true">↗</span>
        </a>
        <?php endforeach; ?>
        <?php if (!$orders): ?><p class="muted">Вы еще не отправляли заявки</p><?php endif; ?>
    </div>
</section>
<?php page_end(); ?>
