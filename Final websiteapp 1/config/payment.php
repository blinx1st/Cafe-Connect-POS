<?php

declare(strict_types=1);

return [
    'demo_provider' => getenv('PAYMENT_DEMO_PROVIDER') ?: 'Cafe Connect DemoPay',
    'cod_provider' => getenv('PAYMENT_COD_PROVIDER') ?: 'Cash on Delivery',
];
