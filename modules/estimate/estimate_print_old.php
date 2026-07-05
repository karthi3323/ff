<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";
require_once('../../includes/fpdf/fpdf.php');

$database = new Database();
$db = $database->getConnection();

if(!isset($_GET['id'])) {
    header("Location: view.php");
    exit();
}

$estimate_id = $_GET['id'];

// Fetch estimate details
$query = "SELECT e.*, p.name as party_name, p.address_line1, p.address_line2, p.city, p.state, 
                 p.gst_no as party_gst, c.name as company_name, c.address as company_address,
                 c.city as company_city, c.state as company_state, c.gst_no as company_gst,
                 c.phone as company_phone, c.email as company_email
          FROM ff_sch.estimate e 
          LEFT JOIN ff_sch.parties p ON e.party_id = p.id 
          CROSS JOIN (SELECT * FROM ff_sch.companies LIMIT 1) c
          WHERE e.id = :id 
          LIMIT 1";

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $estimate_id);
$stmt->execute();
$estimate = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$estimate) {
    header("Location: view.php");
    exit();
}

// Fetch estimate items
$items_query = "SELECT ei.*, p.name as product_name 
                FROM ff_sch.estimate_items ei 
                LEFT JOIN ff_sch.products p ON ei.product_id = p.id 
                WHERE ei.estimate_id = :estimate_id 
                ORDER BY ei.id ASC";
$items_stmt = $db->prepare($items_query);
$items_stmt->bindParam(':estimate_id', $estimate_id);
$items_stmt->execute();
$items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

class EstimatePDF extends FPDF {
    private $company_name = '';
    private $company_address = '';
    private $company_city = '';
    private $company_state = '';
    private $company_gst = '';
    private $company_phone = '';
    private $company_email = '';
    private $copy_name = '';
    private $primary_color;
    
    function setCompanyInfo($name, $address, $city, $state, $gst, $phone, $email) {
        $this->company_name = $name ?: (defined('COMPANY_NAME') ? COMPANY_NAME : 'Company Name');
        $this->company_address = $address ?: '';
        $this->company_city = $city ?: '';
        $this->company_state = $state ?: '';
        $this->company_gst = $gst ?: '';
        $this->company_phone = $phone ?: '';
        $this->company_email = $email ?: '';
        
        $this->primary_color = array(37, 99, 235); // Blue 600
    }
    
    function setCopyType($copy_name) {
        $this->copy_name = $copy_name;
    }

    function Header() {
        // Page Border
        $this->SetDrawColor(200);
        $this->SetLineWidth(0.5);
        $this->Rect(5, 5, 200, 287);

        // Company Details
        $this->SetFont('Arial', 'B', 14);
        $this->SetTextColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->Cell(100, 7, $this->company_name, 0, 1, 'L');
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(0);
        $this->MultiCell(100, 5, $this->company_address, 0, 'L');
        if ($this->company_phone) $this->Cell(100, 5, 'Phone: ' . $this->company_phone, 0, 1, 'L');
        if ($this->company_email) $this->Cell(100, 5, 'Email: ' . $this->company_email, 0, 1, 'L');
        if ($this->company_gst) $this->Cell(100, 5, 'GSTIN: ' . $this->company_gst, 0, 1, 'L');

        // Estimate Title
        $this->SetXY(110, 10);
        $this->SetFont('Arial', 'B', 20);
        $this->SetTextColor(50);
        $this->Cell(95, 12, 'ESTIMATE', 0, 1, 'R');

        // Copy Name
        if (!empty($this->copy_name)) {
            $this->SetXY(110, 22);
            $this->SetFont('Arial', 'B', 10);
            $this->SetTextColor(150);
            $this->Cell(95, 5, strtoupper($this->copy_name), 0, 1, 'R');
        }
        
        // Line separator
        $this->SetY(45);
        $this->SetDrawColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->SetLineWidth(0.5);
        $this->Line(5, $this->GetY(), 205, $this->GetY());
        $this->Ln(2);
    }
    
    function Footer() {
        $this->SetY(-25);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 8, 'For ' . $this->company_name, 0, 1, 'R');
        $this->Ln(5);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 5, 'Authorised Signatory', 0, 1, 'R');

        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(150);
        $this->Cell(0, 5, 'This is a computer generated estimate', 0, 0, 'C');
        $this->Cell(0, 5, 'Page ' . $this->PageNo() . '/{nb}', 0, 1, 'R');
    }
    
    function addBillingDetails($estimate) {
        $this->SetY(50);
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(100);
        $this->Cell(95, 7, 'BILLED TO:', 0, 0, 'L');
        $this->Cell(95, 7, 'ESTIMATE DETAILS:', 0, 1, 'L');
        $this->SetLineWidth(0.2);
        $this->Line($this->GetX(), $this->GetY(), $this->GetX() + 95, $this->GetY());
        $this->Line($this->GetX() + 100, $this->GetY(), $this->GetX() + 195, $this->GetY());
        $this->Ln(2);

        $y_start_col = $this->GetY();

        // --- Left Column: Party Details ---
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(0);
        $this->Cell(95, 6, $estimate['party_name'] ?: '-', 0, 1);
        $this->SetFont('Arial', '', 9);
        $this->MultiCell(90, 5, $estimate['p_address'] ?? '', 0, 'L');
        if ($estimate['party_gst']) {
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(95, 5, 'GSTIN: ' . $estimate['party_gst'], 0, 1);
        }
        $y_left_end = $this->GetY();

        // --- Right Column: Estimate Details ---
        $this->SetY($y_start_col);
        $this->SetX(110);
        $this->SetFont('Arial', '', 9);
        $this->Cell(30, 6, 'Estimate No:', 0, 0);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(65, 6, $estimate['estimate_no'], 0, 1);

        $this->SetX(110);
        $this->SetFont('Arial', '', 9);
        $this->Cell(30, 6, 'Date:', 0, 0);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(65, 6, ($estimate['estimate_date'] ? date('d-m-Y', strtotime($estimate['estimate_date'])) : '-'), 0, 1);

        $this->SetX(110);
        $this->SetFont('Arial', '', 9);
        $this->Cell(30, 6, 'Agent Name:', 0, 0);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(65, 6, $estimate['agent_name'], 0, 1);

        $this->SetX(110);
        $this->SetFont('Arial', '', 9);
        $this->Cell(30, 6, 'HSN Code:', 0, 0);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(65, 6, '3604', 0, 1); // Hardcoded in original
        $y_right_end = $this->GetY();

        // --- Set Y to continue ---
        $this->SetY(max($y_left_end, $y_right_end) + 5);
    }
    
    function addItemsTableHeader() {
        $this->SetFillColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->SetTextColor(255);
        $this->SetDrawColor(220);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(10, 8, 'S.No', 1, 0, 'C', true);
        $this->Cell(85, 8, 'PRODUCT', 1, 0, 'L', true);
        $this->Cell(20, 8, 'C/S', 1, 0, 'C', true);
        $this->Cell(15, 8, 'QTY', 1, 0, 'C', true);
        $this->Cell(25, 8, 'RATE', 1, 0, 'R', true);
        $this->Cell(15, 8, 'PER', 1, 0, 'C', true);
        $this->Cell(25, 8, 'AMOUNT', 1, 1, 'R', true);
        $this->SetTextColor(0);
    }
    
    function addItemsTable($items, $estimate) {
        $this->addItemsTableHeader();
        
        $this->SetFont('Arial','',8);
        $this->SetFillColor(245, 245, 245);
        $fill = false;
        $sl = 1;
        
        foreach ($items as $item) {
            // Check for page break
            if ($this->GetY() > 240) { // Approx position for footer
                $this->AddPage();
                $this->addBillingDetails($estimate);
                $this->addItemsTableHeader();
                $this->SetFont('Arial','',8);
            }

            $this->Cell(10, 7, $sl++, 'LR', 0, 'C', $fill);
            $this->Cell(85, 7, $item['product_name'] ?? '-', 'LR', 0, 'L', $fill);
            $this->Cell(20, 7, '(' . $item['cases'] . ' X ' . $item['counts'] . ')', 'LR', 0, 'C', $fill);
            $this->Cell(15, 7, $item['qty'], 'LR', 0, 'C', $fill);
            $this->Cell(25, 7, number_format($item['rate'], 2), 'LR', 0, 'R', $fill);
            $this->Cell(15, 7, $item['per'], 'LR', 0, 'C', $fill);
            $this->Cell(25, 7, number_format($item['amount'], 2), 'LR', 1, 'R', $fill);
            $fill = !$fill;
        }
        // Bottom border
        $this->Cell(195, 0, '', 'T', 1);
    }
    
    function addTotalsSection($estimate, $total_cases) {
        $this->Ln(1);
        $y_pos = $this->GetY();

        // Left side: Dispatch details & Amount in words
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(95, 6, 'DISPATCH DETAILS', 0, 1, 'L');
        $this->SetFont('Arial', '', 9);
        $this->Cell(30, 5, 'No. of Cases:', 0, 0);
        $this->Cell(65, 5, $total_cases, 0, 1);
        $this->Cell(30, 5, 'Dispatch To:', 0, 0);
        $this->Cell(65, 5, $estimate['dispatch_through'] ?: '-', 0, 1);
        $this->Cell(30, 5, 'Transport:', 0, 0);
        $this->Cell(65, 5, $estimate['transport_name'] ?: '-', 0, 1);
        $this->Cell(30, 5, 'LR No.:', 0, 0);
        $this->Cell(65, 5, $estimate['eway_bill_no'] ?: '-', 0, 1);
        $this->Ln(5);

        $this->SetFont('Arial', 'B', 9);
        $this->Cell(95, 6, 'AMOUNT IN WORDS:', 0, 1, 'L');
        $this->SetFont('Arial', '', 9);
        $words = convertNumberToWords((float)$estimate['net_amount']) . ' Only';
        $this->MultiCell(95, 5, $words, 0, 'L');

        // Right side: Totals
        $this->SetY($y_pos);
        $this->SetX(110);
        
        $this->SetFont('Arial', '', 10);
        $cell_width = 45;
        $value_width = 50;

        $this->Cell($cell_width, 6, 'Goods Value:', 0, 0, 'R');
        $this->Cell($value_width, 6, number_format($estimate['goods_value'], 2), 0, 1, 'R');

        if ((float)$estimate['discount_percent'] > 0) {
            $discount_amt = (float)$estimate['goods_value'] * ((float)$estimate['discount_percent'] / 100);
            $this->SetX(110);
            $this->Cell($cell_width, 6, 'Discount (' . (float)$estimate['discount_percent'] . '%):', 0, 0, 'R');
            $this->Cell($value_width, 6, '- ' . number_format($discount_amt, 2), 0, 1, 'R');
        }

        $this->SetX(110);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell($cell_width, 6, 'Sub Total:', 0, 0, 'R');
        $this->Cell($value_width, 6, number_format($estimate['sub_total'], 2), 0, 1, 'R');
        $this->SetFont('Arial', '', 10);

        $charges = [
            'packing_percent' => 'Packing Charges', 'mahamai_percent' => 'Mahamai',
            'insurance_percent' => 'Insurance', 'commission_percent' => 'Commission'
        ];
        foreach ($charges as $key => $label) {
            if ((float)$estimate[$key] > 0) {
                $amt = (float)$estimate['sub_total'] * ((float)$estimate[$key] / 100);
                $this->SetX(110);
                $this->Cell($cell_width, 6, $label . ' (' . (float)$estimate[$key] . '%):', 0, 0, 'R');
                $this->Cell($value_width, 6, number_format($amt, 2), 0, 1, 'R');
            }
        }

        $this->SetX(110);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell($cell_width, 6, 'Taxable Amount:', 0, 0, 'R');
        $this->Cell($value_width, 6, number_format($estimate['taxable_amount'], 2), 0, 1, 'R');
        $this->SetFont('Arial', '', 10);

        $taxes = ['sgst_percent' => 'SGST', 'cgst_percent' => 'CGST', 'igst_percent' => 'IGST'];
        foreach ($taxes as $key => $label) {
            if ((float)$estimate[$key] > 0) {
                $amt = (float)$estimate['taxable_amount'] * ((float)$estimate[$key] / 100);
                $this->SetX(110);
                $this->Cell($cell_width, 6, $label . ' (' . (float)$estimate[$key] . '%):', 0, 0, 'R');
                $this->Cell($value_width, 6, number_format($amt, 2), 0, 1, 'R');
            }
        }

        $this->SetX(110);
        $this->Line(155, $this->GetY(), 205, $this->GetY());
        $this->Ln(1);

        $this->SetX(110);
        $this->SetFont('Arial', 'B', 12);
        $this->Cell($cell_width, 8, 'NET AMOUNT:', 0, 0, 'R');
        $this->SetFillColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->SetTextColor(255);
        $this->Cell($value_width, 8, 'Rs. ' . number_format($estimate['net_amount'], 2), 0, 1, 'R', true);
        $this->SetTextColor(0);
    }
}

$copies_to_print = [''];
if (isset($_GET['copy'])) {
    if ($_GET['copy'] === 'All') {
        $copies_to_print = ['Original', 'Duplicate', 'Transport', 'Supplier'];
    } else {
        $copies_to_print = [$_GET['copy']];
    }
}

$pdf = new EstimatePDF('P', 'mm', 'A4');

$pdf->setCompanyInfo(
    $estimate['company_name'] ?? (defined('COMPANY_NAME') ? COMPANY_NAME : 'Company Name'),
    $estimate['company_address'] ?? '',
    $estimate['company_city'] ?? '',
    $estimate['company_state'] ?? '',
    $estimate['company_gst'] ?? '',
    $estimate['company_phone'] ?? '',
    $estimate['company_email'] ?? ''
);

$pdf->AliasNbPages();

// Calculate total cases
$total_cases = 0;
foreach($items as $item) {
    $total_cases += (int)$item['cases'];
}

foreach ($copies_to_print as $copy_name) {
    $pdf->setCopyType($copy_name);
    $pdf->AddPage();
    $pdf->addBillingDetails($estimate);
    $pdf->addItemsTable($items, $estimate);
    $pdf->addTotalsSection($estimate, $total_cases);
}

$filename = 'Estimate_' . preg_replace('/[^A-Za-z0-9\-]/', '_', $estimate['estimate_no']);
$pdf->Output('I', $filename . '.pdf');


function convertNumberToWords($number) {
    $number = number_format((float)$number, 2, '.', '');
    $parts = explode('.', $number);
    $rupees = (int)$parts[0];
    $paise = isset($parts[1]) ? (int)$parts[1] : 0;

    if ($rupees == 0) $rupees_words = "Zero";
    else $rupees_words = _convertIntegerToIndianWords($rupees);

    if ($paise > 0) {
        $paise_words = _convertIntegerToIndianWords($paise) . ' Paise';
        return $rupees_words . ' and ' . $paise_words;
    }
    return $rupees_words;
}

function _convertIntegerToIndianWords($num) {
    $ones = array('', 'One','Two','Three','Four','Five','Six','Seven','Eight','Nine','Ten',
                  'Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen','Seventeen','Eighteen','Nineteen');
    $tens = array('', '', 'Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety');

    $result = '';

    if ($num >= 10000000) {
        $crores = (int)($num / 10000000);
        $result .= _convertIntegerToIndianWords($crores) . ' Crore ';
        $num %= 10000000;
    }
    if ($num >= 100000) {
        $lakhs = (int)($num / 100000);
        $result .= _convertIntegerToIndianWords($lakhs) . ' Lakh ';
        $num %= 100000;
    }
    if ($num >= 1000) {
        $thousands = (int)($num / 1000);
        $result .= _convertIntegerToIndianWords($thousands) . ' Thousand ';
        $num %= 1000;
    }
    if ($num >= 100) {
        $hundreds = (int)($num / 100);
        $result .= $ones[$hundreds] . ' Hundred ';
        $num %= 100;
    }
    if ($num > 0) {
        if ($num < 20) $result .= $ones[$num] . ' ';
        else {
            $t = (int)($num / 10);
            $o = $num % 10;
            $result .= $tens[$t] . ($o ? ' ' . $ones[$o] : '') . ' ';
        }
    }
    return trim($result);
}
?>