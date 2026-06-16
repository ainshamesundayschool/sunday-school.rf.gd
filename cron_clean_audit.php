<?php
// cron_clean_audit.php
// Run this script as a cron job to delete audit logs older than 6 months.

header('Content-Type: text/plain; charset=utf-8');

try {
    $configRoot = __DIR__;
    while ($configRoot && !file_exists($configRoot . '/api.php')) {
        $configParent = dirname($configRoot);
        if ($configParent === $configRoot) {
            break;
        }
        $configRoot = $configParent;
    }
    $isTesting = (strpos($configRoot, '/testing') !== false);
    $configName = $isTesting ? 'config-testing.php' : 'config.php';

    $configFile = $configRoot . '/' . $configName;
    if (!is_file($configFile)) {
        $configFile = dirname($configRoot) . '/' . $configName;
        if (!is_file($configFile)) {
            throw new Exception("Configuration file ($configName) not found.");
        }
    }
    require_once $configFile;

    if (!function_exists('getDBConnection')) {
        throw new Exception("getDBConnection function is not defined.");
    }

    $conn = getDBConnection();

    // Query to delete logs older than 6 months
    $stmt = $conn->prepare("DELETE FROM audit_logs WHERE created_at < NOW() - INTERVAL 6 MONTH");
    if (!$stmt) {
        throw new Exception("Failed to prepare statement: " . $conn->error);
    }

    if ($stmt->execute()) {
        $deletedRows = $stmt->affected_rows;
        echo "[" . date('Y-m-d H:i:s') . "] Success: Deleted $deletedRows audit log entries older than 6 months.\n";
    } else {
        throw new Exception("Execute failed: " . $stmt->error);
    }

} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error: " . $e->getMessage() . "\n";
    error_log("cron_clean_audit.php error: " . $e->getMessage());
}
