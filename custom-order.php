<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/connect.php';
start_session_once();
require_https();
send_embed_headers();
redirect_if_no_shopify_context();

if (empty($_SESSION['custom_order_csrf'])) {
    $_SESSION['custom_order_csrf'] = bin2hex(random_bytes(32));
}

$hw_token = $_GET['hw_token'] ?? '';
$decoded  = $hw_token ? json_decode(base64_decode($hw_token), true) : [];
$shop_myshopifyDomain = $decoded['myshopifyDomain'] ?? ($_SESSION['shop'] ?? null);
$shop_domain          = $_GET['shop'] ?? null;
$shop = $shop_myshopifyDomain ?? $shop_domain;
$productId = isset($_GET['productId']) ? (int)$_GET['productId'] : 0;

if (!$shop) {
    http_response_code(401);
    exit('No shop context.');
}
$row = $admin->get_shop_detail($shop);
if ($row) {
    $_SESSION['shop'] = $row['shop_domain'];
    $shop = $row['shop_domain'];
}

$productPayload = null;
$productError = '';
if ($row && $productId) {
    $productPayload = $admin->get_shopify_product_for_custom_order($row, $productId);
    if (empty($productPayload['success'])) {
        $productError = $productPayload['message'] ?? 'Unable to load this product.';
        $productPayload = null;
    }
}

$boot = [
    'shop' => $shop,
    'csrf' => $_SESSION['custom_order_csrf'],
    'endpoint' => 'process.php?domain=' . rawurlencode((string)$shop),
    'inventoryEndpoint' => 'validate-option-inventory.php',
    'currency' => $productPayload['currency'] ?? 'USD',
    'product' => $productPayload['product'] ?? null,
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= $boot['product'] ? htmlspecialchars($boot['product']['title'] . ' — Custom Order') : 'Custom Order' ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <?php render_embed_head(); ?>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap');
        * { font-family: "Montserrat", sans-serif; box-sizing: border-box; }
        body { margin: 0; padding: 40px; background: #F5F5F5; color: #000; }
        .nav-bar { margin-bottom: 20px; }
        .back-button { text-decoration: none; background: #000; color: #fff; padding: 8px 14px; border-radius: 4px; font-weight: 400; display: inline-block; }
        .dashboard-wrapper-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; gap: 16px; flex-wrap: wrap; }
        .dashboard-title { font-size: 24px; color: #333; margin: 0; font-weight: 600; }
        .install-btn { display: inline-block; background-color: #2196F3; color: white; padding: 10px 24px; font-size: 14px; border-radius: 6px; text-decoration: none; }
        .product-search { display: flex; justify-content: space-between; margin-bottom: 15px; }
        .product-search input[type="text"] { flex: 1; font-size: 14px; padding: 5px 10px; border: 1px solid #E0E0E0; border-radius: 6px; margin-right: 10px; height: 38px; }
        .product-search button { cursor: pointer; font-size: 14px; padding: 8px 20px; border-radius: 6px; border: none; background: #263238; color: #fff; height: 38px; }
        .products-grid { display: flex; flex-direction: column; gap: 10px; padding: 10px; background: #fff; border-radius: 8px; }
        .product-card { display: flex; align-items: center; padding: 10px; border: 1px solid #dedede; border-radius: 8px; width: 100%; gap: 20px; position: relative; }
        .product-card-link { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1; }
        .product-image { max-width: 70px; height: 70px; position: relative; border-radius: 8px; overflow: hidden; border: 1px solid #dedede; width: 100%; }
        .product-image img { position: absolute; top: 0; left: 0; width: 100%; height: 100%; object-fit: cover; }
        .product-info { display: flex; align-items: center; width: calc(100% - 70px); justify-content: space-between; gap: 20px; }
        .product-title { font-size: 16px; line-height: 1.5; }
        .pagination-wrapper { display: flex; justify-content: flex-end; gap: 10px; margin-top: 20px; }
        .pagination-link { display: inline-flex; background: #1976D2; color: #fff; text-decoration: none; height: 30px; width: 60px; align-items: center; justify-content: center; font-size: 12px; border-radius: 10px; }
        .error-banner { background: #fff; border: 1px solid #e0e0e0; color: #b71c1c; padding: 16px; border-radius: 8px; }

        .custom-order-layout { display: grid; grid-template-columns: minmax(0, 1.05fr) minmax(0, .95fr); gap: 32px; background: #fff; padding: 24px; border-radius: 8px; }
        .gallery-main { position: relative; width: 100%; padding-top: 100%; border: 1px solid #ccc; border-radius: 5px; overflow: hidden; background: #fafafa; }
        .gallery-main img { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; display: block; }
        .gallery-thumbs { display: flex; flex-wrap: wrap; gap: 8px; margin-top: 12px; }
        .gallery-thumbs button { width: 64px; height: 64px; padding: 0; border: 1px solid #ccc; border-radius: 5px; overflow: hidden; cursor: pointer; background: #fff; }
        .gallery-thumbs button.active, .gallery-thumbs button:hover { border-color: #000; }
        .gallery-thumbs img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .product__title { font-size: 28px; line-height: 1.2; margin: 0 0 12px; }
        .product-price { font-size: 18px; letter-spacing: .5px; margin: 0 0 20px; }
        .product-price.is-loading { opacity: .55; }
        .variant-group, .option-values-wp { width: 100%; display: flex; flex-wrap: wrap; gap: 10px 5px; margin-bottom: 15px; position: relative; }
        .variant-group legend, .custom-product-options > div legend { flex: 0 0 100%; color: #000; font-size: 16px; padding: 0; }
        .custom-product-options [type=radio] { opacity: 0; pointer-events: none; position: absolute; left: 0; top: 0; width: 100%; height: 100%; }
        .custom-product-options [type=radio] + label,
        .variant-group [type=radio] + label { display: flex; align-items: center; justify-content: center; flex-direction: column; flex: 0 0 calc(100% / 4 - 15px / 4); gap: 10px; padding: 10px; border: 1px solid #ccc; font-size: 14px; color: #000; transition: .3s; cursor: pointer; text-align: center; border-radius: 5px; text-transform: capitalize; }
        .custom-product-options [type=radio]:checked + label,
        .variant-group [type=radio]:checked + label { border-color: #000; }
        .custom-product-options [type=radio] + label img { width: 30px; height: 30px; object-fit: cover; border-radius: 50%; display: block; }
        .custom-product-options input[type=text],
        .custom-product-options select { color: #000; font-size: 14px; padding: 10px; border: 1px solid #000; display: block; width: 100%; margin: 0; }
        .custom-select-wrap { position: relative; display: inline-block; width: 100%; }
        .custom-select-hidden { position: absolute; left: 0; top: 0; width: 100%; height: 100%; opacity: 0; pointer-events: none; z-index: -1; }
        .custom-select { position: relative; }
        .custom-select .selected { border: 1px solid #ccc; padding: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px; background: #fff; user-select: none; }
        .custom-select .options { position: absolute; width: 100%; border: 1px solid #ccc; border-top: none; background: #fff; display: none; z-index: 20; max-height: 180px; overflow-y: auto; }
        .custom-select .option { padding: 8px; cursor: pointer; display: flex; align-items: center; gap: 8px; }
        .custom-select .option:hover { background: #f0f0f0; }
        .custom-select .option.active { background: #f5f5f5; font-weight: 600; }
        .custom-select img { width: 24px; height: 24px; object-fit: cover; }
        .custom-product-options-inline-wrap { margin-bottom: 20px; }
        .custom-order-extra { display: grid; gap: 10px; margin: 18px 0; }
        .custom-order-extra label { font-size: 14px; font-weight: 600; }
        .custom-order-extra input, .custom-order-extra textarea { width: 100%; border: 1px solid #000; padding: 10px; font-size: 14px; }
        .custom-drawer-atc_btnwpr { display: flex; gap: 5px; align-items: stretch; }
        .quantity-selector { display: flex; align-items: center; border: 1px solid #000; width: 100px; height: 48px; }
        .quantity-selector button { background: none; border: 0; width: 32px; height: 100%; cursor: pointer; }
        .quantity-selector input { width: 36px; border: 0; text-align: center; font-size: 14px; -moz-appearance: textfield; }
        .quantity-selector input::-webkit-outer-spin-button,
        .quantity-selector input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
        #createDraftOrder { position: relative; width: calc(100% - 105px); background: #000; color: #fff; border: 0; cursor: pointer; font-size: 14px; padding: 0 16px; }
        #createDraftOrder.btn--loading { opacity: .85; pointer-events: none; }
        .engraving-instructions { margin: 15px 0; width: 100%; padding: 0 0 15px; font-size: 14px; border-bottom: 1px solid #000; }
        .engraving-instructions-trigger { display: flex; align-items: center; justify-content: space-between; width: 100%; line-height: 1; padding: 0; background: none; border: 0; cursor: pointer; font-size: 14px; }
        .engraving-instructions-content { overflow: hidden; height: 0; }
        ul.engraving-instruction-list { margin: 0; list-style: none; padding: 0; color: #000; font-size: 14px; }
        .engraving-instruction-list li { display: flex; align-items: center; gap: 10px; line-height: 1.2; }
        .engraving-instruction-list li span { display: block; width: 5px; height: 5px; background: #000; border-radius: 50%; min-width: 5px; }
        .status-banner { margin-top: 16px; padding: 14px; border-radius: 6px; border: 1px solid #ccc; display: none; }
        .status-banner.success { display: block; border-color: #000; }
        .status-banner.error { display: block; color: #b71c1c; border-color: #e57373; }
        .status-banner a { color: #000; font-weight: 600; }
        .sku-line { font-size: 12px; color: #555; margin: -12px 0 16px; }
        @keyframes btn-spin { to { transform: rotate(360deg); } }
        @media (max-width: 900px) {
            body { padding: 20px; }
            .custom-order-layout { grid-template-columns: 1fr; }
            .custom-product-options [type=radio] + label,
            .variant-group [type=radio] + label { flex: 0 0 calc(50% - 5px); }
        }
    </style>
</head>
<body>
    <?php render_embed_nav(); ?>
    <div class="nav-bar">
        <a href="dashboard.php?shop=<?= urlencode($shop) ?>" class="back-button">← Back</a>
    </div>

    <?php if (!$row): ?>
        <div class="error-banner">This shop is not connected. <a href="install.php?shop=<?= urlencode($shop) ?>">Install the app</a>.</div>
    <?php elseif ($productId && $productError): ?>
        <div class="error-banner"><?= htmlspecialchars($productError) ?></div>
    <?php elseif ($boot['product']): ?>
        <div class="dashboard-wrapper-header">
            <h1 class="dashboard-title">Custom Order</h1>
        </div>
        <div class="custom-order-layout" id="customOrderApp">
            <div class="custom-order-gallery">
                <div class="gallery-main">
                    <img id="mainProductImage" src="<?= htmlspecialchars($boot['product']['featuredImage'] ?: '') ?>" alt="<?= htmlspecialchars($boot['product']['title']) ?>">
                </div>
                <div class="gallery-thumbs" id="productThumbs"></div>
            </div>
            <div class="custom-order-details">
                <h2 class="product__title"><?= htmlspecialchars($boot['product']['title']) ?></h2>
                <p class="product-price" id="productPriceDisplay">$0.00</p>
                <p class="sku-line" id="variantSku"></p>
                <form id="customOrderForm" novalidate>
                    <div id="variantOptions"></div>
                    <div id="customOptionsMount"></div>
                    <div class="engraving-instructions">
                        <button class="engraving-instructions-trigger" type="button">
                            <span>Engraving & Customization Terms</span>
                            <svg aria-hidden="true" focusable="false" role="presentation" class="icon icon-nav-arrow-down" viewBox="0 0 24 24" width="18" height="18">
                                <path d="m6 9 6 6 6-6" stroke="#000" stroke-linecap="round" stroke-linejoin="round" fill="none"></path>
                            </svg>
                        </button>
                        <div class="engraving-instructions-content">
                            <p class="engraving-instruction-text">I understand that all custom items are made especially for me, are final sale, and that:</p>
                            <ul class="engraving-instruction-list">
                                <li><span></span>Orders are not eligible for exchanges, refunds, or cancellations.</li>
                                <li><span></span>No changes can be made once the order has been submitted.</li>
                                <li><span></span>Due to the handmade process, engraving may vary slightly from samples and previews.</li>
                                <li><span></span>Lettering may vary depending on the size, width, and length of the name.</li>
                                <li><span></span>Custom orders typically require approximately 3-6 weeks for production. Expedited service is available.</li>
                            </ul>
                        </div>
                    </div>
                    <div class="custom-order-extra">
                        <div>
                            <label for="customerEmail">Customer email (optional)</label>
                            <input id="customerEmail" type="email" name="customer_email" placeholder="customer@email.com" autocomplete="email">
                        </div>
                        <div>
                            <label for="orderNote">Order note (optional)</label>
                            <textarea id="orderNote" name="note" rows="3" placeholder="Internal note for this draft order"></textarea>
                        </div>
                    </div>
                    <div class="custom-drawer-atc_btnwpr">
                        <div class="quantity-selector">
                            <button class="quantity__minus" type="button" name="decrease" title="Decrease quantity">−</button>
                            <input id="orderQuantity" class="quantity__input" type="number" name="quantity" value="1" min="1" aria-label="quantity">
                            <button class="quantity__plus" type="button" name="increase" title="Increase quantity">+</button>
                        </div>
                        <button id="createDraftOrder" type="submit">
                            <span class="btn__text">Create Draft Order <span class="btn__price"></span></span>
                        </button>
                    </div>
                </form>
                <div id="orderStatus" class="status-banner"></div>
            </div>
        </div>
        <script>window.CUSTOM_ORDER_BOOT = <?= json_encode($boot, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
        <script src="admin-custom-order.js"></script>
    <?php else: ?>
        <?php
        $search = $_GET['search'] ?? '';
        $page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 1 ? (int)$_GET['page'] : 1;
        $responsedata = $admin->get_products_by_page($row, $page, $search);
        $response = is_array($responsedata) ? $responsedata : json_decode($responsedata, true);
        $products   = $response['data']['products']['nodes'] ?? [];
        $pageInfo   = $response['data']['products']['pageInfo'] ?? [];
        $hasNext    = $pageInfo['hasNextPage'] ?? false;
        $hasPrev    = $pageInfo['hasPreviousPage'] ?? false;
        $endCursor  = $pageInfo['endCursor'] ?? null;
        $startCursor = $pageInfo['startCursor'] ?? null;
        ?>
        <div class="dashboard-wrapper-header">
            <h1 class="dashboard-title">Create Custom Order</h1>
        </div>
        <form method="get" class="product-search">
            <input type="hidden" name="shop" value="<?= htmlspecialchars($shop) ?>">
            <input type="text" name="search" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>">
            <button type="submit">Search</button>
        </form>
        <div class="products-grid">
            <?php if (!empty($products)): ?>
                <?php foreach ($products as $p):
                    $pid = basename($p['id']); ?>
                    <div class="product-card">
                        <a class="product-card-link" href="custom-order.php?productId=<?= urlencode($pid) ?>&shop=<?= urlencode($shop) ?>"></a>
                        <?php if (!empty($p['media']['nodes'][0]['preview']['image']['url'])): ?>
                            <div class="product-image">
                                <img src="<?= htmlspecialchars($p['media']['nodes'][0]['preview']['image']['url']) ?>" alt="<?= htmlspecialchars($p['title']) ?>">
                            </div>
                        <?php endif; ?>
                        <div class="product-info">
                            <div class="product-title"><?= htmlspecialchars($p['title']) ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p>No products found.</p>
            <?php endif; ?>
        </div>
        <?php if (empty($search)): ?>
            <div class="pagination pagination-wrapper">
                <?php if ($hasPrev): ?>
                    <a class="pagination-link" href="?shop=<?= urlencode($shop) ?>&before=<?= urlencode($startCursor) ?>&page=<?= urlencode($page - 1) ?>">Prev</a>
                <?php endif; ?>
                <?php if ($hasNext): ?>
                    <a class="pagination-link" href="?shop=<?= urlencode($shop) ?>&after=<?= urlencode($endCursor) ?>&page=<?= urlencode($page + 1) ?>">Next</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>
