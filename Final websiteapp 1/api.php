<?php

declare(strict_types=1);

$legacyActionMap = [
    'bootstrap' => '/api/bootstrap',
    'csrf_refresh' => '/api/csrf-refresh',
    'member_session' => '/api/member-session',
    'member_login' => '/api/member-login',
    'member_staff_adopt' => '/api/member-staff-adopt',
    'member_register' => '/api/member-register',
    'member_logout' => '/api/member-logout',
    'member_profile_update' => '/api/member-profile-update',
    'member_change_password' => '/api/member-change-password',
    'member_forgot_password' => '/api/member-forgot-password',
    'member_reset_password' => '/api/member-reset-password',
    'member_lookup' => '/api/member-lookup',
    'product_detail' => '/api/product-detail',
    'website_orders' => '/api/website-orders',
    'website_order_detail' => '/api/website-order-detail',
    'website_order_cancel' => '/api/website-order-cancel',
    'voucher_claim' => '/api/voucher-claim',
    'voucher_claim_code' => '/api/voucher-claim-code',
    'customer_create' => '/api/customer-create',
    'pos_auth_login' => '/api/pos-auth-login',
    'pos_auth_current' => '/api/pos-auth-current',
    'pos_auth_heartbeat' => '/api/pos-auth-heartbeat',
    'pos_auth_logout' => '/api/pos-auth-logout',
    'pos_session_login' => '/api/pos-session-login',
    'pos_session_current' => '/api/pos-session-current',
    'pos_session_heartbeat' => '/api/pos-session-heartbeat',
    'pos_session_logout' => '/api/pos-session-logout',
    'pos_session_report' => '/api/pos-session-report',
    'voucher_validate' => '/api/member-lookup',
    'checkout' => '/api/checkout',
    'dashboard' => '/api/dashboard',
    'campaigns' => '/api/campaigns',
    'create_campaign' => '/api/create-campaign',
    'campaign_save' => '/api/campaign-save',
    'campaign_delete' => '/api/campaign-delete',
    'campaign_restore' => '/api/campaign-restore',
    'refund_invoice' => '/api/refund-invoice',
    'void_order_item' => '/api/void-order-item',
    'cancel_order' => '/api/cancel-order',
    'checkout_closing' => '/api/checkout-closing',
    'shift_closing' => '/api/shift-closing',
    'payment_demo_create' => '/api/payment-demo-create',
    'payment_demo_confirm' => '/api/payment-demo-confirm',
    'order_status_update' => '/api/order-status-update',
    'receipt_print_log' => '/api/receipt-print-log',
    'reports_export' => '/api/reports-export',
    'product_list' => '/api/product-list',
    'product_delete' => '/api/product-delete',
    'product_restore' => '/api/product-restore',
    'product_image_upload' => '/api/product-image-upload',
    'category_save' => '/api/category-save',
    'content_save' => '/api/content-save',
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
