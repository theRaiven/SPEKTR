<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Exceptions\BadRequestException;
use App\Services\FinancialService;
use App\Repositories\PaymentRepository;

class PaymentController
{
    private FinancialService $financialService;
    private PaymentRepository $paymentRepository;

    public function __construct()
    {
        $this->financialService = new FinancialService();
        $this->paymentRepository = new PaymentRepository();
    }

    public function payment(Request $request): void
    {
        $orderId = (int) $request->getRouteParam('id');
        $summary = $this->financialService->getPaymentSummary($orderId);
        Response::json(['payment' => $summary]);
    }

    public function discounts(Request $request): void
    {
        $orderId = (int) $request->getRouteParam('id');
        $clientId = $request->getClientId();
        if (!$clientId) {
            throw new BadRequestException('Применение скидок доступно только клиенту');
        }

        $result = $this->financialService->applyDiscount($orderId, $clientId, $request->getBody());
        Response::json($result, 201);
    }

    public function recordPayment(Request $request): void
    {
        $orderId = (int) $request->getRouteParam('id');
        $adminId = $request->getEmployeeId();

        $result = $this->financialService->recordPayment($orderId, $adminId, $request->getBody());
        Response::json($result, 201);
    }

    public function methods(Request $request): void
    {
        $methods = $this->paymentRepository->listPaymentMethods();
        Response::json(['methods' => $methods]);
    }
}
