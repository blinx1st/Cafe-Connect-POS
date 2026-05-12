# ERD - Web Quan Ly Su Kien Va Cau Lac Bo Sinh Vien

## 1. Tong quan

He thong quan ly cau lac bo va su kien sinh vien gom 15 bang chinh. Mo hinh du lieu tap trung vao cac nghiep vu:

- Quan ly tai khoan va phan quyen: `roles`, `users`.
- Quan ly ho so sinh vien va cau lac bo: `students`, `clubs`, `club_members`.
- Quan ly su kien, dang ky va check-in QR: `event_categories`, `events`, `event_images`, `event_registrations`, `checkin_logs`.
- Quan ly diem ren luyen, hoc ky va chung nhan: `activity_point_rules`, `student_points`, `semesters`, `certificates`.
- Gui thong bao he thong: `notifications`.

## 2. Danh sach bang

| Bang | Khoa chinh | Khoa ngoai chinh | Y nghia |
|---|---|---|---|
| `roles` | `id` | - | Luu danh sach vai tro nhu Admin, Club Manager, Student. |
| `users` | `id` | `role_id` -> `roles.id` | Tai khoan dang nhap cua toan he thong. |
| `students` | `id` | `user_id` -> `users.id` | Thong tin rieng cua sinh vien. |
| `clubs` | `id` | `president_user_id` -> `users.id` | Thong tin cau lac bo va nguoi dai dien. |
| `club_members` | `id` | `club_id` -> `clubs.id`, `student_id` -> `students.id` | Quan he thanh vien giua sinh vien va CLB. |
| `event_categories` | `id` | - | Phan loai su kien: Workshop, Seminar, Volunteer, Competition, Club Meeting. |
| `events` | `id` | `club_id` -> `clubs.id`, `category_id` -> `event_categories.id`, `created_by` -> `users.id` | Bang trung tam luu thong tin su kien. |
| `event_images` | `id` | `event_id` -> `events.id` | Anh banner hoac anh minh hoa su kien. |
| `event_registrations` | `id` | `event_id` -> `events.id`, `student_id` -> `students.id` | Luot dang ky tham gia su kien. |
| `checkin_logs` | `id` | `event_registration_id` -> `event_registrations.id` | Lich su check-in, ngan quet QR trung. |
| `activity_point_rules` | `id` | `category_id` -> `event_categories.id` | Quy tac cong diem theo loai su kien. |
| `student_points` | `id` | `student_id` -> `students.id`, `event_id` -> `events.id`, `semester_id` -> `semesters.id` | Lich su diem ren luyen da cong cho sinh vien. |
| `semesters` | `id` | - | Hoc ky va nam hoc de tong hop diem. |
| `certificates` | `id` | `student_id` -> `students.id`, `event_id` -> `events.id` | Chung nhan tham gia su kien. |
| `notifications` | `id` | `user_id` -> `users.id` | Thong bao gui toi nguoi dung. |

## 3. Mo ta quan he

| Quan he | Kieu | Mo ta nghiep vu |
|---|---:|---|
| `roles` -> `users` | 1-N | Mot vai tro co nhieu tai khoan; moi tai khoan thuoc mot vai tro. |
| `users` -> `students` | 1-0..1 | Mot user co the co mot ho so sinh vien neu la tai khoan Student. |
| `users` -> `clubs` | 1-N | Mot user co the lam chu nhiem/nguoi dai dien cua nhieu CLB. |
| `clubs` -> `club_members` | 1-N | Mot CLB co nhieu thanh vien. |
| `students` -> `club_members` | 1-N | Mot sinh vien co the tham gia nhieu CLB. |
| `clubs` -> `events` | 1-N | Mot CLB co the to chuc nhieu su kien. |
| `event_categories` -> `events` | 1-N | Mot loai su kien co nhieu su kien. |
| `users` -> `events` | 1-N | Mot user co the tao nhieu su kien. |
| `events` -> `event_images` | 1-N | Mot su kien co the co nhieu anh minh hoa. |
| `events` -> `event_registrations` | 1-N | Mot su kien co nhieu luot dang ky. |
| `students` -> `event_registrations` | 1-N | Mot sinh vien co the dang ky nhieu su kien. |
| `event_registrations` -> `checkin_logs` | 1-0..1 | Mot luot dang ky chi duoc check-in hop le mot lan. |
| `event_categories` -> `activity_point_rules` | 1-0..1 | Moi loai su kien co mot quy tac diem dang ap dung. |
| `students` -> `student_points` | 1-N | Mot sinh vien co nhieu dong lich su cong diem. |
| `events` -> `student_points` | 1-N | Mot su kien co the cong diem cho nhieu sinh vien. |
| `semesters` -> `student_points` | 1-N | Mot hoc ky gom nhieu dong diem ren luyen. |
| `students` -> `certificates` | 1-N | Mot sinh vien co nhieu chung nhan tham gia. |
| `events` -> `certificates` | 1-N | Mot su kien co the cap nhieu chung nhan. |
| `users` -> `notifications` | 1-N | Mot user nhan nhieu thong bao. |

## 4. Rang buoc quan trong

- `users.email` la duy nhat.
- `students.user_id` la duy nhat de mot user chi co mot ho so sinh vien.
- `students.student_code` la duy nhat.
- `event_registrations` duy nhat theo cap `(event_id, student_id)` de ngan dang ky trung.
- `event_registrations.qr_token` la duy nhat de moi luot dang ky co mot ma QR rieng.
- `checkin_logs.event_registration_id` la duy nhat de ngan check-in trung.
- `student_points` duy nhat theo cap `(student_id, event_id)` de khong cong diem hai lan cho cung mot su kien.
- `certificates.certificate_code` la duy nhat.
- `certificates` duy nhat theo cap `(student_id, event_id)` de khong cap trung chung nhan cho cung mot su kien.
- Khi dang ky su kien, backend can dem so luot `pending`/`approved`/`attended` va so sanh voi `events.capacity`.
- Khi check-in hop le, backend thuc hien trong transaction: cap nhat `event_registrations.registration_status = 'attended'`, them `checkin_logs`, them `student_points`, va tao `certificates` neu su kien co cap chung nhan.

## 5. Mermaid ERD

```mermaid
erDiagram
    roles ||--o{ users : has
    users ||--o| students : owns_profile
    users ||--o{ clubs : represents
    users ||--o{ events : creates
    users ||--o{ notifications : receives

    clubs ||--o{ club_members : has
    students ||--o{ club_members : joins

    clubs ||--o{ events : organizes
    event_categories ||--o{ events : classifies
    events ||--o{ event_images : has
    events ||--o{ event_registrations : receives
    students ||--o{ event_registrations : registers
    event_registrations ||--o| checkin_logs : checkins

    event_categories ||--o| activity_point_rules : defines
    students ||--o{ student_points : earns
    events ||--o{ student_points : awards
    semesters ||--o{ student_points : groups

    students ||--o{ certificates : receives
    events ||--o{ certificates : issues

    roles {
        INT id PK
        VARCHAR role_name UK
        TEXT description
        DATETIME created_at
    }

    users {
        INT id PK
        VARCHAR full_name
        VARCHAR email UK
        VARCHAR password_hash
        INT role_id FK
        ENUM status
        DATETIME created_at
        DATETIME updated_at
    }

    students {
        INT id PK
        INT user_id FK,UK
        VARCHAR student_code UK
        VARCHAR class_name
        VARCHAR faculty
        VARCHAR phone
        DATE date_of_birth
    }

    clubs {
        INT id PK
        VARCHAR club_name UK
        TEXT description
        DATE founded_date
        INT president_user_id FK
        ENUM status
        DATETIME created_at
        DATETIME updated_at
    }

    club_members {
        INT id PK
        INT club_id FK
        INT student_id FK
        ENUM member_role
        DATE joined_at
        ENUM status
    }

    event_categories {
        INT id PK
        VARCHAR category_name UK
        TEXT description
        ENUM status
    }

    events {
        INT id PK
        INT club_id FK
        INT category_id FK
        VARCHAR title
        TEXT description
        DATE event_date
        TIME start_time
        TIME end_time
        VARCHAR location
        INT capacity
        DATETIME registration_deadline
        VARCHAR checkin_qr_code
        ENUM status
        INT created_by FK
    }

    event_images {
        INT id PK
        INT event_id FK
        VARCHAR image_url
        BOOLEAN is_thumbnail
    }

    event_registrations {
        INT id PK
        INT event_id FK
        INT student_id FK
        DATETIME registered_at
        ENUM registration_status
        VARCHAR qr_token UK
    }

    checkin_logs {
        INT id PK
        INT event_registration_id FK,UK
        DATETIME checkin_time
        ENUM checkin_method
        DECIMAL latitude
        DECIMAL longitude
        VARCHAR device_info
        BOOLEAN is_valid
    }

    activity_point_rules {
        INT id PK
        INT category_id FK,UK
        INT point_value
        TEXT description
        BOOLEAN is_active
    }

    student_points {
        INT id PK
        INT student_id FK
        INT event_id FK
        INT points_awarded
        DATETIME awarded_at
        INT semester_id FK
        VARCHAR note
    }

    semesters {
        INT id PK
        VARCHAR semester_name
        VARCHAR academic_year
        DATE start_date
        DATE end_date
        ENUM status
    }

    certificates {
        INT id PK
        INT student_id FK
        INT event_id FK
        VARCHAR certificate_code UK
        DATETIME issued_at
        VARCHAR file_path
        ENUM status
    }

    notifications {
        INT id PK
        INT user_id FK
        VARCHAR title
        TEXT content
        BOOLEAN is_read
        DATETIME created_at
    }
```

