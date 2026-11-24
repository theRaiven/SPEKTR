<?php

require_once __DIR__ . '/settings.php';

function pg_connection(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $settings = app_settings();
    $driver = $settings['db_driver'] ?? ($settings['mode'] === 'sqlite_test' ? 'sqlite' : 'pgsql');

    if ($driver === 'sqlite') {
        $path = $settings['db_sqlite_path'] ?? (dirname(__DIR__) . '/var/spektr_test.sqlite');
        $dir = dirname($path);
        if (!file_exists($dir) && $path !== ':memory:') {
            @mkdir($dir, 0777, true);
        }
        $pdo = new PDO("sqlite:{$path}", null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON;');
        $pdo->sqliteCreateFunction('now', fn() => date('Y-m-d H:i:s'));
        $pdo->sqliteCreateFunction('CURRENT_DATE', fn() => date('Y-m-d'));
        return $pdo;
    }

    $host = $settings['db_host'] ?? '127.0.0.1';
    $port = $settings['db_port'] ?? '5432';
    $dbname = $settings['db_name'] ?? 'spektr';
    $user = $settings['db_user'] ?? 'postgres';
    $password = $settings['db_password'] ?? '';

    $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
    try {
        $pdo = new PDO($dsn, $user, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    } catch (PDOException $e) {
        throw new RuntimeException("Нет подключения к PostgreSQL ({$dsn}): " . $e->getMessage(), 0, $e);
    }

    return $pdo;
}

function pg_db_query(string $sql, array $params = []): array
{
    $stmt = pg_connection()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

function pg_db_query_one(string $sql, array $params = []): ?array
{
    $stmt = pg_connection()->prepare($sql);
    $stmt->execute($params);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function pg_db_execute(string $sql, array $params = []): int
{
    $stmt = pg_connection()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

function pg_db_insert_id(string $sql, array $params = [], string $idColumn = 'id'): int
{
    $pdo = pg_connection();
    $driver = $pdo->getAttribute(PDO::ATTR_DRIVER_NAME);

    if ($driver === 'pgsql') {
        if (!str_contains(strtoupper($sql), 'RETURNING')) {
            $sql .= " RETURNING {$idColumn}";
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int) ($row[$idColumn] ?? 0);
    }

    $cleanSql = preg_replace('/\s+RETURNING\s+[a-zA-Z0-9_]+/i', '', $sql);
    $stmt = $pdo->prepare($cleanSql);
    $stmt->execute($params);
    return (int) $pdo->lastInsertId();
}

// Авторизация пользователей через PostgreSQL
function repo_login($login, $password)
{
    $sql = "SELECT u.account_id AS id, u.login, u.password_hash, u.is_active AS active,
                   r.name AS role, u.client_id, u.employee_id AS staff_id,
                   c.last_name AS client_last_name, c.first_name AS client_first_name,
                   e.last_name AS staff_last_name, e.first_name AS staff_first_name
            FROM user_accounts u
            JOIN account_roles r ON r.role_id = u.role_id
            LEFT JOIN clients c ON c.client_id = u.client_id
            LEFT JOIN employees e ON e.employee_id = u.employee_id
            WHERE LOWER(u.login) = LOWER(:login)";

    $user = pg_db_query_one($sql, ['login' => trim((string) $login)]);
    if (!$user) {
        return null;
    }

    if (!$user['active']) {
        return null;
    }

    if (!password_verify((string) $password, $user['password_hash'])) {
        return null;
    }

    $role = mb_strtolower((string) $user['role']);
    if (in_array($role, ['клиент', 'client'], true)) {
        $role = 'client';
    } elseif (in_array($role, ['мастер', 'master'], true)) {
        $role = 'master';
    } elseif (in_array($role, ['администратор', 'админ', 'admin'], true)) {
        $role = 'admin';
    }

    // Для совместимости с сессией интерфейса
    return [
        'id' => (int) ($user['client_id'] ?: $user['staff_id']),
        'account_id' => (int) $user['id'],
        'login' => $user['login'],
        'role' => $role,
        'client_id' => $user['client_id'] ? (int) $user['client_id'] : null,
        'staff_id' => $user['staff_id'] ? (int) $user['staff_id'] : null,
        'last_name' => $user['client_last_name'] ?: $user['staff_last_name'],
        'first_name' => $user['client_first_name'] ?: $user['staff_first_name'],
        'active' => true,
    ];
}

// Получение выборки данных из PostgreSQL
function repo_data()
{
    // Клиенты
    $clients = pg_db_query("SELECT client_id AS id, last_name, first_name, middle_name, phone, email FROM clients ORDER BY client_id ASC");

    // Модели устройств
    $models = pg_db_query("SELECT dm.model_id AS id, dt.name AS type, b.name AS brand, dm.model_name AS name
                        FROM device_models dm
                        JOIN device_types dt ON dt.device_type_id = dm.device_type_id
                        JOIN brands b ON b.brand_id = dm.brand_id
                        ORDER BY dm.model_id ASC");

    // Экземпляры устройств
    $devices = pg_db_query("SELECT device_id AS id, model_id, serial_number AS serial, purchase_date AS release_date
                         FROM devices ORDER BY device_id ASC");

    // Владение устройствами (синтезируется из заказов или связей для шаблонов страниц)
    $ownership = pg_db_query("SELECT DISTINCT ro.order_id AS id, ro.device_id, ro.client_id,
                                           ro.created_at AS start_date, NULL AS end_date
                           FROM repair_orders ro
                           ORDER BY ro.order_id ASC");

    // Сотрудники
    $staff = pg_db_query("SELECT e.employee_id AS id, e.last_name, e.first_name, e.middle_name, e.phone,
                              r.name AS role, u.is_active AS active
                       FROM employees e
                       JOIN user_accounts u ON u.employee_id = e.employee_id
                       JOIN account_roles r ON r.role_id = u.role_id
                       ORDER BY e.employee_id ASC");

    // Учётные записи
    $accounts = pg_db_query("SELECT u.account_id AS id, u.employee_id AS staff_id, u.client_id, u.login,
                                 u.password_hash, r.name AS role, u.is_active AS active
                          FROM user_accounts u
                          JOIN account_roles r ON r.role_id = u.role_id
                          ORDER BY u.account_id ASC");

    // Специализации мастеров
    $specializations = pg_db_query("SELECT ms.master_specialization_id AS id, ms.employee_id AS staff_id,
                                        s.name, sl.name AS level, ms.experience_since AS experience_from
                                 FROM master_specializations ms
                                 JOIN specializations s ON s.specialization_id = ms.specialization_id
                                 JOIN skill_levels sl ON sl.skill_level_id = ms.skill_level_id
                                 WHERE ms.valid_to IS NULL OR ms.valid_to >= CURRENT_DATE
                                 ORDER BY ms.master_specialization_id ASC");

    // Статусы
    $statusRows = pg_db_query("SELECT name FROM order_statuses ORDER BY order_status_id ASC");
    $statuses = array_column($statusRows, 'name');

    // Заказы на ремонт
    $orders = pg_db_query("SELECT ro.order_id AS id, ro.order_id AS ownership_id,
                               ro.created_at, ro.fault_description AS problem, '' AS condition,
                               ro.queue_priority AS priority, ro.planned_ready_at AS planned_date,
                               ro.issued_at, ro.rejection_reason AS reject_reason,
                               s.name AS status,
                               oa_cur.employee_id AS preferred_master_id
                        FROM repair_orders ro
                        JOIN order_statuses s ON s.order_status_id = ro.order_status_id
                        LEFT JOIN (
                            SELECT oa.order_id, oa.employee_id
                            FROM order_assignments oa
                            ORDER BY oa.assigned_at DESC
                        ) oa_cur ON oa_cur.order_id = ro.order_id
                        ORDER BY ro.order_id DESC");

    // Журнал смены статусов
    $history = pg_db_query("SELECT osh.status_history_id AS id, osh.order_id, s.name AS status,
                                osh.changed_at, osh.changed_by_employee_id AS staff_id, osh.note AS comment
                         FROM order_status_history osh
                         JOIN order_statuses s ON s.order_status_id = osh.order_status_id
                         ORDER BY osh.status_history_id ASC");

    // Назначения мастеров
    $assignments = pg_db_query("SELECT assignment_id AS id, order_id, employee_id AS master_id,
                                    assigned_at, work_started_at AS started_at, work_completed_at AS completed_at
                             FROM order_assignments
                             ORDER BY assignment_id ASC");

    // Тарифы работ
    $tariffs = pg_db_query("SELECT wt.work_type_id AS id, wt.name, wt_tar.price, wt_tar.warranty_days,
                                wt_tar.valid_from AS from_date,
                                'Смартфон,Ноутбук,Планшет,Компьютер,Принтер,Монитор,Игровая приставка,Телевизор,Другая техника' AS categories
                         FROM work_types wt
                         JOIN work_tariffs wt_tar ON wt_tar.work_type_id = wt.work_type_id
                         WHERE wt_tar.valid_from <= CURRENT_DATE
                         ORDER BY wt.work_type_id ASC");

    // Выполненные работы по заказам
    $works = pg_db_query("SELECT ow.order_work_id AS id, oa.order_id, ow.assignment_id, ow.tariff_id,
                              wt.name, wt_tar.price, ow.performed_at AS done_at
                       FROM order_works ow
                       JOIN order_assignments oa ON oa.assignment_id = ow.assignment_id
                       JOIN work_tariffs wt_tar ON wt_tar.tariff_id = ow.tariff_id
                       JOIN work_types wt ON wt.work_type_id = wt_tar.work_type_id
                       ORDER BY ow.order_work_id ASC");

    // Запчасти
    $parts = pg_db_query("SELECT p.part_id AS id, p.article, p.name,
                              COALESCE(SUM(im.quantity_delta), 10) AS stock
                       FROM parts p
                       LEFT JOIN inventory_movements im ON (
                           im.adjustment_part_id = p.part_id
                           OR im.order_part_id IN (SELECT op.order_part_id FROM order_parts op WHERE op.part_id = p.part_id)
                       )
                       GROUP BY p.part_id, p.article, p.name
                       ORDER BY p.part_id ASC");

    // Запчасти заказа
    $orderParts = pg_db_query("SELECT op.order_part_id AS id, op.order_id, op.part_id,
                                   op.quantity, pt.unit_price AS price
                            FROM order_parts op
                            JOIN part_tariffs pt ON pt.part_tariff_id = op.part_tariff_id
                            ORDER BY op.order_part_id ASC");

    // Применённые скидки
    $discounts = pg_db_query("SELECT ad.applied_discount_id AS id, ad.order_id, dt.name,
                                  ad.discount_amount AS amount, 'promo' AS kind
                           FROM applied_discounts ad
                           JOIN discount_types dt ON dt.discount_type_id = ad.discount_type_id
                           ORDER BY ad.applied_discount_id ASC");

    // Платежи
    $payments = pg_db_query("SELECT p.payment_id AS id, p.order_id, p.amount,
                                 pm.name AS method, ps.name AS status, p.paid_at
                          FROM payments p
                          JOIN payment_methods pm ON pm.payment_method_id = p.payment_method_id
                          JOIN payment_statuses ps ON ps.payment_status_id = p.payment_status_id
                          ORDER BY p.payment_id ASC");

    // Ваучеры
    $vouchers = pg_db_query("SELECT v.voucher_id AS id, v.client_id, v.code,
                                 (v.face_value + COALESCE(SUM(vt.amount_change), 0)) AS amount,
                                 (CASE WHEN (v.face_value + COALESCE(SUM(vt.amount_change), 0)) <= 0 THEN 1 ELSE 0 END) AS used
                          FROM vouchers v
                          LEFT JOIN voucher_transactions vt ON vt.voucher_id = v.voucher_id
                          GROUP BY v.voucher_id, v.client_id, v.code, v.face_value
                          ORDER BY v.voucher_id ASC");

    // Отзывы
    $reviews = pg_db_query("SELECT r.order_work_id AS id, oa.order_id, r.order_work_id AS work_id,
                                r.rating, r.comment, r.created_at, u.client_id
                         FROM reviews r
                         JOIN order_works ow ON ow.order_work_id = r.order_work_id
                         JOIN order_assignments oa ON oa.assignment_id = ow.assignment_id
                         JOIN user_accounts u ON u.account_id = r.account_id
                         ORDER BY r.order_work_id ASC");

    // Гарантийные обращения
    $warranties = pg_db_query("SELECT wc.warranty_claim_id AS id, wc.repair_order_id AS order_id,
                                  wc.original_order_work_id AS work_id, ws.name AS status,
                                  wc.reason, wc.decision, wc.claimed_at AS created_at
                           FROM warranty_claims wc
                           JOIN warranty_statuses ws ON ws.warranty_status_id = wc.warranty_status_id
                           ORDER BY wc.warranty_claim_id ASC");

    // Льготы клиентов
    $benefits = pg_db_query("SELECT cb.client_benefit_id AS id, cb.client_id, bc.name,
                                 10 AS percent, 1 AS active
                          FROM client_benefits cb
                          JOIN benefit_categories bc ON bc.benefit_category_id = cb.benefit_category_id
                          WHERE cb.valid_from <= CURRENT_DATE AND (cb.valid_to IS NULL OR cb.valid_to >= CURRENT_DATE)
                          ORDER BY cb.client_benefit_id ASC");

    // Промокоды
    $promoCodes = pg_db_query("SELECT dt.name AS code, dt.value AS percent, 1 AS active
                            FROM discount_types dt
                            WHERE dt.calculation_type_id = 1");

    return [
        'clients' => $clients,
        'models' => $models,
        'devices' => $devices,
        'ownership' => $ownership,
        'staff' => $staff,
        'accounts' => $accounts,
        'specializations' => $specializations,
        'statuses' => $statuses,
        'orders' => $orders,
        'history' => $history,
        'assignments' => $assignments,
        'tariffs' => $tariffs,
        'works' => $works,
        'parts' => $parts,
        'order_parts' => $orderParts,
        'discounts' => $discounts,
        'payments' => $payments,
        'vouchers' => $vouchers,
        'reviews' => $reviews,
        'warranties' => $warranties,
        'benefits' => $benefits,
        'promo_codes' => $promoCodes,
    ];
}

// Выполнение пользовательских операций и транзакций в PostgreSQL
function repo_action($action, $values = [])
{
    $pdo = pg_connection();
    $staff = staff_user();
    $orderId = (int) ($values['id'] ?? 0);
    $now = date('Y-m-d H:i:s');

    $pdo->beginTransaction();
    try {
        switch ($action) {
            case 'register':
                foreach (['last_name', 'first_name', 'phone', 'login', 'password'] as $field) {
                    if (empty(trim((string) ($values[$field] ?? '')))) {
                        throw new InvalidArgumentException('Заполните обязательные поля');
                    }
                }
                if (!preg_match('/^\+?[0-9() \-]{10,20}$/', $values['phone'])) {
                    throw new InvalidArgumentException('Проверьте телефон');
                }
                if (!empty($values['email']) && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException('Проверьте адрес почты');
                }
                if (strlen($values['password']) < 6) {
                    throw new InvalidArgumentException('Длина пароля должна быть от 6 символов');
                }

                $existing = pg_db_query_one("SELECT account_id FROM user_accounts WHERE LOWER(login) = LOWER(:login)", ['login' => trim($values['login'])]);
                if ($existing) {
                    throw new InvalidArgumentException('Такой логин уже занят');
                }

                $clientId = pg_db_insert_id(
                    "INSERT INTO clients (last_name, first_name, middle_name, phone, email, created_at)
                     VALUES (:last_name, :first_name, :middle_name, :phone, :email, now())",
                    [
                        'last_name' => $values['last_name'],
                        'first_name' => $values['first_name'],
                        'middle_name' => $values['middle_name'] ?? '',
                        'phone' => $values['phone'],
                        'email' => $values['email'] ?? '',
                    ],
                    'client_id'
                );

                $roleRow = pg_db_query_one("SELECT role_id FROM account_roles WHERE LOWER(name) IN ('client', 'клиент') OR owner_kind = 'client' ORDER BY role_id ASC LIMIT 1");
                $roleId = $roleRow ? (int) $roleRow['role_id'] : 1;

                pg_db_insert_id(
                    "INSERT INTO user_accounts (login, password_hash, role_id, client_id, is_active, created_at)
                     VALUES (:login, :password_hash, :role_id, :client_id, true, now())",
                    [
                        'login' => trim($values['login']),
                        'password_hash' => password_hash($values['password'], PASSWORD_DEFAULT),
                        'role_id' => $roleId,
                        'client_id' => $clientId,
                    ],
                    'account_id'
                );

                $pdo->commit();
                return $clientId;

            case 'request_add':
                if (!$staff || $staff['role'] !== 'client') {
                    throw new InvalidArgumentException('Создать заявку можно из кабинета клиента');
                }
                foreach (['type', 'brand', 'model', 'problem'] as $field) {
                    if (empty(trim((string) ($values[$field] ?? '')))) {
                        throw new InvalidArgumentException('Заполните поле ' . $field);
                    }
                }

                // Найти или создать тип устройства
                $typeRow = pg_db_query_one("SELECT device_type_id FROM device_types WHERE LOWER(name) = LOWER(:name)", ['name' => $values['type']]);
                $typeId = $typeRow ? (int) $typeRow['device_type_id'] : pg_db_insert_id("INSERT INTO device_types (name) VALUES (:name)", ['name' => $values['type']], 'device_type_id');

                // Найти или создать бренд
                $brandRow = pg_db_query_one("SELECT brand_id FROM brands WHERE LOWER(name) = LOWER(:name)", ['name' => $values['brand']]);
                $brandId = $brandRow ? (int) $brandRow['brand_id'] : pg_db_insert_id("INSERT INTO brands (name) VALUES (:name)", ['name' => $values['brand']], 'brand_id');

                // Найти или создать модель
                $modelRow = pg_db_query_one("SELECT model_id FROM device_models WHERE brand_id = :bid AND LOWER(model_name) = LOWER(:mname)", [
                    'bid' => $brandId,
                    'mname' => $values['model'],
                ]);
                $modelId = $modelRow ? (int) $modelRow['model_id'] : pg_db_insert_id("INSERT INTO device_models (device_type_id, brand_id, model_name) VALUES (:tid, :bid, :mname)", [
                    'tid' => $typeId,
                    'bid' => $brandId,
                    'mname' => $values['model'],
                ], 'model_id');

                // Создать экземпляр устройства
                $serial = trim((string) ($values['serial'] ?? ''));
                $deviceId = pg_db_insert_id("INSERT INTO devices (model_id, serial_number) VALUES (:mid, :serial)", [
                    'mid' => $modelId,
                    'serial' => $serial ?: null,
                ], 'device_id');

                // Статус "Новая" / "Заявка" / "Заявка создана"
                $statusRow = pg_db_query_one("SELECT order_status_id FROM order_statuses WHERE name IN ('Новая', 'Заявка', 'Заявка создана') ORDER BY order_status_id ASC LIMIT 1");
                $statusId = $statusRow ? (int) $statusRow['order_status_id'] : 1;

                $newOrderId = pg_db_insert_id(
                    "INSERT INTO repair_orders (client_id, device_id, order_status_id, fault_description, queue_priority, created_at)
                     VALUES (:cid, :did, :sid, :fault, 1, now())",
                    [
                        'cid' => (int) $staff['id'],
                        'did' => $deviceId,
                        'sid' => $statusId,
                        'fault' => $values['problem'],
                    ],
                    'order_id'
                );

                pg_db_execute(
                    "INSERT INTO order_status_history (order_id, order_status_id, changed_at, note)
                     VALUES (:oid, :sid, now(), 'Заявка отправлена клиентом')",
                    ['oid' => $newOrderId, 'sid' => $statusId]
                );

                $pdo->commit();
                return $newOrderId;

            case 'request_decide':
                if (!$staff || !in_array($staff['role'], ['admin', 'manager', 'reception'], true)) {
                    throw new InvalidArgumentException('Недостаточно прав');
                }
                $approve = ($values['decision'] ?? '') === 'accept';
                $targetStatus = $approve ? 'Принята' : 'Отклонена';
                $statusRow = pg_db_query_one("SELECT order_status_id FROM order_statuses WHERE name = :name", ['name' => $targetStatus]);
                if (!$statusRow) {
                    $statusRow = pg_db_query_one("SELECT order_status_id FROM order_statuses WHERE name = :name", ['name' => $approve ? 'Принят' : 'Отказано']);
                }
                $targetStatusId = (int) $statusRow['order_status_id'];

                $reason = $approve ? null : trim((string) ($values['reason'] ?? 'Отклонено администратором'));
                pg_db_execute(
                    "UPDATE repair_orders SET order_status_id = :sid, rejection_reason = :reason, closed_at = CASE WHEN :is_reject THEN now() ELSE NULL END WHERE order_id = :oid",
                    [
                        'sid' => $targetStatusId,
                        'reason' => $reason,
                        'is_reject' => !$approve,
                        'oid' => $orderId,
                    ]
                );

                pg_db_execute(
                    "INSERT INTO order_status_history (order_id, order_status_id, changed_at, changed_by_employee_id, note)
                     VALUES (:oid, :sid, now(), :emp, :note)",
                    [
                        'oid' => $orderId,
                        'sid' => $targetStatusId,
                        'emp' => $staff['staff_id'] ?? $staff['id'],
                        'note' => $approve ? 'Заявка принята администратором' : "Причина отказа: {$reason}",
                    ]
                );

                $pdo->commit();
                return $orderId;

            case 'assign':
                if (!$staff || !in_array($staff['role'], ['admin', 'manager', 'reception'], true)) {
                    throw new InvalidArgumentException('Недостаточно прав');
                }
                $masterId = (int) ($values['master_id'] ?? 0);
                if (!$masterId) {
                    throw new InvalidArgumentException('Выберите мастера');
                }

                $assignId = pg_db_insert_id(
                    "INSERT INTO order_assignments (order_id, employee_id, assigned_at)
                     VALUES (:oid, :mid, now())",
                    ['oid' => $orderId, 'mid' => $masterId],
                    'assignment_id'
                );

                $statusRow = pg_db_query_one("SELECT order_status_id FROM order_statuses WHERE name IN ('Передана мастеру', 'Ожидает мастера', 'В ремонте') ORDER BY order_status_id ASC LIMIT 1");
                $statusId = $statusRow ? (int) $statusRow['order_status_id'] : 4;

                pg_db_execute("UPDATE repair_orders SET order_status_id = :sid WHERE order_id = :oid", [
                    'sid' => $statusId,
                    'oid' => $orderId,
                ]);

                pg_db_execute(
                    "INSERT INTO order_status_history (order_id, order_status_id, changed_at, changed_by_employee_id, note)
                     VALUES (:oid, :sid, now(), :emp, 'Назначен мастер')",
                    [
                        'oid' => $orderId,
                        'sid' => $statusId,
                        'emp' => $staff['staff_id'] ?? $staff['id'],
                    ]
                );

                $pdo->commit();
                return $orderId;

            case 'status':
                $newStatus = trim((string) ($values['status'] ?? ''));
                $statusRow = pg_db_query_one("SELECT order_status_id, is_closed, is_issued FROM order_statuses WHERE name = :name", ['name' => $newStatus]);
                if (!$statusRow) {
                    throw new InvalidArgumentException("Неизвестный статус: {$newStatus}");
                }
                $newStatusId = (int) $statusRow['order_status_id'];

                $extra = '';
                if ($statusRow['is_closed']) {
                    $extra .= ', closed_at = now()';
                }
                if ($statusRow['is_issued']) {
                    $extra .= ', issued_at = now()';
                }

                pg_db_execute("UPDATE repair_orders SET order_status_id = :sid {$extra} WHERE order_id = :oid", [
                    'sid' => $newStatusId,
                    'oid' => $orderId,
                ]);

                pg_db_execute(
                    "INSERT INTO order_status_history (order_id, order_status_id, changed_at, changed_by_employee_id, note)
                     VALUES (:oid, :sid, now(), :emp, :note)",
                    [
                        'oid' => $orderId,
                        'sid' => $newStatusId,
                        'emp' => $staff['staff_id'] ?? $staff['id'] ?? null,
                        'note' => $values['comment'] ?? "Смена статуса на {$newStatus}",
                    ]
                );

                $pdo->commit();
                return $orderId;

            case 'work_add':
                $tariffId = (int) ($values['tariff_id'] ?? 0);
                $tariffRow = pg_db_query_one("SELECT tariff_id, price FROM work_tariffs WHERE tariff_id = :tid", ['tid' => $tariffId]);
                if (!$tariffRow) {
                    throw new InvalidArgumentException('Выберите корректную работу');
                }

                $assignRow = pg_db_query_one("SELECT assignment_id FROM order_assignments WHERE order_id = :oid ORDER BY assigned_at DESC LIMIT 1", ['oid' => $orderId]);
                $assignId = $assignRow ? (int) $assignRow['assignment_id'] : 1;

                pg_db_insert_id(
                    "INSERT INTO order_works (assignment_id, tariff_id, tariff_applied_on, performed_at)
                     VALUES (:aid, :tid, CURRENT_DATE, now())",
                    ['aid' => $assignId, 'tid' => $tariffId],
                    'order_work_id'
                );

                $pdo->commit();
                return $orderId;

            case 'part_add':
                $partId = (int) ($values['part_id'] ?? 0);
                $quantity = max(1, (int) ($values['quantity'] ?? 1));

                $ptRow = pg_db_query_one("SELECT part_tariff_id FROM part_tariffs WHERE part_id = :pid ORDER BY valid_from DESC LIMIT 1", ['pid' => $partId]);
                $partTariffId = $ptRow ? (int) $ptRow['part_tariff_id'] : 1;

                $orderPartId = pg_db_insert_id(
                    "INSERT INTO order_parts (order_id, part_id, part_tariff_id, price_applied_on, quantity)
                     VALUES (:oid, :pid, :ptid, CURRENT_DATE, :qty)",
                    [
                        'oid' => $orderId,
                        'pid' => $partId,
                        'ptid' => $partTariffId,
                        'qty' => $quantity,
                    ],
                    'order_part_id'
                );

                pg_db_execute(
                    "INSERT INTO inventory_movements (movement_type_id, order_part_id, quantity_delta, moved_at)
                     VALUES (2, :opid, :delta, now())",
                    ['opid' => $orderPartId, 'delta' => -$quantity]
                );

                $pdo->commit();
                return $orderId;

            case 'client_discount':
            case 'discount_add':
                $code = trim((string) ($values['code'] ?? ''));
                $kind = $values['kind'] ?? 'promo';

                $dtRow = pg_db_query_one("SELECT discount_type_id, value, calculation_type_id FROM discount_types WHERE LOWER(name) LIKE :code LIMIT 1", ['code' => '%' . strtolower($code) . '%']);
                $dtId = $dtRow ? (int) $dtRow['discount_type_id'] : 1;
                $amount = isset($values['amount']) ? (float) $values['amount'] : (float) ($dtRow['value'] ?? 100.0);

                pg_db_insert_id(
                    "INSERT INTO applied_discounts (order_id, discount_type_id, calculation_base, discount_amount)
                     VALUES (:oid, :dtid, 0, :amount)",
                    [
                        'oid' => $orderId,
                        'dtid' => $dtId,
                        'amount' => $amount,
                    ],
                    'applied_discount_id'
                );

                $pdo->commit();
                return $orderId;

            case 'payment_add':
                $amount = (float) ($values['amount'] ?? 0);
                if ($amount <= 0) {
                    throw new InvalidArgumentException('Сумма платежа должна быть больше нуля');
                }

                $methodName = $values['method'] ?? 'Наличные';
                $pmRow = pg_db_query_one("SELECT payment_method_id FROM payment_methods WHERE name = :name", ['name' => $methodName]);
                $pmId = $pmRow ? (int) $pmRow['payment_method_id'] : 1;

                pg_db_insert_id(
                    "INSERT INTO payments (order_id, payment_method_id, payment_status_id, paid_at, amount)
                     VALUES (:oid, :pmid, 2, now(), :amount)",
                    [
                        'oid' => $orderId,
                        'pmid' => $pmId,
                        'amount' => $amount,
                    ],
                    'payment_id'
                );

                $pdo->commit();
                return $orderId;

            case 'review_add':
                $rating = max(1, min(5, (int) ($values['rating'] ?? 5)));
                $comment = trim((string) ($values['comment'] ?? ''));

                $workRow = pg_db_query_one("SELECT ow.order_work_id FROM order_works ow JOIN order_assignments oa ON oa.assignment_id = ow.assignment_id WHERE oa.order_id = :oid LIMIT 1", ['oid' => $orderId]);
                $workId = $workRow ? (int) $workRow['order_work_id'] : 1;

                $userRow = pg_db_query_one("SELECT account_id FROM user_accounts WHERE client_id = :cid LIMIT 1", ['cid' => $staff['id'] ?? 1]);
                $accId = $userRow ? (int) $userRow['account_id'] : 1;

                pg_db_execute(
                    "INSERT INTO reviews (order_work_id, account_id, rating, comment, created_at)
                     VALUES (:wid, :acc, :rating, :comment, now())",
                    [
                        'wid' => $workId,
                        'acc' => $accId,
                        'rating' => $rating,
                        'comment' => $comment,
                    ]
                );

                $pdo->commit();
                return $orderId;

            default:
                throw new RuntimeException("Неизвестная операция: {$action}");
        }
    } catch (\Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
