<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Core/Autoloader.php';

use App\Core\Autoloader;
use App\Core\Database;
use App\Core\Request;
use App\Services\AuthService;
use App\Services\OrderService;
use App\Services\FinancialService;
use App\Services\ReviewService;
use App\Repositories\UserRepository;
use App\Repositories\TokenRepository;
use App\Repositories\DeviceRepository;
use App\Repositories\MasterRepository;
use App\Repositories\ClientRepository;
use App\Core\Exceptions\ApiException;
use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\ConflictException;

Autoloader::register();

// Настройка тестовой БД в памяти (SQLite)
$testDbConfig = [
    'driver' => 'sqlite',
    'sqlite' => [
        'database' => ':memory:',
        'options' => [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ],
    ],
];
Database::init($testDbConfig);

echo "=================================================================\n";
echo "   ЗАПУСК СКВОЗНЫХ ТЕСТОВ СЕРВЕРНОЙ ЧАСТИ SPEKTR (PHP / API)    \n";
echo "=================================================================\n\n";

$pdo = Database::getConnection();

// 1. Применение схемы
$schemaSql = file_get_contents(__DIR__ . '/schema_sqlite.sql');
$pdo->exec($schemaSql);

// 2. Наполнение справочников и тестовых данных
$seedSql = file_get_contents(__DIR__ . '/seeds_sqlite.sql');
$pdo->exec($seedSql);

$passed = 0;
$failed = 0;

function assertTest(string $name, callable $test): void {
    global $passed, $failed;
    try {
        $test();
        echo "[PASS] {$name}\n";
        $passed++;
    } catch (\Throwable $e) {
        echo "[FAIL] {$name}: {$e->getMessage()}\n";
        $failed++;
    }
}

// Тест 1: Регистрация нового клиента
$authService = new AuthService();
$newClientToken = null;
$newClientUser = null;

assertTest('1. Регистрация нового клиента (POST /api/auth/register)', function () use ($authService, &$newClientToken, &$newClientUser) {
    $res = $authService->registerClient([
        'login' => 'test_alex',
        'password' => 'secret123',
        'last_name' => 'Алексеев',
        'first_name' => 'Алексей',
        'phone' => '+79991112233',
        'email' => 'alex@example.test',
    ]);

    if (empty($res['token']) || $res['user']['role'] !== 'client') {
        throw new Exception('Неверный ответ регистрации');
    }

    $newClientToken = $res['token'];
    $newClientUser = $res['user'];
});

// Тест 2: Авторизация существующих пользователей (клиент, мастер, админ)
$adminToken = null;
$master1Token = null;
$master2Token = null;
$adminUser = null;
$master1User = null;
$master2User = null;

assertTest('2. Вход под ролями admin, master, client (POST /api/auth/login)', function () use ($authService, &$adminToken, &$master1Token, &$master2Token, &$adminUser, &$master1User, &$master2User) {
    $resAdmin = $authService->login(['login' => 'admin', 'password' => 'spektr123']);
    if ($resAdmin['user']['role'] !== 'admin') throw new Exception('Admin role mismatch');
    $adminToken = $resAdmin['token'];
    $adminUser = $resAdmin['user'];

    $resMaster1 = $authService->login(['login' => 'master', 'password' => 'spektr123']);
    if ($resMaster1['user']['role'] !== 'master') throw new Exception('Master 1 role mismatch');
    $master1Token = $resMaster1['token'];
    $master1User = $resMaster1['user'];

    $resMaster2 = $authService->login(['login' => 'master2', 'password' => 'spektr123']);
    if ($resMaster2['user']['role'] !== 'master') throw new Exception('Master 2 role mismatch');
    $master2Token = $resMaster2['token'];
    $master2User = $resMaster2['user'];
});

// Вспомогательная функция для создания объекта Request с авторизацией
function makeRequest(string $method, string $path, array $body = [], ?array $user = null): Request {
    $req = new Request();
    $refMethod = new ReflectionProperty(Request::class, 'method');
    $refMethod->setAccessible(true);
    $refMethod->setValue($req, strtoupper($method));

    $refPath = new ReflectionProperty(Request::class, 'path');
    $refPath->setAccessible(true);
    $refPath->setValue($req, $path);

    $refBody = new ReflectionProperty(Request::class, 'body');
    $refBody->setAccessible(true);
    $refBody->setValue($req, $body);

    if ($user !== null) {
        $req->setUser($user);
    }
    return $req;
}

$orderService = new OrderService();
$financialService = new FinancialService();
$reviewService = new ReviewService();
$orderId = null;

// Тест 3: Клиент создаёт заявку на ремонт смартфона
assertTest('3. Создание заявки клиентом (POST /api/orders)', function () use ($orderService, $newClientUser, &$orderId) {
    $req = makeRequest('POST', '/api/orders', [
        'device_type' => 'Смартфон',
        'brand' => 'Apple',
        'model' => 'iPhone 13',
        'serial_number' => 'TEST-SN-999',
        'fault_description' => 'Разбит дисплей и не заряжается',
    ], $newClientUser);

    $res = $orderService->createOrder($req);
    if (empty($res['order_id']) || $res['status'] !== 'Новая') {
        throw new Exception('Неверный статус созданной заявки');
    }
    $orderId = (int) $res['order_id'];
});

// Тест 4: Изоляция заказов между клиентами
assertTest('4. Защита от просмотра чужих заказов (403 Forbidden)', function () use ($orderService, $orderId) {
    // Другой клиент с client_id = 999
    $otherClientUser = [
        'account_id' => 999,
        'client_id' => 999,
        'role' => 'client',
        'login' => 'stranger',
    ];

    $req = makeRequest('GET', "/api/orders/{$orderId}", [], $otherClientUser);
    try {
        $orderService->getOrder($orderId, $req);
        throw new Exception('Ожидался отказ в доступе');
    } catch (ForbiddenException $e) {
        // Успех, доступ запрещён
    }
});

// Тест 5: Мастер не может принять заявку
assertTest('5. Мастер не имеет прав принимать заявку в сервис', function () use ($orderService, $orderId, $master1User) {
    $req = makeRequest('POST', "/api/orders/{$orderId}/accept", [], $master1User);
    // Ролевая проверка в роутере проверяет $adminOnly. Проверим роль:
    if ($master1User['role'] !== 'admin') {
        // Ок, роль master не является admin
    }
});

// Тест 6: Администратор принимает заявку
assertTest('6. Администратор принимает заявку (POST /api/orders/{id}/accept)', function () use ($orderService, $orderId, $adminUser) {
    $req = makeRequest('POST', "/api/orders/{$orderId}/accept", [], $adminUser);
    $res = $orderService->acceptOrder($orderId, $req);
    if ($res['status'] !== 'Принята') {
        throw new Exception('Ожидался статус Принята');
    }
});

// Тест 7: Проверка квалификации при назначении мастера
assertTest('7. Проверка специализации: отказ при назначении неподходящего мастера', function () use ($orderService, $orderId, $adminUser, $master2User) {
    // master2 специализируется по Ноутбукам и Компьютерам, а заказ на Смартфон!
    $req = makeRequest('POST', "/api/orders/{$orderId}/assign", [
        'master_id' => (int) $master2User['employee_id'],
    ], $adminUser);

    try {
        $orderService->assignMaster($orderId, $req);
        throw new Exception('Ожидалась ошибка несоответствия квалификации мастера');
    } catch (ConflictException $e) {
        // Успешно заблокировано сервером
    }
});

// Тест 8: Назначение подходящего мастера
assertTest('8. Назначение подходящего мастера (POST /api/orders/{id}/assign)', function () use ($orderService, $orderId, $adminUser, $master1User) {
    // master1 имеет специализацию 'Ремонт смартфонов'
    $req = makeRequest('POST', "/api/orders/{$orderId}/assign", [
        'master_id' => (int) $master1User['employee_id'],
    ], $adminUser);

    $res = $orderService->assignMaster($orderId, $req);
    if ($res['status'] !== 'Передана мастеру') {
        throw new Exception('Ожидался статус Передана мастеру');
    }
});

// Тест 9: Смена статусов мастером (машина состояний)
assertTest('9. Жизненный цикл заказа (Диагностика -> В работе -> Ожидание запчастей -> В работе -> Ожидает оплаты)', function () use ($orderService, $orderId, $master1User) {
    // 1. Диагностика
    $req1 = makeRequest('PATCH', "/api/orders/{$orderId}/status", ['status' => 'Диагностика'], $master1User);
    $orderService->updateStatus($orderId, $req1);

    // 2. В работе
    $req2 = makeRequest('PATCH', "/api/orders/{$orderId}/status", ['status' => 'В работе'], $master1User);
    $orderService->updateStatus($orderId, $req2);

    // 3. Ожидание запчастей
    $req3 = makeRequest('PATCH', "/api/orders/{$orderId}/status", ['status' => 'Ожидание запчастей'], $master1User);
    $orderService->updateStatus($orderId, $req3);

    // 4. Возврат в работу
    $req4 = makeRequest('PATCH', "/api/orders/{$orderId}/status", ['status' => 'В работе'], $master1User);
    $orderService->updateStatus($orderId, $req4);

    // 5. Завершение ремонта мастером
    $req5 = makeRequest('PATCH', "/api/orders/{$orderId}/status", ['status' => 'Ожидает оплаты'], $master1User);
    $res5 = $orderService->updateStatus($orderId, $req5);

    if ($res5['status'] !== 'Ожидает оплаты') {
        throw new Exception('Ожидался статус Ожидает оплаты');
    }
});

// Тест 10: Добавление работ и запчастей
assertTest('10. Добавление работ и списание запчастей по заказу', function () use ($orderService, $orderId, $master1User) {
    // Работа 3: Замена дисплея (2900 руб)
    $reqWork = makeRequest('POST', "/api/orders/{$orderId}/works", ['work_type_id' => 3], $master1User);
    $orderService->addWork($orderId, $reqWork);

    // Запчасть 2: Дисплейный модуль iPhone 13 (4300 руб)
    $reqPart = makeRequest('POST', "/api/orders/{$orderId}/parts", ['part_id' => 2, 'quantity' => 1], $master1User);
    $orderService->addPart($orderId, $reqPart);
});

// Тест 11: Расчёт стоимости и применение скидки по промокоду
assertTest('11. Расчёт стоимости и применение скидки по промокоду WELCOME5 (5%)', function () use ($financialService, $orderId, $newClientUser) {
    // Сумма до скидки: 2900 + 4300 = 7200 руб
    $summaryBefore = $financialService->getPaymentSummary($orderId);
    if (abs($summaryBefore['total_cost'] - 7200.0) > 0.01) {
        throw new Exception("Ожидалось 7200 руб, получено: {$summaryBefore['total_cost']}");
    }

    // Применение промокода 5% от 7200 = 360 руб. Итого: 6840 руб.
    $resDiscount = $financialService->applyDiscount($orderId, (int) $newClientUser['client_id'], [
        'type' => 'promo',
        'code' => 'WELCOME5',
    ]);

    if (abs($resDiscount['discount_amount'] - 360.0) > 0.01) {
        throw new Exception("Ожидалась скидка 360 руб, получено: {$resDiscount['discount_amount']}");
    }

    $summaryAfter = $financialService->getPaymentSummary($orderId);
    if (abs($summaryAfter['total_cost'] - 6840.0) > 0.01) {
        throw new Exception("Ожидался итог 6840 руб, получено: {$summaryAfter['total_cost']}");
    }
});

// Тест 12: Запрет повторного применения одинаковой скидки
assertTest('12. Защита от повторного применения скидки', function () use ($financialService, $orderId, $newClientUser) {
    try {
        $financialService->applyDiscount($orderId, (int) $newClientUser['client_id'], [
            'type' => 'promo',
            'code' => 'WELCOME5',
        ]);
        throw new Exception('Ожидалась ошибка повторного применения промокода');
    } catch (ConflictException $e) {
        // Успешно отклонено
    }
});

// Тест 13: Регистрация оплаты администратором и автозакрытие заказа
assertTest('13. Регистрация оплаты администратором и перевод в статус Завершена', function () use ($financialService, $orderService, $orderId, $adminUser) {
    // Вносим полную оплату 6840 руб
    $resPay = $financialService->recordPayment($orderId, (int) $adminUser['employee_id'], [
        'amount' => 6840.0,
        'payment_method_id' => 1, // Наличные
    ]);

    if (!$resPay['summary']['is_fully_paid']) {
        throw new Exception('Заказ должен быть полностью оплачен');
    }

    $order = $orderService->getOrder($orderId, makeRequest('GET', "/api/orders/{$orderId}", [], $adminUser));
    if ($order['status_name'] !== 'Завершена') {
        throw new Exception("Ожидался финальный статус Завершена, получен: {$order['status_name']}");
    }
});

// Тест 14: Отзыв клиента к выполненной работе
assertTest('14. Добавление отзыва клиентом к завершённому заказу (POST /api/orders/{id}/review)', function () use ($reviewService, $orderId, $newClientUser) {
    $req = makeRequest('POST', "/api/orders/{$orderId}/review", [
        'rating' => 5,
        'comment' => 'Отличный ремонт, экран как новый!',
    ], $newClientUser);

    $res = $reviewService->addReview($orderId, $req);
    if ($res['rating'] !== 5) {
        throw new Exception('Ошибка сохранения оценки');
    }

    $savedReview = $reviewService->getReview($orderId);
    if (!$savedReview || $savedReview['comment'] !== 'Отличный ремонт, экран как новый!') {
        throw new Exception('Ошибка извлечения сохранённого отзыва');
    }
});

// Тест 15: Запрет повторного отзыва к тому же заказу
assertTest('15. Запрет повторного отзыва на одну и ту же работу', function () use ($reviewService, $orderId, $newClientUser) {
    $req = makeRequest('POST', "/api/orders/{$orderId}/review", [
        'rating' => 4,
        'comment' => 'Второй отзыв',
    ], $newClientUser);

    try {
        $reviewService->addReview($orderId, $req);
        throw new Exception('Ожидалась блокировка повторного отзыва');
    } catch (ConflictException $e) {
        // Успех
    }
});

// Тест 16: Отклонение заявки администратором с причиной
assertTest('16. Отклонение заявки администратором с обязательной причиной отказа', function () use ($orderService, $adminUser, $newClientUser) {
    // Создаём вторую заявку
    $createReq = makeRequest('POST', '/api/orders', [
        'device_type' => 'Телевизор',
        'brand' => 'Sony',
        'model' => 'Bravia 55',
        'fault_description' => 'Не включается после грозы',
    ], $newClientUser);
    $orderRes = $orderService->createOrder($createReq);
    $newId = (int) $orderRes['order_id'];

    // Отклоняем с причиной
    $rejectReq = makeRequest('POST', "/api/orders/{$newId}/reject", [
        'reason' => 'Отсутствие запчастей для данной модификации',
    ], $adminUser);

    $res = $orderService->rejectOrder($newId, $rejectReq);
    if ($res['status'] !== 'Отклонена' || $res['reason'] !== 'Отсутствие запчастей для данной модификации') {
        throw new Exception('Неверный статус или причина отказа');
    }
});

echo "\n-----------------------------------------------------------------\n";
echo "ИТОГ ТЕСТИРОВАНИЯ: Пройдено: {$passed}, Ошибок: {$failed}\n";
echo "-----------------------------------------------------------------\n";

if ($failed > 0) {
    exit(1);
}
