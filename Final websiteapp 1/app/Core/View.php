<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    public static function render(string $view, array $data = [], string $layout = 'main'): void
    {
        extract($data, EXTR_SKIP);

        ob_start();
        require VIEW_PATH . '/' . $view . '.php';
        $content = ob_get_clean();

        require VIEW_PATH . '/layouts/' . $layout . '.php';
    }
}
