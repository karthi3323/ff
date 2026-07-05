<style>
  @media print {
    body {
      margin: 1px solid #000 !important;
    }
    .no-print {
        display: none !important;
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

if(!isset($_GET['id']) || !isset($_GET['year'])) {
    header("Location: " . BASE_URL . "modules/invoice/proforma_view.php");
    exit();
}

$invoice_id = $_GET['id'];
$fiscal_year_id = $_GET['year'];

// Fetch invoice details
$query = "SELECT i.*, p.name as party_name, TRIM(
        COALESCE(p.address_line1, '') ||
        CASE 
            WHEN p.address_line2 IS NOT NULL AND p.address_line2 <> '' 
            THEN ', ' || p.address_line2 
            ELSE '' 
        END
    ) AS address, TRIM(
        COALESCE(p.city, '') ||
        CASE 
            WHEN p.pin_code IS NOT NULL AND p.pin_code <> '' 
            THEN ' - ' || p.pin_code 
            ELSE '' 
        END
    ) AS city, p.city p_city, p.state, 
                p.gst_no as party_gst, c.name as company_name, c.address as company_address, c.address2 as company_address2, c.address3 as company_address3,
                c.city as company_city, c.state as company_state, c.gst_no as company_gst,
                c.phone as company_phone, c.email as company_email, lic_no1 AS comp_lic1, lic_no2 AS comp_lic2
        FROM ff_sch.proforma_invoices i 
        LEFT JOIN ff_sch.parties p ON i.party_id = p.id 
        CROSS JOIN ff_sch.companies c
        WHERE i.id = :id AND i.fiscal_year_id = :fiscal_year_id
        LIMIT 1";

$stmt = $db->prepare($query);
$stmt->bindParam(':id', $invoice_id);
$stmt->bindParam(':fiscal_year_id', $fiscal_year_id);
$stmt->execute();
$valbill = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$valbill) {
    echo "Pro-forma invoice not found.";
    exit;
}

// Fetch invoice items
$items_query = "SELECT ii.*, p.name as product_name,p.per_box_pieces 
                FROM ff_sch.proforma_invoice_items ii 
                LEFT JOIN ff_sch.products p ON ii.product_id = p.id 
                WHERE ii.invoice_id = :invoice_id AND ii.fiscal_year_id = :fiscal_year_id
                ORDER BY ii.id";
$items_stmt = $db->prepare($items_query);
$items_stmt->bindParam(':invoice_id', $invoice_id);
$items_stmt->bindParam(':fiscal_year_id', $fiscal_year_id);
$items_stmt->execute();
$items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
$no = $items_stmt->rowCount();

// Fetch total cartons
$cartons_query = "SELECT sum(ii.cartons) tot_cartons
            FROM ff_sch.proforma_invoice_items ii 
            WHERE ii.invoice_id = :invoice_id AND ii.fiscal_year_id = :fiscal_year_id";
$cartons_count_stmt = $db->prepare($cartons_query);
$cartons_count_stmt->bindParam(':invoice_id', $invoice_id);
$cartons_count_stmt->bindParam(':fiscal_year_id', $fiscal_year_id);
$cartons_count_stmt->execute();
$total_cartons = $cartons_count_stmt->fetchColumn();

$master = $db->query("SELECT * FROM ff_sch.master WHERE is_active = true LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$hsn_cd = $master['hsn_code'];

$p_place = $valbill['p_place'] ?: $valbill['city'];
$p_address = $valbill['p_address'] ?: $valbill['address'];
$p_state = $valbill['p_state'] ?: $valbill['state'];
$p_gst = $valbill['p_gst'] ?: $valbill['party_gst'];

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <title>Pro-forma Invoice - <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/x-icon" href="<?php echo ASSETS_URL; ?>/img/favicon.png">
  <link rel="stylesheet" type="text/css" href="<?php echo ASSETS_URL; ?>/css/main.css">
  <link href="<?php echo ASSETS_URL; ?>/css/all.min.css" rel="stylesheet">
</head>

<body class="app sidebar-mini rtl">
  <main class="app-content" style="margin-top: 0; padding: 15px;">
    <?php 
    $items_chunks = array_chunk($items, 20);
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
                </div>
                <div class="col-6">
                  <h3 class="page-header text-center mt-1">INVOICE</h3>
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
              <div class="col-6 mb-1 mt-2" style="border-left: 1px solid #000;margin-top: 0px !important; margin-bottom: 0px !important; padding: 10px !important;">
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
              <div class="col-1" style="border-right: 1px solid #000; position: relative; left: 30px;"></div>
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
                      <th rowspan="2" style="border-right:1px solid #000;border-top:1px solid #000 !important; text-align: right; padding-right: 5px;">RATE</th>
                      <th rowspan="2" style="border-right:1px solid #000;border-top:1px solid #000 !important;">PER</th>
                      <th rowspan="2" style="border-right:1px solid #000;border-top:1px solid #000 !important; text-align: right; padding-right: 5px;">AMOUNT</th>
                    </tr>
                    <tr>
                      <th style="border-right:1px solid #000;border-top:1px solid #000 !important;">Carton</th>
                      <th style="border-right:1px solid #000;border-top:1px solid #000 !important;">Carton Contents</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php
                    $j = $page_idx * 20;
                    foreach ($chunk_items as $item) {
                        $rate = (float)($item['rate'] ?? 0);
                        $qty_val = (float)($item['qty'] ?? 0);
                        $amount = $item['total_amount'];
                        $product = $item['product_name'] ?: '-';
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
                    // Add empty rows to fill the page
                    $current_items_count = count($chunk_items);
                    for ($i = $current_items_count; $i < 20; $i++) {
                        echo '<tr>
                                <td style="border-right: 1px solid #000;">&nbsp;</td>
                                <td style="border-right: 1px solid #000;">&nbsp;</td>
                                <td style="border-right: 1px solid #000;">&nbsp;</td>
                                <td style="border-right: 1px solid #000;">&nbsp;</td>
                                <td style="border-right: 1px solid #000;">&nbsp;</td>
                                <td style="border-right: 1px solid #000;">&nbsp;</td>
                                <td style="border-right: 1px solid #000;">&nbsp;</td>
                                <td style="border-right: 1px solid #000;">&nbsp;</td>
                                <td style="border-right: 1px solid #000;">&nbsp;</td>
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
                $additional_charges = $valbill['additional_charges_amount'];
                $packing_charges = $valbill['packing_charges'];
                $taxable_amount = ($additional_charges + $packing_charges) - $discount_amount;
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
                    <td width="30%"><p class="mt-1"><b>HSN Code</b></p></td>
                    <td width="5%"><p class="mt-1">:</p></td>
                    <td><p class="mt-1 text-left"><strong><?= $hsn_cd ?? '3604' ?></strong></p></td>
                  </tr>
                  <tr>
                    <td><p>Total Cartons</p></td>
                    <td><p>:</p></td>
                    <td><p class="text-left"> <?= $total_cartons ?></p></td>
                  </tr>
                  <tr>
                    <td><p>Despatched From</p></td>
                    <td><p>:</p></td>
                    <td><p class="text-left"><?= $valbill['dispatch_from'] ?></p></td>
                  </tr>
                  <tr>
                    <td><p>Despatched To</p></td>
                    <td><p>:</p></td>
                    <td><p class="text-left"><?= $valbill['dispatch_through'] ?></p></td>
                  </tr>
                  <tr>
                    <td><p>Vehicle No</p></td>
                    <td><p>:</p></td>
                    <td><p class="text-left"><?= $valbill['vehicle_no'] ?></p></td>
                  </tr>
                  <tr>
                    <td><p>Transport Name</p></td>
                    <td><p>:</p></td>
                    <td><p class="text-left"><?= $valbill['transport_name'] ?></p></td>
                  </tr>
                  <tr>
                    <td><p>Transport GSTIN</p></td>
                    <td><p>:</p></td>
                    <td><p class="text-left"><?= $valbill['transport_gst'] ?></p></td>
                  </tr>
                  <tr>
                    <td><p>E-way Bill</p></td>
                    <td><p>:</p></td>
                    <td><p class="text-left"><?= $valbill['eway_bill_no'] ?></p></td>
                  </tr>
                </table>
              </div>
              <div class="col-1 text-center" style="position:relative;left:-20px;border-right:1px solid #000"></div>
              <div class="col-3 text-right mt-1" style="position: relative;left:150px">
                <table style="border:none !important">
                  <tbody>
                    <tr><td><p> <b>GOODS VALUE</b></p></td></tr>
                    <tr><td class="pl-1"><p><b>PACKING CHARGES</b></p></td></tr>
                    <tr><td class="pl-1"><p><b>LESS DISC</b></p></td><td>:</td><td></td><td><p class="pt-2"><b><?= number_format($disc_percent) ?>&nbsp;%</b></p></td></tr>
                    <tr><td class="pt-1"><p><b>TAXABLE VALUE</b></p></td></tr>
                    <tr><td class="pl-1"><p><b>GST</b></p></td><td>:</td><td></td><td><p class="pt-2"><b><?= number_format($igst_percent) ?>&nbsp;%</b></p></td></tr>
                    <tr><td><p><b>ROUND OFF</b></p></td></tr>
                    <tr><td><p><b>NET AMOUNT</b></p></td></tr>
                </table>
              </div>
              <div class="col-1" style="position:relative;left:100px;border-left:1px solid #000"></div>
              <div class="col-1 text-left" style="position: relative;left:50px;">
                <table style="border:none !important">
                  <tr><td><p class="mt-1 text-right" style="margin-left:10px;"><b><?= number_format($additional_charges,2) ?></b></p></td></tr>
                  <tr><?php if ($valbill['packing_charges'] != '') { ?><td><p class="pt-2 text-right"><b><?= number_format($valbill['packing_charges'],2) ?></b></p></td><?php } else { ?><td><p class="pt-2 text-right">-</p></td><?php } ?></tr>
                  <tr><td><p class="text-right"><b><?= number_format($valbill['discount'],2) ?></b></p></td></tr>
                  <tr><td><p class="pt-1 text-right"><b><?= number_format($taxable_amount,2) ?></b></p></td></tr>
                  <tr><?php if ($valbill['igst_amount'] != '') { ?><td><p class="pt-2 text-right"><b><?= number_format($valbill['igst_amount'],2) ?></b></p></td><?php } else { ?><td><p class="pt-2 text-right">-</p></td><?php } ?></tr>
                  
                  <tr><td><p class="pt-1 text-right"><b><?= number_format($valbill['round_off'],2) ?></b></p></td></tr>
                  <tr><td><p class="pt-0 text-right"><b><?= number_format($valbill['net_amount'],2) ?></b></p></td></tr>
                </table>
              </div>
              <div class="col-1" style="position:relative;left:-15px;border-right:1px solid #000"></div>
            </div>
            <div class="row m-0" style="border: 1px solid #000;">
              <div class="col-12 text-center" style="padding: 8px; border-bottom: 1px solid #000;">
                <?php
                // Your number to words logic here
                ?>
              </div>
              <div class="col-8" style="border-right: 1px solid #000; padding: 10px 15px;">
                <h6 class="mt-1 font-weight-bold">Note :</h6>
                <h6 class="pl-4 mb-1">1) Interest @ 24% per annum will be charged on bill not paid on date.</h6>
                <h6 class="pl-4 mb-2">2) All Disputes subject to SIVAKASI Jurisdiction.</h6>
              </div>
              <div class="col-4 text-left" style="padding: 10px 15px;">
                <h6 class="mt-1">Certified that the particulars given above are true and correct</h6>
              </div>
              
              <div class="col-12 p-0" style="border-top: 1px solid #000;"></div>
              
              <div class="col-8" style="border-right: 1px solid #000; padding: 10px 15px;">
                <p class="mb-1 mt-2"><i>Bank Details</i></p>
                <table class="table mb-0 table-borderless" style="border: none !important;">
                  <tr>
                    <td class="p-0" style="border: none !important;">
                      <p style="font-weight: 600 !important;font-size:13.5px !important; margin-bottom:2px;">Account Name : FRIENDS FIREWORKS INDUSTRIES</p>
                      <p style="font-weight: 600 !important;font-size:13.5px !important; margin-bottom:2px;">Bank Name : SBI Bank</p>
                      <p style="font-weight: 600 !important;font-size:13.5px !important; margin-bottom:2px;">Account Number : 41155719647</p>
                      <p style="font-weight: 600 !important;font-size:13.5px !important; margin-bottom:0;">IFSC Code : sbin0070654</p>
                    </td>
                  </tr>
                </table>
              </div>
              <div class="col-4 text-center" style="padding: 10px 15px;">
                <h6 class="mt-1"><i>For Friends Fireworks Industries</i></h6>
                <h6><br /><br /><br /></h6>
                <h6 class="mb-0"><i>Authorized Signatory</i></h6>
              </div>
            </div>
            <?php else: ?>
            <div class="row text-right mt-2 mb-2">
                <div class="col-12">
                    <p><strong>Continued on next page...</strong></p>
                </div>
            </div>
            <?php endif; ?>
            <div class="row d-print-none mt-4 no-print">
              <div class="col-12 text-right"><a class="btn btn-primary" href="javascript:window.print();"><i class="fa fa-print"></i> Print</a></div>
            </div>
          </section>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </main>
</body>
</html>