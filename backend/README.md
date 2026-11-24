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

---

## Подробные сценарии ручного тестирования (Manual Test Cases)

Данный раздел предназначен для ручной проверки работы сервера через любой HTTP-клиент (**Postman**, **Insomnia**, **cURL**, расширение **REST Client** в VS Code или браузер).

Базовый URL: `http://localhost:8080/api` (или `http://127.0.0.1:8000/api` при автономном запуске через `php -S`).

Все защищенные запросы требуют передачи заголовка:
`Authorization: Bearer <полученный_токен>`

---

### Сценарий 1: Регистрация и аутентификация пользователей

#### TC-01: Регистрация нового клиента
* **Метод и URL**: `POST /api/auth/register`
* **Заголовки**: `Content-Type: application/json`
* **Тело запроса**:
  ```json
  {
    "login": "alex_client",
    "password": "password123",
    "last_name": "Алексеев",
    "first_name": "Алексей",
    "middle_name": "Сергеевич",
    "phone": "+79991112233",
    "email": "alex@example.test"
  }
  ```
* **Ожидаемый ответ**: `HTTP 201 Created`
* **Проверка**: Возвращается `token` и объект `user` с ролью `client`. В базе создана запись в таблицах `УЧЕТНЫЕ_ЗАПИСИ` и `КЛИЕНТЫ`.

#### TC-02: Вход под существующими ролями (Администратор, Мастер, Клиент)
* **Метод и URL**: `POST /api/auth/login`
* **Заголовки**: `Content-Type: application/json`
* **Тестовые учетные данные**:
  1. Администратор: `{"login": "admin", "password": "spektr123"}`
  2. Мастер по смартфонам: `{"login": "master", "password": "spektr123"}`
  3. Клиент: `{"login": "ivan", "password": "client123"}`
* **Ожидаемый ответ**: `HTTP 200 OK`
* **Проверка**: В ответе приходит `data.token`, а в `data.user.role` указана соответствующая роль (`admin`, `master`, `client`).

#### TC-03: Негативная проверка: отказ при неверном пароле
* **Метод и URL**: `POST /api/auth/login`
* **Тело запроса**: `{"login": "admin", "password": "wrong_password"}`
* **Ожидаемый ответ**: `HTTP 401 Unauthorized`
* **Проверка**: Сообщение об ошибке `Неверный логин или пароль`. Токен не выдается.

---

### Сценарий 2: Подача и первичная обработка заявки

#### TC-04: Клиент оформляет заявку на ремонт смартфона
* **Метод и URL**: `POST /api/orders`
* **Заголовок**: `Authorization: Bearer <ТОКЕН_КЛИЕНТА>`
* **Тело запроса**:
  ```json
  {
    "device_type": "Смартфон",
    "brand": "Apple",
    "model": "iPhone 13",
    "serial_number": "SN-IPHONE-777",
    "fault_description": "Разбит дисплей, мерцает полоса"
  }
  ```
* **Ожидаемый ответ**: `HTTP 201 Created`
* **Проверка**: Возвращается `order_id`, статус заказа — `Новая`. Заказ привязан к текущему клиенту.

#### TC-05: Разграничение прав: мастер не может принять заявку в сервис
* **Метод и URL**: `POST /api/orders/{order_id}/accept`
* **Заголовок**: `Authorization: Bearer <ТОКЕН_МАСТЕРА>`
* **Ожидаемый ответ**: `HTTP 403 Forbidden`
* **Проверка**: Сервер блокирует попытку мастера принять заказ, так как операция разрешена только администратору.

#### TC-06: Администратор принимает заявку в сервис
* **Метод и URL**: `POST /api/orders/{order_id}/accept`
* **Заголовок**: `Authorization: Bearer <ТОКЕН_АДМИНИСТРАТОРА>`
* **Ожидаемый ответ**: `HTTP 200 OK`
* **Проверка**: Статус заказа переходит в `Принята`. В истории статусов зафиксирована запись с `employee_id` администратора.

---

### Сценарий 3: Назначение мастера и контроль специализации

#### TC-07: Контроль квалификации: отказ при назначении неподходящего мастера
* **Метод и URL**: `POST /api/orders/{order_id}/assign`
* **Заголовок**: `Authorization: Bearer <ТОКЕН_АДМИНИСТРАТОРА>`
* **Тело запроса**: `{"master_id": 3}` *(мастер №3 Дмитриев специализируется только на ноутбуках и ПК, а заказ — на смартфон)*
* **Ожидаемый ответ**: `HTTP 409 Conflict`
* **Проверка**: Сервер отвергает назначение с текстом ошибки: *«Мастер не имеет действующей квалификации для ремонта категории устройств 'Смартфон'»*.

#### TC-08: Успешное назначение сертифицированного мастера
* **Метод и URL**: `POST /api/orders/{order_id}/assign`
* **Заголовок**: `Authorization: Bearer <ТОКЕН_АДМИНИСТРАТОРА>`
* **Тело запроса**: `{"master_id": 2}` *(мастер №2 Сидоров Алексей — ведущий специалист по смартфонам)*
* **Ожидаемый ответ**: `HTTP 200 OK`
* **Проверка**: Статус заказа переходит в `Передана мастеру`. Создана запись в `НАЗНАЧЕНИЯ_ЗАКАЗОВ`.

---

### Сценарий 4: Диагностика, ремонт и списание со склада

#### TC-09: Мастер переводит заказ в диагностику и работу
* **Метод и URL**: `PATCH /api/orders/{order_id}/status`
* **Заголовок**: `Authorization: Bearer <ТОКЕН_МАСТЕРА>`
* **Шаг 1**: `{"status": "Диагностика", "note": "Проверка тачскрина и подсветки"}`
  * Ожидаемый ответ: `HTTP 200 OK`, статус `Диагностика`.
* **Шаг 2**: `{"status": "В работе", "note": "Разборка аппарата, демонтаж модуля"}`
  * Ожидаемый ответ: `HTTP 200 OK`, статус `В работе`.

#### TC-10: Мастер фиксирует выполненную работу
* **Метод и URL**: `POST /api/orders/{order_id}/works`
* **Заголовок**: `Authorization: Bearer <ТОКЕН_МАСТЕРА>`
* **Тело запроса**: `{"work_type_id": 3}` *(Замена дисплея, тариф 2900 руб., гарантия 120 дней)*
* **Ожидаемый ответ**: `HTTP 201 Created`
* **Проверка**: В заказе появляется работа с зафиксированной на момент выполнения ценой тарифа.

#### TC-11: Мастер списывает запчасть со склада
* **Метод и URL**: `POST /api/orders/{order_id}/parts`
* **Заголовок**: `Authorization: Bearer <ТОКЕН_МАСТЕРА>`
* **Тело запроса**: `{"part_id": 1, "quantity": 1}` *(Разъем/модуль, цена 400 руб.)*
* **Ожидаемый ответ**: `HTTP 201 Created`
* **Проверка**:
  1. Деталь привязана к заказу (`order_part_id`).
  2. Остаток детали в каталоге `ЗАПЧАСТИ` уменьшен на 1.
  3. В таблице `ДВИЖЕНИЯ_ЗАПЧАСТЕЙ` зафиксирована транзакция расхода.

#### TC-12: Мастер завершает ремонтные работы
* **Метод и URL**: `PATCH /api/orders/{order_id}/status`
* **Заголовок**: `Authorization: Bearer <ТОКЕН_МАСТЕРА>`
* **Тело запроса**: `{"status": "Ожидает оплаты", "note": "Ремонт окончен, аппарат протестирован"}`
* **Ожидаемый ответ**: `HTTP 200 OK`
* **Проверка**: Статус заказа — `Ожидает оплаты`. Заказ готов к финансовым расчетам.

---

### Сценарий 5: Скидки, оплата и завершение заказа

#### TC-13: Клиент применяет промокод на скидку 5%
* **Метод и URL**: `POST /api/orders/{order_id}/discounts`
* **Заголовок**: `Authorization: Bearer <ТОКЕН_КЛИЕНТА>`
* **Тело запроса**: `{"type": "promo", "code": "WELCOME5"}`
* **Ожидаемый ответ**: `HTTP 201 Created`
* **Проверка**:
  1. Стоимость заказа (2900 работы + 400 запчасти = 3300 руб.).
  2. Скидка 5% = 165 руб.
  3. Итоговая сумма к оплате (`total_cost` / `balance_due`) пересчитана: **3135 руб.**

#### TC-14: Защита: блокировка повторного применения скидки
* **Метод и URL**: Повторный `POST /api/orders/{order_id}/discounts` с тем же промокодом `WELCOME5`.
* **Заголовок**: `Authorization: Bearer <ТОКЕН_КЛИЕНТА>`
* **Ожидаемый ответ**: `HTTP 409 Conflict`
* **Проверка**: Ошибка *«Промокод 'WELCOME5' уже применён к этому заказу»*.

#### TC-15: Администратор проводит оплату и заказ закрывается
* **Метод и URL**: `POST /api/orders/{order_id}/payment`
* **Заголовок**: `Authorization: Bearer <ТОКЕН_АДМИНИСТРАТОРА>`
* **Тело запроса**: `{"amount": 3135.0, "payment_method_id": 1}`
* **Ожидаемый ответ**: `HTTP 201 Created`
* **Проверка**:
  1. В `summary` флаг `is_fully_paid: true`.
  2. Остаток долга `balance_due: 0`.
  3. Сервер **автоматически переводит заказ в финальный статус `Завершена`**.

---

### Сценарий 6: Клиентский отзыв

#### TC-16: Клиент оставляет отзыв к завершенному ремонту
* **Метод и URL**: `POST /api/orders/{order_id}/review`
* **Заголовок**: `Authorization: Bearer <ТОКЕН_КЛИЕНТА>`
* **Тело запроса**:
  ```json
  {
    "rating": 5,
    "comment": "Отличная работа! Экран заменили за пару часов, всё работает идеально."
  }
  ```
* **Ожидаемый ответ**: `HTTP 201 Created`
* **Проверка**: Отзыв сохранен и привязан к выполненной работе.

#### TC-17: Защита от накрутки: запрет повторного отзыва
* **Метод и URL**: Повторный `POST /api/orders/{order_id}/review` по тому же заказу.
* **Ожидаемый ответ**: `HTTP 409 Conflict`
* **Проверка**: Ошибка *«Отзыв по выполненной работе этого заказа уже оставлен»*.

---

## Интеграция с настольным приложением на C# (WPF)

В десктопном приложении на C# WPF **не должно быть прямых SQL-запросов к PostgreSQL**. Все взаимодействие с базой данных и бизнес-логикой выполняется строго через данный REST API.

#### Пример сервиса взаимодействия на C# (`ApiClient.cs`):

```csharp
using System;
using System.Net.Http;
using System.Net.Http.Headers;
using System.Text;
using System.Text.Json;
using System.Threading.Tasks;

namespace Spektr.Desktop.Services
{
    public class ApiClient
    {
        private readonly HttpClient _http;
        private string _token;

        public ApiClient(string baseUrl = "http://localhost:8080/api")
        {
            _http = new HttpClient { BaseAddress = new Uri(baseUrl) };
        }

        // 1. Авторизация и сохранение токена
        public async Task<bool> LoginAsync(string login, string password)
        {
            var body = JsonSerializer.Serialize(new { login, password });
            var response = await _http.PostAsync("/api/auth/login", 
                new StringContent(body, Encoding.UTF8, "application/json"));

            if (!response.IsSuccessStatusCode) return false;

            var json = await response.Content.ReadAsStringAsync();
            using var doc = JsonDocument.Parse(json);
            _token = doc.RootElement.GetProperty("data").GetProperty("token").GetString();

            // Прикрепляем Bearer токен ко всем последующим запросам
            _http.DefaultRequestHeaders.Authorization = 
                new AuthenticationHeaderValue("Bearer", _token);

            return true;
        }

        // 2. Получение списка заказов (согласно роли текущего пользователя)
        public async Task<string> GetOrdersJsonAsync()
        {
            var response = await _http.GetAsync("/api/orders");
            response.EnsureSuccessStatusCode();
            return await response.Content.ReadAsStringAsync();
        }

        // 3. Смена статуса мастером (например, "В работе")
        public async Task<bool> UpdateOrderStatusAsync(int orderId, string newStatus, string note)
        {
            var body = JsonSerializer.Serialize(new { status = newStatus, note });
            var request = new HttpRequestMessage(new HttpMethod("PATCH"), $"/api/orders/{orderId}/status")
            {
                Content = new StringContent(body, Encoding.UTF8, "application/json")
            };

            var response = await _http.SendAsync(request);
            return response.IsSuccessStatusCode;
        }
    }
}
```

---

## Подключение PostgreSQL

Сервер по умолчанию использует умный fallback на локальный SQLite (`var/spektr_dev.sqlite`), если PostgreSQL не запущен. Для промышленной работы с PostgreSQL:

1. **Убедитесь, что служба PostgreSQL запущена** на порту 5432:
   ```bash
   # Проверка доступности порта в Windows PowerShell:
   Test-NetConnection -ComputerName 127.0.0.1 -Port 5432
   ```
2. **Создайте базу данных `spektr` и накатите схему бригады**:
   ```sql
   CREATE DATABASE spektr WITH ENCODING 'UTF8';
   ```
   Выполните файлы `database/migrations/01_schema.sql` и `database/seeds/01_initial_data.sql`.
3. **Настройте `.env` в папке `backend/`**:
   ```ini
   DB_DRIVER=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_NAME=spektr
   DB_USER=postgres
   DB_PASSWORD=ваш_пароль_от_postgres
   ```
4. После сохранения `.env` сервер автоматически переключится на реальный PostgreSQL, не требуя переписывания ни строчки PHP-кода.

