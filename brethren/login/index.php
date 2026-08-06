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
            padding: 36px 30px;
            box-shadow: var(--shadow-lg);
            position: relative;
            z-index: 1;
        }

        .login-header {
            text-align: center;
            margin-bottom: 24px;
        }

        .brand-icon {
            width: 58px;
            height: 58px;
            border-radius: var(--r-lg);
            background: linear-gradient(135deg, var(--brand), var(--brand-dark));
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.65rem;
            box-shadow: 0 6px 20px var(--brand-glow);
            margin-bottom: 14px;
        }

        .login-title {
            font-size: 1.55rem;
            font-weight: 900;
            color: var(--text);
        }

        .login-subtitle {
            font-size: 0.88rem;
            color: var(--text-3);
            font-weight: 600;
            margin-top: 4px;
        }

        .form-group {
            margin-bottom: 18px;
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
            padding: 13px 16px;
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
            margin-top: 22px;
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
            padding: 12px 14px;
            border-radius: var(--r-md);
            font-size: 0.85rem;
            font-weight: 800;
            margin-bottom: 18px;
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
            <h1 class="login-title">تسجيل الدخول</h1>
            <p class="login-subtitle">ادخل رقم الهاتف، كود الـ QR، أو كلمة المرور للمتابعة</p>
        </div>

        <div id="alertMsg" class="alert-msg alert-error"></div>

        <form id="loginForm" onsubmit="handleLogin(event)">
            <div class="form-group">
                <label class="form-label">رقم الهاتف / كود QR / كلمة المرور</label>
                <input type="text" id="loginKeyInput" class="form-input" required placeholder="مثال: 01200000000 أو BR-XXXXXX">
            </div>
            <button type="submit" class="btn-submit" id="loginBtn">
                <i class="fas fa-sign-in-alt"></i> دخول الحساب
            </button>
        </form>

        <a href="#" id="backHomeLink" class="back-home-link">
            <i class="fas fa-arrow-right"></i> العودة للصفحة الرئيسية
        </a>
    </div>

    <script>
        const isBrethrenSubfolder = window.location.pathname.includes('/brethren');
        const API_URL = isBrethrenSubfolder ? '/brethren/api.php' : '/api.php';
        const HOME_URL = isBrethrenSubfolder ? '/brethren/' : '/';
        const ADMIN_URL = isBrethrenSubfolder ? '/brethren/admin/' : 'admin/';
        const USER_URL = isBrethrenSubfolder ? '/brethren/user/' : 'user/';

        document.getElementById('backHomeLink').href = HOME_URL;

        async function handleLogin(e) {
            e.preventDefault();
            const key = document.getElementById('loginKeyInput').value.trim();
            if (!key) return;

            const btn = document.getElementById('loginBtn');
            btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> جاري التحقق...`;
            btn.disabled = true;

            try {
                const res = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'login', key: key })
                });
                const data = await res.json();

                if (data.status === 'success' && data.user) {
                    localStorage.setItem('brethren_active_user_id', data.user.id);
                    localStorage.setItem('brethren_is_admin', data.is_admin ? 'true' : 'false');

                    if (data.is_admin) {
                        window.location.href = ADMIN_URL;
                    } else {
                        window.location.href = `${USER_URL}?id=${data.user.id}`;
                    }
                } else {
                    showAlert(data.message || 'لم نتمكن من العثور على حساب بهذا الرقم أو الكود');
                }
            } catch (err) {
                showAlert('تعذر الاتصال بالخادم، يرجى المحاولة لاحقاً');
            } finally {
                btn.innerHTML = `<i class="fas fa-sign-in-alt"></i> دخول الحساب`;
                btn.disabled = false;
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
