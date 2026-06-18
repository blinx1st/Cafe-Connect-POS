<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Core\RolePolicy;
use App\Models\Campaign;
use App\Models\Dashboard;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Report;
use App\Models\Staff;

final class PosController extends Controller
{
    private const MODULE_TITLES = [
        'checkout' => 'POS bán hàng',
        'orders' => 'Bàn & order',
        'kitchen' => 'Bếp pha chế',
        'dashboard' => 'Dashboard',
        'customers' => 'CRM khách hàng',
        'campaigns' => 'Campaign',
        'inventory' => 'Kho',
        'reports' => 'Báo cáo',
        'products' => 'Sản phẩm',
        'staff' => 'Nhân viên',
        'cash' => 'Thu chi',
    ];

    public function index(): void
    {
        $this->module('checkout');
    }

    public function login(): void
    {
        $this->view('pos/login', [
            'pageTitle' => 'Cafe Connect POS | Login',
            'page' => 'pos-login',
            'section' => 'pos',
            'installed' => Database::ready(),
            'appData' => $this->appData('login'),
        ]);
    }

    public function checkout(): void
    {
        $this->module('checkout');
    }

    public function orders(): void
    {
        $this->module('orders');
    }

    public function kitchen(): void
    {
        $this->module('kitchen');
    }

    public function dashboard(): void
    {
        $this->module('dashboard');
    }

    public function customers(): void
    {
        $this->module('customers');
    }

    public function campaigns(): void
    {
        $this->module('campaigns');
    }

    public function inventory(): void
    {
        $this->module('inventory');
    }

    public function reports(): void
    {
        $this->module('reports');
    }

    public function products(): void
    {
        $this->module('products');
    }

    public function staff(): void
    {
        $this->module('staff');
    }

    public function cash(): void
    {
        $this->module('cash');
    }

    private function module(string $module): void
    {
        $this->view('pos/module', [
            'pageTitle' => 'Cafe Connect POS | ' . (self::MODULE_TITLES[$module] ?? $module),
            'page' => 'pos-' . $module,
            'section' => 'pos',
            'posModule' => $module,
            'installed' => Database::ready(),
            'appData' => $this->appData($module),
        ]);
    }

    private function appData(string $module): array
    {
        $data = [
            'page' => 'pos-' . $module,
            'section' => 'pos',
            'posModule' => $module,
            'products' => [],
            'admin_products' => [],
            'categories' => [],
            'admin_categories' => [],
            'staff' => [],
            'branches' => [],
            'tables' => [],
            'orders' => [],
            'website_orders_pending' => [],
            'kitchen' => [],
            'dashboard' => null,
            'campaigns' => [],
            'inventory' => null,
            'reports' => null,
            'current_session' => null,
            'session_reports' => [],
            'roles' => Staff::ROLES,
            'permissions' => RolePolicy::permissionsPayload(),
        ];

        if (!Database::ready()) {
            return $data;
        }

        $product = new Product();
        $staff = new Staff();
        $data['products'] = [];
        $data['categories'] = $product->categories();
        $data['branches'] = $staff->branches();

        if ($module === 'login') {
            $data['staff'] = $staff->all();
        }

        return $data;
    }
}
