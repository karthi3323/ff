<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";

$database = new Database();
$db = $database->getConnection();

// Get distinct agent names from estimates
$agents_query = "(SELECT DISTINCT agent_name FROM ff_sch.estimate WHERE agent_name IS NOT NULL AND agent_name <> '')
                 UNION
                 (SELECT DISTINCT agent_name FROM ff_sch.invoices WHERE agent_name IS NOT NULL AND agent_name <> '')
                 ORDER BY agent_name";
$agents = $db->query($agents_query)->fetchAll(PDO::FETCH_ASSOC);

$fiscal_year = $db->query("SELECT * FROM ff_sch.fiscal_years WHERE is_active = true LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$current_fiscal_year_id = $fiscal_year ? $fiscal_year['id'] : 1;

// Generate next receipt number
$rcpt_query = $db->prepare("SELECT max(receipt_no) as count FROM ff_sch.rcpt_hdr where fiscal_year_id = :fiscal_year_id");
$rcpt_query->bindParam(':fiscal_year_id', $current_fiscal_year_id);
$rcpt_query->execute();
$rcpt_data = $rcpt_query->fetch(PDO::FETCH_ASSOC);
$max_id = ($rcpt_data['count'] ?? 0) + 1;
// NOTE: This method of generating a receipt number can have a race condition if two users
// add a receipt at the same time. Consider adding a UNIQUE constraint on (receipt_no, fiscal_year_id)
// in the database and handling the potential PDOException on insert.
$receipt_no = $max_id;

if($_POST) {
    // Check for duplicate receipt number
    $check_sql = "SELECT 1 FROM ff_sch.rcpt_hdr WHERE receipt_no = :receipt_no AND fiscal_year_id = :fiscal_year_id LIMIT 1";
    $check_stmt = $db->prepare($check_sql);
    $check_stmt->bindParam(':receipt_no', $_POST['receipt_no']);
    $check_stmt->bindParam(':fiscal_year_id', $current_fiscal_year_id);
    $check_stmt->execute();
    // print_r($_POST); exit;
    if ($check_stmt->fetch()) {
        echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Duplicate Receipt!',
                        text: 'Receipt number already exists for this fiscal year.',
                        icon: 'warning',
                        focusConfirm: true
                    });
                });
            </script>";
        $receipt_no = $_POST['receipt_no'];
    } else {
        try {
            $db->beginTransaction();
            
            $total_receipt_amount = 0;
            if (isset($_POST['receipt_amount'])) {
                foreach ($_POST['receipt_amount'] as $amount) {
                    $total_receipt_amount += (float)$amount;
                }
            }

            // Insert into rcpt_hdr
            $receipt_against = $_POST['receipt_against'] ?? 'estimate';
            $hdr_query = "INSERT INTO ff_sch.rcpt_hdr (
                receipt_no, receipt_date, agent_name, total_receipt_amount, fiscal_year_id, created_by, receipt_against
            ) VALUES (
                :receipt_no, :receipt_date, :agent_name, :total_receipt_amount, :fiscal_year_id, :created_by, :receipt_against
            )";
            
            $hdr_stmt = $db->prepare($hdr_query);
            $hdr_stmt->bindParam(':receipt_no', $_POST['receipt_no']);
            $hdr_stmt->bindParam(':receipt_date', $_POST['receipt_date']);
            $hdr_stmt->bindParam(':agent_name', $_POST['agent_name']);
            $hdr_stmt->bindParam(':receipt_against', $receipt_against);
            $hdr_stmt->bindParam(':total_receipt_amount', $total_receipt_amount);
            $hdr_stmt->bindValue(':fiscal_year_id', $current_fiscal_year_id);
            $hdr_stmt->bindParam(':created_by', $_SESSION['user_id']);
            
            $hdr_stmt->execute();
            $rcpt_hdr_id = $db->lastInsertId();

            // Insert into rcpt_dtl
            $dtl_query = "INSERT INTO ff_sch.rcpt_dtl (
                rcpt_hdr_id, estimate_id, invoice_id, receipt_amount, pending_amount_after_receipt, 
                payment_type, narration
            ) VALUES (
                :rcpt_hdr_id, :estimate_id, :invoice_id, :receipt_amount, :pending_amount_after_receipt, 
                :payment_type, :narration
            )";
            $dtl_stmt = $db->prepare($dtl_query);

            $update_table = ($receipt_against == 'estimate') ? 'estimate' : 'invoices';
            $update_stmt = $db->prepare("UPDATE ff_sch.{$update_table} SET pending_amount = :pending_amount WHERE id = :doc_id");
            
            if (isset($_POST['doc_id'])) {
                foreach($_POST['doc_id'] as $key => $doc_id) {
                    $receipt_amount = (float)$_POST['receipt_amount'][$key];
                    $pending_amount_after = (float)$_POST['pending_amount_after'][$key];

                    if(!empty($doc_id) && $receipt_amount > 0) {
                        $dtl_params = [
                            ':rcpt_hdr_id' => $rcpt_hdr_id,
                            ':estimate_id' => ($receipt_against == 'estimate') ? $doc_id : null,
                            ':invoice_id' => ($receipt_against == 'invoice') ? $doc_id : null,
                            ':receipt_amount' => $receipt_amount,
                            ':pending_amount_after_receipt' => $pending_amount_after,
                            ':payment_type' => $_POST['payment_type_row'][$key],
                            ':narration' => $_POST['narration_row'][$key]
                        ];
                        $dtl_stmt->execute($dtl_params);
                        
                        // Update pending amount in estimate/invoice table
                        $update_stmt->execute([
                            ':pending_amount' => $pending_amount_after,
                            ':doc_id' => $doc_id
                        ]);
                    }
                }
            }
            
            $db->commit();
            
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Success!',
                        text: 'Receipt created successfully!',
                        icon: 'success',
                        focusConfirm: true
                    }).then(() => { window.location.href = 'view.php'; });
                });
                </script>";
            
        } catch(Exception $e) {
            $db->rollBack();
            // In a production environment, it's better to log the detailed error and show a generic message.
            // error_log("Receipt creation failed: " . $e->getMessage());
            // $error_message = "An unexpected error occurred. Please try again.";
            $error_message = addslashes($e->getMessage()); // For development
            echo "<script>
                document.addEventListener('DOMContentLoaded', function() {
                    Swal.fire({
                        title: 'Error!',
                        text: 'Failed to create receipt: {$error_message}',
                        icon: 'error',
                        focusConfirm: true,
                    });
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

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fa fa-file-text"></i> Add Receipt</h2>
        <a href="view.php" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back to List</a>
    </div>
    
    <form id="addReceiptForm" method="POST" action="">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title">Receipt Details</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Receipt No</label>
                        <input type="text" class="form-control" id="receipt_no" name="receipt_no" value="<?php echo $receipt_no; ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Receipt Date</label>
                        <input type="date" class="form-control" name="receipt_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <!-- <label class="form-label">Receipt Against</label>
                        <div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="receipt_against" id="against_estimate" value="estimate" checked>
                                <label class="form-check-label" for="against_estimate">Estimate</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="receipt_against" id="against_invoice" value="invoice">
                                <label class="form-check-label" for="against_invoice">Invoice</label>
                            </div>
                        </div> -->
                        <!-- <label class="form-label">Receipt Against</label>
                        <select class="form-select" name="receipt_against" disabled>
                            <option value="estimate">Estimate</option>
                            <option value="invoice">Invoice</option>
                        </select> -->
                        <label class="form-label fw-semibold mb-2">Receipt Against</label>

                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="receipt_against"
                                id="against_estimate" value="estimate" checked>
                            <label class="btn btn-outline-primary" for="against_estimate">
                                <i class="fas fa-file-alt me-1"></i> Estimate
                            </label>

                            <input type="radio" class="btn-check" name="receipt_against"
                                id="against_invoice" value="invoice">
                            <label class="btn btn-outline-success" for="against_invoice">
                                <i class="fas fa-file-invoice me-1"></i> Invoice
                            </label>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3 align-self-end">
                        <label class="form-label">Agent Name</label>
                        <select class="form-select agent-select" name="agent_name" id="agent_name" required>
                            <option value="">Select Agent</option>
                            <?php foreach($agents as $agent): ?>
                                <option value="<?php echo htmlspecialchars($agent['agent_name']); ?>">
                                    <?php echo htmlspecialchars($agent['agent_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>
                    
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title">Bills from Agent</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" id="estimatesTable">
                        <thead>
                            <tr>
                                <th>S.No</th>
                                <th>Party Name</th>
                                <th class="doc-header">Bill No / Date</th>
                                <th class="text-end">Net Amount</th>
                                <th class="text-end">Pending Amount</th>
                                <th>Payment Type</th>
                                <th>Narration</th>
                                <th class="text-end" width="15%">Receipt Amount</th>
                                <th class="text-end">Pending After Receipt</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Rows will be populated by JavaScript -->
                            <tr>
                                <td colspan="9" class="text-center">Select an agent to see documents.</td>
                            </tr>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="7" class="text-end fw-bold">Total Receipt Amount:</td>
                                <td class="text-end fw-bold" id="totalReceiptAmount">0.00</td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                <div class="text-end mt-3 mb-4">
                    <button class="btn btn-primary btn-lg" type="submit"><i class="fa fa-fw fa-lg fa-check-circle"></i> Save Receipt</button>
                </div>
            </div>
        </div>
    </form>
</div>

<?php include "../../includes/footer.php"; ?>
<script src="<?php echo ASSETS_URL; ?>/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    
    // Initialize Select2 for Agent dropdown
    $('.agent-select').select2({ 
        theme: 'bootstrap4', 
        placeholder: "Select Agent", 
        allowClear: true, 
        width: '100%' 
    }).on('select2:open', function() {
        setTimeout(() => {
            document.querySelector('.select2-container--open .select2-search__field').focus();
        }, 100);
    });

    // Initialize Select2 for Payment Type dropdown
    $('.payment-type-select').select2({ 
        theme: 'bootstrap4', 
        placeholder: "Select Payment Type", 
        allowClear: true, 
        width: '100%' 
    }).on('select2:open', function() {
        setTimeout(() => {
            document.querySelector('.select2-container--open .select2-search__field').focus();
        }, 100);
    });

    function loadDocuments() {
        const agentName = $(this).val();
        const docType = $('input[name="receipt_against"]:checked').val();
        const tableBody = $('#estimatesTable tbody');
        
        if (!agentName) {
            tableBody.html('<tr><td colspan="9" class="text-center">Select an agent to see documents.</td></tr>');
            calculateTotal();
            return;
        }

        // Update table header
        const docHeader = docType === 'estimate' ? 'Bill No / Date' : 'Invoice No / Date';
        $('#estimatesTable .doc-header').text(docHeader);

        $.ajax({
            url: '<?php echo BASE_URL; ?>/includes/ajax_actions.php',
            type: 'POST',
            dataType: 'json',
            data: { 
                action: 'getDocumentsForReceipt', 
                agent_name: agentName,
                doc_type: docType
            },
            beforeSend: function() {
                tableBody.html('<tr><td colspan="9" class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading...</td></tr>');
            },
            success: function(response) {
                tableBody.empty();
                if (response.success && response.data && response.data.length > 0) {
                    let serial = 1;
                    response.data.forEach(doc => {
                        const docDate = new Date(doc.doc_date).toLocaleDateString('en-GB');
                        const pendingAmount = parseFloat(doc.pending_amount) || 0;
                        const row = `
                            <tr>
                                <td>${serial++}</td>
                                <td>${escapeHtml(doc.party_name)}</td>
                                <td>
                                    ${escapeHtml(doc.doc_no)} - ${docDate}
                                    <input type="hidden" name="doc_id[]" value="${doc.id}">
                                </td>
                                <td class="text-end">${parseFloat(doc.net_amount).toFixed(2)}</td>
                                <td class="text-end pending-amount">${pendingAmount.toFixed(2)}</td>
                                <td>
                                    <select class="form-select form-select-sm payment-type-row" name="payment_type_row[]">
                                        <option value="">Select</option>
                                        <option value="Cash">Cash</option>
                                        <option value="G-Pay">G-Pay</option>
                                        <option value="Bank Transfer">Bank Transfer</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm narration-row" 
                                           name="narration_row[]" placeholder="Enter narration">
                                </td>
                                <td>
                                    <input type="text" onkeypress="return keyPressNumber(event,this);" 
                                           class="form-control form-control-sm text-end receipt-amount" 
                                           name="receipt_amount[]" placeholder="0.00" step="0.01"
                                           data-pending="${pendingAmount}">
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm text-end pending-after" 
                                           name="pending_amount_after[]" readonly value="${pendingAmount.toFixed(2)}">
                                </td>
                            </tr>
                        `;
                        tableBody.append(row);
                    });
                } else {
                    tableBody.html(`<tr><td colspan="9" class="text-center">No pending ${docType}s found for this agent.</td></tr>`);
                }
                calculateTotal();
            },
            error: function() {
                tableBody.html('<tr><td colspan="9" class="text-center text-danger">Error loading documents.</td></tr>');
                calculateTotal();
            }
        });
    }

    $('#agent_name').on('change', loadDocuments);

    $('input[name="receipt_against"]').on('change', function() {
        $('#agent_name').val(null).trigger('change');
    });

    // Calculate pending amount after receipt
    $(document).on('input', '.receipt-amount', function() {
        const row = $(this).closest('tr');
        const pendingAmount = parseFloat($(this).data('pending')) || 0;
        const receiptAmount = parseFloat($(this).val()) || 0;
        const pendingAfter = Math.max(0, pendingAmount - receiptAmount);
        row.find('.pending-after').val(pendingAfter.toFixed(2));
        calculateTotal();
    });

    function calculateTotal() {
        let total = 0;
        $('.receipt-amount').each(function() {
            total += parseFloat($(this).val()) || 0;
        });
        $('#totalReceiptAmount').text(total.toFixed(2));
    }

    function escapeHtml(text) {
        if (text === null || text === undefined) {
            return '';
        }
        var map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.toString().replace(/[&<>"']/g, function(m) { return map[m]; });
    }
});
</script>