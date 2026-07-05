<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";

$database = new Database();
$db = $database->getConnection();

// Get parties, products, transports, master
$parties = $db->query("SELECT * FROM ff_sch.parties WHERE is_active = true ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$products = $db->query("SELECT id, name, uom, per_box_pieces FROM ff_sch.products WHERE is_active = true ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);
$transports = $db->query("SELECT id, name, gst_no FROM ff_sch.transport WHERE is_active = true ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

$master = $db->query("SELECT * FROM ff_sch.master WHERE is_active = true LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$hsn_cd = $master['hsn_code'] ?? '';

$fiscal_year = $db->query("SELECT * FROM ff_sch.fiscal_years WHERE is_active = true LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$current_fiscal_year_id = $fiscal_year ? $fiscal_year['id'] : 1;

// Max Inv ID
$quotation_query = $db->query("SELECT max(quotation_no) as count FROM ff_sch.quotation where fiscal_year_id ='".$current_fiscal_year_id."'");
$quotation_data = $quotation_query->fetch(PDO::FETCH_ASSOC);
$max_id = ($quotation_data['count'] ?? 0) + 1;
$quotation_no = $max_id;

if($_POST) {
    // Check duplicate quotation number
    $check_sql = "SELECT 1 FROM ff_sch.quotation WHERE quotation_no = :quotation_no AND fiscal_year_id = :fiscal_year_id LIMIT 1";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->bindParam(':quotation_no', $_POST['quotation_no']);
    $check_stmt->bindParam(':fiscal_year_id', $current_fiscal_year_id);
    $check_stmt->execute();

    if ($check_stmt->fetch()) {
        echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire('Duplicate Quotation!', 'Quotation number already exists.', 'warning');
                });
            </script>";
        $quotation_no = $_POST['quotation_no'];
    } else {
        try {
            $db->beginTransaction();
            
            $query = "INSERT INTO ff_sch.quotation (
                quotation_no, party_id, price_code_id, quotation_date, agent_name, dispatch_from, dispatch_through,
                p_place, p_state, p_gst, p_address, hsn_code, eway_bill_no, vehicle_no, transport_name, transport_gst,
                goods_value, discount_percent, sub_total, packing_percent, mahamai_percent, insurance_percent,
                commission_percent, taxable_amount, sgst_percent, cgst_percent, igst_percent, net_amount, 
                fiscal_year_id, created_by
            ) VALUES (
                :quotation_no, :party_id, :price_code_id, :quotation_date, :agent_name, :dispatch_from, :dispatch_through,
                :p_place, :p_state, :p_gst, :p_address, :hsn_code, :eway_bill_no, :vehicle_no, :transport_name, :transport_gst,
                :goods_value, :discount_percent, :sub_total, :packing_percent, :mahamai_percent, :insurance_percent,
                :commission_percent, :taxable_amount, :sgst_percent, :cgst_percent, :igst_percent, :net_amount, 
                :fiscal_year_id, :created_by
            )";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':quotation_no', $_POST['quotation_no']);
            $stmt->bindParam(':party_id', $_POST['party_id']);
            $stmt->bindParam(':price_code_id', $_POST['price_code_id']);
            $stmt->bindParam(':quotation_date', $_POST['quotation_date']);
            $stmt->bindParam(':agent_name', $_POST['agent_name']);
            $stmt->bindParam(':dispatch_from', $_POST['dispatch_from']);
            $stmt->bindParam(':dispatch_through', $_POST['dispatch_through']);
            $stmt->bindParam(':p_place', $_POST['p_place']);
            $stmt->bindParam(':p_state', $_POST['p_state']);
            $stmt->bindParam(':p_gst', $_POST['p_gst']);
            $stmt->bindParam(':p_address', $_POST['p_address']);
            $stmt->bindParam(':hsn_code', $_POST['hsn_cd']);
            $stmt->bindParam(':eway_bill_no', $_POST['eway']);
            $stmt->bindParam(':vehicle_no', $_POST['veh_no']);
            $stmt->bindParam(':transport_name', $_POST['transport_name']);
            $stmt->bindParam(':transport_gst', $_POST['transport_gst']);
            $stmt->bindParam(':goods_value', $_POST['goods_value']);
            $stmt->bindParam(':discount_percent', $_POST['discount_percent']);
            $stmt->bindParam(':sub_total', $_POST['sub_total']);
            $stmt->bindValue(':packing_percent', $_POST['packing_percent'] ?: 0);
            $stmt->bindValue(':mahamai_percent', $_POST['mahamai_percent'] ?: 0);
            $stmt->bindValue(':insurance_percent', $_POST['insurance_percent'] ?: 0);
            $stmt->bindValue(':commission_percent', $_POST['commission_percent'] ?: 0);
            $stmt->bindParam(':taxable_amount', $_POST['taxable_amount']);
            $stmt->bindValue(':sgst_percent', $_POST['sgst_percent'] ?: 0);
            $stmt->bindValue(':cgst_percent', $_POST['cgst_percent'] ?: 0);
            $stmt->bindValue(':igst_percent', $_POST['igst_percent'] ?: 0);
            $stmt->bindParam(':net_amount', $_POST['net_amount']);
            $stmt->bindValue(':fiscal_year_id', $current_fiscal_year_id);
            $stmt->bindParam(':created_by', $_SESSION['user_id']);
            
            $stmt->execute();
            $quotation_id = $db->lastInsertId();

            $items_query = "INSERT INTO ff_sch.quotation_items (
                quotation_id, product_id, cases, counts, qty, rate, per, discount_eligible, amount
            ) VALUES (
                :quotation_id, :product_id, :cases, :counts, :qty, :rate, :per, :discount_eligible, :amount
            )";
            $items_stmt = $db->prepare($items_query);
            
            foreach($_POST['product_id'] as $key => $product_id) {
                if(!empty($product_id)) {
                    $items_stmt->bindParam(':quotation_id', $quotation_id);
                    $items_stmt->bindParam(':product_id', $product_id);
                    $items_stmt->bindParam(':cases', $_POST['case'][$key]);
                    $items_stmt->bindParam(':counts', $_POST['counts'][$key]);
                    $items_stmt->bindParam(':qty', $_POST['qty'][$key]);
                    $items_stmt->bindParam(':rate', $_POST['rate'][$key]);
                    $items_stmt->bindParam(':per', $_POST['per'][$key]);
                    $items_stmt->bindParam(':discount_eligible', $_POST['discount_eligible'][$key]);
                    $items_stmt->bindParam(':amount', $_POST['amount'][$key]);
                    
                    $items_stmt->execute();
                }
            }
            
            $db->commit();
            
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire('Success!', 'Quotation created successfully!', 'success')
                        .then(() => { window.location.href = 'view.php'; });
                });
                </script>";
            
        } catch(Exception $e) {
            $db->rollBack();
            $error_message = addslashes($e->getMessage());
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire('Error!', 'Failed to create quotation: {$error_message}', 'error');
                });
                </script>";
        }
    }
}
?>
<?php include "../../includes/header.php"; ?>
<?php include "../../includes/sidebar.php"; ?>

<!-- Local Asset Files -->
<link href="<?php echo ASSETS_URL; ?>/css/select2.min.css" rel="stylesheet">
<link href="<?php echo ASSETS_URL; ?>/css/select2-bootstrap4.min.css" rel="stylesheet">
<style>
    .amount-display {
        font-size: 1.25rem;
        font-weight: 600;
    }
    .text-right {
        text-align: right !important;
    }
</style>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fa fa-file-text"></i> Add Quotation</h2>
        <a href="view.php" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back to List</a>
    </div>
    <!-- <h2 class="mb-4"><i class="fa fa-file-text"></i> Add Quotation</h2> -->
    <form id="addQuotationForm" method="POST" action="">
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title">Quotation Details</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Quotation No</label>
                                <input type="text" class="form-control" id="quotation_no" name="quotation_no" value="<?php echo $quotation_no; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Quotation Date</label>
                                <input type="date" class="form-control" name="quotation_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Party Name</label>
                            <select class="form-select party-select" name="party_id" id="party_id" required>
                                <option value="">Select Party</option>
                                <?php foreach($parties as $party): ?>
                                    <option value="<?php echo $party['id']; ?>" data-state="<?php echo htmlspecialchars($party['state']); ?>">
                                        <?php echo htmlspecialchars($party['name']); ?> (<?php echo htmlspecialchars($party['state']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Agent Name</label>
                                <input type="text" class="form-control" name="agent_name" id="agent_name">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Dispatch From</label>
                                <input type="text" class="form-control" name="dispatch_from">
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label">Dispatch To</label>
                                <input type="text" class="form-control" name="dispatch_through">
                            </div>
                            <div class="col-md-12 mb-3">
                                <label class="form-label">Price Code *</label>
                                <select class="form-select" name="price_code_id" id="price_code_id" required>
                                    <option value="">-- Select Price Code --</option>
                                </select>
                                <input type="hidden" id="fiscal_year_id" value="<?php echo $current_fiscal_year_id; ?>">
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
                                <input type="text" class="form-control" name="hsn_cd" id="hsn_cd" value="<?= htmlspecialchars($hsn_cd) ?>" readonly>
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
                                        <option value="<?php echo htmlspecialchars($transport['name']); ?>" data-gst="<?php echo htmlspecialchars($transport['gst_no']); ?>">
                                            <?php echo htmlspecialchars($transport['name']); ?>
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
                    
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title">Products</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="productsTable">
                        <thead>
                            <tr>
                                <th width="30%">Product</th>
                                <th>Case</th>
                                <th>Counts</th>
                                <th>Qty</th>
                                <th>Rate</th>
                                <th>Per</th>
                                <th class="text-center">Disc.</th>
                                <th>Amount</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <select class="form-select form-control-sm product-select" name="product_id[]">
                                        <option value="">Select Product</option>
                                        <?php foreach($products as $product): ?>
                                            <option value="<?php echo $product['id']; ?>">
                                                <?php echo htmlspecialchars($product['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td><input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control form-control-sm calc-trigger" name="case[]" placeholder="0"></td>
                                <td><input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control form-control-sm calc-trigger" name="counts[]" placeholder="0"></td>
                                <td><input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control form-control-sm calc-trigger" name="qty[]" placeholder="0" readonly></td>
                                <td><input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control form-control-sm calc-trigger rate" name="rate[]" placeholder="0.00"></td>
                                <td><input type="text" class="form-control form-control-sm" name="per[]" value="1 CASE"></td>
                                <td class="text-center align-middle">
                                    <input type="hidden" class="discount-eligible-hidden" name="discount_eligible[]" value="Yes">
                                    <input type="checkbox" class="form-check-input calc-trigger discount-eligible" checked>
                                </td>
                                <td><input type="text" class="form-control form-control-sm amount" name="amount[]" readonly value="0.00"></td>
                                <td><button type="button" class="btn btn-danger btn-sm remove-row"><i class="fa fa-trash"></i></button></td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="8" class="text-end text-right"></td>
                                <td><button type="button" class="btn btn-success btn-sm" id="addRow"><i class="fas fa-plus"></i></button></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6 offset-md-6">
                        <div class="card bg-light">
                            <div class="card-header"><h5 class="card-title mb-0">Summary</h5></div>
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong>Goods Value:</strong>
                                    <input type="text" class="form-control-plaintext w-50 text-end text-right fw-bold" id="goodsValue" name="goods_value" value="0.00" readonly>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong>Discount:</strong>
                                    <div class="d-flex align-items-center" style="width: 55%;">
                                        <div class="input-group input-group-sm w-50" style="padding-right: 10px;">
                                            <input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control calc-trigger text-end text-right" id="discount_percent" name="discount_percent" value="0">
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <span class="w-50 text-end text-right text-danger fw-bold" id="discountAmtDisplay">-0.00</span>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong>Sub Total:</strong>
                                    <input type="text" class="form-control-plaintext w-50 text-end text-right fw-bold" id="subTotal" name="sub_total" value="0.00" readonly>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong>Packing Charges:</strong>
                                    <div class="d-flex align-items-center" style="width: 55%;">
                                        <div class="input-group input-group-sm w-50" style="padding-right: 10px;">
                                            <input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control calc-trigger text-end text-right" id="packing_percent" name="packing_percent" value="0">
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <span class="w-50 text-end text-right fw-bold" id="packingAmtDisplay">0.00</span>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong>Mahamai:</strong>
                                    <div class="d-flex align-items-center" style="width: 55%;">
                                        <div class="input-group input-group-sm w-50" style="padding-right: 10px;">
                                            <input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control calc-trigger text-end text-right" id="mahamai_percent" name="mahamai_percent" value="0">
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <span class="w-50 text-end text-right fw-bold" id="mahamaiAmtDisplay">0.00</span>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong>Insurance:</strong>
                                    <div class="d-flex align-items-center" style="width: 55%;">
                                        <div class="input-group input-group-sm w-50" style="padding-right: 10px;">
                                            <input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control calc-trigger text-end text-right" id="insurance_percent" name="insurance_percent" value="0">
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <span class="w-50 text-end text-right fw-bold" id="insuranceAmtDisplay">0.00</span>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong>Commission:</strong>
                                    <div class="d-flex align-items-center" style="width: 55%;">
                                        <div class="input-group input-group-sm w-50" style="padding-right: 10px;">
                                            <input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control calc-trigger text-end text-right" id="commission_percent" name="commission_percent" value="0">
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <span class="w-50 text-end text-right fw-bold" id="commissionAmtDisplay">0.00</span>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong>Taxable Amount:</strong>
                                    <input type="text" class="form-control-plaintext w-50 text-end text-right fw-bold" id="taxableAmount" name="taxable_amount" value="0.00" readonly>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong>SGST:</strong>
                                    <div class="d-flex align-items-center" style="width: 55%;">
                                        <div class="input-group input-group-sm w-50" style="padding-right: 10px;">
                                            <input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control calc-trigger text-end text-right" id="sgst_percent" name="sgst_percent" value="0">
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <span class="w-50 text-end text-right fw-bold" id="sgstAmtDisplay">0.00</span>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong>CGST:</strong>
                                    <div class="d-flex align-items-center" style="width: 55%;">
                                        <div class="input-group input-group-sm w-50" style="padding-right: 10px;">
                                            <input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control calc-trigger text-end text-right" id="cgst_percent" name="cgst_percent" value="0">
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <span class="w-50 text-end text-right fw-bold" id="cgstAmtDisplay">0.00</span>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <strong>IGST:</strong>
                                    <div class="d-flex align-items-center" style="width: 55%;">
                                        <div class="input-group input-group-sm w-50" style="padding-right: 10px;">
                                            <input type="text" onkeypress="return keyPressNumber(event,this);" class="form-control calc-trigger text-end text-right" id="igst_percent" name="igst_percent" value="0">
                                            <span class="input-group-text">%</span>
                                        </div>
                                        <span class="w-50 text-end text-right fw-bold" id="igstAmtDisplay">0.00</span>
                                    </div>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between align-items-center mb-2">
                                    <h4 class="mb-0"><strong>Net Amount:</strong></h4>
                                    <input type="text" class="form-control-plaintext w-50 text-end text-right amount-display text-success" id="netAmount" name="net_amount" value="0.00" readonly>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="text-right mt-3 mb-4">
                    <button class="btn btn-primary btn-lg" type="submit"><i class="fa fa-fw fa-lg fa-check-circle"></i>Save Quotation</button>
                </div>
            </div>
        </div>
    </form>
</div>

<?php include "../../includes/footer.php"; ?>
<script src="<?php echo ASSETS_URL; ?>/js/select2.min.js"></script>
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
$(document).ready(function() {
    
    function initializeSelect2() {
        $('.party-select').select2({ theme: 'bootstrap4', placeholder: "Select Party", allowClear: true, width: '100%' }).on('select2:open', function() {
            setTimeout(() => {
                document.querySelector('.select2-container--open .select2-search__field').focus()
            }, 100);
        });
        $('#price_code_id').select2({ theme: 'bootstrap4', placeholder: "Select Price Code", allowClear: true, width: '100%' }).on('select2:open', function() {
            setTimeout(() => {
                document.querySelector('.select2-container--open .select2-search__field').focus()
            }, 100);
        });
        $('.transport-select').select2({ theme: 'bootstrap4', placeholder: "Select Transport", allowClear: true, width: '100%' }).on('select2:open', function() {
            setTimeout(() => {
                document.querySelector('.select2-container--open .select2-search__field').focus()
            }, 100);
        });

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
        });
    }

    initializeSelect2();

    function loadPriceCodes(fiscalYearId) {
        if (!fiscalYearId) return;
        $.ajax({
            url: '<?php echo BASE_URL; ?>/includes/ajax_actions.php',
            type: 'POST',
            dataType: 'json',
            data: { action: 'getPriceCodes', fiscal_year_id: fiscalYearId },
            success: function(response) {
                const pcSelect = $('#price_code_id');
                pcSelect.empty().append('<option value="">-- Select Price Code --</option>');
                if (response.success && response.data.length > 0) {
                    response.data.forEach(pc => {
                        pcSelect.append(`<option value="${pc.id}">${pc.name}</option>`);
                    });
                }
            }
        });
    }
    
    loadPriceCodes($('#fiscal_year_id').val());
    
    $('#price_code_id').on('change', function() {
        $('.product-select').trigger('change');
    });

    $('#party_id').on('change', function() {
        const partyId = $(this).val();
        if (!partyId) {
            $('#p_place, #p_state, #p_gst, #p_address, #agent_name').val('');
            return;
        }
        $.ajax({
            url: '<?php echo BASE_URL; ?>/includes/ajax_actions.php',
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
                }
            }
        });
    });

    $('#transport_name').on('change', function() {
        const selectedOption = this.options[this.selectedIndex];
        if (selectedOption.value) {
            $('#transport_gst').val(selectedOption.getAttribute('data-gst') || '');
        } else {
            $('#transport_gst').val('');
        }
    });

    $(document).on('change', '.product-select', function() {
        const row = $(this).closest('tr');
        const productId = $(this).val();
        const priceCodeId = $('#price_code_id').val();
        
        if(productId) {
            if (!priceCodeId) {
                Swal.fire('Warning', 'Please select a Price Code first.', 'warning');
                $(this).val('').trigger('change');
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
                        calculateQuotation();
                    } else {
                        Swal.fire('Error', 'Could not fetch product details or price.', 'error');
                    }
                }
            });
        } else {
            row.find('.rate').val('');
            calculateQuotation();
        }
    });

    function calculateQuotation() {
        let goodsValue = 0;
        let discountableValue = 0;
        
        $('#productsTable tbody tr').each(function() {
            let row = $(this);
            let cases = parseFloat(row.find('input[name="case[]"]').val()) || 0;
            let counts = parseFloat(row.find('input[name="counts[]"]').val()) || 0;
            let rate = parseFloat(row.find('input[name="rate[]"]').val()) || 0;
            let per = row.find('input[name="per[]"]').val() || '';
            let discountEligible = row.find('.discount-eligible-hidden').val();
            
            let qty = cases * counts;
            row.find('input[name="qty[]"]').val(qty);
            
            let amount = (qty * rate);
            
            row.find('input[name="amount[]"]').val(amount.toFixed(2));
            
            goodsValue += amount;
            if (discountEligible === 'Yes') discountableValue += amount;
        });
        
        $('#goodsValue').val(goodsValue.toFixed(2));
        
        let discountPercent = parseFloat($('#discount_percent').val()) || 0;
        let discountAmount = discountableValue * (discountPercent / 100);
        $('#discountAmtDisplay').text('-' + discountAmount.toFixed(2));
        
        let subTotal = goodsValue - discountAmount;
        $('#subTotal').val(subTotal.toFixed(2));
        
        let packingAmount = subTotal * ((parseFloat($('#packing_percent').val()) || 0) / 100);
        $('#packingAmtDisplay').text(packingAmount.toFixed(2));
        
        let mahamaiAmount = subTotal * ((parseFloat($('#mahamai_percent').val()) || 0) / 100);
        $('#mahamaiAmtDisplay').text(mahamaiAmount.toFixed(2));
        
        let insuranceAmount = subTotal * ((parseFloat($('#insurance_percent').val()) || 0) / 100);
        $('#insuranceAmtDisplay').text(insuranceAmount.toFixed(2));
        
        let commissionAmount = subTotal * ((parseFloat($('#commission_percent').val()) || 0) / 100);
        $('#commissionAmtDisplay').text(commissionAmount.toFixed(2));
        
        let taxableAmount = subTotal + packingAmount + mahamaiAmount + insuranceAmount + commissionAmount;
        $('#taxableAmount').val(taxableAmount.toFixed(2));
        
        let sgstAmount = taxableAmount * ((parseFloat($('#sgst_percent').val()) || 0) / 100);
        $('#sgstAmtDisplay').text(sgstAmount.toFixed(2));
        
        let cgstAmount = taxableAmount * ((parseFloat($('#cgst_percent').val()) || 0) / 100);
        $('#cgstAmtDisplay').text(cgstAmount.toFixed(2));
        
        let igstAmount = taxableAmount * ((parseFloat($('#igst_percent').val()) || 0) / 100);
        $('#igstAmtDisplay').text(igstAmount.toFixed(2));
        
        let netAmount = taxableAmount + sgstAmount + cgstAmount + igstAmount;
        $('#netAmount').val(Math.round(netAmount).toFixed(2));
    }

    // Delegated event handler for the discount eligibility checkbox (already correct)
    $(document).on('change', '.discount-eligible', function() {
        $(this).siblings('.discount-eligible-hidden').val(this.checked ? 'Yes' : 'No');
    });

    $(document).on('input change', '.calc-trigger', calculateQuotation);
    
    $('#addRow').click(function() {
        let newRow = $('#productsTable tbody tr:first').clone();
        newRow.find('.product-select')
            .val('')
            .removeClass('select2-hidden-accessible')
            .removeAttr('data-select2-id')
            .next('.select2-container').remove();
            
        newRow.find('input').val('');
        newRow.find('input[name="amount[]"]').val('0.00');
        newRow.find('input[name="per[]"]').val('1 CASE');
        newRow.find('.discount-eligible-hidden').val('Yes');
        newRow.find('.discount-eligible').prop('checked', true);
        
        // Reset options to strip out initialized Select2 data-attributes
        const productSelect = newRow.find('.product-select')[0];
        productSelect.innerHTML = '<option value="">Select Product</option><?php 
            foreach($products as $product) { 
                echo '<option value="'.$product['id'].'">'.
                     addslashes(htmlspecialchars($product['name'], ENT_QUOTES)).'</option>';
            } 
        ?>';
        
        $('#productsTable tbody').append(newRow);
        
        newRow.find('.product-select').select2({
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
    });

    $(document).on('click', '.remove-row', function() {
        if ($('#productsTable tbody tr').length > 1) {
            $(this).closest('tr').remove();
            calculateQuotation();
        } else {
            Swal.fire("Warning", "At least one row is required", "warning");
        }
    });
    
    calculateQuotation(); // Initial Calculation
});
</script>