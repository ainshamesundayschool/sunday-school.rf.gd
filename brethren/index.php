<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>منصة الأخوة - الصفحة الرئيسية</title>
    
    <!-- Google Fonts: Cairo & Baloo Bhaijaan 2 -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+Bhaijaan+2:wght@400;500;600;700;800&family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --brand: #5b6cf5;
            --brand-dark: #4354e8;
            --brand-light: #a5b0ff;
            --brand-bg: #eef0ff;
            --brand-glow: rgba(91, 108, 245, .18);

            --success: #10b981;
            --success-bg: #d1fae5;
            --warning: #f59e0b;
            --warning-bg: #fef3c7;
            
            --bg: #f3f4f9;
            --surface: #ffffff;
            --surface-2: #f7f8fc;
            --surface-3: #eceef7;
            --border-solid: #e4e6f0;
            --text: #1a1d2e;
            --text-2: #4b5068;
            --text-3: #8b90a8;

            --shadow-sm: 0 2px 8px -2px rgba(0, 0, 0, .06);
            --shadow-md: 0 8px 24px -4px rgba(0, 0, 0, .08);
            --shadow-lg: 0 20px 48px -8px rgba(0, 0, 0, .12);
            
            --r-sm: 10px;
            --r-md: 14px;
            --r-lg: 18px;
            --r-xl: 24px;
            --r-full: 9999px;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Cairo', 'Baloo Bhaijaan 2', system-ui, -apple-system, sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .container {
            max-width: 960px;
            margin: 0 auto;
            padding: 20px 16px;
            width: 100%;
        }

        /* Topbar & Navigation Menu */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 300;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-solid);
            padding: 12px 20px;
            border-radius: var(--r-xl);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-sm);
            flex-wrap: wrap;
            gap: 12px;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.25rem;
            font-weight: 900;
            color: var(--text);
            text-decoration: none;
        }

        .brand-logo-img {
            width: 40px;
            height: 40px;
            object-fit: contain;
        }

        .main-menu {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .menu-link {
            background: var(--surface-2);
            color: var(--text-2);
            padding: 8px 16px;
            border-radius: var(--r-md);
            text-decoration: none;
            font-weight: 800;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            gap: 6px;
            border: 1px solid var(--border-solid);
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .menu-link:hover, .menu-link.active {
            background: var(--brand-bg);
            color: var(--brand-dark);
            border-color: rgba(91, 108, 245, 0.25);
        }

        .nav-btn {
            background: var(--brand);
            color: #fff;
            padding: 9px 20px;
            border-radius: var(--r-md);
            text-decoration: none;
            font-weight: 800;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s ease;
            box-shadow: 0 4px 14px var(--brand-glow);
        }

        .nav-btn:hover {
            background: var(--brand-dark);
            transform: translateY(-1px);
        }

        /* Hero Section */
        .hero-section {
            background: var(--surface);
            border: 1px solid var(--border-solid);
            border-radius: var(--r-xl);
            padding: 40px 24px;
            text-align: center;
            margin-bottom: 24px;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }

        .hero-section::before {
            content: '';
            position: absolute;
            top: 0; right: 0; width: 100%; height: 5px;
            background: linear-gradient(90deg, var(--brand), #8b5cf6, var(--warning));
        }

        .hero-badge-logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 16px;
            display: block;
            object-fit: contain;
        }

        .hero-title {
            font-size: 2.1rem;
            font-weight: 900;
            color: var(--text);
            margin-bottom: 12px;
            line-height: 1.25;
        }

        .hero-subtitle {
            font-size: 1rem;
            color: var(--text-2);
            font-weight: 600;
            margin-bottom: 24px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            line-height: 1.6;
        }

        .hero-cta-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--brand);
            color: #fff;
            padding: 14px 32px;
            border-radius: var(--r-lg);
            font-size: 1.05rem;
            font-weight: 900;
            text-decoration: none;
            box-shadow: 0 6px 20px var(--brand-glow);
            transition: all 0.2s ease;
        }

        .hero-cta-btn:hover {
            background: var(--brand-dark);
            transform: scale(1.03);
        }

        /* Auth Status Banner */
        .auth-status-banner {
            background: var(--brand-bg);
            border: 1px solid rgba(91, 108, 245, 0.25);
            padding: 14px 20px;
            border-radius: var(--r-lg);
            display: none;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
        }

        .auth-status-info {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 800;
            color: var(--brand-dark);
        }

        /* Section Header & Events Tab Content */
        .section-title-bar {
            display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px;
        }

        .section-title-bar h2 {
            font-size: 1.2rem; font-weight: 900; color: var(--text); display: flex; align-items: center; gap: 8px;
        }

        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }

        .event-card {
            background: var(--surface);
            border: 1px solid var(--border-solid);
            border-radius: var(--r-lg);
            padding: 18px;
            box-shadow: var(--shadow-sm);
            position: relative;
        }

        .event-card-title { font-size: 1.1rem; font-weight: 900; color: var(--text); margin-bottom: 6px; }
        .event-card-date { font-size: 0.82rem; color: var(--text-3); display: flex; align-items: center; gap: 6px; margin-bottom: 8px; font-weight: 600; }
        .event-card-desc { font-size: 0.85rem; color: var(--text-2); line-height: 1.4; }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }

        .feature-card {
            background: var(--surface);
            border: 1px solid var(--border-solid);
            border-radius: var(--r-xl);
            padding: 22px 18px;
            text-align: center;
            box-shadow: var(--shadow-sm);
        }

        .feature-icon-wrapper {
            width: 50px; height: 50px; border-radius: var(--r-lg);
            background: var(--brand-bg); color: var(--brand-dark);
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 1.3rem; margin-bottom: 14px; border: 1px solid rgba(91, 108, 245, 0.2);
        }

        .feature-title { font-size: 1.1rem; font-weight: 900; color: var(--text); margin-bottom: 6px; }
        .feature-desc { font-size: 0.85rem; color: var(--text-2); line-height: 1.5; font-weight: 600; }

        .landing-footer {
            text-align: center;
            padding: 20px 16px;
            color: var(--text-3);
            font-size: 0.85rem;
            font-weight: 700;
            border-top: 1px solid var(--border-solid);
            background: var(--surface);
        }

        .tab-section { display: none; }
        .tab-section.active { display: block; animation: fadeIn 0.2s ease; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

    <div class="container">
        <!-- Top Navbar with Main Navigation Menu -->
        <header class="topbar">
            <a href="#" id="homeBrandLink" class="brand">
                <img src="/assets/brethren-logo.png" id="homeLogoImg" class="brand-logo-img" alt="Logo">
                <span>منصة الأخوة</span>
            </a>

            <!-- Main Menu Tabs -->
            <nav class="main-menu">
                <button class="menu-link active" onclick="switchHomeTab('homeSection')">
                    <i class="fas fa-home"></i> الرئيسية
                </button>
                <button class="menu-link" onclick="switchHomeTab('eventsSection')">
                    <i class="fas fa-calendar-alt"></i> الفعاليات المتاحة
                </button>
            </nav>

            <div>
                <a href="#" id="loginNavLink" class="nav-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>تسجيل الدخول / حساب جديد</span>
                </a>
            </div>
        </header>

        <!-- Auth Status Banner -->
        <div class="auth-status-banner" id="authStatusBanner">
            <div class="auth-status-info">
                <i class="fas fa-user-circle" style="font-size:1.3rem;"></i>
                <span id="authStatusText">مرحباً بك!</span>
            </div>
            <a href="#" id="userProfileDirectLink" class="nav-btn">
                <span id="authStatusBtnLabel">عرض ملفك</span>
                <i class="fas fa-arrow-left"></i>
            </a>
        </div>

        <!-- HOME TAB SECTION -->
        <div class="tab-section active" id="homeSection">
            <!-- Hero Section -->
            <div class="hero-section">
                <img src="/assets/brethren-logo.png" id="heroBadgeImg" class="hero-badge-logo" alt="Logo">
                <h1 class="hero-title">منصة الأخوة</h1>
                <p class="hero-subtitle">
                    منصة متكاملة لمتابعة الفعاليات، تسجيل الحضور إلكترونياً عبر رمز QR المخصص، وجمع النقاط والمكافآت الشخصية بسهولة.
                </p>
                <a href="#" id="heroLoginBtn" class="hero-cta-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>تسجيل الدخول / إنشاء حساب جديد</span>
                </a>
            </div>

            <!-- Features Highlights -->
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon-wrapper">
                        <i class="fas fa-qrcode"></i>
                    </div>
                    <h3 class="feature-title">رمز QR مخصص لكل عضو</h3>
                    <p class="feature-desc">كود استجابة سريعة فريد وخاص بكل عضو يسهل تسجيل الحضور في الفعاليات فورياً.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon-wrapper">
                        <i class="fas fa-star" style="color:var(--warning);"></i>
                    </div>
                    <h3 class="feature-title">نظام النقاط والمكافآت</h3>
                    <p class="feature-desc">إضافة تلقائية للنقاط عند كل حضور وتتبع سجل النقاط والمكافآت في ملفك.</p>
                </div>

                <div class="feature-card">
                    <div class="feature-icon-wrapper">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <h3 class="feature-title">متابعة الفعاليات والأنشطة</h3>
                    <p class="feature-desc">استعراض كافة الفعاليات المتاحة ومتابعة الأنشطة القادمة فور إعلانها.</p>
                </div>
            </div>
        </div>

        <!-- EVENTS TAB SECTION -->
        <div class="tab-section" id="eventsSection">
            <div class="section-title-bar">
                <h2><i class="fas fa-calendar-star" style="color:var(--brand);"></i> جميع الفعاليات المتاحة</h2>
            </div>
            <div class="events-grid" id="homeEventsGrid">
                <div style="text-align:center;padding:24px;color:var(--text-3);grid-column:1/-1;">جاري تحميل الفعاليات...</div>
            </div>
        </div>
    </div>

    <footer class="landing-footer">
        جميع الحقوق محفوظة &copy; منصة الأخوة 2026
    </footer>

    <script>
        const isBrethrenSubfolder = window.location.pathname.includes('/brethren');
        const API_URL = isBrethrenSubfolder ? '/brethren/api.php' : '/api.php';
        const LOGIN_URL = isBrethrenSubfolder ? '/brethren/login/' : '/login/';
        const ADMIN_URL = isBrethrenSubfolder ? '/brethren/admin/' : '/admin/';
        const USER_URL = isBrethrenSubfolder ? '/brethren/user/' : '/user/';
        const HOME_URL = isBrethrenSubfolder ? '/brethren/' : '/';
        const LOGO_SRC = isBrethrenSubfolder ? '/brethren/assets/brethren-logo.png' : '/assets/brethren-logo.png';

        document.getElementById('homeBrandLink').href = HOME_URL;
        document.getElementById('loginNavLink').href = LOGIN_URL;
        document.getElementById('heroLoginBtn').href = LOGIN_URL;
        document.getElementById('homeLogoImg').src = LOGO_SRC;
        document.getElementById('heroBadgeImg').src = LOGO_SRC;

        document.addEventListener('DOMContentLoaded', () => {
            checkActiveAuth();
            fetchHomeEvents();
        });

        function switchHomeTab(sectionId) {
            document.querySelectorAll('.menu-link').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-section').forEach(sec => sec.classList.remove('active'));
            event.currentTarget.classList.add('active');
            document.getElementById(sectionId).classList.add('active');
        }

        async function fetchHomeEvents() {
            try {
                const res = await fetch(`${API_URL}?action=get_events`);
                const data = await res.json();
                const grid = document.getElementById('homeEventsGrid');
                if (data.status === 'success' && data.events && data.events.length > 0) {
                    grid.innerHTML = data.events.map(ev => `
                        <div class="event-card">
                            <div class="event-card-title">${ev.event_name}</div>
                            <div class="event-card-date"><i class="far fa-calendar-alt"></i> ${ev.event_date}</div>
                            ${ev.description ? `<div class="event-card-desc">${ev.description}</div>` : ''}
                        </div>
                    `).join('');
                } else {
                    grid.innerHTML = `<div style="text-align:center;padding:24px;color:var(--text-3);grid-column:1/-1;">لا توجد فعاليات متاحة حالياً</div>`;
                }
            } catch (err) {}
        }

        async function checkActiveAuth() {
            const activeId = localStorage.getItem('brethren_active_user_id');
            const isAdmin = localStorage.getItem('brethren_is_admin') === 'true';

            if (isAdmin) {
                const banner = document.getElementById('authStatusBanner');
                document.getElementById('authStatusText').innerText = `أهلاً بك يا مسئول النظام!`;
                document.getElementById('authStatusBtnLabel').innerText = `دخول لوحة التحكم`;
                document.getElementById('userProfileDirectLink').href = ADMIN_URL;
                banner.style.display = 'flex';
                return;
            }

            if (activeId) {
                try {
                    const res = await fetch(`${API_URL}?action=get_user&id=${activeId}`);
                    const data = await res.json();
                    if (data.status === 'success' && data.user) {
                        const banner = document.getElementById('authStatusBanner');
                        document.getElementById('authStatusText').innerText = `أهلاً بك يا ${data.user.name}!`;
                        document.getElementById('authStatusBtnLabel').innerText = `عرض صفحتك الشخصية`;
                        document.getElementById('userProfileDirectLink').href = `${USER_URL}?id=${data.user.id}`;
                        banner.style.display = 'flex';
                    }
                } catch (e) {}
            }
        }
    </script>
</body>
</html>
