<?php
$database = new Database();
$db = $database->getConnection();
$company_query = "SELECT * FROM ff_sch.companies LIMIT 1";
$company = $db->query($company_query)->fetch(PDO::FETCH_ASSOC);

// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - <?php echo $page_title ?? 'Dashboard'; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/img/favicon.png">
    
    <!-- Bootstrap CSS -->
    <link href="<?php echo ASSETS_URL; ?>/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="<?php echo ASSETS_URL; ?>/css/all.min.css" rel="stylesheet">
    
    <!-- DataTables CSS -->
    <link href="<?php echo ASSETS_URL; ?>/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    
    <!-- SweetAlert2 CSS -->
    <!-- <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css"> -->
    
    <script src="<?php echo ASSETS_URL; ?>/js/sweetalert2@11.js"></script>
</head>
<body>
    <!-- Mobile Overlay -->
    <div class="mobile-overlay" id="mobileOverlay"></div>

    <!-- Main Header -->
    <header class="main-header expanded" id="mainHeader">
        <div class="header-content">
            <div class="header-left">
                <!-- Header Toggle Button -->
                <button class="header-toggle" id="headerToggle">
                    <i class="fas fa-bars"></i>
                </button>
                
                <!-- Brand -->
                <div class="header-brand" href="<?php echo BASE_URL; ?>dashboard.php">
                    <i class="fas fa-cash-register"></i>
                    <span><?php echo $company['name'] ?? COMPANY_NAME; ?></span>
                </div>
            </div>
            
            <div class="header-right">
                <!-- User Info -->
                <div class="user-info">
                    <div class="user-avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <span class="user-name"><?php echo $_SESSION['full_name']; ?></span>
                </div>
                
                <!-- Mobile Menu Toggle -->
                <button class="header-toggle d-md-none" id="mobileMenuToggle">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
            </div>
        </div>
    </header>