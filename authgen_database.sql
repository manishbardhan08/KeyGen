CREATE DATABASE IF NOT EXISTS `authgen` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `authgen`;

DROP TABLE IF EXISTS `api_sessions`;
CREATE TABLE `api_sessions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `token` varchar(128) NOT NULL,
  `user_id` int(10) unsigned NOT NULL,
  `key_id` int(10) unsigned NOT NULL,
  `governing_admin_id` int(10) unsigned DEFAULT NULL,
  `hwid` varchar(128) NOT NULL,
  `ip` varchar(64) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  KEY `idx_sessions_key_id` (`key_id`),
  KEY `idx_sessions_gov_admin` (`governing_admin_id`),
  KEY `idx_sessions_user_id` (`user_id`),
  KEY `idx_sessions_expires_at` (`expires_at`),
  CONSTRAINT `fk_sessions_key_id` FOREIGN KEY (`key_id`) REFERENCES `keys` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_sessions_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=72 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `key_devices`;
CREATE TABLE `key_devices` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `key_id` int(10) unsigned NOT NULL,
  `hwid` varchar(128) NOT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_id_hwid` (`key_id`,`hwid`),
  KEY `key_id` (`key_id`),
  CONSTRAINT `key_devices_key_id_foreign` FOREIGN KEY (`key_id`) REFERENCES `keys` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `key_levels`;
CREATE TABLE `key_levels` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(10) unsigned NOT NULL,
  `level_number` smallint(5) unsigned NOT NULL,
  `name` varchar(60) NOT NULL,
  `is_admin_only` tinyint(1) DEFAULT 0,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_id_level_number` (`admin_id`,`level_number`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `fk_levels_admin_id` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `key_plans`;
CREATE TABLE `key_plans` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `admin_id` int(10) unsigned NOT NULL,
  `duration_days` int(10) unsigned NOT NULL,
  `level_number` smallint(5) unsigned DEFAULT 0,
  `price` int(10) unsigned NOT NULL DEFAULT 0,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `duration_hours` int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `admin_hours_level` (`admin_id`,`duration_hours`,`level_number`),
  KEY `admin_id` (`admin_id`),
  CONSTRAINT `fk_plans_admin_id` FOREIGN KEY (`admin_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `keys`;
CREATE TABLE `keys` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `key` varchar(64) NOT NULL,
  `created_by` int(10) unsigned NOT NULL,
  `created_by_username` varchar(50) DEFAULT NULL,
  `created_by_role` varchar(20) DEFAULT NULL,
  `duration_days` int(11) NOT NULL DEFAULT 30,
  `level` smallint(5) unsigned DEFAULT 0,
  `product` varchar(100) DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'unused',
  `used_by` int(10) unsigned DEFAULT NULL,
  `used_at` datetime DEFAULT NULL,
  `hwid` varchar(128) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `duration_hours` int(10) unsigned NOT NULL DEFAULT 720,
  `device_limit` int(10) unsigned NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `key` (`key`),
  KEY `idx_keys_created_by` (`created_by`),
  KEY `idx_keys_status` (`status`),
  KEY `idx_keys_hwid` (`hwid`),
  KEY `idx_keys_level` (`level`),
  KEY `idx_keys_expires_at` (`expires_at`),
  CONSTRAINT `fk_keys_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=264 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `version` varchar(255) NOT NULL,
  `class` varchar(255) NOT NULL,
  `group` varchar(255) NOT NULL,
  `namespace` varchar(255) NOT NULL,
  `time` int(11) NOT NULL,
  `batch` int(11) unsigned NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=30 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(100) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` varchar(20) NOT NULL DEFAULT 'seller',
  `is_banned` tinyint(1) DEFAULT 0,
  `api_key_hash` varchar(128) DEFAULT NULL,
  `api_key_display` text DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL,
  `created_by_role` varchar(20) DEFAULT NULL,
  `balance` int(11) NOT NULL DEFAULT 0,
  `phone` varchar(20) DEFAULT NULL,
  `product` varchar(100) DEFAULT NULL,
  `level` smallint(5) unsigned DEFAULT 0,
  `software_enabled` tinyint(1) DEFAULT 1,
  `offline_message` varchar(255) DEFAULT NULL,
  `expires_at` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `updated_at` datetime DEFAULT NULL,
  `sell_levels` varchar(255) DEFAULT NULL,
  `can_extend` tinyint(1) DEFAULT 0,
  `can_ban` tinyint(1) DEFAULT 0,
  `can_reset_hwid` tinyint(1) DEFAULT 0,
  `can_delete` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `idx_users_created_by` (`created_by`)
) ENGINE=InnoDB AUTO_INCREMENT=23 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


