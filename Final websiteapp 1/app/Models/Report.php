<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Report extends Model
{
    public function data(array $filters = []): array
    {
        $start = $this->dateOrDefault((string) ($filters['start_date'] ?? ''), date('Y-m-01'));
        $end = $this->dateOrDefault((string) ($filters['end_date'] ?? ''), date('Y-m-d'));

        return [
            'revenue_by_channel' => $this->revenueByChannel($start, $end),
            'staff_performance' => $this->staffPerformance($start, $end),
            'gross_margin' => $this->grossMargin($start, $end),
            'cash_transactions' => $this->cashTransactions(),
            'session_reports' => (new PosSession())->report(),
        ];
    }

    public function exportCsv(array $filters = []): array
    {
        $data = $this->data($filters);
        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Report', 'Metric 1', 'Metric 2', 'Metric 3', 'Metric 4']);
        foreach ($data['revenue_by_channel'] as $row) {
            fputcsv($handle, [
                'Revenue by channel',
                $row['sales_channel'],
                $row['paid_invoice_count'],
                $row['net_revenue'],
                '',
            ]);
        }
        foreach ($data['gross_margin'] as $row) {
            fputcsv($handle, [
                'Gross margin',
                $row['sales_channel'],
                $row['net_revenue'],
                $row['cogs_amount'],
                $row['gross_margin_amount'],
            ]);
        }
        foreach ($data['staff_performance'] as $row) {
            fputcsv($handle, [
                'Staff performance',
                $row['staff_name'],
                $row['staff_role'],
                $row['orders_processed'],
                $row['revenue_handled'],
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return [
            'filename' => 'cafe-connect-report-' . date('Ymd-His') . '.csv',
            'content_type' => 'text/csv',
            'csv' => $csv,
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
            "SELECT ct.id, ct.pos_session_id, ct.transaction_type, ct.reason, ct.amount, ct.created_at,
                    s.staff_name, b.branch_name
             FROM cash_transactions ct
             JOIN staff s ON s.id = ct.staff_id
             JOIN branches b ON b.id = ct.branch_id
             ORDER BY ct.created_at DESC
             LIMIT 12"
        )->fetchAll();
    }

    private function grossMargin(string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT grouped.sales_channel,
                    COUNT(*) AS invoice_count,
                    COALESCE(SUM(grouped.total_amount), 0) AS net_revenue,
                    COALESCE(SUM(grouped.cogs_amount), 0) AS cogs_amount,
                    COALESCE(SUM(grouped.total_amount - grouped.cogs_amount), 0) AS gross_margin_amount
             FROM (
                SELECT i.id, i.sales_channel, i.total_amount,
                       COALESCE(SUM(sm.total_amount), 0) AS cogs_amount
                FROM invoices i
                LEFT JOIN stock_movements sm
                    ON sm.note LIKE CONCAT('Auto consume for invoice #', i.id, ' -%')
                WHERE i.status = 'paid' AND i.invoice_date BETWEEN :start AND :end
                GROUP BY i.id, i.sales_channel, i.total_amount
             ) grouped
             GROUP BY grouped.sales_channel
             ORDER BY gross_margin_amount DESC"
        );
        $stmt->execute(['start' => $start, 'end' => $end]);
        return $stmt->fetchAll();
    }

    private function dateOrDefault(string $value, string $default): string
    {
        $timestamp = trim($value) !== '' ? strtotime($value) : false;
        return $timestamp === false ? $default : date('Y-m-d', $timestamp);
    }
}
