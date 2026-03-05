<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

$shopDomain = $_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN'] ?? '';
$topic      = $_SERVER['HTTP_X_SHOPIFY_TOPIC'] ?? '';
$hmacHeader = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ?? '';

$body = file_get_contents('php://input');

if (!verify_webhook($body, SHOPIFY_API_SECRET, $hmacHeader)) {
    http_response_code(401);
    exit('Invalid webhook HMAC');
}

log_webhook($shopDomain ?: 'unknown', $topic ?: 'unknown', $body);

http_response_code(200);
echo 'OK';
