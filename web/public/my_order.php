<?php
require_once dirname(__DIR__) . '/src/page.php';
require_roles(['client']);

$id = (int) ($_GET['id'] ?? 0);
$order = app_order($id);
if (!$order || $order['client_id'] !== current_user()['id']) {
    http_response_code(404);
    exit('Заказ не найден');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        check_csrf();
        $action = post_text('action', 30);
        if ($action === 'discount') {
            app_action('client_discount', [
                'id' => $id,
                'kind' => post_text('kind', 20),
                'code' => post_text('code', 40),
            ]);
        } elseif ($action === 'review') {
            app_action('review_add', [
                'work_id' => post_int('work_id'),
                'rating' => post_int('rating'),
                'comment' => post_text('comment', 600),
            ]);
        } else {
            throw new InvalidArgumentException('Неизвестное действие');
        }
        flash('Сохранено');
        go('/my_order.php?id=' . $id);
    } catch (Throwable $exception) {
        $error = app_error($exception);
    }
}

$data = app_data();
$cost = app_order_cost($id);
$master = order_master($id);
$history = app_rows_for('history', 'order_id', $id);
$works = app_rows_for('works', 'order_id', $id);
$payments = app_rows_for('payments', 'order_id', $id);
$discounts = app_rows_for('discounts', 'order_id', $id);
$reviews = $data['reviews'];
$estimate = app_estimate($order);
$finished = in_array($order['status'], ['Готов к выдаче', 'Выдан', 'Закрыт'], true);
usort($history, fn($a, $b) => strcmp($b['changed_at'], $a['changed_at']));

page_start('Мой заказ №' . $id);
?>
<div class="page-header">
    <div><span class="section-kicker">МОЕ ОБРАЩЕНИЕ</span><h1>Заказ №<?= h($id) ?></h1><p><?= h($order['device']) ?></p></div>
    <div class="actions"><span class="badge <?= h(status_class($order['status'])) ?>"><?= h($order['status']) ?></span><a href="/profile.php" class="btn btn-secondary">← Мои заказы</a></div>
</div>
<?php form_error($error); ?>
<div class="two-col">
    <section class="card">
        <div class="card-title-row"><h2>Информация о заказе</h2><span class="section-kicker">ДЕТАЛИ</span></div>
        <dl class="detail-list">
            <div><dt>Устройство</dt><dd><?= h($order['device']) ?></dd></div>
            <div><dt>Неисправность</dt><dd><?= h($order['problem']) ?></dd></div>
            <?php if (!empty($order['device_type_note'])): ?>
                <div><dt>Вид техники</dt><dd><?= h($order['device_type_note']) ?></dd></div>
            <?php endif; ?>
            <div><dt>Дата обращения</dt><dd><?= h(date_view($order['created_at'])) ?></dd></div>
            <div><dt>Услуга</dt><dd><?= h(app_find('tariffs', $order['service_id'] ?? 0)['name'] ?? 'Уточняется') ?></dd></div>
            <?php if (!empty($order['preferred_master_id'])): ?>
                <div><dt>Выбранный при заявке мастер</dt><dd><?= h(app_master_name($order['preferred_master_id'])) ?></dd></div>
            <?php endif; ?>
            <div><dt>Предварительная стоимость работы</dt><dd><?= $estimate === null ? 'После диагностики' : h(money_view($estimate)) ?></dd></div>
        </dl>
        <?php if ($order['status'] === 'Отказано'): ?><div class="notice error"><strong>Причина отказа:</strong> <?= h($order['reject_reason'] ?? '') ?></div><?php endif; ?>
    </section>
    <section class="card">
        <div class="card-title-row"><h2>Ваш мастер</h2><span class="section-kicker">КОНТАКТ</span></div>
        <?php if ($master): ?>
            <div class="master-profile"><span class="profile-avatar"><?= h(mb_first_letter($master['first_name'])) ?></span><div><h2><?= h(name_view($master)) ?></h2><p>Мастер сервиса, стаж <?= h(app_master_experience($master['id'])) ?></p></div>
            </div>
            <div class="cost-row"><span>Контактный телефон</span><strong><?= h($master['phone']) ?></strong></div>
        <?php else: ?>
            <p class="muted">Мастер появится здесь после подтверждения заявки администратором</p>
        <?php endif; ?>
    </section>
</div>
<?php if ($finished): ?>
<p class="muted">Ваучер учитывается как оплата после подтверждения администратором</p>
<?php endif; ?>
<div class="order-columns">
    <div class="order-main-column">
        <section class="card">
            <div class="card-title-row"><h2>Статус и история</h2><span class="section-kicker">ЭТАПЫ</span></div>
            <ol class="timeline">
                <?php foreach ($history as $item): ?>
                    <li><strong><?= h($item['status']) ?></strong><span><?= h(date_view($item['changed_at'])) ?></span><small><?= h($item['comment'] ?? '') ?></small></li>
                <?php endforeach; ?>
            </ol>
        </section>
        <?php if ($finished): ?>
        <section class="card" id="payment">
            <div class="card-title-row"><h2>Стоимость и оплата</h2><span class="section-kicker">РАСЧЕТ</span></div>
            <div class="cost-row"><span>Работы</span><strong><?= h(money_view($cost['works'])) ?></strong></div>
            <div class="cost-row"><span>Запчасти</span><strong><?= h(money_view($cost['parts'])) ?></strong></div>
            <div class="cost-row"><span>Скидки</span><strong>− <?= h(money_view($cost['discount'])) ?></strong></div>
            <div class="cost-row cost-total"><span>Итого</span><strong><?= h(money_view($cost['total'])) ?></strong></div>
            <div class="cost-row"><span>Оплачено</span><strong><?= h(money_view($cost['paid'])) ?></strong></div>
            <div class="cost-row"><span>К оплате</span><strong><?= h(money_view($cost['due'])) ?></strong></div>
            <p class="form-note">Оплату проводит администратор сервиса. Здесь отображается ее состояние</p>
            <?php if ($payments): ?>
                <?php foreach ($payments as $pay): ?>
                    <p class="mini-pill"><?= h($pay['method']) ?> · <?= h(money_view($pay['amount'])) ?> · <?= h($pay['status']) ?></p>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php if ($order['status'] === 'Готов к выдаче' && $cost['due'] > 0): ?>
                <div class="subtle-divider"></div>
                <h3>Льготы, ваучеры и промокоды</h3>
                <p class="muted">Льготы и промокоды уменьшают стоимость, а ваучер учитывается как оплата</p>
                <?php foreach (['benefit' => 'Применить мою льготу', 'voucher' => 'Использовать ваучер', 'promo' => 'Применить промокод'] as $kind => $text): ?>
                <form method="post" class="inline-form">
                    <?php form_csrf(); ?>
                    <input type="hidden" name="action" value="discount">
                    <input type="hidden" name="kind" value="<?= h($kind) ?>">
                    <?php if ($kind !== 'benefit'): ?>
                        <div class="field"><label for="code_<?= h($kind) ?>"><?= $kind === 'voucher' ? 'Код ваучера' : 'Промокод' ?></label>
                    <input id="code_<?= h($kind) ?>" name="code" maxlength="40" required placeholder="Введите код"></div>
                    <?php endif; ?>
                    <button class="btn btn-secondary" type="submit"><?= h($text) ?></button>
                </form>
                <?php endforeach; ?>
            <?php endif; ?>
            <?php foreach ($discounts as $discount): ?><p class="form-note"><?= h($discount['name']) ?>: −<?= h(money_view($discount['amount'])) ?></p><?php endforeach; ?>
        </section>
        <?php endif; ?>
        <?php if (in_array($order['status'], ['Выдан', 'Закрыт'], true)): ?>
        <section class="card" id="reviews">
            <div class="card-title-row"><h2>Отзыв о ремонте</h2><span class="section-kicker">ОЦЕНКА</span></div>
            <?php if (!$works): ?>
                <p class="muted">После записи выполненных работ здесь появится форма отзыва</p>
            <?php endif; ?>
            <?php foreach ($works as $work): ?>
                <?php
                    $review = null;
                    foreach ($reviews as $item) {
                        if ((int) $item['work_id'] === (int) $work['id']) {
                            $review = $item;
                            break;
                        }
                    }
                ?>
                <h3><?= h($work['name']) ?></h3>
                <?php if ($review): ?>
                    <p>Оценка: <?= h($review['rating']) ?> из 5</p><p><?= h($review['comment']) ?></p>
                <?php else: ?>
                <form method="post" class="stack-form review-form">
                    <?php form_csrf(); ?>
                    <input type="hidden" name="action" value="review">
                    <input type="hidden" name="work_id" value="<?= h($work['id']) ?>">
                    <div class="field"><label for="rating_<?= h($work['id']) ?>">Оценка</label>
                    <select id="rating_<?= h($work['id']) ?>" name="rating" required><option value="">Выберите</option>
                        <?php for ($rating = 5; $rating >= 1; $rating--): ?><option value="<?= $rating ?>"><?= $rating ?> / 5</option>
                        <?php endfor; ?></select>
                </div>
                    <div class="field"><label for="comment_<?= h($work['id']) ?>">Комментарий</label><textarea id="comment_<?= h($work['id']) ?>" name="comment" maxlength="600" rows="3"></textarea></div>
                    <button type="submit" class="btn btn-primary">Отправить отзыв</button>
                </form>
                <?php endif; ?>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>
    </div>
    <aside class="order-side-column">
        <section class="card">
            <h2>Что дальше?</h2>
            <?php if ($order['status'] === 'Заявка'): ?>
                <p class="muted">Администратор рассматривает ваше обращение. После принятия заявки здесь появится назначенный мастер</p>
            <?php elseif ($order['status'] === 'Отказано'): ?>
                <p class="muted">Обращение отклонено, причина указана в карточке заказа</p>
            <?php elseif ($finished): ?>
                <p class="muted">Ремонт завершен. Итоговая стоимость и сведения об оплате отображаются в этом кабинете</p>
            <?php else: ?>
                <p class="muted">Следите за этапами ремонта в этом разделе</p>
            <?php endif; ?>
        </section>
    </aside>
</div>
<?php page_end(); ?>
