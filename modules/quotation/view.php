<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";

$database = new Database();
$db = $database->getConnection();

if (isset($_GET['delete_id'])) {
    $del_id = $_GET['delete_id'];
    $stmt = $db->prepare("DELETE FROM ff_sch.quotation WHERE id = :id");
    $stmt->bindParam(':id', $del_id);
    if ($stmt->execute()) {
        $_SESSION['notification'] = ['type' => 'success', 'message' => 'Quotation deleted successfully.'];
    } else {
        $_SESSION['notification'] = ['type' => 'error', 'message' => 'Failed to delete quotation.'];
    }
    header("Location: view.php");
    exit;
}

$notification = null;
if (isset($_SESSION['notification'])) {
    $notification = $_SESSION['notification'];
    unset($_SESSION['notification']);
}

$is_detail_view = isset($_GET['id']) && !empty($_GET['id']);

if ($is_detail_view) {
    $quotation_id = $_GET['id'];
    $stmt_quotation = $db->prepare("SELECT e.*, p.name as party_name, pc.name as price_code_name 
                                   FROM ff_sch.quotation e 
                                   LEFT JOIN ff_sch.parties p ON e.party_id = p.id 
                                   LEFT JOIN ff_sch.price_codes pc ON e.price_code_id = pc.id 
                                   WHERE e.id = :quotation_id");
    $stmt_quotation->bindParam(':quotation_id', $quotation_id, PDO::PARAM_INT);
    $stmt_quotation->execute();
    $quotation = $stmt_quotation->fetch(PDO::FETCH_ASSOC);

    if (!$quotation) {
        echo "<script>alert('Quotation not found!'); window.location.href='view.php';</script>";
        exit;
    }

    $stmt_items = $db->prepare("SELECT ei.*, p.name as product_name 
                                FROM ff_sch.quotation_items ei 
                                LEFT JOIN ff_sch.products p ON ei.product_id = p.id 
                                WHERE ei.quotation_id = :quotation_id ORDER BY ei.id ASC");
    $stmt_items->bindParam(':quotation_id', $quotation_id, PDO::PARAM_INT);
    $stmt_items->execute();
    $items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);
} else {
    $quotations = $db->query("SELECT e.*, p.name as party_name 
                             FROM ff_sch.quotation e 
                             LEFT JOIN ff_sch.parties p ON e.party_id = p.id 
                             ORDER BY e.id DESC")->fetchAll(PDO::FETCH_ASSOC);
}
?>
<?php include "../../includes/header.php"; ?>
<?php include "../../includes/sidebar.php"; ?>
<div class="container-fluid">
    <?php if ($is_detail_view): ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0"><i class="fa fa-eye"></i> View Quotation #<?= htmlspecialchars($quotation['quotation_no']) ?></h2>
            <div>
                <button type="button" onclick="showPrintOptions('<?= $quotation_id ?>')" class="btn btn-success"><i class="fa fa-print"></i> Print</button>
                <a href="edit.php?id=<?= $quotation_id ?>" class="btn btn-primary"><i class="fa fa-edit"></i> Edit</a>
                <a href="view.php" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back to List</a>
            </div>
        </div>
        
        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header"><h5 class="card-title">Quotation Details</h5></div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr><th width="35%">Quotation No:</th><td><?= htmlspecialchars($quotation['quotation_no']) ?></td></tr>
                            <tr><th>Quotation Date:</th><td><?= date('d-m-Y', strtotime($quotation['quotation_date'])) ?></td></tr>
                            <tr><th>Party Name:</th><td><?= htmlspecialchars($quotation['party_name']) ?></td></tr>
                            <tr><th>Price Code:</th><td><?= htmlspecialchars($quotation['price_code_name'] ?? '') ?></td></tr>
                            <tr><th>Agent Name:</th><td><?= htmlspecialchars($quotation['agent_name']) ?></td></tr>
                            <tr><th>Dispatch From:</th><td><?= htmlspecialchars($quotation['dispatch_from']) ?></td></tr>
                            <tr><th>Dispatch To:</th><td><?= htmlspecialchars($quotation['dispatch_through']) ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header"><h5 class="card-title">Additional Details</h5></div>
                    <div class="card-body">
                        <table class="table table-sm table-borderless">
                            <tr><th width="35%">Party Place:</th><td><?= htmlspecialchars($quotation['p_place']) ?></td></tr>
                            <tr><th>Party State:</th><td><?= htmlspecialchars($quotation['p_state']) ?></td></tr>
                            <tr><th>Party GST:</th><td><?= htmlspecialchars($quotation['p_gst']) ?></td></tr>
                            <tr><th>E-Way Bill No:</th><td><?= htmlspecialchars($quotation['eway_bill_no']) ?></td></tr>
                            <tr><th>Vehicle No:</th><td><?= htmlspecialchars($quotation['vehicle_no']) ?></td></tr>
                            <tr><th>Transport Name:</th><td><?= htmlspecialchars($quotation['transport_name']) ?></td></tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
                    
        <div class="card mb-4">
            <div class="card-header"><h5 class="card-title">Products</h5></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead class="thead-light">
                            <tr>
                                <th>Product</th>
                                <th>Case</th>
                                <th>Counts</th>
                                <th>Qty</th>
                                <th>Rate</th>
                                <th>Per</th>
                                <th>Discount Eligible</th>
                                <th>Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($items as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['product_name']) ?></td>
                                <td><?= htmlspecialchars($item['cases']) ?></td>
                                <td><?= htmlspecialchars($item['counts']) ?></td>
                                <td><?= htmlspecialchars($item['qty']) ?></td>
                                <td><?= number_format($item['rate'], 2) ?></td>
                                <td><?= htmlspecialchars($item['per']) ?></td>
                                <td><?= htmlspecialchars($item['discount_eligible']) ?></td>
                                <td><?= number_format($item['amount'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="row mt-4">
                    <div class="col-md-6 offset-md-6">
                        <h4 class="line-head">Summary</h4>
                        <div class="form-group row align-items-center mb-1">
                            <label class="col-sm-6 col-form-label text-right">Goods Value:</label>
                            <div class="col-sm-6"><span class="form-control-plaintext"><?= number_format($quotation['goods_value'], 2) ?></span></div>
                        </div>
                        <div class="form-group row align-items-center mb-1">
                            <label class="col-sm-6 col-form-label text-right">Discount (<?= (float)$quotation['discount_percent'] ?>%):</label>
                            <div class="col-sm-6"><span class="form-control-plaintext">- <?= number_format($quotation['goods_value'] * ($quotation['discount_percent']/100), 2) ?></span></div>
                        </div>
                        <div class="form-group row align-items-center mb-1">
                            <label class="col-sm-6 col-form-label text-right">Sub Total:</label>
                            <div class="col-sm-6"><span class="form-control-plaintext"><?= number_format($quotation['sub_total'], 2) ?></span></div>
                        </div>
                        <div class="form-group row align-items-center mb-1">
                            <label class="col-sm-6 col-form-label text-right">Packing Charges (<?= (float)$quotation['packing_percent'] ?>%):</label>
                            <div class="col-sm-6"><span class="form-control-plaintext"><?= number_format($quotation['sub_total'] * ($quotation['packing_percent']/100), 2) ?></span></div>
                        </div>
                        <div class="form-group row align-items-center mb-1">
                            <label class="col-sm-6 col-form-label text-right">Mahamai (<?= (float)$quotation['mahamai_percent'] ?>%):</label>
                            <div class="col-sm-6"><span class="form-control-plaintext"><?= number_format($quotation['sub_total'] * ($quotation['mahamai_percent']/100), 2) ?></span></div>
                        </div>
                        <div class="form-group row align-items-center mb-1">
                            <label class="col-sm-6 col-form-label text-right">Insurance (<?= (float)$quotation['insurance_percent'] ?>%):</label>
                            <div class="col-sm-6"><span class="form-control-plaintext"><?= number_format($quotation['sub_total'] * ($quotation['insurance_percent']/100), 2) ?></span></div>
                        </div>
                        <div class="form-group row align-items-center mb-1">
                            <label class="col-sm-6 col-form-label text-right">Commission (<?= (float)$quotation['commission_percent'] ?>%):</label>
                            <div class="col-sm-6"><span class="form-control-plaintext"><?= number_format($quotation['sub_total'] * ($quotation['commission_percent']/100), 2) ?></span></div>
                        </div>
                        <div class="form-group row align-items-center mb-1">
                            <label class="col-sm-6 col-form-label text-right">Taxable Amount:</label>
                            <div class="col-sm-6"><span class="form-control-plaintext"><?= number_format($quotation['taxable_amount'], 2) ?></span></div>
                        </div>
                        <div class="form-group row align-items-center mb-1">
                            <label class="col-sm-6 col-form-label text-right">SGST (<?= (float)$quotation['sgst_percent'] ?>%):</label>
                            <div class="col-sm-6"><span class="form-control-plaintext"><?= number_format($quotation['taxable_amount'] * ($quotation['sgst_percent']/100), 2) ?></span></div>
                        </div>
                        <div class="form-group row align-items-center mb-1">
                            <label class="col-sm-6 col-form-label text-right">CGST (<?= (float)$quotation['cgst_percent'] ?>%):</label>
                            <div class="col-sm-6"><span class="form-control-plaintext"><?= number_format($quotation['taxable_amount'] * ($quotation['cgst_percent']/100), 2) ?></span></div>
                        </div>
                        <div class="form-group row align-items-center mb-1">
                            <label class="col-sm-6 col-form-label text-right">IGST (<?= (float)$quotation['igst_percent'] ?>%):</label>
                            <div class="col-sm-6"><span class="form-control-plaintext"><?= number_format($quotation['taxable_amount'] * ($quotation['igst_percent']/100), 2) ?></span></div>
                        </div>
                        <div class="form-group row align-items-center border-top pt-2 mt-2">
                            <label class="col-sm-6 col-form-label text-right font-weight-bold text-primary">Net Amount:</label>
                            <div class="col-sm-6"><span class="form-control-plaintext font-weight-bold text-primary"><?= number_format($quotation['net_amount'], 2) ?></span></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0"><i class="fa fa-list"></i> Quotations</h2>
            <a href="add.php" class="btn btn-primary"><i class="fa fa-plus"></i> New Quotation</a>
        </div>
        
        <div class="card mb-4">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped" id="quotationListTable">
                        <thead>
                            <tr>
                                <th>Quotation No</th>
                                <th>Date</th>
                                <th>Party</th>
                                <th>Net Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($quotations as $quot): ?>
                            <tr>
                                <td><?= htmlspecialchars($quot['quotation_no']) ?></td>
                                <td><?= date('d-m-Y', strtotime($quot['quotation_date'])) ?></td>
                                <td><?= htmlspecialchars($quot['party_name']) ?></td>
                                <td><?= number_format($quot['net_amount'], 2) ?></td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <!-- <a href="view.php?id=<?= $quot['id'] ?>" class="btn btn-sm btn-info" title="View"><i class="fa fa-eye"></i></a> -->
                                        <a href="edit.php?id=<?= $quot['id'] ?>" class="btn btn-sm btn-warning me-2" title="Edit"><i class="fa fa-edit"></i></a>
                                        <button type="button" onclick="showPrintOptions('<?= $quot['id'] ?>')" class="btn btn-sm btn-info me-2" title="Print"><i class="fa fa-print"></i></button>
                                        <button type="button" onclick="confirmDelete('You will not be able to recover this quotation!', 'modules/quotation/view.php?delete_id=<?= $quot['id'] ?>')" class="btn btn-sm btn-danger me-2" title="Delete"><i class="fa fa-trash"></i></button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include "../../includes/footer.php"; ?>
<?php if ($notification): ?>
<script>
$(document).ready(function() {
    // Ensure notification is an array and has the expected keys before using
    <?php if (is_array($notification) && isset($notification['message']) && isset($notification['type'])): ?>
        showNotification('<?php echo addslashes($notification['message']); ?>', '<?php echo addslashes($notification['type']); ?>');
    <?php endif; ?>
});
</script>
<?php endif; ?>
<?php if (!$is_detail_view): ?>
<script>
$(document).ready(function() { 
    $('#quotationListTable').DataTable({
        "order": [[ 0, "desc" ]],
        columnDefs: [
            {
                targets: [4],
                orderable: false,
                className: "text-center",
                searchable: false // Important: Make action columns not searchable
            }
        ]
    });
});

function showPrintOptions(quotationId) {
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
        focusConfirm: true,
        preConfirm: () => {
            return document.querySelector('input[name="printCopy"]:checked').value;
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            window.open(`quotation_print.php?id=${quotationId}&copy=${result.value}`, '_blank');
        }
    });
}
</script>
<?php endif; ?>