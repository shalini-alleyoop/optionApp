<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/connect.php';
start_session_once();
require_https();
$hw_token = $_GET['hw_token'] ?? '';
$decoded  = $hw_token ? json_decode(base64_decode($hw_token), true) : [];
$shop_myshopifyDomain = $decoded['myshopifyDomain'] ?? ($_SESSION['shop'] ?? null);
$shop_domain          = $_GET['shop'] ?? null;
$shop = $shop_myshopifyDomain ?? $shop_domain;
$row = $admin->get_row("SELECT * FROM shops WHERE shop_domain='$shop'");

$shop = $row['shop_domain'];
$accessToken = $row['access_token'];

$endpoint = "https://$shop/admin/api/2025-04/graphql.json";
$allproducts = $admin->get_results("SELECT * FROM bg_products WHERE shopify_product_id='' order by id asc ");
if (is_array($allproducts) && !empty($allproducts)) {
    foreach ($allproducts as $product) {
		echo $handle = trim($product['slug'], '/');
		$query = <<<GRAPHQL
		query {
		  productByHandle(handle: "$handle") {
			id
			title
			handle
		  }
		}
		GRAPHQL;

		$ch = curl_init($endpoint);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			"Content-Type: application/json",
			"X-Shopify-Access-Token: $accessToken"
		]);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['query' => $query]));

		$response = curl_exec($ch);
		if (curl_errno($ch)) {
			echo "cURL error: " . curl_error($ch);
			exit;
		}
		curl_close($ch);

		$data = json_decode($response, true);

		// Get the product ID
		$productIdFull = $data['data']['productByHandle']['id'] ?? null;
		if ($productIdFull) {
			// Extract the numeric ID from the Shopify GID
			$productId = basename($productIdFull);
			echo "✅ Product ID: $productId<br>";
			$admin->query("update bg_products SET shopify_product_id='$productId' where slug = '{$product['slug']}' LIMIT 1");
		} else {
			echo "❌ Product not found for handle: $handle<br>";
		}
	}
}
?>