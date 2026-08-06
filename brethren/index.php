<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الرئيسية - منصة الأخوة</title>
    
    <!-- Google Fonts: Cairo & Baloo Bhaijaan 2 -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+Bhaijaan+2:wght@400;500;600;700;800&family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="js/search_intelligent.js"></script>
    <script src="/js/search_intelligent.js"></script>

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
            padding-bottom: 60px;
            position: relative;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: 
                radial-gradient(ellipse 80% 50% at 10% -10%, rgba(91, 108, 245, .08) 0%, transparent 60%),
                radial-gradient(ellipse 60% 40% at 90% 110%, rgba(139, 92, 246, .06) 0%, transparent 60%);
            pointer-events: none;
            z-index: 0;
        }

        .container {
            max-width: 860px;
            margin: 0 auto;
            padding: 20px 16px;
            position: relative;
            z-index: 1;
        }

        .topbar {
            position: sticky;
            top: 0;
            z-index: 300;
            background: rgba(255, 255, 255, 0.85);
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
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--text);
            text-decoration: none;
        }

        .brand-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--r-md);
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.15rem;
            box-shadow: 0 4px 14px var(--brand-glow);
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-btn {
            background: var(--brand);
            color: #fff;
            padding: 8px 18px;
            border-radius: var(--r-md);
            text-decoration: none;
            font-weight: 800;
            font-size: 0.88rem;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
            box-shadow: 0 4px 14px var(--brand-glow);
        }

        .nav-btn:hover { background: var(--brand-dark); }

        .hero-card {
            background: var(--surface);
            border: 1px solid var(--border-solid);
            border-radius: var(--r-xl);
            padding: 32px 24px;
            text-align: center;
            margin-bottom: 24px;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }

        .hero-card::before {
            content: '';
            position: absolute;
            top: 0; right: 0; width: 100%; height: 6px;
            background: linear-gradient(90deg, var(--brand), #8b5cf6, var(--warning));
        }

        .hero-title {
            font-size: 1.8rem;
            font-weight: 900;
            color: var(--text);
            margin-bottom: 8px;
        }

        .hero-subtitle {
            font-size: 0.95rem;
            color: var(--text-2);
            font-weight: 600;
            margin-bottom: 20px;
            max-width: 580px;
            margin-left: auto;
            margin-right: auto;
        }

        .hero-cta {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--brand);
            color: #fff;
            padding: 14px 28px;
            border-radius: var(--r-lg);
            font-size: 1.05rem;
            font-weight: 900;
            text-decoration: none;
            box-shadow: 0 6px 20px var(--brand-glow);
            transition: transform 0.2s;
        }

        .hero-cta:hover { transform: scale(1.03); }

        .auth-status-banner {
            background: var(--brand-bg);
            border: 1px solid rgba(91, 108, 245, 0.25);
            padding: 14px 18px;
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

        .search-card {
            background: var(--surface);
            border: 1px solid var(--border-solid);
            border-radius: var(--r-lg);
            padding: 18px;
            margin-bottom: 28px;
            box-shadow: var(--shadow-sm);
            position: relative;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            width: 100%;
            background: var(--surface-2);
            border: 1.5px solid var(--border-solid);
            padding: 12px 45px 12px 16px;
            border-radius: var(--r-md);
            color: var(--text);
            font-size: 0.95rem;
            font-weight: 600;
            outline: none;
        }

        .search-box input:focus { border-color: var(--brand); background: #fff; }
        .search-box i { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: var(--text-3); }

        .search-dropdown {
            position: absolute; top: 100%; left: 0; right: 0;
            background: var(--surface); border: 1px solid var(--border-solid);
            border-radius: var(--r-md); margin-top: 8px; max-height: 260px;
            overflow-y: auto; z-index: 100; box-shadow: var(--shadow-lg); display: none; padding: 6px;
        }

        .search-item {
            padding: 10px 14px; display: flex; align-items: center; gap: 12px;
            cursor: pointer; border-radius: var(--r-sm); transition: background 0.15s;
        }

        .search-item:hover { background: var(--brand-bg); }

        .section-title {
            font-size: 1.25rem; font-weight: 900; color: var(--text);
            margin-bottom: 14px; display: flex; align-items: center; gap: 8px;
        }

        .section-title i { color: var(--brand); }

        .events-grid {
            display: grid; grid-template-columns: repeat(auto-fill, minmax(270px, 1fr)); gap: 16px;
        }

        .event-card {
            background: var(--surface); border: 1px solid var(--border-solid);
            border-radius: var(--r-lg); padding: 18px; box-shadow: var(--shadow-sm);
        }

        .event-name { font-size: 1.1rem; font-weight: 900; margin-bottom: 6px; color: var(--text); }
        .event-date { font-size: 0.82rem; color: var(--text-3); font-weight: 600; margin-bottom: 8px; }
        .event-desc { font-size: 0.85rem; color: var(--text-2); }

        .empty-state { text-align: center; padding: 24px; color: var(--text-3); font-weight: 600; }
    </style>
</head>
<body>

    <div class="container">
        <!-- Top Navbar (Restricted Public View - Only Login CTA) -->
        <header class="topbar">
            <a href="#" id="homeBrandLink" class="brand">
                <div class="brand-icon"><i class="fas fa-cross"></i></div>
                <span>منصة الأخوة</span>
            </a>
            <div class="topbar-actions">
                <a href="#" id="loginNavLink" class="nav-btn">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>تسجيل الدخول</span>
                </a>
            </div>
        </header>

        <!-- Auth Status Banner if already logged in -->
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

        <!-- Hero Card -->
        <div class="hero-card">
            <h1 class="hero-title">منصة الأخوة والشباب</h1>
            <p class="hero-subtitle">المنصة المخصصة لمتابعة الفعاليات، تسجيل الحضور عبر رمز QR، وجمع النقاط والمكافآت الشخصية.</p>
            <a href="#" id="heroLoginBtn" class="hero-cta">
                <i class="fas fa-sign-in-alt"></i>
                <span>تسجيل الدخول للحساب</span>
            </a>
        </div>

        <!-- Search Card -->
        <div class="search-card">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="memberSearchInput" placeholder="البحث عن اسم عضو للتعرف على النقاط والفعاليات..." oninput="handleMemberSearch()">
            </div>
            <div class="search-dropdown" id="searchDropdown"></div>
        </div>

        <!-- Events List -->
        <div class="section-title">
            <i class="fas fa-calendar-star"></i>
            <span>الفعاليات المتاحة</span>
        </div>
        <div class="events-grid" id="eventsGrid">
            <div class="empty-state">جاري تحميل الفعاليات...</div>
        </div>
    </div>

    <script>
        const isBrethrenSubfolder = window.location.pathname.includes('/brethren');
        const API_URL = isBrethrenSubfolder ? '/brethren/api.php' : '/api.php';
        const LOGIN_URL = isBrethrenSubfolder ? '/brethren/login/' : 'login/';
        const ADMIN_URL = isBrethrenSubfolder ? '/brethren/admin/' : 'admin/';
        const USER_URL = isBrethrenSubfolder ? '/brethren/user/' : 'user/';
        const HOME_URL = isBrethrenSubfolder ? '/brethren/' : '/';

        document.getElementById('homeBrandLink').href = HOME_URL;
        document.getElementById('loginNavLink').href = LOGIN_URL;
        document.getElementById('heroLoginBtn').href = LOGIN_URL;

        let allUsersList = [];

        document.addEventListener('DOMContentLoaded', () => {
            checkActiveAuth();
            fetchUsers();
            fetchEvents();
        });

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

        async function fetchUsers() {
            try {
                const res = await fetch(`${API_URL}?action=get_users`);
                const data = await res.json();
                if (data.status === 'success') {
                    allUsersList = data.users || [];
                }
            } catch (e) {}
        }

        async function fetchEvents() {
            const grid = document.getElementById('eventsGrid');
            try {
                const res = await fetch(`${API_URL}?action=get_events`);
                const data = await res.json();
                if (data.status === 'success' && (data.events || []).length > 0) {
                    grid.innerHTML = data.events.map(ev => `
                        <div class="event-card">
                            <div class="event-name">${ev.event_name}</div>
                            <div class="event-date"><i class="far fa-calendar"></i> ${ev.event_date}</div>
                            ${ev.description ? `<div class="event-desc">${ev.description}</div>` : ''}
                        </div>
                    `).join('');
                } else {
                    grid.innerHTML = `<div class="empty-state">لا توجد فعاليات متاحة حالياً</div>`;
                }
            } catch (e) {
                grid.innerHTML = `<div class="empty-state">تعذر تحميل الفعاليات</div>`;
            }
        }

        function handleMemberSearch() {
            const query = document.getElementById('memberSearchInput').value.trim();
            const dropdown = document.getElementById('searchDropdown');

            if (!query) {
                dropdown.style.display = 'none';
                return;
            }

            const scored = allUsersList.map(u => ({
                ...u,
                _score: (typeof getMatchScore === 'function') 
                    ? getMatchScore(u, query, [{ val: u.name, weight: 1.0 }, { val: u.phone, weight: 1.1 }])
                    : (u.name.includes(query) ? 1 : 0)
            })).filter(u => u._score > 0)
               .sort((a, b) => b._score - a._score);

            if (scored.length === 0) {
                dropdown.innerHTML = '<div class="search-item" style="color:var(--text-3)">لا توجد نتائج مطابقة</div>';
            } else {
                dropdown.innerHTML = scored.slice(0, 5).map(u => `
                    <div class="search-item" onclick="selectUser(${u.id})">
                        <div style="font-weight:800;color:var(--text);">${u.name}</div>
                        <div style="font-size:0.76rem;color:var(--text-3);margin-right:auto;">${u.points} نقطة</div>
                    </div>
                `).join('');
            }
            dropdown.style.display = 'block';
        }

        function selectUser(id) {
            window.location.href = `${USER_URL}?id=${id}`;
        }
    </script>
</body>
</html>
