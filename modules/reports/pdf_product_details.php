<?php
session_start();
error_reporting(0);
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";
require_once "../../includes/pdf_helper.php";

$database = new Database();
$db = $database->getConnection();

// Fetch all products with categories
$products = $db->query("SELECT p.*, pc.name as category_name 
                       FROM ff_sch.products p 
                       LEFT JOIN ff_sch.product_categories pc ON p.category_id = pc.id 
                       WHERE p.is_active = true 
                       ORDER BY p.name")->fetchAll(PDO::FETCH_ASSOC);

// Get product statistics
$product_stats = [];

foreach($products as $product) {
    $stats_query = "SELECT 
                    COUNT(ii.id) as times_sold,
                    SUM(ii.qty) as total_quantity,
                    SUM(ii.total_amount) as total_sales,
                    MAX(i.invoice_date) as last_sold
                   FROM ff_sch.invoice_items ii 
                   LEFT JOIN ff_sch.invoices i ON ii.invoice_id = i.id
                   WHERE ii.product_id = :product_id";
    $stmt = $db->prepare($stats_query);
    $stmt->bindParam(':product_id', $product['id']);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $product_stats[$product['id']] = $stats;
}

// Get company details
$company = $db->query("SELECT * FROM ff_sch.companies LIMIT 1")->fetch(PDO::FETCH_ASSOC);

$comp_name = preg_replace('/\s+/', ' ', trim($company['name']));
// Create PDF in Landscape mode
$pdf = new PDFHelper('Product Details Report', trim($comp_name), $company['address']);
$pdf->AliasNbPages();
$pdf->AddPage(); // Landscape orientation
$pdf->setAutoPageBreak(false);

// Modern color scheme
$primaryColor = array(39, 174, 96);    // Green theme for products
$secondaryColor = array(46, 204, 113); // Light Green
$accentColor = array(241, 196, 15);    // Yellow
$headerColor = array(44, 62, 80);      // Dark Blue
$warningColor = array(230, 126, 34);   // Orange
$lightGray = array(245, 245, 245);

// Report Header with modern styling
$pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 16);
$pdf->Cell(0, 12, 'PRODUCT DETAILS REPORT', 0, 1, 'C', true);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell(0, 6, 'Generated on: ' . date('F j, Y g:i A'), 0, 1, 'C', true);
$pdf->Ln(5);

// Reset text color
$pdf->SetTextColor(0, 0, 0);

// Summary Statistics with modern cards design
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor($headerColor[0], $headerColor[1], $headerColor[2]);
$pdf->Cell(0, 10, 'Executive Summary', 0, 1);
$pdf->SetTextColor(0, 0, 0);

$total_products = count($products);
$hsn_count = 0;
$categories = [];
$total_sales = 0;
$never_sold = 0;
$top_selling_count = 0;

$master = $db->query("SELECT * FROM ff_sch.master WHERE is_active = true LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$hsn_cd = $master['hsn_code'];

foreach($products as $product) {
    if(!empty($product['hsn_code'])) $hsn_count++;
    if($product['category_name']) {
        $categories[$product['category_name']] = true;
    }
    
    $stats = $product_stats[$product['id']] ?? [];
    $product_sales = $stats['total_sales'] ?? 0;
    $total_sales += $product_sales;
    
    if(($stats['times_sold'] ?? 0) === 0) {
        $never_sold++;
    }
    
    if($product_sales > 10000) { // Consider products with sales > 10,000 as top selling
        $top_selling_count++;
    }
}

// Summary cards in a 2x2 grid
$pdf->SetFont('Arial', '', 10);
$cardWidth = 65;
$cardHeight = 25;
$spacing = 5;
$startX = $pdf->GetX();
$startY = $pdf->GetY();

// Card 1: Total Products
$pdf->SetFillColor($primaryColor[0], $primaryColor[1], $primaryColor[2]);
$pdf->SetTextColor(255, 255, 255);
$pdf->Cell($cardWidth, $cardHeight, '', 0, 0, 'C', true);
$pdf->SetXY($startX, $startY);
$pdf->Cell($cardWidth, 8, 'TOTAL PRODUCTS', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetX($startX);
$pdf->Cell($cardWidth, 10, number_format($total_products), 0, 1, 'C');
$pdf->SetFont('Arial', '', 8);
$pdf->SetX($startX);
$pdf->Cell($cardWidth, 6, 'Active Inventory', 0, 1, 'C');

// Card 4: Never Sold
// $pdf->SetXY($startX + $cardWidth + $spacing, $startY + $cardHeight + $spacing);
$pdf->SetXY($startX + $cardWidth + $spacing, $startY);
$pdf->SetFillColor($warningColor[0], $warningColor[1], $warningColor[2]);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell($cardWidth, $cardHeight, '', 0, 0, 'C', true);
// $pdf->SetXY($startX + $cardWidth + $spacing, $startY + $cardHeight + $spacing);
$pdf->SetXY($startX + $cardWidth + $spacing, $startY);
$pdf->Cell($cardWidth, 8, 'NEVER SOLD', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetX($startX + $cardWidth + $spacing);
$pdf->Cell($cardWidth, 10, number_format($never_sold), 0, 1, 'C');
$pdf->SetFont('Arial', '', 8);
$pdf->SetX($startX + $cardWidth + $spacing);
$pdf->Cell($cardWidth, 6, number_format(($never_sold/$total_products)*100, 1) . '% of inventory', 0, 1, 'C');

// Card 2: HSN Registered
/* $pdf->SetXY($startX + $cardWidth + $spacing, $startY);
$pdf->SetFillColor($secondaryColor[0], $secondaryColor[1], $secondaryColor[2]);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell($cardWidth, $cardHeight, '', 0, 0, 'C', true);
$pdf->SetXY($startX + $cardWidth + $spacing, $startY);
$pdf->Cell($cardWidth, 8, 'HSN REGISTERED', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetX($startX + $cardWidth + $spacing);
$pdf->Cell($cardWidth, 10, number_format($hsn_count), 0, 1, 'C');
$pdf->SetFont('Arial', '', 8);
$pdf->SetX($startX + $cardWidth + $spacing);
$pdf->Cell($cardWidth, 6, number_format(($hsn_count/$total_products)*100, 1) . '% coverage', 0, 1, 'C'); */

// Card 3: Categories
/* $pdf->SetXY($startX, $startY + $cardHeight + $spacing);
$pdf->SetFillColor($accentColor[0], $accentColor[1], $accentColor[2]);
$pdf->SetFont('Arial', '', 10);
$pdf->Cell($cardWidth, $cardHeight, '', 0, 0, 'C', true);
$pdf->SetXY($startX, $startY + $cardHeight + $spacing);
$pdf->Cell($cardWidth, 8, 'CATEGORIES', 0, 1, 'C');
$pdf->SetFont('Arial', 'B', 16);
$pdf->SetX($startX);
$pdf->Cell($cardWidth, 10, number_format(count($categories)), 0, 1, 'C');
$pdf->SetFont('Arial', '', 8);
$pdf->SetX($startX);
$pdf->Cell($cardWidth, 6, 'Product Groups', 0, 1, 'C'); */



// $pdf->SetXY($startX, $startY + (2 * $cardHeight) + (2 * $spacing));
$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(10);

// Products Table with modern styling
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor($headerColor[0], $headerColor[1], $headerColor[2]);
$pdf->Cell(0, 10, 'Product Inventory Details', 0, 1);

// Adjusted column widths for landscape
$headers = ['ID', 'Product Name', 'Rate', 'HSN', 'Sold', 'Qty', 'Sales', 'Status'];
$widths = [20, 55, 25, 20, 15, 15, 25, 20];
$alignments = ['C', 'L', 'R', 'C', 'C', 'C', 'R', 'C']; // Added alignments array

$data = [];
foreach($products as $product) {
    $stats = $product_stats[$product['id']] ?? ['times_sold' => 0, 'total_quantity' => 0, 'total_sales' => 0, 'last_sold' => null];
    
    // Determine product status
    $status = '';
    $times_sold = $stats['times_sold'] ?? 0;
    $total_sales_amount = $stats['total_sales'] ?? 0;
    
    if ($times_sold === 0) {
        $status = 'Never Sold';
    } elseif ($times_sold <= 5) {
        $status = 'Low Sales';
    } elseif ($total_sales_amount > 10000) {
        $status = 'Top Seller';
    } else {
        $status = 'Regular';
    }
    
    // Format sales with emphasis for high values
    $sales_display = CURRENCY . ' ' . number_format($total_sales_amount, 2);
    
    $data[] = [
        $product['product_id'],
        substr($product['name'], 0, 35),
        CURRENCY . ' ' . number_format($product['rate'], 2),
        $hsn_cd ?: '-',
        $times_sold,
        $stats['total_quantity'] ?? 0,
        $sales_display,
        $status
    ];
}

// Function to draw table header
function drawTableHeader($pdf, $headers, $widths, $headerColor, $lightGray) {
    $pdf->SetFillColor($lightGray[0], $lightGray[1], $lightGray[2]);
    $pdf->SetTextColor($headerColor[0], $headerColor[1], $headerColor[2]);
    $pdf->SetDrawColor(200, 200, 200);
    $pdf->SetLineWidth(0.3);
    $pdf->SetFont('Arial', 'B', 9);
    
    for($i = 0; $i < count($headers); $i++) {
        $pdf->Cell($widths[$i], 8, $headers[$i], 1, 0, 'C', true);
    }
    $pdf->Ln();
    
    // Reset for data
    $pdf->SetTextColor(0, 0, 0);
    $pdf->SetFont('Arial', '', 8);
}

// Function to draw table bottom border
function drawTableBottomBorder($pdf, $widths) {
    $pdf->Cell(array_sum($widths), 0, '', 'T');
    $pdf->Ln(5);
}

// Draw initial table header
drawTableHeader($pdf, $headers, $widths, $headerColor, $lightGray);

// Table data with page break handling
$fill = false;
$rowHeight = 6;
$currentY = $pdf->GetY();
$pageHeight = 190; // Approximate page height in landscape

foreach($data as $rowIndex => $row) {
    // Check if we need a new page
    if ($currentY + $rowHeight > $pageHeight) {
        // Draw bottom border for current page
        drawTableBottomBorder($pdf, $widths);
        
        $pdf->AddPage();
        drawTableHeader($pdf, $headers, $widths, $headerColor, $lightGray);
        $currentY = $pdf->GetY();
        $fill = false; // Reset fill pattern on new page
    }
    
    // Alternate row background
    if($fill) {
        $pdf->SetFillColor(248, 248, 248);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }
    
    for($i = 0; $i < count($row); $i++) {
        $pdf->Cell($widths[$i], $rowHeight, $row[$i], 'LR', 0, $alignments[$i], $fill);
    }
    $pdf->Ln();
    $fill = !$fill;
    $currentY = $pdf->GetY();
}

// Draw final bottom border for the table
drawTableBottomBorder($pdf, $widths);
$pdf->Ln(5);
$pdf->AddPage();

/* 
// Category-wise Distribution
$pdf->SetFont('Arial', 'B', 14);
$pdf->SetTextColor($headerColor[0], $headerColor[1], $headerColor[2]);

$pdf->Cell(0, 10, 'Category-wise Product Performance', 0, 1);

$category_stats = [];
foreach($products as $product) {
    $category = $product['category_name'] ?: 'General';
    if(!isset($category_stats[$category])) {
        $category_stats[$category] = [
            'count' => 0, 
            'sales' => 0,
            'products' => 0,
            'avg_rate' => 0
        ];
    }
    $category_stats[$category]['count']++;
    $category_stats[$category]['sales'] += ($product_stats[$product['id']]['total_sales'] ?? 0);
    $category_stats[$category]['avg_rate'] += $product['rate'];
    $category_stats[$category]['products']++;
}

// Calculate average rate per category
foreach($category_stats as $category => $stats) {
    if($stats['products'] > 0) {
        $category_stats[$category]['avg_rate'] = $stats['avg_rate'] / $stats['products'];
    }
}

// Sort by sales descending
uasort($category_stats, function($a, $b) {
    return $b['sales'] - $a['sales'];
});

$headers = ['Category', 'Products', 'Percentage', 'Avg Rate', 'Total Sales', 'Performance'];
$widths = [50, 25, 25, 25, 35, 30];
$alignments = ['L', 'C', 'C', 'R', 'R', 'C']; // Right align for amount fields

$data = [];
foreach($category_stats as $category => $stats) {
    $percentage = ($stats['count'] / $total_products) * 100;
    $sales_per_product = $stats['count'] > 0 ? $stats['sales'] / $stats['count'] : 0;
    
    // Performance indicator
    if($sales_per_product > 5000) {
        $performance = 'Excellent 🟢';
    } elseif($sales_per_product > 1000) {
        $performance = 'Good 🔵';
    } elseif($sales_per_product > 0) {
        $performance = 'Average 🟡';
    } else {
        $performance = 'No Sales 🔴';
    }
    
    $data[] = [
        substr($category, 0, 25),
        $stats['count'],
        number_format($percentage, 1) . '%',
        CURRENCY . ' ' . number_format($stats['avg_rate'], 2),
        CURRENCY . ' ' . number_format($stats['sales'], 2),
        $performance
    ];
}

// Draw category table with header handling
drawTableHeader($pdf, $headers, $widths, $headerColor, $lightGray);
$currentY = $pdf->GetY();
$fill = false;

foreach($data as $rowIndex => $row) {
    if ($currentY + $rowHeight > $pageHeight) {
        // Draw bottom border for current page
        drawTableBottomBorder($pdf, $widths);
        
        $pdf->AddPage();
        drawTableHeader($pdf, $headers, $widths, $headerColor, $lightGray);
        $currentY = $pdf->GetY();
        $fill = false;
    }
    
    if($fill) {
        $pdf->SetFillColor(248, 248, 248);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }
    
    for($i = 0; $i < count($row); $i++) {
        $pdf->Cell($widths[$i], $rowHeight, $row[$i], 'LR', 0, $alignments[$i], $fill);
    }
    $pdf->Ln();
    $fill = !$fill;
    $currentY = $pdf->GetY();
}

// Draw final bottom border for the table
drawTableBottomBorder($pdf, $widths);
$pdf->Ln(10); */

// Top Selling Products
$pdf->SetFont('Arial', 'B', 14);
$pdf->Cell(0, 10, 'Top 15 Selling Products', 0, 1);

$top_products = [];
foreach($products as $product) {
    $stats = $product_stats[$product['id']] ?? ['times_sold' => 0, 'total_quantity' => 0, 'total_sales' => 0];
    if($stats['total_sales'] > 0) {
        $top_products[] = [
            'name' => $product['name'],
            'rate' => $product['rate'],
            'times_sold' => $stats['times_sold'],
            'total_quantity' => $stats['total_quantity'],
            'total_sales' => $stats['total_sales'],
            'last_sold' => $stats['last_sold'] ?? null
        ];
    }
}

// Sort by total sales descending
usort($top_products, function($a, $b) {
    return $b['total_sales'] - $a['total_sales'];
});

$top_products = array_slice($top_products, 0, 15);

$headers = ['Rank', 'Product Name', 'Rate', 'Sold', 'Qty', 'Sales', 'Status'];
$widths = [15, 65, 25, 15, 15, 30, 25];
$alignments = ['C', 'L', 'R', 'C', 'C', 'R', 'C']; // Right align for amount fields

$data = [];
$rank = 1;
foreach($top_products as $product) {
    // Sales status based on recency
    $status = 'Active';
    if ($product['last_sold']) {
        $days_ago = (time() - strtotime($product['last_sold'])) / (60 * 60 * 24);
        if ($days_ago <= 7) {
            $status = 'Hot';
        } elseif ($days_ago <= 30) {
            $status = 'Active';
        } else {
            $status = 'Stale';
        }
    }
    
    $data[] = [
        '#' . $rank++,
        substr($product['name'], 0, 40),
        substr($product['category'], 0, 20),
        CURRENCY . ' ' . number_format($product['rate'], 2),
        $product['times_sold'],
        $product['total_quantity'],
        CURRENCY . ' ' . number_format($product['total_sales'], 2),
        $status
    ];
}

// Draw top products table with header handling
drawTableHeader($pdf, $headers, $widths, $headerColor, $lightGray);
$currentY = $pdf->GetY();
$fill = false;

foreach($data as $rowIndex => $row) {
    if ($currentY + $rowHeight > $pageHeight) {
        // Draw bottom border for current page
        drawTableBottomBorder($pdf, $widths);
        
        $pdf->AddPage();
        drawTableHeader($pdf, $headers, $widths, $headerColor, $lightGray);
        $currentY = $pdf->GetY();
        $fill = false;
    }
    
    if($fill) {
        $pdf->SetFillColor(248, 248, 248);
    } else {
        $pdf->SetFillColor(255, 255, 255);
    }
    
    for($i = 0; $i < count($row); $i++) {
        $pdf->Cell($widths[$i], $rowHeight, $row[$i], 'LR', 0, $alignments[$i], $fill);
    }
    $pdf->Ln();
    $fill = !$fill;
    $currentY = $pdf->GetY();
}

// Draw final bottom border for the table
drawTableBottomBorder($pdf, $widths);

// Footer with summary
$pdf->SetY(-30);
$pdf->SetFont('Arial', 'I', 8);
$pdf->SetTextColor(100, 100, 100);
$pdf->Cell(0, 6, 'Report Summary: ' . $total_products . ' products across ' . count($categories) . ' categories with total sales of ' . CURRENCY . ' ' . number_format($total_sales, 2), 0, 1);
$pdf->Cell(0, 6, 'Generated by: ' . ($_SESSION['user_name'] ?? 'System'), 0, 1);
$pdf->Cell(0, 6, 'Page ' . $pdf->PageNo() . ' of {nb}', 0, 1);

// Output PDF
$pdf->Output('I', 'Product_Details_Report_' . date('Y-m-d') . '.pdf');
?>