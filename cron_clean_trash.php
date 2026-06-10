<?php
// cron_clean_trash.php
// Run this script as a cron job to delete trash bin files older than 6 months.

header('Content-Type: text/plain; charset=utf-8');

try {
    $trashBinDir = __DIR__ . '/uploads/trash_bin';
    if (!is_dir($trashBinDir)) {
        echo "[" . date('Y-m-d H:i:s') . "] Success: Trash bin directory does not exist or has nothing to clean.\n";
        exit;
    }

    $sixMonthsAgo = strtotime('-6 months');
    if ($sixMonthsAgo === false) {
        throw new Exception("Failed to calculate cutoff time.");
    }

    $deletedCount = 0;
    
    function cleanTrashBinRecursively(string $dir, int $cutoffTimestamp, int &$count): void {
        if (!is_dir($dir)) {
            return;
        }

        $files = scandir($dir);
        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filePath = $dir . '/' . $file;
            if (is_dir($filePath)) {
                cleanTrashBinRecursively($filePath, $cutoffTimestamp, $count);
                // Remove empty directory
                $subFiles = @scandir($filePath);
                if ($subFiles !== false && count($subFiles) === 2) {
                    @rmdir($filePath);
                }
            } elseif (is_file($filePath)) {
                $mtime = @filemtime($filePath);
                $ctime = @filectime($filePath);
                $lastActionTime = max($mtime ?: 0, $ctime ?: 0);

                if ($lastActionTime > 0 && $lastActionTime < $cutoffTimestamp) {
                    if (@unlink($filePath)) {
                        $count++;
                    }
                }
            }
        }
    }

    cleanTrashBinRecursively($trashBinDir, $sixMonthsAgo, $deletedCount);
    
    echo "[" . date('Y-m-d H:i:s') . "] Success: Deleted $deletedCount trash bin files older than 6 months.\n";

} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] Error: " . $e->getMessage() . "\n";
    error_log("cron_clean_trash.php error: " . $e->getMessage());
}
