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
            "SELECT id, material_name, unit, stock_quantity, min_stock_level, supplier_name,
                    CASE WHEN stock_quantity < min_stock_level THEN 'low' ELSE 'ok' END AS stock_status
             FROM inventory_materials
             ORDER BY stock_status DESC, material_name"
        )->fetchAll();
    }

    public function movements(): array
    {
        return $this->db->query(
            "SELECT sm.id, sm.movement_code, sm.movement_type, sm.quantity, sm.total_amount, sm.pos_session_id,
                    sm.note, sm.created_at, im.material_name, s.staff_name, b.branch_name
             FROM stock_movements sm
             JOIN inventory_materials im ON im.id = sm.material_id
             JOIN staff s ON s.id = sm.staff_id
             JOIN branches b ON b.id = sm.branch_id
             ORDER BY sm.created_at DESC
             LIMIT 12"
        )->fetchAll();
    }

    public function createMovement(array $data): array
    {
        $type = in_array(($data['movement_type'] ?? 'import'), ['import', 'sales_export', 'waste_export'], true)
            ? $data['movement_type']
            : 'import';
        $quantity = max(1, (int) ($data['quantity'] ?? 1));
        $materialId = max(1, (int) ($data['material_id'] ?? 1));
        $branchId = max(1, (int) ($data['branch_id'] ?? 1));
        $staffId = max(1, (int) ($data['staff_id'] ?? 1));
        $posSessionId = max(1, (int) ($data['pos_session_id'] ?? 0));
        $sign = $type === 'import' ? 1 : -1;

        $this->db->beginTransaction();
        try {
            $this->db->prepare(
                "INSERT INTO stock_movements (
                    movement_code, material_id, branch_id, staff_id, pos_session_id, movement_type, quantity, total_amount, note
                 ) VALUES (
                    :code, :material_id, :branch_id, :staff_id, :pos_session_id, :movement_type, :quantity, :total_amount, :note
                 )"
            )->execute([
                'code' => strtoupper(substr($type, 0, 2)) . '-' . date('His'),
                'material_id' => $materialId,
                'branch_id' => $branchId,
                'staff_id' => $staffId,
                'pos_session_id' => $posSessionId,
                'movement_type' => $type,
                'quantity' => $quantity,
                'total_amount' => max(0, (float) ($data['total_amount'] ?? 0)),
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
                'amount' => max(0, (float) ($data['total_amount'] ?? 0)),
                'status_to' => $type,
                'note' => trim((string) ($data['note'] ?? '')),
            ]);

            $this->db->commit();
            return $this->overview();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }
    }
}
