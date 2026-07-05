<?php
// Script to add sidebar.js to all PHP files
$files = [
    'dashboard.php',
    'modules/invoice/view.php',
    'modules/invoice/add.php',
    'modules/parties/list.php',
    'modules/parties/add.php',
    'modules/parties/edit.php',
    'modules/products/list.php',
    'modules/products/add.php',
    'modules/products/edit.php',
    'modules/products/category.php',
    'modules/reports/invoice_report.php',
    'modules/reports/daywise_summary.php',
    'modules/reports/overall_sales.php',
    'modules/reports/party_details.php',
    'modules/reports/product_details.php',
    'modules/master/users.php',
    'modules/master/fiscal_year.php',
    'modules/master/backup.php',
    'modules/master/roles.php',
    'modules/master/company.php',
    'modules/master/discount.php',
    'modules/master/coupon.php'
];

foreach ($files as $file) {
    if (file_exists($file)) {
        $content = file_get_contents($file);
        
        // Check if sidebar.js is already included
        if (strpos($content, 'sidebar.js') === false) {
            // Add sidebar.js before closing body tag
            $newContent = str_replace(
                '</body>',
                "    <script src=\"<?php echo ASSETS_URL; ?>/js/sidebar.js\"></script>\n</body>",
                $content
            );
            
            file_put_contents($file, $newContent);
            echo "Updated: $file\n";
        } else {
            echo "Already has sidebar: $file\n";
        }
    } else {
        echo "File not found: $file\n";
    }
}

echo "Update complete!";
?>