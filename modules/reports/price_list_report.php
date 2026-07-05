<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";

$database = new Database();
$db = $database->getConnection();

// Fetch all active price codes for the dropdown
$price_codes_query = "SELECT pc.id, pc.name, pc.code, fy.year_name 
                      FROM ff_sch.price_codes pc
                      JOIN ff_sch.fiscal_years fy ON pc.fiscal_year_id = fy.id
                      WHERE pc.is_active = true 
                      ORDER BY fy.year_name DESC, pc.name ASC";
$price_codes = $db->query($price_codes_query)->fetchAll(PDO::FETCH_ASSOC);

$selected_price_code_id = null;
$product_prices = [];
$selected_price_code_details = null;

if (isset($_GET['price_code_id']) && !empty($_GET['price_code_id'])) {
    $selected_price_code_id = $_GET['price_code_id'];

    // Get details of the selected price code
    $stmt = $db->prepare("SELECT name, code FROM ff_sch.price_codes WHERE id = :id");
    $stmt->bindParam(':id', $selected_price_code_id, PDO::PARAM_INT);
    $stmt->execute();
    $selected_price_code_details = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch product prices for the selected price code
    $prices_query = "SELECT 
                        p.product_id, 
                        p.name, 
                        p.uom, 
                        p.per_box_pieces,
                        COALESCE(pp.rate, 0.00) as rate
                     FROM 
                        ff_sch.products p
                     LEFT JOIN 
                        ff_sch.product_prices pp ON p.id = pp.product_id AND pp.price_code_id = :price_code_id
                     WHERE 
                        p.is_active = true
                     ORDER BY 
                        p.name ASC";
    
    $stmt = $db->prepare($prices_query);
    $stmt->bindParam(':price_code_id', $selected_price_code_id, PDO::PARAM_INT);
    $stmt->execute();
    $product_prices = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Price List Report - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/img/favicon.png">
    <link href="<?php echo ASSETS_URL; ?>/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    <style>
        @media print {
            body * { visibility: hidden; }
            #print-area, #print-area * { visibility: visible; }
            #print-area { position: absolute; left: 0; top: 0; width: 100%; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <?php include "../../includes/header.php"; ?>
    <?php include "../../includes/sidebar.php"; ?>

    <div class="container-fluid">
        <h2 class="mb-4 no-print">Price List Report</h2>

        <div class="card mb-4 no-print">
            <div class="card-header"><h5 class="card-title">Select Price Code</h5></div>
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-4">
                        <label for="price_code_id" class="form-label">Price Code</label>
                        <select class="form-select" id="price_code_id" name="price_code_id" required onchange="this.form.submit()">
                            <option value="">-- Select a Price Code --</option>
                            <?php foreach ($price_codes as $pc) : ?>
                                <option value="<?php echo $pc['id']; ?>" <?php echo ($selected_price_code_id == $pc['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($pc['name']) . ' (' . htmlspecialchars($pc['year_name']) . ')'; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <?php if ($selected_price_code_id && $selected_price_code_details): ?>
        <div id="print-area">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="card-title mb-0">Price List for: <?php echo htmlspecialchars($selected_price_code_details['name']); ?></h5>
                        <small class="text-muted">Code: <?php echo htmlspecialchars($selected_price_code_details['code']); ?></small>
                    </div>
                    <button type="button" onclick="printReport()" class="btn btn-info no-print"><i class="fas fa-print me-2"></i>Print Report</button>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-bordered data-table">
                            <thead>
                                <tr>
                                    <th>Product ID</th>
                                    <th>Product Name</th>
                                    <th>UOM</th>
                                    <th>Per Box</th>
                                    <th>Rate (<?php echo CURRENCY; ?>)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($product_prices as $item): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($item['product_id']); ?></td>
                                    <td><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo htmlspecialchars($item['uom']); ?></td>
                                    <td><?php echo htmlspecialchars($item['per_box_pieces']); ?></td>
                                    <td class="text-end"><?php echo number_format($item['rate'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/jquery-3.6.0.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/jquery.dataTables.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/dataTables.bootstrap5.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/sweetalert2@11.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/script.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/sidebar.js"></script>
    <script>
        $(document).ready(function() {
            $('.data-table').DataTable({
                "pageLength": 25,
                "lengthMenu": [ [10, 25, 50, -1], [10, 25, 50, "All"] ],
                "language": { "search": "_INPUT_", "searchPlaceholder": "Search..." }
            });
        });

        function printReport() {
            const priceCodeId = $('#price_code_id').val();
            if (priceCodeId) {
                window.open(`print_price_list.php?price_code_id=${priceCodeId}`, '_blank');
            }
        }
    </script>
</body>
</html>