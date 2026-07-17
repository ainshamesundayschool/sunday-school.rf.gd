<?php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/config.php';

try {
    $conn = getDBConnection();
    echo "=== DATABASE CONNECTION SUCCESSFUL ===\n\n";

    // 1. Check daily_notification_logs table
    echo "=== daily_notification_logs TABLE ===\n";
    $tblCheck1 = $conn->query("SHOW TABLES LIKE 'daily_notification_logs'")->fetch_assoc();
    if ($tblCheck1) {
        echo "Table exists.\n";
        
        echo "\n--- Indexes ---\n";
        $idxRes = $conn->query("SHOW INDEX FROM daily_notification_logs");
        while ($row = $idxRes->fetch_assoc()) {
            echo "Key_name: {$row['Key_name']}, Column: {$row['Column_name']}, Non_unique: {$row['Non_unique']}\n";
        }
        
        echo "\n--- Row Count ---\n";
        $cnt = $conn->query("SELECT COUNT(*) as cnt FROM daily_notification_logs")->fetch_assoc()['cnt'];
        echo "Total rows: $cnt\n";
        
        echo "\n--- Recent Rows ---\n";
        $rows = $conn->query("SELECT * FROM daily_notification_logs ORDER BY id DESC LIMIT 10");
        while ($r = $rows->fetch_assoc()) {
            echo "ID: {$r['id']}, Church: {$r['church_id']}, Date: {$r['check_date']}, Type: {$r['notification_type']}\n";
        }
    } else {
        echo "Table does not exist.\n";
    }

    echo "\n=======================================\n\n";

    // 2. Check push_subscriptions table
    echo "=== push_subscriptions TABLE ===\n";
    $tblCheck2 = $conn->query("SHOW TABLES LIKE 'push_subscriptions'")->fetch_assoc();
    if ($tblCheck2) {
        echo "Table exists.\n";
        
        echo "\n--- Indexes ---\n";
        $idxRes = $conn->query("SHOW INDEX FROM push_subscriptions");
        while ($row = $idxRes->fetch_assoc()) {
            echo "Key_name: {$row['Key_name']}, Column: {$row['Column_name']}, Non_unique: {$row['Non_unique']}\n";
        }
        
        echo "\n--- Row Count ---\n";
        $cnt = $conn->query("SELECT COUNT(*) as cnt FROM push_subscriptions")->fetch_assoc()['cnt'];
        echo "Total rows: $cnt\n";
        
        echo "\n--- Duplicate Endpoints? ---\n";
        $dupRes = $conn->query("SELECT SUBSTRING(endpoint, 1, 100) as endp_prefix, COUNT(*) as cnt FROM push_subscriptions GROUP BY SUBSTRING(endpoint, 1, 100) HAVING cnt > 1");
        if ($dupRes && $dupRes->num_rows > 0) {
            echo "Warning: Duplicate endpoints found!\n";
            while ($row = $dupRes->fetch_assoc()) {
                echo "Prefix: " . substr($row['endp_prefix'], 0, 50) . "... Count: {$row['cnt']}\n";
            }
        } else {
            echo "No duplicate endpoint prefixes found.\n";
        }

        echo "\n--- Subscriptions Grouped by Uncle ---\n";
        $groupRes = $conn->query("SELECT church_id, uncle_id, COUNT(*) as cnt FROM push_subscriptions GROUP BY church_id, uncle_id");
        while ($row = $groupRes->fetch_assoc()) {
            echo "Church: {$row['church_id']}, Uncle: " . ($row['uncle_id'] ?? 'NULL') . ", Subscriptions: {$row['cnt']}\n";
        }
        
        echo "\n--- Recent Subscription Details ---\n";
        $subsRes = $conn->query("SELECT id, church_id, uncle_id, SUBSTRING(endpoint, 1, 50) as endp, created_at FROM push_subscriptions ORDER BY id DESC LIMIT 10");
        while ($row = $subsRes->fetch_assoc()) {
            echo "ID: {$row['id']}, Church: {$row['church_id']}, Uncle: {$row['uncle_id']}, Endpoint: {$row['endp']}..., Created: {$row['created_at']}\n";
        }
    } else {
        echo "Table does not exist.\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
