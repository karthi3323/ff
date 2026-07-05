<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";
require_once "../../includes/audit_helper.php";

// Set the current user for audit logging
//setAuditUser($_SESSION['user_id']);


$database = new Database();
$db = $database->getConnection();

// Handle add category
if($_POST && isset($_POST['add_category'])) {
    try {
        $query = "INSERT INTO ff_sch.product_categories (name, description) VALUES (:name, :description)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $_POST['name']);
        $stmt->bindParam(':description', $_POST['description']);
        $stmt->execute();
        
        echo "<script>
                Swal.fire('Success!', 'Category added successfully!', 'success')
                    .then(() => { window.location.href = 'category.php'; });
              </script>";
    } catch(Exception $e) {
        echo "<script>Swal.fire('Error!', 'Failed to add category: " . addslashes($e->getMessage()) . "', 'error');</script>";
    }
}

// Handle delete category
if(isset($_GET['delete_id'])) {
    try {
        // Check if category is used in products
        $check_query = "SELECT COUNT(*) FROM ff_sch.products WHERE category_id = :id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':id', $_GET['delete_id']);
        $check_stmt->execute();
        $product_count = $check_stmt->fetchColumn();
        
        if($product_count > 0) {
            echo "<script>
                    Swal.fire('Error!', 'Cannot delete category. It is used in products.', 'error')
                        .then(() => { window.location.href = 'category.php'; });
                  </script>";
        } else {
            $delete_query = "DELETE FROM ff_sch.product_categories WHERE id = :id";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->bindParam(':id', $_GET['delete_id']);
            $delete_stmt->execute();
            
            echo "<script>
                    Swal.fire('Success!', 'Category deleted successfully!', 'success')
                        .then(() => { window.location.href = 'category.php'; });
                  </script>";
        }
    } catch(Exception $e) {
        echo "<script>Swal.fire('Error!', 'Failed to delete category: " . addslashes($e->getMessage()) . "', 'error');</script>";
    }
}

// Fetch categories
$categories = $db->query("SELECT * FROM ff_sch.product_categories ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Categories - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/img/favicon.png">
    <link href="<?php echo ASSETS_URL; ?>/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include "../../includes/header.php"; ?>
    <?php include "../../includes/sidebar.php"; ?>

    <!-- <main class="main-content"> -->
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Product Categories</h2>
                <a href="list.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Products
                </a>
            </div>

            <div class="row">
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title">Add New Category</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Category Name *</label>
                                    <input type="text" class="form-control" name="name" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Description</label>
                                    <textarea class="form-control" name="description" rows="3"></textarea>
                                </div>
                                <button type="submit" name="add_category" class="btn btn-primary w-100">
                                    <i class="fas fa-plus me-2"></i>Add Category
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">All Categories</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-striped data-table">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Description</th>
                                        <th>Created Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($categories as $category): ?>
                                    <tr>
                                        <td><?php echo $category['name']; ?></td>
                                        <td><?php echo $category['description'] ?: 'N/A'; ?></td>
                                        <td><?php echo date('d M Y', strtotime($category['created_at'])); ?></td>
                                        <td>
                                            <button onclick="confirmDelete('Are you sure you want to delete this category?', 'category.php?delete_id=<?php echo $category['id']; ?>')" 
                                                    class="btn btn-sm btn-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
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
    </main>

    <script src="<?php echo ASSETS_URL; ?>/js/jquery-3.6.0.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/jquery.dataTables.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/dataTables.bootstrap5.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/sweetalert2@11.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/script.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/sidebar.js"></script>
</body>
</html>