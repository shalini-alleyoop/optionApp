<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/connect.php';
start_session_once();
require_https();
redirect_if_no_shopify_context();

$shop = $_GET['shop'] ?? ($_SESSION['shop'] ?? '');
if (!$shop) { http_response_code(401); exit('No shop context.'); }

function rules_redirect($shop, $shopifyId, $preview, $status, $message) {
    header('Location: singleproduct.php?' . http_build_query(['shop'=>$shop,'productId'=>$shopifyId,'previewurl'=>$preview,'currenttab'=>'prdRules','csv_status'=>$status,'csv_message'=>$message]));
    exit;
}
function import_error($admin, $inTransaction, $handle, $shop, $shopifyId, $preview, $message) {
    if (is_resource($handle)) fclose($handle);
    if ($inTransaction) $admin->query('ROLLBACK');
    rules_redirect($shop,$shopifyId,$preview,'error',$message);
}
function csv_bool($value, &$valid) {
    $value = strtolower(trim((string)$value));
    if (in_array($value,['1','true','yes','on'],true)) { $valid=true; return 'true'; }
    if (in_array($value,['0','false','no','off'],true)) { $valid=true; return 'false'; }
    $valid=false; return 'false';
}
function sql_value($admin, $value, $nullable=false) {
    if ($nullable && $value === '') return 'NULL';
    return "'" . $admin->escape((string)$value) . "'";
}

$shopifyId = trim((string)($_REQUEST['productId'] ?? ''));
if (!preg_match('/^[0-9]{1,30}$/', $shopifyId)) { http_response_code(400); exit('Invalid Shopify product ID.'); }
$safeShopifyId = $admin->escape($shopifyId);
$product = $admin->get_row("SELECT product_id,shopify_product_id,name FROM bg_products WHERE shopify_product_id='$safeShopifyId' LIMIT 1");
if (!$product) { http_response_code(404); exit('Product not found.'); }
$productId = (int)$product['product_id'];

$columns = ['id','sort_order','is_enabled','adjuster','adjuster_value','conditions_json'];

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'export_options') {
    $options=$admin->get_results("SELECT o.display_name AS option_name,ov.label AS option_value FROM bg_product_options po JOIN bg_options o ON o.option_id=po.option_id JOIN bg_option_values ov ON ov.option_id=o.option_id WHERE po.product_id='$productId' AND o.status='1' AND ov.status='1' AND (po.options_values IS NULL OR po.options_values='' OR FIND_IN_SET(ov.option_value_id,po.options_values)) ORDER BY po.sort_order ASC,ov.sort_order ASC,ov.option_value_id ASC");
    $filename=trim(preg_replace('/[^a-z0-9]+/i','-',$product['name']),'-');
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="product-options-'.$filename.'.csv"');
    header('X-Content-Type-Options: nosniff');
    $out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['option_name','option_value']);
    $seen=[];
    foreach($options as $option){
        $key=$option['option_name']."\0".$option['option_value'];
        if(isset($seen[$key]))continue;
        $seen[$key]=true;
        fputcsv($out,[$option['option_name'],$option['option_value']]);
    }
    fclose($out);exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'export') {
    $rules = $admin->get_results("SELECT * FROM bg_product_rules_extract WHERE product_id='$productId' ORDER BY sort_order ASC,id ASC");
    $filename = trim(preg_replace('/[^a-z0-9]+/i','-',$product['name']),'-');
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="pricing-rules-' . $filename . '.csv"');
    header('X-Content-Type-Options: nosniff');
    $out=fopen('php://output','wb'); fwrite($out,"\xEF\xBB\xBF"); fputcsv($out,$columns);
    foreach ($rules as $rule) {
        $conditions=json_decode($rule['conditions_json'],true) ?: []; $readableConditions=[];
        foreach ($conditions as $condition) {
            $productOptionId=(int)($condition['product_option_id']??0); $valueId=(int)($condition['option_value_id']??0);
            $label=$admin->get_row("SELECT o.display_name,v.label FROM bg_product_options po JOIN bg_options o ON o.option_id=po.option_id JOIN bg_option_values v ON v.option_id=o.option_id WHERE po.product_id='$productId' AND po.product_option_id='$productOptionId' AND v.option_value_id='$valueId' LIMIT 1");
            if(!$label){
                $optionId=(int)($condition['option_id']??0);
                $label=$admin->get_row("SELECT o.display_name,v.label FROM bg_product_options po JOIN bg_options o ON o.option_id=po.option_id JOIN bg_option_values v ON v.option_id=o.option_id WHERE po.product_id='$productId' AND o.option_id='$optionId' AND v.option_value_id='$valueId' LIMIT 1");
            }
            if($label){
                $optionName=(string)$label['display_name'];
                if(!isset($readableConditions[$optionName]))$readableConditions[$optionName]=[];
                if(!in_array($label['label'],$readableConditions[$optionName],true))$readableConditions[$optionName][]=$label['label'];
            }
        }
        fputcsv($out,[(int)$rule['id'],(int)$rule['sort_order'],$rule['is_enabled']??'false',$rule['adjuster']??'',$rule['adjuster_value']??'',json_encode($readableConditions,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    }
    fclose($out); exit;
}

if ($_SERVER['REQUEST_METHOD']!=='POST'){http_response_code(405);exit('Method not allowed.');}
$preview=(string)($_POST['previewurl']??'');
if(!hash_equals($_SESSION['rules_csv_csrf']??'',$_POST['csrf_token']??''))rules_redirect($shop,$shopifyId,$preview,'error','Invalid request token. Please try again.');
$action=(string)($_POST['action']??'import');
if($action==='undo'){
    $admin->query('START TRANSACTION');
    $backup=$admin->get_row("SELECT * FROM pricing_rule_import_backups WHERE product_id='$productId' AND shopify_product_id='$safeShopifyId' AND restored_at IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE");
    if(!$backup)import_error($admin,true,null,$shop,$shopifyId,$preview,'No import backup is available to undo.');
    $snapshot=json_decode($backup['rules_json'],true);
    if(!is_array($snapshot))import_error($admin,true,null,$shop,$shopifyId,$preview,'The import backup is invalid and cannot be restored.');
    $admin->get_results("SELECT id FROM bg_product_rules_extract WHERE product_id='$productId' FOR UPDATE");
    foreach($snapshot as $rule){
        $oldId=(int)($rule['id']??0);
        if(!$oldId)import_error($admin,true,null,$shop,$shopifyId,$preview,'The backup contains an invalid rule ID.');
        $conflict=$admin->get_row("SELECT product_id FROM bg_product_rules_extract WHERE id='$oldId' AND product_id<>'$productId' LIMIT 1");
        if($conflict)import_error($admin,true,null,$shop,$shopifyId,$preview,"Rule ID $oldId is now used by another product; undo was cancelled.");
    }
    $ok=$admin->query("DELETE FROM bg_product_rules_extract WHERE product_id='$productId'");
    foreach($snapshot as $rule){
        if(!$ok)break;
        $id=(int)$rule['id'];$sort=(int)($rule['sort_order']??0);
        $sql="INSERT INTO bg_product_rules_extract (id,rule_id,product_id,sort_order,is_enabled,is_stop,adjuster,adjuster_value,is_purchasing_disabled,purchasing_disabled_message,is_purchasing_hidden,image_file,conditions_json,raw) VALUES (".$id.",".sql_value($admin,$rule['rule_id']??'',true).",'$productId','$sort',".sql_value($admin,$rule['is_enabled']??'false').",".sql_value($admin,$rule['is_stop']??'false').",".sql_value($admin,$rule['adjuster']??'',true).",".sql_value($admin,$rule['adjuster_value']??'',true).",".sql_value($admin,$rule['is_purchasing_disabled']??'false').",".sql_value($admin,$rule['purchasing_disabled_message']??'').",".sql_value($admin,$rule['is_purchasing_hidden']??'false').",".sql_value($admin,$rule['image_file']??'',true).",".sql_value($admin,$rule['conditions_json']??'[]').",".sql_value($admin,$rule['raw']??'{}').")";
        $ok=$admin->query($sql);
    }
    if($ok)$ok=$admin->query("UPDATE pricing_rule_import_backups SET restored_at=NOW() WHERE id=".(int)$backup['id']." AND product_id='$productId'");
    $admin->query($ok?'COMMIT':'ROLLBACK');
    rules_redirect($shop,$shopifyId,$preview,$ok?'success':'error',$ok?'The last CSV import was undone and all previous rules were restored.':'Undo failed; current rules were preserved.');
}
if($action!=='import')rules_redirect($shop,$shopifyId,$preview,'error','Invalid CSV action.');
if(empty($_FILES['rules_csv'])||$_FILES['rules_csv']['error']!==UPLOAD_ERR_OK||!is_uploaded_file($_FILES['rules_csv']['tmp_name']))rules_redirect($shop,$shopifyId,$preview,'error','CSV upload failed.');
if($_FILES['rules_csv']['size']<1||$_FILES['rules_csv']['size']>2097152)rules_redirect($shop,$shopifyId,$preview,'error','CSV must be between 1 byte and 2 MB.');
if(strtolower(pathinfo($_FILES['rules_csv']['name'],PATHINFO_EXTENSION))!=='csv')rules_redirect($shop,$shopifyId,$preview,'error','Only CSV files are accepted.');

$handle=fopen($_FILES['rules_csv']['tmp_name'],'rb');
$firstLine=fgets($handle);
if($firstLine===false)import_error($admin,false,$handle,$shop,$shopifyId,$preview,'CSV is empty.');
$delimiter=substr_count($firstLine,"\t")>substr_count($firstLine,',')?"\t":',';
rewind($handle);
$header=fgetcsv($handle,0,$delimiter);
if($header&&isset($header[0]))$header[0]=preg_replace('/^\xEF\xBB\xBF/','',$header[0]);
if($header!==$columns)import_error($admin,false,$handle,$shop,$shopifyId,$preview,'Invalid columns. Export a fresh CSV from this product.');

$rows=[]; $seenIds=[]; $skippedNew=0; $line=1;
while(($csv=fgetcsv($handle,0,$delimiter))!==false){
    $line++; if(count($csv)===1&&trim((string)$csv[0])==='')continue;
    if(count($csv)!==count($columns))import_error($admin,false,$handle,$shop,$shopifyId,$preview,"Line $line has the wrong number of columns.");
    $row=array_combine($columns,$csv);
    $idText=trim($row['id']);
    if($idText==='')$idText='0';
    if(!ctype_digit($idText)||(int)$idText<0)import_error($admin,false,$handle,$shop,$shopifyId,$preview,"Line $line has an invalid ID. Use 0 or leave it empty for a new rule.");
    $id=(int)$idText;
    if($id>0&&isset($seenIds[$id]))import_error($admin,false,$handle,$shop,$shopifyId,$preview,"Rule ID $id appears more than once.");
    if($id>0)$seenIds[$id]=true;
    $newRequired=['sort_order','is_enabled','adjuster','adjuster_value','conditions_json'];
    $newMissing=false;
    if($id===0)foreach($newRequired as $requiredColumn)if(trim((string)$row[$requiredColumn])===''){$newMissing=true;break;}
    if($newMissing){$skippedNew++;continue;}
    if(!preg_match('/^-?[0-9]+$/',trim($row['sort_order'])))import_error($admin,false,$handle,$shop,$shopifyId,$preview,"Line $line has an invalid sort order.");
    $enabled=csv_bool($row['is_enabled'],$valid); if(!$valid)import_error($admin,false,$handle,$shop,$shopifyId,$preview,"Line $line has an invalid enabled value.");
    $adjuster=strtolower(trim($row['adjuster']));
    if(!in_array($adjuster,['relative','percentage','absolute'],true)||!is_numeric($row['adjuster_value'])||!is_finite((float)$row['adjuster_value']))import_error($admin,false,$handle,$shop,$shopifyId,$preview,"Line $line has an invalid adjustment.");
    $conditions=json_decode($row['conditions_json'],true);
    if(!is_array($conditions)||!$conditions)import_error($admin,false,$handle,$shop,$shopifyId,$preview,"Line $line has invalid or empty conditions JSON.");
    if(count($conditions)>2)import_error($admin,false,$handle,$shop,$shopifyId,$preview,"Line $line has more than two option groups.");
    $row['_line']=$line;$row['_id']=$id;$row['_enabled']=$enabled;$row['_adjuster']=$adjuster;$row['_conditions']=$conditions;
    $rows[]=$row; if(count($rows)>10000)import_error($admin,false,$handle,$shop,$shopifyId,$preview,'CSV contains too many rules.');
}
fclose($handle);
if(!$rows)rules_redirect($shop,$shopifyId,$preview,'error','CSV contains no pricing rules.');

$admin->query('START TRANSACTION'); $inTransaction=true;
$current=$admin->get_results("SELECT * FROM bg_product_rules_extract WHERE product_id='$productId' FOR UPDATE"); $currentById=[];
foreach($current as $rule)$currentById[(int)$rule['id']]=$rule;
foreach($seenIds as $id=>$unused)if(!isset($currentById[$id]))import_error($admin,true,null,$shop,$shopifyId,$preview,"Rule ID $id does not belong to this product or no longer exists.");

$normalizedRows=[];
foreach($rows as $index=>$row){
    $normalized=[];$seenValues=[];
    foreach($row['_conditions'] as $optionName=>$valueNames){
        $optionName=trim((string)$optionName);
        if($optionName===''||!is_array($valueNames)||!$valueNames)import_error($admin,true,null,$shop,$shopifyId,$preview,"Line {$row['_line']} has an invalid option group.");
        $safeOptionName=$admin->escape($optionName);
        $optionMatches=$admin->get_results("SELECT po.product_option_id,po.option_id FROM bg_product_options po JOIN bg_options o ON o.option_id=po.option_id WHERE po.product_id='$productId' AND o.display_name='$safeOptionName'");
        if(count($optionMatches)!==1)import_error($admin,true,null,$shop,$shopifyId,$preview,"Line {$row['_line']}: option '$optionName' is missing or ambiguous for this product.");
        $productOption=$optionMatches[0];
        foreach($valueNames as $valueName){
            if(!is_string($valueName)||trim($valueName)==='')import_error($admin,true,null,$shop,$shopifyId,$preview,"Line {$row['_line']} has an empty option value.");
            $valueName=trim($valueName);$safeValueName=$admin->escape($valueName);$optionId=(int)$productOption['option_id'];
            $valueMatches=$admin->get_results("SELECT option_value_id FROM bg_option_values WHERE option_id='$optionId' AND label='$safeValueName' AND status='1'");
            if(count($valueMatches)!==1)import_error($admin,true,null,$shop,$shopifyId,$preview,"Line {$row['_line']}: value '$valueName' for '$optionName' is missing or ambiguous.");
            $valueId=(int)$valueMatches[0]['option_value_id'];
            $assignment=$admin->get_row("SELECT product_option_id FROM bg_product_options WHERE product_id='$productId' AND product_option_id='".(int)$productOption['product_option_id']."' AND (options_values IS NULL OR options_values='' OR FIND_IN_SET('$valueId',options_values)) LIMIT 1");
            if(!$assignment)import_error($admin,true,null,$shop,$shopifyId,$preview,"Line {$row['_line']}: value '$valueName' is not enabled for this product.");
            if(isset($seenValues[$valueId]))import_error($admin,true,null,$shop,$shopifyId,$preview,"Line {$row['_line']} contains a duplicate option value.");
            $seenValues[$valueId]=true;
            $normalized[]=['product_option_id'=>$productOption['product_option_id'],'option_id'=>$optionId,'option_value_id'=>$valueId,'sku_id'=>null];
        }
    }
    $row['_json']=json_encode($normalized);$normalizedRows[]=$row;
}

$backupJson=$admin->escape(json_encode(array_values($current),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
$backupOk=$admin->query("INSERT INTO pricing_rule_import_backups (product_id,shopify_product_id,rules_json) VALUES ('$productId','$safeShopifyId','$backupJson')");
if(!$backupOk)import_error($admin,true,null,$shop,$shopifyId,$preview,'Import backup could not be created. Run the backup-table migration first.');

$keepIds=array_keys($seenIds);
$deleteSql="DELETE FROM bg_product_rules_extract WHERE product_id='$productId'";
if($keepIds)$deleteSql.=' AND id NOT IN ('.implode(',',array_map('intval',$keepIds)).')';
$ok=$admin->query($deleteSql);
foreach($normalizedRows as $row){
    if(!$ok)break;
    $fields="sort_order=".(int)$row['sort_order'].",is_enabled=".sql_value($admin,$row['_enabled']).",adjuster=".sql_value($admin,$row['_adjuster']).",adjuster_value=".sql_value($admin,$row['adjuster_value']).",conditions_json=".sql_value($admin,$row['_json']);
    if($row['_id']>0)$ok=$admin->query("UPDATE bg_product_rules_extract SET $fields WHERE id=".$row['_id']." AND product_id='$productId'");
    else $ok=$admin->query("INSERT INTO bg_product_rules_extract SET product_id='$productId',is_stop='false',is_purchasing_disabled='false',purchasing_disabled_message='',is_purchasing_hidden='false',raw='{}',$fields");
    if(!$ok)break;
}
$admin->query($ok?'COMMIT':'ROLLBACK');
$successMessage=count($normalizedRows).' rules synchronized; removed rules were deleted and ID 0/blank-ID rows were added.';
if($skippedNew)$successMessage.=' '.$skippedNew.' incomplete new row(s) were skipped.';
rules_redirect($shop,$shopifyId,$preview,$ok?'success':'error',$ok?$successMessage:'Import failed; the backup and existing rules were preserved.');
