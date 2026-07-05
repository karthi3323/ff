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
    private $secondary_color;
    private $accent_color;
    
    function setCompanyInfo($name, $address, $city, $state, $gst, $phone, $email) {
        $this->company_name = $name ?: (defined('COMPANY_NAME') ? COMPANY_NAME : 'Company Name');
        $this->company_address = $address ?: '';
        $this->company_city = $city ?: '';
        $this->company_state = $state ?: '';
        $this->company_gst = $gst ?: '';
        $this->company_phone = $phone ?: '';
        $this->company_email = $email ?: '';
        
        // Modern color scheme (Blue/Gray theme)
        $this->primary_color = array(37, 99, 235);    // Blue 600
        $this->secondary_color = array(107, 114, 128); // Gray 500
        $this->accent_color = array(16, 185, 129);    // Emerald 500
    }
    
    function SetColor($color) {
        $this->SetTextColor($color[0], $color[1], $color[2]);
    }
    
    function setCopyType($copy_name) {
        $this->copy_name = $copy_name;
    }

    function Header() {
        // Page Border (5mm margin on all sides of an A4 page)
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.5);
        $this->Rect(5, 5, 200, 287);
        
        $this->SetDrawColor(229, 231, 235);
        $this->SetLineWidth(0.2); // Reset to default line width for other elements

        if (!empty($this->copy_name)) {
            $this->SetY(8);
            $this->SetFont('Arial', 'B', 10);
            $this->SetTextColor(100, 100, 100);
            $this->Cell(0, 5, strtoupper($this->copy_name), 0, 1, 'R');
            $this->SetTextColor(0, 0, 0);
        }

        // Title
        $this->SetY(8);
        $this->SetFont('Arial', 'B', 12);
        $this->SetTextColor(0,0,0);
        $this->Cell(0, 6, 'ESTIMATE', 0, 1, 'C');
        $this->Ln(2);
    }
    
    function Footer() {
        $this->SetY(-10);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(107, 114, 128);
        $this->Cell(0, 5, 'Page ' . $this->PageNo() . ' of {nb}', 0, 0, 'C');
        // $this->Ln(3);
        /* $this->Cell(0, 5, 'This is a computer generated estimate', 0, 1, 'C');
        $this->Cell(0, 5, 'Generated on ' . date('d/m/Y H:i:s'), 0, 1, 'C'); */
    }
    
    function addPartyDetails($party_name, $address, $city, $state, $party_gst) {
        /* $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->Cell(95, 8, 'To :', 0, 1); */
        $this->SetTextColor(0,0,0);
        
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(95, 6, $party_name ?: '-', 0, 1);
        
        $this->SetFont('Arial', '', 10);
        if ($address) {
            $this->MultiCell(90, 5, $address, 0, 'L');
        }
        $this->Cell(95, 5, trim(($city ?: '') . ($city && $state ? ', ' : '') . ($state ?: '')), 0, 1);
        
        if ($party_gst) {
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(95, 5, 'GSTIN: ' . $party_gst, 0, 1);
        }
    }

    function addEstimateHeader($estimate_no, $estimate_date, $dispatch_from, $dispatch_through, $agent_name, $startY) {
        $this->SetXY(110, $startY);
        $this->Rect(5, 16, 200, 26);
        // Left column: estimate details
        /* $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->Cell(80, 8, 'ESTIMATE DETAILS', 0, 1); */
        $this->SetTextColor(0,0,0);
        
        $this->SetFont('Arial', '', 10);
        $this->SetX(110);
        $this->Cell(35, 6, 'Estimate No:', 0, 0);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(45, 6, $estimate_no, 0, 1);
        
        $this->SetFont('Arial', '', 10);
        $this->SetX(110);
        $this->Cell(35, 6, 'Date:', 0, 0);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(45, 6, ($estimate_date ? date('d-m-Y', strtotime($estimate_date)) : '-'), 0, 1);
        
        /* if($dispatch_from) {
            $this->SetFont('Arial', '', 9);
            $this->SetX(110);
            $this->Cell(35, 6, 'Dispatch From:', 0, 0);
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(45, 6, $dispatch_from, 0, 1);
        }
        
        if($dispatch_through) {
            $this->SetFont('Arial', '', 9);
            $this->SetX(110);
            $this->Cell(35, 6, 'Dispatch To:', 0, 0);
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(45, 6, $dispatch_through, 0, 1);
        } */

        // if($agent_name) {
            $this->SetFont('Arial', '', 10);
            $this->SetX(110);
            $this->Cell(35, 6, 'Agent Name:', 0, 0);
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(45, 6, $agent_name, 0, 1);
        // }
        $this->SetFont('Arial', '', 10);
        $this->SetX(110);
        $this->Cell(35, 6, 'HSN Code', 0, 0);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(45, 8, 3604, 0, 1);
    }
    
    function addItemsTableHeader() {
        $col_sr = 10;
        $col_desc = 65;
        $col_cs = 26;
        $col_qty = 15;
        $col_rate = 22;
        $col_per = 22;
        // $col_disc = 15;
        $col_amount = 40;
        
        $header_height = 10;
        
        $this->SetFillColor(255,255,255);
        $this->SetTextColor(0,0,0);
        $this->SetFont('Arial', '', 9.5);
        
        $this->Cell($col_sr, $header_height, 'S.No.', 1, 0, 'C', 0);
        $this->Cell($col_desc, $header_height, 'PRODUCT', 1, 0, 'C', true);
        $this->Cell($col_cs, $header_height, 'C/S', 1, 0, 'C', true);
        $this->Cell($col_qty, $header_height, 'QTY', 1, 0, 'C', true);
        $this->Cell($col_rate, $header_height, 'RATE (' . CURRENCY . ')', 1, 0, 'C', true);
        $this->Cell($col_per, $header_height, 'PER', 1, 0, 'C', true);
        // $this->Cell($col_disc, $header_height, 'DISC.', 1, 0, 'C', true);
        $this->Cell($col_amount, $header_height, 'AMOUNT (' . CURRENCY . ')', 1, 1, 'C', 0);
    }
    
    function addItemsTable($items, $estimate) {
        $col_sr = 10; $col_desc = 65; $col_cs = 26;
        $col_qty = 15; $col_rate = 22; $col_per = 22; /* $col_disc = 15; */ $col_amount = 40;
        $total_width = $col_sr + $col_desc + $col_cs + $col_qty + $col_rate + $col_per /* + $col_disc */ + $col_amount;
        
        $this->SetFont('Arial','',8);
        $this->setX(5);
        $this->addItemsTableHeader();
        
        $fill = false;
        $row_per_page = 28;
        $row_height = 5;
        $count = 0;
        $sl = 1;
        
        $table_start_y = $this->GetY();
        $current_y = $table_start_y;
        $product_area_height = $row_per_page * $row_height;
        
        foreach ($items as $index => $item) {
            if ($count == $row_per_page) {
                $this->SetY($table_start_y + $product_area_height);
                $this->SetX(5);
                $this->Cell($total_width, 0, '', 'T', 1);
                
                $this->AddPage();
                
                $startY = $this->GetY();
                $this->addPartyDetails(
                    $estimate['party_name'],
                    trim(($estimate['address_line1'] ?? '') . ' ' . ($estimate['address_line2'] ?? '')),
                    $estimate['p_place'],
                    $estimate['p_state'],
                    $estimate['p_gst']
                );
                $endYLeft = $this->GetY();
                
                $this->addEstimateHeader(
                    $estimate['estimate_no'],
                    $estimate['estimate_date'],
                    $estimate['dispatch_from'],
                    $estimate['dispatch_through'],
                    $estimate['agent_name'],
                    $startY
                );
                $endYRight = $this->GetY();
                $this->SetY(max($endYLeft, $endYRight));
                // $this->Ln(2);
                
                // $this->SetDrawColor(229, 231, 235);
                // $this->Line(10, $this->GetY(), 200, $this->GetY());
                // $this->Ln(5);
                
                $this->SetFont('Arial','',8);
                $this->setX(5);
                $this->addItemsTableHeader();
                $table_start_y = $this->GetY();
                $current_y = $table_start_y;
                $count = 0;
                $fill = false;
            }
            
            $product_name = $item['product_name'] ?? '-';
            if ($this->GetStringWidth($product_name) > $col_desc - 2) {
                $product_name = substr($product_name, 0, 32) . '...';
            }
            
            $this->SetY($current_y);
            $this->SetX(5);
            
            $this->Cell($col_sr, $row_height, $index + 1, 'LR', 0, 'C', 0);
            $this->Cell($col_desc, $row_height, $product_name, 'LR', 0, 'L', $fill);
            $this->Cell($col_cs, $row_height, '(' . $item['cases'] . ' X ' . $item['counts'] . ')', 'LR', 0, 'C', $fill);
            $this->Cell($col_qty, $row_height, $item['qty'], 'LR', 0, 'C', $fill);
            $this->Cell($col_rate, $row_height, number_format($item['rate'], 2), 'LR', 0, 'R', $fill);
            $this->Cell($col_per, $row_height, $item['per'], 'LR', 0, 'C', $fill);
            // $this->Cell($col_disc, $row_height, $item['discount_eligible'], 'LR', 0, 'C', $fill);
            $this->SetFont('Arial', 'B', 9.5);
            $this->Cell($col_amount, $row_height, number_format($item['amount'], 2), 'LR', 1, 'R', 0);
            $this->SetFont('Arial', '', 9.5);
            
            $fill = !$fill;
            $count++;
            $sl++;
            $current_y += $row_height;
        }
        
        if ($count < $row_per_page) {
            $empty_rows = $row_per_page - $count;
            for ($i = 0; $i < $empty_rows; $i++) {
                $this->SetY($current_y);
                $this->SetX(5);
                $this->Cell($col_sr, $row_height, '', 'LR', 0, 'C', 0);
                $this->Cell($col_desc, $row_height, '', 'LR', 0, 'L', $fill);
                $this->Cell($col_cs, $row_height, '', 'LR', 0, 'C', $fill);
                $this->Cell($col_qty, $row_height, '', 'LR', 0, 'C', $fill);
                $this->Cell($col_rate, $row_height, '', 'LR', 0, 'R', $fill);
                $this->Cell($col_per, $row_height, '', 'LR', 0, 'C', $fill);
                // $this->Cell($col_disc, $row_height, '', 'LR', 0, 'C', $fill);
                $this->Cell($col_amount, $row_height, '', 'LR', 1, 'R', 0);
                $fill = !$fill;
                $current_y += $row_height;
            }
        }
        
        $this->SetY($table_start_y + $product_area_height);
        $this->SetX(5);
        $this->Cell($total_width, 0, '', 'T', 1);
        // $this->Ln(1);
    }
    
    function addTotalsSection($estimate, $total_cases) {
        $startY = $this->GetY();
        
        $summary_height = 10;
        $summ_line = 6;
        // RIGHT SIDE SUMMARY
        $this->SetFillColor(249, 250, 251);
        $this->SetDrawColor(0,0,0);
        // $this->SetLineWidth(0.5);
        
        $start_x = 111;
        $cell_width = 49;
        $value_width = 45;
        
        $this->SetX($start_x);
        /* $this->SetFont('Arial', 'B', 11);
        $this->SetColor($this->primary_color);
        $this->Cell($cell_width + $value_width, 8, 'ESTIMATE SUMMARY', 1, 1, 'C', true); */
        
        $this->SetColor(array(0,0,0));
        $this->SetFont('Arial', '', 10);
        
        // Helpers
        $goods_value = (float)$estimate['goods_value'];
        $discount_pct = (float)$estimate['discount_percent'];
        $discount_amt = $goods_value * ($discount_pct / 100);
        $sub_total = (float)$estimate['sub_total'];
        $packing_amt = $sub_total * ((float)$estimate['packing_percent'] / 100);
        $mahamai_amt = $sub_total * ((float)$estimate['mahamai_percent'] / 100);
        $insurance_amt = $sub_total * ((float)$estimate['insurance_percent'] / 100);
        $commission_amt = $sub_total * ((float)$estimate['commission_percent'] / 100);
        $taxable_amt = (float)$estimate['taxable_amount'];
        $sgst_amt = $taxable_amt * ((float)$estimate['sgst_percent'] / 100);
        $cgst_amt = $taxable_amt * ((float)$estimate['cgst_percent'] / 100);
        $igst_amt = $taxable_amt * ((float)$estimate['igst_percent'] / 100);
        
        $this->SetX($start_x);
        $this->Cell($cell_width, $summ_line, 'Goods Value:', 'LR', 0, 'L');
        $this->SetFont('Arial', 'B', 10);
        $this->Cell($value_width, $summ_line, number_format($goods_value, 2), 'LR', 1, 'R');
        $this->SetFont('Arial', '', 10);
        
        if($discount_pct > 0) {
            $this->SetX($start_x);
            $this->Cell($cell_width, $summ_line, 'Discount (' . $discount_pct . '%):', 'LR', 0, 'L');
            $this->SetTextColor(239, 68, 68);
            $this->SetFont('Arial', 'B', 10);
            $this->Cell($value_width, $summ_line, '- ' . number_format($discount_amt, 2), 'LR', 1, 'R');
            $this->SetTextColor(0, 0, 0);
        }
        
        $this->SetX($start_x);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell($cell_width, $summ_line, 'Sub Total:', 'LR', 0, 'L');
        $this->Cell($value_width, $summ_line, number_format($sub_total, 2), 'LR', 1, 'R');
        $this->SetFont('Arial', '', 10);

        if($estimate['packing_percent'] > 0) {
            $this->SetX($start_x);
            $this->Cell($cell_width, $summ_line, 'Packing Charges (' . (float)$estimate['packing_percent'] . '%):', 'LR', 0, 'L');
            $this->SetFont('Arial', 'B', 10);
            $this->Cell($value_width, $summ_line, number_format($packing_amt, 2), 'LR', 1, 'R');
            $this->SetFont('Arial', '', 10);
        }
        if($estimate['mahamai_percent'] > 0) {
            $this->SetX($start_x);
            $this->Cell($cell_width, $summ_line, 'Mahamai (' . (float)$estimate['mahamai_percent'] . '%):', 'LR', 0, 'L');
            $this->SetFont('Arial', 'B', 10);
            $this->Cell($value_width, $summ_line, number_format($mahamai_amt, 2), 'LR', 1, 'R');
            $this->SetFont('Arial', '', 10);
        }
        if($estimate['insurance_percent'] > 0) {
            $this->SetX($start_x);
            $this->Cell($cell_width, $summ_line, 'Insurance (' . (float)$estimate['insurance_percent'] . '%):', 'LR', 0, 'L');
            $this->SetFont('Arial', 'B', 10);
            $this->Cell($value_width, $summ_line, number_format($insurance_amt, 2), 'LR', 1, 'R');
            $this->SetFont('Arial', '', 10);
        }
        if($estimate['commission_percent'] > 0) {
            $this->SetX($start_x);
            $this->Cell($cell_width, $summ_line, 'Commission (' . (float)$estimate['commission_percent'] . '%):', 'LR', 0, 'L');
            $this->SetFont('Arial', 'B', 10);
            $this->Cell($value_width, $summ_line, number_format($commission_amt, 2), 'LR', 1, 'R');
            $this->SetFont('Arial', '', 10);
        }
        
        $this->SetX($start_x);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell($cell_width, $summ_line, 'Taxable Amount:', 'LR', 0, 'L');
        $this->Cell($value_width, $summ_line, number_format($taxable_amt, 2), 'LR', 1, 'R');
        $this->SetFont('Arial', '', 10);

        if($estimate['sgst_percent'] > 0) {
            $this->SetX($start_x);
            $this->Cell($cell_width, $summ_line, 'SGST (' . (float)$estimate['sgst_percent'] . '%):', 'LR', 0, 'L');
            $this->SetFont('Arial', 'B', 10);
            $this->Cell($value_width, $summ_line, number_format($sgst_amt, 2), 'LR', 1, 'R');
            $this->SetFont('Arial', '', 10);
        }
        if($estimate['cgst_percent'] > 0) {
            $this->SetX($start_x);
            $this->Cell($cell_width, $summ_line, 'CGST (' . (float)$estimate['cgst_percent'] . '%):', 'LR', 0, 'L');
            $this->SetFont('Arial', 'B', 10);
            $this->Cell($value_width, $summ_line, number_format($cgst_amt, 2), 'LR', 1, 'R');
            $this->SetFont('Arial', '', 10);
        }
        if($estimate['igst_percent'] > 0) {
            $this->SetX($start_x);
            $this->Cell($cell_width, $summ_line, 'IGST (' . (float)$estimate['igst_percent'] . '%):', 'LR', 0, 'L');
            $this->SetFont('Arial', 'B', 10);
            $this->Cell($value_width, $summ_line, number_format($igst_amt, 2), 'LR', 1, 'R');
            $this->SetFont('Arial', '', 10);
        }
        
        $this->SetX($start_x);
        $this->Cell($cell_width + $value_width, 0, '', 'T', 1);
        
        $this->SetX($start_x);
        $this->SetFont('Arial', 'B', 11);
        // $this->SetColor($this->accent_color);
        $this->Cell($cell_width, $summary_height, 'NET AMOUNT:', 'BLR', 0, 'L');
        $this->Cell($value_width, $summary_height, CURRENCY . ' ' . number_format($estimate['net_amount'], 2), 'BR', 1, 'R');
        
        $endYRight = $this->GetY();
        
        // LEFT SIDE DETAILS
        $this->SetY($endYRight-32);
        $this->SetX(5);
        $this->Line(5, $this->GetY(), 111, $this->GetY());
        
        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor($this->primary_color[0], $this->primary_color[1], $this->primary_color[2]);
        $this->Cell(80, 8, 'DISPATCH DETAILS', 0, 1, 'L');
        $this->SetColor(array(0,0,0));
        
        $this->SetFont('Arial', '', 9);
        $this->Cell(30, 6, 'No. of Cases:', 0, 0);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(50, 6, $total_cases, 0, 1);
        
        $this->SetFont('Arial', '', 9);
        $this->Cell(30, 6, 'Dispatch To:', 0, 0);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(50, 6, $estimate['dispatch_through'] ?: '-', 0, 1);
        
        $this->SetFont('Arial', '', 9);
        $this->Cell(30, 6, 'Transport:', 0, 0);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(50, 6, $estimate['transport_name'] ?: '-', 0, 1);
        
        $this->SetFont('Arial', '', 9);
        $this->Cell(30, 6, 'LR No.:', 0, 0);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(50, 6, $estimate['eway_bill_no'] ?: '-', 0, 1);
        
        // $this->Line(5, $this->GetY(), 111, $this->GetY());
        $endYLeft = $this->GetY();
        
        $this->SetY(max($endYRight, $endYLeft));
        // $this->Ln(2);
    }
    
    function addAmountInWords($amount) {
        $this->SetFillColor(255,255,255);
        // $this->SetDrawColor(229,231,235);
        // $this->SetLineWidth(0.5);
        
        $words = convertNumberToWords($amount) . ' Only';
        $this->Line(5, $this->GetY(), 205, $this->GetY());
        $this->setY($this->GetY() + 1);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(30, 8, 'In Words: '.$words, 0, 1, 'L', true);
        // $this->SetFont('Arial', '', 10);
        // $this->setX(27);
        // $this->Cell(0, 8, $words, 0, 1, 'L', true);
        $this->Line(5, $this->GetY(), 205, $this->GetY());
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

    $startY = $pdf->GetY();

    $pdf->addPartyDetails(
        $estimate['party_name'],
        trim(($estimate['address_line1'] ?? '') . ' ' . ($estimate['address_line2'] ?? '')),
        $estimate['p_place'],
        $estimate['p_state'],
        $estimate['p_gst']
    );

    $endYLeft = $pdf->GetY();

    $pdf->addEstimateHeader(
        $estimate['estimate_no'],
        $estimate['estimate_date'],
        $estimate['dispatch_from'],
        $estimate['dispatch_through'],
        $estimate['agent_name'],
        $startY
    );

    $endYRight = $pdf->GetY();
    $pdf->SetY(max($endYLeft, $endYRight));
    $pdf->SetX(5);
    $pdf->addItemsTable($items, $estimate);
    $pdf->addTotalsSection($estimate, $total_cases);
    $pdf->addAmountInWords((float)$estimate['net_amount']);
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