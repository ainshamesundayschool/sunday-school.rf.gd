<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>System Diagnostic Tool</h1>";

// 1. Check config.php
if (!file_exists(__DIR__ . '/config.php')) {
    echo "<p style='color:red;'>❌ <b>config.php</b> does not exist in the root folder!</p>";
    echo "<p>Please copy config.php.example to config.php and fill in your database credentials.</p>";
    exit;
} else {
    echo "<p style='color:green;'>✅ <b>config.php</b> exists.</p>";
}

// 2. Try including config.php
try {
    require_once 'config.php';
    echo "<p style='color:green;'>✅ <b>config.php</b> included successfully.</p>";
} catch (Throwable $e) {
    echo "<p style='color:red;'>❌ Error including <b>config.php</b>: " . $e->getMessage() . "</p>";
    exit;
}

// 3. Check getDBConnection function
if (!function_exists('getDBConnection')) {
    echo "<p style='color:red;'>❌ Function <b>getDBConnection</b> is not defined in config.php!</p>";
    exit;
}

// 4. Test Database Connection
try {
    $conn = getDBConnection();
    echo "<p style='color:green;'>✅ Database connection established successfully.</p>";
} catch (Throwable $e) {
    echo "<p style='color:red;'>❌ Database connection failed: " . $e->getMessage() . "</p>";
    exit;
}

// 5. Check tables and columns
$tables = ['students', 'uncles', 'trips', 'trip_registrations'];
foreach ($tables as $table) {
    $res = $conn->query("SHOW TABLES LIKE '$table'");
    if ($res && $res->num_rows > 0) {
        echo "<p style='color:green;'>✅ Table <b>$table</b> exists.</p>";
        // Simple test query
        $test = $conn->query("SELECT COUNT(*) as count FROM $table");
        if ($test) {
            $row = $test->fetch_assoc();
            echo "   - Row count: " . $row['count'] . "<br>";
        } else {
            echo "   - <span style='color:red;'>Failed to query table: " . $conn->error . "</span><br>";
        }
    } else {
        echo "<p style='color:red;'>❌ Table <b>$table</b> does not exist!</p>";
    }
}
?>