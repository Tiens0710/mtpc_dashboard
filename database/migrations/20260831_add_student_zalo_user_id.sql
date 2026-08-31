-- Chạy một lần trên database mtpc_students nếu đã import student_management_v2.sql trước đó.
SET @has_zalo_user_id := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND COLUMN_NAME = 'zalo_user_id'
);
SET @sql := IF(@has_zalo_user_id = 0,
  'ALTER TABLE students ADD COLUMN zalo_user_id VARCHAR(160) NULL AFTER phone',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @has_zalo_user_index := (
  SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'students' AND INDEX_NAME = 'idx_student_zalo_user'
);
SET @sql := IF(@has_zalo_user_index = 0,
  'ALTER TABLE students ADD KEY idx_student_zalo_user (zalo_user_id)',
  'SELECT 1'
);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
