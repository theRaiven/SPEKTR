<?php
require_once __DIR__ . '/settings.php';

// страницы обращаются к данным через эти функции
function app_mode()
{
    $settings = app_settings();
    return $settings['mode'] ?? 'demo';
}

function app_repo()
{
    if (app_mode() === 'demo') {
        require_once __DIR__ . '/repository_demo.php';
    } else {
        require_once __DIR__ . '/repository_pg.php';
    }
}

function app_data()
{
    app_repo();
    return repo_data();
}

function app_login($login, $password)
{
    app_repo();
    return repo_login($login, $password);
}

function app_action($action, $values = [])
{
    app_repo();
    return repo_action($action, $values);
}

function app_lookup($rows, $id)
{
    foreach ($rows ?? [] as $row) {
        if ((int) $row['id'] === (int) $id) {
            return $row;
        }
    }
    return null;
}

function app_find($table, $id)
{
    $data = app_data();
    return app_lookup($data[$table] ?? [], $id);
}

function app_orders()
{
    $data = app_data();
    $orders = [];

    foreach ($data['orders'] as $order) {
        $ownership = app_lookup($data['ownership'], $order['ownership_id']);
        $device = $ownership ? app_lookup($data['devices'], $ownership['device_id']) : null;
        $client = $ownership ? app_lookup($data['clients'], $ownership['client_id']) : null;
        $model = $device ? app_lookup($data['models'], $device['model_id']) : null;

        $row = $order;
        $row['client'] = $client ? name_view($client) : ' - ';
        $row['client_id'] = $client['id'] ?? null;
        $row['device'] = $model ? $model['brand'] . ' ' . $model['name'] : ' - ';
        $row['device_id'] = $device['id'] ?? null;
        $orders[] = $row;
    }

    usort($orders, fn($first, $second) => $second['id'] <=> $first['id']);
    return $orders;
}

function app_order($id)
{
    return app_lookup(app_orders(), $id);
}

function app_client_orders($id)
{
    return array_values(array_filter(
        app_orders(),
        fn($order) => $order['client_id'] == $id
    ));
}

function app_device_orders($id)
{
    return array_values(array_filter(
        app_orders(),
        fn($order) => $order['device_id'] == $id
    ));
}

function app_rows_for($table, $field, $id)
{
    $data = app_data();
    return array_values(array_filter(
        $data[$table] ?? [],
        fn($row) => ($row[$field] ?? null) == $id
    ));
}

function app_master_name($id)
{
    $master = app_find('staff', $id);
    return $master ? name_view($master) : ' - ';
}

function app_order_cost($id)
{
    $data = app_data();
    $works = 0;
    $parts = 0;
    $discount = 0;
    $paid = 0;

    foreach ($data['works'] as $work) {
        if ($work['order_id'] == $id) {
            $works += $work['price'];
        }
    }
    foreach ($data['order_parts'] as $part) {
        if ($part['order_id'] == $id) {
            $parts += $part['quantity'] * $part['price'];
        }
    }
    foreach ($data['discounts'] ?? [] as $item) {
        if ($item['order_id'] == $id) {
            $discount += $item['amount'];
        }
    }
    foreach ($data['payments'] as $payment) {
        if ($payment['order_id'] == $id && $payment['status'] === 'Проведен') {
            $paid += $payment['amount'];
        }
    }

    $total = max(0, $works + $parts - $discount);
    return [
        'works' => $works,
        'parts' => $parts,
        'discount' => $discount,
        'total' => $total,
        'paid' => $paid,
        'due' => $total - $paid,
    ];
}

function app_estimate($order)
{
    $service = app_find('tariffs', $order['service_id'] ?? 0);
    return $service ? $service['price'] : null;
}

function app_specialty_for_type($type)
{
    $specialties = [
        'Смартфон' => 'Ремонт смартфонов',
        'Ноутбук' => 'Ремонт ноутбуков',
        'Планшет' => 'Ремонт планшетов',
        'Компьютер' => 'Ремонт компьютеров',
        'Принтер' => 'Ремонт принтеров',
        'Монитор' => 'Ремонт мониторов',
        'Игровая приставка' => 'Ремонт приставок',
        'Телевизор' => 'Ремонт телевизоров',
        'Другая техника' => 'Ремонт прочей электроники',
    ];
    return $specialties[$type] ?? null;
}

function app_qualified_masters($order)
{
    $data = app_data();
    $ownership = app_lookup($data['ownership'], $order['ownership_id']);
    $device = $ownership ? app_lookup($data['devices'], $ownership['device_id']) : null;
    $model = $device ? app_lookup($data['models'], $device['model_id']) : null;
    $required = app_specialty_for_type($model['type'] ?? '');
    $masters = [];
    foreach ($data['staff'] as $person) {
        if ($person['role'] !== 'master' || !$person['active']) {
            continue;
        }
        foreach ($data['specializations'] as $spec) {
            if ($spec['staff_id'] === $person['id'] && $spec['name'] === $required) {
                $masters[] = $person;
                break;
            }
        }
    }
    return $masters;
}


// стаж считаем по самой ранней специализации мастера
function app_master_experience($masterId)
{
    $startDate = null;
    foreach (app_data()['specializations'] as $specialty) {
        if ((int) $specialty['staff_id'] !== (int) $masterId) {
            continue;
        }
        $date = $specialty['experience_from'] ?? null;
        if ($date && (!$startDate || $date < $startDate)) {
            $startDate = $date;
        }
    }
    if (!$startDate) {
        return 'Не указан';
    }
    $years = (new DateTimeImmutable($startDate))->diff(new DateTimeImmutable('today'))->y;
    if ($years < 1) {
        return 'Меньше года';
    }
    $ending = ($years % 10 === 1 && $years % 100 !== 11) ? 'год' :
        ((in_array($years % 10, [2, 3, 4], true) &&
          !in_array($years % 100, [12, 13, 14], true)) ? 'года' : 'лет');
    return $years . ' ' . $ending;
}
