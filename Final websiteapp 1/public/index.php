<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Controllers\ApiController;
use App\Controllers\PosController;
use App\Controllers\WebsiteController;
use App\Core\Router;

$router = new Router();

$router->get('/', [WebsiteController::class, 'home']);
$router->get('/menu', [WebsiteController::class, 'menu']);
$router->get('/account', [WebsiteController::class, 'account']);
$router->get('/member', [WebsiteController::class, 'member']);
$router->get('/checkout', [WebsiteController::class, 'checkout']);

$router->get('/pos', [PosController::class, 'index']);
$router->get('/pos/login', [PosController::class, 'login']);
$router->get('/pos/checkout', [PosController::class, 'checkout']);
$router->get('/pos/orders', [PosController::class, 'orders']);
$router->get('/pos/kitchen', [PosController::class, 'kitchen']);
$router->get('/pos/dashboard', [PosController::class, 'dashboard']);
$router->get('/pos/customers', [PosController::class, 'customers']);
$router->get('/pos/campaigns', [PosController::class, 'campaigns']);
$router->get('/pos/inventory', [PosController::class, 'inventory']);
$router->get('/pos/reports', [PosController::class, 'reports']);
$router->get('/pos/products', [PosController::class, 'products']);
$router->get('/pos/staff', [PosController::class, 'staff']);
$router->get('/pos/cash', [PosController::class, 'cash']);

$apiRoutes = [
    '/api/bootstrap',
    '/api/website-bootstrap',
    '/api/pos-bootstrap',
    '/api/member-session',
    '/api/member-login',
    '/api/member-register',
    '/api/member-logout',
    '/api/member-lookup',
    '/api/pos-session-login',
    '/api/pos-session-current',
    '/api/pos-session-heartbeat',
    '/api/pos-session-logout',
    '/api/pos-session-report',
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
