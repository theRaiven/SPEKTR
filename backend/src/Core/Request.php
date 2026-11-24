<?php

namespace App\Core;

class Request
{
    private string $method;
    private string $path;
    private array $headers;
    private array $query;
    private array $body;
    private array $routeParams = [];
    private ?array $user = null;

    public function __construct()
    {
        $this->method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $parts = explode('?', $uri, 2);
        $this->path = rawurldecode($parts[0]);

        $this->query = $_GET;
        $this->headers = $this->parseHeaders();
        $this->body = $this->parseBody();
    }

    private function parseHeaders(): array
    {
        $headers = [];

        if (function_exists('apache_request_headers')) {
            $apacheHeaders = apache_request_headers();
            if (is_array($apacheHeaders)) {
                foreach ($apacheHeaders as $name => $value) {
                    $headers[strtolower($name)] = $value;
                }
            }
        }

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($key, 5)));
                $headers[$name] = $value;
            } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $name = str_replace('_', '-', strtolower($key));
                $headers[$name] = $value;
            } elseif ($key === 'REDIRECT_HTTP_AUTHORIZATION') {
                $headers['authorization'] = $value;
            }
        }
        return $headers;
    }

    private function parseBody(): array
    {
        if (in_array($this->method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
            $rawInput = file_get_contents('php://input');
            if ($rawInput !== false && trim($rawInput) !== '') {
                $decoded = json_decode($rawInput, true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
            if (!empty($_POST)) {
                return $_POST;
            }
        }
        return [];
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function getBearerToken(): ?string
    {
        $auth = $this->getHeader('Authorization');
        if ($auth && preg_match('/Bearer\s+(.*)$/i', $auth, $matches)) {
            return trim($matches[1]);
        }
        return null;
    }

    public function getQuery(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->query;
        }
        return $this->query[$key] ?? $default;
    }

    public function getBody(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->body;
        }
        return $this->body[$key] ?? $default;
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function getRouteParam(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }

    public function setUser(?array $user): void
    {
        $this->user = $user;
    }

    public function getUser(): ?array
    {
        return $this->user;
    }

    public function getUserId(): ?int
    {
        return isset($this->user['account_id']) ? (int) $this->user['account_id'] : null;
    }

    public function getUserRole(): ?string
    {
        return $this->user['role'] ?? null;
    }

    public function getClientId(): ?int
    {
        return isset($this->user['client_id']) ? (int) $this->user['client_id'] : null;
    }

    public function getEmployeeId(): ?int
    {
        return isset($this->user['employee_id']) ? (int) $this->user['employee_id'] : null;
    }
}
