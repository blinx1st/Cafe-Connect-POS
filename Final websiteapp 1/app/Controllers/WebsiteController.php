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
        $this->renderWebsite('website/home', 'Cafe Connect | Website', 'website-home');
    }

    public function menu(): void
    {
        $this->renderWebsite('website/menu', 'Cafe Connect | Menu', 'website-menu');
    }

    public function account(): void
    {
        $this->renderWebsite('website/account', 'Cafe Connect | Member Account', 'website-account');
    }

    public function checkout(): void
    {
        $this->renderWebsite('website/checkout', 'Cafe Connect | Checkout', 'website-checkout');
    }

    public function member(): void
    {
        $this->renderWebsite('website/member', 'Cafe Connect | Member Portal', 'website-member');
    }

    private function renderWebsite(string $view, string $title, string $page): void
    {
        $this->view($view, [
            'pageTitle' => $title,
            'page' => $page,
            'section' => 'website',
            'installed' => Database::ready(),
            'appData' => $this->appData($page),
        ]);
    }

    private function appData(string $page): array
    {
        $data = [
            'page' => $page,
            'section' => 'website',
            'products' => [],
            'categories' => [],
            'reviews' => [],
            'staff' => [],
            'branches' => [],
            'member' => null,
        ];

        if (!Database::ready()) {
            return $data;
        }

        $product = new Product();
        $customer = new Customer();
        $data['products'] = $product->active();
        $data['categories'] = $product->categories();
        $data['reviews'] = $customer->reviews();

        $memberId = (int) Session::get('member_customer_id', 0);
        if ($memberId > 0) {
            $member = $customer->lookup((string) $memberId);
            if ($member) {
                $data['member'] = $member;
            } else {
                Session::forget('member_customer_id');
            }
        }

        return $data;
    }
}
