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
            $items = array_map(static fn ($item) => [
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
                'unit_price' => (float) $item['unit_price'],
                'size' => $item['size'],
                'topping' => $item['topping'],
                'line_total' => (float) $item['line_total'],
            ], $order['items']);
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
        $posSessionId = $salesChannel === 'website' ? null : (int) ($data['pos_session_id'] ?? 0);
        $billStartedAt = $this->dateTimeOrNow((string) ($data['bill_started_at'] ?? ($order['created_at'] ?? '')));
        $paidAt = date('Y-m-d H:i:s');
        $invoiceDate = substr($paidAt, 0, 10);
        $invoiceTime = substr($paidAt, 11, 8);

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
                'size' => in_array(($item['size'] ?? 'M'), ['S', 'M', 'L'], true) ? $item['size'] : 'M',
                'topping' => trim((string) ($item['topping'] ?? '')) ?: null,
                'line_total' => $lineTotal,
            ];
        }

        $customer = $customerId ? $this->customerForUpdate($customerId) : null;
        $membershipDiscount = $customer ? round($subtotal * ((float) $customer['discount_rate'] / 100), 0) : 0.0;
        $voucherModel = new Voucher();
        $voucher = $voucherModel->validateForCheckout($voucherId, $customerId);
        $voucherDiscount = $voucher ? $voucherModel->discount($voucher, max(0, $subtotal - $membershipDiscount)) : 0.0;
        $total = max(0, $subtotal - $membershipDiscount - $voucherDiscount);
        $points = $customerId ? (int) floor($total / 10000) : 0;

        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "INSERT INTO invoices (
                    branch_id, staff_id, pos_session_id, service_order_id, customer_id, voucher_id, sales_channel,
                    invoice_date, invoice_time, bill_started_at, paid_at, subtotal_amount, membership_discount_amount,
                    voucher_discount_amount, total_amount, points_earned, payment_method, status
                 ) VALUES (
                    :branch_id, :staff_id, :pos_session_id, :service_order_id, :customer_id, :voucher_id, :sales_channel,
                    :invoice_date, :invoice_time, :bill_started_at, :paid_at, :subtotal_amount, :membership_discount_amount,
                    :voucher_discount_amount, :total_amount, :points_earned, :payment_method, 'paid'
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

            $this->db->prepare(
                "INSERT INTO payments (invoice_id, payment_method, payment_provider, amount, paid_at, transaction_reference, status)
                 VALUES (:invoice_id, :payment_method, :provider, :amount, :paid_at, :ref, 'paid')"
            )->execute([
                'invoice_id' => $invoiceId,
                'payment_method' => $paymentMethod,
                'provider' => $paymentMethod === 'cash' ? null : 'Demo ' . $paymentMethod,
                'amount' => $total,
                'paid_at' => $paidAt,
                'ref' => strtoupper($salesChannel) . '-' . str_pad((string) $invoiceId, 6, '0', STR_PAD_LEFT),
            ]);

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
                )->execute(['points' => $points, 'total' => $total, 'customer_id' => $customerId]);
                $this->upgradeTier($customerId);
            }

            if ($voucher) {
                $voucherModel->redeem((int) $voucherId);
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
                'pos_session_id' => $posSessionId,
                'customer' => $customerId ? (new Customer())->lookup((string) $customerId) : null,
            ];
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
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
}
