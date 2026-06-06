(function () {
  var DEFAULT_ORIGIN = 'https://sunday-school.online';
  var SQUARE_IMAGE = DEFAULT_ORIGIN + '/imgs/Sunday-School-Og.png';
  var WIDE_IMAGE = DEFAULT_ORIGIN + '/imgs/Sunday%20School%20App.png';

  function getMeta(selector) {
    return document.head.querySelector(selector);
  }

  function setMeta(attr, name, content) {
    var selector = attr === 'property' ? 'meta[property="' + name + '"]' : 'meta[name="' + name + '"]';
    var tag = getMeta(selector);
    if (!tag) {
      tag = document.createElement('meta');
      tag.setAttribute(attr, name);
      document.head.appendChild(tag);
    }
    tag.setAttribute('content', content);
  }

  function absoluteUrl(path) {
    if (/^https?:\/\//i.test(path)) return path;
    return DEFAULT_ORIGIN + (path.charAt(0) === '/' ? path : '/' + path);
  }

  function readChurchType() {
    try {
      return localStorage.getItem('churchType') || localStorage.getItem('church_type') || 'kids';
    } catch (e) {
      return 'kids';
    }
  }

  function pageLabel(path) {
    if (path.indexOf('/login') !== -1) return 'تسجيل الدخول';
    if (path.indexOf('/registration') !== -1 || path.indexOf('church-register') !== -1) return 'التسجيل';
    if (path.indexOf('/dashboard/tasks') !== -1) return 'المهام';
    if (path.indexOf('/dashboard/withdraw') !== -1) return 'السحب';
    if (path.indexOf('/dashboard') !== -1 || path.indexOf('/uncle/church') !== -1) return 'لوحة التحكم';
    if (path.indexOf('/trip') !== -1 || path.indexOf('/trips') !== -1) return 'الرحلات';
    if (path.indexOf('/leaderboard') !== -1) return 'لوحة المتفوقين';
    if (path.indexOf('/help') !== -1) return 'المساعدة';
    if (path.indexOf('/about') !== -1) return 'حول النظام';
    if (path.indexOf('/kids/profile') !== -1) return 'بوابة المستخدم';
    if (path.indexOf('/kids') !== -1) return 'بوابة المستخدم';
    return '';
  }

  function applyOgMeta() {
    var type = readChurchType();
    var isYouth = type === 'youth';
    var baseTitle = isYouth ? 'Sunday School' : 'نظام مدارس الأحد';
    var description = isYouth
      ? 'منصة متكاملة لإدارة Sunday School — الحضور، الكوبونات، الإعلانات والمزيد'
      : 'منصة متكاملة لإدارة مدارس الأحد — الحضور، الكوبونات، الرحلات / المؤتمرات والمزيد';
    var label = pageLabel(location.pathname);
    var fullTitle = label ? label + ' - ' + baseTitle : baseTitle;
    var url = absoluteUrl(location.pathname.replace(/\/index\.(html|php)$/i, '/') + location.search);

    document.title = fullTitle;
    setMeta('name', 'description', description);
    setMeta('property', 'og:type', 'website');
    setMeta('property', 'og:site_name', baseTitle);
    setMeta('property', 'og:title', fullTitle);
    setMeta('property', 'og:description', description);
    setMeta('property', 'og:url', url);
    setMeta('property', 'og:image', SQUARE_IMAGE);
    setMeta('property', 'og:image:width', '1000');
    setMeta('property', 'og:image:height', '1000');
    setMeta('property', 'og:image:type', 'image/png');
    setMeta('property', 'og:image:alt', baseTitle);
    setMeta('property', 'og:locale', 'ar_AR');
    setMeta('name', 'twitter:card', 'summary_large_image');
    setMeta('name', 'twitter:title', fullTitle);
    setMeta('name', 'twitter:description', description);
    setMeta('name', 'twitter:image', WIDE_IMAGE);
  }

  applyOgMeta();
  window.applySundaySchoolOgMeta = applyOgMeta;
})();
