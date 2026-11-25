# Серверная инфраструктура

Для локальной демонстрации под Windows достаточно встроенного сервера PHP и PostgreSQL. См. [инструкцию локального запуска](../web/README.md). Настройка Nginx ниже является отдельным вариантом развертывания.

Проверенное окружение:

- Windows 11
- WSL2 / Ubuntu 26.04
- Nginx 1.28.3
- PHP-FPM 8.5.4
- PostgreSQL 18.4

## Пути

- Приложение: /var/www/spektr
- Document root: /var/www/spektr/public
- Конфигурация приложения: /var/www/spektr/config.php
- PHP-сессии: /var/www/spektr/var/sessions
- FastCGI: /run/php/php8.5-fpm.sock
- Конфигурация Nginx: /etc/nginx/sites-available/spektr
- TLS-сертификат: /etc/nginx/ssl/spektr.crt
- Закрытый ключ: /etc/nginx/ssl/spektr.key

## Развертывание

1. Установить Nginx, PHP-FPM, php-pgsql и php-mbstring.
2. Создать базу данных PostgreSQL и пользователя приложения.
3. Выполнить web/sql/schema.sql и web/sql/seed.sql.
4. Разместить public/ и src/ в каталоге приложения.
5. Создать config.php на основе config.example.php.
6. Создать каталог var/sessions с правами записи для www-data.
7. Создать TLS-сертификат и закрытый ключ.
8. Адаптировать и активировать конфигурацию nginx.conf.
9. Проверить конфигурацию командой nginx -t.
10. Запустить Nginx и PHP-FPM.

## Безопасность

Конфигурация nginx.conf предназначена для локального окружения.
При переносе на другой сервер необходимо проверить пути,
версию PHP-FPM, сетевые адреса и TLS-сертификаты.

Полный аудит безопасности приложения не проводился.
