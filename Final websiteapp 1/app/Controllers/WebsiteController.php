<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\RolePolicy;
use App\Models\Product;
use App\Models\Staff;

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

    public function product(): void
    {
        $this->renderWebsite('website/product', 'Cafe Connect | Product Detail', 'website-product');
    }

    public function login(): void
    {
        $this->renderWebsite('website/login', 'Cafe Connect | Member Login', 'website-login');
    }

    public function register(): void
    {
        $this->renderWebsite('website/register', 'Cafe Connect | Member Register', 'website-register');
    }

    public function forgotPassword(): void
    {
        $this->renderWebsite('website/forgot-password', 'Cafe Connect | Forgot Password', 'website-forgot-password');
    }

    public function resetPassword(): void
    {
        $this->renderWebsite('website/reset-password', 'Cafe Connect | Reset Password', 'website-reset-password');
    }

    public function account(): void
    {
        $this->renderWebsite('website/account', 'Cafe Connect | Hồ sơ thành viên', 'website-account');
    }

    public function checkout(): void
    {
        $this->renderWebsite('website/checkout', 'Cafe Connect | Checkout', 'website-checkout');
    }

    public function order(): void
    {
        $this->renderWebsite('website/order', 'Cafe Connect | Order Detail', 'website-order');
    }

    public function momoReturn(): void
    {
        $this->renderWebsite('website/payment-momo-return', 'Cafe Connect | MoMo Payment', 'website-payment-return');
    }

    public function member(): void
    {
        $authState = Database::ready() ? (new AuthController())->memberSession() : ['member' => null, 'web_staff' => null];
        $member = $authState['member'] ?? null;
        $webStaff = $authState['web_staff'] ?? null;
        $staffRole = (string) ($webStaff['staff_role'] ?? '');

        if (!$webStaff && !$member) {
            $this->redirectTo('login');
        }
        if ($member || !RolePolicy::canAccessCustomerCrm($staffRole)) {
            $this->redirectTo('account');
        }

        $this->renderWebsite('website/member', 'Cafe Connect | CRM khách hàng', 'website-member');
    }

    public function feedback(): void
    {
        $this->renderWebsite('website/feedback', 'Cafe Connect | Member Feedback', 'website-feedback');
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
            'web_staff' => null,
            'can_access_customer_crm' => false,
            'payment' => [
                'momo_enabled' => false,
                'momo_provider' => PAYMENT_MOMO_PROVIDER,
                'cod_provider' => PAYMENT_COD_PROVIDER,
            ],
        ];

        if (!Database::ready()) {
            return $data;
        }

        $product = new Product();
        $data['products'] = $product->active();
        $data['categories'] = $product->categories();
        $data['reviews'] = (new \App\Models\Customer())->reviews();
        $data['branches'] = (new Staff())->branches();

        $authState = (new AuthController())->memberSession();
        $data['member'] = $authState['member'] ?? null;
        $data['web_staff'] = $authState['web_staff'] ?? null;
        $data['can_access_customer_crm'] = RolePolicy::canAccessCustomerCrm((string) ($data['web_staff']['staff_role'] ?? ''));
        $data['payment'] = [
            'momo_enabled' => PAYMENT_MOMO_ENABLED && (new \App\Models\MomoPayment())->isConfigured(),
            'momo_provider' => PAYMENT_MOMO_PROVIDER,
            'cod_provider' => PAYMENT_COD_PROVIDER,
        ];

        return $data;
    }

    private function redirectTo(string $path): never
    {
        header('Location: ' . base_url($path));
        exit;
    }
}
