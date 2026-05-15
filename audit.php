<?php
// =============================================================
//  AUDIT LOGGING SYSTEM
//  Include this file in api.php  →  require_once 'audit.php';
//  Place it next to api.php / config.php
// =============================================================

// ── Core writer ──────────────────────────────────────────────
/**
 * Write one audit record.
 *
 * @param string      $action      e.g. 'student_add', 'student_edit', 'student_delete'
 * @param string      $entity      e.g. 'student', 'uncle', 'attendance', 'coupon', 'trip'
 * @param int|null    $entityId    Primary key of the affected row
 * @param string|null $entityName  Human-readable label (student name, trip title …)
 * @param array|null  $oldData     Snapshot BEFORE the change (null for inserts)
 * @param array|null  $newData     Snapshot AFTER  the change (null for deletes)
 * @param string|null $notes       Any extra context you want to store
 */
function writeAuditLog($action, $entity, $entity_id = null, $entity_name = '', $old_data = null, $new_data = null, $notes = '') {
    try {
        $conn = getDBConnection();
        
        $church_id = getChurchId();
        $uncle_id = $_SESSION['uncle_id'] ?? null;
        $uncle_name = $_SESSION['uncle_name'] ?? $_SESSION['username'] ?? 'system';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        // Convert arrays to JSON
        $old_data_json = is_array($old_data) ? json_encode($old_data, JSON_UNESCAPED_UNICODE) : $old_data;
        $new_data_json = is_array($new_data) ? json_encode($new_data, JSON_UNESCAPED_UNICODE) : $new_data;
        
        $stmt = $conn->prepare("
            INSERT INTO audit_logs 
            (church_id, uncle_id, uncle_name, action, entity, entity_id, entity_name, 
             old_data, new_data, ip_address, user_agent, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->bind_param(
            "iisssissssss",
            $church_id,
            $uncle_id,
            $uncle_name,
            $action,
            $entity,
            $entity_id,
            $entity_name,
            $old_data_json,
            $new_data_json,
            $ip_address,
            $user_agent,
            $notes
        );
        
        return $stmt->execute();
        
    } catch (Exception $e) {
        error_log("writeAuditLog error: " . $e->getMessage());
        return false;
    }
}

// ── Helper: fetch a student snapshot (safe, no sensitive hash) ─
function getStudentSnapshot($studentId) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM students WHERE id = ?");
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// ── Helper: fetch an uncle snapshot ──────────────────────────
function getUncleSnapshot($uncleId) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM uncles WHERE id = ?");
    $stmt->bind_param("i", $uncleId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// ── Helper: fetch a trip snapshot ────────────────────────────
function getTripSnapshot($tripId) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM trips WHERE id = ?");
    $stmt->bind_param("i", $tripId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// ── Helper: fetch an announcement snapshot ───────────────────
function getAnnouncementSnapshot($announcementId) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM announcements WHERE id = ?");
    $stmt->bind_param("i", $announcementId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}
function getClassSnapshot($classId) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT * FROM church_classes WHERE id = ?");
    $stmt->bind_param("i", $classId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}
// ── Helper: fetch an attendance snapshot ─────────────────────
function getAttendanceSnapshot($attendanceId) {
    $conn = getDBConnection();
    $stmt = $conn->prepare("
        SELECT a.*, s.name as student_name 
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        WHERE a.id = ?
    ");
    $stmt->bind_param("i", $attendanceId);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc();
}

// =============================================================
//  READ / QUERY FUNCTIONS (for the admin view page)
// =============================================================

/**
 * getAuditLogs() — paginated, filterable list
 * Used by the new 'getAuditLogs' API action below.
 */
function getAuditLogs() {
    checkAuth();
    
    try {
        $churchId = getChurchId();
        $limit = intval($_POST['limit'] ?? $_GET['limit'] ?? 100);
        $offset = intval($_POST['offset'] ?? $_GET['offset'] ?? 0);
        
        $conn = getDBConnection();
        
        $isAll = (!empty($_POST['all_churches']) && $_POST['all_churches'] === '1') || 
                 (isset($_SESSION['uncle_role']) && $_SESSION['uncle_role'] === 'developer');
        
        $sql = "SELECT 
                    al.*,
                    DATE_FORMAT(al.created_at, '%Y-%m-%d %H:%i:%s') as created_at_formatted
                FROM audit_logs al
                WHERE 1=1";
        
        $params = [];
        $types = "";
        
        if (!$isAll) {
            $sql .= " AND al.church_id = ?";
            $params[] = $churchId;
            $types .= "i";
        }
        
        $sql .= " ORDER BY al.created_at DESC LIMIT ? OFFSET ?";
        $params[] = $limit;
        $params[] = $offset;
        $types .= "ii";
        
        $stmt = $conn->prepare($sql);
        
        if (!empty($params)) {
            $stmt->bind_param($types, ...$params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $logs = [];
        while ($row = $result->fetch_assoc()) {
            // Parse JSON data
            $row['old_data'] = !empty($row['old_data']) ? json_decode($row['old_data'], true) : null;
            $row['new_data'] = !empty($row['new_data']) ? json_decode($row['new_data'], true) : null;
            
            // Format created_at
            $row['created_at'] = $row['created_at_formatted'];
            unset($row['created_at_formatted']);
            
            $logs[] = $row;
        }
        
        // Get total count
        $countSql = "SELECT COUNT(*) as total FROM audit_logs al WHERE 1=1";
        if (!$isAll) {
            $countSql .= " AND al.church_id = ?";
        }
        
        $countStmt = $conn->prepare($countSql);
        if (!$isAll) {
            $countStmt->bind_param("i", $churchId);
        }
        $countStmt->execute();
        $totalCount = $countStmt->get_result()->fetch_assoc()['total'];
        
        sendJSON([
            'success' => true,
            'logs' => $logs,
            'total' => $totalCount,
            'limit' => $limit,
            'offset' => $offset,
            'has_more' => ($offset + $limit) < $totalCount
        ]);
        
    } catch (Exception $e) {
        error_log("getAuditLogs error: " . $e->getMessage());
        sendJSON(['success' => false, 'message' => 'خطأ في تحميل سجل العمليات: ' . $e->getMessage()]);
    }
}

/**
 * getEntityAuditHistory() — timeline for a single record
 * e.g. "show me all changes ever made to student #42"
 */
function getEntityAuditHistory(): void {
    checkAuth();
    try {
        $churchId = getChurchId();
        $entity   = sanitize($_POST['entity']    ?? '');
        $entityId = intval($_POST['entity_id']   ?? 0);

        if (empty($entity) || $entityId === 0) {
            sendJSON(['success' => false, 'message' => 'بيانات غير كاملة']);
            return;
        }

        $conn = getDBConnection();
        $sql = "
            SELECT al.*, u.image_url as uncle_image
            FROM audit_logs al
            LEFT JOIN uncles u ON al.uncle_id = u.id
            WHERE al.church_id = ? AND al.entity_id = ?
        ";
        
        // If entity is 'student', we want profile edits, coupons, and attendance
        if ($entity === 'student' || $entity === 'coupon') {
            $sql .= " AND al.entity IN ('student', 'coupon', 'attendance')";
        } else {
            $sql .= " AND al.entity = ?";
        }
        
        $sql .= " ORDER BY al.created_at DESC LIMIT 200";
        
        $stmt = $conn->prepare($sql);
        if ($entity === 'student' || $entity === 'coupon') {
            $stmt->bind_param("ii", $churchId, $entityId);
        } else {
            $stmt->bind_param("iis", $churchId, $entityId, $entity);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();

        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $row['old_data'] = $row['old_data'] ? json_decode($row['old_data'], true) : null;
            $row['new_data'] = $row['new_data'] ? json_decode($row['new_data'], true) : null;
            $row['created_at_formatted'] = date('d/m/Y H:i:s', strtotime($row['created_at']));
            $logs[] = $row;
        }

        sendJSON(['success' => true, 'logs' => $logs, 'count' => count($logs)]);

    } catch (Exception $e) {
        error_log("getEntityAuditHistory error: " . $e->getMessage());
        sendJSON(['success' => false, 'message' => 'خطأ في جلب السجل']);
    }
}

// =============================================================
//  WRAPPERS  — drop these into api.php to replace the originals
//  or call them right BEFORE / AFTER your existing DB calls
// =============================================================

// ── STUDENTS ─────────────────────────────────────────────────

/**
 * Call this right after a successful INSERT in addStudent().
 */
function auditStudentAdd(int $studentId, string $studentName, array $data): void {
    // Strip any sensitive fields
    unset($data['password_hash']);
    writeAuditLog('student_add', 'student', $studentId, $studentName, null, $data,
        "إضافة طفل جديد: $studentName");
}

/**
 * Call this BEFORE updating a student (pass old row) and AFTER (pass new row).
 */
function auditStudentEdit(int $studentId, array $oldRow, array $newRow): void {
    unset($oldRow['password_hash'], $newRow['password_hash']);
    writeAuditLog('student_edit', 'student', $studentId, $oldRow['name'] ?? $newRow['name'] ?? '',
        $oldRow, $newRow, "تعديل بيانات الطفل");
}

/**
 * Call this BEFORE deleting a student (pass the full row).
 */
function auditStudentDelete(int $studentId, array $oldRow): void {
    unset($oldRow['password_hash']);
    writeAuditLog('student_delete', 'student', $studentId, $oldRow['name'] ?? '',
        $oldRow, null, "حذف الطفل: " . ($oldRow['name'] ?? ''));
}

// ── ATTENDANCE ───────────────────────────────────────────────

function auditAttendanceSave(int $studentId, string $studentName, string $date, string $oldStatus, string $newStatus, bool $isNew): void {
    $action = $isNew ? 'attendance_add' : 'attendance_edit';
    $old = $isNew ? null : ['student_id' => $studentId, 'date' => $date, 'status' => $oldStatus];
    $new = ['student_id' => $studentId, 'date' => $date, 'status' => $newStatus];
    writeAuditLog($action, 'attendance', $studentId, $studentName, $old, $new,
        "$studentName — $date — $newStatus");
}

function auditAttendanceDelete(int $attendanceId, array $row): void {
    writeAuditLog('attendance_delete', 'attendance', $attendanceId, $row['student_name'] ?? '',
        $row, null, "حذف سجل حضور: " . ($row['student_name'] ?? '') . " — " . ($row['attendance_date'] ?? ''));
}

// ── COUPONS ──────────────────────────────────────────────────

function auditCouponChange(int $studentId, string $studentName, int $oldTotal, int $newTotal, string $reason = ''): void {
    $change = $newTotal - $oldTotal;
    $sign   = $change >= 0 ? "+$change" : "$change";
    writeAuditLog('coupon_edit', 'coupon', $studentId, $studentName,
        ['coupons' => $oldTotal],
        ['coupons' => $newTotal],
        "كوبونات $studentName: $oldTotal → $newTotal ($sign)" . ($reason ? " | $reason" : ''));
}

// ── UNCLES ───────────────────────────────────────────────────

function auditUncleAdd(int $uncleId, string $uncleName, array $data): void {
    unset($data['password_hash']);
    writeAuditLog('uncle_add', 'uncle', $uncleId, $uncleName, null, $data,
        "إضافة مستخدم جديد: $uncleName");
}

function auditUncleEdit(int $uncleId, array $oldRow, array $newRow): void {
    unset($oldRow['password_hash'], $newRow['password_hash']);
    writeAuditLog('uncle_edit', 'uncle', $uncleId, $oldRow['name'] ?? '',
        $oldRow, $newRow, "تعديل بيانات المستخدم: " . ($oldRow['name'] ?? ''));
}

function auditUncleDelete(int $uncleId, array $oldRow): void {
    unset($oldRow['password_hash']);
    writeAuditLog('uncle_delete', 'uncle', $uncleId, $oldRow['name'] ?? '',
        $oldRow, null, "حذف المستخدم: " . ($oldRow['name'] ?? ''));
}

function auditUnclePasswordChange(int $uncleId, string $uncleName): void {
    writeAuditLog('uncle_password_change', 'uncle', $uncleId, $uncleName, null, null,
        "تغيير كلمة مرور: $uncleName");
}

// ── TRIPS ────────────────────────────────────────────────────

function auditTripAdd(int $tripId, string $tripTitle, array $data): void {
    writeAuditLog('trip_add', 'trip', $tripId, $tripTitle, null, $data,
        "إضافة رحلة: $tripTitle");
}

function auditTripEdit(int $tripId, array $oldRow, array $newRow): void {
    writeAuditLog('trip_edit', 'trip', $tripId, $oldRow['title'] ?? '',
        $oldRow, $newRow, "تعديل رحلة: " . ($oldRow['title'] ?? ''));
}

function auditTripDelete(int $tripId, array $oldRow): void {
    writeAuditLog('trip_delete', 'trip', $tripId, $oldRow['title'] ?? '',
        $oldRow, null, "حذف رحلة: " . ($oldRow['title'] ?? ''));
}

function auditTripRegistration(int $tripId, string $tripTitle, int $studentId, string $studentName, string $action = 'register'): void {
    $label = $action === 'cancel' ? 'إلغاء تسجيل' : 'تسجيل';
    writeAuditLog("trip_$action", 'trip_registration', $tripId, $tripTitle,
        null, ['student_id' => $studentId, 'student_name' => $studentName],
        "$label الطفل $studentName في رحلة $tripTitle");
}

// ── ANNOUNCEMENTS ────────────────────────────────────────────

function auditAnnouncementAdd(int $id, string $text): void {
    writeAuditLog('announcement_add', 'announcement', $id, mb_substr($text, 0, 60),
        null, null, "إضافة إعلان");
}

function auditAnnouncementDelete(int $id, array $oldRow): void {
    writeAuditLog('announcement_delete', 'announcement', $id, mb_substr($oldRow['text'] ?? '', 0, 60),
        $oldRow, null, "حذف إعلان");
}

function auditAnnouncementToggle(int $id, bool $newState): void {
    writeAuditLog('announcement_toggle', 'announcement', $id, null,
        null, ['is_active' => (int)$newState],
        "تغيير حالة الإعلان: " . ($newState ? 'مفعّل' : 'معطّل'));
}

// ── REGISTRATIONS ────────────────────────────────────────────

function auditRegistrationDecision(int $regId, string $studentName, string $decision, string $note = ''): void {
    $label = $decision === 'approved' ? 'قبول' : 'رفض';
    writeAuditLog("registration_$decision", 'registration', $regId, $studentName,
        null, ['status' => $decision, 'note' => $note],
        "$label طلب التسجيل: $studentName" . ($note ? " | $note" : ''));
}

// ── CHURCH / PASSWORD ────────────────────────────────────────

function auditChurchPasswordChange(int $churchId, string $churchName): void {
    writeAuditLog('church_password_change', 'church', $churchId, $churchName, null, null,
        "تغيير كلمة مرور الكنيسة: $churchName");
}

function auditLogin(string $type, int $id, string $name): void {
    writeAuditLog("login_$type", $type, $id, $name, null, null, "تسجيل دخول: $name");
}