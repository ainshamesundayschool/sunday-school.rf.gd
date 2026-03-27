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
ini_set('error_log', __DIR__ . '/photo_delete_errors.log');

function sendJson($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $input = json_decode(file_get_contents('php://input'), true);
    $fileName = $input['fileName'] ?? '';
    $action = $input['action'] ?? 'delete';
    
    // Log the request
    error_log("Delete request: fileName='$fileName', action='$action'");
    
    if (empty($fileName)) {
        sendJson(['success' => false, 'message' => 'No file name provided']);
    }
    
    // VERY STRICT VALIDATION: Must be a profile image
    if (!preg_match('/^profile_[a-zA-Z0-9_]+\.(jpg|jpeg|png|gif|webp)$/i', $fileName)) {
        error_log("SECURITY: Invalid filename pattern: $fileName");
        sendJson(['success' => false, 'message' => 'Invalid file name']);
    }
    
    $uploadDir = __DIR__ . '/uploads/students/';
    $filePath = $uploadDir . $fileName;
    
    // Security check: make sure file is within upload directory
    $realFilePath = realpath($filePath);
    $realUploadDir = realpath($uploadDir);
    
    if (!$realFilePath || strpos($realFilePath, $realUploadDir) !== 0) {
        error_log("SECURITY: Path traversal attempt: $fileName");
        sendJson(['success' => false, 'message' => 'Security violation']);
    }
    
    if (!file_exists($filePath)) {
        error_log("File not found: $fileName");
        sendJson(['success' => false, 'message' => 'File not found']);
    }
    
    // Check if it's really an image
    $imageInfo = @getimagesize($realFilePath);
    if (!$imageInfo) {
        error_log("SECURITY: Not an image file: $fileName");
        sendJson(['success' => false, 'message' => 'File is not a valid image']);
    }
    
    if ($action === 'delete') {
        // Perform deletion
        if (unlink($realFilePath)) {
            error_log("✅ Successfully deleted: $fileName");
            sendJson([
                'success' => true, 
                'message' => 'File deleted successfully',
                'fileName' => $fileName
            ]);
        } else {
            error_log("❌ Failed to delete: $fileName");
            sendJson(['success' => false, 'message' => 'Failed to delete file']);
        }
    }
    
    sendJson(['success' => false, 'message' => 'Invalid action']);
    
} catch (Exception $e) {
    error_log("Delete photo error: " . $e->getMessage());
    sendJson(['success' => false, 'message' => 'Server error']);
}
?>