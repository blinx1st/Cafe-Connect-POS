<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Inventory extends Model
{
    public function overview(): array
    {
        return [
            'product_inventory' => $this->productInventory(),
            'materials' => $this->materials(),
            'movements' => $this->movements(),
            'recipes' => $this->recipes(),
        ];
    }

    public function productInventory(): array
    {
        return $this->db->query(
            "SELECT b.branch_name, p.product_name, bi.stock_quantity, bi.min_stock_level, bi.last_updated,
                    CASE WHEN bi.stock_quantity < bi.min_stock_level THEN 'low' ELSE 'ok' END AS stock_status
             FROM branch_inventory bi
             JOIN branches b ON b.id = bi.branch_id
             JOIN products p ON p.id = bi.product_id
             ORDER BY stock_status DESC, b.branch_name, p.product_name"
        )->fetchAll();
    }

    public function materials(): array
    {
        return $this->db->query(
            "SELECT id, material_name, unit, stock_quantity, min_stock_level, unit_cost, supplier_name,
                    CASE WHEN stock_quantity < min_stock_level THEN 'low' ELSE 'ok' END AS stock_status
             FROM inventory_materials
             ORDER BY stock_status DESC, material_name"
        )->fetchAll();
    }

    public function movements(): array
    {
        return $this->db->query(
            "SELECT sm.id, sm.movement_code, sm.movement_type, sm.quantity, sm.unit_cost, sm.total_amount,
                    sm.pos_session_id, sm.supplier_name, sm.batch_code, sm.expiry_date,
                    sm.note, sm.created_at, im.material_name, s.staff_name, b.branch_name
             FROM stock_movements sm
             JOIN inventory_materials im ON im.id = sm.material_id
             JOIN staff s ON s.id = sm.staff_id
             JOIN branches b ON b.id = sm.branch_id
             ORDER BY sm.created_at DESC
             LIMIT 12"
        )->fetchAll();
    }

    public function recipes(): array
    {
        return $this->db->query(
            "SELECT r.id, r.product_id, p.product_name, r.recipe_name, r.yield_quantity, r.status,
                    GROUP_CONCAT(CONCAT(im.material_name, ' ', ri.quantity_per_unit, ' ', im.unit) ORDER BY im.material_name SEPARATOR ', ') AS materials
             FROM recipes r
             JOIN products p ON p.id = r.product_id
             LEFT JOIN recipe_items ri ON ri.recipe_id = r.id
             LEFT JOIN inventory_materials im ON im.id = ri.material_id
             GROUP BY r.id, r.product_id, p.product_name, r.recipe_name, r.yield_quantity, r.status
             ORDER BY p.product_name"
        )->fetchAll();
    }

    public function createMovement(array $data): array
    {
        $type = in_array(($data['movement_type'] ?? 'import'), ['import', 'sales_export', 'waste_export'], true)
            ? $data['movement_type']
            : 'import';
        $quantity = max(0.01, (float) ($data['quantity'] ?? 1));
        $materialId = max(1, (int) ($data['material_id'] ?? 1));
        $branchId = max(1, (int) ($data['branch_id'] ?? 1));
        $staffId = max(1, (int) ($data['staff_id'] ?? 1));
        $posSessionId = max(1, (int) ($data['pos_session_id'] ?? 0));
        $sign = $type === 'import' ? 1 : -1;
        $unitCost = max(0, (float) ($data['unit_cost'] ?? 0));
        $totalAmount = max(0, (float) ($data['total_amount'] ?? ($unitCost * $quantity)));

        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "INSERT INTO stock_movements (
                    movement_code, material_id, branch_id, staff_id, pos_session_id, movement_type,
                    quantity, unit_cost, total_amount, supplier_name, batch_code, expiry_date, note
                 ) VALUES (
                    :code, :material_id, :branch_id, :staff_id, :pos_session_id, :movement_type,
                    :quantity, :unit_cost, :total_amount, :supplier_name, :batch_code, :expiry_date, :note
                 )"
            )->execute([
                'code' => strtoupper(substr($type, 0, 2)) . '-' . date('His') . '-' . random_int(10, 99),
                'material_id' => $materialId,
                'branch_id' => $branchId,
                'staff_id' => $staffId,
                'pos_session_id' => $posSessionId,
                'movement_type' => $type,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_amount' => $totalAmount,
                'supplier_name' => trim((string) ($data['supplier_name'] ?? '')) ?: null,
                'batch_code' => trim((string) ($data['batch_code'] ?? '')) ?: null,
                'expiry_date' => trim((string) ($data['expiry_date'] ?? '')) ?: null,
                'note' => trim((string) ($data['note'] ?? '')),
            ]);
            $movementId = (int) $this->db->lastInsertId();

            $this->db->prepare(
                "UPDATE inventory_materials
                 SET stock_quantity = GREATEST(stock_quantity + :delta, 0), last_updated = NOW()
                 WHERE id = :id"
            )->execute(['delta' => $quantity * $sign, 'id' => $materialId]);

            (new PosSession())->logFromPayload($data, 'stock_movement', [
                'entity_type' => 'stock_movement',
                'entity_id' => $movementId,
                'quantity' => $quantity,
                'amount' => $totalAmount,
                'status_to' => $type,
                'note' => trim((string) ($data['note'] ?? '')),
            ]);
            (new AuditLog())->record([
                'actor_type' => 'staff',
                'actor_id' => $staffId,
                'actor_role' => (string) ($data['staff_role'] ?? ''),
                'action' => 'stock_movement',
                'entity_type' => 'stock_movement',
                'entity_id' => $movementId,
                'metadata' => [
                    'movement_type' => $type,
                    'material_id' => $materialId,
                    'quantity' => $quantity,
                    'total_amount' => $totalAmount,
                ],
            ]);

            $this->db->commit();
            return $this->overview();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function consumeInvoiceMaterials(int $invoiceId, int $branchId, int $staffId, ?int $posSessionId): void
    {
        $stmt = $this->db->prepare(
            "SELECT ri.material_id,
                    SUM(idt.quantity * ri.quantity_per_unit / GREATEST(r.yield_quantity, 0.0001)) AS quantity,
                    im.unit_cost,
                    im.material_name
             FROM invoice_details idt
             JOIN recipes r ON r.product_id = idt.product_id AND r.status = 'active'
             JOIN recipe_items ri ON ri.recipe_id = r.id
             JOIN inventory_materials im ON im.id = ri.material_id
             WHERE idt.invoice_id = :invoice_id
             GROUP BY ri.material_id, im.unit_cost, im.material_name"
        );
        $stmt->execute(['invoice_id' => $invoiceId]);
        $rows = $stmt->fetchAll();

        if (!$rows) {
            return;
        }

        $movementStmt = $this->db->prepare(
            "INSERT INTO stock_movements (
                movement_code, material_id, branch_id, staff_id, pos_session_id, movement_type,
                quantity, unit_cost, total_amount, note
             ) VALUES (
                :movement_code, :material_id, :branch_id, :staff_id, :pos_session_id, 'sales_export',
                :quantity, :unit_cost, :total_amount, :note
             )"
        );
        $stockStmt = $this->db->prepare(
            "UPDATE inventory_materials
             SET stock_quantity = GREATEST(stock_quantity - :quantity, 0), last_updated = NOW()
             WHERE id = :material_id"
        );

        foreach ($rows as $row) {
            $quantity = round((float) $row['quantity'], 4);
            if ($quantity <= 0) {
                continue;
            }

            $unitCost = (float) $row['unit_cost'];
            $movementStmt->execute([
                'movement_code' => 'SALE-' . $invoiceId . '-' . (int) $row['material_id'],
                'material_id' => (int) $row['material_id'],
                'branch_id' => $branchId,
                'staff_id' => $staffId,
                'pos_session_id' => $posSessionId,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_amount' => round($quantity * $unitCost, 2),
                'note' => 'Auto consume for invoice #' . $invoiceId . ' - ' . $row['material_name'],
            ]);
            $stockStmt->execute(['quantity' => $quantity, 'material_id' => (int) $row['material_id']]);
        }
    }
}
