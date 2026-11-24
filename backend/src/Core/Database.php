<?php

namespace App\Core;

use PDO;
use RuntimeException;

class Database
{
    private static ?PDO $connection = null;
    private static array $config = [];
    private static int $transactionLevel = 0;

    public static function init(array $config): void
    {
        self::$config = $config;
        self::$connection = null;
        self::$transactionLevel = 0;
    }

    public static function getConnection(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }

        if (empty(self::$config)) {
            $configPath = dirname(__DIR__, 2) . '/config/database.php';
            if (file_exists($configPath)) {
                self::$config = require $configPath;
            }
        }

        $driver = self::$config['driver'] ?? 'pgsql';

        if ($driver === 'pgsql') {
            $pg = self::$config['pgsql'] ?? [];
            $host = $pg['host'] ?? '127.0.0.1';
            $port = $pg['port'] ?? '5432';
            $dbname = $pg['database'] ?? 'spektr';
            $user = $pg['username'] ?? 'postgres';
            $password = $pg['password'] ?? '';
            $options = $pg['options'] ?? [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $dsn = "pgsql:host={$host};port={$port};dbname={$dbname}";
            try {
                self::$connection = new PDO($dsn, $user, $password, $options);
            } catch (\PDOException $e) {
                throw new RuntimeException("Не удалось подключиться к базе данных PostgreSQL ({$dsn}): " . $e->getMessage(), 0, $e);
            }
        } elseif ($driver === 'sqlite') {
            $sqlite = self::$config['sqlite'] ?? [];
            $path = $sqlite['database'] ?? ':memory:';
            $options = $sqlite['options'] ?? [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $dir = dirname($path);
            if (!file_exists($dir) && $path !== ':memory:') {
                @mkdir($dir, 0777, true);
            }

            self::$connection = new PDO("sqlite:{$path}", null, null, $options);
            self::$connection->exec('PRAGMA foreign_keys = ON;');
            self::$connection->sqliteCreateFunction('now', fn() => date('Y-m-d H:i:s'));
            self::$connection->sqliteCreateFunction('CURRENT_DATE', fn() => date('Y-m-d'));
        } else {
            throw new RuntimeException("Неподдерживаемый драйвер базы данных: {$driver}");
        }

        return self::$connection;
    }

    public static function beginTransaction(): bool
    {
        if (self::$transactionLevel === 0) {
            self::getConnection()->beginTransaction();
        }
        self::$transactionLevel++;
        return true;
    }

    public static function commit(): bool
    {
        if (self::$transactionLevel > 0) {
            self::$transactionLevel--;
            if (self::$transactionLevel === 0) {
                return self::getConnection()->commit();
            }
            return true;
        }
        return false;
    }

    public static function rollBack(): bool
    {
        if (self::$transactionLevel > 0) {
            self::$transactionLevel = 0;
            if (self::getConnection()->inTransaction()) {
                return self::getConnection()->rollBack();
            }
        }
        return false;
    }

    public static function inTransaction(): bool
    {
        return self::getConnection()->inTransaction();
    }

    public static function query(string $sql, array $params = []): array
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function queryOne(string $sql, array $params = []): ?array
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public static function insertAndGetId(string $sql, array $params = [], string $idColumn = 'id'): int|string
    {
        $pdo = self::getConnection();
        $driver = self::$config['driver'] ?? 'pgsql';

        if ($driver === 'pgsql') {
            if (!str_contains(strtoupper($sql), 'RETURNING')) {
                $sql .= " RETURNING {$idColumn}";
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row[$idColumn] ?? 0;
        }

        $cleanSql = preg_replace('/\s+RETURNING\s+[a-zA-Z0-9_]+/i', '', $sql);
        $stmt = $pdo->prepare($cleanSql);
        $stmt->execute($params);
        return $pdo->lastInsertId();
    }
}
