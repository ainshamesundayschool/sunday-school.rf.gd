<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>لوحة التحكم - منصة الأخوة</title>
    
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+Bhaijaan+2:wght@400;500;600;700;800&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Intelligent Search & QR Scanner -->
    <script src="/js/search_intelligent.js"></script>
    <script src="/js/qr-scanner.umd.min.js"></script>

    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #311042 100%);
            --glass-bg: rgba(255, 255, 255, 0.08);
            --glass-border: rgba(255, 255, 255, 0.15);
            --glass-card: rgba(255, 255, 255, 0.12);
            --primary: #8b5cf6;
            --primary-hover: #7c3aed;
            --accent: #f59e0b;
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
            max-width: 1000px;
            margin: 0 auto;
            padding: 20px 16px;
        }

        /* Top Admin Navigation Header */
        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
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

        .portal-link {
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
            border: 1px solid var(--glass-border);
            transition: all 0.2s;
        }

        .portal-link:hover {
            background: var(--primary);
        }

        /* Navigation Tabs */
        .nav-tabs {
            display: flex;
            gap: 10px;
            overflow-x: auto;
            padding-bottom: 8px;
            margin-bottom: 24px;
            scrollbar-width: none;
        }

        .nav-tabs::-webkit-scrollbar {
            display: none;
        }

        .tab-btn {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            color: var(--text-muted);
            padding: 12px 20px;
            border-radius: var(--r-lg);
            font-size: 0.98rem;
            font-weight: 700;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
            transition: all 0.25s;
        }

        .tab-btn:hover {
            color: #fff;
            background: rgba(255, 255, 255, 0.12);
        }

        .tab-btn.active {
            background: var(--primary);
            color: #fff;
            border-color: var(--primary);
            box-shadow: var(--shadow-glow);
        }

        /* Tab Content Area */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease-in-out;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(6px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Cards & Containers */
        .panel-card {
            background: var(--glass-card);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: var(--r-xl);
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 16px 40px rgba(0, 0, 0, 0.25);
        }

        .card-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .card-title {
            font-size: 1.25rem;
            font-weight: 800;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .card-title i {
            color: var(--primary);
        }

        /* User Table & Grid */
        .search-box {
            position: relative;
            margin-bottom: 20px;
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
        }

        .search-box input:focus {
            border-color: var(--primary);
        }

        .search-box i {
            position: absolute;
            right: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }

        .user-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 16px;
        }

        .user-card {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: var(--r-lg);
            padding: 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            transition: all 0.2s;
        }

        .user-card:hover {
            border-color: rgba(139, 92, 246, 0.4);
            transform: translateY(-3px);
        }

        .user-card-header {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 12px;
        }

        .user-card-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #334155;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            font-weight: 700;
            color: #fff;
            object-fit: cover;
        }

        .user-card-name {
            font-size: 1.1rem;
            font-weight: 800;
            color: #fff;
        }

        .user-card-sub {
            font-size: 0.8rem;
            color: var(--text-muted);
        }

        .user-card-points {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
            padding: 4px 10px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 800;
            display: inline-block;
            margin-top: 6px;
        }

        .user-card-actions {
            display: flex;
            gap: 8px;
            margin-top: 14px;
            padding-top: 12px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
        }

        .action-btn {
            flex: 1;
            padding: 8px;
            border-radius: var(--r-md);
            border: none;
            font-weight: 700;
            font-size: 0.82rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            transition: opacity 0.2s;
        }

        .action-btn:hover {
            opacity: 0.88;
        }

        .btn-edit { background: rgba(139, 92, 246, 0.25); color: #c084fc; }
        .btn-delete { background: rgba(239, 68, 68, 0.2); color: #f87171; }
        .btn-add-pt { background: rgba(16, 185, 129, 0.2); color: #34d399; }

        /* Form Layouts */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 16px;
            margin-bottom: 16px;
        }

        .form-group {
            margin-bottom: 14px;
        }

        .form-label {
            display: block;
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--text-muted);
            margin-bottom: 6px;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            background: rgba(15, 23, 42, 0.7);
            border: 1px solid var(--glass-border);
            padding: 12px 14px;
            border-radius: var(--r-md);
            color: #fff;
            font-size: 0.95rem;
            outline: none;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            border-color: var(--primary);
        }

        .btn-primary {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: var(--r-md);
            font-size: 0.98rem;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.2s;
        }

        .btn-primary:hover {
            background: var(--primary-hover);
        }

        /* Bulk Import Textarea */
        .bulk-textarea {
            height: 160px;
            font-family: monospace;
            font-size: 0.88rem;
            line-height: 1.5;
            white-space: pre;
        }

        /* Events Cards */
        .events-list-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 18px;
        }

        .event-admin-card {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--glass-border);
            border-radius: var(--r-lg);
            padding: 20px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .event-admin-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 8px;
        }

        .event-admin-title {
            font-size: 1.2rem;
            font-weight: 800;
            color: #fff;
        }

        .event-attendance-badge {
            background: rgba(139, 92, 246, 0.2);
            color: #c084fc;
            padding: 4px 12px;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 800;
        }

        .event-date-text {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-bottom: 14px;
        }

        .btn-scan {
            background: linear-gradient(135deg, #10b981, #059669);
            color: #fff;
            border: none;
            padding: 12px;
            border-radius: var(--r-md);
            font-size: 1rem;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
            transition: transform 0.2s;
            margin-top: 12px;
        }

        .btn-scan:hover {
            transform: scale(1.02);
        }

        /* Points Component & Shortcuts */
        .points-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
        }

        .gear-btn {
            background: rgba(255, 255, 255, 0.1);
            color: #fff;
            border: 1px solid var(--glass-border);
            width: 40px;
            height: 40px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            transition: all 0.2s;
        }

        .gear-btn:hover {
            background: var(--primary);
            transform: rotate(45deg);
        }

        .mode-toggle {
            display: flex;
            background: rgba(15, 23, 42, 0.8);
            border-radius: var(--r-md);
            padding: 4px;
            gap: 4px;
            margin-bottom: 20px;
        }

        .mode-btn {
            flex: 1;
            padding: 10px;
            border: none;
            background: none;
            color: var(--text-muted);
            font-weight: 800;
            font-size: 0.95rem;
            border-radius: var(--r-sm);
            cursor: pointer;
            transition: all 0.2s;
        }

        .mode-btn.active.add {
            background: var(--success);
            color: #fff;
        }

        .mode-btn.active.deduct {
            background: var(--danger);
            color: #fff;
        }

        .shortcuts-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 20px;
        }

        .shortcut-chip {
            background: rgba(139, 92, 246, 0.15);
            border: 1px solid rgba(139, 92, 246, 0.3);
            color: #c084fc;
            padding: 16px;
            border-radius: var(--r-md);
            font-size: 1.3rem;
            font-weight: 800;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .shortcut-chip:hover {
            background: var(--primary);
            color: #fff;
            transform: scale(1.05);
        }

        /* Modal Overlay & Scanner */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0, 0, 0, 0.85);
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
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.5);
            overflow: hidden;
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
        }

        .modal-body {
            padding: 24px;
            overflow-y: auto;
        }

        #qrVideoContainer {
            width: 100%;
            height: 280px;
            background: #000;
            border-radius: var(--r-lg);
            overflow: hidden;
            position: relative;
            margin-bottom: 16px;
        }

        #qrVideo {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .scan-feedback-banner {
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid rgba(16, 185, 129, 0.4);
            padding: 14px;
            border-radius: var(--r-md);
            display: none;
            align-items: center;
            gap: 14px;
            margin-top: 12px;
        }

        .empty-state {
            text-align: center;
            padding: 30px;
            color: var(--text-muted);
            font-size: 0.95rem;
        }

        @media (max-width: 600px) {
            .shortcuts-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>

    <div class="container">
        <!-- Top Navbar -->
        <header class="navbar">
            <a href="/brethren/admin/" class="brand">
                <div class="brand-icon"><i class="fas fa-shield-alt"></i></div>
                <span>لوحة التحكم - الأخوة</span>
            </a>
            <a href="/brethren/" class="portal-link">
                <i class="fas fa-external-link-alt"></i>
                <span>صفحة المستخدمين</span>
            </a>
        </header>

        <!-- Navigation Tabs -->
        <nav class="nav-tabs">
            <button class="tab-btn active" onclick="switchTab('tabUsers')">
                <i class="fas fa-users"></i> إدارة المستخدمين
            </button>
            <button class="tab-btn" onclick="switchTab('tabBulk')">
                <i class="fas fa-file-csv"></i> التحميل الجماعي (شيت)
            </button>
            <button class="tab-btn" onclick="switchTab('tabEvents')">
                <i class="fas fa-calendar-star"></i> الفعاليات ومسح QR
            </button>
            <button class="tab-btn" onclick="switchTab('tabPoints')">
                <i class="fas fa-coins"></i> إدارة النقاط
            </button>
        </nav>

        <!-- ───────────────────────────────────────────────────────────── -->
        <!-- TAB 1: USERS MANAGEMENT -->
        <!-- ───────────────────────────────────────────────────────────── -->
        <div class="tab-content active" id="tabUsers">
            <div class="panel-card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-user-plus"></i> إضافة مستخدم جديد
                    </div>
                </div>
                <form id="addUserForm" onsubmit="submitAddUser(event)">
                    <input type="hidden" id="userIdInput" value="0">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">الاسم بالكامل *</label>
                            <input type="text" id="userNameInput" class="form-input" required placeholder="مثال: بيتر فايز">
                        </div>
                        <div class="form-group">
                            <label class="form-label">رقم الهاتف</label>
                            <input type="text" id="userPhoneInput" class="form-input" placeholder="012xxxxxxxx">
                        </div>
                        <div class="form-group">
                            <label class="form-label">المنطقة / السكن</label>
                            <input type="text" id="userLocationInput" class="form-input" placeholder="مثال: عين شمس">
                        </div>
                        <div class="form-group">
                            <label class="form-label">النوع</label>
                            <select id="userGenderInput" class="form-select">
                                <option value="شاب">شاب</option>
                                <option value="شابة">شابة</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">تاريخ الميلاد</label>
                            <input type="text" id="userBirthDateInput" class="form-input" placeholder="شهر/سنة أو يوم/شهر/سنة">
                        </div>
                    </div>

                    <!-- Custom Fields Input Container -->
                    <div id="addUserCustomFields"></div>

                    <button type="submit" class="btn-primary" id="saveUserBtn">
                        <i class="fas fa-plus"></i> حفظ المستخدم
                    </button>
                </form>
            </div>

            <!-- Users List Panel -->
            <div class="panel-card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-list-ul"></i> قائمة المستخدمين
                    </div>
                </div>
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="adminUserSearch" placeholder="بحث ذكي عن مستخدم بالاسم أو الهاتف أو المنطقة..." oninput="renderUsersList()">
                </div>

                <div class="user-grid" id="usersListGrid">
                    <div class="empty-state">جاري التحميل...</div>
                </div>
            </div>
        </div>

        <!-- ───────────────────────────────────────────────────────────── -->
        <!-- TAB 2: BULK IMPORT (GOOGLE SHEETS) -->
        <!-- ───────────────────────────────────────────────────────────── -->
        <div class="tab-content" id="tabBulk">
            <div class="panel-card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-file-import"></i> التحميل الجماعي عبر Google Sheets / Excel
                    </div>
                </div>
                <p style="color:var(--text-muted); font-size:0.9rem; margin-bottom:16px; line-height:1.5;">
                    انسخ الجدول مباشرة من <strong>Google Sheets</strong> أو ملف Excel والصقه في الخانة بالأسفل.<br>
                    • الأعمدة الأساسية: (الاسم - الهاتف/الموبايل - السكن/العنوان - النوع - تاريخ الميلاد).<br>
                    • <strong>أي أعمدة إضافية أو مجهولة في الشيت سيتم اعتبارها تلقائياً كمعلومات مخصصة (Custom Fields)</strong> وتضاف للعمود المقابل!
                </p>

                <div class="form-group">
                    <label class="form-label">لصق الجدول المنسوخ (Tab Separated / CSV)</label>
                    <textarea id="bulkPasteInput" class="form-textarea bulk-textarea" placeholder="مثال:
الاسم	رقم الهاتف	العنوان	السنة الدراسية	الخدمة
بيتر فايز	01200000000	عين شمس	ثالثة كليّة	كشافة
كيرلس مجدي	01100000000	مصر الجديدة	ثانية كليّة	شبان"></textarea>
                </div>

                <button class="btn-primary" onclick="processBulkImport()">
                    <i class="fas fa-cloud-upload-alt"></i> استيراد المستخدمين
                </button>
            </div>
        </div>

        <!-- ───────────────────────────────────────────────────────────── -->
        <!-- TAB 3: EVENTS & ATTENDANCE SCANNER -->
        <!-- ───────────────────────────────────────────────────────────── -->
        <div class="tab-content" id="tabEvents">
            <div class="panel-card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-calendar-plus"></i> إنشاء فعالية جديدة
                    </div>
                </div>
                <form id="createEventForm" onsubmit="submitCreateEvent(event)">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">اسم الفعالية *</label>
                            <input type="text" id="eventNameInput" class="form-input" required placeholder="مثال: اجتماع الأحد - درس الكتاب">
                        </div>
                        <div class="form-group">
                            <label class="form-label">تاريخ الفعالية</label>
                            <input type="date" id="eventDateInput" class="form-input">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">الوصف / ملاحظات</label>
                        <input type="text" id="eventDescInput" class="form-input" placeholder="وصف اختياري للفعالية">
                    </div>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-check"></i> إنشاء الفعالية
                    </button>
                </form>
            </div>

            <div class="panel-card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-calendar-day"></i> الفعاليات المتاحة ومسح الحضور
                    </div>
                </div>
                <div class="events-list-grid" id="adminEventsGrid">
                    <div class="empty-state">جاري التحميل...</div>
                </div>
            </div>
        </div>

        <!-- ───────────────────────────────────────────────────────────── -->
        <!-- TAB 4: POINTS MANAGEMENT -->
        <!-- ───────────────────────────────────────────────────────────── -->
        <div class="tab-content" id="tabPoints">
            <div class="panel-card">
                <div class="points-panel-header">
                    <div class="card-title">
                        <i class="fas fa-coins" style="color:var(--accent);"></i> إضافة وتخصيص النقاط
                    </div>
                    <button class="gear-btn" onclick="openSettingsModal()" title="إعدادات النقاط واختيارات الأسباب">
                        <i class="fas fa-cog"></i>
                    </button>
                </div>

                <!-- Select Target User -->
                <div class="form-group">
                    <label class="form-label">اختر المستخدم المستهدف *</label>
                    <select id="pointsTargetUserSelect" class="form-select"></select>
                </div>

                <!-- Add / Deduct Mode Toggle -->
                <div class="mode-toggle">
                    <button class="mode-btn active add" id="modeAddBtn" onclick="setPointsMode('add')">
                        <i class="fas fa-plus-circle"></i> إضافة نقاط (+)
                    </button>
                    <button class="mode-btn deduct" id="modeDeductBtn" onclick="setPointsMode('deduct')">
                        <i class="fas fa-minus-circle"></i> خصم نقاط (-)
                    </button>
                </div>

                <!-- Shortcuts Section -->
                <div id="shortcutsContainer">
                    <label class="form-label">اختصارات سريعة للنقاط</label>
                    <div class="shortcuts-grid" id="shortcutsGrid">
                        <!-- Dynamic Chips (10, 30, 50, 100) -->
                    </div>
                </div>

                <!-- Custom Amount Tab -->
                <div id="customPointsContainer" class="form-group">
                    <label class="form-label">أو ادخل عدد نقاط مخصص</label>
                    <input type="number" id="customPointsInput" class="form-input" placeholder="مثال: 25">
                </div>

                <!-- Reason Select & Other Text -->
                <div class="form-group">
                    <label class="form-label">سبب إعطاء/خصم النقاط</label>
                    <select id="pointsReasonSelect" class="form-select" onchange="toggleReasonOtherInput()">
                        <!-- Dynamic choices -->
                    </select>
                </div>
                <div class="form-group" id="reasonOtherGroup" style="display:none;">
                    <label class="form-label">اكتب السبب المخصص</label>
                    <input type="text" id="reasonOtherInput" class="form-input" placeholder="اكتب السبب هنا...">
                </div>

                <button class="btn-primary" onclick="submitPointsUpdate()">
                    <i class="fas fa-save"></i> تطبيق تحديث النقاط
                </button>
            </div>
        </div>
    </div>

    <!-- ───────────────────────────────────────────────────────────── -->
    <!-- QR SCANNER MODAL -->
    <!-- ───────────────────────────────────────────────────────────── -->
    <div class="modal-overlay" id="qrScannerModal">
        <div class="modal-card">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fas fa-qrcode" style="color:var(--success);"></i>
                    <span id="scannerModalTitle">مسح كود الحضور</span>
                </div>
                <button class="modal-close" onclick="closeScannerModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="qrVideoContainer">
                    <video id="qrVideo" playsinline></video>
                </div>

                <div class="scan-feedback-banner" id="scanFeedbackBanner">
                    <div id="scanFeedbackAvatar" class="user-card-avatar"></div>
                    <div>
                        <div id="scanFeedbackName" style="font-weight:800;color:#fff;"></div>
                        <div id="scanFeedbackMsg" style="font-size:0.85rem;color:#34d399;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ───────────────────────────────────────────────────────────── -->
    <!-- POINTS SETTINGS MODAL -->
    <!-- ───────────────────────────────────────────────────────────── -->
    <div class="modal-overlay" id="settingsModal">
        <div class="modal-card">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fas fa-sliders-h" style="color:var(--primary);"></i>
                    <span>إعدادات وتنسيق نظام النقاط</span>
                </div>
                <button class="modal-close" onclick="closeModal('settingsModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form onsubmit="saveSettingsForm(event)">
                    <div class="form-group">
                        <label class="form-label">تعديل قيم الاختصارات السريعة (تفصل بينها بفواصل)</label>
                        <input type="text" id="settingShortcutsInput" class="form-input" placeholder="10, 30, 50, 100">
                    </div>
                    <div class="form-group">
                        <label style="display:flex; align-items:center; gap:10px; color:#fff; cursor:pointer;">
                            <input type="checkbox" id="settingEnableShortcut" style="width:18px;height:18px;">
                            <span>تفعيل زر الاختصارات السريعة</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label style="display:flex; align-items:center; gap:10px; color:#fff; cursor:pointer;">
                            <input type="checkbox" id="settingEnableCustom" style="width:18px;height:18px;">
                            <span>تفعيل إدخال قيمة النقاط المخصصة</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="form-label">خيارات أسباب إعطاء النقاط (كل سبب في سطر منفصل)</label>
                        <textarea id="settingReasonsInput" class="form-textarea" style="height:120px;" placeholder="ألعاب
بونص
التزام بالأوقات"></textarea>
                    </div>
                    <button type="submit" class="btn-primary" style="width:100%;">حفظ الإعدادات</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Mandatory absolute API path rule
        const API_URL = '/brethren/api.php';

        let usersList = [];
        let eventsList = [];
        let systemSettings = {
            shortcuts: [10, 30, 50, 100],
            enable_shortcut: true,
            enable_custom: true,
            reasons: ['ألعاب', 'بونص', 'التزام بالأوقات']
        };

        let currentPointsMode = 'add'; // 'add' or 'deduct'
        let activeEventForScan = null;
        let qrScannerInstance = null;

        // Sound Synthesis (Exact synth audio matching project standards)
        function playSuccessSound() {
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const playNote = (freq, startTime, duration) => {
                    const osc = audioCtx.createOscillator();
                    const gain = audioCtx.createGain();
                    osc.connect(gain);
                    gain.connect(audioCtx.destination);
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(freq, startTime);
                    gain.gain.setValueAtTime(0.08, startTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);
                    osc.start(startTime);
                    osc.stop(startTime + duration);
                };
                const now = audioCtx.currentTime;
                playNote(523.25, now, 0.12);
                playNote(659.25, now + 0.06, 0.12);
                playNote(783.99, now + 0.12, 0.24);
            } catch (e) { }
        }

        function playErrorSound() {
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = audioCtx.createOscillator();
                const gain = audioCtx.createGain();
                osc.connect(gain);
                gain.connect(audioCtx.destination);
                osc.type = 'sawtooth';
                osc.frequency.setValueAtTime(150, audioCtx.currentTime);
                gain.gain.setValueAtTime(0.08, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.3);
                osc.start();
                osc.stop(audioCtx.currentTime + 0.3);
            } catch (e) { }
        }

        document.addEventListener('DOMContentLoaded', () => {
            fetchUsers();
            fetchEvents();
            fetchSettings();

            // Set default date for create event form
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('eventDateInput').value = today;
        });

        function switchTab(tabId) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

            event.currentTarget.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        }

        // ─────────────────────────────────────────────────────────────
        // USERS & DATA FETCHING
        // ─────────────────────────────────────────────────────────────
        async function fetchUsers() {
            try {
                const res = await fetch(`${API_URL}?action=get_users`);
                const data = await res.json();
                if (data.status === 'success') {
                    usersList = data.users || [];
                    renderUsersList();
                    populateUserSelects();
                }
            } catch (err) {
                console.error(err);
            }
        }

        function renderUsersList() {
            const query = document.getElementById('adminUserSearch').value.trim();
            const grid = document.getElementById('usersListGrid');

            let filtered = usersList;
            if (query && typeof getMatchScore === 'function') {
                filtered = usersList.map(u => ({
                    ...u,
                    _score: getMatchScore(u, query, [
                        { val: u.name, weight: 1.0 },
                        { val: u.phone, weight: 1.1 },
                        { val: u.location, weight: 0.8 }
                    ])
                })).filter(u => u._score > 0)
                   .sort((a, b) => b._score - a._score);
            }

            if (filtered.length === 0) {
                grid.innerHTML = `<div class="empty-state">لا يوجد مستخدمين متاحين</div>`;
                return;
            }

            grid.innerHTML = filtered.map(u => `
                <div class="user-card">
                    <div class="user-card-header">
                        <div class="user-card-avatar">
                            ${u.photo ? `<img src="${u.photo}" style="width:100%;height:100%;border-radius:50%;object-fit:cover">` : u.name.charAt(0)}
                        </div>
                        <div>
                            <div class="user-card-name">${u.name}</div>
                            <div class="user-card-sub">${u.phone || 'بدون هاتف'} • ${u.location || 'بدون عنوان'}</div>
                            <span class="user-card-points"><i class="fas fa-star"></i> ${u.points} نقطة</span>
                        </div>
                    </div>
                    <div class="user-card-actions">
                        <button class="action-btn btn-edit" onclick="editUserModal(${u.id})"><i class="fas fa-edit"></i> تعديل</button>
                        <button class="action-btn btn-add-pt" onclick="quickAddPointsUser(${u.id})"><i class="fas fa-plus"></i> نقاط</button>
                        <button class="action-btn btn-delete" onclick="deleteUserConfirm(${u.id})"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
            `).join('');
        }

        function populateUserSelects() {
            const sel = document.getElementById('pointsTargetUserSelect');
            sel.innerHTML = `<option value="">-- اختر مستخدم --</option>` + 
                usersList.map(u => `<option value="${u.id}">${u.name} (${u.points} نقطة)</option>`).join('');
        }

        async function submitAddUser(e) {
            e.preventDefault();
            const id = document.getElementById('userIdInput').value;
            const payload = {
                action: 'save_user',
                id: id,
                name: document.getElementById('userNameInput').value.trim(),
                phone: document.getElementById('userPhoneInput').value.trim(),
                location: document.getElementById('userLocationInput').value.trim(),
                gender: document.getElementById('userGenderInput').value,
                birth_date: document.getElementById('userBirthDateInput').value.trim()
            };

            try {
                const res = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.status === 'success') {
                    document.getElementById('addUserForm').reset();
                    document.getElementById('userIdInput').value = 0;
                    document.getElementById('saveUserBtn').innerHTML = `<i class="fas fa-plus"></i> حفظ المستخدم`;
                    fetchUsers();
                    alert('تم الحفظ بنجاح');
                } else {
                    alert(data.message);
                }
            } catch (err) {
                alert('تعذر الاتصال بالخادم');
            }
        }

        function editUserModal(id) {
            const u = usersList.find(item => item.id == id);
            if (!u) return;

            document.getElementById('userIdInput').value = u.id;
            document.getElementById('userNameInput').value = u.name;
            document.getElementById('userPhoneInput').value = u.phone || '';
            document.getElementById('userLocationInput').value = u.location || '';
            document.getElementById('userGenderInput').value = u.gender || 'شاب';
            document.getElementById('userBirthDateInput').value = u.birth_date || '';
            document.getElementById('saveUserBtn').innerHTML = `<i class="fas fa-save"></i> تحديث البيانات`;

            window.scrollTo({ top: 0, behavior: 'smooth' });
        }

        async function deleteUserConfirm(id) {
            if (!confirm('هل أنت تأكد من رغبتك في حذف هذا المستخدم نهائياً؟')) return;
            try {
                const res = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete_user', id: id })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    fetchUsers();
                } else {
                    alert(data.message);
                }
            } catch (err) {
                alert('تعذر الحذف');
            }
        }

        // ─────────────────────────────────────────────────────────────
        // BULK IMPORT PROCESSOR (GOOGLE SHEETS)
        // ─────────────────────────────────────────────────────────────
        async function processBulkImport() {
            const rawText = document.getElementById('bulkPasteInput').value.trim();
            if (!rawText) {
                alert('يرجى لصق بيانات الجدول أولاً');
                return;
            }

            const lines = rawText.split(/\r?\n/).map(l => l.trim()).filter(l => l);
            if (lines.length < 2) {
                alert('يجب أن يحتوي النص الملصوق على صف عناوين وصفوف بيانات');
                return;
            }

            // Detect delimiter (Tab \t or Comma ,)
            const firstLine = lines[0];
            const delimiter = firstLine.includes('\t') ? '\t' : ',';

            const headers = firstLine.split(delimiter).map(h => h.trim().toLowerCase());
            
            const standardMap = {
                name: ['اسم', 'الاسم', 'name'],
                phone: ['تليفون', 'موبايل', 'رقم', 'هاتف', 'phone', 'number'],
                location: ['عنوان', 'منطقة', 'سكن', 'location', 'address'],
                gender: ['نوع', 'جنس', 'gender'],
                birth_date: ['تاريخ ميلاد', 'تاريخ الميلاد', 'ميلاد', 'birth_date', 'birthdate', 'dob']
            };

            const headerKeys = headers.map(h => {
                for (const [stdKey, aliases] of Object.entries(standardMap)) {
                    if (aliases.some(a => h.includes(a))) return stdKey;
                }
                return h; // Return full title as custom info title!
            });

            const parsedUsers = [];
            for (let i = 1; i < lines.length; i++) {
                const cells = lines[i].split(delimiter).map(c => c.trim());
                if (cells.length === 0 || !cells[0]) continue;

                const userObj = { custom_fields: {} };
                headers.forEach((h, colIdx) => {
                    const key = headerKeys[colIdx];
                    const val = cells[colIdx] || '';
                    if (['name', 'phone', 'location', 'gender', 'birth_date'].includes(key)) {
                        userObj[key] = val;
                    } else if (val) {
                        // Extra / unknown column is stored as Custom Info Field!
                        userObj.custom_fields[headers[colIdx]] = val;
                    }
                });

                if (userObj.name) {
                    parsedUsers.push(userObj);
                }
            }

            if (parsedUsers.length === 0) {
                alert('لم يتم العثور على أسماء صالحة في البيانات الملصوقة');
                return;
            }

            try {
                const res = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'bulk_add_users', users: parsedUsers })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    alert(data.message);
                    document.getElementById('bulkPasteInput').value = '';
                    fetchUsers();
                    switchTab('tabUsers');
                } else {
                    alert(data.message);
                }
            } catch (err) {
                alert('تعذر استيراد البيانات');
            }
        }

        // ─────────────────────────────────────────────────────────────
        // EVENTS & QR SCANNER
        // ─────────────────────────────────────────────────────────────
        async function fetchEvents() {
            try {
                const res = await fetch(`${API_URL}?action=get_events`);
                const data = await res.json();
                if (data.status === 'success') {
                    eventsList = data.events || [];
                    renderAdminEvents();
                }
            } catch (err) {
                console.error(err);
            }
        }

        function renderAdminEvents() {
            const grid = document.getElementById('adminEventsGrid');
            if (eventsList.length === 0) {
                grid.innerHTML = `<div class="empty-state">لا توجد فعاليات مضافة بعد</div>`;
                return;
            }

            grid.innerHTML = eventsList.map(ev => `
                <div class="event-admin-card">
                    <div>
                        <div class="event-admin-header">
                            <div class="event-admin-title">${ev.event_name}</div>
                            <span class="event-attendance-badge"><i class="fas fa-users"></i> ${ev.attendance_count} حاضر</span>
                        </div>
                        <div class="event-date-text"><i class="far fa-calendar"></i> ${ev.event_date}</div>
                        ${ev.description ? `<p style="font-size:0.88rem;color:#cbd5e1;margin-bottom:10px;">${ev.description}</p>` : ''}
                    </div>
                    <button class="btn-scan" onclick="openScannerModal(${ev.id}, '${ev.event_name.replace(/'/g, "\\'")}')">
                        <i class="fas fa-camera"></i> فتح ماسح الحضور QR (+20 نقطة)
                    </button>
                </div>
            `).join('');
        }

        async function submitCreateEvent(e) {
            e.preventDefault();
            const payload = {
                action: 'create_event',
                event_name: document.getElementById('eventNameInput').value.trim(),
                event_date: document.getElementById('eventDateInput').value,
                description: document.getElementById('eventDescInput').value.trim()
            };

            try {
                const res = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.status === 'success') {
                    document.getElementById('createEventForm').reset();
                    document.getElementById('eventDateInput').value = new Date().toISOString().split('T')[0];
                    fetchEvents();
                    alert('تم إنشاء الفعالية بنجاح');
                } else {
                    alert(data.message);
                }
            } catch (err) {
                alert('تعذر إنشاء الفعالية');
            }
        }

        function openScannerModal(eventId, eventName) {
            activeEventForScan = { id: eventId, name: eventName };
            document.getElementById('scannerModalTitle').innerText = `ماسح QR: ${eventName}`;
            document.getElementById('scanFeedbackBanner').style.display = 'none';
            document.getElementById('qrScannerModal').style.display = 'flex';

            const videoElem = document.getElementById('qrVideo');

            if (typeof QrScanner !== 'undefined') {
                qrScannerInstance = new QrScanner(
                    videoElem,
                    result => onQrCodeScanned(result.data || result),
                    {
                        onDecodeError: error => {},
                        highlightScanRegion: true,
                        highlightCodeOutline: true
                    }
                );
                qrScannerInstance.start().catch(err => {
                    console.error('Camera access error:', err);
                    alert('تعذر فتح الكاميرا، يرجى التأكد من السماح بالوصول للكاميرا');
                });
            }
        }

        let isScanningLock = false;
        async function onQrCodeScanned(userCode) {
            if (isScanningLock || !activeEventForScan) return;
            isScanningLock = true;

            try {
                const res = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'scan_attendance',
                        event_id: activeEventForScan.id,
                        user_code: userCode
                    })
                });
                const data = await res.json();

                const banner = document.getElementById('scanFeedbackBanner');
                const avatar = document.getElementById('scanFeedbackAvatar');
                const nameElem = document.getElementById('scanFeedbackName');
                const msgElem = document.getElementById('scanFeedbackMsg');

                if (data.status === 'success') {
                    playSuccessSound();
                    avatar.innerText = data.user.name.charAt(0);
                    nameElem.innerText = data.user.name;
                    msgElem.innerText = `✓ تم تسجيل الحضور بنجاح! النقاط الحالية: ${data.user.points}`;
                    banner.style.background = 'rgba(16, 185, 129, 0.2)';
                    banner.style.borderColor = 'rgba(16, 185, 129, 0.4)';
                    banner.style.display = 'flex';
                    fetchEvents();
                    fetchUsers();
                } else if (data.status === 'already_attended') {
                    playErrorSound();
                    avatar.innerText = data.user ? data.user.name.charAt(0) : '?';
                    nameElem.innerText = data.user ? data.user.name : 'مستخدم مسجل';
                    msgElem.innerText = `⚠ تم تسجيل الحضور سابقاً لهذه الفعالية!`;
                    banner.style.background = 'rgba(245, 158, 11, 0.2)';
                    banner.style.borderColor = 'rgba(245, 158, 11, 0.4)';
                    banner.style.display = 'flex';
                } else {
                    playErrorSound();
                    avatar.innerText = '!';
                    nameElem.innerText = 'خطأ في المسح';
                    msgElem.innerText = data.message || 'رمز QR غير صالح';
                    banner.style.background = 'rgba(239, 68, 68, 0.2)';
                    banner.style.borderColor = 'rgba(239, 68, 68, 0.4)';
                    banner.style.display = 'flex';
                }
            } catch (err) {
                playErrorSound();
            } finally {
                setTimeout(() => { isScanningLock = false; }, 2000);
            }
        }

        function closeScannerModal() {
            if (qrScannerInstance) {
                qrScannerInstance.stop();
                qrScannerInstance.destroy();
                qrScannerInstance = null;
            }
            document.getElementById('qrScannerModal').style.display = 'none';
        }

        // ─────────────────────────────────────────────────────────────
        // POINTS MANAGEMENT & SETTINGS
        // ─────────────────────────────────────────────────────────────
        async function fetchSettings() {
            try {
                const res = await fetch(`${API_URL}?action=get_settings`);
                const data = await res.json();
                if (data.status === 'success') {
                    systemSettings = data.settings || systemSettings;
                    renderPointsUI();
                }
            } catch (err) {
                renderPointsUI();
            }
        }

        function renderPointsUI() {
            // Render Shortcuts
            const scContainer = document.getElementById('shortcutsContainer');
            const scGrid = document.getElementById('shortcutsGrid');
            if (systemSettings.enable_shortcut && Array.isArray(systemSettings.shortcuts)) {
                scContainer.style.display = 'block';
                scGrid.innerHTML = systemSettings.shortcuts.map(pts => `
                    <div class="shortcut-chip" onclick="applyPointsValue(${pts})">
                        ${currentPointsMode === 'add' ? '+' : '-'}${pts}
                    </div>
                `).join('');
            } else {
                scContainer.style.display = 'none';
            }

            // Custom Points input visibility
            document.getElementById('customPointsContainer').style.display = 
                systemSettings.enable_custom ? 'block' : 'none';

            // Render Reasons options
            const rSelect = document.getElementById('pointsReasonSelect');
            const reasonsArr = Array.isArray(systemSettings.reasons) ? systemSettings.reasons : ['ألعاب', 'بونص', 'التزام بالأوقات'];
            rSelect.innerHTML = reasonsArr.map(r => `<option value="${r}">${r}</option>`).join('') +
                `<option value="أخرى">أخرى: ...</option>`;
            toggleReasonOtherInput();
        }

        function setPointsMode(mode) {
            currentPointsMode = mode;
            document.getElementById('modeAddBtn').className = `mode-btn ${mode === 'add' ? 'active add' : ''}`;
            document.getElementById('modeDeductBtn').className = `mode-btn ${mode === 'deduct' ? 'active deduct' : ''}`;
            renderPointsUI();
        }

        function applyPointsValue(val) {
            document.getElementById('customPointsInput').value = val;
        }

        function toggleReasonOtherInput() {
            const val = document.getElementById('pointsReasonSelect').value;
            document.getElementById('reasonOtherGroup').style.display = (val === 'أخرى') ? 'block' : 'none';
        }

        function quickAddPointsUser(userId) {
            switchTab('tabPoints');
            document.getElementById('pointsTargetUserSelect').value = userId;
        }

        async function submitPointsUpdate() {
            const userId = document.getElementById('pointsTargetUserSelect').value;
            let amount = parseInt(document.getElementById('customPointsInput').value, 10);
            
            if (!userId) {
                alert('يرجى اختيار المستخدم المستهدف أولاً');
                return;
            }
            if (isNaN(amount) || amount <= 0) {
                alert('يرجى إدخال عدد نقاط صحيح أكبر من الصفر');
                return;
            }

            if (currentPointsMode === 'deduct') {
                amount = -amount;
            }

            let reason = document.getElementById('pointsReasonSelect').value;
            if (reason === 'أخرى') {
                reason = document.getElementById('reasonOtherInput').value.trim() || 'أخرى';
            }

            try {
                const res = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_points',
                        user_id: userId,
                        points_change: amount,
                        reason: reason,
                        type: 'manual'
                    })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    alert(data.message);
                    document.getElementById('customPointsInput').value = '';
                    fetchUsers();
                } else {
                    alert(data.message);
                }
            } catch (err) {
                alert('تعذر تحديث النقاط');
            }
        }

        // Settings Modal Handlers
        function openSettingsModal() {
            document.getElementById('settingShortcutsInput').value = (systemSettings.shortcuts || [10, 30, 50, 100]).join(', ');
            document.getElementById('settingEnableShortcut').checked = systemSettings.enable_shortcut !== false;
            document.getElementById('settingEnableCustom').checked = systemSettings.enable_custom !== false;
            document.getElementById('settingReasonsInput').value = (systemSettings.reasons || []).join('\n');
            document.getElementById('settingsModal').style.display = 'flex';
        }

        async function saveSettingsForm(e) {
            e.preventDefault();
            const shortcutsRaw = document.getElementById('settingShortcutsInput').value;
            const shortcuts = shortcutsRaw.split(',').map(s => parseInt(s.trim(), 10)).filter(n => !isNaN(n));
            const enableShortcut = document.getElementById('settingEnableShortcut').checked;
            const enableCustom = document.getElementById('settingEnableCustom').checked;
            const reasonsRaw = document.getElementById('settingReasonsInput').value;
            const reasons = reasonsRaw.split(/\r?\n/).map(r => r.trim()).filter(r => r);

            const payload = {
                action: 'save_settings',
                shortcuts: shortcuts.length > 0 ? shortcuts : [10, 30, 50, 100],
                enable_shortcut: enableShortcut,
                enable_custom: enableCustom,
                reasons: reasons.length > 0 ? reasons : ['ألعاب', 'بونص', 'التزام بالأوقات']
            };

            try {
                const res = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.status === 'success') {
                    closeModal('settingsModal');
                    fetchSettings();
                    alert('تم حفظ الإعدادات بنجاح');
                } else {
                    alert(data.message);
                }
            } catch (err) {
                alert('تعذر حفظ الإعدادات');
            }
        }

        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }
    </script>
</body>
</html>
