<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\Campaign;
use App\Models\Customer;
use App\Models\Dashboard;
use App\Models\Inventory;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Product;
use App\Models\Report;
use App\Models\Staff;
use Throwable;

final class ApiController extends Controller
{
    public function handle(): void
    {
        try {
            if (!Database::ready()) {
                $this->json(false, null, 'Database is not installed. Open install.php first.');
            }

            $payload = request_payload();
            $route = $this->route();

            $result = match ($route) {
                '/api/bootstrap', '/api/website-bootstrap' => $this->websiteBootstrap(),
                '/api/pos-bootstrap' => $this->posBootstrap(),
                '/api/member-lookup' => (new Customer())->lookup(require_field($payload, 'identity', 'Phone or email')),
                '/api/customer-create' => (new Customer())->create($payload),
                '/api/newsletter-subscribe' => (new Customer())->newsletterSubscribe($payload),
                '/api/favorite-toggle' => (new Customer())->toggleFavorite($payload),
                '/api/checkout' => (new Invoice())->checkout($payload),
                '/api/orders' => ['orders' => (new Order())->activeOrders(), 'tables' => (new Order())->tables()],
                '/api/create-order' => (new Order())->create($payload),
                '/api/update-order-item' => (new Order())->updateItemStatus($payload),
                '/api/kitchen' => ['kitchen' => (new Order())->kitchenQueue()],
                '/api/checkout-order' => (new Invoice())->checkout($payload),
                '/api/dashboard' => (new Dashboard())->data(),
                '/api/campaigns' => ['campaigns' => (new Campaign())->performance()],
                '/api/create-campaign' => (new Campaign())->create($payload),
                '/api/inventory' => (new Inventory())->overview(),
                '/api/stock-movement' => (new Inventory())->createMovement($payload),
                '/api/cash-transaction' => $this->createCashTransaction($payload),
                '/api/product-save' => (new Product())->save($payload),
                '/api/staff-save' => (new Staff())->save($payload),
                '/api/reports' => (new Report())->data(),
                default => throw new \InvalidArgumentException('Unknown API route: ' . $route),
            };

            $this->json(true, $result);
        } catch (Throwable $exception) {
            $this->json(false, null, $exception->getMessage());
        }
    }

    private function route(): string
    {
        if (isset($_GET['route'])) {
            return '/' . trim((string) $_GET['route'], '/');
        }
        if (isset($_GET['endpoint'])) {
            return '/' . trim((string) $_GET['endpoint'], '/');
        }
        if (isset($_GET['action'])) {
            return match ((string) $_GET['action']) {
                'bootstrap' => '/api/bootstrap',
                'member_lookup' => '/api/member-lookup',
                'customer_create' => '/api/customer-create',
                'checkout' => '/api/checkout',
                'dashboard' => '/api/dashboard',
                'campaigns' => '/api/campaigns',
                'create_campaign' => '/api/create-campaign',
                default => '/api/' . str_replace('_', '-', (string) $_GET['action']),
            };
        }

        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $pos = strpos($path, '/api/');
        return $pos === false ? '/api/bootstrap' : substr($path, $pos);
    }

    private function websiteBootstrap(): array
    {
        $product = new Product();
        return [
            'products' => $product->active(),
            'categories' => $product->categories(),
            'reviews' => (new Customer())->reviews(),
        ];
    }

    private function posBootstrap(): array
    {
        $product = new Product();
        $staff = new Staff();
        $order = new Order();

        return [
            'products' => $product->active(),
            'categories' => $product->categories(),
            'staff' => $staff->all(),
            'branches' => $staff->branches(),
            'tables' => $order->tables(),
            'orders' => $order->activeOrders(),
            'kitchen' => $order->kitchenQueue(),
            'dashboard' => (new Dashboard())->data(),
            'campaigns' => (new Campaign())->performance(),
            'inventory' => (new Inventory())->overview(),
            'reports' => (new Report())->data(),
            'roles' => Staff::ROLES,
        ];
    }

    private function createCashTransaction(array $payload): array
    {
        $db = Database::pdo();
        $db->prepare(
            "INSERT INTO cash_transactions (branch_id, staff_id, transaction_type, reason, amount, created_at)
             VALUES (:branch_id, :staff_id, :transaction_type, :reason, :amount, NOW())"
        )->execute([
            'branch_id' => max(1, (int) ($payload['branch_id'] ?? 1)),
            'staff_id' => max(1, (int) ($payload['staff_id'] ?? 1)),
            'transaction_type' => in_array(($payload['transaction_type'] ?? 'in'), ['in', 'out'], true) ? $payload['transaction_type'] : 'in',
            'reason' => trim((string) ($payload['reason'] ?? 'POS transaction')),
            'amount' => max(0, (float) ($payload['amount'] ?? 0)),
        ]);

        return (new Report())->data();
    }
}
