<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";
require_once '../../includes/fpdf/fpdf.php';

$report_type = $_GET['report_type'] ?? '';
$from_date   = $_GET['from_date'] ?? '';
$to_date     = $_GET['to_date'] ?? '';
$selected_id = $_GET['selected_id'] ?? '';
$selected_name = $_GET['selected_name'] ?? '';
$payment_type = $_GET['payment_type'] ?? 'all';
$receipt_against = $_GET['receipt_against'] ?? 'all';

// echo "<pre>";
// print_r($_GET);
// exit;

$database = new Database();
$db = $database->getConnection();

// Fetch company details for header
$company_query = "SELECT * FROM ff_sch.companies LIMIT 1";
$company = $db->query($company_query)->fetch(PDO::FETCH_ASSOC);

// Fetch data based on report type
$base_sql = "SELECT 
                rh.agent_name,
                rh.receipt_no,
                rh.receipt_date,
                p.name as party_name,
                COALESCE(CAST(e.estimate_no AS VARCHAR), CAST(inv.invoice_no AS VARCHAR)) as doc_no,
                COALESCE(e.net_amount, inv.net_amount) as estimate_amount,
                rd.receipt_amount,
                rd.payment_type,
                rd.narration,
                rd.pending_amount_after_receipt
            FROM ff_sch.rcpt_hdr rh
            JOIN ff_sch.rcpt_dtl rd ON rh.id = rd.rcpt_hdr_id
            LEFT JOIN ff_sch.estimate e ON rd.estimate_id = e.id
            LEFT JOIN ff_sch.invoices inv ON rd.invoice_id = inv.id
            JOIN ff_sch.parties p ON p.id = COALESCE(e.party_id, inv.party_id)";

$where_clauses = [];
$params = [];

$where_clauses[] = "rh.receipt_date BETWEEN :from_date AND :to_date";
$params[':from_date'] = $from_date;
$params[':to_date'] = $to_date;

$title = '';
$order_by = '';

if ($report_type == 'agent') {
    if ($selected_id != 'all' && !empty($selected_id)) {
        $where_clauses[] = "rh.agent_name = :selected_id";
        $params[':selected_id'] = $selected_id;
        $title = 'Agent Wise Receipt Report for ' . $selected_name;
    } else {
        $title = 'Agent Wise Receipt Report (All Agents)';
    }
    $order_by = "rh.agent_name, rh.receipt_no, p.name, rh.receipt_date";

} elseif ($report_type == 'party') {
    if ($selected_id != 'all' && !empty($selected_id)) {
        $where_clauses[] = "p.id = :selected_id";
        $params[':selected_id'] = $selected_id;
        $title = 'Party Wise Receipt Report for ' . $selected_name;
    } else {
        $title = 'Party Wise Receipt Report (All Parties)';
    }
    $order_by = "p.name, rh.receipt_no, rh.receipt_date";
}

if ($payment_type != 'all' && !empty($payment_type)) {
    $where_clauses[] = "rd.payment_type = :payment_type";
    $params[':payment_type'] = $payment_type;
}

if ($receipt_against != 'all' && !empty($receipt_against)) {
    $where_clauses[] = "rh.receipt_against = :receipt_against";
    $params[':receipt_against'] = $receipt_against;
}

$sql = $base_sql . " WHERE " . implode(" AND ", $where_clauses) . " ORDER BY " . $order_by;

$stmt = $db->prepare($sql);
$stmt->execute($params);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

$data = [];
if ($report_type == 'agent') {
    foreach ($results as $row) {
        $data[$row['agent_name']][] = $row;
    }
} elseif ($report_type == 'party') {
    foreach ($results as $row) {
        $data[$row['party_name']][] = $row;
    }
}

class PDF extends FPDF
{
    private $company;
    private $title;
    private $date_range;

    function __construct($orientation='P', $unit='mm', $size='A4', $company, $title, $date_range) {
        parent::__construct($orientation, $unit, $size);
        $this->company = $company;
        $this->title = $title;
        $this->date_range = $date_range;
    }

    // Page header
    function Header()
    {
        $this->SetFont('Arial','B',16);
        $this->Cell(0,10,$this->company['name'],0,1,'C');
        $this->SetFont('Arial','B',12);
        $this->Cell(0,10,$this->title,0,1,'C');
        $this->SetFont('Arial','',10);
        $this->Cell(0,7,$this->date_range,0,1,'C');
        $this->Ln(5);
    }

    // Page footer
    function Footer()
    {
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
    }
}

$pdf = new PDF('L', 'mm', 'A4', $company, $title, "From: " . date('d-m-Y', strtotime($from_date)) . " To: " . date('d-m-Y', strtotime($to_date)));
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','',9);

$grand_total = 0;

if ($report_type == 'agent') {
    // Headers
    $pdf->SetFont('Arial','B',9);
    $pdf->Cell(20, 7, 'Receipt No', 1);
    $pdf->Cell(20, 7, 'Receipt Date', 1);
    $pdf->Cell(50, 7, 'Party Name', 1);
    $pdf->Cell(20, 7, 'Pay Type', 1);
    $pdf->Cell(42, 7, 'Narration', 1);
    $pdf->Cell(25, 7, 'Bill No', 1);
    $pdf->Cell(25, 7, 'Bill Amt', 1, 0, 'C');
    $pdf->Cell(25, 7, 'Pending Before', 1, 0, 'R');
    $pdf->Cell(25, 7, 'Received Amt', 1, 0, 'R');
    $pdf->Cell(25, 7, 'Balance', 1, 1, 'R');

    foreach ($data as $groupName => $receipts) {
        $pdf->SetFont('Arial','B',10);
        $pdf->Cell(0, 8, 'Agent: ' . $groupName, '', 1);
        // $pdf->Cell(0, 8, '', 0, 0, 'R');
        $pdf->SetFont('Arial','',8);
        $group_total = 0;

        foreach ($receipts as $row) {
            $pending_before = $row['pending_amount_after_receipt'] + $row['receipt_amount'];
            $pdf->Cell(20, 7, $row['receipt_no'], 1);
            $pdf->Cell(20, 7, date('d-m-Y', strtotime($row['receipt_date'])), 1);
            $pdf->Cell(50, 7, $row['party_name'], 1);
            $pdf->Cell(20, 7, $row['payment_type'], 1);
            $pdf->Cell(42, 7, $row['narration'], 1);
            $pdf->Cell(25, 7, $row['doc_no'], 1);
            $pdf->Cell(25, 7, number_format($row['estimate_amount'], 2), 1, 0, 'R');
            $pdf->Cell(25, 7, number_format($pending_before, 2), 1, 0, 'R');
            $pdf->Cell(25, 7, number_format($row['receipt_amount'], 2), 1, 0, 'R');
            $pdf->Cell(25, 7, number_format($row['pending_amount_after_receipt'], 2), 1, 1, 'R');
            $group_total += $row['receipt_amount'];
        }
        $pdf->SetFont('Arial','B',9);
        $pdf->Cell(227, 7, 'Agent Total', 1, 0, 'R');
        $pdf->Cell(25, 7, number_format($group_total, 2), 1, 0, 'R');
        $pdf->Cell(25, 7, '', 1, 1, 'L');
        $pdf->Ln(5);
        $grand_total += $group_total;
    }
} elseif ($report_type == 'party') {
    // Headers
    $pdf->SetFont('Arial','B',9);
    $pdf->Cell(20, 7, 'Receipt No', 1);
    $pdf->Cell(20, 7, 'Receipt Date', 1);
    $pdf->Cell(50, 7, 'Agent Name', 1);
    $pdf->Cell(20, 7, 'Pay Type', 1);
    $pdf->Cell(42, 7, 'Narration', 1);
    $pdf->Cell(25, 7, 'Bill No', 1);
    $pdf->Cell(25, 7, 'Bill Amt', 1, 0, 'C');
    $pdf->Cell(25, 7, 'Pending Before', 1, 0, 'R');
    $pdf->Cell(25, 7, 'Received Amt', 1, 0, 'R');
    $pdf->Cell(25, 7, 'Balance', 1, 1, 'R');
    

    foreach ($data as $groupName => $receipts) {
        $pdf->SetFont('Arial','B',10);
        $pdf->Cell(0, 8, 'Party: ' . $groupName, '', 1);
        $pdf->SetFont('Arial','',8);
        $group_total = 0;

        foreach ($receipts as $row) {
            $pending_before = $row['pending_amount_after_receipt'] + $row['receipt_amount'];
            $pdf->Cell(20, 7, $row['receipt_no'], 1);
            $pdf->Cell(20, 7, date('d-m-Y', strtotime($row['receipt_date'])), 1);
            $pdf->Cell(50, 7, $row['agent_name'], 1);
            $pdf->Cell(20, 7, $row['payment_type'], 1);
            $pdf->Cell(42, 7, $row['narration'], 1);
            $pdf->Cell(25, 7, $row['doc_no'], 1);
            $pdf->Cell(25, 7, number_format($row['estimate_amount'], 2), 1, 0, 'R');
            $pdf->Cell(25, 7, number_format($pending_before, 2), 1, 0, 'R');
            $pdf->Cell(25, 7, number_format($row['receipt_amount'], 2), 1, 0, 'R');
            $pdf->Cell(25, 7, number_format($row['pending_amount_after_receipt'], 2), 1, 1, 'R');
            $group_total += $row['receipt_amount'];
        }
        $pdf->SetFont('Arial','B',9);
        $pdf->Cell(227, 7, 'Party Total', 1, 0, 'R');
        $pdf->Cell(25, 7, number_format($group_total, 2), 1, 0, 'R');
        $pdf->Cell(25, 7, '', 1, 1, 'L');
        $pdf->Ln(5);
        $grand_total += $group_total;
    }
}

// Grand Total
$pdf->SetFont('Arial','B',10);
$pdf->Cell(227, 8, 'Grand Total', 1, 0, 'R');
$pdf->Cell(25, 8, number_format($grand_total, 2), 1, 0, 'R');
$pdf->Cell(25, 8, '', 1, 1, 'L');

$pdf->Output('I', 'receipt_report.pdf');
?>