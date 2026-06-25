<?php
require_once __DIR__ . '/config.php';
try {
    $conn = getDBConnection();
    echo "Connection successful!\n";
    
    // Check if table exists
    $res = $conn->query("SHOW TABLES LIKE 'students'");
    if ($res && $res->num_rows > 0) {
        echo "Table students exists!\n";
    } else {
        echo "Table students does NOT exist!\n";
    }

    // Try altering table
    $alter = $conn->query("ALTER TABLE students ADD COLUMN added_by VARCHAR(100) DEFAULT NULL");
    if ($alter) {
        echo "ALTER TABLE succeeded!\n";
    } else {
        echo "ALTER TABLE failed: " . $conn->error . "\n";
    }

    // Show columns
    $cols = $conn->query("SHOW COLUMNS FROM students");
    if ($cols) {
        echo "Columns in students table:\n";
        while ($row = $cols->fetch_assoc()) {
            echo " - " . $row['Field'] . " (" . $row['Type'] . ")\n";
        }
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
