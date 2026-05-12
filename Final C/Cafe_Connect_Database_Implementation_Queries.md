# Part C: System Design and Advanced Modelling

## V. Database Implementation & Queries

### 5.1. SQL Schema

Phần Database Implementation xây dựng cơ sở dữ liệu cho hệ thống CRM/POS của chuỗi Café Connect. Mục tiêu của schema là lưu trữ tập trung dữ liệu khách hàng, hạng thành viên, voucher, chiến dịch marketing, hóa đơn bán hàng, chi tiết hóa đơn, tồn kho chi nhánh, nhân viên và lịch sử điểm tích lũy.

Schema được thiết kế theo hướng quan hệ, có đầy đủ khóa chính, khóa ngoại, ràng buộc duy nhất và ràng buộc kiểm tra dữ liệu. Các bảng được đặt tên theo dạng `snake_case` để dễ triển khai trong MySQL/MariaDB, nhưng vẫn tương ứng trực tiếp với các thực thể nghiệp vụ trong báo cáo như `Customer`, `Membership Tiers`, `Voucher`, `Promotion`, `Invoices`, `Invoice details`, `Products`, `Branch`, `Branch_Inventory` và `Staff`.

File SQL đi kèm: `cafe_connect_schema_queries.sql`.

---

## 1. Tổng quan các bảng chính

| Bảng | Khóa chính | Khóa ngoại chính | Ý nghĩa nghiệp vụ |
|---|---|---|---|
| `membership_tiers` | `id` | - | Lưu các hạng thành viên Bronze, Silver, Gold, tỷ lệ giảm giá và ngưỡng chi tiêu. |
| `customers` | `id` | `membership_tier_id` -> `membership_tiers.id` | Lưu hồ sơ khách hàng, số điện thoại, email, điểm hiện tại và tổng chi tiêu. |
| `branches` | `id` | - | Lưu thông tin 5 chi nhánh Café Connect tại Hà Nội. |
| `staff` | `id` | `branch_id` -> `branches.id` | Lưu nhân viên, vai trò và chi nhánh làm việc. |
| `products` | `id` | - | Lưu menu sản phẩm, danh mục, giá bán và ghi chú pha chế. |
| `promotions` | `id` | - | Lưu chiến dịch marketing, thời gian áp dụng, nhóm khách hàng mục tiêu và giá trị giảm giá. |
| `vouchers` | `id` | `customer_id` -> `customers.id`, `promotion_id` -> `promotions.id` | Lưu mã voucher được phát hành cho từng khách hàng trong từng chiến dịch. |
| `invoices` | `id` | `branch_id`, `staff_id`, `customer_id`, `voucher_id` | Lưu hóa đơn bán hàng, tổng tiền, giảm giá, điểm nhận và phương thức thanh toán. |
| `invoice_details` | `id` | `invoice_id` -> `invoices.id`, `product_id` -> `products.id` | Lưu chi tiết từng sản phẩm trong hóa đơn. |
| `branch_inventory` | `id` | `branch_id` -> `branches.id`, `product_id` -> `products.id` | Lưu tồn kho sản phẩm theo từng chi nhánh và mức tồn tối thiểu. |
| `loyalty_point_transactions` | `id` | `customer_id` -> `customers.id`, `invoice_id` -> `invoices.id` | Lưu lịch sử cộng, trừ hoặc điều chỉnh điểm tích lũy. |

---

## 2. Các ràng buộc quan trọng

| Nhóm ràng buộc | Mô tả |
|---|---|
| Khóa chính | Mỗi bảng có cột `id` tự tăng làm khóa chính. |
| Khóa ngoại | Các bảng giao dịch như `vouchers`, `invoices`, `invoice_details`, `branch_inventory`, `loyalty_point_transactions` liên kết với bảng cha để đảm bảo toàn vẹn dữ liệu. |
| Unique | `customers.phone_number`, `customers.email`, `vouchers.voucher_code`, `branches.branch_name`, `products.product_name` là duy nhất để tránh dữ liệu trùng. |
| Tồn kho | `branch_inventory` có unique `(branch_id, product_id)` để mỗi sản phẩm chỉ có một dòng tồn kho tại một chi nhánh. |
| Giá trị số | Giá sản phẩm, tổng tiền, tồn kho, điểm tích lũy và tỷ lệ giảm giá không được âm. |
| Ngày tháng | `promotions.start_date <= promotions.end_date`; `vouchers.release_date <= vouchers.expiration_date`. |
| Voucher | Một voucher chỉ được gắn tối đa với một hóa đơn qua unique `invoices.voucher_id`, giúp tránh dùng lại voucher đã thanh toán. |

---

## 3. Mô tả nghiệp vụ theo nhóm bảng

### 3.1. Nhóm khách hàng và thành viên

Hai bảng `customers` và `membership_tiers` hỗ trợ quản lý quan hệ khách hàng. Mỗi khách hàng thuộc một hạng thành viên, có điểm hiện tại và tổng chi tiêu. Hệ thống có thể dùng `total_spending` để xét nâng hạng từ Bronze lên Silver hoặc Gold.

Các trường quan trọng:

- `customers.phone_number`: dùng để tra cứu khách hàng tại POS.
- `customers.current_points`: điểm tích lũy hiện tại.
- `customers.total_spending`: tổng chi tiêu dùng cho phân hạng thành viên.
- `membership_tiers.discount_rate`: tỷ lệ giảm giá mặc định theo hạng.

### 3.2. Nhóm chi nhánh, nhân viên và sản phẩm

Các bảng `branches`, `staff` và `products` phục vụ vận hành bán hàng tại 5 cửa hàng. Mỗi nhân viên thuộc một chi nhánh. Mỗi sản phẩm có giá bán, danh mục và trạng thái hoạt động.

Schema có sẵn dữ liệu mẫu cho 5 chi nhánh:

- Cầu Giấy
- Hoàn Kiếm
- Đống Đa
- Thanh Xuân
- Tây Hồ

### 3.3. Nhóm marketing, voucher và chiến dịch

Hai bảng `promotions` và `vouchers` hỗ trợ triển khai chiến dịch marketing. Một chiến dịch có thể phát hành nhiều voucher cho nhiều khách hàng. Voucher có trạng thái như `issued`, `active`, `redeemed`, `expired`, `cancelled`.

Thiết kế này giúp hệ thống đo lường:

- Số voucher đã phát hành.
- Số voucher đã sử dụng.
- Tỷ lệ sử dụng voucher.
- Doanh thu phát sinh từ các hóa đơn có dùng voucher.

### 3.4. Nhóm bán hàng và hóa đơn

Hai bảng `invoices` và `invoice_details` lưu nghiệp vụ bán hàng tại POS. Bảng `invoices` lưu thông tin tổng quan của giao dịch, còn `invoice_details` lưu từng sản phẩm trong hóa đơn.

Các trường quan trọng:

- `invoices.subtotal_amount`: tổng tiền trước giảm giá.
- `invoices.membership_discount_amount`: giảm giá theo hạng thành viên.
- `invoices.voucher_discount_amount`: giảm giá từ voucher.
- `invoices.total_amount`: số tiền khách thực trả.
- `invoices.points_earned`: điểm cộng sau thanh toán thành công.
- `invoice_details.quantity`, `unit_price`, `line_total`: chi tiết sản phẩm trong hóa đơn.

### 3.5. Nhóm tồn kho và điểm tích lũy

Bảng `branch_inventory` cho phép theo dõi tồn kho sản phẩm theo chi nhánh. Nếu `stock_quantity < min_stock_level`, hệ thống có thể hiển thị cảnh báo sắp hết hàng.

Bảng `loyalty_point_transactions` lưu lịch sử điểm của khách hàng. Việc tách bảng này giúp truy vết lý do cộng/trừ điểm, thay vì chỉ lưu tổng điểm trong bảng `customers`.

---

## 4. SQL Schema

Phần schema đầy đủ nằm trong file:

```text
Final C/cafe_connect_schema_queries.sql
```

File này bao gồm:

- Lệnh tạo database `cafe_connect_crm`.
- Lệnh tạo bảng và ràng buộc.
- Dữ liệu mẫu cho 5 chi nhánh, khách hàng, sản phẩm, nhân viên, voucher, hóa đơn và tồn kho.
- Các truy vấn phân tích ở mục 5.2.

---

## 5.2. Queries

Phần này mô tả các truy vấn phân tích chính phục vụ chăm sóc khách hàng, đánh giá marketing và tối ưu vận hành. Các truy vấn chi tiết đã được viết trong file `cafe_connect_schema_queries.sql`.

### Query 1: Thống kê 10 khách hàng chi tiêu nhiều nhất trong tháng

Mục đích của truy vấn là tìm ra nhóm khách hàng có giá trị cao trong tháng để bộ phận chăm sóc khách hàng hoặc Marketing có thể gửi ưu đãi đặc biệt.

Kết quả trả về gồm:

- Mã khách hàng.
- Tên khách hàng.
- Số điện thoại.
- Hạng thành viên.
- Số hóa đơn đã thanh toán.
- Tổng chi tiêu trong tháng.
- Tổng điểm nhận trong tháng.

```sql
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
```

Ý nghĩa nghiệp vụ: Café Connect có thể dùng danh sách này để chăm sóc khách hàng VIP, gửi voucher riêng hoặc mời tham gia chương trình thành viên cao hơn.

---

### Query 2: Đánh giá hiệu quả chiến dịch marketing

Truy vấn này đo tỷ lệ voucher đã sử dụng so với số voucher đã phát hành cho từng chiến dịch. Đây là chỉ số quan trọng để đánh giá chiến dịch marketing có thực sự thúc đẩy khách hàng quay lại hay không.

Kết quả trả về gồm:

- Mã chiến dịch.
- Tên chiến dịch.
- Thời gian áp dụng.
- Nhóm khách hàng mục tiêu.
- Số voucher đã phát hành.
- Số voucher đã sử dụng.
- Tỷ lệ sử dụng voucher.
- Doanh thu từ các hóa đơn dùng voucher.

```sql
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
```

Ý nghĩa nghiệp vụ: Nếu một chiến dịch có tỷ lệ sử dụng voucher thấp, Marketing cần điều chỉnh nhóm khách hàng mục tiêu, giá trị giảm giá hoặc kênh gửi thông báo.

---

### Query 3: Phân tích khung giờ khách hàng thành viên thường đến cửa hàng

Truy vấn này thống kê số lượt ghé quán của khách hàng thành viên theo từng khung giờ và từng chi nhánh. Kết quả giúp quản lý cửa hàng tối ưu phân ca nhân viên vào các khung giờ cao điểm.

Kết quả trả về gồm:

- Tên chi nhánh.
- Giờ trong ngày.
- Khung giờ.
- Số lượt khách thành viên.
- Số khách thành viên duy nhất.
- Doanh thu trong khung giờ.

```sql
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
```

Ý nghĩa nghiệp vụ: Nếu Cầu Giấy có nhiều khách thành viên vào 7h-9h sáng, quản lý có thể tăng nhân sự thu ngân và pha chế trong khung giờ này.

---

### Query 4: Top sản phẩm bán chạy theo chi nhánh

Truy vấn này giúp xác định sản phẩm nào bán tốt tại từng chi nhánh, hỗ trợ quyết định nhập hàng, thiết kế combo và điều chỉnh menu.

```sql
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
```

---

### Query 5: Cảnh báo tồn kho dưới mức tối thiểu

Truy vấn này phát hiện sản phẩm tại chi nhánh có tồn kho thấp hơn mức tối thiểu, giúp quản lý chủ động nhập hàng.

```sql
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
```

---

### Query 6: Doanh thu theo chi nhánh trong tháng

Truy vấn này tổng hợp doanh thu theo từng chi nhánh, bao gồm tổng tiền trước giảm giá, tổng giảm giá và doanh thu thực nhận.

```sql
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
```

---

### Query 7: Danh sách khách hàng lâu chưa quay lại

Truy vấn này tìm khách hàng không phát sinh giao dịch trong hơn 30 ngày để Marketing gửi voucher kích hoạt lại.

```sql
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
```

Ý nghĩa nghiệp vụ: Đây là nhóm khách hàng phù hợp với chiến dịch `Inactive Customer Reactivation`.

---

## 6. Tổng kết

Thiết kế database cho Café Connect CRM/POS hỗ trợ đầy đủ các nghiệp vụ chính của chuỗi cửa hàng: quản lý khách hàng, thành viên, điểm tích lũy, voucher, chiến dịch marketing, bán hàng tại POS, tồn kho chi nhánh và báo cáo quản trị. Các truy vấn phân tích giúp Ban quản lý ra quyết định dựa trên dữ liệu thay vì tổng hợp thủ công từ từng cửa hàng.

Schema và queries được tách ra file SQL riêng để có thể chạy thử, kiểm tra ràng buộc và dùng làm minh chứng cho phần Database Implementation trong báo cáo.
