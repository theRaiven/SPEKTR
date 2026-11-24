<?php
require_once dirname(__DIR__) . '/src/common.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Метод не разрешен');
}
check_csrf();
$_SESSION = [];
session_regenerate_id(true);
go('/login.php');
