import obspython as obs
import urllib.request
import json

# GLOBAL VARIABLES
url = "http://sunday-school.online/taranim/api.php?action=live"
source_name = ""
interval = 100
last_text = ""

def fetch_live_text():
    global url, source_name, last_text
    if not source_name:
        return

    try:
        req = urllib.request.Request(url, headers={'User-Agent': 'SundaySchoolOBS/1.0'})
        with urllib.request.urlopen(req, timeout=1) as response:
            if response.status == 200:
                data = json.loads(response.read().decode('utf-8'))
                text = data.get('text', '') if not data.get('isBlank', False) else ''
                
                if text != last_text:
                    last_text = text
                    update_obs_text_source(source_name, text)
    except Exception as e:
        pass

def update_obs_text_source(name, text):
    source = obs.obs_get_source_by_name(name)
    if source is not None:
        settings = obs.obs_data_create()
        obs.obs_data_set_string(settings, "text", text)
        obs.obs_source_update(source, settings)
        obs.obs_data_release(settings)
        obs.obs_source_release(source)

def script_update(settings):
    global url, source_name, interval
    url = obs.obs_data_get_string(settings, "url")
    source_name = obs.obs_data_get_string(settings, "source_name")
    interval = obs.obs_data_get_int(settings, "interval")
    
    obs.timer_remove(fetch_live_text)
    if interval > 0:
        obs.timer_add(fetch_live_text, interval)

def script_description():
    return "<b>Sunday School Taranim Live Text Sync</b><br>ربط نصوص الترانيم والكلمات مباشرة من الموقع إلى مصدر النص (Text Source) في OBS Studio.<br><br>1. حدد اسم مصدر النص (Text Source) في OBS.<br>2. اضغط تفعيل ومتابعة."

def script_defaults(settings):
    obs.obs_data_set_default_string(settings, "url", "http://sunday-school.online/taranim/api.php?action=live")
    obs.obs_data_set_default_int(settings, "interval", 100)

def script_properties():
    props = obs.obs_properties_create()
    obs.obs_properties_add_text(props, "url", "رابط API المباشر (Taranim Live URL)", obs.OBS_TEXT_DEFAULT)
    
    p_sources = obs.obs_properties_add_list(props, "source_name", "مصدر النص في OBS (Text Source)", obs.OBS_COMBO_TYPE_LIST, obs.OBS_COMBO_FORMAT_STRING)
    sources = obs.obs_enum_sources()
    if sources is not None:
        for source in sources:
            source_id = obs.obs_source_get_unversioned_id(source)
            if source_id in ["text_gdiplus", "text_ft2", "text_gdiplus_v2", "text_ft2_source_v2"]:
                name = obs.obs_source_get_name(source)
                obs.obs_property_list_add_string(p_sources, name, name)
        obs.source_list_release(sources)
        
    obs.obs_properties_add_int(props, "interval", "معدل التحديث بالميللي ثانية (Interval ms)", 50, 2000, 50)
    return props
