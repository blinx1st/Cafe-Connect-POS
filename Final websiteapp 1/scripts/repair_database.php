<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Core\Database;

$pdo = Database::pdo();

function table_exists(PDO $pdo, string $table): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = :table"
    );
    $stmt->execute(['table' => $table]);

    return (int) $stmt->fetchColumn() > 0;
}

function column_exists(PDO $pdo, string $table, string $column): bool
{
    $stmt = $pdo->prepare(
        "SELECT COUNT(*)
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = :table
           AND column_name = :column"
    );
    $stmt->execute(['table' => $table, 'column' => $column]);

    return (int) $stmt->fetchColumn() > 0;
}

function enum_column_contains(PDO $pdo, string $table, string $column, string $value): bool
{
    $stmt = $pdo->prepare(
        "SELECT column_type
         FROM information_schema.columns
         WHERE table_schema = DATABASE()
           AND table_name = :table
           AND column_name = :column
         LIMIT 1"
    );
    $stmt->execute(['table' => $table, 'column' => $column]);
    $columnType = (string) $stmt->fetchColumn();

    return str_contains($columnType, "'" . $value . "'");
}

function exec_if_missing_table(PDO $pdo, string $table, string $sql): void
{
    if (!table_exists($pdo, $table)) {
        $pdo->exec($sql);
        echo "created table {$table}" . PHP_EOL;
    } else {
        echo "ok table {$table}" . PHP_EOL;
    }
}

function add_column_if_missing(PDO $pdo, string $table, string $column, string $definition): void
{
    if (!column_exists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$definition}");
        echo "added column {$table}.{$column}" . PHP_EOL;
    } else {
        echo "ok column {$table}.{$column}" . PHP_EOL;
    }
}

function seed_missing_demo_credentials(PDO $pdo): void
{
    $customerCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM customers WHERE password_hash IS NOT NULL AND password_hash <> ''"
    )->fetchColumn();
    if ($customerCount === 0) {
        $memberPasswordHash = password_hash('123456', PASSWORD_DEFAULT);
        $memberPhones = ['0900000001', '0900000002', '0900000003', '0900000004', '0900000005', '0900000006'];
        $memberStmt = $pdo->prepare(
            "UPDATE customers
             SET password_hash = :password_hash
             WHERE phone_number = :phone_number"
        );
        foreach ($memberPhones as $phone) {
            $memberStmt->execute([
                'password_hash' => $memberPasswordHash,
                'phone_number' => $phone,
            ]);
        }
        echo "seeded member demo passwords" . PHP_EOL;
    } else {
        echo "ok member passwords" . PHP_EOL;
    }

    $staffCount = (int) $pdo->query(
        "SELECT COUNT(*) FROM staff WHERE password_hash IS NOT NULL AND password_hash <> '' AND pin_hash IS NOT NULL AND pin_hash <> ''"
    )->fetchColumn();
    if ($staffCount > 0) {
        echo "ok staff credentials" . PHP_EOL;
        return;
    }

    $staffCredentials = [
        'waiter.cg@cafeconnect.test' => ['code' => 'WAIT001', 'password' => 'waiter123', 'pin' => '1111'],
        'cashier.cg@cafeconnect.test' => ['code' => 'CASH001', 'password' => 'cashier123', 'pin' => '2222'],
        'barista.cg@cafeconnect.test' => ['code' => 'BAR001', 'password' => 'barista123', 'pin' => '3333'],
        'owner@cafeconnect.test' => ['code' => 'OWNER001', 'password' => 'owner123', 'pin' => '4444'],
        'marketing@cafeconnect.test' => ['code' => 'MKT001', 'password' => 'marketing123', 'pin' => '5555'],
        'admin@cafeconnect.test' => ['code' => 'ADMIN001', 'password' => 'admin123', 'pin' => '6666'],
        'manager.hk@cafeconnect.test' => ['code' => 'MGR001', 'password' => 'manager123', 'pin' => '7777'],
        'cashier.th@cafeconnect.test' => ['code' => 'CASH002', 'password' => 'cashier123', 'pin' => '2222'],
    ];
    $staffStmt = $pdo->prepare(
        "UPDATE staff
         SET staff_code = :staff_code,
             password_hash = :password_hash,
             pin_hash = :pin_hash
         WHERE email = :email"
    );
    foreach ($staffCredentials as $email => $credential) {
        $staffStmt->execute([
            'staff_code' => $credential['code'],
            'password_hash' => password_hash($credential['password'], PASSWORD_DEFAULT),
            'pin_hash' => password_hash($credential['pin'], PASSWORD_DEFAULT),
            'email' => $email,
        ]);
    }
    echo "seeded staff demo credentials" . PHP_EOL;
}

function seed_missing_operational_data(PDO $pdo): void
{
    $pdo->exec(
        "UPDATE inventory_materials
         SET unit_cost = CASE material_name
            WHEN 'Arabica beans' THEN 190000
            WHEN 'Robusta beans' THEN 120000
            WHEN 'Fresh milk' THEN 30000
            WHEN 'Tea leaves' THEN 160000
            WHEN 'Croissant dough' THEN 25000
            ELSE unit_cost
         END
         WHERE unit_cost = 0"
    );
    echo "checked material unit costs" . PHP_EOL;

    $recipeCount = (int) $pdo->query('SELECT COUNT(*) FROM recipes')->fetchColumn();
    if ($recipeCount === 0) {
        $recipes = [
            'Signature Brown Latte' => 'Signature Brown Latte recipe',
            'Vietnamese Phin Coffee' => 'Vietnamese Phin Coffee recipe',
            'Cold Brew Citrus' => 'Cold Brew Citrus recipe',
            'Lotus Oolong Tea' => 'Lotus Oolong Tea recipe',
            'Peach Lemongrass Tea' => 'Peach Lemongrass Tea recipe',
            'Mango Yogurt Smoothie' => 'Mango Yogurt Smoothie recipe',
            'Croissant Butter' => 'Croissant Butter recipe',
            'Tiramisu Cup' => 'Tiramisu Cup recipe',
            'May Bloom Macchiato' => 'May Bloom Macchiato recipe',
        ];
        $stmt = $pdo->prepare(
            "INSERT INTO recipes (product_id, recipe_name, yield_quantity, status)
             SELECT id, :recipe_name, 1, 'active'
             FROM products
             WHERE product_name = :product_name
             LIMIT 1"
        );
        foreach ($recipes as $productName => $recipeName) {
            $stmt->execute([
                'product_name' => $productName,
                'recipe_name' => $recipeName,
            ]);
        }
        echo "seeded recipes" . PHP_EOL;
    } else {
        echo "ok recipes" . PHP_EOL;
    }

    $recipeItemCount = (int) $pdo->query('SELECT COUNT(*) FROM recipe_items')->fetchColumn();
    if ($recipeItemCount === 0) {
        $items = [
            ['Signature Brown Latte', 'Arabica beans', 0.0180],
            ['Signature Brown Latte', 'Fresh milk', 0.1800],
            ['Vietnamese Phin Coffee', 'Robusta beans', 0.0200],
            ['Cold Brew Citrus', 'Arabica beans', 0.0200],
            ['Lotus Oolong Tea', 'Tea leaves', 0.0100],
            ['Peach Lemongrass Tea', 'Tea leaves', 0.0120],
            ['Mango Yogurt Smoothie', 'Fresh milk', 0.1200],
            ['Croissant Butter', 'Croissant dough', 1.0000],
            ['Tiramisu Cup', 'Fresh milk', 0.0800],
            ['May Bloom Macchiato', 'Arabica beans', 0.0180],
            ['May Bloom Macchiato', 'Fresh milk', 0.1500],
        ];
        $stmt = $pdo->prepare(
            "INSERT IGNORE INTO recipe_items (recipe_id, material_id, quantity_per_unit)
             SELECT r.id, m.id, :quantity_per_unit
             FROM recipes r
             JOIN products p ON p.id = r.product_id
             JOIN inventory_materials m ON m.material_name = :material_name
             WHERE p.product_name = :product_name
             LIMIT 1"
        );
        foreach ($items as [$productName, $materialName, $quantity]) {
            $stmt->execute([
                'product_name' => $productName,
                'material_name' => $materialName,
                'quantity_per_unit' => $quantity,
            ]);
        }
        echo "seeded recipe items" . PHP_EOL;
    } else {
        echo "ok recipe items" . PHP_EOL;
    }

    $pdo->exec(
        "INSERT IGNORE INTO website_orders (
            invoice_id, customer_id, fulfillment_type, order_status,
            delivery_address, customer_note, requested_at, created_at
         )
         SELECT id,
                customer_id,
                CASE WHEN sales_channel = 'delivery' THEN 'delivery' ELSE 'pickup' END,
                CASE WHEN status = 'pending' THEN 'pending' ELSE 'completed' END,
                CASE WHEN sales_channel = 'delivery' THEN 'Sample delivery address' ELSE NULL END,
                'Seeded from sample invoice',
                paid_at,
                created_at
         FROM invoices
         WHERE sales_channel IN ('website', 'delivery')"
    );
    echo "checked website order backfill" . PHP_EOL;
}

exec_if_missing_table($pdo, 'schema_migrations', "
    CREATE TABLE schema_migrations (
        id INT AUTO_INCREMENT PRIMARY KEY,
        migration_name VARCHAR(150) NOT NULL,
        applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_schema_migrations_name (migration_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

exec_if_missing_table($pdo, 'auth_lockouts', "
    CREATE TABLE auth_lockouts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        scope VARCHAR(60) NOT NULL,
        identity_hash CHAR(64) NOT NULL,
        identity_label VARCHAR(160) NULL,
        ip_address VARCHAR(64) NULL,
        user_agent VARCHAR(255) NULL,
        failed_attempts INT NOT NULL DEFAULT 0,
        locked_until DATETIME NULL,
        last_failed_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_auth_lockouts_scope_identity (scope, identity_hash),
        KEY idx_auth_lockouts_locked_until (locked_until),
        KEY idx_auth_lockouts_scope_updated (scope, updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

exec_if_missing_table($pdo, 'audit_logs', "
    CREATE TABLE audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        actor_type ENUM('customer', 'staff', 'system', 'guest') NOT NULL DEFAULT 'system',
        actor_id INT NULL,
        actor_role VARCHAR(40) NULL,
        action VARCHAR(80) NOT NULL,
        entity_type VARCHAR(80) NULL,
        entity_id INT NULL,
        ip_address VARCHAR(64) NULL,
        user_agent VARCHAR(255) NULL,
        metadata_json TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_audit_logs_actor (actor_type, actor_id, created_at),
        KEY idx_audit_logs_entity (entity_type, entity_id),
        KEY idx_audit_logs_action_date (action, created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

exec_if_missing_table($pdo, 'website_orders', "
    CREATE TABLE website_orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        customer_id INT NULL,
        fulfillment_type ENUM('pickup', 'delivery') NOT NULL DEFAULT 'pickup',
        order_status ENUM('pending', 'paid', 'preparing', 'ready', 'delivering', 'completed', 'cancelled') NOT NULL DEFAULT 'paid',
        delivery_address VARCHAR(255) NULL,
        customer_note VARCHAR(255) NULL,
        requested_at DATETIME NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_website_orders_invoice (invoice_id),
        KEY idx_website_orders_customer_status (customer_id, order_status),
        KEY idx_website_orders_status_created (order_status, created_at),
        CONSTRAINT fk_website_orders_invoice
            FOREIGN KEY (invoice_id) REFERENCES invoices(id)
            ON UPDATE CASCADE
            ON DELETE CASCADE,
        CONSTRAINT fk_website_orders_customer
            FOREIGN KEY (customer_id) REFERENCES customers(id)
            ON UPDATE CASCADE
            ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

exec_if_missing_table($pdo, 'invoice_refunds', "
    CREATE TABLE invoice_refunds (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        staff_id INT NOT NULL,
        pos_session_id INT NULL,
        refund_amount DECIMAL(12,2) NOT NULL,
        reason VARCHAR(255) NOT NULL,
        status ENUM('approved', 'rejected') NOT NULL DEFAULT 'approved',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY idx_invoice_refunds_invoice (invoice_id),
        KEY idx_invoice_refunds_staff_date (staff_id, created_at),
        CONSTRAINT fk_invoice_refunds_invoice
            FOREIGN KEY (invoice_id) REFERENCES invoices(id)
            ON UPDATE CASCADE
            ON DELETE CASCADE,
        CONSTRAINT fk_invoice_refunds_staff
            FOREIGN KEY (staff_id) REFERENCES staff(id)
            ON UPDATE CASCADE
            ON DELETE RESTRICT,
        CONSTRAINT fk_invoice_refunds_session
            FOREIGN KEY (pos_session_id) REFERENCES pos_sessions(id)
            ON UPDATE CASCADE
            ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

exec_if_missing_table($pdo, 'receipt_print_logs', "
    CREATE TABLE receipt_print_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        invoice_id INT NOT NULL,
        staff_id INT NULL,
        pos_session_id INT NULL,
        receipt_type ENUM('html', 'pdf', 'thermal') NOT NULL DEFAULT 'html',
        printed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        note VARCHAR(255) NULL,
        KEY idx_receipt_print_logs_invoice (invoice_id, printed_at),
        CONSTRAINT fk_receipt_print_logs_invoice
            FOREIGN KEY (invoice_id) REFERENCES invoices(id)
            ON UPDATE CASCADE
            ON DELETE CASCADE,
        CONSTRAINT fk_receipt_print_logs_staff
            FOREIGN KEY (staff_id) REFERENCES staff(id)
            ON UPDATE CASCADE
            ON DELETE SET NULL,
        CONSTRAINT fk_receipt_print_logs_session
            FOREIGN KEY (pos_session_id) REFERENCES pos_sessions(id)
            ON UPDATE CASCADE
            ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

add_column_if_missing($pdo, 'inventory_materials', 'unit_cost', 'unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER min_stock_level');
add_column_if_missing($pdo, 'stock_movements', 'unit_cost', 'unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER quantity');
add_column_if_missing($pdo, 'stock_movements', 'supplier_name', 'supplier_name VARCHAR(150) NULL AFTER total_amount');
add_column_if_missing($pdo, 'stock_movements', 'batch_code', 'batch_code VARCHAR(80) NULL AFTER supplier_name');
add_column_if_missing($pdo, 'stock_movements', 'expiry_date', 'expiry_date DATE NULL AFTER batch_code');

if (!enum_column_contains($pdo, 'service_order_items', 'kitchen_status', 'cancelled')) {
    $pdo->exec("ALTER TABLE service_order_items MODIFY kitchen_status ENUM('waiting', 'preparing', 'ready', 'served', 'cancelled') NOT NULL DEFAULT 'waiting'");
    echo "updated enum service_order_items.kitchen_status" . PHP_EOL;
} else {
    echo "ok enum service_order_items.kitchen_status" . PHP_EOL;
}

exec_if_missing_table($pdo, 'recipes', "
    CREATE TABLE recipes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        recipe_name VARCHAR(160) NOT NULL,
        yield_quantity DECIMAL(12,2) NOT NULL DEFAULT 1,
        status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_recipes_product (product_id),
        CONSTRAINT fk_recipes_product
            FOREIGN KEY (product_id) REFERENCES products(id)
            ON UPDATE CASCADE
            ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

exec_if_missing_table($pdo, 'recipe_items', "
    CREATE TABLE recipe_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        recipe_id INT NOT NULL,
        material_id INT NOT NULL,
        quantity_per_unit DECIMAL(12,4) NOT NULL,
        UNIQUE KEY uq_recipe_items_recipe_material (recipe_id, material_id),
        KEY idx_recipe_items_material (material_id),
        CONSTRAINT fk_recipe_items_recipe
            FOREIGN KEY (recipe_id) REFERENCES recipes(id)
            ON UPDATE CASCADE
            ON DELETE CASCADE,
        CONSTRAINT fk_recipe_items_material
            FOREIGN KEY (material_id) REFERENCES inventory_materials(id)
            ON UPDATE CASCADE
            ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
");

$pdo->exec("INSERT IGNORE INTO schema_migrations (migration_name) VALUES ('001_security_operations')");

seed_missing_demo_credentials($pdo);
seed_missing_operational_data($pdo);

echo "repair complete" . PHP_EOL;
