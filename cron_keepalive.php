<?php
/**
 * Cron Keep-Alive & Clean-Up Script
 * 
 * Purpose:
 * 1. Pings the Replit WhatsApp Bot server to keep it active 24/7.
 * 2. Cleans up old expired OTP entries from phone_verifications table.
 * 
 * Hostinger Cron Job Setup:
 * - Recommended Command: curl -s https://sunday-school.rf.gd/cron_keepalive.php
 *   (or: php /home/uXXXXXX/public_html/cron_keepalive.php)
 * - Recommended Schedule: Every 2 to 5 minutes (e.g. rate of every 3 min)
 */

header('Content-Type: text/plain; charset=utf-8');
date_default_timezone_set('Africa/Cairo');

require_once __DIR__ . '/config.php';

// 1. Ping Replit WhatsApp Bot Apps
$replitUrls = [
    'https://baileys-qr-code--sundayschooleg.replit.app/',
    'https://sunday-school-reactivate--ainshamesundays.replit.app/'
];
$statusMessages = [];

foreach ($replitUrls as $replitUrl) {
    $ch = curl_init($replitUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 4);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'HostingerCronKeepAlive/1.0');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $host = parse_url($replitUrl, PHP_URL_HOST);
    $statusMessages[] = "$host: " . (($httpCode >= 200 && $httpCode < 400) ? "OK ($httpCode)" : "FAIL ($httpCode " . ($curlError ?: "") . ")");
}
$statusStr = implode(' | ', $statusMessages);

// 2. Clean up expired OTPs older than 30 minutes
$cleanedCount = 0;
try {
    if (function_exists('getDBConnection')) {
        $conn = getDBConnection();
        $stmt = $conn->prepare("DELETE FROM phone_verifications WHERE created_at < NOW() - INTERVAL 30 MINUTE");
        if ($stmt) {
            $stmt->execute();
            $cleanedCount = $stmt->affected_rows;
        }
    }
} catch (Throwable $e) {
    // Non-blocking cleanup error handling
}

$now = date('Y-m-d H:i:s');
echo "[$now] Replit Bot Ping: $statusStr | Expired OTPs Cleaned: $cleanedCount\n";
