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
$router->get('/product', [WebsiteController::class, 'product']);
$router->get('/login', [WebsiteController::class, 'login']);
$router->get('/register', [WebsiteController::class, 'register']);
$router->get('/forgot-password', [WebsiteController::class, 'forgotPassword']);
$router->get('/reset-password', [WebsiteController::class, 'resetPassword']);
$router->get('/account', [WebsiteController::class, 'account']);
$router->get('/member', [WebsiteController::class, 'member']);
$router->get('/checkout', [WebsiteController::class, 'checkout']);
$router->get('/order', [WebsiteController::class, 'order']);
$router->get('/payment/momo-return', [WebsiteController::class, 'momoReturn']);
$router->get('/feedback', [WebsiteController::class, 'feedback']);

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
    '/api/csrf-refresh',
    '/api/bootstrap',
    '/api/website-bootstrap',
    '/api/pos-bootstrap',
    '/api/member-session',
    '/api/member-login',
    '/api/member-staff-adopt',
    '/api/member-register',
    '/api/member-logout',
    '/api/member-profile-update',
    '/api/member-change-password',
    '/api/staff-change-password',
    '/api/staff-change-pin',
    '/api/member-forgot-password',
    '/api/member-reset-password',
    '/api/member-feedback',
    '/api/member-lookup',
    '/api/product-detail',
    '/api/website-orders',
    '/api/website-order-detail',
    '/api/website-order-cancel',
    '/api/voucher-claim',
    '/api/voucher-claim-code',
    '/api/pos-auth-login',
    '/api/pos-auth-current',
    '/api/pos-auth-heartbeat',
    '/api/pos-auth-logout',
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
    '/api/void-order-item',
    '/api/cancel-order',
    '/api/order-status-update',
    '/api/kitchen',
    '/api/checkout-order',
    '/api/refund-invoice',
    '/api/refund-history',
    '/api/receipt',
    '/api/receipt-print-log',
    '/api/payment-demo-create',
    '/api/payment-demo-confirm',
    '/api/payment-momo-ipn',
    '/api/payment-status',
    '/api/checkout-closing',
    '/api/shift-closing',
    '/api/dashboard',
    '/api/campaigns',
    '/api/create-campaign',
    '/api/campaign-save',
    '/api/campaign-delete',
    '/api/campaign-restore',
    '/api/inventory',
    '/api/stock-movement',
    '/api/material-save',
    '/api/material-delete',
    '/api/material-restore',
    '/api/inventory-stock-save',
    '/api/recipe-save',
    '/api/recipe-delete',
    '/api/recipe-restore',
    '/api/cash-transaction',
    '/api/product-list',
    '/api/product-save',
    '/api/product-delete',
    '/api/product-restore',
    '/api/product-image-upload',
    '/api/category-save',
    '/api/content-save',
    '/api/staff-list',
    '/api/staff-save',
    '/api/staff-delete',
    '/api/staff-restore',
    '/api/reports',
    '/api/reports-export',
];

foreach ($apiRoutes as $route) {
    $router->any($route, [ApiController::class, 'handle']);
}

$router->dispatch();
