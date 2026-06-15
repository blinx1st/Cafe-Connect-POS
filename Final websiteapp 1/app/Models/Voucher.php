<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use InvalidArgumentException;

final class Voucher extends Model
{
    public function validateForCheckout(?int $voucherId, ?int $customerId, string $salesChannel = 'pos', bool $lock = false): ?array
    {
        if (!$voucherId) {
            return null;
        }
        if (!$customerId) {
            throw new InvalidArgumentException('Vui lòng chọn khách hàng trước khi dùng voucher.');
        }

        $lockSql = $lock ? ' FOR UPDATE' : '';
        $stmt = $this->db->prepare(
            "SELECT v.id, v.voucher_code, v.customer_id, v.status, v.release_date, v.expiration_date,
                    p.promotion_name, p.discount_type, p.discount_value,
                    p.status AS promotion_status, p.campaign_channel
             FROM vouchers v
             JOIN promotions p ON p.id = v.promotion_id
             WHERE v.id = :id
             LIMIT 1" . $lockSql
        );
        $stmt->execute(['id' => $voucherId]);
        $voucher = $stmt->fetch();

        if (!$voucher) {
            throw new InvalidArgumentException('Không tìm thấy voucher.');
        }
        if ((int) $voucher['customer_id'] !== $customerId) {
            throw new InvalidArgumentException('Voucher không thuộc khách hàng này.');
        }
        if (($voucher['promotion_status'] ?? '') !== 'active') {
            throw new InvalidArgumentException('Chiến dịch voucher đã kết thúc hoặc đã bị hủy.');
        }
        if (!$this->channelAllowsCheckout((string) $voucher['campaign_channel'], $salesChannel)) {
            throw new InvalidArgumentException('Voucher không áp dụng cho kênh thanh toán này.');
        }
        if (!in_array($voucher['status'], ['issued', 'active'], true)) {
            throw new InvalidArgumentException($this->unavailableMessage((string) $voucher['status']));
        }
        if ($voucher['release_date'] > today_sql() || $voucher['expiration_date'] < today_sql()) {
            throw new InvalidArgumentException('Voucher đã hết hạn hoặc chưa đến ngày sử dụng.');
        }

        $voucher['discount_value'] = (float) $voucher['discount_value'];
        return $voucher;
    }

    public function discount(array $voucher, float $baseAmount): float
    {
        if ($voucher['discount_type'] === 'percentage') {
            return round($baseAmount * ((float) $voucher['discount_value'] / 100), 0);
        }

        return min($baseAmount, (float) $voucher['discount_value']);
    }

    public function redeem(int $voucherId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE vouchers
             SET status = 'redeemed', used_at = NOW()
             WHERE id = :id AND status IN ('issued', 'active', 'reserved')"
        );
        $stmt->execute(['id' => $voucherId]);
        if ($stmt->rowCount() !== 1) {
            throw new InvalidArgumentException('Voucher không thể chuyển sang trạng thái đã dùng.');
        }
    }

    public function reserve(int $voucherId): void
    {
        $stmt = $this->db->prepare(
            "UPDATE vouchers SET status = 'reserved' WHERE id = :id AND status IN ('issued', 'active')"
        );
        $stmt->execute(['id' => $voucherId]);
        if ($stmt->rowCount() !== 1) {
            throw new InvalidArgumentException('Voucher không thể giữ cho đơn chờ thanh toán.');
        }
    }

    public function restoreIfAvailable(int $voucherId): string
    {
        if ($voucherId <= 0) {
            return '';
        }

        $stmt = $this->db->prepare(
            "SELECT id, status, expiration_date
             FROM vouchers
             WHERE id = :id
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute(['id' => $voucherId]);
        $voucher = $stmt->fetch();
        if (!$voucher || !in_array($voucher['status'], ['reserved', 'redeemed'], true)) {
            return (string) ($voucher['status'] ?? '');
        }

        $nextStatus = $voucher['expiration_date'] >= today_sql() ? 'active' : 'expired';
        $this->db->prepare(
            "UPDATE vouchers
             SET status = :status, used_at = NULL
             WHERE id = :id"
        )->execute(['status' => $nextStatus, 'id' => $voucherId]);

        return $nextStatus;
    }

    public function displayStatus(array $voucher): string
    {
        if (in_array(($voucher['status'] ?? ''), ['issued', 'active'], true)
            && ($voucher['release_date'] ?? '') <= today_sql()
            && ($voucher['expiration_date'] ?? '') >= today_sql()) {
            return 'usable';
        }
        if (($voucher['expiration_date'] ?? '') < today_sql()
            && in_array(($voucher['status'] ?? ''), ['issued', 'active', 'reserved'], true)) {
            return 'expired';
        }

        return (string) ($voucher['status'] ?? '');
    }

    public function canUseOnChannel(array $voucher, string $salesChannel): bool
    {
        return $this->channelAllowsCheckout((string) ($voucher['campaign_channel'] ?? ''), $salesChannel);
    }

    private function channelAllowsCheckout(string $campaignChannel, string $salesChannel): bool
    {
        $checkoutGroup = in_array($salesChannel, ['website', 'delivery'], true) ? 'website' : 'pos';
        if ($campaignChannel === 'omnichannel') {
            return true;
        }

        return $campaignChannel === $checkoutGroup;
    }

    private function unavailableMessage(string $status): string
    {
        return match ($status) {
            'reserved' => 'Voucher đang được giữ cho một đơn chờ thanh toán.',
            'redeemed' => 'Voucher đã được sử dụng.',
            'expired' => 'Voucher đã hết hạn.',
            'cancelled' => 'Voucher đã bị hủy.',
            default => 'Voucher không khả dụng.',
        };
    }
}
