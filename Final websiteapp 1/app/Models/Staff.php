<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\RolePolicy;
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

    public function allForAdmin(): array
    {
        return $this->db->query(
            "SELECT s.id, s.staff_code, s.staff_name, s.staff_role, s.phone_number, s.email, s.status,
                    s.branch_id, b.branch_name, sh.shift_name
             FROM staff s
             JOIN branches b ON b.id = s.branch_id
             LEFT JOIN staff_shifts sh ON sh.staff_id = s.id AND sh.status = 'active'
             ORDER BY FIELD(s.status, 'active', 'inactive'),
                      FIELD(s.staff_role, 'owner', 'admin', 'manager', 'cashier', 'waiter', 'barista', 'marketing'),
                      s.staff_name"
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

    public function updateProfile(int $id, array $data): array
    {
        $payload = [
            'id' => $id,
            'staff_name' => require_field($data, 'staff_name', 'Staff name'),
            'phone_number' => trim((string) ($data['phone_number'] ?? '')) ?: null,
            'email' => trim((string) ($data['email'] ?? '')) ?: null,
        ];

        $this->db->prepare(
            "UPDATE staff
             SET staff_name = :staff_name,
                 phone_number = :phone_number,
                 email = :email
             WHERE id = :id AND status = 'active'"
        )->execute($payload);

        return $this->find($id) ?: ['id' => $id] + $payload;
    }

    public function passwordHash(int $id): ?string
    {
        $stmt = $this->db->prepare("SELECT password_hash FROM staff WHERE id = :id AND status = 'active' LIMIT 1");
        $stmt->execute(['id' => $id]);
        $hash = $stmt->fetchColumn();

        return $hash ? (string) $hash : null;
    }

    public function updatePassword(int $id, string $passwordHash): void
    {
        $this->db->prepare("UPDATE staff SET password_hash = :password_hash WHERE id = :id AND status = 'active'")
            ->execute(['password_hash' => $passwordHash, 'id' => $id]);
    }

    public function pinHash(int $id): ?string
    {
        $stmt = $this->db->prepare("SELECT pin_hash FROM staff WHERE id = :id AND status = 'active' LIMIT 1");
        $stmt->execute(['id' => $id]);
        $hash = $stmt->fetchColumn();

        return $hash ? (string) $hash : null;
    }

    public function updatePin(int $id, string $pinHash): void
    {
        $this->db->prepare("UPDATE staff SET pin_hash = :pin_hash WHERE id = :id AND status = 'active'")
            ->execute(['pin_hash' => $pinHash, 'id' => $id]);
    }

    public function save(array $data, array $actor = []): array
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

        $actorRole = (string) ($actor['staff_role'] ?? '');
        $actorId = (int) ($actor['id'] ?? 0);
        if ($actorRole !== '') {
            if ($id > 0) {
                $target = $this->findAny($id);
                if (!$target) {
                    throw new \InvalidArgumentException('Không tìm thấy nhân viên.');
                }
                $sameAccount = $actorId > 0 && $actorId === (int) $target['id'];
                if (!RolePolicy::canManageStaffRole($actorRole, (string) $target['staff_role'], $sameAccount)) {
                    throw new \InvalidArgumentException('Bạn không có quyền chỉnh sửa tài khoản này.');
                }
                if ($payload['staff_role'] === 'owner' && $actorRole !== 'owner') {
                    throw new \InvalidArgumentException('Chỉ Owner được cấp hoặc chuyển role Owner.');
                }
            } elseif (!RolePolicy::canCreateStaffRole($actorRole, (string) $payload['staff_role'])) {
                throw new \InvalidArgumentException('Bạn không có quyền tạo nhân viên với role này.');
            }
        }

        try {
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
                $password = trim((string) ($data['password'] ?? ''));
                $pin = trim((string) ($data['pin'] ?? ''));
                if ($password === '') {
                    throw new \InvalidArgumentException('Mật khẩu POS là bắt buộc khi tạo nhân viên mới.');
                }
                if (strlen($password) < 6) {
                    throw new \InvalidArgumentException('Mật khẩu POS phải có ít nhất 6 ký tự.');
                }
                if ($pin === '' || strlen($pin) < 4) {
                    throw new \InvalidArgumentException('PIN mở ca phải có ít nhất 4 ký tự khi tạo nhân viên mới.');
                }
                $payload['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                $payload['pin_hash'] = password_hash($pin, PASSWORD_DEFAULT);
                $this->db->prepare(
                    "INSERT INTO staff (branch_id, staff_code, staff_name, staff_role, phone_number, email, password_hash, pin_hash, status)
                     VALUES (:branch_id, :staff_code, :staff_name, :staff_role, :phone_number, :email, :password_hash, :pin_hash, :status)"
                )->execute($payload);
                $id = (int) $this->db->lastInsertId();
            }
        } catch (\PDOException $exception) {
            if ($exception->getCode() === '23000') {
                throw new \InvalidArgumentException('Mã nhân viên hoặc email đã tồn tại.');
            }
            throw $exception;
        }

        return ['id' => $id, 'staff' => $this->allForAdmin()];
    }

    public function deactivate(int $id, array $actor = []): array
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Nhân viên không hợp lệ.');
        }
        $actorRole = (string) ($actor['staff_role'] ?? '');
        $actorId = (int) ($actor['id'] ?? 0);
        if ($actorId > 0 && $id === $actorId && $actorRole !== 'owner') {
            throw new \InvalidArgumentException('Không thể ngừng hoạt động chính tài khoản đang đăng nhập.');
        }

        $staff = $this->findAny($id);
        if (!$staff) {
            throw new \InvalidArgumentException('Không tìm thấy nhân viên.');
        }
        $sameAccount = $actorId > 0 && $actorId === $id;
        if ($actorRole !== '' && !RolePolicy::canManageStaffRole($actorRole, (string) $staff['staff_role'], $sameAccount)) {
            throw new \InvalidArgumentException('Bạn không có quyền ngừng hoặc xóa tài khoản này.');
        }
        if (!$sameAccount && in_array((string) $staff['staff_role'], ['owner', 'admin'], true) && $this->activeAdminCount() <= 1) {
            throw new \InvalidArgumentException('Phải giữ lại ít nhất một tài khoản owner/admin đang hoạt động.');
        }

        $this->db->prepare("UPDATE staff SET status = 'inactive' WHERE id = :id")
            ->execute(['id' => $id]);
        $this->db->prepare("UPDATE staff_login_sessions SET status = 'logged_out', logged_out_at = NOW() WHERE staff_id = :id AND status = 'active'")
            ->execute(['id' => $id]);
        $this->db->prepare("UPDATE pos_sessions SET status = 'closed', closed_at = NOW(), closed_reason = 'staff_deactivated' WHERE staff_id = :id AND status = 'open'")
            ->execute(['id' => $id]);

        return ['id' => $id, 'staff' => $this->allForAdmin()];
    }

    public function restore(int $id, array $actor = []): array
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Nhân viên không hợp lệ.');
        }
        $staff = $this->findAny($id);
        if (!$staff) {
            throw new \InvalidArgumentException('Không tìm thấy nhân viên.');
        }
        $actorRole = (string) ($actor['staff_role'] ?? '');
        if ($actorRole !== '' && !RolePolicy::canManageStaffRole($actorRole, (string) $staff['staff_role'], false)) {
            throw new \InvalidArgumentException('Bạn không có quyền khôi phục tài khoản này.');
        }

        $this->db->prepare("UPDATE staff SET status = 'active' WHERE id = :id")
            ->execute(['id' => $id]);

        return ['id' => $id, 'staff' => $this->allForAdmin()];
    }

    private function findAny(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT s.id, s.staff_code, s.staff_name, s.staff_role, s.phone_number, s.email, s.status,
                    s.branch_id, b.branch_name
             FROM staff s
             JOIN branches b ON b.id = s.branch_id
             WHERE s.id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $staff = $stmt->fetch();

        return $staff ?: null;
    }

    private function activeAdminCount(): int
    {
        return (int) $this->db
            ->query("SELECT COUNT(*) FROM staff WHERE status = 'active' AND staff_role IN ('owner', 'admin')")
            ->fetchColumn();
    }
}
