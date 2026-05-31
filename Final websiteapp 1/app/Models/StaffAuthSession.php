<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\RateLimiter;
use App\Core\Model;
use InvalidArgumentException;

final class StaffAuthSession extends Model
{
    private const STALE_MINUTES = 720;

    public function login(array $data): array
    {
        $identity = require_field($data, 'identity', 'Staff code, email or phone');
        $password = require_field($data, 'password', 'Password');
        $limitIdentity = RateLimiter::identity('pos-auth-login|' . $identity);
        $lockout = new AuthLockout();
        $lockout->assertAllowed('pos-auth-login', $identity);
        RateLimiter::hit('pos-auth-login', $limitIdentity, 8, 15 * 60);

        $staffModel = new Staff();
        $staff = $staffModel->verifyPassword($identity, $password);
        if (!$staff) {
            $lockout->recordFailure('pos-auth-login', $identity);
            throw new InvalidArgumentException('Tai khoan POS hoac mat khau khong dung.');
        }

        $this->closeStaleSessions();
        $this->closeActiveSessionsForStaff((int) $staff['id']);

        $token = bin2hex(random_bytes(24));
        $loginIp = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64) ?: null;
        $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null;

        $stmt = $this->db->prepare(
            "INSERT INTO staff_login_sessions (
                staff_id, auth_token, logged_in_at, last_seen_at, status, login_ip, user_agent
             ) VALUES (
                :staff_id, :auth_token, NOW(), NOW(), 'active', :login_ip, :user_agent
             )"
        );
        $stmt->execute([
            'staff_id' => (int) $staff['id'],
            'auth_token' => $token,
            'login_ip' => $loginIp,
            'user_agent' => $userAgent,
        ]);

        $staffModel->touchLogin((int) $staff['id']);
        $session = $this->findByToken($token);
        if (!$session) {
            throw new InvalidArgumentException('Khong the tao phien dang nhap POS.');
        }

        $lockout->clear('pos-auth-login', $identity);
        RateLimiter::clear('pos-auth-login', $limitIdentity);
        (new AuditLog())->record([
            'actor_type' => 'staff',
            'actor_id' => (int) $staff['id'],
            'actor_role' => (string) $staff['staff_role'],
            'action' => 'staff_auth_login',
            'entity_type' => 'staff_login_session',
            'entity_id' => (int) $session['id'],
        ]);

        return $this->payload($session);
    }

    public function current(array $data): array
    {
        $this->closeStaleSessions();

        try {
            $session = $this->requireActive($data);
        } catch (InvalidArgumentException) {
            return ['auth_session' => null, 'staff' => null];
        }

        return $this->payload($session);
    }

    public function heartbeat(array $data): array
    {
        $session = $this->requireActive($data);
        $this->db->prepare("UPDATE staff_login_sessions SET last_seen_at = NOW() WHERE id = :id")
            ->execute(['id' => (int) $session['id']]);

        return $this->payload($this->findById((int) $session['id']) ?: $session);
    }

    public function logout(array $data): array
    {
        $session = $this->requireActive($data);
        $this->db->prepare(
            "UPDATE staff_login_sessions
             SET status = 'logged_out', logged_out_at = NOW(), last_seen_at = NOW()
             WHERE id = :id AND status = 'active'"
        )->execute(['id' => (int) $session['id']]);

        (new AuditLog())->record([
            'actor_type' => 'staff',
            'actor_id' => (int) $session['staff_id'],
            'actor_role' => (string) $session['staff_role'],
            'action' => 'staff_auth_logout',
            'entity_type' => 'staff_login_session',
            'entity_id' => (int) $session['id'],
        ]);

        return [
            'auth_session' => $this->findById((int) $session['id']),
        ];
    }

    public function requireActive(array $data, ?int $expectedStaffId = null): array
    {
        $sessionId = (int) ($data['auth_session_id'] ?? $data['staff_login_session_id'] ?? 0);
        $token = trim((string) ($data['auth_token'] ?? ''));
        if ($sessionId <= 0 || $token === '') {
            throw new InvalidArgumentException('POS login requires an active staff auth session.');
        }

        $stmt = $this->db->prepare(
            "SELECT sls.*, s.staff_code, s.staff_name, s.staff_role, s.phone_number, s.email,
                    s.branch_id, b.branch_name,
                    TIMESTAMPDIFF(MINUTE, COALESCE(sls.last_seen_at, sls.logged_in_at), NOW()) AS idle_minutes
             FROM staff_login_sessions sls
             JOIN staff s ON s.id = sls.staff_id
             JOIN branches b ON b.id = s.branch_id
             WHERE sls.id = :id AND sls.auth_token = :token
             LIMIT 1"
        );
        $stmt->execute(['id' => $sessionId, 'token' => $token]);
        $session = $stmt->fetch();
        if (!$session || $session['status'] !== 'active') {
            throw new InvalidArgumentException('POS staff auth session is not active.');
        }

        if ((int) $session['idle_minutes'] > self::STALE_MINUTES) {
            $this->expireSession((int) $session['id']);
            throw new InvalidArgumentException('POS staff auth session expired. Please login again.');
        }

        if ($expectedStaffId !== null && (int) $session['staff_id'] !== $expectedStaffId) {
            throw new InvalidArgumentException('POS staff auth session does not match staff account.');
        }

        return $session;
    }

    private function payload(array $session): array
    {
        return [
            'staff' => [
                'id' => (int) $session['staff_id'],
                'staff_code' => $session['staff_code'],
                'staff_name' => $session['staff_name'],
                'staff_role' => $session['staff_role'],
                'phone_number' => $session['phone_number'],
                'email' => $session['email'],
                'branch_id' => (int) $session['branch_id'],
                'branch_name' => $session['branch_name'],
            ],
            'auth_session' => [
                'id' => (int) $session['id'],
                'auth_token' => $session['auth_token'],
                'logged_in_at' => $session['logged_in_at'],
                'last_seen_at' => $session['last_seen_at'],
                'logged_out_at' => $session['logged_out_at'],
                'status' => $session['status'],
            ],
        ];
    }

    private function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT sls.*, s.staff_code, s.staff_name, s.staff_role, s.phone_number, s.email,
                    s.branch_id, b.branch_name
             FROM staff_login_sessions sls
             JOIN staff s ON s.id = sls.staff_id
             JOIN branches b ON b.id = s.branch_id
             WHERE sls.id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $session = $stmt->fetch();

        return $session ?: null;
    }

    private function findByToken(string $token): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT sls.*, s.staff_code, s.staff_name, s.staff_role, s.phone_number, s.email,
                    s.branch_id, b.branch_name
             FROM staff_login_sessions sls
             JOIN staff s ON s.id = sls.staff_id
             JOIN branches b ON b.id = s.branch_id
             WHERE sls.auth_token = :token
             LIMIT 1"
        );
        $stmt->execute(['token' => $token]);
        $session = $stmt->fetch();

        return $session ?: null;
    }

    private function closeActiveSessionsForStaff(int $staffId): void
    {
        $this->db->prepare(
            "UPDATE staff_login_sessions
             SET status = 'logged_out', logged_out_at = NOW(), last_seen_at = NOW()
             WHERE staff_id = :staff_id AND status = 'active'"
        )->execute(['staff_id' => $staffId]);
    }

    private function closeStaleSessions(): void
    {
        $this->db->prepare(
            "UPDATE staff_login_sessions
             SET status = 'expired',
                 logged_out_at = COALESCE(last_seen_at, logged_in_at)
             WHERE status = 'active'
               AND TIMESTAMPDIFF(MINUTE, COALESCE(last_seen_at, logged_in_at), NOW()) > :minutes"
        )->execute(['minutes' => self::STALE_MINUTES]);
    }

    private function expireSession(int $sessionId): void
    {
        $this->db->prepare(
            "UPDATE staff_login_sessions
             SET status = 'expired', logged_out_at = NOW(), last_seen_at = NOW()
             WHERE id = :id AND status = 'active'"
        )->execute(['id' => $sessionId]);
    }
}
