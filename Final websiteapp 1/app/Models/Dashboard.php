<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Dashboard extends Model
{
    public function data(): array
    {
        $businessDate = (string) ($this->db->query("SELECT COALESCE(MAX(invoice_date), CURDATE()) FROM invoices")->fetchColumn() ?: today_sql());
        $monthStart = substr($businessDate, 0, 7) . '-01';

        $summary = $this->row(
            "SELECT COUNT(*) AS orders, COALESCE(SUM(total_amount), 0) AS revenue,
                    COALESCE(SUM(points_earned), 0) AS points,
                    SUM(voucher_id IS NOT NULL) AS voucher_orders
             FROM invoices
             WHERE status = 'paid' AND invoice_date = :business_date",
            ['business_date' => $businessDate]
        );

        $month = $this->row(
            "SELECT COUNT(*) AS orders, COALESCE(SUM(total_amount), 0) AS revenue,
                    COUNT(DISTINCT customer_id) AS customers
             FROM invoices
             WHERE status = 'paid' AND invoice_date BETWEEN :start AND :end",
            ['start' => $monthStart, 'end' => $businessDate]
        );

        return [
            'business_date' => $businessDate,
            'summary' => $summary,
            'month' => $month,
            'top_products' => $this->topProducts($monthStart, $businessDate),
            'low_inventory' => array_slice((new Inventory())->productInventory(), 0, 8),
            'branch_revenue' => $this->branchRevenue($monthStart, $businessDate),
            'campaigns' => (new Campaign())->performance(),
            'recent_invoices' => $this->recentInvoices(),
        ];
    }

    private function row(string $sql, array $params): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch() ?: [];
    }

    private function topProducts(string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT p.product_name, p.category, SUM(idt.quantity) AS quantity_sold,
                    SUM(idt.line_total) AS product_revenue
             FROM invoice_details idt
             JOIN invoices i ON i.id = idt.invoice_id
             JOIN products p ON p.id = idt.product_id
             WHERE i.status = 'paid' AND i.invoice_date BETWEEN :start AND :end
             GROUP BY p.id, p.product_name, p.category
             ORDER BY quantity_sold DESC, product_revenue DESC
             LIMIT 6"
        );
        $stmt->execute(['start' => $start, 'end' => $end]);
        return $stmt->fetchAll();
    }

    private function branchRevenue(string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT b.branch_name, COUNT(i.id) AS paid_invoice_count,
                    COALESCE(SUM(i.total_amount), 0) AS net_revenue
             FROM branches b
             LEFT JOIN invoices i ON i.branch_id = b.id
                AND i.status = 'paid'
                AND i.invoice_date BETWEEN :start AND :end
             GROUP BY b.id, b.branch_name
             ORDER BY net_revenue DESC"
        );
        $stmt->execute(['start' => $start, 'end' => $end]);
        return $stmt->fetchAll();
    }

    private function recentInvoices(): array
    {
        return $this->db->query(
            "SELECT i.id, i.invoice_date, i.invoice_time, i.bill_started_at, i.paid_at, i.sales_channel, i.total_amount,
                    i.payment_method, COALESCE(c.customer_name, 'Guest') AS customer_name,
                    b.branch_name
             FROM invoices i
             JOIN branches b ON b.id = i.branch_id
             LEFT JOIN customers c ON c.id = i.customer_id
             WHERE i.status = 'paid'
             ORDER BY i.invoice_date DESC, i.invoice_time DESC, i.id DESC
             LIMIT 8"
        )->fetchAll();
    }
}
