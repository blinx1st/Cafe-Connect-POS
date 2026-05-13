<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Product extends Model
{
    public function categories(): array
    {
        return $this->db->query(
            "SELECT id, category_code, category_name, display_order
             FROM product_categories
             WHERE status = 'active'
             ORDER BY display_order, category_name"
        )->fetchAll();
    }

    public function active(): array
    {
        $rows = $this->db->query(
            "SELECT p.id, p.product_name, p.category, p.price, p.take_note, p.status,
                    c.category_name,
                    COALESCE(pi.image_path, 'assets/images/coffee-1.png') AS image
             FROM products p
             LEFT JOIN product_categories c ON c.category_code = p.category
             LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
             WHERE p.status = 'active'
             ORDER BY COALESCE(c.display_order, 99), p.product_name"
        )->fetchAll();

        foreach ($rows as &$row) {
            $row['price'] = (float) $row['price'];
        }

        return $rows;
    }

    public function byIds(array $ids): array
    {
        $ids = array_values(array_filter(array_unique(array_map('intval', $ids))));
        if (!$ids) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT id, product_name, category, price
             FROM products
             WHERE status = 'active' AND id IN ($placeholders)"
        );
        $stmt->execute($ids);

        $products = [];
        foreach ($stmt->fetchAll() as $row) {
            $products[(int) $row['id']] = $row;
        }

        return $products;
    }

    public function save(array $data): array
    {
        $id = (int) ($data['id'] ?? 0);
        $payload = [
            'product_name' => require_field($data, 'product_name', 'Product name'),
            'category' => $data['category'] ?? 'coffee',
            'price' => max(0, (float) ($data['price'] ?? 0)),
            'take_note' => trim((string) ($data['take_note'] ?? '')),
            'status' => in_array(($data['status'] ?? 'active'), ['active', 'inactive'], true) ? $data['status'] : 'active',
        ];

        if ($id > 0) {
            $payload['id'] = $id;
            $this->db->prepare(
                "UPDATE products
                 SET product_name = :product_name, category = :category, price = :price,
                     take_note = :take_note, status = :status
                 WHERE id = :id"
            )->execute($payload);
        } else {
            $this->db->prepare(
                "INSERT INTO products (product_name, category, price, take_note, status)
                 VALUES (:product_name, :category, :price, :take_note, :status)"
            )->execute($payload);
            $id = (int) $this->db->lastInsertId();
        }

        return ['id' => $id, 'products' => $this->active()];
    }
}
