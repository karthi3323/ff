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
    echo $id = $_GET['delete_id'];
    $query = "UPDATE ff_sch.transport SET is_active = false WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    echo "<script>
            Swal.fire('Success!', 'Transport deleted successfully!', 'success')
                .then(() => { window.location.href = 'list.php'; });
          </script>";
}

// Fetch transport records
$query = "SELECT * FROM ff_sch.transport WHERE is_active = true ORDER BY created_at DESC";
$stmt = $db->query($query);
$transports = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transport - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/img/favicon.png">

    <link href="<?php echo ASSETS_URL; ?>/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <!-- <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet"> -->

    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
</head>
<body>

<?php include "../../includes/header.php"; ?>
<?php include "../../includes/sidebar.php"; ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Transport</h2>
        <a href="add.php" class="btn btn-primary">
            <i class="fas fa-plus me-2"></i>Add New Transport
        </a>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title">All Transport</h5>
        </div>
        <div class="card-body">

            <!-- ⭐ Added ID to activate DataTable -->
            <table id="transportTable" class="table table-striped">
                <thead>
                    <tr>
                        <!-- <th>ID</th> -->
                        <th>Name</th>
                        <th>GST No</th>
                        <th>Created Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($transports as $transport): ?>
                    <tr>
                        <!-- <td><?php echo $transport['id']; ?></td> -->
                        <td><?php echo $transport['name']; ?></td>
                        <td><?php echo $transport['gst_no'] ?: 'N/A'; ?></td>
                        <td><?php echo date('d-m-Y', strtotime($transport['created_at'])); ?></td>
                        <td>
                            <a href="edit.php?id=<?php echo $transport['id']; ?>" class="btn btn-sm btn-warning">
                                <i class="fas fa-edit"></i>
                            </a>
                            <button onclick="confirmDelete('Are you sure you want to delete this transport?', 'modules/transport/list.php?delete_id=<?php echo $transport['id']; ?>')" 
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

<!-- JS FILES -->
<script src="<?php echo ASSETS_URL; ?>/js/jquery-3.6.0.min.js"></script>
<script src="<?php echo ASSETS_URL; ?>/js/bootstrap.bundle.min.js"></script>

<script src="<?php echo ASSETS_URL; ?>/js/jquery.dataTables.min.js"></script>
<script src="<?php echo ASSETS_URL; ?>/js/dataTables.bootstrap5.min.js"></script>

<!-- <script src="<?php echo ASSETS_URL; ?>/js/dataTables.buttons.min.js"></script>
<script src="<?php echo ASSETS_URL; ?>/js/buttons.bootstrap5.min.js"></script> -->

<!-- <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script> -->

<!-- <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script> -->

<script src="<?php echo ASSETS_URL; ?>/js/sweetalert2@11.js"></script>
<script src="<?php echo ASSETS_URL; ?>/js/script.js"></script>
<script src="<?php echo ASSETS_URL; ?>/js/sidebar.js"></script>

<!-- ⭐ DataTable Initialization -->
<script>
$(document).ready(function () {
    $('#transportTable').DataTable({
        processing: true,
        pageLength: 10,
        lengthMenu: [10, 25, 50, 100],
        ordering: true,
        searching: true,  // enables search bar
        responsive: true
    });
});
</script>

</body>
</html>
