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
$tstmt = $conn->prepare("SELECT title, church_id, collaborating_churches FROM trips WHERE id = ? LIMIT 1");
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
    <title>الماسح السريع للكوبونات | مدارس الأحد</title>
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
            max-width: 600px;
            margin: 0 auto;
            padding: 16px;
            padding-bottom: 40px;
        }

        .header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
        }

        .back-btn {
            width: 40px;
            height: 40px;
            border-radius: 12px;
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
            font-size: 1.2rem;
            font-weight: 800;
            flex: 1;
        }

        .card {
            background: var(--surface);
            border-radius: 20px;
            border: 1.5px solid var(--border-solid);
            padding: 20px;
            box-shadow: var(--shadow-sm);
            margin-bottom: 16px;
        }

        .card-title {
            font-size: 1.0rem;
            font-weight: 800;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* Amount Selector group */
        .option-group {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 8px;
            margin-bottom: 16px;
        }

        .option-btn {
            padding: 12px 6px;
            border-radius: 12px;
            border: 2px solid var(--border-solid);
            background: var(--surface-2);
            color: var(--text-2);
            font-weight: 800;
            font-size: 1.05rem;
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
            border-radius: 12px;
            margin-bottom: 20px;
            gap: 4px;
        }

        .sign-btn {
            flex: 1;
            padding: 10px;
            border-radius: 8px;
            border: none;
            background: none;
            font-weight: 800;
            font-size: 0.95rem;
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
            margin-bottom: 12px;
            gap: 4px;
        }

        .scanner-source-tab {
            flex: 1;
            padding: 8px;
            border-radius: 8px;
            border: none;
            background: none;
            font-weight: 700;
            font-size: 0.82rem;
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
            border-radius: 16px;
            overflow: hidden;
            border: 1.5px solid var(--border-solid);
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
        
        <div class="header">
            <a href="/uncle/trip/?trip_id=<?php echo $tripId; ?>" class="back-btn" title="عودة">
                <i class="fas fa-arrow-right"></i>
            </a>
            <div class="header-title">
                <div style="font-size: 0.72rem; color: var(--text-3); font-weight: 700;">الماسح السريع للكوبونات</div>
                <div style="font-weight: 800; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($tripTitle); ?></div>
            </div>
        </div>

        <!-- Settings Card -->
        <div class="card">
            <div class="card-title">
                <i class="fas fa-cog text-brand"></i> إعدادات المسح
            </div>

            <!-- Sign Toggle (Plus / Minus) -->
            <div class="sign-toggle">
                <button type="button" class="sign-btn plus active" onclick="setSign('plus')">
                    <i class="fas fa-plus"></i> إضافة كوبونات
                </button>
                <button type="button" class="sign-btn minus" onclick="setSign('minus')">
                    <i class="fas fa-minus"></i> سحب (خصم) كوبونات
                </button>
            </div>

            <!-- Options Grid -->
            <div class="option-group">
                <button type="button" class="option-btn active" onclick="setAmount(10)">10</button>
                <button type="button" class="option-btn" onclick="setAmount(30)">30</button>
                <button type="button" class="option-btn" onclick="setAmount(50)">50</button>
                <button type="button" class="option-btn" onclick="setAmount(100)">100</button>
            </div>

            <!-- Cooldown Toggle Switch -->
            <div class="cooldown-wrap">
                <div class="cooldown-label">
                    <i class="fas fa-history" style="color: var(--brand);"></i>
                    <div>
                        <div>حماية التكرار (فترة انتظار 15 ثانية)</div>
                        <div style="font-size:0.7rem;color:var(--text-3);font-weight:600;margin-top:2px;">يمنع مسح الكود مرتين بالخطأ خلال فترة قصيرة.</div>
                    </div>
                </div>
                <label class="switch">
                    <input type="checkbox" id="cooldownToggle" checked>
                    <span class="slider"></span>
                </label>
            </div>
        </div>

        <!-- Scanner Card -->
        <div class="card" style="padding: 12px;">
            <!-- Source selection (Camera vs external scanner) -->
            <div class="scanner-source-tabs">
                <button type="button" id="tab_camera" class="scanner-source-tab active" onclick="switchScannerSource('camera')">
                    <i class="fas fa-camera"></i> الكاميرا
                </button>
                <button type="button" id="tab_usb" class="scanner-source-tab" onclick="switchScannerSource('usb')">
                    <i class="fas fa-barcode"></i> ماسح خارجي (USB/بلوتوث)
                </button>
            </div>

            <div id="reader"></div>
        </div>

        <!-- Scans Log -->
        <div class="scans-log-title">
            <span>آخر عمليات المسح</span>
            <span style="font-size: 0.75rem; color: var(--text-3); font-weight: 700;" id="logCount">0 طفل</span>
        </div>

        <div class="scans-list" id="scansList">
            <!-- Scanned items will appear here -->
        </div>

    </div>

    <!-- Toast Notification -->
    <div class="toast-notify" id="toastNotify"></div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

    <script>
        const API_URL = '/api.php';
        const tripId = <?php echo $tripId; ?>;
        
        let scanAmount = 10;
        let scanSign = 1; // 1 for addition, -1 for subtraction
        let scannerSource = 'camera';
        let html5QrcodeScanner = null;

        // Keep track of scanned kids for cooldown: { studentId: timestamp }
        const cooldowns = {};
        const COOLDOWN_DURATION = 15000; // 15 seconds

        // Scanned kids log array
        let scansLog = [];

        function setAmount(val) {
            scanAmount = val;
            document.querySelectorAll('.option-btn').forEach(btn => {
                btn.classList.remove('active');
                if (parseInt(btn.textContent, 10) === val) {
                    btn.classList.add('active');
                }
            });
        }

        function setSign(sign) {
            if (sign === 'plus') {
                scanSign = 1;
                document.querySelector('.sign-btn.plus').classList.add('active');
                document.querySelector('.sign-btn.minus').classList.remove('active');
            } else {
                scanSign = -1;
                document.querySelector('.sign-btn.plus').classList.remove('active');
                document.querySelector('.sign-btn.minus').classList.add('active');
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
                reader.style.minHeight = '180px';
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
                reader.style.minHeight = '280px';
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
                const cleaned = String(decodedText).trim();
                if (cleaned.includes('://') || cleaned.includes('?')) {
                    const url = new URL(cleaned, window.location.origin);
                    const id = url.searchParams.get('student_id') ||
                               url.searchParams.get('studentId') ||
                               url.searchParams.get('id');
                    if (id) return id.trim();
                }
                if (/^\d+$/.test(cleaned)) {
                    return cleaned;
                }
                return cleaned;
            } catch (e) {
                return '';
            }
        }

        async function handleScannedText(decodedText) {
            const studentId = getKidIdFromQrText(decodedText);
            if (!studentId) {
                showToast("الكود الممسوح غير صحيح", 'error');
                return;
            }

            // Check Cooldown
            const useCooldown = document.getElementById('cooldownToggle').checked;
            const now = Date.now();
            if (useCooldown && cooldowns[studentId] && (now - cooldowns[studentId] < COOLDOWN_DURATION)) {
                const remaining = Math.ceil((COOLDOWN_DURATION - (now - cooldowns[studentId])) / 1000);
                showToast(`تم مسح هذا الكارت للتو! انتظر ${remaining} ثانية أو ألغِ الانتظار`, 'warning');
                return;
            }

            // Lock student cooldown
            cooldowns[studentId] = now;

            const changeAmount = scanAmount * scanSign;

            // Instantly append to recent list in "Processing" state to feel extremely responsive
            const tempLogId = 'temp_' + Date.now() + '_' + Math.random().toString(36).substr(2, 4);
            const tempEntry = {
                id: tempLogId,
                studentId: studentId,
                studentName: 'جاري التحميل...',
                change: changeAmount,
                status: 'processing',
                profilePhoto: null
            };
            addOrUpdateLogEntry(tempEntry);

            // Call API
            const fd = new FormData();
            fd.append('action', 'processFastScanCoupon');
            fd.append('trip_id', tripId);
            fd.append('student_id', studentId);
            fd.append('amount', changeAmount);

            try {
                const res = await fetch(API_URL, { method: 'POST', body: fd }).then(r => r.json());
                
                if (res.success) {
                    tempEntry.status = 'success';
                    tempEntry.studentName = res.student_name;
                    tempEntry.new_coupons = res.new_coupons;
                    showToast(`تم التسجيل بنجاح: ${res.student_name} (${changeAmount > 0 ? '+' : ''}${changeAmount})`, 'success');
                    
                    // Vibrate device if supported
                    if (navigator.vibrate) {
                        navigator.vibrate([100]);
                    }
                } else {
                    tempEntry.status = 'error';
                    tempEntry.studentName = 'فشل المسح';
                    tempEntry.errorMsg = res.message || 'خطأ غير معروف';
                    showToast("فشل التسجيل: " + (res.message || 'خطأ'), 'error');
                    
                    // Remove from cooldown on failure
                    delete cooldowns[studentId];
                }
                
                addOrUpdateLogEntry(tempEntry);

            } catch (err) {
                tempEntry.status = 'error';
                tempEntry.studentName = 'فشل المسح';
                tempEntry.errorMsg = 'حدث خطأ في الشبكة';
                showToast("حدث خطأ في الاتصال بالسيرفر", 'error');
                
                delete cooldowns[studentId];
                addOrUpdateLogEntry(tempEntry);
            }
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

        async function undoScan(logId, studentId, amount) {
            // Confirm undo
            const btn = document.querySelector(`[data-id="${logId}"]`);
            if (btn) btn.disabled = true;

            const undoAmount = -1 * amount;

            const fd = new FormData();
            fd.append('action', 'processFastScanCoupon');
            fd.append('trip_id', tripId);
            fd.append('student_id', studentId);
            fd.append('amount', undoAmount);

            try {
                const res = await fetch(API_URL, { method: 'POST', body: fd }).then(r => r.json());
                if (res.success) {
                    showToast(`تم التراجع بنجاح!`, 'success');
                    // Remove this scan from list
                    scansLog = scansLog.filter(item => item.id !== logId);
                    delete cooldowns[studentId];
                    renderScansList();
                } else {
                    showToast("فشل التراجع: " + res.message, 'error');
                    if (btn) btn.disabled = false;
                }
            } catch (e) {
                showToast("حدث خطأ أثناء الاتصال بالسيرفر للتراجع", 'error');
                if (btn) btn.disabled = false;
            }
        }

        function renderScansList() {
            const listEl = document.getElementById('scansList');
            const countEl = document.getElementById('logCount');

            countEl.textContent = scansLog.length + ' طفل';

            if (scansLog.length === 0) {
                listEl.innerHTML = `
                    <div style="text-align:center;padding:40px;color:var(--text-3);font-size:0.9rem;">
                        <i class="fas fa-barcode" style="font-size: 2rem; margin-bottom: 8px; opacity: 0.5;"></i>
                        <div>لم يتم مسح أي كروت بعد.</div>
                    </div>
                `;
                return;
            }

            listEl.innerHTML = scansLog.map(item => {
                let badgeClass = item.change > 0 ? 'plus' : 'minus';
                let formattedChange = item.change > 0 ? `+${item.change}` : item.change;
                
                let isCooldownActive = cooldowns[item.studentId] && (Date.now() - cooldowns[item.studentId] < COOLDOWN_DURATION);
                
                let statusHtml = '';
                if (item.status === 'processing') {
                    statusHtml = `<i class="fas fa-spinner fa-spin" style="color:var(--brand);"></i> جاري التسجيل...`;
                } else if (item.status === 'error') {
                    statusHtml = `<span style="color:var(--danger);"><i class="fas fa-exclamation-triangle"></i> ${item.errorMsg || 'فشل'}</span>`;
                } else {
                    statusHtml = `<span style="color:var(--success);"><i class="fas fa-check-circle"></i> تم الحفظ بنجاح</span>`;
                }

                let photoHtml = item.profilePhoto 
                    ? `<img src="${item.profilePhoto}" class="scan-photo" onerror="this.outerHTML='<div class=\'scan-photo\'><i class=\'fas fa-user\'></i></div>'">`
                    : `<div class="scan-photo"><i class="fas fa-user"></i></div>`;

                let actionButtons = '';
                if (item.status === 'success') {
                    actionButtons += `
                        <button class="scan-action-btn undo" data-id="${item.id}" onclick="undoScan('${item.id}', '${item.studentId}', ${item.change})" title="تراجع عن هذه العملية">
                            <i class="fas fa-undo"></i> تراجع
                        </button>
                    `;
                }
                if (isCooldownActive && document.getElementById('cooldownToggle').checked) {
                    actionButtons += `
                        <button class="scan-action-btn" onclick="cancelCooldown('${item.studentId}')" title="السماح بالمسح الفوري للطفل">
                            <i class="fas fa-bolt"></i> إلغاء الانتظار
                        </button>
                    `;
                }

                return `
                    <div class="scan-item">
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
            }).join('');
        }

        function showToast(message, type = 'info') {
            const toast = document.getElementById('toastNotify');
            let icon = '<i class="fas fa-info-circle"></i>';
            if (type === 'success') {
                icon = '<i class="fas fa-check-circle" style="color:var(--success);"></i>';
            } else if (type === 'error') {
                icon = '<i class="fas fa-exclamation-circle" style="color:var(--danger);"></i>';
            } else if (type === 'warning') {
                icon = '<i class="fas fa-exclamation-triangle" style="color:#f59e0b;"></i>';
            }
            
            toast.innerHTML = `${icon} <span>${message}</span>`;
            toast.classList.add('show');

            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }

        // Keyboard USB Barcode Scanner Handler
        (function() {
            let scanBuffer = '';
            let lastKeyTime = 0;
            const SCAN_TIMEOUT = 50; // ms

            document.addEventListener('keydown', function(e) {
                if (e.ctrlKey || e.altKey || e.metaKey) return;
                
                // If focus is in input fields, do not capture barcode unless in USB mode
                const activeTag = document.activeElement ? document.activeElement.tagName.toLowerCase() : '';
                const isEditing = activeTag === 'input' || activeTag === 'textarea';
                if (isEditing && scannerSource !== 'usb') return;

                const now = Date.now();
                if (e.key.length === 1) {
                    if (scanBuffer === '' || (now - lastKeyTime) < SCAN_TIMEOUT) {
                        scanBuffer += e.key;
                        lastKeyTime = now;
                    } else {
                        scanBuffer = e.key;
                        lastKeyTime = now;
                    }
                } else if (e.key === 'Enter') {
                    if (scanBuffer.length > 0) {
                        e.preventDefault();
                        handleScannedText(scanBuffer);
                        scanBuffer = '';
                    }
                }
            });
        })();

        // Start default scanner (camera) on load
        document.addEventListener('DOMContentLoaded', () => {
            startCamera();
            renderScansList();
        });
    </script>
</body>
</html>
