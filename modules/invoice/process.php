<?php
session_start();
require_once "../../config/database.php";
require_once "../../config/constants.php";

$database = new Database();
$db = $database->getConnection();

$response = ['exists' => false];

if (!empty($_POST['invoice_no'])) {
    $invoice_no = $_POST['invoice_no'];
    $fiscal_year_id = $_POST['fiscal_year_id'];
    $source = $_POST['source'];

    if ($source == 'add') {
        $table = 'invoices';
    } else if ($source == 'proforma') {
        $table = 'proforma_invoices';
    } else {
        $table = 'temp_inv';
    }

    $sql = "SELECT 1
            FROM ff_sch.".$table."
            WHERE invoice_no = :invoice_no
            AND fiscal_year_id = :fiscal_year_id
            LIMIT 1";

    $stmt = $db->prepare($sql);
    $stmt->bindParam(':invoice_no', $invoice_no);
    $stmt->bindParam(':fiscal_year_id', $fiscal_year_id);
    $stmt->execute();

    if ($stmt->fetch()) {
        $response['exists'] = 'error';
    } else {
        $response['exists'] = 'success';
    }
}

echo json_encode($response);
