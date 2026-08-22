<?php
require_once __DIR__ . '/config.php';

function start_session_once(): void {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_name(SESSION_NAME);
        // Allow the session cookie to travel when the app is loaded inside
        // the Shopify admin iframe (top frame is admin.shopify.com; our app
        // is a cross-site iframe). Requires HTTPS.
        $params = [
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => true,
            'httponly' => true,
            'samesite' => 'None',
        ];
        session_set_cookie_params($params);
        session_start();
    }
}

function request_is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    // ngrok / Cloudflare / load balancers terminate TLS and forward HTTP to Apache
    $forwarded = strtolower((string)($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    if (str_starts_with($forwarded, 'https')) {
        return true;
    }
    return !empty($_SERVER['HTTP_X_FORWARDED_SSL']) && $_SERVER['HTTP_X_FORWARDED_SSL'] !== 'off';
}

function require_https(): void {
    if (!request_is_https()) {
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

function shopify_admin_app_url(?string $shop = null): string {
    $shop = normalize_shop_domain($shop ?: SHOPIFY_ALLOWED_SHOP);
    $handle = preg_replace('/\.myshopify\.com$/', '', $shop);
    return 'https://admin.shopify.com/store/' . $handle . '/apps/' . SHOPIFY_API_KEY;
}

function send_embed_headers(): void {
    $shop = SHOPIFY_ALLOWED_SHOP;
    header("Content-Security-Policy: frame-ancestors https://{$shop} https://admin.shopify.com;");
}

function app_base_path(): string {
    return rtrim((string)(parse_url(APP_URL, PHP_URL_PATH) ?: ''), '/');
}

function embed_query(array $extra = []): string {
    $params = [];
    if (!empty($_GET['shop'])) {
        $params['shop'] = normalize_shop_domain((string)$_GET['shop']);
    } elseif (!empty($_SESSION['shop'])) {
        $params['shop'] = (string)$_SESSION['shop'];
    }
    if (!empty($_GET['host'])) {
        $params['host'] = (string)$_GET['host'];
    }
    $params = array_merge($params, $extra);
    return $params ? ('?' . http_build_query($params)) : '';
}

function render_embed_head(): void {
    echo '<meta name="shopify-api-key" content="' . htmlspecialchars(SHOPIFY_API_KEY, ENT_QUOTES, 'UTF-8') . '">' . "\n";
    echo '<script src="https://cdn.shopify.com/shopifycloud/app-bridge.js"></script>' . "\n";
}

function render_embed_nav(): void {
    $base = app_base_path();
    $q = embed_query();
    $h = static function (string $url): string {
        return htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    };
    echo '<ui-nav-menu>';
    echo '<a href="' . $h($base . '/dashboard.php' . $q) . '" rel="home">Products</a>';
    echo '<a href="' . $h($base . '/custom-order.php' . $q) . '">Custom Order</a>';
    echo '<a href="' . $h($base . '/alloptions.php' . $q) . '">All Options</a>';
    echo '<a href="' . $h($base . '/insert_script.php' . $q) . '">Insert Script</a>';
    echo '<a href="' . $h($base . '/engraving-editor.php' . $q) . '">Engraving Instructions</a>';
    echo '</ui-nav-menu>';
}

function redirect_to(string $url): never {
    header('Location: ' . $url, true, 302);
    exit;
}

/**
 * Redirect the TOP-LEVEL browser window. Used when the destination refuses
 * iframe embedding (e.g. admin.shopify.com). From inside a Shopify admin
 * iframe a plain Location header causes "refused to connect" errors.
 */
function redirect_to_top(string $url): never {
    $dest = strtolower((string)($_SERVER['HTTP_SEC_FETCH_DEST'] ?? ''));
    // Already a top-level tab (OAuth callback, typed URL) — use a normal HTTP redirect.
    if ($dest !== 'iframe') {
        redirect_to($url);
    }

    $h = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');
    http_response_code(200);
    header('Content-Type: text/html; charset=utf-8');
    // From inside Shopify's iframe, admin.shopify.com cannot be loaded in the
    // frame. Click a target=_top link so the whole browser tab navigates.
    echo '<!doctype html><meta charset="utf-8"><title>Redirecting…</title>';
    echo '<p style="font-family:sans-serif;padding:30px;">Redirecting… <a id="continue" href="' . $h . '" target="_top">Continue in Shopify admin</a>.</p>';
    echo '<script>document.getElementById("continue").click();</script>';
    exit;
}

function mask_token(string $token): string {
    if (strlen($token) <= 8) return str_repeat('*', max(0, strlen($token)-2)) . substr($token, -2);
    return substr($token, 0, 4) . str_repeat('*', strlen($token) - 8) . substr($token, -4);
}

/**
 * Lightweight access check for admin pages. Bounces direct-browser visits to
 * the Shopify admin app URL so the app can only be opened through Shopify.
 *
 * Pass conditions (any one):
 *  - Sec-Fetch-Site is anything other than "none" -- this means the request
 *    came from a link, an iframe load, or another page, not a typed URL or
 *    bookmark. Reliably set by Chrome/Edge/Firefox/Safari and not forgeable
 *    from a normal browser address bar. Covers:
 *      * Shopify admin iframe loads (cross-site)
 *      * Intra-app link clicks from within the iframe (same-origin)
 *  - ?host= is present (Shopify admin iframe load, legacy browser fallback)
 *  - ?hw_token= is present (post-OAuth redirect from callback.php)
 *  - $_SESSION['shop'] is set (prior Shopify-admin entry in this session)
 *
 * Sec-Fetch-Site: "none" means the request was user-initiated with no
 * referring origin -- address bar, bookmark, new tab -- so we block it.
 *
 * This is a UX guard, NOT a cryptographic boundary. It just stops casual URL
 * typing; legit access via Shopify admin and its subsequent navigation is
 * always allowed without relying on cookies (which some browsers block in
 * cross-site iframe contexts).
 */
function redirect_if_no_shopify_context(): void {
    start_session_once();

    $secSite = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? '';

    // Any origin-bearing request (link / iframe / fetch / redirect chain).
    if ($secSite !== '' && $secSite !== 'none') {
        // Opportunistically persist the shop for intra-app navigation when
        // the session cookie survives; harmless if it doesn't.
        if (!empty($_GET['shop'])) {
            $incomingShop = normalize_shop_domain((string)$_GET['shop']);
            if ($incomingShop === SHOPIFY_ALLOWED_SHOP) {
                $_SESSION['shop'] = SHOPIFY_ALLOWED_SHOP;
            }
        }
        return;
    }

    // Shopify Admin iframe always sends shop= (and usually host=).
    if (!empty($_GET['shop'])) {
        $incomingShop = normalize_shop_domain((string)$_GET['shop']);
        if ($incomingShop === SHOPIFY_ALLOWED_SHOP) {
            $_SESSION['shop'] = SHOPIFY_ALLOWED_SHOP;
            return;
        }
    }
    if (!empty($_GET['host']))     { $_SESSION['shop'] = SHOPIFY_ALLOWED_SHOP; return; }
    if (!empty($_GET['hw_token'])) return;
    if (!empty($_SESSION['shop'])) return;

    // Direct URL typed in browser -> push to Shopify admin app URL.
    redirect_to_top(shopify_admin_app_url());
}
