<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/option_inventory.php';
require_once __DIR__ . '/shopify_option_products.php';
start_session_once();
header('Content-Type: application/json; charset=utf-8');

try {
    $shop = normalize_shop_domain((string)($_GET['shop'] ?? $_POST['shop'] ?? ''));
    if ($shop !== SHOPIFY_ALLOWED_SHOP) throw new RuntimeException('Invalid shop.');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $originHost = strtolower((string)parse_url($_SERVER['HTTP_ORIGIN'] ?? '', PHP_URL_HOST));
        $appHost = strtolower((string)parse_url(APP_URL, PHP_URL_HOST));
        $requestHost = strtolower(explode(':', (string)($_SERVER['HTTP_HOST'] ?? ''))[0]);
        if ($originHost === '' || !in_array($originHost, array_filter([$appHost, $requestHost]), true) || (($_SESSION['shop'] ?? '') !== $shop)) {
            http_response_code(403);
            throw new RuntimeException('Your Shopify admin session has expired. Reload the app and try again.');
        }
    }
    $shopRow = get_shop($shop); if (!$shopRow) throw new RuntimeException('Shop is not connected.');
    $action = (string)($_GET['action'] ?? $_POST['action'] ?? 'search');

    if ($action === 'search') {
        $term = trim((string)($_GET['q'] ?? ''));
        shopify_option_products_seed_from_database($shop);
        $items=shopify_option_products_search($shop,$term);
        echo json_encode(['success'=>true,'items'=>$items]); exit;
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') throw new RuntimeException('POST required.');
    $valueId=(int)($_POST['option_value_id']??0);
    $valid=db()->prepare('SELECT option_value_id FROM bg_option_values WHERE option_value_id=? AND status=1');$valid->execute([$valueId]);if(!$valid->fetch())throw new RuntimeException('Invalid option value.');
    if($action==='disconnect'){$stmt=db()->prepare('DELETE FROM option_value_shopify_products WHERE shop_domain=? AND option_value_id=?');$stmt->execute([$shop,$valueId]);echo json_encode(['success'=>true]);exit;}
    if($action!=='connect')throw new RuntimeException('Invalid action.');
    $variantId=(int)($_POST['variant_id']??0);$locationId=(int)($_POST['location_id']??0);if(!$variantId)throw new RuntimeException('Select a product variant.');
    $state=option_inventory_variant_state($shop,$shopRow['access_token'],$variantId,$locationId?:null);
    if($state['tracked']&&!$state['location_id'])throw new RuntimeException('Tracked inventory requires an inventory location.');
    $sql='INSERT INTO option_value_shopify_products (shop_domain,option_value_id,shopify_product_id,shopify_variant_id,inventory_item_id,location_id,product_title,variant_title,sku) VALUES (?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE shopify_product_id=VALUES(shopify_product_id),shopify_variant_id=VALUES(shopify_variant_id),inventory_item_id=VALUES(inventory_item_id),location_id=VALUES(location_id),product_title=VALUES(product_title),variant_title=VALUES(variant_title),sku=VALUES(sku)';
    $stmt=db()->prepare($sql);$stmt->execute([$shop,$valueId,$state['product_id'],$state['variant_id'],$state['inventory_item_id'],$state['location_id'],$state['product_title'],$state['variant_title'],$state['sku']]);
    echo json_encode(['success'=>true,'mapping'=>$state]);
} catch(Throwable $e){http_response_code(422);echo json_encode(['success'=>false,'message'=>$e->getMessage()]);}
