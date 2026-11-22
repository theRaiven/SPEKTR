<?php
require_once __DIR__ . '/repository.php';

// даты для заказов считаем по времени Новосибирска
date_default_timezone_set('Asia/Novosibirsk');

if (session_status() !== PHP_SESSION_ACTIVE) {
    // сессии хранятся внутри проекта, чтобы не зависеть от настроек xampp
    $sessionsDir = dirname(__DIR__) . '/var/sessions';
    if (!is_dir($sessionsDir) && !mkdir($sessionsDir, 0775, true) && !is_dir($sessionsDir)) {
        throw new RuntimeException('Не удалось создать папку для сессий');
    }
    session_save_path($sessionsDir);
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

function h($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function go($url)
{
    header('Location: ' . $url);
    exit;
}

function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function check_csrf()
{
    if (!hash_equals(csrf_token(), (string) ($_POST['csrf'] ?? ''))) {
        throw new InvalidArgumentException('Форма устарела. Обновите страницу');
    }
}

function flash($text)
{
    $_SESSION['flash'] = $text;
}

function read_flash()
{
    $message = $_SESSION['flash'] ?? '';
    unset($_SESSION['flash']);
    return $message;
}

function staff_user()
{
    return $_SESSION['staff'] ?? null;
}

function require_login()
{
    if (!staff_user()) {
        go('/login.php');
    }
}

function require_roles($roles)
{
    require_login();
    if (!in_array(staff_user()['role'], $roles, true)) {
        http_response_code(403);
        exit('Нет доступа к этому разделу');
    }
}

function has_role($roles)
{
    return staff_user() && in_array(staff_user()['role'], $roles, true);
}

function date_view($value)
{
    return $value ? date('d.m.Y H:i', strtotime($value)) : ' - ';
}

function short_date($value)
{
    return $value ? date('d.m.Y', strtotime($value)) : ' - ';
}

function money_view($value)
{
    return number_format((float) $value, 2, ',', ' ') . ' ₽';
}

function name_view($person)
{
    return trim(
        ($person['last_name'] ?? '') . ' ' .
        ($person['first_name'] ?? '') . ' ' .
        ($person['middle_name'] ?? '')
    );
}

function mb_first_letter($name)
{
    preg_match('/^./us', trim((string) $name), $match);
    return $match[0] ?? 'С';
}

function role_label($role)
{
    $names = [
        'manager' => 'Администратор',
        'client' => 'Клиент',
        'reception' => 'Приемщик',
        'master' => 'Мастер',
    ];
    return $names[$role] ?? 'Сотрудник';
}

function status_class($status)
{
    $classes = [
        'Заявка' => 'status-orange',
        'Принят' => 'status-neutral',
        'Передан мастеру' => 'status-blue',
        'Диагностика' => 'status-blue',
        'Ожидает согласования' => 'status-orange',
        'В ремонте' => 'status-purple',
        'Готов к выдаче' => 'status-green',
        'Выдан' => 'status-neutral',
        'Отменен' => 'status-red',
        'Отказано' => 'status-red',
    ];
    return $classes[$status] ?? 'status-neutral';
}

function status_changes()
{
    return [
        'Заявка' => [],
        'Принят' => ['Диагностика', 'Отменен'],
        'Передан мастеру' => ['Диагностика', 'В ремонте'],
        'Диагностика' => ['Ожидает согласования', 'В ремонте', 'Отменен'],
        'Ожидает согласования' => ['В ремонте', 'Отменен'],
        'В ремонте' => ['Готов к выдаче'],
        'Готов к выдаче' => ['Выдан', 'В ремонте'],
        'Выдан' => [],
        'Отказано' => [],
        'Отменен' => [],
    ];
}

function home_for_user($user)
{
    return $user['role'] === 'client' ? '/profile.php' : '/staff_profile.php';
}

function app_error($error)
{
    if ($error instanceof InvalidArgumentException) {
        return $error->getMessage();
    }
    error_log((string) $error);
    return app_settings()['debug']
        ? $error->getMessage()
        : 'Не получилось выполнить действие. Проверьте данные';
}

function post_text($key, $maxLength = 200)
{
    $value = trim((string) ($_POST[$key] ?? ''));
    if (strlen($value) > $maxLength * 4) {
        throw new InvalidArgumentException('Слишком длинное значение в поле');
    }
    return $value;
}

function post_int($key)
{
    return (int) ($_POST[$key] ?? 0);
}

function form_csrf()
{
    ?><input type="hidden" name="csrf" value="<?= h(csrf_token()) ?>"><?php
}

function form_error($error)
{
    if ($error) {
        ?><div class="notice error" role="alert"><?= h($error) ?></div><?php
    }
}

function demo_mode()
{
    return app_settings()['mode'] === 'demo';
}

function order_master($orderId)
{
    $data = app_data();
    $latest = null;
    foreach ($data['assignments'] as $assignment) {
        if ($assignment['order_id'] != $orderId) {
            continue;
        }
        if (empty($assignment['completed_at'])) {
            return app_find('staff', $assignment['master_id']);
        }
        if (!$latest || $assignment['assigned_at'] > $latest['assigned_at']) {
            $latest = $assignment;
        }
    }
    return $latest ? app_find('staff', $latest['master_id']) : null;
}
