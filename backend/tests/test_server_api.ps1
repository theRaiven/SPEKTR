# ==============================================================================
#   СКВОЗНОЕ ТЕСТИРОВАНИЕ REST API СЕРВЕРА «СПЕКТР» (HTTP / JSON / RBAC)
# ==============================================================================

param (
    [string]$BaseUrl = "http://localhost:8080/api"
)

# Настройка кодировки консоли
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8
$OutputEncoding = [System.Text.Encoding]::UTF8

$passed = 0
$failed = 0

function Write-Header {
    param ([string]$Title)
    Write-Host ""
    Write-Host ("=" * 75) -ForegroundColor Cyan
    Write-Host "   $Title" -ForegroundColor Cyan
    Write-Host ("=" * 75) -ForegroundColor Cyan
}

function Invoke-Api {
    param (
        [string]$Method,
        [string]$Endpoint,
        [object]$Body = $null,
        [string]$Token = $null,
        [int]$ExpectedStatus = 200
    )

    $uri = "$BaseUrl$Endpoint"
    $headers = @{}
    if ($Token) {
        $headers["Authorization"] = "Bearer $Token"
    }

    $params = @{
        Uri = $uri
        Method = $Method
        Headers = $headers
        ContentType = "application/json; charset=utf-8"
        UseBasicParsing = $true
    }

    if ($Body -ne $null) {
        $jsonStr = $Body | ConvertTo-Json -Depth 10 -Compress
        $params["Body"] = [System.Text.Encoding]::UTF8.GetBytes($jsonStr)
    }

    try {
        $response = Invoke-WebRequest @params
        $statusCode = [int]$response.StatusCode
        $content = $response.Content | ConvertFrom-Json
        
        if ($statusCode -ne $ExpectedStatus) {
            throw "Ожидался статус $ExpectedStatus, получен $statusCode"
        }
        return @{ Success = $true; StatusCode = $statusCode; Data = $content.data; Full = $content }
    } catch [System.Net.WebException] {
        $ex = $_.Exception
        if ($ex.Response) {
            $resp = [System.Net.HttpWebResponse]$ex.Response
            $statusCode = [int]$resp.StatusCode
            $reader = New-Object System.IO.StreamReader($resp.GetResponseStream(), [System.Text.Encoding]::UTF8)
            $errorBody = $reader.ReadToEnd()
            $parsedError = $null
            try { $parsedError = $errorBody | ConvertFrom-Json } catch {}

            if ($statusCode -eq $ExpectedStatus) {
                return @{ Success = $true; StatusCode = $statusCode; Error = $parsedError.error; ExpectedFailure = $true }
            } else {
                $msg = if ($parsedError.error.message) { $parsedError.error.message } else { $ex.Message }
                throw "HTTP $statusCode (ожидался $ExpectedStatus): $msg"
            }
        } else {
            throw $ex.Message
        }
    }
}

function Run-Test {
    param (
        [string]$Name,
        [scriptblock]$Action
    )
    Write-Host -NoNewline ("  {0,-58} ... " -f $Name)
    try {
        & $Action
        Write-Host "[OK]" -ForegroundColor Green
        $global:passed++
    } catch {
        Write-Host "[FAIL]" -ForegroundColor Red
        Write-Host "       Причина: $_" -ForegroundColor Yellow
        $global:failed++
    }
}

Write-Header "ЗАПУСК ТЕСТИРОВАНИЯ REST API: $BaseUrl"

# Переменные для цепочки тестов
$adminToken = $null
$masterToken = $null
$clientToken = $null
$orderId = $null
$totalAmount = 0.0

# ------------------------------------------------------------------------------
# ГРУППА 1: СИСТЕМНЫЙ СТАТУС И АУТЕНТИФИКАЦИЯ
# ------------------------------------------------------------------------------
Write-Host "`n[1/6] Аутентификация, профили и безопасность (JWT/Bearer)" -ForegroundColor White

Run-Test "1.1 Проверка доступности сервера (GET /api/health)" {
    $res = Invoke-Api -Method "GET" -Endpoint "/health"
    if ($res.Data.status -ne "ok") { throw "Сервер вернул статус не ok" }
}

Run-Test "1.2 Вход администратора (POST /api/auth/login: admin)" {
    $res = Invoke-Api -Method "POST" -Endpoint "/auth/login" -Body @{ login = "admin"; password = "spektr123" }
    if (-not $res.Data.token -or $res.Data.user.role -ne "admin") { throw "Не получен токен администратора" }
    $global:adminToken = $res.Data.token
}

Run-Test "1.3 Вход мастера (POST /api/auth/login: master)" {
    $res = Invoke-Api -Method "POST" -Endpoint "/auth/login" -Body @{ login = "master"; password = "spektr123" }
    if (-not $res.Data.token -or $res.Data.user.role -ne "master") { throw "Не получен токен мастера" }
    $global:masterToken = $res.Data.token
}

Run-Test "1.4 Защита от подбора пароля: отказ при неверном пароле (401)" {
    Invoke-Api -Method "POST" -Endpoint "/auth/login" -Body @{ login = "admin"; password = "wrong_password" } -ExpectedStatus 401 | Out-Null
}

Run-Test "1.5 Регистрация нового клиента (POST /api/auth/register)" {
    $rand = Get-Random -Minimum 1000 -Maximum 9999
    $regData = @{
        login = "user_$rand"
        password = "password123"
        last_name = "Смирнов"
        first_name = "Артем"
        phone = "+7999" + (Get-Random -Minimum 1000000 -Maximum 9999999)
        email = "user_$rand@test.ru"
    }
    $res = Invoke-Api -Method "POST" -Endpoint "/auth/register" -Body $regData -ExpectedStatus 201
    if (-not $res.Data.token -or $res.Data.user.role -ne "client") { throw "Не получен токен клиента" }
    $global:clientToken = $res.Data.token
}

Run-Test "1.6 Проверка профиля по Bearer-токену (GET /api/auth/me)" {
    $res = Invoke-Api -Method "GET" -Endpoint "/auth/me" -Token $global:clientToken
    if ($res.Data.user.role -ne "client") { throw "Не совпадает роль в профиле" }
}

# ------------------------------------------------------------------------------
# ГРУППА 2: ПРОВЕРКА РОЛЕВОЙ МОДЕЛИ ДОСТУПА (RBAC)
# ------------------------------------------------------------------------------
Write-Host "`n[2/6] Разграничение прав доступа (RBAC: 403 Forbidden)" -ForegroundColor White

Run-Test "2.1 Клиент не имеет доступа к служебным данным персонала (403)" {
    Invoke-Api -Method "GET" -Endpoint "/clients" -Token $global:clientToken -ExpectedStatus 403 | Out-Null
}

Run-Test "2.2 Мастер не имеет прав регистрировать сотрудников (403)" {
    Invoke-Api -Method "POST" -Endpoint "/staff" -Token $global:masterToken -Body @{ name = "hack" } -ExpectedStatus 403 | Out-Null
}

# ------------------------------------------------------------------------------
# ГРУППА 3: СОЗДАНИЕ И ОБРАБОТКА ЗАЯВКИ
# ------------------------------------------------------------------------------
Write-Host "`n[3/6] Жизненный цикл заявки (Создание -> Приёмка -> Назначение)" -ForegroundColor White

Run-Test "3.1 Клиент подаёт заявку на ремонт смартфона (POST /api/orders)" {
    $orderData = @{
        device_type = "Смартфон"
        brand = "Apple"
        model = "iPhone 13"
        serial_number = "IPHONE-SN-" + (Get-Random -Minimum 1000 -Maximum 9999)
        fault_description = "Разбит дисплей, быстро садится аккумулятор"
    }
    $res = Invoke-Api -Method "POST" -Endpoint "/orders" -Token $global:clientToken -Body $orderData -ExpectedStatus 201
    if (-not $res.Data.order_id -or $res.Data.status -ne "Новая") { throw "Заявка не создана" }
    $global:orderId = $res.Data.order_id
}

Run-Test "3.2 Мастер не может принять заявку в сервис (403 Forbidden)" {
    Invoke-Api -Method "POST" -Endpoint "/orders/$($global:orderId)/accept" -Token $global:masterToken -ExpectedStatus 403 | Out-Null
}

Run-Test "3.3 Администратор принимает заявку в сервис (POST /accept)" {
    $res = Invoke-Api -Method "POST" -Endpoint "/orders/$($global:orderId)/accept" -Token $global:adminToken
    if ($res.Data.status -ne "Принята") { throw "Статус должен быть 'Принята'" }
}

Run-Test "3.4 Контроль квалификации: отказ при назначении неподходящего мастера (409)" {
    # Мастер №3 специализируется на ноутбуках и ПК, а заявка на Смартфон!
    Invoke-Api -Method "POST" -Endpoint "/orders/$($global:orderId)/assign" -Token $global:adminToken -Body @{ master_id = 3 } -ExpectedStatus 409 | Out-Null
}

Run-Test "3.5 Администратор назначает сертифицированного мастера (POST /assign)" {
    # Мастер №2 специализируется на смартфонах
    $res = Invoke-Api -Method "POST" -Endpoint "/orders/$($global:orderId)/assign" -Token $global:adminToken -Body @{ master_id = 2 }
    if ($res.Data.status -ne "Передана мастеру") { throw "Статус должен быть 'Передана мастеру'" }
}

# ------------------------------------------------------------------------------
# ГРУППА 4: ВЫПОЛНЕНИЕ РЕМОНТА, СКЛАД И РАБОТЫ
# ------------------------------------------------------------------------------
Write-Host "`n[4/6] Ремонтные работы, складские транзакции и смена статусов" -ForegroundColor White

Run-Test "4.1 Мастер переводит заказ: Диагностика (PATCH /status)" {
    $res = Invoke-Api -Method "PATCH" -Endpoint "/orders/$($global:orderId)/status" -Token $global:masterToken -Body @{
        status = "Диагностика"
        note = "Проведена входная диагностика дисплейного модуля"
    }
    if ($res.Data.status -ne "Диагностика") { throw "Ожидался статус Диагностика" }
}

Run-Test "4.2 Мастер переводит заказ: В работе (PATCH /status)" {
    $res = Invoke-Api -Method "PATCH" -Endpoint "/orders/$($global:orderId)/status" -Token $global:masterToken -Body @{
        status = "В работе"
        note = "Начаты работы по замене экрана"
    }
    if ($res.Data.status -ne "В работе") { throw "Ожидался статус В работе" }
}

Run-Test "4.3 Добавление выполненной работы (POST /works: Замена экрана)" {
    $res = Invoke-Api -Method "POST" -Endpoint "/orders/$($global:orderId)/works" -Token $global:masterToken -Body @{
        work_type_id = 3 # Замена дисплея
    } -ExpectedStatus 201
    if (-not $res.Data.order_work_id) { throw "Работа не привязана к заказу" }
}

Run-Test "4.4 Добавление запчасти со списанием со склада (POST /parts)" {
    $res = Invoke-Api -Method "POST" -Endpoint "/orders/$($global:orderId)/parts" -Token $global:masterToken -Body @{
        part_id = 1 # Деталь со склада
        quantity = 1
    } -ExpectedStatus 201
    if (-not $res.Data.order_part_id) { throw "Запчасть не списана со склада" }
}

Run-Test "4.5 Мастер завершает ремонт: Ожидает оплаты (PATCH /status)" {
    $res = Invoke-Api -Method "PATCH" -Endpoint "/orders/$($global:orderId)/status" -Token $global:masterToken -Body @{
        status = "Ожидает оплаты"
        note = "Ремонт окончен, аппарат протестирован"
    }
    if ($res.Data.status -ne "Ожидает оплаты") { throw "Ожидался статус Ожидает оплаты" }
}

# ------------------------------------------------------------------------------
# ГРУППА 5: СКИДКИ, ПЛАТЕЖИ И ЗАКРЫТИЕ ЗАКАЗА
# ------------------------------------------------------------------------------
Write-Host "`n[5/6] Расчёт стоимости, промокоды и фиксация оплаты" -ForegroundColor White

Run-Test "5.1 Применение клиентом промокода на скидку 5% (POST /discounts)" {
    $res = Invoke-Api -Method "POST" -Endpoint "/orders/$($global:orderId)/discounts" -Token $global:clientToken -Body @{
        type = "promo"
        code = "WELCOME5"
    } -ExpectedStatus 201
    if ($res.Data.discount_amount -le 0) { throw "Не применилась скидка промокода" }
    $global:totalAmount = [double]$res.Data.summary.balance_due
}

Run-Test "5.2 Защита: повторное применение промокода блокируется (409 Conflict)" {
    Invoke-Api -Method "POST" -Endpoint "/orders/$($global:orderId)/discounts" -Token $global:clientToken -Body @{
        type = "promo"
        code = "WELCOME5"
    } -ExpectedStatus 409 | Out-Null
}

Run-Test "5.3 Администратор вносит оплату, автозакрытие заказа в 'Завершена'" {
    $res = Invoke-Api -Method "POST" -Endpoint "/orders/$($global:orderId)/payment" -Token $global:adminToken -Body @{
        amount = $global:totalAmount
        payment_method_id = 1 # Наличные / Карта
    } -ExpectedStatus 201
    if (-not $res.Data.summary.is_fully_paid -or $res.Data.summary.status -ne "Завершена") {
        throw "Заказ должен быть полностью оплачен и переведен в статус 'Завершена'"
    }
}

# ------------------------------------------------------------------------------
# ГРУППА 6: ОТЗЫВЫ И ГАРАНТИЯ
# ------------------------------------------------------------------------------
Write-Host "`n[6/6] Клиентские отзывы и контроль повторной отправки" -ForegroundColor White

Run-Test "6.1 Клиент оставляет отзыв 5 звёзд к выполненному ремонту (POST /review)" {
    $res = Invoke-Api -Method "POST" -Endpoint "/orders/$($global:orderId)/review" -Token $global:clientToken -Body @{
        rating = 5
        comment = "Ремонт выполнен быстро и качественно, экран работает отлично!"
    } -ExpectedStatus 201
    if ($res.Data.rating -ne 5) { throw "Отзыв не сохранён" }
}

Run-Test "6.2 Защита: повторный отзыв на ту же работу блокируется (409 Conflict)" {
    Invoke-Api -Method "POST" -Endpoint "/orders/$($global:orderId)/review" -Token $global:clientToken -Body @{
        rating = 4
        comment = "Повторный отзыв"
    } -ExpectedStatus 409 | Out-Null
}

# ------------------------------------------------------------------------------
# ИТОГ ТЕСТИРОВАНИЯ
# ------------------------------------------------------------------------------
Write-Header "ИТОГ СКВОЗНОГО ТЕСТИРОВАНИЯ СЕРВЕРА"
Write-Host "  Всего тестов : $(($passed + $failed))" -ForegroundColor White
Write-Host "  Успешно      : $passed" -ForegroundColor Green
if ($failed -gt 0) {
    Write-Host "  Провалено    : $failed" -ForegroundColor Red
    Write-Host ""
    exit 1
} else {
    Write-Host "  Провалено    : 0" -ForegroundColor Green
    Write-Host "`n  ВСЕ ТЕСТЫ СЕРВЕРА УСПЕШНО ПРОЙДЕНЫ! СЕРВЕР ПОЛНОСТЬЮ ГОТОВ К ИНТЕГРАЦИИ." -ForegroundColor Green
    Write-Host ""
}
