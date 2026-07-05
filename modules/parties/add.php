<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";
require_once "../../includes/audit_helper.php";

$database = new Database();
$db = $database->getConnection();

// Max Prod ID
$party_query = $db->query("SELECT COUNT(party_id) as count FROM ff_sch.parties");
$party = $party_query->fetch(PDO::FETCH_ASSOC);
$max_id = $party['count']+1;

// Generate party ID with zero padding (5 digits total)
$padded_max_id = str_pad($max_id, 5, '0', STR_PAD_LEFT);
$party_id = "PTY-" . $padded_max_id;

// Generate Party ID
// $party_id = "PTY-" . date('Ym') . "-" . str_pad(rand(1, 999), 3, '0', STR_PAD_LEFT);


?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Party - <?php echo SITE_NAME; ?></title>
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
        $country = 'INDIA';
        try {
            // Convert city and state to uppercase
            $city = strtoupper($_POST['city']);
            $state = strtoupper($_POST['state']);
            
            // Check for duplicate name
            $check_query = "SELECT COUNT(*) FROM ff_sch.parties WHERE name = :name AND is_active = true";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':name', $_POST['name']);
            $check_stmt->execute();
            
            if ($check_stmt->fetchColumn() > 0) {
                echo "<script>Swal.fire('Error!', 'A Party with this name already exists.', 'error');</script>";
            } else {
                $query = "INSERT INTO ff_sch.parties (party_id, name, address_line1, address_line2, city, pin_code, state, country, gst_no, agent_name) 
                        VALUES (:party_id, :name, :address_line1, :address_line2, :city, :pin_code, :state, :country, :gst_no, :agent_name)";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':party_id', $_POST['party_id']);
                $stmt->bindParam(':name', $_POST['name']);
                $stmt->bindParam(':address_line1', $_POST['address_line1']);
                $stmt->bindParam(':address_line2', $_POST['address_line2']);
                $stmt->bindParam(':city', $city);
                $stmt->bindParam(':pin_code', $_POST['pin_code']);
                $stmt->bindParam(':state', $state);
                $stmt->bindParam(':country', $country);
                $stmt->bindParam(':gst_no', $_POST['gst_no']);
                $stmt->bindParam(':agent_name', $_POST['agent_name']);
                $stmt->execute();
                
                echo "<script>
                        Swal.fire('Success!', 'Party added successfully!', 'success')
                            .then(() => { window.location.href = 'list.php'; });
                    </script>";
            }
            
        } catch(Exception $e) {
            $errorMsg = addslashes(str_replace(["\r", "\n"], ' ', $e->getMessage()));
            echo "<script>Swal.fire('Error!', 'Failed to add Party: " . $errorMsg . "', 'error');</script>";
        }
    } ?>

    <!-- <main class="main-content"> -->
        <div class="container-fluid">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Add New Party</h2>
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
                                <input type="text" class="form-control" name="party_id" value="<?php echo $party_id; ?>" readonly>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Party Name *</label>
                                <input type="text" class="form-control" name="name" required autofocus>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">GST No</label>
                                <input type="text" class="form-control" name="gst_no">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Agent Name</label>
                                <input type="text" class="form-control" name="agent_name">
                            </div>
                        </div>
                        
                        <div class="row">
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Place</label>
                                <input type="text" class="form-control" name="city">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Pincode</label>
                                <input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control" name="pin_code" maxlength=6>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">State</label>
                                <input type="text" class="form-control" name="state">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Address Line 1</label>
                            <textarea class="form-control" name="address_line1"></textarea> 
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Address Line 2</label>
                            <textarea class="form-control" name="address_line2"></textarea> 
                        </div>
                        
                        <div class="row" style='display : none;'>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Country</label>
                                <input type="text" class="form-control" name="country" value="India">
                            </div>
                        </div>
                        
                        <div class="text-end">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Save Party
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