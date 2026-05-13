<?php

declare(strict_types=1);

const DB_HOST = '127.0.0.1';
const DB_PORT = '3306';
const DB_NAME = 'cafe_connect_crm';
const DB_USER = 'root';
const DB_PASS = '';

function db(bool $withDatabase = true): PDO
{
    static $connections = [];

    $key = $withDatabase ? 'app' : 'server';
    if (isset($connections[$key])) {
        return $connections[$key];
    }

    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT;
    if ($withDatabase) {
        $dsn .= ';dbname=' . DB_NAME;
    }
    $dsn .= ';charset=utf8mb4';

    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if (defined('PDO::MYSQL_ATTR_MULTI_STATEMENTS')) {
        $options[PDO::MYSQL_ATTR_MULTI_STATEMENTS] = true;
    }

    $connections[$key] = new PDO($dsn, DB_USER, DB_PASS, $options);

    return $connections[$key];
}

function database_is_ready(): bool
{
    try {
        db()->query('SELECT 1 FROM products LIMIT 1');
        return true;
    } catch (Throwable $exception) {
        return false;
    }
}
