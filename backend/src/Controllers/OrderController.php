<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\OrderService;

class OrderController
{
    private OrderService $orderService;

    public function __construct()
    {
        $this->orderService = new OrderService();
    }

    public function index(Request $request): void
    {
        $orders = $this->orderService->getOrders($request);
        Response::json(['orders' => $orders]);
    }

    public function show(Request $request): void
    {
        $id = (int) $request->getRouteParam('id');
        $order = $this->orderService->getOrder($id, $request);
        Response::json(['order' => $order]);
    }

    public function store(Request $request): void
    {
        $result = $this->orderService->createOrder($request);
        Response::json($result, 201);
    }

    public function accept(Request $request): void
    {
        $id = (int) $request->getRouteParam('id');
        $result = $this->orderService->acceptOrder($id, $request);
        Response::json($result);
    }

    public function reject(Request $request): void
    {
        $id = (int) $request->getRouteParam('id');
        $result = $this->orderService->rejectOrder($id, $request);
        Response::json($result);
    }

    public function assign(Request $request): void
    {
        $id = (int) $request->getRouteParam('id');
        $result = $this->orderService->assignMaster($id, $request);
        Response::json($result);
    }

    public function updateStatus(Request $request): void
    {
        $id = (int) $request->getRouteParam('id');
        $result = $this->orderService->updateStatus($id, $request);
        Response::json($result);
    }

    public function history(Request $request): void
    {
        $id = (int) $request->getRouteParam('id');
        $order = $this->orderService->getOrder($id, $request);
        Response::json(['history' => $order['history'] ?? []]);
    }

    public function addWork(Request $request): void
    {
        $id = (int) $request->getRouteParam('id');
        $result = $this->orderService->addWork($id, $request);
        Response::json($result, 201);
    }

    public function addPart(Request $request): void
    {
        $id = (int) $request->getRouteParam('id');
        $result = $this->orderService->addPart($id, $request);
        Response::json($result, 201);
    }
}
