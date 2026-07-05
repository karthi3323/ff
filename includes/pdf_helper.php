<?php
require_once('fpdf/fpdf.php');

class PDFHelper extends FPDF {
    private $title;
    private $company_name;
    private $company_details;

    function __construct($title = '', $company_name = '', $company_details = '') {
        parent::__construct();
        $this->title = $title;
        $this->company_name = $company_name;
        $this->company_details = $company_details;
    }

    function Header() {
        // Company Logo and Name
        if (!empty($this->company_name)) {
            $this->SetFont('Arial', 'B', 16);
            $this->Cell(0, 10, $this->cleanText($this->company_name), 0, 1, 'C');
        }

        if (!empty($this->company_details)) {
            $this->SetFont('Arial', '', 10);
            $this->MultiCell(0, 5, $this->cleanText($this->company_details), 0, 'C');
        }

        // Report Title
        if (!empty($this->title)) {
            $this->SetFont('Arial', 'B', 14);
            $this->Cell(0, 10, $this->cleanText($this->title), 0, 1, 'C');
        }

        // Line break
        $this->Ln(5);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->Cell(0, 10, 'Page ' . $this->PageNo() . ' / {nb}', 0, 0, 'C');
        $this->Cell(0, 10, 'Generated on: ' . date('d/m/Y H:i:s'), 0, 0, 'R');
    }

    function BasicTable($headers, $data, $colWidths = null) {
        // Calculate column widths if not provided
        if ($colWidths === null) {
            $colWidths = array_fill(0, count($headers), 190 / count($headers));
        }

        // Colors, line width and bold font
        $this->SetFillColor(59, 89, 152);
        $this->SetTextColor(255);
        $this->SetDrawColor(128, 128, 128);
        $this->SetLineWidth(.3);
        $this->SetFont('Arial', 'B');

        // Header
        for ($i = 0; $i < count($headers); $i++) {
            $this->Cell($colWidths[$i], 7, $this->cleanText($headers[$i]), 1, 0, 'C', true);
        }
        $this->Ln();

        // Color and font restoration
        $this->SetFillColor(224, 235, 255);
        $this->SetTextColor(0);
        $this->SetFont('Arial', '');

        // Data
        $fill = false;
        foreach ($data as $row) {
            for ($i = 0; $i < count($headers); $i++) {
                $value = isset($row[$i]) ? $this->cleanText($row[$i]) : '';
                $this->Cell($colWidths[$i], 6, $value, 'LR', 0, 'L', $fill);
            }
            $this->Ln();
            $fill = !$fill;
        }

        // Closing line
        $this->Cell(array_sum($colWidths), 0, '', 'T');
    }

    function ImprovedTable($headers, $data, $colWidths = null, $aligns = null) {
        // Calculate column widths if not provided
        if ($colWidths === null) {
            $colWidths = array_fill(0, count($headers), 190 / count($headers));
        }

        // Header
        $this->SetFont('Arial', 'B', 10);
        for ($i = 0; $i < count($headers); $i++) {
            $this->Cell($colWidths[$i], 7, $this->cleanText($headers[$i]), 1, 0, 'C'); // Headers are always centered
        }
        $this->Ln();

        // Data
        $this->SetFont('Arial', '', 9);
        foreach ($data as $row) {
            // Check if we need a page break
            if ($this->GetY() > 250) {
                $this->AddPage();
                // Reprint header
                $this->SetFont('Arial', 'B', 10);
                for ($i = 0; $i < count($headers); $i++) {
                    $this->Cell($colWidths[$i], 7, $this->cleanText($headers[$i]), 1, 0, 'C');
                }
                $this->Ln();
                $this->SetFont('Arial', '', 9);
            }

            for ($i = 0; $i < count($headers); $i++) {
                $value = isset($row[$i]) ? $this->cleanText($row[$i]) : '';
                $align = $aligns[$i] ?? 'L'; // Use provided alignment or default to Left
                $this->Cell($colWidths[$i], 6, $value, 1, 0, $align);
            }
            $this->Ln();
        }
    }

    function cleanText($text) {
        // Remove any characters that might cause issues in PDF
        if (is_numeric($text)) {
            return (string)$text;
        }
        
        if (!is_string($text)) {
            return '';
        }
        
        // Remove non-printable characters and handle encoding
        $text = preg_replace('/[^\x20-\x7E\x0A\x0D]/', '', $text);
        
        // Convert to ASCII-safe string
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/[^\x20-\x7E\x0A\x0D]/', '', $text);
        
        return $text;
    }

    function AddSummary($title, $data) {
        $this->SetFont('Arial', 'B', 12);
        $this->Cell(0, 10, $this->cleanText($title), 0, 1);
        $this->SetFont('Arial', '', 10);

        foreach ($data as $key => $value) {
            $this->Cell(95, 6, $this->cleanText($key) . ':', 0, 0, 'R');
            $this->Cell(95, 6, $this->cleanText($value), 0, 1, 'L');
        }
        $this->Ln(5);
    }
}
?>