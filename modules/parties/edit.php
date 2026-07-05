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

if(!isset($_GET['id'])) {
    header("Location: " . BASE_URL . "modules/parties/list.php");
    exit();
}

$id = $_GET['id'];

// Fetch party data
$query = "SELECT * FROM ff_sch.parties WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();
$party = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$party) {
    header("Location: " . BASE_URL . "modules/parties/list.php");
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Party - <?php echo SITE_NAME; ?></title>
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
            $country = 'INDIA';
            $city = strtoupper($_POST['city']);
            $state = strtoupper($_POST['state']);
            $query = "UPDATE ff_sch.parties SET name = :name, address_line1 = :address_line1, address_line2 = :address_line2, 
                    city = :city, pin_code = :pin_code, state = :state, country = :country, 
                    gst_no = :gst_no, agent_name = :agent_name
                    WHERE id = :id";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':name', $_POST['name']);
            $stmt->bindParam(':address_line1', $_POST['address_line1']);
            $stmt->bindParam(':address_line2', $_POST['address_line2']);
            $stmt->bindParam(':city', $city);
            $stmt->bindParam(':pin_code', $_POST['pin_code']);
            $stmt->bindParam(':state', $state);
            $stmt->bindParam(':country', $country);
            $stmt->bindParam(':gst_no', $_POST['gst_no']);
            $stmt->bindParam(':agent_name', $_POST['agent_name']);
            $stmt->bindParam(':id', $id);
            
            $stmt->execute();
            
            echo "<script>
                    Swal.fire('Success!', 'Party updated successfully!', 'success')
                        .then(() => { window.location.href = 'list.php'; });
                </script>";
            
        } catch(Exception $e) {
            $errorMsg = addslashes(str_replace(["\r", "\n"], ' ', $e->getMessage()));
            echo "<script>Swal.fire('Error!', 'Failed to update Party: " . $errorMsg . "', 'error');</script>";
        }
    } ?>

    <!-- <main class="main-content"> -->
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Edit Party</h2>
                <a href="list.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to List
                </a>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Party Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3" style="display:none;">
                                <label class="form-label">Party ID</label>
                                <input type="text" class="form-control" value="<?php echo $party['party_id']; ?>" readonly>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Party Name *</label>
                                <input type="text" class="form-control" name="name" value="<?php echo $party['name']; ?>" required autofocus>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">GST No</label>
                                <input type="text" class="form-control" name="gst_no" value="<?php echo $party['gst_no']; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Agent Name</label>
                                <input type="text" class="form-control" name="agent_name" value="<?php echo $party['agent_name']; ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Place</label>
                                <input type="text" class="form-control" name="city" value="<?php echo $party['city']; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Pincode</label>
                                <input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control" name="pin_code" value="<?php echo $party['pin_code']; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">State</label>
                                <input type="text" class="form-control" name="state" value="<?php echo $party['state']; ?>">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Address Line 1</label>
                            <input type="text" class="form-control" name="address_line1" value="<?php echo $party['address_line1']; ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Address Line 2</label>
                            <input type="text" class="form-control" name="address_line2" value="<?php echo $party['address_line2']; ?>">
                        </div>
                        
                        <div class="row" style="display: none;">
                            
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Country</label>
                                <input type="text" class="form-control" name="country" value="<?php echo $party['country']; ?>">
                            </div>
                            
                        </div>
                        
                        <div class="row" style="display: none;">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" value="<?php echo $party['phone']; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" value="<?php echo $party['email']; ?>">
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Update Party
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