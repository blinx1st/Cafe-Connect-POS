<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\AppLogger;
use App\Core\Database;
use App\Core\RolePolicy;
use App\Core\Session;
use App\Models\Campaign;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Dashboard;
use App\Models\Inventory;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\PosSession;
use App\Models\Product;
use App\Models\Report;
use App\Models\Staff;
use App\Models\StaffAuthSession;
use InvalidArgumentException;
use Throwable;

final class ApiController extends Controller
{
    public function handle(): void
    {
        $route = '/api/bootstrap';
        try {
            if (!Database::ready()) {
                $this->json(false, null, 'Database is not installed. Open install.php first.');
            }

            $payload = request_payload();
            $route = $this->route();
            $this->validateCsrf($route);
            $auth = new AuthController();

            $result = match ($route) {
                '/api/csrf-refresh' => ['csrf_token' => Session::refreshCsrfToken()],
                '/api/bootstrap', '/api/website-bootstrap' => $this->websiteBootstrap(),
                '/api/pos-bootstrap' => $this->posBootstrap($payload),
                '/api/member-session' => $auth->memberSession(),
                '/api/member-login' => $auth->memberLogin($payload),
                '/api/member-staff-adopt' => $auth->staffWebAdopt($payload),
                '/api/member-register' => $auth->memberRegister($payload),
                '/api/member-logout' => $auth->memberLogout(),
                '/api/member-profile-update' => $auth->memberProfileUpdate($payload),
                '/api/member-change-password' => $auth->memberChangePassword($payload),
                '/api/member-forgot-password' => $auth->memberForgotPassword($payload),
                '/api/member-reset-password' => $auth->memberResetPassword($payload),
                '/api/member-lookup' => (new Customer())->lookup(require_field($payload, 'identity', 'Phone or email')),
                '/api/pos-auth-login' => (new StaffAuthSession())->login($payload),
                '/api/pos-auth-current' => (new StaffAuthSession())->current($payload),
                '/api/pos-auth-heartbeat' => (new StaffAuthSession())->heartbeat($payload),
                '/api/pos-auth-logout' => (new StaffAuthSession())->logout($payload),
                '/api/pos-session-login' => (new PosSession())->login($payload),
                '/api/pos-session-current' => (new PosSession())->current($payload),
                '/api/pos-session-heartbeat' => (new PosSession())->heartbeat($payload),
                '/api/pos-session-logout' => (new PosSession())->logout($payload),
                '/api/pos-session-report' => $this->withEndpoint($route, $auth, $payload, fn () => ['session_reports' => (new PosSession())->report($payload)]),
                '/api/customer-create' => $this->withEndpoint($route, $auth, $payload, fn () => (new Customer())->create($payload)),
                '/api/newsletter-subscribe' => (new Customer())->newsletterSubscribe($payload),
                '/api/favorite-toggle' => (new Customer())->toggleFavorite($payload),
                '/api/checkout' => $this->checkout($payload, $auth),
                '/api/orders' => $this->withEndpoint($route, $auth, $payload, fn () => ['orders' => (new Order())->activeOrders(), 'tables' => (new Order())->tables()]),
                '/api/create-order' => $this->withEndpoint($route, $auth, $payload, fn (array $staff) => (new Order())->create($payload + ['staff_role' => $staff['staff_role']])),
                '/api/update-order-item' => $this->withEndpoint($route, $auth, $payload, fn (array $staff) => (new Order())->updateItemStatus($payload + ['staff_role' => $staff['staff_role']])),
                '/api/void-order-item' => $this->withEndpoint($route, $auth, $payload, fn (array $staff) => (new Order())->voidItem($payload + ['staff_role' => $staff['staff_role']])),
                '/api/cancel-order' => $this->withEndpoint($route, $auth, $payload, fn (array $staff) => (new Order())->cancel($payload + ['staff_role' => $staff['staff_role']])),
                '/api/order-status-update' => $this->withEndpoint($route, $auth, $payload, fn (array $staff) => (new Order())->updateOrderStatus($payload + ['staff_role' => $staff['staff_role']])),
                '/api/kitchen' => $this->withEndpoint($route, $auth, $payload, fn () => ['kitchen' => (new Order())->kitchenQueue()]),
                '/api/checkout-order' => $this->withEndpoint($route, $auth, $payload, fn () => (new Invoice())->checkout($payload)),
                '/api/refund-invoice' => $this->withEndpoint($route, $auth, $payload, fn () => (new Invoice())->refund($payload)),
                '/api/receipt' => $this->withEndpoint($route, $auth, $payload, fn () => (new Invoice())->receipt((int) ($payload['invoice_id'] ?? 0))),
                '/api/receipt-print-log' => $this->withEndpoint($route, $auth, $payload, fn () => (new Invoice())->logReceiptPrint($payload)),
                '/api/payment-demo-create' => $this->paymentDemoCreate($payload),
                '/api/checkout-closing' => $this->withEndpoint($route, $auth, $payload, fn () => (new PosSession())->logout($payload)),
                '/api/dashboard' => $this->withEndpoint($route, $auth, $payload, fn () => (new Dashboard())->data()),
                '/api/campaigns' => $this->withEndpoint($route, $auth, $payload, fn () => ['campaigns' => (new Campaign())->performance()]),
                '/api/create-campaign' => $this->withEndpoint($route, $auth, $payload, fn () => (new Campaign())->create($payload)),
                '/api/inventory' => $this->withEndpoint($route, $auth, $payload, fn () => (new Inventory())->overview()),
                '/api/stock-movement' => $this->withEndpoint($route, $auth, $payload, fn () => (new Inventory())->createMovement($payload)),
                '/api/cash-transaction' => $this->withEndpoint($route, $auth, $payload, fn () => $this->createCashTransaction($payload)),
                '/api/product-save' => $this->withEndpoint($route, $auth, $payload, fn () => $this->saveProduct($payload)),
                '/api/staff-save' => $this->withEndpoint($route, $auth, $payload, fn () => $this->saveStaff($payload)),
                '/api/reports' => $this->withEndpoint($route, $auth, $payload, fn () => (new Report())->data($payload)),
                '/api/reports-export' => $this->withEndpoint($route, $auth, $payload, fn () => (new Report())->exportCsv($payload)),
                default => throw new InvalidArgumentException('Unknown API route: ' . $route),
            };

            $this->json(true, $result);
        } catch (Throwable $exception) {
            if (!$exception instanceof InvalidArgumentException) {
                AppLogger::error($exception, ['route' => $route]);
            }
            $message = $exception instanceof InvalidArgumentException
                ? $exception->getMessage()
                : 'Lỗi hệ thống. Vui lòng kiểm tra storage/logs/app.log.';
            $this->json(false, null, $message);
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
                'csrf_refresh' => '/api/csrf-refresh',
                'member_session' => '/api/member-session',
                'member_login' => '/api/member-login',
                'member_staff_adopt' => '/api/member-staff-adopt',
                'member_register' => '/api/member-register',
                'member_logout' => '/api/member-logout',
                'member_profile_update' => '/api/member-profile-update',
                'member_change_password' => '/api/member-change-password',
                'member_forgot_password' => '/api/member-forgot-password',
                'member_reset_password' => '/api/member-reset-password',
                'member_lookup' => '/api/member-lookup',
                'customer_create' => '/api/customer-create',
                'checkout' => '/api/checkout',
                'dashboard' => '/api/dashboard',
                'campaigns' => '/api/campaigns',
                'create_campaign' => '/api/create-campaign',
                'pos_auth_login' => '/api/pos-auth-login',
                'pos_auth_current' => '/api/pos-auth-current',
                'pos_auth_heartbeat' => '/api/pos-auth-heartbeat',
                'pos_auth_logout' => '/api/pos-auth-logout',
                'pos_session_login' => '/api/pos-session-login',
                'pos_session_current' => '/api/pos-session-current',
                'pos_session_heartbeat' => '/api/pos-session-heartbeat',
                'pos_session_logout' => '/api/pos-session-logout',
                'pos_session_report' => '/api/pos-session-report',
                'refund_invoice' => '/api/refund-invoice',
                'void_order_item' => '/api/void-order-item',
                'cancel_order' => '/api/cancel-order',
                'checkout_closing' => '/api/checkout-closing',
                'payment_demo_create' => '/api/payment-demo-create',
                'order_status_update' => '/api/order-status-update',
                'receipt_print_log' => '/api/receipt-print-log',
                'reports_export' => '/api/reports-export',
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

    private function validateCsrf(string $route): void
    {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
            return;
        }

        $readOnly = [
            '/api/csrf-refresh',
            '/api/bootstrap',
            '/api/website-bootstrap',
            '/api/pos-bootstrap',
            '/api/member-session',
            '/api/member-lookup',
            '/api/pos-auth-current',
            '/api/pos-session-current',
            '/api/pos-session-report',
            '/api/orders',
            '/api/kitchen',
            '/api/dashboard',
            '/api/campaigns',
            '/api/inventory',
            '/api/reports',
            '/api/receipt',
        ];

        if (in_array($route, $readOnly, true)) {
            return;
        }

        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!Session::verifyCsrfToken(is_string($token) ? $token : null)) {
            throw new InvalidArgumentException('CSRF token không hợp lệ. Vui lòng tải lại trang.');
        }
    }

    private function posBootstrap(array $payload): array
    {
        $product = new Product();
        $staff = new Staff();
        $order = new Order();
        $posSession = new PosSession();
        $staffAuthSession = new StaffAuthSession();
        $currentSession = null;
        $currentAuthSession = null;
        $role = '';

        if (!empty($payload['pos_session_id']) && !empty($payload['session_token'])) {
            try {
                $currentSession = $posSession->requireOpen($payload);
                $role = (string) ($currentSession['staff_role'] ?? '');
            } catch (Throwable $exception) {
                throw new InvalidArgumentException('POS session is invalid or expired.');
            }
        }
        if (!empty($payload['auth_session_id']) && !empty($payload['auth_token'])) {
            try {
                $currentAuthSession = $staffAuthSession->current($payload);
            } catch (Throwable) {
                $currentAuthSession = null;
            }
        }

        $data = [
            'products' => $product->active(),
            'categories' => $product->categories(),
            'staff' => [],
            'branches' => $staff->branches(),
            'tables' => [],
            'orders' => [],
            'kitchen' => [],
            'dashboard' => null,
            'campaigns' => [],
            'inventory' => [],
            'reports' => [],
            'current_session' => $currentSession,
            'current_auth_session' => $currentAuthSession,
            'session_reports' => [],
            'roles' => Staff::ROLES,
            'permissions' => RolePolicy::permissionsPayload(),
            'allowed_modules' => $role !== '' ? RolePolicy::modulesForRole($role) : [],
        ];

        if ($role === '') {
            return $data;
        }

        if (RolePolicy::canAccessModule($role, 'orders')) {
            $data['tables'] = $order->tables();
            $data['orders'] = $order->activeOrders();
        }
        if (RolePolicy::canAccessModule($role, 'kitchen')) {
            $data['kitchen'] = $order->kitchenQueue();
        }
        if (RolePolicy::canAccessModule($role, 'dashboard') || RolePolicy::canAccessModule($role, 'reports')) {
            $data['dashboard'] = (new Dashboard())->data();
        }
        if (RolePolicy::canAccessModule($role, 'campaigns')) {
            $data['campaigns'] = (new Campaign())->performance();
        }
        if (RolePolicy::canAccessModule($role, 'inventory')) {
            $data['inventory'] = (new Inventory())->overview();
        }
        if (RolePolicy::canAccessModule($role, 'reports')) {
            $sessionReports = $posSession->report($payload);
            $reports = (new Report())->data();
            $reports['session_reports'] = $sessionReports;
            $data['reports'] = $reports;
            $data['session_reports'] = $sessionReports;
        }
        if (RolePolicy::canAccessModule($role, 'staff')) {
            $data['staff'] = $staff->all();
        }

        return $data;
    }

    private function createCashTransaction(array $payload): array
    {
        $db = Database::pdo();
        $amount = max(0, (float) ($payload['amount'] ?? 0));
        $transactionType = in_array(($payload['transaction_type'] ?? 'in'), ['in', 'out'], true) ? $payload['transaction_type'] : 'in';
        $reason = trim((string) ($payload['reason'] ?? 'POS transaction'));
        $db->prepare(
            "INSERT INTO cash_transactions (branch_id, staff_id, pos_session_id, transaction_type, reason, amount, created_at)
             VALUES (:branch_id, :staff_id, :pos_session_id, :transaction_type, :reason, :amount, NOW())"
        )->execute([
            'branch_id' => max(1, (int) ($payload['branch_id'] ?? 1)),
            'staff_id' => max(1, (int) ($payload['staff_id'] ?? 1)),
            'pos_session_id' => max(1, (int) ($payload['pos_session_id'] ?? 0)),
            'transaction_type' => $transactionType,
            'reason' => $reason,
            'amount' => $amount,
        ]);
        $cashTransactionId = (int) $db->lastInsertId();

        (new PosSession())->logFromPayload($payload, 'cash_transaction', [
            'entity_type' => 'cash_transaction',
            'entity_id' => $cashTransactionId,
            'amount' => $amount,
            'status_to' => $transactionType,
            'note' => $reason,
        ]);
        (new AuditLog())->record([
            'actor_type' => 'staff',
            'actor_id' => (int) ($payload['staff_id'] ?? 0),
            'actor_role' => (string) ($payload['staff_role'] ?? ''),
            'action' => 'cash_transaction',
            'entity_type' => 'cash_transaction',
            'entity_id' => $cashTransactionId,
            'metadata' => ['amount' => $amount, 'transaction_type' => $transactionType, 'reason' => $reason],
        ]);

        return (new Report())->data();
    }

    private function saveProduct(array $payload): array
    {
        $result = (new Product())->save($payload);
        (new PosSession())->logFromPayload($payload, 'product_save', [
            'entity_type' => 'product',
            'entity_id' => (int) ($result['id'] ?? $payload['id'] ?? 0),
            'amount' => max(0, (float) ($payload['price'] ?? 0)),
            'status_to' => (string) ($payload['status'] ?? 'active'),
            'note' => (string) ($payload['product_name'] ?? 'Product save'),
        ]);
        (new AuditLog())->record([
            'actor_type' => 'staff',
            'actor_id' => (int) ($payload['staff_id'] ?? 0),
            'actor_role' => (string) ($payload['staff_role'] ?? ''),
            'action' => 'product_save',
            'entity_type' => 'product',
            'entity_id' => (int) ($result['id'] ?? $payload['id'] ?? 0),
            'metadata' => ['product_name' => (string) ($payload['product_name'] ?? '')],
        ]);

        return $result;
    }

    private function saveStaff(array $payload): array
    {
        $result = (new Staff())->save($payload);
        (new PosSession())->logFromPayload($payload, 'staff_save', [
            'entity_type' => 'staff',
            'entity_id' => (int) ($result['id'] ?? $payload['id'] ?? 0),
            'status_to' => (string) ($payload['staff_role'] ?? 'staff'),
            'note' => (string) ($payload['staff_name'] ?? 'Staff save'),
        ]);
        (new AuditLog())->record([
            'actor_type' => 'staff',
            'actor_id' => (int) ($payload['staff_id'] ?? 0),
            'actor_role' => (string) ($payload['staff_role'] ?? ''),
            'action' => 'staff_save',
            'entity_type' => 'staff',
            'entity_id' => (int) ($result['id'] ?? $payload['id'] ?? 0),
            'metadata' => ['staff_name' => (string) ($payload['staff_name'] ?? '')],
        ]);

        return $result;
    }

    private function checkout(array $payload, AuthController $auth): array
    {
        $salesChannel = $payload['sales_channel'] ?? 'pos';
        if ($salesChannel !== 'website' || !empty($payload['order_id'])) {
            $auth->requireStaffRole($payload, RolePolicy::rolesForEndpoint('/api/checkout') ?? ['cashier', 'manager', 'owner', 'admin']);
        }

        return (new Invoice())->checkout($payload);
    }

    private function paymentDemoCreate(array $payload): array
    {
        $amount = max(0, (float) ($payload['amount'] ?? 0));
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount is required.');
        }

        $provider = trim((string) ($payload['provider'] ?? 'Cafe Connect Demo Pay'));
        $status = in_array(($payload['status'] ?? 'paid'), ['pending', 'paid', 'failed'], true)
            ? $payload['status']
            : 'paid';

        return [
            'provider' => $provider,
            'amount' => $amount,
            'status' => $status,
            'transaction_reference' => 'DEMO-' . date('YmdHis') . '-' . random_int(100, 999),
            'created_at' => date('Y-m-d H:i:s'),
        ];
    }

    private function withEndpoint(string $route, AuthController $auth, array $payload, callable $callback): mixed
    {
        $roles = RolePolicy::rolesForEndpoint($route);
        if ($roles === null) {
            throw new InvalidArgumentException('Endpoint role policy is not configured: ' . $route);
        }
        $staff = $auth->requireStaffRole($payload, $roles);
        $reflection = new \ReflectionFunction(\Closure::fromCallable($callback));
        return $reflection->getNumberOfParameters() > 0 ? $callback($staff) : $callback();
    }
}
