<?php
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);
ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 365 * 10);

$isTestingEnv = (strpos($_SERVER['REQUEST_URI'], '/testing/') !== false || strpos($_SERVER['SCRIPT_NAME'], '/testing/') !== false);
$pathPrefix = $isTestingEnv ? '/testing' : '';

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
    header("Location: " . $pathPrefix . "/login/");
    exit();
}

$configRoot = __DIR__;
while ($configRoot && !file_exists($configRoot . '/api.php')) {
    $configParent = dirname($configRoot);
    if ($configParent === $configRoot) {
        break;
    }
    $configRoot = $configParent;
}
$isTesting = (strpos($configRoot, '/testing') !== false);
$configName = $isTesting ? 'config-testing.php' : 'config.php';

if (file_exists($configRoot . '/' . $configName)) {
    require_once $configRoot . '/' . $configName;
} elseif (file_exists(dirname($configRoot) . '/' . $configName)) {
    require_once dirname($configRoot) . '/' . $configName;
} else {
    require_once $configRoot . '/config.php';
}

$tripId = intval($_GET['trip_id'] ?? 0);
if ($tripId <= 0) {
    header("Location: " . $pathPrefix . "/uncle/dashboard/");
    exit();
}

$conn = getDBConnection();
$tstmt = $conn->prepare("SELECT title FROM trips WHERE id = ? LIMIT 1");
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
    <title>شاشة الترحيب بالأطفال | مدارس الأحد</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --brand: #6366f1;
            --brand-glow: rgba(99, 102, 241, 0.25);
            --success: #10b981;
            --success-glow: rgba(16, 185, 129, 0.25);
            --danger: #ef4444;
            --danger-glow: rgba(239, 68, 68, 0.25);
            --text-dark: #1e293b;
            --text-muted: #64748b;
            --bg-light: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 50%, #e2e8f0 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Cairo', sans-serif;
        }

        body, html {
            width: 100%;
            height: 100%;
            overflow: hidden;
            background: var(--bg-light);
            color: var(--text-dark);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        /* Screen Wrapper */
        .screen-wrap {
            position: relative;
            z-index: 10;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        /* Start Overlay */
        .overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            padding: 20px;
            direction: rtl;
        }

        .overlay-card {
            background: rgba(255, 255, 255, 0.95);
            border: 2px solid rgba(99, 102, 241, 0.15);
            border-radius: 32px;
            padding: 40px 30px;
            text-align: center;
            max-width: 450px;
            width: 100%;
            box-shadow: 0 20px 50px rgba(99, 102, 241, 0.12);
        }

        .overlay-title {
            font-size: 1.8rem;
            font-weight: 900;
            margin-bottom: 12px;
            color: var(--brand);
        }

        .overlay-desc {
            font-size: 0.95rem;
            color: var(--text-muted);
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .start-btn {
            background: linear-gradient(135deg, var(--brand), #7c3aed);
            color: white;
            border: none;
            padding: 16px 36px;
            border-radius: 20px;
            font-weight: 800;
            font-size: 1.25rem;
            cursor: pointer;
            box-shadow: 0 10px 25px var(--brand-glow);
            transition: all 0.25s;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            width: 100%;
            justify-content: center;
        }

        .start-btn:hover {
            transform: scale(1.03) translateY(-2px);
            box-shadow: 0 15px 30px rgba(99, 102, 241, 0.35);
        }

        .start-btn:active {
            transform: scale(0.98);
        }

        /* Top Info bar */
        .top-info {
            position: absolute;
            top: 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            z-index: 5;
        }

        .top-title {
            font-size: 1.8rem;
            font-weight: 900;
            color: var(--text-dark);
            text-shadow: 0 2px 10px rgba(255, 255, 255, 0.6);
        }

        /* Idle State Panel */
        .idle-panel {
            text-align: center;
            animation: pulse 2.5s infinite alternate ease-in-out;
            transition: opacity 0.5s ease, transform 0.5s ease;
            z-index: 5;
        }

        .idle-panel.hidden {
            opacity: 0;
            transform: scale(0.9);
            pointer-events: none;
        }

        .idle-icon {
            font-size: 5rem;
            color: var(--brand);
            margin-bottom: 20px;
            filter: drop-shadow(0 4px 10px rgba(99, 102, 241, 0.2));
        }

        .idle-text {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text-dark);
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 0.65; }
            100% { transform: scale(1.05); opacity: 0.95; }
        }

        /* Welcome Greeting Card */
        .welcome-card {
            background: rgba(255, 255, 255, 0.85);
            border: 2px solid rgba(255, 255, 255, 0.5);
            border-radius: 40px;
            padding: 36px;
            max-width: 440px;
            width: 90%;
            text-align: center;
            box-shadow: 0 30px 70px rgba(99, 102, 241, 0.15), inset 0 2px 0 rgba(255,255,255,0.6);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            position: absolute;
            z-index: 100;
            opacity: 0;
            transform: translateY(60px) scale(0.85);
            pointer-events: none;
            direction: rtl;
        }

        .welcome-card.show {
            animation: cardEntrance 0.8s cubic-bezier(0.19, 1, 0.22, 1) forwards, cardFloat 4s ease-in-out infinite alternate 0.8s;
            pointer-events: auto;
        }

        @keyframes cardEntrance {
            0% {
                opacity: 0;
                transform: translateY(80px) scale(0.7) rotate(-3deg);
                filter: blur(10px);
            }
            50% {
                opacity: 0.8;
                transform: translateY(-10px) scale(1.04) rotate(1deg);
                filter: none;
            }
            70% {
                transform: translateY(4px) scale(0.98) rotate(-0.5deg);
            }
            100% {
                opacity: 1;
                transform: translateY(0) scale(1) rotate(0deg);
            }
        }

        @keyframes cardFloat {
            0% {
                transform: translateY(0) rotate(0);
            }
            100% {
                transform: translateY(-10px) rotate(0.5deg);
            }
        }

        /* Gold/Green style for Additions */
        .welcome-card.style-plus {
            border-color: rgba(16, 185, 129, 0.25);
            box-shadow: 0 30px 60px rgba(16, 185, 129, 0.15), inset 0 2px 0 rgba(255,255,255,0.6);
        }

        /* Red style for Subtractions */
        .welcome-card.style-minus {
            border-color: rgba(239, 68, 68, 0.25);
            box-shadow: 0 30px 60px rgba(239, 68, 68, 0.15), inset 0 2px 0 rgba(255,255,255,0.6);
        }

        .student-photo {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid rgba(255, 255, 255, 0.8);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.1);
            margin: 0 auto 24px;
            display: block;
            background: #f8fafc;
        }

        .welcome-card.style-plus .student-photo {
            border-color: var(--success);
            box-shadow: 0 10px 25px var(--success-glow);
        }

        .welcome-card.style-minus .student-photo {
            border-color: var(--danger);
            box-shadow: 0 10px 25px var(--danger-glow);
        }

        .congrats-title {
            font-size: 1.25rem;
            color: var(--brand);
            font-weight: 800;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }

        .student-name {
            font-size: 2rem;
            font-weight: 900;
            color: var(--text-dark);
            margin-bottom: 18px;
            line-height: 1.4;
        }

        .message-body {
            font-size: 1.4rem;
            font-weight: 800;
            padding: 10px 24px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .welcome-card.style-plus .message-body {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1.5px solid rgba(16, 185, 129, 0.15);
        }

        .welcome-card.style-minus .message-body {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1.5px solid rgba(239, 68, 68, 0.15);
        }

        /* Confetti particles container */
        .confetti-container {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            pointer-events: none;
            z-index: 50;
        }

        /* Scattered Cards Styling */
        .scattered-card {
            position: absolute;
            background: rgba(255, 255, 255, 0.95);
            border: 3px solid #fff;
            border-radius: 24px;
            padding: 8px;
            width: 120px;
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.08);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            z-index: 10;
            transition: all 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            animation: gentleFloat 6s infinite alternate ease-in-out;
        }

        .scattered-photo {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid rgba(255,255,255,0.8);
            margin-bottom: 4px;
        }

        .scattered-name {
            font-size: 0.72rem;
            font-weight: 800;
            color: var(--text-dark);
            max-width: 100px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .scattered-badge {
            font-size: 0.65rem;
            font-weight: 900;
            padding: 1px 6px;
            border-radius: 10px;
            margin-top: 3px;
        }

        .scattered-badge.plus {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }

        .scattered-badge.minus {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }

        @keyframes gentleFloat {
            0% { transform: translate(0, 0) rotate(0deg); }
            100% { transform: translate(var(--float-x, 10px), var(--float-y, -10px)) rotate(var(--float-r, 2deg)); }
        }
    </style>
</head>
<body>

    <!-- Start Overlay (User interaction to enable audio/fullscreen) -->
    <div class="overlay" id="startOverlay">
        <div class="overlay-card">
            <h2 class="overlay-title" style="margin-top:0;">شاشة عرض الترحيب</h2>
            <p class="overlay-desc">اضغط على الزر أدناه لتفعيل المؤثرات الصوتية والعرض التلقائي بملء الشاشة عند مسح النقاط.</p>
            <button type="button" class="start-btn" onclick="startWelcomeScreen()">
                <i class="fas fa-play"></i> بدء الشاشة
            </button>
        </div>
    </div>

    <div class="screen-wrap">
        
        <div class="top-info">
            <h1 class="top-title"><?php echo htmlspecialchars($tripTitle); ?></h1>
        </div>

        <!-- Scattered Recent Cards Container -->
        <div id="scatterContainer" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; overflow: hidden; pointer-events: none; z-index: 8;"></div>

        <!-- Idle Panel -->
        <div class="idle-panel" id="idlePanel">
        </div>

        <!-- Welcome Greeting Card -->
        <div class="welcome-card" id="welcomeCard">
            <img src="" class="student-photo" id="studentPhoto" alt="">
            <div class="congrats-title" id="congratsTitle">مبروووك! 🎉</div>
            <div class="student-name" id="studentName"></div>
            <div>
                <div class="message-body" id="messageBody"></div>
            </div>
        </div>

        <!-- Confetti Canvas -->
        <canvas class="confetti-container" id="confettiCanvas"></canvas>

    </div>

    <script>
        const API_URL = '/api.php';
        const tripId = <?php echo $tripId; ?>;
        
        let lastProcessedLogId = 0;
        let isOverlayActive = false;
        let displayQueue = [];
        let pollingInterval = null;

        // Web Audio Context for synthesized sound effect
        let audioCtx = null;

        function initAudio() {
            if (!audioCtx) {
                audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            }
        }

        function playSuccessSound() {
            if (!audioCtx) return;
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }
            
            const now = audioCtx.currentTime;
            
            // Premium chime sound: harmonious arpeggio arced E5 -> G5 -> C6
            playTone(659.25, now, 0.15); 
            playTone(783.99, now + 0.08, 0.25);
            playTone(1046.50, now + 0.16, 0.4);
        }

        function playFailureSound() {
            if (!audioCtx) return;
            if (audioCtx.state === 'suspended') {
                audioCtx.resume();
            }
            
            const now = audioCtx.currentTime;
            
            // Descent buzz-like alert tone: G4 -> E4
            playTone(392.00, now, 0.15); 
            playTone(329.63, now + 0.08, 0.25);
        }

        function playTone(freq, startTime, duration) {
            try {
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                
                osc.type = 'sine';
                osc.frequency.setValueAtTime(freq, startTime);
                
                // Prevent clicking sound at the start and end by setting soft envelope curve
                gain.gain.setValueAtTime(0, startTime);
                gain.gain.linearRampToValueAtTime(0.12, startTime + 0.02);
                gain.gain.exponentialRampToValueAtTime(0.0001, startTime + duration);
                
                osc.start(startTime);
                osc.stop(startTime + duration);
            } catch (e) {
                console.error("Audio play error: ", e);
            }
        }

        async function startWelcomeScreen() {
            // Dismiss overlay
            document.getElementById('startOverlay').style.display = 'none';
            
            // Request fullscreen on start
            try {
                if (document.documentElement.requestFullscreen) {
                    await document.documentElement.requestFullscreen();
                } else if (document.documentElement.webkitRequestFullscreen) {
                    await document.documentElement.webkitRequestFullscreen();
                }
            } catch (e) {
                console.warn("Fullscreen request failed: ", e);
            }
            
            // Initialize audio
            initAudio();
            
            // Play a gentle welcome tone to confirm sound is active
            if (audioCtx) {
                const now = audioCtx.currentTime;
                playTone(523.25, now, 0.15); // C5
                playTone(659.25, now + 0.1, 0.25); // E5
            }
        }

        // Start display polling automatically on page load
        document.addEventListener('DOMContentLoaded', async () => {
            // Load latest scan log ID on load so we don't replay past items
            try {
                const res = await fetch(`${API_URL}?action=getLatestTripPointsScan&trip_id=${tripId}`).then(r => r.json());
                if (res.success && res.latest) {
                    lastProcessedLogId = res.latest.log_id;
                }
            } catch (e) {
                console.error("Error fetching initial state: ", e);
            }

            // Start polling database every 800ms
            pollingInterval = setInterval(pollScanEvents, 800);
        });

        async function pollScanEvents() {
            try {
                const res = await fetch(`${API_URL}?action=getLatestTripPointsScan&trip_id=${tripId}`).then(r => r.json());
                
                if (res.success && res.latest) {
                    const latest = res.latest;
                    if (latest.log_id > lastProcessedLogId) {
                        lastProcessedLogId = latest.log_id;
                        
                        // Push scan event to the display queue
                        displayQueue.push(latest);
                        
                        // Process queue if not busy
                        processDisplayQueue();
                    }
                }
            } catch (e) {
                console.error("Polling error: ", e);
            }
        }

        let currentKidData = null; // Store the currently displayed kid's data
        let activeScatteredKids = [];

        function processDisplayQueue() {
            if (isOverlayActive || displayQueue.length === 0) return;

            isOverlayActive = true;
            const event = displayQueue.shift();

            const card = document.getElementById('welcomeCard');
            const idle = document.getElementById('idlePanel');

            // If a card is already shown, scatter it first as it gets replaced
            if (card.classList.contains('show')) {
                if (currentKidData) {
                    addKidToScatter(currentKidData);
                    currentKidData = null; // Prevent double scattering in showNewEvent
                }
                card.classList.remove('show');
                stopConfetti();
                setTimeout(() => {
                    showNewEvent(event);
                }, 400); // Wait for fade out to complete
            } else {
                idle.classList.add('hidden');
                showNewEvent(event);
            }
        }

        function showNewEvent(event) {
            const card = document.getElementById('welcomeCard');
            const nameEl = document.getElementById('studentName');
            const photoEl = document.getElementById('studentPhoto');
            const titleEl = document.getElementById('congratsTitle');
            const bodyEl = document.getElementById('messageBody');

            // Save the new kid's data
            currentKidData = {
                student_id: event.student_id,
                student_name: event.student_name,
                profile_photo: event.profile_photo,
                change_amount: event.change_amount
            };

            // Set styles based on points change sign
            const isAdd = event.change_amount > 0;
            card.className = 'welcome-card show ' + (isAdd ? 'style-plus' : 'style-minus');
            titleEl.textContent = isAdd ? 'تهانينااا! 🎉' : 'سحب نقاط ⭐️';
            
            // Set message texts
            const countStr = Math.abs(event.change_amount);
            let arabicTerm = 'نقطة';
            if (countStr >= 3 && countStr <= 10) {
                arabicTerm = 'نقاط';
            }
            
            bodyEl.innerHTML = isAdd 
                ? `<i class="fas fa-star"></i> مبروك عليك ${countStr} ${arabicTerm}!`
                : `<i class="fas fa-star"></i> تم سحب ${countStr} ${arabicTerm}`;

            nameEl.textContent = event.student_name;
            
            // Flicker-free photo load: hide image and only show it once loaded
            photoEl.style.display = 'none';
            let finalPhotoSrc = '/profile_default.webp';
            if (event.profile_photo) {
                finalPhotoSrc = event.profile_photo;
                photoEl.src = event.profile_photo;
                photoEl.onload = () => {
                    photoEl.style.display = 'block';
                };
                photoEl.onerror = () => {
                    photoEl.src = '/logo.png';
                    photoEl.style.display = 'block';
                    finalPhotoSrc = '/logo.png';
                };
            } else {
                photoEl.src = '/profile_default.webp';
                photoEl.onload = () => {
                    photoEl.style.display = 'block';
                };
                photoEl.onerror = () => {
                    photoEl.src = '/logo.png';
                    photoEl.style.display = 'block';
                    finalPhotoSrc = '/logo.png';
                };
            }

            // Confetti, fireworks and sound effects on points change
            if (isAdd) {
                playSuccessSound();
                
                // Launch initial fireworks rockets
                launchFirework();
                setTimeout(launchFirework, 150);
                setTimeout(launchFirework, 300);
                setTimeout(launchFirework, 450);
                setTimeout(launchFirework, 600);
                
                startConfetti();
            } else {
                playFailureSound();
            }

            // Transition logic: stay on screen unless there's a new card waiting
            if (displayQueue.length > 0) {
                // If there are more events in queue, wait 5 seconds then proceed to next
                setTimeout(() => {
                    isOverlayActive = false;
                    processDisplayQueue();
                }, 5000);
            } else {
                // Keep the card on screen indefinitely
                isOverlayActive = false;
            }
        }

        // Helper to locate non-overlapping random positions outside the center welcome card area
        function getRandomPosition(cardWidth, cardHeight) {
            const screenW = window.innerWidth;
            const screenH = window.innerHeight;
            
            // Center exclusion zone bounds
            const centerXMin = screenW * 0.3;
            const centerXMax = screenW * 0.7;
            const centerYMin = screenH * 0.2;
            const centerYMax = screenH * 0.8;
            
            let x, y;
            let attempts = 0;
            let overlaps;
            const buffer = 20; // 20px buffer spacing between cards
            
            do {
                x = Math.random() * (screenW - cardWidth - 40) + 20;
                y = Math.random() * (screenH - cardHeight - 120) + 60;
                attempts++;
                
                // Check if it overlaps with the center exclusion zone
                const overlapsCenter = (
                    x + cardWidth > centerXMin && 
                    x < centerXMax && 
                    y + cardHeight > centerYMin && 
                    y < centerYMax
                );
                
                overlaps = overlapsCenter;
                
                if (!overlaps) {
                    // Check if it overlaps with any active scattered cards
                    for (const activeCard of activeScatteredKids) {
                        const ax = activeCard.x;
                        const ay = activeCard.y;
                        const aw = cardWidth;
                        const ah = cardHeight;
                        
                        if (
                            x + cardWidth + buffer > ax &&
                            x < ax + aw + buffer &&
                            y + cardHeight + buffer > ay &&
                            y < ay + ah + buffer
                        ) {
                            overlaps = true;
                            break;
                        }
                    }
                }
            } while (overlaps && attempts < 100);
            
            return { x, y };
        }

        // Spawns and animates a small scattered card representing a recent scan
        function addKidToScatter(kidData) {
            const container = document.getElementById('scatterContainer');
            
            const card = document.createElement('div');
            card.className = 'scattered-card';
            
            const isAdd = kidData.change_amount > 0;
            card.style.borderColor = isAdd ? 'var(--success)' : 'var(--danger)';
            
            const badgeClass = isAdd ? 'plus' : 'minus';
            const badgeText = isAdd ? `+${kidData.change_amount}` : `${kidData.change_amount}`;
            
            card.innerHTML = `
                <img src="${kidData.profile_photo || '/profile_default.webp'}" class="scattered-photo" onerror="this.src='/logo.png'">
                <div class="scattered-name">${kidData.student_name}</div>
                <div class="scattered-badge ${badgeClass}">${badgeText}</div>
            `;
            
            card.style.setProperty('--float-x', `${Math.random() * 20 - 10}px`);
            card.style.setProperty('--float-y', `${Math.random() * 20 - 10}px`);
            card.style.setProperty('--float-r', `${Math.random() * 6 - 3}deg`);
            
            card.style.position = 'absolute';
            card.style.left = '50%';
            card.style.top = '50%';
            card.style.transform = 'translate(-50%, -50%) scale(0.1)';
            card.style.opacity = '0';
            
            container.appendChild(card);
            
            const cardW = 120;
            const cardH = 110;
            const pos = getRandomPosition(cardW, cardH);
            
            card.offsetWidth; // Force reflow
            
            card.style.left = `${pos.x}px`;
            card.style.top = `${pos.y}px`;
            card.style.transform = `translate(0, 0) scale(1) rotate(${Math.random() * 12 - 6}deg)`;
            card.style.opacity = '1';
            
            activeScatteredKids.push({ element: card, x: pos.x, y: pos.y });
            
            if (activeScatteredKids.length > 8) {
                const oldest = activeScatteredKids.shift();
                oldest.element.style.opacity = '0';
                oldest.element.style.transform += ' scale(0.8)';
                setTimeout(() => {
                    oldest.element.remove();
                }, 800);
            }
        }

        // Scatter mini kids photo effect
        function createScatterEffect(photoSrc) {
            const container = document.querySelector('.screen-wrap');
            const count = 18;
            
            for (let i = 0; i < count; i++) {
                const img = document.createElement('img');
                img.src = photoSrc || '/profile_default.webp';
                img.className = 'scatter-avatar';
                
                // Random starting size
                const size = Math.random() * 50 + 60; // 60px to 110px
                img.style.width = `${size}px`;
                img.style.height = `${size}px`;
                
                // Position in the center
                img.style.position = 'absolute';
                img.style.left = '50%';
                img.style.top = '50%';
                img.style.transform = 'translate(-50%, -50%) scale(1.2)';
                img.style.borderRadius = '50%';
                img.style.border = '3px solid #fff';
                img.style.boxShadow = '0 0 15px rgba(255,255,255,0.7)';
                img.style.zIndex = '110';
                img.style.pointerEvents = 'none';
                img.style.objectFit = 'cover';
                
                container.appendChild(img);
                
                // Random angles and distance
                const angle = Math.random() * Math.PI * 2;
                const distance = Math.random() * 320 + 160; // 160px to 480px
                const destX = Math.cos(angle) * distance;
                const destY = Math.sin(angle) * distance;
                
                // Web Animations API for smooth scatter & shrink (gets smaller randomly)
                img.animate([
                    {
                        transform: 'translate(-50%, -50%) scale(1.3) translate(0, 0)',
                        opacity: 1
                    },
                    {
                        transform: `translate(-50%, -50%) scale(0.02) translate(${destX}px, ${destY}px) rotate(${Math.random() * 720 - 360}deg)`,
                        opacity: 0
                    }
                ], {
                    duration: 1200 + Math.random() * 600, // 1.2s to 1.8s
                    easing: 'cubic-bezier(0.1, 0.8, 0.25, 1)',
                    fill: 'forwards'
                });
                
                // Clean up DOM element
                setTimeout(() => {
                    img.remove();
                }, 2000);
            }
        }

        /* Confetti & Fireworks Canvas Animation */
        const canvas = document.getElementById('confettiCanvas');
        const ctx = canvas.getContext('2d');
        let particles = [];
        let isConfettiRunning = false;
        let fireworksRockets = [];
        let fireworksParticles = [];

        function resizeCanvas() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }
        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

        class FireworkRocket {
            constructor(startX, startY, targetX, targetY, color) {
                this.x = startX;
                this.y = startY;
                this.targetX = targetX;
                this.targetY = targetY;
                this.color = color;
                this.speed = 7;
                this.angle = Math.atan2(targetY - startY, targetX - startX);
                this.velocity = {
                    x: Math.cos(this.angle) * this.speed,
                    y: Math.sin(this.angle) * this.speed
                };
                this.trail = [];
                this.trailLength = 6;
            }

            update() {
                this.trail.push({ x: this.x, y: this.y });
                if (this.trail.length > this.trailLength) {
                    this.trail.shift();
                }

                this.x += this.velocity.x;
                this.y += this.velocity.y;

                if (this.y <= this.targetY) {
                    return true;
                }
                return false;
            }

            draw() {
                ctx.beginPath();
                if (this.trail.length > 0) {
                    ctx.moveTo(this.trail[0].x, this.trail[0].y);
                    ctx.lineTo(this.x, this.y);
                } else {
                    ctx.moveTo(this.x, this.y);
                    ctx.lineTo(this.x - this.velocity.x, this.y - this.velocity.y);
                }
                ctx.strokeStyle = this.color;
                ctx.lineWidth = 3.5;
                ctx.stroke();
            }
        }

        class FireworkParticle {
            constructor(x, y, color) {
                this.x = x;
                this.y = y;
                this.color = color;
                this.angle = Math.random() * Math.PI * 2;
                this.speed = Math.random() * 4 + 1.5;
                this.velocity = {
                    x: Math.cos(this.angle) * this.speed,
                    y: Math.sin(this.angle) * this.speed
                };
                this.gravity = 0.04;
                this.friction = 0.95;
                this.alpha = 1;
                this.decay = Math.random() * 0.006 + 0.004;
            }

            update() {
                this.velocity.x *= this.friction;
                this.velocity.y *= this.friction;
                this.velocity.y += this.gravity;
                this.x += this.velocity.x;
                this.y += this.velocity.y;
                this.alpha -= this.decay;
            }

            draw() {
                ctx.save();
                ctx.globalAlpha = this.alpha;
                ctx.beginPath();
                ctx.arc(this.x, this.y, Math.random() * 2 + 1.5, 0, Math.PI * 2);
                ctx.fillStyle = this.color;
                ctx.fill();
                ctx.restore();
            }
        }

        function launchFirework() {
            const startX = Math.random() * canvas.width;
            const startY = canvas.height;
            const targetX = Math.random() * (canvas.width * 0.7) + (canvas.width * 0.15);
            const targetY = Math.random() * (canvas.height * 0.35) + (canvas.height * 0.15);
            const colors = ["#5b6cf5", "#10b981", "#fbbf24", "#ef4444", "#ec4899", "#8b5cf6"];
            const color = colors[Math.floor(Math.random() * colors.length)];
            
            fireworksRockets.push(new FireworkRocket(startX, startY, targetX, targetY, color));
        }

        function triggerFireworkExplosion(x, y, color) {
            const count = 50;
            for (let i = 0; i < count; i++) {
                fireworksParticles.push(new FireworkParticle(x, y, color));
            }
        }

        class Particle {
            constructor() {
                this.x = Math.random() * canvas.width;
                this.y = Math.random() * canvas.height - canvas.height;
                this.r = Math.random() * 8 + 4;
                this.d = Math.random() * canvas.height;
                this.color = ["#5b6cf5", "#10b981", "#fbbf24", "#ef4444", "#ec4899", "#8b5cf6"][Math.floor(Math.random() * 6)];
                this.tilt = Math.random() * 10 - 5;
                this.tiltAngleChan = (Math.random() * 0.05 + 0.02) * 0.4;
                this.tiltAngle = 0;
            }

            draw() {
                ctx.beginPath();
                ctx.lineWidth = this.r / 2;
                ctx.strokeStyle = this.color;
                ctx.moveTo(this.x + this.tilt + this.r / 2, this.y);
                ctx.lineTo(this.x + this.tilt, this.y + this.tilt + this.r / 2);
                ctx.stroke();
            }

            update() {
                this.tiltAngle += this.tiltAngleChan;
                this.y += (Math.cos(this.d) + 3 + this.r / 2) * 0.15;
                this.tilt = Math.sin(this.tiltAngle - this.r / 2) * 12;

                // Loop particles
                if (this.y > canvas.height) {
                    this.y = -10;
                    this.x = Math.random() * canvas.width;
                }
            }
        }

        function startConfetti() {
            particles = [];
            for (let i = 0; i < 90; i++) {
                particles.push(new Particle());
            }
            isConfettiRunning = true;
            animateConfetti();
        }

        function stopConfetti() {
            isConfettiRunning = false;
        }

        function animateConfetti() {
            if (!isConfettiRunning && particles.length === 0 && fireworksRockets.length === 0 && fireworksParticles.length === 0) {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                return;
            }

            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            // Confetti
            for (let i = 0; i < particles.length; i++) {
                particles[i].update();
                particles[i].draw();
            }

            if (!isConfettiRunning) {
                particles = particles.filter(p => p.y > 0);
            }

            // Fireworks Rockets
            for (let i = fireworksRockets.length - 1; i >= 0; i--) {
                const rocket = fireworksRockets[i];
                const exploded = rocket.update();
                rocket.draw();
                if (exploded) {
                    triggerFireworkExplosion(rocket.targetX, rocket.targetY, rocket.color);
                    fireworksRockets.splice(i, 1);
                }
            }

            // Fireworks Particles
            for (let i = fireworksParticles.length - 1; i >= 0; i--) {
                const p = fireworksParticles[i];
                p.update();
                p.draw();
                if (p.alpha <= 0) {
                    fireworksParticles.splice(i, 1);
                }
            }

            requestAnimationFrame(animateConfetti);
        }
    </script>
</body>
</html>
