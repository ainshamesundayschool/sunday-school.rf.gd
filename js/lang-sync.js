/**
 * lang-sync.js
 * Multi-language synchronization and translation engine for Sunday School system.
 * Supports URL param overriding, persistent cookies/localStorage, RTL/LTR toggles,
 * automatic class insertions, and visual translations.
 */

(function () {
    'use strict';

    // ─── TRANSLATION DICTIONARY ───
    const translations = {
        ar: {
            // Shared & Common
            "loading": "جارٍ التحميل…",
            "error_conn": "خطأ في الاتصال",
            "ok": "تم ✓",
            "success": "تم بنجاح ✓",
            "error": "خطأ",
            "cancel": "إلغاء",
            "close": "إغلاق",
            "back": "رجوع",
            "save": "حفظ",
            "confirm": "تأكيد",
            "present": "حضر",
            "absent": "غاب",
            "rate": "نسبة",
            "coupon": "كوبون",

            // Landing Page
            "app_title": "نظام مدارس الأحد",
            "app_subtitle": "نظام متكامل لإدارة وتتبع نشاط الأطفال والاناكل في مدارس الأحد",
            "stat_kids": "طفل مسجل",
            "stat_churches": "كنيسة تستخدم النظام",
            "stat_coupons": "كوبون تم توزيعه",
            "stat_attendance": "معدل حضور",
            "features_header": "مميزات النظام",
            "feat_att_title": "إدارة الحضور الذكية",
            "feat_att_desc": "تسجيل حضور الأطفال بسهولة مع تحديث فوري",
            "feat_cou_title": "نظام الكوبونات",
            "feat_cou_desc": "تتبع وتوزيع الكوبونات للمكافأة والتشجيع",
            "feat_rep_title": "تقارير وإحصائيات",
            "feat_rep_desc": "تقارير مفصلة عن الحضور والأداء",
            "start_now": "ابدأ الآن",
            "user_selection_header": "اختر نوع المستخدم للبدء",
            "user_kid_title": "طفل",
            "user_kid_desc": "مشاهدة ملفي الشخصي، الكوبونات، سجل الحضور والمهام",
            "user_kid_action": "الدخول كـ طفل",
            "user_uncle_title": "انكل / طنط",
            "user_uncle_desc": "إدارة الأطفال، تسجيل الحضور، تعديل المعلومات، وإرسال الإعلانات والمهام",
            "user_uncle_action": "الدخول كـ انكل / طنط",
            "footer_brand": "نظام مدارس الأحد 2026",
            "footer_verse": "“مُكْثِرِينَ فِي عَمَلِ ٱلرَّبِّ كُلَّ حِينٍ، عَالِمِينَ أَنَّ تَعَبَكُمْ لَيْسَ بَاطِلًا فِي ٱلرَّبِّ.”",
            "footer_ref": "كُورِنْثُوسَ ٱلأُولَى ١٥:‏٥٨",
            "footer_help": "المساعدة",
            "footer_contact": "تواصل معنا",
            "footer_about": "حول التطبيق",

            // Kids Profile Page
            "kid_portal_title": "بوابة الطفل",
            "hero_class_lbl": "الفصل",
            "btn_edit_profile": "تعديل البيانات",
            "btn_change_pass": "تغيير المرور",
            "btn_logout": "تسجيل الخروج",
            "stats_coupons_total": "الكوبونات",
            "stats_coupons_attendance": "حضور",
            "stats_coupons_tasks": "مهام",
            "stats_coupons_commitment": "التزام",
            
            // Kids Profile Sections
            "sec_info_title": "بياناتي الشخصية",
            "sec_att_title": "سجل الحضور",
            "sec_att_sub": "آخر 12 أسبوع",
            "sec_tasks_title": "المهام والأنشطة",
            "sec_trips_title": "الرحلات والمؤتمرات",
            "sec_ann_title": "لوحة الإعلانات",
            "sec_uncles_title": "خدام الفصل",
            "sec_search_title": "ابحث عن أصدقائك",

            // Kid Info Fields
            "info_name": "الاسم",
            "info_phone": "التليفون",
            "info_address": "العنوان",
            "info_birthday": "عيد الميلاد",
            "info_email": "البريد الإلكتروني",
            "info_church": "الكنيسة",

            // Kid Attendance
            "att_present": "حضور ✓",
            "att_absent": "غياب ✗",
            "att_unrecorded": "غير مسجّل",
            "att_btn_report": "بلّغ عن خطأ",
            "att_btn_view_all": "عرض السجل بالكامل",
            
            // Kid Tasks
            "task_upcoming": "قادم",
            "task_open": "مفتوح",
            "task_expired": "منتهي",
            "task_done": "مكتمل",
            "task_no_deadline": "بدون آخر موعد",
            "task_max_coupons": "حتى {num} كوبون",
            "task_upon_answering": "عند الإجابة",
            "task_score": "درجة",
            "task_time": "دقيقة",
            
            // Kid Trips
            "trip_free": "مجانية",
            "trip_price": "{num} ج.م",
            "trip_remaining": "متبقي {num} ج.م",
            "trip_paid": "المدفوع {num} ج.م",
            "trip_registered": "أنت مسجّل في هذه الرحلة",
            "trip_not_registered": "اسمك غير موجود في القائمة",
            "trip_contact_uncle": "تواصل مع المدرّس",
            "trip_planned": "مخطط",
            "trip_active": "نشط",
            "trip_completed": "مكتمل",
            "trip_cancelled": "ملغي",
            "trip_spots": "أقصى {num}",
            "trip_points": "{num} نقاط",

            // Kid Announcements
            "ann_badge_latest": "أحدث إعلان",
            "ann_badge_link": "رابط سريع",
            "ann_badge_message": "رسالة",
            "ann_btn_open_link": "فتح الرابط",
            "ann_btn_open_ann": "فتح الإعلان",
            "ann_from_church": "من الكنيسة أو خدام الفصل",
            "ann_empty_title": "لا توجد إعلانات حالياً",
            "ann_empty_desc": "أول ما ينزل إعلان جديد من الخدام أو الكنيسة هتلاقيه هنا.",

            // Modals & Popups
            "modal_edit_title": "تعديل البيانات الشخصية",
            "modal_pass_title": "تغيير كلمة المرور",
            "modal_photo_title": "تعديل الصورة الشخصية",
            "field_current_pass": "كلمة المرور الحالية",
            "field_new_pass": "كلمة المرور الجديدة",
            "field_confirm_pass": "تأكيد كلمة المرور",
            "toast_pass_mismatch": "كلمة المرور غير متطابقة",
            "toast_pass_length": "٦ أحرف على الأقل",
            "toast_name_required": "أدخل الاسم",
            "btn_save_changes": "حفظ التغييرات",
            "btn_change_now": "تغيير الآن",
            "btn_choose_photo": "اختر صورة",
            "btn_crop_photo": "قص الصورة",
            "btn_upload_photo": "رفع الصورة",
            "photo_placeholder": "اسحب صورتك هنا أو اضغط للاختيار",
            
            // Search Friends
            "search_placeholder": "اكتب اسماً للبحث عن أصدقائك…",
            "search_empty": "لم يُعثر على نتائج",
            "search_error": "خطأ في البحث",
            "search_you": "(أنت)",
            
            // Switch Account
            "switch_acc_title": "تبديل الحساب",
            "switch_acc_btn": "تبديل الحساب",
            "switch_acc_confirm": "تبديل الآن",
            
            // Friend Banner
            "friend_banner_title": "ملف {name}",
            "btn_my_profile": "العودة لملفي الشخصي"
        },
        en: {
            // Shared & Common
            "loading": "Loading…",
            "error_conn": "Connection Error",
            "ok": "Done ✓",
            "success": "Success ✓",
            "error": "Error",
            "cancel": "Cancel",
            "close": "Close",
            "back": "Back",
            "save": "Save",
            "confirm": "Confirm",
            "present": "Present",
            "absent": "Absent",
            "rate": "Rate",
            "coupon": "Coupon",

            // Landing Page
            "app_title": "Sunday School System",
            "app_subtitle": "An integrated system to manage and track kids and servants activity in Sunday School",
            "stat_kids": "Registered Kid",
            "stat_churches": "Active Church",
            "stat_coupons": "Coupon Distributed",
            "stat_attendance": "Attendance Rate",
            "features_header": "System Features",
            "feat_att_title": "Smart Attendance",
            "feat_att_desc": "Easily register children's attendance with real-time updates",
            "feat_cou_title": "Coupon Rewards",
            "feat_cou_desc": "Track and distribute coupons for rewards and motivation",
            "feat_rep_title": "Reports & Stats",
            "feat_rep_desc": "Detailed analytics on attendance and kids performance",
            "start_now": "Get Started",
            "user_selection_header": "Choose User Type to Start",
            "user_kid_title": "Kid",
            "user_kid_desc": "View my profile, coupons, tasks and attendance records",
            "user_kid_action": "Enter as a Kid",
            "user_uncle_title": "Servant / Teacher",
            "user_uncle_desc": "Manage kids, record attendance, edit profiles, send announcements & tasks",
            "user_uncle_action": "Enter as a Servant",
            "footer_brand": "Sunday School System 2026",
            "footer_verse": "“Therefore, my beloved brethren, be steadfast, immovable, always abounding in the work of the Lord, knowing that your labor is not in vain in the Lord.”",
            "footer_ref": "1 Corinthians 15:58",
            "footer_help": "Help Center",
            "footer_contact": "Contact Us",
            "footer_about": "About App",

            // Kids Profile Page
            "kid_portal_title": "Kid Portal",
            "hero_class_lbl": "Class",
            "btn_edit_profile": "Edit Profile",
            "btn_change_pass": "Change Password",
            "btn_logout": "Logout",
            "stats_coupons_total": "Coupons",
            "stats_coupons_attendance": "Attendance",
            "stats_coupons_tasks": "Tasks",
            "stats_coupons_commitment": "Commitment",

            // Kids Profile Sections
            "sec_info_title": "My Personal Info",
            "sec_att_title": "Attendance History",
            "sec_att_sub": "Last 12 Weeks",
            "sec_tasks_title": "Tasks & Activities",
            "sec_trips_title": "Trips & Conferences",
            "sec_ann_title": "Announcement Board",
            "sec_uncles_title": "Class Servants",
            "sec_search_title": "Find My Friends",

            // Kid Info Fields
            "info_name": "Name",
            "info_phone": "Phone",
            "info_address": "Address",
            "info_birthday": "Birthday",
            "info_email": "Email",
            "info_church": "Church",

            // Kid Attendance
            "att_present": "Present ✓",
            "att_absent": "Absent ✗",
            "att_unrecorded": "Unrecorded",
            "att_btn_report": "Report Error",
            "att_btn_view_all": "View Full History",

            // Kid Tasks
            "task_upcoming": "Upcoming",
            "task_open": "Open",
            "task_expired": "Expired",
            "task_done": "Completed",
            "task_no_deadline": "No Deadline",
            "task_max_coupons": "Up to {num} Coupons",
            "task_upon_answering": "When answered",
            "task_score": "Score",
            "task_time": "Min",

            // Kid Trips
            "trip_free": "Free",
            "trip_price": "{num} EGP",
            "trip_remaining": "{num} EGP Left",
            "trip_paid": "Paid {num} EGP",
            "trip_registered": "Registered in this trip ✓",
            "trip_not_registered": "Name is not on the list",
            "trip_contact_uncle": "Contact Teacher",
            "trip_planned": "Planned",
            "trip_active": "Active",
            "trip_completed": "Completed",
            "trip_cancelled": "Cancelled",
            "trip_spots": "Max {num}",
            "trip_points": "{num} Points",

            // Kid Announcements
            "ann_badge_latest": "Latest",
            "ann_badge_link": "Link",
            "ann_badge_message": "Message",
            "ann_btn_open_link": "Open Link",
            "ann_btn_open_ann": "Open Post",
            "ann_from_church": "From Church or Class Teachers",
            "ann_empty_title": "No Announcements",
            "ann_empty_desc": "New announcements from teachers or church will appear here.",

            // Modals & Popups
            "modal_edit_title": "Edit Personal Info",
            "modal_pass_title": "Change Password",
            "modal_photo_title": "Edit Profile Photo",
            "field_current_pass": "Current Password",
            "field_new_pass": "New Password",
            "field_confirm_pass": "Confirm Password",
            "toast_pass_mismatch": "Passwords do not match",
            "toast_pass_length": "At least 6 characters",
            "toast_name_required": "Name is required",
            "btn_save_changes": "Save Changes",
            "btn_change_now": "Change Now",
            "btn_choose_photo": "Select Image",
            "btn_crop_photo": "Crop Image",
            "btn_upload_photo": "Upload Image",
            "photo_placeholder": "Drag your image here or tap to browse",

            // Search Friends
            "search_placeholder": "Type a name to find friends…",
            "search_empty": "No results found",
            "search_error": "Search failed",
            "search_you": "(You)",

            // Switch Account
            "switch_acc_title": "Switch Account",
            "switch_acc_btn": "Switch Account",
            "switch_acc_confirm": "Switch Now",

            // Friend Banner
            "friend_banner_title": "{name}'s Profile",
            "btn_my_profile": "Back to My Profile"
        }
    };

    // ─── COOKIE HELPERS ───
    function setCookie(name, value, days) {
        let expires = "";
        if (days) {
            const date = new Date();
            date.setTime(date.getTime() + (days * 24 * 60 * 60 * 1000));
            expires = "; expires=" + date.toUTCString();
        }
        document.cookie = name + "=" + (value || "") + expires + "; path=/; SameSite=Lax";
    }

    // Export getCookie globally as well
    function getCookie(name) {
        const nameEQ = name + "=";
        const ca = document.cookie.split(';');
        for (let i = 0; i < ca.length; i++) {
            let c = ca[i];
            while (c.charAt(0) === ' ') c = c.substring(1, c.length);
            if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
        }
        return null;
    }

    // ─── INITIAL DETECTION ───
    let lang = 'ar'; // Default

    // 1. Detect from URL query param
    const urlParams = new URLSearchParams(window.location.search);
    const urlLang = urlParams.get('lang');
    if (urlLang === 'en' || urlLang === 'ar') {
        lang = urlLang;
        setCookie('lang', lang, 365);
        localStorage.setItem('lang', lang);
    } else {
        // 2. Detect from Cookie or LocalStorage
        const savedLang = getCookie('lang') || localStorage.getItem('lang');
        if (savedLang === 'en' || savedLang === 'ar') {
            lang = savedLang;
        }
    }

    // Apply document structures
    document.documentElement.lang = lang;
    document.documentElement.dir = lang === 'en' ? 'ltr' : 'rtl';
    
    // Add layout support CSS directly to the head to avoid flash of RTL/LTR
    const styleEl = document.createElement('style');
    styleEl.innerHTML = `
        /* Sync alignment and layout globally for English */
        [lang="en"] body {
            direction: ltr !important;
            text-align: left !important;
        }
        [lang="en"] .copyright, [lang="en"] .footer-copy {
            text-align: left !important;
        }
        [lang="en"] .user-card:hover .user-action {
            transform: translateX(8px) !important;
        }
        [lang="en"] .fa-arrow-left {
            transform: scaleX(-1);
        }
        [lang="en"] .ua-more, [lang="en"] .ka-more {
            margin-right: unset !important;
            margin-left: -10px !important;
        }
        [lang="en"] .my-trip-box {
            text-align: left !important;
        }
        [lang="en"] .trip-status-overlay {
            right: unset !important;
            left: 12px !important;
        }
        [lang="en"] .trip-price-overlay {
            left: unset !important;
            right: 12px !important;
            flex-direction: row-reverse !important;
        }
        [lang="en"] .att-hist-row button {
            margin-right: unset !important;
            margin-left: auto !important;
        }
        
        /* SLEEK FLOATING WIDGET */
        .lang-switcher-widget {
            position: fixed;
            bottom: 24px;
            right: 24px;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(255, 255, 255, 0.7);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            padding: 6px 14px;
            border-radius: 9999px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: 'Baloo Bhaijaan 2', 'Cairo', system-ui, sans-serif;
            font-weight: 700;
            font-size: 0.85rem;
            color: #1e293b;
            gap: 8px;
            user-select: none;
            -webkit-user-select: none;
        }
        [lang="en"] .lang-switcher-widget {
            right: unset !important;
            left: 24px !important;
        }
        .lang-switcher-widget:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 12px 40px rgba(99, 102, 241, 0.2);
            border-color: rgba(99, 102, 241, 0.3);
            color: #6366f1;
        }
        .lang-switcher-widget i {
            font-size: 0.95rem;
            color: #6366f1;
        }
        
        /* Dynamic font adjustments */
        [lang="en"] .title, [lang="en"] body, [lang="en"] .btn, [lang="en"] .user-title, [lang="en"] .feature-title {
            font-family: 'Baloo Bhaijaan 2', 'Cairo', system-ui, sans-serif !important;
        }
    `;
    document.head.appendChild(styleEl);

    // ─── TRANSLATION PARSER ENGINE ───
    function translateDOM() {
        const dict = translations[lang];
        if (!dict) return;

        // Translate textContent and innerHTML based on data-i18n
        document.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.getAttribute('data-i18n');
            let translation = dict[key];

            if (translation) {
                // If there are dynamic tokens in translation like {num}
                const tokenVal = el.getAttribute('data-i18n-num');
                if (tokenVal !== null) {
                    translation = translation.replace('{num}', tokenVal);
                }
                const nameVal = el.getAttribute('data-i18n-name');
                if (nameVal !== null) {
                    translation = translation.replace('{name}', nameVal);
                }

                // Inject safely
                if (el.tagName === 'INPUT' || el.tagName === 'TEXTAREA') {
                    el.value = translation;
                } else {
                    el.textContent = translation;
                }
            }
        });

        // Translate placeholders
        document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
            const key = el.getAttribute('data-i18n-placeholder');
            const translation = dict[key];
            if (translation) {
                el.setAttribute('placeholder', translation);
            }
        });

        // Toggle layout classes on body
        document.body.classList.toggle('lang-en', lang === 'en');
        document.body.classList.toggle('lang-ar', lang === 'ar');
    }

    // ─── SWITCHER FLOATING BUTTON ───
    function createSwitcherWidget() {
        if (document.getElementById('langSwitcherBtn')) return;

        const btn = document.createElement('div');
        btn.id = 'langSwitcherBtn';
        btn.className = 'lang-switcher-widget';
        
        // Render globe icon + switch label
        const targetLang = lang === 'en' ? 'ar' : 'en';
        const displayLabel = lang === 'en' ? 'العربية' : 'English';
        btn.innerHTML = `<i class="fas fa-globe"></i> <span>${displayLabel}</span>`;
        
        btn.addEventListener('click', function () {
            // Toggle language
            const newLang = targetLang;
            setCookie('lang', newLang, 365);
            localStorage.setItem('lang', newLang);
            
            // Push query param to URL to ensure seamless synchronization
            const url = new URL(window.location.href);
            url.searchParams.set('lang', newLang);
            window.location.href = url.toString();
        });

        document.body.appendChild(btn);
    }

    // Run DOM translate and load widget on content loaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            translateDOM();
            createSwitcherWidget();
        });
    } else {
        translateDOM();
        createSwitcherWidget();
    }

    // Export to global window namespace
    window.SundaySchoolI18n = {
        lang: lang,
        dict: translations[lang],
        translate: function(key, replacements = {}) {
            let val = translations[lang][key] || key;
            for (const [k, v] of Object.entries(replacements)) {
                val = val.replace(`{${k}}`, v);
            }
            return val;
        },
        translateDOM: translateDOM
    };

})();
