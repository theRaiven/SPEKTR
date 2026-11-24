<?php
require_once dirname(__DIR__) . '/src/page.php';
require_roles(['admin']);

$data = app_data();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        check_csrf();
        $device = [
            'client_id' => post_int('client_id'),
            'type' => post_text('type', 50),
            'brand' => post_text('brand', 80),
            'model' => post_text('model', 80),
            'serial' => post_text('serial', 100),
        ];
        $id = app_action('device_add', $device);
        flash('Устройство зарегистрировано');
        go('/device.php?id=' . $id);
    } catch (Throwable $errorData) {
        $error = app_error($errorData);
    }
}
page_start('Новое устройство');
?>
<div class="page-header">
    <div>
        <span class="section-kicker">УЧЕТ ТЕХНИКИ</span>
        <h1>
            Новое устройство
        </h1>
        <p>
            Данные устройства и модели.
        </p>
    </div>
    <a href="/devices.php" class="btn btn-secondary">← К списку</a>
</div>
<div class="card form-card">
    <?php form_error($error); ?>
    <form method="post" class="stack-form">
        <?php form_csrf(); ?>
        <div class="form-row">
            <div class="field">
                <label for="type">Тип техники</label><input id="type" name="type" required maxlength="50" placeholder="Ноутбук" value="<?= h($_POST['type']??'') ?>">
            </div>
            <div class="field">
                <label for="brand">Производитель</label><input id="brand" name="brand" maxlength="80" required placeholder="ASUS" value="<?= h($_POST['brand']??'') ?>">
            </div>
        </div>
        <div class="form-row">
            <div class="field">
                <label for="model">Модель</label><input id="model" name="model" required maxlength="80" placeholder="VivoBook 15" value="<?= h($_POST['model']??'') ?>">
            </div>
            <div class="field">
                <label for="serial">Серийный номер</label><input id="serial" name="serial" required maxlength="100" value="<?= h($_POST['serial']??'') ?>">
            </div>
        </div>
        <div class="actions">
            <button class="btn btn-primary">Сохранить устройство</button><a class="btn btn-secondary" href="/devices.php">Отмена</a>
        </div>
    </form>
</div>
<?php page_end(); ?>
