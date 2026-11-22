<?php

namespace App\Repositories;

use App\Core\Database;

class DeviceRepository
{
    public function findClientDevices(int $clientId): array
    {
        $sql = "SELECT d.device_id, d.serial_number, d.purchase_date,
                       m.model_id, m.model_name,
                       b.brand_id, b.name AS brand_name,
                       t.device_type_id, t.name AS device_type_name
                FROM devices d
                JOIN device_models m ON m.model_id = d.model_id
                JOIN brands b ON b.brand_id = m.brand_id
                JOIN device_types t ON t.device_type_id = m.device_type_id
                WHERE d.device_id IN (
                    SELECT DISTINCT ro.device_id FROM repair_orders ro WHERE ro.client_id = :client_id
                )
                ORDER BY d.device_id DESC";

        return Database::query($sql, ['client_id' => $clientId]);
    }

    public function findDeviceById(int $deviceId): ?array
    {
        $sql = "SELECT d.device_id, d.serial_number, d.purchase_date,
                       m.model_id, m.model_name,
                       b.brand_id, b.name AS brand_name,
                       t.device_type_id, t.name AS device_type_name
                FROM devices d
                JOIN device_models m ON m.model_id = d.model_id
                JOIN brands b ON b.brand_id = m.brand_id
                JOIN device_types t ON t.device_type_id = m.device_type_id
                WHERE d.device_id = :device_id";

        return Database::queryOne($sql, ['device_id' => $deviceId]);
    }

    public function createDevice(
        string $typeName,
        string $brandName,
        string $modelName,
        ?string $serialNumber = null,
        ?string $purchaseDate = null
    ): array {
        Database::beginTransaction();
        try {
            // 1. Найти или создать тип устройства
            $typeRow = Database::queryOne("SELECT device_type_id FROM device_types WHERE LOWER(name) = LOWER(:name)", ['name' => $typeName]);
            if ($typeRow) {
                $typeId = (int) $typeRow['device_type_id'];
            } else {
                $typeId = Database::insertAndGetId(
                    "INSERT INTO device_types (name) VALUES (:name)",
                    ['name' => $typeName],
                    'device_type_id'
                );
            }

            // 2. Найти или создать бренд
            $brandRow = Database::queryOne("SELECT brand_id FROM brands WHERE LOWER(name) = LOWER(:name)", ['name' => $brandName]);
            if ($brandRow) {
                $brandId = (int) $brandRow['brand_id'];
            } else {
                $brandId = Database::insertAndGetId(
                    "INSERT INTO brands (name) VALUES (:name)",
                    ['name' => $brandName],
                    'brand_id'
                );
            }

            // 3. Найти или создать модель
            $modelRow = Database::queryOne(
                "SELECT model_id FROM device_models WHERE brand_id = :brand_id AND LOWER(model_name) = LOWER(:model_name)",
                ['brand_id' => $brandId, 'model_name' => $modelName]
            );
            if ($modelRow) {
                $modelId = (int) $modelRow['model_id'];
            } else {
                $modelId = Database::insertAndGetId(
                    "INSERT INTO device_models (device_type_id, brand_id, model_name) VALUES (:type_id, :brand_id, :model_name)",
                    [
                        'type_id' => $typeId,
                        'brand_id' => $brandId,
                        'model_name' => $modelName,
                    ],
                    'model_id'
                );
            }

            // 4. Создать экземпляр устройства
            $deviceId = Database::insertAndGetId(
                "INSERT INTO devices (model_id, serial_number, purchase_date) VALUES (:model_id, :serial, :pdate)",
                [
                    'model_id' => $modelId,
                    'serial' => $serialNumber ?: null,
                    'pdate' => $purchaseDate ?: null,
                ],
                'device_id'
            );

            Database::commit();

            return [
                'device_id' => (int) $deviceId,
                'device_type' => $typeName,
                'brand' => $brandName,
                'model' => $modelName,
                'serial_number' => $serialNumber,
            ];
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public function listDeviceTypes(): array
    {
        return Database::query("SELECT device_type_id, name FROM device_types ORDER BY name ASC");
    }

    public function listBrands(): array
    {
        return Database::query("SELECT brand_id, name FROM brands ORDER BY name ASC");
    }
}
