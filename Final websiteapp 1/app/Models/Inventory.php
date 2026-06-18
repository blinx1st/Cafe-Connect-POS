<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Inventory extends Model
{
    public function overview(): array
    {
        return [
            'materials' => $this->branchMaterials(),
            'material_catalog' => $this->materialCatalog(),
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
        return $this->branchMaterials();
    }

    public function lowMaterials(int $limit = 8): array
    {
        $stmt = $this->db->prepare(
            "SELECT b.branch_name, im.material_name, im.unit,
                    bmi.stock_quantity, bmi.min_stock_level, bmi.unit_cost, bmi.last_updated,
                    CASE
                        WHEN bmi.stock_quantity <= 0 THEN 'out'
                        WHEN bmi.stock_quantity < bmi.min_stock_level THEN 'low'
                        ELSE 'ok'
                    END AS stock_status
             FROM branch_material_inventory bmi
             JOIN branches b ON b.id = bmi.branch_id
             JOIN inventory_materials im ON im.id = bmi.material_id
             WHERE im.status = 'active'
             ORDER BY FIELD(stock_status, 'out', 'low', 'ok'), b.branch_name, im.material_name
             LIMIT :limit"
        );
        $stmt->bindValue('limit', max(1, $limit), \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    private function branchMaterials(): array
    {
        return $this->db->query(
            "SELECT bmi.id, bmi.branch_id, bmi.material_id, b.branch_name,
                    im.material_name, im.unit, im.supplier_name, im.status,
                    bmi.stock_quantity, bmi.min_stock_level, bmi.unit_cost, bmi.last_updated,
                    CASE
                        WHEN bmi.stock_quantity <= 0 THEN 'out'
                        WHEN bmi.stock_quantity < bmi.min_stock_level THEN 'low'
                        ELSE 'ok'
                    END AS stock_status
             FROM branch_material_inventory bmi
             JOIN branches b ON b.id = bmi.branch_id
             JOIN inventory_materials im ON im.id = bmi.material_id
             ORDER BY FIELD(stock_status, 'out', 'low', 'ok'), b.branch_name, im.material_name"
        )->fetchAll();
    }

    private function materialCatalog(): array
    {
        return $this->db->query(
            "SELECT id, material_name, unit, min_stock_level, unit_cost, supplier_name, status
             FROM inventory_materials
             WHERE status = 'active'
             ORDER BY material_name"
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
        $type = in_array(($data['movement_type'] ?? 'import'), ['import', 'sales_export', 'waste_export', 'adjustment'], true)
            ? $data['movement_type']
            : 'import';
        $quantity = max(0.01, (float) ($data['quantity'] ?? 1));
        $materialId = max(1, (int) ($data['material_id'] ?? 1));
        $branchId = max(1, (int) ($data['branch_id'] ?? 1));
        $staffId = max(1, (int) ($data['staff_id'] ?? 1));
        $posSessionId = max(1, (int) ($data['pos_session_id'] ?? 0));
        $sign = in_array($type, ['import', 'adjustment'], true) ? 1 : -1;
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
                "INSERT INTO branch_material_inventory (
                    branch_id, material_id, stock_quantity, min_stock_level, unit_cost, last_updated
                 )
                 SELECT :branch_id, im.id,
                        GREATEST(:delta, 0),
                        im.min_stock_level,
                        COALESCE(NULLIF(:unit_cost, 0), im.unit_cost),
                        NOW()
                 FROM inventory_materials im
                 WHERE im.id = :material_id
                 ON DUPLICATE KEY UPDATE
                    stock_quantity = GREATEST(stock_quantity + VALUES(stock_quantity) - GREATEST(-:delta_again, 0), 0),
                    unit_cost = CASE WHEN VALUES(unit_cost) > 0 THEN VALUES(unit_cost) ELSE unit_cost END,
                    last_updated = NOW()"
            )->execute([
                'branch_id' => $branchId,
                'delta' => $quantity * $sign,
                'delta_again' => $quantity * $sign,
                'unit_cost' => $unitCost,
                'material_id' => $materialId,
            ]);
            $this->syncMaterialAggregate($materialId);

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
                    COALESCE(bmi.unit_cost, im.unit_cost) AS unit_cost,
                    im.material_name
             FROM invoice_details idt
             JOIN recipes r ON r.product_id = idt.product_id AND r.status = 'active'
             JOIN recipe_items ri ON ri.recipe_id = r.id
             JOIN inventory_materials im ON im.id = ri.material_id
             LEFT JOIN branch_material_inventory bmi ON bmi.material_id = im.id AND bmi.branch_id = :branch_id
             WHERE idt.invoice_id = :invoice_id
             GROUP BY ri.material_id, COALESCE(bmi.unit_cost, im.unit_cost), im.material_name"
        );
        $stmt->execute(['invoice_id' => $invoiceId, 'branch_id' => $branchId]);
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
            "UPDATE branch_material_inventory
             SET stock_quantity = GREATEST(stock_quantity - :quantity, 0), last_updated = NOW()
             WHERE branch_id = :branch_id AND material_id = :material_id"
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
            $stockStmt->execute([
                'quantity' => $quantity,
                'branch_id' => $branchId,
                'material_id' => (int) $row['material_id'],
            ]);
            $this->syncMaterialAggregate((int) $row['material_id']);
        }
    }

    public function assertMaterialsAvailableForItems(array $items, int $branchId): void
    {
        $requirements = $this->materialRequirementsForItems($items);
        if (!$requirements) {
            return;
        }

        $materialIds = array_keys($requirements);
        $placeholders = implode(',', array_fill(0, count($materialIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT im.id, im.material_name, im.unit, COALESCE(bmi.stock_quantity, 0) AS stock_quantity
             FROM inventory_materials im
             LEFT JOIN branch_material_inventory bmi ON bmi.material_id = im.id AND bmi.branch_id = ?
             WHERE im.id IN ($placeholders)
             FOR UPDATE"
        );
        $stmt->execute(array_merge([$branchId], $materialIds));

        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[(int) $row['id']] = $row;
        }

        foreach ($requirements as $materialId => $required) {
            $row = $rows[$materialId] ?? null;
            $available = (float) ($row['stock_quantity'] ?? 0);
            if (!$row || $available + 0.0001 < $required) {
                $name = (string) ($row['material_name'] ?? ('Material #' . $materialId));
                $unit = (string) ($row['unit'] ?? '');
                throw new \InvalidArgumentException(sprintf(
                    'Không đủ nguyên vật liệu %s. Còn %.2f %s, cần %.2f %s.',
                    $name,
                    $available,
                    $unit,
                    $required,
                    $unit
                ));
            }
        }
    }

    public function restoreInvoiceMaterials(int $invoiceId, int $branchId, int $staffId, ?int $posSessionId, string $note): void
    {
        $stmt = $this->db->prepare(
            "SELECT ri.material_id,
                    SUM(idt.quantity * ri.quantity_per_unit / GREATEST(r.yield_quantity, 0.0001)) AS quantity,
                    COALESCE(bmi.unit_cost, im.unit_cost) AS unit_cost,
                    im.material_name
             FROM invoice_details idt
             JOIN recipes r ON r.product_id = idt.product_id AND r.status = 'active'
             JOIN recipe_items ri ON ri.recipe_id = r.id
             JOIN inventory_materials im ON im.id = ri.material_id
             LEFT JOIN branch_material_inventory bmi ON bmi.material_id = im.id AND bmi.branch_id = :branch_id
             WHERE idt.invoice_id = :invoice_id
             GROUP BY ri.material_id, COALESCE(bmi.unit_cost, im.unit_cost), im.material_name"
        );
        $stmt->execute(['invoice_id' => $invoiceId, 'branch_id' => $branchId]);

        $movementStmt = $this->db->prepare(
            "INSERT INTO stock_movements (
                movement_code, material_id, branch_id, staff_id, pos_session_id, movement_type,
                quantity, unit_cost, total_amount, note
             ) VALUES (
                :movement_code, :material_id, :branch_id, :staff_id, :pos_session_id, 'adjustment',
                :quantity, :unit_cost, :total_amount, :note
             )"
        );
        $stockStmt = $this->db->prepare(
            "UPDATE branch_material_inventory
             SET stock_quantity = stock_quantity + :quantity, last_updated = NOW()
             WHERE branch_id = :branch_id AND material_id = :material_id"
        );

        foreach ($stmt->fetchAll() as $row) {
            $quantity = round((float) $row['quantity'], 4);
            if ($quantity <= 0) {
                continue;
            }
            $unitCost = (float) $row['unit_cost'];
            $movementStmt->execute([
                'movement_code' => 'RESTORE-' . $invoiceId . '-' . (int) $row['material_id'],
                'material_id' => (int) $row['material_id'],
                'branch_id' => $branchId,
                'staff_id' => $staffId,
                'pos_session_id' => $posSessionId,
                'quantity' => $quantity,
                'unit_cost' => $unitCost,
                'total_amount' => round($quantity * $unitCost, 2),
                'note' => $note . ' - ' . $row['material_name'],
            ]);
            $stockStmt->execute([
                'quantity' => $quantity,
                'branch_id' => $branchId,
                'material_id' => (int) $row['material_id'],
            ]);
            $this->syncMaterialAggregate((int) $row['material_id']);
        }
    }

    private function materialRequirementsForItems(array $items): array
    {
        $requiredProducts = [];
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $requiredProducts[$productId] = ($requiredProducts[$productId] ?? 0) + max(1, (int) ($item['quantity'] ?? 1));
        }
        if (!$requiredProducts) {
            return [];
        }

        $productIds = array_keys($requiredProducts);
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT r.product_id, ri.material_id, ri.quantity_per_unit, r.yield_quantity
             FROM recipes r
             JOIN recipe_items ri ON ri.recipe_id = r.id
             WHERE r.status = 'active' AND r.product_id IN ($placeholders)"
        );
        $stmt->execute($productIds);

        $requirements = [];
        foreach ($stmt->fetchAll() as $row) {
            $productId = (int) $row['product_id'];
            $materialId = (int) $row['material_id'];
            $requirements[$materialId] = ($requirements[$materialId] ?? 0)
                + ((float) $requiredProducts[$productId] * (float) $row['quantity_per_unit'] / max((float) $row['yield_quantity'], 0.0001));
        }

        return $requirements;
    }

    private function syncMaterialAggregate(int $materialId): void
    {
        $this->db->prepare(
            "UPDATE inventory_materials im
             SET stock_quantity = COALESCE((
                    SELECT SUM(bmi.stock_quantity)
                    FROM branch_material_inventory bmi
                    WHERE bmi.material_id = im.id
                ), 0),
                unit_cost = COALESCE((
                    SELECT MAX(bmi.unit_cost)
                    FROM branch_material_inventory bmi
                    WHERE bmi.material_id = im.id AND bmi.unit_cost > 0
                ), im.unit_cost),
                last_updated = NOW()
             WHERE im.id = :material_id"
        )->execute(['material_id' => $materialId]);
    }
}
