# Part C: System Design and Advanced Modelling

## IV. User Interface Design

### 1. Mục tiêu thiết kế giao diện

Phần User Interface Design mô tả các màn hình chính của hệ thống Web Quản lý Sự kiện và Câu lạc bộ Sinh viên. Giao diện được thiết kế dựa trên các thực thể đã xác định trong ERD, bao gồm `students`, `users`, `clubs`, `events`, `event_registrations`, `checkin_logs`, `student_points`, `semesters`, `certificates` và `notifications`.

Mục tiêu của thiết kế UI là giúp từng nhóm người dùng thao tác đúng nghiệp vụ:

- Sinh viên có thể theo dõi hồ sơ cá nhân, sự kiện đã đăng ký, điểm rèn luyện và chứng nhận.
- Club Manager có thể tạo sự kiện, quản lý đăng ký và thực hiện check-in QR.
- Admin có thể tra cứu sinh viên, kiểm soát dữ liệu, theo dõi điểm và chứng nhận.
- Hệ thống tự động cập nhật trạng thái đăng ký, ghi nhận check-in, cộng điểm và cấp chứng nhận sau khi sinh viên tham gia sự kiện hợp lệ.

Các giao diện dưới đây không phải là thiết kế đồ họa cuối cùng, mà là mô tả wireframe ở mức phân tích hệ thống để làm rõ dữ liệu, chức năng và luồng thao tác.

---

## 2. Giao diện 1: Student Profile Search & Detail

### 2.1. Thực thể liên quan

Thực thể chính của giao diện là `Student`. Giao diện này cho phép Admin hoặc Club Manager tìm kiếm sinh viên bằng mã sinh viên, họ tên, email hoặc số điện thoại, sau đó xem hồ sơ chi tiết của sinh viên.

### 2.2. Người dùng chính

| Người dùng | Mục đích sử dụng |
|---|---|
| Admin | Tra cứu thông tin sinh viên, kiểm tra tài khoản, điểm rèn luyện và chứng nhận. |
| Club Manager | Kiểm tra thông tin sinh viên khi quản lý thành viên CLB hoặc danh sách tham gia sự kiện. |
| Student | Xem hồ sơ cá nhân, CLB đang tham gia, sự kiện đã đăng ký, điểm và chứng nhận. |

### 2.3. Dữ liệu liên quan

| Bảng | Trường dữ liệu sử dụng |
|---|---|
| `users` | `id`, `full_name`, `email`, `role_id`, `status`, `created_at` |
| `students` | `id`, `user_id`, `student_code`, `class_name`, `faculty`, `phone`, `date_of_birth` |
| `club_members` | `club_id`, `student_id`, `member_role`, `joined_at`, `status` |
| `clubs` | `club_name`, `status` |
| `event_registrations` | `event_id`, `student_id`, `registered_at`, `registration_status` |
| `student_points` | `points_awarded`, `semester_id`, `awarded_at`, `note` |
| `semesters` | `semester_name`, `academic_year`, `status` |
| `certificates` | `certificate_code`, `issued_at`, `file_path`, `status` |

### 2.4. Thành phần giao diện

| Khu vực UI | Mô tả |
|---|---|
| Thanh tìm kiếm | Cho phép nhập mã sinh viên, họ tên, email hoặc số điện thoại. |
| Bộ lọc | Lọc theo khoa, lớp, trạng thái tài khoản hoặc CLB đang tham gia. |
| Bảng kết quả | Hiển thị danh sách sinh viên phù hợp với điều kiện tìm kiếm. |
| Hồ sơ chi tiết | Hiển thị thông tin cá nhân, tài khoản, lớp, khoa, số điện thoại. |
| Tab CLB | Hiển thị các CLB sinh viên đang tham gia và vai trò trong từng CLB. |
| Tab sự kiện | Hiển thị sự kiện đã đăng ký, trạng thái đăng ký và trạng thái tham dự. |
| Tab điểm | Hiển thị điểm rèn luyện theo học kỳ. |
| Tab chứng nhận | Hiển thị chứng nhận đã cấp và trạng thái chứng nhận. |

### 2.5. Luồng thao tác

| Bước | Hành động | Kết quả |
|---:|---|---|
| 1 | Người dùng nhập từ khóa tìm kiếm. | Hệ thống truy vấn `students` kết hợp với `users`. |
| 2 | Người dùng chọn một sinh viên trong bảng kết quả. | Hệ thống mở hồ sơ chi tiết của sinh viên. |
| 3 | Người dùng chuyển giữa các tab CLB, sự kiện, điểm, chứng nhận. | Hệ thống tải dữ liệu liên quan từ các bảng tương ứng. |
| 4 | Admin hoặc Club Manager kiểm tra thông tin cần thiết. | Hỗ trợ nghiệp vụ quản lý thành viên, đăng ký, điểm và chứng nhận. |

### 2.6. Validation và quy tắc xử lý

| Trường hợp | Quy tắc |
|---|---|
| Tìm kiếm rỗng | Hiển thị thông báo yêu cầu nhập từ khóa hoặc chọn bộ lọc. |
| Không tìm thấy sinh viên | Hiển thị trạng thái "No matching student found". |
| Tài khoản bị khóa | Hiển thị nhãn `locked` từ `users.status` và hạn chế thao tác chỉnh sửa. |
| Sinh viên không có điểm | Hiển thị tổng điểm bằng 0 theo học kỳ được chọn. |
| Chứng nhận bị thu hồi | Hiển thị trạng thái `revoked` từ `certificates.status`. |

### 2.7. Kết quả đầu ra

Giao diện giúp người dùng xem được hồ sơ sinh viên đầy đủ, bao gồm thông tin cá nhân, CLB tham gia, lịch sử đăng ký sự kiện, điểm rèn luyện và chứng nhận. Đây là màn hình trung tâm để Admin và Club Manager kiểm tra thông tin trước khi xử lý các nghiệp vụ liên quan.

### 2.8. Wireframe

```text
+--------------------------------------------------------------------------------+
| Student Profile Search                                                         |
+--------------------------------------------------------------------------------+
| Search: [ student code / name / email / phone                    ] [Search]     |
| Faculty: [All v]   Class: [All v]   Account Status: [All v]   Club: [All v]    |
+--------------------------------------------------------------------------------+
| Results                                                                        |
|--------------------------------------------------------------------------------|
| Student Code | Full Name        | Faculty        | Class      | Status | View   |
| SV001        | Nguyen Van A     | IT             | KTPM01     | active | [Open] |
| SV002        | Tran Thi B       | Business       | QTKD02     | active | [Open] |
+--------------------------------------------------------------------------------+
| Student Detail                                                                 |
|--------------------------------------------------------------------------------|
| Full name: Nguyen Van A              Student code: SV001                       |
| Email: vana@student.edu.vn            Phone: 090xxxxxxx                         |
| Faculty: IT                           Class: KTPM01                             |
| Account status: active                Date of birth: 2004-01-10                 |
+--------------------------------------------------------------------------------+
| [Clubs] [Events] [Points] [Certificates]                                       |
|--------------------------------------------------------------------------------|
| Clubs: IT Club - member - active                                               |
| Events: AI Workshop - approved; Career Seminar - attended                      |
| Points: Semester 2 2025-2026 - 8 points                                        |
| Certificates: CERT-2026-001 - issued                                           |
+--------------------------------------------------------------------------------+
```

---

## 3. Giao diện 2: Event Create / Edit

### 3.1. Thực thể liên quan

Thực thể chính của giao diện là `Event`. Giao diện này cho phép Club Manager tạo mới hoặc chỉnh sửa sự kiện thuộc CLB của mình. Admin có thể sử dụng giao diện này để kiểm tra và quản lý toàn bộ sự kiện trong hệ thống.

### 3.2. Người dùng chính

| Người dùng | Mục đích sử dụng |
|---|---|
| Club Manager | Tạo sự kiện mới, chỉnh sửa nội dung, đăng ảnh, đặt giới hạn số lượng và thời hạn đăng ký. |
| Admin | Kiểm tra sự kiện, khóa hoặc hủy sự kiện nếu có sai phạm. |

### 3.3. Dữ liệu liên quan

| Bảng | Trường dữ liệu sử dụng |
|---|---|
| `events` | `club_id`, `category_id`, `title`, `description`, `event_date`, `start_time`, `end_time`, `location`, `capacity`, `registration_deadline`, `checkin_qr_code`, `status`, `created_by` |
| `clubs` | `id`, `club_name`, `president_user_id`, `status` |
| `event_categories` | `id`, `category_name`, `status` |
| `event_images` | `event_id`, `image_url`, `is_thumbnail` |
| `notifications` | `user_id`, `title`, `content`, `is_read`, `created_at` |

### 3.4. Thành phần giao diện

| Khu vực UI | Mô tả |
|---|---|
| Thông tin cơ bản | Nhập tên sự kiện, mô tả, CLB tổ chức và loại sự kiện. |
| Thời gian và địa điểm | Chọn ngày, giờ bắt đầu, giờ kết thúc và địa điểm. |
| Đăng ký | Nhập sức chứa, hạn đăng ký và trạng thái sự kiện. |
| Hình ảnh | Tải ảnh banner hoặc ảnh minh họa cho sự kiện. |
| Hành động | Lưu nháp, xuất bản, hủy hoặc cập nhật sự kiện. |

### 3.5. Luồng thao tác

| Bước | Hành động | Kết quả |
|---:|---|---|
| 1 | Club Manager mở màn hình tạo sự kiện. | Hệ thống hiển thị form nhập thông tin sự kiện. |
| 2 | Người dùng nhập thông tin cơ bản, thời gian, địa điểm và sức chứa. | Dữ liệu được kiểm tra theo validation. |
| 3 | Người dùng tải ảnh minh họa nếu có. | Hệ thống lưu ảnh vào `event_images`. |
| 4 | Người dùng chọn `Save Draft` hoặc `Publish`. | Sự kiện được lưu với trạng thái `draft` hoặc `published`. |
| 5 | Nếu sự kiện được publish, hệ thống có thể gửi thông báo. | Bản ghi `notifications` được tạo cho sinh viên phù hợp. |

### 3.6. Validation và quy tắc xử lý

| Trường hợp | Quy tắc |
|---|---|
| Tên sự kiện rỗng | Không cho lưu, hiển thị lỗi bắt buộc nhập `title`. |
| Sức chứa không hợp lệ | `capacity` phải lớn hơn 0 theo ràng buộc `chk_events_capacity`. |
| Thời gian sai | `start_time` phải nhỏ hơn `end_time` theo ràng buộc `chk_events_time_range`. |
| Hạn đăng ký không hợp lệ | `registration_deadline` phải trước thời điểm bắt đầu sự kiện. |
| Loại sự kiện không hoạt động | Không cho chọn `event_categories.status = inactive`. |
| CLB không hoạt động | Không cho tạo sự kiện nếu `clubs.status` không phải `active`. |
| Người tạo không có quyền | Club Manager chỉ được tạo sự kiện cho CLB mình quản lý. |

### 3.7. Kết quả đầu ra

Giao diện tạo ra hoặc cập nhật bản ghi trong bảng `events`. Nếu có ảnh, hệ thống tạo thêm bản ghi trong `event_images`. Khi sự kiện được xuất bản, sinh viên có thể nhìn thấy sự kiện và đăng ký tham gia.

### 3.8. Wireframe

```text
+--------------------------------------------------------------------------------+
| Create / Edit Event                                                            |
+--------------------------------------------------------------------------------+
| Club: [ IT Club v ]                  Category: [ Workshop v ]                  |
| Title: [ Introduction to AI Workshop                                  ]        |
| Description:                                                                  |
| [ Event description, objectives, speaker information...                 ]      |
+--------------------------------------------------------------------------------+
| Date: [2026-05-20]   Start: [08:00]   End: [11:00]                            |
| Location: [ Hall A - Main Campus                                      ]        |
| Capacity: [100]       Registration deadline: [2026-05-18 23:59]               |
+--------------------------------------------------------------------------------+
| Images                                                                         |
| [Upload banner]  Thumbnail: [x] image_01.jpg                                   |
+--------------------------------------------------------------------------------+
| Status: [draft v]                                                              |
| [Save Draft]   [Publish Event]   [Cancel Event]                                |
+--------------------------------------------------------------------------------+
| Validation messages                                                            |
| - Capacity must be greater than 0.                                              |
| - Start time must be earlier than end time.                                    |
+--------------------------------------------------------------------------------+
```

---

## 4. Giao diện 3: Event Registration Management & QR Check-in

### 4.1. Thực thể liên quan

Thực thể chính của giao diện là `Event Registration` và `Check-in Log`. Đây là giao diện phục vụ Club Manager hoặc Admin trong quá trình quản lý danh sách đăng ký và xác nhận sinh viên tham dự sự kiện bằng mã QR.

### 4.2. Người dùng chính

| Người dùng | Mục đích sử dụng |
|---|---|
| Club Manager | Duyệt đăng ký, kiểm tra danh sách sinh viên và check-in người tham dự. |
| Admin | Giám sát tình trạng tham dự, kiểm tra log check-in và xử lý trường hợp đặc biệt. |

### 4.3. Dữ liệu liên quan

| Bảng | Trường dữ liệu sử dụng |
|---|---|
| `events` | `id`, `title`, `capacity`, `event_date`, `location`, `status` |
| `event_registrations` | `id`, `event_id`, `student_id`, `registered_at`, `registration_status`, `qr_token` |
| `students` | `id`, `student_code`, `class_name`, `faculty` |
| `users` | `full_name`, `email` |
| `checkin_logs` | `event_registration_id`, `checkin_time`, `checkin_method`, `device_info`, `is_valid` |
| `activity_point_rules` | `category_id`, `point_value`, `is_active` |
| `student_points` | `student_id`, `event_id`, `points_awarded`, `semester_id`, `note` |
| `certificates` | `student_id`, `event_id`, `certificate_code`, `issued_at`, `status` |

### 4.4. Thành phần giao diện

| Khu vực UI | Mô tả |
|---|---|
| Thông tin sự kiện | Hiển thị tên sự kiện, ngày, địa điểm, sức chứa và trạng thái. |
| Thống kê đăng ký | Hiển thị số lượng pending, approved, attended, cancelled. |
| Danh sách đăng ký | Hiển thị sinh viên đã đăng ký và trạng thái của từng lượt đăng ký. |
| QR scanner | Khu vực nhập hoặc quét `qr_token`. |
| Kết quả check-in | Hiển thị hợp lệ/không hợp lệ, thông tin sinh viên và trạng thái xử lý. |
| Log check-in | Hiển thị lịch sử check-in, phương thức, thời gian và thiết bị. |

### 4.5. Luồng thao tác

| Bước | Hành động | Kết quả |
|---:|---|---|
| 1 | Club Manager chọn một sự kiện đã publish. | Hệ thống tải thông tin sự kiện và danh sách đăng ký. |
| 2 | Người dùng duyệt các đăng ký hợp lệ nếu cần. | `registration_status` chuyển từ `pending` sang `approved`. |
| 3 | Khi sinh viên đến tham dự, Club Manager quét QR. | Hệ thống đọc `qr_token` từ bảng `event_registrations`. |
| 4 | Hệ thống kiểm tra QR có hợp lệ và chưa check-in hay không. | Nếu hợp lệ, tiếp tục xử lý trong transaction. |
| 5 | Hệ thống cập nhật trạng thái tham dự. | `event_registrations.registration_status = attended`. |
| 6 | Hệ thống ghi log check-in. | Tạo bản ghi trong `checkin_logs`. |
| 7 | Hệ thống cộng điểm và cấp chứng nhận nếu đủ điều kiện. | Tạo bản ghi trong `student_points` và `certificates`. |

### 4.6. Validation và quy tắc xử lý

| Trường hợp | Quy tắc |
|---|---|
| QR không tồn tại | Hiển thị lỗi "Invalid QR token". |
| Sinh viên chưa đăng ký | Không cho check-in. |
| Đăng ký đã bị hủy | Không cho check-in nếu `registration_status = cancelled`. |
| Sinh viên đã check-in | Không tạo thêm log vì `checkin_logs.event_registration_id` là duy nhất. |
| Sự kiện bị hủy | Không cho check-in nếu `events.status = cancelled`. |
| Check-in hợp lệ | Cập nhật trạng thái attended, tạo log, cộng điểm và cấp chứng nhận trong cùng transaction. |
| Cộng điểm trùng | Không cộng lại nếu đã có bản ghi `student_points` theo cặp `(student_id, event_id)`. |
| Cấp chứng nhận trùng | Không cấp lại nếu đã có bản ghi `certificates` theo cặp `(student_id, event_id)`. |

### 4.7. Kết quả đầu ra

Giao diện giúp Club Manager kiểm soát chính xác số lượng sinh viên tham dự. Việc dùng `qr_token` và ràng buộc duy nhất của `checkin_logs.event_registration_id` giúp ngăn check-in trùng. Sau check-in hợp lệ, hệ thống tự động ghi nhận điểm rèn luyện và tạo chứng nhận cho sinh viên nếu sự kiện có chính sách cấp chứng nhận.

### 4.8. Wireframe

```text
+--------------------------------------------------------------------------------+
| Event Registration & QR Check-in                                               |
+--------------------------------------------------------------------------------+
| Event: [ Introduction to AI Workshop v ]                                       |
| Date: 2026-05-20      Location: Hall A      Capacity: 100      Status: published|
+--------------------------------------------------------------------------------+
| Summary                                                                        |
| Pending: 12     Approved: 76     Attended: 45     Cancelled: 3                 |
+--------------------------------------------------------------------------------+
| QR Check-in                                                                    |
| QR token: [ paste or scan QR token here                              ] [Scan]  |
| Result: VALID - Nguyen Van A - SV001 - checked in at 08:05                    |
+--------------------------------------------------------------------------------+
| Registration List                                                              |
|--------------------------------------------------------------------------------|
| Student Code | Full Name      | Faculty | Status    | Registered At | Action   |
| SV001        | Nguyen Van A   | IT      | attended  | 2026-05-01    | [View]   |
| SV002        | Tran Thi B     | Business| approved  | 2026-05-02    | [Check]  |
| SV003        | Le Van C       | IT      | pending   | 2026-05-03    | [Approve]|
+--------------------------------------------------------------------------------+
| Check-in Logs                                                                  |
| Time              | Student Code | Method | Device        | Valid              |
| 2026-05-20 08:05  | SV001        | qr     | Gate Tablet 1 | true               |
+--------------------------------------------------------------------------------+
```

---

## 5. Giao diện 4: Student Points & Certificates

### 5.1. Thực thể liên quan

Thực thể chính của giao diện là `Student Points` và `Certificates`. Giao diện này cho phép sinh viên xem điểm rèn luyện đã được cộng theo từng học kỳ, đồng thời xem hoặc tải chứng nhận từ các sự kiện đã tham gia.

### 5.2. Người dùng chính

| Người dùng | Mục đích sử dụng |
|---|---|
| Student | Theo dõi tổng điểm rèn luyện và tải chứng nhận tham gia sự kiện. |
| Admin | Kiểm tra lịch sử cộng điểm, trạng thái chứng nhận và xử lý khiếu nại dữ liệu. |
| Club Manager | Xem danh sách chứng nhận/điểm liên quan đến sự kiện do CLB tổ chức. |

### 5.3. Dữ liệu liên quan

| Bảng | Trường dữ liệu sử dụng |
|---|---|
| `student_points` | `student_id`, `event_id`, `points_awarded`, `awarded_at`, `semester_id`, `note` |
| `semesters` | `semester_name`, `academic_year`, `start_date`, `end_date`, `status` |
| `events` | `title`, `event_date`, `category_id`, `club_id` |
| `event_categories` | `category_name` |
| `certificates` | `certificate_code`, `issued_at`, `file_path`, `status` |
| `students` | `student_code`, `faculty`, `class_name` |
| `users` | `full_name`, `email` |

### 5.4. Thành phần giao diện

| Khu vực UI | Mô tả |
|---|---|
| Bộ lọc học kỳ | Chọn học kỳ và năm học để xem điểm. |
| Tổng quan điểm | Hiển thị tổng điểm trong học kỳ được chọn. |
| Bảng lịch sử điểm | Hiển thị sự kiện, loại sự kiện, điểm được cộng và ngày cộng điểm. |
| Danh sách chứng nhận | Hiển thị mã chứng nhận, sự kiện, ngày cấp, trạng thái và nút tải file. |
| Ghi chú nghiệp vụ | Hiển thị ghi chú cộng điểm hoặc lý do thu hồi chứng nhận nếu có. |

### 5.5. Luồng thao tác

| Bước | Hành động | Kết quả |
|---:|---|---|
| 1 | Sinh viên mở màn hình điểm và chứng nhận. | Hệ thống mặc định chọn học kỳ đang active. |
| 2 | Sinh viên chọn học kỳ khác nếu cần. | Hệ thống tải dữ liệu từ `student_points` theo `semester_id`. |
| 3 | Sinh viên xem danh sách điểm theo từng sự kiện. | Hệ thống hiển thị điểm, ngày cộng và ghi chú. |
| 4 | Sinh viên mở tab chứng nhận. | Hệ thống hiển thị chứng nhận từ bảng `certificates`. |
| 5 | Sinh viên chọn tải chứng nhận. | Hệ thống mở hoặc tải file từ `certificates.file_path`. |

### 5.6. Validation và quy tắc xử lý

| Trường hợp | Quy tắc |
|---|---|
| Không có điểm trong học kỳ | Hiển thị tổng điểm bằng 0 và thông báo chưa có hoạt động được ghi nhận. |
| Chứng nhận bị thu hồi | Không cho tải file nếu `certificates.status = revoked`. |
| Chứng nhận hết hạn | Hiển thị trạng thái `expired` để sinh viên biết chứng nhận không còn hiệu lực. |
| Điểm bị trùng | Hệ thống không hiển thị bản ghi trùng vì `student_points` duy nhất theo `(student_id, event_id)`. |
| Không tìm thấy file chứng nhận | Hiển thị thông báo lỗi và cho phép gửi yêu cầu hỗ trợ. |

### 5.7. Kết quả đầu ra

Giao diện giúp sinh viên tự theo dõi quá trình tham gia hoạt động ngoại khóa, điểm rèn luyện và chứng nhận. Với Admin, giao diện này hỗ trợ kiểm tra dữ liệu điểm theo học kỳ và phát hiện lỗi nghiệp vụ nếu có khiếu nại từ sinh viên.

### 5.8. Wireframe

```text
+--------------------------------------------------------------------------------+
| Student Points & Certificates                                                  |
+--------------------------------------------------------------------------------+
| Student: Nguyen Van A - SV001        Faculty: IT        Class: KTPM01          |
| Semester: [Hoc ky 2 v]  Academic year: [2025-2026 v]      [Apply Filter]       |
+--------------------------------------------------------------------------------+
| Point Summary                                                                  |
| Total points: 11        Active semester: Yes                                   |
+--------------------------------------------------------------------------------+
| Point History                                                                  |
|--------------------------------------------------------------------------------|
| Event                       | Category    | Points | Awarded At       | Note   |
| Introduction to AI Workshop | Workshop    | 3      | 2026-05-20 08:10 | QR OK  |
| Green Sunday Volunteer      | Volunteer   | 5      | 2026-04-10 09:00 | QR OK  |
| Career Seminar              | Seminar     | 3      | 2026-03-15 14:00 | QR OK  |
+--------------------------------------------------------------------------------+
| Certificates                                                                   |
|--------------------------------------------------------------------------------|
| Code          | Event                       | Issued At        | Status | File |
| CERT-2026-001 | Introduction to AI Workshop | 2026-05-20 08:15 | issued | [Download] |
| CERT-2026-002 | Career Seminar              | 2026-03-15 14:30 | issued | [Download] |
+--------------------------------------------------------------------------------+
```

---

## 6. Liên kết giữa UI và ERD

Các giao diện trên được thiết kế trực tiếp từ mô hình dữ liệu của hệ thống. Màn hình hồ sơ sinh viên sử dụng nhóm bảng `users`, `students`, `club_members`, `clubs`, `event_registrations`, `student_points` và `certificates`. Màn hình tạo sự kiện sử dụng `events`, `clubs`, `event_categories` và `event_images`. Màn hình check-in QR sử dụng `event_registrations`, `checkin_logs`, `student_points` và `certificates`. Màn hình điểm và chứng nhận sử dụng `student_points`, `semesters`, `events` và `certificates`.

Thiết kế này giúp đảm bảo giao diện không tách rời mô hình dữ liệu. Mỗi thao tác quan trọng trên UI đều tương ứng với một hoặc nhiều bảng trong ERD, đồng thời tuân thủ các ràng buộc quan trọng như không đăng ký trùng, không check-in trùng, không cộng điểm hai lần cho cùng một sự kiện và không cấp trùng chứng nhận cho cùng một sinh viên.

## 7. Tổng kết

Phần User Interface Design tập trung vào bốn giao diện cốt lõi của hệ thống Web Quản lý Sự kiện và Câu lạc bộ Sinh viên. Các giao diện này bao phủ các nghiệp vụ chính: quản lý hồ sơ sinh viên, tạo sự kiện, quản lý đăng ký/check-in QR, cộng điểm rèn luyện và cấp chứng nhận. Với thiết kế này, hệ thống có thể hỗ trợ đầy đủ ba nhóm người dùng chính là Admin, Club Manager và Student, đồng thời bảo đảm dữ liệu được xử lý nhất quán theo ERD đã xây dựng.
