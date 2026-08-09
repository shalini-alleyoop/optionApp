<?php
require_once __DIR__ . '/option_inventory.php';
header('Content-Type: application/json; charset=utf-8');
$allowed=['https://qheqg4-bu.myshopify.com','https://royalhawaiianheritagejewelry.com','https://www.royalhawaiianheritagejewelry.com'];
$origin=$_SERVER['HTTP_ORIGIN']??''; if(in_array($origin,$allowed,true)) header('Access-Control-Allow-Origin: '.$origin);
header('Vary: Origin'); header('Access-Control-Allow-Methods: POST, OPTIONS'); header('Access-Control-Allow-Headers: Content-Type');
if($_SERVER['REQUEST_METHOD']==='OPTIONS'){http_response_code(204);exit;}
if($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);echo json_encode(['success'=>false,'checkout_allowed'=>false,'errors'=>[['message'=>'POST required.']]]);exit;}
try{$input=json_decode(file_get_contents('php://input'),true);if(!is_array($input))throw new InvalidArgumentException('Invalid request.');$shop=normalize_shop_domain((string)($input['shop']??''));if($shop!==SHOPIFY_ALLOWED_SHOP)throw new RuntimeException('Unknown shop.');$lines=$input['lines']??[];if(!is_array($lines)||count($lines)>250)throw new InvalidArgumentException('Invalid cart lines.');echo json_encode(option_inventory_validate_cart($shop,$lines,!empty($input['availability_check'])));}catch(Throwable $e){http_response_code(422);echo json_encode(['success'=>false,'checkout_allowed'=>false,'errors'=>[['message'=>$e->getMessage()]]]);}
