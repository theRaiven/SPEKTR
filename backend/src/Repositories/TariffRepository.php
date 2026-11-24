<?php

namespace App\Repositories;

use App\Core\Database;

class TariffRepository
{
    public function listWorkTypes(): array
    {
        $sql = "SELECT wt.work_type_id, wt.name, wt.requires_specialist,
                       t.tariff_id, t.price, t.warranty_days, t.valid_from
                FROM work_types wt
                JOIN work_tariffs t ON t.work_type_id = wt.work_type_id
                WHERE t.valid_from <= CURRENT_DATE
                ORDER BY wt.name ASC, t.valid_from DESC";

        $rows = Database::query($sql);
        // Оставить только самый свежий действующий тариф для каждого вида работ
        $result = [];
        $seen = [];
        foreach ($rows as $row) {
            $id = $row['work_type_id'];
            if (!isset($seen[$id])) {
                $seen[$id] = true;
                $result[] = $row;
            }
        }
        return $result;
    }

    public function getActiveTariffForWorkType(int $workTypeId): ?array
    {
        $sql = "SELECT t.tariff_id, t.work_type_id, t.price, t.warranty_days, t.valid_from
                FROM work_tariffs t
                WHERE t.work_type_id = :id AND t.valid_from <= CURRENT_DATE
                ORDER BY t.valid_from DESC LIMIT 1";

        return Database::queryOne($sql, ['id' => $workTypeId]);
    }

    public function addWorkToOrder(int $assignmentId, int $tariffId): int
    {
        $sql = "INSERT INTO order_works (assignment_id, tariff_id, tariff_applied_on, performed_at)
                VALUES (:assignment_id, :tariff_id, CURRENT_DATE, now())";

        return (int) Database::insertAndGetId($sql, [
            'assignment_id' => $assignmentId,
            'tariff_id' => $tariffId,
        ], 'order_work_id');
    }

    public function getOrderWorks(int $orderId): array
    {
        $sql = "SELECT ow.order_work_id, ow.assignment_id, ow.tariff_applied_on, ow.performed_at,
                       wt.work_type_id, wt.name AS work_name,
                       wt_tar.price, wt_tar.warranty_days,
                       e.employee_id AS master_id, e.last_name AS master_last_name, e.first_name AS master_first_name
                FROM order_works ow
                JOIN order_assignments oa ON oa.assignment_id = ow.assignment_id
                JOIN work_tariffs wt_tar ON wt_tar.tariff_id = ow.tariff_id
                JOIN work_types wt ON wt.work_type_id = wt_tar.work_type_id
                JOIN masters m ON m.employee_id = oa.employee_id
                JOIN employees e ON e.employee_id = m.employee_id
                WHERE oa.order_id = :order_id
                ORDER BY ow.order_work_id ASC";

        return Database::query($sql, ['order_id' => $orderId]);
    }
}
