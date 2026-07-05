<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";
require_once "../../includes/audit_helper.php";


$database = new Database();
$db = $database->getConnection();

// Set the current user for audit logging
//setAuditUser($_SESSION['user_id']);

// Get parties and products for dropdowns
$parties = $db->query("SELECT * FROM ff_sch.parties WHERE is_active = true ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$products = $db->query("SELECT id, name, rate, carton_contents, uom, per_box_pieces FROM ff_sch.products WHERE is_active = true and rate>0 ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get transports
$transports = $db->query("SELECT id, name, gst_no FROM ff_sch.transport WHERE is_active = true ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get current fiscal year
$fiscal_year = $db->query("SELECT * FROM ff_sch.fiscal_years WHERE is_active = true LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$current_fiscal_year_id = $fiscal_year ? $fiscal_year['id'] : 1;


$master = $db->query("SELECT * FROM ff_sch.master WHERE is_active = true LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$hsn_cd = $master['hsn_code'];
$sgst = $master['sgst'];
$cgst = $master['cgst'];
$igst = $master['igst'];
// $maxCtnRange = 5000;

// Get last used carton numbers for this fiscal year
$last_carton_query = $db->query("SELECT MAX(carton_to) as last_carton FROM ff_sch.invoice_items ii 
                                JOIN ff_sch.invoices i ON ii.invoice_id = i.id 
                                WHERE i.fiscal_year_id = $current_fiscal_year_id");
$last_carton = $last_carton_query->fetch(PDO::FETCH_ASSOC);
// $next_carton_start = $last_carton['last_carton'] ? $last_carton['last_carton'] + 1 : $maxCtnRange;
$next_carton_start = $last_carton['last_carton'] ? $last_carton['last_carton'] + 1 : $last_carton['last_carton'] + 1;


// Max Inv ID
$inv_query = $db->query("SELECT max(invoice_no) as count FROM ff_sch.invoices where fiscal_year_id ='".$current_fiscal_year_id."'");
$invoice = $inv_query->fetch(PDO::FETCH_ASSOC);
$max_id = $invoice['count']+1;

// Generate Invoice ID with zero padding (5 digits total)
$padded_max_id = str_pad($max_id, 5, '0', STR_PAD_LEFT);
$invoice_no = "INV-" . date('Y') . "-" . $padded_max_id;
$invoice_no = $max_id;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Invoice - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/img/favicon.png">
    <link href="<?php echo ASSETS_URL; ?>/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="<?php echo ASSETS_URL; ?>/css/select2.min.css" rel="stylesheet" />
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
    <!-- <script src="<?php echo ASSETS_URL; ?>/js/sweetalert2@11.js"></script> -->
    <?php if($_POST) {
        // Check duplicate invoice number
        $check_sql = "SELECT 1 
                    FROM ff_sch.invoices 
                    WHERE invoice_no = :invoice_no 
                    AND fiscal_year_id = :fiscal_year_id
                    LIMIT 1";

        $check_stmt = $db->prepare($check_sql);
        $check_stmt->bindParam(':invoice_no', $_POST['invoice_no']);
        $check_stmt->bindParam(':fiscal_year_id', $current_fiscal_year_id);
        $check_stmt->execute();

        if ($check_stmt->fetch()) {
            echo "<script>
                    Swal.fire('Duplicate Invoice!', 
                    'Invoice number already exists. Please use a different invoice number.', 
                    'warning');
                </script>";
            $invoice_no - $_POST['invoice_no'];
            // exit; // VERY IMPORTANT
        }

        try {
            $db->beginTransaction();
            
            // Insert invoice - UPDATED QUERY with new fields
            $invoice_query = "INSERT INTO ff_sch.invoices (
                invoice_no, party_id, dispatch_from, dispatch_through, invoice_date, 
                taxable_amount, discount_type, discount_value, discount_amount, discount,
                sgst_percent, cgst_percent, igst_percent, 
                sgst_amount, cgst_amount, igst_amount, total_tax, 
                net_amount, fiscal_year_id, created_by, tax_type,
                hsn_code, eway_bill_no, vehicle_no, transport_name, transport_gst, round_off,
                p_place, p_state, p_gst, p_address
            ) VALUES (
                :invoice_no, :party_id, :dispatch_from, :dispatch_through, :invoice_date, 
                :taxable_amount, :discount_type, :discount_value, :discount_amount, :discount,
                :sgst_percent, :cgst_percent, :igst_percent, 
                :sgst_amount, :cgst_amount, :igst_amount, :total_tax, 
                :net_amount, :fiscal_year_id, :created_by, :tax_type,
                :hsn_code, :eway_bill_no, :vehicle_no, :transport_name, :transport_gst, :round_off,
                :p_place, :p_state, :p_gst, :p_address
            )";
            
            $stmt = $db->prepare($invoice_query);
            $stmt->bindParam(':invoice_no', $_POST['invoice_no']);
            $stmt->bindParam(':party_id', $_POST['party_id']);
            $stmt->bindParam(':dispatch_from', $_POST['dispatch_from']);
            $stmt->bindParam(':dispatch_through', $_POST['dispatch_through']);
            $stmt->bindParam(':invoice_date', $_POST['invoice_date']);
            $stmt->bindParam(':taxable_amount', $_POST['taxable_amount']);
            $stmt->bindParam(':discount_type', $_POST['discount_type']);
            $stmt->bindParam(':discount_value', $_POST['discount_value']);
            $stmt->bindParam(':discount_amount', $_POST['discount_amount']);
            $stmt->bindParam(':discount', $_POST['discount']);
            $stmt->bindParam(':sgst_percent', $_POST['sgst_percent']);
            $stmt->bindParam(':cgst_percent', $_POST['cgst_percent']);
            $stmt->bindParam(':igst_percent', $_POST['igst_percent']);
            $stmt->bindParam(':sgst_amount', $_POST['sgst_amount']);
            $stmt->bindParam(':cgst_amount', $_POST['cgst_amount']);
            $stmt->bindParam(':igst_amount', $_POST['igst_amount']);
            $stmt->bindParam(':total_tax', $_POST['total_tax']);
            $stmt->bindParam(':net_amount', $_POST['net_amount']);
            $stmt->bindParam(':p_place', $_POST['p_place']);
            $stmt->bindParam(':p_state', $_POST['p_state']);
            $stmt->bindParam(':p_gst', $_POST['p_gst']);
            $stmt->bindParam(':p_address', $_POST['p_address']);
            $stmt->bindValue(':fiscal_year_id', $current_fiscal_year_id);
            $stmt->bindParam(':created_by', $_SESSION['user_id']);
            
            // Add tax_type parameter
            $tax_type = $_POST['tax_type'] ?? 'intrastate';
            $stmt->bindParam(':tax_type', $tax_type);
            
            // Add new fields
            $stmt->bindParam(':hsn_code', $_POST['hsn_cd']);
            $stmt->bindParam(':eway_bill_no', $_POST['eway']);
            $stmt->bindParam(':vehicle_no', $_POST['veh_no']);
            $stmt->bindParam(':transport_name', $_POST['transport_name']);
            $stmt->bindParam(':transport_gst', $_POST['transport_gst']);
            $stmt->bindParam(':round_off', $_POST['round_off']);
            
            $stmt->execute();
            $invoice_id = $db->lastInsertId();
            
            // Insert invoice items - FIXED QUERY with carton_from and carton_to
            $items_query = "INSERT INTO ff_sch.invoice_items (
                invoice_id, product_id, carton_contents, uom, rate, cartons, total_amount, carton_from, carton_to, qty, fiscal_year_id
            ) VALUES (
                :invoice_id, :product_id, :carton_contents, :uom, :rate, :cartons, :total_amount, :carton_from, :carton_to, :qty, :fiscal_year_id
            )";
            $items_stmt = $db->prepare($items_query);
            
            foreach($_POST['product_id'] as $key => $product_id) {
                if(!empty($product_id) && !empty($_POST['cartons'][$key]) && $_POST['cartons'][$key] > 0) {
                    $items_stmt->bindParam(':invoice_id', $invoice_id);
                    $items_stmt->bindParam(':product_id', $product_id);
                    $items_stmt->bindParam(':carton_contents', $_POST['carton_contents'][$key]);
                    $items_stmt->bindParam(':uom', $_POST['uom'][$key]);
                    $items_stmt->bindParam(':rate', $_POST['rate'][$key]);
                    $items_stmt->bindParam(':cartons', $_POST['cartons'][$key]);
                    $items_stmt->bindParam(':total_amount', $_POST['total_amount'][$key]);
                    
                    // Add carton_from and carton_to
                    $carton_from = $_POST['carton_from'][$key] ?? 0;
                    $carton_to = $_POST['carton_to'][$key] ?? 0;
                    $qty1 = $_POST['qty'][$key] ?? 0;
                    $items_stmt->bindParam(':carton_from', $carton_from);
                    $items_stmt->bindParam(':carton_to', $carton_to);
                    $items_stmt->bindParam(':qty', $qty1);
                    $items_stmt->bindValue(':fiscal_year_id', $current_fiscal_year_id);
                    
                    $items_stmt->execute();
                }
            }
            
            $db->commit();
            
            echo "<script>
                    Swal.fire('Success!', 'Invoice created successfully!', 'success')
                        .then(() => { window.location.href = 'view.php'; });
                </script>";
            
        } catch(Exception $e) {
            $db->rollBack();
            // Better error display for debugging
            $error_message = addslashes($e->getMessage());
            echo "<script>
                    Swal.fire('Error!', 'Failed to create invoice: {$error_message}', 'error');
                    console.error('Invoice Error:', '{$error_message}');
                </script>";
            // You can also log the error for debugging
            error_log("Invoice Creation Error: " . $e->getMessage());
        }
    } 
    ?>

    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Create New Invoice</h2>
            <a href="view.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to List
            </a>
        </div>

        <form method="POST" id="invoiceForm">
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
                                    <input type="text" class="form-control" id="invoice_no" name="invoice_no" value="<?php echo $invoice_no; ?>">
                                    <small id="invoiceHelp" class="text-danger"></small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Invoice Date</label>
                                    <input type="date" class="form-control" name="invoice_date" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Party Name</label>
                                <select class="form-select party-select" name="party_id" id="party_id" required>
                                    <option value="">Select Party</option>
                                    <?php foreach($parties as $party): ?>
                                        <option value="<?php echo $party['id']; ?>" data-state="<?php echo $party['state']; ?>">
                                            <?php echo $party['name']; ?> (<?php echo $party['state']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Dispatch From</label>
                                    <input type="text" class="form-control" name="dispatch_from">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Dispatch To</label>
                                    <input type="text" class="form-control" name="dispatch_through">
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
                                    <input type="text" class="form-control" name="p_place" id="p_place">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Party State</label>
                                    <input type="text" class="form-control" name="p_state" id="p_state" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Party GST</label>
                                    <input type="text" class="form-control" name="p_gst" id="p_gst">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Party Address</label>
                                    <textarea class="form-control" name="p_address" id="p_address" rows="2" required></textarea>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">HSN Code</label>
                                    <input type="text" class="form-control" name="hsn_cd" id="hsn_cd" value="<?= $hsn_cd ?>" readonly>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">E-Way Bill No.</label>
                                    <input type="text" class="form-control" name="eway" id="eway">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Vehicle No.</label>
                                    <input type="text" class="form-control" name="veh_no" id="veh_no">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Transport Name</label>
                                    <select class="form-select transport-select" name="transport_name" id="transport_name">
                                        <option value="">Select Transport</option>
                                        <?php foreach($transports as $transport): ?>
                                            <option value="<?php echo $transport['name']; ?>" data-gst="<?php echo $transport['gst_no']; ?>">
                                                <?php echo $transport['name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Transport GST</label>
                                    <input type="text" class="form-control" name="transport_gst" id="transport_gst" readonly>
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
                                    <th>Cartons</th>
                                    <th>Carton Contents</th>
                                    <th>Carton Range</th>
                                    <th>Qty</th>
                                    <th>Per</th>
                                    <th>Rate</th>
                                    <th>Total Amount</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <select class="form-select product-select" name="product_id[]">
                                            <option value="">Select Product</option>
                                            <?php foreach($products as $product): ?>
                                                <option value="<?php echo $product['id']; ?>" 
                                                        data-rate="<?php echo $product['rate']; ?>"
                                                        data-ctn="<?php echo $product['carton_contents']; ?>"
                                                        data-uom="<?php echo $product['uom']; ?>"
                                                        data-per_box_pieces="<?php echo $product['per_box_pieces']; ?>">
                                                    <?php echo $product['name']; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td style="display:none;"><input type="text" class="form-control uom" name="uom[]" readonly></td>
                                    <td><input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control cartons" name="cartons[]" value="" min="1" ></td>
                                    <td><input type="text" class="form-control ctnCntnts" name="carton_contents[]" readonly></td>
                                    <td>
                                        <div class="carton-range">
                                            <input type="hidden" class="form-control carton-from" name="carton_from[]" value="0">
                                            <input type="hidden" class="form-control carton-to" name="carton_to[]" value="0">
                                            <span class="carton-range-display">-</span>
                                        </div>
                                    </td>
                                    <td><input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control qty" name="qty[]" min="1" readonly></td>
                                    <td><input type="text" class="form-control per_box_pieces" name="per_box_pieces[]" readonly></td>
                                    <td><input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control rate" name="rate[]" step="0.01"></td>
                                    <td><input type="text" class="form-control total-amount" name="total_amount[]" value="0" readonly></td>
                                    <td><button type="button" class="btn btn-danger btn-sm remove-row" ><i class="fas fa-trash"></i></button></td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="" class="text-end"><strong>Total:</strong></td>
                                    <td><input type="text" class="form-control" id="cartonTotal" value="0" readonly></td>
                                    <td colspan="5" class="text-end"></td>
                                    <td><input type="text" class="form-control" id="grandTotal" value="0" readonly></td>
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
                                    <input type="text" class="form-control" name="taxable_amount" id="taxable_amount" value="0" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tax Type</label>
                                    <div class="btn-group w-100 tax-type-btn" role="group">
                                        <input type="radio" class="btn-check" name="tax_type" id="intrastate" value="intrastate" checked >
                                        <label class="btn btn-outline-primary" for="intrastate">Intra-State (SGST+CGST)</label>
                                        
                                        <input type="radio" class="btn-check" name="tax_type" id="interstate" value="interstate" >
                                        <label class="btn btn-outline-primary" for="interstate">Inter-State (IGST)</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row" id="intrastateTaxFields">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">SGST %</label>
                                    <input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control sgst-percent" name="sgst_percent" value="<?= $sgst ?>" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">CGST %</label>
                                    <input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control cgst-percent" name="cgst_percent" value="<?= $cgst ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="row d-none" id="interstateTaxFields">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">IGST %</label>
                                    <input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control igst-percent" name="igst_percent" value="<?= $igst ?>" readonly>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Discount Type</label>
                                    <div class="btn-group w-100 discount-type-btn" role="group">
                                        <input type="radio" class="btn-check" name="discount_type" id="discount_percent" value="percent" checked >
                                        <label class="btn btn-outline-secondary" for="discount_percent">Percentage %</label>
                                        
                                        <input type="radio" class="btn-check" name="discount_type" id="discount_amount" value="amount" >
                                        <label class="btn btn-outline-secondary" for="discount_amount">Fixed Amount</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label" id="discountLabel">Discount Percentage</label>
                                    <input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control" name="discount_value" id="discount_value" value="0" step="0.01" >
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
                                <span class="amount-display" id="goodsAmountDisplay"><?php echo CURRENCY; ?>0.00</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <strong>Discount:</strong>
                                <span class="amount-display text-danger" id="discountDisplay">-<?php echo CURRENCY; ?>0.00</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <strong>Taxable Amount:</strong>
                                <span class="amount-display" id="taxableAmountDisplay"><?php echo CURRENCY; ?>0.00</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3" id="sgstSummary">
                                <strong>SGST:</strong>
                                <span class="amount-display" id="sgstDisplay"><?php echo CURRENCY; ?>0.00</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3" id="cgstSummary">
                                <strong>CGST:</strong>
                                <span class="amount-display" id="cgstDisplay"><?php echo CURRENCY; ?>0.00</span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3 d-none" id="igstSummary">
                                <strong>IGST:</strong>
                                <span class="amount-display" id="igstDisplay"><?php echo CURRENCY; ?>0.00</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <strong>Total Tax:</strong>
                                <span class="amount-display" id="totalTaxDisplay"><?php echo CURRENCY; ?>0.00</span>
                            </div>

                            <!-- Round Off Display -->
                            <div class="d-flex justify-content-between align-items-center mb-3 d-none" id="roundOffSummary">
                                <strong>Round Off:</strong>
                                <span class="amount-display" id="roundOffDisplay"><?php echo CURRENCY; ?>0.00</span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between align-items-center">
                                <h4><strong>Net Amount:</strong></h4>
                                <h4 class="amount-display text-success" id="netAmountDisplay"><?php echo CURRENCY; ?>0.00</h4>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hidden fields for form submission -->
            <input type="hidden" name="sgst_amount" id="sgst_amount" value="0">
            <input type="hidden" name="cgst_amount" id="cgst_amount" value="0">
            <input type="hidden" name="igst_amount" id="igst_amount" value="0">
            <input type="hidden" name="total_tax" id="total_tax" value="0">
            <input type="hidden" name="net_amount" id="net_amount" value="0">
            <input type="hidden" name="discount" id="discount" value="0">
            <input type="hidden" name="round_off" id="round_off" value="0">
            <input type="hidden" name="discount_amount" id="discount_amount" value="0">

            <div class="text-center d-flex justify-content-end">
                <button type="submit" class="btn btn-primary btn-lg me-3" >
                    <i class="fas fa-save"></i> Save Invoice
                </button>
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
    let currentCartonStart = <?php echo $next_carton_start; ?>;
    
    // Initialize Select2 for all dropdowns
    function initializeSelect2() {

    // Initialize transport dropdown
        $('.transport-select').select2({
            theme: 'bootstrap4',
            placeholder: "Select Transport",
            allowClear: true,
            width: '100%'
        })/* .on('select2:open', function() {
            setTimeout(() => {
                document.querySelector('.select2-container--open .select2-search__field').focus();
            }, 100);
        }) */;
        
        // Initialize party dropdown
        $('.party-select').select2({
            theme: 'bootstrap4',
            placeholder: "Select Party",
            allowClear: true,
            width: '100%'
        })/* .on('select2:open', function() {
            setTimeout(() => {
                document.querySelector('.select2-container--open .select2-search__field').focus();
            }, 100);
        });  */

        

        // Initialize product selects
        $('.product-select').each(function() {
            if (!$(this).hasClass('select2-hidden-accessible')) {
                $(this).select2({
                    theme: 'bootstrap4',
                    placeholder: "Select Product",
                    allowClear: true,
                    width: '100%',
                    dropdownParent: $('body')
                }).on('select2:open', function() {
            setTimeout(() => {
                document.querySelector('.select2-container--open .select2-search__field').focus();
            }, 100);
        });
            }
        })/* .on('select2:open', function() {
            setTimeout(() => {
                document.querySelector('.select2-container--open .select2-search__field').focus();
            }, 100);
        }) */;
    }

    // Function to fetch party details via AJAX
    function fetchPartyDetails(partyId) {
        if (!partyId) {
            $('#p_place').val('');
            $('#p_state').val('');
            $('#p_gst').val('');
            $('#p_address').val('');
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
                    
                    const businessState = "TAMIL NADU";
                    if (response.data.state === businessState) {
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

    // Update transport GST when transport is selected
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
        })/* .on('select2:open', function() {
            setTimeout(() => {
                document.querySelector('.select2-container--open .select2-search__field').focus();
            }, 100);
        }) */;
        
        return { hasDuplicates, duplicateProductName };
    }

    function calculateCartonRanges() {
        let cartonStart = currentCartonStart;
        $('#productsTable tbody tr').each(function() {
            const cartons = parseInt($(this).find('.cartons').val()) || 0;
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
        });
    }

    // Function to attach event handlers to a specific row
    function attachRowEventHandlers(row) {
        // Remove any existing handlers first
        row.find('.rate, .cartons').off('input change');
        row.find('.remove-row').off('click');
        row.find('.product-select').off('select2:select');
        
        // Rate change handler
        row.find('.rate').on('input change', function() {
            calculateRowTotal(row);
            calculateRowQty(row);
        });
        
        // Cartons change handler
        row.find('.cartons').on('input change', function() {
            calculateRowQty(row);
            calculateRowTotal(row);
            calculateCartonRanges();
        });
        
        // Remove row button handler
        row.find('.remove-row').on('click', function() {
            removeRow(this);
        });
        
        // Product select change
        row.find('.product-select').on('select2:select', function(e) {
            updateProductDetails(this);
        });
    }

    function addRow() {
        const tbody = $('#productsTable tbody');
        const firstRow = tbody.find('tr:first');
        
        // Clone the first row
        const newRow = firstRow.clone();
        
        // Clear all values
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
        
        // Reset select options - FIXED PHP SYNTAX
        const productSelect = newRow.find('.product-select')[0];
        productSelect.innerHTML = '<option value="">Select Product</option><?php 
            foreach($products as $product) { 
                echo '<option value="'.$product['id'].'" '.
                     'data-rate="'.$product['rate'].'" '.
                     'data-ctn="'.$product['carton_contents'].'" '.
                     'data-uom="'.$product['uom'].'" '.
                     'data-per_box_pieces="'.$product['per_box_pieces'].'">'.
                     htmlspecialchars($product['name']).'</option>';
            } 
        ?>';
        
        tbody.append(newRow);
        
        // Initialize Select2 for the new row
        const newProductSelect = newRow.find('.product-select');
        newProductSelect.select2({
            theme: 'bootstrap4',
            placeholder: "Select Product",
            allowClear: true,
            width: '100%',
            dropdownParent: newRow.find('td:first')
        }).on('select2:open', function() {
            setTimeout(() => {
                document.querySelector('.select2-container--open .select2-search__field').focus();
            }, 100);
        });
        
        // Attach event handlers to the new row
        attachRowEventHandlers(newRow);
        // Initial calculations
        calculateRowQty(newRow);
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

    // Function to update product details when product is selected
    function updateProductDetails(select) {
        const row = $(select).closest('tr');
        const selectedOption = select.options[select.selectedIndex];
        
        if(selectedOption.value) {
            const rate = selectedOption.getAttribute('data-rate');
            const ctn = selectedOption.getAttribute('data-ctn') || '';
            const uom = selectedOption.getAttribute('data-uom') || '';
            const per_box_pieces = selectedOption.getAttribute('data-per_box_pieces') || '';
            
            row.find('.rate').val(rate);
            row.find('.ctnCntnts').val(ctn);
            row.find('.uom').val(uom);
            row.find('.per_box_pieces').val(per_box_pieces);
            
            calculateRowQty(row);
            calculateRowTotal(row);
            calculateCartonRanges();
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
            calculateRowQty();
        }
    }

    // Calculate row quantity
    function calculateRowQty(row) {
        const cartons = parseFloat(row.find('.cartons').val()) || 0;
        const ctnContents = parseFloat(row.find('.ctnCntnts').val()) || 0;
        const total = cartons * ctnContents;
        row.find('.qty').val(total.toFixed(0));
       /*  const cartons = parseFloat(row.find('.cartons').val()) || 0;
        const ctnContents = parseFloat(row.find('.ctnCntnts').val()) || 0;
        const perBoxPieces = parseFloat(row.find('.per_box_pieces').val()) || 0;
        
        let totalQty = 0;
        
        // Determine how to calculate quantity based on your business logic
        if (perBoxPieces > 0 && perBoxPieces !== ctnContents) {
            totalQty = cartons * perBoxPieces;
        } else {
            totalQty = cartons * ctnContents;
        }
        
        row.find('.qty').val(totalQty.toFixed(0)); */
    }

    // Calculate row total
    function calculateRowTotal(row) {
        const rate = parseFloat(row.find('.rate').val()) || 0;
        const qty = parseFloat(row.find('.qty').val()) || 0;
        const cartons = parseFloat(row.find('.cartons').val()) || 0;
        const ctnCntnts = parseFloat(row.find('.ctnCntnts').val()) || 0;
        const per = parseFloat(row.find('.per_box_pieces').val()) || 0;
        /* console.log(rate, qty, cartons, ctnCntnts, per)
        
        const total = rate * qty; */
        console.log('Values:', rate, qty, cartons, ctnCntnts, per);
    
        let total = 0;
        
        // logic for different scenarios
        /* if (ctnCntnts === qty && per === 0) {
            console.log('aaa')
            total = cartons * qty;
        }else */ 
        if (ctnCntnts === per) {
            console.log('fff')
            total = cartons * rate;
        } else if (per !== ctnCntnts && per !== 0) {
            console.log('ccccc')
            total = qty * (rate / per);
        }/*  else if (per !== ctnCntnts && per === 0) {
            console.log('bbbb')
            total = cartons * rate;
        } */ else {
            console.log('dddd')
            // Default calculation
            total = rate * qty;
        }
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
        
        // Calculate discount amount
        let discountAmount = 0;
        if (discountType === 'percent') {
            discountAmount = (taxableAmount * discountValue) / 100;
        } else {
            discountAmount = discountValue;
        }
        
        const amountAfterDiscount = taxableAmount - discountAmount;
        
        // Calculate tax amounts
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
        
        // Apply round-off
        const roundedNetAmount = Math.round(netAmountBeforeRoundoff);
        const roundOffAmount = roundedNetAmount - netAmountBeforeRoundoff;
        
        // Update hidden fields
        $('#sgst_amount').val(sgstAmount.toFixed(2));
        $('#cgst_amount').val(cgstAmount.toFixed(2));
        $('#igst_amount').val(igstAmount.toFixed(2));
        $('#total_tax').val(totalTax.toFixed(2));
        $('#net_amount').val(roundedNetAmount.toFixed(2));
        $('#discount').val(discountAmount.toFixed(2));
        $('#round_off').val(roundOffAmount.toFixed(2));
        $('#discount_amount').val(discountAmount.toFixed(2));
        
        // Update display
        $('#goodsAmountDisplay').text('<?php echo CURRENCY; ?>' + taxableAmount.toFixed(2));
        $('#discountDisplay').text('-<?php echo CURRENCY; ?>' + discountAmount.toFixed(2));
        $('#taxableAmountDisplay').text('<?php echo CURRENCY; ?>' + (taxableAmount.toFixed(2) - discountAmount.toFixed(2)).toFixed(2));
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

    // Initialize on page load
    $(document).ready(function() {
        initializeSelect2();
        
        // Attach event handlers to existing rows
        $('#productsTable tbody tr').each(function() {
            attachRowEventHandlers($(this));
        });
        
        // Initial calculations
        calculateGrandTotal();
        calculateCartonTotal();
        calculateCartonRanges();
        $('#productsTable tbody tr').each(function() {
            calculateRowQty($(this));
        });
        calculateTax();

        // Event handlers
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
        
        // Global event handlers for dynamic content
        $(document).on('input change', '.rate, .cartons', function() {
            const row = $(this).closest('tr');
            calculateRowTotal(row);
            calculateRowQty(row);
            calculateCartonRanges();
        });
        
        // Form submission validation
        $('#invoiceForm').on('submit', function(e) {
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
            
            // Final calculations before submission
            calculateCartonRanges();
            $('#productsTable tbody tr').each(function() {
                calculateRowQty($(this));
            });
        });
    });
</script>
    <script>
        $(document).keydown(function (e) {
            // Ctrl+S (Windows/Linux) or Cmd+S (Mac)
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                e.preventDefault(); // stop browser save
                $('.fa-save').trigger('click');
            }

            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'c') {
                e.preventDefault(); // stop browser save
                $('.fa-arrow-left').trigger('click');
            }

        });
    </script>
    <script>
        $(document).ready(function () {
            $('#invoice_no').focus();
            $('#invoice_no').select();
            let invoiceTimer;

            $('#invoice_no').on('keyup blur', function () {
                clearTimeout(invoiceTimer);

                let invoiceNo = $(this).val().trim();

                if (invoiceNo === '') {
                    $('#invoiceHelp').text('');
                    return;
                }

                // invoiceTimer = setTimeout(function () {
                    $.ajax({
                        url: 'process.php',
                        type: 'POST',
                        dataType: 'json',
                        data: { invoice_no: invoiceNo, fiscal_year_id: <?= $current_fiscal_year_id ?>,source: 'proforma' },
                        success: function (res) {
                            console.log(res)
                            if (res.exists == 'error') {
                                $('#invoiceHelp').text('Invoice number already exists!');
                                $('#invoice_no').addClass('is-invalid');
                                $('#invoice_no').focus();
                                return;
                            } else {
                                $('#invoiceHelp').text('');
                                $('#invoice_no').removeClass('is-invalid');
                            }
                        }
                    });
                // }, 500); // debounce 500ms
            });

        });
    </script>

</body>
</html>