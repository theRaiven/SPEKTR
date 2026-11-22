<?php

namespace App\Repositories;

use App\Core\Database;

class DiscountRepository
{
    public function findDiscountTypeByName(string $name): ?array
    {
        $sql = "SELECT dt.discount_type_id, dt.name, dt.value,
                       dct.discount_calculation_type_id, dct.name AS calc_name, dct.value_kind
                FROM discount_types dt
                JOIN discount_calculation_types dct ON dct.discount_calculation_type_id = dt.calculation_type_id
                WHERE LOWER(dt.name) LIKE LOWER(:name)";

        return Database::queryOne($sql, ['name' => '%' . trim($name) . '%']);
    }

    public function findDiscountTypeById(int $id): ?array
    {
        $sql = "SELECT dt.discount_type_id, dt.name, dt.value,
                       dct.discount_calculation_type_id, dct.name AS calc_name, dct.value_kind
                FROM discount_types dt
                JOIN discount_calculation_types dct ON dct.discount_calculation_type_id = dt.calculation_type_id
                WHERE dt.discount_type_id = :id";

        return Database::queryOne($sql, ['id' => $id]);
    }

    public function findVoucherByCode(string $code, int $clientId): ?array
    {
        $sql = "SELECT v.voucher_id, v.code, v.client_id, v.face_value, v.expires_on,
                       (v.face_value + COALESCE(SUM(vt.amount_change), 0)) AS remaining_balance
                FROM vouchers v
                LEFT JOIN voucher_transactions vt ON vt.voucher_id = v.voucher_id
                WHERE UPPER(v.code) = UPPER(:code)
                  AND v.client_id = :client_id
                  AND (v.expires_on IS NULL OR v.expires_on >= CURRENT_DATE)
                GROUP BY v.voucher_id, v.code, v.client_id, v.face_value, v.expires_on";

        return Database::queryOne($sql, ['code' => trim($code), 'client_id' => $clientId]);
    }

    public function findActiveClientBenefit(int $clientId, ?int $benefitCategoryId = null): ?array
    {
        $params = ['client_id' => $clientId];
        $catClause = '';
        if ($benefitCategoryId !== null) {
            $catClause = 'AND cb.benefit_category_id = :cat_id';
            $params['cat_id'] = $benefitCategoryId;
        }

        $sql = "SELECT cb.client_benefit_id, cb.client_id, cb.benefit_category_id,
                       bc.name AS category_name, cb.valid_from, cb.valid_to, cb.evidence_reference
                FROM client_benefits cb
                JOIN benefit_categories bc ON bc.benefit_category_id = cb.benefit_category_id
                WHERE cb.client_id = :client_id
                  AND cb.valid_from <= CURRENT_DATE
                  AND (cb.valid_to IS NULL OR cb.valid_to >= CURRENT_DATE)
                  {$catClause}
                ORDER BY cb.client_benefit_id DESC LIMIT 1";

        return Database::queryOne($sql, $params);
    }

    public function getAppliedDiscounts(int $orderId): array
    {
        $sql = "SELECT ad.applied_discount_id, ad.order_id, ad.calculation_base, ad.discount_amount,
                       dt.discount_type_id, dt.name AS discount_name, dt.value,
                       dct.value_kind,
                       cb.client_benefit_id, bc.name AS benefit_category_name
                FROM applied_discounts ad
                JOIN discount_types dt ON dt.discount_type_id = ad.discount_type_id
                JOIN discount_calculation_types dct ON dct.discount_calculation_type_id = dt.calculation_type_id
                LEFT JOIN client_benefits cb ON cb.client_benefit_id = ad.client_benefit_id
                LEFT JOIN benefit_categories bc ON bc.benefit_category_id = cb.benefit_category_id
                WHERE ad.order_id = :order_id";

        return Database::query($sql, ['order_id' => $orderId]);
    }

    public function isDiscountAlreadyApplied(int $orderId, int $discountTypeId): bool
    {
        $sql = "SELECT 1 FROM applied_discounts WHERE order_id = :order_id AND discount_type_id = :dt_id";
        return Database::queryOne($sql, ['order_id' => $orderId, 'dt_id' => $discountTypeId]) !== null;
    }

    public function applyDiscount(int $orderId, int $discountTypeId, ?int $clientBenefitId, float $calculationBase, float $amount): int
    {
        $sql = "INSERT INTO applied_discounts (order_id, discount_type_id, client_benefit_id, calculation_base, discount_amount)
                VALUES (:order_id, :discount_type_id, :client_benefit_id, :calc_base, :amount)";

        return (int) Database::insertAndGetId($sql, [
            'order_id' => $orderId,
            'discount_type_id' => $discountTypeId,
            'client_benefit_id' => $clientBenefitId,
            'calc_base' => $calculationBase,
            'amount' => $amount,
        ], 'applied_discount_id');
    }
}
