<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";
require_once "../../includes/pdf_helper.php";

class ReportPDF extends PDFHelper {
    private $summary_data = [];
    private $report_period = '';
    public $total_pages = 1;

    function setSummaryData($data, $period) {
        $this->summary_data = $data;
        $this->report_period = $period;
    }

    function Footer() {
        // Only show summary on the last page
        if ($this->PageNo() == $this->total_pages) {
            $this->SetY(-60); // Position for summary
            $this->SetFont('Arial', 'B', 10);
            $this->Cell(0, 6, 'Summary (' . $this->report_period . ')', 0, 1, 'L');
            $this->SetFont('Arial', '', 9);
            
            foreach ($this->summary_data as $key => $value) {
                $this->Cell(60, 5, $this->cleanText($key) . ':', 0, 0, 'L');
                $this->Cell(0, 5, $this->cleanText($value), 0, 1, 'L');
            }
        }

        // Call parent to draw page number and date
        parent::Footer();
    }
}

$database = new Database();
$db = $database->getConnection();

// Filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');
$party_id = $_GET['party_id'] ?? '';

// Build query
$query = "SELECT i.*, p.name as party_name 
          FROM ff_sch.invoices i 
          LEFT JOIN ff_sch.parties p ON i.party_id = p.id 
          WHERE i.invoice_date BETWEEN :start_date AND :end_date";

$params = [':start_date' => $start_date, ':end_date' => $end_date];

if(!empty($party_id)) {
    $query .= " AND i.party_id = :party_id";
    $params[':party_id'] = $party_id;
}

$query .= " ORDER BY i.invoice_date DESC";

$stmt = $db->prepare($query);
foreach($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get company details
$company = $db->query("SELECT * FROM ff_sch.companies LIMIT 1")->fetch(PDO::FETCH_ASSOC);

// Create PDF
$report_period_str = 'From: ' . date('d/m/Y', strtotime($start_date)) . ' To: ' . date('d/m/Y', strtotime($end_date));
$pdf = new ReportPDF('Invoice Report', $company['name'], $company['address']);

// Summary Statistics
$total_invoices = count($invoices);
$total_taxable = 0;
$total_tax = 0;
$total_discount = 0;
$total_net = 0;

foreach($invoices as $invoice) {
    $total_taxable += $invoice['taxable_amount'];
    $total_tax += $invoice['total_tax'];
    $total_discount += $invoice['discount'];
    $total_net += $invoice['net_amount'];
}

$pdf->SetFont('Arial', '', 10);
$summary_data = [
    'Total Invoices' => $total_invoices,
    'Total Taxable Amount' => CURRENCY . number_format($total_taxable, 2),
    'Total Discount' => CURRENCY . number_format($total_discount, 2),
    'Total Tax' => CURRENCY . number_format($total_tax, 2),
    'Total Net Amount' => CURRENCY . number_format($total_net, 2)
];

$rows_per_page = 35;
$total_rows = count($invoices);
$total_pages = ceil($total_rows / $rows_per_page);
if ($total_pages == 0) {
    $total_pages = 1;
}

$pdf->total_pages = $total_pages;

$pdf->setSummaryData($summary_data, $report_period_str);
$pdf->AliasNbPages();
$pdf->AddPage('P');

// Report Period
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Report Period: ' . $report_period_str, 0, 1);

// Invoices Table
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Invoice Details', 0, 1);

$headers = ['Inv. No', 'Date', 'Party', 'Taxable', 'Tax %', 'Discount', 'Net Amount'];
$widths = [15, 25, 60, 25, 15, 25, 25];
$aligns = ['L', 'L', 'L', 'R', 'R', 'R', 'R'];

$data = [];
foreach($invoices as $invoice) {

    if($invoice['tax_type'] == 'interstate'){
        $tax_percent = $invoice['igst_percent'];
    } else {
        $tax_percent = $invoice['sgst_percent'] + $invoice['cgst_percent'];
    }

    // $tax_percent = ($invoice['taxable_amount'] > 0) ? ($invoice['sgst_percent'] + $invoice['cgst_percent'] + $invoice['igst_percent']) : 0;
    
    $data[] = [
        $invoice['invoice_no'],
        date('d/m/Y', strtotime($invoice['invoice_date'])),
        substr($invoice['party_name'], 0, 35),
        CURRENCY . number_format($invoice['taxable_amount'], 2),
        number_format($tax_percent, 2) . '%',
        CURRENCY . number_format($invoice['discount'], 2),
        CURRENCY . number_format($invoice['net_amount'], 2)
    ];
}

// Manually draw table to control pagination
$pdf->SetFont('Arial', 'B', 10);
for ($i = 0; $i < count($headers); $i++) {
    $pdf->Cell($widths[$i], 7, $pdf->cleanText($headers[$i]), 1, 0, 'C');
}
$pdf->Ln();

$pdf->SetFont('Arial', '', 9);
$row_count = 0;
foreach($data as $row) {
    if ($row_count > 0 && $row_count % $rows_per_page == 0) {
        $pdf->AddPage('P');
        $pdf->SetFont('Arial', 'B', 10);
        for ($i = 0; $i < count($headers); $i++) {
            $pdf->Cell($widths[$i], 7, $pdf->cleanText($headers[$i]), 1, 0, 'C');
        }
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 9);
    }

    for ($i = 0; $i < count($headers); $i++) {
        $pdf->Cell($widths[$i], 6, $row[$i], 1, 0, $aligns[$i]);
    }
    $pdf->Ln();
    $row_count++;
}

// Grand Total Row
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(array_sum(array_slice($widths, 0, 3)), 7, 'Grand Total', 1, 0, 'R');
$pdf->Cell($widths[3], 7, CURRENCY . number_format($total_taxable, 2), 1, 0, 'R');
$pdf->Cell($widths[4], 7, '', 1, 0, 'R'); // Empty for Tax %
$pdf->Cell($widths[5], 7, CURRENCY . number_format($total_discount, 2), 1, 0, 'R');
$pdf->Cell($widths[6], 7, CURRENCY . number_format($total_net, 2), 1, 1, 'R');

// Output PDF
$pdf->Output('I', 'Invoice_Report_' . date('Y-m-d') . '.pdf');
?>