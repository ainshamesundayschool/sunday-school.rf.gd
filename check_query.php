<?php
header('Content-Type: text/plain; charset=utf-8');
require_once __DIR__ . '/config.php';

session_start();

try {
    $conn = getDBConnection();
    echo "=== DATABASE CONNECTION SUCCESSFUL ===\n\n";

    $churchId = getChurchId();
    $uncleId = intval($_SESSION['uncle_id'] ?? 1); // fallback to 1 for testing
    $limit = 100;

    echo "Parameters:\n";
    echo "churchId: $churchId\n";
    echo "uncleId: $uncleId\n";
    echo "limit: $limit\n\n";

    echo "Attempting Query 1 (Admin/No Uncle):\n";
    $stmt1 = $conn->prepare("
        SELECT id, action, entity, entity_id, entity_name,
               uncle_name, old_data, new_data, notes, ip_address, created_at
        FROM audit_logs
        WHERE church_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ");
    if (!$stmt1) {
        echo "Query 1 prepare failed: " . $conn->error . "\n";
    } else {
        echo "Query 1 prepare succeeded.\n";
        if (!$stmt1->bind_param("ii", $churchId, $limit)) {
            echo "Query 1 bind_param failed: " . $stmt1->error . "\n";
        } else {
            if (!$stmt1->execute()) {
                echo "Query 1 execute failed: " . $stmt1->error . "\n";
            } else {
                $res1 = $stmt1->get_result();
                if (!$res1) {
                    echo "Query 1 get_result failed: " . $stmt1->error . "\n";
                } else {
                    echo "Query 1 succeeded. Rows found: " . $res1->num_rows . "\n";
                }
            }
        }
    }

    echo "\nAttempting Query 2 (Uncle):\n";
    $stmt2 = $conn->prepare("
        SELECT id, action, entity, entity_id, entity_name,
               uncle_name, old_data, new_data, notes, ip_address, created_at
        FROM audit_logs
        WHERE uncle_id = ? AND church_id = ?
        ORDER BY created_at DESC
        LIMIT ?
    ");
    if (!$stmt2) {
        echo "Query 2 prepare failed: " . $conn->error . "\n";
    } else {
        echo "Query 2 prepare succeeded.\n";
        if (!$stmt2->bind_param("iii", $uncleId, $churchId, $limit)) {
            echo "Query 2 bind_param failed: " . $stmt2->error . "\n";
        } else {
            if (!$stmt2->execute()) {
                echo "Query 2 execute failed: " . $stmt2->error . "\n";
            } else {
                $res2 = $stmt2->get_result();
                if (!$res2) {
                    echo "Query 2 get_result failed: " . $stmt2->error . "\n";
                } else {
                    echo "Query 2 succeeded. Rows found: " . $res2->num_rows . "\n";
                }
            }
        }
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
