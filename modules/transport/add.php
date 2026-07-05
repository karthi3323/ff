<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";
require_once "../../includes/audit_helper.php";

// Set the current user for audit logging
setAuditUser($_SESSION['user_id']);

$database = new Database();
$db = $database->getConnection();

// Max Transport ID
$transport_query = $db->query("SELECT COUNT(id) as count FROM ff_sch.transport");
$transport = $transport_query->fetch(PDO::FETCH_ASSOC);
$max_id = $transport['count'] + 1;

// Generate transport ID with zero padding (5 digits total)
$padded_max_id = str_pad($max_id, 5, '0', STR_PAD_LEFT);
$transport_id = "TRP-" . $padded_max_id;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Transport - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/img/favicon.png">
    <link href="<?php echo ASSETS_URL; ?>/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    <script src="<?php echo ASSETS_URL; ?>/js/sweetalert2@11.js"></script>
</head>
<body>
    <?php include "../../includes/header.php"; ?>
    <?php include "../../includes/sidebar.php"; ?>

    <?php if($_POST) {
        try {
            $query = "INSERT INTO ff_sch.transport (name, gst_no) 
                    VALUES (:name, :gst_no)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':name', $_POST['name']);
            $stmt->bindParam(':gst_no', $_POST['gst_no']);
            
            $stmt->execute();
            
            echo "<script>
                    Swal.fire('Success!', 'Transport added successfully!', 'success')
                        .then(() => { window.location.href = 'list.php'; });
                </script>";
            
        } catch(Exception $e) {
            echo "<script>Swal.fire('Error!', 'Failed to add transport: " . addslashes($e->getMessage()) . "', 'error');</script>";
        }
    } ?>

    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Add New Transport</h2>
            <a href="list.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to List
            </a>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Transport Information</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3" style='display: none;'>
                            <label class="form-label">Transport ID</label>
                            <input type="text" class="form-control" value="<?php echo $transport_id; ?>" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Transport Name *</label>
                            <input type="text" class="form-control" name="name" required autofocus>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">GST No</label>
                            <input type="text" class="form-control" name="gst_no">
                        </div>
                    </div>
                    
                    <div class="row">
                    </div>
                    
                    <div class="text-end">
                        <button type="submit" id="saveTransportBtn" class="btn btn-primary btn-lg">
                            <i class="fas fa-save me-2"></i>Save Transport
                        </button>
                        <!-- <button type="reset" class="btn btn-secondary btn-lg">
                            <i class="fas fa-redo me-2"></i>Reset
                        </button> -->
                    </div>
                </form>
            </div>
        </div>
    </div>

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