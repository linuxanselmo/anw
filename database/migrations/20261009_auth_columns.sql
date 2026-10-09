ALTER TABLE `users`
  MODIFY COLUMN `name` VARCHAR(100) DEFAULT NULL,
  MODIFY COLUMN `password` VARCHAR(255) DEFAULT NULL;

SET @auth_schema = DATABASE();

SET @auth_sql = IF(
  EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @auth_schema AND TABLE_NAME = 'users' AND COLUMN_NAME = 'verification_token'
  ),
  'SELECT 1',
  'ALTER TABLE `users` ADD COLUMN `verification_token` VARCHAR(64) DEFAULT NULL'
);
PREPARE auth_stmt FROM @auth_sql;
EXECUTE auth_stmt;
DEALLOCATE PREPARE auth_stmt;

SET @auth_sql = IF(
  EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @auth_schema AND TABLE_NAME = 'users' AND COLUMN_NAME = 'reset_token'
  ),
  'SELECT 1',
  'ALTER TABLE `users` ADD COLUMN `reset_token` VARCHAR(64) DEFAULT NULL'
);
PREPARE auth_stmt FROM @auth_sql;
EXECUTE auth_stmt;
DEALLOCATE PREPARE auth_stmt;

SET @auth_sql = IF(
  EXISTS (
    SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = @auth_schema AND TABLE_NAME = 'users' AND COLUMN_NAME = 'reset_expires'
  ),
  'SELECT 1',
  'ALTER TABLE `users` ADD COLUMN `reset_expires` DATETIME DEFAULT NULL'
);
PREPARE auth_stmt FROM @auth_sql;
EXECUTE auth_stmt;
DEALLOCATE PREPARE auth_stmt;