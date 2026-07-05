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

// Fetch company details
$company = $db->query("SELECT * FROM ff_sch.companies LIMIT 1")->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Company Settings - <?php echo SITE_NAME; ?></title>
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
        $inv_chk = ($_POST['inv_chk'] === 'Y');
        try {
            if($company) {
                // Update existing company
                $query = "UPDATE ff_sch.companies SET 
                        name = :name, address = :address, address2 = :address2, address3 = :address3, city = :city, state = :state, 
                        country = :country, gst_no = :gst_no, phone = :phone, email = :email, lic_no1 = :lic_no1, lic_no2 = :lic_no2, inv_chk = :inv_chk 
                        WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $company['id']);
            } else {
                // Insert new company
                $query = "INSERT INTO ff_sch.companies (name, address, address2, address3, city, state, country, gst_no, phone, email, lic_no1, lic_no2, inv_chk) 
                        VALUES (:name, :address, :city, :state, :country, :gst_no, :phone, :email, :lic_no1, :lic_no2, :inv_chk)";
                $stmt = $db->prepare($query);
            }
            
            $stmt->bindParam(':name', $_POST['name']);
            $stmt->bindParam(':address', $_POST['address']);
            $stmt->bindParam(':address2', $_POST['address2']);
            $stmt->bindParam(':address3', $_POST['address3']);
            $stmt->bindParam(':city', $_POST['city']);
            $stmt->bindParam(':state', $_POST['state']);
            $stmt->bindParam(':country', $_POST['country']);
            $stmt->bindParam(':gst_no', $_POST['gst_no']);
            $stmt->bindParam(':phone', $_POST['phone']);
            $stmt->bindParam(':email', $_POST['email']);
            $stmt->bindParam(':lic_no1', $_POST['lic_no1']);
            $stmt->bindParam(':lic_no2', $_POST['lic_no2']);
            $stmt->bindParam(':inv_chk', $inv_chk, PDO::PARAM_BOOL);
            
            $stmt->execute();
            
            echo "<script>
                    Swal.fire('Success!', 'Company details updated successfully!', 'success')
                        .then(() => { window.location.href = 'company.php'; });
                </script>";
            
        } catch(Exception $e) {
            echo "<script>Swal.fire('Error!', 'Failed to update company details: " . addslashes($e->getMessage()) . "', 'error');</script>";
        }
        
        // Refresh company data
        $company = $db->query("SELECT * FROM ff_sch.companies LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    } ?>

    <!-- <main class="main-content"> -->
        <div class="container-fluid">
            <h2 class="mb-4">Company Settings</h2>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Company Information</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Company Name *
                                    <input type="hidden" class="inv_chk" name="inv_chk" value="N">
                                    <input class="ms-2" id="inv_chk" type='checkbox' name='inv_chk' value='Y'<?= (!empty($company) && $company['inv_chk'] === true) ? 'checked' : '' ?>>
                                </label>
                                <input type="hidden" id="company_id" value="<?= $company['id'] ?>">
                                <textarea class="form-control" name="name" 
                                       value="" required autofocus><?php echo $company['name'] ?? ''; ?></textarea>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">GST No</label>
                                <input type="text" class="form-control" name="gst_no" 
                                       value="<?php echo $company['gst_no'] ?? ''; ?>">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Address</label>
                            <input type="text" class="form-control mb-2" name="address" value="<?php echo $company['address'] ?? ''; ?>">
                            <input type="text" class="form-control mb-2" name="address2" value="<?php echo $company['address2'] ?? ''; ?>">
                            <input type="text" class="form-control mb-2" name="address3" value="<?php echo $company['address3'] ?? ''; ?>">
                            
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">City</label>
                                <input type="text" class="form-control" name="city" 
                                       value="<?php echo $company['city'] ?? ''; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">State</label>
                                <input type="text" class="form-control" name="state" 
                                       value="<?php echo $company['state'] ?? ''; ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Country</label>
                                <input type="text" class="form-control" name="country" 
                                       value="<?php echo $company['country'] ?? 'India'; ?>">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Phone</label>
                                <input type="text" class="form-control" name="phone" 
                                       value="<?php echo $company['phone'] ?? ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?php echo $company['email'] ?? ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Licence No 1</label>
                                <input type="text" class="form-control" name="lic_no1" 
                                       value="<?php echo $company['lic_no1'] ?? ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Licence No 2</label>
                                <input type="text" class="form-control" name="lic_no2" 
                                       value="<?php echo $company['lic_no2'] ?? ''; ?>">
                            </div>
                        </div>
                        
                        <div class="text-center">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Save Company Details
                            </button>
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

        });
        

        $(document).on('change', '#inv_chk', function () {

            let inv_chk = $(this).prop('checked') ? 'Y' : 'N';
            console.log(inv_chk);
            $.ajax({
                url: '../../includes/ajax_actions.php',
                type: 'POST',
                dataType: 'json',
                data: {
                    inv_chk: inv_chk, action: 'updateComp',
                    company_id: $('#company_id').val() // or PHP echo
                },
                success: function (res) {
                    // console.log(res); return;
                    if (res.status === 'success') {
                        console.log('Updated successfully');
                         Swal.fire('Success!', 'Company details updated successfully!', 'success')
                        .then(() => { window.location.href = 'company.php'; });
                    } else {
                        // alert(res.message);
                        Swal.fire('Error!', 'Failed to update company details: " . addslashes($e->getMessage()) . "', 'error');
                    }
                },
                error: function () {
                    alert('Server error');
                }
            });

        });


    </script>
</body>
</html>