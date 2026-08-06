<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Brethren Database Connection Config
define('BRETHREN_DB_HOST', 'sql206.infinityfree.com');
define('BRETHREN_DB_PORT', 3306);
define('BRETHREN_DB_USER', 'if0_40860329');
define('BRETHREN_DB_PASS', 'wqSwU86i8GvLDAw');
define('BRETHREN_DB_NAME', 'if0_40860329_brethren');

function getBrethrenDB(): mysqli {
    static $conn = null;
    if ($conn !== null && $conn->ping()) {
        return $conn;
    }

    // Try remote database connection first
    mysqli_report(MYSQLI_REPORT_OFF);
    $conn = @new mysqli(BRETHREN_DB_HOST, BRETHREN_DB_USER, BRETHREN_DB_PASS, BRETHREN_DB_NAME, BRETHREN_DB_PORT);

    // Local / Offline Fallback if remote DB is unreachable (e.g. during local dev)
    if ($conn->connect_error) {
        $conn = @new mysqli('127.0.0.1', 'root', '', '');
        if (!$conn->connect_error) {
            $conn->query("CREATE DATABASE IF NOT EXISTS `if0_40860329_brethren` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $conn->select_db('if0_40860329_brethren');
        } else {
            sendJSONResponse(['status' => 'error', 'message' => 'Database Connection Failed: ' . $conn->connect_error], 500);
        }
    }

    $conn->set_charset('utf8mb4');
    ensureTablesExist($conn);
    return $conn;
}

function ensureTablesExist(mysqli $conn) {
    // 1. Users Table
    $conn->query("CREATE TABLE IF NOT EXISTS `brethren_users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_code` VARCHAR(50) UNIQUE NOT NULL,
        `name` VARCHAR(150) NOT NULL,
        `phone` VARCHAR(30) DEFAULT NULL,
        `location` VARCHAR(150) DEFAULT NULL,
        `gender` VARCHAR(20) DEFAULT NULL,
        `birth_date` VARCHAR(50) DEFAULT NULL,
        `photo` LONGTEXT DEFAULT NULL,
        `points` INT DEFAULT 0,
        `custom_fields` LONGTEXT DEFAULT NULL,
        `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;");

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

    // Initialize default settings if missing
    $checkSet = $conn->query("SELECT COUNT(*) AS cnt FROM `brethren_settings`");
    $row = $checkSet ? $checkSet->fetch_assoc() : null;
    if (!$row || (int)$row['cnt'] === 0) {
        $defaultSettings = [
            'shortcuts' => [10, 30, 50, 100],
            'enable_shortcut' => true,
            'enable_custom' => true,
            'reasons' => ['ألعاب', 'بونص', 'التزام بالأوقات']
        ];
        foreach ($defaultSettings as $k => $v) {
            $valJson = json_encode($v, JSON_UNESCAPED_UNICODE);
            $stmt = $conn->prepare("INSERT IGNORE INTO `brethren_settings` (`setting_key`, `setting_value`) VALUES (?, ?)");
            $stmt->bind_param('ss', $k, $valJson);
            $stmt->execute();
        }
    }
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
    // USERS ACTIONS
    // ─────────────────────────────────────────────────────────────
    case 'get_users':
        $res = $db->query("SELECT * FROM `brethren_users` ORDER BY `name` ASC");
        $users = [];
        while ($row = $res->fetch_assoc()) {
            $row['custom_fields'] = json_decode($row['custom_fields'] ?? '{}', true) ?: (object)[];
            $users[] = $row;
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
            $stmt = $db->prepare("SELECT * FROM `brethren_users` WHERE `user_code` = ? OR `id` = ? LIMIT 1");
            $stmt->bind_param('si', $userCode, $userCode);
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
        while ($ev = $resEvents->fetch_assoc()) {
            $ev['is_attended'] = in_array((int)$ev['id'], $attendedIds, true);
            $allEvents[] = $ev;
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
        $phone = trim($bodyData['phone'] ?? $_POST['phone'] ?? '');
        $location = trim($bodyData['location'] ?? $_POST['location'] ?? '');
        $gender = trim($bodyData['gender'] ?? $_POST['gender'] ?? '');
        $birthDate = trim($bodyData['birth_date'] ?? $_POST['birth_date'] ?? '');
        $photo = trim($bodyData['photo'] ?? $_POST['photo'] ?? '');
        $customFields = $bodyData['custom_fields'] ?? $_POST['custom_fields'] ?? [];

        if (empty($name)) {
            sendJSONResponse(['status' => 'error', 'message' => 'اسم المستخدم مطلوب'], 400);
        }

        if (is_string($customFields)) {
            $customFields = json_decode($customFields, true) ?: [];
        }

        $customFieldsJson = json_encode($customFields, JSON_UNESCAPED_UNICODE);

        if ($id > 0) {
            // Update User
            $stmt = $db->prepare("UPDATE `brethren_users` SET 
                `name` = ?, `phone` = ?, `location` = ?, `gender` = ?, `birth_date` = ?, 
                `photo` = IF(? != '', ?, `photo`), `custom_fields` = ? 
                WHERE `id` = ?");
            $stmt->bind_param('sssssssi', $name, $phone, $location, $gender, $birthDate, $photo, $photo, $customFieldsJson, $id);
            $stmt->execute();
            $userId = $id;
        } else {
            // Insert User
            $userCode = 'BR-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
            $stmt = $db->prepare("INSERT INTO `brethren_users` 
                (`user_code`, `name`, `phone`, `location`, `gender`, `birth_date`, `photo`, `points`, `custom_fields`) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?)");
            $stmt->bind_param('ssssssss', $userCode, $name, $phone, $location, $gender, $birthDate, $photo, $customFieldsJson);
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

            $phone = trim($u['phone'] ?? $u['number'] ?? '');
            $location = trim($u['location'] ?? '');
            $gender = trim($u['gender'] ?? '');
            $birthDate = trim($u['birth_date'] ?? '');
            $customFields = $u['custom_fields'] ?? [];
            if (!is_array($customFields)) $customFields = [];

            $userCode = 'BR-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
            $customJson = json_encode($customFields, JSON_UNESCAPED_UNICODE);

            $stmt = $db->prepare("INSERT INTO `brethren_users` 
                (`user_code`, `name`, `phone`, `location`, `gender`, `birth_date`, `photo`, `points`, `custom_fields`) 
                VALUES (?, ?, ?, ?, ?, ?, '', 0, ?)");
            $stmt->bind_param('sssssss', $userCode, $name, $phone, $location, $gender, $birthDate, $customJson);
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
    // EVENTS & ATTENDANCE QR SCANNER ACTIONS
    // ─────────────────────────────────────────────────────────────
    case 'get_events':
        $res = $db->query("SELECT e.*, COUNT(a.id) AS attendance_count 
            FROM `brethren_events` e 
            LEFT JOIN `brethren_attendance` a ON e.id = a.event_id 
            GROUP BY e.id 
            ORDER BY e.event_date DESC, e.id DESC");
        $events = [];
        while ($row = $res->fetch_assoc()) {
            $events[] = $row;
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

    case 'delete_event':
        $eventId = (int)($bodyData['id'] ?? $_POST['id'] ?? 0);
        if ($eventId <= 0) sendJSONResponse(['status' => 'error', 'message' => 'معرف الفعالية غير صحيح'], 400);

        $stmt = $db->prepare("DELETE FROM `brethren_events` WHERE `id` = ?");
        $stmt->bind_param('i', $eventId);
        $stmt->execute();

        $stmtAtt = $db->prepare("DELETE FROM `brethren_attendance` WHERE `event_id` = ?");
        $stmtAtt->bind_param('i', $eventId);
        $stmtAtt->execute();

        sendJSONResponse(['status' => 'success', 'message' => 'تم حذف الفعالية بنجاح']);
        break;

    case 'scan_attendance':
        $eventId = (int)($bodyData['event_id'] ?? $_POST['event_id'] ?? 0);
        $userCode = trim($bodyData['user_code'] ?? $_POST['user_code'] ?? '');

        if ($eventId <= 0 || empty($userCode)) {
            sendJSONResponse(['status' => 'error', 'message' => 'بيانات كود المستخدم أو الفعالية غير مكتملة'], 400);
        }

        // Check Event
        $stmtEv = $db->prepare("SELECT * FROM `brethren_events` WHERE `id` = ? LIMIT 1");
        $stmtEv->bind_param('i', $eventId);
        $stmtEv->execute();
        $event = $stmtEv->get_result()->fetch_assoc();
        if (!$event) {
            sendJSONResponse(['status' => 'error', 'message' => 'الفعالية غير موجودة'], 404);
        }

        // Find User by user_code or id
        $stmtUser = $db->prepare("SELECT * FROM `brethren_users` WHERE `user_code` = ? OR `id` = ? LIMIT 1");
        $stmtUser->bind_param('si', $userCode, $userCode);
        $stmtUser->execute();
        $user = $stmtUser->get_result()->fetch_assoc();
        if (!$user) {
            sendJSONResponse(['status' => 'error', 'message' => 'رمز QR غير صالح أو مستخدم غير مسجل'], 404);
        }

        $userId = (int)$user['id'];

        // Check Duplicate Attendance
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

        // Record Attendance
        $stmtAdd = $db->prepare("INSERT INTO `brethren_attendance` (`event_id`, `user_id`) VALUES (?, ?)");
        $stmtAdd->bind_param('ii', $eventId, $userId);
        $stmtAdd->execute();

        // Automatically Add +20 Points for Event Attendance
        $pointsChange = 20;
        $stmtPts = $db->prepare("UPDATE `brethren_users` SET `points` = `points` + ? WHERE `id` = ?");
        $stmtPts->bind_param('ii', $pointsChange, $userId);
        $stmtPts->execute();

        // Record Points History
        $reason = "حضور فعالية: " . $event['event_name'];
        $type = "event_attendance";
        $stmtHist = $db->prepare("INSERT INTO `brethren_points_history` (`user_id`, `points_change`, `reason`, `type`, `event_id`) VALUES (?, ?, ?, ?, ?)");
        $stmtHist->bind_param('iissi', $userId, $pointsChange, $reason, $type, $eventId);
        $stmtHist->execute();

        // Fetch Updated Points
        $stmtUpdated = $db->prepare("SELECT `points` FROM `brethren_users` WHERE `id` = ? LIMIT 1");
        $stmtUpdated->bind_param('i', $userId);
        $stmtUpdated->execute();
        $updatedRow = $stmtUpdated->get_result()->fetch_assoc();
        $user['points'] = (int)$updatedRow['points'];

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

        // Verify User
        $stmtUser = $db->prepare("SELECT `name`, `points` FROM `brethren_users` WHERE `id` = ? LIMIT 1");
        $stmtUser->bind_param('i', $userId);
        $stmtUser->execute();
        $user = $stmtUser->get_result()->fetch_assoc();
        if (!$user) {
            sendJSONResponse(['status' => 'error', 'message' => 'المستخدم غير موجود'], 404);
        }

        // Update User Points
        $stmtUpd = $db->prepare("UPDATE `brethren_users` SET `points` = `points` + ? WHERE `id` = ?");
        $stmtUpd->bind_param('ii', $pointsChange, $userId);
        $stmtUpd->execute();

        // Add Points History Entry
        $stmtHist = $db->prepare("INSERT INTO `brethren_points_history` (`user_id`, `points_change`, `reason`, `type`) VALUES (?, ?, ?, ?)");
        $stmtHist->bind_param('iiss', $userId, $pointsChange, $reason, $type);
        $stmtHist->execute();

        $newPoints = $user['points'] + $pointsChange;
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
            'reasons' => ['ألعاب', 'بونص', 'التزام بالأوقات']
        ];
        while ($row = $res->fetch_assoc()) {
            $val = json_decode($row['setting_value'], true);
            $settings[$row['setting_key']] = ($val !== null) ? $val : $row['setting_value'];
        }
        sendJSONResponse(['status' => 'success', 'settings' => $settings]);
        break;

    case 'save_settings':
        $shortcuts = $bodyData['shortcuts'] ?? [10, 30, 50, 100];
        $enableShortcut = (bool)($bodyData['enable_shortcut'] ?? true);
        $enableCustom = (bool)($bodyData['enable_custom'] ?? true);
        $reasons = $bodyData['reasons'] ?? ['ألعاب', 'بونص', 'التزام بالأوقات'];

        $settingsMap = [
            'shortcuts' => $shortcuts,
            'enable_shortcut' => $enableShortcut,
            'enable_custom' => $enableCustom,
            'reasons' => $reasons
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
