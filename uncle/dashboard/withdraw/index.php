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
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&display=swap">
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
            --warning: #f59e0b;
            --warning-bg: #fef3c7;
            --coupon: #8b5cf6;
            --coupon-bg: #ede9fe;
            --bg: #f3f4f9;
            --surface: #ffffff;
            --surface-2: #f7f8fc;
            --surface-3: #eef0f8;
            --border: rgba(91, 108, 245, .12);
            --border-solid: #e4e6f0;
            --text: #1a1d2e;
            --text-2: #4b5068;
            --text-3: #8b90a8;
            --shadow-sm: 0 2px 8px -2px rgba(0, 0, 0, .07);
            --shadow-md: 0 8px 24px -4px rgba(0, 0, 0, .10);
            --r-md: 14px;
            --r-lg: 18px;
            --r-xl: 24px;
            --r-full: 9999px;
            --t: .22s;
            --ease: cubic-bezier(.4, 0, .2, 1);
            --spring: cubic-bezier(.16, 1, .3, 1);
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

        * { margin: 0; padding: 0; box-sizing: border-box; -webkit-tap-highlight-color: transparent; }
        body { font-family: 'Cairo', sans-serif; background: var(--bg); color: var(--text); line-height: 1.65; transition: background var(--t), color var(--t); overflow-x: hidden; }
        
        /* Ambient background - More minimal */
        body::before {
            content: ''; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: radial-gradient(ellipse 60% 40% at 10% -10%, rgba(91, 108, 245, .04) 0%, transparent 60%);
            pointer-events: none; z-index: 0;
        }

        /* ── TOPBAR ── */
        .topbar {
            position: sticky; top: 0; z-index: 300; background: var(--bg); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px);
            padding: 0 16px; height: 56px; display: flex; align-items: center; justify-content: space-between; gap: 12px;
            border-bottom: 1px solid var(--border-solid);
        }
        .topbar-brand { display: flex; align-items: center; gap: 10px; text-decoration: none; color: inherit; }
        .topbar-logo { width: 34px; height: 34px; border-radius: 10px; background: var(--brand-bg); display: flex; align-items: center; justify-content: center; }
        .topbar-logo img { width: 100%; height: 100%; object-fit: cover; }
        .topbar-title { font-size: 0.9rem; font-weight: 800; line-height: 1.2; }
        .topbar-btn { width: 36px; height: 36px; border-radius: 12px; border: 1.5px solid var(--border-solid); background: var(--surface); color: var(--text-2); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: all var(--t); }
        .topbar-btn:hover { border-color: var(--brand); color: var(--brand); }

        .main-content { position: relative; z-index: 1; max-width: 800px; margin: 0 auto; padding-bottom: 40px; }

        /* ── SEARCH ── */
        .search-section { padding: 24px 16px 16px; text-align: center; }
        .search-title { font-size: 1.4rem; font-weight: 900; margin-bottom: 16px; color: var(--text); }
        .search-wrap { position: relative; max-width: 500px; margin: 0 auto; }
        .search-input { width: 100%; padding: 12px 48px 12px 20px; border-radius: 14px; border: 2px solid var(--border-solid); background: var(--surface); font-size: 1rem; color: var(--text); outline: none; transition: all var(--t); text-align: center; box-shadow: var(--shadow-sm); }
        .search-input:focus { border-color: var(--brand); }
        .search-icon { position: absolute; right: 18px; top: 50%; transform: translateY(-50%); color: var(--text-3); font-size: 1.1rem; }

        /* ── CLASSES GRID ── */
        .classes-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; padding: 12px; }
        .class-card { background: var(--surface); border-radius: 16px; padding: 20px 12px; border: 1.5px solid var(--border-solid); text-align: center; cursor: pointer; transition: all var(--t); }
        .class-card:hover { border-color: var(--brand); transform: translateY(-2px); }
        .class-icon { width: 48px; height: 48px; background: var(--brand-bg); color: var(--brand); border-radius: 14px; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-size: 1.3rem; }
        .class-name { font-weight: 800; font-size: 0.95rem; margin-bottom: 4px; color: var(--text); }
        .class-badge { font-size: .7rem; font-weight: 800; color: var(--text-3); opacity: 0.7; }

        /* ── FILTER PILLS ── */
        .filter-pills { display: flex; gap: 8px; overflow-x: auto; padding: 0 16px 16px; scrollbar-width: none; justify-content: flex-start; -webkit-overflow-scrolling: touch; }
        .filter-pills::-webkit-scrollbar { display: none; }
        .pill { padding: 8px 18px; border-radius: 12px; background: var(--surface); border: 1.5px solid var(--border-solid); font-size: .85rem; font-weight: 800; color: var(--text-2); cursor: pointer; white-space: nowrap; transition: all var(--t); flex-shrink: 0; }
        .pill.active { background: var(--brand); color: #fff; border-color: var(--brand); }

        /* ── STUDENT LIST ── */
        .kid-list { display: flex; flex-direction: column; gap: 8px; padding: 12px; }
        .kid-item { background: var(--surface); border-radius: 14px; padding: 12px; border: 1.5px solid var(--border-solid); display: flex; align-items: center; gap: 12px; cursor: pointer; transition: all var(--t); }
        .kid-item:hover { border-color: var(--brand); transform: translateX(-2px); }
        .kid-photo { width: 52px; height: 52px; border-radius: 12px; object-fit: cover; background: var(--surface-2); display: flex; align-items: center; justify-content: center; color: var(--text-3); font-size: 1.1rem; flex-shrink: 0; }
        .kid-info { flex: 1; }
        .kid-name { font-weight: 800; font-size: 1rem; margin-bottom: 2px; }
        .kid-meta { font-size: .75rem; color: var(--text-3); font-weight: 700; display: flex; gap: 8px; align-items: center; }
        .kid-coupons { background: var(--coupon-bg); color: var(--coupon); padding: 6px 12px; border-radius: 10px; font-weight: 900; font-size: 0.95rem; display: flex; align-items: center; gap: 6px; }

        /* ── PROFILE MODAL ── */
        .profile-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 17, 23, 0.3); backdrop-filter: blur(6px); -webkit-backdrop-filter: blur(6px); z-index: 1000; opacity: 0; pointer-events: none; transition: opacity .3s; display: flex; align-items: flex-end; justify-content: center; }
        .profile-overlay.active { opacity: 1; pointer-events: auto; }
        .profile-sheet { width: 100%; max-width: 500px; background: var(--surface); border-radius: 24px 24px 0 0; transform: translateY(100%); transition: transform .4s var(--spring); max-height: 90vh; overflow-y: auto; padding-bottom: 40px; }
        .profile-overlay.active .profile-sheet { transform: translateY(0); }
        
        .sheet-header { padding: 32px 24px 20px; text-align: center; position: relative; border-bottom: 1px solid var(--border-solid); }
        .sheet-close { position: absolute; top: 16px; left: 16px; width: 40px; height: 40px; border-radius: 12px; background: var(--surface-2); border: 1.5px solid var(--border-solid); display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text-2); transition: all var(--t); }
        .sheet-close:hover { background: var(--danger-bg); border-color: var(--danger); color: var(--danger); }
        .sheet-photo { width: 110px; height: 110px; border-radius: 28px; border: 4px solid var(--surface); box-shadow: var(--shadow-md); margin: 0 auto 16px; object-fit: cover; display: block; }
        .sheet-name { font-size: 1.4rem; font-weight: 900; margin-bottom: 4px; }
        .sheet-class { color: var(--brand); font-weight: 800; font-size: 0.9rem; }

        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; padding: 20px; }
        .stat-box { background: var(--surface-2); border-radius: 16px; padding: 16px; border: 1.5px solid var(--border-solid); text-align: center; }
        .stat-lbl { font-size: .8rem; color: var(--text-3); font-weight: 800; margin-bottom: 8px; }
        .stat-num { font-size: 1.4rem; font-weight: 900; color: var(--text); }
        .stat-num.total { color: var(--brand); }

        .breakdown-card { background: var(--surface-2); border-radius: 16px; margin: 0 20px 20px; padding: 20px; border: 1.5px solid var(--border-solid); }
        .br-row { display: flex; justify-content: space-between; align-items: center; padding: 10px 0; border-bottom: 1.5px dashed var(--border-solid); }
        .br-row:last-child { border: none; }
        .br-lbl { font-weight: 800; font-size: .9rem; color: var(--text-2); }
        .br-val { font-weight: 900; font-size: 1rem; color: var(--text); }

        .action-box { padding: 0 20px 32px; }
        .input-group { display: flex; gap: 10px; }
        .amount-input { flex: 1; padding: 14px; border-radius: 14px; border: 2px solid var(--border-solid); background: var(--surface-2); font-size: 1.2rem; font-weight: 900; text-align: center; outline: none; transition: all var(--t); }
        .amount-input:focus { border-color: var(--brand); background: var(--surface); }
        .withdraw-btn { padding: 0 28px; border-radius: 14px; border: none; background: var(--brand); color: #fff; font-weight: 900; font-size: 1.1rem; cursor: pointer; transition: all var(--t); }
        .withdraw-btn:active { transform: scale(0.96); }
        .withdraw-btn:disabled { opacity: .4; cursor: not-allowed; }

        .hist-section { padding: 0 20px; }
        .hist-title { font-weight: 900; font-size: 1.1rem; margin-bottom: 16px; padding-right: 4px; }
        .hist-item { background: var(--surface-2); border-radius: 16px; padding: 16px; border: 1.5px solid var(--border-solid); margin-bottom: 12px; }
        .hist-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
        .hist-amt { font-weight: 900; color: var(--danger); font-size: 1.2rem; display: flex; align-items: center; gap: 6px; }
        .hist-amt.refunded { text-decoration: line-through; opacity: .4; color: var(--text-3); }
        .hist-date { font-size: .75rem; color: var(--text-3); font-weight: 800; }
        .hist-uncle { font-size: .8rem; color: var(--text-2); font-weight: 700; }
        .refund-btn { background: var(--warning-bg); color: var(--warning-dark); border: 1.5px solid var(--warning); padding: 6px 14px; border-radius: 10px; font-size: .8rem; font-weight: 900; cursor: pointer; transition: all var(--t); }

        .toast { position: fixed; top: 80px; left: 50%; transform: translateX(-50%) translateY(-20px); background: #1a1d2e; color: #fff; padding: 12px 28px; border-radius: 16px; z-index: 2000; font-weight: 800; opacity: 0; transition: all .3s var(--spring); pointer-events: none; box-shadow: var(--shadow-md); }
        .toast.active { transform: translateX(-50%) translateY(0); opacity: 1; }
        .toast.success { background: var(--success); }
        .toast.error { background: var(--danger); }

        .loading-overlay { position: fixed; inset: 0; background: var(--bg); display: flex; align-items: center; justify-content: center; z-index: 500; transition: opacity .4s; }
        .loading-spinner { width: 40px; height: 40px; border: 4px solid var(--border-solid); border-top-color: var(--brand); border-radius: 50%; animation: spin 1s infinite linear; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .skeleton { animation: pulse 1.5s infinite ease-in-out; background: var(--surface-3); border-radius: 8px; }
        @keyframes pulse { 0% { opacity: 1 } 50% { opacity: 0.5 } 100% { opacity: 1 } }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
    </style>
</head>
<body>

<div id="loadingOverlay" class="loading-overlay">
    <div class="loading-spinner"></div>
</div>

<header class="topbar">
    <a href="/uncle/dashboard/" class="topbar-brand">
        <div class="topbar-logo"><img src="/logo.png" alt="" onerror="this.outerHTML='<i class=\'fas fa-cross\'></i>'"></div>
        <div><div class="topbar-title">سحب الكوبونات</div><div style="font-size:.65rem;font-weight:800;color:var(--text-3)">مدارس الأحد</div></div>
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
        <div id="profileBody"></div>
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
        } finally {
            document.getElementById('loadingOverlay').style.opacity = '0';
            setTimeout(() => document.getElementById('loadingOverlay').style.display = 'none', 400);
        }
    }

    function renderFilters() {
        const container = document.getElementById('classFilters');
        let html = `<div class="pill ${currentClass==='all'?'active':''}" onclick="setFilter('all')">الكل</div>`;
        classes.forEach(c => {
            const name = c.arabic_name || c.code;
            html += `<div class="pill ${currentClass===name?'active':''}" onclick="setFilter('${name}')">${name}</div>`;
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

    function renderClasses() {
        const container = document.getElementById('classList');
        container.innerHTML = classes.map(c => {
            const name = c.arabic_name || c.code;
            const count = allStudents.filter(s => s['الفصل'] === name).length;
            return `
                <div class="class-card" onclick="setFilter('${name}')">
                    <div class="class-icon"><i class="fas fa-chalkboard-teacher"></i></div>
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
            return `
                <div class="kid-item" onclick="openProfile(${s['_studentId']})">
                    ${photo}
                    <div class="kid-info">
                        <div class="kid-name">${s['الاسم']}</div>
                        <div class="kid-meta"><span><i class="fas fa-school"></i> ${s['الفصل']}</span></div>
                    </div>
                    <div class="kid-coupons">${s['كوبونات'] || 0} <i class="fas fa-star" style="font-size:.7rem"></i></div>
                </div>
            `;
        }).join('');
    }

    function performSearch() {
        const q = document.getElementById('searchInput').value.trim().toLowerCase();
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
            <div style="margin-top:12px">
                <button class="pill" style="background:var(--brand-bg);color:var(--brand);border:none;padding:8px 16px;font-size:.85rem" onclick="viewFullAudit(${s['_studentId']})">
                    <i class="fas fa-history"></i> سجل الكوبونات العام
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
            <div class="action-box">
                <div style="font-weight:900;margin-bottom:12px;padding-right:8px">سحب كوبونات</div>
                <div class="input-group">
                    <input type="number" id="wAmount" class="amount-input" placeholder="0" min="1" oninput="checkAmount()">
                    <button class="withdraw-btn" id="wBtn" onclick="submitWithdraw()">سحب</button>
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
                        <div class="hist-amt ${h.is_refunded?'refunded':''}">${h.amount} <i class="fas fa-star" style="font-size:.7rem"></i></div>
                        <div class="hist-date">${h.created_at}</div>
                    </div>
                    <div class="hist-row">
                        <div class="hist-uncle">بواسطة: ${h.uncle_name}</div>
                        ${h.is_refunded ? '<span style="color:var(--warning);font-weight:900;font-size:.75rem">تم الاسترجاع</span>' : `<button class="refund-btn" onclick="refund(${h.id})">استرجاع</button>`}
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

    function showToast(m, t='info') {
        const el = document.getElementById('toast');
        el.textContent = m; el.className = 'toast active ' + t;
        setTimeout(() => el.classList.remove('active'), 3000);
    }

    async function viewFullAudit(sid) {
        const fd = new FormData();
        fd.append('action', 'getEntityAuditHistory');
        fd.append('entity', 'coupon');
        fd.append('entity_id', sid);
        
        showToast('جاري تحميل السجل العام...', 'info');
        try {
            const r = await fetch(API_URL, { method: 'POST', body: fd, credentials: 'include' }).then(r => r.json());
            if (r.success) {
                let html = `<div style="padding:20px"><h3 style="margin-bottom:20px;font-weight:900"><i class="fas fa-list-alt"></i> السجل التاريخي للكوبونات</h3>`;
                if (!r.logs.length) {
                    html += `<div style="text-align:center;padding:40px;color:var(--text-3);font-weight:800">لا يوجد سجلات لهذه الطفل</div>`;
                } else {
                    html += r.logs.map(l => `
                        <div class="hist-item" style="background:var(--surface);border-radius:16px;margin-bottom:12px;padding:14px">
                            <div class="hist-row">
                                <div style="font-weight:900;color:var(--brand);font-size:.95rem">${l.notes || l.action}</div>
                                <div class="hist-date">${l.created_at_formatted}</div>
                            </div>
                            <div style="font-size:.8rem;color:var(--text-3);font-weight:700;margin-top:4px">بواسطة: ${l.uncle_name}</div>
                        </div>
                    `).join('');
                }
                html += `<button class="withdraw-btn" style="width:100%;margin-top:20px" onclick="closeAudit()">إغلاق</button></div>`;
                
                const auditDiv = document.createElement('div');
                auditDiv.id = 'auditModal';
                auditDiv.className = 'profile-overlay active';
                auditDiv.style.zIndex = '1100';
                auditDiv.innerHTML = `<div class="profile-sheet" style="border-radius:24px 24px 0 0">${html}</div>`;
                document.body.appendChild(auditDiv);
            }
        } catch(e) { showToast('خطأ في تحميل السجل', 'error'); }
    }

    function closeAudit() {
        const m = document.getElementById('auditModal');
        if (m) m.remove();
    }

    init();
</script>

</body>
</html>
