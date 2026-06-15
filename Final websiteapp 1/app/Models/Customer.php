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
        $customer['claimable_vouchers'] = $this->claimableVouchers((int) $customer['id'], $customer);
        $customer['history'] = $this->history((int) $customer['id']);
        $customer['favorites'] = $this->favorites((int) $customer['id']);
        $customer['website_orders'] = (new WebsiteOrder())->forCustomer((int) $customer['id']);

        return $customer;
    }

    public function authByIdentity(string $identity): ?array
    {
        $identity = trim($identity);
        if ($identity === '') {
            throw new InvalidArgumentException('Phone or email is required.');
        }

        $stmt = $this->db->prepare(
            "SELECT id, customer_name, phone_number, email, password_hash, status
             FROM customers
             WHERE phone_number = :phone_identity OR email = :email_identity
             LIMIT 1"
        );
        $stmt->execute([
            'phone_identity' => $identity,
            'email_identity' => $identity,
        ]);
        $customer = $stmt->fetch();

        return $customer ?: null;
    }

    public function touchLogin(int $customerId): void
    {
        $this->db->prepare("UPDATE customers SET last_login_at = NOW() WHERE id = :id")
            ->execute(['id' => $customerId]);
    }

    public function passwordHash(int $customerId): ?string
    {
        $stmt = $this->db->prepare("SELECT password_hash FROM customers WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $customerId]);
        $hash = $stmt->fetchColumn();

        return $hash ? (string) $hash : null;
    }

    public function updatePassword(int $customerId, string $passwordHash): void
    {
        $this->db->prepare(
            "UPDATE customers
             SET password_hash = :password_hash, updated_at = NOW()
             WHERE id = :id"
        )->execute(['id' => $customerId, 'password_hash' => $passwordHash]);
    }

    public function updateProfile(int $customerId, array $data): array
    {
        $name = require_field($data, 'customer_name', 'Customer name');
        $email = trim((string) ($data['email'] ?? '')) ?: null;
        $gender = in_array(($data['gender'] ?? ''), ['male', 'female', 'other'], true) ? $data['gender'] : null;
        $birthDate = trim((string) ($data['birth_date'] ?? '')) ?: null;
        $address = trim((string) ($data['address'] ?? '')) ?: null;

        if ($email !== null) {
            $existing = $this->authByIdentity($email);
            if ($existing && (int) $existing['id'] !== $customerId) {
                throw new InvalidArgumentException('Email này đã được dùng bởi tài khoản khác.');
            }
        }

        $this->db->prepare(
            "UPDATE customers
             SET customer_name = :customer_name,
                 email = :email,
                 gender = :gender,
                 birth_date = :birth_date,
                 address = :address,
                 updated_at = NOW()
             WHERE id = :id"
        )->execute([
            'id' => $customerId,
            'customer_name' => $name,
            'email' => $email,
            'gender' => $gender,
            'birth_date' => $birthDate,
            'address' => $address,
        ]);

        return $this->lookup((string) $customerId) ?: [];
    }

    public function createPasswordReset(int $customerId, string $token, string $requestIp = ''): array
    {
        $this->ensurePasswordResetTable();
        $tokenHash = hash('sha256', $token);
        $this->db->prepare(
            "UPDATE customer_password_resets
             SET used_at = NOW()
             WHERE customer_id = :customer_id AND used_at IS NULL"
        )->execute(['customer_id' => $customerId]);

        $this->db->prepare(
            "INSERT INTO customer_password_resets (customer_id, token_hash, expires_at, request_ip)
             VALUES (:customer_id, :token_hash, DATE_ADD(NOW(), INTERVAL 30 MINUTE), :request_ip)"
        )->execute([
            'customer_id' => $customerId,
            'token_hash' => $tokenHash,
            'request_ip' => $requestIp !== '' ? $requestIp : null,
        ]);

        return ['token_hash' => $tokenHash];
    }

    public function findPasswordReset(string $token): ?array
    {
        $this->ensurePasswordResetTable();
        $stmt = $this->db->prepare(
            "SELECT pr.id, pr.customer_id, pr.expires_at, c.customer_name, c.email, c.status
             FROM customer_password_resets pr
             JOIN customers c ON c.id = pr.customer_id
             WHERE pr.token_hash = :token_hash
               AND pr.used_at IS NULL
               AND pr.expires_at >= NOW()
             LIMIT 1"
        );
        $stmt->execute(['token_hash' => hash('sha256', $token)]);
        $reset = $stmt->fetch();

        return $reset ?: null;
    }

    public function markPasswordResetUsed(int $resetId): void
    {
        $this->ensurePasswordResetTable();
        $this->db->prepare(
            "UPDATE customer_password_resets SET used_at = NOW() WHERE id = :id"
        )->execute(['id' => $resetId]);
    }

    private function ensurePasswordResetTable(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS customer_password_resets (
                id INT AUTO_INCREMENT PRIMARY KEY,
                customer_id INT NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                request_ip VARCHAR(45) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_customer_password_resets_token (token_hash),
                KEY idx_customer_password_resets_customer (customer_id),
                KEY idx_customer_password_resets_expires (expires_at),
                CONSTRAINT fk_customer_password_resets_customer
                    FOREIGN KEY (customer_id) REFERENCES customers(id)
                    ON UPDATE CASCADE
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    public function vouchers(int $customerId): array
    {
        $stmt = $this->db->prepare(
            "SELECT v.id, v.voucher_code, v.release_date, v.expiration_date, v.status,
                    p.promotion_name, p.discount_type, p.discount_value,
                    p.campaign_channel, p.status AS promotion_status
             FROM vouchers v
             JOIN promotions p ON p.id = v.promotion_id
             WHERE v.customer_id = :customer_id
             ORDER BY FIELD(v.status, 'active', 'issued', 'reserved', 'redeemed', 'expired', 'cancelled'),
                      v.expiration_date DESC"
        );
        $stmt->execute(['customer_id' => $customerId]);

        $rows = $stmt->fetchAll();
        $voucherModel = new Voucher();
        foreach ($rows as &$row) {
            $row['discount_value'] = (float) $row['discount_value'];
            $row['display_status'] = $voucherModel->displayStatus($row);
            $row['usable'] = in_array($row['status'], ['issued', 'active'], true)
                && $row['release_date'] <= today_sql()
                && $row['expiration_date'] >= today_sql()
                && $row['promotion_status'] === 'active';
            $row['usable_on_website'] = $row['usable'] && $voucherModel->canUseOnChannel($row, 'website');
            $row['usable_on_pos'] = $row['usable'] && $voucherModel->canUseOnChannel($row, 'pos');
        }

        return $rows;
    }

    public function claimableVouchers(int $customerId, ?array $customer = null): array
    {
        $customer ??= $this->lookup((string) $customerId);
        if (!$customer) {
            return [];
        }

        $today = today_sql();
        $stmt = $this->db->prepare(
            "SELECT p.id, p.promotion_name, p.description, p.start_date, p.end_date,
                    p.target_segment, p.campaign_channel, p.discount_type, p.discount_value,
                    p.voucher_quantity, p.usage_limit_per_customer,
                    COUNT(v.id) AS issued_count,
                    SUM(CASE WHEN v.customer_id = :customer_id_claim THEN 1 ELSE 0 END) AS customer_claim_count,
                    SUM(CASE WHEN v.customer_id = :customer_id_active AND v.status IN ('issued', 'active', 'reserved') THEN 1 ELSE 0 END) AS customer_active_count
             FROM promotions p
             LEFT JOIN vouchers v ON v.promotion_id = p.id
             WHERE p.status = 'active'
               AND p.start_date <= :today_start
               AND p.end_date >= :today_end
               AND p.campaign_channel IN ('website', 'omnichannel')
             GROUP BY p.id, p.promotion_name, p.description, p.start_date, p.end_date,
                      p.target_segment, p.campaign_channel, p.discount_type, p.discount_value,
                      p.voucher_quantity, p.usage_limit_per_customer
             ORDER BY p.end_date, p.id"
        );
        $stmt->execute([
            'customer_id_claim' => $customerId,
            'customer_id_active' => $customerId,
            'today_start' => $today,
            'today_end' => $today,
        ]);

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $row['discount_value'] = (float) $row['discount_value'];
            $row['voucher_quantity'] = (int) $row['voucher_quantity'];
            $row['usage_limit_per_customer'] = (int) $row['usage_limit_per_customer'];
            $row['issued_count'] = (int) $row['issued_count'];
            $row['customer_claim_count'] = (int) $row['customer_claim_count'];
            $row['customer_active_count'] = (int) $row['customer_active_count'];
            $row['remaining_quantity'] = $row['voucher_quantity'] > 0
                ? max(0, $row['voucher_quantity'] - $row['issued_count'])
                : null;
            $row['eligible'] = $this->customerMatchesPromotion($customer, (string) $row['target_segment']);
            $row['can_claim'] = $row['eligible']
                && ($row['remaining_quantity'] === null || $row['remaining_quantity'] > 0)
                && $row['customer_claim_count'] < max(1, $row['usage_limit_per_customer']);
            $rows[] = $row;
        }

        return $rows;
    }

    public function claimVoucher(int $customerId, int $promotionId): array
    {
        if ($customerId <= 0 || $promotionId <= 0) {
            throw new InvalidArgumentException('Claim voucher requires customer and promotion.');
        }

        return $this->claimVoucherInternal($customerId, $promotionId, null, 'voucher_claim');
    }

    public function claimVoucherByCode(int $customerId, string $claimCode): array
    {
        $claimCode = strtoupper(trim($claimCode));
        if ($customerId <= 0 || $claimCode === '') {
            throw new InvalidArgumentException('Vui lòng nhập mã voucher.');
        }

        return $this->claimVoucherInternal($customerId, null, $claimCode, 'voucher_claim_code');
    }

    private function claimVoucherInternal(int $customerId, ?int $promotionId, ?string $claimCode, string $action): array
    {
        $this->db->beginTransaction();
        try {
            $customerStmt = $this->db->prepare(
                "SELECT c.id, c.customer_name, c.phone_number, c.email, c.birth_date, c.last_visit_date,
                        mt.tier_name
                 FROM customers c
                 JOIN membership_tiers mt ON mt.id = c.membership_tier_id
                 WHERE c.id = :id AND c.status = 'active'
                 FOR UPDATE"
            );
            $customerStmt->execute(['id' => $customerId]);
            $customer = $customerStmt->fetch();
            if (!$customer) {
                throw new InvalidArgumentException('Member account is not available for voucher claim.');
            }

            $today = today_sql();
            $promotionWhere = $promotionId !== null ? 'id = :promotion_key' : 'claim_code = :promotion_key';
            $promotionStmt = $this->db->prepare(
                "SELECT *
                 FROM promotions
                 WHERE $promotionWhere
                   AND status = 'active'
                   AND start_date <= :today_start
                   AND end_date >= :today_end
                   AND campaign_channel IN ('website', 'omnichannel')
                 FOR UPDATE"
            );
            $promotionStmt->execute([
                'promotion_key' => $promotionId ?? $claimCode,
                'today_start' => $today,
                'today_end' => $today,
            ]);
            $promotion = $promotionStmt->fetch();
            if (!$promotion) {
                throw new InvalidArgumentException('Voucher campaign is not available.');
            }
            $promotionId = (int) $promotion['id'];
            if (!$this->customerMatchesPromotion($customer, (string) $promotion['target_segment'])) {
                throw new InvalidArgumentException('Member is not eligible for this voucher campaign.');
            }

            $countStmt = $this->db->prepare(
                "SELECT
                    COUNT(*) AS issued_count,
                    SUM(customer_id = :customer_id) AS customer_claim_count
                 FROM vouchers
                 WHERE promotion_id = :promotion_id"
            );
            $countStmt->execute(['customer_id' => $customerId, 'promotion_id' => $promotionId]);
            $counts = $countStmt->fetch() ?: ['issued_count' => 0, 'customer_claim_count' => 0];
            $issuedCount = (int) ($counts['issued_count'] ?? 0);
            $customerClaimCount = (int) ($counts['customer_claim_count'] ?? 0);
            $quantity = (int) ($promotion['voucher_quantity'] ?? 0);
            $limit = max(1, (int) ($promotion['usage_limit_per_customer'] ?? 1));

            if ($quantity > 0 && $issuedCount >= $quantity) {
                throw new InvalidArgumentException('Voucher campaign has no remaining quantity.');
            }
            if ($customerClaimCount >= $limit) {
                throw new InvalidArgumentException('Member has already claimed this voucher campaign.');
            }

            $code = $this->uniqueVoucherCode((string) $promotion['promotion_name'], $promotionId, $customerId);
            $this->db->prepare(
                "INSERT INTO vouchers (voucher_code, customer_id, promotion_id, release_date, expiration_date, status)
                 VALUES (:code, :customer_id, :promotion_id, :release_date, :expiration_date, 'active')"
            )->execute([
                'code' => $code,
                'customer_id' => $customerId,
                'promotion_id' => $promotionId,
                'release_date' => $today,
                'expiration_date' => $promotion['end_date'],
            ]);
            $voucherId = (int) $this->db->lastInsertId();

            (new AuditLog())->record([
                'actor_type' => 'customer',
                'actor_id' => $customerId,
                'action' => $action,
                'entity_type' => 'voucher',
                'entity_id' => $voucherId,
                'metadata' => [
                    'promotion_id' => $promotionId,
                    'claim_code' => $claimCode,
                    'voucher_code' => $code,
                ],
            ]);

            $this->db->commit();

            return [
                'voucher_id' => $voucherId,
                'voucher_code' => $code,
                'member' => $this->lookup((string) $customerId),
            ];
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
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

    private function customerMatchesPromotion(array $customer, string $target): bool
    {
        return match ($target) {
            'all' => true,
            'bronze', 'silver', 'gold' => strtolower((string) ($customer['tier_name'] ?? '')) === $target,
            'birthday' => !empty($customer['birth_date']) && date('m', strtotime((string) $customer['birth_date'])) === date('m'),
            'inactive' => empty($customer['last_visit_date']) || strtotime((string) $customer['last_visit_date']) <= strtotime('-30 days'),
            default => false,
        };
    }

    private function uniqueVoucherCode(string $promotionName, int $promotionId, int $customerId): string
    {
        $prefix = strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $promotionName) ?: 'CLAIM', 0, 6));
        for ($attempt = 0; $attempt < 8; $attempt++) {
            $code = sprintf('%s-%03d-%04d-%s', $prefix, $promotionId, $customerId, strtoupper(bin2hex(random_bytes(2))));
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM vouchers WHERE voucher_code = :code");
            $stmt->execute(['code' => $code]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $code;
            }
        }

        throw new InvalidArgumentException('Cannot generate a unique voucher code.');
    }

    public function create(array $data): array
    {
        $name = require_field($data, 'customer_name', 'Customer name');
        $phone = require_field($data, 'phone_number', 'Phone number');
        $email = trim((string) ($data['email'] ?? '')) ?: null;
        $passwordHash = trim((string) ($data['password_hash'] ?? '')) ?: null;

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
                    birth_date, address, preferred_channel, password_hash, current_points, total_spending, status
                 ) VALUES (
                    :tier_id, :customer_name, :phone_number, :email, :gender,
                    :birth_date, :address, :preferred_channel, :password_hash, 0, 0, 'active'
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
                'password_hash' => $passwordHash,
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
