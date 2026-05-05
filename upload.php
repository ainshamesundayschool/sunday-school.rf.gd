<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/upload_errors.log');

function sendJson($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Image Enhancement Function ──────────────────────────────
function enhanceImage($imagePath, $targetWidth = 400, $targetHeight = 500) {
    // Load image
    $imageInfo = @getimagesize($imagePath);
    if (!$imageInfo) {
        return null;
    }
    
    $mime = $imageInfo['mime'];
    $source = null;
    
    // Create image resource based on type
    if ($mime === 'image/jpeg') {
        $source = @imagecreatefromjpeg($imagePath);
    } elseif ($mime === 'image/png') {
        $source = @imagecreatefrompng($imagePath);
    } elseif ($mime === 'image/gif') {
        $source = @imagecreatefromgif($imagePath);
    } elseif ($mime === 'image/webp') {
        $source = @imagecreatefromwebp($imagePath);
    }
    
    if (!$source) {
        return null;
    }
    
    $width = imagesx($source);
    $height = imagesy($source);
    
    // Calculate new dimensions maintaining aspect ratio
    $ratio = $width / $height;
    $targetRatio = $targetWidth / $targetHeight;
    
    if ($ratio > $targetRatio) {
        $newWidth = $targetHeight * $ratio;
        $newHeight = $targetHeight;
    } else {
        $newWidth = $targetWidth;
        $newHeight = $targetWidth / $ratio;
    }
    
    // Create intermediate image (scaled)
    $scaled = imagecreatetruecolor($newWidth, $newHeight);
    imagealphablending($scaled, false);
    imagesavealpha($scaled, true);
    imagecopyresampled($scaled, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    // Create final image with cropping (center crop)
    $final = imagecreatetruecolor($targetWidth, $targetHeight);
    imagealphablending($final, false);
    imagesavealpha($final, true);
    
    $offsetX = ($newWidth - $targetWidth) / 2;
    $offsetY = ($newHeight - $targetHeight) / 2;
    
    imagecopy($final, $scaled, 0, 0, $offsetX, $offsetY, $targetWidth, $targetHeight);
    
    // Apply enhancement: increase contrast and brightness
    imagefilter($final, IMG_FILTER_CONTRAST, 5);
    imagefilter($final, IMG_FILTER_BRIGHTNESS, 15);
    imagefilter($final, IMG_FILTER_SMOOTH, 1);
    
    imagedestroy($source);
    imagedestroy($scaled);
    
    return $final;
}

// ── Save enhanced image ──────────────────────────────────────
function saveEnhancedImage($image, $outputPath, $quality = 85) {
    $ext = strtolower(pathinfo($outputPath, PATHINFO_EXTENSION));
    
    if ($ext === 'jpg' || $ext === 'jpeg') {
        return imagejpeg($image, $outputPath, $quality);
    } elseif ($ext === 'png') {
        return imagepng($image, $outputPath, 8);
    } elseif ($ext === 'gif') {
        return imagegif($image, $outputPath);
    } elseif ($ext === 'webp') {
        return imagewebp($image, $outputPath, $quality);
    }
    
    return imagejpeg($image, $outputPath, $quality);
}

try {
    // Check if this is a file upload
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        sendJson(['success' => false, 'message' => 'لم يتم رفع أي ملف']);
    }

    $file = $_FILES['photo'];
    $studentName = $_POST['studentName'] ?? '';
    $studentPhone = $_POST['studentPhone'] ?? '';
    $applyEnhancement = isset($_POST['enhanceImage']) && $_POST['enhanceImage'] === 'true';
    
    // Validate file
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $maxSize = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowedTypes)) {
        sendJson(['success' => false, 'message' => 'نوع الملف غير مسموح به']);
    }
    
    if ($file['size'] > $maxSize) {
        sendJson(['success' => false, 'message' => 'حجم الملف كبير جداً']);
    }
    
    // Generate unique filename
    $timestamp = time();
    $random = bin2hex(random_bytes(4));
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = "profile_{$studentPhone}_{$timestamp}_{$random}.{$extension}";
    
    // Upload directory
    $uploadDir = __DIR__ . '/uploads/students/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $filePath = $uploadDir . $filename;
    
    // Move uploaded file temporarily
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        sendJson(['success' => false, 'message' => 'فشل في رفع الملف']);
    }
    
    // Apply enhancement if requested and GD is available
    if ($applyEnhancement && extension_loaded('gd')) {
        $enhanced = @enhanceImage($filePath, 400, 500);
        if ($enhanced) {
            @saveEnhancedImage($enhanced, $filePath, 85);
            imagedestroy($enhanced);
        }
    }
    
    // Full URL to the uploaded image
    $baseUrl = 'https://' . $_SERVER['HTTP_HOST'];
    $imageUrl = $baseUrl . '/uploads/students/' . $filename;
    
    sendJson([
        'success' => true,
        'message' => 'تم رفع الصورة بنجاح',
        'imageUrl' => $imageUrl,
        'fileName' => $filename,
        'studentName' => $studentName,
        'studentPhone' => $studentPhone,
        'enhanced' => $applyEnhancement && extension_loaded('gd')
    ]);
    
} catch (Exception $e) {
    error_log("Upload error: " . $e->getMessage());
    sendJson(['success' => false, 'message' => 'خطأ في السيرفر']);
}
?>