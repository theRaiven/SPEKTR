<?php

namespace App\Repositories;

use App\Core\Database;

class MasterRepository
{
    public function findAll(): array
    {
        $sql = "SELECT m.employee_id, e.last_name, e.first_name, e.middle_name, e.phone, e.hired_on,
                       COUNT(DISTINCT CASE WHEN s.is_closed = false THEN oa.order_id END) AS active_orders_count
                FROM masters m
                JOIN employees e ON e.employee_id = m.employee_id
                LEFT JOIN order_assignments oa ON oa.employee_id = m.employee_id AND oa.work_completed_at IS NULL
                LEFT JOIN repair_orders ro ON ro.order_id = oa.order_id
                LEFT JOIN order_statuses s ON s.order_status_id = ro.order_status_id
                WHERE e.dismissed_on IS NULL
                GROUP BY m.employee_id, e.last_name, e.first_name, e.middle_name, e.phone, e.hired_on
                ORDER BY e.last_name ASC, e.first_name ASC";

        $masters = Database::query($sql);

        foreach ($masters as &$master) {
            $master['specializations'] = $this->getSpecializations((int) $master['employee_id']);
        }

        return $masters;
    }

    public function findById(int $masterId): ?array
    {
        $sql = "SELECT m.employee_id, e.last_name, e.first_name, e.middle_name, e.phone, e.hired_on
                FROM masters m
                JOIN employees e ON e.employee_id = m.employee_id
                WHERE m.employee_id = :master_id AND e.dismissed_on IS NULL";

        $master = Database::queryOne($sql, ['master_id' => $masterId]);
        if ($master) {
            $master['specializations'] = $this->getSpecializations($masterId);
        }
        return $master;
    }

    public function getSpecializations(int $masterId): array
    {
        $sql = "SELECT ms.master_specialization_id, s.specialization_id, s.name AS specialization_name,
                       sl.name AS skill_level_name, sl.level_rank,
                       ms.experience_since, ms.valid_from, ms.valid_to
                FROM master_specializations ms
                JOIN specializations s ON s.specialization_id = ms.specialization_id
                JOIN skill_levels sl ON sl.skill_level_id = ms.skill_level_id
                WHERE ms.employee_id = :master_id
                  AND (ms.valid_to IS NULL OR ms.valid_to >= CURRENT_DATE)
                ORDER BY sl.level_rank DESC";

        return Database::query($sql, ['master_id' => $masterId]);
    }

    public function isQualifiedForDeviceType(int $masterId, int $deviceTypeId): bool
    {
        // 1. Получить название типа устройства
        $typeRow = Database::queryOne("SELECT name FROM device_types WHERE device_type_id = :id", ['id' => $deviceTypeId]);
        if (!$typeRow) {
            return false;
        }

        $typeName = $typeRow['name'];

        // Карта соответствия типа устройства названию специализации
        $mapping = [
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

        $requiredSpec = $mapping[$typeName] ?? null;
        if (!$requiredSpec) {
            // Если специфичной специализации нет, считаем подходящим мастера с любой специализацией
            return true;
        }

        $sql = "SELECT 1 FROM master_specializations ms
                JOIN specializations s ON s.specialization_id = ms.specialization_id
                WHERE ms.employee_id = :master_id
                  AND (ms.valid_to IS NULL OR ms.valid_to >= CURRENT_DATE)
                  AND (s.name = :required_spec OR s.name = 'Ремонт прочей электроники')";

        $match = Database::queryOne($sql, [
            'master_id' => $masterId,
            'required_spec' => $requiredSpec,
        ]);

        return $match !== null;
    }
}
