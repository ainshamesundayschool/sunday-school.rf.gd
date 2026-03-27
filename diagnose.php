<?php
$uploadDir = __DIR__ . '/uploads/students/';
$files = glob($uploadDir . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);

echo "<h1>Photo Directory Analysis</h1>";
echo "<p>Total files: " . count($files) . "</p>";
echo "<h3>File List:</h3>";
echo "<ul>";

foreach ($files as $file) {
    $fileName = basename($file);
    $fileSize = filesize($file);
    $modified = date('Y-m-d H:i:s', filemtime($file));
    echo "<li>$fileName - Size: $fileSize bytes - Modified: $modified</li>";
}

echo "</ul>";

// Check for recent deletions in log
$logFile = __DIR__ . '/photo_delete_errors.log';
if (file_exists($logFile)) {
    echo "<h3>Recent Deletion Logs:</h3>";
    echo "<pre>";
    $logs = file_get_contents($logFile);
    echo htmlspecialchars(substr($logs, -5000)); // Last 5000 chars
    echo "</pre>";
}
?>