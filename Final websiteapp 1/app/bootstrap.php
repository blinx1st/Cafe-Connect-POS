<?php

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_PATH', APP_ROOT . '/app');
define('VIEW_PATH', APP_PATH . '/Views');
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'cafe_connect_crm');
define('DB_USER', 'root');
define('DB_PASS', '');

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $path = APP_PATH . '/' . $relative . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

require_once APP_PATH . '/Core/helpers.php';

use App\Core\Session;

Session::start();
