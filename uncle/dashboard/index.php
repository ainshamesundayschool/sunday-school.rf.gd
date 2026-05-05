<?php
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);
ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 365 * 10);
$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443) ||
    ((isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
);

session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 365 * 10,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

$hasChurchId = isset($_SESSION['church_id']);
$hasUncleId  = isset($_SESSION['uncle_id']);
$hasSession  = $hasChurchId || $hasUncleId;
$isLoginPage = strpos($_SERVER['REQUEST_URI'], '/login') !== false;

if (!$hasSession && !$isLoginPage) { ?>
<script>
// ── SESSION GUARD ──────────────────────────────────────────────
// Runs ONCE per tab. If PHP session is missing but localStorage says we're
// logged in, silently restore the session then reload. Only clears credentials
// if the server explicitly says the user doesn't exist. Never redirects offline.
(function(){
    var KEY = '_ss_restoring';

    // Already reloaded once after restore — don't loop
    if (sessionStorage.getItem(KEY) === '1') {
        sessionStorage.removeItem(KEY);
        return;
    }

    var cl = localStorage.getItem('loggedIn')     === 'true';
    var ul = localStorage.getItem('uncleLoggedIn') === 'true';
    var cc = localStorage.getItem('churchCode');
    var un = localStorage.getItem('uncleUsername');

    // No credentials at all → go to login
    if (!cl && !ul) { window.location.href = '/login/'; return; }

    // Build restore request
    var fd = new FormData();
    fd.append('action', 'restore_session');
    if      (cl && cc) fd.append('church_code', cc);
    else if (ul && un) fd.append('username', un);
    else { window.location.href = '/login/'; return; }

    // Mark that we're attempting a restore so the reload won't loop
    sessionStorage.setItem(KEY, '1');

    fetch('/api.php', { method:'POST', body:fd, credentials:'include' })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.success) {
                // Store churchType BEFORE reload so PHP _syncSessionToStorage
                // can detect account-type changes and clear stale caches.
                if (d.church_type) {
                    try { localStorage.setItem('churchType', d.church_type); } catch(e) {}
                }
                if (d.church_name) {
                    try { localStorage.setItem('churchName', d.church_name); } catch(e) {}
                }
                if (d.uncle_name) {
                    try { localStorage.setItem('uncleName', d.uncle_name); } catch(e) {}
                }
                // Session cookie is now set — plain reload, no query string
                window.location.reload();
            } else if (navigator.onLine && d.message && (d.message.indexOf('not found') !== -1 || d.message.indexOf('No credentials') !== -1)) {
                // Server confirmed user/church does not exist → truly invalid, clear and redirect
                sessionStorage.removeItem(KEY);
                ['loggedIn','uncleLoggedIn','churchCode','uncleUsername','churchName','uncleName']
                    .forEach(function(k){ localStorage.removeItem(k); });
                window.location.href = '/login/';
            } else {
                // Any other failure (DB error, network hiccup, etc.) — stay on page
                // Don't clear credentials — let the app work with cached data offline
                sessionStorage.removeItem(KEY);
            }
        })
        .catch(function(){
            // Network error — stay on page, don't wipe credentials
            sessionStorage.removeItem(KEY);
        });
})();
</script>
<?php }

if ($isLoginPage && $hasSession) {
    if ($hasChurchId) { header("Location: /uncle/church/dashboard/"); exit(); }
    elseif ($hasUncleId) {
        $role = $_SESSION['uncle_role'] ?? 'uncle';
        header("Location: " . ($role === 'developer' ? "/uncle/dev/" : "/uncle/dashboard/")); exit();
    }
}

$churchName = $_SESSION['church_name'] ?? 'الكنيسة';
$churchCode = $_SESSION['church_code'] ?? '';
$uncleName  = $_SESSION['uncle_name']  ?? '';
$uncleRole  = $_SESSION['uncle_role']  ?? '';
$churchType = $_SESSION['church_type'] ?? 'kids'; // 'kids' or 'youth'

$showSettings = $hasChurchId || ($hasUncleId && in_array($uncleRole, ['developer','admin']));
if ($hasUncleId && $uncleRole === 'uncle') $showSettings = false;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl" data-theme="light">
<head>
<meta name="facebook-domain-verification" content="fxb1yj847dop22gkxax1i02jkz7w98"/>

<!-- ═══ INSTANT THEME BOOTSTRAP — must be first script in <head> ═══
     Reads churchType from localStorage before PHP even renders so
     youth accounts get the purple palette with ZERO flash, even on
     account switch. PHP will confirm/overwrite on DOMContentLoaded. -->
<script>
(function(){
    // Apply dark/light theme instantly (existing behaviour, kept here for clarity)
    var t = localStorage.getItem('app_theme') || 'light';
    document.documentElement.setAttribute('data-theme', t);

    // PHP is the authoritative source for church type — read it first.
    var phpType = <?php echo json_encode($churchType); ?>;

    // Apply youth colour palette instantly.
    // If PHP says youth → inject now (zero flash).
    // If PHP says kids  → make sure no stale youth style from a previous
    //                     account lingers (remove any boot style we added).
    if (phpType === 'youth') {
        var s = document.createElement('style');
        s.id = 'youth-theme-boot';
        s.textContent = ':root{--brand:#7c3aed!important;--brand-dark:#5b21b6!important;--brand-light:#c4b5fd!important;--brand-bg:#ede9fe!important;--brand-glow:rgba(124,58,237,.18)!important;}';
        document.head.appendChild(s);
    }
    // Persist PHP's value so the next page load can use it from localStorage
    // (useful when session-guard fires before PHP has a session).
    try { localStorage.setItem('churchType', phpType); } catch(e) {}
})();
</script>

<?php
$ogTitle       = ($churchType === 'youth') ? 'خدمة الشباب' : 'نظام مدارس الأحد';
$ogDescription = ($churchType === 'youth')
    ? 'منصة متكاملة لإدارة خدمة الشباب — الحضور، الكوبونات، الإعلانات والمزيد'
    : 'منصة متكاملة لإدارة مدارس الأحد — الحضور، الكوبونات، الرحلات والمزيد';
$ogImage       = 'https://sunday-school.rf.gd/imgs/Thumbnail-300x.png';
$ogUrl         = 'https://sunday-school.rf.gd/';
?>

<!-- ── Font-ready guard: hide body until Cairo is loaded (or 300ms max).
     This eliminates the flash entirely on first load. After 300ms we
     show regardless so slow connections are never stuck. ── -->
<style id="font-guard">body{visibility:hidden}</style>
<script>
(function(){
    var show = function(){ 
        var g = document.getElementById('font-guard');
        if (g) g.remove();
    };
    // Use FontFace API if available
    if (document.fonts && document.fonts.ready) {
        // Race: fonts ready OR 300ms, whichever is first
        var timer = setTimeout(show, 300);
        document.fonts.ready.then(function(){
            clearTimeout(timer);
            show();
        });
    } else {
        // Fallback: just wait one frame so the theme bootstrap has run
        setTimeout(show, 50);
    }
})();
</script>
<meta property="og:type"        content="website">
<meta property="og:site_name"   content="<?php echo htmlspecialchars($ogTitle); ?>">
<meta property="og:title"       content="<?php echo htmlspecialchars($ogTitle); ?>">
<meta property="og:description" content="<?php echo htmlspecialchars($ogDescription); ?>">
<meta property="og:image"       content="<?php echo $ogImage; ?>">
<meta property="og:image:width"  content="300">
<meta property="og:image:height" content="300">
<meta property="og:image:type"   content="image/png">
<meta property="og:url"         content="<?php echo $ogUrl; ?>">
<meta property="og:locale"      content="ar_AR">

<!-- ═══ Twitter / X Card ═══ -->
<meta name="twitter:card"        content="summary">
<meta name="twitter:title"       content="<?php echo htmlspecialchars($ogTitle); ?>">
<meta name="twitter:description" content="<?php echo htmlspecialchars($ogDescription); ?>">
<meta name="twitter:image"       content="<?php echo $ogImage; ?>">

<!-- ═══ Standard fallback description ═══ -->
<meta name="description" content="<?php echo htmlspecialchars($ogDescription); ?>">

<title>Sunday School — <?php echo htmlspecialchars($churchName); ?></title>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
<meta name="theme-color" content="#5b6cf5">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="<?php echo $churchType === 'youth' ? 'الشباب' : 'مدارس الأحد'; ?>">
<meta name="mobile-web-app-capable" content="yes">
<script>
// ── Church Type Vocabulary Engine ─────────────────────────────
window.CHURCH_TYPE = <?php echo json_encode($churchType); ?>;
window.IS_YOUTH    = (window.CHURCH_TYPE === 'youth');

window.VOCAB_MAP = window.IS_YOUTH ? [
    // ── Longest / most-specific phrases first ──────────────────
    ['مدارس الأحد',         'خدمة الشباب'],
    ['مدرسة الأحد',         'خدمة الشباب'],
    ['قائمة الأطفال',       'قائمة الشباب'],
    ['إجمالي الأطفال',      'إجمالي الشباب'],
    ['عدد الأطفال',         'عدد الشباب'],
    ['جميع الأطفال',        'جميع الشباب'],
    ['للأطفال',             'للشباب'],
    ['بالأطفال',            'بالشباب'],
    // ── Person terms ───────────────────────────────────────────
    ['الأطفال',             'الشباب'],
    ['أطفال',               'شباب'],
    ['الطفل',               'الشاب'],
    ['طفلاً',               'شاباً'],
    ['طفل',                 'شاب'],
    // ── Student labels ─────────────────────────────────────────
    ['الطلاب',              'الأعضاء'],
    ['طلاب',                'أعضاء'],
    ['الطالب',              'العضو'],
    ['طالباً',              'عضواً'],
    ['طالب',                'عضو'],
    // ── Coupon labels — keep كوبون as-is, just swap labels ─────
    ['كوبونات الحضور',      'كوبونات الحضور'],   // keep unchanged
    ['كوبونات الالتزام',    'كوبونات الالتزام'], // keep unchanged
    ['متوسط الكوبونات',     'متوسط الكوبونات'],  // keep unchanged
    // NOTE: فصل / الفصل intentionally NOT swapped (user request)
] : [];

window.applyYouthVocab = function() {
    if (!window.IS_YOUTH || !window.VOCAB_MAP.length) return;
    const map = window.VOCAB_MAP;
    function swapText(str) {
        for (const [from, to] of map) str = str.split(from).join(to);
        return str;
    }
    const walker = document.createTreeWalker(
        document.body, NodeFilter.SHOW_TEXT,
        { acceptNode: n => n.nodeValue.trim() ? NodeFilter.FILTER_ACCEPT : NodeFilter.FILTER_SKIP }
    );
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    nodes.forEach(n => { const s = swapText(n.nodeValue); if (s !== n.nodeValue) n.nodeValue = s; });
    document.querySelectorAll('[placeholder]').forEach(el => {
        const s = swapText(el.placeholder);
        if (s !== el.placeholder) el.placeholder = s;
    });
    if (document.title.includes('مدارس الأحد'))
        document.title = document.title.replace('مدارس الأحد', 'خدمة الشباب');
};

window.t = function(str) {
    if (!window.IS_YOUTH || !window.VOCAB_MAP.length) return str;
    for (const [from, to] of window.VOCAB_MAP) str = str.split(from).join(to);
    return str;
};
</script>
<link rel="icon" type="image/x-icon" href="/favicon.ico">
<link rel="apple-touch-icon" href="/logo.png">
<link rel="manifest" href="/manifest.webmanifest">

<!-- ── Preconnect to font origins so DNS+TLS is ready before CSS fires ── -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

<!-- ── Cairo font: load async so it never blocks rendering.
         font-display=swap in the Google URL means the browser uses the
         fallback immediately and swaps only once Cairo is ready.
         To kill the swap-flash entirely we override to `optional` in CSS
         below — this means: use Cairo if already cached, otherwise keep
         the fallback for THIS paint and cache for next load. ── -->
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=IBM+Plex+Mono:wght@400;600&display=optional" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=IBM+Plex+Mono:wght@400;600&display=optional"></noscript>

<!-- ── Font Awesome: async load so icons don't block first paint ── -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"></noscript>

<!-- ── Heavy tool scripts: defer so they never block layout ── -->
<script defer src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css" media="print" onload="this.media='all'">
<script defer src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<script defer src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script defer src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
<style>
/* ═══════════════════════════════════════════════════════════════
   DESIGN TOKENS  — Light & Dark
═══════════════════════════════════════════════════════════════ */

/* ── Kill font-swap flash ────────────────────────────────────────
   Override Google's font-display:swap → optional.
   optional = use Cairo only if already in the browser cache (zero
   layout shift). On first ever load the fallback is used for that
   paint; Cairo is cached silently and used from the next load on.
   The fallback stack below matches Cairo's metrics closely enough
   that no reflow is visible even on cold loads.
─────────────────────────────────────────────────────────────── */
@font-face {
  font-family: 'Cairo';
  font-display: optional;
}

:root {
  /* Brand */
  --brand:        #5b6cf5;
  --brand-dark:   #4354e8;
  --brand-light:  #a5b0ff;
  --brand-bg:     #eef0ff;
  --brand-glow:   rgba(91,108,245,.18);

  /* Semantic */
  --success:      #10b981;
  --success-dark: #059669;
  --success-bg:   #d1fae5;
  --danger:       #ef4444;
  --danger-dark:  #dc2626;
  --danger-bg:    #fee2e2;
  --warning:      #f59e0b;
  --warning-dark: #d97706;
  --warning-bg:   #fef3c7;
  --coupon:       #8b5cf6;
  --coupon-dark:  #7c3aed;
  --coupon-bg:    #ede9fe;
  --coupon-grad:  linear-gradient(135deg,#8b5cf6,#7c3aed);

  /* Surface (Light) */
  --bg:           #f3f4f9;
  --surface:      #ffffff;
  --surface-2:    #f7f8fc;
  --surface-3:    #eef0f8;
  --border:       rgba(91,108,245,.12);
  --border-solid: #e4e6f0;
  --text:         #1a1d2e;
  --text-2:       #4b5068;
  --text-3:       #8b90a8;
  --shadow-sm:    0 2px 8px -2px rgba(0,0,0,.07);
  --shadow-md:    0 8px 24px -4px rgba(0,0,0,.10);
  --shadow-lg:    0 20px 48px -8px rgba(0,0,0,.14);
  --shadow-xl:    0 32px 64px -12px rgba(0,0,0,.18);

  /* Radius */
  --r-xs: 6px; --r-sm: 10px; --r-md: 14px;
  --r-lg: 18px; --r-xl: 24px; --r-2xl: 32px;
  --r-full: 9999px;

  /* Motion */
  --ease: cubic-bezier(.4,0,.2,1);
  --spring: cubic-bezier(.16,1,.3,1);
  --t: .22s;
}

[data-theme="dark"] {
  --bg:           #0f1117;
  --surface:      #181b26;
  --surface-2:    #1e2132;
  --surface-3:    #252840;
  --border:       rgba(91,108,245,.18);
  --border-solid: #2a2d42;
  --text:         #e8eaf6;
  --text-2:       #9299be;
  --text-3:       #565c7a;
  --shadow-sm:    0 2px 8px -2px rgba(0,0,0,.4);
  --shadow-md:    0 8px 24px -4px rgba(0,0,0,.5);
  --shadow-lg:    0 20px 48px -8px rgba(0,0,0,.6);
  --shadow-xl:    0 32px 64px -12px rgba(0,0,0,.7);
  --brand-bg:     rgba(91,108,245,.15);
  --success-bg:   rgba(16,185,129,.15);
  --danger-bg:    rgba(239,68,68,.15);
  --warning-bg:   rgba(245,158,11,.15);
  --coupon-bg:    rgba(139,92,246,.15);
}

/* ═══════════════════════════════════════════════════════════════
   RESET & BASE
═══════════════════════════════════════════════════════════════ */
*{margin:0;padding:0;box-sizing:border-box}
/* Disable annoying text selection highlight across the whole app */
*{
  -webkit-user-select:none;
  -moz-user-select:none;
  -ms-user-select:none;
  user-select:none;
  -webkit-tap-highlight-color:transparent;
  -webkit-touch-callout:none;
}
/* Re-enable selection only where user needs to type or read data */
input,textarea,select,[contenteditable]{
  -webkit-user-select:text !important;
  user-select:text !important;
}
html{scroll-behavior:smooth;overflow-x:hidden}
/* Hide scrollbar on the page/body itself */
html::-webkit-scrollbar,body::-webkit-scrollbar{display:none}
html,body{scrollbar-width:none;-ms-overflow-style:none}
/* Default thin scrollbar for all other elements */
*::-webkit-scrollbar{width:4px;height:4px}
*::-webkit-scrollbar-thumb{background:var(--border-solid);border-radius:10px}
*::-webkit-scrollbar-track{background:transparent}

body {
  font-family: 'Cairo', 'Segoe UI', Tahoma, 'Arabic Typesetting', sans-serif;
  background: var(--bg);
  color: var(--text);
  line-height: 1.65;
  font-size: 14px;
  min-height: 100vh;
  transition: background var(--t) var(--ease), color var(--t) var(--ease);
}

/* Ambient mesh background */
body::before {
  content:'';
  position:fixed;top:0;left:0;width:100%;height:100%;
  background:
    radial-gradient(ellipse 80% 50% at 10% -10%, rgba(91,108,245,.07) 0%, transparent 60%),
    radial-gradient(ellipse 60% 40% at 90% 110%, rgba(139,92,246,.05) 0%, transparent 60%);
  pointer-events:none;z-index:0;
}
[data-theme="dark"] body::before {
  background:
    radial-gradient(ellipse 80% 50% at 10% -10%, rgba(91,108,245,.12) 0%, transparent 60%),
    radial-gradient(ellipse 60% 40% at 90% 110%, rgba(139,92,246,.08) 0%, transparent 60%);
}

/* ═══════════════════════════════════════════════════════════════
   TOPBAR
═══════════════════════════════════════════════════════════════ */
.topbar {
  position: sticky; top: 0; z-index: 300;
  background: var(--bg);
  backdrop-filter: blur(20px);
  -webkit-backdrop-filter: blur(20px);
  padding: 0 16px;
  height: 58px;
  display: flex; align-items: center; justify-content: space-between;
  gap: 12px;
  transition: background var(--t) var(--ease), border-color var(--t) var(--ease);
}

@keyframes gradShift{0%{background-position:0%}100%{background-position:200%}}

/* Combined class card */
.combined-class-card {
    background: linear-gradient(135deg, var(--brand-bg), rgba(91,108,245,.08)) !important;
    transition: transform var(--t) var(--ease), box-shadow var(--t) var(--ease);
}
.combined-class-card:hover {
    transform: translateY(-3px) scale(1.02);
    box-shadow: 0 8px 24px var(--brand-glow);
}

.topbar-brand {
  display:flex;align-items:center;gap:10px;text-decoration:none;flex-shrink:0;
}
.topbar-logo {
  width:36px;height:36px;border-radius:10px;overflow:hidden;
  box-shadow:var(--shadow-sm);flex-shrink:0;
  background:var(--brand-bg);display:flex;align-items:center;justify-content:center;
}
.topbar-logo img{width:100%;height:100%;object-fit:cover}
.topbar-logo i{color:var(--brand);font-size:1rem}
.topbar-title {
font-size: 0.95rem; font-weight: 800; color: var(--text);
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 140px;
    line-break: auto;
    line-height: 18px;
}
.topbar-subtitle{font-size:.62rem;color:var(--text-3);font-weight:500;margin-top:-2px;display:none}
@media(min-width:400px){.topbar-subtitle{display:block}}

.topbar-actions{display:flex;align-items:center;gap:6px;padding-left:4px}
.topbar-btn {
  height:38px; min-width:38px; padding:0 11px;
  border-radius:var(--r-md);
  display:flex;align-items:center;justify-content:center;gap:5px;
  background:var(--surface-3);
  border:1.5px solid var(--border-solid);
  color:var(--text-2);font-size:.88rem;cursor:pointer;
  transition:all var(--t) var(--ease);text-decoration:none;
  flex-shrink:0;
}
.topbar-btn:hover{background:var(--brand-bg);color:var(--brand);border-color:var(--brand);transform:translateY(-1px);box-shadow:0 3px 8px var(--brand-glow)}
.topbar-avatar-btn {
  height:40px; min-width:40px; padding:0;
  border-radius:50%; overflow:hidden;
  cursor:pointer;transition:all var(--t) var(--ease);
  background:var(--brand-bg);
  border:2.5px solid var(--brand);
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
  box-shadow:0 2px 10px var(--brand-glow);
}
.topbar-avatar-btn:hover{border-color:var(--brand-dark);transform:translateY(-1px) scale(1.06);box-shadow:0 4px 14px var(--brand-glow)}
.topbar-avatar-btn img{width:36px;height:36px;border-radius:50%;object-fit:cover;flex-shrink:0}
.topbar-avatar-btn i{color:var(--brand);font-size:1rem;flex-shrink:0}

/* ═══════════════════════════════════════════════════════════════
   PAGE WRAPPER
═══════════════════════════════════════════════════════════════ */
.page-wrap{position:relative;z-index:1;max-width:1440px;margin:0 auto;padding:16px 14px 10px}
@media(min-width:768px){.page-wrap{padding:20px 20px 10px}}

/* ═══════════════════════════════════════════════════════════════
   CLASSES VIEW — HERO + STATS + GRID
═══════════════════════════════════════════════════════════════ */
.hero-strip {
  display:flex;align-items:center;justify-content:space-between;
  gap:12px;flex-wrap:wrap;
  margin-bottom:18px;
}
.hero-greeting{font-size:1.2rem;font-weight:800;color:var(--text)}
.hero-greeting span{color:var(--brand)}
.hero-actions{display:flex;gap:8px;flex-wrap:wrap}

/* Stats cards */
.stats-row {
  display:grid;
  grid-template-columns:repeat(2,1fr);
  gap:10px;margin-bottom:18px;
}
@media(min-width:600px){.stats-row{grid-template-columns:repeat(4,1fr)}}
.stat-tile {
  background:var(--surface);border-radius:var(--r-lg);
  padding:14px 16px;border:1px solid var(--border);
  display:flex;align-items:center;gap:12px;
  box-shadow:var(--shadow-sm);
  transition:all var(--t) var(--ease);
  cursor:default;
}
.stat-tile:hover{transform:translateY(-3px);box-shadow:var(--shadow-md)}
.stat-tile-icon {
  width:40px;height:40px;border-radius:var(--r-sm);
  display:flex;align-items:center;justify-content:center;
  font-size:1.1rem;flex-shrink:0;
}
.stat-tile-icon.blue{background:var(--brand-bg);color:var(--brand)}
.stat-tile-icon.green{background:var(--success-bg);color:var(--success)}
.stat-tile-icon.pink{background:#fce7f3;color:#db2777}
[data-theme="dark"] .stat-tile-icon.pink{background:rgba(219,39,119,.15)}
.stat-tile-icon.purple{background:var(--coupon-bg);color:var(--coupon)}
.stat-tile-val{font-size:1.35rem;font-weight:900;color:var(--text);line-height:1}
.stat-tile-lbl{font-size:.68rem;font-weight:600;color:var(--text-3);margin-top:2px}

/* Section header */
.section-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.section-title{font-size:.88rem;font-weight:800;color:var(--text-2);text-transform:uppercase;letter-spacing:.08em}

/* Classes grid */
.classes-grid {
  display:grid;
  grid-template-columns:repeat(auto-fill, minmax(150px, 1fr));
  gap:12px;
  margin-bottom:24px;
}
@media(min-width:600px){.classes-grid{grid-template-columns:repeat(auto-fill,minmax(180px,1fr))}}
.class-card {
  background:var(--surface);border-radius:var(--r-xl);
  padding:20px 14px;text-align:center;cursor:pointer;
  border:1.5px solid var(--border);
  box-shadow:var(--shadow-sm);
  transition:all var(--t) var(--spring);
  position:relative;overflow:hidden;
}
.class-card::before {
  content:'';position:absolute;top:0;left:0;right:0;height:3px;
  background:linear-gradient(90deg,var(--brand),var(--coupon));
  transform:scaleX(0);transform-origin:left;
  transition:transform .3s var(--spring);
}
.class-card:hover{transform:translateY(-5px) scale(1.01);box-shadow:var(--shadow-lg);border-color:var(--brand)}
.class-card:hover::before{transform:scaleX(1)}
.class-card:active{transform:scale(.97)}
.class-icon {
  width:60px;height:60px;border-radius:var(--r-lg);
  background:var(--brand-bg);color:var(--brand);
  display:inline-flex;align-items:center;justify-content:center;
  font-size:1.5rem;margin-bottom:12px;font-weight:800;
  transition:all var(--t) var(--spring);
}
.class-card:hover .class-icon{background:var(--brand);color:#fff;transform:rotate(6deg) scale(1.1)}
.class-name{font-size:.95rem;font-weight:700;color:var(--text);margin-bottom:8px}
.class-badge {
  display:inline-flex;align-items:center;gap:4px;
  background:var(--brand-bg);color:var(--brand);
  padding:3px 10px;border-radius:var(--r-full);
  font-size:.72rem;font-weight:700;
}

/* Footer */
.site-footer {
  background:var(--surface);border-radius:var(--r-xl);
  border:1px solid var(--border);padding:16px 20px;
  margin-top:20px;
}
.footer-inner{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px}
.footer-brand{display:flex;align-items:center;gap:8px;text-decoration:none}
.footer-logo{width:28px;height:28px;background:var(--brand);border-radius:8px;display:flex;align-items:center;justify-content:center;overflow:hidden}
.footer-logo img{width:100%;height:100%;object-fit:cover}
.footer-name{font-size:.82rem;font-weight:800;color:var(--text)}
.footer-copy{font-size:.72rem;color:var(--text-3);text-align:end;line-height:1.6}
.footer-links{display:flex;justify-content:center;gap:16px;flex-wrap:wrap;padding-top:12px;border-top:1px solid var(--border)}
.footer-link{color:var(--brand);text-decoration:none;font-size:.78rem;font-weight:600;display:flex;align-items:center;gap:4px;transition:all var(--t) var(--ease)}
.footer-link:hover{color:var(--brand-dark);transform:translateY(-2px)}

/* ═══════════════════════════════════════════════════════════════
   CLASS VIEW
═══════════════════════════════════════════════════════════════ */
.class-view{display:none}
.class-view.active{display:block}

.class-topbar {
  display:flex;align-items:center;justify-content:space-between;
  gap:10px;flex-wrap:wrap;
 border-radius:var(--r-xl);
 margin-bottom:10px;
}
.class-topbar-left{display:flex;align-items:center;gap:10px}
.class-title-text{font-size:1rem;font-weight:800;color:var(--brand)}
.date-chip {
  display:inline-flex;align-items:center;gap:6px;
  background:var(--brand-bg);color:var(--brand);
  padding:6px 12px;border-radius:var(--r-full);
  font-size:.75rem;font-weight:700;cursor:pointer;
  border:1px solid rgba(91,108,245,.2);
  transition:all var(--t) var(--ease);
}
.date-chip:hover{background:var(--brand);color:#fff}

/* Uncles bar */
.uncles-bar{display:flex;align-items:center;gap:10px;margin-bottom:10px;padding:0 2px;flex-wrap:wrap}
.uncles-bar-label{font-size:.75rem;font-weight:700;color:var(--text-3);flex-shrink:0}
.uncles-list{display:flex;padding-right:8px}
.uncle-avatar-wrap{position:relative;margin-left:-10px;transition:all var(--t) var(--ease);z-index:1}
.uncle-avatar-wrap:hover{z-index:100;transform:scale(1.18) translateY(-3px)}
.uncle-avatar-img{width:34px;height:34px;border-radius:50%;object-fit:cover;border:2.5px solid var(--bg);cursor:pointer;display:block}
.uncle-tooltip{position:absolute;bottom:calc(100% + 6px);left:50%;transform:translateX(-50%) translateY(4px);background:var(--text);color:var(--surface);padding:3px 8px;border-radius:var(--r-full);font-size:.68rem;font-weight:700;white-space:nowrap;pointer-events:none;opacity:0;transition:all var(--t) var(--ease)}
.uncle-avatar-wrap:hover .uncle-tooltip{opacity:1;transform:translateX(-50%) translateY(0)}

/* Pending registrations */
/* ── PENDING REGISTRATIONS ── */
.pending-section{background:var(--surface);margin-bottom:12px;border-radius:var(--r-lg);border:1.5px solid rgba(245,158,11,.35);display:none;overflow:hidden;box-shadow:0 2px 12px rgba(245,158,11,.08)}
.pending-header{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;background:linear-gradient(135deg,rgba(245,158,11,.1),rgba(245,158,11,.04));border-bottom:1px solid rgba(245,158,11,.2)}
.pending-title{font-size:.88rem;font-weight:800;color:var(--warning-dark);display:flex;align-items:center;gap:8px;margin:0}
[data-theme="dark"] .pending-title{color:#fbbf24}
.pending-count-badge{background:var(--warning);color:#fff;border-radius:99px;font-size:.7rem;font-weight:800;padding:2px 9px;min-width:22px;text-align:center}
.pending-body{padding:10px 12px}
.pending-search-row{display:flex;gap:8px;margin-bottom:10px}
.pending-search-row .search-input{flex:1;font-size:.82rem;padding:8px 12px}
.pending-search-row .search-btn{padding:8px 12px;font-size:.82rem}
.reg-card{background:var(--surface-2,#f7f8fc);border:1px solid var(--border);border-radius:var(--r-md,10px);margin-bottom:8px;overflow:hidden;transition:box-shadow .2s}
.reg-card:last-child{margin-bottom:0}
.reg-card:hover{box-shadow:0 3px 14px rgba(0,0,0,.09)}
.reg-card-top{display:flex;align-items:center;gap:10px;padding:10px 12px;cursor:pointer}
.reg-avatar{width:38px;height:38px;border-radius:50%;background:linear-gradient(135deg,#f59e0b,#d97706);display:flex;align-items:center;justify-content:center;color:#fff;font-size:.95rem;flex-shrink:0;font-weight:800}
.reg-info{flex:1;min-width:0}
.reg-name{font-size:.88rem;font-weight:800;color:var(--text);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.reg-meta{font-size:.72rem;color:var(--text-3);margin-top:2px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}
.reg-meta span{display:flex;align-items:center;gap:3px}
.reg-status-badge{flex-shrink:0;background:rgba(245,158,11,.15);color:var(--warning-dark);border-radius:99px;font-size:.68rem;font-weight:700;padding:3px 9px;display:flex;align-items:center;gap:4px}
.reg-actions{display:flex;gap:6px;padding:0 12px 10px;max-width: 340px;}
.reg-actions .btn{flex:1;font-size:.78rem;padding:7px 10px;display:flex;align-items:center;justify-content:center;gap:5px}
.reg-expand-toggle{width:28px;height:28px;border-radius:50%;background:transparent;border:1.5px solid var(--border);color:var(--text-3);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;flex-shrink:0;font-size:.72rem}
.reg-expand-toggle:hover{border-color:var(--brand);color:var(--brand);background:var(--brand-bg)}
.reg-expand-toggle.open{transform:rotate(180deg);border-color:var(--brand);color:var(--brand);background:var(--brand-bg)}
.reg-details{display:none;padding:0 12px 10px;border-top:1px solid var(--border);margin-top:0}
.reg-details.open{display:block;animation:fadeSlideDown .18s ease both}
.reg-detail-row{display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--border);font-size:.8rem}
.reg-detail-row:last-child{border-bottom:none}
.reg-detail-label{color:var(--text-3);font-weight:600}
.reg-detail-val{color:var(--text);font-weight:700;text-align:left}
.reg-select-row{display:flex;align-items:center;gap:6px;padding:4px 12px 8px;font-size:.75rem;color:var(--text-3)}
.reg-select-row input[type=checkbox]{width:15px;height:15px;cursor:pointer;accent-color:var(--warning)}
.pending-bulk-row{display:flex;gap:8px;margin-top:10px;padding-top:10px;border-top:1px solid rgba(245,158,11,.2)}
.pending-bulk-row .btn{flex:1;font-size:.78rem;padding:8px}
[data-theme="dark"] .reg-card{background:rgba(255,255,255,.04);border-color:rgba(255,255,255,.09)}
[data-theme="dark"] .reg-details{border-color:rgba(255,255,255,.09)}

/* single-row action strip */
.action-strip{display:flex;gap:6px;margin-bottom:8px;flex-wrap:nowrap;align-items:stretch}
.action-dropdown{position:relative;flex:1;min-width:0}
.action-strip-standalone{border:2px solid var(--border-solid)!important;background:var(--surface)!important;color:var(--text-2)!important;flex-shrink:0!important;flex:0 0 auto!important;width:auto!important;padding:10px 10px!important}
.action-strip-standalone:hover{border-color:var(--brand)!important;color:var(--brand)!important;background:var(--brand-bg)!important}
.action-strip-add{border-color:rgba(16,185,129,.35)!important;color:var(--success)!important}
.action-strip-add:hover{border-color:var(--success)!important;background:var(--success-bg)!important;color:var(--success)!important}
.action-strip-btn {
  width:100%;display:flex;align-items:center;justify-content:center;gap:6px;
  padding:10px 10px;
  background:var(--surface);border:1.5px solid var(--border);
  border-radius:var(--r-lg);font-family:'Cairo',sans-serif;
  font-size:.83rem;font-weight:700;cursor:pointer;
  color:var(--text-2);transition:all var(--t) var(--ease);
}
.action-strip-btn:hover{background:var(--brand-bg);color:var(--brand);border-color:var(--brand);transform:translateY(-1px)}
.action-strip-btn.open{background:var(--brand-bg);color:var(--brand);border-color:var(--brand)}
.action-strip-btn > i:first-child{font-size:.85rem;flex-shrink:0}
.strip-label{flex:1;text-align:center;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.chevron{font-size:.65rem;flex-shrink:0;transition:transform var(--t) var(--ease)}
.action-strip-btn.open .chevron{transform:rotate(180deg)}

.dropdown-menu {
  display:none;position:absolute;top:calc(100% + 6px);right:0;
  background:var(--surface);border-radius:var(--r-lg);
  box-shadow:var(--shadow-lg);z-index:99999;
  border:1px solid var(--border);overflow:hidden;
  animation:dropIn .15s var(--spring);min-width:180px;width:max-content;
}
.dropdown-menu.open{display:block}
.action-dropdown:last-child .dropdown-menu{left:auto;right:0}
@keyframes dropIn{from{opacity:0;transform:translateY(-8px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}

.dropdown-divider{height:1px;background:var(--border-solid);margin:4px 0}
.dropdown-group-label{padding:8px 14px 3px;font-size:.66rem;font-weight:800;color:var(--text-3);text-transform:uppercase;letter-spacing:.07em}
.dropdown-item {
  display:flex;align-items:center;gap:9px;padding:7px 14px;
  font-size:.82rem;font-weight:600;cursor:pointer;color:var(--text);
  transition:all var(--t) var(--ease);border:none;background:none;
  width:100%;text-align:right;font-family:'Cairo',sans-serif;
}
.dropdown-item:hover{background:var(--brand-bg);color:var(--brand)}
.dropdown-item.danger:hover{background:var(--danger-bg);color:var(--danger)}
.dropdown-item.success:hover{background:var(--success-bg);color:var(--success)}
.dropdown-item.coupon:hover{background:var(--coupon-bg);color:var(--coupon)}
.dropdown-item i{width:16px;text-align:center;font-size:.85rem}

/* Custom export builder */
.export-builder{display:grid;grid-template-columns:minmax(250px,330px) 1fr;gap:12px;height:100%;min-height:0}
.export-controls{overflow:auto;padding-left:4px;display:flex;flex-direction:column;gap:10px}
.export-panel{background:var(--surface-2);border:1px solid var(--border-solid);border-radius:var(--r-lg);padding:10px}
.export-panel-title{font-size:.78rem;font-weight:800;color:var(--text);margin-bottom:8px;display:flex;align-items:center;gap:6px}
.export-field-list{display:flex;flex-direction:column;gap:6px}
.export-field-row{display:flex;align-items:center;gap:7px;background:var(--surface);border:1px solid var(--border);border-radius:var(--r-md);padding:7px 8px;font-size:.78rem}
.export-field-row input{accent-color:var(--brand);width:15px;height:15px}
.export-field-row label{flex:1;cursor:pointer;font-weight:700;color:var(--text)}
.export-field-actions{display:flex;gap:3px}
.export-mini-btn{width:24px;height:24px;border-radius:7px;border:1px solid var(--border-solid);background:var(--surface);color:var(--text-2);cursor:pointer;display:inline-flex;align-items:center;justify-content:center;font-size:.62rem}
.export-mini-btn:hover{border-color:var(--brand);color:var(--brand);background:var(--brand-bg)}
.export-date-options{display:grid;gap:7px}
.export-preview-wrap{background:#fff;color:#1e293b;border:1px solid var(--border-solid);border-radius:var(--r-lg);overflow:auto;min-height:0;box-shadow:var(--shadow-sm)}
[data-theme="dark"] .export-preview-wrap{background:#fff;color:#1e293b}
.custom-export-doc{direction:rtl;font-family:Cairo,Tahoma,Arial,sans-serif;background:#fff;color:#1e293b;padding:16px;min-width:max-content}
.custom-export-title{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;border-bottom:2px solid #1e293b;padding-bottom:8px;margin-bottom:12px}
.custom-export-title h2{font-size:18px;margin:0;font-weight:900;color:#1e293b}
.custom-export-title small{display:block;color:#64748b;font-size:11px;margin-top:2px}
.custom-export-table{border-collapse:collapse;width:100%;font-size:12px;white-space:nowrap}
.custom-export-table th{background:#1e293b;color:#fff;padding:8px 10px;border:1px solid #334155;text-align:right;font-weight:800}
.custom-export-table td{padding:7px 10px;border:1px solid #e2e8f0;text-align:right;color:#1e293b}
.custom-export-table tr:nth-child(even) td{background:#f8fafc}
.custom-export-table .att-p{background:#d1fae5!important;color:#065f46;font-weight:800;text-align:center}
.custom-export-table .att-a{background:#fee2e2!important;color:#991b1b;font-weight:800;text-align:center}
.custom-export-photo{width:38px;height:38px;border-radius:50%;object-fit:cover;border:1.5px solid #cbd5e1;display:block}
@media(max-width:780px){.export-builder{grid-template-columns:1fr}.export-controls{max-height:38vh}.export-preview-wrap{min-height:42vh}}

/* ═══════════════════════════════════════════════════════════════
   STICKY ATTENDANCE TOOLBAR
═══════════════════════════════════════════════════════════════ */
/* ── Sticky attendance toolbar ─────────────────────────────── */
.att-toolbar {
    position: sticky;
    top: 58px;
    z-index: 200;
    border-radius: 0 0 var(--r-xl) var(--r-xl);
    background: var(--bg);
    backdrop-filter: blur(26px);
    -webkit-backdrop-filter: blur(26px);
    padding: 8px 8px 6px;
    margin-bottom: 8px;
    transition: background var(--t) var(--ease);
    box-shadow: 0 4px 12px -4px rgba(0,0,0,.08);
}
.att-toolbar ::-webkit-scrollbar { display:none; }
.toolbar-row { display:flex; align-items:center; gap:6px; }

/* ── Stats ─────────────────────────────────────────────────── */
.toolbar-stats {
    display:flex; gap:4px; flex:1;
    min-width:0; overflow-x:auto;
    flex-wrap:nowrap; scrollbar-width:none;
}
.toolbar-stat {
    display:flex; align-items:center; gap:4px;
    padding:6px 10px;
    background:var(--brand-bg); border-radius:var(--r-full);
    font-size:.72rem; font-weight:700; color:var(--brand);
    white-space:nowrap; flex-shrink:0;
}
.toolbar-stat .stat-lbl {
    font-weight:500; opacity:.8; font-size:.68rem;
}
.toolbar-stat.s { background:var(--success-bg); color:var(--success); }
.toolbar-stat.a { background:var(--danger-bg);  color:var(--danger); }
.toolbar-stat.c { background:var(--coupon-bg);  color:var(--coupon); }

/* ── Save buttons ─────────────────────────────────────────── */
.save-row { display:flex; gap:5px; flex-shrink:0; }

/* Base — mobile: column (icon above, label + count below) */
.save-btn {
    display: inline-flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2px;
    padding: 6px 10px;
    border: none;
    border-radius: var(--r-md);
    font-family: 'Cairo', sans-serif;
    cursor: pointer;
    transition: all var(--t) var(--ease);
    min-width: 52px;
}
.save-btn i { font-size:.9rem; line-height:1; flex-shrink:0; }
/* label + count on the same tiny line */
.save-btn .save-btn-bottom {
    display: flex;
    align-items: center;
    gap: 3px;
    line-height: 1;
}
.save-btn .save-btn-label {
    font-size: .57rem;
    font-weight: 700;
    white-space: nowrap;
}
/* count: tiny superscript-style badge inline with label */
.save-btn .save-count {
    font-size: .52rem;
    font-weight: 800;
    background: rgba(255,255,255,.38);
    padding: 0 3px;
    border-radius: 20px;
    line-height: 1.6;
    white-space: nowrap;
}
.save-btn:disabled {
    opacity:.35; cursor:not-allowed !important;
    transform:none !important; box-shadow:none !important;
}
.save-btn-attendance { background:linear-gradient(135deg,var(--success),var(--success-dark)); color:#fff; }
.save-btn-coupons    { background:var(--coupon-grad); color:#fff; }
.save-btn-unsaved    { background:linear-gradient(135deg,var(--warning),var(--warning-dark)); color:#fff; }
.save-btn:not(:disabled):hover { transform:translateY(-2px); box-shadow:var(--shadow-md); }

/* ── Mobile (<520px): two rows, stats centered ────────────── */
@media (max-width:519px) {
    .toolbar-row  { flex-direction:column; gap:5px; }
    .toolbar-stats { width:100%; justify-content:center; }
    .save-row { width:100%; justify-content:center; gap:5px; }
    .save-btn { flex:1; max-width:88px; padding:7px 5px; }
}

/* ── Desktop (≥520px): single row, buttons horizontal ───────  */
@media (min-width:520px) {
    .toolbar-stats { border-left:1px solid var(--border-solid); padding-left:8px; }
    .toolbar-stat  { font-size:.78rem; padding:7px 12px; }

    /* buttons switch to row layout: icon left, text right */
    .save-btn {
        flex-direction: row;
        gap: 5px;
        padding: 8px 13px;
        min-width: unset;
    }
    .save-btn i { font-size:.88rem; }
    .save-btn .save-btn-label { font-size:.72rem; }
    .save-btn .save-count { font-size:.6rem; padding:1px 5px; }
}

@media (min-width:768px) {
    .toolbar-stat { font-size:.82rem; padding:8px 14px; }
    .save-btn { padding:8px 16px; gap:6px; }
    .save-btn i { font-size:.95rem; }
    .save-btn .save-btn-label { font-size:.76rem; }
}

/* ═══════════════════════════════════════════════════════════════
   SEARCH
═══════════════════════════════════════════════════════════════ */
.search-wrap{display:flex;gap:4px;align-items:center;padding:8px;border-radius:var(--r-lg);margin-bottom:8px;}
.search-input{flex:1;padding:9px 14px;border-radius:var(--r-md);font-size:.88rem;font-family:'Cairo',sans-serif;background:var(--surface-3);border:1.5px solid var(--border-solid);color:var(--text);transition:all var(--t) var(--ease)}
.search-input:focus{outline:none;border-color:var(--brand);background:var(--surface);box-shadow:0 0 0 3px var(--brand-glow)}
.search-input::placeholder{color:var(--text-3)}
.search-btn{background:var(--brand);color:#fff;border:none;padding:12px;border-radius:var(--r-md);cursor:pointer;font-size:.88rem;transition:all var(--t) var(--ease)}
.search-btn:hover{background:var(--brand-dark)}
.clear-search-btn{background:var(--surface-3);color:var(--text-2);border:1px solid var(--border-solid);padding:7px 11px;border-radius:var(--r-md);cursor:pointer;font-size:.78rem;font-family:'Cairo',sans-serif;transition:all var(--t) var(--ease);display:none}
.clear-search-btn:hover{background:var(--danger-bg);color:var(--danger)}
.search-results-info{background:var(--brand-bg);padding:8px 14px;border-radius:var(--r-md);margin-bottom:8px;font-weight:700;color:var(--brand);display:none;border:1px solid rgba(91,108,245,.15);font-size:.82rem;text-align:center}
.search-results-info.show{display:block;animation:fadeSlideDown .2s var(--ease)}

/* ═══════════════════════════════════════════════════════════════
   ATTENDANCE LIST  — PRESERVED EXACTLY, REDESIGNED VISUALLY
═══════════════════════════════════════════════════════════════ */
.attendance-list{display:grid;gap:6px;grid-template-columns:repeat(auto-fill,minmax(340px,1fr))}

.attendance-item {
  display:flex;align-items:stretch;
  background:var(--surface);
  border-radius:var(--r-xl);
  transition:all .18s var(--ease);
  border:2px solid transparent;
  overflow:hidden;min-height:148px;
  padding:3px;
  position:relative;
}
.attendance-item:hover{box-shadow:var(--shadow-md)}
.attendance-item.has-local{border-right:3px solid var(--warning)!important}

.attendance-item.absent{
  border-color:rgba(239,68,68,.28);
  background:linear-gradient(135deg,rgba(254,242,242,.95),rgba(254,226,226,.9));
}
[data-theme="dark"] .attendance-item.absent{
  background:linear-gradient(135deg,rgba(127,29,29,.3),rgba(153,27,27,.25));
  border-color:rgba(239,68,68,.35);
}
.attendance-item.present{
  border-color:rgba(16,185,129,.32);
  background:linear-gradient(135deg,rgba(236,253,245,.95),rgba(209,250,229,.9));
}
[data-theme="dark"] .attendance-item.present{
  background:linear-gradient(135deg,rgba(6,78,59,.3),rgba(5,95,75,.25));
  border-color:rgba(16,185,129,.4);
}

/* student info panel (right in RTL) */
.student-info{
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  flex:1;padding:10px;
  background:rgba(91,108,245,.04);cursor:pointer;
  transition:background var(--t) var(--ease);
  min-width:100px;border-radius:0 var(--r-xl) var(--r-xl) 0;
}
[data-theme="dark"] .student-info{background:rgba(91,108,245,.07)}
.student-info:hover{background:rgba(91,108,245,.1)}
.student-avatar{
  min-width:50px;min-height:50px;max-width:50px;max-height:50px;
  border-radius:50%;object-fit:cover;
  background:linear-gradient(135deg,var(--brand-light),var(--brand));
  display:flex;align-items:center;justify-content:center;
  font-size:1.1rem;color:#fff;cursor:pointer;
  transition:all var(--t) var(--spring);
  box-shadow:var(--shadow-sm);border:2.5px solid var(--surface);flex-shrink:0;
}
.student-avatar:hover{transform:scale(1.12);box-shadow:var(--shadow-md)}
.student-name{font-weight:700;color:var(--brand);font-size:.94rem;text-align:center;padding:4px 6px;border-radius:var(--r-sm);cursor:pointer;word-break:break-word;margin-top:4px}
.status-indicator{display:flex;flex-wrap:wrap;gap:2px;justify-content:center;max-width:120px;margin-top:3px}
.status-badge{font-size:.58rem;font-weight:600;padding:2px 6px;border-radius:var(--r-full);white-space:nowrap}
.status-badge.saved{background:var(--success-bg);color:#065f46}
[data-theme="dark"] .status-badge.saved{color:#6ee7b7}
.status-badge.local{background:var(--surface-3);color:var(--text-3)}
.status-badge.local-unsaved{background:var(--warning-bg);color:#92400e;border:1px solid rgba(245,158,11,.25)}
[data-theme="dark"] .status-badge.local-unsaved{color:#fbbf24}
.status-badge.changed{background:var(--warning-bg);color:var(--warning-dark)}
.status-badge.pending{background:var(--surface-3);color:var(--text-3)}
.status-badge.coupon-unsaved{background:var(--coupon-bg);color:var(--coupon-dark)}

/* actions panel (left in RTL) */
.attendance-actions{
  display:flex;flex-direction:column;gap:4px;align-items:flex-end;
  padding:10px 10px 10px 5px;
  background:rgba(91,108,245,.035);
  border-radius:var(--r-xl) 0 0 var(--r-xl);
  min-width:205px;justify-content:center;
}
[data-theme="dark"] .attendance-actions{background:rgba(91,108,245,.06)}

.student-coupons{
  background:var(--coupon-bg);color:var(--coupon-dark);
  padding:4px 10px;border-radius:var(--r-full);
  font-weight:700;font-size:.82rem;display:flex;align-items:center;gap:4px;
  min-width:60px;justify-content:center;transition:all var(--t) var(--ease);
}
[data-theme="dark"] .student-coupons{color:var(--brand-light)}

.coupon-toggle-row{display:flex;align-items:center;gap:3px;width:100%}
.coupon-toggle-btn{
  width:46px;height:26px;border-radius:var(--r-xl);border:none;
  display:flex;align-items:center;justify-content:center;
  cursor:pointer;font-size:.7rem;font-weight:800;
  transition:all var(--t) var(--spring);
}
.coupon-toggle-btn.minus{background:var(--danger-bg);color:var(--danger)}
.coupon-toggle-btn.plus{background:var(--success-bg);color:var(--success)}
.coupon-toggle-btn:hover{transform:scale(1.18);box-shadow:var(--shadow-sm)}

.coupon-value-display{
  flex:1;text-align:center;font-weight:800;color:var(--coupon-dark);
  font-size:.8rem;padding:4px 0 14px;cursor:pointer;user-select:none;
  border-radius:var(--r-sm);background:var(--coupon-bg);
  transition:all var(--t) var(--spring);position:relative;
}
[data-theme="dark"] .coupon-value-display{color:var(--brand-light)}
.coupon-value-display:hover{transform:scale(1.08)}
/* 4-dot position indicator */
.coupon-value-display::after{
  content:'';position:absolute;
  bottom:4px;left:50%;transform:translateX(-50%);
  width:34px;height:5px;
  background:
    radial-gradient(circle, currentColor 2px, transparent 2px) 0 0,
    radial-gradient(circle, currentColor 2px, transparent 2px) 11px 0,
    radial-gradient(circle, currentColor 2px, transparent 2px) 22px 0,
    radial-gradient(circle, currentColor 2px, transparent 2px) 33px 0;
  background-size:11px 5px;background-repeat:no-repeat;
  opacity:.18;
}
.coupon-value-display[data-idx="0"]::after{background:radial-gradient(circle,currentColor 2.5px,transparent 2.5px) 0 0,radial-gradient(circle,currentColor 2px,transparent 2px) 11px 0,radial-gradient(circle,currentColor 2px,transparent 2px) 22px 0,radial-gradient(circle,currentColor 2px,transparent 2px) 33px 0;background-size:11px 5px;background-repeat:no-repeat;opacity:.5}
.coupon-value-display[data-idx="1"]::after{background:radial-gradient(circle,currentColor 2px,transparent 2px) 0 0,radial-gradient(circle,currentColor 2.5px,transparent 2.5px) 11px 0,radial-gradient(circle,currentColor 2px,transparent 2px) 22px 0,radial-gradient(circle,currentColor 2px,transparent 2px) 33px 0;background-size:11px 5px;background-repeat:no-repeat;opacity:.5}
.coupon-value-display[data-idx="2"]::after{background:radial-gradient(circle,currentColor 2px,transparent 2px) 0 0,radial-gradient(circle,currentColor 2px,transparent 2px) 11px 0,radial-gradient(circle,currentColor 2.5px,transparent 2.5px) 22px 0,radial-gradient(circle,currentColor 2px,transparent 2px) 33px 0;background-size:11px 5px;background-repeat:no-repeat;opacity:.5}
.coupon-value-display[data-idx="3"]::after{background:radial-gradient(circle,currentColor 2px,transparent 2px) 0 0,radial-gradient(circle,currentColor 2px,transparent 2px) 11px 0,radial-gradient(circle,currentColor 2px,transparent 2px) 22px 0,radial-gradient(circle,currentColor 2.5px,transparent 2.5px) 33px 0;background-size:11px 5px;background-repeat:no-repeat;opacity:.5}

.attend-btn-row{display:flex;gap:3px;width:100%}
.present-btn,.absent-btn{
  flex:1;padding:7px 3px;border:none;border-radius:var(--r-md);
  font-family:'Cairo',sans-serif;font-size:.7rem;font-weight:700;
  cursor:pointer;transition:all var(--t) var(--ease);
  display:flex;align-items:center;justify-content:center;gap:3px;
}
.present-btn{background:rgb(0 255 171 / 16%);color:#065f46}
.present-btn:hover{background:var(--success);color:#fff;transform:scale(1.03)}
[data-theme="dark"] .present-btn{background:rgba(16,185,129,.18);color:#6ee7b7}
[data-theme="dark"] .present-btn:hover{color:#fff}
.absent-btn{background:rgba(239,68,68,.1);color:#991b1b}
.absent-btn:hover{background:var(--danger);color:#fff;transform:scale(1.03)}
[data-theme="dark"] .absent-btn{background:rgba(239,68,68,.18);color:#fca5a5}
[data-theme="dark"] .absent-btn:hover{color:#fff}

/* ═══════════════════════════════════════════════════════════════
   BUTTONS
═══════════════════════════════════════════════════════════════ */
.btn{
  background:linear-gradient(135deg,var(--brand),var(--brand-dark));
  color:#fff;border:none;padding:9px 16px;border-radius:var(--r-md);
  cursor:pointer;font-weight:700;transition:all var(--t) var(--ease);
  display:inline-flex;align-items:center;justify-content:center;gap:6px;
  font-family:'Cairo',sans-serif;font-size:.82rem;
  position:relative;overflow:hidden;
}
.btn:hover{transform:translateY(-2px);box-shadow:var(--shadow-md)}
.btn:active{transform:scale(.97)}
.btn:disabled{opacity:.5;cursor:not-allowed;transform:none!important;box-shadow:none!important}
.btn-success{background:linear-gradient(135deg,var(--success),var(--success-dark))}
.btn-danger{background:linear-gradient(135deg,var(--danger),var(--danger-dark))}
.btn-warning{background:linear-gradient(135deg,var(--warning),var(--warning-dark))}
.btn-info{background:linear-gradient(135deg,#3b82f6,#2563eb)}
.btn-secondary{background:linear-gradient(135deg,#64748b,#475569)}
.btn-coupon{background:var(--coupon-grad)}
.btn-ghost{background:var(--surface-3);color:var(--text-2);border:1px solid var(--border-solid)}
.btn-ghost:hover{background:var(--brand-bg);color:var(--brand)}
.btn-sm{padding:8px 14px;font-size:.8rem;border-radius:var(--r-md)}
.btn-xs{padding:5px 10px;font-size:.72rem;border-radius:var(--r-sm)}

/* ═══════════════════════════════════════════════════════════════
   MODALS  — bottom sheet on mobile, centered on desktop
═══════════════════════════════════════════════════════════════ */
/*
  Z-INDEX LAYER STACK:
  100        — topbar / dropdowns
  200        — sticky toolbar
  999999     — base modals (list/info modals)
  1000005    — action modals (edit, add, delete, confirmations) — above list modals
  1000010    — sheet modal
  1000050    — image modal
  1000060    — crop modal
  9999997    — PWA install sheet
  9999998    — offline banner
  2147483640 — ctx backdrop
  2147483647 — ctx menu
*/
.modal-overlay{
  display:none;position:fixed;inset:0;
  background:rgba(0,0,0,.45);backdrop-filter:blur(6px);
  z-index:999999;justify-content:flex-end;align-items:flex-end;flex-direction:column;
}
.modal-overlay.active{display:flex;animation:overlayIn .2s var(--ease)}
@keyframes overlayIn{from{opacity:0}to{opacity:1}}

.modal{
  background:var(--surface);
  border-radius:var(--r-2xl) var(--r-2xl) 0 0;
  width:100%;max-width:600px;
  height:82vh;max-height:82vh;
  overflow-y:auto;overflow-x:hidden;
  box-shadow:0 -8px 40px rgba(0,0,0,.2);
  margin:0 auto;padding:0 20px 20px;
  position:relative;
  animation:sheetUp .35s var(--spring);
  touch-action:pan-y;-webkit-overflow-scrolling:touch;
  transition:background var(--t) var(--ease);
}
.modal-lg{max-width:1400px;height:88vh;max-height:80vh}
.modal-sm{height:auto;max-height:65vh}

@keyframes sheetUp{from{transform:translateY(100%)}to{transform:translateY(0)}}
@keyframes sheetDown{from{transform:translateY(0);opacity:1}to{transform:translateY(100%);opacity:0}}
.modal.closing{animation:sheetDown .25s var(--ease) forwards}

.modal::before{content:'';display:block;width:36px;height:4px;background:var(--border-solid);border-radius:2px;margin:12px auto 4px;flex-shrink:0}

.modal-header{
  display:flex;justify-content:space-between;align-items:center;
  margin-block:14px;padding-bottom:12px;
  border-bottom:1.5px solid var(--border-solid);
  position:sticky;top:0;background:var(--surface);z-index:10;padding-top:4px;
  transition:background var(--t) var(--ease);
}
.modal-header h3{font-size:1rem;font-weight:800;color:var(--text)}
.close-btn{
  background:var(--surface-3);border:none;color:var(--text-3);
  font-size:1.2rem;cursor:pointer;border-radius:50%;
  width:34px;height:34px;display:flex;align-items:center;justify-content:center;
  transition:all var(--t) var(--ease);
}
.close-btn:hover{background:var(--brand-bg);color:var(--brand);transform:scale(1.1)}

@media(min-width:769px){
  .modal-overlay{justify-content:center;align-items:center;flex-direction:row}
  .modal{height:auto;max-height:90vh;border-radius:var(--r-xl);box-shadow:var(--shadow-xl);animation:fadeScaleIn .25s var(--spring)}
  .modal::before{display:none}
  .modal-header{position:static;padding-top:0}
}
@keyframes fadeScaleIn{from{opacity:0;transform:scale(.96) translateY(16px)}to{opacity:1;transform:scale(1) translateY(0)}}

/* ═══════════════════════════════════════════════════════════════
   STUDENT DETAILS MODAL  — icon-rich detail rows
═══════════════════════════════════════════════════════════════ */
.detail-avatar-wrap{text-align:center;margin-bottom:18px;padding-bottom:18px;border-bottom:1px solid var(--border-solid)}
.detail-avatar{width:88px;height:88px;border-radius:50%;object-fit:cover;margin:0 auto;border:3px solid var(--brand-light);box-shadow:var(--shadow-md);cursor:pointer;transition:all var(--t) var(--spring);display:block}
.detail-avatar:hover{transform:scale(1.06);box-shadow:0 0 0 6px var(--brand-glow)}
.detail-avatar-fallback{width:88px;height:88px;border-radius:50%;background:var(--brand-bg);display:flex;align-items:center;justify-content:center;margin:0 auto;color:var(--brand);font-size:2.2rem}
.detail-student-name{font-size:1.1rem;font-weight:800;color:var(--text);text-align:center;margin-top:10px}
.detail-student-class{font-size:.78rem;color:var(--text-3);text-align:center;margin-top:4px}

.detail-row{
  display:flex;align-items:center;gap:12px;
  padding:5px 0;border-bottom:1px solid var(--border-solid);
  transition:background var(--t) var(--ease);
}
.detail-row:last-child{border-bottom:none}
.detail-row:hover{background:var(--surface-3);border-radius:var(--r-sm);padding-inline:8px;margin-inline:-8px}
.detail-icon{
  width:32px;height:32px;border-radius:var(--r-sm);
  display:flex;align-items:center;justify-content:center;
  font-size:.82rem;flex-shrink:0;
}
.detail-icon.blue{background:var(--brand-bg);color:var(--brand)}
.detail-icon.green{background:var(--success-bg);color:var(--success)}
.detail-icon.purple{background:var(--coupon-bg);color:var(--coupon)}
.detail-icon.pink{background:#fce7f3;color:#db2777}
[data-theme="dark"] .detail-icon.pink{background:rgba(219,39,119,.15)}
.detail-icon.orange{background:var(--warning-bg);color:var(--warning-dark)}
.detail-icon.red{background:var(--danger-bg);color:var(--danger)}
.detail-icon.teal{background:rgba(20,184,166,.12);color:#14b8a6}
[data-theme="dark"] .detail-icon.teal{background:rgba(20,184,166,.15)}
.detail-label{font-size:.74rem;font-weight:700;color:var(--text-3);min-width:80px;flex-shrink:0}
.detail-val{font-size:.88rem;font-weight:600;color:var(--text);flex:1;text-align:start}

/* ═══════════════════════════════════════════════════════════════
   FORM
═══════════════════════════════════════════════════════════════ */
.form-group{margin-bottom:14px}
.form-label{display:block;margin-bottom:5px;font-weight:700;color:var(--text-2);font-size:.82rem}
.form-input{
  width:100%;padding:10px 14px 10px 38px;border:1.5px solid var(--border-solid);
  border-radius:var(--r-md);font-size:.88rem;font-family:'Cairo',sans-serif;
  background:var(--surface-3);color:var(--text);
  transition:all var(--t) var(--ease);
}
.form-input:focus{outline:none;border-color:var(--brand);background:var(--surface);box-shadow:0 0 0 3px var(--brand-glow)}
.form-input::placeholder{color:var(--text-3)}
select.form-input{cursor:pointer}
select.form-input option{background:var(--surface);color:var(--text)}
textarea.form-input{resize:vertical;min-height:80px}
/* Input icon wrapper */
.input-icon-wrap{position:relative;display:flex;align-items:center}
.input-icon-wrap .input-icon{
  position:absolute;right:12px;top:50%;transform:translateY(-50%);
  color:var(--brand);font-size:.82rem;pointer-events:none;z-index:1;
  width:16px;text-align:center;flex-shrink:0;
}
.input-icon-wrap .form-input{padding-right:36px}
.input-icon-wrap select.form-input{padding-right:36px}

/* Birthday input */
input[id*="Birthday"],input[id*="birthday"]{direction:ltr;text-align:center;font-family:'IBM Plex Mono',monospace;letter-spacing:1px}

/* ═══════════════════════════════════════════════════════════════
   TABLE
═══════════════════════════════════════════════════════════════ */
/* ── Table container: always scrollable, touch-friendly ─────── */
.table-container {
    width: 100%;
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
    border-radius: var(--r-lg);
    box-shadow: var(--shadow-sm);
    background: var(--surface);
    border: 1px solid var(--border-solid);
    position: relative;
}
/* Zoom wrapper inside sheet table */
.table-zoom-wrap {
    overflow: auto;
    -webkit-overflow-scrolling: touch;
    max-height: 55vh;
    /* No forced grab cursor — JS adds it only when dragging empty space */
}
/* JS adds .is-dragging to wrap during mouse drag */
.table-zoom-wrap.is-dragging { cursor: grabbing !important; user-select: none; }
/* Text inside table: always selectable / pointer */
.table-zoom-wrap td, .table-zoom-wrap th { cursor: default; }
.table-zoom-inner {
    transform-origin: top right;
    transition: transform .18s ease;
    display: inline-block;
    min-width: 100%;
}

/* Desktop table: normal */
.data-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    font-size: .8rem;
    white-space: nowrap;
}
.data-table th {
    background: var(--text); color: var(--surface);
    padding: 10px 12px; text-align: right;
    font-weight: 700; font-size: .78rem;
    position: sticky; top: 0; z-index: 2;
}
[data-theme="dark"] .data-table th { background:var(--surface-3); color:var(--text); }
.data-table td {
    padding: 9px 12px; border-bottom: 1px solid var(--border-solid);
    text-align: right; color: var(--text);
    transition: background var(--t) var(--ease);
}
.data-table tr:last-child td  { border-bottom: none; }
.data-table tr:nth-child(even) td { background: var(--surface-2); }
.data-table tr:hover td { background: var(--brand-bg); }

/* ── Mobile card view (< 600px): each row becomes a card ────── */
@media (max-width: 599px) {
    /* All tables except the big attendance-sheet table */
    .table-container:not(.sheet-container) .data-table,
    .table-container:not(.sheet-container) .data-table thead,
    .table-container:not(.sheet-container) .data-table tbody,
    .table-container:not(.sheet-container) .data-table th,
    .table-container:not(.sheet-container) .data-table td,
    .table-container:not(.sheet-container) .data-table tr {
        display: block;
    }
    .table-container:not(.sheet-container) .data-table thead {
        display: none; /* hide original header — labels shown via data-label */
    }
    .table-container:not(.sheet-container) .data-table tr {
        background: var(--surface) !important;
        border: 1.5px solid var(--border-solid);
        border-radius: var(--r-lg);
        margin: 8px;
        padding: 4px 0;
        box-shadow: var(--shadow-sm);
    }
    .table-container:not(.sheet-container) .data-table td {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
        padding: 7px 12px;
        border-bottom: 1px solid var(--border-solid);
        font-size: .82rem;
        border-radius: 0;
    }
    .table-container:not(.sheet-container) .data-table td:last-child {
        border-bottom: none;
    }
    /* Label shown on the right (RTL: left visually = end of flex) */
    .table-container:not(.sheet-container) .data-table td::before {
        content: attr(data-label);
        font-size: .72rem;
        font-weight: 700;
        color: var(--text-3);
        white-space: nowrap;
        flex-shrink: 0;
    }
    .table-container:not(.sheet-container) .data-table td:empty { display: none; }
}

/* ── Sheet table: always horizontal-scroll, zoom-able ───────── */
.sheet-container {
    overflow: hidden;
    border-radius: var(--r-lg);
}
.sheet-container .table-zoom-wrap {
    overflow: auto;
    -webkit-overflow-scrolling: touch;
    max-height: 58vh;
}
.sheet-container .data-table th:first-child,
.sheet-container .data-table td:first-child {
    position: sticky; right: 0; z-index: 3;
    background: var(--surface);
    border-left: 2px solid var(--border-solid);
    min-width: 120px;
}
.sheet-container .data-table th:first-child {
    background: var(--text); color: var(--surface); z-index: 4;
}
[data-theme="dark"] .sheet-container .data-table th:first-child { background:var(--surface-3); }

/* Zoom controls */
.zoom-controls {
    display: flex; gap: 5px; align-items: center;
    background: var(--surface-3);
    border-radius: var(--r-full);
    padding: 3px 6px;
}
.zoom-btn {
    width: 28px; height: 28px;
    border: none; border-radius: 50%;
    background: var(--surface); color: var(--text);
    cursor: pointer; font-size: .85rem;
    display: flex; align-items: center; justify-content: center;
    transition: all .15s; box-shadow: var(--shadow-sm);
}
.zoom-btn:hover { background: var(--brand-bg); color: var(--brand); }
.zoom-level {
    font-size: .72rem; font-weight: 700;
    color: var(--text-3); min-width: 32px;
    text-align: center;
}
.attendance-present{background:linear-gradient(135deg,var(--success-bg),rgba(167,243,208,.6))!important;color:#065f46;font-weight:700;text-align:center}
.attendance-absent{background:linear-gradient(135deg,var(--danger-bg),rgba(254,202,202,.6))!important;color:#991b1b;font-weight:700;text-align:center}
[data-theme="dark"] .attendance-present{color:#6ee7b7}
[data-theme="dark"] .attendance-absent{color:#fca5a5}

/* ═══════════════════════════════════════════════════════════════
   BIRTHDAY, FRIDAYS, SHEET …misc
═══════════════════════════════════════════════════════════════ */
.birthday-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:10px}
/* old .birthday-card kept for back-compat, new style = .birthday-card-new */
.birthday-card{background:var(--surface-3);padding:14px;border-radius:var(--r-lg);border-right:4px solid var(--brand);transition:all var(--t) var(--ease);box-shadow:var(--shadow-sm);cursor:pointer}
.birthday-card:hover{transform:translateY(-3px);box-shadow:var(--shadow-md);border-color:var(--success)}
.birthday-name{font-weight:700;color:var(--brand);font-size:.95rem;margin-bottom:3px}
.birthday-details{display:flex;justify-content:space-between;color:var(--text-3);font-size:.8rem}
.month-selector{display:flex;gap:5px;margin-bottom:14px;flex-wrap:nowrap;overflow-x:auto;padding-bottom:4px}
.month-selector::-webkit-scrollbar{height:3px}
.month-btn{background:var(--surface-3);color:var(--text-2);border:1px solid var(--border-solid);padding:7px 14px;border-radius:var(--r-full);cursor:pointer;transition:all var(--t) var(--ease);font-weight:700;font-family:'Cairo',sans-serif;white-space:nowrap;font-size:.8rem}
.month-btn.active{background:linear-gradient(135deg,var(--brand),var(--brand-dark));color:#fff;border-color:transparent;box-shadow:var(--shadow-sm)}
.month-btn:hover:not(.active){background:var(--brand-bg);color:var(--brand)}

.fridays-list{overflow-y:auto;margin-bottom:14px;padding-top:6px}
.month-row{margin-bottom:20px;background:var(--surface-3);border-radius:var(--r-lg);padding:14px;border:1px solid var(--border)}
.month-row h4{color:var(--brand);margin-bottom:12px;font-size:.95rem;border-bottom:1.5px solid var(--border-solid);padding-bottom:6px;display:flex;align-items:center;gap:8px}
.fridays-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(108px,1fr));gap:8px}
.friday-item{background:var(--surface);padding:10px 8px;border-radius:var(--r-lg);cursor:pointer;transition:all var(--t) var(--spring);border:2px solid var(--border-solid);text-align:center;min-height:96px;display:flex;flex-direction:column;justify-content:center;align-items:center;position:relative}
.friday-item:hover{transform:translateY(-3px);box-shadow:var(--shadow-md);border-color:var(--brand)}
.friday-item.selected{background:linear-gradient(135deg,var(--brand),var(--brand-dark));border-color:var(--brand);color:#fff}
.friday-item.selected .friday-stats-row{color:rgba(255,255,255,.85)!important}
.friday-item.current-week{box-shadow:0 0 0 3px rgba(16,185,129,.3)}
.friday-item.custom-date{border-color:rgba(245,158,11,.5);border-style:dashed}
.friday-item.custom-date:hover{border-color:var(--warning);border-style:solid}
.friday-item.custom-date.selected{background:linear-gradient(135deg,var(--warning),var(--warning-dark));border-color:var(--warning);border-style:solid}
.friday-item .current-badge{position:absolute;top:-7px;right:-7px;background:var(--success);color:#fff;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:.6rem;font-weight:800}
.friday-item .custom-badge{position:absolute;top:-7px;left:-7px;background:var(--warning);color:#fff;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:.55rem;font-weight:800}
.friday-item .delete-custom-btn{position:absolute;top:4px;left:4px;background:rgba(239,68,68,.15);border:none;border-radius:50%;width:18px;height:18px;display:flex;align-items:center;justify-content:center;font-size:.55rem;color:var(--danger);cursor:pointer;opacity:0;transition:opacity .15s;z-index:2}
.friday-item:hover .delete-custom-btn{opacity:1}
.friday-item .delete-custom-btn:hover{background:var(--danger);color:#fff}
.friday-num{font-size:1.5rem;font-weight:900;line-height:1;color:var(--brand)}
.friday-item.selected .friday-num,.friday-item.selected .friday-day,.friday-item.selected .friday-date{color:#fff!important}
.friday-item.custom-date .friday-num{color:var(--warning-dark)}
.friday-item.custom-date.selected .friday-num{color:#fff!important}
.friday-day{font-size:.7rem;color:var(--text-3);font-weight:600;margin-top:2px}
.friday-date{font-size:.62rem;color:var(--text-3);direction:ltr;margin-top:1px}
.friday-stats-row{display:flex;align-items:center;justify-content:center;gap:5px;margin-top:5px;font-size:.58rem;font-weight:700;color:var(--text-3);line-height:1}
.friday-stats-row .fs-p{color:var(--success-dark);display:flex;align-items:center;gap:2px}
.friday-stats-row .fs-a{color:var(--danger);display:flex;align-items:center;gap:2px}
.friday-stats-row .fs-div{opacity:.3;font-size:.5rem}
[data-theme="dark"] .friday-stats-row .fs-p{color:#6ee7b7}
[data-theme="dark"] .friday-stats-row .fs-a{color:#fca5a5}
.friday-reset-row{display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;gap:8px;flex-wrap:wrap}
/* Custom date add section */
.custom-date-section{background:linear-gradient(135deg,rgba(245,158,11,.08),rgba(245,158,11,.04));border:1.5px solid rgba(245,158,11,.3);border-radius:var(--r-lg);padding:12px 14px;margin-bottom:14px}
.custom-date-section h4{font-size:.82rem;font-weight:800;color:var(--warning-dark);margin-bottom:10px;display:flex;align-items:center;gap:6px}
[data-theme="dark"] .custom-date-section h4{color:#fbbf24}
.custom-date-inputs{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
.custom-date-input{flex:1;min-width:130px;padding:8px 12px;border-radius:var(--r-md);border:1.5px solid var(--border-solid);background:var(--surface);color:var(--text);font-family:Cairo,sans-serif;font-size:.82rem;transition:border-color .2s}
.custom-date-input:focus{outline:none;border-color:var(--warning)}
.custom-dates-strip{display:none;flex-wrap:wrap;gap:6px;margin-top:10px;padding-top:10px;border-top:1px solid rgba(245,158,11,.2)}
.custom-dates-strip.has-items{display:flex}
.custom-date-chip{display:inline-flex;align-items:center;gap:5px;background:rgba(245,158,11,.12);color:var(--warning-dark);border:1px solid rgba(245,158,11,.3);padding:3px 8px 3px 4px;border-radius:var(--r-full);font-size:.72rem;font-weight:700;cursor:pointer;transition:all .15s}
.custom-date-chip:hover{background:var(--warning);color:#fff;border-color:var(--warning)}
.custom-date-chip .cdel{background:rgba(0,0,0,.1);border:none;border-radius:50%;width:16px;height:16px;display:flex;align-items:center;justify-content:center;font-size:.55rem;cursor:pointer;color:inherit;transition:.15s}
.custom-date-chip .cdel:hover{background:rgba(0,0,0,.25)}

/* Account modal */
.account-avatar-section{text-align:center;margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid var(--border-solid)}
/* Uncle profile circle avatar */
.account-avatar-circle-wrap{position:relative;width:84px;height:84px;margin:0 auto 10px;cursor:pointer}
.account-big-avatar{width:84px;height:84px;border-radius:50%;object-fit:cover;border:3px solid var(--brand-light);box-shadow:var(--shadow-md);cursor:pointer;transition:all var(--t) var(--spring);display:block}
.account-big-avatar:hover{transform:scale(1.06);box-shadow:0 0 0 6px var(--brand-glow)}
.account-avatar-plus{
  position:absolute;bottom:2px;left:2px;
  width:24px;height:24px;border-radius:50%;
  background:var(--brand);color:#fff;
  display:flex;align-items:center;justify-content:center;
  font-size:.7rem;box-shadow:0 2px 6px var(--brand-glow);
  border:2px solid var(--bg);pointer-events:none;
}
.account-name{font-size:1.1rem;font-weight:800;color:var(--text)}
.account-role{font-size:.78rem;color:var(--text-3);font-weight:600;margin-top:2px}
.account-info-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid var(--border-solid)}
.account-info-label{font-size:.76rem;color:var(--text-3);font-weight:700;display:flex;align-items:center;gap:6px}
.account-info-value{font-size:.84rem;font-weight:600;color:var(--text)}
.account-edit-form{display:none}
.account-edit-form.active{display:block;animation:fadeSlideDown .22s var(--ease)}

/* Announcement */
.announcement-form{background:var(--brand-bg);border-radius:var(--r-lg);padding:14px;margin-bottom:18px;border:1px solid rgba(91,108,245,.15)}
.announcements-table-wrap{max-height:340px;overflow-y:auto;border-radius:var(--r-lg)}

/* Skeleton — shapes match real attendance-item cards */
.skeleton-loader {
    display: grid;
    gap: 6px;
    padding: 4px;
    grid-template-columns: repeat(auto-fill, minmax(340px, 1fr));
}
/* Attendance-card skeleton */
.skeleton-row {
    min-height: 148px;
    background: linear-gradient(90deg,var(--surface-3) 25%,var(--surface-2) 50%,var(--surface-3) 75%);
    background-size: 200% 100%;
    border-radius: var(--r-xl);
    animation: shimmerSkeleton 1.4s ease infinite;
    border: 2px solid transparent;
    padding: 3px;
    position: relative;
    overflow: hidden;
}
/* Class-card skeleton (smaller) */
.skeleton-row.cls {
    min-height: 110px;
    border-radius: var(--r-xl);
}
/* Uncle avatar skeleton */
.skeleton-uncle {
    width: 34px; height: 34px;
    border-radius: 50%;
    background: linear-gradient(90deg,var(--surface-3) 25%,var(--surface-2) 50%,var(--surface-3) 75%);
    background-size: 200% 100%;
    animation: shimmerSkeleton 1.4s ease infinite;
    border: 2.5px solid var(--bg);
    margin-left: -10px;
    flex-shrink: 0;
}
@keyframes shimmerSkeleton{0%{background-position:200% 0}100%{background-position:-200% 0}}
.inline-spinner{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:20px;color:var(--text-3);font-size:.84rem;font-weight:600;width:100%}
.inline-spinner .spin{width:20px;height:20px;border:3px solid var(--border-solid);border-radius:50%;border-top-color:var(--brand);animation:spin .75s linear infinite;flex-shrink:0}

/* Toast stack container */
#toastStack {
    position: fixed;
    top: 68px;
    right: 12px;
    z-index: 9999999;
    display: flex;
    flex-direction: column;
    gap: 8px;
    pointer-events: none;
    /* fit to content, max 300px */
    width: auto;
    max-width: min(300px, calc(100vw - 24px));
    align-items: flex-end;
}
@media(min-width:600px){ #toastStack { right:16px; } }

.toast-item {
    display: inline-flex;
    align-items: flex-start;
    gap: 10px;
    padding: 10px 14px;
    border-radius: var(--r-lg);
    color: #fff;
    font-family: 'Cairo', sans-serif;
    font-size: .84rem;
    font-weight: 600;
    box-shadow: var(--shadow-lg);
    pointer-events: all;
    cursor: default;
    animation: toastSlideIn .28s var(--spring);
    position: relative;
    overflow: hidden;
    backdrop-filter: blur(4px);
    /* fit to content */
    width: fit-content;
    max-width: min(300px, calc(100vw - 24px));
    align-self: flex-end;
}
.toast-item.removing { animation: toastSlideOut .22s var(--ease) forwards; }

/* progress bar at bottom */
.toast-item::after {
    content: '';
    position: absolute;
    bottom: 0; left: 0;
    height: 3px;
    background: rgba(255,255,255,.4);
    border-radius: 0 0 var(--r-lg) var(--r-lg);
    animation: toastProgress var(--toast-dur, 4.5s) linear forwards;
}
@keyframes toastSlideIn  { from{opacity:0;transform:translateX(40px) scale(.94)} to{opacity:1;transform:translateX(0) scale(1)} }
@keyframes toastSlideOut { from{opacity:1;transform:translateX(0)} to{opacity:0;transform:translateX(50px)} }
@keyframes toastProgress { from{width:100%} to{width:0%} }

.toast-icon  { font-size:1rem; flex-shrink:0; margin-top:1px; }
.toast-body  { flex:1; min-width:0; }
.toast-msg   { line-height:1.4; word-break:break-word; }
.toast-action {
    margin-top:5px;
    background: rgba(255,255,255,.22);
    border: 1px solid rgba(255,255,255,.35);
    color: #fff;
    padding: 3px 10px;
    border-radius: var(--r-full);
    font-size:.74rem;
    font-weight:700;
    font-family:'Cairo',sans-serif;
    cursor:pointer;
    transition: background .15s;
}
.toast-action:hover { background:rgba(255,255,255,.38); }
.toast-close {
    flex-shrink: 0;
    background: rgba(255,255,255,.18);
    border: none;
    color: #fff;
    width: 22px; height: 22px;
    border-radius: 50%;
    cursor: pointer;
    display: flex; align-items:center; justify-content:center;
    font-size:.7rem;
    transition: background .15s;
    margin-top: 1px;
}
.toast-close:hover { background:rgba(255,255,255,.38); }

.toast-item.success { background:linear-gradient(135deg,var(--success),var(--success-dark)); }
.toast-item.error   { background:linear-gradient(135deg,var(--danger),var(--danger-dark)); }
.toast-item.info    { background:linear-gradient(135deg,var(--brand),var(--brand-dark)); }
.toast-item.warning { background:linear-gradient(135deg,var(--warning),var(--warning-dark)); }

/* keep old #toast for any legacy code that touches it directly */
#toast { display:none !important; }

/* Misc */
.delete-warning{background:var(--danger-bg);border:1.5px solid rgba(239,68,68,.25);padding:16px;border-radius:var(--r-lg);margin-bottom:16px;text-align:center}
.delete-warning i{font-size:2.2rem;color:var(--danger);display:block;margin-bottom:8px}
.image-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.92);backdrop-filter:blur(12px);z-index:1000050;flex-direction:column;justify-content:center;align-items:center}
.image-modal.active{display:flex;animation:overlayIn .2s var(--ease)}
.image-modal-toolbar{position:absolute;top:0;left:0;right:0;display:flex;justify-content:space-between;align-items:center;padding:12px 16px;background:linear-gradient(to bottom,rgba(0,0,0,.6),transparent);z-index:2}
.img-modal-btn{background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.25);color:#fff;width:38px;height:38px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.9rem;transition:all .18s;backdrop-filter:blur(4px)}
.img-modal-btn:hover{background:rgba(255,255,255,.3);transform:scale(1.1)}
.image-modal-body{flex:1;display:flex;align-items:center;justify-content:center;width:100%;overflow:hidden;position:relative;cursor:grab}
.image-modal-body.grabbing{cursor:grabbing}
.image-modal-content{max-width:90vw;max-height:85vh;border-radius:var(--r-lg);box-shadow:0 0 60px rgba(0,0,0,.8);transform-origin:center;transition:transform .1s;user-select:none;pointer-events:none}
.crop-modal{display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:1000060;justify-content:center;align-items:center}
.crop-modal.active{display:flex}
.crop-container{background:var(--surface);border-radius:var(--r-xl);padding:20px;max-width:90%;max-height:90%;position:relative;box-shadow:var(--shadow-xl)}
.crop-image-container{max-width:380px;max-height:380px;margin-bottom:14px}
.crop-controls{display:flex;gap:12px;justify-content:center}
.photo-editor-section{display:flex;flex-direction:column;align-items:center;margin-bottom:18px}
/* Circle avatar upload button */
.photo-circle-wrap{position:relative;width:88px;height:88px;cursor:pointer;flex-shrink:0}
.photo-circle-wrap .photo-circle-img{
  width:88px;height:88px;border-radius:50%;object-fit:cover;
  border:3px solid var(--brand-light);box-shadow:var(--shadow-md);
  background:var(--brand-bg);display:block;
  transition:all var(--t) var(--spring);
}
.photo-circle-wrap .photo-circle-placeholder{
  width:88px;height:88px;border-radius:50%;
  border:2.5px dashed var(--brand-light);
  background:var(--brand-bg);
  display:flex;align-items:center;justify-content:center;
  color:var(--brand);font-size:2rem;
  transition:all var(--t) var(--spring);
}
.photo-circle-wrap:hover .photo-circle-img,
.photo-circle-wrap:hover .photo-circle-placeholder{box-shadow:0 0 0 5px var(--brand-glow);border-color:var(--brand)}
/* Plus badge */
.photo-circle-plus{
  position:absolute;bottom:2px;left:2px;
  width:24px;height:24px;border-radius:50%;
  background:var(--brand);color:#fff;
  display:flex;align-items:center;justify-content:center;
  font-size:.7rem;box-shadow:0 2px 6px var(--brand-glow);
  border:2px solid var(--bg);
  pointer-events:none;
}
.upload-preview{max-width:88px;max-height:88px;border-radius:50%;object-fit:cover;display:none;margin:0 auto 10px;border:3px solid var(--brand-light);box-shadow:var(--shadow-md)}
.upload-controls{display:none;gap:10px;justify-content:center;margin-top:10px}
/* Legacy photo-upload-area kept for any other usages */
.photo-upload-area{display:flex;flex-direction:column;align-items:center;border:2px dashed rgba(255,255,255,.4);border-radius:var(--r-lg);padding:16px;cursor:pointer;transition:all var(--t) var(--ease);background:rgba(255,255,255,.08);color:#fff;text-align:center}
.photo-upload-area:hover{border-color:rgba(255,255,255,.8);background:rgba(255,255,255,.16)}
.photo-upload-area i{font-size:2rem;margin-bottom:6px}
.pending-section{background:var(--surface);margin-bottom:10px;padding:14px;border-radius:var(--r-lg);border:1.5px solid rgba(245,158,11,.3);display:none}
.pending-title{font-size:.9rem;font-weight:800;color:var(--warning-dark);margin-bottom:12px;display:flex;align-items:center;gap:8px}
.badge{border-radius:var(--r-full);padding:3px 9px;font-size:.72rem;font-weight:700;color:#fff;display:inline-block}
.table-toolbar{display:flex;flex-direction:column;gap:8px;margin-bottom:8px;}
.table-export-btns{display:flex;gap:8px;}
.table-export-btns .btn{flex:1;justify-content:center;padding:10px 14px;font-size:.82rem;}

/* Dark mode toggle */
.theme-toggle-icon-sun{display:none}
.theme-toggle-icon-moon{display:block}
[data-theme="dark"] .theme-toggle-icon-sun{display:block}
[data-theme="dark"] .theme-toggle-icon-moon{display:none}

/* ═══════════════════════════════════════════════════════════════
   KEYFRAMES
═══════════════════════════════════════════════════════════════ */
@keyframes fadeSlideDown{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
@keyframes spin{to{transform:rotate(360deg)}}
@keyframes zoomIn{from{opacity:0;transform:scale(.85)}to{opacity:1;transform:scale(1)}}
@keyframes bounceIn{0%{transform:scale(0);opacity:0}60%{transform:scale(1.06)}100%{transform:scale(1);opacity:1}}

/* ═══════════════════════════════════════════════════════════════
   RESPONSIVE
═══════════════════════════════════════════════════════════════ */
@media(max-width:599px){
  .attendance-list{grid-template-columns:1fr}
  .stats-row{grid-template-columns:repeat(2,1fr)}
  .classes-grid{grid-template-columns:repeat(auto-fill,minmax(130px,1fr))}
  .hero-actions .btn{font-size:.72rem;padding:7px 10px}
  .save-btn{font-size:.7rem;padding: 10px}
}
@media(max-width:480px){
  .topbar{padding:0 12px}
  .fridays-grid{grid-template-columns:repeat(3,1fr)}
  .action-strip-btn{font-size:.75rem;padding:9px 6px}
}
@media(prefers-reduced-motion:reduce){*{animation-duration:.01ms!important;transition-duration:.01ms!important}}

/* ── All-students table: always normal table, never card view ── */
.all-students-table-container .data-table,
.all-students-table-container .data-table thead,
.all-students-table-container .data-table tbody,
.all-students-table-container .data-table th,
.all-students-table-container .data-table td,
.all-students-table-container .data-table tr {
    display: revert !important;
}
.all-students-table-container .data-table thead { display: table-header-group !important; }
.all-students-table-container .data-table tbody { display: table-row-group !important; }
.all-students-table-container .data-table tr { display: table-row !important; background: revert !important; border: none !important; border-radius: 0 !important; margin: 0 !important; padding: 0 !important; box-shadow: none !important; }
.all-students-table-container .data-table td { display: table-cell !important; border-bottom: 1px solid var(--border-solid) !important; padding: 7px 8px !important; font-size: .8rem !important; }
.all-students-table-container .data-table td::before { display: none !important; }
.all-students-table-container { overflow-x: auto; -webkit-overflow-scrolling: touch; }

/* ── Profile link name style ── */
.profile-link { color: var(--text); transition: color .15s; }
.profile-link:hover { color: var(--brand); text-decoration: underline; text-underline-offset: 3px; }

/* ── Hold-to-open animation — ripple overlay inside card ── */
.hold-ripple-overlay {
    position: absolute; inset: 0;
    background: var(--brand);
    opacity: 0;
    pointer-events: none;
    border-radius: inherit;
    animation: holdRippleAnim 0.7s ease forwards;
    z-index: 0;
}
@keyframes holdRippleAnim {
    0%   { opacity: 0;    transform: scale(.94); }
    50%  { opacity: .07;  transform: scale(.97); }
    100% { opacity: .13;  transform: scale(1); }
}

/* ── Today's Birthday Banner (homepage) ── */
#todayBirthdayBanner {
    display: none;
    background: linear-gradient(135deg, #fce7f3, #fdf4ff);
    border: 1.5px solid #db2777;
    border-radius: var(--r-xl);
    padding: 14px 16px;
    margin-bottom: 16px;
    box-shadow: 0 0 0 3px rgba(219,39,119,.1), var(--shadow-md);
    animation: fadeSlideDown .35s var(--spring);
}
[data-theme="dark"] #todayBirthdayBanner {
    background: linear-gradient(135deg,rgba(219,39,119,.15),rgba(139,92,246,.12));
    border-color: #db2777;
}
#todayBirthdayBanner.show { display: block; }
.bday-banner-header {
    display: flex; align-items: center; gap: 8px;
    font-size: .88rem; font-weight: 800; color: #9d174d;
    margin-bottom: 10px;
}
[data-theme="dark"] .bday-banner-header { color: #f9a8d4; }
.bday-banner-header i { font-size: 1.1rem; animation: bday-pulse 1.4s ease-in-out infinite; }
@keyframes bday-pulse { 0%,100%{transform:scale(1)} 50%{transform:scale(1.25) rotate(-8deg)} }
.bday-banner-list {
    display: flex; flex-wrap: wrap; gap: 8px;
}
.bday-banner-chip {
    display: inline-flex; align-items: center; gap: 6px;
    background: rgba(219,39,119,.12); color: #9d174d;
    border: 1px solid rgba(219,39,119,.3);
    padding: 5px 12px; border-radius: var(--r-full);
    font-size: .82rem; font-weight: 700; cursor: pointer;
    transition: all var(--t) var(--ease);
}
[data-theme="dark"] .bday-banner-chip { background: rgba(219,39,119,.2); color: #f9a8d4; border-color: rgba(219,39,119,.4); }
.bday-banner-chip:hover { background: #db2777; color: #fff; transform: translateY(-2px); }
.bday-banner-chip .bday-chip-class {
    font-size: .68rem; opacity: .75; font-weight: 600;
}

/* ── Birthday indicator on attendance row ── */
.attendance-item.bday-row {
    border-top: 3px solid #db2777 !important;
}
.bday-row-badge {
    display: inline-flex; align-items: center; gap: 3px;
    background: linear-gradient(135deg,#db2777,#9333ea);
    color: #fff; font-size: .58rem; font-weight: 800;
    padding: 2px 7px; border-radius: var(--r-full);
    animation: bday-pulse 1.8s ease-in-out infinite;
    white-space: nowrap;
}

/* ── Birthday card new style ── */
.birthday-card-new {
    background: var(--surface-3);
    border: 1.5px solid var(--border-solid);
    border-radius: var(--r-xl);
    padding: 14px;
    cursor: pointer;
    position: relative;
    overflow: hidden;
    transition: all var(--t) var(--spring);
    box-shadow: var(--shadow-sm);
}
.birthday-card-new:hover { transform: translateY(-3px); box-shadow: var(--shadow-md); border-color: var(--brand); }
.birthday-card-new.bday-today {
    background: linear-gradient(135deg, #fce7f3, #fdf4ff);
    border-color: #db2777;
    box-shadow: 0 0 0 3px rgba(219,39,119,.15), var(--shadow-md);
}
[data-theme="dark"] .birthday-card-new.bday-today { background: linear-gradient(135deg,rgba(219,39,119,.15),rgba(139,92,246,.1)); }
.bday-today-badge {
    position: absolute; top: 8px; left: 8px;
    background: linear-gradient(135deg,#db2777,#9333ea);
    color: #fff; font-size: .68rem; font-weight: 800;
    padding: 2px 8px; border-radius: var(--r-full);
    animation: bounceIn .4s var(--spring);
}
.birthday-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 10px; }

/* ── PWA offline banner ── */
#offlineBanner {
    position: fixed; bottom: 0; left: 0; right: 0;
    background: linear-gradient(135deg, var(--warning), var(--warning-dark));
    color: #fff; text-align: center; padding: 10px 16px;
    font-size: .82rem; font-weight: 700; z-index: 9999998;
    display: none; align-items: center; justify-content: center; gap: 10px;
    animation: slideUp .3s var(--spring);
}
#offlineBanner.show { display: flex; }
@keyframes slideUp { from{transform:translateY(100%)} to{transform:translateY(0)} }

/* ── PWA install modal ── */
#pwaInstallModal {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.5); backdrop-filter: blur(8px);
    z-index: 9999997; display: none; align-items: flex-end; justify-content: center;
}
#pwaInstallModal.show { display: flex; }
.pwa-install-sheet {
    background: var(--surface);
    border-radius: var(--r-2xl) var(--r-2xl) 0 0;
    padding: 24px 20px 36px;
    width: 100%; max-width: 480px;
    box-shadow: 0 -8px 40px rgba(0,0,0,.25);
    animation: sheetUp .35s var(--spring);
    text-align: center;
}
.pwa-icon-big {
    width: 84px; height: 84px; border-radius: 22px;
    overflow: hidden;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px;
    background: linear-gradient(135deg,#5b6cf5,#8b5cf6);
    box-shadow:
        0 0 0 6px rgba(181,190,248,.22),
        0 0 0 12px rgba(181,190,248,.08),
        0 8px 28px rgba(181,190,248,.50);
    animation: pwaIconPulse 3s ease-in-out infinite;
}
@keyframes pwaIconPulse {
    0%,100% { box-shadow: 0 0 0 6px rgba(181,190,248,.25), 0 0 0 12px rgba(181,190,248,.10), 0 8px 28px rgba(181,190,248,.45); }
    50%      { box-shadow: 0 0 0 9px rgba(181,190,248,.30), 0 0 0 18px rgba(181,190,248,.08), 0 8px 36px rgba(181,190,248,.60); }
}
.pwa-steps {
    background: var(--surface-3); border-radius: var(--r-lg);
    padding: 14px; margin: 16px 0; text-align: right;
}
.pwa-step {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 6px 0; font-size: .84rem; color: var(--text-2);
}
.pwa-step-num {
    width: 22px; height: 22px; border-radius: 50%;
    background: var(--brand); color: #fff;
    display: flex; align-items: center; justify-content: center;
    font-size: .7rem; font-weight: 800; flex-shrink: 0; margin-top: 1px;
}

/* ══ SWIPE MODE ══════════════════════════════════════════════ */

/* Swipe trigger button in toolbar */
.swipe-toolbar-btn {
    background: linear-gradient(135deg,#8b5cf6,#7c3aed) !important;
    color:#fff !important;
    border:none !important;
    box-shadow:
        0 0 0   0   rgba(139,92,246,0),
        0 2px 8px rgba(124,58,237,.4);
    animation: swipeBtnGlow 5.5s ease-in-out infinite;
    position: relative;
    overflow: visible !important;
}
@keyframes swipeBtnGlow {
    0%,  60%, 100% { box-shadow: 0 2px 8px rgba(124,58,237,.35); }
    70%            { box-shadow: 0 0 0 5px rgba(139,92,246,.22), 0 2px 16px rgba(124,58,237,.6); }
    80%            { box-shadow: 0 0 0 9px rgba(139,92,246,.08), 0 2px 22px rgba(124,58,237,.5); }
    90%            { box-shadow: 0 0 0 3px rgba(139,92,246,.15), 0 2px 12px rgba(124,58,237,.45); }
}
.swipe-toolbar-btn:hover { opacity:.92; transform:translateY(-1px) scale(1.04); }

/* Swipe hand icon — animate then rest */
.swipe-hand-icon {
    display: inline-block;
    animation: swipeHandAnim 5.5s ease-in-out infinite;
}
@keyframes swipeHandAnim {
    /* rest for ~4.5s, animate over ~1s */
    0%,  55%       { transform: translateX(0) rotate(0deg); opacity: 1; }
    65%            { transform: translateX(12px) rotate(-8deg); opacity: .7; }
    75%            { transform: translateX(-10px) rotate(6deg); opacity: .85; }
    85%            { transform: translateX(6px) rotate(-4deg); opacity: .9; }
    100%           { transform: translateX(0) rotate(0deg); opacity: 1; }
}
/* keep old class for compat */
.swipe-mode-btn { display:none !important; }

/* ── Overlay ── */
#swipeOverlay {
    display:none;
    position:fixed; inset:0;
    z-index:10000000;
    background:var(--surface);
    flex-direction:column;
    align-items:stretch;
    overflow:hidden;
}
#swipeOverlay.active {
    display:flex;
    animation:swipeOverlayIn .28s cubic-bezier(.16,1,.3,1);
}
@keyframes swipeOverlayIn {
    from { opacity:0; transform:scale(.97) }
    to   { opacity:1; transform:scale(1) }
}

/* ── Header ── */
.swipe-header {
    display:flex; align-items:center; gap:12px;
    padding:max(env(safe-area-inset-top,0px),14px) 16px 12px;
    background:var(--surface);
    border-bottom:1px solid var(--border-solid);
    flex-shrink:0;
}
.swipe-exit-btn {
    width:36px; height:36px; border-radius:50%;
    background:var(--surface-3); border:1.5px solid var(--border-solid);
    color:var(--text-2); cursor:pointer;
    display:flex; align-items:center; justify-content:center;
    font-size:.95rem; transition:all .18s; flex-shrink:0;
}
.swipe-exit-btn:hover { background:var(--danger-bg); color:var(--danger); border-color:var(--danger); }
.swipe-progress-wrap {
    flex:1; display:flex; flex-direction:column;
    align-items:center; gap:5px; min-width:0;
}
.swipe-class-label {
    font-size:.72rem; font-weight:800; color:var(--brand);
    font-family:Cairo,sans-serif; letter-spacing:.02em;
}
.swipe-progress-bar {
    width:100%; max-width:200px; height:4px;
    background:var(--surface-3); border-radius:var(--r-full); overflow:hidden;
}
.swipe-progress-fill {
    height:100%;
    background:linear-gradient(90deg,var(--brand),var(--coupon));
    border-radius:var(--r-full);
    transition:width .45s cubic-bezier(.16,1,.3,1);
}
.swipe-counter {
    font-size:.68rem; color:var(--text-3); font-weight:700;
    font-family:Cairo,sans-serif;
}
.swipe-score-row { display:flex; gap:6px; flex-shrink:0; }
.swipe-score {
    display:flex; align-items:center; gap:3px;
    padding:5px 10px; border-radius:var(--r-full);
    font-family:Cairo,sans-serif; font-size:.78rem; font-weight:800;
    border:1.5px solid;
}
.swipe-score.pres { background:var(--success-bg); color:var(--success-dark); border-color:rgba(16,185,129,.2); }
.swipe-score.abs  { background:var(--danger-bg);  color:var(--danger-dark);  border-color:rgba(239,68,68,.2); }

/* ── Card area — takes all space between header and buttons ── */
.swipe-card-area {
    flex:1; display:flex; align-items:center; justify-content:center;
    padding:16px 20px 12px; overflow:hidden; position:relative;
    background:var(--bg);
}

/* Subtle left/right swipe hint overlays in card area */
.swipe-hints {
    position:absolute; inset:0;
    display:flex; justify-content:space-between; align-items:center;
    padding:0 20px; pointer-events:none; z-index:1;
}
.swipe-hint-left, .swipe-hint-right {
    display:flex; flex-direction:column; align-items:center; gap:4px;
    font-family:Cairo,sans-serif; font-size:.72rem; font-weight:800;
    opacity:.18; transition:opacity .15s;
}
.swipe-hint-left  { color:var(--danger); }
.swipe-hint-right { color:var(--success); }
.swipe-hint-left  i,
.swipe-hint-right i { font-size:1.4rem; }

/* ── The Card ── */
.swipe-card {
    width:min(340px, calc(100vw - 32px));
    height:min(520px, calc(100dvh - 230px));
    border-radius:24px;
    overflow:hidden;
    background:var(--surface);
    box-shadow:
        0 0 0 1px var(--border-solid),
        0 4px 16px rgba(0,0,0,.07),
        0 16px 48px rgba(0,0,0,.12);
    display:flex; flex-direction:column;
    position:relative;
    cursor:grab;
    will-change:transform;
    touch-action:none;
    z-index:2;
    transition:box-shadow .2s;
}
.swipe-card.dragging {
    cursor:grabbing;
    transition:none !important;
    box-shadow:
        0 0 0 1px var(--border-solid),
        0 24px 80px rgba(0,0,0,.2);
}

/* Photo — fills upper ~60% of card */
.swipe-card-photo {
    width:100%; flex:1;
    object-fit:cover; object-position:center top;
    display:block; pointer-events:none; min-height:0;
}
.swipe-card-photo-placeholder {
    width:100%; flex:1; min-height:0;
    background:linear-gradient(145deg,var(--brand-bg),var(--coupon-bg));
    display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px;
}
.swipe-card-photo-placeholder i    { font-size:4rem; color:var(--brand); opacity:.25; }
.swipe-card-photo-placeholder span { font-size:.78rem; color:var(--text-3); font-family:Cairo,sans-serif; }

/* Stamp labels — appear on photo as you drag */
.swipe-stamp {
    position:absolute; top:18px;
    display:flex; align-items:center; gap:6px;
    font-size:1.4rem; font-weight:900;
    font-family:'Cairo', sans-serif;
    opacity:0; pointer-events:none;
    letter-spacing:.03em;
    border:none; background:none; padding:0;
}
.swipe-stamp.present-stamp { left:18px;  color:#059669; transform:rotate(-10deg); }
.swipe-stamp.absent-stamp  { right:18px; color:#dc2626; transform:rotate(10deg); }
[data-theme="dark"] .swipe-stamp.present-stamp { color:#34d399; }
[data-theme="dark"] .swipe-stamp.absent-stamp  { color:#f87171; }
.swipe-stamp i { font-size:1.6rem; }

/* Photo colour wash while dragging */
.swipe-card-wash {
    position:absolute; left:0; right:0; top:0; bottom:120px;
    pointer-events:none; opacity:0; border-radius:24px 24px 0 0;
}

/* Info section at bottom of card */
.swipe-card-info {
    flex-shrink:0; padding:14px 16px 16px;
    background:var(--surface);
    border-top:1px solid var(--border-solid);
}
.swipe-info-row1 {
    display:flex; align-items:center; justify-content:space-between;
    gap:8px; margin-bottom:8px;
}
.swipe-card-name {
    font-size:1.2rem; font-weight:900; color:var(--text);
    font-family:Cairo,sans-serif; line-height:1.15; flex:1; min-width:0;
    overflow:hidden; text-overflow:ellipsis; white-space:nowrap;
}
.swipe-coupon-pill {
    display:flex; align-items:center; gap:4px;
    background:var(--coupon-bg); color:var(--coupon-dark);
    border:1.5px solid rgba(139,92,246,.18);
    padding:4px 10px; border-radius:var(--r-full);
    font-size:.78rem; font-weight:800; font-family:Cairo,sans-serif; flex-shrink:0;
}
.swipe-info-row2 {
    display:flex; align-items:center; gap:6px; flex-wrap:wrap;
}
.swipe-class-pill {
    background:var(--brand-bg); color:var(--brand);
    padding:3px 10px; border-radius:var(--r-full);
    font-size:.7rem; font-weight:700; font-family:Cairo,sans-serif;
    border:1px solid rgba(91,108,245,.15);
}
.swipe-prev-pill {
    display:inline-flex; align-items:center; gap:4px;
    padding:3px 10px; border-radius:var(--r-full);
    font-size:.7rem; font-weight:700; font-family:Cairo,sans-serif;
    border:1px solid;
}
.swipe-prev-pill.present { background:var(--success-bg); color:var(--success-dark); border-color:rgba(16,185,129,.2); }
.swipe-prev-pill.absent  { background:var(--danger-bg);  color:var(--danger-dark);  border-color:rgba(239,68,68,.2); }
.swipe-prev-pill.pending { background:var(--surface-3);  color:var(--text-3);       border-color:var(--border-solid); }
.swipe-remaining-pill {
    background:var(--surface-3); color:var(--text-3);
    padding:3px 8px; border-radius:var(--r-full);
    font-size:.68rem; font-weight:700; font-family:Cairo,sans-serif;
    margin-right:auto;
}

/* ── Bottom action bar ── */
.swipe-btns {
    flex-shrink:0;
    display:flex; align-items:center; justify-content:center; gap:12px;
    padding:12px 20px max(env(safe-area-inset-bottom,14px),14px);
    background:var(--surface);
    border-top:1px solid var(--border-solid);
}

/* Absent / Present — styled pill buttons */
.swipe-absent-btn, .swipe-present-btn {
    flex:1; height:54px; max-width:160px;
    border-radius:var(--r-xl); border:none; cursor:pointer;
    display:flex; align-items:center; justify-content:center; gap:8px;
    font-family:Cairo,sans-serif; font-weight:800; font-size:.92rem;
    box-shadow:none;
    transition:all .2s cubic-bezier(.16,1,.3,1);
}
.swipe-absent-btn:active, .swipe-present-btn:active { transform:scale(.93) !important; }
.swipe-absent-btn  { background:var(--danger-bg); color:var(--danger-dark); }
.swipe-absent-btn:hover  { background:var(--danger); color:#fff; transform:translateY(-2px); box-shadow:0 6px 18px rgba(239,68,68,.3); }
.swipe-present-btn { background:var(--success-bg); color:var(--success-dark); }
.swipe-present-btn:hover { background:var(--success); color:#fff; transform:translateY(-2px); box-shadow:0 6px 18px rgba(16,185,129,.3); }

/* Skip — muted pill */
.swipe-skip-btn {
    flex-shrink:0; padding:0 18px; height:50px;
    border-radius:var(--r-xl); border:none;
    background:var(--surface-3); color:var(--text-3);
    cursor:pointer; display:flex; align-items:center; justify-content:center;
    font-family:'Cairo',sans-serif; font-size:.82rem; font-weight:700;
    transition:all .18s;
}
.swipe-skip-btn:hover { background:var(--warning-bg); color:var(--warning-dark); transform:translateY(-2px); }


/* ── Done screen ── */
.swipe-done {
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    flex:1; padding:24px; text-align:center; font-family:Cairo,sans-serif;
}
.swipe-done-icon {
    width:80px; height:80px; border-radius:50%;
    background:linear-gradient(135deg,var(--success),var(--success-dark));
    display:flex; align-items:center; justify-content:center;
    font-size:2.2rem; color:#fff;
    box-shadow:0 0 0 14px var(--success-bg), var(--shadow-md);
    margin-bottom:18px; animation:bounceIn .55s var(--spring);
}
.swipe-done h2 { font-size:1.35rem; font-weight:900; color:var(--text); margin-bottom:6px; }
.swipe-done p  { font-size:.86rem; color:var(--text-3); margin-bottom:22px; }
.swipe-done-stats { display:flex; gap:10px; justify-content:center; margin-bottom:22px; flex-wrap:wrap; width:100%; }
.swipe-done-stat {
    flex:1; min-width:80px; max-width:110px;
    background:var(--surface-3); border:1.5px solid var(--border-solid);
    border-radius:var(--r-xl); padding:14px 10px; text-align:center;
}
.swipe-done-stat-val { font-size:1.8rem; font-weight:900; display:block; line-height:1.1; }
.swipe-done-stat-lbl { font-size:.7rem; color:var(--text-3); margin-top:4px; display:block; font-weight:600; }

/* ── Overlay shell ── */
#swipeOverlay {
    display:none; position:fixed; inset:0;
    background:var(--bg);
    z-index:10000000; flex-direction:column; align-items:center; overflow:hidden;
}
#swipeOverlay.active { display:flex; animation:fadeIn .22s ease; }
@keyframes fadeIn { from{opacity:0} to{opacity:1} }

/* ── Top bar ── */
.swipe-header {
    width:100%; display:flex; align-items:center; justify-content:space-between;
    padding:max(env(safe-area-inset-top,0px),14px) 18px 12px;
    gap:12px; flex-shrink:0;
    background:var(--surface);
    border-bottom:1px solid var(--border-solid);
    box-shadow:var(--shadow-sm);
}
.swipe-exit-btn {
    background:var(--surface-3); border:1.5px solid var(--border-solid);
    color:var(--text-2); padding:8px 16px; border-radius:var(--r-full);
    cursor:pointer; font-family:Cairo,sans-serif; font-size:.8rem; font-weight:700;
    display:flex; align-items:center; gap:6px; white-space:nowrap; transition:all .18s;
}
.swipe-exit-btn:hover { background:var(--danger-bg); color:var(--danger); border-color:var(--danger); }
.swipe-progress-wrap { flex:1; display:flex; flex-direction:column; align-items:center; gap:5px; min-width:0; }
.swipe-progress-bar {
    width:100%; max-width:160px; height:6px;
    background:var(--surface-3); border-radius:var(--r-full); overflow:hidden;
    border:1px solid var(--border-solid);
}
.swipe-progress-fill {
    height:100%; background:linear-gradient(90deg,var(--brand),var(--coupon));
    border-radius:var(--r-full); transition:width .4s var(--spring);
}
.swipe-counter {
    font-size:.72rem; color:var(--text-3); font-weight:700; font-family:Cairo,sans-serif;
}
.swipe-score-row { display:flex; gap:8px; align-items:center; flex-shrink:0; }
.swipe-score {
    display:flex; align-items:center; gap:4px;
    font-family:Cairo,sans-serif; font-size:.82rem; font-weight:800;
    padding:4px 10px; border-radius:var(--r-full);
}
.swipe-score.pres { background:var(--success-bg); color:var(--success-dark); }
.swipe-score.abs  { background:var(--danger-bg);  color:var(--danger-dark); }

/* ── Card area ── */
.swipe-card-area {
    flex:1; display:flex; align-items:center; justify-content:center;
    width:100%; position:relative; overflow:hidden; padding:8px 20px 0;
}

/* ── THE CARD ── */
.swipe-card {
    width:min(360px,90vw);
    max-height:calc(100dvh - 230px);
    background:var(--surface);
    border-radius:28px;
    overflow:hidden;
    box-shadow:
        0 0 0 1.5px var(--border-solid),
        0 8px 24px rgba(0,0,0,.08),
        0 20px 60px rgba(0,0,0,.13);
    display:flex; flex-direction:column;
    position:relative; cursor:grab; user-select:none;
    will-change:transform; touch-action:none;
    transition:box-shadow .2s;
}
.swipe-card.dragging { cursor:grabbing; transition:none !important; box-shadow:0 20px 70px rgba(0,0,0,.22); }

/* ── Pre-assigned attendance state — mirrors attendance list card ── */
.swipe-card.pre-present {
    box-shadow:
        0 0 0 2.5px var(--success),
        0 8px 24px rgba(16,185,129,.2),
        0 20px 60px rgba(0,0,0,.1);
}
.swipe-card.pre-absent {
    box-shadow:
        0 0 0 2.5px var(--danger),
        0 8px 24px rgba(239,68,68,.2),
        0 20px 60px rgba(0,0,0,.1);
}
.swipe-card.pre-present .swipe-card-info {
    background:linear-gradient(135deg,rgba(236,253,245,.98),rgba(209,250,229,.92));
    border-top-color:rgba(16,185,129,.3);
}
.swipe-card.pre-absent .swipe-card-info {
    background:linear-gradient(135deg,rgba(254,242,242,.98),rgba(254,226,226,.92));
    border-top-color:rgba(239,68,68,.3);
}
[data-theme="dark"] .swipe-card.pre-present .swipe-card-info {
    background:linear-gradient(135deg,rgba(6,78,59,.35),rgba(5,95,75,.28));
}
[data-theme="dark"] .swipe-card.pre-absent .swipe-card-info {
    background:linear-gradient(135deg,rgba(127,29,29,.35),rgba(153,27,27,.28));
}

/* Photo — tall, fills top */
.swipe-card-photo {
    width:100%; flex:1; min-height:180px;
    object-fit:cover; object-position:center top;
    display:block; pointer-events:none;
}
.swipe-card-photo-placeholder {
    width:100%; flex:1; min-height:180px;
    background:linear-gradient(145deg,var(--brand-bg),var(--coupon-bg));
    display:flex; align-items:center; justify-content:center; flex-direction:column; gap:10px;
}
.swipe-card-photo-placeholder i { font-size:4.5rem; color:var(--brand); opacity:.3; }
.swipe-card-photo-placeholder span { font-size:.8rem; color:var(--text-3); font-family:Cairo,sans-serif; }

/* Info panel at bottom of card */
.swipe-card-info {
    padding:16px 18px 18px; background:var(--surface);
    border-top:1.5px solid var(--border-solid);
    flex-shrink:0;
}
.swipe-card-top-row {
    display:flex; align-items:flex-start; justify-content:space-between; margin-bottom:8px;
}
.swipe-card-name {
    font-size:1.25rem; font-weight:900; color:var(--text);
    font-family:Cairo,sans-serif; line-height:1.2;
}
.swipe-card-coupon-badge {
    display:flex; align-items:center; gap:4px;
    background:var(--coupon-bg); color:var(--coupon-dark);
    border:1.5px solid rgba(139,92,246,.2);
    padding:5px 10px; border-radius:var(--r-full);
    font-size:.8rem; font-weight:800; font-family:Cairo,sans-serif;
    flex-shrink:0; white-space:nowrap;
}
.swipe-card-meta {
    display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:10px;
}
.swipe-card-class-tag {
    background:var(--brand-bg); color:var(--brand);
    padding:3px 10px; border-radius:var(--r-full);
    font-size:.72rem; font-weight:700; font-family:Cairo,sans-serif;
}
.swipe-card-prev-status {
    display:inline-flex; align-items:center; gap:4px;
    padding:3px 10px; border-radius:var(--r-full);
    font-size:.72rem; font-weight:700; font-family:Cairo,sans-serif;
    border:1.5px solid;
}
.swipe-card-prev-status.present { background:var(--success-bg); color:var(--success-dark); border-color:rgba(16,185,129,.25); }
.swipe-card-prev-status.absent  { background:var(--danger-bg);  color:var(--danger-dark);  border-color:rgba(239,68,68,.25); }
.swipe-card-prev-status.pending { background:var(--surface-3);  color:var(--text-3);       border-color:var(--border-solid); }

/* ── STAMP labels — bare handwritten text, no box ── */
.swipe-card-label {
    position:absolute; top:18px;
    display:flex; align-items:center; gap:6px;
    font-size:1.4rem; font-weight:900;
    font-family:'Cairo', sans-serif;
    opacity:0; pointer-events:none;
    letter-spacing:.03em;
    border:none; background:none; padding:0;
}
.swipe-card-label i { font-size:1.6rem; }
.swipe-card-label.present-lbl { left:18px;  color:#059669; transform:rotate(-10deg); }
.swipe-card-label.absent-lbl  { right:18px; color:#dc2626; transform:rotate(10deg); }
[data-theme="dark"] .swipe-card-label.present-lbl { color:#34d399; }
[data-theme="dark"] .swipe-card-label.absent-lbl  { color:#f87171; }

/* Colour wash over photo during drag */
.swipe-card-tint {
    position:absolute; left:0; right:0; top:0; bottom:60px;
    pointer-events:none; opacity:0; border-radius:28px 28px 0 0;
}

/* ── Action buttons — BELOW card, above safe area ── */
.swipe-btns {
    width:100%; flex-shrink:0;
    display:flex; align-items:center; justify-content:center;
    gap:16px; padding:14px 24px max(env(safe-area-inset-bottom,16px),16px);
    background:var(--surface);
    border-top:1px solid var(--border-solid);
}

/* Absent — left, large */
.swipe-action-btn {
    border-radius:var(--r-xl); border:none; cursor:pointer;
    display:flex; align-items:center; justify-content:center; gap:6px;
    font-family:Cairo,sans-serif; font-weight:800; font-size:.9rem;
    transition:all .2s var(--spring); flex-shrink:0;
}
.swipe-action-btn:active { transform:scale(.93) !important; }

.absent-action {
    height:54px; padding:0 24px; flex:1;
    background:var(--danger-bg); color:var(--danger-dark);
    border:none; box-shadow:none;
}
.absent-action:hover { background:var(--danger); color:#fff; box-shadow:0 6px 18px rgba(239,68,68,.3); transform:translateY(-2px); }

.present-action {
    height:54px; padding:0 24px; flex:1;
    background:var(--success-bg); color:var(--success-dark);
    border:none; box-shadow:none;
}
.present-action:hover { background:var(--success); color:#fff; box-shadow:0 6px 18px rgba(16,185,129,.3); transform:translateY(-2px); }

/* Skip — centre, muted pill */
.swipe-skip-btn {
    height:54px; flex-shrink:0; padding:0 18px;
    background:var(--surface-3); border:none;
    color:var(--text-3); border-radius:var(--r-xl); cursor:pointer;
    display:flex; align-items:center; justify-content:center;
    font-family:Cairo,sans-serif; font-size:.82rem; font-weight:700;
    transition:all .18s;
}
.swipe-skip-btn:hover { background:var(--warning-bg); color:var(--warning-dark); transform:translateY(-2px); }

/* ── Done screen ── */
.swipe-done {
    display:flex; flex-direction:column; align-items:center; justify-content:center;
    flex:1; padding:28px 24px; text-align:center; font-family:Cairo,sans-serif;
    width:100%;
}
.swipe-done-icon {
    width:84px; height:84px; border-radius:50%;
    background:linear-gradient(135deg,var(--success),var(--success-dark));
    display:flex; align-items:center; justify-content:center;
    font-size:2.4rem; color:#fff;
    box-shadow:0 0 0 12px var(--success-bg), var(--shadow-lg);
    margin-bottom:18px; animation:bounceIn .55s var(--spring);
}
.swipe-done h2 { font-size:1.4rem; font-weight:900; color:var(--text); margin-bottom:6px; }
.swipe-done p  { font-size:.88rem; color:var(--text-3); margin-bottom:22px; max-width:280px; line-height:1.5; }
.swipe-done-stats {
    display:flex; gap:12px; justify-content:center; margin-bottom:24px; flex-wrap:wrap; width:100%;
}
.swipe-done-stat {
    background:var(--surface-3); border:1.5px solid var(--border-solid);
    border-radius:var(--r-xl); padding:14px 20px; text-align:center;
    flex:1; min-width:80px; max-width:110px;
}
.swipe-done-stat-val { font-size:1.9rem; font-weight:900; display:block; line-height:1.1; }
.swipe-done-stat-lbl { font-size:.72rem; color:var(--text-3); margin-top:4px; display:block; font-weight:600; }

/* ════ UNIFIED NOTIFICATION PANEL ═══════════════════════════════ */
.tasks-notif-dot{
  position:absolute;top:-6px;right:-6px;min-width:18px;height:18px;
  border-radius:9px;background:#ef4444;border:2.5px solid white;
  font-size:.58rem;font-weight:800;color:#fff;
  display:flex;align-items:center;justify-content:center;padding:0 3px;
  animation:pulse-badge .9s ease-in-out infinite alternate;pointer-events:none;
}
@keyframes pulse-badge{from{transform:scale(1);}to{transform:scale(1.18);}}

.notif-panel-overlay{
  position:fixed;inset:0;z-index:9997;
  display:none;
}
.notif-panel-overlay.open{display:block;}
.notif-panel-bg{
  position:absolute;inset:0;
  background:rgba(0,0,0,.35);backdrop-filter:blur(6px);
}
.notif-panel-sheet{
  position:absolute;top:0;right:0;
  width:min(420px,100%);height:100%;
  background:var(--bg);
  box-shadow:-12px 0 60px rgba(0,0,0,.18);
  display:flex;flex-direction:column;
  animation:npsSlideIn .3s cubic-bezier(.16,1,.3,1);
  z-index:1;
  border-left:1px solid var(--border-solid);
}
@keyframes npsSlideIn{from{transform:translateX(110%);}to{transform:translateX(0);}}

/* ── Header ── */
.nps-header{
  padding:20px 18px 16px;
  background:var(--surface);
  border-bottom:1.5px solid var(--border-solid);
  display:flex;align-items:center;gap:12px;flex-shrink:0;
}
.nps-header-icon{
  width:42px;height:42px;border-radius:var(--r-md);
  background:var(--brand-bg);color:var(--brand);
  display:flex;align-items:center;justify-content:center;
  font-size:1.1rem;flex-shrink:0;
}
.nps-title{flex:1;font-size:1.05rem;font-weight:800;color:var(--text);}
.nps-title small{display:block;font-size:.68rem;font-weight:500;color:var(--text-3);margin-top:1px;}
.nps-mark-all{
  background:var(--brand-bg);border:1.5px solid rgba(91,108,245,.25);
  color:var(--brand);padding:7px 12px;border-radius:var(--r-md);
  font-size:.75rem;font-weight:700;cursor:pointer;font-family:inherit;
  display:flex;align-items:center;gap:5px;
  transition:all var(--t) var(--ease);white-space:nowrap;
}
.nps-mark-all:hover{background:var(--brand);color:#fff;border-color:var(--brand);}
.nps-close{
  width:36px;height:36px;border-radius:var(--r-md);
  background:var(--surface-3);border:1.5px solid var(--border-solid);
  color:var(--text-3);cursor:pointer;font-size:.9rem;
  display:flex;align-items:center;justify-content:center;
  transition:all var(--t) var(--ease);
}
.nps-close:hover{background:var(--danger-bg);color:var(--danger);border-color:var(--danger);}

/* ── Body ── */
.nps-body{
  flex:1;overflow-y:auto;
  padding:12px 14px;
  display:flex;flex-direction:column;gap:8px;
  background:var(--bg);
}

/* ── Notification Item ── */
.nps-item{
  padding:14px 14px 12px;
  border-radius:var(--r-lg);
  background:var(--surface);
  border:1.5px solid var(--border-solid);
  cursor:pointer;
  transition:all .18s var(--ease);
  position:relative;
}
.nps-item::before{
  content:'';position:absolute;top:0;right:0;width:3px;height:100%;
  background:transparent;border-radius:0 var(--r-lg) var(--r-lg) 0;
  transition:background var(--t) var(--ease);
}
.nps-item:hover{
  background:var(--surface);
  border-color:var(--brand);
  box-shadow:0 4px 16px var(--brand-glow);
  transform:translateY(-1px);
}
.nps-item.unread{
  background:var(--surface);
  border-color:rgba(91,108,245,.3);
  box-shadow:0 2px 10px rgba(91,108,245,.08);
}
.nps-item.unread::before{background:var(--brand);}

/* ── Type-specific icon styles ── */
.nps-icon{
  width:40px;height:40px;border-radius:var(--r-md);
  display:flex;align-items:center;justify-content:center;
  font-size:1rem;flex-shrink:0;
}
.nps-icon.type-registration { background:#dbeafe;color:#1d4ed8; }
.nps-icon.type-task_submission { background:#fef3c7;color:#b45309; }
.nps-icon.type-developer_message { background:#ede9fe;color:#6d28d9; }
.nps-icon.type-system { background:#f0fdf4;color:#166534; }
.nps-icon.type-announcement { background:#fff7ed;color:#c2410c; }
.nps-icon.type-default { background:var(--brand-bg);color:var(--brand); }
[data-theme="dark"] .nps-icon.type-registration { background:rgba(29,78,216,.2);color:#93c5fd; }
[data-theme="dark"] .nps-icon.type-task_submission { background:rgba(180,83,9,.2);color:#fcd34d; }
[data-theme="dark"] .nps-icon.type-developer_message { background:rgba(109,40,217,.2);color:#c4b5fd; }
[data-theme="dark"] .nps-icon.type-system { background:rgba(22,101,52,.2);color:#86efac; }
[data-theme="dark"] .nps-icon.type-announcement { background:rgba(194,65,12,.2);color:#fdba74; }

.nps-item-title{
  font-size:.88rem;font-weight:700;color:var(--text);
  line-height:1.4;margin-bottom:3px;
}
.nps-item-body{
  font-size:.78rem;color:var(--text-2);
  margin-top:4px;line-height:1.55;
}
.nps-item-footer{
  display:flex;align-items:center;justify-content:space-between;
  margin-top:8px;gap:8px;
}
.nps-item-time{
  font-size:.7rem;color:var(--text-3);
  display:flex;align-items:center;gap:4px;
}
.nps-item-time i{font-size:.65rem;}
.nps-item-action{
  font-size:.72rem;color:var(--brand);font-weight:700;
  background:var(--brand-bg);padding:4px 10px;
  border-radius:var(--r-full);
  display:flex;align-items:center;gap:4px;
  transition:all var(--t) var(--ease);
}
.nps-item:hover .nps-item-action{background:var(--brand);color:#fff;}
.nps-delete-btn{
  background:none;border:none;
  color:var(--text-3);cursor:pointer;
  padding:4px 6px;border-radius:var(--r-sm);
  transition:all var(--t) var(--ease);
  flex-shrink:0;
}
.nps-delete-btn:hover{background:var(--danger-bg);color:var(--danger);}

/* ── Unread dot ── */
.nps-unread-dot{
  width:8px;height:8px;border-radius:50%;
  background:var(--brand);flex-shrink:0;
  animation:pulse-badge .9s ease-in-out infinite alternate;
}

/* ── Empty state ── */
.nps-empty{
  text-align:center;padding:60px 20px;
  color:var(--text-3);font-size:.88rem;
  display:flex;flex-direction:column;align-items:center;gap:10px;
}
.nps-empty-icon{
  width:64px;height:64px;border-radius:50%;
  background:var(--surface-3);
  display:flex;align-items:center;justify-content:center;
  font-size:1.6rem;color:var(--text-3);margin-bottom:6px;
}
</style>
</head>
<body>

<!-- OFFLINE BANNER -->

<!-- ══ UNIFIED NOTIFICATION PANEL ══ -->
<div class="notif-panel-overlay" id="notifPanelOverlay">
  <div class="notif-panel-bg" onclick="toggleNotifPanel()"></div>
  <div class="notif-panel-sheet">
    <div class="nps-header">
      <div class="nps-header-icon"><i class="fas fa-bell"></i></div>
      <div class="nps-title">الإشعارات<small>آخر التحديثات والتنبيهات</small></div>
      <button onclick="markAllNotifsRead()" class="nps-mark-all" title="تعليم الكل كمقروء"><i class="fas fa-check-double"></i> الكل مقروء</button>
      <button onclick="toggleNotifPanel()" class="nps-close" title="إغلاق"><i class="fas fa-times"></i></button>
    </div>
    <div class="nps-body" id="notifPanelList">
      <div class="nps-empty"><i class="fas fa-bell-slash" style="font-size:1.8rem;display:block;margin-bottom:8px;opacity:.4;"></i>لا توجد إشعارات</div>
    </div>
  </div>
</div>

<div id="offlineBanner">
  <i class="fas fa-wifi-slash"></i>
  <span>أنت غير متصل بالإنترنت — التغييرات محفوظة محلياً وستُرفع عند الاتصال</span>
  <button id="offlineNotifBtn" onclick="requestNotifPermission()" title="تفعيل الإشعارات"
    style="display:none;background:rgba(255,255,255,.25);border:1.5px solid rgba(255,255,255,.5);color:#fff;padding:4px 10px;border-radius:20px;font-size:.72rem;font-weight:700;font-family:Cairo,sans-serif;cursor:pointer;white-space:nowrap;gap:5px;align-items:center;flex-shrink:0">
    <i class="fas fa-bell"></i> إشعارات
  </button>
  <button onclick="document.getElementById('offlineBanner').classList.remove('show')" style="background:rgba(255,255,255,.2);border:none;color:#fff;width:24px;height:24px;border-radius:50%;cursor:pointer;font-size:.8rem;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fas fa-times"></i></button>
</div>

<!-- PWA INSTALL MODAL -->
<div id="pwaInstallModal" onclick="if(event.target===this)closePwaModal()">
  <div class="pwa-install-sheet">
    <div class="pwa-icon-big">
      <img src="/logo.png" alt="مدارس الأحد" style="width:84px;height:84px;object-fit:cover;display:block" onerror="this.outerHTML='<i class=\\'fas fa-cross\\'style=\\'font-size:2rem;color:#fff\\'></i>'">
    </div>
    <h3 style="font-size:1.1rem;font-weight:800;color:var(--text);margin-bottom:6px">تثبيت التطبيق</h3>
    <p style="color:var(--text-3);font-size:.84rem;margin-bottom:4px">ثبّت نظام مدارس الأحد على شاشتك الرئيسية للوصول السريع والعمل بدون إنترنت</p>
    <div class="pwa-steps" id="pwaSteps">
      <!-- filled by JS based on OS -->
    </div>
    <div style="display:flex;flex-direction:column;gap:8px;margin-top:4px">
      <button class="btn" id="pwaInstallNowBtn" onclick="doPwaInstall()" style="width:100%;justify-content:center"><i class="fas fa-download"></i> تثبيت الآن</button>
      <button class="btn btn-secondary" id="pwaNotifBtn" onclick="requestNotifPermission()" style="width:100%;justify-content:center;display:none"><i class="fas fa-bell"></i> السماح بالإشعارات</button>
      <button class="btn btn-ghost" onclick="closePwaModal()" style="width:100%;justify-content:center">ليس الآن</button>
    </div>
  </div>
</div>

<!-- TOAST -->
<div id="toastStack"></div>
<div id="toast" style="display:none"></div><!-- legacy stub -->

<!-- IMAGE MODAL -->
<div class="image-modal" id="imageModal" onclick="if(event.target===this||event.target.id==='imageModalBody')hideImageModal()">
  <div class="image-modal-toolbar">
    <button class="img-modal-btn" onclick="hideImageModal()" title="إغلاق"><i class="fas fa-times"></i></button>
    <div style="display:flex;gap:8px;align-items:center">
      <button class="img-modal-btn" onclick="_imgZoomChange(0.3)" title="تكبير"><i class="fas fa-search-plus"></i></button>
      <button class="img-modal-btn" onclick="_imgZoomChange(-0.3)" title="تصغير"><i class="fas fa-search-minus"></i></button>
      <button class="img-modal-btn" onclick="_imgZoomReset()" title="إعادة ضبط"><i class="fas fa-compress-arrows-alt"></i></button>
      <button class="img-modal-btn" onclick="_imgDownload()" title="تحميل"><i class="fas fa-download"></i></button>
    </div>
  </div>
  <div class="image-modal-body" id="imageModalBody">
    <img class="image-modal-content" id="imageModalImg" src="" alt="" draggable="false">
  </div>
</div>

<!-- CROP MODAL -->
<div class="crop-modal" id="cropModal">
  <div class="crop-container">
    <div class="modal-header">
      <h3>قص الصورة</h3>
      <button class="close-btn" id="cropClose">&times;</button>
    </div>
    <div class="crop-image-container"><img id="cropImage" style="max-width:100%;max-height:360px"></div>
    <div class="crop-controls">
      <button class="btn btn-danger" id="cropCancel"><i class="fas fa-times"></i> إلغاء</button>
      <button class="btn btn-success" id="cropConfirm"><i class="fas fa-check"></i> تأكيد</button>
    </div>
  </div>
</div>

<!-- ACCOUNT MODAL -->
<div class="modal-overlay" id="accountModal">
  <div class="modal" style="max-width:460px">
    <div class="modal-header">
      <h3><i class="fas fa-user-circle"></i> الملف الشخصي</h3>
      <button class="close-btn" onclick="hideAccountModal()">&times;</button>
    </div>
    <div class="account-avatar-section">
      <div class="account-avatar-circle-wrap" onclick="document.getElementById('unclePhotoInput').click()">
        <img src="" alt="" class="account-big-avatar" id="accountBigAvatar">
        <div class="account-avatar-plus"><i class="fas fa-plus"></i></div>
      </div>
      <div class="account-name" id="accountDisplayName">---</div>
      <div class="account-role" id="accountDisplayRole">---</div>
      <input type="file" id="unclePhotoInput" accept="image/*" style="display:none">
    </div>
    <div id="accountInfoView" style="margin-bottom:14px">
      <div class="account-info-row">
        <span class="account-info-label"><i class="fas fa-user"></i> الاسم</span>
        <span class="account-info-value" id="aiName">---</span>
      </div>
      <div class="account-info-row">
        <span class="account-info-label"><i class="fas fa-at"></i> المستخدم</span>
        <span class="account-info-value" id="aiUsername">---</span>
      </div>
      <div class="account-info-row">
        <span class="account-info-label"><i class="fas fa-shield-alt"></i> الدور</span>
        <span class="account-info-value" id="aiRole">---</span>
      </div>
    </div>
    <div style="display:flex;gap:8px;margin-bottom:12px">
      <button class="btn" style="flex:1;padding:10px 12px;font-size:.82rem" onclick="showAccountEditForm()"><i class="fas fa-edit"></i> تعديل</button>
      <button class="btn btn-secondary" style="flex:1;padding:10px 12px;font-size:.82rem" onclick="showUncleHistory()"><i class="fas fa-history"></i> السجل</button>
      <button class="btn btn-danger" style="flex:1;padding:10px 12px;font-size:.82rem" onclick="logout()"><i class="fas fa-sign-out-alt"></i> خروج</button>
    </div>
    <?php if ($showSettings): ?>
    <a href="/uncle/church/" style="display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 11px 16px; border-radius: var(--r-md); background: var(--surface-3); border-color: var(--border-solid); color: var(--text-2); font-family: Cairo, sans-serif; font-size: 0.84rem; font-weight: 700; text-decoration: none; margin-bottom: 12px; transition: all var(--t) var(--ease);" onmouseover="this.style.background='var(--brand-bg)';this.style.color='var(--brand)';this.style.borderColor='var(--brand)'" onmouseout="this.style.background='var(--surface-3)';this.style.color='var(--text-2)';this.style.borderColor='var(--border-solid)'">
      <i class="fa-solid fa-screwdriver-wrench" style="font-size:.9rem"></i> لوحة الإدارة والإعدادات
      <i class="fas fa-arrow-left" style="font-size:.7rem;opacity:.5;margin-right:auto"></i>
    </a>
    <?php endif; ?>
          <a href="/leaderboard/" style="display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; padding: 11px 16px; border-radius: var(--r-md); background:linear-gradient(135deg,#7c5cfc,#fc5c7d); border-color: var(--border-solid); color: var(--bg); font-family: Cairo, sans-serif; font-size: 0.84rem; font-weight: 700; text-decoration: none; margin-bottom: 12px; transition: all var(--t) var(--ease);" onmouseover="this.style.background='linear-gradient(135deg, rgb(77 49 189), rgb(252, 92, 125))';this.style.color='var(--bg)';this.style.borderColor='var(--brand)'" onmouseout="this.style.background='linear-gradient(135deg,#7c5cfc,#fc5c7d)';this.style.color='var(--bg)';this.style.borderColor='var(--border-solid)'">
      <i class="fas fa-crown" style="font-size:.9rem"></i> التحقق من الأوائل
      <i class="fas fa-arrow-left" style="font-size:.7rem;opacity:.5;margin-right:auto"></i>
    </a>
    <div class="account-edit-form" id="accountEditForm">
      <form id="uncleProfileForm">
        <div class="form-group">
          <label class="form-label">الاسم</label>
          <div class="input-icon-wrap"><i class="fas fa-user input-icon"></i><input type="text" class="form-input" id="uncleProfileName" required></div>
        </div>
        <div class="form-group">
          <label class="form-label">اسم المستخدم</label>
          <div class="input-icon-wrap"><i class="fas fa-at input-icon"></i><input type="text" class="form-input" id="uncleProfileUsername" required></div>
        </div>
        <div class="form-group">
          <label class="form-label">كلمة مرور جديدة <small style="color:var(--text-3)">(اتركه فارغاً للإبقاء)</small></label>
          <div class="input-icon-wrap"><i class="fas fa-lock input-icon"></i><input type="password" class="form-input" id="uncleProfileNewPassword" minlength="6"></div>
        </div>
        <div style="display:flex;gap:8px">
          <button type="submit" class="btn btn-success" style="flex:1"><i class="fas fa-save"></i> حفظ</button>
          <button type="button" class="btn btn-secondary" style="flex:1" onclick="hideAccountEditForm()"><i class="fas fa-times"></i> إلغاء</button>
        </div>
      </form>
    </div>
  </div>
</div>


<!-- Uncle Activity History Modal -->
<div class="modal-overlay" id="uncleHistoryModal">
  <div class="modal modal-lg" style="max-width:640px">
    <div class="modal-header">
      <h3><i class="fas fa-history" style="color:var(--brand)"></i> سجل نشاطي</h3>
      <button class="close-btn" onclick="document.getElementById('uncleHistoryModal').classList.remove('active');startAutoRefresh()">&times;</button>
    </div>

    <!-- Search + filter bar -->
    <div style="padding:0 0 12px;display:flex;gap:8px;flex-wrap:wrap;">
      <div style="flex:1;min-width:140px;display:flex;align-items:center;gap:6px;background:var(--surface-3);border-radius:var(--r-md);padding:6px 10px;border:1.5px solid var(--border-solid)">
        <i class="fas fa-search" style="color:var(--text-3);font-size:.8rem;flex-shrink:0"></i>
        <input id="historySearch" type="text" placeholder="بحث في السجل..."
          style="border:none;background:transparent;font-family:Cairo,sans-serif;font-size:.82rem;color:var(--text);width:100%;outline:none"
          oninput="filterHistory()">
      </div>
      <select id="historyFilter" onchange="filterHistory()"
        style="border:1.5px solid var(--border-solid);border-radius:var(--r-md);padding:6px 10px;font-family:Cairo,sans-serif;font-size:.82rem;background:var(--surface-3);color:var(--text);cursor:pointer;outline:none">
        <option value="">كل الأنشطة</option>
        <option value="attendance">الحضور</option>
        <option value="student">الأطفال</option>
        <option value="coupon">الكوبونات</option>
        <option value="login">تسجيل الدخول</option>
        <option value="other">أخرى</option>
      </select>
    </div>

    <!-- Summary chips (filled dynamically) -->
    <div id="historySummary" style="display:flex;gap:6px;flex-wrap:wrap;margin-bottom:12px;"></div>

    <!-- Log list -->
    <div id="uncleHistoryContent" style="max-height:55vh;overflow-y:auto;margin:0 -4px;padding:0 4px">
      <div style="text-align:center;padding:2rem;color:var(--text-3)">
        <i class="fas fa-spinner fa-spin" style="font-size:1.5rem"></i>
        <p style="margin-top:8px">جاري التحميل...</p>
      </div>
    </div>

    <!-- Empty / no-match state (hidden initially) -->
    <div id="historyEmpty" style="display:none;text-align:center;padding:2rem;color:var(--text-3)">
      <i class="fas fa-search" style="font-size:1.5rem;display:block;margin-bottom:8px"></i>
      لا توجد نتائج
    </div>
  </div>
</div>

<!-- MAIN CONTAINER -->
<div id="mainContainer">

  <!-- TOPBAR -->
  <header class="topbar">
    <a class="topbar-brand" href="#">
      <div class="topbar-logo">
        <img src="/logo.png" alt="" onerror="this.outerHTML='<i class=\'fas fa-cross\'></i>'">
      </div>
      <div>
        <div class="topbar-title"><?php echo htmlspecialchars($churchName); ?></div>
        <div class="topbar-subtitle">نظام مدارس الأحد</div>
      </div>
    </a>
    <div class="topbar-actions">
      <!-- Dark mode toggle -->
      <button class="topbar-btn" id="themeToggleBtn" onclick="toggleTheme()" title="تبديل الوضع">
        <i class="fas fa-moon theme-toggle-icon-moon"></i>
        <i class="fas fa-sun theme-toggle-icon-sun"></i>
      </button>
      <!-- Unified notification bell (unread count + push permission) -->
      <button class="topbar-btn" id="notifBellBtn" onclick="toggleNotifPanel()" title="الإشعارات" style="position:relative;">
        <i class="fas fa-bell"></i>
        <span id="notifBellBadge" style="display:none;position:absolute;top:-3px;right:-3px;min-width:17px;height:17px;background:var(--danger,#ef4444);border-radius:9px;border:2px solid white;font-size:.58rem;font-weight:800;color:#fff;display:none;align-items:center;justify-content:center;padding:0 3px;"></span>
      </button>
      <!-- Push permission button (only when not granted) -->
      <button class="topbar-btn" id="notifPermBtn" onclick="requestNotifPermission()" title="تفعيل الإشعارات" style="display:none;position:relative">
        <i class="fas fa-bell-slash"></i>
        <span style="position:absolute;top:-3px;right:-3px;width:8px;height:8px;background:var(--warning);border-radius:50%;border:2px solid var(--bg)"></span>
      </button>
      <!-- PWA Install -->
      <button class="topbar-btn" id="pwaInstallBtn" onclick="triggerPwaInstall()" title="تثبيت التطبيق" style="display:none;position:relative">
        <i class="fas fa-download"></i>
        <span style="position:absolute;top:-3px;right:-3px;width:8px;height:8px;background:var(--success);border-radius:50%;border:2px solid var(--bg)"></span>
      </button>
      <?php /* Admin/Settings button moved into profile modal */ ?>
      <div class="topbar-avatar-btn" id="uncleChip" style="display:<?php echo $hasUncleId?'flex':'none'?>" onclick="showAccountModal()">
        <img src="" alt="" id="uncleAvatar" onerror="this.style.display='none';var n=this.nextElementSibling;if(n)n.style.display='flex'">
        <span id="uncleInitials" style="display:none;font-size:.78rem;font-weight:800;color:var(--brand);letter-spacing:-.5px;line-height:1"></span>
      </div>
    </div>
  </header>

  <!-- PAGE WRAP -->
  <div class="page-wrap">

    <!-- ═══ CLASSES VIEW ═══ -->
    <div id="classesView">
      <div class="hero-strip">
        <div>
          <div class="hero-greeting">أهلاً، <span id="heroName"><?php echo htmlspecialchars($uncleName ?: $churchName); ?></span> 👋</div>
        </div>
        <div class="hero-actions">
          <button class="btn btn-info btn-sm" id="showBirthdayModalBtn"><i class="fas fa-birthday-cake"></i> أعياد الميلاد</button>
          <button class="btn btn-success btn-sm" id="showAllStudentsModalBtn"><i class="fas fa-list"></i> الأطفال</button>
          <button class="btn btn-sm" id="manageAnnouncementsBtn"><i class="fas fa-bullhorn"></i> الإعلانات</button>
          <a href="/uncle/dashboard/tasks/" class="btn btn-sm" id="tasksGlobalBtn" style="background:linear-gradient(135deg,#5b6cf5,#4154e8);color:#fff;border:none;text-decoration:none;display:inline-flex;align-items:center;gap:5px;position:relative;"><i class="fas fa-tasks"></i> المهام<span id="globalTasksBadge" style="display:none;background:#fff;color:#4f46e5;border-radius:9px;min-width:17px;height:17px;font-size:.6rem;font-weight:800;padding:0 3px;align-items:center;justify-content:center;margin-right:4px;display:none;"></span></a>
        </div>
      </div>

      <!-- Stats -->
      <div class="stats-row">
        <div class="stat-tile" onclick="showAllStudentsModal()" style="cursor:pointer" title="عرض جميع الأطفال">
          <div class="stat-tile-icon blue"><i class="fas fa-users"></i></div>
          <div><div class="stat-tile-val" id="totalStudents">0</div><div class="stat-tile-lbl">إجمالي الأطفال</div></div>
        </div>
        <div class="stat-tile" style="cursor:default">
          <div class="stat-tile-icon green"><i class="fas fa-door-open"></i></div>
          <div><div class="stat-tile-val" id="totalClasses">0</div><div class="stat-tile-lbl">الفصول</div></div>
        </div>
        <div class="stat-tile" onclick="showBirthdayModal()" style="cursor:pointer" title="عرض أعياد الميلاد">
          <div class="stat-tile-icon pink"><i class="fas fa-birthday-cake"></i></div>
          <div><div class="stat-tile-val" id="birthdaysThisMonth">0</div><div class="stat-tile-lbl">أعياد هذا الشهر</div></div>
        </div>
        <div class="stat-tile" style="cursor:default">
          <div class="stat-tile-icon purple"><i class="fas fa-star"></i></div>
          <div><div class="stat-tile-val" id="averageCoupons">0</div><div class="stat-tile-lbl">متوسط الكوبونات</div></div>
        </div>
      </div>

      <!-- Today's Birthdays Banner -->
      <div id="todayBirthdayBanner">
        <div class="bday-banner-header">
          <i class="fas fa-birthday-cake"></i>
          <span id="todayBirthdayTitle">🎂 أعياد ميلاد اليوم!</span>
        </div>
        <div class="bday-banner-list" id="todayBirthdayList"></div>
      </div>

      <div class="section-head">
        <span class="section-title">الفصول</span>
      </div>
      <div class="classes-grid" id="classesGrid"></div>

      <footer class="site-footer">
        <div class="footer-inner">
          <a href="https://sunday-school.rf.gd/" class="footer-brand">
            <div class="footer-logo"><img src="/logo.png" alt="" onerror="this.style.display='none'"></div>
            <span class="footer-name">نظام مدارس الأحد 2026</span>
          </a>
          <div class="footer-copy">مُكْثِرِينَ فِي عَمَلِ الرَّبِّ كُلَّ حِينٍ<br><span>كُورِنْثُوسَ الأُولَى ١٥:‏٥٨</span></div>
        </div>
        <div class="footer-links">
          <a href="/help" class="footer-link"><i class="fas fa-question-circle"></i> المساعدة</a>
          <a href="https://api.whatsapp.com/send?phone=201037011355" class="footer-link"><i class="fab fa-whatsapp"></i> تواصل</a>
          <a href="/about" class="footer-link"><i class="fas fa-info-circle"></i> حول</a>
        </div>
      </footer>
    </div>
    <!-- end classesView -->

    <!-- ═══ CLASS VIEW ═══ -->
    <div class="class-view" id="classView">
      <!-- Class topbar -->
      <div class="class-topbar">
        <div class="class-topbar-left">
          <button class="btn btn-ghost btn-sm" id="backBtn" style="min-width:40px;height:40px;padding:0 14px;font-size:.9rem;"><i class="fas fa-arrow-right"></i></button>
          <h2 class="class-title-text" id="className">الفصل</h2>
        </div>
        <div class="date-chip" id="dateChip" onclick="showPastFridaysModal()">
          <i class="fas fa-calendar-alt"></i>
          <span id="currentDateText">جاري...</span>
        </div>
      </div>

      <!-- Uncles bar -->
      <div class="uncles-bar" id="unclesBar" style="display:none">
        <span class="uncles-bar-label"><i class="fas fa-users"></i> الخدام:</span>
        <div class="uncles-list" id="unclesList"></div>
      </div>

      <!-- Pending registrations -->
      <div class="pending-section" id="pendingRegistrationsSection">
        <div class="pending-header">
          <div class="pending-title">
            <i class="fas fa-user-clock"></i>
            طلبات التسجيل المعلقة
            <span class="pending-count-badge" id="pendingCountBadge">0</span>
          </div>
          <button class="reg-expand-toggle" id="pendingCollapseBtn" onclick="togglePendingSection()" title="طي/توسيع">
            <i class="fas fa-chevron-up"></i>
          </button>
        </div>
        <div class="pending-body" id="pendingBody">
          <div class="pending-search-row">
            <input type="text" class="search-input" id="pendingSearchInput" placeholder="بحث بالاسم أو الهاتف..." oninput="searchPendingRegistrations()">
            <button class="search-btn" id="pendingSearchBtn" onclick="searchPendingRegistrations()"><i class="fas fa-search"></i></button>
          </div>
          <div id="pendingList"></div>
          <div class="pending-bulk-row" id="pendingBulkRow" style="display:none">
            <button class="btn btn-success" id="approveAllSelectedBtn" onclick="approveAllSelected()"><i class="fas fa-check-circle"></i> موافقة المحددين (<span id="selectedCount">0</span>)</button>
            <button class="btn btn-danger" id="rejectAllSelectedBtn" onclick="rejectAllSelected()"><i class="fas fa-times-circle"></i> رفض المحددين</button>
          </div>
        </div>
      </div>

      <!-- Action strip -->
      <div class="action-strip">
        <div class="action-dropdown">
          <button class="action-strip-btn" id="actionsStripBtn">
            <i class="fas fa-chalkboard-teacher"></i>
            <span class="strip-label">الفصل</span>
            <i class="fas fa-chevron-down chevron"></i>
          </button>
          <div class="dropdown-menu" id="actionsDropdownMenu">
            <div class="dropdown-group-label">الفصل</div>
            <button class="dropdown-item" onclick="showSheetModal();closeAllDropdowns()"><i class="fas fa-table"></i> جداول أطفال الفصل</button>
            <button class="dropdown-item coupon" onclick="showCustomExportModal();closeAllDropdowns()"><i class="fas fa-sliders-h"></i> تصدير مخصص</button>
            <button class="dropdown-item" onclick="showPastFridaysModal();closeAllDropdowns()"><i class="fas fa-calendar-alt"></i> سجل الجُمَع السابقة</button>
            <button class="dropdown-item success" onclick="showAttendedModal();closeAllDropdowns()"><i class="fas fa-user-check"></i> عرض الحاضرين</button>
            <button class="dropdown-item" onclick="showAbsentModal();closeAllDropdowns()"><i class="fas fa-user-times"></i> عرض الغائبين</button>
          </div>
        </div>
        <div class="action-dropdown">
          <button class="action-strip-btn" id="couponsStripBtn">
            <i class="fas fa-layer-group"></i>
            <span class="strip-label">جماعي</span>
            <i class="fas fa-chevron-down chevron"></i>
          </button>
          <div class="dropdown-menu" id="couponsDropdownMenu">
            <div class="dropdown-group-label">الحضور الجماعي</div>
            <button class="dropdown-item success" onclick="markAllPresent();closeAllDropdowns()"><i class="fas fa-check-circle"></i> حضور للجميع</button>
            <button class="dropdown-item danger" onclick="markAllAbsent();closeAllDropdowns()"><i class="fas fa-times-circle"></i> غياب للجميع</button>
            <div class="dropdown-divider"></div>
            <div class="dropdown-group-label">الكوبونات الجماعية</div>
            <button class="dropdown-item coupon" onclick="addCouponsToAll(10);closeAllDropdowns()"><i class="fas fa-star"></i> +10 كوبونات للجميع</button>
            <button class="dropdown-item coupon" onclick="addCouponsToAll(30);closeAllDropdowns()"><i class="fas fa-star"></i> +30 كوبونات للجميع</button>
            <button class="dropdown-item coupon" onclick="addCouponsToAll(50);closeAllDropdowns()"><i class="fas fa-star"></i> +50 كوبونات للجميع</button>
            <button class="dropdown-item coupon" onclick="addCouponsToAll(100);closeAllDropdowns()"><i class="fas fa-star"></i> +100 كوبونات للجميع</button>
            <div class="dropdown-divider"></div>
            <button class="dropdown-item danger" onclick="resetCouponDataForClass(currentClass);closeAllDropdowns()"><i class="fas fa-undo"></i> إعادة تعيين الكوبونات</button>
          </div>
        </div>
        <button class="action-strip-btn action-strip-standalone" onclick="showResetModal()" title="إعادة التعيين">
          <i class="fas fa-rotate-left"></i>
        </button>
                  <button class="action-strip-btn action-strip-standalone" id="tasksClassBtn"
          onclick="window.location.href='/uncle/dashboard/tasks?class='+encodeURIComponent(currentClass)"
          title="مهام الفصل"
          style="background:linear-gradient(135deg,#5b6cf5,#4154e8);color:#fff;border:none;position:relative;">
          <i class="fas fa-tasks"></i>
          <span class="tasks-notif-dot" id="tasksSubmissionDot" style="display:none;"></span>
        </button>
        <button class="action-strip-btn action-strip-standalone action-strip-add" onclick="showAddPersonModal()" title="إضافة طفل جديد">
          <i class="fas fa-user-plus"></i>
        </button>
      </div>

      <!-- Search -->
      <div class="search-wrap">
        <input type="text" class="search-input" id="searchInput" placeholder="ابحث عن طفل بالاسم...">
        <button class="search-btn" id="searchBtn"><i class="fas fa-search"></i></button>
        <button class="clear-search-btn" id="clearSearchBtn"><i class="fas fa-times"></i> إلغاء</button>
      </div>
      <div class="search-wrap" style="margin-top:-8px">
        <select class="search-input" id="classSortSelect" title="ترتيب القائمة">
          <option value="name_az">الترتيب: الاسم أ-ي</option>
          <option value="name_za">الاسم ي-أ</option>
          <option value="age_asc">الأصغر سناً</option>
          <option value="age_desc">الأكبر سناً</option>
          <option value="class_az">الفصل</option>
          <option value="coupons_desc">الأكثر كوبونات</option>
          <option value="attendance_desc">الأكثر حضوراً</option>
          <option value="top_desc">الأوائل</option>
        </select>
        <button class="search-btn" onclick="renderAttendanceList(currentClass)" title="تطبيق الترتيب"><i class="fas fa-sort"></i></button>
      </div>
      <div class="search-results-info" id="searchResultsInfo"></div>
        
      <!-- Sticky attendance toolbar -->
      <div class="att-toolbar">
        <div class="toolbar-row">
          <div class="toolbar-stats">
            <span class="toolbar-stat"><i class="fas fa-users"></i> <span id="tbTotalVal">0</span> <span class="stat-lbl">طفل</span></span>
            <span class="toolbar-stat s"><i class="fas fa-check"></i> <span id="tbPresentVal">0</span> <span class="stat-lbl">حاضر</span></span>
            <span class="toolbar-stat a"><i class="fas fa-times"></i> <span id="tbAbsentVal">0</span> <span class="stat-lbl">غائب</span></span>
            <span class="toolbar-stat c"><i class="fas fa-star"></i> <span id="tbCouponsVal">0</span> <span class="stat-lbl">متوسط</span></span>
          </div>
          <div class="save-row">
            <!-- Swipe mode — FIRST, with glow -->
            <button class="save-btn swipe-toolbar-btn" onclick="startSwipeMode()" title="وضع السحب السريع">
              <i class="fas fa-hand-pointer swipe-hand-icon"></i>
              <span class="save-btn-bottom"><span class="save-btn-label">سحب</span></span>
            </button>
            <button class="save-btn save-btn-unsaved" id="saveAllBtn" disabled
              title="محفوظ" onclick="showUnsavedModal()">
              <i class="fas fa-check-circle"></i>
              <span class="save-btn-bottom"><span class="save-btn-label">محفوظ</span></span>
            </button>
            <button class="save-btn save-btn-attendance" id="submitAttendance" disabled
              title="حفظ الحضور">
              <i class="fas fa-user-check"></i>
              <span class="save-btn-bottom"><span class="save-btn-label">الحضور</span></span>
            </button>
            <button class="save-btn save-btn-coupons" id="submitCoupons" disabled
              title="حفظ الكوبونات">
              <i class="fas fa-star"></i>
              <span class="save-btn-bottom"><span class="save-btn-label">الكوبونات</span></span>
            </button>
          </div>
        </div>
      </div>


      <div class="attendance-list" id="attendanceList"></div>
    </div>
    <!-- end classView -->

  </div><!-- end page-wrap -->
</div><!-- end mainContainer -->

<!-- ════════════════════════════════════════════
     SWIPE MODE OVERLAY
════════════════════════════════════════════ -->
<div id="swipeOverlay">
  <div class="swipe-header">
    <button class="swipe-exit-btn" onclick="exitSwipeMode()" title="خروج"><i class="fas fa-times"></i></button>
    <div class="swipe-progress-wrap">
      <div class="swipe-class-label" id="swipeClassLabel"></div>
      <div class="swipe-progress-bar"><div class="swipe-progress-fill" id="swipeProgressFill"></div></div>
      <div class="swipe-counter" id="swipeCounter">— / —</div>
    </div>
    <div class="swipe-score-row">
      <span class="swipe-score pres" id="swipePresCount"><i class="fas fa-check"></i> 0</span>
      <span class="swipe-score abs"  id="swipeAbsCount"><i class="fas fa-times"></i> 0</span>
    </div>
  </div>

  <div class="swipe-card-area" id="swipeCardArea">
    <!-- subtle bg hints -->
    <div class="swipe-hints">
      <div class="swipe-hint-left"><i class="fas fa-times-circle"></i><span>غياب</span></div>
      <div class="swipe-hint-right"><i class="fas fa-check-circle"></i><span>حضور</span></div>
    </div>
    <div class="swipe-card" id="swipeCard"></div>
  </div>

  <div class="swipe-btns">
    <!-- RTL: right side = positive drag = حضور, left side = negative drag = غياب -->
    <!-- In RTL layout, right button is first visually -->
    <button class="swipe-present-btn" onclick="swipeDecide('present')"><i class="fas fa-check"></i> حضور</button>
    <button class="swipe-skip-btn"    onclick="swipeDecide('skip')"   title="تخطي">تخطي</button>
    <button class="swipe-absent-btn"  onclick="swipeDecide('absent')"><i class="fas fa-times"></i> غياب</button>
  </div>
</div>

<!-- ════════════════════════════════════════════
     ALL MODALS
════════════════════════════════════════════ -->

<!-- Unsaved Modal -->
<div class="modal-overlay" id="unsavedModal">
  <div class="modal" style="max-width:430px">
    <div class="modal-header">
      <h3><i class="fas fa-exclamation-triangle" style="color:var(--warning)"></i> تغييرات غير محفوظة</h3>
      <button class="close-btn" onclick="document.getElementById('unsavedModal').classList.remove('active')">&times;</button>
    </div>
    <div id="unsavedModalContent" style="margin-bottom:14px"></div>
    <div style="display:flex;gap:8px;flex-wrap:wrap">
      <button class="btn btn-secondary" style="width:100%" onclick="document.getElementById('unsavedModal').classList.remove('active')"><i class="fas fa-times"></i> إغلاق</button>
    </div>
  </div>
</div>

<!-- Student Details Modal -->
<div id="studentModal" class="modal-overlay" style="z-index:1000005">
  <div class="modal">
    <div class="modal-header">
      <h3 id="studentModalTitle">معلومات الطفل</h3>
      <button class="close-btn" id="closeStudentModal">&times;</button>
    </div>
    <div id="studentDetails" style="margin-bottom:14px"></div>
    <div style="display:flex;gap:8px">
      <button class="btn" id="editStudentBtn" style="flex:1"><i class="fas fa-edit"></i> تعديل</button>
      <button class="btn btn-danger" id="deleteStudentBtn" style="flex:1"><i class="fas fa-trash"></i> حذف</button>
    </div>
  </div>
</div>

<!-- Edit Student Modal -->
<div id="editStudentForm" class="modal-overlay" style="z-index:1000006">
  <div class="modal">
    <div class="modal-header">
      <h3>تعديل بيانات الطفل</h3>
      <button class="close-btn" id="cancelEditBtn">&times;</button>
    </div>
    <form id="editForm">
      <div class="form-group">
        <label class="form-label">الصورة الشخصية</label>
        <div class="photo-editor-section">
          <div class="photo-circle-wrap" id="photoUploadArea" onclick="document.getElementById('photoInput').click()">
            <img id="uploadPreview" class="photo-circle-img upload-preview" src="" alt="" style="display:none">
            <div class="photo-circle-placeholder" id="photoPlaceholder"><i class="fas fa-user"></i></div>
            <div class="photo-circle-plus"><i class="fas fa-plus"></i></div>
            <input type="file" id="photoInput" accept="image/*" style="display:none">
          </div>
          <div class="upload-controls" id="uploadControls" style="margin-top:10px">
            <button type="button" class="btn btn-success btn-sm" id="savePhotoBtn"><i class="fas fa-upload"></i> رفع</button>
            <button type="button" class="btn btn-danger btn-sm" id="cancelUploadBtn"><i class="fas fa-times"></i> إلغاء</button>
          </div>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">الاسم *</label>
        <div class="input-icon-wrap"><i class="fas fa-id-card input-icon"></i><input type="text" id="editStudentName" class="form-input" required></div>
      </div>
      <div class="form-group">
        <label class="form-label">الفصل *</label>
        <div class="input-icon-wrap"><i class="fas fa-chalkboard-teacher input-icon"></i><select id="editStudentClass" class="form-input" required><option value="">اختر الفصل</option></select></div>
      </div>
      <div class="form-group">
        <label class="form-label">العنوان</label>
        <div class="input-icon-wrap"><i class="fas fa-map-marker-alt input-icon"></i><input type="text" id="editStudentAddress" class="form-input"></div>
      </div>
      <div class="form-group">
        <label class="form-label">رقم التليفون</label>
        <div class="input-icon-wrap"><i class="fas fa-phone input-icon"></i><input type="tel" id="editStudentPhone" class="form-input"></div>
      </div>
      <div class="form-group">
        <label class="form-label">تاريخ الميلاد (DD/MM/YYYY)</label>
        <div class="input-icon-wrap"><i class="fas fa-birthday-cake input-icon"></i><input type="text" id="editStudentBirthday" class="form-input" placeholder="DD/MM/YYYY"></div>
      </div>
      <div class="form-group">
        <label class="form-label">كوبونات الالتزام</label>
        <div class="input-icon-wrap"><i class="fas fa-star input-icon" style="color:var(--coupon)"></i><input type="number" id="editStudentCommitmentCoupons" class="form-input" value="0" min="0"></div>
      </div>
      <!-- Multiple custom fields container (filled dynamically) -->
      <div id="editCustomFieldsContainer" style="display:none"></div>
      <button type="submit" class="btn" style="width:100%"><i class="fas fa-save"></i> حفظ التعديلات</button>
    </form>
  </div>
</div>

<!-- Add Student Modal -->
<div id="addPersonModal" class="modal-overlay" style="z-index:1000006">
  <div class="modal">
    <div class="modal-header">
      <h3>إضافة طفل جديد</h3>
      <button class="close-btn" id="closeAddPersonModal">&times;</button>
    </div>
    <form id="addPersonForm">
      <div class="form-group">
        <label class="form-label">الصورة الشخصية</label>
        <div class="photo-editor-section">
          <div class="photo-circle-wrap" id="newStudentPhotoUploadArea" onclick="document.getElementById('newStudentPhotoInput').click()">
            <img id="newStudentUploadPreview" class="photo-circle-img upload-preview" src="" alt="" style="display:none">
            <div class="photo-circle-placeholder" id="newStudentPhotoPlaceholder"><i class="fas fa-user-plus"></i></div>
            <div class="photo-circle-plus"><i class="fas fa-plus"></i></div>
            <input type="file" id="newStudentPhotoInput" accept="image/*" style="display:none">
          </div>
          <div class="upload-controls" id="newStudentUploadControls" style="margin-top:10px">
            <button type="button" class="btn btn-success btn-sm" id="saveNewStudentPhotoBtn"><i class="fas fa-upload"></i> رفع</button>
            <button type="button" class="btn btn-danger btn-sm" id="cancelNewStudentUploadBtn"><i class="fas fa-times"></i> إلغاء</button>
          </div>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">الاسم *</label>
        <div class="input-icon-wrap"><i class="fas fa-id-card input-icon"></i><input type="text" id="studentName" class="form-input" required></div>
      </div>
      <div class="form-group">
        <label class="form-label">الفصل *</label>
        <div class="input-icon-wrap"><i class="fas fa-chalkboard-teacher input-icon"></i><select id="studentClass" class="form-input" required><option value="">اختر الفصل</option></select></div>
      </div>
      <div class="form-group">
        <label class="form-label">العنوان</label>
        <div class="input-icon-wrap"><i class="fas fa-map-marker-alt input-icon"></i><input type="text" id="studentAddress" class="form-input"></div>
      </div>
      <div class="form-group">
        <label class="form-label">رقم التليفون</label>
        <div class="input-icon-wrap"><i class="fas fa-phone input-icon"></i><input type="tel" id="studentPhone" class="form-input"></div>
      </div>
      <div class="form-group">
        <label class="form-label">تاريخ الميلاد (DD/MM/YYYY)</label>
        <div class="input-icon-wrap"><i class="fas fa-birthday-cake input-icon"></i><input type="text" id="studentBirthday" class="form-input" placeholder="DD/MM/YYYY"></div>
      </div>
      <div class="form-group">
        <label class="form-label">كوبونات ابتدائية</label>
        <div class="input-icon-wrap"><i class="fas fa-star input-icon" style="color:var(--coupon)"></i><input type="number" id="studentCoupons" class="form-input" value="0" min="0"></div>
      </div>
      <!-- Multiple custom fields container (filled dynamically) -->
      <div id="addCustomFieldsContainer" style="display:none"></div>
      <div style="display:flex;gap:8px">
        <button type="submit" class="btn" style="flex:1"><i class="fas fa-plus"></i> إضافة</button>
        <button type="button" class="btn btn-secondary" id="cancelAddPersonModal" style="flex:1"><i class="fas fa-times"></i> إلغاء</button>
      </div>
    </form>
  </div>
</div>

<!-- Sheet Modal -->
<div class="modal-overlay" id="sheetModal" style="z-index:1000010">
  <div class="modal modal-lg" style="z-index:1000011">
    <div class="modal-header" style="flex-wrap:wrap;gap:6px;">
      <h3 id="sheetModalTitle" style="flex:1;min-width:120px;">جدول الحضور</h3>
      <div style="display:flex;gap:5px;align-items:center;flex-wrap:wrap;">
        <input type="text" class="form-input" id="sheetFromDate" placeholder="من DD/MM/YYYY" style="width:130px;padding:7px 10px;font-size:.76rem" maxlength="10" inputmode="numeric" oninput="autoFormatCustomDate(this)">
        <input type="text" class="form-input" id="sheetToDate" placeholder="إلى DD/MM/YYYY" style="width:130px;padding:7px 10px;font-size:.76rem" maxlength="10" inputmode="numeric" oninput="autoFormatCustomDate(this)">
        <button class="btn btn-secondary btn-sm" id="applySheetDateRangeBtn"><i class="fas fa-filter"></i></button>
        <button class="btn btn-ghost btn-sm" id="clearSheetDateRangeBtn"><i class="fas fa-times"></i></button>
        <div class="zoom-controls" id="sheetZoomControls">
          <button class="zoom-btn" onclick="sheetZoom(-1)" title="تصغير"><i class="fas fa-minus"></i></button>
          <span class="zoom-level" id="sheetZoomLevel">100%</span>
          <button class="zoom-btn" onclick="sheetZoom(1)" title="تكبير"><i class="fas fa-plus"></i></button>
          <button class="zoom-btn" onclick="sheetZoomReset()" title="إعادة ضبط"><i class="fas fa-compress-arrows-alt"></i></button>
        </div>
        <button class="btn btn-danger btn-sm" id="saveSheetAsPdfBtn"><i class="fas fa-file-pdf"></i> PDF</button>
        <button class="btn btn-info btn-sm" id="saveSheetAsImageBtn"><i class="fas fa-image"></i> صورة</button>
        <button class="btn btn-success btn-sm" id="saveSheetAsCsvBtn"><i class="fas fa-file-csv"></i> CSV</button>
        <button class="close-btn" id="closeSheetModal">&times;</button>
      </div>
    </div>
    <div class="table-container sheet-container" id="sheetTableContainer">
      <div class="table-zoom-wrap" id="sheetZoomWrap">
        <div class="table-zoom-inner" id="sheetZoomInner">
          <table class="data-table" id="sheetTable">
            <thead id="sheetTableHead"><tr><th style="width:40px"></th><th>الاسم</th><th>الفصل</th><th>العنوان</th><th>التليفون</th><th>الميلاد</th><th>كوبونات</th></tr></thead>
            <tbody id="sheetTableBody"></tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Custom Export Modal -->
<div class="modal-overlay" id="customExportModal" style="z-index:1000012">
  <div class="modal modal-lg" style="z-index:1000013">
    <div class="modal-header" style="gap:8px;flex-wrap:wrap">
      <h3><i class="fas fa-sliders-h" style="color:var(--coupon)"></i> تصدير مخصص</h3>
      <div style="display:flex;gap:6px;align-items:center;flex-wrap:wrap">
        <button class="btn btn-success btn-sm" id="customExportCsvBtn"><i class="fas fa-file-csv"></i> CSV</button>
        <button class="btn btn-danger btn-sm" id="customExportPdfBtn"><i class="fas fa-file-pdf"></i> PDF</button>
        <button class="btn btn-info btn-sm" id="customExportImageBtn"><i class="fas fa-image"></i> صورة</button>
        <button class="close-btn" id="closeCustomExportModal">&times;</button>
      </div>
    </div>
    <div class="export-builder">
      <div class="export-controls">
        <div class="export-panel">
          <div class="export-panel-title"><i class="fas fa-heading"></i> اسم التقرير</div>
          <input type="text" class="form-input" id="customExportTitle" placeholder="مثلاً: حضور فصل أولى">
        </div>
        <div class="export-panel">
          <div class="export-panel-title"><i class="fas fa-table-columns"></i> البيانات والترتيب</div>
          <div class="export-field-list" id="customExportFields"></div>
        </div>
        <div class="export-panel">
          <div class="export-panel-title"><i class="fas fa-calendar-days"></i> الحضور</div>
          <div class="export-date-options">
            <select class="form-input" id="customExportDateMode">
              <option value="none">بدون حضور</option>
              <option value="current">تاريخ الحضور الحالي</option>
              <option value="range">نطاق تواريخ</option>
              <option value="custom">تواريخ مخصصة</option>
              <option value="all">كل تواريخ الحضور</option>
            </select>
            <div id="customExportRangeInputs" style="display:none;grid-template-columns:1fr 1fr;gap:6px">
              <input type="text" class="form-input" id="customExportFromDate" placeholder="من DD/MM/YYYY" maxlength="10" inputmode="numeric" oninput="autoFormatCustomDate(this)">
              <input type="text" class="form-input" id="customExportToDate" placeholder="إلى DD/MM/YYYY" maxlength="10" inputmode="numeric" oninput="autoFormatCustomDate(this)">
            </div>
            <textarea class="form-input" id="customExportDates" placeholder="تواريخ مخصصة: 01/01/2026, 08/01/2026" rows="2" style="display:none;min-height:54px"></textarea>
          </div>
        </div>
        <button class="btn" id="customExportPreviewBtn" style="width:100%;justify-content:center"><i class="fas fa-eye"></i> تحديث المعاينة</button>
      </div>
      <div class="export-preview-wrap">
        <div id="customExportPreview" class="custom-export-doc"></div>
      </div>
    </div>
  </div>
</div>

<!-- Birthday Modal -->
<div class="modal-overlay" id="birthdayModal">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3><i class="fas fa-birthday-cake" style="color:#db2777"></i> أعياد الميلاد</h3>
      <div style="display:flex;gap:6px;align-items:center">
        <span id="birthdayMonthCount" style="background:var(--brand-bg);color:var(--brand);padding:3px 10px;border-radius:var(--r-full);font-size:.75rem;font-weight:700"></span>
        <button class="close-btn" id="closeBirthdayModal">&times;</button>
      </div>
    </div>
    <div class="month-selector" id="monthSelector"></div>
    <div id="birthdayGrid" class="birthday-grid"></div>
    <!-- Confetti container -->
    <div id="bdConfetti" style="position:fixed;inset:0;pointer-events:none;z-index:2000000;overflow:hidden"></div>
  </div>
</div>

<!-- All Students Modal -->
<div class="modal-overlay" id="allStudentsModal">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3><i class="fas fa-users"></i> جميع الأطفال</h3>
      <button class="close-btn" id="closeAllStudentsModal">&times;</button>
    </div>
    <div class="table-toolbar">
      <div class="search-wrap" style="flex:1;margin-bottom:0;padding:4px 6px;width:100%">
        <input type="text" class="search-input" id="allStudentsSearch" placeholder="ابحث عن طفل...">
        <button class="search-btn" id="allStudentsSearchBtn"><i class="fas fa-search"></i></button>
      </div>
      <div class="table-export-btns">
        <button class="btn btn-danger" id="exportAllAsPdfBtn"><i class="fas fa-file-pdf"></i> تصدير PDF</button>
        <button class="btn btn-success" id="exportAllAsCsvBtn"><i class="fas fa-file-csv"></i> تصدير CSV</button>
      </div>
    </div>
    <div class="table-container all-students-table-container" style="margin-top:8px">
      <table class="data-table">
        <thead><tr><th style="width:44px"></th><th>الاسم</th><th>الفصل</th><th>العنوان</th><th>التليفون</th><th>الميلاد</th><th>كوبونات</th></tr></thead>
        <tbody id="allStudentsTableBody"></tbody>
      </table>
    </div>
  </div>
</div>

<!-- Attended Modal -->
<div class="modal-overlay" id="attendedModal">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3><i class="fas fa-user-check" style="color:var(--success)"></i> الأطفال الحاضرين</h3>
      <div style="display:flex;gap:5px;align-items:center">
        <button class="btn btn-success btn-sm" id="copyAttendedModalBtn"><i class="fas fa-copy"></i> نسخ</button>
        <button class="btn btn-sm" style="background:#25d366;color:#fff" onclick="shareAttendedToWhatsApp()"><i class="fab fa-whatsapp"></i></button>
        <button class="btn btn-info btn-sm" id="saveAttendedAsCsvBtn"><i class="fas fa-file-csv"></i></button>
        <button class="close-btn" id="closeAttendedModal">&times;</button>
      </div>
    </div>
    <div class="search-wrap" style="margin-bottom:8px">
      <input type="text" class="search-input" id="attendedSearchInput" placeholder="بحث في الحاضرين..." oninput="renderAttendedTable()">
      <button class="search-btn"><i class="fas fa-search"></i></button>
    </div>
    <div class="table-container all-students-table-container">
      <table class="data-table" id="attendedTable">
        <thead><tr><th style="width:40px"></th><th>الاسم</th><th>الفصل</th><th>التليفون</th><th>العنوان</th><th>ملاحظة</th><th style="width:36px"></th></tr></thead>
        <tbody id="attendedTableBody"></tbody>
      </table>
    </div>
  </div>
</div>

<!-- Absent Modal -->
<div class="modal-overlay" id="absentModal">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3><i class="fas fa-user-times" style="color:var(--danger)"></i> الأطفال الغائبين</h3>
      <div style="display:flex;gap:5px;align-items:center">
        <button class="btn btn-success btn-sm" id="copyAbsentModalBtn"><i class="fas fa-copy"></i> نسخ</button>
        <button class="btn btn-sm" style="background:#25d366;color:#fff" onclick="shareAbsentToWhatsApp()"><i class="fab fa-whatsapp"></i></button>
        <button class="btn btn-info btn-sm" id="saveAbsentAsCsvBtn"><i class="fas fa-file-csv"></i></button>
        <button class="close-btn" id="closeAbsentModal">&times;</button>
      </div>
    </div>
    <div class="search-wrap" style="margin-bottom:8px">
      <input type="text" class="search-input" id="absentSearchInput" placeholder="بحث في الغائبين...">
      <button class="search-btn"><i class="fas fa-search"></i></button>
    </div>
    <div class="table-container all-students-table-container">
      <table class="data-table" id="absentTable">
        <thead><tr><th style="width:40px"></th><th>الاسم</th><th>التليفون</th><th>العنوان</th><th>ملاحظة</th><th style="width:36px"></th></tr></thead>
        <tbody id="absentTableBody"></tbody>
      </table>
    </div>
    <div style="margin-top:8px">
      <button class="btn btn-danger btn-sm" id="clearAbsentDataBtn" style="width:100%"><i class="fas fa-trash-alt"></i> مسح الكل</button>
    </div>
  </div>
</div>

<!-- Past Fridays Modal -->
<div class="modal-overlay" id="pastFridaysModal">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3><i class="fas fa-calendar-alt"></i> السجل التاريخي</h3>
      <button class="close-btn" id="closePastFridaysModal">&times;</button>
    </div>
    <div class="friday-reset-row">
      <button class="btn btn-sm" id="resetToTodayBtn"><i class="fas fa-calendar-day"></i> العودة لآخر يوم</button>
      <button class="btn btn-warning btn-sm" id="toggleCustomDateSectionBtn" onclick="toggleCustomDateSection()">
        <i class="fas fa-plus-circle"></i> إضافة تاريخ مخصص
      </button>
    </div>
    <!-- Custom date add section -->
    <div class="custom-date-section" id="customDateSection" style="display:none">
      <h4><i class="fas fa-calendar-plus"></i> إضافة تاريخ مخصص للحضور</h4>
      <div class="custom-date-inputs">
        <input type="text" class="custom-date-input" id="customDateInput"
          placeholder="DD/MM/YYYY" maxlength="10" inputmode="numeric"
          oninput="autoFormatCustomDate(this)"
          onkeydown="if(event.key==='Enter')addCustomDate()">
        <input type="text" class="custom-date-input" id="customDateLabel"
          placeholder="وصف (اختياري)" style="max-width:160px">
        <button class="btn btn-warning btn-sm" onclick="addCustomDate()">
          <i class="fas fa-plus"></i> إضافة
        </button>
      </div>
      <div class="custom-dates-strip" id="customDatesStrip"></div>
    </div>
    <div class="fridays-list" id="fridaysList"></div>
  </div>
</div>

<!-- Delete Student Modal -->
<div class="modal-overlay" id="deleteStudentModal" style="z-index:1000007">
  <div class="modal modal-sm" style="max-width:380px">
    <div class="modal-header">
      <h3><i class="fas fa-trash"></i> حذف طفل</h3>
      <button class="close-btn" id="closeDeleteStudentModal">&times;</button>
    </div>
    <div class="delete-warning">
      <i class="fas fa-exclamation-triangle"></i>
      <h4 id="deleteStudentName">هل أنت متأكد؟</h4>
      <p style="color:var(--text-3);font-size:.82rem;margin-top:6px">هذا الإجراء لا يمكن التراجع عنه</p>
    </div>
    <div style="display:flex;gap:10px">
      <button class="btn btn-danger" id="confirmDeleteStudentBtn" style="flex:1"><i class="fas fa-trash"></i> نعم، احذف</button>
      <button class="btn btn-secondary" id="cancelDeleteStudentBtn" style="flex:1"><i class="fas fa-times"></i> إلغاء</button>
    </div>
  </div>
</div>

<!-- Reset Modal -->
<div class="modal-overlay" id="resetModal">
  <div class="modal modal-sm" style="max-width:400px">
    <div class="modal-header">
      <h3><i class="fas fa-undo"></i> إعادة التعيين</h3>
      <button class="close-btn" id="closeResetModal">&times;</button>
    </div>
    <div style="text-align:center;padding:10px 0 16px">
      <i class="fas fa-undo" style="font-size:2.2rem;color:var(--warning);margin-bottom:10px;display:block"></i>
      <p style="color:var(--text-3)">اختر نوع إعادة التعيين للبيانات غير المحفوظة</p>
    </div>
    <div style="display:flex;flex-direction:column;gap:8px">
      <button class="btn btn-warning" id="resetAttendanceBtn" style="justify-content:flex-start"><i class="fas fa-user-times"></i> إعادة تعيين الحضور المحلي</button>
      <button class="btn btn-coupon" id="resetCouponsBtn" style="justify-content:flex-start"><i class="fas fa-star"></i> إعادة تعيين الكوبونات</button>
      <button class="btn btn-danger" id="resetAllBtn" style="justify-content:flex-start"><i class="fas fa-bomb"></i> إعادة تعيين الكل</button>
      <button class="btn btn-ghost" id="cancelResetBtn"><i class="fas fa-times"></i> إلغاء</button>
    </div>
  </div>
</div>

<!-- Registration Details Modal -->
<div class="modal-overlay" id="registrationDetailsModal">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3><i class="fas fa-user-plus"></i> تفاصيل طلب التسجيل</h3>
      <button class="close-btn" id="closeRegistrationDetailsModal">&times;</button>
    </div>
    <div id="registrationDetails" style="margin-bottom:14px"></div>
    <div id="rejectionNoteContainer" style="display:none;margin-bottom:12px">
      <div class="form-group">
        <label class="form-label">سبب الرفض (اختياري)</label>
        <textarea class="form-input" id="rejectionNote" placeholder="أدخل سبب الرفض..." rows="3"></textarea>
      </div>
    </div>
    <div style="display:flex;gap:8px">
      <button class="btn btn-success" id="approveRegistrationBtn" style="flex:1"><i class="fas fa-check"></i> الموافقة</button>
      <button class="btn btn-danger" id="rejectRegistrationBtn" style="flex:1"><i class="fas fa-times"></i> الرفض</button>
    </div>
  </div>
</div>

<!-- Announcements Modal -->
<div class="modal-overlay" id="announcementsModal">
  <div class="modal modal-lg">
    <div class="modal-header">
      <h3><i class="fas fa-bullhorn"></i> إدارة الإعلانات</h3>
      <button class="close-btn" id="closeAnnouncementsModal">&times;</button>
    </div>
    <div>
      <div class="announcement-form">
        <h4 style="margin-bottom:12px;font-size:.9rem;color:var(--brand)"><i class="fas fa-plus-circle"></i> إضافة إعلان جديد</h4>
        <form id="addAnnouncementForm">
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:12px">
            <div class="form-group" style="margin:0"><label class="form-label">النوع</label><select class="form-input" id="announcementType"><option value="message">رسالة نصية</option><option value="button">زر برابط</option></select></div>
            <div class="form-group" style="margin:0"><label class="form-label">الفصل</label><select class="form-input" id="announcementClass"><option value="الجميع">جميع الفصول</option><option value="حضانة">حضانة</option><option value="أولى">أولى</option><option value="تانية">تانية</option><option value="تالتة">تالتة</option><option value="رابعة">رابعة</option><option value="خامسة">خامسة</option><option value="سادسة">سادسة</option></select></div>
          </div>
          <div class="form-group"><label class="form-label">النص</label><input type="text" class="form-input" id="announcementText" placeholder="نص الإعلان..." required></div>
          <div id="linkFieldContainer" style="display:none" class="form-group"><label class="form-label">الرابط</label><input type="url" class="form-input" id="announcementLink" placeholder="https://..."></div>
          <div class="form-group"><label class="form-label">أطفال محددين <small style="color:var(--text-3)">(اختياري)</small></label><input type="text" class="form-input" id="announcementStudents" placeholder="اتركه فارغاً للجميع" disabled></div>
          <div style="display:flex;gap:8px">
            <button type="submit" class="btn btn-success" style="flex:1"><i class="fas fa-plus"></i> إضافة</button>
            <button type="button" class="btn btn-danger" id="clearAnnouncementForm" style="flex:1"><i class="fas fa-times"></i> مسح</button>
          </div>
        </form>
      </div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
        <h4 style="font-size:.9rem;color:var(--text)"><i class="fas fa-list"></i> الإعلانات النشطة</h4>
        <span class="badge" id="activeAnnouncementsCount" style="background:var(--brand)">0</span>
      </div>
      <div class="announcements-table-wrap table-container">
        <table class="data-table" id="announcementsTable">
          <thead><tr><th>النوع</th><th>النص</th><th>الفصل</th><th>الأطفال</th><th>الحالة</th><th>التاريخ</th><th>إجراء</th></tr></thead>
          <tbody id="announcementsTableBody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- ════════════════════════════════════════════
     JAVASCRIPT
════════════════════════════════════════════ -->
<script>
'use strict';
const API_URL = '/api.php';
const APP_VERSION = '1.0.1';
const couponPresetValues = [10, 30, 50, 100];

// ── THEME ─────────────────────────────────────────────────────
function applyTheme(theme) {
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('app_theme', theme);
}
function toggleTheme() {
    const current = document.documentElement.getAttribute('data-theme') || 'light';
    applyTheme(current === 'light' ? 'dark' : 'light');
}
// Apply immediately
(function(){
    const saved = localStorage.getItem('app_theme') || 'light';
    applyTheme(saved);
})();

// ── SYNC PHP SESSION DATA → localStorage on every page load ───
(function _syncSessionToStorage() {
    const phpChurchType = <?php echo json_encode($churchType); ?>;
    const phpChurchName = <?php echo json_encode($churchName); ?>;
    const phpUncleName  = <?php echo json_encode($uncleName); ?>;
    const phpUncleRole  = <?php echo json_encode($uncleRole); ?>;
    const phpChurchCode = <?php echo json_encode($churchCode); ?>;

    // Detect account switch: if stored church identity differs from what PHP
    // says, clear account-scoped cached data so another church is not shown.
    const storedType = localStorage.getItem('churchType');
    const storedCode = localStorage.getItem('churchCode');
    if ((storedType && storedType !== phpChurchType) || (storedCode && phpChurchCode && storedCode !== phpChurchCode)) {
        try {
            [
                'churchSettings','lastStudentsData','currentClass','selectedFriday',
                'combinedClassGroups','customAttendanceDates'
            ].forEach(k => localStorage.removeItem(k));
            Object.keys(localStorage).forEach(k => {
                if (
                    k.startsWith('attendanceData_') ||
                    k.startsWith('changedStudents_') ||
                    k.startsWith('savedStudents_') ||
                    k.startsWith('couponData_') ||
                    k.startsWith('changedCouponStudents_') ||
                    k.startsWith('savedCoupons_')
                ) localStorage.removeItem(k);
            });
        } catch(e) {}
    }

    try {
        if (phpChurchType) localStorage.setItem('churchType',  phpChurchType);
        if (phpChurchName) localStorage.setItem('churchName',  phpChurchName);
        if (phpUncleName)  localStorage.setItem('uncleName',   phpUncleName);
        if (phpUncleRole)  localStorage.setItem('uncleRole',   phpUncleRole);
        if (phpChurchCode) localStorage.setItem('churchCode',  phpChurchCode);
    } catch(e) {}
})();

// ── STATE ─────────────────────────────────────────────────────
let students = [], classes = [], allStudentsData = [];
let currentClass = '', currentFriday = '';
let attendanceData = {}, couponData = {}, absentData = {};
let originalAttendanceData = {}, originalCouponData = {};
let changedStudents = new Set(), savedStudents = new Set();
let changedCouponStudents = new Set(), savedCouponStudents = new Set();
let currentStudentForEdit = null, studentToDelete = null;
let currentRegistrationDetails = null, pendingRegistrations = [];
let selectedRegistrations = new Set();
let searchQuery = '', filteredStudents = [], searchTimeout = null;
let allStudentsSearchQuery = '', filteredAllStudents = [], allStudentsSearchTimeout = null;
let cropper = null, currentCroppedBlob = null, currentPhotoEditorType = '';
let autoRefreshEnabled = true, refreshTimer = null, lastDataHash = '';
let activeDropdown = null;
let classSortMode = 'name_az';
let sheetDateFrom = '';
let sheetDateTo = '';
let customExportFields = [];
// Class navigation permission: 'all' = can see all classes, 'own' = only assigned
let uncleClassNavPermission = 'all';

// ── NEVER LOGOUT — silent session restore ─────────────────────
function silentSessionRestore() {
    const cl = localStorage.getItem('loggedIn') === 'true';
    const ul = localStorage.getItem('uncleLoggedIn') === 'true';
    if (!cl && !ul) return;
    const fd = new FormData();
    fd.append('action', 'restore_session');
    const cc = localStorage.getItem('churchCode');
    const un = localStorage.getItem('uncleUsername');
    if (cl && cc) fd.append('church_code', cc);
    else if (ul && un) fd.append('username', un);
    else return;
    fetch(API_URL, { method: 'POST', body: fd, credentials: 'include' })
        .then(r => r.json())
        .then(d => { if (!d.success) { /* silent fail — don't redirect */ } })
        .catch(() => { /* network error — stay on page */ });
}
// Restore every 10 minutes
setInterval(silentSessionRestore, 10 * 60 * 1000);
// Restore on tab regain focus
document.addEventListener('visibilitychange', () => {
    if (!document.hidden) silentSessionRestore();
});

// ── API ───────────────────────────────────────────────────────
function makeApiCall(params, ok, err) {
    const fd = new FormData();
    Object.keys(params).forEach(k => {
        if (params[k] !== undefined && params[k] !== null)
            fd.append(k, typeof params[k] === 'object' && k !== 'action' ? JSON.stringify(params[k]) : params[k]);
    });
    fetch(API_URL, { method: 'POST', body: fd, credentials: 'include' })
        .then(r => r.json())
        .then(d => { if (d.success) { if (ok) ok(d); } else { const m = d.message || 'فشل'; if (err) err(m); else showToast(m, 'error'); } })
        .catch(e => { const m = 'خطأ في الاتصال'; if (err) err(m); else showToast(m, 'error'); });
}

// ── HELPERS ───────────────────────────────────────────────────
function getStudentId(s) { return `${s['الفصل']}_${s['الاسم']}`; }
function escJs(str) { return (str||'').replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,'\\"'); }
function escHtml(str) { return (str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;'); }

// ── SMART PHOTO CACHE ─────────────────────────────────────────
// Appends a version token to image URLs so the browser caches photos
// aggressively (no re-download on every render) but auto-busts when
// the URL itself changes (i.e. a new photo was uploaded for this student).
// We store a tiny map: { url → token } in localStorage.
// Token = a stable hash of the URL string, so the same photo always gets
// the same token and stays in the browser cache forever — until the URL
// changes, at which point a new token is produced and the old cached entry
// is naturally abandoned.
(function _initPhotoCache() {
    const STORE_KEY = '_photo_ver';
    let _map = {};
    try { _map = JSON.parse(localStorage.getItem(STORE_KEY) || '{}'); } catch(e) { _map = {}; }

    // Simple, fast string hash (djb2 variant)
    function _hashUrl(url) {
        let h = 5381;
        for (let i = 0; i < url.length; i++) h = (h * 33) ^ url.charCodeAt(i);
        return (h >>> 0).toString(36); // unsigned 32-bit hex-ish string
    }

    // Prune entries for URLs no longer in the student list to keep storage lean.
    // Called lazily once per loadData() cycle.
    window._prunePhotoCache = function(activeUrls) {
        let changed = false;
        Object.keys(_map).forEach(k => { if (!activeUrls.has(k)) { delete _map[k]; changed = true; } });
        if (changed) { try { localStorage.setItem(STORE_KEY, JSON.stringify(_map)); } catch(e) {} }
    };

    // Returns the URL with a ?_v= token appended.
    // If the URL is already versioned (contains _v=) strip it first.
    window.photoUrl = function(rawUrl) {
        if (!rawUrl) return rawUrl;
        // Strip any existing version token we added
        const base = rawUrl.replace(/([?&])_v=[^&]*/g, '').replace(/[?&]$/, '');
        const token = _hash(base);
        // Store so we can prune later
        if (!_map[base]) {
            _map[base] = token;
            try { localStorage.setItem(STORE_KEY, JSON.stringify(_map)); } catch(e) {}
        }
        const sep = base.includes('?') ? '&' : '?';
        return base + sep + '_v=' + token;
    };

    function _hash(url) {
        // Check if we already have a stored token for this URL
        if (_map[url]) return _map[url];
        const tok = _hashUrl(url);
        _map[url] = tok;
        try { localStorage.setItem(STORE_KEY, JSON.stringify(_map)); } catch(e) {}
        return tok;
    }
})();
// ── Toast system ─────────────────────────────────────────────
const _toastTimers = new Map();
let _toastIdCounter = 0;

function showToast(msg, type = 'info', opts = {}) {
    // opts: { dur, action: {label, fn}, refresh: false }
    const stack = document.getElementById('toastStack');
    if (!stack) return;

    const id  = 'toast_' + (++_toastIdCounter);
    const dur = opts.dur ?? (type === 'error' ? 6000 : 4500);

    const icons = { success:'fa-check-circle', error:'fa-exclamation-circle',
                    info:'fa-info-circle', warning:'fa-exclamation-triangle' };
    const icon  = icons[type] || 'fa-info-circle';

    const item = document.createElement('div');
    item.className = `toast-item ${type}`;
    item.id = id;
    item.style.setProperty('--toast-dur', dur + 'ms');

    let actionHtml = '';
    if (opts.refresh) {
        actionHtml = `<button class="toast-action" onclick="_toastAction('${id}','refresh')"><i class="fas fa-sync-alt"></i> تحديث</button>`;
    } else if (opts.action) {
        actionHtml = `<button class="toast-action" onclick="_toastAction('${id}','custom')">${opts.action.label}</button>`;
    }

    item.innerHTML =
        `<i class="fas ${icon} toast-icon"></i>` +
        `<div class="toast-body">` +
          `<div class="toast-msg">${msg}</div>` +
          (actionHtml ? actionHtml : '') +
        `</div>` +
        `<button class="toast-close" onclick="dismissToast('${id}')" title="إغلاق">` +
          `<i class="fas fa-times"></i>` +
        `</button>`;

    stack.appendChild(item);

    // Store action callback
    if (opts.action) item._customAction = opts.action.fn;

    // Auto-dismiss
    const timer = setTimeout(() => dismissToast(id), dur);
    _toastTimers.set(id, timer);

    // Limit stack to 4 visible toasts
    const all = [...stack.querySelectorAll('.toast-item:not(.removing)')];
    if (all.length > 4) dismissToast(all[0].id);

    return id;
}

function _toastAction(id, type) {
    const item = document.getElementById(id);
    if (!item) return;
    if (type === 'refresh') { loadData(); }
    else if (type === 'custom' && item._customAction) { item._customAction(); }
    dismissToast(id);
}

function dismissToast(id) {
    const item = document.getElementById(id);
    if (!item || item.classList.contains('removing')) return;
    item.classList.add('removing');
    const t = _toastTimers.get(id);
    if (t) { clearTimeout(t); _toastTimers.delete(id); }
    setTimeout(() => item.remove(), 240);
}

// Legacy shim — called internally all over the file
function showToastRefresh(msg, type = 'success') {
    showToast(msg, type, { dur: 7000, refresh: true });
}
function showLoading(msg = '', targetId = null) {
    if (targetId) { const el = document.getElementById(targetId); if (el) { el.innerHTML = `<div class="inline-spinner"><div class="spin"></div>${msg}</div>`; return; } }
    showToast(msg || 'جاري...', 'info');
}
function hideLoading() {}
function formatDateDDMMYYYY(d) { return `${String(d.getDate()).padStart(2,'0')}/${String(d.getMonth()+1).padStart(2,'0')}/${d.getFullYear()}`; }
function parseDate(s) { const p = s.split('/'); return p.length === 3 ? new Date(p[2], p[1]-1, p[0]) : new Date(0); }
function copyToClipboard(text) { const ta = document.createElement('textarea'); ta.value = text; document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta); }
function logout() {
    if (!confirm('تسجيل الخروج؟')) return;
    if (!navigator.onLine) {
        showToast('لا يمكن تسجيل الخروج بدون إنترنت — تأكد من الاتصال أولاً', 'warning', { dur: 5000 });
        return;
    }
    fetch(API_URL + '?action=logout')
        .then(() => { localStorage.clear(); window.location.href = '/'; })
        .catch(() => { window.location.href = '/'; });
}

// ── CLASS NAVIGATION PERMISSION ───────────────────────────────
function canNavigateClasses() {
    // If uncle role is admin/developer they can always navigate
    if (window.currentUncle) {
        const role = window.currentUncle.role || '';
        if (['admin','developer'].includes(role.toLowerCase())) return true;
    }
    // Otherwise check church setting
    return uncleClassNavPermission === 'all';
}

// Attendance day: 1=Mon…5=Fri…7=Sun (ISO weekday, same as Date.getDay() mapped)
// JS getDay(): 0=Sun,1=Mon,2=Tue,3=Wed,4=Thu,5=Fri,6=Sat
// DB values:   1=Mon,2=Tue,3=Wed,4=Thu,5=Fri,6=Sat,7=Sun
let churchAttendanceDay = 5;
let combinedClassGroups = [];
let churchCustomFields  = []; // array of {name,icon}
let churchCustomField   = null; // alias for first field
let churchViewMode      = 'classes'; // 'classes' | 'all' | 'both'

function dbDayToJsDay(dbDay) {
    const map = {1:1,2:2,3:3,4:4,5:5,6:6,7:0};
    return map[dbDay] ?? 5;
}

function loadChurchSettings() {
    // Helper: update any UI text that depends on the attendance day name
    function _applyDayNameToUI() {
        const dayName = getAttendanceDayName();
        const resetBtn = document.getElementById('resetToTodayBtn');
        if (resetBtn) resetBtn.innerHTML = `<i class="fas fa-calendar-day"></i> العودة لآخر ${dayName}`;
    }

    // Restore cached settings first so offline mode has combined groups / day info
    try {
        const cached = localStorage.getItem('churchSettings');
        if (cached) {
            const r = JSON.parse(cached);
            uncleClassNavPermission = r.uncle_class_navigation || 'all';
            churchAttendanceDay     = parseInt(r.attendance_day || 5);
            combinedClassGroups     = Array.isArray(r.combined_class_groups) ? r.combined_class_groups : [];
            churchViewMode          = r.view_mode || 'classes';
            const cf = r.custom_fields || r.custom_field;
            if (Array.isArray(cf) && cf.length) churchCustomFields = cf;
            else if (cf && cf.name) churchCustomFields = [cf];
            else churchCustomFields = [];
            churchCustomField = churchCustomFields[0] || null;
            updateCurrentDateDisplay();
            _applyDayNameToUI();
        }
    } catch(e) {}

    if (!navigator.onLine) { if (!currentClass) displayClasses(); return; }

    makeApiCall({ action: 'getChurchSettings' }, r => {
        if (r.settings) {
            uncleClassNavPermission = r.settings.uncle_class_navigation || 'all';
            churchAttendanceDay     = parseInt(r.settings.attendance_day || 5);
            combinedClassGroups     = Array.isArray(r.settings.combined_class_groups)
                                       ? r.settings.combined_class_groups : [];
            churchViewMode          = r.settings.view_mode || 'classes';
            const cf = r.settings.custom_fields || r.settings.custom_field;
            if (Array.isArray(cf) && cf.length) {
                churchCustomFields = cf;
            } else if (cf && cf.name) {
                churchCustomFields = [cf];
            } else {
                churchCustomFields = [];
            }
            churchCustomField = churchCustomFields[0] || null;
            // Cache settings for offline use
            try { localStorage.setItem('churchSettings', JSON.stringify(r.settings)); } catch(e) {}
        }
        updateCurrentDateDisplay();
        _applyDayNameToUI();
        if (!currentClass) displayClasses();
    }, () => {
        uncleClassNavPermission = 'all';
        churchAttendanceDay     = 5;
        combinedClassGroups     = [];
        churchCustomFields      = [];
        churchCustomField       = null;
        churchViewMode          = 'classes';
    });
}

// ── HASH ROUTING ──────────────────────────────────────────────
function updateHash(view, param = '') {
    if (view === 'class' && param) {
        location.hash = 'class=' + encodeURIComponent(param);
        document.title = param + ' - Sunday School';
    } else if (view === 'combined' && param) {
        location.hash = 'combined=' + encodeURIComponent(param);
        document.title = param + ' - Sunday School';
    } else {
        location.hash = '';
        document.title = 'Sunday School — <?php echo htmlspecialchars($churchName); ?>';
    }
}
window.addEventListener('hashchange', () => {
    const h = location.hash.replace('#', '');
    if (h.startsWith('class=')) {
        const cn = decodeURIComponent(h.replace('class=', ''));
        if (cn && cn !== currentClass) showClassViewInternal(cn);
    } else if (h.startsWith('combined=')) {
        const gl = decodeURIComponent(h.replace('combined=', ''));
        if (gl) showCombinedClassView(gl);
    } else {
        showClassesViewInternal();
    }
});

function showClassesView() { showClassesViewInternal(); updateHash('home'); }
function showClassView(className) {
    // Check class navigation permission
    if (!canNavigateClasses() && window.currentUncle) {
        // Check if this uncle is assigned to this class
        const assignedClasses = (window.currentUncle.classes || []).map(c => c.class_name);
        if (assignedClasses.length > 0 && !assignedClasses.includes(className)) {
            showToast('غير مصرح لك بالدخول لهذا الفصل', 'error');
            return;
        }
    }
    showClassViewInternal(className);
    updateHash('class', className);
}
function showClassViewInternal(className) {
    isCombinedView = false;
    combinedGroupLabel = '';
    combinedStudents = [];
    currentClass = className;
    localStorage.setItem('currentClass', className);
    document.getElementById('classesView').style.display = 'none';
    document.getElementById('classView').classList.add('active');
    document.getElementById('className').textContent = 'الفصل: ' + className;
    clearSearch();
    loadCouponDataForClass(className);
    const sf = localStorage.getItem('selectedFriday');
    if (sf) { currentFriday = sf; document.getElementById('currentDateText').textContent = sf; loadAttendanceDataForClass(className, sf); }
    else { updateCurrentDateDisplay(); loadAttendanceDataForClass(className); }
    loadPendingRegistrationsForClass(className);

    // If students array is empty (offline, first load), show placeholder until data arrives
    const classStudents = students.filter(s => s['الفصل'] === className);
    if (!classStudents.length && !navigator.onLine) {
        const list = document.getElementById('attendanceList');
        if (list) list.innerHTML = `
            <div style="text-align:center;padding:2.5rem;grid-column:1/-1;color:var(--text-3)">
                <i class="fas fa-wifi" style="font-size:2rem;color:var(--warning);display:block;margin-bottom:12px"></i>
                <div style="font-weight:700;color:var(--warning);margin-bottom:6px">بيانات الفصل غير متاحة أوفلاين</div>
                <div style="font-size:.82rem">يجب فتح الفصل مرة واحدة على الأقل أثناء الاتصال لحفظ بياناته</div>
            </div>`;
    } else {
        renderAttendanceList(className);
    }
    updateClassStats();
    setupLiveSearch();
    updateSaveBtns();
    loadClassUncles(className);
    // Hide back button if uncle can't navigate
    const backBtn = document.getElementById('backBtn');
    if (backBtn) backBtn.style.display = canNavigateClasses() ? 'inline-flex' : 'none';
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ── COMBINED CLASS VIEW ───────────────────────────────────────
let combinedGroupLabel = '';
let isCombinedView = false;
let combinedStudents = []; // students for the active combined view (not injected into main array)

function showCombinedClassView(groupLabel) {
    combinedGroupLabel = groupLabel;
    isCombinedView = true;

    // Permission check
    if (!canNavigateClasses() && window.currentUncle) {
        const assignedClasses = (window.currentUncle.classes || []).map(c => c.class_name);
        const grp = combinedClassGroups.find(g => g.label === groupLabel);
        const grpClasses = grp ? grp.classes : [];
        if (assignedClasses.length > 0 && !grpClasses.some(cn => assignedClasses.includes(cn))) {
            showToast('غير مصرح لك بعرض هذه المجموعة', 'error');
            return;
        }
    }

    // Find the group definition
    const grp = combinedClassGroups.find(g => g.label === groupLabel);
    if (!grp || !grp.classes || !grp.classes.length) {
        showToast('المجموعة غير موجودة أو فارغة', 'error');
        return;
    }
    const grpClassNames = grp.classes; // e.g. ['أولى', 'تانية']

    // Build combinedStudents directly from the already-loaded students array
    // Students keep their real الفصل value — we do NOT change it
    combinedStudents = students.filter(s => grpClassNames.includes(s['الفصل']));

    if (!combinedStudents.length) {
        showToast('لا يوجد أطفال في هذه المجموعة', 'info');
        return;
    }

    // Switch to class view UI
    currentClass = groupLabel; // used as the key for local storage
    localStorage.setItem('currentClass', groupLabel);
    document.getElementById('classesView').style.display = 'none';
    document.getElementById('classView').classList.add('active');
    clearSearch();

    const backBtn = document.getElementById('backBtn');
    if (backBtn) backBtn.style.display = 'inline-flex';

    // Set date
    const sf = localStorage.getItem('selectedFriday');
    if (sf) { currentFriday = sf; document.getElementById('currentDateText').textContent = sf; }
    else updateCurrentDateDisplay();

    // Load local coupon/attendance data using the group label as key
    loadCouponDataForClass(groupLabel);
    loadAttendanceDataForCombined(groupLabel);

    // Render
    renderCombinedAttendanceList();
    updateCombinedClassStats();
    setupLiveSearch();
    updateSaveBtns();

    // Header
    document.getElementById('className').innerHTML =
        `<i class="fas fa-layer-group" style="color:var(--brand)"></i> ${groupLabel}
         <span style="font-size:.68rem;background:var(--brand-bg);color:var(--brand);padding:2px 8px;border-radius:20px;margin-right:8px;font-weight:500">
            ${grpClassNames.join(' + ')}
         </span>`;

    updateHash('combined', groupLabel);
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Load attendance for combined view — loops over all combinedStudents
function loadAttendanceDataForCombined(groupLabel) {
    const dateKey = currentFriday;
    const localKey = `attendanceData_${groupLabel}_${dateKey}`;
    const chKey    = `changedStudents_${groupLabel}_${dateKey}`;
    const local    = JSON.parse(localStorage.getItem(localKey) || '{}');
    const savedChanged = localStorage.getItem(chKey);
    const ids = combinedStudents.map(s => getStudentId(s));
    let restoredChanged = new Set();
    if (savedChanged) {
        try {
            const arr = JSON.parse(savedChanged);
            restoredChanged = new Set(arr.filter(id => ids.includes(id)));
        } catch(e) {}
    }
    changedStudents = restoredChanged;
    combinedStudents.forEach(s => {
        const id = getStudentId(s);
        const srv = getServerAttendanceStatus(s, dateKey);
        originalAttendanceData[id] = srv;
        if (changedStudents.has(id) && id in local) attendanceData[id] = local[id];
        else if (srv !== 'pending') { attendanceData[id] = srv; savedStudents.add(id); }
        else if (id in local) { attendanceData[id] = local[id]; if (local[id] !== 'pending') changedStudents.add(id); }
        else attendanceData[id] = 'pending';
    });
    saveChangedStudentsToLocalStorage(groupLabel, dateKey);
}

// Render attendance list for combined view
function renderCombinedAttendanceList() {
    const list = document.getElementById('attendanceList'); if (!list) return;
    let cs = filterAndSortActiveStudents();
    if (!cs.length) {
        list.innerHTML = '<div style="text-align:center;padding:2rem;grid-column:1/-1;color:var(--text-3)">لا يوجد أطفال</div>';
        return;
    }
    list.innerHTML = cs.map(s => {
        const id = getStudentId(s), st = attendanceData[id] || 'pending';
        const baseC = parseInt(s['كوبونات']||0), addC = parseInt(couponData[id]||0), totC = baseC + addC;
        const srv = getServerAttendanceStatus(s, currentFriday);
        const isInChanged = changedStudents.has(id), isCouponChanged = changedCouponStudents.has(id);
        const isSynced = srv !== 'pending' && st === srv && !isInChanged;
        // Birthday today check
        const _now2 = new Date();
        const _bdParts2 = (s['عيد الميلاد']||'').split('/');
        const isBdayToday2 = _bdParts2.length >= 2 && parseInt(_bdParts2[0]) === _now2.getDate() && parseInt(_bdParts2[1]) - 1 === _now2.getMonth();
        let badges = '';
        if (isBdayToday2) badges += '<span class="bday-row-badge"><i class="fas fa-birthday-cake"></i> عيد ميلاد سعيد! 🎂</span>';
        if (st === 'pending' && !isInChanged) badges += '<span class="status-badge pending"><i class="fas fa-minus"></i> لا بيانات</span>';
        else if (st === 'pending' && isInChanged) badges += '<span class="status-badge local"><i class="fas fa-times-circle"></i> مسح — محلياً</span>';
        else if (isSynced) badges += '<span class="status-badge saved"><i class="fas fa-check"></i> محفوظ</span>';
        else if (isInChanged) badges += '<span class="status-badge local-unsaved"><i class="fas fa-clock"></i> محلياً</span>';
        if (isCouponChanged) badges += `<span class="status-badge coupon-unsaved"><i class="fas fa-star"></i> ${addC>=0?'+':''}${addC}</span>`;
        // Show real class name as a small tag
        const classBadge = `<span style="font-size:.62rem;background:var(--brand-bg);color:var(--brand);padding:1px 6px;border-radius:10px;margin-right:4px">${s['الفصل']}</span>`;
        let name = s['الاسم'] || '---';
        if (searchQuery) name = name.replace(new RegExp(`(${searchQuery})`,'gi'),'<mark style="background:#fde047;border-radius:3px;padding:0 2px;color:#000">$1</mark>');
        const safeImg2 = (s['صورة']||'').replace(/'/g,"\\'");
        const safeName2 = (s['الاسم']||'').replace(/'/g,"\\'");
        const img = s['صورة'] ? `<img src="${window.photoUrl(s['صورة'])}" alt="" class="student-avatar" onclick="showImageModal('${safeImg2}',event)" onerror="this.style.display='none';var n=this.nextElementSibling;if(n)n.style.display='flex'">` : '';
        const fallback = `<div class="student-avatar" ${s['صورة']?'style="display:none"':''}><i class="fas fa-user"></i></div>`;
        const localClass = (isInChanged || isCouponChanged) ? ' has-local' : '';
        const bdayClass2 = isBdayToday2 ? ' bday-row' : '';
        return `<div class="attendance-item ${st}${localClass}${bdayClass2}" id="ai-${id}"
            ontouchstart="_holdStart(event,'${safeName2}')"
            ontouchmove="_holdMove(event)"
            ontouchend="_holdEnd()"
            ontouchcancel="_holdEnd()"
            oncontextmenu="_rowContextMenu(event,'${safeName2}')">
            <div class="student-info" onclick="showStudentDetails('${safeName2}')" style="cursor:pointer">
                ${img}${fallback}
                <div>
                    <div class="student-name profile-link">${name}</div>
                    <div>${classBadge}</div>
                    <div class="status-indicator">${badges}</div>
                </div>
            </div>
            <div class="attendance-actions">
                <span class="student-coupons"><i class="fas fa-star" style="font-size:.7rem"></i> ${totC}${addC>0?`<small style="opacity:.65;font-size:.7em"> +${addC}</small>`:''}</span>
                <div class="coupon-toggle-row">
                    <button class="coupon-toggle-btn minus" onclick="adjustStudentCoupons('${id}',-1)"><i class="fas fa-minus"></i></button>
                    <div class="coupon-value-display" id="cv-${id}" data-idx="2" onclick="toggleCouponValue('${id}')">50</div>
                    <button class="coupon-toggle-btn plus" onclick="adjustStudentCoupons('${id}',1)"><i class="fas fa-plus"></i></button>
                </div>
                <div class="attend-btn-row">
                    <button class="present-btn" onclick="markStudentAttendance('${id}','present')"><i class="fas fa-check"></i> حضور</button>
                    <button class="absent-btn" onclick="markStudentAttendance('${id}','absent')"><i class="fas fa-times"></i> غياب</button>
                </div>
            </div>
        </div>`;
    }).join('');
    updateSearchResultsInfo(cs.length);
    updateCombinedClassStats();
    setTimeout(updateSaveBtns, 50);
    _animateVisibleCards(list);
}

function updateCombinedClassStats() {
    let present = 0, absent = 0, totalC = 0;
    combinedStudents.forEach(s => {
        const id = getStudentId(s), st = attendanceData[id];
        if (st === 'present') present++;
        else if (st === 'absent') absent++;
        totalC += (parseInt(s['كوبونات'])||0) + (parseInt(couponData[id])||0);
    });
    const avg = combinedStudents.length > 0 ? Math.round(totalC / combinedStudents.length) : 0;
    const se = id => document.getElementById(id);
    if (se('tbTotalVal')) se('tbTotalVal').textContent = combinedStudents.length;
    if (se('tbPresentVal')) se('tbPresentVal').textContent = present;
    if (se('tbAbsentVal')) se('tbAbsentVal').textContent = absent;
    if (se('tbCouponsVal')) se('tbCouponsVal').textContent = avg;
}

function showClassesViewInternal() {
    isCombinedView = false;
    combinedGroupLabel = '';
    combinedStudents = [];
    localStorage.removeItem('currentClass'); // Clear so home screen reopens to classes grid
    document.getElementById('classesView').style.display = 'block';
    document.getElementById('classView').classList.remove('active');
    currentClass = '';
    location.hash = ''; // Clear hash so next open goes to classes grid
    updateSaveBtns();
    displayClasses();
}

function showAccountModal() {
    // Allow opening even if currentUncle is not yet loaded from API.
    const u = window.currentUncle || {
        name: localStorage.getItem('uncleName') || '',
        username: localStorage.getItem('uncleUsername') || '',
        role: localStorage.getItem('uncleRole') || '',
        image_url: localStorage.getItem('uncleImageUrl') || ''
    };

    document.getElementById('accountDisplayName').textContent = u.name || '';
    document.getElementById('accountDisplayRole').textContent = u.role || '';
    document.getElementById('aiName').textContent = u.name || '';
    document.getElementById('aiUsername').textContent = u.username || '';
    document.getElementById('aiRole').textContent = u.role || '';
    document.getElementById('uncleProfileName').value = u.name || '';
    document.getElementById('uncleProfileUsername').value = u.username || '';
    document.getElementById('uncleProfileNewPassword').value = '';
    const av = document.getElementById('accountBigAvatar');
    av.src = window.photoUrl(u.image_url || 'https://sunday-school.rf.gd/profile_default..webp');
    hideAccountEditForm();
    document.getElementById('accountModal').classList.add('active');
    stopAutoRefresh();
}
function hideAccountModal() { document.getElementById('accountModal').classList.remove('active'); startAutoRefresh(); }
function showAccountEditForm() { document.getElementById('accountEditForm').classList.add('active'); }
function hideAccountEditForm() { document.getElementById('accountEditForm').classList.remove('active'); }

// ── DROPDOWNS ─────────────────────────────────────────────────
function toggleDropdown(id, btn) {
    const menu = document.getElementById(id), btnEl = document.getElementById(btn);
    if (activeDropdown && activeDropdown !== id) {
        const prev = document.getElementById(activeDropdown); if (prev) prev.classList.remove('open');
        const prevBtn = document.querySelector(`[data-menu="${activeDropdown}"]`); if (prevBtn) prevBtn.classList.remove('open');
    }
    const isOpen = menu.classList.toggle('open');
    btnEl.classList.toggle('open', isOpen);
    activeDropdown = isOpen ? id : null;
}
function closeAllDropdowns() {
    document.querySelectorAll('.dropdown-menu.open').forEach(m => m.classList.remove('open'));
    document.querySelectorAll('.action-strip-btn.open').forEach(b => b.classList.remove('open'));
    activeDropdown = null;
}
document.addEventListener('click', e => { if (!e.target.closest('.action-dropdown')) closeAllDropdowns(); });

// ── VIEWPORT-AWARE CARD ANIMATION ─────────────────────────────
// Only animate items currently visible on screen — items below the fold
// appear instantly when scrolled into view (no stagger delay penalty).
function _animateVisibleCards(container) {
    const items = container ? container.querySelectorAll('.attendance-item') : [];
    if (!items.length) return;
    // Mark all as pre-animation state
    items.forEach(el => { el.style.opacity = '0'; el.style.transform = 'translateY(10px)'; el.style.transition = 'none'; });
    // Use rAF so the browser sees the initial state before transitioning
    requestAnimationFrame(() => {
        requestAnimationFrame(() => {
            const vp = window.innerHeight;
            let visibleDelay = 0;
            items.forEach(el => {
                const rect = el.getBoundingClientRect();
                const inView = rect.top < vp + 40; // a tiny buffer for partial visibility
                el.style.transition = 'opacity .22s ease, transform .22s ease';
                if (inView) {
                    // Stagger only visible items — max ~5 items staggered, rest instant
                    const delay = Math.min(visibleDelay, 5) * 28;
                    setTimeout(() => { el.style.opacity = '1'; el.style.transform = 'translateY(0)'; }, delay);
                    visibleDelay++;
                } else {
                    // Off-screen: no delay, appear instantly when scrolled to
                    el.style.opacity = '1'; el.style.transform = 'translateY(0)';
                }
            });
        });
    });
}

// ── INIT ──────────────────────────────────────────────────────
function initApp() {
    const ul = localStorage.getItem('uncleLoggedIn') === 'true';
    const cl = localStorage.getItem('loggedIn') === 'true';
    if (!ul && !cl) { window.location.href = '/login/'; return; }

    // ── Immediately show the correct name for the current account ──
    // PHP may have already rendered the name server-side, but if the
    // user just switched accounts the PHP value is authoritative.
    // Sync it to #heroName NOW so there's no flash of the old name.
    (function _initHeroName() {
        const phpUncleName  = <?php echo json_encode($uncleName); ?>;
        const phpChurchName = <?php echo json_encode($churchName); ?>;
        const el = document.getElementById('heroName');
        if (el) {
            const name = phpUncleName || phpChurchName || '';
            if (name) el.textContent = name;
        }
        // Also clear any stale uncleName in localStorage if this is a
        // church (non-uncle) login, so loadUncleProfile can't bleed it back.
        if (!ul && cl) {
            try { localStorage.removeItem('uncleName'); } catch(e) {}
        }
    })();

    setupEventListeners();
    if (ul) loadUncleProfile();
    loadChurchSettings();
    initAutoRefresh();

    // Restore view: URL hash takes priority, then last-opened class from localStorage
    const h = location.hash.replace('#', '');
    if (h.startsWith('class=')) {
        window._pendingHashRestore = { type: 'class', value: decodeURIComponent(h.replace('class=', '')) };
    } else if (h.startsWith('combined=')) {
        window._pendingHashRestore = { type: 'combined', value: decodeURIComponent(h.replace('combined=', '')) };
    } else {
        // No hash — check if user had a class open last time (e.g. opened from home screen icon)
        const savedClass = localStorage.getItem('currentClass');
        if (savedClass) {
            window._pendingHashRestore = { type: 'class', value: savedClass };
        }
    }

    loadData();
    updateCurrentDateDisplay();
    setTimeout(() => { setupBirthdayInputListeners(); setupLiveSearch(); setupAllStudentsSearch(); }, 800);

    // If navigating to a class, show it immediately from cache if available
    if (window._pendingHashRestore && window._pendingHashRestore.type === 'class') {
        const className = window._pendingHashRestore.value;
        const cachedRaw = localStorage.getItem('lastStudentsData');
        if (cachedRaw) {
            try {
                const d = JSON.parse(cachedRaw);
                students        = d.students    || d.allStudents || [];
                allStudentsData = d.allStudents || d.students    || students;
                if (d.classes && d.classes.length) classes = d.classes;

                // Show class view shell
                document.getElementById('classesView').style.display = 'none';
                document.getElementById('classView').classList.add('active');
                const cn = document.getElementById('className');
                if (cn) cn.textContent = 'الفصل: ' + className;
                currentClass = className;

                // Render real content straight from cache — no skeleton needed
                loadAttendanceDataForClass(className);
                renderAttendanceList(className);
                updateClassStats();
                updateCurrentDateDisplay();
                window._pendingHashRestore = null; // handled
            } catch(e) {
                // Cache parse failed — fall back to skeleton while online fetch loads
                document.getElementById('classesView').style.display = 'none';
                document.getElementById('classView').classList.add('active');
                currentClass = className;
                const list = document.getElementById('attendanceList');
                if (list) list.innerHTML = '<div class="skeleton-loader" style="grid-column:1/-1">' + Array(6).fill('<div class="skeleton-row"></div>').join('') + '</div>';
            }
        } else if (navigator.onLine) {
            // No cache at all — show skeleton while waiting for API
            document.getElementById('classesView').style.display = 'none';
            document.getElementById('classView').classList.add('active');
            const cn = document.getElementById('className');
            if (cn) cn.textContent = 'الفصل: ' + className;
            currentClass = className;
            const list = document.getElementById('attendanceList');
            if (list) list.innerHTML = '<div class="skeleton-loader" style="grid-column:1/-1">' + Array(6).fill('<div class="skeleton-row"></div>').join('') + '</div>';
        }
    }

    // Only show skeleton on classes grid if no cache and going online to fetch
    if (!currentClass && !localStorage.getItem('lastStudentsData') && navigator.onLine) {
        const grid = document.getElementById('classesGrid');
        if (grid) grid.innerHTML = '<div class="skeleton-loader">' + Array(4).fill('<div class="skeleton-row cls"></div>').join('') + '</div>';
    }
}

function saveDataToLocalStorage() {
    try {
        localStorage.setItem('lastStudentsData', JSON.stringify({
            students,
            allStudents: allStudentsData,
            classes,
            lastUpdated: new Date().toISOString()
        }));
    } catch(e) {}
}

// ── DATA LOADING ──────────────────────────────────────────────
function loadData() {
    // If offline, go straight to cache — don't flash a skeleton over content
    // that's already rendered from the DOMContentLoaded pre-render.
    if (!navigator.onLine) {
        _loadDataFromCache();
        return;
    }

    const grid = document.getElementById('classesGrid');
    if (grid && !currentClass) grid.innerHTML = '<div class="skeleton-loader">' + Array(4).fill('<div class="skeleton-row cls"></div>').join('') + '</div>';
    const list = document.getElementById('attendanceList');
    if (list && currentClass) list.innerHTML = '<div class="skeleton-loader" style="grid-column:1/-1">' + Array(5).fill('<div class="skeleton-row"></div>').join('') + '</div>';

    makeApiCall({ action: 'getData' }, r => {
        if (r.data && Array.isArray(r.data)) students = r.data;
        else if (r.allStudents && Array.isArray(r.allStudents)) students = r.allStudents;
        allStudentsData = students;
        if (r.classes && Array.isArray(r.classes)) classes = r.classes;
        saveDataToLocalStorage();
        // Prune photo cache: drop entries for URLs no longer in student data
        if (window._prunePhotoCache) {
            const activeUrls = new Set(students.map(s => s['صورة']).filter(Boolean));
            window._prunePhotoCache(activeUrls);
        }
        updateDashboardStats();
        if (!currentClass) displayClasses(); // skip if class view already open
        else renderTodayBirthdayBanner(); // always refresh birthday banner
        _maybeSendBirthdayNotification(); // send push if today has birthdays
        if (currentClass) { loadAttendanceDataForClass(currentClass); renderAttendanceList(currentClass); updateClassStats(); }
        // Restore hash-requested view now that real data is available
        if (window._pendingHashRestore) {
            const { type, value } = window._pendingHashRestore;
            window._pendingHashRestore = null;
            if (type === 'class' && value) {
                const match = students.some(s => s['الفصل'] === value);
                if (match) {
                    showClassViewInternal(value);
                } else {
                    const found = [...new Set(students.map(s => s['الفصل']))].find(n => n && n.trim() === value.trim());
                    if (found) showClassViewInternal(found);
                    else showClassViewInternal(value);
                }
            } else if (type === 'combined' && value) {
                showCombinedClassView(value);
            }
        }
        setTimeout(updateSaveBtns, 300);
    }, () => {
        // Network failed — fall back to cached data
        _loadDataFromCache();
    });
}

function _loadDataFromCache() {
    const cached = localStorage.getItem('lastStudentsData');
    if (cached) {
        try {
            const d = JSON.parse(cached);
            students     = d.students || d.allStudents || [];
            allStudentsData = d.allStudents || d.students || students;
            if (d.classes && d.classes.length) classes = d.classes;
            updateDashboardStats();
            if (!currentClass) displayClasses();
            else renderTodayBirthdayBanner();
            updateCurrentDateDisplay();
            if (currentClass) { loadAttendanceDataForClass(currentClass); renderAttendanceList(currentClass); updateClassStats(); }
            // Restore hash view
            if (window._pendingHashRestore) {
                const { type, value } = window._pendingHashRestore;
                window._pendingHashRestore = null;
                if (type === 'class') {
                    const found = students.some(s => s['الفصل'] === value);
                    if (found) showClassViewInternal(value);
                    else if (value) showClassViewInternal(value); // try anyway with cached data
                } else if (type === 'combined' && value) {
                    showCombinedClassView(value);
                }
            }
            showToast('تعمل بدون إنترنت — البيانات من الذاكرة المحلية', 'warning');
        } catch(e) {
            showToast('لا يمكن تحميل البيانات بدون إنترنت', 'error');
        }
    } else {
        showToast('لا يوجد بيانات محفوظة — يرجى الاتصال بالإنترنت', 'error');
    }
    setTimeout(updateSaveBtns, 300);
}

// ── DISPLAY ───────────────────────────────────────────────────
// ── TODAY'S BIRTHDAY BANNER ───────────────────────────────────
function getTodayBirthdays() {
    const now = new Date();
    const todayDay = now.getDate(), todayMonth = now.getMonth(); // month 0-based
    return (allStudentsData.length ? allStudentsData : students).filter(s => {
        if (!s['عيد الميلاد']) return false;
        const p = s['عيد الميلاد'].split('/');
        if (p.length < 2) return false;
        return parseInt(p[0]) === todayDay && parseInt(p[1]) - 1 === todayMonth;
    });
}

function renderTodayBirthdayBanner() {
    const banner = document.getElementById('todayBirthdayBanner');
    const list   = document.getElementById('todayBirthdayList');
    const title  = document.getElementById('todayBirthdayTitle');
    if (!banner || !list) return;
    const kids = getTodayBirthdays();
    if (!kids.length) { banner.classList.remove('show'); return; }
    const label = window.IS_YOUTH ? 'شباب' : 'أطفال';
    title.textContent = `🎂 أعياد ميلاد اليوم! (${kids.length} ${label})`;
    list.innerHTML = kids.map(s => {
        const name = s['الاسم'] || '---';
        const cls  = s['الفصل'] || '';
        const safe = name.replace(/'/g, "\\'");
        return `<div class="bday-banner-chip" onclick="showStudentDetails('${safe}')">
            <i class="fas fa-birthday-cake" style="font-size:.7rem"></i>
            ${name}${cls ? `<span class="bday-chip-class">${cls}</span>` : ''}
        </div>`;
    }).join('');
    banner.classList.add('show');
}

function displayClasses() {
    const grid = document.getElementById('classesGrid');
    let list = classes;
    if (!list || !list.length) {
        const names = [...new Set(students.map(s => s['الفصل'] || 'بدون فصل'))];
        list = names.map((n, i) => ({ id: i + 1, code: n, arabic_name: n }));
    }
    if (!list.length) {
        grid.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--text-3)">لا توجد فصول</div>';
        return;
    }

    // ── "View all together" card — only if view_mode includes 'all' ─
    const showAllCard    = (churchViewMode === 'all' || churchViewMode === 'both');
    const showClassCards = (churchViewMode === 'classes' || churchViewMode === 'both' || !churchViewMode);

    const allLabel  = window.IS_YOUTH ? 'عرض كل الشباب معاً' : 'عرض كل الأطفال معاً';
    const allIcon   = window.IS_YOUTH ? 'fa-users' : 'fa-children';
    const allColor  = window.IS_YOUTH ? '#8b5cf6' : '#4f46e5';
    const allBg     = window.IS_YOUTH ? 'linear-gradient(135deg,#8b5cf6,#6d28d9)' : 'linear-gradient(135deg,#4f46e5,#6366f1)';
    const allCount  = students.length;
    const allTogetherHtml = showAllCard ? `<div class="class-card" onclick="showAllTogetherView()"
        style="--cls-color:${allColor};border:2px dashed ${allColor};position:relative;">
        <div class="class-icon" style="background:${allBg}"><i class="fas ${allIcon}" style="color:white"></i></div>
        <div class="class-name">${allLabel}</div>
        <div class="class-badge" style="background:color-mix(in srgb,${allColor} 12%,white);color:${allColor}">
            <i class="fas fa-user" style="font-size:.6rem"></i> ${allCount} ${window.IS_YOUTH ? 'شاب' : 'طفل'}
        </div>
        <span style="position:absolute;top:6px;left:6px;background:${allColor};color:white;border-radius:4px;font-size:.6rem;padding:1px 5px;">الكل</span>
    </div>` : '';

    // ── Combined class group cards ────────────────────────────
    let combinedHtml = '';
    if (combinedClassGroups && combinedClassGroups.length) {
        combinedHtml = combinedClassGroups.map(g => {
            const label = g.label || 'مجموعة';
            const grpClasses = Array.isArray(g.classes) ? g.classes : [];
            const count = students.filter(s => grpClasses.includes(s['الفصل'])).length;
            return `<div class="class-card combined-class-card" onclick="showCombinedClassView('${escJs(label)}')" style="border:2px solid var(--brand);position:relative;">
                <div class="class-icon" style="background:linear-gradient(135deg,var(--brand),var(--brand-dark))"><i class="fas fa-layer-group" style="color:white"></i></div>
                <div class="class-name">${label}</div>
                <div class="class-badge" style="background:var(--brand-bg);color:var(--brand)"><i class="fas fa-users" style="font-size:.6rem"></i> ${count} ${window.IS_YOUTH ? 'شاب' : 'طفل'}</div>
                <div style="font-size:.68rem;color:var(--text-3);margin-top:4px">${grpClasses.slice(0,3).join(' + ')}${grpClasses.length > 3 ? '...' : ''}</div>
                <span style="position:absolute;top:6px;left:6px;background:var(--brand);color:white;border-radius:4px;font-size:.6rem;padding:1px 5px;">مدمج</span>
            </div>`;
        }).join('');
    }

    const regularHtml = list.map(cls => {
        const name  = cls.arabic_name || cls.code || 'فصل';
        const count = students.filter(s => s['الفصل'] === name).length;
        const color = cls.color || '#4f46e5';
        const iconHtml = getClassIcon(name, cls);
        return `<div class="class-card" onclick="showClassView('${name}')"
            style="--cls-color:${color}">
            <div class="class-icon" style="background:color-mix(in srgb,${color} 15%,white);color:${color}">${iconHtml}</div>
            <div class="class-name">${name}</div>
            <div class="class-badge" style="background:color-mix(in srgb,${color} 12%,white);color:${color}"><i class="fas fa-user" style="font-size:.6rem"></i> ${count} ${window.IS_YOUTH ? 'شاب' : 'طفل'}</div>
        </div>`;
    }).join('');

    const visibleCombined = showClassCards ? combinedHtml : '';
    const visibleRegular  = showClassCards ? regularHtml  : '';
    grid.innerHTML = allTogetherHtml + visibleCombined + visibleRegular;
    renderTodayBirthdayBanner();
}

// ── VIEW ALL TOGETHER ─────────────────────────────────────────
// Shows every student across all classes in a single attendance list.
// Each student still has their class recorded — this is purely a
// display/attendance convenience mode; no data is changed.
function showAllTogetherView() {
    combinedGroupLabel = '__ALL__';
    isCombinedView     = true;
    combinedStudents   = [...students];

    const label = window.IS_YOUTH ? 'كل الشباب' : 'كل الأطفال';

    document.getElementById('classesView').style.display = 'none';
    document.getElementById('classView').classList.add('active');
    document.getElementById('className').textContent = label;
    currentClass = '__ALL__';

    // Restore last selected date if any
    const sf = localStorage.getItem('selectedFriday');
    if (sf) { currentFriday = sf; document.getElementById('currentDateText').textContent = sf; }
    else updateCurrentDateDisplay();

    // Show back button
    const backBtn = document.getElementById('backBtn');
    if (backBtn) backBtn.style.display = 'inline-flex';

    updateHash('all');

    loadAttendanceDataForCombinedAll();
    renderAttendanceList('__ALL__');
    updateClassStats();
    updateSaveBtns();
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Loads attendance for the virtual "all" combined view
function loadAttendanceDataForCombinedAll() {
    const dateKey = currentFriday;
    combinedStudents.forEach(s => {
        const id  = getStudentId(s);
        const srv = getServerAttendanceStatus(s, dateKey);
        originalAttendanceData[id] = srv;
        const localKey = `attendanceData_${s['الفصل']}_${dateKey}`;
        const local    = JSON.parse(localStorage.getItem(localKey) || '{}');
        if (srv !== 'pending') { attendanceData[id] = srv; savedStudents.add(id); }
        else if (id in local)  { attendanceData[id] = local[id]; if (local[id] !== 'pending') changedStudents.add(id); }
        else                     attendanceData[id] = 'pending';
    });
}

function escJs(str) {
    return (str || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'").replace(/"/g, '\\"');
}
function getClassIcon(name, cls) {
    // If the class object has a custom icon defined, use it
    if (cls && cls.icon) {
        if (/^\d+$/.test(cls.icon)) return `<span style="font-weight:900;font-size:1.4em">${cls.icon}</span>`;
        return `<i class="fas ${cls.icon}"></i>`;
    }
    // Fallback built-in map
    const icons = {
        'حضانة':'<i class="fas fa-baby"></i>',
        'أولى':'<span style="font-weight:900;font-size:1.3em">١</span>',
        'تانية':'<span style="font-weight:900;font-size:1.3em">٢</span>',
        'تالتة':'<span style="font-weight:900;font-size:1.3em">٣</span>',
        'رابعة':'<span style="font-weight:900;font-size:1.3em">٤</span>',
        'خامسة':'<span style="font-weight:900;font-size:1.3em">٥</span>',
        'سادسة':'<span style="font-weight:900;font-size:1.3em">٦</span>',
        'سابعة':'<span style="font-weight:900;font-size:1.3em">٧</span>',
        'ثامنة':'<span style="font-weight:900;font-size:1.3em">٨</span>',
    };
    return icons[name] || `<i class="fas fa-chalkboard-teacher"></i>`;
}
function updateDashboardStats() {
    const total = allStudentsData.length, cm = new Date().getMonth();
    const births = allStudentsData.filter(s => { if (!s['عيد الميلاد']) return false; const p = s['عيد الميلاد'].split('/'); return p.length >= 2 && parseInt(p[1])-1 === cm; }).length;
    const avgC = total > 0 ? Math.round(allStudentsData.reduce((a, s) => a + (parseInt(s['كوبونات'])||0), 0) / total) : 0;
    const classNames = [...new Set(allStudentsData.map(s => s['الفصل']))];
    const se = id => document.getElementById(id);
    if (se('totalStudents')) se('totalStudents').textContent = total;
    if (se('totalClasses')) se('totalClasses').textContent = classNames.length;
    if (se('birthdaysThisMonth')) se('birthdaysThisMonth').textContent = births;
    if (se('averageCoupons')) se('averageCoupons').textContent = avgC;
}

// ── CLASS STATS ───────────────────────────────────────────────
function updateClassStats() {
    if (isCombinedView) { updateCombinedClassStats(); return; }
    if (!currentClass) return;
    const cs = students.filter(s => s['الفصل'] === currentClass);
    let present = 0, absent = 0, totalC = 0;
    cs.forEach(s => {
        const id = getStudentId(s), st = attendanceData[id];
        if (st === 'present') present++;
        else if (st === 'absent') absent++;
        totalC += (parseInt(s['كوبونات'])||0) + (parseInt(couponData[id])||0);
    });
    const avg = cs.length > 0 ? Math.round(totalC/cs.length) : 0;
    const se = id => document.getElementById(id);
    if (se('tbTotalVal')) se('tbTotalVal').textContent = cs.length;
    if (se('tbPresentVal')) se('tbPresentVal').textContent = present;
    if (se('tbAbsentVal')) se('tbAbsentVal').textContent = absent;
    if (se('tbCouponsVal')) se('tbCouponsVal').textContent = avg;
}

// ── ATTENDANCE DATA ───────────────────────────────────────────
function getServerAttendanceStatus(student, date) {
    if (!date) return 'pending';
    const check = v => { v = v?.toString().trim(); return v === 'ح' || v === 'حاضر' || v === 'present' ? 'present' : (v === 'غ' || v === 'غائب' || v === 'absent' ? 'absent' : null); };
    let r = check(student[date]); if (r) return r;
    if (student._allAttendance) { r = check(student._allAttendance[date]); if (r) return r; }
    return 'pending';
}
function loadAttendanceDataForClass(className, date = null) {
    if (!className) return;
    const dateKey = date || currentFriday;
    const localKey = `attendanceData_${className}_${dateKey}`;
    const chKey = `changedStudents_${className}_${dateKey}`;
    const local = JSON.parse(localStorage.getItem(localKey) || '{}');
    const savedChanged = localStorage.getItem(chKey);
    const cs = students.filter(s => s['الفصل'] === className);
    let restoredChanged = new Set();
    if (savedChanged) { try { const arr = JSON.parse(savedChanged); const ids = cs.map(s => getStudentId(s)); restoredChanged = new Set(arr.filter(id => ids.includes(id))); } catch(e) {} }
    changedStudents = restoredChanged;
    cs.forEach(s => {
        const id = getStudentId(s), srv = getServerAttendanceStatus(s, dateKey);
        originalAttendanceData[id] = srv;
        if (changedStudents.has(id) && id in local) attendanceData[id] = local[id];
        else if (srv !== 'pending') { attendanceData[id] = srv; savedStudents.add(id); }
        else if (id in local) { attendanceData[id] = local[id]; if (local[id] !== 'pending') changedStudents.add(id); }
        else attendanceData[id] = 'pending';
    });
    saveChangedStudentsToLocalStorage(className, dateKey);
}
function saveAttendanceDataForClass(className, date = null) {
    if (!className) return;
    const dateKey = date || currentFriday, cs = students.filter(s => s['الفصل'] === className), data = {};
    cs.forEach(s => {
        const id = getStudentId(s), cur = attendanceData[id] || 'pending';
        if (changedStudents.has(id)) data[id] = cur;
        else if (cur !== 'pending') data[id] = cur;
    });
    localStorage.setItem(`attendanceData_${className}_${dateKey}`, JSON.stringify(data));
}
function saveChangedStudentsToLocalStorage(className, date = null) {
    if (!className) return;
    localStorage.setItem(`changedStudents_${className}_${date || currentFriday}`, JSON.stringify([...changedStudents]));
}
function loadCouponDataForClass(className) {
    couponData = JSON.parse(localStorage.getItem(`couponData_${className}`) || '{}');
    const sc = localStorage.getItem(`savedCoupons_${className}`);
    savedCouponStudents = sc ? new Set(JSON.parse(sc)) : new Set();
    const chk = localStorage.getItem(`changedCouponStudents_${className}`);
    changedCouponStudents = chk ? new Set(JSON.parse(chk)) : new Set();
    students.filter(s => s['الفصل'] === className).forEach(s => { const id = getStudentId(s); originalCouponData[id] = parseInt(s['كوبونات الالتزام']||0); });
}
function saveCouponDataForClass(className) {
    if (!className) return;
    localStorage.setItem(`couponData_${className}`, JSON.stringify(couponData || {}));
    localStorage.setItem(`changedCouponStudents_${className}`, JSON.stringify([...changedCouponStudents]));
}

// ── SWIPE TO CLOSE ────────────────────────────────────────────
function initSwipeToClose(overlay) {
    const modal = overlay.querySelector('.modal');
    if (!modal) return;

    let startX = 0, startY = 0, curX = 0, dragging = false, isHorizontal = null;

    const onStart = e => {
        // If touch starts inside a scrollable table, don't intercept
        const target = e.target || e.srcElement;
        if (target && target.closest && (
            target.closest('.table-zoom-wrap') ||
            target.closest('.table-container') ||
            target.closest('.sheet-container')
        )) {
            dragging = false;
            return;
        }
        const t = e.touches ? e.touches[0] : e;
        startX = t.clientX; startY = t.clientY;
        curX = 0; dragging = true; isHorizontal = null;
        modal.style.transition = 'none';
    };

    const onMove = e => {
        if (!dragging) return;
        const t = e.touches ? e.touches[0] : e;
        const dx = t.clientX - startX;
        const dy = t.clientY - startY;

        // Determine direction on first significant move
        if (isHorizontal === null && (Math.abs(dx) > 6 || Math.abs(dy) > 6)) {
            isHorizontal = Math.abs(dx) > Math.abs(dy);
        }

        // Only handle rightward horizontal swipe (RTL: "away from content")
        if (!isHorizontal) return;
        // In RTL layout, sliding left (negative dx) = closing direction
        // but swiping right (positive dx) also feels natural on phones
        // so we accept both left and right, but predominantly left in RTL
        curX = dx; // allow both directions, close on threshold
        const absDx = Math.abs(curX);
        modal.style.transform = `translateX(${curX}px)`;
        overlay.style.background = `rgba(0,0,0,${Math.max(0, .45 - absDx / 500)})`;
    };

    const onEnd = () => {
        if (!dragging) return;
        dragging = false; modal.style.transition = '';
        if (isHorizontal && Math.abs(curX) > 100) {
            // Animate off screen in swipe direction
            modal.style.transition = 'transform .2s ease';
            modal.style.transform = `translateX(${curX > 0 ? '120%' : '-120%'})`;
            setTimeout(() => {
                overlay.classList.remove('active');
                modal.style.transform = '';
                modal.style.transition = '';
                overlay.style.background = '';
                startAutoRefresh();
            }, 210);
        } else {
            modal.style.transform = '';
            overlay.style.background = '';
        }
        curX = 0; isHorizontal = null;
    };

    modal.addEventListener('touchstart', onStart, { passive: true });
    modal.addEventListener('touchmove',  onMove,  { passive: true });
    modal.addEventListener('touchend',   onEnd);
}

// ── RENDER ATTENDANCE ─────────────────────────────────────────
function renderAttendanceList(className) {
    if (isCombinedView) { renderCombinedAttendanceList(); return; }
    const list = document.getElementById('attendanceList'); if (!list) return;
    let cs = filterAndSortActiveStudents();
    if (searchQuery && !cs.length) {
        list.innerHTML = `<div style="text-align:center;padding:2rem;grid-column:1/-1"><i class="fas fa-search" style="font-size:2rem;color:var(--text-3);margin-bottom:1rem;display:block"></i><p style="color:var(--text-3)">لا نتائج لـ "${searchQuery}"</p><button class="btn btn-sm btn-ghost" onclick="clearSearch()" style="margin-top:10px"><i class="fas fa-times"></i> عرض الكل</button></div>`;
        updateSearchResultsInfo(0); return;
    }
    if (!cs.length) { list.innerHTML = '<div style="text-align:center;padding:2rem;grid-column:1/-1;color:var(--text-3)">لا يوجد أطفال في هذا الفصل</div>'; return; }

    list.innerHTML = cs.map(s => {
        const id = getStudentId(s), st = attendanceData[id] || 'pending';
        const baseC = parseInt(s['كوبونات']||0), addC = parseInt(couponData[id]||0), totC = baseC + addC;
        const srv = getServerAttendanceStatus(s, currentFriday);
        const isInChanged = changedStudents.has(id), isCouponChanged = changedCouponStudents.has(id);
        const isSynced = srv !== 'pending' && st === srv && !isInChanged;
        // Birthday today check
        const _now = new Date();
        const _bdParts = (s['عيد الميلاد']||'').split('/');
        const isBdayToday = _bdParts.length >= 2 && parseInt(_bdParts[0]) === _now.getDate() && parseInt(_bdParts[1]) - 1 === _now.getMonth();
        let badges = '';
        if (isBdayToday) badges += '<span class="bday-row-badge"><i class="fas fa-birthday-cake"></i> عيد ميلاد سعيد! 🎂</span>';
        if (st === 'pending' && !isInChanged) badges += '<span class="status-badge pending"><i class="fas fa-minus"></i> لا بيانات</span>';
        else if (st === 'pending' && isInChanged) badges += '<span class="status-badge local"><i class="fas fa-times-circle"></i> مسح — محلياً</span>';
        else if (isSynced) badges += '<span class="status-badge saved"><i class="fas fa-check"></i> محفوظ</span>';
        else if (isInChanged) badges += '<span class="status-badge local-unsaved"><i class="fas fa-clock"></i> محفوظ محلياً</span>';
        if (isCouponChanged) badges += `<span class="status-badge coupon-unsaved"><i class="fas fa-star"></i> ${addC >= 0?'+':''}${addC}</span>`;
        let name = s['الاسم'] || '---';
        if (searchQuery) name = name.replace(new RegExp(`(${searchQuery})`,'gi'),'<mark style="background:#fde047;border-radius:3px;padding:0 2px;color:#000">$1</mark>');
        const safeImg = (s['صورة']||'').replace(/'/g,"\\'");
        const safeName = (s['الاسم']||'').replace(/'/g,"\\'");
        const img = s['صورة']
            ? `<img src="${window.photoUrl(s['صورة'])}" alt="" class="student-avatar" onclick="showImageModal('${safeImg}',event)" onerror="this.style.display='none';var n=this.nextElementSibling;if(n)n.style.display='flex'">`
            : '';
        const fallback = `<div class="student-avatar" ${s['صورة']?'style="display:none"':''}><i class="fas fa-user"></i></div>`;
        const localClass = (isInChanged || isCouponChanged) ? ' has-local' : '';
        const bdayClass  = isBdayToday ? ' bday-row' : '';
        return `<div class="attendance-item ${st}${localClass}${bdayClass}" id="ai-${id}"
            ontouchstart="_holdStart(event,'${safeName}')"
            ontouchmove="_holdMove(event)"
            ontouchend="_holdEnd()"
            ontouchcancel="_holdEnd()"
            oncontextmenu="_rowContextMenu(event,'${safeName}')">
            <div class="student-info" onclick="showStudentDetails('${safeName}')" style="cursor:pointer">
                ${img}${fallback}
                <div>
                    <div class="student-name profile-link">${name}</div>
                    <div class="status-indicator">${badges}</div>
                </div>
            </div>
            <div class="attendance-actions">
                <span class="student-coupons"><i class="fas fa-star" style="font-size:.7rem"></i> ${totC}${addC>0?`<small style="opacity:.65;font-size:.7em"> +${addC}</small>`:''}</span>
                <div class="coupon-toggle-row">
                    <button class="coupon-toggle-btn minus" onclick="adjustStudentCoupons('${id}',-1)"><i class="fas fa-minus"></i></button>
                    <div class="coupon-value-display" id="cv-${id}" data-idx="2" onclick="toggleCouponValue('${id}')">50</div>
                    <button class="coupon-toggle-btn plus" onclick="adjustStudentCoupons('${id}',1)"><i class="fas fa-plus"></i></button>
                </div>
                <div class="attend-btn-row">
                    <button class="present-btn" onclick="markStudentAttendance('${id}','present')"><i class="fas fa-check"></i> حضور</button>
                    <button class="absent-btn" onclick="markStudentAttendance('${id}','absent')"><i class="fas fa-times"></i> غياب</button>
                </div>
            </div>
        </div>`;
    }).join('');
    updateSearchResultsInfo(cs.length);
    updateClassStats();
    setTimeout(updateSaveBtns, 50);
    // Animate only items that are already in the viewport — no waiting for off-screen ones
    _animateVisibleCards(list);
}

// ── ATTENDANCE ACTIONS ────────────────────────────────────────
function markStudentAttendance(studentId, status) {
    const cur = attendanceData[studentId] || 'pending';
    const srv = originalAttendanceData[studentId] || 'pending';

    if (cur === status) {
        attendanceData[studentId] = 'pending';
        if (srv === 'pending') changedStudents.delete(studentId);
        else changedStudents.add(studentId);
    } else {
        attendanceData[studentId] = status;
        if (status === srv) changedStudents.delete(studentId);
        else changedStudents.add(studentId);
    }
    saveAttendanceDataForClass(currentClass); saveChangedStudentsToLocalStorage(currentClass);
    // Update only the affected row — no full list re-render (avoids blink)
    _updateAttendanceRow(studentId);
    updateClassStats(); updateSaveBtns();
}

function _updateAttendanceRow(studentId) {
    const row = document.getElementById('ai-' + studentId);
    if (!row) { renderAttendanceList(currentClass); return; }

    const st = attendanceData[studentId] || 'pending';
    const isInChanged = changedStudents.has(studentId);
    const isCouponChanged = changedCouponStudents.has(studentId);

    const allList = isCombinedView ? combinedStudents : students;
    const s = allList.find(x => getStudentId(x) === studentId);
    const srv = s ? getServerAttendanceStatus(s, currentFriday) : 'pending';
    const isSynced = srv !== 'pending' && st === srv && !isInChanged;

    // Birthday today
    const _now = new Date();
    const _bdParts = (s ? (s['عيد الميلاد']||'') : '').split('/');
    const isBdayToday = _bdParts.length >= 2 && parseInt(_bdParts[0]) === _now.getDate() && parseInt(_bdParts[1]) - 1 === _now.getMonth();

    // Update row class
    let cls = 'attendance-item ' + st;
    if (isInChanged || isCouponChanged) cls += ' has-local';
    if (isBdayToday) cls += ' bday-row';
    row.className = cls;

    // Update badges
    let badges = '';
    if (isBdayToday) badges += '<span class="bday-row-badge"><i class="fas fa-birthday-cake"></i> عيد ميلاد سعيد! 🎂</span>';
    if (st === 'pending' && !isInChanged) badges += '<span class="status-badge pending"><i class="fas fa-minus"></i> لا بيانات</span>';
    else if (st === 'pending' && isInChanged) badges += '<span class="status-badge local"><i class="fas fa-times-circle"></i> مسح — محلياً</span>';
    else if (isSynced) badges += '<span class="status-badge saved"><i class="fas fa-check"></i> محفوظ</span>';
    else if (isInChanged) badges += '<span class="status-badge local-unsaved"><i class="fas fa-clock"></i> محفوظ محلياً</span>';
    const addC = parseInt(couponData[studentId] || 0);
    if (isCouponChanged) badges += `<span class="status-badge coupon-unsaved"><i class="fas fa-star"></i> ${addC >= 0 ? '+' : ''}${addC}</span>`;

    const indicator = row.querySelector('.status-indicator');
    if (indicator) indicator.innerHTML = badges;
}
function markAllPresent() {
    const list = isCombinedView ? combinedStudents : students.filter(s => s['الفصل'] === currentClass);
    list.forEach(s => markStudentAttendance(getStudentId(s), 'present'));
    showToast('تم تسجيل حضور الجميع', 'success');
}
function markAllAbsent() {
    const list = isCombinedView ? combinedStudents : students.filter(s => s['الفصل'] === currentClass);
    list.forEach(s => markStudentAttendance(getStudentId(s), 'absent'));
    showToast('تم تسجيل غياب الجميع', 'success');
}

// ── COUPON TOGGLE ─────────────────────────────────────────────
let globalCouponIdx = 2;
function toggleCouponValue(id) {
    globalCouponIdx = (globalCouponIdx + 1) % couponPresetValues.length;
    const newVal = couponPresetValues[globalCouponIdx];
    document.querySelectorAll('.coupon-value-display').forEach((el, i) => {
        setTimeout(() => {
            el.textContent = newVal; el.setAttribute('data-idx', globalCouponIdx);
            el.style.transform = 'scale(1.2)';
            setTimeout(() => { el.style.transform = ''; el.style.background = ''; el.style.color = ''; }, 270);
        }, i * 16);
    });
}
function adjustStudentCoupons(studentId, dir) {
    const el = document.getElementById(`cv-${studentId}`);
    const val = el ? parseInt(el.textContent)||50 : 50;
    const cur = parseInt(couponData[studentId]||0);
    couponData[studentId] = cur + dir * val;
    if (couponData[studentId] !== 0) { changedCouponStudents.add(studentId); savedCouponStudents.delete(studentId); } else changedCouponStudents.delete(studentId);
    saveCouponDataForClass(currentClass); renderAttendanceList(currentClass); updateClassStats(); updateSaveBtns();
    showToast(`${dir>0?'تم إضافة':'تم خصم'} ${val} كوبون`, 'success');
}
function addCouponsToAll(amount) {
    students.filter(s => s['الفصل'] === currentClass).forEach(s => { const id = getStudentId(s); couponData[id] = (couponData[id]||0) + amount; changedCouponStudents.add(id); savedCouponStudents.delete(id); });
    saveCouponDataForClass(currentClass); renderAttendanceList(currentClass); updateClassStats(); updateSaveBtns();
    showToast(`تم إضافة ${amount} كوبون للجميع`, 'success');
}
function resetCouponDataForClass(className) {
    couponData = {}; changedCouponStudents.clear(); savedCouponStudents.clear();
    localStorage.removeItem(`couponData_${className}`); localStorage.removeItem(`changedCouponStudents_${className}`); localStorage.removeItem(`savedCoupons_${className}`);
    renderAttendanceList(currentClass); updateClassStats(); updateSaveBtns(); showToast('تم إعادة تعيين الكوبونات', 'info');
}

// ── SAVE BUTTONS ──────────────────────────────────────────────
function updateSaveBtns() {
    const at = document.getElementById('submitAttendance');
    const ct = document.getElementById('submitCoupons');
    const un = document.getElementById('saveAllBtn');
    if (!at || !ct || !un) return;

    const list = isCombinedView ? combinedStudents : students.filter(s => s['الفصل'] === currentClass);
    const ids  = list.map(s => getStudentId(s));
    const achg = [...changedStudents].filter(id => ids.includes(id)).length;
    const cchg = [...changedCouponStudents].filter(id => ids.includes(id)).length;
    const tot  = achg + cchg;

    // helper: icon + label/count wrapped together
    // Mobile: column (icon top, bottom-row below)
    // Desktop: row (icon left, bottom-row right) via CSS flex-direction override
    const btn = (icon, label, count) =>
        `<i class="${icon}"></i>`
        + `<span class="save-btn-bottom">`
        +   `<span class="save-btn-label">${label}</span>`
        +   (count > 0 ? `<span class="save-count">${count}</span>` : '')
        + `</span>`;

    // Attendance
    at.disabled = achg === 0;
    at.innerHTML = btn('fas fa-user-check', 'الحضور', achg);
    at.title = achg > 0 ? `حفظ حضور ${achg} طفل` : 'لا تغييرات في الحضور';

    // Coupons
    ct.disabled = cchg === 0;
    ct.innerHTML = btn('fas fa-star', 'الكوبونات', cchg);
    ct.title = cchg > 0 ? `حفظ كوبونات ${cchg} طفل` : 'لا تغييرات في الكوبونات';

    // Status / unsaved indicator
    un.disabled = tot === 0;
    if (tot > 0) {
        un.innerHTML = btn('fas fa-exclamation-circle', 'احفظ الكل', tot);
        un.title = `${tot} تغيير غير محفوظ — انقر للتفاصيل`;
    } else {
        un.innerHTML = btn('fas fa-check-circle', 'محفوظ', 0);
        un.title = 'جميع التغييرات محفوظة';
    }
}

async function submitCoupons() {
    if (!currentClass || changedCouponStudents.size === 0) { showToast('لا توجد تغييرات', 'info'); return; }
    if (!(await _isActuallyOnline(true))) {
        showToast('أنت غير متصل — الكوبونات محفوظة محلياً وستُرفع عند عودة الإنترنت', 'warning', { dur: 6000 });
        return;
    }
    const btn = document.getElementById('submitCoupons');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
    const sourceList = isCombinedView ? combinedStudents : students;
    const records = [];
    changedCouponStudents.forEach(id => {
        const s = sourceList.find(s => getStudentId(s) === id);
        if (s) records.push({ studentName: s['الاسم'].trim(), coupons: (parseInt(s['كوبونات الالتزام']||0)) + (parseInt(couponData[id]||0)) });
    });
    const fd = new FormData(); fd.append('action','updateCoupons'); fd.append('className',currentClass); fd.append('couponData',JSON.stringify(records));
    fetch(API_URL,{method:'POST',body:fd}).then(r=>r.json()).then(d => {
        if (d.success) {
            showToast(`تم حفظ كوبونات ${records.length} طالب`, 'success', {dur:7000, refresh:true});
            changedCouponStudents.forEach(id => { savedCouponStudents.add(id); });
            changedCouponStudents.clear(); couponData = {};
            saveCouponDataForClass(currentClass); renderAttendanceList(currentClass); updateSaveBtns();
            _sendSyncCompletePush(records.length, 'coupons');
            setTimeout(loadData, 1200);
        } else showToast('فشل: '+d.message, 'error');
    }).catch(() => showToast('خطأ في الاتصال','error')).finally(() => { btn.disabled = false; updateSaveBtns(); });
}
function showUnsavedModal() {
    // Gather ALL locally-stored pending changes across ALL dates for current class
    const cls = isCombinedView ? (combinedGroupLabel || currentClass) : currentClass;
    const list = isCombinedView ? combinedStudents : students.filter(s => s['الفصل'] === cls);
    const ids  = list.map(s => getStudentId(s));

    // Find all localStorage keys for attendance changes for this class
    const allKeys = Object.keys(localStorage);
    const atDateEntries = []; // {date, id, status}
    const cpItems = [...changedCouponStudents].filter(id => ids.includes(id));

    // Scan all attendance change keys: changedStudents_{class}_{date}
    allKeys.forEach(k => {
        const prefix = `changedStudents_${cls}_`;
        if (!k.startsWith(prefix)) return;
        const date = k.slice(prefix.length);
        try {
            const changedIds = JSON.parse(localStorage.getItem(k) || '[]');
            const localData  = JSON.parse(localStorage.getItem(`attendanceData_${cls}_${date}`) || '{}');
            changedIds.forEach(id => {
                if (!ids.includes(id)) return;
                const s = list.find(s => getStudentId(s) === id);
                const st = localData[id] || 'pending';
                atDateEntries.push({ date, id, name: s?.['الاسم'] || id, status: st });
            });
        } catch(e) {}
    });

    // Group by date
    const byDate = {};
    atDateEntries.forEach(e => {
        if (!byDate[e.date]) byDate[e.date] = [];
        byDate[e.date].push(e);
    });

    let html = '';

    // Attendance section grouped by date
    if (atDateEntries.length) {
        html += `<div style="margin-bottom:16px">
            <div style="font-weight:700;color:var(--success);margin-bottom:10px;display:flex;align-items:center;gap:6px">
                <i class="fas fa-user-check"></i> تغييرات الحضور (${atDateEntries.length})
            </div>`;

        Object.entries(byDate).sort(([a],[b]) => parseDate(b)-parseDate(a)).forEach(([date, items]) => {
            const isCurrent = date === currentFriday;
            html += `<div style="background:var(--surface-3);border-radius:var(--r-lg);padding:10px 12px;margin-bottom:8px;border:1.5px solid ${isCurrent?'var(--brand)':'var(--border-solid)'}">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;gap:6px">
                    <span style="font-size:.78rem;font-weight:800;color:${isCurrent?'var(--brand)':'var(--text-2)'};display:flex;align-items:center;gap:5px">
                        <i class="fas fa-calendar-alt" style="font-size:.7rem"></i> ${date}
                        ${isCurrent?'<span style="background:var(--brand);color:#fff;font-size:.65rem;padding:1px 6px;border-radius:20px">الحالي</span>':''}
                    </span>
                    ${!isCurrent ? `<button class="btn btn-ghost btn-xs" onclick="_jumpToDate('${date}')" title="الانتقال لهذا التاريخ">
                        <i class="fas fa-external-link-alt"></i> انتقل
                    </button>` : ''}
                </div>`;
            items.forEach(e => {
                let bc='pending', lbl='<i class="fas fa-times-circle"></i> مسح';
                if(e.status==='present'){bc='saved';lbl='<i class="fas fa-check"></i> حاضر';}
                else if(e.status==='absent'){bc='changed';lbl='<i class="fas fa-times"></i> غائب';}
                html += `<div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;border-bottom:1px solid var(--border-solid);font-size:.82rem;gap:6px">
                    <span style="flex:1;font-weight:600">${e.name}</span>
                    <span class="status-badge ${bc}" style="flex-shrink:0">${lbl}</span>
                    <button class="btn btn-danger btn-xs" style="flex-shrink:0;width:24px;height:24px;padding:0;min-width:0;border-radius:50%" 
                        onclick="_removeUnsavedEntry('${e.id}','${date}')" title="إزالة هذا التغيير">
                        <i class="fas fa-times" style="font-size:.6rem"></i>
                    </button>
                </div>`;
            });
            html += `</div>`;
        });
        html += `</div>`;
    }

    // Coupon section
    if (cpItems.length) {
        html += `<div><div style="font-weight:700;color:var(--coupon);margin-bottom:8px;display:flex;align-items:center;gap:6px">
            <i class="fas fa-star"></i> تغييرات الكوبونات (${cpItems.length})
        </div>`;
        cpItems.forEach(id => {
            const s = list.find(s => getStudentId(s) === id);
            const add = parseInt(couponData[id]||0);
            html += `<div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;border-bottom:1px solid var(--border-solid);font-size:.82rem;gap:6px">
                <span style="flex:1;font-weight:600">${s?.['الاسم']||id}</span>
                <span class="status-badge coupon-unsaved" style="flex-shrink:0">${add>0?'+':''}${add} <i class="fas fa-star"></i></span>
                <button class="btn btn-danger btn-xs" style="flex-shrink:0;width:24px;height:24px;padding:0;min-width:0;border-radius:50%"
                    onclick="_removeUnsavedCoupon('${id}')" title="إزالة تغيير الكوبون">
                    <i class="fas fa-times" style="font-size:.6rem"></i>
                </button>
            </div>`;
        });
        html += `</div>`;
    }

    if (!atDateEntries.length && !cpItems.length) {
        if (navigator.onLine) {
            html = '<p style="text-align:center;color:var(--text-3);padding:24px">' +
                '<i class="fas fa-check-circle" style="color:var(--success);font-size:2.5rem;display:block;margin-bottom:12px"></i>' +
                '<span style="font-weight:700;color:var(--success);display:block;margin-bottom:6px">كل شيء محفوظ على السيرفر!</span>' +
                'لا توجد تغييرات معلقة في هذا الفصل.' +
                '</p>';
        } else {
            html = '<p style="text-align:center;color:var(--text-3);padding:24px">' +
                '<i class="fas fa-wifi-slash" style="color:var(--warning);font-size:2.5rem;display:block;margin-bottom:12px"></i>' +
                '<span style="font-weight:700;color:var(--warning);display:block;margin-bottom:6px">غير متصل بالإنترنت</span>' +
                'لا توجد تغييرات معلقة في هذا الفصل.' +
                '</p>';
        }
    }

    // Add sync-now button at the bottom when online and there are changes
    if ((atDateEntries.length || cpItems.length) && navigator.onLine) {
        html += `<div style="margin-top:16px;padding-top:12px;border-top:1.5px solid var(--border-solid);display:flex;gap:8px">
            <button class="btn btn-success" style="flex:1" onclick="saveAllData();document.getElementById('unsavedModal').classList.remove('active')">
                <i class="fas fa-cloud-upload-alt"></i> رفع الكل الآن
            </button>
            <button class="btn btn-danger" style="flex:1" onclick="_clearAllUnsaved()">
                <i class="fas fa-trash"></i> مسح الكل
            </button>
        </div>`;
    } else if ((atDateEntries.length || cpItems.length) && !navigator.onLine) {
        html += `<div style="margin-top:16px;padding:10px 14px;border-radius:var(--r-md);background:var(--warning-bg);border:1.5px solid rgba(245,158,11,.3);display:flex;align-items:center;gap:8px;font-size:.82rem;color:var(--warning-dark)">
            <i class="fas fa-wifi" style="font-size:1rem;flex-shrink:0"></i>
            <span>ستُرفع هذه التغييرات تلقائياً عند عودة الإنترنت</span>
        </div>`;
    }

    document.getElementById('unsavedModalContent').innerHTML = html;
    document.getElementById('unsavedModal').classList.add('active');
}

function _removeUnsavedEntry(studentId, date) {
    // Remove from changedStudents for that date
    const cls = isCombinedView ? (combinedGroupLabel || currentClass) : currentClass;
    const key = `changedStudents_${cls}_${date}`;
    try {
        let arr = JSON.parse(localStorage.getItem(key) || '[]');
        arr = arr.filter(id => id !== studentId);
        localStorage.setItem(key, JSON.stringify(arr));
        // Also update the local attendance data back to server value
        const localKey = `attendanceData_${cls}_${date}`;
        const local = JSON.parse(localStorage.getItem(localKey) || '{}');
        delete local[studentId];
        localStorage.setItem(localKey, JSON.stringify(local));
    } catch(e) {}
    // If it's the current date, also update in-memory state
    if (date === currentFriday) {
        changedStudents.delete(studentId);
        attendanceData[studentId] = originalAttendanceData[studentId] || 'pending';
        renderAttendanceList(currentClass);
        updateSaveBtns();
    }
    showToast('تم إزالة التغيير', 'info');
    showUnsavedModal(); // refresh modal
}

function _removeUnsavedCoupon(studentId) {
    changedCouponStudents.delete(studentId);
    delete couponData[studentId];
    saveCouponDataForClass(currentClass);
    renderAttendanceList(currentClass);
    updateSaveBtns();
    showToast('تم إزالة تغيير الكوبون', 'info');
    showUnsavedModal();
}

function _jumpToDate(date) {
    document.getElementById('unsavedModal').classList.remove('active');
    loadFridayAttendance(date);
}

// ── SUBMIT ────────────────────────────────────────────────────
async function submitAttendance() {
    if (!currentClass || changedStudents.size === 0) { showToast('لا توجد تغييرات للحفظ', 'info'); return; }
    if (!(await _isActuallyOnline(true))) {
        showToast('أنت غير متصل — التغييرات محفوظة محلياً وستُرفع عند عودة الإنترنت', 'warning', { dur: 6000 });
        return;
    }
    const btn = document.getElementById('submitAttendance');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

    let date = currentFriday;
    if (!date) { updateCurrentDateDisplay(); date = currentFriday; }

    if (isCombinedView) {
        // Group changed students by their real class name
        const byClass = {};
        changedStudents.forEach(id => {
            const s = combinedStudents.find(s => getStudentId(s) === id);
            if (!s) return;
            const realClass = s['الفصل'];
            if (!byClass[realClass]) byClass[realClass] = [];
            byClass[realClass].push({ studentName: s['الاسم'].trim(), status: attendanceData[id] || 'pending' });
        });

        // Fire one API call per real class
        const classNames = Object.keys(byClass);
        let done = 0, totalSaved = 0;
        classNames.forEach(cn => {
            const records = byClass[cn];
            makeApiCall({ action: 'submitAttendance', className: cn, attendanceData: JSON.stringify(records), date }, r => {
                totalSaved += r.savedCount || 0;
                done++;
                if (done === classNames.length) {
                    showToast(`تم حفظ ${totalSaved} طالب`, 'success', {dur:7000, refresh:true});
                    changedStudents.forEach(id => { savedStudents.add(id); originalAttendanceData[id] = attendanceData[id] || 'pending'; });
                    changedStudents.clear();
                    renderAttendanceList(currentClass); updateSaveBtns();
                    _sendSyncCompletePush(totalSaved, 'attendance');
                    setTimeout(loadData, 1200);
                }
            }, e => { showToast('فشل الحفظ: ' + e, 'error'); btn.disabled = false; updateSaveBtns(); });
        });
        return;
    }

    // Normal single-class submit
    const records = [];
    changedStudents.forEach(id => {
        const s = students.find(s => getStudentId(s) === id);
        if (!s) return;
        const st = attendanceData[id] || 'pending';
        // Include all changed students — 'pending' means "clear this record on server"
        records.push({ studentName: s['الاسم'].trim(), status: st });
    });
    if (!records.length) { btn.disabled = false; updateSaveBtns(); return; }

    makeApiCall({ action: 'submitAttendance', className: currentClass, attendanceData: JSON.stringify(records), date }, r => {
        showToast(`تم حفظ ${records.length} طالب`, 'success', {dur:7000, refresh:true});
        changedStudents.forEach(id => { savedStudents.add(id); originalAttendanceData[id] = attendanceData[id] || 'pending'; });
        changedStudents.clear();
        localStorage.removeItem(`changedStudents_${currentClass}_${date}`);
        saveAttendanceDataForClass(currentClass, date);
        updateAbsentData(); renderAttendanceList(currentClass); updateSaveBtns();
        _sendSyncCompletePush(records.length, 'attendance');
        setTimeout(loadData, 1200);
    }, e => { showToast('فشل الحفظ: ' + e, 'error'); btn.disabled = false; updateSaveBtns(); });
}
function saveAllData() {
    const hA = changedStudents.size > 0, hC = changedCouponStudents.size > 0;
    if (!hA && !hC) { showToast('لا توجد تغييرات', 'info'); return; }
    if (hA) submitAttendance();
    if (hC) setTimeout(submitCoupons, hA ? 1500 : 0);
}

// ── ABSENT DATA ───────────────────────────────────────────────
let absentDataStore = {};
function getAttendanceStatusStudents(status) {
    return sortStudentsForCurrentView(getActiveViewStudents().filter(s => attendanceData[getStudentId(s)] === status));
}
function buildAttendanceShareText(status, label, icon) {
    if (!currentClass) return '';
    const rows = getAttendanceStatusStudents(status);
    let txt = `\u202B━━━━━━━━━━━━━━━━━━\n${icon} *قائمة ${label}*\n🏫 ${getActiveViewLabel()}\n👤 ${rows.length} ${label}\n📅 ${currentFriday}\n━━━━━━━━━━━━━━━━━━\n\n`;
    rows.forEach((s,i) => {
        txt += `*${i+1}.* ${s['الاسم']}\n`;
        if (isCombinedView || currentClass === '__ALL__') txt += `   🏫 ${s['الفصل'] || ''}\n`;
        if (s['رقم التليفون']) txt += `   📱 ${s['رقم التليفون'].replace(/\D/g,'')}\n`;
        if (i < rows.length-1) txt += '   ─────────────\n';
    });
    return txt + '\n━━━━━━━━━━━━━━━━━━\n\u200F';
}
function updateAbsentData() {
    if (!currentClass) return;
    absentDataStore[currentClass] = getAttendanceStatusStudents('absent')
        .map(s => ({ id: getStudentId(s), name: s['الاسم'], phone: s['رقم التليفون']||'', address: s['العنوان']||'' }));
}
function copyAbsentData() {
    if (!currentClass) { showToast('اختر فصلاً أولاً','info'); return; }
    const absent = getAttendanceStatusStudents('absent');
    if (!absent.length) { showToast('لا يوجد غائبون','info'); return; }
    const txt = buildAttendanceShareText('absent', 'الغائبين', '📋');
    copyToClipboard(txt); showToast(`تم نسخ ${absent.length} غائب`, 'success');
}
function copyAttendedData() {
    if (!currentClass) { showToast('اختر فصلاً أولاً','info'); return; }
    const attended = getAttendanceStatusStudents('present');
    if (!attended.length) { showToast('لا يوجد حاضرون','info'); return; }
    copyToClipboard(buildAttendanceShareText('present', 'الحاضرين', '✅'));
    showToast(`تم نسخ ${attended.length} حاضر`, 'success');
}

// ── STUDENT DETAILS ───────────────────────────────────────────
function showStudentDetails(name) {
    const s = isCombinedView
        ? (combinedStudents.find(s => s['الاسم'] === name) || students.find(s => s['الاسم'] === name))
        : students.find(s => s['الاسم'] === name);
    if (!s) { showToast('لم يتم العثور على الطفل','error'); return; }
    currentStudentForEdit = s;
    document.getElementById('studentModalTitle').textContent = 'معلومات: ' + name;
    const img = s['صورة']
        ? `<div class="detail-avatar-wrap"><img src="${s['صورة']}" class="detail-avatar" onclick="showImageModal('${s['صورة']}')" onerror="this.style.display='none';var el=document.querySelector('.detail-avatar-fallback');if(el)el.style.display='flex'"><div class="detail-student-name">${s['الاسم']||''}</div><div class="detail-student-class">${s['الفصل']||''}</div></div>`
        : `<div class="detail-avatar-wrap"><div class="detail-avatar-fallback"><i class="fas fa-user"></i></div><div class="detail-student-name">${s['الاسم']||''}</div><div class="detail-student-class">${s['الفصل']||''}</div></div>`;
    const rows = [
        ['الاسم الكامل', s['الاسم']||'---', 'blue', 'fa-id-card'],
        ['الفصل', s['الفصل']||'---', 'purple', 'fa-chalkboard-teacher'],
        ['العنوان', s['العنوان']||'---', 'orange', 'fa-map-marker-alt'],
        ['رقم التليفون', s['رقم التليفون']||'---', 'green', 'fa-phone'],
        ['تاريخ الميلاد', s['عيد الميلاد']||'---', 'pink', 'fa-birthday-cake'],
        ['الكوبونات', (s['كوبونات']||'0') + ' <i class="fas fa-star" style="color:var(--coupon);font-size:.8rem"></i>', 'purple', 'fa-star'],
    ].map(([l,v,color,icon]) => `
        <div class="detail-row">
            <div class="detail-icon ${color}"><i class="fas ${icon}"></i></div>
            <div class="detail-label">${l}</div>
            <div class="detail-val">${v}</div>
        </div>`).join('');

    // Custom fields rows (supports multiple)
    let customRows = '';
    if (churchCustomFields && churchCustomFields.length) {
        const info = s._customInfo || {};
        customRows = churchCustomFields.map((cf, idx) => {
            const key = 'field_' + idx; // always stable sequential
            const val = info[key] || info['field_'+idx] || (idx===0 ? (info.value||'') : '') || '---';
            const icon = cf.icon || 'fa-tag';
            return `<div class="detail-row">
                <div class="detail-icon teal"><i class="fas ${icon}"></i></div>
                <div class="detail-label">${cf.name}</div>
                <div class="detail-val">${val}</div>
            </div>`;
        }).join('');
    }

    document.getElementById('studentDetails').innerHTML = img + rows + customRows;
    document.getElementById('studentModal').classList.add('active');
    stopAutoRefresh();
}
function hideStudentModal() { document.getElementById('studentModal').classList.remove('active'); currentStudentForEdit = null; startAutoRefresh(); }
function showEditForm() {
    if (!currentStudentForEdit) return;
    const s = currentStudentForEdit;
    const cs = document.getElementById('editStudentClass');
    cs.innerHTML = '<option value="">اختر الفصل</option>';
    classes.forEach(c => { const o = document.createElement('option'); o.value = c.id||c.code; o.textContent = c.arabic_name||c.code; cs.appendChild(o); });
    document.getElementById('editStudentName').value = s['الاسم']||'';
    const cid = s._classId; if (cid) cs.value = cid;
    document.getElementById('editStudentAddress').value = s['العنوان']||'';
    document.getElementById('editStudentPhone').value = s['رقم التليفون']||'';
    const bd = s['عيد الميلاد']||'';
    document.getElementById('editStudentBirthday').value = bd.match(/^\d{4}-\d{2}-\d{2}$/) ? bd.split('-').reverse().join('/') : bd;
    document.getElementById('editStudentCommitmentCoupons').value = s['كوبونات الالتزام']||'0';
    // Populate circle photo preview
    const prev = document.getElementById('uploadPreview');
    const ph   = document.getElementById('photoPlaceholder');
    if (s['صورة'] && prev) {
        prev.src = window.photoUrl(s['صورة']);
        prev.style.display = 'block';
        if (ph) ph.style.display = 'none';
    } else {
        if (prev) prev.style.display = 'none';
        if (ph)   ph.style.display  = 'flex';
    }
    // Custom fields (multiple)
    const cfContainer = document.getElementById('editCustomFieldsContainer');
    if (cfContainer) {
        if (churchCustomFields && churchCustomFields.length) {
            const info = s._customInfo || {};
            cfContainer.innerHTML = churchCustomFields.map((cf, idx) => {
                const key = 'field_' + idx;
                const val = info[key] || info['field_'+idx] || (idx===0 ? (info.value||'') : '');
                return `<div class="form-group">
                    <label class="form-label"><i class="fas ${cf.icon||'fa-tag'}"></i> ${cf.name}</label>
                    <div class="input-icon-wrap"><i class="fas ${cf.icon||'fa-tag'} input-icon"></i><input type="text" class="form-input" data-cf-key="${key}" value="${escHtml(val)}" placeholder="${cf.name}..."></div>
                </div>`;
            }).join('');
            cfContainer.style.display = '';
        } else {
            cfContainer.innerHTML = '';
            cfContainer.style.display = 'none';
        }
    }
    document.getElementById('editStudentForm').classList.add('active');
}
function hideEditForm() { document.getElementById('editStudentForm').classList.remove('active'); }
function updateStudentInfo(e) {
    e.preventDefault(); if (!currentStudentForEdit) return;
    const id = currentStudentForEdit._studentId; if (!id) { showToast('خطأ في بيانات الطفل','error'); return; }
    const name = document.getElementById('editStudentName').value.trim(), cls = document.getElementById('editStudentClass').value;
    if (!name || !cls) { showToast('الاسم والفصل مطلوبان','error'); return; }
    // Collect multiple custom fields
    const cfContainer = document.getElementById('editCustomFieldsContainer');
    let customInfoPayload = '';
    if (cfContainer && churchCustomFields.length) {
        const infoObj = {};
        cfContainer.querySelectorAll('[data-cf-key]').forEach((inp, idx) => {
            const key = 'field_' + idx; // stable sequential key
            infoObj[key] = inp.value.trim();
        });
        customInfoPayload = JSON.stringify(infoObj);
    }
    showLoading('جاري التحديث...');
    makeApiCall({
        action:'updateStudent', studentId:id, name, classId:cls,
        address:document.getElementById('editStudentAddress').value.trim(),
        phone:document.getElementById('editStudentPhone').value.trim(),
        birthday:document.getElementById('editStudentBirthday').value.trim(),
        coupons:parseInt(document.getElementById('editStudentCommitmentCoupons').value)||0,
        custom_info: customInfoPayload
    }, r => {
        showToast('تم التحديث بنجاح','success'); hideEditForm(); hideStudentModal(); setTimeout(loadData,1000);
    }, e => showToast('فشل: '+e,'error'));
}
function addNewPerson(e) {
    e.preventDefault();
    const name = document.getElementById('studentName').value.trim(), cls = document.getElementById('studentClass').value;
    if (!name || !cls) { showToast('الاسم والفصل مطلوبان','error'); return; }
    showLoading('جاري الإضافة...');
    const fd = new FormData();
    fd.append('action','addStudent'); fd.append('name',name); fd.append('classId',cls);
    fd.append('address',document.getElementById('studentAddress').value.trim()||'');
    fd.append('phone',document.getElementById('studentPhone').value.trim()||'');
    fd.append('birthday',document.getElementById('studentBirthday').value.trim()||'');
    fd.append('coupons',parseInt(document.getElementById('studentCoupons').value)||0);
    // Multiple custom fields
    const cfContainer = document.getElementById('addCustomFieldsContainer');
    if (cfContainer && churchCustomFields.length) {
        const infoObj = {};
        cfContainer.querySelectorAll('[data-cf-key]').forEach((inp, idx) => {
            const key = 'field_' + idx; // stable sequential key
            const v = inp.value.trim();
            if (v) infoObj[key] = v;
        });
        if (Object.keys(infoObj).length) fd.append('custom_info', JSON.stringify(infoObj));
    }
    if (currentCroppedBlob && currentPhotoEditorType==='new') fd.append('photo', new File([currentCroppedBlob], `profile_${Date.now()}.jpg`,{type:'image/jpeg'}));
    fetch(API_URL,{method:'POST',body:fd}).then(r=>r.json()).then(d => {
        if (d.success) { showToast('تم الإضافة بنجاح','success'); hideAddPersonModal(); document.getElementById('addPersonForm').reset(); cancelNewStudentPhotoUpload(); currentCroppedBlob=null; setTimeout(loadData,1000); }
        else showToast('فشل: '+(d.message||'خطأ'),'error');
    }).catch(() => showToast('خطأ في الاتصال','error'));
}
function showDeleteStudentModal(s) { studentToDelete=s; document.getElementById('deleteStudentName').textContent=`هل تريد حذف: ${s['الاسم']}؟`; document.getElementById('deleteStudentModal').classList.add('active'); }
function hideDeleteStudentModal() { studentToDelete=null; document.getElementById('deleteStudentModal').classList.remove('active'); }
function deleteStudent() {
    if (!studentToDelete) return;
    const id = studentToDelete._studentId||studentToDelete.studentId; if (!id) { showToast('معرف الطفل غير موجود','error'); return; }
    showLoading('جاري الحذف...');
    makeApiCall({ action:'deleteStudent', studentId:id }, r => { showToast('تم الحذف بنجاح','success'); hideDeleteStudentModal(); hideStudentModal(); setTimeout(loadData,1000); }, e => { showToast('فشل: '+e,'error'); hideDeleteStudentModal(); });
}

// ── MODALS ────────────────────────────────────────────────────
function showAddPersonModal() {
    document.getElementById('addPersonForm').reset(); cancelNewStudentPhotoUpload(); currentCroppedBlob=null;
    // Reset circle photo to placeholder
    const prev2 = document.getElementById('newStudentUploadPreview');
    const ph2   = document.getElementById('newStudentPhotoPlaceholder');
    if (prev2) prev2.style.display = 'none';
    if (ph2)   ph2.style.display   = 'flex';
    const cs = document.getElementById('studentClass');
    cs.innerHTML = '<option value="">اختر الفصل</option>';
    (classes.length ? classes : [...new Set(students.map(s=>s['الفصل']))].map((n,i)=>({id:i+1,code:n,arabic_name:n}))).forEach(c => { const o=document.createElement('option'); o.value=c.id||c.code; o.textContent=c.arabic_name||c.code; cs.appendChild(o); });
    if (currentClass) { const f=classes.find(c=>c.arabic_name===currentClass||c.code===currentClass); if(f) cs.value=f.id||f.code; }
    // Build multiple custom fields dynamically
    const cfCont = document.getElementById('addCustomFieldsContainer');
    if (cfCont) {
        if (churchCustomFields && churchCustomFields.length) {
            cfCont.innerHTML = churchCustomFields.map((cf, idx) => {
                const key = 'field_' + idx;
                return `<div class="form-group">
                    <label class="form-label"><i class="fas ${cf.icon||'fa-tag'}"></i> ${cf.name}</label>
                    <div class="input-icon-wrap"><i class="fas ${cf.icon||'fa-tag'} input-icon"></i><input type="text" class="form-input" data-cf-key="${key}" placeholder="${cf.name}..."></div>
                </div>`;
            }).join('');
            cfCont.style.display = '';
        } else {
            cfCont.innerHTML = '';
            cfCont.style.display = 'none';
        }
    }
    document.getElementById('addPersonModal').classList.add('active'); stopAutoRefresh();
}
function hideAddPersonModal() { document.getElementById('addPersonModal').classList.remove('active'); startAutoRefresh(); }
function showBirthdayModal() { document.getElementById('birthdayModal').classList.add('active'); renderBirthdayMonths(); showBirthdaysByMonth(new Date().getMonth()); stopAutoRefresh(); }
function hideBirthdayModal() { document.getElementById('birthdayModal').classList.remove('active'); startAutoRefresh(); }
function showAllStudentsModal() { document.getElementById('allStudentsModal').classList.add('active'); clearAllStudentsSearch(); renderAllStudentsTable(); setupAllStudentsSearch(); stopAutoRefresh(); }
function hideAllStudentsModal() { document.getElementById('allStudentsModal').classList.remove('active'); startAutoRefresh(); }
function showSheetModal() { _sheetZoom=1.0; document.getElementById('sheetModal').classList.add('active'); renderSheetTable(); _applySheetZoom(); stopAutoRefresh(); }
function hideSheetModal() { document.getElementById('sheetModal').classList.remove('active'); startAutoRefresh(); }
function showAttendedModal() { document.getElementById('attendedModal').classList.add('active'); renderAttendedTable(); stopAutoRefresh(); }
function hideAttendedModal() { document.getElementById('attendedModal').classList.remove('active'); startAutoRefresh(); }
function showAbsentModal() { document.getElementById('absentModal').classList.add('active'); updateAbsentData(); renderAbsentTable(); stopAutoRefresh(); }
function hideAbsentModal() { document.getElementById('absentModal').classList.remove('active'); startAutoRefresh(); }
function showPastFridaysModal() { document.getElementById('pastFridaysModal').classList.add('active'); renderPastFridays(); stopAutoRefresh(); }
function hidePastFridaysModal() { document.getElementById('pastFridaysModal').classList.remove('active'); startAutoRefresh(); }
function showAnnouncementsModal() { document.getElementById('announcementsModal').classList.add('active'); loadAnnouncements(); stopAutoRefresh(); }
function hideAnnouncementsModal() { document.getElementById('announcementsModal').classList.remove('active'); startAutoRefresh(); }
function showResetModal() { document.getElementById('resetModal').classList.add('active'); stopAutoRefresh(); }
function hideResetModal() { document.getElementById('resetModal').classList.remove('active'); startAutoRefresh(); }
function showImageModal(src, e) {
    if(!src) return;
    if(e) { e.stopPropagation(); e.preventDefault(); }
    const modal = document.getElementById('imageModal');
    const img = document.getElementById('imageModalImg');
    img.src = src;
    modal.classList.add('active');
    // Reset zoom/pan state
    _imgZoom = 1; _imgX = 0; _imgY = 0;
    img.style.transform = 'scale(1) translate(0,0)';
    stopAutoRefresh();
}
function hideImageModal() {
    document.getElementById('imageModal').classList.remove('active');
    startAutoRefresh();
}
// Image zoom/pan state
let _imgZoom = 1, _imgX = 0, _imgY = 0;
function hideRegistrationDetails() { document.getElementById('registrationDetailsModal').classList.remove('active'); currentRegistrationDetails=null; startAutoRefresh(); }

// ── BIRTHDAY ──────────────────────────────────────────────────
function renderBirthdayMonths() {
    const months = ['يناير','فبراير','مارس','أبريل','مايو','يونيو','يوليو','أغسطس','سبتمبر','أكتوبر','نوفمبر','ديسمبر'], cm = new Date().getMonth();
    document.getElementById('monthSelector').innerHTML = months.map((m,i)=>`<button class="month-btn ${i===cm?'active':''}" onclick="showBirthdaysByMonth(${i})">${m}</button>`).join('');
}
function showBirthdaysByMonth(idx) {
    document.querySelectorAll('.month-btn').forEach((b,i)=>b.classList.toggle('active',i===idx));
    const ms = allStudentsData.filter(s => {
        if(!s['عيد الميلاد']) return false;
        const p = s['عيد الميلاد'].split('/');
        return p.length >= 2 && parseInt(p[1])-1 === idx;
    }).sort((a,b) => {
        const pa = a['عيد الميلاد'].split('/'), pb = b['عيد الميلاد'].split('/');
        return parseInt(pa[0]) - parseInt(pb[0]);
    });

    const countEl = document.getElementById('birthdayMonthCount');
    if (countEl) countEl.textContent = ms.length ? ms.length + ' طفل' : '';

    const today = new Date();
    const todayDay = today.getDate(), todayMonth = today.getMonth();

    const zodiacSigns = [
        {name:'الجدي',emoji:'♑',start:[12,22],end:[1,19]},
        {name:'الدلو',emoji:'♒',start:[1,20],end:[2,18]},
        {name:'الحوت',emoji:'♓',start:[2,19],end:[3,20]},
        {name:'الحمل',emoji:'♈',start:[3,21],end:[4,19]},
        {name:'الثور',emoji:'♉',start:[4,20],end:[5,20]},
        {name:'الجوزاء',emoji:'♊',start:[5,21],end:[6,20]},
        {name:'السرطان',emoji:'♋',start:[6,21],end:[7,22]},
        {name:'الأسد',emoji:'♌',start:[7,23],end:[8,22]},
        {name:'العذراء',emoji:'♍',start:[8,23],end:[9,22]},
        {name:'الميزان',emoji:'♎',start:[9,23],end:[10,22]},
        {name:'العقرب',emoji:'♏',start:[10,23],end:[11,21]},
        {name:'القوس',emoji:'♐',start:[11,22],end:[12,21]},
    ];
    function getZodiac(day, month) {
        for (const z of zodiacSigns) {
            const [sm, sd] = z.start, [em, ed] = z.end;
            if ((month === sm && day >= sd) || (month === em && day <= ed)) return z;
        }
        return null;
    }

    document.getElementById('birthdayGrid').innerHTML = ms.length ? ms.map(s => {
        const p = s['عيد الميلاد'].split('/');
        const bDay = parseInt(p[0]), bMonth = parseInt(p[1]), bYear = p.length >= 3 ? parseInt(p[2]) : 0;
        const isToday = (bDay === todayDay && bMonth-1 === todayMonth);
        const age = bYear > 0 ? today.getFullYear() - bYear - (today < new Date(today.getFullYear(), bMonth-1, bDay) ? 1 : 0) : 0;
        const zodiac = getZodiac(bDay, bMonth);
        const photo = s['صورة']
            ? `<img src="${s['صورة']}" alt="" style="width:54px;height:54px;border-radius:50%;object-fit:cover;border:3px solid ${isToday?'#db2777':'var(--border-solid)'};box-shadow:var(--shadow-sm);cursor:pointer;flex-shrink:0" onclick="showImageModal('${(s['صورة']||'').replace(/'/g,"\\'")}',event)">`
            : `<div style="width:54px;height:54px;border-radius:50%;background:linear-gradient(135deg,var(--brand-bg),var(--coupon-bg));display:flex;align-items:center;justify-content:center;color:var(--brand);font-size:1.3rem;flex-shrink:0"><i class="fas fa-user"></i></div>`;
        const phone = (s['رقم التليفون']||'').replace(/\D/g,'');
        const waMsgRaw = `🎂 كل سنة وأنت طيب يا ${s['الاسم']}!\n\nنتمنى لك عيد ميلاد سعيد ومليان فرحة 🎉\nمع حبنا وصلواتنا 🙏`;
        const waLink = phone ? `https://api.whatsapp.com/send?phone=${phone.startsWith('0')?'2'+phone:phone}&text=${encodeURIComponent(waMsgRaw)}` : '';
        return `<div class="birthday-card-new ${isToday?'bday-today':''}" onclick="showStudentDetails('${(s['الاسم']||'').replace(/'/g,"\\'")}')">
            ${isToday?'<div class="bday-today-badge">🎂 اليوم!</div>':''}
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px">
                ${photo}
                <div style="flex:1;min-width:0">
                    <div style="font-weight:800;color:var(--text);font-size:.92rem;margin-bottom:2px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${s['الاسم']||'---'}</div>
                    <div style="font-size:.74rem;color:var(--text-3)">${s['الفصل']||''}</div>
                    ${zodiac?`<div style="font-size:.72rem;color:var(--text-3);margin-top:1px">${zodiac.emoji} ${zodiac.name}</div>`:''}
                </div>
            </div>
            <div style="display:flex;align-items:center;justify-content:space-between;gap:6px">
                <div style="display:flex;align-items:center;gap:6px;flex-wrap:wrap">
                    <span style="background:var(--brand-bg);color:var(--brand);padding:2px 8px;border-radius:var(--r-full);font-size:.72rem;font-weight:700"><i class="fas fa-calendar-day" style="font-size:.6rem"></i> ${s['عيد الميلاد']}</span>
                    ${age>0?`<span style="background:var(--success-bg);color:var(--success-dark);padding:2px 8px;border-radius:var(--r-full);font-size:.72rem;font-weight:700">${age} سنة</span>`:''}
                </div>
                ${waLink?`<a href="${waLink}" target="_blank" onclick="event.stopPropagation()" style="background:#25d366;color:#fff;padding:4px 10px;border-radius:var(--r-full);font-size:.72rem;font-weight:700;text-decoration:none;display:flex;align-items:center;gap:4px;flex-shrink:0"><i class="fab fa-whatsapp"></i> تهنئة</a>`:''}
            </div>
        </div>`;
    }).join('') : '<div style="text-align:center;padding:3rem;color:var(--text-3);grid-column:1/-1"><i class="fas fa-birthday-cake" style="font-size:2.5rem;opacity:.3;display:block;margin-bottom:10px"></i>لا أعياد ميلاد هذا الشهر</div>';

    // Confetti if any birthday today
    if (ms.some(s => { const p=s['عيد الميلاد'].split('/'); return parseInt(p[0])===todayDay && parseInt(p[1])-1===todayMonth; })) {
        _launchConfetti();
    }
}

// ── PAST FRIDAYS ──────────────────────────────────────────────
function updateCurrentDateDisplay() {
    const today = new Date();
    const jsDay = dbDayToJsDay(churchAttendanceDay); // e.g. 5 for Friday
    const d = new Date(today);
    // Find the most recent occurrence of the configured day (today or before)
    let diff = (today.getDay() - jsDay + 7) % 7;
    d.setDate(today.getDate() - diff);
    currentFriday = formatDateDDMMYYYY(d);
    const el = document.getElementById('currentDateText');
    if (el) el.textContent = currentFriday;
}

// ── Custom dates storage ──────────────────────────────────────
// Stored in localStorage as array of {date:'DD/MM/YYYY', label:'...'}
function _getCustomDates() {
    try { return JSON.parse(localStorage.getItem('customAttendanceDates') || '[]'); } catch(e) { return []; }
}
function _saveCustomDates(arr) {
    try { localStorage.setItem('customAttendanceDates', JSON.stringify(arr)); } catch(e) {}
}

// ── Attendance stats cache ─────────────────────────────────────
// Key: 'DD/MM/YYYY', value: {present:N, absent:N} — populated lazily from student data
function _getAttendanceStatsForDate(dateStr) {
    const srcList = isCombinedView ? combinedStudents : students.filter(s => s['الفصل'] === currentClass);
    let present = 0, absent = 0;
    srcList.forEach(s => {
        const id = getStudentId(s);
        // Use in-memory attendanceData if this is the current date
        if (dateStr === currentFriday) {
            const st = attendanceData[id] || 'pending';
            if (st === 'present') present++;
            else if (st === 'absent') absent++;
        } else {
            // Check server-side data stored in student object
            const srv = getServerAttendanceStatus(s, dateStr);
            if (srv === 'present') present++;
            else if (srv === 'absent') absent++;
        }
    });
    return { present, absent };
}

function _buildFridayItemHtml(fr, isCur, isSel, isCustom = false, customLabel = '') {
    const stats = _getAttendanceStatsForDate(fr.date);
    const hasStats = stats.present > 0 || stats.absent > 0;
    const statsHtml = hasStats
        ? `<div class="friday-stats-row">
              <span class="fs-p"><i class="fas fa-check" style="font-size:.5rem"></i>${stats.present}</span>
              <span class="fs-div">|</span>
              <span class="fs-a"><i class="fas fa-times" style="font-size:.5rem"></i>${stats.absent}</span>
           </div>`
        : '';
    const customBadge = isCustom ? '<div class="custom-badge"><i class="fas fa-star"></i></div>' : '';
    const currentBadge = isCur ? '<div class="current-badge"><i class="fas fa-star"></i></div>' : '';
    const deleteBtn = isCustom
        ? `<button class="delete-custom-btn" onclick="event.stopPropagation();removeCustomDate('${fr.date}')" title="حذف هذا التاريخ"><i class="fas fa-times"></i></button>`
        : '';
    const dayLabel = fr.obj ? fr.obj.toLocaleDateString('ar-EG', { weekday: 'short' }) : '';
    const displayNum = fr.obj ? fr.obj.getDate() : fr.date.split('/')[0];
    const labelLine = customLabel ? `<div style="font-size:.58rem;color:var(--warning-dark);margin-top:1px;font-weight:700;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:90px">${customLabel}</div>` : '';
    return `<div class="friday-item ${isSel ? 'selected' : ''} ${isCur ? 'current-week' : ''} ${isCustom ? 'custom-date' : ''}" onclick="loadFridayAttendance('${fr.date}')">
        ${currentBadge}${customBadge}${deleteBtn}
        <div class="friday-num">${displayNum}</div>
        <div class="friday-day">${dayLabel}</div>
        <div class="friday-date">${fr.date}</div>
        ${labelLine}
        ${statsHtml}
    </div>`;
}

function renderPastFridays() {
    const jsDay = dbDayToJsDay(churchAttendanceDay);
    const today = new Date();
    const start = new Date(today.getFullYear() - 1, 8, 1);

    // Find first occurrence of the configured day from start
    let f = new Date(start);
    while (f.getDay() !== jsDay) f.setDate(f.getDate() + 1);

    const days = [];
    while (f <= today) {
        days.push({ date: formatDateDDMMYYYY(f), obj: new Date(f) });
        f.setDate(f.getDate() + 7);
    }
    days.reverse();

    const grouped = {};
    days.forEach(fr => {
        const k = fr.obj.toLocaleDateString('ar-EG', { year: 'numeric', month: 'long' });
        if (!grouped[k]) grouped[k] = [];
        grouped[k].push(fr);
    });

    const todayTarget = getCurrentAttendanceDay();
    let html = '';

    // ── Custom dates section (shown at top if any exist) ─────────
    const customDates = _getCustomDates();
    if (customDates.length) {
        html += `<div class="month-row" style="border-color:rgba(245,158,11,.4)">
            <h4 style="color:var(--warning-dark)"><i class="fas fa-star"></i> تواريخ مخصصة
              <span style="font-size:.72rem;background:rgba(245,158,11,.15);color:var(--warning-dark);padding:1px 7px;border-radius:var(--r-full);margin-right:auto">${customDates.length}</span>
            </h4>
            <div class="fridays-grid">`;
        customDates.forEach(cd => {
            const parts = cd.date.split('/');
            const dateObj = parts.length === 3 ? new Date(parseInt(parts[2]), parseInt(parts[1])-1, parseInt(parts[0])) : null;
            const isSel = cd.date === currentFriday;
            html += _buildFridayItemHtml({ date: cd.date, obj: dateObj }, false, isSel, true, cd.label || '');
        });
        html += '</div></div>';
    }

    // ── Regular attendance days ───────────────────────────────────
    for (const [month, mf] of Object.entries(grouped)) {
        html += `<div class="month-row"><h4><i class="fas fa-calendar"></i> ${month} <span style="font-size:.72rem;background:var(--brand-bg);color:var(--brand);padding:1px 7px;border-radius:var(--r-full);margin-right:auto">${mf.length}</span></h4><div class="fridays-grid">`;
        mf.forEach(fr => {
            const isCur = todayTarget.toDateString() === fr.obj.toDateString();
            const isSel = fr.date === currentFriday;
            html += _buildFridayItemHtml(fr, isCur, isSel, false, '');
        });
        html += '</div></div>';
    }
    document.getElementById('fridaysList').innerHTML = html;

    // Also refresh the custom dates strip inside the add section
    _refreshCustomDatesStrip();
}

// ── Custom date management ─────────────────────────────────────
function toggleCustomDateSection() {
    const sec = document.getElementById('customDateSection');
    const btn = document.getElementById('toggleCustomDateSectionBtn');
    if (!sec) return;
    const isOpen = sec.style.display !== 'none';
    sec.style.display = isOpen ? 'none' : 'block';
    if (btn) {
        btn.innerHTML = isOpen
            ? '<i class="fas fa-plus-circle"></i> إضافة تاريخ مخصص'
            : '<i class="fas fa-times-circle"></i> إغلاق';
    }
    if (!isOpen) {
        _refreshCustomDatesStrip();
        const inp = document.getElementById('customDateInput');
        if (inp) inp.focus();
    }
}

function autoFormatCustomDate(input) {
    let v = input.value.replace(/\D/g, '').substring(0, 8), out = '';
    if (v.length > 0) out = v.substring(0, 2);
    if (v.length > 2) out += '/' + v.substring(2, 4);
    if (v.length > 4) out += '/' + v.substring(4, 8);
    input.value = out;
    input.style.borderColor = out.length === 10 ? 'var(--success)' : '';
}

function addCustomDate() {
    const dateStr = (document.getElementById('customDateInput')?.value || '').trim();
    const label   = (document.getElementById('customDateLabel')?.value || '').trim();

    if (!dateStr.match(/^\d{2}\/\d{2}\/\d{4}$/)) {
        showToast('أدخل تاريخاً صحيحاً بصيغة DD/MM/YYYY', 'error'); return;
    }
    const [d, m, y] = dateStr.split('/').map(Number);
    if (!isValidDate(d, m, y)) { showToast('التاريخ غير صحيح', 'error'); return; }

    const existing = _getCustomDates();
    if (existing.some(c => c.date === dateStr)) {
        showToast('هذا التاريخ موجود بالفعل', 'warning'); return;
    }

    existing.unshift({ date: dateStr, label }); // newest first
    _saveCustomDates(existing);

    // Clear inputs
    const inp = document.getElementById('customDateInput');
    const lbl = document.getElementById('customDateLabel');
    if (inp) { inp.value = ''; inp.style.borderColor = ''; }
    if (lbl) lbl.value = '';

    renderPastFridays(); // re-render the full list
    showToast('تم إضافة التاريخ المخصص ' + dateStr, 'success');
}

function removeCustomDate(dateStr) {
    if (!confirm('حذف هذا التاريخ المخصص؟')) return;
    const arr = _getCustomDates().filter(c => c.date !== dateStr);
    _saveCustomDates(arr);
    renderPastFridays();
    showToast('تم حذف التاريخ', 'info');
}

function _refreshCustomDatesStrip() {
    const strip = document.getElementById('customDatesStrip');
    if (!strip) return;
    const arr = _getCustomDates();
    if (!arr.length) { strip.classList.remove('has-items'); strip.innerHTML = ''; return; }
    strip.classList.add('has-items');
    strip.innerHTML = arr.map(cd =>
        `<span class="custom-date-chip" onclick="loadFridayAttendance('${cd.date}');toggleCustomDateSection()">
            <i class="fas fa-calendar-alt" style="font-size:.65rem"></i>
            ${cd.date}${cd.label ? ' — ' + cd.label : ''}
            <button class="cdel" onclick="event.stopPropagation();removeCustomDate('${cd.date}')" title="حذف"><i class="fas fa-times"></i></button>
        </span>`
    ).join('');
}

function getCurrentAttendanceDay() {
    const jsDay = dbDayToJsDay(churchAttendanceDay);
    const today = new Date();
    const d = new Date(today);
    const diff = (today.getDay() - jsDay + 7) % 7;
    d.setDate(today.getDate() - diff);
    return d;
}

function getCurrentFriday() { return getCurrentAttendanceDay(); }
function loadFridayAttendance(date) {
    localStorage.setItem('selectedFriday', date);
    currentFriday = date;
    document.getElementById('currentDateText').textContent = date;

    if (isCombinedView) {
        if (currentClass === '__ALL__') {
            combinedStudents = [...students];
            loadAttendanceDataForCombinedAll();
        } else {
            const grp = combinedClassGroups.find(g => g.label === currentClass);
            if (grp && grp.classes) {
                combinedStudents = students.filter(s => grp.classes.includes(s['الفصل']));
            }
            loadAttendanceDataForCombined(currentClass);
        }
        if (currentClass === '__ALL__') {
            renderAttendanceList('__ALL__');
        } else {
            renderCombinedAttendanceList();
        }
    } else {
        loadAttendanceDataForClass(currentClass, date);
        renderAttendanceList(currentClass);
    }

    hidePastFridaysModal();
    showToast('تم تحميل بيانات ' + date, 'success');
}
function resetToCurrentFriday() {
    localStorage.removeItem('selectedFriday');
    updateCurrentDateDisplay();

    if (isCombinedView) {
        if (currentClass === '__ALL__') {
            combinedStudents = [...students];
            loadAttendanceDataForCombinedAll();
            renderAttendanceList('__ALL__');
        } else {
            const grp = combinedClassGroups.find(g => g.label === currentClass);
            if (grp && grp.classes) {
                combinedStudents = students.filter(s => grp.classes.includes(s['الفصل']));
            }
            loadAttendanceDataForCombined(currentClass);
            renderCombinedAttendanceList();
        }
    } else {
        loadAttendanceDataForClass(currentClass);
        renderAttendanceList(currentClass);
    }

    hidePastFridaysModal();
    showToast('تم العودة لآخر ' + getAttendanceDayName(), 'success');
}
function getAttendanceDayName() {
    const names = {1:'اثنين',2:'ثلاثاء',3:'أربعاء',4:'خميس',5:'جمعة',6:'سبت',7:'أحد'};
    return names[churchAttendanceDay] || 'جمعة';
}

// ── SHEET TABLE ───────────────────────────────────────────────
// ── Sheet table zoom ──────────────────────────────────────────
let _sheetZoom = 1.0;
const ZOOM_STEP = 0.15, ZOOM_MIN = 0.4, ZOOM_MAX = 2.5;

function _applySheetZoom() {
    const inner = document.getElementById('sheetZoomInner');
    const label = document.getElementById('sheetZoomLevel');
    if (inner) inner.style.transform = `scale(${_sheetZoom})`;
    if (label) label.textContent = Math.round(_sheetZoom * 100) + '%';
}
function sheetZoom(dir) {
    _sheetZoom = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, _sheetZoom + dir * ZOOM_STEP));
    _applySheetZoom();
}
function sheetZoomReset() { _sheetZoom = 1.0; _applySheetZoom(); }

function _dateInSheetRange(dateStr) {
    const d = parseDate(dateStr);
    if (sheetDateFrom && d < parseDate(sheetDateFrom)) return false;
    if (sheetDateTo && d > parseDate(sheetDateTo)) return false;
    return true;
}
function getSheetDateColumns(fs) {
    const dates = new Set();
    fs.forEach(s => {
        Object.keys(s).forEach(k => {
            if (k.includes('/') && k.length === 10) {
                const v = s[k];
                if (['ح','غ','حاضر','غائب','present','absent'].includes(v) && _dateInSheetRange(k)) dates.add(k);
            }
        });
        if (s._allAttendance) Object.keys(s._allAttendance).forEach(d => {
            if (d.includes('/') && _dateInSheetRange(d)) dates.add(d);
        });
    });
    return [...dates].sort((a,b) => parseDate(b) - parseDate(a));
}
function applySheetDateRange() {
    const from = (document.getElementById('sheetFromDate')?.value || '').trim();
    const to = (document.getElementById('sheetToDate')?.value || '').trim();
    const valid = v => !v || /^\d{2}\/\d{2}\/\d{4}$/.test(v);
    if (!valid(from) || !valid(to)) { showToast('استخدم صيغة DD/MM/YYYY للتاريخ', 'error'); return; }
    if (from && to && parseDate(from) > parseDate(to)) { showToast('تاريخ البداية بعد تاريخ النهاية', 'error'); return; }
    sheetDateFrom = from;
    sheetDateTo = to;
    renderSheetTable();
    showToast('تم تطبيق نطاق التاريخ', 'success');
}
function clearSheetDateRange() {
    sheetDateFrom = '';
    sheetDateTo = '';
    const f = document.getElementById('sheetFromDate'), t = document.getElementById('sheetToDate');
    if (f) f.value = '';
    if (t) t.value = '';
    renderSheetTable();
}

// Pinch-to-zoom on the sheet wrapper
(function initPinch() {
    document.addEventListener('DOMContentLoaded', () => {
        const wrap = document.getElementById('sheetZoomWrap');
        if (!wrap) return;
        let lastDist = 0;
        wrap.addEventListener('touchstart', e => {
            if (e.touches.length === 2) {
                const dx = e.touches[0].clientX - e.touches[1].clientX;
                const dy = e.touches[0].clientY - e.touches[1].clientY;
                lastDist = Math.hypot(dx, dy);
            }
        }, {passive:true});
        wrap.addEventListener('touchmove', e => {
            if (e.touches.length === 2) {
                const dx = e.touches[0].clientX - e.touches[1].clientX;
                const dy = e.touches[0].clientY - e.touches[1].clientY;
                const dist = Math.hypot(dx, dy);
                if (lastDist > 0) {
                    const ratio = dist / lastDist;
                    _sheetZoom = Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, _sheetZoom * ratio));
                    _applySheetZoom();
                }
                lastDist = dist;
                e.preventDefault();
            }
        }, {passive:false});
        wrap.addEventListener('touchend', () => { lastDist = 0; });
    });
})();

// ── Desktop mouse-drag to scroll (only on empty space, not text) ──
(function initMouseDragScroll() {
    document.addEventListener('DOMContentLoaded', () => {
        const wrap = document.getElementById('sheetZoomWrap');
        if (!wrap) return;

        let isDown = false, startMouseX = 0, startMouseY = 0,
            startScrollLeft = 0, startScrollTop = 0;

        wrap.addEventListener('mousedown', e => {
            // Only activate drag on the wrap itself or the inner div — not on table cells/text
            const tag = (e.target.tagName || '').toLowerCase();
            // If click is on actual table content (td/th/span/text), let browser handle normally
            if (tag === 'td' || tag === 'th' || tag === 'input' || tag === 'button' || tag === 'a') return;
            // If the user is trying to select text (double-click or shift-click), skip
            if (e.detail >= 2 || e.shiftKey) return;

            isDown = true;
            startMouseX    = e.clientX;
            startMouseY    = e.clientY;
            startScrollLeft = wrap.scrollLeft;
            startScrollTop  = wrap.scrollTop;
            wrap.classList.add('is-dragging');
            e.preventDefault();
        });

        document.addEventListener('mousemove', e => {
            if (!isDown) return;
            const dx = e.clientX - startMouseX;
            const dy = e.clientY - startMouseY;
            wrap.scrollLeft = startScrollLeft - dx;
            wrap.scrollTop  = startScrollTop  - dy;
        });

        document.addEventListener('mouseup', () => {
            if (!isDown) return;
            isDown = false;
            wrap.classList.remove('is-dragging');
        });

        // If mouse leaves window, cancel
        document.addEventListener('mouseleave', () => {
            isDown = false;
            wrap.classList.remove('is-dragging');
        });
    });
})();

function renderSheetTable() {
    const body=document.getElementById('sheetTableBody'), head=document.getElementById('sheetTableHead');
    document.getElementById('sheetModalTitle').textContent = currentClass ? `جدول: ${currentClass}` : 'جدول جميع الفصول';
    let fs = sortStudentsForCurrentView(getActiveViewStudents());
    if (!fs.length) { body.innerHTML='<tr><td colspan="12" style="text-align:center;padding:2rem">لا بيانات</td></tr>'; return; }
    const sorted = getSheetDateColumns(fs);
    head.querySelector('tr').innerHTML =
        '<th style="width:36px;padding:6px 4px"></th>' +
        '<th style="cursor:pointer" onclick="">الاسم</th>' +
        '<th>الفصل</th><th>العنوان</th><th>التليفون</th><th>الميلاد</th><th>كوبونات</th>' +
        sorted.map(d=>`<th style="min-width:56px;text-align:center">${d}</th>`).join('');
    body.innerHTML = fs.map(s => {
        const safeName = escJs(s['الاسم']||'');
        const photoCell = s['صورة']
            ? `<td style="padding:4px 4px;width:36px;text-align:center">
                <img src="${s['صورة']}" alt="" style="width:30px;height:30px;border-radius:50%;object-fit:cover;cursor:pointer;vertical-align:middle;border:1.5px solid var(--border-solid)"
                    onclick="showImageModal('${escJs(s['صورة']||'')}',event)"
                    onerror="this.style.display='none'">
               </td>`
            : `<td style="padding:4px 4px;width:36px;text-align:center">
                <div style="width:30px;height:30px;border-radius:50%;background:var(--brand-bg);display:inline-flex;align-items:center;justify-content:center;color:var(--brand);font-size:.75rem"><i class="fas fa-user"></i></div>
               </td>`;
        const nameCell = `<td style="font-weight:700;cursor:pointer;color:var(--brand);white-space:nowrap"
            onclick="showStudentDetails('${safeName}')">${s['الاسم']||'---'}</td>`;
        return `<tr>
            ${photoCell}
            ${nameCell}
            ${['الفصل','العنوان','رقم التليفون','عيد الميلاد','كوبونات'].map(k=>`<td style="white-space:nowrap">${s[k]||'---'}</td>`).join('')}
            ${sorted.map(d => {
                let v=s[d]||(s._allAttendance&&s._allAttendance[d])||'';
                if(v==='حاضر'||v==='present')v='ح';
                if(v==='غائب'||v==='absent')v='غ';
                return `<td class="${v==='ح'?'attendance-present':v==='غ'?'attendance-absent':''}" style="text-align:center;font-weight:700">${v||''}</td>`;
            }).join('')}
        </tr>`;
    }).join('');
}
function renderAttendanceStatusTable(status, bodyId, emptyHtml, searchInputId) {
    const q = (document.getElementById(searchInputId)?.value || '').trim().toLowerCase();
    const cs = getAttendanceStatusStudents(status).filter(s => !q || ['الاسم','الفصل','رقم التليفون','العنوان'].some(k => (s[k] || '').toLowerCase().includes(q)));
    document.getElementById(bodyId).innerHTML = cs.length
        ? cs.map((s, i) => {
            const photo = s['صورة']
                ? `<img src="${s['صورة']}" alt="" style="width:32px;height:32px;border-radius:50%;object-fit:cover;vertical-align:middle;border:1.5px solid var(--border-solid)" onerror="this.outerHTML='<div style=\\'width:32px;height:32px;border-radius:50%;background:var(--brand-bg);display:inline-flex;align-items:center;justify-content:center;color:var(--brand);font-size:.75rem\\'><i class=\\'fas fa-user\\'></i></div>'">`
                : `<div style="width:32px;height:32px;border-radius:50%;background:var(--brand-bg);display:inline-flex;align-items:center;justify-content:center;color:var(--brand);font-size:.75rem"><i class="fas fa-user"></i></div>`;
            const phone = s['رقم التليفون'] || '';
            const phoneLink = phone
                ? `<a href="tel:${phone.replace(/\D/g,'')}" style="color:var(--brand);text-decoration:none;font-size:.8rem">${phone}</a>`
                : '<span style="color:var(--text-3);font-size:.8rem">—</span>';
            return `<tr id="${bodyId}_row_${i}">
                <td style="padding:6px 8px;width:40px;text-align:center">${photo}</td>
                <td style="font-weight:700;font-size:.85rem;cursor:pointer;color:var(--text)" onclick="showStudentDetails('${escJs(s['الاسم']||'')}')">${s['الاسم']||'---'}</td>
                ${bodyId === 'attendedTableBody' ? `<td><span style="background:var(--brand-bg);color:var(--brand);padding:2px 8px;border-radius:var(--r-full);font-size:.74rem;font-weight:700">${s['الفصل']||'---'}</span></td>` : ''}
                <td style="font-size:.8rem">${phoneLink}</td>
                <td style="color:var(--text-2);font-size:.78rem">${s['العنوان']||'—'}</td>
                <td><input type="text" class="form-input" style="padding:4px 8px;font-size:.76rem;min-width:90px;height:30px" placeholder="ملاحظة..."></td>
                <td style="text-align:center;padding:4px">
                    <button class="btn btn-danger btn-xs" style="width:28px;height:28px;padding:0;border-radius:50%;min-width:0" onclick="document.getElementById('${bodyId}_row_${i}').remove()" title="إزالة">
                        <i class="fas fa-times" style="font-size:.65rem"></i>
                    </button>
                </td>
            </tr>`;
        }).join('')
        : emptyHtml;
}
function renderAbsentTable() {
    renderAttendanceStatusTable('absent', 'absentTableBody', `<tr><td colspan="6" style="text-align:center;padding:2.5rem;color:var(--text-3)">
            <i class="fas fa-check-circle" style="font-size:2rem;color:var(--success);display:block;margin-bottom:8px"></i>
            لا غائبين هذا الأسبوع 🎉
           </td></tr>`, 'absentSearchInput');
}
function renderAttendedTable() {
    renderAttendanceStatusTable('present', 'attendedTableBody', `<tr><td colspan="7" style="text-align:center;padding:2.5rem;color:var(--text-3)">
            <i class="fas fa-user-check" style="font-size:2rem;color:var(--success);display:block;margin-bottom:8px"></i>
            لا يوجد حاضرون لهذا التاريخ
           </td></tr>`, 'attendedSearchInput');
}
function clearAbsentData() { if(confirm('مسح جميع بيانات الغائبين؟')) { document.getElementById('absentTableBody').innerHTML=''; showToast('تم المسح','success'); } }
function renderAllStudentsTable() {
    const data = allStudentsSearchQuery ? filteredAllStudents : allStudentsData;
    document.getElementById('allStudentsTableBody').innerHTML = data.length ? data.map(s => {
        const photo = s['صورة']
            ? `<img src="${s['صورة']}" alt="" style="width:34px;height:34px;border-radius:50%;object-fit:cover;border:2px solid var(--border-solid);cursor:pointer;transition:transform .2s" onclick="showImageModal('${escJs(s['صورة'])}',event)" onerror="this.outerHTML='<div style=\'width:34px;height:34px;border-radius:50%;background:var(--brand-bg);display:flex;align-items:center;justify-content:center;color:var(--brand);font-size:.8rem\'><i class=\'fas fa-user\'></i></div>'">`
            : `<div style="width:34px;height:34px;border-radius:50%;background:var(--brand-bg);display:flex;align-items:center;justify-content:center;color:var(--brand);font-size:.8rem"><i class="fas fa-user"></i></div>`;
        const name = s['الاسم']||'---';
        const nameHl = allStudentsSearchQuery
            ? name.replace(new RegExp(`(${allStudentsSearchQuery})`,'gi'),'<mark style="background:#fde047;border-radius:3px;padding:0 2px;color:#000">$1</mark>')
            : name;
        const bd = s['عيد الميلاد']||'---';
        const age = _calcAge(bd);
        return `<tr data-student-name="${escHtml(s['الاسم']||'')}">
            <td style="padding:6px 8px;width:44px;text-align:center">${photo}</td>
            <td style="font-weight:700;cursor:pointer;color:var(--brand)" onclick="showStudentDetails('${escJs(s['الاسم']||'')}')">
                ${nameHl}
            </td>
            <td><span style="background:var(--brand-bg);color:var(--brand);padding:2px 8px;border-radius:var(--r-full);font-size:.75rem;font-weight:700">${s['الفصل']||'---'}</span></td>
            <td style="color:var(--text-2);font-size:.82rem">${s['العنوان']||'---'}</td>
            <td style="direction:ltr;text-align:right;color:var(--text-2)">${s['رقم التليفون']||'---'}</td>
            <td style="font-size:.8rem;color:var(--text-2)">${bd}${age?` <small style="color:var(--text-3);font-size:.7rem">(${age} سنة)</small>`:''}</td>
            <td><span style="background:var(--coupon-bg);color:var(--coupon-dark);padding:3px 10px;border-radius:var(--r-full);font-weight:700;font-size:.8rem"><i class="fas fa-star" style="font-size:.65rem"></i> ${s['كوبونات']||'0'}</span></td>
        </tr>`;
    }).join('') : '<tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--text-3)">لا بيانات</td></tr>';
}
function _calcAge(bdStr) {
    if (!bdStr || bdStr === '---') return '';
    const p = bdStr.split('/'); if (p.length < 3) return '';
    const bd = new Date(parseInt(p[2]), parseInt(p[1])-1, parseInt(p[0]));
    if (isNaN(bd)) return '';
    const now = new Date();
    let age = now.getFullYear() - bd.getFullYear();
    if (now < new Date(now.getFullYear(), bd.getMonth(), bd.getDate())) age--;
    return age > 0 && age < 25 ? age : '';
}

function getActiveViewStudents() {
    if (isCombinedView) return combinedStudents || [];
    if (currentClass) return students.filter(s => s['الفصل'] === currentClass);
    return allStudentsData.length ? allStudentsData : students;
}
function getActiveViewLabel() {
    if (currentClass === '__ALL__') return window.IS_YOUTH ? 'كل الشباب' : 'كل الأطفال';
    return currentClass || (window.IS_YOUTH ? 'كل الشباب' : 'كل الأطفال');
}
function getAttendanceCountForStudent(s) {
    const presentDates = new Set();
    Object.keys(s || {}).forEach(k => {
        if (k.includes('/') && getServerAttendanceStatus(s, k) === 'present') presentDates.add(k);
    });
    if (s && s._allAttendance) {
        Object.keys(s._allAttendance).forEach(k => {
            if (getServerAttendanceStatus(s, k) === 'present') presentDates.add(k);
        });
    }
    return presentDates.size;
}
function getStudentAgeValue(s) {
    const age = parseInt(_calcAge(s['عيد الميلاد'] || ''), 10);
    return Number.isFinite(age) ? age : 999;
}
function getStudentCouponTotal(s) {
    const id = getStudentId(s);
    return (parseInt(s['كوبونات'] || 0) || 0) + (parseInt(couponData[id] || 0) || 0);
}
function sortStudentsForCurrentView(list) {
    const mode = classSortMode || document.getElementById('classSortSelect')?.value || 'name_az';
    const collator = new Intl.Collator('ar', { sensitivity: 'base', numeric: true });
    const arr = [...(list || [])];
    arr.sort((a, b) => {
        const nameA = a['الاسم'] || '', nameB = b['الاسم'] || '';
        if (mode === 'name_za') return collator.compare(nameB, nameA);
        if (mode === 'age_asc') return getStudentAgeValue(a) - getStudentAgeValue(b) || collator.compare(nameA, nameB);
        if (mode === 'age_desc') {
            const ageA = getStudentAgeValue(a) === 999 ? -1 : getStudentAgeValue(a);
            const ageB = getStudentAgeValue(b) === 999 ? -1 : getStudentAgeValue(b);
            return ageB - ageA || collator.compare(nameA, nameB);
        }
        if (mode === 'class_az') return collator.compare(a['الفصل'] || '', b['الفصل'] || '') || collator.compare(nameA, nameB);
        if (mode === 'coupons_desc') return getStudentCouponTotal(b) - getStudentCouponTotal(a) || collator.compare(nameA, nameB);
        if (mode === 'attendance_desc') return getAttendanceCountForStudent(b) - getAttendanceCountForStudent(a) || collator.compare(nameA, nameB);
        if (mode === 'top_desc') {
            const scoreA = getStudentCouponTotal(a) + (getAttendanceCountForStudent(a) * 10);
            const scoreB = getStudentCouponTotal(b) + (getAttendanceCountForStudent(b) * 10);
            return scoreB - scoreA || collator.compare(nameA, nameB);
        }
        return collator.compare(nameA, nameB);
    });
    return arr;
}
function filterAndSortActiveStudents() {
    let list = getActiveViewStudents();
    if (searchQuery) {
        const q = searchQuery.toLowerCase();
        list = list.filter(s => (s['الاسم'] || '').toLowerCase().includes(q));
    }
    return sortStudentsForCurrentView(list);
}

// ── SEARCH ────────────────────────────────────────────────────
function setupLiveSearch() {
    const si = document.getElementById('searchInput'); if (!si) return;
    si.addEventListener('input', () => { clearTimeout(searchTimeout); if(!si.value.trim()){clearSearch();return;} searchTimeout=setTimeout(()=>{searchQuery=si.value.trim();executeSearch();},280); });
    si.addEventListener('keyup', e => { if(e.key==='Enter'){clearTimeout(searchTimeout);searchQuery=si.value.trim();executeSearch();} });
}
function executeSearch() {
    if (!searchQuery || !currentClass) { clearSearch(); return; }
    const q = searchQuery.toLowerCase();
    filteredStudents = getActiveViewStudents().filter(s => (s['الاسم'] || '').toLowerCase().includes(q));
    renderAttendanceList(currentClass); document.getElementById('clearSearchBtn').style.display='flex';
}
function clearSearch() {
    searchQuery=''; filteredStudents=[];
    const si=document.getElementById('searchInput'); if(si) si.value='';
    const cb=document.getElementById('clearSearchBtn'); if(cb) cb.style.display='none';
    const ri=document.getElementById('searchResultsInfo'); if(ri) ri.classList.remove('show');
    clearTimeout(searchTimeout); searchTimeout=null;
    if(currentClass) renderAttendanceList(currentClass);
}
function updateSearchResultsInfo(count) {
    const el=document.getElementById('searchResultsInfo'); if(!el) return;
    const total=getActiveViewStudents().length;
    if(searchQuery){el.innerHTML=count>0?`<i class="fas fa-search"></i> ${count} من ${total} طفل لـ "${searchQuery}"`:`<i class="fas fa-exclamation-circle"></i> لا نتائج لـ "${searchQuery}"`;el.classList.add('show');}else el.classList.remove('show');
}
function performSearch() { searchQuery=(document.getElementById('searchInput')?.value||'').trim(); executeSearch(); }
function setupAllStudentsSearch() {
    const si=document.getElementById('allStudentsSearch'); if(!si) return;
    si.addEventListener('input',()=>{clearTimeout(allStudentsSearchTimeout);if(!si.value.trim()){clearAllStudentsSearch();return;}allStudentsSearchTimeout=setTimeout(()=>{allStudentsSearchQuery=si.value.trim();performAllStudentsSearch();},280);});
    si.addEventListener('keyup',e=>{if(e.key==='Enter'){clearTimeout(allStudentsSearchTimeout);allStudentsSearchQuery=si.value.trim();performAllStudentsSearch();}});
}
function performAllStudentsSearch() {
    if(!allStudentsSearchQuery){clearAllStudentsSearch();return;}
    const q=allStudentsSearchQuery.toLowerCase();
    filteredAllStudents=allStudentsData.filter(s=>['الاسم','الفصل','رقم التليفون','العنوان','عيد الميلاد'].some(k=>(s[k]||'').toLowerCase().includes(q)));
    renderAllStudentsTable();
}
function clearAllStudentsSearch() { allStudentsSearchQuery=''; filteredAllStudents=[]; const si=document.getElementById('allStudentsSearch');if(si)si.value=''; clearTimeout(allStudentsSearchTimeout);allStudentsSearchTimeout=null; renderAllStudentsTable(); }

// ── CSV / PDF / IMAGE EXPORTS ─────────────────────────────────
function exportToCSV(data, headers, filename) {
    let csv='\uFEFF'+headers.join(',')+'\n'; data.forEach(row=>{csv+=headers.map(h=>`"${(row[h]||'').toString().replace(/"/g,'""')}"`).join(',')+'\n';});
    const a=document.createElement('a'); a.href=URL.createObjectURL(new Blob([csv],{type:'text/csv;charset=utf-8;'})); a.download=filename+'.csv'; a.click(); URL.revokeObjectURL(a.href);
}
function getCustomExportDefaultFields() {
    const fields = [
        { key:'photo', label:'الصورة', type:'photo', selected:false },
        { key:'name', label:'الاسم', source:'الاسم', selected:true },
        { key:'class', label:'الفصل', source:'الفصل', selected:true },
        { key:'phone', label:'رقم التليفون', source:'رقم التليفون', selected:true },
        { key:'address', label:'العنوان', source:'العنوان', selected:false },
        { key:'birthday', label:'عيد الميلاد', source:'عيد الميلاد', selected:false },
        { key:'age', label:'السن', type:'age', selected:false },
        { key:'coupons', label:'الكوبونات', source:'كوبونات', selected:false },
        { key:'attended_count', label:'إجمالي الحضور', type:'attendance_count', selected:false },
    ];
    if (churchCustomFields && churchCustomFields.length) {
        churchCustomFields.forEach((cf, idx) => fields.push({
            key:'custom_' + idx,
            label:cf.name || ('حقل مخصص ' + (idx + 1)),
            type:'custom',
            customIndex:idx,
            selected:false
        }));
    }
    return fields;
}
function initCustomExportFields(force = false) {
    if (force || !customExportFields.length) customExportFields = getCustomExportDefaultFields();
    renderCustomExportFields();
}
function renderCustomExportFields() {
    const box = document.getElementById('customExportFields');
    if (!box) return;
    box.innerHTML = customExportFields.map((f, i) => `
        <div class="export-field-row">
            <input type="checkbox" id="cef_${f.key}" ${f.selected ? 'checked' : ''} onchange="toggleCustomExportField('${f.key}',this.checked)">
            <label for="cef_${f.key}">${f.label}</label>
            <div class="export-field-actions">
                <button class="export-mini-btn" onclick="moveCustomExportField(${i},-1)" title="أعلى"><i class="fas fa-arrow-up"></i></button>
                <button class="export-mini-btn" onclick="moveCustomExportField(${i},1)" title="أسفل"><i class="fas fa-arrow-down"></i></button>
            </div>
        </div>
    `).join('');
}
function toggleCustomExportField(key, checked) {
    const f = customExportFields.find(x => x.key === key);
    if (f) f.selected = checked;
    renderCustomExportPreview();
}
function moveCustomExportField(index, dir) {
    const next = index + dir;
    if (next < 0 || next >= customExportFields.length) return;
    const tmp = customExportFields[index];
    customExportFields[index] = customExportFields[next];
    customExportFields[next] = tmp;
    renderCustomExportFields();
    renderCustomExportPreview();
}
function getAllAttendanceDateColumnsForStudents(fs) {
    const dates = new Set();
    fs.forEach(s => {
        Object.keys(s || {}).forEach(k => {
            if (k.includes('/') && k.length === 10) {
                const v = s[k];
                if (['ح','غ','حاضر','غائب','present','absent'].includes(v)) dates.add(k);
            }
        });
        if (s._allAttendance) Object.keys(s._allAttendance).forEach(d => {
            if (d.includes('/')) dates.add(d);
        });
    });
    return [...dates].sort((a,b) => parseDate(b) - parseDate(a));
}
function parseCustomExportDates() {
    const fs = getActiveViewStudents();
    const mode = document.getElementById('customExportDateMode')?.value || 'none';
    const allDates = getAllAttendanceDateColumnsForStudents(fs);
    if (mode === 'none') return [];
    if (mode === 'current') return currentFriday ? [currentFriday] : [];
    if (mode === 'all') return allDates;
    if (mode === 'range') {
        const from = (document.getElementById('customExportFromDate')?.value || '').trim();
        const to = (document.getElementById('customExportToDate')?.value || '').trim();
        const valid = v => !v || /^\d{2}\/\d{2}\/\d{4}$/.test(v);
        if (!valid(from) || !valid(to)) return [];
        if (from && to && parseDate(from) > parseDate(to)) return [];
        return allDates.filter(d => (!from || parseDate(d) >= parseDate(from)) && (!to || parseDate(d) <= parseDate(to)));
    }
    if (mode === 'custom') {
        const raw = (document.getElementById('customExportDates')?.value || '').trim();
        return raw.split(/[\s,،]+/).map(x => x.trim()).filter(x => /^\d{2}\/\d{2}\/\d{4}$/.test(x));
    }
    return [];
}
function getCustomFieldValue(s, idx) {
    const info = s._customInfo || {};
    return info['field_' + idx] || (idx === 0 ? (info.value || '') : '') || '';
}
function getCustomExportCellValue(s, field, forCsv = false) {
    if (field.type === 'photo') return forCsv ? (s['صورة'] || '') : (s['صورة'] ? `<img class="custom-export-photo" src="${window.photoUrl(s['صورة'])}" alt="">` : '');
    if (field.type === 'age') return _calcAge(s['عيد الميلاد'] || '') || '';
    if (field.type === 'attendance_count') return getAttendanceCountForStudent(s);
    if (field.type === 'custom') return getCustomFieldValue(s, field.customIndex);
    return s[field.source] || '';
}
function getAttendanceDisplayValue(s, date) {
    let v = s[date] || (s._allAttendance && s._allAttendance[date]) || '';
    if (v === 'present' || v === 'حاضر') v = 'ح';
    if (v === 'absent' || v === 'غائب') v = 'غ';
    return v;
}
function getCustomExportConfig() {
    const fields = customExportFields.filter(f => f.selected);
    const dates = parseCustomExportDates();
    const title = (document.getElementById('customExportTitle')?.value || '').trim() || ('تقرير ' + getActiveViewLabel());
    return { fields, dates, title };
}
function renderCustomExportPreview() {
    const preview = document.getElementById('customExportPreview');
    if (!preview) return;
    const cfg = getCustomExportConfig();
    const fs = sortStudentsForCurrentView(getActiveViewStudents());
    if (!cfg.fields.length && !cfg.dates.length) {
        preview.innerHTML = '<div style="text-align:center;padding:3rem;color:#64748b">اختر عموداً واحداً على الأقل</div>';
        return;
    }
    const headers = [...cfg.fields.map(f => f.label), ...cfg.dates];
    const dateLabel = cfg.dates.length ? cfg.dates.join('، ') : 'بدون أعمدة حضور';
    preview.innerHTML = `
        <div class="custom-export-title">
            <div>
                <h2>${escHtml(cfg.title)}</h2>
                <small>${escHtml(getActiveViewLabel())} - ${fs.length} ${window.IS_YOUTH ? 'عضو' : 'طفل'}</small>
                <small>${escHtml(dateLabel)}</small>
            </div>
            <small>${new Date().toLocaleDateString('ar-EG')}</small>
        </div>
        <table class="custom-export-table">
            <thead><tr>${headers.map(h => `<th>${escHtml(h)}</th>`).join('')}</tr></thead>
            <tbody>
                ${fs.map(s => `<tr>
                    ${cfg.fields.map(f => `<td>${f.type === 'photo' ? getCustomExportCellValue(s, f, false) : escHtml(String(getCustomExportCellValue(s, f, false) || ''))}</td>`).join('')}
                    ${cfg.dates.map(d => {
                        const v = getAttendanceDisplayValue(s, d);
                        const cls = v === 'ح' ? 'att-p' : (v === 'غ' ? 'att-a' : '');
                        return `<td class="${cls}">${escHtml(v || '')}</td>`;
                    }).join('')}
                </tr>`).join('')}
            </tbody>
        </table>`;
}
function showCustomExportModal() {
    initCustomExportFields();
    const title = document.getElementById('customExportTitle');
    if (title && !title.value) title.value = 'تقرير ' + getActiveViewLabel();
    document.getElementById('customExportModal').classList.add('active');
    renderCustomExportPreview();
    stopAutoRefresh();
}
function hideCustomExportModal() { document.getElementById('customExportModal').classList.remove('active'); startAutoRefresh(); }
function updateCustomExportDateControls() {
    const mode = document.getElementById('customExportDateMode')?.value || 'none';
    const range = document.getElementById('customExportRangeInputs');
    const custom = document.getElementById('customExportDates');
    if (range) range.style.display = mode === 'range' ? 'grid' : 'none';
    if (custom) custom.style.display = mode === 'custom' ? 'block' : 'none';
    renderCustomExportPreview();
}
function exportCustomAsCSV() {
    const cfg = getCustomExportConfig();
    if (!cfg.fields.length && !cfg.dates.length) { showToast('اختر بيانات للتصدير','info'); return; }
    const headers = [...cfg.fields.map(f => f.label), ...cfg.dates];
    const rows = sortStudentsForCurrentView(getActiveViewStudents()).map(s => {
        const row = {};
        cfg.fields.forEach(f => { row[f.label] = getCustomExportCellValue(s, f, true); });
        cfg.dates.forEach(d => { row[d] = getAttendanceDisplayValue(s, d); });
        return row;
    });
    exportToCSV(rows, headers, cfg.title.replace(/[\/\s]+/g,'-'));
    showToast('تم تصدير CSV','success');
}
async function exportCustomPreviewAsImage() {
    const preview = document.getElementById('customExportPreview');
    if (!preview) return;
    showToast('جاري تجهيز الصورة...', 'info');
    try {
        const canvas = await html2canvas(preview, { scale:2, backgroundColor:'#ffffff', logging:false, useCORS:true, allowTaint:true });
        const a = document.createElement('a');
        a.download = (getCustomExportConfig().title || 'custom-export').replace(/[\/\s]+/g,'-') + '.png';
        a.href = canvas.toDataURL('image/png');
        a.click();
        showToast('تم حفظ الصورة','success');
    } catch(e) { showToast('فشل حفظ الصورة: ' + e.message, 'error'); }
}
async function exportCustomPreviewAsPdf() {
    const preview = document.getElementById('customExportPreview');
    if (!preview) return;
    showToast('جاري إنشاء PDF...', 'info');
    try {
        const {jsPDF} = window.jspdf;
        if (!jsPDF) { showToast('مكتبة PDF غير محملة','error'); return; }
        const canvas = await html2canvas(preview, { scale:2, backgroundColor:'#ffffff', logging:false, useCORS:true, allowTaint:true });
        const orientation = canvas.width > canvas.height ? 'landscape' : 'portrait';
        const pdf = new jsPDF({ orientation, unit:'mm', format:'a4' });
        const pageW = pdf.internal.pageSize.getWidth();
        const pageH = pdf.internal.pageSize.getHeight();
        const imgW = pageW - 12;
        const imgH = canvas.height * imgW / canvas.width;
        const img = canvas.toDataURL('image/png');
        let y = 6, remaining = imgH;
        pdf.addImage(img, 'PNG', 6, y, imgW, imgH);
        while (remaining > pageH - 12) {
            remaining -= pageH - 12;
            pdf.addPage();
            pdf.addImage(img, 'PNG', 6, 6 - (imgH - remaining), imgW, imgH);
        }
        pdf.save((getCustomExportConfig().title || 'custom-export').replace(/[\/\s]+/g,'-') + '.pdf');
        showToast('تم حفظ PDF','success');
    } catch(e) { showToast('فشل PDF: ' + e.message, 'error'); }
}
function saveSheetAsCSV() {
    const fs = sortStudentsForCurrentView(getActiveViewStudents());
    const dateCols = getSheetDateColumns(fs);
    const headers = ['الاسم','الفصل','العنوان','رقم التليفون','عيد الميلاد','كوبونات', ...dateCols];
    const rows = fs.map(s => {
        const row = {...s};
        dateCols.forEach(d => {
            let v = s[d] || (s._allAttendance && s._allAttendance[d]) || '';
            if (v === 'present' || v === 'حاضر') v = 'ح';
            if (v === 'absent' || v === 'غائب') v = 'غ';
            row[d] = v;
        });
        return row;
    });
    exportToCSV(rows, headers, `حضور_${getActiveViewLabel()}_${sheetDateFrom||'كل'}_${sheetDateTo||'كل'}`.replace(/[\/\s]+/g,'-'));
    showToast('تم تصدير CSV','success');
}
function exportAllAsCSV() { exportToCSV(allStudentsSearchQuery?filteredAllStudents:allStudentsData,['الاسم','الفصل','العنوان','رقم التليفون','عيد الميلاد','كوبونات'],'جميع_الأطفال'); showToast('تم التصدير','success'); }
function saveAbsentAsCSV() { exportToCSV(getAttendanceStatusStudents('absent'),['الاسم','الفصل','رقم التليفون','العنوان'],`غائبين_${getActiveViewLabel()}_${currentFriday.replace(/\//g,'-')}`); showToast('تم التصدير','success'); }
function saveAttendedAsCSV() { exportToCSV(getAttendanceStatusStudents('present'),['الاسم','الفصل','رقم التليفون','العنوان'],`حاضرين_${getActiveViewLabel()}_${currentFriday.replace(/\//g,'-')}`); showToast('تم التصدير','success'); }
async function saveSheetAsImage() {
    showToast('جاري تجهيز الصورة...', 'info');
    try {
        // ── Build a full off-screen clone of the table ───────────
        // We grab the actual rendered <table> (not the clipped scroll container)
        // so the image captures every row and column regardless of screen size.
        const srcTable = document.getElementById('sheetTable');
        if (!srcTable) { showToast('لا يوجد جدول','error'); return; }

        const title    = currentClass || 'جميع الفصول';
        const dateStr  = new Date().toLocaleDateString('ar-EG');

        // Wrapper div — wide enough to never wrap columns
        const wrap = document.createElement('div');
        wrap.style.cssText = [
            'position:fixed',
            'left:-99999px',
            'top:0',
            'background:#ffffff',
            'font-family:Cairo,Tahoma,Arial,sans-serif',
            'direction:rtl',
            'padding:16px',
            'width:max-content',
            'min-width:800px',
            'box-sizing:border-box',
        ].join(';');

        // Header bar
        const header = document.createElement('div');
        header.style.cssText = 'display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;padding-bottom:8px;border-bottom:2px solid #1e293b';
        header.innerHTML =
            `<div style="font-size:18px;font-weight:800;color:#1e293b">${title}</div>` +
            `<div style="font-size:12px;color:#64748b">${dateStr}</div>`;
        wrap.appendChild(header);

        // Clone the table with inline styles (html2canvas needs explicit styles)
        const tClone = srcTable.cloneNode(true);
        tClone.style.cssText = 'border-collapse:collapse;width:100%;font-size:12px';

        // Style all th/td in the clone
        [...tClone.querySelectorAll('th')].forEach(th => {
            th.style.cssText = 'background:#1e293b;color:#fff;padding:8px 10px;text-align:right;font-weight:700;border:1px solid #334155;white-space:nowrap';
        });
        [...tClone.querySelectorAll('td')].forEach((td, i) => {
            // Alternate row shading based on row index
            const rowIdx = Math.floor(i / (tClone.querySelectorAll('th').length || 1));
            const bg = rowIdx % 2 === 0 ? '#f8fafc' : '#ffffff';
            td.style.cssText = `background:${bg};padding:7px 10px;text-align:right;border:1px solid #e2e8f0;white-space:nowrap`;
            // Colour ح / غ cells
            const v = td.textContent.trim();
            if (v === 'ح') td.style.cssText += ';background:#d1fae5;color:#065f46;font-weight:700;text-align:center';
            else if (v === 'غ') td.style.cssText += ';background:#fee2e2;color:#991b1b;font-weight:700;text-align:center';
        });

        wrap.appendChild(tClone);
        document.body.appendChild(wrap);

        // ── Render full table to one large canvas ────────────────
        const fullCanvas = await html2canvas(wrap, {
            scale: 2,              // retina quality
            backgroundColor: '#ffffff',
            logging: false,
            useCORS: true,
            allowTaint: true,
        });
        document.body.removeChild(wrap);

        const fullW = fullCanvas.width;
        const fullH = fullCanvas.height;

        // ── Slice into pages (max ~3000px tall per image at scale 2) ──
        const PAGE_H = 3000;  // pixels at scale 2 (= 1500 logical px)
        const pages  = Math.ceil(fullH / PAGE_H);

        const baseName = `جدول_${title}_${new Date().toISOString().slice(0,10)}`;

        for (let p = 0; p < pages; p++) {
            const sliceY = p * PAGE_H;
            const sliceH = Math.min(PAGE_H, fullH - sliceY);

            const pageCanvas  = document.createElement('canvas');
            pageCanvas.width  = fullW;
            pageCanvas.height = sliceH;

            const ctx = pageCanvas.getContext('2d');
            ctx.drawImage(fullCanvas, 0, sliceY, fullW, sliceH, 0, 0, fullW, sliceH);

            const a = document.createElement('a');
            a.download = pages > 1 ? `${baseName}_${p+1}_${pages}.png` : `${baseName}.png`;
            a.href = pageCanvas.toDataURL('image/png');
            a.click();

            // Small delay between downloads so browser doesn't block them
            if (p < pages - 1) await new Promise(r => setTimeout(r, 400));
        }

        const msg = pages > 1
            ? `تم حفظ ${pages} صور (الجدول كامل)`
            : 'تم حفظ الصورة';
        showToast(msg, 'success');

    } catch(e) {
        console.error(e);
        showToast('فشل الحفظ: ' + e.message, 'error');
    }
}
async function saveSheetAsPdf() {
    showToast('جاري إنشاء PDF...', 'info');
    try {
        const {jsPDF} = window.jspdf;
        if (!jsPDF) { showToast('مكتبة PDF غير محملة','error'); return; }

        const fs = sortStudentsForCurrentView(getActiveViewStudents());
        if (!fs.length) { showToast('لا بيانات للتصدير','info'); return; }

        const fixedCols  = ['الاسم','الفصل','العنوان','رقم التليفون','عيد الميلاد','كوبونات'];
        const dateCols   = getSheetDateColumns(fs);

        // Decide orientation based on total columns
        const totalCols = fixedCols.length + dateCols.length;
        const orientation = totalCols > 12 ? 'landscape' : (totalCols > 7 ? 'landscape' : 'portrait');
        const pdf = new jsPDF({ orientation, unit:'mm', format:'a4', putOnlyUsedFonts:true });

        const pageW = pdf.internal.pageSize.getWidth();
        const margin = 10;

        // Title
        pdf.setFontSize(13);
        pdf.text(currentClass || 'جميع الفصول', pageW - margin, margin + 4, { align:'right' });
        pdf.setFontSize(9);
        pdf.text(new Date().toLocaleDateString('ar-EG'), margin, margin + 4);

        // Build table data
        const headers = [...fixedCols, ...dateCols];
        const body = fs.map(s => {
            const row = fixedCols.map(k => String(s[k] || ''));
            dateCols.forEach(d => {
                let v = s[d] || (s._allAttendance && s._allAttendance[d]) || '';
                if (v==='حاضر'||v==='present') v='ح';
                if (v==='غائب'||v==='absent')  v='غ';
                row.push(v);
            });
            return row;
        });

        // Column widths: name wider, date cols narrow
        const usableW = pageW - margin * 2;
        const dateColW = Math.max(6, Math.min(10, (usableW * 0.45) / (dateCols.length || 1)));
        const fixedW   = (usableW - dateColW * dateCols.length) / fixedCols.length;
        const colWidths = [...fixedCols.map(()=>fixedW), ...dateCols.map(()=>dateColW)];

        pdf.autoTable({
            head: [headers],
            body,
            startY: margin + 10,
            margin: { right: margin, left: margin },
            styles: {
                font: 'helvetica',
                fontSize: dateCols.length > 15 ? 6 : dateCols.length > 8 ? 7 : 8,
                cellPadding: 2,
                halign: 'right',
                overflow: 'linebreak',
            },
            headStyles: {
                fillColor: [30, 41, 59],
                textColor: 255,
                fontStyle: 'bold',
                halign: 'center',
            },
            alternateRowStyles: { fillColor: [248, 250, 252] },
            columnStyles: Object.fromEntries(colWidths.map((w,i) => [i, {cellWidth: w}])),
            // Attendance columns: centered, colored
            didParseCell(data) {
                if (data.section === 'body' && data.column.index >= fixedCols.length) {
                    const v = data.cell.raw;
                    if (v === 'ح') { data.cell.styles.fillColor = [209,250,229]; data.cell.styles.textColor = [6,95,70]; }
                    else if (v === 'غ') { data.cell.styles.fillColor = [254,226,226]; data.cell.styles.textColor = [153,27,27]; }
                    data.cell.styles.halign = 'center';
                }
            },
            // Page break header repeat
            showHead: 'everyPage',
        });

        const fname = (currentClass || 'الكل') + '_' + new Date().toISOString().slice(0,10) + '.pdf';
        pdf.save(fname);
        showToast('تم حفظ PDF', 'success');
    } catch(e) {
        console.error(e);
        showToast('فشل: ' + e.message, 'error');
    }
}

// ── PHOTO EDITOR ──────────────────────────────────────────────
let currentImageFile = null;
function openCropModal(src) {
    if(cropper){cropper.destroy();cropper=null;}
    const img=document.getElementById('cropImage'); img.src=src;
    document.getElementById('cropModal').classList.add('active');
    img.onload=()=>{cropper=new Cropper(img,{aspectRatio:1,viewMode:1,autoCropArea:.8});};
}
function closeCropModal() { document.getElementById('cropModal').classList.remove('active'); if(cropper){cropper.destroy();cropper=null;} document.getElementById('photoInput').value=''; document.getElementById('newStudentPhotoInput').value=''; }
function confirmCrop() {
    if(!cropper) return;
    const canvas=cropper.getCroppedCanvas({width:400,height:400}), dataURL=canvas.toDataURL('image/jpeg',.9);
    if(currentPhotoEditorType==='uncle') { canvas.toBlob(blob=>{currentCroppedBlob=blob;document.getElementById('accountBigAvatar').src=dataURL;closeCropModal();uploadUnclePhoto(blob);},'image/jpeg',.9); }
    else {
        const isNew = currentPhotoEditorType==='new';
        const prev  = document.getElementById(isNew?'newStudentUploadPreview':'uploadPreview');
        const ctrl  = document.getElementById(isNew?'newStudentUploadControls':'uploadControls');
        const ph    = document.getElementById(isNew?'newStudentPhotoPlaceholder':'photoPlaceholder');
        if(prev&&ctrl){ prev.src=dataURL; prev.style.display='block'; ctrl.style.display='flex'; }
        if(ph) ph.style.display='none';
        canvas.toBlob(blob=>{currentCroppedBlob=blob;closeCropModal();showToast('تم القص، يمكنك الرفع الآن','success');},'image/jpeg',.9);
    }
}
function handleImageSelect(e) { if(e.target.files?.[0]){currentImageFile=e.target.files[0];currentPhotoEditorType='existing';openCropModal(URL.createObjectURL(currentImageFile));} }
function handleNewStudentImageSelect(e) { if(e.target.files?.[0]){currentImageFile=e.target.files[0];currentPhotoEditorType='new';openCropModal(URL.createObjectURL(currentImageFile));} }
function uploadStudentPhoto() {
    if(!currentCroppedBlob||!currentStudentForEdit){showToast('اختر صورة أولاً','error');return;}
    showLoading('جاري الرفع...');
    const fd=new FormData(); fd.append('photo',new File([currentCroppedBlob],`profile_${Date.now()}.jpg`,{type:'image/jpeg'})); fd.append('studentName',currentStudentForEdit['الاسم']); fd.append('studentClass',currentStudentForEdit['الفصل']);
    fetch('https://sunday-school.rf.gd/upload.php',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if(d.success){makeApiCall({action:'updateStudentImage',studentName:currentStudentForEdit['الاسم'],imageUrl:d.imageUrl},()=>{showToast('تم الرفع','success');cancelPhotoUpload();setTimeout(loadData,500);},()=>showToast('رُفعت ولكن فشل التحديث','warning'));}
        else showToast('فشل الرفع: '+(d.message||''),'error');
    }).catch(()=>showToast('خطأ في الاتصال','error'));
}
function cancelPhotoUpload() {
    const p=document.getElementById('uploadPreview'), c=document.getElementById('uploadControls'), i=document.getElementById('photoInput');
    const ph=document.getElementById('photoPlaceholder');
    if(p){p.style.display='none';} if(c)c.style.display='none'; if(i)i.value='';
    if(ph) ph.style.display='flex';
}
function cancelNewStudentPhotoUpload() {
    const p=document.getElementById('newStudentUploadPreview'), c=document.getElementById('newStudentUploadControls'), i=document.getElementById('newStudentPhotoInput');
    const ph=document.getElementById('newStudentPhotoPlaceholder');
    if(p){p.style.display='none';} if(c)c.style.display='none'; if(i)i.value='';
    if(ph) ph.style.display='flex';
}
function uploadNewStudentPhoto() { showToast('سيتم الرفع تلقائياً عند إضافة الطفل','info'); }

// ── UNCLE PROFILE ─────────────────────────────────────────────
function loadUncleProfile() {
    // Always show cached uncle info immediately from localStorage (no flicker)
    (function _showCachedUncle() {
        const name = localStorage.getItem('uncleName');
        const cachedImg = localStorage.getItem('uncleImageUrl');
        const chip = document.getElementById('uncleChip');
        if (chip && name) {
            chip.style.display = 'flex';
            const av = document.getElementById('uncleAvatar');
            const ini = document.getElementById('uncleInitials');
            const initials = _getInitials(name);
            if (ini) ini.textContent = initials;
            if (cachedImg && av) {
                av.src = window.photoUrl(cachedImg);
                av.style.display = 'block';
                if (ini) ini.style.display = 'none';
                av.onerror = function(){ this.style.display='none'; if(ini){ini.textContent=initials;ini.style.display='flex';} };
            } else if (ini) {
                ini.textContent = initials;
                ini.style.display = 'flex';
                if (av) av.style.display = 'none';
            }
            // Also update hero name immediately from cache
            const heroEl = document.getElementById('heroName');
            if (heroEl && name && !heroEl.textContent.trim()) heroEl.textContent = name;
        }
    })();

    if (!navigator.onLine) {
        return; // cached display above is all we can do offline
    }
    makeApiCall({action:'getCurrentUncle'}, r => {
        if(r.uncle?.id){
            window.currentUncle=r.uncle;
            localStorage.setItem('uncleName', r.uncle.name || '');
            localStorage.setItem('uncleUsername', r.uncle.username || '');
            localStorage.setItem('uncleRole', r.uncle.role || '');
            const chip = document.getElementById('uncleChip');
            if (chip) chip.style.display='flex';
            // Update hero greeting with fresh name from DB
            const heroEl = document.getElementById('heroName');
            if (heroEl && r.uncle.name) heroEl.textContent = r.uncle.name;
            const av=document.getElementById('uncleAvatar');
            const ini=document.getElementById('uncleInitials');
            const initials = _getInitials(r.uncle.name||'');
            if(ini) ini.textContent = initials;
            if(r.uncle.image_url){
                if(av){ av.src = window.photoUrl(r.uncle.image_url); av.style.display='block'; }
                if(ini) ini.style.display='none';
                if(av) av.onerror=function(){ this.style.display='none'; if(ini){ini.style.display='flex';} };
                try { localStorage.setItem('uncleImageUrl', r.uncle.image_url); } catch(e){}
            } else {
                if(av) av.style.display='none';
                if(ini) ini.style.display='flex';
                try { localStorage.removeItem('uncleImageUrl'); } catch(e){}
            }
            _sendUnclMetaToSW();
        }
    }, ()=>{/* silently ignore errors — cached display already shown */});
}
function uploadUnclePhoto(blob) {
    showLoading('جاري رفع الصورة...');
    const fd=new FormData(); fd.append('photo',new File([blob],`uncle_${Date.now()}.jpg`,{type:'image/jpeg'})); fd.append('username',window.currentUncle?.username||'user');
    fetch('https://sunday-school.rf.gd/upload_uncle.php/',{method:'POST',body:fd}).then(r=>r.json()).then(d=>{
        if(d.success){makeApiCall({action:'updateUncleImage',imageUrl:d.imageUrl},()=>{
            showToast('تم التحديث','success');
            const av=document.getElementById('uncleAvatar');
            if(av) av.src=window.photoUrl(d.imageUrl);
            const bav=document.getElementById('accountBigAvatar');
            if(bav) bav.src=window.photoUrl(d.imageUrl);
            if(window.currentUncle) window.currentUncle.image_url=d.imageUrl;
            try { localStorage.setItem('uncleImageUrl', d.imageUrl); } catch(e){} // store raw URL
        },()=>showToast('فشل التحديث','error'));}
        else showToast('فشل الرفع','error');
    }).catch(()=>showToast('خطأ','error'));
}

// ── CLASS UNCLES ──────────────────────────────────────────────
function loadClassUncles(className) {
    const bar  = document.getElementById('unclesBar');
    const list = document.getElementById('unclesList');
    if (!bar || !list) return;
    // Skip network call when offline — uncles bar not critical
    if (!navigator.onLine) { bar.style.display = 'none'; return; }

    bar.style.display  = 'flex';
    list.innerHTML = Array(3).fill('<div class="skeleton-uncle"></div>').join('');

    makeApiCall({action:'getClassUncles', class:className}, r => {
        if (r.uncles && r.uncles.length) {
            list.innerHTML = r.uncles.map(u =>
                `<div class="uncle-avatar-wrap">` +
                `<img class="uncle-avatar-img" src="${window.photoUrl(u.image_url||'https://sunday-school.rf.gd/profile_default..webp')}"` +
                ` alt="${u.name}" onerror="this.src='https://sunday-school.rf.gd/profile_default..webp'">` +
                `<div class="uncle-tooltip">${u.name}</div>` +
                `</div>`
            ).join('');
        } else {
            bar.style.display = 'none';
        }
    }, () => { bar.style.display = 'none'; });
}

// ── PENDING REGISTRATIONS ─────────────────────────────────────
function loadPendingRegistrationsForClass(className) {
    // Skip when offline — not critical, avoids console errors and hanging requests
    if (!navigator.onLine) { document.getElementById('pendingRegistrationsSection').style.display='none'; return; }
    makeApiCall({action:'getPendingRegistrations',class:className}, r => {
        pendingRegistrations = r.data&&Array.isArray(r.data) ? r.data : [];
        const sec=document.getElementById('pendingRegistrationsSection');
        if(pendingRegistrations.length){sec.style.display='block';renderPendingRegistrations(className);}else sec.style.display='none';
    }, ()=>{ document.getElementById('pendingRegistrationsSection').style.display='none'; });
}
// ── Helper: initials from Arabic name ─────────────────────────
function _regInitials(name) {
    const parts = (name||'').trim().split(/\s+/);
    if (parts.length >= 2) return parts[0][0] + parts[1][0];
    return (parts[0]||'?')[0];
}

// ── Toggle collapse pending section ───────────────────────────
function togglePendingSection() {
    const body = document.getElementById('pendingBody');
    const btn  = document.getElementById('pendingCollapseBtn');
    const open = body.style.display !== 'none';
    body.style.display = open ? 'none' : 'block';
    btn.classList.toggle('open', !open);
}

// ── Update bulk action bar visibility & count ─────────────────
function _updateBulkBar() {
    const n   = selectedRegistrations.size;
    const row = document.getElementById('pendingBulkRow');
    const cnt = document.getElementById('selectedCount');
    if (row) row.style.display = n > 0 ? 'flex' : 'none';
    if (cnt) cnt.textContent = n;
}

// ── Toggle card details expand ────────────────────────────────
function toggleRegCard(id) {
    const det = document.getElementById('reg-det-' + id);
    const btn = document.getElementById('reg-tog-' + id);
    if (!det) return;
    const open = det.classList.toggle('open');
    if (btn) btn.classList.toggle('open', open);
}

// ── Main render ───────────────────────────────────────────────
function renderPendingRegistrations(className, regs) {
    const list = document.getElementById('pendingList');
    const data = regs || pendingRegistrations;

    // update count badge
    const badge = document.getElementById('pendingCountBadge');
    if (badge) badge.textContent = data.length;

    if (!data.length) {
        list.innerHTML = '<div style="text-align:center;padding:1.2rem;color:var(--text-3);font-size:.85rem"><i class="fas fa-check-circle" style="font-size:1.4rem;color:var(--success);display:block;margin-bottom:6px"></i>لا توجد طلبات معلقة</div>';
        return;
    }

    list.innerHTML = data.map(r => {
        const id    = r.id || r.ID;
        const name  = r['الاسم']  || r.name  || '---';
        const phone = r['الهاتف'] || r.phone || '';
        const cls   = r['الفصل'] || r.class  || '';
        const bday  = r['تاريخ الميلاد'] || '';
        const addr  = r['العنوان'] || r.address || '';
        const email = r['البريد الإلكتروني'] || r.email || '';
        const date  = r['تاريخ الإنشاء'] || '';
        const ini   = _regInitials(name);
        const safe  = JSON.stringify(r).replace(/"/g,'&quot;').replace(/'/g,'&#39;');

        return `
        <div class="reg-card" id="reg-card-${id}">
          <div class="reg-card-top" onclick="toggleRegCard(${id})">
            <div class="reg-avatar">${ini}</div>
            <div class="reg-info">
              <div class="reg-name">${name}</div>
              <div class="reg-meta">
                ${phone ? `<span><i class="fas fa-phone"></i>${phone}</span>` : ''}
                ${cls   ? `<span><i class="fas fa-graduation-cap"></i>${cls}</span>` : ''}
                ${date  ? `<span><i class="fas fa-calendar-alt"></i>${date}</span>` : ''}
              </div>
            </div>
            <div class="reg-status-badge"><i class="fas fa-clock"></i> منتظر</div>
            <button class="reg-expand-toggle" id="reg-tog-${id}" onclick="event.stopPropagation();toggleRegCard(${id})">
              <i class="fas fa-chevron-down"></i>
            </button>
          </div>

          <div class="reg-details" id="reg-det-${id}">
            ${bday  ? `<div class="reg-detail-row"><span class="reg-detail-label"><i class="fas fa-birthday-cake"></i> تاريخ الميلاد</span><span class="reg-detail-val">${bday}</span></div>` : ''}
            ${addr  ? `<div class="reg-detail-row"><span class="reg-detail-label"><i class="fas fa-map-marker-alt"></i> العنوان</span><span class="reg-detail-val">${addr}</span></div>` : ''}
            ${email ? `<div class="reg-detail-row"><span class="reg-detail-label"><i class="fas fa-envelope"></i> البريد</span><span class="reg-detail-val">${email}</span></div>` : ''}
            <div class="reg-detail-row"><span class="reg-detail-label"><i class="fas fa-hashtag"></i> رقم الطلب</span><span class="reg-detail-val">#${id}</span></div>
          </div>

          <div class="reg-actions">
            <button class="btn btn-success" onclick="approveRegistration(${id})"><i class="fas fa-check"></i> موافقة</button>
            <button class="btn btn-danger"  onclick="rejectRegistration(${id})"><i class="fas fa-times"></i> رفض</button>
          </div>

          <div class="reg-select-row">
            <input type="checkbox" id="sr-${id}" onchange="toggleRegistrationSelection(${id},this.checked)">
            <label for="sr-${id}">تحديد للعمليات الجماعية</label>
          </div>
        </div>`;
    }).join('');
}

function showRegistrationDetails(reg) {
    currentRegistrationDetails = reg;
    stopAutoRefresh();
    document.getElementById('registrationDetailsModal').classList.add('active');
    const fields = [
        ['رقم التسجيل', reg.id||reg.ID],
        ['الاسم',        reg['الاسم']||reg.name],
        ['الفصل',        reg['الفصل']||reg.class],
        ['تاريخ الميلاد',reg['تاريخ الميلاد']||reg.birth_date],
        ['الهاتف',       reg['الهاتف']||reg.phone],
        ['البريد',       reg['البريد الإلكتروني']||reg.email],
        ['العنوان',      reg['العنوان']||reg.address],
        ['الحالة',       reg['الحالة']||reg.status||'قيد الانتظار'],
    ];
    document.getElementById('registrationDetails').innerHTML = fields.map(([l,v]) =>
        `<div style="display:flex;justify-content:space-between;padding:9px 0;border-bottom:1px solid var(--border)">
            <span style="font-weight:700;color:var(--text-3);font-size:.8rem">${l}</span>
            <span style="font-size:.86rem;color:var(--text);font-weight:600">${v||'---'}</span>
        </div>`
    ).join('');
}

function approveRegistration(id) {
    if (confirm('الموافقة على هذا التسجيل؟')) processRegistration(id, 'approved', 'الموافقة');
}
function rejectRegistration(id) {
    const note = prompt('سبب الرفض (اختياري):', '');
    if (note !== null) processRegistration(id, 'rejected', 'الرفض', note);
}
function processRegistration(id, action, name, note='') {
    const card = document.getElementById('reg-card-' + id);
    if (card) { card.style.opacity = '.5'; card.style.pointerEvents = 'none'; }
    const apiAction = action === 'approved' ? 'approveRegistration' : 'updateRegistration';
    makeApiCall(
        {action: apiAction, registrationId: id, status: action, ...(note ? {rejectionNote: note} : {})},
        r => {
            showToast('تم ' + name + ' بنجاح', 'success');
            hideRegistrationDetails();
            loadPendingRegistrationsForClass(currentClass);
            setTimeout(loadData, 1000);
        },
        e => {
            if (card) { card.style.opacity = '1'; card.style.pointerEvents = ''; }
            showToast('فشل: ' + e, 'error');
        }
    );
}
function toggleRegistrationSelection(id, val) {
    if (val) selectedRegistrations.add(id);
    else     selectedRegistrations.delete(id);
    _updateBulkBar();
}
function approveAllSelected() {
    if (!selectedRegistrations.size) { showToast('لم تحدد أي طلبات', 'info'); return; }
    if (!confirm('موافقة على ' + selectedRegistrations.size + ' طلب؟')) return;
    showLoading('جاري الموافقة...');
    makeApiCall(
        {action:'bulkUpdateRegistrations', registrationIds:[...selectedRegistrations], status:'approved'},
        r => { showToast('تمت الموافقة على ' + selectedRegistrations.size + ' طلب', 'success'); selectedRegistrations.clear(); _updateBulkBar(); loadPendingRegistrationsForClass(currentClass); setTimeout(loadData,1000); },
        e => showToast('فشل: ' + e, 'error')
    );
}
function rejectAllSelected() {
    if (!selectedRegistrations.size) { showToast('لم تحدد أي طلبات', 'info'); return; }
    if (!confirm('رفض ' + selectedRegistrations.size + ' طلب؟')) return;
    showLoading('جاري الرفض...');
    makeApiCall(
        {action:'bulkUpdateRegistrations', registrationIds:[...selectedRegistrations], status:'rejected'},
        r => { showToast('تم رفض ' + selectedRegistrations.size + ' طلب', 'success'); selectedRegistrations.clear(); _updateBulkBar(); loadPendingRegistrationsForClass(currentClass); setTimeout(loadData,1000); },
        e => showToast('فشل: ' + e, 'error')
    );
}
function searchPendingRegistrations() {
    const q = (document.getElementById('pendingSearchInput')?.value || '').toLowerCase().trim();
    if (!q) { renderPendingRegistrations(currentClass); return; }
    const filtered = pendingRegistrations.filter(r =>
        (r['الاسم']||r.name||'').toLowerCase().includes(q) ||
        (r['الهاتف']||r.phone||'').includes(q) ||
        (r['العنوان']||r.address||'').toLowerCase().includes(q)
    );
    renderPendingRegistrations(currentClass, filtered);
}

// ── ANNOUNCEMENTS ─────────────────────────────────────────────
function loadAnnouncements() {
    showLoading('تحميل الإعلانات...');
    makeApiCall({action:'getAllAnnouncements'},r=>{ if(r.announcements) renderAnnouncementsTable(r.announcements); },()=>showToast('فشل التحميل','error'));
}
function renderAnnouncementsTable(anns) {
    const body=document.getElementById('announcementsTableBody'),cnt=document.getElementById('activeAnnouncementsCount');
    if(!anns?.length){body.innerHTML='<tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--text-3)">لا إعلانات</td></tr>';if(cnt)cnt.textContent='0';return;}
    let active=0;
    body.innerHTML=anns.map(a=>{
        const isActive=a['منشط']===true||a['منشط']==='TRUE'||a['منشط']==='true'||a['منشط']===1||a['منشط']==='1'; if(isActive)active++;
        return `<tr><td><span class="badge ${a['النوع']==='button'?'btn-coupon':'btn-info'}" style="font-size:.72rem">${a['النوع']==='button'?'<i class="fas fa-link"></i> زر':'<i class="fas fa-comment"></i> رسالة'}</span></td><td style="max-width:180px;word-break:break-word;color:var(--text)">${a['النص']||''} ${a['الرابط']?`<br><a href="${a['الرابط']}" target="_blank" style="color:var(--brand);font-size:.74rem">${a['الرابط']}</a>`:''}</td><td style="color:var(--text)">${a['الفصل']==='الجميع'?'الكل':(a['الفصل']||'الكل')}</td><td style="font-size:.74rem;color:var(--text-3)">${a['أسماء الطلاب']||'الجميع'}</td><td><span class="badge ${isActive?'btn-success':'btn-danger'}" style="cursor:pointer;font-size:.72rem" onclick="toggleAnnouncementStatus(${a.rowIndex},${!isActive})">${isActive?'<i class="fas fa-check"></i> منشط':'<i class="fas fa-times"></i> معطل'}</span></td><td style="font-size:.72rem;color:var(--text-3)">${a['تاريخ الإضافة']||''}</td><td><button class="btn btn-danger btn-xs" onclick="deleteAnnouncement(${a.rowIndex},'${(a['النص']||'').replace(/'/g,"\\'")}')"><i class="fas fa-trash"></i></button></td></tr>`;
    }).join('');
    if(cnt)cnt.textContent=active;
}
function toggleAnnouncementStatus(idx,val) { if(confirm(`${val?'تفعيل':'تعطيل'} هذا الإعلان؟`)){showLoading('...');makeApiCall({action:'toggleAnnouncement',rowIndex:idx,active:val?'true':'false'},r=>{showToast(r.message,'success');loadAnnouncements();},()=>showToast('فشل','error'));} }
function deleteAnnouncement(idx,txt) { if(confirm(`حذف "${txt}"؟`)){showLoading('...');makeApiCall({action:'deleteAnnouncement',rowIndex:idx},r=>{showToast(r.message,'success');loadAnnouncements();},()=>showToast('فشل','error'));} }

// ── AUTO REFRESH ──────────────────────────────────────────────
function initAutoRefresh() { startAutoRefresh(); document.addEventListener('visibilitychange', ()=>{ if(!document.hidden&&autoRefreshEnabled) setTimeout(checkForUpdates,1000); }); }
function startAutoRefresh() { stopAutoRefresh(); if(autoRefreshEnabled) refreshTimer=setInterval(()=>{ if(!document.hidden) checkForUpdates(); },35000); }
function stopAutoRefresh() { if(refreshTimer){clearInterval(refreshTimer);refreshTimer=null;} }
function checkForUpdates() { if(document.hidden||!autoRefreshEnabled) return; makeApiCall({action:'getData',checkOnly:'true'},r=>{ const h=JSON.stringify(r.data).split('').reduce((a,c)=>((a<<5)-a)+c.charCodeAt(0)|0,0).toString(); if(lastDataHash&&h!==lastDataHash) showToast('توجد بيانات جديدة','info'); lastDataHash=h; },()=>{}); }

// ── BIRTHDAY INPUT FORMATTER ──────────────────────────────────
function autoFormatBirthdayInput(input) {
    let v=input.value.replace(/\D/g,'').substring(0,8),f='';
    if(v.length>0)f=v.substring(0,2); if(v.length>2)f+='/'+v.substring(2,4); if(v.length>4)f+='/'+v.substring(4,8);
    input.value=f;
    if(f.length===10){const[d,m,y]=f.split('/').map(Number);input.style.borderColor=isValidDate(d,m,y)?'var(--success)':'var(--danger)';}else input.style.borderColor='';
}
function isValidDate(d,m,y) { if(y<1900||y>new Date().getFullYear()||m<1||m>12||d<1) return false; const dm=[31,y%4===0&&(y%100!==0||y%400===0)?29:28,31,30,31,30,31,31,30,31,30,31]; return d<=dm[m-1]; }
function setupBirthdayInputListeners() {
    document.querySelectorAll('input[id*="Birthday"],input[id*="birthday"]').forEach(inp => {
        inp.addEventListener('input', ()=>autoFormatBirthdayInput(inp));
        inp.addEventListener('paste', e=>{e.preventDefault();const p=(e.clipboardData||window.clipboardData).getData('text').replace(/\D/g,'');if(p.length>=8)inp.value=`${p.substring(0,2)}/${p.substring(2,4)}/${p.substring(4,8)}`;autoFormatBirthdayInput(inp);});
    });
}

// ── EVENT LISTENERS ───────────────────────────────────────────
function setupEventListeners() {
    const on=(id,ev,fn)=>{const el=document.getElementById(id);if(el)el.addEventListener(ev,fn);};
    on('backBtn','click',showClassesView);
    on('showBirthdayModalBtn','click',showBirthdayModal);
    on('showAllStudentsModalBtn','click',showAllStudentsModal);
    on('manageAnnouncementsBtn','click',showAnnouncementsModal);
    on('closeBirthdayModal','click',hideBirthdayModal);
    on('closeAllStudentsModal','click',hideAllStudentsModal);
    on('closeStudentModal','click',hideStudentModal);
    on('cancelEditBtn','click',hideEditForm);
    on('closeAddPersonModal','click',hideAddPersonModal);
    on('cancelAddPersonModal','click',hideAddPersonModal);
    on('closeSheetModal','click',hideSheetModal);
    on('closeCustomExportModal','click',hideCustomExportModal);
    on('closePastFridaysModal','click',hidePastFridaysModal);
    on('closeAttendedModal','click',hideAttendedModal);
    on('closeAbsentModal','click',hideAbsentModal);
    on('closeDeleteStudentModal','click',hideDeleteStudentModal);
    on('cancelDeleteStudentBtn','click',hideDeleteStudentModal);
    on('imageModalClose','click',hideImageModal);
    on('cropClose','click',closeCropModal);
    on('cropCancel','click',closeCropModal);
    on('cropConfirm','click',confirmCrop);
    on('closeResetModal','click',hideResetModal);
    on('cancelResetBtn','click',hideResetModal);
    on('closeRegistrationDetailsModal','click',hideRegistrationDetails);
    on('closeAnnouncementsModal','click',hideAnnouncementsModal);
    on('editStudentBtn','click',showEditForm);
    on('deleteStudentBtn','click',()=>{ if(currentStudentForEdit) showDeleteStudentModal(currentStudentForEdit); });
    on('confirmDeleteStudentBtn','click',deleteStudent);
    on('editForm','submit',updateStudentInfo);
    on('addPersonForm','submit',addNewPerson);
    on('submitAttendance','click',submitAttendance);
    on('submitCoupons','click',submitCoupons);
    on('saveAllBtn','click',()=>showUnsavedModal());
    on('searchBtn','click',performSearch);
    on('clearSearchBtn','click',clearSearch);
    on('allStudentsSearchBtn','click',performAllStudentsSearch);
    on('saveSheetAsImageBtn','click',saveSheetAsImage);
    on('saveSheetAsPdfBtn','click',saveSheetAsPdf);
    on('saveSheetAsCsvBtn','click',saveSheetAsCSV);
    on('customExportCsvBtn','click',exportCustomAsCSV);
    on('customExportPdfBtn','click',exportCustomPreviewAsPdf);
    on('customExportImageBtn','click',exportCustomPreviewAsImage);
    on('customExportPreviewBtn','click',renderCustomExportPreview);
    on('customExportDateMode','change',updateCustomExportDateControls);
    on('customExportTitle','input',renderCustomExportPreview);
    on('customExportFromDate','input',renderCustomExportPreview);
    on('customExportToDate','input',renderCustomExportPreview);
    on('customExportDates','input',renderCustomExportPreview);
    on('applySheetDateRangeBtn','click',applySheetDateRange);
    on('clearSheetDateRangeBtn','click',clearSheetDateRange);
    on('exportAllAsCsvBtn','click',exportAllAsCSV);
    on('saveAttendedAsCsvBtn','click',saveAttendedAsCSV);
    on('copyAttendedModalBtn','click',copyAttendedData);
    on('saveAbsentAsCsvBtn','click',saveAbsentAsCSV);
    on('copyAbsentModalBtn','click',copyAbsentData);
    on('absentSearchInput','input',renderAbsentTable);
    on('classSortSelect','change',e=>{ classSortMode=e.target.value; renderAttendanceList(currentClass); });
    on('clearAbsentDataBtn','click',clearAbsentData);
    on('photoUploadArea','click',()=>document.getElementById('photoInput').click());
    on('photoInput','change',handleImageSelect);
    on('savePhotoBtn','click',uploadStudentPhoto);
    on('cancelUploadBtn','click',cancelPhotoUpload);
    on('newStudentPhotoUploadArea','click',()=>document.getElementById('newStudentPhotoInput').click());
    on('newStudentPhotoInput','change',handleNewStudentImageSelect);
    on('saveNewStudentPhotoBtn','click',uploadNewStudentPhoto);
    on('cancelNewStudentUploadBtn','click',cancelNewStudentPhotoUpload);
    on('unclePhotoInput','change',e=>{if(e.target.files?.[0]){currentImageFile=e.target.files[0];currentPhotoEditorType='uncle';openCropModal(URL.createObjectURL(currentImageFile));}});
    on('uncleProfileForm','submit',e=>{
        e.preventDefault();
        const name=document.getElementById('uncleProfileName').value.trim(),username=document.getElementById('uncleProfileUsername').value.trim(),pass=document.getElementById('uncleProfileNewPassword').value.trim();
        if(!name||!username){showToast('الاسم واسم المستخدم مطلوبان','error');return;}
        showLoading('جاري التحديث...');
        makeApiCall({action:'updateUncleProfile',name,username,new_password:pass},r=>{ showToast('تم التحديث','success'); document.getElementById('accountDisplayName').textContent=name; if(window.currentUncle){window.currentUncle.name=name;window.currentUncle.username=username;} hideAccountEditForm(); },e=>showToast('فشل: '+e,'error'));
    });
    on('resetAttendanceBtn','click',()=>{
        if(confirm('إعادة تعيين الحضور المحلي؟')){
            attendanceData={}; changedStudents.clear(); savedStudents.clear();
            if(currentClass){const dk=currentFriday;localStorage.removeItem(`attendanceData_${currentClass}_${dk}`);localStorage.removeItem(`savedStudents_${currentClass}_${dk}`);localStorage.removeItem(`changedStudents_${currentClass}_${dk}`);loadAttendanceDataForClass(currentClass,dk);renderAttendanceList(currentClass);}
            hideResetModal(); showToast('تم إعادة التعيين','success');
        }
    });
    on('resetCouponsBtn','click',()=>{if(confirm('إعادة تعيين الكوبونات؟')){resetCouponDataForClass(currentClass);hideResetModal();}});
    on('resetAllBtn','click',()=>{
        if(confirm('إعادة تعيين الكل؟')){
            attendanceData={}; changedStudents.clear(); savedStudents.clear();
            if(currentClass){localStorage.removeItem(`attendanceData_${currentClass}_${currentFriday}`);localStorage.removeItem(`changedStudents_${currentClass}_${currentFriday}`);loadAttendanceDataForClass(currentClass);}
            resetCouponDataForClass(currentClass); hideResetModal();
        }
    });
    on('resetToTodayBtn','click',resetToCurrentFriday);
    on('pendingSearchBtn','click',searchPendingRegistrations);
    on('approveAllSelectedBtn','click',approveAllSelected);
    on('rejectAllSelectedBtn','click',rejectAllSelected);
    on('approveRegistrationBtn','click',()=>{ if(currentRegistrationDetails) approveRegistration(currentRegistrationDetails.id||currentRegistrationDetails.ID); });
    on('rejectRegistrationBtn','click',()=>{ if(currentRegistrationDetails) document.getElementById('rejectionNoteContainer').style.display='block'; });
    on('addAnnouncementForm','submit',e=>{
        e.preventDefault();
        const type=document.getElementById('announcementType').value,text=document.getElementById('announcementText').value.trim(),link=document.getElementById('announcementLink').value.trim(),cls=document.getElementById('announcementClass').value,stds=document.getElementById('announcementStudents').value.trim();
        if(!text){showToast('أدخل نص الإعلان','error');return;} if(type==='button'&&!link){showToast('أدخل رابطاً للزر','error');return;}
        showLoading('...');
        makeApiCall({action:'addAnnouncement',type,text,link,class:cls,students:stds},r=>{showToast(r.message,'success');document.getElementById('addAnnouncementForm').reset();document.getElementById('linkFieldContainer').style.display='none';loadAnnouncements();},()=>showToast('فشل','error'));
    });
    on('clearAnnouncementForm','click',()=>{document.getElementById('addAnnouncementForm').reset();document.getElementById('linkFieldContainer').style.display='none';});
    on('announcementType','change',()=>{document.getElementById('linkFieldContainer').style.display=document.getElementById('announcementType').value==='button'?'block':'none';});
    on('actionsStripBtn','click',e=>{e.stopPropagation();toggleDropdown('actionsDropdownMenu','actionsStripBtn');});
    on('couponsStripBtn','click',e=>{e.stopPropagation();toggleDropdown('couponsDropdownMenu','couponsStripBtn');});
    // Overlay click & swipe-to-close
    document.querySelectorAll('.modal-overlay').forEach(overlay=>{
        overlay.addEventListener('click',e=>{
            if(e.target===overlay){ overlay.classList.remove('active'); startAutoRefresh(); if(overlay.id==='studentModal')currentStudentForEdit=null; if(overlay.id==='accountModal')hideAccountEditForm(); }
        });
        initSwipeToClose(overlay);
    });
}


// ── UNCLE ACTIVITY HISTORY ────────────────────────────────────
// ── History: human-readable action map ────────────────────────
// Each entry: { label, icon, color, category, desc(fn) }
const historyActions = {
    // ── Attendance ────────────────────────────────────────────
    attendance:       { label:'تسجيل حضور',    icon:'fa-user-check',  color:'#3b82f6', cat:'attendance' },
    attendance_edit:  { label:'تعديل حضور',     icon:'fa-user-edit',   color:'#f59e0b', cat:'attendance' },
    // ── Students ──────────────────────────────────────────────
    student_add:      { label:'إضافة طفل',      icon:'fa-user-plus',   color:'#10b981', cat:'student' },
    student_edit:     { label:'تعديل بيانات طفل',icon:'fa-user-edit',   color:'#f59e0b', cat:'student' },
    student_delete:   { label:'حذف طفل',         icon:'fa-user-times',  color:'#ef4444', cat:'student' },
    add:              { label:'إضافة',            icon:'fa-plus-circle', color:'#10b981', cat:'student' },
    insert:           { label:'إضافة',            icon:'fa-plus-circle', color:'#10b981', cat:'student' },
    create:           { label:'إنشاء',            icon:'fa-plus-circle', color:'#10b981', cat:'student' },
    edit:             { label:'تعديل',            icon:'fa-edit',        color:'#f59e0b', cat:'student' },
    update:           { label:'تعديل',            icon:'fa-edit',        color:'#f59e0b', cat:'student' },
    delete:           { label:'حذف',              icon:'fa-trash',       color:'#ef4444', cat:'student' },
    remove:           { label:'حذف',              icon:'fa-trash',       color:'#ef4444', cat:'student' },
    approve:          { label:'موافقة على تسجيل', icon:'fa-check-circle',color:'#10b981', cat:'student' },
    reject:           { label:'رفض تسجيل',        icon:'fa-times-circle',color:'#ef4444', cat:'student' },
    // ── Coupons ───────────────────────────────────────────────
    coupon:           { label:'تعديل كوبونات',    icon:'fa-star',        color:'#8b5cf6', cat:'coupon' },
    // ── Login ─────────────────────────────────────────────────
    login:            { label:'تسجيل دخول',       icon:'fa-sign-in-alt', color:'#5b6cf5', cat:'login' },
    logout:           { label:'تسجيل خروج',       icon:'fa-sign-out-alt',color:'#6b7280', cat:'login' },
    // ── Uncles / other ────────────────────────────────────────
    uncle_add:        { label:'إضافة خادم',        icon:'fa-user-plus',  color:'#10b981', cat:'other' },
    uncle_edit:       { label:'تعديل خادم',        icon:'fa-user-edit',  color:'#f59e0b', cat:'other' },
    uncle_delete:     { label:'حذف خادم',          icon:'fa-user-times', color:'#ef4444', cat:'other' },
    trip:             { label:'رحلة',              icon:'fa-bus',         color:'#06b6d4', cat:'other' },
    announcement:     { label:'إعلان',             icon:'fa-bullhorn',   color:'#f59e0b', cat:'other' },
};

// Friendly entity names (what the action was done to)
const historyEntityNames = {
    student:'طفل', uncle:'خادم', attendance:'حضور', coupon:'كوبونات',
    trip:'رحلة', announcement:'إعلان', church:'كنيسة', registration:'تسجيل', auth:'دخول',
};

// Kept for legacy compatibility with any code still using these
const auditActionLabels = Object.fromEntries(Object.entries(historyActions).map(([k,v])=>[k,v.label]));
const auditActionColors  = Object.fromEntries(Object.entries(historyActions).map(([k,v])=>[k,v.color]));

// ── Uncle history state ────────────────────────────────────────
let _historyLogs    = [];  // full raw log array
let _historyFilter  = '';  // selected category filter
let _historySearch  = '';  // search text

function filterHistory() {
    _historyFilter = document.getElementById('historyFilter')?.value || '';
    _historySearch = (document.getElementById('historySearch')?.value || '').trim().toLowerCase();
    renderHistoryList();
}

function renderHistoryList() {
    const content = document.getElementById('uncleHistoryContent');
    const empty   = document.getElementById('historyEmpty');
    if (!content) return;

    let logs = _historyLogs;

    // Category filter
    if (_historyFilter) {
        logs = logs.filter(l => {
            const meta = historyActions[l.action || ''];
            return meta ? meta.cat === _historyFilter : _historyFilter === 'other';
        });
    }

    // Text search
    if (_historySearch) {
        logs = logs.filter(l => {
            const hay = [l.action, l.entity_name, l.entity, l.notes, l.uncle_name, l.created_at]
                .filter(Boolean).join(' ').toLowerCase();
            return hay.includes(_historySearch);
        });
    }

    if (!logs.length) {
        content.innerHTML = '';
        if (empty) empty.style.display = 'block';
        return;
    }
    if (empty) empty.style.display = 'none';

    // Group by date (today / yesterday / older by date)
    const groups = {};
    const now     = new Date();
    const todayStr    = now.toISOString().slice(0,10);
    const yesterStr   = new Date(now - 86400000).toISOString().slice(0,10);

    logs.forEach(log => {
        const dateStr = (log.created_at || '').slice(0, 10);
        let groupKey;
        if (dateStr === todayStr)    groupKey = 'اليوم';
        else if (dateStr === yesterStr) groupKey = 'أمس';
        else if (dateStr)            groupKey = dateStr.split('-').reverse().join('/');
        else                         groupKey = 'بدون تاريخ';

        if (!groups[groupKey]) groups[groupKey] = [];
        groups[groupKey].push(log);
    });

    let html = '';
    Object.entries(groups).forEach(([dateLabel, items]) => {
        html += `<div style="font-size:.72rem;font-weight:800;color:var(--text-3);
            text-transform:uppercase;letter-spacing:.06em;padding:10px 0 4px;
            position:sticky;top:0;background:var(--surface);z-index:1;">
            ${dateLabel}
        </div>`;

        items.forEach(log => {
            const action = log.action || '';
            const meta   = historyActions[action] || { label: action, icon:'fa-circle', color:'#9ca3af', cat:'other' };
            const color  = meta.color;
            const icon   = meta.icon;
            const label  = meta.label;

            const entityName = log.entity_name || '';
            const entityType = historyEntityNames[log.entity || ''] || (log.entity || '');
            const actor      = log.uncle_name || '';
            const notes      = log.notes || '';
            const time       = (log.created_at || '').substring(11, 16); // HH:MM

            // Build a single clear sentence describing what happened
            let desc = label;
            if (entityName) desc += ` — <strong>${entityName}</strong>`;
            if (entityType && !entityName) desc += ` في ${entityType}`;

            html += `<div style="display:flex;align-items:center;gap:10px;padding:9px 0;
                border-bottom:1px solid var(--border);">

                <!-- Color-coded icon -->
                <div style="width:36px;height:36px;border-radius:10px;flex-shrink:0;
                    background:${color}18;display:flex;align-items:center;
                    justify-content:center;">
                    <i class="fas ${icon}" style="color:${color};font-size:.9rem"></i>
                </div>

                <!-- Main content -->
                <div style="flex:1;min-width:0;">
                    <div style="font-size:.84rem;font-weight:600;color:var(--text);line-height:1.3">${desc}</div>
                    ${notes ? `<div style="font-size:.75rem;color:var(--text-2);margin-top:2px">${notes}</div>` : ''}
                    ${actor ? `<div style="font-size:.72rem;color:var(--text-3);margin-top:1px"><i class="fas fa-user" style="font-size:.6rem"></i> ${actor}</div>` : ''}
                </div>

                <!-- Time badge -->
                <div style="font-size:.7rem;color:var(--text-3);white-space:nowrap;flex-shrink:0">${time}</div>
            </div>`;
        });
    });

    content.innerHTML = html;
}

function renderHistorySummary(logs) {
    const el = document.getElementById('historySummary');
    if (!el) return;
    const cats = {
        attendance: { label:'حضور', icon:'fa-user-check', color:'#3b82f6' },
        student:    { label:'أطفال', icon:'fa-child',      color:'#10b981' },
        coupon:     { label:'كوبونات', icon:'fa-star',     color:'#8b5cf6' },
        login:      { label:'دخول',   icon:'fa-sign-in-alt',color:'#5b6cf5' },
        other:      { label:'أخرى',   icon:'fa-ellipsis-h', color:'#9ca3af' },
    };
    const counts = {};
    logs.forEach(l => {
        const cat = (historyActions[l.action||'']?.cat) || 'other';
        counts[cat] = (counts[cat] || 0) + 1;
    });
    el.innerHTML = Object.entries(counts)
        .filter(([,n]) => n > 0)
        .map(([cat, n]) => {
            const c = cats[cat] || cats.other;
            return `<span style="display:inline-flex;align-items:center;gap:4px;
                padding:4px 10px;border-radius:var(--r-full);font-size:.72rem;font-weight:700;
                background:${c.color}15;color:${c.color};cursor:pointer;border:1.5px solid ${c.color}25"
                onclick="document.getElementById('historyFilter').value='${cat}';filterHistory()">
                <i class="fas ${c.icon}" style="font-size:.65rem"></i> ${c.label} ${n}
            </span>`;
        }).join('');
}

function showUncleHistory() {
    document.getElementById('accountModal').classList.remove('active');
    const modal   = document.getElementById('uncleHistoryModal');
    const content = document.getElementById('uncleHistoryContent');
    const empty   = document.getElementById('historyEmpty');

    // Reset state
    _historyLogs   = [];
    _historyFilter = '';
    _historySearch = '';
    if (document.getElementById('historyFilter')) document.getElementById('historyFilter').value = '';
    if (document.getElementById('historySearch')) document.getElementById('historySearch').value = '';
    if (document.getElementById('historySummary')) document.getElementById('historySummary').innerHTML = '';
    if (empty) empty.style.display = 'none';
    content.innerHTML = `<div style="text-align:center;padding:2rem;color:var(--text-3)">
        <i class="fas fa-spinner fa-spin" style="font-size:1.5rem"></i>
        <p style="margin-top:8px">جاري التحميل...</p>
    </div>`;

    modal.classList.add('active');
    stopAutoRefresh();

    const fd = new FormData();
    fd.append('action', 'getUncleActivityLogs');
    fd.append('limit', '300');
    fetch(API_URL, { method:'POST', body:fd, credentials:'include' })
        .then(r => r.json())
        .then(d => {
            if (!d.success) {
                content.innerHTML = `<div style="text-align:center;padding:2rem;color:var(--danger)">
                    <i class="fas fa-exclamation-circle" style="font-size:1.5rem;display:block;margin-bottom:8px"></i>
                    <div style="font-size:.88rem">${d.message || 'فشل في تحميل السجل'}</div>
                </div>`;
                return;
            }
            if (!d.logs || !d.logs.length) {
                content.innerHTML = `<div style="text-align:center;padding:3rem;color:var(--text-3)">
                    <i class="fas fa-scroll" style="font-size:2rem;display:block;margin-bottom:10px"></i>
                    لا يوجد نشاط مسجل بعد
                </div>`;
                return;
            }
            _historyLogs = d.logs;
            renderHistorySummary(_historyLogs);
            renderHistoryList();
        })
        .catch(e => {
            content.innerHTML = `<div style="text-align:center;padding:2rem;color:var(--danger)">
                <i class="fas fa-wifi" style="font-size:1.5rem;display:block;margin-bottom:8px"></i>
                <div style="font-size:.88rem">خطأ في الاتصال: ${e.message}</div>
            </div>`;
        });
}

// ══════════════════════════════════════════════════════════════
// HOLD ANIMATION (tap-and-hold visual feedback only)
// ══════════════════════════════════════════════════════════════
let _holdTimer    = null;
let _holdTarget   = null;
let _holdRipple   = null;
let _holdScrolled = false;
let _holdStartX   = 0;
let _holdStartY   = 0;
const HOLD_MS     = 700;

function _holdStart(e, studentName) {
    _holdCancel();
    const t = e.touches ? e.touches[0] : e;
    _holdStartX = t.clientX;
    _holdStartY = t.clientY;
    _holdScrolled = false;

    const el = e.currentTarget || e.target?.closest('.attendance-item');
    if (el) {
        _holdTarget = el;
        _holdRipple = document.createElement('div');
        _holdRipple.className = 'hold-ripple-overlay';
        el.appendChild(_holdRipple);
    }

    _holdTimer = setTimeout(() => {
        if (_holdScrolled) { _holdCancel(); return; }
        _holdCancel(false);
        navigator.vibrate && navigator.vibrate([12, 60, 20]);
    }, HOLD_MS);
}

function _holdMove(e) {
    if (!_holdTimer) return;
    const t = e.touches ? e.touches[0] : e;
    const dx = Math.abs(t.clientX - _holdStartX);
    const dy = Math.abs(t.clientY - _holdStartY);
    if (dx > 8 || dy > 8) { _holdScrolled = true; _holdCancel(); }
}

function _holdEnd() { _holdCancel(); }

function _holdCancel(removeRipple = true) {
    clearTimeout(_holdTimer); _holdTimer = null;
    if (removeRipple && _holdRipple) {
        _holdRipple.remove(); _holdRipple = null;
    }
    _holdTarget = null;
}

// Stubs kept for any remaining references
function _rowHoldStart(row, studentName) {}
function _rowHoldMove(e) {}
function _rowHoldEnd() {}
function _rowContextMenu(e, studentName) { e.preventDefault(); }
function _ctxAction(idx) {}
function _closeCtxMenu() {
    document.getElementById('ctxBackdrop')?.remove();
    document.getElementById('ctxMenu')?.remove();
}
window._ctxMenuActions = [];

// ══════════════════════════════════════════════════════════════
// IMAGE MODAL — zoom & pan
// ══════════════════════════════════════════════════════════════
function _imgZoomChange(delta) {
    _imgZoom = Math.min(5, Math.max(0.5, _imgZoom + delta));
    _applyImgTransform();
}
function _imgZoomReset() { _imgZoom = 1; _imgX = 0; _imgY = 0; _applyImgTransform(); }
function _applyImgTransform() {
    const img = document.getElementById('imageModalImg');
    if (img) img.style.transform = `scale(${_imgZoom}) translate(${_imgX}px,${_imgY}px)`;
}
function _imgDownload() {
    const src = document.getElementById('imageModalImg')?.src;
    if (!src) return;
    const a = document.createElement('a');
    a.href = src; a.download = 'student_photo.jpg';
    a.target = '_blank'; a.click();
}
// Pinch-to-zoom & drag on image modal
(function initImageModalGestures() {
    document.addEventListener('DOMContentLoaded', () => {
        const body = document.getElementById('imageModalBody');
        const img  = document.getElementById('imageModalImg');
        if (!body || !img) return;
        let lastDist = 0, isDragging = false, startX = 0, startY = 0, startImgX = 0, startImgY = 0;
        // Pinch zoom
        body.addEventListener('touchstart', e => {
            if (e.touches.length === 2) {
                const dx = e.touches[0].clientX - e.touches[1].clientX;
                const dy = e.touches[0].clientY - e.touches[1].clientY;
                lastDist = Math.hypot(dx, dy);
            } else if (e.touches.length === 1 && _imgZoom > 1) {
                isDragging = true;
                startX = e.touches[0].clientX; startY = e.touches[0].clientY;
                startImgX = _imgX; startImgY = _imgY;
            }
        }, {passive:true});
        body.addEventListener('touchmove', e => {
            if (e.touches.length === 2) {
                const dx = e.touches[0].clientX - e.touches[1].clientX;
                const dy = e.touches[0].clientY - e.touches[1].clientY;
                const dist = Math.hypot(dx, dy);
                if (lastDist > 0) {
                    _imgZoom = Math.min(5, Math.max(0.5, _imgZoom * (dist / lastDist)));
                    _applyImgTransform();
                }
                lastDist = dist; e.preventDefault();
            } else if (isDragging && e.touches.length === 1) {
                _imgX = startImgX + (e.touches[0].clientX - startX) / _imgZoom;
                _imgY = startImgY + (e.touches[0].clientY - startY) / _imgZoom;
                _applyImgTransform(); e.preventDefault();
            }
        }, {passive:false});
        body.addEventListener('touchend', () => { lastDist = 0; isDragging = false; });
        // Mouse drag
        body.addEventListener('mousedown', e => {
            if (_imgZoom <= 1) return;
            isDragging = true; startX = e.clientX; startY = e.clientY;
            startImgX = _imgX; startImgY = _imgY;
            body.classList.add('grabbing'); e.preventDefault();
        });
        document.addEventListener('mousemove', e => {
            if (!isDragging) return;
            _imgX = startImgX + (e.clientX - startX) / _imgZoom;
            _imgY = startImgY + (e.clientY - startY) / _imgZoom;
            _applyImgTransform();
        });
        document.addEventListener('mouseup', () => { isDragging = false; body.classList.remove('grabbing'); });
        // Close on Escape
        document.addEventListener('keydown', e => {
            if (e.key === 'Escape') hideImageModal();
        });
    });
})();

// ══════════════════════════════════════════════════════════════
// CONFETTI
// ══════════════════════════════════════════════════════════════
function _launchConfetti() {
    const container = document.getElementById('bdConfetti');
    if (!container) return;
    container.innerHTML = '';
    const colors = ['#5b6cf5','#db2777','#10b981','#f59e0b','#8b5cf6','#ef4444','#06b6d4'];
    for (let i = 0; i < 80; i++) {
        const el = document.createElement('div');
        const size = 6 + Math.random() * 8;
        el.style.cssText = `
            position:absolute;
            width:${size}px;height:${size}px;
            background:${colors[Math.floor(Math.random()*colors.length)]};
            border-radius:${Math.random()>.5?'50%':'2px'};
            left:${Math.random()*100}%;
            top:-20px;
            opacity:1;
            animation: confettiFall ${1.5+Math.random()*2}s ease-in ${Math.random()*1}s forwards;
            transform: rotate(${Math.random()*360}deg);
        `;
        container.appendChild(el);
    }
    // Add keyframes once
    if (!document.getElementById('confettiStyle')) {
        const st = document.createElement('style');
        st.id = 'confettiStyle';
        st.textContent = `@keyframes confettiFall{to{top:110%;opacity:0;transform:rotate(${720}deg) translateX(${(Math.random()-.5)*200}px)}}`;
        document.head.appendChild(st);
    }
    setTimeout(() => { container.innerHTML = ''; }, 4000);
}

// ══════════════════════════════════════════════════════════════
// WHATSAPP SHARE ABSENCE
// ══════════════════════════════════════════════════════════════
function shareAbsentToWhatsApp() {
    if (!currentClass) { showToast('اختر فصلاً أولاً','info'); return; }
    const absent = getAttendanceStatusStudents('absent');
    if (!absent.length) { showToast('لا يوجد غائبون','info'); return; }
    const txt = buildAttendanceShareText('absent', 'الغائبين', '📋');
    const url = `https://api.whatsapp.com/send?text=${encodeURIComponent(txt)}`;
    window.open(url, '_blank');
}
function shareAttendedToWhatsApp() {
    if (!currentClass) { showToast('اختر فصلاً أولاً','info'); return; }
    const attended = getAttendanceStatusStudents('present');
    if (!attended.length) { showToast('لا يوجد حاضرون','info'); return; }
    const txt = buildAttendanceShareText('present', 'الحاضرين', '✅');
    const url = `https://api.whatsapp.com/send?text=${encodeURIComponent(txt)}`;
    window.open(url, '_blank');
}

// ══════════════════════════════════════════════════════════════
// PWA — Service Worker, Install prompt, Offline mode
// ══════════════════════════════════════════════════════════════
let _pwaPrompt = null;

// ── VAPID public key (set this to your server's VAPID public key) ──────────
// Generate a VAPID key pair on your server with:
//   php artisan webpush:vapid  OR  npx web-push generate-vapid-keys
// Then paste the PUBLIC key string here:
const VAPID_PUBLIC_KEY = '<?php echo defined("VAPID_PUBLIC_KEY") ? VAPID_PUBLIC_KEY : (getenv("VAPID_PUBLIC_KEY") ?: ""); ?>';

// ── Register service worker ───────────────────────────────────
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js?v=7')
            .then(reg => {
                _initPushSubscription(reg);
                // ── Re-subscribe whenever SW becomes active after an update ──
                reg.addEventListener('updatefound', () => {
                    const newSW = reg.installing;
                    if (newSW) newSW.addEventListener('statechange', () => {
                        if (newSW.state === 'activated') _initPushSubscription(reg);
                    });
                });
            })
            .catch(() => {});
    });

    // ── Re-subscribe + reload data when coming back online ───────
    window.addEventListener('online', async () => {
        try {
            const reg = await navigator.serviceWorker.ready;
            _doSubscribe(reg); // silently re-subscribe in case it lapsed
        } catch(e) {}
        setTimeout(() => { if (navigator.onLine) loadData(); }, 1000);
    });

    // ── Messages from the Service Worker ─────────────────────────
    navigator.serviceWorker.addEventListener('message', e => {
        const d = e.data;
        if (!d) return;

        // Background sync completed — reload data so UI reflects server state
        if (d.type === 'SYNC_COMPLETE') {
            showToast(`✅ تمت المزامنة — رُفع ${d.count} تغيير في الخلفية`, 'success', { dur: 5000 });
            setTimeout(() => { if (navigator.onLine) loadData(); }, 800);
        }

        // New registration pushed to this device
        if (d.type === 'NEW_REGISTRATION') {
            showToast(`📋 طلب تسجيل جديد في ${d.className || 'الفصل'}`, 'info', { dur: 8000 });
            // Reload pending registrations no matter which class we're viewing
            if (currentClass) loadPendingRegistrationsForClass(currentClass);
            else if (typeof loadPendingRegistrationsForClass === 'function') {
                // Reload for all classes in the background so badge counts update
                const cls = [...new Set(students.map(s => s['الفصل']).filter(Boolean))];
                cls.forEach(c => loadPendingRegistrationsForClass(c));
            }
        }

        // Attendance saved by another device/tab
        if (d.type === 'ATTENDANCE_SAVED') {
            showToast(`🔄 تم حفظ الحضور من جهاز آخر`, 'info', { dur: 4000 });
            setTimeout(() => { if (navigator.onLine) loadData(); }, 500);
        }

        // Birthday reminder from SW
        if (d.type === 'BIRTHDAY_REMINDER') {
            const names = (d.names || []).join('، ');
            showToast(`🎂 عيد ميلاد اليوم: ${names}`, 'info', { dur: 9000 });
        }

        // User tapped a notification while app was in background
        if (d.type === 'NOTIFICATION_CLICK') {
            if (d.notifType === 'registration') {
                if (currentClass) {
                    loadPendingRegistrationsForClass(currentClass);
                    document.getElementById('pendingRegistrationsSection')?.scrollIntoView({ behavior: 'smooth' });
                } else {
                    // Navigate to classes view and scroll to pending
                    showClassesView();
                }
            } else if (d.notifType === 'sync') {
                showUnsavedModal();
            } else if (d.notifType === 'birthday') {
                showBirthdayModal();
            }
        }
    });
}

// ── Push subscription bootstrap ──────────────────────────────
async function _initPushSubscription(reg) {
    if (Notification.permission === 'granted') {
        await _doSubscribe(reg);
        // Check for today's birthdays and send notification if not sent yet today
        _maybeSendBirthdayNotification();
    }
    _updateNotifBtnVisibility();
}

// ── Called once per day to notify about today's birthdays ────
async function _maybeSendBirthdayNotification() {
    if (!navigator.onLine || !VAPID_PUBLIC_KEY) return;
    const today = new Date().toISOString().split('T')[0]; // YYYY-MM-DD
    const storageKey = `bdayNotifSent_${today}`;
    if (localStorage.getItem(storageKey)) return; // already sent today

    // Wait for students data to be loaded (may be called before loadData finishes)
    const tryCheck = () => {
        const data = allStudentsData.length ? allStudentsData : students;
        if (!data.length) return;
        const now = new Date();
        const todayKids = data.filter(s => {
            if (!s['عيد الميلاد']) return false;
            const p = s['عيد الميلاد'].split('/');
            return p.length >= 2 && parseInt(p[0]) === now.getDate() && parseInt(p[1]) - 1 === now.getMonth();
        });
        if (!todayKids.length) return;
        localStorage.setItem(storageKey, '1');
        const names = todayKids.map(s => s['الاسم'] || '').filter(Boolean);
        _sendBirthdayPush(names).catch(() => {});
    };
    // Try immediately; if data not ready yet, retry after loadData
    tryCheck();
    setTimeout(tryCheck, 3000);
    setTimeout(tryCheck, 8000);
}

async function _sendBirthdayPush(names) {
    if (!names.length || !navigator.onLine || !VAPID_PUBLIC_KEY) return;
    try {
        await makeApiCallRaw({
            action: 'sendPushNotification',
            target: 'self',
            uncle_id: window.currentUncle?.id || '',
            notifType: 'birthday',
            title: `🎂 أعياد ميلاد اليوم (${names.length})`,
            body: names.join('، '),
            url: window.location.href
        });
    } catch(e) {}
}

// Called from every notification button — asks permission then subscribes
async function requestNotifPermission() {
    if (!('Notification' in window)) {
        showToast('المتصفح لا يدعم الإشعارات', 'error'); return;
    }
    if (Notification.permission === 'denied') {
        showToast('الإشعارات محظورة — افتح إعدادات المتصفح لتفعيلها', 'warning', { dur: 7000 }); return;
    }
    if (Notification.permission === 'granted') {
        const reg = await navigator.serviceWorker.ready;
        await _doSubscribe(reg);
        showToast('الإشعارات مفعّلة بالفعل ✅', 'success');
        _updateNotifBtnVisibility();
        _maybeSendBirthdayNotification();
        return;
    }
    const perm = await Notification.requestPermission();
    if (perm === 'granted') {
        try {
            const reg = await navigator.serviceWorker.ready;
            await _doSubscribe(reg);
            showToast('✅ تم تفعيل الإشعارات!', 'success', { dur: 5000 });
            _maybeSendBirthdayNotification();
        } catch(e) {
            showToast('تم القبول، لكن فشل التسجيل — حاول مجدداً', 'warning');
        }
    } else {
        showToast('تم رفض الإشعارات — يمكنك تفعيلها لاحقاً من الإعدادات', 'info', { dur: 6000 });
    }
    _updateNotifBtnVisibility();
}

// Subscribes the device to Web Push and saves to server
async function _doSubscribe(reg) {
    if (!VAPID_PUBLIC_KEY) return;
    try {
        let sub = await reg.pushManager.getSubscription();
        if (!sub) {
            sub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: _urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
            });
        }
        await _savePushSubscriptionToServer(sub);
    } catch(e) {}
}

// Shows/hides all notification buttons based on current Notification.permission
function _updateNotifBtnVisibility() {
    const perm      = ('Notification' in window) ? Notification.permission : 'denied';
    const isDefault = perm === 'default';
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches
                      || window.navigator.standalone === true;

    // Show bell in topbar only if not yet asked AND not standalone (PWA)
    const notifBtn = document.getElementById('notifPermBtn');
    if (notifBtn) notifBtn.style.display = (isDefault && !isStandalone) ? 'flex' : 'none';

    const pwaNotifBtn = document.getElementById('pwaNotifBtn');
    if (pwaNotifBtn) pwaNotifBtn.style.display = isDefault ? 'flex' : 'none';

    const offlineNotifBtn = document.getElementById('offlineNotifBtn');
    if (offlineNotifBtn) offlineNotifBtn.style.display = (isDefault && isStandalone) ? 'flex' : 'none';
}

async function _savePushSubscriptionToServer(sub) {
    if (!sub || !navigator.onLine) return;
    try {
        const subJson = sub.toJSON();
        await makeApiCallRaw({
            action: 'savePushSubscription',
            endpoint: subJson.endpoint,
            p256dh: subJson.keys?.p256dh || '',
            auth: subJson.keys?.auth || '',
            uncle_id: window.currentUncle?.id || ''
        });
    } catch(e) {}
}

// makeApiCall wrapper that returns a promise (for async/await)
function makeApiCallRaw(params) {
    return new Promise((res, rej) => {
        makeApiCall(params, res, rej);
    });
}

// Converts a URL-safe base64 VAPID key to Uint8Array for subscribe()
function _urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64  = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const raw     = atob(base64);
    return new Uint8Array([...raw].map(c => c.charCodeAt(0)));
}

// Called after a successful attendance/coupon save — push to all subscribed devices
async function _sendSyncCompletePush(savedCount, type) {
    if (!navigator.onLine || !VAPID_PUBLIC_KEY) return;
    try {
        await makeApiCallRaw({
            action: 'sendPushNotification',
            target: 'church',  // notify ALL uncle devices for this church, not just sender
            notifType: 'sync',
            uncle_id: window.currentUncle?.id || '',
            title: type === 'coupons' ? 'مدارس الأحد — كوبونات ✅' : 'مدارس الأحد — حضور ✅',
            body: `تم حفظ ${savedCount} ${type === 'coupons' ? 'كوبون' : 'سجل حضور'} بنجاح`,
            url: window.location.href
        });
    } catch(e) {}
}

// Capture the install prompt
window.addEventListener('beforeinstallprompt', e => {
    e.preventDefault();
    _pwaPrompt = e;
    const btn = document.getElementById('pwaInstallBtn');
    if (btn) btn.style.display = 'flex';
});

// App installed
window.addEventListener('appinstalled', () => {
    _pwaPrompt = null;
    const btn = document.getElementById('pwaInstallBtn');
    if (btn) btn.style.display = 'none';
    closePwaModal();
    showToast('✅ تم تثبيت التطبيق بنجاح!', 'success');
});

function triggerPwaInstall() {
    // Detect OS for instructions
    const ua = navigator.userAgent;
    const isIOS = /iPad|iPhone|iPod/.test(ua);
    const isAndroid = /Android/.test(ua);
    const stepsEl = document.getElementById('pwaSteps');
    const installBtn = document.getElementById('pwaInstallNowBtn');

    if (_pwaPrompt) {
        // Native prompt available (Android Chrome)
        if (stepsEl) stepsEl.innerHTML = `
            <div class="pwa-step"><div class="pwa-step-num">1</div><div>اضغط "تثبيت الآن" أدناه</div></div>
            <div class="pwa-step"><div class="pwa-step-num">2</div><div>وافق على طلب التثبيت</div></div>
            <div class="pwa-step"><div class="pwa-step-num">3</div><div>افتح التطبيق من الشاشة الرئيسية</div></div>`;
        if (installBtn) installBtn.style.display = 'flex';
    } else if (isIOS) {
        if (stepsEl) stepsEl.innerHTML = `
            <div style="font-weight:700;color:var(--brand);margin-bottom:8px;font-size:.84rem">على iPhone / iPad:</div>
            <div class="pwa-step"><div class="pwa-step-num">1</div><div>اضغط زر المشاركة <i class="fas fa-share-square" style="color:var(--brand)"></i> في أسفل المتصفح</div></div>
            <div class="pwa-step"><div class="pwa-step-num">2</div><div>اختر "إضافة إلى الشاشة الرئيسية"</div></div>
            <div class="pwa-step"><div class="pwa-step-num">3</div><div>اضغط "إضافة" — سيظهر أيقونة التطبيق</div></div>`;
        if (installBtn) installBtn.style.display = 'none';
        const btn = document.getElementById('pwaInstallBtn');
        if (btn) btn.style.display = 'flex';
    } else {
        if (stepsEl) stepsEl.innerHTML = `
            <div class="pwa-step"><div class="pwa-step-num">1</div><div>اضغط قائمة المتصفح ⋮ أو ⋯</div></div>
            <div class="pwa-step"><div class="pwa-step-num">2</div><div>اختر "تثبيت التطبيق" أو "إضافة إلى الشاشة الرئيسية"</div></div>
            <div class="pwa-step"><div class="pwa-step-num">3</div><div>وافق على التثبيت</div></div>`;
        if (installBtn) installBtn.style.display = 'none';
    }
    document.getElementById('pwaInstallModal').classList.add('show');
    // Update notification button visibility when modal opens
    _updateNotifBtnVisibility();
}

async function doPwaInstall() {
    if (!_pwaPrompt) return;
    _pwaPrompt.prompt();
    const { outcome } = await _pwaPrompt.userChoice;
    if (outcome === 'accepted') {
        _pwaPrompt = null;
        closePwaModal();
    }
}

function closePwaModal() {
    document.getElementById('pwaInstallModal').classList.remove('show');
}

// Offline/online detection
let _connectivityState = { ok: true, ts: 0 };
async function _isActuallyOnline(force = false) {
    const now = Date.now();
    if (!force && (now - _connectivityState.ts) < 15000) return _connectivityState.ok;

    if (navigator.onLine) {
        _connectivityState = { ok: true, ts: now };
        return true;
    }

    try {
        const ctrl = new AbortController();
        const timer = setTimeout(() => ctrl.abort(), 3500);
        const res = await fetch(`/manifest.webmanifest?_probe=${now}`, {
            method: 'GET',
            cache: 'no-store',
            credentials: 'same-origin',
            signal: ctrl.signal
        });
        clearTimeout(timer);
        const ok = !!(res && res.ok);
        _connectivityState = { ok, ts: Date.now() };
        return ok;
    } catch(_) {
        _connectivityState = { ok: false, ts: Date.now() };
        return false;
    }
}

function _countAllPendingLocalChanges() {
    // Count only genuinely-unsaved changes.
    // A localStorage key is "stale" (already saved) if all its IDs are in savedStudents.
    // We also garbage-collect truly empty keys here.
    let total = 0;
    const keysToDelete = [];

    Object.keys(localStorage).forEach(k => {
        if (k.startsWith('changedStudents_')) {
            try {
                const arr = JSON.parse(localStorage.getItem(k) || '[]');
                // Filter out IDs already confirmed saved this session
                const unsaved = arr.filter(id => !savedStudents.has(id));
                if (unsaved.length === 0) {
                    keysToDelete.push(k); // stale — mark for cleanup
                } else {
                    total += unsaved.length;
                }
            } catch(e) {}
        }
        if (k.startsWith('changedCouponStudents_')) {
            try {
                const arr = JSON.parse(localStorage.getItem(k) || '[]');
                const unsaved = arr.filter(id => !savedCouponStudents.has(id));
                if (unsaved.length === 0) {
                    keysToDelete.push(k);
                } else {
                    total += unsaved.length;
                }
            } catch(e) {}
        }
    });

    // Clean up stale keys so they don't keep triggering false positives
    keysToDelete.forEach(k => { try { localStorage.removeItem(k); } catch(e) {} });

    return total;
}

// Track whether we were previously offline so we only show the "back online"
// toast when we actually CAME BACK from an offline state — not on first load.
let _wasOffline = false;

async function _updateOnlineStatus() {
    const banner = document.getElementById('offlineBanner');
    if (!banner) return;

    const onlineNow = await _isActuallyOnline(true);
    if (!onlineNow) {
        banner.classList.add('show');
        _wasOffline = true;
        if (typeof _updateNotifBtnVisibility === 'function') _updateNotifBtnVisibility();
        return;
    }

    // We are online
    banner.classList.remove('show');

    // Only run the "came back online" logic if we actually went offline first.
    // This prevents the toast from firing on initial page load.
    if (!_wasOffline) return;
    _wasOffline = false;

    const inMemory = changedStudents.size + changedCouponStudents.size;
    const allStored = _countAllPendingLocalChanges();
    const hasPending = inMemory > 0 || allStored > 0;

    if (hasPending) {
        const count = Math.max(inMemory, allStored);
        showToast(`عدت للإنترنت — جاري رفع ${count} تغيير تلقائياً…`, 'info', { dur: 5000 });
        setTimeout(() => { if (navigator.onLine) saveAllData(); }, 1200);
    }
    setTimeout(() => { if (navigator.onLine) loadData(); }, 2500);
}

// Returns first-letter of first name + first-letter of last name (Arabic-safe)
function _getInitials(name) {
    const parts = (name || '').trim().split(/\s+/).filter(Boolean);
    if (!parts.length) return '؟';
    if (parts.length === 1) return parts[0][0];
    return parts[0][0] + parts[parts.length - 1][0];
}

// Sends uncle identity + API URL to SW so background registration checks work
// even when the app is fully closed (periodic sync reads this from IDB)
async function _sendUnclMetaToSW() {
    if (!('serviceWorker' in navigator)) return;
    try {
        const reg = await navigator.serviceWorker.ready;
        if (!reg.active) return;
        reg.active.postMessage({
            type: 'SET_UNCLE_META',
            meta: {
                apiUrl:       (typeof API_URL !== 'undefined' ? API_URL : '/api.php'),
                uncleId:      window.currentUncle?.id || '',
                lastRegCount: 0   // SW will update this after first check
            }
        });
        // Also request periodic sync permission if not already set up
        _requestPeriodicSyncPermission(reg);
    } catch(err) {}
}

async function _requestPeriodicSyncPermission(reg) {
    try {
        if (!('periodicSync' in reg)) return;
        const status = await navigator.permissions.query({ name: 'periodic-background-sync' });
        if (status.state === 'granted') {
            await reg.periodicSync.register('check-registrations', { minInterval: 15 * 60 * 1000 });
        }
    } catch(err) {}
}
window.addEventListener('online',  _updateOnlineStatus);
window.addEventListener('offline', _updateOnlineStatus);
document.addEventListener('DOMContentLoaded', async () => {
    // On first load: only show the offline banner if we're already offline.
    // Do NOT run the "came back online" reconnect logic — that's for transitions only.
    const onlineNow = await _isActuallyOnline(true);
    if (!onlineNow) {
        const banner = document.getElementById('offlineBanner');
        if (banner) banner.classList.add('show');
        _wasOffline = true;
    } else {
        const banner = document.getElementById('offlineBanner');
        if (banner) banner.classList.remove('show');
    }
    _updateNotifBtnVisibility();
    // React to permission changes live (Chrome 93+)
    if ('permissions' in navigator) {
        navigator.permissions.query({ name: 'notifications' }).then(status => {
            status.onchange = () => _updateNotifBtnVisibility();
        }).catch(() => {});
    }
});

// ══════════════════════════════════════════════════════════════
// SWIPE MODE
// ══════════════════════════════════════════════════════════════
let _swipeList = [];      // students yet to swipe
let _swipeIdx  = 0;       // current index
let _swipePres = 0;       // present count
let _swipeAbs  = 0;       // absent count
let _swipeStartX = 0, _swipeStartY = 0, _swipeCurX = 0;
let _swipeDragging = false, _swipeCard = null;
const SWIPE_THRESHOLD = 80; // px to commit a swipe

function startSwipeMode() {
    const src = isCombinedView ? combinedStudents : students.filter(s => s['الفصل'] === currentClass);
    if (!src.length) { showToast('لا يوجد أطفال في هذا الفصل', 'info'); return; }
    _swipeList = [...src];
    _swipeIdx  = 0;
    _swipePres = 0;
    _swipeAbs  = 0;
    const btns  = document.querySelector('.swipe-btns');
    const hints = document.querySelector('.swipe-hints');
    if (btns)  btns.style.display  = '';
    if (hints) hints.style.display = '';
    const lbl = document.getElementById('swipeClassLabel');
    if (lbl) lbl.textContent = currentClass || '';
    document.getElementById('swipeOverlay').classList.add('active');
    document.body.style.overflow = 'hidden';
    _renderSwipeCard();
    _updateSwipeProgress();
}

function exitSwipeMode() {
    document.getElementById('swipeOverlay').classList.remove('active');
    document.body.style.overflow = '';
}

function _renderSwipeCard() {
    const card = document.getElementById('swipeCard');
    if (!card) return;
    card.style.transition = '';
    card.style.transform  = 'translateX(0) rotate(0deg)';
    card.style.opacity    = '1';

    if (_swipeIdx >= _swipeList.length) { _showSwipeDone(); return; }

    const s    = _swipeList[_swipeIdx];
    const id   = getStudentId(s);
    const st   = attendanceData[id] || 'pending';
    const totC = (parseInt(s['كوبونات']||0)) + (parseInt(couponData[id]||0));
    const left = _swipeList.length - _swipeIdx - 1;

    const prevPill =
        st === 'present' ? `<span class="swipe-prev-pill present"><i class="fas fa-check"></i> حضر سابقاً</span>` :
        st === 'absent'  ? `<span class="swipe-prev-pill absent"><i class="fas fa-times"></i> غائب سابقاً</span>` :
                           `<span class="swipe-prev-pill pending"><i class="fas fa-minus"></i> لا بيانات</span>`;

    const photoEl = s['صورة']
        ? `<img class="swipe-card-photo" src="${s['صورة']}" alt=""
               onerror="this.outerHTML='<div class=\\'swipe-card-photo-placeholder\\'><i class=\\'fas fa-user\\'></i><span>لا توجد صورة</span></div>'">`
        : `<div class="swipe-card-photo-placeholder"><i class="fas fa-user"></i><span>لا توجد صورة</span></div>`;

    card.innerHTML = `
        ${photoEl}
        <div class="swipe-card-wash" id="swipeCardWash"></div>
        <div class="swipe-stamp present-stamp" id="swipeStampPresent"><i class="fas fa-check-circle"></i> حضر</div>
        <div class="swipe-stamp absent-stamp"  id="swipeStampAbsent"><i class="fas fa-times-circle"></i> غاب</div>
        <div class="swipe-card-info">
            <div class="swipe-info-row1">
                <div class="swipe-card-name">${s['الاسم']||'---'}</div>
                <div class="swipe-coupon-pill"><i class="fas fa-star" style="font-size:.65rem"></i> ${totC}</div>
            </div>
            <div class="swipe-info-row2">
                <span class="swipe-class-pill">${s['الفصل']||''}</span>
                ${prevPill}
                ${left > 0 ? `<span class="swipe-remaining-pill">${left} متبقي</span>` : ''}
            </div>
        </div>`;

    const fresh = card.cloneNode(true);
    // Apply pre-assigned border class before replacing
    if (st === 'present') fresh.classList.add('pre-present');
    else if (st === 'absent') fresh.classList.add('pre-absent');
    card.parentNode.replaceChild(fresh, card);
    _swipeCard = document.getElementById('swipeCard');
    _attachSwipeListeners(_swipeCard);
}

function _attachSwipeListeners(card) {
    // Touch
    card.addEventListener('touchstart', e => {
        _swipeStartX = e.touches[0].clientX;
        _swipeStartY = e.touches[0].clientY;
        _swipeCurX   = 0;
        _swipeDragging = true;
        card.classList.add('dragging');
    }, { passive: true });

    card.addEventListener('touchmove', e => {
        if (!_swipeDragging) return;
        _swipeCurX = e.touches[0].clientX - _swipeStartX;
        _animateDrag(card, _swipeCurX);
    }, { passive: true });

    card.addEventListener('touchend', () => {
        card.classList.remove('dragging');
        _commitSwipe(card, _swipeCurX);
        _swipeDragging = false;
    });

    // Mouse
    card.addEventListener('mousedown', e => {
        _swipeStartX = e.clientX;
        _swipeCurX   = 0;
        _swipeDragging = true;
        card.classList.add('dragging');
        e.preventDefault();
    });
    document.addEventListener('mousemove', e => {
        if (!_swipeDragging) return;
        _swipeCurX = e.clientX - _swipeStartX;
        _animateDrag(card, _swipeCurX);
    });
    document.addEventListener('mouseup', () => {
        if (!_swipeDragging) return;
        card.classList.remove('dragging');
        _commitSwipe(card, _swipeCurX);
        _swipeDragging = false;
    });
}

function _animateDrag(card, dx) {
    const rot  = dx / 14;
    const prog = Math.min(Math.abs(dx) / SWIPE_THRESHOLD, 1);
    card.style.transform = `translateX(${dx}px) rotate(${rot}deg)`;
    // Labels
    const lP = document.getElementById('swipeStampPresent');
    const lA = document.getElementById('swipeStampAbsent');
    const ti = document.getElementById('swipeCardWash');
    if (lP) lP.style.opacity = dx > 20 ? Math.min((dx - 20) / 60, 1) : 0;
    if (lA) lA.style.opacity = dx < -20 ? Math.min((-dx - 20) / 60, 1) : 0;
    if (ti) {
        if (dx > 20)       { ti.style.background = `rgba(16,185,129,${prog * 0.15})`; ti.style.opacity = 1; }
        else if (dx < -20) { ti.style.background = `rgba(239,68,68,${prog * 0.15})`;  ti.style.opacity = 1; }
        else               { ti.style.opacity = 0; }
    }
    // Also tint the hint arrows
    const hL = document.querySelector('.swipe-hint-left');
    const hR = document.querySelector('.swipe-hint-right');
    if (hL) hL.style.opacity = dx < -20 ? Math.min(0.18 + (-dx-20)/120, 0.9) : 0.18;
    if (hR) hR.style.opacity = dx > 20  ? Math.min(0.18 + (dx-20)/120, 0.9)  : 0.18;
}

function _commitSwipe(card, dx) {
    _resetDragVisuals(card);
    if (Math.abs(dx) >= SWIPE_THRESHOLD) {
        if (dx > 0) swipeDecide('present');
        else        swipeDecide('absent');
    } else {
        // Spring back
        card.style.transition = 'transform .35s var(--spring)';
        card.style.transform  = 'translateX(0) rotate(0deg)';
    }
}

function _resetDragVisuals(card) {
    const lP = document.getElementById('swipeStampPresent');
    const lA = document.getElementById('swipeStampAbsent');
    const ti = document.getElementById('swipeCardWash');
    if (lP) lP.style.opacity = 0;
    if (lA) lA.style.opacity = 0;
    if (ti) ti.style.opacity = 0;
    const hL = document.querySelector('.swipe-hint-left');
    const hR = document.querySelector('.swipe-hint-right');
    if (hL) hL.style.opacity = 0.18;
    if (hR) hR.style.opacity = 0.18;
}

function swipeDecide(verdict) {
    const card = document.getElementById('swipeCard');
    if (!card || _swipeIdx >= _swipeList.length) return;

    const s  = _swipeList[_swipeIdx];
    const id = getStudentId(s);

    if (verdict === 'present') {
        // Apply to attendance state
        attendanceData[id] = 'present';
        const srv = originalAttendanceData[id] || 'pending';
        if ('present' !== srv) changedStudents.add(id); else changedStudents.delete(id);
        saveAttendanceDataForClass(currentClass); saveChangedStudentsToLocalStorage(currentClass);
        _swipePres++;
        card.style.transition = 'transform .32s ease, opacity .32s ease';
        card.style.transform  = 'translateX(120vw) rotate(22deg)';
        card.style.opacity    = '0';
        navigator.vibrate && navigator.vibrate(18);
    } else if (verdict === 'absent') {
        attendanceData[id] = 'absent';
        const srv = originalAttendanceData[id] || 'pending';
        if ('absent' !== srv) changedStudents.add(id); else changedStudents.delete(id);
        saveAttendanceDataForClass(currentClass); saveChangedStudentsToLocalStorage(currentClass);
        _swipeAbs++;
        card.style.transition = 'transform .32s ease, opacity .32s ease';
        card.style.transform  = 'translateX(-120vw) rotate(-22deg)';
        card.style.opacity    = '0';
        navigator.vibrate && navigator.vibrate(18);
    } else {
        // skip — fade up
        card.style.transition = 'transform .28s ease, opacity .28s ease';
        card.style.transform  = 'translateY(-50px) scale(.88)';
        card.style.opacity    = '0';
    }

    _swipeIdx++;
    _updateSwipeProgress();
    setTimeout(() => {
        _renderSwipeCard();
        // Also refresh the normal list in background
        renderAttendanceList(currentClass);
        updateClassStats();
        updateSaveBtns();
    }, 280);
}

function _updateSwipeProgress() {
    const total = _swipeList.length;
    const done  = _swipeIdx;
    const fill  = document.getElementById('swipeProgressFill');
    const ctr   = document.getElementById('swipeCounter');
    const presC = document.getElementById('swipePresCount');
    const absC  = document.getElementById('swipeAbsCount');
    if (fill)  fill.style.width  = total ? (done / total * 100) + '%' : '0%';
    if (ctr)   ctr.textContent   = `${Math.min(done + 1, total)} / ${total}`;
    if (presC) presC.innerHTML   = `<i class="fas fa-check-circle"></i> ${_swipePres}`;
    if (absC)  absC.innerHTML    = `<i class="fas fa-times-circle"></i> ${_swipeAbs}`;
}

function _showSwipeDone() {
    const area  = document.getElementById('swipeCardArea');
    const btns  = document.querySelector('.swipe-btns');
    const hints = document.querySelector('.swipe-hints');
    const skip  = _swipeList.length - _swipePres - _swipeAbs;
    if (area) area.innerHTML = `
        <div class="swipe-done">
            <div class="swipe-done-icon"><i class="fas fa-check"></i></div>
            <h2>انتهينا! 🎉</h2>
            <p>${window.t ? window.t('تم مراجعة جميع الأطفال') : 'تم مراجعة جميع الأطفال'} — اضغط زر الحفظ لرفع البيانات</p>
            <div class="swipe-done-stats">
                <div class="swipe-done-stat">
                    <span class="swipe-done-stat-val" style="color:var(--success)">${_swipePres}</span>
                    <span class="swipe-done-stat-lbl">حاضر</span>
                </div>
                <div class="swipe-done-stat">
                    <span class="swipe-done-stat-val" style="color:var(--danger)">${_swipeAbs}</span>
                    <span class="swipe-done-stat-lbl">غائب</span>
                </div>
                ${skip > 0 ? `<div class="swipe-done-stat">
                    <span class="swipe-done-stat-val" style="color:var(--text-3)">${skip}</span>
                    <span class="swipe-done-stat-lbl">تخطي</span>
                </div>` : ''}
            </div>
            <button class="btn" style="font-size:1rem;padding:12px 32px;border-radius:var(--r-full)" onclick="exitSwipeMode()">
                <i class="fas fa-arrow-right"></i> العودة للقائمة
            </button>
        </div>`;
    if (btns)  btns.style.display  = 'none';
    if (hints) hints.style.display = 'none';
}

// ══════════════════════════════════════════════════════════════
// PWA BUTTON — show on iOS automatically (no native prompt there)
// ══════════════════════════════════════════════════════════════
(function _initPwaBtn() {
    document.addEventListener('DOMContentLoaded', () => {
        const ua    = navigator.userAgent;
        const isIOS = /iPad|iPhone|iPod/.test(ua) && !window.MSStream;
        // On iOS Safari, never gets beforeinstallprompt, but we still show the button
        if (isIOS) {
            const btn = document.getElementById('pwaInstallBtn');
            // Only show if not already installed (standalone mode)
            if (btn && !window.navigator.standalone) btn.style.display = 'flex';
        }
        // On Android/desktop the beforeinstallprompt event handles it
    });
})();

// ── CLEAR ALL UNSAVED ─────────────────────────────────────────
function _clearAllUnsaved() {
    if (!confirm('مسح جميع التغييرات غير المحفوظة؟ لا يمكن التراجع.')) return;
    const cls = isCombinedView ? (combinedGroupLabel || currentClass) : currentClass;

    // Clear in-memory attendance changes
    changedStudents.clear();
    const list = isCombinedView ? combinedStudents : students.filter(s => s['الفصل'] === cls);
    list.forEach(s => {
        const id = getStudentId(s);
        attendanceData[id] = originalAttendanceData[id] || 'pending';
    });

    // Clear all localStorage keys for this class
    Object.keys(localStorage).forEach(k => {
        if (k.startsWith(`changedStudents_${cls}_`) || k.startsWith(`attendanceData_${cls}_`)) {
            localStorage.removeItem(k);
        }
    });

    // Clear coupon changes
    changedCouponStudents.clear();
    couponData = {};
    saveCouponDataForClass(cls);

    document.getElementById('unsavedModal').classList.remove('active');
    renderAttendanceList(currentClass);
    updateClassStats();
    updateSaveBtns();
    showToast('تم مسح جميع التغييرات', 'info');
}

// ══════════════════════════════════════════════════════════════
// EXPOSE NEW FUNCTIONS GLOBALLY
// ══════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', () => {
    // Instantly render from cache so the user sees content immediately (especially offline)
    const cached = localStorage.getItem('lastStudentsData');
    if (cached) {
        try {
            const d = JSON.parse(cached);
            students        = d.students    || d.allStudents || [];
            allStudentsData = d.allStudents || d.students    || students;
            if (d.classes && d.classes.length) classes = d.classes;
            displayClasses(); // also calls renderTodayBirthdayBanner()
            updateDashboardStats();
            updateCurrentDateDisplay();
        } catch(e) {}
    }
    initApp();
});

// Global exposure
Object.assign(window,{
    showClassView,showClassesView,showCombinedClassView,showAllTogetherView,markStudentAttendance,showStudentDetails,
    showSheetModal,showCustomExportModal,addCouponsToAll,resetCouponDataForClass,showAttendedModal,showAbsentModal,copyAttendedData,copyAbsentData,
    showImageModal,hideImageModal,showAddPersonModal,showBirthdayModal,showBirthdaysByMonth,
    showPastFridaysModal,loadFridayAttendance,performSearch,clearSearch,showRegistrationDetails,
    approveRegistration,rejectRegistration,toggleRegistrationSelection,searchPendingRegistrations,
    clearAllStudentsSearch,performAllStudentsSearch,showAnnouncementsModal,toggleAnnouncementStatus,
    deleteAnnouncement,adjustStudentCoupons,toggleCouponValue,closeAllDropdowns,logout,
    showAccountModal,hideAccountModal,showUncleHistory,showResetModal,retryConnection:()=>{},
    resetToCurrentFriday,showUnsavedModal,toggleTheme,escJs,
    // New
    shareAbsentToWhatsApp,shareAttendedToWhatsApp,saveAttendedAsCSV,triggerPwaInstall,doPwaInstall,closePwaModal,
    toggleCustomExportField,moveCustomExportField,renderCustomExportPreview,
    exportCustomAsCSV,exportCustomPreviewAsImage,exportCustomPreviewAsPdf,
    requestNotifPermission,_updateNotifBtnVisibility,
    _holdStart,_holdMove,_holdEnd,_holdCancel,_rowHoldStart,_rowHoldMove,_rowHoldEnd,_rowContextMenu,_ctxAction,_closeCtxMenu,
    _imgZoomChange,_imgZoomReset,_imgDownload,
    startSwipeMode,exitSwipeMode,swipeDecide,
    _removeUnsavedEntry,_removeUnsavedCoupon,_jumpToDate,
    _loadDataFromCache,_clearAllUnsaved,addCustomDate,removeCustomDate,toggleCustomDateSection,autoFormatCustomDate,
    _sendSyncCompletePush,_sendUnclMetaToSW,
    showAllTogetherView,
    renderTodayBirthdayBanner,getTodayBirthdays,_maybeSendBirthdayNotification,_sendBirthdayPush,_updateAttendanceRow
});

// ══════════════════════════════════════════════════════════════
// YOUTH VOCABULARY ENGINE — auto-swap all kids terms to youth
// ══════════════════════════════════════════════════════════════
(function _initYouthMode() {
    if (!window.IS_YOUTH) return;

    // ── 1. Apply accent colour override for youth (purple palette) ──
    const style = document.createElement('style');
    style.id = 'youth-theme';
    style.textContent = `
        :root {
            --brand:        #7c3aed !important;
            --brand-dark:   #5b21b6 !important;
            --brand-light:  #c4b5fd !important;
            --brand-bg:     #ede9fe !important;
            --brand-glow:   rgba(124,58,237,.18) !important;
        }
        /* Youth badge shown in class chips */
        .youth-badge {
            display:inline-flex;align-items:center;gap:4px;
            background:#ede9fe;color:#5b21b6;
            font-size:0.65rem;font-weight:700;
            padding:2px 7px;border-radius:20px;
        }
    `;
    document.head.appendChild(style);

    // ── 2. First full-page vocab pass on DOMContentLoaded ──
    document.addEventListener('DOMContentLoaded', () => {
        window.applyYouthVocab();
    });

    // ── 3. MutationObserver — re-runs swap whenever DOM updates ──
    //       Debounced to avoid thrashing during heavy renders.
    let _vocabTimer = null;
    const observer = new MutationObserver(() => {
        clearTimeout(_vocabTimer);
        _vocabTimer = setTimeout(() => window.applyYouthVocab(), 80);
    });
    document.addEventListener('DOMContentLoaded', () => {
        observer.observe(document.body, {
            childList: true,
            subtree:   true,
            characterData: false  // text-node changes handled by childList
        });
    });

    // ── 4. Patch showToast so runtime toasts are also translated ──
    const _origShowToast = window.showToast;
    if (typeof _origShowToast === 'function') {
        window.showToast = function(msg, type) {
            return _origShowToast(window.t ? window.t(msg) : msg, type);
        };
    }
})();

// ══════════════════════════════════════════════════════════
// UNIFIED NOTIFICATIONS (DB + push)
// ══════════════════════════════════════════════════════════
let _notifPanelOpen = false;
let _notifData = [];

async function loadUnifiedNotifications() {
    if (!navigator.onLine) return;
    try {
        const fd = new FormData();
        fd.append('action', 'getNotifications');
        const d = await fetch(API_URL, {method:'POST', body:fd, credentials:'include'}).then(r=>r.json());
        if (!d.success) return;
        _notifData = d.notifications || [];
        const unread = d.unread_count || 0;
        // Update bell badge
        const badge = document.getElementById('notifBellBadge');
        if (badge) {
            if (unread > 0) {
                badge.textContent = unread > 99 ? '99+' : unread;
                badge.style.display = 'flex';
            } else {
                badge.style.display = 'none';
            }
        }
        // Update task submission badge
        const taskSubCount = _notifData.filter(n => n.type === 'task_submission' && n.is_read == 0).length;
        const dot = document.getElementById('tasksSubmissionDot');
        const globalBadge = document.getElementById('globalTasksBadge');
        if (dot) { dot.style.display = taskSubCount > 0 ? 'flex' : 'none'; if(taskSubCount>0) dot.textContent = taskSubCount > 9 ? '9+' : taskSubCount; }
        if (globalBadge) { if(taskSubCount>0){globalBadge.style.display='flex';globalBadge.textContent=taskSubCount>9?'9+':taskSubCount;}else globalBadge.style.display='none'; }
        // Re-render if panel open
        if (_notifPanelOpen) renderNotifPanel();
    } catch(e) {}
}

function renderNotifPanel() {
    const el = document.getElementById('notifPanelList');
    if (!el) return;
    if (!_notifData.length) {
        el.innerHTML = `<div class="nps-empty">
            <div class="nps-empty-icon"><i class="fas fa-bell-slash"></i></div>
            <strong style="color:var(--text-2);font-size:.92rem;">لا توجد إشعارات</strong>
            <span>ستظهر هنا أحدث التنبيهات والتحديثات</span>
        </div>`;
        return;
    }
    const typeIcon = {
        registration:      'fa-user-plus',
        task_submission:   'fa-paper-plane',
        developer_message: 'fa-envelope',
        system:            'fa-circle-check',
        announcement:      'fa-bullhorn',
    };
    const typeLabel = {
        registration:      'تسجيل جديد',
        task_submission:   'مهمة',
        developer_message: 'رسالة',
        system:            'نظام',
        announcement:      'إعلان',
    };
    const typeAction = {
        registration:      'عرض الطلبات',
        task_submission:   'فتح المهام',
        developer_message: 'فتح الرسالة',
        announcement:      'عرض الإعلان',
    };
    const typeUrl = {
        registration: '/uncle/dashboard/?tab=pending',
        task_submission: currentClass
            ? `/uncle/dashboard/tasks?class=${encodeURIComponent(currentClass)}`
            : '/uncle/dashboard/tasks/',
        developer_message: '/uncle/church/dashboard/',
        announcement: '/uncle/dashboard/',
    };
    el.innerHTML = _notifData.map(n => {
        const icon      = typeIcon[n.type]  || 'fa-bell';
        const label     = typeLabel[n.type] || '';
        const action    = typeAction[n.type];
        const url       = typeUrl[n.type];
        const typeClass = 'type-' + (n.type || 'default');
        const t = new Date(n.created_at).toLocaleString('ar-EG', {day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'});
        return `<div class="nps-item ${n.is_read == 0 ? 'unread' : ''}" onclick="onUnifNotifClick(${n.id}, '${n.type}', '${url || ''}')">
            <div style="display:flex;align-items:flex-start;gap:12px;">
                <div class="nps-icon ${typeClass}"><i class="fas ${icon}"></i></div>
                <div style="flex:1;min-width:0;">
                    ${label ? `<div style="font-size:.64rem;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--text-3);margin-bottom:3px;">${label}</div>` : ''}
                    <div class="nps-item-title">${escStr(n.title)}</div>
                    ${n.body ? `<div class="nps-item-body">${escStr(n.body)}</div>` : ''}
                    <div class="nps-item-footer">
                        <span class="nps-item-time"><i class="fas fa-clock"></i> ${t}</span>
                        ${action ? `<span class="nps-item-action"><i class="fas fa-arrow-left"></i> ${action}</span>` : ''}
                    </div>
                </div>
                ${n.is_read == 0 ? '<div class="nps-unread-dot" style="margin-top:4px;flex-shrink:0;"></div>' : ''}
                <button onclick="deleteUnifNotif(event,${n.id})" class="nps-delete-btn" title="حذف"><i class="fas fa-trash" style="font-size:.72rem;"></i></button>
            </div>
        </div>`;
    }).join('');
}

async function onUnifNotifClick(id, type, url) {
    // Mark read
    const fd = new FormData(); fd.append('action','markNotificationRead'); fd.append('id', id);
    fetch(API_URL, {method:'POST', body:fd, credentials:'include'});
    const notif = _notifData.find(n => n.id === id);
    if (notif) notif.is_read = 1;
    loadUnifiedNotifications();
    // Navigate
    toggleNotifPanel();
    if (url) setTimeout(() => window.location.href = url, 200);
}

async function deleteUnifNotif(e, id) {
    e.stopPropagation();
    const fd = new FormData(); fd.append('action','deleteNotification'); fd.append('id', id);
    await fetch(API_URL, {method:'POST', body:fd, credentials:'include'});
    _notifData = _notifData.filter(n => n.id !== id);
    renderNotifPanel();
    loadUnifiedNotifications();
}

async function markAllNotifsRead() {
    const fd = new FormData(); fd.append('action','markAllNotificationsRead');
    await fetch(API_URL, {method:'POST', body:fd, credentials:'include'});
    _notifData.forEach(n => n.is_read = 1);
    renderNotifPanel();
    loadUnifiedNotifications();
}

function toggleNotifPanel() {
    _notifPanelOpen = !_notifPanelOpen;
    const overlay = document.getElementById('notifPanelOverlay');
    if (overlay) {
        overlay.classList.toggle('open', _notifPanelOpen);
        document.body.style.overflow = _notifPanelOpen ? 'hidden' : '';
    }
    if (_notifPanelOpen) { loadUnifiedNotifications(); renderNotifPanel(); }
}

function escStr(s) { return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;'); }

// Load on boot and every 60s
window.addEventListener('load', () => {
    setTimeout(loadUnifiedNotifications, 2000);
    setInterval(loadUnifiedNotifications, 60000);
});

// Push → DB notification bridge: when SW sends NEW_REGISTRATION push,
// also create a DB notification so it shows in the panel
navigator.serviceWorker?.addEventListener('message', e => {
    if (!e.data) return;
    if (e.data.type === 'NEW_REGISTRATION') {
        loadUnifiedNotifications();
    }
    if (e.data.type === 'NOTIFICATION_CLICK') {
        loadUnifiedNotifications();
    }
});

</script>
</body>
</html>
