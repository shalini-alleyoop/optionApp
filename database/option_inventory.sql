CREATE TABLE IF NOT EXISTS option_value_shopify_products (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    shop_domain VARCHAR(255) NOT NULL,
    option_value_id INT NOT NULL,
    shopify_product_id BIGINT UNSIGNED NOT NULL,
    shopify_variant_id BIGINT UNSIGNED NOT NULL,
    inventory_item_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED DEFAULT NULL,
    product_title VARCHAR(255) NOT NULL,
    variant_title VARCHAR(255) DEFAULT NULL,
    sku VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_option_value_shop (shop_domain, option_value_id),
    KEY idx_variant (shop_domain, shopify_variant_id),
    KEY idx_inventory_item (inventory_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS option_inventory_orders (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    shop_domain VARCHAR(255) NOT NULL,
    shopify_order_id BIGINT UNSIGNED NOT NULL,
    order_number VARCHAR(100) NOT NULL,
    shopify_line_item_id BIGINT UNSIGNED NOT NULL,
    option_id BIGINT DEFAULT NULL,
    option_value_id INT NOT NULL,
    option_title VARCHAR(255) NOT NULL,
    option_value VARCHAR(255) NOT NULL,
    shopify_product_id BIGINT UNSIGNED NOT NULL,
    shopify_variant_id BIGINT UNSIGNED NOT NULL,
    inventory_item_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED DEFAULT NULL,
    quantity INT UNSIGNED NOT NULL,
    inventory_tracked TINYINT(1) NOT NULL DEFAULT 0,
    inventory_deducted TINYINT(1) NOT NULL DEFAULT 0,
    inventory_restored TINYINT(1) NOT NULL DEFAULT 0,
    delete_flag TINYINT(1) NOT NULL DEFAULT 0,
    processing_error TEXT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    cancelled_at DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_order_line_option (shop_domain, shopify_order_id, shopify_line_item_id, option_value_id),
    KEY idx_active_order (shop_domain, shopify_order_id, delete_flag)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE webhook_logs
    ADD COLUMN IF NOT EXISTS webhook_id VARCHAR(255) DEFAULT NULL AFTER topic,
    ADD COLUMN IF NOT EXISTS processed TINYINT(1) NOT NULL DEFAULT 0 AFTER payload,
    ADD COLUMN IF NOT EXISTS processing_error TEXT DEFAULT NULL AFTER processed,
    ADD UNIQUE KEY IF NOT EXISTS uq_webhook_shop_id (shop_domain, webhook_id);
