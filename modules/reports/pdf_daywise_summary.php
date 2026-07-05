<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";
require_once "../../includes/pdf_helper.php";

$database = new Database();
$db = $database->getConnection();

// Filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Day-wise summary query
$query = "SELECT 
            invoice_date as date,
            COUNT(*) as invoice_count,
            SUM(taxable_amount) as total_taxable,
            SUM(net_amount) as total_net,
            SUM(discount) as total_discount
          FROM ff_sch.invoices 
          WHERE invoice_date BETWEEN :start_date AND :end_date
          GROUP BY invoice_date 
          ORDER BY invoice_date DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->execute();
$summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get company details
$company = $db->query("SELECT * FROM ff_sch.companies LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$comp_name = preg_replace('/\s+/', ' ', trim($company['name']));
// Create PDF in Landscape mode
$pdf = new PDFHelper('Day-wise Summary Details Report', trim($comp_name), $company['address']);
$pdf->AliasNbPages();
$pdf->AddPage();

// Report Period (modern style)
$pdf->SetFillColor(245, 245, 245);
$pdf->SetDrawColor(220, 220, 220);
$pdf->SetFont('Arial', 'B', 11);
$pdf->Cell(0, 10, 'Report Period: ' . date('d/m/Y', strtotime($start_date)) . ' to ' . date('d/m/Y', strtotime($end_date)),
    0, 1, 'L', true);

// Calculate grand totals
$grand_totals = [
    'invoice_count' => 0,
    'total_taxable' => 0,
    'total_net' => 0,
    'total_discount' => 0
];
foreach ($summary as $row) {
    $grand_totals['invoice_count'] += $row['invoice_count'];
    $grand_totals['total_taxable'] += $row['total_taxable'];
    $grand_totals['total_net'] += $row['total_net'];
    $grand_totals['total_discount'] += $row['total_discount'];
}

// Summary Box (modern dashboard style)
$pdf->Ln(5);
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(40, 40, 40);
$pdf->Cell(0, 10, "Overall Summary", 0, 1);

$pdf->SetFillColor(250, 250, 250);
$pdf->SetDrawColor(210, 210, 210);
$pdf->SetFont('Arial', '', 10);

// Build summary data
$summary_data = [
    ['Total Days', count($summary)],
    ['Total Invoices', $grand_totals['invoice_count']],
    ['Total Taxable Amount', CURRENCY . ' ' . number_format($grand_totals['total_taxable'], 2)],
    ['Total Discount', CURRENCY . ' ' . number_format($grand_totals['total_discount'], 2)],
    ['Total Net Sales', CURRENCY . ' ' . number_format($grand_totals['total_net'], 2)],
    ['Average / Day', CURRENCY . ' ' . number_format($grand_totals['total_net'] / max(count($summary), 1), 2)]
];

foreach ($summary_data as $item) {
    $pdf->Cell(60, 10, $item[0], 1, 0, 'L', true);
    $pdf->Cell(0, 10, $item[1], 1, 1, 'L', true);
}

$pdf->Ln(4);

// Day-wise Section Title
$pdf->SetFont('Arial', 'B', 12);
$pdf->SetTextColor(0, 92, 153);
$pdf->Cell(0, 10, 'Day-wise Sales Details', 0, 1);

// Table Headers
$headers = ['Date', 'Invoices', 'Taxable', 'Discount', 'Net Sales', 'Avg/Invoice'];
$widths = [28, 20, 32, 28, 32, 32];

// Colors
$pdf->SetFillColor(0, 92, 153);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 10);

foreach ($headers as $i => $header) {
    $pdf->Cell($widths[$i], 9, $header, 1, 0, 'C', true);
}
$pdf->Ln();

// Table Rows
$pdf->SetFont('Arial', '', 9);
$pdf->SetTextColor(40, 40, 40);

foreach ($summary as $row) {
    $avg = $row['invoice_count'] ? $row['total_net'] / $row['invoice_count'] : 0;

    $pdf->SetFillColor(250, 250, 250);

    $pdf->Cell($widths[0], 8, date('d/m/Y', strtotime($row['date'])), 1, 0, 'C');
    $pdf->Cell($widths[1], 8, $row['invoice_count'], 1, 0, 'C');
    $pdf->Cell($widths[2], 8, CURRENCY . ' ' . number_format($row['total_taxable'], 2), 1, 0, 'R');
    $pdf->Cell($widths[3], 8, CURRENCY . ' ' . number_format($row['total_discount'], 2), 1, 0, 'R');
    $pdf->Cell($widths[4], 8, CURRENCY . ' ' . number_format($row['total_net'], 2), 1, 0, 'R');
    $pdf->Cell($widths[5], 8, CURRENCY . ' ' . number_format($avg, 2), 1, 1, 'R');
}

// Output
$pdf->Output('I', 'Daywise_Summary_' . date('Y-m-d') . '.pdf');

?>
