<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use InvalidArgumentException;

final class Order extends Model
{
    public function tables(): array
    {
        return $this->db->query(
            "SELECT t.id, t.table_name, t.area_name, t.seat_count, t.status,
                    so.id AS active_order_id, so.order_code, so.status AS order_status
             FROM dining_tables t
             LEFT JOIN service_orders so ON so.table_id = t.id AND so.status IN ('draft', 'preparing', 'ready', 'served')
             ORDER BY t.area_name, t.table_name"
        )->fetchAll();
    }

    public function activeOrders(): array
    {
        $orders = $this->db->query(
            "SELECT so.id, so.order_code, so.table_id, so.customer_id, so.waiter_id, so.cashier_id,
                    so.branch_id, so.status, so.note, so.created_at,
                    t.table_name, b.branch_name,
                    COALESCE(c.customer_name, 'Khách lẻ') AS customer_name,
                    COALESCE(s.staff_name, 'Chưa gán') AS waiter_name,
                    SUM(soi.line_total) AS subtotal_amount
             FROM service_orders so
             JOIN dining_tables t ON t.id = so.table_id
             JOIN branches b ON b.id = so.branch_id
             LEFT JOIN customers c ON c.id = so.customer_id
             LEFT JOIN staff s ON s.id = so.waiter_id
             LEFT JOIN service_order_items soi ON soi.service_order_id = so.id
             WHERE so.status IN ('draft', 'preparing', 'ready', 'served')
             GROUP BY so.id, so.order_code, so.table_id, so.customer_id, so.waiter_id, so.cashier_id,
                      so.branch_id, so.status, so.note, so.created_at, t.table_name, b.branch_name,
                      c.customer_name, s.staff_name
             ORDER BY so.created_at DESC"
        )->fetchAll();

        foreach ($orders as &$order) {
            $order['items'] = $this->items((int) $order['id']);
        }

        return $orders;
    }

    public function items(int $orderId): array
    {
        $stmt = $this->db->prepare(
            "SELECT soi.id, soi.service_order_id, soi.product_id, soi.quantity, soi.unit_price,
                    soi.size, soi.topping, soi.note, soi.line_total, soi.kitchen_status,
                    p.product_name, p.category
             FROM service_order_items soi
             JOIN products p ON p.id = soi.product_id
             WHERE soi.service_order_id = :order_id
             ORDER BY soi.id"
        );
        $stmt->execute(['order_id' => $orderId]);

        return $stmt->fetchAll();
    }

    public function create(array $data): array
    {
        $items = $data['items'] ?? [];
        if (!is_array($items) || !$items) {
            throw new InvalidArgumentException('Order requires at least one item.');
        }

        $tableId = max(1, (int) ($data['table_id'] ?? 1));
        $branchId = max(1, (int) ($data['branch_id'] ?? 1));
        $waiterId = max(1, (int) ($data['waiter_id'] ?? $data['staff_id'] ?? 1));
        $customerId = isset($data['customer_id']) && $data['customer_id'] !== '' ? (int) $data['customer_id'] : null;
        $note = trim((string) ($data['note'] ?? ''));
        $totalQuantity = 0;
        $subtotal = 0.0;

        $productModel = new Product();
        $products = $productModel->byIds(array_map(static fn ($item) => (int) ($item['product_id'] ?? 0), $items));

        $this->db->beginTransaction();
        try {
            $orderCode = 'OD-' . date('His') . '-' . random_int(10, 99);
            $this->db->prepare(
                "INSERT INTO service_orders (order_code, branch_id, table_id, customer_id, waiter_id, status, note)
                 VALUES (:order_code, :branch_id, :table_id, :customer_id, :waiter_id, 'preparing', :note)"
            )->execute([
                'order_code' => $orderCode,
                'branch_id' => $branchId,
                'table_id' => $tableId,
                'customer_id' => $customerId,
                'waiter_id' => $waiterId,
                'note' => $note,
            ]);
            $orderId = (int) $this->db->lastInsertId();

            $itemStmt = $this->db->prepare(
                "INSERT INTO service_order_items (
                    service_order_id, product_id, quantity, unit_price, size, topping, note, line_total, kitchen_status
                 ) VALUES (
                    :order_id, :product_id, :quantity, :unit_price, :size, :topping, :note, :line_total, 'waiting'
                 )"
            );

            foreach ($items as $item) {
                $productId = (int) ($item['product_id'] ?? 0);
                $quantity = max(1, (int) ($item['quantity'] ?? 1));
                if (!isset($products[$productId])) {
                    throw new InvalidArgumentException('Invalid product in service order.');
                }
                $unitPrice = (float) $products[$productId]['price'];
                $lineTotal = $unitPrice * $quantity;
                $totalQuantity += $quantity;
                $subtotal += $lineTotal;
                $itemStmt->execute([
                    'order_id' => $orderId,
                    'product_id' => $productId,
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'size' => in_array(($item['size'] ?? 'M'), ['S', 'M', 'L'], true) ? $item['size'] : 'M',
                    'topping' => trim((string) ($item['topping'] ?? '')) ?: null,
                    'note' => trim((string) ($item['note'] ?? '')) ?: null,
                    'line_total' => $lineTotal,
                ]);
            }

            $this->db->prepare("UPDATE dining_tables SET status = 'occupied' WHERE id = :id")->execute(['id' => $tableId]);
            (new PosSession())->logFromPayload($data, 'order_created', [
                'entity_type' => 'service_order',
                'entity_id' => $orderId,
                'quantity' => $totalQuantity,
                'amount' => $subtotal,
                'status_to' => 'preparing',
                'note' => $orderCode,
            ]);
            $this->db->commit();

            return ['order_id' => $orderId, 'orders' => $this->activeOrders(), 'tables' => $this->tables()];
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function updateItemStatus(array $data): array
    {
        $itemId = (int) ($data['item_id'] ?? 0);
        $status = (string) ($data['status'] ?? 'waiting');
        if ($itemId <= 0 || !in_array($status, ['waiting', 'preparing', 'ready', 'served'], true)) {
            throw new InvalidArgumentException('Invalid item status update.');
        }

        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare(
                "SELECT soi.id, soi.service_order_id, soi.product_id, soi.quantity, soi.line_total,
                        soi.kitchen_status, p.product_name, so.order_code
                 FROM service_order_items soi
                 JOIN products p ON p.id = soi.product_id
                 JOIN service_orders so ON so.id = soi.service_order_id
                 WHERE soi.id = :id
                 FOR UPDATE"
            );
            $stmt->execute(['id' => $itemId]);
            $item = $stmt->fetch();
            if (!$item) {
                throw new InvalidArgumentException('Service order item not found.');
            }

            $set = ['kitchen_status = :status'];
            $params = ['status' => $status, 'id' => $itemId];
            $staffId = (int) ($data['staff_id'] ?? 0);
            $sessionId = (int) ($data['pos_session_id'] ?? 0);
            if ($status === 'preparing') {
                $set[] = 'preparing_started_at = COALESCE(preparing_started_at, NOW())';
                $set[] = 'prepared_by_staff_id = :prepared_staff_id';
                $set[] = 'prepared_by_session_id = :prepared_session_id';
                $params['prepared_staff_id'] = $staffId ?: null;
                $params['prepared_session_id'] = $sessionId ?: null;
            } elseif ($status === 'ready') {
                $set[] = 'preparing_started_at = COALESCE(preparing_started_at, created_at)';
                $set[] = 'ready_at = COALESCE(ready_at, NOW())';
                $set[] = 'prepared_by_staff_id = :prepared_staff_id';
                $set[] = 'prepared_by_session_id = :prepared_session_id';
                $params['prepared_staff_id'] = $staffId ?: null;
                $params['prepared_session_id'] = $sessionId ?: null;
            } elseif ($status === 'served') {
                $set[] = 'served_at = COALESCE(served_at, NOW())';
            }

            $this->db->prepare(
                "UPDATE service_order_items SET " . implode(', ', $set) . " WHERE id = :id"
            )->execute($params);

            $orderId = (int) $item['service_order_id'];
            $this->syncOrderStatus($orderId);
            (new PosSession())->logFromPayload($data, 'kitchen_' . $status, [
                'entity_type' => 'service_order_item',
                'entity_id' => $itemId,
                'product_id' => (int) $item['product_id'],
                'quantity' => (int) $item['quantity'],
                'amount' => (float) $item['line_total'],
                'status_from' => (string) $item['kitchen_status'],
                'status_to' => $status,
                'note' => $item['order_code'] . ' - ' . $item['product_name'],
            ]);
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }

        return ['orders' => $this->activeOrders(), 'kitchen' => $this->kitchenQueue(), 'tables' => $this->tables()];
    }

    public function kitchenQueue(): array
    {
        return $this->db->query(
            "SELECT soi.id, soi.service_order_id, soi.product_id, soi.quantity, soi.size,
                    soi.topping, soi.note, soi.kitchen_status, p.product_name,
                    so.order_code, so.created_at, t.table_name, b.branch_name
             FROM service_order_items soi
             JOIN service_orders so ON so.id = soi.service_order_id
             JOIN products p ON p.id = soi.product_id
             JOIN dining_tables t ON t.id = so.table_id
             JOIN branches b ON b.id = so.branch_id
             WHERE so.status IN ('preparing', 'ready') AND soi.kitchen_status IN ('waiting', 'preparing', 'ready')
             ORDER BY FIELD(soi.kitchen_status, 'waiting', 'preparing', 'ready'), so.created_at, soi.id"
        )->fetchAll();
    }

    public function find(int $orderId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT so.*, t.table_name
             FROM service_orders so
             JOIN dining_tables t ON t.id = so.table_id
             WHERE so.id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            return null;
        }
        $order['items'] = $this->items($orderId);
        return $order;
    }

    public function markPaid(int $orderId, int $cashierId): void
    {
        $order = $this->find($orderId);
        if ($order) {
            $this->db->prepare(
                "UPDATE service_orders SET status = 'paid', cashier_id = :cashier_id WHERE id = :id"
            )->execute(['cashier_id' => $cashierId, 'id' => $orderId]);
            $this->db->prepare("UPDATE dining_tables SET status = 'available' WHERE id = :id")->execute(['id' => $order['table_id']]);
        }
    }

    private function syncOrderStatus(int $orderId): void
    {
        $stmt = $this->db->prepare(
            "SELECT
                SUM(kitchen_status = 'waiting') AS waiting_count,
                SUM(kitchen_status = 'preparing') AS preparing_count,
                SUM(kitchen_status = 'ready') AS ready_count,
                SUM(kitchen_status = 'served') AS served_count,
                COUNT(*) AS total_count
             FROM service_order_items
             WHERE service_order_id = :order_id"
        );
        $stmt->execute(['order_id' => $orderId]);
        $row = $stmt->fetch();

        $status = 'preparing';
        if ((int) $row['served_count'] === (int) $row['total_count']) {
            $status = 'served';
        } elseif ((int) $row['ready_count'] + (int) $row['served_count'] === (int) $row['total_count']) {
            $status = 'ready';
        }

        $this->db->prepare("UPDATE service_orders SET status = :status WHERE id = :id")
            ->execute(['status' => $status, 'id' => $orderId]);
    }
}
