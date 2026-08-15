<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
start_session_once();
require_https();

$params = $_GET;
$shop   = isset($params['shop']) ? normalize_shop_domain($params['shop']) : '';
$code   = $params['code'] ?? '';

if (!$shop || !$code) {
    http_response_code(400);
    exit('Missing required parameters.');
}

if (SHOPIFY_ALLOWED_SHOP && $shop !== SHOPIFY_ALLOWED_SHOP) {
    http_response_code(403);
    exit('This app is locked to a specific store.');
}

if (!verify_shopify_hmac($params, SHOPIFY_API_SECRET)) {
    http_response_code(403);
    exit('Invalid HMAC – request not from Shopify.');
}

$tokenResp = exchange_access_token($shop, $code);
if (!empty($tokenResp['error']) || empty($tokenResp['access_token'])) {
    http_response_code(500);
    echo "Failed to get access token. " . htmlspecialchars($tokenResp['error'] ?? 'Unknown error');
    exit;
}
$accessToken = $tokenResp['access_token'];

upsert_shop_token($shop, $accessToken);

if (INSTALL_SCRIPT_TAG) {
    $payload = [
        'script_tag' => [
            'event' => 'onload',
            'src'   => SCRIPT_TAG_SRC
        ]
    ];
    $r = shopify_request($shop, 'POST', 'script_tags.json', $accessToken, $payload);
}

if (REGISTER_WEBHOOKS_ON_INSTALL) {
    $webhookUrl = APP_URL . '/webhook_handler.php';
    $requiredWebhookTopics = array_unique(array_merge(WEBHOOK_TOPICS, ['orders/create', 'orders/cancelled', 'products/create', 'products/update', 'products/delete']));
    foreach ($requiredWebhookTopics as $topic) {
        $payload = [
            'webhook' => [
                'topic'   => $topic,
                'address' => $webhookUrl,
                'format'  => 'json'
            ]
        ];
        $res = shopify_request($shop, 'POST', 'webhooks.json', $accessToken, $payload);
    }
}

$_SESSION['access_token'] = $accessToken;
$_SESSION['hs_token'] = base64_encode(json_encode(['myshopifyDomain' => $shop]));
$_SESSION['shop'] = $shop;

// Already in a top-level tab after OAuth. 302 back into Admin so Shopify
// reloads this app inside the admin iframe.
redirect_to(shopify_admin_app_url($shop));


function exchange_access_token(string $shop, string $code): array {
    $url = "https://{$shop}/admin/oauth/access_token";
    $payload = [
        'client_id'     => SHOPIFY_API_KEY,
        'client_secret' => SHOPIFY_API_SECRET,
        'code'          => $code
    ];

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_POSTFIELDS     => json_encode($payload),
    ]);
    $resp = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $err = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        return ['error' => $err];
    }

    $data = json_decode($resp, true);
    if ($status >= 200 && $status < 300 && isset($data['access_token'])) {
        return $data;
    }

    return ['error' => "HTTP $status: " . $resp];
}
