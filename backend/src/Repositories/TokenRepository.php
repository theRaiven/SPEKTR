<?php

namespace App\Repositories;

use App\Core\Database;

class TokenRepository
{
    public function createToken(int $accountId, string $token, int $ttlHours = 72): void
    {
        $tokenHash = hash('sha256', $token);
        $expiresAt = date('Y-m-d H:i:sP', strtotime("+{$ttlHours} hours"));

        $sql = "INSERT INTO auth_tokens (account_id, token_hash, expires_at, created_at)
                VALUES (:account_id, :token_hash, :expires_at, now())";

        Database::execute($sql, [
            'account_id' => $accountId,
            'token_hash' => $tokenHash,
            'expires_at' => $expiresAt,
        ]);
    }

    public function findUserByToken(string $token): ?array
    {
        $tokenHash = hash('sha256', $token);

        $sql = "SELECT u.account_id, u.login, u.is_active, r.name AS role, u.client_id, u.employee_id,
                       c.first_name AS client_first_name, c.last_name AS client_last_name, c.phone AS client_phone,
                       e.first_name AS employee_first_name, e.last_name AS employee_last_name, e.phone AS employee_phone
                FROM auth_tokens t
                JOIN user_accounts u ON u.account_id = t.account_id
                JOIN account_roles r ON r.role_id = u.role_id
                LEFT JOIN clients c ON c.client_id = u.client_id
                LEFT JOIN employees e ON e.employee_id = u.employee_id
                WHERE t.token_hash = :token_hash
                  AND t.expires_at > now()
                  AND u.is_active = true";

        return Database::queryOne($sql, ['token_hash' => $tokenHash]);
    }

    public function revokeToken(string $token): void
    {
        $tokenHash = hash('sha256', $token);
        $sql = "DELETE FROM auth_tokens WHERE token_hash = :token_hash";
        Database::execute($sql, ['token_hash' => $tokenHash]);
    }

    public function revokeAllForAccount(int $accountId): void
    {
        $sql = "DELETE FROM auth_tokens WHERE account_id = :account_id";
        Database::execute($sql, ['account_id' => $accountId]);
    }
}
