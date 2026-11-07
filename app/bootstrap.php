<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_BASE_URL', rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/'));

require APP_ROOT . '/app/helpers.php';
require APP_ROOT . '/app/Database.php';
require APP_ROOT . '/app/Controller.php';

foreach (glob(APP_ROOT . '/app/controllers/*.php') ?: [] as $controllerFile) {
    require_once $controllerFile;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => $secure,
        'samesite' => 'Lax',
        'path' => APP_BASE_URL === '' ? '/' : APP_BASE_URL . '/',
    ]);
    session_start();
}

if (!isset($_SESSION['_token'])) {
    $_SESSION['_token'] = bin2hex(random_bytes(32));
}
