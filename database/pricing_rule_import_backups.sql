CREATE TABLE IF NOT EXISTS `pricing_rule_import_backups` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `product_id` int NOT NULL,
  `shopify_product_id` varchar(100) NOT NULL,
  `rules_json` longtext NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `restored_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `pricing_rule_backups_product_id_index` (`product_id`),
  KEY `pricing_rule_backups_created_at_index` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `pricing_rule_import_backups`
  ADD COLUMN IF NOT EXISTS `restored_at` datetime DEFAULT NULL AFTER `created_at`;
