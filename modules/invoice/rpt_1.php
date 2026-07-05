<?php
// ==========================================
// 1. PHP DATABASE AND SESSION SETUP
// ==========================================
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

// ==========================================
// 2. FETCH ALL REQUIRED DATA
// ==========================================

// Fetch master settings
$master = $db->query("SELECT * FROM ff_sch.master WHERE is_active = true LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$hsn_cd = $master['hsn_code'] ?? "3604";

// Fetch invoice details
$query = "SELECT i.*, p.name as party_name, TRIM(
        COALESCE(address_line1, '') ||
        CASE 
            WHEN address_line2 IS NOT NULL AND address_line2 <> '' 
            THEN ', ' || address_line2 
            ELSE '' 
        END
    ) AS address, TRIM(
        COALESCE(p.city, '') ||
        CASE 
            WHEN pin_code IS NOT NULL AND pin_code <> '' 
            THEN ', ' || pin_code 
            ELSE '' 
        END
    ) AS city, p.city p_city, p.state, 
                p.gst_no as party_gst, c.name as company_name, c.address as company_address,
                c.city as company_city, c.state as company_state, c.gst_no as company_gst,
                c.phone as company_phone, c.email as company_email, lic_no1 AS comp_lic1, lic_no2 AS comp_lic2
        FROM ff_sch.invoices i 
        LEFT JOIN ff_sch.parties p ON i.party_id = p.id 
        LEFT JOIN ff_sch.companies c ON 1=1
        WHERE i.invoice_no   = :id 
        LIMIT 1";

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $invoice_id);
$stmt->execute();
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);
$inv_num = $invoice['id'];

// If company info not present, fetch from companies table
if (empty($invoice['company_name'])) {
    $cstmt = $db->prepare("SELECT name AS company_name, address AS company_address, city AS company_city, state AS company_state, gst_no AS company_gst, phone AS company_phone, email AS company_email, lic_no1 AS comp_lic1, lic_no2 AS comp_lic2 FROM ff_sch.companies LIMIT 1");
    $cstmt->execute();
    $company_fallback = $cstmt->fetch(PDO::FETCH_ASSOC);
    if ($company_fallback) {
        foreach ($company_fallback as $k => $v) {
            if (empty($invoice[$k])) $invoice[$k] = $v;
        }
    }
}

// Fetch invoice items
$items_query = "SELECT ii.*, p.name as product_name,p.per_box_pieces 
                FROM ff_sch.invoice_items ii 
                LEFT JOIN ff_sch.products p ON ii.product_id = p.id 
                WHERE ii.invoice_id = :invoice_id 
                ORDER BY ii.id";
$items_stmt = $db->prepare($items_query);
$items_stmt->bindParam(':invoice_id', $inv_num);
$items_stmt->execute();
$items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch total cartons
$cartons_query = "SELECT sum(ii.cartons) tot_cartons
            FROM ff_sch.invoice_items ii
            WHERE ii.invoice_id = :invoice_id";
$cartons_count_stmt = $db->prepare($cartons_query);
$cartons_count_stmt->bindParam(':invoice_id', $inv_num);
$cartons_count_stmt->execute();
$total_cartons = $cartons_count_stmt->fetchColumn();

// ==========================================
// 3. CALCULATE TOTALS
// ==========================================
$disc_percent = $invoice['discount_value'] ?? 0;
$discount_amount = $invoice['discount'] ?? 0;
$taxable_amount = $invoice['taxable_amount'] ?? 0;
$igst_percent = $invoice['igst_percent'] ?? 0;
$sgst_percent = $invoice['sgst_percent'] ?? 0;
$cgst_percent = $invoice['cgst_percent'] ?? 0;
$igst_amount = $invoice['igst_amount'] ?? 0;
$sgst_amount = $invoice['sgst_amount'] ?? 0;
$cgst_amount = $invoice['cgst_amount'] ?? 0;
$round_off = $invoice['round_off'] ?? 0;
$net_amount = $invoice['net_amount'] ?? 0;

$val = $taxable_amount - $discount_amount;

// Adjust percentages if zero
if($sgst_amount == 0 && $cgst_amount == 0){
    $cgst_percent = 0;
    $sgst_percent = 0;
}
if($igst_amount == 0){
    $igst_percent = 0;
}

// ==========================================
// 4. HELPER FUNCTIONS
// ==========================================
function int_to_words($number) {
    // Simple number to words conversion (you can enhance this)
    $words = array(
        '0' => '', '1' => 'One', '2' => 'Two', '3' => 'Three', '4' => 'Four',
        '5' => 'Five', '6' => 'Six', '7' => 'Seven', '8' => 'Eight', '9' => 'Nine',
        '10' => 'Ten', '11' => 'Eleven', '12' => 'Twelve', '13' => 'Thirteen',
        '14' => 'Fourteen', '15' => 'Fifteen', '16' => 'Sixteen', '17' => 'Seventeen',
        '18' => 'Eighteen', '19' => 'Nineteen', '20' => 'Twenty', '30' => 'Thirty',
        '40' => 'Forty', '50' => 'Fifty', '60' => 'Sixty', '70' => 'Seventy',
        '80' => 'Eighty', '90' => 'Ninety'
    );
    
    $number = str_replace(',', '', $number);
    $number = floatval($number);
    $rupees = floor($number);
    
    if ($rupees == 0) {
        return 'Zero';
    }
    
    // Simplified version - for production use a proper library
    if ($rupees <= 20) {
        return $words[$rupees];
    }
    
    return number_format($rupees) . ''; // Return number as fallback
}

$amount_in_words = int_to_words($net_amount) . " Rupees Only";

// ==========================================
// 5. HTML/CSS PRINT TEMPLATE
// ==========================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice <?php echo htmlspecialchars($invoice['invoice_no']); ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/img/favicon.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ==========================================
           RESET AND BASE STYLES
        ========================================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            /* background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); */
            min-height: 100vh;
            padding: 30px;
        }

        /* ==========================================
           SCREEN STYLES (FOR BROWSER VIEW)
        ========================================== */
        @media screen {
            .container {
                max-width: 210mm;
                margin: 0 auto;
                background: white;
                border-radius: 12px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                overflow: hidden;
                position: relative;
            }

            .controls {
                background: white;
                padding: 20px;
                margin-bottom: 20px;
                border-radius: 12px;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
                display: flex;
                gap: 15px;
                justify-content: center;
                align-items: center;
            }

            .btn {
                padding: 12px 28px;
                border: none;
                border-radius: 8px;
                cursor: pointer;
                font-weight: 600;
                font-size: 14px;
                display: inline-flex;
                align-items: center;
                gap: 8px;
                transition: all 0.3s ease;
                text-decoration: none;
            }

            .btn-primary {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
            }

            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 20px rgba(102, 126, 234, 0.4);
            }

            .btn-secondary {
                background: #6c757d;
                color: white;
            }

            .btn-secondary:hover {
                background: #5a6268;
                transform: translateY(-2px);
            }

            .btn-success {
                background: #28a745;
                color: white;
            }

            .btn-success:hover {
                background: #218838;
                transform: translateY(-2px);
            }

            .info-box {
                background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
                color: white;
                padding: 15px;
                border-radius: 8px;
                margin: 20px auto;
                max-width: 210mm;
                text-align: center;
                box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
            }

            .info-box h3 {
                margin-bottom: 10px;
                font-size: 18px;
            }

            .watermark {
                position: fixed;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%) rotate(-45deg);
                font-size: 120px;
                color: rgba(255, 255, 255, 0.1);
                z-index: -1;
                font-weight: bold;
                pointer-events: none;
            }
        }

        /* ==========================================
           PRINT STYLES (WHEN PRINTING)
        ========================================== */
        @media print {
            body {
                background: none !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .container {
                max-width: 100% !important;
                margin: 0 !important;
                box-shadow: none !important;
                border-radius: 0 !important;
            }

            .controls,
            .info-box,
            .watermark {
                display: none !important;
            }

            .invoice-page {
                page-break-after: always;
            }

            .keep-together {
                page-break-inside: avoid;
            }
        }

        /* ==========================================
           INVOICE STYLES (BOTH SCREEN AND PRINT)
        ========================================== */
        .invoice-page {
            padding: 15mm;
            min-height: 297mm;
            position: relative;
        }

        /* HEADER SECTION */
        .invoice-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
            /* border-bottom: 3px double #333; */
            padding-bottom: 15px;
        }

        .logo-section {
            flex: 1;
        }

        .logo {
            max-height: 120px;
            width: auto;
        }

        .company-info {
            flex: 3;
            text-align: center;
        }

        .company-name {
            font-family: 'times';
            font-size: 22px;
            font-weight: bold;
            text-transform: uppercase;
            color: #2c3e50;
            margin-bottom: 5px;
        }

        .company-address {
            font-size: 16px;
            color: #555;
            margin-bottom: 5px;
        }

        .off-info{
            display:flex;
            justify-content:space-between;
        }

        .gst-info {
            font-size: 11px;
            font-weight: bold;
            color: #333;
        }

        .licence-info {
            font-size: 10px;
            color: #666;
        }

        .invoice-title {
            text-align: center;
            font-size: 20px;
            font-weight: bold;
            margin: 15px 0;
            color: #2c3e50;
            /* border-top: 2px solid #333; */
            /* border-bottom: 2px solid #333; */
            padding: 8px 0;
        }

        /* ADDRESS SECTION */
        .address-section {
            display: flex;
            border: 2px solid #333;
            padding: 15px;
            margin-bottom: 20px;
            background: #f9f9f9;
        }

        .party-info {
            flex: 1;
            padding-right: 20px;
            border-right: 1px solid #ccc;
        }

        .party-title {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #2c3e50;
        }

        .party-name {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .party-address {
            font-size: 12px;
            margin-bottom: 5px;
        }

        .invoice-details {
            flex: 0 0 250px;
            padding-left: 20px;
        }

        .invoice-number {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .invoice-date {
            font-size: 14px;
            font-weight: bold;
        }

        /* TABLE STYLES */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 11px;
        }

        .items-table th {
            background: #e0e0e0;
            color: #000;
            font-weight: bold;
            padding: 8px 5px;
            text-align: center;
            vertical-align: middle;
            border: 1px solid #333;
        }

        .items-table td {
            padding: 6px 5px;
            border: 1px solid #333;
            vertical-align: middle;
        }

        .table-header-main {
            background: #e0e0e0 !important;
            color: #333 !important;
        }

        .text-center {
            text-align: center;
        }

        .text-right {
            text-align: right;
        }

        .text-left {
            text-align: left;
        }

        .text-bold {
            font-weight: bold;
        }

        .text-uppercase {
            text-transform: uppercase;
        }

        /* BOTTOM SECTION */
        .bottom-section {
            border: 2px solid #333;
            padding: 15px;
            margin-top: 20px;
            position: relative;
        }

        .section-columns {
            display: flex;
            gap: 20px;
        }

        .left-column {
            flex: 1;
        }

        .right-column {
            flex: 1;
        }

        .detail-row {
            display: flex;
            margin-bottom: 6px;
            font-size: 12px;
        }

        .detail-label {
            flex: 0 0 150px;
            font-weight: bold;
        }

        .detail-value {
            flex: 1;
        }

        .amount-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            padding: 3px 0;
            font-size: 12px;
        }

        .amount-label {
            font-weight: bold;
        }

        .total-row {
            border-top: 2px solid #333;
            margin-top: 10px;
            padding-top: 10px;
            font-size: 14px;
            font-weight: bold;
        }

        /* AMOUNT IN WORDS */
        .amount-in-words {
            border: 1px solid #333;
            padding: 10px;
            margin: 15px 0;
            font-style: italic;
            font-size: 12px;
            background: #f8f9fa;
            text-align: center;
        }

        /* FOOTER SECTION */
        .footer-section {
            display: flex;
            margin-top: 20px;
            font-size: 11px;
        }

        .footer-left {
            flex: 2;
            padding-right: 20px;
        }

        .footer-right {
            flex: 1;
            text-align: right;
            border-left: 1px solid #ccc;
            padding-left: 20px;
        }

        .declaration {
            margin-bottom: 15px;
        }

        .declaration-title {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 12px;
        }

        .bank-details {
            margin-bottom: 10px;
        }

        .bank-title {
            font-weight: bold;
            margin-bottom: 5px;
            font-size: 12px;
        }

        .bank-info {
            font-size: 10px;
            line-height: 1.4;
        }

        .signature-space {
            height: 60px;
            margin-top: 20px;
            border-top: 1px solid #333;
            position: relative;
        }

        .signature-text {
            position: absolute;
            bottom: 5px;
            right: 0;
            font-size: 12px;
            font-weight: bold;
        }

        /* PAGE NUMBER */
        .page-number {
            text-align: center;
            font-size: 10px;
            margin-top: 20px;
            color: #666;
        }

        /* UTILITY CLASSES */
        .mb-10 { margin-bottom: 10px; }
        .mb-20 { margin-bottom: 20px; }
        .mt-10 { margin-top: 10px; }
        .mt-20 { margin-top: 20px; }
        .p-10 { padding: 10px; }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .address-section,
            .section-columns,
            .footer-section {
                flex-direction: column;
            }
            
            .party-info {
                border-right: none;
                border-bottom: 1px solid #ccc;
                padding-right: 0;
                padding-bottom: 15px;
                margin-bottom: 15px;
            }
            
            .invoice-details {
                padding-left: 0;
            }
            
            .footer-right {
                border-left: none;
                border-top: 1px solid #ccc;
                padding-left: 0;
                padding-top: 15px;
                margin-top: 15px;
                text-align: left;
            }
        }
    </style>
</head>
<body>
    <!-- Watermark (Screen only) -->
    <div class="watermark">PREVIEW</div>
    
    <!-- Controls (Screen only) -->
    <div class="controls">
         <div class="row d-print-none mt-4">
              <div class="col-12 text-right"><a class="btn btn-primary" href="javascript:window.print();"><i class="fa fa-print"></i> Print</a></div>
            </div>
        <button class="btn btn-primary" onclick="window.print()">
            <i class="fas fa-print"></i> Print Invoice
        </button>
        <a href="<?php echo BASE_URL; ?>modules/invoice/view.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Invoices
        </a>
        <!-- <button class="btn btn-success" onclick="saveAsPDF()">
            <i class="fas fa-file-pdf"></i> Save as PDF
        </button> -->
    </div>
    
    <!-- Info Box (Screen only) -->
   <!--  <div class="info-box">
        <h3><i class="fas fa-info-circle"></i> Invoice Preview</h3>
        <p>Click the Print button to print or use your browser's print function (Ctrl+P)</p>
    </div> -->
    
    <!-- Invoice Container -->
    <div class="container">
        <div class="invoice-page">

            <!-- Invoice Title -->
            <div class="invoice-title">TAX INVOICE</div>
            <!-- Header Section -->
            <div class="invoice-header">
                <div class="logo-section">
                    <img src="<?php echo ASSETS_URL; ?>/img/logo.png" alt="Company Logo" class="logo">
                </div>
                
                <div class="company-info">
                    <div class="company-name"><?php echo htmlspecialchars(strtoupper($invoice['company_name'])); ?></div>
                    <div class="company-address"><?php echo htmlspecialchars($invoice['company_address']); ?></div>
                </div>
                
                <div class="logo-section" style="text-align: right;">
                    <img src="<?php echo ASSETS_URL; ?>/img/trademark.png" alt="NEERI Logo" class="logo">
                </div>
            </div>
            <div class="off-info">
                <div class="gst-info">GSTIN: <?php echo htmlspecialchars($invoice['company_gst']); ?></div>
                <div class="licence-info">
                    LICENCE NO: <?php echo htmlspecialchars($invoice['comp_lic1']); ?><br>
                    LICENCE NO: <?php echo htmlspecialchars($invoice['comp_lic2']); ?>
                </div>
            </div>
            
            
            <!-- Address Section -->
            <div class="address-section">
                <div class="party-info">
                    <div class="party-title">Party's Name and Address</div>
                    <div class="party-name"><?php echo htmlspecialchars(strtoupper($invoice['party_name'])); ?></div>
                    <div class="party-address"><?php echo htmlspecialchars(strtoupper($invoice['address'])); ?></div>
                    <div class="party-address">Place: <?php echo htmlspecialchars(strtoupper($invoice['city'])); ?></div>
                    <div class="party-address">State: <?php echo htmlspecialchars(strtoupper($invoice['state'])); ?></div>
                    <div class="gst-info">GSTIN: <?php echo htmlspecialchars(strtoupper($invoice['party_gst'])); ?></div>
                </div>
                
                <div class="invoice-details">
                    <div class="invoice-number">Invoice No: <?php echo htmlspecialchars($invoice['invoice_no']); ?></div>
                    <div class="invoice-date">Invoice Date: <?php echo date('d-m-Y', strtotime($invoice['invoice_date'])); ?></div>
                </div>
            </div>
            
            <!-- Items Table -->
            <table class="items-table keep-together">
                <thead>
                    <tr>
                        <th rowspan="2" class="text-center">Sl</th>
                        <th rowspan="2" class="text-center">Carton From - To</th>
                        <th rowspan="2" class="text-center">Product Name</th>
                        <th colspan="2" class="text-center table-header-main">Package Details</th>
                        <th rowspan="2" class="text-center">Qty</th>
                        <th rowspan="2" class="text-center">Rate</th>
                        <th rowspan="2" class="text-center">Per</th>
                        <th rowspan="2" class="text-center">Amount</th>
                    </tr>
                    <tr>
                        <th class="text-center table-header-main">Carton</th>
                        <th class="text-center table-header-main">Carton Contents</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sl = 1;
                    $total_items = count($items);
                    foreach ($items as $item):
                        $range = '-';
                        $ctn = $item['cartons'] ?? 0;
                        if (!empty($item['carton_from']) && !empty($item['carton_to'])) {
                            $range = ($ctn == 1) ? $item['carton_from'] : $item['carton_from'] . " - " . $item['carton_to'];
                        }
                        $qty_words = preg_replace('/[^a-zA-Z\s]+/', '', $item['carton_contents'] ?? '');
                    ?>
                    <tr>
                        <td class="text-center"><?php echo $sl++; ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($range); ?></td>
                        <td class="text-left"><?php echo htmlspecialchars($item['product_name']); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($ctn); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($item['carton_contents'] ?? '-'); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($item['qty'] . ' ' . $qty_words); ?></td>
                        <td class="text-right"><?php echo number_format($item['rate'], 2); ?></td>
                        <td class="text-center"><?php echo htmlspecialchars($item['per_box_pieces'] ?? '-'); ?></td>
                        <td class="text-right text-bold"><?php echo number_format($item['total_amount'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    
                    <!-- Fill empty rows for consistent layout -->
                    <?php for($i = $total_items; $i < 15; $i++): ?>
                    <tr>
                        <td class="text-center"><?php echo $i + 1; ?></td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                        <td>&nbsp;</td>
                    </tr>
                    <?php endfor; ?>
                </tbody>
            </table>
            
            <!-- Bottom Section -->
            <div class="bottom-section keep-together">
                <div class="section-columns">
                    <!-- Left Column - Details -->
                    <div class="left-column">
                        <div class="detail-row">
                            <div class="detail-label">HSN Code:</div>
                            <div class="detail-value text-bold"><?php echo htmlspecialchars($hsn_cd); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Total Cartons:</div>
                            <div class="detail-value text-bold"><?php echo htmlspecialchars($total_cartons); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Despatched From:</div>
                            <div class="detail-value text-bold"><?php echo htmlspecialchars($invoice['dispatch_from']); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Despatched To:</div>
                            <div class="detail-value text-bold"><?php echo htmlspecialchars($invoice['dispatch_through']); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Vehicle No:</div>
                            <div class="detail-value text-bold"><?php echo htmlspecialchars($invoice['vehicle_no']); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Transport Name:</div>
                            <div class="detail-value text-bold"><?php echo htmlspecialchars($invoice['transport_name']); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">Transport GSTIN:</div>
                            <div class="detail-value text-bold"><?php echo htmlspecialchars($invoice['transport_gst']); ?></div>
                        </div>
                        <div class="detail-row">
                            <div class="detail-label">E-way Bill:</div>
                            <div class="detail-value text-bold"><?php echo htmlspecialchars($invoice['eway_bill_no']); ?></div>
                        </div>
                    </div>
                    
                    <!-- Right Column - Amounts -->
                    <div class="right-column">
                        <div class="amount-row">
                            <div class="amount-label">GOODS VALUE:</div>
                            <div class="text-bold"><?php echo number_format($taxable_amount, 2); ?></div>
                        </div>
                        <div class="amount-row">
                            <div class="amount-label">LESS DISC <?php echo $disc_percent; ?>%:</div>
                            <div class="text-bold"><?php echo number_format($discount_amount, 2); ?></div>
                        </div>
                        <div class="amount-row">
                            <div class="amount-label">TAXABLE VALUE:</div>
                            <div class="text-bold"><?php echo number_format($val, 2); ?></div>
                        </div>
                        <div class="amount-row">
                            <div class="amount-label">IGST <?php echo $igst_percent; ?>%:</div>
                            <div class="text-bold"><?php echo number_format($igst_amount, 2); ?></div>
                        </div>
                        <div class="amount-row">
                            <div class="amount-label">SGST <?php echo $sgst_percent; ?>%:</div>
                            <div class="text-bold"><?php echo number_format($sgst_amount, 2); ?></div>
                        </div>
                        <div class="amount-row">
                            <div class="amount-label">CGST <?php echo $cgst_percent; ?>%:</div>
                            <div class="text-bold"><?php echo number_format($cgst_amount, 2); ?></div>
                        </div>
                        <div class="amount-row">
                            <div class="amount-label">ROUND OFF:</div>
                            <div class="text-bold"><?php echo number_format($round_off, 2); ?></div>
                        </div>
                        <div class="amount-row total-row">
                            <div class="amount-label">NET AMOUNT:</div>
                            <div class="text-bold" style="font-size: 16px;"><?php echo number_format($net_amount, 2); ?></div>
                        </div>
                    </div>
                </div>
                
                <!-- Amount in Words -->
                <div class="amount-in-words">
                    <span class="text-bold">Total Amount In Words:</span> <?php echo htmlspecialchars($amount_in_words); ?>
                </div>
                
                <!-- Footer Section -->
                <div class="footer-section">
                    <div class="footer-left">
                        <div class="declaration">
                            <div class="declaration-title">Declaration:</div>
                            <div>We declare that this invoice shows the actual price of the goods and that all particulars are true and correct.</div>
                        </div>
                        
                        <div class="bank-details">
                            <div class="bank-title">Company Bank Details:</div>
                            <div class="bank-info">
                                <strong>Account 1:</strong> Jeyalakshmi Priya Sparklers Factory<br>
                                Punjab National Bank | A/C: 4199002100015343 | IFSC: PUNB0419900
                            </div>
                            <div class="bank-info mt-10">
                                <strong>Account 2:</strong> Jeyalakshmi Priya Sparklers Factory<br>
                                Tamilnadu Mercantile Bank | A/C: 003700050900353 | IFSC: TMBL0000037
                            </div>
                        </div>
                    </div>
                    
                    <div class="footer-right">
                        <div>For <?php echo htmlspecialchars($invoice['company_name']); ?></div>
                        <div class="signature-space">
                            <div class="signature-text">Authorized Signatory</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Page Number -->
            <div class="page-number">
                Page 1 of 1 | Generated on: <?php echo date('d-m-Y h:i A'); ?>
            </div>
        </div>
    </div>
    
    <script>
        // ==========================================
        // JAVASCRIPT FUNCTIONS
        // ==========================================
        
        function saveAsPDF() {
            // Method 1: Use browser's print to PDF
            alert("To save as PDF:\n1. Click the Print button\n2. Choose 'Save as PDF' as your printer\n3. Click Save");
            
            // Method 2: Using html2pdf.js (if you include the library)
            /*
            if (typeof html2pdf !== 'undefined') {
                const element = document.querySelector('.container');
                html2pdf()
                    .from(element)
                    .set({
                        margin: [10, 10, 10, 10],
                        filename: 'Invoice_<?php echo $invoice['invoice_no']; ?>.pdf',
                        html2canvas: { scale: 2 },
                        jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
                    })
                    .save();
            } else {
                alert("Please include html2pdf.js library for PDF generation");
            }
            */
        }
        
        // Auto-print if print parameter is set
       /*  window.onload = function() {
            const urlParams = new URLSearchParams(window.location.search);
            console.log(urlParams.get())
            if(urlParams.get('print') === '1') {
                setTimeout(() => {
                    window.print();
                }, 500);
            }
            
            // Add keyboard shortcut for printing
            document.addEventListener('keydown', function(e) {
                if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                    e.preventDefault();
                    window.print();
                }
            });
        }; */
        
        // Add beforeprint event to hide controls
        window.addEventListener('beforeprint', function() {
            document.querySelectorAll('.no-print').forEach(el => {
                el.style.display = 'none';
            });
        });
        
        // Add afterprint event to show controls
        window.addEventListener('afterprint', function() {
            document.querySelectorAll('.no-print').forEach(el => {
                el.style.display = '';
            });
        });
    </script>
    
    <!-- Optional: Include html2pdf.js library for PDF generation -->
    <!-- 
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    -->
</body>
</html>