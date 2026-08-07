<?php
// Brethren Platform API Engine
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Brethren Database Connection Config
define('BRETHREN_DB_HOST', 'sql206.infinityfree.com');
define('BRETHREN_DB_USER', 'if0_40860329');
define('BRETHREN_DB_PASS', 'wqSwU86i8GvLDAw');
define('BRETHREN_DB_NAME', 'if0_40860329_brethren');

function getBrethrenDB(): mysqli {
    static $conn = null;
    if ($conn !== null && $conn->ping()) {
        return $conn;
    }

    mysqli_report(MYSQLI_REPORT_OFF);

    // 1. Primary Attempt: Dedicated Brethren Database (if0_40860329_brethren)
    $primaryConn = @new mysqli(BRETHREN_DB_HOST, BRETHREN_DB_USER, BRETHREN_DB_PASS, BRETHREN_DB_NAME, 3306);
    if (!$primaryConn->connect_error) {
        $conn = $primaryConn;
        $conn->set_charset('utf8mb4');
        ensureTablesExist($conn);
        return $conn;
    }

    // 2. Secondary Fallback: Try global site database from config.php
    $rootPath = dirname(__DIR__);
    if (file_exists($rootPath . '/config.php')) {
        @include_once $rootPath . '/config.php';
        if (function_exists('getDBConnection')) {
            try {
                $globalConn = getDBConnection();
                if ($globalConn && !$globalConn->connect_error) {
                    $conn = $globalConn;
                    ensureTablesExist($conn);
                    return $conn;
                }
            } catch (Exception $e) {}
        }
    }

    // 3. Local Development Fallback (XAMPP / Local MySQL)
    $localHosts = ['127.0.0.1', 'localhost'];
    foreach ($localHosts as $host) {
        $localConn = @new mysqli($host, 'root', '', BRETHREN_DB_NAME, 3306);
        if (!$localConn->connect_error) {
            $conn = $localConn;
            $conn->set_charset('utf8mb4');
            ensureTablesExist($conn);
            return $conn;
        }

        $localRoot = @new mysqli($host, 'root', '', '', 3306);
        if (!$localRoot->connect_error) {
            $localRoot->query("CREATE DATABASE IF NOT EXISTS `" . BRETHREN_DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            if ($localRoot->select_db(BRETHREN_DB_NAME)) {
                $conn = $localRoot;
                $conn->set_charset('utf8mb4');
                ensureTablesExist($conn);
                return $conn;
            }
        }
    }

    // Clean user-friendly error response (No raw PHP traces or DNS errors)
    sendJSONResponse(['status' => 'error', 'message' => 'تعذر الاتصال بقاعدة البيانات حالياً، يرجى إعادة المحاولة لاحقاً'], 500);
    exit;
}

function ensureTablesExist(mysqli $conn) {
    // 1. Users Table (with email, is_admin, passcode, custom_fields)
    $conn->query("CREATE TABLE IF NOT EXISTS `brethren_users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_code` VARCHAR(50) UNIQUE NOT NULL,
        `name` VARCHAR(150) NOT NULL,
        `email` VARCHAR(150) DEFAULT NULL,
        `phone` VARCHAR(30) DEFAULT NULL,
        `location` VARCHAR(150) DEFAULT NULL,
        `gender` VARCHAR(20) DEFAULT 'ذكر',
        `birth_date` VARCHAR(50) DEFAULT NULL,
        `photo` LONGTEXT DEFAULT NULL,
        `points` INT DEFAULT 0,
        `is_admin` TINYINT(1) DEFAULT 0,
        `passcode` VARCHAR(255) DEFAULT NULL,
        `custom_fields` LONGTEXT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Self-heal columns if missing
    $checkEmailCol = $conn->query("SHOW COLUMNS FROM `brethren_users` LIKE 'email'");
    if ($checkEmailCol && $checkEmailCol->num_rows === 0) {
        $conn->query("ALTER TABLE `brethren_users` ADD COLUMN `email` VARCHAR(150) DEFAULT NULL AFTER `name`");
    }
    $checkAdminCol = $conn->query("SHOW COLUMNS FROM `brethren_users` LIKE 'is_admin'");
    if ($checkAdminCol && $checkAdminCol->num_rows === 0) {
        $conn->query("ALTER TABLE `brethren_users` ADD COLUMN `is_admin` TINYINT(1) DEFAULT 0");
    }
    $checkPassCol = $conn->query("SHOW COLUMNS FROM `brethren_users` LIKE 'passcode'");
    if ($checkPassCol && $checkPassCol->num_rows === 0) {
        $conn->query("ALTER TABLE `brethren_users` ADD COLUMN `passcode` VARCHAR(255) DEFAULT NULL");
    }

    // 2. Events Table
    $conn->query("CREATE TABLE IF NOT EXISTS `brethren_events` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `event_name` VARCHAR(255) NOT NULL,
        `event_date` VARCHAR(50) NOT NULL,
        `description` TEXT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 3. Attendance Table
    $conn->query("CREATE TABLE IF NOT EXISTS `brethren_attendance` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `event_id` INT NOT NULL,
        `user_id` INT NOT NULL,
        `scanned_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uniq_evt_usr` (`event_id`, `user_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 4. Points History Table
    $conn->query("CREATE TABLE IF NOT EXISTS `brethren_points_history` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `points_change` INT NOT NULL,
        `reason` VARCHAR(255) NOT NULL,
        `type` VARCHAR(50) DEFAULT 'manual',
        `event_id` INT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // 5. Settings Table
    $conn->query("CREATE TABLE IF NOT EXISTS `brethren_settings` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `setting_key` VARCHAR(50) UNIQUE NOT NULL,
        `setting_value` LONGTEXT NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

    // Default Settings
    $checkSet = $conn->query("SELECT COUNT(*) AS cnt FROM `brethren_settings`");
    $row = $checkSet ? $checkSet->fetch_assoc() : null;
    if (!$row || (int)$row['cnt'] === 0) {
        $defaultSettings = [
            'shortcuts' => [10, 30, 50, 100],
            'enable_shortcut' => true,
            'enable_custom' => true,
            'reasons' => ['ألعاب', 'بونص', 'التزام بالأوقات'],
            'admin_passcode' => 'admin123',
            'admin_email' => 'admin@sunday-school.online',
            'google_script_url' => ''
        ];
        foreach ($defaultSettings as $k => $v) {
            $valJson = json_encode($v, JSON_UNESCAPED_UNICODE);
            $stmt = $conn->prepare("INSERT IGNORE INTO `brethren_settings` (`setting_key`, `setting_value`) VALUES (?, ?)");
            $stmt->bind_param('ss', $k, $valJson);
            $stmt->execute();
        }
    }
}

function sendGoogleScriptEmail($toEmail, $subject, $bodyHtml, $db) {
    if (empty($toEmail)) return false;

    $res = $db->query("SELECT `setting_value` FROM `brethren_settings` WHERE `setting_key` = 'google_script_url' LIMIT 1");
    $row = $res ? $res->fetch_assoc() : null;
    $scriptUrl = $row ? json_decode($row['setting_value'], true) : '';

    if (empty($scriptUrl)) return false;

    $payload = json_encode([
        'to' => $toEmail,
        'subject' => $subject,
        'htmlBody' => $bodyHtml
    ], JSON_UNESCAPED_UNICODE);

    $opts = [
        'http' => [
            'method' => 'POST',
            'header' => "Content-Type: application/json\r\n",
            'content' => $payload,
            'timeout' => 5
        ]
    ];

    $context = stream_context_create($opts);
    @file_get_contents($scriptUrl, false, $context);
    return true;
}

function sendJSONResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// Request Router
$action = $_GET['action'] ?? $_POST['action'] ?? '';
$rawInput = file_get_contents('php://input');
$bodyData = [];
if (!empty($rawInput)) {
    $decoded = json_decode($rawInput, true);
    if (is_array($decoded)) {
        $bodyData = $decoded;
    }
}

if (empty($action) && isset($bodyData['action'])) {
    $action = $bodyData['action'];
}

$db = getBrethrenDB();

switch ($action) {

    // ─────────────────────────────────────────────────────────────
    // AUTH, LOGIN & REGISTER ACTIONS
    // ─────────────────────────────────────────────────────────────
    case 'login':
        $key = trim($bodyData['key'] ?? $bodyData['email_or_phone'] ?? $_POST['key'] ?? '');
        $passcode = trim($bodyData['password'] ?? $bodyData['passcode'] ?? $_POST['password'] ?? '');

        if (empty($key)) {
            sendJSONResponse(['status' => 'error', 'message' => 'يرجى إدخال البريد الإلكتروني أو رقم الهاتف'], 400);
        }

        // Check Master Admin Passcode from Settings
        $resPass = $db->query("SELECT `setting_value` FROM `brethren_settings` WHERE `setting_key` = 'admin_passcode' LIMIT 1");
        $rowPass = $resPass ? $resPass->fetch_assoc() : null;
        $globalAdminPass = $rowPass ? json_decode($rowPass['setting_value'], true) : 'admin123';
        if (is_array($globalAdminPass)) $globalAdminPass = reset($globalAdminPass);

        if ($passcode === $globalAdminPass || $key === $globalAdminPass) {
            $resAdmin = $db->query("SELECT * FROM `brethren_users` WHERE `is_admin` = 1 LIMIT 1");
            $adminUser = $resAdmin ? $resAdmin->fetch_assoc() : null;

            if (!$adminUser) {
                $userCode = 'BR-ADMIN01';
                $adminPassHash = password_hash($globalAdminPass, PASSWORD_DEFAULT);
                $stmtNew = $db->prepare("INSERT INTO `brethren_users` 
                    (`user_code`, `name`, `email`, `phone`, `location`, `gender`, `is_admin`, `points`, `passcode`) 
                    VALUES (?, 'المسؤول الإداري', 'admin@sunday-school.online', '01000000000', 'الإدارة', 'ذكر', 1, 100, ?)");
                $stmtNew->bind_param('ss', $userCode, $adminPassHash);
                $stmtNew->execute();

                $stmtFetch = $db->prepare("SELECT * FROM `brethren_users` WHERE `id` = ? LIMIT 1");
                $newId = $db->insert_id;
                $stmtFetch->bind_param('i', $newId);
                $stmtFetch->execute();
                $adminUser = $stmtFetch->get_result()->fetch_assoc();
            }

            $adminUser['custom_fields'] = json_decode($adminUser['custom_fields'] ?? '{}', true) ?: (object)[];
            sendJSONResponse([
                'status' => 'success',
                'is_admin' => true,
                'user' => $adminUser,
                'redirect' => 'admin/'
            ]);
        }

        // Search user by email, phone, user_code, or id
        $stmt = $db->prepare("SELECT * FROM `brethren_users` WHERE `email` = ? OR `phone` = ? OR `user_code` = ? OR `id` = ? LIMIT 1");
        $stmt->bind_param('ssss', $key, $key, $key, $key);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) {
            sendJSONResponse(['status' => 'error', 'message' => 'لم نتمكن من العثور على حساب بهذا البريد أو الهاتف'], 404);
        }

        // Secure Password Verification (password_verify + backward compatibility plain-text auto-hash)
        if (!empty($user['passcode']) && !empty($passcode)) {
            $isPasswordValid = password_verify($passcode, $user['passcode']) || ($user['passcode'] === $passcode);
            if (!$isPasswordValid) {
                sendJSONResponse(['status' => 'error', 'message' => 'كلمة المرور غير صحيحة'], 401);
            }

            // Auto-hash plain text passwords to Bcrypt for security
            if ($user['passcode'] === $passcode) {
                $newHashedPass = password_hash($passcode, PASSWORD_DEFAULT);
                $stmtUpdHash = $db->prepare("UPDATE `brethren_users` SET `passcode` = ? WHERE `id` = ?");
                $stmtUpdHash->bind_param('si', $newHashedPass, $user['id']);
                $stmtUpdHash->execute();
            }
        }

        $user['custom_fields'] = json_decode($user['custom_fields'] ?? '{}', true) ?: (object)[];
        $isAdmin = (int)$user['is_admin'] === 1;

        sendJSONResponse([
            'status' => 'success',
            'is_admin' => $isAdmin,
            'user' => $user,
            'redirect' => $isAdmin ? 'admin/' : 'user/'
        ]);
        break;

    case 'register':
        $name = trim($bodyData['name'] ?? $_POST['name'] ?? '');
        $email = trim($bodyData['email'] ?? $_POST['email'] ?? '');
        $phone = trim($bodyData['phone'] ?? $_POST['phone'] ?? '');
        $passcode = trim($bodyData['password'] ?? $bodyData['passcode'] ?? $_POST['password'] ?? '');
        $location = trim($bodyData['location'] ?? $_POST['location'] ?? '');
        $gender = trim($bodyData['gender'] ?? $_POST['gender'] ?? 'ذكر');
        $birthDate = trim($bodyData['birth_date'] ?? $_POST['birth_date'] ?? '');
        $customFields = $bodyData['custom_fields'] ?? $_POST['custom_fields'] ?? [];

        if (empty($name)) {
            sendJSONResponse(['status' => 'error', 'message' => 'يرجى إدخال الاسم بالكامل'], 400);
        }
        if (empty($email) && empty($phone)) {
            sendJSONResponse(['status' => 'error', 'message' => 'يرجى إدخال البريد الإلكتروني أو رقم الهاتف'], 400);
        }

        if (!empty($email)) {
            $stmtDup = $db->prepare("SELECT `id` FROM `brethren_users` WHERE `email` = ? LIMIT 1");
            $stmtDup->bind_param('s', $email);
            $stmtDup->execute();
            if ($stmtDup->get_result()->fetch_assoc()) {
                sendJSONResponse(['status' => 'error', 'message' => 'البريد الإلكتروني مسجل بحساب آخر بالفعل'], 400);
            }
        }

        if (is_string($customFields)) {
            $customFields = json_decode($customFields, true) ?: [];
        }

        $userCode = 'BR-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        $hashedPasscode = !empty($passcode) ? password_hash($passcode, PASSWORD_DEFAULT) : '';
        $customFieldsJson = json_encode($customFields, JSON_UNESCAPED_UNICODE);

        $stmt = $db->prepare("INSERT INTO `brethren_users` 
            (`user_code`, `name`, `email`, `phone`, `location`, `gender`, `birth_date`, `passcode`, `points`, `is_admin`, `custom_fields`) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, 0, ?)");
        $stmt->bind_param('sssssssss', $userCode, $name, $email, $phone, $location, $gender, $birthDate, $hashedPasscode, $customFieldsJson);
        
        if (!$stmt->execute()) {
            sendJSONResponse(['status' => 'error', 'message' => 'حدث خطأ أثناء إنشاء حساب جديد'], 500);
        }

        $newUserId = $db->insert_id;

        $stmtFetch = $db->prepare("SELECT * FROM `brethren_users` WHERE `id` = ? LIMIT 1");
        $stmtFetch->bind_param('i', $newUserId);
        $stmtFetch->execute();
        $newUser = $stmtFetch->get_result()->fetch_assoc();
        $newUser['custom_fields'] = json_decode($newUser['custom_fields'] ?? '{}', true) ?: (object)[];

        if (!empty($email)) {
            $subject = "مرحباً بك في منصة الأخوة!";
            $bodyHtml = "<h3>أهلاً بك يا {$name} في منصة الأخوة</h3>" .
                        "<p>كود الـ QR الخاص بك: <strong>{$userCode}</strong></p>" .
                        "<p>تأكد من إبراز الكود عند حضور الفعاليات لجمع النقاط والمكافآت!</p>";
            sendGoogleScriptEmail($email, $subject, $bodyHtml, $db);
        }

        sendJSONResponse([
            'status' => 'success',
            'message' => 'تم إنشاء الحساب بنجاح!',
            'user' => $newUser,
            'redirect' => 'user/'
        ]);
        break;

    // ─────────────────────────────────────────────────────────────
    // USERS ACTIONS
    // ─────────────────────────────────────────────────────────────
    case 'get_users':
        $res = $db->query("SELECT * FROM `brethren_users` ORDER BY `name` ASC");
        $users = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $row['custom_fields'] = json_decode($row['custom_fields'] ?? '{}', true) ?: (object)[];
                $users[] = $row;
            }
        }
        sendJSONResponse(['status' => 'success', 'users' => $users]);
        break;

    case 'get_user':
        $userId = (int)($_GET['id'] ?? $bodyData['id'] ?? 0);
        $userCode = trim($_GET['user_code'] ?? $bodyData['user_code'] ?? '');

        if ($userId > 0) {
            $stmt = $db->prepare("SELECT * FROM `brethren_users` WHERE `id` = ? LIMIT 1");
            $stmt->bind_param('i', $userId);
        } else if (!empty($userCode)) {
            $stmt = $db->prepare("SELECT * FROM `brethren_users` WHERE `user_code` = ? OR `email` = ? OR `phone` = ? OR `id` = ? LIMIT 1");
            $stmt->bind_param('ssss', $userCode, $userCode, $userCode, $userCode);
        } else {
            sendJSONResponse(['status' => 'error', 'message' => 'User ID or User Code is required'], 400);
        }

        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if (!$user) {
            sendJSONResponse(['status' => 'error', 'message' => 'المستخدم غير موجود'], 404);
        }

        $user['custom_fields'] = json_decode($user['custom_fields'] ?? '{}', true) ?: (object)[];

        // Fetch Points History
        $stmtHist = $db->prepare("SELECT * FROM `brethren_points_history` WHERE `user_id` = ? ORDER BY `id` DESC");
        $stmtHist->bind_param('i', $user['id']);
        $stmtHist->execute();
        $histRes = $stmtHist->get_result();
        $history = [];
        while ($h = $histRes->fetch_assoc()) {
            $history[] = $h;
        }

        // Fetch Scanned/Attended Events
        $stmtAtt = $db->prepare("SELECT e.*, a.scanned_at 
            FROM `brethren_attendance` a 
            JOIN `brethren_events` e ON a.event_id = e.id 
            WHERE a.user_id = ? 
            ORDER BY a.scanned_at DESC");
        $stmtAtt->bind_param('i', $user['id']);
        $stmtAtt->execute();
        $attendedEvents = [];
        $attendedIds = [];
        $attRes = $stmtAtt->get_result();
        while ($att = $attRes->fetch_assoc()) {
            $attendedEvents[] = $att;
            $attendedIds[] = (int)$att['id'];
        }

        // Fetch All Available Events
        $resEvents = $db->query("SELECT * FROM `brethren_events` ORDER BY `event_date` DESC, `id` DESC");
        $allEvents = [];
        if ($resEvents) {
            while ($ev = $resEvents->fetch_assoc()) {
                $ev['is_attended'] = in_array((int)$ev['id'], $attendedIds, true);
                $allEvents[] = $ev;
            }
        }

        sendJSONResponse([
            'status' => 'success',
            'user' => $user,
            'history' => $history,
            'attended_events' => $attendedEvents,
            'all_events' => $allEvents
        ]);
        break;

    case 'save_user':
        $id = (int)($bodyData['id'] ?? $_POST['id'] ?? 0);
        $name = trim($bodyData['name'] ?? $_POST['name'] ?? '');
        $email = trim($bodyData['email'] ?? $_POST['email'] ?? '');
        $phone = trim($bodyData['phone'] ?? $_POST['phone'] ?? '');
        $location = trim($bodyData['location'] ?? $_POST['location'] ?? '');
        $gender = trim($bodyData['gender'] ?? $_POST['gender'] ?? 'ذكر');
        $birthDate = trim($bodyData['birth_date'] ?? $_POST['birth_date'] ?? '');
        $photo = trim($bodyData['photo'] ?? $_POST['photo'] ?? '');
        $passcode = trim($bodyData['passcode'] ?? $bodyData['password'] ?? $_POST['passcode'] ?? '');
        $isAdmin = (int)($bodyData['is_admin'] ?? $_POST['is_admin'] ?? 0);
        $customFields = $bodyData['custom_fields'] ?? $_POST['custom_fields'] ?? [];

        if (empty($name)) {
            sendJSONResponse(['status' => 'error', 'message' => 'اسم المستخدم مطلوب'], 400);
        }

        if (is_string($customFields)) {
            $customFields = json_decode($customFields, true) ?: [];
        }

        $customFieldsJson = json_encode($customFields, JSON_UNESCAPED_UNICODE);

        if ($id > 0) {
            if (!empty($passcode)) {
                $hashed = password_hash($passcode, PASSWORD_DEFAULT);
                $stmt = $db->prepare("UPDATE `brethren_users` SET 
                    `name` = ?, `email` = ?, `phone` = ?, `location` = ?, `gender` = ?, `birth_date` = ?, 
                    `photo` = IF(? != '', ?, `photo`), `passcode` = ?, 
                    `is_admin` = ?, `custom_fields` = ? 
                    WHERE `id` = ?");
                $stmt->bind_param('sssssssssisi', $name, $email, $phone, $location, $gender, $birthDate, $photo, $photo, $hashed, $isAdmin, $customFieldsJson, $id);
            } else {
                $stmt = $db->prepare("UPDATE `brethren_users` SET 
                    `name` = ?, `email` = ?, `phone` = ?, `location` = ?, `gender` = ?, `birth_date` = ?, 
                    `photo` = IF(? != '', ?, `photo`), 
                    `is_admin` = ?, `custom_fields` = ? 
                    WHERE `id` = ?");
                $stmt->bind_param('ssssssssisi', $name, $email, $phone, $location, $gender, $birthDate, $photo, $photo, $isAdmin, $customFieldsJson, $id);
            }
            $stmt->execute();
            $userId = $id;
        } else {
            $userCode = 'BR-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
            $hashed = !empty($passcode) ? password_hash($passcode, PASSWORD_DEFAULT) : '';
            $stmt = $db->prepare("INSERT INTO `brethren_users` 
                (`user_code`, `name`, `email`, `phone`, `location`, `gender`, `birth_date`, `photo`, `passcode`, `points`, `is_admin`, `custom_fields`) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, ?, ?)");
            $stmt->bind_param('sssssssssis', $userCode, $name, $email, $phone, $location, $gender, $birthDate, $photo, $hashed, $isAdmin, $customFieldsJson);
            $stmt->execute();
            $userId = $db->insert_id;
        }

        sendJSONResponse(['status' => 'success', 'message' => 'تم حفظ بيانات المستخدم بنجاح', 'user_id' => $userId]);
        break;

    case 'delete_user':
        $id = (int)($bodyData['id'] ?? $_POST['id'] ?? 0);
        if ($id <= 0) sendJSONResponse(['status' => 'error', 'message' => 'معرف المستخدم غير صحيح'], 400);

        $stmt = $db->prepare("DELETE FROM `brethren_users` WHERE `id` = ?");
        $stmt->bind_param('i', $id);
        $stmt->execute();

        $stmtAtt = $db->prepare("DELETE FROM `brethren_attendance` WHERE `user_id` = ?");
        $stmtAtt->bind_param('i', $id);
        $stmtAtt->execute();

        $stmtHist = $db->prepare("DELETE FROM `brethren_points_history` WHERE `user_id` = ?");
        $stmtHist->bind_param('i', $id);
        $stmtHist->execute();

        sendJSONResponse(['status' => 'success', 'message' => 'تم حذف المستخدم بنجاح']);
        break;

    case 'bulk_add_users':
        $usersList = $bodyData['users'] ?? [];
        if (empty($usersList) || !is_array($usersList)) {
            sendJSONResponse(['status' => 'error', 'message' => 'قائمة المستخدمين فارغة'], 400);
        }

        $insertedCount = 0;
        foreach ($usersList as $u) {
            $name = trim($u['name'] ?? '');
            if (empty($name)) continue;

            $email = trim($u['email'] ?? '');
            $phone = trim($u['phone'] ?? $u['number'] ?? '');
            $location = trim($u['location'] ?? '');
            $gender = trim($u['gender'] ?? 'ذكر');
            $birthDate = trim($u['birth_date'] ?? '');
            $isAdmin = (int)($u['is_admin'] ?? 0);
            $passcode = trim($u['passcode'] ?? $u['password'] ?? '');
            $hashedPass = !empty($passcode) ? password_hash($passcode, PASSWORD_DEFAULT) : '';

            $customFields = $u['custom_fields'] ?? [];
            if (!is_array($customFields)) $customFields = [];

            $userCode = 'BR-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
            $customJson = json_encode($customFields, JSON_UNESCAPED_UNICODE);

            $stmt = $db->prepare("INSERT INTO `brethren_users` 
                (`user_code`, `name`, `email`, `phone`, `location`, `gender`, `birth_date`, `photo`, `passcode`, `points`, `is_admin`, `custom_fields`) 
                VALUES (?, ?, ?, ?, ?, ?, ?, '', ?, 0, ?, ?)");
            $stmt->bind_param('sssssssssis', $userCode, $name, $email, $phone, $location, $gender, $birthDate, $hashedPass, $isAdmin, $customJson);
            if ($stmt->execute()) {
                $insertedCount++;
            }
        }

        sendJSONResponse([
            'status' => 'success', 
            'message' => "تم إضافة $insertedCount مستخدم بنجاح", 
            'inserted_count' => $insertedCount
        ]);
        break;

    // ─────────────────────────────────────────────────────────────
    // EVENTS & ATTENDANCE SCANNER ACTIONS
    // ─────────────────────────────────────────────────────────────
    case 'get_events':
        $res = $db->query("SELECT e.*, COUNT(a.id) AS attendance_count 
            FROM `brethren_events` e 
            LEFT JOIN `brethren_attendance` a ON e.id = a.event_id 
            GROUP BY e.id 
            ORDER BY e.event_date DESC, e.id DESC");
        $events = [];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $events[] = $row;
            }
        }
        sendJSONResponse(['status' => 'success', 'events' => $events]);
        break;

    case 'create_event':
        $eventName = trim($bodyData['event_name'] ?? $_POST['event_name'] ?? '');
        $eventDate = trim($bodyData['event_date'] ?? $_POST['event_date'] ?? date('Y-m-d'));
        $description = trim($bodyData['description'] ?? $_POST['description'] ?? '');

        if (empty($eventName)) {
            sendJSONResponse(['status' => 'error', 'message' => 'اسم الفعالية مطلوب'], 400);
        }

        $stmt = $db->prepare("INSERT INTO `brethren_events` (`event_name`, `event_date`, `description`) VALUES (?, ?, ?)");
        $stmt->bind_param('sss', $eventName, $eventDate, $description);
        $stmt->execute();

        sendJSONResponse([
            'status' => 'success',
            'message' => 'تم إنشاء الفعالية بنجاح',
            'event_id' => $db->insert_id
        ]);
        break;

    case 'scan_attendance':
        $eventId = (int)($bodyData['event_id'] ?? $_POST['event_id'] ?? 0);
        $userCode = trim($bodyData['user_code'] ?? $_POST['user_code'] ?? '');

        if ($eventId <= 0 || empty($userCode)) {
            sendJSONResponse(['status' => 'error', 'message' => 'بيانات كود المستخدم أو الفعالية غير مكتملة'], 400);
        }

        $stmtEv = $db->prepare("SELECT * FROM `brethren_events` WHERE `id` = ? LIMIT 1");
        $stmtEv->bind_param('i', $eventId);
        $stmtEv->execute();
        $event = $stmtEv->get_result()->fetch_assoc();
        if (!$event) {
            sendJSONResponse(['status' => 'error', 'message' => 'الفعالية غير موجودة'], 404);
        }

        $stmtUser = $db->prepare("SELECT * FROM `brethren_users` WHERE `user_code` = ? OR `email` = ? OR `phone` = ? OR `id` = ? LIMIT 1");
        $stmtUser->bind_param('ssss', $userCode, $userCode, $userCode, $userCode);
        $stmtUser->execute();
        $user = $stmtUser->get_result()->fetch_assoc();
        if (!$user) {
            sendJSONResponse(['status' => 'error', 'message' => 'رمز QR غير صالح أو مستخدم غير مسجل'], 404);
        }

        $userId = (int)$user['id'];

        $stmtCheck = $db->prepare("SELECT * FROM `brethren_attendance` WHERE `event_id` = ? AND `user_id` = ? LIMIT 1");
        $stmtCheck->bind_param('ii', $eventId, $userId);
        $stmtCheck->execute();
        $already = $stmtCheck->get_result()->fetch_assoc();

        if ($already) {
            sendJSONResponse([
                'status' => 'already_attended',
                'message' => 'تم تسجيل حضور ' . $user['name'] . ' في هذه الفعالية سابقاً',
                'user' => $user
            ]);
        }

        $stmtAdd = $db->prepare("INSERT INTO `brethren_attendance` (`event_id`, `user_id`) VALUES (?, ?)");
        $stmtAdd->bind_param('ii', $eventId, $userId);
        $stmtAdd->execute();

        $pointsChange = 20;
        $stmtPts = $db->prepare("UPDATE `brethren_users` SET `points` = `points` + ? WHERE `id` = ?");
        $stmtPts->bind_param('ii', $pointsChange, $userId);
        $stmtPts->execute();

        $reason = "حضور فعالية: " . $event['event_name'];
        $type = "event_attendance";
        $stmtHist = $db->prepare("INSERT INTO `brethren_points_history` (`user_id`, `points_change`, `reason`, `type`, `event_id`) VALUES (?, ?, ?, ?, ?)");
        $stmtHist->bind_param('iissi', $userId, $pointsChange, $reason, $type, $eventId);
        $stmtHist->execute();

        $stmtUpdated = $db->prepare("SELECT `points` FROM `brethren_users` WHERE `id` = ? LIMIT 1");
        $stmtUpdated->bind_param('i', $userId);
        $stmtUpdated->execute();
        $updatedRow = $stmtUpdated->get_result()->fetch_assoc();
        $user['points'] = (int)$updatedRow['points'];

        if (!empty($user['email'])) {
            $subject = "تسجيل حضور: " . $event['event_name'];
            $bodyHtml = "<h3>مرحباً {$user['name']}</h3>" .
                        "<p>تم تسجيل حضورك بنجاح في فعالية <strong>{$event['event_name']}</strong> بتاريخ {$event['event_date']}.</p>" .
                        "<p>تم إضافة <strong>+20 نقطة</strong> لحسابك! مجموع نقاطك الآن: <strong>{$user['points']}</strong></p>";
            sendGoogleScriptEmail($user['email'], $subject, $bodyHtml, $db);
        }

        sendJSONResponse([
            'status' => 'success',
            'message' => 'تم تسجيل الحضور بنجاح وأضيفت 20 نقطة!',
            'user' => $user,
            'event' => $event,
            'points_added' => 20
        ]);
        break;

    // ─────────────────────────────────────────────────────────────
    // POINTS & SETTINGS ACTIONS
    // ─────────────────────────────────────────────────────────────
    case 'update_points':
        $userId = (int)($bodyData['user_id'] ?? $_POST['user_id'] ?? 0);
        $pointsChange = (int)($bodyData['points_change'] ?? $_POST['points_change'] ?? 0);
        $reason = trim($bodyData['reason'] ?? $_POST['reason'] ?? 'تحديث نقاط');
        $type = trim($bodyData['type'] ?? $_POST['type'] ?? 'manual');

        if ($userId <= 0 || $pointsChange === 0) {
            sendJSONResponse(['status' => 'error', 'message' => 'معرف المستخدم وقيمة النقاط مطلوبين'], 400);
        }

        $stmtUser = $db->prepare("SELECT `name`, `email`, `points` FROM `brethren_users` WHERE `id` = ? LIMIT 1");
        $stmtUser->bind_param('i', $userId);
        $stmtUser->execute();
        $user = $stmtUser->get_result()->fetch_assoc();
        if (!$user) {
            sendJSONResponse(['status' => 'error', 'message' => 'المستخدم غير موجود'], 404);
        }

        $stmtUpd = $db->prepare("UPDATE `brethren_users` SET `points` = `points` + ? WHERE `id` = ?");
        $stmtUpd->bind_param('ii', $pointsChange, $userId);
        $stmtUpd->execute();

        $stmtHist = $db->prepare("INSERT INTO `brethren_points_history` (`user_id`, `points_change`, `reason`, `type`) VALUES (?, ?, ?, ?)");
        $stmtHist->bind_param('iiss', $userId, $pointsChange, $reason, $type);
        $stmtHist->execute();

        $newPoints = $user['points'] + $pointsChange;

        if (!empty($user['email'])) {
            $changeText = ($pointsChange > 0 ? "+$pointsChange" : "$pointsChange");
            $subject = "تحديث النقاط: $changeText نقطة";
            $bodyHtml = "<h3>مرحباً {$user['name']}</h3>" .
                        "<p>تم تحديث رصيد نقاطك: <strong>{$changeText}</strong> نقطة بسبب: <strong>{$reason}</strong>.</p>" .
                        "<p>رصيد النقاط الجديد: <strong>{$newPoints}</strong> نقطة.</p>";
            sendGoogleScriptEmail($user['email'], $subject, $bodyHtml, $db);
        }

        sendJSONResponse([
            'status' => 'success',
            'message' => 'تم تحديث نقاط ' . $user['name'] . ' بنجاح',
            'new_points' => $newPoints
        ]);
        break;

    case 'get_settings':
        $res = $db->query("SELECT * FROM `brethren_settings`");
        $settings = [
            'shortcuts' => [10, 30, 50, 100],
            'enable_shortcut' => true,
            'enable_custom' => true,
            'reasons' => ['ألعاب', 'بونص', 'التزام بالأوقات'],
            'admin_passcode' => 'admin123',
            'admin_email' => 'admin@sunday-school.online',
            'google_script_url' => ''
        ];
        if ($res) {
            while ($row = $res->fetch_assoc()) {
                $val = json_decode($row['setting_value'], true);
                $settings[$row['setting_key']] = ($val !== null) ? $val : $row['setting_value'];
            }
        }
        sendJSONResponse(['status' => 'success', 'settings' => $settings]);
        break;

    case 'save_settings':
        $shortcuts = $bodyData['shortcuts'] ?? [10, 30, 50, 100];
        $enableShortcut = (bool)($bodyData['enable_shortcut'] ?? true);
        $enableCustom = (bool)($bodyData['enable_custom'] ?? true);
        $reasons = $bodyData['reasons'] ?? ['ألعاب', 'بونص', 'التزام بالأوقات'];
        $adminPasscode = trim($bodyData['admin_passcode'] ?? 'admin123');
        $adminEmail = trim($bodyData['admin_email'] ?? 'admin@sunday-school.online');
        $googleScriptUrl = trim($bodyData['google_script_url'] ?? '');

        $settingsMap = [
            'shortcuts' => $shortcuts,
            'enable_shortcut' => $enableShortcut,
            'enable_custom' => $enableCustom,
            'reasons' => $reasons,
            'admin_passcode' => $adminPasscode,
            'admin_email' => $adminEmail,
            'google_script_url' => $googleScriptUrl
        ];

        foreach ($settingsMap as $k => $v) {
            $valJson = json_encode($v, JSON_UNESCAPED_UNICODE);
            $stmt = $db->prepare("INSERT INTO `brethren_settings` (`setting_key`, `setting_value`) VALUES (?, ?) ON DUPLICATE KEY UPDATE `setting_value` = ?");
            $stmt->bind_param('sss', $k, $valJson, $valJson);
            $stmt->execute();
        }

        sendJSONResponse(['status' => 'success', 'message' => 'تم حفظ الإعدادات بنجاح']);
        break;

    default:
        sendJSONResponse(['status' => 'error', 'message' => 'إجراء غير معروف (Invalid Action)'], 400);
        break;
}
