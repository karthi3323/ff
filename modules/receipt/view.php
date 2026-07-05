<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";

$database = new Database();
$db = $database->getConnection();

if (isset($_GET['delete_id'])) {
    $del_id = $_GET['delete_id'];
    $stmt = $db->prepare("DELETE FROM ff_sch.rcpt_hdr WHERE id = :id");
    $stmt->bindParam(':id', $del_id);
    if ($stmt->execute()) {
        $_SESSION['notification'] = ['type' => 'success', 'message' => 'Receipt deleted successfully.'];
    } else {
        $_SESSION['notification'] = ['type' => 'error', 'message' => 'Failed to delete receipt.'];
    }
    header("Location: view.php");
    exit;
}

$notification = null;
if (isset($_SESSION['notification'])) {
    $notification = $_SESSION['notification'];
    unset($_SESSION['notification']);
}

$receipts = $db->query("SELECT *, 
                            CASE 
                                WHEN receipt_against = 'invoice' THEN 'Invoice' 
                                ELSE 'Estimate' 
                            END as against_type 
                        FROM ff_sch.rcpt_hdr ORDER BY receipt_date DESC, receipt_no DESC")->fetchAll(PDO::FETCH_ASSOC);

?>
<?php include "../../includes/header.php"; ?>
<?php include "../../includes/sidebar.php"; ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><i class="fa fa-list"></i> Receipts</h2>
        <div class="me-4 ">
            <a href="add.php" class="btn btn-primary"><i class="fa fa-plus"></i> New Receipt</a>
            <!-- <button type="button" class="btn btn-info" onclick="showPrintOptions()"><i class="fa fa-print"></i> Print Report</button> -->
        </div>
    </div>
    
    <div class="card mb-4">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped" id="receiptListTable">
                    <thead>
                        <tr>
                            <th>Receipt No</th>
                            <th>Date</th>
                            <th>Agent Name</th>
                            <th>Against</th>
                            <th class="text-end">Total Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($receipts as $receipt): ?>
                        <tr>
                            <td><?= htmlspecialchars($receipt['receipt_no']) ?></td>
                            <td><?= date('d-m-Y', strtotime($receipt['receipt_date'])) ?></td>
                            <td><?= htmlspecialchars($receipt['agent_name']) ?></td>
                            <td><span class="badge bg-secondary"><?= htmlspecialchars($receipt['against_type']) ?></span></td>
                            <td class="text-end"><?= number_format($receipt['total_receipt_amount'], 2) ?></td>
                            <td>
                                <a href="edit.php?id=<?= $receipt['id'] ?>" class="btn btn-sm btn-warning" title="Edit"><i class="fa fa-edit"></i></a>
                                <a href="receipt_print.php?id=<?= $receipt['id'] ?>" target="_blank" class="btn btn-sm btn-info" title="Print"><i class="fa fa-print"></i></a>
                                <button type="button" onclick="confirmDelete('You will not be able to recover this receipt! This action cannot be undone.', 'modules/receipt/view.php?delete_id=<?= $receipt['id'] ?>')" class="btn btn-sm btn-danger" title="Delete"><i class="fa fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include "../../includes/footer.php"; ?>
<?php if ($notification): ?>
<script>
$(document).ready(function() {
    showNotification('<?php echo addslashes($notification['message']); ?>', '<?php echo $notification['type']; ?>');
});
</script>
<?php endif; ?>
<script>
$(document).ready(function() {
    $('#receiptListTable').DataTable({
        "order": [[0, "desc"]],
        columnDefs: [
            {
                targets: [5],
                orderable: false,
                className: "text-center",
                searchable: false // Important: Make action columns not searchable
            }
        ]
    });
});

function showPrintOptions() {
    const today = new Date().toISOString().split('T')[0];
    const htmlContent = `
        <div class="row">
            <div class="col-md-6 mb-3">
                <label for="swal-from-date" class="form-label">From Date</label>
                <input type="date" id="swal-from-date" class="form-control" value="${today}">
            </div>
            <div class="col-md-6 mb-3">
                <label for="swal-to-date" class="form-label">To Date</label>
                <input type="date" id="swal-to-date" class="form-control" value="${today}">
            </div>
        </div>
        <hr>
        <h5 class="mb-3">Report Type</h5>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="reportType" id="agentWise" value="agent" checked>
            <label class="form-check-label" for="agentWise">
                Agent Wise Report
            </label>
        </div>
        <div class="form-check">
            <input class="form-check-input" type="radio" name="reportType" id="partyWise" value="party">
            <label class="form-check-label" for="partyWise">
                Party Wise Report
            </label>
        </div>
    `;

    Swal.fire({
        title: 'Receipt Report Options',
        html: htmlContent,
        showCancelButton: true,
        confirmButtonText: '<i class="fa fa-print me-1"></i> Generate Report',
        focusConfirm: true,
        preConfirm: () => {
            return {
                type: document.querySelector('input[name="reportType"]:checked').value,
                from: document.getElementById('swal-from-date').value,
                to: document.getElementById('swal-to-date').value
            }
        }
    }).then((result) => {
        if (result.isConfirmed && result.value) {
            const { type, from, to } = result.value;
            window.open(`receipt_report.php?type=${type}&from=${from}&to=${to}`, '_blank');
        }
    });
}
</script>