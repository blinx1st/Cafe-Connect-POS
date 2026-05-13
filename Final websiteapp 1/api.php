<?php

declare(strict_types=1);

$legacyActionMap = [
    'bootstrap' => '/api/bootstrap',
    'member_session' => '/api/member-session',
    'member_login' => '/api/member-login',
    'member_register' => '/api/member-register',
    'member_logout' => '/api/member-logout',
    'member_lookup' => '/api/member-lookup',
    'customer_create' => '/api/customer-create',
    'voucher_validate' => '/api/member-lookup',
    'checkout' => '/api/checkout',
    'dashboard' => '/api/dashboard',
    'campaigns' => '/api/campaigns',
    'create_campaign' => '/api/create-campaign',
];

if (isset($_GET['endpoint'])) {
    $endpoint = '/' . trim((string) $_GET['endpoint'], '/');
    $_GET['route'] = str_starts_with($endpoint, '/api/') ? $endpoint : '/api' . $endpoint;
} elseif (isset($_GET['action'])) {
    $action = (string) $_GET['action'];
    $_GET['route'] = $legacyActionMap[$action] ?? '/api/' . str_replace('_', '-', $action);
} else {
    $_GET['route'] = '/api/bootstrap';
}

require __DIR__ . '/public/index.php';
