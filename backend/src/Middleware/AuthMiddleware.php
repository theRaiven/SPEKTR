<?php

namespace App\Middleware;

use App\Core\Request;
use App\Core\Exceptions\UnauthorizedException;
use App\Core\Exceptions\ForbiddenException;
use App\Repositories\TokenRepository;

class AuthMiddleware
{
    private static ?TokenRepository $tokenRepository = null;

    private static function getTokenRepository(): TokenRepository
    {
        if (self::$tokenRepository === null) {
            self::$tokenRepository = new TokenRepository();
        }
        return self::$tokenRepository;
    }

    public static function authenticate(Request $request): void
    {
        $token = $request->getBearerToken();
        if (!$token) {
            throw new UnauthorizedException('Токен авторизации отсутствует. Передайте Authorization: Bearer <token>');
        }

        $user = self::getTokenRepository()->findUserByToken($token);
        if (!$user) {
            throw new UnauthorizedException('Токен недействителен или срок его действия истёк');
        }

        $request->setUser($user);
    }

    public static function requireRole(Request $request, array $allowedRoles): void
    {
        self::authenticate($request);

        $role = $request->getUserRole();
        if (!$role || !in_array($role, $allowedRoles, true)) {
            throw new ForbiddenException('Недостаточно прав для выполнения данной операции. Доступно ролям: ' . implode(', ', $allowedRoles));
        }
    }
}
