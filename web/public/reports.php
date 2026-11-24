<?php
require_once dirname(__DIR__) . '/src/page.php';
require_roles(['admin']);

$report = app_report();
$totalOrders = $report['total_orders'];
$total = $report['total'];
$paid = $report['paid'];
$warranty = $report['warranty'];
$byStatus = $report['by_status'];

page_start('Сводка');
?>
<div class="page-header">
    <div>
        <span class="section-kicker">ОТЧЕТЫ И АНАЛИТИКА</span>
        <h1>
            Сводка по сервису
        </h1>
        <p>
            Текущие показатели по заказам, выполненным работам и оплатам
        </p>
    </div>
    <button class="btn btn-secondary" type="button" onclick="window.print()">Печать ↗</button>
</div>
<div class="stat-grid">
    <div class="stat-card">
        <span>Всего заказов</span><strong><?= $totalOrders ?></strong>
    </div>
    <div class="stat-card">
        <span>К оплате с учетом скидок</span><strong class="stat-money"><?= h(money_view($total)) ?></strong>
    </div>
    <div class="stat-card">
        <span>Оплачено</span><strong class="stat-money"><?= h(money_view($paid)) ?></strong>
    </div>
    <div class="stat-card">
        <span>Гарантийных обращений</span><strong><?= h($warranty) ?></strong>
    </div>
</div>
<section class="card">
    <div class="card-title-row">
        <h2>
            Заказы по статусам
        </h2>
        <span class="section-kicker">СОСТОЯНИЕ РЕМОНТОВ</span>
    </div>
    <div class="report-bars">
        <?php foreach($byStatus as $status=>$count): ?>
        <div class="report-row">
            <span><?= h($status) ?></span>
            <div class="report-meter">
                <div style="width:<?= $totalOrders?round($count/$totalOrders*100):0 ?>%">
                </div>
            </div>
            <strong><?= $count ?></strong>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php page_end(); ?>
