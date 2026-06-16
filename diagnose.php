<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>System Diagnostic Tool</h1>";

// 1. Check config.php
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

$configPath = $configRoot . '/' . $configName;
if (!file_exists($configPath)) {
    $configPath = dirname($configRoot) . '/' . $configName;
    if (!file_exists($configPath)) {
        echo "<p style='color:red;'>❌ <b>$configName</b> does not exist in root or parent folder!</p>";
        exit;
    } else {
        echo "<p style='color:green;'>✅ <b>$configName</b> exists in parent folder (safe from Git clean).</p>";
    }
} else {
    echo "<p style='color:green;'>✅ <b>$configName</b> exists in root folder.</p>";
}

// 2. Try including config.php
try {
    require_once $configPath;
    echo "<p style='color:green;'>✅ <b>$configName</b> included successfully.</p>";
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