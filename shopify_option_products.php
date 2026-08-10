<?php
require_once __DIR__ . '/db.php';

function shopify_option_product_tags($tags): array {
    if (is_array($tags)) return array_values(array_filter(array_map('trim', $tags)));
    return array_values(array_filter(array_map('trim', explode(',', (string)$tags))));
}

function shopify_option_product_is_tagged(array $tags): bool {
    foreach ($tags as $tag) if (strcasecmp($tag, 'option_only') === 0) return true;
    return false;
}

function shopify_option_products_upsert(string $shop, array $product): void {
    $productId = (int)($product['id'] ?? 0);
    if (!$productId) throw new InvalidArgumentException('Missing Shopify product ID.');
    $tags = shopify_option_product_tags($product['tags'] ?? []);
    $tagString = implode(', ', $tags);
    $isOptionOnly = shopify_option_product_is_tagged($tags) ? 1 : 0;
    $variants = is_array($product['variants'] ?? null) ? $product['variants'] : [];
    $variantIds = [];
    $sql = 'INSERT INTO shopify_option_products (shop_domain,shopify_product_id,shopify_variant_id,inventory_item_id,product_title,variant_title,handle,sku,tags,is_option_only,product_status,inventory_tracked,inventory_quantity,deleted_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NULL) ON DUPLICATE KEY UPDATE shopify_product_id=VALUES(shopify_product_id),inventory_item_id=VALUES(inventory_item_id),product_title=VALUES(product_title),variant_title=VALUES(variant_title),handle=VALUES(handle),sku=VALUES(sku),tags=VALUES(tags),is_option_only=VALUES(is_option_only),product_status=VALUES(product_status),inventory_tracked=VALUES(inventory_tracked),inventory_quantity=VALUES(inventory_quantity),deleted_at=NULL';
    $stmt = db()->prepare($sql);
    foreach ($variants as $variant) {
        $variantId = (int)($variant['id'] ?? 0); if (!$variantId) continue;
        $variantIds[] = $variantId;
        $tracked = !empty($variant['inventory_management']) ? 1 : 0;
        $stmt->execute([$shop,$productId,$variantId,!empty($variant['inventory_item_id'])?(int)$variant['inventory_item_id']:null,(string)($product['title']??''),(string)($variant['title']??''),(string)($product['handle']??''),(string)($variant['sku']??''),$tagString,$isOptionOnly,(string)($product['status']??''),$tracked,isset($variant['inventory_quantity'])?(int)$variant['inventory_quantity']:null]);
    }
    if ($variantIds) {
        $marks = implode(',', array_fill(0, count($variantIds), '?'));
        $delete = db()->prepare("UPDATE shopify_option_products SET is_option_only=0,deleted_at=NOW() WHERE shop_domain=? AND shopify_product_id=? AND shopify_variant_id NOT IN ($marks)");
        $delete->execute(array_merge([$shop,$productId],$variantIds));
    } else {
        db()->prepare('UPDATE shopify_option_products SET is_option_only=0,deleted_at=NOW() WHERE shop_domain=? AND shopify_product_id=?')->execute([$shop,$productId]);
    }
}

function shopify_option_products_delete(string $shop, int $productId): void {
    if (!$productId) throw new InvalidArgumentException('Missing Shopify product ID.');
    db()->prepare('UPDATE shopify_option_products SET is_option_only=0,deleted_at=NOW() WHERE shop_domain=? AND shopify_product_id=?')->execute([$shop,$productId]);
    // Deleted Shopify products immediately behave like unconnected options.
    db()->prepare('DELETE FROM option_value_shopify_products WHERE shop_domain=? AND shopify_product_id=?')->execute([$shop,$productId]);
}

function shopify_option_products_search(string $shop, string $term = '', int $limit = 200): array {
    $limit = max(1, min(500, $limit));
    $params = [$shop];
    $where = "shop_domain=? AND is_option_only=1 AND deleted_at IS NULL";
    if ($term !== '') { $where .= ' AND (product_title LIKE ? OR variant_title LIKE ? OR sku LIKE ?)'; $like='%'.$term.'%';array_push($params,$like,$like,$like); }
    $stmt=db()->prepare("SELECT shopify_product_id AS product_id,product_title,shopify_variant_id AS variant_id,variant_title,sku,inventory_item_id,inventory_tracked AS tracked,inventory_quantity FROM shopify_option_products WHERE $where ORDER BY product_title,variant_title LIMIT $limit");
    $stmt->execute($params); return $stmt->fetchAll();
}

function shopify_option_products_seed_from_database(string $shop): int {
    $countStmt=db()->prepare('SELECT COUNT(*) FROM shopify_option_products WHERE shop_domain=?');$countStmt->execute([$shop]);$count=(int)$countStmt->fetchColumn();if($count>0)return 0;
    $inserted=0;
    if (db()->query("SHOW TABLES LIKE 'shopify_products'")->fetchColumn()) {
        $rows=db()->query("SELECT json_data FROM shopify_products WHERE json_data IS NOT NULL AND json_data<>''")->fetchAll();
        foreach($rows as $row){$product=json_decode($row['json_data'],true);if(!is_array($product)||!shopify_option_product_is_tagged(shopify_option_product_tags($product['tags']??[])))continue;try{shopify_option_products_upsert($shop,$product);$inserted++;}catch(Throwable $e){error_log('Option product seed skipped: '.$e->getMessage());}}
    }
    // Product webhook logs in the supplied live DB contain newer products than the legacy dump.
    if (db()->query("SHOW TABLES LIKE 'webhook_logs'")->fetchColumn()) {
        $logs=db()->prepare("SELECT topic,payload FROM webhook_logs WHERE shop_domain=? AND topic IN ('products/create','products/update','products/delete') ORDER BY id ASC");
        $logs->execute([$shop]);
        foreach($logs->fetchAll() as $log){$product=json_decode($log['payload'],true);if(!is_array($product)||empty($product['id']))continue;try{if($log['topic']==='products/delete')shopify_option_products_delete($shop,(int)$product['id']);else shopify_option_products_upsert($shop,$product);}catch(Throwable $e){error_log('Option product webhook seed skipped: '.$e->getMessage());}}
    }
    // Preserve currently connected products even when the legacy product dump is old.
    $sql='INSERT IGNORE INTO shopify_option_products (shop_domain,shopify_product_id,shopify_variant_id,inventory_item_id,product_title,variant_title,sku,is_option_only) SELECT shop_domain,shopify_product_id,shopify_variant_id,inventory_item_id,product_title,variant_title,sku,1 FROM option_value_shopify_products WHERE shop_domain=?';
    $stmt=db()->prepare($sql);$stmt->execute([$shop]);
    return $inserted+$stmt->rowCount();
}
