<?php
// ══════════════════════════════════════════════════════════════════
// tasks.php  —  Uncle Dashboard → Tasks & Quizzes
// URL pattern:  /uncle/tasks/?class=الفصل+الأول
// Links back to dashboard class view automatically.
// ══════════════════════════════════════════════════════════════════
ini_set('session.gc_probability', 1);
ini_set('session.gc_divisor', 100);
ini_set('session.gc_maxlifetime', 60 * 60 * 24 * 365 * 10);
session_set_cookie_params([
    'lifetime' => 60 * 60 * 24 * 365 * 10,
    'path'     => '/',
    'domain'   => $_SERVER['HTTP_HOST'] ?? '',
    'secure'   => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);
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
      html += `</div>`;
    });
  }
  html += `</div>`;
  
  openModal(html);
}
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
<title>المهام — <?php echo htmlspecialchars($activeClass ?: $churchName); ?></title>
<meta name="theme-color" content="#5b6cf5">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800;900&family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="icon" href="/favicon.ico">
<style>
:root{
  --brand:#5b6cf5;--brand-d:#4154e8;--brand-l:#a5b0fb;--brand-bg:#eef0fe;--brand-glow:rgba(91,108,245,.18);
  --ok:#10b981;--ok-bg:#d1fae5;--err:#ef4444;--err-bg:#fee2e2;
  --warn:#f59e0b;--warn-bg:#fef3c7;--info:#3b82f6;--info-bg:#dbeafe;
  --cou:#8b5cf6;--cou-bg:#ede9fe;
  --t1:#1e293b;--t2:#475569;--t3:#94a3b8;
  --bg:#ffffff;--bg2:#f8fafc;--bdr:#e2e8f0;--bdr2:#cbd5e1;
  --r-sm:8px;--r-md:14px;--r-lg:20px;--r-xl:28px;--r-full:9999px;
  --sh-sm:0 2px 8px -2px rgba(0,0,0,.07);--sh-md:0 8px 24px -4px rgba(0,0,0,.10);
  --sh-lg:0 20px 40px -8px rgba(0,0,0,.13);--sh-brand:0 8px 24px -4px rgba(91,108,245,.30);
  --ease:cubic-bezier(.4,0,.2,1);--fast:.15s var(--ease);--norm:.28s var(--ease);--slow:.45s var(--ease);
}
[data-theme="dark"]{--t1:#f1f5f9;--t2:#cbd5e1;--t3:#64748b;--bg:#1e293b;--bg2:#0f172a;--bdr:#334155;--bdr2:#475569;}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
html{scroll-behavior:smooth}
body{font-family:'Cairo',sans-serif;background:linear-gradient(135deg,#f8fafc 0%,#f0f4ff 50%,#e8edff 100%);color:var(--t1);min-height:100vh;overflow-x:hidden;}
body::before{content:'';position:fixed;inset:0;background:radial-gradient(circle at 15% 85%,rgba(91,108,245,.07) 0%,transparent 45%),radial-gradient(circle at 85% 15%,rgba(139,92,246,.05) 0%,transparent 45%);pointer-events:none;z-index:0;}

/* ── Topbar ── */
.topbar{position:sticky;top:0;z-index:100;display:flex;align-items:center;gap:10px;padding:0 20px;height:62px;background:rgba(255,255,255,.85);backdrop-filter:blur(16px);border-bottom:1px solid var(--bdr);box-shadow:var(--sh-sm);}
.tb-back{display:flex;align-items:center;gap:7px;padding:7px 13px;border-radius:var(--r-full);background:var(--bg2);border:1px solid var(--bdr);color:var(--t2);font-size:.82rem;font-weight:600;text-decoration:none;transition:var(--fast);white-space:nowrap;}
.tb-back:hover{background:var(--brand-bg);color:var(--brand);border-color:var(--brand-l);}
.tb-title{flex:1;display:flex;align-items:center;gap:9px;font-family:'Tajawal',sans-serif;font-weight:800;font-size:1.03rem;color:var(--t1);min-width:0;}
.tb-icon{width:33px;height:33px;border-radius:var(--r-sm);background:linear-gradient(135deg,var(--brand),var(--brand-d));display:flex;align-items:center;justify-content:center;color:#fff;font-size:.83rem;box-shadow:var(--sh-brand);flex-shrink:0;}
.tb-cls{text-align: center;padding:3px 11px;border-radius:var(--r-full);background:var(--brand-bg);border:1px solid var(--brand-l);font-size:.73rem;font-weight:700;color:var(--brand);white-space:nowrap; margin-bottom: 11px;}
.btn-create{display:inline-flex;align-items:center;gap:6px;padding:8px 17px;border-radius:var(--r-full);background:linear-gradient(135deg,var(--brand),var(--brand-d));color:#fff;font-family:'Cairo',sans-serif;font-weight:700;font-size:.83rem;border:none;cursor:pointer;box-shadow:var(--sh-brand);transition:var(--fast);white-space:nowrap;}
.btn-create:hover{transform:translateY(-1px);box-shadow:0 12px 28px -6px rgba(91,108,245,.42);}

/* ── Page ── */
.page{max-width:1140px;margin:0 auto;padding:24px 18px 70px;position:relative;z-index:1;}

/* ── Stats ── */
.stats{display:grid;grid-template-columns:repeat(4,1fr);gap:11px;margin-bottom:22px;}
.scard{background:var(--bg);border:1px solid var(--bdr);border-radius:var(--r-lg);padding:15px 17px;display:flex;align-items:center;gap:12px;box-shadow:var(--sh-sm);transition:var(--norm);}
.scard:hover{transform:translateY(-2px);box-shadow:var(--sh-md);}
.scard-ico{width:40px;height:40px;border-radius:var(--r-md);display:flex;align-items:center;justify-content:center;font-size:1rem;flex-shrink:0;}
.scard-val{font-size:1.45rem;font-weight:800;line-height:1;color:var(--t1);}
.scard-lbl{font-size:.73rem;color:var(--t3);margin-top:2px;}

/* ── Section header ── */
.sec-hdr{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:14px;flex-wrap:wrap;}
.sec-title{font-family:'Tajawal',sans-serif;font-weight:800;font-size:.97rem;color:var(--t1);display:flex;align-items:center;gap:7px;}
.sec-dot{width:7px;height:7px;border-radius:50%;background:var(--brand);}
.ftabs{display:flex;gap:5px;flex-wrap:wrap;}
.ftab{padding:5px 12px;border-radius:var(--r-full);font-size:.77rem;font-weight:600;cursor:pointer;border:1.5px solid var(--bdr);background:var(--bg);color:var(--t2);transition:var(--fast);}
.ftab:hover,.ftab.active{background:var(--brand-bg);color:var(--brand);border-color:var(--brand);}
.hero{display:grid;grid-template-columns:minmax(0,1.4fr) minmax(280px,.9fr);gap:18px;margin-bottom:18px;}
.hero-card{position:relative;overflow:hidden;background:linear-gradient(135deg,#ffffff 0%,#eef3ff 52%,#f8fbff 100%);border:1px solid rgba(165,176,251,.55);border-radius:calc(var(--r-xl) + 4px);padding:24px;box-shadow:var(--sh-md);}
.hero-card::before{content:'';position:absolute;inset:auto auto -60px -40px;width:180px;height:180px;border-radius:50%;background:radial-gradient(circle,rgba(91,108,245,.16),transparent 70%);}
.hero-card::after{content:'';position:absolute;top:-70px;left:-20px;width:180px;height:180px;border-radius:50%;background:radial-gradient(circle,rgba(16,185,129,.10),transparent 70%);}
.hero-main,.hero-side{position:relative;z-index:1;}
.hero-badge{display:inline-flex;align-items:center;gap:7px;padding:7px 12px;border-radius:var(--r-full);background:rgba(255,255,255,.82);border:1px solid rgba(165,176,251,.7);font-size:.75rem;font-weight:800;color:var(--brand);margin-bottom:14px;box-shadow:var(--sh-sm);}
.hero-title{font-family:'Tajawal',sans-serif;font-size:1.85rem;font-weight:900;line-height:1.2;color:var(--t1);margin-bottom:8px;}
.hero-sub{max-width:620px;font-size:.92rem;line-height:1.9;color:var(--t2);}
.hero-actions{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px;}
.hero-link{display:inline-flex;align-items:center;gap:7px;padding:9px 14px;border-radius:var(--r-full);border:1px solid var(--bdr);background:rgba(255,255,255,.76);color:var(--t2);text-decoration:none;font-size:.8rem;font-weight:700;transition:var(--fast);}
.hero-link:hover{border-color:var(--brand-l);color:var(--brand);background:var(--brand-bg);}
.hero-side{display:grid;grid-template-columns:1fr 1fr;gap:10px;}
.hero-mini{background:rgba(255,255,255,.78);border:1px solid rgba(203,213,225,.85);border-radius:var(--r-lg);padding:16px;backdrop-filter:blur(10px);}
.hero-mini-label{font-size:.72rem;color:var(--t3);margin-bottom:7px;}
.hero-mini-value{font-size:1.2rem;font-weight:900;color:var(--t1);line-height:1;}
.hero-mini-note{font-size:.73rem;color:var(--t2);margin-top:6px;}
.list-shell{background:rgba(255,255,255,.58);border:1px solid rgba(226,232,240,.95);border-radius:var(--r-xl);padding:18px;box-shadow:var(--sh-sm);backdrop-filter:blur(14px);}

/* ── Tasks grid ── */
.tgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(305px,1fr));gap:15px;}
.tcard{position:relative;background:linear-gradient(180deg,rgba(255,255,255,.98),rgba(248,250,252,.98));border:1px solid rgba(226,232,240,.95);border-radius:calc(var(--r-lg) + 2px);overflow:hidden;box-shadow:var(--sh-sm);transition:var(--norm);cursor:pointer;}
.tcard:hover{transform:translateY(-4px);box-shadow:0 18px 36px -14px rgba(91,108,245,.24);border-color:rgba(165,176,251,.95);}
.tcard::after{content:'';position:absolute;inset:auto -30px -40px auto;width:120px;height:120px;border-radius:50%;background:radial-gradient(circle,rgba(91,108,245,.12),transparent 70%);pointer-events:none;}
.tcard-acc{height:6px;background:linear-gradient(90deg,var(--brand),var(--brand-l));}
.tcard-acc.ok{background:linear-gradient(90deg,var(--ok),#6ee7b7);}
.tcard-acc.warn{background:linear-gradient(90deg,var(--warn),#fde68a);}
.tcard-acc.err{background:linear-gradient(90deg,var(--err),#fca5a5);}
.tcard-body{padding:16px 17px 13px;position:relative;z-index:1;}
.tcard-top{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;margin-bottom:12px;}
.tcard-title{font-weight:800;font-size:1rem;color:var(--t1);line-height:1.5;flex:1;}
.tstatus{display:inline-flex;align-items:center;gap:4px;padding:3px 9px;border-radius:var(--r-full);font-size:.69rem;font-weight:700;white-space:nowrap;flex-shrink:0;}
.s-active{background:var(--ok-bg);color:#059669;}
.s-upcoming{background:var(--info-bg);color:#2563eb;}
.s-ended{background:var(--bg2);color:var(--t3);border:1px solid var(--bdr);}
.s-draft{background:var(--warn-bg);color:#b45309;}
.tclass-inline{display:inline-flex;align-items:center;gap:6px;padding:5px 10px;border-radius:var(--r-full);background:var(--brand-bg);border:1px solid var(--brand-l);font-size:.71rem;font-weight:700;color:var(--brand);margin-bottom:12px;}
.tmeta{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px;}
.tmeta-i{display:flex;align-items:center;gap:7px;font-size:.72rem;color:var(--t2);background:rgba(248,250,252,.95);border:1px solid var(--bdr);border-radius:12px;padding:8px 10px;min-height:42px;}
.tmeta-i i{color:var(--brand);font-size:.8rem;}
.tinfo-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:12px;}
.tinfo-pill{display:flex;align-items:center;gap:8px;background:linear-gradient(180deg,var(--bg),var(--bg2));border:1px solid var(--bdr);border-radius:14px;padding:10px;}
.tinfo-pill i{font-size:.8rem;color:var(--brand);}
.tip-val{font-size:.93rem;font-weight:800;color:var(--t1);}
.tip-lbl{font-size:.68rem;color:var(--t3);}
.tprogress{padding:10px 12px;border-radius:14px;background:rgba(238,240,254,.6);border:1px solid rgba(165,176,251,.5);}
.prog-bar{height:6px;background:rgba(203,213,225,.7);border-radius:var(--r-full);overflow:hidden;margin-bottom:7px;}
.prog-fill{height:100%;border-radius:var(--r-full);background:linear-gradient(90deg,var(--brand),var(--brand-l));transition:width .6s var(--ease);}
.prog-lbl{display:flex;justify-content:space-between;font-size:.69rem;color:var(--t2);}
.tcard-foot{padding:12px 17px;border-top:1px solid var(--bdr);display:flex;align-items:center;justify-content:space-between;gap:10px;background:rgba(248,250,252,.95);position:relative;z-index:1;}
.tclass-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 11px;border-radius:var(--r-full);font-size:.71rem;font-weight:700;background:#fff;color:var(--t2);border:1px solid var(--bdr);}
.tact{display:flex;gap:7px;}
.tbtn{height:32px;padding:0 11px;border-radius:10px;border:1px solid var(--bdr);background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--t2);font-size:.75rem;transition:var(--fast);gap:5px;font-weight:700;}
.tbtn:hover{background:var(--brand-bg);color:var(--brand);border-color:var(--brand-l);}
.tbtn.d:hover{background:var(--err-bg);color:var(--err);border-color:#fca5a5;}
.empty{grid-column:1/-1;text-align:center;padding:58px 20px;}
.empty-ico{width:72px;height:72px;border-radius:50%;background:var(--brand-bg);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:1.8rem;color:var(--brand);}
.empty-t{font-weight:700;font-size:1rem;color:var(--t1);margin-bottom:6px;}
.empty-s{font-size:.83rem;color:var(--t3);margin-bottom:20px;}

/* ── Overlay / Modal ── */
.overlay{position:fixed;inset:0;background:rgba(15,23,42,.6);backdrop-filter:blur(6px);z-index:500;display:flex;align-items:flex-start;justify-content:center;padding:16px;overflow-y:auto;opacity:0;visibility:hidden;transition:var(--norm);}
.overlay.fullscreen{padding:0;align-items:stretch;}
.overlay.fullscreen .modal{max-width:100%;width:100%;border-radius:0;margin:0;min-height:100vh;display:flex;flex-direction:column;transform:none!important;}
.overlay.fullscreen .mbody{flex:1;overflow-y:auto;}
.overlay.open{opacity:1;visibility:visible;}
.modal{background:var(--bg);border-radius:var(--r-xl);width:100%;max-width:730px;margin:auto;box-shadow:0 40px 80px -16px rgba(0,0,0,.25);transform:translateY(26px) scale(.97);transition:var(--slow);border:1px solid var(--bdr);}
.overlay.open .modal{transform:translateY(0) scale(1);}
.modal.wide{max-width:860px;}
.modal.narrow{max-width:390px;}
.mhdr{display:flex;align-items:center;gap:12px;padding:19px 22px;border-bottom:1px solid var(--bdr);}
.mhdr-ico{width:38px;height:38px;border-radius:var(--r-md);background:linear-gradient(135deg,var(--brand),var(--brand-d));display:flex;align-items:center;justify-content:center;color:#fff;font-size:.95rem;box-shadow:var(--sh-brand);flex-shrink:0;}
.mhdr-title{font-family:'Tajawal',sans-serif;font-weight:800;font-size:1.02rem;color:var(--t1);}
.mhdr-sub{font-size:.73rem;color:var(--t3);margin-top:2px;}
.mclose{width:31px;height:31px;border-radius:var(--r-sm);border:1px solid var(--bdr);background:var(--bg2);display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--t3);font-size:.85rem;transition:var(--fast);margin-right:auto;}
.mclose:hover{background:var(--err-bg);color:var(--err);}
.mbody{padding:20px 22px;}
.mfoot{padding:13px 22px;border-top:1px solid var(--bdr);display:flex;align-items:center;justify-content:flex-end;gap:8px;background:var(--bg2);border-radius:0 0 var(--r-xl) var(--r-xl);}

/* ── Wizard steps ── */
.steps{display:flex;align-items:center;margin-bottom:20px;}
.step{flex:1;display:flex;flex-direction:column;align-items:center;gap:4px;position:relative;}
.step:not(:last-child)::after{content:'';position:absolute;top:12px;left:calc(-50% + 12px);right:calc(50% + 12px);height:2px;background:var(--bdr);z-index:0;}
.step.done:not(:last-child)::after{background:var(--brand);}
.step-c{width:24px;height:24px;border-radius:50%;border:2px solid var(--bdr);background:var(--bg);display:flex;align-items:center;justify-content:center;font-size:.68rem;font-weight:700;color:var(--t3);z-index:1;transition:var(--norm);}
.step.active .step-c{border-color:var(--brand);background:var(--brand);color:#fff;}
.step.done .step-c{border-color:var(--brand);background:var(--brand);color:#fff;}
.step-l{font-size:.64rem;color:var(--t3);font-weight:600;text-align:center;}
.step.active .step-l,.step.done .step-l{color:var(--brand);}

/* ── Form ── */
.fsec{margin-bottom:20px;}
.fsec-title{font-weight:700;font-size:.76rem;color:var(--brand);letter-spacing:.04em;text-transform:uppercase;margin-bottom:11px;display:flex;align-items:center;gap:6px;}
.fsec-title::after{content:'';flex:1;height:1px;background:linear-gradient(90deg,var(--brand-l),transparent);}
.frow{display:grid;grid-template-columns:1fr 1fr;gap:11px;margin-bottom:11px;}
.frow.full{grid-template-columns:1fr;}
.fg{display:flex;flex-direction:column;gap:4px;}
.flbl{font-size:.75rem;font-weight:700;color:var(--t2);display:flex;align-items:center;gap:4px;}
.flbl .req{color:var(--err);}
.flbl .tip{color:var(--t3);font-weight:400;font-size:.69rem;}
.fi,.fs,.fta{padding:9px 12px;border:1.5px solid var(--bdr);border-radius:var(--r-md);font-family:'Cairo',sans-serif;font-size:.85rem;color:var(--t1);background:var(--bg);outline:none;transition:var(--fast);width:100%;}
.fi:focus,.fs:focus,.fta:focus{border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-glow);}
.fta{resize:vertical;min-height:62px;}
.tgl-row{display:flex;align-items:center;justify-content:space-between;padding:10px 13px;background:var(--bg2);border:1px solid var(--bdr);border-radius:var(--r-md);margin-bottom:8px;}
.tgl-lbl{font-size:.82rem;font-weight:600;color:var(--t1);}
.tgl-desc{font-size:.69rem;color:var(--t3);margin-top:2px;}
.tgl{position:relative;width:40px;height:22px;flex-shrink:0;}
.tgl input{opacity:0;width:0;height:0;position:absolute;}
.tgl-s{position:absolute;inset:0;border-radius:var(--r-full);background:var(--bdr2);cursor:pointer;transition:var(--norm);}
.tgl-s::after{content:'';position:absolute;width:16px;height:16px;border-radius:50%;background:#fff;top:3px;right:3px;transition:var(--norm);box-shadow:0 1px 3px rgba(0,0,0,.15);}
.tgl input:checked+.tgl-s{background:var(--brand);}
.tgl input:checked+.tgl-s::after{transform:translateX(-18px);}

/* ── Questions ── */
.qlist{display:flex;flex-direction:column;gap:12px;margin-bottom:11px;}
.qcard{background:var(--bg2);border:1.5px solid var(--bdr);border-radius:var(--r-lg);overflow:hidden;transition:var(--fast);}
.qcard:focus-within{border-color:var(--brand);box-shadow:0 0 0 3px var(--brand-glow);}
.qhdr{display:flex;align-items:center;gap:8px;padding:11px 13px 9px;border-bottom:1px solid var(--bdr);background:var(--bg);}
.qnum{width:24px;height:24px;border-radius:var(--r-sm);background:linear-gradient(135deg,var(--brand),var(--brand-d));display:flex;align-items:center;justify-content:center;font-size:.7rem;font-weight:800;color:#fff;flex-shrink:0;}
.qi{flex:1;border:none;background:transparent;outline:none;font-family:'Cairo',sans-serif;font-size:.86rem;font-weight:600;color:var(--t1);}
.qi::placeholder{color:var(--t3);}
.qdeg{display:flex;align-items:center;gap:5px;background:var(--brand-bg);border:1px solid var(--brand-l);border-radius:var(--r-sm);padding:4px 8px;}
.qdeg-l{font-size:.66rem;color:var(--brand);font-weight:600;white-space:nowrap;}
.qdeg-i{width:34px;border:none;background:transparent;outline:none;font-family:'Cairo',sans-serif;font-size:.8rem;font-weight:700;color:var(--brand);text-align:center;}
.qrm{width:24px;height:24px;border-radius:var(--r-sm);border:1px solid var(--bdr);background:var(--bg);display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--t3);font-size:.7rem;transition:var(--fast);}
.qrm:hover{background:var(--err-bg);color:var(--err);}
.qbody{padding:11px 13px;}
.opts{display:flex;flex-direction:column;gap:6px;margin-bottom:8px;}
.orow{display:flex;align-items:center;gap:7px;}
.oradio{width:17px;height:17px;border-radius:50%;border:2px solid var(--bdr);flex-shrink:0;cursor:pointer;transition:var(--fast);display:flex;align-items:center;justify-content:center;font-size:.58rem;font-weight:700;}
.oradio.ok{border-color:var(--ok);background:var(--ok);color:#fff;}
.oradio:not(.ok):hover{border-color:var(--ok);}
.olet{width:19px;height:19px;border-radius:var(--r-sm);background:var(--bdr);display:flex;align-items:center;justify-content:center;font-size:.65rem;font-weight:700;color:var(--t2);flex-shrink:0;}
.oinp{flex:1;padding:6px 10px;border:1.5px solid var(--bdr);border-radius:var(--r-sm);font-family:'Cairo',sans-serif;font-size:.78rem;color:var(--t1);background:var(--bg);outline:none;transition:var(--fast);}
.oinp:focus{border-color:var(--brand);box-shadow:0 0 0 2px var(--brand-glow);}
.odel{width:22px;height:22px;border-radius:var(--r-sm);border:1px solid var(--bdr);background:transparent;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--t3);font-size:.66rem;transition:var(--fast);flex-shrink:0;}
.odel:hover{color:var(--err);}
.add-opt{display:inline-flex;align-items:center;gap:5px;padding:5px 10px;border-radius:var(--r-full);border:1.5px dashed var(--bdr);background:transparent;font-family:'Cairo',sans-serif;font-size:.72rem;font-weight:600;color:var(--t3);cursor:pointer;transition:var(--fast);}
.add-opt:hover{border-color:var(--brand);color:var(--brand);background:var(--brand-bg);}
.add-q{display:flex;align-items:center;justify-content:center;gap:7px;width:100%;padding:10px;border-radius:var(--r-lg);border:2px dashed var(--brand-l);background:var(--brand-bg);font-family:'Cairo',sans-serif;font-size:.84rem;font-weight:700;color:var(--brand);cursor:pointer;transition:var(--fast);}
.add-q:hover{background:var(--brand);color:#fff;border-color:var(--brand);}
.deg-sum{display:flex;align-items:center;justify-content:space-between;padding:10px 13px;background:var(--brand-bg);border:1px solid var(--brand-l);border-radius:var(--r-md);margin-top:11px;}
.deg-sum-l{font-size:.78rem;color:var(--brand);font-weight:600;}
.deg-sum-v{font-size:1.02rem;font-weight:800;color:var(--brand);}

/* ── Coupon tiers ── */
.ctiers{display:flex;flex-direction:column;gap:6px;}
.ctier{display:flex;align-items:center;gap:8px;flex-wrap:wrap;background:var(--bg2);border:1px solid var(--bdr);border-radius:var(--r-md);padding:8px 11px;}
.ctier-range{display:flex;align-items:center;gap:4px;font-size:.77rem;color:var(--t2);}
.ctier input[type=number]{width:50px;padding:5px 6px;border:1.5px solid var(--bdr);border-radius:var(--r-sm);font-family:'Cairo',sans-serif;font-size:.78rem;color:var(--t1);background:var(--bg);outline:none;text-align:center;}
.ctier input[type=number]:focus{border-color:var(--brand);}
.ctier-arr{color:var(--t3);font-size:.76rem;}
.crew{display:flex;align-items:center;gap:5px;background:var(--cou-bg);border:1px solid #c4b5fd;border-radius:var(--r-sm);padding:5px 8px;}
.crew i{color:var(--cou);}
.crew input[type=number]{width:42px;background:transparent;border:none;outline:none;font-family:'Cairo',sans-serif;font-size:.8rem;font-weight:700;color:var(--cou);text-align:center;}
.crew-l{font-size:.68rem;color:var(--cou);font-weight:600;}
.ctier-del{width:22px;height:22px;border-radius:var(--r-sm);border:1px solid var(--bdr);background:transparent;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--t3);font-size:.66rem;transition:var(--fast);margin-right:auto;}
.ctier-del:hover{color:var(--err);}

/* ── Buttons ── */
.btn{display:inline-flex;align-items:center;gap:6px;padding:8px 17px;border-radius:var(--r-full);font-family:'Cairo',sans-serif;font-size:.84rem;font-weight:700;border:1.5px solid transparent;cursor:pointer;transition:var(--fast);}
.btn-p{background:linear-gradient(135deg,var(--brand),var(--brand-d));color:#fff;box-shadow:var(--sh-brand);}
.btn-p:hover{transform:translateY(-1px);box-shadow:0 12px 26px -6px rgba(91,108,245,.42);}
.btn-g{background:var(--bg2);color:var(--t2);border-color:var(--bdr);}
.btn-g:hover{background:var(--brand-bg);color:var(--brand);border-color:var(--brand-l);}
.btn-dg{background:transparent;color:var(--err);border-color:#fca5a5;}
.btn-dg:hover{background:var(--err-bg);}
.btn:disabled{opacity:.5;cursor:not-allowed;transform:none!important;}

/* ── Detail view ── */
.dq{background:var(--bg2);border:1px solid var(--bdr);border-radius:var(--r-md);margin-bottom:8px;overflow:hidden;}
.dq-hdr{display:flex;align-items:center;gap:8px;padding:10px 13px;border-bottom:1px solid var(--bdr);background:var(--bg);}
.dq-opt{display:flex;align-items:center;gap:8px;padding:5px 10px;border-radius:var(--r-sm);border:1px solid var(--bdr);font-size:.78rem;color:var(--t2);margin-bottom:4px;}
.dq-opt.ok{background:var(--ok-bg);border-color:#6ee7b7;color:#065f46;font-weight:600;}
.dq-opt:last-child{margin-bottom:0;}
.sub-tbl{width:100%;border-collapse:collapse;}
.sub-tbl th{padding:8px 12px;text-align:right;font-size:.72rem;font-weight:700;color:var(--t3);background:var(--bg2);border-bottom:1px solid var(--bdr);}
.sub-tbl td{padding:8px 12px;font-size:.79rem;color:var(--t1);border-bottom:1px solid var(--bdr);}
.sub-tbl tr:last-child td{border-bottom:none;}
.sub-tbl tr:hover td{background:var(--bg2);}

/* ── Spinner ── */
.spinner{display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;}
@keyframes spin{to{transform:rotate(360deg)}}

/* ── Confirm ── */
.conf-body{padding:24px 22px;text-align:center;}
.conf-ico{width:54px;height:54px;border-radius:50%;background:var(--err-bg);display:flex;align-items:center;justify-content:center;margin:0 auto 13px;font-size:1.35rem;color:var(--err);}
.conf-t{font-weight:800;font-size:.98rem;color:var(--t1);margin-bottom:6px;}
.conf-s{font-size:.81rem;color:var(--t3);}

/* ── Toast ── */
.tc{position:fixed;bottom:20px;left:50%;transform:translateX(-50%);z-index:9999;display:flex;flex-direction:column;gap:6px;pointer-events:none;}
.toast{display:flex;align-items:center;gap:8px;padding:10px 17px;border-radius:var(--r-full);background:var(--t1);color:#fff;font-size:.82rem;font-weight:600;box-shadow:var(--sh-lg);opacity:0;transform:translateY(8px);transition:var(--norm);pointer-events:auto;white-space:nowrap;}
.toast.show{opacity:1;transform:translateY(0);}
.toast.ok{background:#059669;}
.toast.err{background:var(--err);}
.toast.info{background:var(--brand);}

/* ── Responsive ── */
@media(max-width:680px){
  .hero,.step1-grid,.detail-columns,.detail-overview{grid-template-columns:1fr;}
  .hero-side,.field-grid,.timer-grid,.preset-grid,.tmeta,.tinfo-grid{grid-template-columns:1fr 1fr;}
  .stats{grid-template-columns:1fr 1fr;}
  .frow{grid-template-columns:1fr;}
  .tgrid{grid-template-columns:1fr;}
  .topbar,.mbody,.mhdr,.mfoot{padding-left:12px;padding-right:12px;}
  .hero-card{padding:18px;}
  .list-shell{padding:14px;}
  .tcard-foot{flex-direction:column;align-items:stretch;}
  .tact{width:100%;}
  .tbtn{flex:1;}
  .steps{gap:8px;}
  .step:not(:last-child)::after{display:none;}
  .scard2-body,.panel-body{padding:14px;}
  .ans-shell{padding:12px;}
  .ans-head{padding:14px;align-items:flex-start;}
  .sub-tbl thead{display:none;}
  .sub-tbl,.sub-tbl tbody,.sub-tbl tr,.sub-tbl td{display:block;width:100%;}
  .sub-tbl tr{padding:12px;border-bottom:1px solid var(--bdr);}
  .sub-tbl td{padding:6px 0 !important;border:none !important;text-align:right;}
}
@media(max-width:380px){
  .scard{padding:11px 12px;}
  .scard-val{font-size:1.2rem;}
  .hero-side,.hero-mini,.field-grid,.timer-grid,.preset-grid,.tmeta,.tinfo-grid{grid-template-columns:1fr;}
  .topbar{padding:10px 12px;height:auto;flex-wrap:wrap;}
  .tb-title{order:-1;width:100%;}
  .btn-create,.hero-link,.tb-back{width:100%;justify-content:center;}
}
/* q-type-selector */
.q-type-selector{display:flex;gap:6px;padding:10px 13px 8px;background:var(--bg);border-bottom:1px solid var(--bdr);}
.q-type-btn{flex:1;display:flex;align-items:center;justify-content:center;gap:5px;padding:8px 6px;border-radius:var(--r-md);border:1.5px solid var(--bdr);background:var(--bg2);color:var(--t2);font-family:'Cairo',sans-serif;font-size:.73rem;font-weight:700;cursor:pointer;transition:var(--fast);white-space:nowrap;}
.q-type-btn:hover{border-color:var(--brand-l);color:var(--brand);background:var(--brand-bg);}
.q-type-btn.active{background:var(--brand-bg);border-color:var(--brand);color:var(--brand);box-shadow:0 0 0 2px var(--brand-glow);}
.q-type-btn.active-tf{background:#fef3c7;border-color:#f59e0b;color:#92400e;box-shadow:0 0 0 2px rgba(245,158,11,.15);}
.q-type-btn.active-open{background:#f0fdf4;border-color:#10b981;color:#065f46;box-shadow:0 0 0 2px rgba(16,185,129,.12);}
.q-type-btn i{font-size:.78rem;flex-shrink:0;}
/* True/False */
.tf-opts{display:flex;gap:10px;padding:10px 13px;}
.tf-btn{flex:1;display:flex;align-items:center;justify-content:center;gap:8px;padding:12px;border-radius:var(--r-md);border:2px solid var(--bdr);background:var(--bg2);font-family:'Cairo',sans-serif;font-size:.88rem;font-weight:700;cursor:pointer;transition:var(--fast);color:var(--t2);}
.tf-btn:hover{border-color:var(--brand-l);}
.tf-btn.tf-true.selected{background:#d1fae5;border-color:#10b981;color:#065f46;}
.tf-btn.tf-false.selected{background:#fee2e2;border-color:#ef4444;color:#991b1b;}
.tf-btn.tf-true i{color:#10b981;}
.tf-btn.tf-false i{color:#ef4444;}
/* Question image */
.q-img-section{padding:8px 13px 10px;border-top:1px solid var(--bdr);background:var(--bg);}
.q-img-toggle{display:flex;align-items:center;gap:7px;font-size:.73rem;font-weight:700;color:var(--t3);cursor:pointer;padding:4px 0;transition:color var(--fast);background:none;border:none;font-family:'Cairo',sans-serif;width:100%;}
.q-img-toggle:hover{color:var(--brand);}
.q-img-input-wrap{display:none;margin-top:8px;flex-direction:column;gap:6px;}
.q-img-input-wrap.open{display:flex;}
.q-img-url-row{display:flex;gap:7px;align-items:center;}
.q-img-url-inp{flex:1;padding:7px 11px;border:1.5px solid var(--bdr);border-radius:var(--r-sm);font-family:'Cairo',sans-serif;font-size:.78rem;color:var(--t1);background:var(--bg2);outline:none;transition:border-color var(--fast);}
.q-img-url-inp:focus{border-color:var(--brand);}
.q-img-fetch-btn{padding:7px 13px;border-radius:var(--r-sm);background:var(--brand);color:#fff;border:none;font-family:'Cairo',sans-serif;font-size:.74rem;font-weight:700;cursor:pointer;white-space:nowrap;transition:var(--fast);}
.q-img-fetch-btn:hover{background:var(--brand-d);}
.q-img-preview{display:none;position:relative;border-radius:var(--r-md);overflow:hidden;background:var(--bg2);border:1.5px solid var(--bdr);max-height:220px;}
.q-img-preview img{width:100%;max-height:220px;object-fit:contain;display:block;}
.q-img-remove{position:absolute;top:6px;left:6px;width:26px;height:26px;border-radius:50%;background:rgba(239,68,68,.9);border:none;color:#fff;font-size:.72rem;cursor:pointer;display:flex;align-items:center;justify-content:center;}
.q-img-status{font-size:.7rem;color:var(--t3);font-weight:500;}
.q-img-status.ok{color:var(--ok);}
.q-img-status.err{color:var(--err);}
/* Open note */
.open-q-note{display:flex;align-items:center;gap:8px;margin:8px 13px 10px;padding:9px 12px;background:#f0fdf4;border:1px solid #6ee7b7;border-radius:var(--r-sm);font-size:.74rem;color:#065f46;font-weight:600;}
/* Grade panel */
.grade-panel{position:fixed;inset:0;z-index:900;background:rgba(0,0,0,.55);backdrop-filter:blur(6px);display:none;align-items:flex-end;justify-content:center;}
.grade-panel.open{display:flex;}
.grade-sheet{background:var(--bg);border-radius:20px 20px 0 0;width:100%;max-width:700px;max-height:90vh;overflow-y:auto;box-shadow:0 -8px 40px rgba(0,0,0,.18);display:flex;flex-direction:column;}
.grade-sheet-hdr{padding:18px 20px 14px;border-bottom:1px solid var(--bdr);display:flex;align-items:center;gap:12px;position:sticky;top:0;background:var(--bg);z-index:2;}
.grade-sheet-body{padding:16px 20px;flex:1;}
.grade-sub-card{background:var(--bg2);border:1.5px solid var(--bdr);border-radius:var(--r-md);padding:14px 16px;margin-bottom:12px;}
.grade-sub-name{font-size:.9rem;font-weight:800;color:var(--t1);margin-bottom:10px;display:flex;align-items:center;gap:8px;}
.grade-q-row{margin-bottom:12px;}
.grade-q-text{font-size:.82rem;font-weight:600;color:var(--t2);margin-bottom:5px;}
.grade-ans-text{background:var(--bg);border:1px solid var(--bdr);border-radius:var(--r-sm);padding:9px 12px;font-size:.82rem;color:var(--t1);margin-bottom:7px;white-space:pre-wrap;word-break:break-word;line-height:1.55;}
.grade-score-row{display:flex;align-items:center;gap:8px;}
.grade-score-inp{width:80px;padding:6px 10px;border:1.5px solid var(--bdr);border-radius:var(--r-sm);font-family:'Cairo',sans-serif;font-size:.88rem;text-align:center;}
.grade-score-inp:focus{border-color:var(--brand);outline:none;}
.grade-max-lbl{font-size:.75rem;color:var(--t3);}
.grade-save-btn{display:block;width:100%;margin-top:14px;padding:12px;background:linear-gradient(135deg,var(--brand),var(--brand-d));color:#fff;border:none;border-radius:var(--r-full);font-family:'Cairo',sans-serif;font-size:.92rem;font-weight:700;cursor:pointer;transition:var(--fast);}
.grade-save-btn:hover{transform:translateY(-1px);box-shadow:var(--sh-brand);}
.pending-badge{display:inline-flex;align-items:center;gap:4px;background:#fee2e2;color:#dc2626;border-radius:var(--r-full);padding:2px 9px;font-size:.7rem;font-weight:700;}

/* ── Image source tabs ── */
.q-img-tabs{display:flex;gap:0;border-radius:var(--r-sm);overflow:hidden;border:1.5px solid var(--bdr);background:var(--bg2);margin-bottom:8px;}
.q-img-tab{flex:1;padding:7px 10px;border:none;background:transparent;font-family:'Cairo',sans-serif;font-size:.74rem;font-weight:700;color:var(--t3);cursor:pointer;transition:var(--fast);display:flex;align-items:center;justify-content:center;gap:5px;}
.q-img-tab:first-child{border-left:1px solid var(--bdr);}
.q-img-tab.active{background:var(--brand);color:#fff;}
.q-img-tab:hover:not(.active){background:var(--brand-bg);color:var(--brand);}
.q-img-tab-panel{display:none;}
.q-img-tab-panel.active{display:flex;flex-direction:column;gap:6px;}
/* Upload drag zone */
.q-img-drop{border:2px dashed var(--bdr);border-radius:var(--r-md);padding:18px 14px;text-align:center;cursor:pointer;transition:var(--fast);background:var(--bg2);}
.q-img-drop:hover,.q-img-drop.dragover{border-color:var(--brand);background:var(--brand-bg);}
.q-img-drop i{font-size:1.6rem;color:var(--brand);display:block;margin-bottom:6px;}
.q-img-drop p{font-size:.74rem;color:var(--t3);margin:0;}
.q-img-drop small{font-size:.66rem;color:var(--t4);}
.q-img-uploading{display:none;align-items:center;gap:8px;font-size:.75rem;color:var(--brand);font-weight:600;}

/* ── Settings step clean cards ── */
.scard2{background:var(--bg);border:1.5px solid var(--bdr);border-radius:var(--r-lg);overflow:hidden;margin-bottom:14px;}
.scard2-hdr{display:flex;align-items:center;gap:9px;padding:12px 18px;background:linear-gradient(90deg,var(--bg2),var(--bg));border-bottom:1px solid var(--bdr);font-size:.82rem;font-weight:800;color:var(--brand);letter-spacing:.03em;text-transform:uppercase;}
.scard2-hdr i{font-size:.88rem;}
.scard2-body{padding:16px 18px;}
.scard2-body > .fg,.scard2-body > .frow,.scard2-body > #specRow{padding:14px;border-radius:18px;background:linear-gradient(180deg,#fff,var(--bg2));border:1px solid rgba(226,232,240,.95);margin-bottom:12px;}
.scard2-body > .fg:last-child{margin-bottom:0;}
.sopt-row{display:flex;align-items:center;gap:13px;padding:12px 14px;border-radius:var(--r-md);border:1.5px solid var(--bdr);background:var(--bg2);cursor:pointer;transition:var(--fast);margin-bottom:10px;}
.sopt-row:hover{border-color:var(--brand-l);background:var(--brand-bg);}
.sopt-ico{width:38px;height:38px;border-radius:var(--r-sm);display:flex;align-items:center;justify-content:center;font-size:.95rem;flex-shrink:0;}
.sopt-txt{flex:1;}
.sopt-lbl{font-size:.85rem;font-weight:700;color:var(--t1);}
.sopt-desc{font-size:.71rem;color:var(--t3);margin-top:2px;}
.hint-box{display:flex;align-items:flex-start;gap:9px;padding:11px 12px;border-radius:var(--r-md);background:linear-gradient(135deg,#f8fafc,#eef4ff);border:1px solid #dbeafe;margin-bottom:12px;}
.hint-box i{color:var(--info);font-size:.95rem;margin-top:2px;}
.hint-box strong{display:block;font-size:.8rem;color:var(--t1);margin-bottom:2px;}
.hint-box span{font-size:.72rem;color:var(--t3);line-height:1.7;}
.quick-presets{display:flex;flex-wrap:wrap;gap:7px;margin-top:10px;}
.quick-btn{padding:7px 11px;border-radius:var(--r-full);border:1.5px solid var(--bdr);background:var(--bg);color:var(--t2);font-family:'Cairo',sans-serif;font-size:.74rem;font-weight:700;cursor:pointer;transition:var(--fast);}
.quick-btn:hover{background:var(--brand-bg);color:var(--brand);border-color:var(--brand-l);}
.mini-note{font-size:.7rem;color:var(--t3);margin-top:5px;line-height:1.7;}
.date-switch{display:grid;grid-template-columns:1fr;gap:10px;}
.time-block.is-disabled{opacity:.6;pointer-events:none;}
.step1-grid{display:grid;grid-template-columns:minmax(0,1.25fr) minmax(290px,.85fr);gap:16px;align-items:start;}
.step-stack{display:flex;flex-direction:column;gap:14px;}
.panel-card{background:linear-gradient(180deg,var(--bg),rgba(248,250,252,.96));border:1px solid rgba(226,232,240,.95);border-radius:24px;box-shadow:var(--sh-sm);overflow:hidden;}
.panel-head{display:flex;align-items:flex-start;justify-content:space-between;gap:14px;padding:20px 20px 0;}
.panel-title{font-family:'Tajawal',sans-serif;font-size:1.02rem;font-weight:900;color:var(--t1);}
.panel-sub{font-size:.76rem;line-height:1.8;color:var(--t3);margin-top:4px;}
.panel-body{padding:18px 20px 20px;}
.panel-mark{width:44px;height:44px;border-radius:16px;background:linear-gradient(135deg,var(--brand),var(--brand-d));display:flex;align-items:center;justify-content:center;color:#fff;font-size:1rem;box-shadow:var(--sh-brand);flex-shrink:0;}
.field-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;}
.field-grid.single{grid-template-columns:1fr;}
.field-card{padding:14px;border-radius:18px;border:1px solid var(--bdr);background:rgba(248,250,252,.9);}
.field-card.full{grid-column:1/-1;}
.field-card .flbl{margin-bottom:6px;}
.field-note{font-size:.71rem;color:var(--t3);line-height:1.7;margin-top:6px;}
.spec-list-card{max-height:190px;overflow-y:auto;border:1.5px solid var(--bdr);border-radius:16px;padding:12px;display:flex;flex-direction:column;gap:8px;background:var(--bg);font-size:.82rem;color:var(--t3);}
.setting-group{display:flex;flex-direction:column;gap:10px;}
.setting-title{font-size:.8rem;font-weight:800;color:var(--brand);letter-spacing:.03em;text-transform:uppercase;margin-bottom:2px;}
.setting-item{display:flex;align-items:flex-start;gap:12px;padding:14px;border-radius:18px;border:1px solid var(--bdr);background:rgba(248,250,252,.96);transition:var(--fast);cursor:pointer;}
.setting-item:hover{border-color:var(--brand-l);background:var(--brand-bg);}
.setting-item.primary{background:linear-gradient(135deg,rgba(238,240,254,.85),rgba(255,255,255,.96));border-color:rgba(165,176,251,.72);}
.setting-icon{width:40px;height:40px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:.95rem;flex-shrink:0;}
.setting-copy{flex:1;}
.setting-copy strong{display:block;font-size:.86rem;color:var(--t1);margin-bottom:3px;}
.setting-copy span{display:block;font-size:.73rem;color:var(--t3);line-height:1.7;}
.setting-copy small{display:inline-flex;align-items:center;gap:5px;margin-top:6px;padding:4px 8px;border-radius:999px;background:#fff;border:1px solid rgba(203,213,225,.9);font-size:.66rem;font-weight:700;color:var(--t2);}
.preset-grid{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px;margin-top:10px;}
.preset-btn{padding:9px 8px;border-radius:14px;border:1px solid var(--bdr);background:#fff;font-family:'Cairo',sans-serif;font-size:.75rem;font-weight:800;color:var(--t2);cursor:pointer;transition:var(--fast);}
.preset-btn:hover{background:var(--brand-bg);border-color:var(--brand-l);color:var(--brand);}
.timer-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-top:10px;}
.coupon-summary{display:flex;align-items:center;gap:10px;padding:14px 16px;border-radius:18px;background:linear-gradient(135deg,var(--cou-bg),rgba(255,255,255,.95));border:1px solid #d8b4fe;margin-bottom:14px;}
.coupon-summary i{width:38px;height:38px;border-radius:14px;display:flex;align-items:center;justify-content:center;background:#fff;color:var(--cou);}
.coupon-summary strong{display:block;font-size:.88rem;color:var(--t1);}
.coupon-summary span{display:block;font-size:.72rem;color:var(--t3);margin-top:2px;}
.detail-shell{display:flex;flex-direction:column;gap:16px;}
.detail-overview{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;}
.detail-stat{padding:15px;border-radius:18px;background:linear-gradient(180deg,var(--bg),var(--bg2));border:1px solid var(--bdr);box-shadow:var(--sh-sm);}
.detail-stat-label{font-size:.72rem;color:var(--t3);margin-bottom:7px;}
.detail-stat-value{font-size:1.08rem;font-weight:900;color:var(--t1);}
.detail-banner{display:flex;flex-wrap:wrap;align-items:center;gap:8px;padding:14px 16px;border-radius:20px;background:linear-gradient(135deg,rgba(238,240,254,.9),rgba(255,255,255,.98));border:1px solid rgba(165,176,251,.6);}
.coupon-chips{display:flex;flex-wrap:wrap;gap:8px;}
.detail-columns{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.detail-card{background:var(--bg);border:1px solid var(--bdr);border-radius:20px;box-shadow:var(--sh-sm);overflow:hidden;}
.detail-card-head{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:15px 16px;border-bottom:1px solid var(--bdr);background:rgba(248,250,252,.92);}
.detail-card-title{font-size:.84rem;font-weight:800;color:var(--t1);}
.detail-list{padding:8px 16px 14px;}
.detail-person{display:flex;align-items:center;gap:10px;padding:10px 0;border-bottom:1px solid rgba(226,232,240,.8);}
.detail-person:last-child{border-bottom:none;padding-bottom:0;}
.detail-person-badge{display:inline-flex;align-items:center;gap:5px;padding:3px 8px;border-radius:999px;background:var(--brand-bg);color:var(--brand);font-size:.67rem;font-weight:800;}
.detail-empty{padding:18px 0;font-size:.78rem;color:var(--t3);}
.ans-shell{padding:18px;max-height:min(78vh,760px);overflow-y:auto;background:linear-gradient(180deg,#f8fbff 0%,#f8fafc 100%);}
.ans-head{display:flex;align-items:center;gap:14px;padding:16px 18px;border-radius:20px;background:#fff;border:1px solid var(--bdr);box-shadow:var(--sh-sm);margin-bottom:16px;}
.ans-avatar{width:54px;height:54px;border-radius:18px;background:linear-gradient(135deg,var(--brand-bg),#fff);color:var(--brand);display:flex;align-items:center;justify-content:center;font-size:1.25rem;flex-shrink:0;border:1px solid var(--brand-l);}
.ans-name{font-size:1.02rem;font-weight:900;color:var(--t1);line-height:1.2;}
.ans-sub{font-size:.76rem;color:var(--t3);margin-top:4px;}
.ans-question{margin-bottom:14px;padding:16px;border:1px solid var(--bdr);border-radius:20px;background:#fff;box-shadow:var(--sh-sm);}
.ans-qhead{display:flex;gap:10px;align-items:flex-start;margin-bottom:12px;}
.ans-qnum{width:30px;height:30px;border-radius:10px;background:var(--brand-bg);color:var(--brand);display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:900;flex-shrink:0;border:1px solid var(--brand-l);}
.ans-qtext{font-weight:800;color:var(--t1);line-height:1.6;flex:1;}
.ans-open{padding:14px;border-radius:16px;background:var(--bg2);border:1px solid var(--bdr);}
.ans-open-label{font-size:.7rem;color:var(--t3);margin-bottom:6px;font-weight:800;}
.ans-open-text{color:var(--t2);font-size:.9rem;white-space:pre-wrap;line-height:1.7;}
.ans-choice{display:flex;align-items:center;gap:10px;padding:11px 13px;border-radius:14px;border:1.5px solid var(--bdr);background:#fff;color:var(--t2);font-size:.86rem;}
.ans-choice + .ans-choice{margin-top:8px;}
.ans-choice.correct{border-color:#86efac;background:var(--ok-bg);color:#047857;}
.ans-choice.wrong{border-color:#fca5a5;background:var(--err-bg);color:#dc2626;}
.ans-choice-letter{width:24px;height:24px;border-radius:8px;background:rgba(148,163,184,.16);display:flex;align-items:center;justify-content:center;font-weight:900;font-size:.72rem;flex-shrink:0;}
.ans-choice-icon{margin-right:auto;font-size:.95rem;}

</style>
</head>
<body>

<!-- ══ TOPBAR ═══════════════════════════════════════════════════ -->
<nav class="topbar">
  <a href="<?php echo htmlspecialchars($dashBack); ?>" class="tb-back">
    <i class="fas fa-chevron-right"></i>
    <?php echo $activeClass ? htmlspecialchars($activeClass) : 'لوحة التحكم'; ?>
  </a>
  <div class="tb-title">
    <div class="tb-icon"><i class="fas fa-tasks"></i></div>
    المهام والاختبارات
  </div>
  <button class="btn-create" onclick="openCreate()">
    <i class="fas fa-plus"></i> مهمة جديدة
  </button>
</nav>

<!-- ══ PAGE ══════════════════════════════════════════════════════ -->
<main class="page">
  <section class="hero">
    <div class="hero-card hero-main">
      <?php if ($activeClass): ?>
        <div class="hero-badge"><i class="fas fa-users"></i><?php echo htmlspecialchars($activeClass); ?></div>
      <?php endif; ?>
      <div class="hero-title">المهام والاختبارات</div>
      <div class="hero-sub">تابع حالة كل مهمة بسرعة، وابدأ مهمة جديدة من واجهة أبسط تركز على المواعيد والإعدادات المهمة بدون تعقيد.</div>
      <div class="hero-actions">
        <button class="btn-create" onclick="openCreate()"><i class="fas fa-plus"></i> مهمة جديدة</button>
        <a class="hero-link" href="<?php echo htmlspecialchars($dashBack); ?>"><i class="fas fa-arrow-right"></i> الرجوع للوحة الفصل</a>
      </div>
    </div>
    <div class="hero-side">
      <div class="hero-mini">
        <div class="hero-mini-label">إجمالي المهام</div>
        <div class="hero-mini-value" id="stTotal">—</div>
        <div class="hero-mini-note">كل المسودات والمنشور</div>
      </div>
      <div class="hero-mini">
        <div class="hero-mini-label">نشطة الآن</div>
        <div class="hero-mini-value" id="stActive">—</div>
        <div class="hero-mini-note">المتاح للطلاب حاليًا</div>
      </div>
      <div class="hero-mini">
        <div class="hero-mini-label">قادمة</div>
        <div class="hero-mini-value" id="stUpcoming">—</div>
        <div class="hero-mini-note">جاهزة للبدء قريبًا</div>
      </div>
      <div class="hero-mini">
        <div class="hero-mini-label">كوبونات ممنوحة</div>
        <div class="hero-mini-value" id="stCoupons">—</div>
        <div class="hero-mini-note">إجمالي ما تم منحه</div>
      </div>
    </div>
  </section>

  <section class="list-shell">
    <div class="sec-hdr">
      <div class="sec-title"><div class="sec-dot"></div>المهام</div>
      <div class="ftabs">
        <div class="ftab active" onclick="setFilter('all',this)">الكل</div>
        <div class="ftab" onclick="setFilter('active',this)">نشطة</div>
        <div class="ftab" onclick="setFilter('upcoming',this)">قادمة</div>
        <div class="ftab" onclick="setFilter('ended',this)">منتهية</div>
        <div class="ftab" onclick="setFilter('draft',this)">مسودات</div>
      </div>
    </div>

    <div class="tgrid" id="tGrid">
      <div class="empty"><div class="empty-ico"><i class="fas fa-circle-notch fa-spin"></i></div><div class="empty-t">جارٍ التحميل…</div></div>
    </div>
  </section>
</main>

<!-- ══ CREATE / EDIT MODAL ══════════════════════════════════════ -->
<div class="overlay fullscreen" id="createOv">
  <div class="modal">
    <div class="mhdr">
      <div class="mhdr-ico"><i class="fas fa-pen-nib"></i></div>
      <div><div class="mhdr-title" id="createTitle">إنشاء مهمة جديدة</div><div class="mhdr-sub">اختبار MCQ مع مكافآت كوبونات</div></div>
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
          <div class="scard2-hdr"><i class="fas fa-pen"></i> معلومات المهمة</div>
          <div class="scard2-body">
            <div class="fg" style="margin-bottom:12px;">
              <label class="flbl">العنوان <span class="req">*</span></label>
              <input id="fTitle" class="fi" type="text" placeholder="مثال: اختبار سفر التكوين" style="font-size:.95rem;">
            </div>
            <div class="fg" style="margin-bottom:12px;">
              <label class="flbl">الفصل <span class="req">*</span></label>
              <select id="fClass" class="fs" onchange="onClassChange()" style="font-size:.9rem;"><option value="">— اختر الفصل —</option></select>
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
              <textarea id="fDesc" class="fta" placeholder="تعليمات للطالب…"></textarea>
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
                <div class="sopt-desc">عداد تنازلي يبدأ عند فتح الطالب للمهمة</div>
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
                <strong>Ù…Ø§ ÙŠØ±Ø§Ù‡ Ø§Ù„Ø·Ø§Ù„Ø¨</strong>
                <span>Ø§Ø®ØªØ± Ø§Ù„Ø®ÙŠØ§Ø±Ø§Øª Ø§Ù„ØªÙŠ ØªØ¬Ø¹Ù„ Ø§Ù„Ù…Ù‡Ù…Ø© Ø£ÙˆØ¶Ø­: Ù†ØªÙŠØ¬Ø© ÙÙˆØ±ÙŠØ©ØŒ Ù…Ø±Ø§Ø¬Ø¹Ø© Ø§Ù„Ø¥Ø¬Ø§Ø¨Ø§ØªØŒ Ø£Ùˆ Ø¥Ø¸Ù‡Ø§Ø± Ø§Ù„Ø­Ù„ÙˆÙ„ Ø¨Ø¹Ø¯ Ø§Ù„Ø¥Ù†Ù‡Ø§Ø¡.</span>
              </div>
            </div>
            <div class="sopt-row" onclick="document.getElementById('fShowAns').click()">
              <div class="sopt-ico" style="background:#cffafe;color:#0891b2;"><i class="fas fa-eye"></i></div>
              <div class="sopt-txt"><div class="sopt-lbl">إظهار الإجابات المفصلة</div><div class="sopt-desc">السماح للطالب بمعرفة إجاباته الصحيحة والخاطئة</div></div>
              <label class="tgl" onclick="event.stopPropagation()"><input type="checkbox" id="fShowAns"><span class="tgl-s"></span></label>
            </div>
            <div class="sopt-row" onclick="document.getElementById('fShowRes').click()">
              <div class="sopt-ico" style="background:#d1fae5;color:#059669;"><i class="fas fa-check-circle"></i></div>
              <div class="sopt-txt"><div class="sopt-lbl">إظهار النتيجة فور الانتهاء</div><div class="sopt-desc">يرى الطفل درجته مباشرةً بعد التسليم</div></div>
              <label class="tgl" onclick="event.stopPropagation()"><input type="checkbox" id="fShowRes" checked><span class="tgl-s"></span></label>
            </div>
            <div class="sopt-row" onclick="document.getElementById('fShuffle').click()">
              <div class="sopt-ico" style="background:#e0e7ff;color:#4f46e5;"><i class="fas fa-random"></i></div>
              <div class="sopt-txt"><div class="sopt-lbl">خلط ترتيب الأسئلة</div><div class="sopt-desc">ترتيب عشوائي مختلف لكل طالب</div></div>
              <label class="tgl" onclick="event.stopPropagation()"><input type="checkbox" id="fShuffle"><span class="tgl-s"></span></label>
            </div>
            <div class="sopt-row" style="margin-bottom:0;" onclick="document.getElementById('fReview').click()">
              <div class="sopt-ico" style="background:#fef3c7;color:#d97706;"><i class="fas fa-eye"></i></div>
              <div class="sopt-txt"><div class="sopt-lbl">مراجعة الإجابات قبل الإرسال</div><div class="sopt-desc">يستطيع الطالب تغيير إجاباته قبل التسليم النهائي</div></div>
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
          <div class="fsec-title"><i class="fas fa-ticket-alt"></i>مستويات الكوبونات</div>
          <p style="font-size:.77rem;color:var(--t3);margin-bottom:13px;">حدد كم كوبون يحصل عليه الطفل بناءً على نسبة إجاباته الصحيحة.<br>الدرجة الكلية: <strong id="s3deg">0</strong> درجة.</p>
          <div class="ctiers" id="ctierList"></div>
          <button class="add-opt" style="margin-top:9px;width:100%;justify-content:center;" onclick="addTier()"><i class="fas fa-plus"></i>إضافة مستوى</button>
        </div>
      </div>

    </div>
    <div class="mfoot">
      <div style="margin-left:auto;font-size:.73rem;color:var(--t3);">الخطوة <strong id="stepNum">1</strong> من 3</div>
      <button class="btn btn-g" id="prevBtn" onclick="prevStep()" style="display:none;"><i class="fas fa-chevron-right"></i> السابق</button>
      <button class="btn btn-g" id="draftBtn" onclick="saveDraft()"><i class="fas fa-save"></i> مسودة</button>
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
      <div><div class="mhdr-title" id="dTitle">تفاصيل المهمة</div><div class="mhdr-sub" id="dSub"></div></div>
      <div class="mclose" onclick="closeDetail()"><i class="fas fa-times"></i></div>
    </div>
    <div class="mbody" id="dBody" style="max-height:75vh;overflow-y:auto;"></div>
    <div class="mfoot" id="dFoot"></div>
  </div>
</div>

<!-- ══ CONFIRM DELETE ════════════════════════════════════════════ -->
<div class="overlay" id="confOv">
  <div class="modal narrow">
    <div class="conf-body">
      <div class="conf-ico"><i class="fas fa-trash-alt"></i></div>
      <div class="conf-t">حذف المهمة؟</div>
      <div class="conf-s" id="confSub">لا يمكن التراجع.</div>
    </div>
    <div class="mfoot" style="justify-content:center;gap:10px;">
      <button class="btn btn-g" onclick="closeConf()">إلغاء</button>
      <button class="btn btn-dg" onclick="doDelete()"><i class="fas fa-trash-alt"></i> حذف</button>
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
  const s = document.getElementById('fClass');
  s.innerHTML = '<option value="">— اختر الفصل —</option>';
  const allOpt = document.createElement('option');
  allOpt.value = 'كل الفصول';
  allOpt.dataset.id = '0';
  allOpt.textContent = 'كل الفصول';
  if (CFG.activeClass === 'كل الفصول') allOpt.selected = true;
  s.appendChild(allOpt);
  allClasses.forEach(c => {
    const o = document.createElement('option');
    o.value = c.arabic_name; o.dataset.id = c.id; o.textContent = c.arabic_name;
    if (CFG.activeClass && c.arabic_name === CFG.activeClass) o.selected = true;
    s.appendChild(o);
  });
}

// ─── Load tasks ─────────────────────────────────────────────────
async function loadTasks() {
  try {
    const extra = {};
    if (CFG.activeClass) extra.class_name = CFG.activeClass;
    const d = await api('getTasks', extra);
    if (d.success) tasks = d.tasks || [];
    else showToast(d.message||'فشل تحميل المهام', 'err');
  } catch(e) { showToast('خطأ في الاتصال', 'err'); }
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
        const student = {id: s._studentId, name: s['الاسم']||s.name||''};
        if (!classStuCache[c]) classStuCache[c] = [];
        classStuCache[c].push(student);
        classStuCache['كل الفصول'].push(student);
      });
    }
  } catch(e) {}
  return classStuCache[cls] || [];
}

// ─── Render tasks grid ──────────────────────────────────────────
function statusOf(t) {
  if (t.status==='draft') return {key:'draft',cls:'s-draft',label:'مسودة',acc:'warn'};
  const n=Date.now(), s=new Date(t.start_date).getTime();
  const hasEnd = !parseInt(t.no_deadline||0) && !!t.end_date;
  const e=hasEnd ? new Date(t.end_date).getTime() : null;
  if (n<s) return {key:'upcoming',cls:'s-upcoming',label:'قادمة',acc:''};
  if (hasEnd && n>e) return {key:'ended',   cls:'s-ended',   label:'منتهية',acc:'err'};
  return          {key:'active',  cls:'s-active',  label:'نشطة',  acc:'ok'};
}
function setFilter(f, el) {
  curFilter=f;
  document.querySelectorAll('.ftab').forEach(t=>t.classList.remove('active'));
  el.classList.add('active');
  renderGrid();
}
function renderGrid() {
  const g = document.getElementById('tGrid');
  let list = curFilter==='all' ? tasks : tasks.filter(t=>statusOf(t).key===curFilter);
  if (!list.length) {
    g.innerHTML = `<div class="empty"><div class="empty-ico"><i class="fas fa-clipboard-list"></i></div><div class="empty-t">لا توجد مهام</div><div class="empty-s">اضغط "مهمة جديدة" لإنشاء أول اختبار</div><button class="btn btn-p" onclick="openCreate()"><i class="fas fa-plus"></i> إنشاء مهمة</button></div>`;
    return;
  }
  g.innerHTML = list.map(t => {
    const si   = statusOf(t);
    const qs   = (t.questions||[]).length;
    const subs = (t.submissions||[]).length;
    const asgn = t.assign_to==='specific' ? (t.specific_ids ? JSON.parse(t.specific_ids).length : 0) : (classStuCache[t.class_name]?.length ?? '?');
    const pct  = (asgn && asgn!=='?') ? Math.round(subs/asgn*100) : 0;
    const tc   = (t.submissions||[]).reduce((a,s)=>a+(parseInt(s.coupons_awarded)||0),0);
    return `<div class="tcard" onclick="openDetail(${t.id})">
      <div class="tcard-acc${si.acc?' '+si.acc:''}"></div>
      <div class="tcard-body">
        <div class="tcard-top"><div class="tcard-title">${esc(t.title)}</div><div class="tstatus ${si.cls}">${si.label}</div></div>
        <div class="tmeta">
          <div class="tmeta-i"><i class="fas fa-calendar-check"></i>${fmtDate(t.start_date)}</div>
          <div class="tmeta-i"><i class="fas fa-flag-checkered"></i>${parseInt(t.no_deadline||0)?'بدون آخر موعد':fmtDate(t.end_date)}</div>
          ${t.time_limit?`<div class="tmeta-i"><i class="fas fa-stopwatch"></i>${t.time_limit} دقيقة</div>`:''}
        </div>
        <div class="tinfo-grid">
          <div class="tinfo-pill"><i class="fas fa-question-circle"></i><div><div class="tip-val">${qs} سؤال</div><div class="tip-lbl">${t.total_degree||0} درجة</div></div></div>
          <div class="tinfo-pill"><i class="fas fa-ticket-alt" style="color:var(--cou);"></i><div><div class="tip-val">${tc}</div><div class="tip-lbl">كوبون ممنوح</div></div></div>
        </div>
        ${asgn!=='?'?`<div class="prog-bar"><div class="prog-fill" style="width:${pct}%"></div></div><div class="prog-lbl"><span>${subs}/${asgn} أجاب</span><span>${pct}%</span></div>`:''}
      </div>
      <div class="tcard-foot">
        <div class="tclass-badge"><i class="fas fa-users"></i>${esc(t.class_name||'—')}</div>
        <div class="tact" onclick="event.stopPropagation()">
          <div class="tbtn" onclick="openEdit(${t.id})" title="تعديل"><i class="fas fa-pen"></i></div>
          <div class="tbtn d" onclick="openConf(${t.id})" title="حذف"><i class="fas fa-trash"></i></div>
        </div>
      </div>
    </div>`;
  }).join('');
}
function updateStats() {
  document.getElementById('stTotal').textContent    = tasks.length;
  document.getElementById('stActive').textContent   = tasks.filter(t=>statusOf(t).key==='active').length;
  document.getElementById('stUpcoming').textContent = tasks.filter(t=>statusOf(t).key==='upcoming').length;
  const tc = tasks.reduce((a,t)=>a+(t.submissions||[]).reduce((b,s)=>b+(parseInt(s.coupons_awarded)||0),0),0);
  document.getElementById('stCoupons').textContent  = tc;
}

// ─── Create / Edit ──────────────────────────────────────────────
function openCreate(taskId=null) {
  editId=taskId; qCnt=0;
  resetForm();
  if (taskId) { const t=tasks.find(x=>x.id==taskId); if(t)fillForm(t); document.getElementById('createTitle').textContent='تعديل المهمة'; }
  else { document.getElementById('createTitle').textContent='إنشاء مهمة جديدة'; addQ(); addQ(); }
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
  const s=document.getElementById('fClass');
  if(CFG.activeClass){for(const o of s.options){if(o.value===CFG.activeClass){o.selected=true;break;}}}
  else s.selectedIndex=0;
}
function fillForm(t) {
  document.getElementById('fTitle').value=t.title||'';
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
  const s=document.getElementById('fClass');
  for(const o of s.options){if(o.value===t.class_name){o.selected=true;break;}}
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
  if(mx&&mx.length) mx.forEach(m=>addTier(m.from,m.to,m.val));
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
  if(!document.getElementById('fClass').value){showToast('اختر الفصل','err');return false;}
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
      '<div class="open-q-note" id="opennote_'+id+'" style="display:none"><i class="fas fa-pen-nib"></i>الطالب يكتب إجابة نصية \u2014 تُصحَّح يدوياً بعد التسليم</div>'+
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
    var r = await fetch('https://sunday-school.rf.gd/upload.php', {
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
    <div class="crew"><i class="fas fa-ticket-alt"></i><input type="number" min="0" max="999" value="${coupons}"><span class="crew-l">كوبون</span></div>
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
  const cls=document.getElementById('fClass').value;
  const c=document.getElementById('specList');
  if(!cls){c.innerHTML='<span style="color:var(--t3);">اختر الفصل أولاً</span>';return;}
  c.innerHTML='<span style="color:var(--t3);"><i class="fas fa-spinner fa-spin"></i> جارٍ التحميل…</span>';
  const st=await loadStudents(cls);
  if(!st.length){c.innerHTML='<span style="color:var(--t3);">لا يوجد أطفال</span>';return;}
  c.innerHTML=st.map(s=>`<label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:.78rem;color:var(--t1);">
    <input type="checkbox" name="spec_ids" value="${s.id}" style="accent-color:var(--brand);">${esc(s.name)}</label>`).join('');
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
  const tiers=[];
  document.querySelectorAll('.ctier').forEach(t=>{
    const ns=t.querySelectorAll('input[type=number]');
    tiers.push({from:parseInt(ns[0].value)||0,to:parseInt(ns[1].value)||100,val:parseInt(ns[2].value)||0});
  });
  const specIds=[];
  document.querySelectorAll('[name="spec_ids"]:checked').forEach(c=>specIds.push(parseInt(c.value)));
  const clsOpt=document.getElementById('fClass').selectedOptions[0];
  return {
    status, title:document.getElementById('fTitle').value.trim(), description:document.getElementById('fDesc').value.trim(),
    class_name:document.getElementById('fClass').value, class_id:clsOpt?.dataset.id||0,
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
    if(d.success){ showToast(status==='draft'?'تم حفظ المسودة ✓':'تم نشر المهمة 🎉',status==='draft'?'info':'ok'); closeCreate(); await loadTasks(); }
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
      <button class="btn btn-g" onclick="closeDetail()"><i class="fas fa-times"></i> إغلاق</button>
      ${hasOpenQs?`<button class="btn" onclick="closeDetail();openGradePanel(${t.id})" style="background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;">
        <i class="fas fa-pen-nib"></i> تصحيح${pendingSubs>0?` <span class="pending-badge">${pendingSubs}</span>`:''}</button>`:''}
      <button class="btn btn-p" onclick="closeDetail();openEdit(${t.id})"><i class="fas fa-pen"></i> تعديل</button>`;

    // ── Build who answered / who didn't list ──────────────────────
    // Always ensure class students are loaded
    const answeredIds = subs.map(s=>parseInt(s.student_id));
    const classStudents = await loadStudents(t.class_name || 'كل الفصول');

    const notAnswered = classStudents.filter(s=>!answeredIds.includes(s.id));

    document.getElementById('dBody').innerHTML=`
      <div class="detail-shell">
      <div class="detail-banner">
        <span class="tstatus ${si.cls}">${si.label}</span>
        ${t.time_limit?`<span class="tstatus s-upcoming"><i class="fas fa-stopwatch"></i> ${t.time_limit} دقيقة</span>`:''}
        <span class="tmeta-i"><i class="fas fa-calendar-check"></i>${fmtDate(t.start_date)}</span>
        <span class="tmeta-i"><i class="fas fa-flag-checkered"></i>${parseInt(t.no_deadline||0)?'بدون آخر موعد':fmtDate(t.end_date)}</span>
        ${parseInt(t.shuffle)?'<span class="tmeta-i"><i class="fas fa-random"></i>ترتيب عشوائي</span>':''}
      </div>
      <div class="detail-overview">
        <div class="detail-stat"><div class="detail-stat-label">Ø§Ù„Ø£Ø³Ø¦Ù„Ø©</div><div class="detail-stat-value">${(t.questions||[]).length}</div></div>
        <div class="detail-stat"><div class="detail-stat-label">Ø§Ù„Ø¥Ø¬Ø§Ø¨Ø§Øª</div><div class="detail-stat-value">${subs.length}</div></div>
        <div class="detail-stat"><div class="detail-stat-label">Ø§Ù„Ø¯Ø±Ø¬Ø© Ø§Ù„ÙƒÙ„ÙŠØ©</div><div class="detail-stat-value">${t.total_degree}</div></div>
        <div class="detail-stat"><div class="detail-stat-label">Ø§Ù„ÙƒÙˆØ¨ÙˆÙ†Ø§Øª</div><div class="detail-stat-value">${tc}</div></div>
      </div>
      <div class="coupon-chips" style="margin-bottom:18px;">
        ${matrix.map(m=>`<span style="display:flex;align-items:center;gap:4px;padding:4px 10px;background:var(--cou-bg);border:1px solid #c4b5fd;border-radius:var(--r-full);font-size:.72rem;font-weight:600;color:var(--cou);"><i class="fas fa-ticket-alt"></i>${m.from}%–${m.to}% = ${m.val} كوبون</span>`).join('')}
      </div>

      <!-- Who answered / who didn't -->
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:20px;">
        <div style="background:var(--ok-bg);border:1px solid #6ee7b7;border-radius:var(--r-md);padding:11px 14px;">
          <div style="font-size:.75rem;font-weight:700;color:var(--ok);margin-bottom:8px;display:flex;align-items:center;gap:5px;">
            <i class="fas fa-check-circle"></i> أجابوا (${subs.length})
          </div>
          ${subs.length?subs.map(s=>`
            <div style="display:flex;align-items:center;gap:7px;padding:5px 0;border-bottom:1px solid rgba(0,0,0,.06);">
              <span style="font-size:.78rem;font-weight:700;color:var(--t1);flex:1;">${esc(s.student_name||'—')}</span>
              <span style="font-size:.68rem;background:var(--brand-bg);color:var(--brand);border-radius:var(--r-full);padding:1px 7px;font-weight:700;">${s.score||0}/${t.total_degree}</span>
              <button onclick="event.stopPropagation();showDeleteSubConfirm(${s.id},'${esc(s.student_name||'')}',${s.coupons_awarded||0},${t.id})"
                style="background:none;border:1px solid #fca5a5;color:var(--err);border-radius:5px;padding:2px 6px;cursor:pointer;font-size:.63rem;"><i class="fas fa-trash"></i></button>
            </div>`).join('')
          :'<div style="font-size:.75rem;color:var(--t3);">لا أحد بعد</div>'}
        </div>
        <div style="background:var(--err-bg);border:1px solid #fca5a5;border-radius:var(--r-md);padding:11px 14px;">
          <div style="font-size:.75rem;font-weight:700;color:var(--err);margin-bottom:8px;display:flex;align-items:center;gap:5px;">
            <i class="fas fa-clock"></i> لم يجيبوا (${notAnswered.length})
          </div>
          ${notAnswered.length?notAnswered.map(s=>`
            <div style="padding:5px 0;border-bottom:1px solid rgba(0,0,0,.06);">
              <span style="font-size:.78rem;font-weight:700;color:var(--t1);">${esc(s.name)}</span>
            </div>`).join('')
          :`<div style="font-size:.75rem;color:var(--t3);">${classStudents.length?'الجميع أجاب 🎉':'بيانات الفصل غير محملة'}</div>`}
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
          <table class="sub-tbl"><thead><tr><th>${PEOPLE}</th><th>الدرجة</th><th>النسبة</th><th>الكوبونات</th><th>وقت الإرسال</th><th style="width:38px;"></th></tr></thead>
          <tbody>${subs.map(s=>`<tr>
            <td>${esc(s.student_name||'—')}</td>
            <td>${s.score||0}/${t.total_degree}</td>
            <td>${t.total_degree?Math.round((parseInt(s.score)||0)/t.total_degree*100):0}%</td>
            <td><span style="color:var(--cou);font-weight:700;">${s.coupons_awarded||0} <i class="fas fa-ticket-alt"></i></span></td>
            <td style="color:var(--t3);font-size:.7rem;">${fmtDate(s.submitted_at)}</td>
            <td>
    <div style="display:flex;gap:4px;">
      <button onclick="event.stopPropagation();viewAnswers(${t.id}, ${s.student_id})" style="background:none;border:1px solid #cffafe;color:#0891b2;border-radius:6px;padding:3px 8px;cursor:pointer;font-size:.68rem;font-weight:700;"><i class="fas fa-eye"></i></button>
      <button onclick="event.stopPropagation();showDeleteSubConfirm(${s.id},'${esc(s.student_name||'')}',${s.coupons_awarded||0},${t.id})" style="background:none;border:1px solid #fca5a5;color:var(--err);border-radius:6px;padding:3px 8px;cursor:pointer;font-size:.68rem;font-weight:700;"><i class="fas fa-trash"></i></button>
    </div>
  </td>
          </tr>`).join('')}</tbody>
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
    msg.innerHTML = `حذف إجابة <strong>${esc(studentName||'هذا الطالب')}</strong>؟`
      + (coupons>0?`<br><span style="color:var(--err);font-size:.8rem;">سيتم خصم ${coupons} كوبون من كوبونات المهام فقط.</span>`:'');
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
      if(d.coupons_reversed > 0) msg += ` — تم خصم ${d.coupons_reversed} كوبون من كوبونات المهام`;
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
function openConf(id){
  delId=id;
  const t=tasks.find(x=>x.id==id);
  document.getElementById('confSub').textContent=t?`سيتم حذف "${t.title}" بشكل نهائي وعكس جميع الكوبونات.`:'لا يمكن التراجع.';
  openOv('confOv');
}
async function doDelete(){
  try{
    const d=await api('deleteTask',{task_id:delId});
    if(d.success){
      const msg = d.coupons_reversed>0?`تم حذف المهمة وعكس ${d.coupons_reversed} كوبون`:'تم حذف المهمة';
      showToast(msg,'info');closeConf();await loadTasks();
    }
    else showToast(d.message||'فشل الحذف','err');
  }catch(e){showToast('خطأ في الاتصال','err');}
}

// ─── Open question grading — FULL EXAM VIEW ───────────────────────
let gradeTaskId = null;
let gradeSubs = [];
let gradeTaskData = null;

async function openGradePanel(taskId) {
  gradeTaskId = taskId;
  document.getElementById('gradePanel').classList.add('open');
  document.body.style.overflow = 'hidden';
  document.getElementById('gradePanelBody').innerHTML = '<div style="text-align:center;padding:40px;color:var(--t3);"><i class="fas fa-spinner fa-spin" style="font-size:2rem;"></i></div>';
  try {
    // Load task detail for questions
    const td = await api('getTaskDetail', {task_id: taskId});
    gradeTaskData = td.success ? td.task : null;

    // Load all submissions (not just ungraded) for full view
    const d = await api('getPendingOpenSubmissions', {task_id: taskId});
    if(!d.success){showToast(d.message||'فشل','err');return;}
    gradeSubs = d.submissions || [];
    renderGradePanel();
  } catch(e){showToast('خطأ في الاتصال','err');}
}

function renderGradePanel() {
  const el = document.getElementById('gradePanelBody');
  document.getElementById('gradePanelSub').textContent = `${gradeSubs.length} طالب ينتظر التصحيح`;
  if(!gradeSubs.length){
    el.innerHTML = '<div style="text-align:center;padding:40px;color:var(--t3);"><i class="fas fa-check-circle" style="font-size:2rem;color:var(--ok);display:block;margin-bottom:10px;"></i>تم تصحيح جميع الإجابات!</div>';
    return;
  }

  const allQuestions = gradeTaskData ? (gradeTaskData.questions||[]) : [];
  const matrix = gradeTaskData ? JSON.parse(gradeTaskData.coupon_matrix||'[]') : [];
  const totalDeg = gradeTaskData ? (gradeTaskData.total_degree||0) : 0;

  el.innerHTML = gradeSubs.map((sub, si) => {
    const answers = JSON.parse(sub.answers||'{}');
    const openQs = sub.open_questions || [];
    const openQIds = openQs.map(q=>String(q.id));

    // Build full exam view
    const qRows = allQuestions.map((q, qi) => {
      const qtype = q.question_type || 'mcq';
      const qId = String(q.id);
      const imgH = q.image_url ? `<div style="margin:4px 0 8px;border-radius:8px;overflow:hidden;border:1px solid var(--bdr);"><img src="${q.image_url}" alt="" style="width:100%;max-height:160px;object-fit:contain;display:block;background:#f8fafc;"></div>` : '';

      if(qtype === 'open'){
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
        </div>`;
      }

      // MCQ / TF — show with correct/wrong highlighting
      const given = answers[qId] !== undefined ? parseInt(answers[qId]) : null;
      const correct = q.correct_index !== null ? parseInt(q.correct_index) : null;
      const isCorrect = given !== null && correct !== null && given === correct;
      const isWrong = given !== null && correct !== null && given !== correct;

      const statusDot = given === null
        ? `<span style="color:var(--t3);font-size:.68rem;">لم يجب</span>`
        : isCorrect
          ? `<span style="background:var(--ok-bg);color:var(--ok);border-radius:var(--r-full);padding:1px 8px;font-size:.68rem;font-weight:700;"><i class="fas fa-check"></i> صح</span>`
          : `<span style="background:var(--err-bg);color:var(--err);border-radius:var(--r-full);padding:1px 8px;font-size:.68rem;font-weight:700;"><i class="fas fa-times"></i> خطأ</span>`;

      if(qtype === 'tf') {
        const opts = ['صحيح','خطأ'];
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
        </div>`;
      }

      // MCQ
      const opts = typeof q.options==='string' ? JSON.parse(q.options) : (q.options||[]);
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
      </div>`;
    }).join('');

    return `<div class="grade-sub-card" id="gradecard_${sub.id}">
      <div class="grade-sub-name">
        <i class="fas fa-user-circle" style="color:var(--brand);"></i>
        ${esc(sub.student_name||'—')}
        <span style="font-size:.72rem;color:var(--t3);font-weight:500;margin-right:5px;">${esc(sub.task_title||'')}</span>
        <span style="margin-right:auto;font-size:.72rem;" id="scoreDisp_${sub.id}"></span>
      </div>
      ${qRows}
      <button class="grade-save-btn" onclick="submitGrade(${sub.id}, ${si})">
        <i class="fas fa-check"></i> حفظ التصحيح وتحديث الكوبونات
      </button>
    </div>`;
  }).join('');
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
  const btn = document.querySelector(`#gradecard_${subId} .grade-save-btn`);
  const orig = btn.innerHTML; btn.disabled=true; btn.innerHTML='<i class="fas fa-spinner fa-spin"></i> جارٍ الحفظ…';
  try {
    const d = await api('gradeOpenAnswer', {submission_id: subId, scores: JSON.stringify(scores)});
    if(d.success){
      // coupon_diff = change in task_coupons (positive = added, negative = removed, 0 = no change)
      let couponMsg = '';
      if(d.coupon_diff > 0)       couponMsg = ` — تمت إضافة ${d.coupon_diff} كوبون لكوبونات المهام`;
      else if(d.coupon_diff < 0)  couponMsg = ` — تم خصم ${Math.abs(d.coupon_diff)} كوبون من كوبونات المهام`;
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
  ov.innerHTML = `
    <div class="modal" style="max-width:760px;margin:24px auto;">
      <div class="mhdr" style="background:linear-gradient(135deg,var(--brand),var(--brand-d));padding:16px 20px;border-radius:var(--r-xl) var(--r-xl) 0 0;display:flex;align-items:center;justify-content:space-between;border-bottom:none;">
        <div style="color:#fff;font-weight:800;font-size:1rem;">مراجعة الإجابات</div>
        <button onclick="this.closest('.overlay').remove(); document.documentElement.classList.remove('ov-open');" style="background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.2);color:#fff;width:34px;height:34px;border-radius:10px;cursor:pointer;"><i class="fas fa-times"></i></button>
      </div>
      <div class="mbody" style="padding:0;">${html}</div>
    </div>
  `;
  document.body.appendChild(ov);
  ov.onclick = (e) => { if(e.target === ov) { ov.remove(); document.documentElement.classList.remove('ov-open'); } };
  document.documentElement.classList.add('ov-open');
}

function viewAnswers(taskId, studentId) {
  const t = tasks.find(x=>x.id==taskId);
  if(!t) return;
  const sub = t.submissions.find(x=>x.student_id==studentId);
  if(!sub) return;

  const ans = typeof sub.answers === 'string' ? JSON.parse(sub.answers) : (sub.answers || {});
  let html = `<div class="ans-shell">
    <div class="ans-head">
      <div class="ans-avatar"><i class="fas fa-user-check"></i></div>
      <div style="flex:1;">
        <div class="ans-name">${esc(sub.student_name)}</div>
        <div style="font-size:.78rem;color:var(--t3);margin-top:2px;">لقد حصل على ${sub.score} من ${t.total_degree} درجة</div>
      </div>
    </div>`;

  if(!t.questions || t.questions.length === 0) {
    html += `<div style="text-align:center;padding:40px;color:var(--t4);">لا توجد أسئلة لهذه المهمة.</div>`;
  } else {
    t.questions.forEach((q, i) => {
      const qType = q.question_type || 'mcq';
      const given = ans[q.id];
      const correctIdx = q.correct_index !== null ? parseInt(q.correct_index) : null;
      
      html += `<div class="ans-question">`;
      html += `<div class="ans-qhead">
        <div class="ans-qnum">${i+1}</div>
        <div class="ans-qtext">${esc(q.question_text)}</div>
      </div>`;
      
      if(qType === 'open') {
        html += `<div class="ans-open">
          <div style="font-size:.7rem;color:var(--t3);margin-bottom:6px;font-weight:700;">إجابة الطالب:</div>
          <div style="color:var(--t2);font-size:.9rem;white-space:pre-wrap;line-height:1.6;">${esc(given || '— لم يُجب على هذا السؤال —')}</div>
        </div>`;
      } else {
        const opts = typeof q.options === 'string' ? JSON.parse(q.options) : (q.options || []);
        if(qType === 'tf') { opts[0] = 'صح'; opts[1] = 'خطأ'; }
        
        html += `<div style="display:flex;flex-direction:column;gap:8px;">`;
        opts.forEach((o, j) => {
          const isCorr = j === correctIdx;
          const isSel = given !== undefined && parseInt(given) === j;
          
          let borderColor = 'var(--bdr)';
          let bgColor = '#fff';
          let textColor = 'var(--t2)';
          let icon = '';

          if(isCorr) {
            borderColor = 'var(--ok)';
            bgColor = 'var(--ok-bg)';
            textColor = 'var(--ok)';
            icon = isSel ? '<i class="fas fa-check-circle" style="margin-right:auto;"></i>' : '<i class="fas fa-check" style="margin-right:auto;opacity:.4;"></i>';
          } else if(isSel) {
            borderColor = 'var(--err)';
            bgColor = 'var(--err-bg)';
            textColor = 'var(--err)';
            icon = '<i class="fas fa-times-circle" style="margin-right:auto;"></i>';
          }

          html += `<div class="ans-choice ${isCorr?'correct':isSel?'wrong':''}" style="border-color:${borderColor};background:${bgColor};color:${textColor};${isSel?'font-weight:700;':''}">
            <span class="ans-choice-letter">${LETTERS[j]}</span>
            <span>${esc(o)}</span>
            ${icon}
          </div>`;
        });
        html += `</div>`;
      }
      html += `</div>`;
    });
  }
  html += `</div>`;
  
  openModal(html);
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
    note.textContent = 'لن يكون هناك آخر موعد، وستظل المهمة متاحة بعد البداية.';
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
function viewAnswers(taskId, studentId) {
  const t = tasks.find(x=>x.id==taskId);
  if(!t) return;
  const sub = t.submissions.find(x=>x.student_id==studentId);
  if(!sub) return;

  const ans = typeof sub.answers === 'string' ? JSON.parse(sub.answers) : (sub.answers || {});
  let html = `<div style="padding:15px;max-height:80vh;overflow-y:auto;">
    <h3 style="margin-bottom:15px;color:var(--t1);">إجابات الطالب: ${esc(sub.student_name)}</h3>`;

  if(!t.questions || t.questions.length === 0) {
    html += `<div style="color:var(--t3);">لا توجد أسئلة.</div>`;
  } else {
    t.questions.forEach((q, i) => {
      const qType = q.question_type || 'mcq';
      const given = ans[q.id];
      const correctIdx = q.correct_index !== null ? parseInt(q.correct_index) : null;
      
      html += `<div style="margin-bottom:15px;padding:10px;border:1px solid var(--bdr);border-radius:var(--r-md);background:var(--bg2);">`;
      html += `<div style="font-weight:700;margin-bottom:8px;color:var(--t1);">${i+1}. ${esc(q.question_text)}</div>`;
      
      if(qType === 'open') {
        html += `<div style="color:var(--t2);font-size:.85rem;"><strong>الإجابة:</strong> <span style="${!given?'color:var(--err)':''}"><pre style="white-space:pre-wrap;margin:5px 0;">${esc(given || 'لم يُجب')}</pre></span></div>`;
      } else {
        const opts = typeof q.options === 'string' ? JSON.parse(q.options) : (q.options || []);
        if(qType === 'tf') { opts[0] = 'صح'; opts.push('خطأ'); }
        
        opts.forEach((o, j) => {
          let style = "padding:5px 10px;border-radius:5px;margin-bottom:5px;font-size:.85rem;border:1px solid var(--bdr);";
          let icon = "";
          if(j === correctIdx) {
            style += "background:var(--ok-bg);border-color:#6ee7b7;color:#065f46;";
            icon = `<i class="fas fa-check" style="float:left;margin-top:3px;"></i>`;
          }
          if(given !== undefined && parseInt(given) === j) {
            if(j !== correctIdx) {
              style += "background:var(--err-bg);border-color:#fca5a5;color:var(--err);";
              icon = `<i class="fas fa-times" style="float:left;margin-top:3px;"></i>`;
            }
            style += "font-weight:700;box-shadow:inset 0 0 0 1px currentColor;";
          }
          html += `<div style="${style}">${icon} ${esc(o)}</div>`;
        });
      }
      html += `</div>`;
    });
  }
  html += `</div>`;
  
  openModal(html);
}
</script>

<!-- ══ GRADING PANEL ══ -->
<div class="grade-panel" id="gradePanel">
  <div class="grade-sheet">
    <div class="grade-sheet-hdr">
      <div style="width:38px;height:38px;border-radius:10px;background:var(--warn-bg);color:var(--warn);display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="fas fa-pen-nib"></i></div>
      <div style="flex:1;">
        <div style="font-size:1rem;font-weight:800;color:var(--t1);">تصحيح الإجابات المفتوحة</div>
        <div style="font-size:.72rem;color:var(--t3);" id="gradePanelSub">أدخل الدرجة لكل إجابة</div>
      </div>
      <button onclick="closeGradePanel()" style="width:32px;height:32px;border-radius:8px;border:1px solid var(--bdr);background:var(--bg2);cursor:pointer;color:var(--t2);"><i class="fas fa-times"></i></button>
    </div>
    <div class="grade-sheet-body" id="gradePanelBody">
      <div style="text-align:center;padding:40px;color:var(--t3);"><i class="fas fa-spinner fa-spin" style="font-size:2rem;"></i></div>
    </div>
  </div>
</div>
</body>
</html>
