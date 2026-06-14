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

if (!isset($_SESSION['uncle_id']) && !isset($_SESSION['church_id'])) {
    header("Location: /login/");
    exit();
}

require_once '../../../config.php';

$tripId = intval($_GET['trip_id'] ?? 0);
if ($tripId <= 0) {
    header("Location: /uncle/dashboard/");
    exit();
}

$conn = getDBConnection();
$tstmt = $conn->prepare("SELECT title, church_id, collaborating_churches, points_config FROM trips WHERE id = ? LIMIT 1");
$tstmt->bind_param('i', $tripId);
$tstmt->execute();
$trip = $tstmt->get_result()->fetch_assoc();

if (!$trip) {
    echo "الرحلة غير موجودة";
    exit();
}

$tripTitle = $trip['title'];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>الماسح السريع للنقاط | مدارس الأحد</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --brand: #5b6cf5;
            --brand-bg: #eef0ff;
            --brand-glow: rgba(91, 108, 245, .18);
            --success: #10b981;
            --success-bg: #d1fae5;
            --danger: #ef4444;
            --danger-bg: #fee2e2;
            --bg: #f3f4f9;
            --surface: #ffffff;
            --surface-2: #f7f8fc;
            --surface-3: #eef0f8;
            --border-solid: #e4e6f0;
            --text: #1a1d2e;
            --text-2: #4b5068;
            --text-3: #8b90a8;
            --shadow-sm: 0 2px 8px -2px rgba(0, 0, 0, .07);
            --shadow-md: 0 8px 24px -4px rgba(0, 0, 0, .10);
            --shadow-lg: 0 20px 48px -8px rgba(0, 0, 0, .14);
            --r-md: 14px;
            --r-lg: 18px;
            --r-xl: 24px;
            --t: .22s;
            --ease: cubic-bezier(.4, 0, .2, 1);
        }

        [data-theme="dark"] {
            --bg: #0f1117;
            --surface: #181b26;
            --surface-2: #1e2132;
            --surface-3: #252840;
            --border-solid: #2a2d42;
            --text: #e8eaf6;
            --text-2: #9299be;
            --text-3: #565c7a;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
            font-family: 'Cairo', sans-serif;
        }

        body {
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            line-height: 1.6;
        }

        /* Ambient background mesh */
        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background:
                radial-gradient(ellipse 80% 50% at 10% -10%, rgba(91, 108, 245, .07) 0%, transparent 60%),
                radial-gradient(ellipse 60% 40% at 90% 110%, rgba(139, 92, 246, .05) 0%, transparent 60%);
            pointer-events: none;
            z-index: 0;
        }

        .container {
            position: relative;
            z-index: 1;
            max-width: 800px;
            margin: 0 auto;
            padding: 10px;
            padding-bottom: 24px;
        }

        .top-row-grid {
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            gap: 12px;
            margin-bottom: 12px;
        }

        @media (max-width: 650px) {
            .top-row-grid {
                grid-template-columns: 1fr;
                gap: 10px;
            }
        }

        .split-scans-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px;
            margin-top: 10px;
        }

        @media (max-width: 650px) {
            .split-scans-container {
                grid-template-columns: 1fr;
                gap: 16px;
            }
        }

        .scans-column {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .scans-column-title {
            font-size: 0.95rem;
            font-weight: 800;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 2px solid var(--border-solid);
            padding-bottom: 6px;
            margin-bottom: 4px;
            color: var(--text-2);
        }

        #scanSearchInput:focus {
            border-color: var(--brand);
            box-shadow: 0 0 0 3px var(--brand-glow);
        }

        .header {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 12px;
        }

        .back-btn {
            width: 36px;
            height: 36px;
            border-radius: 10px;
            border: 1.5px solid var(--border-solid);
            background: var(--surface);
            color: var(--text-2);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--t);
            text-decoration: none;
        }

        .back-btn:hover {
            border-color: var(--brand);
            color: var(--brand);
        }

        .header-title {
            font-size: 1.05rem;
            font-weight: 800;
            flex: 1;
        }

        .card {
            background: var(--surface);
            border-radius: 16px;
            border: 1.5px solid var(--border-solid);
            padding: 12px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 10px;
        }

        .card-title {
            font-size: 0.92rem;
            font-weight: 800;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Amount Selector group */
        .option-group {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 6px;
            margin-bottom: 10px;
        }

        .option-btn {
            padding: 8px 4px;
            border-radius: 10px;
            border: 2px solid var(--border-solid);
            background: var(--surface-2);
            color: var(--text-2);
            font-weight: 800;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all var(--t);
            text-align: center;
        }

        .option-btn.active {
            border-color: var(--brand);
            background: var(--brand-bg);
            color: var(--brand);
        }

        /* Plus / Minus toggle buttons */
        .sign-toggle {
            display: flex;
            background: var(--surface-3);
            padding: 4px;
            border-radius: 10px;
            margin-bottom: 12px;
            gap: 4px;
        }

        .sign-btn {
            flex: 1;
            padding: 8px;
            border-radius: 8px;
            border: none;
            background: none;
            font-weight: 800;
            font-size: 0.88rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            color: var(--text-3);
            transition: all var(--t);
        }

        .sign-btn.plus.active {
            background: var(--success-bg);
            color: var(--success);
        }

        .sign-btn.minus.active {
            background: var(--danger-bg);
            color: var(--danger);
        }

        /* Cooldown switch */
        .cooldown-wrap {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--surface-2);
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            border: 1.5px solid var(--border-solid);
        }

        .cooldown-label {
            font-size: 0.88rem;
            font-weight: 700;
            color: var(--text-2);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .switch {
            position: relative;
            display: inline-block;
            width: 46px;
            height: 24px;
        }

        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .slider {
            position: absolute;
            cursor: pointer;
            top: 0; left: 0; right: 0; bottom: 0;
            background-color: var(--border-solid);
            transition: .4s;
            border-radius: 24px;
        }

        .slider:before {
            position: absolute;
            content: "";
            height: 18px;
            width: 18px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .slider {
            background-color: var(--brand);
        }

        input:checked + .slider:before {
            transform: translateX(22px);
        }

        /* Scanner modal style tab */
        .scanner-source-tabs {
            display: flex;
            background: var(--surface-3);
            padding: 4px;
            border-radius: 10px;
            margin-bottom: 8px;
            gap: 4px;
        }

        .scanner-source-tab {
            flex: 1;
            padding: 6px;
            border-radius: 8px;
            border: none;
            background: none;
            font-weight: 700;
            font-size: 0.78rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            color: var(--text-3);
            transition: all var(--t);
        }

        .scanner-source-tab.active {
            background: var(--surface);
            color: var(--brand);
        }

        #reader {
            width: 100%;
            min-height: 280px;
            background: #000;
            border-radius: 12px;
            overflow: hidden;
            border: 1.5px solid var(--border-solid);
            position: relative;
        }

        #reader video, #reader canvas {
            width: 100% !important;
            height: 100% !important;
            object-fit: cover !important;
        }

        .toast-skip-btn {
            background: var(--brand);
            color: white;
            border: none;
            padding: 4px 10px;
            border-radius: 6px;
            font-weight: 800;
            font-size: 0.72rem;
            cursor: pointer;
            margin-right: 8px;
            transition: all var(--t);
            vertical-align: middle;
            text-transform: lowercase;
        }

        .toast-skip-btn:hover {
            background: #4758d6;
        }

        /* Scans Log */
        .scans-log-title {
            font-size: 1.0rem;
            font-weight: 800;
            margin: 20px 4px 10px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .scans-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .scan-item {
            background: var(--surface);
            border-radius: 16px;
            padding: 12px;
            border: 1.5px solid var(--border-solid);
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideIn 0.3s var(--ease);
            box-shadow: var(--shadow-sm);
        }

        .scan-photo {
            width: 46px;
            height: 46px;
            border-radius: 10px;
            object-fit: cover;
            background: var(--surface-2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-3);
            font-size: 1.1rem;
            flex-shrink: 0;
            border: 1px solid var(--border-solid);
        }

        .scan-info {
            flex: 1;
            min-width: 0;
        }

        .scan-name {
            font-weight: 800;
            font-size: 0.9rem;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .scan-status {
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--text-3);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .scan-badge {
            font-weight: 900;
            font-size: 0.9rem;
            padding: 4px 10px;
            border-radius: 8px;
        }

        .scan-badge.plus {
            background: var(--success-bg);
            color: var(--success);
        }

        .scan-badge.minus {
            background: var(--danger-bg);
            color: var(--danger);
        }

        .scan-actions {
            display: flex;
            gap: 6px;
        }

        .scan-action-btn {
            border: 1px solid var(--border-solid);
            background: var(--surface-2);
            color: var(--text-2);
            font-weight: 700;
            font-size: 0.75rem;
            padding: 6px 10px;
            border-radius: 8px;
            cursor: pointer;
            transition: all var(--t);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .scan-action-btn:hover {
            border-color: var(--brand);
            color: var(--brand);
            background: var(--brand-bg);
        }

        .scan-action-btn.undo:hover {
            border-color: var(--danger);
            color: var(--danger);
            background: var(--danger-bg);
        }

        @keyframes slideIn {
            from { transform: translateY(-10px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .toast-notify {
            position: fixed;
            bottom: 24px;
            left: 50%;
            transform: translateX(-50%) translateY(100px);
            background: rgba(22, 28, 45, 0.95);
            backdrop-filter: blur(8px);
            color: white;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 800;
            font-size: 0.88rem;
            z-index: 999999;
            box-shadow: var(--shadow-lg);
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.3s;
            opacity: 0;
            text-align: center;
            direction: rtl;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .toast-notify.show {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }
    </style>
</head>
<body>

    <div class="container">
        
        <div class="header" style="align-items: center; gap: 8px; margin-bottom: 10px;">
            <a href="/uncle/trip/?trip_id=<?php echo $tripId; ?>" class="back-btn" title="عودة">
                <i class="fas fa-arrow-right"></i>
            </a>
            <div class="scanner-source-tabs" style="margin-bottom: 0; flex: 1;">
                <button type="button" id="tab_camera" class="scanner-source-tab active" onclick="switchScannerSource('camera')">
                    <i class="fas fa-camera"></i> الكاميرا
                </button>
                <button type="button" id="tab_usb" class="scanner-source-tab" onclick="switchScannerSource('usb')">
                    <i class="fas fa-barcode"></i> ماسح (USB)
                </button>
            </div>
        </div>

        <div class="top-row-grid">
            <!-- Scanner Card -->
            <div class="card" style="padding: 6px; margin-bottom: 0;">
                <div id="reader"></div>
            </div>

            <!-- Settings (Controls) Card -->
            <div class="card" style="margin-bottom: 0;">
                <div class="card-title">
                    <i class="fas fa-cog text-brand"></i> إعدادات المسح
                </div>

                <!-- Dynamic Controls Area -->
                <div id="settingsControlsArea"></div>

                <!-- Cooldown Notice -->
                <div style="display: flex; align-items: center; gap: 8px; background: var(--surface-2); padding: 8px 12px; border-radius: 10px; border: 1.5px solid var(--border-solid); font-size: 0.74rem; color: var(--text-2); font-weight: 700;">
                    <i class="fas fa-history" style="color: var(--brand); font-size: 0.95rem;"></i>
                    <div>
                        <div>حماية التكرار (فترة انتظار 15 ثانية) نشطة تلقائياً.</div>
                        <div style="font-size:0.7rem;color:var(--text-3);font-weight:600;margin-top:2px;">في حالة التكرار سيُطلب منك تأكيد المسح لمتابعة العملية.</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Search bar & Refresh Header -->
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 20px;">
            <div style="font-weight: 800; font-size: 1.05rem; color: var(--text);">سجل المسوحات الأخير</div>
            <button class="topbar-btn" onclick="loadRecentScans()" title="تحديث السجل" style="padding: 6px 12px; font-size: 0.8rem; background: var(--brand-bg); color: var(--brand-dark); border-color: var(--brand-light); border-radius: 8px; cursor: pointer; display: flex; align-items: center; gap: 6px; font-weight: 700; margin: 0; height: 32px; border: 1.5px solid var(--border);">
                <i class="fas fa-sync-alt"></i> تحديث
            </button>
        </div>
        <div class="search-wrap" style="margin-top: 10px; margin-bottom: 10px; position: relative;">
            <input type="text" id="scanSearchInput" placeholder="البحث في عمليات المسح بالأسم..." oninput="filterScansList()" style="width: 100%; padding: 10px 14px 10px 38px; border-radius: 10px; border: 1.5px solid var(--border-solid); background: var(--surface); color: var(--text); font-weight: 700; font-size: 0.88rem; transition: all var(--t); outline: none;">
            <i class="fas fa-search" style="position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-3); pointer-events: none;"></i>
        </div>

        <!-- Split Scans Columns -->
        <div class="split-scans-container">
            <!-- Right Column (First in RTL): My Scans -->
            <div class="scans-column">
                <div class="scans-column-title">
                    <span>عمليات مسحي الخاصة</span>
                    <span style="font-size: 0.72rem; color: var(--text-3); font-weight: 700;" id="myCount">0 طفل</span>
                </div>
                <div class="scans-list" id="myScansList">
                    <!-- My scanned items will appear here -->
                </div>
            </div>

            <!-- Left Column (Second in RTL): All Uncles' Scans -->
            <div class="scans-column">
                <div class="scans-column-title">
                    <span>جميع مسوحات الأعمام بالرحلة</span>
                    <span style="font-size: 0.72rem; color: var(--text-3); font-weight: 700;" id="allCount">0 طفل</span>
                </div>
                <div class="scans-list" id="allScansList">
                    <!-- All scanned items will appear here -->
                </div>
            </div>
        </div>

    </div>

    <!-- Toast Notification -->
    <div class="toast-notify" id="toastNotify"></div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <script>
        const API_URL = '/api.php';
        const tripId = <?php echo $tripId; ?>;
        const pointsConfig = <?php echo json_encode($trip['points_config'] ? json_decode($trip['points_config'], true) : null); ?>;
        
        let activeShortcut = null;
        let scanAmount = 30;
        let scanSign = 1; // 1 for addition, -1 for subtraction
        let scannerSource = 'camera';
        let html5QrcodeScanner = null;
        let currentUncleId = null;

        // Keep track of scanned kids for cooldown: { studentId: timestamp }
        const cooldowns = {};
        const COOLDOWN_DURATION = 15000; // 15 seconds

        // Scanned kids log array
        let scansLog = [];
        let searchQuery = '';

        // Toast notifications handlers
        let toastTimeout = null;
        function showToast(msg, type = 'info', duration = 3000) {
            const t = document.getElementById('toastNotify');
            if (!t) return;
            
            if (toastTimeout) {
                clearTimeout(toastTimeout);
            }
            
            t.className = 'toast-notify show ' + type;
            
            let icon = '<i class="fas fa-info-circle"></i>';
            if (type === 'success') {
                icon = '<i class="fas fa-check-circle" style="color:var(--success)"></i>';
            } else if (type === 'error') {
                icon = '<i class="fas fa-exclamation-circle" style="color:var(--danger)"></i>';
            } else if (type === 'warning') {
                icon = '<i class="fas fa-exclamation-triangle" style="color:var(--warning, #f59e0b)"></i>';
            }
            
            t.innerHTML = `${icon} <span>${msg}</span>`;
            
            if (duration > 0) {
                toastTimeout = setTimeout(() => {
                    t.classList.remove('show');
                }, duration);
            }
        }

        function dismissToast() {
            const t = document.getElementById('toastNotify');
            if (t) {
                t.classList.remove('show');
            }
            if (toastTimeout) {
                clearTimeout(toastTimeout);
            }
        }

        // External USB Barcode Scanner Input Buffer
        let barcodeBuffer = '';
        let lastKeyTime = 0;
        document.addEventListener('keydown', (e) => {
            if (scannerSource !== 'usb') return;
            
            if (document.activeElement && (document.activeElement.tagName === 'INPUT' || document.activeElement.tagName === 'TEXTAREA')) {
                return;
            }

            const now = Date.now();
            if (now - lastKeyTime > 100) {
                barcodeBuffer = '';
            }
            lastKeyTime = now;

            if (e.key === 'Enter') {
                if (barcodeBuffer.length > 0) {
                    e.preventDefault();
                    handleScannedText(barcodeBuffer);
                    barcodeBuffer = '';
                }
            } else if (e.key.length === 1) {
                barcodeBuffer += e.key;
            }
        });

        function initializeSettings() {
            const area = document.getElementById('settingsControlsArea');
            const hasShortcuts = pointsConfig && (pointsConfig.points_type === 'shortcuts' || pointsConfig.points_type === 'combined') && Array.isArray(pointsConfig.shortcuts) && pointsConfig.shortcuts.length > 0;
            
            if (pointsConfig && pointsConfig.points_type === 'shortcuts' && hasShortcuts) {
                // Render custom shortcuts only
                let html = `<div style="display:flex; flex-direction:column; gap:8px; margin-bottom:12px;">`;
                pointsConfig.shortcuts.forEach((sh, idx) => {
                    const activeClass = idx === 0 ? 'active' : '';
                    if (idx === 0) activeShortcut = sh;
                    
                    const signChar = sh.points >= 0 ? '+' : '';
                    html += `
                        <button type="button" class="option-btn shortcut-action-btn ${activeClass}" onclick="selectShortcut(${idx})" style="display:flex; align-items:center; justify-content:space-between; width:100%; padding:8px 12px; text-align:right; font-size:0.88rem;">
                            <span style="display:flex; align-items:center; gap:10px;">
                                <i class="${sh.icon || 'fas fa-star'}"></i>
                                <span style="display:flex; flex-direction:column; align-items:flex-start; text-align:right;">
                                    <span style="font-weight:700;">${sh.name}</span>
                                    ${sh.desc ? `<span style="font-size:0.72rem; opacity:0.75; font-weight:normal; display:block;">${sh.desc}</span>` : ''}
                                </span>
                            </span>
                            <span style="font-weight:900; background:var(--brand-bg); padding:2px 8px; border-radius:6px; font-size:0.8rem;">
                                ${signChar}${sh.points}
                            </span>
                        </button>
                    `;
                });
                html += `</div>`;
                area.innerHTML = html;
            } else if (pointsConfig && pointsConfig.points_type === 'combined' && hasShortcuts) {
                // Render combined layout (Shortcuts first, then compact standard direct layout)
                activeShortcut = pointsConfig.shortcuts[0];
                
                let html = `<div style="display:flex; flex-direction:column; gap:6px; margin-bottom:10px;">`;
                pointsConfig.shortcuts.forEach((sh, idx) => {
                    const activeClass = idx === 0 ? 'active' : '';
                    const signChar = sh.points >= 0 ? '+' : '';
                    html += `
                        <button type="button" class="option-btn shortcut-action-btn ${activeClass}" onclick="selectShortcut(${idx})" style="display:flex; align-items:center; justify-content:space-between; width:100%; padding:6px 10px; text-align:right; font-size:0.82rem;">
                            <span style="display:flex; align-items:center; gap:8px;">
                                <i class="${sh.icon || 'fas fa-star'}" style="font-size: 0.9rem;"></i>
                                <span style="display:flex; flex-direction:column; align-items:flex-start; text-align:right;">
                                    <span style="font-weight:700;">${sh.name}</span>
                                    ${sh.desc ? `<span style="font-size:0.68rem; opacity:0.75; font-weight:normal; display:block;">${sh.desc}</span>` : ''}
                                </span>
                            </span>
                            <span style="font-weight:900; background:var(--brand-bg); padding:2px 6px; border-radius:4px; font-size:0.78rem;">
                                ${signChar}${sh.points}
                            </span>
                        </button>
                    `;
                });
                html += `</div>`;

                // Add compact direct options
                html += `
                    <div style="margin-top: 10px; border-top: 1.5px solid var(--border-solid); padding-top: 8px;">
                        <div style="font-size:0.75rem; font-weight:700; color:var(--text-3); margin-bottom:6px;">أو نقاط مباشرة:</div>
                        <div class="sign-toggle" style="margin-bottom: 8px; padding: 2px; gap: 2px;">
                            <button type="button" class="sign-btn plus" onclick="setSign('plus')" style="padding: 6px; font-size: 0.8rem;">
                                <i class="fas fa-plus"></i> إضافة
                            </button>
                            <button type="button" class="sign-btn minus" onclick="setSign('minus')" style="padding: 6px; font-size: 0.8rem;">
                                <i class="fas fa-minus"></i> خصم
                            </button>
                        </div>
                        <div class="option-group" style="margin-bottom: 8px; gap: 4px;">
                            <button type="button" class="option-btn direct-btn" onclick="setAmount(30)" style="padding: 6px 2px; font-size: 0.88rem;">30</button>
                            <button type="button" class="option-btn direct-btn" onclick="setAmount(50)" style="padding: 6px 2px; font-size: 0.88rem;">50</button>
                            <button type="button" class="option-btn direct-btn" onclick="setAmount(80)" style="padding: 6px 2px; font-size: 0.88rem;">80</button>
                            <button type="button" class="option-btn direct-btn" onclick="setAmount(100)" style="padding: 6px 2px; font-size: 0.88rem;">100</button>
                        </div>
                    </div>
                `;
                area.innerHTML = html;
            } else {
                // Direct mode only
                activeShortcut = null;
                area.innerHTML = `
                    <div class="sign-toggle">
                        <button type="button" class="sign-btn plus active" onclick="setSign('plus')">
                            <i class="fas fa-plus"></i> إضافة نقاط
                        </button>
                        <button type="button" class="sign-btn minus" onclick="setSign('minus')">
                            <i class="fas fa-minus"></i> سحب (خصم) نقاط
                        </button>
                    </div>

                    <div class="option-group" style="margin-bottom: 12px;">
                        <button type="button" class="option-btn direct-btn active" onclick="setAmount(30)">30</button>
                        <button type="button" class="option-btn direct-btn" onclick="setAmount(50)">50</button>
                        <button type="button" class="option-btn direct-btn" onclick="setAmount(80)">80</button>
                        <button type="button" class="option-btn direct-btn" onclick="setAmount(100)">100</button>
                    </div>
                `;
            }
        }

        function selectShortcut(index) {
            activeShortcut = pointsConfig.shortcuts[index];
            
            // Set active class on shortcut buttons
            document.querySelectorAll('.shortcut-action-btn').forEach((btn, idx) => {
                if (idx === index) {
                    btn.classList.add('active');
                } else {
                    btn.classList.remove('active');
                }
            });
            
            // Remove active classes from direct buttons
            document.querySelectorAll('.direct-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.sign-btn').forEach(btn => btn.classList.remove('active'));
        }

        function setAmount(val) {
            scanAmount = val;
            activeShortcut = null;
            
            // Remove active class from shortcuts
            document.querySelectorAll('.shortcut-action-btn').forEach(btn => btn.classList.remove('active'));
            
            // Set active class on direct buttons
            document.querySelectorAll('.direct-btn').forEach(btn => {
                btn.classList.remove('active');
                if (parseInt(btn.textContent, 10) === val) {
                    btn.classList.add('active');
                }
            });
            
            // Ensure sign button is active
            if (scanSign === 1) {
                document.querySelector('.sign-btn.plus').classList.add('active');
                document.querySelector('.sign-btn.minus').classList.remove('active');
            } else {
                document.querySelector('.sign-btn.plus').classList.remove('active');
                document.querySelector('.sign-btn.minus').classList.add('active');
            }
        }

        function setSign(sign) {
            if (sign === 'plus') {
                scanSign = 1;
            } else {
                scanSign = -1;
            }
            activeShortcut = null;
            
            // Remove active class from shortcuts
            document.querySelectorAll('.shortcut-action-btn').forEach(btn => btn.classList.remove('active'));
            
            // Set active class on sign buttons
            if (scanSign === 1) {
                document.querySelector('.sign-btn.plus').classList.add('active');
                document.querySelector('.sign-btn.minus').classList.remove('active');
            } else {
                document.querySelector('.sign-btn.plus').classList.remove('active');
                document.querySelector('.sign-btn.minus').classList.add('active');
            }
            
            // Ensure direct button is active
            let hasActiveDirect = false;
            document.querySelectorAll('.direct-btn').forEach(btn => {
                if (btn.classList.contains('active')) hasActiveDirect = true;
            });
            if (!hasActiveDirect) {
                setAmount(scanAmount);
            }
        }

        function switchScannerSource(source) {
            scannerSource = source;
            document.querySelectorAll('.scanner-source-tab').forEach(btn => btn.classList.remove('active'));
            document.getElementById('tab_' + source).classList.add('active');

            const reader = document.getElementById('reader');

            if (source === 'usb') {
                // Stop camera
                if (html5QrcodeScanner) {
                    html5QrcodeScanner.stop().then(() => {}).catch(() => {});
                    html5QrcodeScanner = null;
                }
                reader.style.aspectRatio = 'initial';
                reader.style.minHeight = '140px';
                reader.style.background = 'var(--surface-2)';
                reader.style.border = '2px dashed var(--border-solid)';
                reader.style.display = 'flex';
                reader.style.flexDirection = 'column';
                reader.style.alignItems = 'center';
                reader.style.justifyContent = 'center';
                reader.style.color = 'var(--brand)';
                reader.style.gap = '10px';
                reader.style.padding = '20px';
                reader.innerHTML = `
                    <i class="fas fa-barcode" style="font-size: 3rem; opacity: 0.8;"></i>
                    <div style="font-weight: 800; font-size: 1.0rem;">الماسح الخارجي نشط</div>
                    <div style="font-size: 0.78rem; color: var(--text-3); text-align: center; max-width: 280px; line-height: 1.4;">
                        قم بتوجيه قارئ الـ QR نحو الكارت والمسح مباشرة.
                    </div>
                `;
            } else {
                reader.style.aspectRatio = '1 / 1';
                reader.style.minHeight = 'initial';
                reader.style.background = '#000';
                reader.style.border = '1px solid var(--border-solid)';
                reader.style.padding = '0';
                reader.innerHTML = '';
                startCamera();
            }
        }

        function startCamera() {
            if (html5QrcodeScanner) return;
            
            html5QrcodeScanner = new Html5Qrcode("reader");
            
            html5QrcodeScanner.start(
                { facingMode: "environment" },
                { fps: 10, qrbox: { width: 250, height: 250 } },
                (decodedText, decodedResult) => {
                    handleScannedText(decodedText);
                },
                (errorMessage) => {}
            ).catch(err => {
                showToast("حدث خطأ في فتح الكاميرا: " + err, 'error');
            });
        }

        function getKidIdFromQrText(decodedText) {
            if (!decodedText) return '';
            try {
                let cleaned = String(decodedText).trim();
                if (cleaned.includes('?') || cleaned.includes('/') || cleaned.includes('=')) {
                    if (!cleaned.startsWith('http://') && !cleaned.startsWith('https://')) {
                        if (cleaned.startsWith('/')) {
                            cleaned = window.location.origin + cleaned;
                        } else {
                            cleaned = 'https://' + cleaned;
                        }
                    }
                    try {
                        const url = new URL(cleaned);
                        const id = url.searchParams.get('student_id') ||
                                   url.searchParams.get('studentId') ||
                                   url.searchParams.get('id');
                        if (id) return id.trim();
                    } catch (urlErr) {
                        const match = cleaned.match(/[?&](student_id|studentId|id)=([^&]+)/);
                        if (match) return match[2].trim();
                    }
                }
                if (/^\d+$/.test(cleaned)) {
                    return cleaned;
                }
                return cleaned;
            } catch (e) {
                return '';
            }
        }

        let lastScannedText = '';
        let lastScannedTime = 0;

        function playSuccessSound() {
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const playNote = (freq, startTime, duration) => {
                    const osc = audioCtx.createOscillator();
                    const gain = audioCtx.createGain();
                    osc.connect(gain);
                    gain.connect(audioCtx.destination);
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(freq, startTime);
                    gain.gain.setValueAtTime(0.08, startTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);
                    osc.start(startTime);
                    osc.stop(startTime + duration);
                };
                const now = audioCtx.currentTime;
                playNote(523.25, now, 0.12); // C5
                playNote(659.25, now + 0.06, 0.12); // E5
                playNote(783.99, now + 0.12, 0.24); // G5
            } catch(e) {}
        }

        function playErrorSound() {
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(150, audioCtx.currentTime);
                gain.gain.setValueAtTime(0.08, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.3);
                osc.start();
                osc.stop(audioCtx.currentTime + 0.3);
            } catch(e) {}
        }

        async function handleScannedText(decodedText) {
            const studentId = getKidIdFromQrText(decodedText);
            if (!studentId) {
                playErrorSound();
                showToast("الكود الممسوح غير صحيح", 'error');
                return;
            }

            // Camera debounce: ignore duplicate scans of same code within 3 seconds
            const now = Date.now();
            if (studentId === lastScannedText && (now - lastScannedTime < 3000)) {
                return; // drop silently to prevent instant repeat scans
            }
            lastScannedText = studentId;
            lastScannedTime = now;

            // Check Cooldown
            if (cooldowns[studentId] && (now - cooldowns[studentId] < COOLDOWN_DURATION)) {
                playErrorSound();
                showToast(
                    `تم مسح هذا الطفل مؤخراً! <button class="toast-skip-btn" onclick="bypassCooldown('${studentId}')">skip</button>`,
                    'warning',
                    8000
                );
                return;
            }

            // Execute scan
            executeScan(studentId);
        }

        function bypassCooldown(studentId) {
            dismissToast();
            delete cooldowns[studentId];
            executeScan(studentId);
        }

        function filterScansList() {
            searchQuery = document.getElementById('scanSearchInput').value.trim().toLowerCase();
            renderScansList();
        }

        async function executeScan(studentId) {
            let changeAmount = 0;
            let actionName = '';

            if (activeShortcut) {
                changeAmount = activeShortcut.points;
                actionName = activeShortcut.name;
            } else {
                changeAmount = scanAmount * scanSign;
            }

            // Check same shortcut/amount in 15 minutes
            const now = Date.now();
            const duplicateScan = scansLog.find(item => {
                if (String(item.studentId) !== String(studentId)) return false;
                if (item.change !== changeAmount) return false;
                if (item.actionNote !== actionName) return false;
                
                if (!item.createdAt) return false;
                let logTime;
                if (item.createdAt instanceof Date) {
                    logTime = item.createdAt.getTime();
                } else {
                    logTime = new Date(item.createdAt.replace(' ', 'T')).getTime();
                }
                if (isNaN(logTime)) return false;
                const diffMs = now - logTime;
                return diffMs >= 0 && diffMs < 15 * 60 * 1000;
            });

            if (duplicateScan) {
                playErrorSound();
                let logTime;
                if (duplicateScan.createdAt instanceof Date) {
                    logTime = duplicateScan.createdAt.getTime();
                } else {
                    logTime = new Date(duplicateScan.createdAt.replace(' ', 'T')).getTime();
                }
                const elapsedMin = Math.floor((now - logTime) / 60000);
                const elapsedSec = Math.floor(((now - logTime) % 60000) / 1000);
                const agoStr = elapsedMin > 0 ? `${elapsedMin} دقيقة` : `${elapsedSec} ثانية`;
                
                const confirmMsg = `تنبيه: هذا الطفل حصل على نفس هذا الاختصار (${actionName || changeAmount}) منذ ${agoStr}. هل تريد بالتأكيد إضافة النقاط مرة أخرى؟`;
                if (!confirm(confirmMsg)) {
                    return;
                }
            }

            // Instantly append to recent list in "Processing" state to feel extremely responsive
            const tempLogId = 'temp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 4);
            const tempEntry = {
                id: tempLogId,
                studentId: studentId,
                studentName: 'جاري التحميل...',
                change: changeAmount,
                status: 'processing',
                profilePhoto: null,
                uncleId: currentUncleId,
                uncleName: 'مسوحاتي الخاصة',
                actionNote: actionName,
                createdAt: new Date()
            };
            addOrUpdateLogEntry(tempEntry);

            // Call API
            const fd = new FormData();
            fd.append('action', 'processFastScanPoints');
            fd.append('trip_id', tripId);
            fd.append('student_id', studentId);
            fd.append('amount', changeAmount);
            if (actionName) {
                fd.append('action_name', actionName);
            }

            try {
                const res = await fetch(API_URL, { method: 'POST', body: fd }).then(r => r.json());
                
                if (res.success) {
                    playSuccessSound();
                    
                    // Flash scanner green
                    const reader = document.getElementById('reader');
                    if (reader && scannerSource === 'camera') {
                        reader.style.setProperty('border', '3px solid #10b981', 'important');
                        reader.style.setProperty('box-shadow', '0 0 25px rgba(16, 185, 129, 0.7)', 'important');
                        setTimeout(() => {
                            reader.style.removeProperty('border');
                            reader.style.removeProperty('box-shadow');
                        }, 500);
                    }

                    const successEntry = {
                        ...tempEntry,
                        id: res.log_id,
                        status: 'success',
                        studentName: res.student_name,
                        new_points: res.new_points,
                        profilePhoto: res.profile_photo || tempEntry.profilePhoto,
                        createdAt: new Date()
                    };
                    
                    showToast(`تم التسجيل بنجاح: ${res.student_name} (${changeAmount > 0 ? '+' : ''}${changeAmount})`, 'success');
                    
                    // Lock student cooldown on success
                    cooldowns[studentId] = Date.now();
                    
                    // Vibrate device if supported
                    if (navigator.vibrate) {
                        navigator.vibrate([100]);
                    }
                    
                    // Update the temporary entry in scansLog by searching for tempLogId
                    const idx = scansLog.findIndex(item => item.id === tempLogId);
                    if (idx > -1) {
                        scansLog[idx] = successEntry;
                    } else {
                        scansLog.unshift(successEntry);
                    }
                    renderScansList();
                } else {
                    playErrorSound();
                    
                    // Flash scanner red
                    const reader = document.getElementById('reader');
                    if (reader && scannerSource === 'camera') {
                        reader.style.setProperty('border', '3px solid #ef4444', 'important');
                        reader.style.setProperty('box-shadow', '0 0 25px rgba(239, 68, 68, 0.7)', 'important');
                        setTimeout(() => {
                            reader.style.removeProperty('border');
                            reader.style.removeProperty('box-shadow');
                        }, 500);
                    }

                    // Remove from log list because it failed
                    scansLog = scansLog.filter(item => item.id !== tempLogId);
                    renderScansList();
                    showToast("فشل التسجيل: " + (res.message || 'خطأ'), 'error');
                }

            } catch (err) {
                playErrorSound();
                
                // Flash scanner red
                const reader = document.getElementById('reader');
                if (reader && scannerSource === 'camera') {
                    reader.style.setProperty('border', '3px solid #ef4444', 'important');
                    reader.style.setProperty('box-shadow', '0 0 25px rgba(239, 68, 68, 0.7)', 'important');
                    setTimeout(() => {
                        reader.style.removeProperty('border');
                        reader.style.removeProperty('box-shadow');
                    }, 500);
                }

                // Remove from log list because it failed
                scansLog = scansLog.filter(item => item.id !== tempLogId);
                renderScansList();
                showToast("حدث خطأ في الاتصال بالسيرفر", 'error');
            }
        }

        function formatLogTime(timeVal) {
            if (!timeVal) return '';
            let date;
            if (timeVal instanceof Date) {
                date = timeVal;
            } else {
                date = new Date(String(timeVal).replace(' ', 'T'));
            }
            if (isNaN(date.getTime())) return timeVal;

            const diffMs = Date.now() - date.getTime();
            const diffSec = Math.floor(diffMs / 1000);
            const diffMin = Math.floor(diffSec / 60);
            const diffHr = Math.floor(diffMin / 60);
            const diffDays = Math.floor(diffHr / 24);

            let timeAgo = '';
            if (diffSec < 60) {
                timeAgo = 'منذ ثوانٍ';
            } else if (diffMin < 60) {
                timeAgo = `منذ ${diffMin} د`;
            } else if (diffHr < 24) {
                timeAgo = `منذ ${diffHr} س`;
            } else {
                timeAgo = `منذ ${diffDays} يوم`;
            }

            let hours = date.getHours();
            const minutes = date.getMinutes().toString().padStart(2, '0');
            const ampm = hours >= 12 ? 'م' : 'ص';
            hours = hours % 12;
            hours = hours ? hours : 12;
            const oClock = `${hours}:${minutes} ${ampm}`;

            return `${timeAgo} (${oClock})`;
        }

        function addOrUpdateLogEntry(entry) {
            const idx = scansLog.findIndex(item => item.id === entry.id);
            if (idx > -1) {
                scansLog[idx] = entry;
            } else {
                scansLog.unshift(entry);
            }
            renderScansList();
        }

        function cancelCooldown(studentId) {
            delete cooldowns[studentId];
            showToast("تم إلغاء فترة الانتظار لهذا الطفل", 'info');
            renderScansList();
        }

        async function undoScan(logId, studentId, amount, event) {
            // Confirm undo
            const btns = document.querySelectorAll(`[data-id="${logId}"]`);
            btns.forEach(btn => btn.disabled = true);

            let clickedBtn = event ? event.currentTarget : null;
            let originalBtnHtml = '';
            if (clickedBtn) {
                originalBtnHtml = clickedBtn.innerHTML;
                clickedBtn.innerHTML = `<i class="fas fa-spinner fa-spin"></i>`;
            }

            const undoAmount = -1 * amount;

            const fd = new FormData();
            fd.append('action', 'processFastScanPoints');
            fd.append('trip_id', tripId);
            fd.append('student_id', studentId);
            fd.append('amount', undoAmount);
            fd.append('undoing_log_id', logId);

            try {
                const res = await fetch(API_URL, { method: 'POST', body: fd }).then(r => r.json());
                if (clickedBtn) {
                    clickedBtn.innerHTML = originalBtnHtml;
                }
                if (res.success) {
                    showToast(`تم التراجع بنجاح!`, 'success');
                    // Remove this scan from list
                    scansLog = scansLog.filter(item => String(item.id) !== String(logId));
                    delete cooldowns[studentId];
                    renderScansList();
                } else {
                    showToast("فشل التراجع: " + res.message, 'error');
                    btns.forEach(btn => btn.disabled = false);
                }
            } catch (e) {
                if (clickedBtn) {
                    clickedBtn.innerHTML = originalBtnHtml;
                }
                showToast("حدث خطأ أثناء الاتصال بالسيرفر للتراجع", 'error');
                btns.forEach(btn => btn.disabled = false);
            }
        }

        function renderScanItemHTML(item) {
            let badgeClass = item.change > 0 ? 'plus' : 'minus';
            let formattedChange = item.change > 0 ? `+${item.change}` : item.change;
            
            let isCooldownActive = cooldowns[item.studentId] && (Date.now() - cooldowns[item.studentId] < COOLDOWN_DURATION);
            
            let statusHtml = '';
            if (item.status === 'processing') {
                statusHtml = `<i class="fas fa-spinner fa-spin" style="color:var(--brand);"></i> جاري التسجيل...`;
            } else if (item.status === 'error') {
                statusHtml = `<span style="color:var(--danger);"><i class="fas fa-exclamation-triangle"></i> ${item.errorMsg || 'فشل'}</span>`;
            } else {
                // Show scanning uncle's name if it's in the "All scans" feed and not logged by me
                const uncleSuffix = (item.uncleId !== currentUncleId && item.uncleName) 
                    ? ` <span style="font-size:0.7rem;color:var(--text-3);background:var(--surface-3);padding:2px 6px;border-radius:4px;margin-right:6px;"><i class="fas fa-user-tie"></i> ${item.uncleName}</span>` 
                    : '';
                const noteSuffix = item.actionNote 
                    ? ` <span style="font-size:0.7rem;color:var(--brand);background:var(--brand-bg);padding:2px 6px;border-radius:4px;margin-right:6px;"><i class="fas fa-info-circle"></i> ${item.actionNote}</span>`
                    : '';
                const timeStr = item.createdAt ? ` <span style="font-size:0.7rem;color:var(--text-3);margin-right:6px;"><i class="far fa-clock"></i> ${formatLogTime(item.createdAt)}</span>` : '';
                statusHtml = `<span style="color:var(--success);"><i class="fas fa-check-circle"></i> تم الحفظ</span>${timeStr}${noteSuffix}${uncleSuffix}`;
            }

            let photoHtml = item.profilePhoto 
                ? `<img src="${item.profilePhoto}" class="scan-photo" onerror="this.outerHTML='<div class=\'scan-photo\'><i class=\'fas fa-user\'></i></div>'">`
                : `<div class="scan-photo"><i class="fas fa-user"></i></div>`;

            let actionButtons = '';
            // Only allow undo/cooldown management for my own scans!
            if (item.uncleId === currentUncleId || currentUncleId === null) {
                if (item.status === 'success') {
                    actionButtons += `
                        <button class="scan-action-btn undo" data-id="${item.id}" onclick="undoScan('${item.id}', '${item.studentId}', ${item.change}, event)" title="تراجع عن هذه العملية">
                            <i class="fas fa-undo"></i> تراجع
                        </button>
                    `;
                }
                if (isCooldownActive) {
                    actionButtons += `
                        <button class="scan-action-btn" onclick="cancelCooldown('${item.studentId}')" title="السماح بالمسح الفوري للطفل">
                            <i class="fas fa-bolt"></i> إلغاء الانتظار
                        </button>
                    `;
                }
            }

            return `
                <div class="scan-item" onclick="if(!event.target.closest('button')) window.location.href='/uncle/trip/points/?trip_id=${tripId}&student_id=${item.studentId}'" style="cursor: pointer;">
                    ${photoHtml}
                    <div class="scan-info">
                        <div class="scan-name">${item.studentName}</div>
                        <div class="scan-status">${statusHtml}</div>
                    </div>
                    <div style="display:flex; flex-direction:column; align-items:flex-end; gap:8px;">
                        <div class="scan-badge ${badgeClass}">${formattedChange}</div>
                        <div class="scan-actions">
                            ${actionButtons}
                        </div>
                    </div>
                </div>
            `;
        }

        function renderScansList() {
            const myListEl = document.getElementById('myScansList');
            const allListEl = document.getElementById('allScansList');
            const myCountEl = document.getElementById('myCount');
            const allCountEl = document.getElementById('allCount');

            // Apply search filter
            const filtered = scansLog.filter(item => {
                if (!searchQuery) return true;
                return item.studentName.toLowerCase().includes(searchQuery) || 
                       String(item.studentId).includes(searchQuery);
            });

            // Split scans
            const myScans = filtered.filter(item => item.uncleId === currentUncleId);
            const allScans = filtered;

            myCountEl.textContent = myScans.length + ' طفل';
            allCountEl.textContent = allScans.length + ' طفل';

            // Render My Scans
            if (myScans.length === 0) {
                myListEl.innerHTML = `
                    <div style="text-align:center;padding:30px 10px;color:var(--text-3);font-size:0.85rem;">
                        <i class="fas fa-user" style="font-size: 1.6rem; margin-bottom: 6px; opacity: 0.5;"></i>
                        <div>لا توجد مسوحات خاصة بك.</div>
                    </div>
                `;
            } else {
                myListEl.innerHTML = myScans.map(renderScanItemHTML).join('');
            }

            // Render All Scans
            if (allScans.length === 0) {
                allListEl.innerHTML = `
                    <div style="text-align:center;padding:30px 10px;color:var(--text-3);font-size:0.85rem;">
                        <i class="fas fa-users" style="font-size: 1.6rem; margin-bottom: 6px; opacity: 0.5;"></i>
                        <div>لا توجد مسوحات للرحلة.</div>
                    </div>
                `;
            } else {
                allListEl.innerHTML = allScans.map(renderScanItemHTML).join('');
            }
        }

        async function loadRecentScans() {
            try {
                const res = await fetch(`${API_URL}?action=getRecentTripPointsScans&trip_id=${tripId}`).then(r => r.json());
                if (res.success && res.scans) {
                    currentUncleId = res.current_uncle_id;

                    // Collect all log_ids that were undone
                    const undoneLogIds = new Set();
                    res.scans.forEach(s => {
                        if (s.reason && (s.reason.startsWith('trip_points_scan_undo:') || s.reason.startsWith('trip_points_normal_undo:'))) {
                            const parts = s.reason.split(':');
                            if (parts.length >= 3) {
                                const originalLogId = parseInt(parts[2], 10);
                                if (!isNaN(originalLogId)) {
                                    undoneLogIds.add(originalLogId);
                                }
                            }
                        }
                    });

                    // Filter out undone logs and the undo log entries themselves
                    const activeScans = res.scans.filter(s => {
                        // Exclude the undo entries
                        if (s.reason && (s.reason.startsWith('trip_points_scan_undo:') || s.reason.startsWith('trip_points_normal_undo:'))) {
                            return false;
                        }
                        // Exclude original entries that were undone
                        if (undoneLogIds.has(s.log_id)) {
                            return false;
                        }
                        return true;
                    });

                    scansLog = activeScans.map(s => {
                        let actionNote = '';
                        if (s.reason) {
                            const parts = s.reason.split(':');
                            if (parts.length >= 3) {
                                actionNote = parts.slice(2).join(':');
                            }
                        }
                        return {
                            id: s.log_id,
                            studentId: s.student_id,
                            studentName: s.student_name,
                            change: s.change_amount,
                            status: 'success',
                            profilePhoto: s.profile_photo,
                            uncleId: s.uncle_id,
                            uncleName: s.uncle_name,
                            actionNote: actionNote,
                            createdAt: s.created_at
                        };
                    });
                }
            } catch (e) {
                console.error("Error loading recent scans:", e);
            }
            renderScansList();
        }

        // Start default scanner (camera) on load
        document.addEventListener('DOMContentLoaded', () => {
            initializeSettings();
            startCamera();
            loadRecentScans();
        });
    </script>
</body>
</html>
