<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Report extends Model
{
    public function data(): array
    {
        $start = date('Y-m-01');
        $end = date('Y-m-d');

        return [
            'revenue_by_channel' => $this->revenueByChannel($start, $end),
            'staff_performance' => $this->staffPerformance($start, $end),
            'cash_transactions' => $this->cashTransactions(),
        ];
    }

    private function revenueByChannel(string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT sales_channel, COUNT(*) AS paid_invoice_count, COALESCE(SUM(total_amount), 0) AS net_revenue
             FROM invoices
             WHERE status = 'paid' AND invoice_date BETWEEN :start AND :end
             GROUP BY sales_channel
             ORDER BY net_revenue DESC"
        );
        $stmt->execute(['start' => $start, 'end' => $end]);
        return $stmt->fetchAll();
    }

    private function staffPerformance(string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.staff_name, s.staff_role, COUNT(i.id) AS orders_processed,
                    COALESCE(SUM(i.total_amount), 0) AS revenue_handled
             FROM staff s
             LEFT JOIN invoices i ON i.staff_id = s.id
                AND i.status = 'paid'
                AND i.invoice_date BETWEEN :start AND :end
             GROUP BY s.id, s.staff_name, s.staff_role
             ORDER BY revenue_handled DESC, orders_processed DESC"
        );
        $stmt->execute(['start' => $start, 'end' => $end]);
        return $stmt->fetchAll();
    }

    private function cashTransactions(): array
    {
        return $this->db->query(
            "SELECT ct.id, ct.transaction_type, ct.reason, ct.amount, ct.created_at,
                    s.staff_name, b.branch_name
             FROM cash_transactions ct
             JOIN staff s ON s.id = ct.staff_id
             JOIN branches b ON b.id = ct.branch_id
             ORDER BY ct.created_at DESC
             LIMIT 12"
        )->fetchAll();
    }
}
