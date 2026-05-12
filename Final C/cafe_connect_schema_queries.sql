CREATE DATABASE IF NOT EXISTS cafe_connect_crm
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE cafe_connect_crm;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS loyalty_point_transactions;
DROP TABLE IF EXISTS branch_inventory;
DROP TABLE IF EXISTS invoice_details;
DROP TABLE IF EXISTS invoices;
DROP TABLE IF EXISTS vouchers;
DROP TABLE IF EXISTS promotions;
DROP TABLE IF EXISTS products;
DROP TABLE IF EXISTS staff;
DROP TABLE IF EXISTS branches;
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
    address VARCHAR(255) NULL,
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
    discount_type ENUM('fixed', 'percentage') NOT NULL DEFAULT 'fixed',
    discount_value DECIMAL(12,2) NOT NULL DEFAULT 0,
    status ENUM('draft', 'active', 'cancelled', 'completed') NOT NULL DEFAULT 'draft',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_promotions_date_range CHECK (start_date <= end_date),
    CONSTRAINT chk_promotions_discount_value CHECK (discount_value >= 0)
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

CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    branch_id INT NOT NULL,
    staff_id INT NOT NULL,
    customer_id INT NULL,
    voucher_id INT NULL,
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
    CONSTRAINT fk_invoices_customer
        FOREIGN KEY (customer_id) REFERENCES customers(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL,
    CONSTRAINT fk_invoices_voucher
        FOREIGN KEY (voucher_id) REFERENCES vouchers(id)
        ON UPDATE CASCADE
        ON DELETE SET NULL
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
CREATE INDEX idx_vouchers_promotion_status ON vouchers(promotion_id, status);
CREATE INDEX idx_invoices_date_branch ON invoices(invoice_date, branch_id);
CREATE INDEX idx_invoices_customer_date ON invoices(customer_id, invoice_date);
CREATE INDEX idx_invoice_details_product ON invoice_details(product_id);
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

INSERT INTO promotions (
    promotion_name, description, start_date, end_date, target_segment, discount_type, discount_value, status
) VALUES
('Birthday Voucher', 'Birthday discount for members in their birthday month.', '2026-05-01', '2026-05-31', 'birthday', 'fixed', 20000, 'active'),
('Weekend Combo', 'Weekend combo promotion for returning customers.', '2026-05-10', '2026-05-31', 'all', 'percentage', 15, 'active'),
('Gold Member Appreciation', 'Special campaign for Gold members.', '2026-05-01', '2026-06-15', 'gold', 'percentage', 20, 'active'),
('Inactive Customer Reactivation', 'Voucher to bring inactive customers back after 30 days.', '2026-04-01', '2026-05-31', 'inactive', 'fixed', 30000, 'active');

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
