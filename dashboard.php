<?php
session_start();
require_once "config/database.php";
require_once "config/constants.php";
require_once "includes/auth.php";

$database = new Database();
$db = $database->getConnection();

// Get today's sales
$today = date('Y-m-d');
$today_sales_query = "SELECT COALESCE(SUM(net_amount), 0) as total_sales 
                      FROM ff_sch.invoices 
                      WHERE DATE(invoice_date) = :today";
$stmt = $db->prepare($today_sales_query);
$stmt->bindParam(':today', $today);
$stmt->execute();
$today_sales = $stmt->fetch(PDO::FETCH_ASSOC);

// Get today's receipts
$today_receipts_query = "SELECT COALESCE(SUM(total_receipt_amount), 0) as total_receipts 
                         FROM ff_sch.rcpt_hdr 
                         WHERE DATE(receipt_date) = :today";
$stmt_receipts = $db->prepare($today_receipts_query);
$stmt_receipts->bindParam(':today', $today);
$stmt_receipts->execute();
$today_receipts = $stmt_receipts->fetch(PDO::FETCH_ASSOC);

// Get today's quotations
$today_quotations_query = "SELECT COALESCE(SUM(net_amount), 0) as total_quotations 
                            FROM ff_sch.quotation
                            WHERE DATE(quotation_date) = :today";
$stmt_quotations = $db->prepare($today_quotations_query);
$stmt_quotations->bindParam(':today', $today);
$stmt_quotations->execute();
$today_quotations = $stmt_quotations->fetch(PDO::FETCH_ASSOC);

// Get today's estimates
$today_estimates_query = "SELECT COALESCE(SUM(net_amount), 0) as total_estimates 
                           FROM ff_sch.estimate 
                           WHERE DATE(estimate_date) = :today";
$stmt_estimates = $db->prepare($today_estimates_query);
$stmt_estimates->bindParam(':today', $today);
$stmt_estimates->execute();
$today_estimates = $stmt_estimates->fetch(PDO::FETCH_ASSOC);


// Get current fiscal year
$fiscal_year_data = $db->query("SELECT * FROM ff_sch.fiscal_years WHERE is_active = true LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$fiscal_year_id = $fiscal_year_data ? $fiscal_year_data['id'] : 1;

// Get tax summary from invoices table (using the actual columns from your invoices table)
$tax_query = "SELECT 
                COALESCE(SUM(sgst_amount), 0) as total_sgst, 
                COALESCE(SUM(cgst_amount), 0) as total_cgst,
                COALESCE(SUM(igst_amount), 0) as total_igst,
                COALESCE(SUM(total_tax), 0) as total_tax
              FROM ff_sch.invoices 
              WHERE DATE(invoice_date) = :today AND fiscal_year_id = :fiscal_year_id";
$stmt = $db->prepare($tax_query);
$stmt->bindParam(':today', $today);
$stmt->bindParam(':fiscal_year_id', $fiscal_year_id);
$stmt->execute();
$tax_summary = $stmt->fetch(PDO::FETCH_ASSOC);

$ovl_tax_query = "SELECT 
                COALESCE(SUM(sgst_amount), 0) as ovl_total_sgst, 
                COALESCE(SUM(cgst_amount), 0) as ovl_total_cgst,
                COALESCE(SUM(igst_amount), 0) as ovl_total_igst,
                COALESCE(SUM(total_tax), 0) as ovl_total_tax
              FROM ff_sch.invoices WHERE fiscal_year_id = :fiscal_year_id";
$stmt = $db->prepare($ovl_tax_query);
$stmt->bindParam(':fiscal_year_id', $fiscal_year_id);
$stmt->execute();
$ovl_tax_summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Get total counts
$products_count = $db->query("SELECT COUNT(*) FROM ff_sch.products WHERE is_active = true")->fetchColumn();
$parties_count = $db->query("SELECT COUNT(*) FROM ff_sch.parties WHERE is_active = true")->fetchColumn();
$invoices_count_stmt = $db->prepare("SELECT COUNT(*) FROM ff_sch.invoices WHERE fiscal_year_id = :fiscal_year_id");
$invoices_count_stmt->bindParam(':fiscal_year_id', $fiscal_year_id);
$invoices_count_stmt->execute();
$invoices_count = $invoices_count_stmt->fetchColumn();

// Get total quotations count
$quotations_count_stmt = $db->prepare("SELECT COUNT(*) FROM ff_sch.quotation WHERE fiscal_year_id = :fiscal_year_id");
$quotations_count_stmt->bindParam(':fiscal_year_id', $fiscal_year_id);
$quotations_count_stmt->execute();
$quotations_count = $quotations_count_stmt->fetchColumn();

// Get total estimates count
$estimates_count_stmt = $db->prepare("SELECT COUNT(*) FROM ff_sch.estimate WHERE fiscal_year_id = :fiscal_year_id");
$estimates_count_stmt->bindParam(':fiscal_year_id', $fiscal_year_id);
$estimates_count_stmt->execute();
$estimates_count = $estimates_count_stmt->fetchColumn();

// Get total receipts count
$receipts_count_stmt = $db->prepare("SELECT COUNT(*) FROM ff_sch.rcpt_hdr WHERE fiscal_year_id = :fiscal_year_id");
$receipts_count_stmt->bindParam(':fiscal_year_id', $fiscal_year_id);
$receipts_count_stmt->execute();
$receipts_count = $receipts_count_stmt->fetchColumn();



// Get monthly sales data for chart
$monthly_sales_query = "
    SELECT 
        DATE_TRUNC('month', invoice_date) as month,
        SUM(net_amount) as monthly_sales,
        COUNT(*) as invoice_count
    FROM ff_sch.invoices 
    WHERE invoice_date >= CURRENT_DATE - INTERVAL '6 months'
    GROUP BY DATE_TRUNC('month', invoice_date)
    ORDER BY month DESC
    LIMIT 6
";
$monthly_sales = $db->query($monthly_sales_query)->fetchAll(PDO::FETCH_ASSOC);

// Get top selling products
$top_products_query = "
    SELECT 
        p.name as product_name,
        SUM(ii.cartons) as total_cartons,
        SUM(ii.total_amount) as total_sales
    FROM ff_sch.invoice_items ii
    JOIN ff_sch.products p ON ii.product_id = p.id
    JOIN ff_sch.invoices i ON ii.invoice_id = i.id
    WHERE i.invoice_date >= CURRENT_DATE - INTERVAL '30 days'
    GROUP BY p.id, p.name
    ORDER BY total_sales DESC
    LIMIT 5
";
$top_products = $db->query($top_products_query)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/img/favicon.png">
    <link href="<?php echo ASSETS_URL; ?>/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/chart.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    <style>
        .card {
            transition: transform 0.2s;
        }
        .card:hover {
            transform: translateY(-2px);
        }
        .stats-card .card-body {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.5rem;
        }
        .stats-card .info-text p {
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 0.25rem;
            color: #6c757d;
        }
        .stats-card .info-text h4 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 0;
        }
        .stats-card .icon-container {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 60px;
            height: 60px;
            border-radius: 50%;
        }
        .stats-card .icon-container .fas {
            font-size: 24px;
            color: #fff;
        }
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        .bg-purple { background-color: #6f42c1 !important; }
        .bg-orange { background-color: #fd7e14 !important; }
        .bg-teal { color: #20c997 !important; }
        .text-purple { color: #6f42c1 !important; }
    </style>
</head>
<body>
    <?php include "includes/header.php"; ?>
    <?php include "includes/sidebar.php"; ?>

    <div class="container-fluid">
        <h2 class="mb-4">Dashboard</h2>

        <!-- Today's Snapshot -->
        <h4 class="mb-3">Today's Snapshot</h4>
        <div class="row">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stats-card h-100">
                    <div class="card-body">
                        <div class="info-text">
                            <p class="text-muted mb-2">Today's Sales</p>
                            <h4 class="mb-0 text-primary"><?php echo CURRENCY . number_format($today_sales['total_sales'], 2); ?></h4>
                        </div>
                        <div class="icon-container bg-primary">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stats-card h-100">
                    <div class="card-body">
                        <div class="info-text">
                            <p class="text-muted mb-2">Today's Receipts</p>
                            <h4 class="mb-0 text-purple"><?php echo CURRENCY . number_format($today_receipts['total_receipts'], 2); ?></h4>
                        </div>
                        <div class="icon-container bg-purple">
                            <i class="fas fa-receipt"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stats-card h-100">
                    <div class="card-body">
                        <div class="info-text">
                            <p class="text-muted mb-2">Today's Quotations</p>
                            <h4 class="mb-0 text-orange"><?php echo CURRENCY . number_format($today_quotations['total_quotations'], 2); ?></h4>
                        </div>
                        <div class="icon-container bg-orange">
                            <i class="fas fa-file-invoice-dollar"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stats-card h-100">
                    <div class="card-body">
                        <div class="info-text">
                            <p class="text-muted mb-2">Today's Estimates</p>
                            <h4 class="mb-0 text-info"><?php echo CURRENCY . number_format($today_estimates['total_estimates'], 2); ?></h4>
                        </div>
                        <div class="icon-container bg-info">
                            <i class="fas fa-calculator"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Overall Stats -->
        <h4 class="mb-3 mt-4">Overall Stats</h4>
        <div class="row">
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stats-card h-100">
                    <div class="card-body">
                        <div class="info-text">
                            <p class="text-muted mb-2">Total Estimates</p>
                            <h4 class="mb-0 text-success"><?php echo $estimates_count; ?></h4>
                        </div>
                        <div class="icon-container bg-success">
                            <i class="fas fa-file-invoice"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stats-card h-100">
                    <div class="card-body">
                        <div class="info-text">
                            <p class="text-muted mb-2">Total Receipts</p>
                            <h4 class="mb-0 text-purple"><?php echo $receipts_count; ?></h4>
                        </div>
                        <div class="icon-container bg-purple">
                            <i class="fas fa-receipt"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stats-card h-100">
                    <div class="card-body">
                        <div class="info-text">
                            <p class="text-muted mb-2">Active Parties</p>
                            <h4 class="mb-0 text-warning"><?php echo $parties_count; ?></h4>
                        </div>
                        <div class="icon-container bg-warning">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-xl-3 col-md-6 mb-4">
                <div class="card stats-card h-100">
                    <div class="card-body">
                        <div class="info-text">
                            <p class="text-muted mb-2">Active Products</p>
                            <h4 class="mb-0 text-info"><?php echo $products_count; ?></h4>
                        </div>
                        <div class="icon-container bg-info">
                            <i class="fas fa-boxes"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Additional Stats -->
        <div class="row">
            <!-- Tax Summary -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Today's Tax Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="text-primary">SGST</h6>
                                        <h4 class="text-primary"><?php echo CURRENCY . number_format($tax_summary['total_sgst'], 2); ?></h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="text-success">CGST</h6>
                                        <h4 class="text-success"><?php echo CURRENCY . number_format($tax_summary['total_cgst'], 2); ?></h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="text-info">IGST</h6>
                                        <h4 class="text-info"><?php echo CURRENCY . number_format($tax_summary['total_igst'], 2); ?></h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="text-warning">Total Tax</h6>
                                        <h4 class="text-warning"><?php echo CURRENCY . number_format($tax_summary['total_tax'], 2); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Top Products -->
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Top Selling Products (Last 30 Days)</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($top_products)): ?>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Cartons</th>
                                            <th>Sales</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($top_products as $product): ?>
                                            <tr>
                                                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                                <td><?php echo number_format($product['total_cartons']); ?></td>
                                                <td><?php echo CURRENCY . number_format($product['total_sales'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-muted text-center">No sales data available for the last 30 days.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts and Additional Stats -->
        <div class="row">
            <!-- Tax Summary -->
            <div class="col-lg-12 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Overall Tax Summary</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="text-primary">SGST</h6>
                                        <h4 class="text-primary"><?php echo CURRENCY . number_format($ovl_tax_summary['ovl_total_sgst'], 2); ?></h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="text-success">CGST</h6>
                                        <h4 class="text-success"><?php echo CURRENCY . number_format($ovl_tax_summary['ovl_total_cgst'], 2); ?></h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="text-info">IGST</h6>
                                        <h4 class="text-info"><?php echo CURRENCY . number_format($ovl_tax_summary['ovl_total_igst'], 2); ?></h4>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <div class="card bg-light">
                                    <div class="card-body text-center">
                                        <h6 class="text-warning">Total Tax</h6>
                                        <h4 class="text-warning"><?php echo CURRENCY . number_format($ovl_tax_summary['ovl_total_tax'], 2); ?></h4>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>


        <!-- Monthly Sales Chart -->
        <!-- <div class="row mt-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title">Sales Overview (Last 6 Months)</h5>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <canvas id="salesChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div> -->

        <!-- Recent Invoices -->
        <div class="row mt-5">

             <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Recent Quotations</h5>
                        <a href="modules/quotation/view.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Quotation No</th>
                                        <th>Party Name</th>
                                        <th>Date</th>
                                        <th>Net Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $recent_quotations_query = "SELECT q.id, q.quotation_no, p.name as party_name, q.quotation_date, q.net_amount 
                                                                FROM ff_sch.quotation q 
                                                                LEFT JOIN ff_sch.parties p ON q.party_id = p.id 
                                                                WHERE q.fiscal_year_id = :fiscal_year_id
                                                                ORDER BY q.quotation_date DESC, q.created_at DESC LIMIT 5";
                                    $stmt_recent_quotations = $db->prepare($recent_quotations_query);
                                    $stmt_recent_quotations->bindParam(':fiscal_year_id', $fiscal_year_id);
                                    $stmt_recent_quotations->execute();
                                    $recent_quotations = $stmt_recent_quotations->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (!empty($recent_quotations)) {
                                        foreach ($recent_quotations as $quotation) {
                                            echo "<tr><td><a href='modules/quotation/edit.php?id={$quotation['id']}' target='_blank'>{$quotation['quotation_no']}</a></td><td>{$quotation['party_name']}</td><td>" . date('d-m-Y', strtotime($quotation['quotation_date'])) . "</td><td><strong>" . CURRENCY . number_format($quotation['net_amount'], 2) . "</strong></td></tr>";
                                        }
                                    } else {
                                        echo '<tr><td colspan="4" class="text-center">No recent quotations found.</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

             <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Recent Estimates</h5>
                        <a href="modules/estimate/view.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Estimate No</th>
                                        <th>Party Name</th>
                                        <th>Date</th>
                                        <th>Net Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $recent_estimates_query = "SELECT e.id, e.estimate_no, p.name as party_name, e.estimate_date, e.net_amount 
                                                               FROM ff_sch.estimate e 
                                                               LEFT JOIN ff_sch.parties p ON e.party_id = p.id 
                                                               WHERE e.fiscal_year_id = :fiscal_year_id
                                                               ORDER BY e.estimate_date DESC, e.created_at DESC LIMIT 5";
                                    $stmt_recent_estimates = $db->prepare($recent_estimates_query);
                                    $stmt_recent_estimates->bindParam(':fiscal_year_id', $fiscal_year_id);
                                    $stmt_recent_estimates->execute();
                                    $recent_estimates = $stmt_recent_estimates->fetchAll(PDO::FETCH_ASSOC);

                                    if (!empty($recent_estimates)) {
                                        foreach ($recent_estimates as $estimate) {
                                            echo "<tr><td><a href='modules/estimate/edit.php?id={$estimate['id']}' target='_blank'>{$estimate['estimate_no']}</a></td><td>{$estimate['party_name']}</td><td>" . date('d-m-Y', strtotime($estimate['estimate_date'])) . "</td><td><strong>" . CURRENCY . number_format($estimate['net_amount'], 2) . "</strong></td></tr>";
                                        }
                                    } else {
                                        echo '<tr><td colspan="4" class="text-center">No recent estimates found.</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
             <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Recent Invoices</h5>
                        <a href="modules/invoice/view.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Invoice No</th>
                                        <th>Party Name</th>
                                        <th>Date</th>
                                        <th>Net Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $recent_invoices_query = "SELECT i.invoice_no, p.name as party_name, i.invoice_date, i.net_amount 
                                                   FROM ff_sch.invoices i 
                                                   LEFT JOIN ff_sch.parties p ON i.party_id = p.id 
                                                   WHERE i.fiscal_year_id = :fiscal_year_id
                                                   ORDER BY i.invoice_date DESC, i.created_at DESC LIMIT 5";
                                    $stmt_invoices = $db->prepare($recent_invoices_query);
                                    $stmt_invoices->bindParam(':fiscal_year_id', $fiscal_year_id);
                                    $stmt_invoices->execute();
                                    $recent_invoices = $stmt_invoices->fetchAll(PDO::FETCH_ASSOC);
                                    
                                    if (!empty($recent_invoices)) {
                                        foreach ($recent_invoices as $invoice) {
                                            echo "<tr><td><a href='modules/invoice/edit.php?id={$invoice['invoice_no']}&year={$fiscal_year_id}' target='_blank'>{$invoice['invoice_no']}</a></td><td>{$invoice['party_name']}</td><td>" . date('d-m-Y', strtotime($invoice['invoice_date'])) . "</td><td><strong>" . CURRENCY . number_format($invoice['net_amount'], 2) . "</strong></td></tr>";
                                        }
                                    } else {
                                        echo '<tr><td colspan="4" class="text-center">No recent invoices found.</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

             <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Recent Receipts</h5>
                        <a href="modules/receipt/view.php" class="btn btn-sm btn-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Receipt No</th>
                                        <th>Agent Name</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $recent_receipts_query = "SELECT rh.id, rh.receipt_no, rh.receipt_date, rh.agent_name, rh.total_receipt_amount 
                                                            FROM ff_sch.rcpt_hdr rh
                                                            WHERE rh.fiscal_year_id = :fiscal_year_id
                                                            ORDER BY rh.receipt_date DESC, rh.created_at DESC LIMIT 5";
                                    $stmt_receipts = $db->prepare($recent_receipts_query);
                                    $stmt_receipts->bindParam(':fiscal_year_id', $fiscal_year_id);
                                    $stmt_receipts->execute();
                                    $recent_receipts = $stmt_receipts->fetchAll(PDO::FETCH_ASSOC);

                                    if (!empty($recent_receipts)) {
                                        foreach ($recent_receipts as $receipt) {
                                            echo "<tr>
                                                    <td><a href='modules/receipt/receipt_print.php?id={$receipt['id']}' target='_blank'>{$receipt['receipt_no']}</a></td>
                                                    <td>{$receipt['agent_name']}</td>
                                                    <td>" . date('d-m-Y', strtotime($receipt['receipt_date'])) . "</td>
                                                    <td><strong>" . CURRENCY . number_format($receipt['total_receipt_amount'], 2) . "</strong></td>
                                                </tr>";
                                        }
                                    } else {
                                        echo '<tr><td colspan="4" class="text-center">No recent receipts found.</td></tr>';
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/jquery-3.6.0.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/jquery.dataTables.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/dataTables.bootstrap5.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/chart.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/sweetalert2@11.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/script.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/sidebar.js"></script>
    
    <script>
        $(document).ready(function() {
            /* // Sales Chart
            const salesCtx = document.getElementById('salesChart').getContext('2d');
            const salesChart = new Chart(salesCtx, {
                type: 'bar',
                data: {
                    labels: [<?php 
                        foreach(array_reverse($monthly_sales) as $sale) {
                            $month = date('M Y', strtotime($sale['month']));
                            echo "'$month',";
                        }
                    ?>],
                    datasets: [{
                        label: 'Monthly Sales (<?php echo CURRENCY; ?>)',
                        data: [<?php 
                            foreach(array_reverse($monthly_sales) as $sale) {
                                echo $sale['monthly_sales'] . ',';
                            }
                        ?>],
                        backgroundColor: 'rgba(54, 162, 235, 0.5)',
                        borderColor: 'rgba(54, 162, 235, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '<?php echo CURRENCY; ?>' + value.toLocaleString();
                                }
                            }
                        }
                    },
                    plugins: {
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Sales: <?php echo CURRENCY; ?>' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    }
                }
            }); */
        });
    </script>
</body>
</html>