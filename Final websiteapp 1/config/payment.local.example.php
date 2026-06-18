<?php

declare(strict_types=1);

return [
    'momo' => [
        'enabled' => true,
        'provider' => 'MoMo Sandbox',
        'endpoint' => 'https://test-payment.momo.vn/v2/gateway/api/create',
        'partner_code' => 'YOUR_MOMO_PARTNER_CODE',
        'access_key' => 'YOUR_MOMO_ACCESS_KEY',
        'secret_key' => 'YOUR_MOMO_SECRET_KEY',
        // If the app is not hosted at web root, include the full XAMPP subfolder path before /payment and /api.
        'redirect_url' => 'https://your-ngrok-domain.ngrok-free.app/payment/momo-return',
        'ipn_url' => 'https://your-ngrok-domain.ngrok-free.app/api/payment-momo-ipn',
    ],
];
