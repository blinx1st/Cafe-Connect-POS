<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use InvalidArgumentException;

final class WebsiteOrder extends Model
{
    public function forCustomer(int $customerId): array
    {
        if ($customerId <= 0) {
            return [];
        }

        $stmt = $this->db->prepare(
            "SELECT wo.id AS website_order_id, wo.invoice_id, wo.fulfillment_type, wo.order_status,
                    wo.delivery_address, wo.customer_note, wo.requested_at, wo.created_at,
                    i.invoice_date, i.invoice_time, i.paid_at, i.total_amount, i.payment_method, i.status AS invoice_status,
                    b.branch_name,
                    COALESCE(v.voucher_code, '') AS voucher_code,
                    COALESCE(p.status, '') AS payment_status
             FROM website_orders wo
             JOIN invoices i ON i.id = wo.invoice_id
             JOIN branches b ON b.id = i.branch_id
             LEFT JOIN vouchers v ON v.id = i.voucher_id
             LEFT JOIN payments p ON p.invoice_id = i.id
             WHERE wo.customer_id = :customer_id
             ORDER BY wo.created_at DESC, wo.id DESC
             LIMIT 30"
        );
        $stmt->execute(['customer_id' => $customerId]);

        return $stmt->fetchAll();
    }

    public function detailForCustomer(int $customerId, int $invoiceId): array
    {
        $this->assertCustomerInvoice($customerId, $invoiceId);
        return (new Invoice())->receipt($invoiceId);
    }

    public function cancelForCustomer(int $customerId, int $invoiceId, string $reason): array
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('Cancel reason is required.');
        }

        $this->db->beginTransaction();
        try {
            $invoice = $this->invoiceForCustomer($customerId, $invoiceId, true);
            if (!$invoice) {
                throw new InvalidArgumentException('Website order not found.');
            }
            if (($invoice['order_status'] ?? '') !== 'pending') {
                throw new InvalidArgumentException('Only pending website orders can be cancelled by customer.');
            }

            $this->db->prepare(
                "UPDATE website_orders
                 SET order_status = 'cancelled',
                     customer_note = TRIM(CONCAT(COALESCE(customer_note, ''), :note))
                 WHERE invoice_id = :invoice_id"
            )->execute([
                'invoice_id' => $invoiceId,
                'note' => "\nCustomer cancel reason: " . $reason,
            ]);
            $this->db->prepare("UPDATE invoices SET status = 'cancelled' WHERE id = :id")
                ->execute(['id' => $invoiceId]);
            $this->db->prepare("UPDATE payments SET status = 'failed' WHERE invoice_id = :invoice_id AND status = 'pending'")
                ->execute(['invoice_id' => $invoiceId]);

            if (!empty($invoice['voucher_id'])) {
                $this->db->prepare(
                    "UPDATE vouchers
                     SET status = 'active', used_at = NULL
                     WHERE id = :id AND status = 'reserved'"
                )->execute(['id' => (int) $invoice['voucher_id']]);
            }

            (new AuditLog())->record([
                'actor_type' => 'customer',
                'actor_id' => $customerId,
                'action' => 'website_order_cancel',
                'entity_type' => 'invoice',
                'entity_id' => $invoiceId,
                'metadata' => ['reason' => $reason],
            ]);

            $this->db->commit();
            return $this->detailForCustomer($customerId, $invoiceId);
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function confirmDemoPayment(int $customerId, int $invoiceId): array
    {
        $this->db->beginTransaction();
        try {
            $invoice = $this->invoiceForCustomer($customerId, $invoiceId, true);
            if (!$invoice) {
                throw new InvalidArgumentException('Website order not found.');
            }
            if (!in_array(($invoice['payment_method'] ?? ''), ['card', 'e_wallet'], true)) {
                throw new InvalidArgumentException('DemoPay can only confirm card or e-wallet orders.');
            }

            if (($invoice['invoice_status'] ?? '') !== 'paid') {
                $points = (int) floor((float) $invoice['total_amount'] / 10000);
                $this->db->prepare(
                    "UPDATE invoices
                     SET status = 'paid',
                         paid_at = NOW(),
                         points_earned = :points
                     WHERE id = :id"
                )->execute(['id' => $invoiceId, 'points' => $points]);
                $this->db->prepare(
                    "UPDATE payments
                     SET status = 'paid',
                         paid_at = NOW(),
                         payment_provider = :provider,
                         transaction_reference = COALESCE(transaction_reference, :ref)
                     WHERE invoice_id = :invoice_id"
                )->execute([
                    'invoice_id' => $invoiceId,
                    'provider' => PAYMENT_DEMO_PROVIDER,
                    'ref' => 'DEMO-' . str_pad((string) $invoiceId, 6, '0', STR_PAD_LEFT),
                ]);
                $this->db->prepare("UPDATE website_orders SET order_status = 'paid' WHERE invoice_id = :invoice_id")
                    ->execute(['invoice_id' => $invoiceId]);

                if ($points > 0) {
                    $this->db->prepare(
                        "INSERT INTO loyalty_point_transactions (customer_id, invoice_id, transaction_type, points, description, created_at)
                         VALUES (:customer_id, :invoice_id, 'earn', :points, :description, NOW())"
                    )->execute([
                        'customer_id' => $customerId,
                        'invoice_id' => $invoiceId,
                        'points' => $points,
                        'description' => 'Earned points from website order #' . $invoiceId,
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
                    'total' => (float) $invoice['total_amount'],
                    'customer_id' => $customerId,
                ]);
                $this->upgradeTier($customerId);

                if (!empty($invoice['voucher_id'])) {
                    (new Voucher())->redeem((int) $invoice['voucher_id']);
                }
            }

            (new AuditLog())->record([
                'actor_type' => 'customer',
                'actor_id' => $customerId,
                'action' => 'payment_demo_confirm',
                'entity_type' => 'invoice',
                'entity_id' => $invoiceId,
                'metadata' => ['provider' => PAYMENT_DEMO_PROVIDER],
            ]);

            $this->db->commit();
            return $this->detailForCustomer($customerId, $invoiceId);
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    private function assertCustomerInvoice(int $customerId, int $invoiceId): void
    {
        if (!$this->invoiceForCustomer($customerId, $invoiceId, false)) {
            throw new InvalidArgumentException('Website order not found.');
        }
    }

    private function invoiceForCustomer(int $customerId, int $invoiceId, bool $lock): ?array
    {
        if ($customerId <= 0 || $invoiceId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT i.*, i.status AS invoice_status, wo.order_status, wo.id AS website_order_id
             FROM website_orders wo
             JOIN invoices i ON i.id = wo.invoice_id
             WHERE wo.customer_id = :customer_id AND wo.invoice_id = :invoice_id
             LIMIT 1" . ($lock ? ' FOR UPDATE' : '')
        );
        $stmt->execute(['customer_id' => $customerId, 'invoice_id' => $invoiceId]);
        $invoice = $stmt->fetch();

        return $invoice ?: null;
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
}
