<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الملف الشخصي - منصة الأخوة</title>
    
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
            max-width: 820px;
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
            margin-bottom: 20px;
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
            background: var(--surface-2);
            color: var(--text-2);
            padding: 8px 14px;
            border-radius: var(--r-md);
            text-decoration: none;
            font-weight: 800;
            font-size: 0.85rem;
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

        /* Profile Hero Card */
        .profile-card {
            background: var(--surface);
            border: 1px solid var(--border-solid);
            border-radius: var(--r-xl);
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }

        .profile-card::before {
            content: '';
            position: absolute;
            top: 0; right: 0; width: 100%; height: 6px;
            background: linear-gradient(90deg, var(--brand), #8b5cf6, var(--warning));
        }

        .profile-top-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            direction: rtl; /* Right aligned profile picture */
        }

        .profile-identity {
            display: flex;
            align-items: center;
            gap: 20px;
            flex: 1;
        }

        .profile-avatar {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--brand-bg);
            box-shadow: 0 6px 18px rgba(91, 108, 245, 0.2);
            background: var(--surface-3);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.4rem;
            color: var(--brand);
            font-weight: 800;
        }

        .profile-meta {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .profile-name {
            font-size: 1.6rem;
            font-weight: 900;
            color: var(--text);
            line-height: 1.2;
        }

        .points-badge {
            background: linear-gradient(135deg, var(--warning), #d97706);
            color: #fff;
            padding: 8px 18px;
            border-radius: var(--r-full);
            font-size: 1.1rem;
            font-weight: 900;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            box-shadow: 0 4px 16px rgba(245, 158, 11, 0.35);
            transition: transform 0.2s;
            width: fit-content;
            margin-top: 4px;
        }

        .points-badge:hover { transform: scale(1.05); }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
            gap: 12px;
            margin-top: 20px;
            padding-top: 18px;
            border-top: 1px solid var(--border-solid);
        }

        .info-item {
            background: var(--surface-2);
            padding: 10px 14px;
            border-radius: var(--r-md);
            border: 1px solid var(--border-solid);
        }

        .info-label {
            font-size: 0.76rem;
            color: var(--text-3);
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 2px;
        }

        .info-value {
            font-size: 0.92rem;
            font-weight: 800;
            color: var(--text);
            word-break: break-word;
        }

        .edit-info-btn {
            background: var(--brand-bg);
            color: var(--brand-dark);
            border: 1px solid rgba(91, 108, 245, 0.25);
            padding: 8px 16px;
            border-radius: var(--r-md);
            font-weight: 800;
            font-size: 0.85rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 16px;
            transition: all 0.2s;
        }

        .edit-info-btn:hover {
            background: var(--brand);
            color: #fff;
        }

        /* Big QR Code Section */
        .qr-section {
            background: var(--surface);
            border: 1px solid var(--border-solid);
            border-radius: var(--r-xl);
            padding: 24px 20px;
            text-align: center;
            margin-bottom: 28px;
            box-shadow: var(--shadow-md);
        }

        .qr-title {
            font-size: 1.25rem;
            font-weight: 900;
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            color: var(--text);
        }

        .qr-subtitle {
            font-size: 0.85rem;
            color: var(--text-3);
            margin-bottom: 18px;
            font-weight: 600;
        }

        .qr-box {
            background: #ffffff;
            padding: 16px;
            border-radius: var(--r-lg);
            display: inline-block;
            border: 2px solid var(--border-solid);
            box-shadow: var(--shadow-sm);
            max-width: 240px;
            width: 100%;
        }

        .qr-box img {
            width: 100%; height: auto; display: block; border-radius: 8px;
        }

        .user-code-tag {
            margin-top: 12px;
            display: inline-block;
            background: var(--surface-3);
            padding: 6px 16px;
            border-radius: var(--r-full);
            font-family: monospace;
            font-size: 1rem;
            font-weight: 800;
            color: var(--brand-dark);
            border: 1px solid var(--border-solid);
        }

        /* Event Cards */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 14px;
        }

        .section-title {
            font-size: 1.2rem;
            font-weight: 900;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text);
        }

        .section-title i { color: var(--brand); }

        .badge-count {
            background: var(--brand-bg);
            color: var(--brand-dark);
            padding: 2px 10px;
            border-radius: var(--r-full);
            font-size: 0.82rem;
            font-weight: 800;
            border: 1px solid rgba(91, 108, 245, 0.2);
        }

        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(270px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .event-card {
            background: var(--surface);
            border: 1px solid var(--border-solid);
            border-radius: var(--r-lg);
            padding: 18px;
            position: relative;
            box-shadow: var(--shadow-sm);
        }

        .event-card.attended {
            border-color: rgba(16, 185, 129, 0.4);
            background: var(--success-bg);
        }

        .event-status-pill {
            position: absolute;
            top: 14px; left: 14px;
            padding: 4px 10px;
            border-radius: var(--r-full);
            font-size: 0.75rem;
            font-weight: 800;
        }

        .status-attended { background: #10b981; color: #fff; }
        .status-available { background: var(--surface-3); color: var(--text-2); border: 1px solid var(--border-solid); }

        .event-name {
            font-size: 1.1rem;
            font-weight: 900;
            margin-bottom: 6px;
            color: var(--text);
            padding-left: 80px;
        }

        .event-date {
            font-size: 0.82rem;
            color: var(--text-3);
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 8px;
            font-weight: 600;
        }

        .event-desc { font-size: 0.85rem; color: var(--text-2); line-height: 1.4; }

        /* Modal Overlay & Forms */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(6px);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .modal-card {
            background: var(--surface);
            border: 1px solid var(--border-solid);
            border-radius: var(--r-xl);
            width: 100%;
            max-width: 520px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }

        .modal-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--border-solid);
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--surface-2);
        }

        .modal-title {
            font-size: 1.15rem;
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
        }

        .modal-body { padding: 20px; overflow-y: auto; flex: 1; }

        .history-list { display: flex; flex-direction: column; gap: 10px; }

        .history-item {
            background: var(--surface-2);
            border: 1px solid var(--border-solid);
            border-radius: var(--r-md);
            padding: 12px 16px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .history-reason { font-size: 0.92rem; font-weight: 800; color: var(--text); }
        .history-date { font-size: 0.76rem; color: var(--text-3); font-weight: 600; }
        .history-change { font-size: 1.05rem; font-weight: 900; padding: 4px 12px; border-radius: var(--r-full); }
        .history-change.positive { background: var(--success-bg); color: var(--success-dark); }
        .history-change.negative { background: var(--danger-bg); color: var(--danger-dark); }

        .form-group { margin-bottom: 14px; }
        .form-label { display: block; font-size: 0.82rem; font-weight: 800; color: var(--text-2); margin-bottom: 6px; }
        .form-input, .form-select {
            width: 100%; background: var(--surface-2); border: 1.5px solid var(--border-solid);
            padding: 10px 14px; border-radius: var(--r-md); color: var(--text); font-size: 0.92rem; font-weight: 600; outline: none;
        }

        .btn-submit {
            width: 100%; background: var(--brand); color: #fff; border: none; padding: 12px;
            border-radius: var(--r-md); font-size: 0.95rem; font-weight: 900; cursor: pointer; margin-top: 10px;
        }

        .empty-state { text-align: center; padding: 24px; color: var(--text-3); font-size: 0.9rem; font-weight: 600; }
        .empty-state i { font-size: 2.2rem; display: block; margin-bottom: 8px; color: var(--brand-light); }
    </style>
</head>
<body>

    <div class="container">
        <!-- Top Navbar -->
        <header class="topbar">
            <a href="#" id="homeBrandLink" class="brand">
                <div class="brand-icon"><i class="fas fa-cross"></i></div>
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
                <form id="editProfileForm" onsubmit="submitProfileEdit(event)">
                    <input type="hidden" id="editUserId">
                    <div class="form-group">
                        <label class="form-label">الاسم بالكامل</label>
                        <input type="text" id="editName" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">رقم الهاتف</label>
                        <input type="text" id="editPhone" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">العنوان / المنطقة</label>
                        <input type="text" id="editLocation" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">النوع</label>
                        <select id="editGender" class="form-select">
                            <option value="شاب">شاب</option>
                            <option value="شابة">شابة</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">تاريخ الميلاد (شهر/سنة أو يوم/شهر/سنة)</label>
                        <input type="text" id="editBirthDate" class="form-input" placeholder="مثال: 05/2000 أو 15/05/2000">
                    </div>
                    
                    <div id="editCustomFieldsContainer"></div>

                    <button type="submit" class="btn-submit">حفظ التغييرات</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const isBrethrenSubfolder = window.location.pathname.includes('/brethren');
        const API_URL = isBrethrenSubfolder ? '/brethren/api.php' : '/api.php';
        const HOME_URL = isBrethrenSubfolder ? '/brethren/' : '/';
        const LOGIN_URL = isBrethrenSubfolder ? '/brethren/login/' : '/login/';

        document.getElementById('homeBrandLink').href = HOME_URL;
        document.getElementById('homeNavLink').href = HOME_URL;

        let currentUser = null;
        let pointsHistory = [];

        document.addEventListener('DOMContentLoaded', () => {
            const urlParams = new URLSearchParams(window.location.search);
            const paramId = urlParams.get('id');
            const savedId = paramId || localStorage.getItem('brethren_active_user_id');

            if (savedId) {
                loadUserProfile(savedId);
            } else {
                window.location.href = LOGIN_URL;
            }
        });

        async function loadUserProfile(userId) {
            const container = document.getElementById('profileContainer');
            try {
                const res = await fetch(`${API_URL}?action=get_user&id=${userId}`);
                const data = await res.json();

                if (data.status === 'success') {
                    currentUser = data.user;
                    pointsHistory = data.history || [];
                    localStorage.setItem('brethren_active_user_id', currentUser.id);

                    renderProfile(data.user);
                    renderEvents(data.attended_events || [], data.all_events || []);
                } else {
                    container.innerHTML = `<div class="empty-state"><i class="fas fa-exclamation-circle"></i> ${data.message}</div>`;
                }
            } catch (err) {
                console.error(err);
                container.innerHTML = `<div class="empty-state"><i class="fas fa-wifi"></i> تعذر الاتصال بالخادم</div>`;
            }
        }

        function renderProfile(user) {
            const container = document.getElementById('profileContainer');
            const customFields = user.custom_fields || {};

            let customHtml = '';
            for (const [key, val] of Object.entries(customFields)) {
                if (val) {
                    customHtml += `
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-info-circle"></i> ${key}</div>
                            <div class="info-value">${val}</div>
                        </div>
                    `;
                }
            }

            const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=${encodeURIComponent(user.user_code)}`;

            container.innerHTML = `
                <div class="profile-card">
                    <div class="profile-top-row">
                        <div class="profile-identity">
                            <div class="profile-avatar-wrapper">
                                ${user.photo 
                                    ? `<img src="${user.photo}" class="profile-avatar" alt="${user.name}">` 
                                    : `<div class="profile-avatar">${user.name.charAt(0)}</div>`
                                }
                            </div>
                            <div class="profile-meta">
                                <h2 class="profile-name">${user.name}</h2>
                                <div class="points-badge" onclick="openHistoryModal()" title="انقر لعرض سجل النقاط">
                                    <i class="fas fa-star"></i>
                                    <span>${user.points} نقطة</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-phone"></i> رقم الهاتف</div>
                            <div class="info-value">${user.phone || 'غير محدد'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-map-marker-alt"></i> المنطقة / السكن</div>
                            <div class="info-value">${user.location || 'غير محدد'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-venus-mars"></i> النوع</div>
                            <div class="info-value">${user.gender || 'غير محدد'}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label"><i class="fas fa-birthday-cake"></i> تاريخ الميلاد</div>
                            <div class="info-value">${user.birth_date || 'غير محدد'}</div>
                        </div>
                        ${customHtml}
                    </div>

                    <button class="edit-info-btn" onclick="openEditProfileModal()">
                        <i class="fas fa-edit"></i> تعديل البيانات
                    </button>
                </div>

                <div class="qr-section">
                    <div class="qr-title"><i class="fas fa-qrcode" style="color:var(--brand);"></i> رمز الاستجابة السريعة (QR Code)</div>
                    <div class="qr-subtitle">أبرز هذا الكود لخدمة الحضور والتسجيل في الفعاليات</div>
                    <div class="qr-box">
                        <img src="${qrUrl}" alt="QR Code">
                    </div>
                    <div>
                        <span class="user-code-tag">${user.user_code}</span>
                    </div>
                </div>
            `;
        }

        function renderEvents(attended, all) {
            const attGrid = document.getElementById('attendedEventsGrid');
            const availGrid = document.getElementById('availableEventsGrid');

            document.getElementById('attendedEventsCount').innerText = attended.length;
            document.getElementById('availableEventsCount').innerText = all.length;

            if (attended.length === 0) {
                attGrid.innerHTML = `<div class="empty-state"><i class="fas fa-calendar-times"></i> لم تسجل حضور في أي فعالية حتى الآن</div>`;
            } else {
                attGrid.innerHTML = attended.map(ev => `
                    <div class="event-card attended">
                        <span class="event-status-pill status-attended"><i class="fas fa-check-circle"></i> تم الحضور (+20)</span>
                        <div class="event-name">${ev.event_name}</div>
                        <div class="event-date"><i class="far fa-calendar"></i> ${ev.event_date}</div>
                        ${ev.description ? `<div class="event-desc">${ev.description}</div>` : ''}
                    </div>
                `).join('');
            }

            if (all.length === 0) {
                availGrid.innerHTML = `<div class="empty-state"><i class="fas fa-calendar"></i> لا توجد فعاليات متوفرة حالياً</div>`;
            } else {
                availGrid.innerHTML = all.map(ev => `
                    <div class="event-card ${ev.is_attended ? 'attended' : ''}">
                        <span class="event-status-pill ${ev.is_attended ? 'status-attended' : 'status-available'}">
                            ${ev.is_attended ? '<i class="fas fa-check"></i> تم الحضور' : '<i class="far fa-clock"></i> متاحة'}
                        </span>
                        <div class="event-name">${ev.event_name}</div>
                        <div class="event-date"><i class="far fa-calendar"></i> ${ev.event_date}</div>
                        ${ev.description ? `<div class="event-desc">${ev.description}</div>` : ''}
                    </div>
                `).join('');
            }
        }

        function openHistoryModal() {
            const list = document.getElementById('historyList');
            if (pointsHistory.length === 0) {
                list.innerHTML = `<div class="empty-state"><i class="fas fa-history"></i> لا يوجد سجل نقاط بعد</div>`;
            } else {
                list.innerHTML = pointsHistory.map(item => {
                    const isPos = item.points_change > 0;
                    return `
                        <div class="history-item">
                            <div>
                                <div class="history-reason">${item.reason}</div>
                                <div class="history-date">${item.created_at}</div>
                            </div>
                            <div class="history-change ${isPos ? 'positive' : 'negative'}">
                                ${isPos ? '+' : ''}${item.points_change}
                            </div>
                        </div>
                    `;
                }).join('');
            }
            document.getElementById('historyModal').style.display = 'flex';
        }

        function openEditProfileModal() {
            if (!currentUser) return;
            document.getElementById('editUserId').value = currentUser.id;
            document.getElementById('editName').value = currentUser.name;
            document.getElementById('editPhone').value = currentUser.phone || '';
            document.getElementById('editLocation').value = currentUser.location || '';
            document.getElementById('editGender').value = currentUser.gender || 'شاب';
            document.getElementById('editBirthDate').value = currentUser.birth_date || '';

            const container = document.getElementById('editCustomFieldsContainer');
            container.innerHTML = '';
            const customFields = currentUser.custom_fields || {};
            for (const [key, val] of Object.entries(customFields)) {
                container.innerHTML += `
                    <div class="form-group">
                        <label class="form-label">${key}</label>
                        <input type="text" data-custom-key="${key}" class="form-input custom-field-input" value="${val}">
                    </div>
                `;
            }

            document.getElementById('editProfileModal').style.display = 'flex';
        }

        async function submitProfileEdit(e) {
            e.preventDefault();
            if (!currentUser) return;

            const customFields = {};
            document.querySelectorAll('.custom-field-input').forEach(inp => {
                const key = inp.getAttribute('data-custom-key');
                if (key) customFields[key] = inp.value.trim();
            });

            const payload = {
                action: 'save_user',
                id: currentUser.id,
                name: document.getElementById('editName').value.trim(),
                phone: document.getElementById('editPhone').value.trim(),
                location: document.getElementById('editLocation').value.trim(),
                gender: document.getElementById('editGender').value,
                birth_date: document.getElementById('editBirthDate').value.trim(),
                custom_fields: customFields
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
                    loadUserProfile(currentUser.id);
                } else {
                    alert(data.message || 'حدث خطأ أثناء الحفظ');
                }
            } catch (err) {
                console.error(err);
                alert('تعذر الاتصال بالخادم');
            }
        }

        function handleLogout() {
            localStorage.removeItem('brethren_active_user_id');
            window.location.href = LOGIN_URL;
        }

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }
    </script>
</body>
</html>
