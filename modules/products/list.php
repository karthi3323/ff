<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";

$database = new Database();
$db = $database->getConnection();

$edit_mode = false;
$product_data = [];
$product_prices = [];


// Max Prod ID
$prod_query = $db->query("SELECT COUNT(product_id) as count FROM ff_sch.products");
$product = $prod_query->fetch(PDO::FETCH_ASSOC);
$max_id = $product['count'] + 1;

// Generate Product ID with zero padding (5 digits total)
$padded_max_id = str_pad($max_id, 5, '0', STR_PAD_LEFT);
$product_id = "PROD" . "-" . $padded_max_id;

// Fetch data for page
$products = $db->query("SELECT p.*, c.name as category_name, fy.year_name FROM ff_sch.products p LEFT JOIN ff_sch.product_categories c ON p.category_id = c.id LEFT JOIN ff_sch.fiscal_years fy ON p.fiscal_year_id = fy.id ORDER BY p.is_active DESC, p.name ASC")->fetchAll(PDO::FETCH_ASSOC);
$fiscal_years = $db->query("SELECT id, year_name FROM ff_sch.fiscal_years ORDER BY is_active DESC, year_name DESC")->fetchAll(PDO::FETCH_ASSOC);
$categories = $db->query("SELECT id, name FROM ff_sch.product_categories ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/img/favicon.png">
    <link href="<?php echo ASSETS_URL; ?>/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="<?php echo ASSETS_URL; ?>/css/select2.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/select2-bootstrap4.min.css" rel="stylesheet" />
</head>
<body>
    <?php include "../../includes/header.php"; ?>
    <?php include "../../includes/sidebar.php"; ?>
    <?php 
        
        // Handle Edit Mode
        if (isset($_GET['edit_id'])) {
            $edit_mode = true;
            $product_id = $_GET['edit_id'];

            // Fetch product data
            $stmt = $db->prepare("SELECT * FROM ff_sch.products WHERE id = :id");
            $stmt->bindParam(':id', $product_id, PDO::PARAM_INT);
            $stmt->execute();
            $product_data = $stmt->fetch(PDO::FETCH_ASSOC);

            // Fetch product prices
            $stmt = $db->prepare("SELECT price_code_id, rate FROM ff_sch.product_prices WHERE product_id = :product_id");
            $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
            $stmt->execute();
            $prices_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($prices_raw as $price) {
                $product_prices[$price['price_code_id']] = $price['rate'];
            }
        }

        // Handle Add/Update Product
        if ($_POST && (isset($_POST['add_product']) || isset($_POST['update_product']))) {
            $db->beginTransaction();
            try {
                $is_active = isset($_POST['is_active']) ? true : false;
                $_POST['carton_contents'] = 0;

                if (isset($_POST['update_product'])) { // UPDATE
                    $product_id = $_POST['product_id'];
                    $query = "UPDATE ff_sch.products SET product_id=:product_id_str, name=:name, uom=:uom, per_box_pieces=:per_box_pieces, carton_contents=:carton_contents, category_id=:category_id, is_active=:is_active, fiscal_year_id=:fiscal_year_id WHERE id=:id";
                    $stmt = $db->prepare($query);
                    $stmt->bindParam(':id', $product_id, PDO::PARAM_INT);
                } else { // INSERT
                    $query = "INSERT INTO ff_sch.products (product_id, name, uom, per_box_pieces, carton_contents, category_id, is_active, fiscal_year_id) VALUES (:product_id_str, :name, :uom, :per_box_pieces, :carton_contents, :category_id, :is_active, :fiscal_year_id)";
                    $stmt = $db->prepare($query);
                }

                $stmt->bindParam(':product_id_str', $_POST['product_id_str']);
                $stmt->bindParam(':name', $_POST['name']);
                $stmt->bindParam(':uom', $_POST['uom']);
                $stmt->bindParam(':per_box_pieces', $_POST['per_box_pieces']);
                $stmt->bindParam(':carton_contents', $_POST['carton_contents']);
                $stmt->bindParam(':category_id', $_POST['category_id'], PDO::PARAM_INT);
                $stmt->bindParam(':is_active', $is_active, PDO::PARAM_BOOL);
                $stmt->bindParam(':fiscal_year_id', $_POST['fiscal_year_id'], PDO::PARAM_INT);
                $stmt->execute();

                if (!isset($_POST['update_product'])) {
                    $product_id = $db->lastInsertId();
                }

                // UPSERT prices
                if (isset($_POST['prices']) && is_array($_POST['prices'])) {
                    $price_query = "INSERT INTO ff_sch.product_prices (product_id, price_code_id, rate) VALUES (:product_id, :price_code_id, :rate) ON CONFLICT (product_id, price_code_id) DO UPDATE SET rate = EXCLUDED.rate";
                    $price_stmt = $db->prepare($price_query);

                    foreach ($_POST['prices'] as $price_code_id => $rate) {
                        if (is_numeric($rate) && $rate >= 0) {
                            $price_stmt->execute([
                                ':product_id' => $product_id,
                                ':price_code_id' => $price_code_id,
                                ':rate' => $rate
                            ]);
                        }
                    }
                }

                $db->commit();
                $action = isset($_POST['update_product']) ? 'updated' : 'added';
                echo "<script>
                        Swal.fire('Success!', 'Product {$action} successfully!', 'success')
                            .then(() => { window.location.href = 'list.php'; });
                    </script>";
            } catch (Exception $e) {
                $db->rollBack();
                $errorMsg = addslashes(str_replace(["\r", "\n"], ' ', $e->getMessage()));
                    echo "<script>Swal.fire('Error!', 'Failed to save product: " . $errorMsg . "', 'error');</script>";
            }
        }
    ?>
    <div class="container-fluid">
        <h2 class="mb-4">Product Management</h2>

        <div class="row">
            <!-- Add/Edit Form -->
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title"><?php echo $edit_mode ? 'Edit Product' : 'Add New Product'; ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?php if ($edit_mode) : ?>
                                <input type="hidden" name="product_id" value="<?php echo $product_data['id']; ?>">
                            <?php endif; ?>

                            <div class="mb-3">
                                <label class="form-label">Fiscal Year *</label>
                                <select class="form-select" name="fiscal_year_id" id="fiscal_year_id" required>
                                    <option value="">-- Select --</option>
                                    <?php foreach ($fiscal_years as $year) : ?>
                                        <option value="<?php echo $year['id']; ?>" <?php echo ($edit_mode && $product_data['fiscal_year_id'] == $year['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($year['year_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3 d-none">
                                <label class="form-label">Product ID *</label>
                                <input type="text" class="form-control" name="product_id_str" value="<?php echo $product_data['product_id'] ?? $product_id; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Product Name *</label>
                                <input type="text" class="form-control" name="name" value="<?php echo $product_data['name'] ?? ''; ?>" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">UOM *</label>
                                    <!-- <input type="text" class="form-control" name="uom" value="<?php echo $product_data['uom'] ?? ''; ?>" required> -->
                                    <select class="form-select uom-select" name="uom">
                                        <option value="">-- Select UOM --</option>
                                        <option value="BOX" <?php echo (isset($product_data['uom']) && $product_data['uom'] == 'BOX') ? 'selected' : ''; ?>>Box</option>
                                        <option value="PCS" <?php echo (isset($product_data['uom']) && $product_data['uom'] == 'PCS') ? 'selected' : ''; ?>>Pieces</option>
                                        <option value="PKT" <?php echo (isset($product_data['uom']) && $product_data['uom'] == 'PKT') ? 'selected' : ''; ?>>Packets</option>
                                        <option value="CTN" <?php echo (isset($product_data['uom']) && $product_data['uom'] == 'CTN') ? 'selected' : ''; ?>>Carton</option>
                                        <option value="CASE" <?php echo (isset($product_data['uom']) && $product_data['uom'] == 'CASE') ? 'selected' : ''; ?>>Case</option>
                                        <option value="BAG" <?php echo (isset($product_data['uom']) && $product_data['uom'] == 'BAG') ? 'selected' : ''; ?>>Bag</option>
                                        <option value="OTHER" <?php echo (isset($product_data['uom']) && $product_data['uom'] == 'OTHER') ? 'selected' : ''; ?>>Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Pieces Per Box</label>
                                    <input type="text" class="form-control" name="per_box_pieces" value="<?php echo $product_data['per_box_pieces'] ?? ''; ?>">
                                </div>
                            </div>
                            <hr>
                            <h6 class="mb-3">Product Pricing</h6>
                            <div id="price-code-inputs">
                                <p class="text-muted">Select a fiscal year to load price codes.</p>
                            </div>
                            <hr>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="is_active" id="is_active" value="1" <?php echo ($edit_mode ? ($product_data['is_active'] ? 'checked' : '') : 'checked'); ?>>
                                <label class="form-check-label" for="is_active">Product is Active</label>
                            </div>

                            <?php if ($edit_mode) : ?>
                                <button type="submit" name="update_product" class="btn btn-success w-100"><i class="fas fa-save me-2"></i>Update Product</button>
                                <a href="list.php" class="btn btn-secondary w-100 mt-2">Cancel Edit</a>
                            <?php else : ?>
                                <button type="submit" name="add_product" class="btn btn-primary w-100"><i class="fas fa-plus me-2"></i>Add Product</button>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Product List -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header"><h5 class="card-title">Product List</h5></div>
                    <div class="card-body">
                        <table class="table table-striped data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>UOM</th>
                                    <th>Per Box Pieces</th>
                                    <th>Fiscal Year</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $prod) : 
                                        if($prod['uom'] == 'BOX'){
                                            $uom_display = 'Box';
                                        } elseif($prod['uom'] == 'PCS'){
                                            $uom_display = 'Pieces';
                                        } elseif($prod['uom'] == 'PKT'){
                                            $uom_display = 'Packets';
                                        } elseif($prod['uom'] == 'CTN'){
                                            $uom_display = 'Carton';
                                        } elseif($prod['uom'] == 'CASE'){
                                            $uom_display = 'Case';
                                        } elseif($prod['uom'] == 'BAG'){
                                            $uom_display = 'Bag';
                                        } elseif($prod['uom'] == 'OTHER'){
                                            $uom_display = 'Other';
                                        } else {
                                            $uom_display = $prod['uom'] ?? 'N/A';
                                        } ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($prod['name']); ?></td>
                                        <td><?php echo htmlspecialchars($uom_display); ?></td>
                                        <td><?php echo htmlspecialchars($prod['per_box_pieces'] ?? 'N/A'); ?></td>
                                        <td><?php echo htmlspecialchars($prod['year_name']); ?></td>
                                        <td>
                                            <?php if ($prod['is_active']) : ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else : ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="list.php?edit_id=<?php echo $prod['id']; ?>" class="btn btn-sm btn-info" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/jquery-3.6.0.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/jquery.dataTables.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/dataTables.bootstrap5.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/sweetalert2@11.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/script.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/sidebar.js"></script>
    <!-- Select2 JS -->
    <script src="<?php echo ASSETS_URL; ?>/js/select2.min.js"></script>    
    <script>
        $(document).ready(function() {
            $('.uom-select').select2({
                theme: 'bootstrap4',
                placeholder: "Select UOM",
                allowClear: true,
                width: '100%'
             }).on('select2:open', function() {
                setTimeout(() => {
                    document.querySelector('.select2-container--open .select2-search__field').focus();
                }, 100);
            });        
            // Initialize DataTables for search, pagination, and sorting
            $('.data-table').DataTable({
                "order": [[ 2, "desc" ], [0, 'asc']], // Default sort: Active status first, then by name
                "pageLength": 15,
                "lengthMenu": [ [15, 35, 50, -1], [15, 35, 50, "All"] ],
                "language": {
                    "search": "_INPUT_",
                    "searchPlaceholder": "Search products..."
                }
            });

            const fiscalYearSelect = $('#fiscal_year_id');
            const priceCodeContainer = $('#price-code-inputs');
            const existingPrices = <?php echo json_encode($product_prices); ?>;

            function loadPriceCodes(fiscalYearId) {
                if (!fiscalYearId) {
                    priceCodeContainer.html('<p class="text-muted">Select a fiscal year to load price codes.</p>');
                    return;
                }

                priceCodeContainer.html('<p class="text-info">Loading price codes...</p>');

                $.ajax({
                    url: '<?php echo BASE_URL; ?>/includes/ajax_actions.php',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'getPriceCodes',
                        fiscal_year_id: fiscalYearId
                    },
                    success: function(response) {
                        if (response.success && response.data.length > 0) {
                            let html = '';
                            response.data.forEach(pc => {
                                const existingRate = existingPrices[pc.id] || '';
                                html += `
                                    <div class="input-group mb-2">
                                        <span class="input-group-text" style="width: 150px;">${pc.name}</span>
                                        <input type="text" onkeypress="return keyPressNumber(event,this);" step="0.01" name="prices[${pc.id}]" class="form-control" placeholder="0.00" value="${existingRate}">
                                    </div>
                                `;
                            });
                            priceCodeContainer.html(html);
                        } else {
                            priceCodeContainer.html('<p class="text-warning">No active price codes found for this fiscal year.</p>');
                        }
                    },
                    error: function() {
                        priceCodeContainer.html('<p class="text-danger">Failed to load price codes.</p>');
                    }
                });
            }

            // Load on page load if a fiscal year is selected (for edit mode)
            if (fiscalYearSelect.val()) {
                loadPriceCodes(fiscalYearSelect.val());
            }

            // Load on change
            fiscalYearSelect.on('change', function() {
                loadPriceCodes($(this).val());
            });
        });
    </script>
</body>
</html>