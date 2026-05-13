<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use InvalidArgumentException;

final class PosSession extends Model
{
    private const STALE_MINUTES = 30;

    public function login(array $data): array
    {
        $staffId = (int) ($data['staff_id'] ?? 0);
        if ($staffId <= 0) {
            throw new InvalidArgumentException('POS login requires staff_id.');
        }

        $staff = (new Staff())->find($staffId);
        if (!$staff) {
            throw new InvalidArgumentException('Staff account not found.');
        }

        $this->closeStaleSessions();
        $this->closeOpenSessionsForStaff($staffId);

        $branchId = max(1, (int) ($data['branch_id'] ?? $staff['branch_id'] ?? 1));
        $shiftId = $this->activeShiftId($staffId);
        $token = bin2hex(random_bytes(24));
        $openingCash = max(0, (float) ($data['opening_cash_amount'] ?? 0));
        $loginIp = substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64) ?: null;
        $userAgent = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255) ?: null;

        $stmt = $this->db->prepare(
            "INSERT INTO pos_sessions (
                branch_id, staff_id, shift_id, session_token, staff_role, opened_at, last_seen_at,
                login_ip, user_agent, opening_cash_amount, expected_cash_amount, status, notes
             ) VALUES (
                :branch_id, :staff_id, :shift_id, :session_token, :staff_role, NOW(), NOW(),
                :login_ip, :user_agent, :opening_cash_amount, :expected_cash_amount, 'open', :notes
             )"
        );
        $stmt->execute([
            'branch_id' => $branchId,
            'staff_id' => $staffId,
            'shift_id' => $shiftId,
            'session_token' => $token,
            'staff_role' => $staff['staff_role'],
            'login_ip' => $loginIp,
            'user_agent' => $userAgent,
            'opening_cash_amount' => $openingCash,
            'expected_cash_amount' => $openingCash,
            'notes' => trim((string) ($data['notes'] ?? '')),
        ]);

        $session = $this->findById((int) $this->db->lastInsertId());
        if (!$session) {
            throw new InvalidArgumentException('Could not create POS session.');
        }

        $this->logActivity($session, 'session_login', [
            'entity_type' => 'pos_session',
            'entity_id' => (int) $session['id'],
            'amount' => $openingCash,
            'note' => 'POS login',
        ]);

        return [
            'staff' => $this->staffPayload($session),
            'session' => $session,
        ];
    }

    public function current(array $data): array
    {
        $this->closeStaleSessions();

        try {
            $session = $this->requireOpen($data);
        } catch (InvalidArgumentException) {
            return ['current_session' => null];
        }

        return [
            'current_session' => $session,
            'staff' => $this->staffPayload($session),
        ];
    }

    public function heartbeat(array $data): array
    {
        $session = $this->requireOpen($data);
        $this->db->prepare("UPDATE pos_sessions SET last_seen_at = NOW() WHERE id = :id")
            ->execute(['id' => (int) $session['id']]);

        return [
            'current_session' => $this->findById((int) $session['id']),
        ];
    }

    public function logout(array $data): array
    {
        $session = $this->requireOpen($data);
        $expectedCash = $this->expectedCashAmount((int) $session['id'], (float) $session['opening_cash_amount']);
        $closingCash = isset($data['closing_cash_amount']) && $data['closing_cash_amount'] !== ''
            ? max(0, (float) $data['closing_cash_amount'])
            : $expectedCash;

        $this->db->prepare(
            "UPDATE pos_sessions
             SET status = 'closed',
                 closed_at = NOW(),
                 last_seen_at = NOW(),
                 closed_reason = 'manual',
                 expected_cash_amount = :expected_cash_amount,
                 closing_cash_amount = :closing_cash_amount,
                 cash_difference_amount = :cash_difference_amount
             WHERE id = :id AND status = 'open'"
        )->execute([
            'expected_cash_amount' => $expectedCash,
            'closing_cash_amount' => $closingCash,
            'cash_difference_amount' => $closingCash - $expectedCash,
            'id' => (int) $session['id'],
        ]);

        $closed = $this->findById((int) $session['id']);
        if ($closed) {
            $this->logActivity($closed, 'session_logout', [
                'entity_type' => 'pos_session',
                'entity_id' => (int) $closed['id'],
                'amount' => $closingCash,
                'note' => 'POS logout',
            ]);
        }

        return [
            'current_session' => $closed,
        ];
    }

    public function requireOpen(array $data, ?array $staff = null): array
    {
        $sessionId = (int) ($data['pos_session_id'] ?? 0);
        $token = trim((string) ($data['session_token'] ?? ''));
        if ($sessionId <= 0 || $token === '') {
            throw new InvalidArgumentException('POS request requires an active session.');
        }

        $stmt = $this->db->prepare(
            "SELECT ps.*, s.staff_name, s.phone_number, s.email, b.branch_name,
                    TIMESTAMPDIFF(MINUTE, COALESCE(ps.last_seen_at, ps.opened_at), NOW()) AS idle_minutes
             FROM pos_sessions ps
             JOIN staff s ON s.id = ps.staff_id
             JOIN branches b ON b.id = ps.branch_id
             WHERE ps.id = :id AND ps.session_token = :token
             LIMIT 1"
        );
        $stmt->execute(['id' => $sessionId, 'token' => $token]);
        $session = $stmt->fetch();
        if (!$session || $session['status'] !== 'open') {
            throw new InvalidArgumentException('POS session is not active.');
        }

        if ((int) $session['idle_minutes'] > self::STALE_MINUTES) {
            $this->closeSession((int) $session['id'], 'timeout', 'Session closed after inactivity.');
            throw new InvalidArgumentException('POS session expired after inactivity. Please login again.');
        }

        $expectedStaffId = (int) ($staff['id'] ?? $data['staff_id'] ?? 0);
        if ($expectedStaffId > 0 && (int) $session['staff_id'] !== $expectedStaffId) {
            throw new InvalidArgumentException('POS session does not match staff account.');
        }

        return $session;
    }

    public function logFromPayload(array $payload, string $actionType, array $context = []): void
    {
        if (empty($payload['pos_session_id']) || empty($payload['session_token'])) {
            return;
        }

        $session = $this->requireOpen($payload);
        $this->logActivity($session, $actionType, $context);
    }

    public function report(array $payload = []): array
    {
        $this->closeStaleSessions();

        $where = '';
        $params = [];

        $role = (string) ($payload['staff_role'] ?? '');
        $staffId = (int) ($payload['staff_id'] ?? 0);
        if ($staffId > 0 && !in_array($role, ['manager', 'owner', 'admin'], true)) {
            $where = 'WHERE ps.staff_id = :staff_id';
            $params['staff_id'] = $staffId;
        }

        $stmt = $this->db->prepare(
            "SELECT
                ps.id, ps.staff_id, ps.staff_role, ps.opened_at, ps.closed_at, ps.last_seen_at,
                ps.status, ps.closed_reason, ps.opening_cash_amount, ps.expected_cash_amount,
                ps.closing_cash_amount, ps.cash_difference_amount, ps.login_ip,
                s.staff_name, b.branch_name,
                TIMESTAMPDIFF(MINUTE, ps.opened_at, COALESCE(ps.closed_at, NOW())) AS duration_minutes,
                COALESCE(inv.invoice_count, 0) AS invoice_count,
                COALESCE(inv.revenue_total, 0) AS revenue_total,
                COALESCE(inv.cash_revenue, 0) AS cash_revenue,
                COALESCE(inv.card_revenue, 0) AS card_revenue,
                COALESCE(inv.ewallet_revenue, 0) AS ewallet_revenue,
                COALESCE(ord.order_count, 0) AS order_count,
                COALESCE(ord.order_items, 0) AS order_items,
                COALESCE(ord.order_value, 0) AS order_value,
                COALESCE(k.prepared_items, 0) AS prepared_items,
                COALESCE(k.prepared_quantity, 0) AS prepared_quantity,
                COALESCE(k.avg_prepare_minutes, 0) AS avg_prepare_minutes,
                COALESCE(cash.cash_in, 0) AS cash_in,
                COALESCE(cash.cash_out, 0) AS cash_out,
                COALESCE(act.activity_count, 0) AS activity_count,
                COALESCE(act.main_actions, '') AS main_actions
             FROM pos_sessions ps
             JOIN staff s ON s.id = ps.staff_id
             JOIN branches b ON b.id = ps.branch_id
             LEFT JOIN (
                SELECT pos_session_id,
                       COUNT(*) AS invoice_count,
                       SUM(total_amount) AS revenue_total,
                       SUM(CASE WHEN payment_method = 'cash' THEN total_amount ELSE 0 END) AS cash_revenue,
                       SUM(CASE WHEN payment_method = 'card' THEN total_amount ELSE 0 END) AS card_revenue,
                       SUM(CASE WHEN payment_method = 'e_wallet' THEN total_amount ELSE 0 END) AS ewallet_revenue
                FROM invoices
                WHERE status = 'paid' AND pos_session_id IS NOT NULL
                GROUP BY pos_session_id
             ) inv ON inv.pos_session_id = ps.id
             LEFT JOIN (
                SELECT pos_session_id,
                       SUM(CASE WHEN action_type = 'order_created' THEN 1 ELSE 0 END) AS order_count,
                       SUM(CASE WHEN action_type = 'order_created' THEN quantity ELSE 0 END) AS order_items,
                       SUM(CASE WHEN action_type = 'order_created' THEN amount ELSE 0 END) AS order_value
                FROM pos_activity_logs
                GROUP BY pos_session_id
             ) ord ON ord.pos_session_id = ps.id
             LEFT JOIN (
                SELECT prepared_by_session_id AS pos_session_id,
                       COUNT(*) AS prepared_items,
                       SUM(quantity) AS prepared_quantity,
                       AVG(TIMESTAMPDIFF(MINUTE, preparing_started_at, ready_at)) AS avg_prepare_minutes
                FROM service_order_items
                WHERE prepared_by_session_id IS NOT NULL AND ready_at IS NOT NULL
                GROUP BY prepared_by_session_id
             ) k ON k.pos_session_id = ps.id
             LEFT JOIN (
                SELECT pos_session_id,
                       SUM(CASE WHEN transaction_type = 'in' THEN amount ELSE 0 END) AS cash_in,
                       SUM(CASE WHEN transaction_type = 'out' THEN amount ELSE 0 END) AS cash_out
                FROM cash_transactions
                WHERE pos_session_id IS NOT NULL
                GROUP BY pos_session_id
             ) cash ON cash.pos_session_id = ps.id
             LEFT JOIN (
                SELECT pos_session_id,
                       COUNT(*) AS activity_count,
                       GROUP_CONCAT(CONCAT(action_type, ':', action_count) ORDER BY action_count DESC SEPARATOR ', ') AS main_actions
                FROM (
                    SELECT pos_session_id, action_type, COUNT(*) AS action_count
                    FROM pos_activity_logs
                    GROUP BY pos_session_id, action_type
                ) grouped_actions
                GROUP BY pos_session_id
             ) act ON act.pos_session_id = ps.id
             $where
             ORDER BY ps.opened_at DESC
             LIMIT 50"
        );
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private function logActivity(array $session, string $actionType, array $context): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO pos_activity_logs (
                pos_session_id, staff_id, staff_role, action_type, entity_type, entity_id,
                product_id, quantity, amount, status_from, status_to, note, created_at
             ) VALUES (
                :pos_session_id, :staff_id, :staff_role, :action_type, :entity_type, :entity_id,
                :product_id, :quantity, :amount, :status_from, :status_to, :note, NOW()
             )"
        );
        $stmt->execute([
            'pos_session_id' => (int) $session['id'],
            'staff_id' => (int) $session['staff_id'],
            'staff_role' => (string) $session['staff_role'],
            'action_type' => substr($actionType, 0, 60),
            'entity_type' => isset($context['entity_type']) ? substr((string) $context['entity_type'], 0, 60) : null,
            'entity_id' => isset($context['entity_id']) ? (int) $context['entity_id'] : null,
            'product_id' => isset($context['product_id']) ? (int) $context['product_id'] : null,
            'quantity' => max(0, (float) ($context['quantity'] ?? 0)),
            'amount' => max(0, (float) ($context['amount'] ?? 0)),
            'status_from' => isset($context['status_from']) ? substr((string) $context['status_from'], 0, 40) : null,
            'status_to' => isset($context['status_to']) ? substr((string) $context['status_to'], 0, 40) : null,
            'note' => isset($context['note']) ? substr((string) $context['note'], 0, 255) : null,
        ]);
    }

    private function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT ps.*, s.staff_name, s.phone_number, s.email, b.branch_name,
                    TIMESTAMPDIFF(MINUTE, ps.opened_at, COALESCE(ps.closed_at, NOW())) AS duration_minutes
             FROM pos_sessions ps
             JOIN staff s ON s.id = ps.staff_id
             JOIN branches b ON b.id = ps.branch_id
             WHERE ps.id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $id]);
        $session = $stmt->fetch();

        return $session ?: null;
    }

    private function staffPayload(array $session): array
    {
        return [
            'id' => (int) $session['staff_id'],
            'staff_name' => $session['staff_name'],
            'staff_role' => $session['staff_role'],
            'phone_number' => $session['phone_number'],
            'email' => $session['email'],
            'branch_id' => (int) $session['branch_id'],
            'branch_name' => $session['branch_name'],
            'pos_session_id' => (int) $session['id'],
            'session_token' => $session['session_token'],
            'session_opened_at' => $session['opened_at'],
            'session_last_seen_at' => $session['last_seen_at'],
        ];
    }

    private function activeShiftId(int $staffId): ?int
    {
        $stmt = $this->db->prepare(
            "SELECT id
             FROM staff_shifts
             WHERE staff_id = :staff_id
               AND status = 'active'
               AND (work_date IS NULL OR work_date = CURDATE())
               AND (
                    (starts_at <= ends_at AND CURTIME() BETWEEN starts_at AND ends_at)
                    OR (starts_at > ends_at AND (CURTIME() >= starts_at OR CURTIME() <= ends_at))
               )
             ORDER BY work_date DESC, starts_at DESC
             LIMIT 1"
        );
        $stmt->execute(['staff_id' => $staffId]);
        $shiftId = $stmt->fetchColumn();

        return $shiftId ? (int) $shiftId : null;
    }

    private function closeStaleSessions(): void
    {
        $this->db->prepare(
            "UPDATE pos_sessions
             SET status = 'closed',
                 closed_at = COALESCE(last_seen_at, opened_at),
                 closed_reason = 'timeout',
                 notes = TRIM(CONCAT(COALESCE(notes, ''), ' Auto-closed after inactivity.'))
             WHERE status = 'open'
               AND TIMESTAMPDIFF(MINUTE, COALESCE(last_seen_at, opened_at), NOW()) > :minutes"
        )->execute(['minutes' => self::STALE_MINUTES]);
    }

    private function closeOpenSessionsForStaff(int $staffId): void
    {
        $this->db->prepare(
            "UPDATE pos_sessions
             SET status = 'closed',
                 closed_at = NOW(),
                 last_seen_at = NOW(),
                 closed_reason = 'system',
                 notes = TRIM(CONCAT(COALESCE(notes, ''), ' Closed by new login.'))
             WHERE staff_id = :staff_id AND status = 'open'"
        )->execute(['staff_id' => $staffId]);
    }

    private function closeSession(int $sessionId, string $reason, string $note): void
    {
        $this->db->prepare(
            "UPDATE pos_sessions
             SET status = 'closed',
                 closed_at = NOW(),
                 last_seen_at = NOW(),
                 closed_reason = :reason,
                 notes = TRIM(CONCAT(COALESCE(notes, ''), ' ', :note))
             WHERE id = :id AND status = 'open'"
        )->execute(['reason' => $reason, 'note' => $note, 'id' => $sessionId]);
    }

    private function expectedCashAmount(int $sessionId, float $openingCash): float
    {
        $stmt = $this->db->prepare(
            "SELECT
                :opening_cash
                + COALESCE((SELECT SUM(total_amount) FROM invoices WHERE pos_session_id = :invoice_session_id AND status = 'paid' AND payment_method = 'cash'), 0)
                + COALESCE((SELECT SUM(amount) FROM cash_transactions WHERE pos_session_id = :cash_in_session_id AND transaction_type = 'in'), 0)
                - COALESCE((SELECT SUM(amount) FROM cash_transactions WHERE pos_session_id = :cash_out_session_id AND transaction_type = 'out'), 0)"
        );
        $stmt->execute([
            'opening_cash' => $openingCash,
            'invoice_session_id' => $sessionId,
            'cash_in_session_id' => $sessionId,
            'cash_out_session_id' => $sessionId,
        ]);

        return (float) $stmt->fetchColumn();
    }
}
