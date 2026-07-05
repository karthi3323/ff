<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";

$database = new Database();
$db = $database->getConnection();

if(!isset($_GET['id'])) {
    header("Location: " . BASE_URL . "modules/invoice/view.php");
    exit();
}

$invoice_id = $_GET['id'];

// Fetch invoice details with updated table structure
$query = "SELECT i.*, p.name as party_name, p.address_line1, p.address_line2, p.city, p.state, 
                 p.gst_no as party_gst, c.name as company_name, c.address as company_address,
                 c.city as company_city, c.state as company_state, c.gst_no as company_gst,
                 c.phone as company_phone
          FROM ff_sch.invoices i 
          LEFT JOIN ff_sch.parties p ON i.party_id = p.id 
          LEFT JOIN ff_sch.companies c ON 1=1
          WHERE i.id = :id 
          LIMIT 1";

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $invoice_id);
$stmt->execute();
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$invoice) {
    header("Location: " . BASE_URL . "modules/invoice/view.php");
    exit();
}

// Fetch invoice items
$items_query = "SELECT ii.*, p.name as product_name 
                FROM ff_sch.invoice_items ii 
                LEFT JOIN ff_sch.products p ON ii.product_id = p.id 
                WHERE ii.invoice_id = :invoice_id 
                ORDER BY ii.id";
$items_stmt = $db->prepare($items_query);
$items_stmt->bindParam(':invoice_id', $invoice_id);
$items_stmt->execute();
$items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate totals based on new structure
$taxable_amount = $invoice['taxable_amount'];
$discount_amount = $invoice['discount_amount'];
$sgst_amount = $invoice['sgst_amount'];
$cgst_amount = $invoice['cgst_amount'];
$igst_amount = $invoice['igst_amount'];
$total_tax = $invoice['total_tax'];
$net_amount = $invoice['net_amount'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo $invoice['invoice_no']; ?> - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/img/favicon.png">
    <link href="<?php echo ASSETS_URL; ?>/css/bootstrap.min.css" rel="stylesheet">
    <style>
        @media print {
            .no-print {
                display: none !important;
            }
            .container {
                max-width: 100% !important;
                margin: 0 !important;
                padding: 10px !important;
            }
            body {
                font-family: "Times New Roman", Times, serif;
                font-size: 12px;
                margin: 0;
                padding: 0;
            }
            .page-break {
                page-break-after: always;
            }
        }
        body {
            font-family: Arial, sans-serif;
            font-size: 14px;
            margin: 0;
            padding: 10px;
        }
        .invoice-header {
            border-bottom: 2px solid #000;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        .invoice-table {
            width: 100%;
            border-collapse: collapse;
        }
        .invoice-table th {
            border-bottom: 2px solid #000;
            background-color: #f8f9fa;
            font-size: 11px;
            padding: 6px 4px;
            text-align: left;
        }
        .invoice-table td {
            padding: 4px;
            font-size: 11px;
            border-bottom: 1px solid #ddd;
        }
        .total-section {
            border-top: 2px solid #000;
            padding-top: 8px;
            margin-top: 10px;
        }
        .company-header {
            text-align: center;
            margin-bottom: 10px;
        }
        .company-header h2 {
            margin: 0;
            font-size: 18px;
            font-weight: bold;
        }
        .tax-details {
            font-size: 10px;
        }
        .signature-area {
            margin-top: 40px;
        }
        .footer-note {
            font-size: 9px;
            margin-top: 5px;
        }
        .amount-words {
            font-size: 10px;
            margin: 5px 0;
            padding: 3px;
            border: 1px solid #ddd;
            background-color: #f9f9f9;
        }
        .table-responsive {
            max-height: 400px;
            overflow: hidden;
        }
    </style>
</head>
<body>
    <div class="no-print text-end mb-3" style="position: fixed; top: 10px; right: 10px; z-index: 1000; background: white; padding: 5px; border-radius: 5px;">
        <button onclick="window.print()" class="btn btn-primary btn-sm">
            <i class="fas fa-print me-1"></i>Print
        </button>
        <a href="view.php" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Back
        </a>
    </div>

    <div class="container">
        <!-- Company Header -->
        <div class="company-header">
            <h2><?php echo $invoice['company_name'] ?? COMPANY_NAME; ?></h2>
            <p style="margin: 2px 0; font-size: 11px;">
                <?php echo $invoice['company_address']; ?>, 
                <?php echo $invoice['company_city'] . ' - ' . $invoice['company_state']; ?>
            </p>
            <p style="margin: 2px 0; font-size: 10px;">
                GST: <?php echo $invoice['company_gst']; ?> | 
                Phone: <?php echo $invoice['company_phone']; ?>
            </p>
        </div>

        <!-- Invoice Header -->
        <div class="row invoice-header">
            <div class="col-md-6">
                <h4 style="margin-bottom: 8px; font-size: 16px;">TAX INVOICE</h4>
                <p style="margin: 1px 0; font-size: 11px;"><strong>Invoice No:</strong> <?php echo $invoice['invoice_no']; ?></p>
                <p style="margin: 1px 0; font-size: 11px;"><strong>Date:</strong> <?php echo date('d/m/Y', strtotime($invoice['invoice_date'])); ?></p>
                <?php if($invoice['dispatch_through']): ?>
                    <p style="margin: 1px 0; font-size: 11px;"><strong>Dispatch:</strong> <?php echo $invoice['dispatch_through']; ?></p>
                <?php endif; ?>
                <p style="margin: 1px 0; font-size: 11px;"><strong>GST Type:</strong> <?php echo strtoupper($invoice['tax_type']); ?></p>
            </div>
            <div class="col-md-6">
                <h5 style="margin-bottom: 3px; font-size: 12px;">Bill To:</h5>
                <p style="margin: 1px 0; font-weight: bold; font-size: 11px;"><?php echo $invoice['party_name']; ?></p>
                <?php if($invoice['address_line1']): ?>
                    <p style="margin: 1px 0; font-size: 10px;"><?php echo $invoice['address_line1']; ?></p>
                <?php endif; ?>
                <?php if($invoice['address_line2']): ?>
                    <p style="margin: 1px 0; font-size: 10px;"><?php echo $invoice['address_line2']; ?></p>
                <?php endif; ?>
                <p style="margin: 1px 0; font-size: 10px;"><?php echo $invoice['city'] . ', ' . $invoice['state']; ?></p>
                <?php if($invoice['party_gst']): ?>
                    <p style="margin: 1px 0; font-size: 10px;">GST: <?php echo $invoice['party_gst']; ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Items Table -->
        <div class="table-responsive">
            <table class="table table-bordered invoice-table">
                <thead>
                    <tr>
                        <th width="4%">Sr No</th>
                        <th width="35%">Product Description</th>
                        <th width="10%">Carton Contents</th>
                        <th width="6%">UOM</th>
                        <th width="10%" class="text-end">Rate (<?php echo CURRENCY; ?>)</th>
                        <th width="8%" class="text-end">Cartons</th>
                        <th width="12%" class="text-end">Carton Range</th>
                        <th width="15%" class="text-end">Amount (<?php echo CURRENCY; ?>)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($items as $index => $item): ?>
                    <tr>
                        <td><?php echo $index + 1; ?></td>
                        <td><?php echo $item['product_name']; ?></td>
                        <td><?php echo $item['carton_contents'] ?: '-'; ?></td>
                        <td><?php echo $item['uom']; ?></td>
                        <td class="text-end"><?php echo number_format($item['rate'], 2); ?></td>
                        <td class="text-end"><?php echo $item['cartons']; ?></td>
                        <td class="text-end"><?php echo $item['carton_from'] . ' - ' . $item['carton_to']; ?></td>
                        <td class="text-end"><?php echo number_format($item['total_amount'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Dynamic spacing - no fixed empty rows -->
                </tbody>
            </table>
        </div>

        <!-- Totals Section -->
        <div class="row justify-content-end mt-3">
            <div class="col-md-5">
                <table class="table table-bordered" style="font-size: 11px;">
                    <tr>
                        <td width="60%"><strong>Taxable Amount:</strong></td>
                        <td width="40%" class="text-end"><?php echo CURRENCY . number_format($taxable_amount, 2); ?></td>
                    </tr>
                    
                    <?php if($discount_amount > 0): ?>
                    <tr>
                        <td><strong>Discount:</strong></td>
                        <td class="text-end">- <?php echo CURRENCY . number_format($discount_amount, 2); ?></td>
                    </tr>
                    <?php endif; ?>
                    
                    <!-- Tax Breakdown -->
                    <?php if($invoice['tax_type'] == 'intrastate'): ?>
                        <?php if($sgst_amount > 0): ?>
                        <tr>
                            <td class="tax-details">SGST (<?php echo $invoice['sgst_percent']; ?>%):</td>
                            <td class="text-end tax-details"><?php echo CURRENCY . number_format($sgst_amount, 2); ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if($cgst_amount > 0): ?>
                        <tr>
                            <td class="tax-details">CGST (<?php echo $invoice['cgst_percent']; ?>%):</td>
                            <td class="text-end tax-details"><?php echo CURRENCY . number_format($cgst_amount, 2); ?></td>
                        </tr>
                        <?php endif; ?>
                    <?php else: ?>
                        <?php if($igst_amount > 0): ?>
                        <tr>
                            <td class="tax-details">IGST (<?php echo $invoice['igst_percent']; ?>%):</td>
                            <td class="text-end tax-details"><?php echo CURRENCY . number_format($igst_amount, 2); ?></td>
                        </tr>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <tr class="total-section">
                        <td><strong>Total Tax:</strong></td>
                        <td class="text-end"><strong><?php echo CURRENCY . number_format($total_tax, 2); ?></strong></td>
                    </tr>
                    <tr class="total-section">
                        <td><strong>Net Amount:</strong></td>
                        <td class="text-end"><strong><?php echo CURRENCY . number_format($net_amount, 2); ?></strong></td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Amount in Words -->
        <div class="row mt-2">
            <div class="col-md-12">
                <div class="amount-words">
                    <strong>Amount in Words:</strong> 
                    <?php echo convertNumberToWords($net_amount); ?> Rupees Only
                </div>
            </div>
        </div>

        <!-- Footer and Signatures -->
        <div class="row signature-area">
            <div class="col-md-7">
                <p style="font-size: 9px; margin: 2px 0;"><strong>Terms & Conditions:</strong></p>
                <p style="font-size: 8px; margin: 1px 0; line-height: 1.2;">
                    1. Goods once sold will not be taken back.<br>
                    2. Interest @18% p.a. will be charged on overdue payments.<br>
                    3. Subject to <?php echo $invoice['company_city'] ?? 'our'; ?> jurisdiction.<br>
                    4. E. & O.E.
                </p>
                <p class="footer-note">
                    This is a computer generated invoice
                </p>
            </div>
            <div class="col-md-5 text-center">
                <div style="border-top: 1px solid #000; width: 200px; margin: 0 auto; padding-top: 30px;">
                    For <?php echo $invoice['company_name'] ?? COMPANY_NAME; ?>
                </div>
                <p style="margin-top: 5px; font-size: 10px;">
                    <strong>Authorized Signatory</strong>
                </p>
            </div>
        </div>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/all.min.js"></script>
    <script>
        // Auto-print option (uncomment if needed)
        // window.onload = function() {
        //     window.print();
        // }
    </script>
</body>
</html>

<?php
// Function to convert number to words
function convertNumberToWords($number) {
    $ones = array(
        0 => "",
        1 => "One",
        2 => "Two",
        3 => "Three",
        4 => "Four",
        5 => "Five",
        6 => "Six",
        7 => "Seven",
        8 => "Eight",
        9 => "Nine",
        10 => "Ten",
        11 => "Eleven",
        12 => "Twelve",
        13 => "Thirteen",
        14 => "Fourteen",
        15 => "Fifteen",
        16 => "Sixteen",
        17 => "Seventeen",
        18 => "Eighteen",
        19 => "Nineteen"
    );
    
    $tens = array(
        2 => "Twenty",
        3 => "Thirty",
        4 => "Forty",
        5 => "Fifty",
        6 => "Sixty",
        7 => "Seventy",
        8 => "Eighty",
        9 => "Ninety"
    );
    
    $number = number_format($number, 2, '.', '');
    $parts = explode('.', $number);
    $rupees = $parts[0];
    $paise = isset($parts[1]) ? $parts[1] : '00';
    
    if ($rupees == 0) {
        return "Zero";
    }
    
    $words = "";
    
    // Convert rupees part
    if ($rupees >= 10000000) {
        $crores = floor($rupees / 10000000);
        $words .= convertNumberToWords($crores) . " Crore ";
        $rupees %= 10000000;
    }
    
    if ($rupees >= 100000) {
        $lakhs = floor($rupees / 100000);
        $words .= convertNumberToWords($lakhs) . " Lakh ";
        $rupees %= 100000;
    }
    
    if ($rupees >= 1000) {
        $thousands = floor($rupees / 1000);
        $words .= convertNumberToWords($thousands) . " Thousand ";
        $rupees %= 1000;
    }
    
    if ($rupees >= 100) {
        $hundreds = floor($rupees / 100);
        $words .= $ones[$hundreds] . " Hundred ";
        $rupees %= 100;
    }
    
    if ($rupees >= 20) {
        $tens_digit = floor($rupees / 10);
        $words .= $tens[$tens_digit] . " ";
        $rupees %= 10;
    }
    
    if ($rupees > 0) {
        $words .= $ones[$rupees] . " ";
    }
    
    $words = trim($words);
    
    // Add paise part
    if ($paise > 0) {
        $words .= " and ";
        if ($paise < 20) {
            $words .= $ones[$paise] . " Paise";
        } else {
            $tens_digit = floor($paise / 10);
            $ones_digit = $paise % 10;
            $words .= $tens[$tens_digit];
            if ($ones_digit > 0) {
                $words .= " " . $ones[$ones_digit];
            }
            $words .= " Paise";
        }
    }
    
    return $words;
}
?>