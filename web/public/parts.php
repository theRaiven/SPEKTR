<?php
require_once dirname(__DIR__) . '/src/page.php';
require_roles(['admin']);

$data = app_data();
page_start('Запчасти');
?>
<div class="page-header">
    <div>
        <span class="section-kicker">УЧЕТ МАТЕРИАЛОВ</span>
        <h1>
            Запчасти
        </h1>
        <p>
            Номенклатура и складские остатки
        </p>
    </div>
</div>
<div class="card table-card">
    <div class="table-heading">
        <div>
            <h2>
                Остатки
            </h2>
            <p>
                Количество уменьшается после добавления запчасти к ремонту.
            </p>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Артикул</th><th>Наименование</th><th>Остаток</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($data['parts'] as $p): ?>
                <tr>
                    <td><?= h($p['article']) ?></td><td><?= h($p['name']) ?></td><td><span class="badge <?= $p['stock']>0?'status-green':'status-red' ?>"><?= h($p['stock']) ?> шт.</span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php page_end(); ?>
