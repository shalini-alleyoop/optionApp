<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/option_inventory.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit;
}

$shopDomain = $_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN'] ?? '';
$topic      = $_SERVER['HTTP_X_SHOPIFY_TOPIC'] ?? '';
$webhookId  = $_SERVER['HTTP_X_SHOPIFY_WEBHOOK_ID'] ?? '';
$hmacHeader = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ?? '';

$body = file_get_contents('php://input');

if (!verify_webhook($body, SHOPIFY_API_SECRET, $hmacHeader)) {
    http_response_code(401);
    exit('Invalid webhook HMAC');
}

try {
    $stmt = db()->prepare("INSERT INTO webhook_logs (shop_domain, topic, webhook_id, payload) VALUES (?, ?, ?, ?)");
    $stmt->execute([$shopDomain ?: 'unknown', $topic ?: 'unknown', $webhookId ?: null, $body]);
    $logId = (int)db()->lastInsertId();
} catch (PDOException $e) {
    if ($webhookId && $e->getCode() === '23000') {
        $existing = db()->prepare('SELECT id, processed FROM webhook_logs WHERE shop_domain=? AND webhook_id=? LIMIT 1');
        $existing->execute([$shopDomain ?: 'unknown', $webhookId]);
        $existingLog = $existing->fetch();
        if ($existingLog && $existingLog['processed']) { http_response_code(200); exit('Already processed'); }
        if (!$existingLog) throw $e;
        $logId = (int)$existingLog['id'];
    } else {
        throw $e;
    }
}

try {
    $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    if ($topic === 'orders/create') option_inventory_process_order($shopDomain, $payload);
    elseif ($topic === 'orders/cancelled') option_inventory_cancel_order($shopDomain, $payload);
    db()->prepare('UPDATE webhook_logs SET processed=1, processing_error=NULL WHERE id=?')->execute([$logId]);
} catch (Throwable $e) {
    db()->prepare('UPDATE webhook_logs SET processing_error=? WHERE id=?')->execute([$e->getMessage(), $logId]);
    http_response_code(500);
    exit('Webhook processing failed');
}

http_response_code(200);
echo 'OK';
