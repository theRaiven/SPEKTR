<?php

/**
 * Тестирование функций repository_pg.php (repo_login, repo_data, repo_action)
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/common.php';
require_once dirname(__DIR__) . '/src/repository_pg.php';

// Настройка для работы с тестовой базой в памяти
putenv('APP_ENV=test');

function run_pg_repo_tests(): void
{
    echo "=================================================================\n";
    echo "       ТЕСТИРОВАНИЕ REPOSITORY_PG.PHP ДЛЯ POSTGRESQL            \n";
    echo "=================================================================\n\n";

    // 1. Инициализируем тестовую схему и сиды
    $pdo = pg_connection();
    $schemaSql = file_get_contents(__DIR__ . '/schema_sqlite.sql');
    $pdo->exec($schemaSql);

    $seedSql = file_get_contents(__DIR__ . '/seeds_sqlite.sql');
    $pdo->exec($seedSql);

    $passed = 0;
    $failed = 0;

    $assert = function (string $name, callable $test) use (&$passed, &$failed) {
        try {
            $test();
            echo "[PASS] {$name}\n";
            $passed++;
        } catch (\Throwable $e) {
            echo "[FAIL] {$name}: {$e->getMessage()}\n";
            $failed++;
        }
    };

    // Тест 1: repo_login
    $assert('1. repo_login() для администратора и клиента', function () {
        $admin = repo_login('admin', 'spektr123');
        if (!$admin || $admin['role'] !== 'admin') {
            throw new Exception('repo_login(admin) failed');
        }

        $client = repo_login('ivan', 'client123');
        if (!$client || $client['role'] !== 'client') {
            throw new Exception('repo_login(ivan) failed');
        }

        $wrong = repo_login('admin', 'wrong_pass');
        if ($wrong !== null) {
            throw new Exception('repo_login with wrong pass should return null');
        }
    });

    // Тест 2: repo_data
    $assert('2. repo_data() загружает все 22 массива сущностей', function () {
        $data = repo_data();
        $expectedKeys = [
            'clients', 'models', 'devices', 'ownership', 'staff', 'accounts',
            'specializations', 'statuses', 'orders', 'history', 'assignments',
            'tariffs', 'works', 'parts', 'order_parts', 'discounts', 'payments',
            'vouchers', 'reviews', 'warranties', 'benefits', 'promo_codes',
        ];

        foreach ($expectedKeys as $key) {
            if (!isset($data[$key]) || !is_array($data[$key])) {
                throw new Exception("Key '{$key}' missing in repo_data");
            }
        }

        if (count($data['statuses']) === 0 || count($data['tariffs']) === 0) {
            throw new Exception('Statuses or tariffs are empty');
        }
    });

    // Тест 3: repo_action: register
    $newClientId = null;
    $assert('3. repo_action(register) регистрирует клиента в БД', function () use (&$newClientId) {
        $newClientId = repo_action('register', [
            'last_name' => 'Николаев',
            'first_name' => 'Николай',
            'phone' => '+79998887766',
            'email' => 'nikolay@test.local',
            'login' => 'nikolay',
            'password' => 'secret123',
        ]);

        if (!$newClientId) {
            throw new Exception('Failed to register client');
        }

        $loginRes = repo_login('nikolay', 'secret123');
        if (!$loginRes || $loginRes['role'] !== 'client') {
            throw new Exception('Cannot login as newly registered client');
        }
    });

    // Тест 4: repo_action: request_add
    $orderId = null;
    $assert('4. repo_action(request_add) создает заявку клиентом', function () use (&$orderId) {
        // Устанавливаем сессию клиента
        $_SESSION['staff'] = [
            'id' => 1,
            'role' => 'client',
            'login' => 'ivan',
        ];

        $orderId = repo_action('request_add', [
            'type' => 'Смартфон',
            'brand' => 'Samsung',
            'model' => 'Galaxy S23',
            'serial' => 'SN-TEST-8812',
            'problem' => 'Не заряжается',
        ]);

        if (!$orderId) {
            throw new Exception('Failed to create order');
        }
    });

    // Тест 5: repo_action: request_decide (принятие администратором)
    $assert('5. repo_action(request_decide) - принятие администратором', function () use (&$orderId) {
        $_SESSION['staff'] = [
            'id' => 1,
            'staff_id' => 1,
            'role' => 'admin',
            'login' => 'admin',
        ];

        repo_action('request_decide', [
            'id' => $orderId,
            'decision' => 'accept',
        ]);

        $data = repo_data();
        $order = null;
        foreach ($data['orders'] as $o) {
            if ((int) $o['id'] === $orderId) {
                $order = $o;
                break;
            }
        }

        if (!$order || !in_array($order['status'], ['Принята', 'Принят'], true)) {
            throw new Exception("Unexpected status after accept: " . ($order['status'] ?? 'null'));
        }
    });

    // Тест 6: repo_action: assign (назначение мастера)
    $assert('6. repo_action(assign) - назначение мастера', function () use (&$orderId) {
        $_SESSION['staff'] = [
            'id' => 1,
            'staff_id' => 1,
            'role' => 'admin',
            'login' => 'admin',
        ];

        repo_action('assign', [
            'id' => $orderId,
            'master_id' => 2, // Сидоров (смартфоны)
        ]);

        $data = repo_data();
        $order = null;
        foreach ($data['orders'] as $o) {
            if ((int) $o['id'] === $orderId) {
                $order = $o;
                break;
            }
        }

        if (!$order || !in_array($order['status'], ['Передана мастеру', 'Передан мастеру'], true)) {
            throw new Exception("Unexpected status after assign: " . ($order['status'] ?? 'null'));
        }
    });

    // Тест 7: repo_action: payment_add
    $assert('7. repo_action(payment_add) - регистрация оплаты', function () use (&$orderId) {
        $_SESSION['staff'] = [
            'id' => 1,
            'staff_id' => 1,
            'role' => 'admin',
            'login' => 'admin',
        ];

        repo_action('payment_add', [
            'id' => $orderId,
            'amount' => 1500.0,
            'method' => 'Наличные',
        ]);

        $data = repo_data();
        $hasPayment = false;
        foreach ($data['payments'] as $p) {
            if ((int) $p['order_id'] === $orderId && (float) $p['amount'] === 1500.0) {
                $hasPayment = true;
                break;
            }
        }

        if (!$hasPayment) {
            throw new Exception('Payment record not found in repo_data');
        }
    });

    echo "\n-----------------------------------------------------------------\n";
    echo "ИТОГ REPOSITORY_PG: Пройдено: {$passed}, Ошибок: {$failed}\n";
    echo "-----------------------------------------------------------------\n";

    if ($failed > 0) {
        exit(1);
    }
}

run_pg_repo_tests();
