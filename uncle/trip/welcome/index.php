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
            --brand: #5b6cf5;
            --brand-glow: rgba(91, 108, 245, 0.4);
            --success: #10b981;
            --success-glow: rgba(16, 185, 129, 0.4);
            --danger: #ef4444;
            --danger-glow: rgba(239, 68, 68, 0.4);
            --text-light: #f3f4f6;
            --text-muted: #9ca3af;
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
            background: #0b0c10;
            color: var(--text-light);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }

        /* Ambient glowing circles */
        .ambient-glow {
            position: absolute;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            filter: blur(140px);
            opacity: 0.15;
            pointer-events: none;
            z-index: 0;
            animation: moveGlow 25s infinite alternate ease-in-out;
        }

        .glow-1 {
            background: var(--brand);
            top: -10%;
            left: -10%;
        }

        .glow-2 {
            background: #8b5cf6;
            bottom: -10%;
            right: -10%;
            animation-delay: -5s;
        }

        @keyframes moveGlow {
            0% { transform: translate(0, 0) scale(1); }
            50% { transform: translate(100px, 80px) scale(1.2); }
            100% { transform: translate(-50px, -60px) scale(0.9); }
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
            background: rgba(11, 12, 16, 0.95);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            padding: 20px;
            direction: rtl;
        }

        .overlay-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 24px;
            padding: 40px 30px;
            text-align: center;
            max-width: 450px;
            width: 100%;
            box-shadow: 0 30px 60px rgba(0,0,0,0.5);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
        }

        .overlay-title {
            font-size: 1.6rem;
            font-weight: 900;
            margin-bottom: 12px;
            color: #fff;
        }

        .overlay-desc {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 30px;
            line-height: 1.6;
        }

        .start-btn {
            background: linear-gradient(135deg, var(--brand), #7c3aed);
            color: white;
            border: none;
            padding: 16px 36px;
            border-radius: 16px;
            font-weight: 800;
            font-size: 1.15rem;
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
            box-shadow: 0 15px 30px rgba(91, 108, 245, 0.5);
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
            font-size: 1.4rem;
            font-weight: 900;
            color: #fff;
            text-shadow: 0 2px 10px rgba(0,0,0,0.5);
        }

        .top-subtitle {
            font-size: 0.82rem;
            color: var(--text-muted);
            font-weight: 700;
            margin-top: 4px;
            background: rgba(255,255,255,0.05);
            padding: 4px 16px;
            border-radius: 20px;
            border: 1px solid rgba(255,255,255,0.05);
        }

        /* Idle State Panel */
        .idle-panel {
            text-align: center;
            animation: pulse 2.5s infinite alternate ease-in-out;
            transition: opacity 0.5s ease, transform 0.5s ease;
        }

        .idle-panel.hidden {
            opacity: 0;
            transform: scale(0.9);
            pointer-events: none;
        }

        .idle-icon {
            font-size: 4rem;
            color: var(--brand);
            margin-bottom: 20px;
            filter: drop-shadow(0 0 15px var(--brand-glow));
        }

        .idle-text {
            font-size: 1.3rem;
            font-weight: 800;
            color: var(--text-muted);
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 0.6; }
            100% { transform: scale(1.05); opacity: 0.95; }
        }

        /* Welcome Greeting Card */
        .welcome-card {
            background: rgba(255, 255, 255, 0.03);
            border: 1.5px solid rgba(255, 255, 255, 0.08);
            border-radius: 36px;
            padding: 40px;
            max-width: 500px;
            width: 90%;
            text-align: center;
            box-shadow: 0 40px 100px rgba(0,0,0,0.6), inset 0 1px 0 rgba(255,255,255,0.1);
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            position: absolute;
            z-index: 100;
            opacity: 0;
            transform: translateY(60px) scale(0.85);
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            pointer-events: none;
            direction: rtl;
        }

        .welcome-card.show {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        /* Gold/Green style for Additions */
        .welcome-card.style-plus {
            border-color: rgba(16, 185, 129, 0.25);
            box-shadow: 0 40px 100px rgba(0,0,0,0.6), 0 0 40px rgba(16, 185, 129, 0.15), inset 0 1px 0 rgba(255,255,255,0.1);
        }

        /* Red style for Subtractions */
        .welcome-card.style-minus {
            border-color: rgba(239, 68, 68, 0.25);
            box-shadow: 0 40px 100px rgba(0,0,0,0.6), 0 0 40px rgba(239, 68, 68, 0.15), inset 0 1px 0 rgba(255,255,255,0.1);
        }

        .student-photo {
            width: 140px;
            height: 140px;
            border-radius: 50%;
            object-fit: cover;
            border: 5px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 10px 25px rgba(0,0,0,0.4);
            margin: 0 auto 24px;
            display: block;
            background: #181b26;
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
            font-size: 1.1rem;
            color: var(--text-muted);
            font-weight: 700;
            margin-bottom: 6px;
            letter-spacing: 0.5px;
        }

        .student-name {
            font-size: 1.8rem;
            font-weight: 900;
            color: #fff;
            margin-bottom: 18px;
            line-height: 1.4;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
        }

        .message-body {
            font-size: 1.3rem;
            font-weight: 800;
            padding: 10px 20px;
            border-radius: 20px;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 10px;
        }

        .welcome-card.style-plus .message-body {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.15);
        }

        .welcome-card.style-minus .message-body {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.15);
        }

        /* Confetti particles container */
        .confetti-container {
            position: absolute;
            top: 0; left: 0; width: 100%; height: 100%;
            pointer-events: none;
            z-index: 50;
        }

        /* Sync Indicator */
        .sync-indicator {
            position: absolute;
            bottom: 24px;
            font-size: 0.75rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
            background: rgba(255,255,255,0.02);
            border: 1px solid rgba(255,255,255,0.05);
            padding: 6px 14px;
            border-radius: 20px;
        }

        .sync-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--success);
            box-shadow: 0 0 8px var(--success-glow);
            animation: pulseDot 1.5s infinite ease-in-out;
        }

        @keyframes pulseDot {
            0% { transform: scale(1); opacity: 0.6; }
            50% { transform: scale(1.3); opacity: 1; }
            100% { transform: scale(1); opacity: 0.6; }
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

    <!-- Ambient glowing backgrounds -->
    <div class="ambient-glow glow-1"></div>
    <div class="ambient-glow glow-2"></div>

    <div class="screen-wrap">
        
        <div class="top-info">
            <h1 class="top-title"><?php echo htmlspecialchars($tripTitle); ?></h1>
            <div class="top-subtitle">شاشة الترحيب بالأطفال تلقائياً</div>
        </div>

        <!-- Idle Panel -->
        <div class="idle-panel" id="idlePanel">
            <i class="fas fa-qrcode idle-icon"></i>
            <div class="idle-text">في انتظار مسح الكروت...</div>
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

        <!-- Sync Indicator -->
        <div class="sync-indicator">
            <div class="sync-dot"></div>
            <span>متصل ويزامن تلقائياً</span>
        </div>

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

        function processDisplayQueue() {
            if (isOverlayActive || displayQueue.length === 0) return;

            isOverlayActive = true;
            const event = displayQueue.shift();

            const card = document.getElementById('welcomeCard');
            const idle = document.getElementById('idlePanel');

            // If a card is already shown, fade it out first to prevent flickers
            if (card.classList.contains('show')) {
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
            if (event.profile_photo) {
                photoEl.src = event.profile_photo;
                photoEl.onload = () => {
                    photoEl.style.display = 'block';
                };
                photoEl.onerror = () => {
                    photoEl.src = '/logo.png';
                    photoEl.style.display = 'block';
                };
            } else {
                photoEl.src = '/profile_default.webp';
                photoEl.onload = () => {
                    photoEl.style.display = 'block';
                };
                photoEl.onerror = () => {
                    photoEl.src = '/logo.png';
                    photoEl.style.display = 'block';
                };
            }

            // Confetti and sound effects on points change
            if (isAdd) {
                playSuccessSound();
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

        /* Confetti Animation */
        const canvas = document.getElementById('confettiCanvas');
        const ctx = canvas.getContext('2d');
        let particles = [];
        let isConfettiRunning = false;

        function resizeCanvas() {
            canvas.width = window.innerWidth;
            canvas.height = window.innerHeight;
        }
        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

        class Particle {
            constructor() {
                this.x = Math.random() * canvas.width;
                this.y = Math.random() * canvas.height - canvas.height;
                this.r = Math.random() * 8 + 4;
                this.d = Math.random() * canvas.height;
                this.color = ["#5b6cf5", "#10b981", "#fbbf24", "#ef4444", "#ec4899", "#8b5cf6"][Math.floor(Math.random() * 6)];
                this.tilt = Math.random() * 10 - 5;
                this.tiltAngleChan = Math.random() * 0.05 + 0.02;
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
                this.y += (Math.cos(this.d) + 3 + this.r / 2) / 2;
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
            if (!isConfettiRunning && particles.length === 0) {
                ctx.clearRect(0, 0, canvas.width, canvas.height);
                return;
            }

            ctx.clearRect(0, 0, canvas.width, canvas.height);
            
            for (let i = 0; i < particles.length; i++) {
                particles[i].update();
                particles[i].draw();
            }

            // Gradually decrease particles count when stopped
            if (!isConfettiRunning) {
                particles = particles.filter(p => p.y > 0);
            }

            requestAnimationFrame(animateConfetti);
        }
    </script>
</body>
</html>
