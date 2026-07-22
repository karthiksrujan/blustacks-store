-- SQL Schema and Initial Seeding for App Store Platform
-- Storage Engine: InnoDB
-- Charset: utf8mb4

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `rate_limits`;
DROP TABLE IF EXISTS `admin_logs`;
DROP TABLE IF EXISTS `permissions`;
DROP TABLE IF EXISTS `wishlist`;
DROP TABLE IF EXISTS `downloads`;
DROP TABLE IF EXISTS `reviews`;
DROP TABLE IF EXISTS `screenshots`;
DROP TABLE IF EXISTS `apps`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Users Table
CREATE TABLE `users` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `username` VARCHAR(50) NOT NULL UNIQUE,
    `email` VARCHAR(100) NOT NULL UNIQUE,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('user', 'developer', 'admin') DEFAULT 'user',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_users_role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Categories Table
CREATE TABLE `categories` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `name` VARCHAR(50) NOT NULL,
    `slug` VARCHAR(50) NOT NULL UNIQUE,
    `icon` VARCHAR(50) NOT NULL,
    INDEX `idx_categories_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Apps Table
CREATE TABLE `apps` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `developer_id` INT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT NOT NULL,
    `short_desc` VARCHAR(255) NOT NULL,
    `category_id` INT NOT NULL,
    `icon_url` VARCHAR(255) NOT NULL,
    `banner_url` VARCHAR(255) NOT NULL,
    `version` VARCHAR(20) NOT NULL,
    `file_size` BIGINT NOT NULL, -- file size in bytes
    `file_path_or_url` VARCHAR(255) NOT NULL,
    `status` ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    `is_published` TINYINT(1) DEFAULT 0,
    `download_count` INT DEFAULT 0,
    `average_rating` DECIMAL(3,2) DEFAULT 0.00,
    `price` DECIMAL(10,2) DEFAULT 0.00, -- 0.00 for free apps
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_apps_developer` (`developer_id`),
    INDEX `idx_apps_category` (`category_id`),
    INDEX `idx_apps_status` (`status`),
    INDEX `idx_apps_download_count` (`download_count`),
    FOREIGN KEY (`developer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Screenshots Table
CREATE TABLE `screenshots` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `app_id` INT NOT NULL,
    `image_url` VARCHAR(255) NOT NULL,
    `order_index` INT NOT NULL DEFAULT 0,
    INDEX `idx_screenshots_app` (`app_id`),
    FOREIGN KEY (`app_id`) REFERENCES `apps`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Reviews Table
CREATE TABLE `reviews` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `app_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `rating` TINYINT NOT NULL,
    `review_text` TEXT DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_reviews_app` (`app_id`),
    INDEX `idx_reviews_user` (`user_id`),
    UNIQUE KEY `unique_app_user_review` (`app_id`, `user_id`),
    FOREIGN KEY (`app_id`) REFERENCES `apps`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    CONSTRAINT `chk_rating` CHECK (`rating` BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Downloads Table
CREATE TABLE `downloads` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `app_id` INT NOT NULL,
    `user_id` INT NOT NULL,
    `downloaded_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_downloads_app` (`app_id`),
    INDEX `idx_downloads_user` (`user_id`),
    FOREIGN KEY (`app_id`) REFERENCES `apps`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Wishlist Table
CREATE TABLE `wishlist` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `user_id` INT NOT NULL,
    `app_id` INT NOT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_wishlist_user` (`user_id`),
    INDEX `idx_wishlist_app` (`app_id`),
    UNIQUE KEY `unique_user_app_wishlist` (`user_id`, `app_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`app_id`) REFERENCES `apps`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Permissions Table
CREATE TABLE `permissions` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `app_id` INT NOT NULL,
    `permission_name` VARCHAR(100) NOT NULL,
    INDEX `idx_permissions_app` (`app_id`),
    FOREIGN KEY (`app_id`) REFERENCES `apps`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Admin Logs Table
CREATE TABLE `admin_logs` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `admin_id` INT NOT NULL,
    `action` VARCHAR(255) NOT NULL,
    `app_id` INT DEFAULT NULL,
    `timestamp` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_admin_logs_admin` (`admin_id`),
    FOREIGN KEY (`admin_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`app_id`) REFERENCES `apps`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Rate Limits Table
CREATE TABLE `rate_limits` (
    `id` INT AUTO_INCREMENT PRIMARY KEY,
    `ip_address` VARCHAR(45) NOT NULL,
    `endpoint` VARCHAR(50) NOT NULL,
    `user_id` INT DEFAULT NULL,
    `attempt_time` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_rate_limits_lookup` (`ip_address`, `endpoint`, `attempt_time`),
    INDEX `idx_rate_limits_user` (`user_id`, `endpoint`, `attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ==========================================
-- SEED DATA
-- ==========================================

-- Seed Categories
INSERT INTO `categories` (`name`, `slug`, `icon`) VALUES
('Games', 'games', 'gamepad'),
('Productivity', 'productivity', 'briefcase'),
('Tools', 'tools', 'wrench'),
('Entertainment', 'entertainment', 'tv'),
('Education', 'education', 'graduation-cap'),
('Other', 'other', 'ellipsis-h');

-- Seed Users
-- Admin (password: AdminPassword123!)
-- Developer (password: DevPassword123!)
-- Standard User (password: UserPassword123!)
INSERT INTO `users` (`id`, `username`, `email`, `password_hash`, `role`) VALUES
(1, 'admin', 'admin@store.com', '$2y$12$VjNsypFuiD7RLMkqWtoShOQFL5QCGahN.yQ5RinyeN0kQ794Ljk/G', 'admin'),
(2, 'developer_bob', 'dev@store.com', '$2y$12$KcaKds1SxCk1nn5XsCFj4ed5M5VvuNX1olbNaC5EnoCFLfGk0UWxG', 'developer'),
(3, 'alice_user', 'user@store.com', '$2y$12$xQNPk4vfuhXHwLtxS6SuiOJPxwwJoiKByM1fuJR7Iaa21wRQ9sdu.', 'user');

-- Seed Sample Apps (under bob developer id = 2)
INSERT INTO `apps` (`id`, `developer_id`, `name`, `description`, `short_desc`, `category_id`, `icon_url`, `banner_url`, `version`, `file_size`, `file_path_or_url`, `status`, `is_published`, `download_count`, `average_rating`, `price`) VALUES
(1, 2, 'Pixel Adventure', 'An epic 2D platformer game where you explore ancient temples, collect magical pixels, and defeat pixelated bosses. Featuring stunning retro graphics, responsive controls, and hours of gameplay.', 'Classic 8-bit retro platformer game.', 1, 'pixel_icon.png', 'pixel_banner.jpg', '1.0.2', 15423892, 'https://example.com/downloads/pixel_adventure.apk', 'approved', 1, 1420, 4.60, 0.00),
(2, 2, 'FocusFlow Taskmanager', 'Organize your life, schedule tasks, and track your daily habits with FocusFlow. The smart productivity tool built to keep you in the zone with Pomodoro integration, Kanban boards, and cloud sync.', 'Boost your productivity with tasks & schedules.', 2, 'focusflow_icon.png', 'focusflow_banner.jpg', '2.1.0', 25610243, 'https://example.com/downloads/focusflow.exe', 'approved', 1, 840, 4.25, 0.00),
(3, 2, 'NetSpeed Utility', 'Verify your connection speed, analyze Wi-Fi networks, and optimize your DNS routing. NetSpeed is a lightweight tool for system administrators and power users needing network diagnostics.', 'Wi-Fi analyzer and speed test tool.', 3, 'netspeed_icon.png', 'netspeed_banner.jpg', '1.5.0', 5892102, 'https://example.com/downloads/netspeed.zip', 'approved', 1, 1230, 4.80, 0.00),
(4, 2, 'CinemaCast Streamer', 'Stream movies, watch live television, and cast media to your smart devices. CinemaCast supports high-definition video, multi-language subtitles, and customized playlist curation.', 'Stream and cast high-quality videos.', 4, 'cinemacast_icon.png', 'cinemacast_banner.jpg', '3.0.1', 45120300, 'https://example.com/downloads/cinemacast.apk', 'approved', 1, 980, 3.90, 0.00),
(5, 2, 'MathQuest tutor', 'Learn algebra, geometry, and calculus through gamified lessons. MathQuest makes education engaging with step-by-step interactive solutions, quizzes, and rewards for students.', 'Learn mathematics through fun games.', 5, 'mathquest_icon.png', 'mathquest_banner.jpg', '1.0.0', 32410293, 'https://example.com/downloads/mathquest.apk', 'approved', 1, 350, 4.50, 0.00),
(6, 2, 'HexaHex Editor', 'A low-level binary hex editor for reverse engineering, debugging, and file patching. Supports file maps, structure overlays, search & replace, and dark-themed interface.', 'Powerful low-level binary hex editor.', 3, 'hexahex_icon.png', 'hexahex_banner.jpg', '0.9.8', 1245030, 'https://example.com/downloads/hexahex.zip', 'pending', 0, 0, 0.00, 0.00),
(7, 2, 'CodePad Editor', 'A lightweight text editor designed for quick code editing and scripting. Includes syntax highlighting for over 30 languages, file tree navigation, and extensions manager.', 'Fast text editor for code & scripts.', 2, 'codepad_icon.png', 'codepad_banner.jpg', '1.2.4', 12401920, 'https://example.com/downloads/codepad.zip', 'approved', 1, 2410, 4.70, 0.00),
(8, 2, 'Space Combat Arena', 'Pilot a spaceship through intense cosmic dogfights. Upgrade weapons, join alliances, and dominate the galaxy in this multiplayer arcade combat simulator.', 'Thrilling multiplayer space dogfights.', 1, 'space_icon.png', 'space_banner.jpg', '2.0.4', 85402012, 'https://example.com/downloads/space_combat.apk', 'approved', 1, 1980, 4.45, 0.00),
(9, 2, 'AudioMix Pro', 'Edit audio tracks, record high-quality sound, and add professional sound effects. Ideal for podcasters, musicians, and creators wanting portable audio editing.', 'Edit and record multi-track audio.', 4, 'audiomix_icon.png', 'audiomix_banner.jpg', '1.1.0', 52401203, 'https://example.com/downloads/audiomix.exe', 'approved', 1, 670, 4.10, 0.00);

-- Seed Sample Screenshots
INSERT INTO `screenshots` (`app_id`, `image_url`, `order_index`) VALUES
(1, 'pixel_ss1.jpg', 0),
(1, 'pixel_ss2.jpg', 1),
(1, 'pixel_ss3.jpg', 2),
(2, 'focusflow_ss1.jpg', 0),
(2, 'focusflow_ss2.jpg', 1),
(3, 'netspeed_ss1.jpg', 0),
(4, 'cinemacast_ss1.jpg', 0),
(4, 'cinemacast_ss2.jpg', 1),
(5, 'mathquest_ss1.jpg', 0),
(7, 'codepad_ss1.jpg', 0),
(7, 'codepad_ss2.jpg', 1),
(8, 'space_ss1.jpg', 0),
(8, 'space_ss2.jpg', 1),
(9, 'audiomix_ss1.jpg', 0);

-- Seed Sample Reviews
INSERT INTO `reviews` (`app_id`, `user_id`, `rating`, `review_text`) VALUES
(1, 3, 5, 'Absolutely love this game! The retro vibes are real, controls are tight, and it runs beautifully on my mobile device. Highly recommended!'),
(2, 3, 4, 'Very solid task manager. The Pomodoro integration really helps me focus during my programming sessions. Wish it had a bit more calendar integration though.'),
(3, 3, 5, 'Does exactly what it says. Fast speed tests and helpful network diagnostics. Simple and clean.'),
(4, 3, 4, 'Great streaming client. Easy to cast to my TV, but sometimes has a bit of buffering. Hopefully the next update fixes it.'),
(8, 3, 5, 'Super fun multiplayer space fights! Dynamic graphics and upgrading the ship is very addicting.'),
(7, 3, 4, 'Very fast code editor. Use it for small edits daily.');

-- Seed Sample Permissions
INSERT INTO `permissions` (`app_id`, `permission_name`) VALUES
(1, 'Storage Access'),
(1, 'Vibration Control'),
(2, 'Notifications'),
(2, 'Background Execution'),
(3, 'Network State / Wi-Fi Access'),
(4, 'Internet Access'),
(4, 'Bluetooth Casting'),
(5, 'None'),
(7, 'Local File System Read/Write'),
(8, 'Internet Access'),
(8, 'Microphone (Voice Chat)'),
(9, 'Microphone Input'),
(9, 'Storage Access');

-- Seed Sample Downloads
INSERT INTO `downloads` (`app_id`, `user_id`, `downloaded_at`) VALUES
(1, 3, '2026-07-10 10:15:30'),
(2, 3, '2026-07-12 14:22:15'),
(3, 3, '2026-07-13 09:05:00'),
(4, 3, '2026-07-15 18:30:12'),
(8, 3, '2026-07-16 20:11:45'),
(7, 3, '2026-07-17 11:00:00');

-- Seed Wishlist
INSERT INTO `wishlist` (`user_id`, `app_id`) VALUES
(3, 5),
(3, 9);
