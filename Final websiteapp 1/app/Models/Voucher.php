<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use InvalidArgumentException;

final class Voucher extends Model
{
    public function validateForCheckout(?int $voucherId, ?int $customerId): ?array
    {
        if (!$voucherId) {
            return null;
        }
        if (!$customerId) {
            throw new InvalidArgumentException('Voucher requires a selected customer.');
        }

        $stmt = $this->db->prepare(
            "SELECT v.id, v.voucher_code, v.customer_id, v.status, v.release_date, v.expiration_date,
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

    public function discount(array $voucher, float $baseAmount): float
    {
        if ($voucher['discount_type'] === 'percentage') {
            return round($baseAmount * ((float) $voucher['discount_value'] / 100), 0);
        }

        return min($baseAmount, (float) $voucher['discount_value']);
    }

    public function redeem(int $voucherId): void
    {
        $this->db->prepare(
            "UPDATE vouchers SET status = 'redeemed', used_at = NOW() WHERE id = :id"
        )->execute(['id' => $voucherId]);
    }
}
