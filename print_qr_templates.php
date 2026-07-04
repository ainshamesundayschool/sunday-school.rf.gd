<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config.php';
$conn = getDBConnection();

header('Content-Type: application/json; charset=utf-8');

try {
    $churchId = 0;
    if (isset($_SESSION['church_id'])) {
        $churchId = intval($_SESSION['church_id']);
    } else if (isset($_POST['church_id'])) {
        $churchId = intval($_POST['church_id']);
    } else if (isset($_GET['church_id'])) {
        $churchId = intval($_GET['church_id']);
    } else {
        // Fallback to first church in DB for simulation
        $cRes = $conn->query("SELECT id FROM churches LIMIT 1");
        if ($cRes && $row = $cRes->fetch_assoc()) {
            $churchId = intval($row['id']);
        }
    }

    $tripId = intval($_POST['trip_id'] ?? $_GET['trip_id'] ?? 0);

    // 1. Church template
    $churchStmt = $conn->prepare("SELECT qr_template FROM church_settings WHERE church_id = ?");
    $churchStmt->bind_param("i", $churchId);
    $churchStmt->execute();
    $churchRow = $churchStmt->get_result()->fetch_assoc();
    $churchTemplate = $churchRow['qr_template'] ?? null;

    // 2. Trip template
    $tripTemplate = null;
    if ($tripId > 0) {
        $tripStmt = $conn->prepare("SELECT qr_template FROM trips WHERE id = ?");
        $tripStmt->bind_param("i", $tripId);
        $tripStmt->execute();
        $tripRow = $tripStmt->get_result()->fetch_assoc();
        $tripTemplate = $tripRow['qr_template'] ?? null;
    }

    // 3. Other trips templates
    $otherTrips = [];
    $othersStmt = $conn->prepare("SELECT id, title, qr_template FROM trips WHERE church_id = ? AND qr_template IS NOT NULL AND id != ?");
    $othersStmt->bind_param("ii", $churchId, $tripId);
    $othersStmt->execute();
    $res = $othersStmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $otherTrips[] = [
            'id' => $row['id'],
            'title' => $row['title'],
            'template' => $row['qr_template']
        ];
    }

    echo json_encode([
        'success' => true,
        'simulated_church_id' => $churchId,
        'simulated_trip_id' => $tripId,
        'church_template' => $churchTemplate,
        'trip_template' => $tripTemplate,
        'other_trips' => $otherTrips
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
