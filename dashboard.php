<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/connect.php';
start_session_once();
require_https();
redirect_if_no_shopify_context();

$hw_token = $_GET['hw_token'] ?? '';
$decoded  = $hw_token ? json_decode(base64_decode($hw_token), true) : [];
$shop_myshopifyDomain = $decoded['myshopifyDomain'] ?? ($_SESSION['shop'] ?? null);
$shop_domain          = $_GET['shop'] ?? null;
$shop = $shop_myshopifyDomain ?? $shop_domain;


if (!$shop) {
    http_response_code(401);
    exit('No shop context.');
}
$row = $admin->get_shop_detail($shop);
// $create_collections = $admin->create_collections($row);
// $update_product_tags = $admin->update_product_tags($row);
$totalRecords = $admin->get_products_count($row);
$limit = 50;

$page = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 1
    ? (int)$_GET['page']
    : 1;

$totalPages = ceil($totalRecords / $limit);

if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $limit;

$data = range(1, $totalRecords);
$currentData = array_slice($data, $offset, $limit);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title>RHHJ Custom Product Options Dashboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap');

        * {
            font-family: "Montserrat", sans-serif;
            box-sizing: border-box;
        }

 body {
    margin: 0px;
    padding: 40px;
    background: #F5F5F5; /* Material Gray 100 */
}

.dashboard-wrapper-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
}

.dashboard-title {
    font-size: 24px;
    color: #333;
    margin: 0;
    font-weight: 600;
}

.install-btn {
    display: inline-block;
    background-color: #2196F3;
    color: white;
    padding: 10px 24px;
    font-size: 14px;
    border-radius: 6px;
    text-decoration: none;
    transition: background-color 0.3s ease, transform 0.2s ease;
}

.install-btn:hover {
    background-color: #1565C0; /* Material Blue 800 */
    transform: translateY(-2px);
}

.product-card {
    display: flex;
    align-items: center;
    padding: 10px;
    border: 1px solid #dedede;
    border-radius: 8px;
    width: 100%;
    gap: 20px;
    position: relative;
}

.product-card-link {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 1;
}

.products-grid {
    display: flex;
    flex-direction: column;
    gap: 10px;
    padding: 10px;
    background: #fff;
    border-radius: 8px;
}

.product-image {
    max-width: 70px;
    height: 70px;
    position: relative;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid #dedede;
    width: 100%;
}

.product-image img {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
}

.product-info {
    display: flex;
    align-items: center;
    width: calc(100% - 70px);
    justify-content: space-between;
    gap: 20px;
}

.product-title {
    font-size: 16px;
    line-height: 1.5;
    max-width: 30%;
    width: 100%;
}

.product-status {
    font-size: 12px;
    padding: 8px 15px;
    line-height: 01;
    color: #fff;
    background-color: #00C853;
    border-radius: 15px;
}

.product-search {
    display: flex;
    justify-content: space-between;
    margin-bottom: 15px;
}

.product-search input[type="text"] {
    flex: 1;
    font-size: 14px;
    font-weight: bold;
    padding: 5px 10px;
    border: 1px solid #E0E0E0;
    border-radius: 6px;
    margin-right: 10px;
    font-weight: 500;
    height: 38px;
}

.product-search button {
    cursor: pointer;
    font-size: 14px;
    padding: 8px 20px;
    border-radius: 6px;
    border: none;
    transition: all 0.2s ease;
    background: #263238;
    color: #fff;
    height: 38px;
}
a.btn.install-btn + a {
    background: #424242;
}
.product-search button:hover {
    background: #1565C0; /* Material Blue 800 */
}

.pagination-link {
    transition: all 0.2s ease;
    display: inline-flex;
    background: #1976D2; /* Material Blue 700 */
    color: #fff;
    text-decoration: none;
    line-height: 1;
    height: 30px;
    width: 60px;
    align-items: center;
    justify-content: center;
    gap: 5px;
    font-size: 12px;
    border-radius: 10px;
}

.pagination-link.prev:before {
    content: '\276E';
}

.pagination-link.next:after {
    content: '\276F';
}

.pagination-link.prev:before,
.pagination-link.next:after {
    display: inline-block;
    font-size: 10px;
    height: 8px;
}

.pagination.pagination-wrapper {
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
    margin-top: 20px;
}

.pagination-link:hover {
    background: #1565C0; /* Material Blue 800 */
}

    </style>
</head>

<body>
    <div class="dashboard-wrapper-header">
        <h2 class="dashboard-title">Products Dashboard</h2>
		<div>
			<a class="btn install-btn" href="alloptions.php?shop=<?= urlencode($shop) ?>">All Options</a>
			<a class="btn install-btn" href="insert_script.php?shop=<?= urlencode($shop) ?>">Insert Script</a>
			<a class="btn install-btn" href="engraving-editor.php?shop=<?= urlencode($shop) ?>">Engraving Instructions</a>
		</div>
    </div>
    <div class="dashboard-wrapper">
        <div class="shop-info">

            <?php if ($row): ?>
                <?php
                $search = $_GET['search'] ?? ''; // grab search term from querystring

                $responsedata = $admin->get_products_by_page($row, $page, $search);
                $response = is_array($responsedata) ? $responsedata : json_decode($responsedata, true);
                $products   = $response['data']['products']['nodes'] ?? [];
                $pageInfo   = $response['data']['products']['pageInfo'] ?? [];
                $hasNext    = $pageInfo['hasNextPage'] ?? false;
                $hasPrev    = $pageInfo['hasPreviousPage'] ?? false;
                $endCursor  = $pageInfo['endCursor'] ?? null;
                $startCursor = $pageInfo['startCursor'] ?? null;
                ?>
                <form method="get" class="product-search">
                    <input type="hidden" name="shop" value="<?= htmlspecialchars($shop) ?>">
                    <input type="text" name="search" placeholder="Search products..." value="<?= htmlspecialchars($search) ?>">
                    <button type="submit">Search</button>
                </form>

                <div class="products-grid">
                    <?php if (!empty($products)): ?>
                        <?php foreach ($products as $p): 
							$productId = basename($p['id']);
							$previewurl = $p['onlineStorePreviewUrl'];?>
                            <div class="product-card">
                                <a class="product-card-link" href="/singleproduct.php?previewurl=<?= $previewurl ?>&productId=<?= $productId ?>&shop=<?= urlencode($shop) ?>"></a>

                                <?php if (!empty($p['media']['nodes'][0]['preview']['image']['url'])): ?>
                                    <div class="product-image">
                                        <img src="<?= $p['media']['nodes'][0]['preview']['image']['url'] ?>" alt="<?= htmlspecialchars($p['title']) ?>">
                                    </div>
                                <?php endif; ?>

                                <div class="product-info">
                                    <div class="product-title"><?= htmlspecialchars($p['title']) ?></div>
                                    <div class="product-status">Active</div>
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
                            <a class="pagination-link prev" href="?shop=<?= urlencode($shop) ?>&before=<?= urlencode($startCursor) ?>&page=<?= urlencode($page - 1) ?>">Prev</a>
                        <?php endif; ?>

                        <?php if ($hasNext): ?>
                            <a class="pagination-link next" href="?shop=<?= urlencode($shop) ?>&after=<?= urlencode($endCursor) ?>&page=<?= urlencode($page + 1) ?>">Next</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>


            <?php else: ?>
                <div class="shop-row status-not-connected">Status: Not Connected ❌</div>
                <div class="shop-row">
                    <a class="btn install-btn" href="install.php?shop=<?= urlencode($shop) ?>">Install App</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>