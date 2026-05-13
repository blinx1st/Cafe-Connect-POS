<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Controllers\ApiController;
use App\Controllers\PosController;
use App\Controllers\WebsiteController;
use App\Core\Router;

$router = new Router();

$router->get('/', [WebsiteController::class, 'home']);
$router->get('/member', [WebsiteController::class, 'home']);
$router->get('/checkout', [WebsiteController::class, 'home']);

$router->get('/pos', [PosController::class, 'index']);
$router->get('/pos/login', [PosController::class, 'index']);
$router->get('/pos/orders', [PosController::class, 'index']);
$router->get('/pos/kitchen', [PosController::class, 'index']);
$router->get('/pos/dashboard', [PosController::class, 'index']);
$router->get('/pos/campaigns', [PosController::class, 'index']);

$apiRoutes = [
    '/api/bootstrap',
    '/api/website-bootstrap',
    '/api/pos-bootstrap',
    '/api/member-lookup',
    '/api/customer-create',
    '/api/newsletter-subscribe',
    '/api/favorite-toggle',
    '/api/checkout',
    '/api/orders',
    '/api/create-order',
    '/api/update-order-item',
    '/api/kitchen',
    '/api/checkout-order',
    '/api/dashboard',
    '/api/campaigns',
    '/api/create-campaign',
    '/api/inventory',
    '/api/stock-movement',
    '/api/cash-transaction',
    '/api/product-save',
    '/api/staff-save',
    '/api/reports',
];

foreach ($apiRoutes as $route) {
    $router->any($route, [ApiController::class, 'handle']);
}

$router->dispatch();
