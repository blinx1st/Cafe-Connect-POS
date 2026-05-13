<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

function fetch_products(): array
{
    $rows = db()->query(
        "SELECT id, product_name, category, price, take_note, status
         FROM products
         WHERE status = 'active'
         ORDER BY FIELD(category, 'coffee', 'tea', 'smoothie', 'food', 'seasonal'), product_name"
    )->fetchAll();

    foreach ($rows as &$row) {
        $row['price'] = (float) $row['price'];
        $row['image'] = product_image($row);
    }

    return $rows;
}

function fetch_staff(): array
{
    return db()->query(
        "SELECT s.id, s.staff_name, s.staff_role, s.branch_id, b.branch_name
         FROM staff s
         JOIN branches b ON b.id = s.branch_id
         WHERE s.status = 'active'
         ORDER BY FIELD(s.staff_role, 'cashier', 'manager', 'barista', 'marketing', 'admin'), s.staff_name"
    )->fetchAll();
}

function fetch_branches(): array
{
    return db()->query(
        "SELECT id, branch_name, address, district
         FROM branches
         WHERE status = 'active'
         ORDER BY id"
    )->fetchAll();
}

function app_bootstrap(): array
{
    return [
        'products' => fetch_products(),
        'staff' => fetch_staff(),
        'branches' => fetch_branches(),
        'dashboard' => dashboard_data(),
    ];
}

function member_lookup(string $identity): ?array
{
    $identity = trim($identity);
    if ($identity === '') {
        throw new InvalidArgumentException('Phone or email is required.');
    }

    $stmt = db()->prepare(
        "SELECT
            c.id, c.customer_name, c.phone_number, c.email, c.gender, c.birth_date,
            c.address, c.preferred_channel, c.last_visit_date, c.current_points,
            c.total_spending, c.status,
            mt.tier_name, mt.discount_rate
         FROM customers c
         JOIN membership_tiers mt ON mt.id = c.membership_tier_id
         WHERE c.phone_number = :identity OR c.email = :identity
         LIMIT 1"
    );
    $stmt->execute(['identity' => $identity]);
    $customer = $stmt->fetch();

    if (!$customer) {
        return null;
    }

    $customer['current_points'] = (int) $customer['current_points'];
    $customer['total_spending'] = (float) $customer['total_spending'];
    $customer['discount_rate'] = (float) $customer['discount_rate'];

    $customer['vouchers'] = customer_vouchers((int) $customer['id']);
    $customer['history'] = customer_history((int) $customer['id']);

    return $customer;
}

function customer_profile(int $customerId): ?array
{
    $stmt = db()->prepare('SELECT phone_number FROM customers WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $customerId]);
    $phone = $stmt->fetchColumn();

    return $phone ? member_lookup((string) $phone) : null;
}

function customer_vouchers(int $customerId): array
{
    $stmt = db()->prepare(
        "SELECT
            v.id, v.voucher_code, v.release_date, v.expiration_date, v.status,
            p.promotion_name, p.discount_type, p.discount_value
         FROM vouchers v
         JOIN promotions p ON p.id = v.promotion_id
         WHERE v.customer_id = :customer_id
         ORDER BY FIELD(v.status, 'active', 'issued', 'reserved', 'redeemed', 'expired', 'cancelled'),
                  v.expiration_date DESC"
    );
    $stmt->execute(['customer_id' => $customerId]);

    $rows = $stmt->fetchAll();
    foreach ($rows as &$row) {
        $row['discount_value'] = (float) $row['discount_value'];
        $row['usable'] = in_array($row['status'], ['issued', 'active'], true)
            && $row['release_date'] <= today_sql()
            && $row['expiration_date'] >= today_sql();
    }

    return $rows;
}

function customer_history(int $customerId, int $limit = 8): array
{
    $stmt = db()->prepare(
        "SELECT
            i.id, i.invoice_date, i.invoice_time, i.sales_channel, i.subtotal_amount,
            i.membership_discount_amount, i.voucher_discount_amount, i.total_amount,
            i.points_earned, i.payment_method, b.branch_name,
            COALESCE(v.voucher_code, '') AS voucher_code
         FROM invoices i
         JOIN branches b ON b.id = i.branch_id
         LEFT JOIN vouchers v ON v.id = i.voucher_id
         WHERE i.customer_id = :customer_id AND i.status = 'paid'
         ORDER BY i.invoice_date DESC, i.invoice_time DESC
         LIMIT " . (int) $limit
    );
    $stmt->execute(['customer_id' => $customerId]);

    return $stmt->fetchAll();
}

function create_customer(array $data): array
{
    $name = require_value($data, 'customer_name', 'Customer name');
    $phone = require_value($data, 'phone_number', 'Phone number');
    $email = trim((string) ($data['email'] ?? '')) ?: null;

    $existingByPhone = member_lookup($phone);
    if ($existingByPhone) {
        $existingByPhone['was_existing'] = true;
        $existingByPhone['notice'] = 'Phone number already exists. Existing customer profile was opened.';
        return $existingByPhone;
    }

    if ($email !== null) {
        $existingByEmail = member_lookup($email);
        if ($existingByEmail) {
            throw new InvalidArgumentException('Email already belongs to customer ' . $existingByEmail['customer_name'] . '.');
        }
    }

    $tierId = (int) db()->query(
        "SELECT id FROM membership_tiers ORDER BY min_total_spending ASC LIMIT 1"
    )->fetchColumn();

    try {
        $stmt = db()->prepare(
            "INSERT INTO customers (
                membership_tier_id, customer_name, phone_number, email, gender,
                address, preferred_channel, current_points, total_spending, status
             ) VALUES (
                :tier_id, :customer_name, :phone_number, :email, :gender,
                :address, :preferred_channel, 0, 0, 'active'
             )"
        );
        $stmt->execute([
            'tier_id' => $tierId,
            'customer_name' => $name,
            'phone_number' => $phone,
            'email' => $email,
            'gender' => in_array(($data['gender'] ?? ''), ['male', 'female', 'other'], true) ? $data['gender'] : null,
            'address' => trim((string) ($data['address'] ?? '')) ?: null,
            'preferred_channel' => in_array(($data['preferred_channel'] ?? ''), ['pos', 'website', 'delivery', 'email', 'zalo', 'sms'], true)
                ? $data['preferred_channel']
                : 'pos',
        ]);
    } catch (PDOException $exception) {
        if ($exception->getCode() === '23000') {
            $existing = member_lookup($phone);
            if (!$existing && $email !== null) {
                $existing = member_lookup($email);
            }
            if ($existing) {
                $existing['was_existing'] = true;
                $existing['notice'] = 'Customer already exists. Existing profile was opened.';
                return $existing;
            }
        }

        throw $exception;
    }

    return member_lookup($phone) ?: [];
}

function validate_voucher_for_checkout(?int $voucherId, ?int $customerId): ?array
{
    if (!$voucherId) {
        return null;
    }
    if (!$customerId) {
        throw new InvalidArgumentException('Voucher requires a selected customer.');
    }

    $stmt = db()->prepare(
        "SELECT
            v.id, v.voucher_code, v.customer_id, v.status, v.release_date, v.expiration_date,
            p.promotion_name, p.discount_type, p.discount_value
         FROM vouchers v
         JOIN promotions p ON p.id = v.promotion_id
         WHERE v.id = :id
         LIMIT 1"
    );
    $stmt->execute(['id' => $voucherId]);
    $voucher = $stmt->fetch();

    if (!$voucher) {
        throw new InvalidArgumentException('Voucher not found.');
    }
    if ((int) $voucher['customer_id'] !== $customerId) {
        throw new InvalidArgumentException('Voucher does not belong to this customer.');
    }
    if (!in_array($voucher['status'], ['issued', 'active'], true)) {
        throw new InvalidArgumentException('Voucher is not available.');
    }
    if ($voucher['release_date'] > today_sql() || $voucher['expiration_date'] < today_sql()) {
        throw new InvalidArgumentException('Voucher is outside its valid date range.');
    }

    $voucher['discount_value'] = (float) $voucher['discount_value'];
    return $voucher;
}

function public_voucher_validate(array $data): array
{
    $voucherId = (int) ($data['voucher_id'] ?? 0);
    $customerId = (int) ($data['customer_id'] ?? 0);
    $subtotal = max(0, (float) ($data['subtotal'] ?? 0));
    $voucher = validate_voucher_for_checkout($voucherId, $customerId);

    return [
        'voucher' => $voucher,
        'discount_amount' => $voucher ? calculate_voucher_discount($voucher, $subtotal) : 0,
    ];
}

function calculate_voucher_discount(array $voucher, float $baseAmount): float
{
    if ($voucher['discount_type'] === 'percentage') {
        return round($baseAmount * ((float) $voucher['discount_value'] / 100), 0);
    }

    return min($baseAmount, (float) $voucher['discount_value']);
}

function checkout(array $data): array
{
    $items = $data['items'] ?? [];
    if (!is_array($items) || count($items) === 0) {
        throw new InvalidArgumentException('Cart is empty.');
    }

    $staffId = max(1, (int) ($data['staff_id'] ?? default_staff_id()));
    $branchId = max(1, (int) ($data['branch_id'] ?? default_branch_id_for_staff($staffId)));
    $customerId = isset($data['customer_id']) && $data['customer_id'] !== '' ? (int) $data['customer_id'] : null;
    $voucherId = isset($data['voucher_id']) && $data['voucher_id'] !== '' ? (int) $data['voucher_id'] : null;
    $paymentMethod = normalize_payment_method((string) ($data['payment_method'] ?? 'cash'));
    $salesChannel = in_array(($data['sales_channel'] ?? 'pos'), ['pos', 'website', 'delivery'], true)
        ? $data['sales_channel']
        : 'pos';

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $products = products_by_id(array_map(
            static fn ($item) => (int) ($item['product_id'] ?? 0),
            $items
        ));

        $preparedItems = [];
        $subtotal = 0.0;

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = max(0, (int) ($item['quantity'] ?? $item['qty'] ?? 0));

            if ($productId <= 0 || $quantity <= 0 || !isset($products[$productId])) {
                throw new InvalidArgumentException('Invalid product or quantity in cart.');
            }

            $product = $products[$productId];
            $lineTotal = (float) $product['price'] * $quantity;
            $subtotal += $lineTotal;

            $preparedItems[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_price' => (float) $product['price'],
                'size' => in_array(($item['size'] ?? 'M'), ['S', 'M', 'L'], true) ? $item['size'] : 'M',
                'topping' => trim((string) ($item['topping'] ?? '')) ?: null,
                'line_total' => $lineTotal,
            ];
        }

        $customer = $customerId ? customer_for_update($customerId) : null;
        $tierDiscountRate = $customer ? (float) $customer['discount_rate'] : 0.0;
        $membershipDiscount = round($subtotal * $tierDiscountRate / 100, 0);

        $voucher = validate_voucher_for_checkout($voucherId, $customerId);
        $voucherBase = max(0, $subtotal - $membershipDiscount);
        $voucherDiscount = $voucher ? calculate_voucher_discount($voucher, $voucherBase) : 0.0;
        $total = max(0, $subtotal - $membershipDiscount - $voucherDiscount);
        $points = $customerId ? (int) floor($total / 10000) : 0;

        $invoiceStmt = $pdo->prepare(
            "INSERT INTO invoices (
                branch_id, staff_id, customer_id, voucher_id, sales_channel,
                invoice_date, invoice_time, subtotal_amount, membership_discount_amount,
                voucher_discount_amount, total_amount, points_earned, payment_method, status
             ) VALUES (
                :branch_id, :staff_id, :customer_id, :voucher_id, :sales_channel,
                CURDATE(), CURTIME(), :subtotal_amount, :membership_discount_amount,
                :voucher_discount_amount, :total_amount, :points_earned, :payment_method, 'paid'
             )"
        );
        $invoiceStmt->execute([
            'branch_id' => $branchId,
            'staff_id' => $staffId,
            'customer_id' => $customerId,
            'voucher_id' => $voucherId,
            'sales_channel' => $salesChannel,
            'subtotal_amount' => $subtotal,
            'membership_discount_amount' => $membershipDiscount,
            'voucher_discount_amount' => $voucherDiscount,
            'total_amount' => $total,
            'points_earned' => $points,
            'payment_method' => $paymentMethod,
        ]);
        $invoiceId = (int) $pdo->lastInsertId();

        $detailStmt = $pdo->prepare(
            "INSERT INTO invoice_details (
                invoice_id, product_id, quantity, unit_price, size, topping, line_total
             ) VALUES (
                :invoice_id, :product_id, :quantity, :unit_price, :size, :topping, :line_total
             )"
        );
        foreach ($preparedItems as $item) {
            $detailStmt->execute($item + ['invoice_id' => $invoiceId]);
        }

        $stockStmt = $pdo->prepare(
            "UPDATE branch_inventory
             SET stock_quantity = GREATEST(stock_quantity - :quantity, 0),
                 last_updated = NOW()
             WHERE branch_id = :branch_id AND product_id = :product_id"
        );
        foreach ($preparedItems as $item) {
            $stockStmt->execute([
                'quantity' => $item['quantity'],
                'branch_id' => $branchId,
                'product_id' => $item['product_id'],
            ]);
        }

        $paymentStmt = $pdo->prepare(
            "INSERT INTO payments (
                invoice_id, payment_method, payment_provider, amount, paid_at,
                transaction_reference, status
             ) VALUES (
                :invoice_id, :payment_method, :payment_provider, :amount, NOW(),
                :transaction_reference, 'paid'
             )"
        );
        $paymentStmt->execute([
            'invoice_id' => $invoiceId,
            'payment_method' => $paymentMethod,
            'payment_provider' => payment_provider($paymentMethod),
            'amount' => $total,
            'transaction_reference' => strtoupper($salesChannel) . '-' . str_pad((string) $invoiceId, 6, '0', STR_PAD_LEFT),
        ]);

        if ($customerId) {
            if ($points > 0) {
                $pointsStmt = $pdo->prepare(
                    "INSERT INTO loyalty_point_transactions (
                        customer_id, invoice_id, transaction_type, points, description, created_at
                     ) VALUES (:customer_id, :invoice_id, 'earn', :points, :description, NOW())"
                );
                $pointsStmt->execute([
                    'customer_id' => $customerId,
                    'invoice_id' => $invoiceId,
                    'points' => $points,
                    'description' => 'Earned points from invoice #' . $invoiceId,
                ]);
            }

            $pdo->prepare(
                "UPDATE customers
                 SET current_points = current_points + :points,
                     total_spending = total_spending + :total,
                     last_visit_date = CURDATE()
                 WHERE id = :customer_id"
            )->execute([
                'points' => $points,
                'total' => $total,
                'customer_id' => $customerId,
            ]);

            upgrade_customer_tier($customerId);

            $interactionType = $salesChannel === 'website' ? 'website_order' : ($voucher ? 'voucher_redeemed' : 'pos_visit');
            $pdo->prepare(
                "INSERT INTO customer_interactions (
                    customer_id, staff_id, invoice_id, interaction_type, interaction_note, sentiment, created_at
                 ) VALUES (
                    :customer_id, :staff_id, :invoice_id, :interaction_type, :interaction_note, 'positive', NOW()
                 )"
            )->execute([
                'customer_id' => $customerId,
                'staff_id' => $staffId,
                'invoice_id' => $invoiceId,
                'interaction_type' => $interactionType,
                'interaction_note' => 'Checkout completed from ' . $salesChannel . '.',
            ]);
        }

        if ($voucher) {
            $pdo->prepare(
                "UPDATE vouchers SET status = 'redeemed', used_at = NOW() WHERE id = :voucher_id"
            )->execute(['voucher_id' => $voucherId]);
        }

        $pdo->commit();

        return [
            'invoice_id' => $invoiceId,
            'subtotal_amount' => $subtotal,
            'membership_discount_amount' => $membershipDiscount,
            'voucher_discount_amount' => $voucherDiscount,
            'total_amount' => $total,
            'points_earned' => $points,
            'customer' => $customerId ? customer_profile($customerId) : null,
        ];
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

function products_by_id(array $ids): array
{
    $ids = array_values(array_filter(array_unique($ids), static fn ($id) => $id > 0));
    if (!$ids) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = db()->prepare(
        "SELECT id, product_name, category, price FROM products WHERE status = 'active' AND id IN ($placeholders)"
    );
    $stmt->execute($ids);

    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $rows[(int) $row['id']] = $row;
    }

    return $rows;
}

function customer_for_update(int $customerId): array
{
    $stmt = db()->prepare(
        "SELECT c.id, c.customer_name, c.current_points, c.total_spending, mt.discount_rate
         FROM customers c
         JOIN membership_tiers mt ON mt.id = c.membership_tier_id
         WHERE c.id = :id
         FOR UPDATE"
    );
    $stmt->execute(['id' => $customerId]);
    $customer = $stmt->fetch();

    if (!$customer) {
        throw new InvalidArgumentException('Customer not found.');
    }

    return $customer;
}

function upgrade_customer_tier(int $customerId): void
{
    db()->prepare(
        "UPDATE customers c
         JOIN membership_tiers mt ON mt.min_total_spending = (
            SELECT MAX(mt2.min_total_spending)
            FROM membership_tiers mt2
            WHERE mt2.min_total_spending <= c.total_spending
         )
         SET c.membership_tier_id = mt.id
         WHERE c.id = :customer_id"
    )->execute(['customer_id' => $customerId]);
}

function default_staff_id(): int
{
    return (int) db()->query(
        "SELECT id FROM staff WHERE status = 'active' ORDER BY FIELD(staff_role, 'cashier', 'manager', 'admin'), id LIMIT 1"
    )->fetchColumn();
}

function default_branch_id_for_staff(int $staffId): int
{
    $stmt = db()->prepare('SELECT branch_id FROM staff WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $staffId]);
    return (int) ($stmt->fetchColumn() ?: 1);
}

function dashboard_data(): array
{
    $pdo = db();
    $businessDate = (string) ($pdo->query("SELECT COALESCE(MAX(invoice_date), CURDATE()) FROM invoices")->fetchColumn() ?: today_sql());
    $monthStart = substr($businessDate, 0, 7) . '-01';

    $summaryStmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS orders,
            COALESCE(SUM(total_amount), 0) AS revenue,
            COALESCE(SUM(points_earned), 0) AS points,
            SUM(CASE WHEN voucher_id IS NOT NULL THEN 1 ELSE 0 END) AS voucher_orders
         FROM invoices
         WHERE status = 'paid' AND invoice_date = :business_date"
    );
    $summaryStmt->execute(['business_date' => $businessDate]);
    $summary = $summaryStmt->fetch() ?: [];

    $monthStmt = $pdo->prepare(
        "SELECT
            COUNT(*) AS orders,
            COALESCE(SUM(total_amount), 0) AS revenue,
            COUNT(DISTINCT customer_id) AS customers
         FROM invoices
         WHERE status = 'paid' AND invoice_date >= :month_start AND invoice_date <= :business_date"
    );
    $monthStmt->execute(['month_start' => $monthStart, 'business_date' => $businessDate]);
    $month = $monthStmt->fetch() ?: [];

    return [
        'business_date' => $businessDate,
        'summary' => $summary,
        'month' => $month,
        'top_products' => top_products($monthStart, $businessDate),
        'low_inventory' => low_inventory(),
        'branch_revenue' => branch_revenue($monthStart, $businessDate),
        'campaigns' => campaign_performance(),
        'recent_invoices' => recent_invoices(),
    ];
}

function top_products(string $startDate, string $endDate): array
{
    $stmt = db()->prepare(
        "SELECT p.product_name, p.category, SUM(idt.quantity) AS quantity_sold,
                SUM(idt.line_total) AS product_revenue
         FROM invoice_details idt
         JOIN invoices i ON i.id = idt.invoice_id
         JOIN products p ON p.id = idt.product_id
         WHERE i.status = 'paid' AND i.invoice_date BETWEEN :start_date AND :end_date
         GROUP BY p.id, p.product_name, p.category
         ORDER BY quantity_sold DESC, product_revenue DESC
         LIMIT 6"
    );
    $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
    return $stmt->fetchAll();
}

function low_inventory(): array
{
    return db()->query(
        "SELECT b.branch_name, p.product_name, bi.stock_quantity, bi.min_stock_level, bi.last_updated
         FROM branch_inventory bi
         JOIN branches b ON b.id = bi.branch_id
         JOIN products p ON p.id = bi.product_id
         WHERE bi.stock_quantity < bi.min_stock_level
         ORDER BY b.branch_name, p.product_name
         LIMIT 8"
    )->fetchAll();
}

function branch_revenue(string $startDate, string $endDate): array
{
    $stmt = db()->prepare(
        "SELECT b.branch_name, COUNT(i.id) AS paid_invoice_count,
                COALESCE(SUM(i.total_amount), 0) AS net_revenue
         FROM branches b
         LEFT JOIN invoices i ON i.branch_id = b.id
            AND i.status = 'paid'
            AND i.invoice_date BETWEEN :start_date AND :end_date
         GROUP BY b.id, b.branch_name
         ORDER BY net_revenue DESC"
    );
    $stmt->execute(['start_date' => $startDate, 'end_date' => $endDate]);
    return $stmt->fetchAll();
}

function campaign_performance(): array
{
    return db()->query(
        "SELECT
            p.id, p.promotion_name, p.start_date, p.end_date, p.target_segment,
            p.discount_type, p.discount_value, p.status,
            COUNT(v.id) AS issued_vouchers,
            SUM(CASE WHEN v.status = 'redeemed' THEN 1 ELSE 0 END) AS redeemed_vouchers,
            COALESCE(SUM(CASE WHEN i.status = 'paid' THEN i.total_amount ELSE 0 END), 0) AS attributed_revenue
         FROM promotions p
         LEFT JOIN vouchers v ON v.promotion_id = p.id
         LEFT JOIN invoices i ON i.voucher_id = v.id
         GROUP BY p.id, p.promotion_name, p.start_date, p.end_date, p.target_segment,
                  p.discount_type, p.discount_value, p.status
         ORDER BY p.created_at DESC, p.id DESC"
    )->fetchAll();
}

function recent_invoices(): array
{
    return db()->query(
        "SELECT i.id, i.invoice_date, i.invoice_time, i.sales_channel, i.total_amount,
                i.payment_method, COALESCE(c.customer_name, 'Guest') AS customer_name,
                b.branch_name
         FROM invoices i
         JOIN branches b ON b.id = i.branch_id
         LEFT JOIN customers c ON c.id = i.customer_id
         WHERE i.status = 'paid'
         ORDER BY i.invoice_date DESC, i.invoice_time DESC, i.id DESC
         LIMIT 8"
    )->fetchAll();
}

function campaigns_data(): array
{
    return [
        'campaigns' => campaign_performance(),
        'staff' => fetch_staff(),
    ];
}

function create_campaign(array $data): array
{
    $name = require_value($data, 'promotion_name', 'Campaign name');
    $startDate = require_value($data, 'start_date', 'Start date');
    $endDate = require_value($data, 'end_date', 'End date');
    if ($startDate > $endDate) {
        throw new InvalidArgumentException('End date must be after start date.');
    }

    $target = in_array(($data['target_segment'] ?? 'all'), ['all', 'bronze', 'silver', 'gold', 'birthday', 'inactive'], true)
        ? $data['target_segment']
        : 'all';
    $discountType = in_array(($data['discount_type'] ?? 'fixed'), ['fixed', 'percentage'], true)
        ? $data['discount_type']
        : 'fixed';
    $discountValue = max(0, (float) ($data['discount_value'] ?? 0));
    $quantity = max(0, (int) ($data['voucher_quantity'] ?? 0));

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            "INSERT INTO promotions (
                promotion_name, description, start_date, end_date, target_segment,
                campaign_channel, discount_type, discount_value, voucher_quantity,
                usage_limit_per_customer, status
             ) VALUES (
                :promotion_name, :description, :start_date, :end_date, :target_segment,
                'omnichannel', :discount_type, :discount_value, :voucher_quantity,
                1, 'active'
             )"
        );
        $stmt->execute([
            'promotion_name' => $name,
            'description' => trim((string) ($data['description'] ?? '')),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'target_segment' => $target,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'voucher_quantity' => $quantity,
        ]);
        $promotionId = (int) $pdo->lastInsertId();

        $customers = target_customers($target, $quantity);
        $voucherStmt = $pdo->prepare(
            "INSERT INTO vouchers (
                voucher_code, customer_id, promotion_id, release_date, expiration_date, status
             ) VALUES (
                :voucher_code, :customer_id, :promotion_id, CURDATE(), :expiration_date, 'active'
             )"
        );

        foreach ($customers as $customer) {
            $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 5));
            $code = ($code ?: 'PROMO') . '-' . str_pad((string) $promotionId, 3, '0', STR_PAD_LEFT) . '-' . str_pad((string) $customer['id'], 4, '0', STR_PAD_LEFT);
            $voucherStmt->execute([
                'voucher_code' => $code,
                'customer_id' => $customer['id'],
                'promotion_id' => $promotionId,
                'expiration_date' => $endDate,
            ]);
        }

        $pdo->commit();
        return [
            'promotion_id' => $promotionId,
            'issued_count' => count($customers),
            'campaigns' => campaign_performance(),
        ];
    } catch (Throwable $exception) {
        $pdo->rollBack();
        throw $exception;
    }
}

function target_customers(string $target, int $limit): array
{
    $limitSql = $limit > 0 ? ' LIMIT ' . $limit : '';
    $where = "c.status = 'active'";

    if (in_array($target, ['bronze', 'silver', 'gold'], true)) {
        $where .= " AND LOWER(mt.tier_name) = " . db()->quote($target);
    } elseif ($target === 'birthday') {
        $where .= " AND MONTH(c.birth_date) = MONTH(CURDATE())";
    } elseif ($target === 'inactive') {
        $where .= " AND (c.last_visit_date IS NULL OR DATEDIFF(CURDATE(), c.last_visit_date) > 30)";
    }

    return db()->query(
        "SELECT c.id
         FROM customers c
         JOIN membership_tiers mt ON mt.id = c.membership_tier_id
         WHERE $where
         ORDER BY c.total_spending DESC, c.id ASC
         $limitSql"
    )->fetchAll();
}
