<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>لوحة التحكم - منصة الأخوة</title>
    
    <!-- Google Fonts: Cairo & Baloo Bhaijaan 2 -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+Bhaijaan+2:wght@400;500;600;700;800&family=Cairo:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- FontAwesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Intelligent Search & QR Scanner -->
    <script src="../js/search_intelligent.js"></script>
    <script src="/js/search_intelligent.js"></script>
    <script src="../js/qr-scanner.umd.min.js"></script>
    <script src="/js/qr-scanner.umd.min.js"></script>

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
            top: 0; left: 0; width: 100%; height: 100%;
            background: 
                radial-gradient(ellipse 80% 50% at 10% -10%, rgba(91, 108, 245, .08) 0%, transparent 60%),
                radial-gradient(ellipse 60% 40% at 90% 110%, rgba(139, 92, 246, .06) 0%, transparent 60%);
            pointer-events: none;
            z-index: 0;
        }

        .container {
            max-width: 1040px;
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
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid var(--border-solid);
            padding: 8px 14px;
            border-radius: var(--r-xl);
            margin-bottom: 14px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-sm);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 1.05rem;
            font-weight: 900;
            color: var(--text);
            text-decoration: none;
        }

        .brand-logo-img {
            width: 32px;
            height: 32px;
            object-fit: contain;
        }

        .topbar-actions {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .portal-link {
            background: var(--brand-bg);
            color: var(--brand-dark);
            padding: 6px 10px;
            border-radius: var(--r-md);
            text-decoration: none;
            font-weight: 800;
            font-size: 0.78rem;
            display: flex;
            align-items: center;
            gap: 5px;
            border: 1px solid rgba(91, 108, 245, 0.2);
            transition: all 0.2s;
        }

        .portal-link:hover { background: var(--brand); color: #fff; }

        .logout-btn {
            background: var(--surface-2);
            color: var(--text-2);
            border: 1px solid var(--border-solid);
            padding: 6px 10px;
            border-radius: var(--r-md);
            font-weight: 800;
            font-size: 0.78rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .logout-btn:hover { background: var(--danger-bg); color: var(--danger); }

        /* Nav Tabs */
        .nav-tabs {
            display: flex;
            gap: 8px;
            overflow-x: auto;
            padding-bottom: 4px;
            margin-bottom: 14px;
            scrollbar-width: none;
        }

        .nav-tabs::-webkit-scrollbar { display: none; }

        .tab-btn {
            background: var(--surface);
            border: 1px solid var(--border-solid);
            color: var(--text-2);
            padding: 8px 14px;
            border-radius: var(--r-lg);
            font-size: 0.85rem;
            font-weight: 800;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            white-space: nowrap;
            transition: all 0.2s ease;
            box-shadow: var(--shadow-sm);
        }

        .tab-btn:hover { color: var(--brand); border-color: var(--brand-light); }
        .tab-btn.active { background: var(--brand); color: #fff; border-color: var(--brand); box-shadow: 0 4px 14px var(--brand-glow); }

        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.2s ease; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(4px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .panel-card {
            background: var(--surface);
            border: 1px solid var(--border-solid);
            border-radius: var(--r-xl);
            padding: 16px;
            margin-bottom: 14px;
            box-shadow: var(--shadow-md);
        }

        .card-header-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px;
        }

        .card-title {
            font-size: 1.05rem;
            font-weight: 900;
            color: var(--text);
            display: flex;
            align-items: center;
            gap: 7px;
        }

        .card-title i { color: var(--brand); }

        .btn-add-action {
            background: var(--brand);
            color: #fff;
            border: none;
            padding: 8px 14px;
            border-radius: var(--r-md);
            font-size: 0.82rem;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            box-shadow: 0 4px 12px var(--brand-glow);
            transition: all 0.2s;
            white-space: nowrap;
        }

        .btn-add-action:hover { background: var(--brand-dark); transform: translateY(-1px); }

        /* Search Input */
        .search-box { position: relative; width: 100%; margin-bottom: 12px; }

        .search-box input {
            width: 100%; background: var(--surface-2); border: 1.5px solid var(--border-solid);
            padding: 9px 38px 9px 12px; border-radius: var(--r-md); color: var(--text);
            font-size: 0.86rem; font-weight: 600; outline: none; transition: all 0.2s;
        }

        .search-box input:focus { border-color: var(--brand); background: #fff; box-shadow: 0 0 0 3px var(--brand-glow); }
        .search-box i { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); color: var(--text-3); }

        /* Responsive Compact List Layout for Users */
        .user-list-container {
            display: flex; flex-direction: column; gap: 8px;
        }

        .user-list-row {
            background: var(--surface-2); border: 1px solid var(--border-solid);
            border-radius: var(--r-lg); padding: 10px 14px; display: flex;
            align-items: center; justify-content: space-between; gap: 12px;
            transition: all 0.2s ease;
        }

        .user-list-row:hover {
            border-color: var(--brand-light); background: #fff; box-shadow: var(--shadow-sm);
        }

        .user-row-top-line {
            display: flex; align-items: center; justify-content: space-between; gap: 10px; flex: 1; min-width: 0;
        }

        .user-row-main {
            display: flex; align-items: center; gap: 10px; flex: 1; min-width: 0;
        }

        .user-row-avatar {
            width: 40px; height: 40px; border-radius: 50%; object-fit: cover;
            display: flex; align-items: center; justify-content: center; font-size: 0.95rem;
            font-weight: 800; flex-shrink: 0; border: 2px solid var(--surface); box-shadow: var(--shadow-sm);
        }
        .user-row-avatar.male { background: linear-gradient(135deg, #60a5fa, #2563eb); color: #fff; }
        .user-row-avatar.female { background: linear-gradient(135deg, #f472b6, #db2777); color: #fff; }

        .user-row-details { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
        .user-row-title { font-size: 0.92rem; font-weight: 900; color: var(--text); display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
        .user-row-sub { font-size: 0.76rem; color: var(--text-3); font-weight: 600; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }

        .user-row-points {
            background: var(--warning-bg); color: var(--warning-dark); padding: 3px 9px;
            border-radius: var(--r-full); font-size: 0.76rem; font-weight: 900; white-space: nowrap; flex-shrink: 0;
        }

        .user-row-actions { display: flex; align-items: center; gap: 5px; flex-shrink: 0; }

        .action-btn {
            padding: 6px 9px; border-radius: var(--r-sm); border: 1px solid transparent;
            font-weight: 800; font-size: 0.76rem; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 4px;
        }

        .btn-info { background: var(--surface-3); color: var(--text-2); border-color: var(--border-solid); }
        .btn-info:hover { background: var(--brand-bg); color: var(--brand); }
        .btn-edit { background: var(--brand-bg); color: var(--brand-dark); border-color: rgba(91,108,245,0.2); }
        .btn-delete { background: var(--danger-bg); color: var(--danger-dark); border-color: rgba(239,68,68,0.2); padding: 6px 8px; }
        .btn-add-pt { background: var(--success-bg); color: var(--success-dark); border-color: rgba(16,185,129,0.2); }

        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; margin-bottom: 12px; }
        .form-group { margin-bottom: 12px; }
        .form-label { display: block; font-size: 0.8rem; font-weight: 800; color: var(--text-2); margin-bottom: 4px; }

        .form-input, .form-select, .form-textarea {
            width: 100%; background: var(--surface-2); border: 1.5px solid var(--border-solid);
            padding: 9px 12px; border-radius: var(--r-md); color: var(--text); font-size: 0.88rem; font-weight: 600; outline: none;
        }
        .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: var(--brand); background: #fff; }

        .btn-primary {
            background: var(--brand); color: #fff; border: none; padding: 11px 20px;
            border-radius: var(--r-md); font-size: 0.9rem; font-weight: 900; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center; gap: 7px; width: 100%;
        }

        .btn-primary:hover { background: var(--brand-dark); }
        .bulk-textarea { height: 140px; font-family: monospace; font-size: 0.85rem; line-height: 1.5; }

        /* Clickable Event Cards */
        .events-list-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 14px; }

        .event-admin-card {
            background: var(--surface-2); border: 1px solid var(--border-solid);
            border-radius: var(--r-lg); padding: 16px; display: flex; flex-direction: column;
            justify-content: space-between; cursor: pointer; transition: all 0.2s ease; position: relative;
        }

        .event-admin-card:hover {
            border-color: var(--brand); transform: translateY(-2px); box-shadow: var(--shadow-md); background: #fff;
        }

        .event-admin-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 6px; }
        .event-admin-title { font-size: 1.05rem; font-weight: 900; color: var(--text); }
        .event-attendance-badge { background: var(--brand-bg); color: var(--brand-dark); padding: 3px 10px; border-radius: var(--r-full); font-size: 0.78rem; font-weight: 800; }
        .event-date-text { font-size: 0.8rem; color: var(--text-3); margin-bottom: 8px; font-weight: 600; }
        .event-click-hint { font-size: 0.78rem; font-weight: 800; color: var(--brand); display: flex; align-items: center; gap: 6px; margin-top: 10px; }

        .points-panel-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }

        .gear-btn {
            background: var(--surface-2); color: var(--text-2); border: 1px solid var(--border-solid);
            width: 36px; height: 36px; border-radius: 50%; cursor: pointer; display: flex;
            align-items: center; justify-content: center; font-size: 1rem; transition: all 0.2s;
        }

        .gear-btn:hover { background: var(--brand-bg); color: var(--brand); transform: rotate(45deg); }

        .mode-toggle { display: flex; background: var(--surface-2); border: 1px solid var(--border-solid); border-radius: var(--r-md); padding: 3px; gap: 4px; margin-bottom: 14px; }

        .mode-btn { flex: 1; padding: 8px; border: none; background: none; color: var(--text-3); font-weight: 800; font-size: 0.88rem; border-radius: var(--r-sm); cursor: pointer; transition: all 0.15s ease; }
        .mode-btn.active { background: var(--brand); color: #fff; }
        .mode-btn.active.add { background: var(--success); color: #fff; }
        .mode-btn.active.deduct { background: var(--danger); color: #fff; }

        .shortcuts-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 8px; margin-bottom: 14px; }

        .shortcut-chip {
            background: var(--brand-bg); border: 1.5px solid rgba(91, 108, 245, 0.25); color: var(--brand-dark);
            padding: 11px; border-radius: var(--r-md); font-size: 1.05rem; font-weight: 900; text-align: center; cursor: pointer;
            transition: all 0.18s ease;
        }

        .shortcut-chip.selected {
            background: var(--brand); color: #fff; border-color: var(--brand-dark); box-shadow: 0 4px 12px var(--brand-glow);
        }

        /* Reason Chips Layout */
        .reasons-chips-grid { display: flex; flex-wrap: wrap; gap: 8px; margin-bottom: 14px; }

        .reason-chip {
            background: var(--surface-2); border: 1.5px solid var(--border-solid); color: var(--text-2);
            padding: 8px 16px; border-radius: var(--r-full); font-size: 0.85rem; font-weight: 800; cursor: pointer;
            transition: all 0.18s ease; display: inline-flex; align-items: center; gap: 6px;
        }

        .reason-chip:hover { border-color: var(--brand-light); color: var(--brand); }
        .reason-chip.selected { background: var(--brand-bg); border-color: var(--brand); color: var(--brand-dark); box-shadow: var(--shadow-sm); }

        /* RESPONSIVE MODAL SYSTEM */
        .modal-overlay {
            position: fixed; top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px);
            z-index: 1000; display: none; align-items: flex-end; justify-content: center;
            padding: 0; transition: opacity 0.2s ease;
        }

        .modal-overlay.active { display: flex; }

        .modal-card {
            background: var(--surface); border: 1px solid var(--border-solid);
            width: 100%; max-width: 580px; max-height: 85vh; display: flex; flex-direction: column;
            box-shadow: var(--shadow-xl); overflow: hidden; position: relative;
            border-radius: 24px 24px 0 0; animation: slideUpMobile 0.3s var(--spring);
        }

        .modal-card::before {
            content: ''; position: absolute; top: 8px; left: 50%; transform: translateX(-50%);
            width: 38px; height: 4px; border-radius: 99px; background: var(--border-solid); z-index: 10;
        }

        @media (min-width: 769px) {
            .modal-overlay { align-items: center; padding: 20px; }
            .modal-card { border-radius: var(--r-xl); max-height: 90vh; animation: fadeScaleIn 0.25s var(--spring); }
            .modal-card::before { display: none; }
        }

        @keyframes slideUpMobile {
            from { transform: translateY(100%); }
            to { transform: translateY(0); }
        }

        @keyframes fadeScaleIn {
            from { opacity: 0; transform: scale(0.95) translateY(12px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }

        .modal-header {
            padding: 16px 20px; border-bottom: 1px solid var(--border-solid);
            display: flex; align-items: center; justify-content: space-between; background: var(--surface-2);
        }

        .modal-title { font-size: 1.1rem; font-weight: 900; color: var(--text); display: flex; align-items: center; gap: 8px; }
        .modal-close { background: none; border: none; color: var(--text-3); font-size: 1.5rem; cursor: pointer; line-height: 1; }
        .modal-close:hover { color: var(--danger); }
        .modal-body { padding: 20px; overflow-y: auto; }

        /* FULL SCREEN QR SCANNER PAGE STYLING */
        .fullscreen-page {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: #0f172a; color: #fff; z-index: 99999;
            display: flex; flex-direction: column; overflow: hidden;
            animation: fadeIn 0.2s ease;
        }

        .fullscreen-header {
            background: rgba(15, 23, 42, 0.95); backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255,255,255,0.1); padding: 12px 16px;
            display: flex; align-items: center; justify-content: space-between; gap: 12px;
        }

        .back-btn {
            background: rgba(255,255,255,0.12); color: #fff; border: none;
            padding: 8px 14px; border-radius: var(--r-full); font-size: 0.88rem; font-weight: 800;
            cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s;
        }

        .back-btn:hover { background: rgba(255,255,255,0.25); }

        .fullscreen-title {
            font-size: 1.05rem; font-weight: 900; color: #fff; text-align: center;
            flex: 1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }

        .fullscreen-body {
            flex: 1; display: flex; flex-direction: column; padding: 16px;
            gap: 14px; max-width: 650px; width: 100%; margin: 0 auto;
        }

        .fullscreen-video-wrap {
            flex: 1; width: 100%; background: #000; border-radius: var(--r-xl);
            overflow: hidden; position: relative; box-shadow: var(--shadow-xl);
            border: 2px solid rgba(255,255,255,0.15); min-height: 280px;
        }

        #qrVideo { width: 100%; height: 100%; object-fit: cover; }

        .scan-target-box {
            position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);
            width: 220px; height: 220px; border: 3px dashed rgba(255, 255, 255, 0.7);
            border-radius: var(--r-lg); box-shadow: 0 0 0 9999px rgba(0, 0, 0, 0.45);
            pointer-events: none;
        }

        /* Mobile Optimization Media Query */
        @media (max-width: 768px) {
            .container { padding: 8px; }
            .topbar { padding: 6px 10px; margin-bottom: 10px; border-radius: var(--r-md); }
            .brand { font-size: 0.92rem; gap: 6px; }
            .brand-logo-img { width: 26px; height: 26px; }
            .topbar-actions { gap: 4px; }
            .portal-link, .logout-btn { padding: 6px 8px; font-size: 0.72rem; border-radius: var(--r-sm); }
            .portal-link .btn-text, .logout-btn .btn-text { display: none; }

            .card-header-bar { align-items: center; justify-content: space-between; gap: 8px; }
            .btn-add-action { width: 36px; height: 36px; padding: 0; border-radius: 50%; justify-content: center; font-size: 0.95rem; }
            .btn-add-action .btn-text { display: none; }

            .user-list-row { flex-direction: column; align-items: stretch; gap: 8px; padding: 10px 12px; }
            .user-row-actions { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; width: 100%; padding-top: 6px; border-top: 1px dashed var(--border-solid); }
            .user-row-actions .action-btn { width: 100%; padding: 8px 0; font-size: 0.85rem; justify-content: center; }
            .user-row-actions .action-btn .btn-text { display: none; }
        }

        /* User Info Detail Rows */
        .info-detail-wrap { display: flex; flex-direction: column; align-items: center; text-align: center; margin-bottom: 16px; padding-bottom: 16px; border-bottom: 1px solid var(--border-solid); }
        .info-detail-avatar { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; background: var(--brand-bg); color: var(--brand); display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 900; margin-bottom: 10px; border: 3px solid var(--brand-light); box-shadow: var(--shadow-md); }
        .info-detail-name { font-size: 1.25rem; font-weight: 900; color: var(--text); }
        .info-detail-code { background: var(--brand-bg); color: var(--brand-dark); padding: 3px 10px; border-radius: var(--r-full); font-size: 0.8rem; font-weight: 800; display: inline-block; margin-top: 4px; }
        
        .info-rows-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; margin-bottom: 16px; }
        .info-row-item { background: var(--surface-2); padding: 10px 12px; border-radius: var(--r-md); border: 1px solid var(--border-solid); display: flex; align-items: center; gap: 10px; font-size: 0.85rem; }
        .info-row-item i { color: var(--brand); font-size: 0.95rem; width: 18px; text-align: center; }

        .qr-preview-box { text-align: center; background: #fff; padding: 14px; border-radius: var(--r-lg); border: 1px solid var(--border-solid); margin-bottom: 16px; }
        .qr-preview-box img { width: 150px; height: 150px; object-fit: contain; }

        .scan-result-card {
            background: #1e293b; border: 1px solid rgba(255,255,255,0.15);
            border-radius: var(--r-lg); padding: 14px; display: none; align-items: center;
            gap: 14px; box-shadow: var(--shadow-md); animation: fadeIn 0.2s ease; color: #fff;
        }

        .scanned-user-avatar {
            width: 50px; height: 50px; border-radius: 50%; background: var(--brand-bg);
            color: var(--brand); font-size: 1.25rem; font-weight: 900; display: flex;
            align-items: center; justify-content: center; flex-shrink: 0; border: 2px solid var(--brand-light); object-fit: cover;
        }

        .scanned-user-info { flex: 1; min-width: 0; }
        .scanned-user-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; margin-bottom: 3px; }
        .scanned-user-name { font-size: 1.05rem; font-weight: 900; color: #fff; }
        .scanned-points-badge { background: var(--success); color: #fff; padding: 3px 10px; border-radius: var(--r-full); font-size: 0.8rem; font-weight: 900; }
        .scanned-status-msg { font-size: 0.82rem; font-weight: 700; color: #94a3b8; }

        .empty-state { text-align: center; padding: 24px; color: var(--text-3); font-size: 0.88rem; font-weight: 600; }
    </style>
</head>
<body>

    <div class="container">
        <!-- Top Navbar -->
        <header class="topbar">
            <a href="#" id="adminBrandLink" class="brand">
                <img src="/assets/brethren-logo.png" id="adminLogoImg" class="brand-logo-img" alt="Logo">
                <span>لوحة التحكم</span>
            </a>
            <div class="topbar-actions">
                <a href="#" id="myProfileLink" class="portal-link" title="ملفي والـ QR">
                    <i class="fas fa-user-circle"></i>
                    <span class="btn-text">ملفي</span>
                </a>
                <button onclick="handleAdminLogout()" class="logout-btn" title="تسجيل الخروج">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="btn-text">خروج</span>
                </button>
            </div>
        </header>

        <!-- Navigation Tabs -->
        <nav class="nav-tabs">
            <button class="tab-btn active" onclick="switchTab('tabUsers')">
                <i class="fas fa-users"></i> قائمة الأعضاء
            </button>
            <button class="tab-btn" onclick="switchTab('tabEvents')">
                <i class="fas fa-calendar-star"></i> الفعاليات وQR
            </button>
            <button class="tab-btn" onclick="switchTab('tabPoints')">
                <i class="fas fa-coins"></i> النقاط والـ QR
            </button>
        </nav>

        <!-- TAB 1: USERS MANAGEMENT (LIST VIEW) -->
        <div class="tab-content active" id="tabUsers">
            <div class="panel-card">
                <div class="card-header-bar">
                    <div class="card-title">
                        <i class="fas fa-users"></i> قائمة الأعضاء والمستخدمين
                    </div>
                    <button class="btn-add-action" onclick="openAddUserModal()" title="إضافة مستخدم جديد">
                        <i class="fas fa-user-plus"></i><span class="btn-text"> إضافة جديد</span>
                    </button>
                </div>

                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="adminUserSearch" placeholder="بحث ذكي عن مستخدم بالاسم أو البريد أو الهاتف..." oninput="renderUsersList()">
                </div>

                <div class="user-list-container" id="usersListGrid">
                    <div class="empty-state">جاري التحميل...</div>
                </div>
            </div>
        </div>

        <!-- TAB 2: EVENTS & SCANNER -->
        <div class="tab-content" id="tabEvents">
            <div class="panel-card">
                <div class="card-header-bar">
                    <div class="card-title">
                        <i class="fas fa-calendar-day"></i> الفعاليات المتاحة
                    </div>
                    <button class="btn-add-action" onclick="openAddEventModal()" title="إضافة فعالية جديدة">
                        <i class="fas fa-calendar-plus"></i><span class="btn-text"> إضافة فعالية</span>
                    </button>
                </div>

                <div class="events-list-grid" id="adminEventsGrid">
                    <div class="empty-state">جاري التحميل...</div>
                </div>
            </div>
        </div>

        <!-- TAB 3: POINTS MANAGEMENT VIA QR SCANNER -->
        <div class="tab-content" id="tabPoints">
            <div class="panel-card">
                <div class="points-panel-header">
                    <div class="card-title">
                        <i class="fas fa-coins" style="color:var(--warning-dark);"></i> إعطاء / خصم نقاط بالـ QR
                    </div>
                    <button class="gear-btn" onclick="openSettingsModal()" title="إعدادات النقاط والإيميل">
                        <i class="fas fa-cog"></i>
                    </button>
                </div>

                <!-- Add vs Deduct Toggle -->
                <div class="mode-toggle">
                    <button class="mode-btn active add" id="modeAddBtn" onclick="setPointsMode('add')">
                        <i class="fas fa-plus-circle"></i> إضافة نقاط (+)
                    </button>
                    <button class="mode-btn deduct" id="modeDeductBtn" onclick="setPointsMode('deduct')">
                        <i class="fas fa-minus-circle"></i> خصم نقاط (-)
                    </button>
                </div>

                <!-- Points Value Mode Switch (Shortcut Chips Tab vs Custom Points Tab) -->
                <label class="form-label" style="margin-bottom:6px;">طريقة تحديد كمية النقاط</label>
                <div class="mode-toggle">
                    <button type="button" class="mode-btn active" id="ptsValShortcutTab" onclick="switchPointsValueMode('shortcuts')">
                        <i class="fas fa-th-large"></i> اختصارات سريعة
                    </button>
                    <button type="button" class="mode-btn" id="ptsValCustomTab" onclick="switchPointsValueMode('custom')">
                        <i class="fas fa-pen"></i> عدد نقاط مخصص
                    </button>
                </div>

                <!-- Shortcuts Chips View -->
                <div id="shortcutsContainer">
                    <div class="shortcuts-grid" id="shortcutsGrid"></div>
                </div>

                <!-- Custom Points Input View (Separate Tab View) -->
                <div id="customPointsContainer" class="form-group" style="display:none;">
                    <label class="form-label">ادخل عدد النقاط المخصص</label>
                    <input type="number" id="customPointsInput" class="form-input" placeholder="مثال: 25">
                </div>

                <!-- Reasons Chips Section -->
                <div class="form-group">
                    <label class="form-label">سبب إعطاء/خصم النقاط</label>
                    <div class="reasons-chips-grid" id="reasonsChipsGrid"></div>
                </div>
                <div class="form-group" id="reasonOtherGroup" style="display:none;">
                    <label class="form-label">اكتب السبب المخصص</label>
                    <input type="text" id="reasonOtherInput" class="form-input" placeholder="اكتب السبب هنا...">
                </div>

                <button class="btn-primary" style="padding:14px;font-size:1.05rem;margin-bottom:16px;" onclick="openPointsQrScanner()">
                    <i class="fas fa-qrcode" style="font-size:1.2rem;"></i> مسح كود الـ QR لإضافة النقاط
                </button>

                <div style="border-top:1px dashed var(--border-solid);padding-top:14px;margin-top:14px;">
                    <label class="form-label">أو اختر مستخدم يدوياً بالاسم (اختياري)</label>
                    <select id="pointsTargetUserSelect" class="form-select" style="margin-bottom:10px;"></select>
                    <button class="action-btn btn-add-pt" style="width:100%;padding:10px;font-size:0.88rem;" onclick="submitPointsUpdateManual()">
                        <i class="fas fa-save"></i> تطبيق النقاط يدوياً للمستخدم المحدد
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- FULL SCREEN QR SCANNER PAGE VIEW -->
    <div id="qrScannerScreen" class="fullscreen-page" style="display:none;">
        <div class="fullscreen-header">
            <button class="back-btn" onclick="closeScannerModal()">
                <i class="fas fa-arrow-right"></i>
                <span>رجوع</span>
            </button>
            <div class="fullscreen-title" id="scannerModalTitle">مسح كود الحضور QR</div>
            <div style="width:40px;"></div>
        </div>
        
        <div class="fullscreen-body">
            <!-- Camera Video Feed Container -->
            <div id="qrVideoContainer" class="fullscreen-video-wrap">
                <video id="qrVideo" playsinline></video>
                <div class="scan-target-box"></div>
            </div>

            <!-- Scanned User Card Underneath Video -->
            <div class="scan-result-card" id="scanFeedbackBanner">
                <div id="scanFeedbackAvatar" class="scanned-user-avatar"></div>
                <div class="scanned-user-info">
                    <div class="scanned-user-top">
                        <div id="scanFeedbackName" class="scanned-user-name"></div>
                        <span class="scanned-points-badge" id="scanFeedbackPointsBadge">+20 نقطة</span>
                    </div>
                    <div id="scanFeedbackMsg" class="scanned-status-msg"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- ADD / EDIT USER MODAL (Includes Bulk Add Option) -->
    <div class="modal-overlay" id="addUserModal">
        <div class="modal-card">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fas fa-user-plus" style="color:var(--brand);"></i>
                    <span id="addUserModalTitle">إضافة مستخدم جديد</span>
                </div>
                <button class="modal-close" onclick="closeModal('addUserModal')">&times;</button>
            </div>
            <div class="modal-body">
                <!-- Add User Mode Switch (Single vs Bulk) -->
                <div class="mode-toggle" id="addUserModeToggle">
                    <button type="button" class="mode-btn active" id="userAddSingleBtn" onclick="switchAddUserMode('single')">
                        <i class="fas fa-user"></i> إضافة فردية
                    </button>
                    <button type="button" class="mode-btn" id="userAddBulkBtn" onclick="switchAddUserMode('bulk')">
                        <i class="fas fa-file-csv"></i> إضافة جماعية (شيت)
                    </button>
                </div>

                <!-- Single User Form -->
                <form id="addUserForm" onsubmit="submitAddUser(event)">
                    <input type="hidden" id="userIdInput" value="0">
                    <div class="form-grid">
                        <div class="form-group">
                            <label class="form-label">الاسم بالكامل *</label>
                            <input type="text" id="userNameInput" class="form-input" required autocomplete="name" placeholder="الاسم بالكامل">
                        </div>
                        <div class="form-group">
                            <label class="form-label">البريد الإلكتروني</label>
                            <input type="email" id="userEmailInput" class="form-input" autocomplete="email" placeholder="name@domain.com">
                        </div>
                        <div class="form-group">
                            <label class="form-label">رقم الهاتف</label>
                            <input type="tel" id="userPhoneInput" class="form-input" autocomplete="tel" placeholder="012xxxxxxxx">
                        </div>
                        <div class="form-group">
                            <label class="form-label">كلمة المرور / الباسكود</label>
                            <input type="password" id="userPassInput" class="form-input" autocomplete="new-password" placeholder="كلمة المرور للدخول">
                        </div>
                        <div class="form-group">
                            <label class="form-label">المنطقة / السكن</label>
                            <input type="text" id="userLocationInput" class="form-input" placeholder="المنطقة أو العنوان">
                        </div>
                        <div class="form-group">
                            <label class="form-label">النوع</label>
                            <select id="userGenderInput" class="form-select">
                                <option value="ذكر">ذكر</option>
                                <option value="أنثى">أنثى</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">تاريخ الميلاد</label>
                            <input type="text" id="userBirthDateInput" class="form-input" placeholder="شهر/سنة أو يوم/شهر/سنة">
                        </div>
                        <div class="form-group">
                            <label class="form-label">الصلاحية</label>
                            <select id="userIsAdminInput" class="form-select">
                                <option value="0">عضو عادي</option>
                                <option value="1">مسؤول إداري (Admin)</option>
                            </select>
                        </div>
                    </div>

                    <div id="addUserCustomFields"></div>

                    <button type="submit" class="btn-primary" id="saveUserBtn">
                        <i class="fas fa-plus"></i> حفظ المستخدم
                    </button>
                </form>

                <!-- Bulk Import Form (Inside Modal) -->
                <div id="bulkUserForm" style="display:none;">
                    <p style="color:var(--text-2); font-size:0.82rem; margin-bottom:10px; line-height:1.4; font-weight:600;">
                        انسخ الجدول مباشرة من Google Sheets وانقله هنا.<br>
                        • الأعمدة الأساسية: (الاسم - البريد/الإيميل - الهاتف/الموبايل - السكن - النوع - تاريخ الميلاد).
                    </p>

                    <div class="form-group">
                        <label class="form-label">لصق الجدول المنسوخ (Tab Separated / CSV)</label>
                        <textarea id="bulkPasteInput" class="form-textarea bulk-textarea" placeholder="الاسم	البريد الإلكتروني	رقم الهاتف	المنطقة"></textarea>
                    </div>

                    <button type="button" class="btn-primary" onclick="processBulkImport()">
                        <i class="fas fa-cloud-upload-alt"></i> استيراد المستخدمين
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- USER INFO MODAL -->
    <div class="modal-overlay" id="userInfoModal">
        <div class="modal-card">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fas fa-id-card" style="color:var(--brand);"></i>
                    <span>بيانات العضو التفصيلية</span>
                </div>
                <button class="modal-close" onclick="closeModal('userInfoModal')">&times;</button>
            </div>
            <div class="modal-body" id="userInfoModalBody">
                <div class="empty-state">جاري التحميل...</div>
            </div>
        </div>
    </div>

    <!-- ADD EVENT MODAL -->
    <div class="modal-overlay" id="addEventModal">
        <div class="modal-card">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fas fa-calendar-plus" style="color:var(--brand);"></i>
                    <span>إنشاء فعالية جديدة</span>
                </div>
                <button class="modal-close" onclick="closeModal('addEventModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="createEventForm" onsubmit="submitCreateEvent(event)">
                    <div class="form-group">
                        <label class="form-label">اسم الفعالية *</label>
                        <input type="text" id="eventNameInput" class="form-input" required placeholder="مثال: اجتماع الأحد">
                    </div>
                    <div class="form-group">
                        <label class="form-label">تاريخ الفعالية</label>
                        <input type="date" id="eventDateInput" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">الوصف / ملاحظات</label>
                        <input type="text" id="eventDescInput" class="form-input" placeholder="وصف اختياري">
                    </div>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-check"></i> إنشاء الفعالية
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- SETTINGS MODAL -->
    <div class="modal-overlay" id="settingsModal">
        <div class="modal-card">
            <div class="modal-header">
                <div class="modal-title">
                    <i class="fas fa-sliders-h" style="color:var(--brand);"></i>
                    <span>إعدادات النظام وإشعارات البريد</span>
                </div>
                <button class="modal-close" onclick="closeModal('settingsModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form onsubmit="saveSettingsForm(event)">
                    <div class="form-group">
                        <label class="form-label">بريد الأدمن لاستقبال الإشعارات والتقارير</label>
                        <input type="email" id="settingAdminEmailInput" class="form-input" placeholder="admin@sunday-school.online">
                    </div>
                    <div class="form-group">
                        <label class="form-label">تعديل قيم الاختصارات السريعة (مفصولة بفواصل)</label>
                        <input type="text" id="settingShortcutsInput" class="form-input" placeholder="10, 30, 50, 100">
                    </div>
                    <div class="form-group">
                        <label style="display:flex; align-items:center; gap:10px; color:var(--text); cursor:pointer; font-weight:700;">
                            <input type="checkbox" id="settingEnableShortcut" style="width:18px;height:18px;">
                            <span>تفعيل زر الاختصارات السريعة</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label style="display:flex; align-items:center; gap:10px; color:var(--text); cursor:pointer; font-weight:700;">
                            <input type="checkbox" id="settingEnableCustom" style="width:18px;height:18px;">
                            <span>تفعيل إدخال قيمة النقاط المخصصة</span>
                        </label>
                    </div>
                    <div class="form-group">
                        <label class="form-label">أسباب إعطاء النقاط (كل سبب في سطر)</label>
                        <textarea id="settingReasonsInput" class="form-textarea" style="height:90px;" placeholder="ألعاب
بونص
التزام بالأوقات"></textarea>
                    </div>
                    <button type="submit" class="btn-primary">حفظ الإعدادات</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        const isBrethrenSubfolder = window.location.pathname.includes('/brethren');
        const API_URL = isBrethrenSubfolder ? '/brethren/api.php' : '/api.php';
        const USER_URL = isBrethrenSubfolder ? '/brethren/user/' : '/user/';
        const LOGIN_URL = isBrethrenSubfolder ? '/brethren/login/' : '/login/';
        const ADMIN_BRAND_URL = isBrethrenSubfolder ? '/brethren/admin/' : '/admin/';
        const LOGO_SRC = isBrethrenSubfolder ? '/brethren/assets/brethren-logo.png' : '/assets/brethren-logo.png';

        const isAdmin = localStorage.getItem('brethren_is_admin') === 'true';
        if (!isAdmin) window.location.href = LOGIN_URL;

        const activeUserId = localStorage.getItem('brethren_active_user_id');
        document.getElementById('myProfileLink').href = `${USER_URL}?id=${activeUserId || ''}`;
        document.getElementById('adminBrandLink').href = ADMIN_BRAND_URL;
        document.getElementById('adminLogoImg').src = LOGO_SRC;

        let usersList = [];
        let eventsList = [];
        let systemSettings = {
            shortcuts: [10, 30, 50, 100],
            enable_shortcut: true,
            enable_custom: true,
            reasons: ['ألعاب', 'بونص', 'التزام بالأوقات'],
            admin_email: 'admin@sunday-school.online'
        };

        let currentPointsMode = 'add';
        let currentPointsValueMode = 'shortcuts';
        let selectedShortcutPoints = 10;
        let selectedReasonText = 'ألعاب';
        let activeScannerTask = null;
        let qrScannerInstance = null;

        function playSuccessSound() {
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const playNote = (freq, startTime, duration) => {
                    const osc = audioCtx.createOscillator();
                    const gain = audioCtx.createGain();
                    osc.connect(gain); gain.connect(audioCtx.destination);
                    osc.type = 'sine'; osc.frequency.setValueAtTime(freq, startTime);
                    gain.gain.setValueAtTime(0.08, startTime);
                    gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);
                    osc.start(startTime); osc.stop(startTime + duration);
                };
                const now = audioCtx.currentTime;
                playNote(523.25, now, 0.12); playNote(659.25, now + 0.06, 0.12); playNote(783.99, now + 0.12, 0.24);
            } catch (e) {}
        }

        function playErrorSound() {
            try {
                const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
                const osc = audioCtx.createOscillator(); const gain = audioCtx.createGain();
                osc.connect(gain); gain.connect(audioCtx.destination);
                osc.type = 'sawtooth'; osc.frequency.setValueAtTime(150, audioCtx.currentTime);
                gain.gain.setValueAtTime(0.08, audioCtx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, audioCtx.currentTime + 0.3);
                osc.start(); osc.stop(audioCtx.currentTime + 0.3);
            } catch (e) {}
        }

        document.addEventListener('DOMContentLoaded', () => {
            fetchUsers(); fetchEvents(); fetchSettings();
            document.getElementById('eventDateInput').value = new Date().toISOString().split('T')[0];
        });

        function switchTab(tabId) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            event.currentTarget.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        }

        function switchAddUserMode(mode) {
            const singleForm = document.getElementById('addUserForm');
            const bulkForm = document.getElementById('bulkUserForm');
            const singleBtn = document.getElementById('userAddSingleBtn');
            const bulkBtn = document.getElementById('userAddBulkBtn');

            if (mode === 'bulk') {
                singleForm.style.display = 'none';
                bulkForm.style.display = 'block';
                singleBtn.classList.remove('active');
                bulkBtn.classList.add('active');
            } else {
                singleForm.style.display = 'block';
                bulkForm.style.display = 'none';
                singleBtn.classList.add('active');
                bulkBtn.classList.remove('active');
            }
        }

        function switchPointsValueMode(mode) {
            currentPointsValueMode = mode;
            const scContainer = document.getElementById('shortcutsContainer');
            const customContainer = document.getElementById('customPointsContainer');
            const scTab = document.getElementById('ptsValShortcutTab');
            const customTab = document.getElementById('ptsValCustomTab');

            if (mode === 'custom') {
                scContainer.style.display = 'none';
                customContainer.style.display = 'block';
                scTab.classList.remove('active');
                customTab.classList.add('active');
            } else {
                scContainer.style.display = 'block';
                customContainer.style.display = 'none';
                scTab.classList.add('active');
                customTab.classList.remove('active');
            }
        }

        async function fetchUsers() {
            try {
                const res = await fetch(`${API_URL}?action=get_users`);
                const data = await res.json();
                if (data.status === 'success') {
                    usersList = data.users || [];
                    renderUsersList();
                    populateUserSelects();
                }
            } catch (err) {}
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
                        { val: u.email, weight: 1.1 },
                        { val: u.phone, weight: 1.1 },
                        { val: u.location, weight: 0.8 }
                    ])
                })).filter(u => u._score > 0).sort((a, b) => b._score - a._score);
            }

            if (filtered.length === 0) {
                grid.innerHTML = `<div class="empty-state">لا يوجد مستخدمين متاحين</div>`;
                return;
            }

            grid.innerHTML = filtered.map(u => {
                const isFemale = u.gender === 'أنثى';
                return `
                <div class="user-list-row">
                    <div class="user-row-top-line">
                        <div class="user-row-main">
                            <div class="user-row-avatar ${isFemale ? 'female' : 'male'}">
                                ${u.photo ? `<img src="${u.photo}" style="width:100%;height:100%;border-radius:50%;object-fit:cover">` : u.name.charAt(0)}
                            </div>
                            <div class="user-row-details">
                                <div class="user-row-title">
                                    <span>${u.name}</span>
                                    ${u.is_admin == 1 ? '<span style="color:var(--brand);font-size:0.72rem;">(Admin)</span>' : ''}
                                </div>
                                <div class="user-row-sub">
                                    <span><i class="fas fa-envelope"></i> ${u.email || u.phone || 'بدون بيانات'}</span>
                                    ${u.location ? `<span>• <i class="fas fa-map-marker-alt"></i> ${u.location}</span>` : ''}
                                </div>
                            </div>
                        </div>
                        <span class="user-row-points"><i class="fas fa-star"></i> ${u.points} نقطة</span>
                    </div>

                    <div class="user-row-actions">
                        <button class="action-btn btn-info" onclick="openUserInfoModal(${u.id})" title="معلومات"><i class="fas fa-info-circle"></i><span class="btn-text"> معلومات</span></button>
                        <button class="action-btn btn-edit" onclick="openEditUserModal(${u.id})" title="تعديل"><i class="fas fa-edit"></i><span class="btn-text"> تعديل</span></button>
                        <button class="action-btn btn-add-pt" onclick="quickAddPointsUser(${u.id})" title="نقاط"><i class="fas fa-plus"></i><span class="btn-text"> نقاط</span></button>
                        <button class="action-btn btn-delete" onclick="deleteUserConfirm(${u.id})" title="حذف"><i class="fas fa-trash"></i><span class="btn-text"> حذف</span></button>
                    </div>
                </div>
            `}).join('');
        }

        function populateUserSelects() {
            const sel = document.getElementById('pointsTargetUserSelect');
            sel.innerHTML = `<option value="">-- اختر مستخدم --</option>` + 
                usersList.map(u => `<option value="${u.id}">${u.name} (${u.points} نقطة)</option>`).join('');
        }

        function openAddUserModal() {
            document.getElementById('addUserForm').reset();
            document.getElementById('userIdInput').value = 0;
            switchAddUserMode('single');
            document.getElementById('addUserModalTitle').innerText = 'إضافة مستخدم جديد';
            document.getElementById('saveUserBtn').innerHTML = `<i class="fas fa-plus"></i> حفظ المستخدم`;
            document.getElementById('addUserModal').classList.add('active');
        }

        function openEditUserModal(id) {
            const u = usersList.find(item => item.id == id);
            if (!u) return;

            switchAddUserMode('single');
            document.getElementById('userIdInput').value = u.id;
            document.getElementById('userNameInput').value = u.name;
            document.getElementById('userEmailInput').value = u.email || '';
            document.getElementById('userPhoneInput').value = u.phone || '';
            document.getElementById('userPassInput').value = u.passcode || '';
            document.getElementById('userLocationInput').value = u.location || '';
            document.getElementById('userGenderInput').value = u.gender || 'ذكر';
            document.getElementById('userBirthDateInput').value = u.birth_date || '';
            document.getElementById('userIsAdminInput').value = u.is_admin || 0;
            document.getElementById('addUserModalTitle').innerText = 'تعديل بيانات المستخدم';
            document.getElementById('saveUserBtn').innerHTML = `<i class="fas fa-save"></i> تحديث البيانات`;
            document.getElementById('addUserModal').classList.add('active');
        }

        async function openUserInfoModal(id) {
            const modalBody = document.getElementById('userInfoModalBody');
            modalBody.innerHTML = `<div class="empty-state">جاري تحميل البيانات...</div>`;
            document.getElementById('userInfoModal').classList.add('active');

            try {
                const res = await fetch(`${API_URL}?action=get_user&id=${id}`);
                const data = await res.json();
                if (data.status === 'success') {
                    const u = data.user;
                    const history = data.history || [];
                    const attended = data.attended_events || [];
                    const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=180x180&data=${encodeURIComponent(u.user_code)}`;

                    modalBody.innerHTML = `
                        <div class="info-detail-wrap">
                            <div class="info-detail-avatar">
                                ${u.photo ? `<img src="${u.photo}" style="width:100%;height:100%;border-radius:50%;object-fit:cover">` : u.name.charAt(0)}
                            </div>
                            <div class="info-detail-name">${u.name}</div>
                            <div class="info-detail-code">الكود: ${u.user_code}</div>
                        </div>

                        <div class="qr-preview-box">
                            <img src="${qrUrl}" alt="QR Code">
                            <div style="font-size:0.8rem;font-weight:700;color:var(--text-3);margin-top:6px;">كود الـ QR الخاص بالحضور</div>
                        </div>

                        <div class="info-rows-grid">
                            <div class="info-row-item"><i class="fas fa-envelope"></i> <span>${u.email || 'بدون بريد'}</span></div>
                            <div class="info-row-item"><i class="fas fa-phone"></i> <span>${u.phone || 'بدون هاتف'}</span></div>
                            <div class="info-row-item"><i class="fas fa-map-marker-alt"></i> <span>${u.location || 'غير محدد'}</span></div>
                            <div class="info-row-item"><i class="fas fa-venus-mars"></i> <span>${u.gender || 'ذكر'}</span></div>
                            <div class="info-row-item"><i class="fas fa-birthday-cake"></i> <span>${u.birth_date || 'غير محدد'}</span></div>
                            <div class="info-row-item"><i class="fas fa-coins"></i> <span>رصيد النقاط: ${u.points} نقطة</span></div>
                        </div>

                        ${Object.keys(u.custom_fields || {}).length > 0 ? `
                            <div style="font-weight:800;font-size:0.9rem;margin-bottom:8px;color:var(--text);">بيانات مخصصة:</div>
                            <div class="info-rows-grid">
                                ${Object.entries(u.custom_fields).map(([k, v]) => `
                                    <div class="info-row-item"><i class="fas fa-info-circle"></i> <span><strong>${k}:</strong> ${v}</span></div>
                                `).join('')}
                            </div>
                        ` : ''}

                        <div style="font-weight:800;font-size:0.9rem;margin:14px 0 8px;color:var(--text);">سجل النقاط والفعالية:</div>
                        <div style="max-height:160px;overflow-y:auto;background:var(--surface-2);border-radius:var(--r-md);padding:10px;border:1px solid var(--border-solid);">
                            ${history.length === 0 ? '<div style="font-size:0.82rem;color:var(--text-3);">لا يوجد سجل نقاط حتى الآن</div>' : 
                                history.map(h => `
                                    <div style="font-size:0.8rem;padding:6px 0;border-bottom:1px dashed var(--border-solid);display:flex;justify-content:space-between;">
                                        <span>${h.reason}</span>
                                        <strong style="color:${h.points_change > 0 ? 'var(--success)' : 'var(--danger)'}">
                                            ${h.points_change > 0 ? '+' : ''}${h.points_change} نقطة
                                        </strong>
                                    </div>
                                `).join('')}
                        </div>
                    `;
                } else { modalBody.innerHTML = `<div class="empty-state">تعذر تحميل بيانات المستخدم</div>`; }
            } catch (err) { modalBody.innerHTML = `<div class="empty-state">حدث خطأ أثناء تحميل البيانات</div>`; }
        }

        async function submitAddUser(e) {
            e.preventDefault();
            const id = document.getElementById('userIdInput').value;
            const payload = {
                action: 'save_user',
                id: id,
                name: document.getElementById('userNameInput').value.trim(),
                email: document.getElementById('userEmailInput').value.trim(),
                phone: document.getElementById('userPhoneInput').value.trim(),
                passcode: document.getElementById('userPassInput').value.trim(),
                location: document.getElementById('userLocationInput').value.trim(),
                gender: document.getElementById('userGenderInput').value || 'ذكر',
                birth_date: document.getElementById('userBirthDateInput').value.trim(),
                is_admin: parseInt(document.getElementById('userIsAdminInput').value, 10)
            };

            try {
                const res = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.status === 'success') {
                    closeModal('addUserModal');
                    fetchUsers();
                } else alert(data.message);
            } catch (err) { alert('تعذر الاتصال بالخادم'); }
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
                if (data.status === 'success') fetchUsers();
                else alert(data.message);
            } catch (err) { alert('تعذر الحذف'); }
        }

        async function processBulkImport() {
            const rawText = document.getElementById('bulkPasteInput').value.trim();
            if (!rawText) return alert('يرجى لصق بيانات الجدول أولاً');

            const lines = rawText.split(/\r?\n/).map(l => l.trim()).filter(l => l);
            if (lines.length < 2) return alert('يجب أن يحتوي النص الملصوق على صف عناوين وصفوف بيانات');

            const firstLine = lines[0];
            const delimiter = firstLine.includes('\t') ? '\t' : ',';
            const headers = firstLine.split(delimiter).map(h => h.trim().toLowerCase());
            
            const standardMap = {
                name: ['اسم', 'الاسم', 'name'],
                email: ['إيميل', 'ايميل', 'بريد', 'email', 'mail'],
                phone: ['تليفون', 'موبايل', 'رقم', 'هاتف', 'phone', 'number'],
                location: ['عنوان', 'منطقة', 'سكن', 'location', 'address'],
                gender: ['نوع', 'جنس', 'gender'],
                birth_date: ['تاريخ ميلاد', 'تاريخ الميلاد', 'ميلاد', 'birth_date', 'dob']
            };

            const headerKeys = headers.map(h => {
                for (const [stdKey, aliases] of Object.entries(standardMap)) {
                    if (aliases.some(a => h.includes(a))) return stdKey;
                }
                return h;
            });

            const parsedUsers = [];
            for (let i = 1; i < lines.length; i++) {
                const cells = lines[i].split(delimiter).map(c => c.trim());
                if (cells.length === 0 || !cells[0]) continue;

                const userObj = { custom_fields: {} };
                headers.forEach((h, colIdx) => {
                    const key = headerKeys[colIdx];
                    const val = cells[colIdx] || '';
                    if (['name', 'email', 'phone', 'location', 'gender', 'birth_date'].includes(key)) {
                        userObj[key] = val;
                    } else if (val) {
                        userObj.custom_fields[headers[colIdx]] = val;
                    }
                });

                if (userObj.name) parsedUsers.push(userObj);
            }

            if (parsedUsers.length === 0) return alert('لم يتم العثور على أسماء صالحة في البيانات');

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
                    closeModal('addUserModal');
                    fetchUsers();
                } else alert(data.message);
            } catch (err) { alert('تعذر استيراد البيانات'); }
        }

        async function fetchEvents() {
            try {
                const res = await fetch(`${API_URL}?action=get_events`);
                const data = await res.json();
                if (data.status === 'success') {
                    eventsList = data.events || [];
                    renderAdminEvents();
                }
            } catch (err) {}
        }

        function renderAdminEvents() {
            const grid = document.getElementById('adminEventsGrid');
            if (eventsList.length === 0) {
                grid.innerHTML = `<div class="empty-state">لا توجد فعاليات مضافة بعد</div>`;
                return;
            }

            grid.innerHTML = eventsList.map(ev => `
                <div class="event-admin-card" onclick="openScannerModal('attendance', { eventId: ${ev.id}, name: '${ev.event_name.replace(/'/g, "\\'")}' })">
                    <div>
                        <div class="event-admin-header">
                            <div class="event-admin-title">${ev.event_name}</div>
                            <span class="event-attendance-badge"><i class="fas fa-users"></i> ${ev.attendance_count} حاضر</span>
                        </div>
                        <div class="event-date-text"><i class="far fa-calendar"></i> ${ev.event_date}</div>
                        ${ev.description ? `<p style="font-size:0.82rem;color:var(--text-2);margin-bottom:8px;">${ev.description}</p>` : ''}
                    </div>
                    <div class="event-click-hint">
                        <i class="fas fa-qrcode"></i> اضغط لمسح الحضور QR
                    </div>
                </div>
            `).join('');
        }

        function openAddEventModal() {
            document.getElementById('createEventForm').reset();
            document.getElementById('eventDateInput').value = new Date().toISOString().split('T')[0];
            document.getElementById('addEventModal').classList.add('active');
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
                    closeModal('addEventModal');
                    fetchEvents();
                } else alert(data.message);
            } catch (err) { alert('تعذر إنشاء الفعالية'); }
        }

        function getActivePointsAmount() {
            if (currentPointsValueMode === 'custom') {
                return parseInt(document.getElementById('customPointsInput').value, 10);
            }
            return selectedShortcutPoints;
        }

        function getActivePointsReason() {
            if (selectedReasonText === 'أخرى') {
                return document.getElementById('reasonOtherInput').value.trim() || 'أخرى';
            }
            return selectedReasonText;
        }

        function openPointsQrScanner() {
            let amount = getActivePointsAmount();
            if (isNaN(amount) || amount <= 0) return alert('يرجى اختيار أو إدخال قيمة النقاط أولاً');
            if (currentPointsMode === 'deduct') amount = -amount;

            let reason = getActivePointsReason();
            openScannerModal('points', { amount: amount, reason: reason });
        }

        function openScannerModal(type, data) {
            activeScannerTask = { type: type, ...data };
            const titleElem = document.getElementById('scannerModalTitle');

            if (type === 'attendance') {
                titleElem.innerText = `مسح حضور QR: ${data.name}`;
            } else {
                titleElem.innerText = `مسح QR لإضافة نقاط (${data.amount > 0 ? '+' : ''}${data.amount} نقطة - ${data.reason})`;
            }

            document.getElementById('scanFeedbackBanner').style.display = 'none';
            document.getElementById('qrScannerScreen').style.display = 'flex';

            const videoElem = document.getElementById('qrVideo');
            if (typeof QrScanner !== 'undefined') {
                qrScannerInstance = new QrScanner(videoElem, result => onQrCodeScanned(result.data || result), {
                    highlightScanRegion: true, highlightCodeOutline: true
                });
                qrScannerInstance.start().catch(err => alert('تعذر فتح الكاميرا'));
            }
        }

        let isScanningLock = false;
        async function onQrCodeScanned(userCode) {
            if (isScanningLock || !activeScannerTask) return;
            isScanningLock = true;

            const banner = document.getElementById('scanFeedbackBanner');
            const avatar = document.getElementById('scanFeedbackAvatar');
            const nameElem = document.getElementById('scanFeedbackName');
            const msgElem = document.getElementById('scanFeedbackMsg');
            const pointsBadge = document.getElementById('scanFeedbackPointsBadge');

            try {
                if (activeScannerTask.type === 'attendance') {
                    const res = await fetch(API_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'scan_attendance', event_id: activeScannerTask.eventId, user_code: userCode })
                    });
                    const data = await res.json();

                    if (data.status === 'success') {
                        playSuccessSound();
                        avatar.innerHTML = data.user.photo ? `<img src="${data.user.photo}" style="width:100%;height:100%;border-radius:50%;object-fit:cover">` : data.user.name.charAt(0);
                        nameElem.innerText = data.user.name;
                        pointsBadge.innerText = `+20 نقطة`;
                        msgElem.innerText = `✓ تم تسجيل الحضور بنجاح وأضيفت 20+ نقطة!`;
                        banner.style.display = 'flex';
                        fetchEvents(); fetchUsers();
                    } else if (data.status === 'already_attended') {
                        playErrorSound();
                        avatar.innerHTML = (data.user && data.user.photo) ? `<img src="${data.user.photo}" style="width:100%;height:100%;border-radius:50%;object-fit:cover">` : (data.user ? data.user.name.charAt(0) : '?');
                        nameElem.innerText = data.user ? data.user.name : 'مستخدم';
                        pointsBadge.innerText = `تم الحضور سابقاً`;
                        msgElem.innerText = `⚠ تم تسجيل الحضور لهذا العضو سابقاً!`;
                        banner.style.display = 'flex';
                    } else {
                        playErrorSound();
                        avatar.innerText = '!'; nameElem.innerText = 'خطأ';
                        pointsBadge.innerText = '!';
                        msgElem.innerText = data.message || 'كود غير صالح';
                        banner.style.display = 'flex';
                    }
                } else if (activeScannerTask.type === 'points') {
                    const res = await fetch(API_URL, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ action: 'update_points_by_code', user_code: userCode, points_change: activeScannerTask.amount, reason: activeScannerTask.reason, type: 'manual' })
                    });
                    const data = await res.json();

                    if (data.status === 'success') {
                        playSuccessSound();
                        avatar.innerHTML = data.user.photo ? `<img src="${data.user.photo}" style="width:100%;height:100%;border-radius:50%;object-fit:cover">` : data.user.name.charAt(0);
                        nameElem.innerText = data.user.name;
                        pointsBadge.innerText = `${activeScannerTask.amount > 0 ? '+' : ''}${activeScannerTask.amount} نقطة`;
                        msgElem.innerText = `✓ تم تحديث رصيد ${data.user.name} بنجاح (${activeScannerTask.reason})!`;
                        banner.style.display = 'flex';
                        fetchUsers();
                    } else {
                        playErrorSound();
                        avatar.innerText = '!'; nameElem.innerText = 'خطأ';
                        pointsBadge.innerText = '!';
                        msgElem.innerText = data.message || 'تعذر إضافة النقاط';
                        banner.style.display = 'flex';
                    }
                }
            } catch (err) { playErrorSound(); }
            finally { setTimeout(() => { isScanningLock = false; }, 2000); }
        }

        function closeScannerModal() {
            if (qrScannerInstance) { qrScannerInstance.stop(); qrScannerInstance.destroy(); qrScannerInstance = null; }
            document.getElementById('qrScannerScreen').style.display = 'none';
        }

        async function fetchSettings() {
            try {
                const res = await fetch(`${API_URL}?action=get_settings`);
                const data = await res.json();
                if (data.status === 'success') {
                    systemSettings = data.settings || systemSettings;
                    renderPointsUI();
                }
            } catch (err) { renderPointsUI(); }
        }

        function renderPointsUI() {
            const scContainer = document.getElementById('shortcutsContainer');
            const scGrid = document.getElementById('shortcutsGrid');
            if (systemSettings.enable_shortcut && Array.isArray(systemSettings.shortcuts)) {
                scGrid.innerHTML = systemSettings.shortcuts.map((pts, idx) => `
                    <div class="shortcut-chip ${idx === 0 ? 'selected' : ''}" onclick="selectShortcutChip(${pts}, this)">
                        ${currentPointsMode === 'add' ? '+' : '-'}${pts}
                    </div>
                `).join('');
                if (systemSettings.shortcuts.length > 0) selectedShortcutPoints = systemSettings.shortcuts[0];
            }

            renderReasonChips();
        }

        function selectShortcutChip(val, elem) {
            selectedShortcutPoints = val;
            document.querySelectorAll('.shortcut-chip').forEach(c => c.classList.remove('selected'));
            if (elem) elem.classList.add('selected');
        }

        function renderReasonChips() {
            const grid = document.getElementById('reasonsChipsGrid');
            const reasonsArr = Array.isArray(systemSettings.reasons) ? [...systemSettings.reasons, 'أخرى'] : ['ألعاب', 'بونص', 'التزام بالأوقات', 'أخرى'];
            
            if (!selectedReasonText && reasonsArr.length > 0) selectedReasonText = reasonsArr[0];

            grid.innerHTML = reasonsArr.map(r => `
                <div class="reason-chip ${selectedReasonText === r ? 'selected' : ''}" onclick="selectReasonChip('${r.replace(/'/g, "\\'")}', this)">
                    <i class="fas ${r === 'أخرى' ? 'fa-pen' : 'fa-tag'}"></i>
                    <span>${r}</span>
                </div>
            `).join('');

            toggleReasonOtherInput();
        }

        function selectReasonChip(reasonVal, elem) {
            selectedReasonText = reasonVal;
            document.querySelectorAll('.reason-chip').forEach(c => c.classList.remove('selected'));
            if (elem) elem.classList.add('selected');
            toggleReasonOtherInput();
        }

        function setPointsMode(mode) {
            currentPointsMode = mode;
            document.getElementById('modeAddBtn').className = `mode-btn ${mode === 'add' ? 'active add' : ''}`;
            document.getElementById('modeDeductBtn').className = `mode-btn ${mode === 'deduct' ? 'active deduct' : ''}`;
            renderPointsUI();
        }

        function toggleReasonOtherInput() {
            document.getElementById('reasonOtherGroup').style.display = (selectedReasonText === 'أخرى') ? 'block' : 'none';
        }

        function quickAddPointsUser(userId) {
            switchTab('tabPoints');
            document.getElementById('pointsTargetUserSelect').value = userId;
        }

        async function submitPointsUpdateManual() {
            const userId = document.getElementById('pointsTargetUserSelect').value;
            let amount = getActivePointsAmount();
            if (!userId) return alert('يرجى اختيار المستخدم المستهدف أولاً');
            if (isNaN(amount) || amount <= 0) return alert('يرجى إدخال عدد نقاط صحيح');

            if (currentPointsMode === 'deduct') amount = -amount;
            let reason = getActivePointsReason();

            try {
                const res = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_points', user_id: userId, points_change: amount, reason: reason, type: 'manual' })
                });
                const data = await res.json();
                if (data.status === 'success') {
                    alert(data.message);
                    fetchUsers();
                } else alert(data.message);
            } catch (err) { alert('تعذر تحديث النقاط'); }
        }

        function openSettingsModal() {
            document.getElementById('settingAdminEmailInput').value = systemSettings.admin_email || 'admin@sunday-school.online';
            document.getElementById('settingShortcutsInput').value = (systemSettings.shortcuts || [10, 30, 50, 100]).join(', ');
            document.getElementById('settingEnableShortcut').checked = systemSettings.enable_shortcut !== false;
            document.getElementById('settingEnableCustom').checked = systemSettings.enable_custom !== false;
            document.getElementById('settingReasonsInput').value = (systemSettings.reasons || []).join('\n');
            document.getElementById('settingsModal').classList.add('active');
        }

        async function saveSettingsForm(e) {
            e.preventDefault();
            const adminEmail = document.getElementById('settingAdminEmailInput').value.trim();
            const shortcutsRaw = document.getElementById('settingShortcutsInput').value;
            const shortcuts = shortcutsRaw.split(',').map(s => parseInt(s.trim(), 10)).filter(n => !isNaN(n));
            const enableShortcut = document.getElementById('settingEnableShortcut').checked;
            const enableCustom = document.getElementById('settingEnableCustom').checked;
            const reasonsRaw = document.getElementById('settingReasonsInput').value;
            const reasons = reasonsRaw.split(/\r?\n/).map(r => r.trim()).filter(r => r);

            const payload = {
                action: 'save_settings',
                admin_email: adminEmail,
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
                    closeModal('settingsModal'); fetchSettings(); alert('تم حفظ الإعدادات بنجاح');
                } else alert(data.message);
            } catch (err) { alert('تعذر حفظ الإعدادات'); }
        }

        function handleAdminLogout() {
            localStorage.removeItem('brethren_is_admin');
            localStorage.removeItem('brethren_active_user_id');
            window.location.href = LOGIN_URL;
        }

        function closeModal(id) { document.getElementById(id).classList.remove('active'); }
    </script>
</body>
</html>
