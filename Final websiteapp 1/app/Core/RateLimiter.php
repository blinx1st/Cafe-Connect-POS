<?php

declare(strict_types=1);

namespace App\Core;

use InvalidArgumentException;

final class RateLimiter
{
    public static function hit(string $name, string $identity, int $maxAttempts, int $windowSeconds): void
    {
        $key = self::key($name, $identity);
        $path = self::path($key);
        $now = time();
        $attempts = self::read($path);
        $attempts = array_values(array_filter(
            $attempts,
            static fn (int $timestamp): bool => $timestamp > ($now - $windowSeconds)
        ));

        if (count($attempts) >= $maxAttempts) {
            $oldest = min($attempts);
            $retryAfter = max(1, $windowSeconds - ($now - $oldest));
            throw new InvalidArgumentException(
                'Qua nhieu lan thu. Vui long thu lai sau ' . (int) ceil($retryAfter / 60) . ' phut.'
            );
        }

        $attempts[] = $now;
        self::write($path, $attempts);
    }

    public static function clear(string $name, string $identity): void
    {
        $path = self::path(self::key($name, $identity));
        if (is_file($path)) {
            @unlink($path);
        }
    }

    public static function identity(string $raw): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'cli');
        $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'cli'), 0, 80);

        return strtolower(trim($raw)) . '|' . $ip . '|' . $userAgent;
    }

    private static function key(string $name, string $identity): string
    {
        return preg_replace('/[^a-z0-9_-]/i', '_', $name) . '_' . hash('sha256', $identity);
    }

    private static function path(string $key): string
    {
        $dir = APP_ROOT . '/storage/rate-limit';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir . '/' . $key . '.json';
    }

    /**
     * @return list<int>
     */
    private static function read(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $json = file_get_contents($path);
        $data = $json !== false ? json_decode($json, true) : null;
        if (!is_array($data)) {
            return [];
        }

        return array_values(array_filter(array_map('intval', $data)));
    }

    /**
     * @param list<int> $attempts
     */
    private static function write(string $path, array $attempts): void
    {
        file_put_contents($path, json_encode($attempts, JSON_THROW_ON_ERROR), LOCK_EX);
    }
}

