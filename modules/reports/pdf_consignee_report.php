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

// Fetch data
$query = "SELECT 
            p.name,
            p.city,
            p.state,
            COUNT(i.id) as invoice_count,
            SUM(i.net_amount) as total_sales
          FROM ff_sch.parties p
          JOIN ff_sch.invoices i ON p.id = i.party_id
          WHERE i.invoice_date BETWEEN :start_date AND :end_date
          GROUP BY p.id, p.name, p.city, p.state
          ORDER BY total_sales DESC";

$stmt = $db->prepare($query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->execute();
$Parties = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get company details
$company = $db->query("SELECT * FROM ff_sch.companies LIMIT 1")->fetch(PDO::FETCH_ASSOC);

// Create PDF
$pdf = new PDFHelper('Party Sales Report', $company['name'], $company['address']);
$pdf->AliasNbPages();
$pdf->AddPage('L'); // Landscape

// Report Period
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Report Period: ' . date('d/m/Y', strtotime($start_date)) . ' to ' . date('d/m/Y', strtotime($end_date)), 0, 1, 'C');
$pdf->Ln(5);

// Table
$headers = ['Party Name', 'Location', 'No. of Invoices', 'Total Sales'];
$widths = [120, 70, 40, 40];
$aligns = ['L', 'L', 'C', 'R'];

$data = [];
$total_sales_all = 0;
foreach ($Parties as $party) {
    $data[] = [
        $party['name'],
        $party['city'] . ', ' . $party['state'],
        $party['invoice_count'],
        CURRENCY . ' ' . number_format($party['total_sales'], 2)
    ];
    $total_sales_all += $party['total_sales'];
}

$pdf->ImprovedTable($headers, $data, $widths, $aligns);

// Total
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(array_sum(array_slice($widths, 0, 3)), 7, 'Total Sales', 1, 0, 'R');
$pdf->Cell($widths[3], 7, CURRENCY . ' ' . number_format($total_sales_all, 2), 1, 1, 'R');

// Output PDF
$pdf->Output('I', 'party_Report_' . date('Y-m-d') . '.pdf');
?>