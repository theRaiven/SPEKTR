<?php
require_once dirname(__DIR__) . '/src/page.php';

if (current_user()) {
    go(home_for_user(current_user()));
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        check_csrf();
        if (($_POST['password'] ?? '') !== ($_POST['password_repeat'] ?? '')) {
            throw new InvalidArgumentException('Пароли не совпадают');
        }
        $values = [
            'last_name' => post_text('last_name', 100),
            'first_name' => post_text('first_name', 100),
            'middle_name' => post_text('middle_name', 100),
            'phone' => post_text('phone', 25),
            'email' => post_text('email', 120),
            'login' => post_text('login', 60),
            'password' => (string) ($_POST['password'] ?? ''),
        ];
        app_action('register', $values);
        flash('Аккаунт создан. Теперь войдите');
        go('/login.php');
    } catch (Throwable $exception) {
        $error = app_error($exception);
    }
}

page_start('Регистрация');
?>
<section class="auth-layout">
    <div class="auth-visual">
        <div class="auth-visual-overlay">
            <span class="section-kicker">СЕРВИСНЫЙ ЦЕНТР</span>
            <h1>Ремонт техники<br>под контролем</h1>
            <p>Создайте обращение и следите за его выполнением</p>
        </div>
    </div>
    <div class="auth-card auth-card-wide">
        <span class="section-kicker">НОВЫЙ АККАУНТ</span>
        <h1>Регистрация</h1>
        <p class="muted">Создайте личный кабинет клиента</p>
        <?php form_error($error); ?>
        <form method="post" class="stack-form">
            <?php form_csrf(); ?>
            <div class="form-row">
                <div class="field"><label for="last_name">Фамилия</label><input name="last_name" id="last_name" maxlength="100" autocomplete="family-name" required value="<?= h($_POST['last_name'] ?? '') ?>"></div>
                <div class="field"><label for="first_name">Имя</label><input name="first_name" id="first_name" maxlength="100" autocomplete="given-name" required value="<?= h($_POST['first_name'] ?? '') ?>"></div>
            </div>
            <div class="field"><label for="middle_name">Отчество</label><input name="middle_name" id="middle_name" maxlength="100" autocomplete="additional-name" value="<?= h($_POST['middle_name'] ?? '') ?>"></div>
            <div class="form-row">
                <div class="field"><label for="phone">Телефон</label><input type="tel" name="phone" id="phone" required maxlength="25" placeholder="+7 999 123-45-67" autocomplete="tel" inputmode="tel" value="<?= h($_POST['phone'] ?? '') ?>"></div>
                <div class="field"><label for="email">Электронная почта</label><input type="email" name="email" id="email" autocomplete="email" maxlength="120" value="<?= h($_POST['email'] ?? '') ?>"></div>
            </div>
            <div class="field"><label for="login">Логин</label><input name="login" id="login" required maxlength="60" autocomplete="username" value="<?= h($_POST['login'] ?? '') ?>"></div>
            <div class="field"><label for="password">Пароль</label><input type="password" name="password" id="password" minlength="8" maxlength="128" required autocomplete="new-password" aria-describedby="password_help"><small id="password_help">Не меньше 8 символов</small></div>
            <div class="field"><label for="password_repeat">Повторите пароль</label><input type="password" name="password_repeat" id="password_repeat" minlength="8" required autocomplete="new-password"></div>
            <button class="btn btn-primary btn-wide" type="submit">Создать аккаунт <span>→</span></button>
        </form>
        <p class="auth-switch">Уже зарегистрированы? <a href="/login.php">Войти →</a></p>
    </div>
</section>
<?php page_end(); ?>
