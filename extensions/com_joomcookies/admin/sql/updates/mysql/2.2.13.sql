-- 2.2.13: privacy-safe, deduplicated cookie observation register.
CREATE TABLE IF NOT EXISTS `#__joomcookies_cookie_registry` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `cookie_key` char(64) NOT NULL,
  `name` varchar(191) NOT NULL,
  `domain` varchar(191) NOT NULL DEFAULT '',
  `path` varchar(255) NOT NULL DEFAULT '/',
  `service_id` varchar(64) NOT NULL DEFAULT '',
  `category` varchar(32) NOT NULL DEFAULT '',
  `duration` varchar(64) NOT NULL DEFAULT '',
  `first_party` tinyint unsigned NOT NULL DEFAULT 1,
  `source` varchar(16) NOT NULL DEFAULT 'http',
  `scenario` varchar(32) NOT NULL DEFAULT 'before_consent',
  `status` varchar(32) NOT NULL DEFAULT 'observed',
  `last_scan_id` int unsigned NOT NULL DEFAULT 0,
  `last_page_url` varchar(512) NOT NULL DEFAULT '',
  `attributes_json` text,
  `created_at` datetime NOT NULL,
  `last_observed_at` datetime NOT NULL,
  PRIMARY KEY (`id`), UNIQUE KEY `idx_cookie_key` (`cookie_key`),
  KEY `idx_cookie_status` (`status`), KEY `idx_cookie_source` (`source`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci;
