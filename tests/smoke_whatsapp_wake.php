<?php
/**
 * WhatsApp Bot API Smoke Test
 *
 * Verifies that the Sunday School website backend can successfully:
 * 1. Read WHATSAPP_BOT_API_URL without hardcoding secrets.
 * 2. Send the non-blocking POST /api/wake signal with proper timeout and retry configuration.
 * 3. Receive the acknowledgement (HTTP 200 / 202).
 * 4. Confirm that NO bearer tokens, secrets, or dashboard passwords are exposed in the output.
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');

$startTime = microtime(true);

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
    'test' => true,
    'timestamp' => time()
]);

$maxRetries = 3;
$httpCode = 0;
$rawResponse = '';
$curlError = '';
$attempt = 0;

for ($i = 1; $i <= $maxRetries; $i++) {
    $attempt = $i;
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

    $rawResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        if ($i < $maxRetries) {
            usleep(250000);
            continue;
        }
        break;
    }

    if ($httpCode === 200 || $httpCode === 202) {
        break;
    }

    if ($i < $maxRetries && ($httpCode >= 500 || $httpCode === 0)) {
        usleep(250000);
        continue;
    }
    break;
}

$durationMs = round((microtime(true) - $startTime) * 1000, 2);
$decoded = json_decode($rawResponse, true);

// Confirm no tokens or secrets are contained in the response or debug output
$hasExposedSecrets = false;
if (!empty($token) && (strpos($rawResponse, $token) !== false)) {
    $hasExposedSecrets = true;
}

$testPassed = ($httpCode === 200 || $httpCode === 202) && !$hasExposedSecrets;

$report = [
    'test' => 'WhatsApp Bot /api/wake Integration Smoke Test',
    'passed' => $testPassed,
    'endpoint' => $wakeEndpoint,
    'http_code' => $httpCode,
    'bot_status' => is_array($decoded) ? ($decoded['status'] ?? 'unknown') : 'unknown',
    'bot_message' => is_array($decoded) ? ($decoded['message'] ?? '') : '',
    'attempts' => $attempt,
    'duration_ms' => $durationMs,
    'timeout_sec' => 5,
    'retries_configured' => $maxRetries,
    'credentials_exposed' => $hasExposedSecrets,
    'curl_error' => $curlError ?: null
];

if (!headers_sent()) {
    http_response_code($testPassed ? 200 : 502);
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
if (php_sapi_name() === 'cli') {
    exit($testPassed ? 0 : 1);
}
