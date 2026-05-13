<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function money(mixed $amount): string
{
    return number_format((float) $amount, 0, ',', '.') . 'đ';
}

function app_json(bool $ok, mixed $data = null, string $message = ''): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(
        ['ok' => $ok, 'data' => $data, 'message' => $message],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function request_data(): array
{
    $raw = file_get_contents('php://input');
    $json = json_decode($raw ?: '', true);

    if (is_array($json)) {
        return $json;
    }

    return $_POST ?: $_GET;
}

function require_value(array $data, string $key, string $label): string
{
    $value = trim((string) ($data[$key] ?? ''));
    if ($value === '') {
        throw new InvalidArgumentException($label . ' is required.');
    }

    return $value;
}

function product_image(array $product): string
{
    $name = strtolower((string) ($product['product_name'] ?? ''));
    $category = strtolower((string) ($product['category'] ?? ''));

    if (str_contains($name, 'tiramisu')) {
        return 'assets/images/dessert-1.png';
    }
    if (str_contains($name, 'croissant')) {
        return 'assets/images/dessert-4.png';
    }
    if ($category === 'tea' || str_contains($name, 'matcha') || str_contains($name, 'tea')) {
        return 'assets/images/coffee-4.png';
    }
    if ($category === 'smoothie') {
        return 'assets/images/coffee-2.png';
    }

    $images = [
        'assets/images/coffee-1.png',
        'assets/images/coffee-2.png',
        'assets/images/coffee-3.png',
        'assets/images/coffee-4.png',
    ];

    return $images[((int) ($product['id'] ?? 1) - 1) % count($images)];
}

function normalize_payment_method(string $method): string
{
    return match ($method) {
        'cash', 'card', 'e_wallet' => $method,
        'wallet' => 'e_wallet',
        default => 'cash',
    };
}

function payment_provider(?string $method): ?string
{
    return match ($method) {
        'card' => 'Demo Card',
        'e_wallet' => 'Demo Wallet',
        default => null,
    };
}

function today_sql(): string
{
    return date('Y-m-d');
}

function now_sql(): string
{
    return date('Y-m-d H:i:s');
}
