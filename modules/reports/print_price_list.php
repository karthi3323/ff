<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";
require_once "../../includes/fpdf/fpdf.php";

// Basic validation
if (!isset($_GET['price_code_id']) || empty($_GET['price_code_id'])) {
    die("No Price Code selected.");
}

$database = new Database();
$db = $database->getConnection();

$selected_price_code_id = $_GET['price_code_id'];

// Get company details for header
$company = $db->query("SELECT * FROM ff_sch.companies LIMIT 1")->fetch(PDO::FETCH_ASSOC);

// Get details of the selected price code
$stmt = $db->prepare("SELECT pc.name, pc.code, fy.year_name 
                      FROM ff_sch.price_codes pc
                      JOIN ff_sch.fiscal_years fy ON pc.fiscal_year_id = fy.id
                      WHERE pc.id = :id");
$stmt->bindParam(':id', $selected_price_code_id, PDO::PARAM_INT);
$stmt->execute();
$selected_price_code_details = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$selected_price_code_details) {
    die("Invalid Price Code.");
}

// Fetch ALL product prices for the selected price code
$prices_query = "SELECT 
                    p.product_id, 
                    p.name, 
                    p.uom, 
                    p.per_box_pieces,
                    COALESCE(pp.rate, 0.00) as rate
                 FROM 
                    ff_sch.products p
                 LEFT JOIN 
                    ff_sch.product_prices pp ON p.id = pp.product_id AND pp.price_code_id = :price_code_id
                 WHERE 
                    p.is_active = true
                 ORDER BY 
                    p.name ASC";

$stmt = $db->prepare($prices_query);
$stmt->bindParam(':price_code_id', $selected_price_code_id, PDO::PARAM_INT);
$stmt->execute();
$product_prices = $stmt->fetchAll(PDO::FETCH_ASSOC);

// PDF Generation
class PDF extends FPDF
{
    private $company_name = '';
    private $company_address = '';
    private $report_title = '';

    function setReportDetails($company, $report_title) {
        $this->company_name = $company['name'] ?? 'Company Name';
        $this->company_address = $company['address'] ?? '';
        $this->report_title = $report_title;
    }

    // Page header
    function Header()
    {
        // Company Name
        $this->SetFont('Arial','B',16);
        $this->Cell(0, 8, $this->company_name, 0, 1, 'C');
        // Address
        $this->SetFont('Arial','',10);
        $this->Cell(0, 5, $this->company_address, 0, 1, 'C');
        $this->Ln(2);
        // Report Title
        $this->SetFont('Arial','B',12);
        $this->Cell(0, 10, $this->report_title, 0, 1, 'C');
        $this->Ln(5);

        // Table Header
        $this->SetFont('Arial','B',10);
        $this->SetFillColor(230, 230, 230);
        $this->SetTextColor(0);
        $this->SetDrawColor(128, 128, 128);
        $this->Cell(15, 7, '#', 1, 0, 'C', true);
        $this->Cell(30, 7, 'Product ID', 1, 0, 'C', true);
        $this->Cell(85, 7, 'Product Name', 1, 0, 'C', true);
        $this->Cell(20, 7, 'UOM', 1, 0, 'C', true);
        $this->Cell(20, 7, 'Per Box', 1, 0, 'C', true);
        $this->Cell(20, 7, 'Rate', 1, 1, 'C', true);
    }

    // Page footer
    function Footer()
    {
        // Position at 1.5 cm from bottom
        $this->SetY(-15);
        // Arial italic 8
        $this->SetFont('Arial','I',8);
        // Page number
        $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
    }
}

// Instantiation of inherited class
$pdf = new PDF('P', 'mm', 'A4'); // 'P' for Portrait
$pdf->setReportDetails($company, 'Price List: ' . htmlspecialchars_decode($selected_price_code_details['name']));
$pdf->AliasNbPages();
$pdf->AddPage();
$pdf->SetFont('Arial','',9);

// Data loop
$i = 1;
foreach($product_prices as $item) {
    // Auto page break
    if($pdf->GetY() > 275) { // Trigger page break near the bottom of the page
        $pdf->AddPage();
    }
    $pdf->Cell(15, 6, $i++, 1, 0, 'C');
    $pdf->Cell(30, 6, $item['product_id'], 1, 0, 'L');
    $pdf->Cell(85, 6, $item['name'], 1, 0, 'L');
    $pdf->Cell(20, 6, $item['uom'], 1, 0, 'C');
    $pdf->Cell(20, 6, $item['per_box_pieces'], 1, 0, 'C');
    $pdf->Cell(20, 6, number_format($item['rate'], 2), 1, 1, 'R');
}

$pdf->Output('I', 'Price_List_Report.pdf');
?>