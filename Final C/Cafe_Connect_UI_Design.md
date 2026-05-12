# Part C: System Design and Advanced Modelling

## IV. User Interface Design

### 1. Mục tiêu thiết kế giao diện

Phần User Interface Design mô tả các giao diện chính của hệ thống CRM/POS cho chuỗi Café Connect. Hệ thống được thiết kế để hỗ trợ 5 cửa hàng tại Hà Nội trong các nghiệp vụ: quản lý khách hàng, chương trình thành viên, tích điểm, voucher, bán hàng tại quầy, quản lý tồn kho chi nhánh và báo cáo vận hành.

Các giao diện được thiết kế dựa trên các thực thể chính trong mô hình dữ liệu của hệ thống, bao gồm `Customer`, `Membership Tiers`, `Voucher`, `Promotion`, `Invoices`, `Invoice details`, `Products`, `Branch`, `Branch_Inventory` và `Staff`.

Mục tiêu của UI là giúp từng nhóm người dùng thao tác nhanh, chính xác và nhất quán:

- Thu ngân có thể tra cứu khách hàng bằng số điện thoại, xem hạng thành viên, áp dụng voucher và tạo hóa đơn tại POS.
- Bộ phận Marketing có thể tạo chiến dịch khuyến mãi, phát hành voucher và theo dõi hiệu quả sử dụng.
- Quản lý cửa hàng có thể theo dõi doanh thu, tồn kho, sản phẩm bán chạy và hiệu suất nhân viên.
- Ban giám đốc có thể xem báo cáo tổng hợp theo từng chi nhánh và toàn chuỗi.

Các wireframe bên dưới là mô tả ở mức phân tích hệ thống, không phải thiết kế đồ họa cuối cùng. Mục đích là làm rõ bố cục, dữ liệu hiển thị, hành động chính và quy tắc nghiệp vụ của từng màn hình.

---

## 2. Giao diện 1: Customer Profile Search & Detail

### 2.1. Thực thể liên quan

Thực thể chính của giao diện là `Customer`. Màn hình này cho phép thu ngân, quản lý cửa hàng hoặc Admin tìm kiếm khách hàng bằng số điện thoại, email hoặc tên, sau đó xem hồ sơ CRM chi tiết của khách hàng.

### 2.2. Người dùng chính

| Người dùng | Mục đích sử dụng |
|---|---|
| Thu ngân | Tra cứu nhanh khách hàng tại quầy để áp dụng hạng thành viên, điểm tích lũy hoặc voucher. |
| Quản lý cửa hàng | Kiểm tra lịch sử mua hàng, phản hồi và mức độ trung thành của khách tại chi nhánh. |
| Admin / Ban quản lý | Quản lý hồ sơ khách hàng toàn chuỗi, kiểm tra dữ liệu trùng lặp và phân tích hành vi mua hàng. |

### 2.3. Dữ liệu liên quan

| Thực thể / Bảng | Trường dữ liệu sử dụng |
|---|---|
| `Customer` | `Customer_ID`, `Customer_name`, `Phone_Number`, `Email`, `Gender`, `Address`, `Current_points`, `MT_ID` |
| `Membership Tiers` | `MT_ID`, `Tier_name`, `Discount_rate`, `Description` |
| `Voucher` | `Voucher_ID`, `Customer_ID`, `Promotion_ID`, `Release_date`, `Expiration_date`, `Status` |
| `Promotion` | `Promotion_ID`, `Promotion_name`, `Start_date`, `End_date`, `Description` |
| `Invoices` | `Invoice_ID`, `Staff_ID`, `Voucher_ID`, `Customer_ID`, `Date`, `Time`, `Points_Earned`, `Total amount`, `Payment method` |
| `Branch` | `Branch_ID`, `Branch_name`, `Address` |

### 2.4. Thành phần giao diện

| Khu vực UI | Mô tả |
|---|---|
| Thanh tìm kiếm | Nhập số điện thoại, email hoặc tên khách hàng. |
| Bộ lọc | Lọc theo hạng thành viên Đồng, Bạc, Vàng hoặc trạng thái khách hàng. |
| Bảng kết quả | Hiển thị danh sách khách hàng phù hợp với từ khóa tìm kiếm. |
| Hồ sơ khách hàng | Hiển thị thông tin cá nhân, hạng thành viên, điểm hiện tại và mức giảm giá. |
| Lịch sử mua hàng | Hiển thị các hóa đơn gần nhất, tổng tiền, phương thức thanh toán và điểm đã nhận. |
| Voucher khả dụng | Hiển thị voucher còn hạn, voucher đã dùng hoặc voucher hết hạn. |
| Hành động nhanh | Sửa hồ sơ, áp dụng voucher, gửi ưu đãi hoặc xem lịch sử chi tiết. |

### 2.5. Luồng thao tác

| Bước | Hành động | Kết quả |
|---:|---|---|
| 1 | Thu ngân nhập số điện thoại hoặc tên khách hàng. | Hệ thống truy vấn `Customer` và hiển thị kết quả phù hợp. |
| 2 | Thu ngân chọn một khách hàng trong danh sách. | Hệ thống mở hồ sơ CRM chi tiết của khách hàng. |
| 3 | Hệ thống hiển thị hạng thành viên và điểm tích lũy. | Thu ngân biết khách thuộc hạng Đồng, Bạc hoặc Vàng. |
| 4 | Thu ngân kiểm tra voucher khả dụng. | Hệ thống hiển thị voucher có thể áp dụng tại POS. |
| 5 | Người dùng xem lịch sử mua hàng nếu cần. | Hệ thống hiển thị hóa đơn gần nhất và hành vi mua hàng. |

### 2.6. Validation và quy tắc xử lý

| Trường hợp | Quy tắc |
|---|---|
| Không nhập từ khóa | Hiển thị thông báo yêu cầu nhập số điện thoại, email hoặc tên khách hàng. |
| Không tìm thấy khách hàng | Cho phép tạo hồ sơ khách hàng mới nếu khách đồng ý đăng ký thành viên. |
| Số điện thoại trùng | Hiển thị cảnh báo dữ liệu trùng để Admin kiểm tra và hợp nhất nếu cần. |
| Voucher hết hạn | Không cho áp dụng voucher có `Expiration_date` nhỏ hơn ngày hiện tại. |
| Voucher đã sử dụng | Không cho áp dụng voucher có `Status = Redeemed`. |
| Khách hàng chưa có hạng thành viên | Gán mặc định hạng Đồng hoặc hạng cơ bản của hệ thống. |

### 2.7. Kết quả đầu ra

Giao diện giúp thu ngân nhận diện khách hàng nhanh tại quầy, hỗ trợ cá nhân hóa ưu đãi, áp dụng voucher đúng điều kiện và giảm thời gian phục vụ trong giờ cao điểm. Với quản lý và Marketing, màn hình này cung cấp dữ liệu nền để phân tích khách hàng trung thành, khách lâu chưa quay lại và hiệu quả khuyến mãi.

### 2.8. Wireframe

```text
+--------------------------------------------------------------------------------+
| Customer Profile Search                                                        |
+--------------------------------------------------------------------------------+
| Search: [ phone number / customer name / email                     ] [Search]  |
| Tier: [All v]     Branch: [All v]     Voucher Status: [All v]                  |
+--------------------------------------------------------------------------------+
| Search Results                                                                 |
|--------------------------------------------------------------------------------|
| Customer ID | Name          | Phone       | Tier   | Points | Status | Action  |
| C001        | Nguyen An     | 090xxxx001  | Gold   | 1250   | Active | [Open]  |
| C002        | Tran Binh     | 090xxxx002  | Silver | 620    | Active | [Open]  |
+--------------------------------------------------------------------------------+
| Customer Detail                                                                |
|--------------------------------------------------------------------------------|
| Name: Nguyen An                  Phone: 090xxxx001                             |
| Email: an.nguyen@email.com        Gender: Male                                  |
| Address: Cau Giay, Hanoi          Membership: Gold - 10% discount              |
| Current points: 1250              Last visit: 2026-05-01                       |
+--------------------------------------------------------------------------------+
| [Purchase History] [Available Vouchers] [Profile Notes]                        |
|--------------------------------------------------------------------------------|
| Invoice ID | Branch   | Date       | Amount    | Payment | Points Earned       |
| INV001     | Cau Giay | 2026-05-01 | 120,000đ  | E-wallet| 12                  |
| INV002     | Tay Ho   | 2026-04-20 | 85,000đ   | Cash    | 8                   |
+--------------------------------------------------------------------------------+
| Voucher ID | Promotion             | Expiration Date | Status    | Action       |
| V001       | Birthday Voucher      | 2026-05-30      | Active    | [Apply]      |
| V002       | Weekend Combo         | 2026-04-30      | Expired   | Disabled     |
+--------------------------------------------------------------------------------+
| [Edit Profile] [Send Promotion] [Use in POS Order]                             |
+--------------------------------------------------------------------------------+
```

---

## 3. Giao diện 2: Marketing Campaign & Voucher Management

### 3.1. Thực thể liên quan

Thực thể chính của giao diện là `Promotion` và `Voucher`. Màn hình này cho phép bộ phận Marketing tạo chiến dịch khuyến mãi, xác định nhóm khách hàng mục tiêu, phát hành voucher và theo dõi hiệu quả sử dụng voucher.

### 3.2. Người dùng chính

| Người dùng | Mục đích sử dụng |
|---|---|
| Marketing | Tạo chiến dịch khuyến mãi, phát hành voucher và đo lường hiệu quả. |
| Admin / Ban quản lý | Duyệt, hủy hoặc kiểm soát chiến dịch khuyến mãi toàn chuỗi. |
| Quản lý cửa hàng | Theo dõi chiến dịch đang áp dụng tại chi nhánh và tỷ lệ khách sử dụng voucher. |

### 3.3. Dữ liệu liên quan

| Thực thể / Bảng | Trường dữ liệu sử dụng |
|---|---|
| `Promotion` | `Promotion_ID`, `Promotion_name`, `Start_date`, `End_date`, `Description` |
| `Voucher` | `Voucher_ID`, `Customer_ID`, `Promotion_ID`, `Release_date`, `Expiration_date`, `Status` |
| `Customer` | `Customer_ID`, `Customer_name`, `Current_points`, `MT_ID` |
| `Membership Tiers` | `MT_ID`, `Tier_name`, `Discount_rate` |
| `Invoices` | `Invoice_ID`, `Voucher_ID`, `Customer_ID`, `Total amount`, `Date`, `Payment method` |

### 3.4. Thành phần giao diện

| Khu vực UI | Mô tả |
|---|---|
| Form chiến dịch | Nhập tên chiến dịch, mô tả, ngày bắt đầu, ngày kết thúc và trạng thái. |
| Nhóm khách hàng mục tiêu | Chọn tất cả khách hàng, khách hạng Đồng, Bạc, Vàng, khách sinh nhật hoặc khách lâu chưa quay lại. |
| Thiết lập voucher | Nhập số lượng voucher, ngày hết hạn, giá trị giảm và giới hạn sử dụng. |
| Bảng chiến dịch | Hiển thị danh sách chiến dịch, thời gian áp dụng, trạng thái và số voucher đã dùng. |
| Bảng voucher | Hiển thị voucher đã phát hành, khách hàng nhận voucher, trạng thái và ngày hết hạn. |
| Báo cáo hiệu quả | Hiển thị tỷ lệ sử dụng voucher và doanh thu phát sinh từ chiến dịch. |

### 3.5. Luồng thao tác

| Bước | Hành động | Kết quả |
|---:|---|---|
| 1 | Marketing nhập thông tin chiến dịch. | Hệ thống tạo bản ghi nháp cho `Promotion`. |
| 2 | Người dùng chọn nhóm khách hàng mục tiêu. | Hệ thống lọc danh sách `Customer` theo hạng thành viên hoặc hành vi mua hàng. |
| 3 | Người dùng thiết lập số lượng và thời hạn voucher. | Hệ thống chuẩn bị danh sách voucher sẽ phát hành. |
| 4 | Người dùng chọn Launch Campaign. | Hệ thống phát hành voucher cho khách hàng phù hợp. |
| 5 | Khi khách sử dụng voucher tại POS, trạng thái voucher được cập nhật. | Marketing theo dõi được tỷ lệ sử dụng và hiệu quả chiến dịch. |

### 3.6. Validation và quy tắc xử lý

| Trường hợp | Quy tắc |
|---|---|
| Thiếu tên chiến dịch | Không cho lưu nếu `Promotion_name` rỗng. |
| Ngày kết thúc không hợp lệ | `End_date` phải sau hoặc bằng `Start_date`. |
| Ngày hết hạn voucher không hợp lệ | `Expiration_date` phải nằm trong hoặc sau thời gian chiến dịch theo chính sách của hệ thống. |
| Không có khách hàng mục tiêu | Hiển thị cảnh báo và không phát hành voucher. |
| Voucher đã hết hạn | Tự động chuyển trạng thái sang `Expired`. |
| Voucher đã dùng | Không cho sử dụng lại voucher có trạng thái `Redeemed`. |
| Chiến dịch bị hủy | Các voucher chưa sử dụng có thể chuyển sang `Cancelled`. |

### 3.7. Kết quả đầu ra

Giao diện giúp Marketing quản lý toàn bộ vòng đời chiến dịch khuyến mãi, từ tạo chương trình, phát hành voucher đến đo lường tỷ lệ sử dụng. Dữ liệu này hỗ trợ đánh giá chiến dịch nào làm tăng khách quay lại, doanh thu và mức độ tương tác của từng nhóm thành viên.

### 3.8. Wireframe

```text
+--------------------------------------------------------------------------------+
| Marketing Campaign & Voucher Management                                        |
+--------------------------------------------------------------------------------+
| Campaign Name: [ Weekend Combo Promotion                              ]        |
| Description:                                                                   |
| [ Discount combo for returning customers during weekend...              ]      |
| Start Date: [2026-05-10]    End Date: [2026-05-31]    Status: [Draft v]       |
+--------------------------------------------------------------------------------+
| Target Segment                                                                 |
| ( ) All customers   ( ) Bronze   ( ) Silver   (x) Gold                         |
| ( ) Birthday customers this month   ( ) Inactive customers over 30 days         |
+--------------------------------------------------------------------------------+
| Voucher Setup                                                                  |
| Discount Type: [Percentage v]    Discount Value: [15%]                         |
| Number of Vouchers: [500]       Expiration Date: [2026-06-15]                  |
| Usage Limit: [1 use per customer]                                               |
| [Save Draft] [Launch Campaign] [Cancel Campaign]                               |
+--------------------------------------------------------------------------------+
| Campaign List                                                                  |
|--------------------------------------------------------------------------------|
| ID | Name                   | Start      | End        | Status | Used/Issued   |
| P1 | Birthday Voucher       | 2026-05-01 | 2026-05-31 | Active | 82/300        |
| P2 | Weekend Combo          | 2026-05-10 | 2026-05-31 | Draft  | 0/500         |
+--------------------------------------------------------------------------------+
| Voucher List                                                                   |
|--------------------------------------------------------------------------------|
| Voucher ID | Customer    | Promotion        | Expiration | Status   | Action   |
| V001       | Nguyen An   | Birthday Voucher | 2026-05-30 | Active   | [View]   |
| V002       | Tran Binh   | Weekend Combo    | 2026-06-15 | Issued   | [View]   |
+--------------------------------------------------------------------------------+
| Performance: Usage Rate 27.3% | Revenue from Campaign 15,200,000đ              |
+--------------------------------------------------------------------------------+
```

---

## 4. Giao diện 3: POS Order / Invoice With Membership & Voucher

### 4.1. Thực thể liên quan

Thực thể chính của giao diện là `Order / Invoice`. Đây là màn hình bán hàng tại quầy, tích hợp tra cứu khách hàng, áp dụng voucher, tính giảm giá, tạo hóa đơn và cộng điểm thành viên sau khi thanh toán thành công.

### 4.2. Người dùng chính

| Người dùng | Mục đích sử dụng |
|---|---|
| Thu ngân | Tạo đơn hàng, tra cứu thành viên, áp dụng voucher, xử lý thanh toán và in hóa đơn. |
| Quản lý cửa hàng | Kiểm soát giao dịch, duyệt hủy hóa đơn hoặc xử lý sự cố tại quầy. |
| Admin | Theo dõi dữ liệu bán hàng, kiểm soát gian lận và cấu hình phân quyền. |

### 4.3. Dữ liệu liên quan

| Thực thể / Bảng | Trường dữ liệu sử dụng |
|---|---|
| `Invoices` | `Invoice_ID`, `Staff_ID`, `Voucher_ID`, `Customer_ID`, `Date`, `Time`, `Points_Earned`, `Total amount`, `Payment method` |
| `Invoice details` | `Invoices_Details_ID`, `Invoice_ID`, `Product_ID`, `Quantity`, `Unit_price`, `Size`, `Topping` |
| `Products` | `Product_ID`, `Product_Name`, `Price`, `Take_note` |
| `Customer` | `Customer_ID`, `Customer_name`, `Phone_Number`, `Current_points`, `MT_ID` |
| `Membership Tiers` | `MT_ID`, `Tier_name`, `Discount_rate` |
| `Voucher` | `Voucher_ID`, `Customer_ID`, `Promotion_ID`, `Expiration_date`, `Status` |
| `Staff` | `Staff_ID`, `Staff_name`, `Staff_role`, `Branch_ID` |
| `Branch` | `Branch_ID`, `Branch_name`, `Address` |

### 4.4. Thành phần giao diện

| Khu vực UI | Mô tả |
|---|---|
| Menu sản phẩm | Hiển thị sản phẩm, giá, size và ghi chú pha chế. |
| Giỏ hàng | Hiển thị sản phẩm đã chọn, số lượng, size, topping, đơn giá và thành tiền. |
| Tra cứu thành viên | Nhập số điện thoại hoặc quét mã thành viên để lấy thông tin khách hàng. |
| Thông tin khách hàng | Hiển thị hạng thành viên, điểm hiện có và voucher khả dụng. |
| Áp dụng voucher | Chọn voucher, kiểm tra điều kiện và tính giảm giá. |
| Tổng kết đơn hàng | Hiển thị tạm tính, giảm giá, tổng tiền cuối cùng và điểm sẽ nhận. |
| Thanh toán | Chọn tiền mặt, thẻ hoặc ví điện tử. |
| Hành động | Tạo hóa đơn, thanh toán, in hóa đơn, hủy đơn. |

### 4.5. Luồng thao tác

| Bước | Hành động | Kết quả |
|---:|---|---|
| 1 | Thu ngân chọn sản phẩm từ menu. | Sản phẩm được thêm vào giỏ hàng và tạo dòng `Invoice details` tạm thời. |
| 2 | Thu ngân nhập số điện thoại khách hàng. | Hệ thống tra cứu `Customer`, `Membership Tiers` và voucher khả dụng. |
| 3 | Thu ngân chọn voucher nếu khách muốn sử dụng. | Hệ thống kiểm tra trạng thái, hạn dùng và điều kiện áp dụng. |
| 4 | Hệ thống tính tổng tiền cuối cùng. | Tổng tiền = tạm tính - giảm giá từ voucher/hạng thành viên. |
| 5 | Khách hàng thanh toán. | Hệ thống tạo `Invoices` chính thức. |
| 6 | Sau thanh toán thành công, hệ thống cộng điểm. | `Points_Earned` được cập nhật vào hóa đơn và `Current_points` của khách hàng tăng. |
| 7 | Voucher đã dùng được cập nhật trạng thái. | `Voucher.Status` chuyển sang `Redeemed`. |
| 8 | Thu ngân in hoặc gửi hóa đơn. | Giao dịch hoàn tất và dữ liệu được đồng bộ cho báo cáo. |

### 4.6. Validation và quy tắc xử lý

| Trường hợp | Quy tắc |
|---|---|
| Giỏ hàng rỗng | Không cho tạo hóa đơn. |
| Sản phẩm không tồn tại | Không cho thêm sản phẩm vào giỏ hàng. |
| Số lượng không hợp lệ | `Quantity` phải lớn hơn 0. |
| Khách không phải thành viên | Cho phép thanh toán như khách vãng lai, không cộng điểm thành viên. |
| Voucher hết hạn | Không cho áp dụng voucher có trạng thái `Expired` hoặc quá `Expiration_date`. |
| Voucher đã dùng | Không cho áp dụng voucher có trạng thái `Redeemed`. |
| Thanh toán thất bại | Không tạo hóa đơn chính thức, không cộng điểm, không đổi trạng thái voucher. |
| Hủy hóa đơn | Chỉ quản lý cửa hàng hoặc Admin được hủy hóa đơn sau khi xác thực quyền. |

### 4.7. Kết quả đầu ra

Giao diện POS giúp Café Connect giảm thao tác thủ công tại quầy, tăng tốc độ phục vụ và đảm bảo chính xác khi áp dụng ưu đãi. Sau mỗi giao dịch, dữ liệu hóa đơn, chi tiết hóa đơn, điểm tích lũy và trạng thái voucher được cập nhật đồng bộ, tạo cơ sở cho báo cáo doanh thu và phân tích khách hàng.

### 4.8. Wireframe

```text
+--------------------------------------------------------------------------------+
| POS Order Screen - Café Connect                                                |
+--------------------------------------------------------------------------------+
| Branch: [Cau Giay v]       Staff: Le Thu Ngan       Time: 2026-05-05 14:20     |
+--------------------------------------------------------------------------------+
| Member Lookup                                                                  |
| Phone / Member ID: [090xxxx001                                  ] [Lookup]     |
| Customer: Nguyen An | Tier: Gold | Points: 1250 | Available vouchers: 2        |
+----------------------------------------------+---------------------------------+
| Product Menu                                  | Cart                            |
|----------------------------------------------|---------------------------------|
| [Espresso] 45,000đ                           | Product       Qty Size Subtotal |
| [Cafe Sua Da] 35,000đ                        | Cafe Sua Da    2   M    70,000 |
| [Latte] 55,000đ                              | Tiramisu       1   -    45,000 |
| [Matcha Latte] 60,000đ                       |                                 |
| [Tiramisu] 45,000đ                           |                                 |
+----------------------------------------------+---------------------------------+
| Voucher: [Birthday Voucher - 20,000đ v] [Validate] [Apply]                    |
| Order Summary                                                                   |
| Subtotal: 115,000đ                                                              |
| Membership Discount: 10%                                                        |
| Voucher Discount: 20,000đ                                                       |
| Final Amount: 83,500đ                                                           |
| Points Earned: 8                                                                |
+--------------------------------------------------------------------------------+
| Payment Method: ( ) Cash   ( ) Card   (x) E-wallet                             |
| [Create Invoice] [Process Payment] [Print Receipt] [Cancel Order]              |
+--------------------------------------------------------------------------------+
```

---

## 5. Giao diện 4: Branch Inventory & Reporting Dashboard

### 5.1. Thực thể liên quan

Thực thể chính của giao diện là `Branch Inventory / Reporting`. Màn hình này phục vụ quản lý cửa hàng và Ban giám đốc trong việc theo dõi doanh thu, sản phẩm bán chạy, hiệu suất nhân viên và tình trạng tồn kho tại từng chi nhánh.

### 5.2. Người dùng chính

| Người dùng | Mục đích sử dụng |
|---|---|
| Quản lý cửa hàng | Theo dõi doanh thu, đơn hàng, tồn kho và cảnh báo sản phẩm/nguyên liệu sắp hết. |
| Ban giám đốc | So sánh hiệu quả giữa 5 chi nhánh và xem báo cáo toàn chuỗi. |
| Admin | Kiểm tra dữ liệu chi nhánh, sản phẩm, nhân viên và báo cáo vận hành. |

### 5.3. Dữ liệu liên quan

| Thực thể / Bảng | Trường dữ liệu sử dụng |
|---|---|
| `Branch` | `Branch_ID`, `Staff_ID`, `Branch_name`, `Address` |
| `Branch_Inventory` | `BI_ID`, `Branch_ID`, `Product_ID`, `Stock_Quantity`, `Min_stock_level`, `Last_Updated` |
| `Products` | `Product_ID`, `Product_Name`, `Price`, `Take_note` |
| `Invoices` | `Invoice_ID`, `Staff_ID`, `Customer_ID`, `Date`, `Time`, `Total amount`, `Payment method` |
| `Invoice details` | `Invoice_ID`, `Product_ID`, `Quantity`, `Unit_price`, `Size`, `Topping` |
| `Staff` | `Staff_ID`, `Staff_name`, `Staff_role`, `Phone_number`, `Email`, `Branch_ID` |
| `Customer` | `Customer_ID`, `MT_ID`, `Current_points` |

### 5.4. Thành phần giao diện

| Khu vực UI | Mô tả |
|---|---|
| Bộ lọc chi nhánh | Chọn Cầu Giấy, Hoàn Kiếm, Đống Đa, Thanh Xuân, Tây Hồ hoặc toàn chuỗi. |
| Bộ lọc thời gian | Xem dữ liệu theo ngày, tuần, tháng hoặc khoảng thời gian tùy chọn. |
| Thẻ chỉ số | Doanh thu hôm nay, số đơn, khách mới, khách quay lại, tỷ lệ dùng voucher. |
| Biểu đồ doanh thu | Hiển thị doanh thu theo thời gian hoặc theo chi nhánh. |
| Bảng sản phẩm bán chạy | Hiển thị top sản phẩm theo số lượng bán và doanh thu. |
| Bảng tồn kho | Hiển thị số lượng tồn, mức tồn tối thiểu, trạng thái và ngày cập nhật. |
| Cảnh báo tồn kho | Gắn nhãn Low Stock nếu số lượng nhỏ hơn mức tối thiểu. |
| Hiệu suất nhân viên | Hiển thị số đơn xử lý và doanh thu theo nhân viên. |

### 5.5. Luồng thao tác

| Bước | Hành động | Kết quả |
|---:|---|---|
| 1 | Quản lý chọn chi nhánh và khoảng thời gian. | Hệ thống lọc dữ liệu hóa đơn, sản phẩm và tồn kho. |
| 2 | Hệ thống tổng hợp doanh thu và số đơn. | Hiển thị các chỉ số trên dashboard. |
| 3 | Hệ thống phân tích sản phẩm bán chạy. | Hiển thị bảng top sản phẩm dựa trên `Invoice details`. |
| 4 | Hệ thống kiểm tra tồn kho theo chi nhánh. | So sánh `Stock_Quantity` với `Min_stock_level`. |
| 5 | Nếu tồn kho thấp, hệ thống hiển thị cảnh báo. | Quản lý có thể lập kế hoạch nhập hàng. |
| 6 | Ban giám đốc xem báo cáo toàn chuỗi. | Hỗ trợ so sánh hiệu quả vận hành giữa các chi nhánh. |

### 5.6. Validation và quy tắc xử lý

| Trường hợp | Quy tắc |
|---|---|
| Không chọn chi nhánh | Mặc định hiển thị dữ liệu toàn chuỗi. |
| Khoảng thời gian không hợp lệ | Ngày bắt đầu phải nhỏ hơn hoặc bằng ngày kết thúc. |
| Tồn kho thấp | Nếu `Stock_Quantity < Min_stock_level`, hiển thị cảnh báo `Low Stock`. |
| Không có dữ liệu bán hàng | Hiển thị trạng thái rỗng thay vì biểu đồ sai lệch. |
| Nhân viên không thuộc chi nhánh | Không hiển thị trong báo cáo chi nhánh đã chọn. |
| Dữ liệu cập nhật cũ | Nếu `Last_Updated` quá lâu, hiển thị nhãn cần kiểm tra lại tồn kho. |

### 5.7. Kết quả đầu ra

Giao diện dashboard giúp quản lý và Ban giám đốc theo dõi hiệu quả vận hành theo thời gian thực. Dữ liệu doanh thu, sản phẩm bán chạy, tồn kho và hiệu suất nhân viên giúp Café Connect ra quyết định nhanh hơn về nhập hàng, phân ca, điều chỉnh menu và triển khai khuyến mãi theo từng chi nhánh.

### 5.8. Wireframe

```text
+--------------------------------------------------------------------------------+
| Branch Inventory & Reporting Dashboard                                         |
+--------------------------------------------------------------------------------+
| Branch: [All Branches v]       Date Range: [2026-05-01] to [2026-05-05] [Apply]|
+--------------------------------------------------------------------------------+
| Today Revenue | Orders | New Customers | Returning Customers | Voucher Usage   |
| 18,500,000đ   | 245    | 38            | 126                 | 31%             |
+--------------------------------------------------------------------------------+
| Revenue Chart                                                                 |
| [Line/bar chart: revenue by day or by branch]                                  |
+--------------------------------------------------------------------------------+
| Top-selling Products                                                           |
|--------------------------------------------------------------------------------|
| Product        | Quantity Sold | Revenue      | Branch                         |
| Cafe Sua Da    | 180           | 6,300,000đ   | All                            |
| Latte          | 96            | 5,280,000đ   | Cau Giay                       |
| Tiramisu       | 72            | 3,240,000đ   | Hoan Kiem                      |
+--------------------------------------------------------------------------------+
| Inventory                                                                      |
|--------------------------------------------------------------------------------|
| Branch    | Product        | Stock | Min Level | Last Updated       | Status   |
| Cau Giay  | Cafe Sua Da    | 35    | 20        | 2026-05-05 09:00   | OK       |
| Tay Ho    | Tiramisu       | 8     | 15        | 2026-05-05 09:30   | Low Stock|
| Dong Da   | Latte          | 18    | 20        | 2026-05-04 21:00   | Low Stock|
+--------------------------------------------------------------------------------+
| Staff Performance                                                              |
|--------------------------------------------------------------------------------|
| Staff Name     | Role      | Orders Processed | Revenue Handled               |
| Le Thu Ngan    | Cashier   | 68               | 5,450,000đ                    |
| Pham Quan Ly   | Manager   | 24               | 2,100,000đ                    |
+--------------------------------------------------------------------------------+
```

---

## 6. Liên kết giữa UI và ERD

Các giao diện trên được thiết kế trực tiếp từ các thực thể nghiệp vụ của hệ thống Café Connect CRM/POS. Màn hình Customer Profile sử dụng nhóm dữ liệu `Customer`, `Membership Tiers`, `Voucher`, `Promotion` và `Invoices`. Màn hình Marketing Campaign sử dụng `Promotion`, `Voucher`, `Customer` và `Membership Tiers`. Màn hình POS Order sử dụng `Invoices`, `Invoice details`, `Products`, `Customer`, `Voucher`, `Staff` và `Branch`. Màn hình Branch Inventory & Reporting sử dụng `Branch`, `Branch_Inventory`, `Products`, `Invoices`, `Invoice details` và `Staff`.

Thiết kế UI này đảm bảo mỗi thao tác trên giao diện đều liên kết với một nghiệp vụ và một nhóm dữ liệu cụ thể. Khi khách mua hàng, POS tạo hóa đơn, cập nhật chi tiết hóa đơn, áp dụng voucher và cộng điểm. Khi Marketing tạo chiến dịch, hệ thống phát hành voucher cho nhóm khách hàng phù hợp. Khi quản lý xem dashboard, hệ thống tổng hợp dữ liệu từ hóa đơn, sản phẩm và tồn kho để hỗ trợ ra quyết định.

## 7. Tổng kết

Phần User Interface Design tập trung vào bốn giao diện cốt lõi của hệ thống Café Connect CRM/POS: quản lý hồ sơ khách hàng, quản lý chiến dịch khuyến mãi, bán hàng tại POS và dashboard tồn kho/báo cáo. Các giao diện này bao phủ các nghiệp vụ chính của chuỗi cửa hàng cà phê, đồng thời đáp ứng yêu cầu thiết kế tối thiểu ba thực thể trong phần System Design and Advanced Modelling.
