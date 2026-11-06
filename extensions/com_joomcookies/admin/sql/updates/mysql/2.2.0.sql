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
