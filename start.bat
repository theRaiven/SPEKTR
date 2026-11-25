@echo off
setlocal DisableDelayedExpansion
set "PHP_EXE="
if not "%~1"=="" set "PHP_EXE=%~f1"
pushd "%~dp0web" || goto :bad_directory

if defined PHP_EXE goto :check_php

for /f "delims=" %%P in ('where php.exe 2^>nul') do if not defined PHP_EXE set "PHP_EXE=%%P"
if not defined PHP_EXE if exist "C:\xampp\php\php.exe" set "PHP_EXE=C:\xampp\php\php.exe"
if not defined PHP_EXE goto :no_php

:check_php
if not exist "%PHP_EXE%" goto :no_php
"%PHP_EXE%" -r "exit(PHP_VERSION_ID >= 80200 ? 0 : 1);"
if errorlevel 1 goto :bad_php
"%PHP_EXE%" -r "exit(extension_loaded('pgsql') ? 0 : 1);"
if errorlevel 1 goto :no_pgsql
"%PHP_EXE%" -r "exit(extension_loaded('mbstring') ? 0 : 1);"
if errorlevel 1 goto :no_mbstring

if not exist "config.php" goto :no_config
if not exist "public\index.php" goto :no_public
"%PHP_EXE%" -l "config.php" >nul
if errorlevel 1 goto :bad_config

"%PHP_EXE%" -r "require 'src/repository.php'; app_repo(); try { pg_connection(); } catch (Throwable $e) { exit(1); }"
if errorlevel 1 goto :no_database

"%PHP_EXE%" -r "require 'src/repository.php'; app_repo(); try { pg_value('SELECT count(*) FROM user_accounts'); pg_value('SELECT count(*) FROM repair_orders'); if (!(int) pg_value('SELECT count(*) FROM account_roles')) { exit(1); } } catch (Throwable $e) { exit(1); }"
if errorlevel 1 goto :no_schema

echo PHP: "%PHP_EXE%"
echo PostgreSQL connection OK.
echo Open http://127.0.0.1:8000 in your browser.
echo Press Ctrl+C to stop the server.
echo.
"%PHP_EXE%" -S 127.0.0.1:8000 -t public
if errorlevel 1 goto :server_error
popd
exit /b 0

:no_php
echo PHP not found. Install PHP 8.2+ or XAMPP, add PHP to PATH,
echo or pass the full path to php.exe as the first argument.
goto :fail

:bad_php
echo PHP 8.2 or newer is required.
goto :fail

:no_pgsql
echo Enable extension=pgsql in the PHP configuration file:
"%PHP_EXE%" --ini
goto :fail

:no_mbstring
echo Enable extension=mbstring in the PHP configuration file:
"%PHP_EXE%" --ini
goto :fail

:no_config
echo Create web\config.php from web\config.example.php and set database credentials.
goto :fail

:bad_config
echo Invalid web\config.php. Check its PHP syntax.
goto :fail

:no_public
echo Application files are missing. Extract the entire archive.
goto :fail

:no_database
echo Cannot connect to PostgreSQL. Check the service and web\config.php.
goto :fail

:no_schema
echo Database schema or reference data is missing or inaccessible.
echo Follow web\README.md. Do not re-import SQL into an existing populated database.
goto :fail

:server_error
echo PHP server stopped with an error. Check the message above and port 8000.
goto :fail

:fail
popd
pause
exit /b 1

:bad_directory
echo Cannot open the web directory. Extract the entire archive.
pause
exit /b 1
