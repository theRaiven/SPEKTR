<?php

namespace App\Repositories;

use App\Core\Database;

class ClientRepository
{
    public function findAll(string $search = ''): array
    {
        $params = [];
        $where = '';

        if (trim($search) !== '') {
            $where = "WHERE (
                LOWER(c.last_name || ' ' || c.first_name || ' ' || COALESCE(c.middle_name, '')) LIKE :search
                OR c.phone LIKE :search
                OR LOWER(c.email) LIKE :search
                OR EXISTS (
                    SELECT 1 FROM repair_orders ro
                    WHERE ro.client_id = c.client_id AND CAST(ro.order_id AS text) LIKE :exact_search
                )
            )";
            $params['search'] = '%' . strtolower(trim($search)) . '%';
            $params['exact_search'] = '%' . trim($search) . '%';
        }

        $sql = "SELECT c.client_id, c.last_name, c.first_name, c.middle_name, c.phone, c.email, c.created_at,
                       COUNT(DISTINCT ro.order_id) AS total_orders,
                       COUNT(DISTINCT CASE WHEN s.is_closed = false THEN ro.order_id END) AS active_orders
                FROM clients c
                LEFT JOIN repair_orders ro ON ro.client_id = c.client_id
                LEFT JOIN order_statuses s ON s.order_status_id = ro.order_status_id
                {$where}
                GROUP BY c.client_id, c.last_name, c.first_name, c.middle_name, c.phone, c.email, c.created_at
                ORDER BY c.client_id DESC";

        return Database::query($sql, $params);
    }

    public function findById(int $clientId): ?array
    {
        $sql = "SELECT c.client_id, c.last_name, c.first_name, c.middle_name, c.phone, c.email, c.created_at
                FROM clients c
                WHERE c.client_id = :client_id";

        return Database::queryOne($sql, ['client_id' => $clientId]);
    }
}
