CREATE DATABASE IF NOT EXISTS cafe_connect_crm
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE cafe_connect_crm;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS loyalty_point_transactions;
DROP TABLE IF EXISTS campaign_recipients;
DROP TABLE IF EXISTS marketing_emails;
DROP TABLE IF EXISTS customer_interactions;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS branch_inventory;
DROP TABLE IF EXISTS invoice_details;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS pos_sessions;
DROP TABLE IF EXISTS vouchers;
DROP TABLE IF EXISTS promotions;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS staff;
DROP TABLE IF EXISTS branches;
DROP TABLE IF EXISTS customer_segment_memberships;
DROP TABLE IF EXISTS customer_segments;
DROP TABLE IF EXISTS customers;
DROP TABLE IF EXISTS membership_tiers;

SET FOREIGN_KEY_CHECKS = 1;

-- =========================================================
-- 5.1. SQL Schema - Cafe Connect CRM/POS
-- =========================================================

CREATE TABLE membership_tiers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tier_name VARCHAR(50) NOT NULL,
    min_total_spending DECIMAL(12,2) NOT NULL DEFAULT 0,
    discount_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_membership_tiers_name UNIQUE (tier_name),
    CONSTRAINT chk_membership_tiers_spending CHECK (min_total_spending >= 0),
    CONSTRAINT chk_membership_tiers_discount CHECK (discount_rate >= 0 AND discount_rate <= 100)
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
    CONSTRAINT uq_customers_phone UNIQUE (phone_number),
    CONSTRAINT uq_customers_email UNIQUE (email),
    CONSTRAINT chk_customers_points CHECK (current_points >= 0),
    CONSTRAINT chk_customers_spending CHECK (total_spending >= 0),
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
    CONSTRAINT uq_customer_segments_code UNIQUE (segment_code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customer_segment_memberships (
    customer_id INT NOT NULL,
    segment_id INT NOT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    source ENUM('manual', 'auto') NOT NULL DEFAULT 'auto',
    PRIMARY KEY (customer_id, segment_id),
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
    CONSTRAINT uq_branches_name UNIQUE (branch_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE staff (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    staff_name VARCHAR(150) NOT NULL,
    staff_role ENUM('manager', 'cashier', 'barista', 'marketing', 'admin') NOT NULL,
    phone_number VARCHAR(20) NULL,
    email VARCHAR(150) NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_staff_email UNIQUE (email),
    CONSTRAINT fk_staff_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pos_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    staff_id INT NOT NULL,
    opened_at DATETIME NOT NULL,
    closed_at DATETIME NULL,
    opening_cash_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    expected_cash_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    closing_cash_amount DECIMAL(12,2) NULL,
    cash_difference_amount DECIMAL(12,2) NULL,
    status ENUM('open', 'closed') NOT NULL DEFAULT 'open',
    notes VARCHAR(255) NULL,
    CONSTRAINT chk_pos_sessions_cash CHECK (
        opening_cash_amount >= 0
        AND expected_cash_amount >= 0
        AND (closing_cash_amount IS NULL OR closing_cash_amount >= 0)
    ),
    CONSTRAINT fk_pos_sessions_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_pos_sessions_staff
        FOREIGN KEY (staff_id) REFERENCES staff(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(150) NOT NULL,
    category ENUM('coffee', 'tea', 'smoothie', 'food', 'seasonal') NOT NULL,
    price DECIMAL(12,2) NOT NULL,
    take_note TEXT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_products_name UNIQUE (product_name),
    CONSTRAINT chk_products_price CHECK (price >= 0)
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
    CONSTRAINT chk_promotions_date_range CHECK (start_date <= end_date),
    CONSTRAINT chk_promotions_discount_value CHECK (discount_value >= 0),
    CONSTRAINT chk_promotions_limits CHECK (voucher_quantity >= 0 AND usage_limit_per_customer > 0)
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
    CONSTRAINT uq_vouchers_code UNIQUE (voucher_code),
    CONSTRAINT chk_vouchers_date_range CHECK (release_date <= expiration_date),
    CONSTRAINT fk_vouchers_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_vouchers_promotion
        FOREIGN KEY (promotion_id) REFERENCES promotions(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
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
    CONSTRAINT uq_campaign_recipients_email_customer UNIQUE (marketing_email_id, customer_id),
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

CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    staff_id INT NOT NULL,
    pos_session_id INT NULL,
    customer_id INT NULL,
    voucher_id INT NULL,
    sales_channel ENUM('pos', 'website', 'delivery') NOT NULL DEFAULT 'pos',
    invoice_date DATE NOT NULL,
    invoice_time TIME NOT NULL,
    subtotal_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    membership_discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    voucher_discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    points_earned INT NOT NULL DEFAULT 0,
    payment_method ENUM('cash', 'card', 'e_wallet') NOT NULL,
    status ENUM('pending', 'paid', 'cancelled', 'refunded') NOT NULL DEFAULT 'paid',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_invoices_voucher UNIQUE (voucher_id),
    CONSTRAINT chk_invoices_amounts CHECK (
        subtotal_amount >= 0
        AND membership_discount_amount >= 0
        AND voucher_discount_amount >= 0
        AND total_amount >= 0
    ),
    CONSTRAINT chk_invoices_points CHECK (points_earned >= 0),
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
    CONSTRAINT chk_payments_amount CHECK (amount >= 0),
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
    CONSTRAINT chk_invoice_details_quantity CHECK (quantity > 0),
    CONSTRAINT chk_invoice_details_amounts CHECK (unit_price >= 0 AND line_total >= 0),
    CONSTRAINT fk_invoice_details_invoice
        FOREIGN KEY (invoice_id) REFERENCES invoices(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_invoice_details_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
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

CREATE TABLE branch_inventory (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    product_id INT NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    min_stock_level INT NOT NULL DEFAULT 0,
    last_updated DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_branch_inventory_branch_product UNIQUE (branch_id, product_id),
    CONSTRAINT chk_branch_inventory_stock CHECK (stock_quantity >= 0 AND min_stock_level >= 0),
    CONSTRAINT fk_branch_inventory_branch
        FOREIGN KEY (branch_id) REFERENCES branches(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_branch_inventory_product
        FOREIGN KEY (product_id) REFERENCES products(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE loyalty_point_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    invoice_id INT NULL,
    transaction_type ENUM('earn', 'redeem', 'adjust') NOT NULL,
    points INT NOT NULL,
    description VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_loyalty_point_transactions_points CHECK (points > 0),
    CONSTRAINT fk_loyalty_point_transactions_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_loyalty_point_transactions_invoice
        FOREIGN KEY (invoice_id) REFERENCES invoices(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_customers_tier ON customers(membership_tier_id);
CREATE INDEX idx_customer_segments_segment ON customer_segment_memberships(segment_id);
CREATE INDEX idx_pos_sessions_branch_date ON pos_sessions(branch_id, opened_at);
CREATE INDEX idx_vouchers_promotion_status ON vouchers(promotion_id, status);
CREATE INDEX idx_marketing_emails_promotion_status ON marketing_emails(promotion_id, status);
CREATE INDEX idx_campaign_recipients_status ON campaign_recipients(delivery_status);
CREATE INDEX idx_invoices_date_branch ON invoices(invoice_date, branch_id);
CREATE INDEX idx_invoices_customer_date ON invoices(customer_id, invoice_date);
CREATE INDEX idx_invoices_channel_date ON invoices(sales_channel, invoice_date);
CREATE INDEX idx_payments_invoice_method ON payments(invoice_id, payment_method);
CREATE INDEX idx_invoice_details_product ON invoice_details(product_id);
CREATE INDEX idx_customer_interactions_customer_date ON customer_interactions(customer_id, created_at);
CREATE INDEX idx_loyalty_customer_date ON loyalty_point_transactions(customer_id, created_at);

-- =========================================================
-- Sample Data
-- =========================================================

INSERT INTO membership_tiers (tier_name, min_total_spending, discount_rate, description) VALUES
('Bronze', 0, 0.00, 'Basic membership tier for new or low-spending customers.'),
('Silver', 1000000, 5.00, 'Mid-tier membership for returning customers.'),
('Gold', 3000000, 10.00, 'Highest tier for loyal high-value customers.');

INSERT INTO branches (branch_name, address, district) VALUES
('Cau Giay', '144 Xuan Thuy, Cau Giay, Hanoi', 'Cau Giay'),
('Hoan Kiem', '25 Hang Bai, Hoan Kiem, Hanoi', 'Hoan Kiem'),
('Dong Da', '89 Chua Boc, Dong Da, Hanoi', 'Dong Da'),
('Thanh Xuan', '12 Nguyen Trai, Thanh Xuan, Hanoi', 'Thanh Xuan'),
('Tay Ho', '45 Xuan Dieu, Tay Ho, Hanoi', 'Tay Ho');

INSERT INTO staff (branch_id, staff_name, staff_role, phone_number, email) VALUES
(1, 'Le Thu Ngan', 'cashier', '0911000001', 'ngan.cg@cafeconnect.vn'),
(1, 'Pham Quan Ly', 'manager', '0911000002', 'manager.cg@cafeconnect.vn'),
(2, 'Nguyen Minh Anh', 'cashier', '0911000003', 'cashier.hk@cafeconnect.vn'),
(3, 'Tran Hoang Nam', 'cashier', '0911000004', 'cashier.dd@cafeconnect.vn'),
(4, 'Bui Thanh Ha', 'manager', '0911000005', 'manager.tx@cafeconnect.vn'),
(5, 'Do Bao Chau', 'cashier', '0911000006', 'cashier.th@cafeconnect.vn'),
(1, 'Mai Marketing', 'marketing', '0911000007', 'marketing@cafeconnect.vn'),
(2, 'Admin Cafe Connect', 'admin', '0911000008', 'admin@cafeconnect.vn');

INSERT INTO pos_sessions (
    branch_id, staff_id, opened_at, closed_at,
    opening_cash_amount, expected_cash_amount, closing_cash_amount, cash_difference_amount,
    status, notes
) VALUES
(1, 1, '2026-05-14 07:00:00', '2026-05-14 15:00:00', 1000000, 1070000, 1070000, 0, 'closed', 'Morning POS session at Cau Giay.'),
(2, 3, '2026-05-14 07:00:00', '2026-05-14 15:00:00', 800000, 800000, 800000, 0, 'closed', 'No cash variance.'),
(4, 5, '2026-05-13 07:00:00', '2026-05-13 15:00:00', 900000, 900000, 898000, -2000, 'closed', 'Minor cash variance for manager review.'),
(5, 6, '2026-05-14 12:00:00', NULL, 700000, 837750, NULL, NULL, 'open', 'Afternoon session still open.');

INSERT INTO products (product_name, category, price, take_note) VALUES
('Cafe Sua Da', 'coffee', 35000, 'Best seller, Vietnamese iced milk coffee.'),
('Espresso', 'coffee', 45000, 'Single shot espresso.'),
('Latte', 'coffee', 55000, 'Milk coffee, can add caramel.'),
('Matcha Latte', 'tea', 60000, 'Green tea latte.'),
('Peach Tea', 'tea', 50000, 'Cold tea with peach slices.'),
('Mango Smoothie', 'smoothie', 65000, 'Seasonal mango smoothie.'),
('Tiramisu', 'food', 45000, 'Dessert cake.'),
('Croissant', 'food', 40000, 'Butter croissant.');

INSERT INTO customers (
    membership_tier_id, customer_name, phone_number, email, gender, address, current_points, total_spending, status, created_at
) VALUES
(3, 'Nguyen An', '0900000001', 'an.nguyen@email.com', 'male', 'Cau Giay, Hanoi', 1250, 4200000, 'active', '2025-08-01 08:00:00'),
(2, 'Tran Binh', '0900000002', 'binh.tran@email.com', 'male', 'Tay Ho, Hanoi', 620, 1600000, 'active', '2025-09-12 09:00:00'),
(1, 'Le Chi', '0900000003', 'chi.le@email.com', 'female', 'Dong Da, Hanoi', 180, 450000, 'active', '2026-01-05 10:00:00'),
(3, 'Pham Dung', '0900000004', 'dung.pham@email.com', 'female', 'Hoan Kiem, Hanoi', 980, 3600000, 'active', '2025-07-20 11:00:00'),
(2, 'Hoang Giang', '0900000005', 'giang.hoang@email.com', 'other', 'Thanh Xuan, Hanoi', 540, 1250000, 'active', '2025-11-11 12:00:00'),
(1, 'Bui Hanh', '0900000006', 'hanh.bui@email.com', 'female', 'Cau Giay, Hanoi', 90, 260000, 'inactive', '2025-12-01 13:00:00'),
(3, 'Dang Khoa', '0900000007', 'khoa.dang@email.com', 'male', 'Tay Ho, Hanoi', 1500, 5200000, 'active', '2025-06-15 14:00:00'),
(2, 'Do Linh', '0900000008', 'linh.do@email.com', 'female', 'Dong Da, Hanoi', 710, 1900000, 'active', '2025-10-03 15:00:00'),
(1, 'Vo Minh', '0900000009', 'minh.vo@email.com', 'male', 'Thanh Xuan, Hanoi', 120, 390000, 'active', '2026-02-09 16:00:00'),
(2, 'Ngo Nhi', '0900000010', 'nhi.ngo@email.com', 'female', 'Hoan Kiem, Hanoi', 660, 1750000, 'active', '2025-09-27 17:00:00'),
(1, 'Vu Oanh', '0900000011', 'oanh.vu@email.com', 'female', 'Cau Giay, Hanoi', 60, 180000, 'inactive', '2026-03-01 18:00:00'),
(3, 'Phan Phuc', '0900000012', 'phuc.phan@email.com', 'male', 'Dong Da, Hanoi', 1320, 4700000, 'active', '2025-05-22 19:00:00');

UPDATE customers SET birth_date = '1996-05-12', preferred_channel = 'website', last_visit_date = '2026-05-09' WHERE id = 1;
UPDATE customers SET birth_date = '1994-11-03', preferred_channel = 'pos', last_visit_date = '2026-05-13' WHERE id = 2;
UPDATE customers SET birth_date = '2001-02-18', preferred_channel = 'pos', last_visit_date = '2026-05-08' WHERE id = 3;
UPDATE customers SET birth_date = '1998-07-09', preferred_channel = 'email', last_visit_date = '2026-05-11' WHERE id = 4;
UPDATE customers SET birth_date = '1997-05-24', preferred_channel = 'website', last_visit_date = '2026-05-07' WHERE id = 5;
UPDATE customers SET birth_date = '1995-09-17', preferred_channel = 'email', last_visit_date = '2026-04-02' WHERE id = 6;
UPDATE customers SET birth_date = '1992-03-21', preferred_channel = 'pos', last_visit_date = '2026-05-10' WHERE id = 7;
UPDATE customers SET birth_date = '1999-12-05', preferred_channel = 'website', last_visit_date = '2026-05-14' WHERE id = 8;
UPDATE customers SET birth_date = '2000-06-29', preferred_channel = 'delivery', last_visit_date = '2026-05-08' WHERE id = 9;
UPDATE customers SET birth_date = '1993-05-30', preferred_channel = 'email', last_visit_date = '2026-05-03' WHERE id = 10;
UPDATE customers SET birth_date = '1996-10-11', preferred_channel = 'email', last_visit_date = '2026-03-25' WHERE id = 11;
UPDATE customers SET birth_date = '1991-01-14', preferred_channel = 'pos', last_visit_date = '2026-05-12' WHERE id = 12;

INSERT INTO customer_segments (segment_code, segment_name, rule_description) VALUES
('GOLD_ACTIVE', 'Gold members active this month', 'Gold tier customers with at least one paid order in the current month.'),
('BIRTHDAY_MAY', 'May birthday members', 'Members with birthday month in May.'),
('INACTIVE_30', 'Inactive more than 30 days', 'Customers whose latest purchase is older than 30 days.'),
('WEBSITE_MEMBER', 'Website member portal users', 'Customers who prefer website as the main CRM channel.');

INSERT INTO customer_segment_memberships (customer_id, segment_id, source) VALUES
(1, 1, 'auto'),
(4, 1, 'auto'),
(7, 1, 'auto'),
(12, 1, 'auto'),
(1, 2, 'auto'),
(5, 2, 'auto'),
(10, 2, 'auto'),
(6, 3, 'auto'),
(11, 3, 'auto'),
(1, 4, 'auto'),
(5, 4, 'auto'),
(8, 4, 'auto');

INSERT INTO promotions (
    promotion_name, description, start_date, end_date, target_segment, campaign_channel,
    discount_type, discount_value, voucher_quantity, usage_limit_per_customer, status
) VALUES
('Birthday Voucher', 'Birthday discount for members in their birthday month.', '2026-05-01', '2026-05-31', 'birthday', 'email', 'fixed', 20000, 200, 1, 'active'),
('Weekend Combo', 'Weekend combo promotion for returning customers.', '2026-05-10', '2026-05-31', 'all', 'omnichannel', 'percentage', 15, 500, 1, 'active'),
('Gold Member Appreciation', 'Special campaign for Gold members.', '2026-05-01', '2026-06-15', 'gold', 'email', 'percentage', 20, 120, 1, 'active'),
('Inactive Customer Reactivation', 'Voucher to bring inactive customers back after 30 days.', '2026-04-01', '2026-05-31', 'inactive', 'email', 'fixed', 30000, 150, 1, 'active');

INSERT INTO vouchers (voucher_code, customer_id, promotion_id, release_date, expiration_date, status, used_at) VALUES
('BDAY-NAN-001', 1, 1, '2026-05-01', '2026-05-30', 'redeemed', '2026-05-01 08:18:00'),
('WEEK-TBI-002', 2, 2, '2026-05-10', '2026-06-15', 'active', NULL),
('GOLD-PDU-003', 4, 3, '2026-05-01', '2026-06-15', 'redeemed', '2026-05-04 15:12:00'),
('REAC-BHA-004', 6, 4, '2026-04-01', '2026-05-31', 'active', NULL),
('GOLD-DKH-005', 7, 3, '2026-05-01', '2026-06-15', 'redeemed', '2026-05-02 18:40:00'),
('WEEK-DLI-006', 8, 2, '2026-05-10', '2026-06-15', 'issued', NULL),
('BDAY-NNH-007', 10, 1, '2026-05-01', '2026-05-30', 'redeemed', '2026-05-03 09:35:00'),
('REAC-VOA-008', 11, 4, '2026-04-01', '2026-05-31', 'expired', NULL),
('GOLD-PPH-009', 12, 3, '2026-05-01', '2026-06-15', 'redeemed', '2026-05-05 20:10:00'),
('WEEK-LCH-010', 3, 2, '2026-05-10', '2026-06-15', 'issued', NULL),
('WEEK-VMI-011', 9, 2, '2026-05-10', '2026-06-15', 'active', NULL),
('BDAY-HGI-012', 5, 1, '2026-05-01', '2026-05-30', 'issued', NULL);

INSERT INTO marketing_emails (
    promotion_id, created_by_staff_id, email_subject, email_body, scheduled_at, sent_at, status
) VALUES
(1, 7, 'Happy birthday from Cafe Connect', 'Send birthday voucher and member point summary.', '2026-05-01 07:00:00', '2026-05-01 07:05:00', 'sent'),
(3, 7, 'Gold member appreciation week', 'Send Gold tier offer with POS redeemable voucher.', '2026-05-01 08:00:00', '2026-05-01 08:05:00', 'sent'),
(4, 7, 'We miss your coffee visit', 'Send reactivation voucher to inactive customers.', '2026-05-02 09:00:00', '2026-05-02 09:05:00', 'sent');

INSERT INTO campaign_recipients (
    marketing_email_id, customer_id, voucher_id, delivery_status, sent_at, opened_at, clicked_at
) VALUES
(1, 1, 1, 'clicked', '2026-05-01 07:05:00', '2026-05-01 07:20:00', '2026-05-01 07:22:00'),
(1, 10, 7, 'clicked', '2026-05-01 07:05:00', '2026-05-01 08:40:00', '2026-05-01 08:42:00'),
(1, 5, 12, 'opened', '2026-05-01 07:05:00', '2026-05-01 10:15:00', NULL),
(2, 4, 3, 'clicked', '2026-05-01 08:05:00', '2026-05-01 08:50:00', '2026-05-01 08:53:00'),
(2, 7, 5, 'clicked', '2026-05-01 08:05:00', '2026-05-01 09:30:00', '2026-05-01 09:32:00'),
(2, 12, 9, 'clicked', '2026-05-01 08:05:00', '2026-05-01 10:00:00', '2026-05-01 10:03:00'),
(3, 6, 4, 'opened', '2026-05-02 09:05:00', '2026-05-02 11:10:00', NULL),
(3, 11, 8, 'sent', '2026-05-02 09:05:00', NULL, NULL);

INSERT INTO invoices (
    branch_id, staff_id, customer_id, voucher_id, invoice_date, invoice_time,
    subtotal_amount, membership_discount_amount, voucher_discount_amount, total_amount,
    points_earned, payment_method, status
) VALUES
(1, 1, 1, 1, '2026-05-01', '08:15:00', 120000, 12000, 20000, 88000, 8, 'e_wallet', 'paid'),
(5, 6, 7, 5, '2026-05-02', '18:35:00', 210000, 21000, 42000, 147000, 14, 'card', 'paid'),
(2, 3, 10, 7, '2026-05-03', '09:30:00', 95000, 4750, 20000, 70250, 7, 'cash', 'paid'),
(2, 3, 4, 3, '2026-05-04', '15:05:00', 180000, 18000, 36000, 126000, 12, 'e_wallet', 'paid'),
(3, 4, 12, 9, '2026-05-05', '20:05:00', 240000, 24000, 48000, 168000, 16, 'card', 'paid'),
(1, 1, 2, NULL, '2026-05-05', '07:45:00', 85000, 4250, 0, 80750, 8, 'cash', 'paid'),
(3, 4, 8, NULL, '2026-05-06', '08:20:00', 155000, 7750, 0, 147250, 14, 'e_wallet', 'paid'),
(4, 5, 5, NULL, '2026-05-07', '14:15:00', 110000, 5500, 0, 104500, 10, 'card', 'paid'),
(1, 1, 3, NULL, '2026-05-08', '10:10:00', 75000, 0, 0, 75000, 7, 'cash', 'paid'),
(4, 5, 9, NULL, '2026-05-08', '17:25:00', 135000, 0, 0, 135000, 13, 'e_wallet', 'paid'),
(5, 6, 1, NULL, '2026-05-09', '08:05:00', 175000, 17500, 0, 157500, 15, 'card', 'paid'),
(1, 1, 7, NULL, '2026-05-10', '19:45:00', 260000, 26000, 0, 234000, 23, 'e_wallet', 'paid'),
(2, 3, 4, NULL, '2026-05-11', '07:55:00', 90000, 9000, 0, 81000, 8, 'cash', 'paid'),
(3, 4, 12, NULL, '2026-05-12', '18:10:00', 195000, 19500, 0, 175500, 17, 'card', 'paid'),
(4, 5, 2, NULL, '2026-05-13', '08:35:00', 65000, 3250, 0, 61750, 6, 'e_wallet', 'paid'),
(5, 6, 8, NULL, '2026-05-14', '13:20:00', 145000, 7250, 0, 137750, 13, 'cash', 'paid'),
(1, 1, NULL, NULL, '2026-05-14', '12:05:00', 70000, 0, 0, 70000, 0, 'cash', 'paid'),
(2, 3, 10, NULL, '2026-04-20', '16:45:00', 125000, 6250, 0, 118750, 11, 'card', 'paid'),
(3, 4, 6, NULL, '2026-04-02', '09:10:00', 45000, 0, 0, 45000, 4, 'cash', 'paid'),
(4, 5, 11, NULL, '2026-03-25', '18:00:00', 50000, 0, 0, 50000, 5, 'e_wallet', 'paid');

UPDATE invoices SET pos_session_id = 1 WHERE id = 17;
UPDATE invoices SET pos_session_id = 3 WHERE id = 15;
UPDATE invoices SET pos_session_id = 4 WHERE id = 16;
UPDATE invoices SET sales_channel = 'website' WHERE id IN (11, 16);
UPDATE invoices SET sales_channel = 'delivery' WHERE id = 18;

INSERT INTO payments (
    invoice_id, payment_method, payment_provider, amount, paid_at, transaction_reference, status
) VALUES
(1, 'e_wallet', 'Momo', 88000, '2026-05-01 08:18:00', 'MOMO-000001', 'paid'),
(2, 'card', 'Visa', 147000, '2026-05-02 18:40:00', 'CARD-000002', 'paid'),
(3, 'cash', NULL, 70250, '2026-05-03 09:35:00', NULL, 'paid'),
(4, 'e_wallet', 'ZaloPay', 126000, '2026-05-04 15:12:00', 'ZALO-000004', 'paid'),
(5, 'card', 'Mastercard', 168000, '2026-05-05 20:10:00', 'CARD-000005', 'paid'),
(6, 'cash', NULL, 80750, '2026-05-05 07:50:00', NULL, 'paid'),
(7, 'e_wallet', 'Momo', 147250, '2026-05-06 08:25:00', 'MOMO-000007', 'paid'),
(8, 'card', 'Visa', 104500, '2026-05-07 14:20:00', 'CARD-000008', 'paid'),
(9, 'cash', NULL, 75000, '2026-05-08 10:15:00', NULL, 'paid'),
(10, 'e_wallet', 'ZaloPay', 135000, '2026-05-08 17:30:00', 'ZALO-000010', 'paid'),
(11, 'card', 'Visa', 157500, '2026-05-09 08:10:00', 'WEB-000011', 'paid'),
(12, 'e_wallet', 'Momo', 234000, '2026-05-10 19:50:00', 'MOMO-000012', 'paid'),
(13, 'cash', NULL, 81000, '2026-05-11 08:00:00', NULL, 'paid'),
(14, 'card', 'Mastercard', 175500, '2026-05-12 18:15:00', 'CARD-000014', 'paid'),
(15, 'e_wallet', 'Momo', 61750, '2026-05-13 08:40:00', 'MOMO-000015', 'paid'),
(16, 'cash', NULL, 137750, '2026-05-14 13:25:00', NULL, 'paid'),
(17, 'cash', NULL, 70000, '2026-05-14 12:08:00', NULL, 'paid'),
(18, 'card', 'Visa', 118750, '2026-04-20 16:50:00', 'DEL-000018', 'paid'),
(19, 'cash', NULL, 45000, '2026-04-02 09:15:00', NULL, 'paid'),
(20, 'e_wallet', 'ZaloPay', 50000, '2026-03-25 18:05:00', 'ZALO-000020', 'paid');

INSERT INTO customer_interactions (
    customer_id, staff_id, invoice_id, interaction_type, interaction_note, sentiment, created_at
) VALUES
(1, 1, 1, 'voucher_redeemed', 'Birthday voucher redeemed at POS; customer earned 8 points.', 'positive', '2026-05-01 08:18:00'),
(7, 6, 2, 'voucher_redeemed', 'Gold campaign voucher redeemed at Tay Ho branch.', 'positive', '2026-05-02 18:40:00'),
(10, 3, 3, 'voucher_redeemed', 'Birthday voucher redeemed with cash payment.', 'positive', '2026-05-03 09:35:00'),
(4, 3, 4, 'voucher_redeemed', 'Gold member used appreciation offer.', 'positive', '2026-05-04 15:12:00'),
(12, 4, 5, 'voucher_redeemed', 'High-value customer redeemed Gold campaign voucher.', 'positive', '2026-05-05 20:10:00'),
(1, NULL, 11, 'website_order', 'Customer ordered through website member portal.', 'positive', '2026-05-09 08:10:00'),
(8, NULL, 16, 'website_order', 'Website order synchronized to CRM purchase history.', 'positive', '2026-05-14 13:25:00'),
(6, 7, NULL, 'email_sent', 'Reactivation voucher email sent after inactive period.', 'neutral', '2026-05-02 09:05:00'),
(11, 7, NULL, 'email_sent', 'Reactivation voucher email sent but not opened yet.', 'neutral', '2026-05-02 09:05:00');

INSERT INTO invoice_details (invoice_id, product_id, quantity, unit_price, size, topping, line_total) VALUES
(1, 1, 2, 35000, 'M', NULL, 70000),
(1, 7, 1, 45000, NULL, NULL, 45000),
(2, 3, 2, 55000, 'M', 'Caramel', 110000),
(2, 7, 2, 45000, NULL, NULL, 90000),
(3, 1, 1, 35000, 'M', NULL, 35000),
(3, 5, 1, 50000, 'M', NULL, 50000),
(4, 4, 2, 60000, 'M', NULL, 120000),
(4, 8, 1, 40000, NULL, NULL, 40000),
(5, 6, 2, 65000, 'L', NULL, 130000),
(5, 7, 2, 45000, NULL, NULL, 90000),
(6, 3, 1, 55000, 'M', NULL, 55000),
(6, 8, 1, 40000, NULL, NULL, 40000),
(7, 1, 3, 35000, 'M', NULL, 105000),
(7, 5, 1, 50000, 'M', NULL, 50000),
(8, 3, 2, 55000, 'M', NULL, 110000),
(9, 1, 1, 35000, 'M', NULL, 35000),
(9, 8, 1, 40000, NULL, NULL, 40000),
(10, 4, 1, 60000, 'M', NULL, 60000),
(10, 7, 1, 45000, NULL, NULL, 45000),
(11, 3, 2, 55000, 'M', NULL, 110000),
(11, 5, 1, 50000, 'M', NULL, 50000),
(12, 6, 2, 65000, 'L', NULL, 130000),
(12, 7, 2, 45000, NULL, NULL, 90000),
(13, 1, 2, 35000, 'M', NULL, 70000),
(14, 3, 2, 55000, 'M', NULL, 110000),
(14, 8, 2, 40000, NULL, NULL, 80000),
(15, 6, 1, 65000, 'M', NULL, 65000),
(16, 4, 2, 60000, 'M', NULL, 120000),
(16, 1, 1, 35000, 'M', NULL, 35000),
(17, 1, 2, 35000, 'M', NULL, 70000),
(18, 5, 1, 50000, 'M', NULL, 50000),
(18, 7, 1, 45000, NULL, NULL, 45000),
(19, 2, 1, 45000, 'S', NULL, 45000),
(20, 5, 1, 50000, 'M', NULL, 50000);

INSERT INTO branch_inventory (branch_id, product_id, stock_quantity, min_stock_level, last_updated) VALUES
(1, 1, 35, 20, '2026-05-05 09:00:00'),
(1, 3, 22, 15, '2026-05-05 09:00:00'),
(1, 7, 18, 12, '2026-05-05 09:00:00'),
(2, 1, 28, 20, '2026-05-05 09:30:00'),
(2, 5, 16, 15, '2026-05-05 09:30:00'),
(2, 7, 8, 15, '2026-05-05 09:30:00'),
(3, 1, 40, 20, '2026-05-04 21:00:00'),
(3, 3, 18, 20, '2026-05-04 21:00:00'),
(3, 6, 14, 10, '2026-05-04 21:00:00'),
(4, 4, 12, 15, '2026-05-05 08:45:00'),
(4, 6, 20, 10, '2026-05-05 08:45:00'),
(4, 8, 11, 12, '2026-05-05 08:45:00'),
(5, 3, 25, 15, '2026-05-05 10:00:00'),
(5, 6, 9, 10, '2026-05-05 10:00:00'),
(5, 7, 16, 15, '2026-05-05 10:00:00');

INSERT INTO loyalty_point_transactions (customer_id, invoice_id, transaction_type, points, description, created_at) VALUES
(1, 1, 'earn', 8, 'Earned points from invoice #1', '2026-05-01 08:18:00'),
(7, 2, 'earn', 14, 'Earned points from invoice #2', '2026-05-02 18:40:00'),
(10, 3, 'earn', 7, 'Earned points from invoice #3', '2026-05-03 09:35:00'),
(4, 4, 'earn', 12, 'Earned points from invoice #4', '2026-05-04 15:12:00'),
(12, 5, 'earn', 16, 'Earned points from invoice #5', '2026-05-05 20:10:00'),
(2, 6, 'earn', 8, 'Earned points from invoice #6', '2026-05-05 07:50:00'),
(8, 7, 'earn', 14, 'Earned points from invoice #7', '2026-05-06 08:25:00'),
(5, 8, 'earn', 10, 'Earned points from invoice #8', '2026-05-07 14:20:00'),
(3, 9, 'earn', 7, 'Earned points from invoice #9', '2026-05-08 10:15:00'),
(9, 10, 'earn', 13, 'Earned points from invoice #10', '2026-05-08 17:30:00'),
(1, 11, 'earn', 15, 'Earned points from invoice #11', '2026-05-09 08:10:00'),
(7, 12, 'earn', 23, 'Earned points from invoice #12', '2026-05-10 19:50:00'),
(4, 13, 'earn', 8, 'Earned points from invoice #13', '2026-05-11 08:00:00'),
(12, 14, 'earn', 17, 'Earned points from invoice #14', '2026-05-12 18:15:00'),
(2, 15, 'earn', 6, 'Earned points from invoice #15', '2026-05-13 08:40:00'),
(8, 16, 'earn', 13, 'Earned points from invoice #16', '2026-05-14 13:25:00');

-- =========================================================
-- 5.2. Queries
-- =========================================================

SET @report_month_start = '2026-05-01';
SET @report_month_end = '2026-06-01';

-- Query 1: Top 10 customers by monthly spending.
SELECT
    c.id AS customer_id,
    c.customer_name,
    c.phone_number,
    mt.tier_name,
    COUNT(i.id) AS paid_invoice_count,
    SUM(i.total_amount) AS monthly_spending,
    SUM(i.points_earned) AS points_earned_in_month
FROM customers c
JOIN membership_tiers mt ON mt.id = c.membership_tier_id
JOIN invoices i ON i.customer_id = c.id
WHERE i.status = 'paid'
  AND i.invoice_date >= @report_month_start
  AND i.invoice_date < @report_month_end
GROUP BY c.id, c.customer_name, c.phone_number, mt.tier_name
ORDER BY monthly_spending DESC
LIMIT 10;

-- Query 2: Marketing campaign effectiveness by voucher usage rate.
SELECT
    p.id AS promotion_id,
    p.promotion_name,
    p.start_date,
    p.end_date,
    p.target_segment,
    COUNT(v.id) AS issued_vouchers,
    SUM(CASE WHEN v.status = 'redeemed' THEN 1 ELSE 0 END) AS redeemed_vouchers,
    ROUND(
        SUM(CASE WHEN v.status = 'redeemed' THEN 1 ELSE 0 END) / NULLIF(COUNT(v.id), 0) * 100,
        2
    ) AS voucher_usage_rate_percent,
    COALESCE(SUM(CASE WHEN i.status = 'paid' THEN i.total_amount ELSE 0 END), 0) AS revenue_from_redeemed_vouchers
FROM promotions p
LEFT JOIN vouchers v ON v.promotion_id = p.id
LEFT JOIN invoices i ON i.voucher_id = v.id
GROUP BY p.id, p.promotion_name, p.start_date, p.end_date, p.target_segment
ORDER BY voucher_usage_rate_percent DESC, revenue_from_redeemed_vouchers DESC;

-- Query 3: Peak visiting hours of member customers for staff optimization.
SELECT
    b.branch_name,
    HOUR(i.invoice_time) AS visit_hour,
    CONCAT(
        LPAD(HOUR(i.invoice_time), 2, '0'),
        ':00 - ',
        LPAD(HOUR(i.invoice_time) + 1, 2, '0'),
        ':00'
    ) AS time_slot,
    COUNT(i.id) AS member_visit_count,
    COUNT(DISTINCT i.customer_id) AS unique_member_customers,
    SUM(i.total_amount) AS revenue_in_time_slot
FROM invoices i
JOIN branches b ON b.id = i.branch_id
JOIN customers c ON c.id = i.customer_id
WHERE i.status = 'paid'
  AND i.customer_id IS NOT NULL
  AND i.invoice_date >= @report_month_start
  AND i.invoice_date < @report_month_end
GROUP BY b.branch_name, HOUR(i.invoice_time)
ORDER BY b.branch_name, member_visit_count DESC, revenue_in_time_slot DESC;

-- Query 4: Top-selling products by branch.
SELECT
    b.branch_name,
    p.product_name,
    SUM(idt.quantity) AS quantity_sold,
    SUM(idt.line_total) AS product_revenue
FROM invoice_details idt
JOIN invoices i ON i.id = idt.invoice_id
JOIN branches b ON b.id = i.branch_id
JOIN products p ON p.id = idt.product_id
WHERE i.status = 'paid'
  AND i.invoice_date >= @report_month_start
  AND i.invoice_date < @report_month_end
GROUP BY b.branch_name, p.product_name
ORDER BY b.branch_name, quantity_sold DESC, product_revenue DESC;

-- Query 5: Low-stock inventory alerts.
SELECT
    b.branch_name,
    p.product_name,
    bi.stock_quantity,
    bi.min_stock_level,
    bi.last_updated,
    CASE
        WHEN bi.stock_quantity < bi.min_stock_level THEN 'Low Stock'
        ELSE 'OK'
    END AS inventory_status
FROM branch_inventory bi
JOIN branches b ON b.id = bi.branch_id
JOIN products p ON p.id = bi.product_id
WHERE bi.stock_quantity < bi.min_stock_level
ORDER BY b.branch_name, p.product_name;

-- Query 6: Monthly revenue by branch.
SELECT
    b.branch_name,
    COUNT(i.id) AS paid_invoice_count,
    SUM(i.subtotal_amount) AS subtotal_revenue,
    SUM(i.membership_discount_amount + i.voucher_discount_amount) AS total_discount_amount,
    SUM(i.total_amount) AS net_revenue
FROM branches b
LEFT JOIN invoices i
    ON i.branch_id = b.id
    AND i.status = 'paid'
    AND i.invoice_date >= @report_month_start
    AND i.invoice_date < @report_month_end
GROUP BY b.id, b.branch_name
ORDER BY net_revenue DESC;

-- Query 7: Customers inactive for more than 30 days for reactivation campaign.
SELECT
    c.id AS customer_id,
    c.customer_name,
    c.phone_number,
    mt.tier_name,
    MAX(i.invoice_date) AS last_purchase_date,
    DATEDIFF(CURRENT_DATE, MAX(i.invoice_date)) AS days_since_last_purchase
FROM customers c
JOIN membership_tiers mt ON mt.id = c.membership_tier_id
LEFT JOIN invoices i ON i.customer_id = c.id AND i.status = 'paid'
WHERE c.status IN ('active', 'inactive')
GROUP BY c.id, c.customer_name, c.phone_number, mt.tier_name
HAVING last_purchase_date IS NULL OR days_since_last_purchase > 30
ORDER BY days_since_last_purchase DESC;

-- Query 8: POS customer lookup before checkout by phone/email/QR identity.
SET @lookup_identity = '0900000001';

SELECT
    c.id AS customer_id,
    c.customer_name,
    c.phone_number,
    c.email,
    mt.tier_name,
    mt.discount_rate,
    c.current_points,
    c.total_spending,
    c.last_visit_date,
    COUNT(v.id) AS available_voucher_count,
    GROUP_CONCAT(v.voucher_code ORDER BY v.expiration_date SEPARATOR ', ') AS available_vouchers
FROM customers c
JOIN membership_tiers mt ON mt.id = c.membership_tier_id
LEFT JOIN vouchers v
    ON v.customer_id = c.id
    AND v.status IN ('issued', 'active')
    AND CURRENT_DATE BETWEEN v.release_date AND v.expiration_date
WHERE c.phone_number = @lookup_identity
   OR c.email = @lookup_identity
GROUP BY
    c.id, c.customer_name, c.phone_number, c.email,
    mt.tier_name, mt.discount_rate,
    c.current_points, c.total_spending, c.last_visit_date;

-- Query 9: Website member portal purchase history and points.
SELECT
    i.id AS invoice_id,
    i.invoice_date,
    i.invoice_time,
    i.sales_channel,
    b.branch_name,
    i.total_amount,
    i.points_earned,
    COALESCE(v.voucher_code, 'No voucher') AS voucher_code
FROM invoices i
JOIN branches b ON b.id = i.branch_id
LEFT JOIN vouchers v ON v.id = i.voucher_id
WHERE i.customer_id = 1
  AND i.status = 'paid'
ORDER BY i.invoice_date DESC, i.invoice_time DESC
LIMIT 5;

-- Query 10: Email campaign delivery, engagement, voucher redemption, and revenue.
SELECT
    me.id AS marketing_email_id,
    me.email_subject,
    p.promotion_name,
    p.campaign_channel,
    COUNT(cr.id) AS recipients,
    SUM(CASE WHEN cr.delivery_status IN ('sent', 'opened', 'clicked') THEN 1 ELSE 0 END) AS sent_count,
    SUM(CASE WHEN cr.opened_at IS NOT NULL THEN 1 ELSE 0 END) AS opened_count,
    SUM(CASE WHEN cr.clicked_at IS NOT NULL THEN 1 ELSE 0 END) AS clicked_count,
    SUM(CASE WHEN v.status = 'redeemed' THEN 1 ELSE 0 END) AS redeemed_voucher_count,
    COALESCE(SUM(CASE WHEN i.status = 'paid' THEN i.total_amount ELSE 0 END), 0) AS attributed_revenue
FROM marketing_emails me
LEFT JOIN promotions p ON p.id = me.promotion_id
LEFT JOIN campaign_recipients cr ON cr.marketing_email_id = me.id
LEFT JOIN vouchers v ON v.id = cr.voucher_id
LEFT JOIN invoices i ON i.voucher_id = v.id
GROUP BY me.id, me.email_subject, p.promotion_name, p.campaign_channel
ORDER BY attributed_revenue DESC, redeemed_voucher_count DESC;

-- Query 11: POS session cash closing control.
SELECT
    ps.id AS pos_session_id,
    b.branch_name,
    s.staff_name,
    ps.opened_at,
    ps.closed_at,
    ps.status,
    ps.opening_cash_amount,
    COALESCE(SUM(CASE WHEN pay.payment_method = 'cash' THEN pay.amount ELSE 0 END), 0) AS cash_sales_amount,
    ps.opening_cash_amount
        + COALESCE(SUM(CASE WHEN pay.payment_method = 'cash' THEN pay.amount ELSE 0 END), 0) AS calculated_expected_cash,
    ps.closing_cash_amount,
    ps.cash_difference_amount
FROM pos_sessions ps
JOIN branches b ON b.id = ps.branch_id
JOIN staff s ON s.id = ps.staff_id
LEFT JOIN invoices i ON i.pos_session_id = ps.id AND i.status = 'paid'
LEFT JOIN payments pay ON pay.invoice_id = i.id AND pay.status = 'paid'
GROUP BY
    ps.id, b.branch_name, s.staff_name, ps.opened_at, ps.closed_at,
    ps.status, ps.opening_cash_amount, ps.closing_cash_amount, ps.cash_difference_amount
ORDER BY ps.opened_at DESC;

-- Query 12: Customers eligible for automatic membership tier upgrade.
SELECT
    c.id AS customer_id,
    c.customer_name,
    current_tier.tier_name AS current_tier,
    earned_tier.tier_name AS earned_tier,
    c.total_spending,
    c.current_points
FROM customers c
JOIN membership_tiers current_tier ON current_tier.id = c.membership_tier_id
JOIN membership_tiers earned_tier
    ON earned_tier.min_total_spending = (
        SELECT MAX(mt2.min_total_spending)
        FROM membership_tiers mt2
        WHERE mt2.min_total_spending <= c.total_spending
    )
WHERE earned_tier.id <> c.membership_tier_id
ORDER BY c.total_spending DESC;

-- Query 13: Omnichannel revenue split proving POS, website, and delivery integration.
SELECT
    i.sales_channel,
    COUNT(i.id) AS paid_invoice_count,
    COUNT(DISTINCT i.customer_id) AS unique_customers,
    SUM(i.points_earned) AS points_earned,
    SUM(i.total_amount) AS net_revenue
FROM invoices i
WHERE i.status = 'paid'
  AND i.invoice_date >= @report_month_start
  AND i.invoice_date < @report_month_end
GROUP BY i.sales_channel
ORDER BY net_revenue DESC;
