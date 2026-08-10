<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';

class OptionInventoryMissingProductException extends RuntimeException {}

function option_inventory_ids($raw): array {
    if (is_array($raw)) $raw = implode(',', $raw);
    preg_match_all('/\d+/', (string)$raw, $matches);
    return array_values(array_unique(array_filter(array_map('intval', $matches[0] ?? []))));
}

function option_inventory_graphql(string $shop, string $token, string $query, array $variables = []): array {
    $response = shopify_request($shop, 'POST', 'graphql.json', $token, ['query' => $query, 'variables' => $variables]);
    if (($response['status'] ?? 0) < 200 || ($response['status'] ?? 0) >= 300 || !empty($response['error'])) {
        throw new RuntimeException('Shopify inventory request failed.');
    }
    $body = $response['body'] ?? [];
    if (!empty($body['errors'])) throw new RuntimeException($body['errors'][0]['message'] ?? 'Shopify GraphQL error.');
    return $body['data'] ?? [];
}

function option_inventory_variant_state(string $shop, string $token, int $variantId, ?int $preferredLocation = null): array {
    $query = <<<'GQL'
query VariantInventory($id: ID!) {
  productVariant(id: $id) {
    id title sku inventoryPolicy
    product { id title tags }
    inventoryItem {
      id tracked
      inventoryLevels(first: 100) { nodes { location { id name } quantities(names: ["available"]) { name quantity } } }
    }
  }
}
GQL;
    $data = option_inventory_graphql($shop, $token, $query, ['id' => 'gid://shopify/ProductVariant/' . $variantId]);
    $variant = $data['productVariant'] ?? null;
    if (!$variant) throw new OptionInventoryMissingProductException('Connected Shopify product or variant no longer exists.');
    if (!in_array('option_only', $variant['product']['tags'] ?? [], true)) throw new RuntimeException('Connected product is missing the option_only tag.');
    $item = $variant['inventoryItem'] ?? [];
    $levels = $item['inventoryLevels']['nodes'] ?? [];
    $chosen = null;
    foreach ($levels as $level) {
        $locationId = (int)preg_replace('/\D+/', '', $level['location']['id'] ?? '');
        if ($preferredLocation && $locationId === $preferredLocation) { $chosen = $level; break; }
        if ($chosen === null) $chosen = $level;
    }
    $available = 0;
    if ($chosen) foreach (($chosen['quantities'] ?? []) as $q) if (($q['name'] ?? '') === 'available') $available = (int)$q['quantity'];
    return [
        'product_id' => (int)preg_replace('/\D+/', '', $variant['product']['id']),
        'variant_id' => (int)preg_replace('/\D+/', '', $variant['id']),
        'inventory_item_id' => (int)preg_replace('/\D+/', '', $item['id'] ?? ''),
        'location_id' => $chosen ? (int)preg_replace('/\D+/', '', $chosen['location']['id']) : null,
        'tracked' => !empty($item['tracked']), 'available' => $available,
        'product_title' => $variant['product']['title'] ?? '', 'variant_title' => $variant['title'] ?? '', 'sku' => $variant['sku'] ?? ''
    ];
}

function option_inventory_adjust(string $shop, string $token, int $inventoryItemId, int $locationId, int $delta, string $reference): void {
    $usesIdempotency = defined('SHOPIFY_API_VERSION') && version_compare(SHOPIFY_API_VERSION, '2026-01', '>=');
    $mutation = $usesIdempotency
        ? 'mutation AdjustInventory($input: InventoryAdjustQuantitiesInput!, $idempotencyKey: String!) { inventoryAdjustQuantities(input: $input) @idempotent(key: $idempotencyKey) { userErrors { field message } } }'
        : 'mutation AdjustInventory($input: InventoryAdjustQuantitiesInput!) { inventoryAdjustQuantities(input: $input) { userErrors { field message } } }';
    $input = ['reason' => 'correction', 'name' => 'available', 'referenceDocumentUri' => $reference,
        'changes' => [['delta' => $delta, 'inventoryItemId' => 'gid://shopify/InventoryItem/' . $inventoryItemId, 'locationId' => 'gid://shopify/Location/' . $locationId]]];
    $variables = ['input' => $input];
    if ($usesIdempotency) $variables['idempotencyKey'] = hash('sha256', $shop . '|' . $reference . '|' . $inventoryItemId . '|' . $locationId . '|' . $delta);
    $data = option_inventory_graphql($shop, $token, $mutation, $variables);
    $errors = $data['inventoryAdjustQuantities']['userErrors'] ?? [];
    if ($errors) throw new RuntimeException($errors[0]['message'] ?? 'Inventory adjustment was rejected.');
}

function option_inventory_mappings(string $shop, array $valueIds): array {
    if (!$valueIds) return [];
    $marks = implode(',', array_fill(0, count($valueIds), '?'));
    $stmt = db()->prepare("SELECT m.*, v.option_id, v.label AS option_value, o.display_name AS option_title FROM option_value_shopify_products m JOIN shopify_option_products sop ON sop.shop_domain=m.shop_domain AND sop.shopify_variant_id=m.shopify_variant_id AND sop.is_option_only=1 AND sop.deleted_at IS NULL JOIN bg_option_values v ON v.option_value_id=m.option_value_id JOIN bg_options o ON o.option_id=v.option_id WHERE m.shop_domain=? AND m.option_value_id IN ($marks)");
    $stmt->execute(array_merge([$shop], $valueIds));
    $out = []; foreach ($stmt->fetchAll() as $row) $out[(int)$row['option_value_id']] = $row;
    return $out;
}

function option_inventory_disconnect_missing(string $shop, int $optionValueId): void {
    $stmt = db()->prepare('DELETE FROM option_value_shopify_products WHERE shop_domain=? AND option_value_id=?');
    $stmt->execute([$shop, $optionValueId]);
}

function option_inventory_validate_cart(string $shop, array $lines, bool $availabilityCheck = false): array {
    $shopRow = get_shop($shop); if (!$shopRow) throw new RuntimeException('Shop is not connected.');
    $requested = []; $lineRefs = [];
    foreach ($lines as $line) {
        $qty = max(0, (int)($line['quantity'] ?? 0)); if (!$qty) continue;
        foreach (option_inventory_ids($line['option_value_ids'] ?? '') as $id) {
            $requested[$id] = ($requested[$id] ?? 0) + $qty;
            $lineRefs[$id] = $line['key'] ?? '';
        }
    }
    $mappings = option_inventory_mappings($shop, array_keys($requested)); $errors = [];
    $variantTotals = [];
    foreach ($mappings as $id => $mapping) $variantTotals[(int)$mapping['shopify_variant_id']] = ($variantTotals[(int)$mapping['shopify_variant_id']] ?? 0) + $requested[$id];
    $checked = [];
    foreach ($mappings as $id => $mapping) {
        $variantId = (int)$mapping['shopify_variant_id'];
        try {
            if (!isset($checked[$variantId])) $checked[$variantId] = option_inventory_variant_state($shop, $shopRow['access_token'], $variantId, $mapping['location_id'] ? (int)$mapping['location_id'] : null);
            $state = $checked[$variantId]; $need = $availabilityCheck ? 1 : $variantTotals[$variantId];
            if ($state['tracked'] && $state['available'] < $need) $errors[] = ['cart_line_key'=>$lineRefs[$id], 'option_value_id'=>$id, 'option_title'=>$mapping['option_title'], 'option_value'=>$mapping['option_value'], 'requested_quantity'=>$need, 'available_quantity'=>$state['available'], 'message'=>$state['available'] <= 0 ? $mapping['option_value'].' is currently out of stock.' : 'Only '.$state['available'].' of '.$mapping['option_value'].' is available; your cart requires '.$need.'.'];
        } catch (OptionInventoryMissingProductException $e) {
            // A deleted Shopify product behaves exactly like no connection.
            option_inventory_disconnect_missing($shop, (int)$id);
        } catch (Throwable $e) { $errors[] = ['cart_line_key'=>$lineRefs[$id], 'option_value_id'=>$id, 'option_title'=>$mapping['option_title'], 'option_value'=>$mapping['option_value'], 'message'=>$e->getMessage()]; }
    }
    return ['success'=>true, 'checkout_allowed'=>!$errors, 'errors'=>$errors];
}

function option_inventory_process_order(string $shop, array $order): void {
    $shopRow=get_shop($shop); if(!$shopRow) throw new RuntimeException('Shop token missing.');
    foreach (($order['line_items'] ?? []) as $line) {
        $ids=[]; foreach (($line['properties'] ?? []) as $p) if (($p['name'] ?? '') === '_Option Value IDs') $ids=option_inventory_ids($p['value'] ?? '');
        $mappings=option_inventory_mappings($shop,$ids); $qty=max(1,(int)($line['quantity']??1));
        foreach($mappings as $id=>$m){
            $exists=db()->prepare('SELECT * FROM option_inventory_orders WHERE shop_domain=? AND shopify_order_id=? AND shopify_line_item_id=? AND option_value_id=?');
            $exists->execute([$shop,(int)$order['id'],(int)$line['id'],$id]); $ledger=$exists->fetch();
            if($ledger && ($ledger['inventory_deducted'] || (!$ledger['inventory_tracked'] && !$ledger['processing_error']))) continue;
            $location=$m['location_id']?(int)$m['location_id']:null;
            if(!$ledger){$stmt=db()->prepare('INSERT INTO option_inventory_orders (shop_domain,shopify_order_id,order_number,shopify_line_item_id,option_id,option_value_id,option_title,option_value,shopify_product_id,shopify_variant_id,inventory_item_id,location_id,quantity,processing_error) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)');$stmt->execute([$shop,(int)$order['id'],(string)($order['order_number']??$order['name']??''),(int)$line['id'],(int)$m['option_id'],$id,$m['option_title'],$m['option_value'],(int)$m['shopify_product_id'],(int)$m['shopify_variant_id'],(int)$m['inventory_item_id'],$location,$qty,'Pending inventory check']);$ledgerId=(int)db()->lastInsertId();}else{$ledgerId=(int)$ledger['id'];}
            try{$state=option_inventory_variant_state($shop,$shopRow['access_token'],(int)$m['shopify_variant_id'],$location);$tracked=$state['tracked']?1:0;$location=$state['location_id'];if($tracked){if(!$location||$state['available']<$qty)throw new RuntimeException('Insufficient connected-option inventory.');option_inventory_adjust($shop,$shopRow['access_token'],(int)$m['inventory_item_id'],$location,-$qty,'gid://option-app/Order/'.(int)$order['id'].'/OptionValue/'.$id);}$up=db()->prepare('UPDATE option_inventory_orders SET location_id=?,inventory_tracked=?,inventory_deducted=?,processing_error=NULL WHERE id=?');$up->execute([$location,$tracked,$tracked?1:0,$ledgerId]);}catch(OptionInventoryMissingProductException $e){option_inventory_disconnect_missing($shop,(int)$id);db()->prepare('DELETE FROM option_inventory_orders WHERE id=? AND inventory_deducted=0')->execute([$ledgerId]);continue;}catch(Throwable $e){$up=db()->prepare('UPDATE option_inventory_orders SET processing_error=? WHERE id=?');$up->execute([$e->getMessage(),$ledgerId]);throw $e;}
        }
    }
}

function option_inventory_cancel_order(string $shop, array $order): void {
    $shopRow=get_shop($shop);if(!$shopRow)throw new RuntimeException('Shop token missing.');
    $stmt=db()->prepare('SELECT * FROM option_inventory_orders WHERE shop_domain=? AND shopify_order_id=? AND delete_flag=0');$stmt->execute([$shop,(int)$order['id']]);
    foreach($stmt->fetchAll() as $row){try{if($row['inventory_deducted']&&!$row['inventory_restored'])option_inventory_adjust($shop,$shopRow['access_token'],(int)$row['inventory_item_id'],(int)$row['location_id'],(int)$row['quantity'],'gid://option-app/OrderCancellation/'.(int)$order['id']);$up=db()->prepare('UPDATE option_inventory_orders SET inventory_restored=?,delete_flag=1,cancelled_at=NOW(),processing_error=NULL WHERE id=? AND delete_flag=0');$up->execute([$row['inventory_deducted']?1:0,$row['id']]);}catch(Throwable $e){$up=db()->prepare('UPDATE option_inventory_orders SET processing_error=? WHERE id=?');$up->execute([$e->getMessage(),$row['id']]);throw $e;}}
}
