<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function json(bool $ok, mixed $data = null, string $message = ''): never
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(
            ['ok' => $ok, 'data' => $data, 'message' => $message],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }
}
