CREATE DATABASE IF NOT EXISTS club_event_platform
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE club_event_platform;

SET FOREIGN_KEY_CHECKS = 0;

DROP VIEW IF EXISTS v_student_point_summary;
DROP VIEW IF EXISTS v_event_registration_counts;

DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS certificates;
DROP TABLE IF EXISTS student_points;
DROP TABLE IF EXISTS activity_point_rules;
DROP TABLE IF EXISTS checkin_logs;
DROP TABLE IF EXISTS event_registrations;
DROP TABLE IF EXISTS event_images;
DROP TABLE IF EXISTS events;
DROP TABLE IF EXISTS semesters;
DROP TABLE IF EXISTS event_categories;
DROP TABLE IF EXISTS club_members;
DROP TABLE IF EXISTS clubs;
DROP TABLE IF EXISTS students;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    role_name VARCHAR(50) NOT NULL,
    description TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_roles_role_name UNIQUE (role_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role_id INT NOT NULL,
    status ENUM('active', 'inactive', 'locked') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_users_email UNIQUE (email),
    CONSTRAINT fk_users_role
        FOREIGN KEY (role_id) REFERENCES roles(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE students (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    student_code VARCHAR(30) NOT NULL,
    class_name VARCHAR(100) NULL,
    faculty VARCHAR(150) NULL,
    phone VARCHAR(20) NULL,
    date_of_birth DATE NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_students_user_id UNIQUE (user_id),
    CONSTRAINT uq_students_student_code UNIQUE (student_code),
    CONSTRAINT fk_students_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE clubs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_name VARCHAR(150) NOT NULL,
    description TEXT NULL,
    founded_date DATE NULL,
    president_user_id INT NOT NULL,
    status ENUM('active', 'inactive', 'pending') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_clubs_club_name UNIQUE (club_name),
    CONSTRAINT fk_clubs_president_user
        FOREIGN KEY (president_user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE club_members (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    student_id INT NOT NULL,
    member_role ENUM('president', 'vice_president', 'secretary', 'member') NOT NULL DEFAULT 'member',
    joined_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('active', 'inactive', 'pending') NOT NULL DEFAULT 'active',
    CONSTRAINT uq_club_members_club_student UNIQUE (club_id, student_id),
    CONSTRAINT fk_club_members_club
        FOREIGN KEY (club_id) REFERENCES clubs(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_club_members_student
        FOREIGN KEY (student_id) REFERENCES students(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    description TEXT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    CONSTRAINT uq_event_categories_category_name UNIQUE (category_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE semesters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    semester_name VARCHAR(100) NOT NULL,
    academic_year VARCHAR(20) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('upcoming', 'active', 'closed') NOT NULL DEFAULT 'upcoming',
    CONSTRAINT uq_semesters_name_year UNIQUE (semester_name, academic_year),
    CONSTRAINT chk_semesters_date_range CHECK (start_date <= end_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    club_id INT NOT NULL,
    category_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NULL,
    event_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    location VARCHAR(255) NOT NULL,
    capacity INT NOT NULL,
    registration_deadline DATETIME NOT NULL,
    checkin_qr_code VARCHAR(255) NULL,
    status ENUM('draft', 'published', 'closed', 'cancelled', 'completed') NOT NULL DEFAULT 'draft',
    created_by INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT chk_events_capacity CHECK (capacity > 0),
    CONSTRAINT chk_events_time_range CHECK (start_time < end_time),
    CONSTRAINT fk_events_club
        FOREIGN KEY (club_id) REFERENCES clubs(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_events_category
        FOREIGN KEY (category_id) REFERENCES event_categories(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,
    CONSTRAINT fk_events_created_by
        FOREIGN KEY (created_by) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_events_club_id ON events(club_id);
CREATE INDEX idx_events_category_id ON events(category_id);
CREATE INDEX idx_events_status_date ON events(status, event_date);

CREATE TABLE event_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    image_url VARCHAR(255) NOT NULL,
    is_thumbnail TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_event_images_event
        FOREIGN KEY (event_id) REFERENCES events(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE event_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_id INT NOT NULL,
    student_id INT NOT NULL,
    registered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    registration_status ENUM('pending', 'approved', 'cancelled', 'attended') NOT NULL DEFAULT 'pending',
    qr_token VARCHAR(255) NOT NULL,
    CONSTRAINT uq_event_registrations_event_student UNIQUE (event_id, student_id),
    CONSTRAINT uq_event_registrations_qr_token UNIQUE (qr_token),
    CONSTRAINT fk_event_registrations_event
        FOREIGN KEY (event_id) REFERENCES events(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_event_registrations_student
        FOREIGN KEY (student_id) REFERENCES students(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_event_registrations_status ON event_registrations(event_id, registration_status);

CREATE TABLE checkin_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_registration_id INT NOT NULL,
    checkin_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    checkin_method ENUM('qr', 'manual') NOT NULL DEFAULT 'qr',
    latitude DECIMAL(10, 8) NULL,
    longitude DECIMAL(11, 8) NULL,
    device_info VARCHAR(255) NULL,
    is_valid TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_checkin_logs_registration UNIQUE (event_registration_id),
    CONSTRAINT fk_checkin_logs_registration
        FOREIGN KEY (event_registration_id) REFERENCES event_registrations(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE activity_point_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    point_value INT NOT NULL,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT uq_activity_point_rules_category UNIQUE (category_id),
    CONSTRAINT chk_activity_point_rules_value CHECK (point_value >= 0),
    CONSTRAINT fk_activity_point_rules_category
        FOREIGN KEY (category_id) REFERENCES event_categories(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE student_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    event_id INT NOT NULL,
    points_awarded INT NOT NULL,
    awarded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    semester_id INT NOT NULL,
    note VARCHAR(255) NULL,
    CONSTRAINT uq_student_points_student_event UNIQUE (student_id, event_id),
    CONSTRAINT chk_student_points_awarded CHECK (points_awarded >= 0),
    CONSTRAINT fk_student_points_student
        FOREIGN KEY (student_id) REFERENCES students(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_student_points_event
        FOREIGN KEY (event_id) REFERENCES events(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_student_points_semester
        FOREIGN KEY (semester_id) REFERENCES semesters(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_student_points_semester ON student_points(semester_id);

CREATE TABLE certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    event_id INT NOT NULL,
    certificate_code VARCHAR(100) NOT NULL,
    issued_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    file_path VARCHAR(255) NULL,
    status ENUM('issued', 'revoked', 'expired') NOT NULL DEFAULT 'issued',
    CONSTRAINT uq_certificates_code UNIQUE (certificate_code),
    CONSTRAINT uq_certificates_student_event UNIQUE (student_id, event_id),
    CONSTRAINT fk_certificates_student
        FOREIGN KEY (student_id) REFERENCES students(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE,
    CONSTRAINT fk_certificates_event
        FOREIGN KEY (event_id) REFERENCES events(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    content TEXT NOT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON UPDATE CASCADE
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_notifications_user_read ON notifications(user_id, is_read);

CREATE VIEW v_event_registration_counts AS
SELECT
    e.id AS event_id,
    e.title,
    e.capacity,
    SUM(CASE WHEN er.registration_status IN ('pending', 'approved', 'attended') THEN 1 ELSE 0 END) AS active_registration_count,
    SUM(CASE WHEN er.registration_status = 'attended' THEN 1 ELSE 0 END) AS attended_count
FROM events e
LEFT JOIN event_registrations er ON er.event_id = e.id
GROUP BY e.id, e.title, e.capacity;

CREATE VIEW v_student_point_summary AS
SELECT
    s.id AS student_id,
    s.student_code,
    u.full_name,
    sem.id AS semester_id,
    sem.semester_name,
    sem.academic_year,
    COALESCE(SUM(sp.points_awarded), 0) AS total_points
FROM students s
JOIN users u ON u.id = s.user_id
CROSS JOIN semesters sem
LEFT JOIN student_points sp
    ON sp.student_id = s.id
    AND sp.semester_id = sem.id
GROUP BY
    s.id,
    s.student_code,
    u.full_name,
    sem.id,
    sem.semester_name,
    sem.academic_year;

INSERT INTO roles (role_name, description) VALUES
('Admin', 'Phong Cong tac sinh vien hoac quan tri vien he thong'),
('Club Manager', 'Ban chu nhiem cau lac bo, quan ly su kien cua CLB'),
('Student', 'Sinh vien dang ky tham gia su kien');

INSERT INTO event_categories (category_name, description) VALUES
('Workshop', 'Buoi huong dan, dao tao ky nang hoac chuyen mon'),
('Seminar', 'Hoi thao, toa dam hoc thuat hoac nghe nghiep'),
('Volunteer', 'Hoat dong tinh nguyen, cong dong'),
('Competition', 'Cuoc thi hoc thuat, the thao, van nghe hoac sang tao'),
('Club Meeting', 'Sinh hoat noi bo cua cau lac bo');

INSERT INTO activity_point_rules (category_id, point_value, description, is_active)
SELECT id,
    CASE category_name
        WHEN 'Workshop' THEN 3
        WHEN 'Seminar' THEN 3
        WHEN 'Volunteer' THEN 5
        WHEN 'Competition' THEN 4
        WHEN 'Club Meeting' THEN 1
        ELSE 0
    END,
    CONCAT('Diem mac dinh cho loai su kien ', category_name),
    1
FROM event_categories;

INSERT INTO semesters (semester_name, academic_year, start_date, end_date, status) VALUES
('Hoc ky 1', '2025-2026', '2025-08-01', '2025-12-31', 'closed'),
('Hoc ky 2', '2025-2026', '2026-01-01', '2026-05-31', 'active');
