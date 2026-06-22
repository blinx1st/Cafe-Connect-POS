-- Cafe Connect manual pre-run setup
-- Import this file after database/cafe_connect_schema.sql and before opening Website/POS.
-- It is safe to import more than once on XAMPP/MariaDB.

CREATE DATABASE IF NOT EXISTS cafe_connect_crm
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE cafe_connect_crm;

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO branches (branch_name, address, district, status)
VALUES
    ('Coffee Connect - Cầu Giấy', '144 Xuân Thủy, Cầu Giấy, Hà Nội', 'Cầu Giấy', 'active'),
    ('Coffee Connect - Hoàn Kiếm', '25 Hàng Bài, Hoàn Kiếm, Hà Nội', 'Hoàn Kiếm', 'active'),
    ('Coffee Connect - Tây Hồ', '45 Xuân Diệu, Tây Hồ, Hà Nội', 'Tây Hồ', 'active'),
    ('Coffee Connect - Số 1', 'Số 1 Trịnh Văn Bô, Nam Từ Liêm, Hà Nội', 'Trịnh Văn Bô', 'active')
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

INSERT INTO staff (branch_id, staff_code, staff_name, staff_role, phone_number, email, password_hash, pin_hash, status)
SELECT b.id, x.staff_code, x.staff_name, x.staff_role, x.phone_number, x.email, x.password_hash, x.pin_hash, 'active'
FROM (
    SELECT 'Coffee Connect - Hoàn Kiếm' AS branch_name, 'WAIT002' AS staff_code, 'Hoa Waiter HK' AS staff_name, 'waiter' AS staff_role, '0911000009' AS phone_number, 'waiter.hk@cafeconnect.test' AS email,
           '$2y$10$Ab5E5p/MRUYrX2KveFuRxOsnX2kxdyypr7yW4olj2wYHXEa7OX/Yi' AS password_hash, '$2y$10$Xa1YpKLgzz7zrlOZEhuG1eJaXYJYRTJcM3QYFGLfdKmpP401HVpz.' AS pin_hash
    UNION ALL SELECT 'Coffee Connect - Hoàn Kiếm', 'BAR002', 'Phuc Barista HK', 'barista', '0911000010', 'barista.hk@cafeconnect.test',
           '$2y$10$UX2z6uCdt1xzkod2ZO1gbOWxBcJnOyM7TOSYHPUWoQzng.2GllXta', '$2y$10$jxd6oQN8/iczNI8Yib6DG.BKmJzpThg0M1kDy3gTtgduu.E.oi5DO'
    UNION ALL SELECT 'Coffee Connect - Hoàn Kiếm', 'CASH003', 'Vy Cashier HK', 'cashier', '0911000018', 'cashier.hk@cafeconnect.test',
           '$2y$10$oFqmdbthLOHnwOBy0A.UMOXUPFAQ0R3Uy2ZyeJc86rf8/XW0ND7EC', '$2y$10$74rMDDpDOwy2RL0RUlCsBOnGACbPQp9AQagLagFemyVdN7LS81MAa'
    UNION ALL SELECT 'Coffee Connect - Tây Hồ', 'WAIT003', 'Linh Waiter TH', 'waiter', '0911000011', 'waiter.th@cafeconnect.test',
           '$2y$10$Ab5E5p/MRUYrX2KveFuRxOsnX2kxdyypr7yW4olj2wYHXEa7OX/Yi', '$2y$10$Xa1YpKLgzz7zrlOZEhuG1eJaXYJYRTJcM3QYFGLfdKmpP401HVpz.'
    UNION ALL SELECT 'Coffee Connect - Tây Hồ', 'BAR003', 'Son Barista TH', 'barista', '0911000012', 'barista.th@cafeconnect.test',
           '$2y$10$UX2z6uCdt1xzkod2ZO1gbOWxBcJnOyM7TOSYHPUWoQzng.2GllXta', '$2y$10$jxd6oQN8/iczNI8Yib6DG.BKmJzpThg0M1kDy3gTtgduu.E.oi5DO'
    UNION ALL SELECT 'Coffee Connect - Tây Hồ', 'MGR003', 'Hanh Manager TH', 'manager', '0911000013', 'manager.th@cafeconnect.test',
           '$2y$10$tJ/jx1BHmu3zuSksDHiYgecgP6.ciXtbyabvFRnplaiktWT9J0vIu', '$2y$10$a9JQfEvPu.B41gGVhn22BuWlevlLhRNKIgsWXQstt0/lX0dfuiX1u'
    UNION ALL SELECT 'Coffee Connect - Số 1', 'WAIT004', 'An Waiter TVB', 'waiter', '0911000014', 'waiter.tvb@cafeconnect.test',
           '$2y$10$Ab5E5p/MRUYrX2KveFuRxOsnX2kxdyypr7yW4olj2wYHXEa7OX/Yi', '$2y$10$Xa1YpKLgzz7zrlOZEhuG1eJaXYJYRTJcM3QYFGLfdKmpP401HVpz.'
    UNION ALL SELECT 'Coffee Connect - Số 1', 'CASH004', 'Binh Cashier TVB', 'cashier', '0911000015', 'cashier.tvb@cafeconnect.test',
           '$2y$10$oFqmdbthLOHnwOBy0A.UMOXUPFAQ0R3Uy2ZyeJc86rf8/XW0ND7EC', '$2y$10$74rMDDpDOwy2RL0RUlCsBOnGACbPQp9AQagLagFemyVdN7LS81MAa'
    UNION ALL SELECT 'Coffee Connect - Số 1', 'BAR004', 'Khoa Barista TVB', 'barista', '0911000016', 'barista.tvb@cafeconnect.test',
           '$2y$10$UX2z6uCdt1xzkod2ZO1gbOWxBcJnOyM7TOSYHPUWoQzng.2GllXta', '$2y$10$jxd6oQN8/iczNI8Yib6DG.BKmJzpThg0M1kDy3gTtgduu.E.oi5DO'
    UNION ALL SELECT 'Coffee Connect - Số 1', 'MGR004', 'Trang Manager TVB', 'manager', '0911000017', 'manager.tvb@cafeconnect.test',
           '$2y$10$tJ/jx1BHmu3zuSksDHiYgecgP6.ciXtbyabvFRnplaiktWT9J0vIu', '$2y$10$a9JQfEvPu.B41gGVhn22BuWlevlLhRNKIgsWXQstt0/lX0dfuiX1u'
) x
JOIN branches b ON b.branch_name = x.branch_name
ON DUPLICATE KEY UPDATE
    branch_id = VALUES(branch_id),
    staff_name = VALUES(staff_name),
    staff_role = VALUES(staff_role),
    phone_number = VALUES(phone_number),
    email = VALUES(email),
    password_hash = VALUES(password_hash),
    pin_hash = VALUES(pin_hash),
    status = 'active';

INSERT INTO staff_shifts (staff_id, shift_name, starts_at, ends_at, work_date)
SELECT s.id, x.shift_name, x.starts_at, x.ends_at, x.work_date
FROM (
    SELECT 'WAIT002' AS staff_code, 'Hoan Kiem floor' AS shift_name, '08:00:00' AS starts_at, '16:00:00' AS ends_at, '2026-05-13' AS work_date
    UNION ALL SELECT 'BAR002', 'Hoan Kiem bar', '08:00:00', '16:00:00', '2026-05-13'
    UNION ALL SELECT 'CASH003', 'Hoan Kiem cashier', '13:00:00', '21:00:00', '2026-05-13'
    UNION ALL SELECT 'WAIT003', 'Tay Ho floor', '09:00:00', '17:00:00', '2026-05-13'
    UNION ALL SELECT 'BAR003', 'Tay Ho bar', '09:00:00', '17:00:00', '2026-05-13'
    UNION ALL SELECT 'MGR003', 'Tay Ho manager', '13:00:00', '21:00:00', '2026-05-13'
    UNION ALL SELECT 'WAIT004', 'Trinh Van Bo floor', '07:00:00', '15:00:00', '2026-05-13'
    UNION ALL SELECT 'CASH004', 'Trinh Van Bo cashier', '07:00:00', '15:00:00', '2026-05-13'
    UNION ALL SELECT 'BAR004', 'Trinh Van Bo bar', '07:00:00', '15:00:00', '2026-05-13'
    UNION ALL SELECT 'MGR004', 'Trinh Van Bo manager', '13:00:00', '21:00:00', '2026-05-13'
) x
JOIN staff s ON s.staff_code = x.staff_code
WHERE NOT EXISTS (
    SELECT 1
    FROM staff_shifts ss
    WHERE ss.staff_id = s.id AND ss.shift_name = x.shift_name AND ss.work_date = x.work_date
);

INSERT INTO dining_tables (branch_id, table_name, area_name, seat_count, status)
SELECT b.id, x.table_name, x.area_name, x.seat_count, x.status
FROM (
    SELECT 'Coffee Connect - Hoàn Kiếm' AS branch_name, 'H03' AS table_name, 'Balcony' AS area_name, 2 AS seat_count, 'available' AS status
    UNION ALL SELECT 'Coffee Connect - Hoàn Kiếm', 'H04', 'Main', 6, 'available'
    UNION ALL SELECT 'Coffee Connect - Hoàn Kiếm', 'H05', 'Main', 4, 'available'
    UNION ALL SELECT 'Coffee Connect - Tây Hồ', 'W02', 'Lake', 4, 'occupied'
    UNION ALL SELECT 'Coffee Connect - Tây Hồ', 'W03', 'Main', 4, 'available'
    UNION ALL SELECT 'Coffee Connect - Tây Hồ', 'W04', 'Terrace', 6, 'available'
    UNION ALL SELECT 'Coffee Connect - Số 1', 'B01', 'Ground floor', 2, 'available'
    UNION ALL SELECT 'Coffee Connect - Số 1', 'B02', 'Ground floor', 4, 'occupied'
    UNION ALL SELECT 'Coffee Connect - Số 1', 'B03', 'Study corner', 4, 'available'
    UNION ALL SELECT 'Coffee Connect - Số 1', 'B04', 'Balcony', 6, 'available'
    UNION ALL SELECT 'Coffee Connect - Số 1', 'B05', 'Takeaway', 2, 'available'
) x
JOIN branches b ON b.branch_name = x.branch_name
ON DUPLICATE KEY UPDATE
    area_name = VALUES(area_name),
    seat_count = VALUES(seat_count),
    status = VALUES(status);

ALTER TABLE promotions
    ADD COLUMN IF NOT EXISTS claim_code VARCHAR(50) NULL AFTER usage_limit_per_customer,
    ADD COLUMN IF NOT EXISTS distribution_type ENUM('auto_issue', 'claim_code') NOT NULL DEFAULT 'claim_code' AFTER claim_code;

ALTER TABLE promotions
    ADD UNIQUE KEY IF NOT EXISTS uq_promotions_claim_code (claim_code);

CREATE TABLE IF NOT EXISTS product_size_prices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    size ENUM('S', 'M', 'L') NOT NULL,
    price DECIMAL(12,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_product_size_prices_product_size (product_id, size),
    CONSTRAINT fk_product_size_prices_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO product_size_prices (product_id, size, price)
SELECT p.id, x.size_code, x.price
FROM (
    SELECT 'Signature Brown Latte' AS product_name, 'S' AS size_code, 49000 AS price
    UNION ALL SELECT 'Signature Brown Latte', 'M', 55000
    UNION ALL SELECT 'Signature Brown Latte', 'L', 62000
    UNION ALL SELECT 'Vietnamese Phin Coffee', 'S', 30000
    UNION ALL SELECT 'Vietnamese Phin Coffee', 'M', 35000
    UNION ALL SELECT 'Vietnamese Phin Coffee', 'L', 42000
    UNION ALL SELECT 'Cold Brew Citrus', 'S', 54000
    UNION ALL SELECT 'Cold Brew Citrus', 'M', 60000
    UNION ALL SELECT 'Cold Brew Citrus', 'L', 68000
    UNION ALL SELECT 'Lotus Oolong Tea', 'S', 40000
    UNION ALL SELECT 'Lotus Oolong Tea', 'M', 45000
    UNION ALL SELECT 'Lotus Oolong Tea', 'L', 52000
    UNION ALL SELECT 'Peach Lemongrass Tea', 'S', 43000
    UNION ALL SELECT 'Peach Lemongrass Tea', 'M', 48000
    UNION ALL SELECT 'Peach Lemongrass Tea', 'L', 55000
    UNION ALL SELECT 'Mango Yogurt Smoothie', 'S', 59000
    UNION ALL SELECT 'Mango Yogurt Smoothie', 'M', 65000
    UNION ALL SELECT 'Mango Yogurt Smoothie', 'L', 73000
    UNION ALL SELECT 'Croissant Butter', 'S', 42000
    UNION ALL SELECT 'Croissant Butter', 'M', 42000
    UNION ALL SELECT 'Croissant Butter', 'L', 42000
    UNION ALL SELECT 'Tiramisu Cup', 'S', 58000
    UNION ALL SELECT 'Tiramisu Cup', 'M', 58000
    UNION ALL SELECT 'Tiramisu Cup', 'L', 58000
    UNION ALL SELECT 'May Bloom Macchiato', 'S', 62000
    UNION ALL SELECT 'May Bloom Macchiato', 'M', 68000
    UNION ALL SELECT 'May Bloom Macchiato', 'L', 76000
) x
JOIN products p ON p.product_name = x.product_name
ON DUPLICATE KEY UPDATE
    price = VALUES(price);

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

ALTER TABLE website_orders
    ADD COLUMN IF NOT EXISTS receiver_email VARCHAR(150) NULL AFTER order_status,
    ADD COLUMN IF NOT EXISTS receiver_name VARCHAR(150) NULL AFTER receiver_email,
    ADD COLUMN IF NOT EXISTS receiver_phone VARCHAR(20) NULL AFTER receiver_name,
    ADD COLUMN IF NOT EXISTS city VARCHAR(120) NULL AFTER delivery_address,
    ADD COLUMN IF NOT EXISTS district VARCHAR(120) NULL AFTER city,
    ADD COLUMN IF NOT EXISTS ward VARCHAR(120) NULL AFTER district;

CREATE TABLE IF NOT EXISTS invoice_refunds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    staff_id INT NOT NULL,
    pos_session_id INT NULL,
    refund_amount DECIMAL(12,2) NOT NULL,
    refund_type ENUM('full', 'partial') NOT NULL DEFAULT 'full',
    refund_method ENUM('cash', 'card', 'e_wallet') NOT NULL DEFAULT 'cash',
    refund_reference VARCHAR(120) NULL,
    reason_code VARCHAR(40) NOT NULL DEFAULT 'other',
    reason VARCHAR(255) NOT NULL,
    note VARCHAR(500) NULL,
    external_refund_confirmed TINYINT(1) NOT NULL DEFAULT 0,
    inventory_disposition ENUM('waste', 'restock', 'none') NOT NULL DEFAULT 'waste',
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

ALTER TABLE invoices
    MODIFY status ENUM('pending', 'paid', 'partially_refunded', 'cancelled', 'refunded') NOT NULL DEFAULT 'paid';

ALTER TABLE payments
    MODIFY status ENUM('pending', 'paid', 'partially_refunded', 'failed', 'refunded') NOT NULL DEFAULT 'paid';

ALTER TABLE invoice_refunds
    ADD COLUMN IF NOT EXISTS refund_type ENUM('full', 'partial') NOT NULL DEFAULT 'full' AFTER refund_amount,
    ADD COLUMN IF NOT EXISTS refund_method ENUM('cash', 'card', 'e_wallet') NOT NULL DEFAULT 'cash' AFTER refund_type,
    ADD COLUMN IF NOT EXISTS refund_reference VARCHAR(120) NULL AFTER refund_method,
    ADD COLUMN IF NOT EXISTS reason_code VARCHAR(40) NOT NULL DEFAULT 'other' AFTER refund_reference,
    ADD COLUMN IF NOT EXISTS note VARCHAR(500) NULL AFTER reason,
    ADD COLUMN IF NOT EXISTS external_refund_confirmed TINYINT(1) NOT NULL DEFAULT 0 AFTER note,
    ADD COLUMN IF NOT EXISTS inventory_disposition ENUM('waste', 'restock', 'none') NOT NULL DEFAULT 'waste' AFTER external_refund_confirmed;

ALTER TABLE cash_transactions
    ADD COLUMN IF NOT EXISTS invoice_id INT NULL AFTER pos_session_id,
    ADD COLUMN IF NOT EXISTS invoice_refund_id INT NULL AFTER invoice_id,
    ADD KEY IF NOT EXISTS idx_cash_transactions_invoice (invoice_id),
    ADD UNIQUE KEY IF NOT EXISTS uq_cash_transactions_refund (invoice_refund_id);

SET @add_cash_invoice_fk = IF(
    EXISTS(
        SELECT 1 FROM information_schema.referential_constraints
        WHERE constraint_schema = DATABASE()
          AND table_name = 'cash_transactions'
          AND constraint_name = 'fk_cash_transactions_invoice'
    ),
    'SELECT 1',
    'ALTER TABLE cash_transactions ADD CONSTRAINT fk_cash_transactions_invoice FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON UPDATE CASCADE ON DELETE SET NULL'
);
PREPARE add_cash_invoice_fk_stmt FROM @add_cash_invoice_fk;
EXECUTE add_cash_invoice_fk_stmt;
DEALLOCATE PREPARE add_cash_invoice_fk_stmt;

SET @add_cash_refund_fk = IF(
    EXISTS(
        SELECT 1 FROM information_schema.referential_constraints
        WHERE constraint_schema = DATABASE()
          AND table_name = 'cash_transactions'
          AND constraint_name = 'fk_cash_transactions_refund'
    ),
    'SELECT 1',
    'ALTER TABLE cash_transactions ADD CONSTRAINT fk_cash_transactions_refund FOREIGN KEY (invoice_refund_id) REFERENCES invoice_refunds(id) ON UPDATE CASCADE ON DELETE SET NULL'
);
PREPARE add_cash_refund_fk_stmt FROM @add_cash_refund_fk;
EXECUTE add_cash_refund_fk_stmt;
DEALLOCATE PREPARE add_cash_refund_fk_stmt;

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

ALTER TABLE payments
    MODIFY paid_at DATETIME NULL;

CREATE TABLE IF NOT EXISTS payment_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NULL,
    invoice_id INT NOT NULL,
    provider VARCHAR(40) NOT NULL DEFAULT 'momo',
    provider_order_id VARCHAR(200) NOT NULL,
    provider_request_id VARCHAR(80) NOT NULL,
    provider_transaction_id VARCHAR(80) NULL,
    amount DECIMAL(12,2) NOT NULL,
    pay_url TEXT NULL,
    deeplink TEXT NULL,
    qr_code_url TEXT NULL,
    result_code INT NULL,
    message VARCHAR(255) NULL,
    status ENUM('created', 'pending', 'paid', 'failed', 'cancelled') NOT NULL DEFAULT 'created',
    raw_request_json TEXT NULL,
    raw_response_json TEXT NULL,
    raw_ipn_json TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_payment_transactions_order (provider_order_id),
    KEY idx_payment_transactions_invoice (invoice_id, created_at),
    KEY idx_payment_transactions_payment (payment_id),
    CONSTRAINT fk_payment_transactions_payment
        FOREIGN KEY (payment_id) REFERENCES payments(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_payment_transactions_invoice
        FOREIGN KEY (invoice_id) REFERENCES invoices(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3) Inventory cost / recipe tables.
ALTER TABLE service_order_items
    MODIFY kitchen_status ENUM('waiting', 'preparing', 'ready', 'served', 'cancelled') NOT NULL DEFAULT 'waiting';

ALTER TABLE inventory_materials
    ADD COLUMN IF NOT EXISTS unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER min_stock_level;

ALTER TABLE stock_movements
    MODIFY movement_type ENUM('import', 'sales_export', 'waste_export', 'adjustment') NOT NULL,
    ADD COLUMN IF NOT EXISTS unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0 AFTER quantity,
    ADD COLUMN IF NOT EXISTS supplier_name VARCHAR(150) NULL AFTER total_amount,
    ADD COLUMN IF NOT EXISTS batch_code VARCHAR(80) NULL AFTER supplier_name,
    ADD COLUMN IF NOT EXISTS expiry_date DATE NULL AFTER batch_code;

CREATE TABLE IF NOT EXISTS branch_material_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    material_id INT NOT NULL,
    stock_quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
    min_stock_level DECIMAL(12,2) NOT NULL DEFAULT 0,
    unit_cost DECIMAL(12,2) NOT NULL DEFAULT 0,
    last_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_branch_material_inventory (branch_id, material_id),
    KEY idx_branch_material_inventory_material (material_id),
    CONSTRAINT fk_branch_material_inventory_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_branch_material_inventory_material
        FOREIGN KEY (material_id) REFERENCES inventory_materials(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
INSERT IGNORE INTO branch_inventory (branch_id, product_id, stock_quantity, min_stock_level, last_updated)
SELECT b.id, p.id,
       CASE
           WHEN p.product_name IN ('Signature Brown Latte', 'Vietnamese Phin Coffee', 'Cold Brew Citrus', 'Lotus Oolong Tea', 'Peach Lemongrass Tea') THEN 30
           ELSE 20
       END,
       CASE
           WHEN p.product_name IN ('Signature Brown Latte', 'Vietnamese Phin Coffee') THEN 20
           ELSE 10
       END,
       NOW()
FROM branches b
CROSS JOIN products p
WHERE b.branch_name LIKE '%Số 1%' OR b.address LIKE '%Trịnh Văn Bô%';

INSERT IGNORE INTO branch_inventory (branch_id, product_id, stock_quantity, min_stock_level, last_updated)
SELECT b.id,
       p.id,
       24,
       CASE WHEN p.category IN ('coffee', 'tea') THEN 12 ELSE 8 END,
       NOW()
FROM branches b
CROSS JOIN products p;

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

INSERT INTO branch_material_inventory (
    branch_id, material_id, stock_quantity, min_stock_level, unit_cost, last_updated
)
SELECT b.id,
       im.id,
       im.stock_quantity,
       im.min_stock_level,
       im.unit_cost,
       NOW()
FROM branches b
CROSS JOIN inventory_materials im
WHERE 1 = 1
ON DUPLICATE KEY UPDATE
    min_stock_level = VALUES(min_stock_level),
    unit_cost = VALUES(unit_cost),
    last_updated = branch_material_inventory.last_updated;

UPDATE branch_material_inventory bmi
JOIN inventory_materials im ON im.id = bmi.material_id
JOIN branches b ON b.id = bmi.branch_id
SET bmi.stock_quantity = CASE
        WHEN b.branch_name = 'Coffee Connect - Cầu Giấy' AND im.material_name = 'Robusta beans' THEN 16
        WHEN b.branch_name = 'Coffee Connect - Cầu Giấy' AND im.material_name = 'Croissant dough' THEN 9
        WHEN b.branch_name = 'Coffee Connect - Hoàn Kiếm' AND im.material_name = 'Arabica beans' THEN 28
        WHEN b.branch_name = 'Coffee Connect - Hoàn Kiếm' AND im.material_name = 'Fresh milk' THEN 62
        WHEN b.branch_name = 'Coffee Connect - Hoàn Kiếm' AND im.material_name = 'Tea leaves' THEN 18
        WHEN b.branch_name = 'Coffee Connect - Tây Hồ' AND im.material_name = 'Arabica beans' THEN 18
        WHEN b.branch_name = 'Coffee Connect - Tây Hồ' AND im.material_name = 'Robusta beans' THEN 14
        WHEN b.branch_name = 'Coffee Connect - Tây Hồ' AND im.material_name = 'Fresh milk' THEN 48
        WHEN b.branch_name = 'Coffee Connect - Số 1' AND im.material_name = 'Arabica beans' THEN 36
        WHEN b.branch_name = 'Coffee Connect - Số 1' AND im.material_name = 'Robusta beans' THEN 22
        WHEN b.branch_name = 'Coffee Connect - Số 1' AND im.material_name = 'Croissant dough' THEN 16
        ELSE bmi.stock_quantity
    END,
    bmi.last_updated = NOW();

INSERT IGNORE INTO stock_movements (
    movement_code, material_id, branch_id, staff_id, pos_session_id, movement_type,
    quantity, total_amount, note, created_at
)
SELECT x.movement_code, im.id, b.id, s.id, NULL, x.movement_type,
       x.quantity, x.total_amount, x.note, x.created_at
FROM (
    SELECT 'IM-004' AS movement_code, 'Coffee Connect - Hoàn Kiếm' AS branch_name, 'MGR001' AS staff_code, 'Arabica beans' AS material_name, 'import' AS movement_type, 12 AS quantity, 2280000 AS total_amount, 'Hoan Kiem bean replenishment.' AS note, '2026-05-12 08:20:00' AS created_at
    UNION ALL SELECT 'SA-005', 'Coffee Connect - Hoàn Kiếm', 'BAR002', 'Fresh milk', 'sales_export', 8, 0, 'Milk used by Hoan Kiem bar.', '2026-05-13 11:45:00'
    UNION ALL SELECT 'IM-006', 'Coffee Connect - Tây Hồ', 'MGR003', 'Tea leaves', 'import', 6, 960000, 'Tay Ho tea stock import.', '2026-05-12 09:10:00'
    UNION ALL SELECT 'WA-007', 'Coffee Connect - Tây Hồ', 'BAR003', 'Fresh milk', 'waste_export', 3, 0, 'Expired milk bottles.', '2026-05-13 14:00:00'
    UNION ALL SELECT 'IM-008', 'Coffee Connect - Số 1', 'MGR004', 'Robusta beans', 'import', 18, 2160000, 'Trinh Van Bo robusta import.', '2026-05-12 08:45:00'
    UNION ALL SELECT 'SA-009', 'Coffee Connect - Số 1', 'BAR004', 'Arabica beans', 'sales_export', 10, 0, 'Arabica used by Trinh Van Bo bar.', '2026-05-13 12:25:00'
) x
JOIN branches b ON b.branch_name = x.branch_name
JOIN staff s ON s.staff_code = x.staff_code
JOIN inventory_materials im ON im.material_name = x.material_name;

INSERT INTO cash_transactions (branch_id, staff_id, pos_session_id, transaction_type, reason, amount, created_at)
SELECT b.id, s.id, NULL, x.transaction_type, x.reason, x.amount, x.created_at
FROM (
    SELECT 'Coffee Connect - Hoàn Kiếm' AS branch_name, 'MGR001' AS staff_code, 'in' AS transaction_type, 'Hoan Kiem opening float' AS reason, 1500000 AS amount, '2026-05-13 08:00:00' AS created_at
    UNION ALL SELECT 'Coffee Connect - Hoàn Kiếm', 'MGR001', 'out', 'Hoan Kiem local delivery fee', 90000, '2026-05-13 12:10:00'
    UNION ALL SELECT 'Coffee Connect - Tây Hồ', 'CASH002', 'in', 'Tay Ho opening float', 1200000, '2026-05-13 09:00:00'
    UNION ALL SELECT 'Coffee Connect - Tây Hồ', 'CASH002', 'out', 'Tay Ho ice purchase', 65000, '2026-05-13 13:20:00'
    UNION ALL SELECT 'Coffee Connect - Số 1', 'CASH004', 'in', 'Trinh Van Bo opening float', 1000000, '2026-05-13 07:00:00'
    UNION ALL SELECT 'Coffee Connect - Số 1', 'CASH004', 'out', 'Trinh Van Bo packaging purchase', 110000, '2026-05-13 10:30:00'
) x
JOIN branches b ON b.branch_name = x.branch_name
JOIN staff s ON s.staff_code = x.staff_code
WHERE NOT EXISTS (
    SELECT 1
    FROM cash_transactions ct
    WHERE ct.branch_id = b.id
      AND ct.staff_id = s.id
      AND ct.reason = x.reason
      AND ct.created_at = x.created_at
);

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

SET @tvb_branch_id := (SELECT id FROM branches WHERE branch_name = 'Coffee Connect - Số 1' LIMIT 1);
SET @tvb_cashier_id := (SELECT id FROM staff WHERE staff_code = 'CASH004' LIMIT 1);
SET @tvb_customer_id := (SELECT id FROM customers WHERE phone_number = '0900000004' LIMIT 1);
SET @tvb_pending_invoice_id := (
    SELECT id
    FROM invoices
    WHERE branch_id = @tvb_branch_id
      AND sales_channel = 'website'
      AND payment_method = 'cash'
      AND status = 'pending'
    LIMIT 1
);

INSERT INTO invoices (
    branch_id, staff_id, pos_session_id, service_order_id, customer_id, voucher_id, sales_channel,
    invoice_date, invoice_time, bill_started_at, paid_at, subtotal_amount, membership_discount_amount,
    voucher_discount_amount, total_amount, points_earned, payment_method, status, created_at
)
SELECT @tvb_branch_id, @tvb_cashier_id, NULL, NULL, @tvb_customer_id, NULL, 'website',
       '2026-05-13', '11:15:00', '2026-05-13 11:15:00', NULL, 139000, 0,
       0, 139000, 0, 'cash', 'pending', '2026-05-13 11:15:00'
WHERE @tvb_branch_id IS NOT NULL
  AND @tvb_cashier_id IS NOT NULL
  AND @tvb_pending_invoice_id IS NULL;

SET @tvb_pending_invoice_id := COALESCE(@tvb_pending_invoice_id, LAST_INSERT_ID());

INSERT INTO payments (invoice_id, payment_method, payment_provider, amount, paid_at, transaction_reference, status)
SELECT @tvb_pending_invoice_id, 'cash', 'COD', 139000, NULL, 'WEB-TVB-SAMPLE', 'pending'
WHERE @tvb_pending_invoice_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM payments p WHERE p.invoice_id = @tvb_pending_invoice_id
  );

INSERT INTO invoice_details (invoice_id, product_id, quantity, unit_price, size, topping, line_total)
SELECT @tvb_pending_invoice_id, p.id, x.quantity, x.unit_price, x.size, x.topping, x.line_total
FROM (
    SELECT 'Signature Brown Latte' AS product_name, 1 AS quantity, 55000 AS unit_price, 'M' AS size, NULL AS topping, 55000 AS line_total
    UNION ALL SELECT 'Croissant Butter', 2, 42000, NULL, 'Warm', 84000
) x
JOIN products p ON p.product_name = x.product_name
WHERE @tvb_pending_invoice_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM invoice_details id WHERE id.invoice_id = @tvb_pending_invoice_id
  );

INSERT IGNORE INTO website_orders (
    invoice_id, customer_id, fulfillment_type, order_status,
    receiver_email, receiver_name, receiver_phone, delivery_address, city, district, ward,
    customer_note, requested_at, created_at
)
SELECT id,
       customer_id,
       CASE WHEN sales_channel = 'delivery' THEN 'delivery' ELSE 'pickup' END,
       CASE WHEN status = 'pending' THEN 'pending' ELSE 'completed' END,
       CASE WHEN sales_channel = 'delivery' THEN 'sample.customer@example.test' ELSE NULL END,
       CASE WHEN sales_channel = 'delivery' THEN 'Khách giao hàng mẫu' ELSE NULL END,
       CASE WHEN sales_channel = 'delivery' THEN '0900000000' ELSE NULL END,
       CASE WHEN sales_channel = 'delivery' THEN 'Sample delivery address, Phường mẫu, Quận mẫu, Hà Nội' ELSE NULL END,
       CASE WHEN sales_channel = 'delivery' THEN 'Hà Nội' ELSE NULL END,
       CASE WHEN sales_channel = 'delivery' THEN 'Quận mẫu' ELSE NULL END,
       CASE WHEN sales_channel = 'delivery' THEN 'Phường mẫu' ELSE NULL END,
       'Seeded from sample invoice',
       paid_at,
       created_at
FROM invoices
WHERE sales_channel IN ('website', 'delivery');

INSERT IGNORE INTO schema_migrations (migration_name)
VALUES ('001_security_operations'), ('001_recipe_seed'), ('manual_pre_run_setup'), ('002_branch_material_momo_payment'), ('003_branch_sample_data');
