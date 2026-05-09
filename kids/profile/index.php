<?php
session_start();
$studentIdFromUrl = isset($_GET['id']) ? intval($_GET['id']) : null;
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action']??'')==='logout') {
    session_destroy();
    echo '<script>["savedUsername","savedPassword","rememberMe","userPhone","loginType"].forEach(k=>localStorage.removeItem(k));window.location.href="/kids/login";</script>';
    exit;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1,maximum-scale=1,user-scalable=no">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="mobile-web-app-capable" content="yes">
<title id="pageTitle">بوابة الطفل</title>
<meta name="theme-color" content="#4f46e5">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Baloo+Bhaijaan+2:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.css">
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.5.13/cropper.min.js"></script>
<link rel="icon" href="/favicon.ico">
<style>
/* ══ TOKENS ══════════════════════════════════════════════════ */
:root{
  --brand:#4f46e5;--brand-d:#3730a3;--brand-l:#818cf8;--brand-bg:#eef2ff;--brand-glow:rgba(79,70,229,.18);
  --ok:#059669;--ok-l:#10b981;--ok-bg:#d1fae5;
  --err:#dc2626;--err-l:#ef4444;--err-bg:#fee2e2;
  --warn:#d97706;--warn-l:#f59e0b;--warn-bg:#fef3c7;
  --cou:#7c3aed;--cou-l:#8b5cf6;--cou-bg:#ede9fe;
  --trip:#0369a1;--trip-l:#0ea5e9;--trip-bg:#e0f2fe;
  --gold:#b45309;--gold-l:#f59e0b;--gold-bg:#fef3c7;
  --t1:#0f172a;--t2:#334155;--t3:#64748b;--t4:#94a3b8;--t5:#cbd5e1;
  --bg:#f1f5f9;--surf:#fff;--s2:#f8fafc;--bdr:#e2e8f0;--bdr2:#f1f5f9;
  --r-xs:6px;--r-sm:10px;--r-md:16px;--r-lg:22px;--r-xl:30px;--r-2xl:42px;--r-full:9999px;
  --sh-sm:0 1px 6px rgba(0,0,0,.05);
  --sh-md:0 6px 20px rgba(0,0,0,.08);
  --sh-lg:0 16px 40px rgba(0,0,0,.11);
  --sh-xl:0 30px 60px rgba(0,0,0,.14);
  --sh-brand:0 8px 24px rgba(79,70,229,.3);
  --ease:cubic-bezier(.4,0,.2,1);--spring:cubic-bezier(.16,1,.3,1);
  --fast:.15s var(--ease);--norm:.26s var(--ease);--slow:.48s var(--spring);
}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box;-webkit-tap-highlight-color:transparent;-webkit-user-select:none;-moz-user-select:none;user-select:none;-webkit-touch-callout:none;}
html{scroll-behavior:smooth;}
html.ov-open{overflow:visible;}
body{
  font-family:'Baloo Bhaijaan 2',sans-serif;
  background:var(--bg);color:var(--t1);
  min-height:100vh;overflow-x:hidden;
  -webkit-font-smoothing:antialiased;
}
body::before{
  content:'';position:fixed;inset:0;pointer-events:none;z-index:0;
  background:
    radial-gradient(ellipse 70% 45% at 10% 5%,rgba(79,70,229,.08) 0%,transparent 65%),
    radial-gradient(ellipse 55% 40% at 90% 90%,rgba(124,58,237,.06) 0%,transparent 60%);
}

/* ══ HERO ════════════════════════════════════════════════════ */
.hero{
  position:relative;overflow:hidden;
  background:linear-gradient(145deg,#312e81 0%,#4f46e5 35%,#7c3aed 70%,#5b21b6 100%);
  padding:0 0 0;
  display:flex;flex-direction:column;
  min-height:340px;
}
/* animated mesh */
.hero::before{
  content:'';position:absolute;inset:0;
  background:
    radial-gradient(circle at 20% 30%,rgba(255,255,255,.07) 0%,transparent 40%),
    radial-gradient(circle at 80% 70%,rgba(255,255,255,.05) 0%,transparent 35%);
  animation:hero-pulse 6s ease-in-out infinite;
}
@keyframes hero-pulse{
  0%,100%{opacity:1;}50%{opacity:.6;}
}
/* star/dot texture */
.hero::after{
  content:'';position:absolute;inset:0;pointer-events:none;
  background-image:radial-gradient(circle, rgba(255,255,255,.08) 1px, transparent 1px);
  background-size:28px 28px;
}

.hero-top{
  position:relative;z-index:2;
  display:flex;align-items:center;justify-content:space-between;
  padding:14px 18px 0;
}
.hero-church-chip{
  display:inline-flex;align-items:center;gap:6px;
  background:rgba(255,255,255,.14);backdrop-filter:blur(10px);
  color:rgba(255,255,255,.92);font-size:.78rem;font-weight:600;
  padding:5px 13px;border-radius:var(--r-full);
  border:1px solid rgba(255,255,255,.22);
  max-width:180px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;
}
.hero-actions-top{display:flex;gap:8px;}
.hero-ico-btn{
  width:34px;height:34px;border-radius:50%;
  background:rgba(255,255,255,.15);backdrop-filter:blur(8px);
  border:1px solid rgba(255,255,255,.25);
  color:#fff;font-size:.9rem;cursor:pointer;
  display:flex;align-items:center;justify-content:center;
  transition:var(--fast);
}
.hero-ico-btn:hover{background:rgba(255,255,255,.26);}

/* center content */
.hero-body{
  position:relative;z-index:2;
  display:flex;flex-direction:column;align-items:center;
  padding:22px 20px 0;
  flex:1;
}
.avatar-ring{
  position:relative;
  width:116px;height:116px;
  /* glowing ring */
  background:conic-gradient(from 0deg,#818cf8,#c4b5fd,#e0e7ff,#818cf8);
  border-radius:50%;
  padding:3px;
  box-shadow:0 0 0 4px rgba(255,255,255,.15),0 8px 32px rgba(0,0,0,.25);
  cursor:pointer;
  transition:var(--norm);
  animation:ring-spin 8s linear infinite;
}
@keyframes ring-spin{
  from{background:conic-gradient(from 0deg,#818cf8,#c4b5fd,#e0e7ff,#818cf8);}
  to{background:conic-gradient(from 360deg,#818cf8,#c4b5fd,#e0e7ff,#818cf8);}
}
.avatar-ring:hover{transform:scale(1.04);}
.avatar-inner{
  width:100%;height:100%;border-radius:50%;
  background:linear-gradient(135deg,#e0e7ff,#c7d2fe);
  overflow:hidden;display:flex;align-items:center;justify-content:center;
  font-size:2.6rem;color:#818cf8;
  border:3px solid rgba(255,255,255,.9);
}
.avatar-inner img{width:100%;height:100%;object-fit:cover;}
.avatar-edit-fab{
  position:absolute;bottom:2px;left:2px;
  width:26px;height:26px;border-radius:50%;
  background:var(--brand);border:2px solid #fff;
  display:none;align-items:center;justify-content:center;
  font-size:.58rem;color:#fff;cursor:pointer;
  box-shadow:0 2px 8px rgba(0,0,0,.2);transition:var(--fast);
}
.avatar-edit-fab.show{display:flex;}
.avatar-edit-fab:hover{background:var(--brand-d);transform:scale(1.12);}

.hero-name{
  margin-top:14px;
  font-size:1.85rem;font-weight:800;color:#fff;
  text-align:center;text-shadow:0 2px 12px rgba(0,0,0,.2);
  line-height:1.15;
}
@media(max-width:400px){.hero-name{font-size:1.5rem;}}

.hero-tags{
  display:flex;align-items:center;justify-content:center;
  gap:7px;flex-wrap:wrap;margin-top:10px;
}
.htag{
  display:inline-flex;align-items:center;gap:5px;
  padding:4px 13px;border-radius:var(--r-full);
  font-size:.78rem;font-weight:700;
  background:rgba(255,255,255,.16);backdrop-filter:blur(8px);
  border:1px solid rgba(255,255,255,.28);color:#fff;
  transition:var(--fast);
}
.htag.class-tag{background:rgba(255,255,255,.95);color:var(--brand-d);border-color:transparent;}
.htag.switch-tag{background:transparent;border-color:rgba(255,255,255,.4);cursor:pointer;}
.htag.switch-tag:hover{background:rgba(255,255,255,.18);}

/* ── COUPON HERO CARD ──────────────────────────────── */
.coupon-hero{
  position:relative;z-index:2;
  margin:20px 16px 0;
  background:rgba(255,255,255,.1);backdrop-filter:blur(14px);
  border:1px solid rgba(255,255,255,.22);
  border-radius:var(--r-xl);
  padding:18px 20px 16px;
  display:grid;grid-template-columns:1fr auto;align-items:center;gap:16px;
  overflow:hidden;
}
.coupon-hero::before{
  content:'';position:absolute;top:-30px;left:-30px;
  width:140px;height:140px;border-radius:50%;
  background:radial-gradient(circle,rgba(196,181,253,.25),transparent 70%);
  pointer-events:none;
}
.ch-total-label{font-size:.74rem;font-weight:600;color:rgba(255,255,255,.75);margin-bottom:3px;}
.ch-total-val{
  font-size:2.9rem;font-weight:800;color:#fff;line-height:1;
  text-shadow:0 2px 16px rgba(0,0,0,.2);
}
.ch-total-unit{font-size:.88rem;color:rgba(255,255,255,.8);margin-top:1px;font-weight:600;}
.ch-breakdown{
  display:flex;flex-direction:column;gap:5px;
}
.ch-row{
  display:flex;align-items:center;gap:6px;
  font-size:.74rem;color:rgba(255,255,255,.85);font-weight:600;
  background:rgba(255,255,255,.1);padding:4px 10px;border-radius:var(--r-full);
}
.ch-row i{font-size:.72rem;}

/* ── HERO WAVE BOTTOM ──────────────────────────────── */
.hero-wave{
  position:relative;z-index:2;
  margin-top:20px;
  height:38px;
  background:var(--bg);
  clip-path:ellipse(56% 100% at 50% 100%);
  flex-shrink:0;
}

/* ══ STATS BAR ═══════════════════════════════════════ */
.stats-bar{
  display:grid;grid-template-columns:repeat(4,1fr);
  margin:0 14px 18px;
  background:var(--surf);
  border-radius:var(--r-lg);
  border:1px solid var(--bdr);
  box-shadow:var(--sh-md);
  overflow:hidden;
}
.sb-cell{
  padding:12px 8px;text-align:center;
  border-left:1px solid var(--bdr);
  transition:var(--fast);position:relative;
}
.sb-cell:last-child{border-left:none;}
.sb-cell:hover{background:var(--s2);}
.sb-val{font-size:1.3rem;font-weight:800;line-height:1;color:var(--t1);}
.sb-lbl{font-size:.63rem;color:var(--t4);margin-top:2px;font-weight:600;}
.sb-cell.ok .sb-val{color:var(--ok-l);}
.sb-cell.err .sb-val{color:var(--err-l);}
.sb-cell.cou .sb-val{color:var(--cou-l);}
.sb-cell.neu .sb-val{color:var(--brand-l);}

/* ══ PAGE ════════════════════════════════════════════ */
.page{max-width:860px;margin:0 auto;padding:0 14px 90px;position:relative;z-index:1;}

/* ══ SECTION CARD ════════════════════════════════════ */
.sc{
  background:var(--surf);border-radius:var(--r-lg);
  border:1px solid var(--bdr);box-shadow:var(--sh-sm);
  overflow:hidden;margin-bottom:14px;
  transition:box-shadow var(--norm),transform var(--norm);
}
.sc:hover{box-shadow:var(--sh-md);}
.sc-head{
  display:flex;align-items:center;gap:11px;
  padding:14px 18px 12px;border-bottom:1px solid var(--bdr2);
  background:linear-gradient(90deg,var(--s2),var(--surf));
}
.sc-ico{
  width:36px;height:36px;border-radius:var(--r-sm);
  display:flex;align-items:center;justify-content:center;
  font-size:.9rem;flex-shrink:0;
}
.sc-label{
  flex:1;
}
.sc-title{font-size:.96rem;font-weight:700;color:var(--t1);}
.sc-sub{font-size:.7rem;color:var(--t4);margin-top:1px;font-weight:500;}
.sc-badge{
  font-size:.72rem;font-weight:800;
  padding:3px 10px;border-radius:var(--r-full);
  background:var(--brand-bg);color:var(--brand);
}
.sc-body{padding:16px;}

/* ══ INFO PILLS ══════════════════════════════════════ */
.info-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(168px,1fr));gap:10px;}
.ip{
  display:flex;align-items:flex-start;gap:9px;
  padding:11px 13px;border-radius:var(--r-md);
  background:var(--s2);border:1px solid var(--bdr);
  transition:var(--fast);
}
.ip:hover{border-color:var(--brand-l);background:var(--brand-bg);transform:translateY(-1px);}
.ip-ico{
  width:30px;height:30px;border-radius:var(--r-xs);flex-shrink:0;
  display:flex;align-items:center;justify-content:center;font-size:.78rem;
  margin-top:1px;
}
.ip-lbl{font-size:.66rem;color:var(--t4);font-weight:600;margin-bottom:2px;}
.ip-val{font-size:.84rem;font-weight:700;color:var(--t1);word-break:break-word;line-height:1.3;}

/* ══ ATTENDANCE ══════════════════════════════════════ */
.att-stats{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:14px;}
.as{
  text-align:center;padding:11px 6px;border-radius:var(--r-md);
  background:var(--s2);border:1px solid var(--bdr);
}
.as-val{font-size:1.35rem;font-weight:800;line-height:1;}
.as-lbl{font-size:.65rem;color:var(--t4);margin-top:2px;font-weight:600;}
.as.ok{background:var(--ok-bg);border-color:#6ee7b7;}
.as.ok .as-val{color:var(--ok);}
.as.err{background:var(--err-bg);border-color:#fca5a5;}
.as.err .as-val{color:var(--err);}
.as.neu .as-val{color:var(--brand);}

.cal-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(82px,1fr));gap:8px;}
.cal-day{
  padding:10px 7px;border-radius:var(--r-md);text-align:center;
  border:2px solid var(--bdr);background:var(--s2);
  transition:var(--fast);
}
.cal-day:hover{transform:translateY(-2px);box-shadow:var(--sh-sm);}
.cal-day.present{background:var(--ok-bg);border-color:#6ee7b7;}
.cal-day.absent{background:var(--err-bg);border-color:#fca5a5;}
.cd-num{font-size:1.1rem;font-weight:800;color:var(--t1);}
.cal-day.present .cd-num{color:var(--ok);}
.cal-day.absent  .cd-num{color:var(--err);}
.cd-mo{font-size:.6rem;color:var(--t4);font-weight:600;}
.cd-st{font-size:.62rem;font-weight:700;margin-top:3px;color:var(--t5);}
.cal-day.present .cd-st{color:var(--ok);}
.cal-day.absent  .cd-st{color:var(--err);}
.cd-days{font-size:.58rem;color:var(--t4);margin-top:1px;font-weight:500;}

/* ══ ATTENDANCE HISTORY MODAL ════════════════════════ */
.att-view-all{
  display:inline-flex;align-items:center;gap:5px;
  font-size:.72rem;font-weight:700;color:var(--brand);
  cursor:pointer;padding:4px 10px;border-radius:var(--r-full);
  background:var(--brand-bg);border:1px solid rgba(79,70,229,.15);
  transition:var(--fast);margin-top:10px;
  -webkit-user-select:none;user-select:none;
}
.att-view-all:hover{background:var(--brand);color:#fff;transform:translateY(-1px);}
#attHistOv .modal{max-width:700px;}
.att-hist-filters{
  display:flex;align-items:center;gap:8px;flex-wrap:wrap;
  padding:12px 18px;border-bottom:1px solid var(--bdr);
  background:var(--s2);
}
.att-hist-search{
  flex:1;min-width:140px;
  padding:9px 14px;border:1.5px solid var(--bdr);
  border-radius:var(--r-sm);font-family:'Baloo Bhaijaan 2',sans-serif;
  font-size:.88rem;background:var(--surf);color:var(--t1);outline:none;
  transition:var(--fast);
}
.att-hist-search:focus{border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-bg);}
.att-filter-chips{display:flex;gap:6px;flex-wrap:wrap;}
.fchip{
  padding:5px 12px;border-radius:var(--r-full);font-size:.72rem;font-weight:700;
  border:1.5px solid var(--bdr);background:var(--surf);color:var(--t3);
  cursor:pointer;transition:var(--fast);white-space:nowrap;
}
.fchip:hover{border-color:var(--brand);color:var(--brand);}
.fchip.active{background:var(--brand);border-color:var(--brand);color:#fff;}
.fchip.ok.active{background:var(--ok);border-color:var(--ok);color:#fff;}
.fchip.err.active{background:var(--err);border-color:var(--err);color:#fff;}
.att-hist-sort{
  padding:7px 12px;border:1.5px solid var(--bdr);border-radius:var(--r-sm);
  font-family:'Baloo Bhaijaan 2',sans-serif;font-size:.8rem;
  background:var(--surf);color:var(--t2);outline:none;cursor:pointer;
  transition:var(--fast);
}
.att-hist-sort:focus{border-color:var(--brand);}
.att-hist-summary{
  display:grid;grid-template-columns:repeat(3,1fr);gap:8px;
  padding:12px 18px;border-bottom:1px solid var(--bdr);
}
.ahs-card{
  text-align:center;padding:10px 6px;border-radius:var(--r-md);
  background:var(--s2);border:1px solid var(--bdr);
}
.ahs-val{font-size:1.2rem;font-weight:800;line-height:1;}
.ahs-lbl{font-size:.6rem;color:var(--t4);margin-top:2px;font-weight:600;}
.ahs-card.ok{background:var(--ok-bg);border-color:#6ee7b7;}
.ahs-card.ok .ahs-val{color:var(--ok);}
.ahs-card.err{background:var(--err-bg);border-color:#fca5a5;}
.ahs-card.err .ahs-val{color:var(--err);}
.ahs-card.neu .ahs-val{color:var(--brand);}
.att-hist-list{
  padding:10px 18px 18px;
  max-height:calc(100vh - 340px);overflow-y:auto;
  display:flex;flex-direction:column;gap:6px;
}
.att-hist-item{
  display:flex;align-items:center;gap:12px;
  padding:10px 14px;border-radius:var(--r-md);
  border:1.5px solid var(--bdr);background:var(--surf);
  transition:var(--fast);
}
.att-hist-item:hover{transform:translateX(-2px);box-shadow:var(--sh-sm);}
.att-hist-item.present{border-color:#6ee7b7;background:var(--ok-bg);}
.att-hist-item.absent{border-color:#fca5a5;background:var(--err-bg);}
.att-hist-item.unrecorded{border-color:var(--bdr2);background:var(--s2);opacity:.75;}
.ahi-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;}
.present .ahi-dot{background:var(--ok);}
.absent  .ahi-dot{background:var(--err);}
.unrecorded .ahi-dot{background:var(--t5);}
.ahi-info{flex:1;min-width:0;}
.ahi-date{font-size:.9rem;font-weight:800;color:var(--t1);}
.ahi-meta{font-size:.67rem;color:var(--t4);font-weight:500;margin-top:2px;}
.ahi-status{
  padding:3px 10px;border-radius:var(--r-full);
  font-size:.7rem;font-weight:700;flex-shrink:0;
}
.present .ahi-status{background:rgba(5,150,105,.12);color:var(--ok);}
.absent  .ahi-status{background:rgba(220,38,38,.12);color:var(--err);}
.unrecorded .ahi-status{background:var(--bdr2);color:var(--t4);}
.att-hist-empty{text-align:center;padding:32px 16px;color:var(--t4);font-size:.88rem;font-weight:600;}
.att-hist-empty i{display:block;font-size:1.8rem;margin-bottom:10px;opacity:.4;}
.att-hist-count{font-size:.72rem;color:var(--t4);font-weight:600;padding:6px 18px 2px;}

/* ══ CLEAN SCROLLBAR ════════════════════════════════ */
.att-hist-scroll{
  scrollbar-width:thin;
  scrollbar-color:var(--bdr) transparent;
}
.att-hist-scroll::-webkit-scrollbar{width:4px;}
.att-hist-scroll::-webkit-scrollbar-track{background:transparent;}
.att-hist-scroll::-webkit-scrollbar-thumb{
  background:var(--bdr);border-radius:var(--r-full);
}
.att-hist-scroll::-webkit-scrollbar-thumb:hover{background:var(--t5);}
.settings-sheet{
  scrollbar-width:thin;
  scrollbar-color:var(--bdr) transparent;
}
.settings-sheet::-webkit-scrollbar{width:4px;}
.settings-sheet::-webkit-scrollbar-track{background:transparent;}
.settings-sheet::-webkit-scrollbar-thumb{
  background:var(--bdr);border-radius:var(--r-full);
}
.settings-sheet::-webkit-scrollbar-thumb:hover{background:var(--t5);}

/* ══ TRIPS ═══════════════════════════════════════════ */
.trip-card{
  border-radius:var(--r-lg);overflow:hidden;
  border:1px solid var(--bdr);margin-bottom:12px;
  background:var(--surf);transition:var(--norm);cursor:pointer;
}
.trip-card:last-child{margin-bottom:0;}
.trip-card:hover{transform:translateY(-3px);box-shadow:var(--sh-md);border-color:var(--trip-l);}
.trip-thumb{
  width:100%;height:160px;object-fit:cover;display:block;
  background:linear-gradient(135deg,#0c4a6e,#0369a1);
}
.trip-thumb-placeholder{
  width:100%;height:160px;
  background:linear-gradient(135deg,#1e3a5f 0%,#0369a1 50%,#0ea5e9 100%);
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  color:rgba(255,255,255,.5);font-size:2.5rem;gap:8px;
}
.trip-thumb-placeholder span{font-size:.8rem;font-weight:600;}

/* price overlay on thumb */
.trip-thumb-wrap{position:relative;}
.trip-price-overlay{
  position:absolute;bottom:10px;left:10px;
  display:flex;flex-direction:column;gap:4px;
}
.trip-price-pill{
  display:inline-flex;align-items:center;gap:5px;
  padding:4px 12px;border-radius:var(--r-full);
  font-size:.8rem;font-weight:800;
  backdrop-filter:blur(12px);
}
.trip-price-pill.main{
  background:rgba(16,185,129,.9);color:#fff;
  box-shadow:0 2px 8px rgba(0,0,0,.2);
}
.trip-price-pill.remaining{
  background:rgba(220,38,38,.85);color:#fff;
  font-size:.72rem;
}
.trip-status-overlay{
  position:absolute;top:10px;right:10px;
  padding:4px 10px;border-radius:var(--r-full);
  font-size:.7rem;font-weight:700;backdrop-filter:blur(10px);
}
.ts-planned{background:rgba(217,119,6,.88);color:#fff;}
.ts-active{background:rgba(5,150,105,.88);color:#fff;}
.ts-completed{background:rgba(100,116,139,.8);color:#fff;}
.ts-cancelled{background:rgba(220,38,38,.8);color:#fff;}

.trip-body{padding:13px 15px 12px;}
.trip-title{font-size:.95rem;font-weight:800;color:var(--t1);margin-bottom:6px;}
.trip-desc{font-size:.78rem;color:var(--t3);line-height:1.55;margin-bottom:9px;}
.trip-meta-row{display:flex;flex-wrap:wrap;gap:7px;align-items:center;}
.trip-meta-chip{
  display:inline-flex;align-items:center;gap:4px;
  font-size:.71rem;color:var(--t3);
  padding:3px 9px;border-radius:var(--r-full);
  background:var(--s2);border:1px solid var(--bdr);
}
/* not-registered contact bar on trip card */
.trip-contact-bar{
  display:flex;align-items:center;gap:8px;flex-wrap:wrap;
  margin-top:10px;padding:9px 12px;
  background:#fef3c7;border:1.5px solid #fde68a;
  border-radius:var(--r-md);
  font-size:.76rem;font-weight:600;color:#92400e;
  cursor:pointer;transition:var(--fast);
}
.trip-contact-bar:hover{background:#fde68a;}
.trip-contact-bar.unreach{cursor:default;}
.trip-contact-bar.unreach:hover{background:#fef3c7;}
.trip-contact-action{
  margin-right:auto;display:inline-flex;align-items:center;gap:5px;
  padding:4px 11px;border-radius:var(--r-full);
  background:var(--brand);color:#fff;
  font-size:.72rem;font-weight:700;
  transition:var(--fast);
}
.trip-contact-bar:hover .trip-contact-action{background:var(--brand-d);}
/* avatars strip */
.kids-strip{
  display:flex;align-items:center;margin-top:10px;
}
.kids-strip .ka{
  width:28px;height:28px;border-radius:50%;
  border:2px solid var(--surf);
  background:linear-gradient(135deg,var(--brand-bg),#c7d2fe);
  color:var(--brand);font-size:.65rem;font-weight:700;
  display:flex;align-items:center;justify-content:center;
  margin-left:-8px;overflow:hidden;flex-shrink:0;
}
.kids-strip .ka:first-child{margin-left:0;}
.kids-strip .ka img{width:100%;height:100%;object-fit:cover;}
.kids-strip .ka-more{
  background:var(--brand);color:#fff;
  font-size:.6rem;
}

/* ══ TASKS ════════════════════════════════════════════ */
.task-card{
  border-radius:var(--r-md);overflow:hidden;
  border:1px solid var(--bdr);margin-bottom:10px;
  background:var(--surf);transition:var(--fast);cursor:pointer;
  display:flex;
}
.task-card:last-child{margin-bottom:0;}
.task-card:hover{transform:translateX(-2px);box-shadow:var(--sh-sm);border-color:var(--brand-l);}
.task-bar{width:5px;flex-shrink:0;background:linear-gradient(180deg,var(--brand),var(--cou-l));}
.task-bar.done-bar{background:linear-gradient(180deg,var(--ok),#6ee7b7);}
.task-bar.exp-bar{background:var(--t5);}
.task-bar.up-bar{background:linear-gradient(180deg,var(--warn-l),var(--gold-l));}
.task-body{padding:11px 13px;flex:1;}
.task-top{display:flex;align-items:flex-start;gap:8px;margin-bottom:6px;}
.task-title{font-size:.88rem;font-weight:700;color:var(--t1);flex:1;line-height:1.35;}
.task-badge{
  font-size:.66rem;font-weight:700;padding:2px 8px;
  border-radius:var(--r-full);flex-shrink:0;white-space:nowrap;
}
.tb-open{background:var(--ok-bg);color:var(--ok);}
.tb-done{background:var(--brand-bg);color:var(--brand);}
.tb-up{background:var(--warn-bg);color:var(--warn);}
.tb-exp{background:var(--s2);color:var(--t4);border:1px solid var(--bdr);}
.task-metas{display:flex;flex-wrap:wrap;gap:6px;}
.task-meta-chip{
  display:inline-flex;align-items:center;gap:4px;
  font-size:.69rem;color:var(--t4);
}
.task-result{
  display:flex;align-items:center;gap:7px;
  margin-top:7px;padding:7px 10px;
  border-radius:var(--r-sm);
  background:var(--brand-bg);border:1px solid var(--brand-bg);
  font-size:.77rem;color:var(--brand);font-weight:700;
}

/* ══ ANNOUNCEMENTS ═══════════════════════════════════ */
.ann-item{
  padding:13px 15px;border-radius:var(--r-md);
  background:var(--s2);border:1px solid var(--bdr);
  border-right:4px solid var(--brand);
  margin-bottom:9px;transition:var(--fast);
}
.ann-item:last-child{margin-bottom:0;}
.ann-item:hover{transform:translateX(-2px);background:var(--brand-bg);}
.ann-top{display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:5px;}
.ann-type{
  display:inline-flex;align-items:center;gap:4px;
  font-size:.69rem;font-weight:700;
  padding:3px 9px;border-radius:var(--r-full);
  background:var(--brand-bg);color:var(--brand);
}
.ann-date{font-size:.67rem;color:var(--t4);}
.ann-text{font-size:.82rem;color:var(--t1);line-height:1.6;}
.ann-link-btn{
  display:inline-flex;align-items:center;gap:5px;margin-top:8px;
  padding:5px 14px;border-radius:var(--r-full);
  background:linear-gradient(135deg,var(--cou-l),var(--cou));
  color:#fff;font-size:.76rem;font-weight:700;
  text-decoration:none;border:none;cursor:pointer;
  font-family:'Baloo Bhaijaan 2',sans-serif;
  transition:var(--fast);
}
.ann-link-btn:hover{transform:translateY(-2px);box-shadow:var(--sh-md);}

/* ══ OVERLAY / MODAL ═════════════════════════════════ */
.overlay{
  position:fixed;inset:0;background:rgba(10,16,40,.65);
  z-index:500;
  display:flex;align-items:flex-start;justify-content:center;
  padding:12px;overflow-y:auto;
  opacity:0;visibility:hidden;transition:var(--norm);
  overflow: hidden;
}
.overlay.open{opacity:1;visibility:visible;}
.modal{
  background:var(--surf);border-radius:var(--r-xl);
  width:100%;max-width:660px;margin:auto;
  box-shadow:var(--sh-xl);border:1px solid var(--bdr);
  transform:translateY(20px) scale(.97);transition:var(--slow);
}
.overlay.open .modal{transform:translateY(0) scale(1);}
.modal.narrow{max-width:400px;}
.mhdr{
  display:flex;align-items:center;gap:11px;
  padding:16px 18px;border-bottom:1px solid var(--bdr);
  background:linear-gradient(135deg,var(--brand),var(--cou));
  border-radius:var(--r-xl) var(--r-xl) 0 0;
}
.mhdr-title{font-size:.97rem;font-weight:800;color:#fff;flex:1;}
.mhdr-sub{font-size:.7rem;color:rgba(255,255,255,.75);margin-top:1px;}
.mclose{
  width:28px;height:28px;border-radius:var(--r-sm);
  background:rgba(255,255,255,.15);border:none;color:#fff;
  font-size:.85rem;cursor:pointer;
  display:flex;align-items:center;justify-content:center;transition:var(--fast);
}
.mclose:hover{background:rgba(255,255,255,.28);}
.mbody{padding:18px;}
.mfooter{
  padding:12px 18px;border-top:1px solid var(--bdr);
  display:flex;align-items:center;justify-content:flex-end;gap:8px;
  background:var(--s2);border-radius:0 0 var(--r-xl) var(--r-xl);
}

/* ══ TRIP DETAIL MODAL ════════════════════════════════ */
.trip-detail-thumb{
  width:100%;max-height:200px;object-fit:cover;
  border-radius:var(--r-md);margin-bottom:14px;display:block;
}
.trip-detail-ph{
  width:100%;height:120px;border-radius:var(--r-md);margin-bottom:14px;
  background:linear-gradient(135deg,#1e3a5f,#0ea5e9);
  display:flex;align-items:center;justify-content:center;
  color:rgba(255,255,255,.4);font-size:2.5rem;
}
.my-trip-box{
  padding:13px 15px;border-radius:var(--r-md);
  background:var(--ok-bg);border:1px solid #6ee7b7;
  margin-bottom:14px;
}
.my-trip-title{font-size:.8rem;font-weight:700;color:var(--ok);margin-bottom:8px;display:flex;align-items:center;gap:5px;}
.my-trip-row{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
.mtr-cell{text-align:center;padding:8px;background:rgba(255,255,255,.6);border-radius:var(--r-sm);}
.mtr-val{font-size:1.1rem;font-weight:800;color:var(--ok);}
.mtr-lbl{font-size:.65rem;color:var(--t3);font-weight:600;}
.mtr-cell.warn{background:var(--warn-bg);}
.mtr-cell.warn .mtr-val{color:var(--warn);}
.mtr-cell.ok .mtr-val{color:var(--ok);}

.kids-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(72px,1fr));gap:10px;margin-top:4px;}
.kid-tile{
  display:flex;flex-direction:column;align-items:center;gap:4px;
  text-align:center;
}
.kid-tile-av{
  width:52px;height:52px;border-radius:50%;
  background:linear-gradient(135deg,var(--brand-bg),#c7d2fe);
  color:var(--brand);font-size:1.3rem;
  display:flex;align-items:center;justify-content:center;
  overflow:hidden;border:2px solid var(--bdr);
  transition:var(--fast);
}
.kid-tile-av:hover{border-color:var(--brand-l);transform:scale(1.06);}
.kid-tile-av img{width:100%;height:100%;object-fit:cover;}
.kid-tile-name{font-size:.64rem;font-weight:700;color:var(--t2);line-height:1.2;max-width:68px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.kid-tile-cls{font-size:.58rem;color:var(--t4);}

/* ══ EXAM / QUESTIONS ════════════════════════════════ */
.qcard,.qcard-inner{background:var(--s2);border:1.5px solid var(--bdr);border-radius:var(--r-md);margin-bottom:12px;overflow:hidden;}
.open-ans-textarea{width:100%;min-height:110px;padding:11px 13px;border:1.5px solid var(--bdr);border-radius:var(--r-md);font-family:'Baloo Bhaijaan 2',sans-serif;font-size:.9rem;color:var(--t1);background:var(--surf);resize:vertical;outline:none;display:block;line-height:1.6;transition:border-color var(--fast),box-shadow var(--fast);}
.open-ans-textarea:focus{border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-glow);}
.qhdr{display:flex;align-items:center;gap:7px;padding:10px 13px;background:var(--surf);border-bottom:1px solid var(--bdr);}
.qnum{width:22px;height:22px;border-radius:5px;background:linear-gradient(135deg,var(--brand),var(--cou-l));color:#fff;font-size:.68rem;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.qtext{font-size:.85rem;font-weight:700;color:var(--t1);flex:1;}
.qdeg{font-size:.68rem;font-weight:700;color:var(--brand);background:var(--brand-bg);border:1px solid var(--brand-l);padding:2px 7px;border-radius:var(--r-full);}
.qopts{padding:9px 13px;display:flex;flex-direction:column;gap:6px;}
.qopt{display:flex;align-items:center;gap:7px;padding:8px 11px;border-radius:var(--r-sm);border:1.5px solid var(--bdr);background:var(--surf);cursor:pointer;transition:var(--fast);font-size:.81rem;color:var(--t1);}
.qopt:hover{border-color:var(--brand-l);background:var(--brand-bg);}
.qopt.selected{border-color:var(--brand);background:var(--brand-bg);color:var(--brand);font-weight:700;}
.qopt.correct{border-color:var(--ok);background:var(--ok-bg);color:var(--ok);font-weight:700;}
.qopt.wrong{border-color:var(--err);background:var(--err-bg);color:var(--err);}
.oradio{width:15px;height:15px;border-radius:50%;border:2px solid var(--bdr);flex-shrink:0;transition:var(--fast);display:flex;align-items:center;justify-content:center;}
.qopt.selected .oradio{border-color:var(--brand);background:var(--brand);}
.qopt.selected .oradio::after{content:'';width:5px;height:5px;border-radius:50%;background:#fff;}
.qopt.correct .oradio{border-color:var(--ok);background:var(--ok);}
.qopt.correct .oradio::after{content:'✓';font-size:.5rem;color:#fff;font-weight:900;}
.qopt.wrong .oradio{border-color:var(--err);background:var(--err);}
.qopt.wrong .oradio::after{content:'✗';font-size:.5rem;color:#fff;font-weight:900;}
.olet{width:18px;height:18px;border-radius:4px;background:var(--bdr);font-size:.64rem;font-weight:700;color:var(--t3);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.qopt.selected .olet,.qopt.correct .olet,.qopt.wrong .olet{background:transparent;}
.result-card{text-align:center;padding:28px 20px;background:linear-gradient(135deg,var(--brand-bg),#e0e7ff);border-radius:var(--r-lg);border:1px solid var(--brand-l);}
.result-icon{font-size:3rem;margin-bottom:10px;}
.result-score{font-size:2.6rem;font-weight:800;color:var(--brand);}
.result-pct{font-size:.95rem;color:var(--t3);margin-top:3px;font-weight:600;}
.result-coupons{display:inline-flex;align-items:center;gap:6px;margin-top:14px;padding:8px 20px;border-radius:var(--r-full);background:linear-gradient(135deg,var(--cou-l),var(--cou));color:#fff;font-weight:800;font-size:.9rem;}

/* ══ TIMER ════════════════════════════════════════════ */
.timer-wrap{margin-bottom:12px;}
.timer-bar{display:flex;align-items:center;gap:8px;padding:9px 13px;background:var(--warn-bg);border:1px solid #fde68a;border-radius:var(--r-md);}
.timer-bar.urgent{background:var(--err-bg);border-color:#fca5a5;}
.timer-val{font-size:1.05rem;font-weight:800;color:var(--warn);}
.timer-bar.urgent .timer-val{color:var(--err);}
.prog-wrap{margin-bottom:12px;}
.prog-bar{height:5px;background:var(--bdr);border-radius:var(--r-full);overflow:hidden;}
.prog-fill{height:100%;background:linear-gradient(90deg,var(--brand),var(--cou-l));border-radius:var(--r-full);transition:width .4s var(--ease);}
.prog-lbl{display:flex;justify-content:space-between;font-size:.66rem;color:var(--t4);margin-top:3px;}

/* ══ SETTINGS / FAB ══════════════════════════════════ */
/* fab removed */
.settings-list{display:flex;flex-direction:column;gap:7px;}
.s-btn{
  display:flex;align-items:center;gap:11px;
  padding:12px 15px;border-radius:var(--r-md);
  border:1.5px solid var(--bdr);background:var(--surf);
  font-family:'Baloo Bhaijaan 2',sans-serif;font-weight:700;font-size:.88rem;
  color:var(--t1);cursor:pointer;transition:var(--fast);width:100%;text-align:right;
}
.s-btn:hover{background:var(--brand-bg);border-color:var(--brand-l);transform:translateX(-2px);}
.s-btn i{width:20px;color:var(--brand);font-size:1rem;}
.s-btn.danger{color:var(--err);border-color:#fca5a5;}
.s-btn.danger:hover{background:var(--err-bg);}
.s-btn.danger i{color:var(--err);}

/* ══ FORM ════════════════════════════════════════════ */
.fg{display:flex;flex-direction:column;gap:4px;margin-bottom:12px;}
.flbl{font-size:.74rem;font-weight:700;color:var(--t2);}
.fi{padding:10px 12px;border:1.5px solid var(--bdr);border-radius:var(--r-md);font-family:'Baloo Bhaijaan 2',sans-serif;font-size:.87rem;color:var(--t1);background:var(--surf);outline:none;width:100%;transition:var(--fast);}
.fi:focus{border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-glow);}
.pass-wrap{position:relative;}
.pass-wrap .fi{padding-left:42px;}
.pass-eye{position:absolute;left:11px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--t4);font-size:.88rem;cursor:pointer;}

/* ══ BUTTONS ═════════════════════════════════════════ */
.btn{display:inline-flex;align-items:center;gap:5px;padding:8px 17px;border-radius:var(--r-full);font-family:'Baloo Bhaijaan 2',sans-serif;font-size:.84rem;font-weight:700;border:1.5px solid transparent;cursor:pointer;transition:var(--fast);}
.btn-p{background:linear-gradient(135deg,var(--brand),var(--cou));color:#fff;box-shadow:var(--sh-brand);}
.btn-p:hover{transform:translateY(-1px);box-shadow:0 12px 26px -6px rgba(79,70,229,.42);}
.btn-g{background:var(--s2);color:var(--t2);border-color:var(--bdr);}
.btn-g:hover{background:var(--brand-bg);color:var(--brand);border-color:var(--brand-l);}
.btn:disabled{opacity:.5;cursor:not-allowed;transform:none!important;}

/* ══ PHOTO UPLOAD ════════════════════════════════════ */
.upload-drop{border:2.5px dashed var(--bdr);border-radius:var(--r-lg);padding:28px 20px;text-align:center;cursor:pointer;transition:var(--fast);margin-bottom:12px;}
.upload-drop:hover,.upload-drop.over{border-color:var(--brand);background:var(--brand-bg);}
.upload-drop i{font-size:2.4rem;color:var(--brand);display:block;margin-bottom:8px;}
.upload-drop p{font-size:.8rem;color:var(--t3);}
.crop-area{width:100%;height:280px;background:var(--s2);border-radius:var(--r-md);overflow:hidden;margin-bottom:12px;}

/* ══ ACCOUNT SWITCHER ════════════════════════════════ */
.acc-item{display:flex;align-items:center;gap:10px;padding:10px 13px;border-radius:var(--r-md);border:1.5px solid var(--bdr);background:var(--surf);cursor:pointer;transition:var(--fast);margin-bottom:7px;}
.acc-item:last-child{margin-bottom:0;}
.acc-item:hover,.acc-item.active{background:var(--brand-bg);border-color:var(--brand-l);}
.acc-av{width:38px;height:38px;border-radius:50%;background:var(--brand-bg);color:var(--brand);font-size:1rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;border:2px solid var(--bdr);}
.acc-av img{width:100%;height:100%;object-fit:cover;}
.acc-name{font-weight:800;font-size:.84rem;color:var(--t1);}
.acc-cls{font-size:.69rem;color:var(--t4);}

/* ══ LOADING / TOAST / EMPTY ═════════════════════════ */
.loading-screen{position:fixed;inset:0;background:rgba(255,255,255,.96);backdrop-filter:blur(12px);z-index:1000;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:14px;font-weight:700;color:var(--brand);font-size:.9rem;}
.loading-screen.hidden{display:none;}
.spin{display:inline-block;width:38px;height:38px;border:3px solid var(--brand-bg);border-top-color:var(--brand);border-radius:50%;animation:_spin .7s linear infinite;}
.spin-sm{width:14px;height:14px;border-width:2px;border-top-color:#fff;}
@keyframes _spin{to{transform:rotate(360deg)}}
.tc{position:fixed;bottom:18px;right:14px;z-index:9999;display:flex;flex-direction:column;gap:5px;pointer-events:none;}
.toast{display:flex;align-items:center;gap:7px;padding:9px 15px;border-radius:var(--r-full);background:var(--t1);color:#fff;font-size:.81rem;font-weight:700;box-shadow:var(--sh-lg);opacity:0;transform:translateX(16px);transition:var(--norm);pointer-events:auto;white-space:nowrap;max-width:270px;}
.toast.show{opacity:1;transform:translateX(0);}
.toast.ok{background:var(--ok);}
.toast.err{background:var(--err);}
.toast.info{background:var(--brand);}
.empty-st{text-align:center;padding:28px 20px;color:var(--t4);}
.empty-st i{font-size:2rem;display:block;margin-bottom:8px;opacity:.5;}
.empty-st p{font-size:.8rem;font-weight:600;}
.no-profile{text-align:center;padding:60px 20px;background:var(--surf);border-radius:var(--r-xl);box-shadow:var(--sh-md);border:1px solid var(--bdr);max-width:430px;margin:40px auto;}
.no-profile i{font-size:3.2rem;color:var(--t4);display:block;margin-bottom:14px;}
.no-profile h2{font-size:1.05rem;color:var(--t1);margin-bottom:7px;}
.no-profile p{font-size:.82rem;color:var(--t4);margin-bottom:20px;}
.public-banner{background:var(--brand-bg);border:1px solid var(--brand-l);border-radius:var(--r-md);padding:10px 14px;margin-bottom:14px;display:flex;align-items:center;gap:9px;font-size:.8rem;color:var(--brand);font-weight:700;}
.friend-banner{background:#fdf2f8;border:1.5px solid #e879f9;border-radius:var(--r-md);padding:10px 14px;margin-bottom:14px;display:flex;align-items:center;gap:9px;font-size:.8rem;color:#9333ea;font-weight:700;}
/* Search friends */
.friend-search-bar{display:flex;align-items:center;gap:8px;background:var(--surf);border:1.5px solid var(--bdr);border-radius:var(--r-full);padding:8px 14px;transition:var(--fast);}
.friend-search-bar:focus-within{border-color:var(--cou-l);box-shadow:0 0 0 3px rgba(167,139,250,.15);}
.friend-search-bar input{flex:1;border:none;outline:none;background:transparent;font-family:inherit;font-size:.88rem;color:var(--t1);-webkit-user-select:text;user-select:text;}
.friend-search-bar input::placeholder{color:var(--t4);}
.friend-result-card{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:var(--r-md);border:1.5px solid var(--bdr);background:var(--surf);cursor:pointer;transition:var(--fast);margin-bottom:8px;}
.friend-result-card:hover{border-color:var(--cou-l);background:var(--cou-bg);}
.friend-result-av{width:46px;height:46px;border-radius:50%;flex-shrink:0;background:linear-gradient(135deg,var(--cou-bg),#ede9fe);color:var(--cou);font-size:1.1rem;font-weight:800;display:flex;align-items:center;justify-content:center;overflow:hidden;border:2px solid var(--bdr);}
.friend-result-av img{width:100%;height:100%;object-fit:cover;}
.friend-result-info{flex:1;min-width:0;}
.friend-result-name{font-size:.88rem;font-weight:700;color:var(--t1);white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.friend-result-meta{font-size:.72rem;color:var(--t3);margin-top:2px;}
.friend-result-cou{font-size:.78rem;font-weight:700;color:var(--cou);white-space:nowrap;}

/* ══ SETTINGS SHEET ═════════════════════════════════ */
.settings-overlay{align-items:flex-end;padding:0;}
.settings-sheet{
  background:var(--surf);
  border-radius:28px 28px 0 0;
  width:100%;max-width:520px;
  padding:0 0 max(24px,env(safe-area-inset-bottom));
  box-shadow:0 -8px 40px rgba(0,0,0,.14);
  transform:translateY(100%);transition:transform var(--slow);
  max-height:90vh;overflow:hidden;overflow-x:hidden;
  will-change:transform;
  overscroll-behavior:contain;
}
.settings-sheet.ss-scrollable{overflow-y:auto;overflow-x:hidden;}
.overlay.open .settings-sheet{transform:translateY(0);}
.ss-handle{width:38px;height:4px;background:var(--t5);border-radius:var(--r-full);margin:12px auto 0;}
.ss-profile{
  display:flex;align-items:center;gap:14px;
  padding:18px 22px 16px;
  border-bottom:1px solid var(--bdr2);
}
.ss-avatar{
  width:52px;height:52px;border-radius:50%;
  background:linear-gradient(135deg,var(--brand-bg),#c7d2fe);
  color:var(--brand);font-size:1.3rem;
  display:flex;align-items:center;justify-content:center;
  overflow:hidden;border:2px solid var(--bdr);flex-shrink:0;
}
.ss-avatar img{width:100%;height:100%;object-fit:cover;}
.ss-name{font-size:1rem;font-weight:800;color:var(--t1);}
.ss-class{font-size:.75rem;color:var(--t4);font-weight:600;margin-top:2px;}
.ss-items{padding:8px 14px;}
.ss-item{
  display:flex;align-items:center;gap:13px;
  padding:13px 10px;border-radius:var(--r-md);
  cursor:pointer;transition:var(--fast);
}
.ss-item:hover{background:var(--s2);}
.ss-item.danger .ss-item-label{color:var(--err);}
.ss-item-ico{
  width:38px;height:38px;border-radius:var(--r-sm);flex-shrink:0;
  display:flex;align-items:center;justify-content:center;font-size:.92rem;
}
.ss-item-label{font-size:.92rem;font-weight:700;color:var(--t1);flex:1;}
.ss-item-arr{font-size:.72rem;color:var(--t5);}
.ss-divider{height:8px;background:var(--s2);margin:0;}
.ss-close-btn{
  display:block;width:calc(100% - 32px);margin:16px 16px 0;
  padding:13px;border-radius:var(--r-md);
  background:var(--s2);border:1.5px solid var(--bdr);
  font-family:'Baloo Bhaijaan 2',sans-serif;font-size:.9rem;font-weight:700;
  color:var(--t2);cursor:pointer;transition:var(--fast);
}
.ss-close-btn:hover{background:var(--bdr);}

/* ══ UNCLE STRIP ══════════════════════════════════════ */
.uncle-strip{
  display:inline-flex!important;align-items:center;gap:7px;
  background:#fff;
  border:1.5px solid rgba(99,102,241,.2);
  border-radius:var(--r-full);
  padding:5px 12px 5px 7px;
  cursor:pointer;
  box-shadow:0 2px 8px rgba(0,0,0,.10);
  transition:var(--fast);
}
.uncle-strip:hover{background:#f5f3ff;border-color:var(--cou-l);}
.uncle-strip-label{
  font-size:.68rem;font-weight:700;color:var(--cou);
  white-space:nowrap;letter-spacing:.01em;
}
.uncle-avatars{display:flex;align-items:center;}
.ua{
  width:24px;height:24px;border-radius:50%;
  border:2px solid #fff;
  background:linear-gradient(135deg,#6366f1,#a78bfa);
  color:#fff;font-size:.58rem;font-weight:800;
  display:flex;align-items:center;justify-content:center;
  overflow:hidden;margin-left:-6px;flex-shrink:0;
  box-shadow:0 1px 4px rgba(0,0,0,.18);
  transition:var(--fast);
}
.ua:first-child{margin-left:0;}
.ua img{width:100%;height:100%;object-fit:cover;}
.ua-more{
  font-size:.58rem;color:var(--cou);font-weight:800;
  padding:0 6px;border-radius:var(--r-full);height:24px;
  display:flex;align-items:center;margin-left:5px;
  border:1.5px solid var(--cou-l);
  background:var(--cou-bg);
  white-space:nowrap;flex-shrink:0;
}

/* ══ ATTENDANCE NARROWER ══════════════════════════════ */
.cal-day{
  padding:7px 5px!important;
}
.cd-num{font-size:1rem!important;}

/* ══ RESPONSIVE ══════════════════════════════════════ */
@media(max-width:580px){
  .coupon-hero{grid-template-columns:1fr;}
  .ch-breakdown{flex-direction:row;flex-wrap:wrap;}
  .cal-grid{grid-template-columns:repeat(auto-fill,minmax(70px,1fr));}
  .info-grid{grid-template-columns:1fr 1fr;}
  .stats-bar{grid-template-columns:repeat(2,1fr);}
  .sb-cell:nth-child(3){border-top:1px solid var(--bdr);}
  .sb-cell:nth-child(4){border-top:1px solid var(--bdr);}
}
@media(max-width:360px){
  .info-grid{grid-template-columns:1fr;}
  .hero-name{font-size:1.4rem;}
}
/* ══ UNCLE CARDS ═════════════════════════════════════ */
.uncle-card{
  display:flex;flex-direction:column;align-items:center;
  gap:8px;cursor:pointer;
  padding:12px 8px;border-radius:var(--r-lg);
  border:1.5px solid var(--bdr);background:var(--surf);
  transition:var(--fast);text-align:center;
}
.uncle-card:hover{
  border-color:var(--cou-l);background:var(--cou-bg);
  transform:translateY(-2px);box-shadow:var(--sh-sm);
}
.uncle-card-av{
  width:64px;height:64px;border-radius:50%;
  background:linear-gradient(135deg,var(--cou-bg),#ede9fe);
  color:var(--cou);font-size:1.5rem;font-weight:800;
  display:flex;align-items:center;justify-content:center;
  overflow:hidden;border:2px solid var(--bdr);
  flex-shrink:0;transition:var(--fast);
}
.uncle-card:hover .uncle-card-av{border-color:var(--cou-l);}
.uncle-card-av img{width:100%;height:100%;object-fit:cover;}
.uncle-card-name{
  font-size:.75rem;font-weight:700;color:var(--t1);
  line-height:1.3;word-break:break-word;
}
.uncle-card-role{font-size:.65rem;color:var(--t4);font-weight:600;}

/* uncle drawer profile */
.uncle-drawer-hero{
  background:linear-gradient(145deg,#4c1d95,#7c3aed);
  padding:24px 22px 22px;display:flex;align-items:center;gap:16px;
  border-radius:28px 28px 0 0;
}
.uncle-drawer-av{
  width:70px;height:70px;border-radius:50%;flex-shrink:0;
  background:rgba(255,255,255,.18);
  border:3px solid rgba(255,255,255,.4);
  overflow:hidden;display:flex;align-items:center;justify-content:center;
  font-size:1.8rem;color:#fff;font-weight:800;
}
.uncle-drawer-av img{width:100%;height:100%;object-fit:cover;}
.uncle-drawer-name{font-size:1.15rem;font-weight:800;color:#fff;line-height:1.25;}
.uncle-drawer-role{font-size:.75rem;color:rgba(255,255,255,.75);margin-top:3px;font-weight:600;}
.uncle-action-btn{
  display:flex;align-items:center;gap:12px;
  padding:14px 18px;border-radius:var(--r-md);
  border:1.5px solid var(--bdr);background:var(--surf);
  cursor:pointer;transition:var(--fast);width:100%;
  font-family:'Baloo Bhaijaan 2',sans-serif;font-size:.93rem;font-weight:700;color:var(--t1);
}
.uncle-action-btn:hover{background:var(--s2);}
.uncle-action-btn.call{border-color:#6ee7b7;}
.uncle-action-btn.call:hover{background:var(--ok-bg);}
.uncle-action-btn.wa{border-color:#86efac;}
.uncle-action-btn.wa:hover{background:#f0fdf4;}
.uncle-action-ico{
  width:40px;height:40px;border-radius:var(--r-sm);flex-shrink:0;
  display:flex;align-items:center;justify-content:center;font-size:1.1rem;
}

/* ══ EXAM SCREEN ════════════════════════════════════════ */
#examScreen{font-family:'Baloo Bhaijaan 2',sans-serif;}

/* ── Start card ── */
.exam-start-card{
  background:var(--surf);border-radius:var(--r-xl);
  border:1px solid var(--bdr);box-shadow:var(--sh-lg);overflow:hidden;
}
.exam-start-hero{
  background:linear-gradient(145deg,#312e81 0%,#4f46e5 45%,#7c3aed 100%);
  padding:32px 24px 28px;text-align:center;position:relative;overflow:hidden;
}
.exam-start-hero::before{
  content:'';position:absolute;inset:0;
  background:radial-gradient(circle at 20% 30%,rgba(255,255,255,.07) 0%,transparent 50%),
             radial-gradient(circle at 80% 70%,rgba(255,255,255,.05) 0%,transparent 40%);
}
.exam-start-hero::after{
  content:'';position:absolute;inset:0;pointer-events:none;
  background-image:radial-gradient(circle,rgba(255,255,255,.06) 1px,transparent 1px);
  background-size:24px 24px;
}
.exam-start-icon{
  position:relative;z-index:1;
  width:64px;height:64px;border-radius:50%;
  background:rgba(255,255,255,.15);backdrop-filter:blur(8px);
  border:2px solid rgba(255,255,255,.3);
  display:flex;align-items:center;justify-content:center;
  margin:0 auto 16px;font-size:1.7rem;color:#fff;
}
.exam-start-title{
  position:relative;z-index:1;
  font-size:1.3rem;font-weight:800;color:#fff;margin-bottom:5px;line-height:1.25;
}
.exam-start-sub{
  position:relative;z-index:1;
  font-size:.82rem;color:rgba(255,255,255,.78);font-weight:600;
}
.exam-meta-row{
  display:flex;align-items:center;gap:12px;
  padding:13px 16px;border-radius:var(--r-md);
  font-weight:700;font-size:.88rem;
}
.exam-meta-row i{font-size:1.05rem;flex-shrink:0;}
.exam-meta-row .em-text{}
.exam-meta-row .em-sub{font-size:.7rem;font-weight:500;color:var(--t4);margin-top:2px;}

/* ── Active exam header ── */
.exam-hdr{
  position:sticky;top:0;z-index:10;
  background:var(--surf);border-bottom:1px solid var(--bdr);
  box-shadow:0 2px 12px rgba(0,0,0,.06);
}
.exam-hdr-inner{
  display:flex;align-items:center;gap:10px;
  padding:10px 16px;
}
.exam-back-btn{
  width:36px;height:36px;border-radius:50%;
  border:1.5px solid var(--bdr);background:var(--s2);
  color:var(--t2);cursor:pointer;
  display:flex;align-items:center;justify-content:center;flex-shrink:0;
  transition:var(--fast);
}
.exam-back-btn:hover{background:var(--brand-bg);border-color:var(--brand-l);color:var(--brand);}
.exam-hdr-title{
  font-size:.92rem;font-weight:800;color:var(--t1);
  overflow:hidden;text-overflow:ellipsis;white-space:nowrap;flex:1;
}
.exam-hdr-sub{font-size:.67rem;color:var(--t4);margin-top:1px;}
.exam-timer{
  display:none;padding:5px 13px;border-radius:var(--r-full);
  font-size:.88rem;font-weight:800;white-space:nowrap;flex-shrink:0;
  background:var(--warn-bg);border:1.5px solid #fde68a;color:var(--warn);
  font-family:'Baloo Bhaijaan 2',sans-serif;
  transition:background var(--fast),color var(--fast),border-color var(--fast);
}
.exam-timer.urgent{background:var(--err-bg);border-color:#fca5a5;color:var(--err);}
.exam-prog-track{height:3px;background:var(--bdr);}
.exam-prog-fill{
  height:100%;
  background:linear-gradient(90deg,var(--brand),var(--cou-l));
  transition:width .35s var(--ease);width:0%;
}

/* ── Questions area ── */
.exam-questions{
  padding:18px 16px 130px;
  max-width:720px;margin:0 auto;width:100%;
}

/* ── Sticky submit footer ── */
.exam-footer{
  position:sticky;bottom:0;z-index:10;
  background:rgba(255,255,255,.95);backdrop-filter:blur(14px);
  border-top:1px solid var(--bdr);
  padding:10px 16px max(10px,env(safe-area-inset-bottom));
  box-shadow:0 -4px 20px rgba(0,0,0,.07);
  margin-top:auto;
}
.exam-footer-inner{
  max-width:720px;margin:0 auto;
  display:flex;align-items:center;gap:10px;
}
.exam-ans-count{
  flex:1;font-size:.8rem;color:var(--t4);font-weight:600;
  display:flex;align-items:center;gap:5px;
}
.exam-ans-count strong{color:var(--t1);font-size:.95rem;}

/* ── Result card ── */
.exam-result-wrap{
  width:100%;max-width:440px;
}
.exam-result-card{
  border-radius:var(--r-xl);overflow:hidden;
  box-shadow:var(--sh-lg);border:1px solid var(--bdr);
  margin-bottom:14px;
}
.exam-result-hero{
  padding:36px 24px 28px;text-align:center;
  position:relative;overflow:hidden;
}
.exam-result-icon-ring{
  width:80px;height:80px;border-radius:50%;
  display:flex;align-items:center;justify-content:center;
  margin:0 auto 16px;font-size:2rem;
  border:3px solid rgba(255,255,255,.4);
  background:rgba(255,255,255,.2);
  backdrop-filter:blur(8px);
}
.exam-result-score-big{
  font-size:3.4rem;font-weight:800;color:#fff;line-height:1;
  text-shadow:0 2px 16px rgba(0,0,0,.2);
}
.exam-result-pct{
  font-size:1rem;color:rgba(255,255,255,.85);margin-top:4px;font-weight:700;
}
.exam-result-body{padding:20px 22px;}
.exam-result-msg{
  font-size:1rem;font-weight:700;color:var(--t1);
  text-align:center;margin-bottom:14px;line-height:1.5;
}
.exam-result-coupons{
  display:flex;align-items:center;justify-content:center;gap:8px;
  padding:11px 20px;border-radius:var(--r-full);
  background:linear-gradient(135deg,var(--cou-l),var(--cou));
  color:#fff;font-weight:800;font-size:.95rem;
  box-shadow:0 4px 14px rgba(124,58,237,.35);
}
</style>
</head>
<body>

<div class="loading-screen" id="ls"><div class="spin"></div><span id="lt">جارٍ التحميل…</span></div>

<!-- ══ HERO ══ -->
<div class="hero" id="hero" style="display:none">
  <div class="hero-top">
    <div class="hero-church-chip" id="churchChip" style="display:none">
      <i class="fas fa-church"></i><span id="churchName"></span>
    </div>
    <div class="hero-actions-top">
      <div class="hero-ico-btn" id="switchBtnTop" style="display:none" onclick="openOv('switchOv')" title="تبديل الحساب"><i class="fas fa-exchange-alt"></i></div>
      <div class="hero-ico-btn" id="settingsTop" style="display:none" onclick="openOv('settingsOv')" title="الإعدادات"><i class="fas fa-cog"></i></div>
    </div>
  </div>
  <div class="hero-body">
    <div class="avatar-ring" id="avatarRing">
      <div class="avatar-inner" id="avatarInner"><i class="fas fa-user"></i></div>
      <div class="avatar-edit-fab" id="avatarEdit" onclick="openOv('photoOv')"><i class="fas fa-camera"></i></div>
    </div>
    <div class="hero-name" id="heroName">—</div>
    <div class="hero-tags" id="heroTags">
      <span class="htag class-tag" id="heroClass"><i class="fas fa-graduation-cap"></i><span id="heroClassTxt">—</span></span>
      <div class="uncle-strip" id="uncleStrip" style="display:none"></div>
    </div>
  </div>
  <!-- Coupon hero card (private only) -->
  <div class="coupon-hero" id="couponHero" style="display:none">
    <div>
      <div class="ch-total-label"><i class="fas fa-star"></i> إجمالي كوبوناتك</div>
      <div class="ch-total-val" id="chTotal">0</div>
      <div class="ch-total-unit">كوبون</div>
    </div>
    <div class="ch-breakdown" id="chBreakdown"></div>
  </div>
  <div class="hero-wave"></div>
</div>

<!-- stats bar -->
<div class="stats-bar" id="statsBar" style="display:none">
  <div class="sb-cell ok"><div class="sb-val" id="sbP">0</div><div class="sb-lbl">حضر</div></div>
  <div class="sb-cell err"><div class="sb-val" id="sbA">0</div><div class="sb-lbl">غاب</div></div>
  <div class="sb-cell neu"><div class="sb-val" id="sbR">0%</div><div class="sb-lbl">نسبة</div></div>
  <div class="sb-cell cou"><div class="sb-val" id="sbC">0</div><div class="sb-lbl">كوبون</div></div>
</div>

<!-- Page -->
<div class="page" id="mainPage" style="display:none">

  <!-- public banner -->
  <div class="public-banner" id="pubBanner" style="display:none">
    <i class="fas fa-eye"></i> عرض عام
    <a href="/kids/login" style="margin-right:auto;color:var(--brand);text-decoration:none;font-size:.74rem;font-weight:800;"><i class="fas fa-sign-in-alt"></i> دخول</a>
  </div>

  <!-- Friend mode banner -->
  <div class="friend-banner" id="friendBanner" style="display:none">
    <i class="fas fa-user-friends"></i>
    <span id="friendBannerName">ملف صديق</span>
    <button onclick="returnToMyProfile()" style="margin-right:auto;background:none;border:none;color:#9333ea;font-size:.8rem;font-weight:800;cursor:pointer;font-family:inherit;padding:0;display:flex;align-items:center;gap:5px;"><i class="fas fa-arrow-right"></i> رجوع</button>
  </div>
  <!-- Search friends (private only) -->
  <div class="sc" id="scSearch" style="display:none">
    <div class="sc-head">
      <div class="sc-ico" style="background:#fdf2f8;color:#9333ea;"><i class="fas fa-search"></i></div>
      <div class="sc-label"><div class="sc-title">ابحث عن صحبك في الكنيسة</div></div>
    </div>
    <div class="sc-body">
      <div class="friend-search-bar">
        <i class="fas fa-search" style="color:var(--t4);font-size:.85rem;"></i>
        <input type="text" id="friendSearchInput" placeholder="ابحث بالاسم…" oninput="onFriendSearch(this.value)" autocomplete="off">
        <button onclick="document.getElementById('friendSearchInput').value='';document.getElementById('friendSearchResults').innerHTML=''" style="background:none;border:none;color:var(--t4);cursor:pointer;padding:0;font-size:.85rem;"><i class="fas fa-times"></i></button>
      </div>
      <div id="friendSearchResults" style="margin-top:12px;"></div>
    </div>
  </div>
    
  <!-- Info -->
  <div class="sc" id="scInfo">
    <div class="sc-head">
      <div class="sc-ico" style="background:#e0e7ff;color:var(--brand);"><i class="fas fa-id-card"></i></div>
      <div class="sc-label"><div class="sc-title">المعلومات الشخصية</div></div>
    </div>
    <div class="sc-body"><div class="info-grid" id="infoGrid"></div></div>
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
        <div class="as ok"><div class="as-val" id="ap">0</div><div class="as-lbl">حضر</div></div>
        <div class="as err"><div class="as-val" id="aa">0</div><div class="as-lbl">غاب</div></div>
        <div class="as neu"><div class="as-val" id="ar">0%</div><div class="as-lbl">نسبة</div></div>
      </div>
      <div class="cal-grid" id="calGrid"></div>
      <div style="text-align:center;margin-top:4px;">
        <span class="att-view-all" id="attViewAllBtn" onclick="openAttHistory()">
          <i class="fas fa-list-ul"></i> عرض السجل الكامل
        </span>
      </div>
    </div>
  </div>

  <!-- Tasks -->
  <div class="sc" id="scTasks" style="display:none">
    <div class="sc-head">
      <div class="sc-ico" style="background:var(--brand-bg);color:var(--brand);"><i class="fas fa-tasks"></i></div>
      <div class="sc-label"><div class="sc-title">الاختبارات والمهام</div><div class="sc-sub" id="taskSub"></div></div>
    </div>
    <div class="sc-body"><div id="taskList"></div></div>
  </div>

  <!-- Trips -->
  <div class="sc" id="scTrips" style="display:none">
    <div class="sc-head">
      <div class="sc-ico" style="background:var(--trip-bg);color:var(--trip-l);"><i class="fas fa-bus"></i></div>
      <div class="sc-label"><div class="sc-title">الرحلات</div><div class="sc-sub" id="tripSub"></div></div>
    </div>
    <div class="sc-body"><div id="tripList"></div></div>
  </div>

  <!-- Announcements -->
  <div class="sc" id="scAnn" style="display:none">
    <div class="sc-head">
      <div class="sc-ico" style="background:var(--warn-bg);color:var(--warn-l);"><i class="fas fa-bullhorn"></i></div>
      <div class="sc-label"><div class="sc-title">الإعلانات</div></div>
    </div>
    <div class="sc-body"><div id="annList"></div></div>
  </div>

  <!-- Uncles section -->
  <div class="sc" id="scUncles" style="display:none;">
    <div class="sc-head">
      <div class="sc-ico" style="background:#ede9fe;color:#7c3aed;"><i class="fas fa-chalkboard-teacher"></i></div>
      <div class="sc-label"><div class="sc-title">انكل وطنط اللي معاك في الفصل</div><div class="sc-sub" id="unclesSub"></div></div>
    </div>
    <div class="sc-body">
      <div id="uncleCardGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(100px,1fr));gap:14px;"></div>
    </div>
  </div>



  <div style="text-align:center;padding:18px 0 0;font-size:.72rem;color:var(--t4);">
    <span style="font-weight:700;">نظام مدارس الأحد ٢٠٢٦</span><br>
   <!-- مُكْثِرِينَ فِي عَمَلِ الرَّبِّ كُلَّ حِينٍ-->
  </div>
</div>

<div class="no-profile" id="noProfile" style="display:none">
  <i class="fas fa-user-slash"></i>
  <h2>لم يُعثر على ملف شخصي</h2>
  <p id="noMsg">يرجى تسجيل الدخول أو استخدام رابط المعرّف</p>
  <a href="/kids/login" class="btn btn-p"><i class="fas fa-sign-in-alt"></i> تسجيل الدخول</a>
</div>


<!-- ══ ATTENDANCE HISTORY SHEET ══ -->
<div class="overlay settings-overlay" id="attHistOv">
  <div class="settings-sheet" style="max-width:600px;max-height:92vh;">
    <div class="ss-handle"></div>

    <!-- Header -->
    <div style="padding:14px 20px 12px;border-bottom:1px solid var(--bdr2);display:flex;align-items:center;gap:12px;">
      <div style="width:38px;height:38px;border-radius:var(--r-sm);background:var(--ok-bg);color:var(--ok);display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0;">
        <i class="fas fa-calendar-check"></i>
      </div>
      <div style="flex:1;">
        <div style="font-size:1rem;font-weight:800;color:var(--t1);">سجل الحضور الكامل</div>
        <div style="font-size:.72rem;color:var(--t4);font-weight:600;" id="attHistSubtitle">جارٍ التحميل…</div>
      </div>
      <button onclick="closeOv('attHistOv')" style="width:30px;height:30px;border-radius:var(--r-sm);border:1.5px solid var(--bdr);background:var(--s2);color:var(--t3);font-size:.82rem;cursor:pointer;display:flex;align-items:center;justify-content:center;">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <!-- Summary -->
    <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;padding:12px 16px;border-bottom:1px solid var(--bdr2);">
      <div style="text-align:center;padding:10px 6px;border-radius:var(--r-md);background:var(--ok-bg);border:1px solid #6ee7b7;">
        <div style="font-size:1.2rem;font-weight:800;color:var(--ok);" id="ahsPresent">0</div>
        <div style="font-size:.6rem;color:var(--t4);margin-top:2px;font-weight:600;">حضر</div>
      </div>
      <div style="text-align:center;padding:10px 6px;border-radius:var(--r-md);background:var(--err-bg);border:1px solid #fca5a5;">
        <div style="font-size:1.2rem;font-weight:800;color:var(--err);" id="ahsAbsent">0</div>
        <div style="font-size:.6rem;color:var(--t4);margin-top:2px;font-weight:600;">غاب</div>
      </div>
      <div style="text-align:center;padding:10px 6px;border-radius:var(--r-md);background:var(--s2);border:1px solid var(--bdr);">
        <div style="font-size:1.2rem;font-weight:800;color:var(--brand);" id="ahsRate">0%</div>
        <div style="font-size:.6rem;color:var(--t4);margin-top:2px;font-weight:600;">نسبة الحضور</div>
      </div>
    </div>

    <!-- Filters -->
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;padding:10px 16px;border-bottom:1px solid var(--bdr2);background:var(--s2);">
      <input id="attHistSearch" type="text" placeholder="ابحث بالتاريخ…"
        style="flex:1;min-width:120px;padding:8px 12px;border:1.5px solid var(--bdr);border-radius:var(--r-sm);font-family:'Baloo Bhaijaan 2',sans-serif;font-size:.86rem;background:var(--surf);color:var(--t1);outline:none;"
        oninput="renderAttHist()" />
      <select id="attHistSort" onchange="renderAttHist()"
        style="padding:7px 10px;border:1.5px solid var(--bdr);border-radius:var(--r-sm);font-family:'Baloo Bhaijaan 2',sans-serif;font-size:.8rem;background:var(--surf);color:var(--t2);outline:none;">
        <option value="newest">الأحدث أولاً</option>
        <option value="oldest">الأقدم أولاً</option>
      </select>
      <div style="display:flex;gap:6px;flex-wrap:wrap;">
        <span class="fchip active" data-filter="all"        onclick="setAttFilter(this,'all')">الكل</span>
        <span class="fchip ok"     data-filter="present"    onclick="setAttFilter(this,'present')">✓ حضر</span>
        <span class="fchip err"    data-filter="absent"     onclick="setAttFilter(this,'absent')">✗ غاب</span>
        <span class="fchip"        data-filter="unrecorded" onclick="setAttFilter(this,'unrecorded')">— غير مسجّل</span>
      </div>
    </div>

    <!-- Count -->
    <div id="attHistCount" style="font-size:.7rem;color:var(--t4);font-weight:600;padding:6px 16px 2px;"></div>

    <!-- List (scrollable) -->
    <div id="attHistList" class="att-hist-scroll"
      style="padding:6px 14px 12px;overflow-y:auto;max-height:calc(92vh - 290px);display:flex;flex-direction:column;gap:5px;">
      <div style="text-align:center;padding:28px;color:var(--t4);font-size:.88rem;">
        <i class="fas fa-spinner fa-spin" style="display:block;font-size:1.6rem;margin-bottom:8px;opacity:.4;"></i>جارٍ التحميل…
      </div>
    </div>

  </div>
</div>

<!-- ══ REPORT ATTENDANCE ERROR (WhatsApp) SHEET ══ -->
<div class="overlay settings-overlay" id="attReportOv">
  <div class="settings-sheet" style="max-width:480px;">
    <div class="ss-handle"></div>
    <div style="padding:14px 20px 12px;border-bottom:1px solid var(--bdr2);display:flex;align-items:center;gap:12px;">
      <div style="width:36px;height:36px;border-radius:var(--r-sm);background:#fef3c7;color:#d97706;display:flex;align-items:center;justify-content:center;font-size:.92rem;flex-shrink:0;">
        <i class="fas fa-flag"></i>
      </div>
      <div style="flex:1;">
        <div style="font-size:.96rem;font-weight:800;color:var(--t1);">بلّغ عن خطأ</div>
        <div style="font-size:.72rem;color:var(--t4);font-weight:600;" id="reportDateLabel"></div>
      </div>
      <button onclick="closeOv('attReportOv')" style="width:28px;height:28px;border-radius:var(--r-sm);border:1.5px solid var(--bdr);background:var(--s2);color:var(--t3);font-size:.8rem;cursor:pointer;display:flex;align-items:center;justify-content:center;">
        <i class="fas fa-times"></i>
      </button>
    </div>

    <!-- Only picker: what SHOULD it be -->
    <div style="padding:16px 16px 12px;border-bottom:1px solid var(--bdr2);">
      <div style="font-size:.74rem;font-weight:700;color:var(--t3);margin-bottom:10px;">المفروض أنا كنت</div>
      <div style="display:flex;gap:10px;">
        <button id="reportShouldPresent" onclick="setReportShould('present')"
          style="flex:1;padding:14px 8px;border-radius:var(--r-md);border:2px solid var(--bdr);background:var(--surf);color:var(--t2);font-family:'Baloo Bhaijaan 2',sans-serif;font-size:.95rem;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;transition:var(--fast);">
          <i class="fas fa-check-circle"></i> حضر
        </button>
        <button id="reportShouldAbsent" onclick="setReportShould('absent')"
          style="flex:1;padding:14px 8px;border-radius:var(--r-md);border:2px solid var(--bdr);background:var(--surf);color:var(--t2);font-family:'Baloo Bhaijaan 2',sans-serif;font-size:.95rem;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;transition:var(--fast);">
          <i class="fas fa-times-circle"></i> غاب
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
      <div class="ss-item" onclick="closeOv('settingsOv');setTimeout(()=>openOv('passOv'),180)">
        <div class="ss-item-ico" style="background:#fef3c7;color:#92400e;"><i class="fas fa-lock"></i></div>
        <div class="ss-item-label">تغيير كلمة المرور</div>
        <i class="fas fa-chevron-left ss-item-arr"></i>
      </div>
      <div class="ss-item" onclick="closeOv('settingsOv');setTimeout(()=>openOv('photoOv'),180)">
        <div class="ss-item-ico" style="background:#d1fae5;color:#065f46;"><i class="fas fa-camera"></i></div>
        <div class="ss-item-label">تغيير الصورة الشخصية</div>
        <i class="fas fa-chevron-left ss-item-arr"></i>
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
        <div style="width:36px;height:36px;border-radius:var(--r-sm);background:#e0e7ff;color:#4338ca;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-user-edit"></i></div>
        تعديل المعلومات
      </div>
    </div>
    <div style="padding:18px 22px;">
      <div class="fg"><label class="flbl">الاسم</label><input class="fi" id="eN" type="text"></div>
      <div class="fg"><label class="flbl">العنوان</label><input class="fi" id="eA" type="text"></div>
      <div class="fg"><label class="flbl">التليفون</label><input class="fi" id="eP" type="tel"></div>
      <div class="fg" style="margin-bottom:0;"><label class="flbl">تاريخ الميلاد</label><input class="fi" id="eB" type="text" placeholder="DD/MM/YYYY"></div>
    </div>
    <div style="padding:8px 22px 0;">
      <button class="btn btn-p" style="width:100%;padding:12px;" onclick="saveProfile()"><i class="fas fa-save"></i> حفظ المعلومات</button>
    </div>
    <button class="ss-close-btn" onclick="closeOv('editOv')">إغلاق</button>
  </div>
</div>

<!-- Change Password -->
<div class="overlay settings-overlay" id="passOv">
  <div class="settings-sheet">
    <div class="ss-handle"></div>
    <div style="padding:18px 22px 8px;border-bottom:1px solid var(--bdr2);">
      <div style="font-size:1.05rem;font-weight:800;color:var(--t1);display:flex;align-items:center;gap:10px;">
        <div style="width:36px;height:36px;border-radius:var(--r-sm);background:#fef3c7;color:#92400e;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-lock"></i></div>
        تغيير كلمة المرور
      </div>
    </div>
    <div style="padding:18px 22px;">
      <div class="fg"><label class="flbl">الحالية</label><div class="pass-wrap"><input class="fi" id="po" type="password"><button type="button" class="pass-eye" onclick="tPass('po',this)"><i class="fas fa-eye"></i></button></div></div>
      <div class="fg"><label class="flbl">الجديدة (٦ أحرف+)</label><div class="pass-wrap"><input class="fi" id="pn" type="password"><button type="button" class="pass-eye" onclick="tPass('pn',this)"><i class="fas fa-eye"></i></button></div></div>
      <div class="fg" style="margin-bottom:0;"><label class="flbl">تأكيد الجديدة</label><div class="pass-wrap"><input class="fi" id="pc" type="password"><button type="button" class="pass-eye" onclick="tPass('pc',this)"><i class="fas fa-eye"></i></button></div></div>
    </div>
    <div style="padding:8px 22px 0;">
      <button class="btn btn-p" style="width:100%;padding:12px;" onclick="changePass()"><i class="fas fa-lock"></i> تغيير كلمة المرور</button>
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
        <div style="width:36px;height:36px;border-radius:var(--r-sm);background:#d1fae5;color:#065f46;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-camera"></i></div>
        تغيير الصورة الشخصية
      </div>
    </div>
    <div style="padding:18px 22px;">
      <div class="upload-drop" id="dropZone" onclick="document.getElementById('photoIn').click()">
        <i class="fas fa-cloud-upload-alt"></i>
        <p>اضغط أو اسحب صورة هنا</p>
        <input type="file" id="photoIn" accept="image/*" style="display:none" onchange="onPhoto(event)">
      </div>
      <div id="cropWrap" style="display:none"><div class="crop-area"><img id="cropImg" src="" alt=""></div></div>
      <img id="photoPrev" src="" style="display:none;width:100px;height:100px;border-radius:50%;object-fit:cover;margin:0 auto 12px;border:3px solid var(--brand);">
    </div>
    <div style="padding:8px 22px 0;display:flex;gap:8px;">
      <button class="btn btn-p" id="cropBtn" style="display:none;width:100%;padding:12px;" onclick="doCrop()"><i class="fas fa-crop-alt"></i> قص الصورة</button>
      <button class="btn btn-p" id="uploadBtn" style="display:none;width:100%;padding:12px;" onclick="uploadPhoto()"><i class="fas fa-upload"></i> رفع الصورة</button>
    </div>
    <button class="ss-close-btn" onclick="closeOv('photoOv');resetPhoto()">إغلاق</button>
  </div>
</div>

<!-- Account Switch -->
<div class="overlay settings-overlay" id="switchOv">
  <div class="settings-sheet">
    <div class="ss-handle"></div>
    <div style="padding:18px 22px 8px;border-bottom:1px solid var(--bdr2);">
      <div style="font-size:1.05rem;font-weight:800;color:var(--t1);display:flex;align-items:center;gap:10px;">
        <div style="width:36px;height:36px;border-radius:var(--r-sm);background:var(--brand-bg);color:var(--brand);display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-exchange-alt"></i></div>
        تبديل الحساب
      </div>
    </div>
    <div id="switchList" style="padding:10px 16px;"></div>
    <div style="padding:8px 22px 0;">
      <button class="btn btn-p" style="width:100%;padding:12px;" onclick="doSwitch()"><i class="fas fa-exchange-alt"></i> تبديل الحساب</button>
    </div>
    <button class="ss-close-btn" onclick="closeOv('switchOv')">إغلاق</button>
  </div>
</div>

<!-- Trip Detail -->
<div class="overlay settings-overlay" id="tripOv">
  <div class="settings-sheet" style="max-height:92vh;">
    <div class="ss-handle"></div>
    <div style="padding:14px 22px 10px;border-bottom:1px solid var(--bdr2);display:flex;align-items:center;gap:10px;">
      <div style="width:36px;height:36px;border-radius:var(--r-sm);background:var(--trip-bg);color:var(--trip-l);display:flex;align-items:center;justify-content:center;flex-shrink:0;flex-shrink:0;"><i class="fas fa-bus"></i></div>
      <div style="flex:1;min-width:0;">
        <div id="tripOvTitle" style="font-size:1rem;font-weight:800;color:var(--t1);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></div>
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
<div id="examScreen" style="display:none;position:fixed;inset:0;z-index:800;background:var(--bg);overflow-y:auto;-webkit-overflow-scrolling:touch;">

  <!-- ① Start / confirmation view -->
  <div id="examStartView" style="display:none;flex-direction:column;align-items:center;justify-content:center;padding:20px 16px;min-height:100vh;">
    <div style="width:100%;max-width:460px;">
      <div class="exam-start-card">
        <div class="exam-start-hero">
          <div class="exam-start-icon"><i class="fas fa-pen-nib"></i></div>
          <div class="exam-start-title" id="startTitle"></div>
          <div class="exam-start-sub" id="startSub"></div>
        </div>
        <div id="startMeta" style="padding:16px 18px;display:flex;flex-direction:column;gap:8px;"></div>
        <div style="padding:0 18px 20px;display:flex;gap:10px;">
          <button class="btn btn-g" style="flex:1;" onclick="exitExamScreen()"><i class="fas fa-chevron-right"></i> رجوع</button>
          <button class="btn btn-p" id="examStartBtn" style="flex:2;padding:12px;font-size:.97rem;" onclick="beginExam()"><i class="fas fa-play-circle"></i> ابدأ الاختبار</button>
        </div>
      </div>
    </div>
  </div>

  <!-- ② Active exam view -->
  <div id="examActiveView" style="display:none;flex-direction:column;min-height:100vh;position:relative;">
    <div class="exam-hdr">
      <div class="exam-hdr-inner">
        <button class="exam-back-btn" onclick="confirmExitExam()"><i class="fas fa-chevron-right" style="font-size:.78rem;"></i></button>
        <div style="flex:1;min-width:0;">
          <div class="exam-hdr-title" id="examHeaderTitle"></div>
          <div class="exam-hdr-sub" id="examHeaderSub"></div>
        </div>
        <div class="exam-timer" id="examTimerBadge"></div>
      </div>
      <div class="exam-prog-track"><div class="exam-prog-fill" id="examProgBar"></div></div>
    </div>
    <div class="exam-questions"><div id="examQList"></div></div>
    <div class="exam-footer">
      <div class="exam-footer-inner">
        <div class="exam-ans-count">
          <i class="fas fa-check-circle" style="color:var(--ok);"></i>
          <strong id="examAnsDone">0</strong> / <span id="examTotalQ">0</span> سؤال
        </div>
        <div id="examQNav" style="display:flex;gap:4px;flex-wrap:wrap;flex:1;justify-content:center;padding:0 8px;"></div>
        <button class="btn btn-p" style="padding:11px 26px;font-size:.93rem;" onclick="submitExam()">
          <i class="fas fa-paper-plane"></i> تسليم
        </button>
      </div>
    </div>
  </div>

  <!-- ③ Result view -->
  <div id="examResultView" style="display:none;flex-direction:column;align-items:center;justify-content:center;min-height:100vh;padding:20px 16px;">
    <div class="exam-result-wrap">
      <div id="examResultCard"></div>
      <button class="btn btn-p" style="width:100%;padding:14px;font-size:1rem;margin-top:4px;" onclick="exitExamScreen()">
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
      <div style="width:36px;height:36px;border-radius:var(--r-sm);background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.2rem;color:#fff;"><i class="fas fa-exclamation-triangle"></i></div>
      <div class="mhdr-title" style="flex:1;">تأكيد التسليم</div>
      <button class="mclose" onclick="_closeSubmitConfirm()"><i class="fas fa-times"></i></button>
    </div>
    <div class="mbody" style="text-align:center;padding:22px 18px 14px;">
      <div id="scModalMsg" style="font-size:.93rem;font-weight:700;color:var(--t1);line-height:1.6;margin-bottom:18px;"></div>
      <div style="display:flex;gap:10px;">
        <button class="btn btn-g" style="flex:1;" onclick="_closeSubmitConfirm()"><i class="fas fa-arrow-right"></i> رجوع</button>
        <button class="btn btn-p" style="flex:1;background:linear-gradient(135deg,#d97706,#b45309);" onclick="_confirmSubmitExam()"><i class="fas fa-paper-plane"></i> تسليم</button>
      </div>
    </div>
  </div>
</div>
<!-- Exit exam with saved answers -->
<div class="overlay" id="exitConfirmModal" style="z-index:1200;">
  <div class="modal narrow" style="max-width:360px;">
    <div class="mhdr" style="background:linear-gradient(135deg,var(--brand),var(--cou));">
      <div style="width:36px;height:36px;border-radius:var(--r-sm);background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:1.2rem;color:#fff;"><i class="fas fa-question-circle"></i></div>
      <div class="mhdr-title" style="flex:1;">الخروج من الاختبار</div>
      <button class="mclose" onclick="_closeExitConfirm()"><i class="fas fa-times"></i></button>
    </div>
    <div class="mbody" style="text-align:center;padding:22px 18px 14px;">
      <div style="font-size:.93rem;font-weight:700;color:var(--t1);line-height:1.6;margin-bottom:18px;">إجاباتك محفوظة تلقائياً. هل تريد الخروج؟</div>
      <div style="display:flex;gap:10px;">
        <button class="btn btn-g" style="flex:1;" onclick="_closeExitConfirm()"><i class="fas fa-times"></i> بقاء</button>
        <button class="btn btn-p" style="flex:1;" onclick="_confirmExit()"><i class="fas fa-sign-out-alt"></i> خروج</button>
      </div>
    </div>
  </div>
</div>

<div class="tc" id="tc"></div>

<script>
'use strict';
// ── Config ────────────────────────────────────────────────────────
const URL_ID = (()=>{const m=location.search.match(/[?&]id=(\d+)/);return m?parseInt(m[1]):null;})();
const _creds = localStorage.getItem('rememberMe')==='true'&&!!localStorage.getItem('savedUsername')&&!!localStorage.getItem('savedPassword');
const IS_PUBLIC = !!(URL_ID && !_creds);
const API_URL = (()=>{
  const segs = location.pathname.replace(/\/[^/]*$/,'').split('/').filter(Boolean);
  return segs.map(()=>'../').join('')+'api.php';
})();
const LETTERS = ['أ','ب','ج','د','هـ'];
// attendance_day: DB 1=Mon…7=Sun → JS getDay() 0=Sun 1=Mon…6=Sat
const DB_TO_JSDAY = {1:1,2:2,3:3,4:4,5:5,6:6,7:0};
const DAY_NAMES   = {0:'الأحد',1:'الاثنين',2:'الثلاثاء',3:'الأربعاء',4:'الخميس',5:'الجمعة',6:'السبت'};

// ── State ─────────────────────────────────────────────────────────
let student=null, allAccounts=[], selAccId=null;
let churchDay=5;
let customFields=null;
let cropper=null, croppedBlob=null;
let allTrips=[], allTasks=[];
let curTask=null, taskAnswers={}, examDone=false;

// ── Boot ──────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', async ()=>{
  if (_creds && URL_ID)          { await initPrivate(); await openFriendProfile(URL_ID); }
  else if (IS_PUBLIC && URL_ID)  await initPublic(URL_ID);
  else if (_creds)               await initPrivate();
  else if (URL_ID)               await initPublic(URL_ID);
  else                           noProfile('يرجى تسجيل الدخول أو استخدام رابط المعرّف');
  setupOvClose();
});

async function api(p){
  const fd=new FormData();
  for(const[k,v]of Object.entries(p)) if(v!==null&&v!==undefined) fd.append(k,v);
  const r=await fetch(API_URL,{method:'POST',body:fd,credentials:'include'});
  if(!r.ok)throw new Error('HTTP '+r.status);
  return r.json();
}

// ── Public init ───────────────────────────────────────────────────
async function initPublic(id){
  showLoad('جارٍ التحميل…');
  try{
    const d=await api({action:'getStudentProfile',studentId:id});
    hideLoad();
    if(d.success&&d.student){
      student=norm(d.student);
      await loadChurchSettings();
      renderPublic(student);
    } else noProfile('لم يُعثر على الملف الشخصي');
  }catch(e){hideLoad();noProfile('خطأ في الاتصال');}
}

// ── Private init ──────────────────────────────────────────────────
async function initPrivate(){
  showLoad('جارٍ تحميل ملفك…');
  try{
    const d=await api({action:'kidLogin',username:localStorage.getItem('savedUsername'),password:localStorage.getItem('savedPassword')});
    hideLoad();
    if(d.success&&d.data&&d.data.length>0){
      allAccounts=d.data.map(norm);
      student=allAccounts[0];
      await loadChurchSettings();
      renderPrivate(student);
      if(allAccounts.length>1){
        document.getElementById('switchBtnTop').style.display='flex';
      }
    } else noProfile('فشل في تحميل الملف الشخصي');
  }catch(e){hideLoad();noProfile('خطأ في الاتصال');}
}

async function loadChurchSettings(){
  if(!student?.church_id)return;
  try{
    const d=await api({action:'getPublicChurchSettings',church_id:student.church_id});
    if(d.success){
      churchDay=d.attendance_day||5;
      customFields=d.custom_fields||null;
    }
  }catch(e){}
}

// ── Normalise ─────────────────────────────────────────────────────
function norm(s){
  // The API returns `class` as the resolved class name (COALESCE'd in SQL).
  // '---' is the PHP fallback when all sources are null — treat it as empty.
  const rawCls = s.class || s['الفصل'] || '';
  const cls = (rawCls === '---' || rawCls === '--') ? '' : rawCls;
  return{
    id:s.id||0,
    name:s.name||s['الاسم']||'',
    class:cls,
    class_id:s.class_id||s._classId||0,
    address:s.address||'',
    phone:s.phone||'',
    birthday:s.birthday||'',
    email:s.email||'',
    coupons:parseInt(s.coupons||0),
    att_coupons:parseInt(s.attendance_coupons||0),
    com_coupons:parseInt(s.commitment_coupons||0),
    task_coupons:parseInt(s.task_coupons||0),
    image_url:s.image_url||'',
    church_name:s.church_name||'',
    church_id:s.church_id||0,
    custom_info:s.custom_info?(typeof s.custom_info==='string'?JSON.parse(s.custom_info):s.custom_info):null,
    trip_points: (function(){ try{ if(!s.trip_points) return {}; return (typeof s.trip_points==='string'?JSON.parse(s.trip_points):s.trip_points)||{} }catch(e){return{}} })(),
  };
}

// ── Render public ─────────────────────────────────────────────────
function renderPublic(s){
  renderHero(s,false);
  renderInfo(s,true);
  document.getElementById('pubBanner').style.display='flex';
  document.getElementById('sbC').textContent=s.coupons;
  document.getElementById('statsBar').style.display='grid';
  // hide private stats cells
  ['sbP','sbA','sbR'].forEach(id=>{
    document.getElementById(id).closest('.sb-cell').style.display='none';
  });
  loadTrips(false);
  loadAnn();
  showMain();
}

// ── Render private ────────────────────────────────────────────────
function renderPrivate(s){
  renderHero(s,true);
  renderInfo(s,false);
  renderCouponHero(s);
  document.getElementById('couponHero').style.display='grid';
  document.getElementById('scAtt').style.display='block';
  document.getElementById('scTasks').style.display='block';
  document.getElementById('scTrips').style.display='block';
  document.getElementById('scSearch').style.display='block';
  document.getElementById('settingsTop').style.display='flex';
  document.getElementById('avatarEdit').classList.add('show');
    
  // edit form prefill
  document.getElementById('eN').value=s.name;
  document.getElementById('eA').value=s.address;
  document.getElementById('eP').value=s.phone;
  document.getElementById('eB').value=s.birthday;
  document.getElementById('statsBar').style.display='grid';
document.getElementById('sbC').textContent = s.coupons;
  loadAtt();
  loadTasks();
  loadTrips(true);
  loadAnn();
  showMain();
}

// ── Hero ──────────────────────────────────────────────────────────
function renderHero(s,isPrivate){
  document.getElementById('hero').style.display='flex';
  document.getElementById('heroName').textContent=s.name;
  document.getElementById('heroClassTxt').textContent=s.class||'—';
  if(s.church_name){
    document.getElementById('churchName').textContent=s.church_name;
    document.getElementById('churchChip').style.display='inline-flex';
  }
  if(s.image_url){
    document.getElementById('avatarInner').innerHTML=`<img src="${esc(s.image_url)}" alt="${esc(s.name)}" onerror="this.parentElement.innerHTML='<i class=\\'fas fa-user\\'></i>'">`;
  }
  if(!isPrivate){
    document.getElementById('avatarEdit').style.display='none';
  }
  // Populate settings sheet
  const ssN=document.getElementById('ssName');
  const ssCls=document.getElementById('ssClass');
  const ssAv=document.getElementById('ssAvatar');
  if(ssN) ssN.textContent=s.name;
  if(ssCls) ssCls.textContent=s.class||'—';
  if(ssAv&&s.image_url) ssAv.innerHTML=`<img src="${esc(s.image_url)}" alt="" onerror="this.innerHTML='<i class=\\'fas fa-user\\'></i>'">`;
  // Load class uncles — always try, API resolves class_id→name server-side
  if(s.church_id && (s.class || s.class_id)){
    loadClassUncles(s.church_id, s.class||'', s.class_id||0);
  }
}

// ── Coupon Hero ───────────────────────────────────────────────────
function renderCouponHero(s){
  // Get all coupon types
  const attCoupons = s.att_coupons || 0;
  const comCoupons = s.com_coupons || 0;
  const taskCoupons = s.task_coupons || 0;
  const totalCoupons = s.coupons || 0;
  
  document.getElementById('chTotal').textContent = totalCoupons;
  document.getElementById('sbC').textContent = totalCoupons;
  
  const rows = [
    {icon:'fa-calendar-check', color:'#6ee7b7', label:'حضور', val: attCoupons},
    {icon:'fa-star', color:'#c4b5fd', label:'التزام', val: comCoupons},
    {icon:'fa-tasks', color:'#fde68a', label:'مهام', val: taskCoupons}
  ];
  
  document.getElementById('chBreakdown').innerHTML = rows.map(r => `
    <div class="ch-row"><i class="fas ${r.icon}" style="color:${r.color};"></i>${r.val} ${r.label}</div>
  `).join('');
}

// ── Class uncles strip ────────────────────────────────────────────
async function loadClassUncles(churchId, className, classId){
  if(!churchId) return;
  try{
    const params = {action:'getPublicClassUncles', church_id:churchId};
    if(className) params.class_name = className;
    if(classId)   params.class_id   = classId;
    if(!className && !classId) return;
    const d = await api(params);
    // If server resolved a class name from class_id and we had none, update display
    if(d.resolved_class_name && !className){
      const heroTxt=document.getElementById('heroClassTxt');
      if(heroTxt&&heroTxt.textContent==='—') heroTxt.textContent=d.resolved_class_name;
      const ssCls=document.getElementById('ssClass');
      if(ssCls&&ssCls.textContent==='—') ssCls.textContent=d.resolved_class_name;
      if(student) student.class=d.resolved_class_name;
    }
    if(d.success && d.uncles && d.uncles.length) renderUncleStrip(d.uncles);
  }catch(e){}
}
// Global uncles list (filled after load)
let classUncles = [];

function renderUncleStrip(uncles){
  classUncles = uncles;
  const strip=document.getElementById('uncleStrip');
  if(!strip||!uncles.length)return;
  const show=uncles.slice(0,4);
  const extra=uncles.length-show.length;
  strip.innerHTML=`
    <div class="uncle-avatars">
      ${show.map(u=>`<div class="ua" title="${esc(u.name)}">${u.image_url?`<img src="${esc(u.image_url)}" alt="${esc(u.name)}">`:u.name.charAt(0)}</div>`).join('')}
      ${extra>0?`<div class="ua-more">+${extra}</div>`:''}
    </div>
    <span class="uncle-strip-label"><i class="fas fa-user-tie" style="font-size:.6rem;opacity:.8;"></i> ${uncles.length===1?esc(uncles[0].name):''}</span>`;
  strip.style.display='inline-flex';
  strip.onclick=()=>{const sec=document.getElementById('scUncles');if(sec)sec.scrollIntoView({behavior:'smooth',block:'start'});};
  renderUncleCards(uncles);
}

function renderUncleCards(uncles){
  const sec=document.getElementById('scUncles');
  const grid=document.getElementById('uncleCardGrid');
  const sub=document.getElementById('unclesSub');
  if(!sec||!grid||!uncles.length)return;
  sub.textContent=uncles.length+' مدرس';
  const roleLbl={admin:'مدرس',developer:'مطوّر',uncle:'مدرس'};
  grid.innerHTML=uncles.map(u=>`
    <div class="uncle-card" onclick="openUncleDrawer(${u.id})">
      <div class="uncle-card-av">
        ${u.image_url?`<img src="${esc(u.image_url)}" alt="${esc(u.name)}">`:u.name.charAt(0)}
      </div>
      <div class="uncle-card-name">${esc(u.name)}</div>
      <div class="uncle-card-role">${roleLbl[u.role]||u.role}</div>
    </div>`).join('');
  sec.style.display='block';
}

function openUncleDrawer(uid){
  const u=classUncles.find(x=>x.id===uid);
  if(!u)return;
  const roleLbl={admin:'مدرس',developer:'مطوّر',uncle:'مدرس'};
  const hasPhone=u.phone&&u.phone.trim();
  // Format phone for WhatsApp (strip leading 0, add country code)
  const waPhone=hasPhone?'20'+u.phone.trim().replace(/^0/,''):'';
  const waMsg=encodeURIComponent('مرحباً أنا '+((student&&student.name)||'')+'، طفل في فصلك بمدارس الأحد');
  const actionBtns=hasPhone?`
    <a href="tel:${esc(u.phone.trim())}" class="uncle-action-btn call" style="text-decoration:none;">
      <div class="uncle-action-ico" style="background:#d1fae5;color:#059669;"><i class="fas fa-phone-alt"></i></div>
      <div><div>${esc(u.phone.trim())}</div><div style="font-size:.7rem;color:var(--t4);font-weight:500;">اضغط للاتصال</div></div>
    </a>
    <a href="https://wa.me/${waPhone}?text=${waMsg}" target="_blank" class="uncle-action-btn wa" style="text-decoration:none;">
      <div class="uncle-action-ico" style="background:#dcfce7;color:#16a34a;"><i class="fab fa-whatsapp"></i></div>
      <div><div>واتساب</div><div style="font-size:.7rem;color:var(--t4);font-weight:500;">إرسال رسالة</div></div>
    </a>`
    :`<div style="padding:14px 18px;background:var(--s2);border-radius:var(--r-md);text-align:center;color:var(--t4);font-size:.82rem;font-weight:600;"><i class="fas fa-phone-slash"></i> لم يُضَف رقم الهاتف بعد</div>`;
  document.getElementById('uncleOvContent').innerHTML=`
    <div class="uncle-drawer-hero">
      <div class="uncle-drawer-av">
        ${u.image_url?`<img src="${esc(u.image_url)}" alt="${esc(u.name)}">`:u.name.charAt(0)}
      </div>
      <div>
        <div class="uncle-drawer-name">${esc(u.name)}</div>
        <div class="uncle-drawer-role">${roleLbl[u.role]||u.role}</div>
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

async function openFriendProfile(friendId){
  showLoad('جارٍ تحميل الملف…');
  try{
    const [profD, attD] = await Promise.all([
      api({action:'getStudentProfile', studentId:friendId}),
      api({action:'getStudentAttendance', studentId:friendId})
    ]);
    hideLoad();
    if(!profD.success||!profD.student) return; // friend not found — stay on own profile
    const f = norm(profD.student);
    f._friendAtt = attD.success ? (attD.attendance||[]) : [];
    renderFriend(f);
  }catch(e){ hideLoad(); }
}

function renderFriend(f){
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
  document.getElementById('heroClassTxt').textContent = f.class || '—';
  const chip = document.getElementById('churchChip');
  if(chip && f.church_name){
    document.getElementById('churchName').textContent = f.church_name;
    chip.style.display = 'inline-flex';
  }
  document.getElementById('hero').style.display = 'flex';

  // ── Uncle strip for friend's class ───────────────────────────
  const strip = document.getElementById('uncleStrip');
  strip.style.display = 'none';
  strip.innerHTML = '';
  classUncles = [];
  if(f.church_id && (f.class||f.class_id)){
    loadClassUncles(f.church_id, f.class||'', f.class_id||0);
  }

  // ── Info (public mode — class + church only) ──────────────────
  renderInfo(f, true);

  // ── Coupon hero ────────────────────────────────────────────────
  renderCouponHero(f);
  document.getElementById('couponHero').style.display = 'grid';

  // ── Stats bar — coupons only ──────────────────────────────────
  document.getElementById('statsBar').style.display = 'grid';
  document.getElementById('sbC').textContent = f.coupons;
  ['sbP','sbA','sbR'].forEach(id=>{
    const el=document.getElementById(id);
    if(el) el.closest('.sb-cell').style.display='none';
  });

  // ── Attendance calendar ───────────────────────────────────────
  renderCal(f._friendAtt||[]);
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
  document.getElementById('scSearch').style.display = 'none';
  document.getElementById('pubBanner').style.display = 'none';
  document.getElementById('settingsTop').style.display = 'none';

  // ── Friend banner ─────────────────────────────────────────────
  document.getElementById('friendBannerName').textContent = 'ملف ' + f.name;
  document.getElementById('friendBanner').style.display = 'flex';

  showMain();
  window.scrollTo({top:0, behavior:'smooth'});
}

function returnToMyProfile(){
  if(!_myStudent) return;
  const s = _myStudent;
  _myStudent = null;

  // Clean the ?id= from the URL without reloading
  const clean = location.pathname;
  window.history.replaceState({}, '', clean);

  // Restore own avatar
  const avInner = document.getElementById('avatarInner');
  if(s.image_url){
    avInner.innerHTML = `<img src="${esc(s.image_url)}" alt="${esc(s.name)}" onerror="this.parentElement.innerHTML='<i class=\\'fas fa-user\\'></i>'">`;
  } else {
    avInner.innerHTML = '<i class="fas fa-user"></i>';
  }
  document.getElementById('avatarEdit').style.display = '';
  document.getElementById('avatarEdit').classList.add('show');

  // Restore hero text
  document.getElementById('heroName').textContent = s.name;
  document.getElementById('heroClassTxt').textContent = s.class || '—';
  const chip = document.getElementById('churchChip');
  if(chip){ document.getElementById('churchName').textContent = s.church_name||''; chip.style.display = s.church_name?'inline-flex':'none'; }

  // Restore uncle strip for own class
  const strip = document.getElementById('uncleStrip');
  strip.style.display = 'none'; strip.innerHTML = ''; classUncles = [];
  if(s.church_id && (s.class||s.class_id)) loadClassUncles(s.church_id, s.class||'', s.class_id||0);

  // Restore info
  renderInfo(s, false);

  // Restore coupons
  renderCouponHero(s);
  document.getElementById('couponHero').style.display = 'grid';

  // Restore stats bar
  document.getElementById('statsBar').style.display = 'grid';
  document.getElementById('sbC').textContent = s.coupons;
  ['sbP','sbA','sbR'].forEach(id=>{
    const el=document.getElementById(id);
    if(el) el.closest('.sb-cell').style.display='';
  });

  // Restore sections
  document.getElementById('scTasks').style.display = 'block';
  document.getElementById('scTrips').style.display = 'block';
  document.getElementById('scSearch').style.display = 'block';
  document.getElementById('scAtt').style.display = 'block';
  document.getElementById('settingsTop').style.display = 'flex';
  document.getElementById('friendBanner').style.display = 'none';
  document.getElementById('pubBanner').style.display = 'none';

  // Reload own data
  student = s;
  loadAtt();
  loadTasks();
  loadTrips(true);
  loadAnn();
  window.scrollTo({top:0, behavior:'smooth'});
}

// ── Search friends ────────────────────────────────────────────────
let _searchTimer = null;
function onFriendSearch(val){
  clearTimeout(_searchTimer);
  const res = document.getElementById('friendSearchResults');
  if(!val||val.trim().length<2){ res.innerHTML=''; return; }
  res.innerHTML='<div style="padding:12px;text-align:center;color:var(--t4);font-size:.82rem;"><i class="fas fa-spinner fa-spin"></i></div>';
  _searchTimer = setTimeout(()=>doFriendSearch(val.trim()), 350);
}
async function doFriendSearch(q){
  const res = document.getElementById('friendSearchResults');
  try{
    const d = await api({action:'searchKidsByName', query:q, church_id: student?.church_id||0});
    if(!d.success||!d.kids||!d.kids.length){
      res.innerHTML='<div style="padding:16px;text-align:center;color:var(--t3);font-size:.82rem;">لم يُعثر على نتائج</div>';
      return;
    }
    res.innerHTML = d.kids.map(k=>{
      const av = k.image_url
        ? `<img src="${esc(k.image_url)}" alt="${esc(k.name)}">`
        : `<span>${k.name.charAt(0)}</span>`;
      const isSelf = student && k.id === student.id;
      return `<div class="friend-result-card" onclick="${isSelf?'window.scrollTo({top:0,behavior:\'smooth\'})':'openFriendProfile('+k.id+')'}">
        <div class="friend-result-av">${av}</div>
        <div class="friend-result-info">
          <div class="friend-result-name">${esc(k.name)}${isSelf?' <span style="font-size:.68rem;color:var(--cou);font-weight:700;">(أنت)</span>':''}</div>
          <div class="friend-result-meta">${esc(k.class||'—')}${k.church_name?' · '+esc(k.church_name):''}</div>
        </div>
        <div class="friend-result-cou"><i class="fas fa-ticket-alt" style="font-size:.72rem;margin-left:3px;"></i>${k.coupons}</div>
      </div>`;
    }).join('');
  }catch(e){
    res.innerHTML='<div style="padding:12px;text-align:center;color:var(--err);font-size:.82rem;">خطأ في البحث</div>';
  }
}

function renderInfo(s,isPublic){
  const el=document.getElementById('infoGrid');
  const pills=[];
  if(s.class) pills.push({bg:'#e0e7ff',c:'#4338ca',icon:'fas fa-graduation-cap',lbl:'الفصل',val:s.class});
  if(!isPublic){
    if(s.phone)   pills.push({bg:'#d1fae5',c:'#065f46',icon:'fas fa-phone',lbl:'التليفون',val:s.phone});
    if(s.address) pills.push({bg:'#ffedd5',c:'#9a3412',icon:'fas fa-map-marker-alt',lbl:'العنوان',val:s.address});
    if(s.birthday)pills.push({bg:'#fce7f3',c:'#9d174d',icon:'fas fa-birthday-cake',lbl:'عيد الميلاد',val:s.birthday});
    if(s.email)   pills.push({bg:'#dbeafe',c:'#1e40af',icon:'fas fa-envelope',lbl:'البريد',val:s.email});
    // Custom info from student record
    if(s.custom_info&&typeof s.custom_info==='object'){
      // Match keys against church custom_field definitions for labels/icons
      const defs=customFields||[];
      for(const[key,val] of Object.entries(s.custom_info)){
        if(!val)continue;
        const def=defs.find(d=>d.key===key);
        const label=def?.name||key;
        const icon=def?.icon||'fas fa-tag';
        pills.push({bg:'#f0fdf4',c:'#166534',icon:`fas ${icon.replace('fas ','').replace('fa-','')?'fa-'+icon.replace('fas ','').replace('fa-',''):icon}`,lbl:label,val:String(val)});
      }
    }
  }
  if(s.church_name&&isPublic) pills.push({bg:'#ede9fe',c:'#4c1d95',icon:'fas fa-church',lbl:'الكنيسة',val:s.church_name});

  if(!pills.length){
    document.getElementById('scInfo').style.display='none';
    return;
  }
  el.innerHTML=pills.map(p=>`
    <div class="ip">
      <div class="ip-ico" style="background:${p.bg};color:${p.c};"><i class="${p.icon}"></i></div>
      <div><div class="ip-lbl">${esc(p.lbl)}</div><div class="ip-val">${esc(p.val)}</div></div>
    </div>`).join('');
}

// ── Attendance ────────────────────────────────────────────────────
async function loadAtt(){
  try{
    const d=await api({action:'getStudentAttendance',studentId:student.id});
    renderCal(d.attendance||[]);
  }catch(e){renderCal([]);}
}

function renderCal(records){
  const jsDay = DB_TO_JSDAY[churchDay] ?? 5;
  const dayName = DAY_NAMES[jsDay] || 'الجمعة';
  document.getElementById('attSub').textContent=`آخر 12 ${dayName}`;

  const todayLocal = new Date(); todayLocal.setHours(12,0,0,0);
  const days=[];
  for(let i=0;days.length<12;i++){
    const d=new Date(todayLocal); d.setDate(d.getDate()-i);
    if(d.getDay()===jsDay){
      const y=d.getFullYear(), m=String(d.getMonth()+1).padStart(2,'0'), dd=String(d.getDate()).padStart(2,'0');
      const str=`${y}-${m}-${dd}`;
      days.push({date:d, str, num:d.getDate(), mo:d.toLocaleDateString('ar-EG',{month:'short'})});
    }
  }
  let pr=0,ab=0;
  document.getElementById('calGrid').innerHTML=days.map(day=>{
    const rec=records.find(r=>r.attendance_date===day.str);
    const diff=Math.round((todayLocal-day.date)/(86400000));
    const daysAgo=diff===0?'اليوم':diff===7?'أسبوع':diff<14?`${diff} يوم`:Math.round(diff/7)+' أسبوع';
    let cls='',st='—';
    if(rec){
      if(rec.status==='present'){cls='present';st='حضر';pr++;}
      else if(rec.status==='absent'){cls='absent';st='غاب';ab++;}
    }
    return `<div class="cal-day ${cls}">
      <div class="cd-num">${day.num}</div>
      <div class="cd-mo">${day.mo}</div>
      <div class="cd-st">${st}</div>
      <div class="cd-days">${daysAgo}</div>
    </div>`;
  }).join('');
  const total=pr+ab; const rate=total>0?Math.round(pr/total*100):0;
  document.getElementById('ap').textContent=pr;
  document.getElementById('aa').textContent=ab;
  document.getElementById('ar').textContent=rate+'%';
  document.getElementById('sbP').textContent=pr;
  document.getElementById('sbA').textContent=ab;
  document.getElementById('sbR').textContent=rate+'%';
  document.getElementById('attBadge').textContent=rate+'%';
  if(!pr&&!ab) document.getElementById('scAtt').style.display='none';
}

// ── Attendance History Sheet ──────────────────────────────────────
let _attAllRecords = [];
let _attFilter     = 'all';

function timeAgoAr(dateStr) {
  const [yr,mo,dy] = dateStr.split('-').map(Number);
  const diff = Math.floor((Date.now() - new Date(yr, mo-1, dy, 12).getTime()) / 86400000);
  if (diff <= 0)   return 'اليوم';
  if (diff === 1)  return 'أمس';
  if (diff < 7)    return `منذ ${diff} أيام`;
  const w = Math.floor(diff / 7);
  if (w === 1)     return 'منذ أسبوع';
  if (w < 5)       return `منذ ${w} أسابيع`;
  const m = Math.floor(diff / 30);
  if (m === 1)     return 'منذ شهر';
  if (m < 12)      return `منذ ${m} شهور`;
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
    const d = await api({action:'getStudentAttendance', studentId: student.id});
    const dbRecords = (d.attendance || []);

    // Build ALL church-day slots for the past 24 weeks
    // Use LOCAL date arithmetic to avoid UTC timezone shift bugs
    const jsDay = DB_TO_JSDAY[churchDay] ?? 5;
    const todayLocal = new Date();
    todayLocal.setHours(12,0,0,0); // noon — safe against DST/UTC shifts
    const allSlots = [];
    let cur = new Date(todayLocal);
    // Walk back to most recent church day (inclusive of today)
    while(cur.getDay() !== jsDay) cur.setDate(cur.getDate()-1);
    for(let i=0;i<24;i++){
      // Format as YYYY-MM-DD using local parts — never toISOString (UTC)
      const y  = cur.getFullYear();
      const m  = String(cur.getMonth()+1).padStart(2,'0');
      const dd = String(cur.getDate()).padStart(2,'0');
      const str = `${y}-${m}-${dd}`;
      const rec = dbRecords.find(r=>r.attendance_date===str);
      allSlots.push({ str, status: rec ? rec.status : 'unrecorded' });
      cur.setDate(cur.getDate()-7);
    }
    _attAllRecords = allSlots;

    const pr = allSlots.filter(r=>r.status==='present').length;
    const ab = allSlots.filter(r=>r.status==='absent').length;
    const ur = allSlots.filter(r=>r.status==='unrecorded').length;
    const total = pr + ab;
    document.getElementById('ahsPresent').textContent = pr;
    document.getElementById('ahsAbsent').textContent  = ab;
    document.getElementById('ahsRate').textContent    = total > 0 ? Math.round(pr/total*100)+'%' : '—';
    document.getElementById('attHistSubtitle').textContent =
      `${allSlots.length} أسبوع · ${pr} حضور · ${ab} غياب · ${ur} غير مسجّل`;

    renderAttHist();
  } catch(e) {
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
  const sort   = document.getElementById('attHistSort').value;
  const list   = document.getElementById('attHistList');
  const WDAYS  = ['الأحد','الاثنين','الثلاثاء','الأربعاء','الخميس','الجمعة','السبت'];

  let items = _attAllRecords.slice();
  if (sort === 'oldest') items = items.slice().reverse();
  if (_attFilter !== 'all') items = items.filter(r => r.status === _attFilter);
  if (search) {
    items = items.filter(r => {
      const dateAr = new Date(r.str||r.attendance_date).toLocaleDateString('ar-EG',{day:'numeric',month:'long',year:'numeric'});
      return (r.str||r.attendance_date).includes(search) || dateAr.includes(search);
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
    const [yr,mo,dy] = dateStr.split('-').map(Number);
    const d       = new Date(yr, mo-1, dy, 12);
    const dateAr  = d.toLocaleDateString('ar-EG',{day:'numeric',month:'long',year:'numeric'});
    const wday    = WDAYS[d.getDay()];
    const ago     = timeAgoAr(dateStr);
    const isP     = r.status === 'present';
    const isA     = r.status === 'absent';
    const isU     = r.status === 'unrecorded';

    const rowBg   = isP ? 'background:var(--ok-bg);border-color:#6ee7b7;'
                  : isA ? 'background:var(--err-bg);border-color:#fca5a5;'
                  :        'background:var(--s2);border-color:var(--bdr2);opacity:.82;';
    const dotClr  = isP ? 'var(--ok)' : isA ? 'var(--err)' : 'var(--t5)';
    const badgeBg = isP ? 'background:rgba(5,150,105,.12);color:var(--ok);'
                  : isA ? 'background:rgba(220,38,38,.12);color:var(--err);'
                  :        'background:var(--s2);color:var(--t4);border:1px solid var(--bdr);';
    const label   = isP ? 'حضر ✓' : isA ? 'غاب ✗' : '— غير مسجّل';

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
let _reportDateStr  = '';
let _reportShould   = '';

function openRowReport(dateStr, currentStatus) {
  _reportDateStr = dateStr;
  _reportShould  = '';

  // Show the date in Arabic in the sheet header
  let dateAr = dateStr;
  try {
    const [yr,mo,dy] = dateStr.split('-').map(Number);
    dateAr = new Date(yr, mo-1, dy, 12).toLocaleDateString('ar-EG',{weekday:'long',day:'numeric',month:'long'});
  } catch(e){}
  document.getElementById('reportDateLabel').textContent = dateAr;

  // Reset should buttons
  ['reportShouldPresent','reportShouldAbsent'].forEach(id=>{
    const b=document.getElementById(id);
    if(b){b.style.background='var(--surf)';b.style.borderColor='var(--bdr)';b.style.color='var(--t2)';}
  });

  // Build uncle list
  const unclesWithPhone = classUncles.filter(u => u.phone && u.phone.trim());
  const ul = document.getElementById('reportUncleList');
  if (!unclesWithPhone.length) {
    ul.innerHTML = `<div style="text-align:center;padding:16px;color:var(--t4);font-size:.82rem;font-weight:600;">
      <i class="fas fa-phone-slash" style="display:block;font-size:1.2rem;margin-bottom:5px;opacity:.4;"></i>
      لم يُضَف رقم هاتف لأي مدرّس بعد</div>`;
  } else {
    const roleLbl = {admin:'مشرف', developer:'مطوّر', uncle:'مدرّس'};
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
          <div style="font-size:.68rem;color:var(--t4);font-weight:500;">${roleLbl[u.role]||u.role}</div>
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
    present:{id:'reportShouldPresent',bg:'var(--ok-bg)', border:'#6ee7b7',color:'var(--ok)'},
    absent: {id:'reportShouldAbsent', bg:'var(--err-bg)',border:'#fca5a5',color:'var(--err)'},
  };
  ['reportShouldPresent','reportShouldAbsent'].forEach(id=>{
    const b=document.getElementById(id);
    if(b){b.style.background='var(--surf)';b.style.borderColor='var(--bdr)';b.style.color='var(--t2)';}
  });
  const cfg=map[val]; if(!cfg)return;
  const btn=document.getElementById(cfg.id);
  if(btn){btn.style.background=cfg.bg;btn.style.borderColor=cfg.border;btn.style.color=cfg.color;}
}

function sendRowReport(uid) {
  if(!_reportShould) { toast('اختر حضر أو غاب أولاً','err'); return; }
  const u = classUncles.find(x => x.id === uid);
  if(!u||!u.phone) return;
  const name    = (student && student.name) || '';
  const cls     = (student && student.class) || '';
  const shouldAr = _reportShould === 'present' ? 'حضر' : 'غاب';
  let dateAr = _reportDateStr;
  try {
    const [yr,mo,dy] = _reportDateStr.split('-').map(Number);
    dateAr = new Date(yr, mo-1, dy, 12).toLocaleDateString('ar-EG',{weekday:'long',day:'numeric',month:'long',year:'numeric'});
  } catch(e){}
  const msg = `${name}${cls?' ('+cls+')':''} — ${dateAr}\nالمفروض أكون: ${shouldAr}`;
  const wa  = '20' + u.phone.trim().replace(/^0/,'');
  window.open(`https://wa.me/${wa}?text=${encodeURIComponent(msg)}`, '_blank');
}

// ── Tasks ─────────────────────────────────────────────────────────
async function loadTasks(){
  try{
    const d=await api({action:'getStudentTasks',student_id:student.id,church_id:student.church_id,class_id:student.class_id||0});
    if(d.success) renderTasks(d.tasks||[]);
  }catch(e){}
}
function tSt(t){
  if(t.my_submission)return 'done';
  const n=Date.now(),s=new Date(t.start_date).getTime();
  const hasDeadline=!parseInt(t.no_deadline||0) && !!t.end_date;
  const e=hasDeadline?new Date(t.end_date).getTime():null;
  if(n<s)return 'upcoming';if(hasDeadline&&n>e)return 'expired';return 'open';
}
function renderTasks(tasks){
  allTasks=tasks;
  const el=document.getElementById('taskList');
  document.getElementById('taskSub').textContent=tasks.length+' مهمة';
  if(!tasks.length){document.getElementById('scTasks').style.display='none';return;}
  const stLbl={done:'مكتمل',open:'مفتوح',upcoming:'قادم',expired:'منتهي'};
  const stBar={done:'done-bar',open:'',upcoming:'up-bar',expired:'exp-bar'};
  const stBadge={done:'tb-done',open:'tb-open',upcoming:'tb-up',expired:'tb-exp'};
  el.innerHTML=tasks.map(t=>{
    const st=tSt(t);
    const sub=t.my_submission;

    // Max coupon from matrix
    const matrix=t.coupon_matrix?JSON.parse(t.coupon_matrix||'[]'):[];
    const maxCoupon=matrix.length?Math.max(...matrix.map(m=>parseInt(m.val)||0)):0;

    // Coupon row — always visible
    let couponRow='';
    if(sub){
      // Submitted: show earned score + coupons
      const pct=t.total_degree>0?Math.round(sub.score/t.total_degree*100):0;
      couponRow=`<div class="task-result">
        <i class="fas fa-check-circle"></i>
        <span>${sub.score}/${t.total_degree} (${pct}%)</span>
        <span style="margin-right:auto;display:flex;align-items:center;gap:4px;">
          <i class="fas fa-ticket-alt" style="color:var(--cou-l);font-size:.75rem;"></i>
          <strong style="color:var(--cou);font-size:.82rem;">${sub.coupons_awarded}</strong>
          <span style="font-size:.7rem;color:var(--t3);margin-left:5px;">كوبون</span>
          ${t.show_answers ? `<button onclick="event.stopPropagation();viewMyAnswers(${t.id})" style="margin-right:5px;background:var(--s2);border:1px solid var(--brand-l);color:var(--brand);border-radius:5px;padding:3px 8px;font-size:.7rem;font-family:'Baloo Bhaijaan 2',sans-serif;font-weight:700;cursor:pointer;"><i class="fas fa-eye"></i> الإجابات</button>` : ''}
        </span>
      </div>`;
    } else if(maxCoupon>0){
      // Not submitted: show max possible coupons
      couponRow=`<div style="display:flex;align-items:center;gap:5px;margin-top:7px;padding:5px 9px;border-radius:var(--r-sm);background:var(--cou-bg);border:1px solid #c4b5fd;font-size:.74rem;">
        <i class="fas fa-ticket-alt" style="color:var(--cou-l);"></i>
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
          <span class="task-meta-chip"><i class="fas fa-calendar-alt"></i>${parseInt(t.no_deadline||0)?'بدون آخر موعد':fmtDate(t.end_date)}</span>
          ${t.total_degree?`<span class="task-meta-chip"><i class="fas fa-star"></i>${t.total_degree} درجة</span>`:''}
          ${t.time_limit?`<span class="task-meta-chip"><i class="fas fa-stopwatch"></i>${t.time_limit} دقيقة</span>`:''}
        </div>
        ${couponRow}
      </div>
    </div>`;
  }).join('');
}

// ── Task exam (full-screen, DB-anchored timer, auto-save) ─────────
let examStartedAt   = null;   // Date set from server
let examTimerIv     = null;   // countdown interval
let examTimeLimitSec= 0;      // seconds total

async function openTask(id){
  const t=allTasks.find(x=>x.id==id);if(!t)return;
  const st=tSt(t);
  if(st==='done'&&t.my_submission){showExamResult(t,t.my_submission);return;}
  if(st==='upcoming'){toast('هذه المهمة لم تُفتح بعد','info');return;}
  if(st==='expired'&&!t.my_submission){toast('انتهت فترة التسليم','err');return;}
  curTask=t; examDone=false;

  if(t.time_limit){
    examTimeLimitSec=t.time_limit*60;
    taskAnswers=JSON.parse(localStorage.getItem(`ta_${t.id}_${student.id}`)||'{}');

    // ── 1. Check localStorage first (instant, no network) ──────
    const lsKey=`examStart_${t.id}_${student.id}`;
    const lsStart=localStorage.getItem(lsKey);
    if(lsStart){
      const parsed=new Date(lsStart);
      const elapsed=Math.floor((Date.now()-parsed.getTime())/1000);
      const remaining=examTimeLimitSec-elapsed;
      if(remaining>0){
        // Active session in localStorage — show continue
        examStartedAt=parsed;
        showExamStartScreen(t,true,remaining);
        // Also verify/sync with server silently
        _syncExamStart(t,lsStart);
        return;
      } else {
        // Expired in localStorage — clear it and let them start fresh
        localStorage.removeItem(lsKey);
        examStartedAt=null;
      }
    }

    // ── 2. No localStorage record — check server ────────────────
    try{
      const d=await api({action:'getExamStart',student_id:student.id,church_id:student.church_id,task_id:t.id});
      if(d.success && d.started_at){
        const parsed=new Date(d.started_at.replace(' ','T'));
        const elapsed=Math.floor((Date.now()-parsed.getTime())/1000);
        const remaining=examTimeLimitSec-elapsed;
        if(remaining>0){
          // Server has active session — restore it and save to localStorage
          examStartedAt=parsed;
          localStorage.setItem(lsKey, d.started_at.replace(' ','T'));
          showExamStartScreen(t,true,remaining);
          return;
        }
        // Server record expired — clear it
        try{ await api({action:'clearExamStart',student_id:student.id,church_id:student.church_id,task_id:t.id}); }catch(e){}
        examStartedAt=null;
      }
    }catch(e){}
  } else {
    taskAnswers=JSON.parse(localStorage.getItem(`ta_${t.id}_${student.id}`)||'{}');
  }

  showExamStartScreen(t,false,0);
}

// Silently sync localStorage start time to server (fire-and-forget)
async function _syncExamStart(t, startedAtStr){
  try{
    await api({action:'startExam',student_id:student.id,church_id:student.church_id,task_id:t.id});
  }catch(e){}
}

function examMetaRow(bg,bdr,icoColor,icon,label,sub){
  return `<div class="exam-meta-row" style="background:${bg};border:1px solid ${bdr};">
    <i class="${icon}" style="color:${icoColor};"></i>
    <div class="em-text">
      <div>${label}</div>
      ${sub?`<div class="em-sub">${sub}</div>`:''}
    </div>
  </div>`;
}

// isResume=true → student already started, show "استكمل" with remaining time
function showExamStartScreen(t, isResume, remainingSec){
  const qs=(t.questions||[]).length;
  document.getElementById('startTitle').textContent=t.title;
  document.getElementById('startSub').textContent=`${qs} سؤال · ${t.total_degree} درجة`;
  const lsKey=`examStart_${t.id}_${student.id}`;
  const hasSaved=Object.keys(JSON.parse(localStorage.getItem(`ta_${t.id}_${student.id}`)||'{}')).length>0;
  const hasStart=!!localStorage.getItem(lsKey);
  const rows=[];

  if(t.time_limit){
    if((isResume||hasStart) && remainingSec>0){
      const remM=Math.floor(remainingSec/60);
      const remS=remainingSec%60;
      const remStr=`${remM}:${String(remS).padStart(2,'0')}`;
      rows.push(examMetaRow('#fef3c7','#fde68a','#d97706','fas fa-stopwatch',
        `الوقت المتبقي: <strong style="font-size:1.1em;color:#b45309;">${remStr}</strong>`,
        'الوقت يعمل من السيرفر — يستكمل من حيث توقف'));
    } else {
      rows.push(examMetaRow('var(--warn-bg)','#fde68a','var(--warn)','fas fa-stopwatch',
        `مدة الاختبار: <strong>${t.time_limit} دقيقة</strong>`,
        'يبدأ العد فور الضغط على ابدأ ويُسجَّل في السيرفر'));
    }
  } else {
    rows.push(examMetaRow('var(--ok-bg)','#6ee7b7','var(--ok)','fas fa-infinity',
      'لا يوجد وقت محدد — أجب بتأنٍّ',null));
  }
  rows.push(examMetaRow('var(--s2)','var(--bdr)','var(--brand)','fas fa-list-ol',`${qs} سؤال`,null));
  rows.push(examMetaRow('var(--s2)','var(--bdr)','var(--gold-l)','fas fa-award',`${t.total_degree} درجة كاملة`,null));
  if(hasSaved||isResume||hasStart){
    rows.push(examMetaRow('var(--brand-bg)','var(--brand-l)','var(--brand)','fas fa-layer-group',
      'إجاباتك السابقة محفوظة وستُستكمل',null));
  }
  document.getElementById('startMeta').innerHTML=rows.join('');

  const startBtn=document.getElementById('examStartBtn');
  if(startBtn){
    startBtn.innerHTML=(isResume||hasSaved||hasStart)
      ?'<i class="fas fa-play-circle"></i> استكمل الاختبار'
      :'<i class="fas fa-play-circle"></i> ابدأ الاختبار';
  }

  examShowView('start');
  examScreenOpen();
}

async function beginExam(){
  if(!curTask)return;
  const t=curTask;
  taskAnswers=JSON.parse(localStorage.getItem(`ta_${t.id}_${student.id}`)||'{}');

  if(t.time_limit){
    examTimeLimitSec=t.time_limit*60;
    const lsKey=`examStart_${t.id}_${student.id}`;

    if(!examStartedAt){
      // Fresh start — save to localStorage immediately before any network call
      const now=new Date();
      examStartedAt=now;
      localStorage.setItem(lsKey, now.toISOString());
      // Record on server too (INSERT IGNORE keeps original if already exists)
      try{
        const d=await api({action:'startExam',student_id:student.id,church_id:student.church_id,task_id:t.id});
        if(d.started_at){
          const serverTime=new Date(d.started_at.replace(' ','T'));
          const diff=Math.abs(serverTime.getTime()-now.getTime());
          if(diff<10000){ // within 10s — prefer server time
            examStartedAt=serverTime;
            localStorage.setItem(lsKey, d.started_at.replace(' ','T'));
          }
        }
      }catch(e){} // localStorage fallback already saved — safe to ignore
    }
    // If examStartedAt already set (resume path) — use as-is, timer continues
  }

  document.getElementById('examHeaderTitle').textContent=t.title;
  document.getElementById('examHeaderSub').textContent=`${(t.questions||[]).length} سؤال — ${t.total_degree} درجة`;
  document.getElementById('examTotalQ').textContent=(t.questions||[]).length;
  renderExamQuestions(t);
  examShowView('active');
  if(t.time_limit) startExamCountdown(t);
}

function renderExamQuestions(t){
  const qs=t.questions||[];
  document.getElementById('examQList').innerHTML=qs.map((q,i)=>{
    const qtype=q.question_type||'mcq';
    const imgHtml=q.image_url
      ?`<div style="margin:0 0 2px;"><img src="${esc(q.image_url)}" alt="" style="width:100%;max-height:200px;object-fit:contain;display:block;background:var(--s2);"></div>`
      :'';

    // ── Open / essay ──────────────────────────────────────────
    if(qtype==='open'){
      const savedAns=taskAnswers[String(q.id)]||'';
      return `<div class="qcard" id="qc_${q.id}">
        <div class="qhdr">
          <div class="qnum" style="background:linear-gradient(135deg,#f59e0b,#d97706);">${i+1}</div>
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
    if(qtype==='tf'){
      const saved=taskAnswers[String(q.id)];
      const trueOn=saved===0; const falseOn=saved===1;
      return `<div class="qcard" id="qc_${q.id}">
        <div class="qhdr">
          <div class="qnum">${i+1}</div>
          <div class="qtext">${esc(q.question_text)}</div>
          <span class="qdeg">${q.degree} درجة</span>
        </div>
        ${imgHtml}
        <div class="qopts" style="display:flex;gap:10px;padding:10px 12px;">
          <button id="tfbtn_${q.id}_0" onclick="pickOpt(${q.id},0,null)"
            style="flex:1;padding:13px 8px;border-radius:var(--r-sm);border:2px solid ${trueOn?'var(--ok)':'var(--bdr)'};background:${trueOn?'var(--ok-bg)':'var(--surf)'};color:${trueOn?'var(--ok)':'var(--t2)'};font-family:inherit;font-size:.88rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;transition:var(--fast);">
            <i class="fas fa-check-circle"></i> صحيح
          </button>
          <button id="tfbtn_${q.id}_1" onclick="pickOpt(${q.id},1,null)"
            style="flex:1;padding:13px 8px;border-radius:var(--r-sm);border:2px solid ${falseOn?'var(--err)':'var(--bdr)'};background:${falseOn?'var(--err-bg)':'var(--surf)'};color:${falseOn?'var(--err)':'var(--t2)'};font-family:inherit;font-size:.88rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:7px;transition:var(--fast);">
            <i class="fas fa-times-circle"></i> خطأ
          </button>
        </div>
      </div>`;
    }

    // ── MCQ (default) ─────────────────────────────────────────
    const opts=typeof q.options==='string'?JSON.parse(q.options):(q.options||[]);
    const sel=taskAnswers[String(q.id)]!==undefined?taskAnswers[String(q.id)]:null;
    return `<div class="qcard" id="qc_${q.id}">
      <div class="qhdr">
        <div class="qnum">${i+1}</div>
        <div class="qtext">${esc(q.question_text)}</div>
        ${sel!==null?`<span style="background:var(--ok-bg);color:var(--ok);border-radius:var(--r-full);padding:2px 7px;font-size:.62rem;font-weight:700;flex-shrink:0;"><i class="fas fa-check"></i></span>`:''}
        <span class="qdeg">${q.degree} درجة</span>
      </div>
      ${imgHtml}
      <div class="qopts">${opts.map((o,j)=>`<div class="qopt${sel===j?' selected':''}" onclick="pickOpt(${q.id},${j},this)"><div class="oradio"></div><div class="olet">${LETTERS[j]}</div>${esc(o)}</div>`).join('')}</div>
    </div>`;
  }).join('');
  updExamProgress(t);
}

function pickOpt(qid,idx,el){
  if(examDone)return;
  taskAnswers[String(qid)]=idx;
  localStorage.setItem(`ta_${curTask.id}_${student.id}`,JSON.stringify(taskAnswers));
  // MCQ: toggle selected class
  if(el && el.closest && el.closest('.qopts')){
    el.closest('.qopts').querySelectorAll('.qopt').forEach(o=>o.classList.remove('selected'));
    el.classList.add('selected');
  }
  // TF: re-render TF buttons with updated state
  const tfT = document.getElementById(`tfbtn_${qid}_0`);
  const tfF = document.getElementById(`tfbtn_${qid}_1`);
  if(tfT && tfF){
    const isTrueSelected = (idx===0);
    tfT.style.border = `2px solid ${isTrueSelected?'#10b981':'var(--bdr)'}`;
    tfT.style.background = isTrueSelected?'#d1fae5':'var(--s2)';
    tfT.style.color = isTrueSelected?'#065f46':'var(--t2)';
    tfT.querySelector('i').style.color = isTrueSelected?'#10b981':'var(--t4)';
    tfF.style.border = `2px solid ${!isTrueSelected?'#ef4444':'var(--bdr)'}`;
    tfF.style.background = !isTrueSelected?'#fee2e2':'var(--s2)';
    tfF.style.color = !isTrueSelected?'#991b1b':'var(--t2)';
    tfF.querySelector('i').style.color = !isTrueSelected?'#ef4444':'var(--t4)';
  }
  updExamProgress(curTask);
}

function pickOpenAns(qid, textarea){
  if(examDone){textarea.value=taskAnswers[qid]||'';return;}
  taskAnswers[String(qid)]=textarea.value;
  localStorage.setItem(`ta_${curTask.id}_${student.id}`,JSON.stringify(taskAnswers));
  updExamProgress(curTask);
}

function updExamProgress(t){
  const qs=t?t.questions||[]:curTask?curTask.questions||[]:[];
  const total=qs.length;
  let done=0;
  // Build nav dots and update card borders
  const navEl=document.getElementById('examQNav');
  const dots=qs.map((q,i)=>{
    const k=String(q.id);
    const isOpen=q.question_type==='open';
    let answered=false;
    if(isOpen) answered=!!(taskAnswers[k]&&String(taskAnswers[k]).trim());
    else answered=taskAnswers[k]!==undefined;
    if(answered) done++;
    // Update card border to show answered/unanswered visually
    const card=document.getElementById(`qc_${q.id}`);
    if(card){
      card.style.borderColor=answered?'var(--ok)':'var(--bdr)';
      card.style.borderWidth=answered?'2px':'1.5px';
    }
    return `<div onclick="document.getElementById('qc_${q.id}')?.scrollIntoView({behavior:'smooth',block:'center'})"
      title="سؤال ${i+1}" style="width:22px;height:22px;border-radius:50%;cursor:pointer;
        display:flex;align-items:center;justify-content:center;font-size:.6rem;font-weight:800;
        flex-shrink:0;transition:all .2s;
        background:${answered?'var(--ok)':'var(--bdr)'};
        color:${answered?'#fff':'var(--t3)'};
        box-shadow:${answered?'0 2px 6px rgba(5,150,105,.3)':'none'};"
    >${i+1}</div>`;
  });
  if(navEl) navEl.innerHTML=dots.join('');
  const pct=total>0?Math.round(done/total*100):0;
  const el=document.getElementById('examAnsDone');if(el)el.textContent=done;
  const pb=document.getElementById('examProgBar');if(pb)pb.style.width=pct+'%';
}

function startExamCountdown(t){
  clearInterval(examTimerIv);
  const beh=t.timer_behavior||'submit';
  const tick=()=>{
    if(!examStartedAt||!examTimeLimitSec)return;
    const elapsed=Math.floor((Date.now()-examStartedAt.getTime())/1000);
    const rem=examTimeLimitSec-elapsed;
    const badge=document.getElementById('examTimerBadge');
    // Guard: only fire expire logic if exam is actually active on screen
    if(rem<=0){
      clearInterval(examTimerIv);
      if(badge){badge.textContent='00:00';badge.classList.add('urgent');}
      // Only auto-submit if exam screen is open and not already done
      if(!examDone){
        if(beh==='submit') _doSubmitExam();
        else{examDone=true;toast('انتهى الوقت','err');}
      }
      return;
    }
    const m=Math.floor(rem/60).toString().padStart(2,'0');
    const s=(rem%60).toString().padStart(2,'0');
    const urg=rem<=60;
    if(badge){
      badge.style.display='block';
      badge.innerHTML=`<i class="fas fa-stopwatch" style="font-size:.75rem;margin-left:4px;"></i>${m}:${s}`;
      badge.classList.toggle('urgent',urg);
    }
  };
  // Delay first tick by 1s so the UI finishes rendering before any expire check
  examTimerIv=setInterval(tick,1000);
  setTimeout(tick,500);
}

function examScreenOpen(){
  const scr=document.getElementById('examScreen');
  scr.style.display='block';
  scr.scrollTop=0;
  document.documentElement.classList.add('ov-open');
}

function examScreenClose(){
  clearInterval(examTimerIv);
  document.getElementById('examScreen').style.display='none';
  if(!document.querySelector('.overlay.open')) document.documentElement.classList.remove('ov-open');
  curTask=null;examDone=false;examStartedAt=null;taskAnswers={};
  examShowView('start');
}

function confirmExitExam(){
  if(examDone){examScreenClose();return;}
  if(Object.keys(taskAnswers).length>0){
    document.getElementById('scModalMsg').textContent='إجاباتك محفوظة تلقائياً. هل تريد الخروج؟';
    document.getElementById('exitConfirmModal').classList.add('open');
    document.documentElement.classList.add('ov-open');
    return;
  }
  examScreenClose();
}
function _closeExitConfirm(){
  document.getElementById('exitConfirmModal').classList.remove('open');
  if(!document.querySelector('.overlay.open')) document.documentElement.classList.remove('ov-open');
}
function _confirmExit(){
  _closeExitConfirm();
  examScreenClose();
}

// alias used by result "back" button
function exitExamScreen(){examScreenClose();}

function examShowView(which){
  ['examStartView','examActiveView','examResultView'].forEach(id=>{
    const el=document.getElementById(id);
    if(el){el.style.display='none';}
  });
  const map={start:'examStartView',active:'examActiveView',result:'examResultView'};
  const el=document.getElementById(map[which]);
  if(el){el.style.display='flex';}
}


function buildResultCard(score, total, pct, coupons, hasOpenQs, taskId, showAnswers){
  let grad, iconCls, msg, color;
  if(pct >= 90){
    grad = 'linear-gradient(135deg, #059669 0%, #10b981 100%)';
    iconCls = 'fas fa-trophy';
    msg = 'ممتاز! إجاباتك رائعة، أنت نجم الفصل!';
    color = '#059669';
  } else if(pct >= 70){
    grad = 'linear-gradient(135deg, #1d4ed8 0%, #3b82f6 100%)';
    iconCls = 'fas fa-medal';
    msg = 'أحسنت جداً! نتيجة جميلة وأنت تستاهل أكثر من كده.';
    color = '#1d4ed8';
  } else if(pct >= 50){
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
      <i class="fas fa-ticket-alt"></i> حصلت على ${coupons} كوبون!
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
function showExamResult(t,sub){
  curTask=t;examDone=true;
  const pct=t.total_degree>0?Math.round(sub.score/t.total_degree*100):0;
  const hasOpenQs=(t.questions||[]).some(q=>q.question_type==='open');
  document.getElementById('examResultCard').innerHTML=buildResultCard(sub.score,t.total_degree,pct,sub.coupons_awarded,hasOpenQs,t.id,!!parseInt(t.show_answers||0));
  examShowView('result');
  examScreenOpen();
}

// legacy stubs
function openExamResult(t){showExamResult(t,t.my_submission);}
function renderExam(t){renderExamQuestions(t);}
function updProg(t){updExamProgress(t);}
function updAnsCnt(t){updExamProgress(t);}
function saveCloseExam(){examScreenClose();}
function closeExam(){examScreenClose();}

async function submitExam(){
  if(!curTask||examDone)return;
  const qs=curTask.questions||[];
  // Count unanswered questions of all types
  const unanswered=qs.filter(q=>{
    const k=String(q.id);
    if(q.question_type==='open') return !taskAnswers[k]||!String(taskAnswers[k]).trim();
    return taskAnswers[k]===undefined&&taskAnswers[q.id]===undefined;
  }).length;
  if(unanswered>0){
    _showSubmitConfirm(unanswered);
    return;
  }
  await _doSubmitExam();
}

function _showSubmitConfirm(unanswered){
  document.getElementById('scModalMsg').textContent=`لم تجب على ${unanswered} سؤال. هل تريد التسليم الآن؟`;
  document.getElementById('submitConfirmModal').classList.add('open');
  document.documentElement.classList.add('ov-open');
}
function _closeSubmitConfirm(){
  document.getElementById('submitConfirmModal').classList.remove('open');
  if(!document.querySelector('.overlay.open')) document.documentElement.classList.remove('ov-open');
}
function _confirmSubmitExam(){
  _closeSubmitConfirm();
  _doSubmitExam();
}

async function _doSubmitExam(){
  if(!curTask||examDone)return;
  clearInterval(examTimerIv);examDone=true;
  const tt=examStartedAt?Math.floor((Date.now()-examStartedAt.getTime())/1000):null;
  try{
    const d=await api({action:'submitTaskAnswers',student_id:student.id,church_id:student.church_id,task_id:curTask.id,answers:JSON.stringify(taskAnswers),time_taken_sec:tt});
    localStorage.removeItem(`ta_${curTask.id}_${student.id}`);
    localStorage.removeItem(`examStart_${curTask.id}_${student.id}`);
    if(d.success){
      // Build a complete my_submission object so viewMyAnswers works immediately
      // without waiting for loadTasks() to re-fetch
      const mySubmission = {
        id: d.submission_id || null,
        score: d.score || 0,
        coupons_awarded: d.coupons_awarded || 0,
        submitted_at: new Date().toISOString(),
        answers: taskAnswers,                          // the answers we just sent
        correct_answers: d.questions_with_answers      // full questions with correct_index from API
          ? Object.fromEntries((d.questions_with_answers||[]).filter(q=>q.question_type!=='open').map(q=>[q.id,parseInt(q.correct_index)]))
          : (curTask.my_submission?.correct_answers || {}),
      };
      // Patch the task in allTasks so viewMyAnswers can find it
      curTask.my_submission = mySubmission;
      const idx = allTasks.findIndex(x=>x.id===curTask.id);
      if(idx>=0) allTasks[idx].my_submission = mySubmission;

      // Always refresh student data from server after submission
      // so coupons (task + total) reflect the real DB values
      try {
        const sd = await api({action:'getStudentProfile', studentId: student.id});
        if (sd.success && sd.student) {
          const fresh = norm(sd.student);
          // preserve fields not returned by getStudentProfile
          student.coupons            = fresh.coupons;
          student.task_coupons       = fresh.task_coupons;
          student.att_coupons        = fresh.att_coupons;
          student.com_coupons        = fresh.com_coupons;
        }
      } catch(_){}
      // Re-render coupon hero with fresh values
      renderCouponHero(student);
      document.getElementById('couponHero').style.display = 'grid';
      if(d.show_result){
        const hasOpenQs=(curTask.questions||[]).some(q=>q.question_type==='open');
        document.getElementById('examResultCard').innerHTML=buildResultCard(d.score,curTask.total_degree,d.percentage,d.coupons_awarded,hasOpenQs,curTask.id,!!d.show_answers);
        examShowView('result');
      } else{toast('تم التسليم ✓','ok');examScreenClose();}
      loadTasks();
    } else{examDone=false;toast(d.message||'فشل التسليم','err');}
  }catch(e){examDone=false;toast('خطأ في الاتصال','err');}
}

// ── Trips ─────────────────────────────────────────────────────────
async function loadTrips(isPrivate){
  if(!student?.church_id)return;
  try{
    const params={action:'getStudentTrips',church_id:student.church_id};
    if(isPrivate&&student.id) params.student_id=student.id;
    const d=await api(params);
    if(d.success&&d.trips&&d.trips.length){
      allTrips=d.trips;
      renderTrips(d.trips);
      document.getElementById('scTrips').style.display='block';
    } else {
      // No trips — keep section hidden
      document.getElementById('scTrips').style.display='none';
    }
  }catch(e){
    document.getElementById('scTrips').style.display='none';
  }
}
function renderTrips(trips){
  const el=document.getElementById('tripList');
  document.getElementById('tripSub').textContent=trips.length+' رحلة';
  const stLbl={planned:'مخطط',active:'نشط',completed:'مكتمل',cancelled:'ملغي'};
  el.innerHTML=trips.map(t=>{
    const thumb=t.image_url
      ?`<img class="trip-thumb" src="${esc(t.image_url)}" alt="${esc(t.title)}" onerror="this.style.display='none';this.nextElementSibling.style.display='flex';">`
      :'';
    const ph=`<div class="trip-thumb-placeholder" ${t.image_url?'style="display:none"':''}><i class="fas fa-bus"></i><span>${esc(t.title)}</span></div>`;
    const priceOverlay=parseFloat(t.final_price)>0
      ?`<span class="trip-price-pill main"><i class="fas fa-tag"></i>${parseFloat(t.final_price).toFixed(0)} ج.م</span>`
      :`<span class="trip-price-pill main"><i class="fas fa-gift"></i> مجانية</span>`;
    const myReg=t.my_registration;
    const remOverlay=myReg&&parseFloat(myReg.remaining)>0
      ?`<span class="trip-price-pill remaining"><i class="fas fa-exclamation-circle"></i>متبقي ${parseFloat(myReg.remaining).toFixed(0)} ج.م</span>`
      :'';
    // Kids avatars strip
    const kids=(t.registered_kids||[]).slice(0,6);
    const extra=(t.registered_count||0)-kids.length;
    const kidsHtml=kids.length?`<div class="kids-strip">
      ${kids.map(k=>`<div class="ka">${k.image_url?`<img src="${esc(k.image_url)}" alt="${esc(k.name)}">`:k.name.charAt(0)}</div>`).join('')}
      ${extra>0?`<div class="ka ka-more">+${extra}</div>`:''}
    </div>`:'';
    // "Contact uncle" button if this student is NOT registered
    const notRegistered = !myReg;
    const contactBtn = notRegistered && classUncles.length
      ? `<div class="trip-contact-bar" onclick="event.stopPropagation();openTripContactUncle(${t.id})">
          <i class="fas fa-exclamation-circle"></i>
          <span>اسمك غير موجود في القائمة</span>
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
        <div class="trip-status-overlay ts-${t.status}">${stLbl[t.status]||t.status}</div>
        <div class="trip-price-overlay">${priceOverlay}${remOverlay}</div>
      </div>
      <div class="trip-body">
        <div class="trip-title">${esc(t.title)}</div>
        ${t.description?`<div class="trip-desc">${esc(t.description)}</div>`:''}
        <div class="trip-meta-row">
          ${t.start_date_formatted?`<span class="trip-meta-chip"><i class="fas fa-calendar"></i>${t.start_date_formatted}</span>`:''}
          <span class="trip-meta-chip"><i class="fas fa-users"></i>${t.registered_count||0} مسجّل</span>
          ${typeof t.my_points !== 'undefined' ? `<span class="trip-meta-chip"><i class="fas fa-star"></i> ${esc(t.my_points)} نقاط</span>` : ''}
          ${t.max_participants?`<span class="trip-meta-chip"><i class="fas fa-user-check"></i>أقصى ${t.max_participants}</span>`:''}
        </div>
        ${kidsHtml}
        ${contactBtn}
      </div>
    </div>`;
  }).join('');
}
function openTrip(id){
  const t=allTrips.find(x=>x.id==id);if(!t)return;
  document.getElementById('tripOvTitle').textContent=t.title;
  document.getElementById('tripOvSub').textContent=`${t.registered_count||0} مسجّل`;
  const stLbl={planned:'مخطط',active:'نشط',completed:'مكتمل',cancelled:'ملغي'};
  const myReg=t.my_registration;
  const myRegHtml=myReg?`<div class="my-trip-box">
    <div class="my-trip-title"><i class="fas fa-check-circle"></i> أنت مسجّل في هذه الرحلة</div>
    <div class="my-trip-row">
      <div class="mtr-cell ok"><div class="mtr-val">${parseFloat(myReg.total_paid).toFixed(0)} ج.م</div><div class="mtr-lbl">المدفوع</div></div>
      <div class="mtr-cell${parseFloat(myReg.remaining)>0?' warn':'ok'}"><div class="mtr-val">${parseFloat(myReg.remaining).toFixed(0)} ج.م</div><div class="mtr-lbl">المتبقي</div></div>
    </div>
  </div>`:'';
  // Not-registered contact block
  const notRegistered = !myReg;
  const contactHtml = notRegistered ? buildTripContactHtml(t) : '';
  const kids=t.registered_kids||[];
  const kidsHtml=kids.length?`
    <div style="margin-bottom:5px;font-size:.78rem;font-weight:700;color:var(--t2);">${kids.length} طفل مسجّل</div>
    <div class="kids-grid">
      ${kids.map(k=>`<div class="kid-tile">
        <div class="kid-tile-av">${k.image_url?`<img src="${esc(k.image_url)}" alt="${esc(k.name)}">`:k.name.charAt(0)}</div>
        <div class="kid-tile-name">${esc(k.name)}</div>
        <div class="kid-tile-cls">${esc(k.class||'')}</div>
      </div>`).join('')}
    </div>`:`<div class="empty-st"><i class="fas fa-user-slash"></i><p>لا يوجد أطفال مسجّلون بعد</p></div>`;
  document.getElementById('tripOvBody').innerHTML=`
    ${t.image_url?`<img class="trip-detail-thumb" src="${esc(t.image_url)}" alt="">`:
    `<div class="trip-detail-ph"><i class="fas fa-bus"></i></div>`}
    ${contactHtml}
    ${myRegHtml}
    <div style="display:flex;flex-wrap:wrap;gap:7px;margin-bottom:14px;">
      <span style="padding:4px 12px;border-radius:var(--r-full);font-size:.74rem;font-weight:700;background:var(--ok-bg);color:var(--ok);">
        <i class="fas fa-tag"></i> ${parseFloat(t.final_price)>0?parseFloat(t.final_price).toFixed(0)+' ج.م':'مجانية'}
      </span>
      ${t.start_date_formatted?`<span style="padding:4px 12px;border-radius:var(--r-full);font-size:.74rem;font-weight:600;background:var(--s2);border:1px solid var(--bdr);"><i class="fas fa-calendar"></i> ${t.start_date_formatted}</span>`:''}
    </div>
    ${t.description?`<p style="font-size:.83rem;color:var(--t3);line-height:1.6;margin-bottom:14px;">${esc(t.description)}</p>`:''}
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
    const waPhone  = hasPhone ? '20'+u.phone.trim().replace(/^0/,'') : '';
    const waMsg    = encodeURIComponent('مرحباً، أنا '+((student&&student.name)||'')+' — اسمي غير موجود في قائمة رحلة "'+t.title+'"، هل يمكن تسجيلي؟');
    return `<div style="display:flex;gap:8px;align-items:center;padding:10px 14px;background:var(--surf);border:1.5px solid var(--bdr);border-radius:var(--r-md);">
      <div style="width:34px;height:34px;border-radius:50%;background:var(--brand-bg);color:var(--brand);display:flex;align-items:center;justify-content:center;font-size:.75rem;font-weight:800;flex-shrink:0;overflow:hidden;">
        ${u.image_url?`<img src="${esc(u.image_url)}" style="width:100%;height:100%;object-fit:cover;" alt="${esc(u.name)}">`:u.name.charAt(0)}
      </div>
      <div style="flex:1;min-width:0;">
        <div style="font-size:.84rem;font-weight:700;color:var(--t1);overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">${esc(u.name)}</div>
        <div style="font-size:.68rem;color:var(--t4);">مدرّس الفصل</div>
      </div>
      <div style="display:flex;gap:6px;flex-shrink:0;">
        ${hasPhone?`<a href="tel:${esc(u.phone.trim())}" style="width:34px;height:34px;border-radius:var(--r-sm);background:#d1fae5;color:#059669;display:flex;align-items:center;justify-content:center;text-decoration:none;font-size:.85rem;" title="اتصال"><i class="fas fa-phone-alt"></i></a>
        <a href="https://wa.me/${waPhone}?text=${waMsg}" target="_blank" style="width:34px;height:34px;border-radius:var(--r-sm);background:#dcfce7;color:#16a34a;display:flex;align-items:center;justify-content:center;text-decoration:none;font-size:.9rem;" title="واتساب"><i class="fab fa-whatsapp"></i></a>`
        :`<span style="font-size:.7rem;color:var(--t4);padding:4px 8px;background:var(--s2);border-radius:var(--r-sm);white-space:nowrap;">لا يوجد رقم</span>`}
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
function openTripContactUncle(tripId){
  const t = allTrips.find(x=>x.id==tripId);
  if(!t) return;
  // Build a lightweight bottom sheet using the existing uncleOv
  if(classUncles.length === 1){
    openUncleDrawer(classUncles[0].id);
  } else {
    // Multiple uncles — show the uncle section or open the first uncle drawer
    openUncleDrawer(classUncles[0].id);
  }
}

// ── Announcements ─────────────────────────────────────────────────
async function loadAnn(){
  if(!student)return;
  try{
    const d=await api({action:'getAnnouncementsForStudent',churchId:student.church_id||1,studentClass:student.class,studentName:student.name});
    if(d.success&&d.announcements&&d.announcements.length){
      document.getElementById('annList').innerHTML=d.announcements.map(a=>`
        <div class="ann-item">
          <div class="ann-top">
            <span class="ann-type"><i class="fas fa-${a.type==='button'?'link':'comment'}"></i>${a.type==='button'?'زر':'رسالة'}</span>
            <span class="ann-date">${fmtDate(a.created_at)}</span>
          </div>
          <div class="ann-text">${esc(a.text)}</div>
          ${a.type==='button'&&a.link?`<a href="${esc(a.link)}" target="_blank" class="ann-link-btn"><i class="fas fa-external-link-alt"></i> اضغط هنا</a>`:''}
        </div>`).join('');
      document.getElementById('scAnn').style.display='block';
    }
  }catch(e){}
}

// ── Edit / Password / Photo ───────────────────────────────────────
async function saveProfile(){
  const n=document.getElementById('eN').value.trim();
  if(!n){toast('أدخل الاسم','err');return;}
  try{
    const d=await api({action:'updateStudentInfo',studentId:student.id,name:n,address:document.getElementById('eA').value.trim(),phone:document.getElementById('eP').value.trim(),birthday:document.getElementById('eB').value.trim()});
    if(d.success){
      student.name=n;student.address=document.getElementById('eA').value.trim();
      student.phone=document.getElementById('eP').value.trim();student.birthday=document.getElementById('eB').value.trim();
      document.getElementById('heroName').textContent=n;
      renderInfo(student,false);closeOv('editOv');toast('تم الحفظ ✓','ok');
    } else toast(d.message||'فشل','err');
  }catch(e){toast('خطأ في الاتصال','err');}
}
async function changePass(){
  const o=document.getElementById('po').value.trim(),n=document.getElementById('pn').value.trim(),c=document.getElementById('pc').value.trim();
  if(!o||!n||!c){toast('أكمل جميع الحقول','err');return;}
  if(n!==c){toast('كلمة المرور غير متطابقة','err');return;}
  if(n.length<6){toast('٦ أحرف على الأقل','err');return;}
  try{
    const d=await api({action:'setupStudentPassword',phone:localStorage.getItem('savedUsername')||student.phone,studentId:student.id,password:o,newPassword:n});
    if(d.success){localStorage.setItem('savedPassword',n);closeOv('passOv');toast('تم التغيير ✓','ok');['po','pn','pc'].forEach(id=>document.getElementById(id).value='');}
    else toast(d.message||'فشل','err');
  }catch(e){toast('خطأ','err');}
}
function onPhoto(e){
  const file=e.target.files[0];if(!file)return;
  const reader=new FileReader();
  reader.onload=ev=>{
    document.getElementById('dropZone').style.display='none';
    document.getElementById('cropWrap').style.display='block';
    document.getElementById('cropBtn').style.display='inline-flex';
    const img=document.getElementById('cropImg'); img.src=ev.target.result;
    if(cropper)cropper.destroy();
    setTimeout(()=>{ cropper=new Cropper(img,{aspectRatio:1,viewMode:2,dragMode:'move',autoCropArea:.85,guides:false}); },100);
  };
  reader.readAsDataURL(file);
}
function doCrop(){
  if(!cropper)return;
  cropper.getCroppedCanvas({width:400,height:400,imageSmoothingQuality:'high'}).toBlob(blob=>{
    croppedBlob=blob;
    const prev=document.getElementById('photoPrev');
    prev.src=URL.createObjectURL(blob);prev.style.display='block';
    document.getElementById('cropWrap').style.display='none';
    document.getElementById('cropBtn').style.display='none';
    document.getElementById('uploadBtn').style.display='inline-flex';
    cropper.destroy();cropper=null;toast('تم القص ✓','ok');
  },'image/jpeg',.9);
}
async function uploadPhoto(){
  if(!croppedBlob){toast('اختر صورة أولاً','err');return;}
  showLoad('جارٍ رفع الصورة…');
  const fd=new FormData();
  fd.append('photo',new File([croppedBlob],`profile_${student.phone}_${Date.now()}.jpg`,{type:'image/jpeg'}));
  fd.append('studentId',student.id);fd.append('studentName',student.name);
  fd.append('studentPhone',student.phone);fd.append('studentClass',student.class);fd.append('churchId',student.church_id||1);
  try{
    const r=await fetch('https://sunday-school.rf.gd/upload.php',{method:'POST',body:fd,headers:{Accept:'application/json'}});
    const up=await r.json();
    if(!up.success)throw new Error(up.message);
    const d=await api({action:'updateStudentImage',studentId:student.id,imageUrl:up.imageUrl});
    hideLoad();
    if(d.success){
      student.image_url=up.imageUrl;
      document.getElementById('avatarInner').innerHTML=`<img src="${up.imageUrl}?t=${Date.now()}" alt="">`;
      closeOv('photoOv');resetPhoto();toast('تم رفع الصورة ✓','ok');
    } else throw new Error(d.message);
  }catch(e){hideLoad();toast('خطأ: '+e.message,'err');}
}
function resetPhoto(){
  document.getElementById('dropZone').style.display='block';
  document.getElementById('cropWrap').style.display='none';
  document.getElementById('cropBtn').style.display='none';
  document.getElementById('uploadBtn').style.display='none';
  document.getElementById('photoPrev').style.display='none';
  document.getElementById('photoIn').value='';
  if(cropper){cropper.destroy();cropper=null;}croppedBlob=null;
}

// ── Account switch ────────────────────────────────────────────────
document.getElementById('switchBtnTop').addEventListener('click',()=>{
  selAccId=student.id;
  document.getElementById('switchList').innerHTML=allAccounts.map(a=>`
    <div class="acc-item${a.id===student.id?' active':''}" data-id="${a.id}" onclick="pickAcc(${a.id})">
      <div class="acc-av">${a.image_url?`<img src="${esc(a.image_url)}" alt="">`:a.name.charAt(0)}</div>
      <div><div class="acc-name">${esc(a.name)}</div><div class="acc-cls"><i class="fas fa-graduation-cap"></i> ${esc(a.class)}</div></div>
      ${a.id===student.id?'<i class="fas fa-check-circle" style="color:var(--brand);margin-right:auto;"></i>':''}
    </div>`).join('');
  openOv('switchOv');
});
function pickAcc(id){selAccId=id;document.querySelectorAll('#switchList .acc-item').forEach(el=>el.classList.toggle('active',parseInt(el.dataset.id)===id));}
function doSwitch(){
  if(!selAccId||selAccId===student.id){closeOv('switchOv');return;}
  const acc=allAccounts.find(a=>a.id===selAccId);if(!acc)return;
  student=acc;renderPrivate(student);closeOv('switchOv');toast(`تم التبديل إلى ${acc.name} ✓`,'ok');
}

// ── Logout ────────────────────────────────────────────────────────
function doLogout(){
  closeOv('settingsOv');
  ['savedUsername','savedPassword','rememberMe','userPhone','loginType'].forEach(k=>localStorage.removeItem(k));
  const fd=new FormData();fd.append('action','logout');
  fetch(location.href,{method:'POST',body:fd}).finally(()=>location.href='/kids/login');
}

// ── UI helpers ────────────────────────────────────────────────────
function openOv(id){
  const ov=document.getElementById(id);
  ov.classList.add('open');
  document.documentElement.classList.add('ov-open');
  const sheet=ov.querySelector('.settings-sheet');
}
function closeOv(id){
  const ov=document.getElementById(id);
  ov.classList.remove('open');
  // Only remove ov-open if no other overlay is still open
  if(!document.querySelector('.overlay.open')) document.documentElement.classList.remove('ov-open');
  const sheet=ov.querySelector('.settings-sheet');
  if(sheet){clearTimeout(sheet._sst);sheet.classList.remove('ss-scrollable');}
}
function setupOvClose(){
  document.querySelectorAll('.overlay').forEach(ov=>{
    ov.addEventListener('click',e=>{if(e.target===ov)closeOv(ov.id);});
  });
  document.addEventListener('keydown',e=>{
    if(e.key==='Escape') document.querySelectorAll('.overlay.open').forEach(ov=>closeOv(ov.id));
  });
}
function showMain(){document.getElementById('mainPage').style.display='block';}
function showLoad(m='جارٍ التحميل…'){document.getElementById('lt').textContent=m;document.getElementById('ls').classList.remove('hidden');}
function hideLoad(){document.getElementById('ls').classList.add('hidden');}
function noProfile(m){hideLoad();document.getElementById('noMsg').textContent=m;document.getElementById('noProfile').style.display='block';}
function toast(m,t='info'){
  const tc=document.getElementById('tc');
  const el=document.createElement('div');el.className=`toast ${t}`;
  const ic=t==='ok'?'fa-check-circle':t==='err'?'fa-exclamation-circle':'fa-info-circle';
  el.innerHTML=`<i class="fas ${ic}"></i>${m}`;
  tc.appendChild(el);
  requestAnimationFrame(()=>requestAnimationFrame(()=>el.classList.add('show')));
  setTimeout(()=>{el.classList.remove('show');setTimeout(()=>el.remove(),350);},3200);
}
function tPass(id,btn){
  const inp=document.getElementById(id);const show=inp.type==='password';
  inp.type=show?'text':'password';
  btn.querySelector('i').className=show?'fas fa-eye-slash':'fas fa-eye';
}
function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function fmtDate(iso){
  if(!iso)return'—';
  try{return new Date(iso).toLocaleDateString('ar-EG',{day:'numeric',month:'short',year:'numeric'});}catch(e){return iso;}
}
function openModal(html) {
  const ov = document.getElementById('genericModalOv');
  if(!ov) {
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
    div.onclick = (e) => { if(e.target === div) closeModal(); };
    div.classList.add('open');
  } else {
    ov.classList.add('open');
  }
  document.getElementById('genericModalBody').innerHTML = html;
  document.documentElement.classList.add('ov-open');
}

function closeModal() {
  const ov = document.getElementById('genericModalOv');
  if(ov) ov.classList.remove('open');
  if(!document.querySelector('.overlay.open')) document.documentElement.classList.remove('ov-open');
}

function viewMyAnswers(taskId) {
  const t = allTasks.find(x=>x.id==taskId);
  if(!t || !t.my_submission) return;
  const sub = t.my_submission;

  // answers: the student's choices (keyed by question id)
  const ans = typeof sub.answers === 'string' ? JSON.parse(sub.answers) : (sub.answers || {});
  // correct_answers: map of {qId: correctIndex} — provided by API when show_answers=1
  const correctMap = sub.correct_answers || {};
  // open_scores: map of {qId: score} for open questions graded by uncle
  const openScores = typeof sub.open_scores === 'string' ? JSON.parse(sub.open_scores||'{}') : (sub.open_scores || {});

  let html = `<div style="padding:20px;max-height:75vh;overflow-y:auto;background:var(--bg);">
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:20px;padding:15px;background:#fff;border-radius:var(--r-md);box-shadow:var(--sh-sm);">
      <div style="width:50px;height:50px;border-radius:50%;background:var(--brand-bg);color:var(--brand);display:flex;align-items:center;justify-content:center;font-size:1.4rem;flex-shrink:0;"><i class="fas fa-clipboard-check"></i></div>
      <div style="flex:1;">
        <div style="font-weight:800;color:var(--t1);font-size:1.1rem;line-height:1.2;">${esc(t.title)}</div>
        <div style="font-size:.78rem;color:var(--t3);margin-top:2px;">لقد حصلت على ${sub.score} من ${t.total_degree} درجة</div>
      </div>
    </div>`;

  if(!t.questions || t.questions.length === 0) {
    html += `<div style="text-align:center;padding:40px;color:var(--t4);">لا توجد أسئلة لهذه المهمة.</div>`;
  } else {
    t.questions.forEach((q, i) => {
      const qType = q.question_type || 'mcq';
      const qId   = String(q.id);
      const given = ans[qId] !== undefined ? ans[qId] : ans[q.id];
      // Use correct_answers map first (returned by API), fall back to q.correct_index
      const correctIdx = (correctMap[q.id] !== undefined)
        ? parseInt(correctMap[q.id])
        : (q.correct_index !== null && q.correct_index !== undefined ? parseInt(q.correct_index) : null);
      const isCorrect = given !== undefined && correctIdx !== null && parseInt(given) === correctIdx;

      html += `<div style="margin-bottom:15px;padding:15px;border:1.5px solid var(--bdr);border-radius:var(--r-md);background:#fff;box-shadow:var(--sh-sm);">`;
      html += `<div style="display:flex;gap:10px;margin-bottom:12px;">
        <div style="width:26px;height:26px;border-radius:8px;background:var(--s2);color:var(--t1);display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:800;flex-shrink:0;">${i+1}</div>
        <div style="font-weight:700;color:var(--t1);line-height:1.4;flex:1;">${esc(q.question_text)}</div>
      </div>`;

      if(qType === 'open') {
        const openScore = openScores[qId] !== undefined ? openScores[qId] : (openScores[q.id] !== undefined ? openScores[q.id] : null);
        const openScoreHtml = openScore !== null
          ? `<div style="margin-top:8px;font-size:.75rem;color:var(--ok);font-weight:700;"><i class="fas fa-check-circle"></i> درجتك: ${openScore} من ${q.degree || 1}</div>`
          : `<div style="margin-top:8px;font-size:.75rem;color:var(--warn);font-weight:700;"><i class="fas fa-clock"></i> في انتظار التصحيح</div>`;
        html += `<div style="background:var(--s2);padding:15px;border-radius:var(--r-sm);border:1.5px solid var(--bdr);">
          <div style="font-size:.7rem;color:var(--t3);margin-bottom:6px;font-weight:700;">إجابتك المسجلة:</div>
          <div style="color:var(--t2);font-size:.9rem;white-space:pre-wrap;line-height:1.6;">${esc(given !== undefined ? String(given) : '— لم تُجب على هذا السؤال —')}</div>
          ${openScoreHtml}
        </div>`;
      } else {
        const opts = typeof q.options === 'string' ? JSON.parse(q.options) : (q.options || []);
        if(qType === 'tf') { opts[0] = 'صحيح'; opts[1] = 'خطأ'; }

        html += `<div style="display:flex;flex-direction:column;gap:8px;">`;
        opts.forEach((o, j) => {
          const isCorr = j === correctIdx;
          const isSel  = given !== undefined && parseInt(given) === j;

          let borderColor = 'var(--bdr)';
          let bgColor     = 'var(--surf)';
          let textColor   = 'var(--t2)';
          let icon        = '';

          if(isCorr && isSel) {
            borderColor = 'var(--ok)'; bgColor = 'var(--ok-bg)'; textColor = 'var(--ok)';
            icon = '<i class="fas fa-check-circle" style="margin-right:auto;"></i>';
          } else if(isCorr) {
            borderColor = 'var(--ok)'; bgColor = 'var(--ok-bg)'; textColor = 'var(--ok)';
            icon = '<i class="fas fa-check" style="margin-right:auto;opacity:.5;"></i>';
          } else if(isSel) {
            borderColor = 'var(--err)'; bgColor = 'var(--err-bg)'; textColor = 'var(--err)';
            icon = '<i class="fas fa-times-circle" style="margin-right:auto;"></i>';
          }

          html += `<div style="display:flex;align-items:center;gap:10px;padding:10px 14px;border-radius:var(--r-sm);border:2px solid ${borderColor};background:${bgColor};color:${textColor};font-size:.88rem;${isSel?'font-weight:700;':''}">
            <span style="width:20px;font-weight:800;opacity:.5;">${LETTERS[j]}</span>
            <span>${esc(o)}</span>
            ${icon}
          </div>`;
        });
        html += `</div>`;

        // Show "didn't answer" notice if student skipped
        if(given === undefined) {
          html += `<div style="margin-top:8px;font-size:.75rem;color:var(--t4);font-weight:700;"><i class="fas fa-minus-circle"></i> لم تُجب على هذا السؤال</div>`;
        }
      }
      html += `</div>`;
    });
  }
  html += `</div>`;

  openModal(html);
}
</script>
</body>
</html>
