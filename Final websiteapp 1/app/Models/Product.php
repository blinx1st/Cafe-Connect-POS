<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

final class Product extends Model
{
    public function categories(bool $includeInactive = false): array
    {
        $where = $includeInactive ? '1 = 1' : "status = 'active'";
        return $this->db->query(
            "SELECT id, category_code, category_name, display_order, status
             FROM product_categories
             WHERE $where
             ORDER BY display_order, category_name"
        )->fetchAll();
    }

    public function allForAdmin(array $filters = []): array
    {
        $branchId = max(1, (int) ($filters['branch_id'] ?? 1));
        $where = ['1 = 1'];
        $params = ['branch_id_select' => $branchId, 'branch_id_join' => $branchId];

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

        $status = trim((string) ($filters['status'] ?? ''));
        if (in_array($status, ['active', 'inactive'], true)) {
            $where[] = 'p.status = :status';
            $params['status'] = $status;
        }

        $stmt = $this->db->prepare(
            "SELECT p.id, p.product_name, p.category, p.price, p.take_note, p.status,
                    p.created_at, p.updated_at,
                    c.category_name,
                    COALESCE(pi.image_path, 'assets/images/coffee-1.png') AS image,
                    COALESCE(bi.branch_id, :branch_id_select) AS branch_id,
                    COALESCE(bi.stock_quantity, 0) AS stock_quantity,
                    COALESCE(bi.min_stock_level, 0) AS min_stock_level
             FROM products p
             LEFT JOIN product_categories c ON c.category_code = p.category
             LEFT JOIN product_images pi ON pi.product_id = p.id AND pi.is_primary = 1
             LEFT JOIN branch_inventory bi ON bi.product_id = p.id AND bi.branch_id = :branch_id_join
             WHERE " . implode(' AND ', $where) . "
             ORDER BY FIELD(p.status, 'active', 'inactive'), COALESCE(c.display_order, 99), p.product_name"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        foreach ($rows as &$row) {
            $row['price'] = (float) $row['price'];
            $row['branch_id'] = (int) $row['branch_id'];
            $row['stock_quantity'] = (int) $row['stock_quantity'];
            $row['min_stock_level'] = (int) $row['min_stock_level'];
            $row['is_out_of_stock'] = $row['status'] !== 'active' || $row['stock_quantity'] <= 0;
            $row['is_low_stock'] = $row['stock_quantity'] <= $row['min_stock_level'];
        }

        return $rows;
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
        $product['is_out_of_stock'] = $product['status'] !== 'active' || $product['stock_quantity'] <= 0;

        $images = $this->db->prepare(
            "SELECT image_path, alt_text, is_primary, display_order
             FROM product_images
             WHERE product_id = :id
             ORDER BY is_primary DESC, display_order, id"
        );
        $images->execute(['id' => $productId]);
        $product['images'] = $images->fetchAll();
        if (!$product['images']) {
            $product['images'] = [[
                'image_path' => $product['image'],
                'alt_text' => $product['product_name'],
                'is_primary' => 1,
                'display_order' => 1,
            ]];
        }

        $branchInventory = $this->db->prepare(
            "SELECT b.branch_name, b.district, COALESCE(bi.stock_quantity, 0) AS stock_quantity,
                    COALESCE(bi.min_stock_level, 0) AS min_stock_level,
                    COALESCE(bi.last_updated, b.created_at) AS last_updated
             FROM branches b
             LEFT JOIN branch_inventory bi ON bi.branch_id = b.id AND bi.product_id = :id
             WHERE b.status = 'active'
             ORDER BY b.id"
        );
        $branchInventory->execute(['id' => $productId]);
        $product['branch_inventory'] = array_map(static function (array $row): array {
            $stock = (float) $row['stock_quantity'];
            $min = (float) $row['min_stock_level'];
            $row['stock_quantity'] = $stock;
            $row['min_stock_level'] = $min;
            $row['stock_status'] = $stock <= 0 ? 'out' : ($min > 0 && $stock <= $min ? 'low' : 'available');
            return $row;
        }, $branchInventory->fetchAll());

        $related = $this->db->prepare(
            "SELECT p.id, p.product_name, p.category, p.price, p.take_note, c.category_name,
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
             WHERE p.status = 'active' AND p.id <> :id AND p.category = :category
             ORDER BY COALESCE(stock.stock_quantity, 0) DESC, p.product_name
             LIMIT 3"
        );
        $related->execute(['id' => $productId, 'category' => $product['category']]);
        $product['related_products'] = array_map(static function (array $row): array {
            $row['price'] = (float) $row['price'];
            $row['stock_quantity'] = (float) $row['stock_quantity'];
            $row['is_out_of_stock'] = $row['stock_quantity'] <= 0;
            return $row;
        }, $related->fetchAll());

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
        $branchId = max(1, (int) ($data['branch_id'] ?? 1));
        $hasInventory = array_key_exists('stock_quantity', $data) || array_key_exists('min_stock_level', $data);
        $payload = [
            'product_name' => require_field($data, 'product_name', 'Product name'),
            'category' => $data['category'] ?? 'coffee',
            'price' => max(0, (float) ($data['price'] ?? 0)),
            'take_note' => trim((string) ($data['take_note'] ?? '')),
            'status' => in_array(($data['status'] ?? 'active'), ['active', 'inactive'], true) ? $data['status'] : 'active',
        ];

        $this->db->beginTransaction();
        try {
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

            if ($hasInventory) {
                $this->upsertBranchInventory(
                    $id,
                    $branchId,
                    max(0, (int) ($data['stock_quantity'] ?? 0)),
                    max(0, (int) ($data['min_stock_level'] ?? 0))
                );
            }

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }

        return $this->adminPayload($branchId) + ['id' => $id];
    }

    public function softDelete(int $id, int $branchId = 1): array
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Product id is required.');
        }
        $this->db->prepare("UPDATE products SET status = 'inactive' WHERE id = :id")->execute(['id' => $id]);

        return $this->adminPayload($branchId) + ['id' => $id];
    }

    public function restore(int $id, int $branchId = 1): array
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Product id is required.');
        }
        $this->db->prepare("UPDATE products SET status = 'active' WHERE id = :id")->execute(['id' => $id]);

        return $this->adminPayload($branchId) + ['id' => $id];
    }

    public function saveImage(int $productId, string $imagePath, string $altText = '', bool $isPrimary = true): array
    {
        if ($productId <= 0) {
            throw new \InvalidArgumentException('Product id is required.');
        }
        if ($imagePath === '') {
            throw new \InvalidArgumentException('Image path is required.');
        }

        $exists = $this->db->prepare('SELECT id FROM products WHERE id = :id LIMIT 1');
        $exists->execute(['id' => $productId]);
        if (!$exists->fetch()) {
            throw new \InvalidArgumentException('Product not found.');
        }

        $this->db->beginTransaction();
        try {
            if ($isPrimary) {
                $this->db->prepare('UPDATE product_images SET is_primary = 0 WHERE product_id = :product_id')
                    ->execute(['product_id' => $productId]);
            }
            $this->db->prepare(
                "INSERT INTO product_images (product_id, image_path, alt_text, is_primary, display_order)
                 VALUES (:product_id, :image_path, :alt_text, :is_primary, 0)"
            )->execute([
                'product_id' => $productId,
                'image_path' => $imagePath,
                'alt_text' => $altText,
                'is_primary' => $isPrimary ? 1 : 0,
            ]);
            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }

        return $this->adminPayload();
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

        if ($id <= 0) {
            $lookup = $this->db->prepare('SELECT id FROM product_categories WHERE category_code = :category_code LIMIT 1');
            $lookup->execute(['category_code' => $payload['category_code']]);
            $id = (int) ($lookup->fetch()['id'] ?? 0);
        }

        return ['id' => $id, 'categories' => $this->categories(), 'admin_categories' => $this->categories(true)];
    }

    public function adminPayload(int $branchId = 1): array
    {
        return [
            'products' => $this->active(),
            'admin_products' => $this->allForAdmin(['branch_id' => $branchId]),
            'categories' => $this->categories(),
            'admin_categories' => $this->categories(true),
        ];
    }

    private function upsertBranchInventory(int $productId, int $branchId, int $stockQuantity, int $minStockLevel): void
    {
        $this->db->prepare(
            "INSERT INTO branch_inventory (branch_id, product_id, stock_quantity, min_stock_level, last_updated)
             VALUES (:branch_id, :product_id, :stock_quantity, :min_stock_level, NOW())
             ON DUPLICATE KEY UPDATE
                stock_quantity = VALUES(stock_quantity),
                min_stock_level = VALUES(min_stock_level),
                last_updated = NOW()"
        )->execute([
            'branch_id' => $branchId,
            'product_id' => $productId,
            'stock_quantity' => $stockQuantity,
            'min_stock_level' => $minStockLevel,
        ]);
    }
}
