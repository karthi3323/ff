<?php
require_once "config/database.php";
$database = new Database();
$db = $database->getConnection();
$stmt = $db->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'ff_sch' AND table_name LIKE '%estimate%'");
$tables = $stmt->fetchAll(PDO::FETCH_ASSOC);
print_r($tables);
