<?php
// Standalone script to clean up orphaned/unused files and move them to trash_bin.
// Will be removed in the next commit.

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

try {
    $conn = getDBConnection();
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}

// 1. Collect all referenced files in DB
$dbUrls = [];

// Students
$res = $conn->query("SELECT image_url FROM students WHERE image_url IS NOT NULL AND image_url != ''");
while ($row = $res->fetch_assoc()) {
    $dbUrls[] = $row['image_url'];
}

// Uncles
$res = $conn->query("SELECT image_url FROM uncles WHERE image_url IS NOT NULL AND image_url != ''");
while ($row = $res->fetch_assoc()) {
    $dbUrls[] = $row['image_url'];
}

// Trips
$res = $conn->query("SELECT image_url FROM trips WHERE image_url IS NOT NULL AND image_url != ''");
while ($row = $res->fetch_assoc()) {
    $dbUrls[] = $row['image_url'];
}

// Pending registrations
$res = $conn->query("SELECT image_url FROM pending_registrations WHERE image_url IS NOT NULL AND image_url != ''");
while ($row = $res->fetch_assoc()) {
    $dbUrls[] = $row['image_url'];
}

// Normalize DB filenames
$dbFilenames = [];
foreach ($dbUrls as $url) {
    $path = $url;
    if (strpos($path, 'http') === 0) {
        $parsed = parse_url($path);
        $path = $parsed['path'] ?? '';
    }
    $filename = basename($path);
    if (!empty($filename)) {
        $dbFilenames[$filename] = true;
    }
}

// 2. Scan upload directories
$uploadDirs = [
    'students' => __DIR__ . '/uploads/students/',
    'uncle'    => __DIR__ . '/uploads/uncle/',
    'trips'    => __DIR__ . '/uploads/trips/',
    'profiles' => __DIR__ . '/uploads/profiles/',
];

$movedCount = 0;
$movedFiles = [];

foreach ($uploadDirs as $dirName => $dirPath) {
    if (!is_dir($dirPath)) continue;

    $files = @scandir($dirPath);
    if (!$files) continue;

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;

        $fullPath = $dirPath . $file;
        if (!is_file($fullPath)) continue;

        // Skip if this file is in DB
        if (isset($dbFilenames[$file])) {
            continue;
        }

        // Also skip anything already in the trash_bin structure
        if (strpos($dirPath, 'trash_bin') !== false) {
            continue;
        }

        // Target path in trash bin
        $trashDir = __DIR__ . '/uploads/trash_bin/' . $dirName . '/';
        if (!is_dir($trashDir)) {
            @mkdir($trashDir, 0755, true);
        }
        $trashPath = $trashDir . $file;

        // Move the file
        if (@rename($fullPath, $trashPath) || (@copy($fullPath, $trashPath) && @unlink($fullPath))) {
            $movedCount++;
            $movedFiles[] = "uploads/$dirName/$file -> uploads/trash_bin/$dirName/$file";
        }
    }
}

echo "<h1>Trash Bin Cleanup Script</h1>";
echo "<p>Total unused files moved to trash bin: <strong>$movedCount</strong></p>";
if (count($movedFiles) > 0) {
    echo "<h3>Moved Files:</h3>";
    echo "<ul>";
    foreach ($movedFiles as $f) {
        echo "<li>" . htmlspecialchars($f) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No unused files found.</p>";
}
?>
