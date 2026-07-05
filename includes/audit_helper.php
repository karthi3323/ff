<?php
function setAuditUser($user_id) {
    // Set the user context for audit logging
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        $query = "SELECT ff_sch.set_user_context(:user_id)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->execute();
        return true;
    } catch (Exception $e) {
        error_log("Audit user context error: " . $e->getMessage());
        return false;
    }
}

// function logAuditAction($action, $table, $record_id, $details = null) {
//     // Manual audit logging if needed
//     $database = new Database();
//     $db = $database->getConnection();
    
//     try {
//         $query = "INSERT INTO ff_sch.audit_logs (table_name, record_id, action, new_values, user_id) 
//                   VALUES (:table_name, :record_id, :action, :new_values, :user_id)";
//         $stmt = $db->prepare($query);
//         $stmt->bindParam(':table_name', $table);
//         $stmt->bindParam(':record_id', $record_id);
//         $stmt->bindParam(':action', $action);
//         $stmt->bindParam(':new_values', $details);
//         $stmt->bindParam(':user_id', $_SESSION['user_id']);
//         $stmt->execute();
//         return true;
//     } catch (Exception $e) {
//         error_log("Audit log error: " . $e->getMessage());
//         return false;
//     }
// }

function logAuditAction($action, $table, $record_id, $details = null) {
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        $query = "INSERT INTO ff_sch.audit_logs 
                  (table_name, operation, record_id, new_data, changed_by) 
                  VALUES (:table_name, :operation, :record_id, :new_data, :changed_by)";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':table_name', $table);
        $stmt->bindParam(':operation', $action);
        $stmt->bindParam(':record_id', $record_id);
        $stmt->bindParam(':new_data', $details);
        $stmt->bindParam(':changed_by', $_SESSION['user_id']);
        $stmt->execute();
        return true;
    } catch (Exception $e) {
        error_log("Audit log error: " . $e->getMessage());
        return false;
    }
}

?>