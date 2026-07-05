<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";
// require_once "../../includes/audit_helper.php";

$database = new Database();
$db = $database->getConnection();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: view.php");
    exit;
}

$invoice_id = $_GET['id'];
$fiscal_year = $_GET['year'];

// Get invoice data
$invoice_query = "SELECT i.*, p.name as party_name, p.state as party_state, 
                         p.city as party_city, p.gst_no as party_gst, TRIM(
            COALESCE(address_line1, '') ||
            CASE 
                WHEN address_line2 IS NOT NULL AND address_line2 <> '' 
                THEN ', ' || address_line2 
                ELSE '' 
            END
        ) AS party_address, p.agent_name as party_agent
                 FROM ff_sch.invoices i 
                 LEFT JOIN ff_sch.parties p ON i.party_id = p.id 
                 WHERE i.invoice_no = :id and i.fiscal_year_id = :fiscal_year_id";
$stmt = $db->prepare($invoice_query);
$stmt->bindParam(':id', $invoice_id);
$stmt->bindParam(':fiscal_year_id', $fiscal_year);
$stmt->execute();
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    echo "<script>alert('Invoice not found!'); window.location.href='view.php';</script>";
    exit;
}

$fiscal_year = $invoice['fiscal_year_id'];

if($_POST) {
    try {
        $db->beginTransaction();
        
        // Calculate new pending amount based on changes to net_amount
        $amount_paid = (float)$invoice['net_amount'] - (float)$invoice['pending_amount'];
        $new_pending_amount = (float)$_POST['net_amount'] - $amount_paid;

        $update_query = "UPDATE ff_sch.invoices SET 
                        party_id = :party_id, 
                        price_code_id = :price_code_id,
                        dispatch_from = :dispatch_from, 
                        dispatch_through = :dispatch_through, 
                        invoice_date = :invoice_date, pending_amount = :pending_amount,
                        taxable_amount = :taxable_amount, 
                        discount_type = :discount_type, 
                        discount_value = :discount_value, 
                        discount_amount = :discount_amount, 
                        discount = :discount, 
                        sgst_percent = :sgst_percent, 
                        cgst_percent = :cgst_percent, 
                        igst_percent = :igst_percent, 
                        sgst_amount = :sgst_amount, 
                        cgst_amount = :cgst_amount, 
                        igst_amount = :igst_amount, 
                        total_tax = :total_tax, 
                        net_amount = :net_amount,
                        p_place = :p_place,
                        agent_name = :agent_name,
                        p_state = :p_state,
                        p_gst = :p_gst,
                        p_address = :p_address,
                        round_off = :round_off,
                        tax_type = :tax_type,
                        vehicle_no = :vehicle_no,
                        eway_bill_no = :eway_bill_no,
                        transport_name = :transport_name,
                        transport_gst = :transport_gst,
                        hsn_code = :hsn_code
                        WHERE id = :id";
        
        $upd_stmt = $db->prepare($update_query);
        $upd_stmt->bindParam(':party_id', $_POST['party_id']);
        $upd_stmt->bindParam(':price_code_id', $_POST['price_code_id']);
        $upd_stmt->bindParam(':dispatch_from', $_POST['dispatch_from']);
        $upd_stmt->bindParam(':dispatch_through', $_POST['dispatch_through']);
        $upd_stmt->bindParam(':invoice_date', $_POST['invoice_date']);
        $upd_stmt->bindParam(':taxable_amount', $_POST['taxable_amount']);
        $upd_stmt->bindParam(':pending_amount', $new_pending_amount);
        $upd_stmt->bindParam(':discount_type', $_POST['discount_type']);
        $upd_stmt->bindParam(':discount_value', $_POST['discount_value']);
        $upd_stmt->bindParam(':discount_amount', $_POST['discount_amount']);
        $upd_stmt->bindParam(':discount', $_POST['discount']);
        $upd_stmt->bindParam(':sgst_percent', $_POST['sgst_percent']);
        $upd_stmt->bindParam(':cgst_percent', $_POST['cgst_percent']);
        $upd_stmt->bindParam(':igst_percent', $_POST['igst_percent']);
        $upd_stmt->bindParam(':sgst_amount', $_POST['sgst_amount']);
        $upd_stmt->bindParam(':cgst_amount', $_POST['cgst_amount']);
        $upd_stmt->bindParam(':igst_amount', $_POST['igst_amount']);
        $upd_stmt->bindParam(':total_tax', $_POST['total_tax']);
        $upd_stmt->bindParam(':net_amount', $_POST['net_amount']);
        $upd_stmt->bindParam(':p_place', $_POST['p_place']);
        $upd_stmt->bindParam(':agent_name', $_POST['agent_name']);
        $upd_stmt->bindParam(':p_state', $_POST['p_state']);
        $upd_stmt->bindParam(':p_gst', $_POST['p_gst']);
        $upd_stmt->bindParam(':p_address', $_POST['p_address']);
        $upd_stmt->bindParam(':round_off', $_POST['round_off']);
        
        $tax_type = $_POST['tax_type'] ?? 'intrastate';
        $upd_stmt->bindParam(':tax_type', $tax_type);
        
        $upd_stmt->bindParam(':vehicle_no', $_POST['veh_no']);
        $upd_stmt->bindParam(':eway_bill_no', $_POST['eway']);
        $upd_stmt->bindParam(':transport_name', $_POST['transport_name']);
        $upd_stmt->bindParam(':transport_gst', $_POST['transport_gst']);
        $upd_stmt->bindParam(':hsn_code', $_POST['hsn_cd']);
        $upd_stmt->bindParam(':id', $invoice['id']);
        $upd_stmt->execute();
        
        // Delete old items
        $del_stmt = $db->prepare("DELETE FROM ff_sch.invoice_items WHERE invoice_id = :invoice_id");
        $del_stmt->bindParam(':invoice_id', $invoice['id']);
        $del_stmt->execute();

        // Insert new items
        $items_query = "INSERT INTO ff_sch.invoice_items (
            invoice_id, product_id, carton_contents, uom, rate, cartons, total_amount, carton_from, carton_to, qty, fiscal_year_id, discount_eligible
        ) VALUES (
            :invoice_id, :product_id, :carton_contents, :uom, :rate, :cartons, :total_amount, :carton_from, :carton_to, :qty, :fiscal_year_id, :discount_eligible
        )";
        $items_stmt = $db->prepare($items_query);
        
        foreach($_POST['product_id'] as $key => $product_id) {
            if(!empty($product_id) && !empty($_POST['cartons'][$key]) && $_POST['cartons'][$key] > 0) {
                $items_stmt->bindParam(':invoice_id', $invoice['id']);
                $items_stmt->bindParam(':product_id', $product_id);
                $items_stmt->bindParam(':carton_contents', $_POST['carton_contents'][$key]);
                $items_stmt->bindParam(':uom', $_POST['uom'][$key]);
                $items_stmt->bindParam(':rate', $_POST['rate'][$key]);
                $items_stmt->bindParam(':cartons', $_POST['cartons'][$key]);
                $items_stmt->bindParam(':total_amount', $_POST['total_amount'][$key]);
                
                $carton_from = $_POST['carton_from'][$key] ?? 0;
                $carton_to = $_POST['carton_to'][$key] ?? 0;
                $qty1 = $_POST['qty'][$key] ?? 0;
                $items_stmt->bindParam(':carton_from', $carton_from);
                $items_stmt->bindParam(':carton_to', $carton_to);
                $items_stmt->bindParam(':qty', $qty1);
                $items_stmt->bindParam(':fiscal_year_id', $fiscal_year);
                
                $discount_eligible = $_POST['discount_eligible'][$key] ?? 0;
                $items_stmt->bindParam(':discount_eligible', $discount_eligible);
                
                $items_stmt->execute();
            }
        }
        
        $db->commit();
        
        echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire('Success!', 'Invoice updated successfully!', 'success')
                        .then(() => { window.location.href = 'view.php'; });
                });
            </script>";
            
        // Refresh data
        $stmt = $db->prepare($invoice_query);
        $stmt->bindParam(':id', $invoice_id);
        $stmt->bindParam(':fiscal_year_id', $fiscal_year);
        $stmt->execute();
        $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch(Exception $e) {
        $db->rollBack();
        $error_message = addslashes($e->getMessage());
        echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire('Error!', 'Failed to update invoice: {$error_message}', 'error');
                });
            </script>";
        error_log("Invoice Update Error: " . $e->getMessage());
    }
}

// Get items
$items_query = "SELECT ii.*, p.name as product_name, p.carton_contents as product_ctn, p.uom as product_uom, p.per_box_pieces 
                FROM ff_sch.invoice_items ii 
                LEFT JOIN ff_sch.products p ON ii.product_id = p.id 
                WHERE ii.invoice_id = :invoice_id ORDER BY ii.id";
$stmt = $db->prepare($items_query);
$stmt->bindParam(':invoice_id', $invoice['id']);
$stmt->execute();
$invoice_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Dropdowns
$parties = $db->query("SELECT * FROM ff_sch.parties WHERE is_active = true ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$products = $db->query("SELECT id, name, carton_contents, uom, per_box_pieces FROM ff_sch.products WHERE is_active = true ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$transports = $db->query("SELECT id, name, gst_no FROM ff_sch.transport WHERE is_active = true ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$master = $db->query("SELECT * FROM ff_sch.master WHERE is_active = true LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$hsn_cd = $invoice['hsn_code'] ?: $master['hsn_code'];

$last_carton_query = $db->prepare("SELECT MAX(carton_to) as last_carton FROM ff_sch.invoice_items ii 
                                JOIN ff_sch.invoices i ON ii.invoice_id = i.id 
                                WHERE i.fiscal_year_id = :fiscal_year_id");
$last_carton_query->bindParam(':fiscal_year_id', $fiscal_year);
// $last_carton_query->bindParam(':invoice_id', $invoice['id']);
$last_carton_query->execute();
$last_carton = $last_carton_query->fetch(PDO::FETCH_ASSOC);
echo $next_carton_start = $last_carton['last_carton'] ? $last_carton['last_carton'] + 1 : 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Invoice - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/img/favicon.png">
    <link href="<?php echo ASSETS_URL; ?>/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="<?php echo ASSETS_URL; ?>/css/select2.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/select2-bootstrap4.min.css" rel="stylesheet" />
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    <script src="<?php echo ASSETS_URL; ?>/js/sweetalert2@11.js"></script>
    <style>
        .tax-input-group {
            margin-bottom: 1rem;
        }
        .tax-input-group label {
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        .amount-display {
            font-size: 1.25rem;
            font-weight: 600;
            color: #0d6efd;
        }
        .product-select {
            width: 100% !important;
        }
        .tax-type-btn {
            margin-bottom: 1rem;
        }
        .discount-type-btn {
            margin-bottom: 1rem;
        }
        .select2-container {
            z-index: 1055 !important;
        }
        .table-responsive {
            overflow-x: auto;
        }
        .carton-range-display {
            padding: 0.375rem 0.75rem;
            background-color: #f8f9fa;
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            display: block;
            min-height: 38px;
        }
    </style>
</head>
<body>
    <?php include "../../includes/header.php"; ?>
    <?php include "../../includes/sidebar.php"; ?>

    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Edit Invoice - <?php echo htmlspecialchars($invoice['invoice_no']); ?></h2>
            <div>
                <a href="view.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to List
                </a>
                <button type="button" onclick="showPrintOptions('<?php echo $invoice['invoice_no']; ?>', '<?php echo $fiscal_year; ?>')" class="btn btn-info">
                    <i class="fas fa-print me-2"></i>Print
                </button>
            </div>
        </div>

        <form method="POST" id="invoice-form">
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title">Invoice Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Invoice No</label>
                                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($invoice['invoice_no']); ?>" readonly>
                                    <small class="text-muted">Invoice number cannot be changed</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Invoice Date</label>
                                    <input type="date" class="form-control" name="invoice_date" value="<?php echo htmlspecialchars($invoice['invoice_date']); ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Party Name</label>
                                <select class="form-select party-select" name="party_id" id="party_id" required>
                                    <option value="">Select Party</option>
                                    <?php foreach($parties as $party): ?>
                                        <option value="<?php echo $party['id']; ?>" 
                                                data-state="<?php echo $party['state']; ?>"
                                                <?php echo $party['id'] == $invoice['party_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($party['name']); ?> (<?php echo htmlspecialchars($party['state']); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Agent Name</label>
                                    <input type="text" class="form-control" name="agent_name" id="agent_name" value="<?php echo htmlspecialchars($invoice['agent_name'] ?? $invoice['party_agent'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Dispatch From</label>
                                    <input type="text" class="form-control" name="dispatch_from" value="<?php echo htmlspecialchars($invoice['dispatch_from'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Dispatch To</label>
                                    <input type="text" class="form-control" name="dispatch_through" value="<?php echo htmlspecialchars($invoice['dispatch_through'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Price Code *</label>
                                    <select class="form-select" name="price_code_id" id="price_code_id" required>
                                        <option value="">-- Select Price Code --</option>
                                    </select>
                                    <input type="hidden" id="fiscal_year_id" value="<?php echo $fiscal_year; ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title">Additional Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Party Place</label>
                                    <input type="text" class="form-control" name="p_place" id="p_place" value="<?php echo htmlspecialchars($invoice['p_place'] ?? $invoice['party_city'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Party State</label>
                                    <input type="text" class="form-control" name="p_state" id="p_state" value="<?php echo htmlspecialchars($invoice['p_state'] ?? $invoice['party_state'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Party GST</label>
                                    <input type="text" class="form-control" name="p_gst" id="p_gst" value="<?php echo htmlspecialchars($invoice['p_gst'] ?? $invoice['party_gst'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Party Address</label>
                                    <textarea class="form-control" name="p_address" id="p_address" rows="2" required><?php echo htmlspecialchars($invoice['p_address'] ?? $invoice['party_address'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">HSN Code</label>
                                    <input type="text" class="form-control" name="hsn_cd" id="hsn_cd" value="<?php echo htmlspecialchars($hsn_cd); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">E-Way Bill No.</label>
                                    <input type="text" class="form-control" name="eway" id="eway" value="<?php echo htmlspecialchars($invoice['eway_bill_no'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Vehicle No.</label>
                                    <input type="text" class="form-control" name="veh_no" id="veh_no" value="<?php echo htmlspecialchars($invoice['vehicle_no'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Transport Name</label>
                                    <select class="form-select transport-select" name="transport_name" id="transport_name">
                                        <option value="">Select Transport</option>
                                        <?php foreach($transports as $transport): ?>
                                            <option value="<?php echo $transport['name']; ?>" 
                                                    data-gst="<?php echo $transport['gst_no']; ?>"
                                                    <?php echo (isset($invoice['transport_name']) && $invoice['transport_name'] == $transport['name']) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($transport['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Transport GST</label>
                                    <input type="text" class="form-control" name="transport_gst" id="transport_gst" value="<?php echo htmlspecialchars($invoice['transport_gst'] ?? ''); ?>" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products Grid -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title">Products</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered" id="productsTable">
                            <thead>
                                <tr>
                                    <th width="25%">Product</th>
                                    <th width="10%" style="display:none;">UOM</th>
                                    <th>No of Cases</th>
                                    <!-- <th>Carton Contents</th> -->
                                    <th style="display:none;">Carton Contents</th>
                                    <th>Carton Range</th>
                                    <th>Qty</th>
                                    <th>Per</th>
                                    <th>Rate</th>
                                    <th>Total Amount</th>
                                    <th>Disc.</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($invoice_items)): ?>
                                    <?php foreach($invoice_items as $item): ?>
                                        <tr data-existing="true">
                                            <td>
                                                <select class="form-select product-select" name="product_id[]">
                                                    <option value="">Select Product</option>
                                                    <?php foreach($products as $product): ?>
                                                        <option value="<?php echo $product['id']; ?>" 
                                                                data-ctn="<?php echo $product['carton_contents']; ?>"
                                                                data-uom="<?php echo $product['uom']; ?>"
                                                                data-per_box_pieces="<?php echo $product['per_box_pieces']; ?>"
                                                                <?php echo $product['id'] == $item['product_id'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($product['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td style="display:none;"><input type="text" class="form-control uom" name="uom[]" value="<?php echo htmlspecialchars($item['uom']); ?>" readonly></td>
                                            <td><input type="text" class="form-control cartons" name="cartons[]" value="<?php echo htmlspecialchars($item['cartons']); ?>" min="1" onkeypress="return keyPressNumber(event,this);"></td>
                                            <td style="display:none;"><input type="text" class="form-control ctnCntnts" name="carton_contents[]" value="<?php echo htmlspecialchars($item['carton_contents']); ?>" readonly></td>
                                            <td>
                                                <div class="carton-range">
                                                    <input type="hidden" class="form-control carton-from" name="carton_from[]" value="<?php echo htmlspecialchars($item['carton_from']); ?>">
                                                    <input type="hidden" class="form-control carton-to" name="carton_to[]" value="<?php echo htmlspecialchars($item['carton_to']); ?>">
                                                    <span class="carton-range-display">
                                                        <?php 
                                                        if ($item['cartons'] == 1) {
                                                            echo $item['carton_from'];
                                                        } else if ($item['cartons'] > 1) {
                                                            echo $item['carton_from'] . ' - ' . $item['carton_to'];
                                                        } else {
                                                            echo '-';
                                                        }
                                                        ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td><input type="text" class="form-control qty" name="qty[]" value="<?php echo htmlspecialchars($item['qty']); ?>" min="1" onkeypress="return keyPressNumber(event,this);"></td>
                                            <td><input type="text" class="form-control per_box_pieces" name="per_box_pieces[]" value="<?php echo htmlspecialchars($item['per_box_pieces']); ?>" readonly></td>
                                            <td><input type="text" class="form-control rate" name="rate[]" value="<?php echo htmlspecialchars($item['rate']); ?>" onkeypress="return keyPressNumber(event,this);"></td>
                                            <td><input type="text" class="form-control total-amount" name="total_amount[]" value="<?php echo htmlspecialchars($item['total_amount']); ?>" readonly onkeypress="return keyPressNumber(event,this);"></td>
                                            <td class="text-center align-middle">
                                                <input type="hidden" name="discount_eligible[]" class="discount-eligible-hidden" value="<?php echo $item['discount_eligible'] ?? 1; ?>">
                                                <input type="checkbox" class="form-check-input discount-eligible" onchange="$(this).prev('.discount-eligible-hidden').val(this.checked ? 1 : 0);" <?php echo (!isset($item['discount_eligible']) || $item['discount_eligible'] == 1) ? 'checked' : ''; ?>>
                                            </td>
                                            <td><button type="button" class="btn btn-danger btn-sm remove-row"><i class="fas fa-trash"></i></button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td>
                                            <select class="form-select product-select" name="product_id[]">
                                                <option value="">Select Product</option>
                                                <?php foreach($products as $product): ?>
                                                    <option value="<?php echo $product['id']; ?>" 
                                                            data-ctn="<?php echo $product['carton_contents']; ?>"
                                                            data-uom="<?php echo $product['uom']; ?>"
                                                            data-per_box_pieces="<?php echo $product['per_box_pieces']; ?>">
                                                        <?php echo htmlspecialchars($product['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td style="display:none;"><input type="text" class="form-control uom" name="uom[]" readonly></td>
                                        <td><input type="text" class="form-control cartons" name="cartons[]" value="" min="1" onkeypress="return keyPressNumber(event,this);"></td>
                                        <td style="display:none;"><input type="text" class="form-control ctnCntnts" name="carton_contents[]" readonly></td>
                                        <td>
                                            <div class="carton-range">
                                                <input type="hidden" class="form-control carton-from" name="carton_from[]" value="0">
                                                <input type="hidden" class="form-control carton-to" name="carton_to[]" value="0">
                                                <span class="carton-range-display">-</span>
                                            </div>
                                        </td>
                                        <td><input type="text" class="form-control qty" name="qty[]" min="1" onkeypress="return keyPressNumber(event,this);"></td>
                                        <td><input type="text" class="form-control per_box_pieces" name="per_box_pieces[]" readonly></td>
                                        <td><input type="text" class="form-control rate" name="rate[]" onkeypress="return keyPressNumber(event,this);"></td>
                                        <td><input type="text" class="form-control total-amount" name="total_amount[]" value="0" readonly onkeypress="return keyPressNumber(event,this);"></td>
                                        <td class="text-center align-middle">
                                            <input type="hidden" name="discount_eligible[]" class="discount-eligible-hidden" value="1">
                                            <input type="checkbox" class="form-check-input discount-eligible" onchange="$(this).prev('.discount-eligible-hidden').val(this.checked ? 1 : 0);" checked>
                                        </td>
                                        <td><button type="button" class="btn btn-danger btn-sm remove-row"><i class="fas fa-trash"></i></button></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="" class="text-end"><strong>Total:</strong></td>
                                    <td><input type="text" class="form-control" id="cartonTotal" value="0" readonly></td>
                                    <td colspan="4" class="text-end"></td>
                                    <td><input type="text" class="form-control" id="grandTotal" value="0" readonly></td>
                                    <td colspan="1" class="text-end"></td>
                                    <td><button type="button" class="btn btn-success btn-sm" onclick="addRow()" ><i class="fas fa-plus"></i></button></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Tax and Amount Details -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title">Tax & Amount Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Goods Amount</label>
                                    <input type="text" class="form-control" name="taxable_amount" id="taxable_amount" value="<?php echo htmlspecialchars($invoice['taxable_amount']); ?>" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tax Type</label>
                                    <div class="btn-group w-100" role="group">
                                        <input type="radio" class="btn-check" name="tax_type" id="intrastate" value="intrastate" <?php echo ($invoice['tax_type'] == 'intrastate' || empty($invoice['tax_type'])) ? 'checked' : ''; ?> >
                                        <label class="btn btn-outline-primary" for="intrastate">Intra-State (SGST+CGST)</label>
                                        
                                        <input type="radio" class="btn-check" name="tax_type" id="interstate" value="interstate" <?php echo ($invoice['tax_type'] == 'interstate') ? 'checked' : ''; ?> >
                                        <label class="btn btn-outline-primary" for="interstate">Inter-State (IGST)</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row <?php echo ($invoice['tax_type'] == 'intrastate' || empty($invoice['tax_type'])) ? 'd-flex' : 'd-none'; ?>" id="intrastateTaxFields">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">SGST %</label>
                                    <input type="text" class="form-control sgst-percent" name="sgst_percent" value="<?php echo htmlspecialchars($invoice['sgst_percent']); ?>" readonly onkeypress="return keyPressNumber(event,this);">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">CGST %</label>
                                    <input type="text" class="form-control cgst-percent" name="cgst_percent" value="<?php echo htmlspecialchars($invoice['cgst_percent']); ?>" readonly onkeypress="return keyPressNumber(event,this);">
                                </div>
                            </div>
                            
                            <div class="row <?php echo ($invoice['tax_type'] == 'interstate' || empty($invoice['tax_type'])) ? 'd-flex' : 'd-none'; ?>" id="interstateTaxFields">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">IGST %</label>
                                    <input type="text" class="form-control igst-percent" name="igst_percent" value="<?php echo htmlspecialchars($invoice['igst_percent']); ?>" readonly onkeypress="return keyPressNumber(event,this);">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Discount Type</label>
                                    <div class="btn-group w-100 discount-type-btn" role="group">
                                        <input type="radio" class="btn-check" name="discount_type" id="discount_percent" value="percent" <?php echo ($invoice['discount_type'] == 'percent') ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-secondary" for="discount_percent">Percentage %</label>
                                        
                                        <input type="radio" class="btn-check" name="discount_type" id="discount_amount" value="amount" <?php echo ($invoice['discount_type'] == 'amount') ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-secondary" for="discount_amount">Fixed Amount</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label" id="discountLabel">Discount <?php echo ($invoice['discount_type'] == 'percent') ? 'Percentage' : 'Amount'; ?></label>
                                    <input type="text" class="form-control" name="discount_value" id="discount_value" value="<?php echo htmlspecialchars($invoice['discount_value']); ?>"  onkeypress="return keyPressNumber(event,this);">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title">Summary</h5>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <strong>Goods Amount:</strong>
                                <span class="amount-display" id="goodsAmountDisplay"><?php echo CURRENCY . number_format($invoice['taxable_amount'], 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <strong>Discount:</strong>
                                <span class="amount-display text-danger" id="discountDisplay">-<?php echo CURRENCY . number_format($invoice['discount_amount'], 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <strong>Taxable Amount:</strong>
                                <span class="amount-display" id="taxableAmountDisplay"><?php echo CURRENCY . number_format($invoice['taxable_amount'] - $invoice['discount_amount'], 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3" id="sgstSummary" style="display: <?php echo ($invoice['tax_type'] == 'intrastate') ? 'flex' : 'none'; ?>;">
                                <strong>SGST:</strong>
                                <span class="amount-display" id="sgstDisplay"><?php echo CURRENCY . number_format($invoice['sgst_amount'], 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3" id="cgstSummary" style="display: <?php echo ($invoice['tax_type'] == 'intrastate') ? 'flex' : 'none'; ?>;">
                                <strong>CGST:</strong>
                                <span class="amount-display" id="cgstDisplay"><?php echo CURRENCY . number_format($invoice['cgst_amount'], 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3" id="igstSummary" style="display: <?php echo ($invoice['tax_type'] == 'interstate') ? 'flex' : 'none'; ?>;">
                                <strong>IGST:</strong>
                                <span class="amount-display" id="igstDisplay"><?php echo CURRENCY . number_format($invoice['igst_amount'], 2); ?></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <strong>Total Tax:</strong>
                                <span class="amount-display" id="totalTaxDisplay"><?php echo CURRENCY . number_format($invoice['total_tax'], 2); ?></span>
                            </div>
                            <!-- Round Off Display -->
                            <div class="d-flex justify-content-between align-items-center mb-3 d-none" id="roundOffSummary">
                                <strong>Round Off:</strong>
                                <span class="amount-display" id="roundOffDisplay"><?php echo CURRENCY . number_format($invoice['round_off'], 2); ?></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between align-items-center">
                                <h4><strong>Net Amount:</strong></h4>
                                <h4 class="amount-display text-success" id="netAmountDisplay"><?php echo CURRENCY . number_format($invoice['net_amount'], 2); ?></h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hidden fields for form submission -->
            <input type="hidden" name="sgst_amount" id="sgst_amount" value="<?php echo htmlspecialchars($invoice['sgst_amount']); ?>">
            <input type="hidden" name="cgst_amount" id="cgst_amount" value="<?php echo htmlspecialchars($invoice['cgst_amount']); ?>">
            <input type="hidden" name="igst_amount" id="igst_amount" value="<?php echo htmlspecialchars($invoice['igst_amount']); ?>">
            <input type="hidden" name="total_tax" id="total_tax" value="<?php echo htmlspecialchars($invoice['total_tax']); ?>">
            <input type="hidden" name="net_amount" id="net_amount" value="<?php echo htmlspecialchars($invoice['net_amount']); ?>">
            <input type="hidden" name="discount" id="discount" value="<?php echo htmlspecialchars($invoice['discount_amount']); ?>">
            <input type="hidden" name="round_off" id="round_off" value="<?php echo htmlspecialchars($invoice['round_off']); ?>">
            <input type="hidden" name="discount_amount" id="discount_amount" value="<?php echo htmlspecialchars($invoice['discount_amount']); ?>">

            <div class="text-center d-flex justify-content-end">
                <button type="submit" class="btn btn-primary btn-lg me-3">
                    <i class="fas fa-save"></i> Update Invoice
                </button>
                <a href="view.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/jquery-3.6.0.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/bootstrap.bundle.min.js"></script>
    <!-- Select2 JS -->
    <script src="<?php echo ASSETS_URL; ?>/js/select2.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/script.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/sidebar.js"></script>

    <script>
        function keyPressNumber(e, t) {
            console.log('KeyPressNumberTrigger')
            try {
                if (window.event) {
                    var charCode = window.event.keyCode;
                }
                else if (e) {
                    var charCode = e.which;
                } else { return true; }
                if (charCode > 31 && (charCode < 46 || charCode > 57)) {
                    return false;
                }
                return true;
            }
            catch (err) {
                alert(err.Description);
            }
        }

        let currentCartonStart = <?php echo $next_carton_start; ?>;

        // Initialize Select2 for all dropdowns
        function initializeSelect2() {
            // Initialize party dropdown
            $('.party-select').select2({
                theme: 'bootstrap4',
                placeholder: "Select Party",
                allowClear: true,
                width: '100%'
            }).on('select2:open', function() {
                setTimeout(() => {
                    document.querySelector('.select2-container--open .select2-search__field').focus();
                }, 100);
            });

            // Initialize transport dropdown
            $('.transport-select').select2({
                theme: 'bootstrap4',
                placeholder: "Select Transport",
                allowClear: true,
                width: '100%'
            }).on('select2:open', function() {
                setTimeout(() => {
                    document.querySelector('.select2-container--open .select2-search__field').focus();
                }, 100);
            });

            // Initialize product selects
            /* $('.product-select').each(function() {
                if (!$(this).hasClass('select2-hidden-accessible')) {
                    $(this).select2({
                        theme: 'bootstrap4',
                        placeholder: "Select Product",
                        allowClear: true,
                        width: '100%',
                        dropdownParent: $('body')
                    });
                }
            }).on('select2:open', function() {
                setTimeout(() => {
                    document.querySelector('.select2-container--open .select2-search__field').focus();
                }, 100);
            }); */

            $('.product-select').each(function() {
                if (!$(this).hasClass('select2-hidden-accessible')) {
                    $(this).select2({
                        theme: 'bootstrap4',
                        placeholder: "Select Product",
                        allowClear: true,
                        width: '100%',
                        dropdownParent: $('body').on('select2:open', function() {
                            setTimeout(() => {
                                document.querySelector('.select2-container--open .select2-search__field').focus();
                            }, 100);
                        })
                    });
                }
            });

            $(document).on('select2:open', '.product-select', function() {
                setTimeout(function() {
                    $('.select2-search__field').focus();
                }, 100);
            });
        }

        // Load Price Codes on page load
        function loadPriceCodes(fiscalYearId) {
            if (!fiscalYearId) return;
            const priceCodeSelect = $('#price_code_id');
            
            $.ajax({
                url: '<?php echo BASE_URL; ?>/includes/ajax_actions.php',
                type: 'POST',
                dataType: 'json',
                data: { action: 'getPriceCodes', fiscal_year_id: fiscalYearId },
                success: function(response) {
                    priceCodeSelect.empty().append('<option value="">-- Select Price Code --</option>');
                    if (response.success && response.data.length > 0) {
                        response.data.forEach(pc => {
                            priceCodeSelect.append(`<option value="${pc.id}">${pc.name}</option>`);
                        });
                        // Select the saved price code
                        priceCodeSelect.val('<?php echo htmlspecialchars($invoice['price_code_id']); ?>');
                    }
                }
            });
        }

        function fetchPartyDetails(partyId) {
            if (!partyId) {
                $('#p_place').val('');
                $('#p_state').val('');
                $('#p_gst').val('');
                $('#p_address').val('');
                $('#agent_name').val('');
                return;
            }

            $.ajax({
                url: '../../includes/ajax_actions.php',
                type: 'POST',
                data: { party_id: partyId, action: 'getPartyDetails'},
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#p_place').val(response.data.city || '');
                        $('#p_state').val(response.data.state || '');
                        $('#p_gst').val(response.data.gst_no || '');
                        $('#p_address').val(response.data.address || '');
                        $('#agent_name').val(response.data.agent_name || '');
                        
                        const businessState = "TAMIL NADU";
                        const bstat = 'TAMILNADU';
                        if (response.data.state === businessState || response.data.state === bstat) {
                            $('#intrastate').prop('checked', true);
                            toggleTaxFields('intrastate');
                        } else {
                            $('#interstate').prop('checked', true);
                            toggleTaxFields('interstate');
                        }
                        calculateTax();
                    }
                },
                error: function() {
                    Swal.fire('Error!', 'Failed to fetch party details', 'error');
                }
            });
        }

        function updateTransportGST(select) {
            const selectedOption = select.options[select.selectedIndex];
            if (selectedOption.value) {
                const transportGST = selectedOption.getAttribute('data-gst') || '';
                $('#transport_gst').val(transportGST);
            } else {
                $('#transport_gst').val('');
            }
        }

        function checkDuplicateProducts() {
            const selectedProducts = new Set();
            let hasDuplicates = false;
            let duplicateProductName = '';
            
            $('.product-select').each(function() {
                const productId = $(this).val();
                const productName = $(this).find('option:selected').text();
                
                if (productId && productId !== '') {
                    if (selectedProducts.has(productId)) {
                        hasDuplicates = true;
                        duplicateProductName = productName;
                        return false;
                    }
                    selectedProducts.add(productId);
                }
            });
            
            return { hasDuplicates, duplicateProductName };
        }

        function calculateCartonRanges() {
            let cartonStart = currentCartonStart;
            $('#productsTable tbody tr').each(function() {
                const cartons = parseInt($(this).find('.cartons').val()) || 0;
                const isExisting = $(this).attr('data-existing') === 'true';

                if (isExisting) {
                    const cartonFrom = parseInt($(this).find('.carton-from').val()) || 0;
                    if (cartons > 0 && cartonFrom > 0) {
                        const cartonTo = cartonFrom + cartons - 1;
                        $(this).find('.carton-to').val(cartonTo);

                        if (cartons === 1) {
                            $(this).find('.carton-range-display').text(cartonFrom.toString());
                        } else {
                            $(this).find('.carton-range-display').text(cartonFrom + ' - ' + cartonTo);
                        }
                    }
                } else {
                    if (cartons > 0) {
                        const cartonFrom = cartonStart;
                        const cartonTo = cartonStart + cartons - 1;

                        $(this).find('.carton-from').val(cartonFrom);
                        $(this).find('.carton-to').val(cartonTo);

                        if (cartons === 1) {
                            $(this).find('.carton-range-display').text(cartonFrom.toString());
                        } else {
                            $(this).find('.carton-range-display').text(cartonFrom + ' - ' + cartonTo);
                        }

                        cartonStart = cartonTo + 1;
                    } else {
                        $(this).find('.carton-from').val(0);
                        $(this).find('.carton-to').val(0);
                        $(this).find('.carton-range-display').text('-');
                    }
                }
            });
        }

        function attachRowEventHandlers(row) {
            row.find('.rate, .cartons').off('input change');
            row.find('.remove-row').off('click');
            row.find('.product-select').off('select2:select');
            
            row.find('.rate').on('input change', function() {
                calculateRowTotal(row);
                // calculateRowQty(row);
            });
            
            row.find('.cartons').on('input change', function() {
                // calculateRowQty(row);
                calculateRowTotal(row);
                calculateCartonRanges();
            });
            
            row.find('.remove-row').on('click', function() {
                removeRow(this);
            });
            
            row.find('.product-select').on('change', function(e) {
                updateProductDetails(this);
            });
            
            row.find('.discount-eligible').off('change').on('change', function() {
                calculateTax();
            });
        }

        function addRow() {
            const tbody = $('#productsTable tbody');
            const firstRow = tbody.find('tr:first');
            
            const newRow = firstRow.clone();
            newRow.removeAttr('data-existing');
            
            newRow.find('.product-select')
                .val('')
                .removeClass('select2-hidden-accessible')
                .removeAttr('data-select2-id')
                .next('.select2-container').remove();
            
            newRow.find('.ctnCntnts').val('');
            newRow.find('.uom').val('');
            newRow.find('.rate').val('');
            newRow.find('.cartons').val('');
            newRow.find('.carton-from').val('0');
            newRow.find('.carton-to').val('0');
            newRow.find('.carton-range-display').text('-');
            newRow.find('.qty').val('');
            newRow.find('.per_box_pieces').val('');
            newRow.find('.total-amount').val('0');
            newRow.find('.discount-eligible-hidden').val('1');
            newRow.find('.discount-eligible').prop('checked', true);
            
            const productSelect = newRow.find('.product-select')[0];
            productSelect.innerHTML = '<option value="">Select Product</option><?php 
                foreach($products as $product) { 
                    echo '<option value="'.$product['id'].'" '.
                         'data-ctn="'.$product['carton_contents'].'" '.
                         'data-uom="'.$product['uom'].'" '.
                         'data-per_box_pieces="'.$product['per_box_pieces'].'">'.
                         htmlspecialchars($product['name']).'</option>';
                } 
            ?>';
            
            tbody.append(newRow);
            
            /* const newProductSelect = newRow.find('.product-select');
            newProductSelect.select2({
                theme: 'bootstrap4',
                placeholder: "Select Product",
                allowClear: true,
                width: '100%',
                dropdownParent: newRow.find('td:first').on('select2:open', function() {
                    setTimeout(() => {
                        document.querySelector('.select2-container--open .select2-search__field').focus();
                    }, 100);
                })
            }); */

            const newProductSelect = newRow.find('.product-select');

            newProductSelect.select2({
                theme: 'bootstrap4',
                placeholder: "Select Product",
                allowClear: true,
                width: '100%',
                dropdownParent: $('body').on('select2:open', function() {
                    setTimeout(() => {
                        document.querySelector('.select2-container--open .select2-search__field').focus();
                    }, 100);
                })
            });
            
            attachRowEventHandlers(newRow);
            // calculateRowQty(newRow);
            calculateCartonRanges();
        }

        function removeRow(button) {
            const row = $(button).closest('tr');
            if($('#productsTable tbody tr').length > 1) {
                row.find('.product-select').select2('destroy');
                row.remove();
                calculateGrandTotal();
                calculateCartonTotal();
                calculateCartonRanges();
                calculateTax();
            } else {
                Swal.fire('Warning!', 'At least one product is required!', 'warning');
            }
        }

        function toggleTaxFields(taxType) {
            if (taxType === 'intrastate') {
                $('#intrastateTaxFields').removeClass('d-none');
                $('#interstateTaxFields').addClass('d-none');
                $('#sgstSummary').removeClass('d-none');
                $('#cgstSummary').removeClass('d-none');
                $('#igstSummary').addClass('d-none');
            } else {
                $('#intrastateTaxFields').addClass('d-none');
                $('#interstateTaxFields').removeClass('d-none');
                $('#sgstSummary').addClass('d-none');
                $('#cgstSummary').addClass('d-none');
                $('#igstSummary').removeClass('d-none');
            }
        }

        function updateProductDetails(select) {
            const row = $(select).closest('tr');
            const productId = $(select).val();
            const priceCodeId = $('#price_code_id').val();
            
            if(productId) {
                if (!priceCodeId) {
                    Swal.fire('Warning', 'Please select a Price Code first.', 'warning');
                    $(select).val('').trigger('change');
                    return;
                }

                $.ajax({
                    url: '<?php echo BASE_URL; ?>/includes/ajax_actions.php',
                    type: 'POST',
                    dataType: 'json',
                    data: { action: 'getProductDetailsForInvoice', product_id: productId, price_code_id: priceCodeId },
                    success: function(response) {
                        if (response.success && response.data) {
                            row.find('.rate').val(response.data.rate/2 || '0.00');
                            // row.find('.qty').val(response.data.carton_contents || '');
                            row.find('.ctnCntnts').val(response.data.carton_contents || '');
                            row.find('.uom').val(response.data.uom || '');
                            row.find('.per_box_pieces').val(response.data.per_box_pieces || '');
                            
                            // calculateRowQty(row);
                            calculateRowTotal(row);
                            calculateCartonRanges();
                        } else {
                            Swal.fire('Error', 'Could not fetch product details or price.', 'error');
                        }
                    }
                });
            } else {
                row.find('.rate').val('');
                row.find('.ctnCntnts').val('');
                row.find('.uom').val('');
                row.find('.total-amount').val('0');
                row.find('.carton-from').val('0');
                row.find('.carton-to').val('0');
                row.find('.qty').val('');
                row.find('.per_box_pieces').val('');
                row.find('.carton-range-display').text('-');
                calculateGrandTotal();
                calculateCartonTotal();
                calculateCartonRanges();
                // calculateRowQty(row);
            }
        }

        /* function calculateRowQty(row) {
            const rate = parseFloat(row.find('.rate').val()) || 0;
            const qty = parseFloat(row.find('.qty').val()) || 0;
            total = rate * qty;
            row.find('.total-amount').val(total.toFixed(2));
        } */

        function calculateRowTotal(row) {
            const rate = parseFloat(row.find('.rate').val()) || 0;
            const qty = parseFloat(row.find('.qty').val()) || 0;
            const cartons = parseFloat(row.find('.cartons').val()) || 0;
            const ctnCntnts = parseFloat(row.find('.ctnCntnts').val()) || 0;
            const per = parseFloat(row.find('.per_box_pieces').val()) || 0;
            
            console.log('Values:', rate, qty, cartons, ctnCntnts, per);
        
            let total = 0;
            total = rate * qty;
            
            row.find('.total-amount').val(total.toFixed(2));
            calculateGrandTotal();
            calculateCartonTotal();
        }

        function calculateCartonTotal(){
            let cartonTotal = 0;
            $('.cartons').each(function() {
                cartonTotal += parseFloat($(this).val()) || 0;
            });
            $('#cartonTotal').val(cartonTotal.toFixed(2));
        }

        function calculateGrandTotal() {
            let grandTotal = 0;
            $('.total-amount').each(function() {
                grandTotal += parseFloat($(this).val()) || 0;
            });
            
            $('#grandTotal').val(grandTotal.toFixed(2));
            $('#taxable_amount').val(grandTotal.toFixed(2));
            calculateTax();
        }

        function calculateTax() {
            const taxableAmount = parseFloat($('#taxable_amount').val()) || 0;
            const discountValue = parseFloat($('#discount_value').val()) || 0;
            const discountType = $('input[name="discount_type"]:checked').val();
            const taxType = $('input[name="tax_type"]:checked').val();
            
            let discountableAmount = 0;
            $('#productsTable tbody tr').each(function() {
                if ($(this).find('.discount-eligible').is(':checked')) {
                    discountableAmount += parseFloat($(this).find('.total-amount').val()) || 0;
                }
            });

            let discountAmount = 0;
            if (discountType === 'percent') {
                discountAmount = (discountableAmount * discountValue) / 100;
            } else {
                discountAmount = discountValue;
            }
            
            const amountAfterDiscount = taxableAmount - discountAmount;
            
            let sgstAmount = 0, cgstAmount = 0, igstAmount = 0;
            
            if (taxType === 'intrastate') {
                const sgstPercent = parseFloat($('.sgst-percent').val()) || 0;
                const cgstPercent = parseFloat($('.cgst-percent').val()) || 0;
                sgstAmount = (amountAfterDiscount * sgstPercent) / 100;
                cgstAmount = (amountAfterDiscount * cgstPercent) / 100;
            } else {
                const igstPercent = parseFloat($('.igst-percent').val()) || 0;
                igstAmount = (amountAfterDiscount * igstPercent) / 100;
            }
            
            const totalTax = sgstAmount + cgstAmount + igstAmount;
            const netAmountBeforeRoundoff = amountAfterDiscount + totalTax;
            
            const roundedNetAmount = Math.round(netAmountBeforeRoundoff);
            const roundOffAmount = roundedNetAmount - netAmountBeforeRoundoff;
            
            $('#sgst_amount').val(sgstAmount.toFixed(2));
            $('#cgst_amount').val(cgstAmount.toFixed(2));
            $('#igst_amount').val(igstAmount.toFixed(2));
            $('#total_tax').val(totalTax.toFixed(2));
            $('#net_amount').val(roundedNetAmount.toFixed(2));
            $('#discount').val(discountAmount.toFixed(2));
            $('#round_off').val(roundOffAmount.toFixed(2));
            $('#discount_amount').val(discountAmount.toFixed(2));
            
            $('#goodsAmountDisplay').text('<?php echo CURRENCY; ?>' + taxableAmount.toFixed(2));
            $('#discountDisplay').text('-<?php echo CURRENCY; ?>' + discountAmount.toFixed(2));
            $('#taxableAmountDisplay').text('<?php echo CURRENCY; ?>' + (taxableAmount - discountAmount).toFixed(2));
            $('#sgstDisplay').text('<?php echo CURRENCY; ?>' + sgstAmount.toFixed(2));
            $('#cgstDisplay').text('<?php echo CURRENCY; ?>' + cgstAmount.toFixed(2));
            $('#igstDisplay').text('<?php echo CURRENCY; ?>' + igstAmount.toFixed(2));
            $('#totalTaxDisplay').text('<?php echo CURRENCY; ?>' + totalTax.toFixed(2));
            
            if (roundOffAmount !== 0) {
                $('#roundOffSummary').removeClass('d-none');
                $('#roundOffDisplay').text('<?php echo CURRENCY; ?>' + roundOffAmount.toFixed(2));
                $('#roundOffDisplay').removeClass('text-success text-danger');
                if (roundOffAmount > 0) {
                    $('#roundOffDisplay').addClass('text-success');
                } else {
                    $('#roundOffDisplay').addClass('text-danger');
                }
            } else {
                $('#roundOffSummary').addClass('d-none');
            }
            
            $('#netAmountDisplay').text('<?php echo CURRENCY; ?>' + roundedNetAmount.toFixed(2));
        }

        $(document).ready(function() {
            loadPriceCodes($('#fiscal_year_id').val());
            initializeSelect2();
            
            $('#productsTable tbody tr').each(function() {
                attachRowEventHandlers($(this));
            });
            
            calculateGrandTotal();
            calculateCartonTotal();
            calculateCartonRanges();
            /* $('#productsTable tbody tr').each(function() {
                calculateRowQty($(this));
            }); */
            calculateTax();

            $('#party_id').on('change', function() {
                fetchPartyDetails($(this).val());
            });
            
            $('#transport_name').on('change', function() {
                updateTransportGST(this);
            });
            
            $('input[name="tax_type"]').on('change', function() {
                toggleTaxFields(this.value);
                calculateTax();
            });
            
            $('input[name="discount_type"]').on('change', function() {
                $('#discountLabel').text(this.value === 'percent' ? 'Discount Percentage' : 'Discount Amount');
                calculateTax();
            });
            
            $(document).on('change input', '.sgst-percent, .cgst-percent, .igst-percent, #discount_value', function() {
                calculateTax();
            });
            
            $(document).on('input change', '.rate, .cartons, .qty', function() {
                const row = $(this).closest('tr');
                calculateRowTotal(row);
                // calculateRowQty(row);
                calculateCartonRanges();
            });

            $('#price_code_id').on('change', function() {
                $('.product-select').trigger('change');
            });
            
            $('#invoice-form').on('submit', function(e) {
                let hasValidProduct = false;
                let emptyProductRows = 0;
                let hasInvalidCartons = false;
                
                $('.product-select').each(function() {
                    const productId = $(this).val();
                    const cartons = $(this).closest('tr').find('.cartons').val();
                    
                    if (productId && productId !== '' && cartons > 0) {
                        hasValidProduct = true;
                    }
                    
                    if (!productId || productId === '') {
                        emptyProductRows++;
                    }
                    
                    if (productId && productId !== '' && (cartons === '' || cartons <= 0)) {
                        hasInvalidCartons = true;
                    }
                });
                
                const totalRows = $('.product-select').length;
                if (emptyProductRows === totalRows) {
                    e.preventDefault();
                    Swal.fire('Error!', 'Please add at least one product with cartons!', 'error');
                    return;
                }
                
                if (!hasValidProduct) {
                    e.preventDefault();
                    Swal.fire('Error!', 'Please add at least one valid product with cartons greater than 0!', 'error');
                    return;
                }
                
                const duplicateCheck = checkDuplicateProducts();
                if (duplicateCheck.hasDuplicates) {
                    e.preventDefault();
                    Swal.fire('Error!', `Duplicate product found: "${duplicateCheck.duplicateProductName}". Please remove duplicate products before saving.`, 'error');
                    return;
                }
                
                if(!$('select[name="party_id"]').val()) {
                    e.preventDefault();
                    Swal.fire('Error!', 'Please select a party!', 'error');
                    return;
                }
                
                if (hasInvalidCartons) {
                    e.preventDefault();
                    Swal.fire('Error!', 'Please enter valid cartons (greater than 0) for all selected products!', 'error');
                    return;
                }
                
                calculateCartonRanges();
                /* $('#productsTable tbody tr').each(function() {
                    calculateRowQty($(this));
                }); */
            });
        });

        function showPrintOptions(invoiceId, year) {
            const htmlContent = `
                <style>
                    .print-options-container { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-top: 10px; }
                    .print-option-label { cursor: pointer; margin: 0; }
                    .print-option-label input { display: none; }
                    .print-card { padding: 12px 10px; border: 2px solid #e0e0e0; border-radius: 8px; transition: all 0.2s; font-weight: 500; color: #555; text-align: center; min-width: 95px; }
                    .print-option-label:hover .print-card { border-color: #b6d4fe; background-color: #f8f9fa; }
                    .print-option-label input:checked + .print-card { border-color: #0d6efd; background-color: #e9f1fe; color: #0d6efd; box-shadow: 0 0 8px rgba(13, 110, 253, 0.2); }
                </style>
                <div class="print-options-container">
                    <label class="print-option-label">
                        <input type="radio" name="printCopy" value="Original" checked>
                        <div class="print-card"><i class="fas fa-file-alt mb-2 d-block" style="font-size: 1.5em;"></i>Original</div>
                    </label>
                    <label class="print-option-label">
                        <input type="radio" name="printCopy" value="Duplicate">
                        <div class="print-card"><i class="fas fa-copy mb-2 d-block" style="font-size: 1.5em;"></i>Duplicate</div>
                    </label>
                    <label class="print-option-label">
                        <input type="radio" name="printCopy" value="Transport">
                        <div class="print-card"><i class="fas fa-truck mb-2 d-block" style="font-size: 1.5em;"></i>Transport</div>
                    </label>
                    <label class="print-option-label">
                        <input type="radio" name="printCopy" value="Supplier">
                        <div class="print-card"><i class="fas fa-building mb-2 d-block" style="font-size: 1.5em;"></i>Supplier</div>
                    </label>
                    <label class="print-option-label">
                        <input type="radio" name="printCopy" value="All">
                        <div class="print-card"><i class="fas fa-layer-group mb-2 d-block" style="font-size: 1.5em;"></i>All</div>
                    </label>
                </div>
            `;

            Swal.fire({
                title: 'Select Print Copy',
                html: htmlContent,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-print me-1"></i> Print',
                preConfirm: () => {
                    return document.querySelector('input[name="printCopy"]:checked').value;
                }
            }).then((result) => {
                if (result.isConfirmed && result.value) {
                    window.open(`new_rpt.php?id=${invoiceId}&year=${year}&copy=${result.value}`, '_blank');
                }
            });
        }

        $(document).keydown(function (e) {
            // Ctrl+S (Windows/Linux) or Cmd+S (Mac)
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                e.preventDefault(); 
                $('.fa-save').trigger('click');
            }

            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'c') {
                e.preventDefault(); 
                $('.fa-arrow-left').trigger('click');
            }
        });
    </script>
</body>
</html>                        