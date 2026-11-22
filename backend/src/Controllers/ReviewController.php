<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Exceptions\NotFoundException;
use App\Services\ReviewService;

class ReviewController
{
    private ReviewService $reviewService;

    public function __construct()
    {
        $this->reviewService = new ReviewService();
    }

    public function store(Request $request): void
    {
        $orderId = (int) $request->getRouteParam('id');
        $result = $this->reviewService->addReview($orderId, $request);
        Response::json($result, 201);
    }

    public function show(Request $request): void
    {
        $orderId = (int) $request->getRouteParam('id');
        $review = $this->reviewService->getReview($orderId);
        if (!$review) {
            throw new NotFoundException("Отзыв к заказу №{$orderId} ещё не оставлен");
        }
        Response::json(['review' => $review]);
    }
}
