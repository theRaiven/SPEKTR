<?php
require_once dirname(__DIR__) . '/src/page.php';
require_roles(['admin', 'master']);

$id = (int) ($_GET['id'] ?? 0);
$order = app_order($id);
if (!$order) {
    http_response_code(404);
    exit('Заказ не найден');
}

$error = '';
$data = app_data();
$assignments = array_values(array_filter(
    $data['assignments'],
    fn($assignment) => $assignment['order_id'] == $id
));
$isMaster = current_user()['role'] === 'master';
$isAssigned = false;

foreach ($assignments as $assignment) {
    if ($assignment['master_id'] === current_user()['id']) {
        $isAssigned = true;
    }
}
if ($isMaster && !$isAssigned) {
    http_response_code(403);
    exit('Этот заказ вам не назначен');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        check_csrf();
        $action = post_text('action', 30);
        $values = ['id' => $id];

        if ($action === 'request_decide') {
            $values['decision'] = post_text('decision', 20);
            $values['reason'] = post_text('reason', 350);
        }
        if ($action === 'assign') {
            $values['master_id'] = post_int('master_id');
        }
        if ($action === 'status') {
            $values['status'] = post_text('status', 100);
            $values['comment'] = post_text('comment', 300);
        }
        if ($action === 'work_add') {
            $values['tariff_id'] = post_int('tariff_id');
        }
        if ($action === 'part_add') {
            $values['part_id'] = post_int('part_id');
            $values['quantity'] = post_int('quantity');
            $values['price'] = post_text('price', 20);
        }
        if ($action === 'discount_add') {
            $values['amount'] = post_text('amount', 20);
            $values['name'] = post_text('name', 100);
        }
        if ($action === 'payment_add') {
            $values['amount'] = post_text('amount', 20);
            $values['method'] = post_text('method', 40);
            $values['voucher_code'] = post_text('voucher_code', 100);
        }
        if ($action === 'warranty_add') {
            $values['work_id'] = post_int('work_id');
            $values['reason'] = post_text('reason', 350);
        }

        app_action($action, $values);
        flash('Изменения сохранены');
        go('/order.php?id=' . $id . '#operations');
    } catch (Throwable $errorData) {
        $error = app_error($errorData);
    }
    $data = app_data();
    $order = app_order($id);
}

$assigned = app_rows_for('assignments', 'order_id', $id);
$history = app_rows_for('history', 'order_id', $id);
usort($history, fn($first, $second) => strcmp($second['changed_at'], $first['changed_at']));
$works = app_rows_for('works', 'order_id', $id);
$parts = app_rows_for('order_parts', 'order_id', $id);
$payments = app_rows_for('payments', 'order_id', $id);
$warranties = app_rows_for('warranties', 'order_id', $id);
$cost = app_order_cost($id);
$canEdit = has_role(['admin']);
$hasActiveAssignment = false;
foreach ($assigned as $assignment) {
    if (empty($assignment['completed_at'])) {
        if (!$isMaster || (int) $assignment['master_id'] === (int) current_user()['id']) {
            $hasActiveAssignment = true;
            break;
        }
    }
}
$workStatuses = ['Передан мастеру', 'В ремонте'];
$canAddWork = $hasActiveAssignment && ($canEdit || $isAssigned)
    && in_array($order['status'], $workStatuses, true);
$canAddPart = $canEdit && in_array($order['status'], $workStatuses, true);
$qualifiedMasters = ($canEdit && in_array($order['status'], ['Принят', 'Передан мастеру'], true))
    ? app_qualified_masters($order) : [];
$canChange = $isMaster ? $hasActiveAssignment : $canEdit;
$choices = status_changes()[$order['status']] ?? [];
if ($isMaster) {
    $choices = array_values(array_filter(
        $choices,
        fn($status) => in_array($status, ['В ремонте', 'Готов к выдаче'], true)
    ));
}
if (!$works) {
    $choices = array_values(array_filter(
        $choices,
        fn($status) => $status !== 'Готов к выдаче'
    ));
}

page_start('Заказ №' . $id);
?>
<div class="page-header order-heading">
    <div>
        <span class="section-kicker">КАРТОЧКА РЕМОНТА</span>
        <h1>
            Заказ №<?= h($id) ?>
        </h1>
        <p>
            <?= h($order['device']) ?> · <?= h($order['client']) ?>
        </p>
    </div>
    <div class="actions">
        <span class="badge <?= h(status_class($order['status'])) ?>"><?= h($order['status']) ?></span>
        <button class="btn btn-secondary print-button" onclick="window.print()" type="button">Печать ↗</button>
        <a class="btn btn-secondary" href="/orders.php">← Все заказы</a>
    </div>
</div>
<?php form_error($error); ?>
<?php if ($canEdit && in_array($order['status'], ['Заявка', 'Ожидает приема устройства'], true)): ?>
<section class="card incoming-request">
    <div class="card-title-row"><h2><?= $order['status'] === 'Заявка' ? 'Новая заявка клиента' : 'Прием устройства' ?></h2><span class="badge status-orange"><?= h($order['status']) ?></span></div>
    <p class="muted">Проверьте описание и примите заявку либо откажите с указанием причины</p>
    <?php if (!empty($order['preferred_master_id'])): ?>
        <p><strong>Мастер, выбранный клиентом:</strong>
            <?= h(app_master_name($order['preferred_master_id'])) ?>
            (стаж <?= h(app_master_experience($order['preferred_master_id'])) ?>)
        </p>
    <?php endif; ?>
    <form method="post" class="inline-form">
        <?php form_csrf(); ?>
        <input type="hidden" name="action" value="request_decide">
        <input type="hidden" name="decision" value="accept">
        <button class="btn btn-primary">Принять заявку</button>
    </form>
    <form method="post" class="inline-form">
        <?php form_csrf(); ?>
        <input type="hidden" name="action" value="request_decide">
        <input type="hidden" name="decision" value="reject">
        <div class="field"><label for="reason">Причина отказа</label><input id="reason" name="reason" maxlength="350" required placeholder="Например, не обслуживаем эту категорию техники"></div>
        <button class="btn btn-secondary">Отказать</button>
    </form>
</section>
<?php endif; ?>
<div class="order-tabs">
    <a href="#details">Общая информация</a><a href="#operations">Работы и запчасти</a><a href="#finance">Оплата</a><a href="#history">История</a>
</div>
<div class="order-overview" id="details">
    <section class="card">
        <div class="card-title-row">
            <h2>
                Информация о заказе
            </h2>

        </div>
        <dl class="detail-list">
            <div>
                <dt>
                    Клиент
                </dt>
                <dd>
                    <?php if($canEdit): ?><a href="/client.php?id=<?= h($order['client_id']) ?>"><?= h($order['client']) ?></a><?php else: ?><?= h($order['client']) ?>
<?php endif; ?>
                </dd>
            </div>
            <div>
                <dt>
                    Устройство
                </dt>
                <dd>
                    <?= h($order['device']) ?>
                </dd>
            </div>
            <?php if (!empty($order['device_type_note'])): ?>
                <div><dt>Вид техники</dt><dd><?= h($order['device_type_note']) ?></dd></div>
            <?php endif; ?>
            <?php if (!empty($order['preferred_master_id'])): ?>
                <div><dt>Выбранный клиентом мастер</dt><dd><?= h(app_master_name($order['preferred_master_id'])) ?></dd></div>
            <?php endif; ?>
            <div>
                <dt>
                    Дата приема
                </dt>
                <dd>
                    <?= h(date_view($order['created_at'])) ?>
                </dd>
            </div>
            <div>
                <dt>
                    Планируемая готовность
                </dt>
                <dd>
                    <?= h(short_date($order['planned_date'])) ?>
                </dd>
            </div>
            <div>
                <dt>
                    Дата выдачи
                </dt>
                <dd>
                    <?= h(date_view($order['issued_at'])) ?>
                </dd>
            </div>
            <div>
                <dt>
                    Приоритет
                </dt>
                <dd>
                    <?= ['1'=>'Обычный','2'=>'Повышенный','3'=>'Срочный'][$order['priority']]??'Обычный' ?>
                </dd>
            </div>
        </dl>
    </section>
    <section class="card">
        <div class="card-title-row">
            <h2>
                Описание и состояние
            </h2>

        </div>
        <p class="small-label">Услуга</p>
        <p><?= h(app_find('tariffs', $order['service_id'] ?? 0)['name'] ?? 'Уточняется') ?></p>
        <p class="small-label">Предварительная стоимость</p>
        <p><?= app_estimate($order) === null ? 'Уточняется' : h(money_view(app_estimate($order))) ?></p>
        <p class="small-label">
            Неисправность
        </p>
        <p class="order-description">
            <?= h($order['problem']) ?>
        </p>
        <div class="subtle-divider">
        </div>
        <p class="small-label">
            Состояние при приеме
        </p>
        <p>
            <?= h($order['condition']?:'Не указано') ?>
        </p>
    </section>
</div>
<div class="order-columns" id="operations">
    <div class="order-main-column">
        <section class="card">
            <div class="card-title-row">
                <h2>
                    Назначения мастеров
                </h2>

            </div>
            <?php if ($assigned): ?>
            <div class="simple-list">
                <?php foreach($assigned as $a): ?>
                <div class="list-line">
                    <span><strong><?= h(app_master_name($a['master_id'])) ?></strong><small>Назначен <?= h(date_view($a['assigned_at'])) ?></small></span><span class="mini-pill">Мастер</span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <p class="muted">
                Мастер пока не назначен.
            </p>
            <?php endif; ?>
        <?php if ($canEdit && in_array($order['status'], ['Принят', 'Передан мастеру'], true)): ?>
            <form method="post" class="inline-form">
                <?php form_csrf(); ?><input type="hidden" name="action" value="assign">
                <div class="field">
                    <label for="master_id">Назначить мастера</label>
                    <select id="master_id" name="master_id" required><option value="">Выберите сотрудника</option>
                        <?php foreach ($qualifiedMasters as $master): ?>
    <?php if (!empty($order['preferred_master_id']) && (int) $order['preferred_master_id'] !== (int) $master['id']) continue; ?>
    <option value="<?= h($master['id']) ?>" <?= (int) ($order['preferred_master_id'] ?? 0) === (int) $master['id'] ? 'selected' : '' ?>><?= h(name_view($master)) ?>, стаж <?= h(app_master_experience($master['id'])) ?></option>
<?php endforeach; ?></select>
                </div>
                <button class="btn btn-secondary">Назначить</button>
            </form>
            <?php if (!empty($order['preferred_master_id'])): ?>
                <p class="form-note">Клиент выбрал конкретного мастера. Замена возможна только после согласования с ним</p>
            <?php endif; ?>
            <?php if (!$qualifiedMasters): ?>
                <p class="form-note">Сейчас нет мастера с нужной специализацией</p>
            <?php endif; ?>
            <?php endif; ?>
        </section>
        <section class="card">
            <div class="card-title-row">
                <h2>
                    Выполненные работы
                </h2>

            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Работа</th><th>Дата</th><th class="number">Стоимость</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($works as $w): ?>
                        <tr>
                            <td><?= h($w['name']) ?></td><td><?= h(short_date($w['done_at'])) ?></td><td class="number"><?= h(money_view($w['price'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
<?php if(!$works): ?>
                        <tr>
                            <td colspan="3" class="empty-row">Работ пока нет</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($canAddWork): ?>
            <form method="post" class="inline-form">
                <?php form_csrf(); ?><input type="hidden" name="action" value="work_add">
                <div class="field">
                    <label for="tariff_id">Добавить выполненную работу</label>
                    <select id="tariff_id" name="tariff_id" required><option value="">Выберите из тарифов</option>
                        <?php foreach($data['tariffs'] as $t): ?><option value="<?= h($t['id']) ?>"><?= h($t['name'].' · '.money_view($t['price'])) ?></option>
                        <?php endforeach; ?></select>
                </div>
                <button class="btn btn-secondary">Добавить</button>
            </form>
            <?php endif; ?>
        </section>
        <section class="card">
            <div class="card-title-row">
                <h2>
                    Запчасти по заказу
                </h2>

            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Наименование</th><th>Кол-во</th><th class="number">Цена за шт.</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($parts as $p): $part=app_lookup($data['parts'],$p['part_id']); ?>
                        <tr>
                            <td><?= h($part['name']??'Запчасть') ?></td><td><?= h($p['quantity']) ?></td><td class="number"><?= h(money_view($p['price'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
<?php if(!$parts): ?>
                        <tr>
                            <td colspan="3" class="empty-row">Запчасти не добавлены</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($canAddPart): ?>
            <form method="post" class="inline-form order-part-form">
                <?php form_csrf(); ?><input type="hidden" name="action" value="part_add">
                <div class="field">
                    <label for="part_id">Запчасть</label>
                    <select id="part_id" name="part_id" required><option value="">Выберите</option>
                        <?php foreach($data['parts'] as $p): ?><option value="<?= h($p['id']) ?>"><?= h($p['name'].' · остаток '.$p['stock']) ?></option>
                        <?php endforeach; ?></select>
                </div>
                <div class="field field-short">
                    <label for="quantity">Кол-во</label><input id="quantity" name="quantity" type="number" min="1" value="1" required>
                </div>
                <button class="btn btn-secondary">Списать</button>
            </form>
            <?php endif; ?>
        </section>
        <section class="card" id="finance">
            <div class="card-title-row">
                <h2>
                    Оплата
                </h2>

            </div>
            <div class="cost-row">
                <span>Стоимость работ</span><strong><?= h(money_view($cost['works'])) ?></strong>
            </div>
            <div class="cost-row">
                <span>Запчасти</span><strong><?= h(money_view($cost['parts'])) ?></strong>
            </div>
            <div class="cost-row">
                <span>Скидки</span><strong>− <?= h(money_view($cost['discount'])) ?></strong>
            </div>
            <div class="cost-row cost-total">
                <span>Итого</span><strong><?= h(money_view($cost['total'])) ?></strong>
            </div>
            <div class="cost-row">
                <span>Оплачено</span><strong><?= h(money_view($cost['paid'])) ?></strong>
            </div>
            <div class="cost-row">
                <span>Остаток</span><strong><?= h(money_view($cost['due'])) ?></strong>
            </div>
            <?php $discounts=app_rows_for('discounts','order_id',$id); if($discounts): ?>
            <div class="simple-list">
                <?php foreach($discounts as $discount): ?>
                <div class="list-line">
                    <span><strong><?= h($discount['name']) ?></strong><small>Скидка</small></span><strong>−<?= h(money_view($discount['amount'])) ?></strong>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        <?php if($payments): ?>
            <div class="simple-list">
                <?php foreach($payments as $pay): ?>
                <div class="list-line">
                    <span><strong><?= h(money_view($pay['amount'])) ?></strong>
                        <small><?= h($pay['method'] . ' · ' . date_view($pay['paid_at'])) ?><?php if (!empty($pay['external_reference'])): ?> · <?= h($pay['external_reference']) ?><?php endif; ?></small>
                    </span><span class="badge status-green"><?= h($pay['status']) ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        <?php if ($canEdit && $order['status'] === 'Готов к выдаче' && $cost['paid'] <= 0 && $cost['total'] > 0): ?>
            <form method="post" class="inline-form">
                <?php form_csrf(); ?><input type="hidden" name="action" value="discount_add">
                <div class="field">
                    <label for="discount_name">Основание скидки</label><input id="discount_name" name="name" required maxlength="100" placeholder="Например, льгота">
                </div>
                <div class="field field-short">
                    <label for="discount_amount">Сумма скидки, ₽</label><input id="discount_amount" name="amount" type="number" min="0.01" step="0.01" required>
                </div>
                <button class="btn btn-secondary">Учесть скидку</button>
            </form>
            <?php endif; ?>
        <?php if($canEdit && $order['status'] === 'Готов к выдаче' && $cost['due'] > 0): ?>
            <form method="post" class="inline-form">
                <?php form_csrf(); ?><input type="hidden" name="action" value="payment_add">
                <div class="field">
                    <label for="amount">Сумма, ₽</label><input id="amount" name="amount" type="number" min="0.01" step="0.01" required>
                </div>
                <div class="field">
                    <label for="method">Способ оплаты</label><select id="method" name="method"><option>Банковская карта</option><option>Наличные</option><option>Предоплаченный ваучер</option></select>
                </div>
                <div class="field"><label for="voucher_code">Код ваучера (только для оплаты ваучером)</label>
                    <input id="voucher_code" name="voucher_code" maxlength="100" placeholder="Код, который указал клиент"></div>
                <button class="btn btn-secondary">Принять оплату</button>
            </form>
            <?php endif; ?>
        </section>
        <section class="card">
            <div class="card-title-row">
                <h2>
                    Гарантийные обращения
                </h2>

            </div>
            <p class="muted">
                Гарантия привязывается к выполненной работе из предыдущего ремонта того же устройства.
            </p>
            <?php foreach($warranties as $w): ?>
            <div class="list-line">
                <span><strong><?= h($w['reason']) ?></strong><small><?= h(short_date($w['opened_at'])) ?> · <?= h($w['status']) ?></small></span>
            </div>
            <?php endforeach; ?>
        <?php if($canEdit && $order['status'] !== 'Заявка'): ?>
<?php
$prior = [];
foreach ($data['works'] as $work) {
    if ((int) $work['order_id'] === $id) {
        continue;
    }
    $previous = app_order($work['order_id']);
    if (!$previous || (int) $previous['device_id'] !== (int) $order['device_id']
        || !in_array($previous['status'], ['Выдан', 'Закрыт'], true)) {
        continue;
    }
    if (app_warranty_work_valid($work['id'])) {
        $prior[] = $work;
    }
}
?>
        <?php if ($prior): ?>
            <form method="post" class="inline-form">
                <?php form_csrf(); ?><input type="hidden" name="action" value="warranty_add">
                <div class="field">
                    <label for="work_id">Работа по прошлому заказу</label>
                    <select id="work_id" name="work_id"><?php foreach($prior as $w): ?><option value="<?= h($w['id']) ?>">№<?= h($w['order_id']) ?> · <?= h($w['name']) ?></option>
                        <?php endforeach; ?></select>
                </div>
                <div class="field">
                    <label for="warranty_reason">Причина обращения</label><input id="warranty_reason" name="reason" required maxlength="350">
                </div>
                <button class="btn btn-secondary">Оформить гарантию</button>
            </form>
            <?php else: ?>
            <p class="form-note">
                По этому устройству пока нет завершенных работ из предыдущих заказов.
            </p>
            <?php endif; ?>
<?php endif; ?>
        </section>
    </div>
    <aside class="order-side-column">
        <section class="card" id="history">
            <div class="card-title-row">
                <h2>
                    История статусов
                </h2>

            </div>
            <ol class="timeline">
                <?php foreach($history as $hrow): ?>
                <li>
                    <strong><?= h($hrow['status']) ?></strong><span><?= h(date_view($hrow['changed_at'])) ?></span><?php if($hrow['comment']): ?><small><?= h($hrow['comment']) ?></small><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ol>
        </section>
        <?php if ($canChange && $choices): ?>
        <section class="card status-form">
            <h2>
                Изменить статус
            </h2>
            <p class="muted">
                Переход сохранится в истории заказа.
            </p>
            <form method="post" class="stack-form">
                <?php form_csrf(); ?><input type="hidden" name="action" value="status">
                <div class="field">
                    <label for="status">Новый статус</label>
                    <select id="status" name="status" required><option value="">Выберите</option>
                        <?php foreach ($choices as $choice): ?><option><?= h($choice) ?></option>
                        <?php endforeach; ?></select>
                </div>
                <div class="field">
                    <label for="comment">Комментарий</label><textarea name="comment" id="comment" rows="3" maxlength="300"></textarea>
                </div>
                <button class="btn btn-primary btn-wide">Сохранить статус</button>
            </form>
        </section>
        <?php endif; ?>
    </aside>
</div>
<?php page_end(); ?>
