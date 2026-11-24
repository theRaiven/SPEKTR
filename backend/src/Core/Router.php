<?php

namespace App\Core;

use App\Core\Exceptions\ApiException;
use App\Core\Exceptions\NotFoundException;
use Throwable;

class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('POST', $path, $handler, $middlewares);
    }

    public function put(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('PUT', $path, $handler, $middlewares);
    }

    public function patch(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('PATCH', $path, $handler, $middlewares);
    }

    public function delete(string $path, callable|array $handler, array $middlewares = []): self
    {
        return $this->addRoute('DELETE', $path, $handler, $middlewares);
    }

    private function addRoute(string $method, string $path, callable|array $handler, array $middlewares): self
    {
        $pattern = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        $pattern = "#^" . $pattern . "$#";

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'pattern' => $pattern,
            'handler' => $handler,
            'middlewares' => $middlewares,
        ];

        return $this;
    }

    public function dispatch(Request $request): void
    {
        try {
            $method = $request->getMethod();
            $path = rtrim($request->getPath(), '/');
            if ($path === '') {
                $path = '/';
            }

            foreach ($this->routes as $route) {
                if ($route['method'] !== $method) {
                    continue;
                }

                if (preg_match($route['pattern'], $path, $matches)) {
                    $routeParams = [];
                    foreach ($matches as $key => $value) {
                        if (is_string($key)) {
                            $routeParams[$key] = $value;
                        }
                    }
                    $request->setRouteParams($routeParams);

                    // Execute middlewares
                    foreach ($route['middlewares'] as $middleware) {
                        if (is_callable($middleware)) {
                            $middleware($request);
                        } elseif (is_array($middleware) && is_callable($middleware)) {
                            call_user_func($middleware, $request);
                        }
                    }

                    // Execute handler
                    if (is_callable($route['handler'])) {
                        call_user_func($route['handler'], $request);
                        return;
                    }

                    // Call handler [ControllerClass, 'action']
                    [$controllerClass, $action] = $route['handler'];
                    $controller = new $controllerClass();
                    $controller->$action($request);
                    return;
                }
            }

            throw new NotFoundException("Эндпоинт {$method} {$path} не найден");

        } catch (ApiException $e) {
            Response::error($e->getMessage(), $e->getErrorCode(), $e->getStatusCode(), $e->getDetails());
        } catch (Throwable $e) {
            $debug = (bool) (getenv('APP_DEBUG') ?: true);
            $message = $debug ? $e->getMessage() : 'Внутренняя ошибка сервера';
            $details = $debug ? [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => explode("\n", $e->getTraceAsString()),
            ] : null;

            Response::error($message, 'INTERNAL_SERVER_ERROR', 500, $details);
        }
    }
}
