<?php
require_once __DIR__ . '/config.php';

function start_session_once(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name(SESSION_NAME);
        session_start();
    }
}

function require_https(): void {
    if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === 'off') {
        http_response_code(400);
        exit('HTTPS is required by Shopify.');
    }
}

function normalize_shop_domain(string $shop): string {
    $shop = strtolower(trim($shop));
    if (!str_ends_with($shop, '.myshopify.com')) {
        $shop .= '.myshopify.com';
    }
    return $shop;
}

function verify_shopify_hmac(array $params, string $secret): bool {
    if (!isset($params['hmac'])) return false;
    $hmac = $params['hmac'];
    unset($params['hmac'], $params['signature']); // signature deprecated but still exclude

    ksort($params);
    $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    $calc = hash_hmac('sha256', $query, $secret);
    return hash_equals($hmac, $calc);
}

function verify_webhook(string $data, string $secret, ?string $hmacHeader): bool {
    if (!$hmacHeader) return false;
    $calculated = base64_encode(hash_hmac('sha256', $data, $secret, true));
    return hash_equals($hmacHeader, $calculated);
}

function shopify_request(string $shop, string $method, string $path, ?string $access_token = null, array $payload = null): array {
    $url = "https://{$shop}/admin/api/" . SHOPIFY_API_VERSION . '/' . ltrim($path, '/');
    $ch = curl_init($url);

    $headers = ['Content-Type: application/json'];
    if ($access_token) {
        $headers[] = "X-Shopify-Access-Token: {$access_token}";
    }

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $method = strtoupper($method);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload ? json_encode($payload) : '{}');
    } elseif ($method === 'PUT') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload ? json_encode($payload) : '{}');
    } elseif ($method === 'DELETE') {
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
    } // GET is default

    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['status' => $status, 'error' => $error, 'body' => null];
    }

    $decoded = json_decode($response, true);
    return ['status' => $status, 'error' => null, 'body' => $decoded, 'raw' => $response];
}

function redirect_to(string $url): never {
    header('Location: ' . $url, true, 302);
    exit;
}

function mask_token(string $token): string {
    if (strlen($token) <= 8) return str_repeat('*', max(0, strlen($token)-2)) . substr($token, -2);
    return substr($token, 0, 4) . str_repeat('*', strlen($token) - 8) . substr($token, -4);
}
