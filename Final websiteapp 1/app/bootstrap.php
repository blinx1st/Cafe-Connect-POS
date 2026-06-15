<?php

declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));
define('APP_PATH', APP_ROOT . '/app');
define('VIEW_PATH', APP_PATH . '/Views');

$appConfig = is_file(APP_ROOT . '/config/app.php') ? require APP_ROOT . '/config/app.php' : [];
$paymentConfig = is_file(APP_ROOT . '/config/payment.php') ? require APP_ROOT . '/config/payment.php' : [];

date_default_timezone_set((string) ($appConfig['timezone'] ?? 'Asia/Bangkok'));

define('APP_NAME', (string) ($appConfig['name'] ?? 'Cafe Connect'));
define('APP_ENV', (string) ($appConfig['env'] ?? 'local'));
define('APP_DEBUG', (bool) ($appConfig['debug'] ?? true));
define('APP_ALLOW_SAMPLE_RESET', (bool) ($appConfig['allow_sample_reset'] ?? (APP_ENV === 'local')));
define('PAYMENT_DEMO_PROVIDER', (string) ($paymentConfig['demo_provider'] ?? 'Cafe Connect DemoPay'));
define('PAYMENT_COD_PROVIDER', (string) ($paymentConfig['cod_provider'] ?? 'Cash on Delivery'));

define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'cafe_connect_crm');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

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
