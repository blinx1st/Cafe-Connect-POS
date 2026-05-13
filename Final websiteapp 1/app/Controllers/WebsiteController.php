<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Session;
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
                'member' => null,
            ],
        ];

        if ($installed) {
            $product = new Product();
            $customer = new Customer();
            $data['appData']['products'] = $product->active();
            $data['appData']['categories'] = $product->categories();
            $data['appData']['reviews'] = $customer->reviews();
            $memberId = (int) Session::get('member_customer_id', 0);
            if ($memberId > 0) {
                $member = $customer->lookup((string) $memberId);
                if ($member) {
                    $data['appData']['member'] = $member;
                } else {
                    Session::forget('member_customer_id');
                }
            }
        }

        $this->view('website/home', $data);
    }
}
