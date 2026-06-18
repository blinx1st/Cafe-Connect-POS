<?php

declare(strict_types=1);

return [
    'demo_provider' => getenv('PAYMENT_DEMO_PROVIDER') ?: 'Cafe Connect DemoPay',
    'cod_provider' => getenv('PAYMENT_COD_PROVIDER') ?: 'Cash on Delivery',
    'momo' => [
        'enabled' => filter_var(getenv('MOMO_ENABLED') ?: false, FILTER_VALIDATE_BOOLEAN),
        'provider' => getenv('MOMO_PROVIDER') ?: 'MoMo Sandbox',
        'endpoint' => getenv('MOMO_ENDPOINT') ?: 'https://test-payment.momo.vn/v2/gateway/api/create',
        'partner_code' => getenv('MOMO_PARTNER_CODE') ?: '',
        'access_key' => getenv('MOMO_ACCESS_KEY') ?: '',
        'secret_key' => getenv('MOMO_SECRET_KEY') ?: '',
        'redirect_url' => getenv('MOMO_REDIRECT_URL') ?: '',
        'ipn_url' => getenv('MOMO_IPN_URL') ?: '',
    ],
];
