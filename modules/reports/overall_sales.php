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

// Overall sales summary
$query = "SELECT 
            COUNT(*) as total_invoices,
            SUM(taxable_amount) as total_taxable,
            SUM(discount) as total_discount,
            SUM(net_amount) as total_net,
            AVG(net_amount) as avg_invoice_value,
            MIN(net_amount) as min_invoice_value,
            MAX(net_amount) as max_invoice_value
          FROM ff_sch.invoices 
          WHERE invoice_date BETWEEN :start_date AND :end_date";

$stmt = $db->prepare($query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->execute();
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Monthly trend
$monthly_query = "SELECT 
                    TO_CHAR(invoice_date, 'YYYY-MM') as month,
                    COUNT(*) as invoice_count,
                    SUM(net_amount) as total_sales
                  FROM ff_sch.invoices 
                  WHERE invoice_date BETWEEN :start_date AND :end_date
                  GROUP BY TO_CHAR(invoice_date, 'YYYY-MM')
                  ORDER BY month DESC";

$monthly_stmt = $db->prepare($monthly_query);
$monthly_stmt->bindParam(':start_date', $start_date);
$monthly_stmt->bindParam(':end_date', $end_date);
$monthly_stmt->execute();
$monthly_trend = $monthly_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Overall Sales Report - <?php echo SITE_NAME; ?></title>
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
            <h2 class="mb-4">Overall Sales Report</h2>

            <!-- Filter Form -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title">Filter Report</h5>
                </div>
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Start Date</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo $start_date; ?>">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="end_date" value="<?php echo $end_date; ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-filter me-2"></i>Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6>Total Invoices</h6>
                            <h3><?php echo $summary['total_invoices'] ?? 0; ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6>Total Sales</h6>
                            <h3><?php echo CURRENCY . number_format($summary['total_net'] ?? 0, 2); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h6>Average Invoice</h6>
                            <h3><?php echo CURRENCY . number_format($summary['avg_invoice_value'] ?? 0, 2); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h6>Total Discount</h6>
                            <h3><?php echo CURRENCY . number_format($summary['total_discount'] ?? 0, 2); ?></h3>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Statistics -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Sales Statistics</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered">
                                <tr>
                                    <td>Highest Invoice Value</td>
                                    <td class="text-end">
                                        <strong><?php echo CURRENCY . number_format($summary['max_invoice_value'] ?? 0, 2); ?></strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Lowest Invoice Value</td>
                                    <td class="text-end">
                                        <strong><?php echo CURRENCY . number_format($summary['min_invoice_value'] ?? 0, 2); ?></strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Total Taxable Amount</td>
                                    <td class="text-end">
                                        <strong><?php echo CURRENCY . number_format($summary['total_taxable'] ?? 0, 2); ?></strong>
                                    </td>
                                </tr>
                                <tr>
                                    <td>Discount Percentage</td>
                                    <td class="text-end">
                                        <strong>
                                            <?php 
                                                $discount_percent = $summary['total_taxable'] > 0 ? 
                                                    ($summary['total_discount'] / $summary['total_taxable']) * 100 : 0;
                                                echo number_format($discount_percent, 2) . '%';
                                            ?>
                                        </strong>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title">Monthly Trend</h5>
                        </div>
                        <div class="card-body">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Month</th>
                                        <th>Invoices</th>
                                        <th>Sales</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($monthly_trend as $month): ?>
                                    <tr>
                                        <td><?php echo date('M Y', strtotime($month['month'] . '-01')); ?></td>
                                        <td><?php echo $month['invoice_count']; ?></td>
                                        <td><?php echo CURRENCY . number_format($month['total_sales'], 2); ?></td>
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