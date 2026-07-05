<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";

$database = new Database();
$db = $database->getConnection();

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: view.php");
    exit;
}

$rcpt_hdr_id = $_GET['id'];

// Fetch existing receipt header
$stmt = $db->prepare("SELECT * FROM ff_sch.rcpt_hdr WHERE id = :id");
$stmt->bindParam(':id', $rcpt_hdr_id);
$stmt->execute();
$receipt = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$receipt) {
    echo "<script>alert('Receipt not found!'); window.location.href='view.php';</script>";
    exit;
}
$receipt_against = $receipt['receipt_against'] ?? 'estimate';

// Fetch existing receipt details
$dtl_stmt = $db->prepare("SELECT * FROM ff_sch.rcpt_dtl WHERE rcpt_hdr_id = :id");
$dtl_stmt->bindParam(':id', $rcpt_hdr_id);
$dtl_stmt->execute();
$receipt_details_raw = $dtl_stmt->fetchAll(PDO::FETCH_ASSOC);
$receipt_details = [];
$receipt_payment_types = [];
$receipt_narrations = [];
foreach ($receipt_details_raw as $detail) {
    $doc_id = $receipt_against == 'estimate' ? $detail['estimate_id'] : $detail['invoice_id'];
    if ($doc_id) {
        $receipt_details[$doc_id] = $detail['receipt_amount'];
        $receipt_payment_types[$doc_id] = $detail['payment_type'] ?? '';
        $receipt_narrations[$doc_id] = $detail['narration'] ?? '';
    }
}

// Get dropdown data
$agents_query = "(SELECT DISTINCT agent_name FROM ff_sch.estimate WHERE agent_name IS NOT NULL AND agent_name <> '')
                 UNION
                 (SELECT DISTINCT agent_name FROM ff_sch.invoices WHERE agent_name IS NOT NULL AND agent_name <> '')
                 ORDER BY agent_name";
$agents = $db->query($agents_query)->fetchAll(PDO::FETCH_ASSOC);
$fiscal_year = $db->query("SELECT * FROM ff_sch.fiscal_years WHERE is_active = true LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$current_fiscal_year_id = $fiscal_year ? $fiscal_year['id'] : 1;

if($_POST) {
    try {
        $db->beginTransaction();

        // 1. Fetch all old receipt details to know which estimates to revert.
        $old_dtl_stmt = $db->prepare("SELECT estimate_id, invoice_id, receipt_amount FROM ff_sch.rcpt_dtl WHERE rcpt_hdr_id = :rcpt_hdr_id");
        $old_dtl_stmt->bindParam(':rcpt_hdr_id', $rcpt_hdr_id);
        $old_dtl_stmt->execute();
        $old_details = $old_dtl_stmt->fetchAll(PDO::FETCH_ASSOC);

        // 2. For each old estimate, add back the old receipt amount to its pending_amount to revert it to its pre-receipt state.
        if ($receipt_against == 'estimate') {
            $revert_stmt = $db->prepare("UPDATE ff_sch.estimate SET pending_amount = pending_amount + :receipt_amount WHERE id = :doc_id");
        } else {
            $revert_stmt = $db->prepare("UPDATE ff_sch.invoices SET pending_amount = pending_amount + :receipt_amount WHERE id = :doc_id");
        }

        foreach ($old_details as $detail) {
            $doc_id = $receipt_against == 'estimate' ? $detail['estimate_id'] : $detail['invoice_id'];
            if ($doc_id) {
                $revert_stmt->execute([':receipt_amount' => $detail['receipt_amount'], ':doc_id' => $doc_id]);
            }
        }

        // 3. Now that pending amounts are reverted, we can process the new submission.
        $total_receipt_amount = 0;
        if (isset($_POST['receipt_amount'])) {
            foreach ($_POST['receipt_amount'] as $amount) {
                $total_receipt_amount += (float)$amount;
            }
        }
        $stmt1 = $db->prepare("SELECT * FROM ff_sch.rcpt_hdr WHERE id = :id");
        $stmt1->bindParam(':id', $rcpt_hdr_id);
        $stmt1->execute();
        $receipt1 = $stmt1->fetch(PDO::FETCH_ASSOC);

        if($_POST['agent_name'] == '' || $_POST['agent_name'] == null) {
            $agent_name = $receipt1['agent_name'];
        } else {
            $agent_name = $_POST['agent_name'];
        }
        // Update rcpt_hdr
        $hdr_query = "UPDATE ff_sch.rcpt_hdr SET 
            receipt_date = :receipt_date, 
            agent_name = :agent_name, 
            total_receipt_amount = :total_receipt_amount
            WHERE id = :id";
        
        $hdr_stmt = $db->prepare($hdr_query);
        $hdr_stmt->execute([
            ':receipt_date' => $_POST['receipt_date'],
            ':agent_name' => $agent_name,
            ':total_receipt_amount' => $total_receipt_amount,
            ':id' => $rcpt_hdr_id
        ]);

        // Delete old details
        $del_stmt = $db->prepare("DELETE FROM ff_sch.rcpt_dtl WHERE rcpt_hdr_id = :id");
        $del_stmt->bindParam(':id', $rcpt_hdr_id);
        $del_stmt->execute();

        // Re-insert current details
        $dtl_query = "INSERT INTO ff_sch.rcpt_dtl (
            rcpt_hdr_id, estimate_id, invoice_id, receipt_amount, payment_type, narration, pending_amount_after_receipt
        ) VALUES (
            :rcpt_hdr_id, :estimate_id, :invoice_id, :receipt_amount, :payment_type, :narration, :pending_amount_after_receipt
        )";
        $dtl_stmt = $db->prepare($dtl_query);

        $update_table = ($receipt_against == 'estimate') ? 'estimate' : 'invoices';
        $update_doc_stmt = $db->prepare("UPDATE ff_sch.{$update_table} SET pending_amount = :pending_amount WHERE id = :doc_id");
        $get_pending_stmt = $db->prepare("SELECT pending_amount FROM ff_sch.{$update_table} WHERE id = :id");
        
        if (isset($_POST['doc_id'])) {
            foreach($_POST['doc_id'] as $key => $doc_id) {
                $receipt_amount = (float)$_POST['receipt_amount'][$key];

                if(!empty($doc_id) && $receipt_amount > 0) {
                    // Get the correct current pending amount (which we just reverted)
                    $get_pending_stmt->execute([':id' => $doc_id]);
                    $current_pending = (float)$get_pending_stmt->fetchColumn();

                    $pending_amount_after = $current_pending - $receipt_amount;
                    $payment_type = $_POST['payment_type_row'][$key] ?? null;
                    $narration = $_POST['narration_row'][$key] ?? null;

                    $dtl_stmt->execute([
                        ':rcpt_hdr_id' => $rcpt_hdr_id,
                        ':estimate_id' => ($receipt_against == 'estimate') ? $doc_id : null,
                        ':invoice_id' => ($receipt_against == 'invoice') ? $doc_id : null,
                        ':receipt_amount' => $receipt_amount,
                        ':payment_type' => $payment_type,
                        ':narration' => $narration,
                        ':pending_amount_after_receipt' => $pending_amount_after
                    ]);
                    
                    // Update pending amount in document table
                    $update_doc_stmt->execute([
                        ':pending_amount' => $pending_amount_after,
                        ':doc_id' => $doc_id
                    ]);
                }
            }
        }
        
        $db->commit();
        
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire('Success!', 'Receipt updated successfully!', 'success')
                    .then(() => { window.location.href = 'view.php'; });
            });
            </script>";
        
    } catch(Exception $e) {
        $db->rollBack();
        // In a production environment, it's better to log the detailed error and show a generic message.
        // error_log("Receipt update failed: " . $e->getMessage());
        // $error_message = "An unexpected error occurred. Please try again.";
        $error_message = addslashes($e->getMessage());
        echo "<script>
            document.addEventListener('DOMContentLoaded', function() {
                Swal.fire('Error!', 'Failed to update receipt: {$error_message}', 'error'); // For development
            });
            </script>";
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
        <h2 class="mb-0"><i class="fa fa-edit"></i> Edit Receipt</h2>
        <div>
            <a href="receipt_print.php?id=<?= $rcpt_hdr_id ?>" target="_blank" class="btn btn-info"><i class="fa fa-print"></i> Print</a>
            <a href="view.php" class="btn btn-secondary"><i class="fa fa-arrow-left"></i> Back to List</a>
        </div>
    </div>
    
    <form id="editReceiptForm" method="POST" action="">
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="card-title">Receipt Details</h5>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Receipt No</label>
                        <input type="text" class="form-control" name="receipt_no" value="<?= htmlspecialchars($receipt['receipt_no']) ?>" readonly>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Receipt Date</label>
                        <input type="date" class="form-control" name="receipt_date" value="<?= htmlspecialchars($receipt['receipt_date']) ?>" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label class="form-label">Receipt Against</label>
                        <select class="form-select" name="receipt_against" disabled>
                            <option value="estimate" <?= $receipt_against == 'estimate' ? 'selected' : '' ?>>
                                Estimate
                            </option>
                            <option value="invoice" <?= $receipt_against == 'invoice' ? 'selected' : '' ?>>
                                Invoice
                            </option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3 align-self-end">
                        <label class="form-label">Agent Name</label>
                        <select class="form-select agent-select" name="agent_name" id="agent_name" required disabled>
                            <option value="">Select Agent</option>
                            <?php foreach($agents as $agent): ?>
                                <option value="<?php echo htmlspecialchars($agent['agent_name']); ?>" <?= $agent['agent_name'] == $receipt['agent_name'] ? 'selected' : '' ?>>
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
                                <td colspan="9" class="text-center">Loading receipt details...</td>
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
                    <button class="btn btn-primary btn-lg" type="submit"><i class="fa fa-fw fa-lg fa-check-circle"></i> Update Receipt</button>
                </div>
            </div>
        </div>
    </form>
</div>

<?php include "../../includes/footer.php"; ?>
<script src="<?php echo ASSETS_URL; ?>/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    
    const existingReceiptDetails = <?= json_encode($receipt_details) ?>;
    const existingPaymentTypes = <?= json_encode($receipt_payment_types) ?>;
    const existingNarrations = <?= json_encode($receipt_narrations) ?>;
    const receiptAgainst = '<?= $receipt_against ?>';

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

    function loadEstimates(agentName) {
        const tableBody = $('#estimatesTable tbody');
        
        if (!agentName) {
            tableBody.html('<tr><td colspan="9" class="text-center">Agent not found.</td></tr>');
            calculateTotal();
            return;
        }

        // NOTE for backend: The 'getEstimatesByAgent' action for the edit page needs to be different.
        // It should calculate the "original" pending amount for each estimate before this receipt was applied.
        // The query should be something like:
        // SELECT e.*, rd.receipt_amount FROM estimate e LEFT JOIN rcpt_dtl rd ON e.id = rd.estimate_id AND rd.rcpt_hdr_id = :rcpt_hdr_id WHERE e.agent_name = :agent_name AND (e.pending_amount > 0 OR rd.id IS NOT NULL)
        // Then, in PHP, calculate: $estimate['original_pending_amount'] = $estimate['pending_amount'] + $estimate['receipt_amount'];
        // The JS below assumes the response contains `original_pending_amount`.

        // Update table header
        const docHeader = receiptAgainst === 'estimate' ? 'Bill No / Date' : 'Invoice No / Date';
        $('#estimatesTable .doc-header').text(docHeader);

        $.ajax({
            url: '<?php echo BASE_URL; ?>/includes/ajax_actions.php',
            type: 'POST',
            dataType: 'json',
            data: { 
                action: 'getDocumentsForReceipt', agent_name: agentName, rcpt_hdr_id: <?= $rcpt_hdr_id ?>, doc_type: receiptAgainst 
            },
            beforeSend: function() {
                tableBody.html('<tr><td colspan="9" class="text-center"><i class="fa fa-spinner fa-spin"></i> Loading...</td></tr>');
            },
            success: function(response) {
                tableBody.empty();
                if (response.success && response.data.length > 0) {
                    let serial = 1;
                    response.data.forEach(doc => {
                        const docDate = new Date(doc.doc_date).toLocaleDateString('en-GB');
                        const receiptAmount = existingReceiptDetails[doc.id] || '';
                        const paymentType = existingPaymentTypes[doc.id] || '';
                        const narration = existingNarrations[doc.id] || '';
                        
                        // Use the original pending amount (pending + this receipt's amount) for correct calculations.
                        // This requires the backend to provide `original_pending_amount`.
                        const pendingAmount = parseFloat(doc.original_pending_amount) || parseFloat(doc.pending_amount) || 0;
                        
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
                                        <option value="Cash" ${paymentType == 'Cash' ? 'selected' : ''}>Cash</option>
                                        <option value="G-Pay" ${paymentType == 'G-Pay' ? 'selected' : ''}>G-Pay</option>
                                        <option value="Bank Transfer" ${paymentType == 'Bank Transfer' ? 'selected' : ''}>Bank Transfer</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm narration-row" 
                                           name="narration_row[]" placeholder="Enter narration" value="${escapeHtml(narration)}">
                                </td>
                                <td>
                                    <input type="text" onkeypress="return keyPressNumber(event,this);" 
                                           class="form-control form-control-sm text-end receipt-amount" 
                                           name="receipt_amount[]" placeholder="0.00" step="0.01"
                                           data-pending="${pendingAmount}" value="${receiptAmount}">
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm text-end pending-after" 
                                           name="pending_amount_after[]" readonly 
                                           value="${(Math.max(0, pendingAmount - parseFloat(receiptAmount || 0))).toFixed(2)}">
                                </td>
                            </tr>
                        `;
                        tableBody.append(row);
                    });
                    
                    // Initialize Select2 for payment type rows
                    $('.payment-type-row').select2({
                        theme: 'bootstrap4',
                        placeholder: "Select",
                        allowClear: true,
                        width: '100%'
                    }).on('select2:open', function() {
                        setTimeout(() => {
                            document.querySelector('.select2-container--open .select2-search__field').focus();
                        }, 100);
                    });
                } else {
                    tableBody.html(`<tr><td colspan="9" class="text-center">No ${receiptAgainst}s found for this receipt.</td></tr>`);
                }
                calculateTotal();
            },
            error: function() {
                tableBody.html('<tr><td colspan="9" class="text-center text-danger">Error loading documents.</td></tr>');
                calculateTotal();
            }
        });
    }

    // Initial load for the selected agent
    if ($('#agent_name').val()) {
        loadEstimates($('#agent_name').val());
    }

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