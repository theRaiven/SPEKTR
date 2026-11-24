<?php

namespace App\Repositories;

use App\Core\Database;

class ReviewRepository
{
    public function getReviewForOrder(int $orderId): ?array
    {
        $sql = "SELECT r.order_work_id, r.account_id, r.rating, r.comment, r.created_at,
                       ua.login AS author_login,
                       wt.name AS work_name
                FROM reviews r
                JOIN order_works ow ON ow.order_work_id = r.order_work_id
                JOIN order_assignments oa ON oa.assignment_id = ow.assignment_id
                JOIN work_tariffs wt_tar ON wt_tar.tariff_id = ow.tariff_id
                JOIN work_types wt ON wt.work_type_id = wt_tar.work_type_id
                JOIN user_accounts ua ON ua.account_id = r.account_id
                WHERE oa.order_id = :order_id
                LIMIT 1";

        return Database::queryOne($sql, ['order_id' => $orderId]);
    }

    public function getReviewForWork(int $orderWorkId): ?array
    {
        $sql = "SELECT r.order_work_id, r.account_id, r.rating, r.comment, r.created_at
                FROM reviews r
                WHERE r.order_work_id = :work_id";

        return Database::queryOne($sql, ['work_id' => $orderWorkId]);
    }

    public function createReview(int $orderWorkId, int $accountId, int $rating, ?string $comment): void
    {
        $sql = "INSERT INTO reviews (order_work_id, account_id, rating, comment, created_at)
                VALUES (:work_id, :account_id, :rating, :comment, now())";

        Database::execute($sql, [
            'work_id' => $orderWorkId,
            'account_id' => $accountId,
            'rating' => $rating,
            'comment' => $comment,
        ]);
    }
}
