<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Core\Exceptions\BadRequestException;
use App\Core\Exceptions\ConflictException;
use App\Repositories\UserRepository;
use App\Repositories\TariffRepository;
use App\Repositories\PartRepository;

class StaffController
{
    private UserRepository $userRepository;
    private TariffRepository $tariffRepository;
    private PartRepository $partRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
        $this->tariffRepository = new TariffRepository();
        $this->partRepository = new PartRepository();
    }

    public function store(Request $request): void
    {
        $body = $request->getBody();
        $login = trim($body['login'] ?? '');
        $password = $body['password'] ?? '';
        $roleName = trim($body['role'] ?? 'master'); // 'master' или 'admin'
        $lastName = trim($body['last_name'] ?? '');
        $firstName = trim($body['first_name'] ?? '');
        $middleName = isset($body['middle_name']) ? trim($body['middle_name']) : null;
        $phone = isset($body['phone']) ? trim($body['phone']) : null;
        $specs = $body['specialization_ids'] ?? [];

        if ($login === '' || $password === '' || $lastName === '' || $firstName === '') {
            throw new BadRequestException('Поля login, password, last_name, first_name обязательны');
        }

        if (!in_array($roleName, ['master', 'admin'], true)) {
            throw new BadRequestException("Недопустимая роль '{$roleName}'. Разрешены: master, admin");
        }

        if ($this->userRepository->findByLogin($login)) {
            throw new ConflictException("Пользователь с логином '{$login}' уже существует");
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $result = $this->userRepository->createEmployeeAccount(
            $login,
            $passwordHash,
            $roleName,
            $lastName,
            $firstName,
            $middleName,
            $phone,
            (array) $specs
        );

        Response::json([
            'message' => 'Сотрудник успешно создан',
            'employee' => $result,
        ], 201);
    }

    public function workTypes(Request $request): void
    {
        $types = $this->tariffRepository->listWorkTypes();
        Response::json(['work_types' => $types]);
    }

    public function parts(Request $request): void
    {
        $parts = $this->partRepository->listParts();
        Response::json(['parts' => $parts]);
    }
}
