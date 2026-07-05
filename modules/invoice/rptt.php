<style>
  @media print {
    body {
      margin: 1px solid #000 !important;
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
    margin-bottom: 7px !important;
    font-weight: 500 !important;
  }

  .table th,
  .table td {
    padding: 6.5px !important;
    color: #000 !important;
  }

  table td {
    color: #000 !important;
    border-top: none !important;
  }
</style>
<?php
session_start();
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
    $query = "SELECT i.*, c.name as company_name, c.address as company_address, c.address2 as company_address2, c.address3 as company_address3,
                    c.city as company_city, c.state as company_state, c.gst_no as company_gst,
                    c.phone as company_phone, c.email as company_email, lic_no1 AS comp_lic1, lic_no2 AS comp_lic2
            FROM ff_sch.temp_inv i 
            LEFT JOIN ff_sch.companies c ON 1=1
            WHERE i.invoice_no   = :id and i.fiscal_year_id = :fiscal_year_id
            LIMIT 1";

    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $invoice_id);
    $stmt->bindParam(':fiscal_year_id', $fiscal_year);
    $stmt->execute();
    $valbill = $stmt->fetch(PDO::FETCH_ASSOC);
    $inv_num = $valbill['id'];
    
    // Fetch invoice items
    $items_query = "SELECT ii.*, p.name as product_name,p.per_box_pieces 
                    FROM ff_sch.temp_inv_items ii 
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
                FROM ff_sch.temp_inv_items ii
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
  <meta property="og:site_name" content="Jeyalakshmi Priya">
  <meta property="og:url" content="http://pratikborsadiya.in/blog/vali-admin">
  <meta property="og:image" content="http://pratikborsadiya.in/blog/vali-admin/hero-social.png">
  <meta property="og:description" content="Vali is a responsive and free admin theme built with Bootstrap 4, SASS and PUG.js. It's fully customizable and modular.">
  <title>Invoice - Jeyalakshmi Priya</title>
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
  <main class="app-content">
    <div class="app-title">
      <div>
        <h1><i class="fa fa-file-text-o"></i> Tax Invoice</h1>
      </div>
      <ul class="app-breadcrumb breadcrumb">
        <li class="breadcrumb-item"><i class="fa fa-home fa-lg"></i></li>
        <li class="breadcrumb-item"><a href="#">Invoice</a></li>
      </ul>
    </div>
    <?php 
    $items_chunks = array_chunk($items, 15);
    if (empty($items_chunks)) {
        $items_chunks = [[]];
    }
    $total_pages = count($items_chunks);
    foreach ($items_chunks as $page_idx => $chunk_items): 
        $is_last_page = ($page_idx == $total_pages - 1);
    ?>
    <div class="row" style="<?php echo $page_idx > 0 ? 'page-break-before: always;' : ''; ?>">
      <div class="col-md-12">
        <div class="tile">
          <section class="invoice">
            <div class="row mb-2">
              <div class="row col-12">
                <div class="col-3 text-center">
                  <img src="<?= ASSETS_URL . '/img/logo.png'?>" width="180" />
                  <strong>
                    <p style="font-size: 16px;"><b>GSTIN : <?= $valbill['company_gst'] ?></b></p>
                  </strong>
                  <div>
                  </div>
                </div>
                <div class="col-6">
                  <h3 class="page-header text-center mt-1"> Tax Invoice</h3>
                  <h3 class="page-header text-center mt-1" style="font-family: ui-rounded !important;">
                    JEYALAKSHMI PRIYA</h3>
                  <h3 class="page-header text-center mt-1" style="font-family: ui-rounded !important;">
                    SPARKLERS FACTORY & FIREWORKS</h3>
                  <div class="col-12 text-center mt-1">
                    <strong>
                      <?= $valbill['company_address'] ?> <br /><?= $valbill['company_address2'] ?>
                      <br /> <?= $valbill['company_address3'] ?>
                    </strong>
                  </div>
                </div>
                <div class="col-3 text-center">
                  <img src="<?= ASSETS_URL . '/img/trademark.png'?>" width="180" />
                  <p style="font-size: 11px;font-weight:800 !important;"><b>LICENCE NO : <?= $valbill['comp_lic1'] ?><br />
                      LICENCE NO : <?= $valbill['comp_lic2'] ?></b></p>
                </div>
              </div>
            </div>
            <div class="row col-md-12">
              <div class="col-6 mb-1 mt-2" style="border-left: 1px solid #000;margin-top: 0px !important;
    margin-bottom: 0px !important;
    padding: 10px !important;">
                <div style="border-top: 1px solid #000;position:relative;top:-10px;left:-10px;width:215% !important"></div>
                <h5>Party's Name and Address :</h5>
                <div class="ml-4 mb-1">
                  <h6 class="mb-1"><?= strtoupper(($valbill['party_name'])) ?></h6>
                  <p class="mb-1"><?= $p_address ?></p>
                  <p class="mb-1">Place : <?= $p_place ?></p>
                  <p class="mb-1">State : <?= $p_state ?></p>
                  <h6 class="mb-1">GSTIN : <?= $p_gst ?></h6>
                </div>
              </div>
              <div class="col-5" style="border-left:1px solid #000">
                <div class="col-12 text-center  mt-5">
                  <h5>Invoice No : <?= $valbill['invoice_no'] ?></h5>
                  <h5>Date : <td><?= date('d-m-Y', strtotime($valbill['invoice_date'])) ?></td>
                  </h5>
                </div>
              </div>
              <div class="col-1" style="    border-right: 1px solid #000;
    position: relative;
    left: 30px;"></div>
            </div>
            <div class="row">
              <div class="col-12 table-responsive">
                <table class="table text-center" style="height:auto !important;border-bottom: 1px solid #000 !important;border-left:1px solid #000;border-right:1px solid #000">
                  <thead>
                    <tr>
                      <th rowspan="2" style="border-right:1px solid #000;border-top:1px solid #000 !important;">SI</th>
                      <th rowspan="2" style="border-right:1px solid #000;border-top:1px solid #000 !important;">Carton From - To</th>
                      <th rowspan="2" style="border-right:1px solid #000;border-top:1px solid #000 !important;">Product Name</th>
                      <th colspan="2" style="border-right:1px solid #000;border-top:1px solid #000 !important;" class="text-center">Package Details</th>
                      <th rowspan="2" style="border-right:1px solid #000;border-top:1px solid #000 !important;">Qty</th>
                      <th rowspan="2" style="border-right:1px solid #000;border-top:1px solid #000 !important;">RATE</th>
                      <th rowspan="2" style="border-right:1px solid #000;border-top:1px solid #000 !important;">PER</th>
                      <th rowspan="2" style="border-right:1px solid #000;border-top:1px solid #000 !important;">AMOUNT</th>
                    </tr>
                    <tr>
                      <th style="border-right:1px solid #000;border-top:1px solid #000 !important;">Carton</th>
                      <th style="border-right:1px solid #000;border-top:1px solid #000 !important;">Carton Contents</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    $j = $page_idx * 15;
                    foreach ($chunk_items as $item) {
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
                        <td style="border-right: 1px solid #000;"><?= $item['carton_contents'] ?? '-' ?></td>
                        <td style="border-right: 1px solid #000;"><?= $qty_val.' '.$qty_words ?></td>
                        <td style="border-right: 1px solid #000;" class="text-right"><?= number_format($rate,2) ?></td>
                        <td style="border-right: 1px solid #000;"><?= $item['per_box_pieces'] ?? '-' ?></td>
                        <td style="border-right: 1px solid #000;" class="text-right"><?= number_format($amount,2) ?></td>
                      </tr>
                    <?php }
                    if ($no == 1) {
                    ?>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                    <?php } else if ($no == 2) { ?>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>

                    <?php } else if ($no == 3) { ?>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>

                    <?php } else if ($no == 4) { ?>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>

                    <?php } else if ($no == 5) { ?>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                    <?php } else if ($no == 6) { ?>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                    <?php }
                    if ($no == 7) { ?>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                    <?php } else if ($no == 8) { ?>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                    <?php } else if ($no == 9) { ?>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                    <?php } else if ($no == 10) { ?>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                    <?php } else if ($no == 11) { ?>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                    <?php } else if ($no == 12) { ?>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                    <?php } else if ($no == 13) { ?>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                    <?php } else if ($no == 14) { ?>
                      <tr>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                        <td style=" border-right: 1px solid #000;">&nbsp;</td>
                      </tr>
                    <?php }
                    ?>
                  </tbody>
                </table>
              </div>
            </div>
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
            <div class="row invoice-info" style="margin-top: -15px;">
              <div class="col-5" style="border-left: 1px solid #000;position: relative;left: 15px;">
                <table class="col-12 ml-5" style="border:none !important">
                  <tr>
                    <td width="30%">
                      <p class="mt-1"><b>HSN Code</b></p>
                    </td>
                    <td width="5%">
                      <p class="mt-1">:</p>
                    </td>
                    <td>
                      <p class="mt-1 text-left"><strong><?= $hsn_cd ?? '3604' ?></strong></p>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <p>Total Cartons</p>
                    </td>
                    <td>
                      <p>:</p>
                    </td>
                    <td>
                      <p class="text-left"> <?= $total_cartons ?></p>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <p>Despatched From</p>
                    </td>
                    <td>
                      <p>:</p>
                    </td>
                    <td>
                      <p class="text-left"><?= $valbill['dispatch_from'] ?></p>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <p>Despatched To</p>
                    </td>
                    <td>
                      <p>:</p>
                    </td>
                    <td>
                      <p class="text-left"><?= $valbill['dispatch_through'] ?></p>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <p>Vehicle No</p>
                    </td>
                    <td>
                      <p>:</p>
                    </td>
                    <td>
                      <p class="text-left"><?= $valbill['vehicle_no'] ?></p>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <p>Transport Name</p>
                    </td>
                    <td>
                      <p>:</p>
                    </td>
                    <td>
                      <p class="text-left"><?= $valbill['transport_name'] ?></p>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <p>Transport GSTIN</p>
                    </td>
                    <td>
                      <p>:</p>
                    </td>
                    <td>
                      <p class="text-left"><?= $valbill['transport_gst'] ?></p>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <p>E-way Bill</p>
                    </td>
                    <td>
                      <p>:</p>
                    </td>
                    <td>
                      <p class="text-left"><?= $valbill['eway_bill_no'] ?></p>
                    </td>
                  </tr>
                </table>
              </div>
              <div class="col-1 text-center" style="position:relative;left:-20px;border-right:1px solid #000">
              </div>
              <div class="col-3 text-right mt-1" style="position: relative;left:150px">
                <table style="border:none !important">
                  <tbody>
                    <tr>
                      <td>
                        <p> <b>GOODS VALUE</b></p>
                      </td>
                    </tr>
                    <tr>
                      <td class="pl-1">
                        <p><b>LESS DISC : <?= number_format($disc_percent) ?>%</b></p>
                      </td>
                    </tr>
                    <tr>
                      <td class="pt-1">
                        <p><b>TAXABLE VALUE</b></p>
                      </td>
                    </tr>
                    <tr>
                      <td class="pl-3">
                        <p><b>IGST</b></p>
                      </td>
                      <td>:</td>
                      <td></td>
                      <td>
                        <p class="pt-2"><b><?= number_format($igst_percent) ?>&nbsp;%</b></p>
                      </td>
                    </tr>
                    <tr>
                      <td class="pl-3">
                        <p><b>SGST</b></p>
                      </td>
                      <td>:</td>
                      <td></td>
                      <td>
                        <p class="pt-2"><b><?= number_format($sgst_percent) ?>&nbsp;%</b></p>
                      </td>
                    </tr>
                    <tr>
                      <td class="pl-3">
                        <p><b>CGST</b></p>
                      </td>
                      <td>:</td>
                      <td></td>
                      <td>
                        <p class="pt-2"><b><?= number_format($cgst_percent) ?>&nbsp;%</b></p>
                      </td>
                    </tr>
                    <tr>
                      <td>
                        <p><b>ROUND OFF</b></p>
                      </td>
                    </tr>
                    <tr>
                      <td>
                        <p><b>NET AMOUNT</b></p>
                      </td>
                    </tr>
                </table>
              </div>
              <div class="col-1" style="position:relative;left:100px;border-left:1px solid #000">
              </div>
              <div class="col-1 text-left" style="position: relative;left:50px;">
                <table style="border:none !important">
                  <tr>
                    <td>
                      <p class="mt-1 text-right" style="margin-left:10px;"><b><?= number_format($valbill['taxable_amount'],2) ?></b></p>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <p class="text-right"><b><?= number_format($valbill['discount'],2) ?></b></p>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <p class="pt-1 text-right"><b><?= number_format($valbill['taxable_amount'] - $valbill['discount'],2) ?></b></p>
                    </td>
                  </tr>
                  <tr>
                    <?php
                    $tax = 0;
                    ?>
                    <?php if ($valbill['igst_amount'] != '') {
                    ?>
                      <td>
                        <p class="pt-2 text-right"><b><?= number_format($valbill['igst_amount'],2) ?></b></p>
                      </td>
                    <?php } else { ?>
                      <td>
                        <p class="pt-2 text-right">-</p>
                      </td>
                    <?php } ?>
                  </tr>
                  <tr> <?php if ($valbill['sgst_amount'] != '') { ?>
                      <td>
                        <p class="pt-2 text-right"><b><?= number_format($valbill['sgst_amount'],2) ?></b></p>
                      </td>
                    <?php } else { ?>
                      <td>
                        <p class="pt-2 text-right">-</p>
                      </td>
                    <?php } ?>
                  </tr>
                  <tr>
                    <?php if ($valbill['cgst_amount'] != '') {
                    ?>
                      <td>
                        <p class="pt-2 text-right"><b><?= number_format($valbill['cgst_amount'],2) ?></b></p>
                      </td>
                    <?php } else { ?>
                      <td>
                        <p class="pt-2 text-right">-</p>
                      </td>
                    <?php }
                    ?>
                  </tr>
                  <tr>
                    <td>
                      <p class="pt-1 text-right"><b><?= number_format($valbill['round_off'],2) ?></b></p>
                    </td>
                  </tr>
                  <tr>
                    <td>
                      <p class="pt-0 text-right"><b><?= number_format($valbill['net_amount'],2) ?></b></p>
                    </td>
                  </tr>
                </table>
              </div>
              <div class="col-1" style="position:relative;left:-15px;border-right:1px solid #000">
              </div>
            </div>

            <div class="row col-xs-12" style="width: 100% !important;
    position: relative !important;
    left: 15px !important;">
              <div class="col-md-12 text-center" style="border:1px solid #000 !important;">
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
                echo '<h6 class="mt-2"><tr><strong><i>Total Amount In Words : <i>' . ucfirst($result) . "Rupees </i>" . "</i></strong></h6>";
                ?>
              </div>
            </div>
            <div class="row col-12">
              <div class="col-9" style="border-left:1px solid #000;">
                <h6 class="mt-1">Declaration :</h6>
                <h6 class="pl-4">We declare that this invoice shows the actual price of the goods and that all
                  particulars are true and collect.
                </h6>
                <p><i>Company Bank Details</i></p>
                <table class="table table-bordered">
                  <tr>
                    <td>
                      <p style="font-weight: 600 !important;font-size:13.5px !important">Account Name : Jeyalakshmi Priya Sparklers Factory</p>
                      <p style="font-weight: 600 !important;font-size:13.5px !important">Bank Name : Punjab National Bank</p>
                      <p style="font-weight: 600 !important;font-size:13.5px !important">Account Number : 4199002100015343</p>
                      <p style="font-weight: 600 !important;font-size:13.5px !important">IFSC Code : PUNB0419900</p>
                    </td>
                    <td>
                      <p style="font-weight: 600 !important;font-size:13.5px !important">Account Name : Jeyalakshmi Priya Sparklers Factory</p>
                      <p style="font-weight: 600 !important;font-size:13.5px !important">Bank Name : Tamilnadu Mercantile Bank</p>
                      <p style="font-weight: 600 !important;font-size:13.5px !important">Account Number : 003700050900353</p>
                      <p style="font-weight: 600 !important;font-size:13.5px !important">IFSC Code : TMBL0000003</p>
                    </td>
                  </tr>
                </table>
                <div style="border-bottom:1px solid #000;position: relative;
    left: -15px;
    width: 143%;
    top: 15px;"></div>
              </div>
              <div class="col-3" style="border-left:1px solid #000 !important;border-right:1px solid #000 !important;position:relative;left:30px">
                <p class="mt-1"><i>For Jeyalakshmi Priya Sparklers Factory & Fireworks</i></p>
                <h6><br /><br /><br /><br /><br /><br /><br /><br /></h6>
                <h6 class="text-right"><i>Manager / Partner</i></h6>
              </div>
            </div>
            <div class="row d-print-none mt-4">
              <div class="col-12 text-right"><a class="btn btn-primary" href="javascript:window.print();"><i class="fa fa-print"></i> Print</a></div>
            </div>
          </section>
        </div>
      </div>
    </div>
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