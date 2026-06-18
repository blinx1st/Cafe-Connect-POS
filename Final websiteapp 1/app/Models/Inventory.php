<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;
use InvalidArgumentException;

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
             ORDER BY FIELD(status, 'active', 'inactive'), material_name"
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
        $recipes = $this->db->query(
            "SELECT r.id, r.product_id, p.product_name, r.recipe_name, r.yield_quantity, r.status,
                    GROUP_CONCAT(CONCAT(im.material_name, ' ', ri.quantity_per_unit, ' ', im.unit) ORDER BY im.material_name SEPARATOR ', ') AS materials
             FROM recipes r
             JOIN products p ON p.id = r.product_id
             LEFT JOIN recipe_items ri ON ri.recipe_id = r.id
             LEFT JOIN inventory_materials im ON im.id = ri.material_id
             GROUP BY r.id, r.product_id, p.product_name, r.recipe_name, r.yield_quantity, r.status
             ORDER BY FIELD(r.status, 'active', 'inactive'), p.product_name"
        )->fetchAll();

        if (!$recipes) {
            return [];
        }

        $ids = array_map(static fn (array $row): int => (int) $row['id'], $recipes);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $items = $this->db->prepare(
            "SELECT ri.recipe_id, ri.material_id, ri.quantity_per_unit,
                    im.material_name, im.unit
             FROM recipe_items ri
             JOIN inventory_materials im ON im.id = ri.material_id
             WHERE ri.recipe_id IN ($placeholders)
             ORDER BY ri.recipe_id, im.material_name"
        );
        $items->execute($ids);

        $byRecipe = [];
        foreach ($items->fetchAll() as $item) {
            $byRecipe[(int) $item['recipe_id']][] = $item;
        }
        foreach ($recipes as &$recipe) {
            $recipe['items'] = $byRecipe[(int) $recipe['id']] ?? [];
        }

        return $recipes;
    }

    public function saveMaterial(array $data): array
    {
        $id = (int) ($data['id'] ?? $data['material_id'] ?? 0);
        $name = require_field($data, 'material_name', 'Tên nguyên vật liệu');
        $unit = require_field($data, 'unit', 'Đơn vị tính');
        $minStock = max(0, (float) ($data['min_stock_level'] ?? 0));
        $unitCost = max(0, (float) ($data['unit_cost'] ?? 0));
        $supplier = substr(trim((string) ($data['supplier_name'] ?? '')), 0, 150) ?: null;
        $status = in_array(($data['status'] ?? 'active'), ['active', 'inactive'], true) ? $data['status'] : 'active';
        $duplicate = $this->db->prepare(
            "SELECT id FROM inventory_materials WHERE material_name = :name AND id <> :id LIMIT 1"
        );
        $duplicate->execute(['name' => $name, 'id' => $id]);
        if ($duplicate->fetchColumn()) {
            throw new InvalidArgumentException('Tên nguyên vật liệu đã tồn tại.');
        }

        $this->db->beginTransaction();
        try {
            if ($id > 0) {
                $this->db->prepare(
                    "UPDATE inventory_materials
                     SET material_name = :name,
                         unit = :unit,
                         min_stock_level = :min_stock,
                         unit_cost = :unit_cost,
                         supplier_name = :supplier,
                         status = :status,
                         last_updated = NOW()
                     WHERE id = :id"
                )->execute([
                    'id' => $id,
                    'name' => $name,
                    'unit' => $unit,
                    'min_stock' => $minStock,
                    'unit_cost' => $unitCost,
                    'supplier' => $supplier,
                    'status' => $status,
                ]);
            } else {
                $this->db->prepare(
                    "INSERT INTO inventory_materials (
                        material_name, unit, stock_quantity, min_stock_level, unit_cost, supplier_name, status, last_updated
                     ) VALUES (
                        :name, :unit, 0, :min_stock, :unit_cost, :supplier, :status, NOW()
                     )"
                )->execute([
                    'name' => $name,
                    'unit' => $unit,
                    'min_stock' => $minStock,
                    'unit_cost' => $unitCost,
                    'supplier' => $supplier,
                    'status' => $status,
                ]);
                $id = (int) $this->db->lastInsertId();
            }

            $this->ensureMaterialBranchRows($id, $minStock, $unitCost);
            $this->syncMaterialAggregate($id);
            $this->auditInventory($data, 'material_save', 'inventory_material', $id, [
                'material_name' => $name,
                'unit' => $unit,
                'status' => $status,
            ]);

            $this->db->commit();
            return ['id' => $id] + $this->overview();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function deleteMaterial(int $id, array $data = []): array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Thiếu nguyên vật liệu cần ngừng sử dụng.');
        }

        $this->db->prepare(
            "UPDATE inventory_materials SET status = 'inactive', last_updated = NOW() WHERE id = :id"
        )->execute(['id' => $id]);
        $this->auditInventory($data, 'material_delete', 'inventory_material', $id);
        return $this->overview();
    }

    public function restoreMaterial(int $id, array $data = []): array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Thiếu nguyên vật liệu cần khôi phục.');
        }

        $this->db->prepare(
            "UPDATE inventory_materials SET status = 'active', last_updated = NOW() WHERE id = :id"
        )->execute(['id' => $id]);
        $this->auditInventory($data, 'material_restore', 'inventory_material', $id);
        return $this->overview();
    }

    public function saveBranchStock(array $data): array
    {
        $branchId = max(1, (int) ($data['branch_id'] ?? 0));
        $materialId = max(1, (int) ($data['material_id'] ?? 0));
        $stock = max(0, (float) ($data['stock_quantity'] ?? 0));
        $minStock = max(0, (float) ($data['min_stock_level'] ?? 0));
        $unitCost = max(0, (float) ($data['unit_cost'] ?? 0));

        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "INSERT INTO branch_material_inventory (
                    branch_id, material_id, stock_quantity, min_stock_level, unit_cost, last_updated
                 ) VALUES (
                    :branch_id, :material_id, :stock, :min_stock, :unit_cost, NOW()
                 )
                 ON DUPLICATE KEY UPDATE
                    stock_quantity = VALUES(stock_quantity),
                    min_stock_level = VALUES(min_stock_level),
                    unit_cost = VALUES(unit_cost),
                    last_updated = NOW()"
            )->execute([
                'branch_id' => $branchId,
                'material_id' => $materialId,
                'stock' => $stock,
                'min_stock' => $minStock,
                'unit_cost' => $unitCost,
            ]);
            $this->syncMaterialAggregate($materialId);
            $this->auditInventory($data, 'branch_material_stock_save', 'branch_material_inventory', $materialId, [
                'branch_id' => $branchId,
                'stock_quantity' => $stock,
                'min_stock_level' => $minStock,
                'unit_cost' => $unitCost,
            ]);
            $this->db->commit();
            return $this->overview();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function saveRecipe(array $data): array
    {
        $id = (int) ($data['id'] ?? $data['recipe_id'] ?? 0);
        $productId = max(1, (int) ($data['product_id'] ?? 0));
        $recipeName = trim((string) ($data['recipe_name'] ?? ''));
        $yield = max(0.0001, (float) ($data['yield_quantity'] ?? 1));
        $status = in_array(($data['status'] ?? 'active'), ['active', 'inactive'], true) ? $data['status'] : 'active';
        $items = $this->recipeItemsFromPayload($data);
        if ($status === 'active' && !$items) {
            throw new InvalidArgumentException('Recipe đang hoạt động cần ít nhất một nguyên vật liệu.');
        }

        $this->db->beginTransaction();
        try {
            if ($id <= 0) {
                $stmt = $this->db->prepare("SELECT id FROM recipes WHERE product_id = :product_id LIMIT 1");
                $stmt->execute(['product_id' => $productId]);
                $id = (int) ($stmt->fetchColumn() ?: 0);
            }
            if ($recipeName === '') {
                $stmt = $this->db->prepare("SELECT product_name FROM products WHERE id = :id LIMIT 1");
                $stmt->execute(['id' => $productId]);
                $recipeName = ((string) ($stmt->fetchColumn() ?: 'Sản phẩm')) . ' recipe';
            }

            if ($id > 0) {
                $this->db->prepare(
                    "UPDATE recipes
                     SET product_id = :product_id,
                         recipe_name = :recipe_name,
                         yield_quantity = :yield_quantity,
                         status = :status
                     WHERE id = :id"
                )->execute([
                    'id' => $id,
                    'product_id' => $productId,
                    'recipe_name' => $recipeName,
                    'yield_quantity' => $yield,
                    'status' => $status,
                ]);
            } else {
                $this->db->prepare(
                    "INSERT INTO recipes (product_id, recipe_name, yield_quantity, status)
                     VALUES (:product_id, :recipe_name, :yield_quantity, :status)"
                )->execute([
                    'product_id' => $productId,
                    'recipe_name' => $recipeName,
                    'yield_quantity' => $yield,
                    'status' => $status,
                ]);
                $id = (int) $this->db->lastInsertId();
            }

            $this->db->prepare("DELETE FROM recipe_items WHERE recipe_id = :id")->execute(['id' => $id]);
            $itemStmt = $this->db->prepare(
                "INSERT INTO recipe_items (recipe_id, material_id, quantity_per_unit)
                 VALUES (:recipe_id, :material_id, :quantity_per_unit)"
            );
            foreach ($items as $item) {
                $itemStmt->execute([
                    'recipe_id' => $id,
                    'material_id' => $item['material_id'],
                    'quantity_per_unit' => $item['quantity_per_unit'],
                ]);
            }

            $this->auditInventory($data, 'recipe_save', 'recipe', $id, [
                'product_id' => $productId,
                'items' => $items,
                'status' => $status,
            ]);
            $this->db->commit();
            return ['id' => $id] + $this->overview();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }

    public function deleteRecipe(int $id, array $data = []): array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Thiếu recipe cần ngừng sử dụng.');
        }
        $this->db->prepare("UPDATE recipes SET status = 'inactive' WHERE id = :id")->execute(['id' => $id]);
        $this->auditInventory($data, 'recipe_delete', 'recipe', $id);
        return $this->overview();
    }

    public function restoreRecipe(int $id, array $data = []): array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Thiếu recipe cần khôi phục.');
        }
        $this->db->prepare("UPDATE recipes SET status = 'active' WHERE id = :id")->execute(['id' => $id]);
        $this->auditInventory($data, 'recipe_restore', 'recipe', $id);
        return $this->overview();
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
                    stock_quantity = GREATEST(branch_material_inventory.stock_quantity + VALUES(stock_quantity) - GREATEST(-:delta_again, 0), 0),
                    unit_cost = CASE WHEN VALUES(unit_cost) > 0 THEN VALUES(unit_cost) ELSE branch_material_inventory.unit_cost END,
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

    private function ensureMaterialBranchRows(int $materialId, float $minStock, float $unitCost): void
    {
        $this->db->prepare(
            "INSERT INTO branch_material_inventory (
                branch_id, material_id, stock_quantity, min_stock_level, unit_cost, last_updated
             )
             SELECT b.id, :material_id, 0, :min_stock, :unit_cost, NOW()
             FROM branches b
             ON DUPLICATE KEY UPDATE
                min_stock_level = CASE WHEN min_stock_level = 0 THEN VALUES(min_stock_level) ELSE min_stock_level END,
                unit_cost = CASE WHEN unit_cost = 0 THEN VALUES(unit_cost) ELSE unit_cost END,
                last_updated = NOW()"
        )->execute([
            'material_id' => $materialId,
            'min_stock' => $minStock,
            'unit_cost' => $unitCost,
        ]);
    }

    private function recipeItemsFromPayload(array $data): array
    {
        $raw = $data['items'] ?? $data['recipe_items'] ?? $data['items_json'] ?? [];
        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($raw)) {
            return [];
        }

        $items = [];
        foreach ($raw as $item) {
            if (!is_array($item)) {
                continue;
            }
            $materialId = (int) ($item['material_id'] ?? 0);
            $quantity = (float) ($item['quantity_per_unit'] ?? $item['quantity'] ?? 0);
            if ($materialId <= 0 || $quantity <= 0) {
                continue;
            }
            $items[$materialId] = [
                'material_id' => $materialId,
                'quantity_per_unit' => round(($items[$materialId]['quantity_per_unit'] ?? 0) + $quantity, 4),
            ];
        }

        return array_values($items);
    }

    private function auditInventory(array $data, string $action, string $entityType, int $entityId, array $metadata = []): void
    {
        (new AuditLog())->record([
            'actor_type' => 'staff',
            'actor_id' => (int) ($data['staff_id'] ?? 0) ?: null,
            'actor_role' => (string) ($data['staff_role'] ?? ''),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'metadata' => $metadata,
        ]);
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
