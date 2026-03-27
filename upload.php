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

try {
    // Check if this is a file upload
    if (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        sendJson(['success' => false, 'message' => 'لم يتم رفع أي ملف']);
    }

    $file = $_FILES['photo'];
    $studentName = $_POST['studentName'] ?? '';
    $studentPhone = $_POST['studentPhone'] ?? '';
    
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
    
    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        sendJson(['success' => false, 'message' => 'فشل في رفع الملف']);
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
        'studentPhone' => $studentPhone
    ]);
    
} catch (Exception $e) {
    error_log("Upload error: " . $e->getMessage());
    sendJson(['success' => false, 'message' => 'خطأ في السيرفر']);
}
?>