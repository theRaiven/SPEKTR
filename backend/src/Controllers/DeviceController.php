<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Exceptions\BadRequestException;
use App\Repositories\DeviceRepository;

class DeviceController
{
    private DeviceRepository $deviceRepository;

    public function __construct()
    {
        $this->deviceRepository = new DeviceRepository();
    }

    public function index(Request $request): void
    {
        $clientId = $request->getClientId();
        if (!$clientId) {
            // Для сотрудников или админа можно вернуть пустой список или все
            $devices = [];
        } else {
            $devices = $this->deviceRepository->findClientDevices($clientId);
        }
        Response::json(['devices' => $devices]);
    }

    public function store(Request $request): void
    {
        $body = $request->getBody();
        $typeName = trim($body['device_type'] ?? '');
        $brandName = trim($body['brand'] ?? '');
        $modelName = trim($body['model'] ?? '');
        $serial = isset($body['serial_number']) ? trim($body['serial_number']) : null;
        $purchaseDate = isset($body['purchase_date']) ? trim($body['purchase_date']) : null;

        if ($typeName === '' || $brandName === '' || $modelName === '') {
            throw new BadRequestException('Поля device_type, brand и model обязательны');
        }

        $device = $this->deviceRepository->createDevice($typeName, $brandName, $modelName, $serial, $purchaseDate);
        Response::json(['device' => $device], 201);
    }

    public function types(Request $request): void
    {
        $types = $this->deviceRepository->listDeviceTypes();
        Response::json(['types' => $types]);
    }

    public function brands(Request $request): void
    {
        $brands = $this->deviceRepository->listBrands();
        Response::json(['brands' => $brands]);
    }
}
