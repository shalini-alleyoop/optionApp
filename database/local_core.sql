-- Core tables needed for a fresh local install (not included in the other SQL files).

CREATE TABLE IF NOT EXISTS shops (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    shop_domain VARCHAR(255) NOT NULL,
    access_token VARCHAR(512) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_shop_domain (shop_domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS engraving_instructions (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    shop_domain VARCHAR(255) NOT NULL,
    title VARCHAR(255) NOT NULL,
    content_html LONGTEXT NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_engraving_shop (shop_domain)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bg_options (
    option_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    display_name VARCHAR(255) NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'S',
    status VARCHAR(10) NOT NULL DEFAULT '1',
    PRIMARY KEY (option_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bg_option_values (
    option_value_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    option_id INT UNSIGNED NOT NULL,
    label VARCHAR(255) NOT NULL,
    value VARCHAR(255) DEFAULT NULL,
    image VARCHAR(255) DEFAULT NULL,
    price DECIMAL(12,2) DEFAULT NULL,
    price_type VARCHAR(50) DEFAULT 'relative',
    price_adjust VARCHAR(20) DEFAULT 'add',
    sort_order INT NOT NULL DEFAULT 0,
    is_default VARCHAR(10) NOT NULL DEFAULT '0',
    status VARCHAR(10) NOT NULL DEFAULT '1',
    PRIMARY KEY (option_value_id),
    KEY idx_option_id (option_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bg_products (
    product_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    shopify_product_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    price DECIMAL(12,2) DEFAULT NULL,
    slug VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (product_id),
    UNIQUE KEY uq_shopify_product (shopify_product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bg_product_options (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    product_id INT UNSIGNED NOT NULL,
    product_option_id INT UNSIGNED NOT NULL,
    option_id INT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    required VARCHAR(10) DEFAULT '0',
    options_values TEXT DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_product_id (product_id),
    KEY idx_option_id (option_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS bg_product_rules_extract (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    rule_id VARCHAR(100) DEFAULT NULL,
    product_id INT UNSIGNED NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_enabled VARCHAR(10) NOT NULL DEFAULT 'true',
    is_stop VARCHAR(10) NOT NULL DEFAULT 'false',
    adjuster VARCHAR(50) DEFAULT NULL,
    adjuster_value VARCHAR(50) DEFAULT NULL,
    is_purchasing_disabled VARCHAR(10) NOT NULL DEFAULT 'false',
    purchasing_disabled_message TEXT DEFAULT NULL,
    is_purchasing_hidden VARCHAR(10) NOT NULL DEFAULT 'false',
    image_file VARCHAR(255) DEFAULT NULL,
    conditions_json LONGTEXT DEFAULT NULL,
    raw LONGTEXT DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_product_id (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS shopify_products (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    bg_id INT UNSIGNED DEFAULT NULL,
    shopify_product_id BIGINT UNSIGNED DEFAULT NULL,
    json_data LONGTEXT DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_bg_id (bg_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
