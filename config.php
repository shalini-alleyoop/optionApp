<?php
define('APP_URL', 'https://apps.royalhawaiianheritage.com');
define('SESSION_NAME', 'shopify_app_sess');
define('SHOPIFY_ALLOWED_SHOP', 'qheqg4-bu.myshopify.com');
define('SHOPIFY_API_KEY',    'XXX');
define('SHOPIFY_API_SECRET', 'XXX');
define('SHOPIFY_API_VERSION', '2025-04');
define('SHOPIFY_SCOPES', implode(',', [
    'read_products','write_products',
    'read_orders','write_orders',
    'read_customers','write_customers',
    'read_themes','write_themes',
    'read_discounts','write_discounts',
    'read_checkouts','unauthenticated_read_checkouts','unauthenticated_write_checkouts',
    'read_shipping','write_shipping',
    'read_fulfillments','write_fulfillments',
    'read_assigned_fulfillment_orders','write_assigned_fulfillment_orders',
    'read_merchant_managed_fulfillment_orders','write_merchant_managed_fulfillment_orders',
    'read_third_party_fulfillment_orders','write_third_party_fulfillment_orders',
    'read_locations','unauthenticated_read_product_listings',
    'unauthenticated_read_product_pickup_locations',
    'read_order_edits','write_order_edits',
    'read_price_rules','write_price_rules',
    'write_script_tags'
]));
define('DB_HOST', 'localhost');
/* define('DB_NAME', 'u955762364_customproducts');
define('DB_USER', 'u955762364_customproducts');
define('DB_PASS', 'No4Z8&dY6wJ='); */
define('DB_NAME', 'u664957797_optionApp');
define('DB_USER', 'u664957797_rhhj');
define('DB_PASS', 'pbHF2LS9=C');
define('DB_CHARSET', 'utf8mb4');
define('REGISTER_WEBHOOKS_ON_INSTALL', true);
const WEBHOOK_TOPICS = [
    'products/update',
    'customers/update',
    'orders/create',
    'discounts/create',
    'discounts/update',
    'discounts/delete'
];

define('INSTALL_SCRIPT_TAG', false);
define('SCRIPT_TAG_SRC', APP_URL . '/frontoptions.js');
