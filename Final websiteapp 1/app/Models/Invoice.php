<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use InvalidArgumentException;

final class Invoice extends Model
{
    public function checkout(array $data): array
    {
        $orderId = isset($data['order_id']) && $data['order_id'] !== '' ? (int) $data['order_id'] : 0;
        if ($orderId > 0) {
            $order = (new Order())->find($orderId);
            if (!$order) {
                throw new InvalidArgumentException('Service order not found.');
            }
            $activeOrderItems = array_values(array_filter(
                $order['items'],
                static fn ($item) => ($item['kitchen_status'] ?? '') !== 'cancelled'
            ));
            $items = array_map(static fn ($item) => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
                'unit_price' => (float) $item['unit_price'],
                'size' => $item['size'],
                'topping' => $item['topping'],
                'line_total' => (float) $item['line_total'],
            ], $activeOrderItems);
            $data['branch_id'] = $order['branch_id'];
            $data['customer_id'] = $data['customer_id'] ?? $order['customer_id'];
            $data['items'] = $items;
        }

        $items = $data['items'] ?? [];
        if (!is_array($items) || !$items) {
            throw new InvalidArgumentException('Cart is empty.');
        }

        $staffId = max(1, (int) ($data['staff_id'] ?? 1));
        $branchId = max(1, (int) ($data['branch_id'] ?? 1));
        $customerId = isset($data['customer_id']) && $data['customer_id'] !== '' ? (int) $data['customer_id'] : null;
        $voucherId = isset($data['voucher_id']) && $data['voucher_id'] !== '' ? (int) $data['voucher_id'] : null;
        $paymentMethod = in_array(($data['payment_method'] ?? 'cash'), ['cash', 'card', 'e_wallet'], true) ? $data['payment_method'] : 'cash';
        $salesChannel = in_array(($data['sales_channel'] ?? 'pos'), ['pos', 'website', 'delivery'], true) ? $data['sales_channel'] : 'pos';
        $billStartedAt = $this->dateTimeOrNow((string) ($data['bill_started_at'] ?? ($order['created_at'] ?? '')));
        $now = date('Y-m-d H:i:s');
        $isWebsiteOrder = in_array($salesChannel, ['website', 'delivery'], true);
        $posSessionId = $isWebsiteOrder ? null : (int) ($data['pos_session_id'] ?? 0);
        $isPendingCod = $isWebsiteOrder && $paymentMethod === 'cash';
        $invoiceStatus = $isPendingCod ? 'pending' : 'paid';
        $paymentStatus = $isPendingCod ? 'pending' : 'paid';
        $paidAt = $invoiceStatus === 'paid' ? $now : null;
        $invoiceDate = substr($now, 0, 10);
        $invoiceTime = substr($now, 11, 8);

        $productModel = new Product();
        $productIds = array_map(static fn ($item) => (int) ($item['product_id'] ?? 0), $items);
        $products = $productModel->byIds($productIds);
        $prepared = [];
        $subtotal = 0.0;

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            if (!isset($products[$productId]) && !isset($item['unit_price'])) {
                throw new InvalidArgumentException('Invalid product in checkout.');
            }
            $unitPrice = isset($item['unit_price']) ? (float) $item['unit_price'] : (float) $products[$productId]['price'];
            $lineTotal = isset($item['line_total']) ? (float) $item['line_total'] : $unitPrice * $quantity;
            $subtotal += $lineTotal;
            $prepared[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'size' => in_array(strtoupper((string) ($item['size'] ?? 'M')), ['S', 'M', 'L'], true) ? strtoupper((string) ($item['size'] ?? 'M')) : 'M',
                'topping' => substr(trim((string) ($item['topping'] ?? '')), 0, 100) ?: null,
                'line_total' => $lineTotal,
            ];
        }

        $voucherModel = new Voucher();
        $customer = null;
        $voucher = null;
        $membershipDiscount = 0.0;
        $voucherDiscount = 0.0;
        $total = $subtotal;
        $points = 0;

        $this->db->beginTransaction();
        try {
            $this->assertStockAvailable($prepared, $branchId);
            $customer = $customerId ? $this->customerForUpdate($customerId) : null;
            $membershipDiscount = $customer ? round($subtotal * ((float) $customer['discount_rate'] / 100), 0) : 0.0;
            $voucher = $voucherModel->validateForCheckout($voucherId, $customerId, $salesChannel, true);
            $voucherDiscount = $voucher ? $voucherModel->discount($voucher, max(0, $subtotal - $membershipDiscount)) : 0.0;
            $total = max(0, $subtotal - $membershipDiscount - $voucherDiscount);
            $points = ($customerId && $invoiceStatus === 'paid') ? (int) floor($total / 10000) : 0;

            $this->db->prepare(
                "INSERT INTO invoices (
                    branch_id, staff_id, pos_session_id, service_order_id, customer_id, voucher_id, sales_channel,
                    invoice_date, invoice_time, bill_started_at, paid_at, subtotal_amount, membership_discount_amount,
                    voucher_discount_amount, total_amount, points_earned, payment_method, status
                 ) VALUES (
                    :branch_id, :staff_id, :pos_session_id, :service_order_id, :customer_id, :voucher_id, :sales_channel,
                    :invoice_date, :invoice_time, :bill_started_at, :paid_at, :subtotal_amount, :membership_discount_amount,
                    :voucher_discount_amount, :total_amount, :points_earned, :payment_method, :status
                 )"
            )->execute([
                'branch_id' => $branchId,
                'staff_id' => $staffId,
                'pos_session_id' => $posSessionId ?: null,
                'service_order_id' => $orderId ?: null,
                'customer_id' => $customerId,
                'voucher_id' => $voucherId,
                'sales_channel' => $salesChannel,
                'invoice_date' => $invoiceDate,
                'invoice_time' => $invoiceTime,
                'bill_started_at' => $billStartedAt,
                'paid_at' => $paidAt,
                'subtotal_amount' => $subtotal,
                'membership_discount_amount' => $membershipDiscount,
                'voucher_discount_amount' => $voucherDiscount,
                'total_amount' => $total,
                'points_earned' => $points,
                'payment_method' => $paymentMethod,
                'status' => $invoiceStatus,
            ]);
            $invoiceId = (int) $this->db->lastInsertId();

            $detailStmt = $this->db->prepare(
                "INSERT INTO invoice_details (invoice_id, product_id, quantity, unit_price, size, topping, line_total)
                 VALUES (:invoice_id, :product_id, :quantity, :unit_price, :size, :topping, :line_total)"
            );
            $stockStmt = $this->db->prepare(
                "UPDATE branch_inventory
                 SET stock_quantity = GREATEST(stock_quantity - :quantity, 0), last_updated = NOW()
                 WHERE branch_id = :branch_id AND product_id = :product_id"
            );
            foreach ($prepared as $item) {
                $detailStmt->execute($item + ['invoice_id' => $invoiceId]);
                $stockStmt->execute(['quantity' => $item['quantity'], 'branch_id' => $branchId, 'product_id' => $item['product_id']]);
            }

            (new Inventory())->consumeInvoiceMaterials($invoiceId, $branchId, $staffId, $posSessionId ?: null);

            $this->db->prepare(
                "INSERT INTO payments (invoice_id, payment_method, payment_provider, amount, paid_at, transaction_reference, status)
                 VALUES (:invoice_id, :payment_method, :provider, :amount, :paid_at, :ref, :status)"
            )->execute([
                'invoice_id' => $invoiceId,
                'payment_method' => $paymentMethod,
                'provider' => $paymentMethod === 'cash' ? PAYMENT_COD_PROVIDER : PAYMENT_DEMO_PROVIDER,
                'amount' => $total,
                'paid_at' => $paidAt ?? $now,
                'ref' => strtoupper($salesChannel) . '-' . str_pad((string) $invoiceId, 6, '0', STR_PAD_LEFT),
                'status' => $paymentStatus,
            ]);

            $websiteOrderId = null;
            $websiteOrderStatus = null;
            if ($isWebsiteOrder) {
                $fulfillmentType = in_array(($data['fulfillment_type'] ?? 'pickup'), ['pickup', 'delivery'], true)
                    ? $data['fulfillment_type']
                    : 'pickup';
                $orderStatus = $invoiceStatus === 'pending' ? 'pending' : 'paid';
                $this->db->prepare(
                    "INSERT INTO website_orders (
                        invoice_id, customer_id, fulfillment_type, order_status, delivery_address, customer_note, requested_at
                     ) VALUES (
                        :invoice_id, :customer_id, :fulfillment_type, :order_status, :delivery_address, :customer_note, :requested_at
                     )"
                )->execute([
                    'invoice_id' => $invoiceId,
                    'customer_id' => $customerId,
                    'fulfillment_type' => $fulfillmentType,
                    'order_status' => $orderStatus,
                    'delivery_address' => trim((string) ($data['delivery_address'] ?? '')) ?: null,
                    'customer_note' => trim((string) ($data['customer_note'] ?? $data['note'] ?? '')) ?: null,
                    'requested_at' => $this->dateTimeOrNull((string) ($data['requested_at'] ?? '')),
                ]);
                $websiteOrderId = (int) $this->db->lastInsertId();
                $websiteOrderStatus = $orderStatus;
            }

            if ($customerId) {
                if ($points > 0) {
                    $this->db->prepare(
                        "INSERT INTO loyalty_point_transactions (customer_id, invoice_id, transaction_type, points, description, created_at)
                         VALUES (:customer_id, :invoice_id, 'earn', :points, :description, NOW())"
                    )->execute([
                        'customer_id' => $customerId,
                        'invoice_id' => $invoiceId,
                        'points' => $points,
                        'description' => 'Earned points from invoice #' . $invoiceId,
                    ]);
                }
                $this->db->prepare(
                    "UPDATE customers
                     SET current_points = current_points + :points,
                         total_spending = total_spending + :total,
                         last_visit_date = CURDATE()
                     WHERE id = :customer_id"
                )->execute([
                    'points' => $points,
                    'total' => $invoiceStatus === 'paid' ? $total : 0,
                    'customer_id' => $customerId,
                ]);
                if ($invoiceStatus === 'paid') {
                    $this->upgradeTier($customerId);
                }
            }

            if ($voucher) {
                if ($invoiceStatus === 'paid') {
                    $voucherModel->redeem((int) $voucherId);
                } else {
                    $voucherModel->reserve((int) $voucherId);
                }
            }
            if ($orderId > 0) {
                (new Order())->markPaid($orderId, $staffId);
            }
            if ($posSessionId) {
                (new PosSession())->logFromPayload($data, 'checkout', [
                    'entity_type' => 'invoice',
                    'entity_id' => $invoiceId,
                    'quantity' => array_sum(array_map(static fn ($item) => (int) $item['quantity'], $prepared)),
                    'amount' => $total,
                    'status_to' => $paymentMethod,
                    'note' => $orderId > 0 ? 'Checkout service order #' . $orderId : 'Direct POS checkout',
                ]);
            }

            (new AuditLog())->record([
                'actor_type' => $posSessionId ? 'staff' : ($customerId ? 'customer' : 'guest'),
                'actor_id' => $posSessionId ? $staffId : $customerId,
                'action' => $invoiceStatus === 'paid' ? 'checkout_paid' : 'checkout_pending',
                'entity_type' => 'invoice',
                'entity_id' => $invoiceId,
                'metadata' => [
                    'sales_channel' => $salesChannel,
                    'total_amount' => $total,
                    'payment_method' => $paymentMethod,
                ],
            ]);

            $this->db->commit();

            return [
                'invoice_id' => $invoiceId,
                'subtotal_amount' => $subtotal,
                'membership_discount_amount' => $membershipDiscount,
                'voucher_discount_amount' => $voucherDiscount,
                'total_amount' => $total,
                'points_earned' => $points,
                'bill_started_at' => $billStartedAt,
                'paid_at' => $paidAt,
                'status' => $invoiceStatus,
                'website_order_id' => $websiteOrderId,
                'order_status' => $websiteOrderStatus,
                'pos_session_id' => $posSessionId,
                'customer' => $customerId ? (new Customer())->lookup((string) $customerId) : null,
            ];
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function refund(array $data): array
    {
        $invoiceId = (int) ($data['invoice_id'] ?? 0);
        $staffId = max(1, (int) ($data['staff_id'] ?? 1));
        $posSessionId = isset($data['pos_session_id']) && (int) $data['pos_session_id'] > 0 ? (int) $data['pos_session_id'] : null;
        $reason = trim((string) ($data['reason'] ?? ''));
        if ($invoiceId <= 0 || $reason === '') {
            throw new InvalidArgumentException('Refund requires invoice_id and reason.');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT * FROM invoices WHERE id = :id FOR UPDATE");
            $stmt->execute(['id' => $invoiceId]);
            $invoice = $stmt->fetch();
            if (!$invoice) {
                throw new InvalidArgumentException('Invoice not found.');
            }
            if ($invoice['status'] !== 'paid') {
                throw new InvalidArgumentException('Only paid invoices can be refunded.');
            }

            $amount = isset($data['refund_amount']) && (float) $data['refund_amount'] > 0
                ? min((float) $data['refund_amount'], (float) $invoice['total_amount'])
                : (float) $invoice['total_amount'];
            $isFullRefund = $amount >= ((float) $invoice['total_amount'] - 0.01);
            $restoredVoucherStatus = null;

            $this->db->prepare(
                "INSERT INTO invoice_refunds (invoice_id, staff_id, pos_session_id, refund_amount, reason, status, created_at)
                 VALUES (:invoice_id, :staff_id, :pos_session_id, :refund_amount, :reason, 'approved', NOW())"
            )->execute([
                'invoice_id' => $invoiceId,
                'staff_id' => $staffId,
                'pos_session_id' => $posSessionId,
                'refund_amount' => $amount,
                'reason' => $reason,
            ]);
            $refundId = (int) $this->db->lastInsertId();

            $this->db->prepare("UPDATE invoices SET status = 'refunded' WHERE id = :id")->execute(['id' => $invoiceId]);
            $this->db->prepare("UPDATE payments SET status = 'refunded' WHERE invoice_id = :invoice_id")->execute(['invoice_id' => $invoiceId]);
            $this->db->prepare("UPDATE website_orders SET order_status = 'cancelled' WHERE invoice_id = :invoice_id")->execute(['invoice_id' => $invoiceId]);
            if ($isFullRefund && !empty($invoice['voucher_id'])) {
                $restoredVoucherStatus = (new Voucher())->restoreIfAvailable((int) $invoice['voucher_id']);
            }

            if (!empty($invoice['customer_id'])) {
                $points = (int) $invoice['points_earned'];
                if ($points > 0) {
                    $this->db->prepare(
                        "INSERT INTO loyalty_point_transactions (customer_id, invoice_id, transaction_type, points, description, created_at)
                         VALUES (:customer_id, :invoice_id, 'adjust', :points, :description, NOW())"
                    )->execute([
                        'customer_id' => (int) $invoice['customer_id'],
                        'invoice_id' => $invoiceId,
                        'points' => -$points,
                        'description' => 'Refund adjustment for invoice #' . $invoiceId,
                    ]);
                }
                $this->db->prepare(
                    "UPDATE customers
                     SET current_points = GREATEST(current_points - :points, 0),
                         total_spending = GREATEST(total_spending - :amount, 0)
                     WHERE id = :customer_id"
                )->execute([
                    'points' => $points,
                    'amount' => $amount,
                    'customer_id' => (int) $invoice['customer_id'],
                ]);
                $this->upgradeTier((int) $invoice['customer_id']);
            }

            if ($posSessionId) {
                (new PosSession())->logFromPayload($data, 'invoice_refund', [
                    'entity_type' => 'invoice_refund',
                    'entity_id' => $refundId,
                    'amount' => $amount,
                    'status_to' => 'refunded',
                    'note' => $reason,
                ]);
            }

            (new AuditLog())->record([
                'actor_type' => 'staff',
                'actor_id' => $staffId,
                'action' => 'invoice_refund',
                'entity_type' => 'invoice',
                'entity_id' => $invoiceId,
                'metadata' => [
                    'refund_amount' => $amount,
                    'reason' => $reason,
                    'is_full_refund' => $isFullRefund,
                    'voucher_status' => $restoredVoucherStatus,
                ],
            ]);

            $this->db->commit();
            return ['refund_id' => $refundId, 'invoice' => $this->receipt($invoiceId)];
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function receipt(int $invoiceId): array
    {
        if ($invoiceId <= 0) {
            throw new InvalidArgumentException('Invoice id is required.');
        }

        $stmt = $this->db->prepare(
            "SELECT i.*, b.branch_name, b.address AS branch_address, s.staff_name,
                    c.customer_name, c.phone_number, c.email,
                    wo.fulfillment_type, wo.order_status, wo.delivery_address, wo.customer_note, wo.requested_at
             FROM invoices i
             JOIN branches b ON b.id = i.branch_id
             JOIN staff s ON s.id = i.staff_id
             LEFT JOIN customers c ON c.id = i.customer_id
             LEFT JOIN website_orders wo ON wo.invoice_id = i.id
             WHERE i.id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $invoiceId]);
        $invoice = $stmt->fetch();
        if (!$invoice) {
            throw new InvalidArgumentException('Invoice not found.');
        }

        $items = $this->db->prepare(
            "SELECT idt.*, p.product_name
             FROM invoice_details idt
             JOIN products p ON p.id = idt.product_id
             WHERE idt.invoice_id = :invoice_id
             ORDER BY idt.id"
        );
        $items->execute(['invoice_id' => $invoiceId]);

        $payments = $this->db->prepare("SELECT * FROM payments WHERE invoice_id = :invoice_id ORDER BY id");
        $payments->execute(['invoice_id' => $invoiceId]);

        $refunds = $this->db->prepare("SELECT * FROM invoice_refunds WHERE invoice_id = :invoice_id ORDER BY created_at DESC");
        $refunds->execute(['invoice_id' => $invoiceId]);

        return [
            'invoice' => $invoice,
            'items' => $items->fetchAll(),
            'payments' => $payments->fetchAll(),
            'refunds' => $refunds->fetchAll(),
        ];
    }

    public function logReceiptPrint(array $data): array
    {
        $invoiceId = (int) ($data['invoice_id'] ?? 0);
        if ($invoiceId <= 0) {
            throw new InvalidArgumentException('Invoice id is required.');
        }

        $type = in_array(($data['receipt_type'] ?? 'html'), ['html', 'pdf', 'thermal'], true) ? $data['receipt_type'] : 'html';
        $staffId = isset($data['staff_id']) && (int) $data['staff_id'] > 0 ? (int) $data['staff_id'] : null;
        $posSessionId = isset($data['pos_session_id']) && (int) $data['pos_session_id'] > 0 ? (int) $data['pos_session_id'] : null;

        $this->db->prepare(
            "INSERT INTO receipt_print_logs (invoice_id, staff_id, pos_session_id, receipt_type, note)
             VALUES (:invoice_id, :staff_id, :pos_session_id, :receipt_type, :note)"
        )->execute([
            'invoice_id' => $invoiceId,
            'staff_id' => $staffId,
            'pos_session_id' => $posSessionId,
            'receipt_type' => $type,
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
        ]);

        (new AuditLog())->record([
            'actor_type' => $staffId ? 'staff' : 'system',
            'actor_id' => $staffId,
            'action' => 'receipt_print',
            'entity_type' => 'invoice',
            'entity_id' => $invoiceId,
            'metadata' => ['receipt_type' => $type],
        ]);

        return ['print_log_id' => (int) $this->db->lastInsertId(), 'receipt' => $this->receipt($invoiceId)];
    }

    private function customerForUpdate(int $customerId): array
    {
        $stmt = $this->db->prepare(
            "SELECT c.id, c.total_spending, c.current_points, mt.discount_rate
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

    private function assertStockAvailable(array $items, int $branchId): void
    {
        $required = [];
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $required[$productId] = ($required[$productId] ?? 0) + max(1, (int) ($item['quantity'] ?? 1));
        }

        if (!$required) {
            throw new InvalidArgumentException('Cart is empty.');
        }

        $ids = array_keys($required);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT p.id, p.product_name, p.status, COALESCE(bi.stock_quantity, 0) AS stock_quantity
             FROM products p
             LEFT JOIN branch_inventory bi ON bi.product_id = p.id AND bi.branch_id = ?
             WHERE p.id IN ($placeholders)
             FOR UPDATE"
        );
        $stmt->execute(array_merge([$branchId], $ids));

        $stockRows = [];
        foreach ($stmt->fetchAll() as $row) {
            $stockRows[(int) $row['id']] = $row;
        }

        foreach ($required as $productId => $quantity) {
            $row = $stockRows[$productId] ?? null;
            if (!$row || ($row['status'] ?? '') !== 'active') {
                throw new InvalidArgumentException('Sản phẩm trong giỏ không còn bán.');
            }

            $available = (float) ($row['stock_quantity'] ?? 0);
            if ($available < $quantity) {
                throw new InvalidArgumentException(sprintf(
                    'Không đủ tồn kho cho %s. Còn %.0f, cần %d.',
                    (string) $row['product_name'],
                    $available,
                    $quantity
                ));
            }
        }
    }

    private function upgradeTier(int $customerId): void
    {
        $this->db->prepare(
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

    private function dateTimeOrNow(string $value): string
    {
        $timestamp = trim($value) !== '' ? strtotime($value) : false;
        if ($timestamp === false) {
            return date('Y-m-d H:i:s');
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    private function dateTimeOrNull(string $value): ?string
    {
        $timestamp = trim($value) !== '' ? strtotime($value) : false;
        if ($timestamp === false) {
            return null;
        }

        return date('Y-m-d H:i:s', $timestamp);
    }
}
