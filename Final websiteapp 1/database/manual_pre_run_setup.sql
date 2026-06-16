-- Cafe Connect manual pre-run setup
-- Import this file after database/cafe_connect_schema.sql and before opening Website/POS.
-- It is safe to import more than once on XAMPP/MariaDB.

CREATE DATABASE IF NOT EXISTS cafe_connect_crm
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE cafe_connect_crm;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO branches (branch_name, address, district, status)
VALUES ('Coffee Connect số 1', 'Số 1 Trịnh Văn Bô, Nam Từ Liêm, Hà Nội', 'Trịnh Văn Bô', 'active')
ON DUPLICATE KEY UPDATE
    address = VALUES(address),
    district = VALUES(district),
    status = 'active';

-- 1) Safety columns for auth / campaign features on older databases.
ALTER TABLE customers
    ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL AFTER email,
    ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL AFTER password_hash;

ALTER TABLE staff
    ADD COLUMN IF NOT EXISTS staff_code VARCHAR(30) NULL AFTER branch_id,
    ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL AFTER email,
    ADD COLUMN IF NOT EXISTS pin_hash VARCHAR(255) NULL AFTER password_hash,
    ADD COLUMN IF NOT EXISTS last_login_at DATETIME NULL AFTER pin_hash;

ALTER TABLE promotions
    ADD COLUMN IF NOT EXISTS claim_code VARCHAR(50) NULL AFTER usage_limit_per_customer,
    ADD COLUMN IF NOT EXISTS distribution_type ENUM('auto_issue', 'claim_code') NOT NULL DEFAULT 'claim_code' AFTER claim_code;

ALTER TABLE promotions
    ADD UNIQUE KEY IF NOT EXISTS uq_promotions_claim_code (claim_code);

-- 2) Security / audit / operational tables.
CREATE TABLE IF NOT EXISTS schema_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration_name VARCHAR(150) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_schema_migrations_name (migration_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS auth_lockouts (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS audit_logs (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS customer_password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    request_ip VARCHAR(64) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_customer_password_resets_token (token_hash),
    KEY idx_customer_password_resets_customer (customer_id, expires_at),
    CONSTRAINT fk_customer_password_resets_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS website_orders (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS invoice_refunds (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS receipt_print_logs (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Inventory cost / recipe tables.
ALTER TABLE service_order_items
    MODIFY kitchen_status ENUM('waiting', 'preparing', 'ready', 'served', 'cancelled') NOT NULL DEFAULT 'waiting';

ALTER TABLE inventory_materials
    ADD COLUMN IF NOT EXISTS unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER min_stock_level;

ALTER TABLE stock_movements
    ADD COLUMN IF NOT EXISTS unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER quantity,
    ADD COLUMN IF NOT EXISTS supplier_name VARCHAR(150) NULL AFTER total_amount,
    ADD COLUMN IF NOT EXISTS batch_code VARCHAR(80) NULL AFTER supplier_name,
    ADD COLUMN IF NOT EXISTS expiry_date DATE NULL AFTER batch_code;

CREATE TABLE IF NOT EXISTS recipes (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS recipe_items (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4) Demo credentials. These hashes match the demo passwords documented in README.
UPDATE customers
SET password_hash = '$2y$10$Swt4eBM1OClADAmkFAL2C.Kr.r3xCmgUTUKOqPl4Nh1DXaY57X3ZO'
WHERE phone_number IN ('0900000001', '0900000002', '0900000003', '0900000004', '0900000005', '0900000006')
  AND (password_hash IS NULL OR password_hash = '');

UPDATE staff
SET staff_code = 'WAIT001',
    password_hash = '$2y$10$Ab5E5p/MRUYrX2KveFuRxOsnX2kxdyypr7yW4olj2wYHXEa7OX/Yi',
    pin_hash = '$2y$10$Xa1YpKLgzz7zrlOZEhuG1eJaXYJYRTJcM3QYFGLfdKmpP401HVpz.'
WHERE email = 'waiter.cg@cafeconnect.test';

UPDATE staff
SET staff_code = 'CASH001',
    password_hash = '$2y$10$oFqmdbthLOHnwOBy0A.UMOXUPFAQ0R3Uy2ZyeJc86rf8/XW0ND7EC',
    pin_hash = '$2y$10$74rMDDpDOwy2RL0RUlCsBOnGACbPQp9AQagLagFemyVdN7LS81MAa'
WHERE email = 'cashier.cg@cafeconnect.test';

UPDATE staff
SET staff_code = 'BAR001',
    password_hash = '$2y$10$UX2z6uCdt1xzkod2ZO1gbOWxBcJnOyM7TOSYHPUWoQzng.2GllXta',
    pin_hash = '$2y$10$jxd6oQN8/iczNI8Yib6DG.BKmJzpThg0M1kDy3gTtgduu.E.oi5DO'
WHERE email = 'barista.cg@cafeconnect.test';

UPDATE staff
SET staff_code = 'OWNER001',
    password_hash = '$2y$10$0dt90ZI/3i9w/59B9XytLuXGTfVoUfEWxZ6wtxuCkuLXgx1EWYwdm',
    pin_hash = '$2y$10$G1Ej2kZm5Y2S/At8pgMBVuOK1P3S4kxrPeQvrVvZowEnJ9ExuHK6K'
WHERE email = 'owner@cafeconnect.test';

UPDATE staff
SET staff_code = 'MKT001',
    password_hash = '$2y$10$BZlSOMWQgNWgJ5M3fDlR7.g60tnafZIAos1oWyrf6VoF4wCVl5jx6',
    pin_hash = '$2y$10$yVbNAe6/PjLwGrzUnsAQk.ArGpoKq4.k1Qyv09XdF0GtLQ24dkCgC'
WHERE email = 'marketing@cafeconnect.test';

UPDATE staff
SET staff_code = 'ADMIN001',
    password_hash = '$2y$10$VFswGSv.vC385/EctXDTLeXDmrP7AWut5e6XKTIOa8o4sXvMnkvEK',
    pin_hash = '$2y$10$N.RNnsx6eDmJb37cVYQewuTOhlPUytwpE9CyHk2TPdAXRexpbNjYW'
WHERE email = 'admin@cafeconnect.test';

UPDATE staff
SET staff_code = 'MGR001',
    password_hash = '$2y$10$tJ/jx1BHmu3zuSksDHiYgecgP6.ciXtbyabvFRnplaiktWT9J0vIu',
    pin_hash = '$2y$10$a9JQfEvPu.B41gGVhn22BuWlevlLhRNKIgsWXQstt0/lX0dfuiX1u'
WHERE email = 'manager.hk@cafeconnect.test';

UPDATE staff
SET staff_code = 'CASH002',
    password_hash = '$2y$10$oFqmdbthLOHnwOBy0A.UMOXUPFAQ0R3Uy2ZyeJc86rf8/XW0ND7EC',
    pin_hash = '$2y$10$74rMDDpDOwy2RL0RUlCsBOnGACbPQp9AQagLagFemyVdN7LS81MAa'
WHERE email = 'cashier.th@cafeconnect.test';

-- 5) Cost, recipe/BOM, and website order seed data.
UPDATE inventory_materials
SET unit_cost = CASE material_name
    WHEN 'Arabica beans' THEN 190000
    WHEN 'Robusta beans' THEN 120000
    WHEN 'Fresh milk' THEN 30000
    WHEN 'Tea leaves' THEN 160000
    WHEN 'Croissant dough' THEN 25000
    ELSE unit_cost
END
WHERE unit_cost = 0;

INSERT INTO recipes (product_id, recipe_name, yield_quantity, status)
SELECT p.id, CONCAT(p.product_name, ' recipe'), 1, 'active'
FROM products p
WHERE p.product_name IN (
    'Signature Brown Latte',
    'Vietnamese Phin Coffee',
    'Cold Brew Citrus',
    'Lotus Oolong Tea',
    'Peach Lemongrass Tea',
    'Mango Yogurt Smoothie',
    'Croissant Butter',
    'Tiramisu Cup',
    'May Bloom Macchiato'
)
ON DUPLICATE KEY UPDATE
    recipe_name = VALUES(recipe_name),
    status = 'active';

INSERT IGNORE INTO recipe_items (recipe_id, material_id, quantity_per_unit)
SELECT r.id, m.id, x.quantity_per_unit
FROM (
    SELECT 'Signature Brown Latte' AS product_name, 'Arabica beans' AS material_name, 0.0180 AS quantity_per_unit
    UNION ALL SELECT 'Signature Brown Latte', 'Fresh milk', 0.1800
    UNION ALL SELECT 'Vietnamese Phin Coffee', 'Robusta beans', 0.0200
    UNION ALL SELECT 'Cold Brew Citrus', 'Arabica beans', 0.0200
    UNION ALL SELECT 'Lotus Oolong Tea', 'Tea leaves', 0.0100
    UNION ALL SELECT 'Peach Lemongrass Tea', 'Tea leaves', 0.0120
    UNION ALL SELECT 'Mango Yogurt Smoothie', 'Fresh milk', 0.1200
    UNION ALL SELECT 'Croissant Butter', 'Croissant dough', 1.0000
    UNION ALL SELECT 'Tiramisu Cup', 'Fresh milk', 0.0800
    UNION ALL SELECT 'May Bloom Macchiato', 'Arabica beans', 0.0180
    UNION ALL SELECT 'May Bloom Macchiato', 'Fresh milk', 0.1500
) x
JOIN products p ON p.product_name = x.product_name
JOIN recipes r ON r.product_id = p.id
JOIN inventory_materials m ON m.material_name = x.material_name;

INSERT IGNORE INTO website_orders (
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
WHERE sales_channel IN ('website', 'delivery');

INSERT IGNORE INTO schema_migrations (migration_name)
VALUES ('001_security_operations'), ('001_recipe_seed'), ('manual_pre_run_setup');
