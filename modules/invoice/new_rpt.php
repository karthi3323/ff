<style>
  @media print {
    @page {
      margin: 5mm;
    }
    body {
      margin: 0 !important;
      -webkit-print-color-adjust: exact;
    }
    .tile {
      padding: 0 !important;
      margin: 0 !important;
      border: none !important;
      box-shadow: none !important;
    }
  }

  hr {
    border: 1px solid #000 !important;
  }

  body {
    font-size: 14px !important;
    font-weight: 900 !important;
    color: #000 !important;
  }

  p {
    margin-bottom: 4px !important;
    font-weight: 500 !important;
  }

  .table th,
  .table td {
    padding: 2px 4px !important;
    color: #000 !important;
    height: 24px !important; /* Force a consistent row height */
  }

  table td {
    color: #000 !important;
    border-top: none !important;
  }
</style>
<?php
session_start();
error_reporting(0);
require_once "../../config/database.php";
require_once "../../config/constants.php";
require_once "../../includes/auth.php";

$database = new Database();
$db = $database->getConnection();

if(!isset($_GET['id'])) {
    header("Location: " . BASE_URL . "modules/invoice/view.php");
    exit();
}

$invoice_id = $_GET['id'];
$fiscal_year = $_GET['year'];
if ($invoice_id != '') {

    // Fetch invoice details
    $query = "SELECT i.*, p.name as party_name, TRIM(
            COALESCE(address_line1, '') ||
            CASE 
                WHEN address_line2 IS NOT NULL AND address_line2 <> '' 
                THEN ', ' || address_line2 
                ELSE '' 
            END
        ) AS address, TRIM(
            COALESCE(p.city, '') ||
            CASE 
                WHEN pin_code IS NOT NULL AND pin_code <> '' 
                THEN ', ' || pin_code 
                ELSE '' 
            END
        ) AS city, p.city p_city, p.state, 
                    p.gst_no as party_gst, c.name as company_name, c.address as company_address, c.address2 as company_address2, c.address3 as company_address3,
                    c.city as company_city, c.state as company_state, c.gst_no as company_gst,
                    c.phone as company_phone, c.email as company_email, lic_no1 AS comp_lic1, lic_no2 AS comp_lic2
            FROM ff_sch.invoices i 
            LEFT JOIN ff_sch.parties p ON i.party_id = p.id 
            LEFT JOIN ff_sch.companies c ON 1=1
            WHERE i.invoice_no   = :id and i.fiscal_year_id = :fiscal_year_id
            LIMIT 1";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $invoice_id);
    $stmt->bindParam(':fiscal_year_id', $fiscal_year);
    $stmt->execute();
    $valbill = $stmt->fetch(PDO::FETCH_ASSOC);
    $inv_num = $valbill['id'];   
    // $no = pg_num_rows($stmt);  
    
    // Fetch invoice items
    $items_query = "SELECT ii.*, p.name as product_name,p.per_box_pieces 
                    FROM ff_sch.invoice_items ii 
                    LEFT JOIN ff_sch.products p ON ii.product_id = p.id 
                    WHERE ii.invoice_id = :invoice_id and ii.fiscal_year_id = :fiscal_year_id
                    ORDER BY ii.id";
    $items_stmt = $db->prepare($items_query);
    $items_stmt->bindParam(':invoice_id', $inv_num);
    $items_stmt->bindParam(':fiscal_year_id', $fiscal_year);
    $items_stmt->execute();
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

    $no = $items_stmt->rowCount();

    // Fetch total cartons
    $cartons_query = "SELECT sum(ii.cartons) tot_cartons
                FROM ff_sch.invoice_items ii
                WHERE ii.invoice_id = :invoice_id and ii.fiscal_year_id = :fiscal_year_id";
    $cartons_count_stmt = $db->prepare($cartons_query);
    $cartons_count_stmt->bindParam(':invoice_id', $inv_num);
    $cartons_count_stmt->bindParam(':fiscal_year_id', $fiscal_year);
    $cartons_count_stmt->execute();
    $total_cartons = $cartons_count_stmt->fetchColumn();


    $master = $db->query("SELECT * FROM ff_sch.master WHERE is_active = true LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $hsn_cd = $master['hsn_code'];

    if($valbill['p_place'] <> ''){
        if($valbill['pin_code'] <> ''){
            $pin =  ' - ' . $valbill['pin_code'];
        }

        $p_place = $valbill['p_place'] . $pin;
    } else {
        $p_place = $valbill['city'];
    }

    if($valbill['p_address'] <> ''){
        $p_address = $valbill['p_address'];
    } else {
        $p_address = $valbill['address'];
    }

    if($valbill['p_state'] <> ''){
        $p_state = $valbill['p_state'];
    } else {
        $p_state = $valbill['state'];
    }

    if($valbill['p_gst'] <> ''){
        $p_gst = $valbill['p_gst'];
    } else {
        $p_gst = $valbill['party_gst'];
    }

  /* $sqlbill = "select * from customerbill where invoice_no=$id";
  $exebill = mysqli_query($con, $sqlbill);
  $valbill = mysqli_fetch_assoc($exebill);
  $no = mysqli_num_rows($exebill);

  $sqlbills = "select * from customerbill where invoice_no=$id";
  $exebills = mysqli_query($con, $sqlbills); */
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta name="description" content="Vali is a responsive and free admin theme built with Bootstrap 4, SASS and PUG.js. It's fully customizable and modular.">
  <!-- Twitter meta-->
  <meta property="twitter:card" content="summary_large_image">
  <meta property="twitter:site" content="@pratikborsadiya">
  <meta property="twitter:creator" content="@pratikborsadiya">
  <!-- Open Graph Meta-->
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="FRIENDS FIREWORKS INDUSTRIES">
  <meta property="og:url" content="http://pratikborsadiya.in/blog/vali-admin">
  <meta property="og:image" content="http://pratikborsadiya.in/blog/vali-admin/hero-social.png">
  <meta property="og:description" content="Vali is a responsive and free admin theme built with Bootstrap 4, SASS and PUG.js. It's fully customizable and modular.">
  <title>Invoice - FRIENDS FIREWORKS INDUSTRIES</title>
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/img/favicon.png">
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <!-- Main CSS-->
  <link rel="stylesheet" type="text/css" href="<?php echo ASSETS_URL; ?>/css/main.css">
  <!-- Font-icon css-->
    <link href="<?php echo ASSETS_URL; ?>/css/all.min.css" rel="stylesheet">
</head>

<body class="app sidebar-mini rtl">
  <!-- Navbar-->
  <?php include('header.php'); ?>
  <!-- Sidebar menu-->
  <div class="app-sidebar__overlay" data-toggle="sidebar"></div>
  <?php include('sidebar.php'); ?>
  <?php 
  $copies_to_print = [''];
  if (isset($_GET['copy'])) {
      if ($_GET['copy'] === 'All') {
          $copies_to_print = ['Original', 'Duplicate', 'Transport', 'Supplier'];
      } else {
          $copies_to_print = [$_GET['copy']];
      }
  }
  ?>
  <main class="app-content">
    <div class="app-title d-print-none">
      <div>
        <h1><i class="fa fa-file-text-o"></i> Tax Invoice</h1>
      </div>
      <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
        <li class="breadcrumb-item"><a href="#">Invoice</a></li>
      </ul>
    </div>
    <?php foreach ($copies_to_print as $index => $copy_name): ?>
    <?php 
    $items_chunks = array_chunk($items, 30);
    if (empty($items_chunks)) {
        $items_chunks = [[]];
    }
    $total_pages = count($items_chunks);
    foreach ($items_chunks as $page_idx => $chunk_items): 
        $is_last_page = ($page_idx == $total_pages - 1);
    ?>
    <div class="row" style="<?php echo ($index > 0 || $page_idx > 0) ? 'page-break-before: always;' : ''; ?>">
      <div class="col-md-12">
        <div class="tile">
          <section class="invoice">
            <div style="border: 1px solid #000; overflow: hidden; margin:30px;">
            <div class="row m-0" style="border-bottom: 1px solid #000;">
              <div class="row m-0 w-100 p-1" >
                <div class="col-2 text-center">
                  <p><br /></p>
                  <img src="<?= ASSETS_URL . '/img/trademark.png'?>" width="150" />
                  <!-- <strong>
                    <p style="font-size: 16px;"><b>GSTIN : <?= $valbill['company_gst'] ?></b></p>
                  </strong> -->
                  <div>
                  </div>
                </div>
                <div class="col-8">
                  <h3 class="page-header text-center mt-1"> Tax Invoice</h3>
                  <h3 class="page-header text-center mt-1" style="font-family: ui-rounded !important; font-size:28px;"><?= htmlspecialchars(strtoupper(($valbill['company_name']))) ?></h3>
				          <h6 class="page-header text-center mt-1" style="font-family: ui-rounded !important; font-size: 14px"><?= $valbill['company_address'] ?> </h6>
                  <div class="col-12 text-center mt-1" style="font-size: 16px">
                    <strong><i class="fas fa-map-marker-alt"></i>
                      <?= $valbill['company_address2'] ?>
                      <?= $valbill['company_address3'] ?><br /><h6 class="mt-1 mb-0 font-weight-bold" style="font-size: 16px">
                      <i class="fas fa-file-invoice"></i> GSTIN : <?= $valbill['company_gst'] ?></h6>
                    </strong>
                  </div>
                </div>
                <div class="col-2 text-right d-flex flex-column align-items-end justify-content-start">
					        <?php if($copy_name !== '') echo '<strong class="d-block mb-1" style="font-size: 16px; font-weight: bold">' . htmlspecialchars(strtoupper($copy_name)) . '</strong>'; ?>
                  <img src="<?= ASSETS_URL . '/img/logo.png'?>" width="220" />
                </div>
              </div>
            </div>
            <div class="row m-0">
              <div class="col-6" style="padding: 5px 10px !important;">
                <h6 class="font-weight-bold mb-1">Party's Name and Address :</h6>
                <div class="ml-2 mb-1" style="font-size:16px;">
                  <h6 class="mb-1"><?= strtoupper(($valbill['party_name'])) ?></h6>
                  <p class="mb-1"><?= $p_address ?></p>
                  <p class="mb-1">Place : <?= $p_place ?></p>
                  <p class="mb-1">State : <?= $p_state ?></p>
                  <h6 class="mb-1">GSTIN : <b><?= $p_gst ?></b></h6>
                </div>
              </div>
              <div class="col-6" style="border-left:1px solid #000; padding: 5px 10px !important;">
                <div class="col-12 text-left">
                  <p class="mb-1">Invoice No : <b style="font-size: 16px"><?= $valbill['invoice_no'] ?></b></p>
                  <p class="mb-1">Date : <b style="font-size: 16px"><?= date('d-m-Y', strtotime($valbill['invoice_date'])) ?></b></p>
                  <p class="mb-1">Ref : <b style="font-size: 16px"><?= $valbill['agent_name'] ?></b></p>
                  <p class="mb-1">No of Cases : <b style="font-size: 16px"><?= $total_cartons ?></b></p>
                  <p class="mb-0">Transport Name : <b style="font-size: 16px"><?= $valbill['transport_name'] ?></b></p>
                  <p class="mb-0">HSN Code : <b style="font-size: 16px"><?= $hsn_cd ?></b></p>
                </div>
              </div>
            </div>
            <div class="row m-0">
              <div class="col-12 p-0 table-responsive">
                <table class="table text-center mb-0" style="height:auto !important;border-top: 1px solid #000 !important; border-bottom: 1px solid #000 !important;">
                  <thead>
                    <tr>
                      <th rowspan="2" style="border-right:1px solid #000;border-top:1px solid #000 !important;">SI</th>
                      <th rowspan="2" style="border-right:1px solid #000;border-top:1px solid #000 !important;">Case Nos</th>
                      <th rowspan="2" style="border-right:1px solid #000;border-top:1px solid #000 !important;">Particulares</th>
                      <th rowspan="2" style="border-right:1px solid #000;border-top:1px solid #000 !important;">No of Cases</th>
                      <!-- <th colspan="2" style="border-right:1px solid #000;border-top:1px solid #000 !important;" class="text-center">Package Details</th> -->
                      <th rowspan="2" style="border-right:1px solid #000;border-top:1px solid #000 !important;">Qty</th>
                      <th rowspan="2" style="border-right:1px solid #000;border-top:1px solid #000 !important;">RATE</th>
                      <th rowspan="2" style="border-right:1px solid #000;border-top:1px solid #000 !important;">PER</th>
                      <th rowspan="2" style="border-top:1px solid #000 !important;">AMOUNT</th>
                    </tr>
                    <!-- <tr>
                      <th style="border-right:1px solid #000;border-top:1px solid #000 !important;">Carton</th>
                      <th style="border-right:1px solid #000;border-top:1px solid #000 !important;">Carton Contents</th>
                    </tr> -->
                  </thead>
                  <tbody>
                    <?php
                    $j = $page_idx * 30;
                    foreach ($chunk_items as $item) {
                        // echo 'dddddd'; exit;
                        $rate = (float)($item['rate'] ?? 0);
                        $qty_val = (float)($item['qty'] ?? 0);
                        $amount = $item['total_amount'];

                        $product = $item['product_name'] ?: '-';

                        // Carton Range
                        $ctn = $item['cartons'] ?? 0;
                        $range = '-';
                        if (!empty($item['carton_from']) && !empty($item['carton_to'])) {
                            $range = ($ctn == 1) ? $item['carton_from'] : $item['carton_from'] . " - " . $item['carton_to'];
                        }
                        $qty_words = preg_replace('/[^a-zA-Z\s]+/','',$item['carton_contents']);
                      $j += 1;
                     ?>
                      <tr>
                        <td style="border-right: 1px solid #000;"><?= $j ?></td>
                        <td style="border-right: 1px solid #000;"><?= $range ?></td>
                        <td style="border-right: 1px solid #000;"><?= $product ?></td>
                        <td style="border-right: 1px solid #000;"><?= $ctn ?></td>
                        <!-- <td style="border-right: 1px solid #000;"><?= $item['carton_contents'] ?? '-' ?></td> -->
                        <td style="border-right: 1px solid #000;"><?= $qty_val ?></td>
                        <!-- <td style="border-right: 1px solid #000;"><?= $qty_val.' '.$qty_words ?></td> -->
                        <td style="border-right: 1px solid #000;" class="text-right"><?= number_format($rate,2) ?></td>
                        <td style="border-right: 1px solid #000;"><?= '1 CASE' ?></td>
                        <!-- <td style="border-right: 1px solid #000;"><?= $item['per_box_pieces'] ?? '-' ?></td> -->
                        <td style="" class="text-right"><b><?= number_format($amount,2) ?></b></td>
                      </tr>
                    <?php } 
                    // Add empty rows to fill the page
                    $current_items_count = count($chunk_items);
                    for ($i = $current_items_count; $i < 30; $i++) {
                    // for ($i = $current_items_count; $i < 20; $i++) {
                        echo '<tr>
                                <td style="border-right: 1px solid #000;">&nbsp;</td>
                                <td style="border-right: 1px solid #000;">&nbsp;</td>
                                <td style="border-right: 1px solid #000;">&nbsp;</td>
                                <td style="border-right: 1px solid #000;">&nbsp;</td>
                                <td style="border-right: 1px solid #000;">&nbsp;</td>
                                <td style="border-right: 1px solid #000;">&nbsp;</td>
                                <td style="border-right: 1px solid #000;">&nbsp;</td>
                                <td style="">&nbsp;</td>
                              </tr>';
                    }
                    ?>
                  </tbody>
                </table>
              </div>
            </div>
            <?php if ($is_last_page): ?>
            <?php
                $disc_percent = $valbill['discount_value'];
                $discount_amount = $valbill['discount'];
                $taxable_amount = $valbill['taxable_amount'];
                $igst_percent = $valbill['igst_percent'];
                $sgst_percent = $valbill['sgst_percent'];
                $cgst_percent = $valbill['cgst_percent'];
                $igst_amount = $valbill['igst_amount'];
                $sgst_amount = $valbill['sgst_amount'];
                $cgst_amount = $valbill['cgst_amount'];
                $round_off = $valbill['round_off'];
                $net_amount = $valbill['net_amount'];

                $val = $taxable_amount - $discount_amount;

                if($sgst_amount == 0 && $cgst_amount == 0){
                    $cgst_percent = 0;
                    $sgst_percent = 0;
                }

                if($igst_amount == 0){
                    $igst_percent = 0;
                }
            ?>
            <div class="row" style="border-bottom:1px solid #000;border-bottom:none;font-size:14px;">

                <div class="col-2 text-center p-1" style="border-right:1px solid #000;">
                    Good value<br>
                   <b><?= number_format($valbill['taxable_amount'],2) ?></b>
                </div>

                <div class="col-1 text-center p-1" style="border-right:1px solid #000;">
                    Discount %<br>
                    <b><?= number_format($disc_percent,2) ?>%</b>
                </div>

                <div class="col-2 text-center p-1" style="border-right:1px solid #000;">
                    Discount<br>
                    <b><?= number_format($valbill['discount'],2) ?></b>
                </div>

                <div class="col-2 text-center p-1" style="border-right:1px solid #000;">
                    Sub Total<br>
                    <b><?= number_format(($valbill['taxable_amount'] - $valbill['discount']),2) ?></b>
                </div>

                <div class="col-3 text-center p-1" style="border-right:1px solid #000;">
                    Packing Charges @ %<br>
                    <b><?= number_format($valbill['packing_charge'] ?? 0,2) ?></b>
                </div>

                <div class="col-2 text-center p-1">
                    Mahamai @ %<br>
                    <b><?= number_format($valbill['mahamai_amount'] ?? 0,2) ?></b>
                </div>
            </div>

            <div class="row" style="border:1px solid #000;font-size:14px;">
                <div class="col-2 text-center p-1" style="border-right:1px solid #000;">
                    Insurance @ %<br>
                    <b><?= number_format($valbill['insurance_amount'] ?? 0,2) ?></b>
                </div>

                <div class="col-2 text-center p-1" style="border-right:1px solid #000;">
                    Taxable value<br>
                    <b><?= number_format(($valbill['taxable_amount'] - $valbill['discount']),2) ?></b>
                </div>

                <div class="col-2 text-center p-1" style="border-right:1px solid #000;">
                    SGST @ <b><?= number_format($sgst_percent,0) ?>%<br>
                    <?= number_format($valbill['sgst_amount'],2) ?></b>
                </div>

                <div class="col-2 text-center p-1" style="border-right:1px solid #000;">
                    CGST @ <b><?= number_format($cgst_percent,0) ?>%<br>
                    <?= number_format($valbill['cgst_amount'],2) ?></b>
                </div>

                <div class="col-2 text-center p-1" style="border-right:1px solid #000;">
                    IGST @ <b><?= number_format($igst_percent,0) ?>%<br>
                    <?= number_format($valbill['igst_amount'],2) ?></b>
                </div>

                <div class="col-2 text-center p-1">
                    Net Amount<br>
                    <b><?= number_format($valbill['net_amount'],2) ?></b>
                </div>
            </div>
            <div class="row m-0">
              <div class="col-12 text-center" style="padding: 8px; border-bottom: 1px solid #000;">
                <?php
                $number = $valbill['net_amount'];
                $no = floor($number);
                $point = round($number - $no, 2) * 100;
                $hundred = null;
                $digits_1 = strlen($no);
                $i = 0;
                $str = array();
                $words = array(
                  '0' => '', '1' => 'one', '2' => 'two',
                  '3' => 'three', '4' => 'four', '5' => 'five', '6' => 'six',
                  '7' => 'seven', '8' => 'eight', '9' => 'nine',
                  '10' => 'ten', '11' => 'eleven', '12' => 'twelve',
                  '13' => 'thirteen', '14' => 'fourteen',
                  '15' => 'fifteen', '16' => 'sixteen', '17' => 'seventeen',
                  '18' => 'eighteen', '19' => 'nineteen', '20' => 'twenty',
                  '30' => 'thirty', '40' => 'forty', '50' => 'fifty',
                  '60' => 'sixty', '70' => 'seventy',
                  '80' => 'eighty', '90' => 'ninety'
                );
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
                  "." . $words[$point / 10] . " " .
                  $words[$point = $point % 10] : '';
                echo '<h6 class="mb-0"><strong><i style="font-size: 16px; font-weight:bold">Total Amount In Words : ' . ucfirst($result) . "Rupees only</i></strong></h6>";
                ?>
              </div>
              <div class="col-8" style="border-right: 1px solid #000; padding: 4px 8px;">
                <h6 class="mt-1 mb-1 font-weight-bold">Note :</h6>
                <h6 class="pl-2 mb-0">1) Interest @ 24% per annum will be charged on bill not paid on date.</h6>
                <h6 class="pl-2 mb-1">2) All Disputes to SIVAKASI Jurisdiction.</h6>
              </div>
              <div class="col-4 text-left px-2 py-1">
                <h6 class="mt-1 mb-0">Certified that the particulars given above are true and correct</h6>
              </div>
              
              <div class="col-12 p-0" style="border-top: 1px solid #000;"></div>
              
              <div class="col-8" style="border-right: 1px solid #000;">
                <p class="mb-0 mt-1"><i>Bank Details</i></p>
                <table class="table mb-0 table-borderless" >
                  <tr>
                    <td class="p-0" style="border: none !important;">
                      <p style="font-weight: 600 !important;font-size:13px !important; margin-bottom:2px;">Account Name : FRIENDS FIREWORKS INDUSTRIES</p>
                      <p style="font-weight: 600 !important;font-size:13px !important; margin-bottom:2px;">Bank Name : SBI Bank</p>
                      <p style="font-weight: 600 !important;font-size:13px !important; margin-bottom:2px;">Branch : Sivakasi</p>
                      <p style="font-weight: 600 !important;font-size:13px !important; margin-bottom:2px;">Account Number : 41155719647</p>
                      <p style="font-weight: 600 !important;font-size:13px !important; margin-bottom:0;">IFSC Code : SBIN0070654</p>
                    </td>
                  </tr>
                </table>
              </div>
              <div class="col-4 text-center px-2 py-1">
                <h6 class="mt-1"><i>For Friends Fireworks Industries</i></h6>
                <h6><br /><br /></h6>
                <h6 class="mb-0"><i>Authorized Signatory</i></h6>
              </div>
            </div>
            <?php else: ?>
            <div class="row m-0 text-right mt-2 mb-2">
                <div class="col-12">
                    <p><strong>Continued on next page...</strong></p>
                </div>
            </div>
            <?php endif; ?>
            </div>
            <div class="row d-print-none mt-4">
              <div class="col-12 text-right"><a class="btn btn-primary" href="javascript:window.print();"><i class="fa fa-print"></i> Print</a></div>
            </div>
          </section>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
    <?php endforeach; ?>
  </main>
  <!-- Essential javascripts for application to work-->
    <script src="<?php echo ASSETS_URL; ?>/js/jquery-3.6.0.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/bootstrap.bundle.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/jquery.dataTables.min.js"></script>
    <script src="<?php echo ASSETS_URL; ?>/js/dataTables.bootstrap5.min.js"></script>
  <!-- The javascript plugin to display page loading on top-->
  <script src="<?php echo ASSETS_URL; ?>/js/pace.min.js"></script>
  <!-- Page specific javascripts-->
  <!-- Google analytics script-->
  <script type="text/javascript">
    if (document.location.hostname == 'pratikborsadiya.in') {
      (function(i, s, o, g, r, a, m) {
        i['GoogleAnalyticsObject'] = r;
        i[r] = i[r] || function() {
          (i[r].q = i[r].q || []).push(arguments)
        }, i[r].l = 1 * new Date();
        a = s.createElement(o),
          m = s.getElementsByTagName(o)[0];
        a.async = 1;
        a.src = g;
        m.parentNode.insertBefore(a, m)
      })(window, document, 'script', '//www.google-analytics.com/analytics.js', 'ga');
      ga('create', 'UA-72504830-1', 'auto');
      ga('send', 'pageview');
    }
  </script>
</body>

</html>