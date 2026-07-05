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

// Handle add Master
if($_POST && isset($_POST['add_master'])) {
    try {
        // Deactivate all other Master if this one is set to active
        if($_POST['is_active'] == 1) {
            $deactivate_query = "UPDATE ff_sch.master SET is_active = false";
            $db->query($deactivate_query);
        }
        
        $query = "INSERT INTO ff_sch.master (hsn_code, sgst, cgst, igst, is_active) 
                 VALUES (:hsn_code, :sgst, :cgst, :igst, :is_active)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':hsn_code', $_POST['hsn_code']);
        $stmt->bindParam(':sgst', $_POST['sgst']);
        $stmt->bindParam(':cgst', $_POST['cgst']);
        $stmt->bindParam(':igst', $_POST['igst']);
        $stmt->bindParam(':is_active', $_POST['is_active']);
        $stmt->execute();
        
        echo "<script>
                Swal.fire('Success!', 'Master added successfully!', 'success')
                    .then(() => { window.location.href = 'master.php'; });
              </script>";
    } catch(Exception $e) {
        echo "<script>Swal.fire('Error!', 'Failed to add master: " . addslashes($e->getMessage()) . "', 'error');</script>";
    }
}

// Handle set active
if(isset($_GET['set_active'])) {
    try {
        $id = $_GET['set_active'];
        
        // Deactivate all Masters
        $deactivate_query = "UPDATE ff_sch.master SET is_active = false";
        $db->query($deactivate_query);
        
        // Activate selected Master
        $activate_query = "UPDATE ff_sch.master SET is_active = true WHERE id = :id";
        $stmt = $db->prepare($activate_query);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        echo "<script>
                Swal.fire('Success!', 'Master activated successfully!', 'success')
                    .then(() => { window.location.href = 'master.php'; });
              </script>";
    } catch(Exception $e) {
        echo "<script>Swal.fire('Error!', 'Failed to activate master: " . addslashes($e->getMessage()) . "', 'error');</script>";
    }
}

// Fetch Master
$master = $db->query("SELECT * FROM ff_sch.master ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Common Master - <?php echo SITE_NAME; ?></title>
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
            <h2 class="mb-4">Master Management</h2>

            <div class="row">
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title">Add New Master Details</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">HSN Code</label>
                                    <input type="text" class="form-control" name="hsn_code" required autofocus>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">SGST</label>
                                    <input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control" name="sgst" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">CGST</label>
                                    <input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control" name="cgst" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">IGST</label>
                                    <input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control" name="igst" required>
                                </div>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="is_active" value="1" id="is_active">
                                        <label class="form-check-label" for="is_active">
                                            Set as Active Master
                                        </label>
                                    </div>
                                </div>
                                <button type="submit" name="add_master" class="btn btn-primary w-100">
                                    <i class="fas fa-plus me-2"></i>Add
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Master Details</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-striped data-table">
                                <thead>
                                    <tr>
                                        <th>HSN Code</th>
                                        <th>SGST</th>
                                        <th>CGST</th>
                                        <th>IGST</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($master as $data): ?>
                                    <tr>
                                        <td><?php echo $data['hsn_code']; ?></td>
                                        <td><?php echo $data['sgst']; ?></td>
                                        <td><?php echo $data['cgst']; ?></td>
                                        <td><?php echo $data['igst']; ?></td>
                                        <td>
                                            <?php if($data['is_active']): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if(!$data['is_active']): ?>
                                                <a href="master.php?set_active=<?php echo $data['id']; ?>" 
                                                   class="btn btn-sm btn-success"
                                                   onclick="return confirm('Are you sure you want to activate this Master?')">
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