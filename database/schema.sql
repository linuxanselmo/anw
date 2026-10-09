CREATE TABLE IF NOT EXISTS `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `role` ENUM('admin', 'advertiser', 'client') NOT NULL DEFAULT 'client',
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `cpf` VARCHAR(20) DEFAULT NULL,
  `verification_media` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('active', 'pending_verification', 'pending_approval', 'rejected') DEFAULT 'active',
  `password` VARCHAR(255) NOT NULL,
  `remember_token` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);


CREATE TABLE IF NOT EXISTS `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL UNIQUE,
  `slug` VARCHAR(50) NOT NULL UNIQUE
);

CREATE TABLE IF NOT EXISTS `ads` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `age` INT,
  `price` DECIMAL(10, 2),
  `phone` VARCHAR(20) NOT NULL,
  `status` ENUM('active', 'inactive', 'pending', 'deleted') DEFAULT 'pending',
  `available_now` BOOLEAN DEFAULT FALSE,
  `is_verified` BOOLEAN DEFAULT FALSE,
  `is_premium` BOOLEAN DEFAULT FALSE,
  `rating` DECIMAL(3,1) DEFAULT 0.0,
  `reviews_count` INT DEFAULT 0,
  `attends_to` VARCHAR(255) DEFAULT '',
  `payment_methods` VARCHAR(255) DEFAULT '',
  `service_locations` VARCHAR(255) DEFAULT '',
  `characteristics` JSON DEFAULT NULL,
  `views` INT DEFAULT 0,
  `clicks` INT DEFAULT 0,
  `highlight_level` ENUM('ultra_top', 'super_top', 'super_highlight', 'paid_highlight', 'organic') DEFAULT 'organic',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `ad_locations` (
  `ad_id` INT NOT NULL,
  `state` VARCHAR(2) NOT NULL,
  `city` VARCHAR(100) NOT NULL,
  PRIMARY KEY (`ad_id`, `state`, `city`),
  FOREIGN KEY (`ad_id`) REFERENCES `ads`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `ad_media` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ad_id` INT NOT NULL,
  `media_type` ENUM('image', 'video') NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `is_primary` BOOLEAN DEFAULT FALSE,
  FOREIGN KEY (`ad_id`) REFERENCES `ads`(`id`) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS `ad_categories` (
  `ad_id` INT NOT NULL,
  `category_id` INT NOT NULL,
  PRIMARY KEY (`ad_id`, `category_id`),
  FOREIGN KEY (`ad_id`) REFERENCES `ads`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE
);

-- Insert dummy categories for MVP
INSERT IGNORE INTO `categories` (`name`, `slug`) VALUES ('Mulheres', 'mulheres'), ('Homens', 'homens'), ('Transex', 'transex'), ('Gays', 'gays'), ('Casais', 'casais');

CREATE TABLE IF NOT EXISTS `reviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ad_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `rating` INT NOT NULL CHECK (`rating` >= 1 AND `rating` <= 5),
  `comment` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ad_id`) REFERENCES `ads`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);
