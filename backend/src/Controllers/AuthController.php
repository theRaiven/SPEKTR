<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

class AuthController
{
    private AuthService $authService;

    public function __construct()
    {
        $this->authService = new AuthService();
    }

    public function register(Request $request): void
    {
        $result = $this->authService->registerClient($request->getBody());
        Response::json($result, 201);
    }

    public function login(Request $request): void
    {
        $result = $this->authService->login($request->getBody());
        Response::json($result, 200);
    }

    public function logout(Request $request): void
    {
        $token = $request->getBearerToken();
        if ($token) {
            $this->authService->logout($token);
        }
        Response::json(['message' => 'Сессия успешно завершена']);
    }

    public function me(Request $request): void
    {
        $user = $request->getUser();
        Response::json(['user' => $user]);
    }
}
