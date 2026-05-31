<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\AppLogger;
use App\Core\Model;
use Throwable;

final class AuditLog extends Model
{
    public function record(array $data): void
    {
        try {
            $metadata = $data['metadata'] ?? [];
            $this->db->prepare(
                "INSERT INTO audit_logs (
                    actor_type, actor_id, actor_role, action, entity_type, entity_id,
                    ip_address, user_agent, metadata_json, created_at
                 ) VALUES (
                    :actor_type, :actor_id, :actor_role, :action, :entity_type, :entity_id,
                    :ip_address, :user_agent, :metadata_json, NOW()
                 )"
            )->execute([
                'actor_type' => in_array(($data['actor_type'] ?? 'system'), ['customer', 'staff', 'system', 'guest'], true)
                    ? $data['actor_type']
                    : 'system',
                'actor_id' => isset($data['actor_id']) ? (int) $data['actor_id'] : null,
                'actor_role' => isset($data['actor_role']) ? substr((string) $data['actor_role'], 0, 40) : null,
                'action' => substr((string) ($data['action'] ?? 'unknown'), 0, 80),
                'entity_type' => isset($data['entity_type']) ? substr((string) $data['entity_type'], 0, 80) : null,
                'entity_id' => isset($data['entity_id']) ? (int) $data['entity_id'] : null,
                'ip_address' => substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'), 0, 64),
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? 'cli'), 0, 255),
                'metadata_json' => json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
        } catch (Throwable $exception) {
            AppLogger::error($exception, ['audit_action' => $data['action'] ?? null]);
        }
    }
}
