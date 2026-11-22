<?php

namespace App\Repositories;

use App\Core\Database;

class UserRepository
{
    public function findByLogin(string $login): ?array
    {
        $sql = "SELECT u.account_id, u.login, u.password_hash, u.is_active, r.name AS role,
                       u.client_id, u.employee_id,
                       c.last_name AS client_last_name, c.first_name AS client_first_name, c.phone AS client_phone, c.email AS client_email,
                       e.last_name AS emp_last_name, e.first_name AS emp_first_name, e.phone AS emp_phone
                FROM user_accounts u
                JOIN account_roles r ON r.role_id = u.role_id
                LEFT JOIN clients c ON c.client_id = u.client_id
                LEFT JOIN employees e ON e.employee_id = u.employee_id
                WHERE LOWER(u.login) = LOWER(:login)";

        $user = Database::queryOne($sql, ['login' => $login]);
        if ($user) {
            $user['role'] = self::normalizeRole($user['role'] ?? '');
        }
        return $user;
    }

    public function findById(int $accountId): ?array
    {
        $sql = "SELECT u.account_id, u.login, u.is_active, r.name AS role,
                       u.client_id, u.employee_id, u.created_at,
                       c.last_name AS client_last_name, c.first_name AS client_first_name, c.middle_name AS client_middle_name,
                       c.phone AS client_phone, c.email AS client_email,
                       e.last_name AS emp_last_name, e.first_name AS emp_first_name, e.middle_name AS emp_middle_name,
                       e.phone AS emp_phone
                FROM user_accounts u
                JOIN account_roles r ON r.role_id = u.role_id
                LEFT JOIN clients c ON c.client_id = u.client_id
                LEFT JOIN employees e ON e.employee_id = u.employee_id
                WHERE u.account_id = :account_id";

        $user = Database::queryOne($sql, ['account_id' => $accountId]);
        if ($user) {
            $user['role'] = self::normalizeRole($user['role'] ?? '');
        }
        return $user;
    }

    public static function normalizeRole(string $role): string
    {
        $r = mb_strtolower(trim($role));
        return match ($r) {
            'клиент', 'client' => 'client',
            'мастер', 'master' => 'master',
            'администратор', 'админ', 'admin' => 'admin',
            default => $r,
        };
    }

    public function createClientAccount(
        string $login,
        string $passwordHash,
        string $lastName,
        string $firstName,
        ?string $middleName,
        string $phone,
        ?string $email
    ): array {
        Database::beginTransaction();
        try {
            // 1. Создать карточку клиента
            $clientId = Database::insertAndGetId(
                "INSERT INTO clients (last_name, first_name, middle_name, phone, email, created_at)
                 VALUES (:last_name, :first_name, :middle_name, :phone, :email, now())",
                [
                    'last_name' => $lastName,
                    'first_name' => $firstName,
                    'middle_name' => $middleName,
                    'phone' => $phone,
                    'email' => $email,
                ],
                'client_id'
            );

            // 2. Получить role_id для 'client'
            $roleRow = Database::queryOne("SELECT role_id FROM account_roles WHERE LOWER(name) IN ('client', 'клиент') OR owner_kind = 'client' ORDER BY role_id ASC LIMIT 1");
            if (!$roleRow) {
                throw new \RuntimeException("Роль 'client' не найдена в базе данных");
            }
            $roleId = (int) $roleRow['role_id'];

            // 3. Создать учётную запись
            $accountId = Database::insertAndGetId(
                "INSERT INTO user_accounts (login, password_hash, role_id, client_id, employee_id, is_active, created_at)
                 VALUES (:login, :password_hash, :role_id, :client_id, NULL, true, now())",
                [
                    'login' => $login,
                    'password_hash' => $passwordHash,
                    'role_id' => $roleId,
                    'client_id' => $clientId,
                ],
                'account_id'
            );

            Database::commit();

            return [
                'account_id' => (int) $accountId,
                'client_id' => (int) $clientId,
                'login' => $login,
                'role' => 'client',
                'first_name' => $firstName,
                'last_name' => $lastName,
            ];
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public function createEmployeeAccount(
        string $login,
        string $passwordHash,
        string $roleName,
        string $lastName,
        string $firstName,
        ?string $middleName,
        ?string $phone,
        array $specializationIds = []
    ): array {
        Database::beginTransaction();
        try {
            // 1. Создать сотрудника
            $employeeId = Database::insertAndGetId(
                "INSERT INTO employees (last_name, first_name, middle_name, phone, hired_on)
                 VALUES (:last_name, :first_name, :middle_name, :phone, CURRENT_DATE)",
                [
                    'last_name' => $lastName,
                    'first_name' => $firstName,
                    'middle_name' => $middleName,
                    'phone' => $phone,
                ],
                'employee_id'
            );

            // 2. Роль
            $roleRow = Database::queryOne(
                "SELECT role_id FROM account_roles 
                 WHERE LOWER(name) = LOWER(:role) 
                    OR (LOWER(name) = 'мастер' AND :role = 'master') 
                    OR (LOWER(name) = 'администратор' AND :role = 'admin') 
                 LIMIT 1", 
                ['role' => $roleName]
            );
            if (!$roleRow) {
                throw new \RuntimeException("Роль '{$roleName}' не найдена в базе данных");
            }
            $roleId = (int) $roleRow['role_id'];

            // 3. Если мастер — добавить в таблицу masters и привязать специализации
            if ($roleName === 'master') {
                Database::execute("INSERT INTO masters (employee_id) VALUES (:emp_id)", ['emp_id' => $employeeId]);

                // Дефолтный уровень квалификации - Специалист (ранг 2)
                $levelRow = Database::queryOne("SELECT skill_level_id FROM skill_levels ORDER BY level_rank ASC LIMIT 1");
                $skillLevelId = $levelRow ? $levelRow['skill_level_id'] : 1;

                // Первый администратор для записи
                $adminEmpRow = Database::queryOne("SELECT employee_id FROM user_accounts WHERE employee_id IS NOT NULL LIMIT 1");
                $recordedBy = $adminEmpRow ? $adminEmpRow['employee_id'] : $employeeId;

                foreach ($specializationIds as $specId) {
                    Database::execute(
                        "INSERT INTO master_specializations (employee_id, specialization_id, skill_level_id, valid_from, recorded_by_employee_id)
                         VALUES (:emp_id, :spec_id, :skill_level_id, CURRENT_DATE, :rec_by)",
                        [
                            'emp_id' => $employeeId,
                            'spec_id' => $specId,
                            'skill_level_id' => $skillLevelId,
                            'rec_by' => $recordedBy,
                        ]
                    );
                }
            }

            // 4. Учётная запись
            $accountId = Database::insertAndGetId(
                "INSERT INTO user_accounts (login, password_hash, role_id, client_id, employee_id, is_active, created_at)
                 VALUES (:login, :password_hash, :role_id, NULL, :emp_id, true, now())",
                [
                    'login' => $login,
                    'password_hash' => $passwordHash,
                    'role_id' => $roleId,
                    'emp_id' => $employeeId,
                ],
                'account_id'
            );

            Database::commit();

            return [
                'account_id' => (int) $accountId,
                'employee_id' => (int) $employeeId,
                'login' => $login,
                'role' => $roleName,
            ];
        } catch (\Throwable $e) {
            Database::rollBack();
            throw $e;
        }
    }

    public function updateClientProfile(int $clientId, ?string $phone, ?string $email, ?string $lastName, ?string $firstName, ?string $middleName): void
    {
        $fields = [];
        $params = ['client_id' => $clientId];

        if ($phone !== null) {
            $fields[] = 'phone = :phone';
            $params['phone'] = $phone;
        }
        if ($email !== null) {
            $fields[] = 'email = :email';
            $params['email'] = $email;
        }
        if ($lastName !== null) {
            $fields[] = 'last_name = :last_name';
            $params['last_name'] = $lastName;
        }
        if ($firstName !== null) {
            $fields[] = 'first_name = :first_name';
            $params['first_name'] = $firstName;
        }
        if ($middleName !== null) {
            $fields[] = 'middle_name = :middle_name';
            $params['middle_name'] = $middleName;
        }

        if (!empty($fields)) {
            $sql = "UPDATE clients SET " . implode(', ', $fields) . " WHERE client_id = :client_id";
            Database::execute($sql, $params);
        }
    }
}
