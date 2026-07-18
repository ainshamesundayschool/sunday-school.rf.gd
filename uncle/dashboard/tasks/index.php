<?php
// Prevent caching of tasks dashboard to see instant updates
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT"); // Date in the past



// ══════════════════════════════════════════════════════════════════



// tasks.php  —  Uncle Dashboard → Tasks & Quizzes



// URL pattern:  /uncle/tasks/?class=الفصل+الأول



// Links back to dashboard class view automatically.



// ══════════════════════════════════════════════════════════════════



ini_set('session.gc_probability', 1);



ini_set('session.gc_divisor', 100);



ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 365 * 10);







// Robust local session directory to prevent aggressive shared hosting garbage collection



$rootPath = dirname(__FILE__);



while ($rootPath && !file_exists($rootPath . '/api.php')) {



    $parent = dirname($rootPath);



    if ($parent === $rootPath) break;



    $rootPath = $parent;



}



$sessionPath = $rootPath . '/.sessions';



if (!is_dir($sessionPath)) {



    @mkdir($sessionPath, 0755, true);



}



if (is_writable($sessionPath)) {



    session_save_path($sessionPath);



}







ini_set('session.gc_maxlifetime', 315360000);



ini_set('session.cookie_lifetime', 315360000);



session_start();







$hasUncle  = isset($_SESSION['uncle_id']);



$hasChurch = isset($_SESSION['church_id']);







if (!$hasUncle && !$hasChurch) {



    ?><!DOCTYPE html><html><head><meta charset="UTF-8"></head><body><script>



    (function(){



        var ul=localStorage.getItem('uncleLoggedIn')==='true';



        var cl=localStorage.getItem('loggedIn')==='true';



        var un=localStorage.getItem('uncleUsername');



        var cc=localStorage.getItem('churchCode');



        if(!ul&&!cl){window.location.href='/login/';return;}



        var fd=new FormData();



        fd.append('action','restore_session');



        if(ul&&un)fd.append('username',un);



        else if(cl&&cc)fd.append('church_code',cc);



        else{window.location.href='/login/';return;}



        fetch('../../api.php',{method:'POST',body:fd,credentials:'include'})



            .then(r=>r.json()).then(d=>{



                if(d.success)window.location.reload();



                else window.location.href='/login/';



            }).catch(()=>window.location.href='/login/');



    })();



</script></body></html><?php



    exit;



}







$uncleName  = $_SESSION['uncle_name']  ?? '';



$uncleId    = (int)($_SESSION['uncle_id'] ?? 0);



$uncleRole  = $_SESSION['uncle_role']  ?? '';



$churchName = $_SESSION['church_name'] ?? 'الكنيسة';



$churchType = $_SESSION['church_type'] ?? 'kids';



$isYouth    = ($churchType === 'youth');







// ?class= routing



$activeClass = trim(urldecode($_GET['class'] ?? ''));



$dashBack    = '/uncle/dashboard/' . ($activeClass ? '?class='.urlencode($activeClass) : '');



?>



<!DOCTYPE html>



<html lang="ar" dir="rtl" data-theme="light">



<head>



<meta charset="UTF-8">



<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <!-- ═══ Social Preview Defaults ═══ -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Sunday School">
    <meta property="og:title" content="التاسكات">
    <meta property="og:description" content="منصة متكاملة لإدارة مدارس الأحد — الحضور، الكوبونات، الرحلات / المؤتمرات والمزيد">
    <meta property="og:url" content="https://sunday-school.online/uncle/dashboard/tasks/">
    <meta property="og:image" content="https://sunday-school.online/imgs/Sunday-School-Og.png">
    <meta property="og:image:width" content="1000">
    <meta property="og:image:height" content="1000">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:alt" content="Sunday School">
    <meta property="og:locale" content="ar_AR">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="التاسكات">
    <meta name="twitter:description" content="منصة متكاملة لإدارة مدارس الأحد — الحضور، الكوبونات، الرحلات / المؤتمرات والمزيد">
    <meta name="twitter:image" content="https://sunday-school.online/imgs/Sunday%20School%20App.png">




<title>التاسكات — <?php echo htmlspecialchars($activeClass ?: $churchName); ?></title>



<meta name="theme-color" content="#f8fafc">



<link rel="preconnect" href="https://fonts.googleapis.com">



<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">



<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">



<link rel="icon" href="/favicon.ico">



<style>



:root {



  /* Brand - Modern Indigo-leaning Blue to match dashboard's #5b6cf5 */



  --brand: #5b6cf5;



  --brand-d: #4354e8;



  --brand-l: #a5b0ff;



  --brand-bg: #eef0ff;



  --brand-glow: rgba(91, 108, 245, .18);







  /* Semantic Colors */



  --ok: #10b981;



  --ok-bg: #d1fae5;



  --err: #ef4444;



  --err-bg: #fee2e2;



  --warn: #f59e0b;



  --warn-bg: #fef3c7;



  --info: #2563eb;



  --info-bg: #dbeafe;



  --cou: #8b5cf6;



  --cou-bg: #ede9fe;



  --coupon-grad: linear-gradient(135deg, #8b5cf6, #7c3aed);







  /* Surfaces & Text (Light Theme) */



  --t1: #1a1d2e;



  --t2: #4b5068;



  --t3: #8b90a8;



  --t4: #cdd1e2;



  --bg: #ffffff;



  --bg2: #f3f4f9;        /* Matches dashboard background */



  --bg3: #f7f8fc;        /* Matches dashboard surface-2 */



  --bdr: rgba(91, 108, 245, .12);



  --bdr2: #e4e6f0;







  /* Premium Smoother Border Radii */



  --r-sm: 10px;



  --r-md: 14px;



  --r-lg: 18px;



  --r-xl: 24px;



  --r-full: 9999px;







  /* Advanced Soft Shadows */



  --sh-sm: 0 2px 8px -2px rgba(0, 0, 0, .07);



  --sh-md: 0 8px 24px -4px rgba(0, 0, 0, .10);



  --sh-lg: 0 20px 48px -8px rgba(0, 0, 0, .14);



  --sh-brand: 0 4px 14px rgba(91, 108, 245, .25);







  /* Motion & Timings */



  --ease: cubic-bezier(.4, 0, .2, 1);



  --fast: .15s var(--ease);



  --norm: .22s var(--ease);



  --slow: .35s var(--ease);



}







[data-theme="dark"] {



  --bg: #181b26;



  --bg2: #0f1117;



  --bg3: #1e2132;



  --bdr: rgba(91, 108, 245, .18);



  --bdr2: #2a2d42;



  --t1: #e8eaf6;



  --t2: #9299be;



  --t3: #565c7a;



  --t4: #333852;



  --brand-bg: rgba(91, 108, 245, .15);



  --ok-bg: rgba(16, 185, 129, .15);



  --err-bg: rgba(239, 68, 68, .15);



  --warn-bg: rgba(245, 158, 11, .15);



  --cou-bg: rgba(139, 92, 246, .15);



}







*, *::before, *::after {



  margin: 0;



  padding: 0;



  box-sizing: border-box;



}







html {



  scroll-behavior: smooth;



}







/* Custom Scrollbars */



::-webkit-scrollbar {



  width: 6px;



  height: 6px;



}



::-webkit-scrollbar-track {



  background: transparent;



}



::-webkit-scrollbar-thumb {



  background: var(--bdr2);



  border-radius: var(--r-full);



}



::-webkit-scrollbar-thumb:hover {



  background: var(--t3);



}







body {



  font-family: 'Cairo', 'Segoe UI', Tahoma, sans-serif;



  background: var(--bg2);



  color: var(--t1);



  min-height: 100vh;



  overflow-x: hidden;



  position: relative;



  transition: background var(--norm), color var(--norm);



}







/* Ambient Mesh Background */



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







[data-theme="dark"] body::before {



  background:



    radial-gradient(ellipse 80% 50% at 10% -10%, rgba(91, 108, 245, .12) 0%, transparent 60%),



    radial-gradient(ellipse 60% 40% at 90% 110%, rgba(139, 92, 246, .08) 0%, transparent 60%);



}







button, input, select, textarea, a {



  font-family: inherit;



}







/* ── Topbar (Glassmorphic & Sleek) ── */



.topbar {



  position: sticky;



  top: 0;



  z-index: 300;



  display: flex;



  align-items: center;



  gap: 12px;



  padding: 0 20px;



  height: 58px;



  background: var(--bg);



  border-bottom: 1px solid var(--bdr);



  box-shadow: var(--sh-sm);



  transition: background var(--fast), border-color var(--fast);



}







[data-theme="light"] .topbar {



  background: rgba(255, 255, 255, 0.88);



  backdrop-filter: blur(20px);



  -webkit-backdrop-filter: blur(20px);



}







[data-theme="dark"] .topbar {



  background: rgba(24, 27, 38, 0.88);



  backdrop-filter: blur(20px);



  -webkit-backdrop-filter: blur(20px);



}







.tb-back {



  display: flex;



  align-items: center;



  gap: 6px;



  padding: 6px 14px;



  border-radius: var(--r-md);



  background: var(--bg3);



  border: 1px solid var(--bdr);



  color: var(--t2);



  font-size: .82rem;



  font-weight: 700;



  text-decoration: none;



  transition: all var(--fast);



  white-space: nowrap;



  min-height: 38px;



}







.tb-back:hover {



  background: var(--brand-bg);



  color: var(--brand);



  border-color: var(--brand);



  transform: translateY(-1px);



}







.tb-title {



  flex: 1;



  display: flex;



  align-items: center;



  gap: 10px;



  font-weight: 800;



  font-size: 1rem;



  color: var(--t1);



  min-width: 0;



}







.tb-icon {



  width: 32px;



  height: 32px;



  border-radius: var(--r-sm);



  background: var(--brand);



  display: flex;



  align-items: center;



  justify-content: center;



  color: #fff;



  font-size: .85rem;



  flex-shrink: 0;



  box-shadow: var(--sh-brand);



}







.tb-cls {



  padding: 4px 12px;



  border-radius: var(--r-full);



  background: var(--brand-bg);



  border: 1px solid var(--brand-l);



  font-size: .72rem;



  font-weight: 700;



  color: var(--brand);



  white-space: nowrap;



}







.btn-create {



  display: inline-flex;



  align-items: center;



  gap: 6px;



  padding: 8px 18px;



  border-radius: var(--r-md);



  background: var(--brand);



  color: #fff;



  font-weight: 700;



  font-size: .83rem;



  border: none;



  cursor: pointer;



  box-shadow: var(--sh-brand);



  transition: all var(--fast);



  white-space: nowrap;



  min-height: 38px;



}







.btn-create:hover {



  background: var(--brand-d);



  transform: translateY(-1px);



  box-shadow: 0 6px 20px var(--brand-glow);



}







/* ── Page Wrapper ── */



.page {



  max-width: 1140px;



  margin: 0 auto;



  padding: 24px 16px calc(70px + env(safe-area-inset-bottom));



  position: relative;



  z-index: 1;



}







/* ── Stats Dashboard ── */



.stats {



  display: grid;



  grid-template-columns: repeat(4, 1fr);



  gap: 12px;



  margin-bottom: 20px;



}







.scard {



  background: var(--bg);



  border: 1px solid var(--bdr);



  border-radius: var(--r-lg);



  padding: 16px 18px;



  display: flex;



  align-items: center;



  gap: 14px;



  box-shadow: var(--sh-sm);



  transition: all var(--norm);



  position: relative;



  overflow: hidden;



}







.scard:hover {



  box-shadow: var(--sh-md);



  transform: translateY(-2px);



  border-color: var(--brand-l);



}







.scard-ico {



  width: 40px;



  height: 40px;



  border-radius: var(--r-md);



  display: flex;



  align-items: center;



  justify-content: center;



  font-size: .95rem;



  flex-shrink: 0;



}







.scard-val {



  font-size: 1.5rem;



  font-weight: 900;



  line-height: 1;



  color: var(--t1);



}







.scard-lbl {



  font-size: .72rem;



  color: var(--t3);



  margin-top: 2px;



  font-weight: 600;



}







/* ── Section Header & Filter Tabs ── */



.sec-hdr {



  display: flex;



  align-items: center;



  justify-content: space-between;



  gap: 12px;



  margin-bottom: 16px;



  flex-wrap: wrap;



}







.sec-title {



  font-weight: 800;



  font-size: .95rem;



  color: var(--t1);



  display: flex;



  align-items: center;



  gap: 8px;



}







.sec-dot {



  width: 6px;



  height: 6px;



  border-radius: 50%;



  background: var(--brand);



  box-shadow: 0 0 8px var(--brand);



}







.ftabs {



  display: flex;



  gap: 6px;



  flex-wrap: wrap;



}







.ftab {



  padding: 6px 14px;



  border-radius: var(--r-full);



  font-size: .76rem;



  font-weight: 700;



  cursor: pointer;



  border: 1px solid var(--bdr);



  background: var(--bg);



  color: var(--t3);



  transition: all var(--fast);



}







.ftab:hover, .ftab.active {



  background: var(--brand-bg);



  color: var(--brand);



  border-color: var(--brand);



}







/* ── Hero / Welcome Section ── */



.hero {



  display: grid;



  grid-template-columns: minmax(0, 1.5fr) minmax(260px, .85fr);



  gap: 16px;



  margin-bottom: 20px;



}







.hero-card {



  background: var(--bg);



  border: 1px solid var(--bdr);



  border-radius: var(--r-xl);



  padding: 24px;



  box-shadow: var(--sh-sm);



  position: relative;



  overflow: hidden;



}







.hero-card::before {



  content: '';



  position: absolute;



  top: -60px;



  left: -60px;



  width: 200px;



  height: 200px;



  border-radius: 50%;



  background: radial-gradient(circle, var(--brand-glow), transparent 70%);



  pointer-events: none;



}







.hero-main, .hero-side {



  position: relative;



  z-index: 1;



}







.hero-badge {



  display: inline-flex;



  align-items: center;



  gap: 6px;



  padding: 5px 12px;



  border-radius: var(--r-full);



  background: var(--brand-bg);



  border: 1px solid var(--brand-l);



  font-size: .74rem;



  font-weight: 700;



  color: var(--brand);



  margin-bottom: 14px;



}







.hero-title {



  font-size: 1.6rem;



  font-weight: 900;



  color: var(--t1);



  margin-bottom: 8px;



  line-height: 1.3;



}







.hero-sub {



  font-size: .88rem;



  line-height: 1.85;



  color: var(--t2);



  max-width: 580px;



}







.hero-actions {



  display: flex;



  flex-wrap: wrap;



  gap: 9px;



  margin-top: 18px;



}







.hero-link {



  display: inline-flex;



  align-items: center;



  gap: 6px;



  padding: 8px 16px;



  border-radius: var(--r-md);



  border: 1px solid var(--bdr);



  background: var(--bg);



  color: var(--t2);



  text-decoration: none;



  font-size: .79rem;



  font-weight: 700;



  transition: all var(--fast);



}







.hero-link:hover {



  border-color: var(--brand);



  color: var(--brand);



  background: var(--brand-bg);



  transform: translateY(-1px);



}







.hero-side {



  display: grid;



  grid-template-columns: 1fr 1fr;



  gap: 10px;



}







.hero-mini {



  background: var(--bg);



  border: 1px solid var(--bdr);



  border-radius: var(--r-lg);



  padding: 14px 16px;



  box-shadow: var(--sh-sm);



  display: flex;



  flex-direction: column;



  justify-content: center;



}







.hero-mini-label {



  font-size: .71rem;



  color: var(--t3);



  margin-bottom: 6px;



  font-weight: 700;



}







.hero-mini-value {



  font-size: 1.4rem;



  font-weight: 900;



  color: var(--t1);



  line-height: 1;



}







.hero-mini-note {



  font-size: .7rem;



  color: var(--t3);



  margin-top: 5px;



}







.list-shell {



  background: var(--bg);



  border: 1px solid var(--bdr);



  border-radius: var(--r-xl);



  padding: 20px;



  box-shadow: var(--sh-sm);



}







/* ── Tasks Grid & Cards ── */



.tgrid {



  display: grid;



  grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));



  gap: 16px;



}







@keyframes cardIn {



  from { opacity: 0; transform: translateY(12px); }



  to { opacity: 1; transform: translateY(0); }



}







.tcard {



  background: var(--bg);



  border: 1px solid var(--bdr);



  border-radius: var(--r-lg);



  overflow: hidden;



  box-shadow: var(--sh-sm);



  transition: all var(--norm);



  cursor: pointer;



  animation: cardIn .35s var(--ease) both;



  position: relative;



}







.tcard:hover {



  box-shadow: var(--sh-md);



  border-color: var(--brand);



  transform: translateY(-3px);



}







.tcard-acc {



  height: 4px;



  background: var(--brand);



}



.tcard-acc.ok { background: var(--ok); }



.tcard-acc.warn { background: var(--warn); }



.tcard-acc.err { background: var(--t4); }







.tcard-body {



  padding: 18px 18px 14px;



}







.tcard-top {



  display: flex;



  align-items: flex-start;



  justify-content: space-between;



  gap: 10px;



  margin-bottom: 12px;



}







.tcard-title {



  font-weight: 800;



  font-size: .97rem;



  color: var(--t1);



  line-height: 1.5;



  flex: 1;



  min-width: 0;



  word-break: break-word;



}







.tstatus {



  display: inline-flex;



  align-items: center;



  gap: 4px;



  padding: 4px 10px;



  border-radius: var(--r-full);



  font-size: .68rem;



  font-weight: 800;



  white-space: nowrap;



  flex-shrink: 0;



}



.s-active { background: var(--ok-bg); color: var(--ok); }



.s-upcoming { background: var(--info-bg); color: var(--info); }



.s-ended { background: var(--bg3); color: var(--t3); border: 1px solid var(--bdr); }



.s-draft { background: var(--warn-bg); color: var(--warn); }







.tclass-inline {



  display: inline-flex;



  align-items: center;



  gap: 5px;



  padding: 4px 10px;



  border-radius: var(--r-full);



  background: var(--brand-bg);



  border: 1px solid var(--brand-l);



  font-size: .7rem;



  font-weight: 700;



  color: var(--brand);



  margin-bottom: 11px;



}







.tmeta {



  display: grid;



  grid-template-columns: 1fr 1fr;



  gap: 8px;



  margin-bottom: 11px;



}







.tmeta-i {



  display: flex;



  align-items: center;



  gap: 6px;



  font-size: .71rem;



  color: var(--t2);



  background: var(--bg3);



  border: 1px solid var(--bdr);



  border-radius: var(--r-md);



  padding: 8px 10px;



  min-height: 38px;



}



.tmeta-i i {



  color: var(--brand);



  font-size: .75rem;



  flex-shrink: 0;



}







.tinfo-grid {



  display: grid;



  grid-template-columns: 1fr 1fr;



  gap: 8px;



  margin-bottom: 11px;



}







.tinfo-pill {



  display: flex;



  align-items: center;



  gap: 8px;



  background: var(--bg3);



  border: 1px solid var(--bdr);



  border-radius: var(--r-md);



  padding: 9px 11px;



}



.tinfo-pill i {



  font-size: .78rem;



  color: var(--brand);



}







.tip-val {



  font-size: .9rem;



  font-weight: 800;



  color: var(--t1);



}



.tip-lbl {



  font-size: .67rem;



  color: var(--t3);



  font-weight: 600;



}







.tprogress {



  padding: 10px 12px;



  border-radius: var(--r-md);



  background: var(--brand-bg);



  border: 1px solid rgba(165,180,252,.35);



}







.prog-bar {



  height: 5px;



  background: var(--bdr2);



  border-radius: var(--r-full);



  overflow: hidden;



  margin-bottom: 6px;



}







.prog-fill {



  height: 100%;



  border-radius: var(--r-full);



  background: var(--brand);



  transition: width .6s var(--ease);



}







.prog-lbl {



  display: flex;



  justify-content: space-between;



  font-size: .68rem;



  color: var(--t2);



  font-weight: 700;



}







.tcard-foot {



  padding: 12px 18px;



  border-top: 1px solid var(--bdr);



  display: flex;



  align-items: center;



  justify-content: space-between;



  gap: 8px;



  background: var(--bg3);



}







.tclass-badge {



  display: inline-flex;



  align-items: center;



  gap: 5px;



  padding: 5px 12px;



  border-radius: var(--r-full);



  font-size: .7rem;



  font-weight: 700;



  background: var(--bg);



  color: var(--t2);



  border: 1px solid var(--bdr);



  min-width: 0;



  overflow: hidden;



  text-overflow: ellipsis;



  white-space: nowrap;



  max-width: 140px;



}







.tact {



  display: flex;



  gap: 6px;



}







.tbtn {



  height: 36px;



  padding: 0 14px;



  border-radius: var(--r-md);



  border: 1px solid var(--bdr);



  background: var(--bg);



  display: flex;



  align-items: center;



  justify-content: center;



  cursor: pointer;



  color: var(--t2);



  font-size: .75rem;



  transition: all var(--fast);



  gap: 6px;



  font-weight: 700;



  white-space: nowrap;



}







.tbtn:hover {



  background: var(--brand-bg);



  color: var(--brand);



  border-color: var(--brand);



  transform: translateY(-1px);



}



.tbtn.d:hover {



  background: var(--err-bg);



  color: var(--err);



  border-color: var(--err);



}



.tbtn.view-btn {



  background: var(--brand-bg);



  color: var(--brand);



  border-color: var(--brand-l);



}



.tbtn.view-btn:hover {



  background: var(--brand);



  color: #fff;



  border-color: var(--brand);



}







.tbtn-lbl {



  font-size: .74rem;



  font-weight: 700;



}







.empty {



  grid-column: 1/-1;



  text-align: center;



  padding: 60px 20px;



}







.empty-ico {



  width: 70px;



  height: 70px;



  border-radius: 50%;



  background: var(--brand-bg);



  display: flex;



  align-items: center;



  justify-content: center;



  margin: 0 auto 16px;



  font-size: 1.8rem;



  color: var(--brand);



  box-shadow: 0 8px 20px var(--brand-glow);



}







.empty-t {



  font-weight: 800;



  font-size: .97rem;



  color: var(--t1);



  margin-bottom: 5px;



}







.empty-s {



  font-size: .82rem;



  color: var(--t3);



  margin-bottom: 18px;



}







/* ── Overlay / Glass Modal ── */



.overlay {



  position: fixed;



  inset: 0;



  background: rgba(15, 17, 23, 0.45);



  backdrop-filter: blur(8px);



  -webkit-backdrop-filter: blur(8px);



  z-index: 500;



  display: flex;



  align-items: flex-start;



  justify-content: center;



  padding: 24px;



  overflow-y: auto;



  opacity: 0;



  visibility: hidden;



  transition: all var(--norm);



}



.overlay.fullscreen {



  padding: 0;



  align-items: stretch;



}



.overlay.fullscreen .modal {



  max-width: 100%;



  width: 100%;



  border-radius: 0;



  margin: 0;



  height: 100vh;
  height: 100dvh;
  max-height: 100vh;
  max-height: 100dvh;



  display: flex;



  flex-direction: column;



  transform: none !important;



}

.overlay.fullscreen .modal.wide {
  min-height: auto;
  max-height: 92vh;
  height: auto;
  border-radius: var(--r-xl);
  margin: auto;
  max-width: 860px;
  width: 90%;
  box-shadow: var(--sh-lg);
}



.overlay.fullscreen .mbody {



  flex: 1;



  overflow-y: auto;



}



.overlay.open {



  opacity: 1;



  visibility: visible;



}







.modal {



  background: var(--bg);



  border-radius: var(--r-xl);



  width: 100%;



  max-width: 740px;



  margin: auto;



  box-shadow: var(--sh-lg);



  transform: translateY(20px) scale(.97);



  transition: all var(--slow);



  border: 1px solid var(--bdr);



}



.overlay.open .modal {



  transform: translateY(0) scale(1);



}



.modal.wide { max-width: 860px; }



.modal.narrow { max-width: 400px; }







.mhdr {



  display: flex;



  align-items: center;



  gap: 12px;



  padding: 18px 24px;



  border-bottom: 1px solid var(--bdr);



  background: var(--bg3);



}







.mhdr-ico {



  width: 36px;



  height: 36px;



  border-radius: var(--r-md);



  background: var(--brand);



  display: flex;



  align-items: center;



  justify-content: center;



  color: #fff;



  font-size: .9rem;



  flex-shrink: 0;



  box-shadow: var(--sh-brand);



}







.mhdr-title {



  font-weight: 800;



  font-size: 1rem;



  color: var(--t1);



}



.mhdr-sub {



  font-size: .72rem;



  color: var(--t3);



  margin-top: 2px;



  font-weight: 600;



}







.mclose {



  width: 34px;



  height: 34px;



  border-radius: var(--r-md);



  border: 1px solid var(--bdr);



  background: var(--bg);



  display: flex;



  align-items: center;



  justify-content: center;



  cursor: pointer;



  color: var(--t3);



  font-size: .82rem;



  transition: all var(--fast);



  margin-right: auto;



  flex-shrink: 0;



}



.mclose:hover {



  background: var(--err-bg);



  color: var(--err);



  border-color: var(--err);



  transform: scale(1.05);



}







.mbody {



  padding: 22px 24px;



}







.mfoot {



  padding: 14px 24px;



  border-top: 1px solid var(--bdr);



  display: flex;



  align-items: center;



  justify-content: flex-end;



  gap: 8px;



  flex-wrap: wrap;



  background: var(--bg3);



}







/* ── Wizard Steps (Interactive Progression) ── */



.steps {



  display: flex;



  align-items: center;



  margin-bottom: 24px;



}







.step {



  flex: 1;



  display: flex;



  flex-direction: column;



  align-items: center;



  gap: 6px;



  position: relative;



}







.step:not(:last-child)::after {



  content: '';



  position: absolute;



  top: 12px;



  left: calc(-50% + 12px);



  right: calc(50% + 12px);



  height: 2px;



  background: var(--bdr);



  z-index: 0;



  transition: background var(--norm);



}



.step.done:not(:last-child)::after {



  background: var(--brand);



}







.step-c {



  width: 24px;



  height: 24px;



  border-radius: 50%;



  border: 2px solid var(--bdr);



  background: var(--bg);



  display: flex;



  align-items: center;



  justify-content: center;



  font-size: .68rem;



  font-weight: 800;



  color: var(--t3);



  z-index: 1;



  transition: all var(--norm);



}



.step.active .step-c {



  border-color: var(--brand);



  background: var(--brand);



  color: #fff;



  box-shadow: 0 0 10px var(--brand-glow);



}



.step.done .step-c {



  border-color: var(--brand);



  background: var(--brand);



  color: #fff;



}







.step-l {



  font-size: .72rem;



  color: var(--t3);



  font-weight: 700;



  text-align: center;



  transition: color var(--norm);



}



.step.active .step-l, .step.done .step-l {



  color: var(--brand);



}







/* ── Clean & Sleek Forms ── */



.fsec {



  margin-bottom: 20px;



}







.fsec-title {



  font-weight: 800;



  font-size: .78rem;



  color: var(--brand);



  letter-spacing: .05em;



  text-transform: uppercase;



  margin-bottom: 12px;



  display: flex;



  align-items: center;



  gap: 8px;



}







.fsec-title::after {



  content: '';



  flex: 1;



  height: 1px;



  background: linear-gradient(90deg, var(--brand), transparent);



}







.frow {



  display: grid;



  grid-template-columns: 1fr 1fr;



  gap: 12px;



  margin-bottom: 12px;



}



.frow.full {



  grid-template-columns: 1fr;



}







.fg {



  display: flex;



  flex-direction: column;



  gap: 5px;



}







.flbl {



  font-size: .76rem;



  font-weight: 700;



  color: var(--t2);



  display: flex;



  align-items: center;



  gap: 4px;



}



.flbl .req { color: var(--err); }



.flbl .tip { color: var(--t3); font-weight: 500; font-size: .68rem; }







.fi, .fs, .fta {



  padding: 9px 13px;



  border: 1.5px solid var(--bdr);



  border-radius: var(--r-md);



  font-family: inherit;



  font-size: .85rem;



  color: var(--t1);



  background: var(--bg);



  outline: none;



  transition: all var(--fast);



  width: 100%;



}



.fi:focus, .fs:focus, .fta:focus {



  border-color: var(--brand);



  box-shadow: 0 0 0 3px var(--brand-glow);



  background: var(--bg);



}



.fta {



  resize: vertical;



  min-height: 70px;



}







.tgl-row {



  display: flex;



  align-items: center;



  justify-content: space-between;



  padding: 12px 14px;



  background: var(--bg3);



  border: 1px solid var(--bdr);



  border-radius: var(--r-md);



  margin-bottom: 8px;



}







.tgl-lbl {



  font-size: .82rem;



  font-weight: 700;



  color: var(--t1);



}



.tgl-desc {



  font-size: .68rem;



  color: var(--t3);



  margin-top: 2px;



  font-weight: 500;



}







.tgl {



  position: relative;



  width: 40px;



  height: 22px;



  flex-shrink: 0;



}



.tgl input {



  opacity: 0;



  width: 0;



  height: 0;



  position: absolute;



}



.tgl-s {



  position: absolute;



  inset: 0;



  border-radius: var(--r-full);



  background: var(--t4);



  cursor: pointer;



  transition: all var(--norm);



}



.tgl-s::after {



  content: '';



  position: absolute;



  width: 16px;



  height: 16px;



  border-radius: 50%;



  background: #fff;



  top: 3px;



  right: 3px;



  transition: all var(--norm);



  box-shadow: 0 1px 3px rgba(0,0,0,.15);



}



.tgl input:checked + .tgl-s {



  background: var(--brand);



}



.tgl input:checked + .tgl-s::after {



  transform: translateX(-18px);



}







/* ── Interactive Questions Workflow ── */



.qlist {



  display: flex;



  flex-direction: column;



  gap: 12px;



  margin-bottom: 12px;



}







.qcard {



  background: var(--bg);



  border: 1.5px solid var(--bdr);



  border-radius: var(--r-lg);



  overflow: hidden;



  transition: all var(--fast);



}



.qcard:focus-within {



  border-color: var(--brand);



  box-shadow: 0 0 0 3px var(--brand-glow);



}







.qhdr {



  display: flex;



  align-items: center;



  gap: 10px;



  padding: 12px 14px;



  border-bottom: 1px solid var(--bdr);



  background: var(--bg3);



}







.qnum {



  width: 24px;



  height: 24px;



  border-radius: var(--r-sm);



  background: var(--brand);



  display: flex;



  align-items: center;



  justify-content: center;



  font-size: .68rem;



  font-weight: 800;



  color: #fff;



  flex-shrink: 0;



  box-shadow: var(--sh-brand);



}







.qi {



  flex: 1;



  border: none;



  background: transparent;



  outline: none;



  font-family: inherit;



  font-size: .85rem;



  font-weight: 700;



  color: var(--t1);



}



.qi::placeholder {



  color: var(--t3);



}







.qdeg {



  display: flex;



  align-items: center;



  gap: 5px;



  background: var(--brand-bg);



  border: 1px solid var(--brand-l);



  border-radius: var(--r-sm);



  padding: 4px 10px;



}



.qdeg-l {



  font-size: .65rem;



  color: var(--brand);



  font-weight: 800;



  white-space: nowrap;



}



.qdeg-i {



  width: 32px;



  border: none;



  background: transparent;



  outline: none;



  font-family: inherit;



  font-size: .78rem;



  font-weight: 800;



  color: var(--brand);



  text-align: center;



}







.qrm {



  width: 24px;



  height: 24px;



  border-radius: var(--r-sm);



  border: 1px solid var(--bdr);



  background: var(--bg);



  display: flex;



  align-items: center;



  justify-content: center;



  cursor: pointer;



  color: var(--t3);



  font-size: .68rem;



  transition: all var(--fast);



}



.qrm:hover {



  background: var(--err-bg);



  color: var(--err);



  border-color: var(--err);



}







.qbody {



  padding: 14px;



}







.opts {



  display: flex;



  flex-direction: column;



  gap: 8px;



  margin-bottom: 10px;



}







.orow {



  display: flex;



  align-items: center;



  gap: 8px;



}







.oradio {



  width: 18px;



  height: 18px;



  border-radius: 50%;



  border: 2px solid var(--bdr);



  flex-shrink: 0;



  cursor: pointer;



  transition: all var(--fast);



  display: flex;



  align-items: center;



  justify-content: center;



  font-size: .55rem;



  font-weight: 800;



}



.oradio.ok {



  border-color: var(--ok);



  background: var(--ok);



  color: #fff;



  box-shadow: 0 0 6px var(--ok);



}



.oradio:not(.ok):hover {



  border-color: var(--ok);



}







.olet {



  width: 20px;



  height: 20px;



  border-radius: var(--r-sm);



  background: var(--t4);



  display: flex;



  align-items: center;



  justify-content: center;



  font-size: .63rem;



  font-weight: 800;



  color: var(--t2);



  flex-shrink: 0;



}







.oinp {



  flex: 1;



  padding: 7px 11px;



  border: 1.5px solid var(--bdr);



  border-radius: var(--r-sm);



  font-family: inherit;



  font-size: .78rem;



  color: var(--t1);



  background: var(--bg);



  outline: none;



  transition: all var(--fast);



}



.oinp:focus {



  border-color: var(--brand);



  box-shadow: 0 0 0 2px var(--brand-glow);



}







.odel {



  width: 22px;



  height: 22px;



  border-radius: var(--r-sm);



  border: 1px solid var(--bdr);



  background: transparent;



  display: flex;



  align-items: center;



  justify-content: center;



  cursor: pointer;



  color: var(--t3);



  font-size: .64rem;



  transition: all var(--fast);



  flex-shrink: 0;



}



.odel:hover {



  color: var(--err);



}







.add-opt {



  display: inline-flex;



  align-items: center;



  gap: 5px;



  padding: 6px 12px;



  border-radius: var(--r-full);



  border: 1.5px dashed var(--bdr);



  background: transparent;



  font-size: .72rem;



  font-weight: 700;



  color: var(--t3);



  cursor: pointer;



  transition: all var(--fast);



}



.add-opt:hover {



  border-color: var(--brand);



  color: var(--brand);



  background: var(--brand-bg);



}







.add-q {



  display: flex;



  align-items: center;



  justify-content: center;



  gap: 7px;



  width: 100%;



  padding: 12px;



  border-radius: var(--r-lg);



  border: 2px dashed var(--brand-l);



  background: var(--brand-bg);



  font-size: .83rem;



  font-weight: 800;



  color: var(--brand);



  cursor: pointer;



  transition: all var(--fast);



}



.add-q:hover {



  background: var(--brand);



  color: #fff;



  border-color: var(--brand);



  box-shadow: var(--sh-brand);



}







.deg-sum {



  display: flex;



  align-items: center;



  justify-content: space-between;



  padding: 10px 14px;



  background: var(--brand-bg);



  border: 1px solid var(--brand-l);



  border-radius: var(--r-md);



  margin-top: 10px;



}



.deg-sum-l {



  font-size: .77rem;



  color: var(--brand);



  font-weight: 700;



}



.deg-sum-v {



  font-size: 1rem;



  font-weight: 900;



  color: var(--brand);



}







/* ── Coupon Tiers & Matrices ── */



.ctiers {



  display: flex;



  flex-direction: column;



  gap: 8px;



}







.ctier {



  display: flex;



  align-items: center;



  gap: 8px;



  flex-wrap: wrap;



  background: var(--bg3);



  border: 1px solid var(--bdr);



  border-radius: var(--r-md);



  padding: 8px 12px;



}







.ctier-range {



  display: flex;



  align-items: center;



  gap: 4px;



  font-size: .77rem;



  color: var(--t2);



  font-weight: 700;



}







.ctier input[type=number] {



  width: 54px;



  padding: 5px 6px;



  border: 1.5px solid var(--bdr);



  border-radius: var(--r-sm);



  font-family: inherit;



  font-size: .78rem;



  color: var(--t1);



  background: var(--bg);



  outline: none;



  text-align: center;



  transition: all var(--fast);



}



.ctier input[type=number]:focus {



  border-color: var(--brand);



  box-shadow: 0 0 0 2px var(--brand-glow);



}







.ctier-arr {



  color: var(--t3);



  font-size: .76rem;



}







.crew {



  display: flex;



  align-items: center;



  gap: 5px;



  background: var(--cou-bg);



  border: 1px solid rgba(139,92,246,.35);



  border-radius: var(--r-sm);



  padding: 5px 10px;



}



.crew i {



  color: var(--cou);



}



.crew input[type=number] {



  width: 42px;



  background: transparent;



  border: none;



  outline: none;



  font-size: .8rem;



  font-weight: 800;



  color: var(--cou);



  text-align: center;



}



.crew-l {



  font-size: .68rem;



  color: var(--cou);



  font-weight: 700;



}







.ctier-del {



  width: 24px;



  height: 24px;



  border-radius: var(--r-sm);



  border: 1px solid var(--bdr);



  background: transparent;



  display: flex;



  align-items: center;



  justify-content: center;



  cursor: pointer;



  color: var(--t3);



  font-size: .66rem;



  transition: all var(--fast);



  margin-right: auto;



}



.ctier-del:hover {



  color: var(--err);



  border-color: var(--err);



  background: var(--err-bg);



}







/* ── Standard Buttons ── */



.btn {



  display: inline-flex;



  align-items: center;



  gap: 6px;



  padding: 8px 18px;



  border-radius: var(--r-md);



  font-size: .83rem;



  font-weight: 700;



  border: 1.5px solid transparent;



  cursor: pointer;



  transition: all var(--fast);



}







.btn-p {



  background: var(--brand);



  color: #fff;



  box-shadow: var(--sh-brand);



}



.btn-p:hover {



  background: var(--brand-d);



  transform: translateY(-1px);



  box-shadow: 0 6px 20px var(--brand-glow);



}







.btn-g {



  background: var(--bg);



  color: var(--t2);



  border-color: var(--bdr);



}



.btn-g:hover {



  background: var(--brand-bg);



  color: var(--brand);



  border-color: var(--brand);



  transform: translateY(-1px);



}







.btn-dg {



  background: transparent;



  color: var(--err);



  border-color: var(--err-bg);



}



.btn-dg:hover {



  background: var(--err-bg);



  border-color: var(--err);



}







.btn:disabled {



  opacity: .45;



  cursor: not-allowed;



  transform: none !important;



  box-shadow: none !important;



}







/* ── Detailed Submissions List ── */



.dq {



  background: var(--bg3);



  border: 1px solid var(--bdr);



  border-radius: var(--r-md);



  margin-bottom: 10px;



  overflow: hidden;



}







.dq-hdr {



  display: flex;



  align-items: center;



  gap: 8px;



  padding: 11px 14px;



  border-bottom: 1px solid var(--bdr);



  background: var(--bg);



}







.dq-opt {



  display: flex;



  align-items: center;



  gap: 8px;



  padding: 6px 12px;



  border-radius: var(--r-sm);



  border: 1px solid var(--bdr);



  font-size: .77rem;



  color: var(--t2);



  margin-bottom: 5px;



}



.dq-opt.ok {



  background: var(--ok-bg);



  border-color: #6ee7b7;



  color: #065f46;



  font-weight: 700;



}



.dq-opt:last-child {



  margin-bottom: 0;



}







.sub-tbl {



  width: 100%;



  border-collapse: collapse;



}



.sub-tbl th {



  padding: 10px 14px;



  text-align: right;



  font-size: .71rem;



  font-weight: 800;



  color: var(--t3);



  background: var(--bg3);



  border-bottom: 1px solid var(--bdr);



}



.sub-tbl td {



  padding: 10px 14px;



  font-size: .78rem;



  color: var(--t1);



  border-bottom: 1px solid var(--bdr);



}



.sub-tbl tr:last-child td {



  border-bottom: none;



}



.sub-tbl tr:hover td {



  background: var(--bg3);



}







/* ── Spinner ── */



.spinner {



  display: inline-block;



  width: 14px;



  height: 14px;



  border: 2px solid rgba(255,255,255,.35);



  border-top-color: #fff;



  border-radius: 50%;



  animation: spin .6s linear infinite;



}



@keyframes spin { to { transform: rotate(360deg) } }







/* ── Confirm Dialogue ── */



.conf-body {



  padding: 24px 20px;



  text-align: center;



}







.conf-ico {



  width: 54px;



  height: 54px;



  border-radius: 50%;



  background: var(--err-bg);



  display: flex;



  align-items: center;



  justify-content: center;



  margin: 0 auto 14px;



  font-size: 1.4rem;



  color: var(--err);



  box-shadow: 0 4px 10px rgba(239,68,68,.15);



}







.conf-t {



  font-weight: 800;



  font-size: .95rem;



  color: var(--t1);



  margin-bottom: 5px;



}



.conf-s {



  font-size: .8rem;



  color: var(--t3);



  font-weight: 600;



}







/* ── Beautiful Live Toasts ── */



.tc {



  position: fixed;



  bottom: 24px;



  left: 50%;



  transform: translateX(-50%);



  z-index: 9999;



  display: flex;



  flex-direction: column;



  gap: 8px;



  pointer-events: none;



}







.toast {



  display: flex;



  align-items: center;



  gap: 8px;



  padding: 12px 20px;



  border-radius: var(--r-full);



  background: var(--t1);



  color: #fff;



  font-size: .81rem;



  font-weight: 700;



  box-shadow: var(--sh-lg);



  opacity: 0;



  transform: translateY(8px);



  transition: all var(--norm);



  pointer-events: auto;



  white-space: nowrap;



}



.toast.show {



  opacity: 1;



  transform: translateY(0);



}



.toast.ok { background: var(--ok); }



.toast.err { background: var(--err); }



.toast.info { background: var(--brand); }







/* ── Responsive Styling ── */



@media(max-width:860px) {



  .hero { grid-template-columns: 1fr; }



  .hero-side { grid-template-columns: 1fr 1fr; }



  .step1-grid { grid-template-columns: 1fr; }



  .detail-columns { grid-template-columns: 1fr; }



  .detail-overview { grid-template-columns: repeat(2, 1fr); }



}







@media(max-width:680px) {



  .hero, .step1-grid, .detail-columns, .detail-overview { grid-template-columns: 1fr; }

  .page { margin-top: 15px !important; padding-top: 20px !important; }



  .hero-side, .field-grid, .timer-grid, .preset-grid, .tmeta, .tinfo-grid { grid-template-columns: 1fr 1fr; }



  .stats { grid-template-columns: 1fr 1fr; }



  .frow { grid-template-columns: 1fr; }



  .tgrid { grid-template-columns: 1fr; }



  .topbar { padding-left: 12px; padding-right: 12px; gap: 8px; }



  .mbody, .mhdr, .mfoot { padding-left: 16px; padding-right: 16px; }



  .hero-card { padding: 18px; }



  .list-shell { padding: 14px; }



  .steps { gap: 6px; }



  .step:not(:last-child)::after { display: none; }



  .scard2-body, .panel-body { padding: 12px; }



  .ans-shell { padding: 12px; }



  .ans-head { padding: 12px; align-items: flex-start; gap: 10px; }



  .ans-avatar { width: 42px; height: 42px; font-size: 1rem; }



  .ans-name { font-size: .9rem; }



  .ans-question { padding: 12px; }



  .ans-choice { font-size: .8rem; padding: 8px 10px; }



  



  .sub-tbl thead { display: none; }
  .sub-tbl, .sub-tbl tbody, .sub-tbl tr, .sub-tbl td { display: block; width: 100%; box-sizing: border-box; }
  .sub-tbl tr { background: var(--bg-card); border: 1px solid var(--bdr); border-radius: 12px; padding: 12px; margin-bottom: 10px; display: flex; flex-direction: column; gap: 10px; }
  .sub-tbl td { padding: 0 !important; border: none !important; text-align: right; font-size: 0.8rem; }
  .sub-tbl td:first-child { display: flex; align-items: center; gap: 8px; justify-content: flex-start; direction: rtl; border-bottom: 1px dashed var(--bdr) !important; padding-bottom: 8px !important; }
  .sub-tbl td:first-child::before { display: none !important; }
  .sub-tbl td:not(:first-child):not(:last-child) { display: flex; justify-content: space-between; align-items: center; direction: rtl; }
  .sub-tbl td:not(:first-child):not(:last-child)[data-label]::before { content: attr(data-label) ': '; font-weight: 700; color: var(--t3); font-size: 0.72rem; }
  .sub-tbl td:last-child { display: flex; justify-content: flex-end; padding-top: 8px !important; border-top: 1px solid var(--bdr) !important; margin-top: 4px; }



  



  .hero-title { font-size: 1.3rem; }



  .hero-sub { font-size: .82rem; }



  .hero-actions { flex-direction: column; gap: 7px; }



  .hero-actions .btn-create, .hero-actions .hero-link { width: 100%; justify-content: center; }



  .tb-title { font-size: .88rem; }



  .btn-create .btn-text { display: none; }



  .detail-overview { grid-template-columns: 1fr 1fr; }



  .grade-sheet-body { padding: 12px 14px; }



  .grade-sub-card { padding: 12px; }



  .mfoot { flex-wrap: wrap; }



  .mfoot .btn { flex: 1; min-width: 80px; justify-content: center; min-height: 40px; }



}







@media(max-width:480px) {



  .hero-side, .tmeta, .tinfo-grid { grid-template-columns: 1fr 1fr; }



  .field-grid, .timer-grid, .preset-grid { grid-template-columns: 1fr; }



  .stats { grid-template-columns: 1fr 1fr; }



  .scard-val { font-size: 1.3rem; }



  .modal { margin: 0; }



  .overlay { padding: 8px; }



  .overlay.fullscreen { padding: 0; }



  .ans-shell { padding: 10px; }



}







@media(max-width:380px) {



  .scard { padding: 11px 12px; }



  .scard-val { font-size: 1.1rem; }



  .hero-side, .tmeta, .tinfo-grid { grid-template-columns: 1fr 1fr; }



  .field-grid, .timer-grid, .preset-grid { grid-template-columns: 1fr; }



  .topbar { padding: 8px 10px; height: auto; flex-wrap: wrap; }



  .tb-title { order: -1; width: 100%; }



  .btn-create, .tb-back { justify-content: center; }



  .detail-overview { grid-template-columns: 1fr; }



}







/* ── Question Type Selector ── */



.q-type-selector {



  display: flex;



  gap: 6px;



  padding: 10px 12px 8px;



  background: var(--bg);



  border-bottom: 1px solid var(--bdr);



}







.q-type-btn {



  flex: 1;



  display: flex;



  align-items: center;



  justify-content: center;



  gap: 5px;



  padding: 8px 6px;



  border-radius: var(--r-md);



  border: 1.5px solid var(--bdr);



  background: var(--bg3);



  color: var(--t2);



  font-weight: 700;



  cursor: pointer;



  transition: all var(--fast);



  white-space: nowrap;



}



.q-type-btn:hover {



  border-color: var(--brand);



  color: var(--brand);



  background: var(--brand-bg);



}



.q-type-btn.active {



  background: var(--brand-bg);



  border-color: var(--brand);



  color: var(--brand);



  box-shadow: 0 0 0 2px var(--brand-glow);



}



.q-type-btn.active-tf {



  background: #fef3c7;



  border-color: #f59e0b;



  color: #92400e;



  box-shadow: 0 0 0 2px rgba(245,158,11,.15);



}



.q-type-btn.active-open {



  background: #f0fdf4;



  border-color: #10b981;



  color: #065f46;



  box-shadow: 0 0 0 2px rgba(16,185,129,.12);



}



.q-type-btn i {



  font-size: .76rem;



  flex-shrink: 0;



}







/* ── True / False Options ── */



.tf-opts {



  display: flex;



  gap: 10px;



  padding: 10px 12px;



}







.tf-btn {



  flex: 1;



  display: flex;



  align-items: center;



  justify-content: center;



  gap: 8px;



  padding: 12px;



  border-radius: var(--r-md);



  border: 2px solid var(--bdr);



  background: var(--bg3);



  font-size: .87rem;



  font-weight: 700;



  cursor: pointer;



  transition: all var(--fast);



  color: var(--t2);



}



.tf-btn:hover {



  border-color: var(--brand);



}



.tf-btn.tf-true.selected {



  background: #d1fae5;



  border-color: #10b981;



  color: #065f46;



  box-shadow: 0 0 10px rgba(16,185,129,.2);



}



.tf-btn.tf-false.selected {



  background: #fee2e2;



  border-color: #ef4444;



  color: #991b1b;



  box-shadow: 0 0 10px rgba(239,68,68,.18);



}



.tf-btn.tf-true i { color: #10b981; }



.tf-btn.tf-false i { color: #ef4444; }







/* ── Question Media Section ── */



.q-img-section {



  padding: 10px 14px;



  border-top: 1px solid var(--bdr);



  background: var(--bg);



}







.q-img-toggle {



  display: flex;



  align-items: center;



  gap: 7px;



  font-size: .72rem;



  font-weight: 700;



  color: var(--t3);



  cursor: pointer;



  padding: 4px 0;



  transition: color var(--fast);



  background: none;



  border: none;



  width: 100%;



}



.q-img-toggle:hover {



  color: var(--brand);



}







.q-img-input-wrap {



  display: none;



  margin-top: 8px;



  flex-direction: column;



  gap: 6px;



}



.q-img-input-wrap.open {



  display: flex;



}







.q-img-url-row {



  display: flex;



  gap: 7px;



  align-items: center;



}







.q-img-url-inp {



  flex: 1;



  padding: 7px 11px;



  border: 1.5px solid var(--bdr);



  border-radius: var(--r-sm);



  font-family: inherit;



  font-size: .78rem;



  color: var(--t1);



  background: var(--bg3);



  outline: none;



  transition: border-color var(--fast);



}



.q-img-url-inp:focus {



  border-color: var(--brand);



}







.q-img-fetch-btn {



  padding: 7px 14px;



  border-radius: var(--r-sm);



  background: var(--brand);



  color: #fff;



  border: none;



  font-size: .73rem;



  font-weight: 700;



  cursor: pointer;



  white-space: nowrap;



  transition: all var(--fast);



}



.q-img-fetch-btn:hover {



  background: var(--brand-d);



}







.q-img-preview {



  display: none;



  position: relative;



  border-radius: var(--r-md);



  overflow: hidden;



  background: var(--bg3);



  border: 1.5px solid var(--bdr);



  max-height: 220px;



}



.q-img-preview img {



  width: 100%;



  max-height: 220px;



  object-fit: contain;



  display: block;



}



.q-img-remove {



  position: absolute;



  top: 6px;



  left: 6px;



  width: 26px;



  height: 26px;



  border-radius: 50%;



  background: rgba(239,68,68,.9);



  border: none;



  color: #fff;



  font-size: .72rem;



  cursor: pointer;



  display: flex;



  align-items: center;



  justify-content: center;



  transition: background var(--fast);



}



.q-img-remove:hover {



  background: var(--err);



}







.q-img-status {



  font-size: .7rem;



  color: var(--t3);



  font-weight: 600;



}



.q-img-status.ok { color: var(--ok); }



.q-img-status.err { color: var(--err); }







/* ── Open Question Notification ── */



.open-q-note {



  display: flex;



  align-items: center;



  gap: 8px;



  margin: 8px 12px 10px;



  padding: 9px 12px;



  background: #f0fdf4;



  border: 1px solid #6ee7b7;



  border-radius: var(--r-sm);



  font-size: .73rem;



  color: #065f46;



  font-weight: 700;



}







/* ── Interactive Grading Sheets ── */



.grade-panel {



  position: fixed;



  inset: 0;



  z-index: 900;



  background: rgba(15, 17, 23, 0.45);



  backdrop-filter: blur(8px);



  -webkit-backdrop-filter: blur(8px);



  display: none;



  align-items: flex-end;



  justify-content: center;



  transition: all var(--norm);



}



.grade-panel.open {



  display: flex;



}







.grade-sheet {



  background: var(--bg);



  border-radius: 20px 20px 0 0;



  width: 100%;



  max-width: 720px;



  max-height: 90vh;



  overflow-y: auto;



  box-shadow: 0 -8px 32px rgba(0,0,0,.16);



  display: flex;



  flex-direction: column;



  border: 1px solid var(--bdr);



}







.grade-sheet-hdr {



  padding: 18px 24px;



  border-bottom: 1px solid var(--bdr);



  display: flex;



  align-items: center;



  gap: 12px;



  position: sticky;



  top: 0;



  background: var(--bg);



  z-index: 2;



}







.grade-sheet-body {



  padding: 20px 24px;



  flex: 1;



}







.grade-sub-card {



  background: var(--bg3);



  border: 1.5px solid var(--bdr);



  border-radius: var(--r-md);



  padding: 16px;



  margin-bottom: 14px;



}







.grade-sub-name {



  font-size: .9rem;



  font-weight: 800;



  color: var(--t1);



  margin-bottom: 12px;



  display: flex;



  align-items: center;



  gap: 8px;



  flex-wrap: wrap;



}







.grade-q-row {



  margin-bottom: 14px;



}







.grade-q-text {



  font-size: .81rem;



  font-weight: 800;



  color: var(--t2);



  margin-bottom: 6px;



}







.grade-ans-text {



  background: var(--bg);



  border: 1px solid var(--bdr);



  border-radius: var(--r-sm);



  padding: 10px 14px;



  font-size: .81rem;



  color: var(--t1);



  margin-bottom: 8px;



  white-space: pre-wrap;



  word-break: break-word;



  line-height: 1.6;



}







.grade-score-row {



  display: flex;



  align-items: center;



  gap: 8px;



}







.grade-score-inp {



  width: 80px;



  padding: 6px 10px;



  border: 1.5px solid var(--bdr);



  border-radius: var(--r-sm);



  font-family: inherit;



  font-size: .87rem;



  font-weight: 800;



  text-align: center;



  transition: all var(--fast);



  background: var(--bg);



  color: var(--t1);



}



.grade-score-inp:focus {



  border-color: var(--brand);



  box-shadow: 0 0 0 2px var(--brand-glow);



  outline: none;



}







.grade-max-lbl {



  font-size: .74rem;



  color: var(--t3);



  font-weight: 700;



}







.grade-save-btn {



  display: block;



  width: 100%;



  margin-top: 14px;



  padding: 12px;



  background: var(--brand);



  color: #fff;



  border: none;



  border-radius: var(--r-full);



  font-size: .9rem;



  font-weight: 800;



  cursor: pointer;



  box-shadow: var(--sh-brand);



  transition: all var(--fast);



}



.grade-save-btn:hover {



  background: var(--brand-d);



  transform: translateY(-1px);



  box-shadow: 0 6px 18px var(--brand-glow);



}







.pending-badge {



  display: inline-flex;



  align-items: center;



  gap: 4px;



  background: var(--err-bg);



  color: var(--err);



  border-radius: var(--r-full);



  padding: 3px 10px;



  font-size: .7rem;



  font-weight: 800;



}







/* ── Grading Panel Notes ── */



.grade-note-row {



  display: flex;



  align-items: flex-start;



  gap: 8px;



  margin-top: 10px;



  padding: 10px 12px;



  background: #fffbeb;



  border: 1px solid #fde68a;



  border-radius: var(--r-sm);



}



.grade-note-row i {



  color: #d97706;



  font-size: .82rem;



  margin-top: 5px;



  flex-shrink: 0;



}



.grade-note-row .gn-wrap {



  flex: 1;



  display: flex;



  flex-direction: column;



  gap: 3px;



}



.grade-note-row .gn-lbl {



  font-size: .68rem;



  font-weight: 800;



  color: #92400e;



}







.grade-note-inp {



  width: 100%;



  padding: 7px 11px;



  border: 1.5px solid #fde68a;



  border-radius: var(--r-sm);



  font-family: inherit;



  font-size: .78rem;



  color: var(--t1);



  background: #fff;



  outline: none;



  resize: vertical;



  min-height: 40px;



  max-height: 120px;



  transition: all var(--fast);



}



.grade-note-inp:focus {



  border-color: #f59e0b;



  box-shadow: 0 0 0 2px rgba(245,158,11,.15);



}



.grade-note-inp::placeholder {



  color: #d4a373;



  font-size: .74rem;



}







/* ── Drag & Drop Media Uploads ── */



.q-img-tabs {



  display: flex;



  gap: 0;



  border-radius: var(--r-sm);



  overflow: hidden;



  border: 1.5px solid var(--bdr);



  background: var(--bg3);



  margin-bottom: 8px;



}







.q-img-tab {



  flex: 1;



  padding: 7px 10px;



  border: none;



  background: transparent;



  font-size: .73rem;



  font-weight: 800;



  color: var(--t3);



  cursor: pointer;



  transition: all var(--fast);



  display: flex;



  align-items: center;



  justify-content: center;



  gap: 5px;



}



.q-img-tab:first-child {



  border-left: 1px solid var(--bdr);



}



.q-img-tab.active {



  background: var(--brand);



  color: #fff;



}



.q-img-tab:hover:not(.active) {



  background: var(--brand-bg);



  color: var(--brand);



}







.q-img-tab-panel {



  display: none;



}



.q-img-tab-panel.active {



  display: flex;



  flex-direction: column;



  gap: 6px;



}







.q-img-drop {



  border: 2px dashed var(--bdr);



  border-radius: var(--r-md);



  padding: 20px 14px;



  text-align: center;



  cursor: pointer;



  transition: all var(--fast);



  background: var(--bg3);



}



.q-img-drop:hover, .q-img-drop.dragover {



  border-color: var(--brand);



  background: var(--brand-bg);



}



.q-img-drop i {



  font-size: 1.5rem;



  color: var(--brand);



  display: block;



  margin-bottom: 6px;



}



.q-img-drop p {



  font-size: .73rem;



  color: var(--t3);



  font-weight: 700;



  margin: 0;



}



.q-img-drop small {



  font-size: .65rem;



  color: var(--t4);



}







.q-img-uploading {



  display: none;



  align-items: center;



  gap: 8px;



  font-size: .74rem;



  color: var(--brand);



  font-weight: 800;



}







/* ── Cleaner Modern Settings Cards ── */



.scard2 {



  background: var(--bg);



  border: 1px solid var(--bdr);



  border-radius: var(--r-lg);



  overflow: hidden;



  margin-bottom: 12px;



}







.scard2-hdr {



  display: flex;



  align-items: center;



  gap: 8px;



  padding: 12px 18px;



  background: var(--bg3);



  border-bottom: 1px solid var(--bdr);



  font-size: .8rem;



  font-weight: 800;



  color: var(--brand);



  letter-spacing: .03em;



  text-transform: uppercase;



}



.scard2-hdr i {



  font-size: .85rem;



}







.scard2-body {



  padding: 14px 16px;



}



.scard2-body > .fg, .scard2-body > .frow, .scard2-body > #specRow {



  padding: 12px;



  border-radius: var(--r-lg);



  background: var(--bg3);



  border: 1px solid var(--bdr);



  margin-bottom: 10px;



}



.scard2-body > .fg:last-child {



  margin-bottom: 0;



}







.sopt-row {



  display: flex;



  align-items: center;



  gap: 12px;



  padding: 12px 14px;



  border-radius: var(--r-md);



  border: 1px solid var(--bdr);



  background: var(--bg3);



  cursor: pointer;



  transition: all var(--fast);



  margin-bottom: 9px;



}



.sopt-row:hover {



  border-color: var(--brand);



  background: var(--brand-bg);



  transform: translateY(-1px);



}







.sopt-ico {



  width: 36px;



  height: 36px;



  min-width: 36px;



  border-radius: var(--r-sm);



  display: flex;



  align-items: center;



  justify-content: center;



  font-size: .92rem;



  flex-shrink: 0;



}







.sopt-txt {



  flex: 1;



  min-width: 0;



}



.sopt-lbl {



  font-size: .84rem;



  font-weight: 800;



  color: var(--t1);



}



.sopt-desc {



  font-size: .7rem;



  color: var(--t3);



  margin-top: 2px;



  line-height: 1.5;



  font-weight: 500;



}







@media(max-width:500px) {



  .sopt-desc { display: none; }



  .sopt-lbl { font-size: .8rem; }



  .scard2-body { padding: 10px 12px; }



  .sopt-row { padding: 9px 10px; gap: 8px; }



}







.hint-box {



  display: flex;



  align-items: flex-start;



  gap: 9px;



  padding: 10px 12px;



  border-radius: var(--r-md);



  background: var(--info-bg);



  border: 1px solid #bfdbfe;



  margin-bottom: 11px;



}



.hint-box i {



  color: var(--info);



  font-size: .92rem;



  margin-top: 2px;



}



.hint-box strong {



  display: block;



  font-size: .78rem;



  color: var(--t1);



  margin-bottom: 2px;



}



.hint-box span {



  font-size: .71rem;



  color: var(--t2);



  line-height: 1.7;



}







.quick-presets {



  display: flex;



  flex-wrap: wrap;



  gap: 6px;



  margin-top: 9px;



}







.quick-btn {



  padding: 6px 12px;



  border-radius: var(--r-full);



  border: 1px solid var(--bdr);



  background: var(--bg);



  color: var(--t2);



  font-size: .73rem;



  font-weight: 700;



  cursor: pointer;



  transition: all var(--fast);



}



.quick-btn:hover {



  background: var(--brand-bg);



  color: var(--brand);



  border-color: var(--brand);



}







.mini-note {



  font-size: .69rem;



  color: var(--t3);



  margin-top: 5px;



  line-height: 1.7;



  font-weight: 600;



}







.date-switch {



  display: grid;



  grid-template-columns: 1fr;



  gap: 10px;



}







.time-block.is-disabled {



  opacity: .5;



  pointer-events: none;



}







.step1-grid {



  display: grid;



  grid-template-columns: minmax(0, 1.25fr) minmax(280px, .85fr);



  gap: 14px;



  align-items: start;



}



@media(max-width:760px) {



  .step1-grid { grid-template-columns: 1fr; }



}







.step-stack {



  display: flex;



  flex-direction: column;



  gap: 12px;



}







.panel-card {



  background: var(--bg);



  border: 1px solid var(--bdr);



  border-radius: var(--r-xl);



  box-shadow: var(--sh-sm);



  overflow: hidden;



}







.panel-head {



  display: flex;



  align-items: flex-start;



  justify-content: space-between;



  gap: 14px;



  padding: 20px 20px 0;



}



.panel-title {



  font-size: 1rem;



  font-weight: 900;



  color: var(--t1);



}



.panel-sub {



  font-size: .75rem;



  line-height: 1.8;



  color: var(--t3);



  margin-top: 4px;



  font-weight: 600;



}







.panel-body {



  padding: 16px 20px 20px;



}







.panel-mark {



  width: 42px;



  height: 42px;



  border-radius: 14px;



  background: var(--brand);



  display: flex;



  align-items: center;



  justify-content: center;



  color: #fff;



  font-size: .95rem;



  box-shadow: var(--sh-brand);



  flex-shrink: 0;



}







.field-grid {



  display: grid;



  grid-template-columns: 1fr 1fr;



  gap: 11px;



}



.field-grid.single {



  grid-template-columns: 1fr;



}







.field-card {



  padding: 12px;



  border-radius: var(--r-lg);



  border: 1px solid var(--bdr);



  background: var(--bg3);



}



.field-card.full {



  grid-column: 1/-1;



}



.field-card .flbl {



  margin-bottom: 6px;



}







.field-note {



  font-size: .7rem;



  color: var(--t3);



  line-height: 1.7;



  margin-top: 6px;



}







.spec-list-card {



  max-height: 180px;



  overflow-y: auto;



  border: 1.5px solid var(--bdr);



  border-radius: var(--r-lg);



  padding: 11px;



  display: flex;



  flex-direction: column;



  gap: 7px;



  background: var(--bg);



  font-size: .81rem;



  color: var(--t3);



  font-weight: 700;



}







.setting-group {



  display: flex;



  flex-direction: column;



  gap: 9px;



}







.setting-title {



  font-size: .78rem;



  font-weight: 800;



  color: var(--brand);



  letter-spacing: .03em;



  text-transform: uppercase;



  margin-bottom: 2px;



}







.setting-item {



  display: flex;



  align-items: flex-start;



  gap: 12px;



  padding: 14px;



  border-radius: var(--r-lg);



  border: 1px solid var(--bdr);



  background: var(--bg3);



  transition: all var(--fast);



  cursor: pointer;



}



.setting-item:hover {



  border-color: var(--brand);



  background: var(--brand-bg);



  transform: translateY(-1px);



}



.setting-item.primary {



  background: var(--brand-bg);



  border-color: var(--brand);



}







.setting-icon {



  width: 38px;



  height: 38px;



  border-radius: 12px;



  display: flex;



  align-items: center;



  justify-content: center;



  font-size: .92rem;



  flex-shrink: 0;



}







.setting-copy {



  flex: 1;



}



.setting-copy strong {



  display: block;



  font-size: .84rem;



  color: var(--t1);



  margin-bottom: 3px;



}



.setting-copy span {



  display: block;



  font-size: .72rem;



  color: var(--t3);



  line-height: 1.7;



}



.setting-copy small {



  display: inline-flex;



  align-items: center;



  gap: 5px;



  margin-top: 6px;



  padding: 4px 10px;



  border-radius: var(--r-full);



  background: var(--bg);



  border: 1px solid var(--bdr);



  font-size: .65rem;



  font-weight: 700;



  color: var(--t2);



}







.preset-grid {



  display: grid;



  grid-template-columns: repeat(5, minmax(0, 1fr));



  gap: 8px;



  margin-top: 9px;



}







.preset-btn {



  padding: 8px;



  border-radius: var(--r-md);



  border: 1px solid var(--bdr);



  background: var(--bg);



  font-size: .74rem;



  font-weight: 800;



  color: var(--t2);



  cursor: pointer;



  transition: all var(--fast);



}



.preset-btn:hover {



  background: var(--brand-bg);



  border-color: var(--brand);



  color: var(--brand);



}







.timer-grid {



  display: grid;



  grid-template-columns: 1fr 1fr;



  gap: 10px;



  margin-top: 9px;



}







.coupon-summary {



  display: flex;



  align-items: center;



  gap: 12px;



  padding: 13px 15px;



  border-radius: var(--r-lg);



  background: var(--cou-bg);



  border: 1px solid rgba(139,92,246,.3);



  margin-bottom: 13px;



}



.coupon-summary i {



  width: 36px;



  height: 36px;



  border-radius: 12px;



  display: flex;



  align-items: center;



  justify-content: center;



  background: #fff;



  color: var(--cou);



  box-shadow: 0 4px 10px rgba(139,92,246,.15);



}



.coupon-summary strong {



  display: block;



  font-size: .87rem;



  color: var(--t1);



}



.coupon-summary span {



  display: block;



  font-size: .71rem;



  color: var(--t3);



  margin-top: 2px;



  font-weight: 600;



}







.detail-shell {



  display: flex;



  flex-direction: column;



  gap: 15px;



}







.detail-overview {



  display: grid;



  grid-template-columns: repeat(4, minmax(0, 1fr));



  gap: 10px;



}







.detail-stat {



  padding: 14px;



  border-radius: var(--r-lg);



  background: var(--bg3);



  border: 1px solid var(--bdr);



}



.detail-stat-label {



  font-size: .71rem;



  color: var(--t3);



  margin-bottom: 6px;



  font-weight: 600;



}



.detail-stat-value {



  font-size: 1.05rem;



  font-weight: 900;



  color: var(--t1);



}







.detail-banner {



  display: flex;



  flex-wrap: wrap;



  align-items: center;



  gap: 8px;



  padding: 13px 15px;



  border-radius: var(--r-lg);



  background: var(--brand-bg);



  border: 1px solid var(--brand-l);



}







.coupon-chips {



  display: flex;



  flex-wrap: wrap;



  gap: 8px;



}







.detail-columns {



  display: grid;



  grid-template-columns: 1fr 1fr;



  gap: 14px;



}







.detail-card {



  background: var(--bg);



  border: 1px solid var(--bdr);



  border-radius: var(--r-lg);



  box-shadow: var(--sh-sm);



  overflow: hidden;



}







.detail-card-head {



  display: flex;



  align-items: center;



  justify-content: space-between;



  gap: 10px;



  padding: 14px 16px;



  border-bottom: 1px solid var(--bdr);



  background: var(--bg3);



}



.detail-card-title {



  font-size: .83rem;



  font-weight: 800;



  color: var(--t1);



}







.detail-list {



  padding: 8px 16px 14px;



}







.detail-person {



  display: flex;



  align-items: center;



  gap: 10px;



  padding: 10px 0;



  border-bottom: 1px solid var(--bdr);



}



.detail-person:last-child {



  border-bottom: none;



  padding-bottom: 0;



}







.detail-person-badge {



  display: inline-flex;



  align-items: center;



  gap: 5px;



  padding: 3px 10px;



  border-radius: var(--r-full);



  background: var(--brand-bg);



  color: var(--brand);



  font-size: .66rem;



  font-weight: 800;



}







.detail-empty {



  padding: 16px 0;



  font-size: .77rem;



  color: var(--t3);



  font-weight: 600;



}







/* ── Premium Student Submissions Review Sheet ── */



.ans-shell {



  padding: 20px;



  max-height: min(78vh, 760px);



  overflow-y: auto;



  background: var(--bg2);



}







.ans-head {



  display: flex;



  align-items: center;



  gap: 14px;



  padding: 16px 18px;



  border-radius: var(--r-lg);



  background: var(--bg);



  border: 1px solid var(--bdr);



  box-shadow: var(--sh-sm);



  margin-bottom: 14px;



}







.ans-avatar {



  width: 50px;



  height: 50px;



  border-radius: 16px;



  background: var(--brand-bg);



  color: var(--brand);



  display: flex;



  align-items: center;



  justify-content: center;



  font-size: 1.25rem;



  flex-shrink: 0;



  border: 1px solid var(--brand-l);



  box-shadow: 0 4px 10px var(--brand-glow);



}







.ans-name {



  font-size: 1rem;



  font-weight: 900;



  color: var(--t1);



  line-height: 1.2;



}



.ans-sub {



  font-size: .75rem;



  color: var(--t3);



  margin-top: 4px;



  font-weight: 600;



}







.ans-question {



  margin-bottom: 14px;



  padding: 16px;



  border: 1px solid var(--bdr);



  border-radius: var(--r-lg);



  background: var(--bg);



  box-shadow: var(--sh-sm);



}







.ans-qhead {



  display: flex;



  gap: 10px;



  align-items: flex-start;



  margin-bottom: 12px;



}







.ans-qnum {



  width: 28px;



  height: 28px;



  min-width: 28px;



  border-radius: 9px;



  background: var(--brand-bg);



  color: var(--brand);



  display: flex;



  align-items: center;



  justify-content: center;



  font-size: .78rem;



  font-weight: 900;



  flex-shrink: 0;



  border: 1px solid var(--brand-l);



}







.ans-qtext {



  font-weight: 800;



  color: var(--t1);



  line-height: 1.6;



  flex: 1;



  word-break: break-word;



}







.ans-open {



  padding: 12px 14px;



  border-radius: var(--r-md);



  background: var(--bg3);



  border: 1px solid var(--bdr);



}



.ans-open-label {



  font-size: .69rem;



  color: var(--t3);



  margin-bottom: 5px;



  font-weight: 800;



}



.ans-open-text {



  color: var(--t2);



  font-size: .88rem;



  white-space: pre-wrap;



  line-height: 1.7;



  word-break: break-word;



}







.ans-choice {



  display: flex;



  align-items: center;



  gap: 10px;



  padding: 10px 14px;



  border-radius: var(--r-md);



  border: 1.5px solid var(--bdr);



  background: var(--bg);



  color: var(--t2);



  font-size: .84rem;



  transition: all var(--fast);



}



.ans-choice + .ans-choice {



  margin-top: 8px;



}



.ans-choice.correct {



  border-color: #86efac;



  background: var(--ok-bg);



  color: #047857;



  font-weight: 700;



}



.ans-choice.wrong {



  border-color: #fca5a5;



  background: var(--err-bg);



  color: #dc2626;



  font-weight: 700;



}







.ans-choice-letter {



  width: 22px;



  height: 22px;



  min-width: 22px;



  border-radius: 7px;



  background: rgba(148,163,184,.14);



  display: flex;



  align-items: center;



  justify-content: center;



  font-weight: 900;



  font-size: .7rem;



  flex-shrink: 0;



}



.ans-choice-icon {



  margin-right: auto;



  font-size: .92rem;



  flex-shrink: 0;



}

/* Subpage Header layout styling */
.tasks-subpage-header {
  display: flex;
}
body {
  background: var(--bg2) !important;
}
.page {
  padding-top: 20px !important;
  margin-top: 0 !important;
  max-width: 1200px !important;
}

@media (max-width: 992px) {
  .tasks-subpage-header {
    flex-direction: column;
    align-items: stretch !important;
    gap: 16px !important;
  }
  .iframe-stats-row {
    justify-content: center;
  }
  .tasks-subpage-header > div:last-child {
    justify-content: center;
  }
}

/* Wide Modal Fullscreen rules */
.overlay.fullscreen {
  height: 100vh !important;
  height: 100dvh !important;
  max-height: 100vh !important;
  max-height: 100dvh !important;
}
.overlay.fullscreen .modal.wide {
  width: 100% !important;
  height: 100vh !important;
  height: 100dvh !important;
  max-width: 100% !important;
  max-height: 100vh !important;
  max-height: 100dvh !important;
  margin: 0 !important;
  border-radius: 0 !important;
  box-shadow: none !important;
  border: none !important;
  display: flex !important;
  flex-direction: column !important;
  transform: none !important;
}

/* Fullscreen Grading Panel Overrides */
.grade-panel {
  position: fixed;
  inset: 0;
  height: 100vh !important;
  height: 100dvh !important;
  z-index: 3000 !important;
  background: var(--bg3) !important;
  align-items: stretch !important;
  justify-content: stretch !important;
}
.grade-sheet {
  width: 100% !important;
  height: 100vh !important;
  height: 100dvh !important;
  max-width: 100% !important;
  max-height: 100vh !important;
  max-height: 100dvh !important;
  border-radius: 0 !important;
  margin: 0 !important;
  display: flex !important;
  flex-direction: column !important;
  background: var(--bg3) !important;
  box-shadow: none !important;
  border: none !important;
  min-height: 100vh !important;
  min-height: 100dvh !important;
}
.grade-sheet-body {
  flex: 1 !important;
  overflow-y: auto !important;
  padding: 0 !important;
  display: flex !important;
  flex-direction: column !important;
}
.grade-sub-card {
  flex: 1 !important;
  display: flex !important;
  flex-direction: column !important;
  height: 100% !important;
}

/* Responsive Unified Details Info Board & Milestones CSS */
.detail-meta-pills {
  display: flex;
  flex-wrap: wrap;
  gap: 12px;
  font-size: 0.75rem;
  color: var(--t3);
  border-bottom: 1px dashed var(--bdr);
  padding-bottom: 12px;
  justify-content: flex-start;
  align-items: center;
  direction: rtl;
  text-align: right;
}
.detail-metrics-row {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  direction: rtl;
}
.detail-metric-col {
  text-align: right;
}
.detail-metric-col:not(:first-child) {
  border-right: 1px solid var(--bdr);
  padding-right: 16px;
}
.detail-milestones-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
  gap: 10px;
}
.detail-milestone-card {
  background: var(--bg-card);
  border: 1.5px solid var(--brand-l);
  border-radius: 10px;
  padding: 10px;
  text-align: center;
  display: flex;
  flex-direction: column;
  gap: 4px;
  transition: transform 0.2s;
}

@media (max-width: 600px) {
  .detail-meta-pills {
    gap: 6px;
    font-size: 0.68rem;
    padding-bottom: 8px;
  }
  .detail-metrics-row {
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
  }
  .detail-metric-col {
    border-right: none !important;
    padding-right: 0 !important;
    background: var(--bg-hover);
    padding: 8px 10px !important;
    border-radius: 8px;
    border: 1px solid var(--bdr);
  }
  .detail-milestones-grid {
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
  }
  .detail-milestone-card {
    padding: 8px;
    gap: 2px;
    border-radius: 8px;
  }
}

/* Skeleton Loading Screens styles */
.skeleton-card {
  background: var(--bg-card, #fff);
  border: 1px solid var(--bdr, rgba(91, 108, 245, .12));
  border-radius: var(--r-lg, 14px);
  padding: 20px;
  height: 160px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  position: relative;
  overflow: hidden;
  box-shadow: var(--sh-sm);
}
.skeleton-card::after {
  content: "";
  position: absolute;
  top: 0; right: 0; bottom: 0; left: 0;
  background: linear-gradient(90deg, transparent, rgba(91, 108, 245, 0.08), transparent);
  transform: translateX(-100%);
  animation: shimmer 1.6s infinite;
}
@keyframes shimmer {
  100% { transform: translateX(100%); }
}
.skeleton-line {
  background: var(--bg2, #f3f4f6);
  border-radius: 6px;
}
.skeleton-title {
  height: 20px;
  width: 60%;
}
.skeleton-text {
  height: 12px;
  width: 85%;
}
.skeleton-footer {
  margin-top: auto;
  display: flex;
  gap: 10px;
}
.skeleton-foot-item {
  height: 22px;
  width: 25%;
  border-radius: 6px;
}

/* Skeleton Rows for Tables / Panels */
.skeleton-row {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-bottom: 1px solid var(--bdr);
  background: var(--bg-card);
  position: relative;
  overflow: hidden;
}
.skeleton-row::after {
  content: "";
  position: absolute;
  top: 0; right: 0; bottom: 0; left: 0;
  background: linear-gradient(90deg, transparent, rgba(91, 108, 245, 0.08), transparent);
  transform: translateX(-100%);
  animation: shimmer 1.6s infinite;
}

/* Ensure all overview overlay contents inherit the premium fonts */
#overviewOv, 
#overviewOv * {
  font-family: 'Baloo Bhaijaan 2', 'Cairo', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
}
</style>



    <script src="/js/og-meta.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.5.28/jspdf.plugin.autotable.min.js"></script>
    <script src="/js/search_intelligent.js"></script>
</head>



<body>















<!-- ══ PAGE ══════════════════════════════════════════════════════ -->



<main class="page">

  <header class="tasks-subpage-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 24px; gap: 16px; border-bottom: 1px solid var(--bdr); padding-bottom: 16px; direction: rtl;">
    <div style="display: flex; align-items: center; gap: 12px;">
      <a href="<?php echo htmlspecialchars($dashBack); ?>" style="text-decoration: none !important; background: var(--bg-card); border: 1px solid var(--bdr); border-radius: var(--r-md); width: 38px; height: 38px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--t1); transition: all 0.2s;" onmouseover="this.style.background='var(--brand-bg)'; this.style.color='var(--brand)';" onmouseout="this.style.background='var(--bg-card)'; this.style.color='var(--t1)';"><i class="fas fa-arrow-right"></i></a>
      <div>
        <div style="font-size: 1.25rem; font-weight: 800; color: var(--t1); font-family: 'Cairo', 'Baloo Bhaijaan 2', sans-serif;">التاسكات والاختبارات</div>
        <?php if ($activeClass): ?>
          <div style="font-size: 0.75rem; font-weight: 700; color: var(--brand); background: var(--brand-bg); padding: 2px 8px; border-radius: 6px; display: inline-flex; align-items: center; gap: 4px; margin-top: 4px; border: 1px solid var(--brand-l);">
            <i class="fas fa-users" style="font-size: 0.65rem;"></i> <?php echo htmlspecialchars($activeClass); ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
    
    <!-- Stats Row -->
    <div class="iframe-stats-row" style="display: flex; gap: 12px; align-items: center;">
      <div style="background: var(--bg-card); border: 1px solid var(--bdr); border-radius: var(--r-md); padding: 6px 14px; text-align: center; min-width: 70px;">
        <div style="font-size: 0.68rem; color: var(--t3); font-weight: bold; margin-bottom: 2px;">إجمالي التاسكات</div>
        <div style="font-size: 1.05rem; font-weight: 800; color: var(--t1);" id="stTotalIframe">—</div>
      </div>

      <div style="background: var(--bg-card); border: 1px solid var(--bdr); border-radius: var(--r-md); padding: 6px 14px; text-align: center; min-width: 70px;">
        <div style="font-size: 0.68rem; color: var(--t3); font-weight: bold; margin-bottom: 2px;">Draft</div>
        <div style="font-size: 1.05rem; font-weight: 800; color: var(--warn);" id="stDraftIframe">—</div>
      </div>
    </div>

    <!-- Actions -->
    <div style="display: flex; gap: 8px; align-items: center;">
      <button onclick="openTasksOverviewModal()" title="تصدير نظرة عامة" style="background:var(--brand-bg); color:var(--brand); border:1.5px solid var(--brand-l); font-weight:bold; cursor:pointer; display:inline-flex; align-items:center; justify-content:center; width: 38px; height: 38px; border-radius: var(--r-md); transition: all 0.2s;" onmouseover="this.style.background='var(--brand)'; this.style.color='#fff';" onmouseout="this.style.background='var(--brand-bg)'; this.style.color='var(--brand)';"><i class="fas fa-file-export" style="font-size:1rem;"></i></button>
      <button class="btn-create" onclick="openCreate()" style="padding: 10px 18px; font-size: 0.85rem; font-weight: 800; height: 38px; display: inline-flex; align-items: center; gap: 6px;"><i class="fas fa-plus"></i> تاسك جديد</button>
    </div>
  </header>

  







  <section class="list-shell">



    <div class="sec-hdr">



      <div class="sec-title"><div class="sec-dot"></div>التاسكات</div>



      <div class="ftabs" id="filterTabsContainer"></div>



    </div>







    <div class="tgrid" id="tGrid">



      <div class="skeleton-card">
        <div class="skeleton-line skeleton-title"></div>
        <div class="skeleton-line skeleton-text" style="width: 40%;"></div>
        <div class="skeleton-line skeleton-text" style="width: 80%;"></div>
        <div class="skeleton-footer">
          <div class="skeleton-line skeleton-foot-item"></div>
          <div class="skeleton-line skeleton-foot-item"></div>
        </div>
      </div>
      <div class="skeleton-card">
        <div class="skeleton-line skeleton-title"></div>
        <div class="skeleton-line skeleton-text" style="width: 30%;"></div>
        <div class="skeleton-line skeleton-text" style="width: 70%;"></div>
        <div class="skeleton-footer">
          <div class="skeleton-line skeleton-foot-item"></div>
          <div class="skeleton-line skeleton-foot-item"></div>
        </div>
      </div>
      <div class="skeleton-card">
        <div class="skeleton-line skeleton-title"></div>
        <div class="skeleton-line skeleton-text" style="width: 50%;"></div>
        <div class="skeleton-line skeleton-text" style="width: 90%;"></div>
        <div class="skeleton-footer">
          <div class="skeleton-line skeleton-foot-item"></div>
          <div class="skeleton-line skeleton-foot-item"></div>
        </div>
      </div>



    </div>



  </section>



</main>







<!-- ══ CREATE / EDIT MODAL ══════════════════════════════════════ -->



<div class="overlay fullscreen" id="createOv">



  <div class="modal">



    <div class="mhdr">



      <div class="mhdr-ico"><i class="fas fa-pen-nib"></i></div>



      <div><div class="mhdr-title" id="createTitle">إنشاء تاسك جديد</div><div class="mhdr-sub">اختبار MCQ مع مكافآت كوبونات</div></div>



      <div class="mclose" onclick="closeCreate()"><i class="fas fa-times"></i></div>



    </div>



    <div style="padding:16px 22px 0;">



      <div class="steps" id="stepBar">



        <div class="step active" id="sd1"><div class="step-c">١</div><div class="step-l">الإعدادات</div></div>



        <div class="step" id="sd2"><div class="step-c">٢</div><div class="step-l">الأسئلة</div></div>



        <div class="step" id="sd3"><div class="step-c">٣</div><div class="step-l">الكوبونات</div></div>



      </div>



    </div>



    <div class="mbody">







      <!-- Step 1 — Settings (redesigned, cleaner) -->



      <div id="sp1">



        <div class="step1-grid">



          <div class="step-stack">







        <!-- Card: Info -->



        <div class="scard2">



          <div class="scard2-hdr"><i class="fas fa-pen"></i> معلومات التاسك</div>



          <div class="scard2-body">



            <div class="fg" style="margin-bottom:12px;">



              <label class="flbl">العنوان <span class="req">*</span></label>



              <input id="fTitle" class="fi" type="text" placeholder="مثال: اختبار سفر التكوين" style="font-size:.95rem;">



            </div>

            <div class="fg" style="margin-bottom:12px;">
              <label class="flbl">تصنيف التاسك</label>
              <div id="activeClassificationsContainer" style="display:flex; flex-wrap:wrap; gap:8px; margin-bottom:8px;"></div>
              <div style="display:flex; gap:8px;">
                <select id="classificationSelector" onchange="handleClassificationSelect(this)" style="flex:1; padding:8px 12px; border:1px solid var(--bdr); border-radius:8px; background:var(--bg); color:var(--t1); font-size:0.87rem; outline:none; cursor:pointer;">
                  <option value="" disabled selected>— اختر تصنيفاً لتحديده —</option>
                </select>
                <button type="button" class="btn btn-p" onclick="addNewClassificationPrompt()" style="white-space: nowrap; flex-shrink: 0; padding: 0 12px; height: 38px; font-size: 0.85rem; font-weight: bold; display: inline-flex; align-items: center; gap: 6px; border-radius: var(--r-md);"><i class="fas fa-plus"></i> تصنيف جديد</button>
              </div>
            </div>



            <div class="fg" style="margin-bottom:12px;">



              <label class="flbl">الفصل / المراحل المستهدفة <span class="req">*</span></label>



              <div id="fClassContainer" style="max-height:160px; overflow-y:auto; border:1.5px solid var(--bdr); border-radius:var(--r-md); padding:10px 12px; background:var(--bg3); display:flex; flex-direction:column; gap:6px;">



                <!-- JavaScript will populate checkboxes here -->



              </div>



            </div>



            <div class="fg" style="margin-bottom:12px;">



              <label class="flbl">تعيين لـ</label>



              <select id="fAssign" class="fs" onchange="onAssignChange()"><option value="all">جميع أطفال الفصل</option><option value="specific">أطفال محددون</option></select>



            </div>



            <div id="specRow" style="display:none;" class="fg" style="margin-bottom:12px;">



              <label class="flbl">اختر الأطفال</label>



              <div id="specList" style="max-height:140px;overflow-y:auto;border:1.5px solid var(--bdr);border-radius:var(--r-md);padding:10px 12px;display:flex;flex-direction:column;gap:7px;background:var(--bg);font-size:.82rem;color:var(--t3);">اختر الفصل أولاً</div>



            </div>



            <div class="fg">



              <label class="flbl">وصف / تعليمات <span style="color:var(--t3);font-weight:400;">(اختياري)</span></label>



              <textarea id="fDesc" class="fta" placeholder="تعليمات للطفل…"></textarea>



            </div>



          </div>



        </div>







        <!-- Card: Timing -->



        <div class="scard2">



          <div class="scard2-hdr"><i class="fas fa-calendar-alt"></i> التوقيت</div>



          <div class="scard2-body">



            <div class="hint-box">



              <i class="fas fa-lightbulb"></i>



              <div>



                <strong>إعداد أسرع للمواعيد</strong>



                <span>يمكنك تحديد ساعة دقيقة، أو تفعيل الإغلاق بنهاية اليوم إذا كنت لا تريد وقتاً محدداً للانتهاء.</span>



              </div>



            </div>



            <div class="frow time-block" id="deadlineBlock" style="margin-bottom:12px;">



              <div class="fg"><label class="flbl">تاريخ البداية <span class="req">*</span></label><input id="fStart" type="datetime-local" class="fi"></div>



              <div class="fg">



                <label class="flbl" style="justify-content:space-between;gap:10px;flex-wrap:wrap;">



                  <span>آخر موعد <span style="color:var(--t3);font-weight:400;">(اختياري)</span></span>



                  <button



                    type="button"



                    onclick="document.getElementById('fNoDeadline').checked=!document.getElementById('fNoDeadline').checked;toggleNoDeadline();"



                    style="border:1px solid var(--brand-l);background:var(--brand-bg);color:var(--brand);border-radius:9999px;padding:4px 10px;font-family:'Cairo',sans-serif;font-size:.72rem;font-weight:700;cursor:pointer;"



                  >بدون آخر موعد</button>



                </label>



                <div class="date-switch">



                  <input id="fEnd" type="datetime-local" class="fi">



                  <input id="fEndDateOnly" type="date" class="fi" style="display:none;">



                </div>



                <div class="mini-note" id="endModeNote">يمكنك تحديد موعد الإغلاق هنا، أو اختيار "بدون آخر موعد".</div>



              </div>



            </div>



            <div class="sopt-row" onclick="document.getElementById('fNoDeadline').click()">



              <div class="sopt-ico" style="background:#ecfeff;color:#0891b2;"><i class="fas fa-infinity"></i></div>



              <div class="sopt-txt">



                <div class="sopt-lbl">بدون آخر موعد</div>



                <div class="sopt-desc">يبقى الامتحان مفتوحاً بعد تاريخ البداية حتى تقوم بإغلاقه أو تعديله لاحقاً</div>



              </div>



              <label class="tgl" onclick="event.stopPropagation()"><input type="checkbox" id="fNoDeadline" onchange="toggleNoDeadline()"><span class="tgl-s"></span></label>



            </div>



            <div class="sopt-row" onclick="document.getElementById('fEndDateMode').click()">



              <div class="sopt-ico" style="background:#dbeafe;color:#2563eb;"><i class="fas fa-calendar-day"></i></div>



              <div class="sopt-txt">



                <div class="sopt-lbl">إغلاق بنهاية اليوم</div>



                <div class="sopt-desc">بدلاً من اختيار ساعة، يظل الامتحان متاحاً حتى 11:59 مساءً في التاريخ المحدد</div>



              </div>



              <label class="tgl" onclick="event.stopPropagation()"><input type="checkbox" id="fEndDateMode" onchange="toggleEndDateMode()"><span class="tgl-s"></span></label>



            </div>



            <div class="quick-presets">



              <button type="button" class="quick-btn" onclick="applyDuePreset(0)">اليوم</button>



              <button type="button" class="quick-btn" onclick="applyDuePreset(1)">غداً</button>



              <button type="button" class="quick-btn" onclick="applyDuePreset(3)">3 أيام</button>



              <button type="button" class="quick-btn" onclick="applyDuePreset(7)">أسبوع</button>



              <button type="button" class="quick-btn" onclick="applyDuePreset(14)">أسبوعان</button>



            </div>



            <div class="sopt-row" onclick="document.getElementById('fTimerOn').click()">



              <div class="sopt-ico" style="background:#fef3c7;color:#d97706;"><i class="fas fa-stopwatch"></i></div>



              <div class="sopt-txt">



                <div class="sopt-lbl">وقت محدد للإجابة</div>



                <div class="sopt-desc">عداد تنازلي يبدأ عند فتح الطفل للتاسك</div>



              </div>



              <label class="tgl" onclick="event.stopPropagation()"><input type="checkbox" id="fTimerOn" onchange="document.getElementById('timerRow').style.display=this.checked?'':'none'"><span class="tgl-s"></span></label>



            </div>



            <div id="timerRow" style="display:none;" class="frow" style="margin-top:10px;">



              <div class="fg"><label class="flbl">المدة (دقيقة) <span class="req">*</span></label><input id="fTimerMin" type="number" class="fi" min="1" max="180" placeholder="30"></div>



              <div class="fg"><label class="flbl">عند انتهاء الوقت</label><select id="fTimerBeh" class="fs"><option value="submit">إرسال تلقائي</option><option value="lock">تأمين بدون إرسال</option></select></div>



            </div>



          </div>



        </div>







        <!-- Card: Options -->



          </div>



          <div class="step-stack">



        <div class="scard2">



          <div class="scard2-hdr"><i class="fas fa-sliders-h"></i> خيارات</div>



          <div class="scard2-body" style="padding-bottom:4px;">



            <div class="hint-box" style="margin-bottom:14px;">



              <i class="fas fa-hand-pointer"></i>



              <div>



                <strong>ما يراه الطفل</strong>



                <span>اختر الخيارات التي تجعل التاسك أوضح: نتيجة فورية، مراجعة الإجابات، أو إظهار الحلول بعد الإنهاء.</span>



              </div>



            </div>



            <div class="sopt-row" onclick="document.getElementById('fShowAns').click()">



              <div class="sopt-ico" style="background:#cffafe;color:#0891b2;"><i class="fas fa-eye"></i></div>



              <div class="sopt-txt"><div class="sopt-lbl">إظهار الإجابات المفصلة</div><div class="sopt-desc">السماح للطفل بمعرفة إجاباته الصحيحة والخاطئة</div></div>



              <label class="tgl" onclick="event.stopPropagation()"><input type="checkbox" id="fShowAns"><span class="tgl-s"></span></label>



            </div>



            <div class="sopt-row" onclick="document.getElementById('fShowRes').click()">



              <div class="sopt-ico" style="background:#d1fae5;color:#059669;"><i class="fas fa-check-circle"></i></div>



              <div class="sopt-txt"><div class="sopt-lbl">إظهار النتيجة فور الانتهاء</div><div class="sopt-desc">يرى الطفل درجته مباشرةً بعد التسليم</div></div>



              <label class="tgl" onclick="event.stopPropagation()"><input type="checkbox" id="fShowRes" checked><span class="tgl-s"></span></label>



            </div>



            <div class="sopt-row" onclick="document.getElementById('fShuffle').click()">



              <div class="sopt-ico" style="background:var(--brand-bg);color:var(--brand);"><i class="fas fa-random"></i></div>



              <div class="sopt-txt"><div class="sopt-lbl">خلط ترتيب الأسئلة</div><div class="sopt-desc">ترتيب عشوائي مختلف لكل طفل</div></div>



              <label class="tgl" onclick="event.stopPropagation()"><input type="checkbox" id="fShuffle"><span class="tgl-s"></span></label>



            </div>



            <div class="sopt-row" style="margin-bottom:0;" onclick="document.getElementById('fReview').click()">



              <div class="sopt-ico" style="background:#fef3c7;color:#d97706;"><i class="fas fa-eye"></i></div>



              <div class="sopt-txt"><div class="sopt-lbl">مراجعة الإجابات قبل الإرسال</div><div class="sopt-desc">يستطيع الطفل تغيير إجاباته قبل التسليم النهائي</div></div>



              <label class="tgl" onclick="event.stopPropagation()"><input type="checkbox" id="fReview" checked><span class="tgl-s"></span></label>



            </div>



          </div>



        </div>







          </div>



        </div>



      </div>







      <!-- Step 2 -->



      <div id="sp2" style="display:none;">



        <div class="fsec" style="margin-bottom:0;">



          <div class="fsec-title" style="display:flex;align-items:center;justify-content:space-between;">



            <span><i class="fas fa-question-circle"></i>الأسئلة</span>



            <span style="font-size:.72rem;color:var(--t3);font-weight:500;">اضغط على النوع لكل سؤال لتغييره</span>



          </div>



          <div class="qlist" id="qList"></div>



          <button class="add-q" onclick="addQ()"><i class="fas fa-plus-circle"></i>إضافة سؤال</button>



          <div class="deg-sum"><span class="deg-sum-l"><i class="fas fa-star" style="margin-left:4px;"></i>إجمالي الدرجات</span><span class="deg-sum-v" id="degTotal">0 <small style="font-size:.7rem;font-weight:500;">درجة</small></span></div>



        </div>



      </div>







      <!-- Step 3 -->



      <div id="sp3" style="display:none;">



        <div class="fsec" style="margin-bottom:0;">



          <div class="fsec-title"><i class="fas fa-star"></i>مستويات الكوبونات</div>



          <p style="font-size:.77rem;color:var(--t3);margin-bottom:13px;">حدد كم كوبون يحصل عليه الطفل بناءً على نسبة إجاباته الصحيحة.<br>الدرجة الكلية: <strong id="s3deg">0</strong> درجة.</p>



          <div class="ctiers" id="ctierList"></div>



          <button class="add-opt" style="margin-top:9px;width:100%;justify-content:center;" onclick="addMilestone()"><i class="fas fa-plus"></i>إضافة مستوى</button>



        </div>



      </div>







    </div>



    <div class="mfoot">



      <div style="margin-left:auto;font-size:.73rem;color:var(--t3);">الخطوة <strong id="stepNum">1</strong> من 3</div>



      <button class="btn btn-g" id="prevBtn" onclick="prevStep()" style="display:none;"><i class="fas fa-chevron-right"></i> السابق</button>



      <button class="btn btn-g" id="draftBtn" onclick="saveDraft()"><i class="fas fa-save"></i> Draft</button>



      <button class="btn btn-p" id="nextBtn" onclick="nextStep()">التالي <i class="fas fa-chevron-left"></i></button>



      <button class="btn btn-p" id="pubBtn" onclick="publishTask()" style="display:none;"><i class="fas fa-paper-plane"></i> نشر</button>



    </div>



  </div>



</div>







<!-- ══ DETAIL MODAL ══════════════════════════════════════════════ -->



<div class="overlay fullscreen" id="detailOv">



  <div class="modal wide">



    <div class="mhdr">



      <div class="mhdr-ico"><i class="fas fa-eye"></i></div>



      <div><div class="mhdr-title" id="dTitle">تفاصيل التاسك</div><div class="mhdr-sub" id="dSub"></div></div>



      <div class="mclose" onclick="closeDetail()"><i class="fas fa-times"></i></div>



    </div>



    <div class="mbody" id="dBody" style="flex:1; overflow-y:auto; overflow-x:hidden;"></div>



    <div class="mfoot" id="dFoot"></div>



  </div>



</div>







<!-- ══ CONFIRM DELETE ════════════════════════════════════════════ -->



<div class="overlay" id="confOv">



  <div class="modal" style="max-width:440px;">



    <div class="conf-body" style="padding-bottom:16px;">



      <div class="conf-ico"><i class="fas fa-trash-alt"></i></div>



      <div class="conf-t">حذف التاسك؟</div>



      <div class="conf-s" id="confSub" style="margin-bottom:14px;">لا يمكن التراجع.</div>



      <div id="confCouponNote" style="display:none;background:var(--cou-bg);border:1px solid #d8b4fe;border-radius:10px;padding:11px 14px;font-size:.8rem;color:var(--t2);text-align:right;line-height:1.7;">



        <strong style="color:var(--cou);display:block;margin-bottom:4px;"><i class="fas fa-star"></i> كوبونات الأطفال</strong>



        <span id="confCouponDetail"></span>



      </div>



    </div>



    <div class="mfoot" style="flex-direction:column;gap:8px;padding:14px 20px;">



      <button class="btn btn-dg" style="width:100%;justify-content:center;background:var(--err-bg);" onclick="doDelete(1)">



        <i class="fas fa-trash-alt"></i> حذف وسحب الكوبونات من الأطفال



      </button>



      <button class="btn" style="width:100%;justify-content:center;background:var(--warn-bg);color:var(--warn);border-color:#fcd34d;" onclick="doDelete(0)">



        <i class="fas fa-star"></i> حذف والاحتفاظ بالكوبونات



      </button>



      <button class="btn btn-g" style="width:100%;justify-content:center;" onclick="closeConf()">إلغاء</button>



    </div>



  </div>



</div>







<!-- ══ DELETE SUBMISSION CONFIRM ═════════════════════════════════ -->



<div class="overlay" id="delSubConfOv">



  <div class="modal narrow">



    <div class="conf-body">



      <div class="conf-ico"><i class="fas fa-trash-alt"></i></div>



      <div class="conf-t">حذف الإجابة؟</div>



      <div class="conf-s" id="delSubMsg">لا يمكن التراجع.</div>



    </div>



    <div class="mfoot" style="justify-content:center;gap:10px;">



      <button class="btn btn-g" onclick="closeOv('delSubConfOv')">إلغاء</button>



      <button class="btn btn-dg" onclick="doDeleteSubConfirmed()"><i class="fas fa-trash-alt"></i> حذف</button>



    </div>



  </div>



</div>







<!-- ══ TASKS OVERVIEW & EXPORT MODAL ══════════════════════════════ -->
<div class="overlay fullscreen" id="overviewOv">
  <style>
    #overviewOv .modal {
      font-family: 'Baloo Bhaijaan 2', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif !important;
    }
    .ov-table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0;
      direction: rtl;
      font-size: .87rem;
      margin-bottom: 25px;
    }
    .ov-table th {
      background: var(--brand-bg) !important;
      color: var(--brand) !important;
      font-weight: 700;
      padding: 12px 10px;
      border-bottom: 2px solid var(--brand-l);
      text-align: right;
      white-space: nowrap;
      position: sticky;
      top: 0;
      z-index: 10;
    }
    .ov-table td {
      padding: 10px;
      border-bottom: 1px solid var(--bdr);
      color: var(--t1);
      vertical-align: middle;
      text-align: right;
    }
    .ov-table tr:hover td {
      background: var(--bg-hover) !important;
    }
    .ov-table tr:nth-child(even) td {
      background: var(--bg);
    }
    .ov-cell-answered {
      color: #10b981;
      font-weight: bold;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .ov-cell-unanswered {
      color: var(--t3);
      font-style: italic;
      display: inline-flex;
      align-items: center;
      gap: 4px;
    }
    .ov-grade-badge {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      padding: 2px 6px;
      border-radius: 4px;
      font-size: .75rem;
      font-weight: bold;
    }
    .ov-grade-pass {
      background: #e6fcf5;
      color: #0ca678;
    }
    .ov-grade-fail {
      background: #fff5f5;
      color: #fa5252;
    }
    .ov-time-text {
      font-size: .7rem;
      color: var(--t3);
      margin-top: 3px;
      display: block;
    }
    .ov-class-badge {
      display: inline-block;
      padding: 2px 6px;
      border-radius: 4px;
      font-size: .75rem;
      background: var(--bg-card);
      border: 1px solid var(--bdr);
      color: var(--t2);
    }
    /* Premium unified export buttons */
    .btn-export {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 8px 16px;
      border-radius: var(--r-md);
      font-size: .83rem;
      font-weight: 700;
      cursor: pointer;
      transition: all var(--fast);
      border: 1.5px solid transparent;
      text-decoration: none;
      min-height: 38px;
    }
    .btn-export.btn-msg {
      background: rgba(16, 185, 129, 0.08);
      color: #10b981;
      border-color: rgba(16, 185, 129, 0.2);
    }
    .btn-export.btn-msg:hover {
      background: #10b981;
      color: #fff;
      border-color: #10b981;
      transform: translateY(-1px);
    }
    .btn-export.btn-csv {
      background: rgba(59, 130, 246, 0.08);
      color: #3b82f6;
      border-color: rgba(59, 130, 246, 0.2);
    }
    .btn-export.btn-csv:hover {
      background: #3b82f6;
      color: #fff;
      border-color: #3b82f6;
      transform: translateY(-1px);
    }
    .btn-export.btn-pdf {
      background: rgba(236, 72, 153, 0.08);
      color: #ec4899;
      border-color: rgba(236, 72, 153, 0.2);
    }
    .btn-export.btn-pdf:hover {
      background: #ec4899;
      color: #fff;
      border-color: #ec4899;
      transform: translateY(-1px);
    }
    .btn-export.btn-img {
      background: rgba(139, 92, 246, 0.08);
      color: #8b5cf6;
      border-color: rgba(139, 92, 246, 0.2);
    }
    .btn-export.btn-img:hover {
      background: #8b5cf6;
      color: #fff;
      border-color: #8b5cf6;
      transform: translateY(-1px);
    }
    
    /* Responsive overrides for tasks overview */
    @media (max-width: 768px) {
      #overviewOv .mhdr {
        padding: 12px 16px !important;
      }
      #overviewOv .mhdr-title {
        font-size: 1.1rem !important;
      }
      #overviewOv .mhdr-sub {
        font-size: 0.75rem !important;
        line-height: 1.3;
      }
      #overviewOv .mbody {
        padding: 12px !important;
        gap: 12px !important;
      }
      .overview-filters-box {
        grid-template-columns: 1fr !important;
        gap: 12px !important;
        padding: 12px !important;
        border-radius: 8px !important;
      }
      #ovClassesList {
        max-height: 80px !important;
      }
      .ov-table {
        font-size: 0.75rem !important;
      }
      .ov-table th {
        padding: 8px 6px !important;
      }
      .ov-table td {
        padding: 6px 6px !important;
      }
      .ov-grade-badge {
        padding: 1px 4px !important;
        font-size: 0.68rem !important;
      }
      .ov-class-badge {
        padding: 1px 4px !important;
        font-size: 0.68rem !important;
      }
      .ov-time-text {
        font-size: 0.65rem !important;
      }
      #overviewOv .mfoot {
        flex-direction: column !important;
        align-items: stretch !important;
        gap: 10px !important;
        padding: 12px !important;
      }
      .ov-mfoot-btns {
        display: grid !important;
        grid-template-columns: repeat(2, 1fr) !important;
        gap: 8px !important;
        width: 100% !important;
      }
      .ov-mfoot-btns .btn,
      .ov-mfoot-btns .btn-export {
        width: 100% !important;
        justify-content: center !important;
        padding: 8px 10px !important;
        font-size: 0.78rem !important;
        min-height: 36px !important;
      }
      .ov-mfoot-btns .btn-g {
        grid-column: span 2 !important;
        order: 99 !important;
      }
    }
  </style>
  <div class="modal wide" style="display:flex; flex-direction:column;">
    <div class="mhdr">
      <div class="mhdr-ico" style="background:var(--brand-bg); color:var(--brand);"><i class="fas fa-file-export"></i></div>
      <div>
        <div class="mhdr-title">تصدير نظرة عامة للتاسكات</div>
        <div class="mhdr-sub">تصدير نتائج وحلول التاسكات بصيغ مختلفة (صورة، PDF، CSV، أو نسخ رسالة)</div>
      </div>
      <div class="mclose" onclick="closeOv('overviewOv')"><i class="fas fa-times"></i></div>
    </div>
    
    <div class="mbody" style="flex:1; overflow-y:auto; padding:20px; display:flex; flex-direction:column; gap:20px;">
      <!-- Filters and Options Box -->
      <div class="overview-filters-box" style="display:grid; grid-template-columns:repeat(auto-fit, minmax(250px, 1fr)); gap:15px; background:var(--bg); border:1px solid var(--bdr); padding:15px; border-radius:12px;">
        <!-- Class Filter -->
        <div style="display:flex; flex-direction:column; gap:8px;">
          <label style="font-weight:700; font-size:.9rem; color:var(--t1);"><i class="fas fa-users"></i> الفصول المستهدفة:</label>
          <div id="ovClassesList" style="display:flex; flex-wrap:wrap; gap:8px; max-height:100px; overflow-y:auto; padding:5px; border:1px solid var(--bdr); border-radius:8px; background:#fff;">
            <!-- Loaded dynamically in JS -->
          </div>
        </div>
        
        <!-- Answer Status Filter -->
        <div style="display:flex; flex-direction:column; gap:8px;">
          <label style="font-weight:700; font-size:.9rem; color:var(--t1);">حالة الحل:</label>
          <select id="ovAnswerStatus" onchange="renderOverviewTable()" style="padding:8px 12px; border:1px solid var(--bdr); border-radius:8px; background:#fff; font-size:.87rem; outline:none; cursor:pointer;">
            <option value="both">الكل (الذين أجابوا والذين لم يجيبوا)</option>
            <option value="answered">الذين أجابوا فقط (على الأقل تاسك واحد)</option>
            <option value="unanswered">الذين لم يجيبوا على أي تاسك</option>
            <option value="missing">الذين لديهم تاسكات لم يتم حلها</option>
          </select>
        </div>
        
        <!-- Search and Toggles -->
        <div style="display:flex; flex-direction:column; gap:8px;">
          <label style="font-weight:700; font-size:.9rem; color:var(--t1);"><i class="fas fa-search"></i> بحث بالاسم:</label>
          <input type="text" id="ovStudentSearch" oninput="renderOverviewTable()" placeholder="ابحث بالاسم (إدخال ذكي)..." style="padding:8px 12px; border:1px solid var(--bdr); border-radius:8px; font-size:.87rem; outline:none;">
        </div>

        <!-- Export Page Orientation -->
        <div style="display:flex; flex-direction:column; gap:8px;">
          <label style="font-weight:700; font-size:.9rem; color:var(--t1);"><i class="fas fa-file-pdf"></i> اتجاه الصفحة للتصدير:</label>
          <select id="ovOrientation" style="padding:8px 12px; border:1px solid var(--bdr); border-radius:8px; background:#fff; font-size:.87rem; outline:none; cursor:pointer;">
            <option value="landscape" selected>عرضي (Landscape)</option>
            <option value="portrait">طولي (Portrait)</option>
          </select>
        </div>

        <!-- Toggle Checkboxes -->
        <div style="display:flex; flex-direction:column; justify-content:center; gap:10px;">
          <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:.87rem; color:var(--t1);">
            <input type="checkbox" id="ovShowGrades" onchange="renderOverviewTable()" checked style="accent-color:var(--brand); width:16px; height:16px; cursor:pointer;">
            <span>عرض الدرجات والتقييم</span>
          </label>
          <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:.87rem; color:var(--t1);">
            <input type="checkbox" id="ovShowTime" onchange="renderOverviewTable()" checked style="accent-color:var(--brand); width:16px; height:16px; cursor:pointer;">
            <span>عرض وقت تسليم التاسك</span>
          </label>
          <label style="display:flex; align-items:center; gap:8px; cursor:pointer; font-size:.87rem; color:var(--t1);">
            <input type="checkbox" id="ovOnlyResList" onchange="renderOverviewTable()" style="accent-color:var(--brand); width:16px; height:16px; cursor:pointer;">
            <span>عرض قائمة المجيبين لكل تاسك فقط</span>
          </label>
        </div>
      </div>
      
      <!-- Table Wrapper -->
      <div style="flex:1; border:1px solid var(--bdr); border-radius:12px; background:#fff; padding:15px; overflow:auto; position:relative; min-height:250px;">
        <div id="ovTableContainer">
          <!-- Dynamically Rendered Table -->
        </div>
      </div>
    </div>
    
    <div class="mfoot" style="justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
      <div style="font-size:.8rem; color:var(--t2);" id="ovStatsText">جاري معالجة البيانات...</div>
      <div class="ov-mfoot-btns" style="display:flex; gap:8px; flex-wrap:wrap; align-items:center;">
        <button class="btn btn-g" onclick="closeOv('overviewOv')" style="order:99;">إلغاء</button>
        <button class="btn-export btn-msg" onclick="copyOverviewMessage()"><i class="fas fa-copy"></i> نسخ كرسالة</button>
        <button class="btn-export btn-csv" onclick="exportOverviewCSV()"><i class="fas fa-file-csv"></i> CSV</button>
        <button class="btn-export btn-pdf" onclick="exportOverviewPDF()"><i class="fas fa-file-pdf"></i> PDF</button>
        <button class="btn-export btn-img" onclick="exportOverviewImage()"><i class="fas fa-file-image"></i> صورة</button>
      </div>
    </div>
  </div>
</div>

<div class="tc" id="tc"></div>







<!-- ══ SCRIPT ════════════════════════════════════════════════════ -->



<script>



// ─── PHP config ────────────────────────────────────────────────



const CFG = {



  uncleId:     <?php echo (int)$uncleId; ?>,



  uncleName:   <?php echo json_encode($uncleName); ?>,



  role:        <?php echo json_encode($uncleRole); ?>,



  churchType:  <?php echo json_encode($churchType); ?>,



  isYouth:     <?php echo $isYouth?'true':'false'; ?>,



  activeClass: <?php echo json_encode($activeClass); ?>



};



const PEOPLE  = CFG.isYouth ? 'الشباب' : 'الأطفال';



const LETTERS = ['أ','ب','ج','د','هـ'];







// Resolve api.php path relative to this page's URL depth



const API = (()=>{



  const parts = window.location.pathname.split('/').filter(Boolean);



  return '../'.repeat(parts.length - 1) + 'api.php';



})();







// ─── State ─────────────────────────────────────────────────────



let tasks      = [];



let allClasses = [];



let classStuCache = {};



let curFilter  = 'all';



let editId     = null;



let delId      = null;



let curStep    = 1;



let qCnt       = 0;







// ─── Boot ──────────────────────────────────────────────────────



document.addEventListener('DOMContentLoaded', async () => {



  setDefaultDates();



  await loadClasses();



  await loadTasks();

  // If inside an iframe, configure postMessage integration
  if (window.self !== window.top) {
      document.body.classList.add('body-iframe-view');
      const backBtn = document.querySelector('.tb-back');
      if (backBtn) {
          backBtn.removeAttribute('href');
          backBtn.style.cursor = 'pointer';
          backBtn.onclick = (e) => {
              e.preventDefault();
              window.parent.postMessage({ action: 'closeTasksModal' }, '*');
          };
      }
      const backToDashLink = document.querySelector('a.hero-link');
      if (backToDashLink) {
          backToDashLink.style.display = 'none';
      }
  }

  // Auto-open specific task details if passed in query param
  const urlParams = new URLSearchParams(window.location.search);
  const taskId = urlParams.get('taskId');
  if (taskId) {
      setTimeout(() => {
          openDetail(parseInt(taskId, 10));
      }, 300);
  }
  const action = urlParams.get('action');
  if (action === 'create') {
      setTimeout(() => {
          openCreate();
      }, 300);
  }

  overlayOnBg();



  document.addEventListener('keydown', e => {



    if (e.key==='Escape') ['confOv','detailOv','createOv'].forEach(id => {



      if (document.getElementById(id).classList.contains('open')) closeOv(id);



    });



  });



});







// ─── API helper ────────────────────────────────────────────────



async function api(action, extra={}) {



  const fd = new FormData();



  fd.append('action', action);



  Object.entries(extra).forEach(([k,v]) => { if (v !== null && v !== undefined) fd.append(k, v); });



  const r = await fetch(API, {method:'POST', body:fd, credentials:'include'});



  if (!r.ok) throw new Error('HTTP '+r.status);



  return r.json();



}







// ─── Load classes ───────────────────────────────────────────────



async function loadClasses() {



  try {



    const d = await api('getChurchClasses');



    if (d.success && d.classes) allClasses = d.classes.filter(c=>c.is_active!=0);



  } catch(e) { console.warn('loadClasses', e); }



  buildClassSel();



}



function buildClassSel() {



  const c = document.getElementById('fClassContainer');



  if (!c) return;



  c.innerHTML = '';



  



  // Create "All Classes" checkbox



  const allWrap = document.createElement('label');



  allWrap.style = "display:flex; align-items:center; gap:8px; cursor:pointer; font-size:.87rem; color:var(--t1); margin-bottom: 4px; padding: 4px 6px; border-radius: 4px; transition: background 0.2s;";



  allWrap.onmouseover = function() { this.style.background = 'var(--bg)'; };



  allWrap.onmouseout = function() { this.style.background = 'transparent'; };



  



  const allChk = document.createElement('input');



  allChk.type = 'checkbox';



  allChk.id = 'class_all';



  allChk.value = 'كل الفصول';



  allChk.dataset.id = '0';



  allChk.style.accentColor = 'var(--brand)';



  allChk.style.width = '16px';



  allChk.style.height = '16px';



  allChk.style.cursor = 'pointer';



  allChk.onchange = onClassCheckboxChange;



  



  if (CFG.activeClass === 'كل الفصول') {



    allChk.checked = true;



  }



  



  allWrap.appendChild(allChk);



  allWrap.appendChild(document.createTextNode(' كل الفصول'));



  c.appendChild(allWrap);



  



  // Create individual class checkboxes



  allClasses.forEach(cl => {



    const wrap = document.createElement('label');



    wrap.style = "display:flex; align-items:center; gap:8px; cursor:pointer; font-size:.87rem; color:var(--t1); margin-bottom: 4px; padding: 4px 6px; border-radius: 4px; transition: background 0.2s;";



    wrap.onmouseover = function() { this.style.background = 'var(--bg)'; };



    wrap.onmouseout = function() { this.style.background = 'transparent'; };



    



    const chk = document.createElement('input');



    chk.type = 'checkbox';



    chk.className = 'class-checkbox';



    chk.value = cl.arabic_name;



    chk.dataset.id = cl.id;



    chk.style.accentColor = 'var(--brand)';



    chk.style.width = '16px';



    chk.style.height = '16px';



    chk.style.cursor = 'pointer';



    chk.onchange = onClassCheckboxChange;



    



    if (CFG.activeClass && cl.arabic_name === CFG.activeClass) {



      chk.checked = true;



      allChk.checked = false; // ensure all classes deselects



    }



    



    wrap.appendChild(chk);



    wrap.appendChild(document.createTextNode(' ' + cl.arabic_name));



    c.appendChild(wrap);



  });



  



  // Default fallback check



  const checkedAny = Array.from(c.querySelectorAll('input[type="checkbox"]:checked')).length > 0;



  if (!checkedAny) {



    allChk.checked = true;



  }



}







async function onClassCheckboxChange(e) {



  const target = e.target;



  if (target.id === 'class_all' && target.checked) {



    // Deselect all other checkboxes



    document.querySelectorAll('.class-checkbox').forEach(cb => cb.checked = false);



  } else if (target.className === 'class-checkbox' && target.checked) {



    // Deselect "All Classes"



    const all = document.getElementById('class_all');



    if (all) all.checked = false;



  }



  



  // Ensure at least one is checked if possible



  const checked = document.querySelectorAll('#fClassContainer input[type="checkbox"]:checked');



  if (checked.length === 0) {



    const all = document.getElementById('class_all');



    if (all) all.checked = true;



  }



  



  await onClassChange();



}







// ─── Load tasks ─────────────────────────────────────────────────



async function loadTasks() {

  try {

    const extra = {};

    if (CFG.activeClass) extra.class_name = CFG.activeClass;

    const d = await api('getTasks', extra);

    if (d.success) tasks = d.tasks || [];

    else showToast(d.message||'فشل تحميل التاسكات', 'err');

  } catch(e) { showToast('خطأ في الاتصال', 'err'); }

  try {
    await loadStudents('كل الفصول');
  } catch(e) {
    console.error('Failed to load students:', e);
  }

  renderGrid();

  updateStats();

}







// ─── Load students ──────────────────────────────────────────────



async function loadStudents(cls) {



  if (classStuCache[cls]) return classStuCache[cls];



  try {



    const d = await api('getData');



    if (d.success) {



      const all = d.data || d.allStudents || [];



      classStuCache['كل الفصول'] = [];



      all.forEach(s => {



        const c = s['الفصل'] || s.class || '';



        const student = {id: s._studentId, name: s['الاسم']||s.name||'', photo: s['صورة']||s.image_url||s.photo||''};



        if (!classStuCache[c]) classStuCache[c] = [];



        classStuCache[c].push(student);



        classStuCache['كل الفصول'].push(student);



      });



    }



  } catch(e) {}



  return classStuCache[cls] || [];



}

function getStudentAvatarHtml(photo, name, size = '28px') {
    if (photo) {
        return `<img src="${esc(photo)}" style="width:${size};height:${size};border-radius:50%;object-fit:cover;flex-shrink:0;border:1px solid var(--bdr);" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';" />` +
               `<span style="display:none;width:${size};height:${size};border-radius:50%;background:var(--brand-bg);color:var(--brand);align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;flex-shrink:0;"><i class="fas fa-user"></i></span>`;
    }
    return `<span style="display:inline-flex;width:${size};height:${size};border-radius:50%;background:var(--brand-bg);color:var(--brand);align-items:center;justify-content:center;font-size:0.75rem;font-weight:700;flex-shrink:0;"><i class="fas fa-user"></i></span>`;
}







// ─── Render tasks grid ──────────────────────────────────────────



function statusOf(t) {
  if (t.status==='draft') return {key:'draft',cls:'s-draft',label:'Draft',acc:'warn'};
  return {key:'published',cls:'s-published',label:'منشور',acc:'ok'};
}

function getCustomFilters() {
  const customFilters = new Set();
  tasks.forEach(t => {
    if (t.group_name) {
      t.group_name.split(',').forEach(g => {
        const trimmed = g.trim();
        if (trimmed) customFilters.add(trimmed);
      });
    }
  });
  return Array.from(customFilters);
}

function renderFilterTabs() {
  const container = document.getElementById('filterTabsContainer');
  if (!container) return;

  let html = `<div class="ftab ${curFilter === 'all' ? 'active' : ''}" onclick="setFilter('all',this)">الكل</div>`;
  html += `<div class="ftab ${curFilter === 'draft' ? 'active' : ''}" onclick="setFilter('draft',this)">Draft</div>`;

  const customFilters = getCustomFilters();
  customFilters.forEach(f => {
    html += `<div class="ftab ${curFilter === 'custom_' + f ? 'active' : ''}" onclick="setFilter('custom_${f}',this)">${f}</div>`;
  });

  container.innerHTML = html;
}

window.selectedClassifications = [];

function renderActiveTags() {
  const container = document.getElementById('activeClassificationsContainer');
  if (!container) return;
  if (window.selectedClassifications.length === 0) {
    container.innerHTML = '<span style="font-size:0.8rem; color:var(--t3); font-style:italic;">لا توجد تصنيفات محددة</span>';
    return;
  }
  let html = '';
  window.selectedClassifications.forEach(tag => {
    html += `
      <span class="badge" style="display:inline-flex; align-items:center; gap:6px; background:var(--brand-bg); border:1px solid var(--brand-l); padding:4px 10px; border-radius:14px; font-size:0.8rem; color:var(--brand); font-weight:700;">
        ${esc(tag)}
        <i class="fas fa-times" onclick="removeClassification('${esc(tag)}')" style="cursor:pointer; font-size:0.75rem; opacity:0.7;" onmouseover="this.style.opacity='1'" onmouseout="this.style.opacity='0.7'"></i>
      </span>
    `;
  });
  container.innerHTML = html;
}

function populateClassificationSelector() {
  const selector = document.getElementById('classificationSelector');
  if (!selector) return;
  
  selector.innerHTML = '<option value="" disabled selected>— اختر تصنيفاً لتحديده —</option>';
  
  const allFilters = getCustomFilters();
  allFilters.forEach(f => {
    if (!window.selectedClassifications.includes(f)) {
      selector.innerHTML += `<option value="${esc(f)}">${esc(f)}</option>`;
    }
  });
}

function handleClassificationSelect(el) {
  const val = el.value.trim();
  if (!val) return;
  if (!window.selectedClassifications.includes(val)) {
    window.selectedClassifications.push(val);
    renderActiveTags();
    populateClassificationSelector();
  }
  el.value = '';
}

function addNewClassificationPrompt() {
  const name = prompt("أدخل اسم التصنيف الجديد:");
  if (!name) return;
  const trimmed = name.trim();
  if (!trimmed) return;
  if (!window.selectedClassifications.includes(trimmed)) {
    window.selectedClassifications.push(trimmed);
    renderActiveTags();
    populateClassificationSelector();
  }
}

function removeClassification(tag) {
  window.selectedClassifications = window.selectedClassifications.filter(x => x !== tag);
  renderActiveTags();
  populateClassificationSelector();
}

function setFilter(f, el) {



  curFilter=f;



  document.querySelectorAll('.ftab').forEach(t=>t.classList.remove('active'));



  el.classList.add('active');



  renderGrid();



}



function renderOverlappingAvatars(students, size = '26px') {
  if (!students || !students.length) {
    return `<span style="font-size:0.7rem; color:var(--t4); font-style:italic;">لا يوجد</span>`;
  }
  const maxShow = 3;
  const visible = students.slice(0, maxShow);
  const extraCount = students.length - maxShow;
  
  let html = `<div style="display:flex; align-items:center; flex-wrap:nowrap; direction:rtl; padding-right:4px;">`;
  visible.forEach((s, idx) => {
    const avatarHtml = getStudentAvatarHtml(s.photo, s.name, size);
    html += `
      <div class="uncle-avatar-wrap" style="margin-left:-8px; z-index:${maxShow - idx}; position:relative;">
        ${avatarHtml}
        <div class="uncle-tooltip" style="bottom:100%; left:50%; transform:translateX(-50%); font-size:0.65rem;">${esc(s.name)}</div>
      </div>
    `;
  });
  
  if (extraCount > 0) {
    html += `
      <div style="margin-left:-8px; z-index:0; width:${size}; height:${size}; border-radius:50%; background:var(--bg3); border:2px solid var(--bdr); color:var(--t2); display:flex; align-items:center; justify-content:center; font-size:0.65rem; font-weight:800; font-family:'Cairo'; flex-shrink:0;">
        +${extraCount}
      </div>
    `;
  }
  
  html += `</div>`;
  return html;
}

function renderGrid() {



  const g = document.getElementById('tGrid');



  // Update dynamic filter tabs view
  renderFilterTabs();

  let list = [];
  if (curFilter === 'all') {
    list = tasks.filter(t => t.status !== 'draft');
  } else if (curFilter === 'draft') {
    list = tasks.filter(t => t.status === 'draft');
  } else if (curFilter.startsWith('custom_')) {
    const fName = curFilter.substring(7);
    list = tasks.filter(t => {
      if (!t.group_name) return false;
      return t.group_name.split(',').map(x => x.trim()).includes(fName);
    });
  } else {
    list = tasks.filter(t => statusOf(t).key === curFilter);
  }



  if (!list.length) {



    g.innerHTML = `<div class="empty"><div class="empty-ico"><i class="fas fa-clipboard-list"></i></div><div class="empty-t">لا توجد تاسكات</div><div class="empty-s">اضغط "تاسك جديد" لإنشاء أول اختبار</div><button class="btn btn-p" onclick="openCreate()"><i class="fas fa-plus"></i> إنشاء تاسك</button></div>`;



    return;



  }



  g.innerHTML = list.map((t, idx) => {
    const si   = statusOf(t);
    const qs   = (t.questions||[]).length;
    const subs = (t.submissions||[]).length;
    const tc   = (t.submissions||[]).reduce((a,s)=>a+(parseInt(s.coupons_awarded)||0),0);
    const pendingOpen = (t.submissions||[]).filter(s=>{
      const hasPending = s.pending_open_grading ?? s.has_open_pending;
      return hasPending;
    }).length;

    const clsName = t.class_name || 'كل الفصول';
    const studentsInClass = classStuCache[clsName] || [];
    const answeredIds = (t.submissions || []).map(s => parseInt(s.student_id));
    const answeredStudents = studentsInClass.filter(s => answeredIds.includes(parseInt(s.id)));
    const notAnsweredStudents = studentsInClass.filter(s => !answeredIds.includes(parseInt(s.id)));

    const answeredAvatarsHtml = renderOverlappingAvatars(answeredStudents, '26px');
    const notAnsweredAvatarsHtml = renderOverlappingAvatars(notAnsweredStudents, '26px');

    const totalToAnswer = studentsInClass.length;
    const progressPercent = totalToAnswer > 0 ? Math.round((answeredStudents.length / totalToAnswer) * 100) : 0;

    return `<div class="tcard" onclick="openDetail(${t.id})" style="animation-delay:${idx*40}ms; border-radius: var(--r-lg); overflow: hidden; background: var(--bg-card); border: 1.5px solid var(--bdr); transition: all 0.2s; box-shadow: var(--shadow-sm); cursor: pointer;" onmouseover="this.style.transform='translateY(-2px)'; this.style.borderColor='var(--brand-l)';" onmouseout="this.style.transform='none'; this.style.borderColor='var(--bdr)';">
      
      <div style="padding: 16px; display: flex; flex-direction: column; gap: 12px;">
        
        <!-- Header row -->
        <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
          <div style="font-size: 0.95rem; font-weight: 800; color: var(--t1); line-height: 1.4; flex: 1; text-align: right;">${esc(t.title)}</div>
          <div style="display: flex; align-items: center; gap: 6px; flex-shrink: 0;">
            ${si.key === 'draft' ? `<span style="background:var(--warn-bg); color:var(--warn); font-size:0.7rem; font-weight:700; padding:2px 8px; border-radius:4px; border:1px solid rgba(245,158,11,0.25);">Draft</span>` : ''}
            ${pendingOpen ? `<span style="background:var(--brand-bg); color:var(--brand); font-size:0.7rem; font-weight:700; padding:2px 8px; border-radius:4px; border:1px solid rgba(124,58,237,0.2);"><i class="fas fa-pen-nib"></i> ${pendingOpen} تصحيح</span>` : ''}
          </div>
        </div>

        <!-- Sub/Meta details -->
        <div style="display: flex; flex-wrap: wrap; gap: 10px; font-size: 0.72rem; color: var(--t3); border-bottom: 1px dashed var(--bdr); padding-bottom: 10px; direction: rtl;">
          <span style="display: inline-flex; align-items: center; gap: 4px;"><i class="fas fa-users" style="color:var(--brand);"></i> ${esc(t.class_name || 'كل الفصول')}</span>
          <span style="display: inline-flex; align-items: center; gap: 4px;"><i class="fas fa-flag-checkered"></i> ${parseInt(t.no_deadline||0)?'بدون موعد':fmtDate(t.end_date)}</span>
          <span style="display: inline-flex; align-items: center; gap: 4px;"><i class="fas fa-question-circle"></i> ${qs} سؤال (${t.total_degree||0} درجة)</span>
        </div>

        <!-- Overlapping Kids Avatars row -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; align-items: center; background: var(--bg3); padding: 8px 12px; border-radius: 8px; border: 1px solid var(--bdr); direction: rtl;">
          <div>
            <div style="font-size: 0.68rem; font-weight: 700; color: var(--t2); margin-bottom: 4px; display: flex; justify-content: space-between; align-items: center; direction: rtl;">
              <span>أجابوا (${answeredStudents.length})</span>
              <span style="color: var(--brand); font-size: 0.65rem;">${progressPercent}%</span>
            </div>
            ${answeredAvatarsHtml}
          </div>
          <div style="border-right: 1px solid var(--bdr); padding-right: 12px;">
            <div style="font-size: 0.68rem; font-weight: 700; color: var(--t2); margin-bottom: 4px; text-align: right;">لم يجيبوا (${notAnsweredStudents.length})</div>
            ${notAnsweredAvatarsHtml}
          </div>
        </div>

      </div>

      <!-- Action Footer -->
      <div style="display: flex; align-items: center; justify-content: space-between; background: var(--bg-hover); padding: 10px 16px; border-top: 1px solid var(--bdr); direction: rtl;">
        <!-- Tags or categories -->
        <div style="display: flex; gap: 4px; overflow: hidden; flex: 1; margin-left: 8px; justify-content: flex-start; direction: rtl;">
          ${t.group_name ? t.group_name.split(',').map(g => `<span style="font-size:0.65rem; color:var(--brand); background:var(--brand-bg); border:1.5px solid var(--brand-l); padding:2px 8px; border-radius:10px; font-weight:700; white-space:nowrap;">${esc(g.trim())}</span>`).join('') : '<span style="font-size:0.65rem; color:var(--t4); font-style:italic;">بدون تصنيف</span>'}
        </div>
        <!-- Buttons -->
        <div style="display: flex; gap: 6px; flex-shrink: 0;" onclick="event.stopPropagation()">
          <button class="btn btn-sm" onclick="openDetail(${t.id})" style="padding: 4px 10px; font-size: 0.75rem; font-weight: 700; background: var(--bg); border: 1px solid var(--bdr); color: var(--t1); cursor: pointer; border-radius: 6px; font-family:'Cairo';"><i class="fas fa-eye"></i> عرض</button>
          <button class="btn btn-sm btn-p" onclick="openEdit(${t.id})" style="padding: 4px 10px; font-size: 0.75rem; font-weight: 700; cursor: pointer; border-radius: 6px; font-family:'Cairo';"><i class="fas fa-pen"></i> تعديل</button>
          <button class="btn btn-sm" onclick="openConf(${t.id})" style="padding: 4px 6px; font-size: 0.75rem; font-weight: 700; color: var(--err); background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.25); cursor: pointer; border-radius: 6px; font-family:'Cairo';"><i class="fas fa-trash"></i></button>
        </div>
      </div>

    </div>`;  }).join('');



}



function updateStats() {
  const total = tasks.length;
  const active = tasks.filter(t=>statusOf(t).key==='active').length;
  const upcoming = tasks.filter(t=>statusOf(t).key==='upcoming').length;
  const drafts = tasks.filter(t=>statusOf(t).key==='draft').length;
  const tc = tasks.reduce((a,t)=>a+(t.submissions||[]).reduce((b,s)=>b+(parseInt(s.coupons_awarded)||0),0),0);

  const setVal = (id, val) => {
    const el = document.getElementById(id);
    if (el) el.textContent = val;
  };

  setVal('stTotal', total);
  setVal('stActive', active);
  setVal('stUpcoming', upcoming);
  setVal('stCoupons', tc);

  setVal('stTotalIframe', total);
  
  setVal('stDraftIframe', drafts);
}







// ─── Create / Edit ──────────────────────────────────────────────



function openCreate(taskId=null) {



  editId=taskId; qCnt=0;



  resetForm();



  if (taskId) { const t=tasks.find(x=>x.id==taskId); if(t)fillForm(t); document.getElementById('createTitle').textContent='تعديل التاسك'; }



  else { document.getElementById('createTitle').textContent='إنشاء تاسك جديد'; addQ(); addQ(); }



  initTiers();



  goStep(1);



  openOv('createOv');



}



async function openEdit(id) {



  // Fetch fresh detail so question_type, image_url etc. are included



  try {



    const d = await api('getTaskDetail', {task_id: id});



    if(d.success && d.task) {



      // Merge full question data into tasks cache



      const idx = tasks.findIndex(t=>t.id==id);



      if(idx>-1) tasks[idx] = {...tasks[idx], ...d.task, questions: d.task.questions};



    }



  } catch(e) {}



  openCreate(id);



}







function resetForm() {



  ['fTitle','fDesc'].forEach(id=>document.getElementById(id).value='');
window.selectedClassifications = [];
  renderActiveTags();
  populateClassificationSelector();



  document.getElementById('fAssign').value='all';



  document.getElementById('specRow').style.display='none';



  document.getElementById('fTimerOn').checked=false;



  document.getElementById('timerRow').style.display='none';



  document.getElementById('fNoDeadline').checked=false;



  document.getElementById('fEndDateMode').checked=false;



  document.getElementById('fShowRes').checked=true;



  document.getElementById('fShowAns').checked=false;



  document.getElementById('fShuffle').checked=false;



  document.getElementById('fReview').checked=true;



  document.getElementById('qList').innerHTML='';



  document.getElementById('ctierList').innerHTML='';



  setDefaultDates();



  toggleEndDateMode(false);



  toggleNoDeadline(false);



  const allChk = document.getElementById('class_all');



  if (allChk) {



    if (CFG.activeClass === 'كل الفصول') {



      allChk.checked = true;



    } else {



      allChk.checked = !CFG.activeClass;



    }



  }



  document.querySelectorAll('.class-checkbox').forEach(cb => {



    if (CFG.activeClass && cb.value === CFG.activeClass) {



      cb.checked = true;



    } else {



      cb.checked = false;



    }



  });



}



function fillForm(t) {



  document.getElementById('fTitle').value=t.title||'';
window.selectedClassifications = t.group_name ? t.group_name.split(',').map(x => x.trim()).filter(Boolean) : [];
  renderActiveTags();
  populateClassificationSelector();



  document.getElementById('fDesc').value=t.description||'';



  document.getElementById('fStart').value=toLocalDT(t.start_date);



  document.getElementById('fEnd').value=toLocalDT(t.end_date);



  document.getElementById('fNoDeadline').checked=!!parseInt(t.no_deadline||0);



  document.getElementById('fAssign').value=t.assign_to||'all';



  document.getElementById('fShowRes').checked=!!parseInt(t.show_result);



  document.getElementById('fShowAns').checked=!!parseInt(t.show_answers||0);



  document.getElementById('fShuffle').checked=!!parseInt(t.shuffle);



  document.getElementById('fReview').checked=!!parseInt(t.allow_review);



  document.getElementById('fEndDateMode').checked=isEndDateOnly(t.end_date);



  toggleEndDateMode(false);



  toggleNoDeadline(false);



  const classIds = (t.class_ids || '').split(',').map(s=>s.trim()).filter(Boolean);



  const allChk = document.getElementById('class_all');



  if (allChk) allChk.checked = (classIds.includes('0') || classIds.length === 0);



  



  document.querySelectorAll('.class-checkbox').forEach(cb => {



    cb.checked = classIds.includes(cb.dataset.id);



  });



  if(parseInt(t.time_limit)){



    document.getElementById('fTimerOn').checked=true;



    document.getElementById('timerRow').style.display='';



    document.getElementById('fTimerMin').value=t.time_limit;



    document.getElementById('fTimerBeh').value=t.timer_behavior||'submit';



  }



  if(t.assign_to==='specific') onAssignChange();



  document.getElementById('qList').innerHTML='';



  (t.questions||[]).forEach(q=>addQ(q));



  document.getElementById('ctierList').innerHTML='';



  const mx=t.coupon_matrix?JSON.parse(t.coupon_matrix):null;



  if(mx&&mx.length) {
    const milestones = convertTiersToMilestones(mx);
    milestones.forEach(m => addMilestone(m.pct, m.coupons));
  }



  else initTiers();



  updDeg();



}







// ─── Steps ──────────────────────────────────────────────────────



function goStep(n) {



  [1,2,3].forEach(i=>{



    document.getElementById(`sp${i}`).style.display=i===n?'':'none';



    const d=document.getElementById(`sd${i}`);



    d.className='step'+(i<n?' done':i===n?' active':'');



  });



  document.getElementById('prevBtn').style.display=n>1?'':'none';



  document.getElementById('nextBtn').style.display=n<3?'':'none';



  document.getElementById('pubBtn').style.display=n===3?'':'none';



  document.getElementById('stepNum').textContent=n;



  curStep=n;



}



function nextStep() {



  if(curStep===1&&!v1())return;



  if(curStep===2&&!v2())return;



  if(curStep===2)document.getElementById('s3deg').textContent=calcDeg();



  goStep(curStep+1);



}



function prevStep(){goStep(curStep-1);}



function v1(){



  if(!document.getElementById('fTitle').value.trim()){showToast('أدخل العنوان','err');return false;}



  const anyClassChecked = Array.from(document.querySelectorAll('#fClassContainer input[type="checkbox"]:checked')).length > 0;

  if(!anyClassChecked){showToast('اختر الفصل / المراحل المستهدفة','err');return false;}



  const s=new Date(document.getElementById('fStart').value);



  if(Number.isNaN(s.getTime())){showToast('أدخل تاريخ البداية','err');return false;}



  if(!document.getElementById('fNoDeadline').checked){



    const e=new Date(getNormalizedEndDateValue());



    if(Number.isNaN(e.getTime())||e<=s){showToast('الموعد النهائي يجب أن يكون بعد البداية','err');return false;}



  }



  if(document.getElementById('fTimerOn').checked&&!document.getElementById('fTimerMin').value){showToast('أدخل مدة المؤقت','err');return false;}



  return true;



}



function v2(){



  const cards=document.querySelectorAll('.qcard');



  if(!cards.length){showToast('أضف سؤالاً واحداً على الأقل','err');return false;}



  for(const card of cards){



    if(!card.querySelector('.qi').value.trim()){showToast('أكمل نص الأسئلة','err');return false;}



    const qt2v=card.dataset.qtype||'mcq';



    if(qt2v==='tf'){



      if(!card.dataset.tfAnswer&&card.dataset.tfAnswer!=='0'){showToast('حدد الإجابة الصحيحة (صح أو خطأ)','err');return false;}



    } else if(qt2v==='mcq'){



      if(!card.querySelector('.oradio.ok')){showToast('حدد الإجابة الصحيحة لكل سؤال','err');return false;}



      const os=card.querySelectorAll('.oinp');



      if(os.length<2){showToast('كل سؤال يحتاج خيارين على الأقل','err');return false;}



      let empty=false;os.forEach(o=>{if(!o.value.trim())empty=true;});



      if(empty){showToast('أكمل نص الخيارات','err');return false;}



    }



  }



  return true;



}







// ─── Question builder ───────────────────────────────────────────



function addQ(data){



  data=data||null;



  qCnt++;



  var id='q'+qCnt;



  var qtype=data&&data.question_type?data.question_type:'mcq';



  var deg=data&&data.degree?data.degree:25;



  var qtxt=data&&data.question_text?data.question_text:'';



  var div=document.createElement('div');



  div.className='qcard'; div.dataset.qid=id; div.dataset.qtype=qtype;



  var n=document.querySelectorAll('.qcard').length+1;



  div.innerHTML=



    '<div class="qhdr">'+



      '<div class="qnum" id="qn_'+id+'">'+n+'</div>'+



      '<input class="qi" type="text" placeholder="نص السؤال\u2026" value="'+esc(qtxt)+'">'+



      '<div class="qdeg"><span class="qdeg-l">الدرجة</span><input class="qdeg-i" type="number" min="1" max="100" value="'+deg+'" oninput="updDeg()"></div>'+



      '<div class="qrm" onclick="rmQ(\''+id+'\')"><i class="fas fa-trash"></i></div>'+



    '</div>'+



    '<div class="q-type-selector">'+



      '<button class="q-type-btn '+(qtype==='mcq'?'active':'')+'" onclick="setQType(\''+id+'\',\'mcq\',this)" title="اختيار من متعدد">'+



        '<i class="fas fa-list-ul"></i> متعدد</button>'+



      '<button class="q-type-btn '+(qtype==='tf'?'active-tf':'')+'" onclick="setQType(\''+id+'\',\'tf\',this)" title="صح أو خطأ">'+



        '<i class="fas fa-check-circle"></i> صح/خطأ</button>'+



      '<button class="q-type-btn '+(qtype==='open'?'active-open':'')+'" onclick="setQType(\''+id+'\',\'open\',this)" title="إجابة مفتوحة">'+



        '<i class="fas fa-pen-nib"></i> مفتوح</button>'+



    '</div>'+



    '<div class="qbody" id="qbody_'+id+'">'+



      '<div class="opts" id="opts_'+id+'"></div>'+



      '<button class="add-opt" id="addopt_'+id+'" onclick="addOpt(\''+id+'\')"><i class="fas fa-plus"></i>إضافة خيار</button>'+



      '<div class="tf-opts" id="tfopts_'+id+'" style="display:none">'+



        '<button class="tf-btn tf-true" id="tftrue_'+id+'" onclick="setTF(\''+id+'\',true)"><i class="fas fa-check-circle"></i> صحيح</button>'+



        '<button class="tf-btn tf-false" id="tffalse_'+id+'" onclick="setTF(\''+id+'\',false)"><i class="fas fa-times-circle"></i> خطأ</button>'+



      '</div>'+



      '<div class="open-q-note" id="opennote_'+id+'" style="display:none"><i class="fas fa-pen-nib"></i>الطفل يكتب إجابة نصية \u2014 تُصحَّح يدوياً بعد التسليم</div>'+



    '</div>'+



    '<div class="q-img-section">'+



      '<button class="q-img-toggle" onclick="toggleImgSection(\''+id+'\')">'+



        '<i class="fas fa-image"></i> إضافة صورة للسؤال (اختياري)'+



        '<i class="fas fa-chevron-down" id="imgchev_'+id+'" style="font-size:.62rem;margin-right:auto;transition:.2s;"></i>'+



      '</button>'+



      '<div class="q-img-input-wrap" id="imgwrap_'+id+'">'+



        '<div class="q-img-tabs">'+



          '<button class="q-img-tab active" onclick="switchImgTab(\''+id+'\',\'url\',this)">'+



            '<i class="fas fa-link"></i> رابط</button>'+



          '<button class="q-img-tab" onclick="switchImgTab(\''+id+'\',\'upload\',this)">'+



            '<i class="fas fa-upload"></i> رفع صورة</button>'+



        '</div>'+



        '<div class="q-img-tab-panel active" id="imgtab_url_'+id+'">'+



          '<div class="q-img-url-row">'+



            '<input class="q-img-url-inp" id="imgurl_'+id+'" type="text" placeholder="الصق رابط الصورة أو أي رابط يحتوي صورة\u2026">'+



            '<button class="q-img-fetch-btn" onclick="fetchImgFromUrl(\''+id+'\')" ><i class="fas fa-magic"></i> جلب</button>'+



          '</div>'+



        '</div>'+



        '<div class="q-img-tab-panel" id="imgtab_upload_'+id+'">'+



          '<div class="q-img-drop" id="imgdrop_'+id+'" onclick="document.getElementById(\'imgfile_'+id+'\').click()" '+



            'ondragover="event.preventDefault();this.classList.add(\'dragover\')" '+



            'ondragleave="this.classList.remove(\'dragover\')" '+



            'ondrop="event.preventDefault();this.classList.remove(\'dragover\');handleImgDrop(\''+id+'\',event)">'+



            '<i class="fas fa-cloud-upload-alt"></i>'+



            '<p>اضغط أو اسحب صورة هنا</p>'+



            '<small>JPG, PNG, WebP — حتى 5 MB</small>'+



          '</div>'+



          '<input type="file" id="imgfile_'+id+'" accept="image/*" style="display:none" onchange="uploadQImg(\''+id+'\',this.files[0])">'+



          '<div class="q-img-uploading" id="imgloading_'+id+'"><div class="spin spin-sm"></div> جارٍ رفع الصورة\u2026</div>'+



        '</div>'+



        '<div class="q-img-status" id="imgstatus_'+id+'"> class="fas fa-times"></i></button>'+



        '</div>'+



      '</div>'+



    '</div>';



  document.getElementById('qList').appendChild(div);



  if(data&&data.image_url){setQImg(id,data.image_url);toggleImgSection(id);}



  if(qtype==='tf'){



    _showTFLayout(id,data);



  } else if(qtype==='open'){



    _showOpenQLayout(id);



  } else if(data&&data.options){



    var os=typeof data.options==='string'?JSON.parse(data.options):(data.options||[]);



    var ci=parseInt(data.correct_index!=null?data.correct_index:0);



    os.forEach(function(o,i){addOpt(id,o,i===ci);});



  } else { for(var i=0;i<4;i++) addOpt(id,'',i===0); }



  renumQ(); updDeg();



}



function setQType(qid,type,btn){



  var div=document.querySelector('.qcard[data-qid="'+qid+'"]');



  if(!div)return;



  div.dataset.qtype=type;



  div.querySelectorAll('.q-type-btn').forEach(function(b){b.classList.remove('active','active-tf','active-open');});



  if(type==='mcq')     {btn.classList.add('active');     _showMcqLayout(qid);}



  else if(type==='tf') {btn.classList.add('active-tf');  _showTFLayout(qid,null);}



  else                 {btn.classList.add('active-open');_showOpenQLayout(qid);}



}



function _showMcqLayout(qid){



  var opts=document.getElementById('opts_'+qid);



  var addBtn=document.getElementById('addopt_'+qid);



  var tfopts=document.getElementById('tfopts_'+qid);



  var note=document.getElementById('opennote_'+qid);



  if(opts)opts.style.display='';



  if(addBtn)addBtn.style.display='';



  if(tfopts)tfopts.style.display='none';



  if(note)note.style.display='none';



  if(opts){opts.innerHTML='';for(var i=0;i<4;i++)addOpt(qid,'',i===0);}



}



function _showTFLayout(qid,data){



  var opts=document.getElementById('opts_'+qid);



  var addBtn=document.getElementById('addopt_'+qid);



  var tfopts=document.getElementById('tfopts_'+qid);



  var note=document.getElementById('opennote_'+qid);



  if(opts)opts.innerHTML='';



  if(addBtn)addBtn.style.display='none';



  if(tfopts)tfopts.style.display='flex';



  if(note)note.style.display='none';



  if(data&&data.correct_index!=null){



    var isTrue=parseInt(data.correct_index)===0;



    setTF(qid,isTrue,true);



  }



}



function _showOpenQLayout(qid){



  var opts=document.getElementById('opts_'+qid);



  var addBtn=document.getElementById('addopt_'+qid);



  var tfopts=document.getElementById('tfopts_'+qid);



  var note=document.getElementById('opennote_'+qid);



  if(opts)opts.innerHTML='';



  if(addBtn)addBtn.style.display='none';



  if(tfopts)tfopts.style.display='none';



  if(note)note.style.display='flex';



}



function setTF(qid,isTrue,silent){



  var trueBtn=document.getElementById('tftrue_'+qid);



  var falseBtn=document.getElementById('tffalse_'+qid);



  if(!trueBtn||!falseBtn)return;



  trueBtn.classList.toggle('selected',isTrue);



  falseBtn.classList.toggle('selected',!isTrue);



  var div=document.querySelector('.qcard[data-qid="'+qid+'"]');



  if(div)div.dataset.tfAnswer=isTrue?'0':'1';



}



function toggleImgSection(qid){



  var wrap=document.getElementById('imgwrap_'+qid);



  var chev=document.getElementById('imgchev_'+qid);



  if(!wrap)return;



  var open=wrap.classList.toggle('open');



  if(chev)chev.style.transform=open?'rotate(180deg)':'';



}



async function fetchImgFromUrl(qid){



  var inp=document.getElementById('imgurl_'+qid);



  var status=document.getElementById('imgstatus_'+qid);



  var url=(inp&&inp.value||'').trim();



  if(!url){showToast('أدخل رابطاً أولاً','err');return;}



  if(status){status.className='q-img-status';status.textContent='جارٍ جلب الصورة\u2026';}



  var directImg=/\.(jpe?g|png|gif|webp|svg|bmp|avif)(\?.*)?$/i.test(url);



  if(directImg){setQImg(qid,url);return;}



  var transformed=transformToDirectImg(url);



  if(transformed){setQImg(qid,transformed);return;}



  try{



    if(status)status.textContent='جارٍ استخراج الصورة من الرابط\u2026';



    var d=await api('fetchOgImage',{url:url});



    if(d.success&&d.image_url){setQImg(qid,d.image_url);}



    else{if(status){status.className='q-img-status err';status.textContent='تعذّر استخراج الصورة \u2014 جرّب رابطاً مباشراً';}}



  }catch(e){if(status){status.className='q-img-status err';status.textContent='خطأ في الاتصال';}}



}



function transformToDirectImg(url){



  var m=url.match(/drive\.google\.com\/file\/d\/([^\/]+)/);



  if(m)return'https://drive.google.com/uc?export=view&id='+m[1];



  m=url.match(/drive\.google\.com\/open\?id=([^&]+)/);



  if(m)return'https://drive.google.com/uc?export=view&id='+m[1];



  if(url.indexOf('dropbox.com')>-1&&url.indexOf('dl=0')>-1)return url.replace('dl=0','dl=1');



  m=url.match(/imgur\.com\/(?!a\/|gallery\/)([a-zA-Z0-9]+)$/);



  if(m)return'https://i.imgur.com/'+m[1]+'.jpg';



  return null;



}



function setQImg(qid,src){



  var preview=document.getElementById('imgpreview_'+qid);



  var img=document.getElementById('imgel_'+qid);



  var status=document.getElementById('imgstatus_'+qid);



  var inp=document.getElementById('imgurl_'+qid);



  if(!preview||!img)return;



  img.onerror=function(){preview.style.display='none';if(status){status.className='q-img-status err';status.textContent='تعذّر تحميل الصورة \u2014 تأكد أن الرابط عام';};};



  img.onload=function(){preview.style.display='block';if(status){status.className='q-img-status ok';status.textContent='تم تحميل الصورة \u2713';};};



  img.src=src;



  if(inp&&!inp.value)inp.value=src;



  var wrap=document.getElementById('imgwrap_'+qid);



  if(wrap&&!wrap.classList.contains('open'))toggleImgSection(qid);



}



function removeQImg(qid){



  var preview=document.getElementById('imgpreview_'+qid);



  var img=document.getElementById('imgel_'+qid);



  var status=document.getElementById('imgstatus_'+qid);



  var inp=document.getElementById('imgurl_'+qid);



  var fileInp=document.getElementById('imgfile_'+qid);



  if(preview)preview.style.display='none';



  if(img)img.src='';



  if(status)status.textContent='';



  if(inp)inp.value='';



  if(fileInp)fileInp.value='';



}



function getQImg(qid){



  var img=document.getElementById('imgel_'+qid);



  return(img&&img.src&&img.src.indexOf('data:')<0&&img.src!==window.location.href)?img.src:'';



}







function switchImgTab(qid, tab, btn){



  var wrap = document.getElementById('imgwrap_'+qid);



  if(!wrap) return;



  wrap.querySelectorAll('.q-img-tab').forEach(function(b){ b.classList.remove('active'); });



  wrap.querySelectorAll('.q-img-tab-panel').forEach(function(p){ p.classList.remove('active'); });



  btn.classList.add('active');



  var panel = document.getElementById('imgtab_'+tab+'_'+qid);



  if(panel) panel.classList.add('active');



}







async function uploadQImg(qid, file){



  if(!file) return;



  if(file.size > 5*1024*1024){ showToast('الحجم الأقصى 5 MB','err'); return; }



  var loading = document.getElementById('imgloading_'+qid);



  var status  = document.getElementById('imgstatus_'+qid);



  if(loading) loading.style.display='flex';



  if(status){ status.className='q-img-status'; status.textContent='جارٍ رفع الصورة…'; }



  try {



    var fd = new FormData();



    fd.append('photo', file, 'question_img_'+Date.now()+'.'+file.name.split('.').pop());



    fd.append('type', 'question');



    var r = await fetch('/upload.php', {



      method: 'POST', body: fd, headers: { Accept: 'application/json' }



    });



    var d = await r.json();



    if(d.success && d.imageUrl){



      setQImg(qid, d.imageUrl);



      var inp = document.getElementById('imgurl_'+qid);



      if(inp) inp.value = d.imageUrl;



    } else {



      if(status){ status.className='q-img-status err'; status.textContent = d.message || 'فشل الرفع'; }



    }



  } catch(e) {



    if(status){ status.className='q-img-status err'; status.textContent='خطأ في الاتصال'; }



  }



  if(loading) loading.style.display='none';



}







function handleImgDrop(qid, event){



  var files = event.dataTransfer.files;



  if(files && files[0]) uploadQImg(qid, files[0]);



}







function addOpt(qid,text='',correct=false){



  const list=document.getElementById(`opts_${qid}`);



  const idx=list.children.length;



  if(idx>=5){showToast('الحد الأقصى ٥ خيارات','info');return;}



  const row=document.createElement('div');



  row.className='orow';



  row.innerHTML=`<div class="oradio${correct?' ok':''}" onclick="setCorrect(this)">${correct?'✓':''}</div>



    <div class="olet">${LETTERS[idx]}</div>



    <input class="oinp" type="text" placeholder="الخيار ${LETTERS[idx]}" value="${esc(text)}">



    <div class="odel" onclick="this.closest('.orow').remove();relabel('${qid}')"><i class="fas fa-times"></i></div>`;



  list.appendChild(row);



}



function setCorrect(el){



  el.closest('.opts').querySelectorAll('.oradio').forEach(r=>{r.classList.remove('ok');r.textContent='';});



  el.classList.add('ok'); el.textContent='✓';



}



function relabel(qid){



  document.getElementById(`opts_${qid}`).querySelectorAll('.orow').forEach((r,i)=>{



    r.querySelector('.olet').textContent=LETTERS[i];



    r.querySelector('.oinp').placeholder=`الخيار ${LETTERS[i]}`;



  });



}



function rmQ(id){document.querySelector(`.qcard[data-qid="${id}"]`)?.remove();renumQ();updDeg();}



function renumQ(){document.querySelectorAll('.qcard').forEach((c,i)=>c.querySelector('.qnum').textContent=i+1);}



function calcDeg(){let t=0;document.querySelectorAll('.qdeg-i').forEach(i=>t+=parseInt(i.value)||0);return t;}



function updDeg(){document.getElementById('degTotal').innerHTML=`${calcDeg()} <small style="font-size:.7rem;font-weight:500;">درجة</small>`;}







// ─── Coupon tiers ────────────────────────────────────────────────



function convertMilestonesToTiers(milestones) {
  const sorted = [...milestones].sort((a, b) => a.pct - b.pct);
  if (sorted.length === 0 || sorted[0].pct > 0) {
    sorted.unshift({ pct: 0, coupons: 0 });
  }
  
  const tiers = [];
  for (let i = 0; i < sorted.length; i++) {
    const from = sorted[i].pct;
    const val = sorted[i].coupons;
    const to = (i < sorted.length - 1) ? (sorted[i+1].pct - 1) : 100;
    tiers.push({ from, to, val });
  }
  return tiers;
}

function convertTiersToMilestones(tiers) {
  return (tiers || [])
    .filter(t => parseInt(t.from) > 0 || parseInt(t.val) > 0)
    .map(t => ({ pct: parseInt(t.from), coupons: parseInt(t.val) }))
    .sort((a, b) => a.pct - b.pct);
}

function addMilestone(pct=50, coupons=10) {
  const div=document.createElement('div');
  div.className='ctier';
  div.style = "display:flex; align-items:center; gap:8px; margin-bottom:8px; background:var(--bg2); border:1px solid var(--bdr); padding:8px 12px; border-radius:8px;";
  div.innerHTML=`
    <div style="display:flex; align-items:center; gap:6px;">
      <span style="font-size:0.85rem; color:var(--t2); font-weight:700;">حصول الطالب على</span>
      <input type="number" class="milestone-pct" min="1" max="100" value="${pct}" style="width:65px; text-align:center; font-weight:bold; border-radius:6px; border:1px solid var(--bdr); padding:5px; background:var(--bg);">
      <span style="font-size:0.85rem; color:var(--t2); font-weight:700;">% أو أكثر</span>
    </div>
    <div style="display:flex; align-items:center; gap:6px; margin-right:15px;">
      <span style="font-size:0.85rem; color:var(--t2); font-weight:700;">يمنحه</span>
      <input type="number" class="milestone-coupons" min="0" max="999" value="${coupons}" style="width:65px; text-align:center; font-weight:bold; border-radius:6px; border:1px solid var(--bdr); padding:5px; background:var(--bg);">
      <i class="fas fa-star" style="color:#eab308; font-size: 0.9rem;"></i>
      <span style="font-size:0.85rem; color:var(--t2); font-weight:700;">كوبون</span>
    </div>
    <div class="ctier-del" onclick="this.closest('.ctier').remove()" style="margin-right:auto; margin-left:5px; cursor:pointer; color:var(--t3); transition:0.2s;" onmouseover="this.style.color='var(--err)'" onmouseout="this.style.color='var(--t3)'"><i class="fas fa-times"></i></div>
  `;
  document.getElementById('ctierList').appendChild(div);
}

function initTiers() {
  document.getElementById('ctierList').innerHTML='';
  addMilestone(50, 10);
  addMilestone(70, 30);
  addMilestone(85, 50);
  addMilestone(95, 100);
}

function resetForm() {



  ['fTitle','fDesc'].forEach(id=>document.getElementById(id).value='');
window.selectedClassifications = [];
  renderActiveTags();
  populateClassificationSelector();



  document.getElementById('fAssign').value='all';



  document.getElementById('specRow').style.display='none';



  document.getElementById('fTimerOn').checked=false;



  document.getElementById('timerRow').style.display='none';



  document.getElementById('fNoDeadline').checked=false;



  document.getElementById('fEndDateMode').checked=false;



  document.getElementById('fShowRes').checked=true;



  document.getElementById('fShowAns').checked=false;



  document.getElementById('fShuffle').checked=false;



  document.getElementById('fReview').checked=true;



  document.getElementById('qList').innerHTML='';



  document.getElementById('ctierList').innerHTML='';



  setDefaultDates();



  toggleEndDateMode(false);



  toggleNoDeadline(false);



  const allChk = document.getElementById('class_all');



  if (allChk) {



    if (CFG.activeClass === 'كل الفصول') {



      allChk.checked = true;



    } else {



      allChk.checked = !CFG.activeClass;



    }



  }



  document.querySelectorAll('.class-checkbox').forEach(cb => {



    if (CFG.activeClass && cb.value === CFG.activeClass) {



      cb.checked = true;



    } else {



      cb.checked = false;



    }



  });



}



function fillForm(t) {



  document.getElementById('fTitle').value=t.title||'';
window.selectedClassifications = t.group_name ? t.group_name.split(',').map(x => x.trim()).filter(Boolean) : [];
  renderActiveTags();
  populateClassificationSelector();



  document.getElementById('fDesc').value=t.description||'';



  document.getElementById('fStart').value=toLocalDT(t.start_date);



  document.getElementById('fEnd').value=toLocalDT(t.end_date);



  document.getElementById('fNoDeadline').checked=!!parseInt(t.no_deadline||0);



  document.getElementById('fAssign').value=t.assign_to||'all';



  document.getElementById('fShowRes').checked=!!parseInt(t.show_result);



  document.getElementById('fShowAns').checked=!!parseInt(t.show_answers||0);



  document.getElementById('fShuffle').checked=!!parseInt(t.shuffle);



  document.getElementById('fReview').checked=!!parseInt(t.allow_review);



  document.getElementById('fEndDateMode').checked=isEndDateOnly(t.end_date);



  toggleEndDateMode(false);



  toggleNoDeadline(false);



  const classIds = (t.class_ids || '').split(',').map(s=>s.trim()).filter(Boolean);



  const allChk = document.getElementById('class_all');



  if (allChk) allChk.checked = (classIds.includes('0') || classIds.length === 0);



  



  document.querySelectorAll('.class-checkbox').forEach(cb => {



    cb.checked = classIds.includes(cb.dataset.id);



  });



  if(parseInt(t.time_limit)){



    document.getElementById('fTimerOn').checked=true;



    document.getElementById('timerRow').style.display='';



    document.getElementById('fTimerMin').value=t.time_limit;



    document.getElementById('fTimerBeh').value=t.timer_behavior||'submit';



  }



  if(t.assign_to==='specific') onAssignChange();



  document.getElementById('qList').innerHTML='';



  (t.questions||[]).forEach(q=>addQ(q));



  document.getElementById('ctierList').innerHTML='';



  const mx=t.coupon_matrix?JSON.parse(t.coupon_matrix):null;



  if(mx&&mx.length) {
    const milestones = convertTiersToMilestones(mx);
    milestones.forEach(m => addMilestone(m.pct, m.coupons));
  }



  else initTiers();



  updDeg();



}







// ─── Steps ──────────────────────────────────────────────────────



function goStep(n) {



  [1,2,3].forEach(i=>{



    document.getElementById(`sp${i}`).style.display=i===n?'':'none';



    const d=document.getElementById(`sd${i}`);



    d.className='step'+(i<n?' done':i===n?' active':'');



  });



  document.getElementById('prevBtn').style.display=n>1?'':'none';



  document.getElementById('nextBtn').style.display=n<3?'':'none';



  document.getElementById('pubBtn').style.display=n===3?'':'none';



  document.getElementById('stepNum').textContent=n;



  curStep=n;



}



function nextStep() {



  if(curStep===1&&!v1())return;



  if(curStep===2&&!v2())return;



  if(curStep===2)document.getElementById('s3deg').textContent=calcDeg();



  goStep(curStep+1);



}



function prevStep(){goStep(curStep-1);}



function v1(){



  if(!document.getElementById('fTitle').value.trim()){showToast('أدخل العنوان','err');return false;}



  const anyClassChecked = Array.from(document.querySelectorAll('#fClassContainer input[type="checkbox"]:checked')).length > 0;

  if(!anyClassChecked){showToast('اختر الفصل / المراحل المستهدفة','err');return false;}



  const s=new Date(document.getElementById('fStart').value);



  if(Number.isNaN(s.getTime())){showToast('أدخل تاريخ البداية','err');return false;}



  if(!document.getElementById('fNoDeadline').checked){



    const e=new Date(getNormalizedEndDateValue());



    if(Number.isNaN(e.getTime())||e<=s){showToast('الموعد النهائي يجب أن يكون بعد البداية','err');return false;}



  }



  if(document.getElementById('fTimerOn').checked&&!document.getElementById('fTimerMin').value){showToast('أدخل مدة المؤقت','err');return false;}



  return true;



}



function v2(){



  const cards=document.querySelectorAll('.qcard');



  if(!cards.length){showToast('أضف سؤالاً واحداً على الأقل','err');return false;}



  for(const card of cards){



    if(!card.querySelector('.qi').value.trim()){showToast('أكمل نص الأسئلة','err');return false;}



    const qt2v=card.dataset.qtype||'mcq';



    if(qt2v==='tf'){



      if(!card.dataset.tfAnswer&&card.dataset.tfAnswer!=='0'){showToast('حدد الإجابة الصحيحة (صح أو خطأ)','err');return false;}



    } else if(qt2v==='mcq'){



      if(!card.querySelector('.oradio.ok')){showToast('حدد الإجابة الصحيحة لكل سؤال','err');return false;}



      const os=card.querySelectorAll('.oinp');



      if(os.length<2){showToast('كل سؤال يحتاج خيارين على الأقل','err');return false;}



      let empty=false;os.forEach(o=>{if(!o.value.trim())empty=true;});



      if(empty){showToast('أكمل نص الخيارات','err');return false;}



    }



  }



  return true;



}







// ─── Question builder ───────────────────────────────────────────



function addQ(data){



  data=data||null;



  qCnt++;



  var id='q'+qCnt;



  var qtype=data&&data.question_type?data.question_type:'mcq';



  var deg=data&&data.degree?data.degree:25;



  var qtxt=data&&data.question_text?data.question_text:'';



  var div=document.createElement('div');



  div.className='qcard'; div.dataset.qid=id; div.dataset.qtype=qtype;



  var n=document.querySelectorAll('.qcard').length+1;



  div.innerHTML=



    '<div class="qhdr">'+



      '<div class="qnum" id="qn_'+id+'">'+n+'</div>'+



      '<input class="qi" type="text" placeholder="نص السؤال\u2026" value="'+esc(qtxt)+'">'+



      '<div class="qdeg"><span class="qdeg-l">الدرجة</span><input class="qdeg-i" type="number" min="1" max="100" value="'+deg+'" oninput="updDeg()"></div>'+



      '<div class="qrm" onclick="rmQ(\''+id+'\')"><i class="fas fa-trash"></i></div>'+



    '</div>'+



    '<div class="q-type-selector">'+



      '<button class="q-type-btn '+(qtype==='mcq'?'active':'')+'" onclick="setQType(\''+id+'\',\'mcq\',this)" title="اختيار من متعدد">'+



        '<i class="fas fa-list-ul"></i> متعدد</button>'+



      '<button class="q-type-btn '+(qtype==='tf'?'active-tf':'')+'" onclick="setQType(\''+id+'\',\'tf\',this)" title="صح أو خطأ">'+



        '<i class="fas fa-check-circle"></i> صح/خطأ</button>'+



      '<button class="q-type-btn '+(qtype==='open'?'active-open':'')+'" onclick="setQType(\''+id+'\',\'open\',this)" title="إجابة مفتوحة">'+



        '<i class="fas fa-pen-nib"></i> مفتوح</button>'+



    '</div>'+



    '<div class="qbody" id="qbody_'+id+'">'+



      '<div class="opts" id="opts_'+id+'"></div>'+



      '<button class="add-opt" id="addopt_'+id+'" onclick="addOpt(\''+id+'\')"><i class="fas fa-plus"></i>إضافة خيار</button>'+



      '<div class="tf-opts" id="tfopts_'+id+'" style="display:none">'+



        '<button class="tf-btn tf-true" id="tftrue_'+id+'" onclick="setTF(\''+id+'\',true)"><i class="fas fa-check-circle"></i> صحيح</button>'+



        '<button class="tf-btn tf-false" id="tffalse_'+id+'" onclick="setTF(\''+id+'\',false)"><i class="fas fa-times-circle"></i> خطأ</button>'+



      '</div>'+



      '<div class="open-q-note" id="opennote_'+id+'" style="display:none"><i class="fas fa-pen-nib"></i>الطفل يكتب إجابة نصية \u2014 تُصحَّح يدوياً بعد التسليم</div>'+



    '</div>'+



    '<div class="q-img-section">'+



      '<button class="q-img-toggle" onclick="toggleImgSection(\''+id+'\')">'+



        '<i class="fas fa-image"></i> إضافة صورة للسؤال (اختياري)'+



        '<i class="fas fa-chevron-down" id="imgchev_'+id+'" style="font-size:.62rem;margin-right:auto;transition:.2s;"></i>'+



      '</button>'+



      '<div class="q-img-input-wrap" id="imgwrap_'+id+'">'+



        '<div class="q-img-tabs">'+



          '<button class="q-img-tab active" onclick="switchImgTab(\''+id+'\',\'url\',this)">'+



            '<i class="fas fa-link"></i> رابط</button>'+



          '<button class="q-img-tab" onclick="switchImgTab(\''+id+'\',\'upload\',this)">'+



            '<i class="fas fa-upload"></i> رفع صورة</button>'+



        '</div>'+



        '<div class="q-img-tab-panel active" id="imgtab_url_'+id+'">'+



          '<div class="q-img-url-row">'+



            '<input class="q-img-url-inp" id="imgurl_'+id+'" type="text" placeholder="الصق رابط الصورة أو أي رابط يحتوي صورة\u2026">'+



            '<button class="q-img-fetch-btn" onclick="fetchImgFromUrl(\''+id+'\')" ><i class="fas fa-magic"></i> جلب</button>'+



          '</div>'+



        '</div>'+



        '<div class="q-img-tab-panel" id="imgtab_upload_'+id+'">'+



          '<div class="q-img-drop" id="imgdrop_'+id+'" onclick="document.getElementById(\'imgfile_'+id+'\').click()" '+



            'ondragover="event.preventDefault();this.classList.add(\'dragover\')" '+



            'ondragleave="this.classList.remove(\'dragover\')" '+



            'ondrop="event.preventDefault();this.classList.remove(\'dragover\');handleImgDrop(\''+id+'\',event)">'+



            '<i class="fas fa-cloud-upload-alt"></i>'+



            '<p>اضغط أو اسحب صورة هنا</p>'+



            '<small>JPG, PNG, WebP — حتى 5 MB</small>'+



          '</div>'+



          '<input type="file" id="imgfile_'+id+'" accept="image/*" style="display:none" onchange="uploadQImg(\''+id+'\',this.files[0])">'+



          '<div class="q-img-uploading" id="imgloading_'+id+'"><div class="spin spin-sm"></div> جارٍ رفع الصورة\u2026</div>'+



        '</div>'+



        '<div class="q-img-status" id="imgstatus_'+id+'"> class="fas fa-times"></i></button>'+



        '</div>'+



      '</div>'+



    '</div>';



  document.getElementById('qList').appendChild(div);



  if(data&&data.image_url){setQImg(id,data.image_url);toggleImgSection(id);}



  if(qtype==='tf'){



    _showTFLayout(id,data);



  } else if(qtype==='open'){



    _showOpenQLayout(id);



  } else if(data&&data.options){



    var os=typeof data.options==='string'?JSON.parse(data.options):(data.options||[]);



    var ci=parseInt(data.correct_index!=null?data.correct_index:0);



    os.forEach(function(o,i){addOpt(id,o,i===ci);});



  } else { for(var i=0;i<4;i++) addOpt(id,'',i===0); }



  renumQ(); updDeg();



}



function setQType(qid,type,btn){



  var div=document.querySelector('.qcard[data-qid="'+qid+'"]');



  if(!div)return;



  div.dataset.qtype=type;



  div.querySelectorAll('.q-type-btn').forEach(function(b){b.classList.remove('active','active-tf','active-open');});



  if(type==='mcq')     {btn.classList.add('active');     _showMcqLayout(qid);}



  else if(type==='tf') {btn.classList.add('active-tf');  _showTFLayout(qid,null);}



  else                 {btn.classList.add('active-open');_showOpenQLayout(qid);}



}



function _showMcqLayout(qid){



  var opts=document.getElementById('opts_'+qid);



  var addBtn=document.getElementById('addopt_'+qid);



  var tfopts=document.getElementById('tfopts_'+qid);



  var note=document.getElementById('opennote_'+qid);



  if(opts)opts.style.display='';



  if(addBtn)addBtn.style.display='';



  if(tfopts)tfopts.style.display='none';



  if(note)note.style.display='none';



  if(opts){opts.innerHTML='';for(var i=0;i<4;i++)addOpt(qid,'',i===0);}



}



function _showTFLayout(qid,data){



  var opts=document.getElementById('opts_'+qid);



  var addBtn=document.getElementById('addopt_'+qid);



  var tfopts=document.getElementById('tfopts_'+qid);



  var note=document.getElementById('opennote_'+qid);



  if(opts)opts.innerHTML='';



  if(addBtn)addBtn.style.display='none';



  if(tfopts)tfopts.style.display='flex';



  if(note)note.style.display='none';



  if(data&&data.correct_index!=null){



    var isTrue=parseInt(data.correct_index)===0;



    setTF(qid,isTrue,true);



  }



}



function _showOpenQLayout(qid){



  var opts=document.getElementById('opts_'+qid);



  var addBtn=document.getElementById('addopt_'+qid);



  var tfopts=document.getElementById('tfopts_'+qid);



  var note=document.getElementById('opennote_'+qid);



  if(opts)opts.innerHTML='';



  if(addBtn)addBtn.style.display='none';



  if(tfopts)tfopts.style.display='none';



  if(note)note.style.display='flex';



}



function setTF(qid,isTrue,silent){



  var trueBtn=document.getElementById('tftrue_'+qid);



  var falseBtn=document.getElementById('tffalse_'+qid);



  if(!trueBtn||!falseBtn)return;



  trueBtn.classList.toggle('selected',isTrue);



  falseBtn.classList.toggle('selected',!isTrue);



  var div=document.querySelector('.qcard[data-qid="'+qid+'"]');



  if(div)div.dataset.tfAnswer=isTrue?'0':'1';



}



function toggleImgSection(qid){



  var wrap=document.getElementById('imgwrap_'+qid);



  var chev=document.getElementById('imgchev_'+qid);



  if(!wrap)return;



  var open=wrap.classList.toggle('open');



  if(chev)chev.style.transform=open?'rotate(180deg)':'';



}



async function fetchImgFromUrl(qid){



  var inp=document.getElementById('imgurl_'+qid);



  var status=document.getElementById('imgstatus_'+qid);



  var url=(inp&&inp.value||'').trim();



  if(!url){showToast('أدخل رابطاً أولاً','err');return;}



  if(status){status.className='q-img-status';status.textContent='جارٍ جلب الصورة\u2026';}



  var directImg=/\.(jpe?g|png|gif|webp|svg|bmp|avif)(\?.*)?$/i.test(url);



  if(directImg){setQImg(qid,url);return;}



  var transformed=transformToDirectImg(url);



  if(transformed){setQImg(qid,transformed);return;}



  try{



    if(status)status.textContent='جارٍ استخراج الصورة من الرابط\u2026';



    var d=await api('fetchOgImage',{url:url});



    if(d.success&&d.image_url){setQImg(qid,d.image_url);}



    else{if(status){status.className='q-img-status err';status.textContent='تعذّر استخراج الصورة \u2014 جرّب رابطاً مباشراً';}}



  }catch(e){if(status){status.className='q-img-status err';status.textContent='خطأ في الاتصال';}}



}



function transformToDirectImg(url){



  var m=url.match(/drive\.google\.com\/file\/d\/([^\/]+)/);



  if(m)return'https://drive.google.com/uc?export=view&id='+m[1];



  m=url.match(/drive\.google\.com\/open\?id=([^&]+)/);



  if(m)return'https://drive.google.com/uc?export=view&id='+m[1];



  if(url.indexOf('dropbox.com')>-1&&url.indexOf('dl=0')>-1)return url.replace('dl=0','dl=1');



  m=url.match(/imgur\.com\/(?!a\/|gallery\/)([a-zA-Z0-9]+)$/);



  if(m)return'https://i.imgur.com/'+m[1]+'.jpg';



  return null;



}



function setQImg(qid,src){



  var preview=document.getElementById('imgpreview_'+qid);



  var img=document.getElementById('imgel_'+qid);



  var status=document.getElementById('imgstatus_'+qid);



  var inp=document.getElementById('imgurl_'+qid);



  if(!preview||!img)return;



  img.onerror=function(){preview.style.display='none';if(status){status.className='q-img-status err';status.textContent='تعذّر تحميل الصورة \u2014 تأكد أن الرابط عام';};};



  img.onload=function(){preview.style.display='block';if(status){status.className='q-img-status ok';status.textContent='تم تحميل الصورة \u2713';};};



  img.src=src;



  if(inp&&!inp.value)inp.value=src;



  var wrap=document.getElementById('imgwrap_'+qid);



  if(wrap&&!wrap.classList.contains('open'))toggleImgSection(qid);



}



function removeQImg(qid){



  var preview=document.getElementById('imgpreview_'+qid);



  var img=document.getElementById('imgel_'+qid);



  var status=document.getElementById('imgstatus_'+qid);



  var inp=document.getElementById('imgurl_'+qid);



  var fileInp=document.getElementById('imgfile_'+qid);



  if(preview)preview.style.display='none';



  if(img)img.src='';



  if(status)status.textContent='';



  if(inp)inp.value='';



  if(fileInp)fileInp.value='';



}



function getQImg(qid){



  var img=document.getElementById('imgel_'+qid);



  return(img&&img.src&&img.src.indexOf('data:')<0&&img.src!==window.location.href)?img.src:'';



}







function switchImgTab(qid, tab, btn){



  var wrap = document.getElementById('imgwrap_'+qid);



  if(!wrap) return;



  wrap.querySelectorAll('.q-img-tab').forEach(function(b){ b.classList.remove('active'); });



  wrap.querySelectorAll('.q-img-tab-panel').forEach(function(p){ p.classList.remove('active'); });



  btn.classList.add('active');



  var panel = document.getElementById('imgtab_'+tab+'_'+qid);



  if(panel) panel.classList.add('active');



}







async function uploadQImg(qid, file){



  if(!file) return;



  if(file.size > 5*1024*1024){ showToast('الحجم الأقصى 5 MB','err'); return; }



  var loading = document.getElementById('imgloading_'+qid);



  var status  = document.getElementById('imgstatus_'+qid);



  if(loading) loading.style.display='flex';



  if(status){ status.className='q-img-status'; status.textContent='جارٍ رفع الصورة…'; }



  try {



    var fd = new FormData();



    fd.append('photo', file, 'question_img_'+Date.now()+'.'+file.name.split('.').pop());



    fd.append('type', 'question');



    var r = await fetch('/upload.php', {



      method: 'POST', body: fd, headers: { Accept: 'application/json' }



    });



    var d = await r.json();



    if(d.success && d.imageUrl){



      setQImg(qid, d.imageUrl);



      var inp = document.getElementById('imgurl_'+qid);



      if(inp) inp.value = d.imageUrl;



    } else {



      if(status){ status.className='q-img-status err'; status.textContent = d.message || 'فشل الرفع'; }



    }



  } catch(e) {



    if(status){ status.className='q-img-status err'; status.textContent='خطأ في الاتصال'; }



  }



  if(loading) loading.style.display='none';



}







function handleImgDrop(qid, event){



  var files = event.dataTransfer.files;



  if(files && files[0]) uploadQImg(qid, files[0]);



}







function addOpt(qid,text='',correct=false){



  const list=document.getElementById(`opts_${qid}`);



  const idx=list.children.length;



  if(idx>=5){showToast('الحد الأقصى ٥ خيارات','info');return;}



  const row=document.createElement('div');



  row.className='orow';



  row.innerHTML=`<div class="oradio${correct?' ok':''}" onclick="setCorrect(this)">${correct?'✓':''}</div>



    <div class="olet">${LETTERS[idx]}</div>



    <input class="oinp" type="text" placeholder="الخيار ${LETTERS[idx]}" value="${esc(text)}">



    <div class="odel" onclick="this.closest('.orow').remove();relabel('${qid}')"><i class="fas fa-times"></i></div>`;



  list.appendChild(row);



}



function setCorrect(el){



  el.closest('.opts').querySelectorAll('.oradio').forEach(r=>{r.classList.remove('ok');r.textContent='';});



  el.classList.add('ok'); el.textContent='✓';



}



function relabel(qid){



  document.getElementById(`opts_${qid}`).querySelectorAll('.orow').forEach((r,i)=>{



    r.querySelector('.olet').textContent=LETTERS[i];



    r.querySelector('.oinp').placeholder=`الخيار ${LETTERS[i]}`;



  });



}



function rmQ(id){document.querySelector(`.qcard[data-qid="${id}"]`)?.remove();renumQ();updDeg();}



function renumQ(){document.querySelectorAll('.qcard').forEach((c,i)=>c.querySelector('.qnum').textContent=i+1);}



function calcDeg(){let t=0;document.querySelectorAll('.qdeg-i').forEach(i=>t+=parseInt(i.value)||0);return t;}



function updDeg(){document.getElementById('degTotal').innerHTML=`${calcDeg()} <small style="font-size:.7rem;font-weight:500;">درجة</small>`;}







// ─── Coupon tiers ────────────────────────────────────────────────



function addTier(from=50,to=100,coupons=3){



  const div=document.createElement('div');div.className='ctier';



  div.innerHTML=`<div class="ctier-range">من <input type="number" min="0" max="100" value="${from}"> %</div>



    <div class="ctier-arr"><i class="fas fa-arrow-left"></i></div>



    <div class="ctier-range">إلى <input type="number" min="0" max="100" value="${to}"> %</div>



    <div class="crew"><i class="fas fa-star"></i><input type="number" min="0" max="999" value="${coupons}"><span class="crew-l">كوبون</span></div>



    <div class="ctier-del" onclick="this.closest('.ctier').remove()"><i class="fas fa-times"></i></div>`;



  document.getElementById('ctierList').appendChild(div);



}



function initTiers(){document.getElementById('ctierList').innerHTML='';addTier(0,49,0);addTier(50,69,10);addTier(70,84,30);addTier(85,94,50);addTier(95,100,100);}







// ─── Assign-to ───────────────────────────────────────────────────



function onAssignChange(){



  const spec=document.getElementById('fAssign').value==='specific';



  document.getElementById('specRow').style.display=spec?'':'none';



  if(spec)populateSpec();



}



async function onClassChange(){if(document.getElementById('fAssign').value==='specific')await populateSpec();}



async function populateSpec(){



  const allChecked = document.getElementById('class_all')?.checked;



  let classes = [];



  if (allChecked) {



    classes = ['كل الفصول'];



  } else {



    classes = Array.from(document.querySelectorAll('.class-checkbox:checked')).map(b => b.value);



  }



  const c = document.getElementById('specList');



  if(!classes.length){c.innerHTML='<span style="color:var(--t3);">اختر الفصل أولاً</span>';return;}



  c.innerHTML='<span style="color:var(--t3);"><i class="fas fa-spinner fa-spin"></i> جارٍ التحميل…</span>';



  



  let st = [];



  for (const cls of classes) {



    const studentsOfClass = await loadStudents(cls);



    st = st.concat(studentsOfClass);



  }



  



  // Deduplicate students by ID



  const seenIds = new Set();



  st = st.filter(s => {



    if (seenIds.has(s.id)) return false;



    seenIds.add(s.id);



    return true;



  });







  if(!st.length){c.innerHTML='<span style="color:var(--t3);">لا يوجد أطفال</span>';return;}



  c.innerHTML=st.map(s=>{
    const avatar = getStudentAvatarHtml(s.photo, s.name, '24px');
    return `<label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-size:.78rem;color:var(--t1);padding:4px 0;">
      <input type="checkbox" name="spec_ids" value="${s.id}" style="accent-color:var(--brand);">${avatar}<span style="font-weight:600;">${esc(s.name)}</span></label>`;
  }).join('');



}







// ─── Collect & Save ──────────────────────────────────────────────



function collectForm(status){



  const questions=[];



  document.querySelectorAll('.qcard').forEach((card,qi)=>{



    const qtype2=card.dataset.qtype||'mcq';



    const qtext2=(card.querySelector('.qi')||{value:''}).value.trim();



    const degree2=parseInt((card.querySelector('.qdeg-i')||{value:25}).value)||1;



    const img2=getQImg(card.dataset.qid)||'';



    if(qtype2==='open'){



      questions.push({sort_order:qi,question_type:'open',question_text:qtext2,options:'[]',correct_index:null,degree:degree2,image_url:img2});



    } else if(qtype2==='tf'){



      const ci2=parseInt(card.dataset.tfAnswer||'0');



      questions.push({sort_order:qi,question_type:'tf',question_text:qtext2,options:JSON.stringify(['\u0635\u062d\u064a\u062d','\u062e\u0637\u0623']),correct_index:ci2,degree:degree2,image_url:img2});



    } else {



      let ci2=0; const opts2=[];



      card.querySelectorAll('.orow').forEach((r,oi)=>{



        opts2.push((r.querySelector('.oinp')||{value:''}).value.trim());



        if(r.querySelector('.oradio.ok'))ci2=oi;



      });



      questions.push({sort_order:qi,question_type:'mcq',question_text:qtext2,options:JSON.stringify(opts2),correct_index:ci2,degree:degree2,image_url:img2});



    }



  });



  const milestones = [];
  document.querySelectorAll('.ctier').forEach(t => {
    const pct = parseInt(t.querySelector('.milestone-pct').value)||50;
    const coupons = parseInt(t.querySelector('.milestone-coupons').value)||0;
    milestones.push({ pct, coupons });
  });
  const tiers = convertMilestonesToTiers(milestones);





  const specIds=[];



  document.querySelectorAll('[name="spec_ids"]:checked').forEach(c=>specIds.push(parseInt(c.value)));



  



  let classId = 0;



  let className = 'كل الفصول';



  let classIdsList = [];



  



  const allChecked = document.getElementById('class_all')?.checked;



  if (allChecked) {



    classId = 0;



    className = 'كل الفصول';



    classIdsList = ['0'];



  } else {



    const checkedBoxes = Array.from(document.querySelectorAll('.class-checkbox:checked'));



    if (checkedBoxes.length > 0) {



      classIdsList = checkedBoxes.map(b => b.dataset.id);



      classId = parseInt(checkedBoxes[0].dataset.id);



      className = checkedBoxes[0].value;



    }



  }



  const classIds = classIdsList.join(',');







  return {



    status, title:document.getElementById('fTitle').value.trim(), description:document.getElementById('fDesc').value.trim(),
    group_name: window.selectedClassifications.join(','),



    class_name: className, class_id: classId, class_ids: classIds,



    assign_to:document.getElementById('fAssign').value, specific_ids:JSON.stringify(specIds),



    start_date:document.getElementById('fStart').value.replace('T',' '),



    end_date:(document.getElementById('fNoDeadline').checked?'':getNormalizedEndDateValue()).replace('T',' '),



    no_deadline:document.getElementById('fNoDeadline').checked?1:0,



    time_limit:document.getElementById('fTimerOn').checked?(parseInt(document.getElementById('fTimerMin').value)||null):null,



    timer_behavior:document.getElementById('fTimerBeh').value,



    show_result:document.getElementById('fShowRes').checked?1:0,



    show_answers:document.getElementById('fShowAns').checked?1:0,



    shuffle:document.getElementById('fShuffle').checked?1:0,



    allow_review:document.getElementById('fReview').checked?1:0,



    total_degree:calcDeg(),



    max_coupons:Math.max(...tiers.map(t=>t.val),0),



    coupon_matrix:JSON.stringify(tiers),



    questions:JSON.stringify(questions)



  };



}



async function saveTask(status){



  const payload=collectForm(status);



  const btnId=status==='draft'?'draftBtn':'pubBtn';



  const btn=document.getElementById(btnId);



  const orig=btn.innerHTML; btn.disabled=true; btn.innerHTML='<span class="spinner"></span>';



  try {



    const extra={...payload}; if(editId)extra.task_id=editId;



    const d=await api(editId?'updateTask':'createTask', extra);



    if(d.success){ showToast(status==='draft'?'تم حفظ الـ Draft ✓':'تم نشر التاسك 🎉',status==='draft'?'info':'ok'); closeCreate(); await loadTasks(); }



    else showToast(d.message||'فشل الحفظ','err');



  } catch(e){ showToast('خطأ في الاتصال','err'); }



  btn.disabled=false; btn.innerHTML=orig;



}



function saveDraft(){ if(!document.getElementById('fTitle').value.trim()){showToast('أدخل العنوان أولاً','err');return;} saveTask('draft'); }



function publishTask(){ if(!document.querySelectorAll('.ctier').length){showToast('أضف مستوى كوبون','err');return;} saveTask('published'); }







// ─── Detail modal ────────────────────────────────────────────────



let detailTask = null;







async function openDetail(id){



  try {



    const d=await api('getTaskDetail',{task_id:id});



    if(!d.success){showToast(d.message||'فشل','err');return;}



    detailTask = d.task;



    const t=d.task; const si=statusOf(t);



    document.getElementById('dTitle').textContent=t.title;



    document.getElementById('dSub').textContent=`${t.class_name||''} — ${(t.questions||[]).length} سؤال — ${t.total_degree} درجة`;



    const matrix=t.coupon_matrix?JSON.parse(t.coupon_matrix):[];



    const subs=t.submissions||[];



    const tc=subs.reduce((a,s)=>a+(parseInt(s.coupons_awarded)||0),0);



    const hasOpenQs = (t.questions||[]).some(q=>q.question_type==='open');



    const pendingSubs = subs.filter(s=>!parseInt(s.is_graded||0) && hasOpenQs).length;



    document.getElementById('dFoot').innerHTML=`



      <button class="btn btn-g" onclick="closeDetail()" style="min-height:40px;"><i class="fas fa-times"></i> إغلاق</button>



      ${hasOpenQs?`<button class="btn" onclick="closeDetail();openGradePanel(${t.id})" style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;min-height:40px;">



        <i class="fas fa-pen-nib"></i> تصحيح${pendingSubs>0?` <span class="pending-badge">${pendingSubs}</span>`:''}</button>`:''}



      <button class="btn btn-p" onclick="closeDetail();openEdit(${t.id})" style="min-height:40px;"><i class="fas fa-pen"></i> تعديل</button>`;







    // ── Build who answered / who didn't list ──────────────────────



    // Always ensure class students are loaded



    const answeredIds = subs.map(s=>parseInt(s.student_id));



    const classStudents = await loadStudents(t.class_name || 'كل الفصول');







    const notAnswered = classStudents.filter(s=>!answeredIds.includes(s.id));







    function buildCollapsibleList(items, renderFn, emptyMsg, maxShow=10) {



      if(!items.length) return `<div style="font-size:.78rem;color:var(--t3);padding:6px 0;">${emptyMsg}</div>`;



      const id = 'clist_' + Math.random().toString(36).slice(2);



      const visible = items.slice(0, maxShow);



      const hidden  = items.slice(maxShow);



      let html = visible.map(renderFn).join('');



      if(hidden.length) {



        html += `<div id="${id}_more" style="display:none;">${hidden.map(renderFn).join('')}</div>



        <button onclick="



          var m=document.getElementById('${id}_more');



          var b=document.getElementById('${id}_btn');



          var open=m.style.display!=='none';



          m.style.display=open?'none':'block';



          b.innerHTML=open?'<i class=\\'fas fa-chevron-down\\'></i> عرض ${hidden.length} أكثر':'<i class=\\'fas fa-chevron-up\\'></i> عرض أقل';



        " id="${id}_btn"



          style="width:100%;margin-top:8px;padding:6px;background:transparent;border:1.5px dashed rgba(0,0,0,.12);border-radius:var(--r-md);cursor:pointer;font-family:'Cairo',sans-serif;font-size:.73rem;font-weight:700;color:inherit;opacity:.7;display:flex;align-items:center;justify-content:center;gap:5px;">



          <i class="fas fa-chevron-down"></i> عرض ${hidden.length} أكثر



        </button>`;



      }



      return html;



    }







    document.getElementById('dBody').innerHTML=`



      <div class="detail-shell">



      <!-- Unified Compact Details Info Board -->
      <div style="background:var(--bg-card); border:1.5px solid var(--bdr); border-radius:12px; padding:16px; margin-bottom:20px; display:flex; flex-direction:column; gap:12px; direction:rtl;">
        
        <!-- Metadata pills -->
        <div style="display:flex; flex-wrap:wrap; gap:12px; font-size:0.75rem; color:var(--t3); border-bottom:1px dashed var(--bdr); padding-bottom:12px; justify-content:flex-start; align-items:center; direction:rtl; text-align:right;">
          <span style="display:inline-flex; align-items:center; gap:4px; background:var(--brand-bg); color:var(--brand); padding:2px 8px; border-radius:4px; font-weight:700;"><i class="fas fa-users"></i> ${esc(t.class_name || 'كل الفصول')}</span>
          <span style="display:inline-flex; align-items:center; gap:4px;"><i class="fas fa-calendar-check" style="color:var(--brand);"></i> البدء: ${fmtDate(t.start_date)}</span>
          <span style="display:inline-flex; align-items:center; gap:4px;"><i class="fas fa-flag-checkered" style="color:var(--brand);"></i> النهاية: ${parseInt(t.no_deadline||0)?'بدون موعد':fmtDate(t.end_date)}</span>
          ${t.time_limit ? `<span style="display:inline-flex; align-items:center; gap:4px;"><i class="fas fa-stopwatch" style="color:var(--brand);"></i> ${t.time_limit} دقيقة</span>` : ''}
          ${parseInt(t.shuffle) ? `<span style="display:inline-flex; align-items:center; gap:4px; color:var(--warn);"><i class="fas fa-random"></i> ترتيب عشوائي</span>` : ''}
          ${si.key === 'draft' ? `<span style="background:var(--warn-bg); color:var(--warn); font-size:0.7rem; font-weight:700; padding:2px 8px; border-radius:4px; border:1px solid rgba(245,158,11,0.25);">Draft</span>` : ''}
        </div>

        <!-- Metrics row -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(100px, 1fr)); gap:16px; direction:rtl;">
          <div style="text-align:right;">
            <div style="font-size:0.7rem; color:var(--t3); font-weight:700; margin-bottom:2px;">الأسئلة</div>
            <div style="font-size:1.15rem; font-weight:900; color:var(--t1);">${(t.questions||[]).length} <span style="font-size:0.75rem; font-weight:700; color:var(--t3);">سؤال</span></div>
          </div>
          <div style="text-align:right; border-right:1px solid var(--bdr); padding-right:16px;">
            <div style="font-size:0.7rem; color:var(--t3); font-weight:700; margin-bottom:2px;">الدرجة الكلية</div>
            <div style="font-size:1.15rem; font-weight:900; color:var(--t1);">${t.total_degree} <span style="font-size:0.75rem; font-weight:700; color:var(--t3);">درجة</span></div>
          </div>
          <div style="text-align:right; border-right:1px solid var(--bdr); padding-right:16px;">
            <div style="font-size:0.7rem; color:var(--t3); font-weight:700; margin-bottom:2px;">تسليمات الطلاب</div>
            <div style="font-size:1.15rem; font-weight:900; color:var(--t1);">${subs.length} <span style="font-size:0.75rem; font-weight:700; color:var(--t3);">إجابة</span></div>
          </div>
          <div style="text-align:right; border-right:1px solid var(--bdr); padding-right:16px;">
            <div style="font-size:0.7rem; color:var(--t3); font-weight:700; margin-bottom:2px;">الكوبونات الموزعة</div>
            <div style="font-size:1.15rem; font-weight:900; color:var(--cou);">${tc} <i class="fas fa-star" style="color:#eab308; font-size:0.75rem; margin-right:2px;"></i></div>
          </div>
        </div>

      </div>



      <!-- Premium Milestones Card -->
      <div style="background:var(--bg3); border:1px solid var(--bdr); border-radius:12px; padding:10px 12px; margin-bottom:14px; direction:rtl;">
        <div style="font-size:0.75rem; font-weight:800; color:var(--t2); margin-bottom:8px; display:flex; align-items:center; gap:6px; text-align:right; justify-content:flex-start;">
          <i class="fas fa-star" style="color:var(--brand); font-size:0.8rem;"></i>
          <span>قواعد توزيع الكوبونات</span>
        </div>
        <div class="detail-milestones-grid" style="grid-template-columns:repeat(auto-fit, minmax(110px, 1fr)); gap:8px;">
          ${convertTiersToMilestones(matrix).map(m=>`
            <div class="detail-milestone-card" style="padding:6px 8px; border-radius:8px; gap:2px; border:1.5px solid var(--brand-l); background:var(--bg-card); text-align:center; display:flex; flex-direction:column; transition:transform 0.2s;" onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='none'">
              <div style="font-size:0.65rem; font-weight:700; color:var(--t3);">عند الحصول على</div>
              <div style="font-size:0.9rem; font-weight:900; color:var(--brand);">${m.pct}% أو أكثر</div>
              <div style="font-size:0.65rem; font-weight:800; color:var(--brand); display:inline-flex; align-items:center; justify-content:center; gap:4px; margin-top:2px; background:var(--brand-bg); padding:2px 6px; border-radius:12px; border:1px solid var(--brand-l);">
                <i class="fas fa-star" style="color:var(--brand); font-size:0.6rem;"></i>
                <span>${m.coupons} كوبون</span>
              </div>
            </div>
          `).join('')}
        </div>
      </div>







      <!-- Who answered / who didn't -->



      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:10px;margin-bottom:20px;">



        <div style="background:var(--ok-bg);border:1px solid #6ee7b7;border-radius:var(--r-md);padding:11px 14px;">



          <div style="font-size:.78rem;font-weight:800;color:var(--ok);margin-bottom:10px;display:flex;align-items:center;gap:6px;">



            <i class="fas fa-check-circle"></i> أجابوا



            <span style="background:var(--ok);color:#fff;border-radius:var(--r-full);padding:1px 8px;font-size:.7rem;font-weight:700;">${subs.length}</span>



          </div>



          ${buildCollapsibleList(subs,



            s=>{
              const stud = (classStuCache['كل الفصول'] || []).find(x => x.id == s.student_id);
              const photo = stud ? stud.photo : '';
              const avatar = getStudentAvatarHtml(photo, s.student_name, '28px');
              return `<div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid rgba(0,0,0,.07);">
                ${avatar}
                <span style="font-size:.8rem;font-weight:700;color:var(--t1);flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(s.student_name||'—')}</span>
                <span style="font-size:.68rem;background:var(--brand-bg);color:var(--brand);border-radius:var(--r-full);padding:1px 7px;font-weight:700;flex-shrink:0;">${s.score||0}/${t.total_degree}</span>
                <button onclick="event.stopPropagation();viewAnswers(${t.id},${s.student_id})"
                  style="background:var(--info-bg);border:1px solid #bfdbfe;color:var(--info);border-radius:6px;padding:4px 8px;cursor:pointer;font-size:.65rem;font-weight:700;font-family:'Cairo',sans-serif;flex-shrink:0;white-space:nowrap;"><i class="fas fa-eye"></i></button>
                <button onclick="event.stopPropagation();showDeleteSubConfirm(${s.id},'${esc(s.student_name||'')}',${s.coupons_awarded||0},${t.id})"
                  style="background:none;border:1px solid #fca5a5;color:var(--err);border-radius:5px;padding:4px 6px;cursor:pointer;font-size:.63rem;flex-shrink:0;"><i class="fas fa-trash"></i></button>
              </div>`;
            },



            'لا أحد بعد'



          )}



        </div>



        <div style="background:var(--err-bg);border:1px solid #fca5a5;border-radius:var(--r-md);padding:11px 14px;">



          <div style="font-size:.78rem;font-weight:800;color:var(--err);margin-bottom:10px;display:flex;align-items:center;gap:6px;">



            <i class="fas fa-clock"></i> لم يجيبوا



            <span style="background:var(--err);color:#fff;border-radius:var(--r-full);padding:1px 8px;font-size:.7rem;font-weight:700;">${notAnswered.length}</span>



          </div>



          ${buildCollapsibleList(notAnswered,



            s=>{
              const avatar = getStudentAvatarHtml(s.photo, s.name, '28px');
              return `<div style="display:flex;align-items:center;gap:8px;padding:5px 0;border-bottom:1px solid rgba(0,0,0,.07);">
                ${avatar}
                <span style="font-size:.8rem;font-weight:700;color:var(--t1);">${esc(s.name)}</span>
              </div>`;
            },



            classStudents.length ? 'الجميع أجاب 🎉' : 'بيانات الفصل غير محملة'



          )}



        </div>



      </div>







      <div style="margin-bottom:20px;">



        <div class="fsec-title"><i class="fas fa-question-circle"></i>الأسئلة</div>



        ${(t.questions||[]).map((q,i)=>{



          const qt3=q.question_type||'mcq';



          const imgH=q.image_url?`<div style="margin:4px 13px 8px;border-radius:var(--r-sm);overflow:hidden;border:1px solid var(--bdr);"><img src="${q.image_url}" alt="" style="width:100%;max-height:180px;object-fit:contain;display:block;background:#f8fafc;"></div>`:'';



          if(qt3==='tf'){



            const ci3=parseInt(q.correct_index||0);



            return `<div class="dq">



              <div class="dq-hdr"><div class="qnum">${i+1}</div><div style="flex:1;font-weight:600;font-size:.86rem;">${esc(q.question_text)}</div>



              <span style="background:#fef3c7;color:#92400e;border-radius:var(--r-full);padding:3px 9px;font-size:.7rem;font-weight:700;white-space:nowrap;margin-left:6px;"><i class="fas fa-check-circle"></i> صح/خطأ</span>



              <div class="qdeg" style="background:var(--brand-bg);"><span class="qdeg-l">الدرجة</span><span style="font-size:.8rem;font-weight:700;color:var(--brand);">${q.degree}</span></div></div>



              ${imgH}



              <div style="padding:10px 13px;display:flex;gap:10px;">



                <div class="dq-opt${ci3===0?' ok':''}" style="flex:1;justify-content:center;"><i class="fas fa-check-circle" style="color:#10b981;"></i> صحيح ${ci3===0?'<i class="fas fa-check-circle" style="color:var(--ok);margin-right:auto;"></i>':''}</div>



                <div class="dq-opt${ci3===1?' ok':''}" style="flex:1;justify-content:center;"><i class="fas fa-times-circle" style="color:#ef4444;"></i> خطأ ${ci3===1?'<i class="fas fa-check-circle" style="color:var(--ok);margin-right:auto;"></i>':''}</div>



              </div>



            </div>`;



          }



          const os=typeof q.options==='string'?JSON.parse(q.options):(q.options||[]);



          const ci=parseInt(q.correct_index||0);



          return `<div class="dq">



            <div class="dq-hdr"><div class="qnum">${i+1}</div><div style="flex:1;font-weight:600;font-size:.86rem;">${esc(q.question_text)}</div><div class="qdeg" style="background:var(--brand-bg);"><span class="qdeg-l">الدرجة</span><span style="font-size:.8rem;font-weight:700;color:var(--brand);">${q.degree}</span></div></div>



            ${imgH}



            <div style="padding:10px 13px;">



              ${os.map((o,j)=>`<div class="dq-opt${j===ci?' ok':''}"><strong style="margin-left:4px;">${LETTERS[j]}</strong>${esc(o)}${j===ci?'<i class="fas fa-check-circle" style="margin-right:auto;color:var(--ok);"></i>':''}</div>`).join('')}



            </div>



          </div>`;



        }).join('')}



      </div>







      <div>



        <div class="fsec-title"><i class="fas fa-users"></i>${PEOPLE} الذين أجابوا (${subs.length}) — ${tc} كوبون ممنوح</div>



        ${subs.length?`<div style="overflow-x:auto;border:1px solid var(--bdr);border-radius:var(--r-md);">



          <table class="sub-tbl"><thead><tr><th>${PEOPLE}</th><th>الدرجة</th><th>النسبة</th><th>الكوبونات</th><th>وقت الإرسال</th><th style="width:80px;"></th></tr></thead>
          <tbody>${subs.map(s=>{
            const stud = (classStuCache['كل الفصول'] || []).find(x => x.id == s.student_id);
            const photo = stud ? stud.photo : '';
            const avatar = getStudentAvatarHtml(photo, s.student_name, '24px');
            return `<tr>
              <td data-label="${PEOPLE}">
                <div style="display:flex;align-items:center;gap:8px;">
                  ${avatar}
                  <span style="font-weight:700;color:var(--t1);">${esc(s.student_name||'—')}</span>
                </div>
              </td>
              <td data-label="الدرجة">${s.score||0}/${t.total_degree}</td>
              <td data-label="النسبة">${t.total_degree?Math.round((parseInt(s.score)||0)/t.total_degree*100):0}%</td>
              <td data-label="الكوبونات"><span style="color:var(--cou);font-weight:700;">${s.coupons_awarded||0} <i class="fas fa-star"></i></span></td>
              <td data-label="التوقيت" style="color:var(--t3);font-size:.7rem;">${fmtDate(s.submitted_at)}</td>
              <td>
                <div style="display:flex;gap:4px;">
                  <button onclick="event.stopPropagation();viewAnswers(${t.id}, ${s.student_id})" style="background:var(--info-bg);border:1px solid #bfdbfe;color:var(--info);border-radius:6px;padding:5px 10px;cursor:pointer;font-size:.72rem;font-weight:700;font-family:'Cairo',sans-serif;min-height:32px;"><i class="fas fa-eye"></i> إجابات</button>
                  <button onclick="event.stopPropagation();showDeleteSubConfirm(${s.id},'${esc(s.student_name||'')}',${s.coupons_awarded||0},${t.id})" style="background:var(--err-bg);border:1px solid #fca5a5;color:var(--err);border-radius:6px;padding:5px 8px;cursor:pointer;font-size:.72rem;min-height:32px;"><i class="fas fa-trash"></i></button>
                </div>
              </td>
            </tr>`;
          }).join('')}</tbody>



          </table></div>`:



        `<div style="text-align:center;padding:24px;color:var(--t3);font-size:.82rem;"><i class="fas fa-inbox" style="font-size:1.7rem;display:block;margin-bottom:6px;"></i>لا توجد إجابات بعد</div>`}



      </div>`;



    openOv('detailOv');



  } catch(e){showToast('خطأ في تحميل التفاصيل','err');}



}







// ─── Delete submission (internal confirm) ─────────────────────────



let _pendingDelSub = null;



function showDeleteSubConfirm(subId, studentName, coupons, taskId){



  _pendingDelSub = {subId, taskId};



  const msg=document.getElementById('delSubMsg');



  if(msg){



    msg.innerHTML = `حذف إجابة <strong>${esc(studentName||'هذا الطفل')}</strong>؟`



      + (coupons>0?`<br><span style="color:var(--err);font-size:.8rem;">سيتم خصم ${coupons} كوبون من كوبونات التاسكات فقط.</span>`:'');



  }



  openOv('delSubConfOv');



}



async function doDeleteSubConfirmed(){



  if(!_pendingDelSub) return;



  const {subId, taskId} = _pendingDelSub;



  _pendingDelSub = null;



  closeOv('delSubConfOv');



  try {



    const d = await api('deleteSubmission', {submission_id: subId});



    if(d.success){



      let msg = 'تم حذف الإجابة ✓';



      if(d.coupons_reversed > 0) msg += ` — تم خصم ${d.coupons_reversed} كوبون من كوبونات التاسكات`;



      showToast(msg, 'ok');



      await openDetail(taskId);



      loadTasks();



    } else {



      showToast(d.message || 'فشل الحذف', 'err');



    }



  } catch(e) {



    showToast('خطأ في الاتصال', 'err');



  }



}







// ─── Delete task ──────────────────────────────────────────────────



function openConf(id) {



  delId = id;



  const t = tasks.find(x => x.id == id);



  if (!t) { openOv('confOv'); return; }







  document.getElementById('confSub').textContent = `سيتم حذف "​${t.title}​" بشكل نهائي.`;







  // Count coupons awarded across all submissions for this task



  const subs        = t.submissions || [];



  const totalCoupons = subs.reduce((a, s) => a + (parseInt(s.coupons_awarded) || 0), 0);



  const kidCount     = subs.filter(s => (parseInt(s.coupons_awarded) || 0) > 0).length;







  const noteEl   = document.getElementById('confCouponNote');



  const detailEl = document.getElementById('confCouponDetail');



  if (totalCoupons > 0) {



    detailEl.textContent = `حصل ${kidCount} طفل على إجمالي ${totalCoupons} كوبون من هذا التاسك. اختر إذا كنت تريد سحبها أو الاحتفاظ بها.`;



    noteEl.style.display = '';



  } else {



    noteEl.style.display = 'none';



  }







  openOv('confOv');



}



async function doDelete(reverseCoupons) {



  try {



    const d = await api('deleteTask', {task_id: delId, reverse_coupons: reverseCoupons ? '1' : '0'});



    if (d.success) {



      let msg = 'تم حذف التاسك';



      if (reverseCoupons && d.coupons_reversed > 0)



        msg += ` وسحب ${d.coupons_reversed} كوبون من الأطفال`;



      else if (!reverseCoupons)



        msg += ' واحتُظ بالكوبونات للأطفال';



      showToast(msg, reverseCoupons ? 'info' : 'ok');



      closeConf();



      await loadTasks();



    } else {



      showToast(d.message || 'فشل الحذف', 'err');



    }



  } catch(e) {



    showToast('خطأ في الاتصال', 'err');



  }



}







// ─── Open question grading — FULL EXAM VIEW ───────────────────────



let gradeTaskId = null;



let gradeSubs = [];



let gradeTaskData = null;







async function openGradePanel(taskId) {
  gradeTaskId = taskId;
  window.activeGradeIndex = 0;

  document.getElementById('gradePanel').classList.add('open');
  document.body.style.overflow = 'hidden';

  document.getElementById('gradePanelBody').innerHTML = `
    <div class="skeleton-row"><div class="skeleton-line" style="height:14px; width:45%;"></div><div class="skeleton-line" style="height:28px; width:60px; border-radius:6px;"></div></div>
    <div class="skeleton-row"><div class="skeleton-line" style="height:14px; width:55%;"></div><div class="skeleton-line" style="height:28px; width:60px; border-radius:6px;"></div></div>
    <div class="skeleton-row"><div class="skeleton-line" style="height:14px; width:35%;"></div><div class="skeleton-line" style="height:28px; width:60px; border-radius:6px;"></div></div>
  `;

  try {
    const td = await api('getTaskDetail', {task_id: taskId});
    gradeTaskData = td.success ? td.task : null;

    const d = await api('getPendingOpenSubmissions', {task_id: taskId});
    if(!d.success){showToast(d.message||'فشل','err');return;}

    gradeSubs = d.submissions || [];
    if (gradeTaskData) {
        await loadStudents(gradeTaskData.class_name || 'كل الفصول');
    }

    renderGradePanel();
  } catch(e){showToast('خطأ في الاتصال','err');}
}

window.activeGradeIndex = 0;

function navigateGrading(dir) {
  if (!gradeSubs || gradeSubs.length === 0) return;
  let newIdx = window.activeGradeIndex + dir;
  if (newIdx < 0 || newIdx >= gradeSubs.length) return;
  window.activeGradeIndex = newIdx;
  renderGradePanel();
}

function updateGradeIndicator() {
  const indicator = document.getElementById('gradeIndexIndicator');
  const prevBtn = document.getElementById('prevGradeBtn');
  const nextBtn = document.getElementById('nextGradeBtn');
  
  if (indicator) {
    indicator.textContent = `${window.activeGradeIndex + 1} / ${gradeSubs.length}`;
  }
  if (prevBtn) {
    prevBtn.disabled = window.activeGradeIndex === 0;
    prevBtn.style.opacity = window.activeGradeIndex === 0 ? '0.4' : '1';
    prevBtn.style.cursor = window.activeGradeIndex === 0 ? 'not-allowed' : 'pointer';
  }
  if (nextBtn) {
    nextBtn.disabled = window.activeGradeIndex === gradeSubs.length - 1;
    nextBtn.style.opacity = window.activeGradeIndex === gradeSubs.length - 1 ? '0.4' : '1';
    nextBtn.style.cursor = window.activeGradeIndex === gradeSubs.length - 1 ? 'not-allowed' : 'pointer';
  }
}

function renderGradePanel() {
  const el = document.getElementById('gradePanelBody');
  
  if (!gradeSubs || !gradeSubs.length) {
    document.getElementById('gradePanelSub').textContent = `0 طفل ينتظر التصحيح`;
    el.innerHTML = '<div style="text-align:center;padding:40px;color:var(--t3);"><i class="fas fa-check-circle" style="font-size:2rem;color:var(--ok);display:block;margin-bottom:10px;"></i>تم تصحيح جميع الإجابات!</div>';
    const indicator = document.getElementById('gradeIndexIndicator');
    if (indicator) indicator.textContent = '0 / 0';
    return;
  }

  if (window.activeGradeIndex >= gradeSubs.length) {
    window.activeGradeIndex = Math.max(0, gradeSubs.length - 1);
  }

  document.getElementById('gradePanelSub').textContent = `${gradeSubs.length} طفل ينتظر التصحيح`;
  updateGradeIndicator();

  const sub = gradeSubs[window.activeGradeIndex];
  const si = window.activeGradeIndex;

  const allQuestions = gradeTaskData ? (gradeTaskData.questions||[]) : [];
  const totalDeg = gradeTaskData ? (gradeTaskData.total_degree||0) : 0;
  const answers = JSON.parse(sub.answers||'{}');

  const qRows = allQuestions.map((q, qi) => {
    const qtype = q.question_type || 'mcq';
    const qId = String(q.id);
    const imgH = q.image_url ? `<div style="margin:4px 0 8px;border-radius:8px;overflow:hidden;border:1px solid var(--bdr);"><img src="${q.image_url}" alt="" style="width:100%;max-height:160px;object-fit:contain;display:block;background:#f8fafc;"></div>` : '';
    const noteId = `gn_${sub.id}_${q.id}`;

    const buildNoteHtml = (placeholder) => `<div class="grade-note-row">
      <i class="fas fa-comment-dots"></i>
      <div class="gn-wrap">
        <div class="gn-lbl">ملاحظة / الإجابة الصحيحة للطفل:</div>
        <textarea class="grade-note-inp" id="${noteId}" data-sub="${sub.id}" data-qid="${q.id}" placeholder="${placeholder}"></textarea>
      </div>
    </div>`;

    if (qtype === 'open') {
      const ans = answers[qId] !== undefined ? String(answers[qId]) : '';
      return `<div class="grade-q-row" style="background:var(--bg2);border:1.5px solid #fde68a;border-radius:var(--r-sm);padding:11px 13px;margin-bottom:10px;">
        <div class="grade-q-text" style="color:#b45309;margin-bottom:4px;">
          <span style="background:#fef3c7;color:#92400e;border-radius:var(--r-full);padding:1px 8px;font-size:.66rem;font-weight:700;margin-left:5px;"><i class="fas fa-pen-nib"></i> مفتوح</span>
          <strong>${qi+1}.</strong> ${esc(q.question_text)} <span style="color:var(--t3);font-size:.72rem;">(${q.degree} درجة)</span>
        </div>
        ${imgH}
        <div class="grade-ans-text" style="background:var(--bg);border:1px solid #fde68a;">${ans ? esc(ans) : '<em style="color:var(--t3);">— لم يُجب —</em>'}</div>
        <div class="grade-score-row">
          <span style="font-size:.78rem;color:var(--t2);font-weight:600;">الدرجة:</span>
          <input class="grade-score-inp" type="number" min="0" max="${q.degree}" value="0"
            id="gs_${sub.id}_${q.id}" data-sub="${sub.id}" data-qid="${q.id}" data-max="${q.degree}"
            oninput="clampGradeInput(this);updateSubScore(${sub.id})">
          <span class="grade-max-lbl">/ ${q.degree}</span>
        </div>
        ${buildNoteHtml('اكتب ملاحظة أو الإجابة الصحيحة للطفل…')}
      </div>`;
    }

    const given = answers[qId] !== undefined ? parseInt(answers[qId]) : null;
    const correct = q.correct_index !== null ? parseInt(q.correct_index) : null;
    const isCorrect = given !== null && correct !== null && given === correct;
    const isWrong = given !== null && correct !== null && given !== correct;

    const statusDot = given === null
      ? `<span style="color:var(--t3);font-size:.68rem;">لم يجب</span>`
      : isCorrect
        ? `<span style="background:var(--ok-bg);color:var(--ok);border-radius:var(--r-full);padding:1px 8px;font-size:.68rem;font-weight:700;"><i class="fas fa-check"></i> صح</span>`
        : `<span style="background:var(--err-bg);color:var(--err);border-radius:var(--r-full);padding:1px 8px;font-size:.68rem;font-weight:700;"><i class="fas fa-times"></i> خطأ</span>`;

    if (qtype === 'tf') {
      const opts = ['صحيح','خطأ'];
      const tfNotePlaceholder = isWrong ? `الإجابة الصحيحة: ${opts[correct]}` : 'اكتب ملاحظة للطفل (اختياري)…';
      const tfNoteDefault = isWrong ? `الإجابة الصحيحة: ${opts[correct]}` : '';

      return `<div style="background:${isCorrect?'var(--ok-bg)':isWrong?'var(--err-bg)':'var(--bg2)'};border:1.5px solid ${isCorrect?'#6ee7b7':isWrong?'#fca5a5':'var(--bdr)'};border-radius:var(--r-sm);padding:9px 13px;margin-bottom:8px;">
        <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
          <span style="font-size:.75rem;font-weight:600;color:var(--t2);"><strong>${qi+1}.</strong> ${esc(q.question_text)}</span>
          <span style="margin-right:auto;">${statusDot}</span>
          <span style="font-size:.68rem;color:var(--t3);">${q.degree} درجة</span>
        </div>
        ${imgH}
        <div style="display:flex;gap:8px;">
          ${opts.map((o,j)=>{
            const isSel = given===j;
            const isCorr = correct===j;
            let bg='var(--bg)'; let border='var(--bdr)'; let clr='var(--t2)';
            if(isSel && isCorr){bg='var(--ok-bg)';border='#6ee7b7';clr='var(--ok)';}
            else if(isSel && !isCorr){bg='var(--err-bg)';border='#fca5a5';clr='var(--err)';}
            else if(isCorr && !isSel){bg='var(--ok-bg)';border='#6ee7b7';clr='var(--ok)';}
            return `<div style="flex:1;padding:7px 10px;border-radius:7px;border:1.5px solid ${border};background:${bg};color:${clr};font-size:.8rem;font-weight:700;text-align:center;">
              ${o}${isSel?' <i class="fas fa-hand-pointer"></i>':''}${isCorr&&!isSel?' ✓':''}
            </div>`;
          }).join('')}
        </div>
        <div class="grade-note-row">
          <i class="fas fa-comment-dots"></i>
          <div class="gn-wrap">
            <div class="gn-lbl">ملاحظة / الإجابة الصحيحة للطفل:</div>
            <textarea class="grade-note-inp" id="${noteId}" data-sub="${sub.id}" data-qid="${q.id}" placeholder="${tfNotePlaceholder}">${tfNoteDefault}</textarea>
          </div>
        </div>
      </div>`;
    }

    const opts = typeof q.options==='string' ? JSON.parse(q.options) : (q.options||[]);
    const mcqNotePlaceholder = isWrong && correct !== null && opts[correct] ? `الإجابة الصحيحة: ${opts[correct]}` : 'اكتب ملاحظة للطفل (اختياري)…';
    const mcqNoteDefault = isWrong && correct !== null && opts[correct] ? `الإجابة الصحيحة: ${opts[correct]}` : '';

    return `<div style="background:${isCorrect?'var(--ok-bg)':isWrong?'var(--err-bg)':'var(--bg2)'};border:1.5px solid ${isCorrect?'#6ee7b7':isWrong?'#fca5a5':'var(--bdr)'};border-radius:var(--r-sm);padding:9px 13px;margin-bottom:8px;">
      <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
        <span style="font-size:.75rem;font-weight:600;color:var(--t2);"><strong>${qi+1}.</strong> ${esc(q.question_text)}</span>
        <span style="margin-right:auto;">${statusDot}</span>
        <span style="font-size:.68rem;color:var(--t3);">${q.degree} درجة</span>
      </div>
      ${imgH}
      ${opts.map((o,j)=>{
        const isSel = given===j;
        const isCorr = correct===j;
        let bg='var(--bg)'; let border='var(--bdr)'; let clr='var(--t2)';
        if(isSel && isCorr){bg='var(--ok-bg)';border='#6ee7b7';clr='var(--ok)';}
        else if(isSel && !isCorr){bg='var(--err-bg)';border='#fca5a5';clr='var(--err)';}
        else if(isCorr && !isSel){bg='var(--ok-bg)';border='#6ee7b7';clr='var(--ok)';}
        return `<div style="display:flex;align-items:center;gap:7px;padding:6px 10px;border-radius:6px;border:1.5px solid ${border};background:${bg};color:${clr};font-size:.78rem;margin-bottom:4px;">
          <strong style="min-width:18px;">${LETTERS[j]}</strong>${esc(o)}
          ${isSel?'<i class="fas fa-hand-pointer" style="margin-right:auto;font-size:.7rem;"></i>':''}
          ${isCorr&&!isSel?'<i class="fas fa-check" style="margin-right:auto;color:var(--ok);"></i>':''}
        </div>`;
      }).join('')}
      <div class="grade-note-row">
        <i class="fas fa-comment-dots"></i>
        <div class="gn-wrap">
          <div class="gn-lbl">ملاحظة / الإجابة الصحيحة للطفل:</div>
          <textarea class="grade-note-inp" id="${noteId}" data-sub="${sub.id}" data-qid="${q.id}" placeholder="${mcqNotePlaceholder}">${mcqNoteDefault}</textarea>
        </div>
      </div>
    </div>`;
  }).join('');

  const stud = (classStuCache['كل الفصول'] || []).find(x => x.id == sub.student_id);
  const photo = stud ? stud.photo : '';
  const avatar = getStudentAvatarHtml(photo, sub.student_name, '28px');

  el.innerHTML = `<div class="grade-sub-card" id="gradecard_${sub.id}" style="margin: 0; border: none; box-shadow: none;">
    <div class="grade-sub-name" style="padding: 12px 18px; background: var(--bg3); border-bottom: 1px solid var(--bdr);">
      ${avatar}
      <strong>${esc(sub.student_name||'—')}</strong>
      <span style="font-size:.72rem;color:var(--t3);font-weight:500;margin-right:5px;">${esc(sub.task_title||'')}</span>
      <span style="margin-right:auto;font-size:.72rem;" id="scoreDisp_${sub.id}"></span>
    </div>
    <div style="padding: 20px 24px; overflow-y: auto; flex: 1;">
      ${qRows}
    </div>
    <div style="padding: 16px 24px; background: var(--bg3); border-top: 1px solid var(--bdr); display: flex; justify-content: flex-end;">
      <button class="grade-save-btn" onclick="submitGrade(${sub.id}, ${si})" style="margin: 0; width: auto; padding: 10px 24px;">
        <i class="fas fa-check"></i> حفظ التصحيح وتحديث الكوبونات
      </button>
    </div>
  </div>`;
  
  updateSubScore(sub.id);
}

async function submitGrade(subId, subIdx) {
  const scores = {};
  document.querySelectorAll(`.grade-score-inp[data-sub="${subId}"]`).forEach(inp => {
    scores[inp.dataset.qid] = parseInt(inp.value)||0;
  });

  const notes = {};
  document.querySelectorAll(`.grade-note-inp[data-sub="${subId}"]`).forEach(ta => {
    if(ta.value.trim()) notes[ta.dataset.qid] = ta.value.trim();
  });

  const btn = document.querySelector(`#gradecard_${subId} .grade-save-btn`);
  const orig = btn.innerHTML; btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> جارٍ الحفظ…';

  try {
    const d = await api('gradeOpenAnswer', {
      submission_id: subId,
      scores: JSON.stringify(scores),
      notes: JSON.stringify(notes)
    });

    if(d.success){
      let couponMsg = '';
      if(d.coupon_diff > 0)       couponMsg = ` — تمت إضافة ${d.coupon_diff} كوبون لكوبونات التاسكات`;
      else if(d.coupon_diff < 0)  couponMsg = ` — تم خصم ${Math.abs(d.coupon_diff)} كوبون من كوبونات التاسكات`;
      else                         couponMsg = ' — لا تغيير في الكوبونات';

      showToast(`تم الحفظ: ${d.score}/${gradeTaskData?.total_degree||0} درجة${couponMsg}`, 'ok');

      gradeSubs.splice(subIdx, 1);
      renderGradePanel();

      await loadTasks();
      updatePendingBadge(gradeTaskId);
    }
  } catch(e) {
    showToast('خطأ في الاتصال', 'err');
    btn.disabled = false;
    btn.innerHTML = orig;
  }
}
function clampGradeInput(inp) {



  const max = parseInt(inp.dataset.max)||0;



  let v = parseInt(inp.value)||0;



  if(v<0) v=0; if(v>max) v=max;



  inp.value = v;



}







function updateSubScore(subId) {



  // Live preview of open question scores



  let openTotal = 0;



  document.querySelectorAll(`.grade-score-inp[data-sub="${subId}"]`).forEach(inp => {



    openTotal += parseInt(inp.value)||0;



  });



  const disp = document.getElementById(`scoreDisp_${subId}`);



  if(disp && gradeTaskData) {



    // Also add MCQ/TF score from submitted answers



    const sub = gradeSubs.find(s=>s.id==subId);



    if(sub) {



      const answers = JSON.parse(sub.answers||'{}');



      let mcqScore = 0;



      (gradeTaskData.questions||[]).forEach(q=>{



        if(q.question_type==='open') return;



        if(q.correct_index===null) return;



        const given = answers[String(q.id)];



        if(given !== undefined && parseInt(given)===parseInt(q.correct_index)) mcqScore += parseInt(q.degree||0);



      });



      const total = mcqScore + openTotal;



      const pct = gradeTaskData.total_degree>0?Math.round(total/gradeTaskData.total_degree*100):0;



      disp.innerHTML = `<span style="background:var(--brand-bg);color:var(--brand);border-radius:var(--r-full);padding:2px 9px;font-weight:800;">${total}/${gradeTaskData.total_degree} — ${pct}%</span>`;



    }



  }



}







async function submitGrade(subId, subIdx) {



  const scores = {};



  document.querySelectorAll(`.grade-score-inp[data-sub="${subId}"]`).forEach(inp => {



    scores[inp.dataset.qid] = parseInt(inp.value)||0;



  });



  const notes = {};



  document.querySelectorAll(`.grade-note-inp[data-sub="${subId}"]`).forEach(ta => {



    if(ta.value.trim()) notes[ta.dataset.qid] = ta.value.trim();



  });



  const btn = document.querySelector(`#gradecard_${subId} .grade-save-btn`);



  const orig = btn.innerHTML; btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> جارٍ الحفظ…';



  try {



    const d = await api('gradeOpenAnswer', {



      submission_id: subId,



      scores: JSON.stringify(scores),



      notes: JSON.stringify(notes)



    });



    if(d.success){



      // coupon_diff = change in task_coupons (positive = added, negative = removed, 0 = no change)



      let couponMsg = '';



      if(d.coupon_diff > 0)       couponMsg = ` — تمت إضافة ${d.coupon_diff} كوبون لكوبونات التاسكات`;



      else if(d.coupon_diff < 0)  couponMsg = ` — تم خصم ${Math.abs(d.coupon_diff)} كوبون من كوبونات التاسكات`;



      else                         couponMsg = ' — لا تغيير في الكوبونات';



      showToast(`تم الحفظ: ${d.score}/${gradeTaskData?.total_degree||0} درجة${couponMsg}`, 'ok');



      document.getElementById(`gradecard_${subId}`)?.remove();



      gradeSubs.splice(subIdx,1);



      if(!gradeSubs.length) renderGradePanel();



      // Reload tasks list so stats card + submission counts update



      await loadTasks();



      updatePendingBadge(gradeTaskId);



    } else showToast(d.message||'فشل','err');



  } catch(e){showToast('خطأ','err');}



  btn.disabled=false; btn.innerHTML=orig;



}







function closeGradePanel(){



  document.getElementById('gradePanel').classList.remove('open');



  document.body.style.overflow='';



}







function updatePendingBadge(taskId) {



  loadTasks();



}







function openModal(html) {
  const ov = document.createElement('div');
  ov.className = 'overlay open';
  ov.style.zIndex = '3000';
  ov.style.padding = '0';
  ov.style.alignItems = 'stretch';

  ov.innerHTML = `
    <div class="modal" style="width:100%; height:100vh; height:100dvh; max-width:100%; max-height:100vh; max-height:100dvh; margin:0; border-radius:0; display:flex; flex-direction:column; background:var(--bg3);">
      <div class="mhdr" style="background:var(--bg3); padding:16px 20px; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid var(--bdr);">
        <div style="display:flex; align-items:center; gap:10px;">
          <div style="width:32px;height:32px;border-radius:10px;background:var(--brand-bg);color:var(--brand);display:flex;align-items:center;justify-content:center;"><i class="fas fa-eye" style="font-size:.85rem;"></i></div>
          <div style="color:var(--t1);font-weight:800;font-size:1rem;font-family:'Cairo',sans-serif;">مراجعة الإجابات</div>
        </div>
        
        <!-- Navigation Arrows -->
        <div class="modal-nav-arrows" style="display:flex; align-items:center; gap:12px; margin-right:auto; margin-left:20px; direction:rtl;">
          <button onclick="navigateSubmission(-1)" id="prevSubBtn" style="background:var(--bg2); border:1px solid var(--bdr); color:var(--t1); width:36px; height:36px; border-radius:8px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:0.2s;" onmouseover="this.style.background='var(--brand-bg)'; this.style.color='var(--brand)';" onmouseout="this.style.background='var(--bg2)'; this.style.color='var(--t1)';"><i class="fas fa-chevron-right"></i></button>
          <span id="subIndexIndicator" style="font-size:0.8rem; font-weight:800; color:var(--t2); min-width:45px; text-align:center;">—</span>
          <button onclick="navigateSubmission(1)" id="nextSubBtn" style="background:var(--bg2); border:1px solid var(--bdr); color:var(--t1); width:36px; height:36px; border-radius:8px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:0.2s;" onmouseover="this.style.background='var(--brand-bg)'; this.style.color='var(--brand)';" onmouseout="this.style.background='var(--bg2)'; this.style.color='var(--t1)';"><i class="fas fa-chevron-left"></i></button>
        </div>

        <button onclick="this.closest('.overlay').remove(); document.documentElement.classList.remove('ov-open');" style="background:var(--bg2); border:1px solid var(--bdr); color:var(--t2); width:34px; height:34px; border-radius:10px; cursor:pointer; font-size:.85rem; display:flex; align-items:center; justify-content:center; transition:0.2s;" onmouseover="this.style.background='var(--err-bg)'; this.style.color='var(--err)';" onmouseout="this.style.background='var(--bg2)'; this.style.color='var(--t2)';"><i class="fas fa-times"></i></button>
      </div>
      <div class="mbody" id="modalSubmissionsBody" style="padding:0; flex:1; overflow-y:auto; background:var(--bg2);">${html}</div>
    </div>
  `;

  document.body.appendChild(ov);
  ov.onclick = (e) => { if(e.target === ov) { ov.remove(); document.documentElement.classList.remove('ov-open'); } };
  document.documentElement.classList.add('ov-open');
}

window.activeSubmissionList = [];
window.activeSubmissionIndex = -1;
window.activeSubmissionTaskId = null;

function navigateSubmission(dir) {
  if (!window.activeSubmissionList || window.activeSubmissionList.length === 0) return;
  
  let newIdx = window.activeSubmissionIndex + dir;
  if (newIdx < 0 || newIdx >= window.activeSubmissionList.length) return;
  
  window.activeSubmissionIndex = newIdx;
  const sub = window.activeSubmissionList[newIdx];
  
  updateSubmissionIndicator();
  
  const html = getSubmissionAnswersHtml(window.activeSubmissionTaskId, sub.student_id);
  document.getElementById('modalSubmissionsBody').innerHTML = html;
}

function updateSubmissionIndicator() {
  const indicator = document.getElementById('subIndexIndicator');
  const prevBtn = document.getElementById('prevSubBtn');
  const nextBtn = document.getElementById('nextSubBtn');
  
  if (indicator) {
    indicator.textContent = `${window.activeSubmissionIndex + 1} / ${window.activeSubmissionList.length}`;
  }
  if (prevBtn) {
    prevBtn.disabled = window.activeSubmissionIndex === 0;
    prevBtn.style.opacity = window.activeSubmissionIndex === 0 ? '0.4' : '1';
    prevBtn.style.cursor = window.activeSubmissionIndex === 0 ? 'not-allowed' : 'pointer';
  }
  if (nextBtn) {
    nextBtn.disabled = window.activeSubmissionIndex === window.activeSubmissionList.length - 1;
    nextBtn.style.opacity = window.activeSubmissionIndex === window.activeSubmissionList.length - 1 ? '0.4' : '1';
    nextBtn.style.cursor = window.activeSubmissionIndex === window.activeSubmissionList.length - 1 ? 'not-allowed' : 'pointer';
  }
}

function getSubmissionAnswersHtml(taskId, studentId) {
  const t = tasks.find(x=>x.id==taskId);
  if(!t) return '';

  const subs = (typeof detailTask !== 'undefined' && detailTask && detailTask.id == taskId) ? (detailTask.submissions || []) : (t.submissions || []);
  const sub = subs.find(x=>x.student_id==studentId);
  if(!sub) return '';

  const ans = typeof sub.answers === 'string' ? JSON.parse(sub.answers) : (sub.answers || {});
  const openScores = typeof sub.open_scores === 'string' ? JSON.parse(sub.open_scores) : (sub.open_scores || {});
  const correctionNotes = typeof sub.correction_notes === 'string' ? JSON.parse(sub.correction_notes) : (sub.correction_notes || {});

  const score = sub.score ?? sub.total_score ?? 0;
  const totalDeg = t.total_degree || 0;
  const pct = totalDeg > 0 ? Math.round(score / totalDeg * 100) : 0;
  const scoreColor = pct >= 80 ? 'var(--ok)' : pct >= 50 ? 'var(--warn)' : 'var(--err)';
  const scoreBg   = pct >= 80 ? 'var(--ok-bg)' : pct >= 50 ? 'var(--warn-bg)' : 'var(--err-bg)';

  const stud = (classStuCache['كل الفصول'] || []).find(x => x.id == studentId);
  const photo = stud ? stud.photo : '';
  const avatar = getStudentAvatarHtml(photo, sub.student_name, '40px');

  let html = `<div class="ans-shell">
    <div class="ans-head">
      <div class="ans-avatar" style="background:none;border:none;display:flex;align-items:center;justify-content:center;padding:0;">${avatar}</div>
      <div style="flex:1;min-width:0;">
        <div class="ans-name">${esc(sub.student_name)}</div>
        <div class="ans-sub">أجاب على التاسك: <strong>${esc(t.title||'')}</strong></div>
      </div>
      <div style="text-align:center;flex-shrink:0;">
        <div style="font-size:1.4rem;font-weight:900;color:${scoreColor};line-height:1;">${score}<span style="font-size:.85rem;font-weight:600;color:var(--t3);">/${totalDeg}</span></div>
        <div style="display:inline-flex;align-items:center;gap:4px;background:${scoreBg};color:${scoreColor};border-radius:var(--r-full);padding:2px 9px;font-size:.7rem;font-weight:700;margin-top:4px;">${pct}%</div>
      </div>
    </div>`;

  if(!t.questions || t.questions.length === 0) {
    html += `<div style="text-align:center;padding:40px;color:var(--t4);font-size:.88rem;"><i class="fas fa-question-circle" style="font-size:2rem;display:block;margin-bottom:10px;color:var(--t4);"></i>لا توجد أسئلة لهذا التاسك.</div>`;
  } else {
    t.questions.forEach((q, i) => {
      const qType = q.question_type || 'mcq';
      const given = ans[q.id] !== undefined ? ans[q.id] : ans[String(q.id)];
      const correctIdx = q.correct_index !== null ? parseInt(q.correct_index) : null;
      const imgH = q.image_url ? `<div style="margin:0 0 10px;border-radius:var(--r-md);overflow:hidden;border:1px solid var(--bdr);"><img src="${esc(q.image_url)}" alt="" style="width:100%;max-height:200px;object-fit:contain;display:block;background:var(--bg2);"></div>` : '';

      html += `<div class="ans-question">`;
      html += `<div class="ans-qhead">
        <div class="ans-qnum">${i+1}</div>
        <div class="ans-qtext">${esc(q.question_text)}</div>
        <div style="flex-shrink:0;font-size:.7rem;color:var(--t3);font-weight:600;padding:2px 7px;background:var(--bg2);border:1px solid var(--bdr);border-radius:var(--r-full);">${q.degree} درجة</div>
      </div>`;

      html += imgH;

      if(qType === 'open') {
        const hasAns = given && String(given).trim().length > 0;
        html += `<div class="ans-open">
          <div class="ans-open-label">إجابة الطفل (إجابة مفتوحة):</div>
          <div class="ans-open-text" style="${!hasAns?'color:var(--t4);font-style:italic;':''}">${hasAns ? esc(given) : '— لم يُجب على هذا السؤال —'}</div>
        </div>`;

        if (sub.is_graded == 1 || openScores[q.id] !== undefined || openScores[String(q.id)] !== undefined) {
          const openScoreVal = openScores[q.id] !== undefined ? openScores[q.id] : openScores[String(q.id)];
          const corrNoteVal = correctionNotes[q.id] !== undefined ? correctionNotes[q.id] : correctionNotes[String(q.id)];
          const scoreDisplay = openScoreVal !== undefined ? openScoreVal : 0;
          html += `
          <div style="margin-top: 10px; padding: 10px; border-radius: var(--r-md); background: var(--bg2); border: 1px solid var(--bdr);">
            <div style="font-weight: 700; font-size: 0.85rem; color: var(--ok); margin-bottom: 5px;">
              <i class="fas fa-check-double"></i> درجة تصحيح السؤال: 
              <span style="font-size: 1rem; color: var(--t1); font-weight: 900;">${scoreDisplay}</span> من <span>${q.degree}</span>
            </div>`;
          if (corrNoteVal && String(corrNoteVal).trim().length > 0) {
            html += `
            <div style="font-size: 0.8rem; color: var(--t2); margin-top: 5px;">
              <strong style="color: var(--t3);"><i class="fas fa-comment-dots"></i> ملاحظات التصحيح:</strong> 
              <span>${esc(corrNoteVal)}</span>
            </div>`;
          }
          html += `</div>`;
        }
      } else {
        const opts = typeof q.options === 'string' ? JSON.parse(q.options) : (q.options || []);
        if(qType === 'tf') { opts[0] = 'صواب'; opts[1] = 'خطأ'; }

        html += `<div style="display:flex;flex-direction:column;gap:7px;">`;
        opts.forEach((o, j) => {
          const isCorr = j === correctIdx;
          const isSel  = given !== undefined && parseInt(given) === j;
          let cls = '';
          let icon = '';

          if(isCorr && isSel)  { cls='correct'; icon=`<i class="fas fa-check-circle ans-choice-icon" style="color:var(--ok);"></i>`; }
          else if(isCorr)       { cls='correct'; icon=`<i class="fas fa-check ans-choice-icon" style="color:var(--ok);opacity:.55;"></i>`; }
          else if(isSel)        { cls='wrong';   icon=`<i class="fas fa-times-circle ans-choice-icon" style="color:var(--err);"></i>`; }

          html += `<div class="ans-choice ${cls}" style="${isSel?'font-weight:700;':''}" >
            <span class="ans-choice-letter">${LETTERS[j]||j+1}</span>
            <span>${esc(o)}</span>
            ${icon}
            ${isSel ? `<span style="font-size:.65rem;font-weight:700;padding:1px 7px;border-radius:var(--r-full);background:${isCorr?'var(--ok)':'var(--err)'};color:#fff;flex-shrink:0;">${isCorr?'إجابتك ✓':'إجابتك ✗'}</span>` : ''}
          </div>`;
        });
        html += `</div>`;
      }
      html += `</div>`;
    });
  }
  html += `</div>`;
  return html;
}

function viewAnswers(taskId, studentId) {
  const t = tasks.find(x=>x.id==taskId);
  if(!t) return;

  const subs = (typeof detailTask !== 'undefined' && detailTask && detailTask.id == taskId) ? (detailTask.submissions || []) : (t.submissions || []);
  
  window.activeSubmissionList = subs;
  window.activeSubmissionIndex = subs.findIndex(x => x.student_id == studentId);
  window.activeSubmissionTaskId = taskId;

  const html = getSubmissionAnswersHtml(taskId, studentId);
  openModal(html);
  updateSubmissionIndicator();
}
// ─── Overlay helpers ─────────────────────────────────────────────



function openOv(id){document.getElementById(id).classList.add('open');document.body.style.overflow='hidden';}



function closeOv(id){document.getElementById(id).classList.remove('open');document.body.style.overflow='';}



function closeCreate(){closeOv('createOv');}



function closeDetail(){closeOv('detailOv');}



function closeConf(){closeOv('confOv');}



function overlayOnBg(){



  ['createOv','detailOv','confOv'].forEach(id=>



    document.getElementById(id).addEventListener('click',function(e){if(e.target===this)closeOv(id);})



  );



}







// ─── Utils ───────────────────────────────────────────────────────



function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}



function fmtDate(iso){if(!iso)return'—';return new Date(iso).toLocaleDateString('ar-EG',{day:'numeric',month:'short',hour:'2-digit',minute:'2-digit'});}



function toLocalDT(iso){if(!iso)return'';const d=new Date(iso);const p=n=>String(n).padStart(2,'0');return`${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;}



function toLocalDateOnly(iso){if(!iso)return'';const d=new Date(iso);const p=n=>String(n).padStart(2,'0');return`${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}`;}



function isEndDateOnly(iso){



  if(!iso) return false;



  const d = new Date(iso);



  return d.getHours()===23 && d.getMinutes()===59;



}



function toggleNoDeadline(sync=false){



  const noDeadline = document.getElementById('fNoDeadline').checked;



  const block = document.getElementById('deadlineBlock');



  const endDateMode = document.getElementById('fEndDateMode');



  const note = document.getElementById('endModeNote');



  if(sync && !noDeadline){



    toggleEndDateMode(false);



  }



  if(block) block.classList.toggle('is-disabled', noDeadline);



  if(endDateMode) endDateMode.disabled = noDeadline;



  if(note && noDeadline){



    note.textContent = 'لن يكون هناك آخر موعد، وستظل التاسك متاحة بعد البداية.';



  } else if(note) {



    note.textContent = document.getElementById('fEndDateMode').checked



      ? 'سيُغلق الامتحان تلقائياً في نهاية هذا اليوم.'



      : 'سيُغلق الامتحان في الساعة التي تحددها هنا.';



  }



}



function syncDateOnlyFromDateTime(){



  const dt = document.getElementById('fEnd').value;



  if(!dt) return;



  document.getElementById('fEndDateOnly').value = dt.split('T')[0];



}



function syncDateTimeFromDateOnly(){



  const dateOnly = document.getElementById('fEndDateOnly').value;



  if(!dateOnly) return;



  document.getElementById('fEnd').value = `${dateOnly}T23:59`;



}



function toggleEndDateMode(syncFromCurrent=true){



  if(document.getElementById('fNoDeadline').checked) return;



  const dateMode = document.getElementById('fEndDateMode').checked;



  const endInput = document.getElementById('fEnd');



  const endDateOnly = document.getElementById('fEndDateOnly');



  const note = document.getElementById('endModeNote');



  if(syncFromCurrent){



    if(dateMode) syncDateOnlyFromDateTime();



    else syncDateTimeFromDateOnly();



  }



  endInput.style.display = dateMode ? 'none' : '';



  endDateOnly.style.display = dateMode ? '' : 'none';



  note.textContent = dateMode



    ? 'سيُغلق الامتحان تلقائياً في نهاية هذا اليوم.'



    : 'سيُغلق الامتحان في الساعة التي تحددها هنا.';



}



function getNormalizedEndDateValue(){



  if(document.getElementById('fNoDeadline').checked) return '';



  const dateMode = document.getElementById('fEndDateMode').checked;



  if(dateMode){



    const dateOnly = document.getElementById('fEndDateOnly').value;



    return dateOnly ? `${dateOnly}T23:59` : '';



  }



  return document.getElementById('fEnd').value;



}



function applyDuePreset(days){



  if(document.getElementById('fNoDeadline').checked) return;



  const startVal = document.getElementById('fStart').value;



  const base = startVal ? new Date(startVal) : new Date();



  if(Number.isNaN(base.getTime())) return;



  const target = new Date(base.getTime());



  target.setDate(target.getDate() + days);



  const p=n=>String(n).padStart(2,'0');



  const dateOnly = `${target.getFullYear()}-${p(target.getMonth()+1)}-${p(target.getDate())}`;



  document.getElementById('fEndDateOnly').value = dateOnly;



  document.getElementById('fEnd').value = `${dateOnly}T23:59`;



  if(document.getElementById('fEndDateMode').checked){



    document.getElementById('endModeNote').textContent = 'سيُغلق الامتحان تلقائياً في نهاية هذا اليوم.';



  }



}



function setDefaultDates(){



  const now=new Date();const p=n=>String(n).padStart(2,'0');



  const f=d=>`${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`;



  const fd=d=>`${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}`;



  const weekLater = new Date(now.getTime()+7*24*60*60*1000);



  document.getElementById('fStart').value=f(now);



  document.getElementById('fEnd').value=f(weekLater);



  document.getElementById('fEndDateOnly').value=fd(weekLater);



}



function showToast(msg,type='info'){



  const tc=document.getElementById('tc');



  const t=document.createElement('div');



  t.className=`toast ${type}`;



  const ic=type==='ok'?'fa-check-circle':type==='err'?'fa-exclamation-circle':'fa-info-circle';



  t.innerHTML=`<i class="fas ${ic}"></i>${msg}`;



  tc.appendChild(t);



  requestAnimationFrame(()=>requestAnimationFrame(()=>t.classList.add('show')));



  setTimeout(()=>{t.classList.remove('show');setTimeout(()=>t.remove(),350);},3200);



}

// ─── Tasks Overview and Export Logic ──────────────────────────────────────────

async function openTasksOverviewModal() {
  showToast('جاري جلب البيانات...', 'info');
  try {
    await loadStudents('كل الفصول');
  } catch(e) {
    console.error(e);
  }
  
  openOv('overviewOv');
  buildOverviewClassesList();
  renderOverviewTable();
}

function buildOverviewClassesList() {
  const container = document.getElementById('ovClassesList');
  if (!container) return;
  container.innerHTML = '';
  
  allClasses.forEach(cl => {
    const wrap = document.createElement('label');
    wrap.className = 'ov-class-label';
    wrap.style = "display:flex; align-items:center; gap:6px; cursor:pointer; font-size:.8rem; background:var(--bg-card); padding:4px 8px; border-radius:6px; border:1px solid var(--bdr); transition:all 0.2s;";
    
    const chk = document.createElement('input');
    chk.type = 'checkbox';
    chk.value = cl.arabic_name;
    chk.className = 'ov-class-checkbox';
    chk.style.accentColor = 'var(--brand)';
    chk.style.width = '14px';
    chk.style.height = '14px';
    chk.style.cursor = 'pointer';
    
    if (!CFG.activeClass || CFG.activeClass === 'كل الفصول' || cl.arabic_name === CFG.activeClass) {
      chk.checked = true;
    }
    
    chk.onchange = renderOverviewTable;
    
    wrap.appendChild(chk);
    wrap.appendChild(document.createTextNode(' ' + cl.arabic_name));
    container.appendChild(wrap);
  });
}

function renderOverviewTable() {
  const tableContainer = document.getElementById('ovTableContainer');
  const statsText = document.getElementById('ovStatsText');
  if (!tableContainer) return;
  
  const showGrades = document.getElementById('ovShowGrades').checked;
  const showTime = document.getElementById('ovShowTime').checked;
  const statusFilter = document.getElementById('ovAnswerStatus').value;
  const searchVal = document.getElementById('ovStudentSearch').value.trim();
  
  const checkedClassNames = Array.from(document.querySelectorAll('.ov-class-checkbox:checked')).map(cb => cb.value);
  
  if (checkedClassNames.length === 0) {
    tableContainer.innerHTML = '<div style="text-align:center; padding:40px; color:var(--t3);">برجاء تحديد فصل واحد على الأقل للعرض.</div>';
    statsText.textContent = 'العدد: 0 | عدد التاسكات: 0';
    return;
  }
  
  let targetStudents = [];
  checkedClassNames.forEach(cn => {
    const list = classStuCache[cn] || [];
    list.forEach(s => {
      if (!targetStudents.some(item => item.id === s.id)) {
        targetStudents.push({ ...s, className: cn });
      }
    });
  });
  
  const checkedClassIds = checkedClassNames.map(cn => {
    const found = allClasses.find(c => c.arabic_name === cn);
    return found ? String(found.id) : null;
  }).filter(Boolean);
  
  const targetTasks = tasks.filter(t => {
    if (t.status === 'draft') return false;
    if (t.class_id === 0 || t.class_name === 'كل الفصول') return true;
    if (checkedClassNames.includes(t.class_name)) return true;
    if (t.class_ids) {
      const ids = String(t.class_ids).split(',');
      if (ids.some(id => checkedClassIds.includes(id))) return true;
    }
    return false;
  });
  
  targetTasks.sort((a, b) => new Date(a.created_at) - new Date(b.created_at));
  
  const subMap = {};
  targetTasks.forEach(t => {
    const subs = t.submissions || [];
    subs.forEach(sub => {
      subMap[sub.student_id + '_' + t.id] = sub;
    });
  });
  
  if (searchVal && targetStudents.length > 0 && typeof getMatchScore === 'function') {
    targetStudents = targetStudents.map(s => {
      const score = getMatchScore(s, searchVal, [
        { val: s.name, weight: 1.0 },
        { val: s.className, weight: 0.5 }
      ]);
      return { ...s, _score: score };
    }).filter(s => s._score > 0)
      .sort((a, b) => b._score - a._score);
  } else {
    targetStudents.sort((a, b) => a.name.localeCompare(b.name, 'ar'));
  }
  
  if (targetTasks.length > 0) {
    targetStudents = targetStudents.filter(s => {
      const solvedCount = targetTasks.filter(t => subMap[s.id + '_' + t.id]).length;
      if (statusFilter === 'answered') {
        return solvedCount > 0;
      } else if (statusFilter === 'unanswered') {
        return solvedCount === 0;
      } else if (statusFilter === 'missing') {
        return solvedCount < targetTasks.length;
      }
      return true;
    });
  }
  
  if (targetTasks.length === 0) {
    tableContainer.innerHTML = '<div style="text-align:center; padding:40px; color:var(--t3);">لا توجد تاسكات منشورة لهذه الفصول بعد.</div>';
    statsText.textContent = `العدد: ${targetStudents.length} | عدد التاسكات: 0`;
    return;
  }

  const onlyResList = document.getElementById('ovOnlyResList') && document.getElementById('ovOnlyResList').checked;
  if (onlyResList) {
    let html = `<table class="ov-table" id="ovExportTable">`;
    html += `<thead><tr>`;
    html += `<th style="width:30%;">اسم التاسك</th>`;
    html += `<th style="width:15%; text-align:center;">الفصل</th>`;
    html += `<th style="width:15%; text-align:center;">عدد المجيبين</th>`;
    html += `<th style="width:40%;">الذين أجابوا</th>`;
    html += `</tr></thead><tbody>`;

    targetTasks.forEach(t => {
      const respondents = targetStudents.filter(s => subMap[s.id + '_' + t.id]);
      let namesText = respondents.map((s, idx) => {
        const avatar = getStudentAvatarHtml(s.photo, s.name, '20px');
        if (showGrades) {
          const sub = subMap[s.id + '_' + t.id];
          const scoreVal = parseInt(sub.score);
          const totalVal = parseInt(t.total_degree || sub.total_degree || 0);
          return `<span style="display:inline-flex; align-items:center; gap:5px; margin:2px 4px; padding:4px 8px; background:var(--brand-bg); color:var(--brand); border-radius:6px; font-size:0.78rem;">${idx + 1}. ${avatar} ${esc(s.name)} (${scoreVal}/${totalVal})</span>`;
        } else {
          return `<span style="display:inline-flex; align-items:center; gap:5px; margin:2px 4px; padding:4px 8px; background:var(--bg-card); color:var(--t1); border:1px solid var(--bdr); border-radius:6px; font-size:0.78rem;">${idx + 1}. ${avatar} ${esc(s.name)}</span>`;
        }
      }).join(' ');

      if (respondents.length === 0) {
        namesText = `<span style="color:var(--t3); font-style:italic;">لا يوجد مجيبين بعد</span>`;
      }

      html += `<tr>`;
      html += `<td><strong>${esc(t.title)}</strong></td>`;
      html += `<td style="text-align:center;"><span class="ov-class-badge">${esc(t.class_name || 'كل الفصول')}</span></td>`;
      html += `<td style="text-align:center;"><strong style="color:var(--brand);">${respondents.length}</strong> / ${targetStudents.length}</td>`;
      html += `<td>${namesText}</td>`;
      html += `</tr>`;
    });

    html += `</tbody></table>`;
    tableContainer.innerHTML = html;
    statsText.textContent = `العدد: ${targetStudents.length} | عدد التاسكات: ${targetTasks.length}`;
    return;
  }
  
  let html = `<table class="ov-table" id="ovExportTable">`;
  html += `<thead><tr>`;
  html += `<th style="min-width:180px;">الاسم</th>`;
  html += `<th style="min-width:100px;">الفصل</th>`;
  
  targetTasks.forEach(t => {
    html += `<th style="min-width:120px; text-align:center;" title="${esc(t.title)}">${esc(t.title)}</th>`;
  });
  html += `</tr></thead><tbody>`;
  
  if (targetStudents.length === 0) {
    html += `<tr><td colspan="${targetTasks.length + 2}" style="text-align:center; padding:30px; color:var(--t3);">لا توجد نتائج تطابق خيارات البحث والترشيح.</td></tr>`;
  } else {
    targetStudents.forEach(s => {
      html += `<tr>`;
      const avatar = getStudentAvatarHtml(s.photo, s.name, '24px');
      html += `<td><div style="display:flex; align-items:center; gap:8px;">${avatar} <strong>${esc(s.name)}</strong></div></td>`;
      html += `<td><span class="ov-class-badge">${esc(s.className)}</span></td>`;
      
      targetTasks.forEach(t => {
        const sub = subMap[s.id + '_' + t.id];
        html += `<td style="text-align:center;">`;
        if (sub) {
          if (showGrades) {
            const scoreVal = parseInt(sub.score);
            const totalVal = parseInt(t.total_degree || sub.total_degree || 0);
            const pct = totalVal > 0 ? (scoreVal / totalVal) : 0;
            const isPass = pct >= 0.5;
            html += `<span class="ov-grade-badge ${isPass ? 'ov-grade-pass' : 'ov-grade-fail'}">${scoreVal}/${totalVal}</span>`;
          } else {
            html += `<span class="ov-cell-answered"><i class="fas fa-check-circle"></i> أجاب</span>`;
          }
          if (showTime && sub.submitted_at) {
            const dateObj = new Date(sub.submitted_at);
            const dateStr = `${dateObj.getDate()}/${dateObj.getMonth() + 1} ${String(dateObj.getHours()).padStart(2, '0')}:${String(dateObj.getMinutes()).padStart(2, '0')}`;
            html += `<span class="ov-time-text">${dateStr}</span>`;
          }
        } else {
          html += `<span class="ov-cell-unanswered"><i class="fas fa-times-circle" style="color:var(--err);"></i> لم يجب</span>`;
        }
        html += `</td>`;
      });
      html += `</tr>`;
    });
  }
  
  html += `</tbody></table>`;
  tableContainer.innerHTML = html;
  
  statsText.textContent = `العدد: ${targetStudents.length} | عدد التاسكات: ${targetTasks.length}`;
}

function exportOverviewCSV() {
  const table = document.getElementById('ovExportTable');
  if (!table) { showToast('لا توجد بيانات لتصديرها', 'err'); return; }
  
  let csv = [];
  const rows = table.querySelectorAll('tr');
  
  rows.forEach(tr => {
    let row = [];
    const cols = tr.querySelectorAll('th, td');
    cols.forEach(col => {
      let text = col.innerText.trim().replace(/"/g, '""');
      text = text.replace(/[\n\r]+/g, ' ');
      row.push('"' + text + '"');
    });
    csv.push(row.join(','));
  });
  
  const csvContent = "\ufeff" + csv.join("\n");
  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.setAttribute('href', url);
  link.setAttribute('download', `overview_tasks_${new Date().toISOString().slice(0,10)}.csv`);
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);
  showToast('تم تصدير ملف CSV بنجاح', 'ok');
}

function copyOverviewMessage() {
  const table = document.getElementById('ovExportTable');
  if (!table) { showToast('لا توجد بيانات لنسخها', 'err'); return; }
  
  const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.innerText.trim());
  const rows = Array.from(table.querySelectorAll('tbody tr'));
  
  let msg = `📊 *تقرير حل التاسكات والاختبارات*\n`;
  msg += `📅 التاريخ: ${new Date().toLocaleDateString('ar-EG')}\n\n`;
  
  msg += `📋 *التاسكات:* \n`;
  for (let i = 2; i < headers.length; i++) {
    msg += ` ${i-1}- ${headers[i]}\n`;
  }
  msg += `\n`;
  
  msg += `👤 *الأسماء:*\n`;
  rows.forEach(tr => {
    const cols = tr.querySelectorAll('td');
    if (cols.length < 2) return;
    const name = cols[0].innerText.trim();
    const cls = cols[1].innerText.trim();
    
    msg += `• *${name}* (${cls}):\n`;
    for (let i = 2; i < cols.length; i++) {
      const taskTitle = headers[i];
      const statusText = cols[i].innerText.trim().replace(/\s+/g, ' ');
      msg += `   - ${taskTitle}: ${statusText}\n`;
    }
  });
  
  navigator.clipboard.writeText(msg).then(() => {
    showToast('تم نسخ التقرير كرسالة', 'ok');
  }).catch(() => {
    const textarea = document.createElement('textarea');
    textarea.value = msg;
    document.body.appendChild(textarea);
    textarea.select();
    try {
      document.execCommand('copy');
      showToast('تم نسخ التقرير كرسالة', 'ok');
    } catch(err) {
      showToast('فشل نسخ التقرير تلقائياً', 'err');
    }
    document.body.removeChild(textarea);
  });
}

async function getExportCanvas() {
  const container = document.getElementById('ovTableContainer');
  if (!container) return null;
  
  // Clone the container
  const clone = container.cloneNode(true);
  
  // Calculate dynamic export width based on orientation and column counts
  const orientationVal = document.getElementById('ovOrientation') ? document.getElementById('ovOrientation').value : 'landscape';
  const ths = clone.querySelectorAll('thead th');
  let exportWidth = 1200;
  
  if (orientationVal === 'portrait') {
    exportWidth = 850; // Constrain width so chips and cells wrap vertically for portrait printing
  } else {
    if (ths.length > 4) {
      exportWidth = Math.max(1200, ths.length * 135);
    }
  }
  
  // Reset styles for all table components to ensure static, full-size rendering
  clone.style.position = 'absolute';
  clone.style.top = '0';
  clone.style.left = '-9999px';
  clone.style.width = exportWidth + 'px';
  clone.style.height = 'auto';
  clone.style.overflow = 'visible';
  clone.style.background = '#ffffff';
  clone.style.padding = '25px 25px 50px 25px'; // Increased bottom padding to prevent bottom row cropping
  
  const tables = clone.querySelectorAll('table');
  tables.forEach(t => {
    t.style.width = '100%';
    t.style.maxWidth = '100%';
    t.style.overflow = 'visible';
    t.style.margin = '0';
    t.style.borderCollapse = 'collapse';
  });
  
  const headers = clone.querySelectorAll('th');
  headers.forEach(th => {
    th.style.position = 'static';
    th.style.background = 'var(--brand-bg, #eef0ff)';
    th.style.color = 'var(--brand, #5b6cf5)';
  });
  
  // Add a beautiful print header at the top of the clone
  const statsText = document.getElementById('ovStatsText') ? document.getElementById('ovStatsText').innerText : '';
  const headerDiv = document.createElement('div');
  headerDiv.style.direction = 'rtl';
  headerDiv.style.fontFamily = "'Baloo Bhaijaan 2', 'Segoe UI', Tahoma, sans-serif";
  headerDiv.style.marginBottom = '25px';
  headerDiv.style.borderBottom = '3px solid var(--brand, #5b6cf5)';
  headerDiv.style.paddingBottom = '15px';
  headerDiv.style.display = 'flex';
  headerDiv.style.justifyContent = 'space-between';
  headerDiv.style.alignItems = 'center';
  
  headerDiv.innerHTML = `
    <div>
      <h2 style="margin:0 0 5px 0; color:#1a1d2e; font-size:1.6rem; font-weight:800;">📋 تقرير متابعة التاسكات والاختبارات</h2>
      <p style="margin:0; color:#4b5068; font-size:0.95rem;">التاريخ: ${new Date().toLocaleDateString('ar-EG')} | ${statsText}</p>
    </div>
    <div style="text-align:left; direction: ltr;">
      <span style="font-size:1.3rem; font-weight:800; color:var(--brand, #5b6cf5); font-family: 'Baloo Bhaijaan 2', sans-serif;">Sunday School</span>
    </div>
  `;
  
  clone.insertBefore(headerDiv, clone.firstChild);
  
  // Append a spacer at the bottom to guarantee no bottom clipping
  const spacer = document.createElement('div');
  spacer.style.height = '30px';
  clone.appendChild(spacer);
  
  document.body.appendChild(clone);
  
  // Let the browser lay it out
  await new Promise(r => setTimeout(r, 150));
  
  try {
    const canvas = await html2canvas(clone, {
      scale: 2, // High DPI capture
      useCORS: true,
      backgroundColor: '#ffffff',
      logging: false,
      allowTaint: false,
      width: clone.scrollWidth,
      height: clone.scrollHeight,
      windowWidth: clone.scrollWidth + 100,
      windowHeight: clone.scrollHeight + 100
    });
    return canvas;
  } finally {
    document.body.removeChild(clone);
  }
}

async function exportOverviewImage() {
  const container = document.getElementById('ovTableContainer');
  if (!container) { showToast('لا توجد بيانات لتصديرها', 'err'); return; }
  
  showToast('جاري إعداد الصورة...', 'info');
  
  try {
    const canvas = await getExportCanvas();
    if (!canvas) { showToast('فشل تصدير الصورة', 'err'); return; }
    
    const link = document.createElement('a');
    link.download = `overview_tasks_${new Date().toISOString().slice(0,10)}.png`;
    link.href = canvas.toDataURL('image/png');
    link.click();
    showToast('تم حفظ الصورة بنجاح', 'ok');
  } catch(e) {
    console.error(e);
    showToast('فشل تصدير الصورة', 'err');
  }
}

async function exportOverviewPDF() {
  const container = document.getElementById('ovTableContainer');
  if (!container) { showToast('لا توجد بيانات لتصديرها', 'err'); return; }
  
  showToast('جاري إنشاء PDF...', 'info');
  
  try {
    const { jsPDF } = window.jspdf;
    if (!jsPDF) { showToast('مكتبة PDF غير محملة', 'err'); return; }
    
    const canvas = await getExportCanvas();
    if (!canvas) { showToast('فشل إنشاء PDF', 'err'); return; }
    
    const orientationVal = document.getElementById('ovOrientation') ? document.getElementById('ovOrientation').value : 'landscape';
    const pdf = new jsPDF({ orientation: orientationVal, unit: 'mm', format: 'a4' });
    const pageW = pdf.internal.pageSize.getWidth();
    const pageH = pdf.internal.pageSize.getHeight();
    const imgW = pageW - 12;
    const imgH = canvas.height * imgW / canvas.width;
    const img = canvas.toDataURL('image/png');
    
    let y = 6;
    let remaining = imgH;
    
    pdf.addImage(img, 'PNG', 6, y, imgW, imgH);
    while (remaining > pageH - 12) {
      remaining -= pageH - 12;
      pdf.addPage();
      pdf.addImage(img, 'PNG', 6, 6 - (imgH - remaining), imgW, imgH);
    }
    
    pdf.save(`overview_tasks_${new Date().toISOString().slice(0,10)}.pdf`);
    showToast('تم حفظ PDF بنجاح', 'ok');
  } catch (e) {
    console.error(e);
    showToast('فشل إنشاء PDF: ' + e.message, 'err');
  }
}
</script>







<!-- ══ GRADING PANEL ══ -->



<div class="grade-panel" id="gradePanel">



  <div class="grade-sheet">



    <div class="grade-sheet-hdr" style="background:var(--bg3); padding:16px 20px; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid var(--bdr);">
      <div style="display:flex; align-items:center; gap:10px;">
        <div style="width:38px;height:38px;border-radius:10px;background:var(--warn-bg);color:var(--warn);display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-pen-nib"></i></div>
        <div style="flex:1;">
          <div style="font-size:1rem;font-weight:800;color:var(--t1);">تصحيح الإجابات المفتوحة</div>
          <div style="font-size:.72rem;color:var(--t3);" id="gradePanelSub">أدخل الدرجة لكل إجابة</div>
        </div>
      </div>
      
      <!-- Navigation Arrows -->
      <div class="grade-nav-arrows" style="display:flex; align-items:center; gap:12px; margin-right:auto; margin-left:20px; direction:rtl;">
        <button onclick="navigateGrading(-1)" id="prevGradeBtn" style="background:var(--bg2); border:1px solid var(--bdr); color:var(--t1); width:36px; height:36px; border-radius:8px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:0.2s;" onmouseover="this.style.background='var(--brand-bg)'; this.style.color='var(--brand)';" onmouseout="this.style.background='var(--bg2)'; this.style.color='var(--t1)';"><i class="fas fa-chevron-right"></i></button>
        <span id="gradeIndexIndicator" style="font-size:0.8rem; font-weight:800; color:var(--t2); min-width:45px; text-align:center;">—</span>
        <button onclick="navigateGrading(1)" id="nextGradeBtn" style="background:var(--bg2); border:1px solid var(--bdr); color:var(--t1); width:36px; height:36px; border-radius:8px; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:0.2s;" onmouseover="this.style.background='var(--brand-bg)'; this.style.color='var(--brand)';" onmouseout="this.style.background='var(--bg2)'; this.style.color='var(--t1)';"><i class="fas fa-chevron-left"></i></button>
      </div>

      <button onclick="closeGradePanel()" style="background:var(--bg2); border:1px solid var(--bdr); color:var(--t2); width:34px; height:34px; border-radius:10px; cursor:pointer; font-size:.85rem; display:flex; align-items:center; justify-content:center; transition:0.2s;" onmouseover="this.style.background='var(--err-bg)'; this.style.color='var(--err)';" onmouseout="this.style.background='var(--bg2)'; this.style.color='var(--t2)';"><i class="fas fa-times"></i></button>
    </div>



    <div class="grade-sheet-body" id="gradePanelBody">



      <div class="skeleton-row"><div class="skeleton-line" style="height:14px; width:45%;"></div><div class="skeleton-line" style="height:28px; width:60px; border-radius:6px;"></div></div>
      <div class="skeleton-row"><div class="skeleton-line" style="height:14px; width:55%;"></div><div class="skeleton-line" style="height:28px; width:60px; border-radius:6px;"></div></div>
      <div class="skeleton-row"><div class="skeleton-line" style="height:14px; width:35%;"></div><div class="skeleton-line" style="height:28px; width:60px; border-radius:6px;"></div></div>



    </div>



  </div>



</div>



</body>



</html>



