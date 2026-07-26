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

// pagination + search setup
$search = $_GET['search'] ?? '';
$page   = isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 1 ? (int)$_GET['page'] : 1;

// call function
$response = $admin->get_options_by_page($page, $search);

// unpack response
$options    = $response['data'] ?? [];
$total      = $response['total'] ?? 0;
$totalPages = $response['pages'] ?? 1;
$current    = $response['page'] ?? 1;
$limit      = $response['per_page'] ?? 20;
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
        	margin: 0;
        	padding: 40px;
        	background: #fafafa;
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
        	background-color: #1976D2;
        	transform: translateY(-2px);
        }

        .product-card {
        	display: flex;
        	align-items: center;
        	padding: 10px;
			border: 1px solid #BDBDBD; /* Material Gray 400 */
			border-radius: 8px;
        	width: 100%;
        	gap: 20px;
        	position: relative;
        }


        .products-grid {
        	display: flex;
        	flex-direction: column;
        	gap: 10px;
        }

        .product-info {
        	display: flex;
        	align-items: center;
        	width: 100%;
        	justify-content: space-between;
        	gap: 20px;
        }

        .product-title {
        	font-size: 16px;
        	line-height: 1.5;
        	max-width: 70%;
        }

        .product-status {
        	font-size: 14px;
        	padding: 8px 15px;
        	line-height: 01;
        	color: #000;
        }

        .product-search {
        	display: flex;
        	justify-content: space-between;
        	margin-bottom: 15px;
        }

        .product-search input[type="text"] {
        	flex: 1;
        	font-size: 18px;
        	padding: 8px 10px;
        	border: 1px solid #ccc;
        	border-radius: 6px;
        	margin-right: 10px;
        	font-weight: 500;
        }

        .product-search button {
        	cursor: pointer;
        	font-size: 16px;
        	padding: 13px 20px;
        	border-radius: 6px;
        	border: none;
        	transition: all 0.2s ease;
        	background: #1976D2;
        	color: #fff;
        }

        .product-search button:hover {
        	background: #0b5ed3;
        }

        .pagination-link {
        	transition: all 0.2s ease;
        	display: inline-flex;
        	background: #1976D2;
        	color: #fff;
        	text-decoration: none;
        	line-height: 1;
        	height: 30px;
        	width: 60px;
        	align-items: center;
        	justify-content: center;
        	font-size: 12px;
        	border-radius: 10px;
        }

        .pagination.pagination-wrapper {
        	display: flex;
        	align-items: center;
        	justify-content: center;
        	gap: 10px;
        	margin-top: 20px;
        }

        .pagination-link:hover {
        	background: #1976D2;
        }
		a.back-button {
			width: 100px;
			display: inline-flex;
			align-items: center;
			justify-content: center;
			background: #000;
			height: 42px;
			border-radius: 5px;
			color: #fff;
			text-decoration: none;
		}
    </style>
</head>

<body>
	<div class="page-loader" id="pageLoader"></div>

    <div class="dashboard-wrapper-header">
        <h2 class="dashboard-title">All Options</h2>
        <div class="nav-bar">
			<a class="btn install-btn" href="javascript:;" id="openAddOption">Add New Option</a>
            <a href="<?= "dashboard.php?shop=" . urlencode($shop) ?>" class="back-button">← Back</a>
        </div>
    </div>

    <div class="dashboard-wrapper">
        <div class="shop-info">
            <form method="get" class="product-search">
                <input type="hidden" name="shop" value="<?= htmlspecialchars($shop) ?>">
                <input type="text" name="search" placeholder="Search option..." value="<?= htmlspecialchars($search) ?>">
                <button type="submit">Search</button>
            </form>

            <div class="products-grid">
                <?php if (!empty($options)): ?>
                    <?php foreach ($options as $opt): ?>
                        <div class="product-card">
                            <div class="product-info">
                                <div class="product-title">
									<?php $displayName = $opt['display_name'];

									// decode raw column JSON
									$rawData = json_decode($opt['raw'], true);
									$rawName = $rawData['name'] ?? '';

									// if display_name and name are different, show both
									if ($displayName !== $rawName && $rawName !== '') {
										$title = $displayName . " (" . $rawName . ")";
									} else {
										$title = $displayName;
									}
                                    echo htmlspecialchars($title) ?>
                                </div>
                                <div class="product-status option-row-actions">
                                    <!--<?= $opt['status'] == 1 ? 'Active' : 'Inactive' ?>-->
                                    <button type="button" class="edit_option" data-optionid="<?= $opt['option_id'] ;?>">Edit</button>
                                    <button type="button" class="delete_option" data-optionid="<?= $opt['option_id'] ;?>">Delete</button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No options found.</p>
                <?php endif; ?>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
                <div class="pagination pagination-wrapper">
                    <?php if ($current > 1): ?>
                        <a class="pagination-link prev" href="?shop=<?= urlencode($shop) ?>&page=<?= $current - 1 ?>&search=<?= urlencode($search) ?>">Prev</a>
                    <?php endif; ?>

                    <span>Page <?= $current ?> of <?= $totalPages ?></span>

                    <?php if ($current < $totalPages): ?>
                        <a class="pagination-link next" href="?shop=<?= urlencode($shop) ?>&page=<?= $current + 1 ?>&search=<?= urlencode($search) ?>">Next</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
	<!-- Modal -->
	<div class='modal-overlay' id="editOptionModal" style="display:none;">
	  <div class='modal'>
		<h2>Edit Option</h2>
		<form id="editOptionForm" enctype="multipart/form-data">
		  <input type="hidden" name="action" value="update_option_values">
		  <div style="margin-top:20px;">
			<label>Option Title (Type: <span id="get_type"></span>)</label>
			<input type="text" name="option_name" id="option_name" value="">
		  </div>
		  <div style="margin-top:10px;">
			<label>Display Name <small style="color:#888;">(optional — shown in app &amp; storefront instead of Option Title)</small></label>
			<input type="text" name="front_label" id="front_label" value="" placeholder="Leave empty to use Option Title">
		  </div>
		  <div style="margin-top:10px;margin-bottom:10px;" id="showtypee" style="display:none;">
			<label><input type="checkbox" name="engraving" id="engraving" value="Yes" style="width:20px;height:20px;"> Engraving</label>			
					</div>
		  <div id="optionValuesWrapper"></div>
		  <button type="button" id="addValueBtn">+ Add Value</button>
		  <div style="margin-top:20px; text-align: right; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:10px;">
			<button type="button" id="deleteOptionFromModal" class="btn-delete-option" style="background:#dc3545;color:#fff;">Delete option</button>
			<div style="display:flex; gap:8px;">
			<button type="submit">Save</button>
			<button type="button" class='close-modal'>Close</button>
			</div>
		  </div>
		</form>
	  </div>
	</div>
	
	<!-- Add New Option Modal -->
	<div class='modal-overlay' id="addOptionModal" style="display:none;">
	  <div class='modal'>
		<h2>Add New Option</h2>
		<form id="addOptionForm">
		  <input type="hidden" name="action" value="add_new_option">
		  <div style="margin-top:20px;">
			<label>Option Title  </label>
			<input type="text" name="new_option_name" id="new_option_name" required>
		  </div>
		  <div style="margin-top:20px;">
			<label>Option Type</label>
			<select name="optiontype" required>
				<option value="RT">Radio</option>
				<option value="S">Select</option>
				<option value="T">Text</option>
			</select>
		  </div>
		  <div style="margin-top:20px; text-align: right;">
			<button type="submit">Save</button>
			<button type="button" class='close-modal'>Close</button>
		  </div>
		</form>
	  </div>
	</div>
	
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.3/Sortable.min.js"></script>
    <script>
		document.addEventListener("DOMContentLoaded", function () {
		  const pageLoader = document.getElementById("pageLoader");

		  // ===== Edit Option Elements =====
		  const editOptionModal = document.getElementById("editOptionModal");
		  const editOptionForm = document.getElementById("editOptionForm");
		  const optionValuesWrapper = document.getElementById("optionValuesWrapper");
		  const addValueBtn = document.getElementById("addValueBtn");

		  // ===== Add Option Elements =====
		  const addOptionModal = document.getElementById("addOptionModal");
		  const addOptionForm = document.getElementById("addOptionForm");
		  let newDefaultSlotIndex = 0;

		  // =============================
		  // Open Edit Option Modal
		  document.addEventListener("click", function (e) {
			if (e.target.classList.contains("edit_option")) {
			  const optionId = e.target.dataset.optionid;
			  newDefaultSlotIndex = 0;
			  pageLoader.style.display = "flex";
				document.getElementById('addValueBtn').style.display='block';
				document.getElementById('showtypee').style.display='none';
			  fetch("process.php?domain=<?= $shop_domain ?>", {
				method: "POST",
				headers: { "Content-Type": "application/x-www-form-urlencoded" },
				body: new URLSearchParams({ option_id: optionId, action: "get_option_values" })
			  })
				.then(res => res.json())
				.then(data => {
				  pageLoader.style.display = "none";
				  if (data.success) {
					optionValuesWrapper.innerHTML = "";
					document.getElementById('option_name').value = data.data.option.display_name;
				document.getElementById('front_label').value = data.data.option.front_label ?? '';
					if(data.data.option.type=='RT'){
						document.getElementById('get_type').innerHTML = "Radio";
					}else if(data.data.option.type=='S'){
						document.getElementById('get_type').innerHTML = "Select";
					}else{
						document.getElementById('get_type').innerHTML = "Text";
						document.getElementById('addValueBtn').style.display='none';
						document.getElementById('showtypee').style.display='block';
					}
					if(data.data.option.engraving=='Yes')
					{
						document.getElementById('engraving').checked = true;
					}

					// Fill values (Radio/Select: one default per option set → is_default in DB)
					const showDefaultRadios = (data.data.option.type === 'RT' || data.data.option.type === 'S');
					const vals = data.data.values || [];
					const defaultIdx = vals.findIndex(v => v.is_default == 1 || v.is_default === '1');
					vals.forEach((val, idx) => {
					  let defaultRadio = '';
					  if (showDefaultRadios) {
						const defChecked = (defaultIdx === -1 ? idx === 0 : idx === defaultIdx) ? 'checked' : '';
						defaultRadio = `<label class="default-value-radio" title="Default selection for storefront"><input type="radio" name="default_value_id" value="${val.option_value_id}" ${defChecked} /></label>`;
					  }
					  optionValuesWrapper.insertAdjacentHTML(
						"beforeend",
						`<div class="option-value-row">
						${defaultRadio}
						<span class="value-drag-handle" title="Drag to reorder">&#9776;</span>
						<img src="${
							val.image && val.image !== ''
							? `/images/${val.image}`
							: 'https://placehold.co/50x50/cccccc/ffffff?text=No+Img'
						}"
						alt="Preview"
						class="preview-image"
						width="50"
						height="50"
						style="object-fit: cover; border: 1px solid #ccc; border-radius: 4px;"
						/>
						   <input type="text" name="existingvalues[${val.option_value_id}]" value="${val.value}" />
						   <select name="existingpriceadjust[${val.option_value_id}]" style="width:115px;">
						     <option value="add" ${(!val.price_adjust || val.price_adjust === 'add') ? 'selected' : ''}>+ Add</option>
						     <option value="remove" ${val.price_adjust === 'remove' ? 'selected' : ''}>- Remove</option>
						   </select>
						   <input type="number" step="0.01" min="0" name="existingprices[${val.option_value_id}]" value="${val.price ?? ''}" placeholder="Price (optional)" style="width:100px;" />
						   <select name="existingpricetypes[${val.option_value_id}]" style="width:100px;">
						     <option value="relative" ${(!val.price_type || val.price_type === 'relative') ? 'selected' : ''}>$ Fixed</option>
						     <option value="percentage" ${val.price_type === 'percentage' ? 'selected' : ''}>% Percent</option>
						   </select>
						   <input type="file" name="fileupload[${val.option_value_id}]" accept=".jpg, .jpeg, .png, .webp" />
						   <button type="button" class="remove-value">❌</button>
						 </div>`
					  );
					});

					// Add hidden option_id
					let hiddenField = editOptionForm.querySelector("input[name='option_id']");
					if (!hiddenField) {
					  hiddenField = document.createElement("input");
					  hiddenField.type = "hidden";
					  hiddenField.name = "option_id";
					  editOptionForm.appendChild(hiddenField);
					}
					hiddenField.value = optionId;

					editOptionModal.style.display = "block";
					Sortable.create(optionValuesWrapper, {
					  handle: ".value-drag-handle",
					  animation: 150,
					  ghostClass: "sortable-ghost"
					});
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

		  // =============================
		  // Add new value field in Edit Modal
		  addValueBtn.addEventListener("click", function () {
			const slot = newDefaultSlotIndex++;
			const defaultRadio = `<label class="default-value-radio" title="Default selection for storefront"><input type="radio" name="default_value_id" value="new_${slot}" /></label>`;
			optionValuesWrapper.insertAdjacentHTML(
			  "beforeend",
			  `<div class="option-value-row">
			  ${defaultRadio}
			  <span class="value-drag-handle" title="Drag to reorder">&#9776;</span>
			  <img
								src="https://placehold.co/50x50/cccccc/ffffff?text=No+Img"
								alt="Preview"
								class="preview-image"
								width="50"
								height="50"
								style="object-fit: cover; border: 1px solid #ccc; border-radius: 4px;"
							/>
				 <input type="text" name="values[]" placeholder="Enter value" />
				 <select name="newpriceadjust[]" style="width:115px;">
				   <option value="add">+ Add</option>
				   <option value="remove">- Remove</option>
				 </select>
				 <input type="number" step="0.01" min="0" name="newprices[]" placeholder="Price (optional)" style="width:100px;" />
				 <select name="newpricetypes[]" style="width:100px;">
				   <option value="relative">$ Fixed</option>
				   <option value="percentage">% Percent</option>
				 </select>
				  <input type="file" name="fileuploadnew[]" accept=".jpg, .jpeg, .png, .webp" />
				 <button type="button" class="remove-value">❌</button>
			   </div>`
			);
			const allDefaultRadios = optionValuesWrapper.querySelectorAll('input[name="default_value_id"]');
			if (allDefaultRadios.length === 1) {
			  allDefaultRadios[0].checked = true;
			}
		  });

		  // Remove value field
		  optionValuesWrapper.addEventListener("click", function (e) {
			if (e.target.classList.contains("remove-value")) {
			  e.target.closest(".option-value-row").remove();
			}
		  });

		  // =============================
		  // Close any modal
		  document.addEventListener("click", function (e) {
			if (e.target.classList.contains("close-modal")) {
			  e.target.closest(".modal-overlay").style.display = "none";
			  editOptionForm.reset();
			  optionValuesWrapper.innerHTML = "";
			  addOptionForm.reset();
			}
		  });

		  function deleteOptionById(optionId) {
			if (!optionId) return;
			pageLoader.style.display = "flex";
			fetch("process.php?domain=<?= $shop_domain ?>", {
			  method: "POST",
			  headers: { "Content-Type": "application/x-www-form-urlencoded" },
			  body: new URLSearchParams({ action: "delete_option", option_id: String(optionId) })
			})
			  .then(res => res.json())
			  .then(data => {
				pageLoader.style.display = "none";
				if (data.success) {
				  alert("Option deleted.");
				  editOptionModal.style.display = "none";
				  location.reload();
				} else {
				  alert(data.msg || "Cannot delete this option.");
				}
			  })
			  .catch(() => {
				pageLoader.style.display = "none";
				alert("Error deleting option.");
			  });
		  }

		  document.addEventListener("click", function (e) {
			if (e.target.classList.contains("delete_option")) {
			  const optionId = e.target.dataset.optionid;
			  if (!confirm("Delete this option? It will be removed from the list if it is not used on any product.")) return;
			  deleteOptionById(optionId);
			}
		  });

		  document.getElementById("deleteOptionFromModal").addEventListener("click", function () {
			const hidden = editOptionForm.querySelector("input[name='option_id']");
			const optionId = hidden ? hidden.value : "";
			if (!optionId) {
			  alert("Open an option to edit first.");
			  return;
			}
			if (!confirm("Delete this option? It will be removed from the list if it is not used on any product.")) return;
			deleteOptionById(optionId);
		  });

		  // =============================
		  // Save Edit Option
		  editOptionForm.addEventListener("submit", function (e) {
			e.preventDefault();
			const formData = new FormData(editOptionForm);
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
				  editOptionModal.style.display = "none";
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

		  // =============================
		  // Save Add New Option
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
				  alert("New option added!");
				  addOptionModal.style.display = "none";
				  location.reload();
				} else {
				  alert(data.msg);
				}
			  })
			  .catch(() => {
				pageLoader.style.display = "none";
				alert("Error adding option");
			  });
		  });

		  // =============================
		  // Open Add New Option Modal (attach to some button)
		  document.addEventListener("click", function (e) {
			if (e.target.id === "openAddOption") {
			  addOptionModal.style.display = "block";
			}
		  });

		});



	</script>
	
	<style>
		.value-drag-handle {
			cursor: grab;
			color: #aaa;
			font-size: 18px;
			padding: 0 6px;
			flex-shrink: 0;
			user-select: none;
		}
		.value-drag-handle:active { cursor: grabbing; }
		.sortable-ghost { opacity: 0.4; background: #e3f0fb; border-radius: 6px; }

		/* Modal overlay */
		.modal-overlay {
		  display: none; /* shown via JS */
		  position: fixed;
		  top: 0;
		  left: 0;
		  right: 0;
		  bottom: 0;
		  background: rgba(0, 0, 0, 0.6);
		  z-index: 1000;
		  display: flex;
		  align-items: center;
		  justify-content: center;
		  padding: 20px; /* some breathing space */
		  overflow-y: auto; /* allow scrolling if modal taller than viewport */
		}

		.modal {
		  background: #fff;
		  border-radius: 8px;
		  padding: 20px;
		  width: 95%;
		  max-width: 800px;
		  max-height: 90vh; /* prevent overflowing off screen */
		  overflow-y: auto; /* inner scroll */
		  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
			position: absolute;
			bottom: 0;
			top: 0;
			height: max-content;
			margin: auto;
			right: 0;
			left: 0;
		}


		/* Modal heading */
		.modal h2 {
			margin: 0 0 25px;
			font-size: 20px;
			font-weight: 600;
			color: #333;
			text-align: center;
		}
		form label {
			display: block;
			margin: 0 0 5px;
			font-size: 14px;
		}

		form input,
		form select {
			width: 100%;
			height: 38px;
			border-radius: 5px;
			border: 1px solid #dedede;
			padding: 5px 10px;
			margin-bottom: 10px;
		}
		/* Form wrapper for values */
		#optionValuesWrapper {
		  display: flex;
		  flex-direction: column;
		  gap: 10px;
		  margin-bottom: 15px;
		}

		.option-value-row {
		  display: flex;
		  align-items: center;
		  gap: 10px;
		}

		.default-value-radio {
		  flex-shrink: 0;
		  display: flex;
		  align-items: center;
		  margin: 0;
		  cursor: pointer;
		}
		.default-value-radio input[type="radio"] {
		  width: auto;
		  height: auto;
		  margin: 0 4px 0 0;
		  flex: none;
		}

		.option-value-row input {
		  flex: 1;
		  padding: 8px 10px;
		  border: 1px solid #ccc;
		  border-radius: 6px;
		  font-size: 14px;
		}

		.option-value-row .remove-value {
		  background: #dc3545;
		  color: #fff;
		  border: none;
		  border-radius: 6px;
		  cursor: pointer;
		  padding: 6px 10px;
		  font-size: 14px;
		  transition: background 0.2s ease;
		}

		.option-value-row .remove-value:hover {
		  background: #b02a37;
		}

		/* Buttons */
		.modal button {
		  cursor: pointer;
		  padding: 10px 18px;
		  border-radius: 6px;
		  border: none;
		  font-size: 14px;
		}
		.option-row-actions {
			display: flex;
			flex-wrap: wrap;
			align-items: center;
			gap: 8px;
		}
		button.edit_option,
		button.delete_option {
			min-width: 72px;
			height: 28px;
			border-radius: 5px;
			border: none;
			cursor: pointer;
			font-size: 12px;
		}
		button.edit_option {
			background: #dedede;
			color: #000;
		}
		button.delete_option {
			background: #dc3545;
			color: #fff;
		}
		button.delete_option:hover {
			background: #c82333;
		}
		.btn-delete-option {
			padding: 8px 14px !important;
			border-radius: 6px;
			border: none;
			cursor: pointer;
			font-size: 14px;
		}
		.btn-delete-option:hover {
			background: #c82333 !important;
		}
		form.product-search input[type="text"] {
			margin: 0 4px 0 0;
			height: 45px;
		}
		#addValueBtn {
		  background: #1976D2;
		  color: #fff;
		  margin-bottom: 15px;
		}

		#addValueBtn:hover {
		  background: #0056b3;
		}

		.close-modal {
		  background: #6c757d;
		  color: #fff;
		  margin-top: 15px;
		}

		.close-modal:hover {
		  background: #5a6268;
		}

		/* Animation */
		@keyframes fadeInScale {
		  from { opacity: 0; transform: scale(0.95); }
		  to   { opacity: 1; transform: scale(1); }
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
		  border-top-color: #1976D2;
		  border-radius: 50%;
		  animation: spin 0.8s linear infinite;
		}

		@keyframes spin {
		  to {
			transform: rotate(360deg);
		  }
		}
	</style>
</body>
</html>
