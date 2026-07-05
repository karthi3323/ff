<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";

$database = new Database();
$db = $database->getConnection();

// Handle add role
if($_POST && isset($_POST['add_role'])) {
    try {
        $query = "INSERT INTO ff_sch.roles (name, permissions) VALUES (:name, :permissions)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':name', $_POST['name']);
        $stmt->bindParam(':permissions', json_encode($_POST['permissions']));
        $stmt->execute();
        
        echo "<script>
                Swal.fire('Success!', 'Role added successfully!', 'success')
                    .then(() => { window.location.href = 'roles.php'; });
              </script>";
    } catch(Exception $e) {
        echo "<script>Swal.fire('Error!', 'Failed to add role: " . addslashes($e->getMessage()) . "', 'error');</script>";
    }
}

// Handle delete role
if(isset($_GET['delete_id'])) {
    try {
        // Check if role is used by users
        $check_query = "SELECT COUNT(*) FROM ff_sch.users WHERE role_id = :id";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->bindParam(':id', $_GET['delete_id']);
        $check_stmt->execute();
        $user_count = $check_stmt->fetchColumn();
        
        if($user_count > 0) {
            echo "<script>
                    Swal.fire('Error!', 'Cannot delete role. It is assigned to users.', 'error')
                        .then(() => { window.location.href = 'roles.php'; });
                  </script>";
        } else {
            $delete_query = "DELETE FROM ff_sch.roles WHERE id = :id";
            $delete_stmt = $db->prepare($delete_query);
            $delete_stmt->bindParam(':id', $_GET['delete_id']);
            $delete_stmt->execute();
            
            echo "<script>
                    Swal.fire('Success!', 'Role deleted successfully!', 'success')
                        .then(() => { window.location.href = 'roles.php'; });
                  </script>";
        }
    } catch(Exception $e) {
        echo "<script>Swal.fire('Error!', 'Failed to delete role: " . addslashes($e->getMessage()) . "', 'error');</script>";
    }
}

// Fetch roles
$roles = $db->query("SELECT * FROM ff_sch.roles ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Roles Management - <?php echo SITE_NAME; ?></title>
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
            <h2 class="mb-4">Roles Management</h2>

            <div class="row">
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title">Add New Role</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Role Name *</label>
                                    <input type="text" class="form-control" name="name" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Permissions</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="read" id="perm_read">
                                        <label class="form-check-label" for="perm_read">Read Access</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="write" id="perm_write">
                                        <label class="form-check-label" for="perm_write">Write Access</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="delete" id="perm_delete">
                                        <label class="form-check-label" for="perm_delete">Delete Access</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="reports" id="perm_reports">
                                        <label class="form-check-label" for="perm_reports">Reports Access</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="permissions[]" value="all" id="perm_all">
                                        <label class="form-check-label" for="perm_all">All Permissions</label>
                                    </div>
                                </div>
                                
                                <button type="submit" name="add_role" class="btn btn-primary w-100">
                                    <i class="fas fa-plus me-2"></i>Add Role
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">System Roles</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-striped data-table">
                                <thead>
                                    <tr>
                                        <th>Role Name</th>
                                        <th>Permissions</th>
                                        <th>Created Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($roles as $role): 
                                        $permissions = json_decode($role['permissions'], true);
                                        $perm_display = is_array($permissions) ? implode(', ', array_keys($permissions)) : 'No permissions';
                                    ?>
                                    <tr>
                                        <td><?php echo $role['name']; ?></td>
                                        <td>
                                            <span class="badge bg-info" title="<?php echo $perm_display; ?>">
                                                <?php echo is_array($permissions) ? count($permissions) . ' permissions' : 'No permissions'; ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('d M Y', strtotime($role['created_at'])); ?></td>
                                        <td>
                                            <?php if($role['name'] !== 'Admin'): ?>
                                                <button onclick="confirmDelete('Are you sure you want to delete this role?', 'roles.php?delete_id=<?php echo $role['id']; ?>')" 
                                                        class="btn btn-sm btn-danger">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php else: ?>
                                                <span class="text-muted">System Role</span>
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
</body>
</html>