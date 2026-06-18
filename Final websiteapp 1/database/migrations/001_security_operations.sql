USE cafe_connect_crm;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS receipt_print_logs;
DROP TABLE IF EXISTS invoice_refunds;
DROP TABLE IF EXISTS recipe_items;
DROP TABLE IF EXISTS recipes;
DROP TABLE IF EXISTS website_orders;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS auth_lockouts;
DROP TABLE IF EXISTS schema_migrations;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE schema_migrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    migration_name VARCHAR(150) NOT NULL,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_schema_migrations_name (migration_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE website_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    customer_id INT NULL,
    fulfillment_type ENUM('pickup', 'delivery') NOT NULL DEFAULT 'pickup',
    order_status ENUM('pending', 'paid', 'preparing', 'ready', 'delivering', 'completed', 'cancelled') NOT NULL DEFAULT 'paid',
    receiver_email VARCHAR(150) NULL,
    receiver_name VARCHAR(150) NULL,
    receiver_phone VARCHAR(20) NULL,
    delivery_address VARCHAR(255) NULL,
    city VARCHAR(120) NULL,
    district VARCHAR(120) NULL,
    ward VARCHAR(120) NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE service_order_items
    MODIFY kitchen_status ENUM('waiting', 'preparing', 'ready', 'served', 'cancelled') NOT NULL DEFAULT 'waiting';

ALTER TABLE inventory_materials
    ADD COLUMN IF NOT EXISTS unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER min_stock_level;

ALTER TABLE stock_movements
    ADD COLUMN IF NOT EXISTS unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER quantity,
    ADD COLUMN IF NOT EXISTS supplier_name VARCHAR(150) NULL AFTER total_amount,
    ADD COLUMN IF NOT EXISTS batch_code VARCHAR(80) NULL AFTER supplier_name,
    ADD COLUMN IF NOT EXISTS expiry_date DATE NULL AFTER batch_code;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO schema_migrations (migration_name)
VALUES ('001_security_operations');
