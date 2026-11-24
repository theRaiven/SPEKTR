<?php
require_once dirname(__DIR__) . '/src/page.php';
require_roles(['client']);

$error = '';
$data = app_data();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        check_csrf();
        $fields = [
            'type' => post_text('type', 40),
            'type_other' => post_text('type_other', 80),
            'brand' => post_text('brand', 80),
            'model' => post_text('model', 100),
            'serial' => post_text('serial', 100),
            'service_id' => post_int('service_id'),
            'preferred_master_id' => post_int('preferred_master_id'),
            'confirmed' => !empty($_POST['confirmed']),
            'problem' => post_text('problem', 500),
        ];
        $id = app_action('request_add', $fields);
        flash('Заявка №' . $id . ' отправлена администратору');
        go('/my_order.php?id=' . $id);
    } catch (Throwable $exception) {
        $error = app_error($exception);
    }
}

page_start('Новое обращение');
?>
<div class="page-header">
    <div><span class="section-kicker">НОВАЯ ЗАЯВКА</span><h1>Заявка на ремонт</h1><p>Заполните данные устройства. Заявку проверит администратор</p></div>
    <a href="/profile.php" class="btn btn-secondary">← В профиль</a>
</div>
<section class="card form-card">
    <?php form_error($error); ?>
    <form method="post" class="stack-form">
        <?php form_csrf(); ?>
        <div class="field">
            <label for="type">Вид техники *</label>
            <select name="type" id="type" required>
                <option value="">Выберите вид техники</option>
                <?php foreach (['Смартфон', 'Ноутбук', 'Планшет', 'Компьютер', 'Принтер', 'Монитор', 'Игровая приставка', 'Телевизор', 'Другая техника'] as $type): ?>
                    <option value="<?= h($type) ?>" <?= ($_POST['type'] ?? '') === $type ? 'selected' : '' ?>><?= h($type) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field" id="other_type_field" hidden>
            <label for="type_other">Укажите вид техники *</label>
            <input id="type_other" name="type_other" maxlength="80" value="<?= h($_POST['type_other'] ?? '') ?>" placeholder="Например, аудиосистема">
        </div>
        <div class="form-row">
            <div class="field"><label for="brand">Бренд *</label><input id="brand" name="brand" maxlength="80" required placeholder="Например, Samsung" value="<?= h($_POST['brand'] ?? '') ?>"></div>
            <div class="field"><label for="model">Модель *</label><input id="model" name="model" maxlength="100" required placeholder="Например, Galaxy S23" value="<?= h($_POST['model'] ?? '') ?>"></div>
        </div>
        <div class="field"><label for="serial">Серийный номер устройства *</label>
                    <input id="serial" name="serial" maxlength="100" required placeholder="Например, SN12345678" autocomplete="off" value="<?= h($_POST['serial'] ?? '') ?>"><small>Номер обычно указан на корпусе или в настройках устройства. Не вводите IMEI, если указан серийный номер</small>
                    </div>
        <div class="field"><label for="service_id">Нужная услуга *</label>
            <select name="service_id" id="service_id" required>
                <option value="">Выберите услугу</option>
                <?php foreach ($data['tariffs'] as $tariff): ?>
                <option value="<?= h($tariff['id']) ?>" data-types="<?= h($tariff['categories']) ?>" <?= (int) ($_POST['service_id'] ?? 0) === (int) $tariff['id'] ? 'selected' : '' ?>><?= h($tariff['name']) ?> · предварительно <?= h(money_view($tariff['price'])) ?></option>
                <?php endforeach; ?>
            </select>
            <small>Предварительная стоимость работы без запчастей. Итоговая сумма будет известна после диагностики</small>
        </div>
        <div class="field"><label for="problem">Описание неисправности *</label>
                    <textarea id="problem" name="problem" rows="5" maxlength="500" minlength="10" required placeholder="Опишите, когда возникает неисправность и что происходит"><?= h($_POST['problem'] ?? '') ?></textarea>
            </div>
        <div class="field">
            <label>Выберите мастера</label>
            <p class="field-help">Вы можете выбрать конкретного специалиста. Администратор получит ваш выбор вместе с заявкой</p>
            <div class="master-choices" id="master_choices">
                <label class="master-choice master-choice-default">
                    <input type="radio" name="preferred_master_id" value="0" <?= empty($_POST['preferred_master_id']) ? 'checked' : '' ?>>
                    <span><strong>Любой подходящий мастер</strong><small>Администратор назначит свободного специалиста</small></span>
                </label>
                <?php foreach ($data['staff'] as $master): ?>
                    <?php if ($master['role'] !== 'master' || !$master['active']) continue; ?>
                    <?php
                    $specialtyNames = [];
                    foreach ($data['specializations'] as $item) {
                        if ((int) $item['staff_id'] === (int) $master['id']) {
                            $specialtyNames[] = $item['name'];
                        }
                    }
                    $categories = [];
                    $eligiblePairs = [];
                    foreach (['Смартфон', 'Ноутбук', 'Планшет', 'Компьютер', 'Принтер', 'Монитор', 'Игровая приставка', 'Телевизор', 'Другая техника'] as $type) {
                        if (!in_array(app_specialty_for_type($type), $specialtyNames, true)) {
                            continue;
                        }
                        foreach ($data['tariffs'] as $tariff) {
                            if (!in_array($type, explode(',', $tariff['categories']), true)) {
                                continue;
                            }
                            if (pg_qualified($master['id'], $type, $tariff['id'])) {
                                $eligiblePairs[] = $type . ':' . $tariff['id'];
                                $categories[] = $type;
                            }
                        }
                    }
                    $categories = array_unique($categories);
                    ?>
                    <?php if ($categories): ?>
                        <label class="master-choice" data-types="<?= h(implode(',', $categories)) ?>" data-pairs="<?= h(implode(';', $eligiblePairs)) ?>">
                            <input type="radio" name="preferred_master_id" value="<?= h($master['id']) ?>"
                                <?= (int) ($_POST['preferred_master_id'] ?? 0) === (int) $master['id'] ? 'checked' : '' ?>>
                            <span class="choice-avatar" aria-hidden="true"><?= h(mb_first_letter($master['first_name'])) ?></span>
                            <span class="choice-info">
                                <strong><?= h(name_view($master)) ?></strong>
                                <small>Стаж <?= h(app_master_experience($master['id'])) ?> · <?= h(implode(', ', $specialtyNames)) ?></small>
                            </span>
                        </label>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <small>Показываются только специалисты по выбранной технике и услуге. Если подходящих мастеров нет, можно отправить заявку без выбора, чтобы администратор уточнил возможность ремонта</small>
        </div>
        <div class="form-preview" aria-live="polite">
            <strong>Проверьте заявку</strong>
            <span id="request_summary">Выберите вид техники и услугу</span>
        </div>
        <label class="confirm-field">
            <input type="checkbox" name="confirmed" value="1" required <?= !empty($_POST['confirmed']) ? 'checked' : '' ?>>
            <span>Модель и серийный номер указаны верно *</span>
        </label>
        <p class="form-note">* Обязательные поля. Стоимость работы предварительная, без деталей</p>
        <div class="actions"><button class="btn btn-primary" type="submit">Отправить заявку →</button><a class="btn btn-secondary" href="/profile.php">Отмена</a></div>
    </form>
</section>
<?php page_end(); ?>
