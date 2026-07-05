<?php
    session_start();
    require_once('../../includes/fpdf/fpdf.php');
    require_once "../../config/database.php";
    require_once "../../config/constants.php";
    require_once "../../config/pdf_exp.php";
    require_once "../../includes/auth.php";
    
    $database = new Database();
    $db = $database->getConnection();

    if(!isset($_GET['id'])) {
        header("Location: " . BASE_URL . "modules/invoice/view.php");
        exit();
    }
    //  echo date('d-m-Y h:i:s A');
    $invoice_id = $_GET['id'];

    class InvoicePDF extends PDF {
        function Footer() {
            // Position at 1.5 cm from bottom
            $this->SetY(-10);
            $this->SetFont('Arial','I',8);
            
            // Page number (centered)
            $this->Cell(0,10,'Page '.$this->PageNo().' of {nb}',0,0,'C');
            
            // Date on right side
            $this->SetY(-10);
            $this->SetX(140);
            // $this->Cell(0,10,'Generated on: '.date('d-m-Y h:i A'),0,0,'R');
        }
    }

    $pdf=new InvoicePDF();
    $pdf->AliasNbPages();
    $pdf->__construct('P','mm','A4');
    $pdf->AddPage();   
    $pdf->SetAutoPageBreak(false);
    $row_height=4.5; 
    $rh = 5;

    $master = $db->query("SELECT * FROM ff_sch.master WHERE is_active = true LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $hsn_cd = $master['hsn_code'];
    $sgst = $master['sgst'];
    $cgst = $master['cgst'];
    $igst = $master['igst'];

    
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

    // If company info not present (because of the JOIN ON 1=1 or other), try fetching first company row
    if (empty($invoice['company_name'])) {
        try {
            $cstmt = $db->prepare("SELECT name AS company_name, address AS company_address, city AS company_city, state AS company_state, gst_no AS company_gst, phone AS company_phone, email AS company_email, lic_no1 AS comp_lic1, lic_no2 AS comp_lic2 FROM ff_sch.companies LIMIT 1");
            $cstmt->execute();
            $company_fallback = $cstmt->fetch(PDO::FETCH_ASSOC);
            if ($company_fallback) {
                // merge fallback only where invoice doesn't have it
                foreach ($company_fallback as $k => $v) {
                    if (empty($invoice[$k])) $invoice[$k] = $v;
                }
            }
        } catch (Exception $e) {
            // ignore fallback failure
        }
    }

    $fiscal_year = $db->query("SELECT * FROM ff_sch.fiscal_years WHERE is_active = true LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $curr_year = $fiscal_year ? date('Y', strtotime($fiscal_year['start_date'])) : 1;
    
    $pdf->setY(15);
    $pdf->Image(ASSETS_URL . '/img/logo.png', 15, 15, 30, 30);
    $pdf->Image(ASSETS_URL . '/img/trademark.png', 158, 12, 38, 38);
    $pdf->SetFont('Arial','B',9);
    $pdf->SetX(10);
    $pdf->Cell(0,$row_height,'TAX INVOICE',0,1,'C',0);
    $pdf->SetFont('Times','B',12);
    
    $pdf->Cell(50,3.2,'',0,1,'C',0);
    $pdf->SetX(10);
    $pdf->MultiCell(0,$row_height+2,strtoupper($invoice['company_name']),0,'C',0);
    $pdf->Cell(55,1,'',0,1,'L',0);
    $pdf->SetFont('Arial','B',9);
    $pdf->SetX(10);
    $pdf->MultiCell(0,$row_height,$invoice['company_address'],0,'C',0);
    $pdf->Cell(50,3.2,'',0,1,'C',0);
    $pdf->SetFont('Arial','B',10);
    $pdf->SetX(15);
    $pdf->Cell(0,$row_height,'GSTIN: '.$invoice['company_gst'],0,0,'L',0);
    $pdf->SetFont('Arial','B',7.5);
    $pdf->SetX(150);
    $pdf->Cell(0,$row_height,'LICENCE NO: '.$invoice['comp_lic1'],0,1,'L',0);
    $pdf->SetX(150);
    $pdf->Cell(0,$row_height,'LICENCE NO: '.$invoice['comp_lic2'],0,1,'L',0);
    
    $pdf->Cell(190,35,'',1,0,'L',0);
    $pdf->setY(15);
    
    $pdf->Cell(50,50,'',0,1,'L',0);
    $pdf->SetFont('Arial','B',10);
    $pdf->SetX(10);
    $pdf->Cell(100,$row_height,"Party's Name and Address",0,1,'L',0);
    $pdf->SetFont('Arial','',9);
    $pdf->SetX(15);
    $pdf->Cell(0,$row_height,strtoupper($invoice['party_name']),0,1,'L',0);
    $y_axis = $pdf->getY();
    $pdf->SetX(15);
    $pdf->MultiCell(100,$row_height,strtoupper($invoice['p_address']),0,'L',0);
    $pdf->SetX(15);
    $pdf->Cell(0,$row_height,'Place: '.strtoupper($invoice['p_place']),0,1,'L',0);
    $pdf->SetX(15);
    $pdf->Cell(0,$row_height,'State: '.strtoupper($invoice['p_state']),0,1,'L',0);
    $pdf->SetX(15);
    $pdf->SetFont('Arial','B',9);
    $pdf->Cell(0,$row_height,'GSTIN: '.strtoupper($invoice['p_gst']),0,0,'L',0);
    
    $pdf->Line(110, 62.5, 110, 97.5); //Invoice line

    $pdf->SetFont('Arial','B',10);
    $pdf->setY($y_axis);
    $pdf->SetX(125);
    $pdf->Cell(40, $row_height, 'Invoice No: '.$invoice['invoice_no'],0,1,'L',0);
    
    $pdf->SetX(125);
    $pdf->Cell(40, $row_height, 'Invoice Date: '.date('d-m-Y',strtotime($invoice['invoice_date'])),0,1,'L',0);
    $pdf->Cell(50,9,'',0,1,'L',0);
    
    $pdf->SetFillColor(215,215,215);
    $pdf->SetFont('Arial','B',8);

    function drawTableHeader($pdf) {
        // Column widths
        $w_sl = 8;
        $w_range = 25;
        $w_product = 45;

        $w_pkg_total = 33;
        $w_carton = 10;
        $w_contents = 23;

        $w_qty = 21;
        $w_rate = 15;
        $w_per = 20;
        $w_amount = 23;

        $pdf->SetFillColor(215,215,215);
        $pdf->SetFont('Arial','B',8);

        // First row
        $pdf->SetX(10);
        $pdf->Cell($w_sl, 10, 'Sl', 1, 0, 'C', 1);
        $pdf->Cell($w_range, 10, 'Carton From - To', 1, 0, 'C', 1);
        $pdf->Cell($w_product, 10, 'Product Name', 1, 0, 'C', 1);

        $x = $pdf->GetX();
        $y = $pdf->GetY();

        $pdf->Cell($w_pkg_total, 5, 'Package Details', 1, 0, 'C', 1);

        $pdf->Cell($w_qty, 10, 'Qty', 1, 0, 'C', 1);
        $pdf->Cell($w_rate, 10, 'Rate', 1, 0, 'C', 1);
        $pdf->Cell($w_per, 10, 'Per', 1, 0, 'C', 1);
        $pdf->Cell($w_amount, 10, 'Amount', 1, 1, 'C', 1);

        // Second row (sub header)
        $pdf->SetXY($x, $y + 5);
        $pdf->Cell($w_carton, 5, 'Carton', 1, 0, 'C', 1);
        $pdf->Cell($w_contents, 5, 'Carton Contents', 1, 1, 'C', 1);

        return [
            $w_sl, $w_range, $w_product,
            $w_carton, $w_contents,
            $w_qty, $w_rate, $w_per, $w_amount
        ];
    }

    $pdf->Ln(5);
    list($w_sl, $w_range, $w_product, $w_carton, $w_contents, 
     $w_qty, $w_rate, $w_per, $w_amount) = drawTableHeader($pdf);

    // ==========================================
    // FIXED PRODUCT AREA HEIGHT (15 ROWS MAX)
    // ==========================================
    $product_area_height = 15 * $rh; // Height for exactly 15 rows
    
    // Store current Y position after header
    $table_start_y = $pdf->GetY();
    
    // ==========================================
    // ITEM LOOP WITH FIXED AREA
    // ==========================================
    $pdf->SetFont('Arial','',9);
    $sl = 1;
    $row_per_page = 15;
    $count = 0;
    $current_y = $table_start_y;

    foreach ($items as $item) {
        if ($count == $row_per_page) {
            // If we reach 15 items, create new page
            $pdf->Line(10, $current_y, 200, $current_y);
            $pdf->Cell(0,8,'---Contunious---',0,1,'R',0);
            $pdf->AddPage();
            $pdf->setY(15);
            $pdf->Image(ASSETS_URL . '/img/logo.png', 15, 15, 30, 30);
            $pdf->Image(ASSETS_URL . '/img/trademark.png', 158, 12, 38, 38);
            $pdf->SetFont('Arial','B',9);
            $pdf->SetX(10);
            $pdf->Cell(0,$row_height,'TAX INVOICE',0,1,'C',0);
            $pdf->SetFont('Times','B',12);
            
            $pdf->Cell(50,3.2,'',0,1,'C',0);
            $pdf->SetX(10);
            $pdf->MultiCell(0,$row_height+2,strtoupper($invoice['company_name']),0,'C',0);
            $pdf->Cell(55,1,'',0,1,'L',0);
            $pdf->SetFont('Arial','B',9);
            $pdf->SetX(10);
            $pdf->MultiCell(0,$row_height,$invoice['company_address'],0,'C',0);
            $pdf->Cell(50,3.2,'',0,1,'C',0);
            $pdf->SetFont('Arial','B',8);
            $pdf->SetX(15);
            $pdf->Cell(0,$row_height,'GSTIN: '.$invoice['company_gst'],0,0,'L',0);
            $pdf->SetFont('Arial','B',7.5);
            $pdf->SetX(150);
            $pdf->Cell(0,$row_height,'LICENCE NO: '.$invoice['comp_lic1'],0,1,'L',0);
            $pdf->SetX(150);
            $pdf->Cell(0,$row_height,'LICENCE NO: '.$invoice['comp_lic2'],0,1,'L',0);
            
            $pdf->Cell(190,35,'',1,0,'L',0);
            $pdf->setY(15);
            
            $pdf->Cell(50,50,'',0,1,'L',0);
            $pdf->SetFont('Arial','B',10);
            $pdf->SetX(10);
            $pdf->Cell(100,$row_height,"Party's Name and Address",0,1,'L',0);
            $pdf->SetFont('Arial','',9);
            $pdf->SetX(15);
            $pdf->Cell(0,$row_height,strtoupper($invoice['party_name']),0,1,'L',0);
            $y_axis = $pdf->getY();
            $pdf->SetX(15);
            $pdf->MultiCell(100,$row_height,strtoupper($invoice['address']),0,'L',0);
            $pdf->SetX(15);
            $pdf->Cell(0,$row_height,'Place: '.strtoupper($invoice['city']),0,1,'L',0);
            $pdf->SetX(15);
            $pdf->Cell(0,$row_height,'State: '.strtoupper($invoice['state']),0,1,'L',0);
            $pdf->SetX(15);
            $pdf->SetFont('Arial','B',9);
            $pdf->Cell(0,$row_height,'GSTIN: '.strtoupper($invoice['party_gst']),0,0,'L',0);
            
            $pdf->Line(110, 62.5, 110, 97.5);

            $pdf->SetFont('Arial','B',10);
            $pdf->setY($y_axis);
            $pdf->SetX(125);
            $pdf->Cell(40, $row_height, 'Invoice No: '.$invoice['invoice_no'],0,1,'L',0);
            
            $pdf->SetX(125);
            $pdf->Cell(40, $row_height, 'Invoice Date: '.date('d-m-Y',strtotime($invoice['invoice_date'])),0,1,'L',0);
            $pdf->Cell(50,9,'',0,1,'L',0);
            list($w_sl, $w_range, $w_product, $w_carton, $w_contents,
                $w_qty, $w_rate, $w_per, $w_amount) = drawTableHeader($pdf);
            $count = 0;
            // $sl = 1; // Reset serial number for new page
            $table_start_y = $pdf->GetY();
            $current_y = $table_start_y;
            $pdf->SetFont('Arial','',8);
        }

        $rate = (float)($item['rate'] ?? 0);
        $qty_val = (float)($item['qty'] ?? 0);
        $amount = $item['total_amount'];

        $product = $item['product_name'] ?: '-';

        // Carton Range
        $ctn = $item['cartons'] ?? 0;
        $range = '-';
        if (!empty($item['carton_from']) && !empty($item['carton_to'])) {
            $range = ($ctn == 1) ? $item['carton_from'] : $item['carton_from'] . " - " . $item['carton_to'];
        }
        $qty_words = preg_replace('/[^a-zA-Z\s]+/','',$item['carton_contents']);
        // Print Row
        $pdf->SetY($current_y);
        $pdf->SetX(10);
        $pdf->Cell($w_sl, $rh, $sl, 'LR', 0, 'C');
        $pdf->Cell($w_range, $rh, $range, 'LR', 0, 'C');
        $pdf->Cell($w_product, $rh, $product, 'LR', 0, 'L');
        $pdf->Cell($w_carton, $rh, $ctn, 'LR', 0, 'C');
        $pdf->Cell($w_contents, $rh, $item['carton_contents'] ?? '-', 'LR', 0, 'C');

        $pdf->Cell($w_qty, $rh, $qty_val.' '.$qty_words, 'LR', 0, 'C');
        $pdf->Cell($w_rate, $rh, number_format($rate,2), 'LR', 0, 'R');
        $pdf->Cell($w_per, $rh, $item['per_box_pieces'] ?? '-', 'LR', 0, 'C');
        $pdf->Cell($w_amount, $rh, number_format($amount,2), 'LR', 1, 'R');

        $current_y += $rh;
        $sl++;
        $count++;
    }

    // ==========================================
    // FILL EMPTY ROWS IF LESS THAN 15 ITEMS
    // ==========================================
    if ($count < $row_per_page) {
        $empty_rows = $row_per_page - $count;
        for ($i = 0; $i < $empty_rows; $i++) {
            $pdf->SetY($current_y);
            $pdf->SetX(10);
            $pdf->Cell($w_sl, $rh, '', 'LR', 0, 'C');
            $pdf->Cell($w_range, $rh, '', 'LR', 0, 'C');
            $pdf->Cell($w_product, $rh, '', 'LR', 0, 'L');
            $pdf->Cell($w_carton, $rh, '', 'LR', 0, 'C');
            $pdf->Cell($w_contents, $rh, '', 'LR', 0, 'C');
            $pdf->Cell($w_qty, $rh, '', 'LR', 0, 'R');
            $pdf->Cell($w_rate, $rh, '', 'LR', 0, 'R');
            $pdf->Cell($w_per, $rh, '', 'LR', 0, 'C');
            $pdf->Cell($w_amount, $rh, '', 'LR', 1, 'R');
            $current_y += $rh;
        }
    }

    // ==========================================
    // BOTTOM BORDER OF TABLE
    // ==========================================
    $pdf->SetY($table_start_y + $product_area_height);
    $pdf->SetX(10);
    $pdf->Cell(
        $w_sl + $w_range + $w_product + $w_carton + $w_contents +
        $w_qty + $w_rate + $w_per + $w_amount,
        0,
        '',
        'T',
        1
    );

    // ==========================================
    // STATIC BOTTOM SECTION AT FIXED POSITION
    // ==========================================
    $bottom_section_start_y = $table_start_y + $product_area_height;

    // BOTTOM BIG BOX
    $boxX = 10;
    $boxY = $bottom_section_start_y;
    $boxW = 190;
    $boxH = 106;

    $pdf->Rect($boxX, $boxY, $boxW, $boxH);

    // LEFT COLUMN START POSITION
    $leftX = $boxX + 2;
    $leftY = $boxY + 3;

    // RIGHT COLUMN START POSITION
    $rightX = $boxX + 105;
    $rightY = $boxY + 3;

    // LINE HEIGHT
    $lh = 6;

    // ==========================================
    // LEFT SIDE DETAILS
    // ==========================================
    $pdf->SetXY($leftX, $leftY);
    $pdf->SetFont('Arial','',9);

    $cartons_query = "SELECT sum(ii.cartons) tot_cartons
                FROM ff_sch.invoice_items ii
                WHERE ii.invoice_id = :invoice_id";
    $cartons_count_stmt = $db->prepare($cartons_query);
    $cartons_count_stmt->bindParam(':invoice_id', $inv_num);
    $cartons_count_stmt->execute();
    $total_cartons = $cartons_count_stmt->fetchColumn();

    $leftLabels = [
        "HSN Code"         => $hsn_cd ?? "3604",
        "Total Cartons"    => $total_cartons,
        "Despatched From"  => $invoice['dispatch_from'],
        "Despatched To"    => $invoice['dispatch_through'],
        "Vehicle No"       => $invoice['vehicle_no'],
        "Transport Name"   => $invoice['transport_name'],
        "Transport GSTIN"  => $invoice['transport_gst'],
        "E-way Bill"       => $invoice['eway_bill_no']
    ];

    foreach ($leftLabels as $label => $value) {
        $pdf->SetX($leftX);
        $pdf->SetFont('Arial','',9);
        $pdf->Cell(35, $lh, $label, 0, 0, 'L');
        $pdf->Cell(3, $lh, ":", 0, 0, 'L');
        $pdf->SetFont('Arial','B',9);
        $pdf->Cell(45, $lh, $value, 0, 1, 'L');
    }

    // ==========================================
    // RIGHT SIDE GST VALUES
    // ==========================================
    $pdf->SetXY($rightX, $rightY);
    $pdf->SetFont('Arial','',9);
    $disc_percent = $invoice['discount_value'];
    $discount_amount = $invoice['discount'];
    $taxable_amount = $invoice['taxable_amount'];
    $igst_percent = $invoice['igst_percent'];
    $sgst_percent = $invoice['sgst_percent'];
    $cgst_percent = $invoice['cgst_percent'];
    $igst_amount = $invoice['igst_amount'];
    $sgst_amount = $invoice['sgst_amount'];
    $cgst_amount = $invoice['cgst_amount'];
    $round_off = $invoice['round_off'];
    $net_amount = $invoice['net_amount'];

    $val = $taxable_amount - $discount_amount;

    if($sgst_amount == 0 && $cgst_amount == 0){
        $cgst_percent = 0;
        $sgst_percent = 0;
    }

    if($igst_amount == 0){
        $igst_percent = 0;
    }
    $rightLabels = [
        "GOODS VALUE"          => number_format($taxable_amount,2),
        "LESS DISC : $disc_percent%" => number_format($discount_amount,2),
        "TAXABLE VALUE"        => number_format($val,2),
        "IGST : $igst_percent%" => number_format($igst_amount,2),
        "SGST : $sgst_percent%" => number_format($sgst_amount,2),
        "CGST : $cgst_percent%" => number_format($cgst_amount,2),
        "ROUND OFF"            => number_format($round_off,2),
        "NET AMOUNT"           => number_format($net_amount,2)
    ];

    foreach ($rightLabels as $label => $value) {
        $pdf->SetFont('Arial','',9);
        $pdf->SetX($rightX);
        $pdf->Cell(50, $lh, $label, 0, 0, 'L');
        $pdf->SetFont('Arial','B',9);
        $pdf->Cell(35, $lh, $value, 0, 1, 'R');
        $pdf->SetFont('Arial','',9);
    }

    $amount_in_words = int_to_words(number_format(str_replace(',','',$net_amount),2,'.',''))." Rupees Only";

    // ==========================================
    // AMOUNT IN WORDS (FULL WIDTH BAR)
    // ==========================================
    $pdf->SetY($boxY + 50);
    $pdf->SetFont('Arial','BI',10);
    $pdf->Cell($boxW, 8, "Total Amount In Words : " . $amount_in_words, 1, 1, 'C');

    // ==========================================
    // DECLARATION
    // ==========================================
    $declarationY = $boxY + 59;
    $pdf->SetY($declarationY);
    $pdf->SetX(10);
    $pdf->SetFont('Arial','B',9);
    $pdf->Cell(10, 5, "Declaration :", 0, 1, 'L',0);
    $pdf->SetX(15);
    $pdf->MultiCell(125, 4.5, "We declare that this invoice shows the actual price of the goods and that all particulars are true and correct.", 0, 'L');
    $pdf->Cell(55,1,'',0,1,'L',0);

    // ==========================================
    // THREE COLUMNS LAYOUT
    // ==========================================
    $columnsY = $declarationY + 20;
    $col1Width = 65;  // Bank Account 1
    $col2Width = 65;  // Bank Account 2  
    $col3Width = 50;  // Signature

   // Calculate positions for the three vertical lines
    $line1X = 100;
    $line2X = 170;
    $line3X = 155;
    
    $bottomBoxEndY = $boxY + $boxH;

    // Draw the three vertical lines
    $pdf->Line($line1X, $boxY, $line1X, $declarationY-9);
    $pdf->Line($line2X, $boxY, $line2X, $declarationY-9);
    $pdf->Line($line3X, $declarationY, $line3X, $bottomBoxEndY);

    // COLUMN 1: First Bank Account
    $pdf->SetY($columnsY);
    $pdf->SetX(10);
    $pdf->SetFont('Arial','BI',9);
    $pdf->Cell(10, 5, "Company Bank Details:", 0, 1, 'L',0);
    $pdf->SetFont('Arial','I',8);
    $pdf->SetX(10);
    $pdf->cell(10,4.5, 'Account Name : Jeyalakshmi Priya Sparklers Factory', 0, 1, 'L',0);
    $pdf->SetX(10);
    $pdf->cell(10,4.5, 'Bank Name : Punjab National Bank', 0, 1, 'L',0);
    $pdf->SetX(10);
    $pdf->cell(10,4.5, 'Account Number : 4199002100015343', 0, 1, 'L',0);
    $pdf->SetX(10);
    $pdf->cell(10,4.5, 'Account Name : JIFSC Code : PUNB0419900', 0, 1, 'L',0);
    // $pdf->MultiCell($col1Width, 4.5,
    // "Account Name : Jeyalakshmi Priya Sparklers Factory
    // Bank Name : Punjab National Bank
    // Account Number : 4199002100015343
    // IFSC Code : PUNB0419900", 
    // 0, 'L');

    // COLUMN 2: Second Bank Account
    $pdf->SetY($columnsY+5);
    $pdf->SetX(10 + $col1Width);
    $pdf->SetFont('Arial','I',8);
    $pdf->Cell(10, 5, "", 0, 0, 'L',0);
    $pdf->cell(10,4.5, 'Account Name : Jeyalakshmi Priya Sparklers Factory', 0, 1, 'L',0);
    $pdf->SetX(20 + $col1Width);
    $pdf->cell(10,4.5, 'Bank Name : Tamilnadu Mercantile Bank', 0, 1, 'L',0);
    $pdf->SetX(20 + $col1Width);
    $pdf->cell(10,4.5, 'Account Number : 003700050900353', 0, 1, 'L',0);
    $pdf->SetX(20 + $col1Width);
    $pdf->cell(10,4.5, 'Account Name : JIFSC Code : TMBL0000037', 0, 1, 'L',0);
    // $pdf->MultiCell($col2Width, 4.5,
    // "Account Name : Jeyalakshmi Priya Sparklers Factory
    // Bank Name : Tamilnadu Mercantile Bank
    // Account Number : 003700050900353
    // IFSC Code : TMBL0000037", 
    // 0, 'L');

    // COLUMN 3: Signature
    $pdf->SetY($declarationY);
    $pdf->SetX(25 + $col1Width + $col2Width);
    $pdf->SetFont('Arial','',8.5);
    $pdf->MultiCell($col3Width, 4.5,
    "For Jeyalakshmi Priya Sparklers Factory & Fireworks",
    0, 'L');
    $pdf->SetY($columnsY+21);
    $pdf->SetX(60 + $col1Width + $col2Width);
    $pdf->Cell(10, 4, "Authorized Signatory", 0, 1, 'R',0);

    // Bottom border
    // $pdf->Line(10, $columnsY + 32, 200, $columnsY + 32);

    // Position at 1.5 cm from bottom
    $pdf->SetY(-10);
    $pdf->SetFont('Arial','I',8);
    
    // Page number (centered)
    $pdf->Cell(0,10,'Page '.$pdf->PageNo().' of {nb}',0,0,'C');
    
    // Date on right side
    $pdf->SetY(-10);
    $pdf->SetX(140);
    // $pdf->Cell(0,10,'Generated on: '.date('d-m-Y h:i A'),0,0,'R');

    // Output PDF (safe filename)
    $filename = 'Invoice_' . preg_replace('/[^A-Za-z0-9\-]/', '_', ($invoice['invoice_no'] ));
    $pdf->Output('I', $filename . '.pdf');
?>