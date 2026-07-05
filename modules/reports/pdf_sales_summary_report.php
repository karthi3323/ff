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

// Sales summary query
$query = "SELECT 
            COUNT(*) as invoice_count,
            SUM(taxable_amount) as total_taxable,
            SUM(discount) as total_discount,
            SUM(sgst_amount) as total_sgst,
            SUM(cgst_amount) as total_cgst,
            SUM(igst_amount) as total_igst,
            SUM(total_tax) as total_tax,
            SUM(net_amount) as total_net
          FROM ff_sch.invoices 
          WHERE invoice_date BETWEEN :start_date AND :end_date";

$stmt = $db->prepare($query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->execute();
$summary = $stmt->fetch(PDO::FETCH_ASSOC);

// Get company details
$company = $db->query("SELECT * FROM ff_sch.companies LIMIT 1")->fetch(PDO::FETCH_ASSOC);

// Create PDF
$pdf = new PDFHelper('Sales Summary Report', $company['name'], $company['address']);
$pdf->AliasNbPages();
$pdf->AddPage('P'); // Portrait

// Report Period
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Summary for ' . date('d M Y', strtotime($start_date)) . ' to ' . date('d M Y', strtotime($end_date)), 0, 1, 'C');
$pdf->Ln(10);

// Function to draw a summary item
function drawSummaryItem($pdf, $label, $value, $is_total = false) {
    $pdf->SetFont('Arial', $is_total ? 'B' : '', 12);
    $pdf->Cell(95, 10, $label, 'B', 0, 'L');
    $pdf->SetFont('Arial', 'B', 12);
    $pdf->Cell(95, 10, $value, 'B', 1, 'R');
}

// Sales Details
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'Sales Overview', 0, 1, 'L');
drawSummaryItem($pdf, 'Total Invoices', number_format($summary['invoice_count'] ?? 0));
drawSummaryItem($pdf, 'Total Taxable Amount', CURRENCY . ' ' . number_format($summary['total_taxable'] ?? 0, 2));
drawSummaryItem($pdf, 'Total Discount', '- ' . CURRENCY . ' ' . number_format($summary['total_discount'] ?? 0, 2));
$pdf->Ln(10);

// Tax Details
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'Tax Collection', 0, 1, 'L');
drawSummaryItem($pdf, 'Total SGST', CURRENCY . ' ' . number_format($summary['total_sgst'] ?? 0, 2));
drawSummaryItem($pdf, 'Total CGST', CURRENCY . ' ' . number_format($summary['total_cgst'] ?? 0, 2));
drawSummaryItem($pdf, 'Total IGST', CURRENCY . ' ' . number_format($summary['total_igst'] ?? 0, 2));
drawSummaryItem($pdf, 'Total Tax Collected', CURRENCY . ' ' . number_format($summary['total_tax'] ?? 0, 2), true);
$pdf->Ln(15);

// Grand Total
$pdf->SetFont('Arial', 'B', 18);
$pdf->SetFillColor(240, 240, 240);
$pdf->Cell(95, 15, 'Total Net Sales', 1, 0, 'C', true);
$pdf->SetTextColor(34, 139, 34); // Green color for total
$pdf->Cell(95, 15, CURRENCY . ' ' . number_format($summary['total_net'] ?? 0, 2), 1, 1, 'C', true);
$pdf->SetTextColor(0);

// Output PDF
$pdf->Output('I', 'Sales_Summary_Report_' . date('Y-m-d') . '.pdf');
?>