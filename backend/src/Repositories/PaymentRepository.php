<?php

namespace App\Repositories;

use App\Core\Database;

class PaymentRepository
{
    public function getOrderPayments(int $orderId): array
    {
        $sql = "SELECT p.payment_id, p.order_id, p.amount, p.paid_at, p.external_reference,
                       pm.payment_method_id, pm.name AS method_name, pm.is_voucher,
                       ps.payment_status_id, ps.name AS status_name, ps.is_successful,
                       COALESCE(SUM(pr.amount), 0) AS refunded_amount
                FROM payments p
                JOIN payment_methods pm ON pm.payment_method_id = p.payment_method_id
                JOIN payment_statuses ps ON ps.payment_status_id = p.payment_status_id
                LEFT JOIN payment_refunds pr ON pr.payment_id = p.payment_id
                WHERE p.order_id = :order_id
                GROUP BY p.payment_id, p.order_id, p.amount, p.paid_at, p.external_reference,
                         pm.payment_method_id, pm.name, pm.is_voucher,
                         ps.payment_status_id, ps.name, ps.is_successful
                ORDER BY p.payment_id ASC";

        return Database::query($sql, ['order_id' => $orderId]);
    }

    public function getNetPaidAmount(int $orderId): float
    {
        $sql = "SELECT COALESCE(SUM(p.amount) - COALESCE(SUM(pr.total_refund), 0), 0) AS net_paid
                FROM payments p
                JOIN payment_statuses ps ON ps.payment_status_id = p.payment_status_id
                LEFT JOIN (
                    SELECT payment_id, SUM(amount) AS total_refund
                    FROM payment_refunds
                    GROUP BY payment_id
                ) pr ON pr.payment_id = p.payment_id
                WHERE p.order_id = :order_id AND ps.is_successful = true";

        $row = Database::queryOne($sql, ['order_id' => $orderId]);
        return $row ? (float) $row['net_paid'] : 0.0;
    }

    public function recordPayment(
        int $orderId,
        int $methodId,
        float $amount,
        ?string $extRef = null,
        ?int $voucherId = null
    ): int {
        Database::beginTransaction();
        try {
            // Статус «Проведен» (успешный)
            $statusRow = Database::queryOne("SELECT payment_status_id FROM payment_statuses WHERE is_successful = true LIMIT 1");
            $statusId = $statusRow ? (int) $statusRow['payment_status_id'] : 2;

            $paymentId = (int) Database::insertAndGetId(
                "INSERT INTO payments (order_id, payment_method_id, payment_status_id, paid_at, amount, external_reference)
                 VALUES (:order_id, :method_id, :status_id, now(), :amount, :ext_ref)",
                [
                    'order_id' => $orderId,
                    'method_id' => $methodId,
                    'status_id' => $statusId,
                    'amount' => $amount,
                    'ext_ref' => $extRef,
                ],
                'payment_id'
            );

            // Если оплата ваучером — зафиксировать расход в voucher_transactions
            if ($voucherId !== null) {
                Database::execute(
                    "INSERT INTO voucher_transactions (voucher_id, payment_id, occurred_at, amount_change, description)
                     VALUES (:voucher_id, :payment_id, now(), :amount_change, :descr)",
                    [
                        'voucher_id' => $voucherId,
                        'payment_id' => $paymentId,
                        'amount_change' => -$amount,
                        'descr' => "Оплата по заказу №{$orderId}",
                    ]
                );
            }

            Database::commit();
            return $paymentId;
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public function listPaymentMethods(): array
    {
        return Database::query("SELECT payment_method_id, name, is_voucher FROM payment_methods ORDER BY payment_method_id ASC");
    }
}
