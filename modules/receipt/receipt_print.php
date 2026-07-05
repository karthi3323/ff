<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";
require_once '../../includes/fpdf/fpdf.php';

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: view.php");
    exit;
}

$rcpt_hdr_id = $_GET['id'];

$database = new Database();
$db = $database->getConnection();

// 1. Fetch Company Details
$company_query = "SELECT * FROM ff_sch.companies LIMIT 1";
$company = $db->query($company_query)->fetch(PDO::FETCH_ASSOC);

// 2. Fetch Receipt Header
$hdr_sql = "SELECT * FROM ff_sch.rcpt_hdr WHERE id = :id";
$hdr_stmt = $db->prepare($hdr_sql);
$hdr_stmt->bindParam(':id', $rcpt_hdr_id);
$hdr_stmt->execute();
$receipt_hdr = $hdr_stmt->fetch(PDO::FETCH_ASSOC);

if (!$receipt_hdr) {
    die("Receipt not found.");
}
// Fetch Party details from the first estimate associated with the receipt
$party_sql = "SELECT p.name as party_name, p.address_line1, p.address_line2, p.city, p.state, p.gst_no as party_gst,
                COALESCE(e.party_id, inv.party_id) as party_id
              FROM ff_sch.rcpt_dtl rd
              LEFT JOIN ff_sch.estimate e ON rd.estimate_id = e.id
              LEFT JOIN ff_sch.invoices inv ON rd.invoice_id = inv.id
              JOIN ff_sch.parties p ON p.id = COALESCE(e.party_id, inv.party_id)
              WHERE rd.rcpt_hdr_id = :id
              LIMIT 1";
$party_stmt = $db->prepare($party_sql);
$party_stmt->bindParam(':id', $rcpt_hdr_id);
$party_stmt->execute();
$party_details = $party_stmt->fetch(PDO::FETCH_ASSOC);

if ($party_details) {
    $receipt_hdr = array_merge($receipt_hdr, $party_details);
}
// 3. Fetch Receipt Details
$dtl_sql = "SELECT 
                rd.receipt_amount,
                rd.payment_type,
                rd.narration,
                rd.pending_amount_after_receipt,
                COALESCE(CAST(e.estimate_no AS VARCHAR), CAST(inv.invoice_no AS VARCHAR)) as doc_no,
                COALESCE(e.estimate_date, inv.invoice_date) as doc_date,
                COALESCE(e.net_amount, inv.net_amount) as estimate_amount,
                p.name as party_name
            FROM ff_sch.rcpt_dtl rd
            LEFT JOIN ff_sch.estimate e ON rd.estimate_id = e.id
            LEFT JOIN ff_sch.invoices inv ON rd.invoice_id = inv.id
            JOIN ff_sch.parties p ON p.id = COALESCE(e.party_id, inv.party_id)
            WHERE rd.rcpt_hdr_id = :rcpt_hdr_id
            ORDER BY doc_date, doc_no";
$dtl_stmt = $db->prepare($dtl_sql);
$dtl_stmt->bindParam(':rcpt_hdr_id', $rcpt_hdr_id);
$dtl_stmt->execute();
$receipt_details = $dtl_stmt->fetchAll(PDO::FETCH_ASSOC);
// exit;
class PDF extends FPDF
{
    private $company;

    function __construct($orientation='P', $unit='mm', $size='A4', $company) {
        parent::__construct($orientation, $unit, $size);
        $this->company = $company;
    }

    // Function to calculate the number of lines for a MultiCell
    function NbLines($w, $txt)
    {
        //Computes the number of lines a MultiCell of width w will take
        $cw = &$this->CurrentFont['cw'];
        if ($w == 0)
            $w = $this->w - $this->rMargin - $this->x;
        $wmax = ($w - 2 * $this->cMargin) * 1000 / $this->FontSize;
        $s = str_replace("\r", '', $txt);
        $nb = strlen($s);
        if ($nb > 0 && $s[$nb - 1] == "\n")
            $nb--;
        $sep = -1;
        $i = 0;
        $j = 0;
        $l = 0;
        $nl = 1;
        while ($i < $nb) {
            $c = $s[$i];
            if ($c == "\n") {
                $i++;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
                continue;
            }
            if ($c == ' ')
                $sep = $i;
            $l += $cw[$c];
            if ($l > $wmax) {
                if ($sep == -1) {
                    if ($i == $j)
                        $i++;
                } else
                    $i = $sep + 1;
                $sep = -1;
                $j = $i;
                $l = 0;
                $nl++;
            } else
                $i++;
        }
        return $nl;
    }

    function Header()
    {
        // Page Border
        $this->SetDrawColor(0, 0, 0);
        $this->SetLineWidth(0.5);
        $this->Rect(5, 5, 200, 287);
        $this->SetLineWidth(0.2);

        // Company Details
        $this->SetFont('Arial','B',16);
        $this->Cell(0,10,$this->company['name'],0,1,'C');
        $this->SetFont('Arial','',10);
        $this->Cell(0,5,$this->company['address'],0,1,'C');
        $this->SetFont('Arial','B',12);
        $this->Cell(0,10,'CASH RECEIPT',0,1,'C');
        $this->Ln(2);
    }

    function Footer()
    {
        $this->SetY(-25);
        $this->SetFont('Arial','',10);
        $this->Cell(0,10,'For ' . $this->company['name'],0,0,'R');
        $this->SetY(-15);
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
    }

    function addPartyDetails($party) {
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(95, 8, 'Received From:', 0, 1);
        
        $this->SetFont('Arial', 'B', 10);
        $this->MultiCell(95, 6, $party['party_name'] ?? '-', 0, 'L');
        
        $this->SetFont('Arial', '', 9);
        $address = trim(($party['address_line1'] ?? '') . ' ' . ($party['address_line2'] ?? ''));
        if ($address) {
            $this->MultiCell(90, 5, $address, 0, 'L');
        }
        $city_state = trim(($party['city'] ?? '') . (!empty($party['city']) && !empty($party['state']) ? ', ' : '') . ($party['state'] ?? ''));
        if ($city_state) {
            $this->Cell(95, 5, $city_state, 0, 1);
        }
        
        if (!empty($party['party_gst'])) {
            $this->SetFont('Arial', 'B', 9);
            $this->Cell(95, 5, 'GSTIN: ' . $party['party_gst'], 0, 1);
        }
    }

    function addReceiptHeaderInfo($receipt_no, $receipt_date, $agent_name, $startY) {
        $this->SetXY(110, $startY);
        $this->SetFont('Arial', 'B', 11);
        $this->Cell(80, 8, 'RECEIPT DETAILS', 0, 1);
        
        $this->SetFont('Arial', '', 9);
        $this->SetX(110);
        $this->Cell(35, 6, 'Receipt No:', 0, 0);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(45, 6, $receipt_no, 0, 1);
        
        $this->SetFont('Arial', '', 9);
        $this->SetX(110);
        $this->Cell(35, 6, 'Date:', 0, 0);
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(45, 6, ($receipt_date ? date('d-m-Y', strtotime($receipt_date)) : '-'), 0, 1);
        
        $this->SetFont('Arial', '', 9);
        $this->SetX(110);
        $this->Cell(35, 6, 'Agent Name:', 0, 0);
        $this->SetFont('Arial', 'B', 9);
        $this->SetX(110 + 35);
        $this->MultiCell(45, 6, $agent_name, 0, 'L');
    }

    function GetMultiCellHeight($w, $h, $txt) {
        return $this->NbLines($w, $txt) * $h;
    }

    function GetPageBreakTrigger()
    {
        return $this->PageBreakTrigger;
    }
}
function numberToWords($number) {
    $no = floor($number);
    $point = round($number - $no, 2) * 100;
    $hundred = null;
    $digits_1 = strlen($no);
    $i = 0;
    $str = array();
    $words = array('0' => '', '1' => 'one', '2' => 'two',
        '3' => 'three', '4' => 'four', '5' => 'five', '6' => 'six',
        '7' => 'seven', '8' => 'eight', '9' => 'nine',
        '10' => 'ten', '11' => 'eleven', '12' => 'twelve',
        '13' => 'thirteen', '14' => 'fourteen',
        '15' => 'fifteen', '16' => 'sixteen', '17' => 'seventeen',
        '18' => 'eighteen', '19' => 'nineteen', '20' => 'twenty',
        '30' => 'thirty', '40' => 'forty', '50' => 'fifty',
        '60' => 'sixty', '70' => 'seventy',
        '80' => 'eighty', '90' => 'ninety');
    $digits = array('', 'hundred', 'thousand', 'lakh', 'crore');
    while ($i < $digits_1) {
        $divider = ($i == 2) ? 10 : 100;
        $number = floor($no % $divider);
        $no = floor($no / $divider);
        $i += ($divider == 10) ? 1 : 2;
        if ($number) {
            $plural = (($counter = count($str)) && $number > 9) ? 's' : null;
            $hundred = ($counter == 1 && $str[0]) ? ' and ' : null;
            $str[] = ($number < 21) ? $words[$number] .
                " " . $digits[$counter] . $plural . " " . $hundred
                :
                $words[floor($number / 10) * 10]
                . " " . $words[$number % 10] . " "
                . $digits[$counter] . $plural . " " . $hundred;
        } else $str[] = null;
    }
    $str = array_reverse($str);
    $result = implode('', $str);
    $points = ($point) ?
        " and " . (isset($words[$point / 10]) ? $words[floor($point/10)*10] . " " : '') . 
        (isset($words[$point % 10]) ? $words[$point % 10] : '') . " Paise" : '';
    return ucwords($result) . "Rupees" . $points;
}

$pdf = new PDF('P', 'mm', 'A4', $company);
$pdf->AliasNbPages();
$pdf->AddPage();

// Layout with party and receipt details side-by-side
$startY = $pdf->GetY();

// Left side: Party Details
$pdf->addPartyDetails($receipt_hdr);
$endYLeft = $pdf->GetY();

// Right side: Receipt Info
$pdf->addReceiptHeaderInfo(
    $receipt_hdr['receipt_no'],
    $receipt_hdr['receipt_date'],
    $receipt_hdr['agent_name'],
    $startY
);
$endYRight = $pdf->GetY();

// Set Y to after the taller of the two columns
$pdf->SetY(max($endYLeft, $endYRight) + 5);

// Table Header
$pdf->SetFont('Arial','B',9);
$pdf->SetFillColor(230,230,230);
$pdf->Cell(8, 7, 'S.No', 1, 0, 'C', true);
$pdf->Cell(35, 7, 'Party Name', 1, 0, 'C', true);
$pdf->Cell(25, 7, 'Bill No / Date', 1, 0, 'C', true);
$pdf->Cell(20, 7, 'Pay Type', 1, 0, 'C', true);
$pdf->Cell(27, 7, 'Narration', 1, 0, 'C', true);
$pdf->Cell(20, 7, 'Bill Amt', 1, 0, 'C', true);
$pdf->Cell(20, 7, 'Pending', 1, 0, 'C', true);
$pdf->Cell(20, 7, 'Received', 1, 0, 'C', true);
$pdf->Cell(20, 7, 'Balance', 1, 1, 'C', true);

// Table Body
$pdf->SetFont('Arial','',8);
$sno = 1;
$lineHeight = 7; // A fixed line height for MultiCell
foreach($receipt_details as $row) {
    // Calculate the maximum height required for this row
    $h1 = $pdf->GetMultiCellHeight(35, $lineHeight, $row['party_name']);
    $h2 = $pdf->GetMultiCellHeight(27, $lineHeight, $row['narration']);
    $rowHeight = max($h1, $h2, $lineHeight);

    // Check for page break before drawing the row
    if ($pdf->GetY() + $rowHeight > $pdf->GetPageBreakTrigger() && $pdf->AcceptPageBreak()) {
        $pdf->AddPage();
        // Redraw table header on new page
        $pdf->SetFont('Arial','B',9);
        $pdf->SetFillColor(230,230,230);
        $pdf->Cell(8, 7, 'S.No', 1, 0, 'C', true);
        $pdf->Cell(35, 7, 'Party Name', 1, 0, 'C', true);
        $pdf->Cell(25, 7, 'Bill No / Date', 1, 0, 'C', true);
        $pdf->Cell(20, 7, 'Pay Type', 1, 0, 'C', true);
        $pdf->Cell(27, 7, 'Narration', 1, 0, 'C', true);
        $pdf->Cell(20, 7, 'Bill Amt', 1, 0, 'C', true);
        $pdf->Cell(20, 7, 'Pending', 1, 0, 'C', true);
        $pdf->Cell(20, 7, 'Received', 1, 0, 'C', true);
        $pdf->Cell(20, 7, 'Balance', 1, 1, 'C', true);
        $pdf->SetFont('Arial','',8);
    }

    // Store the starting X and Y
    $startX = $pdf->GetX();
    $startY = $pdf->GetY();

    // Draw the borders for all cells in the row
    $pdf->Rect($startX, $startY, 8, $rowHeight);
    $pdf->Rect($startX + 8, $startY, 35, $rowHeight);
    $pdf->Rect($startX + 8 + 35, $startY, 25, $rowHeight);
    $pdf->Rect($startX + 8 + 35 + 25, $startY, 20, $rowHeight); // Pay Type
    $pdf->Rect($startX + 8 + 35 + 25 + 20, $startY, 27, $rowHeight); // Narration
    $pdf->Rect($startX + 8 + 35 + 25 + 20 + 27, $startY, 20, $rowHeight); // Bill Amt
    $pdf->Rect($startX + 8 + 35 + 25 + 20 + 27 + 20, $startY, 20, $rowHeight); // Pending
    $pdf->Rect($startX + 8 + 35 + 25 + 20 + 27 + 20 + 20, $startY, 20, $rowHeight); // Received
    $pdf->Rect($startX + 8 + 35 + 25 + 20 + 27 + 20 + 20 + 20, $startY, 20, $rowHeight); // Balance

    // Write the content. Use MultiCell for all to ensure consistent top-alignment.
    $pdf->MultiCell(8, $lineHeight, $sno++, 0, 'C');
    $pdf->SetXY($startX + 8, $startY);
    $pdf->MultiCell(35, $lineHeight, $row['party_name'], 0, 'L');
    $pdf->SetXY($startX + 8 + 35, $startY);
    $pdf->MultiCell(25, $lineHeight, $row['doc_no'] . ' / ' . date('d-m-Y', strtotime($row['doc_date'])), 0, 'L');
    $pdf->SetXY($startX + 8 + 35 + 25, $startY);
    $pdf->MultiCell(20, $lineHeight, $row['payment_type'], 0, 'L');
    $pdf->SetXY($startX + 8 + 35 + 25 + 20, $startY);
    $pdf->MultiCell(27, $lineHeight, $row['narration'], 0, 'L');
    $pdf->SetXY($startX + 8 + 35 + 25 + 20 + 27, $startY);
    $pdf->MultiCell(20, $lineHeight, number_format($row['estimate_amount'], 2), 0, 'R');
    $pdf->SetXY($startX + 8 + 35 + 25 + 20 + 27 + 20, $startY);
    $pending_before = $row['pending_amount_after_receipt'] + $row['receipt_amount'];
    $pdf->MultiCell(20, $lineHeight, number_format($pending_before, 2), 0, 'R');
    $pdf->SetXY($startX + 8 + 35 + 25 + 20 + 27 + 20 + 20, $startY);
    $pdf->MultiCell(20, $lineHeight, number_format($row['receipt_amount'], 2), 0, 'R');
    $pdf->SetXY($startX + 8 + 35 + 25 + 20 + 27 + 20 + 20 + 20, $startY);
    $pdf->MultiCell(20, $lineHeight, number_format($row['pending_amount_after_receipt'], 2), 0, 'R');

    // Move to the next line
    $pdf->SetXY($startX, $startY + $rowHeight);
}

// Total
$pdf->SetFont('Arial','B',9);
$pdf->Cell(155, 8, 'Total Received Amount', 1, 0, 'R');
$pdf->Cell(20, 8, number_format($receipt_hdr['total_receipt_amount'], 2), 1, 0, 'R');
$pdf->Cell(20, 8, '', 1, 1, 'L');
$pdf->Ln(5);

// Amount in words
$pdf->SetFont('Arial','B',10);
$pdf->Cell(35, 7, 'Amount in Words:');
$pdf->SetFont('Arial','',10);
$pdf->MultiCell(0, 7, numberToWords($receipt_hdr['total_receipt_amount']) . ' Only.');

$pdf->Output('I', 'Receipt_' . $receipt_hdr['receipt_no'] . '.pdf');
?>