<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";

$database = new Database();
$db = $database->getConnection();

// Function to find PostgreSQL binaries on different operating systems
function findPostgresBinaries() {
    $pg_dump = null;
    $pg_restore = null;
    
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows paths - common PostgreSQL installations
        $possiblePaths = [
            'C:\\Program Files\\PostgreSQL\\16\\bin\\pg_dump.exe',
            'C:\\Program Files\\PostgreSQL\\15\\bin\\pg_dump.exe',
            'C:\\Program Files\\PostgreSQL\\14\\bin\\pg_dump.exe',
            'C:\\Program Files\\PostgreSQL\\13\\bin\\pg_dump.exe',
            'C:\\Program Files (x86)\\PostgreSQL\\16\\bin\\pg_dump.exe',
            'C:\\Program Files (x86)\\PostgreSQL\\15\\bin\\pg_dump.exe',
            'C:\\Program Files\\PostgreSQL\\16\\bin\\pg_dump.exe',
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                $pg_dump = $path;
                $pg_restore = str_replace('pg_dump.exe', 'pg_restore.exe', $path);
                break;
            }
        }
        
        // Try to find via PATH environment variable
        if (!$pg_dump) {
            $output = [];
            exec('where pg_dump 2>nul', $output, $returnVar);
            if ($returnVar === 0 && !empty($output)) {
                $pg_dump = $output[0];
                $pg_restore = str_replace('pg_dump', 'pg_restore', $output[0]);
            }
        }
    } else {
        // Mac/Linux paths
        $possiblePaths = [
            '/Applications/XAMPP/postgresql/16/bin/pg_dump',
            '/Applications/XAMPP/postgresql/15/bin/pg_dump',
            '/Applications/XAMPP/postgresql/14/bin/pg_dump',
            '/Applications/XAMPP/postgresql/13/bin/pg_dump',
            '/usr/local/bin/pg_dump',
            '/usr/bin/pg_dump',
            '/opt/homebrew/bin/pg_dump', // Homebrew on Apple Silicon
            '/usr/local/opt/postgresql@16/bin/pg_dump',
            '/usr/local/opt/postgresql@15/bin/pg_dump',
            '/usr/local/opt/postgresql@14/bin/pg_dump',
        ];
        foreach ($possiblePaths as $path) {
            print_r($path.'dddddd');
            if (file_exists($path)) {
                $pg_dump = $path;
                $pg_restore = str_replace('pg_dump', 'pg_restore', $path);
                break;
            }
        }
        
        // Try to find via which command
        if (!$pg_dump) {
            $output = [];
            exec('which pg_dump 2>/dev/null', $output, $returnVar);
            if ($returnVar === 0 && !empty($output)) {
                $pg_dump = $output[0];
                $pg_restore = str_replace('pg_dump', 'pg_restore', $output[0]);
            }
        }
    }
    
    return ['pg_dump' => $pg_dump, 'pg_restore' => $pg_restore];
}

// Get database configuration from your database connection
function getDatabaseConfig() {
    // You should ideally get these from your database configuration file
    // For now, we'll use the same configuration as your database connection
    return [
        'host' => 'localhost',
        'port' => '5432',
        'dbname' => 'ff_dbs',
        'username' => 'ff_adm',
        'password' => 'friends'
    ];
}

// Handle backup creation
if(isset($_POST['create_backup'])) {
    try {
        // Get database configuration
        $dbConfig = getDatabaseConfig();
        
        // Create backup directory if it doesn't exist
        $backup_dir = "../../backups/";
        if (!file_exists($backup_dir)) {
            mkdir($backup_dir, 0777, true);
        }
        
        // Find PostgreSQL binaries
        $binaries = findPostgresBinaries();
        if (!$binaries['pg_dump']) {
            throw new Exception("PostgreSQL pg_dump not found. Please ensure PostgreSQL is installed and in PATH.");
        }
        
        // Create backup file
        $backup_file = $backup_dir . "backup_" . date('Y-m-d_H-i-s') . ".backup";
        
        // Build command based on OS
        $command = '';
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            // Windows command
            $command = "\"{$binaries['pg_dump']}\" -h {$dbConfig['host']} -p {$dbConfig['port']} -U {$dbConfig['username']} -d {$dbConfig['dbname']} -F c -b -v -f \"{$backup_file}\"";
            
            // Set password using environment variable for Windows
            putenv("PGPASSWORD={$dbConfig['password']}");
        } else {
            // Mac/Linux command
            $command = escapeshellcmd($binaries['pg_dump']) . " -h {$dbConfig['host']} -p {$dbConfig['port']} -U {$dbConfig['username']} -d {$dbConfig['dbname']} -F c -b -v -f " . escapeshellarg($backup_file);
            
            // Set password using environment variable
            putenv("PGPASSWORD={$dbConfig['password']}");
        }
        
        // Execute command
        $output = [];
        $return_var = 0;
        exec($command . " 2>&1", $output, $return_var);
        
        if($return_var === 0) {
            $_SESSION['success_message'] = "Backup created successfully!";
            header("Location: backup.php");
            exit;
        } else {
            $error_message = implode("\n", $output);
            throw new Exception("Backup command failed: " . $error_message);
        }
        
    } catch(Exception $e) {
        $_SESSION['error_message'] = "Failed to create backup: " . $e->getMessage();
        header("Location: backup.php");
        exit;
    }
}

// Handle backup download
if(isset($_GET['download'])) {
    $file = "../../backups/" . basename($_GET['download']);
    if(file_exists($file) && pathinfo($file, PATHINFO_EXTENSION) === 'backup') {
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="'.basename($file).'"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }
}

// Handle backup delete
if(isset($_GET['delete'])) {
    $file = "../../backups/" . basename($_GET['delete']);
    if(file_exists($file) && pathinfo($file, PATHINFO_EXTENSION) === 'backup') {
        unlink($file);
        $_SESSION['success_message'] = "Backup deleted successfully!";
    } else {
        $_SESSION['error_message'] = "Backup file not found!";
    }
    header("Location: backup.php");
    exit;
}

// Handle backup restore
if(isset($_POST['restore_backup'])) {
    try {
        $backup_file = "../../backups/" . basename($_POST['backup_file']);
        if(!file_exists($backup_file)) {
            throw new Exception("Backup file not found!");
        }
        
        // Get database configuration
        $dbConfig = getDatabaseConfig();
        
        // Find PostgreSQL binaries
        $binaries = findPostgresBinaries();
        if (!$binaries['pg_restore']) {
            throw new Exception("PostgreSQL pg_restore not found. Please ensure PostgreSQL is installed and in PATH.");
        }
        
        // Build restore command
        $command = '';
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $command = "\"{$binaries['pg_restore']}\" -h {$dbConfig['host']} -p {$dbConfig['port']} -U {$dbConfig['username']} -d {$dbConfig['dbname']} -c -v \"{$backup_file}\"";
            putenv("PGPASSWORD={$dbConfig['password']}");
        } else {
            $command = escapeshellcmd($binaries['pg_restore']) . " -h {$dbConfig['host']} -p {$dbConfig['port']} -U {$dbConfig['username']} -d {$dbConfig['dbname']} -c -v " . escapeshellarg($backup_file);
            putenv("PGPASSWORD={$dbConfig['password']}");
        }
        
        // Execute restore command
        $output = [];
        $return_var = 0;
        exec($command . " 2>&1", $output, $return_var);
        
        if($return_var === 0) {
            $_SESSION['success_message'] = "Backup restored successfully!";
        } else {
            $error_message = implode("\n", $output);
            throw new Exception("Restore failed: " . $error_message);
        }
        
    } catch(Exception $e) {
        $_SESSION['error_message'] = "Failed to restore backup: " . $e->getMessage();
    }
    header("Location: backup.php");
    exit;
}

// Get list of backup files
$backup_files = [];
$backup_dir = "../../backups/";
if(file_exists($backup_dir)) {
    $files = scandir($backup_dir);
    foreach($files as $file) {
        if($file !== '.' && $file !== '..' && pathinfo($file, PATHINFO_EXTENSION) === 'backup') {
            $file_path = $backup_dir . $file;
            $backup_files[] = [
                'name' => $file,
                'size' => filesize($file_path),
                'modified' => filemtime($file_path)
            ];
        }
    }
    
    // Sort by modification time (newest first)
    usort($backup_files, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
}

// Display messages
if(isset($_SESSION['success_message'])) {
    echo "<script>Swal.fire('Success!', '{$_SESSION['success_message']}', 'success');</script>";
    unset($_SESSION['success_message']);
}
if(isset($_SESSION['error_message'])) {
    echo "<script>Swal.fire('Error!', '{$_SESSION['error_message']}', 'error');</script>";
    unset($_SESSION['error_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup Management - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/img/favicon.png">
    <link href="<?php echo ASSETS_URL; ?>/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    <script src="<?php echo ASSETS_URL; ?>/js/sweetalert2@11.js"></script>
</head>
<body>
    <?php include "../../includes/header.php"; ?>
    <?php include "../../includes/sidebar.php"; ?>

    <div class="container-fluid">
        <h2 class="mb-4">Backup Management</h2>

        <!-- PostgreSQL Information -->
       <!--  <div class="alert alert-info mb-4">
            <i class="fas fa-info-circle me-2"></i>
            <strong>System Information:</strong> Running on <?php echo PHP_OS; ?> | 
            PostgreSQL: <?php echo $binaries['pg_dump'] ? 'Found' : 'Not Found'; ?>
        </div> -->

        <!-- Backup Actions -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title">Create New Backup</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <form method="POST">
                            <p>Create a complete backup of your database including all tables and data.</p>
                            <p class="text-muted small">
                                <i class="fas fa-database"></i> Backup format: Custom format (.backup)<br>
                                <i class="fas fa-info-circle"></i> Includes all schemas, tables, data, and indexes
                            </p>
                            <button type="submit" name="create_backup" class="btn btn-primary">
                                <i class="fas fa-database me-2"></i>Create Backup Now
                            </button>
                        </form>
                    </div>
                    <div class="col-md-6">
                        <div class="alert alert-info">
                            <h6><i class="fas fa-info-circle me-2"></i>Backup Information</h6>
                            <ul class="mb-0">
                                <li>Backups include all database tables and data</li>
                                <li>Backup files are stored in the backups directory</li>
                                <li>Recommended to create backups regularly</li>
                                <li>Download backups for safe storage</li>
                                <li>Supports both Mac and Windows systems</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Existing Backups -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title">Existing Backups</h5>
            </div>
            <div class="card-body">
                <?php if(empty($backup_files)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-database fa-3x text-muted mb-3"></i>
                        <p class="text-muted">No backup files found.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Backup File</th>
                                    <th>Size</th>
                                    <th>Date Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($backup_files as $file): ?>
                                <tr>
                                    <td>
                                        <i class="fas fa-file-archive text-primary me-2"></i>
                                        <?php echo htmlspecialchars($file['name']); ?>
                                    </td>
                                    <td><?php echo formatBytes($file['size']); ?></td>
                                    <td><?php echo date('d M Y H:i:s', $file['modified']); ?></td>
                                    <td>
                                        <!-- <button onclick="restoreBackup('<?php echo htmlspecialchars($file['name']); ?>')" 
                                                class="btn btn-sm btn-warning me-1">
                                            <i class="fas fa-undo-alt"></i> Restore
                                        </button> -->
                                        <a href="backup.php?download=<?php echo urlencode($file['name']); ?>" 
                                           class="btn btn-sm btn-success me-1">
                                            <i class="fas fa-download"></i> Download
                                        </a>
                                        <button onclick="confirmDelete('Are you sure you want to delete this backup?', 'backup.php?delete=<?php echo urlencode($file['name']); ?>')" 
                                                class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Restore Backup Modal -->
    <div class="modal fade" id="restoreModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Restore Backup</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="restoreForm">
                    <div class="modal-body">
                        <input type="hidden" name="restore_backup" value="1">
                        <input type="hidden" name="backup_file" id="restore_file">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Warning!</strong> Restoring a backup will overwrite your current database. 
                            This action cannot be undone. Please ensure you have a current backup before proceeding.
                        </div>
                        <p>Are you sure you want to restore from: <strong id="restore_file_name"></strong>?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Restore Backup</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/jquery-3.6.0.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/sweetalert2@11.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/script.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/sidebar.js"></script>
    <script>
        // Keyboard shortcut: Ctrl+S to create backup
        $(document).keydown(function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                e.preventDefault();
                $('button[name="create_backup"]').click();
            }
        });

        function restoreBackup(filename) {
            $('#restore_file').val(filename);
            $('#restore_file_name').text(filename);
            $('#restoreModal').modal('show');
        }

        function confirmDelete(message, url) {
            Swal.fire({
                title: 'Are you sure?',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        }

        // Display messages from session
        <?php if(isset($_SESSION['success_message'])): ?>
        Swal.fire('Success!', '<?php echo $_SESSION['success_message']; ?>', 'success');
        <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error_message'])): ?>
        Swal.fire('Error!', '<?php echo $_SESSION['error_message']; ?>', 'error');
        <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
    </script>
</body>
</html>

<?php
// Helper function to format file sizes
function formatBytes($bytes, $precision = 2) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}
?>