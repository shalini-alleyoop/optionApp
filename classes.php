<?php
class Admin extends DB
{

    function get_shop_detail($shop_domain)
    {
        $stmt = $this->get_row("SELECT * FROM shops WHERE shop_domain = '" . $shop_domain . "' LIMIT 1");

        return $stmt;
    }

    function get_products_count($row)
    {
        $shop = $row['shop_domain'];
        $accessToken = $row['access_token'];
        $endpoint = "https://$shop/admin/api/2025-04/graphql.json";
        $query = <<<GRAPHQL
                query {
                    productsCount(limit: null) {
                        count
                        precision
                    }
                }
                GRAPHQL;

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "X-Shopify-Access-Token: $accessToken"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['query' => $query]));

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            echo "cURL error: " . curl_error($ch);
            exit;
        }
        curl_close($ch);

        $data = json_decode($response, true);

        if (isset($data['data']['productsCount'])) {
            return $data['data']['productsCount']['count'];
        } else {
            echo "Failed to fetch product count.";
        }
    }

    function get_products_by_page($row, $page = 1, $search = "")
    {
        $shop = $row['shop_domain'];
        $accessToken = $row['access_token'];
        $endpoint = "https://$shop/admin/api/2025-04/graphql.json";

        $first = 50;
        $afterCursor = null;

        // Base filter to exclude vendor:custom_product
        $queryFilter = ', query: "NOT vendor:\'custom_product\'"';

        if (!empty($search)) {
            $searchSafe = addslashes($search);
            // Combine search + exclude custom_product
            $queryFilter = ", query: \"$searchSafe AND NOT vendor:'custom_product'\"";
        }

        if ($page > 1 && empty($search)) {
            $hasNextPage = true;
            $cursor = null;
            $currentPage = 1;

            while ($hasNextPage && $currentPage < $page) {
                $after = $cursor ? ", after: \"$cursor\"" : "";
                $query = <<<GRAPHQL
            query {
              products(first: $first$after, query: "NOT vendor:'custom_product'") {
                pageInfo {
                    hasNextPage
                    endCursor
                    hasPreviousPage
                    startCursor
                }
                nodes {
                  id
                }
              }
            }
            GRAPHQL;

                $ch = curl_init($endpoint);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    "Content-Type: application/json",
                    "X-Shopify-Access-Token: $accessToken"
                ]);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["query" => $query]));

                $response = curl_exec($ch);
                if (curl_errno($ch)) {
                    echo "cURL error: " . curl_error($ch);
                    exit;
                }
                curl_close($ch);

                $data = json_decode($response, true);
                $pageInfo = $data["data"]["products"]["pageInfo"];
                $hasNextPage = $pageInfo["hasNextPage"];
                $cursor = $pageInfo["endCursor"];
                $currentPage++;
            }

            $afterCursor = $cursor;
        }

        $after = $afterCursor ? ", after: \"$afterCursor\"" : "";
        $query = <<<GRAPHQL
        query {
            products(first: $first$after$queryFilter) {
                pageInfo {
                    hasNextPage
                    endCursor
                    hasPreviousPage
                    startCursor
                }
                nodes {
                    id
                    title
                    handle
                    vendor
                    createdAt
                    updatedAt
                    publishedAt
                    onlineStorePreviewUrl
                    media(first: 1) {
                        nodes {
                            preview {
                                image {
                                    url
                                }
                            }
                        }
                    }
                }
            }
        }
    GRAPHQL;

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "X-Shopify-Access-Token: $accessToken"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["query" => $query]));

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            echo "cURL error: " . curl_error($ch);
            exit;
        }
        curl_close($ch);

        return json_decode($response, true) ?? [];
    }

    function get_options_by_page($page = 1, $search = "")
    {
        $limit = 20; // 20 per page
        $offset = ($page - 1) * $limit;

        // Escape search value to avoid SQL injection
        $search = $this->escape($search);

        // Get paginated results
        $sql = "SELECT * 
				FROM `bg_options` 
				WHERE `status`='1'";
        if (!empty($search)) {
            $sql .= " AND `display_name` LIKE '%$search%'";
        }
        $total = $this->num_rows($sql);
        $sql .= " ORDER BY `display_name` ASC 
				  LIMIT $limit OFFSET $offset";

        $rows = $this->get_results($sql);

        return [
            "success" => true,
            "data"    => $rows,
            "total"   => $total,
            "page"    => $page,
            "per_page" => $limit,
            "pages"   => ceil($total / $limit)
        ];
    }

    function get_option_values()
    {
        $optionId = (int)$_POST['option_id'];
        $row = $this->get_row("SELECT * FROM bg_options WHERE option_id='$optionId'");

        if ($row) {
            $optionValues = $this->get_results("SELECT * FROM bg_option_values WHERE option_id='$optionId' and status  = '1' ");
            echo json_encode([
                "success" => true,
                "data" => [
                    "option" => $row,
                    "values" => $optionValues
                ]
            ]);
        } else {
            echo json_encode(["success" => false]);
        }
    }

    function get_selected_option_values()
    {
        $optionId = (int)$_POST['option_id'];
        $productId = (int)$_POST['productId'];
        $row = $this->get_row("SELECT * FROM bg_options WHERE option_id='$optionId'");
        $prow = $this->get_row("SELECT * FROM bg_product_options WHERE product_id='$productId' AND option_id='$optionId'");
        $selectedoptionids = $prow['options_values'];
        $selected_options = [];
        if (!empty($selectedoptionids)) {
            $selected_options = explode(',', $selectedoptionids);
        }
        if ($row) {
            $optionValues = $this->get_results("SELECT * FROM bg_option_values WHERE option_id='$optionId' and status  = '1' ");
            $i = 0;
            foreach ($optionValues as $option) {
                if (in_array($option['option_value_id'], $selected_options)) {
                    $optionValues[$i]['is_selected'] = "1";
                } else if (empty($selectedoptionids)) {
                    $optionValues[$i]['is_selected'] = "1";
                }
                $i++;
            }
            echo json_encode([
                "success" => true,
                "data" => [
                    "option" => $row,
                    "values" => $optionValues
                ]
            ]);
        } else {
            echo json_encode(["success" => false]);
        }
    }

    function update_product_selected_option()
    {
        $optionId = (int)$_POST['option_id'];
        $productId = (int)$_POST['product_id'];
        $option_values = !empty($_POST['option_values']) ? implode(',', $_POST['option_values']) : '';
        $prow = $this->query("UPDATE bg_product_options SET options_values = '" . $option_values . "' WHERE product_id='$productId' AND option_id = '$optionId' ");

        echo json_encode(["success" => true]);
    }

    function update_option_values()
    {
        $option_id = $_POST["option_id"];
        $option_name = $_POST["option_name"];
        if (isset($_POST['engraving'])) {
            $this->query("Update bg_options SET `engraving` = 'Yes' WHERE option_id='$option_id'");
        } else {
            $this->query("Update bg_options SET `engraving` = 'No' WHERE option_id='$option_id'");
        }
        $this->query("Update bg_options SET `display_name` = '{$option_name}' WHERE option_id='$option_id'");
        $this->query("Update bg_option_values SET `status` = '0' WHERE option_id='$option_id'");
        $sort_order = 0;
        if (!empty($_POST['existingvalues'])) {
            foreach ($_POST['existingvalues'] as $option_value_id => $label) {
                $filepath = '';
                if (isset($_FILES['fileupload']) && $_FILES['fileupload']['error'][$option_value_id] == 0) {
                    $fileTmpPath = $_FILES['fileupload']['tmp_name'][$option_value_id];
                    $fileName = $_FILES['fileupload']['name'][$option_value_id];
                    $fileSize = $_FILES['fileupload']['size'][$option_value_id];
                    $fileType = $_FILES['fileupload']['type'][$option_value_id];
                    $fileNameCmps = explode(".", $fileName);
                    $fileExtension = strtolower(end($fileNameCmps));
                    $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'webp'];

                    if (in_array($fileExtension, $allowedfileExtensions)) {
                        $uploadFileDir = 'images/';
                        $newFileName = uniqid() . '.' . $fileExtension;
                        $dest_path = $uploadFileDir . $newFileName;
                        if (move_uploaded_file($fileTmpPath, $dest_path)) {
                            $filepath = htmlspecialchars($newFileName);
                            $this->query("Update bg_option_values SET `image` = '$filepath' WHERE option_id='$option_id' AND option_value_id = '$option_value_id'");
                        }
                    }
                }
                $this->query("Update bg_option_values SET `status` = '1',`label` = '$label',`value` = '$label', `sort_order` = '$sort_order' WHERE option_id='$option_id' AND option_value_id = '$option_value_id'");
                $sort_order++;
            }
        }
        if (!empty($_POST['values'])) {
            foreach ($_POST['values'] as $key => $label) {
                $filepath = '';

                if (isset($_FILES['fileuploadnew']) && $_FILES['fileuploadnew']['error'][$key] == 0) {
                    $fileTmpPath = $_FILES['fileuploadnew']['tmp_name'][$key];
                    $fileName = $_FILES['fileuploadnew']['name'][$key];
                    $fileSize = $_FILES['fileuploadnew']['size'][$key];
                    $fileType = $_FILES['fileuploadnew']['type'][$key];
                    $fileNameCmps = explode(".", $fileName);
                    $fileExtension = strtolower(end($fileNameCmps));
                    $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'webp'];

                    if (in_array($fileExtension, $allowedfileExtensions)) {
                        $uploadFileDir = 'images/';
                        $newFileName = uniqid() . '.' . $fileExtension;
                        $dest_path = $uploadFileDir . $newFileName;
                        if (move_uploaded_file($fileTmpPath, $dest_path)) {
                            $filepath = htmlspecialchars($newFileName);
                        }
                    }
                }
                $this->query("INSERT INTO bg_option_values SET `status` = '1',`label` = '$label',`value` = '$label', `option_id`='$option_id', `image` = '$filepath', `sort_order` = '$sort_order' ");
                $sort_order++;
            }
        }
        echo json_encode([
            "success" => true
        ]);
    }

    function add_new_option()
    {
        $new_option_name = $_POST["new_option_name"];
        $optiontype = $_POST["optiontype"];
        $row = $this->get_row("SELECT * FROM bg_options WHERE display_name='$new_option_name' AND status = '1'");
        if (!empty($row)) {
            echo json_encode([
                "success" => false,
                "msg" => "This name option already exist. Please use another name."
            ]);
            die;
        }
        $this->query("Insert INTO bg_options SET `display_name` = '{$new_option_name}',`type` = '{$optiontype}',`status` = '1'");

        echo json_encode([
            "success" => true
        ]);
    }

    function get_options()
    {
        $response = [
            "success" => false,
            "message" => "Invalid request",
            "form" => null,
            "product_price" => 0,
            "options_count" => 0,
        ];
        $productId = $_POST["productId"];
        if (!empty($productId)) {
            $product = $this->get_row("SELECT * FROM `bg_products` WHERE `shopify_product_id`='" . ($productId) . "'");
            $productPrice = $product["price"];
            if ($product) {
                $data = [];
                $data["product"] = $product;
                $options = $this->get_results("SELECT * FROM `bg_product_options` WHERE `product_id`='" . $data["product"]["product_id"] . "' ORDER BY `sort_order` ASC");
                $data["options"] = $options;

                $html = "";
                $html .= "<div class='custom-product-options' data-productprice='{$data["product"]["price"]}' data-productid='{$data["product"]["product_id"]}' data-customproductoptions>";
                if (is_array($options) && !empty($options)) {
                    $engraving = true;
                    foreach ($options as $option) {
                        $main_option = $this->get_row("SELECT * FROM `bg_options` WHERE `option_id`='" . $option["option_id"] . "'");
                        $display_name = $main_option["display_name"];
                        $product_option_id = $option["product_option_id"];
                        $option_id = $main_option["option_id"];
                        $option_type = $main_option['type'];
                        $option_engraving = $main_option['engraving'];
                        $option_required = "";
                        if ($option['required']) {
                            $option_required = "required";
                        }
                        $name = $display_name;

                        $html .= "<div class='option-values-wp' style='margin-bottom:15px;'>";
                        $html .= "<legend><strong>{$display_name}" . (!empty($option['required']) ? " <span style='color:red;'>*</span>" : "") . ":</strong></legend>";


                        $selectedoptionids = $option['options_values'];

                        if (!empty($selectedoptionids)) {
                            $optionValues = $this->get_results("SELECT * FROM `bg_option_values` WHERE `option_id`='" . $option_id . "' AND FIND_IN_SET(`option_value_id`, '$selectedoptionids') ORDER BY `sort_order` ASC");
                        } else {
                            $optionValues = $this->get_results("SELECT * FROM `bg_option_values` WHERE `option_id`='" . $option_id . "' ORDER BY `sort_order` ASC");
                        }

                        if ($option_type === "RT") {
                            if (is_array($optionValues) && !empty($optionValues)) {
                                foreach ($optionValues as $vIndex => $value) {
                                    $val = $value["value"];
                                    $option_value_id = $value["option_value_id"];
                                    $id = $name . "_" . strtolower(str_replace(" ", "_", $val)) . $vIndex;
                                    $imageHtml = '';

                                    if (!empty($value["image"])) {
                                        $image = "https://apps.royalhawaiianheritage.com/images/" . $value["image"];
                                        $imageHtml = "<img src='$image'/>";
                                    }

                                    if ($value['is_default'] == 1) {
                                        $html .= "<input type='radio' $option_required data-productoptionid='$product_option_id' data-optionvalueid='$option_value_id' checked id='$id' name='properties[$name]' value='$val'>";
                                    } else {
                                        $html .= "<input type='radio' $option_required data-productoptionid='$product_option_id' data-optionvalueid='$option_value_id' id='$id' name='properties[$name]' value='$val'>";
                                    }

                                    $html .= "<label for='$id'>$imageHtml<span>$val</span></label>";
                                }
                            }
                        } elseif ($option_type === "S") {
                            if (is_array($optionValues) && !empty($optionValues)) {
                                $selectId = strtolower(preg_replace('/[^a-z0-9_]/i', '_', $name)) . '_select_' . rand(1000, 9999);
                                $customSelectId = $selectId . '_custom';

                                $html .= "<div class='custom-select-wrap'>";

                                // Hidden real <select>
                                $html .= "<select id='$selectId' name='properties[{$name}]' class='custom-select-hidden' $option_required>";
                                $html .= "<option value='' selected disabled>-- Select --</option>";

                                foreach ($optionValues as $value) {
                                    $val = htmlspecialchars($value["value"], ENT_QUOTES);
                                    $option_value_id = htmlspecialchars($value["option_value_id"], ENT_QUOTES);
                                    $dataImgAttr = '';

                                    if (!empty($value["image"])) {
                                        $image = "https://apps.royalhawaiianheritage.com/images/" . $value["image"];
                                        $dataImgAttr = " data-img='" . htmlspecialchars($image, ENT_QUOTES) . "'";
                                    }

                                    $selectedAttr = ($value['is_default'] == 1) ? ' selected' : '';
                                    $html .= "<option{$selectedAttr} data-productoptionid='$product_option_id' data-optionvalueid='$option_value_id'{$dataImgAttr} value='{$val}'>{$val}</option>";
                                }

                                $html .= "</select>";

                                // Custom dropdown HTML
                                $html .= "<div class='custom-select' id='$customSelectId'>";
                                $html .= "  <div class='selected'>-- Select --</div>";
                                $html .= "  <div class='options'>";

                                foreach ($optionValues as $value) {
                                    $val = htmlspecialchars($value["value"], ENT_QUOTES);
                                    $imageHtml = '';
                                    if (!empty($value["image"])) {
                                        $image = "https://apps.royalhawaiianheritage.com/images/" . $value["image"];
                                        $imageHtml = "<img src='$image' alt=''>";
                                    }
                                    $defaultClass = ($value['is_default'] == 1) ? ' active' : '';
                                    $html .= "<div class='option{$defaultClass}' data-value='{$val}'>$imageHtml<span>{$val}</span></div>";
                                }

                                $html .= "  </div></div></div>";
                            }
                        } elseif ($option["type"] === "T") {
                            $html .= "<input $option_required type='text' data-productoptionid='$product_option_id' name='properties[{$name}]' placeholder='Enter $display_name'>";
                            if ($option_engraving == "Yes" && $engraving === true) {
                                $html .= "<button type='button' class='engraving_preview_button'>Preview Engraving</button><span class='characters_remaining'></span>";
                                $engraving = false;
                            }
                        }

                        $html .= "</div>";
                    }
                }
                $html .= "</div>";
                $response = [
                    "success" => true,
                    "message" => "Product found",
                    // "data" => $data,
                    "form" => $html,
                    "product_price" => $productPrice,
                    "options_count" => is_array($options) ? count($options) : 0,
                ];
            } else {
                $response = [
                    "success" => false,
                    "message" => "Product not found",
                ];
            }
        }

        echo json_encode($response);
        exit();
    }

    function get_price()
    {
        $response = [
            "success" => false,
            "message" => "Invalid request",
            "product_price" => 0,
            "raw_price" => 0
        ];
        if (isset($_POST["product_id"], $_POST["product_options"])) {
            $product_id = (int)$_POST["product_id"];
            $product_options = $_POST["product_options"];
            $prdprice = $_POST["product_price"];

            $product_prices = $this->get_results("CALL GetMatchingRules('$product_id', '$product_options')");


            $product_price = $this->calculatePrice($product_prices, $prdprice);
            $product_price_formated = number_format($product_price, 2);


            $response = [
                "success" => true,
                "message" => "Product Price Found",
                "product_price" => $product_price_formated,
                "raw_price" => $product_price
            ];
            echo json_encode($response);
        } else {
            $response = [
                "success" => false,
                "message" => "Product Price Not Found",
            ];
        }
        exit();
    }

    function calculatePrice($rules, $prdprice)
    {
        $basePrice = null;

        foreach ($rules as $rule) {
            if (isset($rule['adjuster']) && $rule['adjuster'] === "absolute" && $basePrice === null) {
                $basePrice = (float)$rule['adjuster_value'];
            }
        }

        if ($basePrice === null) {
            $basePrice = (float)$prdprice;
        }

        // Step 1: Apply all RELATIVE (dollar add-ons) FIRST to get subtotal
        // Add-ons (e.g. engraving +$50) must be calculated before percentage discounts
        $subtotal = $basePrice;
        foreach ($rules as $rule) {
            if (isset($rule['adjuster']) && $rule['adjuster'] === "relative") {
                $subtotal += (float)$rule['adjuster_value'];
            }
        }

        // Step 2: Apply all PERCENTAGE adjustments to the subtotal (base + add-ons)
        // E.g. 10% gold discount applies to (base + add-ons), not base only
        $finalPrice = $subtotal;
        foreach ($rules as $rule) {
            if (isset($rule['adjuster']) && $rule['adjuster'] === "percentage") {
                $finalPrice += $subtotal * ((float)$rule['adjuster_value'] / 100);
            }
        }

        return round($finalPrice);
    }

    function get_all_available_options()
    {
        $response = ["success" => false, "html" => ''];
        $productId = $_POST["productId"];
        if (!empty($productId)) {
            $product = $this->get_row("SELECT * FROM `bg_products` WHERE `shopify_product_id`='" . ($productId) . "'");
            if ($product) {
                $options = $this->get_results("SELECT * FROM bg_options WHERE status='1' AND option_id NOT IN (SELECT option_id FROM `bg_product_options` WHERE `product_id`='" . ($product['product_id']) . "') ORDER BY display_name ASC");

                if (!empty($options)) {
                    $typearray = ['RT' => 'Radio', 'S' => 'Select', 'T' => 'Text'];
                    $html = "<div class='custom-product-options' data-productid='{$product["product_id"]}'><select class='option-group' data-productid='$productId' name='productoption'>";

                    foreach ($options as $option) {
                        $rawData = json_decode($option['raw'], true);
                        $rawName = $rawData['name'] ?? '';
                        $display_name = $option['display_name'];
                        $title = ($display_name !== $rawName && $rawName !== '') ? "$display_name ($rawName)" : $display_name;
                        $typeLabel = $typearray[$option['type']] ?? $option['type'];
                        $html .= "<option value='{$option['option_id']}'>$title ($typeLabel)</option>";
                    }

                    $html .= "</select></div>";
                    $response = ["success" => true, "html" => $html];
                }
            }
        }

        echo json_encode($response);
        die;
    }

    function add_product_option()
    {
        $productId = $_POST["product_id"];
        $productoption = $_POST["productoption"];
        $is_required = !empty($_POST["is_required"]) ? 1 : 0;
        if (!empty($productId)) {
            $product = $this->get_row("SELECT * FROM `bg_products` WHERE `shopify_product_id`='" . ($productId) . "'");
            if ($product) {
                $productoptions = $this->get_row("SELECT max(sort_order) as maxsort FROM `bg_product_options` WHERE `product_id`='" . ($product['product_id']) . "' LIMIT 1 ");
                $sort_order = $productoptions['maxsort'] + 1;

                $productoptionidss = $this->get_row("SELECT max(product_option_id) as next_id FROM `bg_product_options` ");
                $next_id = $productoptionidss['next_id'] + 1;

                $options = $this->query("INSERT INTO `bg_product_options` SET `product_id`='" . ($product['product_id']) . "', `product_option_id` = '" . $next_id . "', `option_id` = '" . $productoption . "', sort_order = '$sort_order', required = '$is_required' ");

                $response = ["success" => true];
            } else {
                $response = ["success" => false,];
            }
        } else {
            $response = ["success" => false,];
        }

        echo json_encode($response);
        die;
    }

    function get_product_options($productId)
    {
        if (isset($productId)) {
            $product = $this->get_row("SELECT * FROM `bg_products` WHERE `shopify_product_id`='" . $productId . "'");
            if ($product) {
                $options = $this->get_results("SELECT * FROM `bg_product_options` WHERE `product_id`='" . $product["product_id"] . "' ORDER BY `sort_order` ASC");
                $html = "";
                $html .= "<div class='custom-product-options' data-productprice='{$product["price"]}' data-productid='{$product["product_id"]}'>";
                $typearray = ['RT' => 'Radio', 'S' => 'Select', 'T' => 'Text'];
                if (is_array($options) && !empty($options)) {
                    foreach ($options as $index => $option) {
                        $main_option = $this->get_row("SELECT * FROM `bg_options` WHERE `option_id`='" . $option["option_id"] . "'");
                        $display_name = $main_option["display_name"];
                        $product_option_id = $main_option["product_option_id"];
                        $option_id = $main_option["option_id"];
                        $name = $display_name;

                        $html .= "<div class='option-group' data-optionid='$option_id'>";
                        $html .= "<div class='option-header'>";
                        $html .= "<h3>$display_name ({$typearray[$main_option['type']]})</h3>";
                        $html .= "<div class='custom-product-button'><button type='button' class='remove-option' data-optionid='" . $option['option_id'] . "' data-productid='" . $option['product_id'] . "'>Remove Option</button>";
                        if ($main_option['type'] != 'T') {
                            $html .= "<button type='button' class='edit-option' data-optionid='" . $option['option_id'] . "' data-productid='" . $option['product_id'] . "'>Edit Option</button>";
                        }
                        $html .= "</div>";
                        $html .= "</div>";

                        $selectedoptionids = $option['options_values'];

                        if (!empty($selectedoptionids)) {
                            $optionValues = $this->get_results("SELECT * FROM `bg_option_values` WHERE `option_id`='" . $option_id . "' AND FIND_IN_SET(`option_value_id`, '$selectedoptionids') ORDER BY `sort_order` ASC");
                        } else {
                            $optionValues = $this->get_results("SELECT * FROM `bg_option_values` WHERE `option_id`='" . $option_id . "' ORDER BY `sort_order` ASC");
                        }

                        if (is_array($optionValues) && !empty($optionValues)) {
                            foreach ($optionValues as $vIndex => $value) {
                                $val = $value["value"];
                                $option_value_id = $value["option_value_id"];
                                $checked = $value['is_default'] == 1 ? "checked" : "";

                                $html .= "<div class='productoption'>";
                                $html .= "<p>$val</p>";
                                $html .= "</div>";
                            }
                        }

                        $html .= "</div>";
                    }
                }

                $html .= "<button type='button' class='add-option' data-productid='" . $productId . "'>+ Add Option</button>";
                $html .= "</div>";
                return $html;
            }
            return null;
        }
        return null;
    }

    function product_creation($rawData)
    {
        $product = json_decode($rawData, true);
        $shopify_product_id = $product['id'];
        $name = $product['title'];
        $price = $product['variants'][0]['price'];
        $slug = '/' . $product['handle'] . '/';
        $this->query("INSERT INTO bg_products SET `shopify_product_id` = '$shopify_product_id',`name` = '$name',`price` = '$price',`slug` = '$slug'");
    }
    function get_product_rules($productId)
    {
        if (!isset($productId)) {
            return null;
        }

        $product = $this->get_row("SELECT * FROM `bg_products` WHERE `shopify_product_id`='" . $productId . "'");
        if (!$product) {
            return null;
        }

        $rules = $this->get_results("SELECT * FROM `bg_product_rules_extract` WHERE `product_id`='" . $product["product_id"] . "' ORDER BY `sort_order` ASC");
        $options = $this->get_results("SELECT * FROM `bg_product_options` WHERE `product_id`='" . $product["product_id"] . "' ORDER BY `sort_order` ASC");
        $values = $this->get_results("SELECT * FROM `bg_option_values`");


        $optionMap = [];
        foreach ($options as $opt) {
            $opdisplayname = $this->get_row("SELECT * FROM `bg_options` WHERE `option_id`='" . $opt["option_id"] . "' LIMIT 1");
            if (isset($opdisplayname) && !empty($opdisplayname)) {
                $opt['display_name'] = $opdisplayname['display_name'];
            }
            $optionMap[$opt['product_option_id']] = $opt['display_name'];
        }

        $valueMap = [];
        foreach ($values as $val) {
            $valueMap[$val['option_value_id']] = $val['label'];
        }

        $html = "<div class='rules-spreadsheet-wrap'><table class='rules-spreadsheet-table'><thead><tr><th>Conditions</th><th>Type</th><th class='rules-price-col'>Price</th><th>On</th><th></th></tr></thead><tbody>";

        foreach ($rules as $ruleRow) {
            $rule = json_decode($ruleRow['conditions_json'], true);
            $conditions = [];
            $conditionLabels = [];

            foreach ($rule as $cond) {
                $displayName = $optionMap[$cond['product_option_id']] ?? 'Unknown';
                $label = $valueMap[$cond['option_value_id']] ?? 'Unknown';
                $adjuster_enabled = $ruleRow['is_enabled'];

                if (!isset($conditions[$displayName])) {
                    $conditions[$displayName] = [];
                }
                $conditions[$displayName][] = [
                    'label' => $label,
                    'product_option_id' => $cond['product_option_id'],
                    'option_value_id' => $cond['option_value_id']
                ];
            }

            foreach ($conditions as $name => $vals) {
                $lbls = array_map(function ($v) { return htmlspecialchars($v['label']); }, $vals);
                $conditionLabels[] = "<strong>{$name}:</strong> " . implode(', ', $lbls);
            }
            $conditionsText = implode(' · ', $conditionLabels);

            $adjusterType = $ruleRow['adjuster'] ?? 'relative';
            $value = $ruleRow['adjuster_value'] ?? 0;
            $typeLabel = 'Add $';
            if ($adjusterType === 'absolute') {
                $typeLabel = 'Fixed $';
            } elseif ($adjusterType === 'percentage') {
                $typeLabel = ($value >= 0) ? 'Add %' : 'Remove %';
            } elseif ($value < 0) {
                $typeLabel = 'Remove $';
            }

            $html .= "<tr class='rule-block rule-spreadsheet-row' data-rule-id='{$ruleRow['id']}' data-product-id='{$product['product_id']}'>";
            $html .= "<td class='rule-conditions-cell'>" . $conditionsText . "</td>";
            $html .= "<td class='rule-type-cell'>" . htmlspecialchars($typeLabel) . "</td>";
            $html .= "<td class='rule-price-cell'><input type='text' class='rule-price-input' data-rule-id='{$ruleRow['id']}' data-product-id='{$product['product_id']}' value='" . htmlspecialchars($value) . "' tabindex='0' placeholder='0'></td>";
            $html .= "<td class='rule-enabled-cell'><input type='checkbox' name='adjuster_enabled' value='{$ruleRow['is_enabled']}' " . ($ruleRow['is_enabled'] ? "checked" : "") . " onclick='updateAdjusterRule(this);' data-rule-id='{$ruleRow['id']}' data-product-id='{$product['product_id']}'></td>";
            $html .= "<td class='rule-actions-cell'><div class='menu-wrapper'><button type='button' class='menu-icon'>⋮</button><div class='menu-popup'><button type='button' class='menu-item edit-btn'>Edit</button><button type='button' class='menu-item'>Duplicate</button><button type='button' class='menu-item'>Delete</button></div></div></td>";
            $html .= "</tr>";
        }

        $html .= "</tbody></table></div>";

        return [
            'product' => $product,
            'html'    => $html
        ];
    }

    function update_product_tags($shopRow, $pageInfo = null)
    {
        $shop = $shopRow['shop_domain'];
        $accessToken = $shopRow['access_token'];
        $endpointBase = "https://$shop/admin/api/2025-04";

        echo "<pre>";
        $allCategories = [];
        $cats = $this->get_results("SELECT * FROM `bg_categories`");
        foreach ($cats as $c) {
            $allCategories[$c['category_id']] = $c['name'];
        }

        $limit = 100;

        // FETCH ONE PAGE
        $url = "$endpointBase/products.json?limit=$limit";
        if ($pageInfo) {
            $url .= "&page_info=" . urlencode($pageInfo);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "X-Shopify-Access-Token: $accessToken"
        ]);
        $response = curl_exec($ch);

        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);
        curl_close($ch);

        $data = json_decode($body, true);
        if (!isset($data['products'])) {
            echo "Error fetching products: " . $body . "\n";
            return;
        }

        echo "Processing " . count($data['products']) . " products...\n";

        $productsToUpdate = [];

        foreach ($data['products'] as $product) {
            $handle = "/" . $product['handle'] . "/";
            $row = $this->get_row("SELECT * FROM `big_products` WHERE `custom_url` LIKE '%" . $this->escape($handle) . "%'");
            if (!$row) continue;

            $categories = json_decode($row['categories'], true);
            if (!is_array($categories) || empty($categories)) continue;

            $tagNames = [];
            foreach ($categories as $catId) {
                if (isset($allCategories[$catId])) {
                    $tagNames[] = $allCategories[$catId];
                }
            }
            if (empty($tagNames)) continue;

            $existingTags = $product['tags'] ? explode(', ', $product['tags']) : [];
            $newTags = array_unique(array_merge($existingTags, $tagNames));
            $tagsString = implode(', ', $newTags);

            $productsToUpdate[] = [
                "id" => $product['id'],
                "tags" => $tagsString,
                "title" => $product['title'],
                "handle" => $product['handle']
            ];
        }

        // UPDATE PRODUCTS FROM THIS PAGE
        foreach ($productsToUpdate as $prod) {
            $updatePayload = [
                "product" => [
                    "id" => $prod['id'],
                    "tags" => $prod['tags']
                ]
            ];

            $updateUrl = "$endpointBase/products/{$prod['id']}.json";

            $ch = curl_init($updateUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "X-Shopify-Access-Token: $accessToken"
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($updatePayload));
            $updateResponse = curl_exec($ch);
            curl_close($ch);

            echo "Updated product {$prod['id']} ({$prod['handle']}) with tags: {$prod['tags']}\n";
        }

        if (preg_match('/<([^>]+)>; rel="next"/', $header, $matches)) {
            $nextLink = $matches[1];
            parse_str(parse_url($nextLink, PHP_URL_QUERY), $queryParams);
            if (isset($queryParams['page_info'])) {
                echo "Next page_info: " . $queryParams['page_info'] . "\n";
            }
        } else {
            echo "No more pages.\n";
        }
    }
    public function update_options()
    {
        header('Content-Type: application/json');


        if (empty($_POST['data'])) {
            echo json_encode(["success" => false, "message" => "No data provided"]);
            exit;
        }

        $payload = json_decode($_POST['data'], true);
        if (empty($payload['changes'])) {
            echo json_encode(["success" => false, "message" => "No changes found"]);
            exit;
        }

        $result = [];

        foreach ($payload['changes'] as $change) {
            $action = $change['action'] ?? null;
            $option_id = $change['option_id'] ?? null;
            $product_id = isset($change['product_id']) ? (int)$change['product_id'] : 0;

            // --- OPTION HANDLING ---
            if ($action === 'remove_option' && $option_id && is_numeric($option_id)) {
                $this->query("DELETE FROM `bg_product_options` WHERE `option_id` = '$option_id' AND `product_id` = '$product_id' LIMIT 1 ");
                return ["success" => true];
            }
        }
        return ["success" => true, "result" => $result];
    }

    function delete_product_by_variant_id($row, $variantId)
    {
        $shop = $row['shop_domain'];
        $accessToken = $row['access_token'];
        $endpoint = "https://$shop/admin/api/2025-04/graphql.json";

        if (strpos($variantId, 'gid://shopify/ProductVariant/') === false) {
            $variantId = "gid://shopify/ProductVariant/" . $variantId;
        }

        $query = <<<GRAPHQL
            query {
            productVariant(id: "$variantId") {
                id
                product {
                    id
                    title
                }
            }
            }
            GRAPHQL;

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "X-Shopify-Access-Token: $accessToken"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["query" => $query]));

        $response = curl_exec($ch);
        if (curl_errno($ch)) {
            return ["error" => "cURL error: " . curl_error($ch)];
        }
        curl_close($ch);

        $data = json_decode($response, true);
        if (empty($data["data"]["productVariant"]["product"]["id"])) {
            return ["error" => "No product found for this variant ID"];
        }

        $productId = $data["data"]["productVariant"]["product"]["id"];

        $mutation = <<<GRAPHQL
            mutation {
                productDelete(input: {id: "$productId"}) {
                    deletedProductId
                    userErrors {
                        field
                        message
                    }
                }
            }
        GRAPHQL;

        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: application/json",
            "X-Shopify-Access-Token: $accessToken"
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["query" => $mutation]));

        $deleteResponse = curl_exec($ch);
        if (curl_errno($ch)) {
            return ["error" => "cURL error: " . curl_error($ch)];
        }
        curl_close($ch);

        return json_decode($deleteResponse, true) ?? [];
    }
    function create_orders($row)
    {
        echo "<pre>";
        $shop = $row['shop_domain'];
        $accessToken = $row['access_token'];

        $orders = $this->get_results("SELECT * FROM bigcommerce_orders WHERE `order_imported` = '' AND `status` IN ('Completed','Shipped') ORDER BY `date_created` ASC LIMIT 100");

        foreach ($orders as $order) {
            $orderId = $order['id'];

            if (empty($orderId)) {
                echo "Skipping order with missing ID.\n";
                continue;
            }

            $mysqlDate = $order['date_created'];
            $dt = new DateTime($mysqlDate, new DateTimeZone("UTC"));
            $shopifyDate = $dt->format(DateTime::ATOM);

            $billing_address = [
                "first_name" => $order['billing_first_name'] ?? '',
                "last_name"  => $order['billing_last_name'] ?? '',
                "address1"   => trim(($order['billing_street_1'] ?? '') . " " . ($order['billing_street_2'] ?? '')),
                "phone"      => $order['billing_phone'] ?? '',
                "city"       => $order['billing_city'] ?? '',
                "province"   => $order['billing_state'] ?? '',
                "country"    => $order['billing_country'] ?? '',
                "zip"        => $order['billing_zip'] ?? ''
            ];

            $customer = [
                "first_name" => $order['billing_first_name'] ?? '',
                "last_name"  => $order['billing_last_name'] ?? '',
                "email"      => $order['billing_email'] ?? ''
            ];

            if (empty($customer['email'])) {
                echo "Skipping order $orderId due to missing customer email.\n";
                continue;
            }

            $order_line_items = $this->get_results("SELECT * FROM `bigcommerce_order_items` WHERE `order_id`='$orderId'");
            if (empty($order_line_items)) {
                echo "Skipping order $orderId due to missing line items.\n";
                continue;
            }

            $order_shipping_address = $this->get_row("SELECT * FROM bigcommerce_shipping_addresses WHERE `order_id`='$orderId' LIMIT 1");

            if ($order_shipping_address && !empty($order_shipping_address['json_data'])) {
                $order_shipping_address = json_decode($order_shipping_address['json_data'], true);

                $shipping_address = [
                    "first_name" => $order_shipping_address['first_name'] ?? '',
                    "last_name"  => $order_shipping_address['last_name'] ?? '',
                    "address1"   => trim(($order_shipping_address['street_1'] ?? '') . " " . ($order_shipping_address['street_2'] ?? '')),
                    "phone"      => $order_shipping_address['phone'] ?? '',
                    "city"       => $order_shipping_address['city'] ?? '',
                    "province"   => $order_shipping_address['state'] ?? '',
                    "country"    => $order_shipping_address['country'] ?? '',
                    "zip"        => $order_shipping_address['zip'] ?? ''
                ];
            } else {
                // Shipping address missing → use blank values
                $shipping_address = [
                    "first_name" => '',
                    "last_name"  => '',
                    "address1"   => '',
                    "phone"      => '',
                    "city"       => '',
                    "province"   => '',
                    "country"    => '',
                    "zip"        => ''
                ];
            }

            $line_items = [];
            foreach ($order_line_items as $line_item) {
                $productId = $line_item['product_id'];
                if (!$productId) continue;

                $shopify_product = $this->get_row("SELECT * FROM shopify_products WHERE `bg_id`='$productId' LIMIT 1");

                if ($shopify_product && !empty($shopify_product['default_variant_id'])) {
                    $line_items[] = [
                        "variant_id" => $shopify_product['default_variant_id'],
                        "quantity"   => (int)($line_item["quantity"] ?? 1),
                        "price"      => $line_item["total_inc_tax"] ?? 0
                    ];
                }
            }

            if (empty($line_items)) {
                echo "Skipping order $orderId because no valid Shopify line items were found.\n";
                continue;
            }

            $payload = [
                "order" => [
                    "line_items"         => $line_items,
                    "customer"           => $customer,
                    "billing_address"    => $billing_address,
                    "shipping_address"   => $shipping_address,
                    "email"              => $customer['email'],
                    "financial_status"   => "paid",
                    "fulfillment_status" => "fulfilled",
                    "created_at"         => $shopifyDate
                ]
            ];

            $jsonData = json_encode($payload);
            $ch = curl_init("https://$shop/admin/api/2025-04/orders.json");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Content-Type: application/json",
                "X-Shopify-Access-Token: $accessToken"
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);

            $response  = curl_exec($ch);
            $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            echo "Response for Order $orderId (HTTP $httpCode):\n";

            if ($curlError) {
                echo "cURL Error: $curlError\n\n";
            } elseif ($httpCode >= 400) {
                echo "Shopify API Error (HTTP $httpCode):\n$response\n\n";
            } else {
                $decoded = json_decode($response, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    echo "Invalid JSON Response:\n$response\n\n";
                } else {
                    echo "Success:\n";
                    print_r($decoded);
                    $this->query("UPDATE bigcommerce_orders SET order_imported = 'yes' WHERE id = '$orderId'");
                }
            }
        }
    }

    function get_rule_details()
    {
        global $admin;

        $rule_id = isset($_POST['rule_id']) ? intval($_POST['rule_id']) : 0;
        $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;

        if (!$product_id) {
            echo json_encode(['success' => false, 'message' => 'Missing product id']);
            exit;
        }

        $ruleRow = null;
        $selectedOptionValueIds = [];
        $adjuster_type = 'relative';
        $adjuster_value = '';
        $adjuster_direction = 1;

        if ($rule_id) {
            $ruleRow = $admin->get_row("SELECT * FROM bg_product_rules_extract WHERE id='" . intval($rule_id) . "' AND product_id='" . intval($product_id) . "'");
            if ($ruleRow) {
                $conditions_json = json_decode($ruleRow['conditions_json'], true);
                if (is_array($conditions_json)) {
                    foreach ($conditions_json as $cond) {
                        if (!empty($cond['option_value_id'])) {
                            $selectedOptionValueIds[] = intval($cond['option_value_id']);
                        }
                    }
                }
                $adjuster_type = $ruleRow['adjuster'] ?? 'relative';
                $adjuster_value = $ruleRow['adjuster_value'] ?? '';
                if ($adjuster_type === 'absolute') {
                    $adjuster_direction = 0;
                } else {
                    $val = floatval($adjuster_value);
                    $adjuster_direction = ($val < 0) ? -1 : 1;
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Rule not found']);
                exit;
            }
        } else {
            $selectedOptionValueIds = [];
            $adjuster_type = 'relative';
            $adjuster_value = '';
            $adjuster_direction = 1;
        }

        $product = $admin->get_row("SELECT * FROM bg_products WHERE product_id='" . intval($product_id) . "'");
        if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            exit;
        }

        $options = $admin->get_results("SELECT * FROM bg_product_options WHERE product_id='" . intval($product["product_id"]) . "' ORDER BY sort_order ASC");

        $html = "<div class='custom-product-options' data-productprice='{$product["price"]}' data-productid='{$product["product_id"]}'>";
        $typearray = ['RT' => 'Radio', 'S' => 'Select', 'T' => 'Text'];

        if (!empty($options)) {
            foreach ($options as $optionRow) {
                $main_option = $admin->get_row("SELECT * FROM bg_options WHERE option_id='" . intval($optionRow["option_id"]) . "' AND `type` != 'T' ");
                if (!$main_option) continue;

                $display_name = $main_option["display_name"];
                $product_option_row_id = $optionRow['id'] ?? $optionRow['product_option_id'] ?? 0;

                $html .= "<div class='option-group' data-optionid='{$product_option_row_id}'>";
                $html .= "<div class='option-header'><h3>" . htmlspecialchars($display_name) . " (" . ($typearray[$main_option['type']] ?? 'Option') . ")</h3></div>";

                $selectedoptionids = $optionRow['options_values'] ?? '';
                $query = "SELECT * FROM bg_option_values WHERE option_id='" . intval($main_option['option_id']) . "'";
                if (!empty($selectedoptionids)) {
                    $query .= " AND FIND_IN_SET(option_value_id, '" . addslashes($selectedoptionids) . "')";
                }
                $query .= " ORDER BY sort_order ASC";

                $optionValues = $admin->get_results($query);
                if (!empty($optionValues)) {
                    foreach ($optionValues as $value) {
                        $val = htmlspecialchars($value["label"] ?? $value["value"] ?? '');
                        $option_value_id = intval($value["option_value_id"]);
                        $checked = in_array($option_value_id, $selectedOptionValueIds) ? "checked" : "";

                        $html .= "<div class='productoption'>";
                        $html .= "<label style='display:flex;align-items:center;gap:6px;'>";
                        // name must be condition_values[] (JS expects that)
                        $html .= "<input type='checkbox' name='condition_values[]' value='$option_value_id' data-optionid='{$product_option_row_id}' $checked> $val";
                        $html .= "</label>";
                        $html .= "</div>";
                    }
                }
                $html .= "</div>";
            }
        }

        $html .= "</div>";

        echo json_encode([
            'success' => true,
            'html' => $html,
            'adjuster_type' => $adjuster_type,
            'adjuster_value' => $adjuster_value,
            'adjuster_direction' => $adjuster_direction
        ]);
        exit;
    }

    //
    // ----------------- CREATE RULE -----------------
    //
    function create_rule()
    {
        global $admin;

        $product_id = intval($_POST['product_id'] ?? 0);
        if (!$product_id) {
            echo json_encode(['success' => false, 'message' => 'Missing product id']);
            exit;
        }

        $adjuster_value_raw = $_POST['adjuster_value'] ?? '0';
        $price_adjuster = $_POST['price_adjuster'] ?? 'relative';
        $direction = $_POST['price_adjustment_direction'] ?? '1';
        $condition_values_json = $_POST['condition_values_json'] ?? '[]';
        $condition_values = json_decode($condition_values_json, true);

        $adjuster_value_num = floatval(str_replace(',', '.', $adjuster_value_raw));
        if ($direction === '-1') {
            $adjuster_value_num = -abs($adjuster_value_num);
        } else {
            $adjuster_value_num = abs($adjuster_value_num);
        }

        if ($direction === '0' && $price_adjuster === 'relative') {
            $price_adjuster = 'absolute';
        }

        $conditions = [];
        if (is_array($condition_values)) {
            foreach ($condition_values as $item) {
                $valId = intval($item['option_value_id'] ?? 0);
                $optionRowId = intval($item['option_id'] ?? 0);
                if (!$valId) continue;

                $opt = $admin->get_row("SELECT option_id FROM bg_option_values WHERE option_value_id='$valId'");
                if ($opt) {
                    /*$product_option_id = '';
					if ($optionRowId) {
						$optionsetid = $admin->get_row("SELECT * FROM bg_product_options WHERE option_id='$optionRowId' AND product_id = '$product_id'");
						if (isset($optionsetid) && !empty($optionsetid['product_option_id'])) {
							$product_option_id = $optionsetid['product_option_id'];
						}
					}*/
                    $product_option_id = '';
                    if ($optionRowId) {
                        $optionsetid = $admin->get_row("SELECT product_option_id FROM bg_product_options WHERE id='$optionRowId'");
                        if ($optionsetid && !empty($optionsetid['product_option_id'])) {
                            $product_option_id = $optionsetid['product_option_id'];
                        }
                    }

                    $conditions[] = [
                        'product_option_id' => $product_option_id,
                        'option_id' => $opt['option_id'],
                        'option_value_id' => $valId,
                        'sku_id' => null
                    ];
                }
            }
        }


        $json = json_encode($conditions);

        $jsonEscaped = addslashes($json);
        $price_adjuster_escaped = addslashes($price_adjuster);
        $adjuster_value_escaped = addslashes($adjuster_value_num);

        /* $productoptions = $this->get_row("SELECT max(sort_order) as maxsort FROM `bg_product_rules_extract` WHERE `product_id`='" . ($product_id) . "' LIMIT 1 ");
		$sort_order = $productoptions['maxsort'] + 1; */
        $sort_order = -1;

        $query = "
			INSERT INTO bg_product_rules_extract 
			(product_id, sort_order, is_enabled, is_stop, is_purchasing_disabled, is_purchasing_hidden, conditions_json, adjuster, adjuster_value)
			VALUES (
				'" . intval($product_id) . "',
				'" . intval($sort_order) . "',
				'true',
				'false',
				'false',
				'false',
				'" . $jsonEscaped . "',
				'" . $price_adjuster_escaped . "',
				'" . $adjuster_value_escaped . "'
			)
		";

        $res = $admin->query($query);

        $new_rule_id = 0;
        if ($res) {
            $row = $admin->get_row("SELECT id FROM bg_product_rules_extract WHERE product_id='" . intval($product_id) . "' ORDER BY id DESC LIMIT 1");
            if ($row && isset($row['id'])) $new_rule_id = intval($row['id']);
        }

        echo json_encode(['success' => $res ? true : false, 'rule_id' => $new_rule_id]);
        exit;
    }

    //
    // ----------------- UPDATE RULE -----------------
    //
    function update_rule()
    {
        global $admin;

        $rule_id = intval($_POST['rule_id'] ?? 0);
        $product_id = intval($_POST['product_id'] ?? 0);
        if (!$rule_id || !$product_id) {
            echo json_encode(['success' => false, 'message' => 'Missing ids']);
            exit;
        }

        $adjuster_value_raw = $_POST['adjuster_value'] ?? '0';
        $price_adjuster = $_POST['price_adjuster'] ?? 'relative';
        $direction = $_POST['price_adjustment_direction'] ?? '1';
        $condition_values_json = $_POST['condition_values_json'] ?? '[]';
        $condition_values = json_decode($condition_values_json, true);

        $adjuster_value_num = floatval(str_replace(',', '.', $adjuster_value_raw));
        if ($direction === '-1') {
            $adjuster_value_num = -abs($adjuster_value_num);
        } else {
            $adjuster_value_num = abs($adjuster_value_num);
        }

        if ($direction === '0' && $price_adjuster === 'relative') {
            $price_adjuster = 'absolute';
        }

        $conditions = [];
        if (is_array($condition_values)) {
            foreach ($condition_values as $item) {
                $valId = intval($item['option_value_id'] ?? 0);
                $optionRowId = intval($item['option_id'] ?? 0);
                if (!$valId) continue;

                $opt = $admin->get_row("SELECT option_id FROM bg_option_values WHERE option_value_id='$valId'");
                if ($opt) {
                    $product_option_id = '';
                    if ($optionRowId) {
                        $optionsetid = $admin->get_row("SELECT product_option_id FROM bg_product_options WHERE id='$optionRowId'");
                        if ($optionsetid && !empty($optionsetid['product_option_id'])) {
                            $product_option_id = $optionsetid['product_option_id'];
                        }
                    }

                    $conditions[] = [
                        'product_option_id' => $product_option_id,
                        'option_id' => $opt['option_id'],
                        'option_value_id' => $valId,
                        'sku_id' => null
                    ];
                }
            }
        }

        $json = addslashes(json_encode($conditions));
        $query = "UPDATE bg_product_rules_extract SET 
			adjuster = '" . addslashes($price_adjuster) . "',
			adjuster_value = '" . addslashes($adjuster_value_num) . "',
			conditions_json = '" . $json . "'
			WHERE id='" . intval($rule_id) . "' AND product_id='" . intval($product_id) . "'";
        $res = $admin->query($query);

        echo json_encode(['success' => $res ? true : false]);
        exit;
    }

    //
    // ----------------- UPDATE RULE Status -----------------
    //
    function update_adjuster_status()
    {
        global $admin;

        $rule_id = intval($_POST['rule_id'] ?? 0);
        $product_id = intval($_POST['product_id'] ?? 0);
        $status = $_POST['status'] ?? 'true';
        if (!$rule_id || !$product_id) {
            echo json_encode(['success' => false, 'message' => 'Missing ids']);
            exit;
        }
        $query = "UPDATE bg_product_rules_extract SET 
			is_enabled = '" . $status . "'
			WHERE id='" . intval($rule_id) . "' AND product_id='" . intval($product_id) . "'";
        $res = $admin->query($query);

        echo json_encode(['success' => $res ? true : false]);
        exit;
    }

    //
    // ----------------- UPDATE RULE VALUE (quick inline edit for bulk editing) -----------------
    //
    function update_rule_value()
    {
        global $admin;

        $rule_id = intval($_POST['rule_id'] ?? 0);
        $product_id = intval($_POST['product_id'] ?? 0);
        $adjuster_value_raw = $_POST['adjuster_value'] ?? '';
        if (!$rule_id || !$product_id || $adjuster_value_raw === '') {
            echo json_encode(['success' => false, 'message' => 'Missing required fields']);
            exit;
        }
        $adjuster_value_num = floatval(str_replace([',', ' ', '$', '%'], ['.', '', '', ''], $adjuster_value_raw));
        $adjuster_value_escaped = addslashes($adjuster_value_num);

        $query = "UPDATE bg_product_rules_extract SET adjuster_value = '" . $adjuster_value_escaped . "' WHERE id='" . intval($rule_id) . "' AND product_id='" . intval($product_id) . "'";
        $res = $admin->query($query);

        echo json_encode(['success' => $res ? true : false]);
        exit;
    }

    //
    // ----------------- DUPLICATE RULE -----------------
    //
    function duplicate_rule()
    {
        global $admin;

        $rule_id = intval($_POST['rule_id'] ?? 0);
        $product_id = intval($_POST['product_id'] ?? 0);
        if (!$rule_id || !$product_id) {
            echo json_encode(['success' => false, 'message' => 'Missing ids']);
            exit;
        }

        $rule = $admin->get_row("SELECT * FROM bg_product_rules_extract WHERE id='" . intval($rule_id) . "' AND product_id='" . intval($product_id) . "'");
        if (!$rule) {
            echo json_encode(['success' => false, 'message' => 'Rule not found']);
            exit;
        }

        $conditions_json = addslashes($rule['conditions_json'] ?? '[]');
        $adj = addslashes($rule['adjuster'] ?? 'relative');
        $adjVal = addslashes($rule['adjuster_value'] ?? 0);

        /*$query = "INSERT INTO bg_product_rules_extract (product_id, conditions_json, adjuster, adjuster_value) VALUES ('" . intval($product_id) . "', '" . $conditions_json . "', '" . $adj . "', '" . $adjVal . "')";*/

        $query = "
			INSERT INTO bg_product_rules_extract 
			(product_id, is_enabled, is_stop, is_purchasing_disabled, is_purchasing_hidden, conditions_json, adjuster, adjuster_value)
			VALUES (
				'" . intval($product_id) . "',
				'true',
				'false',
				'false',
				'false',
				'" . $conditions_json . "',
				'" . $adj . "',
				'" . $adjVal . "'
			)
		";

        $res = $admin->query($query);

        echo json_encode(['success' => $res ? true : false]);
        exit;
    }

    //
    // ----------------- DELETE RULE -----------------
    //
    function delete_rule()
    {
        global $admin;

        $rule_id = intval($_POST['rule_id'] ?? 0);
        $product_id = intval($_POST['product_id'] ?? 0);
        if (!$rule_id || !$product_id) {
            echo json_encode(['success' => false, 'message' => 'Missing ids']);
            exit;
        }

        $res = $admin->query("DELETE FROM bg_product_rules_extract WHERE id='" . intval($rule_id) . "' AND product_id='" . intval($product_id) . "'");
        echo json_encode(['success' => $res ? true : false]);
        exit;
    }

    //
    // ----------------- Sort RULE -----------------
    //
    function update_rule_order()
    {
        global $admin;

        $rule_order = $_POST['rule_order'];
        $product_id = intval($_POST['productId'] ?? 0);
        if (empty($rule_order) || !$product_id) {
            echo json_encode(['success' => false, 'message' => 'Missing ids']);
            exit;
        }
        $sort_order = 0;
        foreach ($rule_order as $rule_id) {
            $res = $admin->query("UPDATE bg_product_rules_extract set `sort_order` = '$sort_order' WHERE id='" . intval($rule_id) . "' AND product_id='" . intval($product_id) . "'");
            $sort_order++;
        }

        echo json_encode(['success' => $res ? true : false]);
        exit;
    }
}
