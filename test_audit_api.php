<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
$_SESSION['church_id'] = 1;
$_SESSION['uncle_id'] = 1;
$_SESSION['uncle_role'] = 'developer';

if (!function_exists('checkAuth')) {
    function checkAuth() {
        return true;
    }
}
if (!function_exists('getChurchId')) {
    function getChurchId() {
        return 1;
    }
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/audit.php';

echo "<h1>Audit Log Test</h1>";

try {
    $conn = getDBConnection();
    echo "<h3>1. DB Connection established</h3>";
    
    $check = $conn->query("SHOW TABLES LIKE 'audit_logs'");
    if ($check && $check->num_rows > 0) {
        echo "✅ Table `audit_logs` exists.<br>";
    } else {
        echo "❌ Table `audit_logs` does NOT exist.<br>";
    }
    
    echo "<h3>2. Calling getAuditLogs()</h3>";
    $_POST['limit'] = 500;
    $_POST['offset'] = 0;
    
    getAuditLogs();
    
} catch (Throwable $e) {
    echo "<h2>Caught Throwable:</h2>";
    echo "<b>Class:</b> " . get_class($e) . "<br>";
    echo "<b>Message:</b> " . $e->getMessage() . "<br>";
    echo "<b>File:</b> " . $e->getFile() . "<br>";
    echo "<b>Line:</b> " . $e->getLine() . "<br>";
    echo "<h3>var_dump output:</h3><pre>";
    var_dump($e);
    echo "</pre>";
}
