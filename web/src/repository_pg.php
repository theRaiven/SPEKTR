<?php
// подключение и SQL-запросы к базе сервисного центра
function pg_connection()
{
    static $connection = null;
    if ($connection !== null) {
        return $connection;
    }
    if (!function_exists('pg_connect')) {
        throw new RuntimeException('В PHP нужно включить расширение pgsql');
    }
    $settings = app_settings();
    $params = [];
    foreach (['host' => 'db_host', 'port' => 'db_port', 'dbname' => 'db_name', 'user' => 'db_user', 'password' => 'db_password'] as $key => $field) {
        $value = str_replace(['\\', "'"], ['\\\\', "\\'"], (string) ($settings[$field] ?? ''));
        $params[] = $key . "='" . $value . "'";
    }
    $connection = @pg_connect(implode(' ', $params));
    if (!$connection) {
        throw new RuntimeException('Не удалось подключиться к PostgreSQL. Проверьте config.php');
    }
    return $connection;
}

function pg_rows($sql, $values = [])
{
    $result = @pg_query_params(pg_connection(), $sql, array_values($values));
    if ($result === false) {
        throw new RuntimeException('Ошибка базы данных: ' . pg_last_error(pg_connection()));
    }
    $rows = pg_fetch_all($result) ?: [];
    pg_free_result($result);
    return $rows;
}

function pg_row($sql, $values = [])
{
    return pg_rows($sql, $values)[0] ?? null;
}

function pg_value($sql, $values = [])
{
    $row = pg_row($sql, $values);
    return $row ? reset($row) : null;
}

function pg_save($sql, $values = [])
{
    return pg_value($sql . ' RETURNING 1', $values);
}

function pg_transaction($callback)
{
    pg_rows('BEGIN');
    try {
        $result = $callback();
        pg_rows('COMMIT');
        return $result;
    } catch (Throwable $error) {
        pg_rows('ROLLBACK');
        throw $error;
    }
}

function pg_named_id($table, $column, $name)
{
    // только перечисленные справочники могут пополняться через форму
    $allowed = ['device_types' => 'device_type_id', 'brands' => 'brand_id'];
    if (($allowed[$table] ?? '') !== $column) {
        throw new InvalidArgumentException('Неизвестный справочник');
    }
    $id = pg_value("SELECT $column FROM $table WHERE lower(name) = lower($1)", [$name]);
    if ($id !== null) {
        return (int) $id;
    }
    return (int) pg_value("INSERT INTO $table(name) VALUES ($1) RETURNING $column", [$name]);
}

function pg_device($type, $brand, $model, $serial)
{
    foreach ([$type, $brand, $model] as $value) {
        if (trim((string) $value) === '') {
            throw new InvalidArgumentException('Заполните вид техники, производителя и модель');
        }
    }
    if (strlen((string) $serial) > 120) {
        throw new InvalidArgumentException('Слишком длинный серийный номер');
    }
    $type = $type === 'Смартфон' ? 'Телефон' : $type;
    $typeId = pg_named_id('device_types', 'device_type_id', $type);
    $brandId = pg_named_id('brands', 'brand_id', $brand);
    $modelId = pg_value(
        'SELECT model_id FROM device_models WHERE brand_id=$1 AND lower(model_name)=lower($2)',
        [$brandId, $model]
    );
    if ($modelId === null) {
        $modelId = pg_value(
            'INSERT INTO device_models(device_type_id,brand_id,model_name) VALUES ($1,$2,$3) RETURNING model_id',
            [$typeId, $brandId, $model]
        );
    } else {
        $existingType = pg_value('SELECT device_type_id FROM device_models WHERE model_id=$1', [$modelId]);
        if ((int) $existingType !== $typeId) {
            throw new InvalidArgumentException('Такая модель уже зарегистрирована для другого вида техники');
        }
    }
    if (trim((string) $serial) !== '') {
        $device = pg_row('SELECT device_id,model_id FROM devices WHERE lower(serial_number)=lower($1)', [$serial]);
        if ($device) {
            if ((int) $device['model_id'] !== (int) $modelId) {
                throw new InvalidArgumentException('Серийный номер уже относится к другой модели');
            }
            return (int) $device['device_id'];
        }
    }
    return (int) pg_value(
        'INSERT INTO devices(model_id,serial_number) VALUES ($1,$2) RETURNING device_id',
        [$modelId, $serial ?: null]
    );
}

function pg_role($name)
{
    $id = pg_value('SELECT role_id FROM account_roles WHERE lower(name)=lower($1)', [$name]);
    if ($id === null) {
        throw new RuntimeException('В базе отсутствует роль ' . $name . '. Выполните заполнение справочников');
    }
    return (int) $id;
}

function pg_status_name($name)
{
    $names = [
        'Заявка' => 'Заявка создана',
        'Передан мастеру' => 'Ожидает мастера',
        'Ожидает приема устройства' => 'Ожидает приёма устройства',
        'Готов к выдаче' => 'Готов',
    ];
    return $names[$name] ?? $name;
}

function pg_display_status($name)
{
    $names = [
        'Заявка создана' => 'Заявка',
        'Ожидает приёма устройства' => 'Ожидает приема устройства',
        'Ожидает мастера' => 'Передан мастеру',
        'Готов' => 'Готов к выдаче',
    ];
    return $names[$name] ?? $name;
}

function pg_status($name)
{
    $dbName = pg_status_name($name);
    $id = pg_value('SELECT order_status_id FROM order_statuses WHERE name=$1', [$dbName]);
    if ($id === null) {
        throw new InvalidArgumentException('В базе нет статуса «' . $dbName . '»');
    }
    return (int) $id;
}

function pg_update_status($orderId, $status, $staffId, $note = '')
{
    $statusId = pg_status($status);
    // статус и его историю меняем в одной транзакции
    pg_rows('UPDATE repair_orders SET order_status_id=$1 WHERE order_id=$2', [$statusId, $orderId]);
    pg_rows(
        'INSERT INTO order_status_history(order_id,order_status_id,changed_by_employee_id,note) VALUES ($1,$2,$3,$4)',
        [$orderId, $statusId, $staffId, $note ?: null]
    );
}

function pg_staff_role($role)
{
    $role = mb_strtolower(trim($role), 'UTF-8');
    if (in_array($role, ['admin', 'manager', 'администратор'], true)) {
        return 'admin';
    }
    if (in_array($role, ['master', 'мастер'], true)) {
        return 'master';
    }
    if (in_array($role, ['client', 'клиент'], true)) {
        return 'client';
    }
    return null;
}

function repo_login($login, $password)
{
    $row = pg_row(
        'SELECT ua.account_id,ua.client_id,ua.employee_id,ua.password_hash,ua.is_active,r.name AS role,
                COALESCE(c.last_name,e.last_name) AS last_name,
                COALESCE(c.first_name,e.first_name) AS first_name,
                COALESCE(c.middle_name,e.middle_name) AS middle_name,
                e.dismissed_on
         FROM user_accounts ua JOIN account_roles r ON r.role_id=ua.role_id
         LEFT JOIN clients c ON c.client_id=ua.client_id
         LEFT JOIN employees e ON e.employee_id=ua.employee_id
         WHERE lower(ua.login)=lower($1)',
        [trim($login)]
    );
    if (!$row || $row['is_active'] !== 't' || !password_verify($password, $row['password_hash']) || $row['dismissed_on']) {
        return null;
    }
    $role = pg_staff_role($row['role']);
    if (!$role) {
        return null;
    }
    $id = $role === 'client' ? $row['client_id'] : $row['employee_id'];
    return [
        'id' => (int) $id,
        'account_id' => (int) $row['account_id'],
        'role' => $role,
        'full_name' => trim($row['last_name'] . ' ' . $row['first_name'] . ' ' . $row['middle_name']),
    ];
}

function repo_session_active($accountId, $personId, $role)
{
    $account = pg_row(
        'SELECT ua.is_active, ua.client_id, ua.employee_id, r.name AS role, e.dismissed_on
         FROM user_accounts ua
         JOIN account_roles r ON r.role_id = ua.role_id
         LEFT JOIN employees e ON e.employee_id = ua.employee_id
         WHERE ua.account_id = $1',
        [$accountId]
    );
    if (!$account || $account['is_active'] !== 't' || $account['dismissed_on']) {
        return false;
    }
    $actualRole = pg_staff_role($account['role']);
    $actualId = $actualRole === 'client' ? $account['client_id'] : $account['employee_id'];
    return $actualRole === $role && (int) $actualId === (int) $personId;
}

function pg_records($sql, $values = [], $id = 'id')
{
    $rows = pg_rows($sql, $values);
    $idFields = [
        'id', 'client_id', 'device_id', 'model_id', 'staff_id',
        'master_id', 'order_id', 'assignment_id', 'tariff_id',
        'part_id', 'service_id', 'work_id', 'status_id',
    ];
    foreach ($rows as &$row) {
        foreach ($idFields as $field) {
            if ($row[$field] ?? null) {
                $row[$field] = (int) $row[$field];
            }
        }
        foreach (['active', 'used'] as $field) {
            if (isset($row[$field])) {
                $row[$field] = in_array($row[$field], [true, 't', '1', 1], true);
            }
        }
    }
    unset($row);
    return $rows;
}

function repo_warranty_work_valid($workId)
{
    return (bool) pg_value(
        'SELECT 1 FROM order_works ow
         JOIN order_assignments a ON a.assignment_id=ow.assignment_id
         JOIN repair_orders o ON o.order_id=a.order_id
         JOIN work_tariffs wt ON wt.tariff_id=ow.tariff_id
         WHERE ow.order_work_id=$1 AND ow.performed_at IS NOT NULL
           AND o.issued_at IS NOT NULL AND wt.warranty_days > 0
           AND now() <= ow.performed_at + wt.warranty_days * interval \'1 day\' LIMIT 1',
        [$workId]
    );
}

function repo_report()
{
    $counts = pg_rows(
        'SELECT s.name, count(o.order_id) AS number
         FROM order_statuses s LEFT JOIN repair_orders o ON o.order_status_id=s.order_status_id
         GROUP BY s.order_status_id, s.name ORDER BY s.order_status_id'
    );
    $byStatus = [];
    $totalOrders = 0;
    foreach ($counts as $row) {
        $byStatus[pg_display_status($row['name'])] = (int) $row['number'];
        $totalOrders += (int) $row['number'];
    }
    $values = pg_row(
        'SELECT
           COALESCE((SELECT sum(wt.price) FROM order_works ow
                     JOIN order_assignments a ON a.assignment_id=ow.assignment_id
                     JOIN work_tariffs wt ON wt.tariff_id=ow.tariff_id),0) AS works,
           COALESCE((SELECT sum(op.quantity * pt.unit_price) FROM order_parts op
                     JOIN part_tariffs pt ON pt.part_tariff_id=op.part_tariff_id),0) AS parts,
           COALESCE((SELECT sum(discount_amount) FROM applied_discounts),0) AS discounts,
           COALESCE((SELECT sum(p.amount) FROM payments p
                     JOIN payment_statuses ps ON ps.payment_status_id=p.payment_status_id
                     WHERE ps.is_successful),0) AS payments,
           COALESCE((SELECT sum(amount) FROM payment_refunds),0) AS refunds,
           (SELECT count(*) FROM warranty_claims) AS warranty'
    );
    return [
        'total_orders' => $totalOrders,
        'by_status' => $byStatus,
        'total' => (float)$values['works'] + (float)$values['parts'] - (float)$values['discounts'],
        'paid' => (float)$values['payments'] - (float)$values['refunds'],
        'warranty' => (int)$values['warranty'],
    ];
}

function repo_data()
{
    // загружаем только сведения, которые используются на страницах
    $data = [];
    $data['clients'] = pg_records('SELECT client_id AS id,last_name,first_name,middle_name,phone,email FROM clients ORDER BY client_id DESC ');
    $data['models'] = pg_records(
        'SELECT dm.model_id AS id,CASE WHEN dt.name=\'Телефон\' THEN \'Смартфон\' ELSE dt.name END AS type,b.name AS brand,dm.model_name AS name
         FROM device_models dm JOIN device_types dt ON dt.device_type_id=dm.device_type_id
         JOIN brands b ON b.brand_id=dm.brand_id ORDER BY dm.model_id DESC '
    );
    $data['devices'] = pg_records('SELECT device_id AS id,model_id,serial_number AS serial,purchase_date AS release_date FROM devices ORDER BY device_id DESC ');
    $data['staff'] = pg_records(
        'SELECT e.employee_id AS id,e.last_name,e.first_name,e.middle_name,e.phone,
                COALESCE(r.name,CASE WHEN m.employee_id IS NULL THEN \'admin\' ELSE \'master\' END) AS role,
                (e.dismissed_on IS NULL AND COALESCE(ua.is_active,true)) AS active
         FROM employees e LEFT JOIN masters m ON m.employee_id=e.employee_id
         LEFT JOIN user_accounts ua ON ua.employee_id=e.employee_id
         LEFT JOIN account_roles r ON r.role_id=ua.role_id
         ORDER BY e.employee_id '
    );
    foreach ($data['staff'] as &$person) {
        $person['role'] = pg_staff_role($person['role']) ?? 'admin';
        $person['active'] = in_array($person['active'], [true, 't', '1', 1], true);
    }
    unset($person);
    $data['specializations'] = pg_records(
        'SELECT ms.master_specialization_id AS id,ms.employee_id AS staff_id,s.name,
                sl.name AS level,ms.experience_since AS experience_from
         FROM master_specializations ms JOIN specializations s USING (specialization_id)
         JOIN skill_levels sl USING (skill_level_id)
         WHERE ms.valid_from<=CURRENT_DATE AND (ms.valid_to IS NULL OR ms.valid_to>CURRENT_DATE)
         ORDER BY ms.employee_id,ms.specialization_id '
    );
    $allTypes = ['Смартфон','Ноутбук','Планшет','Компьютер','Принтер','Монитор','Игровая приставка','Телевизор','Другая техника'];
    $data['tariffs'] = pg_records(
        'SELECT wt.tariff_id AS id,wt.work_type_id,w.name,wt.price,wt.warranty_days,
                COALESCE(string_agg(DISTINCT s.name,\',\'),\'\') AS specialties
         FROM work_tariffs wt JOIN work_types w ON w.work_type_id=wt.work_type_id
         LEFT JOIN work_type_specializations wts ON wts.work_type_id=w.work_type_id
         LEFT JOIN specializations s ON s.specialization_id=wts.specialization_id
         WHERE wt.valid_from<=CURRENT_DATE AND wt.valid_from=(
             SELECT max(t2.valid_from) FROM work_tariffs t2
             WHERE t2.work_type_id=wt.work_type_id AND t2.valid_from<=CURRENT_DATE)
         GROUP BY wt.tariff_id,wt.work_type_id,w.name,wt.price,wt.warranty_days
         ORDER BY w.name '
    );
    foreach ($data['tariffs'] as &$tariff) {
        $categories = [];
        foreach ($allTypes as $type) {
            $required = app_specialty_for_type($type);
            if ($tariff['specialties'] === '' || ($required && str_contains($tariff['specialties'], $required))) {
                $categories[] = $type;
            }
        }
        $tariff['categories'] = implode(',', $categories);
    }
    unset($tariff);
    $data['statuses'] = array_values(array_unique(array_map('pg_display_status',
        array_column(pg_rows('SELECT name FROM order_statuses ORDER BY order_status_id'), 'name'))));
    $data['orders'] = pg_records(
        'SELECT o.order_id AS id,o.client_id,o.device_id,o.order_status_id,s.name AS status,
                o.created_at,o.fault_description AS problem,o.queue_priority AS priority,
                o.planned_ready_at AS planned_date,o.issued_at,
                COALESCE((SELECT note FROM order_status_history h WHERE h.order_id=o.order_id AND
                    h.note LIKE \'Клиент выбрал мастера: %\' ORDER BY h.status_history_id LIMIT 1),\'\') AS preferred_note,
                COALESCE((SELECT note FROM order_status_history h WHERE h.order_id=o.order_id AND
                    s.name=\'Отказано\' ORDER BY h.status_history_id DESC LIMIT 1),\'\') AS reject_reason
         FROM repair_orders o JOIN order_statuses s ON s.order_status_id=o.order_status_id
         ORDER BY o.order_id DESC '
    );
    foreach ($data['orders'] as &$order) {
        $order['status'] = pg_display_status($order['status']);
        $order['condition'] = '';
        if (preg_match('/\nСостояние при приеме: (.*)$/su', $order['problem'], $conditionMatch)) {
            $order['condition'] = trim($conditionMatch[1]);
            $order['problem'] = trim(substr($order['problem'], 0, -strlen($conditionMatch[0])));
        }
        $order['service_id'] = null;
        $order['device_type_note'] = null;
        $order['preferred_master_id'] = 0;
        if (preg_match('/^Клиент выбрал мастера: ([0-9]+)/', $order['preferred_note'], $found)) {
            $order['preferred_master_id'] = (int) $found[1];
        }
        if (preg_match('/^Услуга: ([^\n]+)\nОписание неисправности: (.*)$/su', $order['problem'], $found)) {
            $name = trim($found[1]);
            $order['problem'] = trim($found[2]);
            foreach ($data['tariffs'] as $tariff) {
                if ($tariff['name'] === $name) {
                    $order['service_id'] = $tariff['id'];
                    break;
                }
            }
        }
    }
    unset($order);
    $data['history'] = pg_records(
        'SELECT h.status_history_id AS id,h.order_id,s.name AS status,h.changed_at,
                h.changed_by_employee_id AS staff_id,h.note AS comment
         FROM order_status_history h JOIN order_statuses s ON s.order_status_id=h.order_status_id
         ORDER BY h.status_history_id DESC '
    );
    foreach ($data['history'] as &$history) {
        $history['status'] = pg_display_status($history['status']);
    }
    unset($history);
    $data['assignments'] = pg_records(
        'SELECT assignment_id AS id,order_id,employee_id AS master_id,assigned_at,
                work_started_at AS started_at,work_completed_at AS completed_at
         FROM order_assignments ORDER BY assignment_id DESC '
    );
    $data['works'] = pg_records(
        'SELECT ow.order_work_id AS id,a.order_id,ow.assignment_id,ow.tariff_id,wt.price,
                w.name,ow.performed_at AS done_at
         FROM order_works ow JOIN order_assignments a ON a.assignment_id=ow.assignment_id
         JOIN work_tariffs wt ON wt.tariff_id=ow.tariff_id
         JOIN work_types w ON w.work_type_id=wt.work_type_id
         ORDER BY ow.order_work_id DESC '
    );
    $data['parts'] = pg_records(
        'SELECT p.part_id AS id,p.article,p.name,
                COALESCE((SELECT sum(im.quantity_delta) FROM inventory_movements im
                    LEFT JOIN supply_lines sl ON sl.supply_line_id=im.supply_line_id
                    LEFT JOIN order_parts op ON op.order_part_id=im.order_part_id
                    WHERE COALESCE(sl.part_id,op.part_id,im.adjustment_part_id)=p.part_id),0) AS stock
         FROM parts p ORDER BY p.part_id '
    );
    $data['order_parts'] = pg_records(
        'SELECT op.order_part_id AS id,op.order_id,op.part_id,op.quantity,pt.unit_price AS price
         FROM order_parts op JOIN part_tariffs pt ON pt.part_tariff_id=op.part_tariff_id
         ORDER BY op.order_part_id DESC '
    );
    $data['discounts'] = pg_records(
        'SELECT ad.applied_discount_id AS id,ad.order_id,dt.name,ad.discount_amount AS amount,
                CASE WHEN ad.client_benefit_id IS NOT NULL THEN \'benefit\' ELSE \'promo\' END AS kind
         FROM applied_discounts ad JOIN discount_types dt ON dt.discount_type_id=ad.discount_type_id'
    );
    $data['payments'] = pg_records(
        'SELECT p.payment_id AS id,p.order_id,p.amount,p.paid_at,p.external_reference,pm.name AS method,
                CASE WHEN ps.is_successful THEN \'Проведен\' ELSE ps.name END AS status,
                COALESCE((SELECT sum(r.amount) FROM payment_refunds r WHERE r.payment_id=p.payment_id),0) AS refund_amount
         FROM payments p JOIN payment_methods pm ON pm.payment_method_id=p.payment_method_id
         JOIN payment_statuses ps ON ps.payment_status_id=p.payment_status_id'
    );
    $data['warranties'] = pg_records(
        'SELECT wc.warranty_claim_id AS id,wc.repair_order_id AS order_id,
                wc.original_order_work_id AS work_id,wc.claimed_at AS opened_at,
                wc.reason,wc.decision,ws.name AS status
         FROM warranty_claims wc JOIN warranty_statuses ws ON ws.warranty_status_id=wc.warranty_status_id'
    );
    $data['reviews'] = pg_records(
        'SELECT order_work_id AS work_id,rating,comment,created_at FROM reviews'
    );
    $data['benefits'] = pg_records(
        'SELECT cb.client_benefit_id AS id,cb.client_id,bc.name,
                0 AS percent,(cb.valid_from<=CURRENT_DATE AND
                (cb.valid_to IS NULL OR cb.valid_to>CURRENT_DATE)) AS active
         FROM client_benefits cb JOIN benefit_categories bc USING (benefit_category_id)'
    );
    $data['vouchers'] = pg_records(
        'SELECT v.voucher_id AS id,v.code,v.client_id,v.face_value AS amount,
                (v.face_value+COALESCE((SELECT sum(vt.amount_change)
                 FROM voucher_transactions vt WHERE vt.voucher_id=v.voucher_id),0)<=0) AS used
         FROM vouchers v'
    );
    $data['promo_codes'] = pg_rows(
        'SELECT replace(dt.name,\'Промокод \',\'\') AS code,
                dt.value AS percent,true AS active
         FROM discount_types dt JOIN discount_calculation_types dct
         ON dct.discount_calculation_type_id=dt.calculation_type_id
         WHERE dt.name LIKE \'Промокод %\' AND dct.value_kind=\'percentage\''
    );
    return $data;
}

function pg_current_user()
{
    $user = $_SESSION['user'] ?? null;
    if (!$user) {
        throw new InvalidArgumentException('Сначала войдите в систему');
    }
    return $user;
}

function pg_admin()
{
    $user = pg_current_user();
    if ($user['role'] !== 'admin') {
        throw new InvalidArgumentException('Эта операция доступна только администратору');
    }
    return $user;
}

function pg_required($value, $text)
{
    if (trim((string) $value) === '') {
        throw new InvalidArgumentException($text);
    }
}

function pg_order($id)
{
    $row = pg_row(
        'SELECT o.order_id,o.client_id,o.device_id,o.order_status_id,s.name AS status,o.issued_at
         FROM repair_orders o JOIN order_statuses s ON s.order_status_id=o.order_status_id
         WHERE o.order_id=$1 FOR UPDATE OF o',
        [$id]
    );
    if (!$row) {
        throw new InvalidArgumentException('Заказ не найден');
    }
    $row['status'] = pg_display_status($row['status']);
    return $row;
}

function pg_service_allowed($tariffId, $type)
{
    $required = app_specialty_for_type($type);
    $row = pg_row(
        'SELECT w.requires_specialist,wt.work_type_id FROM work_tariffs wt
         JOIN work_types w ON w.work_type_id=wt.work_type_id WHERE wt.tariff_id=$1',
        [$tariffId]
    );
    if (!$row) {
        return false;
    }
    if ($row['requires_specialist'] === 'f') {
        return true;
    }
    if (!$required) {
        return false;
    }
    return (bool) pg_value(
        'SELECT 1 FROM work_type_specializations wts JOIN specializations s
         ON s.specialization_id=wts.specialization_id
         WHERE wts.work_type_id=$1 AND s.name=$2 LIMIT 1',
        [$row['work_type_id'], $required]
    );
}

function pg_qualified($masterId, $type, $tariffId = null)
{
    $required = app_specialty_for_type($type);
    if (!$required) {
        return false;
    }

    $isActiveMaster = pg_value(
        'SELECT 1 FROM masters m JOIN employees e ON e.employee_id=m.employee_id
         LEFT JOIN user_accounts ua ON ua.employee_id=e.employee_id
         WHERE m.employee_id=$1 AND e.dismissed_on IS NULL
           AND COALESCE(ua.is_active, true) LIMIT 1',
        [$masterId]
    );
    if (!$isActiveMaster) {
        return false;
    }

    // даже общую диагностику выполняет мастер по данному виду техники
    $hasType = pg_value(
        'SELECT 1 FROM master_specializations ms JOIN specializations s
         ON s.specialization_id=ms.specialization_id
         WHERE ms.employee_id=$1 AND s.name=$2 AND ms.valid_from<=CURRENT_DATE
           AND (ms.valid_to IS NULL OR ms.valid_to>CURRENT_DATE) LIMIT 1',
        [$masterId, $required]
    );
    if (!$hasType) {
        return false;
    }
    if (!$tariffId) {
        return true;
    }

    $tariff = pg_row(
        'SELECT w.work_type_id,w.requires_specialist
         FROM work_tariffs wt JOIN work_types w USING (work_type_id)
         WHERE wt.tariff_id=$1 AND wt.valid_from<=CURRENT_DATE',
        [$tariffId]
    );
    if (!$tariff) {
        return false;
    }
    if ($tariff['requires_specialist'] === 'f') {
        return true;
    }

    // для профильной работы дополнительно проверяем квалификацию по тарифу
    return (bool) pg_value(
        'SELECT 1 FROM work_type_specializations wts
         JOIN master_specializations ms USING (specialization_id)
         JOIN skill_levels actual ON actual.skill_level_id=ms.skill_level_id
         JOIN skill_levels needed ON needed.skill_level_id=wts.minimum_skill_level_id
         WHERE wts.work_type_id=$1 AND ms.employee_id=$2
           AND actual.level_rank>=needed.level_rank
           AND ms.valid_from<=CURRENT_DATE
           AND (ms.valid_to IS NULL OR ms.valid_to>CURRENT_DATE) LIMIT 1',
        [$tariff['work_type_id'], $masterId]
    );
}

function pg_find_discount($name)
{
    return pg_row(
        'SELECT dt.discount_type_id,dct.value_kind,dt.value FROM discount_types dt
         JOIN discount_calculation_types dct ON dct.discount_calculation_type_id=dt.calculation_type_id
         WHERE lower(dt.name)=lower($1)',
        [$name]
    );
}

function pg_order_subtotal($orderId)
{
    return (float) pg_value(
        'SELECT COALESCE((SELECT sum(wt.price) FROM order_works ow
            JOIN order_assignments a ON a.assignment_id=ow.assignment_id
            JOIN work_tariffs wt ON wt.tariff_id=ow.tariff_id WHERE a.order_id=$1),0)
            + COALESCE((SELECT sum(op.quantity*pt.unit_price) FROM order_parts op
            JOIN part_tariffs pt ON pt.part_tariff_id=op.part_tariff_id WHERE op.order_id=$1),0)',
        [$orderId]
    );
}

function pg_apply_discount($orderId, $discount, $benefitId = null)
{
    $subtotal = pg_order_subtotal($orderId);
    if (pg_value('SELECT 1 FROM applied_discounts WHERE order_id=$1 AND discount_type_id=$2',
        [$orderId,$discount['discount_type_id']])) {
        throw new InvalidArgumentException('Такая скидка уже применена');
    }
    $old = (float) pg_value('SELECT COALESCE(sum(discount_amount),0) FROM applied_discounts WHERE order_id=$1', [$orderId]);
    $amount = $discount['value_kind'] === 'percentage'
        ? round($subtotal * (float) $discount['value'] / 100, 2)
        : min($subtotal, (float) $discount['value']);
    if ($amount <= 0 || $old + $amount > $subtotal) {
        throw new InvalidArgumentException('Скидка превышает стоимость ремонта');
    }
    pg_rows(
        'INSERT INTO applied_discounts(order_id,discount_type_id,client_benefit_id,calculation_base,discount_amount)
         VALUES ($1,$2,$3,$4,$5)',
        [$orderId, $discount['discount_type_id'], $benefitId, $subtotal, $amount]
    );
}

function repo_action($action, $values = [])
{
    return pg_transaction(function () use ($action, $values) {
        $user = $action === 'register' ? null : pg_current_user();
        $orderId = (int) ($values['id'] ?? 0);

        switch ($action) {
            case 'register':
                foreach (['last_name','first_name','phone','login','password'] as $field) {
                    pg_required($values[$field] ?? '', 'Заполните обязательные поля');
                }
                if (!preg_match('/^\+?[0-9() \-]{10,20}$/', $values['phone'])) {
                    throw new InvalidArgumentException('Проверьте телефон');
                }
                if (!empty($values['email']) && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException('Проверьте электронную почту');
                }
                if (strlen($values['password']) < 8 || strlen($values['password']) > 128) {
                    throw new InvalidArgumentException('Пароль должен содержать от 8 до 128 символов');
                }
                if (pg_value('SELECT 1 FROM user_accounts WHERE lower(login)=lower($1)', [$values['login']])) {
                    throw new InvalidArgumentException('Этот логин уже занят');
                }
                $clientId = pg_value(
                    'INSERT INTO clients(last_name,first_name,middle_name,phone,email)
                     VALUES ($1,$2,$3,$4,$5) RETURNING client_id',
                    [$values['last_name'],$values['first_name'],$values['middle_name'] ?: null,
                     $values['phone'],$values['email'] ?: null]
                );
                pg_rows(
                    'INSERT INTO user_accounts(login,password_hash,role_id,client_id) VALUES ($1,$2,$3,$4)',
                    [$values['login'],password_hash($values['password'], PASSWORD_DEFAULT),pg_role('Клиент'),$clientId]
                );
                return (int) $clientId;

            case 'client_add':
                pg_admin();
                foreach (['last_name','first_name','phone'] as $field) {
                    pg_required($values[$field] ?? '', 'Укажите ФИО и телефон');
                }
                return (int) pg_value(
                    'INSERT INTO clients(last_name,first_name,middle_name,phone,email)
                     VALUES ($1,$2,$3,$4,$5) RETURNING client_id',
                    [$values['last_name'],$values['first_name'],$values['middle_name'] ?: null,
                     $values['phone'],$values['email'] ?: null]
                );

            case 'device_add':
                pg_admin();
                pg_required($values['serial'] ?? '', 'Укажите серийный номер');
                return pg_device($values['type'], $values['brand'], $values['model'], $values['serial']);

            case 'client_profile_update':
                if ($user['role'] !== 'client') {
                    throw new InvalidArgumentException('Изменить профиль может только клиент');
                }
                foreach (['last_name', 'first_name', 'phone'] as $field) {
                    pg_required($values[$field] ?? '', 'Заполните имя, фамилию и телефон');
                }
                if (!preg_match('/^\+?[0-9() \-]{10,20}$/', $values['phone'])) {
                    throw new InvalidArgumentException('Проверьте номер телефона');
                }
                if (!empty($values['email']) && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new InvalidArgumentException('Проверьте адрес электронной почты');
                }
                pg_rows(
                    'UPDATE clients SET last_name=$1,first_name=$2,middle_name=$3,phone=$4,email=$5
                     WHERE client_id=$6',
                    [$values['last_name'], $values['first_name'], $values['middle_name'] ?: null,
                     $values['phone'], $values['email'] ?: null, $user['id']]
                );
                return $user['id'];

            case 'request_add':
                if ($user['role'] !== 'client') {
                    throw new InvalidArgumentException('Создать заявку может только клиент');
                }
                if (empty($values['confirmed'])) {
                    throw new InvalidArgumentException('Подтвердите данные устройства');
                }
                foreach (['brand','model','serial','problem','type'] as $field) {
                    pg_required($values[$field] ?? '', 'Заполните все данные заявки');
                }
                $type = $values['type'] === 'Другая техника'
                    ? trim($values['type_other'] ?? '') : $values['type'];
                pg_required($type, 'Укажите вид техники');
                $tariffId = (int) ($values['service_id'] ?? 0);
                $tariff = pg_row(
                    'SELECT wt.tariff_id,w.name FROM work_tariffs wt
                     JOIN work_types w ON w.work_type_id=wt.work_type_id
                     WHERE wt.tariff_id=$1 AND wt.valid_from<=CURRENT_DATE',
                    [$tariffId]
                );
                if (!$tariff) {
                    throw new InvalidArgumentException('Выберите действующую услугу');
                }
                if (!pg_service_allowed($tariffId, $type)) {
                    throw new InvalidArgumentException('Услуга не подходит для этого вида техники');
                }
                $masterId = (int) ($values['preferred_master_id'] ?? 0);
                if ($masterId && !pg_qualified($masterId, $type, $tariffId)) {
                    throw new InvalidArgumentException('У выбранного мастера нет нужной специализации');
                }
                $deviceId = pg_device($type, $values['brand'], $values['model'], $values['serial']);
                $description = 'Услуга: ' . $tariff['name'] . "\n" .
                    'Описание неисправности: ' . trim($values['problem']);
                $orderId = (int) pg_value(
                    'INSERT INTO repair_orders(client_id,device_id,order_status_id,fault_description)
                     VALUES ($1,$2,$3,$4) RETURNING order_id',
                    [$user['id'],$deviceId,pg_status('Заявка'),$description]
                );
                if ($masterId) {
                    // выбор клиента фиксируем до назначения мастера
                    pg_rows(
                        'INSERT INTO order_status_history(order_id,order_status_id,note) VALUES ($1,$2,$3)',
                        [$orderId, pg_status('Заявка'), 'Клиент выбрал мастера: ' . $masterId]
                    );
                }
                return $orderId;

            case 'order_add':
                pg_admin();
                $clientId = (int) ($values['client_id'] ?? 0);
                $deviceId = (int) ($values['device_id'] ?? 0);
                if (!$clientId || !$deviceId || !pg_value('SELECT 1 FROM clients WHERE client_id=$1',[$clientId]) ||
                    !pg_value('SELECT 1 FROM devices WHERE device_id=$1',[$deviceId])) {
                    throw new InvalidArgumentException('Выберите клиента и устройство');
                }
                pg_required($values['problem'] ?? '', 'Опишите неисправность');
                $description = trim($values['problem']);
                $condition = trim((string) ($values['condition'] ?? ''));
                if ($condition !== '') {
                    $description .= "\nСостояние при приеме: " . $condition;
                }
                return (int) pg_value(
                    'INSERT INTO repair_orders(client_id,device_id,order_status_id,fault_description,
                        queue_priority,planned_ready_at,device_received_at)
                     VALUES ($1,$2,$3,$4,$5,$6,now()) RETURNING order_id',
                    [$clientId,$deviceId,pg_status('Принят'),$description,
                     max(0, (int) $values['priority']),$values['planned_date'] ?: null]
                );

            case 'request_decide':
                pg_admin();
                $order = pg_order($orderId);
                if (!in_array($order['status'], ['Заявка', 'Ожидает приема устройства'], true)) {
                    throw new InvalidArgumentException('Заявка уже рассмотрена');
                }
                $accept = ($values['decision'] ?? '') === 'accept';
                if (!$accept) {
                    pg_required($values['reason'] ?? '', 'Укажите причину отказа');
                }
                if ($accept) {
                    pg_rows('UPDATE repair_orders SET device_received_at=now() WHERE order_id=$1',[$orderId]);
                }
                pg_update_status($orderId, $accept ? 'Принят' : 'Отказано', $user['id'],
                    $accept ? 'Заявка принята' : $values['reason']);
                return $orderId;

            case 'assign':
                pg_admin();
                $order = pg_order($orderId);
                if (!in_array($order['status'], ['Принят', 'Передан мастеру'], true)) {
                    throw new InvalidArgumentException('Назначить мастера можно только для принятого заказа');
                }
                $masterId = (int) ($values['master_id'] ?? 0);
                $deviceType = pg_value(
                    'SELECT dt.name FROM devices d JOIN device_models dm USING (model_id)
                     JOIN device_types dt USING (device_type_id) WHERE d.device_id=$1',
                    [$order['device_id']]
                );
                $serviceId = null;
                $requestText = pg_value('SELECT fault_description FROM repair_orders WHERE order_id=$1', [$orderId]);
                if (preg_match('/^Услуга: ([^\n]+)/u', $requestText ?? '', $matches)) {
                    $serviceId = pg_value(
                        'SELECT wt.tariff_id FROM work_tariffs wt
                         JOIN work_types w USING (work_type_id)
                         WHERE w.name=$1 AND wt.valid_from<=CURRENT_DATE
                         ORDER BY wt.valid_from DESC LIMIT 1',
                        [$matches[1]]
                    );
                }
                if (!pg_qualified($masterId, $deviceType === 'Телефон' ? 'Смартфон' : $deviceType, $serviceId)) {
                    throw new InvalidArgumentException('У выбранного мастера нет нужной специализации');
                }
                $choice = pg_value(
                    'SELECT note FROM order_status_history WHERE order_id=$1
                     AND note LIKE \'Клиент выбрал мастера: %\' ORDER BY status_history_id LIMIT 1',
                    [$orderId]
                );
                if ($choice && (int) substr($choice, strlen('Клиент выбрал мастера: ')) !== $masterId) {
                    throw new InvalidArgumentException('Клиент выбрал другого мастера, требуется согласование');
                }
                $assigned = pg_row(
                    'SELECT assignment_id,employee_id FROM order_assignments
                     WHERE order_id=$1 AND work_completed_at IS NULL ORDER BY assignment_id DESC LIMIT 1',
                    [$orderId]
                );
                if ($assigned && (int) $assigned['employee_id'] !== $masterId) {
                    throw new InvalidArgumentException('Другой мастер уже назначен');
                }
                if (!$assigned) {
                    pg_rows('INSERT INTO order_assignments(order_id,employee_id) VALUES ($1,$2)', [$orderId,$masterId]);
                }
                if ($order['status'] === 'Принят') {
                    pg_update_status($orderId, 'Передан мастеру', $user['id'], 'Назначен мастер');
                }
                return $orderId;

            case 'status':
                if ($user['role'] === 'client') {
                    throw new InvalidArgumentException('Клиент не может менять статус');
                }
                $order = pg_order($orderId);
                $newStatus = trim((string) ($values['status'] ?? ''));
                if (!in_array($newStatus, status_changes()[$order['status']] ?? [], true)) {
                    throw new InvalidArgumentException('Недопустимый переход между статусами');
                }
                $assigned = pg_row(
                    'SELECT assignment_id,employee_id,work_started_at FROM order_assignments
                     WHERE order_id=$1 AND work_completed_at IS NULL ORDER BY assignment_id DESC LIMIT 1',
                    [$orderId]
                );
                if ($user['role'] === 'master') {
                    if (!$assigned || (int) $assigned['employee_id'] !== (int) $user['id']) {
                        throw new InvalidArgumentException('Заказ назначен другому мастеру');
                    }
                    if (!in_array($newStatus, ['В ремонте', 'Готов к выдаче'], true)) {
                        throw new InvalidArgumentException('Этот статус меняет администратор');
                    }
                }
                if ($newStatus === 'В ремонте' && !$assigned) {
                    throw new InvalidArgumentException('Сначала назначьте мастера');
                }
                if ($newStatus === 'В ремонте' && $assigned && !$assigned['work_started_at']) {
                    pg_rows('UPDATE order_assignments SET work_started_at=now() WHERE assignment_id=$1', [$assigned['assignment_id']]);
                }
                if ($newStatus === 'Готов к выдаче') {
                    if (!$assigned || !$assigned['work_started_at']) {
                        throw new InvalidArgumentException('Сначала назначьте мастера и начните ремонт');
                    }
                    if (!pg_value(
                        'SELECT 1 FROM order_works ow JOIN order_assignments a
                         ON a.assignment_id = ow.assignment_id
                         WHERE a.order_id=$1 AND ow.performed_at IS NOT NULL LIMIT 1',
                        [$orderId]
                    )) {
                        throw new InvalidArgumentException('Перед завершением укажите выполненную работу или диагностику');
                    }
                    pg_rows(
                        'UPDATE order_assignments SET work_completed_at=now() WHERE assignment_id=$1',
                        [$assigned['assignment_id']]
                    );
                }
                pg_update_status($orderId,$newStatus,$user['id'],trim($values['comment'] ?? ''));
                return $orderId;

            case 'work_add':
                $order = pg_order($orderId);
                if ($user['role'] === 'client' || !in_array($order['status'], ['Передан мастеру', 'В ремонте'], true)) {
                    throw new InvalidArgumentException('В этом заказе нельзя добавлять работы');
                }
                $assigned = pg_row(
                    'SELECT assignment_id,employee_id,work_started_at FROM order_assignments
                     WHERE order_id=$1 AND work_completed_at IS NULL ORDER BY assignment_id DESC LIMIT 1',
                    [$orderId]
                );
                if (!$assigned || ($user['role'] === 'master' && (int)$assigned['employee_id'] !== (int)$user['id'])) {
                    throw new InvalidArgumentException('Работу может добавить назначенный мастер');
                }
                if (!$assigned['work_started_at']) {
                    pg_rows('UPDATE order_assignments SET work_started_at=now() WHERE assignment_id=$1',[$assigned['assignment_id']]);
                }
                $tariffId = (int) ($values['tariff_id'] ?? 0);
                pg_rows(
                    'INSERT INTO order_works(assignment_id,tariff_id,performed_at) VALUES ($1,$2,now())',
                    [$assigned['assignment_id'],$tariffId]
                );
                return $orderId;

            case 'part_add':
                pg_admin();
                $order = pg_order($orderId);
                if (!in_array($order['status'], ['Передан мастеру', 'В ремонте'], true)) {
                    throw new InvalidArgumentException('Нельзя добавлять запчасти в завершенный заказ');
                }
                $partId = (int) ($values['part_id'] ?? 0);
                $quantity = (int) ($values['quantity'] ?? 0);
                if ($quantity <= 0) {
                    throw new InvalidArgumentException('Количество должно быть больше нуля');
                }
                $tariffId = pg_value(
                    'SELECT part_tariff_id FROM part_tariffs WHERE part_id=$1 AND valid_from<=CURRENT_DATE
                     ORDER BY valid_from DESC LIMIT 1',[$partId]
                );
                if (!$tariffId) {
                    throw new InvalidArgumentException('Для запчасти не задана действующая цена');
                }
                $lineId = pg_value(
                    'INSERT INTO order_parts(order_id,part_id,part_tariff_id,quantity)
                     VALUES ($1,$2,$3,$4) RETURNING order_part_id',
                    [$orderId,$partId,$tariffId,$quantity]
                );
                $typeId = pg_value("SELECT movement_type_id FROM inventory_movement_types WHERE source_kind='order' AND direction=-1 LIMIT 1");
                if (!$typeId) {
                    throw new InvalidArgumentException('Не настроен тип складского расхода');
                }
                pg_rows('INSERT INTO inventory_movements(movement_type_id,order_part_id,quantity_delta) VALUES ($1,$2,$3)',
                    [$typeId,$lineId,-$quantity]);
                return $orderId;

            case 'discount_add':
                pg_admin();
                $order = pg_order($orderId);
                if ($order['status'] !== 'Готов к выдаче') {
                    throw new InvalidArgumentException('Скидку можно применить после завершения ремонта');
                }
                pg_required($values['name'] ?? '', 'Укажите основание скидки');
                $amount = (float) ($values['amount'] ?? 0);
                if ($amount <= 0) {
                    throw new InvalidArgumentException('Укажите сумму скидки');
                }
                $calcId = pg_value("SELECT discount_calculation_type_id FROM discount_calculation_types WHERE value_kind='fixed' LIMIT 1");
                if (!$calcId) {
                    throw new InvalidArgumentException('В справочнике нет фиксированного типа скидки');
                }
                $typeId = pg_value(
                    'INSERT INTO discount_types(name,calculation_type_id,value) VALUES ($1,$2,$3) RETURNING discount_type_id',
                    ['Разовая скидка №' . $orderId . ': ' . trim($values['name']),$calcId,$amount]
                );
                pg_apply_discount($orderId,['discount_type_id'=>$typeId,'value'=>$amount,'value_kind'=>'fixed']);
                return $orderId;

            case 'client_discount':
                if ($user['role'] !== 'client') {
                    throw new InvalidArgumentException('Скидку выбирает клиент');
                }
                $order = pg_order($orderId);
                if ((int)$order['client_id'] !== (int)$user['id'] || $order['status'] !== 'Готов к выдаче') {
                    throw new InvalidArgumentException('Скидки доступны для готового заказа');
                }
                $kind = $values['kind'] ?? '';
                if ($kind === 'voucher') {
                    $code = strtoupper(trim((string) ($values['code'] ?? '')));
                    $voucher = pg_row(
                        'SELECT voucher_id,face_value,expires_on FROM vouchers
                         WHERE code=$1 AND client_id=$2 FOR UPDATE',
                        [$code,$user['id']]
                    );
                    if (!$voucher || ($voucher['expires_on'] && $voucher['expires_on'] < date('Y-m-d'))) {
                        throw new InvalidArgumentException('Ваучер не найден или срок его действия истек');
                    }
                    $balance = (float) $voucher['face_value'] + (float) pg_value(
                        'SELECT COALESCE(sum(amount_change),0) FROM voucher_transactions WHERE voucher_id=$1',
                        [$voucher['voucher_id']]
                    );
                    $subtotal = pg_order_subtotal($orderId);
                    $discounts = (float) pg_value(
                        'SELECT COALESCE(sum(discount_amount),0) FROM applied_discounts WHERE order_id=$1',
                        [$orderId]
                    );
                    $paid = (float) pg_value(
                        'SELECT COALESCE(sum(p.amount),0) - COALESCE((SELECT sum(r.amount)
                          FROM payment_refunds r JOIN payments p2 ON p2.payment_id=r.payment_id
                          WHERE p2.order_id=$1),0) FROM payments p
                         JOIN payment_statuses ps USING (payment_status_id)
                         WHERE p.order_id=$1 AND ps.is_successful', [$orderId]
                    );
                    $due = max(0, $subtotal - $discounts - $paid);
                    $amount = min($due, $balance);
                    if ($amount <= 0) {
                        throw new InvalidArgumentException('По ваучеру нет доступной суммы для этого заказа');
                    }
                    if (pg_value('SELECT 1 FROM payments WHERE order_id=$1 AND external_reference=$2', [$orderId,$code])) {
                        throw new InvalidArgumentException('Ваучер уже указан для этого заказа');
                    }
                    $methodId = pg_value('SELECT payment_method_id FROM payment_methods WHERE is_voucher=true');
                    $pendingId = pg_value(
                        "SELECT payment_status_id FROM payment_statuses WHERE name='Создан'"
                    );
                    if (!$methodId || !$pendingId) {
                        throw new InvalidArgumentException('В справочниках не настроена оплата ваучером');
                    }
                    pg_rows(
                        'INSERT INTO payments(order_id,payment_method_id,payment_status_id,amount,external_reference)
                         VALUES ($1,$2,$3,$4,$5)',
                        [$orderId,$methodId,$pendingId,$amount,$code]
                    );
                    return $orderId;
                }
                if ($kind === 'benefit') {
                    $benefit = pg_row(
                        'SELECT cb.client_benefit_id,bc.name FROM client_benefits cb
                         JOIN benefit_categories bc USING (benefit_category_id)
                         WHERE cb.client_id=$1 AND cb.valid_from<=CURRENT_DATE
                         AND (cb.valid_to IS NULL OR cb.valid_to>CURRENT_DATE)
                         ORDER BY cb.client_benefit_id LIMIT 1',[$user['id']]
                    );
                    if (!$benefit) {
                        throw new InvalidArgumentException('Нет действующей льготы');
                    }
                    $discount = pg_row(
                        'SELECT dt.discount_type_id,dct.value_kind,dt.value
                         FROM discount_types dt
                         JOIN discount_calculation_types dct ON dct.discount_calculation_type_id=dt.calculation_type_id
                         WHERE lower(dt.name) LIKE lower($1) ORDER BY dt.discount_type_id LIMIT 1',
                        ['%' . ($benefit['name'] === 'Студент' ? 'Студенческ' : $benefit['name']) . '%']
                    );
                    if (!$discount) {
                        throw new InvalidArgumentException('Для льготы не настроен вид скидки');
                    }
                    pg_apply_discount($orderId,$discount,$benefit['client_benefit_id']);
                    return $orderId;
                }
                if ($kind === 'promo') {
                    $code = strtoupper(trim((string) ($values['code'] ?? '')));
                    $discount = pg_find_discount('Промокод ' . $code);
                    if (!$discount) {
                        throw new InvalidArgumentException('Промокод не найден');
                    }
                    pg_apply_discount($orderId,$discount);
                    return $orderId;
                }
                throw new InvalidArgumentException('Неизвестная скидка');

            case 'payment_add':
                pg_admin();
                $order = pg_order($orderId);
                if ($order['status'] !== 'Готов к выдаче') {
                    throw new InvalidArgumentException('Оплата доступна после завершения ремонта');
                }
                $amount = (float) ($values['amount'] ?? 0);
                if ($amount <= 0) {
                    throw new InvalidArgumentException('Введите сумму оплаты');
                }
                $methodId = pg_value('SELECT payment_method_id FROM payment_methods WHERE name=$1',[$values['method']]);
                $successfulId = pg_value('SELECT payment_status_id FROM payment_statuses WHERE is_successful=true LIMIT 1');
                if (!$methodId || !$successfulId) {
                    throw new InvalidArgumentException('Способ оплаты или статус не настроен');
                }
                if ($values['method'] === 'Предоплаченный ваучер') {
                    $code = strtoupper(trim((string) ($values['voucher_code'] ?? '')));
                    $pending = pg_row(
                        'SELECT p.payment_id,p.amount,v.voucher_id FROM payments p
                         JOIN vouchers v ON v.code=p.external_reference
                         JOIN payment_statuses ps ON ps.payment_status_id=p.payment_status_id
                         JOIN repair_orders o ON o.order_id=p.order_id
                         WHERE p.order_id=$1 AND p.external_reference=$2 AND v.client_id=o.client_id
                         AND ps.is_successful=false FOR UPDATE OF p',
                        [$orderId,$code]
                    );
                    if (!$pending || abs((float)$pending['amount'] - $amount) > 0.001) {
                        throw new InvalidArgumentException('Найдите предъявленный ваучер и укажите его сумму');
                    }
                    pg_rows(
                        'UPDATE payments SET payment_status_id=$1,paid_at=now() WHERE payment_id=$2',
                        [$successfulId,$pending['payment_id']]
                    );
                    pg_rows(
                        'INSERT INTO voucher_transactions(voucher_id,payment_id,amount_change,description)
                         VALUES ($1,$2,$3,$4)',
                        [$pending['voucher_id'],$pending['payment_id'],-$amount,'Оплата заказа №' . $orderId]
                    );
                    return $orderId;
                }
                pg_rows(
                    'INSERT INTO payments(order_id,payment_method_id,payment_status_id,paid_at,amount)
                     VALUES ($1,$2,$3,now(),$4)',
                    [$orderId,$methodId,$successfulId,$amount]
                );
                return $orderId;

            case 'warranty_add':
                pg_admin();
                $order = pg_order($orderId);
                pg_required($values['reason'] ?? '', 'Укажите причину гарантийного обращения');
                $previousWorkId = (int) ($values['work_id'] ?? 0);
                $previous = pg_row(
                    'SELECT source_order.device_id, source_order.issued_at,
                            ow.performed_at, wt.warranty_days
                     FROM order_works ow
                     JOIN order_assignments a ON a.assignment_id = ow.assignment_id
                     JOIN repair_orders source_order ON source_order.order_id = a.order_id
                     JOIN work_tariffs wt ON wt.tariff_id = ow.tariff_id
                     WHERE ow.order_work_id = $1',
                    [$previousWorkId]
                );
                if (!$previous || (int) $previous['device_id'] !== (int) $order['device_id']
                    || !$previous['issued_at'] || !$previous['performed_at']
                    || (int) $previous['warranty_days'] <= 0
                    || strtotime($previous['performed_at'] . ' +' . $previous['warranty_days'] . ' days') < time()) {
                    throw new InvalidArgumentException('Эта работа не подходит для гарантийного обращения');
                }
                if (pg_value(
                    'SELECT 1 FROM warranty_claims WHERE original_order_work_id=$1 AND repair_order_id=$2',
                    [$previousWorkId, $orderId]
                )) {
                    throw new InvalidArgumentException('По этой работе гарантия уже оформлена');
                }
                $statusId = pg_value('SELECT warranty_status_id FROM warranty_statuses ORDER BY warranty_status_id LIMIT 1');
                if (!$statusId) {
                    throw new InvalidArgumentException('Нет статусов гарантийных обращений');
                }
                pg_rows(
                    'INSERT INTO warranty_claims(original_order_work_id,repair_order_id,warranty_status_id,reason)
                     VALUES ($1,$2,$3,$4)',
                    [$previousWorkId,$orderId,$statusId,$values['reason']]
                );
                return $orderId;

            case 'review_add':
                if ($user['role'] !== 'client') {
                    throw new InvalidArgumentException('Отзывы могут оставлять только клиенты');
                }
                $workId = (int) ($values['work_id'] ?? 0);
                $order = pg_row(
                    'SELECT o.client_id,o.issued_at FROM order_works ow
                     JOIN order_assignments a ON a.assignment_id=ow.assignment_id
                     JOIN repair_orders o ON o.order_id=a.order_id WHERE ow.order_work_id=$1',[$workId]
                );
                if (!$order || (int)$order['client_id'] !== (int)$user['id'] || !$order['issued_at']) {
                    throw new InvalidArgumentException('Отзыв доступен после выдачи вашего ремонта');
                }
                $rating = (int) ($values['rating'] ?? 0);
                if ($rating < 1 || $rating > 5) {
                    throw new InvalidArgumentException('Выберите оценку от 1 до 5');
                }
                $accountId = pg_value('SELECT account_id FROM user_accounts WHERE client_id=$1',[$user['id']]);
                pg_rows(
                    'INSERT INTO reviews(order_work_id,account_id,rating,comment)
                     VALUES ($1,$2,$3,$4)',[$workId,$accountId,$rating,$values['comment'] ?? null]
                );
                return $workId;
        }
        throw new InvalidArgumentException('Неизвестное действие');
    });
}
