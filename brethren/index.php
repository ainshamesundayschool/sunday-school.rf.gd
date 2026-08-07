<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>منصة الأخوة - الرئيسية</title>
    
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
            --warning: #f59e0b;
            
            --bg: #f8fafc;
            --surface: #ffffff;
            --surface-2: #f1f5f9;
            --border-solid: #e2e8f0;
            --text: #0f172a;
            --text-2: #475569;
            --text-3: #94a3b8;

            --shadow-sm: 0 1px 3px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 20px -2px rgba(0,0,0,0.06);
            --shadow-lg: 0 12px 32px -4px rgba(0,0,0,0.1);
            
            --r-md: 14px;
            --r-lg: 18px;
            --r-xl: 24px;
            --r-full: 9999px;
        }

        * {
            box-sizing: border-box;
            margin: 0; padding: 0;
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
            max-width: 900px;
            margin: 0 auto;
            padding: 16px;
            width: 100%;
        }

        /* Clean Topbar */
        .topbar {
            position: sticky; top: 0; z-index: 300;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--border-solid);
            padding: 10px 16px;
            border-radius: var(--r-xl);
            margin-bottom: 20px;
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: var(--shadow-sm);
        }

        .brand {
            display: flex; align-items: center; gap: 8px;
            font-size: 1.1rem; font-weight: 900; color: var(--text);
            text-decoration: none;
        }

        .brand-logo-img { width: 34px; height: 34px; object-fit: contain; }

        .header-actions { display: flex; align-items: center; gap: 8px; }

        .btn-nav-primary {
            background: var(--brand); color: #fff;
            padding: 8px 16px; border-radius: var(--r-md);
            font-size: 0.84rem; font-weight: 800; text-decoration: none;
            display: inline-flex; align-items: center; gap: 6px;
            transition: all 0.2s; box-shadow: 0 4px 12px var(--brand-glow);
        }

        .btn-nav-primary:hover { background: var(--brand-dark); }

        .btn-nav-subtle {
            background: var(--surface-2); color: var(--text-2);
            border: 1px solid var(--border-solid); padding: 8px 14px;
            border-radius: var(--r-md); font-size: 0.84rem; font-weight: 800;
            text-decoration: none; display: inline-flex; align-items: center; gap: 6px;
        }

        .btn-nav-subtle:hover { background: var(--brand-bg); color: var(--brand-dark); }

        /* Sleek Hero Card */
        .hero-card {
            background: var(--surface);
            border: 1px solid var(--border-solid);
            border-radius: var(--r-xl);
            padding: 32px 20px;
            text-align: center;
            margin-bottom: 20px;
            box-shadow: var(--shadow-md);
            position: relative;
        }

        .hero-logo {
            width: 72px; height: 72px; margin: 0 auto 14px;
            object-fit: contain; display: block;
        }

        .hero-title {
            font-size: 1.8rem; font-weight: 900; color: var(--text);
            margin-bottom: 8px; line-height: 1.25;
        }

        .hero-subtitle {
            font-size: 0.92rem; color: var(--text-2); font-weight: 600;
            line-height: 1.6; max-width: 540px; margin: 0 auto 20px;
        }

        .hero-main-cta {
            display: inline-flex; align-items: center; gap: 8px;
            background: var(--brand); color: #fff; padding: 12px 28px;
            border-radius: var(--r-md); font-size: 0.98rem; font-weight: 900;
            text-decoration: none; box-shadow: 0 6px 18px var(--brand-glow);
            transition: all 0.2s;
        }

        .hero-main-cta:hover { background: var(--brand-dark); transform: translateY(-1px); }

        /* Features Grid */
        .features-grid {
            display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 14px; margin-bottom: 24px;
        }

        .feature-card {
            background: var(--surface); border: 1px solid var(--border-solid);
            border-radius: var(--r-lg); padding: 20px 16px; text-align: center;
            box-shadow: var(--shadow-sm);
        }

        .feature-icon {
            width: 46px; height: 46px; border-radius: var(--r-md);
            background: var(--brand-bg); color: var(--brand-dark);
            display: inline-flex; align-items: center; justify-content: center;
            font-size: 1.25rem; margin-bottom: 12px;
        }

        .feature-title { font-size: 1.02rem; font-weight: 900; color: var(--text); margin-bottom: 4px; }
        .feature-desc { font-size: 0.83rem; color: var(--text-2); line-height: 1.5; font-weight: 600; }

        /* Events Section */
        .section-header {
            display: flex; align-items: center; justify-content: space-between;
            margin-bottom: 12px;
        }

        .section-title { font-size: 1.1rem; font-weight: 900; color: var(--text); display: flex; align-items: center; gap: 8px; }

        .events-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 14px; margin-bottom: 24px;
        }

        .event-card {
            background: var(--surface); border: 1px solid var(--border-solid);
            border-radius: var(--r-lg); padding: 16px; box-shadow: var(--shadow-sm);
        }

        .event-title { font-size: 1rem; font-weight: 900; color: var(--text); margin-bottom: 4px; }
        .event-date { font-size: 0.8rem; color: var(--text-3); font-weight: 600; margin-bottom: 6px; display: flex; align-items: center; gap: 6px; }
        .event-desc { font-size: 0.82rem; color: var(--text-2); line-height: 1.4; }

        .footer {
            text-align: center; padding: 16px; color: var(--text-3);
            font-size: 0.82rem; font-weight: 700; border-top: 1px solid var(--border-solid);
            background: var(--surface);
        }

        @media (max-width: 768px) {
            .container { padding: 10px; }
            .hero-card { padding: 24px 14px; }
            .hero-title { font-size: 1.5rem; }
        }
    </style>
</head>
<body>

    <div class="container">
        <!-- Top Navbar (Clean Single Action Button) -->
        <header class="topbar">
            <a href="#" id="homeBrandLink" class="brand">
                <img src="/assets/brethren-logo.png" id="homeLogoImg" class="brand-logo-img" alt="Logo">
                <span>منصة الأخوة</span>
            </a>

            <div class="header-actions">
                <a href="#" id="headerEventsLink" class="btn-nav-subtle">
                    <i class="fas fa-calendar-alt"></i>
                    <span>الفعاليات</span>
                </a>
                <a href="#" id="headerAuthBtn" class="btn-nav-primary">
                    <i class="fas fa-sign-in-alt"></i>
                    <span id="headerAuthBtnText">دخول</span>
                </a>
            </div>
        </header>

        <!-- Hero Section -->
        <div class="hero-card">
            <img src="/assets/brethren-logo.png" id="heroLogoImg" class="hero-logo" alt="Logo">
            <h1 class="hero-title">منصة الأخوة</h1>
            <p class="hero-subtitle">
                منصة متكاملة لمتابعة الفعاليات، تسجيل الحضور إلكترونياً عبر رمز QR المخصص، وجمع النقاط والمكافآت الشخصية بسهولة.
            </p>
            <a href="#" id="heroCtaBtn" class="hero-main-cta">
                <i class="fas fa-sign-in-alt"></i>
                <span id="heroCtaBtnText">تسجيل الدخول / حساب جديد</span>
            </a>
        </div>

        <!-- Features Grid -->
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-qrcode"></i></div>
                <h3 class="feature-title">رمز QR مخصص لكل عضو</h3>
                <p class="feature-desc">كود استجابة سريعة فريد وخاص بكل عضو يسهل تسجيل الحضور في الفعاليات فورياً.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-star" style="color:var(--warning);"></i></div>
                <h3 class="feature-title">نظام النقاط والمكافآت</h3>
                <p class="feature-desc">إضافة تلقائية للنقاط عند كل حضور وتتبع سجل النقاط والمكافآت في ملفك.</p>
            </div>

            <div class="feature-card">
                <div class="feature-icon"><i class="fas fa-calendar-alt"></i></div>
                <h3 class="feature-title">متابعة الفعاليات والأنشطة</h3>
                <p class="feature-desc">استعراض كافة الفعاليات المتاحة ومتابعة الأنشطة القادمة فور إعلانها.</p>
            </div>
        </div>

        <!-- Events List Section -->
        <div id="eventsSection" style="margin-top:20px;">
            <div class="section-header">
                <div class="section-title">
                    <i class="fas fa-calendar-star" style="color:var(--brand);"></i> الفعاليات المتاحة
                </div>
            </div>
            <div class="events-grid" id="homeEventsGrid">
                <div style="text-align:center;padding:20px;color:var(--text-3);grid-column:1/-1;">جاري تحميل الفعاليات...</div>
            </div>
        </div>
    </div>

    <footer class="footer">
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
        document.getElementById('homeLogoImg').src = LOGO_SRC;
        document.getElementById('heroLogoImg').src = LOGO_SRC;

        document.addEventListener('DOMContentLoaded', () => {
            setupAuthNavigation();
            fetchHomeEvents();
        });

        function setupAuthNavigation() {
            const activeId = localStorage.getItem('brethren_active_user_id');
            const isAdmin = localStorage.getItem('brethren_is_admin') === 'true';

            const headerBtn = document.getElementById('headerAuthBtn');
            const headerBtnText = document.getElementById('headerAuthBtnText');
            const heroBtn = document.getElementById('heroCtaBtn');
            const heroBtnText = document.getElementById('heroCtaBtnText');

            if (isAdmin) {
                headerBtn.href = ADMIN_URL;
                headerBtnText.innerText = 'لوحة التحكم';
                heroBtn.href = ADMIN_URL;
                heroBtnText.innerText = 'الانتقال للوحة التحكم';
            } else if (activeId) {
                const userLink = `${USER_URL}?id=${activeId}`;
                headerBtn.href = userLink;
                headerBtnText.innerText = 'ملفي الشخصي';
                heroBtn.href = userLink;
                heroBtnText.innerText = 'الانتقال لملفي الشخصي';
            } else {
                headerBtn.href = LOGIN_URL;
                headerBtnText.innerText = 'دخول';
                heroBtn.href = LOGIN_URL;
                heroBtnText.innerText = 'تسجيل الدخول / حساب جديد';
            }
        }

        async function fetchHomeEvents() {
            try {
                const res = await fetch(`${API_URL}?action=get_events`);
                const data = await res.json();
                const grid = document.getElementById('homeEventsGrid');
                if (data.status === 'success' && data.events && data.events.length > 0) {
                    grid.innerHTML = data.events.map(ev => `
                        <div class="event-card">
                            <div class="event-title">${ev.event_name}</div>
                            <div class="event-date"><i class="far fa-calendar-alt"></i> ${ev.event_date}</div>
                            ${ev.description ? `<div class="event-desc">${ev.description}</div>` : ''}
                        </div>
                    `).join('');
                } else {
                    grid.innerHTML = `<div style="text-align:center;padding:20px;color:var(--text-3);grid-column:1/-1;">لا توجد فعاليات متاحة حالياً</div>`;
                }
            } catch (err) {}
        }
    </script>
</body>
</html>
