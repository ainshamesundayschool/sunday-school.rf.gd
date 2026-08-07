<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>الملف الشخصي - منصة الأخوة</title>

    <!-- Google Fonts: Cairo & Baloo Bhaijaan 2 -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Baloo+Bhaijaan+2:wght@400;500;600;700;800&family=Cairo:wght@400;500;600;700;800;900&display=swap"
        rel="stylesheet">

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
            --success-dark: #059669;
            --success-bg: #d1fae5;
            --danger: #ef4444;
            --danger-dark: #dc2626;
            --danger-bg: #fee2e2;
            --warning: #f59e0b;
            --warning-dark: #d97706;
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
            --shadow-xl: 0 32px 64px -12px rgba(0, 0, 0, .18);

            --r-sm: 10px;
            --r-md: 14px;
            --r-lg: 18px;
            --r-xl: 24px;
            --r-full: 9999px;
            --spring: cubic-bezier(.16, 1, .3, 1);
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
            font-size: 14px;
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                radial-gradient(ellipse 80% 50% at 10% -10%, rgba(91, 108, 245, .08) 0%, transparent 60%),
                radial-gradient(ellipse 60% 40% at 90% 110%, rgba(139, 92, 246, .06) 0%, transparent 60%);
            pointer-events: none;
            z-index: 0;
        }

        .container {
            max-width: 860px;
            margin: 0 auto;
            padding: 16px;
            position: relative;
            z-index: 1;
        }

        /* Topbar Header */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 300;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-solid);
            padding: 10px 16px;
            border-radius: var(--r-xl);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-sm);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.15rem;
            font-weight: 900;
            color: var(--text);
            text-decoration: none;
        }

        .brand-logo-img {
            width: 36px;
            height: 36px;
            object-fit: contain;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-btn {
            background: var(--surface-2);
            color: var(--text-2);
            padding: 7px 12px;
            border-radius: var(--r-md);
            text-decoration: none;
            font-weight: 800;
            font-size: 0.82rem;
            display: flex;
            align-items: center;
            gap: 6px;
            border: 1px solid var(--border-solid);
            transition: all 0.2s;
        }

        .nav-btn:hover {
            background: var(--brand-bg);
            color: var(--brand-dark);
        }

        /* Profile Card */
        .profile-card {
            background: var(--surface);
            border: 1px solid var(--border-solid);
            border-radius: var(--r-lg);
            padding: 14px 16px;
            margin-bottom: 14px;
            box-shadow: var(--shadow-sm);
            position: relative;
        }

        .profile-top-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .profile-identity {
            display: flex;
            align-items: center;
            gap: 12px;
            flex: 1;
        }

        .profile-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border-solid);
            box-shadow: var(--shadow-sm);
            background: var(--surface-2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: var(--brand);
            font-weight: 800;
            flex-shrink: 0;
        }

        .profile-meta {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .profile-name {
            font-size: 1.05rem;
            font-weight: 900;
            color: var(--text);
            line-height: 1.2;
        }

        .points-badge {
            background: var(--brand-bg);
            color: var(--brand-dark);
            border: 1px solid rgba(91, 108, 245, 0.22);
            padding: 4px 12px;
            border-radius: var(--r-full);
            font-size: 0.82rem;
            font-weight: 900;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            cursor: pointer;
            transition: all 0.2s;
            width: fit-content;
        }

        .points-badge:hover {
            background: var(--brand);
            color: #fff;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 8px;
            margin-top: 12px;
            padding-top: 10px;
            border-top: 1px solid var(--border-solid);
        }

        .info-item {
            background: var(--surface-2);
            padding: 7px 10px;
            border-radius: var(--r-md);
            border: 1px solid var(--border-solid);
        }

        .info-label {
            font-size: 0.72rem;
            color: var(--text-3);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 4px;
            margin-bottom: 2px;
        }

        .info-value {
            font-size: 0.84rem;
            font-weight: 800;
            color: var(--text);
            word-break: break-word;
        }

        .edit-info-btn {
            background: var(--brand-bg);
            color: var(--brand-dark);
            border: 1px solid rgba(91, 108, 245, 0.22);
            padding: 6px 12px;
            border-radius: var(--r-md);
            font-weight: 800;
            font-size: 0.78rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            margin-top: 10px;
            transition: all 0.2s;
        }

        .edit-info-btn:hover {
            background: var(--brand);
            color: #fff;
        }

        /* QR Section */
        .qr-section {
            background: var(--surface);
            border: 1px solid var(--border-solid);
            border-radius: var(--r-xl);
            padding: 20px;
            text-align: center;
            margin-bottom: 24px;
            box-shadow: var(--shadow-md);
        }

        .qr-title {
            font-size: 1.15rem;
            font-weight: 900;
            margin-bottom: 2px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            color: var(--text);
        }

        .qr-subtitle {
            font-size: 0.82rem;
            color: var(--text-3);
            margin-bottom: 14px;
            font-weight: 600;
        }

        .qr-box {
            background: #ffffff;
            padding: 14px;
            border-radius: var(--r-lg);
            display: inline-block;
            border: 2px solid var(--border-solid);
            box-shadow: var(--shadow-sm);
            max-width: 200px;
            width: 100%;
        }

        .qr-box img {
            width: 100%;
            height: auto;
            display: block;
            border-radius: 8px;
        }

        .user-code-tag {
            margin-top: 10px;
            display: inline-block;
            background: var(--surface-3);
            padding: 5px 14px;
            border-radius: var(--r-full);
            font-family: monospace;
            font-size: 0.95rem;
            font-weight: 800;
            color: var(--brand-dark);
            border: 1px solid var(--border-solid);
        }

        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 12px;
        }

        .section-title {
            font-size: 1.1rem;
            font-weight: 900;
            display: flex;
            align-items: center;
            gap: 7px;
            color: var(--text);
        }

        .section-title i {
            color: var(--brand);
        }

        .badge-count {
            background: var(--brand-bg);
            color: var(--brand-dark);
            padding: 2px 9px;
            border-radius: var(--r-full);
            font-size: 0.78rem;
            font-weight: 800;
            border: 1px solid rgba(91, 108, 245, 0.2);
        }

        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 12px;
            margin-bottom: 24px;
        }

        .event-card {
            background: var(--surface);
            border: 1px solid var(--border-solid);
            border-radius: var(--r-lg);
            padding: 14px;
            position: relative;
            box-shadow: var(--shadow-sm);
        }

        .event-card.attended {
            border-color: rgba(16, 185, 129, 0.4);
            background: var(--success-bg);
        }

        .event-status-pill {
            position: absolute;
            top: 12px;
            left: 12px;
            padding: 3px 9px;
            border-radius: var(--r-full);
            font-size: 0.74rem;
            font-weight: 800;
        }

        .status-attended {
            background: #10b981;
            color: #fff;
        }

        .status-available {
            background: var(--surface-3);
            color: var(--text-2);
            border: 1px solid var(--border-solid);
        }

        .event-name {
            font-size: 1rem;
            font-weight: 900;
            margin-bottom: 4px;
            color: var(--text);
            padding-left: 75px;
        }

        .event-date {
            font-size: 0.78rem;
            color: var(--text-3);
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 6px;
            font-weight: 600;
        }

        .event-desc {
            font-size: 0.82rem;
            color: var(--text-2);
            line-height: 1.4;
        }

        /* RESPONSIVE MODAL SYSTEM (Bottom Sheet on Mobile, Centered Rectangle Pop-up on Desktop) */
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 1000;
            display: none;
            align-items: flex-end;
            justify-content: center;
            padding: 0;
            transition: opacity 0.2s ease;
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-card {
            background: var(--surface);
            border: 1px solid var(--border-solid);
            width: 100%;
            max-width: 540px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            position: relative;
            border-radius: 24px 24px 0 0;
            animation: slideUpMobile 0.3s var(--spring);
        }

        .modal-card::before {
            content: '';
            position: absolute;
            top: 8px;
            left: 50%;
            transform: translateX(-50%);
            width: 38px;
            height: 4px;
            border-radius: 99px;
            background: var(--border-solid);
            z-index: 10;
        }

        @media (min-width: 769px) {
            .modal-overlay {
                align-items: center;
                padding: 20px;
            }

            .modal-card {
                border-radius: var(--r-xl);
                max-height: 90vh;
                animation: fadeScaleIn 0.25s var(--spring);
            }

            .modal-card::before {
                display: none;
            }
        }

        @keyframes slideUpMobile {
            from {
                transform: translateY(100%);
            }

            to {
                transform: translateY(0);
            }
        }

        @keyframes fadeScaleIn {
            from {
                opacity: 0;
                transform: scale(0.95) translateY(12px);
            }

            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-header {
            padding: 14px 18px;
            border-bottom: 1px solid var(--border-solid);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--surface-2);
        }

        .modal-title {
            font-size: 1.05rem;
            font-weight: 900;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--text-3);
            font-size: 1.4rem;
            cursor: pointer;
            line-height: 1;
        }

        .modal-close:hover {
            color: var(--danger);
        }

        .modal-body {
            padding: 18px;
            overflow-y: auto;
            flex: 1;
        }

        .history-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .history-item {
            background: var(--surface-2);
            border: 1px solid var(--border-solid);
            border-radius: var(--r-md);
            padding: 10px 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .history-reason {
            font-size: 0.88rem;
            font-weight: 800;
            color: var(--text);
        }

        .history-date {
            font-size: 0.74rem;
            color: var(--text-3);
            font-weight: 600;
        }

        .history-change {
            font-size: 0.95rem;
            font-weight: 900;
            padding: 3px 10px;
            border-radius: var(--r-full);
        }

        .history-change.positive {
            background: var(--success-bg);
            color: var(--success-dark);
        }

        .history-change.negative {
            background: var(--danger-bg);
            color: var(--danger-dark);
        }

        .form-group {
            margin-bottom: 12px;
        }

        .form-label {
            display: block;
            font-size: 0.8rem;
            font-weight: 800;
            color: var(--text-2);
            margin-bottom: 4px;
        }

        .form-input,
        .form-select {
            width: 100%;
            background: var(--surface-2);
            border: 1.5px solid var(--border-solid);
            padding: 9px 12px;
            border-radius: var(--r-md);
            color: var(--text);
            font-size: 0.88rem;
            font-weight: 600;
            outline: none;
        }

        .form-input:focus,
        .form-select:focus {
            border-color: var(--brand);
            background: #fff;
        }

        .btn-submit {
            width: 100%;
            background: var(--brand);
            color: #fff;
            border: none;
            padding: 11px;
            border-radius: var(--r-md);
            font-size: 0.92rem;
            font-weight: 900;
            cursor: pointer;
            margin-top: 8px;
        }

        .empty-state {
            text-align: center;
            padding: 20px;
            color: var(--text-3);
            font-size: 0.88rem;
            font-weight: 600;
        }
    </style>
</head>

<body>

    <div class="container">
        <!-- Top Navbar -->
        <header class="topbar">
            <a href="#" id="homeBrandLink" class="brand">
                <img src="/assets/brethren-logo.png" id="userLogoImg" class="brand-logo-img" alt="Logo">
                <span>منصة الأخوة</span>
            </a>
            <div class="topbar-actions">
                <a href="#" id="homeNavLink" class="nav-btn">
                    <i class="fas fa-home"></i>
                    <span>الرئيسية</span>
                </a>
                <button onclick="handleLogout()" class="nav-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>خروج</span>
                </button>
            </div>
        </header>

        <!-- Main User Profile Container -->
        <div id="profileContainer">
            <div class="empty-state">
                <i class="fas fa-spinner fa-spin"></i>
                جاري تحميل بيانات الملف الشخصي...
            </div>
        </div>

        <!-- Section: Attended Events -->
        <div class="section-header">
            <div class="section-title">
                <i class="fas fa-calendar-check"></i>
                <span>الفعاليات التي تم حضورها</span>
            </div>
            <span class="badge-count" id="attendedEventsCount">0</span>
        </div>
        <div class="events-grid" id="attendedEventsGrid">
            <div class="empty-state">لا توجد فعاليات مسجلة بعد</div>
        </div>

        <!-- Section: Available Events -->
        <div class="section-header">
            <div class="section-title">
                <i class="fas fa-calendar-alt"></i>
                <span>جميع الفعاليات المتاحة</span>
            </div>
            <span class="badge-count" id="availableEventsCount">0</span>
        </div>
        <div class="events-grid" id="availableEventsGrid">
            <div class="empty-state">لا توجد فعاليات متاحة</div>
        </div>
    </div>

    <!-- Points History Modal -->
    <div class="modal-overlay" id="historyModal">
        <div class="modal-card">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fas fa-history" style="color: var(--warning);"></i>
                    <span>سجل النقاط والمكافآت</span>
                </div>
                <button class="modal-close" onclick="closeModal('historyModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="history-list" id="historyList"></div>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div class="modal-overlay" id="editProfileModal">
        <div class="modal-card">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fas fa-user-edit" style="color: var(--brand);"></i>
                    <span>تعديل البيانات الشخصية</span>
                </div>
                <button class="modal-close" onclick="closeModal('editProfileModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editProfileForm" onsubmit="submitEditProfile(event)">
                    <div class="form-group">
                        <label class="form-label">الاسم بالكامل *</label>
                        <input type="text" id="editNameInput" class="form-input" required autocomplete="name">
                    </div>
                    <div class="form-group">
                        <label class="form-label">البريد الإلكتروني</label>
                        <input type="email" id="editEmailInput" class="form-input" autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label class="form-label">رقم الهاتف</label>
                        <input type="tel" id="editPhoneInput" class="form-input" autocomplete="tel">
                    </div>
                    <div class="form-group">
                        <label class="form-label">كلمة المرور / الباسكود الجديدة (اختياري)</label>
                        <input type="password" id="editPassInput" class="form-input" autocomplete="new-password"
                            placeholder="اتركها فارغة إذا لم ترد التغيير">
                    </div>
                    <div class="form-group">
                        <label class="form-label">المنطقة / السكن</label>
                        <input type="text" id="editLocationInput" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">النوع</label>
                        <select id="editGenderInput" class="form-select">
                            <option value="ذكر">ذكر</option>
                            <option value="أنثى">أنثى</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">تاريخ الميلاد</label>
                        <input type="text" id="editBirthDateInput" class="form-input">
                    </div>

                    <div id="editCustomFieldsContainer"></div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i> حفظ التغييرات
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const isBrethrenSubfolder = window.location.pathname.includes('/brethren');
        const API_URL = isBrethrenSubfolder ? '/brethren/api.php' : '/api.php';
        const LOGIN_URL = isBrethrenSubfolder ? '/brethren/login/' : '/login/';
        const HOME_URL = isBrethrenSubfolder ? '/brethren/' : '/';
        const LOGO_SRC = isBrethrenSubfolder ? '/brethren/assets/brethren-logo.png' : '/assets/brethren-logo.png';

        document.getElementById('homeBrandLink').href = HOME_URL;
        document.getElementById('homeNavLink').href = HOME_URL;
        document.getElementById('userLogoImg').src = LOGO_SRC;

        const urlParams = new URLSearchParams(window.location.search);
        let currentUserId = urlParams.get('id') || localStorage.getItem('brethren_active_user_id');

        if (!currentUserId) {
            window.location.href = LOGIN_URL;
        }

        let currentUserData = null;
        let pointsHistory = [];
        let customFieldsTemplate = [];

        document.addEventListener('DOMContentLoaded', () => {
            loadUserProfile();
        });

        async function loadUserProfile() {
            try {
                const res = await fetch(`${API_URL}?action=get_user&id=${currentUserId}`);
                const data = await res.json();

                if (data.status === 'success') {
                    currentUserData = data.user;
                    pointsHistory = data.history || [];
                    renderProfile(data.user);
                    renderAttendedEvents(data.attended_events || []);
                    renderAvailableEvents(data.all_events || []);
                } else {
                    document.getElementById('profileContainer').innerHTML = `<div class="empty-state">${data.message || 'تعذر تحميل الملف الشخصي'}</div>`;
                }
            } catch (err) {
                document.getElementById('profileContainer').innerHTML = `<div class="empty-state">حدث خطأ في الاتصال بالخادم</div>`;
            }
        }

        function renderProfile(user) {
            const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=220x220&data=${encodeURIComponent(user.user_code)}`;
            const isFemale = user.gender === 'أنثى';

            document.getElementById('profileContainer').innerHTML = `
                <div class="profile-card">
                    <div class="profile-top-row">
                        <div class="profile-identity">
                            <div class="profile-avatar ${isFemale ? 'female' : 'male'}">
                                ${user.photo ? `<img src="${user.photo}" style="width:100%;height:100%;border-radius:50%;object-fit:cover">` : user.name.charAt(0)}
                            </div>
                            <div class="profile-meta">
                                <div class="profile-name">${user.name}</div>
                                <div class="points-badge" onclick="openHistoryModal()">
                                    <i class="fas fa-star"></i>
                                    <span>${user.points} نقطة</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-envelope"></i> البريد الإلكتروني</div>
                            <div class="info-value">${user.email || 'غير مسجل'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-phone"></i> رقم الهاتف</div>
                            <div class="info-value">${user.phone || 'غير مسجل'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-map-marker-alt"></i> المنطقة</div>
                            <div class="info-value">${user.location || 'غير محدد'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-venus-mars"></i> النوع</div>
                            <div class="info-value">${user.gender || 'ذكر'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-birthday-cake"></i> تاريخ الميلاد</div>
                            <div class="info-value">${user.birth_date || 'غير محدد'}</div>
                        </div>
                    </div>

                    ${Object.keys(user.custom_fields || {}).length > 0 ? `
                        <div class="info-grid" style="margin-top:10px;padding-top:10px;">
                            ${Object.entries(user.custom_fields).map(([k, v]) => `
                                <div class="info-item">
                                    <div class="info-label"><i class="fas fa-info-circle"></i> ${k}</div>
                                    <div class="info-value">${v}</div>
                                </div>
                            `).join('')}
                        </div>
                    ` : ''}

                    <button class="edit-info-btn" onclick="openEditProfileModal()">
                        <i class="fas fa-edit"></i> تعديل بياناتي
                    </button>
                </div>

                <div class="qr-section">
                    <div class="qr-title">
                        <i class="fas fa-qrcode" style="color:var(--brand);"></i>
                        <span>رمز الحضور QR Code</span>
                    </div>
                    <div class="qr-subtitle">ابرز هذا الكود للمسؤول عند حضور الفعاليات للحصول على النقاط!</div>
                    <div class="qr-box">
                        <img src="${qrUrl}" alt="QR Code">
                    </div>
                    <br>
                    <div class="user-code-tag">${user.user_code}</div>
                </div>
            `;
        }

        function renderAttendedEvents(events) {
            document.getElementById('attendedEventsCount').innerText = events.length;
            const grid = document.getElementById('attendedEventsGrid');

            if (events.length === 0) {
                grid.innerHTML = `<div class="empty-state">لم تقم بحضور فعاليات بعد</div>`;
                return;
            }

            grid.innerHTML = events.map(ev => `
                <div class="event-card attended">
                    <span class="event-status-pill status-attended"><i class="fas fa-check"></i> تم الحضور</span>
                    <div class="event-name">${ev.event_name}</div>
                    <div class="event-date"><i class="far fa-calendar-alt"></i> ${ev.event_date}</div>
                    ${ev.description ? `<div class="event-desc">${ev.description}</div>` : ''}
                </div>
            `).join('');
        }

        function renderAvailableEvents(events) {
            document.getElementById('availableEventsCount').innerText = events.length;
            const grid = document.getElementById('availableEventsGrid');

            if (events.length === 0) {
                grid.innerHTML = `<div class="empty-state">لا توجد فعاليات متاحة حالياً</div>`;
                return;
            }

            grid.innerHTML = events.map(ev => `
                <div class="event-card ${ev.is_attended ? 'attended' : ''}">
                    <span class="event-status-pill ${ev.is_attended ? 'status-attended' : 'status-available'}">
                        ${ev.is_attended ? '<i class="fas fa-check"></i> تم الحضور' : 'قادمة'}
                    </span>
                    <div class="event-name">${ev.event_name}</div>
                    <div class="event-date"><i class="far fa-calendar-alt"></i> ${ev.event_date}</div>
                    ${ev.description ? `<div class="event-desc">${ev.description}</div>` : ''}
                </div>
            `).join('');
        }

        function openHistoryModal() {
            const list = document.getElementById('historyList');
            if (pointsHistory.length === 0) {
                list.innerHTML = `<div class="empty-state">لا يوجد سجل نقاط حتى الآن</div>`;
            } else {
                list.innerHTML = pointsHistory.map(h => `
                    <div class="history-item">
                        <div>
                            <div class="history-reason">${h.reason}</div>
                            <div class="history-date">${h.created_at || ''}</div>
                        </div>
                        <div class="history-change ${h.points_change >= 0 ? 'positive' : 'negative'}">
                            ${h.points_change >= 0 ? '+' : ''}${h.points_change}
                        </div>
                    </div>
                `).join('');
            }
            document.getElementById('historyModal').classList.add('active');
        }

        function openEditProfileModal() {
            if (!currentUserData) return;

            document.getElementById('editNameInput').value = currentUserData.name || '';
            document.getElementById('editEmailInput').value = currentUserData.email || '';
            document.getElementById('editPhoneInput').value = currentUserData.phone || '';
            document.getElementById('editPassInput').value = '';
            document.getElementById('editLocationInput').value = currentUserData.location || '';
            document.getElementById('editGenderInput').value = currentUserData.gender || 'ذكر';
            document.getElementById('editBirthDateInput').value = currentUserData.birth_date || '';

            document.getElementById('editProfileModal').classList.add('active');
        }

        async function submitEditProfile(e) {
            e.preventDefault();
            const payload = {
                action: 'save_user',
                id: currentUserData.id,
                name: document.getElementById('editNameInput').value.trim(),
                email: document.getElementById('editEmailInput').value.trim(),
                phone: document.getElementById('editPhoneInput').value.trim(),
                passcode: document.getElementById('editPassInput').value.trim(),
                location: document.getElementById('editLocationInput').value.trim(),
                gender: document.getElementById('editGenderInput').value || 'ذكر',
                birth_date: document.getElementById('editBirthDateInput').value.trim(),
                is_admin: currentUserData.is_admin || 0,
                custom_fields: currentUserData.custom_fields || {}
            };

            try {
                const res = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.status === 'success') {
                    closeModal('editProfileModal');
                    loadUserProfile();
                    alert('تم حفظ البيانات بنجاح!');
                } else alert(data.message);
            } catch (err) { alert('تعذر حفظ البيانات'); }
        }

        function handleLogout() {
            localStorage.removeItem('brethren_active_user_id');
            localStorage.removeItem('brethren_is_admin');
            window.location.href = LOGIN_URL;
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }
    </script>
</body>

</html>