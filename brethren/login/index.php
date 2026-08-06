<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - منصة الأخوة</title>
    
    <!-- Google Fonts: Cairo & Baloo Bhaijaan 2 -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+Bhaijaan+2:wght@400;500;600;700;800&family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script src="../js/search_intelligent.js"></script>
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
            --danger: #ef4444;
            --danger-bg: #fee2e2;
            
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
            align-items: center;
            justify-content: center;
            padding: 20px 16px;
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

        .login-card {
            background: var(--surface);
            border: 1px solid var(--border-solid);
            border-radius: var(--r-xl);
            width: 100%;
            max-width: 440px;
            padding: 32px 28px;
            box-shadow: var(--shadow-lg);
            position: relative;
            z-index: 1;
        }

        .login-header {
            text-align: center;
            margin-bottom: 24px;
        }

        .brand-icon {
            width: 56px;
            height: 56px;
            border-radius: var(--r-lg);
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.6rem;
            box-shadow: 0 6px 20px var(--brand-glow);
            margin-bottom: 12px;
        }

        .login-title {
            font-size: 1.5rem;
            font-weight: 900;
            color: var(--text);
        }

        .login-subtitle {
            font-size: 0.85rem;
            color: var(--text-3);
            font-weight: 600;
            margin-top: 4px;
        }

        /* Role Tabs Switcher */
        .role-tabs {
            display: flex;
            background: var(--surface-2);
            border: 1px solid var(--border-solid);
            border-radius: var(--r-md);
            padding: 4px;
            gap: 4px;
            margin-bottom: 24px;
        }

        .role-tab-btn {
            flex: 1;
            padding: 10px;
            border: none;
            background: none;
            color: var(--text-3);
            font-weight: 800;
            font-size: 0.9rem;
            border-radius: var(--r-sm);
            cursor: pointer;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .role-tab-btn.active {
            background: var(--brand);
            color: #fff;
            box-shadow: 0 4px 12px var(--brand-glow);
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--text-2);
            margin-bottom: 6px;
        }

        .form-input {
            width: 100%;
            background: var(--surface-2);
            border: 1.5px solid var(--border-solid);
            padding: 12px 14px;
            border-radius: var(--r-md);
            color: var(--text);
            font-size: 0.95rem;
            font-weight: 600;
            outline: none;
            transition: all 0.2s;
        }

        .form-input:focus {
            border-color: var(--brand);
            background: #fff;
        }

        .btn-submit {
            width: 100%;
            background: var(--brand);
            color: #fff;
            border: none;
            padding: 14px;
            border-radius: var(--r-md);
            font-size: 1rem;
            font-weight: 900;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 8px;
            box-shadow: 0 4px 14px var(--brand-glow);
        }

        .btn-submit:hover {
            background: var(--brand-dark);
        }

        .back-home-link {
            display: block;
            text-align: center;
            margin-top: 20px;
            color: var(--text-3);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.88rem;
            transition: color 0.2s;
        }

        .back-home-link:hover {
            color: var(--brand);
        }

        .alert-msg {
            padding: 10px 14px;
            border-radius: var(--r-md);
            font-size: 0.85rem;
            font-weight: 700;
            margin-bottom: 16px;
            display: none;
        }

        .alert-error {
            background: var(--danger-bg);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.3);
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-header">
            <div class="brand-icon"><i class="fas fa-cross"></i></div>
            <h1 class="login-title">منصة الأخوة</h1>
            <p class="login-subtitle">سجل دخولك لمتابعة نقاطك وحضور الفعاليات</p>
        </div>

        <!-- Role Selector Tabs -->
        <div class="role-tabs">
            <button class="role-tab-btn active" onclick="switchLoginRole('member')">
                <i class="fas fa-user"></i> دخول الأعضاء
            </button>
            <button class="role-tab-btn" onclick="switchLoginRole('admin')">
                <i class="fas fa-user-shield"></i> الإدارة والخدام
            </button>
        </div>

        <div id="alertMsg" class="alert-msg alert-error"></div>

        <!-- Member Login Form -->
        <form id="memberLoginForm" onsubmit="handleMemberLogin(event)">
            <div class="form-group">
                <label class="form-label">رقم الهاتف أو كود المستخدم</label>
                <input type="text" id="memberKeyInput" class="form-input" required placeholder="ادخل رقم هاتفك أو كود QR">
            </div>
            <button type="submit" class="btn-submit">عرض ملفي الشخصي</button>
        </form>

        <!-- Admin Login Form -->
        <form id="adminLoginForm" onsubmit="handleAdminLogin(event)" style="display:none;">
            <div class="form-group">
                <label class="form-label">كلمة مرور الإدارة</label>
                <input type="password" id="adminPassInput" class="form-input" required placeholder="ادخل كلمة مرور الإدارة">
            </div>
            <button type="submit" class="btn-submit">دخول لوحة التحكم</button>
        </form>

        <a href="#" id="backHomeLink" class="back-home-link">
            <i class="fas fa-arrow-right"></i> العودة للصفحة الرئيسية
        </a>
    </div>

    <script>
        const isBrethrenSubfolder = window.location.pathname.includes('/brethren');
        const API_URL = isBrethrenSubfolder ? '/brethren/api.php' : '/api.php';
        const HOME_URL = isBrethrenSubfolder ? '/brethren/' : '/';
        const ADMIN_URL = isBrethrenSubfolder ? '/brethren/admin/' : '/admin/';

        document.getElementById('backHomeLink').href = HOME_URL;

        function switchLoginRole(role) {
            document.querySelectorAll('.role-tab-btn').forEach(btn => btn.classList.remove('active'));
            event.currentTarget.classList.add('active');

            if (role === 'member') {
                document.getElementById('memberLoginForm').style.display = 'block';
                document.getElementById('adminLoginForm').style.display = 'none';
            } else {
                document.getElementById('memberLoginForm').style.display = 'none';
                document.getElementById('adminLoginForm').style.display = 'block';
            }
        }

        async function handleMemberLogin(e) {
            e.preventDefault();
            const key = document.getElementById('memberKeyInput').value.trim();
            if (!key) return;

            try {
                const res = await fetch(`${API_URL}?action=get_user&user_code=${encodeURIComponent(key)}`);
                const data = await res.json();
                if (data.status === 'success' && data.user) {
                    localStorage.setItem('brethren_active_user_id', data.user.id);
                    window.location.href = `${HOME_URL}?id=${data.user.id}`;
                } else {
                    showAlert('لم نتمكن من العثور على حساب بهذا الرقم أو الكود');
                }
            } catch (err) {
                showAlert('تعذر الاتصال بالخادم');
            }
        }

        function handleAdminLogin(e) {
            e.preventDefault();
            const pass = document.getElementById('adminPassInput').value.trim();
            if (pass) {
                localStorage.setItem('brethren_admin_pass', pass);
                window.location.href = ADMIN_URL;
            }
        }

        function showAlert(msg) {
            const el = document.getElementById('alertMsg');
            el.innerText = msg;
            el.style.display = 'block';
        }
    </script>
</body>
</html>
