<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";

$database = new Database();
$db = $database->getConnection();

// Handle add price code
if ($_POST && isset($_POST['add_price_code'])) {
    try {
        $query = "INSERT INTO ff_sch.price_codes (code, name, fiscal_year_id, is_active) 
                  VALUES (:code, :name, :fiscal_year_id, :is_active)";
        $stmt = $db->prepare($query);

        $is_active = isset($_POST['is_active']) ? true : false;

        $stmt->bindParam(':code', $_POST['code']);
        $stmt->bindParam(':name', $_POST['name']);
        $stmt->bindParam(':fiscal_year_id', $_POST['fiscal_year_id'], PDO::PARAM_INT);
        $stmt->bindParam(':is_active', $is_active, PDO::PARAM_BOOL);
        $stmt->execute();

        echo "<script>
                Swal.fire('Success!', 'Price Code added successfully!', 'success')
                    .then(() => { window.location.href = 'price_code.php'; });
              </script>";
    } catch (Exception $e) {
        // Check for unique constraint violation
        if (strpos($e->getMessage(), 'uq_price_codes_fiscal_year_id_code') !== false) {
            echo "<script>Swal.fire('Error!', 'This price code already exists for the selected fiscal year.', 'error');</script>";
        } else {
            echo "<script>Swal.fire('Error!', 'Failed to add price code: " . addslashes($e->getMessage()) . "', 'error');</script>";
        }
    }
}

// Handle toggle active status
if (isset($_GET['toggle_active'])) {
    try {
        $id = $_GET['toggle_active'];
        $query = "UPDATE ff_sch.price_codes SET is_active = NOT is_active, updated_at = CURRENT_TIMESTAMP WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        echo "<script>
                Swal.fire('Success!', 'Price Code status updated!', 'success')
                    .then(() => { window.location.href = 'price_code.php'; });
              </script>";
    } catch (Exception $e) {
        echo "<script>Swal.fire('Error!', 'Failed to update price code: " . addslashes($e->getMessage()) . "', 'error');</script>";
    }
}

// Fetch fiscal years for dropdown
$fiscal_years = $db->query("SELECT id, year_name FROM ff_sch.fiscal_years ORDER BY is_active DESC, year_name DESC")->fetchAll(PDO::FETCH_ASSOC);

// Fetch price codes
$price_codes = $db->query("SELECT pc.*, fy.year_name 
                           FROM ff_sch.price_codes pc
                           JOIN ff_sch.fiscal_years fy ON pc.fiscal_year_id = fy.id
                           ORDER BY fy.year_name DESC, pc.is_active DESC, pc.code ASC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Price Code Master - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/img/favicon.png">
    <link href="<?php echo ASSETS_URL; ?>/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include "../../includes/header.php"; ?>
    <?php include "../../includes/sidebar.php"; ?>

    <div class="container-fluid">
        <h2 class="mb-4">Price Code Management</h2>

        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title">Add New Price Code</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Fiscal Year *</label>
                                <select class="form-select" name="fiscal_year_id" required>
                                    <option value="">-- Select Fiscal Year --</option>
                                    <?php foreach ($fiscal_years as $year) : ?>
                                        <option value="<?php echo $year['id']; ?>"><?php echo htmlspecialchars($year['year_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Price Code *</label>
                                <input type="text" class="form-control" name="code" placeholder="e.g., RETAIL-24" required autofocus>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Price Name *</label>
                                <input type="text" class="form-control" name="name" placeholder="e.g., Retail Price 2024-25" required>
                            </div>
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active" checked>
                                    <label class="form-check-label" for="is_active">
                                        Set as Active
                                    </label>
                                </div>
                            </div>
                            <button type="submit" name="add_price_code" class="btn btn-primary w-100">
                                <i class="fas fa-plus me-2"></i>Add Price Code
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Price Codes</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-striped data-table">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Name</th>
                                    <th>Fiscal Year</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($price_codes as $pc) : ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($pc['code']); ?></td>
                                        <td><?php echo htmlspecialchars($pc['name']); ?></td>
                                        <td><?php echo htmlspecialchars($pc['year_name']); ?></td>
                                        <td>
                                            <?php if ($pc['is_active']) : ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else : ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="price_code.php?toggle_active=<?php echo $pc['id']; ?>" class="btn btn-sm btn-<?php echo $pc['is_active'] ? 'warning' : 'success'; ?>" title="<?php echo $pc['is_active'] ? 'Deactivate' : 'Activate'; ?>" onclick="return confirm('Are you sure you want to toggle the status of this price code?')">
                                                <i class="fas fa-<?php echo $pc['is_active'] ? 'toggle-off' : 'toggle-on'; ?>"></i>
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
</body>
</html>