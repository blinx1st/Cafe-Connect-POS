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
                    p.target_segment, p.campaign_channel, p.discount_type, p.discount_value,
                    p.voucher_quantity, p.usage_limit_per_customer, p.claim_code,
                    p.distribution_type, p.status,
                    COUNT(v.id) AS issued_vouchers,
                    SUM(CASE WHEN v.status = 'redeemed' THEN 1 ELSE 0 END) AS redeemed_vouchers,
                    COALESCE(SUM(CASE WHEN i.status = 'paid' THEN i.total_amount ELSE 0 END), 0) AS attributed_revenue
             FROM promotions p
             LEFT JOIN vouchers v ON v.promotion_id = p.id
             LEFT JOIN invoices i ON i.voucher_id = v.id
             GROUP BY p.id, p.promotion_name, p.description, p.start_date, p.end_date,
                      p.target_segment, p.campaign_channel, p.discount_type, p.discount_value,
                      p.voucher_quantity, p.usage_limit_per_customer, p.claim_code,
                      p.distribution_type, p.status
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
        $distributionType = in_array(($data['distribution_type'] ?? 'claim_code'), ['auto_issue', 'claim_code'], true)
            ? $data['distribution_type']
            : 'claim_code';
        $channel = in_array(($data['campaign_channel'] ?? 'omnichannel'), ['pos', 'website', 'email', 'zalo', 'sms', 'omnichannel'], true)
            ? $data['campaign_channel']
            : 'omnichannel';
        $claimCode = $this->normalizeClaimCode((string) ($data['claim_code'] ?? ''));
        if ($claimCode === '') {
            $claimCode = $this->uniqueClaimCode($name);
        }
        $this->assertClaimCodeUnique($claimCode);

        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "INSERT INTO promotions (
                    promotion_name, description, start_date, end_date, target_segment,
                    campaign_channel, discount_type, discount_value, voucher_quantity,
                    usage_limit_per_customer, claim_code, distribution_type, status
                 ) VALUES (
                    :name, :description, :start_date, :end_date, :target_segment,
                    :campaign_channel, :discount_type, :discount_value, :voucher_quantity,
                    :usage_limit_per_customer, :claim_code, :distribution_type, 'active'
                 )"
            )->execute([
                'name' => $name,
                'description' => trim((string) ($data['description'] ?? '')),
                'start_date' => $start,
                'end_date' => $end,
                'target_segment' => $target,
                'campaign_channel' => $channel,
                'discount_type' => $discountType,
                'discount_value' => max(0, (float) ($data['discount_value'] ?? 0)),
                'voucher_quantity' => $quantity,
                'usage_limit_per_customer' => max(1, (int) ($data['usage_limit_per_customer'] ?? 1)),
                'claim_code' => $claimCode,
                'distribution_type' => $distributionType,
            ]);
            $promotionId = (int) $this->db->lastInsertId();

            $customers = $distributionType === 'auto_issue' ? $this->targetCustomers($target, $quantity) : [];
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

            (new PosSession())->logFromPayload($data, 'campaign_created', [
                'entity_type' => 'promotion',
                'entity_id' => $promotionId,
                'quantity' => count($customers),
                'amount' => max(0, (float) ($data['discount_value'] ?? 0)),
                'status_to' => 'active',
                'note' => $name,
            ]);

            $this->db->commit();
            return [
                'promotion_id' => $promotionId,
                'claim_code' => $claimCode,
                'distribution_type' => $distributionType,
                'issued_count' => count($customers),
                'campaigns' => $this->performance(),
            ];
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function save(array $data): array
    {
        return (int) ($data['id'] ?? 0) > 0 ? $this->update($data) : $this->create($data);
    }

    public function update(array $data): array
    {
        $id = (int) ($data['id'] ?? 0);
        if ($id <= 0) {
            throw new InvalidArgumentException('Campaign id is required.');
        }

        $currentStmt = $this->db->prepare('SELECT * FROM promotions WHERE id = :id FOR UPDATE');
        $this->db->beginTransaction();
        try {
            $currentStmt->execute(['id' => $id]);
            $current = $currentStmt->fetch();
            if (!$current) {
                throw new InvalidArgumentException('Campaign not found.');
            }

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
            $distributionType = in_array(($data['distribution_type'] ?? 'claim_code'), ['auto_issue', 'claim_code'], true)
                ? $data['distribution_type']
                : 'claim_code';
            $channel = in_array(($data['campaign_channel'] ?? 'omnichannel'), ['pos', 'website', 'email', 'zalo', 'sms', 'omnichannel'], true)
                ? $data['campaign_channel']
                : 'omnichannel';
            $status = in_array(($data['status'] ?? $current['status']), ['draft', 'active', 'cancelled', 'completed'], true)
                ? $data['status']
                : $current['status'];
            $claimCode = $this->normalizeClaimCode((string) ($data['claim_code'] ?? ''));
            if ($claimCode === '') {
                $claimCode = (string) ($current['claim_code'] ?: $this->uniqueClaimCode($name));
            }
            $this->assertClaimCodeUnique($claimCode, $id);

            $this->db->prepare(
                "UPDATE promotions
                 SET promotion_name = :name,
                     description = :description,
                     start_date = :start_date,
                     end_date = :end_date,
                     target_segment = :target_segment,
                     campaign_channel = :campaign_channel,
                     discount_type = :discount_type,
                     discount_value = :discount_value,
                     voucher_quantity = :voucher_quantity,
                     usage_limit_per_customer = :usage_limit_per_customer,
                     claim_code = :claim_code,
                     distribution_type = :distribution_type,
                     status = :status
                 WHERE id = :id"
            )->execute([
                'id' => $id,
                'name' => $name,
                'description' => trim((string) ($data['description'] ?? '')),
                'start_date' => $start,
                'end_date' => $end,
                'target_segment' => $target,
                'campaign_channel' => $channel,
                'discount_type' => $discountType,
                'discount_value' => max(0, (float) ($data['discount_value'] ?? 0)),
                'voucher_quantity' => max(0, (int) ($data['voucher_quantity'] ?? 0)),
                'usage_limit_per_customer' => max(1, (int) ($data['usage_limit_per_customer'] ?? 1)),
                'claim_code' => $claimCode,
                'distribution_type' => $distributionType,
                'status' => $status,
            ]);

            if ($status === 'cancelled' && $current['status'] !== 'cancelled') {
                $this->cancelUnredeemedVouchers($id);
            } elseif ($status === 'active' && $current['status'] === 'cancelled') {
                $this->restoreUnredeemedVouchers($id);
            }

            (new PosSession())->logFromPayload($data, 'campaign_updated', [
                'entity_type' => 'promotion',
                'entity_id' => $id,
                'amount' => max(0, (float) ($data['discount_value'] ?? 0)),
                'status_from' => (string) $current['status'],
                'status_to' => $status,
                'note' => $name,
            ]);

            $this->db->commit();

            return [
                'promotion_id' => $id,
                'claim_code' => $claimCode,
                'distribution_type' => $distributionType,
                'issued_count' => 0,
                'campaigns' => $this->performance(),
            ];
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function cancel(int $id, array $payload = []): array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Campaign id is required.');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT id, promotion_name, status FROM promotions WHERE id = :id FOR UPDATE");
            $stmt->execute(['id' => $id]);
            $campaign = $stmt->fetch();
            if (!$campaign) {
                throw new InvalidArgumentException('Campaign not found.');
            }

            $this->db->prepare("UPDATE promotions SET status = 'cancelled' WHERE id = :id")->execute(['id' => $id]);
            $this->cancelUnredeemedVouchers($id);

            (new PosSession())->logFromPayload($payload, 'campaign_cancelled', [
                'entity_type' => 'promotion',
                'entity_id' => $id,
                'status_from' => (string) $campaign['status'],
                'status_to' => 'cancelled',
                'note' => (string) ($payload['reason'] ?? $campaign['promotion_name']),
            ]);

            $this->db->commit();

            return [
                'promotion_id' => $id,
                'campaigns' => $this->performance(),
            ];
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function restore(int $id, array $payload = []): array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Campaign id is required.');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT id, promotion_name, status FROM promotions WHERE id = :id FOR UPDATE");
            $stmt->execute(['id' => $id]);
            $campaign = $stmt->fetch();
            if (!$campaign) {
                throw new InvalidArgumentException('Campaign not found.');
            }

            $this->db->prepare("UPDATE promotions SET status = 'active' WHERE id = :id")->execute(['id' => $id]);
            $this->restoreUnredeemedVouchers($id);

            (new PosSession())->logFromPayload($payload, 'campaign_restored', [
                'entity_type' => 'promotion',
                'entity_id' => $id,
                'status_from' => (string) $campaign['status'],
                'status_to' => 'active',
                'note' => (string) ($payload['reason'] ?? $campaign['promotion_name']),
            ]);

            $this->db->commit();

            return [
                'promotion_id' => $id,
                'campaigns' => $this->performance(),
            ];
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

    private function normalizeClaimCode(string $value): string
    {
        return strtoupper(trim(preg_replace('/[^A-Za-z0-9-]/', '', $value) ?? ''));
    }

    private function assertClaimCodeUnique(string $claimCode, int $excludeId = 0): void
    {
        $sql = 'SELECT COUNT(*) FROM promotions WHERE claim_code = :claim_code';
        $params = ['claim_code' => $claimCode];
        if ($excludeId > 0) {
            $sql .= ' AND id <> :exclude_id';
            $params['exclude_id'] = $excludeId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        if ((int) $stmt->fetchColumn() > 0) {
            throw new InvalidArgumentException('Campaign claim code already exists.');
        }
    }

    private function cancelUnredeemedVouchers(int $promotionId): void
    {
        $this->db->prepare(
            "UPDATE vouchers
             SET status = 'cancelled'
             WHERE promotion_id = :promotion_id
               AND status IN ('issued', 'active', 'reserved')"
        )->execute(['promotion_id' => $promotionId]);
    }

    private function restoreUnredeemedVouchers(int $promotionId): void
    {
        $this->db->prepare(
            "UPDATE vouchers
             SET status = CASE
                WHEN expiration_date < CURDATE() THEN 'expired'
                ELSE 'active'
             END
             WHERE promotion_id = :promotion_id
               AND status = 'cancelled'"
        )->execute(['promotion_id' => $promotionId]);
    }

    private function uniqueClaimCode(string $name): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name) ?: 'CAFE', 0, 8));
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $code = $prefix . '-' . strtoupper(bin2hex(random_bytes(2)));
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM promotions WHERE claim_code = :claim_code');
            $stmt->execute(['claim_code' => $code]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $code;
            }
        }

        throw new InvalidArgumentException('Cannot generate a unique campaign claim code.');
    }
}
