<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";

$database = new Database();
$db = $database->getConnection();

// Fetch all parties
$parties = $db->query("SELECT * FROM ff_sch.parties WHERE is_active = true ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get party statistics
$party_stats = [];
foreach($parties as $party) {
    $stats_query = "SELECT 
                    COUNT(*) as total_invoices,
                    SUM(i.net_amount) as total_sales,
                    MAX(i.invoice_date) as last_purchase
                   FROM ff_sch.invoices i 
                   WHERE i.party_id = :party_id";
    $stmt = $db->prepare($stats_query);
    $stmt->bindParam(':party_id', $party['id']);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $party_stats[$party['id']] = $stats;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Party Details Report - <?php echo SITE_NAME; ?></title>
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
            <h2 class="mb-4">Party Details Report</h2>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6>Total Parties</h6>
                            <h3><?php echo count($parties); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6>Active Parties</h6>
                            <h3><?php echo count($parties); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h6>Parties with GST</h6>
                            <h3>
                                <?php 
                                    $gst_count = 0;
                                    foreach($parties as $party) {
                                        if(!empty($party['gst_no'])) $gst_count++;
                                    }
                                    echo $gst_count;
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h6>New This Month</h6>
                            <h3>
                                <?php
                                    $new_this_month = 0;
                                    $current_month = date('Y-m');
                                    foreach($parties as $party) {
                                        if(date('Y-m', strtotime($party['created_at'])) === $current_month) {
                                            $new_this_month++;
                                        }
                                    }
                                    echo $new_this_month;
                                ?>
                            </h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Parties Table -->
            <div class="card">
                <div class="card-header d-flex align-items-end justify-content-between">
                    <h5 class="card-title">All Parties Details</h5>
                    <a style="width:12% !important;" href="pdf_party_details.php?<?php echo http_build_query($_GET); ?>" class="btn btn-danger w-100" target="_blank">
                        <i class="fas fa-file-pdf me-2"></i>PDF
                    </a>
                </div>
                <div class="card-body">
                    <table id="partyTable" class="table table-striped data-table">
                        <thead>
                            <tr>
                                <th>Party ID</th>
                                <th>Name</th>
                                <th>Contact</th>
                                <th>Address</th>
                                <th>GST No</th>
                                <th>Total Invoices</th>
                                <th>Total Sales</th>
                                <th>Last Purchase</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($parties as $party): 
                                $stats = $party_stats[$party['id']] ?? ['total_invoices' => 0, 'total_sales' => 0, 'last_purchase' => null];
                            ?>
                            <tr>
                                <td><?php echo $party['party_id']; ?></td>
                                <td>
                                    <strong><?php echo $party['name']; ?></strong>
                                    <?php if(!empty($party['email'])): ?>
                                        <br><small class="text-muted"><?php echo $party['email']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if(!empty($party['phone'])): ?>
                                        <i class="fas fa-phone text-muted me-1"></i><?php echo $party['phone']; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php 
                                        $address_parts = [];
                                        if(!empty($party['address_line1'])) $address_parts[] = $party['address_line1'];
                                        if(!empty($party['city'])) $address_parts[] = $party['city'];
                                        if(!empty($party['state'])) $address_parts[] = $party['state'];
                                        echo implode(', ', $address_parts);
                                    ?>
                                </td>
                                <td>
                                    <?php if(!empty($party['gst_no'])): ?>
                                        <span class="badge bg-success"><?php echo $party['gst_no']; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">No GST</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $stats['total_invoices']; ?></span>
                                </td>
                                <td>
                                    <strong><?php echo CURRENCY . number_format($stats['total_sales'] ?? 0, 2); ?></strong>
                                </td>
                                <td>
                                    <?php if($stats['last_purchase']): ?>
                                        <?php echo date('d M Y', strtotime($stats['last_purchase'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">No purchases</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($party['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- State-wise Summary -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">State-wise Party Distribution</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="statepartyTable" class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>State</th>
                                    <th>Number of Parties</th>
                                    <th>Percentage</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $state_count = [];
                                foreach($parties as $party) {
                                    $state = $party['state'] ?: 'Unknown';
                                    if(!isset($state_count[$state])) {
                                        $state_count[$state] = 0;
                                    }
                                    $state_count[$state]++;
                                }
                                arsort($state_count);
                                
                                foreach($state_count as $state => $count):
                                    $percentage = ($count / count($parties)) * 100;
                                ?>
                                <tr>
                                    <td><?php echo $state; ?></td>
                                    <td><?php echo $count; ?></td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%">
                                                <?php echo number_format($percentage, 1); ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
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
        $(document).ready(function () {
            $('#statepartyTable, #partyTable').DataTable({
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