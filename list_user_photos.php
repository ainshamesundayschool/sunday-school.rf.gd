<?php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/photo_list_errors.log');

function sendJson($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $studentPhone = $input['studentPhone'] ?? '';
    $action = $input['action'] ?? 'list';
    $filterByPhone = $input['filterByPhone'] ?? true;
    
    $uploadDir = __DIR__ . '/uploads/students/';
    
    // Security: Validate upload directory
    $realUploadDir = realpath($uploadDir);
    if (!$realUploadDir || !is_dir($realUploadDir)) {
        sendJson(['success' => false, 'message' => 'Invalid upload directory']);
    }
    
    // Get all image files
    $files = glob($uploadDir . '*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE);
    $fileNames = array_map('basename', $files);
    
    // If we have a phone number and should filter, filter the files
    if ($filterByPhone && !empty($studentPhone)) {
        $cleanPhone = preg_replace('/[^0-9]/', '', $studentPhone);
        
        // Pattern to match photos for this specific phone
        $pattern = '/^profile_.*' . preg_quote($cleanPhone, '/') . '_\d+\.(jpg|jpeg|png|gif|webp)$/i';
        
        $filteredFiles = array_filter($fileNames, function($fileName) use ($pattern) {
            return preg_match($pattern, $fileName);
        });
        
        sendJson([
            'success' => true,
            'files' => array_values($filteredFiles),
            'total' => count($filteredFiles),
            'phone' => $cleanPhone
        ]);
    } else {
        // Return all files (with safety information)
        sendJson([
            'success' => true,
            'files' => $fileNames,
            'total' => count($fileNames),
            'warning' => 'Returning all files - consider filtering by phone'
        ]);
    }
    
} catch (Exception $e) {
    error_log("List photos error: " . $e->getMessage());
    sendJson(['success' => false, 'message' => 'Server error']);
}
?>