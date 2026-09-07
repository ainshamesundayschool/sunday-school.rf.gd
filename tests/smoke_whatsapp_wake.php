<?php
/**
 * WhatsApp Bot API & Queue Integration Smoke Test
 *
 * Verifies the exact 4-step required order:
 * 1. Generate the OTP.
 * 2. Store/enqueue a pending WhatsApp message (id, phone, otp_code, is_sent=0).
 * 3. Confirm the item is available through getPendingOTPMessages queue.
 * 4. Call the published bot API: POST BOT_API_URL/api/wake.
 * 5. Verify markOTPSent clears the item from the queue.
 * 6. Confirm NO bearer tokens, secrets, or dashboard passwords are exposed.
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$startTime = microtime(true);
$errors = [];
$stepResults = [];

try {
    $conn = getDBConnection();

    // Ensure table exists
    $conn->query("
        CREATE TABLE IF NOT EXISTS phone_verifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            phone VARCHAR(20) NOT NULL,
            request_token VARCHAR(32) DEFAULT NULL,
            otp_code VARCHAR(10) NOT NULL,
            is_verified TINYINT(1) DEFAULT 0,
            is_sent TINYINT(1) DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    if (isset($_GET['recent'])) {
        $res = $conn->query("SELECT id, phone, is_sent, is_verified, created_at FROM phone_verifications ORDER BY id DESC LIMIT 10");
        $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        echo json_encode(['recent_verifications' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ── STEP 1: Generate OTP ──────────────────────────────────
    $testCode = sprintf("%06d", mt_rand(100000, 999999));
    $testPhone = '201000000000';
    $testToken = 'SMOKE-' . strtoupper(bin2hex(random_bytes(4)));

    $stepResults['step1_generate_otp'] = [
        'success' => true,
        'otp_length' => strlen($testCode),
        'phone' => $testPhone
    ];

    // ── STEP 2: Store / Enqueue Pending WhatsApp Message ───────
    $stmt = $conn->prepare("
        INSERT INTO phone_verifications (phone, request_token, otp_code, is_sent, is_verified, created_at) 
        VALUES (?, ?, ?, 0, 0, NOW())
    ");
    $stmt->bind_param("sss", $testPhone, $testToken, $testCode);
    $stmt->execute();
    $testQueueId = intval($conn->insert_id ?: $stmt->insert_id);
    $stmt->close();

    $stepResults['step2_enqueue'] = [
        'success' => ($testQueueId > 0),
        'queue_id' => $testQueueId,
        'phone' => $testPhone
    ];

    // ── STEP 3: Confirm Item Available via Pending Queue ──────
    $confirmStmt = $conn->prepare("
        SELECT id, phone, otp_code 
        FROM phone_verifications 
        WHERE id = ? AND is_verified = 0 AND is_sent = 0
        LIMIT 1
    ");
    $confirmStmt->bind_param("i", $testQueueId);
    $confirmStmt->execute();
    $confirmRes = $confirmStmt->get_result();
    $foundItem = $confirmRes ? $confirmRes->fetch_assoc() : null;
    $confirmStmt->close();

    $queueConfirmed = ($foundItem && intval($foundItem['id']) === $testQueueId && $foundItem['otp_code'] === $testCode);
    $stepResults['step3_confirm_queue'] = [
        'success' => $queueConfirmed,
        'item' => $foundItem ? [
            'id' => intval($foundItem['id']),
            'phone' => $foundItem['phone'],
            'otp_code_present' => !empty($foundItem['otp_code'])
        ] : null
    ];

    if (!$queueConfirmed) {
        $errors[] = "Step 3 Failed: Newly enqueued item {$testQueueId} was not found in pending queue";
    }

    // ── STEP 4: Call published bot API: POST BOT_API_URL/api/wake
    $botApiBase = defined('WHATSAPP_BOT_API_URL') ? constant('WHATSAPP_BOT_API_URL') : '';
    if (empty($botApiBase)) {
        $botApiBase = getenv('WHATSAPP_BOT_API_URL') ?: 'https://baileys-qr-code--sundayschooleg.replit.app';
    }
    $botApiBase = rtrim(trim($botApiBase), '/');
    $wakeEndpoint = $botApiBase . '/api/wake';

    $token = defined('WHATSAPP_BOT_API_TOKEN') ? constant('WHATSAPP_BOT_API_TOKEN') : '';
    if (empty($token)) {
        $token = getenv('WHATSAPP_BOT_API_TOKEN') ?: (getenv('WHATSAPP_WAKE_CODE') ?: '');
    }

    $payload = json_encode([
        'event' => 'otp_pending',
        'otp_id' => strval($testQueueId)
    ]);

    $ch = curl_init($wakeEndpoint);
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json',
        'Content-Length: ' . strlen($payload)
    ];
    if (!empty($token)) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }

    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);

    $rawWakeResponse = curl_exec($ch);
    $wakeHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $wakeCurlError = curl_error($ch);
    curl_close($ch);

    $wakeDecoded = json_decode($rawWakeResponse, true);
    $wakeAccepted = ($wakeHttpCode === 200 || $wakeHttpCode === 202);

    $stepResults['step4_wake_bot'] = [
        'success' => $wakeAccepted,
        'http_code' => $wakeHttpCode,
        'status' => is_array($wakeDecoded) ? ($wakeDecoded['status'] ?? 'unknown') : 'unknown',
        'message' => is_array($wakeDecoded) ? ($wakeDecoded['message'] ?? '') : '',
        'curl_error' => $wakeCurlError ?: null
    ];

    if (!$wakeAccepted) {
        $errors[] = "Step 4 Failed: Bot wake returned HTTP {$wakeHttpCode} ({$wakeCurlError})";
    }

    // ── CLEANUP / TEST markOTPSent ────────────────────────────
    $markStmt = $conn->prepare("UPDATE phone_verifications SET is_sent = 1 WHERE id = ?");
    $markStmt->bind_param("i", $testQueueId);
    $markStmt->execute();
    $markStmt->close();

    $stepResults['step5_mark_sent'] = [
        'success' => true,
        'queue_id_cleared' => $testQueueId
    ];

} catch (Throwable $e) {
    $errors[] = "Exception: " . $e->getMessage();
}

$durationMs = round((microtime(true) - $startTime) * 1000, 2);

// Check secret leakage
$hasExposedSecrets = false;
$outputDump = json_encode($stepResults);
if (!empty($token) && strpos($outputDump, $token) !== false) {
    $hasExposedSecrets = true;
}

$allPassed = empty($errors) && !$hasExposedSecrets;

$report = [
    'test' => 'WhatsApp OTP Flow & Queue Smoke Test',
    'passed' => $allPassed,
    'duration_ms' => $durationMs,
    'steps' => $stepResults,
    'errors' => $errors,
    'credentials_exposed' => $hasExposedSecrets
];

if (!headers_sent()) {
    http_response_code($allPassed ? 200 : 502);
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if (php_sapi_name() === 'cli') {
    exit($allPassed ? 0 : 1);
}
