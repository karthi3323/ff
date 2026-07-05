<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";
require_once "../../includes/pdf_helper.php";

$database = new Database();
$db = $database->getConnection();

// Fetch all parties
$parties = $db->query("SELECT * FROM ff_sch.parties WHERE is_active = true ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get party statistics
$party_stats = [];
foreach($parties as $party) {
    $stats_query = "SELECT 
                    COUNT(*) as total_invoices,
                    SUM(i.net_amount) as total_sales,
                    MAX(i.invoice_date) as last_purchase
                   FROM ff_sch.invoices i 
                   WHERE i.party_id = :party_id";
    $stmt = $db->prepare($stats_query);
    $stmt->bindParam(':party_id', $party['id']);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $party_stats[$party['id']] = $stats;
}

// Get company details
$company = $db->query("SELECT * FROM ff_sch.companies LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$comp_name = preg_replace('/\s+/', ' ', trim($company['name']));
// Create PDF in Landscape mode
$pdf = new PDFHelper('Party Details Report', trim($comp_name), $company['address']);
$pdf->AliasNbPages();
$pdf->AddPage('L'); // Landscape orientation
$pdf->SetAutoPageBreak(true, 15);

// Modern color scheme
$primaryColor = array(41, 128, 185);   // Blue
$secondaryColor = array(52, 152, 219); // Light Blue
$accentColor = array(46, 204, 113);    // Green
$headerColor = array(44, 62, 80);      // Dark Blue
$lightGray = array(245, 245, 245);

// Report Header with modern styling
/* $pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 12, 'PARTY DETAILS REPORT', 0, 1, 'C', true);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, 'Generated on: ' . date('F j, Y g:i A'), 0, 1, 'C', true); */
$pdf->Ln(5);

// Reset text color
$pdf->SetTextColor(0, 0, 0);

// Summary Statistics with modern cards design
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor($headerColor[0], $headerColor[1], $headerColor[2]);
$pdf->Cell(0, 10, 'Party Summary', 0, 1);
$pdf->SetTextColor(0, 0, 0);

$total_parties = count($parties);
$gst_count = 0;
$new_this_month = 0;
$active_this_month = 0;
$current_month = date('Y-m');
$total_sales = 0;

foreach($parties as $party) {
    if(!empty($party['gst_no'])) $gst_count++;
    if(date('Y-m', strtotime($party['created_at'])) === $current_month) {
        $new_this_month++;
    }
    if(isset($party_stats[$party['id']]['last_purchase']) && 
       date('Y-m', strtotime($party_stats[$party['id']]['last_purchase'])) === $current_month) {
        $active_this_month++;
    }
    if(isset($party_stats[$party['id']]['total_sales'])) {
        $total_sales += $party_stats[$party['id']]['total_sales'];
    }
}

// Summary cards in a 2x2 grid
$pdf->SetFont('Arial', '', 10);
$cardWidth = 65;
$cardHeight = 25;
$spacing = 5;
$startX = $pdf->GetX();
$startY = $pdf->GetY();

// Card 1: Total Parties
$pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell($cardWidth, $cardHeight, '', 0, 0, 'C', true);
$pdf->SetXY($startX, $startY);
$pdf->Cell($cardWidth, 8, 'TOTAL PARTIES', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetX($startX);
$pdf->Cell($cardWidth, 10, number_format($total_parties), 0, 1, 'C');
$pdf->SetFont('Arial', '', 8);
$pdf->SetX($startX);
$pdf->Cell($cardWidth, 6, 'Active Accounts', 0, 1, 'C');

// Card 2: GST Registered
$pdf->SetXY($startX + $cardWidth + $spacing, $startY);
$pdf->SetFillColor($accentColor[0], $accentColor[1], $accentColor[2]);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell($cardWidth, $cardHeight, '', 0, 0, 'C', true);
$pdf->SetXY($startX + $cardWidth + $spacing, $startY);
$pdf->Cell($cardWidth, 8, 'GST REGISTERED', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetX($startX + $cardWidth + $spacing);
$pdf->Cell($cardWidth, 10, number_format($gst_count), 0, 1, 'C');
$pdf->SetFont('Arial', '', 8);
$pdf->SetX($startX + $cardWidth + $spacing);
$pdf->Cell($cardWidth, 6, number_format(($gst_count/$total_parties)*100, 1) . '% of total', 0, 1, 'C');

// Card 3: New This Month
$pdf->SetXY($startX, $startY + $cardHeight + $spacing);
$pdf->SetFillColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell($cardWidth, $cardHeight, '', 0, 0, 'C', true);
$pdf->SetXY($startX, $startY + $cardHeight + $spacing);
$pdf->Cell($cardWidth, 8, 'NEW THIS MONTH', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetX($startX);
$pdf->Cell($cardWidth, 10, number_format($new_this_month), 0, 1, 'C');
$pdf->SetFont('Arial', '', 8);
$pdf->SetX($startX);
$pdf->Cell($cardWidth, 6, date('F Y'), 0, 1, 'C');

// Card 4: Active This Month
$pdf->SetXY($startX + $cardWidth + $spacing, $startY + $cardHeight + $spacing);
$pdf->SetFillColor(155, 89, 182); // Purple
$pdf->SetFont('Arial', '', 10);
$pdf->Cell($cardWidth, $cardHeight, '', 0, 0, 'C', true);
$pdf->SetXY($startX + $cardWidth + $spacing, $startY + $cardHeight + $spacing);
$pdf->Cell($cardWidth, 8, 'ACTIVE THIS MONTH', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetX($startX + $cardWidth + $spacing);
$pdf->Cell($cardWidth, 10, number_format($active_this_month), 0, 1, 'C');
$pdf->SetFont('Arial', '', 8);
$pdf->SetX($startX + $cardWidth + $spacing);
$pdf->Cell($cardWidth, 6, number_format(($active_this_month/$total_parties)*100, 1) . '% engagement', 0, 1, 'C');

$pdf->SetXY($startX, $startY + (2 * $cardHeight) + (2 * $spacing));
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(10);

// Parties Table with modern styling
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor($headerColor[0], $headerColor[1], $headerColor[2]);
$pdf->Cell(0, 10, 'Party Details', 0, 1);

// Adjusted column widths for landscape
$headers = ['Party ID', 'Name', 'City', 'State', 'GST No', 'Invoices', 'Total Sales', 'Last Purchase'];
$widths = [20, 50, 60, 30, 30, 20, 30, 25];

$data = [];
foreach($parties as $party) {
    $stats = $party_stats[$party['id']] ?? ['total_invoices' => 0, 'total_sales' => 0, 'last_purchase' => null];
    
    // Format sales with color coding for high values
    $sales_amount = $stats['total_sales'] ?? 0;
    $sales_display = CURRENCY . ' ' . number_format($sales_amount, 2);
    
    // Format last purchase with recency indicator
    $last_purchase = 'Never';
    if ($stats['last_purchase']) {
        $last_purchase = date('d/m/Y', strtotime($stats['last_purchase']));
       /*  $days_ago = (time() - strtotime($stats['last_purchase'])) / (60 * 60 * 24);
        if ($days_ago <= 30) {
            $last_purchase .= ' 🟢'; // Green for recent (last 30 days)
        } elseif ($days_ago <= 90) {
            $last_purchase .= ' 🟡'; // Yellow for somewhat recent (31-90 days)
        } else {
            $last_purchase .= ' 🔴'; // Red for old (90+ days)
        } */
    }
    
    $data[] = [
        $party['party_id'],
        substr($party['name'], 0, 30),
        $party['city'] ?: '-',
        $party['state'] ?: '-',
        $party['gst_no'] ?: 'No GST',
        $stats['total_invoices'],
        $sales_display,
        $last_purchase
    ];
}

// Custom table with modern styling
$pdf->SetFillColor($lightGray[0], $lightGray[1], $lightGray[2]);
$pdf->SetTextColor($headerColor[0], $headerColor[1], $headerColor[2]);
$pdf->SetDrawColor(200, 200, 200);
$pdf->SetLineWidth(0.3);

// Table header
$pdf->SetFont('Arial', 'B', 9);
for($i = 0; $i < count($headers); $i++) {
    $pdf->Cell($widths[$i], 8, $headers[$i], 1, 0, 'L', true);
}
$pdf->Ln();

// Table data
$pdf->SetFont('Arial', '', 8);
$pdf->SetTextColor(0, 0, 0);
$fill = false;

/* foreach($data as $row) {
    // Alternate row background
    if($fill) {
        $pdf->SetFillColor(248, 248, 248);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }
    
    for($i = 0; $i < count($row); $i++) {
        $pdf->Cell($widths[$i], 6, $row[$i], 'LR', 0, 'C', $fill);
    }
    $pdf->Ln();
    $fill = !$fill;
} */

foreach($data as $row) {

    // Check if page break needed
    if ($pdf->GetY() + 6 > ($pdf->GetPageHeight() - 15)) {
        $pdf->AddPage('L');

        // Reprint table header after page break
        $pdf->SetFont('Arial', 'B', 9);
        $pdf->SetFillColor($lightGray[0], $lightGray[1], $lightGray[2]);
        $pdf->SetTextColor($headerColor[0], $headerColor[1], $headerColor[2]);

        for($i = 0; $i < count($headers); $i++) {
            $pdf->Cell($widths[$i], 8, $headers[$i], 1, 0, 'C', true);
        }
        $pdf->Ln();

        $pdf->SetFont('Arial', '', 8);
        $pdf->SetTextColor(0, 0, 0);
    }

    // Alternate row color
    if($fill) {
        $pdf->SetFillColor(248, 248, 248);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }

    for($i = 0; $i < count($row); $i++) {
        $pdf->Cell($widths[$i], 6, $row[$i], 'LR', 0, 'L', $fill);
    }
    $pdf->Ln();
    $fill = !$fill;
}

// Closing line
$pdf->Cell(array_sum($widths), 0, '', 'T');
$pdf->Ln(10);

// State-wise Distribution
$pdf->AddPage('L');
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor($headerColor[0], $headerColor[1], $headerColor[2]);
$pdf->Cell(0, 10, 'State-wise Party Distribution', 0, 1);

$state_count = [];
$state_sales = [];
foreach($parties as $party) {
    $state = $party['state'] ?: 'Unknown';
    if(!isset($state_count[$state])) {
        $state_count[$state] = 0;
        $state_sales[$state] = 0;
    }
    $state_count[$state]++;
    if(isset($party_stats[$party['id']]['total_sales'])) {
        $state_sales[$state] += $party_stats[$party['id']]['total_sales'];
    }
}
arsort($state_count);

$headers = ['State', 'Number of Parties', 'Percentage', 'Total Sales'];
$widths = [60, 40, 35, 45];

$data = [];
foreach($state_count as $state => $count) {
    $percentage = ($count / $total_parties) * 100;
    $sales = $state_sales[$state] ?? 0;
    $data[] = [
        $state,
        $count,
        number_format($percentage, 1) . '%',
        CURRENCY . ' ' . number_format($sales, 2)
    ];
}

$pdf->ImprovedTable($headers, $data, $widths);

// Footer with summary
$pdf->SetY(-30);
$pdf->SetFont('Arial', 'I', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 6, 'Report Summary: ' . $total_parties . ' parties with total sales of ' . CURRENCY . ' ' . number_format($total_sales, 2), 0, 1);
// $pdf->Cell(0, 6, 'Generated by: ' . ($_SESSION['user_name'] ?? 'System'), 0, 1);
// $pdf->Cell(0, 6, 'Page ' . $pdf->PageNo() . ' of {nb}', 0, 1);

// Output PDF
$pdf->Output('I', 'party_Details_Report_' . date('Y-m-d') . '.pdf');
?>