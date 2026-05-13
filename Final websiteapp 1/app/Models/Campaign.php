<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use InvalidArgumentException;

final class Campaign extends Model
{
    public function performance(): array
    {
        return $this->db->query(
            "SELECT p.id, p.promotion_name, p.description, p.start_date, p.end_date,
                    p.target_segment, p.discount_type, p.discount_value, p.status,
                    COUNT(v.id) AS issued_vouchers,
                    SUM(CASE WHEN v.status = 'redeemed' THEN 1 ELSE 0 END) AS redeemed_vouchers,
                    COALESCE(SUM(CASE WHEN i.status = 'paid' THEN i.total_amount ELSE 0 END), 0) AS attributed_revenue
             FROM promotions p
             LEFT JOIN vouchers v ON v.promotion_id = p.id
             LEFT JOIN invoices i ON i.voucher_id = v.id
             GROUP BY p.id, p.promotion_name, p.description, p.start_date, p.end_date,
                      p.target_segment, p.discount_type, p.discount_value, p.status
             ORDER BY p.created_at DESC, p.id DESC"
        )->fetchAll();
    }

    public function create(array $data): array
    {
        $name = require_field($data, 'promotion_name', 'Campaign name');
        $start = require_field($data, 'start_date', 'Start date');
        $end = require_field($data, 'end_date', 'End date');
        if ($start > $end) {
            throw new InvalidArgumentException('End date must be after start date.');
        }

        $target = in_array(($data['target_segment'] ?? 'all'), ['all', 'bronze', 'silver', 'gold', 'birthday', 'inactive'], true)
            ? $data['target_segment']
            : 'all';
        $discountType = in_array(($data['discount_type'] ?? 'fixed'), ['fixed', 'percentage'], true)
            ? $data['discount_type']
            : 'fixed';
        $quantity = max(0, (int) ($data['voucher_quantity'] ?? 0));

        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "INSERT INTO promotions (
                    promotion_name, description, start_date, end_date, target_segment,
                    campaign_channel, discount_type, discount_value, voucher_quantity,
                    usage_limit_per_customer, status
                 ) VALUES (
                    :name, :description, :start_date, :end_date, :target_segment,
                    'omnichannel', :discount_type, :discount_value, :voucher_quantity,
                    1, 'active'
                 )"
            )->execute([
                'name' => $name,
                'description' => trim((string) ($data['description'] ?? '')),
                'start_date' => $start,
                'end_date' => $end,
                'target_segment' => $target,
                'discount_type' => $discountType,
                'discount_value' => max(0, (float) ($data['discount_value'] ?? 0)),
                'voucher_quantity' => $quantity,
            ]);
            $promotionId = (int) $this->db->lastInsertId();

            $customers = $this->targetCustomers($target, $quantity);
            $voucherStmt = $this->db->prepare(
                "INSERT INTO vouchers (voucher_code, customer_id, promotion_id, release_date, expiration_date, status)
                 VALUES (:code, :customer_id, :promotion_id, CURDATE(), :expiration_date, 'active')"
            );

            foreach ($customers as $customer) {
                $code = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name) ?: 'PROMO', 0, 5));
                $code .= '-' . str_pad((string) $promotionId, 3, '0', STR_PAD_LEFT) . '-' . str_pad((string) $customer['id'], 4, '0', STR_PAD_LEFT);
                $voucherStmt->execute([
                    'code' => $code,
                    'customer_id' => $customer['id'],
                    'promotion_id' => $promotionId,
                    'expiration_date' => $end,
                ]);
            }

            $this->db->commit();
            return ['promotion_id' => $promotionId, 'issued_count' => count($customers), 'campaigns' => $this->performance()];
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    private function targetCustomers(string $target, int $limit): array
    {
        $where = "c.status = 'active'";
        if (in_array($target, ['bronze', 'silver', 'gold'], true)) {
            $where .= " AND LOWER(mt.tier_name) = " . $this->db->quote($target);
        } elseif ($target === 'birthday') {
            $where .= " AND MONTH(c.birth_date) = MONTH(CURDATE())";
        } elseif ($target === 'inactive') {
            $where .= " AND (c.last_visit_date IS NULL OR DATEDIFF(CURDATE(), c.last_visit_date) > 30)";
        }

        $limitSql = $limit > 0 ? ' LIMIT ' . $limit : '';

        return $this->db->query(
            "SELECT c.id
             FROM customers c
             JOIN membership_tiers mt ON mt.id = c.membership_tier_id
             WHERE $where
             ORDER BY c.total_spending DESC, c.id
             $limitSql"
        )->fetchAll();
    }
}
