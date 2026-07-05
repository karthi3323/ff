<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";
require_once "../../includes/audit_helper.php";

$database = new Database();
$db = $database->getConnection();

// Handle delete
if(isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    $query = "UPDATE ff_sch.parties SET is_active = false WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    echo "<script>
            Swal.fire('Success!', 'Party deleted successfully!', 'success')
                .then(() => { window.location.href = 'list.php'; });
          </script>";
}

// Fetch parties
$query = "SELECT * FROM ff_sch.parties WHERE is_active = true ORDER BY created_at DESC";
$stmt = $db->query($query);
$parties = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Parties - <?php echo SITE_NAME; ?></title>
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Parties</h2>
            <a href="add.php" class="btn btn-primary">
                <i class="fas fa-plus me-2"></i>Add New Party
            </a>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title">All Parties</h5>
            </div>
            <div class="card-body">

                <!-- ⭐ Added ID for DataTable Search -->
                <table id="partyTable" class="table table-striped">
                    <thead>
                        <tr>
                            <!-- <th>Party ID</th> -->
                            <th>Name</th>
                            <th>State</th>
                            <th>Agent Name</th>
                            <th>GST No</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($parties as $party): ?>
                        <tr>
                            <!-- <td><?php echo $party['party_id']; ?></td> -->
                            <td><?php echo $party['name']; ?></td>
                            <td><?php echo $party['state']; ?></td>
                            <td><?php echo $party['agent_name']; ?></td>
                            <td><?php echo $party['gst_no'] ?: 'N/A'; ?></td>
                            <td>
                                <a href="edit.php?id=<?php echo $party['id']; ?>" class="btn btn-sm btn-warning">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button onclick="confirmDelete('Are you sure you want to delete this party?', 'modules/parties/list.php?delete_id=<?php echo $party['id']; ?>')" 
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

    <!-- JS Files -->
    <script src="<?php echo ASSETS_URL; ?>/js/jquery-3.6.0.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/bootstrap.bundle.min.js"></script>

    <script src="<?php echo ASSETS_URL; ?>/js/jquery.dataTables.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/dataTables.bootstrap5.min.js"></script>

    <script src="<?php echo ASSETS_URL; ?>/js/sweetalert2@11.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/script.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/sidebar.js"></script>

    <!-- ⭐ DataTable Initialization (Search + Pagination + Sort) -->
    <script>
        $(document).ready(function () {
            $('#partyTable').DataTable({
                processing: true,
                pageLength: 10,
                lengthMenu: [10, 25, 50, 100],
                ordering: true,
                searching: true,
                responsive: true
            });
        });
    </script>

</body>
</html>
