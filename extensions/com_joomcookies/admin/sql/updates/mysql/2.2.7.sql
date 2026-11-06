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
