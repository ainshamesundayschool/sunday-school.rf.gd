<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>منصة الأخوة - الملف الشخصي والفعاليات</title>
    
    <!-- Google Fonts: Baloo Bhaijaan 2 & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+Bhaijaan+2:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Intelligent Search Utility -->
    <script src="/js/search_intelligent.js"></script>

    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #311042 100%);
            --glass-bg: rgba(255, 255, 255, 0.08);
            --glass-border: rgba(255, 255, 255, 0.15);
            --glass-card: rgba(255, 255, 255, 0.12);
            --primary: #8b5cf6;
            --primary-hover: #7c3aed;
            --accent: #f59e0b;
            --accent-glow: rgba(245, 158, 11, 0.35);
            --success: #10b981;
            --danger: #ef4444;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --r-sm: 8px;
            --r-md: 14px;
            --r-lg: 20px;
            --r-xl: 28px;
            --shadow-glow: 0 10px 30px -10px rgba(139, 92, 246, 0.4);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Baloo Bhaijaan 2', 'Inter', system-ui, -apple-system, sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        body {
            background: #0b0f19;
            background-image: var(--bg-gradient);
            background-attachment: fixed;
            color: var(--text-main);
            min-height: 100vh;
            padding-bottom: 60px;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px 16px;
        }

        /* Top Navigation Header */
        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            padding: 14px 20px;
            border-radius: var(--r-xl);
            margin-bottom: 24px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 1.35rem;
            font-weight: 800;
            color: #fff;
            text-decoration: none;
        }

        .brand-icon {
            width: 42px;
            height: 42px;
            border-radius: 12px;
            background: linear-gradient(135deg, #a855f7, #6366f1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-size: 1.2rem;
            box-shadow: 0 4px 15px rgba(168, 85, 247, 0.4);
        }

        .admin-link {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            padding: 8px 16px;
            border-radius: var(--r-md);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
            border: 1px solid var(--glass-border);
        }

        .admin-link:hover {
            background: var(--primary);
            box-shadow: var(--shadow-glow);
        }

        /* User Selector Search Header */
        .user-selector-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: var(--r-lg);
            padding: 16px;
            margin-bottom: 24px;
            position: relative;
        }

        .search-box {
            position: relative;
        }

        .search-box input {
            width: 100%;
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--glass-border);
            padding: 12px 45px 12px 16px;
            border-radius: var(--r-md);
            color: #fff;
            font-size: 0.98rem;
            outline: none;
            transition: all 0.3s;
        }

        .search-box input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.25);
        }

        .search-box i {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .search-results-dropdown {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: #1e293b;
            border: 1px solid var(--glass-border);
            border-radius: var(--r-md);
            margin-top: 6px;
            max-height: 250px;
            overflow-y: auto;
            z-index: 100;
            box-shadow: 0 12px 30px rgba(0, 0, 0, 0.4);
            display: none;
        }

        .search-item {
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            transition: background 0.2s;
        }

        .search-item:hover {
            background: rgba(139, 92, 246, 0.2);
        }

        .search-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #334155;
            object-fit: cover;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #fff;
        }

        /* User Profile Hero Card */
        .profile-card {
            background: var(--glass-card);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--r-xl);
            padding: 24px;
            margin-bottom: 28px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }

        .profile-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100%;
            height: 6px;
            background: linear-gradient(90deg, var(--primary), var(--accent), var(--success));
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

        .profile-avatar-wrapper {
            position: relative;
            flex-shrink: 0;
        }

        .profile-avatar {
            width: 95px;
            height: 95px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            background: linear-gradient(135deg, #475569, #1e293b);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: #fff;
        }

        .profile-meta {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .profile-name {
            font-size: 1.7rem;
            font-weight: 800;
            color: #fff;
            line-height: 1.2;
        }

        /* Points Badge */
        .points-badge {
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: #fff;
            padding: 8px 18px;
            border-radius: 50px;
            font-size: 1.15rem;
            font-weight: 800;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            box-shadow: 0 4px 20px var(--accent-glow);
            transition: transform 0.2s, box-shadow 0.2s;
            width: fit-content;
            margin-top: 4px;
        }

        .points-badge:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 25px var(--accent-glow);
        }

        /* Profile Details Grid (Small info under name/points) */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 12px;
            margin-top: 20px;
            padding-top: 18px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .info-item {
            background: rgba(15, 23, 42, 0.4);
            padding: 10px 14px;
            border-radius: var(--r-md);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        .info-label {
            font-size: 0.78rem;
            color: var(--text-muted);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 2px;
        }

        .info-value {
            font-size: 0.95rem;
            font-weight: 700;
            color: #fff;
            word-break: break-word;
        }

        .edit-info-btn {
            background: rgba(139, 92, 246, 0.2);
            color: #c084fc;
            border: 1px solid rgba(139, 92, 246, 0.4);
            padding: 8px 16px;
            border-radius: var(--r-md);
            font-weight: 700;
            font-size: 0.88rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 16px;
            transition: all 0.2s;
        }

        .edit-info-btn:hover {
            background: var(--primary);
            color: #fff;
        }

        /* QR Code Container */
        .qr-section {
            background: var(--glass-card);
            backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            border-radius: var(--r-xl);
            padding: 28px 20px;
            text-align: center;
            margin-bottom: 32px;
            box-shadow: 0 12px 35px rgba(0, 0, 0, 0.25);
        }

        .qr-title {
            font-size: 1.3rem;
            font-weight: 800;
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .qr-subtitle {
            font-size: 0.88rem;
            color: var(--text-muted);
            margin-bottom: 20px;
        }

        .qr-box {
            background: #ffffff;
            padding: 18px;
            border-radius: var(--r-lg);
            display: inline-block;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            max-width: 260px;
            width: 100%;
        }

        .qr-box img {
            width: 100%;
            height: auto;
            display: block;
            border-radius: 8px;
        }

        .user-code-tag {
            margin-top: 14px;
            display: inline-block;
            background: rgba(255, 255, 255, 0.1);
            padding: 6px 16px;
            border-radius: 50px;
            font-family: monospace;
            font-size: 1.05rem;
            font-weight: 700;
            letter-spacing: 1px;
            color: #e2e8f0;
        }

        /* Section Headings */
        .section-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 16px;
        }

        .section-title {
            font-size: 1.3rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
            color: #fff;
        }

        .section-title i {
            color: var(--primary);
        }

        .badge-count {
            background: var(--primary);
            color: #fff;
            padding: 2px 10px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 700;
        }

        /* Event Cards */
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }

        .event-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border: 1px solid var(--glass-border);
            border-radius: var(--r-lg);
            padding: 18px;
            transition: all 0.25s ease;
            position: relative;
            overflow: hidden;
        }

        .event-card:hover {
            transform: translateY(-4px);
            border-color: rgba(139, 92, 246, 0.4);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }

        .event-card.attended {
            border-color: rgba(16, 185, 129, 0.4);
            background: rgba(16, 185, 129, 0.06);
        }

        .event-status-pill {
            position: absolute;
            top: 14px;
            left: 14px;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 0.78rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .status-attended {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }

        .status-available {
            background: rgba(148, 163, 184, 0.15);
            color: #cbd5e1;
        }

        .event-name {
            font-size: 1.15rem;
            font-weight: 800;
            margin-bottom: 8px;
            color: #fff;
            padding-left: 75px; /* space for pill */
        }

        .event-date {
            font-size: 0.85rem;
            color: var(--text-muted);
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 10px;
        }

        .event-desc {
            font-size: 0.88rem;
            color: #cbd5e1;
            line-height: 1.4;
        }

        /* Modal Backdrop & Dialog */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.75);
            backdrop-filter: blur(8px);
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 16px;
        }

        .modal-card {
            background: #1e293b;
            border: 1px solid var(--glass-border);
            border-radius: var(--r-xl);
            width: 100%;
            max-width: 520px;
            max-height: 85vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            overflow: hidden;
            animation: modalIn 0.25s ease-out;
        }

        @keyframes modalIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }

        .modal-header {
            padding: 18px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .modal-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .modal-close {
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1.4rem;
            cursor: pointer;
            padding: 4px;
            transition: color 0.2s;
        }

        .modal-close:hover {
            color: #fff;
        }

        .modal-body {
            padding: 24px;
            overflow-y: auto;
            flex: 1;
        }

        /* Points History Timeline List */
        .history-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .history-item {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: var(--r-md);
            padding: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .history-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .history-reason {
            font-size: 0.95rem;
            font-weight: 700;
            color: #fff;
        }

        .history-date {
            font-size: 0.78rem;
            color: var(--text-muted);
        }

        .history-change {
            font-size: 1.1rem;
            font-weight: 800;
            padding: 4px 12px;
            border-radius: 50px;
        }

        .history-change.positive {
            background: rgba(16, 185, 129, 0.2);
            color: #34d399;
        }

        .history-change.negative {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }

        /* Form Controls */
        .form-group {
            margin-bottom: 16px;
        }

        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .form-input, .form-select {
            width: 100%;
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid var(--glass-border);
            padding: 12px 14px;
            border-radius: var(--r-md);
            color: #fff;
            font-size: 0.95rem;
            outline: none;
        }

        .form-input:focus, .form-select:focus {
            border-color: var(--primary);
        }

        .btn-submit {
            width: 100%;
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 14px;
            border-radius: var(--r-md);
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
            transition: background 0.2s;
            margin-top: 10px;
        }

        .btn-submit:hover {
            background: var(--primary-hover);
        }

        .empty-state {
            text-align: center;
            padding: 30px;
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        .empty-state i {
            font-size: 2.5rem;
            display: block;
            margin-bottom: 10px;
            opacity: 0.4;
        }

        /* Mobile Adjustments */
        @media (max-width: 600px) {
            .profile-top-row {
                flex-direction: column;
                text-align: center;
            }
            .profile-identity {
                flex-direction: column;
            }
            .points-badge {
                margin: 6px auto 0;
            }
            .info-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <!-- Top Navbar -->
        <header class="navbar">
            <a href="/brethren/" class="brand">
                <div class="brand-icon"><i class="fas fa-cross"></i></div>
                <span>منصة الأخوة</span>
            </a>
            <a href="/brethren/admin/" class="admin-link">
                <i class="fas fa-user-shield"></i>
                <span>لوحة التحكم</span>
            </a>
        </header>

        <!-- User Search / Selector -->
        <div class="user-selector-card">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="userSearchInput" placeholder="ابحث باسمك أو رقمك للانتقال لملفك..." oninput="handleUserSearch()">
            </div>
            <div class="search-results-dropdown" id="searchResults"></div>
        </div>

        <!-- Main User Profile Container -->
        <div id="profileContainer">
            <!-- Rendered dynamically -->
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
                    <i class="fas fa-history" style="color: var(--accent);"></i>
                    <span>سجل النقاط والمكافآت</span>
                </div>
                <button class="modal-close" onclick="closeModal('historyModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="history-list" id="historyList">
                    <!-- Dynamic Items -->
                </div>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div class="modal-overlay" id="editProfileModal">
        <div class="modal-card">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fas fa-user-edit" style="color: var(--primary);"></i>
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
                    
                    <!-- Dynamic Custom Fields Container -->
                    <div id="editCustomFieldsContainer"></div>

                    <button type="submit" class="btn-submit">حفظ التغييرات</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // mandatory absolute API path
        const API_URL = '/brethren/api.php';

        let currentUser = null;
        let allUsersList = [];
        let pointsHistory = [];

        document.addEventListener('DOMContentLoaded', () => {
            fetchAllUsers();
            
            // Check if user ID is saved in URL or localStorage
            const urlParams = new URLSearchParams(window.location.search);
            const paramId = urlParams.get('id');
            const savedId = paramId || localStorage.getItem('brethren_active_user_id');

            if (savedId) {
                loadUserProfile(savedId);
            } else {
                // Fetch first user if available
                loadUserProfile(null);
            }
        });

        async function fetchAllUsers() {
            try {
                const res = await fetch(`${API_URL}?action=get_users`);
                const data = await res.json();
                if (data.status === 'success') {
                    allUsersList = data.users || [];
                    if (!currentUser && allUsersList.length > 0) {
                        const savedId = localStorage.getItem('brethren_active_user_id');
                        const target = savedId ? allUsersList.find(u => u.id == savedId) : allUsersList[0];
                        if (target) loadUserProfile(target.id);
                    }
                }
            } catch (err) {
                console.error('Error fetching users:', err);
            }
        }

        // Intelligent Search Implementation
        function handleUserSearch() {
            const query = document.getElementById('userSearchInput').value.trim();
            const dropdown = document.getElementById('searchResults');

            if (!query) {
                dropdown.style.display = 'none';
                return;
            }

            // Score users using search_intelligent.js getMatchScore
            const scored = allUsersList.map(u => ({
                ...u,
                _score: (typeof getMatchScore === 'function') 
                    ? getMatchScore(u, query, [
                        { val: u.name, weight: 1.0 },
                        { val: u.phone, weight: 1.1 },
                        { val: u.location, weight: 0.8 }
                      ])
                    : (u.name.toLowerCase().includes(query.toLowerCase()) ? 1 : 0)
            })).filter(u => u._score > 0)
               .sort((a, b) => b._score - a._score);

            if (scored.length === 0) {
                dropdown.innerHTML = '<div class="search-item" style="color:var(--text-muted)">لا توجد نتائج مطابقة</div>';
            } else {
                dropdown.innerHTML = scored.slice(0, 6).map(u => `
                    <div class="search-item" onclick="selectUser(${u.id})">
                        <div class="search-avatar">${u.photo ? `<img src="${u.photo}" style="width:100%;height:100%;border-radius:50%">` : u.name.charAt(0)}</div>
                        <div>
                            <div style="font-weight:700;color:#fff">${u.name}</div>
                            <div style="font-size:0.78rem;color:var(--text-muted)">${u.phone || ''}</div>
                        </div>
                    </div>
                `).join('');
            }
            dropdown.style.display = 'block';
        }

        function selectUser(id) {
            document.getElementById('searchResults').style.display = 'none';
            document.getElementById('userSearchInput').value = '';
            loadUserProfile(id);
        }

        async function loadUserProfile(userId) {
            const container = document.getElementById('profileContainer');
            try {
                let url = `${API_URL}?action=get_user`;
                if (userId) url += `&id=${userId}`;

                const res = await fetch(url);
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
                <!-- Profile Card -->
                <div class="profile-card">
                    <div class="profile-top-row">
                        <div class="profile-identity">
                            <div class="profile-avatar-wrapper">
                                ${user.photo 
                                    ? `<img src="${user.photo}" class="profile-avatar" alt="${user.name}">` 
                                    : `<div class="profile-avatar"><i class="fas fa-user"></i></div>`
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

                    <!-- Info Grid Small Under Name -->
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

                <!-- Big QR Code Section -->
                <div class="qr-section">
                    <div class="qr-title"><i class="fas fa-qrcode" style="color:var(--primary);"></i> رمز الاستجابة السريعة (QR Code)</div>
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
                            <div class="history-info">
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
                    fetchAllUsers();
                } else {
                    alert(data.message || 'حدث خطأ أثناء الحفظ');
                }
            } catch (err) {
                console.error(err);
                alert('تعذر الاتصال بالخادم');
            }
        }

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        window.onclick = function(e) {
            if (e.target.classList.contains('modal-overlay')) {
                e.target.style.display = 'none';
            }
        };
    </script>
</body>
</html>
