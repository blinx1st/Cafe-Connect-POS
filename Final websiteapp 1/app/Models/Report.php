<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Report extends Model
{
    public function data(array $filters = []): array
    {
        $start = $this->dateOrDefault((string) ($filters['start_date'] ?? ''), date('Y-m-01', strtotime('-11 months')));
        $end = $this->dateOrDefault((string) ($filters['end_date'] ?? ''), date('Y-m-d'));

        return [
            'period' => ['start_date' => $start, 'end_date' => $end],
            'revenue_by_channel' => $this->revenueByChannel($start, $end),
            'branch_summary' => $this->branchSummary($start, $end),
            'branch_monthly_revenue' => $this->branchMonthlyRevenue($start, $end),
            'payment_by_branch' => $this->paymentByBranch($start, $end),
            'top_products_by_branch' => $this->topProductsByBranch($start, $end),
            'hourly_revenue' => $this->hourlyRevenue($start, $end),
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
        foreach ($data['branch_monthly_revenue'] as $row) {
            fputcsv($handle, [
                'Monthly revenue by branch',
                $row['revenue_month'],
                $row['branch_name'],
                $row['paid_invoice_count'],
                $row['net_revenue'],
            ]);
        }
        foreach ($data['branch_summary'] as $row) {
            fputcsv($handle, [
                'Branch summary',
                $row['branch_name'],
                $row['paid_invoice_count'],
                $row['net_revenue'],
                $row['average_invoice_value'],
            ]);
        }
        foreach ($data['payment_by_branch'] as $row) {
            fputcsv($handle, [
                'Payment by branch',
                $row['branch_name'],
                $row['payment_method'],
                $row['paid_invoice_count'],
                $row['net_revenue'],
            ]);
        }
        foreach ($data['top_products_by_branch'] as $row) {
            fputcsv($handle, [
                'Top products by branch',
                $row['branch_name'],
                $row['product_name'],
                $row['quantity_sold'],
                $row['product_revenue'],
            ]);
        }
        foreach ($data['hourly_revenue'] as $row) {
            fputcsv($handle, [
                'Hourly revenue',
                str_pad((string) $row['business_hour'], 2, '0', STR_PAD_LEFT) . ':00',
                $row['paid_invoice_count'],
                $row['net_revenue'],
                $row['average_invoice_value'],
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

    private function branchSummary(string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT b.id AS branch_id, b.branch_name,
                    COUNT(i.id) AS paid_invoice_count,
                    COALESCE(SUM(i.total_amount), 0) AS net_revenue,
                    COALESCE(SUM(i.subtotal_amount), 0) AS gross_sales,
                    COALESCE(SUM(i.membership_discount_amount), 0) AS membership_discount,
                    COALESCE(SUM(i.voucher_discount_amount), 0) AS voucher_discount,
                    COALESCE(AVG(i.total_amount), 0) AS average_invoice_value,
                    COALESCE(SUM(CASE WHEN i.sales_channel = 'pos' THEN i.total_amount ELSE 0 END), 0) AS pos_revenue,
                    COALESCE(SUM(CASE WHEN i.sales_channel IN ('website', 'delivery') THEN i.total_amount ELSE 0 END), 0) AS website_revenue
             FROM branches b
             LEFT JOIN invoices i ON i.branch_id = b.id
                AND i.status = 'paid'
                AND i.invoice_date BETWEEN :start AND :end
             WHERE b.status = 'active'
             GROUP BY b.id, b.branch_name
             ORDER BY net_revenue DESC, b.branch_name"
        );
        $stmt->execute(['start' => $start, 'end' => $end]);
        return $stmt->fetchAll();
    }

    private function branchMonthlyRevenue(string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT DATE_FORMAT(i.invoice_date, '%Y-%m') AS revenue_month,
                    b.id AS branch_id,
                    b.branch_name,
                    COUNT(i.id) AS paid_invoice_count,
                    COALESCE(SUM(i.total_amount), 0) AS net_revenue,
                    COALESCE(SUM(i.voucher_discount_amount), 0) AS voucher_discount,
                    COALESCE(AVG(i.total_amount), 0) AS average_invoice_value
             FROM invoices i
             JOIN branches b ON b.id = i.branch_id
             WHERE i.status = 'paid'
               AND i.invoice_date BETWEEN :start AND :end
             GROUP BY revenue_month, b.id, b.branch_name
             ORDER BY revenue_month DESC, net_revenue DESC, b.branch_name"
        );
        $stmt->execute(['start' => $start, 'end' => $end]);
        return $stmt->fetchAll();
    }

    private function paymentByBranch(string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT b.branch_name, i.payment_method,
                    COUNT(i.id) AS paid_invoice_count,
                    COALESCE(SUM(i.total_amount), 0) AS net_revenue
             FROM invoices i
             JOIN branches b ON b.id = i.branch_id
             WHERE i.status = 'paid'
               AND i.invoice_date BETWEEN :start AND :end
             GROUP BY b.id, b.branch_name, i.payment_method
             ORDER BY b.branch_name, net_revenue DESC"
        );
        $stmt->execute(['start' => $start, 'end' => $end]);
        return $stmt->fetchAll();
    }

    private function topProductsByBranch(string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT b.branch_name, p.product_name,
                    COALESCE(SUM(idt.quantity), 0) AS quantity_sold,
                    COALESCE(SUM(idt.line_total), 0) AS product_revenue
             FROM invoice_details idt
             JOIN invoices i ON i.id = idt.invoice_id
             JOIN branches b ON b.id = i.branch_id
             JOIN products p ON p.id = idt.product_id
             WHERE i.status = 'paid'
               AND i.invoice_date BETWEEN :start AND :end
             GROUP BY b.id, b.branch_name, p.id, p.product_name
             ORDER BY product_revenue DESC, quantity_sold DESC
             LIMIT 20"
        );
        $stmt->execute(['start' => $start, 'end' => $end]);
        return $stmt->fetchAll();
    }

    private function hourlyRevenue(string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT HOUR(i.invoice_time) AS business_hour,
                    COUNT(i.id) AS paid_invoice_count,
                    COALESCE(SUM(i.total_amount), 0) AS net_revenue,
                    COALESCE(AVG(i.total_amount), 0) AS average_invoice_value
             FROM invoices i
             WHERE i.status = 'paid'
               AND i.invoice_date BETWEEN :start AND :end
             GROUP BY business_hour
             ORDER BY business_hour"
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
