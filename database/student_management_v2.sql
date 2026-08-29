-- MTPC Student Management v2 - run once in phpMyAdmin on database mtpc_students.
SET NAMES utf8mb4;

ALTER TABLE students
  ADD COLUMN gender VARCHAR(20) NULL AFTER date_of_birth,
  ADD COLUMN citizen_id VARCHAR(20) NULL AFTER gender,
  ADD COLUMN address VARCHAR(500) NULL AFTER email,
  ADD COLUMN guardian_name VARCHAR(150) NULL AFTER address,
  ADD COLUMN guardian_phone VARCHAR(30) NULL AFTER guardian_name,
  ADD COLUMN admission_year SMALLINT UNSIGNED NULL AFTER guardian_phone,
  ADD COLUMN cohort VARCHAR(50) NULL AFTER admission_year,
  ADD COLUMN photo_path VARCHAR(500) NULL AFTER note,
  ADD UNIQUE KEY unique_citizen_id (citizen_id),
  ADD KEY idx_student_phone (phone),
  ADD KEY idx_student_email (email),
  ADD KEY idx_student_class (class_name),
  ADD KEY idx_student_program (program_name),
  ADD KEY idx_student_cohort (cohort);

CREATE TABLE IF NOT EXISTS admin_users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  username VARCHAR(100) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  role ENUM('admin','training','teacher') NOT NULL DEFAULT 'teacher',
  status ENUM('active','disabled') NOT NULL DEFAULT 'active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY unique_admin_username (username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_history (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, student_id INT UNSIGNED NOT NULL,
  action VARCHAR(80) NOT NULL, field_name VARCHAR(80) NULL, old_value TEXT NULL, new_value TEXT NULL,
  reason VARCHAR(500) NULL, actor_username VARCHAR(100) NOT NULL, actor_role VARCHAR(30) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_history_student (student_id,created_at),
  CONSTRAINT fk_history_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_documents (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, student_id INT UNSIGNED NOT NULL,
  document_type VARCHAR(80) NOT NULL, file_name VARCHAR(255) NOT NULL, file_path VARCHAR(500) NOT NULL,
  status ENUM('pending','verified','rejected') NOT NULL DEFAULT 'pending', note VARCHAR(500) NULL,
  uploaded_by VARCHAR(100) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_document_student (student_id),
  CONSTRAINT fk_document_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS semesters (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, code VARCHAR(30) NOT NULL, name VARCHAR(100) NOT NULL,
  start_date DATE NOT NULL, end_date DATE NOT NULL, status ENUM('planned','active','closed') NOT NULL DEFAULT 'planned',
  PRIMARY KEY (id), UNIQUE KEY unique_semester_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS subjects (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, code VARCHAR(30) NOT NULL, name VARCHAR(150) NOT NULL,
  credits DECIMAL(4,1) NOT NULL DEFAULT 0, program_name VARCHAR(150) NULL,
  PRIMARY KEY (id), UNIQUE KEY unique_subject_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS academic_results (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, student_id INT UNSIGNED NOT NULL, semester_id INT UNSIGNED NOT NULL,
  subject_id INT UNSIGNED NOT NULL, process_score DECIMAL(5,2) NULL, exam_score DECIMAL(5,2) NULL,
  final_score DECIMAL(5,2) NULL, letter_grade VARCHAR(5) NULL, result ENUM('studying','passed','failed') NOT NULL DEFAULT 'studying',
  updated_by VARCHAR(100) NOT NULL, updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY unique_result (student_id,semester_id,subject_id),
  CONSTRAINT fk_result_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_result_semester FOREIGN KEY (semester_id) REFERENCES semesters(id),
  CONSTRAINT fk_result_subject FOREIGN KEY (subject_id) REFERENCES subjects(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS attendance_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, class_name VARCHAR(100) NOT NULL, subject_name VARCHAR(150) NOT NULL,
  session_date DATE NOT NULL, period_label VARCHAR(50) NULL, teacher_username VARCHAR(100) NOT NULL,
  note VARCHAR(500) NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_attendance_session (class_name,session_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS attendance_records (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, session_id BIGINT UNSIGNED NOT NULL, student_id INT UNSIGNED NOT NULL,
  status ENUM('present','late','excused','absent') NOT NULL DEFAULT 'present', note VARCHAR(500) NULL,
  marked_by VARCHAR(100) NOT NULL, marked_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY unique_attendance (session_id,student_id), KEY idx_attendance_student (student_id),
  CONSTRAINT fk_attendance_session FOREIGN KEY (session_id) REFERENCES attendance_sessions(id) ON DELETE CASCADE,
  CONSTRAINT fk_attendance_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fee_items (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT, code VARCHAR(40) NOT NULL, name VARCHAR(150) NOT NULL,
  semester_id INT UNSIGNED NULL, amount DECIMAL(15,0) NOT NULL DEFAULT 0, due_date DATE NULL, active TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id), UNIQUE KEY unique_fee_code (code), CONSTRAINT fk_fee_semester FOREIGN KEY (semester_id) REFERENCES semesters(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_fees (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, student_id INT UNSIGNED NOT NULL, fee_item_id INT UNSIGNED NOT NULL,
  original_amount DECIMAL(15,0) NOT NULL, discount_amount DECIMAL(15,0) NOT NULL DEFAULT 0,
  discount_reason VARCHAR(500) NULL, amount_due DECIMAL(15,0) NOT NULL, status ENUM('unpaid','partial','paid','waived') NOT NULL DEFAULT 'unpaid',
  created_by VARCHAR(100) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY unique_student_fee (student_id,fee_item_id), KEY idx_fee_status (status),
  CONSTRAINT fk_student_fee_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  CONSTRAINT fk_student_fee_item FOREIGN KEY (fee_item_id) REFERENCES fee_items(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS fee_payments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, student_fee_id BIGINT UNSIGNED NOT NULL,
  amount DECIMAL(15,0) NOT NULL, payment_method VARCHAR(40) NOT NULL, reference_code VARCHAR(100) NULL,
  receipt_no VARCHAR(50) NOT NULL, paid_at DATETIME NOT NULL, note VARCHAR(500) NULL, received_by VARCHAR(100) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), UNIQUE KEY unique_receipt_no (receipt_no), KEY idx_payment_fee (student_fee_id),
  CONSTRAINT fk_payment_student_fee FOREIGN KEY (student_fee_id) REFERENCES student_fees(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS student_actions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, student_id INT UNSIGNED NOT NULL,
  action_type ENUM('reward','discipline','reserve','withdraw','return') NOT NULL,
  decision_no VARCHAR(80) NULL, effective_date DATE NOT NULL, end_date DATE NULL, reason VARCHAR(1000) NOT NULL,
  status ENUM('draft','active','completed','cancelled') NOT NULL DEFAULT 'draft', created_by VARCHAR(100) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_action_student (student_id),
  CONSTRAINT fk_action_student FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS system_audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT, actor_username VARCHAR(100) NOT NULL, actor_role VARCHAR(30) NOT NULL,
  action VARCHAR(100) NOT NULL, entity_type VARCHAR(60) NOT NULL, entity_id VARCHAR(80) NULL,
  before_data MEDIUMTEXT NULL, after_data MEDIUMTEXT NULL, ip_address VARCHAR(45) NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id), KEY idx_audit_entity (entity_type,entity_id), KEY idx_audit_actor (actor_username,created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Dữ liệu mẫu để kiểm thử trợ lý AI. Không xóa các sinh viên đang có.
INSERT INTO students
  (student_code, full_name, date_of_birth, gender, program_name, class_name, admission_year, cohort, status, note)
VALUES
  ('MTPC001', 'Nguyễn Nhật Tiến', '2007-01-01', 'Nam', 'Công nghệ thông tin', 'CNTT-K26', 2026, 'K26', 'Đang học', 'Dữ liệu mẫu để kiểm thử trợ lý AI.'),
  ('MTPC002', 'Huỳnh Xuân Hiệp', '2007-01-01', 'Nam', 'Công nghệ thông tin', 'CNTT-K26', 2026, 'K26', 'Đang học', 'Dữ liệu mẫu để kiểm thử trợ lý AI.')
ON DUPLICATE KEY UPDATE
  full_name = VALUES(full_name),
  date_of_birth = VALUES(date_of_birth),
  gender = VALUES(gender),
  program_name = VALUES(program_name),
  class_name = VALUES(class_name),
  admission_year = VALUES(admission_year),
  cohort = VALUES(cohort),
  status = VALUES(status),
  note = VALUES(note);
