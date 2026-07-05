<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";
require_once "../../includes/audit_helper.php";

$database = new Database();
$db = $database->getConnection();

// Check if invoice ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: temp_view.php");
    exit;
}

$invoice_id = $_GET['id'];
$fiscal_year = $_GET['year'];

// Get invoice data
$invoice_query = "SELECT i.*
                 FROM ff_sch.temp_inv i 
                 WHERE i.invoice_no = :id and i.fiscal_year_id = :fiscal_year_id";
$stmt = $db->prepare($invoice_query);
$stmt->bindParam(':id', $invoice_id);
$stmt->bindParam(':fiscal_year_id', $fiscal_year);
$stmt->execute();
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

$inv_num = $invoice['id'];

if (!$invoice) {
    echo "<script>alert('Invoice not found!'); window.location.href='temp_view.php';</script>";
    exit;
}

// Get invoice items
$items_query = "SELECT ii.*, p.name as product_name, p.rate as product_rate, 
                       p.carton_contents as product_ctn, p.uom as product_uom, p.per_box_pieces per_box_pieces
                FROM ff_sch.temp_inv_items ii 
                LEFT JOIN ff_sch.products p ON ii.product_id = p.id 
                WHERE ii.invoice_id = :invoice_id and ii.fiscal_year_id = :fiscal_year_id order by ii.id";
$stmt = $db->prepare($items_query);
$stmt->bindParam(':invoice_id', $inv_num);
$stmt->bindParam(':fiscal_year_id', $fiscal_year);
$stmt->execute();
$temp_inv_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get parties and products for dropdowns
$parties = $db->query("SELECT * FROM ff_sch.parties WHERE is_active = true ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$products = $db->query("SELECT id, name, carton_contents, uom, per_box_pieces FROM ff_sch.products WHERE is_active = true ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get transports
$transports = $db->query("SELECT * FROM ff_sch.transport WHERE is_active = true ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);


// Get last used carton numbers for this fiscal year (excluding current invoice)
$last_carton_query = $db->prepare("SELECT MAX(carton_to) as last_carton FROM ff_sch.temp_inv_items ii 
                                JOIN ff_sch.temp_inv i ON ii.invoice_id = i.id 
                                WHERE i.fiscal_year_id = :fiscal_year_id AND i.id != :invoice_id");
$last_carton_query->bindParam(':fiscal_year_id', $fiscal_year);
$last_carton_query->bindParam(':invoice_id', $invoice_id);
$last_carton_query->execute();
$last_carton = $last_carton_query->fetch(PDO::FETCH_ASSOC);
$next_carton_start = $last_carton['last_carton'] ? $last_carton['last_carton'] + 1 : 1;

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
            /* z-index: 1055 !important; */
        }
        .table-responsive {
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <?php include "../../includes/header.php"; ?>
    <?php include "../../includes/sidebar.php"; ?>

    <?php if($_POST) {
        try {
            $db->beginTransaction();
            $inv_num = $_POST['inv_num'];
            // Update invoice - FIXED QUERY
            $invoice_query = "UPDATE ff_sch.temp_inv SET 
                            party_id = :party_id, 
                            price_code_id = :price_code_id,
                            dispatch_from = :dispatch_from, 
                            dispatch_through = :dispatch_through, 
                            invoice_date = :invoice_date, 
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
                            p_state = :p_state,
                            p_gst = :p_gst,
                            p_address = :p_address,
                            round_off = :round_off,
                            tax_type = :tax_type,
                            vehicle_no = :vehicle_no,
                            eway_bill_no = :eway_bill_no,
                            transport_name = :transport_name,
                            transport_gst = :transport_gst
                            WHERE id = :id";
            
            $stmt = $db->prepare($invoice_query);
            $stmt->bindParam(':party_id', $_POST['party_id']);
            $stmt->bindParam(':price_code_id', $_POST['price_code_id']);
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
            $stmt->bindParam(':round_off', $_POST['round_off']);
            $stmt->bindParam(':tax_type', $_POST['tax_type']);
            $stmt->bindParam(':vehicle_no', $_POST['vehicle_no']);
            $stmt->bindParam(':eway_bill_no', $_POST['eway_bill_no']);
            $stmt->bindParam(':transport_name', $_POST['transport_name']);
            $stmt->bindParam(':transport_gst', $_POST['transport_gst']);
            $stmt->bindParam(':id', $inv_num);
            $stmt->execute();
            
            // Delete existing invoice items
            $delete_items_query = "DELETE FROM ff_sch.temp_inv_items WHERE invoice_id = :invoice_id";
            $delete_stmt = $db->prepare($delete_items_query);
            $delete_stmt->bindParam(':invoice_id', $inv_num);
            $delete_stmt->execute();

            
            // Insert updated invoice items - FIXED QUERY with carton_from and carton_to
            $items_query = "INSERT INTO ff_sch.temp_inv_items (
                invoice_id, product_id, carton_contents, uom, rate, cartons, total_amount, carton_from, carton_to, qty, fiscal_year_id, discount_eligible
            ) VALUES (
                :invoice_id, :product_id, :carton_contents, :uom, :rate, :cartons, :total_amount, :carton_from, :carton_to, :qty, :fiscal_year_id, :discount_eligible
            )";
            $items_stmt = $db->prepare($items_query);
            
            foreach($_POST['product_id'] as $key => $product_id) {
                if(!empty($product_id) && !empty($_POST['cartons'][$key]) && $_POST['cartons'][$key] > 0) {
                    $items_stmt->bindParam(':invoice_id', $inv_num);
                    $items_stmt->bindParam(':product_id', $product_id);
                    $items_stmt->bindParam(':carton_contents', $_POST['carton_contents'][$key]);
                    $items_stmt->bindParam(':uom', $_POST['uom'][$key]);
                    $items_stmt->bindParam(':rate', $_POST['rate'][$key]);
                    $items_stmt->bindParam(':cartons', $_POST['cartons'][$key]);
                    $items_stmt->bindParam(':total_amount', $_POST['total_amount'][$key]);
                    $discount_eligible = $_POST['discount_eligible'][$key] ?? 0;
                    $items_stmt->bindParam(':discount_eligible', $discount_eligible);
                    
                    // Add carton_from and carton_to
                    $carton_from = $_POST['carton_from'][$key] ?? 0;
                    $carton_to = $_POST['carton_to'][$key] ?? 0;
                    $qty1 = $_POST['qty'][$key] ?? 0;
                    $items_stmt->bindParam(':carton_from', $carton_from);
                    $items_stmt->bindParam(':carton_to', $carton_to);
                    $items_stmt->bindParam(':qty', $qty1);
                    $items_stmt->bindParam(':fiscal_year_id', $fiscal_year);
                    
                    $items_stmt->execute();
                }
            }
            
            $db->commit();
            
            echo "<script>
                    Swal.fire('Success!', 'Invoice updated successfully!', 'success')
                        .then(() => { window.location.href = 'temp_view.php'; });
                </script>";
            
            // Refresh data after update
            $stmt = $db->prepare($invoice_query);
            $stmt->bindParam(':id', $inv_num);
            $stmt->execute();
            $invoice = $stmt->fetch(PDO::FETCH_ASSOC);
            
            $stmt = $db->prepare($items_query);
            $stmt->bindParam(':invoice_id', $inv_num);
            $stmt->execute();
            $temp_inv_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
        } catch(Exception $e) {
            $db->rollBack();
            // Better error display for debugging
            $error_message = addslashes($e->getMessage());
            echo "<script>
                    Swal.fire('Error!', 'Failed to update invoice: {$error_message}', 'error');
                    console.error('Invoice Update Error:', '{$error_message}');
                </script>";
            // You can also log the error for debugging
            error_log("Invoice Update Error: " . $e->getMessage());
        }
    } ?>

    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Edit Invoice - <?php echo $invoice['invoice_no']; ?></h2>
            <div>
                <a href="temp_view.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to List
                </a>
                <a href="rptt.php?id=<?php echo $invoice_id; ?>&year=<?php echo $fiscal_year; ?>" target="_blank" class="btn btn-info">
                    <i class="fas fa-print me-2"></i>Print
                </a>
            </div>
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
                                    <input type="text" class="form-control" value="<?php echo $invoice['invoice_no']; ?>" readonly>
                                    <input type="hidden" class="form-control" name='inv_num' value="<?php echo $inv_num; ?>" readonly>
                                    <small class="text-muted">Invoice number cannot be changed</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Invoice Date</label>
                                    <input type="date" class="form-control" name="invoice_date" value="<?php echo $invoice['invoice_date']; ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Party Name</label>
                                <input type="text" class="form-control" name="party_id" value="<?= $invoice['party_id'] ?>">
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Dispatch From</label>
                                    <input type="text" class="form-control" name="dispatch_from" value="<?php echo $invoice['dispatch_from'] ?? ''; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Dispatch To</label>
                                    <input type="text" class="form-control" name="dispatch_through" value="<?php echo $invoice['dispatch_through']; ?>">
                                </div>
                                <div class="col-md-6 mb-3">
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
                                    <input type="hidden" class="form-control" name="hid_p_place" id="hid_p_place" value="<?php echo $invoice['p_place'] ?? ''; ?>">
                                    <input type="text" class="form-control" name="p_place" id="p_place" value="<?php echo $invoice['p_place'] ?? ''; ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Party State</label>
                                    <input type="hidden" class="form-control" name="hid_p_state" id="hid_p_state" value="<?php echo $invoice['p_state'] ?? ''; ?>" required>
                                    <input type="text" class="form-control" name="p_state" id="p_state" value="<?php echo $invoice['p_state'] ?? ''; ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Party GST</label>
                                    <input type="hidden" class="form-control" name="hid_p_gst" id="hid_p_gst" value="<?php echo $invoice['p_gst'] ?? ''; ?>">
                                    <input type="text" class="form-control" name="p_gst" id="p_gst" value="<?php echo $invoice['p_gst'] ?? ''; ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Party Address</label>
                                    <textarea class="form-control" style="display:none;" name="hid_p_address" id="hid_p_address" rows="2" required><?php echo $invoice['p_address'] ?? ''; ?></textarea>
                                    <textarea class="form-control" name="p_address" id="p_address" rows="2" required><?php echo $invoice['p_address'] ?? ''; ?></textarea>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">HSN Code</label>
                                    <input type="text" class="form-control" name="hsn_cd" id="hsn_cd" value="<?php echo $invoice['hsn_code'] ?? '3604'; ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">E-Way Bill No.</label>
                                    <input type="text" class="form-control" name="eway_bill_no" id="eway_bill_no" value="<?php echo $invoice['eway_bill_no'] ?? ''; ?>" autofocus>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Vehicle No.</label>
                                    <input type="text" class="form-control" name="vehicle_no" id="vehicle_no" value="<?php echo $invoice['vehicle_no'] ?? ''; ?>">
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
                                                <?php echo $transport['name']; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Transport GST</label>
                                    <input type="text" class="form-control" name="transport_gst" id="transport_gst" value="<?php echo $invoice['transport_gst'] ?? ''; ?>" readonly>
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
                                    <th>Disc.</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($temp_inv_items)): ?>
                                    <?php foreach($temp_inv_items as $item): ?>
                                        <tr data-existing="true">
                                            <td>
                                                <select class="form-select product-select" name="product_id[]">
                                                    <option value="">Select Product</option>
                                                    <?php foreach($products as $product): ?>
                                                        <option value="<?php echo $product['id']; ?>"
                                                                data-ctn="<?php echo $product['carton_contents']; ?>"
                                                                data-uom="<?php echo $product['uom']; ?>"
                                                                <?php echo $product['id'] == $item['product_id'] ? 'selected' : ''; ?>>
                                                            <?php echo $product['name']; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td style="display:none;"><input type="text" class="form-control uom" name="uom[]" value="<?php echo $item['uom']; ?>" readonly></td>
                                            <td><input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control cartons" name="cartons[]" value="<?php echo $item['cartons']; ?>" min="1"></td>
                                            <td><input type="text" class="form-control ctnCntnts" name="carton_contents[]" value="<?php echo $item['carton_contents']; ?>" readonly></td>
                                            <td>
                                                <div class="carton-range">
                                                    <input type="hidden" class="form-control carton-from" name="carton_from[]" value="<?php echo $item['carton_from']; ?>">
                                                    <input type="hidden" class="form-control carton-to" name="carton_to[]" value="<?php echo $item['carton_to']; ?>">
                                                    <span class="carton-range-display">
                                                        <?php 
                                                        if ($item['cartons'] == 1) {
                                                            echo $item['carton_from'];
                                                        } else {
                                                            echo $item['carton_from'] . ' - ' . $item['carton_to'];
                                                        }
                                                        ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td><input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control qty" name="qty[]" value="<?php echo $item['qty']; ?>" step="1" readonly></td>
                                            <td><input type="text" class="form-control per_box_pieces" name="per_box_pieces[]" value="<?php echo $item['per_box_pieces']; ?>" readonly></td>
                                            <td><input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control rate" name="rate[]" value="<?php echo $item['rate']; ?>" step="0.01"></td>
                                            <td><input type="text" class="form-control total-amount" name="total_amount[]" value="<?php echo $item['total_amount']; ?>" readonly></td>
                                            <td class="text-center align-middle">
                                                <input type="hidden" name="discount_eligible[]" class="discount-eligible-hidden" value="<?php echo isset($item['discount_eligible']) ? $item['discount_eligible'] : '1'; ?>">
                                                <input type="checkbox" class="form-check-input discount-eligible" onchange="$(this).prev('.discount-eligible-hidden').val(this.checked ? 1 : 0);" <?php echo (!isset($item['discount_eligible']) || $item['discount_eligible']) ? 'checked' : ''; ?>>
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
                                                        <?php echo $product['name']; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td style="display:none;"><input type="text" class="form-control uom" name="uom[]" readonly></td>
                                        <td><input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control cartons" name="cartons[]" value="1" min="1"></td>
                                        <td><input type="text" class="form-control ctnCntnts" name="carton_contents[]" readonly></td>
                                        <td>
                                            <div class="carton-range">
                                                <input type="hidden" class="form-control carton-from" name="carton_from[]" value="0">
                                                <input type="hidden" class="form-control carton-to" name="carton_to[]" value="0">
                                                <span class="carton-range-display">-</span>
                                            </div>
                                        </td>
                                        <td><input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control qty" name="qty[]" step="1" readonly></td>
                                        <td><input type="text" class="form-control per_box_pieces" name="per_box_pieces[]" readonly></td>
                                        <td><input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control rate" name="rate[]" step="0.01"></td>
                                        <td><input type="text" class="form-control total-amount" name="total_amount[]" value="0" readonly></td>
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
                                    <td colspan="5" class="text-end"></td>
                                    <td><input type="text" class="form-control" id="grandTotal" value="0" readonly></td>
                                    <td><button type="button" class="btn btn-success btn-sm" onclick="addRow()" ><i class="fas fa-plus"></i></button></td>
                                </tr>
                                <!-- <tr>
                                    <td colspan="6" class="text-end"><strong>Total:</strong></td>
                                    <td><input type="text" class="form-control" id="grandTotal" value="<?php echo $invoice['taxable_amount']; ?>" readonly></td>
                                    <td><button type="button" class="btn btn-success btn-sm" onclick="addRow()"><i class="fas fa-plus"></i></button></td>
                                </tr> -->
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
                                    <label class="form-label">Taxable Amount</label>
                                    <input type="text" class="form-control" name="taxable_amount" id="taxable_amount" value="<?php echo $invoice['taxable_amount']; ?>" readonly>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Tax Type</label>
                                    <div class="btn-group w-100 tax-type-btn" role="group">
                                        <input type="radio" class="btn-check" name="tax_type" id="intrastate" value="intrastate" <?php echo $invoice['tax_type'] == 'intrastate' ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-primary" for="intrastate">Intra-State (SGST+CGST)</label>
                                        
                                        <input type="radio" class="btn-check" name="tax_type" id="interstate" value="interstate" <?php echo $invoice['tax_type'] == 'interstate' ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-primary" for="interstate">Inter-State (IGST)</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row" id="intrastateTaxFields" style="display: <?php echo $invoice['tax_type'] == 'intrastate' ? 'flex' : 'none'; ?>;">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">SGST %</label>
                                    <input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control sgst-percent" name="sgst_percent" value="<?php echo $invoice['sgst_percent']; ?>" step="0.01">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">CGST %</label>
                                    <input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control cgst-percent" name="cgst_percent" value="<?php echo $invoice['cgst_percent']; ?>" step="0.01">
                                </div>
                            </div>
                            
                            <div class="row" id="interstateTaxFields" style="display: <?php echo $invoice['tax_type'] == 'interstate' ? 'flex' : 'none'; ?>;">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">IGST %</label>
                                    <input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control igst-percent" name="igst_percent" value="<?php echo $invoice['igst_percent']; ?>" step="0.01">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Discount Type</label>
                                    <div class="btn-group w-100 discount-type-btn" role="group">
                                        <input type="radio" class="btn-check" name="discount_type" id="discount_percent" value="percent" <?php echo $invoice['discount_type'] == 'percent' ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-secondary" for="discount_percent">Percentage %</label>
                                        
                                        <input type="radio" class="btn-check" name="discount_type" id="discount_amount" value="amount" <?php echo $invoice['discount_type'] == 'amount' ? 'checked' : ''; ?>>
                                        <label class="btn btn-outline-secondary" for="discount_amount">Fixed Amount</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label" id="discountLabel">Discount <?php echo $invoice['discount_type'] == 'percent' ? 'Percentage' : 'Amount'; ?></label>
                                    <input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control" name="discount_value" id="discount_value" value="<?php echo $invoice['discount_value']; ?>" step="0.01">
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
                                <strong>Taxable Amount:</strong>
                                <span class="amount-display" id="taxableAmountDisplay"><?php echo CURRENCY . number_format($invoice['taxable_amount'], 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <strong>Discount:</strong>
                                <span class="amount-display text-danger" id="discountDisplay">-<?php echo CURRENCY . number_format($invoice['discount_amount'], 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3" id="sgstSummary" style="display: <?php echo $invoice['tax_type'] == 'intrastate' ? 'flex' : 'none'; ?>;">
                                <strong>SGST:</strong>
                                <span class="amount-display" id="sgstDisplay"><?php echo CURRENCY . number_format($invoice['sgst_amount'], 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3" id="cgstSummary" style="display: <?php echo $invoice['tax_type'] == 'intrastate' ? 'flex' : 'none'; ?>;">
                                <strong>CGST:</strong>
                                <span class="amount-display" id="cgstDisplay"><?php echo CURRENCY . number_format($invoice['cgst_amount'], 2); ?></span>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mb-3" id="igstSummary" style="display: <?php echo $invoice['tax_type'] == 'interstate' ? 'flex' : 'none'; ?>;">
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
                                <span class="amount-display" id="roundOffDisplay"><?php echo CURRENCY. number_format($invoice['round_off'], 2); ?></span>
                            </div>
                            <hr>
                            <div class="d-flex justify-content-between align-items-center">
                                <h4><strong>Net Amount:</strong></h4>
                                <h4 class="amount-display text-success" id="netAmountDisplay"><?php echo CURRENCY. number_format($invoice['net_amount'], 2); ?></h4>
                            </div>
                            <!-- <div class="d-flex justify-content-between align-items-center">
                                <h4><strong>Net Amount:</strong></h4>
                                <h4 class="amount-display text-success" id="netAmountDisplay"><?php echo CURRENCY . number_format($invoice['net_amount'], 2); ?></h4>
                            </div> -->
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hidden fields for form submission -->
            <input type="hidden" name="sgst_amount" id="sgst_amount" value="<?php echo $invoice['sgst_amount']; ?>">
            <input type="hidden" name="cgst_amount" id="cgst_amount" value="<?php echo $invoice['cgst_amount']; ?>">
            <input type="hidden" name="igst_amount" id="igst_amount" value="<?php echo $invoice['igst_amount']; ?>">
            <input type="hidden" name="total_tax" id="total_tax" value="<?php echo $invoice['total_tax']; ?>">
            <input type="hidden" name="net_amount" id="net_amount" value="<?php echo $invoice['net_amount']; ?>">
            <input type="hidden" name="round_off" id="round_off" value="<?php echo $invoice['round_off']; ?>">
            <input type="hidden" name="discount" id="discount" value="<?php echo $invoice['discount_amount']; ?>">

            <div class="text-center d-flex justify-content-end">
                <button type="submit" class="btn btn-primary btn-lg me-3">
                    <i class="fas fa-save"></i> Update Invoice
                </button>
                <a href="temp_view.php" class="btn btn-secondary btn-lg">
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
    
    <!-- Use the same JavaScript as add_invoice.php -->
   
    <script>
        let currentCartonStart = <?php echo $next_carton_start; ?>;

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
                        priceCodeSelect.val('<?php echo $invoice['price_code_id']; ?>');
                    }
                }
            });
        }
        
        // Initialize Select2 for all dropdowns
        function initializeSelect2() {
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
            $('.product-select').each(function() {
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
            }).on('select2:open', function() {
                setTimeout(() => {
                    document.querySelector('.select2-container--open .select2-search__field').focus();
                }, 100);
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
        // Function to attach event handlers to a specific row
        function attachRowEventHandlers(row) {
            // Remove any existing handlers first
            row.find('.rate, .cartons').off('input change');
            row.find('.remove-row').off('click');
            row.find('.product-select').off('select2:select');
            
            // Rate change handler
            row.find('.rate').on('input change', function() {
                const currentRow = $(this).closest('tr');
                calculateRowTotal(currentRow);
                calculateRowQty(currentRow);
            });
            
            // Cartons change handler
            row.find('.cartons').on('input change', function() {
                const currentRow = $(this).closest('tr');
                calculateRowTotal(currentRow);
                calculateRowQty(currentRow);
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
            
            row.find('.discount-eligible').off('change').on('change', function() {
                calculateTax();
            });
        }

        function addRow() {
            const tbody = $('#productsTable tbody');
            const firstRow = tbody.find('tr:first');

            // Clone the first row
            const newRow = firstRow.clone();
            newRow.removeAttr('data-existing');

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
            newRow.find('.discount-eligible-hidden').val('1');
            newRow.find('.discount-eligible').prop('checked', true);
            
            // Reset select options - FIXED: Added per_box_pieces data attribute
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
            
            // Focus on the new product select
            setTimeout(() => {
                newProductSelect.select2('open');
            }, 100);
            
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
                            row.find('.rate').val(response.data.rate || '0.00');
                            row.find('.ctnCntnts').val(response.data.carton_contents || '');
                            row.find('.uom').val(response.data.uom || '');
                            row.find('.per_box_pieces').val(response.data.per_box_pieces || '');
                            
                            calculateRowTotal(row);
                            calculateRowQty(row);
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
                row.find('.per_box_pieces').val('');
                row.find('.total-amount').val('0');
                row.find('.carton-from').val('0');
                row.find('.carton-to').val('0');
                row.find('.qty').val('');
                row.find('.carton-range-display').text('-');
                calculateGrandTotal();
                calculateCartonTotal();
                calculateCartonRanges();
                calculateRowQty(row);
            }
        }

        // Calculate row quantity
        function calculateRowQty(row) {
            const cartons = parseFloat(row.find('.cartons').val()) || 0;
            const ctnContents = parseFloat(row.find('.ctnCntnts').val()) || 0;
            const total = cartons * ctnContents;
            row.find('.qty').val(total.toFixed(0));
        }

        // Calculate row total - FIXED FUNCTION
        function calculateRowTotal(row) {
            const rate = parseFloat(row.find('.rate').val()) || 0;
            const qty = parseFloat(row.find('.qty').val()) || 0;
            const cartons = parseFloat(row.find('.cartons').val()) || 0;
            const ctnCntnts = parseFloat(row.find('.ctnCntnts').val()) || 0;
            const per = parseFloat(row.find('.per_box_pieces').val()) || 0;
            
            console.log('Values:', rate, qty, cartons, ctnCntnts, per);
            
            let total = 0;
            
            // Your business logic
            if (ctnCntnts === per) {
                console.log('Case 1: ctnCntnts === qty && per !== 0');
                total = cartons * rate;
            } else if (per !== ctnCntnts && per !== 0) {
                console.log('Case 2: per !== ctnCntnts && per !== 0');
                total = qty * (rate / per);
            } else {
                console.log('Case 3: Default calculation');
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
            
            let discountableAmount = 0;
            $('#productsTable tbody tr').each(function() {
                if ($(this).find('.discount-eligible').is(':checked')) {
                    discountableAmount += parseFloat($(this).find('.total-amount').val()) || 0;
                }
            });

            // Calculate discount amount
            let discountAmount = 0;
            if (discountType === 'percent') {
                discountAmount = (discountableAmount * discountValue) / 100;
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
            $('#round_off').val(roundOffAmount.toFixed(2));
            $('#discount').val(discountAmount.toFixed(2));
            
            // Update display
            $('#taxableAmountDisplay').text('<?php echo CURRENCY; ?>' + taxableAmount.toFixed(2));
            $('#discountDisplay').text('-<?php echo CURRENCY; ?>' + discountAmount.toFixed(2));
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
            loadPriceCodes($('#fiscal_year_id').val());

            initializeSelect2();
            
            // Attach event handlers to existing rows
            $('#productsTable tbody tr').each(function() {
                attachRowEventHandlers($(this));
            });
            
            // Set transport GST on page load
            const transportSelect = document.getElementById('transport_name');
            if (transportSelect) {
                updateTransportGST(transportSelect);
            }
            
            // Initial calculations
            $('#productsTable tbody tr').each(function() {
                calculateRowQty($(this));
                calculateRowTotal($(this));
            });
            calculateGrandTotal();
            calculateCartonTotal();
            calculateCartonRanges();
            calculateTax();

            // Event handlers

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

            // Recalculate on change of price code
            $('#price_code_id').on('change', function() {
                // Trigger change on all product selects to re-fetch prices
                $('.product-select').trigger('change');
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
                
                if (hasInvalidCartons) {
                    e.preventDefault();
                    Swal.fire('Error!', 'Please enter valid cartons (greater than 0) for all selected products!', 'error');
                    return;
                }
                
                // Final calculations before submission
                calculateCartonRanges();
                $('#productsTable tbody tr').each(function() {
                    calculateRowQty($(this));
                    calculateRowTotal($(this));
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
</body>
</html>