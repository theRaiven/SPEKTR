# СПЕКТР

Информационная система сервисного центра по ремонту и гарантийному обслуживанию электронной техники.

**Технологии:** PHP 8.2+, PostgreSQL 14+, HTML, CSS, JavaScript.

## Локальный запуск (Windows)

### 1. Установка

- PostgreSQL 14+
- PHP 8.2+ (допускается использование XAMPP)

В `php.ini` включить расширения:

```ini
extension=pgsql
extension=mbstring
```

### 2. Создание БД

Выполнить в psql от имени администратора PostgreSQL:

```sql
CREATE ROLE spektr_app LOGIN;
\password spektr_app
CREATE DATABASE spektr OWNER spektr_app ENCODING 'UTF8' TEMPLATE template0;
```

Выполнить из каталога `web/`:

```bat
psql -h 127.0.0.1 -U spektr_app -d spektr -v ON_ERROR_STOP=1 -f sql/schema.sql
psql -h 127.0.0.1 -U spektr_app -d spektr -v ON_ERROR_STOP=1 -f sql/seed.sql
```

### 3. Конфигурация

Скопировать `web/config.example.php` в `web/config.php`:

```php
<?php
return [
    'mode' => 'postgres',
    'debug' => false,
    'db_host' => '127.0.0.1',
    'db_port' => '5432',
    'db_name' => 'spektr',
    'db_user' => 'spektr',
    'db_password' => 'ВАШ_ПАРОЛЬ',
];
```

### 4. Запуск

Запустить службу PostgreSQL и выполнить `start.bat`.

Ручной запуск из каталога `web/`:

```bat
php -S 127.0.0.1:8000 -t public
```

Адрес приложения: [http://127.0.0.1:8000](http://127.0.0.1:8000).

## Роли пользователей

- Клиент: оформление заявок, история ремонтов, выбор мастера, отзывы.
- Мастер: обработка назначенных заказов, учёт работ, изменение статусов.
- Администратор: управление заявками, мастерами, заказами, запчастями и оплатой.

## Структура web/

- `public/` — пользовательский интерфейс;
- `src/` — серверная логика;
- `sql/schema.sql` — схема БД;
- `sql/seed.sql` — тестовые данные;
- `config.example.php` — шаблон конфигурации;
- `config.php` — параметры подключения к БД.
