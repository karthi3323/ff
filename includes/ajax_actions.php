<?php
session_start();

// error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once "../config/database.php";

header('Content-Type: application/json');

$database = new Database();
$db = $database->getConnection();

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if (isset($_POST['action']) && $_POST['action'] == 'getPartyDetails') {

    if (!isset($_POST['party_id']) || empty($_POST['party_id'])) {
        echo json_encode(['success' => false, 'message' => 'Party ID is required']);
        exit;
    }
    $party_id = $_POST['party_id'];

    try {
        $database = new Database();
        $db = $database->getConnection();
        
       $stmt = $db->prepare("
    SELECT 
        id, 
        name, 
        state, 
        gst_no, 
        TRIM(
            COALESCE(address_line1, '') ||
            CASE 
                WHEN address_line2 IS NOT NULL AND address_line2 <> '' 
                THEN ', ' || address_line2 
                ELSE '' 
            END
        ) AS address,
        city, agent_name
    FROM ff_sch.parties 
    WHERE id = ? AND is_active = true
");

$stmt->execute([$party_id]);
$party = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt->execute([$party_id]);
        $party = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($party) {
            echo json_encode(['success' => true, 'data' => $party]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Party not found']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} /* else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
} */

if (isset($_POST['action']) && $_POST['action'] == 'getProformaDetails') {
    if (!isset($_POST['proforma_id']) || empty($_POST['proforma_id'])) {
        echo json_encode(['success' => false, 'message' => 'Pro-forma ID is required.']);
        exit;
    }

    $proforma_id = $_POST['proforma_id'];

    try {
        // Fetch main pro-forma invoice details
        $invoice_query = "SELECT * FROM ff_sch.proforma_invoices WHERE id = :id";
        $stmt = $db->prepare($invoice_query);
        $stmt->bindParam(':id', $proforma_id, PDO::PARAM_INT);
        $stmt->execute();
        $invoice_details = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$invoice_details) {
            echo json_encode(['success' => false, 'message' => 'Pro-forma invoice not found.']);
            exit;
        }

        // Fetch pro-forma invoice items
        $items_query = "SELECT * FROM ff_sch.proforma_invoice_items WHERE invoice_id = :id ORDER BY id";
        $stmt = $db->prepare($items_query);
        $stmt->bindParam(':id', $proforma_id, PDO::PARAM_INT);
        $stmt->execute();
        $invoice_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $response_data = ['invoice' => $invoice_details, 'items' => $invoice_items];
        echo json_encode(['success' => true, 'data' => $response_data]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if(isset($_POST['action']) && $_POST['action'] == 'updateComp'){

    if (!isset($_POST['inv_chk'], $_POST['company_id'])) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid data'
        ]);
        exit;
    }

    $inv_chk    = $_POST['inv_chk'] === 'Y' ? true : false;
    $company_id = (int) $_POST['company_id'];

    try {
        
        $sql = "UPDATE ff_sch.companies
                SET inv_chk = :inv_chk
                WHERE id = :id";

        $stmt = $db->prepare($sql);
        $stmt->bindParam(':id', $company_id);
        $stmt->bindParam(':inv_chk', $inv_chk, PDO::PARAM_BOOL);
        // $stmt = $db->prepare($sql);
        $stmt->execute();

        echo json_encode([
            'status' => 'success'
        ]);

    } catch (Exception $e) {

        echo json_encode([
            'status' => 'error',
            // 'message' => 'Update failed'
            'message' => $e->getMessage() // TEMP: for debugging
        ]);
    }
}

if (isset($_POST['action']) && $_POST['action'] == 'getPriceCodes') {
    if (!isset($_POST['fiscal_year_id']) || empty($_POST['fiscal_year_id'])) {
        echo json_encode(['success' => false, 'message' => 'Fiscal Year ID is required.']);
        exit;
    }

    $fiscal_year_id = $_POST['fiscal_year_id'];

    try {
        $query = "SELECT id, code, name FROM ff_sch.price_codes WHERE fiscal_year_id = :fiscal_year_id AND is_active = true ORDER BY name";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':fiscal_year_id', $fiscal_year_id, PDO::PARAM_INT);
        $stmt->execute();
        $price_codes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'data' => $price_codes]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

if (isset($_POST['action']) && ($_POST['action'] == 'getProductDetailsForInvoice' || $_POST['action'] == 'getProductDetails')) {
    if (!isset($_POST['product_id']) || empty($_POST['product_id']) || !isset($_POST['price_code_id'])) {
        echo json_encode(['success' => false, 'message' => 'Product ID and Price Code ID are required.']);
        exit;
    }

    $product_id = (int)$_POST['product_id'];
    $price_code_id = (int)$_POST['price_code_id'];

    try {
        $query = "SELECT 
                    p.name, p.uom, p.carton_contents, p.per_box_pieces, COALESCE(pp.rate, 0.00) as rate
                  FROM ff_sch.products p
                  LEFT JOIN ff_sch.product_prices pp ON p.id = pp.product_id AND pp.price_code_id =:price_code_id
                  WHERE p.id =:product_id";
        
        $stmt = $db->prepare($query);
        $stmt->bindParam(':product_id', $product_id, PDO::PARAM_INT);
        $stmt->bindParam(':price_code_id', $price_code_id, PDO::PARAM_INT);
        $stmt->execute();
        $product_details = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product_details) {
            echo json_encode(['success' => true, 'data' => $product_details]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Product details not found.']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if (isset($_POST['action']) && $_POST['action'] == 'getDocumentsForReceipt') {
    if (!isset($_POST['agent_name']) || !isset($_POST['doc_type'])) {
        echo json_encode(['success' => false, 'message' => 'Agent name and document type are required.']);
        exit;
    }

    $agent_name = $_POST['agent_name'];
    $doc_type = $_POST['doc_type'];
    $rcpt_hdr_id = isset($_POST['rcpt_hdr_id']) ? (int)$_POST['rcpt_hdr_id'] : 0;

    try {
        if ($rcpt_hdr_id > 0) {
            // EDIT MODE: Fetch only documents associated with the current receipt
            if ($doc_type == 'estimate') {
                $query = "SELECT e.id, e.estimate_no as doc_no, e.estimate_date as doc_date, e.net_amount, p.name as party_name, e.pending_amount, rd.receipt_amount
                          FROM ff_sch.rcpt_dtl rd
                          JOIN ff_sch.estimate e ON rd.estimate_id = e.id
                          JOIN ff_sch.parties p ON e.party_id = p.id
                          WHERE rd.rcpt_hdr_id = :rcpt_hdr_id ORDER BY e.estimate_date DESC, e.estimate_no DESC";
            } else { // invoice
                $query = "SELECT i.id, i.invoice_no as doc_no, i.invoice_date as doc_date, i.net_amount, p.name as party_name, i.pending_amount, rd.receipt_amount
                          FROM ff_sch.rcpt_dtl rd
                          JOIN ff_sch.invoices i ON rd.invoice_id = i.id
                          JOIN ff_sch.parties p ON i.party_id = p.id
                          WHERE rd.rcpt_hdr_id = :rcpt_hdr_id ORDER BY i.invoice_date DESC, i.invoice_no DESC";
            }
            $stmt = $db->prepare($query);
            $stmt->bindParam(':rcpt_hdr_id', $rcpt_hdr_id, PDO::PARAM_INT);
            $stmt->execute();
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($documents as &$doc) {
                $doc['original_pending_amount'] = (float)$doc['pending_amount'] + (float)$doc['receipt_amount'];
            }
            unset($doc);

        } else {
            // ADD MODE: Fetch all pending documents for the agent
            if (empty($agent_name)) {
                echo json_encode(['success' => true, 'data' => []]);
                exit;
            }
            if ($doc_type == 'estimate') {
                $query = "SELECT e.id, e.estimate_no as doc_no, e.estimate_date as doc_date, e.net_amount, p.name as party_name, e.pending_amount
                          FROM ff_sch.estimate e JOIN ff_sch.parties p ON e.party_id = p.id
                          WHERE e.agent_name = :agent_name AND e.pending_amount > 0
                          ORDER BY e.estimate_date DESC, e.estimate_no DESC";
            } else { // invoice
                $query = "SELECT i.id, i.invoice_no as doc_no, i.invoice_date as doc_date, i.net_amount, p.name as party_name, i.pending_amount
                          FROM ff_sch.invoices i JOIN ff_sch.parties p ON i.party_id = p.id
                          WHERE i.agent_name = :agent_name AND i.pending_amount > 0
                          ORDER BY i.invoice_date DESC, i.invoice_no DESC";
            }
            $stmt = $db->prepare($query);
            $stmt->bindParam(':agent_name', $agent_name, PDO::PARAM_STR);
            $stmt->execute();
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode(['success' => true, 'data' => $documents]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
}
?>
