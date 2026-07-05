<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";
require_once "../../includes/audit_helper.php";
$database = new Database();
$db = $database->getConnection();

// Get current fiscal year
$fiscal_year_query = $db->query("SELECT * FROM ff_sch.fiscal_years WHERE is_active = true LIMIT 1");
$fiscal_year = $fiscal_year_query->fetch(PDO::FETCH_ASSOC);
$current_fiscal_year_id = $fiscal_year ? $fiscal_year['id'] : 1;

// Handle delete
if(isset($_GET['delete_id']) && isset($_GET['year'])) {
    try {
        $db->beginTransaction();
        
        $invoice_id = $_GET['delete_id'];
        $fiscal_year_id = $_GET['year'];
        
        // Delete pro-forma invoice items first
        $items_query = "DELETE FROM ff_sch.proforma_invoice_items WHERE invoice_id = :invoice_id AND fiscal_year_id = :fiscal_year_id";
        $items_stmt = $db->prepare($items_query);
        $items_stmt->bindParam(':invoice_id', $invoice_id);
        $items_stmt->bindParam(':fiscal_year_id', $fiscal_year_id);
        $items_stmt->execute();
        
        // Delete pro-forma invoice
        $invoice_query = "DELETE FROM ff_sch.proforma_invoices WHERE id = :invoice_id AND fiscal_year_id = :fiscal_year_id";
        $invoice_stmt = $db->prepare($invoice_query);
        $invoice_stmt->bindParam(':invoice_id', $invoice_id);
        $invoice_stmt->bindParam(':fiscal_year_id', $fiscal_year_id);
        $invoice_stmt->execute();
        
        $db->commit();
        
        $_SESSION['success'] = 'Pro-forma invoice deleted successfully!';
        header("Location: proforma_view.php");
        exit;
        
    } catch(Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = 'Failed to delete pro-forma invoice: ' . $e->getMessage();
        header("Location: proforma_view.php");
        exit;
    }
}

// Fetch pro-forma invoices with party details
$query = "SELECT 
            i.*, 
            p.name as party_name,
            p.state as party_state,
            final_inv.invoice_no as final_invoice_no
          FROM ff_sch.proforma_invoices i 
          LEFT JOIN ff_sch.parties p ON i.party_id = p.id 
          LEFT JOIN ff_sch.invoices final_inv ON i.linked_invoice_id = final_inv.id
          WHERE i.fiscal_year_id = :fiscal_year_id
          ORDER BY i.invoice_no DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':fiscal_year_id', $current_fiscal_year_id);
$stmt->execute();
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Pro-forma Invoices - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/img/favicon.png">
    <link href="<?php echo ASSETS_URL; ?>/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    <style>
        .tax-badge {
            font-size: 0.75em;
        }
        .btn-group-sm > .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
    </style>
</head>
<body>
    <?php include "../../includes/header.php"; ?>
    <?php include "../../includes/sidebar.php"; ?>
    <div class="container-fluid">
        <!-- Success/Error Messages -->
        <?php if(isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if(isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Pro-forma Invoice Management</h2>
            <div>
                <a href="proforma_add.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Create New Pro-forma
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">All Pro-forma Invoices</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped data-table">
                        <thead>
                            <tr>
                                <th>Pro-forma No</th>
                                <th>Party Name</th>
                                <th>Date</th>
                                <th>Tax Type</th>
                                <th class="text-end">Taxable Amount</th>
                                <th class="text-end">Discount</th>
                                <th class="text-end">Tax Amount</th>
                                <th class="text-end">Net Amount</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($invoices as $invoice): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($invoice['invoice_no']); ?></strong>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo htmlspecialchars($invoice['party_name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($invoice['party_state']); ?></small>
                                    </div>
                                </td>
                                <td>
                                    <?php echo date('d M Y', strtotime($invoice['invoice_date'])); ?>
                                </td>
                                <td>
                                    <?php if($invoice['tax_type'] == 'intrastate'): ?>
                                        <span class="badge bg-primary tax-badge">SGST+CGST</span>
                                    <?php else: ?>
                                        <span class="badge bg-info tax-badge">GST</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <strong><?php echo CURRENCY . number_format($invoice['taxable_amount'], 2); ?></strong>
                                </td>
                                <td class="text-end">
                                    <?php if($invoice['discount'] > 0): ?>
                                        <span class="text-danger">
                                            -<?php echo CURRENCY . number_format($invoice['discount'], 2); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <span class="text-success">
                                        <?php echo CURRENCY . number_format($invoice['total_tax'], 2); ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <strong class="text-success"><?php echo CURRENCY . number_format($invoice['net_amount'], 2); ?></strong>
                                </td>
                                <td>
                                    <?php if ($invoice['linked_invoice_id']): ?>
                                        <span class="badge bg-success">Converted</span>
                                        <br>
                                        <small>
                                            <a href="view.php#<?php echo htmlspecialchars($invoice['final_invoice_no']); ?>" title="View Final Invoice">
                                                #<?php echo htmlspecialchars($invoice['final_invoice_no']); ?>
                                            </a>
                                        </small>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Pending</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="proforma_edit.php?id=<?php echo $invoice['id']; ?>&year=<?php echo $invoice['fiscal_year_id']; ?>" class="btn btn-warning me-2 <?php if ($invoice['linked_invoice_id']) echo 'disabled'; ?>" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" onclick="showPrintOptions('<?php echo $invoice['id']; ?>', '<?php echo $invoice['fiscal_year_id']; ?>')" class="btn btn-info me-2" title="Print">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <button type="button" 
                                                onclick="confirmDelete('Are you sure you want to delete pro-forma invoice <?php echo htmlspecialchars($invoice['invoice_no']); ?>?', 'proforma_view.php?delete_id=<?php echo $invoice['id']; ?>&year=<?php echo $invoice['fiscal_year_id']; ?>')"
                                                class="btn btn-danger <?php if ($invoice['linked_invoice_id']) echo 'disabled'; ?>" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/jquery-3.6.0.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/jquery.dataTables.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/dataTables.bootstrap5.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/sweetalert2@11.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/script.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/sidebar.js"></script>
    
    <script>
        $(document).ready(function() {
            $('.data-table').DataTable({
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                order: [[0, 'desc']], // Sort by invoice number descending
                dom: '<"row"<"col-md-6"l><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
                language: {
                    search: "Search:",
                    lengthMenu: "Show _MENU_ entries"
                }
            });
        });

        function confirmDelete(message, url) {
            Swal.fire({
                title: 'Are you sure?',
                text: message,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = url;
                }
            });
        }

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
</body>
</html>