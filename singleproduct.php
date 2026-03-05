<?php
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/connect.php';
start_session_once();
require_https();

$hw_token = $_GET['hw_token'] ?? '';
$productId = $_GET['productId'] ?? '';
$previewurl = $_GET['previewurl'] ?? '';
$decoded  = $hw_token ? json_decode(base64_decode($hw_token), true) : [];
$shop_myshopifyDomain = $decoded['myshopifyDomain'] ?? ($_SESSION['shop'] ?? null);
$shop_domain          = $_GET['shop'] ?? null;
$shop = $shop_myshopifyDomain ?? $shop_domain;

if (!$shop && !$productId) {
    http_response_code(401);
    exit('No shop or product context.');
}
$row = $admin->get_shop_detail($shop);
// $update_product_tags = $admin->update_product_tags($row);
$singleproduct = '';
if ($row) {
    $shop_domain = $row['shop_domain'];
    $product_options = $admin->get_product_options($productId);
    $product_rules = $admin->get_product_rules($productId);
	$singleproductrow = $admin->get_row("SELECT * FROM bg_products WHERE shopify_product_id='" . intval($productId) . "'");
	if ($singleproductrow) {
		$singleproduct = $singleproductrow['product_id'];
	}
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <title><?= $product_rules['product']['name'] ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
	
	<style>
		.nav-bar {
		display: flex;
		align-items: center;
		justify-content: space-between;
		background: transparent;
		border-bottom: none;
		padding: 0;
		margin-bottom: 20px;
		border-radius: 6px;
		box-shadow: none;
		}

		.back-button {
			text-decoration: none;
			background: crimson;
			color: #fff;
			padding: 8px 14px;
			border-radius: 4px;
			font-weight: 400;
			transition: background 0.3s ease;
			background: #000 !important;
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
	<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
	<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
</head>

<body>
    <div class="nav-bar">
		<a href="<?= "dashboard.php?shop=" . urlencode($shop) ?>" class="back-button">← Back</a>
	</div>
	<div id="pageLoader" style="display:none;">Loading...</div>
    <div class="dashboard-wrapper-header">
        <h2 class="dashboard-title"><?= $product_rules['product']['name'] ?></h2>
        <a class="btn install-btn" target="_blank" href="<?= $previewurl ?>">View Product</a>
    </div>
    <div class="single-prd-options-wrapper">
        <?php if ($row): ?>
            <div class="prdtabs">
                <button class="prdtabs-trigger active" data-id="prdOptions">Options</button>
                <button class="prdtabs-trigger" data-id="prdRules">Rules</button>
            </div>
            <div class="prdtabs-content prd-options" id="prdOptions">
                <?= $product_options ?>
				<!-- Edit Option Modal -->
				<!-- Modal -->
				<div id="updateOptionModal" class="modal-overlay" style="display:none;">
				  <div class="modal">
					<h2>Edit Option</h2>
					<form id="updateOptionForm">
					  <input type="hidden" name="action" value="update_product_selected_option">
					  <input type="hidden" name="option_id">
					  <input type="hidden" name="product_id">
					  <div id="optionValuesWrapper"></div>
					  <div style="margin-top:20px; text-align:right;">
						<button type="submit">Save</button>
						<button type="button" class="close-modal">Close</button>
					  </div>
					</form>
				  </div>
				</div>
				<!-- Add New Option Modal -->
				<!-- Modal -->
				<div id="addOptionModal" class="modal-overlay" style="display:none;">
				  <div class="modal">
					<h2>Add Option</h2>
					<form id="addOptionForm">
					  <input type="hidden" name="action" value="add_product_option">
					  <input type="hidden" name="product_id">
					  <div id="addOptionValuesWrapper"></div>
					  <div style="margin-top:20px;text-align: right;">
						<button type="submit">Save</button>
						<button type="button" class="close-amodal">Close</button>
					  </div>
					</form>
				  </div>
				</div>
            </div>
            <div class="prdtabs-content prd-rules hidden" id="prdRules">
				<div class="prd-rules-header">
					<button class="add-rule-btn" data-product-id="<?= $singleproduct ?>">+ Add New Rule</button>
				</div>
                <?= $product_rules["html"] ?>
            </div>
        <?php else: ?>
            <div class="shop-row status-not-connected">Status: Not Connected ❌</div>
            <div class="shop-row">
                <a class="btn install-btn" href="install.php?shop=<?= urlencode($shop) ?>">Install App</a>
            </div>
        <?php endif; ?>
    </div>
	
	<!-- Edit Rule Modal -->
	<div id="editRuleModal" class="modal-overlay" style="display:none;">
	  <div class="modal" style="max-width:650px;">
		<h2 id="ruleModalTitle">Edit Rule</h2>
		<form id="editRuleForm">
		  <!--input type="hidden" name="action" value="update_rule"-->
		  <input type="hidden" name="rule_id">
		  <input type="hidden" name="product_id">

		  <div id="ruleConditionsWrapper" style="max-height:300px;overflow-y:auto;margin-bottom:15px;"></div>

		  <hr>
		  <h3>Make These Changes</h3>
		  <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
			<label>Adjust its Price:</label>
			<select name="price_adjustment_direction" id="rule-change-price-adjuster-direction" class="field-smaller">
			  <option value="1">Add</option>
			  <option value="-1">Remove</option>
			  <option value="0">Fixed</option>
			</select>

			<input type="text" name="adjuster_value" id="rule-change-price-adjuster-value" class="field-smaller" placeholder="Enter value">

			<select name="price_adjuster" id="rule-change-price-adjuster-type" class="field-smaller">
			  <option value="relative">$ (Price)</option>
			  <option value="percentage">% (Percent)</option>
			</select>
		  </div>

		  <div style="margin-top:20px;text-align:right;">
			<button type="submit" style="background:#007bff;color:#fff;padding:8px 14px;border:none;border-radius:4px;">Save</button>
			<button type="button" class="close-edit-rule-modal" style="background:#ccc;padding:8px 14px;border:none;border-radius:4px;">Cancel</button>
		  </div>
		</form>
	  </div>
	</div>

	<!-- Page loader (optional - your CSS/spinner may differ) -->
	<div id="pageLoader" style="display:none;position:fixed;inset:0;background:rgba(255,255,255,0.7);z-index:9999;align-items:center;justify-content:center;">
	  <div class="spinner" style="width:40px;height:40px;border-radius:50%;border:4px solid #ccc;border-top-color:#007bff;animation:spin 1s linear infinite;"></div>
	</div>
	<style>
	@keyframes spin { to { transform: rotate(360deg); } }
	.menu-popup { display:inline-block; margin-left:10px; }
	.menu-popup .menu-item {
		margin-right: 0;
		padding: 4px 8px;
		cursor: pointer;
		background: #fff;
		border-radius: 4px;
		border: none;
		font-weight: 500;
		font-size: 14px;
	}
	.menu-popup .menu-item:hover {
		background: #f5f5f5;
	}
	</style>
	<script>
		document.addEventListener("DOMContentLoaded", () => {
		  const editRuleModal = document.getElementById("editRuleModal");
		  const editRuleForm = document.getElementById("editRuleForm");
		  const ruleConditionsWrapper = document.getElementById("ruleConditionsWrapper");
		  const pageLoader = document.getElementById("pageLoader");
		  const ruleModalTitle = document.getElementById("ruleModalTitle");
		  const priceDirSelect = document.getElementById("rule-change-price-adjuster-direction");
		  const priceAdjusterSelect = document.getElementById("rule-change-price-adjuster-type");

		  function showToast(message, timeout = 2000) {
			let existing = document.getElementById("hw-toast-message");
			if (existing) {
			  existing.remove();
			}
			const t = document.createElement("div");
			t.id = "hw-toast-message";
			t.style.position = "fixed";
			t.style.top = "20px";
			t.style.right = "20px";
			t.style.background = "#333";
			t.style.color = "#fff";
			t.style.padding = "10px 14px";
			t.style.borderRadius = "6px";
			t.style.boxShadow = "0 4px 12px rgba(0,0,0,0.15)";
			t.style.zIndex = 100001;
			t.style.fontSize = "13px";
			t.textContent = message;
			document.body.appendChild(t);
			setTimeout(() => {
			  t.style.transition = "opacity 250ms";
			  t.style.opacity = "0";
			  setTimeout(() => t.remove(), 260);
			}, timeout);
		  }
		  window.showToast = showToast;

		  function togglePriceAdjusterOptions() {
			if (!priceAdjusterSelect) return;
			const dir = priceDirSelect ? priceDirSelect.value : null;
			const percentageOption = priceAdjusterSelect.querySelector('option[value="percentage"]');

			if (dir === "0") {
			  if (percentageOption) percentageOption.style.display = "none";
			  priceAdjusterSelect.value = "relative";
			} else {
			  if (percentageOption) percentageOption.style.display = "";
			}
		  }
		  if (priceDirSelect) priceDirSelect.addEventListener("change", togglePriceAdjusterOptions);

		  function handleConditionChange(e) {
			if (!e.target || e.target.name !== "condition_values[]") return;

			if (!e.target.checked) return;

			const targetGroup = e.target.getAttribute("data-optionid");

			const checkedInputs = Array.from(ruleConditionsWrapper.querySelectorAll("input[name='condition_values[]']:checked"));
			const groupsBefore = new Set();
			checkedInputs.forEach(ch => {
			  if (ch === e.target) return; 
			  const gid = ch.getAttribute("data-optionid");
			  if (gid !== null) groupsBefore.add(gid);
			});

			if (groupsBefore.has(targetGroup)) {
			  return; 
			}

			/* if (groupsBefore.size >= 2) {
			  e.target.checked = false;
			  showToast("Only two option combinations can be selected.");
			  return;
			} */

		  }

		  ruleConditionsWrapper.addEventListener("change", handleConditionChange);

		  function openRuleModalFromResponse(data, ruleId = 0, productId = 0) {
			ruleConditionsWrapper.innerHTML = data.html || "";
			editRuleForm.querySelector("input[name='rule_id']").value = ruleId ? ruleId : "";
			editRuleForm.querySelector("input[name='product_id']").value = productId;

			ruleModalTitle.textContent = ruleId && parseInt(ruleId) > 0 ? "Edit Rule" : "Add New Rule";

			let adjType = data.adjuster_type ?? "relative";
			if (adjType === "absolute") adjType = "relative";

			const direction = String(data.adjuster_direction ?? "1");

			if (priceAdjusterSelect) priceAdjusterSelect.value = adjType;
			if (priceDirSelect) priceDirSelect.value = direction;

			togglePriceAdjusterOptions();

			const valueInput = editRuleForm.querySelector("input[name='adjuster_value']");
			if (valueInput) {
			  valueInput.value = (data.adjuster_value !== null && data.adjuster_value !== undefined)
				? Math.abs(data.adjuster_value)
				: "";
			}

			(function validateInitialGroupCount() {
			  const checkedInputs = Array.from(ruleConditionsWrapper.querySelectorAll("input[name='condition_values[]']:checked"));
			  const groups = new Set(checkedInputs.map(ch => ch.getAttribute("data-optionid")));
			 /*  if (groups.size > 2) {
				showToast("Warning: more than two option groups are selected. You cannot add a 3rd group until you reduce to two.");
			  } */
			})();

			editRuleModal.style.display = "flex";
		  }

		  // --- open Edit modal ---
		  document.addEventListener("click", function (e) {
			if (e.target.classList.contains("edit-btn")) {
			  const ruleBlock = e.target.closest(".rule-block");
			  const ruleId = ruleBlock ? ruleBlock.dataset.ruleId : 0;
			  const productId = ruleBlock ? ruleBlock.dataset.productId : 0;

			  pageLoader.style.display = "flex";
			  fetch("process.php?domain=<?= $shop_domain ?>", {
				method: "POST",
				headers: { "Content-Type": "application/x-www-form-urlencoded" },
				body: new URLSearchParams({ action: "get_rule_details", rule_id: ruleId, product_id: productId })
			  })
				.then(res => res.json())
				.then(data => {
				  pageLoader.style.display = "none";
				  if (!data.success) return alert("Failed to load rule details");
				  openRuleModalFromResponse(data, ruleId, productId);
				})
				.catch(() => {
				  pageLoader.style.display = "none";
				  alert("Error loading rule");
				});
			}
		  });

		  // --- open Add New Rule modal ---
		  document.addEventListener("click", function (e) {
			if (e.target.classList.contains("add-rule-btn")) {
			  const productId = e.target.dataset.productId || 0;
			  pageLoader.style.display = "flex";
			  fetch("process.php?domain=<?= $shop_domain ?>", {
				method: "POST",
				headers: { "Content-Type": "application/x-www-form-urlencoded" },
				body: new URLSearchParams({ action: "get_rule_details", product_id: productId })
			  })
				.then(res => res.json())
				.then(data => {
				  pageLoader.style.display = "none";
				  if (!data.success) return alert("Failed to load product options");
				  openRuleModalFromResponse(data, 0, productId);
				})
				.catch(() => {
				  pageLoader.style.display = "none";
				  alert("Error loading product options");
				});
			}
		  });

		  // --- Close Modal ---
		  document.addEventListener("click", function (e) {
			if (e.target.classList.contains("close-edit-rule-modal")) {
			  editRuleModal.style.display = "none";
			  editRuleForm.reset();
			  ruleConditionsWrapper.innerHTML = "";
			  const t = document.getElementById("hw-toast-message");
			  if (t) t.remove();
			}
		  });

		  editRuleForm.addEventListener("submit", function (e) {
			e.preventDefault();
			pageLoader.style.display = "flex";

			const formData = new FormData(editRuleForm);

			const conditionValues = [];
			ruleConditionsWrapper.querySelectorAll("input[name='condition_values[]']:checked").forEach((el) => {
			  const optionValueId = el.value;
			  const optionId = el.getAttribute("data-optionid"); // product_option row id
			  conditionValues.push({ option_value_id: optionValueId, option_id: optionId });
			});
			formData.append("condition_values_json", JSON.stringify(conditionValues));

			// decide action
			const ruleId = editRuleForm.querySelector("input[name='rule_id']").value;
			const action = (ruleId && parseInt(ruleId) > 0) ? "update_rule" : "create_rule";
			formData.append("action", action);

			fetch("process.php?domain=<?= $shop_domain ?>", {
			  method: "POST",
			  body: formData
			})
			  .then((res) => res.json())
			  .then((data) => {
				pageLoader.style.display = "none";
				if (data.success) {
				  const msg = action === "create_rule" ? "Rule created successfully!" : "Rule updated successfully!";
				  alert(msg);
				  editRuleModal.style.display = "none";
				  location.reload();
				} else {
				  alert(data.message || "Failed to save rule.");
				}
			  })
			  .catch(() => {
				pageLoader.style.display = "none";
				alert("Error saving rule.");
			  });
		  });

		  // --- Duplicate ---
		  document.addEventListener("click", function (e) {
			if (e.target.matches(".menu-item") && e.target.textContent.trim() === "Duplicate") {
			  const ruleBlock = e.target.closest(".rule-block");
			  const ruleId = ruleBlock ? ruleBlock.dataset.ruleId : 0;
			  const productId = ruleBlock ? ruleBlock.dataset.productId : 0;
			  if (!confirm("Duplicate this rule?")) return;
			  pageLoader.style.display = "flex";
			  fetch("process.php?domain=<?= $shop_domain ?>", {
				method: "POST",
				headers: { "Content-Type": "application/x-www-form-urlencoded" },
				body: new URLSearchParams({ action: "duplicate_rule", rule_id: ruleId, product_id: productId })
			  })
				.then(res => res.json())
				.then(data => {
				  pageLoader.style.display = "none";
				  if (data.success) {
					alert("Rule duplicated!");
					location.reload();
				  } else {
					alert("Failed to duplicate rule.");
				  }
				})
				.catch(() => {
				  pageLoader.style.display = "none";
				  alert("Error duplicating rule.");
				});
			}
		  });

		  // --- Delete ---
		  document.addEventListener("click", function (e) {
			if (e.target.matches(".menu-item") && e.target.textContent.trim() === "Delete") {
			  const ruleBlock = e.target.closest(".rule-block");
			  const ruleId = ruleBlock ? ruleBlock.dataset.ruleId : 0;
			  const productId = ruleBlock ? ruleBlock.dataset.productId : 0;
			  if (!confirm("Are you sure you want to delete this rule?")) return;
			  pageLoader.style.display = "flex";
			  fetch("process.php?domain=<?= $shop_domain ?>", {
				method: "POST",
				headers: { "Content-Type": "application/x-www-form-urlencoded" },
				body: new URLSearchParams({ action: "delete_rule", rule_id: ruleId, product_id: productId })
			  })
				.then(res => res.json())
				.then(data => {
				  pageLoader.style.display = "none";
				  if (data.success) {
					alert("Rule deleted!");
					location.reload();
				  } else {
					alert("Failed to delete rule.");
				  }
				})
				.catch(() => {
				  pageLoader.style.display = "none";
				  alert("Error deleting rule.");
				});
			}
		  });

		});
	</script>
    <script>
		

        document.addEventListener("DOMContentLoaded", () => {
			/* Edit option modal */
			  const pageLoader = document.getElementById("pageLoader");
			  const updateOptionModal = document.getElementById("updateOptionModal");
			  const updateOptionForm = document.getElementById("updateOptionForm");
			  const optionValuesWrapper = document.getElementById("optionValuesWrapper");

			  // === Open Edit Option Modal ===
			  document.addEventListener("click", function (e) {
				if (e.target.classList.contains("edit-option")) {
				  const optionId = e.target.dataset.optionid;
				  const productId = e.target.dataset.productid;

				  // Show loader
				  pageLoader.style.display = "flex";

				  // Fetch data from server
				  fetch("process.php?domain=<?= $shop_domain ?>", {
					method: "POST",
					headers: { "Content-Type": "application/x-www-form-urlencoded" },
					body: new URLSearchParams({ option_id: optionId, productId: productId, action: "get_selected_option_values" })
				  })
					.then(res => res.json())
					.then(data => {
					  pageLoader.style.display = "none";

					  if (data.success) {
						// Fill modal data
						optionValuesWrapper.innerHTML = ""; // clear existing
						data.data.values.forEach(val => {
						  // Create label + checkbox
						  const div = document.createElement("div");
						  div.classList.add("option-item");

						  const checkbox = document.createElement("input");
						  checkbox.type = "checkbox";
						  checkbox.name = "option_values[]";
						  checkbox.value = val.option_value_id;
						  checkbox.id = `option_${val.option_value_id}`;
						  if (val.is_selected == 1) checkbox.checked = true;

						  const label = document.createElement("label");
						  label.htmlFor = checkbox.id;
						  label.textContent = val.label;

						  div.appendChild(checkbox);
						  div.appendChild(label);
						  optionValuesWrapper.appendChild(div);
						});

						// Set hidden fields
						updateOptionForm.querySelector("input[name='option_id']").value = optionId;
						updateOptionForm.querySelector("input[name='product_id']").value = productId;
						// Show modal
						updateOptionModal.style.display = "block"; 
					  } else {
						alert("Failed to load option");
					  }
					})
					.catch(() => {
					  pageLoader.style.display = "none";
					  alert("Error fetching option values");
					});
				}
			  });

			  // === Close Modal ===
			  document.addEventListener("click", function (e) {
				if (e.target.classList.contains("close-modal")) {
				  updateOptionModal.style.display = "none";
				  updateOptionForm.reset();
				  optionValuesWrapper.innerHTML = "";
				}
			  });
			  
			  // Save Edit Option
			  updateOptionForm.addEventListener("submit", function (e) {
				e.preventDefault();
				const formData = new FormData(updateOptionForm);
				pageLoader.style.display = "flex";

				fetch("process.php?domain=<?= $shop_domain ?>", {
				  method: "POST",
				  body: formData
				})
				  .then(res => res.json())
				  .then(data => {
					pageLoader.style.display = "none";
					if (data.success) {
					  alert("Option updated!");
					  updateOptionModal.style.display = "none";
					  location.reload();
					} else {
					  alert("Update failed");
					}
				  })
				  .catch(() => {
					pageLoader.style.display = "none";
					alert("Error saving option");
				  });
			  });
			  
			  /* Add option modal */
			  const addOptionModal = document.getElementById("addOptionModal");
			  const addOptionForm = document.getElementById("addOptionForm");
			  const addOptionValuesWrapper = document.getElementById("addOptionValuesWrapper");

			  // === Open Add Option Modal ===
			  document.addEventListener("click", function (e) {
				  if (e.target.classList.contains("add-option")) {
					const productId = e.target.dataset.productid;

					// Show loader
					pageLoader.style.display = "flex";

					fetch("process.php?domain=<?= $shop_domain ?>", {
					  method: "POST",
					  headers: { "Content-Type": "application/x-www-form-urlencoded" },
					  body: new URLSearchParams({ productId: productId, action: "get_all_available_options" })
					})
					  .then(res => res.json())
					  .then(data => {
						pageLoader.style.display = "none";

						if (data.success) {
						  addOptionValuesWrapper.innerHTML = "";

						  const div = document.createElement("div");
						  div.classList.add("form-check", "mt-2");

						  const requiredCheckbox = document.createElement("input");
						  requiredCheckbox.type = "checkbox";
						  requiredCheckbox.name = "is_required";
						  requiredCheckbox.id = "is_required";
						  requiredCheckbox.classList.add("form-check-input");

						  const requiredLabel = document.createElement("label");
						  requiredLabel.htmlFor = "is_required";
						  requiredLabel.textContent = "Required option";
						  requiredLabel.classList.add("form-check-label");

						  div.appendChild(requiredCheckbox);
						  div.appendChild(requiredLabel);

						  // ✅ FIXED: inject HTML string correctly
						  div.insertAdjacentHTML("beforeend", data.html);

						  addOptionValuesWrapper.appendChild(div);

						  addOptionForm.querySelector("input[name='product_id']").value = productId;
						  addOptionModal.style.display = "block";
						} else {
						  alert("Failed to load option");
						}
					  })
					  .catch(() => {
						pageLoader.style.display = "none";
						alert("Error fetching option values");
					  });
				  }
				});


			  // === Close Modal ===
			  document.addEventListener("click", function (e) {
				if (e.target.classList.contains("close-amodal")) {
				  addOptionModal.style.display = "none";
				  addOptionForm.reset();
				  addOptionValuesWrapper.innerHTML = "";
				}
			  });
			  
			  // Save Edit Option
			  addOptionForm.addEventListener("submit", function (e) {
				e.preventDefault();
				const formData = new FormData(addOptionForm);
				pageLoader.style.display = "flex";

				fetch("process.php?domain=<?= $shop_domain ?>", {
				  method: "POST",
				  body: formData
				})
				  .then(res => res.json())
				  .then(data => {
					pageLoader.style.display = "none";
					if (data.success) {
					  alert("Option Added!");
					  addOptionModal.style.display = "none";
					  location.reload();
					} else {
					  alert("Update failed");
					}
				  })
				  .catch(() => {
					pageLoader.style.display = "none";
					alert("Error saving option");
				  });
			  });
			  
			  
            const triggers = document.querySelectorAll(".prdtabs-trigger");
			const contents = document.querySelectorAll(".prdtabs-content");

			// Handle click
			triggers.forEach(trigger => {
			  trigger.addEventListener("click", () => {
				// Remove active/hide content
				triggers.forEach(btn => btn.classList.remove("active"));
				contents.forEach(c => c.classList.add("hidden"));

				// Activate clicked tab
				trigger.classList.add("active");
				const targetId = trigger.getAttribute("data-id");
				const el = document.getElementById(targetId);
				if (el) el.classList.remove("hidden");

				// ✅ Update URL param without reloading
				const url = new URL(window.location);
				url.searchParams.set("currenttab", targetId);
				window.history.replaceState({}, "", url);
			  });
			});
			
			const urlParams = new URLSearchParams(window.location.search);
		    const currentTab = urlParams.get("currenttab");

			  if (currentTab) {
				const activeTrigger = document.querySelector(`.prdtabs-trigger[data-id="${currentTab}"]`);
				const activeContent = document.getElementById(currentTab);

				if (activeTrigger && activeContent) {
				  triggers.forEach(btn => btn.classList.remove("active"));
				  contents.forEach(c => c.classList.add("hidden"));
				  activeTrigger.classList.add("active");
				  activeContent.classList.remove("hidden");
				}
			  }
        });
        document.addEventListener("DOMContentLoaded", () => {
            const wrapper = document.querySelector(".prd-options");
            const ENDPOINT = `https://apps.royalhawaiianheritage.com/process.php?domain=<?= $shop_domain ?>`;

            if (!wrapper) return;

            function ensureSaveButton(group) {
                let saveBtn = group.querySelector(".save-value");
                if (!saveBtn) {
                    saveBtn = document.createElement("button");
                    saveBtn.type = "button";
                    saveBtn.className = "save-value";
                    saveBtn.textContent = "Save";
                    saveBtn.disabled = true;
                    saveBtn.style.display = "inline-block";
                    group.appendChild(saveBtn);
                }
                return saveBtn;
            }

            function getValueInputs(group) {
                return Array.from(group.querySelectorAll("input.option-value-input, input.option-text"));
            }

            function primeGroups() {
                document.querySelectorAll(".option-group").forEach(group => {
                    if (!group.getAttribute("data-optionid")) group.setAttribute("data-optionid", "existing");
                    const title = group.querySelector(".option-title-input");
                    if (title && !title.hasAttribute("data-value")) title.setAttribute("data-value", (title.value || "").trim());
                    const inputs = getValueInputs(group);
                    inputs.forEach((input, i) => {
                        if (!input.hasAttribute("data-index")) input.setAttribute("data-index", String(i));
                        if (!input.hasAttribute("data-value")) input.setAttribute("data-value", (input.value || "").trim());
                        if (!input.hasAttribute("data-optionvalueid")) input.setAttribute("data-optionvalueid", "new");
                    });
                });
            }

            function renormalizeIndices(group) {
                const inputs = getValueInputs(group).filter(i => i.getAttribute("data-removed") !== "true");
                inputs.forEach((input, idx) => input.setAttribute("data-index", String(idx)));
            }

            function preparePayload(group) {
                const productId = group.closest(".custom-product-options")?.dataset.productid || null;
                const optionId = group.getAttribute("data-optionid");
                const changes = [];

                const titleInput = group.querySelector(".option-title-input");
                const inputType = group.querySelector(".option-type");
                if (titleInput && inputType) {
                    const oldValue = titleInput.getAttribute("data-value") || "";
                    const newValue = (titleInput.value || "").trim();
                    const inputTypeOldValue = inputType.getAttribute("data-value") || "";
                    const inputTypeNewValue = (inputType.value || "").trim();
                    if (oldValue !== newValue || inputTypeOldValue !== inputTypeNewValue) {
                        const actionType = optionId === "new" ? "insert_option" : "update";
                        changes.push({
                            product_option_id: optionId === "new" ? null : optionId,
                            product_id: productId,
                            new_value: newValue,
                            action: actionType,
                            input_type: inputType.value,
                            field: "title"
                        });
                    }
                }

                getValueInputs(group).forEach(input => {
                    const oldValue = input.getAttribute("data-value") || "";
                    const newValue = (input.value || "").trim();
                    const removed = input.getAttribute("data-removed") === "true";
                    const optionValueId = input.getAttribute("data-optionvalueid");

                    if (removed && optionValueId && optionValueId !== "new") {
                        changes.push({
                            option_id: optionId,
                            option_value_id: optionValueId,
                            action: "remove_value"
                        });
                    } else if (!removed && oldValue !== newValue) {
                        changes.push({
                            option_id: optionId,
                            option_value_id: optionValueId,
                            index: input.getAttribute("data-index"),
                            new_value: newValue,
                            action: optionValueId === "new" ? "insert_value" : "update_value",
                            field: "value"
                        });
                    }
                });

                return changes;
            }

            function refreshSaveButton(group) {
                const saveBtn = ensureSaveButton(group);
                const payload = preparePayload(group);
                saveBtn.disabled = payload.length === 0;
                saveBtn.style.display = payload.length ? "inline-block" : "none";
            }

            primeGroups();

            wrapper.addEventListener("click", (e) => {
                const group = e.target.closest(".option-group");


                // --- Remove Option (with confirm + AJAX, no save button) ---
                if (e.target.classList.contains("remove-option") && group) {
					
					const optionId = e.target.dataset.optionid;
					const productId = e.target.dataset.productid;

                    if (optionId !== "new") {
                        if (!confirm("Are you sure you want to remove this option?\n\nThis action cannot be undone.")) return;

                        const changes = [{
                            option_id: optionId,
                            product_id: productId,
                            action: "remove_option"
                        }];

                        const formData = new URLSearchParams({
                            action: "update_options",
                            data: JSON.stringify({
                                changes
                            })
                        });

                        fetch(ENDPOINT, {
                                method: "POST",
                                headers: {
                                    "Content-Type": "application/x-www-form-urlencoded"
                                },
                                body: formData
                            })
                            .then(r => r.json())
                            .then(resp => {
                                console.log("Option removed:", resp);
                                location.reload();
                            })
                            .catch(err => console.error("Remove option error", err));
                    } else {
                        group.remove();
                    }
                }

            });

            // Input change triggers save button refresh
            wrapper.addEventListener("input", (e) => {
                if (e.target.matches(".option-value-input, .option-title-input, .option-text")) {
                    const group = e.target.closest(".option-group");
                    refreshSaveButton(group);
                }
            });

            wrapper.addEventListener("change", (e) => {
                if (e.target.classList.contains("option-type")) {
                    const group = e.target.closest(".option-group");
                    const addBtn = group.querySelector(".add-value");
                    if (addBtn) addBtn.style.display = "inline-block";
                    refreshSaveButton(group);
                }
            });
	   });
	
		function updateAdjusterRule(checkbox) {
		  let pageLoader = document.getElementById("pageLoader");
		  let ruleBlock = checkbox.closest(".rule-block");
		  let ruleId = ruleBlock.dataset.ruleId;
		  let productId = ruleBlock.dataset.productId;
		  let status = checkbox.checked ? 'true' : 'false';

		  // Show loader
		  pageLoader.style.display = "flex";

		  fetch("process.php?domain=<?= $shop_domain ?>", {
			method: "POST",
			headers: { "Content-Type": "application/x-www-form-urlencoded" },
			body: new URLSearchParams({
			  action: "update_adjuster_status",
			  rule_id: ruleId,
			  product_id: productId,
			  status: status
			})
		  })
			.then(res => res.json())
			.then(data => {
			  // Hide loader
			  pageLoader.style.display = "none";

			  if (data.success) {
				  showToast("✅ Status updated successfully for rule:");
				console.log("✅ Status updated successfully for rule:", ruleId);
			  } else {
				showToast("⚠️ Error updating status:");
				console.error("⚠️ Error updating status:", data.message);
			  }
			})
			.catch(err => {
			  pageLoader.style.display = "none";
			  showToast("⚠️ Error updating status:");
			  console.error("❌ AJAX error:", err);
			});
		}
		
		function showToast(message, timeout = 2000) {
			let existing = document.getElementById("hw-toast-message");
			if (existing) {
			  existing.remove();
			}
			const t = document.createElement("div");
			t.id = "hw-toast-message";
			t.style.position = "fixed";
			t.style.top = "20px";
			t.style.right = "20px";
			t.style.background = "#333";
			t.style.color = "#fff";
			t.style.padding = "10px 14px";
			t.style.borderRadius = "6px";
			t.style.boxShadow = "0 4px 12px rgba(0,0,0,0.15)";
			t.style.zIndex = 100001;
			t.style.fontSize = "13px";
			t.textContent = message;
			document.body.appendChild(t);
			setTimeout(() => {
			  t.style.transition = "opacity 250ms";
			  t.style.opacity = "0";
			  setTimeout(() => t.remove(), 260);
			}, timeout);
		  }

        document.addEventListener("click", (e) => {
            document.querySelectorAll(".menu-popup").forEach(menu => {
                if (!menu.contains(e.target) && !menu.previousElementSibling.contains(e.target)) {
                    menu.classList.remove("show");
                }
            });
        });

        document.querySelectorAll(".menu-icon").forEach(icon => {
            icon.addEventListener("click", (e) => {
                e.stopPropagation();
                const menu = icon.nextElementSibling;
                menu.classList.toggle("show");
            });
        });

        const modalOverlay = document.querySelector(".modal-overlay");
        document.querySelectorAll(".edit-btn").forEach(btn => {
            btn.addEventListener("click", () => {
                modalOverlay.style.display = "flex";
                document.querySelectorAll(".menu-popup").forEach(menu => menu.classList.remove("show"));
            });
        });

        document.querySelector(".close-modal").addEventListener("click", () => {
            modalOverlay.style.display = "none";
        });
		
		

		
    </script>
	
	<script>
		$(function() {
		  const pageLoader = document.getElementById("pageLoader");
		  const shopDomain = "<?= $shop_domain ?>";

		  // Make rule blocks sortable (tbody for table layout)
		  $("#prdRules .rules-spreadsheet-table tbody").sortable({
			items: "tr.rule-block",
			cursor: "move",
			placeholder: "sortable-placeholder",
			update: function(event, ui) {
			  let orderedIds = [];
			  $("#prdRules .rule-block").each(function() {
				orderedIds.push($(this).data("rule-id"));
			  });
			  let productId = $("#prdRules .rule-block:first").data("product-id");
			  pageLoader.style.display = "flex";
			  $.ajax({
				url: "process.php?domain=" + shopDomain,
				type: "POST",
				data: { productId: productId, rule_order: orderedIds, action: 'update_rule_order' },
				success: function() { pageLoader.style.display = "none"; },
				error: function() { pageLoader.style.display = "none"; alert("Error saving order"); }
			  });
			}
		  });
		  $("#prdRules").disableSelection();

		  // ===== Spreadsheet-style inline price editing: Enter/Tab moves to next row =====
		  const priceInputs = document.querySelectorAll(".rule-price-input");
		  priceInputs.forEach(function(input, idx) {
			input.addEventListener("blur", function() { saveRuleValue(this); });
			input.addEventListener("keydown", function(e) {
			  if (e.key === "Enter" || e.key === "Tab") {
				e.preventDefault();
				saveRuleValue(this);
				const nextIdx = (e.key === "Enter" || !e.shiftKey) ? idx + 1 : idx - 1;
				const next = priceInputs[nextIdx];
				if (next) { next.focus(); next.select(); }
			  }
			});
		  });

		  function saveRuleValue(inputEl) {
			const ruleId = inputEl.dataset.ruleId;
			const productId = inputEl.dataset.productId;
			const val = inputEl.value.trim();
			if (!ruleId || !productId) return;
			fetch("process.php?domain=" + shopDomain, {
			  method: "POST",
			  headers: { "Content-Type": "application/x-www-form-urlencoded" },
			  body: new URLSearchParams({ action: "update_rule_value", rule_id: ruleId, product_id: productId, adjuster_value: val })
			})
			.then(r => r.json())
			.then(data => {
			  if (data.success && typeof showToast === "function") showToast("Saved");
			});
		  }
		});
		</script>

    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap');

        * {
            font-family: "Montserrat", sans-serif;
            box-sizing: border-box;
        }

        body {
            margin: 0px;
            padding: 40px;
            background: #fafafa;
        }

        .hidden {
            display: none;
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
            background-color: #1976D2;
            color: white;
            padding: 12px 24px;
            font-size: 16px;
            border-radius: 6px;
            text-decoration: none;
            transition: background-color 0.3s ease, transform 0.2s ease;
        }

        .install-btn:hover {
            background-color: #0056b3;
            transform: translateY(-2px);
        }

        .single-prd-options-wrapper {
            font-family: Arial, sans-serif;
            margin: 20px 0;
        }

        .prd-options {
            background: #fafafa;
            border: 1px solid #ddd;
            padding: 20px;
            border-radius: 10px;
        }

        .option-group {
            background: #fff;
            border: 1px solid #e1e1e1;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.05);
        }

        .option-header {
            display: flex;
            /* align-items: center; */
            justify-content: space-between;
            margin-bottom: 15px;
        }

        .option-title-input {
            flex: 1;
            font-size: 18px;
            font-weight: bold;
            padding: 8px 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            margin-right: 10px;
        }

        button:not(.remove-value, .prdtabs-trigger) {
            cursor: pointer;
            font-size: 16px;
            padding: 6px 12px;
            border-radius: 6px;
            border: none;
            transition: all 0.2s ease;
			height: 34px;
			font-size: 12px;
        }

        .remove-value {
            cursor: pointer;
            font-size: 30px;
            height: 40px;
            width: 40px;
            border-radius: 6px;
            border: none;
            transition: all 0.2s ease;
            line-height: 1.2;
        }

        .remove-option,
        .remove-value {
            background: #ff4d4f;
            color: #fff;
        }
        .custom-product-button {
            display: flex;
        }
        .custom-product-button button {
            margin: 0 5px;
        }
		.productoption p {
			margin:5px;
		}
        .edit-option {
            background: #dedede;
            color: #000;
        }

        .option-type {
            border: 1px solid #ccc;
            font-size: 16px;
            padding: 6px;
            border-radius: 6px;
            margin-right: 10px;
            cursor: pointer;
            outline: none;
            box-shadow: none;
        }

        .remove-option:hover,
        .remove-value:hover {
            background: #d9363e;
        }

        .add-option,
        .add-value {
            background: #1677ff;
            color: #fff;
            margin-top: 10px;
        }

        .add-option:hover,
        .add-value:hover {
            background: #125ecf;
        }

        .option-value-input,
        .option-text {
            flex: 1;
            padding: 8px 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
            margin-right: 10px;
            font-size: 16px;
        }

        .option-radio,
        .option-select-value {
            display: flex;
            align-items: center;
            margin-bottom: 8px;
        }

        .option-radio input[type="radio"],
        .option-select-value input[type="radio"] {
            margin-right: 8px;
        }

        .prdtabs {
            display: flex;
            gap: 10px;
            margin-bottom: 0;
        }

        .prdtabs-trigger {
            padding: 8px 16px;
            border: 1px solid #ccc;
            background: #f9f9f9;
            cursor: pointer;
            border-radius: 4px 4px 0 0;
            font-size: 14px;
            transition: background 0.3s, color 0.3s;
        }

        .prdtabs-trigger.active {
            background: #333;
            color: #fff;
            border-color: #333;
        }

        .prdtabs-content {
            border: 1px solid #dedede;
            padding: 15px;
            border-radius: 0 0 8px 8px;
            background: #fff;
        }

        .rule-block-conditions {
            font-size: 14px;
            display: flex;
            flex-direction: column;
            gap: 5px;
            list-style: none;
            padding: 0;
            margin: 0;
            color: #000;
            font-weight: 500;
        }

        /* rule-block: only apply when NOT in spreadsheet table */
        #prdOptions .rule-block {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 10px;
            position: relative;
			border-bottom: 1px solid #c3c3c3;
        }

        .rule-block-adjuster {
            color: #000;
            font-weight: 500;
            font-size: 16px;
        }

        .save-value[disabled] {
            color: #111111;
            border: 1px solid #ccc;
            opacity: .6;
            transition: all 0.3s;
        }

        .save-value:not([disabled]) {
            color: #fff;
            background: green;
        }

        .save-value {
            margin-left: 20px;
        }

        div#prdRules hr {
            margin: 15px 0;
        }

        div#prdRules hr:last-child {
            display: none;
        }

        .prd-rules-header { margin-bottom: 16px; }
        .prd-rules .add-rule-btn {
            background: #28a745;
            color: #fff;
            padding: 10px 18px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
        }
        .prd-rules .add-rule-btn:hover { background: #218838; }

        /* Rules spreadsheet table - clean Shopify-style design */
        .rules-spreadsheet-wrap {
            overflow-x: auto;
            margin-top: 12px;
            background: #fff;
            border-radius: 8px;
            border: 1px solid #e5e5e5;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
        }
        .rules-spreadsheet-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            min-width: 600px;
        }
        .rules-spreadsheet-table th,
        .rules-spreadsheet-table td {
            padding: 12px 16px;
            text-align: left;
            border-bottom: 1px solid #e8e8e8;
            vertical-align: middle;
        }
        .rules-spreadsheet-table th {
            background: #f8f9fa;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #6c757d;
        }
        .rules-spreadsheet-table tbody tr {
            transition: background 0.15s ease;
        }
        .rules-spreadsheet-table tbody tr:hover {
            background: #f8f9fa;
        }
        .rules-spreadsheet-table tbody tr:last-child td {
            border-bottom: none;
        }
        .rules-price-col { width: 130px; }
        .rule-price-cell { width: 130px; }
        .rule-price-input {
            width: 100%;
            max-width: 100px;
            padding: 8px 12px;
            font-size: 14px;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            background: #fff;
        }
        .rule-price-input:hover {
            border-color: #adb5bd;
        }
        .rule-price-input:focus {
            border-color: #1976D2;
            outline: none;
            box-shadow: 0 0 0 2px rgba(25,118,210,0.15);
        }
        .rule-conditions-cell {
            min-width: 220px;
            line-height: 1.45;
            color: #212529;
        }
        .rule-conditions-cell strong { color: #495057; }
        .rule-type-cell {
            width: 95px;
            color: #6c757d;
            font-size: 13px;
            white-space: nowrap;
        }
        .rule-enabled-cell {
            width: 56px;
            text-align: center;
        }
        .rule-enabled-cell input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin: 0;
            accent-color: #1976D2;
        }
        .rule-actions-cell {
            width: 44px;
            text-align: right;
        }
        .rule-actions-cell .menu-icon {
            color: #6c757d;
            font-size: 18px;
        }
        tr.sortable-placeholder {
            background: #e3f2fd !important;
            border: 2px dashed #1976D2;
            height: 52px;
        }

        .rule-block-adjuster {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .rule-block-adjuster input[type="checkbox"] {
            margin: 0;
            width: 22px;
            height: 22px;
            accent-color: #000;
            margin-left: 25px;
        }

        .menu-wrapper {
            position: relative;
        }

        .menu-icon {
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 6px;
        }

        .menu-icon:hover {
            background: #f2f2f2;
        }

        .menu-popup {
            position: absolute;
            top: 28px;
            right: 0;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            display: none;
            flex-direction: column;
            min-width: 120px;
            z-index: 10;
        }

        .menu-popup.show {
            display: flex;
        }

        .menu-item {
            padding: 8px 12px;
            background: none;
            border: none;
            text-align: left;
            cursor: pointer;
            font-size: 14px;
        }

        .menu-item:hover {
            background: #f9f9f9;
        }

        /* Modal styles */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 20;

        }

        .modal {
            background: #fff;
            padding: 20px;
            border-radius: 10px;
            width: 400px;
            max-width: 90%;
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.2);
			top: 0;
			left: 0;
			right: 0;
			margin: auto;
			bottom: 0;
			height: max-content;
			position: absolute;
        }
		.modal h2 {
			margin: 0 0 30px;
			text-align: center;
			font-weight: 500;
			font-size: 20px;
		}
        .close-modal, .close-amodal {
            margin-top: 20px;
            padding: 8px 14px;
            border: none;
            background: crimson;
            color: #fff;
            border-radius: 6px;
            cursor: pointer;
        }

        .close-modal:hover, .close-amodal:hover {
            background: darkred;
        }
		
		/* Full Page Loader */
		.page-loader {
		  position: fixed;
		  top: 0;
		  left: 0;
		  right: 0;
		  bottom: 0;
		  background: rgba(255, 255, 255, 0.8);
		  display: none; /* hidden by default */
		  align-items: center;
		  justify-content: center;
		  z-index: 5000; /* above everything */
		}

		/* Spinner */
		.page-loader::after {
		  content: "";
		  width: 50px;
		  height: 50px;
		  border: 5px solid #ccc;
		  border-top-color: #007bff;
		  border-radius: 50%;
		  animation: spin 0.8s linear infinite;
		}

		@keyframes spin {
		  to {
			transform: rotate(360deg);
		  }
		}
		
		#addOptionModal select {
			width: 100%;
			height: 38px;
			padding: 5px 10px;
		}
		.sortable-placeholder {
		  border: 2px dashed #aaa;
		  background: #f9f9f9;
		  height: 80px;
		  margin: 5px 0;
		}
		.option-header h3 {
			font-weight: 400;
			margin: 0;
			font-size: 15px;
		}
		.productoption label {
			font-size: 13px;
		}
		h2#ruleModalTitle {
			margin-bottom: 0;
		}
		form#editRuleForm h3 + div select,
		form#editRuleForm h3 + input  {
			width: 110px;
			height: 28px;
			border-radius: 5px;
			border: 1px solid #dedede;
			padding: 5px 10px;
			font-size: 12px;
		}
    </style>
</body>

</html>