<?php
require_once __DIR__ . '/helpers.php';
start_session_once();
require_https();
if (empty($_GET['shop'])) {
    http_response_code(400);
    exit('Missing ?shop parameter');
}
$shop = normalize_shop_domain($_GET['shop']);
if (SHOPIFY_ALLOWED_SHOP && $shop !== SHOPIFY_ALLOWED_SHOP) {
    http_response_code(403);
    exit('This app is locked to a specific store.');
}

$redirectUri = APP_URL . '/callback.php';

$configuredScopes = array_filter(array_map('trim', explode(',', SHOPIFY_SCOPES)));
$requiredScopes = ['read_products', 'read_inventory', 'write_inventory', 'read_orders', 'read_locations'];
$scopes = implode(',', array_unique(array_merge($configuredScopes, $requiredScopes)));

$params = [
    'client_id'    => SHOPIFY_API_KEY,
    'redirect_uri' => $redirectUri,
    // 'state' => bin2hex(random_bytes(16)), // optional anti-CSRF
];
$query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
$installUrl = "https://{$shop}/admin/oauth/authorize?{$query}";

$_SESSION['shop'] = $shop;

redirect_to($installUrl);
