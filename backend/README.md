# Backend REST API

Серверная часть информационной системы сервисного центра.
Реализована на PHP 8.2 и PostgreSQL в виде REST API (JSON).

## Требования к окружению

- PHP 8.2 или выше с расширениями:
  - `pdo_pgsql`
  - `pgsql`
  - `pdo_sqlite` (для локальных тестов)
  - `openssl`
  - `json`
- PostgreSQL 14 или выше.

## Конфигурация

Скопируйте пример файла конфигурации:

```bash
cp .env.example .env
```

Параметры подключения к базе данных и настройки сервиса задаются в файле `.env`:

```ini
APP_ENV=development
APP_DEBUG=true
TOKEN_TTL_HOURS=72

DB_DRIVER=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_NAME=spektr
DB_USER=postgres
DB_PASSWORD=secret
```

## Инициализация базы данных

SQL-скрипты схемы и начальных данных расположены в каталоге `database/`:
- `database/migrations/01_schema.sql` — создание 46 таблиц, внешних ключей, индексов и ограничений целостности.
- `database/seeds/01_initial_data.sql` — справочники (роли, статусы, тарифы, типы устройств, бренды) и тестовые учетные записи.

Применение скриптов:

```bash
psql -h 127.0.0.1 -p 5432 -U postgres -d spektr -f ../database/migrations/01_schema.sql
psql -h 127.0.0.1 -p 5432 -U postgres -d spektr -f ../database/seeds/01_initial_data.sql
```

## Запуск сервиса

### Встроенный сервер PHP

```bash
cd backend
php -S 127.0.0.1:8000 -t public
```

Проверка доступности сервиса:

```bash
curl http://127.0.0.1:8000/api/health
```

### Запуск через Nginx + PHP-FPM

Корневая директория сайта (`root`): `backend/public`  
Точка входа: `index.php`

Пример конфигурации location для Nginx:

```nginx
location / {
    try_files $uri $uri/ /index.php?$query_string;
}

location ~ \.php$ {
    fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    fastcgi_index index.php;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    include fastcgi_params;
}
```

## Тестирование

Для автоматической проверки бизнес-логики, валидации прав доступа, машины состояний заказов и финансовых расчетов запустите тестовые скрипты:

```bash
# Тестирование REST API (16 сценариев)
php backend/tests/run_tests.php

# Тестирование интеграционных функций PostgreSQL (7 сценариев)
php backend/tests/test_repository_pg.php
```

Тесты запускаются автономно с использованием базы SQLite в памяти и не требуют предварительного развертывания PostgreSQL.

## Учетные записи для тестирования

Все пароли в базе захешированы с использованием bcrypt.

| Роль | Логин | Пароль | Описание |
|---|---|---|---|
| Администратор | admin | spektr123 | Прием заявок, назначение мастеров, регистрация оплаты |
| Мастер по смартфонам | master | spektr123 | Диагностика и ремонт смартфонов и планшетов |
| Мастер по ноутбукам | master2 | spektr123 | Ремонт ноутбуков и ПК |
| Клиент | ivan | client123 | Создание заявок, просмотр статуса, применение скидок, отзывы |

## Протокол взаимодействия (REST API)

### Заголовки HTTP

Для запросов, содержащих тело:
```http
Content-Type: application/json
```

Для защищенных маршрутов:
```http
Authorization: Bearer <token>
```

### Формат ответов

Успешный ответ (HTTP 200, 201):
```json
{
  "success": true,
  "data": { ... }
}
```

Ответ с ошибкой (HTTP 400, 401, 403, 404, 409, 422, 500):
```json
{
  "success": false,
  "error": {
    "code": "BAD_REQUEST | UNAUTHORIZED | FORBIDDEN | NOT_FOUND | CONFLICT | VALIDATION_ERROR",
    "message": "Описание причины ошибки",
    "details": null
  }
}
```

## Спецификация эндпоинтов

### 1. Аутентификация

- `POST /api/auth/register` — регистрация клиента.  
  Параметры: `login`, `password`, `last_name`, `first_name`, `middle_name`, `phone`, `email`.
- `POST /api/auth/login` — аутентификация пользователя и получение токена.  
  Параметры: `login`, `password`.
- `POST /api/auth/logout` [Bearer] — завершение сессии, аннулирование токена.
- `GET /api/auth/me` [Bearer] — получение данных текущей сессии.

### 2. Пользователи и устройства

- `GET /api/profile` [Bearer] — данные профиля текущего пользователя.
- `PUT /api/profile` [Bearer] — обновление контактных данных клиента.
- `GET /api/devices` [Bearer] — список устройств клиента.
- `POST /api/devices` [Bearer] — добавление устройства в профиль.  
  Параметры: `device_type`, `brand`, `model`, `serial_number`.
- `GET /api/devices/types` [Bearer] — справочник типов устройств.
- `GET /api/devices/brands` [Bearer] — справочник брендов.
- `GET /api/clients` [Admin] — список клиентов с фильтрацией `?search=...`.
- `GET /api/clients/{id}` [Admin] — карточка клиента и история заказов.

### 3. Управление заказами

- `GET /api/orders` [Bearer] — список заказов с разграничением по роли:
  - клиент видит только свои заказы;
  - мастер видит только назначенные на него заказы;
  - администратор видит все заказы с возможностью фильтрации `?status=...`.
- `GET /api/orders/{id}` [Bearer] — детальная информация о заказе (работы, запчасти, история статусов, смета).
- `POST /api/orders` [Client] — создание новой заявки на ремонт.  
  Параметры: `device_id` (или параметры нового устройства), `fault_description`.
- `POST /api/orders/{id}/accept` [Admin] — перевод заявки из статуса «Новая» в статус «Принята».
- `POST /api/orders/{id}/reject` [Admin] — отклонение заявки с обязательным указанием причины.  
  Параметры: `reason`.
- `POST /api/orders/{id}/assign` [Admin] — назначение мастера на заказ.  
  Параметры: `master_id`. Сервер выполняет проверку соответствия квалификации мастера категории устройства.
- `PATCH /api/orders/{id}/status` [Master, Admin] — изменение статуса заказа согласно допустимым переходам машины состояний.  
  Параметры: `status`, `note`.
- `GET /api/orders/{id}/history` [Bearer] — история изменения статусов заказа.
- `POST /api/orders/{id}/works` [Master, Admin] — добавление выполненной работы к заказу.  
  Параметры: `work_type_id`.
- `POST /api/orders/{id}/parts` [Master, Admin] — списание запчасти со склада и добавление в заказ.  
  Параметры: `part_id`, `quantity`.

### 4. Расчет стоимости, скидки и оплата

- `GET /api/orders/{id}/payment` [Bearer] — финансовая сводка по заказу:
  - `works_total` — сумма работ;
  - `parts_total` — сумма деталей;
  - `discounts_total` — сумма примененных скидок;
  - `total_cost` — итоговая сумма к оплате;
  - `total_paid` — фактически оплаченная сумма;
  - `balance_due` — остаток к оплате;
  - `is_fully_paid` — признак полной оплаты.
- `POST /api/orders/{id}/discounts` [Client] — применение скидки к заказу:
  - промокод: `{"type": "promo", "code": "WELCOME5"}`;
  - ваучер: `{"type": "voucher", "code": "GIFT500"}`;
  - льгота: `{"type": "benefit"}`.
- `POST /api/orders/{id}/payment` [Admin] — фиксация оплаты заказа.  
  Параметры: `amount`, `payment_method_id`, `external_reference`. При полном погашении долга заказ автоматически переводится в статус «Завершена».
- `GET /api/payment/methods` [Bearer] — справочник способов оплаты.

### 5. Отзывы

- `POST /api/orders/{id}/review` [Client] — публикация отзыва к завершенному заказу.  
  Параметры: `rating` (1..5), `comment`. Доступно только клиенту-владельцу после статуса «Завершена» и 100% оплаты.
- `GET /api/orders/{id}/review` [Bearer] — просмотр отзыва по заказу.

### 6. Персонал и справочники

- `GET /api/masters` [Bearer] — реестр мастеров со специализациями и текущей загрузкой.
- `GET /api/masters/{id}/specializations` [Bearer] — специализации конкретного мастера.
- `POST /api/staff` [Admin] — регистрация учетной записи сотрудника.
- `GET /api/work-types` [Bearer] — справочник видов работ и базовых тарифов.
- `GET /api/parts` [Bearer] — каталог запчастей и складские остатки.

## Безопасность

1. Параметризованные SQL-запросы: все обращения к БД выполняются через подготовленные выражения PDO для исключения SQL-инъекций.
2. Ролевая модель доступа (RBAC): права проверяются на стороне сервера для каждого эндпоинта.
3. Транзакционность: многошаговые операции (смена статуса, списание со склада, проводка оплаты) выполняются в рамках транзакций.
4. Хранение паролей: пароли пользователей хранятся исключительно в виде bcrypt-хешей.
