<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Customer;
use App\Models\Product;

final class WebsiteController extends Controller
{
    public function home(): void
    {
        $installed = Database::ready();
        $data = [
            'pageTitle' => 'Cafe Connect | Website & Member Portal',
            'page' => 'website',
            'installed' => $installed,
            'appData' => [
                'products' => [],
                'categories' => [],
                'reviews' => [],
                'staff' => [],
                'branches' => [],
            ],
        ];

        if ($installed) {
            $product = new Product();
            $customer = new Customer();
            $data['appData']['products'] = $product->active();
            $data['appData']['categories'] = $product->categories();
            $data['appData']['reviews'] = $customer->reviews();
        }

        $this->view('website/home', $data);
    }
}
