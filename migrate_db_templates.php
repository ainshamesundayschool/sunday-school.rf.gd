<?php
/**
 * Standalone Database Template Migration Script
 * Upgrades existing saved templates in MySQL to the new schema format.
 */

// Show errors for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: text/html; charset=utf-8');

try {
    require_once __DIR__ . '/config.php';
    $conn = getDBConnection();
    
    echo "<h2>Starting database templates migration...</h2>";
    
    // 1. Migrate church_settings templates
    $churchQuery = "SELECT church_id, qr_template FROM church_settings WHERE qr_template IS NOT NULL";
    $churchRes = $conn->query($churchQuery);
    $churchUpdatedCount = 0;
    
    if ($churchRes) {
        while ($row = $churchRes->fetch_assoc()) {
            $churchId = $row['church_id'];
            $templateStr = $row['qr_template'];
            
            $data = json_decode($templateStr, true);
            if ($data) {
                $original = json_encode($data);
                
                // Convert pixels to centimeters if needed
                if (isset($data['width'])) {
                    $w = floatval($data['width']);
                    if ($w > 50) {
                        $data['width'] = round(($w / 37.795) * 10) / 10;
                    }
                }
                if (isset($data['height'])) {
                    $h = floatval($data['height']);
                    if ($h > 50) {
                        $data['height'] = round(($h / 37.795) * 10) / 10;
                    }
                }
                
                // Copy background URL to back card background URL if missing
                if (isset($data['customCardBackgroundDataUrl']) && !empty($data['customCardBackgroundDataUrl'])) {
                    if (!isset($data['customCardBackgroundDataUrlBack']) || empty($data['customCardBackgroundDataUrlBack'])) {
                        $data['customCardBackgroundDataUrlBack'] = $data['customCardBackgroundDataUrl'];
                    }
                }
                if (isset($data['customCardBackgroundDataUrlUncle']) && !empty($data['customCardBackgroundDataUrlUncle'])) {
                    if (!isset($data['customCardBackgroundDataUrlUncleBack']) || empty($data['customCardBackgroundDataUrlUncleBack'])) {
                        $data['customCardBackgroundDataUrlUncleBack'] = $data['customCardBackgroundDataUrlUncle'];
                    }
                }
                
                // Check fields checklist array and default if empty
                if (!isset($data['extraFields']) || !is_array($data['extraFields']) || count($data['extraFields']) === 0) {
                    $data['extraFields'] = [
                        ['key' => 'church_header', 'label' => 'اسم الكنيسة والشعار', 'placement' => 'front', 'icon' => 'fas fa-church'],
                        ['key' => 'student_image', 'label' => 'صورة الطفل', 'placement' => 'front', 'icon' => 'fas fa-user-circle'],
                        ['key' => 'student_name', 'label' => 'اسم الطفل', 'placement' => 'front', 'icon' => 'fas fa-user'],
                        ['key' => 'class', 'label' => 'الفصل', 'placement' => 'front', 'icon' => 'fas fa-graduation-cap'],
                        ['key' => 'student_id', 'label' => 'كود الطفل ID', 'placement' => 'front', 'icon' => 'fas fa-id-badge']
                    ];
                }
                
                // Default printBackCard to true if missing
                if (!isset($data['printBackCard'])) {
                    $data['printBackCard'] = true;
                }
                
                $updatedStr = json_encode($data, JSON_UNESCAPED_UNICODE);
                if ($original !== $updatedStr) {
                    $stmt = $conn->prepare("UPDATE church_settings SET qr_template = ? WHERE church_id = ?");
                    $stmt->bind_param("si", $updatedStr, $churchId);
                    $stmt->execute();
                    $churchUpdatedCount++;
                }
            }
        }
        echo "<p>✅ Successfully updated <strong>$churchUpdatedCount</strong> church templates.</p>";
    } else {
        echo "<p>❌ Failed to query church_settings: " . $conn->error . "</p>";
    }
    
    // 2. Migrate trips templates
    $tripsQuery = "SELECT id, title, qr_template FROM trips WHERE qr_template IS NOT NULL";
    $tripsRes = $conn->query($tripsQuery);
    $tripsUpdatedCount = 0;
    
    if ($tripsRes) {
        while ($row = $tripsRes->fetch_assoc()) {
            $tripId = $row['id'];
            $tripTitle = $row['title'];
            $templateStr = $row['qr_template'];
            
            $data = json_decode($templateStr, true);
            if ($data) {
                $original = json_encode($data);
                
                // Convert pixels to centimeters if needed
                if (isset($data['width'])) {
                    $w = floatval($data['width']);
                    if ($w > 50) {
                        $data['width'] = round(($w / 37.795) * 10) / 10;
                    }
                }
                if (isset($data['height'])) {
                    $h = floatval($data['height']);
                    if ($h > 50) {
                        $data['height'] = round(($h / 37.795) * 10) / 10;
                    }
                }
                
                // Copy background URL to back card background URL if missing
                if (isset($data['customCardBackgroundDataUrl']) && !empty($data['customCardBackgroundDataUrl'])) {
                    if (!isset($data['customCardBackgroundDataUrlBack']) || empty($data['customCardBackgroundDataUrlBack'])) {
                        $data['customCardBackgroundDataUrlBack'] = $data['customCardBackgroundDataUrl'];
                    }
                }
                if (isset($data['customCardBackgroundDataUrlUncle']) && !empty($data['customCardBackgroundDataUrlUncle'])) {
                    if (!isset($data['customCardBackgroundDataUrlUncleBack']) || empty($data['customCardBackgroundDataUrlUncleBack'])) {
                        $data['customCardBackgroundDataUrlUncleBack'] = $data['customCardBackgroundDataUrlUncle'];
                    }
                }
                
                // Check fields checklist array and default if empty
                if (!isset($data['extraFields']) || !is_array($data['extraFields']) || count($data['extraFields']) === 0) {
                    $data['extraFields'] = [
                        ['key' => 'trip_title', 'label' => 'عنوان الرحلة', 'placement' => 'front', 'icon' => 'fas fa-route'],
                        ['key' => 'student_image', 'label' => 'صورة الطفل', 'placement' => 'front', 'icon' => 'fas fa-user-circle'],
                        ['key' => 'student_name', 'label' => 'اسم الطفل', 'placement' => 'front', 'icon' => 'fas fa-user'],
                        ['key' => 'student_id', 'label' => 'كود الطفل ID', 'placement' => 'front', 'icon' => 'fas fa-id-badge'],
                        ['key' => 'church_name_only', 'label' => 'اسم الكنيسة فقط', 'placement' => 'front', 'icon' => 'fas fa-church'],
                        ['key' => 'student_class', 'label' => 'الفصل', 'placement' => 'front', 'icon' => 'fas fa-graduation-cap'],
                        ['key' => 'custom_field_السكن', 'label' => 'السكن', 'placement' => 'front', 'icon' => 'fas fa-tag']
                    ];
                }
                
                // Default printBackCard to true if missing
                if (!isset($data['printBackCard'])) {
                    $data['printBackCard'] = true;
                }
                
                $updatedStr = json_encode($data, JSON_UNESCAPED_UNICODE);
                if ($original !== $updatedStr) {
                    $stmt = $conn->prepare("UPDATE trips SET qr_template = ? WHERE id = ?");
                    $stmt->bind_param("si", $updatedStr, $tripId);
                    $stmt->execute();
                    $tripsUpdatedCount++;
                }
            }
        }
        echo "<p>✅ Successfully updated <strong>$tripsUpdatedCount</strong> trip templates.</p>";
    } else {
        echo "<p>❌ Failed to query trips: " . $conn->error . "</p>";
    }
    
    echo "<h3>Migration completed successfully!</h3>";
    
} catch (Exception $e) {
    echo "<p>❌ Error during migration: " . htmlspecialchars($e->getMessage()) . "</p>";
}
