<?php
// файл с данными находится вне public
function demo_file()
{
    return dirname(__DIR__) . '/var/demo.json';
}
function demo_seed()
{
    return [
        'clients'=>[
            ['id'=>1,'last_name'=>'Иванов','first_name'=>'Иван','middle_name'=>'Петрович','phone'=>'+79991234567','email'=>'ivan@example.test'],
            ['id'=>2,'last_name'=>'Петров','first_name'=>'Петр','middle_name'=>'Сергеевич','phone'=>'+79997654321','email'=>'maksim@example.test'],
            ['id'=>3,'last_name'=>'Сидорова','first_name'=>'Анна','middle_name'=>'Ивановна','phone'=>'+79992345678','email'=>'maria@example.test'],
            ['id'=>4,'last_name'=>'Смирнов','first_name'=>'Николай','middle_name'=>'Андреевич','phone'=>'+79998765432','email'=>'mikhail@example.test'],
        ],
        'models'=>[
            ['id'=>1,'type'=>'Смартфон','brand'=>'Samsung','name'=>'Galaxy S23'],
            ['id'=>2,'type'=>'Ноутбук','brand'=>'ASUS','name'=>'VivoBook 15'],
            ['id'=>3,'type'=>'Смартфон','brand'=>'Apple','name'=>'iPhone 13'],
            ['id'=>4,'type'=>'Ноутбук','brand'=>'Lenovo','name'=>'IdeaPad 3'],
            ['id'=>5,'type'=>'Планшет','brand'=>'Xiaomi','name'=>'Pad 6'],
        ],
        'devices'=>[
            ['id'=>1,'model_id'=>1,'serial'=>'SM-S23-20516','release_date'=>'2023-02-01'],
            ['id'=>2,'model_id'=>2,'serial'=>'AS-5803-98112','release_date'=>'2022-08-01'],
            ['id'=>3,'model_id'=>3,'serial'=>'IP-13-51732','release_date'=>'2021-11-01'],
            ['id'=>4,'model_id'=>4,'serial'=>'LN-00201837','release_date'=>'2023-01-01'],
            ['id'=>5,'model_id'=>5,'serial'=>'XM-P6-08074','release_date'=>'2023-09-01'],
        ],
        'ownership'=>[
            ['id'=>1,'device_id'=>1,'client_id'=>1,'start_date'=>'2024-01-10','end_date'=>null],
            ['id'=>2,'device_id'=>2,'client_id'=>2,'start_date'=>'2023-04-10','end_date'=>null],
            ['id'=>3,'device_id'=>3,'client_id'=>3,'start_date'=>'2022-01-22','end_date'=>null],
            ['id'=>4,'device_id'=>4,'client_id'=>4,'start_date'=>'2023-08-12','end_date'=>null],
            ['id'=>5,'device_id'=>5,'client_id'=>1,'start_date'=>'2024-07-03','end_date'=>null],
        ],
        'staff'=>[
            ['id'=>1,'last_name'=>'Петров','first_name'=>'Петр','middle_name'=>'Николаевич','phone'=>'+79990001122','role'=>'manager','active'=>true],
            ['id'=>2,'last_name'=>'Иванов','first_name'=>'Иван','middle_name'=>'Сергеевич','phone'=>'+79990002233','role'=>'reception','active'=>true],
            ['id'=>3,'last_name'=>'Сидоров','first_name'=>'Алексей','middle_name'=>'Петрович','phone'=>'+79990003344','role'=>'master','active'=>true],
            ['id'=>4,'last_name'=>'Смирнов','first_name'=>'Дмитрий','middle_name'=>'Иванович','phone'=>'+79990004455','role'=>'master','active'=>true],
            ['id'=>5,'last_name'=>'Васильев','first_name'=>'Сергей','middle_name'=>'Иванович','phone'=>'+79990005566','role'=>'master','active'=>true],
        ],
        'accounts'=>[
            ['id'=>1,'staff_id'=>1,'client_id'=>null,'login'=>'admin','password_hash'=>password_hash('spektr123',PASSWORD_DEFAULT),'role'=>'manager','active'=>true],
            ['id'=>3,'staff_id'=>3,'client_id'=>null,'login'=>'master','password_hash'=>password_hash('spektr123',PASSWORD_DEFAULT),'role'=>'master','active'=>true],
            ['id'=>4,'staff_id'=>4,'client_id'=>null,'login'=>'master2','password_hash'=>password_hash('spektr123',PASSWORD_DEFAULT),'role'=>'master','active'=>true],
            ['id'=>7,'staff_id'=>5,'client_id'=>null,'login'=>'master3','password_hash'=>password_hash('spektr123',PASSWORD_DEFAULT),'role'=>'master','active'=>true],
            ['id'=>5,'staff_id'=>null,'client_id'=>1,'login'=>'ivan','password_hash'=>password_hash('client123',PASSWORD_DEFAULT),'role'=>'client','active'=>true],
            ['id'=>6,'staff_id'=>null,'client_id'=>2,'login'=>'maksim','password_hash'=>password_hash('client123',PASSWORD_DEFAULT),'role'=>'client','active'=>true],
        ],
        'specializations'=>[
            ['id'=>1,'staff_id'=>3,'name'=>'Ремонт смартфонов','level'=>'Высокий','experience_from'=>'2021-03-01'],
            ['id'=>2,'staff_id'=>4,'name'=>'Ремонт ноутбуков','level'=>'Средний','experience_from'=>'2022-05-15'],
            ['id'=>3,'staff_id'=>3,'name'=>'Пайка компонентов','level'=>'Средний','experience_from'=>'2022-08-01'],
            ['id'=>4,'staff_id'=>3,'name'=>'Ремонт планшетов','level'=>'Средний','experience_from'=>'2023-05-01'],
            ['id'=>5,'staff_id'=>4,'name'=>'Ремонт компьютеров','level'=>'Высокий','experience_from'=>'2022-05-15'],
            ['id'=>6,'staff_id'=>5,'name'=>'Ремонт принтеров','level'=>'Высокий','experience_from'=>'2020-04-01'],
            ['id'=>7,'staff_id'=>5,'name'=>'Ремонт мониторов','level'=>'Высокий','experience_from'=>'2020-04-01'],
            ['id'=>8,'staff_id'=>5,'name'=>'Ремонт приставок','level'=>'Средний','experience_from'=>'2021-04-01'],
            ['id'=>9,'staff_id'=>5,'name'=>'Ремонт телевизоров','level'=>'Высокий','experience_from'=>'2020-04-01'],
            ['id'=>10,'staff_id'=>5,'name'=>'Ремонт прочей электроники','level'=>'Средний','experience_from'=>'2020-04-01'],
        ],
        'statuses'=>['Заявка','Принят','Передан мастеру','Диагностика','Ожидает согласования','В ремонте','Готов к выдаче','Выдан','Отказано','Отменен'],
        'orders'=>[
            ['id'=>1256,'ownership_id'=>1,'service_id'=>2,'status'=>'В ремонте','created_at'=>'2026-11-17 11:20:00','problem'=>'Не заряжается после падения','condition'=>'Небольшие потертости корпуса','priority'=>2,'planned_date'=>'2026-11-24','issued_at'=>null],
            ['id'=>1255,'ownership_id'=>2,'service_id'=>4,'status'=>'Диагностика','created_at'=>'2026-11-18 10:10:00','problem'=>'Сильный шум при работе','condition'=>'Без видимых повреждений','priority'=>1,'planned_date'=>'2026-11-25','issued_at'=>null],
            ['id'=>1254,'ownership_id'=>3,'service_id'=>3,'status'=>'Готов к выдаче','created_at'=>'2026-11-14 16:45:00','problem'=>'Разбит дисплей','condition'=>'Трещины на экране','priority'=>2,'planned_date'=>'2026-11-20','issued_at'=>null],
            ['id'=>1253,'ownership_id'=>4,'service_id'=>1,'status'=>'Ожидает согласования','created_at'=>'2026-11-16 13:25:00','problem'=>'Не включается','condition'=>'Царапины на крышке','priority'=>3,'planned_date'=>'2026-11-26','issued_at'=>null],
            ['id'=>1252,'ownership_id'=>5,'service_id'=>5,'status'=>'Выдан','created_at'=>'2026-11-10 09:10:00','problem'=>'Не работает динамик','condition'=>'Хорошее','priority'=>1,'planned_date'=>'2026-11-13','issued_at'=>'2026-11-13 17:30:00'],
            ['id'=>1257,'ownership_id'=>1,'service_id'=>6,'status'=>'Заявка','created_at'=>'2026-11-21 12:40:00','problem'=>'Быстро разряжается батарея','condition'=>'','priority'=>1,'planned_date'=>null,'issued_at'=>null,'reject_reason'=>null],
        ],
        'history'=>[
            ['id'=>1,'order_id'=>1256,'status'=>'Принят','changed_at'=>'2026-11-17 11:20:00','staff_id'=>2,'comment'=>'Техника принята'],
            ['id'=>2,'order_id'=>1256,'status'=>'Диагностика','changed_at'=>'2026-11-17 15:15:00','staff_id'=>3,'comment'=>'Проверка питания'],
            ['id'=>3,'order_id'=>1256,'status'=>'В ремонте','changed_at'=>'2026-11-19 10:20:00','staff_id'=>3,'comment'=>'Согласован ремонт разъема'],
            ['id'=>4,'order_id'=>1255,'status'=>'Принят','changed_at'=>'2026-11-18 10:10:00','staff_id'=>2,'comment'=>'Техника принята'],
            ['id'=>5,'order_id'=>1255,'status'=>'Диагностика','changed_at'=>'2026-11-19 12:15:00','staff_id'=>4,'comment'=>'Проверка охлаждения'],
            ['id'=>6,'order_id'=>1254,'status'=>'Принят','changed_at'=>'2026-11-14 16:45:00','staff_id'=>2,'comment'=>'Техника принята'],
            ['id'=>7,'order_id'=>1254,'status'=>'В ремонте','changed_at'=>'2026-11-16 09:15:00','staff_id'=>3,'comment'=>'Замена дисплея'],
            ['id'=>8,'order_id'=>1254,'status'=>'Готов к выдаче','changed_at'=>'2026-11-19 19:15:00','staff_id'=>3,'comment'=>'Устройство проверено'],
            ['id'=>9,'order_id'=>1253,'status'=>'Принят','changed_at'=>'2026-11-16 13:25:00','staff_id'=>2,'comment'=>'Техника принята'],
            ['id'=>10,'order_id'=>1253,'status'=>'Диагностика','changed_at'=>'2026-11-17 11:15:00','staff_id'=>4,'comment'=>'Требуются запчасти'],
            ['id'=>11,'order_id'=>1253,'status'=>'Ожидает согласования','changed_at'=>'2026-11-19 10:00:00','staff_id'=>2,'comment'=>'Ожидание решения клиента'],
            ['id'=>12,'order_id'=>1252,'status'=>'Принят','changed_at'=>'2026-11-10 09:10:00','staff_id'=>2,'comment'=>'Техника принята'],
            ['id'=>13,'order_id'=>1252,'status'=>'В ремонте','changed_at'=>'2026-11-11 08:40:00','staff_id'=>3,'comment'=>'Ремонт'],
            ['id'=>14,'order_id'=>1252,'status'=>'Готов к выдаче','changed_at'=>'2026-11-12 17:00:00','staff_id'=>3,'comment'=>'Проверка пройдена'],
            ['id'=>15,'order_id'=>1252,'status'=>'Выдан','changed_at'=>'2026-11-13 17:30:00','staff_id'=>2,'comment'=>'Выдан клиенту'],
            ['id'=>16,'order_id'=>1257,'status'=>'Заявка','changed_at'=>'2026-11-21 12:40:00','staff_id'=>null,'comment'=>'Заявка отправлена клиентом'],
        ],
        'assignments'=>[
            ['id'=>1,'order_id'=>1256,'master_id'=>3,'assigned_at'=>'2026-11-17 14:00:00','started_at'=>'2026-11-19 10:20:00','completed_at'=>null],
            ['id'=>2,'order_id'=>1255,'master_id'=>4,'assigned_at'=>'2026-11-18 14:00:00','started_at'=>null,'completed_at'=>null],
            ['id'=>3,'order_id'=>1254,'master_id'=>3,'assigned_at'=>'2026-11-15 10:00:00','started_at'=>'2026-11-16 09:15:00','completed_at'=>'2026-11-19 19:15:00'],
            ['id'=>4,'order_id'=>1253,'master_id'=>4,'assigned_at'=>'2026-11-17 09:00:00','started_at'=>null,'completed_at'=>null],
            ['id'=>5,'order_id'=>1252,'master_id'=>3,'assigned_at'=>'2026-11-10 13:00:00','started_at'=>'2026-11-11 08:40:00','completed_at'=>'2026-11-12 17:00:00'],
        ],
        'tariffs'=>[
            ['id'=>1,'name'=>'Диагностика','price'=>500,'warranty_days'=>0,'from_date'=>'2026-01-01','categories'=>'Смартфон,Ноутбук,Планшет,Компьютер,Принтер,Монитор,Игровая приставка,Телевизор,Другая техника'],
            ['id'=>2,'name'=>'Замена разъема питания','price'=>1200,'warranty_days'=>90,'from_date'=>'2026-01-01','categories'=>'Смартфон,Планшет'],
            ['id'=>3,'name'=>'Замена дисплея','price'=>2900,'warranty_days'=>120,'from_date'=>'2026-01-01','categories'=>'Смартфон,Планшет'],
            ['id'=>4,'name'=>'Очистка системы охлаждения','price'=>1600,'warranty_days'=>30,'from_date'=>'2026-01-01','categories'=>'Ноутбук'],
            ['id'=>5,'name'=>'Замена динамика','price'=>1300,'warranty_days'=>90,'from_date'=>'2026-01-01','categories'=>'Смартфон,Планшет'],
            ['id'=>6,'name'=>'Замена аккумулятора','price'=>1000,'warranty_days'=>90,'from_date'=>'2026-01-01','categories'=>'Смартфон,Планшет'],
            ['id'=>7,'name'=>'Настройка ПО','price'=>900,'warranty_days'=>30,'from_date'=>'2026-01-01','categories'=>'Смартфон,Ноутбук,Планшет,Компьютер'],
            ['id'=>8,'name'=>'Ремонт электронной техники','price'=>1500,'warranty_days'=>90,'from_date'=>'2026-01-01','categories'=>'Компьютер,Принтер,Монитор,Игровая приставка,Телевизор,Другая техника'],
        ],
        'works'=>[
            ['id'=>1,'order_id'=>1256,'assignment_id'=>1,'tariff_id'=>1,'name'=>'Диагностика','price'=>500,'done_at'=>'2026-11-17 15:15:00'],
            ['id'=>2,'order_id'=>1254,'assignment_id'=>3,'tariff_id'=>3,'name'=>'Замена дисплея','price'=>2900,'done_at'=>'2026-11-19 17:20:00'],
            ['id'=>3,'order_id'=>1252,'assignment_id'=>5,'tariff_id'=>5,'name'=>'Замена динамика','price'=>1300,'done_at'=>'2026-11-12 15:00:00'],
        ],
        'parts'=>[
            ['id'=>1,'article'=>'CON-USB-C-01','name'=>'Разъем USB-C','stock'=>12],
            ['id'=>2,'article'=>'OLED-IP13-02','name'=>'Дисплей iPhone 13','stock'=>4],
            ['id'=>3,'article'=>'FAN-ASUS-14','name'=>'Вентилятор ASUS','stock'=>6],
            ['id'=>4,'article'=>'SPK-XM-26','name'=>'Динамик планшета','stock'=>8],
        ],
        'order_parts'=>[
            ['id'=>1,'order_id'=>1254,'part_id'=>2,'quantity'=>1,'price'=>4300],
            ['id'=>2,'order_id'=>1252,'part_id'=>4,'quantity'=>1,'price'=>800],
        ],
        'discounts'=>[],
        'movements'=>[],
        'payments'=>[
            ['id'=>1,'order_id'=>1252,'amount'=>2100,'method'=>'Карта','status'=>'Проведен','paid_at'=>'2026-11-13 17:25:00'],
        ],
        'warranties'=>[],
        'reviews'=>[],
        'benefits'=>[
            ['id'=>1,'client_id'=>1,'name'=>'Студенческая скидка','percent'=>10,'active'=>true],
            ['id'=>2,'client_id'=>2,'name'=>'Льгота','percent'=>7,'active'=>true],
        ],
        'vouchers'=>[
            ['id'=>1,'code'=>'GIFT500','client_id'=>1,'amount'=>500,'used'=>false],
            ['id'=>2,'code'=>'BONUS300','client_id'=>2,'amount'=>300,'used'=>false],
        ],
        'promo_codes'=>[
            ['code'=>'WELCOME5','percent'=>5,'active'=>true],
            ['code'=>'REPAIR10','percent'=>10,'active'=>true],
        ],
    ];
}
function demo_initial_data()
{
    $path = demo_file();
    if (!is_file($path)) {
        $data = json_encode(demo_seed(), JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_INVALID_UTF8_SUBSTITUTE);
        @file_put_contents($path, $data, LOCK_EX);
    }
}
// логины хранятся отдельно от клиентов и сотрудников
function repo_login($login, $password)
{
    $data = repo_data();
    foreach ($data['accounts'] as $account) {
        if ($account['login'] !== $login || !$account['active']) {
            continue;
        }
        if (!password_verify($password, $account['password_hash'])) {
            return null;
        }
        $table = $account['role'] === 'client' ? 'clients' : 'staff';
        $id = $account['role'] === 'client' ? $account['client_id'] : $account['staff_id'];
        $person = demo_get($data[$table], $id);
        if (!$person || (isset($person['active']) && !$person['active'])) {
            return null;
        }
        return [
            'id' => $person['id'],
            'full_name' => name_view($person),
            'role' => $account['role'],
        ];
    }
    return null;
}

function repo_data()
{
    demo_initial_data();
    $stream=@fopen(demo_file(),'r');
    if (!$stream) throw new RuntimeException('Не удалось открыть демонстрационные данные');
    flock($stream,LOCK_SH);
    $contents=stream_get_contents($stream);
    flock($stream,LOCK_UN);
    fclose($stream);
    $data = json_decode($contents ?: '', true);
    if (!is_array($data)) throw new RuntimeException('Не удалось прочитать демонстрационную базу');
    return $data;
}
function demo_next_id($rows, $start = 1)
{
    foreach ($rows as $row) $start = max($start, (int) $row['id'] + 1);
    return $start;
}
function demo_get($rows, $id)
{
    foreach ($rows as $row) if ((int) $row['id'] === (int) $id) return $row;
    return null;
}
function demo_required($value, $message = 'Заполните обязательные поля')
{
    if (trim((string) $value) === '') throw new InvalidArgumentException($message);
}
function demo_staff()
{
    if (empty($_SESSION['staff'])) throw new InvalidArgumentException('Сначала войдите в систему');
    return $_SESSION['staff'];
}
function demo_can_manage()
{
    if (demo_staff()['role'] !== 'manager') throw new InvalidArgumentException('Недостаточно прав для этого действия');
}
function demo_amount($value)
{
    if (!is_numeric($value) || (float) $value <= 0 || (float) $value > 9999999) throw new InvalidArgumentException('Введите корректную сумму');
    return round((float) $value, 2);
}
function demo_order($data, $id)
{
    $order=demo_get($data['orders'], $id);
    if (!$order) throw new InvalidArgumentException('Заказ не найден');
    return $order;
}
function demo_total($data, $orderId)
{
    $total = 0;
    foreach ($data['works'] as $item) {
        if ($item['order_id'] == $orderId) {
            $total += $item['price'];
        }
    }
    foreach ($data['order_parts'] as $item) {
        if ($item['order_id'] == $orderId) {
            $total += $item['price'] * $item['quantity'];
        }
    }
    foreach ($data['discounts'] as $item) {
        if ($item['order_id'] == $orderId) {
            $total -= $item['amount'];
        }
    }
    return max(0, $total);
}

function demo_paid($data, $orderId)
{
    $paid = 0;
    foreach ($data['payments'] as $payment) {
        if ($payment['order_id'] == $orderId && $payment['status'] === 'Проведен') {
            $paid += $payment['amount'];
        }
    }
    return $paid;
}

function demo_modify(&$data, $action, $values)
{
    $staff = $action === 'register' ? null : demo_staff();
    $orderId = (int) ($values['id'] ?? 0);
    $now = date('Y-m-d H:i:s');

    switch ($action) {
        case 'register':
            foreach (['last_name', 'first_name', 'phone', 'login', 'password'] as $field) {
                demo_required($values[$field] ?? '', 'Заполните обязательные поля');
            }
            if (!preg_match('/^\+?[0-9() \-]{10,20}$/', $values['phone'])) {
                throw new InvalidArgumentException('Проверьте телефон');
            }
            if (!empty($values['email']) && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Проверьте адрес почты');
            }
            if (strlen($values['password']) < 8 || strlen($values['password']) > 128) {
                throw new InvalidArgumentException('Длина пароля должна быть от 8 до 128 символов');
            }
            foreach ($data['accounts'] as $account) {
                if (strcasecmp($account['login'], $values['login']) === 0) {
                    throw new InvalidArgumentException('Такой логин уже занят');
                }
            }
            $clientId = demo_next_id($data['clients']);
            $data['clients'][] = [
                'id' => $clientId,
                'last_name' => $values['last_name'],
                'first_name' => $values['first_name'],
                'middle_name' => $values['middle_name'] ?? '',
                'phone' => $values['phone'],
                'email' => $values['email'] ?? '',
            ];
            $data['accounts'][] = [
                'id' => demo_next_id($data['accounts']),
                'staff_id' => null,
                'client_id' => $clientId,
                'login' => $values['login'],
                'password_hash' => password_hash($values['password'], PASSWORD_DEFAULT),
                'role' => 'client',
                'active' => true,
            ];
            return $clientId;

        case 'request_add':
            if ($staff['role'] !== 'client') {
                throw new InvalidArgumentException('Создать заявку можно из кабинета клиента');
            }
            if (empty($values['confirmed'])) {
                throw new InvalidArgumentException('Подтвердите правильность модели и серийного номера');
            }
            foreach (['type', 'brand', 'model', 'serial', 'problem'] as $field) {
                demo_required($values[$field] ?? '');
            }
            $allowedTypes = [
                'Смартфон', 'Ноутбук', 'Планшет', 'Компьютер',
                'Принтер', 'Монитор', 'Игровая приставка',
                'Телевизор', 'Другая техника',
            ];
            if (!in_array($values['type'], $allowedTypes, true)) {
                throw new InvalidArgumentException('Выберите вид техники из списка');
            }
            $typeNote = trim((string) ($values['type_other'] ?? ''));
            if ($values['type'] === 'Другая техника' && !preg_match('/^.{3,}$/us', $typeNote)) {
                throw new InvalidArgumentException('Укажите вид техники');
            }
            $service = demo_get($data['tariffs'], (int) ($values['service_id'] ?? 0));
            if (!$service) {
                throw new InvalidArgumentException('Выберите услугу');
            }
            $types = explode(',', $service['categories'] ?? '');
            if (!in_array($values['type'], $types, true)) {
                throw new InvalidArgumentException('Услуга не подходит для этого вида техники');
            }
            $device = null;
            foreach ($data['devices'] as $item) {
                if (strcasecmp($item['serial'], $values['serial']) === 0) {
                    $device = $item;
                }
            }
            if ($device) {
                $ownership = null;
                foreach ($data['ownership'] as $item) {
                    if ($item['device_id'] === $device['id'] && empty($item['end_date'])) {
                        $ownership = $item;
                    }
                }
                if (!$ownership || $ownership['client_id'] !== $staff['id']) {
                    throw new InvalidArgumentException('Устройство с таким номером зарегистрировано на другого клиента');
                }
            } else {
                $model = null;
                foreach ($data['models'] as $item) {
                    if ($item['type'] === $values['type'] &&
                        strcasecmp($item['brand'], $values['brand']) === 0 &&
                        strcasecmp($item['name'], $values['model']) === 0) {
                        $model = $item;
                        break;
                    }
                }
                if (!$model) {
                    $model = [
                        'id' => demo_next_id($data['models']),
                        'type' => $values['type'],
                        'brand' => $values['brand'],
                        'name' => $values['model'],
                    ];
                    $data['models'][] = $model;
                }
                $device = [
                    'id' => demo_next_id($data['devices']),
                    'model_id' => $model['id'],
                    'serial' => $values['serial'],
                    'release_date' => null,
                ];
                $data['devices'][] = $device;
                $ownership = [
                    'id' => demo_next_id($data['ownership']),
                    'device_id' => $device['id'],
                    'client_id' => $staff['id'],
                    'start_date' => date('Y-m-d'),
                    'end_date' => null,
                ];
                $data['ownership'][] = $ownership;
            }
            $preferredMasterId = (int) ($values['preferred_master_id'] ?? 0);
            if ($preferredMasterId) {
                $preferredMaster = demo_get($data['staff'], $preferredMasterId);
                $specialty = app_specialty_for_type($values['type']);
                $canRepair = false;
                foreach ($data['specializations'] as $item) {
                    if ($item['staff_id'] === $preferredMasterId && $item['name'] === $specialty) {
                        $canRepair = true;
                        break;
                    }
                }
                if (!$preferredMaster || $preferredMaster['role'] !== 'master' ||
                    !$preferredMaster['active'] || !$canRepair) {
                    throw new InvalidArgumentException('Выберите мастера, который работает с этим видом техники');
                }
            }

            $newId = demo_next_id($data['orders'], 1257);
            $data['orders'][] = [
                'id' => $newId,
                'ownership_id' => $ownership['id'],
                'service_id' => $service['id'],
                'preferred_master_id' => $preferredMasterId ?: null,
                'device_type_note' => $values['type'] === 'Другая техника' ? $typeNote : null,
                'status' => 'Заявка',
                'created_at' => $now,
                'problem' => $values['problem'],
                'condition' => '',
                'priority' => 1,
                'planned_date' => null,
                'issued_at' => null,
                'reject_reason' => null,
            ];
            $data['history'][] = [
                'id' => demo_next_id($data['history']),
                'order_id' => $newId,
                'status' => 'Заявка',
                'changed_at' => $now,
                'staff_id' => null,
                'comment' => 'Заявка отправлена клиентом',
            ];
            return $newId;

        case 'request_decide':
            demo_can_manage();
            $order = demo_order($data, $orderId);
            if ($order['status'] !== 'Заявка') {
                throw new InvalidArgumentException('Заявка уже рассмотрена');
            }
            $approve = ($values['decision'] ?? '') === 'accept';
            if (!$approve) {
                demo_required($values['reason'] ?? '', 'Укажите причину отказа');
            }
            $status = $approve ? 'Принят' : 'Отказано';
            foreach ($data['orders'] as &$item) {
                if ($item['id'] === $orderId) {
                    $item['status'] = $status;
                    $item['reject_reason'] = $approve ? null : $values['reason'];
                    break;
                }
            }
            unset($item);
            $data['history'][] = [
                'id' => demo_next_id($data['history']),
                'order_id' => $orderId,
                'status' => $status,
                'changed_at' => $now,
                'staff_id' => $staff['id'],
                'comment' => $approve ? 'Заявка принята' : $values['reason'],
            ];
            return $orderId;

        case 'review_add':
            if ($staff['role'] !== 'client') {
                throw new InvalidArgumentException('Отзывы оставляют клиенты');
            }
            $work = demo_get($data['works'], (int) ($values['work_id'] ?? 0));
            $order = $work ? demo_order($data, $work['order_id']) : null;
            $ownership = $order ? demo_get($data['ownership'], $order['ownership_id']) : null;
            if (!$order || !$ownership || $ownership['client_id'] !== $staff['id'] || $order['status'] !== 'Выдан') {
                throw new InvalidArgumentException('Отзыв можно оставить после выдачи ремонта');
            }
            foreach ($data['reviews'] as $review) {
                if ($review['work_id'] === $work['id']) {
                    throw new InvalidArgumentException('Отзыв на эту работу уже есть');
                }
            }
            $rating = (int) ($values['rating'] ?? 0);
            if ($rating < 1 || $rating > 5) {
                throw new InvalidArgumentException('Поставьте оценку от 1 до 5');
            }
            $data['reviews'][] = [
                'id' => demo_next_id($data['reviews']),
                'work_id' => $work['id'],
                'rating' => $rating,
                'comment' => trim($values['comment'] ?? ''),
                'created_at' => $now,
            ];
            return $order['id'];

        case 'client_discount':
            if ($staff['role'] !== 'client') {
                throw new InvalidArgumentException('Недостаточно прав');
            }
            $order = demo_order($data, $orderId);
            $ownership = demo_get($data['ownership'], $order['ownership_id']);
            if (!$ownership || $ownership['client_id'] !== $staff['id'] || $order['status'] !== 'Готов к выдаче') {
                throw new InvalidArgumentException('Скидки доступны после завершения ремонта');
            }
            $kind = $values['kind'] ?? '';
            $code = strtoupper(trim($values['code'] ?? ''));
            $label = '';
            $amount = 0;
            $alreadyUsed = array_filter($data['discounts'], function ($item) use ($orderId, $kind) {
                return $item['order_id'] == $orderId && ($item['kind'] ?? '') === $kind;
            });
            if ($alreadyUsed) {
                throw new InvalidArgumentException('Этот вид скидки уже применен к заказу');
            }
            $subtotal = demo_total($data, $orderId);
            if ($subtotal <= 0) {
                throw new InvalidArgumentException('По заказу еще нет начислений');
            }
            if ($kind === 'benefit') {
                $benefit = null;
                foreach ($data['benefits'] as $item) {
                    if ($item['client_id'] === $staff['id'] && $item['active']) {
                        $benefit = $item;
                        break;
                    }
                }
                if (!$benefit) {
                    throw new InvalidArgumentException('У вас пока нет активных льгот');
                }
                $label = $benefit['name'];
                $amount = round($subtotal * $benefit['percent'] / 100, 2);
            } elseif ($kind === 'voucher') {
                $voucher = null;
                foreach ($data['vouchers'] as &$item) {
                    if ($item['client_id'] === $staff['id'] && !$item['used'] && $item['code'] === $code) {
                        $voucher = &$item;
                        break;
                    }
                }
                unset($item);
                if (!$voucher) {
                    throw new InvalidArgumentException('Ваучер не найден или уже использован');
                }
                $label = 'Ваучер ' . $code;
                $amount = $voucher['amount'];
            } elseif ($kind === 'promo') {
                $promo = null;
                foreach ($data['promo_codes'] as $item) {
                    if ($item['active'] && $item['code'] === $code) {
                        $promo = $item;
                        break;
                    }
                }
                if (!$promo) {
                    throw new InvalidArgumentException('Промокод не найден');
                }
                $label = 'Промокод ' . $code;
                $amount = round($subtotal * $promo['percent'] / 100, 2);
            } else {
                throw new InvalidArgumentException('Неизвестный вид скидки');
            }
            if (demo_paid($data, $orderId) > 0) {
                throw new InvalidArgumentException('После оплаты скидки не применяются');
            }
            $rest = demo_total($data, $orderId);
            if ($rest <= 0) {
                throw new InvalidArgumentException('Стоимость заказа уже покрыта скидками');
            }
            $amount = min($rest, $amount);
            if ($kind === 'voucher') {
                foreach ($data['vouchers'] as &$item) {
                    if ($item['client_id'] === $staff['id'] && $item['code'] === $code) {
                        $item['used'] = true;
                        break;
                    }
                }
                unset($item);
            }
            $data['discounts'][] = [
                'id' => demo_next_id($data['discounts']),
                'order_id' => $orderId,
                'name' => $label,
                'amount' => $amount,
                'kind' => $kind,
                'applied_at' => $now,
            ];
            return $orderId;

        case 'client_add':
            demo_can_manage();
            demo_required($values['last_name']);
            demo_required($values['first_name']);
            demo_required($values['phone']);

            if (!preg_match('/^\+?[0-9() \-]{10,20}$/', $values['phone'])) {
                throw new InvalidArgumentException('Проверьте номер телефона');
            }
            if ($values['email'] && !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
                throw new InvalidArgumentException('Проверьте электронную почту');
            }

            $clientId = demo_next_id($data['clients']);
            $data['clients'][] = [
                'id' => $clientId,
                'last_name' => $values['last_name'],
                'first_name' => $values['first_name'],
                'middle_name' => $values['middle_name'],
                'phone' => $values['phone'],
                'email' => $values['email'],
            ];
            return $clientId;

        case 'device_add':
            demo_can_manage();
            $client = demo_get($data['clients'], (int) $values['client_id']);
            if (!$client) {
                throw new InvalidArgumentException('Выберите владельца');
            }
            demo_required($values['type']);
            demo_required($values['brand']);
            demo_required($values['model']);
            demo_required($values['serial'], 'Введите серийный номер');

            foreach ($data['devices'] as $device) {
                if ($device['serial'] === $values['serial']) {
                    throw new InvalidArgumentException('Этот серийный номер уже есть в системе');
                }
            }

            $model = null;
            foreach ($data['models'] as $item) {
                $modelName = strtolower($item['brand'] . '|' . $item['name']);
                $enteredName = strtolower($values['brand'] . '|' . $values['model']);
                if ($modelName === $enteredName) {
                    $model = $item;
                    break;
                }
            }

            if (!$model) {
                $model = [
                    'id' => demo_next_id($data['models']),
                    'type' => $values['type'],
                    'brand' => $values['brand'],
                    'name' => $values['model'],
                ];
                $data['models'][] = $model;
            }

            $deviceId = demo_next_id($data['devices']);
            $data['devices'][] = [
                'id' => $deviceId,
                'model_id' => $model['id'],
                'serial' => $values['serial'],
                'release_date' => null,
            ];
            $data['ownership'][] = [
                'id' => demo_next_id($data['ownership']),
                'device_id' => $deviceId,
                'client_id' => $client['id'],
                'start_date' => date('Y-m-d'),
                'end_date' => null,
            ];
            return $deviceId;

        case 'order_add':
            demo_can_manage();
            $ownership = demo_get($data['ownership'], (int) $values['ownership_id']);
            if (!$ownership || $ownership['end_date']) {
                throw new InvalidArgumentException('Выберите устройство действующего владельца');
            }
            demo_required($values['problem'], 'Опишите неисправность');
            if (!empty($values['planned_date']) &&
                !preg_match('/^\d{4}-\d{2}-\d{2}$/', $values['planned_date'])) {
                throw new InvalidArgumentException('Неверная плановая дата');
            }

            $orderId = demo_next_id($data['orders'], 1257);
            $data['orders'][] = [
                'id' => $orderId,
                'ownership_id' => $ownership['id'],
                'status' => 'Принят',
                'created_at' => $now,
                'problem' => $values['problem'],
                'condition' => $values['condition'],
                'priority' => max(1, min(3, (int) $values['priority'])),
                'planned_date' => $values['planned_date'] ?: null,
                'issued_at' => null,
            ];
            $data['history'][] = [
                'id' => demo_next_id($data['history']),
                'order_id' => $orderId,
                'status' => 'Принят',
                'changed_at' => $now,
                'staff_id' => $staff['id'],
                'comment' => 'Заказ оформлен',
            ];
            return $orderId;

        case 'assign':
            demo_can_manage();
            $order = demo_order($data, $orderId);
            if (in_array($order['status'], ['Заявка', 'Отказано', 'Выдан', 'Отменен'], true)) {
                throw new InvalidArgumentException('Завершенный заказ не назначают');
            }
            $master = demo_get($data['staff'], (int) $values['master_id']);
            if (!empty($order['preferred_master_id']) &&
                (int) $order['preferred_master_id'] !== (int) $values['master_id']) {
                throw new InvalidArgumentException('Клиент выбрал другого мастера. Согласуйте изменение с клиентом');
            }
            if (!$master || $master['role'] !== 'master' || !$master['active']) {
                throw new InvalidArgumentException('Выберите действующего мастера');
            }

            if ($order['status'] === 'Заявка' || $order['status'] === 'Отказано') {
                throw new InvalidArgumentException('Сначала примите заявку');
            }
            $ownership = demo_get($data['ownership'], $order['ownership_id']);
            $device = $ownership ? demo_get($data['devices'], $ownership['device_id']) : null;
            $model = $device ? demo_get($data['models'], $device['model_id']) : null;
            $required = app_specialty_for_type($model['type'] ?? '');
            $qualified = false;
            foreach ($data['specializations'] as $item) {
                if ($item['staff_id'] === $master['id'] && $item['name'] === $required) {
                    $qualified = true;
                }
            }
            if (!$qualified) {
                throw new InvalidArgumentException('У выбранного мастера нет нужной специализации');
            }

            foreach ($data['assignments'] as &$assignment) {
                if ($assignment['order_id'] !== $orderId || $assignment['completed_at']) {
                    continue;
                }
                if ($assignment['master_id'] === $master['id']) {
                    throw new InvalidArgumentException('Этот мастер уже назначен');
                }
                $assignment['completed_at'] = $now;
            }
            unset($assignment);

            $data['assignments'][] = [
                'id' => demo_next_id($data['assignments']),
                'order_id' => $orderId,
                'master_id' => $master['id'],
                'assigned_at' => $now,
                'started_at' => null,
                'completed_at' => null,
            ];
            if ($order['status'] === 'Принят') {
                foreach ($data['orders'] as &$item) {
                    if ($item['id'] === $orderId) {
                        $item['status'] = 'Передан мастеру';
                        break;
                    }
                }
                unset($item);
                $data['history'][] = [
                    'id' => demo_next_id($data['history']),
                    'order_id' => $orderId,
                    'status' => 'Передан мастеру',
                    'changed_at' => $now,
                    'staff_id' => $staff['id'],
                    'comment' => 'Назначен мастер',
                ];
            }
            return $orderId;

        case 'status':
            if ($staff['role'] === 'client') {
                throw new InvalidArgumentException('Статусы меняют сотрудники');
            }
            $order = demo_order($data, $orderId);
            $newStatus = $values['status'];

            if ($staff['role'] === 'master') {
                $isAssigned = false;
                foreach ($data['assignments'] as $assignment) {
                    if ($assignment['order_id'] == $orderId &&
                        $assignment['master_id'] == $staff['id'] && empty($assignment['completed_at'])) {
                        $isAssigned = true;
                    }
                }
                if (!$isAssigned) {
                    throw new InvalidArgumentException('Вам не назначен этот ремонт');
                }
                if (!in_array($newStatus, ['Диагностика', 'В ремонте', 'Готов к выдаче'], true)) {
                    throw new InvalidArgumentException('Этот статус меняет приемщик');
                }
            }

            $allowedStatuses = status_changes();
            if (!in_array($newStatus, $allowedStatuses[$order['status']] ?? [], true)) {
                throw new InvalidArgumentException('Из этого статуса нельзя перейти в выбранный');
            }
            if ($newStatus === 'Выдан' && demo_total($data, $orderId) > demo_paid($data, $orderId) + 0.001) {
                throw new InvalidArgumentException('Перед выдачей нужно полностью оплатить заказ');
            }
            if ($newStatus === 'В ремонте') {
                $isAssigned = false;
                foreach ($data['assignments'] as $assignment) {
                    if ($assignment['order_id'] == $orderId) {
                        $isAssigned = true;
                    }
                }
                if (!$isAssigned) {
                    throw new InvalidArgumentException('Сначала назначьте мастера');
                }
            }

            foreach ($data['orders'] as &$item) {
                if ($item['id'] == $orderId) {
                    $item['status'] = $newStatus;
                    if ($newStatus === 'Выдан') {
                        $item['issued_at'] = $now;
                    }
                    break;
                }
            }
            unset($item);

            $data['history'][] = [
                'id' => demo_next_id($data['history']),
                'order_id' => $orderId,
                'status' => $newStatus,
                'changed_at' => $now,
                'staff_id' => $staff['id'],
                'comment' => $values['comment'],
            ];
            return $orderId;

        case 'work_add':
            if ($staff['role'] === 'client') {
                throw new InvalidArgumentException('Работы добавляет мастер');
            }
            $order = demo_order($data, $orderId);
            if (in_array($order['status'], ['Заявка', 'Отказано', 'Выдан', 'Отменен'], true)) {
                throw new InvalidArgumentException('Заказ уже закрыт');
            }
            $tariff = demo_get($data['tariffs'], (int) $values['tariff_id']);
            if (!$tariff) {
                throw new InvalidArgumentException('Выберите работу');
            }
            $assignment = null;
            foreach ($data['assignments'] as $item) {
                if ($item['order_id'] == $orderId &&
                    ($staff['role'] !== 'master' || ($item['master_id'] == $staff['id'] && empty($item['completed_at'])))) {
                    $assignment = $item;
                }
            }
            if (!$assignment) {
                throw new InvalidArgumentException('Сначала назначьте мастера');
            }

            $data['works'][] = [
                'id' => demo_next_id($data['works']),
                'order_id' => $orderId,
                'assignment_id' => $assignment['id'],
                'tariff_id' => $tariff['id'],
                'name' => $tariff['name'],
                'price' => $tariff['price'],
                'done_at' => $now,
            ];
            return $orderId;

        case 'part_add':
            demo_can_manage();
            $order = demo_order($data, $orderId);
            if (in_array($order['status'], ['Заявка', 'Отказано', 'Выдан', 'Отменен'], true)) {
                throw new InvalidArgumentException('Заказ уже закрыт');
            }
            $part = demo_get($data['parts'], (int) $values['part_id']);
            $quantity = (int) $values['quantity'];
            if (!$part || $quantity < 1 || $quantity > 100) {
                throw new InvalidArgumentException('Выберите запчасть и количество');
            }
            if ($quantity > $part['stock']) {
                throw new InvalidArgumentException('На складе недостаточно запчастей');
            }
            $price = demo_amount($values['price']);

            foreach ($data['parts'] as &$item) {
                if ($item['id'] == $part['id']) {
                    $item['stock'] -= $quantity;
                    break;
                }
            }
            unset($item);

            $data['order_parts'][] = [
                'id' => demo_next_id($data['order_parts']),
                'order_id' => $orderId,
                'part_id' => $part['id'],
                'quantity' => $quantity,
                'price' => $price,
            ];
            $data['movements'][] = [
                'id' => demo_next_id($data['movements']),
                'part_id' => $part['id'],
                'order_id' => $orderId,
                'change' => -$quantity,
                'type' => 'Расход',
                'moved_at' => $now,
            ];
            return $orderId;

        case 'discount_add':
            demo_can_manage();
            $order = demo_order($data, $orderId);
            if (in_array($order['status'], ['Заявка', 'Отказано', 'Выдан', 'Отменен'], true)) {
                throw new InvalidArgumentException('Заказ уже закрыт');
            }
            $amount = demo_amount($values['amount']);
            $subtotal = 0;
            foreach ($data['works'] as $work) {
                if ($work['order_id'] == $orderId) {
                    $subtotal += (float) $work['price'];
                }
            }
            foreach ($data['order_parts'] as $part) {
                if ($part['order_id'] == $orderId) {
                    $subtotal += (float) $part['price'] * (int) $part['quantity'];
                }
            }
            foreach ($data['discounts'] as $discount) {
                if ($discount['order_id'] == $orderId) {
                    $subtotal -= (float) $discount['amount'];
                }
            }
            if ($amount > $subtotal) {
                throw new InvalidArgumentException('Скидка не может быть больше стоимости ремонта');
            }
            demo_required($values['name'], 'Укажите основание скидки');

            $data['discounts'][] = [
                'id' => demo_next_id($data['discounts']),
                'order_id' => $orderId,
                'name' => $values['name'],
                'amount' => $amount,
                'applied_at' => $now,
            ];
            return $orderId;

        case 'payment_add':
            demo_can_manage();
            $order = demo_order($data, $orderId);
            if (!in_array($order['status'], ['Готов к выдаче', 'Выдан'], true)) {
                throw new InvalidArgumentException('Оплата доступна после завершения ремонта');
            }
            $amount = demo_amount($values['amount']);
            if ($amount > demo_total($data, $orderId) - demo_paid($data, $orderId) + 0.001) {
                throw new InvalidArgumentException('Сумма больше остатка по заказу');
            }
            if (!in_array($values['method'], ['Карта', 'Наличные', 'Перевод'], true)) {
                throw new InvalidArgumentException('Выберите способ оплаты');
            }
            $data['payments'][] = [
                'id' => demo_next_id($data['payments']),
                'order_id' => $orderId,
                'amount' => $amount,
                'method' => $values['method'],
                'status' => 'Проведен',
                'paid_at' => $now,
            ];
            return $orderId;

        case 'warranty_add':
            demo_can_manage();
            $work = demo_get($data['works'], (int) $values['work_id']);
            $newOrder = demo_order($data, $orderId);
            if (!$work || (int) $work['order_id'] === $orderId) {
                throw new InvalidArgumentException('Выберите работу из предыдущего ремонта');
            }
            $oldOrder = demo_order($data, $work['order_id']);
            if ((int) $oldOrder['ownership_id'] !== (int) $newOrder['ownership_id']) {
                throw new InvalidArgumentException('Гарантия должна относиться к тому же устройству и владельцу');
            }
            if ($oldOrder['status'] !== 'Выдан') {
                throw new InvalidArgumentException('Предыдущий ремонт еще не выдан');
            }
            $tariff = demo_get($data['tariffs'], $work['tariff_id']);
            $warrantyEnd = $tariff
                ? strtotime($work['done_at'] . ' +' . $tariff['warranty_days'] . ' days')
                : 0;
            if (!$tariff || $tariff['warranty_days'] < 1 || $warrantyEnd < time()) {
                throw new InvalidArgumentException('Гарантийный срок работы истек или не установлен');
            }
            demo_required($values['reason'], 'Укажите причину обращения');

            $data['warranties'][] = [
                'id' => demo_next_id($data['warranties']),
                'order_id' => $orderId,
                'work_id' => $work['id'],
                'opened_at' => $now,
                'reason' => $values['reason'],
                'decision' => 'На рассмотрении',
                'status' => 'Открыто',
            ];
            return $orderId;
    }

    throw new RuntimeException('Неизвестная операция');
}

function repo_action($action, $values = [])
{
    demo_initial_data();
    $file = fopen(demo_file(), 'c+');
    if (!$file) {
        throw new RuntimeException('Нет доступа к файлу демонстрационной базы');
    }

    try {
        if (!flock($file, LOCK_EX)) {
            throw new RuntimeException('Не удалось заблокировать файл базы');
        }
        rewind($file);
        $data = json_decode(stream_get_contents($file), true);
        if (!is_array($data)) {
            throw new RuntimeException('Демонстрационная база повреждена');
        }

        $result = demo_modify($data, $action, $values);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_INVALID_UTF8_SUBSTITUTE);
        rewind($file);
        ftruncate($file, 0);
        fwrite($file, $json);
        fflush($file);
        flock($file, LOCK_UN);
        return $result;
    } finally {
        fclose($file);
    }
}
