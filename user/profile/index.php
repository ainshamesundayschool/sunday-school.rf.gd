<?php
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);
ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 365 * 10);

// Robust local session directory to prevent aggressive shared hosting garbage collection
$rootPath = dirname(__FILE__);
while ($rootPath && !file_exists($rootPath . '/api.php')) {
  $parent = dirname($rootPath);
  if ($parent === $rootPath)
    break;
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

// Include config.php for VAPID keys
if (file_exists($rootPath . '/config.php')) {
  require_once $rootPath . '/config.php';
}
$studentIdFromUrl = isset($_GET['id']) ? intval($_GET['id']) : null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
  session_destroy();
  echo '<script>["savedUsername","savedPassword","rememberMe","userPhone","loginType"].forEach(k=>localStorage.removeItem(k));window.location.href="/user/login";</script>';
  exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
    <!-- ═══ Social Preview Defaults ═══ -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Sunday School">
    <meta property="og:title" content="بوابة الطفل">
    <meta property="og:description" content="منصة متكاملة لإدارة مدارس الأحد — الحضور، الكوبونات، الرحلات / المؤتمرات والمزيد">
    <meta property="og:url" content="https://sunday-school.online/user/profile/">
    <meta property="og:image" content="https://sunday-school.online/imgs/Sunday-School-Og.png">
    <meta property="og:image:width" content="1000">
    <meta property="og:image:height" content="1000">
    <meta property="og:image:type" content="image/png">
    <meta property="og:image:alt" content="Sunday School">
    <meta property="og:locale" content="ar_AR">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="بوابة الطفل">
    <meta name="twitter:description" content="منصة متكاملة لإدارة مدارس الأحد — الحضور، الكوبونات، الرحلات / المؤتمرات والمزيد">
    <meta name="twitter:image" content="https://sunday-school.online/imgs/Sunday%20School%20App.png">

  <link rel="manifest" href="/manifest.json">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="mobile-web-app-capable" content="yes">
  <title id="pageTitle">بوابة الطفل</title>
  <meta name="theme-color" content="#4f46e5">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Baloo+Bhaijaan+2:wght@400;500;600;700;800&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
  <link rel="icon" href="/favicon.ico">
  <style>
    /* Hide all tabs and non-info/non-coupon sections in kid profile view */
    .view-other-mode .bottom-nav {
      display: none !important;
    }
    .view-other-mode #scTrips,
    .view-other-mode #scAtt,
    .view-other-mode #scAnn,
    .view-other-mode #scSiblings,
    .view-other-mode #scUncles,
    .view-other-mode #scTasks,
    .view-other-mode #scPaperExams,
    .view-other-mode .stats-bar {
      display: none !important;
    }
    .view-other-mode .page {
      padding-bottom: 24px !important;
    }

    /* ══ TOKENS ══════════════════════════════════════════════════ */
    :root {
      --brand: #4f46e5;
      --brand-d: #3730a3;
      --brand-l: #818cf8;
      --brand-bg: #eef2ff;
      --brand-glow: rgba(79, 70, 229, .18);
      --ok: #059669;
      --ok-l: #10b981;
      --ok-bg: #d1fae5;
      --err: #dc2626;
      --err-l: #ef4444;
      --err-bg: #fee2e2;
      --warn: #d97706;
      --warn-l: #f59e0b;
      --warn-bg: #fef3c7;
      --cou: #7c3aed;
      --cou-l: #8b5cf6;
      --cou-bg: #ede9fe;
      --trip: #0369a1;
      --trip-l: #0ea5e9;
      --trip-bg: #e0f2fe;
      --gold: #b45309;
      --gold-l: #f59e0b;
      --gold-bg: #fef3c7;
      --t1: #0f172a;
      --t2: #334155;
      --t3: #64748b;
      --t4: #94a3b8;
      --t5: #cbd5e1;
      --bg: #f1f5f9;
      --surf: #fff;
      --s2: #f8fafc;
      --bdr: #e2e8f0;
      --bdr2: #f1f5f9;
      --r-xs: 6px;
      --r-sm: 10px;
      --r-md: 16px;
      --r-lg: 22px;
      --r-xl: 30px;
      --r-2xl: 42px;
      --r-full: 9999px;
      --sh-sm: 0 1px 6px rgba(0, 0, 0, .05);
      --sh-md: 0 6px 20px rgba(0, 0, 0, .08);
      --sh-lg: 0 16px 40px rgba(0, 0, 0, .11);
      --sh-xl: 0 30px 60px rgba(0, 0, 0, .14);
      --sh-brand: 0 8px 24px rgba(79, 70, 229, .3);
      --ease: cubic-bezier(.4, 0, .2, 1);
      --spring: cubic-bezier(.16, 1, .3, 1);
      --fast: .15s var(--ease);
      --norm: .26s var(--ease);
      --slow: .48s var(--spring);
    }

    *,
    *::before,
    *::after {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      -webkit-tap-highlight-color: transparent;
      -webkit-user-select: none;
      -moz-user-select: none;
      user-select: none;
      -webkit-touch-callout: none;
    }

    html {
      scroll-behavior: smooth;
    }

    html.ov-open {
      overflow: visible;
    }

    body {
      font-family: 'Baloo Bhaijaan 2', sans-serif;
      background: var(--bg);
      color: var(--t1);
      min-height: 100vh;
      overflow-x: hidden;
      -webkit-font-smoothing: antialiased;
    }

    body::before {
      content: '';
      position: fixed;
      inset: 0;
      pointer-events: none;
      z-index: 0;
      background:
        radial-gradient(ellipse 70% 45% at 10% 5%, rgba(79, 70, 229, .08) 0%, transparent 65%),
        radial-gradient(ellipse 55% 40% at 90% 90%, rgba(124, 58, 237, .06) 0%, transparent 60%);
    }

    /* ══ HERO ════════════════════════════════════════════════════ */
    .hero {
      position: relative;
      overflow: hidden;
      background: linear-gradient(145deg, #312e81 0%, #4f46e5 35%, #7c3aed 70%, #5b21b6 100%);
      padding: 0 0 0;
      display: flex;
      flex-direction: column;
      min-height: 340px;
    }

    /* animated mesh */
    .hero::before {
      content: '';
      position: absolute;
      inset: 0;
      background:
        radial-gradient(circle at 20% 30%, rgba(255, 255, 255, .07) 0%, transparent 40%),
        radial-gradient(circle at 80% 70%, rgba(255, 255, 255, .05) 0%, transparent 35%);
      animation: hero-pulse 6s ease-in-out infinite;
    }

    @keyframes hero-pulse {

      0%,
      100% {
        opacity: 1;
      }

      50% {
        opacity: .6;
      }
    }

    /* star/dot texture */
    .hero::after {
      content: '';
      position: absolute;
      inset: 0;
      pointer-events: none;
      background-image: radial-gradient(circle, rgba(255, 255, 255, .08) 1px, transparent 1px);
      background-size: 28px 28px;
    }

    .hero-top {
      position: relative;
      z-index: 2;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 14px 18px 0;
    }

    .hero-church-chip {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(255, 255, 255, .14);
      backdrop-filter: blur(10px);
      color: rgba(255, 255, 255, .92);
      font-size: .78rem;
      font-weight: 600;
      padding: 5px 13px;
      border-radius: var(--r-full);
      border: 1px solid rgba(255, 255, 255, .22);
      max-width: 180px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .hero-actions-top {
      display: flex;
      gap: 8px;
    }

    .hero-ico-btn {
      width: 34px;
      height: 34px;
      border-radius: 50%;
      background: rgba(255, 255, 255, .15);
      backdrop-filter: blur(8px);
      border: 1px solid rgba(255, 255, 255, .25);
      color: #fff;
      font-size: .9rem;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: var(--fast);
    }

    .hero-ico-btn:hover {
      background: rgba(255, 255, 255, .26);
    }

    /* center content */
    .hero-body {
      position: relative;
      z-index: 2;
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 22px 20px 0;
      flex: 1;
    }

    .avatar-ring {
      position: relative;
      width: 116px;
      height: 116px;
      /* glowing ring */
      background: conic-gradient(from 0deg, #818cf8, #c4b5fd, #e0e7ff, #818cf8);
      border-radius: 50%;
      padding: 3px;
      box-shadow: 0 0 0 4px rgba(255, 255, 255, .15), 0 8px 32px rgba(0, 0, 0, .25);
      cursor: pointer;
      transition: var(--norm);
      animation: ring-spin 8s linear infinite;
    }

    @keyframes ring-spin {
      from {
        background: conic-gradient(from 0deg, #818cf8, #c4b5fd, #e0e7ff, #818cf8);
      }

      to {
        background: conic-gradient(from 360deg, #818cf8, #c4b5fd, #e0e7ff, #818cf8);
      }
    }

    .avatar-ring:hover {
      transform: scale(1.04);
    }

    .avatar-inner {
      width: 100%;
      height: 100%;
      border-radius: 50%;
      background: linear-gradient(135deg, #e0e7ff, #c7d2fe);
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 2.6rem;
      color: #818cf8;
      border: 3px solid rgba(255, 255, 255, .9);
    }

    .avatar-inner img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .avatar-edit-fab {
      position: absolute;
      bottom: 2px;
      left: 2px;
      width: 26px;
      height: 26px;
      border-radius: 50%;
      background: var(--brand);
      border: 2px solid #fff;
      display: none;
      align-items: center;
      justify-content: center;
      font-size: .58rem;
      color: #fff;
      cursor: pointer;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .2);
      transition: var(--fast);
    }

    .avatar-edit-fab.show {
      display: flex;
    }

    .avatar-edit-fab:hover {
      background: var(--brand-d);
      transform: scale(1.12);
    }

    .hero-name {
      margin-top: 14px;
      font-size: 1.85rem;
      font-weight: 800;
      color: #fff;
      text-align: center;
      text-shadow: 0 2px 12px rgba(0, 0, 0, .2);
      line-height: 1.15;
    }

    @media(max-width:400px) {
      .hero-name {
        font-size: 1.5rem;
      }
    }

    .hero-tags {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 7px;
      flex-wrap: wrap;
      margin-top: 10px;
    }

    .htag {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 4px 13px;
      border-radius: var(--r-full);
      font-size: .78rem;
      font-weight: 700;
      background: rgba(255, 255, 255, .16);
      backdrop-filter: blur(8px);
      border: 1px solid rgba(255, 255, 255, .28);
      color: #fff;
      transition: var(--fast);
    }

    .htag.class-tag {
      background: rgba(255, 255, 255, .95);
      color: var(--brand-d);
      border-color: transparent;
    }

    .htag.switch-tag {
      background: transparent;
      border-color: rgba(255, 255, 255, .4);
      cursor: pointer;
    }

    .htag.switch-tag:hover {
      background: rgba(255, 255, 255, .18);
    }

    /* ── COUPON HERO CARD ──────────────────────────────── */
    .coupon-hero {
      position: relative;
      z-index: 2;
      margin: 20px 16px 0;
      background: rgba(255, 255, 255, .1);
      backdrop-filter: blur(14px);
      border: 1px solid rgba(255, 255, 255, .22);
      border-radius: var(--r-xl);
      padding: 18px 20px 16px;
      display: grid;
      grid-template-columns: 1fr auto;
      align-items: center;
      gap: 16px;
      overflow: hidden;
    }

    .coupon-hero::before {
      content: '';
      position: absolute;
      top: -30px;
      left: -30px;
      width: 140px;
      height: 140px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(196, 181, 253, .25), transparent 70%);
      pointer-events: none;
    }

    .ch-total-label {
      font-size: .74rem;
      font-weight: 600;
      color: rgba(255, 255, 255, .75);
      margin-bottom: 3px;
    }

    .ch-total-val {
      font-size: 2.9rem;
      font-weight: 800;
      color: #fff;
      line-height: 1;
      text-shadow: 0 2px 16px rgba(0, 0, 0, .2);
    }

    .ch-total-unit {
      font-size: .88rem;
      color: rgba(255, 255, 255, .8);
      margin-top: 1px;
      font-weight: 600;
    }

    .ch-breakdown {
      display: flex;
      flex-direction: column;
      gap: 5px;
    }

    .ch-row {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: .74rem;
      color: rgba(255, 255, 255, .85);
      font-weight: 600;
      background: rgba(255, 255, 255, .1);
      padding: 4px 10px;
      border-radius: var(--r-full);
    }

    .ch-row i {
      font-size: .72rem;
    }

    /* ── HERO WAVE BOTTOM ──────────────────────────────── */
    .hero-wave {
      position: relative;
      z-index: 2;
      margin-top: 20px;
      height: 38px;
      background: var(--bg);
      clip-path: ellipse(56% 100% at 50% 100%);
      flex-shrink: 0;
    }

    /* ══ STATS BAR ═══════════════════════════════════════ */
    .stats-bar {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      margin: 0 14px 18px;
      background: var(--surf);
      border-radius: var(--r-lg);
      border: 1px solid var(--bdr);
      box-shadow: var(--sh-md);
      overflow: hidden;
    }

    .sb-cell {
      padding: 12px 8px;
      text-align: center;
      border-left: 1px solid var(--bdr);
      transition: var(--fast);
      position: relative;
    }

    .sb-cell:last-child {
      border-left: none;
    }

    .sb-cell:hover {
      background: var(--s2);
    }

    .sb-val {
      font-size: 1.3rem;
      font-weight: 800;
      line-height: 1;
      color: var(--t1);
    }

    .sb-lbl {
      font-size: .63rem;
      color: var(--t4);
      margin-top: 2px;
      font-weight: 600;
    }

    .sb-cell.ok .sb-val {
      color: var(--ok-l);
    }

    .sb-cell.err .sb-val {
      color: var(--err-l);
    }

    .sb-cell.cou .sb-val {
      color: var(--cou-l);
    }

    .sb-cell.neu .sb-val {
      color: var(--brand-l);
    }

    /* ══ PAGE ════════════════════════════════════════════ */
    .page {
      max-width: 860px;
      margin: 0 auto;
      padding: 0 14px 90px;
      position: relative;
      z-index: 1;
    }

    /* ══ SECTION CARD ════════════════════════════════════ */
    .sc {
      position: relative;
      background:
        linear-gradient(180deg, rgba(255, 255, 255, .98), rgba(248, 250, 252, .98));
      border-radius: 26px;
      border: 1px solid rgba(226, 232, 240, .9);
      box-shadow:
        0 1px 0 rgba(255, 255, 255, .85) inset,
        0 14px 34px rgba(15, 23, 42, .06);
      overflow: hidden;
      margin-bottom: 16px;
      transition: box-shadow var(--norm), transform var(--norm), border-color var(--norm);
    }

    .sc::before {
      content: '';
      position: absolute;
      inset: 0 0 auto 0;
      height: 1px;
      background: linear-gradient(90deg, rgba(255, 255, 255, 0), rgba(255, 255, 255, .9), rgba(255, 255, 255, 0));
      pointer-events: none;
    }

    .sc:hover {
      transform: translateY(-3px);
      border-color: rgba(129, 140, 248, .45);
      box-shadow:
        0 1px 0 rgba(255, 255, 255, .92) inset,
        0 20px 46px rgba(15, 23, 42, .09);
    }

    .sc-head {
      position: relative;
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 18px 20px 14px;
      border-bottom: 1px solid rgba(241, 245, 249, .95);
      background:
        radial-gradient(circle at top right, rgba(99, 102, 241, .07), transparent 42%),
        linear-gradient(180deg, rgba(255, 255, 255, .96), rgba(248, 250, 252, .86));
    }

    .sc-ico {
      width: 42px;
      height: 42px;
      border-radius: 14px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
      flex-shrink: 0;
      box-shadow:
        0 1px 0 rgba(255, 255, 255, .8) inset,
        0 10px 22px rgba(15, 23, 42, .08);
    }

    .sc-label {
      flex: 1;
    }

    .sc-title {
      font-size: 1rem;
      font-weight: 800;
      color: var(--t1);
    }

    .sc-sub {
      font-size: .74rem;
      color: var(--t4);
      margin-top: 2px;
      font-weight: 600;
    }

    .sc-badge {
      font-size: .72rem;
      font-weight: 800;
      padding: 5px 11px;
      border-radius: var(--r-full);
      background: linear-gradient(135deg, rgba(238, 242, 255, .96), rgba(224, 231, 255, .96));
      color: var(--brand);
      border: 1px solid rgba(129, 140, 248, .2);
    }

    .sc-body {
      padding: 18px;
    }

    /* ══ INFO PILLS ══════════════════════════════════════ */
    .info-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(168px, 1fr));
      gap: 10px;
    }

    .ip {
      display: flex;
      align-items: flex-start;
      gap: 9px;
      padding: 11px 13px;
      border-radius: var(--r-md);
      background: var(--s2);
      border: 1px solid var(--bdr);
      transition: var(--fast);
    }

    .ip:hover {
      border-color: var(--brand-l);
      background: var(--brand-bg);
      transform: translateY(-1px);
    }

    .ip-ico {
      width: 30px;
      height: 30px;
      border-radius: var(--r-xs);
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .78rem;
      margin-top: 1px;
    }

    .ip-lbl {
      font-size: .66rem;
      color: var(--t4);
      font-weight: 600;
      margin-bottom: 2px;
    }

    .ip-val {
      font-size: .84rem;
      font-weight: 700;
      color: var(--t1);
      word-break: break-word;
      line-height: 1.3;
    }

    /* ══ ATTENDANCE ══════════════════════════════════════ */
    .att-stats {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 10px;
      margin-bottom: 14px;
    }

    .as {
      text-align: center;
      padding: 11px 6px;
      border-radius: var(--r-md);
      background: var(--s2);
      border: 1px solid var(--bdr);
    }

    .as-val {
      font-size: 1.35rem;
      font-weight: 800;
      line-height: 1;
    }

    .as-lbl {
      font-size: .65rem;
      color: var(--t4);
      margin-top: 2px;
      font-weight: 600;
    }

    .as.ok {
      background: var(--ok-bg);
      border-color: #6ee7b7;
    }

    .as.ok .as-val {
      color: var(--ok);
    }

    .as.err {
      background: var(--err-bg);
      border-color: #fca5a5;
    }

    .as.err .as-val {
      color: var(--err);
    }

    .as.neu .as-val {
      color: var(--brand);
    }

    .cal-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(82px, 1fr));
      gap: 8px;
    }

    .cal-day {
      padding: 10px 7px;
      border-radius: var(--r-md);
      text-align: center;
      border: 2px solid var(--bdr);
      background: var(--s2);
      transition: var(--fast);
    }

    .cal-day:hover {
      transform: translateY(-2px);
      box-shadow: var(--sh-sm);
    }

    .cal-day.present {
      background: var(--ok-bg);
      border-color: #6ee7b7;
    }

    .cal-day.absent {
      background: var(--err-bg);
      border-color: #fca5a5;
    }

    .cd-num {
      font-size: 1.1rem;
      font-weight: 800;
      color: var(--t1);
    }

    .cal-day.present .cd-num {
      color: var(--ok);
    }

    .cal-day.absent .cd-num {
      color: var(--err);
    }

    .cd-mo {
      font-size: .6rem;
      color: var(--t4);
      font-weight: 600;
    }

    .cd-st {
      font-size: .62rem;
      font-weight: 700;
      margin-top: 3px;
      color: var(--t5);
    }

    .cal-day.present .cd-st {
      color: var(--ok);
    }

    .cal-day.absent .cd-st {
      color: var(--err);
    }

    .cd-days {
      font-size: .58rem;
      color: var(--t4);
      margin-top: 1px;
      font-weight: 500;
    }

    /* ══ ATTENDANCE HISTORY MODAL ════════════════════════ */
    .att-view-all {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      font-size: .72rem;
      font-weight: 700;
      color: var(--brand);
      cursor: pointer;
      padding: 4px 10px;
      border-radius: var(--r-full);
      background: var(--brand-bg);
      border: 1px solid rgba(79, 70, 229, .15);
      transition: var(--fast);
      margin-top: 10px;
      -webkit-user-select: none;
      user-select: none;
    }

    .att-view-all:hover {
      background: var(--brand);
      color: #fff;
      transform: translateY(-1px);
    }

    #attHistOv .modal {
      max-width: 700px;
    }

    .att-hist-filters {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
      padding: 12px 18px;
      border-bottom: 1px solid var(--bdr);
      background: var(--s2);
    }

    .att-hist-search {
      flex: 1;
      min-width: 140px;
      padding: 9px 14px;
      border: 1.5px solid var(--bdr);
      border-radius: var(--r-sm);
      font-family: 'Baloo Bhaijaan 2', sans-serif;
      font-size: .88rem;
      background: var(--surf);
      color: var(--t1);
      outline: none;
      transition: var(--fast);
    }

    .att-hist-search:focus {
      border-color: var(--brand);
      box-shadow: 0 0 0 3px var(--brand-bg);
    }

    .att-filter-chips {
      display: flex;
      gap: 6px;
      flex-wrap: wrap;
    }

    .fchip {
      padding: 5px 12px;
      border-radius: var(--r-full);
      font-size: .72rem;
      font-weight: 700;
      border: 1.5px solid var(--bdr);
      background: var(--surf);
      color: var(--t3);
      cursor: pointer;
      transition: var(--fast);
      white-space: nowrap;
    }

    .fchip:hover {
      border-color: var(--brand);
      color: var(--brand);
    }

    .fchip.active {
      background: var(--brand);
      border-color: var(--brand);
      color: #fff;
    }

    .fchip.ok.active {
      background: var(--ok);
      border-color: var(--ok);
      color: #fff;
    }

    .fchip.err.active {
      background: var(--err);
      border-color: var(--err);
      color: #fff;
    }

    .att-hist-sort {
      padding: 7px 12px;
      border: 1.5px solid var(--bdr);
      border-radius: var(--r-sm);
      font-family: 'Baloo Bhaijaan 2', sans-serif;
      font-size: .8rem;
      background: var(--surf);
      color: var(--t2);
      outline: none;
      cursor: pointer;
      transition: var(--fast);
    }

    .att-hist-sort:focus {
      border-color: var(--brand);
    }

    .att-hist-summary {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 8px;
      padding: 12px 18px;
      border-bottom: 1px solid var(--bdr);
    }

    .ahs-card {
      text-align: center;
      padding: 10px 6px;
      border-radius: var(--r-md);
      background: var(--s2);
      border: 1px solid var(--bdr);
    }

    .ahs-val {
      font-size: 1.2rem;
      font-weight: 800;
      line-height: 1;
    }

    .ahs-lbl {
      font-size: .6rem;
      color: var(--t4);
      margin-top: 2px;
      font-weight: 600;
    }

    .ahs-card.ok {
      background: var(--ok-bg);
      border-color: #6ee7b7;
    }

    .ahs-card.ok .ahs-val {
      color: var(--ok);
    }

    .ahs-card.err {
      background: var(--err-bg);
      border-color: #fca5a5;
    }

    .ahs-card.err .ahs-val {
      color: var(--err);
    }

    .ahs-card.neu .ahs-val {
      color: var(--brand);
    }

    .att-hist-list {
      padding: 10px 18px 18px;
      max-height: calc(100vh - 340px);
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .att-hist-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 14px;
      border-radius: var(--r-md);
      border: 1.5px solid var(--bdr);
      background: var(--surf);
      transition: var(--fast);
    }

    .att-hist-item:hover {
      transform: translateX(-2px);
      box-shadow: var(--sh-sm);
    }

    .att-hist-item.present {
      border-color: #6ee7b7;
      background: var(--ok-bg);
    }

    .att-hist-item.absent {
      border-color: #fca5a5;
      background: var(--err-bg);
    }

    .att-hist-item.unrecorded {
      border-color: var(--bdr2);
      background: var(--s2);
      opacity: .75;
    }

    .ahi-dot {
      width: 10px;
      height: 10px;
      border-radius: 50%;
      flex-shrink: 0;
    }

    .present .ahi-dot {
      background: var(--ok);
    }

    .absent .ahi-dot {
      background: var(--err);
    }

    .unrecorded .ahi-dot {
      background: var(--t5);
    }

    .ahi-info {
      flex: 1;
      min-width: 0;
    }

    .ahi-date {
      font-size: .9rem;
      font-weight: 800;
      color: var(--t1);
    }

    .ahi-meta {
      font-size: .67rem;
      color: var(--t4);
      font-weight: 500;
      margin-top: 2px;
    }

    .ahi-status {
      padding: 3px 10px;
      border-radius: var(--r-full);
      font-size: .7rem;
      font-weight: 700;
      flex-shrink: 0;
    }

    .present .ahi-status {
      background: rgba(5, 150, 105, .12);
      color: var(--ok);
    }

    .absent .ahi-status {
      background: rgba(220, 38, 38, .12);
      color: var(--err);
    }

    .unrecorded .ahi-status {
      background: var(--bdr2);
      color: var(--t4);
    }

    .att-hist-empty {
      text-align: center;
      padding: 32px 16px;
      color: var(--t4);
      font-size: .88rem;
      font-weight: 600;
    }

    .att-hist-empty i {
      display: block;
      font-size: 1.8rem;
      margin-bottom: 10px;
      opacity: .4;
    }

    .att-hist-count {
      font-size: .72rem;
      color: var(--t4);
      font-weight: 600;
      padding: 6px 18px 2px;
    }

    /* ══ CLEAN SCROLLBAR ════════════════════════════════ */
    .att-hist-scroll {
      scrollbar-width: thin;
      scrollbar-color: var(--bdr) transparent;
    }

    .att-hist-scroll::-webkit-scrollbar {
      width: 4px;
    }

    .att-hist-scroll::-webkit-scrollbar-track {
      background: transparent;
    }

    .att-hist-scroll::-webkit-scrollbar-thumb {
      background: var(--bdr);
      border-radius: var(--r-full);
    }

    .att-hist-scroll::-webkit-scrollbar-thumb:hover {
      background: var(--t5);
    }

    .settings-sheet {
      scrollbar-width: thin;
      scrollbar-color: var(--bdr) transparent;
    }

    .settings-sheet::-webkit-scrollbar {
      width: 4px;
    }

    .settings-sheet::-webkit-scrollbar-track {
      background: transparent;
    }

    .settings-sheet::-webkit-scrollbar-thumb {
      background: var(--bdr);
      border-radius: var(--r-full);
    }

    .settings-sheet::-webkit-scrollbar-thumb:hover {
      background: var(--t5);
    }

    /* ══ TRIPS ═══════════════════════════════════════════ */
    .trip-card {
      border-radius: var(--r-lg);
      overflow: hidden;
      border: 1px solid var(--bdr);
      margin-bottom: 12px;
      background: var(--surf);
      transition: var(--norm);
      cursor: pointer;
    }

    .trip-card:last-child {
      margin-bottom: 0;
    }

    .trip-card:hover {
      transform: translateY(-3px);
      box-shadow: var(--sh-md);
      border-color: var(--trip-l);
    }

    .trip-thumb {
      width: 100%;
      height: 160px;
      object-fit: cover;
      display: block;
      background: linear-gradient(135deg, #0c4a6e, #0369a1);
    }

    .trip-thumb-placeholder {
      width: 100%;
      height: 160px;
      background: linear-gradient(135deg, #1e3a5f 0%, #0369a1 50%, #0ea5e9 100%);
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: rgba(255, 255, 255, .5);
      font-size: 2.5rem;
      gap: 8px;
    }

    .trip-thumb-placeholder span {
      font-size: .8rem;
      font-weight: 600;
    }

    /* price overlay on thumb */
    .trip-thumb-wrap {
      position: relative;
    }

    .trip-price-overlay {
      position: absolute;
      bottom: 10px;
      left: 10px;
      display: flex;
      flex-direction: column;
      gap: 4px;
    }

    .trip-price-pill {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 4px 12px;
      border-radius: var(--r-full);
      font-size: .8rem;
      font-weight: 800;
      backdrop-filter: blur(12px);
    }

    .trip-price-pill.main {
      background: rgba(16, 185, 129, .9);
      color: #fff;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .2);
    }

    .trip-price-pill.remaining {
      background: rgba(220, 38, 38, .85);
      color: #fff;
      font-size: .72rem;
    }

    .trip-status-overlay {
      position: absolute;
      top: 10px;
      right: 10px;
      padding: 4px 10px;
      border-radius: var(--r-full);
      font-size: .7rem;
      font-weight: 700;
      backdrop-filter: blur(10px);
    }

    .ts-planned {
      background: rgba(217, 119, 6, .88);
      color: #fff;
    }

    .ts-active {
      background: rgba(5, 150, 105, .88);
      color: #fff;
    }

    .ts-completed {
      background: rgba(100, 116, 139, .8);
      color: #fff;
    }

    .ts-cancelled {
      background: rgba(220, 38, 38, .8);
      color: #fff;
    }

    .trip-body {
      padding: 13px 15px 12px;
    }

    .trip-title {
      font-size: .95rem;
      font-weight: 800;
      color: var(--t1);
      margin-bottom: 6px;
    }

    .trip-desc {
      font-size: .78rem;
      color: var(--t3);
      line-height: 1.55;
      margin-bottom: 9px;
    }

    .trip-meta-row {
      display: flex;
      flex-wrap: wrap;
      gap: 7px;
      align-items: center;
    }

    .trip-meta-chip {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: .71rem;
      color: var(--t3);
      padding: 3px 9px;
      border-radius: var(--r-full);
      background: var(--s2);
      border: 1px solid var(--bdr);
    }

    /* not-registered contact bar on trip card */
    .trip-contact-bar {
      display: flex;
      align-items: center;
      gap: 8px;
      flex-wrap: wrap;
      margin-top: 10px;
      padding: 9px 12px;
      background: #fef3c7;
      border: 1.5px solid #fde68a;
      border-radius: var(--r-md);
      font-size: .76rem;
      font-weight: 600;
      color: #92400e;
      cursor: pointer;
      transition: var(--fast);
    }

    .trip-contact-bar:hover {
      background: #fde68a;
    }

    .trip-contact-bar.unreach {
      cursor: default;
    }

    .trip-contact-bar.unreach:hover {
      background: #fef3c7;
    }

    .trip-contact-action {
      margin-right: auto;
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 4px 11px;
      border-radius: var(--r-full);
      background: var(--brand);
      color: #fff;
      font-size: .72rem;
      font-weight: 700;
      transition: var(--fast);
    }

    .trip-contact-bar:hover .trip-contact-action {
      background: var(--brand-d);
    }

    /* avatars strip */
    .kids-strip {
      display: flex;
      align-items: center;
      margin-top: 10px;
    }

    .kids-strip .ka {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      border: 2px solid var(--surf);
      background: linear-gradient(135deg, var(--brand-bg), #c7d2fe);
      color: var(--brand);
      font-size: .65rem;
      font-weight: 700;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-left: -8px;
      overflow: hidden;
      flex-shrink: 0;
    }

    .kids-strip .ka:first-child {
      margin-left: 0;
    }

    .kids-strip .ka img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .kids-strip .ka-more {
      background: var(--brand);
      color: #fff;
      font-size: .6rem;
    }

    /* ══ TASKS ════════════════════════════════════════════ */
    .task-card {
      border-radius: var(--r-md);
      overflow: hidden;
      border: 1px solid var(--bdr);
      margin-bottom: 10px;
      background: var(--surf);
      transition: var(--fast);
      cursor: pointer;
      display: flex;
    }

    .task-card:last-child {
      margin-bottom: 0;
    }

    .task-card:hover {
      transform: translateX(-2px);
      box-shadow: var(--sh-sm);
      border-color: var(--brand-l);
    }

    .task-bar {
      width: 5px;
      flex-shrink: 0;
      background: linear-gradient(180deg, var(--brand), var(--cou-l));
    }

    .task-bar.done-bar {
      background: linear-gradient(180deg, var(--ok), #6ee7b7);
    }

    .task-bar.exp-bar {
      background: var(--t5);
    }

    .task-bar.up-bar {
      background: linear-gradient(180deg, var(--warn-l), var(--gold-l));
    }

    .task-body {
      padding: 11px 13px;
      flex: 1;
    }

    .task-top {
      display: flex;
      align-items: flex-start;
      gap: 8px;
      margin-bottom: 6px;
    }

    .task-title {
      font-size: .88rem;
      font-weight: 700;
      color: var(--t1);
      flex: 1;
      line-height: 1.35;
    }

    .task-badge {
      font-size: .66rem;
      font-weight: 700;
      padding: 2px 8px;
      border-radius: var(--r-full);
      flex-shrink: 0;
      white-space: nowrap;
    }

    .tb-open {
      background: var(--ok-bg);
      color: var(--ok);
    }

    .tb-done {
      background: var(--brand-bg);
      color: var(--brand);
    }

    .tb-up {
      background: var(--warn-bg);
      color: var(--warn);
    }

    .tb-exp {
      background: var(--s2);
      color: var(--t4);
      border: 1px solid var(--bdr);
    }

    .task-metas {
      display: flex;
      flex-wrap: wrap;
      gap: 6px;
    }

    .task-meta-chip {
      display: inline-flex;
      align-items: center;
      gap: 4px;
      font-size: .69rem;
      color: var(--t4);
    }

    .task-result {
      display: flex;
      align-items: center;
      gap: 7px;
      margin-top: 7px;
      padding: 7px 10px;
      border-radius: var(--r-sm);
      background: var(--brand-bg);
      border: 1px solid var(--brand-bg);
      font-size: .77rem;
      color: var(--brand);
      font-weight: 700;
    }

    /* ══ ANNOUNCEMENTS ═══════════════════════════════════ */
    .ann-shell {
      display: grid;
      gap: 14px;
    }

    .ann-summary {
      display: grid;
      grid-template-columns: minmax(0, 1.7fr) minmax(140px, .9fr);
      gap: 12px;
    }

    .ann-summary-card,
    .ann-highlight {
      position: relative;
      overflow: hidden;
      border-radius: 22px;
      border: 1px solid rgba(226, 232, 240, .95);
      background: linear-gradient(180deg, rgba(255, 255, 255, .98), rgba(248, 250, 252, .96));
      box-shadow: 0 10px 26px rgba(15, 23, 42, .05);
    }

    .ann-highlight {
      padding: 18px;
      background:
        radial-gradient(circle at top right, rgba(245, 158, 11, .18), transparent 42%),
        linear-gradient(135deg, rgba(255, 251, 235, .98), rgba(255, 255, 255, .98));
    }

    .ann-highlight-badge {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 5px 11px;
      border-radius: var(--r-full);
      background: rgba(255, 255, 255, .72);
      color: var(--warn);
      font-size: .72rem;
      font-weight: 800;
      margin-bottom: 12px;
      border: 1px solid rgba(245, 158, 11, .16);
    }

    .ann-highlight-title {
      font-size: 1rem;
      font-weight: 800;
      color: var(--t1);
      margin-bottom: 6px;
    }

    .ann-highlight-text {
      font-size: .83rem;
      line-height: 1.75;
      color: var(--t2);
      margin-bottom: 12px;
    }

    .ann-highlight-meta {
      display: flex;
      align-items: center;
      flex-wrap: wrap;
      gap: 7px;
    }

    .ann-stat-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 10px;
      padding: 14px;
    }

    .ann-stat {
      padding: 13px 12px;
      border-radius: 16px;
      background: var(--s2);
      border: 1px solid var(--bdr);
      text-align: center;
    }

    .ann-stat-val {
      display: block;
      font-size: 1.18rem;
      font-weight: 800;
      color: var(--t1);
      line-height: 1;
    }

    .ann-stat-lbl {
      display: block;
      margin-top: 5px;
      font-size: .68rem;
      color: var(--t4);
      font-weight: 700;
    }

    .ann-list {
      display: grid;
      gap: 11px;
    }

    .ann-item {
      position: relative;
      display: grid;
      gap: 10px;
      padding: 15px 16px;
      border-radius: 20px;
      background: linear-gradient(180deg, rgba(248, 250, 252, .95), rgba(255, 255, 255, .98));
      border: 1px solid rgba(226, 232, 240, .95);
      box-shadow: 0 8px 22px rgba(15, 23, 42, .04);
      transition: transform var(--fast), box-shadow var(--fast), border-color var(--fast);
    }

    .ann-item::before {
      content: '';
      position: absolute;
      inset: 12px auto 12px 0;
      width: 4px;
      border-radius: 99px;
      background: linear-gradient(180deg, var(--warn-l), var(--brand));
      opacity: .9;
    }

    .ann-item:hover {
      transform: translateY(-2px);
      border-color: rgba(129, 140, 248, .4);
      box-shadow: 0 14px 28px rgba(15, 23, 42, .07);
    }

    .ann-top {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 10px;
    }

    .ann-type {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      font-size: .7rem;
      font-weight: 800;
      padding: 5px 10px;
      border-radius: var(--r-full);
      background: linear-gradient(135deg, rgba(238, 242, 255, .96), rgba(224, 231, 255, .96));
      color: var(--brand);
      border: 1px solid rgba(129, 140, 248, .16);
    }

    .ann-type.link {
      background: linear-gradient(135deg, rgba(237, 233, 254, .96), rgba(243, 232, 255, .96));
      color: var(--cou);
      border-color: rgba(139, 92, 246, .18);
    }

    .ann-date {
      font-size: .68rem;
      color: var(--t4);
      font-weight: 700;
      white-space: nowrap;
      padding-top: 2px;
    }

    .ann-text {
      font-size: .84rem;
      color: var(--t1);
      line-height: 1.75;
      padding-right: 4px;
    }

    .ann-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: wrap;
    }

    .ann-meta-pill {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 4px 10px;
      border-radius: var(--r-full);
      background: var(--s2);
      border: 1px solid var(--bdr);
      color: var(--t3);
      font-size: .68rem;
      font-weight: 700;
    }

    .ann-link-btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 7px 14px;
      border-radius: var(--r-full);
      background: linear-gradient(135deg, var(--cou-l), var(--cou));
      color: #fff;
      font-size: .76rem;
      font-weight: 800;
      text-decoration: none;
      border: none;
      cursor: pointer;
      font-family: 'Baloo Bhaijaan 2', sans-serif;
      transition: var(--fast);
      box-shadow:
        0 1px 0 rgba(255, 255, 255, .22) inset,
        0 10px 20px rgba(124, 58, 237, .18);
    }

    .ann-link-btn:hover {
      transform: translateY(-2px);
      box-shadow:
        0 1px 0 rgba(255, 255, 255, .24) inset,
        0 14px 24px rgba(124, 58, 237, .22);
    }

    .ann-empty {
      text-align: center;
      padding: 28px 18px;
      border-radius: 20px;
      background: linear-gradient(180deg, rgba(248, 250, 252, .96), rgba(255, 255, 255, .98));
      border: 1px dashed rgba(203, 213, 225, .9);
      color: var(--t3);
    }

    .ann-empty i {
      display: block;
      font-size: 1.8rem;
      margin-bottom: 10px;
      color: var(--warn);
      opacity: .75;
    }

    .ann-empty strong {
      display: block;
      font-size: .9rem;
      color: var(--t1);
      margin-bottom: 4px;
    }

    /* ══ OVERLAY / MODAL ═════════════════════════════════ */
    .overlay {
      position: fixed;
      inset: 0;
      background: rgba(10, 16, 40, .65);
      z-index: 500;
      display: flex;
      align-items: flex-start;
      justify-content: center;
      padding: 12px;
      overflow-y: auto;
      opacity: 0;
      visibility: hidden;
      transition: var(--norm);
      overflow: hidden;
    }

    .overlay.open {
      opacity: 1;
      visibility: visible;
    }

    .modal {
      background: var(--surf);
      border-radius: var(--r-xl);
      width: 100%;
      max-width: 660px;
      margin: auto;
      box-shadow: var(--sh-xl);
      border: 1px solid var(--bdr);
      transform: translateY(20px) scale(.97);
      transition: var(--slow);
    }

    .overlay.open .modal {
      transform: translateY(0) scale(1);
    }

    .modal.narrow {
      max-width: 400px;
    }

    .mhdr {
      display: flex;
      align-items: center;
      gap: 11px;
      padding: 16px 18px;
      border-bottom: 1px solid var(--bdr);
      background: linear-gradient(135deg, var(--brand), var(--cou));
      border-radius: var(--r-xl) var(--r-xl) 0 0;
    }

    .mhdr-title {
      font-size: .97rem;
      font-weight: 800;
      color: #fff;
      flex: 1;
    }

    .mhdr-sub {
      font-size: .7rem;
      color: rgba(255, 255, 255, .75);
      margin-top: 1px;
    }

    .mclose {
      width: 28px;
      height: 28px;
      border-radius: var(--r-sm);
      background: rgba(255, 255, 255, .15);
      border: none;
      color: #fff;
      font-size: .85rem;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: var(--fast);
    }

    .mclose:hover {
      background: rgba(255, 255, 255, .28);
    }

    .mbody {
      padding: 18px;
    }

    .mfooter {
      padding: 12px 18px;
      border-top: 1px solid var(--bdr);
      display: flex;
      align-items: center;
      justify-content: flex-end;
      gap: 8px;
      background: var(--s2);
      border-radius: 0 0 var(--r-xl) var(--r-xl);
    }

    /* ══ TRIP DETAIL MODAL ════════════════════════════════ */
    .trip-detail-thumb {
      width: 100%;
      max-height: 200px;
      object-fit: cover;
      border-radius: var(--r-md);
      margin-bottom: 14px;
      display: block;
    }

    .trip-detail-ph {
      width: 100%;
      height: 120px;
      border-radius: var(--r-md);
      margin-bottom: 14px;
      background: linear-gradient(135deg, #1e3a5f, #0ea5e9);
      display: flex;
      align-items: center;
      justify-content: center;
      color: rgba(255, 255, 255, .4);
      font-size: 2.5rem;
    }

    .my-trip-box {
      padding: 13px 15px;
      border-radius: var(--r-md);
      background: var(--ok-bg);
      border: 1px solid #6ee7b7;
      margin-bottom: 14px;
    }

    .my-trip-title {
      font-size: .8rem;
      font-weight: 700;
      color: var(--ok);
      margin-bottom: 8px;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .my-trip-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 8px;
    }

    .mtr-cell {
      text-align: center;
      padding: 8px;
      background: rgba(255, 255, 255, .6);
      border-radius: var(--r-sm);
    }

    .mtr-val {
      font-size: 1.1rem;
      font-weight: 800;
      color: var(--ok);
    }

    .mtr-lbl {
      font-size: .65rem;
      color: var(--t3);
      font-weight: 600;
    }

    .mtr-cell.warn {
      background: var(--warn-bg);
    }

    .mtr-cell.warn .mtr-val {
      color: var(--warn);
    }

    .mtr-cell.ok .mtr-val {
      color: var(--ok);
    }

    .kids-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(72px, 1fr));
      gap: 10px;
      margin-top: 4px;
    }

    .kid-tile {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 4px;
      text-align: center;
    }

    .kid-tile-av {
      width: 52px;
      height: 52px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--brand-bg), #c7d2fe);
      color: var(--brand);
      font-size: 1.3rem;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      border: 2px solid var(--bdr);
      transition: var(--fast);
    }

    .kid-tile-av:hover {
      border-color: var(--brand-l);
      transform: scale(1.06);
    }

    .kid-tile-av img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .kid-tile-name {
      font-size: .64rem;
      font-weight: 700;
      color: var(--t2);
      line-height: 1.2;
      max-width: 68px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .kid-tile-cls {
      font-size: .58rem;
      color: var(--t4);
    }

    .user-tile {
      /* alias for .kid-tile so both classes share styles */
    }

    .user-role {
      font-size: .58rem;
      color: var(--t4);
    }

    /* ══ EXAM / QUESTIONS ════════════════════════════════ */
    .qcard,
    .qcard-inner {
      background: var(--s2);
      border: 1.5px solid var(--bdr);
      border-radius: var(--r-md);
      margin-bottom: 12px;
      overflow: hidden;
    }

    .open-ans-textarea {
      width: 100%;
      min-height: 110px;
      padding: 11px 13px;
      border: 1.5px solid var(--bdr);
      border-radius: var(--r-md);
      font-family: 'Baloo Bhaijaan 2', sans-serif;
      font-size: .9rem;
      color: var(--t1);
      background: var(--surf);
      resize: vertical;
      outline: none;
      display: block;
      line-height: 1.6;
      transition: border-color var(--fast), box-shadow var(--fast);
    }

    .open-ans-textarea:focus {
      border-color: var(--brand);
      box-shadow: 0 0 0 3px var(--brand-glow);
    }

    .qhdr {
      display: flex;
      align-items: center;
      gap: 7px;
      padding: 10px 13px;
      background: var(--surf);
      border-bottom: 1px solid var(--bdr);
    }

    .qnum {
      width: 22px;
      height: 22px;
      border-radius: 5px;
      background: linear-gradient(135deg, var(--brand), var(--cou-l));
      color: #fff;
      font-size: .68rem;
      font-weight: 800;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .qtext {
      font-size: .85rem;
      font-weight: 700;
      color: var(--t1);
      flex: 1;
    }

    .qdeg {
      font-size: .68rem;
      font-weight: 700;
      color: var(--brand);
      background: var(--brand-bg);
      border: 1px solid var(--brand-l);
      padding: 2px 7px;
      border-radius: var(--r-full);
    }

    .qopts {
      padding: 9px 13px;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .qopt {
      display: flex;
      align-items: center;
      gap: 7px;
      padding: 8px 11px;
      border-radius: var(--r-sm);
      border: 1.5px solid var(--bdr);
      background: var(--surf);
      cursor: pointer;
      transition: var(--fast);
      font-size: .81rem;
      color: var(--t1);
    }

    .qopt:hover {
      border-color: var(--brand-l);
      background: var(--brand-bg);
    }

    .qopt.selected {
      border-color: var(--brand);
      background: var(--brand-bg);
      color: var(--brand);
      font-weight: 700;
    }

    .qopt.correct {
      border-color: var(--ok);
      background: var(--ok-bg);
      color: var(--ok);
      font-weight: 700;
    }

    .qopt.wrong {
      border-color: var(--err);
      background: var(--err-bg);
      color: var(--err);
    }

    .oradio {
      width: 15px;
      height: 15px;
      border-radius: 50%;
      border: 2px solid var(--bdr);
      flex-shrink: 0;
      transition: var(--fast);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .qopt.selected .oradio {
      border-color: var(--brand);
      background: var(--brand);
    }

    .qopt.selected .oradio::after {
      content: '';
      width: 5px;
      height: 5px;
      border-radius: 50%;
      background: #fff;
    }

    .qopt.correct .oradio {
      border-color: var(--ok);
      background: var(--ok);
    }

    .qopt.correct .oradio::after {
      content: '✓';
      font-size: .5rem;
      color: #fff;
      font-weight: 900;
    }

    .qopt.wrong .oradio {
      border-color: var(--err);
      background: var(--err);
    }

    .qopt.wrong .oradio::after {
      content: '✗';
      font-size: .5rem;
      color: #fff;
      font-weight: 900;
    }

    .olet {
      width: 18px;
      height: 18px;
      border-radius: 4px;
      background: var(--bdr);
      font-size: .64rem;
      font-weight: 700;
      color: var(--t3);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .qopt.selected .olet,
    .qopt.correct .olet,
    .qopt.wrong .olet {
      background: transparent;
    }

    .result-card {
      text-align: center;
      padding: 28px 20px;
      background: linear-gradient(135deg, var(--brand-bg), #e0e7ff);
      border-radius: var(--r-lg);
      border: 1px solid var(--brand-l);
    }

    .result-icon {
      font-size: 3rem;
      margin-bottom: 10px;
    }

    .result-score {
      font-size: 2.6rem;
      font-weight: 800;
      color: var(--brand);
    }

    .result-pct {
      font-size: .95rem;
      color: var(--t3);
      margin-top: 3px;
      font-weight: 600;
    }

    .result-coupons {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      margin-top: 14px;
      padding: 8px 20px;
      border-radius: var(--r-full);
      background: linear-gradient(135deg, var(--cou-l), var(--cou));
      color: #fff;
      font-weight: 800;
      font-size: .9rem;
    }

    /* ══ TIMER ════════════════════════════════════════════ */
    .timer-wrap {
      margin-bottom: 12px;
    }

    .timer-bar {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 9px 13px;
      background: var(--warn-bg);
      border: 1px solid #fde68a;
      border-radius: var(--r-md);
    }

    .timer-bar.urgent {
      background: var(--err-bg);
      border-color: #fca5a5;
    }

    .timer-val {
      font-size: 1.05rem;
      font-weight: 800;
      color: var(--warn);
    }

    .timer-bar.urgent .timer-val {
      color: var(--err);
    }

    .prog-wrap {
      margin-bottom: 12px;
    }

    .prog-bar {
      height: 5px;
      background: var(--bdr);
      border-radius: var(--r-full);
      overflow: hidden;
    }

    .prog-fill {
      height: 100%;
      background: linear-gradient(90deg, var(--brand), var(--cou-l));
      border-radius: var(--r-full);
      transition: width .4s var(--ease);
    }

    .prog-lbl {
      display: flex;
      justify-content: space-between;
      font-size: .66rem;
      color: var(--t4);
      margin-top: 3px;
    }

    /* ══ SETTINGS / FAB ══════════════════════════════════ */
    /* fab removed */
    .settings-list {
      display: flex;
      flex-direction: column;
      gap: 7px;
    }

    .s-btn {
      display: flex;
      align-items: center;
      gap: 11px;
      padding: 12px 15px;
      border-radius: var(--r-md);
      border: 1.5px solid var(--bdr);
      background: var(--surf);
      font-family: 'Baloo Bhaijaan 2', sans-serif;
      font-weight: 700;
      font-size: .88rem;
      color: var(--t1);
      cursor: pointer;
      transition: var(--fast);
      width: 100%;
      text-align: right;
    }

    .s-btn:hover {
      background: var(--brand-bg);
      border-color: var(--brand-l);
      transform: translateX(-2px);
    }

    .s-btn i {
      width: 20px;
      color: var(--brand);
      font-size: 1rem;
    }

    .s-btn.danger {
      color: var(--err);
      border-color: #fca5a5;
    }

    .s-btn.danger:hover {
      background: var(--err-bg);
    }

    .s-btn.danger i {
      color: var(--err);
    }

    /* ══ FORM ════════════════════════════════════════════ */
    .fg {
      display: flex;
      flex-direction: column;
      gap: 4px;
      margin-bottom: 12px;
    }

    .flbl {
      font-size: .74rem;
      font-weight: 700;
      color: var(--t2);
    }

    .fi {
      padding: 10px 12px;
      border: 1.5px solid var(--bdr);
      border-radius: var(--r-md);
      font-family: 'Baloo Bhaijaan 2', sans-serif;
      font-size: .87rem;
      color: var(--t1);
      background: var(--surf);
      outline: none;
      width: 100%;
      transition: var(--fast);
    }

    .fi:focus {
      border-color: var(--brand);
      box-shadow: 0 0 0 3px var(--brand-glow);
    }

    .pass-wrap {
      position: relative;
    }

    .pass-wrap .fi {
      padding-left: 42px;
    }

    .pass-eye {
      position: absolute;
      left: 11px;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: var(--t4);
      font-size: .88rem;
      cursor: pointer;
    }

    /* ══ BUTTONS ═════════════════════════════════════════ */
    .btn {
      display: inline-flex;
      align-items: center;
      gap: 5px;
      padding: 8px 17px;
      border-radius: var(--r-full);
      font-family: 'Baloo Bhaijaan 2', sans-serif;
      font-size: .84rem;
      font-weight: 700;
      border: 1.5px solid transparent;
      cursor: pointer;
      transition: var(--fast);
    }

    .btn-p {
      background: linear-gradient(135deg, var(--brand), var(--cou));
      color: #fff;
      box-shadow: var(--sh-brand);
    }

    .btn-p:hover {
      transform: translateY(-1px);
      box-shadow: 0 12px 26px -6px rgba(79, 70, 229, .42);
    }

    .btn-g {
      background: var(--s2);
      color: var(--t2);
      border-color: var(--bdr);
    }

    .btn-g:hover {
      background: var(--brand-bg);
      color: var(--brand);
      border-color: var(--brand-l);
    }

    .btn:disabled {
      opacity: .5;
      cursor: not-allowed;
      transform: none !important;
    }

    /* ══ PHOTO UPLOAD ════════════════════════════════════ */
    .upload-drop {
      border: 2.5px dashed var(--bdr);
      border-radius: var(--r-lg);
      padding: 28px 20px;
      text-align: center;
      cursor: pointer;
      transition: var(--fast);
      margin-bottom: 12px;
    }

    .upload-drop:hover,
    .upload-drop.over {
      border-color: var(--brand);
      background: var(--brand-bg);
    }

    .upload-drop i {
      font-size: 2.4rem;
      color: var(--brand);
      display: block;
      margin-bottom: 8px;
    }

    .upload-drop p {
      font-size: .8rem;
      color: var(--t3);
    }

    .crop-area {
      width: 100%;
      height: 280px;
      background: var(--s2);
      border-radius: var(--r-md);
      overflow: hidden;
      margin-bottom: 12px;
    }

    /* ══ ACCOUNT SWITCHER ════════════════════════════════ */
    .acc-item {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 13px;
      border-radius: var(--r-md);
      border: 1.5px solid var(--bdr);
      background: var(--surf);
      cursor: pointer;
      transition: var(--fast);
      margin-bottom: 7px;
    }

    .acc-item:last-child {
      margin-bottom: 0;
    }

    .acc-item:hover,
    .acc-item.active {
      background: var(--brand-bg);
      border-color: var(--brand-l);
    }

    .acc-av {
      width: 38px;
      height: 38px;
      border-radius: 50%;
      background: var(--brand-bg);
      color: var(--brand);
      font-size: 1rem;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      overflow: hidden;
      border: 2px solid var(--bdr);
    }

    .acc-av img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .acc-name {
      font-weight: 800;
      font-size: .84rem;
      color: var(--t1);
    }

    .acc-cls {
      font-size: .69rem;
      color: var(--t4);
    }

    /* ══ LOADING / TOAST / EMPTY ═════════════════════════ */
    .loading-screen {
      position: fixed;
      inset: 0;
      background: rgba(255, 255, 255, .96);
      backdrop-filter: blur(12px);
      z-index: 1000;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      gap: 14px;
      font-weight: 700;
      color: var(--brand);
      font-size: .9rem;
    }

    .loading-screen.hidden {
      display: none;
    }

    .spin {
      display: inline-block;
      width: 38px;
      height: 38px;
      border: 3px solid var(--brand-bg);
      border-top-color: var(--brand);
      border-radius: 50%;
      animation: _spin .7s linear infinite;
    }

    .spin-sm {
      width: 14px;
      height: 14px;
      border-width: 2px;
      border-top-color: #fff;
    }

    @keyframes _spin {
      to {
        transform: rotate(360deg)
      }
    }

    .tc {
      position: fixed;
      bottom: 18px;
      right: 14px;
      z-index: 9999;
      display: flex;
      flex-direction: column;
      gap: 5px;
      pointer-events: none;
    }

    .toast {
      display: flex;
      align-items: center;
      gap: 7px;
      padding: 9px 15px;
      border-radius: var(--r-full);
      background: var(--t1);
      color: #fff;
      font-size: .81rem;
      font-weight: 700;
      box-shadow: var(--sh-lg);
      opacity: 0;
      transform: translateX(16px);
      transition: var(--norm);
      pointer-events: auto;
      white-space: nowrap;
      max-width: 270px;
    }

    .toast.show {
      opacity: 1;
      transform: translateX(0);
    }

    .toast.ok {
      background: var(--ok);
    }

    .toast.err {
      background: var(--err);
    }

    .toast.info {
      background: var(--brand);
    }

    .empty-st {
      text-align: center;
      padding: 28px 20px;
      color: var(--t4);
    }

    .empty-st i {
      font-size: 2rem;
      display: block;
      margin-bottom: 8px;
      opacity: .5;
    }

    .empty-st p {
      font-size: .8rem;
      font-weight: 600;
    }

    .no-profile {
      text-align: center;
      padding: 60px 20px;
      background: var(--surf);
      border-radius: var(--r-xl);
      box-shadow: var(--sh-md);
      border: 1px solid var(--bdr);
      max-width: 430px;
      margin: 40px auto;
    }

    .no-profile i {
      font-size: 3.2rem;
      color: var(--t4);
      display: block;
      margin-bottom: 14px;
    }

    .no-profile h2 {
      font-size: 1.05rem;
      color: var(--t1);
      margin-bottom: 7px;
    }

    .no-profile p {
      font-size: .82rem;
      color: var(--t4);
      margin-bottom: 20px;
    }

    .public-banner {
      background: var(--brand-bg);
      border: 1px solid var(--brand-l);
      border-radius: var(--r-md);
      padding: 10px 14px;
      margin-bottom: 14px;
      display: flex;
      align-items: center;
      gap: 9px;
      font-size: .8rem;
      color: var(--brand);
      font-weight: 700;
    }

    .friend-banner {
      background: #fdf2f8;
      border: 1.5px solid #e879f9;
      border-radius: var(--r-md);
      padding: 10px 14px;
      margin-bottom: 14px;
      display: flex;
      align-items: center;
      gap: 9px;
      font-size: .8rem;
      color: #9333ea;
      font-weight: 700;
    }

    /* Search friends */
    .friend-search-bar {
      display: flex;
      align-items: center;
      gap: 8px;
      background: var(--surf);
      border: 1.5px solid var(--bdr);
      border-radius: var(--r-full);
      padding: 8px 14px;
      transition: var(--fast);
    }

    .friend-search-bar:focus-within {
      border-color: var(--cou-l);
      box-shadow: 0 0 0 3px rgba(167, 139, 250, .15);
    }

    .friend-search-bar input {
      flex: 1;
      border: none;
      outline: none;
      background: transparent;
      font-family: inherit;
      font-size: .88rem;
      color: var(--t1);
      -webkit-user-select: text;
      user-select: text;
    }

    .friend-search-bar input::placeholder {
      color: var(--t4);
    }

    .friend-result-card {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 12px 14px;
      border-radius: var(--r-md);
      border: 1.5px solid var(--bdr);
      background: var(--surf);
      cursor: pointer;
      transition: var(--fast);
      margin-bottom: 8px;
    }

    .friend-result-card:hover {
      border-color: var(--cou-l);
      background: var(--cou-bg);
    }

    .friend-result-av {
      width: 46px;
      height: 46px;
      border-radius: 50%;
      flex-shrink: 0;
      background: linear-gradient(135deg, var(--cou-bg), #ede9fe);
      color: var(--cou);
      font-size: 1.1rem;
      font-weight: 800;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      border: 2px solid var(--bdr);
    }

    .friend-result-av img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .friend-result-info {
      flex: 1;
      min-width: 0;
    }

    .friend-result-name {
      font-size: .88rem;
      font-weight: 700;
      color: var(--t1);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .friend-result-meta {
      font-size: .72rem;
      color: var(--t3);
      margin-top: 2px;
    }

    .friend-result-cou {
      font-size: .78rem;
      font-weight: 700;
      color: var(--cou);
      white-space: nowrap;
    }

    /* ══ SETTINGS SHEET ═════════════════════════════════ */
    .settings-overlay {
      align-items: flex-end;
      padding: 0;
    }

    .settings-sheet {
      background: var(--surf);
      border-radius: 28px 28px 0 0;
      width: 100%;
      max-width: 520px;
      padding: 0 0 max(24px, env(safe-area-inset-bottom));
      box-shadow: 0 -8px 40px rgba(0, 0, 0, .14);
      transform: translateY(100%);
      transition: transform var(--slow);
      max-height: 90vh;
      overflow: hidden;
      overflow-x: hidden;
      will-change: transform;
      overscroll-behavior: contain;
    }

    .settings-sheet.ss-scrollable {
      overflow-y: auto;
      overflow-x: hidden;
    }

    .overlay.open .settings-sheet {
      transform: translateY(0);
    }

    .ss-handle {
      width: 38px;
      height: 4px;
      background: var(--t5);
      border-radius: var(--r-full);
      margin: 12px auto 0;
    }

    .ss-profile {
      display: flex;
      align-items: center;
      gap: 14px;
      padding: 18px 22px 16px;
      border-bottom: 1px solid var(--bdr2);
    }

    .ss-avatar {
      width: 52px;
      height: 52px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--brand-bg), #c7d2fe);
      color: var(--brand);
      font-size: 1.3rem;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      border: 2px solid var(--bdr);
      flex-shrink: 0;
    }

    .ss-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .ss-name {
      font-size: 1rem;
      font-weight: 800;
      color: var(--t1);
    }

    .ss-class {
      font-size: .75rem;
      color: var(--t4);
      font-weight: 600;
      margin-top: 2px;
    }

    .ss-items {
      padding: 8px 14px;
    }

    .ss-item {
      display: flex;
      align-items: center;
      gap: 13px;
      padding: 13px 10px;
      border-radius: var(--r-md);
      cursor: pointer;
      transition: var(--fast);
    }

    .ss-item:hover {
      background: var(--s2);
    }

    .ss-item.danger .ss-item-label {
      color: var(--err);
    }

    .ss-item-ico {
      width: 38px;
      height: 38px;
      border-radius: var(--r-sm);
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: .92rem;
    }

    .ss-item-label {
      font-size: .92rem;
      font-weight: 700;
      color: var(--t1);
      flex: 1;
    }

    .ss-item-arr {
      font-size: .72rem;
      color: var(--t5);
    }

    .ss-divider {
      height: 8px;
      background: var(--s2);
      margin: 0;
    }

    .ss-close-btn {
      display: block;
      width: calc(100% - 32px);
      margin: 16px 16px 0;
      padding: 13px;
      border-radius: var(--r-md);
      background: var(--s2);
      border: 1.5px solid var(--bdr);
      font-family: 'Baloo Bhaijaan 2', sans-serif;
      font-size: .9rem;
      font-weight: 700;
      color: var(--t2);
      cursor: pointer;
      transition: var(--fast);
    }

    .ss-close-btn:hover {
      background: var(--bdr);
    }

    /* ══ UNCLE STRIP ══════════════════════════════════════ */
    .uncle-strip {
      display: inline-flex !important;
      align-items: center;
      gap: 7px;
      background: #fff;
      border: 1.5px solid rgba(99, 102, 241, .2);
      border-radius: var(--r-full);
      padding: 5px 12px 5px 7px;
      cursor: pointer;
      box-shadow: 0 2px 8px rgba(0, 0, 0, .10);
      transition: var(--fast);
    }

    .uncle-strip:hover {
      background: #f5f3ff;
      border-color: var(--cou-l);
    }

    .uncle-strip-label {
      font-size: .68rem;
      font-weight: 700;
      color: var(--cou);
      white-space: nowrap;
      letter-spacing: .01em;
    }

    .uncle-avatars {
      display: flex;
      align-items: center;
    }

    .ua {
      width: 24px;
      height: 24px;
      border-radius: 50%;
      border: 2px solid #fff;
      background: linear-gradient(135deg, #6366f1, #a78bfa);
      color: #fff;
      font-size: .58rem;
      font-weight: 800;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      margin-left: -6px;
      flex-shrink: 0;
      box-shadow: 0 1px 4px rgba(0, 0, 0, .18);
      transition: var(--fast);
    }

    .ua:first-child {
      margin-left: 0;
    }

    .ua img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .ua-more {
      font-size: .58rem;
      color: var(--cou);
      font-weight: 800;
      padding: 0 6px;
      border-radius: var(--r-full);
      height: 24px;
      display: flex;
      align-items: center;
      margin-left: 5px;
      border: 1.5px solid var(--cou-l);
      background: var(--cou-bg);
      white-space: nowrap;
      flex-shrink: 0;
    }

    /* ══ ATTENDANCE NARROWER ══════════════════════════════ */
    .cal-day {
      padding: 7px 5px !important;
    }

    .cd-num {
      font-size: 1rem !important;
    }

    /* ══ RESPONSIVE ══════════════════════════════════════ */
    @media(max-width:580px) {
      .coupon-hero {
        grid-template-columns: 1fr;
      }

      .sc {
        border-radius: 22px;
      }

      .sc-head {
        padding: 16px 16px 12px;
      }

      .sc-body {
        padding: 15px;
      }

      .ch-breakdown {
        flex-direction: row;
        flex-wrap: wrap;
      }

      .cal-grid {
        grid-template-columns: repeat(auto-fill, minmax(70px, 1fr));
      }

      .info-grid {
        grid-template-columns: 1fr 1fr;
      }

      .stats-bar {
        grid-template-columns: repeat(2, 1fr);
      }

      .ann-summary {
        grid-template-columns: 1fr;
      }

      .ann-stat-grid {
        grid-template-columns: repeat(2, 1fr);
      }

      .sb-cell:nth-child(3) {
        border-top: 1px solid var(--bdr);
      }

      .sb-cell:nth-child(4) {
        border-top: 1px solid var(--bdr);
      }
    }

    @media(max-width:360px) {
      .info-grid {
        grid-template-columns: 1fr;
      }

      .hero-name {
        font-size: 1.4rem;
      }

      .ann-stat-grid {
        grid-template-columns: 1fr;
      }

      .ann-top,
      .ann-footer {
        align-items: flex-start;
        flex-direction: column;
      }
    }

    /* ══ UNCLE CARDS ═════════════════════════════════════ */
    .uncle-card {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      cursor: pointer;
      padding: 12px 8px;
      border-radius: var(--r-lg);
      border: 1.5px solid var(--bdr);
      background: var(--surf);
      transition: var(--fast);
      text-align: center;
    }

    .uncle-card:hover {
      border-color: var(--cou-l);
      background: var(--cou-bg);
      transform: translateY(-2px);
      box-shadow: var(--sh-sm);
    }

    .uncle-card-av {
      width: 64px;
      height: 64px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--cou-bg), #ede9fe);
      color: var(--cou);
      font-size: 1.5rem;
      font-weight: 800;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      border: 2px solid var(--bdr);
      flex-shrink: 0;
      transition: var(--fast);
    }

    .uncle-card:hover .uncle-card-av {
      border-color: var(--cou-l);
    }

    .uncle-card-av img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .uncle-card-name {
      font-size: .75rem;
      font-weight: 700;
      color: var(--t1);
      line-height: 1.3;
      word-break: break-word;
    }

    .uncle-card-role {
      font-size: .65rem;
      color: var(--t4);
      font-weight: 600;
    }

    /* uncle drawer profile */
    .uncle-drawer-hero {
      background: linear-gradient(145deg, #4c1d95, #7c3aed);
      padding: 24px 22px 22px;
      display: flex;
      align-items: center;
      gap: 16px;
      border-radius: 28px 28px 0 0;
    }

    .uncle-drawer-av {
      width: 70px;
      height: 70px;
      border-radius: 50%;
      flex-shrink: 0;
      background: rgba(255, 255, 255, .18);
      border: 3px solid rgba(255, 255, 255, .4);
      overflow: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.8rem;
      color: #fff;
      font-weight: 800;
    }

    .uncle-drawer-av img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .uncle-drawer-name {
      font-size: 1.15rem;
      font-weight: 800;
      color: #fff;
      line-height: 1.25;
    }

    .uncle-drawer-role {
      font-size: .75rem;
      color: rgba(255, 255, 255, .75);
      margin-top: 3px;
      font-weight: 600;
    }

    .uncle-action-btn {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 14px 18px;
      border-radius: var(--r-md);
      border: 1.5px solid var(--bdr);
      background: var(--surf);
      cursor: pointer;
      transition: var(--fast);
      width: 100%;
      font-family: 'Baloo Bhaijaan 2', sans-serif;
      font-size: .93rem;
      font-weight: 700;
      color: var(--t1);
    }

    .uncle-action-btn:hover {
      background: var(--s2);
    }

    .uncle-action-btn.call {
      border-color: #6ee7b7;
    }

    .uncle-action-btn.call:hover {
      background: var(--ok-bg);
    }

    .uncle-action-btn.wa {
      border-color: #86efac;
    }

    .uncle-action-btn.wa:hover {
      background: #f0fdf4;
    }

    .uncle-action-ico {
      width: 40px;
      height: 40px;
      border-radius: var(--r-sm);
      flex-shrink: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.1rem;
    }

    /* ══ EXAM SCREEN ════════════════════════════════════════ */
    #examScreen {
      font-family: 'Baloo Bhaijaan 2', sans-serif;
    }

    /* ── Start card ── */
    .exam-start-card {
      background: var(--surf);
      border-radius: var(--r-xl);
      border: 1px solid var(--bdr);
      box-shadow: var(--sh-lg);
      overflow: hidden;
    }

    .exam-start-hero {
      background: linear-gradient(145deg, #312e81 0%, #4f46e5 45%, #7c3aed 100%);
      padding: 32px 24px 28px;
      text-align: center;
      position: relative;
      overflow: hidden;
    }

    .exam-start-hero::before {
      content: '';
      position: absolute;
      inset: 0;
      background: radial-gradient(circle at 20% 30%, rgba(255, 255, 255, .07) 0%, transparent 50%),
        radial-gradient(circle at 80% 70%, rgba(255, 255, 255, .05) 0%, transparent 40%);
    }

    .exam-start-hero::after {
      content: '';
      position: absolute;
      inset: 0;
      pointer-events: none;
      background-image: radial-gradient(circle, rgba(255, 255, 255, .06) 1px, transparent 1px);
      background-size: 24px 24px;
    }

    .exam-start-icon {
      position: relative;
      z-index: 1;
      width: 64px;
      height: 64px;
      border-radius: 50%;
      background: rgba(255, 255, 255, .15);
      backdrop-filter: blur(8px);
      border: 2px solid rgba(255, 255, 255, .3);
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 16px;
      font-size: 1.7rem;
      color: #fff;
    }

    .exam-start-title {
      position: relative;
      z-index: 1;
      font-size: 1.3rem;
      font-weight: 800;
      color: #fff;
      margin-bottom: 5px;
      line-height: 1.25;
    }

    .exam-start-sub {
      position: relative;
      z-index: 1;
      font-size: .82rem;
      color: rgba(255, 255, 255, .78);
      font-weight: 600;
    }

    .exam-meta-row {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 13px 16px;
      border-radius: var(--r-md);
      font-weight: 700;
      font-size: .88rem;
    }

    .exam-meta-row i {
      font-size: 1.05rem;
      flex-shrink: 0;
    }

    .exam-meta-row .em-text {}

    .exam-meta-row .em-sub {
      font-size: .7rem;
      font-weight: 500;
      color: var(--t4);
      margin-top: 2px;
    }

    /* ── Active exam header ── */
    .exam-hdr {
      position: sticky;
      top: 0;
      z-index: 10;
      background: var(--surf);
      border-bottom: 1px solid var(--bdr);
      box-shadow: 0 2px 12px rgba(0, 0, 0, .06);
    }

    .exam-hdr-inner {
      display: flex;
      align-items: center;
      gap: 10px;
      padding: 10px 16px;
    }

    .exam-back-btn {
      width: 36px;
      height: 36px;
      border-radius: 50%;
      border: 1.5px solid var(--bdr);
      background: var(--s2);
      color: var(--t2);
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
      transition: var(--fast);
    }

    .exam-back-btn:hover {
      background: var(--brand-bg);
      border-color: var(--brand-l);
      color: var(--brand);
    }

    .exam-hdr-title {
      font-size: .92rem;
      font-weight: 800;
      color: var(--t1);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
      flex: 1;
    }

    .exam-hdr-sub {
      font-size: .67rem;
      color: var(--t4);
      margin-top: 1px;
    }

    .exam-timer {
      display: none;
      padding: 5px 13px;
      border-radius: var(--r-full);
      font-size: .88rem;
      font-weight: 800;
      white-space: nowrap;
      flex-shrink: 0;
      background: var(--warn-bg);
      border: 1.5px solid #fde68a;
      color: var(--warn);
      font-family: 'Baloo Bhaijaan 2', sans-serif;
      transition: background var(--fast), color var(--fast), border-color var(--fast);
    }

    .exam-timer.urgent {
      background: var(--err-bg);
      border-color: #fca5a5;
      color: var(--err);
    }

    .exam-prog-track {
      height: 3px;
      background: var(--bdr);
    }

    .exam-prog-fill {
      height: 100%;
      background: linear-gradient(90deg, var(--brand), var(--cou-l));
      transition: width .35s var(--ease);
      width: 0%;
    }

    /* ── Questions area ── */
    .exam-questions {
      padding: 18px 16px 130px;
      max-width: 720px;
      margin: 0 auto;
      width: 100%;
    }

    /* ── Sticky submit footer ── */
    .exam-footer {
      position: sticky;
      bottom: 0;
      z-index: 10;
      background: rgba(255, 255, 255, .95);
      backdrop-filter: blur(14px);
      border-top: 1px solid var(--bdr);
      padding: 10px 16px max(10px, env(safe-area-inset-bottom));
      box-shadow: 0 -4px 20px rgba(0, 0, 0, .07);
      margin-top: auto;
    }

    .exam-footer-inner {
      max-width: 720px;
      margin: 0 auto;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .exam-ans-count {
      flex: 1;
      font-size: .8rem;
      color: var(--t4);
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 5px;
    }

    .exam-ans-count strong {
      color: var(--t1);
      font-size: .95rem;
    }

    /* ── Result card ── */
    .exam-result-wrap {
      width: 100%;
      max-width: 440px;
    }

    .exam-result-card {
      border-radius: var(--r-xl);
      overflow: hidden;
      box-shadow: var(--sh-lg);
      border: 1px solid var(--bdr);
      margin-bottom: 14px;
    }

    .exam-result-hero {
      padding: 36px 24px 28px;
      text-align: center;
      position: relative;
      overflow: hidden;
    }

    .exam-result-icon-ring {
      width: 80px;
      height: 80px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 16px;
      font-size: 2rem;
      border: 3px solid rgba(255, 255, 255, .4);
      background: rgba(255, 255, 255, .2);
      backdrop-filter: blur(8px);
    }

    .exam-result-score-big {
      font-size: 3.4rem;
      font-weight: 800;
      color: #fff;
      line-height: 1;
      text-shadow: 0 2px 16px rgba(0, 0, 0, .2);
    }

    .exam-result-pct {
      font-size: 1rem;
      color: rgba(255, 255, 255, .85);
      margin-top: 4px;
      font-weight: 700;
    }

    .exam-result-body {
      padding: 20px 22px;
    }

    .exam-result-msg {
      font-size: 1rem;
      font-weight: 700;
      color: var(--t1);
      text-align: center;
      margin-bottom: 14px;
      line-height: 1.5;
    }

    .exam-result-coupons {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 11px 20px;
      border-radius: var(--r-full);
      background: linear-gradient(135deg, var(--cou-l), var(--cou));
      color: #fff;
      font-weight: 800;
      font-size: .95rem;
      box-shadow: 0 4px 14px rgba(124, 58, 237, .35);
    }

    .birthday-greeting-btn {
      display: none;
      align-items: center;
      justify-content: center;
      gap: 8px;
      margin-top: 12px;
      padding: 8px 16px;
      border-radius: var(--r-full);
      border: 1px solid rgba(255, 255, 255, .34);
      background: rgba(255, 255, 255, .18);
      color: #fff;
      font-family: inherit;
      font-size: .82rem;
      font-weight: 800;
      cursor: pointer;
      box-shadow: 0 10px 28px rgba(15, 23, 42, .18);
      backdrop-filter: blur(10px);
      transition: var(--fast);
    }

    .birthday-greeting-btn.show {
      display: inline-flex;
    }

    .birthday-greeting-btn:hover {
      background: rgba(255, 255, 255, .28);
      transform: translateY(-1px);
    }

    .birthday-card-preview {
      width: min(100%, 360px);
      aspect-ratio: 4 / 5;
      border-radius: var(--r-lg);
      overflow: hidden;
      background: var(--s2);
      border: 1px solid var(--bdr);
      box-shadow: var(--sh-lg);
      margin: 0 auto;
    }

    .birthday-card-preview canvas {
      display: block;
      width: 100%;
      height: 100%;
    }

    .birthday-actions {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
      padding: 14px 22px 0;
    }

    /* ══ BOTTOM NAVIGATION ══════════════════════════════════════ */
    .bottom-nav {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      height: 64px;
      background: rgba(255, 255, 255, 0.96);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border-top: 1.5px solid var(--bdr2);
      display: flex;
      justify-content: space-around;
      align-items: center;
      z-index: 490;
      padding-bottom: env(safe-area-inset-bottom);
      box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.04);
    }
    .bottom-nav-item {
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      color: var(--t3);
      font-size: 0.68rem;
      font-weight: 800;
      text-decoration: none;
      cursor: pointer;
      transition: all var(--fast);
      flex: 1;
      height: 100%;
      gap: 3px;
    }
    .bottom-nav-item i {
      font-size: 1.2rem;
      transition: transform var(--fast);
    }
    .bottom-nav-item.active {
      color: var(--brand);
    }
    .bottom-nav-item.active i {
      transform: translateY(-2px);
    }
    .bottom-nav-item.center-fab {
      position: relative;
      height: 100%;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-end;
      padding-bottom: 6px;
    }
    .fab-btn {
      width: 48px;
      height: 48px;
      border-radius: var(--r-full);
      background: linear-gradient(135deg, var(--brand) 0%, var(--cou) 100%);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 6px 16px rgba(79, 70, 229, 0.3);
      transform: translateY(-10px);
      transition: all var(--fast);
      border: none;
    }
    .fab-btn i {
      font-size: 1.25rem;
      color: #fff;
    }
    .bottom-nav-item.center-fab:hover .fab-btn {
      transform: translateY(-12px) scale(1.05);
      box-shadow: 0 8px 20px rgba(79, 70, 229, 0.4);
    }
    
    /* ══ SIBLINGS & TABS CUSTOM STYLES ══════════════════════════ */
    .sibling-card:hover {
      border-color: var(--brand) !important;
      transform: translateY(-2px);
      box-shadow: var(--sh-md) !important;
    }
    .send-cat-btn {
      background: var(--s2);
      border: 1.5px solid var(--bdr);
      border-radius: var(--r-sm);
      padding: 8px 4px;
      font-size: .75rem;
      font-weight: 800;
      color: var(--t2);
      cursor: pointer;
      font-family: inherit;
      transition: all var(--fast);
      outline: none;
    }
    .send-cat-btn.active {
      background: var(--brand);
      color: #fff;
      border-color: var(--brand);
    }
    .send-recipient-chip {
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 8px 12px;
      background: var(--s2);
      border: 1.5px solid var(--bdr);
      border-radius: var(--r-md);
      font-size: .75rem;
      font-weight: 800;
      cursor: pointer;
      min-width: 80px;
      flex-shrink: 0;
      gap: 4px;
      transition: all var(--fast);
    }
    .send-recipient-chip.active {
      border-color: var(--brand);
      background: var(--brand-bg);
      color: var(--brand);
    }

    /* ══ V3 TABS AND SEARCH STYLE ══════════════════════════════ */
    .send-coupons-page {
      min-height: calc(100vh - 120px);
      margin: 0 !important;
      border-radius: 0 !important;
      border: none !important;
      background: linear-gradient(145deg, #312e81 0%, #4f46e5 35%, #7c3aed 70%, #5b21b6 100%) !important;
      position: relative;
      overflow: hidden;
      color: #fff;
      padding: 24px 18px 80px !important;
    }
    .send-coupons-page::before {
      content: '';
      position: absolute;
      inset: 0;
      background:
        radial-gradient(circle at 20% 30%, rgba(255, 255, 255, .07) 0%, transparent 40%),
        radial-gradient(circle at 80% 70%, rgba(255, 255, 255, .05) 0%, transparent 35%);
      animation: hero-pulse 6s ease-in-out infinite;
      pointer-events: none;
      z-index: 0;
    }
    .send-coupons-page::after {
      content: '';
      position: absolute;
      inset: 0;
      pointer-events: none;
      background-image: radial-gradient(circle, rgba(255, 255, 255, .08) 1px, transparent 1px);
      background-size: 28px 28px;
      z-index: 0;
    }
    .send-coupons-page > * {
      position: relative;
      z-index: 1;
    }
    #scTasks.fullscreen-tab {
      min-height: calc(100vh - 120px);
      margin: 0 !important;
      border-radius: 0 !important;
      border: none !important;
      background: var(--surf) !important;
      padding: 20px 16px 80px !important;
    }
    .wizard-step-container {
      background: rgba(255, 255, 255, 0.12);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: none;
      border-radius: var(--r-lg);
      padding: 20px 16px;
      margin-top: 14px;
    }
    .wizard-step-title {
      font-size: 1.05rem;
      font-weight: 800;
      color: #fff;
      margin-bottom: 12px;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .wizard-btn-row {
      display: flex;
      gap: 10px;
      margin-top: 18px;
    }
    .send-wizard-input {
      width: 100%;
      padding: 12px;
      border: 1.5px solid rgba(255, 255, 255, 0.3);
      background: rgba(255, 255, 255, 0.1);
      border-radius: var(--r-sm);
      color: #fff;
      font-family: inherit;
      font-size: 1rem;
      text-align: center;
      outline: none;
      transition: all var(--fast);
    }
    .send-wizard-input::placeholder {
      color: rgba(255, 255, 255, 0.6);
    }
    .send-wizard-input:focus {
      border-color: #fff;
      background: rgba(255, 255, 255, 0.2);
    }
    
    .send-cat-btn {
      background: rgba(255, 255, 255, 0.12);
      border: 1.5px solid rgba(255, 255, 255, 0.2);
      border-radius: var(--r-sm);
      padding: 8px 4px;
      font-size: .75rem;
      font-weight: 800;
      color: #fff;
      cursor: pointer;
      font-family: inherit;
      transition: all var(--fast);
      outline: none;
    }
    .send-cat-btn.active {
      background: #fff;
      color: var(--brand);
      border-color: #fff;
    }
    .send-recipient-chip {
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 8px 12px;
      background: rgba(255, 255, 255, 0.12);
      border: 1.5px solid rgba(255, 255, 255, 0.2);
      border-radius: var(--r-md);
      font-size: .75rem;
      font-weight: 800;
      cursor: pointer;
      min-width: 80px;
      flex-shrink: 0;
      gap: 4px;
      transition: all var(--fast);
      color: #fff;
    }
    .send-recipient-chip.active {
      border-color: #fff;
      background: #fff;
      color: var(--brand);
    }
    
    /* Sleek Home Search Bar */
    .home-search-bar input {
      border: 2px solid var(--bdr) !important;
      box-shadow: var(--sh-sm) !important;
    }
    .home-search-bar input:focus {
      border-color: var(--brand) !important;
      box-shadow: 0 0 0 4px var(--brand-glow) !important;
    }
    
    /* Step progress dots */
    .wizard-dots {
      display: flex;
      justify-content: center;
      gap: 6px;
      margin-bottom: 12px;
    }
    .wizard-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.3);
      transition: all var(--fast);
    }
    .wizard-dot.active {
      background: #fff;
      transform: scale(1.2);
    }
    
    /* Toggle Switch Styling */
    .switch-toggle {
      position: relative;
      display: inline-block;
      width: 46px;
      height: 24px;
    }
    .switch-toggle input {
      opacity: 0;
      width: 0;
      height: 0;
    }
    .slider-toggle {
      position: absolute;
      cursor: pointer;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: var(--bdr);
      transition: .3s;
      border-radius: 24px;
    }
    .slider-toggle:before {
      position: absolute;
      content: "";
      height: 18px;
      width: 18px;
      left: 3px;
      bottom: 3px;
      background-color: white;
      transition: .3s;
      border-radius: 50%;
      box-shadow: 0 1px 3px rgba(0,0,0,0.2);
    }
    .switch-toggle input:checked + .slider-toggle {
      background-color: var(--brand);
    }
    .switch-toggle input:checked + .slider-toggle:before {
      transform: translateX(-22px); /* RTL slide direction */
    }

  </style>
    <script src="/js/og-meta.js"></script>
</head>

<body>

  <div class="loading-screen" id="ls">
    <div class="spin"></div><span id="lt">جارٍ التحميل…</span>
  </div>

  <!-- ══ HERO ══ -->
  <div class="hero" id="hero" style="display:none">
    <div class="hero-top">
      <div class="hero-church-chip" id="churchChip" style="display:none">
        <i class="fas fa-church"></i><span id="churchName"></span>
      </div>
      <div class="hero-actions-top">
        <div class="hero-ico-btn" id="switchBtnTop" style="display:none" onclick="openOv('switchOv')"
          title="تبديل الحساب"><i class="fas fa-exchange-alt"></i></div>
        <div class="hero-ico-btn" id="settingsTop" style="display:none" onclick="openOv('settingsOv')"
          title="الإعدادات"><i class="fas fa-cog"></i></div>
      </div>
    </div>
    <div class="hero-body">
      <div class="avatar-ring" id="avatarRing">
        <div class="avatar-inner" id="avatarInner"><i class="fas fa-user"></i></div>
        <div class="avatar-edit-fab" id="avatarEdit" onclick="openOv('photoOv')"><i class="fas fa-camera"></i></div>
      </div>
      <div class="hero-name" id="heroName">—</div>
      <div class="hero-tags" id="heroTags">
        <span class="htag class-tag" id="heroClass"><i class="fas fa-graduation-cap"></i><span
            id="heroClassTxt">—</span></span>
        <div class="uncle-strip" id="uncleStrip" style="display:none"></div>
      </div>
      <button class="birthday-greeting-btn" id="birthdayGreetingBtn" type="button" onclick="openBirthdayGreeting()">
        <i class="fas fa-cake-candles"></i>
        <span>صورة عيد الميلاد</span>
      </button>
    </div>
    <!-- Coupon hero card (private only) -->
    <div class="coupon-hero" id="couponHero" style="display:none">
      <div>
        <div class="ch-total-label"><i class="fas fa-star"></i> <span>إجمالي كوبوناتك</span></div>
        <div class="ch-total-val" id="chTotal">0</div>
        <div class="ch-total-unit">كوبون</div>
      </div>
      <div class="ch-breakdown" id="chBreakdown"></div>
    </div>
    <div class="hero-wave"></div>
  </div>

  <!-- stats bar -->
  <div class="stats-bar" id="statsBar" style="display:none">
    <div class="sb-cell ok">
      <div class="sb-val" id="sbP">0</div>
      <div class="sb-lbl">حضر</div>
    </div>
    <div class="sb-cell err">
      <div class="sb-val" id="sbA">0</div>
      <div class="sb-lbl">غاب</div>
    </div>
    <div class="sb-cell neu">
      <div class="sb-val" id="sbR">0%</div>
      <div class="sb-lbl">نسبة</div>
    </div>
    <div class="sb-cell cou">
      <div class="sb-val" id="sbC">0</div>
      <div class="sb-lbl">كوبون</div>
    </div>
  </div>

  <!-- Page -->
  <div class="page" id="mainPage" style="display:none">

    <!-- Announcement notification banner -->
    <div class="ann-banner-wrap" id="scAnnBanner" style="display:none;">
      <div style="padding: 12px 16px; background:var(--warn-bg); border: 1.5px solid var(--warn-l); border-radius: var(--r-md); display: flex; align-items: flex-start; gap: 10px; color: var(--warn); direction: rtl; box-shadow: var(--sh-sm);">
        <div style="font-size: 1.25rem; color: var(--warn-l); flex-shrink: 0; margin-top: 2px;"><i class="fas fa-bell"></i></div>
        <div style="flex: 1; min-width: 0;">
           <div style="font-weight: 800; font-size: 0.82rem; margin-bottom: 2px; color:var(--warn);">إشعار هام:</div>
           <div id="annBannerList" style="font-size: 0.85rem; line-height: 1.4; color: var(--t1);"></div>
        </div>
        <button onclick="dismissAnnBanner()" style="background:none; border:none; color:var(--warn); opacity: 0.7; cursor:pointer; font-size:1rem; display:flex; align-items:center; justify-content:center; padding: 2px; margin-top: 2px; transition: opacity var(--fast);" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0.7" title="إغلاق"><i class="fas fa-times"></i></button>
      </div>
    </div>

    <!-- Top Search Bar (Home page search) -->
    <div id="homeSearchBar" style="display:none; padding: 14px 16px 8px; position:relative; z-index:99;">
      <div style="position:relative;">
        <i class="fas fa-search" style="position:absolute; right:16px; top:50%; transform:translateY(-50%); color:var(--t4); font-size:.95rem; pointer-events:none;"></i>
        <input type="text" id="homeFriendSearch" placeholder="ابحث عن أصحابك في الكنيسة..." style="width:100%; padding:12px 42px 12px 42px; border:2px solid var(--bdr); border-radius:var(--r-xl); font-family:inherit; font-size:.88rem; outline:none; background:var(--surf); color:var(--t1); box-shadow:var(--sh-sm); transition:all var(--fast);" oninput="onHomeSearch(this.value)" autocomplete="off">
        <button onclick="clearHomeSearch()" style="position:absolute; left:16px; top:50%; transform:translateY(-50%); background:none; border:none; color:var(--t4); cursor:pointer; font-size:.95rem; display:flex; align-items:center; justify-content:center;"><i class="fas fa-times"></i></button>
      </div>
      <div id="homeSearchResults" class="inline-search-dropdown"></div>
    </div>

    <!-- ══ FULL PAGE SEND COUPONS WIZARD ══ -->
    <div class="send-coupons-page" id="scSendCoupons" style="display:none">
      <!-- Tab Header -->
      <div style="display:flex; align-items:center; gap:12px; margin-bottom:20px;">
        <div style="width:50px; height:50px; border-radius:50%; background:rgba(255,255,255,0.15); color:#fff; display:flex; align-items:center; justify-content:center; font-size:1.35rem; flex-shrink:0; box-shadow: var(--sh-md);">
          <i class="fas fa-star"></i>
        </div>
        <div>
          <div style="font-size:1.15rem; font-weight:800; color:#fff;">إرسال كوبونات</div>
          <div style="font-size:.78rem; color:rgba(255,255,255,0.75);">شارك كوبوناتك مع إخوتك وصديقك المقرب</div>
        </div>
      </div>

      <!-- Step Progress Dots -->
      <div class="wizard-dots">
         <div class="wizard-dot active" id="dot1"></div>
         <div class="wizard-dot" id="dot2"></div>
         <div class="wizard-dot" id="dot3"></div>
      </div>

      <!-- STEP 1: Select Recipient -->
      <div class="wizard-step-container" id="sendStep1">
         <div class="wizard-step-title"><i class="fas fa-user-plus"></i> اختر المستلم</div>
         
         <div id="siblingChipsContainer" style="display:none; margin-bottom:12px;">
            <div style="font-size:.72rem; color:rgba(255,255,255,0.8); margin-bottom:6px; font-weight:700;">إخوتك:</div>
            <div id="sendSiblingChips" style="display:flex; gap:8px; overflow-x:auto; padding-bottom:6px; scrollbar-width:none; -webkit-overflow-scrolling:touch;"></div>
         </div>

         <div style="font-size:.72rem; color:rgba(255,255,255,0.8); margin-bottom:6px; font-weight:700;">ابحث عن صديق بالاسم:</div>
         <div style="position:relative;">
            <i class="fas fa-search" style="position:absolute; right:12px; top:50%; transform:translateY(-50%); font-size:.82rem; color:rgba(255,255,255,0.6); pointer-events:none;"></i>
            <input type="text" id="sendFriendSearch" placeholder="اكتب اسم صديقك..." class="send-wizard-input" style="padding-right:36px; text-align:right;" oninput="onSendFriendSearch(this.value)" autocomplete="off">
         </div>
         <div id="sendFriendSearchResults" style="max-height:160px; overflow-y:auto; background:rgba(255,255,255,0.95); border-radius:var(--r-sm); margin-top:6px; display:none; color:var(--t1); z-index: 10; position: relative;"></div>

         <!-- Selected Tag -->
         <div id="selectedRecipientTag" style="display:none; align-items:center; gap:8px; background:#fff; color:var(--brand); padding:10px 14px; border-radius:var(--r-sm); margin-top:14px; font-size:.85rem; font-weight:800; box-shadow:var(--sh-md);">
            <i class="fas fa-user-check"></i>
            <span id="selectedRecipientName">صديق محدد</span>
            <button onclick="clearSelectedRecipient()" style="margin-right:auto; background:none; border:none; color:var(--err); cursor:pointer; font-size:1.1rem; display:flex; align-items:center; padding:0;"><i class="fas fa-times-circle"></i></button>
         </div>

         <div class="wizard-btn-row">
            <button class="btn btn-p" id="toStep2Btn" style="width:100%; padding:12px; background:#fff; color:var(--brand); font-weight:800;" onclick="goToStep(2)" disabled>متابعة <i class="fas fa-chevron-left" style="margin-right:4px; font-size:.75rem;"></i></button>
         </div>
      </div>

      <!-- STEP 2: Amount & Category -->
      <div class="wizard-step-container" id="sendStep2" style="display:none;">
         <div class="wizard-step-title"><i class="fas fa-star"></i> رصيد الإرسال والقيمة</div>

         <!-- Available breakdown -->
         <div style="background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); border-radius:var(--r-md); padding:12px; margin-bottom:14px;">
            <div style="font-size:.74rem; color:rgba(255,255,255,0.7); margin-bottom:8px; text-align:center; font-weight:700;"><i class="fas fa-wallet"></i> رصيدك المتاح</div>
            <div style="display:grid; grid-template-columns:repeat(3,1fr); gap:6px; text-align:center;">
               <div style="background:rgba(255,255,255,0.1); padding:6px; border-radius:var(--r-sm);">
                  <div style="font-size:.62rem; color:rgba(255,255,255,0.8);">حضور</div>
                  <div style="font-size:.9rem; font-weight:900;" id="sendAvailAtt">0</div>
               </div>
               <div style="background:rgba(255,255,255,0.1); padding:6px; border-radius:var(--r-sm);">
                  <div style="font-size:.62rem; color:rgba(255,255,255,0.8);">التزام</div>
                  <div style="font-size:.9rem; font-weight:900;" id="sendAvailCom">0</div>
               </div>
               <div style="background:rgba(255,255,255,0.1); padding:6px; border-radius:var(--r-sm);">
                  <div style="font-size:.62rem; color:rgba(255,255,255,0.8);">مهام</div>
                  <div style="font-size:.9rem; font-weight:900;" id="sendAvailTsk">0</div>
               </div>
            </div>
            <div style="text-align:center; font-size:.78rem; font-weight:800; margin-top:8px;">إجمالي رصيدك الكلي: <span id="sendAvailTotal" style="font-weight:900;">0</span></div>
         </div>

         <!-- Choose Category -->
         <div style="margin-bottom:14px;">
            <div style="font-size:.72rem; color:rgba(255,255,255,0.8); margin-bottom:6px; font-weight:700;">أرسل من تصنيف:</div>
            <div style="display:grid; grid-template-columns:repeat(4,1fr); gap:6px;">
               <button class="send-cat-btn active" data-cat="all" onclick="selectSendCat(this)">الكل</button>
               <button class="send-cat-btn" data-cat="att" onclick="selectSendCat(this)">حضور</button>
               <button class="send-cat-btn" data-cat="com" onclick="selectSendCat(this)">التزام</button>
               <button class="send-cat-btn" data-cat="task" onclick="selectSendCat(this)">مهام</button>
            </div>
         </div>

         <!-- Enter Amount -->
         <div style="margin-bottom:14px;">
            <div style="font-size:.72rem; color:rgba(255,255,255,0.8); margin-bottom:6px; font-weight:700;">عدد الكوبونات:</div>
            <input type="number" id="sendAmount" placeholder="أدخل العدد..." class="send-wizard-input" min="1" oninput="checkStep2Valid()">
         </div>

         <div class="wizard-btn-row">
            <button class="btn btn-g" style="flex:1; padding:12px; background:rgba(255,255,255,0.2); border:none; color:#fff;" onclick="goToStep(1)"><i class="fas fa-chevron-right"></i> رجوع</button>
            <button class="btn btn-p" id="toStep3Btn" style="flex:2; padding:12px; background:#fff; color:var(--brand); font-weight:800;" onclick="goToStep(3)" disabled>التالي <i class="fas fa-chevron-left"></i></button>
         </div>
      </div>

      <!-- STEP 3: Password & Confirm -->
      <div class="wizard-step-container" id="sendStep3" style="display:none;">
         <div class="wizard-step-title"><i class="fas fa-shield-alt"></i> تأكيد الهوية والإرسال</div>

         <div style="background:rgba(255,255,255,0.08); border:1px solid rgba(255,255,255,0.15); border-radius:var(--r-md); padding:14px; text-align:center; font-size:.85rem; line-height:1.5; margin-bottom:14px;" id="sendSummaryMsg">
            سوف تقوم بإرسال 0 كوبون إلى صديقك.
         </div>

         <div style="margin-bottom:14px;">
            <div style="font-size:.72rem; color:rgba(255,255,255,0.8); margin-bottom:6px; font-weight:700;">اكتب كلمة مرور حسابك لتأكيد العملية:</div>
            <input type="password" id="sendPassword" placeholder="كلمة المرور الخاصة بك..." class="send-wizard-input" oninput="checkStep3Valid()">
         </div>

         <div class="wizard-btn-row" style="align-items: center; justify-content: center; gap: 16px;">
            <button class="btn btn-g" style="padding: 12px 20px; background:rgba(255,255,255,0.2); border:none; color:#fff; border-radius: var(--r-md); height: 48px; display: flex; align-items: center; justify-content: center; font-weight: 800;" onclick="goToStep(2)"><i class="fas fa-chevron-right" style="margin-left: 6px;"></i> رجوع</button>
            <button class="btn btn-p" id="sendWizardSubmitBtn" style="width: 50px; height: 50px; border-radius: 50%; background:#fff; color:var(--brand); border: none; display: flex; align-items: center; justify-content: center; box-shadow: var(--sh-md); font-size: 1.3rem; flex-shrink: 0; padding: 0; margin: 0 auto;" onclick="trySendCoupons()" disabled><i class="fas fa-star"></i></button>
         </div>
      </div>
    </div>


    <!-- public banner -->
    <div class="public-banner" id="pubBanner" style="display:none">
      <i class="fas fa-eye"></i> عرض عام
      <a href="/user/login"
        style="margin-right:auto;color:var(--brand);text-decoration:none;font-size:.74rem;font-weight:800;"><i
          class="fas fa-sign-in-alt"></i> دخول</a>
    </div>

    <!-- Friend mode banner -->
    <div class="friend-banner" id="friendBanner" style="display:none">
      <i class="fas fa-user-friends"></i>
      <span id="friendBannerName">ملف صديق</span>
      <button onclick="returnToMyProfile()"
        style="margin-right:auto;background:none;border:none;color:#9333ea;font-size:.8rem;font-weight:800;cursor:pointer;font-family:inherit;padding:0;display:flex;align-items:center;gap:5px;"><i
          class="fas fa-arrow-right"></i> <span>رجوع</span></button>
    </div>

    <!-- Info -->
    <div class="sc" id="scInfo">
      <div class="sc-head">
        <div class="sc-ico" style="background:#e0e7ff;color:var(--brand);"><i class="fas fa-id-card"></i></div>
        <div class="sc-label">
          <div class="sc-title">المعلومات الشخصية</div>
        </div>
      </div>
      <div class="sc-body">
        <div class="info-grid" id="infoGrid"></div>
      </div>
    </div>


    <!-- Trips -->
    <div class="sc" id="scTrips" style="display:none">
      <div class="sc-head">
        <div class="sc-ico" style="background:var(--trip-bg);color:var(--trip-l);"><i class="fas fa-bus"></i></div>
        <div class="sc-label">
          <div class="sc-title">الرحلات / المؤتمرات</div>
          <div class="sc-sub" id="tripSub"></div>
        </div>
      </div>
      <div class="sc-body">
        <div id="tripList"></div>
      </div>
    </div>


    <!-- Attendance -->
    <div class="sc" id="scAtt" style="display:none">
      <div class="sc-head">
        <div class="sc-ico" style="background:var(--ok-bg);color:var(--ok);"><i class="fas fa-calendar-check"></i></div>
        <div class="sc-label">
          <div class="sc-title">سجل الحضور</div>
          <div class="sc-sub" id="attSub">آخر 12 أسبوع</div>
        </div>
        <div class="sc-badge" id="attBadge"></div>
      </div>
      <div class="sc-body">
        <div class="att-stats">
          <div class="as ok">
            <div class="as-val" id="ap">0</div>
            <div class="as-lbl">حضر</div>
          </div>
          <div class="as err">
            <div class="as-val" id="aa">0</div>
            <div class="as-lbl">غاب</div>
          </div>
          <div class="as neu">
            <div class="as-val" id="ar">0%</div>
            <div class="as-lbl">نسبة</div>
          </div>
        </div>
        <div class="cal-grid" id="calGrid"></div>
        <div style="text-align:center;margin-top:4px;">
          <span class="att-view-all" id="attViewAllBtn" onclick="openAttHistory()">
            <i class="fas fa-list-ul"></i> <span>عرض السجل بالكامل</span>
          </span>
        </div>
      </div>
    </div>

    <!-- Tasks -->
    <div class="sc fullscreen-tab" id="scTasks" style="display:none">
      <div class="sc-head">
        <div class="sc-ico" style="background:var(--brand-bg);color:var(--brand);"><i class="fas fa-tasks"></i></div>
        <div class="sc-label">
          <div class="sc-title">الاختبارات والمهام</div>
          <div class="sc-sub" id="taskSub">0 مهمة</div>
        </div>
      </div>
      <div class="sc-body">
        <div id="taskList"></div>
      </div>
    </div>

    <!-- Paper Exams -->
    <div class="sc" id="scPaperExams" style="display:none">
      <div class="sc-head">
        <div class="sc-ico" style="background:#fef3c7;color:#d97706;"><i class="fas fa-file-invoice"></i></div>
        <div class="sc-label">
          <div class="sc-title">الامتحانات الورقية</div>
          <div class="sc-sub" id="paperExamsSub">درجات الامتحانات التحريرية وأوراق الإجابات</div>
        </div>
      </div>
      <div class="sc-body">
        <div id="paperExamsList" style="display:flex; flex-direction:column; gap:8px;"></div>
      </div>
    </div>



    <!-- Announcements -->
    <div class="sc" id="scAnn" style="display:none">
      <div class="sc-head">
        <div class="sc-ico" style="background:var(--warn-bg);color:var(--warn-l);"><i class="fas fa-bullhorn"></i></div>
        <div class="sc-label">
          <div class="sc-title">الإعلانات</div>
          <div class="sc-sub" id="annSub">كل جديد يخصك هيظهر هنا أولاً</div>
        </div>
        <div class="sc-badge" id="annBadge">0</div>
      </div>
      <div class="sc-body">
        <div id="annList"></div>
      </div>
    </div>


    <!-- Siblings Section -->
    <div class="sc" id="scSiblings" style="display:none">
      <div class="sc-head">
        <div class="sc-ico" style="background:#e0f2fe;color:#0369a1;"><i class="fas fa-user-friends"></i></div>
        <div class="sc-label">
          <div class="sc-title">إخوتك</div>
          <div class="sc-sub">اضغط على أي من إخوتك لزيارة حسابه مباشرة</div>
        </div>
      </div>
      <div class="sc-body">
        <div id="siblingsList" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:12px;"></div>
      </div>
    </div>




    <!-- Uncles section -->
    <div class="sc" id="scUncles" style="display:none;">
      <div class="sc-head">
        <div class="sc-ico" style="background:#ede9fe;color:#7c3aed;"><i class="fas fa-chalkboard-teacher"></i></div>
        <div class="sc-label">
          <div class="sc-title">انكل وطنط اللي معاك في الفصل</div>
          <div class="sc-sub" id="unclesSub"></div>
        </div>
      </div>
      <div class="sc-body">
        <div id="uncleCardGrid"
          style="display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:14px;"></div>
      </div>
    </div>



    <div style="text-align:center;padding:18px 0 0;font-size:.72rem;color:var(--t4);">
      <span style="font-weight:700;">Sunday School 2026</span><br>
      <!-- مُكْثِرِينَ فِي عَمَلِ الرَّبِّ كُلَّ حِينٍ-->
    </div>
  </div>

  <div class="no-profile" id="noProfile" style="display:none">
    <i class="fas fa-user-slash"></i>
    <h2>لم يُعثر على ملف شخصي</h2>
    <p id="noMsg">يرجى تسجيل الدخول أو استخدام رابط المعرّف</p>
    <a href="/user/login" class="btn btn-p"><i class="fas fa-sign-in-alt"></i> تسجيل الدخول</a>
  </div>


  <!-- ══ ATTENDANCE HISTORY SHEET ══ -->
  <div class="overlay settings-overlay" id="attHistOv">
    <div class="settings-sheet" style="max-width:600px;max-height:92vh;">
      <div class="ss-handle"></div>

      <!-- Header -->
      <div style="padding:14px 20px 12px;border-bottom:1px solid var(--bdr2);display:flex;align-items:center;gap:12px;">
        <div
          style="width:38px;height:38px;border-radius:var(--r-sm);background:var(--ok-bg);color:var(--ok);display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0;">
          <i class="fas fa-calendar-check"></i>
        </div>
        <div style="flex:1;">
          <div style="font-size:1rem;font-weight:800;color:var(--t1);">سجل الحضور الكامل</div>
          <div style="font-size:.72rem;color:var(--t4);font-weight:600;" id="attHistSubtitle">جارٍ التحميل…</div>
        </div>
        <button onclick="closeOv('attHistOv')"
          style="width:30px;height:30px;border-radius:var(--r-sm);border:1.5px solid var(--bdr);background:var(--s2);color:var(--t3);font-size:.82rem;cursor:pointer;display:flex;align-items:center;justify-content:center;">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <!-- Summary -->
      <div
        style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;padding:12px 16px;border-bottom:1px solid var(--bdr2);">
        <div
          style="text-align:center;padding:10px 6px;border-radius:var(--r-md);background:var(--ok-bg);border:1px solid #6ee7b7;">
          <div style="font-size:1.2rem;font-weight:800;color:var(--ok);" id="ahsPresent">0</div>
          <div style="font-size:.6rem;color:var(--t4);margin-top:2px;font-weight:600;">حضر</div>
        </div>
        <div
          style="text-align:center;padding:10px 6px;border-radius:var(--r-md);background:var(--err-bg);border:1px solid #fca5a5;">
          <div style="font-size:1.2rem;font-weight:800;color:var(--err);" id="ahsAbsent">0</div>
          <div style="font-size:.6rem;color:var(--t4);margin-top:2px;font-weight:600;">غاب</div>
        </div>
        <div
          style="text-align:center;padding:10px 6px;border-radius:var(--r-md);background:var(--s2);border:1px solid var(--bdr);">
          <div style="font-size:1.2rem;font-weight:800;color:var(--brand);" id="ahsRate">0%</div>
          <div style="font-size:.6rem;color:var(--t4);margin-top:2px;font-weight:600;">نسبة الحضور</div>
        </div>
      </div>

      <!-- Filters -->
      <div
        style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:10px 16px;border-bottom:1px solid var(--bdr2);background:var(--s2);">
        <input id="attHistSearch" type="text" placeholder="ابحث بالتاريخ…"
          style="flex:1;min-width:120px;padding:8px 12px;border:1.5px solid var(--bdr);border-radius:var(--r-sm);font-family:'Baloo Bhaijaan 2',sans-serif;font-size:.86rem;background:var(--surf);color:var(--t1);outline:none;"
          oninput="renderAttHist()" />
        <select id="attHistSort" onchange="renderAttHist()"
          style="padding:7px 10px;border:1.5px solid var(--bdr);border-radius:var(--r-sm);font-family:'Baloo Bhaijaan 2',sans-serif;font-size:.8rem;background:var(--surf);color:var(--t2);outline:none;">
          <option value="newest">الأحدث أولاً</option>
          <option value="oldest">الأقدم أولاً</option>
        </select>
        <div style="display:flex;gap:6px;flex-wrap:wrap;">
          <span class="fchip active" data-filter="all" onclick="setAttFilter(this,'all')">الكل</span>
          <span class="fchip ok" data-filter="present" onclick="setAttFilter(this,'present')">✓ حضر</span>
          <span class="fchip err" data-filter="absent" onclick="setAttFilter(this,'absent')">✗ غاب</span>
          <span class="fchip" data-filter="unrecorded" onclick="setAttFilter(this,'unrecorded')">— غير مسجّل</span>
        </div>
      </div>

      <!-- Count -->
      <div id="attHistCount" style="font-size:.7rem;color:var(--t4);font-weight:600;padding:6px 16px 2px;"></div>

      <!-- List (scrollable) -->
      <div id="attHistList" class="att-hist-scroll"
        style="padding:6px 14px 12px;overflow-y:auto;max-height:calc(92vh - 290px);display:flex;flex-direction:column;gap:5px;">
        <div style="text-align:center;padding:28px;color:var(--t4);font-size:.88rem;">
          <i class="fas fa-spinner fa-spin"
            style="display:block;font-size:1.6rem;margin-bottom:8px;opacity:.4;"></i>جارٍ التحميل…
        </div>
      </div>

    </div>
  </div>

  <!-- ══ REPORT ATTENDANCE ERROR (WhatsApp) SHEET ══ -->
  <div class="overlay settings-overlay" id="attReportOv">
    <div class="settings-sheet" style="max-width:480px;">
      <div class="ss-handle"></div>
      <div style="padding:14px 20px 12px;border-bottom:1px solid var(--bdr2);display:flex;align-items:center;gap:12px;">
        <div
          style="width:36px;height:36px;border-radius:var(--r-sm);background:#fef3c7;color:#d97706;display:flex;align-items:center;justify-content:center;font-size:.92rem;flex-shrink:0;">
          <i class="fas fa-flag"></i>
        </div>
        <div style="flex:1;">
          <div style="font-size:.96rem;font-weight:800;color:var(--t1);">بلّغ عن خطأ</div>
          <div style="font-size:.72rem;color:var(--t4);font-weight:600;" id="reportDateLabel"></div>
        </div>
        <button onclick="closeOv('attReportOv')"
          style="width:28px;height:28px;border-radius:var(--r-sm);border:1.5px solid var(--bdr);background:var(--s2);color:var(--t3);font-size:.8rem;cursor:pointer;display:flex;align-items:center;justify-content:center;">
          <i class="fas fa-times"></i>
        </button>
      </div>

      <!-- Only picker: what SHOULD it be -->
      <div style="padding:16px 16px 12px;border-bottom:1px solid var(--bdr2);">
        <div style="font-size:.74rem;font-weight:700;color:var(--t3);margin-bottom:10px;">المفروض أنا كنت</div>
        <div style="display:flex;gap:10px;">
          <button id="reportShouldPresent" onclick="setReportShould('present')"
            style="flex:1;padding:14px 8px;border-radius:var(--r-md);border:2px solid var(--bdr);background:var(--surf);color:var(--t2);font-family:'Baloo Bhaijaan 2',sans-serif;font-size:.95rem;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;transition:var(--fast);">
            <i class="fas fa-check-circle"></i> <span>حضر</span>
          </button>
          <button id="reportShouldAbsent" onclick="setReportShould('absent')"
            style="flex:1;padding:14px 8px;border-radius:var(--r-md);border:2px solid var(--bdr);background:var(--surf);color:var(--t2);font-family:'Baloo Bhaijaan 2',sans-serif;font-size:.95rem;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;transition:var(--fast);">
            <i class="fas fa-times-circle"></i> <span>غاب</span>
          </button>
        </div>
      </div>

      <!-- Uncle list -->
      <div style="padding:12px 16px 4px;">
        <div style="font-size:.72rem;font-weight:700;color:var(--t3);margin-bottom:8px;">ابعت للمدرّس</div>
        <div id="reportUncleList" style="display:flex;flex-direction:column;gap:6px;"></div>
      </div>
      <button class="ss-close-btn" onclick="closeOv('attReportOv')">إغلاق</button>
    </div>
  </div>

  <!-- ══ OVERLAYS ══ -->

  <!-- Settings — full-screen modern sheet -->
  <div class="overlay settings-overlay" id="settingsOv">
    <div class="settings-sheet">
      <div class="ss-handle"></div>
      <div class="ss-profile" id="ssProfile">
        <div class="ss-avatar" id="ssAvatar"><i class="fas fa-user"></i></div>
        <div>
          <div class="ss-name" id="ssName">—</div>
          <div class="ss-class" id="ssClass">—</div>
        </div>
      </div>
      <div class="ss-items">
        <div class="ss-item" onclick="closeOv('settingsOv');setTimeout(()=>openOv('editOv'),180)">
          <div class="ss-item-ico" style="background:#e0e7ff;color:#4338ca;"><i class="fas fa-user-edit"></i></div>
          <div class="ss-item-label">تعديل معلوماتي</div>
          <i class="fas fa-chevron-left ss-item-arr"></i>
        </div>
        <div class="ss-item" id="passMenuItem" onclick="closeOv('settingsOv');setTimeout(()=>openOv('passOv'),180)">
          <div class="ss-item-ico" style="background:#fef3c7;color:#92400e;"><i class="fas fa-lock"></i></div>
          <div class="ss-item-label" id="passMenuLabel">تغيير كلمة المرور</div>
          <i class="fas fa-chevron-left ss-item-arr"></i>
        </div>
        <div class="ss-item" onclick="closeOv('settingsOv');setTimeout(()=>openOv('photoOv'),180)">
          <div class="ss-item-ico" style="background:#d1fae5;color:#065f46;"><i class="fas fa-camera"></i></div>
          <div class="ss-item-label">تغيير الصورة الشخصية</div>
          <i class="fas fa-chevron-left ss-item-arr"></i>
        </div>
        <div class="ss-item" id="notifToggleItem" style="display:none">
          <div class="ss-item-ico" style="background:#ffe4e6;color:#e11d48;"><i class="fas fa-bell"></i></div>
          <div class="ss-item-label">إشعارات الهاتف</div>
          <label class="switch-toggle" style="margin-left: auto; display: inline-block;">
            <input type="checkbox" id="phoneNotifToggle">
            <span class="slider-toggle"></span>
          </label>
        </div>
      </div>
      <div class="ss-divider"></div>
      <div class="ss-items">
        <div class="ss-item danger" onclick="doLogout()">
          <div class="ss-item-ico" style="background:#fee2e2;color:#b91c1c;"><i class="fas fa-sign-out-alt"></i></div>
          <div class="ss-item-label">تسجيل الخروج</div>
          <i class="fas fa-chevron-left ss-item-arr"></i>
        </div>
      </div>
      <button class="ss-close-btn" onclick="closeOv('settingsOv')">إغلاق</button>
    </div>
  </div>

  <!-- Edit Info -->
  <div class="overlay settings-overlay" id="editOv">
    <div class="settings-sheet">
      <div class="ss-handle"></div>
      <div style="padding:18px 22px 8px;border-bottom:1px solid var(--bdr2);">
        <div style="font-size:1.05rem;font-weight:800;color:var(--t1);display:flex;align-items:center;gap:10px;">
          <div
            style="width:36px;height:36px;border-radius:var(--r-sm);background:#e0e7ff;color:#4338ca;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fas fa-user-edit"></i></div>
          <span>تعديل المعلومات</span>
        </div>
      </div>
      <div style="padding:18px 22px;">
        <div class="fg"><label class="flbl">الاسم</label><input class="fi" id="eN" type="text"></div>
        <div class="fg"><label class="flbl">العنوان</label><input class="fi" id="eA" type="text"></div>
        <div class="fg"><label class="flbl">التليفون</label><input class="fi" id="eP" type="tel"></div>
        <div class="fg" style="margin-bottom:0;"><label class="flbl">تاريخ الميلاد</label><input class="fi" id="eB"
            type="text" placeholder="DD/MM/YYYY"></div>
      </div>
      <div style="padding:8px 22px 0;">
        <button class="btn btn-p" style="width:100%;padding:12px;" onclick="saveProfile()"><i class="fas fa-save"></i>
          <span>حفظ المعلومات</span></button>
      </div>
      <button class="ss-close-btn" onclick="closeOv('editOv')">إغلاق</button>
    </div>
  </div>

  <!-- Add / Change Password -->
  <div class="overlay settings-overlay" id="passOv">
    <div class="settings-sheet">
      <div class="ss-handle"></div>
      <div style="padding:18px 22px 8px;border-bottom:1px solid var(--bdr2);">
        <div style="font-size:1.05rem;font-weight:800;color:var(--t1);display:flex;align-items:center;gap:10px;">
          <div style="width:36px;height:36px;border-radius:var(--r-sm);background:#fef3c7;color:#92400e;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fas fa-lock"></i></div>
          <span id="passOvTitle">تغيير كلمة المرور</span>
        </div>
      </div>
      <div style="padding:18px 22px;">
        <!-- Note for "add" mode -->
        <div id="passAddNote" style="display:none;background:var(--brand-bg);color:var(--brand);border-radius:var(--r-sm);padding:10px 14px;font-size:.82rem;font-weight:700;margin-bottom:14px;line-height:1.5;">
          <i class="fas fa-info-circle" style="margin-left:6px;"></i>
          لا توجد كلمة مرور لحسابك بعد. أضف كلمة مرور الآن لتتمكن من إرسال الكوبونات والمزيد.
        </div>
        <!-- Old password (shown only in change mode) -->
        <div class="fg" id="passOldWrap">
          <label class="flbl">الحالية</label>
          <div class="pass-wrap"><input class="fi" id="po" type="password"><button type="button" class="pass-eye" onclick="tPass('po',this)"><i class="fas fa-eye"></i></button></div>
        </div>
        <div class="fg"><label class="flbl">الجديدة (٦ أحرف+)</label>
          <div class="pass-wrap"><input class="fi" id="pn" type="password"><button type="button" class="pass-eye" onclick="tPass('pn',this)"><i class="fas fa-eye"></i></button></div>
        </div>
        <div class="fg" style="margin-bottom:0;"><label class="flbl">تأكيد الجديدة</label>
          <div class="pass-wrap"><input class="fi" id="pc" type="password"><button type="button" class="pass-eye" onclick="tPass('pc',this)"><i class="fas fa-eye"></i></button></div>
        </div>
      </div>
      <div style="padding:8px 22px 0;">
        <button class="btn btn-p" style="width:100%;padding:12px;" onclick="changePass()"><i class="fas fa-lock"></i>
          <span id="passOvBtn">تغيير كلمة المرور</span></button>
      </div>
      <button class="ss-close-btn" onclick="closeOv('passOv')">إغلاق</button>
    </div>
  </div>

  <!-- Photo Upload -->
  <div class="overlay settings-overlay" id="photoOv">
    <div class="settings-sheet">
      <div class="ss-handle"></div>
      <div style="padding:18px 22px 8px;border-bottom:1px solid var(--bdr2);">
        <div style="font-size:1.05rem;font-weight:800;color:var(--t1);display:flex;align-items:center;gap:10px;">
          <div
            style="width:36px;height:36px;border-radius:var(--r-sm);background:#d1fae5;color:#065f46;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fas fa-camera"></i></div>
          <span>تغيير الصورة الشخصية</span>
        </div>
      </div>
      <div style="padding:18px 22px;">
        <div class="upload-drop" id="dropZone" onclick="document.getElementById('photoIn').click()">
          <i class="fas fa-cloud-upload-alt"></i>
          <p>اضغط أو اسحب صورة هنا</p>
          <input type="file" id="photoIn" accept="image/*" style="display:none" onchange="onPhoto(event)">
        </div>
        <div id="cropWrap" style="display:none">
          <div class="crop-area"><img id="cropImg" src="" alt=""></div>
        </div>
        <img id="photoPrev" src=""
          style="display:none;width:100px;height:100px;border-radius:50%;object-fit:cover;margin:0 auto 12px;border:3px solid var(--brand);">
      </div>
      <div style="padding:8px 22px 0;display:flex;gap:8px;">
        <button class="btn btn-p" id="cropBtn" style="display:none;width:100%;padding:12px;" onclick="doCrop()"><i
            class="fas fa-crop-alt"></i> <span>قص الصورة</span></button>
        <button class="btn btn-p" id="uploadBtn" style="display:none;width:100%;padding:12px;"
          onclick="uploadPhoto()"><i class="fas fa-upload"></i> <span>رفع الصورة</span></button>
      </div>
      <button class="ss-close-btn" onclick="closeOv('photoOv');resetPhoto()">إغلاق</button>
    </div>
  </div>

  <!-- Birthday Greeting -->
  <div class="overlay settings-overlay" id="bdayGreetingOv">
    <div class="settings-sheet" style="max-width:430px;">
      <div class="ss-handle"></div>
      <div
        style="padding:18px 22px 10px;border-bottom:1px solid var(--bdr2);display:flex;align-items:center;gap:10px;">
        <div
          style="width:36px;height:36px;border-radius:var(--r-sm);background:#fef3c7;color:#b45309;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
          <i class="fas fa-cake-candles"></i>
        </div>
        <div style="flex:1;">
          <div style="font-size:1.05rem;font-weight:800;color:var(--t1);">صورة عيد الميلاد</div>
          <div style="font-size:.72rem;color:var(--t4);font-weight:600;">احفظها أو شاركها مع أصحابك</div>
        </div>
        <button onclick="closeOv('bdayGreetingOv')"
          style="width:30px;height:30px;border-radius:var(--r-sm);border:1.5px solid var(--bdr);background:var(--s2);color:var(--t3);font-size:.82rem;cursor:pointer;display:flex;align-items:center;justify-content:center;">
          <i class="fas fa-times"></i>
        </button>
      </div>
      <div style="padding:18px 22px 0;">
        <div class="birthday-card-preview">
          <canvas id="birthdayGreetingCanvas" width="1080" height="1350"></canvas>
        </div>
      </div>
      <div class="birthday-actions">
        <button class="btn btn-p" onclick="shareBirthdayGreeting()"><i class="fas fa-share-alt"></i>
          <span>مشاركة</span></button>
        <button class="btn btn-p" onclick="saveBirthdayGreeting()"><i class="fas fa-download"></i>
          <span>حفظ</span></button>
      </div>
      <button class="ss-close-btn" onclick="closeOv('bdayGreetingOv')">إغلاق</button>
    </div>
  </div>




  <!-- Internal Confirmation modal for sharing -->
  <div class="overlay" id="shareConfirmModal" style="z-index:1200;">
    <div class="modal narrow" style="max-width:360px;">
      <div class="mhdr" style="background:linear-gradient(135deg,var(--brand),var(--cou));">
        <div style="width:36px;height:36px;border-radius:var(--r-sm);background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.2rem;color:#fff;"><i class="fas fa-question-circle"></i></div>
        <div class="mhdr-title" style="flex:1;">تأكيد الإرسال</div>
        <button class="mclose" onclick="closeOv('shareConfirmModal')"><i class="fas fa-times"></i></button>
      </div>
      <div class="mbody" style="text-align:center;padding:22px 18px 14px;">
        <div id="shareConfirmMsg" style="font-size:.93rem;font-weight:700;color:var(--t1);line-height:1.6;margin-bottom:18px;"></div>
        <div style="display:flex;gap:10px;">
          <button class="btn btn-g" style="flex:1;" onclick="closeOv('shareConfirmModal')">تراجع</button>
          <button class="btn btn-p" style="flex:1;background:linear-gradient(135deg,var(--brand),var(--cou));" onclick="confirmSendCoupons()"><i class="fas fa-check"></i> نعم، أرسل</button>
        </div>
      </div>
    </div>
  </div>


  <!-- Bottom Mobile Navigation Bar -->
  <nav class="bottom-nav" id="bottomNavBar" style="display:none;">
    <div class="bottom-nav-item active" data-tab="home" onclick="switchTab('home')">
      <i class="fas fa-home"></i>
      <span>الرئيسية</span>
    </div>
    <div class="bottom-nav-item" data-tab="attendance" onclick="switchTab('attendance')">
      <i class="fas fa-calendar-check"></i>
      <span>الحضور</span>
    </div>
    <div class="bottom-nav-item center-fab" data-tab="send" onclick="switchTab('send')">
      <div class="fab-btn">
        <i class="fas fa-star"></i>
      </div>
      <span>إرسال</span>
    </div>
    <div class="bottom-nav-item" data-tab="tasks" onclick="switchTab('tasks')" style="position: relative;">
      <i class="fas fa-tasks"></i>
      <span>المهام</span>
      <span id="tasksBadge" style="display:none; position:absolute; top:6px; right:calc(50% - 18px); width:8px; height:8px; background:var(--err); border-radius:50%; border:1px solid #fff;"></span>
    </div>
    <div class="bottom-nav-item" data-tab="family" onclick="switchTab('family')">
      <i class="fas fa-comments"></i>
      <span>التواصل</span>
    </div>
  </nav>

  <!-- Account Switch -->
  <div class="overlay settings-overlay" id="switchOv">
    <div class="settings-sheet">
      <div class="ss-handle"></div>
      <div style="padding:18px 22px 8px;border-bottom:1px solid var(--bdr2);">
        <div style="font-size:1.05rem;font-weight:800;color:var(--t1);display:flex;align-items:center;gap:10px;">
          <div
            style="width:36px;height:36px;border-radius:var(--r-sm);background:var(--brand-bg);color:var(--brand);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fas fa-exchange-alt"></i></div>
          <span>تبديل الحساب</span>
        </div>
      </div>
      <div id="switchList" style="padding:10px 16px;"></div>
      <button class="ss-close-btn" onclick="closeOv('switchOv')">إغلاق</button>
    </div>
  </div>

  <!-- Trip Detail -->
  <div class="overlay settings-overlay" id="tripOv">
    <div class="settings-sheet" style="max-height:92vh;">
      <div class="ss-handle"></div>
      <div style="padding:14px 22px 10px;border-bottom:1px solid var(--bdr2);display:flex;align-items:center;gap:10px;">
        <div
          style="width:36px;height:36px;border-radius:var(--r-sm);background:var(--trip-bg);color:var(--trip-l);display:flex;align-items:center;justify-content:center;flex-shrink:0;flex-shrink:0;">
          <i class="fas fa-bus"></i></div>
        <div style="flex:1;min-width:0;">
          <div id="tripOvTitle"
            style="font-size:1rem;font-weight:800;color:var(--t1);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
          </div>
          <div id="tripOvSub" style="font-size:.72rem;color:var(--t4);margin-top:1px;"></div>
        </div>
      </div>
      <div id="tripOvBody" style="padding:16px 18px;"></div>
      <button class="ss-close-btn" onclick="closeOv('tripOv')">إغلاق</button>
    </div>
  </div>

  <!-- Uncle profile drawer -->
  <div class="overlay settings-overlay" id="uncleOv">
    <div class="settings-sheet" style="padding-bottom:max(20px,env(safe-area-inset-bottom));">
      <div id="uncleOvContent"></div>
      <button class="ss-close-btn" onclick="closeOv('uncleOv')" style="margin:12px 16px 0;">إغلاق</button>
    </div>
  </div>

  <!-- ══ FULL-SCREEN EXAM ══ -->
  <div id="examScreen"
    style="display:none;position:fixed;inset:0;z-index:800;background:var(--bg);overflow-y:auto;-webkit-overflow-scrolling:touch;">

    <!-- ① Start / confirmation view -->
    <div id="examStartView"
      style="display:none;flex-direction:column;align-items:center;justify-content:center;padding:20px 16px;min-height:100vh;">
      <div style="width:100%;max-width:460px;">
        <div class="exam-start-card">
          <div class="exam-start-hero">
            <div class="exam-start-icon"><i class="fas fa-pen-nib"></i></div>
            <div class="exam-start-title" id="startTitle"></div>
            <div class="exam-start-sub" id="startSub"></div>
          </div>
          <div id="startMeta" style="padding:16px 18px;display:flex;flex-direction:column;gap:8px;"></div>
          <div style="padding:0 18px 20px;display:flex;gap:10px;">
            <button class="btn btn-g" style="flex:1;" onclick="exitExamScreen()"><i class="fas fa-chevron-right"></i>
              رجوع</button>
            <button class="btn btn-p" id="examStartBtn" style="flex:2;padding:12px;font-size:.97rem;"
              onclick="beginExam()"><i class="fas fa-play-circle"></i> ابدأ الاختبار</button>
          </div>
        </div>
      </div>
    </div>

    <!-- ② Active exam view -->
    <div id="examActiveView" style="display:none;flex-direction:column;min-height:100vh;position:relative;">
      <div class="exam-hdr">
        <div class="exam-hdr-inner">
          <button class="exam-back-btn" onclick="confirmExitExam()"><i class="fas fa-chevron-right"
              style="font-size:.78rem;"></i></button>
          <div style="flex:1;min-width:0;">
            <div class="exam-hdr-title" id="examHeaderTitle"></div>
            <div class="exam-hdr-sub" id="examHeaderSub"></div>
          </div>
          <div class="exam-timer" id="examTimerBadge"></div>
        </div>
        <div class="exam-prog-track">
          <div class="exam-prog-fill" id="examProgBar"></div>
        </div>
      </div>
      <div class="exam-questions">
        <div id="examQList"></div>
      </div>
      <div class="exam-footer">
        <div class="exam-footer-inner">
          <div class="exam-ans-count">
            <i class="fas fa-check-circle" style="color:var(--ok);"></i>
            <strong id="examAnsDone">0</strong> / <span id="examTotalQ">0</span> سؤال
          </div>
          <div id="examQNav" style="display:flex;gap:4px;flex-wrap:wrap;flex:1;justify-content:center;padding:0 8px;">
          </div>
          <button class="btn btn-p" style="padding:11px 26px;font-size:.93rem;" onclick="submitExam()">
            <i class="fas fa-paper-plane"></i> تسليم
          </button>
        </div>
      </div>
    </div>

    <!-- ③ Result view -->
    <div id="examResultView"
      style="display:none;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;padding:20px 16px;">
      <div class="exam-result-wrap">
        <div id="examResultCard"></div>
        <button class="btn btn-p" style="width:100%;padding:14px;font-size:1rem;margin-top:4px;"
          onclick="exitExamScreen()">
          <i class="fas fa-home"></i> الرجوع للصفحة الرئيسية
        </button>
      </div>
    </div>

  </div>

  <!-- ══ INTERNAL CONFIRM MODALS (no browser dialogs) ══ -->
  <!-- Submit with unanswered questions -->
  <div class="overlay" id="submitConfirmModal" style="z-index:1200;">
    <div class="modal narrow" style="max-width:360px;">
      <div class="mhdr" style="background:linear-gradient(135deg,#d97706,#b45309);">
        <div
          style="width:36px;height:36px;border-radius:var(--r-sm);background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.2rem;color:#fff;">
          <i class="fas fa-exclamation-triangle"></i></div>
        <div class="mhdr-title" style="flex:1;">تأكيد التسليم</div>
        <button class="mclose" onclick="_closeSubmitConfirm()"><i class="fas fa-times"></i></button>
      </div>
      <div class="mbody" style="text-align:center;padding:22px 18px 14px;">
        <div id="scModalMsg"
          style="font-size:.93rem;font-weight:700;color:var(--t1);line-height:1.6;margin-bottom:18px;"></div>
        <div style="display:flex;gap:10px;">
          <button class="btn btn-g" style="flex:1;" onclick="_closeSubmitConfirm()"><i class="fas fa-arrow-right"></i>
            رجوع</button>
          <button class="btn btn-p" style="flex:1;background:linear-gradient(135deg,#d97706,#b45309);"
            onclick="_confirmSubmitExam()"><i class="fas fa-paper-plane"></i> تسليم</button>
        </div>
      </div>
    </div>
  </div>
  <!-- Exit exam with saved answers -->
  <div class="overlay" id="exitConfirmModal" style="z-index:1200;">
    <div class="modal narrow" style="max-width:360px;">
      <div class="mhdr" style="background:linear-gradient(135deg,var(--brand),var(--cou));">
        <div
          style="width:36px;height:36px;border-radius:var(--r-sm);background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.2rem;color:#fff;">
          <i class="fas fa-question-circle"></i></div>
        <div class="mhdr-title" style="flex:1;">الخروج من الاختبار</div>
        <button class="mclose" onclick="_closeExitConfirm()"><i class="fas fa-times"></i></button>
      </div>
      <div class="mbody" style="text-align:center;padding:22px 18px 14px;">
        <div style="font-size:.93rem;font-weight:700;color:var(--t1);line-height:1.6;margin-bottom:18px;">إجاباتك محفوظة
          تلقائياً. هل تريد الخروج؟</div>
        <div style="display:flex;gap:10px;">
          <button class="btn btn-g" style="flex:1;" onclick="_closeExitConfirm()"><i class="fas fa-times"></i>
            بقاء</button>
          <button class="btn btn-p" style="flex:1;" onclick="_confirmExit()"><i class="fas fa-sign-out-alt"></i>
            خروج</button>
        </div>
      </div>
    </div>
  </div>

  <div class="tc" id="tc"></div>

  <script>
    'use strict';
    // ── Config ────────────────────────────────────────────────────────
    const URL_ID = (() => { const m = location.search.match(/[?&]id=(\d+)/); return m ? parseInt(m[1]) : null; })();
    const _creds = localStorage.getItem('rememberMe') === 'true' && !!localStorage.getItem('savedUsername') && !!localStorage.getItem('savedPassword');
    const IS_PUBLIC = !!(URL_ID && !_creds);
    const API_URL = (() => {
      const segs = location.pathname.replace(/\/[^/]*$/, '').split('/').filter(Boolean);
      return segs.map(() => '../').join('') + 'api.php';
    })();
    const LETTERS = ['أ', 'ب', 'ج', 'د', 'هـ'];
    // attendance_day: DB 1=Mon…7=Sun → JS getDay() 0=Sun 1=Mon…6=Sat
    const DB_TO_JSDAY = { 1: 1, 2: 2, 3: 3, 4: 4, 5: 5, 6: 6, 7: 0 };
    const DAY_NAMES = { 0: 'الأحد', 1: 'الاثنين', 2: 'الثلاثاء', 3: 'الأربعاء', 4: 'الخميس', 5: 'الجمعة', 6: 'السبت' };

    // ── State ─────────────────────────────────────────────────────────
    let student = null, allAccounts = [], selAccId = null;
    let churchDay = 5;
    let customFields = null;
    let cropper = null, croppedBlob = null;
    let allTrips = [], allTasks = [];
    let curTask = null, taskAnswers = {}, examDone = false;
    let birthdayGreetingStudent = null;
    let maxFetchedTaskAnnId = 0;


    // ── Boot ──────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', async () => {
      if (IS_PUBLIC) {
        // Hide private tabs in public mode
        ['send', 'tasks', 'family'].forEach(tab => {
          const el = document.querySelector(`.bottom-nav-item[data-tab="${tab}"]`);
          if (el) el.style.display = 'none';
        });
      }
      if (_creds && URL_ID) {
        await initPrivate();
        const matchedAccount = allAccounts.find(a => Number(a.id) === Number(URL_ID));
        if (matchedAccount) {
          student = matchedAccount;
          localStorage.setItem('activeKidAccountId', String(matchedAccount.id));
          renderPrivate(student);
          switchTab('home');
          loadSiblings();
          document.getElementById('bottomNavBar').style.display = 'flex';
          syncPassOverlay();
          if (allAccounts.length > 1) {
            document.getElementById('switchBtnTop').style.display = 'flex';
          }
        } else {
          await openFriendProfile(URL_ID);
        }
      }
      else if (IS_PUBLIC && URL_ID) await initPublic(URL_ID);
      else if (_creds) await initPrivate();
      else if (URL_ID) await initPublic(URL_ID);
      else noProfile('يرجى تسجيل الدخول أو استخدام رابط المعرّف');
      setupOvClose();
      if (!IS_PUBLIC && _creds) {
        _initPushNotifications();
      }
    });

    async function api(p) {
      const fd = new FormData();
      for (const [k, v] of Object.entries(p)) if (v !== null && v !== undefined) fd.append(k, v);
      const r = await fetch(API_URL, { method: 'POST', body: fd, credentials: 'include' });
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.json();
    }

    // ── Public init ───────────────────────────────────────────────────
    async function initPublic(id) {
      showLoad('جارٍ التحميل…');
      try {
        const d = await api({ action: 'getStudentProfile', studentId: id });
        hideLoad();
        if (d.success && (d.student || d.user)) {
          student = norm(d.student || d.user);
          await loadChurchSettings();
          renderPublic(student); switchTab('home'); loadSiblings(); document.getElementById('bottomNavBar').style.display = 'flex'; switchTab('home'); loadSiblings(); document.getElementById('bottomNavBar').style.display = 'flex';
        } else noProfile('لم يُعثر على الملف الشخصي');
      } catch (e) { hideLoad(); noProfile('خطأ في الاتصال'); }
    }

    // ── Private init ──────────────────────────────────────────────────
    async function initPrivate() {
      showLoad('جارٍ تحميل ملفك…');
      try {
        const d = await api({ action: 'kidLogin', username: localStorage.getItem('savedUsername'), password: localStorage.getItem('savedPassword') });
        hideLoad();
        if (d.success && d.data && d.data.length > 0) {
          allAccounts = d.data.map(norm);
          // Restore last active account from localStorage
          const savedId = parseInt(localStorage.getItem('activeKidAccountId') || '0');
          const saved = savedId ? allAccounts.find(a => a.id === savedId) : null;
          student = saved || allAccounts[0];
          await loadChurchSettings();
          renderPrivate(student);
          switchTab('home');
          loadSiblings();
          document.getElementById('bottomNavBar').style.display = 'flex';
          syncPassOverlay();
          if (allAccounts.length > 1) {
            document.getElementById('switchBtnTop').style.display = 'flex';
          }
        } else noProfile('فشل في تحميل الملف الشخصي');
      } catch (e) { hideLoad(); noProfile('خطأ في الاتصال'); }
    }

    async function loadChurchSettings() {
      if (!student?.church_id) return;
      try {
        const d = await api({ action: 'getPublicChurchSettings', church_id: student.church_id });
        if (d.success) {
          churchDay = d.attendance_day || 5;
          customFields = d.custom_fields || null;
        }
      } catch (e) { }
    }

    // ── Normalise ─────────────────────────────────────────────────────
    function norm(s) {
      // The API returns `class` as the resolved class name (COALESCE'd in SQL).
      // '---' is the PHP fallback when all sources are null — treat it as empty.
      const rawCls = s.class || s['الفصل'] || '';
      const cls = (rawCls === '---' || rawCls === '--') ? '' : rawCls;
      return {
        id: s.id || 0,
        name: s.name || s['الاسم'] || '',
        class: cls,
        class_id: s.class_id || s._classId || 0,
        address: s.address || '',
        phone: s.phone || '',
        birthday: s.birthday || '',
        email: s.email || '',
        coupons: parseInt(s.coupons || 0),
        att_coupons: parseInt(s.attendance_coupons || 0),
        com_coupons: parseInt(s.commitment_coupons || 0),
        task_coupons: parseInt(s.task_coupons || 0),
        image_url: s.image_url || '',
        church_name: s.church_name || '',
        church_id: s.church_id || 0,
        church_type: s.church_type || localStorage.getItem('churchType') || 'kids',
        gender: s.gender || '',
        has_password: s.has_password === true || s.has_password === 1 || s.has_password === '1',
        custom_info: s.custom_info ? (typeof s.custom_info === 'string' ? JSON.parse(s.custom_info) : s.custom_info) : null,
        trip_points: (function () { try { if (!s.trip_points) return {}; return (typeof s.trip_points === 'string' ? JSON.parse(s.trip_points) : s.trip_points) || {} } catch (e) { return {} } })(),
        paper_exams: s.paper_exams || [],
      };
    }

    // ── Render public ─────────────────────────────────────────────────
    function renderPublic(s) {
      renderHero(s, false);
      renderInfo(s, true);
      document.getElementById('pubBanner').style.display = 'flex';
      document.getElementById('sbC').textContent = s.coupons;
      document.getElementById('statsBar').style.display = 'grid';
      // hide private stats cells
      ['sbP', 'sbA', 'sbR'].forEach(id => {
        document.getElementById(id).closest('.sb-cell').style.display = 'none';
      });
      loadTrips(false);
      loadAnn();
      loadAtt();
      document.getElementById('scPaperExams').style.display = 'block';
      renderPaperExams(s);
      showMain();
      syncViewMode();
    }

    // ── Render private ────────────────────────────────────────────────
    function renderPrivate(s) {
      renderHero(s, true);
      renderInfo(s, false);
      renderCouponHero(s);
      document.getElementById('couponHero').style.display = 'grid';
      document.getElementById('scAtt').style.display = 'block';
      document.getElementById('scTasks').style.display = 'block';
      document.getElementById('scTrips').style.display = 'block';
      document.getElementById('scPaperExams').style.display = 'block';
      document.getElementById('settingsTop').style.display = 'flex';
      document.getElementById('avatarEdit').classList.add('show');

      // edit form prefill
      document.getElementById('eN').value = s.name;
      document.getElementById('eA').value = s.address;
      document.getElementById('eP').value = s.phone;
      document.getElementById('eB').value = s.birthday;
      document.getElementById('statsBar').style.display = 'grid';
      document.getElementById('sbC').textContent = s.coupons;
      loadAtt();
      loadTasks();
      loadTrips(true);
      loadAnn();
      renderPaperExams(s);
      showMain();
      syncViewMode();
    }

    // ── Hero ──────────────────────────────────────────────────────────
    function renderHero(s, isPrivate) {
      document.getElementById('hero').style.display = 'flex';
      document.getElementById('heroName').textContent = s.name;
      updateBirthdayGreetingButton(s);
      document.getElementById('heroClassTxt').textContent = s.class || '—';
      if (s.church_name) {
        document.getElementById('churchName').textContent = s.church_name;
        document.getElementById('churchChip').style.display = 'inline-flex';
      }
      if (s.image_url) {
        document.getElementById('avatarInner').innerHTML = `<img src="${esc(s.image_url)}" alt="${esc(s.name)}" onerror="this.parentElement.innerHTML='<i class=\\'fas fa-user\\'></i>'">`;
      }
      if (!isPrivate) {
        document.getElementById('avatarEdit').style.display = 'none';
      }
      // Populate settings sheet
      const ssN = document.getElementById('ssName');
      const ssCls = document.getElementById('ssClass');
      const ssAv = document.getElementById('ssAvatar');
      if (ssN) ssN.textContent = s.name;
      if (ssCls) ssCls.textContent = s.class || '—';
      if (ssAv && s.image_url) ssAv.innerHTML = `<img src="${esc(s.image_url)}" alt="" onerror="this.innerHTML='<i class=\\'fas fa-user\\'></i>'">`;
      // Load class uncles — always try, API resolves class_id→name server-side
      if (s.church_id && (s.class || s.class_id)) {
        loadClassUncles(s.church_id, s.class || '', s.class_id || 0);
      }
    }

    // ── Coupon Hero ───────────────────────────────────────────────────
    function renderCouponHero(s) {
      // Get all coupon types
      const attCoupons = s.att_coupons || 0;
      const comCoupons = s.com_coupons || 0;
      const taskCoupons = s.task_coupons || 0;
      const totalCoupons = s.coupons || 0;

      document.getElementById('chTotal').textContent = totalCoupons;
      document.getElementById('sbC').textContent = totalCoupons;

      const rows = [
        { icon: 'fa-calendar-check', color: '#6ee7b7', label: 'حضور', val: attCoupons },
        { icon: 'fa-star', color: '#c4b5fd', label: 'التزام', val: comCoupons },
        { icon: 'fa-tasks', color: '#fde68a', label: 'مهام', val: taskCoupons }
      ];

      document.getElementById('chBreakdown').innerHTML = rows.map(r => `
    <div class="ch-row"><i class="fas ${r.icon}" style="color:${r.color};"></i>${r.val} ${r.label}</div>
  `).join('');
    }

    // ── Class uncles strip ────────────────────────────────────────────
    async function loadClassUncles(churchId, className, classId) {
      if (!churchId) return;
      try {
        const params = { action: 'getPublicClassUncles', church_id: churchId };
        if (className) params.class_name = className;
        if (classId) params.class_id = classId;
        if (!className && !classId) return;
        const d = await api(params);
        // If server resolved a class name from class_id and we had none, update display
        if (d.resolved_class_name && !className) {
          const heroTxt = document.getElementById('heroClassTxt');
          if (heroTxt && heroTxt.textContent === '—') heroTxt.textContent = d.resolved_class_name;
          const ssCls = document.getElementById('ssClass');
          if (ssCls && ssCls.textContent === '—') ssCls.textContent = d.resolved_class_name;
          if (student) student.class = d.resolved_class_name;
        }
        if (d.success && d.uncles && d.uncles.length) renderUncleStrip(d.uncles);
      } catch (e) { }
    }
    // Global uncles list (filled after load)
    let classUncles = [];

    function normalizeBirthdayDigits(value) {
      const ar = '٠١٢٣٤٥٦٧٨٩';
      const fa = '۰۱۲۳۴۵۶۷۸۹';
      return String(value || '')
        .replace(/[٠-٩]/g, d => ar.indexOf(d))
        .replace(/[۰-۹]/g, d => fa.indexOf(d));
    }

    function parseBirthdayDate(value) {
      const raw = normalizeBirthdayDigits(value).trim();
      if (!raw) return null;
      const short = raw.split(/[ T]/)[0];
      let m = short.match(/^(\d{4})[-/.](\d{1,2})[-/.](\d{1,2})$/);
      if (m) return { year: +m[1], month: +m[2], day: +m[3] };
      m = short.match(/^(\d{1,2})[-/.](\d{1,2})[-/.](\d{4})$/);
      if (m) return { year: +m[3], month: +m[2], day: +m[1] };
      m = short.match(/^(\d{1,2})[-/.](\d{1,2})$/);
      if (m) return { year: null, month: +m[2], day: +m[1] };
      const d = new Date(raw);
      if (!Number.isNaN(d.getTime())) return { year: d.getFullYear(), month: d.getMonth() + 1, day: d.getDate() };
      return null;
    }

    function getBirthdayAge(s) {
      const b = parseBirthdayDate(s && s.birthday);
      if (!b || !b.year) return null;
      const now = new Date();
      let age = now.getFullYear() - b.year;
      if (now.getMonth() + 1 < b.month || (now.getMonth() + 1 === b.month && now.getDate() < b.day)) age--;
      return age >= 0 ? age : null;
    }

    function isBirthdayToday(s) {
      const b = parseBirthdayDate(s && s.birthday);
      if (!b) return false;
      const now = new Date();
      return b.month === now.getMonth() + 1 && b.day === now.getDate();
    }

    function updateBirthdayGreetingButton(s) {
      const btn = document.getElementById('birthdayGreetingBtn');
      if (!btn) return;
      const show = !!(s && isBirthdayToday(s));
      birthdayGreetingStudent = show ? s : null;
      btn.classList.toggle('show', show);
    }

    function fillRoundRect(ctx, x, y, w, h, r) {
      ctx.beginPath();
      ctx.moveTo(x + r, y);
      ctx.arcTo(x + w, y, x + w, y + h, r);
      ctx.arcTo(x + w, y + h, x, y + h, r);
      ctx.arcTo(x, y + h, x, y, r);
      ctx.arcTo(x, y, x + w, y, r);
      ctx.closePath();
      ctx.fill();
    }

    function loadCanvasImage(src) {
      return new Promise((resolve, reject) => {
        if (!src) { reject(new Error('missing image')); return; }
        const img = new Image();
        img.crossOrigin = 'anonymous';
        img.onload = () => resolve(img);
        img.onerror = reject;
        img.src = src;
      });
    }

    function drawRoundedImage(ctx, img, x, y, w, h, r) {
      ctx.save();
      ctx.beginPath();
      ctx.moveTo(x + r, y);
      ctx.arcTo(x + w, y, x + w, y + h, r);
      ctx.arcTo(x + w, y + h, x, y + h, r);
      ctx.arcTo(x, y + h, x, y, r);
      ctx.arcTo(x, y, x + w, y, r);
      ctx.closePath();
      ctx.clip();
      const scale = Math.max(w / img.width, h / img.height);
      const sw = w / scale, sh = h / scale;
      ctx.drawImage(img, (img.width - sw) / 2, (img.height - sh) / 2, sw, sh, x, y, w, h);
      ctx.restore();
    }

    function drawBirthdayConfetti(ctx, w, h) {
      const colors = ['#ef4444', '#f59e0b', '#10b981', '#2563eb', '#ec4899', '#7c3aed', '#14b8a6'];
      for (let i = 0; i < 95; i++) {
        const x = (Math.sin(i * 17.31) * .5 + .5) * w;
        const y = 70 + (Math.cos(i * 9.71) * .5 + .5) * (h - 180);
        const size = 8 + (i % 6) * 4;
        ctx.save();
        ctx.translate(x, y);
        ctx.rotate((i * 29 * Math.PI) / 180);
        ctx.globalAlpha = .72;
        ctx.fillStyle = colors[i % colors.length];
        if (i % 3 === 0) {
          ctx.beginPath(); ctx.arc(0, 0, size / 2, 0, Math.PI * 2); ctx.fill();
        } else if (i % 3 === 1) {
          ctx.fillRect(-size / 2, -size / 4, size, size / 2);
        } else {
          ctx.beginPath(); ctx.moveTo(0, -size / 2); ctx.lineTo(size / 2, size / 2); ctx.lineTo(-size / 2, size / 2); ctx.closePath(); ctx.fill();
        }
        ctx.restore();
      }
      for (let i = 0; i < 8; i++) {
        ctx.save();
        ctx.strokeStyle = colors[(i + 2) % colors.length];
        ctx.globalAlpha = .45;
        ctx.lineWidth = 8;
        ctx.beginPath();
        const startX = 70 + i * 135;
        ctx.moveTo(startX, 120);
        for (let t = 0; t < 6; t++) ctx.quadraticCurveTo(startX + 30, 170 + t * 35, startX, 195 + t * 35);
        ctx.stroke();
        ctx.restore();
      }
    }

    async function drawBirthdayGreeting() {
      const s = birthdayGreetingStudent || student;
      const canvas = document.getElementById('birthdayGreetingCanvas');
      if (!s || !canvas) return;
      const ctx = canvas.getContext('2d');
      const w = canvas.width, h = canvas.height;
      ctx.clearRect(0, 0, w, h);

      const bg = ctx.createLinearGradient(0, 0, w, h);
      bg.addColorStop(0, '#fff7ad');
      bg.addColorStop(.35, '#ffd6e7');
      bg.addColorStop(.7, '#bde7ff');
      bg.addColorStop(1, '#d8ffd6');
      ctx.fillStyle = bg;
      ctx.fillRect(0, 0, w, h);
      drawBirthdayConfetti(ctx, w, h);

      ctx.save();
      ctx.fillStyle = 'rgba(255,255,255,.86)';
      ctx.shadowColor = 'rgba(30,41,59,.18)';
      ctx.shadowBlur = 35;
      fillRoundRect(ctx, 78, 150, w - 156, h - 235, 54);
      ctx.restore();

      try {
        const logo = await loadCanvasImage('/logo.png');
        drawRoundedImage(ctx, logo, w / 2 - 58, 185, 116, 116, 28);
      } catch (e) {
        ctx.fillStyle = '#ffffff';
        ctx.beginPath(); ctx.arc(w / 2, 243, 58, 0, Math.PI * 2); ctx.fill();
      }

      ctx.textAlign = 'center';
      ctx.direction = 'rtl';
      ctx.fillStyle = '#0f172a';
      ctx.font = '800 36px Cairo, Tahoma, Arial';
      ctx.fillText(s.church_name || 'Sunday School', w / 2, 340);
      ctx.fillStyle = '#db2777';
      ctx.font = '900 78px Cairo, Tahoma, Arial';
      ctx.fillText('عيد ميلاد سعيد', w / 2, 455);
      ctx.fillStyle = '#7c3aed';
      ctx.font = '800 34px Cairo, Tahoma, Arial';
      ctx.fillText('ربنا يفرح قلبك ويبارك سنينك', w / 2, 515);

      const px = w / 2 - 185, py = 575, ps = 370;
      ctx.save();
      ctx.shadowColor = 'rgba(15,23,42,.25)';
      ctx.shadowBlur = 35;
      ctx.fillStyle = '#fff';
      ctx.beginPath(); ctx.arc(w / 2, py + ps / 2, ps / 2 + 18, 0, Math.PI * 2); ctx.fill();
      ctx.restore();

      try {
        const photo = await loadCanvasImage(s.image_url);
        ctx.save();
        ctx.beginPath(); ctx.arc(w / 2, py + ps / 2, ps / 2, 0, Math.PI * 2); ctx.clip();
        const scale = Math.max(ps / photo.width, ps / photo.height);
        const sw = ps / scale, sh = ps / scale;
        ctx.drawImage(photo, (photo.width - sw) / 2, (photo.height - sh) / 2, sw, sh, px, py, ps, ps);
        ctx.restore();
      } catch (e) {
        const av = ctx.createLinearGradient(px, py, px + ps, py + ps);
        av.addColorStop(0, '#2563eb');
        av.addColorStop(1, '#ec4899');
        ctx.fillStyle = av;
        ctx.beginPath(); ctx.arc(w / 2, py + ps / 2, ps / 2, 0, Math.PI * 2); ctx.fill();
        ctx.fillStyle = '#fff';
        ctx.font = '900 135px Cairo, Tahoma, Arial';
        ctx.fillText((s.name || '?').trim().charAt(0), w / 2, py + ps / 2 + 48);
      }

      ctx.fillStyle = '#111827';
      ctx.font = '900 70px Cairo, Tahoma, Arial';
      ctx.fillText(s.name || '', w / 2, 1030);

      const age = getBirthdayAge(s);
      if (age !== null) {
        ctx.fillStyle = '#f97316';
        fillRoundRect(ctx, w / 2 - 210, 1075, 420, 86, 43);
        ctx.fillStyle = '#fff';
        ctx.font = '900 40px Cairo, Tahoma, Arial';
        ctx.fillText(`تم ${age} سنة`, w / 2, 1132);
      }

      ctx.fillStyle = '#475569';
      ctx.font = '700 31px Cairo, Tahoma, Arial';
      ctx.fillText('كل سنة وأنت طيب', w / 2, 1218);
    }

    function openBirthdayGreeting() {
      const s = birthdayGreetingStudent || student;
      if (!s || !isBirthdayToday(s)) {
        toast('الصورة تظهر في يوم عيد الميلاد فقط', 'info');
        return;
      }
      birthdayGreetingStudent = s;
      openOv('bdayGreetingOv');
      setTimeout(drawBirthdayGreeting, 80);
    }

    function birthdayCanvasToBlob() {
      const canvas = document.getElementById('birthdayGreetingCanvas');
      return new Promise((resolve, reject) => {
        try {
          canvas.toBlob(blob => blob ? resolve(blob) : reject(new Error('تعذر تجهيز الصورة')), 'image/png', 1);
        } catch (e) { reject(e); }
      });
    }

    async function saveBirthdayGreeting() {
      try {
        await drawBirthdayGreeting();
        const blob = await birthdayCanvasToBlob();
        const s = birthdayGreetingStudent || student || {};
        const safeName = String(s.name || 'birthday').replace(/[^\p{L}\p{N}_-]+/gu, '_');
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `birthday_${safeName}.png`;
        document.body.appendChild(a);
        a.click();
        setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 500);
      } catch (e) {
        toast('تعذر حفظ الصورة: ' + e.message, 'err');
      }
    }

    async function shareBirthdayGreeting() {
      try {
        await drawBirthdayGreeting();
        const blob = await birthdayCanvasToBlob();
        const s = birthdayGreetingStudent || student || {};
        const file = new File([blob], 'birthday-greeting.png', { type: 'image/png' });
        if (navigator.canShare && navigator.canShare({ files: [file] })) {
          await navigator.share({ files: [file], title: 'صورة عيد الميلاد', text: `كل سنة و${s.name || ''} طيب` });
        } else {
          await saveBirthdayGreeting();
        }
      } catch (e) {
        toast('تعذر مشاركة الصورة: ' + e.message, 'err');
      }
    }

    function renderUncleStrip(uncles) {
      classUncles = uncles;
      const strip = document.getElementById('uncleStrip');
      if (!strip || !uncles.length) return;
      const show = uncles.slice(0, 4);
      const extra = uncles.length - show.length;
      strip.innerHTML = `
    <div class="uncle-avatars">
      ${show.map(u => `<div class="ua" title="${esc(u.name)}">${u.image_url ? `<img src="${esc(u.image_url)}" alt="${esc(u.name)}">` : u.name.charAt(0)}</div>`).join('')}
      ${extra > 0 ? `<div class="ua-more">+${extra}</div>` : ''}
    </div>
    <span class="uncle-strip-label">${uncles.length === 1 ? esc(uncles[0].name) : ''}</span>`;
      strip.style.display = 'inline-flex';
      strip.onclick = () => { switchTab('family'); };
      renderUncleCards(uncles);
    }

    function renderUncleCards(uncles) {
      const sec = document.getElementById('scUncles');
      const grid = document.getElementById('uncleCardGrid');
      const sub = document.getElementById('unclesSub');
      if (!sec || !grid || !uncles.length) return;
      sub.textContent = uncles.length + ' مدرس';
      const roleLbl = { admin: 'مدرس', developer: 'مطوّر', uncle: 'مدرس' };
      grid.innerHTML = uncles.map(u => `
    <div class="uncle-card" onclick="openUncleDrawer(${u.id})">
      <div class="uncle-card-av">
        ${u.image_url ? `<img src="${esc(u.image_url)}" alt="${esc(u.name)}">` : u.name.charAt(0)}
      </div>
      <div class="uncle-card-name">${esc(u.name)}</div>
      <div class="uncle-card-role">${roleLbl[u.role] || u.role}</div>
    </div>`).join('');
      sec.style.display = 'block';
    }

    function openUncleDrawer(uid) {
      const u = classUncles.find(x => x.id === uid);
      if (!u) return;
      const roleLbl = { admin: 'مدرس', developer: 'مطوّر', uncle: 'مدرس' };
      const hasPhone = u.phone && u.phone.trim();
      // Format phone for WhatsApp (strip leading 0, add country code)
      const waPhone = hasPhone ? '20' + u.phone.trim().replace(/^0/, '') : '';
      const waMsg = encodeURIComponent('مرحباً أنا ' + ((student && student.name) || '') + '، انا في فصلك بمدارس الأحد');
      const actionBtns = hasPhone ? `
    <a href="tel:${esc(u.phone.trim())}" class="uncle-action-btn call" style="text-decoration:none;">
      <div class="uncle-action-ico" style="background:#d1fae5;color:#059669;"><i class="fas fa-phone-alt"></i></div>
      <div><div>${esc(u.phone.trim())}</div><div style="font-size:.7rem;color:var(--t4);font-weight:500;">اضغط للاتصال</div></div>
    </a>
    <a href="https://wa.me/${waPhone}?text=${waMsg}" target="_blank" class="uncle-action-btn wa" style="text-decoration:none;">
      <div class="uncle-action-ico" style="background:#dcfce7;color:#16a34a;"><i class="fab fa-whatsapp"></i></div>
      <div><div>واتساب</div><div style="font-size:.7rem;color:var(--t4);font-weight:500;">إرسال رسالة</div></div>
    </a>`
        : `<div style="padding:14px 18px;background:var(--s2);border-radius:var(--r-md);text-align:center;color:var(--t4);font-size:.82rem;font-weight:600;"><i class="fas fa-phone-slash"></i> لم يُضَف رقم الهاتف بعد</div>`;
      document.getElementById('uncleOvContent').innerHTML = `
    <div class="uncle-drawer-hero">
      <div class="uncle-drawer-av">
        ${u.image_url ? `<img src="${esc(u.image_url)}" alt="${esc(u.name)}">` : u.name.charAt(0)}
      </div>
      <div>
        <div class="uncle-drawer-name">${esc(u.name)}</div>
        <div class="uncle-drawer-role">${roleLbl[u.role] || u.role}</div>
      </div>
    </div>
    <div style="padding:14px 18px;display:flex;flex-direction:column;gap:10px;">
      ${actionBtns}
    </div>`;
      openOv('uncleOv');
    }

    // ── Info grid ─────────────────────────────────────────────────────
    // ── Friend profile ────────────────────────────────────────────────
    let _myStudent = null; // snapshot of logged-in student while viewing a friend

    function isViewingOther() {
      return !!(IS_PUBLIC || _myStudent);
    }

    function syncViewMode() {
      if (isViewingOther()) {
        document.body.classList.add('view-other-mode');
      } else {
        document.body.classList.remove('view-other-mode');
      }
    }

    async function openFriendProfile(friendId) {
      showLoad('جارٍ تحميل الملف…');
      try {
        const [profD, attD] = await Promise.all([
          api({ action: 'getStudentProfile', studentId: friendId }),
          api({ action: 'getStudentAttendance', studentId: friendId })
        ]);
        hideLoad();
        if (!profD.success || !(profD.student || profD.user)) return; // friend not found — stay on own profile
        const f = norm(profD.student || profD.user);
        f._friendAtt = attD.success ? (attD.attendance || []) : [];
        renderFriend(f);
      } catch (e) { hideLoad(); }
    }

    function renderFriend(f) {
      _myStudent = student; // save own profile

      // ── Avatar — friend's own image, no edit button ──────────────
      const avInner = document.getElementById('avatarInner');
      avInner.innerHTML = f.image_url
        ? `<img src="${esc(f.image_url)}" alt="${esc(f.name)}" onerror="this.parentElement.innerHTML='<i class=\\'fas fa-user\\'></i>'">`
        : '<i class="fas fa-user"></i>';
      document.getElementById('avatarEdit').style.display = 'none';
      document.getElementById('avatarEdit').classList.remove('show');

      // ── Name, class ───────────────────────────────────────────────
      document.getElementById('heroName').textContent = f.name;
      updateBirthdayGreetingButton(f);
      document.getElementById('heroClassTxt').textContent = f.class || '—';
      const chip = document.getElementById('churchChip');
      if (chip && f.church_name) {
        document.getElementById('churchName').textContent = f.church_name;
        chip.style.display = 'inline-flex';
      }
      document.getElementById('hero').style.display = 'flex';

      // ── Uncle strip for friend's class ───────────────────────────
      const strip = document.getElementById('uncleStrip');
      strip.style.display = 'none';
      strip.innerHTML = '';
      classUncles = [];
      if (f.church_id && (f.class || f.class_id)) {
        loadClassUncles(f.church_id, f.class || '', f.class_id || 0);
      }

      // ── Info (public mode — class + church only) ──────────────────
      renderInfo(f, true);

      // ── Coupon hero ────────────────────────────────────────────────
      renderCouponHero(f);
      document.getElementById('couponHero').style.display = 'grid';

      // ── Stats bar — coupons only ──────────────────────────────────
      document.getElementById('statsBar').style.display = 'grid';
      document.getElementById('sbC').textContent = f.coupons;
      ['sbP', 'sbA', 'sbR'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.closest('.sb-cell').style.display = 'none';
      });

      // ── Attendance calendar ───────────────────────────────────────
      renderCal(f._friendAtt || []);
      document.getElementById('scAtt').style.display = 'block';
      document.getElementById('attViewAllBtn').style.display = 'none';


      // ── Announcements for friend's church/class ───────────────────
      const savedStudent = student;
      student = f;
      loadAnn();
      student = savedStudent;

      // ── Hide private sections ─────────────────────────────────────
      document.getElementById('scTasks').style.display = 'none';
      document.getElementById('scTrips').style.display = 'none';
      document.getElementById('pubBanner').style.display = 'none';
      document.getElementById('settingsTop').style.display = 'none';
      const scSendCoupons = document.getElementById('scSendCoupons');
      if (scSendCoupons) scSendCoupons.style.display = 'none';
      const homeSearchBar = document.getElementById('homeSearchBar');
      if (homeSearchBar) homeSearchBar.style.display = 'none';
      // Hide bottom nav while viewing friend — only the banner "return" button is available
      document.getElementById('bottomNavBar').style.display = 'none';

      // ── Friend banner ─────────────────────────────────────────────
      document.getElementById('friendBannerName').textContent = 'ملف ' + f.name;
      document.getElementById('friendBanner').style.display = 'flex';

      showMain();
      window.scrollTo({ top: 0, behavior: 'smooth' });
      syncViewMode();
    }

    function returnToMyProfile() {
      if (!_myStudent) return;
      const s = _myStudent;
      _myStudent = null;

      // Clean the ?id= from the URL without reloading
      const clean = location.pathname;
      window.history.replaceState({}, '', clean);

      // Restore student global first
      student = s;

      // Restore own avatar
      const avInner = document.getElementById('avatarInner');
      if (s.image_url) {
        avInner.innerHTML = `<img src="${esc(s.image_url)}" alt="${esc(s.name)}" onerror="this.parentElement.innerHTML='<i class=\\'fas fa-user\\'></i>'">`;
      } else {
        avInner.innerHTML = '<i class="fas fa-user"></i>';
      }
      document.getElementById('avatarEdit').style.display = '';
      document.getElementById('avatarEdit').classList.add('show');

      // Restore hero text
      document.getElementById('heroName').textContent = s.name;
      updateBirthdayGreetingButton(s);
      document.getElementById('heroClassTxt').textContent = s.class || '—';
      const chip = document.getElementById('churchChip');
      if (chip) { document.getElementById('churchName').textContent = s.church_name || ''; chip.style.display = s.church_name ? 'inline-flex' : 'none'; }

      // Restore uncle strip for own class
      const strip = document.getElementById('uncleStrip');
      strip.style.display = 'none'; strip.innerHTML = ''; classUncles = [];
      if (s.church_id && (s.class || s.class_id)) loadClassUncles(s.church_id, s.class || '', s.class_id || 0);

      // Restore info
      renderInfo(s, false);

      // Restore coupons
      renderCouponHero(s);
      document.getElementById('couponHero').style.display = 'grid';

      // Restore stats bar — show all cells
      document.getElementById('statsBar').style.display = 'grid';
      document.getElementById('sbC').textContent = s.coupons;
      ['sbP', 'sbA', 'sbR'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.closest('.sb-cell').style.display = '';
      });

      // Hide friend banner
      document.getElementById('friendBanner').style.display = 'none';
      document.getElementById('pubBanner').style.display = 'none';
      document.getElementById('settingsTop').style.display = 'flex';

      // Restore nav bar and switch to home tab cleanly
      document.getElementById('bottomNavBar').style.display = 'flex';

      // Reload own data
      loadAtt();
      loadTasks();
      loadTrips(true);
      loadAnn();
      loadSiblings();
      syncPassOverlay();

      // Switch to home tab — this handles all section show/hide
      switchTab('home');
      window.scrollTo({ top: 0, behavior: 'smooth' });
      syncViewMode();
    }



    function renderInfo(s, isPublic) {
      const el = document.getElementById('infoGrid');
      const pills = [];
      if (s.class) pills.push({ bg: '#e0e7ff', c: '#4338ca', icon: 'fas fa-graduation-cap', lbl: 'الفصل', val: s.class });
      if (!isPublic) {
        if (s.phone) pills.push({ bg: '#d1fae5', c: '#065f46', icon: 'fas fa-phone', lbl: 'التليفون', val: s.phone });
        if (s.address) pills.push({ bg: '#ffedd5', c: '#9a3412', icon: 'fas fa-map-marker-alt', lbl: 'العنوان', val: s.address });
        if (s.birthday) pills.push({ bg: '#fce7f3', c: '#9d174d', icon: 'fas fa-birthday-cake', lbl: 'عيد الميلاد', val: s.birthday });
        if (s.email) pills.push({ bg: '#dbeafe', c: '#1e40af', icon: 'fas fa-envelope', lbl: 'البريد', val: s.email });
        // Custom info from student record
        if (s.custom_info && typeof s.custom_info === 'object') {
          // Match keys against church custom_field definitions for labels/icons
          const defs = customFields || [];
          for (const [key, val] of Object.entries(s.custom_info)) {
            if (!val || key === 'sibling_group') continue;
            const def = defs.find(d => d.key === key);
            const label = def?.name || key;
            const icon = def?.icon || 'fas fa-tag';
            pills.push({ bg: '#f0fdf4', c: '#166534', icon: `fas ${icon.replace('fas ', '').replace('fa-', '') ? 'fa-' + icon.replace('fas ', '').replace('fa-', '') : icon}`, lbl: label, val: String(val) });
          }
        }
      }
      if (s.church_name && isPublic) pills.push({ bg: '#ede9fe', c: '#4c1d95', icon: 'fas fa-church', lbl: 'الكنيسة', val: s.church_name });

      if (!pills.length) {
        document.getElementById('scInfo').style.display = 'none';
        return;
      }
      el.innerHTML = pills.map(p => `
    <div class="ip">
      <div class="ip-ico" style="background:${p.bg};color:${p.c};"><i class="${p.icon}"></i></div>
      <div><div class="ip-lbl">${esc(p.lbl)}</div><div class="ip-val">${esc(p.val)}</div></div>
    </div>`).join('');
    }

    // ── Attendance ────────────────────────────────────────────────────
    async function loadAtt() {
      try {
        const d = await api({ action: 'getStudentAttendance', studentId: student.id });
        renderCal(d.attendance || []);
      } catch (e) { renderCal([]); }
    }

    function renderCal(records) {
      const jsDay = DB_TO_JSDAY[churchDay] ?? 5;
      const dayName = DAY_NAMES[jsDay] || 'الجمعة';
      document.getElementById('attSub').textContent = `آخر 12 ${dayName}`;

      const todayLocal = new Date(); todayLocal.setHours(12, 0, 0, 0);
      const days = [];
      for (let i = 0; days.length < 12; i++) {
        const d = new Date(todayLocal); d.setDate(d.getDate() - i);
        if (d.getDay() === jsDay) {
          const y = d.getFullYear(), m = String(d.getMonth() + 1).padStart(2, '0'), dd = String(d.getDate()).padStart(2, '0');
          const str = `${y}-${m}-${dd}`;
          days.push({ date: d, str, num: d.getDate(), mo: d.toLocaleDateString('ar-EG', { month: 'short' }) });
        }
      }
      let pr = 0, ab = 0;
      document.getElementById('calGrid').innerHTML = days.map(day => {
        const rec = records.find(r => r.attendance_date === day.str);
        const diff = Math.round((todayLocal - day.date) / (86400000));
        const daysAgo = diff === 0 ? 'اليوم' : diff === 7 ? 'أسبوع' : diff < 14 ? `${diff} يوم` : Math.round(diff / 7) + ' أسبوع';
        let cls = '', st = '—';
        if (rec) {
          if (rec.status === 'present') { cls = 'present'; st = 'حضر'; pr++; }
          else if (rec.status === 'absent') { cls = 'absent'; st = 'غاب'; ab++; }
        }
        return `<div class="cal-day ${cls}">
      <div class="cd-num">${day.num}</div>
      <div class="cd-mo">${day.mo}</div>
      <div class="cd-st">${st}</div>
      <div class="cd-days">${daysAgo}</div>
    </div>`;
      }).join('');
      const total = pr + ab; const rate = total > 0 ? Math.round(pr / total * 100) : 0;
      document.getElementById('ap').textContent = pr;
      document.getElementById('aa').textContent = ab;
      document.getElementById('ar').textContent = rate + '%';
      document.getElementById('sbP').textContent = pr;
      document.getElementById('sbA').textContent = ab;
      document.getElementById('sbR').textContent = rate + '%';
      document.getElementById('attBadge').textContent = rate + '%';
      if (!pr && !ab) document.getElementById('scAtt').style.display = 'none';
    }

    // ── Attendance History Sheet ──────────────────────────────────────
    let _attAllRecords = [];
    let _attFilter = 'all';

    function timeAgoAr(dateStr) {
      const [yr, mo, dy] = dateStr.split('-').map(Number);
      const diff = Math.floor((Date.now() - new Date(yr, mo - 1, dy, 12).getTime()) / 86400000);
      if (diff <= 0) return 'اليوم';
      if (diff === 1) return 'أمس';
      if (diff < 7) return `منذ ${diff} أيام`;
      const w = Math.floor(diff / 7);
      if (w === 1) return 'منذ أسبوع';
      if (w < 5) return `منذ ${w} أسابيع`;
      const m = Math.floor(diff / 30);
      if (m === 1) return 'منذ شهر';
      if (m < 12) return `منذ ${m} شهور`;
      const y = Math.floor(diff / 365);
      return y === 1 ? 'منذ سنة' : `منذ ${y} سنوات`;
    }

    async function openAttHistory() {
      openOv('attHistOv');
      _attFilter = 'all';
      document.querySelectorAll('#attHistOv .fchip').forEach(c => c.classList.remove('active'));
      document.querySelector('#attHistOv .fchip[data-filter="all"]').classList.add('active');
      document.getElementById('attHistSearch').value = '';
      document.getElementById('attHistSort').value = 'newest';
      document.getElementById('attHistList').innerHTML =
        '<div style="text-align:center;padding:28px;color:var(--t4);font-size:.88rem;"><i class="fas fa-spinner fa-spin" style="display:block;font-size:1.6rem;margin-bottom:8px;opacity:.4;"></i>جارٍ التحميل…</div>';

      try {
        const d = await api({ action: 'getStudentAttendance', studentId: student.id });
        const dbRecords = (d.attendance || []);

        // Build ALL church-day slots for the past 24 weeks
        // Use LOCAL date arithmetic to avoid UTC timezone shift bugs
        const jsDay = DB_TO_JSDAY[churchDay] ?? 5;
        const todayLocal = new Date();
        todayLocal.setHours(12, 0, 0, 0); // noon — safe against DST/UTC shifts
        const allSlots = [];
        let cur = new Date(todayLocal);
        // Walk back to most recent church day (inclusive of today)
        while (cur.getDay() !== jsDay) cur.setDate(cur.getDate() - 1);
        for (let i = 0; i < 24; i++) {
          // Format as YYYY-MM-DD using local parts — never toISOString (UTC)
          const y = cur.getFullYear();
          const m = String(cur.getMonth() + 1).padStart(2, '0');
          const dd = String(cur.getDate()).padStart(2, '0');
          const str = `${y}-${m}-${dd}`;
          const rec = dbRecords.find(r => r.attendance_date === str);
          allSlots.push({ str, status: rec ? rec.status : 'unrecorded' });
          cur.setDate(cur.getDate() - 7);
        }
        _attAllRecords = allSlots;

        const pr = allSlots.filter(r => r.status === 'present').length;
        const ab = allSlots.filter(r => r.status === 'absent').length;
        const ur = allSlots.filter(r => r.status === 'unrecorded').length;
        const total = pr + ab;
        document.getElementById('ahsPresent').textContent = pr;
        document.getElementById('ahsAbsent').textContent = ab;
        document.getElementById('ahsRate').textContent = total > 0 ? Math.round(pr / total * 100) + '%' : '—';
        document.getElementById('attHistSubtitle').textContent =
          `${allSlots.length} أسبوع · ${pr} حضور · ${ab} غياب · ${ur} غير مسجّل`;

        renderAttHist();
      } catch (e) {
        document.getElementById('attHistList').innerHTML =
          '<div style="text-align:center;padding:28px;color:var(--err);font-size:.88rem;"><i class="fas fa-exclamation-circle" style="display:block;font-size:1.6rem;margin-bottom:8px;"></i>فشل التحميل</div>';
      }
    }

    function setAttFilter(el, filter) {
      _attFilter = filter;
      document.querySelectorAll('#attHistOv .fchip').forEach(c => c.classList.remove('active'));
      el.classList.add('active');
      renderAttHist();
    }

    function renderAttHist() {
      const search = (document.getElementById('attHistSearch').value || '').trim().toLowerCase();
      const sort = document.getElementById('attHistSort').value;
      const list = document.getElementById('attHistList');
      const WDAYS = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];

      let items = _attAllRecords.slice();
      if (sort === 'oldest') items = items.slice().reverse();
      if (_attFilter !== 'all') items = items.filter(r => r.status === _attFilter);
      if (search) {
        items = items.filter(r => {
          const dateAr = new Date(r.str || r.attendance_date).toLocaleDateString('ar-EG', { day: 'numeric', month: 'long', year: 'numeric' });
          return (r.str || r.attendance_date).includes(search) || dateAr.includes(search);
        });
      }

      document.getElementById('attHistCount').textContent = items.length ? `${items.length} نتيجة` : '';

      if (!items.length) {
        list.innerHTML = '<div style="text-align:center;padding:28px;color:var(--t4);font-size:.88rem;font-weight:600;"><i class="fas fa-search" style="display:block;font-size:1.5rem;margin-bottom:8px;opacity:.35;"></i>لا توجد نتائج</div>';
        return;
      }

      list.innerHTML = items.map(r => {
        const dateStr = r.str || r.attendance_date;
        // Parse as local noon to avoid UTC-shift day-off-by-one
        const [yr, mo, dy] = dateStr.split('-').map(Number);
        const d = new Date(yr, mo - 1, dy, 12);
        const dateAr = d.toLocaleDateString('ar-EG', { day: 'numeric', month: 'long', year: 'numeric' });
        const wday = WDAYS[d.getDay()];
        const ago = timeAgoAr(dateStr);
        const isP = r.status === 'present';
        const isA = r.status === 'absent';
        const isU = r.status === 'unrecorded';

        const rowBg = isP ? 'background:var(--ok-bg);border-color:#6ee7b7;'
          : isA ? 'background:var(--err-bg);border-color:#fca5a5;'
            : 'background:var(--s2);border-color:var(--bdr2);opacity:.82;';
        const dotClr = isP ? 'var(--ok)' : isA ? 'var(--err)' : 'var(--t5)';
        const badgeBg = isP ? 'background:rgba(5,150,105,.12);color:var(--ok);'
          : isA ? 'background:rgba(220,38,38,.12);color:var(--err);'
            : 'background:var(--s2);color:var(--t4);border:1px solid var(--bdr);';
        const label = isP ? 'حضر ✓' : isA ? 'غاب ✗' : '— غير مسجّل';

        return `<div style="display:flex;align-items:center;gap:10px;padding:9px 12px;border-radius:var(--r-md);border:1.5px solid;${rowBg}transition:var(--fast);">
      <div style="width:8px;height:8px;border-radius:50%;flex-shrink:0;background:${dotClr};margin-top:1px;"></div>
      <div style="flex:1;min-width:0;">
        <div style="font-size:.88rem;font-weight:800;color:var(--t1);">${dateAr}</div>
        <div style="font-size:.64rem;color:var(--t4);font-weight:500;margin-top:1px;">${wday} · ${ago}</div>
      </div>
      <span style="padding:3px 9px;border-radius:var(--r-full);font-size:.68rem;font-weight:700;flex-shrink:0;white-space:nowrap;${badgeBg}">${label}</span>
      <button onclick="openRowReport('${dateStr}','${r.status}')"
        title="بلّغ عن خطأ"
        style="width:28px;height:28px;border-radius:var(--r-sm);border:1.5px solid #fde68a;background:#fef3c7;color:#d97706;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:.72rem;flex-shrink:0;transition:var(--fast);"
        onmouseover="this.style.background='#fde68a'" onmouseout="this.style.background='#fef3c7'">
        <i class="fas fa-flag"></i>
      </button>
    </div>`;
      }).join('');
    }

    // ── Report Attendance Error ───────────────────────────────────────
    let _reportDateStr = '';
    let _reportShould = '';

    function openRowReport(dateStr, currentStatus) {
      _reportDateStr = dateStr;
      _reportShould = '';

      // Show the date in Arabic in the sheet header
      let dateAr = dateStr;
      try {
        const [yr, mo, dy] = dateStr.split('-').map(Number);
        dateAr = new Date(yr, mo - 1, dy, 12).toLocaleDateString('ar-EG', { weekday: 'long', day: 'numeric', month: 'long' });
      } catch (e) { }
      document.getElementById('reportDateLabel').textContent = dateAr;

      // Reset should buttons
      ['reportShouldPresent', 'reportShouldAbsent'].forEach(id => {
        const b = document.getElementById(id);
        if (b) { b.style.background = 'var(--surf)'; b.style.borderColor = 'var(--bdr)'; b.style.color = 'var(--t2)'; }
      });

      // Build uncle list
      const unclesWithPhone = classUncles.filter(u => u.phone && u.phone.trim());
      const ul = document.getElementById('reportUncleList');
      if (!unclesWithPhone.length) {
        ul.innerHTML = `<div style="text-align:center;padding:16px;color:var(--t4);font-size:.82rem;font-weight:600;">
      <i class="fas fa-phone-slash" style="display:block;font-size:1.2rem;margin-bottom:5px;opacity:.4;"></i>
      لم يُضَف رقم هاتف لأي مدرّس بعد</div>`;
      } else {
        const roleLbl = { admin: 'مشرف', developer: 'مطوّر', uncle: 'مدرّس' };
        ul.innerHTML = unclesWithPhone.map(u => `
      <button onclick="sendRowReport(${u.id})"
        style="width:100%;display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:var(--r-md);border:1.5px solid var(--bdr);background:var(--surf);cursor:pointer;font-family:'Baloo Bhaijaan 2',sans-serif;transition:var(--fast);"
        onmouseover="this.style.borderColor='#d97706';this.style.background='#fef3c7'"
        onmouseout="this.style.borderColor='var(--bdr)';this.style.background='var(--surf)'">
        <div style="width:36px;height:36px;border-radius:50%;background:linear-gradient(135deg,var(--brand-bg),#c7d2fe);color:var(--brand);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.88rem;flex-shrink:0;overflow:hidden;">
          ${u.image_url ? `<img src="${esc(u.image_url)}" style="width:100%;height:100%;object-fit:cover;">` : u.name.charAt(0)}
        </div>
        <div style="flex:1;text-align:right;">
          <div style="font-size:.88rem;font-weight:800;color:var(--t1);">${esc(u.name)}</div>
          <div style="font-size:.68rem;color:var(--t4);font-weight:500;">${roleLbl[u.role] || u.role}</div>
        </div>
        <div style="width:30px;height:30px;border-radius:var(--r-sm);background:#fef3c7;color:#d97706;display:flex;align-items:center;justify-content:center;font-size:.95rem;flex-shrink:0;">
          <i class="fab fa-whatsapp"></i>
        </div>
      </button>`).join('');
      }

      openOv('attReportOv');
    }

    function setReportShould(val) {
      _reportShould = val;
      const map = {
        present: { id: 'reportShouldPresent', bg: 'var(--ok-bg)', border: '#6ee7b7', color: 'var(--ok)' },
        absent: { id: 'reportShouldAbsent', bg: 'var(--err-bg)', border: '#fca5a5', color: 'var(--err)' },
      };
      ['reportShouldPresent', 'reportShouldAbsent'].forEach(id => {
        const b = document.getElementById(id);
        if (b) { b.style.background = 'var(--surf)'; b.style.borderColor = 'var(--bdr)'; b.style.color = 'var(--t2)'; }
      });
      const cfg = map[val]; if (!cfg) return;
      const btn = document.getElementById(cfg.id);
      if (btn) { btn.style.background = cfg.bg; btn.style.borderColor = cfg.border; btn.style.color = cfg.color; }
    }

    function sendRowReport(uid) {
      if (!_reportShould) { toast('اختر حضر أو غاب أولاً', 'err'); return; }
      const u = classUncles.find(x => x.id === uid);
      if (!u || !u.phone) return;
      const name = (student && student.name) || '';
      const cls = (student && student.class) || '';
      const shouldAr = _reportShould === 'present' ? 'حضر' : 'غاب';
      let dateAr = _reportDateStr;
      try {
        const [yr, mo, dy] = _reportDateStr.split('-').map(Number);
        dateAr = new Date(yr, mo - 1, dy, 12).toLocaleDateString('ar-EG', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
      } catch (e) { }
      const msg = `${name}${cls ? ' (' + cls + ')' : ''} — ${dateAr}\nالمفروض أكون: ${shouldAr}`;
      const wa = '20' + u.phone.trim().replace(/^0/, '');
      window.open(`https://wa.me/${wa}?text=${encodeURIComponent(msg)}`, '_blank');
    }

    // ── Tasks ─────────────────────────────────────────────────────────
    async function loadTasks() {
      try {
        const d = await api({ action: 'getStudentTasks', student_id: student.id, church_id: student.church_id, class_id: student.class_id || 0 });
        if (d.success) renderTasks(d.tasks || []);
      } catch (e) { }
    }
    function tSt(t) {
      if (t.my_submission) return 'done';
      const n = Date.now(), s = new Date(t.start_date).getTime();
      const hasDeadline = !parseInt(t.no_deadline || 0) && !!t.end_date;
      const e = hasDeadline ? new Date(t.end_date).getTime() : null;
      if (n < s) return 'upcoming'; if (hasDeadline && n > e) return 'expired'; return 'open';
    }
    function renderTasks(tasks) {
      allTasks = tasks;
      const el = document.getElementById('taskList');
      document.getElementById('taskSub').textContent = tasks.length + ' مهمة';
      if (!tasks.length) {
        el.innerHTML = `
          <div style="text-align:center; padding:50px 20px; color:rgba(255,255,255,0.7);">
            <div style="font-size:3rem; margin-bottom:15px; opacity:0.4;"><i class="fas fa-tasks"></i></div>
            <div style="font-size:1.05rem; font-weight:800;">لا توجد مهام أو اختبارات حالياً</div>
            <div style="font-size:0.8rem; margin-top:6px; opacity:0.8;">تابع مع مدرسك لمعرفة كل جديد</div>
          </div>
        `;
        return;
      }
      const stLbl = { done: 'مكتمل', open: 'مفتوح', upcoming: 'قادم', expired: 'منتهي' };
      const stBar = { done: 'done-bar', open: '', upcoming: 'up-bar', expired: 'exp-bar' };
      const stBadge = { done: 'tb-done', open: 'tb-open', upcoming: 'tb-up', expired: 'tb-exp' };
      el.innerHTML = tasks.map(t => {
        const st = tSt(t);
        const sub = t.my_submission;

        // Max coupon from matrix
        const matrix = t.coupon_matrix ? JSON.parse(t.coupon_matrix || '[]') : [];
        const maxCoupon = matrix.length ? Math.max(...matrix.map(m => parseInt(m.val) || 0)) : 0;

        // Coupon row — always visible
        let couponRow = '';
        if (sub) {
          // Submitted: show earned score + coupons
          const pct = t.total_degree > 0 ? Math.round(sub.score / t.total_degree * 100) : 0;
          couponRow = `<div class="task-result">
        <i class="fas fa-check-circle"></i>
        <span>${sub.score}/${t.total_degree} (${pct}%)</span>
        <span style="margin-right:auto;display:flex;align-items:center;gap:4px;">
          <i class="fas fa-star" style="color:var(--cou-l);font-size:.75rem;"></i>
          <strong style="color:var(--cou);font-size:.82rem;">${sub.coupons_awarded}</strong>
          <span style="font-size:.7rem;color:var(--t3);margin-left:5px;">كوبون</span>
          ${t.show_answers ? `<button onclick="event.stopPropagation();viewMyAnswers(${t.id})" style="margin-right:5px;background:var(--s2);border:1px solid var(--brand-l);color:var(--brand);border-radius:5px;padding:3px 8px;font-size:.7rem;font-family:'Baloo Bhaijaan 2',sans-serif;font-weight:700;cursor:pointer;"><i class="fas fa-eye"></i> الإجابات</button>` : ''}
        </span>
      </div>`;
        } else if (maxCoupon > 0) {
          // Not submitted: show max possible coupons
          couponRow = `<div class="task-coupon-row" style="display:flex;align-items:center;gap:5px;margin-top:7px;padding:5px 9px;border-radius:var(--r-sm);background:var(--cou-bg);border:1px solid #c4b5fd;font-size:.74rem;">
        <i class="fas fa-star" style="color:var(--cou-l);"></i>
        <span style="color:var(--cou);font-weight:700;">حتى ${maxCoupon} كوبون</span>
        <span style="color:var(--t4);font-size:.68rem;">عند الإجابة</span>
      </div>`;
        }

        return `<div class="task-card" onclick="openTask(${t.id})">
      <div class="task-bar ${stBar[st]}"></div>
      <div class="task-body">
        <div class="task-top">
          <div class="task-title">${esc(t.title)}</div>
          <span class="task-badge ${stBadge[st]}">${stLbl[st]}</span>
        </div>
        <div class="task-metas">
          <span class="task-meta-chip"><i class="fas fa-calendar-alt"></i>${parseInt(t.no_deadline || 0) ? 'بدون آخر موعد' : fmtDate(t.end_date)}</span>
          ${t.total_degree ? `<span class="task-meta-chip"><i class="fas fa-star"></i>${t.total_degree} درجة</span>` : ''}
          ${t.time_limit ? `<span class="task-meta-chip"><i class="fas fa-stopwatch"></i>${t.time_limit} دقيقة</span>` : ''}
        </div>
        ${couponRow}
      </div>
    </div>`;
      }).join('');
    }

    // ── Task exam (full-screen, DB-anchored timer, auto-save) ─────────
    let examStartedAt = null;   // Date set from server
    let examTimerIv = null;   // countdown interval
    let examTimeLimitSec = 0;      // seconds total

    async function openTask(id) {
      if (isViewingOther()) {
        toast('غير مسموح في وضع المعاينة', 'err');
        return;
      }
      const t = allTasks.find(x => x.id == id); if (!t) return;

      // Automatically dismiss task announcements when the task is opened
      const dismissedIds = JSON.parse(localStorage.getItem('dismissedAnns_' + student.id) || '[]');
      let changed = false;
      allAnnouncements.forEach(ann => {
        const isTaskAnn = ann.type === 'task' || (ann.text && (ann.text.includes(t.title) || ann.text.includes('مهمة')));
        if (isTaskAnn && !dismissedIds.includes(parseInt(ann.id))) {
          dismissedIds.push(parseInt(ann.id));
          changed = true;
        }
      });
      if (changed) {
        localStorage.setItem('dismissedAnns_' + student.id, JSON.stringify(dismissedIds));
        loadAnn();
      }
      const st = tSt(t);
      if (st === 'done' && t.my_submission) { showExamResult(t, t.my_submission); return; }
      if (st === 'upcoming') { toast('هذه المهمة لم تُفتح بعد', 'info'); return; }
      if (st === 'expired' && !t.my_submission) { toast('انتهت فترة التسليم', 'err'); return; }
      curTask = t; examDone = false;

      if (t.time_limit) {
        examTimeLimitSec = t.time_limit * 60;
        taskAnswers = JSON.parse(localStorage.getItem(`ta_${t.id}_${student.id}`) || '{}');

        // ── 1. Check localStorage first (instant, no network) ──────
        const lsKey = `examStart_${t.id}_${student.id}`;
        const lsStart = localStorage.getItem(lsKey);
        if (lsStart) {
          const parsed = new Date(lsStart);
          const elapsed = Math.floor((Date.now() - parsed.getTime()) / 1000);
          const remaining = examTimeLimitSec - elapsed;
          if (remaining > 0) {
            // Active session in localStorage — show continue
            examStartedAt = parsed;
            showExamStartScreen(t, true, remaining);
            // Also verify/sync with server silently
            _syncExamStart(t, lsStart);
            return;
          } else {
            // Expired in localStorage — clear it and let them start fresh
            localStorage.removeItem(lsKey);
            examStartedAt = null;
          }
        }

        // ── 2. No localStorage record — check server ────────────────
        try {
          const d = await api({ action: 'getExamStart', student_id: student.id, church_id: student.church_id, task_id: t.id });
          if (d.success && d.started_at) {
            const parsed = new Date(d.started_at.replace(' ', 'T'));
            const elapsed = Math.floor((Date.now() - parsed.getTime()) / 1000);
            const remaining = examTimeLimitSec - elapsed;
            if (remaining > 0) {
              // Server has active session — restore it and save to localStorage
              examStartedAt = parsed;
              localStorage.setItem(lsKey, d.started_at.replace(' ', 'T'));
              showExamStartScreen(t, true, remaining);
              return;
            }
            // Server record expired — clear it
            try { await api({ action: 'clearExamStart', student_id: student.id, church_id: student.church_id, task_id: t.id }); } catch (e) { }
            examStartedAt = null;
          }
        } catch (e) { }
      } else {
        taskAnswers = JSON.parse(localStorage.getItem(`ta_${t.id}_${student.id}`) || '{}');
      }

      showExamStartScreen(t, false, 0);
    }

    // Silently sync localStorage start time to server (fire-and-forget)
    async function _syncExamStart(t, startedAtStr) {
      try {
        await api({ action: 'startExam', student_id: student.id, church_id: student.church_id, task_id: t.id });
      } catch (e) { }
    }

    function examMetaRow(bg, bdr, icoColor, icon, label, sub) {
      return `<div class="exam-meta-row" style="background:${bg};border:1px solid ${bdr};">
    <i class="${icon}" style="color:${icoColor};"></i>
    <div class="em-text">
      <div>${label}</div>
      ${sub ? `<div class="em-sub">${sub}</div>` : ''}
    </div>
  </div>`;
    }

    // isResume=true → student already started, show "استكمل" with remaining time
    function showExamStartScreen(t, isResume, remainingSec) {
      const qs = (t.questions || []).length;
      document.getElementById('startTitle').textContent = t.title;
      document.getElementById('startSub').textContent = `${qs} سؤال · ${t.total_degree} درجة`;
      const lsKey = `examStart_${t.id}_${student.id}`;
      const hasSaved = Object.keys(JSON.parse(localStorage.getItem(`ta_${t.id}_${student.id}`) || '{}')).length > 0;
      const hasStart = !!localStorage.getItem(lsKey);
      const rows = [];

      if (t.time_limit) {
        if ((isResume || hasStart) && remainingSec > 0) {
          const remM = Math.floor(remainingSec / 60);
          const remS = remainingSec % 60;
          const remStr = `${remM}:${String(remS).padStart(2, '0')}`;
          rows.push(examMetaRow('#fef3c7', '#fde68a', '#d97706', 'fas fa-stopwatch',
            `الوقت المتبقي: <strong style="font-size:1.1em;color:#b45309;">${remStr}</strong>`,
            'الوقت يعمل من السيرفر — يستكمل من حيث توقف'));
        } else {
          rows.push(examMetaRow('var(--warn-bg)', '#fde68a', 'var(--warn)', 'fas fa-stopwatch',
            `مدة الاختبار: <strong>${t.time_limit} دقيقة</strong>`,
            'يبدأ العد فور الضغط على ابدأ ويُسجَّل في السيرفر'));
        }
      } else {
        rows.push(examMetaRow('var(--ok-bg)', '#6ee7b7', 'var(--ok)', 'fas fa-infinity',
          'لا يوجد وقت محدد — أجب بتأنٍّ', null));
      }
      rows.push(examMetaRow('var(--s2)', 'var(--bdr)', 'var(--brand)', 'fas fa-list-ol', `${qs} سؤال`, null));
      rows.push(examMetaRow('var(--s2)', 'var(--bdr)', 'var(--gold-l)', 'fas fa-award', `${t.total_degree} درجة كاملة`, null));
      if (hasSaved || isResume || hasStart) {
        rows.push(examMetaRow('var(--brand-bg)', 'var(--brand-l)', 'var(--brand)', 'fas fa-layer-group',
          'إجاباتك السابقة محفوظة وستُستكمل', null));
      }
      document.getElementById('startMeta').innerHTML = rows.join('');

      const startBtn = document.getElementById('examStartBtn');
      if (startBtn) {
        startBtn.innerHTML = (isResume || hasSaved || hasStart)
          ? '<i class="fas fa-play-circle"></i> استكمل الاختبار'
          : '<i class="fas fa-play-circle"></i> ابدأ الاختبار';
      }

      examShowView('start');
      examScreenOpen();
    }

    async function beginExam() {
      if (!curTask) return;
      const t = curTask;
      taskAnswers = JSON.parse(localStorage.getItem(`ta_${t.id}_${student.id}`) || '{}');

      if (t.time_limit) {
        examTimeLimitSec = t.time_limit * 60;
        const lsKey = `examStart_${t.id}_${student.id}`;

        if (!examStartedAt) {
          // Fresh start — save to localStorage immediately before any network call
          const now = new Date();
          examStartedAt = now;
          localStorage.setItem(lsKey, now.toISOString());
          // Record on server too (INSERT IGNORE keeps original if already exists)
          try {
            const d = await api({ action: 'startExam', student_id: student.id, church_id: student.church_id, task_id: t.id });
            if (d.started_at) {
              const serverTime = new Date(d.started_at.replace(' ', 'T'));
              const diff = Math.abs(serverTime.getTime() - now.getTime());
              if (diff < 10000) { // within 10s — prefer server time
                examStartedAt = serverTime;
                localStorage.setItem(lsKey, d.started_at.replace(' ', 'T'));
              }
            }
          } catch (e) { } // localStorage fallback already saved — safe to ignore
        }
        // If examStartedAt already set (resume path) — use as-is, timer continues
      }

      document.getElementById('examHeaderTitle').textContent = t.title;
      document.getElementById('examHeaderSub').textContent = `${(t.questions || []).length} سؤال — ${t.total_degree} درجة`;
      document.getElementById('examTotalQ').textContent = (t.questions || []).length;
      renderExamQuestions(t);
      examShowView('active');
      if (t.time_limit) startExamCountdown(t);
    }

    function renderExamQuestions(t) {
      const qs = t.questions || [];
      document.getElementById('examQList').innerHTML = qs.map((q, i) => {
        const qtype = q.question_type || 'mcq';
        const imgHtml = q.image_url
          ? `<div style="margin:0 0 2px;"><img src="${esc(q.image_url)}" alt="" style="width:100%;max-height:200px;object-fit:contain;display:block;background:var(--s2);"></div>`
          : '';

        // ── Open / essay ──────────────────────────────────────────
        if (qtype === 'open') {
          const savedAns = taskAnswers[String(q.id)] || '';
          return `<div class="qcard" id="qc_${q.id}">
        <div class="qhdr">
          <div class="qnum" style="background:linear-gradient(135deg,#f59e0b,#d97706);">${i + 1}</div>
          <div class="qtext">${esc(q.question_text)}</div>
          <span style="background:#fef3c7;color:#92400e;border-radius:var(--r-full);padding:2px 8px;font-size:.65rem;font-weight:700;flex-shrink:0;"><i class="fas fa-pen-nib"></i> مفتوح</span>
          <span class="qdeg" style="background:#fef3c7;color:#d97706;">${q.degree} درجة</span>
        </div>
        ${imgHtml}
        <div class="qopts" style="padding:10px 12px;display:block;">
          <textarea class="open-ans-textarea" id="openans_${q.id}"
            placeholder="اكتب إجابتك هنا…"
            oninput="pickOpenAns(${q.id},this)">${esc(savedAns)}</textarea>
          <div style="font-size:.69rem;color:var(--t4);margin-top:5px;display:flex;align-items:center;gap:4px;">
            <i class="fas fa-info-circle"></i> يُصحَّح من قِبَل الانكل أو الطنط — الدرجة النهائية ستظهر بعد التصحيح
          </div>
        </div>
      </div>`;
        }

        // ── True / False ──────────────────────────────────────────
        if (qtype === 'tf') {
          const saved = taskAnswers[String(q.id)];
          const trueOn = saved === 0; const falseOn = saved === 1;
          return `<div class="qcard" id="qc_${q.id}">
        <div class="qhdr">
          <div class="qnum">${i + 1}</div>
          <div class="qtext">${esc(q.question_text)}</div>
          <span class="qdeg">${q.degree} درجة</span>
        </div>
        ${imgHtml}
        <div class="qopts" style="display:flex;gap:10px;padding:10px 12px;">
          <button id="tfbtn_${q.id}_0" onclick="pickOpt(${q.id},0,null)"
            style="flex:1;padding:13px 8px;border-radius:var(--r-sm);border:2px solid ${trueOn ? 'var(--ok)' : 'var(--bdr)'};background:${trueOn ? 'var(--ok-bg)' : 'var(--surf)'};color:${trueOn ? 'var(--ok)' : 'var(--t2)'};font-family:inherit;font-size:.88rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;transition:var(--fast);">
            <i class="fas fa-check-circle"></i> صحيح
          </button>
          <button id="tfbtn_${q.id}_1" onclick="pickOpt(${q.id},1,null)"
            style="flex:1;padding:13px 8px;border-radius:var(--r-sm);border:2px solid ${falseOn ? 'var(--err)' : 'var(--bdr)'};background:${falseOn ? 'var(--err-bg)' : 'var(--surf)'};color:${falseOn ? 'var(--err)' : 'var(--t2)'};font-family:inherit;font-size:.88rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;transition:var(--fast);">
            <i class="fas fa-times-circle"></i> خطأ
          </button>
        </div>
      </div>`;
        }

        // ── MCQ (default) ─────────────────────────────────────────
        const opts = typeof q.options === 'string' ? JSON.parse(q.options) : (q.options || []);
        const sel = taskAnswers[String(q.id)] !== undefined ? taskAnswers[String(q.id)] : null;
        return `<div class="qcard" id="qc_${q.id}">
      <div class="qhdr">
        <div class="qnum">${i + 1}</div>
        <div class="qtext">${esc(q.question_text)}</div>
        ${sel !== null ? `<span style="background:var(--ok-bg);color:var(--ok);border-radius:var(--r-full);padding:2px 7px;font-size:.62rem;font-weight:700;flex-shrink:0;"><i class="fas fa-check"></i></span>` : ''}
        <span class="qdeg">${q.degree} درجة</span>
      </div>
      ${imgHtml}
      <div class="qopts">${opts.map((o, j) => `<div class="qopt${sel === j ? ' selected' : ''}" onclick="pickOpt(${q.id},${j},this)"><div class="oradio"></div><div class="olet">${LETTERS[j]}</div>${esc(o)}</div>`).join('')}</div>
    </div>`;
      }).join('');
      updExamProgress(t);
    }

    function pickOpt(qid, idx, el) {
      if (examDone) return;
      taskAnswers[String(qid)] = idx;
      localStorage.setItem(`ta_${curTask.id}_${student.id}`, JSON.stringify(taskAnswers));
      // MCQ: toggle selected class
      if (el && el.closest && el.closest('.qopts')) {
        el.closest('.qopts').querySelectorAll('.qopt').forEach(o => o.classList.remove('selected'));
        el.classList.add('selected');
      }
      // TF: re-render TF buttons with updated state
      const tfT = document.getElementById(`tfbtn_${qid}_0`);
      const tfF = document.getElementById(`tfbtn_${qid}_1`);
      if (tfT && tfF) {
        const isTrueSelected = (idx === 0);
        tfT.style.border = `2px solid ${isTrueSelected ? '#10b981' : 'var(--bdr)'}`;
        tfT.style.background = isTrueSelected ? '#d1fae5' : 'var(--s2)';
        tfT.style.color = isTrueSelected ? '#065f46' : 'var(--t2)';
        tfT.querySelector('i').style.color = isTrueSelected ? '#10b981' : 'var(--t4)';
        tfF.style.border = `2px solid ${!isTrueSelected ? '#ef4444' : 'var(--bdr)'}`;
        tfF.style.background = !isTrueSelected ? '#fee2e2' : 'var(--s2)';
        tfF.style.color = !isTrueSelected ? '#991b1b' : 'var(--t2)';
        tfF.querySelector('i').style.color = !isTrueSelected ? '#ef4444' : 'var(--t4)';
      }
      updExamProgress(curTask);
    }

    function pickOpenAns(qid, textarea) {
      if (examDone) { textarea.value = taskAnswers[qid] || ''; return; }
      taskAnswers[String(qid)] = textarea.value;
      localStorage.setItem(`ta_${curTask.id}_${student.id}`, JSON.stringify(taskAnswers));
      updExamProgress(curTask);
    }

    function updExamProgress(t) {
      const qs = t ? t.questions || [] : curTask ? curTask.questions || [] : [];
      const total = qs.length;
      let done = 0;
      // Build nav dots and update card borders
      const navEl = document.getElementById('examQNav');
      const dots = qs.map((q, i) => {
        const k = String(q.id);
        const isOpen = q.question_type === 'open';
        let answered = false;
        if (isOpen) answered = !!(taskAnswers[k] && String(taskAnswers[k]).trim());
        else answered = taskAnswers[k] !== undefined;
        if (answered) done++;
        // Update card border to show answered/unanswered visually
        const card = document.getElementById(`qc_${q.id}`);
        if (card) {
          card.style.borderColor = answered ? 'var(--ok)' : 'var(--bdr)';
          card.style.borderWidth = answered ? '2px' : '1.5px';
        }
        return `<div onclick="document.getElementById('qc_${q.id}')?.scrollIntoView({behavior:'smooth',block:'center'})"
      title="سؤال ${i + 1}" style="width:22px;height:22px;border-radius:50%;cursor:pointer;
        display:flex;align-items:center;justify-content:center;font-size:.6rem;font-weight:800;
        flex-shrink:0;transition:all .2s;
        background:${answered ? 'var(--ok)' : 'var(--bdr)'};
        color:${answered ? '#fff' : 'var(--t3)'};
        box-shadow:${answered ? '0 2px 6px rgba(5,150,105,.3)' : 'none'};"
    >${i + 1}</div>`;
      });
      if (navEl) navEl.innerHTML = dots.join('');
      const pct = total > 0 ? Math.round(done / total * 100) : 0;
      const el = document.getElementById('examAnsDone'); if (el) el.textContent = done;
      const pb = document.getElementById('examProgBar'); if (pb) pb.style.width = pct + '%';
    }

    function startExamCountdown(t) {
      clearInterval(examTimerIv);
      const beh = t.timer_behavior || 'submit';
      const tick = () => {
        if (!examStartedAt || !examTimeLimitSec) return;
        const elapsed = Math.floor((Date.now() - examStartedAt.getTime()) / 1000);
        const rem = examTimeLimitSec - elapsed;
        const badge = document.getElementById('examTimerBadge');
        // Guard: only fire expire logic if exam is actually active on screen
        if (rem <= 0) {
          clearInterval(examTimerIv);
          if (badge) { badge.textContent = '00:00'; badge.classList.add('urgent'); }
          // Only auto-submit if exam screen is open and not already done
          if (!examDone) {
            if (beh === 'submit') _doSubmitExam();
            else { examDone = true; toast('انتهى الوقت', 'err'); }
          }
          return;
        }
        const m = Math.floor(rem / 60).toString().padStart(2, '0');
        const s = (rem % 60).toString().padStart(2, '0');
        const urg = rem <= 60;
        if (badge) {
          badge.style.display = 'block';
          badge.innerHTML = `<i class="fas fa-stopwatch" style="font-size:.75rem;margin-left:4px;"></i>${m}:${s}`;
          badge.classList.toggle('urgent', urg);
        }
      };
      // Delay first tick by 1s so the UI finishes rendering before any expire check
      examTimerIv = setInterval(tick, 1000);
      setTimeout(tick, 500);
    }

    function examScreenOpen() {
      const scr = document.getElementById('examScreen');
      scr.style.display = 'block';
      scr.scrollTop = 0;
      document.documentElement.classList.add('ov-open');
    }

    function examScreenClose() {
      clearInterval(examTimerIv);
      document.getElementById('examScreen').style.display = 'none';
      if (!document.querySelector('.overlay.open')) document.documentElement.classList.remove('ov-open');
      curTask = null; examDone = false; examStartedAt = null; taskAnswers = {};
      examShowView('start');
    }

    function confirmExitExam() {
      if (examDone) { examScreenClose(); return; }
      if (Object.keys(taskAnswers).length > 0) {
        document.getElementById('scModalMsg').textContent = 'إجاباتك محفوظة تلقائياً. هل تريد الخروج؟';
        document.getElementById('exitConfirmModal').classList.add('open');
        document.documentElement.classList.add('ov-open');
        return;
      }
      examScreenClose();
    }
    function _closeExitConfirm() {
      document.getElementById('exitConfirmModal').classList.remove('open');
      if (!document.querySelector('.overlay.open')) document.documentElement.classList.remove('ov-open');
    }
    function _confirmExit() {
      _closeExitConfirm();
      examScreenClose();
    }

    // alias used by result "back" button
    function exitExamScreen() { examScreenClose(); }

    function examShowView(which) {
      ['examStartView', 'examActiveView', 'examResultView'].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.style.display = 'none'; }
      });
      const map = { start: 'examStartView', active: 'examActiveView', result: 'examResultView' };
      const el = document.getElementById(map[which]);
      if (el) { el.style.display = 'flex'; }
    }


    function buildResultCard(score, total, pct, coupons, hasOpenQs, taskId, showAnswers) {
      let grad, iconCls, msg, color;
      if (pct >= 90) {
        grad = 'linear-gradient(135deg, #059669 0%, #10b981 100%)';
        iconCls = 'fas fa-trophy';
        msg = 'ممتاز! إجاباتك رائعة، أنت نجم الفصل!';
        color = '#059669';
      } else if (pct >= 70) {
        grad = 'linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%)';
        iconCls = 'fas fa-medal';
        msg = 'أحسنت جداً! نتيجة جميلة وأنت تستاهل أكثر من كده.';
        color = '#1d4ed8';
      } else if (pct >= 50) {
        grad = 'linear-gradient(135deg, #b45309 0%, #f59e0b 100%)';
        iconCls = 'fas fa-star';
        msg = 'برافو! في تحسّن واضح وأنت على الطريق الصح.';
        color = '#b45309';
      } else {
        grad = 'linear-gradient(135deg, #4f46e5 0%, #818cf8 100%)';
        iconCls = 'fas fa-heart';
        msg = 'شكراً على مشاركتك! كل خطوة بتخليك أقوى وأحسن.';
        color = '#4f46e5';
      }

      const couponHtml = coupons > 0 ? `
    <div style="display:inline-flex;align-items:center;gap:8px;padding:10px 20px;background:var(--cou-bg);color:var(--cou);border-radius:var(--r-full);font-weight:800;font-size:1.1rem;box-shadow:0 4px 15px rgba(124,58,237,.2);margin-top:15px;animation:bounce 2s infinite;">
      <i class="fas fa-star"></i> حصلت على ${coupons} كوبون!
    </div>` : '';

      const pendingNote = hasOpenQs ? `
    <div style="display:flex;align-items:center;gap:10px;margin-top:20px;padding:12px 18px;background:var(--warn-bg);border:1.5px solid #fde68a;border-radius:var(--r-md);font-size:.85rem;color:#92400e;font-weight:700;line-height:1.5;">
      <i class="fas fa-clock" style="font-size:1.2rem;flex-shrink:0;"></i>
      <span>هذه درجة مؤقتة — الأسئلة المفتوحة يتم تصحيحها يدوياً. الدرجة النهائية ستظهر قريباً!</span>
    </div>` : '';

      return `
    <div style="background:#fff;border-radius:var(--r-2xl);overflow:hidden;box-shadow:var(--sh-xl);animation:pop-in .5s var(--norm);">
      <div style="background:${grad};padding:40px 20px 60px;text-align:center;position:relative;">
        <div style="width:100px;height:100px;background:rgba(255,255,255,.2);backdrop-filter:blur(10px);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;border:3px solid #fff;box-shadow:0 8px 30px rgba(0,0,0,.2);">
          <i class="${iconCls}" style="color:#fff;font-size:3.2rem;"></i>
        </div>
        <div style="font-size:4rem;font-weight:900;color:#fff;line-height:1;text-shadow:0 4px 15px rgba(0,0,0,.2);">${score} <span style="font-size:1.5rem;opacity:.7;">/ ${total}</span></div>
        <div style="color:rgba(255,255,255,.9);font-weight:800;font-size:1.3rem;margin-top:5px;">${hasOpenQs ? 'درجة مؤقتة' : pct + '%'}</div>
        <div style="position:absolute;bottom:-30px;left:0;right:0;height:40px;background:#fff;clip-path:ellipse(55% 100% at 50% 100%);"></div>
      </div>
      <div style="padding:40px 30px 30px;text-align:center;">
        <div style="font-size:1.35rem;font-weight:800;color:var(--t1);line-height:1.4;margin-bottom:10px;">${msg}</div>
        ${couponHtml}
        ${pendingNote}
        <div style="margin-top:35px;display:flex;flex-direction:column;gap:12px;">
          ${showAnswers && taskId ? `<button onclick="viewMyAnswers(${taskId})" style="width:100%;padding:14px;border-radius:var(--r-lg);background:var(--s2);border:2.5px solid ${color};color:${color};font-family:inherit;font-weight:800;font-size:.95rem;cursor:pointer;transition:var(--fast);display:flex;align-items:center;justify-content:center;gap:10px;"><i class="fas fa-eye"></i> راجع إجاباتك وتعلم من أخطائك</button>` : ''}
          <button onclick="exitExamScreen()" style="width:100%;padding:14px;border-radius:var(--r-lg);background:${color};border:none;color:#fff;font-family:inherit;font-weight:800;font-size:.95rem;cursor:pointer;box-shadow:0 6px 20px ${color}44;transition:var(--fast);">
             العودة للملف الشخصي
          </button>
        </div>
      </div>
    </div>
    <style>
      @keyframes pop-in { from { transform: scale(.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
      @keyframes bounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-5px); } }
  


  </style>
  `;
    }
    function showExamResult(t, sub) {
      curTask = t; examDone = true;
      const pct = t.total_degree > 0 ? Math.round(sub.score / t.total_degree * 100) : 0;
      const hasOpenQs = (t.questions || []).some(q => q.question_type === 'open');
      document.getElementById('examResultCard').innerHTML = buildResultCard(sub.score, t.total_degree, pct, sub.coupons_awarded, hasOpenQs, t.id, !!parseInt(t.show_answers || 0));
      examShowView('result');
      examScreenOpen();
    }

    // legacy stubs
    function openExamResult(t) { showExamResult(t, t.my_submission); }
    function renderExam(t) { renderExamQuestions(t); }
    function updProg(t) { updExamProgress(t); }
    function updAnsCnt(t) { updExamProgress(t); }
    function saveCloseExam() { examScreenClose(); }
    function closeExam() { examScreenClose(); }

    async function submitExam() {
      if (isViewingOther()) {
        toast('غير مسموح في وضع المعاينة', 'err');
        return;
      }
      if (!curTask || examDone) return;
      const qs = curTask.questions || [];
      // Count unanswered questions of all types
      const unanswered = qs.filter(q => {
        const k = String(q.id);
        if (q.question_type === 'open') return !taskAnswers[k] || !String(taskAnswers[k]).trim();
        return taskAnswers[k] === undefined && taskAnswers[q.id] === undefined;
      }).length;
      if (unanswered > 0) {
        _showSubmitConfirm(unanswered);
        return;
      }
      await _doSubmitExam();
    }

    function _showSubmitConfirm(unanswered) {
      document.getElementById('scModalMsg').textContent = `لم تجب على ${unanswered} سؤال. هل تريد التسليم الآن؟`;
      document.getElementById('submitConfirmModal').classList.add('open');
      document.documentElement.classList.add('ov-open');
    }
    function _closeSubmitConfirm() {
      document.getElementById('submitConfirmModal').classList.remove('open');
      if (!document.querySelector('.overlay.open')) document.documentElement.classList.remove('ov-open');
    }
    function _confirmSubmitExam() {
      _closeSubmitConfirm();
      _doSubmitExam();
    }

    async function _doSubmitExam() {
      if (!curTask || examDone) return;
      clearInterval(examTimerIv); examDone = true;
      const tt = examStartedAt ? Math.floor((Date.now() - examStartedAt.getTime()) / 1000) : null;
      try {
        const d = await api({ action: 'submitTaskAnswers', student_id: student.id, church_id: student.church_id, task_id: curTask.id, answers: JSON.stringify(taskAnswers), time_taken_sec: tt });
        localStorage.removeItem(`ta_${curTask.id}_${student.id}`);
        localStorage.removeItem(`examStart_${curTask.id}_${student.id}`);
        if (d.success) {
          // Build a complete my_submission object so viewMyAnswers works immediately
          // without waiting for loadTasks() to re-fetch
          const mySubmission = {
            id: d.submission_id || null,
            score: d.score || 0,
            coupons_awarded: d.coupons_awarded || 0,
            submitted_at: new Date().toISOString(),
            answers: taskAnswers,                          // the answers we just sent
            correct_answers: d.questions_with_answers      // full questions with correct_index from API
              ? Object.fromEntries((d.questions_with_answers || []).filter(q => q.question_type !== 'open').map(q => [q.id, parseInt(q.correct_index)]))
              : (curTask.my_submission?.correct_answers || {}),
          };
          // Patch the task in allTasks so viewMyAnswers can find it
          curTask.my_submission = mySubmission;
          const idx = allTasks.findIndex(x => x.id === curTask.id);
          if (idx >= 0) allTasks[idx].my_submission = mySubmission;

          // Always refresh student data from server after submission
          // so coupons (task + total) reflect the real DB values
          try {
            const sd = await api({ action: 'getStudentProfile', studentId: student.id });
            if (sd.success && (sd.student || sd.user)) {
              const fresh = norm(sd.student || sd.user);
              // preserve fields not returned by getStudentProfile
              student.coupons = fresh.coupons;
              student.task_coupons = fresh.task_coupons;
              student.att_coupons = fresh.att_coupons;
              student.com_coupons = fresh.com_coupons;
            }
          } catch (_) { }
          // Re-render coupon hero with fresh values
          renderCouponHero(student);
          document.getElementById('couponHero').style.display = 'grid';
          if (d.show_result) {
            const hasOpenQs = (curTask.questions || []).some(q => q.question_type === 'open');
            document.getElementById('examResultCard').innerHTML = buildResultCard(d.score, curTask.total_degree, d.percentage, d.coupons_awarded, hasOpenQs, curTask.id, !!d.show_answers);
            examShowView('result');
          } else { toast('تم التسليم ✓', 'ok'); examScreenClose(); }
          loadTasks();
        } else { examDone = false; toast(d.message || 'فشل التسليم', 'err'); }
      } catch (e) { examDone = false; toast('خطأ في الاتصال', 'err'); }
    }

    // ── Trips ─────────────────────────────────────────────────────────
    async function loadTrips(isPrivate) {
      if (!student?.church_id) return;
      try {
        const params = { action: 'getStudentTrips', church_id: student.church_id };
        if (isPrivate && student.id) params.student_id = student.id;
        const d = await api(params);
        if (d.success && d.trips && d.trips.length) {
          allTrips = d.trips;
          renderTrips(d.trips);
          document.getElementById('scTrips').style.display = 'block';
        } else {
          // No trips — keep section hidden
          document.getElementById('scTrips').style.display = 'none';
        }
      } catch (e) {
        document.getElementById('scTrips').style.display = 'none';
      }
    }
    function renderTrips(trips) {
      const el = document.getElementById('tripList');
      document.getElementById('tripSub').textContent = trips.length + ' رحلة';
      const stLbl = { planned: 'مخطط', active: 'نشط', completed: 'مكتمل', cancelled: 'ملغي' };
      el.innerHTML = trips.map(t => {
        const thumb = t.image_url
          ? `<img class="trip-thumb" src="${esc(t.image_url)}" alt="${esc(t.title)}" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">`
          : '';
        const ph = `<div class="trip-thumb-placeholder" ${t.image_url ? 'style="display:none"' : ''}><i class="fas fa-bus"></i><span>${esc(t.title)}</span></div>`;
        const priceOverlay = parseFloat(t.final_price) > 0
          ? `<span class="trip-price-pill main"><i class="fas fa-tag"></i>${parseFloat(t.final_price).toFixed(0)} ج.م</span>`
          : `<span class="trip-price-pill main"><i class="fas fa-gift"></i> مجانية</span>`;
        const myReg = t.my_registration;
        const remOverlay = myReg && parseFloat(myReg.remaining) > 0
          ? `<span class="trip-price-pill remaining"><i class="fas fa-exclamation-circle"></i>متبقي ${parseFloat(myReg.remaining).toFixed(0)} ج.م</span>`
          : '';
        // Kids avatars strip
        const canSeeKids = String(t.show_registered_kids ?? 1) === '1';
        const kids = canSeeKids ? (t.registered_kids || []).slice(0, 6) : [];
        const extra = (t.registered_count || 0) - kids.length;
        const kidsHtml = kids.length ? `<div class="kids-strip">
      ${kids.map(k => `<div class="ka">${k.image_url ? `<img src="${esc(k.image_url)}" alt="${esc(k.name)}">` : k.name.charAt(0)}</div>`).join('')}
      ${extra > 0 ? `<div class="ka ka-more">+${extra}</div>` : ''}
    </div>`: '';
        // "Contact uncle" button if this student is NOT registered
        const notRegistered = !myReg;
        const contactBtn = notRegistered && classUncles.length
          ? `<div class="trip-contact-bar" onclick="event.stopPropagation();openTripContactUncle(${t.id})">
          <i class="fas fa-exclamation-circle"></i>
          <span>اسمك غير موجود في قائمة الرحلة</span>
          <span class="trip-contact-action"><i class="fas fa-comments"></i> تواصل مع المدرّس</span>
         </div>`
          : notRegistered
            ? `<div class="trip-contact-bar unreach">
            <i class="fas fa-info-circle"></i>
            <span>اسمك غير موجود في قائمة الرحلة</span>
           </div>`
            : '';
        return `<div class="trip-card" onclick="openTrip(${t.id})">
      <div class="trip-thumb-wrap">
        ${thumb}${ph}
        <div class="trip-status-overlay ts-${t.status}">${stLbl[t.status] || t.status}</div>
        <div class="trip-price-overlay">${priceOverlay}${remOverlay}</div>
      </div>
      <div class="trip-body">
        <div class="trip-title">${esc(t.title)}</div>
        ${t.description ? `<div class="trip-desc">${esc(t.description)}</div>` : ''}
        <div class="trip-meta-row">
          ${t.start_date_formatted ? `<span class="trip-meta-chip"><i class="fas fa-calendar"></i>${t.start_date_formatted}</span>` : ''}
          <span class="trip-meta-chip"><i class="fas fa-users"></i>${t.registered_count || 0} مسجّل</span>
          ${typeof t.my_points !== 'undefined' ? `<span class="trip-meta-chip"><i class="fas fa-star"></i> ${esc(t.my_points)} نقاط</span>` : ''}
          ${t.max_participants ? `<span class="trip-meta-chip"><i class="fas fa-user-check"></i>أقصى ${t.max_participants}</span>` : ''}
        </div>
        ${canSeeKids ? kidsHtml : ''}
        ${contactBtn}
      </div>
    </div>`;
      }).join('');
    }
    function openTrip(id) {
      const t = allTrips.find(x => x.id == id); if (!t) return;
      document.getElementById('tripOvTitle').textContent = t.title;
      document.getElementById('tripOvSub').textContent = `${t.registered_count || 0} مسجّل`;
      const stLbl = { planned: 'مخطط', active: 'نشط', completed: 'مكتمل', cancelled: 'ملغي' };
      const myReg = t.my_registration;
      const myRegHtml = myReg ? `<div class="my-trip-box">
    <div class="my-trip-title"><i class="fas fa-check-circle"></i> أنت مسجّل في هذه الرحلة</div>
    <div class="my-trip-row">
      <div class="mtr-cell ok"><div class="mtr-val">${parseFloat(myReg.total_paid).toFixed(0)} ج.م</div><div class="mtr-lbl">المدفوع</div></div>
      <div class="mtr-cell${parseFloat(myReg.remaining) > 0 ? ' warn' : 'ok'}"><div class="mtr-val">${parseFloat(myReg.remaining).toFixed(0)} ج.م</div><div class="mtr-lbl">المتبقي</div></div>
    </div>
  </div>`: '';
      // Not-registered contact block
      const notRegistered = !myReg;
      const contactHtml = notRegistered ? buildTripContactHtml(t) : '';
      const canSeeKids = String(t.show_registered_kids ?? 1) === '1';
      const kids = canSeeKids ? (t.registered_kids || []) : [];
      const kidsHtml = !canSeeKids ? '' : (kids.length ? `
    <div style="margin-bottom:5px;font-size:.78rem;font-weight:700;color:var(--t2);">${kids.length} مستخدم مسجّل</div>
    <div class="kids-grid">
      ${kids.map(k => `<div class="kid-tile user-tile">
        <div class="kid-tile-av">${k.image_url ? `<img src="${esc(k.image_url)}" alt="${esc(k.name)}">` : k.name.charAt(0)}</div>
        <div class="kid-tile-name">${esc(k.name)}</div>
        <div class="kid-tile-cls">${esc(k.class || '')}</div>
        <div class="user-role">${esc(((k.church_type||t.church_type||localStorage.getItem('churchType')||'kids')==='youth')?((k.gender||'').toString().toLowerCase()==='female'?'شابة':'شاب'):'طفل')}</div>
      </div>`).join('')}
    </div>`: `<div class="empty-st"><i class="fas fa-user-slash"></i><p>لا يوجد مستخدمون مسجّلون بعد</p></div>`);
      document.getElementById('tripOvBody').innerHTML = `
    ${t.image_url ? `<img class="trip-detail-thumb" src="${esc(t.image_url)}" alt="">` :
          `<div class="trip-detail-ph"><i class="fas fa-bus"></i></div>`}
    ${contactHtml}
    ${myRegHtml}
    <div style="display:flex;flex-wrap:wrap;gap:7px;margin-bottom:14px;">
      <span style="padding:4px 12px;border-radius:var(--r-full);font-size:.74rem;font-weight:700;background:var(--ok-bg);color:var(--ok);">
        <i class="fas fa-tag"></i> ${parseFloat(t.final_price) > 0 ? parseFloat(t.final_price).toFixed(0) + ' ج.م' : 'مجانية'}
      </span>
      ${t.start_date_formatted ? `<span style="padding:4px 12px;border-radius:var(--r-full);font-size:.74rem;font-weight:600;background:var(--s2);border:1px solid var(--bdr);"><i class="fas fa-calendar"></i> ${t.start_date_formatted}</span>` : ''}
    </div>
    ${t.description ? `<p style="font-size:.83rem;color:var(--t3);line-height:1.6;margin-bottom:14px;">${esc(t.description)}</p>` : ''}
    ${kidsHtml}
  `;
      openOv('tripOv');
    }

    // Build the "not registered" contact block for inside the trip detail modal
    function buildTripContactHtml(t) {
      if (!classUncles.length) {
        return `<div style="margin-bottom:14px;padding:12px 16px;background:#fef3c7;border:1.5px solid #fde68a;border-radius:var(--r-md);display:flex;align-items:center;gap:10px;font-size:.82rem;font-weight:600;color:#92400e;">
      <i class="fas fa-exclamation-triangle"></i>
      <span>اسمك غير موجود في قائمة هذه الرحلة — تواصل مع المسؤول</span>
    </div>`;
      }
      const uncleButtons = classUncles.map(u => {
        const hasPhone = u.phone && u.phone.trim();
        const waPhone = hasPhone ? '20' + u.phone.trim().replace(/^0/, '') : '';
        const waMsg = encodeURIComponent('مرحباً، أنا ' + ((student && student.name) || '') + ' — اسمي غير موجود في قائمة رحلة "' + t.title + '"، هل يمكن تسجيلي؟');
        return `<div style="display:flex;gap:8px;align-items:center;padding:10px 14px;background:var(--surf);border:1.5px solid var(--bdr);border-radius:var(--r-md);">
      <div style="width:34px;height:34px;border-radius:50%;background:var(--brand-bg);color:var(--brand);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:800;flex-shrink:0;overflow:hidden;">
        ${u.image_url ? `<img src="${esc(u.image_url)}" style="width:100%;height:100%;object-fit:cover;" alt="${esc(u.name)}">` : u.name.charAt(0)}
      </div>
      <div style="flex:1;min-width:0;">
        <div style="font-size:.84rem;font-weight:700;color:var(--t1);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(u.name)}</div>
        <div style="font-size:.68rem;color:var(--t4);">مدرّس الفصل</div>
      </div>
      <div style="display:flex;gap:6px;flex-shrink:0;">
        ${hasPhone ? `<a href="tel:${esc(u.phone.trim())}" style="width:34px;height:34px;border-radius:var(--r-sm);background:#d1fae5;color:#059669;display:flex;align-items:center;justify-content:center;text-decoration:none;font-size:.85rem;" title="اتصال"><i class="fas fa-phone-alt"></i></a>
        <a href="https://wa.me/${waPhone}?text=${waMsg}" target="_blank" style="width:34px;height:34px;border-radius:var(--r-sm);background:#dcfce7;color:#16a34a;display:flex;align-items:center;justify-content:center;text-decoration:none;font-size:.9rem;" title="واتساب"><i class="fab fa-whatsapp"></i></a>`
            : `<span style="font-size:.7rem;color:var(--t4);padding:4px 8px;background:var(--s2);border-radius:var(--r-sm);white-space:nowrap;">لا يوجد رقم</span>`}
      </div>
    </div>`;
      }).join('');
      return `<div style="margin-bottom:14px;padding:12px 14px;background:#fef3c7;border:1.5px solid #fde68a;border-radius:var(--r-md) var(--r-md) 0 0;display:flex;align-items:center;gap:9px;font-size:.82rem;font-weight:700;color:#92400e;">
    <i class="fas fa-exclamation-triangle"></i>
    <span>اسمك غير موجود في القائمة — تواصل مع مدرّسك</span>
  </div>
  <div style="margin-bottom:14px;display:flex;flex-direction:column;gap:7px;padding:0 2px;">
    ${uncleButtons}
  </div>`;
    }

    // Open uncle contact from trip card "تواصل" button (card-level, not modal)
    function openTripContactUncle(tripId) {
      const t = allTrips.find(x => x.id == tripId);
      if (!t) return;
      // Build a lightweight bottom sheet using the existing uncleOv
      if (classUncles.length === 1) {
        openUncleDrawer(classUncles[0].id);
      } else {
        // Multiple uncles — show the uncle section or open the first uncle drawer
        openUncleDrawer(classUncles[0].id);
      }
    }

    // ── Announcements ─────────────────────────────────────────────────
    let allAnnouncements = [];
    let latestBannerAnnId = 0;

    async function loadAnn() {
      if (!student) return;
      try {
        const d = await api({ action: 'getAnnouncementsForStudent', churchId: student.church_id || 1, studentClass: student.class, studentName: student.name });
        const anns = (d.success && Array.isArray(d.announcements)) ? d.announcements : [];
        allAnnouncements = anns;

        // 1. Handle navigation tab badge for tasks
        const badge = document.getElementById('tasksBadge');
        if (badge && !isViewingOther() && document.querySelector('.bottom-nav-item.active')?.getAttribute('data-tab') !== 'tasks') {
          const taskAnns = anns.filter(ann => ann.text && (ann.text.includes('مهمة') || ann.text.includes('تصحيح') || ann.text.includes('الكوبونات')));
          maxFetchedTaskAnnId = taskAnns.length ? Math.max(...taskAnns.map(ann => parseInt(ann.id) || 0)) : 0;
          const lastViewedId = parseInt(localStorage.getItem('tasksLastViewedAnnId_' + student.id) || '0');
          const hasNewTaskAnn = taskAnns.some(ann => (parseInt(ann.id) || 0) > lastViewedId);
          badge.style.display = hasNewTaskAnn ? 'block' : 'none';
        }

        const banner = document.getElementById('scAnnBanner');
        const bannerList = document.getElementById('annBannerList');
        const card = document.getElementById('scAnn');
        const cardList = document.getElementById('annList');
        const annSub = document.getElementById('annSub');
        const annBadge = document.getElementById('annBadge');

        // 2. Render the Announcements Section Card (if present)
        const dismissedIds = JSON.parse(localStorage.getItem('dismissedAnns_' + student.id) || '[]');
        const activeAnns = anns.filter(ann => !dismissedIds.includes(parseInt(ann.id)));

        if (activeAnns.length > 0) {
          if (annSub) annSub.textContent = `${activeAnns.length} إعلان${activeAnns.length > 1 ? 'ات' : ''} موجه ليك`;
          if (annBadge) annBadge.textContent = String(activeAnns.length);

          const latestCard = activeAnns[0];
          const buttonCount = activeAnns.filter(a => a.type === 'button' && a.link).length;
          const messageCount = activeAnns.length - buttonCount;
          const remainingAnns = activeAnns.slice(1);

          let remainingHtml = '';
          if (remainingAnns.length > 0) {
            remainingHtml = `
              <div style="font-weight: 800; font-size: 0.82rem; color: var(--text-3); margin: 15px 0 10px 0; border-bottom: 1.5px solid var(--bdr); padding-bottom: 6px;">الإعلانات السابقة</div>
              <div class="ann-list">
                ${remainingAnns.map(a => `
                  <div class="ann-item">
                    <div class="ann-top">
                      <span class="ann-type ${a.type === 'button' ? 'link' : ''}">
                        <i class="fas fa-${a.type === 'button' ? 'link' : 'comment-dots'}"></i>
                        ${a.type === 'button' ? 'إعلان برابط' : 'إعلان نصي'}
                      </span>
                      <span class="ann-date">${fmtDate(a.created_at)}</span>
                    </div>
                    <div class="ann-text">${esc(a.text)}</div>
                    <div class="ann-footer">
                      <span class="ann-meta-pill"><i class="fas fa-bullhorn"></i> من الكنيسة أو خدام الفصل</span>
                      ${a.type === 'button' && a.link ? `<a href="${esc(a.link)}" target="_blank" class="ann-link-btn"><i class="fas fa-external-link-alt"></i> فتح الإعلان</a>` : ''}
                      <button onclick="dismissSingleAnn(${a.id})" style="margin-right:auto; background:none; border:none; color:var(--danger, #ef4444); cursor:pointer; font-size:.78rem; font-weight:800; display:flex; align-items:center; gap:4px; padding:4px 8px; border-radius:4px;"><i class="fas fa-eye-slash"></i> إخفاء</button>
                    </div>
                  </div>`).join('')}
              </div>
            `;
          }

          if (cardList) {
            cardList.innerHTML = `
          <div class="ann-shell">
            <div class="ann-summary">
              <div class="ann-highlight">
                <div class="ann-highlight-badge"><i class="fas fa-sparkles"></i> أحدث إعلان</div>
                <div class="ann-highlight-title">${latestCard.type === 'button' ? 'إعلان فيه رابط مباشر' : 'رسالة جديدة ليك'}</div>
                <div class="ann-highlight-text">${esc(latestCard.text)}</div>
                <div class="ann-highlight-meta">
                  <span class="ann-type ${latestCard.type === 'button' ? 'link' : ''}">
                    <i class="fas fa-${latestCard.type === 'button' ? 'link' : 'comment-dots'}"></i>
                    ${latestCard.type === 'button' ? 'رابط سريع' : 'رسالة'}
                  </span>
                  <span class="ann-meta-pill"><i class="fas fa-clock"></i>${fmtDate(latestCard.created_at)}</span>
                  ${latestCard.type === 'button' && latestCard.link ? `<a href="${esc(latestCard.link)}" target="_blank" class="ann-link-btn"><i class="fas fa-arrow-up-right-from-square"></i> فتح الرابط</a>` : ''}
                  <button onclick="dismissSingleAnn(${latestCard.id})" style="margin-right:auto; background:none; border:none; color:var(--danger, #ef4444); cursor:pointer; font-size:.78rem; font-weight:800; display:flex; align-items:center; gap:4px; padding:4px 8px; border-radius:4px;"><i class="fas fa-eye-slash"></i> إخفاء</button>
                </div>
              </div>
              <div class="ann-summary-card">
                <div class="ann-stat-grid">
                  <div class="ann-stat">
                    <span class="ann-stat-val">${activeAnns.length}</span>
                    <span class="ann-stat-lbl">إجمالي الإعلانات</span>
                  </div>
                  <div class="ann-stat">
                    <span class="ann-stat-val">${buttonCount}</span>
                    <span class="ann-stat-lbl">روابط سريعة</span>
                  </div>
                  <div class="ann-stat">
                    <span class="ann-stat-val">${messageCount}</span>
                    <span class="ann-stat-lbl">رسائل</span>
                  </div>
                  <div class="ann-stat">
                    <span class="ann-stat-val">${student.class ? esc(student.class) : 'عام'}</span>
                    <span class="ann-stat-lbl">الفصل الحالي</span>
                  </div>
                </div>
              </div>
            </div>
            ${remainingHtml}
          </div>`;
          }
          if (card && document.querySelector('.bottom-nav-item.active')?.getAttribute('data-tab') === 'home') {
            card.style.display = 'block';
          }
        } else {
          if (cardList) {
            cardList.innerHTML = `
            <div class="ann-empty">
              <i class="fas fa-bullhorn"></i>
              <strong>لا توجد إعلانات حالياً</strong>
              <span>أول ما ينزل إعلان جديد من الخدام أو الكنيسة هتلاقيه هنا.</span>
            </div>`;
          }
          if (annSub) annSub.textContent = 'لا توجد تحديثات جديدة الآن';
          if (annBadge) annBadge.textContent = '0';
          if (card) card.style.display = 'none';
        }

        // 3. Handle top announcement notification banner
        if (!activeAnns.length) {
          if (banner) banner.style.display = 'none';
          return;
        }

        const latest = activeAnns[0];
        latestBannerAnnId = parseInt(latest.id);

        let linkBtn = '';
        if (latest.type === 'button' && latest.link) {
          linkBtn = `<a href="${esc(latest.link)}" target="_blank" style="display:inline-flex;align-items:center;gap:4px;background:var(--brand);color:#fff;padding:4px 10px;border-radius:var(--r-xs);text-decoration:none;font-weight:800;font-size:.74rem;margin-top:6px;width:fit-content;"><i class="fas fa-external-link-alt"></i> فتح الرابط</a>`;
        }

        if (bannerList) {
          bannerList.innerHTML = `
            <div style="font-weight:700;font-size:.84rem;color:var(--t1);">${esc(latest.text)}</div>
            ${linkBtn}
          `;
        }

        if (banner && document.querySelector('.bottom-nav-item.active')?.getAttribute('data-tab') === 'home') {
          banner.style.display = 'block';
        }
      } catch (e) {
        const banner = document.getElementById('scAnnBanner');
        if (banner) banner.style.display = 'none';
      }
    }

    function dismissSingleAnn(annId) {
      if (!student) return;
      const dismissedIds = JSON.parse(localStorage.getItem('dismissedAnns_' + student.id) || '[]');
      if (!dismissedIds.includes(parseInt(annId))) {
        dismissedIds.push(parseInt(annId));
        localStorage.setItem('dismissedAnns_' + student.id, JSON.stringify(dismissedIds));
      }
      loadAnn();
    }

    function dismissAnnBanner() {
      if (!student) return;
      const dismissedIds = JSON.parse(localStorage.getItem('dismissedAnns_' + student.id) || '[]');
      if (latestBannerAnnId && !dismissedIds.includes(latestBannerAnnId)) {
        dismissedIds.push(latestBannerAnnId);
        localStorage.setItem('dismissedAnns_' + student.id, JSON.stringify(dismissedIds));
      }
      loadAnn();
    }

    // ── Edit / Password / Photo ───────────────────────────────────────
    async function saveProfile() {
      if (isViewingOther()) {
        toast('غير مسموح في وضع المعاينة', 'err');
        return;
      }
      const n = document.getElementById('eN').value.trim();
      if (!n) { toast('أدخل الاسم', 'err'); return; }
      try {
        const d = await api({ action: 'updateStudentInfo', studentId: student.id, name: n, address: document.getElementById('eA').value.trim(), phone: document.getElementById('eP').value.trim(), birthday: document.getElementById('eB').value.trim() });
        if (d.success) {
          student.name = n; student.address = document.getElementById('eA').value.trim();
          student.phone = document.getElementById('eP').value.trim(); student.birthday = document.getElementById('eB').value.trim();
          document.getElementById('heroName').textContent = n;
          updateBirthdayGreetingButton(student);
          renderInfo(student, false); closeOv('editOv'); toast('تم الحفظ ✓', 'ok');
        } else toast(d.message || 'فشل', 'err');
      } catch (e) { toast('خطأ في الاتصال', 'err'); }
    }
    // ── Sync passOv between "add" and "change" modes ─────────────────────
    function syncPassOverlay() {
      if (!student) return;
      const isAdd = !student.has_password;
      const title   = document.getElementById('passOvTitle');
      const btnLbl  = document.getElementById('passOvBtn');
      const menuLbl = document.getElementById('passMenuLabel');
      const note    = document.getElementById('passAddNote');
      const oldWrap = document.getElementById('passOldWrap');
      if (title)   title.textContent  = isAdd ? 'إضافة كلمة مرور'  : 'تغيير كلمة المرور';
      if (btnLbl)  btnLbl.textContent  = isAdd ? 'إضافة كلمة المرور' : 'تغيير كلمة المرور';
      if (menuLbl) menuLbl.textContent = isAdd ? 'إضافة كلمة مرور'  : 'تغيير كلمة المرور';
      if (note)    note.style.display  = isAdd ? 'block' : 'none';
      if (oldWrap) oldWrap.style.display = isAdd ? 'none' : 'block';
    }

    async function changePass() {
      if (isViewingOther()) {
        toast('غير مسموح في وضع المعاينة', 'err');
        return;
      }
      const isAdd = !student.has_password;
      const o = document.getElementById('po').value.trim();
      const n = document.getElementById('pn').value.trim();
      const c = document.getElementById('pc').value.trim();
      if (!n || !c) { toast('أكمل الحقول المطلوبة', 'err'); return; }
      if (!isAdd && !o) { toast('أدخل كلمة المرور الحالية', 'err'); return; }
      if (n !== c) { toast('كلمة المرور غير متطابقة', 'err'); return; }
      if (n.length < 6) { toast('٦ أحرف على الأقل', 'err'); return; }
      try {
        const d = await api({
          action: 'changeStudentPassword',
          phone: localStorage.getItem('savedUsername') || student.phone,
          studentId: student.id,
          oldPassword: o,
          newPassword: n,
          isAdd: isAdd ? 'true' : 'false'
        });
        if (d.success) {
          // Update localStorage password so next auto-login works
          localStorage.setItem('savedPassword', n);
          // Mark student as having a password now
          student.has_password = true;
          // Update all account copies
          const accRef = allAccounts.find(a => a.id === student.id);
          if (accRef) accRef.has_password = true;
          closeOv('passOv');
          syncPassOverlay();
          toast(d.message || 'تم ✓', 'ok');
          ['po', 'pn', 'pc'].forEach(id => document.getElementById(id).value = '');
        } else toast(d.message || 'فشل', 'err');
      } catch (e) { toast('خطأ في الاتصال', 'err'); }
    }

    function onPhoto(e) {
      const file = e.target.files[0]; if (!file) return;
      const reader = new FileReader();
      reader.onload = ev => {
        document.getElementById('dropZone').style.display = 'none';
        document.getElementById('cropWrap').style.display = 'block';
        document.getElementById('cropBtn').style.display = 'inline-flex';
        const img = document.getElementById('cropImg'); img.src = ev.target.result;
        if (cropper) cropper.destroy();
        setTimeout(() => { cropper = new Cropper(img, { aspectRatio: 1, viewMode: 2, dragMode: 'move', autoCropArea: .85, guides: false }); }, 100);
      };
      reader.readAsDataURL(file);
    }
    function doCrop() {
      if (!cropper) return;
      cropper.getCroppedCanvas({ width: 400, height: 400, imageSmoothingQuality: 'high' }).toBlob(blob => {
        croppedBlob = blob;
        const prev = document.getElementById('photoPrev');
        prev.src = URL.createObjectURL(blob); prev.style.display = 'block';
        document.getElementById('dropZone').style.display = 'none';
        document.getElementById('cropWrap').style.display = 'none';
        document.getElementById('cropBtn').style.display = 'none';
        document.getElementById('uploadBtn').style.display = 'inline-flex';
        cropper.destroy(); cropper = null; toast('تم القص ✓', 'ok');
      }, 'image/jpeg', .9);
    }
    async function uploadPhoto() {
      if (isViewingOther()) {
        toast('غير مسموح في وضع المعاينة', 'err');
        return;
      }
      if (!croppedBlob) { toast('اختر صورة أولاً', 'err'); return; }
      showLoad('جارٍ رفع الصورة…');
      const fd = new FormData();
      fd.append('photo', new File([croppedBlob], `profile_${student.phone}_${Date.now()}.jpg`, { type: 'image/jpeg' }));
      fd.append('studentId', student.id); fd.append('studentName', student.name);
      fd.append('studentPhone', student.phone); fd.append('studentClass', student.class); fd.append('churchId', student.church_id || 1);
      try {
        const r = await fetch('/upload.php', { method: 'POST', body: fd, headers: { Accept: 'application/json' } });
        const up = await r.json();
        if (!up.success) throw new Error(up.message || 'فشل رفع الملف');
        const d = await api({ action: 'updateStudentImageAfterCreation', studentId: student.id, imageUrl: up.imageUrl });
        if (!d.success) throw new Error(d.message || 'فشل حفظ الرابط');
        const savedUrl = d.imageUrl || up.imageUrl;
        // Refresh student data from server
        const fresh = await api({ action: 'getStudentProfile', studentId: student.id });
        if (fresh.success && (fresh.student || fresh.user)) student = norm(fresh.student || fresh.user);
        hideLoad();
        document.getElementById('avatarInner').innerHTML = `<img src="${savedUrl}?t=${Date.now()}" alt="">`;
        closeOv('photoOv'); resetPhoto(); toast('تم رفع الصورة ✓', 'ok');
      } catch (e) { hideLoad(); toast('خطأ: ' + e.message, 'err'); }
    }
    function resetPhoto() {
      document.getElementById('dropZone').style.display = 'block';
      document.getElementById('cropWrap').style.display = 'none';
      document.getElementById('cropBtn').style.display = 'none';
      document.getElementById('uploadBtn').style.display = 'none';
      document.getElementById('photoPrev').style.display = 'none';
      document.getElementById('photoIn').value = '';
      if (cropper) { cropper.destroy(); cropper = null; } croppedBlob = null;
    }

    // ── Account switch ────────────────────────────────────────────────
    document.getElementById('switchBtnTop').addEventListener('click', () => {
      selAccId = student.id;
      document.getElementById('switchList').innerHTML = allAccounts.map(a => `
    <div class="acc-item${a.id === student.id ? ' active' : ''}" data-id="${a.id}" onclick="pickAcc(${a.id})">
      <div class="acc-av">${a.image_url ? `<img src="${esc(a.image_url)}" alt="">` : a.name.charAt(0)}</div>
      <div><div class="acc-name">${esc(a.name)}</div><div class="acc-cls"><i class="fas fa-graduation-cap"></i> ${esc(a.class)}</div></div>
      ${a.id === student.id ? '<i class="fas fa-check-circle" style="color:var(--brand);margin-right:auto;"></i>' : ''}
    </div>`).join('');
      openOv('switchOv');
    });
    function pickAcc(id) {
      if (!id || id === student.id) { closeOv('switchOv'); return; }
      const acc = allAccounts.find(a => a.id === id); if (!acc) return;
      student = acc;
      localStorage.setItem('activeKidAccountId', String(acc.id));
      renderPrivate(student);
      switchTab('home');
      loadSiblings();
      syncPassOverlay();
      document.getElementById('bottomNavBar').style.display = 'flex';
      closeOv('switchOv');
      toast(`تم التبديل إلى ${acc.name} ✓`, 'ok');
      if (!IS_PUBLIC && _creds) {
        _initPushNotifications();
      }
    }

    // ── Logout ────────────────────────────────────────────────────────
    function doLogout() {
      closeOv('settingsOv');
      ['savedUsername', 'savedPassword', 'rememberMe', 'userPhone', 'loginType'].forEach(k => localStorage.removeItem(k));
      const fd = new FormData(); fd.append('action', 'logout');
      fetch(location.href, { method: 'POST', body: fd }).finally(() => location.href = '/user/login');
    }

    // ── UI helpers ────────────────────────────────────────────────────
    function openOv(id) {
      if (['settingsOv', 'editOv', 'passOv', 'photoOv'].includes(id) && isViewingOther()) {
        toast('غير مسموح في وضع المعاينة', 'err');
        return;
      }
      const ov = document.getElementById(id);
      ov.classList.add('open');
      document.documentElement.classList.add('ov-open');
      const sheet = ov.querySelector('.settings-sheet');
    }
    function closeOv(id) {
      const ov = document.getElementById(id);
      ov.classList.remove('open');
      // Only remove ov-open if no other overlay is still open
      if (!document.querySelector('.overlay.open')) document.documentElement.classList.remove('ov-open');
      const sheet = ov.querySelector('.settings-sheet');
      if (sheet) { clearTimeout(sheet._sst); sheet.classList.remove('ss-scrollable'); }
    }
    function setupOvClose() {
      document.querySelectorAll('.overlay').forEach(ov => {
        ov.addEventListener('click', e => { if (e.target === ov) closeOv(ov.id); });
      });
      document.addEventListener('keydown', e => {
        if (e.key === 'Escape') document.querySelectorAll('.overlay.open').forEach(ov => closeOv(ov.id));
      });
    }
    function showMain() { document.getElementById('mainPage').style.display = 'block'; }
    function showLoad(m = 'جارٍ التحميل…') { document.getElementById('lt').textContent = m; document.getElementById('ls').classList.remove('hidden'); }
    function hideLoad() { document.getElementById('ls').classList.add('hidden'); }
    function noProfile(m) { hideLoad(); document.getElementById('noMsg').textContent = m; document.getElementById('noProfile').style.display = 'block'; }
    function toast(m, t = 'info') {
      const tc = document.getElementById('tc');
      const el = document.createElement('div'); el.className = `toast ${t}`;
      const ic = t === 'ok' ? 'fa-check-circle' : t === 'err' ? 'fa-exclamation-circle' : 'fa-info-circle';
      el.innerHTML = `<i class="fas ${ic}"></i>${m}`;
      tc.appendChild(el);
      requestAnimationFrame(() => requestAnimationFrame(() => el.classList.add('show')));
      setTimeout(() => { el.classList.remove('show'); setTimeout(() => el.remove(), 350); }, 3200);
    }
    function tPass(id, btn) {
      const inp = document.getElementById(id); const show = inp.type === 'password';
      inp.type = show ? 'text' : 'password';
      btn.querySelector('i').className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
    }
    function esc(s) { return String(s || '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;'); }
    function fmtDate(iso) {
      if (!iso) return '—';
      try { return new Date(iso).toLocaleDateString('ar-EG', { day: 'numeric', month: 'short', year: 'numeric' }); } catch (e) { return iso; }
    }
    function openModal(html) {
      const ov = document.getElementById('genericModalOv');
      if (!ov) {
        const div = document.createElement('div');
        div.id = 'genericModalOv';
        div.className = 'overlay';
        div.style.zIndex = '3000';
        div.innerHTML = `
      <div class="modal" style="max-width:600px;margin-top:40px;">
        <div class="mhdr" style="background:var(--brand);padding:15px 20px;border-radius:var(--r-xl) var(--r-xl) 0 0;display:flex;align-items:center;justify-content:space-between;">
          <div id="genericModalTitle" style="color:#fff;font-weight:800;font-size:1rem;">مراجعة إجاباتي</div>
          <button onclick="closeModal()" style="background:rgba(255,255,255,.2);border:none;color:#fff;width:30px;height:30px;border-radius:8px;cursor:pointer;"><i class="fas fa-times"></i></button>
        </div>
        <div class="mbody" id="genericModalBody" style="padding:0;"></div>
      </div>
    `;
        document.body.appendChild(div);
        div.onclick = (e) => { if (e.target === div) closeModal(); };
        div.classList.add('open');
      } else {
        ov.classList.add('open');
      }
      document.getElementById('genericModalBody').innerHTML = html;
      document.documentElement.classList.add('ov-open');
    }

    function closeModal() {
      const ov = document.getElementById('genericModalOv');
      if (ov) ov.classList.remove('open');
      if (!document.querySelector('.overlay.open')) document.documentElement.classList.remove('ov-open');
    }

    function viewMyAnswers(taskId) {
      const t = allTasks.find(x => x.id == taskId);
      if (!t || !t.my_submission) return;
      const sub = t.my_submission;

      // answers: the student's choices (keyed by question id)
      const ans = typeof sub.answers === 'string' ? JSON.parse(sub.answers) : (sub.answers || {});
      // correct_answers: map of {qId: correctIndex} — provided by API when show_answers=1
      const correctMap = sub.correct_answers || {};
      // open_scores: map of {qId: score} for open questions graded by uncle
      const openScores = typeof sub.open_scores === 'string' ? JSON.parse(sub.open_scores || '{}') : (sub.open_scores || {});
      // correction_notes: map of {qId: noteText} for questions graded by uncle
      const corrNotes = typeof sub.correction_notes === 'string' ? JSON.parse(sub.correction_notes || '{}') : (sub.correction_notes || {});

      let html = `<div style="padding:20px;max-height:75vh;overflow-y:auto;background:var(--bg);">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;padding:15px;background:#fff;border-radius:var(--r-md);box-shadow:var(--sh-sm);">
      <div style="width:50px;height:50px;border-radius:50%;background:var(--brand-bg);color:var(--brand);display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;"><i class="fas fa-clipboard-check"></i></div>
      <div style="flex:1;">
        <div style="font-weight:800;color:var(--t1);font-size:1.1rem;line-height:1.2;">${esc(t.title)}</div>
        <div style="font-size:.78rem;color:var(--t3);margin-top:2px;">لقد حصلت على ${sub.score} من ${t.total_degree} درجة</div>
      </div>
    </div>`;

      if (!t.questions || t.questions.length === 0) {
        html += `<div style="text-align:center;padding:40px;color:var(--t4);">لا توجد أسئلة لهذه المهمة.</div>`;
      } else {
        t.questions.forEach((q, i) => {
          const qType = q.question_type || 'mcq';
          const qId = String(q.id);
          const given = ans[qId] !== undefined ? ans[qId] : ans[q.id];
          // Use correct_answers map first (returned by API), fall back to q.correct_index
          const correctIdx = (correctMap[q.id] !== undefined)
            ? parseInt(correctMap[q.id])
            : (q.correct_index !== null && q.correct_index !== undefined ? parseInt(q.correct_index) : null);
          const isCorrect = given !== undefined && correctIdx !== null && parseInt(given) === correctIdx;

          html += `<div style="margin-bottom:15px;padding:15px;border:1.5px solid var(--bdr);border-radius:var(--r-md);background:#fff;box-shadow:var(--sh-sm);">`;
          html += `<div style="display:flex;gap:10px;margin-bottom:12px;">
        <div style="width:26px;height:26px;border-radius:8px;background:var(--s2);color:var(--t1);display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:800;flex-shrink:0;">${i + 1}</div>
        <div style="font-weight:700;color:var(--t1);line-height:1.4;flex:1;">${esc(q.question_text)}</div>
      </div>`;

          if (qType === 'open') {
            const openScore = openScores[qId] !== undefined ? openScores[qId] : (openScores[q.id] !== undefined ? openScores[q.id] : null);
            let openScoreHtml;
            if (openScore !== null) {
              const deg = q.degree || 1;
              const pct = Math.round((openScore / deg) * 100);
              let colorVar = 'var(--ok)';
              // Color bands: high -> green, mid -> orange, low -> yellow
              if (pct >= 80) colorVar = 'var(--ok)';
              else if (pct >= 50) colorVar = 'var(--warn-l)';
              else colorVar = 'var(--gold-l)';
              openScoreHtml = `<div style="margin-top:8px;font-size:.75rem;color:${colorVar};font-weight:700;"><i class="fas fa-check-circle"></i> درجتك: ${openScore} من ${deg}</div>`;
            } else {
              openScoreHtml = `<div style="margin-top:8px;font-size:.75rem;color:var(--warn);font-weight:700;"><i class="fas fa-clock"></i> في انتظار التصحيح</div>`;
            }
            html += `<div style="background:var(--s2);padding:15px;border-radius:var(--r-sm);border:1.5px solid var(--bdr);">
          <div style="font-size:.7rem;color:var(--t3);margin-bottom:6px;font-weight:700;">إجابتك المسجلة:</div>
          <div style="color:var(--t2);font-size:.9rem;white-space:pre-wrap;line-height:1.6;">${esc(given !== undefined ? String(given) : '— لم تُجب على هذا السؤال —')}</div>
          ${openScoreHtml}
        </div>`;
          } else {
            const opts = typeof q.options === 'string' ? JSON.parse(q.options) : (q.options || []);
            if (qType === 'tf') { opts[0] = 'صحيح'; opts[1] = 'خطأ'; }

            html += `<div style="display:flex;flex-direction:column;gap:8px;">`;
            opts.forEach((o, j) => {
              const isCorr = j === correctIdx;
              const isSel = given !== undefined && parseInt(given) === j;

              let borderColor = 'var(--bdr)';
              let bgColor = 'var(--surf)';
              let textColor = 'var(--t2)';
              let icon = '';

              if (isCorr && isSel) {
                borderColor = 'var(--ok)'; bgColor = 'var(--ok-bg)'; textColor = 'var(--ok)';
                icon = '<i class="fas fa-check-circle" style="margin-right:auto;"></i>';
              } else if (isCorr) {
                borderColor = 'var(--ok)'; bgColor = 'var(--ok-bg)'; textColor = 'var(--ok)';
                icon = '<i class="fas fa-check" style="margin-right:auto;opacity:.5;"></i>';
              } else if (isSel) {
                borderColor = 'var(--err)'; bgColor = 'var(--err-bg)'; textColor = 'var(--err)';
                icon = '<i class="fas fa-times-circle" style="margin-right:auto;"></i>';
              }

              html += `<div style="display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:var(--r-sm);border:2px solid ${borderColor};background:${bgColor};color:${textColor};font-size:.88rem;${isSel ? 'font-weight:700;' : ''}">
            <span style="width:20px;font-weight:800;opacity:.5;">${LETTERS[j]}</span>
            <span>${esc(o)}</span>
            ${icon}
          </div>`;
            });
            html += `</div>`;

            // Show "didn't answer" notice if student skipped
            if (given === undefined) {
              html += `<div style="margin-top:8px;font-size:.75rem;color:var(--t4);font-weight:700;"><i class="fas fa-minus-circle"></i> لم تُجب على هذا السؤال</div>`;
            }
          }

          // Show correction note if present
          const note = corrNotes[qId] !== undefined ? corrNotes[qId] : (corrNotes[q.id] !== undefined ? corrNotes[q.id] : null);
          if (note && note.trim()) {
            html += `<div style="margin-top:12px;display:flex;gap:10px;padding:12px;background:#fffbeb;border:1.5px solid #fef3c7;border-radius:var(--r-sm);color:#92400e;align-items:flex-start;text-align:right;direction:rtl;">
              <div style="width:30px;height:30px;border-radius:50%;background:#fef3c7;display:flex;align-items:center;justify-content:center;color:#b45309;font-size:1rem;flex-shrink:0;"><i class="fas fa-comment-dots"></i></div>
              <div style="flex:1;">
                <div style="font-weight:700;font-size:.76rem;margin-bottom:2px;color:#b45309;">توضيح الأنكل / الإجابة الصحيحة:</div>
                <div style="font-size:.84rem;line-height:1.5;white-space:pre-wrap;">${esc(note)}</div>
              </div>
            </div>`;
          }

          html += `</div>`;
        });
      }
      html += `</div>`;

      openModal(html);
    }

    // ── V3 MOBILE APP LOGIC & WIZARD STEPS & GENIUS SEARCH ──────────────
    let selectedRecipientId = null;
    let selectedRecipientNameStr = "";
    let selectedSendCategory = "all";
    let wizardCurrentStep = 1;
    let hasSiblingsLoaded = false;

    function switchTab(tabName) {
      document.querySelectorAll('.bottom-nav-item').forEach(item => {
        item.classList.remove('active');
      });
      const activeItem = document.querySelector(`.bottom-nav-item[data-tab="${tabName}"]`);
      if (activeItem) activeItem.classList.add('active');

      const hero = document.querySelector('.hero');
      const statsBar = document.getElementById('statsBar');
      const scInfo = document.getElementById('scInfo');
      const scAnnBanner = document.getElementById('scAnnBanner');
      const scAnn = document.getElementById('scAnn');
      const homeSearchBar = document.getElementById('homeSearchBar');
      const scAtt = document.getElementById('scAtt');
      const scTasks = document.getElementById('scTasks');
      const scTrips = document.getElementById('scTrips');
      const scUncles = document.getElementById('scUncles');
      const scSiblings = document.getElementById('scSiblings');
      const scSendCoupons = document.getElementById('scSendCoupons');
      const mainPage = document.getElementById('mainPage');

      // Adjust mainPage padding/width for fullscreen tabs (send coupons & tasks)
      if (mainPage) {
        if (tabName === 'send' || tabName === 'tasks') {
          mainPage.style.padding = '0';
          mainPage.style.maxWidth = 'none';
        } else {
          mainPage.style.padding = '0 14px 90px';
          mainPage.style.maxWidth = '860px';
        }
      }

      if (hero) hero.style.display = 'none';
      if (statsBar) {
        statsBar.style.display = 'none';
        statsBar.style.marginTop = '0';
      }
      if (scInfo) scInfo.style.display = 'none';
      if (scAnnBanner) scAnnBanner.style.display = 'none';
      if (scAnn) scAnn.style.display = 'none';
      if (homeSearchBar) homeSearchBar.style.display = 'none';
      if (scAtt) scAtt.style.display = 'none';
      if (scTasks) scTasks.style.display = 'none';
      if (scTrips) scTrips.style.display = 'none';
      if (scUncles) scUncles.style.display = 'none';
      if (scSiblings) scSiblings.style.display = 'none';
      if (scSendCoupons) scSendCoupons.style.display = 'none';

      // Load correct tab contents
      if (tabName === 'home') {
        if (hero) hero.style.display = 'flex';
        if (scInfo) scInfo.style.display = 'block';
        if (scTrips) scTrips.style.display = 'block';
        
        // Show announcements banner at top if we have active announcements
        const dismissedIds = JSON.parse(localStorage.getItem('dismissedAnns_' + student.id) || '[]');
        const activeAnns = allAnnouncements.filter(ann => !dismissedIds.includes(parseInt(ann.id)));

        if (activeAnns.length > 0 && scAnnBanner) {
           scAnnBanner.style.display = 'block';
        }
        // Show announcements section card if we have any active announcements
        if (activeAnns.length > 0 && scAnn) {
           scAnn.style.display = 'block';
        }
        
        if (!IS_PUBLIC) {
           if (statsBar) statsBar.style.display = 'flex';
        }
      } else if (tabName === 'attendance') {
        if (scAtt) scAtt.style.display = 'block';
        if (statsBar) {
          statsBar.style.display = 'flex';
          statsBar.style.marginTop = '18px';
        }
      } else if (tabName === 'send') {
        if (scSendCoupons) {
           scSendCoupons.style.display = 'block';
           initSendWizard();
        }
      } else if (tabName === 'tasks') {
        if (scTasks) {
           scTasks.classList.add('fullscreen-tab');
           scTasks.style.display = 'block';
        }
        const badge = document.getElementById('tasksBadge');
        if (badge) badge.style.display = 'none';
        if (maxFetchedTaskAnnId > 0 && student) {
           localStorage.setItem('tasksLastViewedAnnId_' + student.id, String(maxFetchedTaskAnnId));
        }
      } else if (tabName === 'family') {
        // Display siblings only if they have siblings
        if (hasSiblingsLoaded && scSiblings) {
           scSiblings.style.display = 'block';
        }
        if (scUncles) scUncles.style.display = 'block';
        if (!IS_PUBLIC && homeSearchBar) {
           homeSearchBar.style.display = 'block';
        }
      }
    }

    // ── GENIUS SEARCH LOGIC (LIKE UNCLE DASHBOARD) ───────────────────────
    function normalizeArabic(text) {
      if (!text) return "";
      return text
        .replace(/[أإآٱ]/g, "ا")
        .replace(/[ىئ]/g, "ي")
        .replace(/ة/g, "ه")
        .replace(/ؤ/g, "و")
        .replace(/[\u064B-\u0652]/g, "") // Remove Harakat
        .toLowerCase()
        .trim();
    }

        function francoToArabic(text) {
            if (!text) return "";
            let s = text.toLowerCase().trim();
            if (!/[a-z0-9]/.test(s)) return "";
            const multiMap = [
                ['sh', 'ش'], ['ch', 'تش'], ['kh', 'خ'], ['gh', 'غ'],
                ['th', 'ث'], ['dh', 'ذ'], ['zh', 'ج'],
                ['ph', 'ف'],
                ['ou', 'و'], ['oo', 'و'], ['ee', 'ي'], ['ei', 'اي'],
                ['aa', 'ا'], ['ii', 'ي'],
            ];
            for (const [from, to] of multiMap) {
                s = s.split(from).join(to);
            }
            const singleMap = {
                'a': 'ا', 'b': 'ب', 't': 'ت', 'g': 'ج', 'j': 'ج',
                'h': 'ح', 'd': 'د', 'r': 'ر', 'z': 'ز', 's': 'س',
                'c': 'ك',
                'f': 'ف', 'q': 'ق', 'k': 'ك', 'l': 'ل', 'm': 'م',
                'n': 'ن', 'w': 'و', 'u': 'و', 'o': 'و',
                'y': 'ي', 'i': 'ي', 'e': 'ي',
                'x': 'اكس', 'v': 'ف', 'p': 'ب',
                '2': 'ء', '3': 'ع', '4': 'ش', '5': 'خ',
                '6': 'ط', '7': 'ح', '8': 'غ', '9': 'ق',
            };
            let result = '';
            for (let i = 0; i < s.length; i++) {
                const ch = s[i];
                if (singleMap[ch]) {
                    result += singleMap[ch];
                } else if (ch === ' ' || ch === '-' || ch === '_') {
                    result += ' ';
                } else {
                    result += ch;
                }
            }
            return normalizeArabic(result);
        }

        function arabicToLatin(text) {
            if (!text) return "";
            let s = text.toLowerCase().trim();
            if (!/[\u0600-\u06FF]/.test(s)) return "";
            s = normalizeArabic(s);
            const multiMap = [
                ['ش', 'sh'], ['خ', 'kh'], ['غ', 'gh'], ['ث', 'th'],
                ['ذ', 'dh'], ['ج', 'g'], ['ف', 'f'], ['ع', '3'],
                ['ط', '6'], ['ح', '7']
            ];
            for (const [from, to] of multiMap) {
                s = s.split(from).join(to);
            }
            const singleMap = {
                'ا': 'a', 'ب': 'b', 'ت': 't', 'ة': 'a',
                'د': 'd', 'ر': 'r', 'ز': 'z', 'س': 's', 'ص': 's', 'ض': 'd',
                'ق': 'q', 'ك': 'k', 'ل': 'l', 'م': 'm', 'ن': 'n',
                'ه': 'h', 'و': 'w', 'ي': 'y', 'ى': 'y', 'ئ': 'e', 'ء': '2', 'ؤ': 'o'
            };
            let result = '';
            for (let i = 0; i < s.length; i++) {
                const ch = s[i];
                if (singleMap[ch]) {
                    result += singleMap[ch];
                } else if (ch === ' ' || ch === '-' || ch === '_') {
                    result += ' ';
                } else {
                    result += ch;
                }
            }
            return result;
        }

        function phoneticClean(str) {
            if (!str) return "";
            let s = str.toLowerCase().trim();
            s = s.replace(/p/g, 'b');
            s = s.replace(/v/g, 'f');
            s = s.replace(/c/g, 'k');
            s = s.replace(/q/g, 'k');
            s = s.replace(/j/g, 'g');
            s = s.replace(/z/g, 's');
            s = s.replace(/x/g, 'ks');
            s = s.replace(/[aeiouywh]/g, '');
            return s;
        }

    function getMatchScore(friend, query) {
      const qNormalized = normalizeArabic(query);
      const qRaw = query.trim().toLowerCase();
      const qFranco = francoToArabic(query);
      const qLatin = arabicToLatin(query);
      const qPhonetic = phoneticClean(query.includes(' ') ? query : (qLatin || qRaw));
      
      let maxScore = 0;
      const fields = [
        { val: friend.name || friend['الاسم'], weight: 1.0 },
        { val: friend.class || friend['الفصل'], weight: 0.7 },
        { val: (friend.id || friend['_studentId'] || friend['معرف'])?.toString(), weight: 1.1 }
      ];

      fields.forEach(field => {
        if (!field.val) return;
        const target = field.val.toString();
        const tNormalized = normalizeArabic(target);
        const tRaw = target.toLowerCase();
        const tLatin = arabicToLatin(target);
        const tPhonetic = phoneticClean(tLatin || tRaw);

        let currentScore = 0;
        if (tRaw === qRaw || tNormalized === qNormalized) {
          currentScore = 100;
        } else if (tRaw.startsWith(qRaw) || tNormalized.startsWith(qNormalized)) {
          currentScore = 80;
        } else if (tRaw.includes(qRaw) || tNormalized.includes(qNormalized)) {
          currentScore = 60;
        } else if (qFranco && tNormalized === qFranco) {
          currentScore = 92;
        } else if (qFranco && tNormalized.startsWith(qFranco)) {
          currentScore = 72;
        } else if (qFranco && tNormalized.includes(qFranco)) {
          currentScore = 52;
        } else if (qLatin && tRaw === qLatin) {
          currentScore = 92;
        } else if (qLatin && tRaw.startsWith(qLatin)) {
          currentScore = 72;
        } else if (qLatin && tRaw.includes(qLatin)) {
          currentScore = 52;
        } else if (tLatin && tLatin === qRaw) {
          currentScore = 90;
        } else if (tLatin && tLatin.startsWith(qRaw)) {
          currentScore = 70;
        } else if (tLatin && tLatin.includes(qRaw)) {
          currentScore = 50;
        } else if (qPhonetic && tPhonetic && tPhonetic === qPhonetic) {
          currentScore = 88;
        } else if (qPhonetic && tPhonetic && tPhonetic.startsWith(qPhonetic)) {
          currentScore = 68;
        } else if (qPhonetic && tPhonetic && tPhonetic.includes(qPhonetic)) {
          currentScore = 48;
        } else {
          let score = 0;
          let queryIdx = 0;
          for (let i = 0; i < tNormalized.length && queryIdx < qNormalized.length; i++) {
            if (tNormalized[i] === qNormalized[queryIdx]) {
              queryIdx++;
              score++;
            }
          }
          if (queryIdx === qNormalized.length) {
            currentScore = (score / tNormalized.length) * 40;
          }
          if (qFranco) {
            let fScore = 0, fIdx = 0;
            for (let i = 0; i < tNormalized.length && fIdx < qFranco.length; i++) {
              if (tNormalized[i] === qFranco[fIdx]) { fIdx++; fScore++; }
            }
            if (fIdx === qFranco.length) {
              currentScore = Math.max(currentScore, (fScore / tNormalized.length) * 38);
            }
            let rScore = 0, rIdx = 0;
            for (let i = 0; i < qFranco.length && rIdx < tNormalized.length; i++) {
              if (qFranco[i] === tNormalized[rIdx]) { rIdx++; rScore++; }
            }
            if (rIdx === tNormalized.length) {
              const ratio = tNormalized.length / qFranco.length;
              currentScore = Math.max(currentScore, ratio * 70);
            }
          }
        }
        maxScore = Math.max(maxScore, currentScore * field.weight);
      });
      return maxScore;
    }

    // ── Home Genius Search ──
    let _homeSearchTimer = null;
    function onHomeSearch(val) {
      clearTimeout(_homeSearchTimer);
      const res = document.getElementById('homeSearchResults');
      if (!val || val.trim().length < 2) {
        res.innerHTML = '';
        res.style.display = 'none';
        return;
      }
      res.style.display = 'flex';
      res.innerHTML = '<div style="padding:12px;text-align:center;color:var(--t4);font-size:.82rem;"><i class="fas fa-spinner fa-spin"></i></div>';
      _homeSearchTimer = setTimeout(() => doHomeSearch(val.trim()), 300);
    }

    async function doHomeSearch(q) {
      const res = document.getElementById('homeSearchResults');
      try {
        const d = await api({ action: 'searchKidsByName', query: q, church_id: student?.church_id || 0 });
        const list = d.users || d.kids || [];
        if (!d.success || !list.length) {
          res.innerHTML = '<div style="padding:12px;text-align:center;color:var(--t3);font-size:.82rem;">لم يُعثر على نتائج</div>';
          res.style.display = 'flex';
          return;
        }

        // Score and sort search results locally (Genius style)
        let scored = list.map(k => {
           return { ...k, _score: getMatchScore(k, q) };
        }).filter(k => k._score > 0);

        scored.sort((a, b) => b._score - a._score);

        res.innerHTML = scored.map(k => {
          const av = k.image_url
            ? `<img src="${esc(k.image_url)}" alt="${esc(k.name)}">`
            : `<span>${k.name.charAt(0)}</span>`;
          const isSelf = student && k.id === student.id;
          return `
            <div class="friend-result-card" onclick="${isSelf ? 'window.scrollTo({top:0,behavior:\'smooth\'})' : 'openFriendProfile(' + k.id + ')'}" style="margin-bottom:0; width:100%;">
              <div class="friend-result-av">${av}</div>
              <div class="friend-result-info">
                <div class="friend-result-name">${esc(k.name)}${isSelf ? ' <span style="font-size:.68rem;color:var(--cou);font-weight:700;">(أنت)</span>' : ''}</div>
                <div class="friend-result-meta">${esc(k.class || '—')}${k.church_name ? ' · ' + esc(k.church_name) : ''}</div>
              </div>
              <div class="friend-result-cou"><i class="fas fa-star" style="font-size:.72rem;margin-left:3px;"></i>${k.coupons}</div>
            </div>`;
        }).join('');
        res.style.display = 'flex';
      } catch (e) {
        res.innerHTML = '<div style="padding:12px;text-align:center;color:var(--err);font-size:.82rem;">خطأ في البحث</div>';
        res.style.display = 'flex';
      }
    }

    function clearHomeSearch() {
      document.getElementById('homeFriendSearch').value = '';
      const res = document.getElementById('homeSearchResults');
      res.innerHTML = '';
      res.style.display = 'none';
    }

    // Close genius search dropdown on click outside
    document.addEventListener('click', function (e) {
      const wrap = document.getElementById('homeSearchBar');
      if (wrap && !wrap.contains(e.target)) {
        const dropdown = document.getElementById('homeSearchResults');
        if (dropdown) dropdown.style.display = 'none';
      }
    });

    // Show dropdown again on focus if input has value
    window.addEventListener('DOMContentLoaded', () => {
      const input = document.getElementById('homeFriendSearch');
      if (input) {
        input.addEventListener('focus', function () {
          if (this.value.trim().length >= 2) {
            const dropdown = document.getElementById('homeSearchResults');
            if (dropdown) dropdown.style.display = 'flex';
          }
        });
      }
    });

    // ── Sibling loading overrides ──
    async function loadSiblings() {
      const container = document.getElementById('siblingsList');
      const scSiblings = document.getElementById('scSiblings');
      if (!student || !student.custom_info || !student.custom_info.sibling_group) {
        hasSiblingsLoaded = false;
        if (scSiblings) scSiblings.style.display = 'none';
        return;
      }
      try {
        const d = await api({ action: 'getSiblingGroupMembers', studentId: student.id });
        if (d.success && d.siblings && d.siblings.length > 0) {
          const others = d.siblings.filter(s => s.id != student.id);
          if (others.length === 0) {
            hasSiblingsLoaded = false;
            if (scSiblings) scSiblings.style.display = 'none';
            return;
          }
          hasSiblingsLoaded = true;
          container.innerHTML = others.map(s => {
            const photo = s.image_url 
              ? `<img src="${esc(s.image_url)}" style="width:100%;height:100%;object-fit:cover;border-radius:var(--r-md);" alt="${esc(s.name)}">`
              : `<i class="fas fa-user" style="font-size:1.8rem;color:var(--t4);"></i>`;
            return `
              <div class="sibling-card" onclick="openFriendProfile(${s.id})" style="background:var(--surf);border:1.5px solid var(--bdr);border-radius:var(--r-md);padding:14px 10px;text-align:center;cursor:pointer;transition:all var(--fast);display:flex;flex-direction:column;align-items:center;gap:8px;box-shadow:var(--sh-sm);">
                <div style="width:56px;height:56px;border-radius:var(--r-md);background:var(--bg);display:flex;align-items:center;justify-content:center;overflow:hidden;flex-shrink:0;">
                  ${photo}
                </div>
                <div style="font-size:.82rem;font-weight:800;color:var(--t1);line-height:1.3;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;width:100%;">${esc(s.name)}</div>
                <div style="font-size:.68rem;color:var(--t4);font-weight:700;">${esc(s.class || '—')}</div>
                <div style="display:inline-flex;align-items:center;gap:4px;background:var(--brand-bg);color:var(--brand);padding:2px 8px;border-radius:99px;font-size:.7rem;font-weight:800;">
                  <i class="fas fa-star" style="font-size:.65rem;"></i> ${s.coupons}
                </div>
              </div>
            `;
          }).join('');
          
          // Set up chips in Wizard Step 1
          const chipsContainer = document.getElementById('siblingChipsContainer');
          const chipsList = document.getElementById('sendSiblingChips');
          if (chipsContainer && chipsList) {
            chipsContainer.style.display = 'block';
            chipsList.innerHTML = others.map(s => `
              <div class="send-recipient-chip" data-id="${s.id}" data-name="${esc(s.name)}" onclick="selectRecipient(${s.id}, '${esc(s.name)}')">
                <div style="font-size:.8rem;font-weight:800;">${esc(s.name.split(' ')[0])}</div>
                <div style="font-size:.62rem;color:rgba(255,255,255,0.7);">أخ / أخت</div>
              </div>
            `).join('');
          }
        } else {
          hasSiblingsLoaded = false;
          if (scSiblings) scSiblings.style.display = 'none';
        }
      } catch (e) {
        hasSiblingsLoaded = false;
        if (scSiblings) scSiblings.style.display = 'none';
      }
    }

    // ── SEND COUPONS STEP WIZARD ──
    function initSendWizard() {
      if (isViewingOther()) {
        toast('يرجى تسجيل الدخول لتتمكن من إرسال الكوبونات', 'err');
        switchTab('home');
        return;
      }
      
      // Update available values
      document.getElementById('sendAvailAtt').innerText = student.att_coupons || 0;
      document.getElementById('sendAvailCom').innerText = student.com_coupons || 0;
      document.getElementById('sendAvailTsk').innerText = student.task_coupons || 0;
      document.getElementById('sendAvailTotal').innerText = student.coupons || 0;

      goToStep(1);
      clearSelectedRecipient();
      document.getElementById('sendAmount').value = '';
      document.getElementById('sendPassword').value = '';
    }

    function goToStep(stepNum) {
      wizardCurrentStep = stepNum;
      
      // Toggle views
      document.getElementById('sendStep1').style.display = stepNum === 1 ? 'block' : 'none';
      document.getElementById('sendStep2').style.display = stepNum === 2 ? 'block' : 'none';
      document.getElementById('sendStep3').style.display = stepNum === 3 ? 'block' : 'none';

      // Update dots
      document.querySelectorAll('.wizard-dot').forEach((dot, idx) => {
         dot.classList.remove('active');
         if (idx + 1 === stepNum) dot.classList.add('active');
      });

      if (stepNum === 2) {
         checkStep2Valid();
      } else if (stepNum === 3) {
         // Show summary message
         let label = "الكل مدمج";
         if (selectedSendCategory === 'att') label = "الحضور";
         else if (selectedSendCategory === 'com') label = "الالتزام";
         else if (selectedSendCategory === 'task') label = "المهام";
         
         const amount = parseInt(document.getElementById('sendAmount').value);
         document.getElementById('sendSummaryMsg').innerHTML = `سوف تقوم بإرسال <strong style="font-size:1.15rem; color:#f59e0b;">${amount}</strong> كوبون من رصيد [${label}] إلى صديقك <strong style="color:#60a5fa;">(${selectedRecipientNameStr})</strong>.`;
         checkStep3Valid();
      }
    }

    function selectRecipient(id, name) {
      selectedRecipientId = id;
      selectedRecipientNameStr = name;

      document.querySelectorAll('.send-recipient-chip').forEach(chip => {
        chip.classList.remove('active');
        if (chip.getAttribute('data-id') == id) chip.classList.add('active');
      });

      document.getElementById('selectedRecipientName').innerText = name;
      document.getElementById('selectedRecipientTag').style.display = 'flex';
      
      // Clear searches
      document.getElementById('sendFriendSearch').value = '';
      document.getElementById('sendFriendSearchResults').style.display = 'none';

      // Enable next step button
      document.getElementById('toStep2Btn').disabled = false;
    }

    function clearSelectedRecipient() {
      selectedRecipientId = null;
      selectedRecipientNameStr = "";
      document.querySelectorAll('.send-recipient-chip').forEach(chip => chip.classList.remove('active'));
      document.getElementById('selectedRecipientTag').style.display = 'none';
      document.getElementById('toStep2Btn').disabled = true;
    }

    let _sendFriendSearchTimer = null;
    function onSendFriendSearch(val) {
      clearTimeout(_sendFriendSearchTimer);
      const res = document.getElementById('sendFriendSearchResults');
      if (!val || val.trim().length < 2) { res.innerHTML = ''; res.style.display = 'none'; return; }
      res.style.display = 'block';
      res.innerHTML = '<div style="padding:10px;text-align:center;color:var(--t4);font-size:.78rem;"><i class="fas fa-spinner fa-spin"></i></div>';
      _sendFriendSearchTimer = setTimeout(() => doSendFriendSearch(val.trim()), 300);
    }

    async function doSendFriendSearch(q) {
      const res = document.getElementById('sendFriendSearchResults');
      try {
        const d = await api({ action: 'searchKidsByName', query: q, church_id: student?.church_id || 0 });
        const list = d.users || d.kids || [];
        if (!d.success || !list.length) {
          res.innerHTML = '<div style="padding:10px;text-align:center;color:var(--t3);font-size:.78rem;">لم يُعثر على نتائج</div>';
          return;
        }

        // Score results
        let scored = list.filter(k => k.id != student.id).map(k => {
           return { ...k, _score: getMatchScore(k, q) };
        }).filter(k => k._score > 0);
        scored.sort((a, b) => b._score - a._score);

        res.innerHTML = scored.map(k => `
          <div style="display:flex;align-items:center;gap:10px;padding:10px;border-bottom:1px solid var(--bdr2);cursor:pointer; color:var(--t1);" onclick="selectRecipient(${k.id}, '${esc(k.name)}')">
            <div style="width:30px;height:30px;border-radius:50%;background:var(--brand-bg);color:var(--brand);display:flex;align-items:center;justify-content:center;font-weight:800;font-size:.75rem;overflow:hidden;">
              ${k.image_url ? `<img src="${esc(k.image_url)}" style="width:100%;height:100%;object-fit:cover;">` : k.name.charAt(0)}
            </div>
            <div style="flex:1;min-width:0;text-align:right;">
              <div style="font-size:.8rem;font-weight:800;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${esc(k.name)}</div>
              <div style="font-size:.64rem;color:var(--t4);">${esc(k.class || '—')}</div>
            </div>
          </div>
        `).join('');
      } catch (e) {
        res.innerHTML = '<div style="padding:10px;text-align:center;color:var(--err);font-size:.78rem;">خطأ في البحث</div>';
      }
    }

    function selectSendCat(btn) {
      document.querySelectorAll('.send-cat-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      selectedSendCategory = btn.getAttribute('data-cat');
      checkStep2Valid();
    }

    function checkStep2Valid() {
      const amount = parseInt(document.getElementById('sendAmount').value);
      let limit = student.coupons || 0;
      if (selectedSendCategory === 'att') limit = student.att_coupons || 0;
      else if (selectedSendCategory === 'com') limit = student.com_coupons || 0;
      else if (selectedSendCategory === 'task') limit = student.task_coupons || 0;

      const isValid = (!isNaN(amount) && amount > 0 && amount <= limit);
      document.getElementById('toStep3Btn').disabled = !isValid;
    }

    function checkStep3Valid() {
      const password = document.getElementById('sendPassword').value;
      document.getElementById('sendWizardSubmitBtn').disabled = !password;
    }

    function trySendCoupons() {
      const amount = parseInt(document.getElementById('sendAmount').value);
      const msg = `تأكيد نهائي: هل أنت متأكد من إرسال ${amount} كوبون إلى (${selectedRecipientNameStr})؟`;
      document.getElementById('shareConfirmMsg').innerText = msg;
      openOv('shareConfirmModal');
    }

    async function confirmSendCoupons() {
      if (isViewingOther()) {
        toast('غير مسموح في وضع المعاينة', 'err');
        return;
      }
      closeOv('shareConfirmModal');
      showLoad('جاري إرسال الكوبونات…');
      try {
        const password = document.getElementById('sendPassword').value;
        const amount = parseInt(document.getElementById('sendAmount').value);

        const d = await api({
          action: 'shareCoupons',
          senderId: student.id,
          password: password,
          recipientId: selectedRecipientId,
          amount: amount,
          category: selectedSendCategory
        });

        hideLoad();
        if (d.success) {
          toast(d.message || 'تم إرسال الكوبونات بنجاح!', 'ok');
          
          // Refresh and redirect to home after 1 second
          setTimeout(() => { location.reload(); }, 1200);
        } else {
          toast(d.message || 'فشل إرسال الكوبونات', 'err');
        }
      } catch (e) {
        hideLoad();
        toast('خطأ في الاتصال بالسيرفر', 'err');
      }
    }

    function renderPaperExams(s) {
      const list = s.paper_exams || [];
      const el = document.getElementById('paperExamsList');
      const container = document.getElementById('scPaperExams');
      if (!el || !container) return;
      
      if (!list.length) {
        container.style.display = 'none';
        return;
      }
      
      container.style.display = 'block';
      
      el.innerHTML = list.map(exam => {
        const degreeText = exam.degree !== null ? `${exam.degree} / ${exam.total_degree}` : 'غير مرصود بعد';
        const degreeColor = exam.degree !== null 
            ? (exam.degree >= exam.total_degree * 0.5 ? 'var(--ok)' : 'var(--danger)') 
            : 'var(--text-3)';
            
        let refLinkHtml = '';
        if (exam.reference_url) {
          refLinkHtml = `
            <a href="${exam.reference_url}" target="_blank" class="badge" style="background:var(--brand-bg); color:var(--brand); display:inline-flex; align-items:center; gap:4px; font-weight:700; text-decoration:none; padding:4px 8px; border-radius:12px; font-size:0.72rem; cursor:pointer;">
              <i class="fas fa-external-link-alt"></i> ورقة الامتحان
            </a>
          `;
        }
        
        let answersPicHtml = '';
        if (exam.answers_picture) {
          answersPicHtml = `
            <a href="${exam.answers_picture}" target="_blank" class="badge" style="background:#dcfce7; color:#15803d; display:inline-flex; align-items:center; gap:4px; font-weight:700; text-decoration:none; padding:4px 8px; border-radius:12px; font-size:0.72rem; cursor:pointer;">
              <i class="fas fa-image"></i> ورقة إجابتك
            </a>
          `;
        }
        
        return `
          <div class="glass-card" style="padding:12px; border:1px solid var(--bdr); border-radius:12px; display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:4px; box-sizing:border-box;">
            <div style="display:flex; flex-direction:column; gap:4px; text-align:right;">
              <strong style="font-size:0.88rem; color:var(--txt);">${esc(exam.name)}</strong>
              <div style="display:flex; gap:6px; align-items:center; flex-wrap:wrap;">
                ${refLinkHtml}
                ${answersPicHtml}
              </div>
            </div>
            <div style="font-weight:900; font-size:0.95rem; color:${degreeColor}; white-space:nowrap;">
              ${degreeText}
            </div>
          </div>
        `;
      }).join('');
    }

    const VAPID_PUBLIC_KEY = '<?= defined("VAPID_PUBLIC_KEY") ? VAPID_PUBLIC_KEY : "" ?>';

    function _urlBase64ToUint8Array(base64String) {
      const padding = '='.repeat((4 - base64String.length % 4) % 4);
      const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
      const rawData = window.atob(base64);
      const outputArray = new Uint8Array(rawData.length);
      for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
      }
      return outputArray;
    }

    async function _initPushNotifications() {
      const toggleRow = document.getElementById('notifToggleItem');
      const checkbox = document.getElementById('phoneNotifToggle');
      if (!toggleRow || !checkbox) return;

      if (!('serviceWorker' in navigator) || !('PushManager' in window)) {
        console.warn("Push notifications not supported on this browser.");
        toggleRow.style.display = 'none';
        return;
      }

      // Check current permission
      if (Notification.permission === 'denied') {
        checkbox.checked = false;
        checkbox.disabled = true;
        toggleRow.style.display = 'flex';
        return;
      }

      try {
        const reg = await navigator.serviceWorker.ready;
        let sub = await reg.pushManager.getSubscription();
        let needsResubscribe = false;
        if (sub) {
          if (sub.options && sub.options.applicationServerKey) {
            const currentKeyUint8 = _urlBase64ToUint8Array(VAPID_PUBLIC_KEY);
            const subKey = new Uint8Array(sub.options.applicationServerKey);
            let mismatch = subKey.length !== currentKeyUint8.length;
            if (!mismatch) {
              for (let i = 0; i < subKey.length; i++) {
                if (subKey[i] !== currentKeyUint8[i]) {
                  mismatch = true;
                  break;
                }
              }
            }
            if (mismatch) {
              try {
                await sub.unsubscribe();
              } catch (unsubErr) { }
              sub = null;
              needsResubscribe = true;
            }
          } else {
            needsResubscribe = true;
          }
        }

        if (Notification.permission === 'default') {
          try {
            const permission = await Notification.requestPermission();
            if (permission === 'granted' && VAPID_PUBLIC_KEY) {
              sub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: _urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
              });
              needsResubscribe = true;
            }
          } catch (err) {
            console.error("Auto push subscription on load failed:", err);
          }
        }

        if (Notification.permission === 'granted' && (!sub || needsResubscribe)) {
          if (VAPID_PUBLIC_KEY) {
            try {
              sub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: _urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
              });
              needsResubscribe = true;
            } catch (err) {
              console.error("Auto push subscription on load failed:", err);
            }
          }
        }

        checkbox.checked = !!sub;
        toggleRow.style.display = 'flex';

        if (sub) {
          const savedEndpoint = localStorage.getItem('push_sub_saved_endpoint');
          if (needsResubscribe || !savedEndpoint || savedEndpoint !== sub.endpoint) {
            try {
              const res = await api({
                action: 'savePushSubscription',
                endpoint: sub.endpoint,
                p256dh: sub.toJSON().keys?.p256dh || '',
                auth: sub.toJSON().keys?.auth || '',
                student_id: student.id
              });
              if (res.success) {
                localStorage.setItem('push_sub_saved_endpoint', sub.endpoint);
              }
            } catch (saveErr) {
              console.error("Failed to save auto subscription:", saveErr);
            }
          }
        }

        // Remove old event listener and bind new one
        checkbox.onchange = async () => {
          if (checkbox.checked) {
            // Subscribe
            try {
              let permission = Notification.permission;
              if (permission !== 'granted') {
                permission = await Notification.requestPermission();
              }
              if (permission !== 'granted') {
                toast('تم رفض إذن الإشعارات ✕', 'err');
                checkbox.checked = false;
                return;
              }

              if (!VAPID_PUBLIC_KEY) {
                console.error("VAPID Key not found");
                return;
              }

              const newSub = await reg.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: _urlBase64ToUint8Array(VAPID_PUBLIC_KEY)
              });

              // Save on backend
              const res = await api({
                action: 'savePushSubscription',
                endpoint: newSub.endpoint,
                p256dh: newSub.toJSON().keys?.p256dh || '',
                auth: newSub.toJSON().keys?.auth || '',
                student_id: student.id
              });

              if (res.success) {
                toast('تم تفعيل الإشعارات بنجاح ✓', 'ok');
                localStorage.setItem('push_sub_saved_endpoint', newSub.endpoint);
              } else {
                toast(res.message || 'فشل في حفظ الاشتراك', 'err');
                checkbox.checked = false;
                try {
                  await newSub.unsubscribe();
                } catch (e) { }
              }
            } catch (err) {
              console.error("Subscription failed:", err);
              toast('فشل في تفعيل الإشعارات ✕', 'err');
              checkbox.checked = false;
            }
          } else {
            // Unsubscribe
            try {
              const currentSub = await reg.pushManager.getSubscription();
              if (currentSub) {
                try {
                  await currentSub.unsubscribe();
                } catch (e) { }
                await api({
                  action: 'deletePushSubscription',
                  endpoint: currentSub.endpoint
                });
                localStorage.removeItem('push_sub_saved_endpoint');
              }
              toast('تم إلغاء تفعيل الإشعارات ✓', 'ok');
            } catch (err) {
              console.error("Unsubscription failed:", err);
              toast('فشل في إلغاء الإشعارات ✕', 'err');
              checkbox.checked = true;
            }
          }
        };
      } catch (e) {
        console.error("Error setting up push notifications:", e);
      }
    }

    // loadAnn was combined and moved to the primary section above.

    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker.register('/sw.js').catch(err => console.error('SW Registration failed:', err));
      });
    }
  </script>
</body>

</html>
