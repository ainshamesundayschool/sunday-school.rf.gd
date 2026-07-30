import obspython as obs
import urllib.request
import json
import os

# GLOBAL VARIABLES
file_or_url = "live.txt"
source_name = ""
interval = 30
last_text = ""

def fetch_live_text():
    global file_or_url, source_name, last_text
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
        update_obs_text_source(source_name, text)

def update_obs_text_source(name, text):
    source = obs.obs_get_source_by_name(name)
    if source is not None:
        settings = obs.obs_data_create()
        obs.obs_data_set_string(settings, "text", text)
        obs.obs_source_update(source, settings)
        obs.obs_data_release(settings)
        obs.obs_source_release(source)

def script_update(settings):
    global file_or_url, source_name, interval
    file_or_url = obs.obs_data_get_string(settings, "file_or_url")
    source_name = obs.obs_data_get_string(settings, "source_name")
    interval = obs.obs_data_get_int(settings, "interval")
    
    obs.timer_remove(fetch_live_text)
    if interval > 0:
        obs.timer_add(fetch_live_text, interval)

def script_description():
    return "<b>Sunday School Taranim Instant Text Sync (0ms Local File & Network)</b><br>ربط فوري لنصوص الترانيم والكلمات مباشرة إلى مصدر النص في OBS Studio.<br><br>1. أدخل مسار ملف live.txt المحلي لسرعة 0ms أو رابط الأونلاين.<br>2. اختر اسم مصدر النص (Text Source) في OBS."

def script_defaults(settings):
    obs.obs_data_set_default_string(settings, "file_or_url", "live.txt")
    obs.obs_data_set_default_int(settings, "interval", 30)

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
    return props
