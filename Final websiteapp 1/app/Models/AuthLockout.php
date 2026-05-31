<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use InvalidArgumentException;

final class AuthLockout extends Model
{
    public function assertAllowed(string $scope, string $identity): void
    {
        $key = $this->key($scope, $identity);
        $stmt = $this->db->prepare(
            "SELECT failed_attempts, locked_until
             FROM auth_lockouts
             WHERE scope = :scope AND identity_hash = :identity_hash
             LIMIT 1"
        );
        $stmt->execute(['scope' => $scope, 'identity_hash' => $key['hash']]);
        $row = $stmt->fetch();

        if ($row && $row['locked_until'] !== null && strtotime((string) $row['locked_until']) > time()) {
            throw new InvalidArgumentException('Tài khoản bị khóa tạm thời do nhập sai nhiều lần. Vui lòng thử lại sau 15 phút.');
        }
    }

    public function recordFailure(string $scope, string $identity, int $maxAttempts = 5, int $lockMinutes = 15): void
    {
        $key = $this->key($scope, $identity);
        $stmt = $this->db->prepare(
            "SELECT id, failed_attempts
             FROM auth_lockouts
             WHERE scope = :scope AND identity_hash = :identity_hash
             LIMIT 1"
        );
        $stmt->execute(['scope' => $scope, 'identity_hash' => $key['hash']]);
        $row = $stmt->fetch();

        $failedAttempts = ($row ? (int) $row['failed_attempts'] : 0) + 1;
        $lockedUntil = $failedAttempts >= $maxAttempts
            ? date('Y-m-d H:i:s', time() + ($lockMinutes * 60))
            : null;

        if ($row) {
            $this->db->prepare(
                "UPDATE auth_lockouts
                 SET failed_attempts = :failed_attempts,
                     locked_until = :locked_until,
                     last_failed_at = NOW(),
                     ip_address = :ip_address,
                     user_agent = :user_agent,
                     updated_at = NOW()
                 WHERE id = :id"
            )->execute([
                'failed_attempts' => $failedAttempts,
                'locked_until' => $lockedUntil,
                'ip_address' => $key['ip'],
                'user_agent' => $key['user_agent'],
                'id' => (int) $row['id'],
            ]);
            return;
        }

        $this->db->prepare(
            "INSERT INTO auth_lockouts (
                scope, identity_hash, identity_label, ip_address, user_agent,
                failed_attempts, locked_until, last_failed_at
             ) VALUES (
                :scope, :identity_hash, :identity_label, :ip_address, :user_agent,
                :failed_attempts, :locked_until, NOW()
             )"
        )->execute([
            'scope' => $scope,
            'identity_hash' => $key['hash'],
            'identity_label' => $key['label'],
            'ip_address' => $key['ip'],
            'user_agent' => $key['user_agent'],
            'failed_attempts' => $failedAttempts,
            'locked_until' => $lockedUntil,
        ]);
    }

    public function clear(string $scope, string $identity): void
    {
        $key = $this->key($scope, $identity);
        $this->db->prepare(
            "DELETE FROM auth_lockouts
             WHERE scope = :scope AND identity_hash = :identity_hash"
        )->execute(['scope' => $scope, 'identity_hash' => $key['hash']]);
    }

    private function key(string $scope, string $identity): array
    {
        $normalized = strtolower(trim($identity));
        $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64) ?: 'cli';
        $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'cli'), 0, 255);

        return [
            'hash' => hash('sha256', $scope . '|' . $normalized . '|' . $ip),
            'label' => substr($normalized, 0, 160),
            'ip' => $ip,
            'user_agent' => $userAgent,
        ];
    }
}
