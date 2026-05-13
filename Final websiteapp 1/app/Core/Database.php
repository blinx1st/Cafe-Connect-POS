<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

final class Database
{
    private static array $connections = [];

    public static function pdo(bool $withDatabase = true): PDO
    {
        $key = $withDatabase ? 'app' : 'server';
        if (isset(self::$connections[$key])) {
            return self::$connections[$key];
        }

        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT;
        if ($withDatabase) {
            $dsn .= ';dbname=' . DB_NAME;
        }
        $dsn .= ';charset=utf8mb4';

        self::$connections[$key] = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return self::$connections[$key];
    }

    public static function ready(): bool
    {
        try {
            self::pdo()->query(
                "SELECT
                    (SELECT COUNT(*) FROM product_categories) AS product_categories_count,
                    (SELECT COUNT(*) FROM staff_shifts) AS staff_shifts_count,
                    (SELECT COUNT(*) FROM service_orders) AS service_orders_count,
                    (SELECT COUNT(*) FROM newsletter_subscribers) AS newsletter_count"
            );
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
