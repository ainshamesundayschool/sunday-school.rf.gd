<?php
session_start();
$isHttps = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443));
if (!isset($_SESSION['uncle_id']) && !isset($_SESSION['church_id'])) {
    header("Location: /login/");
    exit();
}
$churchName = $_SESSION['church_name'] ?? 'الكنيسة';
$uncleName = $_SESSION['uncle_name'] ?? '';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>سحب الكوبونات | مدارس الأحد</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ── DASHBOARD MATCHING CSS ── */
        :root {
            --brand: #5b6cf5;
            --brand-bg: #eef0ff;
            --brand-glow: rgba(91, 108, 245, .18);
            --success: #10b981;
            --success-bg: #d1fae5;
            --danger: #ef4444;
            --danger-bg: #fee2e2;
            --bg: #f3f4f9;
            --surface: #ffffff;
            --surface-2: #f7f8fc;
            --surface-3: #eef0f8;
            --border-solid: #e4e6f0;
            --text: #1a1d2e;
            --text-2: #4b5068;
            --text-3: #8b90a8;
            --shadow-sm: 0 2px 8px -2px rgba(0, 0, 0, .07);
            --shadow-md: 0 8px 24px -4px rgba(0, 0, 0, .10);
            --shadow-lg: 0 20px 48px -8px rgba(0, 0, 0, .14);
            --r-md: 14px;
            --r-lg: 18px;
            --r-xl: 24px;
            --t: .22s;
            --ease: cubic-bezier(.4, 0, .2, 1);
        }

        /* ── AUDIT TAGS ── */
        .audit-tag {
            display: inline-flex;
            align-items: center;
            padding: 2px 10px;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 900;
            margin: 0 2px;
            border: 1px solid transparent;
            line-height: 1.4;
            vertical-align: middle;
        }

        .tag-success {
            background: var(--success-bg);
            color: var(--success);
            border-color: rgba(16, 185, 129, 0.15);
        }

        .tag-danger {
            background: var(--danger-bg);
            color: var(--danger);
            border-color: rgba(239, 68, 68, 0.15);
        }

        .tag-brand {
            background: var(--brand-bg);
            color: var(--brand);
            border-color: rgba(91, 108, 245, 0.15);
        }

        [data-theme="dark"] {
            --bg: #0f1117;
            --surface: #181b26;
            --surface-2: #1e2132;
            --surface-3: #252840;
            --border-solid: #2a2d42;
            --text: #e8eaf6;
            --text-2: #9299be;
            --text-3: #565c7a;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            -webkit-tap-highlight-color: transparent;
            font-family: 'Cairo';
        }

        img {
            max-height: 100%;
        }

        /* ── Custom Scrollbar ── */
        *::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        *::-webkit-scrollbar-thumb {
            background: rgba(91, 108, 245, 0.2);
            border-radius: 10px;
            transition: background 0.3s;
        }

        *::-webkit-scrollbar-thumb:hover {
            background: var(--brand);
        }

        *::-webkit-scrollbar-track {
            background: rgba(0, 0, 0, 0.03);
            border-radius: 10px;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            overflow-x: hidden;
            line-height: 1.6;
        }

        /* ── Ambient Mesh ── */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background:
                radial-gradient(ellipse 80% 50% at 10% -10%, rgba(91, 108, 245, .07) 0%, transparent 60%),
                radial-gradient(ellipse 60% 40% at 90% 110%, rgba(139, 92, 246, .05) 0%, transparent 60%);
            pointer-events: none;
            z-index: 0;
        }

        /* ── TOPBAR ── */
        .topbar {
            position: sticky;
            top: 0;
            z-index: 300;
            background: var(--bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            padding: 0 16px;
            height: 56px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            border-bottom: 1px solid var(--border-solid);
        }

        .topbar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
            color: inherit;
        }

        .topbar-logo {
            width: 34px;
            height: 34px;
            border-radius: 10px;
            background: var(--brand-bg);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .topbar-logo img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .topbar-title {
            font-size: 0.9rem;
            font-weight: 800;
            line-height: 1.2;
        }

        .topbar-btn {
            width: 36px;
            height: 36px;
            border-radius: 12px;
            border: 1.5px solid var(--border-solid);
            background: var(--surface);
            color: var(--text-2);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all var(--t);
        }

        .topbar-btn:hover {
            border-color: var(--brand);
            color: var(--brand);
        }

        .main-content {
            position: relative;
            z-index: 1;
            max-width: 800px;
            margin: 0 auto;
            padding-bottom: 40px;
        }

        /* ── SEARCH ── */
        .search-section {
            padding: 24px 16px 16px;
            text-align: center;
        }

        .search-title {
            font-size: 1.4rem;
            font-weight: 900;
            margin-bottom: 16px;
            color: var(--text);
        }

        .search-wrap {
            position: relative;
            max-width: 500px;
            margin: 0 auto;
        }

        .search-input {
            width: 100%;
            padding: 12px 48px 12px 20px;
            border-radius: 14px;
            border: 2px solid var(--border-solid);
            background: var(--surface);
            font-size: 1rem;
            font-family: 'Cairo', sans-serif;
            color: var(--text);
            outline: none;
            transition: all var(--t);
            text-align: center;
            box-shadow: var(--shadow-sm);
        }

        .search-input:focus {
            border-color: var(--brand);
        }

        .search-icon {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-3);
            font-size: 1.1rem;
        }

        #sortSelect {
            font-family: 'Cairo', sans-serif;
            font-weight: 800;
        }

        /* ── CLASSES GRID ── */
        .classes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
            gap: 12px;
            padding: 12px;
        }

        .class-card {
            background: var(--surface);
            border-radius: 16px;
            padding: 20px 12px;
            border: 1.5px solid var(--border-solid);
            text-align: center;
            cursor: pointer;
            transition: all var(--t);
            position: relative;
            overflow: hidden;
        }

        .class-card:hover {
            border-color: var(--brand);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .class-icon {
            width: 44px;
            height: 44px;
            background: var(--brand-bg);
            color: var(--brand);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 1.2rem;
        }

        .class-name {
            font-weight: 800;
            font-size: 0.95rem;
            margin-bottom: 4px;
            color: var(--text);
        }

        .class-badge {
            font-size: .7rem;
            font-weight: 800;
            color: var(--text-3);
            opacity: 0.7;
        }

        /* ── FILTER PILLS ── */
        .filter-pills {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding: 0 16px 16px;
            scrollbar-width: none;
            justify-content: flex-start;
            -webkit-overflow-scrolling: touch;
        }

        .filter-pills::-webkit-scrollbar {
            display: none;
        }

        .pill {
            padding: 8px 18px;
            border-radius: 12px;
            background: var(--surface);
            border: 1.5px solid var(--border-solid);
            font-size: .85rem;
            font-weight: 800;
            color: var(--text-2);
            cursor: pointer;
            white-space: nowrap;
            transition: all var(--t);
            flex-shrink: 0;
        }

        .pill.active {
            background: var(--brand);
            color: #fff;
            border-color: var(--brand);
        }

        /* ── STUDENT LIST ── */
        .kid-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding: 12px;
        }

        .kid-item {
            background: var(--surface);
            border-radius: 14px;
            padding: 12px;
            border: 1.5px solid var(--border-solid);
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            transition: all var(--t);
        }

        .kid-item:hover {
            border-color: var(--brand);
            transform: translateX(-2px);
        }

        .kid-photo {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            object-fit: cover;
            background: var(--surface-2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-3);
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .kid-info {
            flex: 1;
            min-width: 0;
        }

        .kid-name {
            font-weight: 800;
            font-size: 0.95rem;
            margin-bottom: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .kid-meta {
            font-size: .75rem;
            color: var(--text-3);
            font-weight: 700;
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .kid-coupons {
            background: var(--brand-bg);
            color: var(--brand);
            padding: 6px 12px;
            border-radius: 10px;
            font-weight: 900;
            font-size: 0.95rem;
        }

        /* ── PROFILE MODAL ── */
        .profile-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.4);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 1000;
            display: flex;
            align-items: flex-end;
            opacity: 0;
            visibility: hidden;
            transition: all 0.4s var(--ease);
        }

        .profile-overlay.active {
            opacity: 1;
            visibility: visible;
        }

        .profile-sheet {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
            background: var(--surface);
            border-radius: 32px 32px 0 0;
            transform: translateY(100%);
            transition: transform 0.5s cubic-bezier(0.16, 1, 0.3, 1);
            max-height: 92vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
        }

        .profile-overlay.active .profile-sheet {
            transform: translateY(0);
        }

        .sheet-header { padding: 20px 24px 12px; text-align: center; position: relative; border-bottom: 1px solid var(--border-solid); }
        .sheet-close { position: absolute; top: 12px; left: 16px; width: 36px; height: 36px; border-radius: 12px; background: var(--surface-2); border: 1.5px solid var(--border-solid); display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text-2); transition: all var(--t); }
        .sheet-photo { width: 80px; height: 80px; border-radius: 22px; border: 3px solid var(--surface); box-shadow: var(--shadow-md); margin: 0 auto 10px; object-fit: cover; display: block; }

        .sheet-name {
            font-size: 1.4rem;
            font-weight: 900;
            margin-bottom: 4px;
        }

        .sheet-class {
            color: var(--brand);
            font-weight: 800;
            font-size: 0.9rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            padding: 12px 20px;
        }

        .stat-box {
            background: var(--surface-2);
            border-radius: 14px;
            padding: 12px;
            border: 1.5px solid var(--border-solid);
            text-align: center;
        }

        .stat-lbl {
            font-size: .8rem;
            color: var(--text-3);
            font-weight: 800;
            margin-bottom: 8px;
        }

        .stat-num {
            font-size: 1.4rem;
            font-weight: 900;
            color: var(--text);
        }

        .stat-num.total {
            color: var(--brand);
        }

        .breakdown-card {
            background: var(--surface-2);
            border-radius: 16px;
            margin: 0 20px 20px;
            padding: 20px;
            border: 1.5px solid var(--border-solid);
        }

        .br-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1.5px dashed var(--border-solid);
        }

        .br-row:last-child {
            border: none;
        }

        .br-lbl {
            font-weight: 800;
            font-size: .9rem;
            color: var(--text-2);
        }

        .br-val {
            font-weight: 900;
            font-size: 1rem;
            color: var(--text);
        }

        .action-box {
            padding: 0 20px 32px;
        }

        .input-group {
            display: flex;
            gap: 10px;
        }

        .amount-input {
            flex: 1;
            padding: 14px;
            border-radius: 14px;
            border: 2px solid var(--border-solid);
            background: var(--surface-2);
            font-size: 1.2rem;
            font-weight: 900;
            text-align: center;
            outline: none;
            transition: all var(--t);
        }

        .amount-input:focus {
            border-color: var(--brand);
            background: var(--surface);
        }

        .withdraw-btn {
            padding: 0 28px;
            border-radius: 14px;
            border: none;
            background: var(--brand);
            color: #fff;
            font-weight: 900;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all var(--t);
        }

        .withdraw-btn:active {
            transform: scale(0.96);
        }

        .withdraw-btn:disabled {
            opacity: .4;
            cursor: not-allowed;
        }

        .hist-section {
            padding: 0 20px;
        }

        .hist-title {
            font-weight: 900;
            font-size: 1.1rem;
            margin-bottom: 16px;
            padding-right: 4px;
        }

        .hist-item {
            background: var(--surface-2);
            border-radius: 16px;
            padding: 16px;
            border: 1.5px solid var(--border-solid);
            margin-bottom: 12px;
        }

        .hist-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }

        .hist-amt {
            font-weight: 900;
            color: var(--danger);
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .hist-amt.refunded {
            text-decoration: line-through;
            opacity: .4;
            color: var(--text-3);
        }

        .hist-date {
            font-size: .75rem;
            color: var(--text-3);
            font-weight: 800;
        }

        .hist-uncle {
            font-size: .8rem;
            font-weight: 700;
        }

        .refund-btn {
            background: var(--warning-bg);
            color: var(--warning-dark);
            border: 1.5px solid var(--warning);
            padding: 6px 14px;
            border-radius: 10px;
            font-size: .8rem;
            font-weight: 900;
            cursor: pointer;
            transition: all var(--t);
        }

        .sheet-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
            scrollbar-width: thin;
            -webkit-overflow-scrolling: touch;
            display: block;
        }

        .toast {
            position: fixed;
            top: 80px;
            left: 50%;
            transform: translateX(-50%) translateY(-20px);
            background: #1a1d2e;
            color: #fff;
            padding: 12px 28px;
            border-radius: 16px;
            z-index: 2000;
            font-weight: 800;
            opacity: 0;
            transition: all .3s var(--spring);
            pointer-events: none;
            box-shadow: var(--shadow-md);
        }

        .toast.active {
            transform: translateX(-50%) translateY(0);
            opacity: 1;
        }

        .toast.success {
            background: var(--success);
        }

        .toast.error {
            background: var(--danger);
        }

        /* ── SKELETONS ── */
        .skeleton {
            background: var(--surface-3);
            border-radius: 14px;
            position: relative;
            overflow: hidden;
        }

        .skeleton::after {
            content: "";
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.4), transparent);
            animation: shimmer 1.5s infinite;
        }

        @keyframes shimmer {
            0% {
                transform: translateX(100%);
            }

            100% {
                transform: translateX(-100%);
            }
        }
    </style>
</head>

<body>

    <header class="topbar">
        <a href="/uncle/dashboard/" class="topbar-brand">
            <div class="topbar-logo"><img src="/logo.png" alt=""
                    onerror="this.outerHTML='<i class=\'fas fa-cross\'></i>'"></div>
            <div>
                <div class="topbar-title">سحب الكوبونات</div>
                <div style="font-size:.65rem;font-weight:800;color:var(--text-3)">مدارس الأحد</div>
            </div>
        </a>
        <button id="themeToggle" class="topbar-btn"><i class="fas fa-moon"></i></button>
    </header>

    <main class="main-content">
        <div class="search-section">
            <h1 class="search-title">ابحث عن طفل للسحب</h1>
            <div class="search-wrap">
                <i class="fas fa-search search-icon"></i>
                <input type="text" id="searchInput" class="search-input" placeholder="اسم الطفل أو الفصل...">
            </div>
            <div style="margin-top:16px;display:flex;justify-content:center;gap:10px">
                <div style="position:relative">
                    <i class="fas fa-sort-amount-down"
                        style="position:absolute;right:14px;top:50%;transform:translateY(-50%);font-size:.8rem;color:var(--text-3);pointer-events:none"></i>
                    <select id="sortSelect" class="pill"
                        style="padding-right:34px;appearance:none;cursor:pointer;outline:none"
                        onchange="performSearch()">
                        <option value="name_asc">الاسم (أ-ي)</option>
                        <option value="name_desc">الاسم (ي-أ)</option>
                        <option value="coupons_desc">الأعلى كوبونات</option>
                        <option value="coupons_asc">الأقل كوبونات</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="filter-pills" id="classFilters">
            <div class="pill active" data-class="all" onclick="setFilter('all')">الكل</div>
            <!-- Filter pills filled via JS -->
        </div>

        <div id="classList" class="classes-grid">
            <!-- Grid of classes -->
        </div>

        <div id="studentList" class="kid-list" style="display:none">
            <!-- List of kids -->
        </div>
    </main>

    <!-- Profile Sheet -->
    <div id="profileOverlay" class="profile-overlay" onclick="if(event.target===this)closeProfile()">
        <div class="profile-sheet">
            <div class="sheet-header">
                <div class="sheet-close" onclick="closeProfile()"><i class="fas fa-times"></i></div>
                <div id="profileHead"></div>
            </div>
            <div id="profileBody" class="sheet-body"></div>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <script>
        const API_URL = '/api.php';
        let allStudents = [];
        let classes = [];
        let currentClass = 'all';
        let selectedStudent = null;

        // Help UI scale
        if (localStorage.getItem('theme') === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
        document.getElementById('themeToggle').onclick = () => {
            const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
            document.documentElement.setAttribute('data-theme', isDark ? '' : 'dark');
            localStorage.setItem('theme', isDark ? 'light' : 'dark');
            document.getElementById('themeToggle').innerHTML = `<i class="fas fa-${isDark ? 'moon' : 'sun'}"></i>`;
        };

        async function init() {
            showSkeletons();
            const fd = new FormData();
            fd.append('action', 'getData');
            try {
                const resp = await fetch(API_URL, { method: 'POST', body: fd, credentials: 'include' }).then(r => r.json());
                if (resp.success) {
                    allStudents = resp.data || resp.allStudents || [];
                    classes = resp.classes || [];
                    renderFilters();
                    renderClasses();
                } else {
                    showToast(resp.message || 'فشل تحميل البيانات', 'error');
                }
            } catch (e) {
                showToast('خطأ في الاتصال بالسيرفر', 'error');
            }
        }

        function showSkeletons() {
            const grid = document.getElementById('classList');
            grid.innerHTML = Array(6).fill(0).map(() => `
            <div class="skeleton" style="height:120px"></div>
        `).join('');
        }

        function renderFilters() {
            const container = document.getElementById('classFilters');
            let html = `<div class="pill ${currentClass === 'all' ? 'active' : ''}" onclick="setFilter('all')">الكل</div>`;
            classes.forEach(c => {
                const name = c.arabic_name || c.code;
                html += `<div class="pill ${currentClass === name ? 'active' : ''}" onclick="setFilter('${name}')">${name}</div>`;
            });
            container.innerHTML = html;
        }

        function setFilter(cls) {
            currentClass = cls;
            renderFilters();
            const searchVal = document.getElementById('searchInput').value.trim();
            if (searchVal) {
                performSearch();
                return;
            }

            if (cls === 'all') {
                document.getElementById('classList').style.display = 'grid';
                document.getElementById('studentList').style.display = 'none';
                renderClasses();
            } else {
                document.getElementById('classList').style.display = 'none';
                document.getElementById('studentList').style.display = 'flex';
                renderStudents(allStudents.filter(s => s['الفصل'] === cls));
            }
        }

        function getClassIcon(name, cls) {
            if (cls && cls.icon) {
                if (/^\d+$/.test(cls.icon)) return `<span style="font-weight:900;font-size:1.2rem">${cls.icon}</span>`;
                return `<i class="fas ${cls.icon}"></i>`;
            }
            const icons = {
                'حضانة': '<i class="fas fa-baby"></i>',
                'أولى': '<span style="font-weight:900;font-size:1.2rem">١</span>',
                'تانية': '<span style="font-weight:900;font-size:1.2rem">٢</span>',
                'تالتة': '<span style="font-weight:900;font-size:1.2rem">٣</span>',
                'رابعة': '<span style="font-weight:900;font-size:1.2rem">٤</span>',
                'خامسة': '<span style="font-weight:900;font-size:1.2rem">٥</span>',
                'سادسة': '<span style="font-weight:900;font-size:1.2rem">٦</span>',
                'سادسه': '<span style="font-weight:900;font-size:1.2rem">٦</span>',
                'سابعة': '<span style="font-weight:900;font-size:1.2rem">٧</span>',
                'ثامنة': '<span style="font-weight:900;font-size:1.2rem">٨</span>',
            };
            return icons[name] || `<i class="fas fa-chalkboard-teacher"></i>`;
        }

        function renderClasses() {
            const container = document.getElementById('classList');
            container.innerHTML = classes.map(c => {
                const name = c.arabic_name || c.code;
                const count = allStudents.filter(s => s['الفصل'] === name).length;
                return `
                <div class="class-card" onclick="setFilter('${name}')">
                    <div class="class-icon">${getClassIcon(name, c)}</div>
                    <div class="class-name">${name}</div>
                    <div class="class-badge">${count} طفل</div>
                </div>
            `;
            }).join('');
        }

        function renderStudents(list) {
            const container = document.getElementById('studentList');
            if (!list.length) {
                container.innerHTML = '<div style="text-align:center;padding:60px;color:var(--text-3);font-weight:800">لا يوجد أطفال في هذا الفصل</div>';
                return;
            }
            container.innerHTML = list.map(s => {
                const photo = s['صورة'] ? `<img src="${s['صورة']}" class="kid-photo" onerror="this.outerHTML='<div class=\\'kid-photo\\'><i class=\\'fas fa-user\\'></i></div>'">` : '<div class="kid-photo"><i class="fas fa-user"></i></div>';
                const clsData = classes.find(c => (c.arabic_name || c.code) === s['الفصل']);
                return `
                <div class="kid-item" onclick="openProfile(${s['_studentId']})">
                    ${photo}
                    <div class="kid-info">
                        <div class="kid-name">${s['الاسم']}</div>
                        <div class="kid-meta"><span style="display:flex;align-items:center;gap:4px"><span style="font-size:0.7rem;opacity:0.7">${getClassIcon(s['الفصل'], clsData)}</span> ${s['الفصل']}</span></div>
                    </div>
                    <div class="kid-coupons">${s['كوبونات'] || 0} <i class="fas fa-star" style="font-size:.7rem"></i></div>
                </div>
            `;
            }).join('');
        }

        function performSearch() {
            const q = document.getElementById('searchInput').value.trim().toLowerCase();
            const sort = document.getElementById('sortSelect').value;

            if (!q && currentClass === 'all') {
                setFilter('all');
                return;
            }

            document.getElementById('classList').style.display = 'none';
            document.getElementById('studentList').style.display = 'flex';

            let filtered = allStudents;
            if (currentClass !== 'all') filtered = filtered.filter(s => s['الفصل'] === currentClass);
            if (q) {
                filtered = filtered.filter(s =>
                    (s['الاسم'] || '').toLowerCase().includes(q) ||
                    (s['الفصل'] || '').toLowerCase().includes(q)
                );
            }

            // Apply Sorting
            filtered.sort((a, b) => {
                if (sort === 'name_asc') return (a['الاسم'] || '').localeCompare(b['الاسم'] || '', 'ar');
                if (sort === 'name_desc') return (b['الاسم'] || '').localeCompare(a['الاسم'] || '', 'ar');
                if (sort === 'coupons_desc') return (parseInt(b['كوبونات']) || 0) - (parseInt(a['كوبونات']) || 0);
                if (sort === 'coupons_asc') return (parseInt(a['كوبونات']) || 0) - (parseInt(b['كوبونات']) || 0);
                return 0;
            });

            renderStudents(filtered);
        }

        document.getElementById('searchInput').oninput = performSearch;

        async function openProfile(id) {
            const s = allStudents.find(x => x['_studentId'] === id);
            if (!s) return;
            selectedStudent = s;

            const photo = s['صورة'] ? `<img src="${s['صورة']}" class="sheet-photo" onerror="this.outerHTML='<div class=\\'sheet-photo\\' style=\\'display:flex;align-items:center;justify-content:center;background:var(--brand-bg);color:var(--brand);font-size:2rem\\'><i class=\\'fas fa-user\\'></i></div>'">` : '<div class="sheet-photo" style="display:flex;align-items:center;justify-content:center;background:var(--brand-bg);color:var(--brand);font-size:2rem"><i class="fas fa-user"></i></div>';

            document.getElementById('profileHead').innerHTML = `
            ${photo}
            <div class="sheet-name">${s['الاسم']}</div>
            <div class="sheet-class">${s['الفصل']}</div>
            <div style="margin-top:8px">
                <button class="pill" style="background:var(--brand-bg);color:var(--brand);border:none;padding:6px 14px;font-size:.8rem;border-radius:10px" onclick="viewFullAudit(${s['_studentId']})">
                    <i class="fas fa-history"></i> سجل العام
                </button>
            </div>
        `;

            document.getElementById('profileBody').innerHTML = `
            <div class="stats-grid">
                <div class="stat-box">
                    <div class="stat-lbl">الرصيد الكلي</div>
                    <div class="stat-num total" id="curTotal">${s['كوبونات'] || 0}</div>
                </div>
                <div class="stat-box">
                    <div class="stat-lbl">عيد الميلاد</div>
                    <div class="stat-num" style="font-size:1.1rem">${s['عيد الميلاد'] || '---'}</div>
                </div>
            </div>
            <div class="breakdown-card">
                <div class="br-row"><div class="br-lbl">حضور</div><div class="br-val">${s['كوبونات الحضور'] || 0}</div></div>
                <div class="br-row"><div class="br-lbl">التزام</div><div class="br-val">${s['كوبونات الالتزام'] || 0}</div></div>
                <div class="br-row"><div class="br-lbl">مهام</div><div class="br-val">${s['كوبونات المهام'] || 0}</div></div>
            </div>
            <div class="action-box" style="padding-bottom:20px">
                <div style="font-weight:900;margin-bottom:8px;padding-right:8px;font-size:0.9rem">سحب كوبونات</div>
                <div class="input-group">
                    <input type="number" id="wAmount" class="amount-input" placeholder="0" min="1" oninput="checkAmount()" style="padding:10px;font-size:1.1rem">
                    <button class="withdraw-btn" id="wBtn" onclick="submitWithdraw()" style="padding:0 20px;font-size:1rem">سحب</button>
                </div>
                <div id="wError" style="color:var(--danger);font-size:.8rem;font-weight:800;margin-top:8px;padding-right:8px;display:none;animation:shake 0.3s">القيمة أكبر من الرصيد المتاح!</div>
            </div>
            <div class="hist-section">
                <div class="hist-title">سجل العمليات</div>
                <div id="histList">
                    <div style="text-align:center;padding:20px;color:var(--text-3);font-weight:800">جارِ التحميل...</div>
                </div>
            </div>
        `;

            document.getElementById('profileOverlay').classList.add('active');
            document.getElementById('profileOverlay').style.display = 'flex'; // Ensure visible
            loadHistory(id);
        }

        function checkAmount() {
            const input = document.getElementById('wAmount');
            const val = parseInt(input.value) || 0;
            const max = selectedStudent ? (selectedStudent['كوبونات'] || 0) : 0;
            const btn = document.getElementById('wBtn');
            const err = document.getElementById('wError');

            if (val > max) {
                err.style.display = 'block';
                btn.disabled = true;
                input.style.borderColor = 'var(--danger)';
            } else {
                err.style.display = 'none';
                btn.disabled = false;
                input.style.borderColor = val > 0 ? 'var(--brand)' : 'var(--border-solid)';
            }
        }

        function closeProfile() {
            document.getElementById('profileOverlay').classList.remove('active');
            selectedStudent = null;
            init(); // Soft refresh
        }

        async function loadHistory(sid) {
            const fd = new FormData();
            fd.append('action', 'getWithdrawalHistory');
            fd.append('student_id', sid);
            const r = await fetch(API_URL, { method: 'POST', body: fd, credentials: 'include' }).then(r => r.json());
            const list = document.getElementById('histList');
            if (r.success && r.history.length) {
                list.innerHTML = r.history.map(h => `
                <div class="hist-item">
                    <div class="hist-row">
                        <div class="hist-amt ${h.is_refunded ? 'refunded' : ''}">
                            <span class="audit-tag tag-danger">${h.amount} <i class="fas fa-star" style="font-size:.6rem"></i></span>
                        </div>
                        <div class="hist-date">${h.created_at}</div>
                    </div>
                    <div class="hist-row">
                        <div class="hist-uncle">بواسطة: ${h.uncle_name}</div>
                        ${h.is_refunded ? '<span class="audit-tag tag-brand">تم الاسترجاع</span>' : `<button class="refund-btn" onclick="refund(${h.id})">استرجاع</button>`}
                    </div>
                </div>
            `).join('');
            } else {
                list.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-3);font-weight:700">لا يوجد عمليات سحب</div>';
            }
        }

        async function submitWithdraw() {
            const val = parseInt(document.getElementById('wAmount').value);
            if (!val || val <= 0) { showToast('أدخل قيمة صحيحة', 'error'); return; }
            if (val > (selectedStudent['كوبونات'] || 0)) { showToast('الرصيد غير كافٍ', 'error'); return; }

            const btn = document.getElementById('wBtn');
            btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

            const fd = new FormData();
            fd.append('action', 'withdrawCoupons');
            fd.append('student_id', selectedStudent['_studentId']);
            fd.append('amount', val);

            try {
                const r = await fetch(API_URL, { method: 'POST', body: fd, credentials: 'include' }).then(r => r.json());
                if (r.success) {
                    showToast('تم السحب بنجاح', 'success');
                    selectedStudent['كوبونات'] = r.newTotal;
                    document.getElementById('curTotal').textContent = r.newTotal;
                    document.getElementById('wAmount').value = '';
                    loadHistory(selectedStudent['_studentId']);
                } else {
                    showToast(r.message || 'فشل السحب', 'error');
                }
            } catch (e) { showToast('خطأ في الاتصال', 'error'); }
            btn.disabled = false; btn.innerHTML = 'سحب';
        }

        async function refund(wid) {
            if (!confirm('هل تريد استرجاع الكوبونات؟')) return;
            const fd = new FormData();
            fd.append('action', 'refundWithdrawal');
            fd.append('withdrawal_id', wid);
            try {
                const r = await fetch(API_URL, { method: 'POST', body: fd, credentials: 'include' }).then(r => r.json());
                if (r.success) {
                    showToast('تم الاسترجاع', 'success');
                    // Soft refresh all data in background
                    const refreshFd = new FormData(); refreshFd.append('action', 'getData');
                    const refreshR = await fetch(API_URL, { method: 'POST', body: refreshFd, credentials: 'include' }).then(r => r.json());
                    if (refreshR.success) {
                        allStudents = refreshR.data || refreshR.allStudents || [];
                        if (selectedStudent) {
                            const updated = allStudents.find(x => x['_studentId'] === selectedStudent['_studentId']);
                            if (updated) {
                                selectedStudent = updated;
                                document.getElementById('curTotal').textContent = updated['كوبونات'];
                            }
                        }
                    }
                    if (selectedStudent) loadHistory(selectedStudent['_studentId']);
                } else {
                    showToast(r.message || 'فشل الاسترجاع', 'error');
                }
            } catch (e) { showToast('خطأ في الاتصال', 'error'); }
        }

        function showToast(m, t = 'info') {
            const el = document.getElementById('toast');
            el.textContent = m; el.className = 'toast active ' + t;
            setTimeout(() => el.classList.remove('active'), 3000);
        }

        function timeSince(date) {
            const seconds = Math.floor((new Date() - new Date(date)) / 1000);
            let interval = seconds / 31536000;
            if (interval > 1) return 'منذ ' + Math.floor(interval) + ' سنة';
            interval = seconds / 2592000;
            if (interval > 1) return 'منذ ' + Math.floor(interval) + ' شهر';
            interval = seconds / 86400;
            if (interval > 1) return 'منذ ' + Math.floor(interval) + ' يوم';
            interval = seconds / 3600;
            if (interval > 1) return 'منذ ' + Math.floor(interval) + ' ساعة';
            interval = seconds / 60;
            if (interval > 1) return 'منذ ' + Math.floor(interval) + ' دقيقة';
            return 'الآن';
        }

        function formatAuditNote(text) {
            if (!text) return '';
            // Wrap status words in tags
            let html = text
                .replace(/حاضر/g, '<span class="audit-tag tag-success">حاضر</span>')
                .replace(/غائب/g, '<span class="audit-tag tag-danger">غائب</span>')
                .replace(/(\d+)\s*كوبون/g, '<span class="audit-tag tag-brand">$1 كوبون</span>');
            return html;
        }

        async function viewFullAudit(sid) {
            const fd = new FormData();
            fd.append('action', 'getEntityAuditHistory');
            fd.append('entity', 'coupon');
            fd.append('entity_id', sid);

            showToast('جاري تحميل السجل...', 'info');
            try {
                const r = await fetch(API_URL, { method: 'POST', body: fd, credentials: 'include' }).then(r => r.json());
                if (r.success) {
                    let listHtml = '';
                    if (!r.logs.length) {
                        listHtml = `<div style="text-align:center;padding:80px 20px;color:var(--text-3);font-weight:800"><i class="fas fa-history" style="font-size:3rem;display:block;margin-bottom:16px;opacity:0.1"></i>لا يوجد سجلات تاريخية لهذا الطفل حتى الآن</div>`;
                    } else {
                        listHtml = `
                        <div style="position:relative;padding-right:20px;margin-top:10px">
                            <div style="position:absolute;right:4px;top:0;bottom:0;width:2px;background:var(--border-solid);border-radius:1px"></div>
                            ${r.logs.map(l => {
                            let badge = 'تعديل'; let color = '#5b6cf5'; let bg = 'rgba(91,108,245,0.08)';
                            if (l.action.includes('add')) { badge = 'إضافة'; color = '#10b981'; bg = 'rgba(16,185,129,0.08)'; }
                            if (l.action.includes('withdraw')) { badge = 'سحب'; color = '#f59e0b'; bg = 'rgba(245,158,11,0.08)'; }
                            if (l.action.includes('refund')) { badge = 'استرجاع'; color = '#8b5cf6'; bg = 'rgba(139,92,246,0.08)'; }
                            if (l.action.includes('delete')) { badge = 'حذف'; color = '#ef4444'; bg = 'rgba(239,68,68,0.08)'; }

                            return `
                                    <div class="tl-item" style="position:relative;padding-bottom:24px">
                                        <div style="position:absolute;right:-20px;top:6px;width:10px;height:10px;border-radius:50%;background:${color};border:2px solid var(--surface);box-shadow:0 0 0 4px var(--bg);z-index:1"></div>
                                        <div style="background:var(--surface-2);border-radius:18px;padding:16px;box-shadow:var(--shadow-sm);border:1px solid var(--border-solid)">
                                            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                                                <span style="background:${bg};color:${color};padding:4px 10px;border-radius:8px;font-size:0.7rem;font-weight:900;border:1px solid ${color}33">${badge}</span>
                                                <span style="font-size:0.65rem;font-weight:800;color:var(--text-3)">${timeSince(l.created_at)}</span>
                                            </div>
                                            <div style="font-weight:800;font-size:0.95rem;color:var(--text);margin-bottom:12px;line-height:1.8">
                                                ${formatAuditNote(l.notes || l.action)}
                                            </div>
                                            <div style="display:flex;justify-content:space-between;align-items:center;padding-top:10px;border-top:1px dashed var(--border-solid)">
                                                <div style="display:flex;align-items:center;gap:6px">
                                                    <div style="width:20px;height:20px;border-radius:6px;background:var(--brand-bg);color:var(--brand);display:flex;align-items:center;justify-content:center;font-size:0.6rem"><i class="fas fa-user-shield"></i></div>
                                                    <span style="font-size:0.75rem;font-weight:800;color:var(--text-2)">${l.uncle_name}</span>
                                                </div>
                                                <div style="font-size:0.65rem;font-weight:700;color:var(--text-3)">${l.created_at_formatted.split(' ')[0]}</div>
                                            </div>
                                        </div>
                                    </div>
                                `;
                        }).join('')}
                        </div>
                    `;
                    }

                    const auditDiv = document.createElement('div');
                    auditDiv.id = 'auditModal';
                    auditDiv.className = 'profile-overlay active';
                    auditDiv.style.zIndex = '1100';
                    auditDiv.innerHTML = `
                    <div class="profile-sheet">
                        <div class="sheet-header">
                            <div class="sheet-close" onclick="closeAudit()"><i class="fas fa-times"></i></div>
                            <h3 style="font-weight:900;font-size:1.2rem;margin-top:10px">السجل التاريخي الشامل</h3>
                            <p style="font-size:0.75rem;font-weight:700;color:var(--text-3);margin-top:4px">تتبع كافة حركات الكوبونات والبيانات</p>
                        </div>
                        <div class="sheet-body" style="padding:20px 24px">
                            ${listHtml}
                        </div>
                    </div>
                `;
                    document.body.appendChild(auditDiv);
                }
            } catch (e) { showToast('خطأ في تحميل السجل', 'error'); }
        }

        function closeAudit() {
            const m = document.getElementById('auditModal');
            if (m) {
                m.classList.remove('active');
                setTimeout(() => m.remove(), 400);
            }
        }

        init();
    </script>

</body>

</html>