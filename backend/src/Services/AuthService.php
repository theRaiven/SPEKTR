<?php

namespace App\Services;

use App\Core\Exceptions\BadRequestException;
use App\Core\Exceptions\ConflictException;
use App\Core\Exceptions\UnauthorizedException;
use App\Repositories\UserRepository;
use App\Repositories\TokenRepository;

class AuthService
{
    private UserRepository $userRepository;
    private TokenRepository $tokenRepository;

    public function __construct()
    {
        $this->userRepository = new UserRepository();
        $this->tokenRepository = new TokenRepository();
    }

    public function registerClient(array $data): array
    {
        $login = trim($data['login'] ?? '');
        $password = $data['password'] ?? '';
        $lastName = trim($data['last_name'] ?? '');
        $firstName = trim($data['first_name'] ?? '');
        $middleName = isset($data['middle_name']) ? trim($data['middle_name']) : null;
        $phone = trim($data['phone'] ?? '');
        $email = isset($data['email']) ? trim($data['email']) : null;

        if ($login === '' || $password === '' || $lastName === '' || $firstName === '' || $phone === '') {
            throw new BadRequestException('Заполните все обязательные поля: login, password, last_name, first_name, phone');
        }

        if (strlen($password) < 6) {
            throw new BadRequestException('Длина пароля должна быть не менее 6 символов');
        }

        if ($this->userRepository->findByLogin($login)) {
            throw new ConflictException("Пользователь с логином '{$login}' уже зарегистрирован");
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $user = $this->userRepository->createClientAccount(
            $login,
            $passwordHash,
            $lastName,
            $firstName,
            $middleName,
            $phone,
            $email
        );

        // Сразу авторизовать и выдать токен
        $token = bin2hex(random_bytes(32));
        $this->tokenRepository->createToken($user['account_id'], $token);

        return [
            'token' => $token,
            'user' => [
                'account_id' => $user['account_id'],
                'login' => $user['login'],
                'role' => 'client',
                'client_id' => $user['client_id'],
                'name' => "{$lastName} {$firstName}",
            ],
        ];
    }

    public function login(array $data): array
    {
        $login = trim($data['login'] ?? '');
        $password = $data['password'] ?? '';

        if ($login === '' || $password === '') {
            throw new BadRequestException('Укажите логин и пароль');
        }

        $user = $this->userRepository->findByLogin($login);
        if (!$user) {
            throw new UnauthorizedException('Неверный логин или пароль');
        }

        if (!$user['is_active']) {
            throw new UnauthorizedException('Учётная запись отключена. Обратитесь к администратору');
        }

        if (!password_verify($password, $user['password_hash'])) {
            throw new UnauthorizedException('Неверный логин или пароль');
        }

        $token = bin2hex(random_bytes(32));
        $this->tokenRepository->createToken((int) $user['account_id'], $token);

        $name = $user['role'] === 'client'
            ? trim("{$user['client_last_name']} {$user['client_first_name']}")
            : trim("{$user['emp_last_name']} {$user['emp_first_name']}");

        return [
            'token' => $token,
            'user' => [
                'account_id' => (int) $user['account_id'],
                'login' => $user['login'],
                'role' => $user['role'],
                'client_id' => $user['client_id'] ? (int) $user['client_id'] : null,
                'employee_id' => $user['employee_id'] ? (int) $user['employee_id'] : null,
                'name' => $name,
            ],
        ];
    }

    public function logout(string $token): void
    {
        $this->tokenRepository->revokeToken($token);
    }
}
