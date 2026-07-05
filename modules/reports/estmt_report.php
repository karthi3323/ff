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
$party_id = $_GET['party_id'] ?? null;

// Base query
$query = "SELECT 
            e.id, e.estimate_no, e.estimate_date, p.name as party_name,
            e.taxable_amount, e.net_amount, e.goods_value, e.discount_percent,
            (e.net_amount - e.taxable_amount) as total_tax,
            (e.goods_value * e.discount_percent / 100) as discount
          FROM ff_sch.estimate e
          JOIN ff_sch.parties p ON e.party_id = p.id
          WHERE e.estimate_date BETWEEN :start_date AND :end_date";

if ($party_id) {
    $query .= " AND e.party_id = :party_id";
}

$query .= " ORDER BY e.estimate_date DESC, e.estimate_no DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
if ($party_id) {
    $stmt->bindParam(':party_id', $party_id, PDO::PARAM_INT);
}
$stmt->execute();
$estimates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_taxable = array_sum(array_column($estimates, 'taxable_amount'));
$total_net = array_sum(array_column($estimates, 'net_amount'));
$total_discount = array_sum(array_column($estimates, 'discount'));
$total_tax = array_sum(array_column($estimates, 'total_tax'));

// Fetch parties for the dropdown
$parties = $db->query("SELECT id, name FROM ff_sch.parties WHERE is_active = true ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estimate Report - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/img/favicon.png">
    <link href="<?php echo ASSETS_URL; ?>/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/select2.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/select2-bootstrap4.min.css" rel="stylesheet" />
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
</head>
<body>
    <?php include "../../includes/header.php"; ?>
    <?php include "../../includes/sidebar.php"; ?>

    <div class="container-fluid">
        <h2 class="mb-4">Estimate Report</h2>

        <div class="card mb-4">
            <div class="card-header"><h5 class="card-title">Filters</h5></div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label for="start_date" class="form-label">From Date</label>
                        <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="end_date" class="form-label">To Date</label>
                        <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="party_id" class="form-label">Party (Optional)</label>
                        <select class="form-select select2" id="party_id" name="party_id">
                            <option value="">All Parties</option>
                            <?php foreach ($parties as $party): ?>
                                <option value="<?php echo $party['id']; ?>" <?php echo ($party_id == $party['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($party['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-primary w-100"><i class="fas fa-filter me-2"></i>Apply Filter</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card shadow-sm">
                    <div class="card-body d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                <i class="fas fa-file-contract fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="card-title text-muted mb-1">Estimates</h5>
                            <p class="card-text h4 fw-bold mb-0"><?php echo count($estimates); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm">
                    <div class="card-body d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="bg-info text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                <i class="fas fa-money-bill-wave fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="card-title text-muted mb-1">Taxable</h5>
                            <p class="card-text h4 fw-bold mb-0"><?php echo CURRENCY . number_format($total_taxable, 2); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm">
                    <div class="card-body d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="bg-warning text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                <i class="fas fa-tags fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="card-title text-muted mb-1">Discount</h5>
                            <p class="card-text h4 fw-bold mb-0"><?php echo CURRENCY . number_format($total_discount, 2); ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm">
                    <div class="card-body d-flex align-items-center">
                        <div class="flex-shrink-0 me-3">
                            <div class="bg-danger text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 50px; height: 50px;">
                                <i class="fas fa-receipt fa-lg"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1">
                            <h5 class="card-title text-muted mb-1">Net Total</h5>
                            <p class="card-text h4 fw-bold mb-0"><?php echo CURRENCY . number_format($total_net, 2); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Estimates</h5>
                <a href="pdf_estimate_report.php?<?php echo http_build_query($_GET); ?>" class="btn btn-danger" target="_blank">
                    <i class="fas fa-file-pdf me-2"></i>Export to PDF
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered data-table">
                        <thead>
                            <tr>
                                <th>Estimate #</th><th>Date</th><th>Party</th><th>Taxable</th><th>Net Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($estimates as $estimate): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($estimate['estimate_no']); ?></td>
                                <td><?php echo date('d-m-Y', strtotime($estimate['estimate_date'])); ?></td>
                                <td><?php echo htmlspecialchars($estimate['party_name']); ?></td>
                                <td class="text-end"><?php echo number_format($estimate['taxable_amount'], 2); ?></td>
                                <td class="text-end fw-bold"><?php echo number_format($estimate['net_amount'], 2); ?></td>
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
    <script src="<?php echo ASSETS_URL; ?>/js/select2.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/script.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/sidebar.js"></script>
    <script>
        $(document).ready(function() {
            $('.data-table').DataTable({ "pageLength": 25, "order": [[ 1, "desc" ]], "language": { "search": "_INPUT_", "searchPlaceholder": "Search..." } });
            $('.select2').select2({ theme: 'bootstrap4' });
        });
    </script>
</body>
</html>