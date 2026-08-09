<?php

$allowed_origins = [
    'https://qheqg4-bu.myshopify.com',
    'https://royalhawaiianheritagejewelry.com',
    'https://www.royalhawaiianheritagejewelry.com',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: https://qheqg4-bu.myshopify.com');
}
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, X-Requested-With");
require_once __DIR__ . '/helpers.php';

// Shopify order-cancellation webhook endpoint:
// process.php?action=cancel_order&domain={shop}.myshopify.com
// This must run before the normal storefront/session initialization below.
if (($_GET['action'] ?? '') === 'cancel_order') {
    require_once __DIR__ . '/db.php';
    require_once __DIR__ . '/option_inventory.php';

    header('Content-Type: text/plain; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        exit('POST required');
    }

    $body = file_get_contents('php://input');
    $hmac = $_SERVER['HTTP_X_SHOPIFY_HMAC_SHA256'] ?? '';
    $headerShop = normalize_shop_domain((string)($_SERVER['HTTP_X_SHOPIFY_SHOP_DOMAIN'] ?? ''));
    $urlShop = normalize_shop_domain((string)($_GET['domain'] ?? ''));
    $topic = (string)($_SERVER['HTTP_X_SHOPIFY_TOPIC'] ?? '');
    $webhookId = trim((string)($_SERVER['HTTP_X_SHOPIFY_WEBHOOK_ID'] ?? ''));

    if (!verify_webhook($body, SHOPIFY_API_SECRET, $hmac)) {
        http_response_code(401);
        exit('Invalid webhook HMAC');
    }
    if ($headerShop !== $urlShop || $urlShop !== SHOPIFY_ALLOWED_SHOP) {
        http_response_code(403);
        exit('Invalid shop');
    }
    if ($topic !== '' && $topic !== 'orders/cancelled') {
        http_response_code(400);
        exit('Invalid webhook topic');
    }

    try {
        $payload = json_decode($body, true, 512, JSON_THROW_ON_ERROR);
        if (empty($payload['id'])) throw new RuntimeException('Missing Shopify order ID.');

        try {
            $stmt = db()->prepare('INSERT INTO webhook_logs (shop_domain, topic, webhook_id, payload) VALUES (?, ?, ?, ?)');
            $stmt->execute([$urlShop, 'orders/cancelled', $webhookId !== '' ? $webhookId : null, $body]);
            $logId = (int)db()->lastInsertId();
        } catch (PDOException $e) {
            if ($webhookId === '' || $e->getCode() !== '23000') throw $e;
            $existing = db()->prepare('SELECT id, processed FROM webhook_logs WHERE shop_domain=? AND webhook_id=? LIMIT 1');
            $existing->execute([$urlShop, $webhookId]);
            $log = $existing->fetch();
            if ($log && $log['processed']) {
                http_response_code(200);
                exit('Already processed');
            }
            if (!$log) throw $e;
            $logId = (int)$log['id'];
        }

        option_inventory_cancel_order($urlShop, $payload);
        db()->prepare('UPDATE webhook_logs SET processed=1, processing_error=NULL WHERE id=?')->execute([$logId]);
        http_response_code(200);
        exit('Cancellation processed');
    } catch (Throwable $e) {
        if (!empty($logId)) {
            db()->prepare('UPDATE webhook_logs SET processing_error=? WHERE id=?')->execute([$e->getMessage(), $logId]);
        }
        error_log('Cancellation webhook failed: ' . $e->getMessage());
        http_response_code(500);
        exit('Cancellation processing failed');
    }
}

require_once __DIR__ . '/option_inventory.php';
require_once __DIR__ . '/connect.php';
start_session_once();
require_https();
$shop     = $_GET['domain'];
if (!$shop) {
    http_response_code(401);
    exit('No shop context.');
}

$getdata = $admin->get_shop_detail($shop);
$access_token = $getdata['access_token'];
$domain = $getdata['shop_domain'];

if (isset($_GET['action']) && $_GET['action'] == 'product_creation') {
    $rawData = file_get_contents("php://input");
    $admin->product_creation($rawData);
} else if (isset($_POST['action']) && $_POST['action'] == 'update_options') {
    $result = $admin->update_options();
    echo json_encode($result);
} else if (isset($_POST['action']) && $_POST['action'] == 'get_options') {
    $admin->get_options();
} else if (isset($_POST['action']) && $_POST['action'] == 'get_price') {
    $admin->get_price();
} else if (isset($_POST['action']) && $_POST['action'] == 'get_option_values') {
    $admin->get_option_values();
} else if (isset($_POST['action']) && $_POST['action'] == 'add_new_option') {
    $admin->add_new_option();
} else if (isset($_POST['action']) && $_POST['action'] == 'update_option_values') {
    $admin->update_option_values();
} else if (isset($_POST['action']) && $_POST['action'] == 'get_selected_option_values') {
    $admin->get_selected_option_values();
} else if (isset($_POST['action']) && $_POST['action'] == 'update_product_selected_option') {
    $admin->update_product_selected_option();
} else if (isset($_POST['action']) && $_POST['action'] == 'get_all_available_options') {
    $admin->get_all_available_options();
} else if (isset($_POST['action']) && $_POST['action'] == 'add_product_option') {
    $admin->add_product_option();
} else if (isset($_POST['action']) && $_POST['action'] == 'get_rule_details') {
    $admin->get_rule_details();
} else if (isset($_POST['action']) && $_POST['action'] == 'update_rule') {
    $admin->update_rule();
} else if (isset($_POST['action']) && $_POST['action'] == 'create_rule') {
    $admin->create_rule();
} else if (isset($_POST['action']) && $_POST['action'] == 'duplicate_rule') {
    $admin->duplicate_rule();
} else if (isset($_POST['action']) && $_POST['action'] == 'delete_rule') {
    $admin->delete_rule();
} else if (isset($_POST['action']) && $_POST['action'] == 'update_rule_order') {
    $admin->update_rule_order();
} else if (isset($_POST['action']) && $_POST['action'] == 'update_option_order') {
    $admin->update_option_order();
} else if (isset($_POST['action']) && $_POST['action'] == 'update_adjuster_status') {
    $admin->update_adjuster_status();
} else if (isset($_POST['action']) && $_POST['action'] == 'update_rule_value') {
    $admin->update_rule_value();
} else if (isset($_POST['action']) && $_POST['action'] == 'delete_option') {
    $admin->delete_option();
} else if (isset($_POST['action']) && $_POST['action'] == 'get_engraving_instructions') {
    header('Content-Type: application/json');
    echo json_encode(['success' => true, 'data' => $admin->get_engraving_instructions($domain)]);
}
