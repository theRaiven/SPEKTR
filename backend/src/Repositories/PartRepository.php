<?php

namespace App\Repositories;

use App\Core\Database;

class PartRepository
{
    public function listParts(): array
    {
        $sql = "SELECT p.part_id, p.article, p.name,
                       pt.part_tariff_id, pt.unit_price, pt.valid_from,
                       COALESCE(SUM(im.quantity_delta), 0) AS in_stock
                FROM parts p
                JOIN part_tariffs pt ON pt.part_id = p.part_id
                LEFT JOIN inventory_movements im ON (
                    (im.adjustment_part_id = p.part_id)
                    OR (im.supply_line_id IN (SELECT sl.supply_line_id FROM supply_lines sl WHERE sl.part_id = p.part_id))
                    OR (im.order_part_id IN (SELECT op.order_part_id FROM order_parts op WHERE op.part_id = p.part_id))
                )
                WHERE pt.valid_from <= CURRENT_DATE
                GROUP BY p.part_id, p.article, p.name, pt.part_tariff_id, pt.unit_price, pt.valid_from
                ORDER BY p.name ASC, pt.valid_from DESC";

        $rows = Database::query($sql);
        $result = [];
        $seen = [];
        foreach ($rows as $row) {
            $id = $row['part_id'];
            if (!isset($seen[$id])) {
                $seen[$id] = true;
                $result[] = $row;
            }
        }
        return $result;
    }

    public function getActiveTariffForPart(int $partId): ?array
    {
        $sql = "SELECT part_tariff_id, part_id, unit_price, valid_from
                FROM part_tariffs
                WHERE part_id = :part_id AND valid_from <= CURRENT_DATE
                ORDER BY valid_from DESC LIMIT 1";

        return Database::queryOne($sql, ['part_id' => $partId]);
    }

    public function addPartToOrder(int $orderId, int $partId, int $partTariffId, int $quantity): int
    {
        Database::beginTransaction();
        try {
            // 1. Добавить строку запчасти в заказ
            $orderPartId = Database::insertAndGetId(
                "INSERT INTO order_parts (order_id, part_id, part_tariff_id, price_applied_on, quantity)
                 VALUES (:order_id, :part_id, :tariff_id, CURRENT_DATE, :quantity)",
                [
                    'order_id' => $orderId,
                    'part_id' => $partId,
                    'tariff_id' => $partTariffId,
                    'quantity' => $quantity,
                ],
                'order_part_id'
            );

            // 2. Зафиксировать списание в inventory_movements
            $typeRow = Database::queryOne("SELECT movement_type_id FROM inventory_movement_types WHERE source_kind = 'order' AND direction = -1");
            $movementTypeId = $typeRow ? (int) $typeRow['movement_type_id'] : 2;

            Database::execute(
                "INSERT INTO inventory_movements (movement_type_id, order_part_id, quantity_delta, moved_at)
                 VALUES (:type_id, :order_part_id, :delta, now())",
                [
                    'type_id' => $movementTypeId,
                    'order_part_id' => $orderPartId,
                    'delta' => -$quantity,
                ]
            );

            Database::commit();
            return (int) $orderPartId;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public function getOrderParts(int $orderId): array
    {
        $sql = "SELECT op.order_part_id, op.order_id, op.quantity, op.price_applied_on,
                       p.part_id, p.article, p.name AS part_name,
                       pt.unit_price, (op.quantity * pt.unit_price) AS total_price
                FROM order_parts op
                JOIN parts p ON p.part_id = op.part_id
                JOIN part_tariffs pt ON pt.part_tariff_id = op.part_tariff_id
                WHERE op.order_id = :order_id
                ORDER BY op.order_part_id ASC";

        return Database::query($sql, ['order_id' => $orderId]);
    }
}
