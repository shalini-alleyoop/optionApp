<?php
$servername = "localhost";
$username = "u664957797_rhhj";
$password = "pbHF2LS9=C";
$dbname = "u664957797_optionApp";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("DB Connection failed: " . $conn->connect_error);
}

$shop = isset($_GET['shop']) ? htmlspecialchars($_GET['shop']) : '';

$previousPage ="dashboard.php?shop=" . urlencode($shop);

if (strpos($previousPage, "shop=") === false && $shop) {
    $previousPage .= (strpos($previousPage, '?') !== false ? '&' : '?') . "shop=" . urlencode($shop);
}
?>

<div class="nav-bar">
    <a href="<?= $previousPage ?>" class="back-button">⬅ Back</a>
</div>

<style>
    .nav-bar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        background: #fff;
        border-bottom: 2px solid #eee;
        padding: 12px 20px;
        margin-bottom: 20px;
        border-radius: 6px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
    }

    .back-button {
        text-decoration: none;
        background: crimson;
        color: #fff;
        padding: 8px 14px;
        border-radius: 4px;
        font-weight: bold;
        transition: background 0.3s ease;
    }

    .back-button:hover {
        background: darkred;
    }

    .shop-label {
        font-size: 14px;
        color: #555;
        font-weight: 500;
    }
</style>