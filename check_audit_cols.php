<?php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/config.php';

try {
    $conn = getDBConnection();
    echo "=== DATABASE CONNECTION SUCCESSFUL ===\n\n";

    echo "=== DESCRIBE audit_logs ===\n";
    $res = $conn->query("DESCRIBE audit_logs");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            echo "Field: {$row['Field']}, Type: {$row['Type']}, Null: {$row['Null']}, Key: {$row['Key']}\n";
        }
    } else {
        echo "Error describing audit_logs: " . $conn->error . "\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
