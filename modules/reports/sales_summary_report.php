<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";

$database = new Database();
$db = $database->getConnection();

// Filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Sales summary query
$query = "SELECT 
            COUNT(*) as invoice_count,
            SUM(taxable_amount) as total_taxable,
            SUM(discount) as total_discount,
            SUM(sgst_amount) as total_sgst,
            SUM(cgst_amount) as total_cgst,
            SUM(igst_amount) as total_igst,
            SUM(total_tax) as total_tax,
            SUM(net_amount) as total_net
          FROM ff_sch.invoices 
          WHERE invoice_date BETWEEN :start_date AND :end_date";

$stmt = $db->prepare($query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->execute();
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Summary Report - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/img/favicon.png">
    <link href="<?php echo ASSETS_URL; ?>/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include "../../includes/header.php"; ?>
    <?php include "../../includes/sidebar.php"; ?>

    <div class="container-fluid">
        <h2 class="mb-4">Sales Summary Report</h2>

        <div class="card mb-4">
            <div class="card-header"><h5 class="card-title">Filters</h5></div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="start_date" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    <div class="col-md-4">
                        <label for="end_date" class="form-label">To Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-2"></i>Generate Report</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Summary for <?php echo date('d M Y', strtotime($start_date)); ?> to <?php echo date('d M Y', strtotime($end_date)); ?></h5>
                <a href="pdf_sales_summary_report.php?<?php echo http_build_query($_GET); ?>" class="btn btn-danger" target="_blank">
                    <i class="fas fa-file-pdf me-2"></i>Export to PDF
                </a>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">Total Invoices <span class="badge bg-primary rounded-pill"><?php echo number_format($summary['invoice_count'] ?? 0); ?></span></li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">Total Taxable Amount <span><?php echo CURRENCY . number_format($summary['total_taxable'] ?? 0, 2); ?></span></li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">Total Discount <span class="text-danger">- <?php echo CURRENCY . number_format($summary['total_discount'] ?? 0, 2); ?></span></li>
                        </ul>
                    </div>
                    <div class="col-md-6">
                        <ul class="list-group">
                            <li class="list-group-item d-flex justify-content-between align-items-center">Total SGST <span><?php echo CURRENCY . number_format($summary['total_sgst'] ?? 0, 2); ?></span></li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">Total CGST <span><?php echo CURRENCY . number_format($summary['total_cgst'] ?? 0, 2); ?></span></li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">Total IGST <span><?php echo CURRENCY . number_format($summary['total_igst'] ?? 0, 2); ?></span></li>
                             <li class="list-group-item d-flex justify-content-between align-items-center fw-bold">Total Tax <span><?php echo CURRENCY . number_format($summary['total_tax'] ?? 0, 2); ?></span></li>
                        </ul>
                    </div>
                </div>
                <div class="mt-4 text-center bg-light p-3 rounded">
                    <h3>Total Net Sales</h3>
                    <h2 class="text-success fw-bold"><?php echo CURRENCY . number_format($summary['total_net'] ?? 0, 2); ?></h2>
                </div>
            </div>
        </div>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/jquery-3.6.0.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/script.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/sidebar.js"></script>
</body>
</html>