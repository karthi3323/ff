<?php
require_once 'config/database.php';
$db = (new Database())->getConnection();
$stmt = $db->query("SELECT column_name FROM information_schema.columns WHERE table_schema = 'ff_sch' AND table_name = 'proforma_invoices';");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
print_r($columns);
