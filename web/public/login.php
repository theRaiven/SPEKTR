<?php
require_once dirname(__DIR__) . '/src/page.php';

if (current_user()) {
    go(home_for_user(current_user()));
}

$error = '';
$login = trim((string) ($_POST['username'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        check_csrf();
        $password = (string) ($_POST['password'] ?? '');
        if (!$login || !$password) {
            throw new InvalidArgumentException('Введите логин и пароль');
        }
        $user = app_login($login, $password);
        if (!$user) {
            throw new InvalidArgumentException('Неправильный логин или пароль');
        }

        session_regenerate_id(true);
        $_SESSION['user'] = $user;
        go(home_for_user($user));
    } catch (Throwable $errorData) {
        $error = app_error($errorData);
    }
}

page_start('Вход');
?>
<section class="auth-layout">
    <div class="auth-visual">
        <div class="auth-visual-overlay">
            <span class="section-kicker">СЕРВИСНЫЙ ЦЕНТР</span>
<h1>Ремонт и обслуживание<br>электронной техники</h1>
            <p>
                Диагностика, ремонт и гарантийное обслуживание техники.
            </p>
        </div>
    </div>
    <div class="auth-card">
        <span class="section-kicker">ЛИЧНЫЙ КАБИНЕТ</span>
        <h1>
            Вход в личный кабинет
        </h1>
        <p class="muted">
            Введите логин и пароль для входа
        </p>
        <?php form_error($error); ?>
        <form method="post" class="stack-form">
            <?php form_csrf(); ?>
            <div class="field">
                <label for="username">Логин</label><input id="username" name="username" autocomplete="username" required value="<?= h($login) ?>" placeholder="Ваш логин">
            </div>
            <div class="field">
                <label for="password">Пароль</label>
                <div class="password-wrap">
                    <input id="password" name="password" type="password" autocomplete="current-password" required placeholder="Пароль"><button class="password-toggle" type="button" aria-label="Показать пароль">Показать</button>
                </div>
            </div>
            <button class="btn btn-primary btn-wide" type="submit">Войти <span>→</span></button>
        </form>
        <p class="auth-switch">Нет аккаунта? <a href="/register.php">Зарегистрироваться →</a></p>
    </div>
</section>
<?php page_end(); ?>
