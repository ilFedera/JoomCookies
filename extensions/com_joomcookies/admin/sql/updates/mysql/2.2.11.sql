-- 2.2.11: scan catalogue columns. Idempotent for upgrades from 2.2.10.
ALTER TABLE `#__joomcookies_services`
  ADD COLUMN `category_suggested` varchar(32) NOT NULL DEFAULT '' AFTER `category`,
  ADD COLUMN `classification_status` varchar(16) NOT NULL DEFAULT 'review' AFTER `source`,
  ADD COLUMN `last_scan_id` int unsigned NOT NULL DEFAULT 0 AFTER `notes`,
  ADD COLUMN `last_evidence` varchar(32) NOT NULL DEFAULT '' AFTER `last_scan_id`,
  ADD COLUMN `page_count` int unsigned NOT NULL DEFAULT 0 AFTER `last_evidence`,
  ADD COLUMN `pages` text AFTER `page_count`;
