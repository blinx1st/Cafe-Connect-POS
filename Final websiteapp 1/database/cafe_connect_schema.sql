CREATE DATABASE IF NOT EXISTS cafe_connect_crm
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE cafe_connect_crm;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS loyalty_point_transactions;
DROP TABLE IF EXISTS cash_transactions;
DROP TABLE IF EXISTS stock_movements;
DROP TABLE IF EXISTS customer_reviews;
DROP TABLE IF EXISTS newsletter_subscribers;
DROP TABLE IF EXISTS customer_favorites;
DROP TABLE IF EXISTS campaign_recipients;
DROP TABLE IF EXISTS marketing_emails;
DROP TABLE IF EXISTS customer_interactions;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS invoice_details;
DROP TABLE IF EXISTS pos_activity_logs;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS service_order_items;
DROP TABLE IF EXISTS service_orders;
DROP TABLE IF EXISTS dining_tables;
DROP TABLE IF EXISTS branch_inventory;
DROP TABLE IF EXISTS inventory_materials;
DROP TABLE IF EXISTS product_images;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS product_categories;
DROP TABLE IF EXISTS pos_sessions;
DROP TABLE IF EXISTS staff_shifts;
DROP TABLE IF EXISTS staff;
DROP TABLE IF EXISTS branches;
DROP TABLE IF EXISTS vouchers;
DROP TABLE IF EXISTS promotions;
DROP TABLE IF EXISTS customer_segment_memberships;
DROP TABLE IF EXISTS customer_segments;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS membership_tiers;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE membership_tiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tier_name VARCHAR(50) NOT NULL,
    min_total_spending DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_membership_tiers_name (tier_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    membership_tier_id INT NOT NULL,
    customer_name VARCHAR(150) NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    email VARCHAR(150) NULL,
    gender ENUM('male', 'female', 'other') NULL,
    birth_date DATE NULL,
    address VARCHAR(255) NULL,
    preferred_channel ENUM('pos', 'website', 'delivery', 'email', 'zalo', 'sms') NOT NULL DEFAULT 'pos',
    last_visit_date DATE NULL,
    current_points INT NOT NULL DEFAULT 0,
    total_spending DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('active', 'inactive', 'blocked') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_customers_phone (phone_number),
    UNIQUE KEY uq_customers_email (email),
    KEY idx_customers_tier (membership_tier_id),
    CONSTRAINT fk_customers_membership_tier
        FOREIGN KEY (membership_tier_id) REFERENCES membership_tiers(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_segments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    segment_code VARCHAR(50) NOT NULL,
    segment_name VARCHAR(120) NOT NULL,
    rule_description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_customer_segments_code (segment_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_segment_memberships (
    customer_id INT NOT NULL,
    segment_id INT NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    source ENUM('manual', 'auto') NOT NULL DEFAULT 'auto',
    PRIMARY KEY (customer_id, segment_id),
    KEY idx_customer_segments_segment (segment_id),
    CONSTRAINT fk_customer_segment_memberships_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_customer_segment_memberships_segment
        FOREIGN KEY (segment_id) REFERENCES customer_segments(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE branches (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_name VARCHAR(100) NOT NULL,
    address VARCHAR(255) NOT NULL,
    district VARCHAR(100) NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_branches_name (branch_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    staff_name VARCHAR(150) NOT NULL,
    staff_role ENUM('waiter', 'cashier', 'barista', 'owner', 'manager', 'marketing', 'admin') NOT NULL,
    phone_number VARCHAR(20) NULL,
    email VARCHAR(150) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_staff_email (email),
    KEY idx_staff_branch_role (branch_id, staff_role),
    CONSTRAINT fk_staff_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE staff_shifts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    shift_name VARCHAR(80) NOT NULL,
    starts_at TIME NOT NULL,
    ends_at TIME NOT NULL,
    work_date DATE NULL,
    status ENUM('active', 'closed') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_staff_shifts_staff_status (staff_id, status),
    CONSTRAINT fk_staff_shifts_staff
        FOREIGN KEY (staff_id) REFERENCES staff(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pos_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    staff_id INT NOT NULL,
    shift_id INT NULL,
    session_token VARCHAR(80) NOT NULL,
    staff_role ENUM('waiter', 'cashier', 'barista', 'owner', 'manager', 'marketing', 'admin') NOT NULL,
    opened_at DATETIME NOT NULL,
    closed_at DATETIME NULL,
    last_seen_at DATETIME NULL,
    login_ip VARCHAR(64) NULL,
    user_agent VARCHAR(255) NULL,
    opening_cash_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    expected_cash_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    closing_cash_amount DECIMAL(12,2) NULL,
    cash_difference_amount DECIMAL(12,2) NULL,
    status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    closed_reason ENUM('manual', 'timeout', 'system') NULL,
    notes VARCHAR(255) NULL,
    UNIQUE KEY uq_pos_sessions_token (session_token),
    KEY idx_pos_sessions_staff_status (staff_id, status),
    KEY idx_pos_sessions_branch_date (branch_id, opened_at),
    CONSTRAINT fk_pos_sessions_shift
        FOREIGN KEY (shift_id) REFERENCES staff_shifts(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_pos_sessions_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_pos_sessions_staff
        FOREIGN KEY (staff_id) REFERENCES staff(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_code VARCHAR(40) NOT NULL,
    category_name VARCHAR(120) NOT NULL,
    display_order INT NOT NULL DEFAULT 0,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_product_categories_code (category_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(150) NOT NULL,
    category VARCHAR(40) NOT NULL,
    price DECIMAL(12,2) NOT NULL,
    take_note TEXT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_products_name (product_name),
    KEY idx_products_category_status (category, status),
    CONSTRAINT fk_products_category
        FOREIGN KEY (category) REFERENCES product_categories(category_code)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    alt_text VARCHAR(180) NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    display_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_product_images_primary (product_id, is_primary),
    CONSTRAINT fk_product_images_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE promotions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    promotion_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    target_segment ENUM('all', 'bronze', 'silver', 'gold', 'birthday', 'inactive') NOT NULL DEFAULT 'all',
    campaign_channel ENUM('pos', 'website', 'email', 'zalo', 'sms', 'omnichannel') NOT NULL DEFAULT 'omnichannel',
    discount_type ENUM('fixed', 'percentage') NOT NULL DEFAULT 'fixed',
    discount_value DECIMAL(12,2) NOT NULL DEFAULT 0,
    voucher_quantity INT NOT NULL DEFAULT 0,
    usage_limit_per_customer INT NOT NULL DEFAULT 1,
    status ENUM('draft', 'active', 'cancelled', 'completed') NOT NULL DEFAULT 'draft',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_promotions_status_date (status, start_date, end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE vouchers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    voucher_code VARCHAR(50) NOT NULL,
    customer_id INT NOT NULL,
    promotion_id INT NOT NULL,
    release_date DATE NOT NULL,
    expiration_date DATE NOT NULL,
    status ENUM('issued', 'active', 'reserved', 'redeemed', 'expired', 'cancelled') NOT NULL DEFAULT 'issued',
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_vouchers_code (voucher_code),
    KEY idx_vouchers_customer_status (customer_id, status),
    KEY idx_vouchers_promotion_status (promotion_id, status),
    CONSTRAINT fk_vouchers_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_vouchers_promotion
        FOREIGN KEY (promotion_id) REFERENCES promotions(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE dining_tables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    area_name VARCHAR(80) NOT NULL DEFAULT 'Main',
    seat_count INT NOT NULL DEFAULT 2,
    status ENUM('available', 'occupied', 'reserved', 'inactive') NOT NULL DEFAULT 'available',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_dining_tables_branch_name (branch_id, table_name),
    KEY idx_dining_tables_status (status),
    CONSTRAINT fk_dining_tables_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_code VARCHAR(40) NOT NULL,
    branch_id INT NOT NULL,
    table_id INT NOT NULL,
    customer_id INT NULL,
    waiter_id INT NULL,
    cashier_id INT NULL,
    status ENUM('draft', 'preparing', 'ready', 'served', 'paid', 'cancelled') NOT NULL DEFAULT 'draft',
    note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_service_orders_code (order_code),
    KEY idx_service_orders_active (status, created_at),
    CONSTRAINT fk_service_orders_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_service_orders_table
        FOREIGN KEY (table_id) REFERENCES dining_tables(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_service_orders_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_service_orders_waiter
        FOREIGN KEY (waiter_id) REFERENCES staff(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_service_orders_cashier
        FOREIGN KEY (cashier_id) REFERENCES staff(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE service_order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_order_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    size ENUM('S', 'M', 'L') NULL,
    topping VARCHAR(100) NULL,
    note VARCHAR(255) NULL,
    line_total DECIMAL(12,2) NOT NULL,
    kitchen_status ENUM('waiting', 'preparing', 'ready', 'served') NOT NULL DEFAULT 'waiting',
    preparing_started_at DATETIME NULL,
    ready_at DATETIME NULL,
    served_at DATETIME NULL,
    prepared_by_staff_id INT NULL,
    prepared_by_session_id INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_service_order_items_order (service_order_id),
    KEY idx_service_order_items_kitchen (kitchen_status),
    KEY idx_service_order_items_prepared_by (prepared_by_staff_id, ready_at),
    CONSTRAINT fk_service_order_items_order
        FOREIGN KEY (service_order_id) REFERENCES service_orders(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_service_order_items_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_service_order_items_prepared_staff
        FOREIGN KEY (prepared_by_staff_id) REFERENCES staff(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_service_order_items_prepared_session
        FOREIGN KEY (prepared_by_session_id) REFERENCES pos_sessions(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    staff_id INT NOT NULL,
    pos_session_id INT NULL,
    service_order_id INT NULL,
    customer_id INT NULL,
    voucher_id INT NULL,
    sales_channel ENUM('pos', 'website', 'delivery') NOT NULL DEFAULT 'pos',
    invoice_date DATE NOT NULL,
    invoice_time TIME NOT NULL,
    bill_started_at DATETIME NULL,
    paid_at DATETIME NULL,
    subtotal_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    membership_discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    voucher_discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    points_earned INT NOT NULL DEFAULT 0,
    payment_method ENUM('cash', 'card', 'e_wallet') NOT NULL,
    status ENUM('pending', 'paid', 'cancelled', 'refunded') NOT NULL DEFAULT 'paid',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_invoices_date_branch (invoice_date, branch_id),
    KEY idx_invoices_customer_date (customer_id, invoice_date),
    KEY idx_invoices_channel_date (sales_channel, invoice_date),
    KEY idx_invoices_service_order (service_order_id),
    KEY idx_invoices_pos_session_paid (pos_session_id, paid_at),
    CONSTRAINT fk_invoices_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_invoices_staff
        FOREIGN KEY (staff_id) REFERENCES staff(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_invoices_pos_session
        FOREIGN KEY (pos_session_id) REFERENCES pos_sessions(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_invoices_service_order
        FOREIGN KEY (service_order_id) REFERENCES service_orders(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_invoices_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_invoices_voucher
        FOREIGN KEY (voucher_id) REFERENCES vouchers(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    payment_method ENUM('cash', 'card', 'e_wallet') NOT NULL,
    payment_provider VARCHAR(80) NULL,
    amount DECIMAL(12,2) NOT NULL,
    paid_at DATETIME NOT NULL,
    transaction_reference VARCHAR(120) NULL,
    status ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'paid',
    KEY idx_payments_invoice_method (invoice_id, payment_method),
    CONSTRAINT fk_payments_invoice
        FOREIGN KEY (invoice_id) REFERENCES invoices(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE invoice_details (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(12,2) NOT NULL,
    size ENUM('S', 'M', 'L') NULL,
    topping VARCHAR(100) NULL,
    line_total DECIMAL(12,2) NOT NULL,
    KEY idx_invoice_details_product (product_id),
    CONSTRAINT fk_invoice_details_invoice
        FOREIGN KEY (invoice_id) REFERENCES invoices(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_invoice_details_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pos_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pos_session_id INT NOT NULL,
    staff_id INT NOT NULL,
    staff_role ENUM('waiter', 'cashier', 'barista', 'owner', 'manager', 'marketing', 'admin') NOT NULL,
    action_type VARCHAR(60) NOT NULL,
    entity_type VARCHAR(60) NULL,
    entity_id INT NULL,
    product_id INT NULL,
    quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
    amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    status_from VARCHAR(40) NULL,
    status_to VARCHAR(40) NULL,
    note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_pos_activity_session_action (pos_session_id, action_type, created_at),
    KEY idx_pos_activity_staff_date (staff_id, created_at),
    CONSTRAINT fk_pos_activity_session
        FOREIGN KEY (pos_session_id) REFERENCES pos_sessions(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_pos_activity_staff
        FOREIGN KEY (staff_id) REFERENCES staff(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_pos_activity_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_interactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    staff_id INT NULL,
    invoice_id INT NULL,
    interaction_type ENUM('pos_visit', 'website_order', 'email_sent', 'voucher_redeemed', 'feedback', 'care_call') NOT NULL,
    interaction_note VARCHAR(255) NOT NULL,
    sentiment ENUM('positive', 'neutral', 'negative') NOT NULL DEFAULT 'neutral',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_customer_interactions_customer_date (customer_id, created_at),
    CONSTRAINT fk_customer_interactions_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_customer_interactions_staff
        FOREIGN KEY (staff_id) REFERENCES staff(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_customer_interactions_invoice
        FOREIGN KEY (invoice_id) REFERENCES invoices(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE marketing_emails (
    id INT AUTO_INCREMENT PRIMARY KEY,
    promotion_id INT NULL,
    created_by_staff_id INT NOT NULL,
    email_subject VARCHAR(200) NOT NULL,
    email_body TEXT NOT NULL,
    scheduled_at DATETIME NULL,
    sent_at DATETIME NULL,
    status ENUM('draft', 'scheduled', 'sent', 'cancelled') NOT NULL DEFAULT 'draft',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_marketing_emails_promotion_status (promotion_id, status),
    CONSTRAINT fk_marketing_emails_promotion
        FOREIGN KEY (promotion_id) REFERENCES promotions(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_marketing_emails_staff
        FOREIGN KEY (created_by_staff_id) REFERENCES staff(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE campaign_recipients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    marketing_email_id INT NOT NULL,
    customer_id INT NOT NULL,
    voucher_id INT NULL,
    delivery_status ENUM('queued', 'sent', 'opened', 'clicked', 'failed') NOT NULL DEFAULT 'queued',
    sent_at DATETIME NULL,
    opened_at DATETIME NULL,
    clicked_at DATETIME NULL,
    UNIQUE KEY uq_campaign_recipients_email_customer (marketing_email_id, customer_id),
    KEY idx_campaign_recipients_status (delivery_status),
    CONSTRAINT fk_campaign_recipients_email
        FOREIGN KEY (marketing_email_id) REFERENCES marketing_emails(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_campaign_recipients_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_campaign_recipients_voucher
        FOREIGN KEY (voucher_id) REFERENCES vouchers(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE branch_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    product_id INT NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    min_stock_level INT NOT NULL DEFAULT 0,
    last_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_branch_inventory_branch_product (branch_id, product_id),
    CONSTRAINT fk_branch_inventory_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_branch_inventory_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory_materials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    material_name VARCHAR(150) NOT NULL,
    unit VARCHAR(30) NOT NULL,
    stock_quantity DECIMAL(12,2) NOT NULL DEFAULT 0,
    min_stock_level DECIMAL(12,2) NOT NULL DEFAULT 0,
    supplier_name VARCHAR(150) NULL,
    last_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    UNIQUE KEY uq_inventory_materials_name (material_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    movement_code VARCHAR(50) NOT NULL,
    material_id INT NOT NULL,
    branch_id INT NOT NULL,
    staff_id INT NOT NULL,
    pos_session_id INT NULL,
    movement_type ENUM('import', 'sales_export', 'waste_export') NOT NULL,
    quantity DECIMAL(12,2) NOT NULL,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    note VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_stock_movements_code (movement_code),
    KEY idx_stock_movements_created (created_at),
    CONSTRAINT fk_stock_movements_material
        FOREIGN KEY (material_id) REFERENCES inventory_materials(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_stock_movements_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_stock_movements_staff
        FOREIGN KEY (staff_id) REFERENCES staff(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_stock_movements_session
        FOREIGN KEY (pos_session_id) REFERENCES pos_sessions(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE cash_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    staff_id INT NOT NULL,
    pos_session_id INT NULL,
    transaction_type ENUM('in', 'out') NOT NULL,
    reason VARCHAR(180) NOT NULL,
    amount DECIMAL(12,2) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_cash_transactions_created (created_at),
    CONSTRAINT fk_cash_transactions_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_cash_transactions_staff
        FOREIGN KEY (staff_id) REFERENCES staff(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_cash_transactions_session
        FOREIGN KEY (pos_session_id) REFERENCES pos_sessions(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE loyalty_point_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    invoice_id INT NULL,
    transaction_type ENUM('earn', 'redeem', 'adjust') NOT NULL,
    points INT NOT NULL,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_loyalty_customer_date (customer_id, created_at),
    CONSTRAINT fk_loyalty_point_transactions_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_loyalty_point_transactions_invoice
        FOREIGN KEY (invoice_id) REFERENCES invoices(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_favorites (
    customer_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (customer_id, product_id),
    CONSTRAINT fk_customer_favorites_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_customer_favorites_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE newsletter_subscribers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(150) NOT NULL,
    subscriber_name VARCHAR(150) NULL,
    status ENUM('active', 'unsubscribed') NOT NULL DEFAULT 'active',
    subscribed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_newsletter_subscribers_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(150) NOT NULL,
    customer_title VARCHAR(150) NULL,
    rating TINYINT NOT NULL DEFAULT 5,
    review_text TEXT NOT NULL,
    avatar_path VARCHAR(255) NULL,
    status ENUM('draft', 'published', 'hidden') NOT NULL DEFAULT 'published',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO membership_tiers (tier_name, min_total_spending, discount_rate, description) VALUES
('Bronze', 0, 0.00, 'Default tier for new members.'),
('Silver', 1000000, 5.00, 'Returning customer tier with checkout discount.'),
('Gold', 3000000, 10.00, 'High-value member tier with stronger checkout discount.');

INSERT INTO branches (branch_name, address, district) VALUES
('Cau Giay', '144 Xuan Thuy, Cau Giay, Hanoi', 'Cau Giay'),
('Hoan Kiem', '25 Hang Bai, Hoan Kiem, Hanoi', 'Hoan Kiem'),
('Tay Ho', '45 Xuan Dieu, Tay Ho, Hanoi', 'Tay Ho');

INSERT INTO staff (branch_id, staff_name, staff_role, phone_number, email) VALUES
(1, 'Lan Waiter', 'waiter', '0911000001', 'waiter.cg@cafeconnect.test'),
(1, 'Thu Cashier', 'cashier', '0911000002', 'cashier.cg@cafeconnect.test'),
(1, 'Nam Barista', 'barista', '0911000003', 'barista.cg@cafeconnect.test'),
(1, 'Quan Owner', 'owner', '0911000004', 'owner@cafeconnect.test'),
(1, 'Mai Marketing', 'marketing', '0911000005', 'marketing@cafeconnect.test'),
(2, 'Admin Cafe Connect', 'admin', '0911000006', 'admin@cafeconnect.test'),
(2, 'Minh Manager', 'manager', '0911000007', 'manager.hk@cafeconnect.test'),
(3, 'Chau Cashier', 'cashier', '0911000008', 'cashier.th@cafeconnect.test');

INSERT INTO staff_shifts (staff_id, shift_name, starts_at, ends_at, work_date) VALUES
(1, 'Morning floor', '07:00:00', '15:00:00', '2026-05-13'),
(2, 'Morning cashier', '07:00:00', '15:00:00', '2026-05-13'),
(3, 'Bar station', '07:00:00', '15:00:00', '2026-05-13'),
(4, 'Owner review', '09:00:00', '18:00:00', '2026-05-13'),
(5, 'Campaign desk', '09:00:00', '17:00:00', '2026-05-13'),
(6, 'Admin support', '09:00:00', '18:00:00', '2026-05-13'),
(7, 'Manager shift', '13:00:00', '21:00:00', '2026-05-13'),
(8, 'Evening cashier', '13:00:00', '21:00:00', '2026-05-13');

INSERT INTO pos_sessions (
    branch_id, staff_id, shift_id, session_token, staff_role, opened_at, closed_at, last_seen_at,
    opening_cash_amount, expected_cash_amount, closing_cash_amount, cash_difference_amount,
    status, closed_reason, notes
) VALUES
(1, 2, 2, 'demo-cashier-session-20260511', 'cashier', '2026-05-11 07:00:00', '2026-05-11 15:00:00', '2026-05-11 14:58:00',
 1000000, 1137750, 1137750, 0, 'closed', 'manual', 'Demo cashier checkout session.'),
(1, 1, 1, 'demo-waiter-session-20260513', 'waiter', '2026-05-13 07:05:00', '2026-05-13 15:00:00', '2026-05-13 14:55:00',
 0, 0, 0, 0, 'closed', 'manual', 'Demo waiter service session.'),
(1, 3, 3, 'demo-barista-session-20260513', 'barista', '2026-05-13 07:00:00', '2026-05-13 15:00:00', '2026-05-13 14:57:00',
 0, 0, 0, 0, 'closed', 'manual', 'Demo barista kitchen session.'),
(1, 2, 2, 'demo-cashier-session-20260513', 'cashier', '2026-05-13 07:00:00', '2026-05-13 15:00:00', '2026-05-13 14:58:00',
 1000000, 1020000, 1020000, 0, 'closed', 'manual', 'Demo cashier cash session.');

INSERT INTO product_categories (category_code, category_name, display_order) VALUES
('coffee', 'Coffee', 1),
('tea', 'Tea', 2),
('smoothie', 'Smoothie', 3),
('food', 'Food', 4),
('seasonal', 'Seasonal', 5);

INSERT INTO products (product_name, category, price, take_note) VALUES
('Signature Brown Latte', 'coffee', 55000, 'Espresso, fresh milk and brown sugar foam.'),
('Vietnamese Phin Coffee', 'coffee', 35000, 'Strong phin brew for daily members.'),
('Cold Brew Citrus', 'coffee', 60000, 'Cold brew with orange peel and tonic finish.'),
('Lotus Oolong Tea', 'tea', 45000, 'Light oolong tea with lotus aroma.'),
('Peach Lemongrass Tea', 'tea', 48000, 'Fresh peach, lemongrass and tea jelly.'),
('Mango Yogurt Smoothie', 'smoothie', 65000, 'Mango, yogurt and light cream.'),
('Croissant Butter', 'food', 42000, 'Warm butter croissant.'),
('Tiramisu Cup', 'food', 58000, 'Coffee cream dessert cup.'),
('May Bloom Macchiato', 'seasonal', 68000, 'Limited May campaign drink.');

INSERT INTO product_images (product_id, image_path, alt_text, is_primary, display_order) VALUES
(1, 'assets/images/coffee-1.png', 'Signature Brown Latte', 1, 1),
(2, 'assets/images/coffee-2.png', 'Vietnamese Phin Coffee', 1, 1),
(3, 'assets/images/coffee-3.png', 'Cold Brew Citrus', 1, 1),
(4, 'assets/images/coffee-4.png', 'Lotus Oolong Tea', 1, 1),
(5, 'assets/images/coffee-1.png', 'Peach Lemongrass Tea', 1, 1),
(6, 'assets/images/hero.png', 'Mango Yogurt Smoothie', 1, 1),
(7, 'assets/images/dessert-1.png', 'Croissant Butter', 1, 1),
(8, 'assets/images/dessert-2.png', 'Tiramisu Cup', 1, 1),
(9, 'assets/images/dessert-3.png', 'May Bloom Macchiato', 1, 1);

INSERT INTO customer_segments (segment_code, segment_name, rule_description) VALUES
('new_member', 'New member', 'Customers with spending below 1,000,000 VND.'),
('loyal_gold', 'Loyal Gold', 'Gold tier customers with recent visits.'),
('birthday_may', 'May birthday', 'Customers with a May birthday.'),
('reactivation', 'Reactivation', 'Inactive customers for campaign outreach.');

INSERT INTO customers (
    membership_tier_id, customer_name, phone_number, email, gender, birth_date, address,
    preferred_channel, last_visit_date, current_points, total_spending, status
) VALUES
(3, 'Nguyen An', '0900000001', 'nguyen.an@example.test', 'male', '1997-05-20', 'Cau Giay, Hanoi', 'website', '2026-05-10', 420, 4200000, 'active'),
(2, 'Tran Binh', '0900000002', 'tran.binh@example.test', 'male', '1995-08-12', 'Hoan Kiem, Hanoi', 'pos', '2026-05-11', 180, 1850000, 'active'),
(1, 'Le Chi', '0900000003', 'le.chi@example.test', 'female', '2000-01-04', 'Tay Ho, Hanoi', 'pos', '2026-05-08', 60, 650000, 'active'),
(3, 'Pham Dung', '0900000004', 'pham.dung@example.test', 'female', '1991-05-02', 'Dong Da, Hanoi', 'email', '2026-05-05', 510, 5300000, 'active'),
(2, 'Hoang Gia', '0900000005', 'hoang.gia@example.test', 'other', '1998-05-29', 'Thanh Xuan, Hanoi', 'zalo', '2026-04-01', 140, 1450000, 'active'),
(1, 'Vu Hoa', '0900000006', 'vu.hoa@example.test', 'female', '1993-11-18', 'Cau Giay, Hanoi', 'sms', '2026-03-25', 30, 320000, 'inactive');

INSERT INTO customer_segment_memberships (customer_id, segment_id, source) VALUES
(1, 2, 'auto'),
(4, 2, 'auto'),
(1, 3, 'auto'),
(4, 3, 'auto'),
(5, 3, 'auto'),
(6, 4, 'auto');

INSERT INTO promotions (
    promotion_name, description, start_date, end_date, target_segment,
    campaign_channel, discount_type, discount_value, voucher_quantity, usage_limit_per_customer, status
) VALUES
('May Birthday Reward', 'Birthday voucher for May members.', '2026-05-01', '2026-05-31', 'birthday', 'omnichannel', 'fixed', 20000, 3, 1, 'active'),
('Gold Member Week', 'Gold members receive 20 percent off.', '2026-05-01', '2026-06-15', 'gold', 'omnichannel', 'percentage', 20, 2, 1, 'active'),
('Website First Order', 'Fixed discount for website checkout.', '2026-05-01', '2026-06-30', 'all', 'website', 'fixed', 15000, 4, 1, 'active'),
('Return Coffee Call', 'Reactivate inactive customers.', '2026-05-01', '2026-06-15', 'inactive', 'email', 'percentage', 15, 2, 1, 'active');

INSERT INTO vouchers (voucher_code, customer_id, promotion_id, release_date, expiration_date, status, used_at) VALUES
('BDAY-NAN-001', 1, 1, '2026-05-01', '2026-05-31', 'active', NULL),
('GOLD-NAN-002', 1, 2, '2026-05-01', '2026-06-15', 'issued', NULL),
('WEB-NAN-003', 1, 3, '2026-05-01', '2026-06-30', 'redeemed', '2026-05-10 09:30:00'),
('GOLD-PDU-004', 4, 2, '2026-05-01', '2026-06-15', 'active', NULL),
('BDAY-HGI-005', 5, 1, '2026-05-01', '2026-05-31', 'issued', NULL),
('REAC-VHO-006', 6, 4, '2026-05-01', '2026-06-15', 'active', NULL),
('WEB-TBI-007', 2, 3, '2026-05-01', '2026-06-30', 'issued', NULL);

INSERT INTO dining_tables (branch_id, table_name, area_name, seat_count, status) VALUES
(1, 'T01', 'Window', 2, 'available'),
(1, 'T02', 'Window', 4, 'occupied'),
(1, 'T03', 'Main', 4, 'available'),
(1, 'T04', 'Main', 6, 'occupied'),
(1, 'T05', 'Garden', 2, 'available'),
(2, 'H01', 'Lobby', 2, 'available'),
(2, 'H02', 'Lobby', 4, 'occupied'),
(3, 'W01', 'Lake', 2, 'available');

INSERT INTO service_orders (order_code, branch_id, table_id, customer_id, waiter_id, status, note, created_at) VALUES
('OD-101', 1, 2, 2, 1, 'preparing', 'Less ice for tea.', '2026-05-13 09:12:00'),
('OD-102', 1, 4, NULL, 1, 'ready', 'Guest at main table.', '2026-05-13 09:25:00'),
('OD-103', 2, 7, 1, 7, 'served', 'Waiting for cashier.', '2026-05-13 09:40:00');

INSERT INTO service_order_items (
    service_order_id, product_id, quantity, unit_price, size, topping, note, line_total, kitchen_status
) VALUES
(1, 1, 2, 55000, 'M', NULL, NULL, 110000, 'preparing'),
(1, 5, 1, 48000, 'M', 'Tea jelly', 'Less ice', 48000, 'waiting'),
(2, 7, 2, 42000, NULL, NULL, 'Warm', 84000, 'ready'),
(2, 2, 2, 35000, 'M', NULL, NULL, 70000, 'ready'),
(3, 9, 1, 68000, 'L', NULL, NULL, 68000, 'served'),
(3, 8, 1, 58000, NULL, NULL, NULL, 58000, 'served');

UPDATE service_order_items
SET preparing_started_at = '2026-05-13 09:26:00',
    ready_at = '2026-05-13 09:34:00',
    prepared_by_staff_id = 3,
    prepared_by_session_id = 3
WHERE id IN (3, 4);

UPDATE service_order_items
SET preparing_started_at = '2026-05-13 09:42:00',
    ready_at = '2026-05-13 09:48:00',
    served_at = '2026-05-13 09:55:00',
    prepared_by_staff_id = 3,
    prepared_by_session_id = 3
WHERE id IN (5, 6);

INSERT INTO invoices (
    branch_id, staff_id, pos_session_id, service_order_id, customer_id, voucher_id, sales_channel,
    invoice_date, invoice_time, bill_started_at, paid_at, subtotal_amount, membership_discount_amount, voucher_discount_amount,
    total_amount, points_earned, payment_method, status, created_at
) VALUES
(1, 2, NULL, NULL, 1, 3, 'website', '2026-05-10', '09:30:00', '2026-05-10 09:30:00', '2026-05-10 09:32:00', 165000, 16500, 15000, 133500, 13, 'e_wallet', 'paid', '2026-05-10 09:32:00'),
(1, 2, 1, NULL, 2, NULL, 'pos', '2026-05-11', '14:10:00', '2026-05-11 14:10:00', '2026-05-11 14:12:00', 145000, 7250, 0, 137750, 13, 'cash', 'paid', '2026-05-11 14:12:00'),
(2, 8, NULL, NULL, 4, NULL, 'pos', '2026-05-12', '18:05:00', '2026-05-12 18:05:00', '2026-05-12 18:08:00', 262000, 26200, 0, 235800, 23, 'card', 'paid', '2026-05-12 18:08:00'),
(3, 8, NULL, NULL, 5, NULL, 'delivery', '2026-05-13', '08:40:00', '2026-05-13 08:40:00', '2026-05-13 08:43:00', 110000, 5500, 0, 104500, 10, 'e_wallet', 'paid', '2026-05-13 08:43:00'),
(1, 2, 4, NULL, NULL, NULL, 'pos', '2026-05-13', '10:05:00', '2026-05-13 10:05:00', '2026-05-13 10:07:00', 70000, 0, 0, 70000, 0, 'cash', 'paid', '2026-05-13 10:07:00');

INSERT INTO payments (invoice_id, payment_method, payment_provider, amount, paid_at, transaction_reference, status) VALUES
(1, 'e_wallet', 'Demo Momo', 133500, '2026-05-10 09:32:00', 'WEB-000001', 'paid'),
(2, 'cash', NULL, 137750, '2026-05-11 14:12:00', NULL, 'paid'),
(3, 'card', 'Demo card', 235800, '2026-05-12 18:08:00', 'CARD-000003', 'paid'),
(4, 'e_wallet', 'Demo ZaloPay', 104500, '2026-05-13 08:43:00', 'DEL-000004', 'paid'),
(5, 'cash', NULL, 70000, '2026-05-13 10:07:00', NULL, 'paid');

INSERT INTO invoice_details (invoice_id, product_id, quantity, unit_price, size, topping, line_total) VALUES
(1, 1, 2, 55000, 'M', NULL, 110000),
(1, 4, 1, 45000, 'M', NULL, 45000),
(2, 2, 3, 35000, 'M', NULL, 105000),
(2, 7, 1, 42000, NULL, NULL, 42000),
(3, 9, 3, 68000, 'L', NULL, 204000),
(3, 8, 1, 58000, NULL, NULL, 58000),
(4, 3, 1, 60000, 'M', NULL, 60000),
(4, 5, 1, 48000, 'M', NULL, 48000),
(5, 2, 2, 35000, 'M', NULL, 70000);

INSERT INTO pos_activity_logs (
    pos_session_id, staff_id, staff_role, action_type, entity_type, entity_id,
    product_id, quantity, amount, status_from, status_to, note, created_at
) VALUES
(1, 2, 'cashier', 'session_login', 'pos_session', 1, NULL, 0, 1000000, NULL, 'open', 'POS login', '2026-05-11 07:00:00'),
(1, 2, 'cashier', 'checkout', 'invoice', 2, NULL, 4, 137750, NULL, 'cash', 'Direct POS checkout', '2026-05-11 14:12:00'),
(1, 2, 'cashier', 'session_logout', 'pos_session', 1, NULL, 0, 1137750, 'open', 'closed', 'POS logout', '2026-05-11 15:00:00'),
(2, 1, 'waiter', 'session_login', 'pos_session', 2, NULL, 0, 0, NULL, 'open', 'POS login', '2026-05-13 07:05:00'),
(2, 1, 'waiter', 'order_created', 'service_order', 1, NULL, 3, 158000, NULL, 'preparing', 'OD-101', '2026-05-13 09:12:00'),
(2, 1, 'waiter', 'order_created', 'service_order', 2, NULL, 4, 154000, NULL, 'ready', 'OD-102', '2026-05-13 09:25:00'),
(3, 3, 'barista', 'kitchen_ready', 'service_order_item', 3, 7, 2, 84000, 'preparing', 'ready', 'OD-102 - Croissant Butter', '2026-05-13 09:34:00'),
(3, 3, 'barista', 'kitchen_ready', 'service_order_item', 4, 2, 2, 70000, 'preparing', 'ready', 'OD-102 - Vietnamese Phin Coffee', '2026-05-13 09:34:00'),
(4, 2, 'cashier', 'checkout', 'invoice', 5, NULL, 2, 70000, NULL, 'cash', 'Direct POS checkout', '2026-05-13 10:07:00'),
(4, 2, 'cashier', 'cash_transaction', 'cash_transaction', 2, NULL, 0, 120000, NULL, 'out', 'Buy small supplies', '2026-05-13 11:20:00');

INSERT INTO customer_interactions (
    customer_id, staff_id, invoice_id, interaction_type, interaction_note, sentiment, created_at
) VALUES
(1, NULL, 1, 'website_order', 'Member ordered through website and used first-order voucher.', 'positive', '2026-05-10 09:32:00'),
(2, 2, 2, 'pos_visit', 'Cashier checkout with member lookup.', 'positive', '2026-05-11 14:12:00'),
(4, 5, NULL, 'email_sent', 'Gold campaign email sent.', 'neutral', '2026-05-01 08:00:00'),
(6, 5, NULL, 'email_sent', 'Reactivation email sent.', 'neutral', '2026-05-01 08:30:00');

INSERT INTO marketing_emails (promotion_id, created_by_staff_id, email_subject, email_body, scheduled_at, sent_at, status) VALUES
(1, 5, 'May birthday reward', 'Birthday members receive a Cafe Connect voucher.', '2026-05-01 07:00:00', '2026-05-01 07:05:00', 'sent'),
(2, 5, 'Gold member week', 'Gold members receive 20 percent discount.', '2026-05-01 08:00:00', '2026-05-01 08:05:00', 'sent'),
(4, 5, 'Return coffee call', 'Inactive members receive a reactivation offer.', '2026-05-01 09:00:00', '2026-05-01 09:05:00', 'sent');

INSERT INTO campaign_recipients (marketing_email_id, customer_id, voucher_id, delivery_status, sent_at, opened_at, clicked_at) VALUES
(1, 1, 1, 'clicked', '2026-05-01 07:05:00', '2026-05-01 07:25:00', '2026-05-01 07:26:00'),
(1, 5, 5, 'opened', '2026-05-01 07:05:00', '2026-05-01 11:10:00', NULL),
(2, 1, 2, 'clicked', '2026-05-01 08:05:00', '2026-05-01 09:00:00', '2026-05-01 09:05:00'),
(2, 4, 4, 'opened', '2026-05-01 08:05:00', '2026-05-01 10:00:00', NULL),
(3, 6, 6, 'sent', '2026-05-01 09:05:00', NULL, NULL);

INSERT INTO branch_inventory (branch_id, product_id, stock_quantity, min_stock_level, last_updated) VALUES
(1, 1, 36, 20, '2026-05-13 07:00:00'),
(1, 2, 18, 20, '2026-05-13 07:00:00'),
(1, 5, 11, 12, '2026-05-13 07:00:00'),
(1, 7, 9, 12, '2026-05-13 07:00:00'),
(2, 1, 24, 20, '2026-05-13 07:00:00'),
(2, 8, 14, 10, '2026-05-13 07:00:00'),
(3, 3, 12, 15, '2026-05-13 07:00:00'),
(3, 9, 10, 8, '2026-05-13 07:00:00');

INSERT INTO inventory_materials (material_name, unit, stock_quantity, min_stock_level, supplier_name, last_updated) VALUES
('Arabica beans', 'kg', 42, 20, 'Highland Supply', '2026-05-13 07:00:00'),
('Robusta beans', 'kg', 16, 20, 'Dak Lak Roaster', '2026-05-13 07:00:00'),
('Fresh milk', 'litre', 85, 50, 'Daily Milk', '2026-05-13 07:00:00'),
('Tea leaves', 'kg', 12, 10, 'Lotus Tea Farm', '2026-05-13 07:00:00'),
('Croissant dough', 'pack', 9, 12, 'Bakery Partner', '2026-05-13 07:00:00');

INSERT INTO stock_movements (
    movement_code, material_id, branch_id, staff_id, pos_session_id, movement_type, quantity, total_amount, note, created_at
) VALUES
('IM-001', 1, 1, 7, NULL, 'import', 20, 3800000, 'Weekly bean import.', '2026-05-12 08:00:00'),
('SA-002', 3, 1, 3, 3, 'sales_export', 12, 0, 'Milk used in morning shift.', '2026-05-13 12:00:00'),
('WA-003', 5, 1, 7, NULL, 'waste_export', 2, 0, 'Damaged pastry packs.', '2026-05-13 13:30:00');

INSERT INTO cash_transactions (branch_id, staff_id, pos_session_id, transaction_type, reason, amount, created_at) VALUES
(1, 2, NULL, 'in', 'Opening cash float', 1000000, '2026-05-13 07:00:00'),
(1, 2, 4, 'out', 'Buy small supplies', 120000, '2026-05-13 11:20:00'),
(1, 2, 4, 'in', 'Cash order correction', 70000, '2026-05-13 10:07:00');

INSERT INTO loyalty_point_transactions (customer_id, invoice_id, transaction_type, points, description, created_at) VALUES
(1, 1, 'earn', 13, 'Earned points from invoice #1', '2026-05-10 09:32:00'),
(2, 2, 'earn', 13, 'Earned points from invoice #2', '2026-05-11 14:12:00'),
(4, 3, 'earn', 23, 'Earned points from invoice #3', '2026-05-12 18:08:00'),
(5, 4, 'earn', 10, 'Earned points from invoice #4', '2026-05-13 08:43:00');

INSERT INTO customer_favorites (customer_id, product_id) VALUES
(1, 1),
(1, 9),
(2, 2),
(4, 8);

INSERT INTO newsletter_subscribers (email, subscriber_name, status) VALUES
('nguyen.an@example.test', 'Nguyen An', 'active'),
('marketing.demo@example.test', 'Demo Subscriber', 'active');

INSERT INTO customer_reviews (customer_name, customer_title, rating, review_text, avatar_path, status, created_at) VALUES
('Nguyen An', 'Gold member', 5, 'Member lookup, voucher and website checkout are connected in one flow.', 'assets/images/avatar-1.png', 'published', '2026-05-12 10:00:00'),
('Tran Binh', 'Morning regular', 5, 'Cashier can apply points and see order history quickly.', 'assets/images/avatar-2.png', 'published', '2026-05-11 16:00:00'),
('Pham Dung', 'Remote worker', 4, 'The website menu and in-store POS use the same product data.', 'assets/images/avatar-3.png', 'published', '2026-05-10 15:00:00');
