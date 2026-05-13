<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Campaign;
use App\Models\Dashboard;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\Product;
use App\Models\Report;
use App\Models\Staff;

final class PosController extends Controller
{
    public function index(): void
    {
        $installed = Database::ready();
        $appData = [
            'products' => [],
            'categories' => [],
            'staff' => [],
            'branches' => [],
            'tables' => [],
            'orders' => [],
            'kitchen' => [],
            'dashboard' => null,
            'campaigns' => [],
            'inventory' => null,
            'reports' => null,
            'roles' => Staff::ROLES,
        ];

        if ($installed) {
            $product = new Product();
            $staff = new Staff();
            $order = new Order();
            $appData['products'] = $product->active();
            $appData['categories'] = $product->categories();
            $appData['staff'] = $staff->all();
            $appData['branches'] = $staff->branches();
            $appData['tables'] = $order->tables();
            $appData['orders'] = $order->activeOrders();
            $appData['kitchen'] = $order->kitchenQueue();
            $appData['dashboard'] = (new Dashboard())->data();
            $appData['campaigns'] = (new Campaign())->performance();
            $appData['inventory'] = (new Inventory())->overview();
            $appData['reports'] = (new Report())->data();
        }

        $this->view('pos/index', [
            'pageTitle' => 'Cafe Connect POS',
            'page' => 'pos',
            'installed' => $installed,
            'appData' => $appData,
        ]);
    }
}
