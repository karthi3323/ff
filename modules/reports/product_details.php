<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";

$database = new Database();
$db = $database->getConnection();
// Fetch all products with categories
$products = $db->query("SELECT p.*
                       FROM ff_sch.products p
                       WHERE p.is_active = true 
                       ORDER BY p.name")->fetchAll(PDO::FETCH_ASSOC);

$masters = $db->query("SELECT p.*
                       FROM ff_sch.master p
                       WHERE p.is_active = true ")->fetchAll(PDO::FETCH_ASSOC);
                       
foreach($masters as $master) {
    $hsn_code = $master['hsn_code'];
}
// Get product statistics
$product_stats = [];

foreach($products as $product) {
    $stats_query = "SELECT 
                    COUNT(ii.id) as times_sold,
                    SUM(ii.qty) as total_quantity,
                    SUM(ii.total_amount) as total_sales,
                    MAX(i.invoice_date) as last_sold
                   FROM ff_sch.invoice_items ii 
                   LEFT JOIN ff_sch.invoices i ON ii.invoice_id = i.id
                   WHERE ii.product_id = :product_id ";
    $stmt = $db->prepare($stats_query);
    $stmt->bindParam(':product_id', $product['id']);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
// exit;
    // Handle case when no data found
    if (!$stats || $stats['times_sold'] === null) {
        $stats = [
            'times_sold' => 0,
            'total_quantity' => 0,
            'total_sales' => 0.00,
            'last_sold' => null
        ];
    }
    
    $product_stats[$product['id']] = $stats;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Details Report - <?php echo SITE_NAME; ?></title>
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
            <h2 class="mb-4">Product Details Report</h2>
            <!-- Summary Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card bg-primary text-white">
                        <div class="card-body">
                            <h6>Total Products</h6>
                            <h3><?php echo count($products); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card bg-success text-white">
                        <div class="card-body">
                            <h6>Active Products</h6>
                            <h3><?php echo count($products); ?></h3>
                        </div>
                    </div>
                </div>
                <!-- <div class="col-md-3">
                    <div class="card bg-info text-white">
                        <div class="card-body">
                            <h6>Categories</h6>
                            <h3>
                                <?php
                                    $categories = [];
                                    foreach($products as $product) {
                                        if($product['category_name']) {
                                            $categories[$product['category_name']] = true;
                                        }
                                    }
                                    echo count($categories);
                                ?>
                            </h3>
                        </div>
                    </div>
                </div> -->
                <!-- <div class="col-md-3">
                    <div class="card bg-warning text-white">
                        <div class="card-body">
                            <h6>Products with HSN</h6>
                            <h3>
                                <?php
                                    $hsn_count = 0;
                                    foreach($products as $product) {
                                        if(!empty($product['hsn_code'])) $hsn_count++;
                                    }
                                    echo $hsn_count;
                                ?>
                            </h3>
                        </div>
                    </div>
                </div> -->
            </div>

            <!-- Products Table -->
            <div class="card">
                <div class="card-header  d-flex align-items-end justify-content-between ">
                    <h5 class="card-title">All Products Details</h5>
                    <a style="width:12% !important;" href="pdf_product_details.php?<?php echo http_build_query($_GET); ?>" class="btn btn-danger w-100" target="_blank">
                        <i class="fas fa-file-pdf me-2"></i>PDF
                    </a>
                </div>
                <div class="card-body">
                    <table id ="ProductTable" class="table table-striped data-table">
                        <thead>
                            <tr>
                                <th>Product ID</th>
                                <th>Name</th>
                                <th>Per</th>
                                <th>Carton Contents</th>
                                <th>Rate</th>
                                <th>HSN Code</th>
                                <th>Times Sold</th>
                                <th>Total Quantity</th>
                                <th>Total Sales</th>
                                <th>Last Sold</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($products as $product): 
                                $stats = $product_stats[$product['id']] ?? ['times_sold' => 0, 'total_quantity' => 0, 'total_sales' => 0, 'last_sold' => null];
                            ?>
                            <tr>
                                <td><?php echo $product['product_id']; ?></td>
                                <td>
                                    <strong><?php echo $product['name']; ?></strong>
                                </td>
                                
                                <td>
                                    <span class="badge bg-primary"><?php echo $product['carton_contents']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $product['per_box_pieces']; ?></span>
                                </td>
                                <td>
                                    <strong><?php echo CURRENCY . number_format($product['rate'], 2); ?></strong>
                                </td>
                                <td>
                                    <?php if(!empty($product['hsn_code'])): ?>
                                        <span class="badge bg-success"><?php echo $product['hsn_code']; ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary"><?php echo $hsn_code; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-warning"><?php echo $stats['times_sold']; ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo $stats['total_quantity']; ?></span>
                                </td>
                                <td>
                                    <strong><?php echo CURRENCY . number_format($stats['total_sales'] ?? 0, 2); ?></strong>
                                </td>
                                <td>
                                    <?php if($stats['last_sold']): ?>
                                        <?php echo date('d M Y', strtotime($stats['last_sold'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not sold yet</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($product['is_active']): ?>
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

            <!-- Category-wise Summary -->
            <!-- <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Category-wise Product Distribution</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Number of Products</th>
                                    <th>Percentage</th>
                                    <th>Total Sales</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $category_stats = [];
                                foreach($products as $product) {
                                    $category = $product['category_name'] ?: 'General';
                                    if(!isset($category_stats[$category])) {
                                        $category_stats[$category] = ['count' => 0, 'sales' => 0];
                                    }
                                    $category_stats[$category]['count']++;
                                    $category_stats[$category]['sales'] += ($product_stats[$product['id']]['total_sales'] ?? 0);
                                }
                                
                                foreach($category_stats as $category => $stats):
                                    $percentage = ($stats['count'] / count($products)) * 100;
                                ?>
                                <tr>
                                    <td><strong><?php echo $category; ?></strong></td>
                                    <td><?php echo $stats['count']; ?></td>
                                    <td>
                                        <div class="progress">
                                            <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%">
                                                <?php echo number_format($percentage, 1); ?>%
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo CURRENCY . number_format($stats['sales'], 2); ?></strong>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div> -->

            <!-- Top Selling Products -->
            <div class="card mt-4">
                <div class="card-header">
                    <h5 class="card-title">Top 10 Selling Products</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table id="TopProductTable" class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Product Name</th>
                                    <th>Per</th>
                                    <th>Times Sold</th>
                                    <th>Total Quantity</th>
                                    <th>Total Sales</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $top_products = [];
                                foreach($products as $product) {
                                    $stats = $product_stats[$product['id']] ?? ['times_sold' => 0, 'total_quantity' => 0, 'total_sales' => 0];
                                    if($stats['total_sales'] > 0) {
                                        $top_products[] = [
                                            'name' => $product['name'],
                                            'category' => $product['category_name'] ?: 'General',
                                            'per_box_pieces' => $product['per_box_pieces'],
                                            'times_sold' => $stats['times_sold'],
                                            'total_quantity' => $stats['total_quantity'],
                                            'total_sales' => $stats['total_sales']
                                        ];
                                    }
                                }
                                
                                // Sort by total sales descending
                                usort($top_products, function($a, $b) {
                                    return $b['total_sales'] - $a['total_sales'];
                                });
                                
                                $top_products = array_slice($top_products, 0, 10);
                                $rank = 1;
                                
                                foreach($top_products as $product):
                                ?>
                                <tr>
                                    <td>
                                        <span class="badge bg-<?php echo $rank <= 3 ? 'primary' : 'secondary'; ?>">
                                            #<?php echo $rank++; ?>
                                        </span>
                                    </td>
                                    <td><strong><?php echo $product['name']; ?></strong></td>
                                    <td><?php echo $product['per_box_pieces']; ?></td>
                                    <td><?php echo $product['times_sold']; ?></td>
                                    <td><?php echo $product['total_quantity']; ?></td>
                                    <td><strong><?php echo CURRENCY . number_format($product['total_sales'], 2); ?></strong></td>
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
            $('#ProductTable, #TopProductTable').DataTable({
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