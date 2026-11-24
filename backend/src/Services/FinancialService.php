<?php

namespace App\Services;

use App\Core\Exceptions\BadRequestException;
use App\Core\Exceptions\ConflictException;
use App\Core\Exceptions\NotFoundException;
use App\Repositories\OrderRepository;
use App\Repositories\TariffRepository;
use App\Repositories\PartRepository;
use App\Repositories\DiscountRepository;
use App\Repositories\PaymentRepository;

class FinancialService
{
    private OrderRepository $orderRepository;
    private TariffRepository $tariffRepository;
    private PartRepository $partRepository;
    private DiscountRepository $discountRepository;
    private PaymentRepository $paymentRepository;

    public function __construct()
    {
        $this->orderRepository = new OrderRepository();
        $this->tariffRepository = new TariffRepository();
        $this->partRepository = new PartRepository();
        $this->discountRepository = new DiscountRepository();
        $this->paymentRepository = new PaymentRepository();
    }

    public function getPaymentSummary(int $orderId): array
    {
        $order = $this->orderRepository->findById($orderId);
        if (!$order) {
            throw new NotFoundException("Заказ №{$orderId} не найден");
        }

        $works = $this->tariffRepository->getOrderWorks($orderId);
        $parts = $this->partRepository->getOrderParts($orderId);
        $discounts = $this->discountRepository->getAppliedDiscounts($orderId);
        $payments = $this->paymentRepository->getOrderPayments($orderId);

        $worksTotal = 0.0;
        foreach ($works as $w) {
            $worksTotal += (float) $w['price'];
        }

        $partsTotal = 0.0;
        foreach ($parts as $p) {
            $partsTotal += (float) $p['total_price'];
        }

        $baseCost = $worksTotal + $partsTotal;

        $discountsTotal = 0.0;
        foreach ($discounts as $d) {
            $discountsTotal += (float) $d['discount_amount'];
        }

        $totalCost = max(0.0, $baseCost - $discountsTotal);
        $totalPaid = $this->paymentRepository->getNetPaidAmount($orderId);
        $due = max(0.0, $totalCost - $totalPaid);
        $isPaid = ($totalCost > 0.0 && $due <= 0.001) || ($totalCost == 0.0 && $totalPaid > 0.0);

        return [
            'order_id' => $orderId,
            'status' => $order['status_name'],
            'base_cost' => round($baseCost, 2),
            'works_total' => round($worksTotal, 2),
            'parts_total' => round($partsTotal, 2),
            'discounts_total' => round($discountsTotal, 2),
            'total_cost' => round($totalCost, 2),
            'total_paid' => round($totalPaid, 2),
            'balance_due' => round($due, 2),
            'is_fully_paid' => $isPaid,
            'works' => $works,
            'parts' => $parts,
            'discounts' => $discounts,
            'payments' => $payments,
        ];
    }

    public function applyDiscount(int $orderId, int $clientId, array $data): array
    {
        $order = $this->orderRepository->findById($orderId);
        if (!$order) {
            throw new NotFoundException("Заказ №{$orderId} не найден");
        }

        if ((int) $order['client_id'] !== $clientId) {
            throw new BadRequestException('Скидка может быть применена только владельцем заказа');
        }

        if ($order['is_closed']) {
            throw new ConflictException('Нельзя применить скидку к закрытому или оплаченному заказу');
        }

        $type = $data['type'] ?? 'promo'; // 'promo', 'voucher', 'benefit'
        $code = trim($data['code'] ?? '');

        // Рассчитать текущую базу
        $summary = $this->getPaymentSummary($orderId);
        $base = $summary['base_cost'];
        if ($base <= 0) {
            throw new BadRequestException('Невозможно применить скидку к заказу с нулевой стоимостью работ и запчастей');
        }

        if ($type === 'promo') {
            if ($code === '') {
                throw new BadRequestException('Укажите промокод');
            }

            $discountType = $this->discountRepository->findDiscountTypeByName($code);
            if (!$discountType) {
                throw new BadRequestException("Промокод '{$code}' не найден или недействителен");
            }

            if ($this->discountRepository->isDiscountAlreadyApplied($orderId, (int) $discountType['discount_type_id'])) {
                throw new ConflictException("Промокод '{$code}' уже применён к этому заказу");
            }

            $amount = 0.0;
            if ($discountType['value_kind'] === 'percentage') {
                $amount = round(($base * (float) $discountType['value']) / 100.0, 2);
            } else {
                $amount = min($base, (float) $discountType['value']);
            }

            $appliedId = $this->discountRepository->applyDiscount(
                $orderId,
                (int) $discountType['discount_type_id'],
                null,
                $base,
                $amount
            );

            return [
                'applied_discount_id' => $appliedId,
                'discount_name' => $discountType['name'],
                'discount_amount' => $amount,
                'summary' => $this->getPaymentSummary($orderId),
            ];

        } elseif ($type === 'voucher') {
            if ($code === '') {
                throw new BadRequestException('Укажите код ваучера');
            }

            $voucher = $this->discountRepository->findVoucherByCode($code, $clientId);
            if (!$voucher) {
                throw new BadRequestException("Ваучер '{$code}' не найден или истек срок действия");
            }

            $remaining = (float) $voucher['remaining_balance'];
            if ($remaining <= 0) {
                throw new BadRequestException("Баланс ваучера '{$code}' исчерпан");
            }

            // Найти или создать скидочный тип "Ваучер"
            $dt = $this->discountRepository->findDiscountTypeByName('Ваучер');
            $dtId = $dt ? (int) $dt['discount_type_id'] : 4;

            $amount = min($base, $remaining);

            $appliedId = $this->discountRepository->applyDiscount(
                $orderId,
                $dtId,
                null,
                $base,
                $amount
            );

            return [
                'applied_discount_id' => $appliedId,
                'discount_name' => "Ваучер {$code}",
                'discount_amount' => $amount,
                'summary' => $this->getPaymentSummary($orderId),
            ];

        } elseif ($type === 'benefit') {
            $benefit = $this->discountRepository->findActiveClientBenefit($clientId);
            if (!$benefit) {
                throw new BadRequestException('У вас нет активных подтверждённых льгот');
            }

            $discountType = $this->discountRepository->findDiscountTypeByName($benefit['category_name']);
            if (!$discountType) {
                throw new BadRequestException("Правило скидки для категории '{$benefit['category_name']}' не найдено");
            }

            if ($this->discountRepository->isDiscountAlreadyApplied($orderId, (int) $discountType['discount_type_id'])) {
                throw new ConflictException("Льготная скидка '{$benefit['category_name']}' уже применена к этому заказу");
            }

            $amount = 0.0;
            if ($discountType['value_kind'] === 'percentage') {
                $amount = round(($base * (float) $discountType['value']) / 100.0, 2);
            } else {
                $amount = min($base, (float) $discountType['value']);
            }

            $appliedId = $this->discountRepository->applyDiscount(
                $orderId,
                (int) $discountType['discount_type_id'],
                (int) $benefit['client_benefit_id'],
                $base,
                $amount
            );

            return [
                'applied_discount_id' => $appliedId,
                'discount_name' => $discountType['name'],
                'discount_amount' => $amount,
                'summary' => $this->getPaymentSummary($orderId),
            ];
        }

        throw new BadRequestException("Неизвестный тип скидки: {$type}");
    }

    public function recordPayment(int $orderId, int $adminEmployeeId, array $data): array
    {
        $order = $this->orderRepository->findById($orderId);
        if (!$order) {
            throw new NotFoundException("Заказ №{$orderId} не найден");
        }

        if ($order['status_name'] === 'Отклонена') {
            throw new ConflictException('Нельзя принимать оплату по отклонённому заказу');
        }

        $summary = $this->getPaymentSummary($orderId);
        $due = $summary['balance_due'];

        if ($due <= 0.001) {
            throw new ConflictException('Заказ уже полностью оплачен');
        }

        $amount = isset($data['amount']) ? (float) $data['amount'] : $due;
        if ($amount <= 0) {
            throw new BadRequestException('Сумма платежа должна быть больше нуля');
        }

        if ($amount > $due + 0.001) {
            throw new BadRequestException("Сумма платежа ({$amount} руб.) превышает остаток к оплате ({$due} руб.)");
        }

        $methodId = (int) ($data['payment_method_id'] ?? 1); // 1 = Наличные, 2 = Карта
        $extRef = isset($data['external_reference']) ? trim($data['external_reference']) : null;

        $paymentId = $this->paymentRepository->recordPayment(
            $orderId,
            $methodId,
            $amount,
            $extRef
        );

        // Проверить, закрылся ли долг после этого платежа
        $updatedSummary = $this->getPaymentSummary($orderId);
        if ($updatedSummary['is_fully_paid']) {
            $this->orderRepository->updateStatus(
                $orderId,
                'Завершена',
                $adminEmployeeId,
                'Оплата принята в полном объёме. Заказ завершён и выдан'
            );
        }

        return [
            'payment_id' => $paymentId,
            'amount_paid' => $amount,
            'summary' => $this->getPaymentSummary($orderId),
        ];
    }
}
