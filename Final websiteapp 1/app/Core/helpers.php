<?php

declare(strict_types=1);

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money(mixed $amount): string
{
    return number_format((float) $amount, 0, ',', '.') . 'đ';
}

function base_url(string $path = ''): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $base = preg_replace('#/public/index\.php$#', '/', $script);
    $base = preg_replace('#/(index|pos|api|install)\.php$#', '/', (string) $base);
    $base = rtrim((string) $base, '/') . '/';

    return $base . ltrim($path, '/');
}

function asset_url(string $path): string
{
    return base_url('assets/' . ltrim($path, '/'));
}

function request_payload(): array
{
    $raw = file_get_contents('php://input') ?: '';
    $json = json_decode($raw, true);
    if (is_array($json)) {
        return $json;
    }

    return $_POST ?: $_GET;
}

function require_field(array $data, string $key, string $label): string
{
    $value = trim((string) ($data[$key] ?? ''));
    if ($value === '') {
        throw new InvalidArgumentException($label . ' is required.');
    }

    return $value;
}

function today_sql(): string
{
    return date('Y-m-d');
}

function role_label(string $role): string
{
    return [
        'waiter' => 'Phục vụ',
        'cashier' => 'Thu ngân',
        'barista' => 'Pha chế',
        'owner' => 'Chủ cửa hàng',
        'manager' => 'Quản lý',
        'marketing' => 'Marketing',
        'admin' => 'Admin',
    ][$role] ?? $role;
}
