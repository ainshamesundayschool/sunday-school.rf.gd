import obspython as obs
import urllib.request
import json
import os

# GLOBAL VARIABLES
file_or_url = "live.txt"
source_name = ""
interval = 30
fix_arabic = True
last_text = ""

# PURE PYTHON SAFE ARABIC GLYPH MAP & RESHAPER FOR OBS FREETYPE2 / GDI+
ARABIC_GLYPHS = {
    '\u0621': ('\u0621', '\u0621', '\u0621', '\u0621'),
    '\u0622': ('\u0622', '\uFE82', '\uFE82', '\u0622'),
    '\u0623': ('\u0623', '\uFE84', '\uFE84', '\u0623'),
    '\u0624': ('\u0624', '\uFE86', '\uFE86', '\u0624'),
    '\u0625': ('\u0625', '\uFE88', '\uFE88', '\u0625'),
    '\u0626': ('\u0626', '\uFE8A', '\uFE8C', '\uFE8B'),
    '\u0627': ('\u0627', '\uFE8E', '\uFE8E', '\u0627'),
    '\u0628': ('\uFE8F', '\uFE90', '\uFE92', '\uFE91'),
    '\u0629': ('\uFE93', '\uFE94', '\uFE94', '\uFE93'),
    '\u062A': ('\uFE95', '\uFE96', '\uFE98', '\uFE97'),
    '\u062B': ('\uFE99', '\uFE9A', '\uFE9C', '\uFE9B'),
    '\u062C': ('\uFE9D', '\uFE9E', '\uFEA0', '\uFE9F'),
    '\u062D': ('\uFEA1', '\uFEA2', '\uFEA4', '\uFEA3'),
    '\u062E': ('\uFEA5', '\uFEA6', '\uFEA8', '\uFEA7'),
    '\u062F': ('\uFEA9', '\uFEAA', '\uFEAA', '\uFEA9'),
    '\u0630': ('\uFEAB', '\uFEAC', '\uFEAC', '\uFEAB'),
    '\u0631': ('\uFEAD', '\uFEAE', '\uFEAE', '\uFEAD'),
    '\u0632': ('\uFEAF', '\uFEB0', '\uFEB0', '\uFEAF'),
    '\u0633': ('\uFEB1', '\uFEB2', '\uFEB4', '\uFEB3'),
    '\u0634': ('\uFEB5', '\uFEB6', '\uFEB8', '\uFEB7'),
    '\u0635': ('\uFEB9', '\uFEBA', '\uFEBC', '\uFEBB'),
    '\u0636': ('\uFEBD', '\uFEBE', '\uFEC0', '\uFEBF'),
    '\u0637': ('\uFEC1', '\uFEC2', '\uFEC4', '\uFEC3'),
    '\u0638': ('\uFEC5', '\uFEC6', '\uFEC8', '\uFEC7'),
    '\u0639': ('\uFEC9', '\uFECA', '\uFECC', '\uFECB'),
    '\u063A': ('\uFECD', '\uFECE', '\uFED0', '\uFECF'),
    '\u0641': ('\uFED1', '\uFED2', '\uFED4', '\uFED3'),
    '\u0642': ('\uFED5', '\uFED6', '\uFED8', '\uFED7'),
    '\u0643': ('\uFED9', '\uFEDA', '\uFEDC', '\uFEDB'),
    '\u0644': ('\uFEDD', '\uFEDE', '\uFEE0', '\uFEDF'),
    '\u0645': ('\uFEE1', '\uFEE2', '\uFEE4', '\uFEE3'),
    '\u0646': ('\uFEE5', '\uFEE6', '\uFEE8', '\uFEE7'),
    '\u0647': ('\uFEE9', '\uFEEA', '\uFEEC', '\uFEEB'),
    '\u0648': ('\uFEED', '\uFEEE', '\uFEEE', '\uFEED'),
    '\u0649': ('\uFEEF', '\uFEF0', '\uFEF0', '\uFEEF'),
    '\u064A': ('\uFEF1', '\uFEF2', '\uFEF4', '\uFEF3'),
}

NON_CONNECTING_ARABIC = {'\u0621', '\u0622', '\u0623', '\u0625', '\u0627', '\u062F', '\u0630', '\u0631', '\u0632', '\u0648', '\u0649'}

def is_arabic_char(ch):
    return '\u0600' <= ch <= '\u06FF' or '\u0750' <= ch <= '\u077F' or '\uFB50' <= ch <= '\uFDFF' or '\uFE70' <= ch <= '\uFEFF'

def reshape_arabic_word(word):
    if not word: return ""
    res = []
    n = len(word)
    for i in range(n):
        c = word[i]
        if c not in ARABIC_GLYPHS:
            res.append(c)
            continue

        prev_c = word[i-1] if i > 0 else None
        next_c = word[i+1] if i < n - 1 else None

        prev_connects = prev_c is not None and prev_c in ARABIC_GLYPHS and prev_c not in NON_CONNECTING_ARABIC
        next_connects = next_c is not None and next_c in ARABIC_GLYPHS

        # LAM-ALEF LIGATURES
        if c == '\u0644' and next_c in ['\u0627', '\u0622', '\u0623', '\u0625']:
            continue
        if i > 0 and word[i-1] == '\u0644' and c in ['\u0627', '\u0622', '\u0623', '\u0625']:
            if c == '\u0627': res.append('\uFEFC' if prev_connects else '\uFEFB')
            elif c == '\u0622': res.append('\uFEF6' if prev_connects else '\uFEF5')
            elif c == '\u0623': res.append('\uFEF8' if prev_connects else '\uFEF7')
            elif c == '\u0625': res.append('\uFEFA' if prev_connects else '\uFEF9')
            continue

        forms = ARABIC_GLYPHS[c]
        if prev_connects and next_connects:
            res.append(forms[2])
        elif prev_connects:
            res.append(forms[1])
        elif next_connects:
            res.append(forms[3])
        else:
            res.append(forms[0])

    return "".join(res)

def format_arabic_text_for_obs(text):
    if not text: return ""
    lines = text.split('\n')
    reshaped_lines = []

    for line in lines:
        words = line.split(' ')
        reshaped_words = []
        for word in words:
            if any(is_arabic_char(ch) for ch in word):
                r_word = reshape_arabic_word(word)
                reshaped_words.append(r_word[::-1])
            else:
                reshaped_words.append(word)

        reshaped_words.reverse()
        reshaped_lines.append(" ".join(reshaped_words))

    # Multi-line visual center padding for OBS text engine
    if len(reshaped_lines) > 1:
        max_len = max(len(l) for l in reshaped_lines)
        padded_lines = []
        for l in reshaped_lines:
            pad = max_len - len(l)
            left = pad // 2
            right = pad - left
            padded_lines.append((' ' * left) + l + (' ' * right))
        return "\n".join(padded_lines)

    return "\n".join(reshaped_lines)

def fetch_live_text():
    global file_or_url, source_name, last_text, fix_arabic
    if not source_name or not file_or_url:
        return

    text = None
    target = file_or_url.strip()

    # 1. READ FROM LOCAL FILE (0.00ms INSTANT DISK READ)
    if not target.startswith("http://") and not target.startswith("https://"):
        if os.path.exists(target):
            try:
                with open(target, "r", encoding="utf-8") as f:
                    content = f.read().strip()
                    if content.startswith("{"):
                        data = json.loads(content)
                        text = "" if data.get("isBlank", False) else data.get("text", "")
                    else:
                        text = content
            except Exception:
                pass
    else:
        # 2. READ FROM HTTP NETWORK URL
        try:
            req = urllib.request.Request(target, headers={'User-Agent': 'SundaySchoolOBS/1.0'})
            with urllib.request.urlopen(req, timeout=0.3) as response:
                if response.status == 200:
                    data = json.loads(response.read().decode('utf-8'))
                    text = "" if data.get("isBlank", False) else data.get("text", "")
        except Exception:
            pass

    if text is not None and text != last_text:
        last_text = text
        final_text = format_arabic_text_for_obs(text) if fix_arabic else text
        update_obs_text_source(source_name, final_text)

def update_obs_text_source(name, text):
    source = obs.obs_get_source_by_name(name)
    if source is not None:
        settings = obs.obs_data_create()
        obs.obs_data_set_string(settings, "text", text)
        obs.obs_data_set_string(settings, "align", "center")
        obs.obs_data_set_string(settings, "valign", "center")
        obs.obs_source_update(source, settings)
        obs.obs_data_release(settings)
        obs.obs_source_release(source)

def script_update(settings):
    global file_or_url, source_name, interval, fix_arabic
    file_or_url = obs.obs_data_get_string(settings, "file_or_url")
    source_name = obs.obs_data_get_string(settings, "source_name")
    interval = obs.obs_data_get_int(settings, "interval")
    fix_arabic = obs.obs_data_get_bool(settings, "fix_arabic")
    
    obs.timer_remove(fetch_live_text)
    if interval > 0:
        obs.timer_add(fetch_live_text, interval)

def script_description():
    return "<b>Sunday School Taranim Instant Text Sync + Safe Arabic Shaping</b><br>ربط فوري وإصلاح آمن لتشبيك الكلمات العربية بدون مربعات لمصادر النص في OBS Studio.<br><br>1. أدخل مسار ملف live.txt أو رابط الأونلاين.<br>2. حدد اسم مصدر النص (Text Source) في OBS.<br>3. استخدم خط مثل Geeza Pro, Cairo, Baghdad, Arial للحصول على أفضل ريندر."

def script_defaults(settings):
    obs.obs_data_set_default_string(settings, "file_or_url", "live.txt")
    obs.obs_data_set_default_int(settings, "interval", 30)
    obs.obs_data_set_default_bool(settings, "fix_arabic", True)

def script_properties():
    props = obs.obs_properties_create()
    obs.obs_properties_add_text(props, "file_or_url", "مسار ملف live.txt المحلي أو الرابط الأونلاين", obs.OBS_TEXT_DEFAULT)
    
    p_sources = obs.obs_properties_add_list(props, "source_name", "مصدر النص في OBS (Text Source)", obs.OBS_COMBO_TYPE_LIST, obs.OBS_COMBO_FORMAT_STRING)
    sources = obs.obs_enum_sources()
    if sources is not None:
        for source in sources:
            source_id = obs.obs_source_get_unversioned_id(source)
            if source_id in ["text_gdiplus", "text_ft2", "text_gdiplus_v2", "text_ft2_source_v2"]:
                name = obs.obs_source_get_name(source)
                obs.obs_property_list_add_string(p_sources, name, name)
        obs.source_list_release(sources)
        
    obs.obs_properties_add_int(props, "interval", "معدل التحديث بالميللي ثانية (Interval ms)", 16, 1000, 16)
    obs.obs_properties_add_bool(props, "fix_arabic", "إصلاح وترافق حروف اللغة العربية تلقائياً (Fix Arabic BiDi)")
    return props
