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
            'refund_summary' => $this->refundSummary($start, $end),
            'refund_history' => (new Invoice())->refundHistory(['limit' => 50]),
            'refundable_invoices' => (new Invoice())->refundableInvoices(['limit' => 50]),
            'session_reports' => (new PosSession())->report(),
        ];
    }

    public function cashData(array $filters = []): array
    {
        $branchId = max(0, (int) ($filters['branch_id'] ?? 0));

        return [
            'cash_transactions' => $this->cashTransactions(['branch_id' => $branchId, 'limit' => 100]),
            'refund_history' => (new Invoice())->refundHistory(['branch_id' => $branchId, 'limit' => 100]),
            'refundable_invoices' => (new Invoice())->refundableInvoices(['branch_id' => $branchId, 'limit' => 100]),
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
        foreach ($data['refund_history'] as $row) {
            fputcsv($handle, [
                'Refund history',
                '#' . $row['invoice_id'],
                $row['staff_name'],
                $row['refund_method'],
                $row['refund_amount'],
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
            "SELECT i.sales_channel,
                    SUM(CASE WHEN GREATEST(i.total_amount - COALESCE(r.refunded_amount, 0), 0) > 0 THEN 1 ELSE 0 END) AS paid_invoice_count,
                    COALESCE(SUM(i.total_amount), 0) AS gross_sales,
                    COALESCE(SUM(COALESCE(r.refunded_amount, 0)), 0) AS refund_amount,
                    COALESCE(SUM(GREATEST(i.total_amount - COALESCE(r.refunded_amount, 0), 0)), 0) AS net_revenue
             FROM invoices i
             LEFT JOIN (
                SELECT invoice_id, SUM(refund_amount) AS refunded_amount
                FROM invoice_refunds WHERE status = 'approved' GROUP BY invoice_id
             ) r ON r.invoice_id = i.id
             WHERE i.status IN ('paid', 'partially_refunded', 'refunded')
               AND i.invoice_date BETWEEN :start AND :end
             GROUP BY i.sales_channel
             ORDER BY net_revenue DESC"
        );
        $stmt->execute(['start' => $start, 'end' => $end]);
        return $stmt->fetchAll();
    }

    private function branchSummary(string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT b.id AS branch_id, b.branch_name,
                    SUM(CASE WHEN GREATEST(i.total_amount - COALESCE(r.refunded_amount, 0), 0) > 0 THEN 1 ELSE 0 END) AS paid_invoice_count,
                    COALESCE(SUM(GREATEST(i.total_amount - COALESCE(r.refunded_amount, 0), 0)), 0) AS net_revenue,
                    COALESCE(SUM(i.subtotal_amount), 0) AS gross_sales,
                    COALESCE(SUM(COALESCE(r.refunded_amount, 0)), 0) AS refund_amount,
                    COALESCE(SUM(i.membership_discount_amount), 0) AS membership_discount,
                    COALESCE(SUM(i.voucher_discount_amount), 0) AS voucher_discount,
                    COALESCE(AVG(GREATEST(i.total_amount - COALESCE(r.refunded_amount, 0), 0)), 0) AS average_invoice_value,
                    COALESCE(SUM(CASE WHEN i.sales_channel = 'pos' THEN GREATEST(i.total_amount - COALESCE(r.refunded_amount, 0), 0) ELSE 0 END), 0) AS pos_revenue,
                    COALESCE(SUM(CASE WHEN i.sales_channel IN ('website', 'delivery') THEN GREATEST(i.total_amount - COALESCE(r.refunded_amount, 0), 0) ELSE 0 END), 0) AS website_revenue
             FROM branches b
             LEFT JOIN invoices i ON i.branch_id = b.id
                AND i.status IN ('paid', 'partially_refunded', 'refunded')
                AND i.invoice_date BETWEEN :start AND :end
             LEFT JOIN (
                SELECT invoice_id, SUM(refund_amount) AS refunded_amount
                FROM invoice_refunds WHERE status = 'approved' GROUP BY invoice_id
             ) r ON r.invoice_id = i.id
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
                    SUM(CASE WHEN GREATEST(i.total_amount - COALESCE(r.refunded_amount, 0), 0) > 0 THEN 1 ELSE 0 END) AS paid_invoice_count,
                    COALESCE(SUM(i.total_amount), 0) AS gross_sales,
                    COALESCE(SUM(COALESCE(r.refunded_amount, 0)), 0) AS refund_amount,
                    COALESCE(SUM(GREATEST(i.total_amount - COALESCE(r.refunded_amount, 0), 0)), 0) AS net_revenue,
                    COALESCE(SUM(i.voucher_discount_amount), 0) AS voucher_discount,
                    COALESCE(AVG(GREATEST(i.total_amount - COALESCE(r.refunded_amount, 0), 0)), 0) AS average_invoice_value
             FROM invoices i
             JOIN branches b ON b.id = i.branch_id
             LEFT JOIN (
                SELECT invoice_id, SUM(refund_amount) AS refunded_amount
                FROM invoice_refunds WHERE status = 'approved' GROUP BY invoice_id
             ) r ON r.invoice_id = i.id
             WHERE i.status IN ('paid', 'partially_refunded', 'refunded')
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
                    SUM(CASE WHEN GREATEST(i.total_amount - COALESCE(r.refunded_amount, 0), 0) > 0 THEN 1 ELSE 0 END) AS paid_invoice_count,
                    COALESCE(SUM(i.total_amount), 0) AS gross_sales,
                    COALESCE(SUM(COALESCE(r.refunded_amount, 0)), 0) AS refund_amount,
                    COALESCE(SUM(GREATEST(i.total_amount - COALESCE(r.refunded_amount, 0), 0)), 0) AS net_revenue
             FROM invoices i
             JOIN branches b ON b.id = i.branch_id
             LEFT JOIN (
                SELECT invoice_id, SUM(refund_amount) AS refunded_amount
                FROM invoice_refunds WHERE status = 'approved' GROUP BY invoice_id
             ) r ON r.invoice_id = i.id
             WHERE i.status IN ('paid', 'partially_refunded', 'refunded')
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
                    SUM(CASE WHEN GREATEST(i.total_amount - COALESCE(r.refunded_amount, 0), 0) > 0 THEN 1 ELSE 0 END) AS paid_invoice_count,
                    COALESCE(SUM(i.total_amount), 0) AS gross_sales,
                    COALESCE(SUM(COALESCE(r.refunded_amount, 0)), 0) AS refund_amount,
                    COALESCE(SUM(GREATEST(i.total_amount - COALESCE(r.refunded_amount, 0), 0)), 0) AS net_revenue,
                    COALESCE(AVG(GREATEST(i.total_amount - COALESCE(r.refunded_amount, 0), 0)), 0) AS average_invoice_value
             FROM invoices i
             LEFT JOIN (
                SELECT invoice_id, SUM(refund_amount) AS refunded_amount
                FROM invoice_refunds WHERE status = 'approved' GROUP BY invoice_id
             ) r ON r.invoice_id = i.id
             WHERE i.status IN ('paid', 'partially_refunded', 'refunded')
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
            "SELECT s.staff_name, s.staff_role,
                    SUM(CASE WHEN GREATEST(i.total_amount - COALESCE(r.refunded_amount, 0), 0) > 0 THEN 1 ELSE 0 END) AS orders_processed,
                    COALESCE(SUM(i.total_amount), 0) AS gross_revenue_handled,
                    COALESCE(SUM(COALESCE(r.refunded_amount, 0)), 0) AS refund_amount,
                    COALESCE(SUM(GREATEST(i.total_amount - COALESCE(r.refunded_amount, 0), 0)), 0) AS revenue_handled
             FROM staff s
             LEFT JOIN invoices i ON i.staff_id = s.id
                AND i.status IN ('paid', 'partially_refunded', 'refunded')
                AND i.invoice_date BETWEEN :start AND :end
             LEFT JOIN (
                SELECT invoice_id, SUM(refund_amount) AS refunded_amount
                FROM invoice_refunds WHERE status = 'approved' GROUP BY invoice_id
             ) r ON r.invoice_id = i.id
             GROUP BY s.id, s.staff_name, s.staff_role
             ORDER BY revenue_handled DESC, orders_processed DESC"
        );
        $stmt->execute(['start' => $start, 'end' => $end]);
        return $stmt->fetchAll();
    }

    public function cashTransactions(array $filters = []): array
    {
        $where = [];
        $params = [];
        if ((int) ($filters['branch_id'] ?? 0) > 0) {
            $where[] = 'ct.branch_id = :branch_id';
            $params['branch_id'] = (int) $filters['branch_id'];
        }
        if ((int) ($filters['pos_session_id'] ?? 0) > 0) {
            $where[] = 'ct.pos_session_id = :pos_session_id';
            $params['pos_session_id'] = (int) $filters['pos_session_id'];
        }
        $limit = max(1, min(200, (int) ($filters['limit'] ?? 50)));
        $stmt = $this->db->prepare(
            "SELECT ct.id, ct.pos_session_id, ct.invoice_id, ct.invoice_refund_id,
                    ct.transaction_type, ct.reason, ct.amount, ct.created_at,
                    s.staff_name, b.branch_name
             FROM cash_transactions ct
             JOIN staff s ON s.id = ct.staff_id
             JOIN branches b ON b.id = ct.branch_id
             " . ($where ? 'WHERE ' . implode(' AND ', $where) : '') . "
             ORDER BY ct.created_at DESC, ct.id DESC
             LIMIT $limit"
        );
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function refundSummary(string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT b.id AS branch_id, b.branch_name, ir.refund_method,
                    COUNT(ir.id) AS refund_count,
                    COALESCE(SUM(ir.refund_amount), 0) AS refund_amount
             FROM invoice_refunds ir
             JOIN invoices i ON i.id = ir.invoice_id
             JOIN branches b ON b.id = i.branch_id
             WHERE ir.status = 'approved'
               AND DATE(ir.created_at) BETWEEN :start AND :end
             GROUP BY b.id, b.branch_name, ir.refund_method
             ORDER BY refund_amount DESC, b.branch_name"
        );
        $stmt->execute(['start' => $start, 'end' => $end]);
        return $stmt->fetchAll();
    }

    private function grossMargin(string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT grouped.sales_channel,
                    COUNT(*) AS invoice_count,
                    COALESCE(SUM(grouped.total_amount), 0) AS gross_sales,
                    COALESCE(SUM(grouped.refunded_amount), 0) AS refund_amount,
                    COALESCE(SUM(GREATEST(grouped.total_amount - grouped.refunded_amount, 0)), 0) AS net_revenue,
                    COALESCE(SUM(grouped.cogs_amount), 0) AS cogs_amount,
                    COALESCE(SUM(GREATEST(grouped.total_amount - grouped.refunded_amount, 0) - grouped.cogs_amount), 0) AS gross_margin_amount
             FROM (
                SELECT i.id, i.sales_channel, i.total_amount,
                       COALESCE(r.refunded_amount, 0) AS refunded_amount,
                       COALESCE(SUM(sm.total_amount), 0) AS cogs_amount
                FROM invoices i
                LEFT JOIN stock_movements sm
                    ON sm.note LIKE CONCAT('Auto consume for invoice #', i.id, ' -%')
                LEFT JOIN (
                    SELECT invoice_id, SUM(refund_amount) AS refunded_amount
                    FROM invoice_refunds WHERE status = 'approved' GROUP BY invoice_id
                ) r ON r.invoice_id = i.id
                WHERE i.status IN ('paid', 'partially_refunded', 'refunded')
                  AND i.invoice_date BETWEEN :start AND :end
                GROUP BY i.id, i.sales_channel, i.total_amount, r.refunded_amount
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
