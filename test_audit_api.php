<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
$_SESSION['church_id'] = 1;
$_SESSION['uncle_id'] = 1;
$_SESSION['uncle_role'] = 'developer';

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/audit.php';

echo "<h1>Audit Log Test</h1>";

try {
    $conn = getDBConnection();
    echo "<h3>1. DB Connection established</h3>";
    
    // Check if table exists
    $check = $conn->query("SHOW TABLES LIKE 'audit_logs'");
    if ($check && $check->num_rows > 0) {
        echo "✅ Table `audit_logs` exists.<br>";
        
        // Show columns
        $cols = $conn->query("SHOW COLUMNS FROM audit_logs");
        echo "Columns in `audit_logs` table:<br>";
        while ($row = $cols->fetch_assoc()) {
            echo " - " . $row['Field'] . " (" . $row['Type'] . ")<br>";
        }
    } else {
        echo "❌ Table `audit_logs` does NOT exist.<br>";
        echo "Running ensureAuditLogsTable...<br>";
        ensureAuditLogsTable($conn);
        echo "Done.<br>";
    }
    
    echo "<h3>2. Calling getAuditLogs()</h3>";
    $_POST['limit'] = 10;
    $_POST['offset'] = 0;
    getAuditLogs();
    
} catch (Throwable $e) {
    echo "<h2 style='color:red;'>Caught Throwable:</h2>";
    echo "<b>Message:</b> " . $e->getMessage() . "<br>";
    echo "<b>File:</b> " . $e->getFile() . "<br>";
    echo "<b>Line:</b> " . $e->getLine() . "<br>";
    echo "<pre>" . $e->getTraceAsString() . "</pre>";
}
