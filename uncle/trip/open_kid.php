<?php
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);
ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 365 * 10);

// Robust local session directory to prevent aggressive shared hosting garbage collection
$rootPath = dirname(__FILE__);
while ($rootPath && !file_exists($rootPath . '/api.php')) {
  $parent = dirname($rootPath);
  if ($parent === $rootPath)
    break;
  $rootPath = $parent;
}
$sessionPath = $rootPath . '/.sessions';
if (!is_dir($sessionPath)) {
  @mkdir($sessionPath, 0755, true);
}
if (is_writable($sessionPath)) {
  session_save_path($sessionPath);
}

ini_set('session.gc_maxlifetime', 315360000);
ini_set('session.cookie_lifetime', 315360000);
session_start();

// Check if uncle/church is logged in
$isUncleLoggedIn = isset($_SESSION['uncle_id']) || isset($_SESSION['church_id']);
if (!$isUncleLoggedIn) {
  // Redirect to login page and forward current request URI to return here after login
  $redirectUrl = "/login/?redirect=" . urlencode($_SERVER['REQUEST_URI']);
  header("Location: " . $redirectUrl);
  exit;
}

require_once $rootPath . '/config.php';

$studentId = isset($_GET['id']) ? intval($_GET['id']) : null;
$tempId = isset($_GET['tempid']) ? $_GET['tempid'] : null;

$conn = getDBConnection();

// Resolve temp ID to real student ID if needed
if (!$studentId && $tempId) {
  try {
    $stmt = $conn->prepare("SELECT student_id AS id FROM student_temp_ids WHERE temp_id = ? LIMIT 1");
    $stmt->bind_param("s", $tempId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
      $studentId = intval($row['id']);
    }
  } catch (Exception $e) {
    // Database connection or query failed
  }
}

$studentName = "غير معروف";
$trips = [];

if ($studentId) {
  try {
    // Fetch student details
    $stmt = $conn->prepare("SELECT name FROM students WHERE id = ? LIMIT 1");
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $row = $res->fetch_assoc()) {
      $studentName = $row['name'];
    }

    // Fetch active registrations for this student in trips
    $stmt = $conn->prepare("
      SELECT t.id AS trip_id, t.title AS trip_title
      FROM trip_registrations tr
      JOIN trips t ON tr.trip_id = t.id
      WHERE tr.student_id = ? AND tr.cancelled = 0
    ");
    $stmt->bind_param("i", $studentId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($res && $row = $res->fetch_assoc()) {
      $trips[] = $row;
    }
  } catch (Exception $e) {
    // Database connection or query failed
  }
}

// If student is registered in exactly one trip, skip selection and redirect directly
if (count($trips) === 1) {
  $tripId = $trips[0]['trip_id'];
  header("Location: /uncle/trip/index.html?trip_id=" . $tripId . "&student_id=" . $studentId);
  exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>فتح ملف الطفل - Sunday School</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/fonts/cairo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        :root {
            --brand: #4f46e5;
            --brand-gradient: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            --bg: #0f172a;
            --surf: rgba(255, 255, 255, 0.08);
            --surf-hover: rgba(255, 255, 255, 0.14);
            --bdr: rgba(255, 255, 255, 0.15);
            --bdr-hover: rgba(255, 255, 255, 0.3);
            --t-white: #ffffff;
            --t-muted: #94a3b8;
            --r-lg: 24px;
            --r-md: 16px;
            --sh-lg: 0 20px 40px rgba(0, 0, 0, 0.3);
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Cairo', 'Cairo', sans-serif;
        }

        body {
            background: var(--bg);
            color: var(--t-white);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        /* Beautiful glowing dynamic backgrounds */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            z-index: -1;
            background: 
                radial-gradient(circle at 15% 15%, rgba(79, 70, 229, 0.25) 0%, transparent 50%),
                radial-gradient(circle at 85% 85%, rgba(124, 58, 237, 0.2) 0%, transparent 50%);
        }

        .container {
            width: 100%;
            max-width: 480px;
            background: var(--surf);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--bdr);
            border-radius: var(--r-lg);
            padding: 36px 24px;
            box-shadow: var(--sh-lg);
            text-align: center;
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            opacity: 0;
            transform: translateY(20px);
        }

        @keyframes slideUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .icon-wrapper {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--brand-gradient);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            margin-bottom: 20px;
            box-shadow: 0 8px 24px rgba(79, 70, 229, 0.4);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 8px 24px rgba(79, 70, 229, 0.4);
            }
            50% {
                transform: scale(1.05);
                box-shadow: 0 12px 32px rgba(79, 70, 229, 0.6);
            }
        }

        h1 {
            font-size: 1.65rem;
            font-weight: 800;
            margin-bottom: 8px;
            background: linear-gradient(135deg, #fff 0%, #cbd5e1 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .student-name {
            font-size: 1.3rem;
            font-weight: 700;
            color: #818cf8;
            margin-bottom: 12px;
        }

        p.subtitle {
            font-size: 0.95rem;
            color: var(--t-muted);
            margin-bottom: 28px;
            line-height: 1.6;
        }

        .trip-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            margin-bottom: 24px;
        }

        .trip-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--bdr);
            padding: 16px 20px;
            border-radius: var(--r-md);
            cursor: pointer;
            text-decoration: none;
            color: var(--t-white);
            transition: var(--transition);
            text-align: right;
        }

        .trip-item:hover {
            background: var(--surf-hover);
            border-color: var(--bdr-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.2);
        }

        .trip-info {
            display: flex;
            align-items: center;
            gap: 14px;
        }

        .trip-icon {
            width: 40px;
            height: 40px;
            border-radius: 12px;
            background: rgba(99, 102, 241, 0.15);
            color: #818cf8;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        .trip-name {
            font-size: 1.05rem;
            font-weight: 700;
        }

        .arrow-icon {
            font-size: 1rem;
            color: var(--t-muted);
            transition: var(--transition);
        }

        .trip-item:hover .arrow-icon {
            color: var(--t-white);
            transform: translateX(-4px);
        }

        .no-trips-icon {
            font-size: 3rem;
            color: #ef4444;
            margin-bottom: 16px;
        }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid var(--bdr);
            color: var(--t-white);
            border-radius: var(--r-md);
            text-decoration: none;
            font-weight: 700;
            width: 100%;
            transition: var(--transition);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: var(--bdr-hover);
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!$studentId || $studentName === "غير معروف"): ?>
            <div class="no-trips-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            <h1>طفل غير معروف</h1>
            <p class="subtitle" style="margin-bottom: 24px;">عذراً، لم نتمكن من العثور على بيانات هذا الطفل في النظام.</p>
            <a href="/uncle/dashboard/" class="btn-secondary">
                <i class="fas fa-home"></i> العودة للوحة التحكم
            </a>
        <?php elseif (count($trips) === 0): ?>
            <div class="no-trips-icon">
                <i class="fas fa-calendar-times"></i>
            </div>
            <h1>غير مسجل في رحلات</h1>
            <p class="student-name"><?= htmlspecialchars($studentName) ?></p>
            <p class="subtitle">هذا الطفل ليس مسجلاً في أي رحلة نشطة حالياً.</p>
            <a href="/uncle/dashboard/" class="btn-secondary">
                <i class="fas fa-home"></i> العودة للوحة التحكم
            </a>
        <?php else: ?>
            <div class="icon-wrapper">
                <i class="fas fa-route"></i>
            </div>
            <h1>اختر الرحلة</h1>
            <p class="student-name"><?= htmlspecialchars($studentName) ?></p>
            <p class="subtitle">الطفل مسجل في عدة رحلات. اختر الرحلة التي ترغب في فتح ملف الطفل بها:</p>
            
            <div class="trip-list">
                <?php foreach ($trips as $t): ?>
                    <a href="/uncle/trip/index.html?trip_id=<?= $t['trip_id'] ?>&student_id=<?= $studentId ?>" class="trip-item">
                        <div class="trip-info">
                            <div class="trip-icon">
                                <i class="fas fa-umbrella-beach"></i>
                            </div>
                            <span class="trip-name"><?= htmlspecialchars($t['trip_title']) ?></span>
                        </div>
                        <i class="fas fa-chevron-left arrow-icon"></i>
                    </a>
                <?php endforeach; ?>
            </div>
            
            <a href="/uncle/dashboard/" class="btn-secondary">
                <i class="fas fa-times"></i> إلغاء
            </a>
        <?php endif; ?>
    </div>
</body>
</html>
