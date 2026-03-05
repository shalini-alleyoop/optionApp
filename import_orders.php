<?php
header('Access-Control-Allow-Origin: *');
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, X-Requested-With");
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/connect.php';
start_session_once();
require_https();
$shop     = $_GET['shop'];
if (!$shop) {
    http_response_code(401);
    exit('No shop context.');
}

$getdata = $admin->get_shop_detail($shop);
$access_token = $getdata['access_token'];
$domain = $getdata['shop_domain'];

$ordersdata = $admin->create_orders($getdata);

