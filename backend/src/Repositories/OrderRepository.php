<?php

namespace App\Repositories;

use App\Core\Database;
use RuntimeException;

class OrderRepository
{
    public function findAll(?int $clientId = null, ?int $masterId = null, ?string $statusName = null): array
    {
        $params = [];
        $where = [];

        if ($clientId !== null) {
            $where[] = "ro.client_id = :client_id";
            $params['client_id'] = $clientId;
        }

        if ($masterId !== null) {
            $where[] = "EXISTS (
                SELECT 1 FROM order_assignments oa
                WHERE oa.order_id = ro.order_id AND oa.employee_id = :master_id
            )";
            $params['master_id'] = $masterId;
        }

        if ($statusName !== null && trim($statusName) !== '') {
            $where[] = "s.name = :status_name";
            $params['status_name'] = trim($statusName);
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT ro.order_id, ro.created_at, ro.fault_description, ro.queue_priority,
                       ro.planned_ready_at, ro.device_received_at, ro.issued_at, ro.closed_at, ro.rejection_reason,
                       s.order_status_id, s.name AS status_name, s.is_issued, s.is_closed,
                       c.client_id, c.last_name AS client_last_name, c.first_name AS client_first_name, c.phone AS client_phone,
                       d.device_id, d.serial_number,
                       m.model_id, m.model_name,
                       b.brand_id, b.name AS brand_name,
                       t.device_type_id, t.name AS device_type_name,
                       cur_oa.employee_id AS master_id,
                       cur_emp.last_name AS master_last_name,
                       cur_emp.first_name AS master_first_name,
                       cur_emp.phone AS master_phone
                FROM repair_orders ro
                JOIN order_statuses s ON s.order_status_id = ro.order_status_id
                JOIN clients c ON c.client_id = ro.client_id
                JOIN devices d ON d.device_id = ro.device_id
                JOIN device_models m ON m.model_id = d.model_id
                JOIN brands b ON b.brand_id = m.brand_id
                JOIN device_types t ON t.device_type_id = m.device_type_id
                LEFT JOIN order_assignments cur_oa ON cur_oa.assignment_id = (
                    SELECT oa2.assignment_id FROM order_assignments oa2
                    WHERE oa2.order_id = ro.order_id
                    ORDER BY oa2.assigned_at DESC LIMIT 1
                )
                LEFT JOIN employees cur_emp ON cur_emp.employee_id = cur_oa.employee_id
                {$whereClause}
                ORDER BY ro.order_id DESC";

        return Database::query($sql, $params);
    }

    public function findById(int $orderId): ?array
    {
        $sql = "SELECT ro.order_id, ro.created_at, ro.fault_description, ro.queue_priority,
                       ro.planned_ready_at, ro.device_received_at, ro.issued_at, ro.closed_at, ro.rejection_reason,
                       s.order_status_id, s.name AS status_name, s.is_issued, s.is_closed,
                       c.client_id, c.last_name AS client_last_name, c.first_name AS client_first_name, c.middle_name AS client_middle_name,
                       c.phone AS client_phone, c.email AS client_email,
                       d.device_id, d.serial_number, d.purchase_date,
                       m.model_id, m.model_name,
                       b.brand_id, b.name AS brand_name,
                       t.device_type_id, t.name AS device_type_name,
                       cur_oa.employee_id AS master_id,
                       cur_emp.last_name AS master_last_name,
                       cur_emp.first_name AS master_first_name,
                       cur_emp.phone AS master_phone
                FROM repair_orders ro
                JOIN order_statuses s ON s.order_status_id = ro.order_status_id
                JOIN clients c ON c.client_id = ro.client_id
                JOIN devices d ON d.device_id = ro.device_id
                JOIN device_models m ON m.model_id = d.model_id
                JOIN brands b ON b.brand_id = m.brand_id
                JOIN device_types t ON t.device_type_id = m.device_type_id
                LEFT JOIN order_assignments cur_oa ON cur_oa.assignment_id = (
                    SELECT oa2.assignment_id FROM order_assignments oa2
                    WHERE oa2.order_id = ro.order_id
                    ORDER BY oa2.assigned_at DESC LIMIT 1
                )
                LEFT JOIN employees cur_emp ON cur_emp.employee_id = cur_oa.employee_id
                WHERE ro.order_id = :order_id";

        return Database::queryOne($sql, ['order_id' => $orderId]);
    }

    public function createOrder(
        int $clientId,
        int $deviceId,
        string $faultDescription,
        ?string $plannedVisitAt = null,
        int $queuePriority = 0
    ): int {
        Database::beginTransaction();
        try {
            // 1. Получить ID начального статуса «Новая»
            $statusRow = Database::queryOne("SELECT order_status_id FROM order_statuses WHERE name = 'Новая'");
            if (!$statusRow) {
                throw new RuntimeException("Статус 'Новая' не найден в справочнике статусов");
            }
            $statusId = (int) $statusRow['order_status_id'];

            // 2. Создать заказ
            $orderId = (int) Database::insertAndGetId(
                "INSERT INTO repair_orders (client_id, device_id, order_status_id, fault_description, planned_visit_at, queue_priority, created_at)
                 VALUES (:client_id, :device_id, :status_id, :fault, :visit, :priority, now())",
                [
                    'client_id' => $clientId,
                    'device_id' => $deviceId,
                    'status_id' => $statusId,
                    'fault' => $faultDescription,
                    'visit' => $plannedVisitAt ?: null,
                    'priority' => $queuePriority,
                ],
                'order_id'
            );

            // 3. Запись в историю статусов
            Database::execute(
                "INSERT INTO order_status_history (order_id, order_status_id, changed_at, changed_by_employee_id, note)
                 VALUES (:order_id, :status_id, now(), NULL, 'Заявка отправлена клиентом')",
                [
                    'order_id' => $orderId,
                    'status_id' => $statusId,
                ]
            );

            Database::commit();
            return $orderId;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public function updateStatus(
        int $orderId,
        string $statusName,
        ?int $changedByEmployeeId = null,
        ?string $note = null
    ): void {
        Database::beginTransaction();
        try {
            $statusRow = Database::queryOne("SELECT order_status_id, is_issued, is_closed FROM order_statuses WHERE name = :name", ['name' => $statusName]);
            if (!$statusRow) {
                throw new RuntimeException("Статус '{$statusName}' не найден в справочнике");
            }
            $statusId = (int) $statusRow['order_status_id'];

            // Обновить repair_orders
            $extraUpdates = '';
            if ($statusName === 'Принята') {
                $extraUpdates = ', device_received_at = COALESCE(device_received_at, now())';
            } elseif ($statusRow['is_closed']) {
                $extraUpdates = ', closed_at = now()';
            }
            if ($statusRow['is_issued']) {
                $extraUpdates .= ', issued_at = now()';
            }

            Database::execute(
                "UPDATE repair_orders SET order_status_id = :status_id {$extraUpdates} WHERE order_id = :order_id",
                [
                    'status_id' => $statusId,
                    'order_id' => $orderId,
                ]
            );

            // Запись в историю статусов
            Database::execute(
                "INSERT INTO order_status_history (order_id, order_status_id, changed_at, changed_by_employee_id, note)
                 VALUES (:order_id, :status_id, now(), :emp_id, :note)",
                [
                    'order_id' => $orderId,
                    'status_id' => $statusId,
                    'emp_id' => $changedByEmployeeId,
                    'note' => $note ?: "Смена статуса на '{$statusName}'",
                ]
            );

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public function rejectOrder(int $orderId, int $adminEmployeeId, string $reason): void
    {
        Database::beginTransaction();
        try {
            $statusRow = Database::queryOne("SELECT order_status_id FROM order_statuses WHERE name = 'Отклонена'");
            if (!$statusRow) {
                throw new RuntimeException("Статус 'Отклонена' не найден в базе данных");
            }
            $statusId = (int) $statusRow['order_status_id'];

            Database::execute(
                "UPDATE repair_orders SET order_status_id = :status_id, rejection_reason = :reason, closed_at = now()
                 WHERE order_id = :order_id",
                [
                    'status_id' => $statusId,
                    'reason' => $reason,
                    'order_id' => $orderId,
                ]
            );

            Database::execute(
                "INSERT INTO order_status_history (order_id, order_status_id, changed_at, changed_by_employee_id, note)
                 VALUES (:order_id, :status_id, now(), :emp_id, :note)",
                [
                    'order_id' => $orderId,
                    'status_id' => $statusId,
                    'emp_id' => $adminEmployeeId,
                    'note' => "Отказ в обслуживании: {$reason}",
                ]
            );

            Database::commit();
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public function assignMaster(int $orderId, int $masterEmployeeId, ?int $assignedByEmployeeId = null): int
    {
        Database::beginTransaction();
        try {
            // 1. Создать назначение
            $assignmentId = (int) Database::insertAndGetId(
                "INSERT INTO order_assignments (order_id, employee_id, assigned_at)
                 VALUES (:order_id, :master_id, now())",
                [
                    'order_id' => $orderId,
                    'master_id' => $masterEmployeeId,
                ],
                'assignment_id'
            );

            // 2. Перевести в статус «Передана мастеру»
            $this->updateStatus($orderId, 'Передана мастеру', $assignedByEmployeeId, 'Назначен мастер на выполнение работ');

            Database::commit();
            return $assignmentId;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public function getActiveAssignment(int $orderId): ?array
    {
        $sql = "SELECT assignment_id, order_id, employee_id, assigned_at, work_started_at, work_completed_at
                FROM order_assignments
                WHERE order_id = :order_id
                ORDER BY assigned_at DESC LIMIT 1";

        return Database::queryOne($sql, ['order_id' => $orderId]);
    }

    public function getStatusHistory(int $orderId): array
    {
        $sql = "SELECT osh.status_history_id, osh.order_id, osh.changed_at, osh.note,
                       s.order_status_id, s.name AS status_name,
                       e.employee_id, e.last_name, e.first_name
                FROM order_status_history osh
                JOIN order_statuses s ON s.order_status_id = osh.order_status_id
                LEFT JOIN employees e ON e.employee_id = osh.changed_by_employee_id
                WHERE osh.order_id = :order_id
                ORDER BY osh.changed_at ASC, osh.status_history_id ASC";

        return Database::query($sql, ['order_id' => $orderId]);
    }
}
