<?php
require_once dirname(__DIR__) . '/src/page.php';
require_roles(['admin']);

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        check_csrf();
        $fields = [
            'last_name' => post_text('last_name', 100),
            'first_name' => post_text('first_name', 100),
            'middle_name' => post_text('middle_name', 100),
            'phone' => post_text('phone', 25),
            'email' => post_text('email', 120),
        ];
        $id = app_action('client_add', $fields);
        flash('Клиент добавлен');
        go('/client.php?id=' . $id);
    } catch (Throwable $errorData) {
        $error = app_error($errorData);
    }
}
page_start('Новый клиент');
?>
<div class="page-header">
    <div>
        <span class="section-kicker">БАЗА КЛИЕНТОВ</span>
        <h1>
            Новый клиент
        </h1>
        <p>
            Заполните контактные данные.
        </p>
    </div>
    <a href="/clients.php" class="btn btn-secondary">← К списку</a>
</div>
<div class="card form-card">
    <?php form_error($error); ?>
    <form method="post" class="stack-form">
        <?php form_csrf(); ?>
        <div class="form-row">
            <div class="field">
                <label for="last_name">Фамилия</label><input id="last_name" name="last_name" required maxlength="100" value="<?= h($_POST['last_name']??'') ?>">
            </div>
            <div class="field">
                <label for="first_name">Имя</label><input id="first_name" name="first_name" required maxlength="100" value="<?= h($_POST['first_name']??'') ?>">
            </div>
        </div>
        <div class="field">
            <label for="middle_name">Отчество (необязательно)</label><input id="middle_name" name="middle_name" maxlength="100" value="<?= h($_POST['middle_name']??'') ?>">
        </div>
        <div class="form-row">
            <div class="field">
                <label for="phone">Телефон</label><input id="phone" name="phone" type="tel" required maxlength="25" placeholder="+79991234567" value="<?= h($_POST['phone']??'') ?>">
            </div>
            <div class="field">
                <label for="email">Электронная почта</label><input id="email" name="email" type="email" maxlength="120" value="<?= h($_POST['email']??'') ?>">
            </div>
        </div>
        <div class="actions">
            <button class="btn btn-primary">Сохранить клиента</button><a href="/clients.php" class="btn btn-secondary">Отмена</a>
        </div>
    </form>
</div>
<?php page_end(); ?>
