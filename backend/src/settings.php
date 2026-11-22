<?php
function app_settings()
{
    static $settings = null;
    if ($settings !== null) {
        return $settings;
    }

    $root = dirname(__DIR__);
    $configFile = is_file($root . '/config.php')
        ? $root . '/config.php'
        : $root . '/config.example.php';

    $settings = require $configFile;
    return $settings;
}
