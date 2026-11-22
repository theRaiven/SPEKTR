<?php

namespace App\Services;

use App\Core\Request;
use App\Core\Exceptions\BadRequestException;
use App\Core\Exceptions\ConflictException;
use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Repositories\OrderRepository;
use App\Repositories\TariffRepository;
use App\Repositories\ReviewRepository;

class ReviewService
{
    private OrderRepository $orderRepository;
    private TariffRepository $tariffRepository;
    private ReviewRepository $reviewRepository;
    private FinancialService $financialService;

    public function __construct()
    {
        $this->orderRepository = new OrderRepository();
        $this->tariffRepository = new TariffRepository();
        $this->reviewRepository = new ReviewRepository();
        $this->financialService = new FinancialService();
    }

    public function addReview(int $orderId, Request $request): array
    {
        $order = $this->orderRepository->findById($orderId);
        if (!$order) {
            throw new NotFoundException("Заказ №{$orderId} не найден");
        }

        $clientId = $request->getClientId();
        if ((int) $order['client_id'] !== $clientId) {
            throw new ForbiddenException('Отзыв может оставить только заказчик этого ремонта');
        }

        // Проверить, что заказ завершен
        if ($order['status_name'] !== 'Завершена') {
            throw new ConflictException("Отзыв можно оставить только после завершения и выдачи заказа. Текущий статус: '{$order['status_name']}'");
        }

        // Проверить полную оплату
        $summary = $this->financialService->getPaymentSummary($orderId);
        if (!$summary['is_fully_paid']) {
            throw new ConflictException('Отзыв доступен только после полной оплаты заказа');
        }

        $body = $request->getBody();
        $rating = (int) ($body['rating'] ?? 0);
        $comment = isset($body['comment']) ? trim($body['comment']) : null;

        if ($rating < 1 || $rating > 5) {
            throw new BadRequestException('Оценка должна быть целым числом от 1 до 5');
        }

        // Найти выполненную работу по заказу
        $works = $this->tariffRepository->getOrderWorks($orderId);
        if (empty($works)) {
            throw new ConflictException('По заказу не зафиксировано выполненных работ для привязки отзыва');
        }

        $workId = (int) $works[0]['order_work_id'];

        if ($this->reviewRepository->getReviewForWork($workId)) {
            throw new ConflictException('Отзыв по данному заказу уже был оставлен ранее');
        }

        $accountId = $request->getUserId();
        $this->reviewRepository->createReview($workId, $accountId, $rating, $comment);

        return [
            'order_id' => $orderId,
            'order_work_id' => $workId,
            'rating' => $rating,
            'comment' => $comment,
            'message' => 'Спасибо! Ваш отзыв успешно сохранён',
        ];
    }

    public function getReview(int $orderId): ?array
    {
        return $this->reviewRepository->getReviewForOrder($orderId);
    }
}
