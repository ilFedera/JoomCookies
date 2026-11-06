CREATE TABLE IF NOT EXISTS `#__joomcookies_settings` (
  `setting_key` varchar(191) NOT NULL,
  `setting_value` mediumtext,
  `updated_at` datetime NOT NULL,
  PRIMARY KEY (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__joomcookies_categories` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `alias` varchar(64) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `required` tinyint unsigned NOT NULL DEFAULT 0,
  `ordering` int NOT NULL DEFAULT 0,
  `published` tinyint NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_alias` (`alias`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__joomcookies_services` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `alias` varchar(64) NOT NULL,
  `name` varchar(255) NOT NULL,
  `provider` varchar(191) NOT NULL DEFAULT '',
  `category` varchar(32) NOT NULL DEFAULT 'statistics',
  `category_suggested` varchar(32) NOT NULL DEFAULT '',
  `domains` text,
  `match_type` varchar(32) NOT NULL DEFAULT 'domain',
  `match_value` varchar(255) NOT NULL DEFAULT '',
  `cookies` text,
  `source` varchar(16) NOT NULL DEFAULT 'manual',
  `classification_status` varchar(16) NOT NULL DEFAULT 'review',
  `approved` tinyint unsigned NOT NULL DEFAULT 0,
  `block_enabled` tinyint unsigned NOT NULL DEFAULT 0,
  `notes` text,
  `last_scan_id` int unsigned NOT NULL DEFAULT 0,
  `last_evidence` varchar(32) NOT NULL DEFAULT '',
  `page_count` int unsigned NOT NULL DEFAULT 0,
  `pages` text,
  `created` datetime NOT NULL,
  `modified` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_alias` (`alias`),
  KEY `idx_approved_block` (`approved`,`block_enabled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__joomcookies_scans` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `started_at` datetime NOT NULL,
  `finished_at` datetime DEFAULT NULL,
  `status` varchar(32) NOT NULL DEFAULT 'queued',
  `mode` varchar(16) NOT NULL DEFAULT 'http',
  `start_url` varchar(512) NOT NULL DEFAULT '',
  `config` text,
  `summary` text,
  `created_by` int NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_started` (`started_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__joomcookies_findings` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `scan_id` int unsigned NOT NULL,
  `type` varchar(32) NOT NULL,
  `name` varchar(191) NOT NULL,
  `domain` varchar(191) NOT NULL DEFAULT '',
  `page_url` varchar(512) NOT NULL DEFAULT '',
  `target` varchar(512) NOT NULL DEFAULT '',
  `category_suggested` varchar(32) NOT NULL DEFAULT '',
  `service_id` varchar(64) NOT NULL DEFAULT '',
  `provider` varchar(191) NOT NULL DEFAULT '',
  `evidence_kind` varchar(32) NOT NULL DEFAULT 'reference',
  `observed` tinyint unsigned NOT NULL DEFAULT 0,
  `meta` text,
  PRIMARY KEY (`id`),
  KEY `idx_scan` (`scan_id`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__joomcookies_migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(64) NOT NULL,
  `ran_at` datetime NOT NULL,
  `checksum` varchar(64) NOT NULL DEFAULT '',
  `reversible` tinyint unsigned NOT NULL DEFAULT 1,
  `payload` mediumtext,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__joomcookies_scan_queue` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `scan_id` int unsigned NOT NULL,
  `url` varchar(1024) NOT NULL DEFAULT '',
  `normalized` varchar(512) NOT NULL DEFAULT '',
  `depth` smallint unsigned NOT NULL DEFAULT 0,
  `status` varchar(16) NOT NULL DEFAULT 'pending',
  `error` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_scan_normalized` (`scan_id`, `normalized`(191)),
  KEY `idx_scan_status` (`scan_id`, `status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__joomcookies_scan_urls` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `scan_id` int unsigned NOT NULL,
  `original_url` varchar(1024) NOT NULL DEFAULT '',
  `normalized` varchar(512) NOT NULL DEFAULT '',
  `referrer_url` varchar(1024) NOT NULL DEFAULT '',
  `discovery` varchar(32) NOT NULL DEFAULT 'html_link',
  `http_status` smallint unsigned NOT NULL DEFAULT 0,
  `final_url` varchar(1024) NOT NULL DEFAULT '',
  `canonical_url` varchar(1024) NOT NULL DEFAULT '',
  `outcome` varchar(16) NOT NULL DEFAULT 'queued',
  `reason` varchar(255) NOT NULL DEFAULT '',
  `created_at` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_scan_normalized` (`scan_id`, `normalized`(191)),
  KEY `idx_scan_outcome` (`scan_id`, `outcome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `#__joomcookies_logs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL,
  `level` varchar(16) NOT NULL DEFAULT 'info',
  `event` varchar(64) NOT NULL,
  `message` varchar(512) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_created` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
