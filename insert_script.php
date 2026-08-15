<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/connect.php';
start_session_once();
require_https();
send_embed_headers();
redirect_if_no_shopify_context();

$hw_token = $_GET['hw_token'] ?? '';
$product_handle = $_GET['handle'] ?? '';
$decoded  = $hw_token ? json_decode(base64_decode($hw_token), true) : [];
$shop_myshopifyDomain = $decoded['myshopifyDomain'] ?? ($_SESSION['shop'] ?? null);
$shop_domain          = $_GET['shop'] ?? null;
$shop = $shop_myshopifyDomain ?? $shop_domain;

if (!$shop && !$product_handle) {
    http_response_code(401);
    exit('No shop or product handle context.');
}
$row = $admin->get_shop_detail($shop);

$shop = $row['shop_domain'];
$accessToken = $row['access_token'];

// Get all themes
function getThemes($shop, $accessToken)
{
    $url = "https://$shop/admin/api/" . SHOPIFY_API_VERSION . "/themes.json";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-Shopify-Access-Token: $accessToken"
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// Check if asset exists
function assetExists($shop, $accessToken, $themeId, $assetKey)
{
    $url = "https://$shop/admin/api/" . SHOPIFY_API_VERSION . "/themes/$themeId/assets.json?asset[key]=$assetKey";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-Shopify-Access-Token: $accessToken"
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    return isset($data['asset']);
}

// Save asset to theme
function saveAsset($shop, $accessToken, $themeId, $assetKey, $content)
{
    $url = "https://$shop/admin/api/" . SHOPIFY_API_VERSION . "/themes/$themeId/assets.json";
    $payload = json_encode([
        "asset" => [
            "key" => $assetKey,
            "value" => $content
        ]
    ]);

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-Shopify-Access-Token: $accessToken",
        "Content-Type: application/json",
        "Content-Length: " . strlen($payload)
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $response = curl_exec($ch);
    curl_close($ch);
    return $response;
}

// Check if theme.liquid contains our script
function scriptTagExists($shop, $accessToken, $themeId)
{
    $getUrl = "https://$shop/admin/api/" . SHOPIFY_API_VERSION . "/themes/$themeId/assets.json?asset[key]=layout/theme.liquid";
    $ch = curl_init($getUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-Shopify-Access-Token: $accessToken"
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    if (!isset($data['asset']['value'])) {
        return false;
    }
    $themeLiquid = $data['asset']['value'];
    $scriptTag = "<script src=\"{{ 'frontoptions.js' | asset_url }}\"></script>";
    return strpos($themeLiquid, $scriptTag) !== false;
}

// Insert script tag into theme.liquid
function insertScriptTag($shop, $accessToken, $themeId)
{
    $getUrl = "https://$shop/admin/api/" . SHOPIFY_API_VERSION . "/themes/$themeId/assets.json?asset[key]=layout/theme.liquid";
    $ch = curl_init($getUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-Shopify-Access-Token: $accessToken"
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    $data = json_decode($response, true);
    if (!isset($data['asset']['value'])) {
        return "❌ Failed to fetch theme.liquid";
    }

    $themeLiquid = $data['asset']['value'];
    $scriptTag = "<script src=\"{{ 'frontoptions.js' | asset_url }}\"></script>";
    if (strpos($themeLiquid, $scriptTag) === false) {
        $themeLiquid = str_replace("</body>", $scriptTag . "\n</body>", $themeLiquid);
    }

    $updateUrl = "https://$shop/admin/api/" . SHOPIFY_API_VERSION . "/themes/$themeId/assets.json";
    $payload = json_encode([
        "asset" => [
            "key" => "layout/theme.liquid",
            "value" => $themeLiquid
        ]
    ]);

    $ch = curl_init($updateUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "X-Shopify-Access-Token: $accessToken",
        "Content-Type: application/json",
        "Content-Length: " . strlen($payload)
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    $updateResponse = curl_exec($ch);
    curl_close($ch);

    return $updateResponse;
}

// ---------------- Handle Form Actions ----------------
$message = "";
$currentTheme = $_POST['theme_id'] ?? null;
$action = $_POST['action'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $currentTheme) {
    $filePath = __DIR__ . "/frontoptions.js";
    $fileContent = file_get_contents($filePath);

    if ($fileContent === false) {
        $message = "❌ Could not read frontoptions.js from server.";
    } else {
        if ($action === "insert") {
            $resp1 = saveAsset($shop, $accessToken, $currentTheme, "assets/frontoptions.js", $fileContent);
            $resp2 = insertScriptTag($shop, $accessToken, $currentTheme);
            $message = "✅ Inserted JS file + added script tag.<br>Upload: $resp1<br>Theme Update: $resp2";
        } elseif ($action === "update") {
            $resp = saveAsset($shop, $accessToken, $currentTheme, "assets/frontoptions.js", $fileContent);
            $message = "✅ JS file updated.<br>Response: $resp";
        }
    }
}

// Load themes
$themes = getThemes($shop, $accessToken);
$themeOptions = "";
if (!empty($themes['themes'])) {
    foreach ($themes['themes'] as $theme) {
        $selected = ($theme['id'] == $currentTheme) ? "selected" : "";
        $themeOptions .= "<option value='{$theme['id']}' $selected>{$theme['name']} ({$theme['role']})</option>";
    }
}

$hasJs = false;
$hasScriptTag = false;
if ($currentTheme) {
    $hasJs = assetExists($shop, $accessToken, $currentTheme, "assets/frontoptions.js");
    $hasScriptTag = scriptTagExists($shop, $accessToken, $currentTheme);
}
?>

<!DOCTYPE html>
<html>

<head>
    <title>Inster Script In Theme</title>
    <?php render_embed_head(); ?>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap');

        * {
            font-family: "Montserrat", sans-serif;
            box-sizing: border-box;
        }

        body {
            padding: 40px 20px;
            background: #f4f6f8;
            color: #333;
        }

        .container {
            background: #fff;
            padding: 30px;
            border-radius: 10px;
            max-width: 100%;
            margin: auto;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }

        h1 {
            font-size: 28px;
            margin-bottom: 25px;
            text-align: center;
            color: #222;
        }

        label {
            font-weight: 600;
            display: block;
            margin-bottom: 8px;
            font-size: 15px;
        }

        select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ccc;
            border-radius: 6px;
            background-color: #fefefe;
            font-size: 15px;
            transition: border-color 0.3s ease;
        }

        select:focus {
            border-color: #007bff;
            outline: none;
        }

        button {
            display: inline-block;
            background-color: #28a745;
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 20px;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        button:hover {
            background-color: #218838;
            transform: translateY(-2px);
        }

        button[value="insert"] {
            background-color: #007bff;
        }

        button[value="insert"]:hover {
            background-color: #0062cc;
        }

        .status {
            margin-top: 20px;
            background-color: #f1f1f1;
            padding: 15px;
            border-radius: 6px;
            font-size: 15px;
            color: #333;
            line-height: 1.6;
        }

        .message {
            margin-top: 25px;
            padding: 15px;
            background: #e0f7e9;
            color: #2c662d;
            border-left: 6px solid #2ecc71;
            border-radius: 6px;
            font-size: 15px;
        }

        @media (max-width: 600px) {
            .container {
                padding: 20px;
            }

            h1 {
                font-size: 24px;
            }

            button {
                width: 100%;
            }
        }
    </style>
</head>

<body>
    <?php render_embed_nav(); ?>
    <?php include 'navigation.php'; ?>
    <div class="container">
        <h1>Shopify Theme Script Manager</h1>

        <form method="post">
            <label for="theme_id">Select Theme:</label>
            <select name="theme_id" id="theme_id" onchange="this.form.submit()">
                <option value="">-- Select Theme --</option>
                <?= $themeOptions ?>
            </select>
        </form>

        <?php if ($currentTheme): ?>
            <div class="status">
                <p><?= $hasJs ? "✅ JS file exists in assets." : "⚠️ JS file missing." ?></p>
                <p><?= $hasScriptTag ? "✅ Script tag is in theme.liquid." : "⚠️ Script tag missing." ?></p>
            </div>

            <form method="post">
                <input type="hidden" name="theme_id" value="<?= htmlspecialchars($currentTheme) ?>">
                <?php if ($hasJs && $hasScriptTag): ?>
                    <button type="submit" name="action" value="update">Update Script</button>
                <?php else: ?>
                    <button type="submit" name="action" value="insert">Insert Script</button>
                <?php endif; ?>
            </form>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="message"><?= $message ?></div>
        <?php endif; ?>
    </div>
</body>

</html>