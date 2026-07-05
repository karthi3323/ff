<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";
require_once "../../includes/audit_helper.php";



$database = new Database();
$db = $database->getConnection();

// Set the current user for audit logging
//setAuditUser($_SESSION['user_id']);

if(!isset($_GET['id'])) {
    header("Location: " . BASE_URL . "modules/products/list.php");
    exit();
}

$id = $_GET['id'];

// Fetch categories
$categories = $db->query("SELECT * FROM ff_sch.product_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Fetch product data
$query = "SELECT * FROM ff_sch.products WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();
$product = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$product) {
    header("Location: " . BASE_URL . "modules/products/list.php");
    exit();
}

if(isset($_POST['category_id'])){
    $cat = $_POST['category_id'];
} else {
    $cat = '1';
} 

if(isset($_POST['uom'])){
    $uom = $_POST['uom'];
} else {
    $uom = 'PKT';
}?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - <?php echo SITE_NAME; ?></title>
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
        // print_r($_SESSION);
        if($_POST) {
            try {
                $query = "UPDATE ff_sch.products SET name = :name, rate = :rate, carton_contents = :carton_contents, 
                        uom = :uom, per_box_pieces = :per_box_pieces, category_id = :category_id 
                        WHERE id = :id";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':name', $_POST['name']);
                $stmt->bindParam(':rate', $_POST['rate']);
                $stmt->bindParam(':carton_contents', $_POST['carton_contents']);
                $stmt->bindParam(':uom', $uom);
                $stmt->bindParam(':per_box_pieces', $_POST['per_box_pieces']);
                $stmt->bindParam(':category_id', $cat);
                $stmt->bindParam(':id', $id);
                
                $stmt->execute();
                
                echo "<script>
                        Swal.fire('Success!', 'Product updated successfully!', 'success')
                            .then(() => { window.location.href = 'list.php'; });
                    </script>";
                
            } catch(Exception $e) {
                echo "<script>Swal.fire('Error!', 'Failed to update product: " . addslashes($e->getMessage()) . "', 'error');</script>";
            }
        }
    ?>

    <!-- <main class="main-content"> -->
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Edit Product</h2>
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
                                <input type="text" class="form-control" value="<?php echo $product['product_id']; ?>" readonly>
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Product Name *</label>
                                <input type="text" class="form-control" name="name" value="<?php echo $product['name']; ?>" required autofocus>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Rate (<?php echo CURRENCY; ?>) *</label>
                                <input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control" name="rate" value="<?php echo $product['rate']; ?>" step="0.01" min="0" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Per</label>
                                <input type="text" class="form-control" name="per_box_pieces" value="<?php echo $product['per_box_pieces']; ?>" min="1">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Case Contents</label>
                                <input type="text" class="form-control" name="carton_contents" value="<?php echo $product['carton_contents']; ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            
                            <!-- <div class="col-md-6 mb-3">
                                <label class="form-label">UOM *</label>
                                <select class="form-select" name="uom" required>
                                    <option value="">Select UOM</option>
                                    <option value="PCS" <?php echo $product['uom'] == 'PCS' ? 'selected' : ''; ?>>Pieces</option>
                                    <option value="BOX" <?php echo $product['uom'] == 'BOX' ? 'selected' : ''; ?>>Box</option>
                                    <option value="PKT" <?php echo $product['uom'] == 'PKT' ? 'selected' : ''; ?>>Packet</option>
                                    <option value="UNT" <?php echo $product['uom'] == 'UNT' ? 'selected' : ''; ?>>Unit</option>   
                                </select>
                            </div> -->
                            <!-- <div class="col-md-6 mb-3">
                                <label class="form-label">Category</label>
                                <select class="form-select" name="category_id">
                                    <option value="">Select Category</option>
                                    <?php foreach($categories as $category): ?>
                                        <option value="<?php echo $category['id']; ?>" 
                                            <?php echo $product['category_id'] == $category['id'] ? 'selected' : ''; ?>>
                                            <?php echo $category['name']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div> -->
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Update Product
                            </button>
                            <a href="list.php" class="btn btn-secondary btn-lg">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
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