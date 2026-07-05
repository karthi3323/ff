<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";

$database = new Database();
$db = $database->getConnection();

// Handle add coupon
if($_POST && isset($_POST['add_coupon'])) {
    try {
        $query = "INSERT INTO ff_sch.coupons (code, discount_type, value, min_amount, max_usage, is_active, valid_from, valid_to, created_by) 
                 VALUES (:code, :discount_type, :value, :min_amount, :max_usage, :is_active, :valid_from, :valid_to, :created_by)";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':code', $_POST['code']);
        $stmt->bindParam(':discount_type', $_POST['discount_type']);
        $stmt->bindParam(':value', $_POST['value']);
        $stmt->bindParam(':min_amount', $_POST['min_amount']);
        $stmt->bindParam(':max_usage', $_POST['max_usage']);
        $stmt->bindParam(':is_active', $_POST['is_active']);
        $stmt->bindParam(':valid_from', $_POST['valid_from']);
        $stmt->bindParam(':valid_to', $_POST['valid_to']);
        $stmt->bindParam(':created_by', $_SESSION['user_id']);
        
        $stmt->execute();
        
        echo "<script>
                Swal.fire('Success!', 'Coupon added successfully!', 'success')
                    .then(() => { window.location.href = 'coupon.php'; });
              </script>";
        
    } catch(Exception $e) {
        echo "<script>Swal.fire('Error!', 'Failed to add coupon: " . addslashes($e->getMessage()) . "', 'error');</script>";
    }
}

// Handle toggle active status
if(isset($_GET['toggle_active'])) {
    try {
        $id = $_GET['toggle_active'];
        $query = "UPDATE ff_sch.coupons SET is_active = NOT is_active WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        echo "<script>
                Swal.fire('Success!', 'Coupon status updated!', 'success')
                    .then(() => { window.location.href = 'coupon.php'; });
              </script>";
    } catch(Exception $e) {
        echo "<script>Swal.fire('Error!', 'Failed to update coupon: " . addslashes($e->getMessage()) . "', 'error');</script>";
    }
}

// Fetch coupons
$coupons = $db->query("SELECT c.*, u.full_name as created_by_name 
                       FROM ff_sch.coupons c 
                       LEFT JOIN ff_sch.users u ON c.created_by = u.id 
                       ORDER BY c.created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coupon Management - <?php echo SITE_NAME; ?></title>
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
            <h2 class="mb-4">Coupon Management</h2>

            <div class="row">
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title">Add New Coupon</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Coupon Code *</label>
                                    <input type="text" class="form-control" name="code" required style="text-transform: uppercase;">
                                    <small class="text-muted">Unique code for the coupon</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Discount Type *</label>
                                    <select class="form-select" name="discount_type" required>
                                        <option value="percentage">Percentage (%)</option>
                                        <option value="fixed">Fixed Amount (<?php echo CURRENCY; ?>)</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Value *</label>
                                    <input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control" name="value" step="0.01" min="0" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Minimum Amount (<?php echo CURRENCY; ?>)</label>
                                    <input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control" name="min_amount" step="0.01" min="0" value="0">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Maximum Usage</label>
                                    <input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control" name="max_usage" min="1" value="1">
                                    <small class="text-muted">How many times this coupon can be used</small>
                                </div>
                                
                                <div class="row mb-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Valid From</label>
                                        <input type="date" class="form-control" name="valid_from">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Valid To</label>
                                        <input type="date" class="form-control" name="valid_to">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" checked>
                                        <label class="form-check-label" for="is_active">
                                            Active
                                        </label>
                                    </div>
                                </div>
                                
                                <button type="submit" name="add_coupon" class="btn btn-primary w-100">
                                    <i class="fas fa-plus me-2"></i>Add Coupon
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">All Coupons</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-striped data-table">
                                <thead>
                                    <tr>
                                        <th>Code</th>
                                        <th>Type</th>
                                        <th>Value</th>
                                        <th>Min Amount</th>
                                        <th>Usage</th>
                                        <th>Valid Until</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($coupons as $coupon): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo $coupon['code']; ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $coupon['discount_type'] === 'percentage' ? 'primary' : 'success'; ?>">
                                                <?php echo ucfirst($coupon['discount_type']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if($coupon['discount_type'] === 'percentage'): ?>
                                                <?php echo $coupon['value']; ?>%
                                            <?php else: ?>
                                                <?php echo CURRENCY . number_format($coupon['value'], 2); ?>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo CURRENCY . number_format($coupon['min_amount'], 2); ?></td>
                                        <td>
                                            <?php echo $coupon['used_count']; ?>/<?php echo $coupon['max_usage']; ?>
                                        </td>
                                        <td>
                                            <?php echo $coupon['valid_to'] ? date('d M Y', strtotime($coupon['valid_to'])) : 'No limit'; ?>
                                        </td>
                                        <td>
                                            <?php if($coupon['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="coupon.php?toggle_active=<?php echo $coupon['id']; ?>" 
                                               class="btn btn-sm btn-<?php echo $coupon['is_active'] ? 'warning' : 'success'; ?>"
                                               title="<?php echo $coupon['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                                <i class="fas fa-<?php echo $coupon['is_active'] ? 'pause' : 'play'; ?>"></i>
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
    </main>

    <script src="<?php echo ASSETS_URL; ?>/js/jquery-3.6.0.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/jquery.dataTables.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/dataTables.bootstrap5.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/sweetalert2@11.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/script.js"></script>
    
    <script>
        // Auto-uppercase coupon code
        document.querySelector('input[name="code"]').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase();
        });
    </script>
    <script src="<?php echo ASSETS_URL; ?>/js/sidebar.js"></script>
</body>
</html>