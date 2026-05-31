<?php

declare(strict_types=1);

namespace App\Core;

use Throwable;

final class AppLogger
{
    public static function error(Throwable $exception, array $context = []): void
    {
        self::write('error', $exception->getMessage(), $context + [
            'exception' => $exception::class,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);
    }

    public static function info(string $message, array $context = []): void
    {
        self::write('info', $message, $context);
    }

    private static function write(string $level, string $message, array $context): void
    {
        $directory = APP_ROOT . '/storage/logs';
        if (!is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        $line = json_encode([
            'time' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($line !== false) {
            file_put_contents($directory . '/app.log', $line . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
    }
}
