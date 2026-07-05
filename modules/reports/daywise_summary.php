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

// Filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Day-wise summary query
$query = "SELECT 
            invoice_date as date,
            COUNT(*) as invoice_count,
            SUM(taxable_amount) as total_taxable,
            SUM(net_amount) as total_net,
            SUM(discount) as total_discount
          FROM ff_sch.invoices 
          WHERE invoice_date BETWEEN :start_date AND :end_date
          GROUP BY invoice_date 
          ORDER BY invoice_date DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->execute();
$summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate grand totals
$grand_totals = [
    'invoice_count' => 0,
    'total_taxable' => 0,
    'total_net' => 0,
    'total_discount' => 0
];

foreach($summary as $row) {
    $grand_totals['invoice_count'] += $row['invoice_count'];
    $grand_totals['total_taxable'] += $row['total_taxable'];
    $grand_totals['total_net'] += $row['total_net'];
    $grand_totals['total_discount'] += $row['total_discount'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Day-wise Summary - <?php echo SITE_NAME; ?></title>
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
            <h2 class="mb-4">Day-wise Summary Report</h2>

            <!-- Filter Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title">Filter Report</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i>Filter
                            </button>
                            <a href="pdf_daywise_summary.php?<?php echo http_build_query($_GET); ?>" class="btn btn-danger w-100" target="_blank">
                                <i class="fas fa-file-pdf me-2"></i>PDF
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6>Total Days</h6>
                            <h3><?php echo count($summary); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6>Total Invoices</h6>
                            <h3><?php echo $grand_totals['invoice_count']; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h6>Total Taxable</h6>
                            <h3><?php echo CURRENCY . number_format($grand_totals['total_taxable'], 2); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h6>Total Net Amount</h6>
                            <h3><?php echo CURRENCY . number_format($grand_totals['total_net'], 2); ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Report Table -->
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title">Day-wise Summary</h5>
                </div>
                <div class="card-body">
                    <table id="invTable" class="table table-striped data-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Invoices</th>
                                <th>Taxable Amount</th>
                                <th>Discount</th>
                                <th>Net Amount</th>
                                <th>Average per Invoice</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($summary as $row): 
                                $avg_per_invoice = $row['invoice_count'] > 0 ? $row['total_net'] / $row['invoice_count'] : 0;
                            ?>
                            <tr>
                                <td><?php echo date('d M Y', strtotime($row['date'])); ?></td>
                                <td><?php echo $row['invoice_count']; ?></td>
                                <td><?php echo CURRENCY . number_format($row['total_taxable'], 2); ?></td>
                                <td><?php echo CURRENCY . number_format($row['total_discount'], 2); ?></td>
                                <td><strong><?php echo CURRENCY . number_format($row['total_net'], 2); ?></strong></td>
                                <td><?php echo CURRENCY . number_format($avg_per_invoice, 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td><strong>Grand Total</strong></td>
                                <td><strong><?php echo $grand_totals['invoice_count']; ?></strong></td>
                                <td><strong><?php echo CURRENCY . number_format($grand_totals['total_taxable'], 2); ?></strong></td>
                                <td><strong><?php echo CURRENCY . number_format($grand_totals['total_discount'], 2); ?></strong></td>
                                <td><strong><?php echo CURRENCY . number_format($grand_totals['total_net'], 2); ?></strong></td>
                                <td><strong>
                                    <?php 
                                        $grand_avg = $grand_totals['invoice_count'] > 0 ? $grand_totals['total_net'] / $grand_totals['invoice_count'] : 0;
                                        echo CURRENCY . number_format($grand_avg, 2); 
                                    ?>
                                </strong></td>
                            </tr>
                        </tfoot>
                    </table>
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
            $('#invTable').DataTable({
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