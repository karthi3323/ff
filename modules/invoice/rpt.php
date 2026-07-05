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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice <?php echo htmlspecialchars($invoice['invoice_no']); ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/img/favicon.png">
    <style>
        /* CRITICAL: Force single page layout */
        @page {
            size: A4;
            margin: 10mm 15mm 10mm 15mm; /* Top, Right, Bottom, Left */
        }
        
        @media print {
            /* Reset everything for print */
            html, body {
                width: 210mm !important;
                height: 297mm !important;
                margin: 0 !important;
                padding: 0 !important;
                overflow: hidden !important;
                background: white !important;
            }
            
            /* Single page container */
            .invoice-page {
                width: 180mm !important; /* 210mm - 15mm left - 15mm right */
                height: 277mm !important; /* 297mm - 10mm top - 10mm bottom */
                margin: 0 auto !important;
                padding: 0 !important;
                position: relative !important;
                font-size: 9pt !important; /* Smaller font for print */
                line-height: 1.2 !important;
            }
            
            /* Hide non-essential elements */
            .no-print {
                display: none !important;
            }
            
            /* Scale down large elements */
            .logo {
                max-height: 60px !important;
            }
            
            .company-name {
                font-size: 14pt !important;
            }
            
            /* Compact table styling */
            .items-table {
                font-size: 7pt !important;
                margin-top: 5mm !important;
                margin-bottom: 5mm !important;
            }
            
            .items-table th,
            .items-table td {
                padding: 2px 3px !important;
                line-height: 1 !important;
            }
            
            /* Reduce spacing */
            .mb-10 { margin-bottom: 5px !important; }
            .mb-20 { margin-bottom: 10px !important; }
            .mt-10 { margin-top: 5px !important; }
            .mt-20 { margin-top: 10px !important; }
            .p-10 { padding: 5px !important; }
            
            /* Compact bottom section */
            .bottom-section {
                padding: 5mm !important;
                margin-top: 5mm !important;
            }
            
            /* Smaller text in footer */
            .footer-section {
                font-size: 8pt !important;
                margin-top: 5mm !important;
            }
            
            /* Force no page breaks */
            .invoice-page * {
                page-break-inside: avoid !important;
                page-break-before: avoid !important;
                page-break-after: avoid !important;
            }
        }
        
        /* SCREEN PREVIEW STYLES */
        @media screen {
            body {
                background: #f5f5f5;
                padding: 20px;
            }
            
            .invoice-page {
                width: 210mm;
                min-height: 297mm;
                background: white;
                margin: 0 auto;
                padding: 20mm;
                box-shadow: 0 0 20px rgba(0,0,0,0.1);
                font-size: 10pt;
            }
            
            .controls {
                text-align: center;
                margin-bottom: 20px;
                padding: 15px;
                background: white;
                border-radius: 5px;
                box-shadow: 0 0 10px rgba(0,0,0,0.1);
            }
            
            .btn {
                padding: 10px 20px;
                background: #007bff;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                margin: 0 5px;
            }
            
            .btn:hover {
                background: #0056b3;
            }
            
            .warning {
                background: #fff3cd;
                border: 1px solid #ffeaa7;
                padding: 10px;
                border-radius: 4px;
                margin: 10px auto;
                max-width: 210mm;
                text-align: center;
            }
        }
        
        /* COMPACT LAYOUT STYLES - APPLIED EVERYWHERE */
        .compact-mode {
            /* Reduce all spacing */
            * {
                margin-top: 0 !important;
                margin-bottom: 0 !important;
            }
            
            /* Compact table */
            .items-table {
                border-collapse: collapse;
                width: 100%;
                font-size: 8pt;
            }
            
            .items-table th,
            .items-table td {
                padding: 2px 4px;
                height: 18px;
                border: 1px solid #000;
            }
            
            /* Adjust column widths for better fit */
            .items-table th:nth-child(1) { width: 5%; } /* Sl */
            .items-table th:nth-child(2) { width: 12%; } /* Carton Range */
            .items-table th:nth-child(3) { width: 20%; } /* Product Name */
            .items-table th:nth-child(4) { width: 7%; }  /* Carton */
            .items-table th:nth-child(5) { width: 10%; } /* Carton Contents */
            .items-table th:nth-child(6) { width: 10%; } /* Qty */
            .items-table th:nth-child(7) { width: 8%; }  /* Rate */
            .items-table th:nth-child(8) { width: 8%; }  /* Per */
            .items-table th:nth-child(9) { width: 10%; } /* Amount */
            
            /* Reduce header size */
            .invoice-header {
                margin-bottom: 10px !important;
                padding-bottom: 5px !important;
            }
            
            /* Smaller logos */
            .logo {
                max-height: 50px;
                width: auto;
            }
            
            /* Compact address section */
            .address-section {
                padding: 8px !important;
                margin-bottom: 10px !important;
            }
            
            .party-address {
                font-size: 9pt;
                line-height: 1.1;
            }
            
            /* Compact bottom section */
            .section-columns {
                gap: 10px !important;
            }
            
            .detail-row {
                margin-bottom: 3px !important;
                font-size: 9pt !important;
            }
            
            .amount-row {
                margin-bottom: 2px !important;
                font-size: 9pt !important;
            }
            
            /* Smaller footer */
            .footer-section {
                margin-top: 10px !important;
                font-size: 8pt !important;
            }
            
            .bank-info {
                font-size: 7.5pt !important;
                line-height: 1.1 !important;
            }
            
            /* Reduce whitespace everywhere */
            .invoice-title {
                margin: 8px 0 !important;
                padding: 4px 0 !important;
                font-size: 14pt !important;
            }
            
            .amount-in-words {
                padding: 5px !important;
                margin: 8px 0 !important;
                font-size: 9pt !important;
            }
        }
    </style>
</head>
<body class="compact-mode">
    <!-- Warning message for preview -->
    <div class="warning no-print">
        <strong>Note:</strong> This invoice is optimized for single-page printing. 
        If content overflows, use "Shrink to fit" in your browser's print settings.
    </div>
    
    <!-- Controls -->
    <div class="controls no-print">
        <button class="btn" onclick="optimizeAndPrint()">
            <i class="fas fa-print"></i> Optimized Print
        </button>
        <button class="btn" onclick="window.location.href='<?php echo BASE_URL; ?>modules/invoice/view.php'">
            <i class="fas fa-arrow-left"></i> Back
        </button>
        <button class="btn" onclick="fitToPage()">
            <i class="fas fa-compress"></i> Fit to Page
        </button>
    </div>
    
    <!-- Single Page Invoice Container -->
    <div class="invoice-page" id="invoiceContent">
        <!-- HEADER - Made more compact -->
        <div class="invoice-header">
            <div style="flex: 1;">
                <img src="<?php echo ASSETS_URL; ?>/img/logo.png" alt="Logo" class="logo">
            </div>
            <div style="flex: 2; text-align: center;">
                <div style="font-size: 16pt; font-weight: bold; margin-bottom: 3px;">
                    <?php echo htmlspecialchars(strtoupper($invoice['company_name'])); ?>
                </div>
                <div style="font-size: 9pt; margin-bottom: 2px;">
                    <?php echo htmlspecialchars($invoice['company_address']); ?>
                </div>
                <div style="font-size: 8pt; font-weight: bold;">
                    GSTIN: <?php echo htmlspecialchars($invoice['company_gst']); ?>
                </div>
                <div style="font-size: 7pt;">
                    LICENCE: <?php echo htmlspecialchars($invoice['comp_lic1']); ?> | 
                    <?php echo htmlspecialchars($invoice['comp_lic2']); ?>
                </div>
            </div>
            <div style="flex: 1; text-align: right;">
                <img src="<?php echo ASSETS_URL; ?>/img/trademark.png" alt="NEERI" class="logo">
            </div>
        </div>
        
        <!-- TITLE -->
        <div style="text-align: center; font-weight: bold; font-size: 14pt; 
                    border-top: 2px solid #000; border-bottom: 2px solid #000;
                    padding: 4px 0; margin: 5px 0 10px 0;">
            TAX INVOICE
        </div>
        
        <!-- ADDRESS SECTION - Compact -->
        <div style="border: 1px solid #000; padding: 8px; margin-bottom: 10px; 
                    display: flex; font-size: 9pt;">
            <div style="flex: 2; padding-right: 10px; border-right: 1px solid #ccc;">
                <div style="font-weight: bold; margin-bottom: 4px;">Party's Name and Address</div>
                <div style="font-weight: bold;"><?php echo htmlspecialchars(strtoupper($invoice['party_name'])); ?></div>
                <div><?php echo htmlspecialchars(strtoupper($invoice['address'])); ?></div>
                <div>Place: <?php echo htmlspecialchars(strtoupper($invoice['city'])); ?></div>
                <div>State: <?php echo htmlspecialchars(strtoupper($invoice['state'])); ?></div>
                <div style="font-weight: bold;">GSTIN: <?php echo htmlspecialchars(strtoupper($invoice['party_gst'])); ?></div>
            </div>
            <div style="flex: 1; padding-left: 10px;">
                <div style="font-weight: bold;">Invoice No: <?php echo htmlspecialchars($invoice['invoice_no']); ?></div>
                <div style="font-weight: bold;">Date: <?php echo date('d-m-Y', strtotime($invoice['invoice_date'])); ?></div>
            </div>
        </div>
        
        <!-- ITEMS TABLE - Ultra compact -->
        <table class="items-table" style="font-size: 8pt; border-collapse: collapse; width: 100%;">
            <thead>
                <tr style="background: #f0f0f0;">
                    <th rowspan="2" style="width: 5%; padding: 2px; border: 1px solid #000;">Sl</th>
                    <th rowspan="2" style="width: 12%; padding: 2px; border: 1px solid #000;">Carton Range</th>
                    <th rowspan="2" style="width: 20%; padding: 2px; border: 1px solid #000;">Product Name</th>
                    <th colspan="2" style="padding: 2px; border: 1px solid #000; text-align: center;">Package Details</th>
                    <th rowspan="2" style="width: 10%; padding: 2px; border: 1px solid #000;">Qty</th>
                    <th rowspan="2" style="width: 8%; padding: 2px; border: 1px solid #000;">Rate</th>
                    <th rowspan="2" style="width: 8%; padding: 2px; border: 1px solid #000;">Per</th>
                    <th rowspan="2" style="width: 10%; padding: 2px; border: 1px solid #000;">Amount</th>
                </tr>
                <tr style="background: #f0f0f0;">
                    <th style="width: 7%; padding: 2px; border: 1px solid #000;">Carton</th>
                    <th style="width: 10%; padding: 2px; border: 1px solid #000;">Contents</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $sl = 1;
                $max_rows = 12; // Reduced from 15 to fit on one page
                foreach ($items as $item):
                    if ($sl > $max_rows) break; // Limit rows
                    $range = '-';
                    $ctn = $item['cartons'] ?? 0;
                    if (!empty($item['carton_from']) && !empty($item['carton_to'])) {
                        $range = ($ctn == 1) ? $item['carton_from'] : $item['carton_from'] . " - " . $item['carton_to'];
                    }
                    $qty_words = preg_replace('/[^a-zA-Z\s]+/', '', $item['carton_contents'] ?? '');
                ?>
                <tr>
                    <td style="padding: 2px; border: 1px solid #000; text-align: center;"><?php echo $sl++; ?></td>
                    <td style="padding: 2px; border: 1px solid #000; text-align: center;"><?php echo htmlspecialchars($range); ?></td>
                    <td style="padding: 2px; border: 1px solid #000; text-align: left;"><?php echo htmlspecialchars($item['product_name']); ?></td>
                    <td style="padding: 2px; border: 1px solid #000; text-align: center;"><?php echo htmlspecialchars($ctn); ?></td>
                    <td style="padding: 2px; border: 1px solid #000; text-align: center;"><?php echo htmlspecialchars($item['carton_contents'] ?? '-'); ?></td>
                    <td style="padding: 2px; border: 1px solid #000; text-align: center;"><?php echo htmlspecialchars($item['qty']); ?></td>
                    <td style="padding: 2px; border: 1px solid #000; text-align: right;"><?php echo number_format($item['rate'], 2); ?></td>
                    <td style="padding: 2px; border: 1px solid #000; text-align: center;"><?php echo htmlspecialchars($item['per_box_pieces'] ?? '-'); ?></td>
                    <td style="padding: 2px; border: 1px solid #000; text-align: right; font-weight: bold;"><?php echo number_format($item['total_amount'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
                
                <!-- Fill remaining rows -->
                <?php for($i = $sl; $i <= $max_rows; $i++): ?>
                <tr>
                    <td style="padding: 2px; border: 1px solid #000; text-align: center;"><?php echo $i; ?></td>
                    <td style="padding: 2px; border: 1px solid #000;">&nbsp;</td>
                    <td style="padding: 2px; border: 1px solid #000;">&nbsp;</td>
                    <td style="padding: 2px; border: 1px solid #000;">&nbsp;</td>
                    <td style="padding: 2px; border: 1px solid #000;">&nbsp;</td>
                    <td style="padding: 2px; border: 1px solid #000;">&nbsp;</td>
                    <td style="padding: 2px; border: 1px solid #000;">&nbsp;</td>
                    <td style="padding: 2px; border: 1px solid #000;">&nbsp;</td>
                    <td style="padding: 2px; border: 1px solid #000;">&nbsp;</td>
                </tr>
                <?php endfor; ?>
            </tbody>
        </table>
        
        <!-- BOTTOM SECTION - Compact -->
        <div style="border: 1px solid #000; padding: 8px; margin-top: 8px; font-size: 9pt;">
            <div style="display: flex; gap: 15px;">
                <!-- Left Column -->
                <div style="flex: 1;">
                    <div><strong>HSN Code:</strong> <?php echo htmlspecialchars($hsn_cd); ?></div>
                    <div><strong>Total Cartons:</strong> <?php echo htmlspecialchars($total_cartons); ?></div>
                    <div><strong>Despatched From:</strong> <?php echo htmlspecialchars($invoice['dispatch_from']); ?></div>
                    <div><strong>Despatched To:</strong> <?php echo htmlspecialchars($invoice['dispatch_through']); ?></div>
                    <div><strong>Vehicle No:</strong> <?php echo htmlspecialchars($invoice['vehicle_no']); ?></div>
                    <div><strong>Transport:</strong> <?php echo htmlspecialchars($invoice['transport_name']); ?></div>
                    <div><strong>Transport GSTIN:</strong> <?php echo htmlspecialchars($invoice['transport_gst']); ?></div>
                    <div><strong>E-way Bill:</strong> <?php echo htmlspecialchars($invoice['eway_bill_no']); ?></div>
                </div>
                
                <!-- Right Column - Amounts -->
                <div style="flex: 1; text-align: right;">
                    <div style="display: flex; justify-content: space-between;">
                        <span>GOODS VALUE:</span>
                        <span style="font-weight: bold;"><?php echo number_format($taxable_amount, 2); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>LESS DISC <?php echo $disc_percent; ?>%:</span>
                        <span style="font-weight: bold;"><?php echo number_format($discount_amount, 2); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>TAXABLE VALUE:</span>
                        <span style="font-weight: bold;"><?php echo number_format($val, 2); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>IGST <?php echo $igst_percent; ?>%:</span>
                        <span style="font-weight: bold;"><?php echo number_format($igst_amount, 2); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>SGST <?php echo $sgst_percent; ?>%:</span>
                        <span style="font-weight: bold;"><?php echo number_format($sgst_amount, 2); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>CGST <?php echo $cgst_percent; ?>%:</span>
                        <span style="font-weight: bold;"><?php echo number_format($cgst_amount, 2); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between;">
                        <span>ROUND OFF:</span>
                        <span style="font-weight: bold;"><?php echo number_format($round_off, 2); ?></span>
                    </div>
                    <div style="display: flex; justify-content: space-between; margin-top: 5px; 
                                border-top: 1px solid #000; padding-top: 5px; font-weight: bold;">
                        <span>NET AMOUNT:</span>
                        <span style="font-size: 11pt;"><?php echo number_format($net_amount, 2); ?></span>
                    </div>
                </div>
            </div>
            
            <!-- Amount in Words - Compact -->
            <div style="border: 1px solid #000; padding: 5px; margin: 8px 0; 
                        text-align: center; font-size: 9pt; font-style: italic;">
                <strong>Amount in Words:</strong> <?php echo htmlspecialchars($amount_in_words); ?>
            </div>
            
            <!-- Footer - Compact -->
            <div style="display: flex; gap: 15px; margin-top: 10px; font-size: 8pt;">
                <div style="flex: 2;">
                    <div><strong>Declaration:</strong> We declare that this invoice shows the actual price of the goods and that all particulars are true and correct.</div>
                    <div style="margin-top: 8px;">
                        <strong>Bank Details:</strong><br>
                        Account 1: Jeyalakshmi Priya Sparklers Factory<br>
                        PNB | A/C: 4199002100015343 | IFSC: PUNB0419900<br>
                        Account 2: TMB | A/C: 003700050900353 | IFSC: TMBL0000037
                    </div>
                </div>
                <div style="flex: 1; text-align: right; border-left: 1px solid #ccc; padding-left: 10px;">
                    <div>For <?php echo htmlspecialchars($invoice['company_name']); ?></div>
                    <div style="margin-top: 30px; border-top: 1px solid #000; padding-top: 5px;">
                        <strong>Authorized Signatory</strong>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Page Info -->
        <div style="text-align: center; font-size: 7pt; margin-top: 10px; color: #666;">
            Page 1 of 1 | Generated on: <?php echo date('d-m-Y h:i A'); ?>
        </div>
    </div>
    
    <script>
        // Function to optimize layout before printing
        function optimizeAndPrint() {
            // Apply compact styles
            document.body.classList.add('compact-mode');
            
            // Reduce font sizes further
            const invoiceContent = document.getElementById('invoiceContent');
            invoiceContent.style.fontSize = '9pt';
            invoiceContent.style.lineHeight = '1.1';
            
            // Hide overflow
            document.body.style.overflow = 'hidden';
            
            // Print with delay to ensure styles are applied
            setTimeout(() => {
                window.print();
                
                // Restore after printing
                setTimeout(() => {
                    document.body.classList.remove('compact-mode');
                    document.body.style.overflow = '';
                }, 500);
            }, 100);
        }
        
        // Function to fit content to one page
        function fitToPage() {
            // Scale down everything
            const scale = 0.85; // 85% scale
            const invoiceContent = document.getElementById('invoiceContent');
            
            invoiceContent.style.transform = `scale(${scale})`;
            invoiceContent.style.transformOrigin = 'top left';
            invoiceContent.style.width = `${210 / scale}mm`;
            
            // Also reduce font sizes
            const allElements = invoiceContent.querySelectorAll('*');
            allElements.forEach(el => {
                const currentSize = parseFloat(window.getComputedStyle(el).fontSize);
                if (currentSize > 8) {
                    el.style.fontSize = `${currentSize * 0.9}pt`;
                }
            });
            
            alert('Content scaled to fit one page. Click "Optimized Print" to print.');
        }
        
        // Keyboard shortcut
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                optimizeAndPrint();
            }
        });
        
        // Auto-optimize for print
        window.addEventListener('beforeprint', function() {
            document.body.classList.add('compact-mode');
            const tables = document.querySelectorAll('table');
            tables.forEach(table => {
                table.style.fontSize = '7pt';
            });
        });
        
        // Restore after print
        window.addEventListener('afterprint', function() {
            document.body.classList.remove('compact-mode');
        });
        
        // Browser print settings helper
        function showPrintTips() {
            alert('For best results:\n\n' +
                  '1. Click "Optimized Print"\n' +
                  '2. In print dialog:\n' +
                  '   - Set Margins to "Minimum"\n' +
                  '   - Check "Shrink to fit"\n' +
                  '   - Scale: 85%\n' +
                  '3. Print Preview should show 1 page');
        }
    </script>
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</body>
</html>