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
function ensureAuditLogsTable($conn) {
    $conn->query("
        CREATE TABLE IF NOT EXISTS `audit_logs` (
          `id` INT AUTO_INCREMENT PRIMARY KEY,
          `church_id` INT NOT NULL,
          `uncle_id` INT DEFAULT NULL,
          `uncle_name` VARCHAR(100) DEFAULT NULL,
          `action` VARCHAR(50) NOT NULL,
          `entity` VARCHAR(30) NOT NULL,
          `entity_id` INT DEFAULT NULL,
          `entity_name` VARCHAR(200) DEFAULT NULL,
          `old_data` LONGTEXT DEFAULT NULL,
          `new_data` LONGTEXT DEFAULT NULL,
          `ip_address` VARCHAR(45) DEFAULT NULL,
          `user_agent` VARCHAR(500) DEFAULT NULL,
          `notes` TEXT DEFAULT NULL,
          `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

function writeAuditLog($action, $entity, $entity_id = null, $entity_name = '', $old_data = null, $new_data = null, $notes = '') {
    try {
        $conn = getDBConnection();
        ensureAuditLogsTable($conn);
        
        $church_id = getChurchId();
        $uncle_id = $_SESSION['uncle_id'] ?? null;
        $uncle_name = $_SESSION['uncle_name'] ?? $_SESSION['username'] ?? 'system';
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

        // 1. Delta JSON Compression: If both old and new data are arrays, extract ONLY the changed keys
        if (is_array($old_data) && is_array($new_data)) {
            $changed_old = [];
            $changed_new = [];
            $all_keys = array_unique(array_merge(array_keys($old_data), array_keys($new_data)));
            foreach ($all_keys as $k) {
                // Ignore internal metadata keys like _attendance_history if unchanged
                if (in_array($k, ['_attendance_history', '_sibling_groups']) && empty($old_data[$k]) && empty($new_data[$k])) {
                    continue;
                }
                $v1 = $old_data[$k] ?? null;
                $v2 = $new_data[$k] ?? null;
                if (json_encode($v1, JSON_UNESCAPED_UNICODE) !== json_encode($v2, JSON_UNESCAPED_UNICODE)) {
                    $changed_old[$k] = $v1;
                    $changed_new[$k] = $v2;
                }
            }
            $old_data = !empty($changed_old) ? $changed_old : null;
            $new_data = !empty($changed_new) ? $changed_new : null;
        }
        
        // Convert arrays to compact JSON strings
        $old_data_json = is_array($old_data) ? json_encode($old_data, JSON_UNESCAPED_UNICODE) : $old_data;
        $new_data_json = is_array($new_data) ? json_encode($new_data, JSON_UNESCAPED_UNICODE) : $new_data;

        // 2. Debounce Window Consolidation: Merge rapid sequential updates (within 10 mins) for same entity/uncle
        $consolidateActions = ['trip_payment_update', 'trip_registration', 'coupon_edit', 'attendance_add', 'attendance_edit', 'student_edit'];
        if (in_array($action, $consolidateActions) && !empty($entity_id) && !empty($uncle_id)) {
            $checkStmt = $conn->prepare("
                SELECT id, old_data, new_data, notes 
                FROM audit_logs 
                WHERE church_id = ? AND uncle_id = ? AND action = ? AND entity = ? AND entity_id = ? 
                  AND created_at >= NOW() - INTERVAL 10 MINUTE 
                ORDER BY id DESC LIMIT 1
            ");
            if ($checkStmt) {
                $checkStmt->bind_param("iissi", $church_id, $uncle_id, $action, $entity, $entity_id);
                $checkStmt->execute();
                $prev = $checkStmt->get_result()->fetch_assoc();
                if ($prev) {
                    // Update existing log entry instead of inserting a new row
                    $prevNew = !empty($prev['new_data']) ? json_decode($prev['new_data'], true) : [];
                    $mergedNew = is_array($new_data) ? array_merge(is_array($prevNew) ? $prevNew : [], $new_data) : $new_data;
                    $mergedNewJson = is_array($mergedNew) ? json_encode($mergedNew, JSON_UNESCAPED_UNICODE) : $mergedNew;

                    $mergedNotes = $prev['notes'] ?: $notes;
                    if ($notes && strpos($mergedNotes, $notes) === false) {
                        $mergedNotes .= ' | ' . $notes;
                    }

                    $upStmt = $conn->prepare("
                        UPDATE audit_logs 
                        SET new_data = ?, notes = ?, created_at = NOW() 
                        WHERE id = ?
                    ");
                    if ($upStmt) {
                        $upStmt->bind_param("ssi", $mergedNewJson, $mergedNotes, $prev['id']);
                        return $upStmt->execute();
                    }
                }
            }
        }
        
        $stmt = $conn->prepare("
            INSERT INTO audit_logs 
            (church_id, uncle_id, uncle_name, action, entity, entity_id, entity_name, 
             old_data, new_data, ip_address, user_agent, notes, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        if (!$stmt) {
            throw new Exception("Failed to prepare audit log statement: " . $conn->error);
        }
        
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
        
    } catch (Throwable $e) {
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
    $student = $result->fetch_assoc();
    if (!$student) return null;

    // Fetch related attendance records
    $attStmt = $conn->prepare("SELECT attendance_date, status, uncle_id, church_id FROM attendance WHERE student_id = ?");
    $attStmt->bind_param("i", $studentId);
    $attStmt->execute();
    $attResult = $attStmt->get_result();
    $attendance = [];
    while ($row = $attResult->fetch_assoc()) {
        $attendance[] = $row;
    }
    $student['_attendance_history'] = $attendance;

    // Fetch related sibling group members
    if ($conn->query("SHOW TABLES LIKE 'student_sibling_group_members'")->num_rows > 0) {
        $sibStmt = $conn->prepare("SELECT group_id FROM student_sibling_group_members WHERE student_id = ?");
        $sibStmt->bind_param("i", $studentId);
        $sibStmt->execute();
        $sibResult = $sibStmt->get_result();
        $siblings = [];
        while ($row = $sibResult->fetch_assoc()) {
            $siblings[] = intval($row['group_id']);
        }
        $student['_sibling_groups'] = $siblings;
    }

    return $student;
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
        ensureAuditLogsTable($conn);
        
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
        if (!$stmt) {
            throw new Exception("Failed to prepare getAuditLogs statement: " . $conn->error);
        }
        
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
        if (!$countStmt) {
            throw new Exception("Failed to prepare countAuditLogs statement: " . $conn->error);
        }
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
        
    } catch (Throwable $e) {
        error_log("getAuditLogs error: " . $e->getMessage());
        sendJSON(['success' => false, 'message' => 'خطأ في تحميل سجل العمليات: ' . $e->getMessage()]);
    }
}

/**
 * getAuditAnalytics() — Provides detailed volume overview & breakdowns of audit logs,
 * specifically analyzing trip logs, top trips taking space, actions inside trips, and actor contributions.
 */
function getAuditAnalytics(): void {
    checkAuth();
    try {
        $churchId = getChurchId();
        $conn = getDBConnection();
        ensureAuditLogsTable($conn);

        $isAll = (!empty($_POST['all_churches']) && $_POST['all_churches'] === '1') || 
                 (isset($_SESSION['uncle_role']) && $_SESSION['uncle_role'] === 'developer');

        $whereClause = "WHERE 1=1";
        if (!$isAll) {
            $whereClause .= " AND al.church_id = " . intval($churchId);
        }

        // 1. Total logs count
        $resTotal = $conn->query("SELECT COUNT(*) as cnt FROM audit_logs al $whereClause");
        $totalLogs = $resTotal ? intval($resTotal->fetch_assoc()['cnt']) : 0;

        // 2. Entity / Category Breakdown
        $catSql = "SELECT 
                    CASE 
                        WHEN LOWER(al.entity) LIKE '%trip%' OR LOWER(al.action) LIKE '%trip%' THEN 'trip'
                        WHEN LOWER(al.entity) LIKE '%student%' OR LOWER(al.action) LIKE '%student%' THEN 'student'
                        WHEN LOWER(al.entity) LIKE '%attendance%' OR LOWER(al.action) LIKE '%attendance%' THEN 'attendance'
                        WHEN LOWER(al.entity) LIKE '%coupon%' OR LOWER(al.action) LIKE '%coupon%' THEN 'coupon'
                        WHEN LOWER(al.entity) LIKE '%auth%' OR LOWER(al.action) LIKE '%login%' OR LOWER(al.action) LIKE '%logout%' THEN 'auth'
                        ELSE 'other'
                    END as cat_key,
                    COUNT(*) as cnt
                   FROM audit_logs al
                   $whereClause
                   GROUP BY cat_key
                   ORDER BY cnt DESC";
        $resCat = $conn->query($catSql);
        $categoryBreakdown = [];
        if ($resCat) {
            while ($r = $resCat->fetch_assoc()) {
                $cnt = intval($r['cnt']);
                $pct = $totalLogs > 0 ? round(($cnt / $totalLogs) * 100, 1) : 0;
                $categoryBreakdown[] = [
                    'category' => $r['cat_key'],
                    'count' => $cnt,
                    'percentage' => $pct
                ];
            }
        }

        // 3. TRIP BREAKDOWN: Which specific trip is generating the most logs?
        $tripWhere = $whereClause . " AND (LOWER(al.entity) LIKE '%trip%' OR LOWER(al.action) LIKE '%trip%')";
        $tripSql = "SELECT 
                        COALESCE(NULLIF(TRIM(al.entity_name), ''), CONCAT('رحلة #', al.entity_id)) as trip_name,
                        al.entity_id as trip_id,
                        COUNT(*) as cnt
                    FROM audit_logs al
                    $tripWhere
                    GROUP BY trip_name, al.entity_id
                    ORDER BY cnt DESC
                    LIMIT 10";
        $resTrips = $conn->query($tripSql);
        $topTrips = [];
        $totalTripLogs = 0;
        if ($resTrips) {
            while ($r = $resTrips->fetch_assoc()) {
                $topTrips[] = [
                    'trip_name' => $r['trip_name'] ?: 'رحلة غير محددة',
                    'trip_id' => intval($r['trip_id']),
                    'count' => intval($r['cnt'])
                ];
                $totalTripLogs += intval($r['cnt']);
            }
        }

        // 4. TRIP ACTIONS BREAKDOWN: What exact action inside trips is taking so much volume?
        $tripActionsSql = "SELECT 
                            al.action,
                            COUNT(*) as cnt
                           FROM audit_logs al
                           $tripWhere
                           GROUP BY al.action
                           ORDER BY cnt DESC";
        $resTripActions = $conn->query($tripActionsSql);
        $tripActionBreakdown = [];
        if ($resTripActions) {
            while ($r = $resTripActions->fetch_assoc()) {
                $cnt = intval($r['cnt']);
                $pct = $totalTripLogs > 0 ? round(($cnt / $totalTripLogs) * 100, 1) : 0;
                $tripActionBreakdown[] = [
                    'action' => $r['action'],
                    'count' => $cnt,
                    'percentage' => $pct
                ];
            }
        }

        // 5. TOP SERVANTS / UNCLES generating trip logs
        $actorSql = "SELECT 
                        COALESCE(NULLIF(TRIM(al.actor_name), ''), 'مسؤول غير معروف') as actor_name,
                        COUNT(*) as cnt
                     FROM audit_logs al
                     $tripWhere
                     GROUP BY actor_name
                     ORDER BY cnt DESC
                     LIMIT 5";
        $resActors = $conn->query($actorSql);
        $topTripActors = [];
        if ($resActors) {
            while ($r = $resActors->fetch_assoc()) {
                $topTripActors[] = [
                    'actor_name' => $r['actor_name'],
                    'count' => intval($r['cnt'])
                ];
            }
        }

        sendJSON([
            'success' => true,
            'total_logs' => $totalLogs,
            'categories' => $categoryBreakdown,
            'trip_analytics' => [
                'total_trip_logs' => $totalTripLogs,
                'top_trips' => $topTrips,
                'action_breakdown' => $tripActionBreakdown,
                'top_actors' => $topTripActors
            ]
        ]);
    } catch (Throwable $e) {
        error_log("getAuditAnalytics error: " . $e->getMessage());
        sendJSON(['success' => false, 'message' => 'خطأ في حساب تحليل البيانات: ' . $e->getMessage()]);
    }
}

/**
 * compactExistingAuditLogs() — Compresses historical audit logs for trips and coupons:
 * - Trims redundant old JSON fields, leaving only changed key-value pairs.
 */
function compactExistingAuditLogs(): void {
    checkAuth();
    try {
        $churchId = getChurchId();
        $conn = getDBConnection();
        ensureAuditLogsTable($conn);

        $isAll = (!empty($_POST['all_churches']) && $_POST['all_churches'] === '1') || 
                 (isset($_SESSION['uncle_role']) && $_SESSION['uncle_role'] === 'developer');

        $whereClause = "WHERE 1=1";
        if (!$isAll) {
            $whereClause .= " AND church_id = " . intval($churchId);
        }

        // Fetch logs with large JSON payloads
        $sql = "SELECT id, action, entity, entity_id, old_data, new_data FROM audit_logs $whereClause ORDER BY id ASC";
        $result = $conn->query($sql);

        $compressedCount = 0;

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $id = intval($row['id']);
                $oldData = !empty($row['old_data']) ? json_decode($row['old_data'], true) : null;
                $newData = !empty($row['new_data']) ? json_decode($row['new_data'], true) : null;

                // Delta Compression
                if (is_array($oldData) && is_array($newData)) {
                    $changedOld = [];
                    $changedNew = [];
                    $allKeys = array_unique(array_merge(array_keys($oldData), array_keys($newData)));
                    foreach ($allKeys as $k) {
                        $v1 = $oldData[$k] ?? null;
                        $v2 = $newData[$k] ?? null;
                        if (json_encode($v1, JSON_UNESCAPED_UNICODE) !== json_encode($v2, JSON_UNESCAPED_UNICODE)) {
                            $changedOld[$k] = $v1;
                            $changedNew[$k] = $v2;
                        }
                    }
                    if (count($changedOld) < count($oldData) || count($changedNew) < count($newData)) {
                        $compactOldJson = !empty($changedOld) ? json_encode($changedOld, JSON_UNESCAPED_UNICODE) : null;
                        $compactNewJson = !empty($changedNew) ? json_encode($changedNew, JSON_UNESCAPED_UNICODE) : null;
                        
                        $uStmt = $conn->prepare("UPDATE audit_logs SET old_data = ?, new_data = ? WHERE id = ?");
                        if ($uStmt) {
                            $uStmt->bind_param("ssi", $compactOldJson, $compactNewJson, $id);
                            $uStmt->execute();
                            $compressedCount++;
                        }
                    }
                }
            }
        }

        sendJSON([
            'success' => true,
            'message' => "تم ضغط وتقليل حجم السجلات القديمة بنجاح! تم تنظيف وتصغير $compressedCount سجل مكرر البيانات.",
            'compressed_count' => $compressedCount
        ]);
    } catch (Throwable $e) {
        error_log("compactExistingAuditLogs error: " . $e->getMessage());
        sendJSON(['success' => false, 'message' => 'خطأ في ضغط السجلات: ' . $e->getMessage()]);
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
        $entity   = preg_replace('/[^a-zA-Z0-9_]/', '', $_POST['entity'] ?? '');
        $entityId = intval($_POST['entity_id']   ?? 0);

        if (empty($entity) || $entityId === 0) {
            sendJSON(['success' => false, 'message' => 'بيانات غير كاملة']);
            return;
        }

        $conn = getDBConnection();
        ensureAuditLogsTable($conn);
        
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
        if (!$stmt) {
            throw new Exception("Failed to prepare getEntityAuditHistory statement: " . $conn->error);
        }
        
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

    } catch (Throwable $e) {
        error_log("getEntityAuditHistory error: " . $e->getMessage());
        sendJSON(['success' => false, 'message' => 'خطأ في جلب السجل: ' . $e->getMessage()]);
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

function recoverStudentAttendanceFromAuditLogs($studentId, $churchId, $conn) {
    try {
        $history = []; // date => [status, uncle_id]

        // 1. Get individual attendance logs, ordered by ID ascending (chronological)
        $stmt = $conn->prepare("
            SELECT action, new_data, old_data, uncle_id 
            FROM audit_logs 
            WHERE church_id = ? 
              AND entity = 'attendance' 
              AND entity_id = ? 
              AND action IN ('attendance_add', 'attendance_edit', 'attendance_delete')
            ORDER BY id ASC
        ");
        $stmt->bind_param("ii", $churchId, $studentId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $action = $row['action'];
            $newData = !empty($row['new_data']) ? json_decode($row['new_data'], true) : null;
            $oldData = !empty($row['old_data']) ? json_decode($row['old_data'], true) : null;

            if ($action === 'attendance_delete') {
                $date = $oldData['attendance_date'] ?? null;
                if ($date) {
                    unset($history[$date]);
                }
            } else {
                $date = $newData['date'] ?? null;
                $status = $newData['status'] ?? null;
                if ($date && $status) {
                    $history[$date] = [
                        'status' => $status,
                        'uncle_id' => $row['uncle_id']
                    ];
                }
            }
        }

        // 2. Get bulk attendance logs
        $stmt2 = $conn->prepare("
            SELECT new_data, uncle_id 
            FROM audit_logs 
            WHERE church_id = ? 
              AND entity = 'bulk_action' 
              AND action = 'bulk_attendance_save'
            ORDER BY id ASC
        ");
        $stmt2->bind_param("i", $churchId);
        $stmt2->execute();
        $res2 = $stmt2->get_result();
        while ($row = $res2->fetch_assoc()) {
            $items = !empty($row['new_data']) ? json_decode($row['new_data'], true) : null;
            if (is_array($items)) {
                foreach ($items as $item) {
                    $sid = intval($item['id'] ?? 0);
                    if ($sid === $studentId) {
                        $date = $item['date'] ?? null;
                        $status = $item['new_data']['status'] ?? null;
                        if ($date && $status) {
                            $history[$date] = [
                                'status' => $status,
                                'uncle_id' => $row['uncle_id']
                            ];
                        }
                    }
                }
            }
        }

        // 3. Re-insert the recovered attendance records
        if (!empty($history)) {
            $ins = $conn->prepare("
                INSERT INTO attendance (student_id, church_id, attendance_date, status, uncle_id, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE status = VALUES(status), uncle_id = VALUES(uncle_id)
            ");
            foreach ($history as $date => $info) {
                if ($info['status'] === 'pending') {
                    $del = $conn->prepare("DELETE FROM attendance WHERE student_id = ? AND attendance_date = ?");
                    $del->bind_param("is", $studentId, $date);
                    $del->execute();
                } else {
                    $ins->bind_param("iissi", $studentId, $churchId, $date, $info['status'], $info['uncle_id']);
                    $ins->execute();
                }
            }
        }
    } catch (Exception $e) {
        error_log("recoverStudentAttendanceFromAuditLogs error: " . $e->getMessage());
    }
}

// ── TRIP PAYMENTS ────────────────────────────────────────────

function auditTripPaymentAdd(int $paymentId, int $registrationId, float $amount, float $donation, string $method, string $notes): void {
    writeAuditLog(
        'trip_payment_add',
        'trip_payment',
        $paymentId,
        "دفعة رقم $paymentId",
        null,
        [
            'registration_id' => $registrationId,
            'amount' => $amount,
            'donation' => $donation,
            'payment_method' => $method,
            'notes' => $notes
        ],
        "إضافة دفعة/تبرع للرحلة بقيمة " . ($amount > 0 ? "$amount ج" : "$donation ج تبرع")
    );
}

function auditTripPaymentDelete(int $paymentId, array $paymentInfo): void {
    writeAuditLog(
        'trip_payment_delete',
        'trip_payment',
        $paymentId,
        "دفعة رقم $paymentId",
        $paymentInfo,
        null,
        "إلغاء/حذف دفعة الرحلة الملغاة بقيمة {$paymentInfo['amount']} ج"
    );
}

function auditTripPaymentRestore(int $paymentId, array $paymentInfo): void {
    writeAuditLog(
        'trip_payment_restore',
        'trip_payment',
        $paymentId,
        "دفعة رقم $paymentId",
        null,
        $paymentInfo,
        "استعادة دفعة الرحلة بقيمة {$paymentInfo['amount']} ج"
    );
}

function auditTripWaitlistPaymentAdd(string $paymentId, int $studentId, int $tripId, float $amount, float $donation, string $method, string $notes): void {
    writeAuditLog(
        'trip_waitlist_payment_add',
        'trip_waitlist_payment',
        $tripId,
        "طفل ID: $studentId",
        null,
        [
            'payment_id' => $paymentId,
            'student_id' => $studentId,
            'amount' => $amount,
            'donation' => $donation,
            'payment_method' => $method,
            'notes' => $notes
        ],
        "إضافة دفعة انتظار بقيمة " . ($amount > 0 ? "$amount ج" : "$donation ج تبرع")
    );
}

function auditTripWaitlistPaymentDelete(string $paymentId, int $studentId, int $tripId, array $paymentInfo): void {
    writeAuditLog(
        'trip_waitlist_payment_delete',
        'trip_waitlist_payment',
        $tripId,
        "طفل ID: $studentId",
        $paymentInfo,
        null,
        "حذف دفعة انتظار بقيمة {$paymentInfo['amount']} ج"
    );
}

function auditTripWaitlistPaymentRestore(string $paymentId, int $studentId, int $tripId, array $paymentInfo): void {
    writeAuditLog(
        'trip_waitlist_payment_restore',
        'trip_waitlist_payment',
        $tripId,
        "طفل ID: $studentId",
        null,
        $paymentInfo,
        "استعادة دفعة انتظار بقيمة {$paymentInfo['amount']} ج"
    );
}

function auditTripWaitlistPromotion(int $tripId, int $studentId, string $studentName, int $registrationId, float $totalPaid): void {
    writeAuditLog(
        'trip_waitlist_promotion',
        'trip_registration',
        $registrationId,
        $studentName,
        null,
        [
            'student_id' => $studentId,
            'trip_id' => $tripId,
            'registration_id' => $registrationId,
            'total_paid' => $totalPaid
        ],
        "ترقية الطفل $studentName من قائمة الانتظار إلى تسجيل مؤكد للرحلة ID: $tripId"
    );
}