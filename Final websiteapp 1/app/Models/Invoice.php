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
        $isPendingWebsitePayment = $isWebsiteOrder && in_array($paymentMethod, ['cash', 'e_wallet'], true);
        $invoiceStatus = $isPendingWebsitePayment ? 'pending' : 'paid';
        $paymentStatus = $isPendingWebsitePayment ? 'pending' : 'paid';
        $paidAt = $invoiceStatus === 'paid' ? $now : null;
        $invoiceDate = substr($now, 0, 10);
        $invoiceTime = substr($now, 11, 8);
        $websiteDelivery = null;
        if ($isWebsiteOrder) {
            $fulfillmentTypeForValidation = in_array(($data['fulfillment_type'] ?? 'pickup'), ['pickup', 'delivery'], true)
                ? (string) $data['fulfillment_type']
                : 'pickup';
            $websiteDelivery = $this->prepareWebsiteDeliveryData($data, $fulfillmentTypeForValidation);
        }

        $productModel = new Product();
        $productIds = array_map(static fn ($item) => (int) ($item['product_id'] ?? 0), $items);
        $products = $productModel->byIds($productIds);
        $prepared = [];
        $subtotal = 0.0;
        $trustedItemPrices = $orderId > 0;

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = max(1, (int) ($item['quantity'] ?? 1));
            if (!isset($products[$productId]) && !isset($item['unit_price'])) {
                throw new InvalidArgumentException('Invalid product in checkout.');
            }
            $size = Product::normalizeSize((string) ($item['size'] ?? 'M'));
            $unitPrice = $trustedItemPrices && isset($item['unit_price'])
                ? (float) $item['unit_price']
                : Product::priceForSize($products[$productId], $size);
            $lineTotal = $trustedItemPrices && isset($item['line_total'])
                ? (float) $item['line_total']
                : $unitPrice * $quantity;
            $subtotal += $lineTotal;
            $prepared[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'size' => $size,
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
            (new Inventory())->assertMaterialsAvailableForItems($prepared, $branchId);
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
                'provider' => $paymentMethod === 'cash' ? PAYMENT_COD_PROVIDER : ($isWebsiteOrder ? PAYMENT_MOMO_PROVIDER : PAYMENT_DEMO_PROVIDER),
                'amount' => $total,
                'paid_at' => $paidAt,
                'ref' => strtoupper($salesChannel) . '-' . str_pad((string) $invoiceId, 6, '0', STR_PAD_LEFT),
                'status' => $paymentStatus,
            ]);

            $websiteOrderId = null;
            $websiteOrderStatus = null;
            if ($isWebsiteOrder) {
                $fulfillmentType = $websiteDelivery['fulfillment_type'] ?? 'pickup';
                $orderStatus = $invoiceStatus === 'pending' ? 'pending' : 'paid';
                $this->db->prepare(
                    "INSERT INTO website_orders (
                        invoice_id, customer_id, fulfillment_type, order_status,
                        receiver_email, receiver_name, receiver_phone, delivery_address, city, district, ward,
                        customer_note, requested_at
                     ) VALUES (
                        :invoice_id, :customer_id, :fulfillment_type, :order_status,
                        :receiver_email, :receiver_name, :receiver_phone, :delivery_address, :city, :district, :ward,
                        :customer_note, :requested_at
                     )"
                )->execute([
                    'invoice_id' => $invoiceId,
                    'customer_id' => $customerId,
                    'fulfillment_type' => $fulfillmentType,
                    'order_status' => $orderStatus,
                    'receiver_email' => $websiteDelivery['receiver_email'] ?? null,
                    'receiver_name' => $websiteDelivery['receiver_name'] ?? null,
                    'receiver_phone' => $websiteDelivery['receiver_phone'] ?? null,
                    'delivery_address' => $websiteDelivery['delivery_address'] ?? null,
                    'city' => $websiteDelivery['city'] ?? null,
                    'district' => $websiteDelivery['district'] ?? null,
                    'ward' => $websiteDelivery['ward'] ?? null,
                    'customer_note' => $websiteDelivery['customer_note'] ?? null,
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

    public function restoreInventoryForCancelledInvoice(int $invoiceId, string $note): void
    {
        $stmt = $this->db->prepare("SELECT * FROM invoices WHERE id = :id FOR UPDATE");
        $stmt->execute(['id' => $invoiceId]);
        $invoice = $stmt->fetch();
        if (!$invoice) {
            return;
        }

        $details = $this->db->prepare(
            "SELECT product_id, quantity
             FROM invoice_details
             WHERE invoice_id = :invoice_id"
        );
        $details->execute(['invoice_id' => $invoiceId]);
        $stockStmt = $this->db->prepare(
            "UPDATE branch_inventory
             SET stock_quantity = stock_quantity + :quantity, last_updated = NOW()
             WHERE branch_id = :branch_id AND product_id = :product_id"
        );
        foreach ($details->fetchAll() as $detail) {
            $stockStmt->execute([
                'quantity' => (int) $detail['quantity'],
                'branch_id' => (int) $invoice['branch_id'],
                'product_id' => (int) $detail['product_id'],
            ]);
        }

        (new Inventory())->restoreInvoiceMaterials(
            $invoiceId,
            (int) $invoice['branch_id'],
            (int) $invoice['staff_id'],
            !empty($invoice['pos_session_id']) ? (int) $invoice['pos_session_id'] : null,
            $note
        );
    }

    public function refund(array $data): array
    {
        $invoiceId = (int) ($data['invoice_id'] ?? 0);
        $staffId = (int) ($data['staff_id'] ?? 0);
        $staffRole = (string) ($data['staff_role'] ?? '');
        $posSessionId = (int) ($data['pos_session_id'] ?? 0);
        $sessionBranchId = (int) ($data['session_branch_id'] ?? 0);
        $note = trim((string) ($data['note'] ?? $data['reason'] ?? ''));
        $reasonCodeCandidate = (string) ($data['reason_code'] ?? 'other');
        $reasonCode = in_array($reasonCodeCandidate, [
            'quality_issue', 'wrong_item', 'duplicate_charge', 'customer_request', 'other',
        ], true) ? $reasonCodeCandidate : 'other';
        $reasonLabels = [
            'quality_issue' => 'Product quality issue',
            'wrong_item' => 'Wrong item or preparation',
            'duplicate_charge' => 'Duplicate charge',
            'customer_request' => 'Customer request',
            'other' => 'Other refund reason',
        ];
        if ($invoiceId <= 0 || $staffId <= 0 || $posSessionId <= 0 || $sessionBranchId <= 0 || $note === '') {
            throw new InvalidArgumentException('Refund requires invoice, active POS session and note.');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT * FROM invoices WHERE id = :id FOR UPDATE");
            $stmt->execute(['id' => $invoiceId]);
            $invoice = $stmt->fetch();
            if (!$invoice) {
                throw new InvalidArgumentException('Invoice not found.');
            }
            if (!in_array($invoice['status'], ['paid', 'partially_refunded'], true)) {
                throw new InvalidArgumentException('Only paid or partially refunded invoices can be refunded.');
            }
            if ($staffRole === 'cashier' && (int) $invoice['branch_id'] !== $sessionBranchId) {
                throw new InvalidArgumentException('Cashier can only refund invoices from the active branch.');
            }

            $refundRows = $this->db->prepare(
                "SELECT id, refund_amount
                 FROM invoice_refunds
                 WHERE invoice_id = :invoice_id AND status = 'approved'
                 FOR UPDATE"
            );
            $refundRows->execute(['invoice_id' => $invoiceId]);
            $refundedBefore = array_reduce(
                $refundRows->fetchAll(),
                static fn (float $sum, array $row): float => $sum + (float) $row['refund_amount'],
                0.0
            );
            $invoiceTotal = (float) $invoice['total_amount'];
            $remaining = max(0.0, $invoiceTotal - $refundedBefore);
            if ($remaining < 0.01) {
                throw new InvalidArgumentException('Invoice has already been fully refunded.');
            }

            $requestedType = ($data['refund_type'] ?? 'full') === 'partial' ? 'partial' : 'full';
            $amount = $requestedType === 'full'
                ? $remaining
                : (float) ($data['refund_amount'] ?? 0);
            if ($amount <= 0) {
                throw new InvalidArgumentException('Partial refund amount must be greater than zero.');
            }
            if ($amount > $remaining + 0.01) {
                throw new InvalidArgumentException('Refund amount exceeds the remaining refundable amount.');
            }
            $amount = min($amount, $remaining);
            $refundedAfter = min($invoiceTotal, $refundedBefore + $amount);
            $isFullRefund = $refundedAfter >= ($invoiceTotal - 0.01);
            $refundType = $isFullRefund ? 'full' : 'partial';
            $refundMethod = in_array(($data['refund_method'] ?? $invoice['payment_method']), ['cash', 'card', 'e_wallet'], true)
                ? (string) ($data['refund_method'] ?? $invoice['payment_method'])
                : (string) $invoice['payment_method'];
            $refundReference = substr(trim((string) ($data['refund_reference'] ?? '')), 0, 120) ?: null;
            $externalConfirmed = $refundMethod === 'cash' || filter_var(
                $data['external_refund_confirmed'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            );
            if ($refundMethod !== 'cash' && (!$externalConfirmed || $refundReference === null)) {
                throw new InvalidArgumentException('Non-cash refund requires an external reference and confirmation.');
            }

            $restoredVoucherStatus = null;

            $this->db->prepare(
                "INSERT INTO invoice_refunds (
                    invoice_id, staff_id, pos_session_id, refund_amount, refund_type, refund_method,
                    refund_reference, reason_code, reason, note, external_refund_confirmed,
                    inventory_disposition, status, created_at
                 ) VALUES (
                    :invoice_id, :staff_id, :pos_session_id, :refund_amount, :refund_type, :refund_method,
                    :refund_reference, :reason_code, :reason, :note, :external_refund_confirmed,
                    'waste', 'approved', NOW()
                 )"
            )->execute([
                'invoice_id' => $invoiceId,
                'staff_id' => $staffId,
                'pos_session_id' => $posSessionId,
                'refund_amount' => $amount,
                'refund_type' => $refundType,
                'refund_method' => $refundMethod,
                'refund_reference' => $refundReference,
                'reason_code' => $reasonCode,
                'reason' => $reasonLabels[$reasonCode],
                'note' => substr($note, 0, 500),
                'external_refund_confirmed' => $externalConfirmed ? 1 : 0,
            ]);
            $refundId = (int) $this->db->lastInsertId();

            $invoiceStatus = $isFullRefund ? 'refunded' : 'partially_refunded';
            $this->db->prepare("UPDATE invoices SET status = :status WHERE id = :id")
                ->execute(['status' => $invoiceStatus, 'id' => $invoiceId]);
            $this->db->prepare("UPDATE payments SET status = :status WHERE invoice_id = :invoice_id")
                ->execute(['status' => $invoiceStatus, 'invoice_id' => $invoiceId]);
            if ($isFullRefund) {
                $this->db->prepare("UPDATE website_orders SET order_status = 'cancelled' WHERE invoice_id = :invoice_id")
                    ->execute(['invoice_id' => $invoiceId]);
                if (!empty($invoice['service_order_id'])) {
                    $this->db->prepare(
                        "UPDATE service_orders
                         SET status = 'cancelled',
                             note = TRIM(CONCAT(COALESCE(note, ''), ' [Refund #', :refund_id, '] ', :note)),
                             updated_at = NOW()
                         WHERE id = :service_order_id"
                    )->execute([
                        'refund_id' => $refundId,
                        'note' => substr($note, 0, 180),
                        'service_order_id' => (int) $invoice['service_order_id'],
                    ]);
                }
            }
            if ($isFullRefund && !empty($invoice['voucher_id'])) {
                $restoredVoucherStatus = (new Voucher())->restoreIfAvailable((int) $invoice['voucher_id']);
            }

            if (!empty($invoice['customer_id'])) {
                $pointsEarned = (int) $invoice['points_earned'];
                $reversedStmt = $this->db->prepare(
                    "SELECT COALESCE(SUM(points), 0)
                     FROM loyalty_point_transactions
                     WHERE invoice_id = :invoice_id
                       AND transaction_type = 'adjust'
                       AND points < 0
                       AND description LIKE 'Refund adjustment for invoice #%'
                    "
                );
                $reversedStmt->execute(['invoice_id' => $invoiceId]);
                $pointsAlreadyReversed = abs((int) $reversedStmt->fetchColumn());
                $targetReversedPoints = $isFullRefund
                    ? $pointsEarned
                    : min($pointsEarned, (int) floor($pointsEarned * ($refundedAfter / max($invoiceTotal, 0.01))));
                $pointsToReverse = max(0, $targetReversedPoints - $pointsAlreadyReversed);
                if ($pointsToReverse > 0) {
                    $this->db->prepare(
                        "INSERT INTO loyalty_point_transactions (customer_id, invoice_id, transaction_type, points, description, created_at)
                         VALUES (:customer_id, :invoice_id, 'adjust', :points, :description, NOW())"
                    )->execute([
                        'customer_id' => (int) $invoice['customer_id'],
                        'invoice_id' => $invoiceId,
                        'points' => -$pointsToReverse,
                        'description' => 'Refund adjustment for invoice #' . $invoiceId,
                    ]);
                }
                $this->db->prepare(
                    "UPDATE customers
                     SET current_points = GREATEST(current_points - :points, 0),
                         total_spending = GREATEST(total_spending - :amount, 0)
                     WHERE id = :customer_id"
                )->execute([
                    'points' => $pointsToReverse,
                    'amount' => $amount,
                    'customer_id' => (int) $invoice['customer_id'],
                ]);
                $this->upgradeTier((int) $invoice['customer_id']);
            }

            $cashTransactionId = null;
            if ($refundMethod === 'cash') {
                $cashReason = substr('Refund #' . $refundId . ' for invoice #' . $invoiceId . ' - ' . $note, 0, 180);
                $this->db->prepare(
                    "INSERT INTO cash_transactions (
                        branch_id, staff_id, pos_session_id, invoice_id, invoice_refund_id,
                        transaction_type, reason, amount, created_at
                     ) VALUES (
                        :branch_id, :staff_id, :pos_session_id, :invoice_id, :invoice_refund_id,
                        'out', :reason, :amount, NOW()
                     )"
                )->execute([
                    'branch_id' => $sessionBranchId,
                    'staff_id' => $staffId,
                    'pos_session_id' => $posSessionId,
                    'invoice_id' => $invoiceId,
                    'invoice_refund_id' => $refundId,
                    'reason' => $cashReason,
                    'amount' => $amount,
                ]);
                $cashTransactionId = (int) $this->db->lastInsertId();
            }

            $activityAction = $isFullRefund ? 'invoice_refund' : 'invoice_partial_refund';
            (new PosSession())->logFromPayload($data, $activityAction, [
                'entity_type' => 'invoice_refund',
                'entity_id' => $refundId,
                'amount' => $amount,
                'status_from' => (string) $invoice['status'],
                'status_to' => $invoiceStatus,
                'note' => $note,
            ]);

            (new AuditLog())->record([
                'actor_type' => 'staff',
                'actor_id' => $staffId,
                'actor_role' => $staffRole,
                'action' => 'invoice_refund',
                'entity_type' => 'invoice',
                'entity_id' => $invoiceId,
                'metadata' => [
                    'refund_amount' => $amount,
                    'refund_type' => $refundType,
                    'refund_method' => $refundMethod,
                    'refund_reference' => $refundReference,
                    'reason_code' => $reasonCode,
                    'note' => $note,
                    'is_full_refund' => $isFullRefund,
                    'voucher_status' => $restoredVoucherStatus,
                    'cash_transaction_id' => $cashTransactionId,
                    'inventory_disposition' => 'waste',
                ],
            ]);

            $this->db->commit();
            return [
                'refund_id' => $refundId,
                'refund_type' => $refundType,
                'refund_amount' => $amount,
                'total_refunded' => $refundedAfter,
                'remaining_refundable' => max(0, $invoiceTotal - $refundedAfter),
                'invoice_status' => $invoiceStatus,
                'cash_transaction_id' => $cashTransactionId,
                'invoice' => $this->receipt($invoiceId),
            ];
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function refundHistory(array $filters = []): array
    {
        $where = ["ir.status = 'approved'"];
        $params = [];
        if ((int) ($filters['invoice_id'] ?? 0) > 0) {
            $where[] = 'ir.invoice_id = :invoice_id';
            $params['invoice_id'] = (int) $filters['invoice_id'];
        }
        if ((int) ($filters['branch_id'] ?? 0) > 0) {
            $where[] = 'i.branch_id = :branch_id';
            $params['branch_id'] = (int) $filters['branch_id'];
        }
        if ((int) ($filters['pos_session_id'] ?? 0) > 0) {
            $where[] = 'ir.pos_session_id = :pos_session_id';
            $params['pos_session_id'] = (int) $filters['pos_session_id'];
        }
        $limit = max(1, min(200, (int) ($filters['limit'] ?? 100)));

        $stmt = $this->db->prepare(
            "SELECT ir.*, i.total_amount AS invoice_total, i.status AS invoice_status,
                    i.sales_channel, i.payment_method AS original_payment_method,
                    b.branch_name AS invoice_branch_name, s.staff_name, s.staff_role,
                    ps.opened_at AS refund_session_opened_at,
                    ct.id AS cash_transaction_id, ct.branch_id AS cash_branch_id,
                    cb.branch_name AS cash_branch_name
             FROM invoice_refunds ir
             JOIN invoices i ON i.id = ir.invoice_id
             JOIN branches b ON b.id = i.branch_id
             JOIN staff s ON s.id = ir.staff_id
             LEFT JOIN pos_sessions ps ON ps.id = ir.pos_session_id
             LEFT JOIN cash_transactions ct ON ct.invoice_refund_id = ir.id
             LEFT JOIN branches cb ON cb.id = ct.branch_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY ir.created_at DESC, ir.id DESC
             LIMIT $limit"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function refundableInvoices(array $filters = []): array
    {
        $where = ["i.status IN ('paid', 'partially_refunded')"];
        $params = [];
        if ((int) ($filters['branch_id'] ?? 0) > 0) {
            $where[] = 'i.branch_id = :branch_id';
            $params['branch_id'] = (int) $filters['branch_id'];
        }
        $limit = max(1, min(200, (int) ($filters['limit'] ?? 50)));

        $stmt = $this->db->prepare(
            "SELECT i.id, i.branch_id, i.invoice_date, i.invoice_time, i.paid_at,
                    i.sales_channel, i.payment_method, i.status, i.total_amount,
                    COALESCE(r.refunded_amount, 0) AS refunded_amount,
                    GREATEST(i.total_amount - COALESCE(r.refunded_amount, 0), 0) AS remaining_refundable,
                    COALESCE(c.customer_name, 'Guest') AS customer_name,
                    b.branch_name
             FROM invoices i
             JOIN branches b ON b.id = i.branch_id
             LEFT JOIN customers c ON c.id = i.customer_id
             LEFT JOIN (
                SELECT invoice_id, SUM(refund_amount) AS refunded_amount
                FROM invoice_refunds WHERE status = 'approved' GROUP BY invoice_id
             ) r ON r.invoice_id = i.id
             WHERE " . implode(' AND ', $where) . "
               AND GREATEST(i.total_amount - COALESCE(r.refunded_amount, 0), 0) > 0.009
             ORDER BY i.invoice_date DESC, i.invoice_time DESC, i.id DESC
             LIMIT $limit"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function receipt(int $invoiceId): array
    {
        if ($invoiceId <= 0) {
            throw new InvalidArgumentException('Invoice id is required.');
        }

        $stmt = $this->db->prepare(
            "SELECT i.*, b.branch_name, b.address AS branch_address, s.staff_name,
                    c.customer_name, c.phone_number, c.email,
                    wo.fulfillment_type, wo.order_status, wo.receiver_email, wo.receiver_name, wo.receiver_phone,
                    wo.delivery_address, wo.city, wo.district, wo.ward, wo.customer_note, wo.requested_at
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

        $refunds = $this->refundHistory(['invoice_id' => $invoiceId, 'limit' => 100]);
        $totalRefunded = array_reduce(
            $refunds,
            static fn (float $sum, array $refund): float => $sum + (float) $refund['refund_amount'],
            0.0
        );

        return [
            'invoice' => $invoice,
            'items' => $items->fetchAll(),
            'payments' => $payments->fetchAll(),
            'refunds' => $refunds,
            'refund_summary' => [
                'total_refunded' => $totalRefunded,
                'remaining_refundable' => max(0, (float) $invoice['total_amount'] - $totalRefunded),
            ],
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

    private function prepareWebsiteDeliveryData(array $data, string $fulfillmentType): array
    {
        $note = substr(trim((string) ($data['customer_note'] ?? $data['note'] ?? '')), 0, 255) ?: null;
        if ($fulfillmentType !== 'delivery') {
            return [
                'fulfillment_type' => 'pickup',
                'receiver_email' => null,
                'receiver_name' => null,
                'receiver_phone' => null,
                'delivery_address' => null,
                'city' => null,
                'district' => null,
                'ward' => null,
                'customer_note' => $note,
            ];
        }

        $email = substr(trim((string) ($data['receiver_email'] ?? $data['email'] ?? '')), 0, 150);
        $name = substr(trim((string) ($data['receiver_name'] ?? $data['customer_name'] ?? '')), 0, 150);
        $phone = substr(trim((string) ($data['receiver_phone'] ?? $data['phone_number'] ?? '')), 0, 20);
        $address = substr(trim((string) ($data['delivery_address'] ?? '')), 0, 180);
        $city = substr(trim((string) ($data['city'] ?? '')), 0, 120);
        $district = substr(trim((string) ($data['district'] ?? '')), 0, 120);
        $ward = substr(trim((string) ($data['ward'] ?? '')), 0, 120);

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Vui lòng nhập email nhận hàng hợp lệ.');
        }
        if ($phone === '') {
            throw new InvalidArgumentException('Vui lòng nhập số điện thoại nhận hàng.');
        }
        if ($address === '') {
            throw new InvalidArgumentException('Vui lòng nhập địa chỉ giao hàng.');
        }
        if ($city === '') {
            throw new InvalidArgumentException('Vui lòng nhập tỉnh/thành phố giao hàng.');
        }

        $fullAddress = implode(', ', array_filter([$address, $ward, $district, $city], static fn ($part) => $part !== ''));

        return [
            'fulfillment_type' => 'delivery',
            'receiver_email' => $email,
            'receiver_name' => $name ?: null,
            'receiver_phone' => $phone,
            'delivery_address' => substr($fullAddress, 0, 255),
            'city' => $city,
            'district' => $district ?: null,
            'ward' => $ward ?: null,
            'customer_note' => $note,
        ];
    }
}
