<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use InvalidArgumentException;
use PDOException;

final class Customer extends Model
{
    public function lookup(string $identity): ?array
    {
        $identity = trim($identity);
        if ($identity === '') {
            throw new InvalidArgumentException('Phone or email is required.');
        }

        $stmt = $this->db->prepare(
            "SELECT c.id, c.customer_name, c.phone_number, c.email, c.gender, c.birth_date,
                    c.address, c.preferred_channel, c.last_visit_date, c.current_points,
                    c.total_spending, c.status, mt.tier_name, mt.discount_rate
             FROM customers c
             JOIN membership_tiers mt ON mt.id = c.membership_tier_id
             WHERE c.phone_number = :phone_identity OR c.email = :email_identity OR c.id = :numeric_id
             LIMIT 1"
        );
        $stmt->execute([
            'phone_identity' => $identity,
            'email_identity' => $identity,
            'numeric_id' => ctype_digit($identity) ? (int) $identity : 0,
        ]);
        $customer = $stmt->fetch();

        if (!$customer) {
            return null;
        }

        $customer['current_points'] = (int) $customer['current_points'];
        $customer['total_spending'] = (float) $customer['total_spending'];
        $customer['discount_rate'] = (float) $customer['discount_rate'];
        $customer['vouchers'] = $this->vouchers((int) $customer['id']);
        $customer['history'] = $this->history((int) $customer['id']);
        $customer['favorites'] = $this->favorites((int) $customer['id']);

        return $customer;
    }

    public function vouchers(int $customerId): array
    {
        $stmt = $this->db->prepare(
            "SELECT v.id, v.voucher_code, v.release_date, v.expiration_date, v.status,
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

    public function history(int $customerId, int $limit = 8): array
    {
        $stmt = $this->db->prepare(
            "SELECT i.id, i.invoice_date, i.invoice_time, i.sales_channel,
                    i.subtotal_amount, i.membership_discount_amount, i.voucher_discount_amount,
                    i.total_amount, i.points_earned, i.payment_method, b.branch_name,
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

    public function favorites(int $customerId): array
    {
        $stmt = $this->db->prepare(
            "SELECT product_id
             FROM customer_favorites
             WHERE customer_id = :customer_id"
        );
        $stmt->execute(['customer_id' => $customerId]);

        return array_map('intval', array_column($stmt->fetchAll(), 'product_id'));
    }

    public function create(array $data): array
    {
        $name = require_field($data, 'customer_name', 'Customer name');
        $phone = require_field($data, 'phone_number', 'Phone number');
        $email = trim((string) ($data['email'] ?? '')) ?: null;

        $existing = $this->lookup($phone);
        if ($existing) {
            $existing['was_existing'] = true;
            return $existing;
        }

        $tierId = (int) $this->db->query(
            "SELECT id FROM membership_tiers ORDER BY min_total_spending ASC LIMIT 1"
        )->fetchColumn();

        try {
            $this->db->prepare(
                "INSERT INTO customers (
                    membership_tier_id, customer_name, phone_number, email, gender,
                    birth_date, address, preferred_channel, current_points, total_spending, status
                 ) VALUES (
                    :tier_id, :customer_name, :phone_number, :email, :gender,
                    :birth_date, :address, :preferred_channel, 0, 0, 'active'
                 )"
            )->execute([
                'tier_id' => $tierId,
                'customer_name' => $name,
                'phone_number' => $phone,
                'email' => $email,
                'gender' => in_array(($data['gender'] ?? ''), ['male', 'female', 'other'], true) ? $data['gender'] : null,
                'birth_date' => trim((string) ($data['birth_date'] ?? '')) ?: null,
                'address' => trim((string) ($data['address'] ?? '')) ?: null,
                'preferred_channel' => in_array(($data['preferred_channel'] ?? 'pos'), ['pos', 'website', 'delivery', 'email', 'zalo', 'sms'], true)
                    ? $data['preferred_channel']
                    : 'pos',
            ]);
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $existing = $this->lookup($phone);
                if ($existing) {
                    $existing['was_existing'] = true;
                    return $existing;
                }
            }
            throw $exception;
        }

        return $this->lookup($phone) ?: [];
    }

    public function newsletterSubscribe(array $data): array
    {
        $email = require_field($data, 'email', 'Email');
        $name = trim((string) ($data['name'] ?? '')) ?: null;

        $this->db->prepare(
            "INSERT INTO newsletter_subscribers (email, subscriber_name, status)
             VALUES (:email, :subscriber_name, 'active')
             ON DUPLICATE KEY UPDATE subscriber_name = VALUES(subscriber_name), status = 'active'"
        )->execute(['email' => $email, 'subscriber_name' => $name]);

        return ['email' => $email];
    }

    public function toggleFavorite(array $data): array
    {
        $customerId = (int) ($data['customer_id'] ?? 0);
        $productId = (int) ($data['product_id'] ?? 0);
        if ($customerId <= 0 || $productId <= 0) {
            throw new InvalidArgumentException('Customer and product are required.');
        }

        $stmt = $this->db->prepare(
            "SELECT 1 FROM customer_favorites WHERE customer_id = :customer_id AND product_id = :product_id"
        );
        $stmt->execute(['customer_id' => $customerId, 'product_id' => $productId]);

        if ($stmt->fetchColumn()) {
            $this->db->prepare(
                "DELETE FROM customer_favorites WHERE customer_id = :customer_id AND product_id = :product_id"
            )->execute(['customer_id' => $customerId, 'product_id' => $productId]);
            return ['favorited' => false, 'favorites' => $this->favorites($customerId)];
        }

        $this->db->prepare(
            "INSERT INTO customer_favorites (customer_id, product_id) VALUES (:customer_id, :product_id)"
        )->execute(['customer_id' => $customerId, 'product_id' => $productId]);

        return ['favorited' => true, 'favorites' => $this->favorites($customerId)];
    }

    public function reviews(): array
    {
        return $this->db->query(
            "SELECT cr.customer_name, cr.customer_title, cr.rating, cr.review_text, cr.avatar_path
             FROM customer_reviews cr
             WHERE cr.status = 'published'
             ORDER BY cr.created_at DESC
             LIMIT 6"
        )->fetchAll();
    }
}
