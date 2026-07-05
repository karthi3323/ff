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

// Handle add fiscal year
if($_POST && isset($_POST['add_fiscal_year'])) {
    try {
        // Deactivate all other fiscal years if this one is set to active
        if($_POST['is_active'] == 1) {
            $deactivate_query = "UPDATE ff_sch.fiscal_years SET is_active = false";
            $db->query($deactivate_query);
        }
        
        $query = "INSERT INTO ff_sch.fiscal_years (year_name, start_date, end_date, is_active) 
                 VALUES (:year_name, :start_date, :end_date, :is_active)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':year_name', $_POST['year_name']);
        $stmt->bindParam(':start_date', $_POST['start_date']);
        $stmt->bindParam(':end_date', $_POST['end_date']);
        $stmt->bindParam(':is_active', $_POST['is_active']);
        $stmt->execute();
        
        echo "<script>
                Swal.fire('Success!', 'Fiscal year added successfully!', 'success')
                    .then(() => { window.location.href = 'fiscal_year.php'; });
              </script>";
    } catch(Exception $e) {
        echo "<script>Swal.fire('Error!', 'Failed to add fiscal year: " . addslashes($e->getMessage()) . "', 'error');</script>";
    }
}

// Handle set active
if(isset($_GET['set_active'])) {
    try {
        $id = $_GET['set_active'];
        
        // Deactivate all fiscal years
        $deactivate_query = "UPDATE ff_sch.fiscal_years SET is_active = false";
        $db->query($deactivate_query);
        
        // Activate selected fiscal year
        $activate_query = "UPDATE ff_sch.fiscal_years SET is_active = true WHERE id = :id";
        $stmt = $db->prepare($activate_query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        echo "<script>
                Swal.fire('Success!', 'Fiscal year activated successfully!', 'success')
                    .then(() => { window.location.href = 'fiscal_year.php'; });
              </script>";
    } catch(Exception $e) {
        echo "<script>Swal.fire('Error!', 'Failed to activate fiscal year: " . addslashes($e->getMessage()) . "', 'error');</script>";
    }
}

// Fetch fiscal years
$fiscal_years = $db->query("SELECT * FROM ff_sch.fiscal_years ORDER BY is_active DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Fiscal Year - <?php echo SITE_NAME; ?></title>
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
            <h2 class="mb-4">Fiscal Year Management</h2>

            <div class="row">
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title">Add New Fiscal Year</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Year Name *</label>
                                    <input type="text" class="form-control" name="year_name" placeholder="e.g., 2024-25" required autofocus>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Start Date *</label>
                                    <input type="date" class="form-control" name="start_date" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">End Date *</label>
                                    <input type="date" class="form-control" name="end_date" required>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active">
                                        <label class="form-check-label" for="is_active">
                                            Set as Active Fiscal Year
                                        </label>
                                    </div>
                                </div>
                                <button type="submit" name="add_fiscal_year" class="btn btn-primary w-100">
                                    <i class="fas fa-plus me-2"></i>Add Fiscal Year
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Fiscal Years</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-striped data-table">
                                <thead>
                                    <tr>
                                        <th>Year Name</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($fiscal_years as $year): ?>
                                    <tr>
                                        <td><?php echo $year['year_name']; ?></td>
                                        <td><?php echo date('d M Y', strtotime($year['start_date'])); ?></td>
                                        <td><?php echo date('d M Y', strtotime($year['end_date'])); ?></td>
                                        <td>
                                            <?php if($year['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if(!$year['is_active']): ?>
                                                <a href="fiscal_year.php?set_active=<?php echo $year['id']; ?>" 
                                                   class="btn btn-sm btn-success"
                                                   onclick="return confirm('Are you sure you want to activate this fiscal year?')">
                                                    <i class="fas fa-check"></i> Activate
                                                </a>
                                            <?php endif; ?>
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
    <script>
        $(document).keydown(function (e) {
            // Ctrl+S (Windows/Linux) or Cmd+S (Mac)
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                e.preventDefault(); // stop browser save
                $('.fa-plus').trigger('click');
            }

        });
    </script>
</body>
</html>