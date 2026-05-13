<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    protected function view(string $view, array $data = [], string $layout = 'main'): void
    {
        View::render($view, $data, $layout);
    }

    protected function json(bool $ok, mixed $data = null, string $message = ''): never
    {
        Response::json($ok, $data, $message);
    }
}
