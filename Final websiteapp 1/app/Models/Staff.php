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
            "SELECT s.id, s.staff_name, s.staff_role, s.phone_number, s.email, s.status,
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
            "SELECT s.id, s.staff_name, s.staff_role, s.phone_number, s.email, s.status,
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

    public function save(array $data): array
    {
        $id = (int) ($data['id'] ?? 0);
        $payload = [
            'branch_id' => max(1, (int) ($data['branch_id'] ?? 1)),
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
                 SET branch_id = :branch_id, staff_name = :staff_name, staff_role = :staff_role,
                     phone_number = :phone_number, email = :email, status = :status
                 WHERE id = :id"
            )->execute($payload);
        } else {
            $this->db->prepare(
                "INSERT INTO staff (branch_id, staff_name, staff_role, phone_number, email, status)
                 VALUES (:branch_id, :staff_name, :staff_role, :phone_number, :email, :status)"
            )->execute($payload);
            $id = (int) $this->db->lastInsertId();
        }

        return ['id' => $id, 'staff' => $this->all()];
    }
}
