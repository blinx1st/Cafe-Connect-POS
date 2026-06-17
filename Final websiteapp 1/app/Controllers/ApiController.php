<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\AppLogger;
use App\Core\Database;
use App\Core\Mailer;
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
use App\Models\WebsiteOrder;
use InvalidArgumentException;
use Throwable;

final class ApiController extends Controller
{
    private const MEMBER_FEEDBACK_EMAIL = 'blinx1st@gmail.com';

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
                '/api/member-feedback' => $this->memberFeedback($payload),
                '/api/member-lookup' => (new Customer())->lookup(require_field($payload, 'identity', 'Phone or email')),
                '/api/product-detail' => $this->productDetail($payload),
                '/api/website-orders' => $this->memberOrders(),
                '/api/website-order-detail' => $this->memberOrderDetail($payload),
                '/api/website-order-cancel' => $this->memberOrderCancel($payload),
                '/api/voucher-claim' => $this->claimVoucher($payload),
                '/api/voucher-claim-code' => $this->claimVoucherByCode($payload),
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
                '/api/payment-demo-confirm' => $this->paymentDemoConfirm($payload),
                '/api/checkout-closing' => $this->withEndpoint($route, $auth, $payload, fn () => (new PosSession())->logout($payload)),
                '/api/shift-closing' => $this->withEndpoint($route, $auth, $payload, fn () => (new PosSession())->logout($payload)),
                '/api/dashboard' => $this->withEndpoint($route, $auth, $payload, fn () => (new Dashboard())->data()),
                '/api/campaigns' => $this->withEndpoint($route, $auth, $payload, fn () => ['campaigns' => (new Campaign())->performance()]),
                '/api/create-campaign' => $this->withEndpoint($route, $auth, $payload, fn () => (new Campaign())->create($payload)),
                '/api/campaign-save' => $this->withEndpoint($route, $auth, $payload, fn () => (new Campaign())->save($payload)),
                '/api/campaign-delete' => $this->withEndpoint($route, $auth, $payload, fn () => (new Campaign())->cancel((int) ($payload['id'] ?? $payload['promotion_id'] ?? 0), $payload)),
                '/api/campaign-restore' => $this->withEndpoint($route, $auth, $payload, fn () => (new Campaign())->restore((int) ($payload['id'] ?? $payload['promotion_id'] ?? 0), $payload)),
                '/api/inventory' => $this->withEndpoint($route, $auth, $payload, fn () => (new Inventory())->overview()),
                '/api/stock-movement' => $this->withEndpoint($route, $auth, $payload, fn () => (new Inventory())->createMovement($payload)),
                '/api/cash-transaction' => $this->withEndpoint($route, $auth, $payload, fn () => $this->createCashTransaction($payload)),
                '/api/product-list' => $this->withEndpoint($route, $auth, $payload, fn () => $this->productList($payload)),
                '/api/product-save' => $this->withEndpoint($route, $auth, $payload, fn () => $this->saveProduct($payload)),
                '/api/product-delete' => $this->withEndpoint($route, $auth, $payload, fn () => $this->deleteProduct($payload)),
                '/api/product-restore' => $this->withEndpoint($route, $auth, $payload, fn () => $this->restoreProduct($payload)),
                '/api/product-image-upload' => $this->withEndpoint($route, $auth, $payload, fn () => $this->uploadProductImage($payload)),
                '/api/category-save' => $this->withEndpoint($route, $auth, $payload, fn () => $this->saveCategory($payload)),
                '/api/content-save' => $this->withEndpoint($route, $auth, $payload, fn () => $this->saveContent($payload)),
                '/api/staff-list' => $this->withEndpoint($route, $auth, $payload, fn () => ['staff' => (new Staff())->allForAdmin()]),
                '/api/staff-save' => $this->withEndpoint($route, $auth, $payload, fn () => $this->saveStaff($payload)),
                '/api/staff-delete' => $this->withEndpoint($route, $auth, $payload, fn () => $this->deleteStaff($payload)),
                '/api/staff-restore' => $this->withEndpoint($route, $auth, $payload, fn () => $this->restoreStaff($payload)),
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
                'member_feedback' => '/api/member-feedback',
                'member_lookup' => '/api/member-lookup',
                'product_detail' => '/api/product-detail',
                'website_orders' => '/api/website-orders',
                'website_order_detail' => '/api/website-order-detail',
                'website_order_cancel' => '/api/website-order-cancel',
                'voucher_claim' => '/api/voucher-claim',
                'voucher_claim_code' => '/api/voucher-claim-code',
                'customer_create' => '/api/customer-create',
                'checkout' => '/api/checkout',
                'dashboard' => '/api/dashboard',
                'campaigns' => '/api/campaigns',
                'create_campaign' => '/api/create-campaign',
                'campaign_save' => '/api/campaign-save',
                'campaign_delete' => '/api/campaign-delete',
                'campaign_restore' => '/api/campaign-restore',
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
                'shift_closing' => '/api/shift-closing',
                'payment_demo_create' => '/api/payment-demo-create',
                'payment_demo_confirm' => '/api/payment-demo-confirm',
                'order_status_update' => '/api/order-status-update',
                'receipt_print_log' => '/api/receipt-print-log',
                'reports_export' => '/api/reports-export',
                'product_list' => '/api/product-list',
                'product_delete' => '/api/product-delete',
                'product_restore' => '/api/product-restore',
                'product_image_upload' => '/api/product-image-upload',
                'category_save' => '/api/category-save',
                'content_save' => '/api/content-save',
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
            '/api/product-detail',
            '/api/website-orders',
            '/api/website-order-detail',
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
            '/api/product-list',
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
        $branchId = max(1, (int) ($payload['branch_id'] ?? 1));

        if (!empty($payload['pos_session_id']) && !empty($payload['session_token'])) {
            try {
                $currentSession = $posSession->requireOpen($payload);
                $role = (string) ($currentSession['staff_role'] ?? '');
                $branchId = max(1, (int) ($currentSession['branch_id'] ?? $branchId));
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
            'products' => $product->active($role !== '' ? ['branch_id' => $branchId] : []),
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
            $data['staff'] = $staff->allForAdmin();
        }
        if (RolePolicy::canAccessModule($role, 'products')) {
            $data['admin_products'] = $product->allForAdmin(['branch_id' => $branchId]);
            $data['admin_categories'] = $product->categories(true);
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

    private function productList(array $payload): array
    {
        return (new Product())->adminPayload(max(1, (int) ($payload['branch_id'] ?? 1)));
    }

    private function deleteProduct(array $payload): array
    {
        $productId = (int) ($payload['id'] ?? $payload['product_id'] ?? 0);
        $result = (new Product())->softDelete($productId, max(1, (int) ($payload['branch_id'] ?? 1)));
        $this->logProductAdminAction($payload, 'product_delete', $productId, 'inactive');

        return $result;
    }

    private function restoreProduct(array $payload): array
    {
        $productId = (int) ($payload['id'] ?? $payload['product_id'] ?? 0);
        $result = (new Product())->restore($productId, max(1, (int) ($payload['branch_id'] ?? 1)));
        $this->logProductAdminAction($payload, 'product_restore', $productId, 'active');

        return $result;
    }

    private function uploadProductImage(array $payload): array
    {
        $productId = (int) ($payload['product_id'] ?? $payload['id'] ?? 0);
        if ($productId <= 0) {
            throw new InvalidArgumentException('Product id is required.');
        }
        if (empty($_FILES['image']) || !is_array($_FILES['image'])) {
            throw new InvalidArgumentException('Image file is required.');
        }

        $file = $_FILES['image'];
        if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Upload failed. Please choose another image.');
        }
        if ((int) ($file['size'] ?? 0) > 2 * 1024 * 1024) {
            throw new InvalidArgumentException('Image must be 2MB or smaller.');
        }

        $tmpPath = (string) ($file['tmp_name'] ?? '');
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $tmpPath !== '' && is_file($tmpPath) ? (string) $finfo->file($tmpPath) : '';
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];
        if (!isset($extensions[$mime])) {
            throw new InvalidArgumentException('Only JPG, PNG and WEBP images are allowed.');
        }

        $uploadDir = APP_ROOT . '/assets/uploads/products';
        if (!is_dir($uploadDir) && !mkdir($uploadDir, 0775, true) && !is_dir($uploadDir)) {
            throw new InvalidArgumentException('Cannot create upload directory.');
        }

        $filename = 'product-' . $productId . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(4)) . '.' . $extensions[$mime];
        $targetPath = $uploadDir . '/' . $filename;
        $moved = is_uploaded_file($tmpPath)
            ? move_uploaded_file($tmpPath, $targetPath)
            : rename($tmpPath, $targetPath);
        if (!$moved) {
            throw new InvalidArgumentException('Cannot save uploaded image.');
        }

        $relativePath = 'assets/uploads/products/' . $filename;
        $product = new Product();
        $product->saveImage(
            $productId,
            $relativePath,
            trim((string) ($payload['alt_text'] ?? '')),
            ((string) ($payload['is_primary'] ?? '1')) !== '0'
        );

        $this->logProductAdminAction($payload, 'product_image_upload', $productId, $relativePath);

        return $product->adminPayload(max(1, (int) ($payload['branch_id'] ?? 1))) + [
            'id' => $productId,
            'image_path' => $relativePath,
        ];
    }

    private function logProductAdminAction(array $payload, string $action, int $productId, string $statusOrNote): void
    {
        (new PosSession())->logFromPayload($payload, $action, [
            'entity_type' => 'product',
            'entity_id' => $productId,
            'status_to' => $statusOrNote,
            'note' => $statusOrNote,
        ]);
        (new AuditLog())->record([
            'actor_type' => 'staff',
            'actor_id' => (int) ($payload['staff_id'] ?? 0),
            'actor_role' => (string) ($payload['staff_role'] ?? ''),
            'action' => $action,
            'entity_type' => 'product',
            'entity_id' => $productId,
            'metadata' => ['status_or_note' => $statusOrNote],
        ]);
    }

    private function saveCategory(array $payload): array
    {
        $result = (new Product())->saveCategory($payload);
        (new AuditLog())->record([
            'actor_type' => 'staff',
            'actor_id' => (int) ($payload['staff_id'] ?? 0),
            'actor_role' => (string) ($payload['staff_role'] ?? ''),
            'action' => 'category_save',
            'entity_type' => 'product_category',
            'entity_id' => (int) ($result['id'] ?? 0),
            'metadata' => ['category_code' => (string) ($payload['category_code'] ?? '')],
        ]);

        return $result;
    }

    private function saveContent(array $payload): array
    {
        $allowed = ['home_banner', 'footer_policy', 'hotline', 'address', 'social_links'];
        $key = (string) ($payload['content_key'] ?? $payload['key'] ?? '');
        if (!in_array($key, $allowed, true)) {
            throw new InvalidArgumentException('Content key is not allowed.');
        }

        $contentDir = APP_ROOT . '/storage/cms';
        if (!is_dir($contentDir) && !mkdir($contentDir, 0775, true) && !is_dir($contentDir)) {
            throw new InvalidArgumentException('Cannot create CMS storage directory.');
        }

        $file = $contentDir . '/site_content.json';
        $current = is_file($file) ? json_decode((string) file_get_contents($file), true) : [];
        if (!is_array($current)) {
            $current = [];
        }
        $current[$key] = [
            'value' => (string) ($payload['content_value'] ?? $payload['value'] ?? ''),
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => (int) ($payload['staff_id'] ?? 0),
        ];
        file_put_contents($file, json_encode($current, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        (new AuditLog())->record([
            'actor_type' => 'staff',
            'actor_id' => (int) ($payload['staff_id'] ?? 0),
            'actor_role' => (string) ($payload['staff_role'] ?? ''),
            'action' => 'content_save',
            'entity_type' => 'site_content',
            'entity_id' => null,
            'metadata' => ['content_key' => $key],
        ]);

        return ['content' => $current];
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

    private function memberFeedback(array $payload): array
    {
        $customerId = (int) Session::get('member_customer_id', 0);
        if ($customerId <= 0) {
            throw new InvalidArgumentException('Vui lòng đăng nhập thành viên để gửi phản hồi.');
        }

        $member = (new Customer())->lookup((string) $customerId);
        if (!$member) {
            Session::forget('member_customer_id');
            throw new InvalidArgumentException('Phiên đăng nhập thành viên không còn hợp lệ.');
        }

        $topicLabels = [
            'service' => 'Dịch vụ',
            'product' => 'Sản phẩm',
            'delivery' => 'Giao hàng',
            'website' => 'Website / đặt hàng',
            'loyalty' => 'Thành viên / voucher',
            'other' => 'Khác',
        ];
        $topic = (string) ($payload['topic'] ?? 'other');
        if (!isset($topicLabels[$topic])) {
            $topic = 'other';
        }

        $rating = (int) ($payload['rating'] ?? 5);
        if ($rating < 1 || $rating > 5) {
            throw new InvalidArgumentException('Vui lòng chọn mức đánh giá từ 1 đến 5.');
        }

        $message = trim((string) ($payload['message'] ?? ''));
        $message = preg_replace("/[ \t]+/", ' ', $message) ?? $message;
        if (strlen($message) < 10) {
            throw new InvalidArgumentException('Vui lòng nhập phản hồi ít nhất 10 ký tự.');
        }
        if (strlen($message) > 2000) {
            throw new InvalidArgumentException('Phản hồi tối đa 2000 ký tự.');
        }

        $subject = '[Cafe Connect] Feedback member - ' . $topicLabels[$topic];
        $body = "Cafe Connect nhận phản hồi mới từ member.\n\n"
            . "Khách hàng: " . (string) ($member['customer_name'] ?? '') . "\n"
            . "Mã khách hàng: " . (int) ($member['id'] ?? $customerId) . "\n"
            . "Số điện thoại: " . (string) ($member['phone_number'] ?? '') . "\n"
            . "Email: " . (string) ($member['email'] ?? '') . "\n"
            . "Hạng: " . (string) ($member['membership_tier'] ?? '') . "\n"
            . "Chủ đề: " . $topicLabels[$topic] . "\n"
            . "Đánh giá: " . $rating . "/5\n"
            . "Thời gian: " . date('Y-m-d H:i:s') . "\n\n"
            . "Nội dung phản hồi:\n"
            . $message . "\n";

        try {
            (new Mailer())->send(
                self::MEMBER_FEEDBACK_EMAIL,
                'Cafe Connect Admin',
                $subject,
                $body
            );
        } catch (\RuntimeException $exception) {
            AppLogger::error($exception, [
                'action' => 'member_feedback_mail',
                'customer_id' => $customerId,
                'recipient' => self::MEMBER_FEEDBACK_EMAIL,
            ]);
            throw new InvalidArgumentException('Không thể gửi phản hồi qua Gmail. Vui lòng kiểm tra cấu hình SMTP.');
        }

        (new AuditLog())->record([
            'actor_type' => 'customer',
            'actor_id' => $customerId,
            'action' => 'member_feedback',
            'entity_type' => 'customer',
            'entity_id' => $customerId,
            'metadata' => [
                'topic' => $topic,
                'rating' => $rating,
                'recipient' => self::MEMBER_FEEDBACK_EMAIL,
            ],
        ]);

        return [
            'sent' => true,
            'recipient' => self::MEMBER_FEEDBACK_EMAIL,
        ];
    }

    private function deleteStaff(array $payload): array
    {
        $id = (int) ($payload['id'] ?? 0);
        $result = (new Staff())->deactivate($id, (int) ($payload['staff_id'] ?? 0));
        (new PosSession())->logFromPayload($payload, 'staff_delete', [
            'entity_type' => 'staff',
            'entity_id' => $id,
            'status_to' => 'inactive',
            'note' => (string) ($payload['reason'] ?? 'Staff deactivated'),
        ]);
        (new AuditLog())->record([
            'actor_type' => 'staff',
            'actor_id' => (int) ($payload['staff_id'] ?? 0),
            'actor_role' => (string) ($payload['staff_role'] ?? ''),
            'action' => 'staff_delete',
            'entity_type' => 'staff',
            'entity_id' => $id,
            'metadata' => ['reason' => (string) ($payload['reason'] ?? '')],
        ]);

        return $result;
    }

    private function restoreStaff(array $payload): array
    {
        $id = (int) ($payload['id'] ?? 0);
        $result = (new Staff())->restore($id);
        (new PosSession())->logFromPayload($payload, 'staff_restore', [
            'entity_type' => 'staff',
            'entity_id' => $id,
            'status_to' => 'active',
            'note' => 'Staff restored',
        ]);
        (new AuditLog())->record([
            'actor_type' => 'staff',
            'actor_id' => (int) ($payload['staff_id'] ?? 0),
            'actor_role' => (string) ($payload['staff_role'] ?? ''),
            'action' => 'staff_restore',
            'entity_type' => 'staff',
            'entity_id' => $id,
        ]);

        return $result;
    }

    private function claimVoucher(array $payload): array
    {
        $customerId = (int) Session::get('member_customer_id', 0);
        if ($customerId <= 0) {
            throw new InvalidArgumentException('Vui lòng đăng nhập thành viên để nhận voucher.');
        }

        $promotionId = (int) ($payload['promotion_id'] ?? 0);
        if ($promotionId <= 0) {
            throw new InvalidArgumentException('Promotion id is required.');
        }

        return (new Customer())->claimVoucher($customerId, $promotionId);
    }

    private function claimVoucherByCode(array $payload): array
    {
        $customerId = (int) Session::get('member_customer_id', 0);
        if ($customerId <= 0) {
            throw new InvalidArgumentException('Vui long dang nhap thanh vien de nhap ma voucher.');
        }

        $claimCode = trim((string) ($payload['claim_code'] ?? ''));
        if ($claimCode === '') {
            throw new InvalidArgumentException('Vui long nhap ma voucher.');
        }

        return (new Customer())->claimVoucherByCode($customerId, $claimCode);
    }

    private function productDetail(array $payload): array
    {
        $productId = (int) ($payload['id'] ?? $payload['product_id'] ?? 0);
        $product = (new Product())->detail($productId);
        if (!$product) {
            throw new InvalidArgumentException('Product not found.');
        }

        return ['product' => $product];
    }

    private function memberOrders(): array
    {
        $customerId = (int) Session::get('member_customer_id', 0);
        if ($customerId <= 0) {
            throw new InvalidArgumentException('Please login to view website orders.');
        }

        return ['orders' => (new WebsiteOrder())->forCustomer($customerId)];
    }

    private function memberOrderDetail(array $payload): array
    {
        $customerId = (int) Session::get('member_customer_id', 0);
        if ($customerId <= 0) {
            throw new InvalidArgumentException('Please login to view this order.');
        }

        return (new WebsiteOrder())->detailForCustomer($customerId, (int) ($payload['invoice_id'] ?? 0));
    }

    private function memberOrderCancel(array $payload): array
    {
        $customerId = (int) Session::get('member_customer_id', 0);
        if ($customerId <= 0) {
            throw new InvalidArgumentException('Please login to cancel this order.');
        }

        return (new WebsiteOrder())->cancelForCustomer(
            $customerId,
            (int) ($payload['invoice_id'] ?? 0),
            (string) ($payload['reason'] ?? '')
        );
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

        $provider = trim((string) ($payload['provider'] ?? PAYMENT_DEMO_PROVIDER));
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

    private function paymentDemoConfirm(array $payload): array
    {
        $customerId = (int) Session::get('member_customer_id', 0);
        if ($customerId <= 0) {
            throw new InvalidArgumentException('Please login before confirming DemoPay payment.');
        }

        return (new WebsiteOrder())->confirmDemoPayment($customerId, (int) ($payload['invoice_id'] ?? 0));
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
