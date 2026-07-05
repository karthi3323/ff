<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";
require_once "../../includes/audit_helper.php";

$database = new Database();
$db = $database->getConnection();

// Handle delete
if(isset($_GET['delete_id'])) {
    try {
        $db->beginTransaction();
        
        $invoice_id = $_GET['delete_id'];
        
        // Delete invoice items first (due to foreign key constraint)
        $items_query = "DELETE FROM ff_sch.invoice_items WHERE invoice_id = :invoice_id";
        $items_stmt = $db->prepare($items_query);
        $items_stmt->bindParam(':invoice_id', $invoice_id);
        $items_stmt->execute();
        
        // Delete invoice
        $invoice_query = "DELETE FROM ff_sch.invoices WHERE id = :invoice_id";
        $invoice_stmt = $db->prepare($invoice_query);
        $invoice_stmt->bindParam(':invoice_id', $invoice_id);
        $invoice_stmt->execute();
        
        $db->commit();
        
        $_SESSION['success'] = 'Invoice deleted successfully!';
        header("Location: view.php");
        exit;
        
    } catch(Exception $e) {
        $db->rollBack();
        $_SESSION['error'] = 'Failed to delete invoice: ' . $e->getMessage();
        header("Location: view.php");
        exit;
    }
}

// Get current fiscal year
$fiscal_year = $db->query("SELECT * FROM ff_sch.fiscal_years WHERE is_active = true LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$current_fiscal_year_id = $fiscal_year ? $fiscal_year['id'] : 1;

// Fetch invoices with party details and tax information
$query = "SELECT 
            i.*, 
            p.name as party_name,
            p.state as party_state,
            (i.sgst_amount + i.cgst_amount + i.igst_amount) as total_tax_amount
          FROM ff_sch.invoices i 
          LEFT JOIN ff_sch.parties p ON i.party_id = p.id 
          where i.fiscal_year_id ='".$current_fiscal_year_id."'
          ORDER BY i.invoice_no DESC";
$stmt = $db->query($query);
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get invoice IDs that have receipts against them
echo "SELECT DISTINCT invoice_id FROM ff_sch.rcpt_dtl WHERE invoice_id IS NOT NULL";
$paid_invoice_ids_stmt = $db->query("SELECT DISTINCT invoice_id FROM ff_sch.rcpt_dtl WHERE invoice_id IS NOT NULL");
$paid_invoice_ids = $paid_invoice_ids_stmt->fetchAll(PDO::FETCH_COLUMN, 0);

// Calculate statistics for dashboard
$stats_query = "SELECT 
                COUNT(*) as total_invoices,
                COALESCE(SUM(net_amount), 0) as total_sales,
                COALESCE(SUM(taxable_amount), 0) as total_taxable,
                COALESCE(SUM(total_tax), 0) as total_tax_collected,
                AVG(net_amount) as avg_invoice_value
               FROM ff_sch.invoices 
               WHERE DATE(created_at) = CURRENT_DATE";
$stats = $db->query($stats_query)->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Invoices - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/img/favicon.png">
    <link href="<?php echo ASSETS_URL; ?>/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/buttons.bootstrap5.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    <style>
        .stats-card {
            transition: transform 0.2s;
        }
        .stats-card:hover {
            transform: translateY(-2px);
        }
        .tax-badge {
            font-size: 0.75em;
        }
        .invoice-status {
            font-size: 0.8em;
            padding: 3px 8px;
            border-radius: 12px;
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
            <h2>Invoice Management</h2>
            <div>
                <a href="add.php" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Create New Invoice
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">All Invoices</h5>
                <!-- <div>
                    <a href="export.php" class="btn btn-success btn-sm">
                        <i class="fas fa-download me-1"></i>Export
                    </a>
                </div> -->
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped data-table">
                        <thead>
                            <tr>
                                <th>Invoice No</th>
                                <th>Party Name</th>
                                <th>Date</th>
                                <th>Tax Type</th>
                                <th class="text-end">Taxable Amount</th>
                                <th class="text-end">Discount</th>
                                <th class="text-end">Tax Amount</th>
                                <th class="text-end">Net Amount</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($invoices as $invoice): ?>
                            <tr>
                                <?php $is_paid = in_array($invoice['id'], $paid_invoice_ids); ?>
                                <td>
                                    <strong><?php echo $invoice['invoice_no']; ?></strong>
                                    <?php if($invoice['dispatch_through']): ?>
                                        <br><small class="text-muted">To: <?php echo $invoice['dispatch_through']; ?></small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div>
                                        <strong><?php echo $invoice['party_name']; ?></strong>
                                        <br><small class="text-muted"><?php echo $invoice['party_state']; ?></small>
                                    </div>
                                </td>
                                <td>
                                    <?php echo date('d M Y', strtotime($invoice['invoice_date'])); ?>
                                    <br><small class="text-muted"><?php echo date('h:i A', strtotime($invoice['created_at'])); ?></small>
                                </td>
                                <td>
                                    <?php if($invoice['tax_type'] == 'intrastate'): ?>
                                        <span class="badge bg-primary tax-badge">SGST+CGST</span>
                                        <br>
                                        <small class="text-muted">
                                            SGST: <?php echo $invoice['sgst_percent']; ?>%
                                            <br>CGST: <?php echo $invoice['cgst_percent']; ?>%
                                        </small>
                                    <?php else: ?>
                                        <span class="badge bg-info tax-badge">IGST</span>
                                        <br>
                                        <small class="text-muted">
                                            IGST: <?php echo $invoice['igst_percent']; ?>%
                                        </small>
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
                                        <br>
                                        <small class="text-muted">
                                            <?php echo $invoice['discount_type'] == 'percent' ? $invoice['discount_value'] . '%' : 'Fixed'; ?>
                                        </small>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <span class="text-success">
                                        <?php echo CURRENCY . number_format($invoice['total_tax'], 2); ?>
                                    </span>
                                    <?php if($invoice['tax_type'] == 'intrastate'): ?>
                                        <br>
                                        <small class="text-muted">
                                            SGST: <?php echo CURRENCY . number_format($invoice['sgst_amount'], 2); ?>
                                            <br>CGST: <?php echo CURRENCY . number_format($invoice['cgst_amount'], 2); ?>
                                        </small>
                                    <?php else: ?>
                                        <br>
                                        <small class="text-muted">
                                            IGST: <?php echo CURRENCY . number_format($invoice['igst_amount'], 2); ?>
                                        </small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end">
                                    <strong class="text-success"><?php echo CURRENCY . number_format($invoice['net_amount'], 2); ?></strong>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="edit.php?id=<?php echo $invoice['invoice_no']; ?>&year=<?php echo $current_fiscal_year_id; ?>" class="btn btn-warning me-2 <?= $is_paid ? 'disabled' : '' ?>" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <button type="button" onclick="showPrintOptions('<?php echo $invoice['invoice_no']; ?>', '<?php echo $current_fiscal_year_id; ?>')" class="btn btn-info me-2" title="Print">
                                            <i class="fas fa-print"></i>
                                        </button>
                                        <button type="button" 
                                                onclick="confirmDelete('Are you sure you want to delete invoice <?php echo $invoice['invoice_no']; ?>?', 'view.php?delete_id=<?php echo $invoice['id']; ?>')" 
                                                class="btn btn-danger <?= $is_paid ? 'disabled' : '' ?>" title="Delete">
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
    <script src="<?php echo ASSETS_URL; ?>/js/buttons.bootstrap5.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/jquery.dataTables.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/dataTables.bootstrap5.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/dataTables.buttons.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/sweetalert2@11.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/script.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/sidebar.js"></script>
    
    <script>
        $(document).ready(function() {
            var table =  $('.data-table').DataTable({
                pageLength: 25,
                lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]],
                order: [[2, 'desc']], // Sort by date descending
                dom: '<"row"<"col-md-6"l><"col-md-6"f>>rt<"row"<"col-md-6"i><"col-md-6"p>>',
                language: {
                    search: "Search invoices:",
                    lengthMenu: "Show _MENU_ invoices"
                },
                columnDefs: [
                    {
                        targets: [8],
                        orderable: false,
                        className: "text-center",
                        searchable: false // Important: Make action columns not searchable
                    }
                ]
                // ,
                // initComplete: function () {
                //     // Apply the search after table initialization
                //     this.api().columns().every(function () {
                //         var column = this;
                //         var headerIndex = column.index();
                        
                //         // Only apply search to columns that have inputs (exclude action columns)
                //         if (headerIndex < 8) { // Adjust this number based on your actual columns
                //             $('input', $('.data-table thead tr:eq(1) th').eq(headerIndex))
                //                 .on('keyup change', function () {
                //                     if (column.search() !== this.value) {
                //                         column.search(this.value).draw();
                //                     }
                //                 });
                //         }
                //     });
                // }
            });

            // Individual column searching
            $('.data-table thead .search-input').on('keyup change', function() {
                table.column($(this).parent().index())
                    .search(this.value)
                    .draw();
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
                    window.open(`new_rpt.php?id=${invoiceId}&year=${year}&copy=${result.value}`, '_blank');
                }
            });
        }
    </script>
</body>
</html>