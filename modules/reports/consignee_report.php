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

// Fetch Parties with sales activity in the date range
$query = "SELECT 
            p.party_id,
            p.name,
            p.city,
            p.state,
            COUNT(i.id) as invoice_count,
            SUM(i.net_amount) as total_sales,
            SUM(i.taxable_amount) as total_taxable,
            SUM(i.discount) as total_discount
          FROM ff_sch.parties p
          JOIN ff_sch.invoices i ON p.id = i.party_id
          WHERE i.invoice_date BETWEEN :start_date AND :end_date
          GROUP BY p.id, p.party_id, p.name, p.city, p.state
          ORDER BY total_sales DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->execute();
$Parties = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_sales_all = array_sum(array_column($Parties, 'total_sales'));
$total_invoices_all = array_sum(array_column($Parties, 'invoice_count'));
$avg_invoice_value = $total_invoices_all > 0 ? $total_sales_all / $total_invoices_all : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Party Report - <?php echo SITE_NAME; ?></title>
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
        <h2 class="mb-4">Party Report</h2>

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
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-filter me-2"></i>Apply Filter
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row">
            <div class="col-md-4">
                <div class="widget-small primary coloured-icon"><i class="icon fa fa-users fa-3x"></i>
                    <div class="info">
                        <h4>Parties</h4>
                        <p><b><?php echo count($Parties); ?></b></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="widget-small info coloured-icon"><i class="icon fa fa-money-bill-alt fa-3x"></i>
                    <div class="info">
                        <h4>Total Sales</h4>
                        <p><b><?php echo CURRENCY . number_format($total_sales_all, 2); ?></b></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="widget-small warning coloured-icon"><i class="icon fa fa-file-invoice-dollar fa-3x"></i>
                    <div class="info">
                        <h4>Avg. Invoice Value</h4>
                        <p><b><?php echo CURRENCY . number_format($avg_invoice_value, 2); ?></b></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Party Sales Summary</h5>
                <a href="pdf_party_report.php?<?php echo http_build_query($_GET); ?>" class="btn btn-danger" target="_blank">
                    <i class="fas fa-file-pdf me-2"></i>Export to PDF
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered data-table">
                        <thead>
                            <tr>
                                <th>Party Name</th>
                                <th>Location</th>
                                <th>Invoices</th>
                                <th>Total Sales</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($Parties as $party): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($party['name']); ?></td>
                                <td><?php echo htmlspecialchars($party['city'] . ', ' . $party['state']); ?></td>
                                <td class="text-center"><?php echo $party['invoice_count']; ?></td>
                                <td class="text-end fw-bold"><?php echo CURRENCY . number_format($party['total_sales'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/jquery-3.6.0.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/jquery.dataTables.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/dataTables.bootstrap5.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/script.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/sidebar.js"></script>
    <script>
        $(document).ready(function() {
            $('.data-table').DataTable({ "pageLength": 25, "order": [[ 3, "desc" ]], "language": { "search": "_INPUT_", "searchPlaceholder": "Search..." } });
        });
    </script>
</body>
</html>