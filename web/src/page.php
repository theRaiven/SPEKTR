<?php
require_once __DIR__ . '/common.php';

function site_logo()
{
    ?>
    <span class="logo-mark" aria-hidden="true"><i></i><i></i><i></i><i></i></span>
    <span class="logo-name">СПЕКТР</span>
    <?php
}

function page_start($title)
{
    $staff = current_user();
    $current = basename($_SERVER['SCRIPT_NAME']);
    $public = in_array($current, ['index.php', 'track.php', 'login.php', 'register.php'], true);
    $message = read_flash();
    $clientPage = $staff && $staff['role'] === 'client';
    ?>
<!doctype html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#111c2a">
    <meta name="description" content="СПЕКТР  -  сервисный центр электроники. Проверка статуса ремонта по номеру заказа и телефону">
    <title><?= h($title) ?> | СПЕКТР  -  сервис электроники</title>
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="/assets/style.css">
    <script src="/assets/site.js" defer></script>
</head>
<body class="<?= $public ? 'public-page' : 'staff-page' ?> <?= $current === 'login.php' ? 'auth-page' : '' ?>">
<?php if ($public): ?>
<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="/" aria-label="СПЕКТР  -  главная"><?php site_logo(); ?></a>
        <button class="menu-toggle" type="button" aria-label="Открыть меню" aria-controls="site-nav" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
        <nav class="site-nav" id="site-nav" aria-label="Основная навигация">
            <a href="/register.php" <?= $current === 'register.php' ? 'aria-current="page"' : '' ?>>Регистрация</a>
            <a href="<?= $staff ? home_for_user($staff) : '/login.php' ?>" class="header-account">
                <?= $staff ? 'Личный кабинет' : 'Войти' ?><span aria-hidden="true">↗</span>
            </a>
        </nav>
    </div>
</header>
<main class="public-container">
<?php else: ?>
<div class="dashboard-shell">
    <aside class="sidebar">
        <a class="brand sidebar-brand" href="/" aria-label="СПЕКТР  -  главная"><?php site_logo(); ?></a>

        <div class="sidebar-divider"></div>
        <div class="sidebar-caption">Меню</div>
        <nav class="sidebar-nav" aria-label="Разделы системы">
            <?php if ($clientPage): ?>
                <a class="<?= $current === 'profile.php' ? 'active' : '' ?>" href="/profile.php"><span class="nav-symbol">◎</span> Мой профиль</a>
                <a class="<?= $current === 'request_new.php' ? 'active' : '' ?>" href="/request_new.php"><span class="nav-symbol">＋</span> Новое обращение</a>
                <a class="<?= $current === 'my_order.php' ? 'active' : '' ?>" href="/profile.php#orders"><span class="nav-symbol">▤</span> История обращений</a>
            <?php else: ?>
            <a class="<?= $current === 'staff_profile.php' ? 'active' : '' ?>" href="/staff_profile.php"><span class="nav-symbol">◎</span> Мой профиль</a>
            <a class="<?= in_array($current, ['orders.php', 'order.php', 'order_new.php'], true) ? 'active' : '' ?>" href="/orders.php"><span class="nav-symbol" aria-hidden="true">▤</span> Заказы</a>
            <?php if (has_role(['admin'])): ?>
                <a class="<?= in_array($current, ['clients.php', 'client.php', 'client_new.php'], true) ? 'active' : '' ?>" href="/clients.php"><span class="nav-symbol" aria-hidden="true">♧</span> Клиенты</a>
                <a class="<?= in_array($current, ['devices.php', 'device.php', 'device_new.php'], true) ? 'active' : '' ?>" href="/devices.php"><span class="nav-symbol" aria-hidden="true">▣</span> Устройства</a>
            <?php endif; ?>
            <a class="<?= $current === 'masters.php' ? 'active' : '' ?>" href="/masters.php"><span class="nav-symbol" aria-hidden="true">◎</span> Мастера</a>
            <?php if (has_role(['admin'])): ?>
                <a class="<?= $current === 'parts.php' ? 'active' : '' ?>" href="/parts.php"><span class="nav-symbol" aria-hidden="true">▦</span> Запчасти</a>
                <a class="<?= $current === 'reports.php' ? 'active' : '' ?>" href="/reports.php"><span class="nav-symbol" aria-hidden="true">▥</span> Сводка</a>
            <?php endif; ?>
            <?php endif; ?>
        </nav>
        <div class="sidebar-bottom">

            <div class="sidebar-user">
                <span class="avatar"><?= h(mb_first_letter($staff['full_name'] ?? 'С')) ?></span>
                <div><strong><?= h($staff['full_name'] ?? '') ?></strong><small><?= h(role_label($staff['role'] ?? '')) ?></small></div>
            </div>
            <form method="post" action="/logout.php">
                <input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>">
                <button type="submit" class="logout-button">Выйти <span>→</span></button>
            </form>
        </div>
    </aside>
    <main class="dashboard-main">
        <div class="dashboard-topline">
            <a href="<?= $clientPage ? '/profile.php' : '/staff_profile.php' ?>">Личный кабинет</a>
            <span class="crumb-arrow">/</span>
            <strong><?= h($title) ?></strong>
        </div>
        <div class="dashboard-content">
<?php endif; ?>
<?php if ($message): ?><div class="notice success" role="status"><?= h($message) ?></div><?php endif; ?>
<?php
}

function page_end()
{
    $current = basename($_SERVER['SCRIPT_NAME']);
    $public = in_array($current, ['index.php', 'track.php', 'login.php', 'register.php'], true);
    if ($public): ?>
</main>
<footer class="site-footer">
    <div class="footer-inner">
        <a class="footer-brand" href="/">СПЕКТР</a>
        <span>Ремонт и обслуживание электронной техники</span>
        <a href="/register.php">Личный кабинет <span>↗</span></a>
    </div>
</footer>
<?php else: ?>
        </div>
    </main>
</div>
<?php endif; ?>
</body>
</html>
<?php
}
