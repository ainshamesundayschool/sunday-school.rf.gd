<?php
session_start();
$isHttps = ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443));
if (!isset($_SESSION['uncle_id']) && !isset($_SESSION['church_id'])) {
    header("Location: /login/");
    exit();
}
$churchName = $_SESSION['church_name'] ?? 'الكنيسة';
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
        :root {
            --brand: #5b6cf5;
            --brand-bg: #eef0ff;
            --surface: #ffffff;
            --surface-2: #f7f8fc;
            --surface-3: #eef0f8;
            --bg: #f3f4f9;
            --text: #1a1d2e;
            --text-2: #4b5068;
            --text-3: #8b90a8;
            --border-solid: #e4e6f0;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --coupon: #8b5cf6;
            --shadow-md: 0 8px 24px -4px rgba(0, 0, 0, .10);
            --r-md: 14px;
            --r-lg: 18px;
            --r-xl: 24px;
            --t: .22s;
            --ease: cubic-bezier(.4, 0, .2, 1);
        }

        [data-theme="dark"] {
            --bg: #0f1117;
            --surface: #181b26;
            --surface-2: #1e2132;
            --surface-3: #252840;
            --text: #e8eaf6;
            --text-2: #9299be;
            --text-3: #565c7a;
            --border-solid: #2a2d42;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Cairo', sans-serif; }
        body { background: var(--bg); color: var(--text); min-height: 100vh; padding-bottom: 40px; transition: background var(--t), color var(--t); }
        
        .header {
            position: sticky; top: 0; z-index: 100;
            background: var(--surface); padding: 12px 16px;
            display: flex; align-items: center; gap: 12px;
            box-shadow: 0 1px 0 var(--border-solid);
        }
        .back-btn { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: var(--surface-2); color: var(--text); text-decoration: none; border: 1px solid var(--border-solid); }
        .page-title { font-size: 1.1rem; font-weight: 800; }

        .search-container { padding: 20px 16px; text-align: center; max-width: 600px; margin: 0 auto; }
        .search-box { position: relative; width: 100%; margin-top: 20px; }
        .search-input { width: 100%; padding: 14px 44px 14px 16px; border-radius: var(--r-md); border: 2px solid var(--border-solid); background: var(--surface); font-size: 1rem; color: var(--text); outline: none; transition: border-color var(--t); text-align: center; }
        .search-input:focus { border-color: var(--brand); }
        .search-icon { position: absolute; right: 16px; top: 50%; transform: translateY(-50%); color: var(--text-3); }

        .filter-pills { display: flex; gap: 8px; overflow-x: auto; padding: 0 16px 16px; scrollbar-width: none; }
        .filter-pills::-webkit-scrollbar { display: none; }
        .pill { padding: 8px 16px; border-radius: var(--r-full); background: var(--surface); border: 1.5px solid var(--border-solid); font-size: .85rem; font-weight: 700; color: var(--text-2); cursor: pointer; white-space: nowrap; transition: all var(--t); }
        .pill.active { background: var(--brand); color: #fff; border-color: var(--brand); box-shadow: 0 4px 12px var(--brand); }

        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 12px; padding: 16px; }
        .card { background: var(--surface); border-radius: var(--r-md); padding: 16px; border: 1px solid var(--border-solid); text-align: center; cursor: pointer; transition: transform var(--t); box-shadow: var(--shadow-md); }
        .card:active { transform: scale(0.97); }
        .card-icon { width: 48px; height: 48px; background: var(--brand-bg); color: var(--brand); border-radius: 12px; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-size: 1.2rem; }
        .card-name { font-weight: 800; font-size: .95rem; margin-bottom: 4px; }
        .card-count { font-size: .75rem; color: var(--text-3); font-weight: 600; }

        .kid-list { display: flex; flex-direction: column; gap: 10px; padding: 16px; }
        .kid-item { background: var(--surface); border-radius: var(--r-md); padding: 12px; border: 1px solid var(--border-solid); display: flex; align-items: center; gap: 12px; cursor: pointer; transition: background var(--t); }
        .kid-item:active { background: var(--surface-2); }
        .kid-photo { width: 48px; height: 48px; border-radius: 50%; object-fit: cover; background: var(--brand-bg); display: flex; align-items: center; justify-content: center; color: var(--brand); font-size: 1rem; flex-shrink: 0; }
        .kid-info { flex: 1; }
        .kid-name { font-weight: 800; font-size: .95rem; }
        .kid-class { font-size: .75rem; color: var(--text-3); font-weight: 600; }
        .kid-coupons { font-weight: 800; color: var(--coupon); display: flex; align-items: center; gap: 4px; font-size: .9rem; }

        .profile-view { background: var(--surface); min-height: 100vh; position: fixed; top: 0; left: 0; width: 100%; z-index: 200; transform: translateX(100%); transition: transform .35s cubic-bezier(0.4, 0, 0.2, 1); overflow-y: auto; }
        .profile-view.active { transform: translateX(0); }
        
        .profile-header { padding: 40px 20px 20px; text-align: center; background: var(--surface-2); border-bottom: 1px solid var(--border-solid); position: relative; }
        .profile-close { position: absolute; top: 12px; left: 16px; width: 36px; height: 36px; border-radius: 50%; background: var(--surface); border: 1px solid var(--border-solid); display: flex; align-items: center; justify-content: center; cursor: pointer; }
        .profile-photo { width: 100px; height: 100px; border-radius: 50%; border: 4px solid var(--surface); box-shadow: var(--shadow-md); margin-bottom: 12px; object-fit: cover; }
        .profile-name { font-size: 1.4rem; font-weight: 800; margin-bottom: 4px; }
        .profile-class { color: var(--text-3); font-weight: 700; font-size: .9rem; }

        .coupon-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; padding: 20px 16px; }
        .stat-card { background: var(--surface-2); border-radius: var(--r-md); padding: 16px; border: 1px solid var(--border-solid); }
        .stat-label { font-size: .75rem; color: var(--text-3); font-weight: 700; margin-bottom: 6px; }
        .stat-val { font-size: 1.2rem; font-weight: 900; color: var(--text); }
        .stat-val.total { color: var(--brand); }

        .breakdown { background: var(--surface-2); border-radius: var(--r-md); margin: 0 16px 20px; padding: 16px; border: 1px solid var(--border-solid); }
        .breakdown-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; border-bottom: 1px dashed var(--border-solid); }
        .breakdown-row:last-child { border: none; }
        .breakdown-label { font-size: .85rem; font-weight: 700; color: var(--text-2); }
        .breakdown-val { font-weight: 800; font-size: .9rem; }

        .withdraw-section { padding: 0 16px 20px; }
        .section-title { font-size: .95rem; font-weight: 800; margin-bottom: 12px; padding-right: 4px; }
        .withdraw-input-wrap { display: flex; gap: 10px; }
        .withdraw-input { flex: 1; padding: 14px 16px; border-radius: var(--r-md); border: 2px solid var(--border-solid); background: var(--surface-2); font-size: 1.1rem; font-weight: 800; text-align: center; outline: none; }
        .withdraw-input:focus { border-color: var(--brand); }
        .withdraw-btn { padding: 0 24px; border-radius: var(--r-md); border: none; background: var(--brand); color: #fff; font-weight: 800; cursor: pointer; box-shadow: 0 4px 12px var(--brand); transition: transform var(--t); }
        .withdraw-btn:active { transform: scale(0.95); }
        .withdraw-btn:disabled { opacity: .5; cursor: not-allowed; box-shadow: none; }

        .history-section { padding: 0 16px; }
        .history-list { display: flex; flex-direction: column; gap: 12px; }
        .history-item { background: var(--surface-2); border-radius: var(--r-md); padding: 12px; border: 1px solid var(--border-solid); position: relative; }
        .history-top { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .history-amount { font-weight: 900; color: var(--danger); font-size: 1.1rem; }
        .history-amount.refunded { text-decoration: line-through; opacity: .5; color: var(--text-3); }
        .history-date { font-size: .7rem; color: var(--text-3); font-weight: 700; }
        .history-uncle { font-size: .75rem; color: var(--text-2); font-weight: 600; }
        .history-note { font-size: .8rem; color: var(--text-3); font-style: italic; margin-top: 4px; }
        .refund-btn { position: absolute; top: 12px; left: 12px; background: var(--warning-bg); color: var(--warning-dark); border: none; padding: 4px 10px; border-radius: var(--r-sm); font-size: .7rem; font-weight: 800; cursor: pointer; }

        .toast { position: fixed; bottom: 30px; left: 50%; transform: translateX(-50%) translateY(100px); background: #333; color: #fff; padding: 12px 24px; border-radius: var(--r-full); z-index: 1000; font-weight: 700; transition: transform .3s var(--spring); }
        .toast.active { transform: translateX(-50%) translateY(0); }
        .toast.success { background: var(--success); }
        .toast.error { background: var(--danger); }

        .skeleton { animation: pulse 1.5s infinite ease-in-out; background: var(--surface-3); border-radius: 8px; }
        @keyframes pulse { 0% { opacity: 1 } 50% { opacity: 0.5 } 100% { opacity: 1 } }
    </style>
</head>
<body>

<header class="header">
    <a href="/uncle/dashboard/" class="back-btn"><i class="fas fa-arrow-right"></i></a>
    <div class="page-title">سحب كوبونات</div>
    <div style="flex:1"></div>
    <button id="themeToggle" class="back-btn"><i class="fas fa-moon"></i></button>
</header>

<div id="mainView">
    <div class="search-container">
        <h2 style="font-weight:900;font-size:1.4rem">ابحث عن طفل للسحب</h2>
        <div class="search-box">
            <i class="fas fa-search search-icon"></i>
            <input type="text" id="searchInput" class="search-input" placeholder="اسم الطفل أو الفصل...">
        </div>
    </div>

    <div class="filter-pills" id="classFilters">
        <div class="pill active" data-class="all">الكل</div>
        <!-- Classes will be loaded here -->
    </div>

    <div id="classList" class="grid">
        <!-- Classes will be shown here first -->
    </div>

    <div id="studentList" class="kid-list" style="display:none">
        <!-- Students will be shown here when a class is picked or searching -->
    </div>
</div>

<div id="profileView" class="profile-view">
    <div class="profile-close" onclick="closeProfile()"><i class="fas fa-times"></i></div>
    <div id="profileContent"></div>
</div>

<div id="toast" class="toast"></div>

<script>
    const API_URL = '/api.php';
    let allStudents = [];
    let classes = [];
    let currentClass = 'all';
    let selectedStudent = null;

    // Dark mode handling
    if (localStorage.getItem('theme') === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
    document.getElementById('themeToggle').onclick = () => {
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        document.documentElement.setAttribute('data-theme', isDark ? '' : 'dark');
        localStorage.setItem('theme', isDark ? 'light' : 'dark');
        document.getElementById('themeToggle').innerHTML = `<i class="fas fa-${isDark ? 'moon' : 'sun'}"></i>`;
    };

    async function loadData() {
        const fd = new FormData();
        fd.append('action', 'getData');
        try {
            const r = await fetch(API_URL, { method: 'POST', body: fd, credentials: 'include' }).then(r => r.json());
            if (r.success) {
                allStudents = r.data || [];
                classes = r.classes || [];
                renderClasses();
                renderFilters();
            }
        } catch (e) { showToast('خطأ في تحميل البيانات', 'error'); }
    }

    function renderFilters() {
        const container = document.getElementById('classFilters');
        const active = currentClass;
        container.innerHTML = '<div class="pill ' + (active === 'all' ? 'active' : '') + '" onclick="setFilter(\'all\')">الكل</div>';
        classes.forEach(c => {
            const name = c.arabic_name || c.code;
            container.innerHTML += `<div class="pill ${active === name ? 'active' : ''}" onclick="setFilter('${name}')">${name}</div>`;
        });
    }

    function setFilter(cls) {
        currentClass = cls;
        renderFilters();
        if (cls === 'all') {
            document.getElementById('classList').style.display = 'grid';
            document.getElementById('studentList').style.display = 'none';
            renderClasses();
        } else {
            document.getElementById('classList').style.display = 'none';
            document.getElementById('studentList').style.display = 'flex';
            renderStudents(cls);
        }
    }

    function renderClasses() {
        const container = document.getElementById('classList');
        container.innerHTML = '';
        classes.forEach(c => {
            const name = c.arabic_name || c.code;
            const count = allStudents.filter(s => s['الفصل'] === name).length;
            container.innerHTML += `
                <div class="card" onclick="setFilter('${name}')">
                    <div class="card-icon"><i class="fas fa-chalkboard-teacher"></i></div>
                    <div class="card-name">${name}</div>
                    <div class="card-count">${count} طفل</div>
                </div>
            `;
        });
    }

    function renderStudents(cls, query = '') {
        const container = document.getElementById('studentList');
        container.innerHTML = '';
        let filtered = allStudents;
        if (cls !== 'all') filtered = filtered.filter(s => s['الفصل'] === cls);
        if (query) {
            const q = query.toLowerCase();
            filtered = filtered.filter(s => 
                (s['الاسم'] || '').toLowerCase().includes(q) || 
                (s['الفصل'] || '').toLowerCase().includes(q)
            );
        }

        if (!filtered.length) {
            container.innerHTML = '<div style="text-align:center;padding:40px;color:var(--text-3)">لا يوجد نتائج</div>';
            return;
        }

        filtered.forEach(s => {
            const photo = s['صورة'] ? `<img src="${s['صورة']}" class="kid-photo" onerror="this.outerHTML='<div class=\\'kid-photo\\'><i class=\\'fas fa-user\\'></i></div>'">` : '<div class="kid-photo"><i class="fas fa-user"></i></div>';
            container.innerHTML += `
                <div class="kid-item" onclick="openProfile(${s['_studentId']})">
                    ${photo}
                    <div class="kid-info">
                        <div class="kid-name">${s['الاسم']}</div>
                        <div class="kid-class">${s['الفصل']}</div>
                    </div>
                    <div class="kid-coupons">${s['كوبونات'] || 0} <i class="fas fa-star" style="font-size:.7rem"></i></div>
                </div>
            `;
        });
    }

    document.getElementById('searchInput').oninput = (e) => {
        const q = e.target.value.trim();
        if (q.length > 0) {
            document.getElementById('classList').style.display = 'none';
            document.getElementById('studentList').style.display = 'flex';
            renderStudents(currentClass, q);
        } else {
            setFilter(currentClass);
        }
    };

    async function openProfile(id) {
        const s = allStudents.find(x => x['_studentId'] === id);
        if (!s) return;
        selectedStudent = s;
        
        const photo = s['صورة'] ? `<img src="${s['صورة']}" class="profile-photo" onerror="this.outerHTML='<div class=\\'profile-photo\\' style=\\'display:flex;align-items:center;justify-content:center;background:var(--brand-bg);color:var(--brand);font-size:2rem\\'><i class=\\'fas fa-user\\'></i></div>'">` : '<div class="profile-photo" style="display:flex;align-items:center;justify-content:center;background:var(--brand-bg);color:var(--brand);font-size:2rem"><i class="fas fa-user"></i></div>';
        
        document.getElementById('profileContent').innerHTML = `
            <div class="profile-header">
                ${photo}
                <div class="profile-name">${s['الاسم']}</div>
                <div class="profile-class">${s['الفصل']}</div>
            </div>
            <div class="coupon-stats">
                <div class="stat-card">
                    <div class="stat-label">إجمالي المتاح</div>
                    <div class="stat-val total" id="currentTotal">${s['كوبونات'] || 0}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label">تاريخ الميلاد</div>
                    <div class="stat-val" style="font-size:1rem">${s['عيد الميلاد'] || '---'}</div>
                </div>
            </div>
            <div class="breakdown">
                <div class="breakdown-row">
                    <div class="breakdown-label">كوبونات الحضور</div>
                    <div class="breakdown-val">${s['كوبونات الحضور'] || 0}</div>
                </div>
                <div class="breakdown-row">
                    <div class="breakdown-label">كوبونات الالتزام</div>
                    <div class="breakdown-val">${s['كوبونات الالتزام'] || 0}</div>
                </div>
                <div class="breakdown-row">
                    <div class="breakdown-label">كوبونات المهام</div>
                    <div class="breakdown-val">${s['كوبونات المهام'] || 0}</div>
                </div>
            </div>
            <div class="withdraw-section">
                <div class="section-title">سحب كوبونات</div>
                <div class="withdraw-input-wrap">
                    <input type="number" id="withdrawAmount" class="withdraw-input" placeholder="0" min="1">
                    <button class="withdraw-btn" id="withdrawBtn" onclick="submitWithdraw()">سحب</button>
                </div>
            </div>
            <div class="history-section">
                <div class="section-title">سجل السحب</div>
                <div id="historyList" class="history-list">
                    <div style="text-align:center;padding:20px;color:var(--text-3)">جارِ التحميل...</div>
                </div>
            </div>
        `;
        
        document.getElementById('profileView').classList.add('active');
        loadHistory(id);
    }

    function closeProfile() {
        document.getElementById('profileView').classList.remove('active');
        selectedStudent = null;
        loadData(); // refresh main list
    }

    async function loadHistory(studentId) {
        const fd = new FormData();
        fd.append('action', 'getWithdrawalHistory');
        fd.append('student_id', studentId);
        try {
            const r = await fetch(API_URL, { method: 'POST', body: fd, credentials: 'include' }).then(r => r.json());
            const container = document.getElementById('historyList');
            if (r.success && r.history.length) {
                container.innerHTML = r.history.map(h => `
                    <div class="history-item">
                        <div class="history-top">
                            <div class="history-amount ${h.is_refunded ? 'refunded' : ''}">${h.amount} <i class="fas fa-star" style="font-size:.7rem"></i></div>
                            <div class="history-date">${h.created_at}</div>
                        </div>
                        <div class="history-uncle">بواسطة: ${h.uncle_name}</div>
                        ${h.is_refunded ? '<div style="color:var(--warning-dark);font-size:.7rem;font-weight:800;margin-top:4px">تم الاسترجاع في ' + h.refunded_at + '</div>' : `<button class="refund-btn" onclick="refund(${h.id})">استرجاع</button>`}
                    </div>
                `).join('');
            } else {
                container.innerHTML = '<div style="text-align:center;padding:20px;color:var(--text-3)">لا توجد عمليات سحب سابقة</div>';
            }
        } catch (e) { }
    }

    async function submitWithdraw() {
        const amount = parseInt(document.getElementById('withdrawAmount').value);
        if (!amount || amount <= 0) { showToast('أدخل قيمة صحيحة', 'error'); return; }
        if (amount > (selectedStudent['كوبونات'] || 0)) { showToast('الرصيد غير كافٍ', 'error'); return; }
        
        const btn = document.getElementById('withdrawBtn');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
        
        const fd = new FormData();
        fd.append('action', 'withdrawCoupons');
        fd.append('student_id', selectedStudent['_studentId']);
        fd.append('amount', amount);
        
        try {
            const r = await fetch(API_URL, { method: 'POST', body: fd, credentials: 'include' }).then(r => r.json());
            if (r.success) {
                showToast('تم السحب بنجاح', 'success');
                selectedStudent['كوبونات'] = r.newTotal;
                document.getElementById('currentTotal').textContent = r.newTotal;
                document.getElementById('withdrawAmount').value = '';
                loadHistory(selectedStudent['_studentId']);
            } else {
                showToast(r.message || 'فشل السحب', 'error');
            }
        } catch (e) { showToast('خطأ في الاتصال', 'error'); }
        btn.disabled = false;
        btn.innerHTML = 'سحب';
    }

    async function refund(withdrawalId) {
        if (!confirm('هل أنت متأكد من استرجاع هذه الكوبونات؟')) return;
        
        const fd = new FormData();
        fd.append('action', 'refundWithdrawal');
        fd.append('withdrawal_id', withdrawalId);
        
        try {
            const r = await fetch(API_URL, { method: 'POST', body: fd, credentials: 'include' }).then(r => r.json());
            if (r.success) {
                showToast('تم الاسترجاع بنجاح', 'success');
                // Refresh full data to update total coupons in memory
                const refreshFd = new FormData(); refreshFd.append('action', 'getData');
                const refreshR = await fetch(API_URL, { method: 'POST', body: refreshFd, credentials: 'include' }).then(r => r.json());
                if (refreshR.success) {
                    allStudents = refreshR.data;
                    const s = allStudents.find(x => x['_studentId'] === selectedStudent['_studentId']);
                    if (s) {
                        selectedStudent = s;
                        document.getElementById('currentTotal').textContent = s['كوبونات'];
                    }
                }
                loadHistory(selectedStudent['_studentId']);
            } else {
                showToast(r.message || 'فشل الاسترجاع', 'error');
            }
        } catch (e) { showToast('خطأ في الاتصال', 'error'); }
    }

    function showToast(msg, type = 'info') {
        const t = document.getElementById('toast');
        t.textContent = msg;
        t.className = 'toast active ' + type;
        setTimeout(() => { t.classList.remove('active'); }, 3000);
    }

    loadData();
</script>

</body>
</html>
