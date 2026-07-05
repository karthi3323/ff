<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";
require_once "../../includes/audit_helper.php";

// Set the current user for audit logging
// setAuditUser($_SESSION['user_id']);

$database = new Database();
$db = $database->getConnection();

// Fetch categories
$categories = $db->query("SELECT * FROM ff_sch.product_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch fiscal years
$fiscal_years = $db->query("SELECT * FROM ff_sch.fiscal_years where is_active = true");
$year_query = $fiscal_years->fetch(PDO::FETCH_ASSOC);
$act_year_id = $year_query['id'];
$act_year = date('Y',strtotime($year_query['start_date']));

// Max Prod ID
$prod_query = $db->query("SELECT COUNT(product_id) as count FROM ff_sch.products");
$product = $prod_query->fetch(PDO::FETCH_ASSOC);
$max_id = $product['count'] + 1;

// Generate Product ID with zero padding (5 digits total)
$padded_max_id = str_pad($max_id, 5, '0', STR_PAD_LEFT);
$product_id = "PROD" . "-" . $padded_max_id;

if(isset($_POST['category_id'])){
    $cat = $_POST['category_id'];
} else {
    $cat = '1';
} 

if(isset($_POST['uom'])){
    $uom = $_POST['uom'];
} else {
    $uom = 'PKT';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/img/favicon.png">
    <link href="<?php echo ASSETS_URL; ?>/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    <script src="<?php echo ASSETS_URL; ?>/js/sweetalert2@11.js"></script>
</head>
<body>
    <?php include "../../includes/header.php"; ?>
    <?php include "../../includes/sidebar.php"; ?>
    <?php 
    
        // Handle form submission
        if($_POST && isset($_POST['name'])) {
            try {
                $query = "INSERT INTO ff_sch.products (product_id, name, rate, carton_contents, uom, per_box_pieces, category_id, fiscal_year_id) 
                        VALUES (:product_id, :name, :rate, :carton_contents, :uom, :per_box_pieces, :category_id, :fiscal_year_id)";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':product_id', $product_id);
                $stmt->bindParam(':name', $_POST['name']);
                $stmt->bindParam(':rate', $_POST['rate']);
                $stmt->bindParam(':carton_contents', $_POST['carton_contents']);
                $stmt->bindParam(':uom', $uom);
                $stmt->bindParam(':per_box_pieces', $_POST['per_box_pieces']);
                $stmt->bindParam(':category_id', $cat);
                $stmt->bindValue(':fiscal_year_id', $act_year_id);
                if($stmt->execute()) {
                    
                    echo "<script>
                            Swal.fire('Success!', 'Product added successfully!', 'success')
                                .then(() => { window.location.href = 'list.php'; });
                        </script>";
                } else {
                    throw new Exception("Execute failed");
                }
                
            } catch(Exception $e) {
                print_r($e->getMessage());
                error_log("Product insert error: " . $e->getMessage());
                echo "<script>Swal.fire('Error!', 'Failed to add product. Please try again.', 'error');</script>";
            }
        }
    ?>
    <!-- <main class="main-content"> -->
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Add New Product</h2>
                <a href="list.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to List
                </a>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Product Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3" style="display:none;">
                                <label class="form-label">Product ID</label>
                                <input type="text" class="form-control" name="product_id" value="<?php echo $product_id; ?>" readonly>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Product Name *</label>
                                <input type="text" class="form-control" name="name" required autofocus>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Rate (<?php echo CURRENCY; ?>) *</label>
                                <input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control" name="rate" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Per Box/Packet Pieces</label>
                                <input type="text" class="form-control" name="per_box_pieces">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Case Contents</label>
                                <input type="text" class="form-control" name="carton_contents">
                            </div>
                            
                        </div>
                        
                        <!-- <div class="row" style="display: none;">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">UOM *</label>
                                <select class="form-select" name="uom" required>
                                    <option value="">Select UOM</option>
                                    <option value="PCS">Pieces</option>
                                    <option value="BOX">Box</option>
                                    <option value="PKT">Packet</option>
                                    <option value="UNT">Unit</option>
                                </select>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Category *</label>
                                <select class="form-select" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>">
                                            <?php echo $category['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div> -->  
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Save Product
                            </button>
                            <!-- <button type="reset" class="btn btn-secondary btn-lg">
                                <i class="fas fa-redo me-2"></i>Reset
                            </button> -->
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </main>

    <script src="<?php echo ASSETS_URL; ?>/js/jquery-3.6.0.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/sidebar.js"></script>
    <script>
        $(document).keydown(function (e) {
            // Ctrl+S (Windows/Linux) or Cmd+S (Mac)
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                e.preventDefault(); // stop browser save
                $('.fa-save').trigger('click');
            }

            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'c') {
                e.preventDefault(); // stop browser save
                $('.fa-arrow-left').trigger('click');
            }

        });
    </script>
</body>
</html>