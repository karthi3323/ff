<?php 
function isActiveMenu($module, $page) {
    $currentUri = $_SERVER['REQUEST_URI'];
    return strpos($currentUri, $module) !== false && basename($currentUri) == $page;
}
$current_page = basename($_SERVER['SCRIPT_NAME']);
?>    
<style>
    .nav-link[data-bs-toggle="collapse"] .arrow-icon {
        transition: transform 0.3s ease;
    }
    .nav-link[data-bs-toggle="collapse"]:not(.collapsed) .arrow-icon {
        transform: rotate(180deg);
    }
    .collapse .nav-link {
        padding: 8px 15px;
        font-size: 0.9em;
    }
    .collapse .nav-link i.fa-minus {
        margin-right: 8px;
        opacity: 0.5;
    }
</style>
<!-- Sidebar -->
    <nav class="sidebar" id="sidebar">
        <div class="sidebar-content">
            <!-- Sidebar Header -->
            <div class="sidebar-header">
                <div class="sidebar-title">
                    <img id="imgSrc" src="<?php echo ASSETS_URL; ?>/img/d_logo.png" alt="Friends">
                </div>
            </div>

            <!-- Sidebar Navigation -->
            <div class="sidebar-nav">
                <!-- Main Navigation Section -->
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <ul class="nav-items">
                        <li class="nav-item">
                            <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'dashboard') !== false ? 'active' : ''; ?>" 
                               href="<?php echo BASE_URL; ?>dashboard.php">
                                <i class="fas fa-tachometer-alt"></i>
                                <span class="nav-text">Dashboard</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Invoice Section -->
                <div class="nav-section">
                    <div class="nav-section-title">Invoices</div>
                    <ul class="nav-items">
                        <li class="nav-item">
                            <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'quotation') !== false ? 'active' : ''; ?>" 
                               href="<?php echo BASE_URL; ?>modules/quotation/view.php">
                                <i class="fas fa-file-invoice"></i>
                                <span class="nav-text">Quotations</span>
                            </a> 
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'estimate') !== false ? 'active' : ''; ?>" 
                               href="<?php echo BASE_URL; ?>modules/estimate/view.php">
                                <i class="fas fa-file-contract"></i>
                                <span class="nav-text">Estimates</span>
                            </a> 
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'invoice') !== false ? 'active' : ''; ?>" 
                               href="<?php echo BASE_URL; ?>modules/invoice/view.php">
                                <i class="fas fa-list-alt"></i>
                                <span class="nav-text">Invoices</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Management Section -->
                 <div class="nav-section">
                    <div class="nav-section-title">Management</div>
                    <ul class="nav-items">
                        <li class="nav-item">
                            <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'receipt') !== false ? 'active' : ''; ?>" 
                               href="<?php echo BASE_URL; ?>modules/receipt/view.php">
                                <i class="fas fa-receipt"></i>
                                <span class="nav-text">Receipts</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'parties') !== false ? 'active' : ''; ?>" 
                            href="<?php echo BASE_URL; ?>modules/parties/list.php">
                                <i class="fas fa-users"></i>
                                <span class="nav-text">Party</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'products') !== false ? 'active' : ''; ?>" 
                            href="<?php echo BASE_URL; ?>modules/products/list.php">
                                <i class="fas fa-boxes"></i>
                                <span class="nav-text">Products</span>
                            </a>
                        </li>

                        <li class="nav-item">
                            <a class="nav-link <?php echo isActiveMenu('transport', 'list.php') ? 'active' : ''; ?>" 
                            href="<?php echo BASE_URL; ?>modules/transport/list.php">
                                <i class="fa-solid fa-dolly"></i>
                                <span class="nav-text">Transports</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Reports Section -->
                <div class="nav-section">
                    <div class="nav-section-title">Reports</div>
                    <ul class="nav-items">
                        <li class="nav-item">
                            <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'reports') !== false ? '' : 'collapsed'; ?>" 
                               data-bs-toggle="collapse" data-bs-target="#collapseReports" role="button" style="cursor:pointer;"
                               aria-expanded="<?php echo strpos($_SERVER['REQUEST_URI'], 'reports') !== false ? 'true' : 'false'; ?>">
                                <i class="fas fa-chart-bar"></i>
                                <span class="nav-text">Reports</span>
                                <i class="fas fa-chevron-down ms-auto arrow-icon"></i>
                            </a>
                            <div class="collapse <?php echo strpos($_SERVER['REQUEST_URI'], 'reports') !== false ? 'show' : ''; ?>" id="collapseReports">
                                <ul class="nav flex-column ms-3 mb-1" style="list-style: none;">
                                    <li><a class="nav-link <?php echo $current_page == 'inv_report.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>modules/reports/inv_report.php"><i class="fas fa-minus"></i> Invoice Report</a></li>
                                    <li><a class="nav-link <?php echo $current_page == 'estmt_report.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>modules/reports/estmt_report.php"><i class="fas fa-minus"></i> Estimate Report</a></li>
                                    <li><a class="nav-link <?php echo $current_page == 'daywise_summary.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>modules/reports/daywise_summary.php"><i class="fas fa-minus"></i> Daily Summary</a></li>
                                    <li><a class="nav-link <?php echo $current_page == 'overall_sales.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>modules/reports/overall_sales.php"><i class="fas fa-minus"></i> Sales Overview</a></li>
                                    <li><a class="nav-link <?php echo $current_page == 'party_details.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>modules/reports/party_details.php"><i class="fas fa-minus"></i> Party Details</a></li>
                                    <li><a class="nav-link <?php echo $current_page == 'party_report.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>modules/reports/party_report.php"><i class="fas fa-minus"></i> Party Report</a></li>
                                    <li><a class="nav-link <?php echo $current_page == 'product_details.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>modules/reports/product_details.php"><i class="fas fa-minus"></i> Product Details</a></li>
                                    <li><a class="nav-link <?php echo $current_page == 'sales_summary_report.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>modules/reports/sales_summary_report.php"><i class="fas fa-minus"></i> Sales Summary</a></li>
                                    <li><a class="nav-link <?php echo $current_page == 'rcpt_summary.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>modules/reports/rcpt_summary.php"><i class="fas fa-minus"></i>Receipt Summary</a></li>
                                    <li><a class="nav-link <?php echo $current_page == 'price_list_report.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>modules/reports/price_list_report.php"><i class="fas fa-minus"></i> Price List Report</a></li>
                                </ul>
                            </div>
                        </li>
                    </ul>
                </div>

                <!-- Master Settings Section -->
                <div class="nav-section">
                    <div class="nav-section-title">Settings</div>
                    <ul class="nav-items">
                        <li class="nav-item">
                            <a class="nav-link <?php echo strpos($_SERVER['REQUEST_URI'], 'master') !== false ? '' : 'collapsed'; ?>" 
                               data-bs-toggle="collapse" data-bs-target="#collapseSettings" role="button" style="cursor:pointer;"
                               aria-expanded="<?php echo strpos($_SERVER['REQUEST_URI'], 'master') !== false ? 'true' : 'false'; ?>">
                                <i class="fas fa-cogs"></i>
                                <span class="nav-text">Settings</span>
                                <i class="fas fa-chevron-down ms-auto arrow-icon"></i>
                            </a>
                            <div class="collapse <?php echo strpos($_SERVER['REQUEST_URI'], 'master') !== false ? 'show' : ''; ?>" id="collapseSettings">
                                <ul class="nav flex-column ms-3 mb-1" style="list-style: none;">
                                    <li><a class="nav-link <?php echo $current_page == 'fiscal_year.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>modules/master/fiscal_year.php"><i class="fas fa-minus"></i> Fiscal Year</a></li>
                                    <li><a class="nav-link <?php echo $current_page == 'price_code.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>modules/master/price_code.php"><i class="fas fa-minus"></i> Price Code</a></li>
                                    <li><a class="nav-link <?php echo $current_page == 'backup.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>modules/master/backup.php"><i class="fas fa-minus"></i> Backup & Restore</a></li>
                                    <li><a class="nav-link <?php echo $current_page == 'company.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>modules/master/company.php"><i class="fas fa-minus"></i> Company Info</a></li>
                                    <li><a class="nav-link <?php echo $current_page == 'master.php' ? 'active' : ''; ?>" href="<?php echo BASE_URL; ?>modules/master/master.php"><i class="fas fa-minus"></i> Master Details</a></li>
                                </ul>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Sidebar Footer -->
            <div class="sidebar-footer">
                <a class="logout-link" href="<?php echo BASE_URL; ?>logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="nav-text">Logout</span>
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">