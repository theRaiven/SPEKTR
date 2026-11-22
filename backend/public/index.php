<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/src/Core/Autoloader.php';

use App\Core\Autoloader;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Middleware\CorsMiddleware;
use App\Middleware\AuthMiddleware;

use App\Controllers\AuthController;
use App\Controllers\ProfileController;
use App\Controllers\DeviceController;
use App\Controllers\ClientController;
use App\Controllers\OrderController;
use App\Controllers\PaymentController;
use App\Controllers\ReviewController;
use App\Controllers\MasterController;
use App\Controllers\StaffController;

// 1. Инициализация автозагрузчика и переменных окружения
Autoloader::register();
Autoloader::loadEnv(dirname(__DIR__) . '/.env');

$appConfig = require dirname(__DIR__) . '/config/app.php';
date_default_timezone_set($appConfig['timezone'] ?? 'Asia/Novosibirsk');

$dbConfig = require dirname(__DIR__) . '/config/database.php';
Database::init($dbConfig);

// 2. Обработка CORS
$request = new Request();
CorsMiddleware::handle($request);

// 3. Регистрация маршрутов API
$router = new Router();

// Вспомогательные замыкания для проверки авторизации и ролей
$auth = fn(Request $r) => AuthMiddleware::authenticate($r);
$adminOnly = fn(Request $r) => AuthMiddleware::requireRole($r, ['admin']);
$clientOnly = fn(Request $r) => AuthMiddleware::requireRole($r, ['client']);
$masterOrAdmin = fn(Request $r) => AuthMiddleware::requireRole($r, ['master', 'admin']);

// Авторизация
$router->post('/api/auth/register', [AuthController::class, 'register']);
$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->post('/api/auth/logout', [AuthController::class, 'logout'], [$auth]);
$router->get('/api/auth/me', [AuthController::class, 'me'], [$auth]);

// Пользователи и устройства
$router->get('/api/profile', [ProfileController::class, 'show'], [$auth]);
$router->put('/api/profile', [ProfileController::class, 'update'], [$auth]);
$router->get('/api/devices', [DeviceController::class, 'index'], [$auth]);
$router->post('/api/devices', [DeviceController::class, 'store'], [$auth]);
$router->get('/api/devices/types', [DeviceController::class, 'types'], [$auth]);
$router->get('/api/devices/brands', [DeviceController::class, 'brands'], [$auth]);
$router->get('/api/clients', [ClientController::class, 'index'], [$adminOnly]);
$router->get('/api/clients/{id}', [ClientController::class, 'show'], [$adminOnly]);

// Заказы
$router->get('/api/orders', [OrderController::class, 'index'], [$auth]);
$router->get('/api/orders/{id}', [OrderController::class, 'show'], [$auth]);
$router->post('/api/orders', [OrderController::class, 'store'], [$clientOnly]);
$router->post('/api/orders/{id}/accept', [OrderController::class, 'accept'], [$adminOnly]);
$router->post('/api/orders/{id}/reject', [OrderController::class, 'reject'], [$adminOnly]);
$router->post('/api/orders/{id}/assign', [OrderController::class, 'assign'], [$adminOnly]);
$router->patch('/api/orders/{id}/status', [OrderController::class, 'updateStatus'], [$masterOrAdmin]);
$router->get('/api/orders/{id}/history', [OrderController::class, 'history'], [$auth]);
$router->post('/api/orders/{id}/works', [OrderController::class, 'addWork'], [$masterOrAdmin]);
$router->post('/api/orders/{id}/parts', [OrderController::class, 'addPart'], [$masterOrAdmin]);

// Платежи, скидки, отзывы
$router->post('/api/orders/{id}/discounts', [PaymentController::class, 'discounts'], [$clientOnly]);
$router->get('/api/orders/{id}/payment', [PaymentController::class, 'payment'], [$auth]);
$router->post('/api/orders/{id}/payment', [PaymentController::class, 'recordPayment'], [$adminOnly]);
$router->get('/api/payment/methods', [PaymentController::class, 'methods'], [$auth]);
$router->post('/api/orders/{id}/review', [ReviewController::class, 'store'], [$clientOnly]);
$router->get('/api/orders/{id}/review', [ReviewController::class, 'show'], [$auth]);

// Мастера и персонал
$router->get('/api/masters', [MasterController::class, 'index'], [$auth]);
$router->get('/api/masters/{id}/specializations', [MasterController::class, 'specializations'], [$auth]);
$router->post('/api/staff', [StaffController::class, 'store'], [$adminOnly]);
$router->get('/api/work-types', [StaffController::class, 'workTypes'], [$auth]);
$router->get('/api/parts', [StaffController::class, 'parts'], [$auth]);

// Системный статус / healthcheck
$router->get('/api/health', function (Request $r) {
    Response::json([
        'status' => 'ok',
        'service' => 'api',
        'timestamp' => date('c'),
    ]);
});

// 4. Диспетчеризация запроса
$router->dispatch($request);
