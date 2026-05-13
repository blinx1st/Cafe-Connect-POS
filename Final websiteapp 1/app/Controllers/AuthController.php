<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class AuthController extends Controller
{
    public function login(): void
    {
        (new PosController())->index();
    }
}
