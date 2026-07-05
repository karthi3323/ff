<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";
require_once "../../includes/audit_helper.php";

$database = new Database();
$db = $database->getConnection();

// Check if invoice ID is provided
if (!isset($_GET['id']) || empty($_GET['id']) || !isset($_GET['year']) || empty($_GET['year'])) {
    header("Location: proforma_view.php");
    exit;
}

$invoice_id = $_GET['id'];
$fiscal_year_id = $_GET['year'];

// Get invoice data
$invoice_query = "SELECT * FROM ff_sch.proforma_invoices WHERE id = :id";
$stmt = $db->prepare($invoice_query);
$stmt->bindParam(':id', $invoice_id);
$stmt->execute();
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    echo "<script>alert('Pro-forma Invoice not found!'); window.location.href='proforma_view.php';</script>";
    exit;
}

// Get invoice items
$items_query = "SELECT ii.*, p.name as product_name, p.rate as product_rate, 
                       p.carton_contents as product_ctn, p.uom as product_uom, p.per_box_pieces as per_box_pieces
                FROM ff_sch.proforma_invoice_items ii 
                LEFT JOIN ff_sch.products p ON ii.product_id = p.id 
                WHERE ii.invoice_id = :invoice_id ORDER BY ii.id";
$stmt = $db->prepare($items_query);
$stmt->bindParam(':invoice_id', $invoice_id);
$stmt->execute();
$proforma_invoice_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get parties and products for dropdowns
$parties = $db->query("SELECT * FROM ff_sch.parties WHERE is_active = true ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$products = $db->query("SELECT id, name, carton_contents, uom, per_box_pieces FROM ff_sch.products WHERE is_active = true ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get transports
$transports = $db->query("SELECT * FROM ff_sch.transport WHERE is_active = true ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get current fiscal year
$fiscal_year_query = $db->query("SELECT * FROM ff_sch.fiscal_years WHERE is_active = true LIMIT 1");
$fiscal_year = $fiscal_year_query->fetch(PDO::FETCH_ASSOC);
$current_fiscal_year_id = $fiscal_year ? $fiscal_year['id'] : 1;

// Get last used carton numbers for this fiscal year (excluding current invoice)
$last_carton_query = $db->prepare("SELECT MAX(carton_to) as last_carton FROM ff_sch.proforma_invoice_items ii 
                                JOIN ff_sch.proforma_invoices i ON ii.invoice_id = i.id 
                                WHERE i.fiscal_year_id = :fiscal_year_id AND i.id != :invoice_id");
$last_carton_query->bindParam(':fiscal_year_id', $current_fiscal_year_id);
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
    <title>Edit Pro-forma Invoice - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/img/favicon.png">
    <link href="<?php echo ASSETS_URL; ?>/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/select2.min.css" rel="stylesheet" />
    <link href="<?php echo ASSETS_URL; ?>/css/select2-bootstrap4.min.css" rel="stylesheet" />
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    <script src="<?php echo ASSETS_URL; ?>/js/sweetalert2@11.js"></script>
    <style>
        .tax-input-group { margin-bottom: 1rem; }
        .tax-input-group label { font-weight: 500; margin-bottom: 0.5rem; }
        .amount-display { font-size: 1.25rem; font-weight: 600; color: #0d6efd; }
        .product-select { width: 100% !important; }
        .tax-type-btn { margin-bottom: 1rem; }
        .discount-type-btn { margin-bottom: 1rem; }
        .select2-container { z-index: 1055 !important; }
        .table-responsive { overflow-x: auto; }
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
    <?php
    
// Handle POST request for update
if($_POST) {
    // Sanitize numeric inputs to prevent SQL errors
    $numeric_fields = [
        'taxable_amount', 'discount_value', 'additional_charges_percent', 
        'additional_charges_amount', 'packing_charges', 'discount_amount', 
        'discount', 'sgst_percent', 'cgst_percent', 'igst_percent', 
        'sgst_amount', 'cgst_amount', 'igst_amount', 'total_tax', 'net_amount', 'round_off'
    ];
    foreach ($numeric_fields as $field) {
        if (!isset($_POST[$field]) || !is_numeric($_POST[$field])) {
            $_POST[$field] = 0;
        }
    }

    try {
        $db->beginTransaction();
        
        // Update pro-forma invoice
        $update_invoice_query = "UPDATE ff_sch.proforma_invoices SET 
                        party_id = :party_id, 
                        price_code_id = :price_code_id,
                        dispatch_from = :dispatch_from, 
                        dispatch_through = :dispatch_through, 
                        invoice_date = :invoice_date, 
                        taxable_amount = :taxable_amount, 
                        discount_type = :discount_type, 
                        discount_value = :discount_value, 
                        additional_charges_percent = :additional_charges_percent, additional_charges_amount = :additional_charges_amount, packing_charges = :packing_charges, agent_commission = :agent_commission,
                        discount_amount = :discount_amount, 
                        discount = :discount, 
                        igst_percent = :igst_percent, 
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
                        transport_gst = :transport_gst,
                        hsn_code = :hsn_code
                        WHERE id = :id";
        
        $stmt = $db->prepare($update_invoice_query);
        $stmt->bindParam(':party_id', $_POST['party_id']);
        $stmt->bindParam(':price_code_id', $_POST['price_code_id']);
        $stmt->bindParam(':dispatch_from', $_POST['dispatch_from']);
        $stmt->bindParam(':dispatch_through', $_POST['dispatch_through']);
        $stmt->bindParam(':invoice_date', $_POST['invoice_date']);
        $stmt->bindParam(':taxable_amount', $_POST['taxable_amount']);
        $stmt->bindParam(':discount_type', $_POST['discount_type']);
        $stmt->bindParam(':discount_value', $_POST['discount_value']);
        $stmt->bindParam(':discount_amount', $_POST['discount_amount']);
        $stmt->bindParam(':additional_charges_percent', $_POST['additional_charges_percent']);
        $stmt->bindParam(':additional_charges_amount', $_POST['additional_charges_amount']);
        $stmt->bindParam(':packing_charges', $_POST['packing_charges']);
        $stmt->bindParam(':agent_commission', $_POST['agent_commission']);
        $stmt->bindParam(':discount', $_POST['discount']);
        $stmt->bindParam(':igst_percent', $_POST['igst_percent']);
        $stmt->bindParam(':igst_amount', $_POST['igst_amount']);
        $stmt->bindParam(':total_tax', $_POST['total_tax']);
        $stmt->bindParam(':net_amount', $_POST['net_amount']);
        $stmt->bindParam(':p_place', $_POST['p_place']);
        $stmt->bindParam(':p_state', $_POST['p_state']);
        $stmt->bindParam(':p_gst', $_POST['p_gst']);
        $stmt->bindParam(':p_address', $_POST['p_address']);
        $stmt->bindParam(':round_off', $_POST['round_off']);
        $stmt->bindParam(':tax_type', $_POST['tax_type']);
        $stmt->bindParam(':vehicle_no', $_POST['veh_no']);
        $stmt->bindParam(':eway_bill_no', $_POST['eway']);
        $stmt->bindParam(':transport_name', $_POST['transport_name']);
        $stmt->bindParam(':transport_gst', $_POST['transport_gst']);
        $stmt->bindParam(':hsn_code', $_POST['hsn_cd']);
        $stmt->bindParam(':id', $invoice_id);
        $stmt->execute();
        
        // Delete existing invoice items
        $delete_items_query = "DELETE FROM ff_sch.proforma_invoice_items WHERE invoice_id = :invoice_id";
        $delete_stmt = $db->prepare($delete_items_query);
        $delete_stmt->bindParam(':invoice_id', $invoice_id);
        $delete_stmt->execute();

        // Insert updated invoice items
        $insert_items_query = "INSERT INTO ff_sch.proforma_invoice_items (
            invoice_id, product_id, carton_contents, uom, rate, cartons, total_amount, carton_from, carton_to, qty, fiscal_year_id, discount_eligible
        ) VALUES (
            :invoice_id, :product_id, :carton_contents, :uom, :rate, :cartons, :total_amount, :carton_from, :carton_to, :qty, :fiscal_year_id, :discount_eligible
        )";
        $items_stmt = $db->prepare($insert_items_query);
        
        foreach($_POST['product_id'] as $key => $product_id) {
            if(!empty($product_id) && !empty($_POST['cartons'][$key]) && $_POST['cartons'][$key] > 0) {
                $items_stmt->bindParam(':invoice_id', $invoice_id);
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
                $items_stmt->bindParam(':fiscal_year_id', $fiscal_year_id);
                $discount_eligible = $_POST['discount_eligible'][$key] ?? 0;
                $items_stmt->bindParam(':discount_eligible', $discount_eligible);
                
                $items_stmt->execute();
            }
        }
        
        $db->commit();
        
        echo "<script>
                Swal.fire('Success!', 'Pro-forma invoice updated successfully!', 'success')
                    .then(() => { window.location.href = 'proforma_view.php'; });
            </script>";
        
    } catch(Exception $e) {
        $db->rollBack();
        $error_message = addslashes($e->getMessage());
        echo "<script>
                Swal.fire('Error!', 'Failed to update pro-forma invoice: {$error_message}', 'error');
                console.error('Pro-forma Invoice Update Error:', '{$error_message}');
            </script>";
    }
}
    ?>
    <?php include "../../includes/header.php"; ?>
    <?php include "../../includes/sidebar.php"; ?>

    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Edit Pro-forma Invoice - #<?php echo htmlspecialchars($invoice['invoice_no']); ?></h2>
            <div>
                <a href="proforma_view.php" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>Back to List
                </a>
                <button type="button" onclick="showPrintOptions('<?php echo $invoice_id; ?>', '<?php echo $fiscal_year_id; ?>')" class="btn btn-info">
                    <i class="fas fa-print me-2"></i>Print
                </button>
            </div>
        </div>

        <form method="POST" id="invoiceForm" onkeydown="return event.key != 'Enter';">
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title">Pro-forma Details</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Pro-forma No</label>
                                    <input type="text" class="form-control" name="invoice_no" value="<?php echo htmlspecialchars($invoice['invoice_no']); ?>" readonly>
                                    <small class="text-muted">Pro-forma number cannot be changed</small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Pro-forma Date</label>
                                    <input type="date" class="form-control" name="invoice_date" value="<?php echo $invoice['invoice_date']; ?>" required>
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
                                            <?php echo $party['name']; ?> (<?php echo $party['state']; ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Dispatch From</label>
                                    <input type="text" class="form-control" name="dispatch_from" value="<?php echo htmlspecialchars($invoice['dispatch_from'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Dispatch To</label>
                                    <input type="text" class="form-control" name="dispatch_through" value="<?php echo htmlspecialchars($invoice['dispatch_through']); ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Price Code *</label>
                                    <select class="form-select" name="price_code_id" id="price_code_id" required>
                                        <option value="">-- Select Price Code --</option>
                                    </select>
                                    <input type="hidden" id="fiscal_year_id" value="<?php echo $fiscal_year_id; ?>">
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
                                    <input type="text" class="form-control" name="p_place" id="p_place" value="<?php echo htmlspecialchars($invoice['p_place'] ?? ''); ?>">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Party State</label>
                                    <input type="text" class="form-control" name="p_state" id="p_state" value="<?php echo htmlspecialchars($invoice['p_state'] ?? ''); ?>" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Party GST</label>
                                    <input type="text" class="form-control" name="p_gst" id="p_gst" value="<?php echo htmlspecialchars($invoice['p_gst'] ?? ''); ?>">
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-12 mb-3">
                                    <label class="form-label">Party Address</label>
                                    <textarea class="form-control" name="p_address" id="p_address" rows="2" required><?php echo htmlspecialchars($invoice['p_address'] ?? ''); ?></textarea>
                                </div>
                            </div>

                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">HSN Code</label>
                                    <input type="text" class="form-control" name="hsn_cd" id="hsn_cd" value="<?php echo htmlspecialchars($invoice['hsn_code'] ?? '3604'); ?>">
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
                                                <?php echo $transport['name']; ?>
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
                                <?php if (!empty($proforma_invoice_items)): ?>
                                    <?php foreach($proforma_invoice_items as $item): ?>
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
                                                            <?php echo $product['name']; ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td style="display:none;"><input type="text" class="form-control uom" name="uom[]" value="<?php echo htmlspecialchars($item['uom']); ?>" readonly></td>
                                            <td><input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control cartons" name="cartons[]" value="<?php echo $item['cartons']; ?>" min="1"></td>
                                            <td><input type="text" class="form-control ctnCntnts" name="carton_contents[]" value="<?php echo htmlspecialchars($item['carton_contents']); ?>" readonly></td>
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
                                            <td><input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control qty" name="qty[]" value="<?php echo $item['qty']; ?>" readonly></td>
                                            <td><input type="text" class="form-control per_box_pieces" name="per_box_pieces[]" value="<?php echo htmlspecialchars($item['per_box_pieces']); ?>" readonly></td>
                                            <td><input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control rate" name="rate[]" value="<?php echo $item['rate']; ?>"></td>
                                            <td><input type="text" class="form-control total-amount" name="total_amount[]" value="<?php echo $item['total_amount']; ?>" readonly></td>
                                            <td class="text-center align-middle">
                                                <input type="hidden" name="discount_eligible[]" class="discount-eligible-hidden" value="<?php echo isset($item['discount_eligible']) ? $item['discount_eligible'] : '1'; ?>">
                                                <input type="checkbox" class="form-check-input discount-eligible" onchange="$(this).prev('.discount-eligible-hidden').val(this.checked ? 1 : 0);" <?php echo (!isset($item['discount_eligible']) || $item['discount_eligible']) ? 'checked' : ''; ?>>
                                            </td>
                                            <td><button type="button" class="btn btn-danger btn-sm remove-row"><i class="fas fa-trash"></i></button></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <!-- Show one empty row if no items -->
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
                                        <td><input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control qty" name="qty[]" readonly></td>
                                        <td><input type="text" class="form-control per_box_pieces" name="per_box_pieces[]" readonly></td>
                                        <td><input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control rate" name="rate[]"></td>
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
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-md-8 offset-md-4">

                    <div class="card mb-4 shadow-sm">

                        <div class="card-header">
                            <h5 class="card-title mb-0">Summary</h5>
                        </div>

                        <div class="card-body">

                            <!-- Hidden Fields -->
                            <input type="hidden" name="tax_type" id="tax_type" value="<?php echo $invoice['tax_type']; ?>">
                            <input type="hidden" name="discount_type" id="discount_type" value="percent">
                            <input type="hidden" name="packing_charges" id="packing_charges" value="<?php echo $invoice['packing_charges']; ?>">
                            <input type="hidden" name="agent_commission" id="agent_commission" value="<?php echo $invoice['agent_commission'] ?? 0; ?>">
                            <input type="hidden" name="gst_amount" id="gst_amount" value="<?php echo $invoice['total_tax'] ?? 0; ?>">

                            <?php
                            $goods_amt_calc = 0;

                            if (!empty($proforma_invoice_items)) {
                                foreach($proforma_invoice_items as $item) {
                                    $goods_amt_calc += $item['total_amount'];
                                }
                            }

                            $add_chg_calc = $invoice['additional_charges_amount'];
                            $add_chg_perc = $invoice['additional_charges_percent'];
                            
                            $add_chg_pct_calc = ($goods_amt_calc > 0)
                                ? ($add_chg_calc * $goods_amt_calc)
                                : 0;

                            $gross_calc = $goods_amt_calc + $add_chg_calc;

                            $disc_amt_calc = $invoice['discount_amount'];

                            $sub_tot_calc = $gross_calc - $disc_amt_calc;

                            $pack_amt_calc = $invoice['packing_charges'];
                            $pack_perc = $invoice['packing_perc'];

                            $pack_pct_calc = ($sub_tot_calc > 0)
                                ? ($pack_amt_calc / $sub_tot_calc) * 100
                                : 0;

                            $agent_comm_amt_calc = $invoice['agent_commission'] ?? 0;
                            $agent_comm_perc = $invoice['agent_perc'] ?? 0;

                            $agent_comm_pct_calc = ($sub_tot_calc > 0)
                                ? ($agent_comm_amt_calc / $sub_tot_calc) * 100
                                : 0;
                            ?>

                            <!-- Goods Amount -->
                            <div class="row align-items-center mb-3">

                                <div class="col-md-6">
                                    <strong>Goods Amount:</strong>
                                    <input type="hidden"
                                        id="goods_total_amount"
                                        value="0">
                                </div>

                                <div class="col-md-6 text-end">
                                    <span class="amount-display text-primary"
                                        id="goodsAmountDisplay">
                                        ₹0.00
                                    </span>
                                </div>

                            </div>

                            <!-- Additional Charges -->
                            <div class="row align-items-center mb-3">

                                <div class="col-md-5">
                                    <strong>Additional Charges (%):</strong>
                                </div>

                                <div class="col-md-3">
                                    <input type="text" onkeypress="return keyPressNumber(event,this);"
                                        class="form-control form-control-sm text-end"
                                        name="additional_charges_percent"
                                        id="additional_charges_percent"
                                        value="<?php echo round($add_chg_perc, 2); ?>"
                                       >
                                </div>

                                <div class="col-md-4 text-end">
                                    <span class="amount-display text-primary"
                                        id="additionalChargesDisplay">
                                        ₹0.00
                                    </span>
                                </div>

                            </div>

                            <!-- Discount -->
                            <div class="row align-items-center mb-3">

                                <div class="col-md-5">
                                    <strong>Discount (%):</strong>
                                </div>

                                <div class="col-md-3">
                                    <input type="text" onkeypress="return keyPressNumber(event,this);"
                                        class="form-control form-control-sm text-end"
                                        name="discount_value"
                                        id="discount_value"
                                        value="<?php echo $invoice['discount_value']; ?>"
                                       >
                                </div>

                                <div class="col-md-4 text-end">
                                    <span class="amount-display text-danger"
                                        id="discountDisplay">
                                        -₹0.00
                                    </span>
                                </div>

                            </div>

                            <!-- Sub Total -->
                            <div class="row align-items-center mb-3 border-top pt-3">

                                <div class="col-md-6">
                                    <strong>Sub Total:</strong>
                                </div>

                                <div class="col-md-6 text-end">
                                    <span class="amount-display text-primary"
                                        id="subTotalDisplay">
                                        ₹0.00
                                    </span>
                                </div>

                            </div>

                            <!-- Packing Charges -->
                            <div class="row align-items-center mb-3">

                                <div class="col-md-5">
                                    <strong>Packing Charges (%):</strong>
                                </div>

                                <div class="col-md-3">
                                    <input type="text" onkeypress="return keyPressNumber(event,this);"
                                        class="form-control form-control-sm text-end"
                                        id="packing_charges_percent"
                                        value="<?php echo round($pack_perc, 2); ?>"
                                       >
                                </div>

                                <div class="col-md-4 text-end">
                                    <span class="amount-display text-primary"
                                        id="packingChargesDisplay">
                                        ₹0.00
                                    </span>
                                </div>

                            </div>

                            <!-- Agent Commission -->
                            <div class="row align-items-center mb-3">

                                <div class="col-md-5">
                                    <strong>Agent Commission (%):</strong>
                                </div>

                                <div class="col-md-3">
                                    <input type="text" onkeypress="return keyPressNumber(event,this);"
                                        class="form-control form-control-sm text-end"
                                        id="agent_commission_percent"
                                        value="<?php echo round($agent_comm_perc, 2); ?>"
                                       >
                                </div>

                                <div class="col-md-4 text-end">
                                    <span class="amount-display text-danger"
                                        id="agentCommissionDisplay">
                                        -₹0.00
                                    </span>
                                </div>

                            </div>

                            <!-- Subtotal 2 -->
                            <div class="row align-items-center mb-3 border-top pt-3">

                                <div class="col-md-6">
                                    <strong>Subtotal 2:</strong>
                                </div>

                                <div class="col-md-6 text-end">
                                    <span class="amount-display text-primary"
                                        id="subTotal2Display">
                                        ₹0.00
                                    </span>
                                </div>

                            </div>

                            <!-- GST -->
                            <div class="row align-items-center mb-3">

                                <div class="col-md-5">
                                    <strong>GST Amount:</strong>
                                </div>

                                <div class="col-md-3">
                                    <input type="text" onkeypress="return keyPressNumber(event,this);"
                                        class="form-control form-control-sm text-end"
                                        name="total_tax"
                                        id="total_tax_input"
                                        value="<?php echo $invoice['total_tax']; ?>"
                                       >
                                </div>
                                <div class="col-md-4 text-end">
                                    <span class="amount-display text-primary"
                                        id="gstAmountDisplay">₹0.00</span>
                                </div>

                            </div>

                            <!-- Round Off -->
                            <div class="row align-items-center mb-3 d-none"
                                id="roundOffSummary">

                                <div class="col-md-6">
                                    <strong>Round Off:</strong>
                                </div>

                                <div class="col-md-6 text-end">
                                    <span class="amount-display text-danger"
                                        id="roundOffDisplay">
                                        ₹0.00
                                    </span>
                                </div>

                            </div>

                            <!-- Net Amount -->
                            <div class="row align-items-center border-top pt-4">

                                <div class="col-md-6">
                                    <h4 class="mb-0">
                                        <strong>Net Amount:</strong>
                                    </h4>
                                </div>

                                <div class="col-md-6 text-end">
                                    <h4 class="amount-display text-success mb-0"
                                        id="netAmountDisplay">
                                        ₹0.00
                                    </h4>
                                </div>

                            </div>

                        </div>

                    </div>

                </div>
            </div>

            <!-- Hidden fields for form submission -->
            <input type="hidden" name="taxable_amount" id="taxable_amount" value="<?php echo $invoice['taxable_amount']; ?>">
            <input type="hidden" name="additional_charges_amount" id="additional_charges_amount" value="<?php echo $invoice['additional_charges_amount']; ?>">
            <input type="hidden" name="sgst_percent" id="sgst_percent" value="<?php echo $invoice['sgst_percent']; ?>">
            <input type="hidden" name="cgst_percent" id="cgst_percent" value="<?php echo $invoice['cgst_percent']; ?>">
            <input type="hidden" name="igst_percent" id="igst_percent" value="<?php echo $invoice['igst_percent']; ?>">
            <input type="hidden" name="sgst_amount" id="sgst_amount" value="<?php echo $invoice['sgst_amount']; ?>">
            <input type="hidden" name="cgst_amount" id="cgst_amount" value="<?php echo $invoice['cgst_amount']; ?>">
            <input type="hidden" name="igst_amount" id="igst_amount" value="<?php echo $invoice['igst_amount']; ?>">
            <input type="hidden" name="total_tax" id="total_tax" value="<?php echo $invoice['total_tax']; ?>">
            <input type="hidden" name="net_amount" id="net_amount" value="<?php echo $invoice['net_amount']; ?>">
            <input type="hidden" name="round_off" id="round_off" value="<?php echo $invoice['round_off']; ?>">
            <input type="hidden" name="discount_amount" id="discount_amount" value="<?php echo $invoice['discount_amount']; ?>">
            <input type="hidden" name="discount" id="discount" value="<?php echo $invoice['discount_amount']; ?>">

            <div class="text-center d-flex justify-content-end">
                <button type="submit" class="btn btn-primary btn-lg me-3">
                    <i class="fas fa-save"></i> Update Pro-forma
                </button>
                <a href="proforma_view.php" class="btn btn-secondary btn-lg">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/jquery-3.6.0.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/select2.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/script.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/sidebar.js"></script>
    
    <!-- Re-using the same JS logic from add page -->
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

        // Copying the JS from proforma_add.php and adapting for edit
        function initializeSelect2() {
            $('.transport-select').select2({
                theme: 'bootstrap4',
                placeholder: "Select Transport",
                allowClear: true,
                width: '100%'
            }).on('select2:open', function() {
                setTimeout(() => { document.querySelector('.select2-container--open .select2-search__field').focus(); }, 100);
            });
            
            $('.party-select').select2({
                theme: 'bootstrap4',
                placeholder: "Select Party",
                allowClear: true,
                width: '100%'
            }).on('select2:open', function() {
                setTimeout(() => { document.querySelector('.select2-container--open .select2-search__field').focus(); }, 100);
            });

            $('.product-select').each(function() {
                if (!$(this).hasClass('select2-hidden-accessible')) {
                    $(this).select2({ theme: 'bootstrap4', placeholder: "Select Product", allowClear: true, width: '100%', dropdownParent: $('body') });
                }
            }).on('select2:open', function() {
                setTimeout(() => { document.querySelector('.select2-container--open .select2-search__field').focus(); }, 100);
            });
        }

        function fetchPartyDetails(partyId) {
            if (!partyId) {
                $('#p_place, #p_state, #p_gst, #p_address').val('');
                return;
            }
            $.ajax({
                url: '../../includes/ajax_actions.php', type: 'POST', data: { party_id: partyId, action: 'getPartyDetails'}, dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#p_place').val(response.data.city || '');
                        $('#p_state').val(response.data.state || '');
                        $('#p_gst').val(response.data.gst_no || '');
                        $('#p_address').val(response.data.address || '');
                        const businessState = "TAMIL NADU";
                        const taxType = (response.data.state === businessState) ? 'intrastate' : 'interstate';
                        $('#tax_type').val(taxType);
                        toggleTaxFields(taxType);
                        calculateTax();
                    }
                }
            });
        }

        function updateTransportGST(select) {
            const selectedOption = select.options[select.selectedIndex];
            $('#transport_gst').val(selectedOption.value ? (selectedOption.getAttribute('data-gst') || '') : '');
        }

        function calculateCartonRanges() {
            let cartonStart = currentCartonStart+1;
            $('#productsTable tbody tr').each(function() {
                const cartons = parseInt($(this).find('.cartons').val()) || 0;
                const isExisting = $(this).attr('data-existing') === 'true';

                if (isExisting) {
                    const cartonFrom = parseInt($(this).find('.carton-from').val()) || 0;
                    if (cartons > 0 && cartonFrom > 0) {
                        const cartonTo = cartonFrom + cartons - 1;
                        $(this).find('.carton-to').val(cartonTo);
                        $(this).find('.carton-range-display').text(cartons === 1 ? cartonFrom.toString() : `${cartonFrom} - ${cartonTo}`);
                    }
                } else {
                    if (cartons > 0) {
                        const cartonFrom = cartonStart;
                        const cartonTo = cartonStart + cartons - 1;
                        $(this).find('.carton-from').val(cartonFrom);
                        $(this).find('.carton-to').val(cartonTo);
                        $(this).find('.carton-range-display').text(cartons === 1 ? cartonFrom.toString() : `${cartonFrom} - ${cartonTo}`);
                        cartonStart = cartonTo + 1;
                    } else {
                        $(this).find('.carton-from, .carton-to').val(0);
                        $(this).find('.carton-range-display').text('-');
                    }
                }
            });
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

        function attachRowEventHandlers(row) {
            row.find('.rate, .cartons').off('input change').on('input change', function() {
                const currentRow = $(this).closest('tr');
                calculateRowQty(currentRow);
                calculateRowTotal(currentRow);
                calculateCartonRanges();
            });
            row.find('.remove-row').off('click').on('click', function() { removeRow(this); });
            row.find('.product-select').off('select2:select').on('select2:select', function() { updateProductDetails(this); });
            row.find('.discount-eligible').off('change').on('change', function() { calculateTax(); });
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
            
            // Reset select options
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
            if ($('#productsTable tbody tr').length >= 1) {
                $(button).closest('tr').find('.product-select').select2('destroy');
                $(button).closest('tr').remove();
                calculateCartonTotal();
                calculateCartonRanges();
                calculateTax();
            } else {
                Swal.fire('Warning!', 'At least one product is required!', 'warning');
            }
        }

        function updateProductDetails(select) {
            const row = $(select).closest('tr');
            const selectedOption = select.options[select.selectedIndex];
            const productId = $(select).val();
            const priceCodeId = $('#price_code_id').val();

            if (productId) {
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
                            calculateRowQty(row);
                            calculateRowTotal(row);
                        }
                    }
                });
            } else {
                row.find('.rate, .ctnCntnts, .uom, .per_box_pieces, .qty, .total-amount').val('');
                row.find('.total-amount, .carton-from, .carton-to').val('0');
                calculateGrandTotal();
                calculateCartonTotal();
            }
        }

        function calculateRowQty(row) {
            const cartons = parseFloat(row.find('.cartons').val()) || 0;
            const ctnContents = parseFloat(row.find('.ctnCntnts').val()) || 0;
            row.find('.qty').val((cartons * ctnContents).toFixed(0));
        }

        function calculateRowTotal(row) {
            const rate = parseFloat(row.find('.rate').val()) || 0;
            const qty = parseFloat(row.find('.qty').val()) || 0;
            const cartons = parseFloat(row.find('.cartons').val()) || 0;
            const ctnCntnts = parseFloat(row.find('.ctnCntnts').val()) || 0;
            const per = parseFloat(row.find('.per_box_pieces').val()) || 0;
            let total = 0;
            if (ctnCntnts === per) { total = cartons * rate; } 
            else if (per !== ctnCntnts && per !== 0) { total = qty * (rate / per); } 
            else { total = rate * qty; }
            row.find('.total-amount').val(total.toFixed(2));
            calculateGrandTotal();
            calculateCartonTotal();
        }

        function calculateCartonTotal() {
            let total = 0;
            $('.cartons').each(function() { total += parseFloat($(this).val()) || 0; });
            $('#cartonTotal').val(total.toFixed(0));
        }

        function calculateGrandTotal() {
            let total = 0;
            $('.total-amount').each(function() { total += parseFloat($(this).val()) || 0; });
            $('#grandTotal').val(total.toFixed(2));
            $('#goods_total_amount').val(total.toFixed(2));
            calculateTax();
        }

        function calculateTax() {
            const goodsAmount = parseFloat($('#goods_total_amount').val()) || 0;
            const additionalChargesPercent = parseFloat($('#additional_charges_percent').val()) || 0;
            const discountPercent = parseFloat($('#discount_value').val()) || 0;
            const packingChargesPercent = parseFloat($('#packing_charges_percent').val()) || 0;
            const agentCommissionPercent = parseFloat($('#agent_commission_percent').val()) || 0;
            const totalTaxInput = parseFloat($('#total_tax_input').val()) || 0;
            
            let discountableAmount = 0;
            $('#productsTable tbody tr').each(function() {
                if ($(this).find('.discount-eligible').is(':checked')) {
                    discountableAmount += parseFloat($(this).find('.total-amount').val()) || 0;
                }
            });

            // 1. Gross
            const additionalChargesAmount = goodsAmount * additionalChargesPercent;
            const grossAmount = goodsAmount + additionalChargesAmount;

            // 2. Discount
            const discountAmount = discountableAmount * (discountPercent / 100);
            
            // 3. Sub Total
            const subTotal = additionalChargesAmount - discountAmount;
            
            // 4. Packing Charges Amount
            const packingChargesAmount = subTotal * (packingChargesPercent / 100);

            // 5. Agent Commission Amount
            const agentCommission = subTotal * (agentCommissionPercent / 100);

            // 6. Subtotal 2
            // const subTotal2 = subTotal - agentCommission + packingChargesAmount;
            const subTotal2 = (subTotal + packingChargesAmount) - agentCommission;
            
            // 7. GST Amount
            const totalTax = totalTaxInput;
            
            // 8. Net Amount
            const netAmountBeforeRoundoff = subTotal2 + totalTax;
            
            const roundedNetAmount = Math.round(netAmountBeforeRoundoff);
            const roundOffAmount = roundedNetAmount - netAmountBeforeRoundoff;
            
            // Update DB hidden fields
            $('#additional_charges_amount').val(additionalChargesAmount.toFixed(2));
            $('#discount').val(discountAmount.toFixed(2));
            $('#discount_amount').val(discountAmount.toFixed(2));
            $('#taxable_amount').val(subTotal2.toFixed(2));
            $('#total_tax').val(totalTax.toFixed(2));
            $('#packing_charges').val(packingChargesAmount.toFixed(2));
            $('#agent_commission').val(agentCommission.toFixed(2));
            $('#gst_amount').val(totalTax.toFixed(2));
            $('#net_amount').val(roundedNetAmount.toFixed(2));
            $('#round_off').val(roundOffAmount.toFixed(2));
            
            const taxType = $('#tax_type').val() || 'intrastate';
            let sgstAmount = 0, cgstAmount = 0, igstAmount = 0;
            if (taxType === 'intrastate') {
                sgstAmount = totalTax / 2;
                cgstAmount = totalTax / 2;
            } else {
                igstAmount = totalTax;
            }
            $('#sgst_percent').val('0');
            $('#cgst_percent').val('0');
            $('#igst_percent').val('0');
            $('#sgst_amount').val(sgstAmount.toFixed(2));
            $('#cgst_amount').val(cgstAmount.toFixed(2));
            $('#igst_amount').val(igstAmount.toFixed(2));
            
            // Update Displays
            $('#goodsAmountDisplay').text(`₹${goodsAmount.toFixed(2)}`);
            $('#additionalChargesDisplay').text(`₹${additionalChargesAmount.toFixed(2)}`);
            $('#discountDisplay').text(`-₹${discountAmount.toFixed(2)}`);
            $('#subTotalDisplay').text(`₹${subTotal.toFixed(2)}`);
            $('#packingChargesDisplay').text(`₹${packingChargesAmount.toFixed(2)}`);
            $('#agentCommissionDisplay').text(`-₹${agentCommission.toFixed(2)}`);
            $('#gstAmountDisplay').text(`₹${totalTax.toFixed(2)}`);
            $('#subTotal2Display').text(`₹${subTotal2.toFixed(2)}`);
            $('#netAmountDisplay').text(`₹${roundedNetAmount.toFixed(2)}`);

            if (roundOffAmount.toFixed(2) != 0.00) {
                $('#roundOffSummary').removeClass('d-none');
                $('#roundOffDisplay').text(`₹${roundOffAmount.toFixed(2)}`).removeClass('text-success text-danger').addClass(roundOffAmount >= 0 ? 'text-success' : 'text-danger');
            } else {
                $('#roundOffSummary').addClass('d-none');
            }
        }

        $(document).ready(function() {
            loadPriceCodes($('#fiscal_year_id').val());

            initializeSelect2();
            $('#productsTable tbody tr').each(function() { attachRowEventHandlers($(this)); });
            calculateGrandTotal();
            calculateCartonTotal();
            calculateCartonRanges();
            calculateTax();

            $('#party_id').on('change', function() { fetchPartyDetails($(this).val()); });
            $('#transport_name').on('change', function() { updateTransportGST(this); });
            $('input[name="discount_type"]').on('change', function() { $('#discountLabel').text(this.value === 'percent' ? 'Discount Percentage' : 'Discount Amount'); calculateTax(); });
            $(document).on('change input', '#total_tax_input, #additional_charges_percent, #packing_charges_percent, #discount_value, #agent_commission_percent', calculateTax);
            
            // Recalculate on change of price code
            $('#price_code_id').on('change', function() {
                $('#productsTable tbody tr').each(function() {
                    $(this).find('.product-select').trigger('change');
                });
            });
            
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
                
                calculateCartonRanges();
                $('#productsTable tbody tr').each(function() {
                    calculateRowQty($(this));
                });
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
                    window.open(`proforma_print.php?id=${invoiceId}&year=${year}&copy=${result.value}`, '_blank');
                }
            });
        }
    </script>
    <script>
        $(document).keydown(function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                e.preventDefault();
                $('#invoiceForm').submit();
            }

            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'c') {
                e.preventDefault();
                window.location.href = 'proforma_view.php';
            }

        });
    </script>
</body>
</html>