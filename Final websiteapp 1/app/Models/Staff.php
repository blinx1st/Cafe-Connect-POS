<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Staff extends Model
{
    public const ROLES = ['waiter', 'cashier', 'barista', 'owner', 'manager', 'marketing', 'admin'];

    public function all(): array
    {
        return $this->db->query(
            "SELECT s.id, s.staff_code, s.staff_name, s.staff_role, s.phone_number, s.email, s.status,
                    s.branch_id, b.branch_name, sh.shift_name
             FROM staff s
             JOIN branches b ON b.id = s.branch_id
             LEFT JOIN staff_shifts sh ON sh.staff_id = s.id AND sh.status = 'active'
             WHERE s.status = 'active'
             ORDER BY FIELD(s.staff_role, 'waiter', 'cashier', 'barista', 'owner', 'manager', 'marketing', 'admin'), s.staff_name"
        )->fetchAll();
    }

    public function branches(): array
    {
        return $this->db->query(
            "SELECT id, branch_name, address, district
             FROM branches
             WHERE status = 'active'
             ORDER BY id"
        )->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT s.id, s.staff_code, s.staff_name, s.staff_role, s.phone_number, s.email, s.status,
                    s.branch_id, b.branch_name
             FROM staff s
             JOIN branches b ON b.id = s.branch_id
             WHERE s.id = :id AND s.status = 'active'
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $staff = $stmt->fetch();

        return $staff ?: null;
    }

    public function verifyPin(int $id, string $pin): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT s.id, s.staff_code, s.staff_name, s.staff_role, s.phone_number, s.email, s.status,
                    s.branch_id, s.pin_hash, b.branch_name
             FROM staff s
             JOIN branches b ON b.id = s.branch_id
             WHERE s.id = :id AND s.status = 'active'
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $staff = $stmt->fetch();
        if (!$staff || empty($staff['pin_hash']) || !password_verify($pin, (string) $staff['pin_hash'])) {
            return null;
        }

        unset($staff['pin_hash']);
        return $staff;
    }

    public function verifyPassword(string $identity, string $password): ?array
    {
        $identity = trim($identity);
        $stmt = $this->db->prepare(
            "SELECT s.id, s.staff_code, s.staff_name, s.staff_role, s.phone_number, s.email, s.status,
                    s.branch_id, s.password_hash, b.branch_name
             FROM staff s
             JOIN branches b ON b.id = s.branch_id
             WHERE s.status = 'active'
               AND (s.staff_code = :staff_code OR s.email = :email OR s.phone_number = :phone_number)
             LIMIT 1"
        );
        $stmt->execute([
            'staff_code' => $identity,
            'email' => $identity,
            'phone_number' => $identity,
        ]);
        $staff = $stmt->fetch();
        if (!$staff || empty($staff['password_hash']) || !password_verify($password, (string) $staff['password_hash'])) {
            return null;
        }

        unset($staff['password_hash']);
        return $staff;
    }

    public function touchLogin(int $id): void
    {
        $this->db->prepare("UPDATE staff SET last_login_at = NOW() WHERE id = :id")
            ->execute(['id' => $id]);
    }

    public function save(array $data): array
    {
        $id = (int) ($data['id'] ?? 0);
        $payload = [
            'branch_id' => max(1, (int) ($data['branch_id'] ?? 1)),
            'staff_code' => strtoupper(trim((string) ($data['staff_code'] ?? ''))) ?: null,
            'staff_name' => require_field($data, 'staff_name', 'Staff name'),
            'staff_role' => in_array(($data['staff_role'] ?? 'waiter'), self::ROLES, true) ? $data['staff_role'] : 'waiter',
            'phone_number' => trim((string) ($data['phone_number'] ?? '')) ?: null,
            'email' => trim((string) ($data['email'] ?? '')) ?: null,
            'status' => in_array(($data['status'] ?? 'active'), ['active', 'inactive'], true) ? $data['status'] : 'active',
        ];

        if ($id > 0) {
            $payload['id'] = $id;
            $this->db->prepare(
                "UPDATE staff
                 SET branch_id = :branch_id, staff_code = :staff_code, staff_name = :staff_name, staff_role = :staff_role,
                     phone_number = :phone_number, email = :email, status = :status
                 WHERE id = :id"
            )->execute($payload);
            if (trim((string) ($data['password'] ?? '')) !== '') {
                $this->db->prepare("UPDATE staff SET password_hash = :password_hash WHERE id = :id")
                    ->execute(['password_hash' => password_hash((string) $data['password'], PASSWORD_DEFAULT), 'id' => $id]);
            }
            if (trim((string) ($data['pin'] ?? '')) !== '') {
                $this->db->prepare("UPDATE staff SET pin_hash = :pin_hash WHERE id = :id")
                    ->execute(['pin_hash' => password_hash((string) $data['pin'], PASSWORD_DEFAULT), 'id' => $id]);
            }
        } else {
            $payload['password_hash'] = trim((string) ($data['password'] ?? '')) !== ''
                ? password_hash((string) $data['password'], PASSWORD_DEFAULT)
                : null;
            $payload['pin_hash'] = trim((string) ($data['pin'] ?? '')) !== ''
                ? password_hash((string) $data['pin'], PASSWORD_DEFAULT)
                : null;
            $this->db->prepare(
                "INSERT INTO staff (branch_id, staff_code, staff_name, staff_role, phone_number, email, password_hash, pin_hash, status)
                 VALUES (:branch_id, :staff_code, :staff_name, :staff_role, :phone_number, :email, :password_hash, :pin_hash, :status)"
            )->execute($payload);
            $id = (int) $this->db->lastInsertId();
        }

        return ['id' => $id, 'staff' => $this->all()];
    }
}
