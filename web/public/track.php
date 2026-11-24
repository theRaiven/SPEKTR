<?php
require_once dirname(__DIR__) . '/src/common.php';
go(current_user() ? home_for_user(current_user()) : '/login.php');
