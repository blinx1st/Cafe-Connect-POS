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

    public function active(array $filters = []): array
    {
        $where = ["p.status = 'active'"];
        $params = [];

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $where[] = '(p.product_name LIKE :search OR p.take_note LIKE :search)';
            $params['search'] = '%' . $search . '%';
        }

        $category = trim((string) ($filters['category'] ?? ''));
        if ($category !== '') {
            $where[] = 'p.category = :category';
            $params['category'] = $category;
        }

        $sort = (string) ($filters['sort'] ?? '');
        $orderBy = match ($sort) {
            'price_asc' => 'p.price ASC, p.product_name',
            'price_desc' => 'p.price DESC, p.product_name',
            'name_desc' => 'p.product_name DESC',
            default => 'COALESCE(c.display_order, 99), p.product_name',
        };

        $stmt = $this->db->prepare(
            "SELECT p.id, p.product_name, p.category, p.price, p.take_note, p.status,
                    c.category_name,
                    COALESCE(pi.image_path, 'assets/images/coffee-1.png') AS image,
                    COALESCE(stock.stock_quantity, 0) AS stock_quantity
             FROM products p
             LEFT JOIN product_categories c ON c.category_code = p.category
             LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
             LEFT JOIN (
                SELECT product_id, SUM(stock_quantity) AS stock_quantity
                FROM branch_inventory
                GROUP BY product_id
             ) stock ON stock.product_id = p.id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY $orderBy"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['price'] = (float) $row['price'];
            $row['stock_quantity'] = (float) $row['stock_quantity'];
            $row['is_out_of_stock'] = $row['stock_quantity'] <= 0;
        }

        return $rows;
    }

    public function detail(int $productId): ?array
    {
        if ($productId <= 0) {
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT p.id, p.product_name, p.category, p.price, p.take_note, p.status,
                    c.category_name,
                    COALESCE(pi.image_path, 'assets/images/coffee-1.png') AS image,
                    COALESCE(stock.stock_quantity, 0) AS stock_quantity
             FROM products p
             LEFT JOIN product_categories c ON c.category_code = p.category
             LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
             LEFT JOIN (
                SELECT product_id, SUM(stock_quantity) AS stock_quantity
                FROM branch_inventory
                GROUP BY product_id
             ) stock ON stock.product_id = p.id
             WHERE p.id = :id
             LIMIT 1"
        );
        $stmt->execute(['id' => $productId]);
        $product = $stmt->fetch();
        if (!$product) {
            return null;
        }

        $product['price'] = (float) $product['price'];
        $product['stock_quantity'] = (float) $product['stock_quantity'];
        $product['is_out_of_stock'] = $product['stock_quantity'] <= 0;

        $images = $this->db->prepare(
            "SELECT image_path, alt_text, is_primary, display_order
             FROM product_images
             WHERE product_id = :id
             ORDER BY is_primary DESC, display_order, id"
        );
        $images->execute(['id' => $productId]);
        $product['images'] = $images->fetchAll();

        return $product;
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

    public function saveCategory(array $data): array
    {
        $id = (int) ($data['id'] ?? 0);
        $payload = [
            'category_code' => require_field($data, 'category_code', 'Category code'),
            'category_name' => require_field($data, 'category_name', 'Category name'),
            'display_order' => max(0, (int) ($data['display_order'] ?? 0)),
            'status' => in_array(($data['status'] ?? 'active'), ['active', 'inactive'], true) ? $data['status'] : 'active',
        ];

        if ($id > 0) {
            $payload['id'] = $id;
            $this->db->prepare(
                "UPDATE product_categories
                 SET category_code = :category_code,
                     category_name = :category_name,
                     display_order = :display_order,
                     status = :status
                 WHERE id = :id"
            )->execute($payload);
        } else {
            $this->db->prepare(
                "INSERT INTO product_categories (category_code, category_name, display_order, status)
                 VALUES (:category_code, :category_name, :display_order, :status)
                 ON DUPLICATE KEY UPDATE
                    category_name = VALUES(category_name),
                    display_order = VALUES(display_order),
                    status = VALUES(status)"
            )->execute($payload);
            $id = (int) $this->db->lastInsertId();
        }

        return ['id' => $id, 'categories' => $this->categories()];
    }
}
