<?php

$allowed_origins = [
    'https://qheqg4-bu.myshopify.com',
    'https://royalhawaiianheritagejewelry.com',
    'https://www.royalhawaiianheritagejewelry.com',
];
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origin, $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $origin);
} else {
    header('Access-Control-Allow-Origin: https://qheqg4-bu.myshopify.com');
}
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, X-Requested-With");
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/connect.php';
start_session_once();
require_https();
$shop     = $_GET['domain'];
if (!$shop) {
    http_response_code(401);
    exit('No shop context.');
}

$getdata = $admin->get_shop_detail($shop);
$access_token = $getdata['access_token'];
$domain = $getdata['shop_domain'];

if (isset($_GET['action']) && $_GET['action'] == 'product_creation') {
    $rawData = file_get_contents("php://input");
    $admin->product_creation($rawData);
} else if (isset($_POST['action']) && $_POST['action'] == 'update_options') {
    $result = $admin->update_options();
    echo json_encode($result);
} else if (isset($_POST['action']) && $_POST['action'] == 'get_options') {
    $admin->get_options();
} else if (isset($_POST['action']) && $_POST['action'] == 'get_price') {
    $admin->get_price();
} else if (isset($_POST['action']) && $_POST['action'] == 'get_option_values') {
    $admin->get_option_values();
} else if (isset($_POST['action']) && $_POST['action'] == 'add_new_option') {
    $admin->add_new_option();
} else if (isset($_POST['action']) && $_POST['action'] == 'update_option_values') {
    $admin->update_option_values();
} else if (isset($_POST['action']) && $_POST['action'] == 'get_selected_option_values') {
    $admin->get_selected_option_values();
} else if (isset($_POST['action']) && $_POST['action'] == 'update_product_selected_option') {
    $admin->update_product_selected_option();
} else if (isset($_POST['action']) && $_POST['action'] == 'get_all_available_options') {
    $admin->get_all_available_options();
} else if (isset($_POST['action']) && $_POST['action'] == 'add_product_option') {
    $admin->add_product_option();
} else if (isset($_POST['action']) && $_POST['action'] == 'get_rule_details') {
    $admin->get_rule_details();
} else if (isset($_POST['action']) && $_POST['action'] == 'update_rule') {
    $admin->update_rule();
} else if (isset($_POST['action']) && $_POST['action'] == 'create_rule') {
    $admin->create_rule();
} else if (isset($_POST['action']) && $_POST['action'] == 'duplicate_rule') {
    $admin->duplicate_rule();
} else if (isset($_POST['action']) && $_POST['action'] == 'delete_rule') {
    $admin->delete_rule();
} else if (isset($_POST['action']) && $_POST['action'] == 'update_rule_order') {
    $admin->update_rule_order();
} else if (isset($_POST['action']) && $_POST['action'] == 'update_option_order') {
    $admin->update_option_order();
} else if (isset($_POST['action']) && $_POST['action'] == 'update_adjuster_status') {
    $admin->update_adjuster_status();
} else if (isset($_POST['action']) && $_POST['action'] == 'update_rule_value') {
    $admin->update_rule_value();
} else if (isset($_POST['action']) && $_POST['action'] == 'delete_option') {
    $admin->delete_option();
}
