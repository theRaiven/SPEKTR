<?php
require_once dirname(__DIR__) . '/src/page.php';
require_roles(['admin']);

$data = app_data();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        check_csrf();
        $order = [
            'client_id' => post_int('client_id'),
            'device_id' => post_int('device_id'),
            'problem' => post_text('problem', 500),
            'condition' => post_text('condition', 400),
            'priority' => post_int('priority'),
            'planned_date' => post_text('planned_date', 30),
        ];
        if (!valid_calendar_date($order['planned_date'])) {
            throw new InvalidArgumentException('Проверьте плановую дату');
        }
        $id = app_action('order_add', $order);
        flash('Заказ №' . $id . ' создан');
        go('/order.php?id=' . $id);
    } catch (Throwable $errorData) {
        $error = app_error($errorData);
    }
}
page_start('Новый заказ');
?>
<div class="page-header">
    <div>
        <span class="section-kicker">ПРИЕМ ТЕХНИКИ</span>
        <h1>
            Новый заказ
        </h1>
        <p>
            Выберите устройство и заполните данные о неисправности.
        </p>
    </div>
    <a class="btn btn-secondary" href="/orders.php">← К списку</a>
</div>
<div class="card form-card">
    <?php form_error($error); ?>
    <form method="post" class="stack-form">
        <?php form_csrf(); ?>
        <div class="field">
            <label for="client_id">Клиент *</label>
            <select name="client_id" id="client_id" required>
                <option value="">Выберите клиента</option>
                <?php foreach ($data['clients'] as $client): ?>
                    <option value="<?= h($client['id']) ?>"><?= h(name_view($client) . ' · ' . $client['phone']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="device_id">Устройство *</label>
            <select name="device_id" id="device_id" required>
                <option value="">Выберите устройство</option>
                <?php foreach ($data['devices'] as $device): ?>
                    <?php $model = app_lookup($data['models'], $device['model_id']); if (!$model) continue; ?>
                    <option value="<?= h($device['id']) ?>">
                        <?= h($model['brand'] . ' ' . $model['name'] . ' · ' . ($device['serial'] ?: 'без серийного номера')) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <small>Заказчиком может быть человек, который принёс технику на ремонт</small>
        </div>
        <div class="field">
            <label for="problem">Описание неисправности</label><textarea id="problem" name="problem" rows="4" required maxlength="500" placeholder="Что случилось с устройством?"> <?= h(trim((string)($_POST['problem']??''))) ?></textarea>
        </div>
        <div class="field">
            <label for="condition">Состояние при приеме</label><textarea id="condition" name="condition" maxlength="400" rows="2" placeholder="Повреждения корпуса, комплектация"><?= h($_POST['condition']??'') ?></textarea>
        </div>
        <div class="form-row">
            <div class="field">
                <label for="priority">Приоритет</label><select id="priority" name="priority"><option value="1">Обычный</option><option value="2">Повышенный</option><option value="3">Срочный</option></select>
            </div>
            <div class="field">
                <label for="planned_date">Планируемая дата готовности</label><input type="date" id="planned_date" name="planned_date" value="<?= h($_POST['planned_date']??'') ?>">
            </div>
        </div>
        <div class="actions">
            <button class="btn btn-primary">Создать заказ</button><a class="btn btn-secondary" href="/orders.php">Отмена</a>
        </div>
    </form>
</div>
<?php page_end(); ?>
