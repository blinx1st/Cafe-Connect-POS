<?php

declare(strict_types=1);

return [
    'name' => getenv('APP_NAME') ?: 'Cafe Connect',
    'env' => getenv('APP_ENV') ?: 'local',
    'debug' => (getenv('APP_DEBUG') ?: '1') === '1',
    'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Bangkok',
    'allow_sample_reset' => (getenv('APP_ENV') ?: 'local') === 'local',
];
