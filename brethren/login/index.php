<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول وإنشاء حساب - منصة الأخوة</title>
    
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
            padding: 24px 16px;
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

        .auth-card {
            background: var(--surface);
            border: 1px solid var(--border-solid);
            border-radius: var(--r-xl);
            width: 100%;
            max-width: 480px;
            padding: 36px 30px;
            box-shadow: var(--shadow-lg);
            position: relative;
            z-index: 1;
        }

        .auth-header {
            text-align: center;
            margin-bottom: 28px;
        }

        .brand-logo-wrapper {
            width: 76px;
            height: 76px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
        }

        .brand-logo-img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .auth-title {
            font-size: 1.65rem;
            font-weight: 900;
            color: var(--text);
        }

        .auth-subtitle {
            font-size: 0.9rem;
            color: var(--text-3);
            font-weight: 600;
            margin-top: 4px;
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

        .form-input, .form-select {
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

        .form-input:focus, .form-select:focus {
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
            margin-top: 10px;
            box-shadow: 0 4px 14px var(--brand-glow);
        }

        .btn-submit:hover {
            background: var(--brand-dark);
        }

        .auth-toggle-wrapper {
            text-align: center;
            margin-top: 18px;
            font-size: 0.9rem;
            color: var(--text-2);
            font-weight: 700;
        }

        .auth-toggle-link {
            color: var(--brand);
            text-decoration: none;
            font-weight: 900;
            margin-right: 4px;
            cursor: pointer;
        }

        .auth-toggle-link:hover {
            text-decoration: underline;
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

        .back-home-link:hover { color: var(--brand); }

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

    <div class="auth-card">
        <div class="auth-header">
            <div class="brand-logo-wrapper">
                <img src="/assets/brethren-logo.png" id="brandLogoImg" class="brand-logo-img" alt="Logo">
            </div>
            <h1 class="auth-title" id="pageMainTitle">تسجيل الدخول</h1>
            <p class="auth-subtitle" id="pageMainSubtitle">ادخل بياناتك للمتابعة في منصة الأخوة</p>
        </div>

        <div id="alertMsg" class="alert-msg alert-error"></div>

        <!-- UNIFIED LOGIN FORM -->
        <form id="loginForm" onsubmit="handleLogin(event)">
            <div class="form-group">
                <label class="form-label">البريد الإلكتروني أو رقم الهاتف</label>
                <input type="text" id="loginKeyInput" class="form-input" required placeholder="مثال: name@mail.com أو 01200000000">
            </div>
            <div class="form-group">
                <label class="form-label">كلمة المرور / كود الـ QR</label>
                <input type="password" id="loginPassInput" class="form-input" placeholder="ادخل كلمة المرور">
            </div>
            <button type="submit" class="btn-submit" id="loginSubmitBtn">
                <i class="fas fa-sign-in-alt"></i> تسجيل الدخول
            </button>

            <div class="auth-toggle-wrapper">
                ليس لديك حساب؟ <a onclick="switchAuthMode('register')" class="auth-toggle-link">إنشاء حساب جديد</a>
            </div>
        </form>

        <!-- REGISTER FORM (CREATE ACCOUNT) -->
        <form id="registerForm" onsubmit="handleRegister(event)" style="display:none;">
            <div class="form-group">
                <label class="form-label">الاسم بالكامل *</label>
                <input type="text" id="regNameInput" class="form-input" required placeholder="مثال: بيتر فايز">
            </div>
            <div class="form-group">
                <label class="form-label">البريد الإلكتروني *</label>
                <input type="email" id="regEmailInput" class="form-input" required placeholder="name@domain.com">
            </div>
            <div class="form-group">
                <label class="form-label">رقم الهاتف *</label>
                <input type="tel" id="regPhoneInput" class="form-input" required placeholder="012xxxxxxxx">
            </div>
            <div class="form-group">
                <label class="form-label">كلمة المرور *</label>
                <input type="password" id="regPassInput" class="form-input" required placeholder="كلمة المرور للدخول بها">
            </div>
            <div class="form-group">
                <label class="form-label">المنطقة / السكن</label>
                <input type="text" id="regLocationInput" class="form-input" placeholder="مثال: عين شمس">
            </div>
            <div class="form-group">
                <label class="form-label">النوع</label>
                <select id="regGenderInput" class="form-select">
                    <option value="ذكر">ذكر</option>
                    <option value="أنثى">أنثى</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label">تاريخ الميلاد</label>
                <input type="text" id="regBirthDateInput" class="form-input" placeholder="مثال: 05/2000 أو 15/05/2000">
            </div>

            <!-- Dynamic Custom Fields Container -->
            <div id="registerCustomFieldsContainer"></div>

            <button type="submit" class="btn-submit" id="registerSubmitBtn">
                <i class="fas fa-check-circle"></i> إنشاء الحساب والتسجيل
            </button>

            <div class="auth-toggle-wrapper">
                لديك حساب بالفعل؟ <a onclick="switchAuthMode('login')" class="auth-toggle-link">تسجيل الدخول</a>
            </div>
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
        const LOGO_SRC = isBrethrenSubfolder ? '/brethren/assets/brethren-logo.png' : '/assets/brethren-logo.png';

        document.getElementById('backHomeLink').href = HOME_URL;
        document.getElementById('brandLogoImg').src = LOGO_SRC;

        document.addEventListener('DOMContentLoaded', () => {
            fetchCustomFieldsTemplate();
        });

        function switchAuthMode(mode) {
            hideAlert();
            const mainTitle = document.getElementById('pageMainTitle');
            const mainSubtitle = document.getElementById('pageMainSubtitle');

            if (mode === 'login') {
                mainTitle.innerText = 'تسجيل الدخول';
                mainSubtitle.innerText = 'ادخل بياناتك للمتابعة في منصة الأخوة';
                document.getElementById('loginForm').style.display = 'block';
                document.getElementById('registerForm').style.display = 'none';
            } else {
                mainTitle.innerText = 'إنشاء حساب جديد';
                mainSubtitle.innerText = 'ادخل بياناتك للانضمام لمنصة الأخوة';
                document.getElementById('loginForm').style.display = 'none';
                document.getElementById('registerForm').style.display = 'block';
            }
        }

        async function fetchCustomFieldsTemplate() {
            try {
                const res = await fetch(`${API_URL}?action=get_users`);
                const data = await res.json();
                if (data.status === 'success' && data.users && data.users.length > 0) {
                    const sampleUser = data.users.find(u => u.custom_fields && Object.keys(u.custom_fields).length > 0);
                    if (sampleUser && sampleUser.custom_fields) {
                        const container = document.getElementById('registerCustomFieldsContainer');
                        container.innerHTML = '';
                        for (const key of Object.keys(sampleUser.custom_fields)) {
                            container.innerHTML += `
                                <div class="form-group">
                                    <label class="form-label">${key}</label>
                                    <input type="text" data-custom-key="${key}" class="form-input reg-custom-field" placeholder="ادخل ${key}">
                                </div>
                            `;
                        }
                    }
                }
            } catch (e) {}
        }

        async function handleLogin(e) {
            e.preventDefault();
            hideAlert();
            const key = document.getElementById('loginKeyInput').value.trim();
            const password = document.getElementById('loginPassInput').value.trim();

            if (!key) return;

            const btn = document.getElementById('loginSubmitBtn');
            btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> جاري التحقق...`;
            btn.disabled = true;

            try {
                const res = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'login', key: key, password: password })
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
                    showAlert(data.message || 'بيانات الدخول غير صحيحة');
                }
            } catch (err) {
                showAlert('تعذر الاتصال بالخادم، يرجى المحاولة لاحقاً');
            } finally {
                btn.innerHTML = `<i class="fas fa-sign-in-alt"></i> تسجيل الدخول`;
                btn.disabled = false;
            }
        }

        async function handleRegister(e) {
            e.preventDefault();
            hideAlert();

            const customFields = {};
            document.querySelectorAll('.reg-custom-field').forEach(inp => {
                const k = inp.getAttribute('data-custom-key');
                if (k) customFields[k] = inp.value.trim();
            });

            const payload = {
                action: 'register',
                name: document.getElementById('regNameInput').value.trim(),
                email: document.getElementById('regEmailInput').value.trim(),
                phone: document.getElementById('regPhoneInput').value.trim(),
                password: document.getElementById('regPassInput').value.trim(),
                location: document.getElementById('regLocationInput').value.trim(),
                gender: document.getElementById('regGenderInput').value || 'ذكر',
                birth_date: document.getElementById('regBirthDateInput').value.trim(),
                custom_fields: customFields
            };

            const btn = document.getElementById('registerSubmitBtn');
            btn.innerHTML = `<i class="fas fa-spinner fa-spin"></i> جاري إنشاء الحساب...`;
            btn.disabled = true;

            try {
                const res = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();

                if (data.status === 'success' && data.user) {
                    localStorage.setItem('brethren_active_user_id', data.user.id);
                    localStorage.setItem('brethren_is_admin', 'false');
                    window.location.href = `${USER_URL}?id=${data.user.id}`;
                } else {
                    showAlert(data.message || 'تعذر إنشاء الحساب');
                }
            } catch (err) {
                showAlert('تعذر الاتصال بالخادم أثناء التسجيل');
            } finally {
                btn.innerHTML = `<i class="fas fa-check-circle"></i> إنشاء الحساب والتسجيل`;
                btn.disabled = false;
            }
        }

        function showAlert(msg) {
            const el = document.getElementById('alertMsg');
            el.innerText = msg;
            el.style.display = 'block';
        }

        function hideAlert() {
            document.getElementById('alertMsg').style.display = 'none';
        }
    </script>
</body>
</html>
