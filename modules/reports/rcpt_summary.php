<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";
require_once "../../includes/audit_helper.php";

$database = new Database();
$db = $database->getConnection();

// Filter parameters
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-d');

// Fetch agents for dropdown
$agent_query = "(SELECT DISTINCT agent_name FROM ff_sch.estimate WHERE agent_name IS NOT NULL AND agent_name <> '')
                 UNION
                 (SELECT DISTINCT agent_name FROM ff_sch.invoices WHERE agent_name IS NOT NULL AND agent_name <> '')
                 ORDER BY agent_name";
$agent_stmt = $db->prepare($agent_query);
$agent_stmt->execute();
$agents = $agent_stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch parties for dropdown
$party_query = "SELECT p.id, p.name FROM ff_sch.parties p, ff_sch.estimate e WHERE p.id = e.party_id ORDER BY p.name";
$party_stmt = $db->prepare($party_query);
$party_stmt->execute();
$parties = $party_stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <base href="<?php echo BASE_URL; ?>/">
    <title>Receipt Summary - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/img/favicon.png">
    <link href="<?php echo ASSETS_URL; ?>/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/all.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/style.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="<?php echo ASSETS_URL; ?>/css/select2.min.css" rel="stylesheet">
    <link href="<?php echo ASSETS_URL; ?>/css/select2-bootstrap4.min.css" rel="stylesheet" />

    <style>
        .report-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 0 30px rgba(0,0,0,0.08);
            padding: 30px;
            margin-top: 20px;
        }
        
        .report-header {
            /* background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); */
            /* color: white; */
            /* padding: 20px 25px; */
            border-radius: 10px;
            margin-bottom: 25px;
        }
        
        .report-header h2 {
            margin: 0;
            font-weight: 300;
        }
        
        .report-header i {
            margin-right: 10px;
        }
        
        .filter-section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 25px;
        }
        
        .filter-section .form-label {
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .filter-section .form-control,
        .filter-section .form-select {
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            transition: all 0.3s ease;
        }
        
        .filter-section .form-control:focus,
        .filter-section .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .radio-group {
            background: white;
            border-radius: 10px;
            padding: 15px 40px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .radio-group:hover {
            border-color: #667eea;
        }
        
        .radio-group .form-check {
            padding: 8px 0;
            margin: 0;
        }
        
        .radio-group .form-check-input {
            width: 18px;
            height: 18px;
            margin-top: 2px;
        }
        
        .radio-group .form-check-input:checked {
            background-color: #667eea;
            border-color: #667eea;
        }
        
        .radio-group .form-check-label {
            font-weight: 500;
            color: #333;
            padding-left: 8px;
        }
        
        .combo-box-container {
            background: white;
            border-radius: 10px;
            padding: 15px 20px;
            border: 2px solid #e9ecef;
            transition: all 0.3s ease;
        }
        
        .combo-box-container:hover {
            border-color: #667eea;
        }
        
        .combo-box-container label {
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .select2-container--bootstrap-5 .select2-selection {
            border-radius: 8px !important;
            border: 1px solid #e0e0e0 !important;
            min-height: 38px !important;
        }
        
        .select2-container--bootstrap-5 .select2-selection:hover {
            border-color: #667eea !important;
        }
        
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            line-height: 36px !important;
            padding-left: 12px !important;
        }
        
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
            height: 36px !important;
        }
        
        /* .btn-generate {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 10px 30px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
         */
        /* .btn-generate:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .btn-generate i {
            margin-right: 8px;
        } */
        
        .hidden-section {
            display: none;
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .badge-radio {
            background: #e9ecef;
            color: #495057;
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            margin-left: 10px;
        }
    </style>
</head>
<body>
    <?php include "../../includes/header.php"; ?>
    <?php include "../../includes/sidebar.php"; ?>
    
    <div class="container-fluid">
        <div class="report-container">
            <!-- Report Header -->
            <div class="report-header">
                <h2>
                    <i class="fas fa-file-invoice"></i> Receipt Summary
                </h2>
            </div>

            <!-- Filter Section -->
            <div class="filter-section">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label for="from-date" class="form-label">
                            <i class="fas fa-calendar-alt"></i> From Date
                        </label>
                        <input type="date" id="from-date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label for="to-date" class="form-label">
                            <i class="fas fa-calendar-alt"></i> To Date
                        </label>
                        <input type="date" id="to-date" class="form-control" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-md-6">
                        <label for="paymentTypeSelect" class="form-label"><i class="fas fa-money-check-alt"></i> Payment Type</label>
                        <select id="paymentTypeSelect" class="form-select">
                            <option value="all" selected>All Payment Types</option>
                            <option value="Cash">Cash</option>
                            <option value="G-Pay">G-Pay</option>
                            <option value="Bank Transfer">Bank Transfer</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="receiptAgainstSelect" class="form-label"><i class="fas fa-file-alt"></i> Receipt Against</label>
                        <select id="receiptAgainstSelect" class="form-select">
                            <option value="all" selected>All</option>
                            <option value="estimate">Estimate</option>
                            <option value="invoice">Invoice</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Report Type & Selection Section -->
            <div class="row">
                <!-- Radio Buttons -->
                <div class="col-md-6 mb-3">
                    <div class="radio-group">
                        <h6 class="mb-2" style="color: #495057; font-weight: 600;">
                            <i class="fas fa-chart-pie"></i> Report Type
                        </h6>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="reportType" id="agentWise" value="agent" checked>
                            <label class="form-check-label" for="agentWise">
                                <i class="fas fa-user-tie"></i> Agent Wise Report
                                <span class="badge-radio">Individual</span>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="reportType" id="partyWise" value="party">
                            <label class="form-check-label" for="partyWise">
                                <i class="fas fa-users"></i> Party Wise Report
                                <span class="badge-radio">Group</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 mb-3">
                <!-- Agent Combo Box -->
                    <div class="combo-box-container" id="agentContainer">
                        <label for="agentSelect">
                            <i class="fas fa-user-tie"></i> Select Agent
                        </label>
                        <select id="agentSelect" class="form-select select2-agent" style="width: 100%;">
                            <option value="">-- Select Agent --</option>
                            <option value="all">All Agents</option>
                            <?php foreach ($agents as $agent): ?>
                                <option value="<?php echo $agent['agent_name']; ?>">
                                    <?php echo htmlspecialchars($agent['agent_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <!-- Party Combo Box -->
                    <div class="combo-box-container hidden-section" id="partyContainer">
                        <label for="partySelect">
                            <i class="fas fa-users"></i> Select Party
                        </label>
                        <select id="partySelect" class="form-select select2-party" style="width: 100%;">
                            <option value="">-- Select Party --</option>
                            <option value="all">All Parties</option>
                            <?php foreach ($parties as $party): ?>
                                <option value="<?php echo $party['id']; ?>">
                                    <?php echo htmlspecialchars($party['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                </div>
            </div>

            <!-- Generate Button -->
            <div class="row mt-3">
                <div class="col-12 text-center">
                    <button type="button" class="btn btn-primary btn-generate" id="generateReport">
                        <i class="fas fa-file-alt"></i> Generate Report
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="<?php echo ASSETS_URL; ?>/js/jquery-3.6.0.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/jquery.dataTables.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/dataTables.bootstrap5.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/sweetalert2@11.js"></script>
    <!-- Select2 JS -->
    <script src="<?php echo ASSETS_URL; ?>/js/select2.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/script.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/sidebar.js"></script>

    <script>
        $(document).ready(function() {
            // Store the base URL
            var baseUrl = '<?php echo BASE_URL; ?>';
            
            // Initialize Select2 for Agent
            $('.select2-agent').select2({
                theme: 'bootstrap4',
                placeholder: '-- Select Agent --',
                allowClear: true,
                width: '100%'
            }).on('select2:open', function() {
                setTimeout(() => {
                    document.querySelector('.select2-container--open .select2-search__field').focus();
                }, 100);
            });

            // Initialize Select2 for Party
            $('.select2-party').select2({
                theme: 'bootstrap4',
                placeholder: '-- Select Party --',
                allowClear: true,
                width: '100%'
            }).on('select2:open', function() {
                setTimeout(() => {
                    document.querySelector('.select2-container--open .select2-search__field').focus();
                }, 100);
            });

            // Radio button change handler
            $('input[name="reportType"]').on('change', function() {
                const selectedValue = $(this).val();
                
                if (selectedValue === 'agent') {
                    $('#agentContainer').removeClass('hidden-section').addClass('fade-in');
                    $('#partyContainer').addClass('hidden-section').removeClass('fade-in');
                    $('#agentSelect').prop('required', true);
                    $('#partySelect').prop('required', false);
                } else if (selectedValue === 'party') {
                    $('#partyContainer').removeClass('hidden-section').addClass('fade-in');
                    $('#agentContainer').addClass('hidden-section').removeClass('fade-in');
                    $('#partySelect').prop('required', true);
                    $('#agentSelect').prop('required', false);
                }
            });

            // Trigger change on load to show default selection
            $('input[name="reportType"]:checked').trigger('change');

            // Generate Report button click - Simplified
            $('#generateReport').on('click', function() {
                const reportType = $('input[name="reportType"]:checked').val();
                const fromDate = $('#from-date').val();
                const toDate = $('#to-date').val();
                const paymentType = $('#paymentTypeSelect').val();
                const receiptAgainst = $('#receiptAgainstSelect').val();
                let selectedId = '';
                let selectedName = '';

                // Validation
                if (!fromDate || !toDate) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Missing Dates',
                        text: 'Please select both From and To dates.',
                        confirmButtonColor: '#667eea'
                    });
                    return;
                }

                if (fromDate > toDate) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Date Range',
                        text: 'From Date cannot be greater than To Date.',
                        confirmButtonColor: '#667eea'
                    });
                    return;
                }

                if (reportType === 'agent') {
                    selectedId = $('#agentSelect').val();
                    selectedName = $('#agentSelect option:selected').text().trim();
                    if (!selectedId) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Agent Required',
                            text: 'Please select an agent to generate the report.',
                            confirmButtonColor: '#667eea'
                        });
                        return;
                    }
                } else if (reportType === 'party') {
                    selectedId = $('#partySelect').val();
                    selectedName = $('#partySelect option:selected').text().trim();
                    if (!selectedId) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Party Required',
                            text: 'Please select a party to generate the report.',
                            confirmButtonColor: '#667eea'
                        });
                        return;
                    }
                }

                // Build the URL with parameters
                var reportUrl = baseUrl + 'modules/receipt/receipt_report.php?';
                reportUrl += 'report_type=' + encodeURIComponent(reportType);
                reportUrl += '&from_date=' + encodeURIComponent(fromDate);
                reportUrl += '&to_date=' + encodeURIComponent(toDate);
                reportUrl += '&selected_id=' + encodeURIComponent(selectedId);
                reportUrl += '&selected_name=' + encodeURIComponent(selectedName);
                reportUrl += '&payment_type=' + encodeURIComponent(paymentType);
                reportUrl += '&receipt_against=' + encodeURIComponent(receiptAgainst);

                // Open in new window
                window.open(reportUrl, '_blank');
            });

            // Keyboard shortcut: Ctrl+Enter to generate report
            $(document).on('keydown', function(e) {
                if (e.ctrlKey && e.key === 'Enter') {
                    $('#generateReport').click();
                }
            });
        });
    </script>
</body>
</html>