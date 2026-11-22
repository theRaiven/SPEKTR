<?php

namespace App\Services;

use App\Core\Request;
use App\Core\Exceptions\BadRequestException;
use App\Core\Exceptions\ConflictException;
use App\Core\Exceptions\ForbiddenException;
use App\Core\Exceptions\NotFoundException;
use App\Repositories\OrderRepository;
use App\Repositories\MasterRepository;
use App\Repositories\DeviceRepository;
use App\Repositories\TariffRepository;
use App\Repositories\PartRepository;
use App\Repositories\ReviewRepository;

class OrderService
{
    private OrderRepository $orderRepository;
    private MasterRepository $masterRepository;
    private DeviceRepository $deviceRepository;
    private TariffRepository $tariffRepository;
    private PartRepository $partRepository;
    private FinancialService $financialService;
    private ReviewRepository $reviewRepository;

    public function __construct()
    {
        $this->orderRepository = new OrderRepository();
        $this->masterRepository = new MasterRepository();
        $this->deviceRepository = new DeviceRepository();
        $this->tariffRepository = new TariffRepository();
        $this->partRepository = new PartRepository();
        $this->financialService = new FinancialService();
        $this->reviewRepository = new ReviewRepository();
    }

    public function getOrders(Request $request): array
    {
        $role = $request->getUserRole();
        $status = $request->getQuery('status');

        if ($role === 'client') {
            $clientId = $request->getClientId();
            return $this->orderRepository->findAll($clientId, null, $status);
        } elseif ($role === 'master') {
            $masterId = $request->getEmployeeId();
            return $this->orderRepository->findAll(null, $masterId, $status);
        } elseif ($role === 'admin') {
            return $this->orderRepository->findAll(null, null, $status);
        }

        throw new ForbiddenException();
    }

    public function getOrder(int $orderId, Request $request): array
    {
        $order = $this->orderRepository->findById($orderId);
        if (!$order) {
            throw new NotFoundException("Заказ №{$orderId} не найден");
        }

        // Проверка прав доступа
        $role = $request->getUserRole();
        if ($role === 'client') {
            if ((int) $order['client_id'] !== $request->getClientId()) {
                throw new ForbiddenException('Вы не можете просматривать чужой заказ');
            }
        } elseif ($role === 'master') {
            $assignment = $this->orderRepository->getActiveAssignment($orderId);
            if (!$assignment || (int) $assignment['employee_id'] !== $request->getEmployeeId()) {
                throw new ForbiddenException('Вы можете просматривать только назначенные вам заказы');
            }
        }

        $order['payment_summary'] = $this->financialService->getPaymentSummary($orderId);
        $order['history'] = $this->orderRepository->getStatusHistory($orderId);
        $order['review'] = $this->reviewRepository->getReviewForOrder($orderId);

        return $order;
    }

    public function createOrder(Request $request): array
    {
        $clientId = $request->getClientId();
        if (!$clientId) {
            throw new ForbiddenException('Создавать заявки может только авторизованный клиент');
        }

        $body = $request->getBody();
        $fault = trim($body['fault_description'] ?? '');
        if ($fault === '') {
            throw new BadRequestException('Укажите описание неисправности');
        }

        $deviceId = isset($body['device_id']) ? (int) $body['device_id'] : null;

        // Если переданы параметры нового устройства — регистрируем его
        if (!$deviceId) {
            $typeName = trim($body['device_type'] ?? '');
            $brandName = trim($body['brand'] ?? '');
            $modelName = trim($body['model'] ?? '');
            $serial = isset($body['serial_number']) ? trim($body['serial_number']) : null;

            if ($typeName === '' || $brandName === '' || $modelName === '') {
                throw new BadRequestException('Укажите существующий device_id или заполните данные нового устройства: device_type, brand, model');
            }

            $device = $this->deviceRepository->createDevice($typeName, $brandName, $modelName, $serial);
            $deviceId = $device['device_id'];
        }

        $plannedVisit = isset($body['planned_visit_at']) ? trim($body['planned_visit_at']) : null;
        $priority = isset($body['queue_priority']) ? (int) $body['queue_priority'] : 0;

        $orderId = $this->orderRepository->createOrder(
            $clientId,
            $deviceId,
            $fault,
            $plannedVisit,
            $priority
        );

        return [
            'order_id' => $orderId,
            'status' => 'Новая',
            'message' => 'Заявка успешно создана и ожидает подтверждения администратором',
        ];
    }

    public function acceptOrder(int $orderId, Request $request): array
    {
        $adminId = $request->getEmployeeId();
        $order = $this->orderRepository->findById($orderId);
        if (!$order) {
            throw new NotFoundException("Заказ №{$orderId} не найден");
        }

        if ($order['status_name'] !== 'Новая') {
            throw new ConflictException("Принять можно только заявку в статусе 'Новая'. Текущий статус: '{$order['status_name']}'");
        }

        $this->orderRepository->updateStatus($orderId, 'Принята', $adminId, 'Заявка принята администратором');

        return [
            'order_id' => $orderId,
            'status' => 'Принята',
            'message' => 'Заявка принята в сервис',
        ];
    }

    public function rejectOrder(int $orderId, Request $request): array
    {
        $adminId = $request->getEmployeeId();
        $order = $this->orderRepository->findById($orderId);
        if (!$order) {
            throw new NotFoundException("Заказ №{$orderId} не найден");
        }

        if ($order['status_name'] !== 'Новая') {
            throw new ConflictException("Отклонить можно только заявку в статусе 'Новая'. Текущий статус: '{$order['status_name']}'");
        }

        $body = $request->getBody();
        $reason = trim($body['reason'] ?? '');
        if ($reason === '') {
            throw new BadRequestException('Укажите причину отказа в обслуживании (поле reason)');
        }

        $this->orderRepository->rejectOrder($orderId, $adminId, $reason);

        return [
            'order_id' => $orderId,
            'status' => 'Отклонена',
            'reason' => $reason,
            'message' => 'Заявка отклонена с сохранением причины отказа',
        ];
    }

    public function assignMaster(int $orderId, Request $request): array
    {
        $adminId = $request->getEmployeeId();
        $order = $this->orderRepository->findById($orderId);
        if (!$order) {
            throw new NotFoundException("Заказ №{$orderId} не найден");
        }

        if (!in_array($order['status_name'], ['Принята', 'Передана мастеру'])) {
            throw new ConflictException("Назначить мастера можно только для принятой заявки. Текущий статус: '{$order['status_name']}'");
        }

        $body = $request->getBody();
        $masterId = (int) ($body['master_id'] ?? 0);
        if (!$masterId) {
            throw new BadRequestException('Укажите master_id назначаемого мастера');
        }

        $master = $this->masterRepository->findById($masterId);
        if (!$master) {
            throw new NotFoundException("Мастер №{$masterId} не найден");
        }

        // Проверка квалификации мастера под вид техники заказа
        $deviceTypeId = (int) $order['device_type_id'];
        if (!$this->masterRepository->isQualifiedForDeviceType($masterId, $deviceTypeId)) {
            throw new ConflictException("Мастер {$master['last_name']} {$master['first_name']} не имеет действующей квалификации для ремонта категории '{$order['device_type_name']}'");
        }

        $assignmentId = $this->orderRepository->assignMaster($orderId, $masterId, $adminId);

        return [
            'order_id' => $orderId,
            'assignment_id' => $assignmentId,
            'master_id' => $masterId,
            'master_name' => "{$master['last_name']} {$master['first_name']}",
            'status' => 'Передана мастеру',
        ];
    }

    public function updateStatus(int $orderId, Request $request): array
    {
        $role = $request->getUserRole();
        $empId = $request->getEmployeeId();

        $order = $this->orderRepository->findById($orderId);
        if (!$order) {
            throw new NotFoundException("Заказ №{$orderId} не найден");
        }

        // Если мастер — он может менять статус только своего заказа
        if ($role === 'master') {
            $assignment = $this->orderRepository->getActiveAssignment($orderId);
            if (!$assignment || (int) $assignment['employee_id'] !== $empId) {
                throw new ForbiddenException('Мастер не может изменять статус чужого заказа');
            }
        }

        $body = $request->getBody();
        $newStatus = trim($body['status'] ?? '');
        $note = isset($body['note']) ? trim($body['note']) : null;

        if ($newStatus === '') {
            throw new BadRequestException('Укажите новый статус (поле status)');
        }

        $currentStatus = $order['status_name'];

        // Допустимые переходы для мастера
        $allowedTransitions = [
            'Передана мастеру' => ['Диагностика'],
            'Диагностика' => ['В работе'],
            'В работе' => ['Ожидание запчастей', 'Ожидает оплаты'],
            'Ожидание запчастей' => ['В работе'],
        ];

        // Мастеру запрещено вручную ставить "Завершена" или "Оплачено"
        if ($role === 'master' && in_array($newStatus, ['Завершена', 'Принята', 'Отклонена', 'Передана мастеру'])) {
            throw new ForbiddenException("Мастеру запрещено переводить заказ в статус '{$newStatus}'");
        }

        if ($role === 'master') {
            $validTargets = $allowedTransitions[$currentStatus] ?? [];
            if (!in_array($newStatus, $validTargets, true)) {
                throw new ConflictException("Недопустимый переход статуса из '{$currentStatus}' в '{$newStatus}'. Допустимо: " . implode(', ', $validTargets));
            }
        }

        $this->orderRepository->updateStatus($orderId, $newStatus, $empId, $note);

        return [
            'order_id' => $orderId,
            'previous_status' => $currentStatus,
            'status' => $newStatus,
            'message' => "Статус заказа успешно изменён на '{$newStatus}'",
        ];
    }

    public function addWork(int $orderId, Request $request): array
    {
        $order = $this->orderRepository->findById($orderId);
        if (!$order) {
            throw new NotFoundException("Заказ №{$orderId} не найден");
        }

        $assignment = $this->orderRepository->getActiveAssignment($orderId);
        if (!$assignment) {
            throw new ConflictException('На заказ ещё не назначен мастер');
        }

        $body = $request->getBody();
        $workTypeId = (int) ($body['work_type_id'] ?? 0);
        if (!$workTypeId) {
            throw new BadRequestException('Укажите work_type_id');
        }

        $tariff = $this->tariffRepository->getActiveTariffForWorkType($workTypeId);
        if (!$tariff) {
            throw new NotFoundException("Действующий тариф для работы №{$workTypeId} не найден");
        }

        $workId = $this->tariffRepository->addWorkToOrder((int) $assignment['assignment_id'], (int) $tariff['tariff_id']);

        return [
            'order_work_id' => $workId,
            'work_type_id' => $workTypeId,
            'price' => (float) $tariff['price'],
            'message' => 'Работа успешно добавлена к заказу',
        ];
    }

    public function addPart(int $orderId, Request $request): array
    {
        $order = $this->orderRepository->findById($orderId);
        if (!$order) {
            throw new NotFoundException("Заказ №{$orderId} не найден");
        }

        $body = $request->getBody();
        $partId = (int) ($body['part_id'] ?? 0);
        $quantity = (int) ($body['quantity'] ?? 1);

        if (!$partId || $quantity <= 0) {
            throw new BadRequestException('Укажите part_id и положительное количество quantity');
        }

        $tariff = $this->partRepository->getActiveTariffForPart($partId);
        if (!$tariff) {
            throw new NotFoundException("Действующий тариф для запчасти №{$partId} не найден");
        }

        $orderPartId = $this->partRepository->addPartToOrder($orderId, $partId, (int) $tariff['part_tariff_id'], $quantity);

        return [
            'order_part_id' => $orderPartId,
            'part_id' => $partId,
            'quantity' => $quantity,
            'unit_price' => (float) $tariff['unit_price'],
            'message' => 'Запчасть успешно добавлена к заказу и списана со склада',
        ];
    }
}
