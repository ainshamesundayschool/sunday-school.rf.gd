// ==========================================================================
// SUNDAY SCHOOL TARANIM - MULTI-SCREEN & CONTROL DASHBOARD ENGINE
// ==========================================================================

const FRANCO_MAPPINGS = [
  ["shabahak", "شبهك"], ["yasou3", "يسوع"], ["kirolos", "كيرلس"],
  ["3ajel", "عجل"], ["rabby", "ربي"], ["7abib", "حبيب"],
  ["allah", "الله"], ["alla", "الله"], ["3ashan", "عشان"], ["3shan", "عشان"],
  ["alhan", "ألحان"], ["tarnima", "ترنيمة"], ["taranim", "ترانيم"],
  ["3a", "عا"], ["3i", "عي"], ["3u", "عو"], ["3", "ع"],
  ["7a", "حا"], ["7i", "حي"], ["7u", "حو"], ["7", "ح"],
  ["5", "خ"], ["2", "أ"], ["kh", "خ"], ["sh", "ش"],
  ["th", "ث"], ["gh", "غ"], ["ou", "و"], ["oo", "و"],
  ["ee", "ي"], ["y", "ي"], ["g", "ج"], ["k", "ك"],
  ["c", "ك"], ["q", "ق"], ["z", "ز"], ["s", "س"],
  ["t", "ت"], ["d", "د"], ["r", "ر"], ["f", "ف"],
  ["l", "ل"], ["m", "م"], ["n", "ن"], ["h", "ه"],
  ["w", "و"], ["b", "ب"], ["p", "ب"], ["v", "ف"],
  ["i", "ي"], ["e", "ي"], ["o", "و"], ["u", "و"],
  ["a", "ا"]
];

function normalizeArabic(text) {
  if (!text) return "";
  return String(text)
    .replace(/[أإآٱ]/g, "ا")
    .replace(/[ىئ]/g, "ي")
    .replace(/ة/g, "ه")
    .replace(/ؤ/g, "و")
    .replace(/[\u064B-\u0652]/g, "")
    .toLowerCase()
    .trim();
}

function francoToArabic(text) {
  if (!text) return "";
  let s = text.toLowerCase().trim();
  if (!/[a-z0-9]/.test(s)) return "";

  FRANCO_MAPPINGS.forEach(([f, a]) => {
    s = s.split(f).join(a);
  });
  return s;
}

function getApiUrl() {
  const loc = window.location;
  let path = loc.pathname;
  if (path.includes('/public/')) {
    return path.replace(/\/public\/.*$/, '/api.php');
  }
  if (path.endsWith('.html') || path.endsWith('.php')) {
    return path.substring(0, path.lastIndexOf('/') + 1) + 'api.php';
  }
  if (!path.endsWith('/')) {
    path += '/';
  }
  return path + 'api.php';
}

function getObsUrl() {
  const loc = window.location;
  let path = loc.pathname;
  if (path.endsWith('.html') || path.endsWith('.php')) {
    path = path.substring(0, path.lastIndexOf('/') + 1);
  } else if (!path.endsWith('/')) {
    path += '/';
  }
  return `${loc.origin}${path}obs.html`;
}

function copyToClipboard(text) {
  if (navigator.clipboard && navigator.clipboard.writeText) {
    return navigator.clipboard.writeText(text);
  }
  const textArea = document.createElement('textarea');
  textArea.value = text;
  textArea.style.position = 'fixed';
  textArea.style.opacity = '0';
  document.body.appendChild(textArea);
  textArea.focus();
  textArea.select();
  try {
    document.execCommand('copy');
  } catch (err) {}
  document.body.removeChild(textArea);
  return Promise.resolve();
}

function levenshteinDistance(a, b) {
  if (a.length === 0) return b.length;
  if (b.length === 0) return a.length;

  const matrix = [];
  for (let i = 0; i <= b.length; i++) matrix[i] = [i];
  for (let j = 0; j <= a.length; j++) matrix[0][j] = j;

  for (let i = 1; i <= b.length; i++) {
    for (let j = 1; j <= a.length; j++) {
      if (b.charAt(i - 1) === a.charAt(j - 1)) {
        matrix[i][j] = matrix[i - 1][j - 1];
      } else {
        matrix[i][j] = Math.min(
          matrix[i - 1][j - 1] + 1,
          matrix[i][j - 1] + 1,
          matrix[i - 1][j] + 1
        );
      }
    }
  }
  return matrix[b.length][a.length];
}

function correctWithArabicDictionary(draftArabic, dictionary) {
  if (!draftArabic || !dictionary || dictionary.length === 0) return draftArabic;

  const words = draftArabic.split(/\s+/);
  const correctedWords = words.map(w => {
    const normW = normalizeArabic(w);
    if (!normW || normW.length < 2) return w;

    const directMatch = dictionary.find(dictWord => normalizeArabic(dictWord) === normW);
    if (directMatch) return directMatch;

    const maxDist = normW.length <= 4 ? 1 : 2;
    let bestWord = w;
    let minDist = maxDist + 1;

    for (let i = 0; i < Math.min(dictionary.length, 6000); i++) {
      const dictWord = dictionary[i];
      const normDict = normalizeArabic(dictWord);

      if (Math.abs(normDict.length - normW.length) > maxDist) continue;

      const dist = levenshteinDistance(normW, normDict);
      if (dist < minDist) {
        minDist = dist;
        bestWord = dictWord;
        if (minDist === 0) break;
      }
    }

    return bestWord;
  });

  return correctedWords.join(' ');
}

function getMatchScore(song, query) {
  if (!song || !query) return 0;
  const title = song.title || '';
  const notes = song.notes || '';

  const qNorm = normalizeArabic(query);
  const qRaw = query.trim().toLowerCase();
  const qFranco = francoToArabic(query);

  const tNorm = normalizeArabic(title);
  const tRaw = title.toLowerCase();
  const nNorm = normalizeArabic(notes);

  if (tRaw === qRaw || tNorm === qNorm) return 100;
  if (tRaw.startsWith(qRaw) || tNorm.startsWith(qNorm)) return 85;
  if (tRaw.includes(qRaw) || tNorm.includes(qNorm)) return 70;

  if (qFranco && (tNorm === qFranco || tNorm.startsWith(qFranco))) return 80;
  if (qFranco && tNorm.includes(qFranco)) return 65;

  if (nNorm.includes(qNorm)) return 50;
  if (qFranco && nNorm.includes(qFranco)) return 40;

  return 10;
}

function showToast(message) {
  let toast = document.getElementById('app-toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'app-toast';
    toast.className = 'app-toast hidden';
    document.body.appendChild(toast);
  }
  toast.textContent = message;
  toast.classList.remove('hidden');
  toast.classList.add('show');

  setTimeout(() => {
    toast.classList.remove('show');
    toast.classList.add('hidden');
  }, 2400);
}

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('./sw.js').catch(() => {});
  });
}

// SHA256 HELPER FOR OBS WEBSOCKET V5 AUTHENTICATION
async function sha256Base64(str) {
  const encoder = new TextEncoder();
  const data = encoder.encode(str);
  const hashBuffer = await window.crypto.subtle.digest('SHA-256', data);
  const hashArray = Array.from(new Uint8Array(hashBuffer));
  const binary = hashArray.map(b => String.fromCharCode(b)).join('');
  return window.btoa(binary);
}

// OBS WEBSOCKET V5 PROTOCOL CLIENT
class OBSWSClient {
  constructor(options = {}) {
    this.ws = null;
    this.isConnected = false;
    this.ip = options.ip || 'localhost';
    this.port = options.port || 4455;
    this.password = options.password || '';
    this.onStatusChange = options.onStatusChange || (() => {});
    this.onScenesUpdated = options.onScenesUpdated || (() => {});
    this.onTransitionsUpdated = options.onTransitionsUpdated || (() => {});
    this.requestIdCounter = 1;
    this.pendingRequests = new Map();
  }

  connect(ip, port, password) {
    if (ip) this.ip = String(ip).trim();
    if (port) this.port = port;
    if (password !== undefined) this.password = String(password);

    this.disconnect();

    let rawHost = this.ip;
    let scheme = 'ws://';

    if (rawHost.startsWith('wss://')) {
      scheme = 'wss://';
      rawHost = rawHost.replace(/^wss:\/\//i, '');
    } else if (rawHost.startsWith('ws://')) {
      scheme = 'ws://';
      rawHost = rawHost.replace(/^ws:\/\//i, '');
    }

    rawHost = rawHost.replace(/\/+$/, '');

    let finalHost = rawHost;
    if (!rawHost.includes(':')) {
      finalHost = `${rawHost}:${this.port}`;
    }

    const wsUrl = `${scheme}${finalHost}`;
    try {
      this.ws = new WebSocket(wsUrl);
    } catch (e) {
      this.onStatusChange(false, 'فشل الاتصال: عنوان غير صالح');
      return;
    }

    this.onStatusChange(false, `جاري الاتصال بـ ${finalHost}...`);

    this.ws.onopen = () => {};

    this.ws.onmessage = async (event) => {
      try {
        const msg = JSON.parse(event.data);
        await this.handleMessage(msg);
      } catch (err) {}
    };

    this.ws.onerror = () => {
      let errMsg = 'تعذر الاتصال بـ OBS.';
      if (window.location.protocol === 'https:' && scheme === 'ws://') {
        errMsg = 'ملاحظة: قد يمنع المتصفح الاتصال بـ ws:// على صفحة HTTPS. استخدم wss:// أو افتح الموقع عبر HTTP.';
      }
      this.onStatusChange(false, errMsg);
    };

    this.ws.onclose = (e) => {
      this.isConnected = false;
      let reason = 'غير متصل';
      if (e && (e.code === 4009 || e.code === 4008)) {
        reason = 'كلمة السر غير صحيحة';
      }
      this.onStatusChange(false, reason);
    };
  }

  disconnect() {
    if (this.ws) {
      try { this.ws.close(); } catch(e) {}
      this.ws = null;
    }
    this.isConnected = false;
    this.onStatusChange(false, 'غير متصل');
  }

  async handleMessage(msg) {
    if (!msg || typeof msg !== 'object') return;

    if (msg.op === 0) {
      const d = msg.d || {};
      let authObj = null;
      if (d.authentication && this.password) {
        try {
          const secret = await sha256Base64(this.password + d.authentication.salt);
          const authString = await sha256Base64(secret + d.authentication.challenge);
          authObj = authString;
        } catch(e) {}
      }

      const identifyPayload = {
        op: 1,
        d: {
          rpcVersion: 1,
          eventSubscriptions: 33
        }
      };
      if (authObj) {
        identifyPayload.d.authentication = authObj;
      }
      this.ws.send(JSON.stringify(identifyPayload));
    } else if (msg.op === 2) {
      this.isConnected = true;
      this.onStatusChange(true, 'متصل بـ OBS Studio');
      Promise.all([this.fetchScenes(), this.fetchTransitions()]).catch(() => {});
    } else if (msg.op === 7) {
      const d = msg.d || {};
      const reqId = d.requestId;
      if (reqId && this.pendingRequests.has(reqId)) {
        const resolve = this.pendingRequests.get(reqId);
        this.pendingRequests.delete(reqId);
        resolve(d.responseData || {});
      }
    }
  }

  sendRequest(requestType, requestData = {}) {
    return new Promise((resolve) => {
      if (!this.ws || this.ws.readyState !== WebSocket.OPEN) {
        resolve(null);
        return;
      }
      const reqId = `req_${this.requestIdCounter++}`;
      this.pendingRequests.set(reqId, resolve);

      const payload = {
        op: 6,
        d: {
          requestType: requestType,
          requestId: reqId,
          requestData: requestData
        }
      };
      this.ws.send(JSON.stringify(payload));
    });
  }

  async fetchScenes() {
    const res = await this.sendRequest('GetSceneList');
    if (res && res.scenes) {
      const scenes = res.scenes.map(s => s.sceneName);
      const current = res.currentProgramSceneName || res.currentPreviewSceneName || '';
      this.onScenesUpdated(scenes, current);
    }
  }

  async fetchTransitions() {
    const res = await this.sendRequest('GetSceneTransitionList');
    if (res && res.transitions) {
      const transitions = res.transitions.map(t => t.transitionName);
      const current = res.currentSceneTransitionName || '';
      this.onTransitionsUpdated(transitions, current);
    }
  }

  setCurrentScene(sceneName) {
    if (!sceneName) return;
    this.sendRequest('SetCurrentProgramScene', { sceneName: sceneName });
  }

  setTransition(transitionName) {
    if (!transitionName) return;
    this.sendRequest('SetCurrentSceneTransition', { transitionName: transitionName });
  }

  setTransitionDuration(durationMs) {
    const ms = parseInt(durationMs);
    if (isNaN(ms)) return;
    this.sendRequest('SetSceneTransitionDuration', { transitionDuration: ms });
  }

  triggerTransition() {
    this.sendRequest('TriggerStudioModeTransition');
  }

  sendLineTextToObsSource(text, sourceName = 'TaranimText') {
    if (!this.isConnected) return;
    this.sendRequest('SetInputSettings', {
      inputName: sourceName,
      inputSettings: { text: text }
    });
  }
}

document.addEventListener('DOMContentLoaded', () => {

  const broadcastChannel = new BroadcastChannel('sunday_school_taranim_obs_channel');
  let screenDetails = null;

  function getLatestSavedPivot() {
    const savedPivotRaw = localStorage.getItem('sunday_school_taranim_drag_pivot');
    if (savedPivotRaw) {
      try {
        const parsed = JSON.parse(savedPivotRaw);
        if (parsed && parsed.xPct !== undefined && parsed.yPct !== undefined) {
          return parsed;
        }
      } catch(e) {}
    }
    return { xPct: 50, yPct: 75 };
  }

  const savedSettingsRaw = localStorage.getItem('sunday_school_taranim_user_settings');
  let savedSettings = {};
  if (savedSettingsRaw) {
    try { savedSettings = JSON.parse(savedSettingsRaw); } catch(e) {}
  }

  const state = {
    allSongs: [],
    arabicDictionary: [],
    sessionRecents: JSON.parse(sessionStorage.getItem('sunday_school_taranim_session_recents') || '[]'),
    activeSong: null,
    presentationLines: [],
    currentLineIndex: 0,
    isBlank: false,

    selectedFont: savedSettings.selectedFont || "'Alexandria', sans-serif",
    fontSize: savedSettings.fontSize || 54,
    chromaKey: savedSettings.chromaKey || "black",
    presentationMode: savedSettings.presentationMode || "oneline",
    francoAutoTranslate: savedSettings.francoAutoTranslate !== undefined ? savedSettings.francoAutoTranslate : true,
    
    styleOptions: savedSettings.styleOptions || {
      textColor: "#ffffff",
      strokeWidth: 0,
      strokeColor: "#000000",
      shadowBlur: 0
    },

    dragPivot: getLatestSavedPivot()
  };

  broadcastChannel.onmessage = (event) => {
    if (event.data && (event.data.type === 'UPDATE_POS' || event.data.pos)) {
      if (event.data.pos) {
        state.dragPivot = event.data.pos;
        localStorage.setItem('sunday_school_taranim_drag_pivot', JSON.stringify(state.dragPivot));
      }
    }
  };

  window.addEventListener('storage', (e) => {
    if (e.key === 'sunday_school_taranim_drag_pivot' && e.newValue) {
      try {
        state.dragPivot = JSON.parse(e.newValue);
      } catch (err) {}
    }
  });

  const els = {
    intelligentSearch: document.getElementById('intelligent-search'),
    clearSearchBtn: document.getElementById('clear-search-btn'),
    searchDropdown: document.getElementById('search-results-dropdown'),
    totalSongsCount: document.getElementById('total-songs-count'),
    francoToggleBtn: document.getElementById('franco-toggle-btn'),
    
    btnMenuStyle: document.getElementById('btn-menu-style'),
    popoverStyle: document.getElementById('popover-style'),
    btnMenuCast: document.getElementById('btn-menu-cast'),
    popoverCast: document.getElementById('popover-cast'),

    btnCreditsInfo: document.getElementById('btn-credits-info'),
    modalCredits: document.getElementById('modal-credits'),
    btnCloseCredits: document.getElementById('btn-close-credits'),

    connectedScreensSelect: document.getElementById('connected-screens-select'),
    fontSelect: document.getElementById('font-family-select'),
    customFontWrapper: document.getElementById('custom-font-wrapper'),
    customFontInput: document.getElementById('custom-font-input'),
    chromaSelect: document.getElementById('chroma-select'),
    presModeSelect: document.getElementById('pres-mode-select'),

    btnOpenTvWindow: document.getElementById('btn-open-tv-window'),
    btnCopyObsUrl: document.getElementById('btn-copy-obs-url'),

    obsFontSizeRange: document.getElementById('obs-font-size-range'),
    fontSizeValBadge: document.getElementById('font-size-val-badge'),
    obsTextColor: document.getElementById('obs-text-color'),
    obsStrokeRange: document.getElementById('obs-stroke-range'),
    obsStrokeColor: document.getElementById('obs-stroke-color'),
    obsShadowRange: document.getElementById('obs-shadow-range'),

    presentationLinesContainer: document.getElementById('presentation-lines-container'),
    recentSessionContainer: document.getElementById('recent-session-container'),
    currentLineCounter: document.getElementById('current-line-counter'),
    recentCount: document.getElementById('recent-count'),

    btnPrevLine: document.getElementById('btn-prev-line'),
    btnNextLine: document.getElementById('btn-next-line'),
    btnToggleBlank: document.getElementById('btn-toggle-blank'),

    obsOverlay: document.getElementById('obs-presentation-overlay'),
    obsLowerThirdBox: document.getElementById('obs-lower-third-box'),
    obsLineText: document.getElementById('obs-line-text'),
    snapGuideH: document.getElementById('snap-guide-h'),
    snapGuideV: document.getElementById('snap-guide-v'),

    btnMenuObsWs: document.getElementById('btn-menu-obs-ws'),
    popoverObsWs: document.getElementById('popover-obs-ws'),
    obsWsStatusBadge: document.getElementById('obs-ws-status-badge'),
    obsConnectionStatusText: document.getElementById('obs-connection-status-text'),
    btnScanQr: document.getElementById('btn-scan-qr'),
    obsWsIp: document.getElementById('obs-ws-ip'),
    obsWsPort: document.getElementById('obs-ws-port'),
    obsWsPassword: document.getElementById('obs-ws-password'),
    btnConnectObsWs: document.getElementById('btn-connect-obs-ws'),
    btnDisconnectObsWs: document.getElementById('btn-disconnect-obs-ws'),
    obsSceneSelect: document.getElementById('obs-scene-select'),
    obsTransitionSelect: document.getElementById('obs-transition-select'),
    obsTransitionDurationRange: document.getElementById('obs-transition-duration-range'),
    obsTransitionDurationBadge: document.getElementById('obs-transition-duration-badge'),
    btnTriggerObsTransition: document.getElementById('btn-trigger-obs-transition'),
    obsQrModal: document.getElementById('obs-qr-modal'),
    btnCloseQrModal: document.getElementById('btn-close-qr-modal'),
    qrScanResultMsg: document.getElementById('qr-scan-result-msg'),
    obsCompactStrip: document.getElementById('obs-compact-strip'),
    obsHttpsWarning: document.getElementById('obs-https-warning')
  };

  function closeAllPopovers(exceptPopover = null) {
    if (els.popoverStyle && els.popoverStyle !== exceptPopover) {
      els.popoverStyle.classList.add('hidden');
    }
    if (els.popoverCast && els.popoverCast !== exceptPopover) {
      els.popoverCast.classList.add('hidden');
    }
    if (els.popoverObsWs && els.popoverObsWs !== exceptPopover) {
      els.popoverObsWs.classList.add('hidden');
    }
  }

  init();

  function init() {
    applyInitialUIState();
    bindEvents();
    makeDraggableCenterPivot();
    detectConnectedScreens();
    loadInitialData();
    renderRecentSession();
  }

  function saveUserSettings() {
    const settings = {
      selectedFont: state.selectedFont,
      fontSize: state.fontSize,
      chromaKey: state.chromaKey,
      presentationMode: state.presentationMode,
      francoAutoTranslate: state.francoAutoTranslate,
      styleOptions: state.styleOptions
    };
    localStorage.setItem('sunday_school_taranim_user_settings', JSON.stringify(settings));
  }

  function applyInitialUIState() {
    if (els.fontSelect) els.fontSelect.value = state.selectedFont;
    if (els.obsFontSizeRange) els.obsFontSizeRange.value = state.fontSize;
    if (els.fontSizeValBadge) els.fontSizeValBadge.textContent = `${state.fontSize}px`;
    if (els.chromaSelect) els.chromaSelect.value = state.chromaKey;
    if (els.presModeSelect) els.presModeSelect.value = state.presentationMode;
    if (els.francoToggleBtn) els.francoToggleBtn.checked = state.francoAutoTranslate;

    if (els.obsTextColor) els.obsTextColor.value = state.styleOptions.textColor;
    if (els.obsStrokeRange) els.obsStrokeRange.value = state.styleOptions.strokeWidth;
    if (els.obsStrokeColor) els.obsStrokeColor.value = state.styleOptions.strokeColor;
    if (els.obsShadowRange) els.obsShadowRange.value = state.styleOptions.shadowBlur;

    if (els.obsOverlay) els.obsOverlay.setAttribute('data-chroma', state.chromaKey);
  }

  function bindEvents() {
    if (els.btnCreditsInfo && els.modalCredits && els.btnCloseCredits) {
      els.btnCreditsInfo.addEventListener('click', () => {
        els.modalCredits.classList.remove('hidden');
      });

      els.btnCloseCredits.addEventListener('click', () => {
        els.modalCredits.classList.add('hidden');
      });

      els.modalCredits.addEventListener('click', (e) => {
        if (e.target === els.modalCredits) {
          els.modalCredits.classList.add('hidden');
        }
      });
    }

    window.addEventListener('online', () => {
      loadInitialData();
    });

    els.btnMenuStyle.addEventListener('click', (e) => {
      e.stopPropagation();
      const willShow = els.popoverStyle.classList.contains('hidden');
      closeAllPopovers(els.popoverStyle);
      if (willShow) {
        els.popoverStyle.classList.remove('hidden');
      } else {
        els.popoverStyle.classList.add('hidden');
      }
    });

    els.btnMenuCast.addEventListener('click', async (e) => {
      e.stopPropagation();
      const willShow = els.popoverCast.classList.contains('hidden');
      closeAllPopovers(els.popoverCast);
      if (willShow) {
        els.popoverCast.classList.remove('hidden');
        await detectConnectedScreens();
      } else {
        els.popoverCast.classList.add('hidden');
      }
    });

    document.addEventListener('click', (e) => {
      if (els.popoverStyle && !els.popoverStyle.contains(e.target) && e.target !== els.btnMenuStyle && !els.btnMenuStyle.contains(e.target)) {
        els.popoverStyle.classList.add('hidden');
      }
      if (els.popoverCast && !els.popoverCast.contains(e.target) && e.target !== els.btnMenuCast && !els.btnMenuCast.contains(e.target)) {
        els.popoverCast.classList.add('hidden');
      }
      if (els.popoverObsWs && !els.popoverObsWs.contains(e.target) && e.target !== els.btnMenuObsWs && !els.btnMenuObsWs.contains(e.target)) {
        els.popoverObsWs.classList.add('hidden');
      }
      if (els.searchDropdown && !els.searchDropdown.contains(e.target) && e.target !== els.intelligentSearch) {
        els.searchDropdown.classList.add('hidden');
      }
    });

    document.addEventListener('fullscreenchange', () => {
      if (!document.fullscreenElement && els.obsOverlay) {
        els.obsOverlay.classList.add('hidden');
      }
    });

    window.addEventListener('resize', () => {
      syncLiveState();
    });

    els.francoToggleBtn.addEventListener('change', (e) => {
      state.francoAutoTranslate = e.target.checked;
      saveUserSettings();
      if (els.intelligentSearch.value.trim()) {
        performIntelligentSearch(els.intelligentSearch.value);
      }
    });

    els.fontSelect.addEventListener('change', (e) => {
      const val = e.target.value;
      if (val === 'custom') {
        els.customFontWrapper.classList.remove('hidden');
        applyFont(els.customFontInput.value || 'Tahoma');
      } else {
        els.customFontWrapper.classList.add('hidden');
        applyFont(val);
      }
    });

    els.customFontInput.addEventListener('input', (e) => {
      applyFont(e.target.value || 'sans-serif');
    });

    els.obsFontSizeRange.addEventListener('input', (e) => {
      state.fontSize = parseInt(e.target.value);
      if (els.fontSizeValBadge) els.fontSizeValBadge.textContent = `${state.fontSize}px`;
      saveUserSettings();
      syncLiveState();
    });

    els.chromaSelect.addEventListener('change', (e) => {
      state.chromaKey = e.target.value;

      if (state.chromaKey === 'green' || state.chromaKey === 'blue') {
        state.styleOptions.textColor = '#ffffff';
        state.styleOptions.strokeWidth = 3;
        state.styleOptions.strokeColor = '#000000';
        state.styleOptions.shadowBlur = 18;

        els.obsTextColor.value = '#ffffff';
        els.obsStrokeRange.value = 3;
        els.obsStrokeColor.value = '#000000';
        els.obsShadowRange.value = 18;
      } else if (state.chromaKey === 'black') {
        state.styleOptions.textColor = '#ffffff';
        state.styleOptions.strokeWidth = 0;
        state.styleOptions.shadowBlur = 0;

        els.obsTextColor.value = '#ffffff';
        els.obsStrokeRange.value = 0;
        els.obsShadowRange.value = 0;
      }

      if (els.obsOverlay) els.obsOverlay.setAttribute('data-chroma', state.chromaKey);
      saveUserSettings();
      syncLiveState();
    });

    els.presModeSelect.addEventListener('change', (e) => {
      state.presentationMode = e.target.value;
      saveUserSettings();
      if (state.activeSong) {
        loadSongIntoPresentation(state.activeSong);
      }
    });

    els.obsTextColor.addEventListener('input', (e) => {
      state.styleOptions.textColor = e.target.value;
      saveUserSettings();
      syncLiveState();
    });

    els.obsStrokeRange.addEventListener('input', (e) => {
      state.styleOptions.strokeWidth = parseInt(e.target.value);
      saveUserSettings();
      syncLiveState();
    });

    els.obsStrokeColor.addEventListener('input', (e) => {
      state.styleOptions.strokeColor = e.target.value;
      saveUserSettings();
      syncLiveState();
    });

    els.obsShadowRange.addEventListener('input', (e) => {
      state.styleOptions.shadowBlur = parseInt(e.target.value);
      saveUserSettings();
      syncLiveState();
    });

    els.btnOpenTvWindow.addEventListener('click', () => {
      launchPresenterOnSelectedScreen();
    });

    els.btnCopyObsUrl.addEventListener('click', async () => {
      const obsUrl = getObsUrl();
      
      try {
        await copyToClipboard(obsUrl);
        showToast('تم نسخ رابط OBS Browser Source بنجاح!');
      } catch (e) {
        showToast('رابط OBS: ' + obsUrl);
      }

      const originalHtml = els.btnCopyObsUrl.innerHTML;
      els.btnCopyObsUrl.innerHTML = `<i class="fa-solid fa-check fa-lg"></i> تم النسخ!`;
      els.btnCopyObsUrl.classList.add('btn-copied-anim');

      setTimeout(() => {
        els.btnCopyObsUrl.innerHTML = originalHtml;
        els.btnCopyObsUrl.classList.remove('btn-copied-anim');
      }, 1400);
    });

    let searchTimer;
    els.intelligentSearch.addEventListener('input', (e) => {
      const query = e.target.value;
      if (query) els.clearSearchBtn.classList.remove('hidden');
      else {
        els.clearSearchBtn.classList.add('hidden');
      }

      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => {
        performIntelligentSearch(query);
      }, 50);
    });

    els.intelligentSearch.addEventListener('focus', () => {
      if (els.intelligentSearch.value.trim()) {
        performIntelligentSearch(els.intelligentSearch.value);
      }
    });

    els.clearSearchBtn.addEventListener('click', () => {
      els.intelligentSearch.value = '';
      els.clearSearchBtn.classList.add('hidden');
      els.searchDropdown.classList.add('hidden');
    });

    els.btnPrevLine.addEventListener('click', prevLine);
    els.btnNextLine.addEventListener('click', nextLine);
    els.btnToggleBlank.addEventListener('click', toggleBlank);

    window.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        e.preventDefault();
        if (els.modalCredits && !els.modalCredits.classList.contains('hidden')) {
          els.modalCredits.classList.add('hidden');
          return;
        }
        els.intelligentSearch.focus();
        els.intelligentSearch.select();
        els.searchDropdown.classList.add('hidden');
        if (document.fullscreenElement) {
          document.exitFullscreen().catch(() => {});
        }
        return;
      }

      if (document.activeElement === els.intelligentSearch || document.activeElement.tagName === 'INPUT') {
        return;
      }

      if (e.key === 'ArrowLeft' || e.key === 'ArrowDown' || e.key === ' ') {
        e.preventDefault();
        nextLine();
      } else if (e.key === 'ArrowRight' || e.key === 'ArrowUp') {
        e.preventDefault();
        prevLine();
      } else if (e.key === 'b' || e.key === 'B') {
        toggleBlank();
      }
    });
  }

  async function detectConnectedScreens() {
    const select = els.connectedScreensSelect;
    if (!select) return;

    if ('getScreenDetails' in window) {
      try {
        screenDetails = await window.getScreenDetails();
        renderScreenOptions();

        screenDetails.addEventListener('screenschange', () => {
          renderScreenOptions();
        });
      } catch (err) {
        renderFallbackScreenOptions();
      }
    } else {
      renderFallbackScreenOptions();
    }
  }

  function renderScreenOptions() {
    const select = els.connectedScreensSelect;
    if (!screenDetails || !screenDetails.screens.length) {
      renderFallbackScreenOptions();
      return;
    }

    select.innerHTML = screenDetails.screens.map((s, idx) => {
      const type = s.isPrimary ? ' (الشاشة الحالية)' : ' (خارجية / TV)';
      const label = s.label || `شاشة ${idx + 1}`;
      return `<option value="${idx}">${label} (${s.width} × ${s.height})${type}</option>`;
    }).join('');
  }

  function renderFallbackScreenOptions() {
    const select = els.connectedScreensSelect;
    select.innerHTML = `
      <option value="primary">الشاشة الحالية (بدون فتح نافذة جديدة)</option>
      <option value="external">الشاشة الخارجية الثانية (TV / البروجيكتور)</option>
    `;
  }

  async function launchPresenterOnSelectedScreen() {
    if ('getScreenDetails' in window && !screenDetails) {
      try {
        screenDetails = await window.getScreenDetails();
        renderScreenOptions();
      } catch (err) {}
    }

    const select = els.connectedScreensSelect;
    const val = select.value;

    let targetScreen = null;
    if (screenDetails && screenDetails.screens) {
      const idx = parseInt(val);
      if (!isNaN(idx) && screenDetails.screens[idx]) {
        targetScreen = screenDetails.screens[idx];
      }
    }

    let left = (window.screen.availWidth || 1920);
    let top = 0;
    let width = 1920;
    let height = 1080;

    if (targetScreen) {
      left = targetScreen.availLeft;
      top = targetScreen.availTop;
      width = targetScreen.availWidth;
      height = targetScreen.availHeight;
    } else if (val === 'external') {
      left = window.screen.width || 1920;
      top = 0;
    }

    if (val === 'primary' && (!targetScreen || targetScreen.isPrimary || !screenDetails)) {
      if (els.obsOverlay) {
        els.obsOverlay.classList.remove('hidden');
        const docEl = document.documentElement || els.obsOverlay;
        if (docEl.requestFullscreen) {
          docEl.requestFullscreen().catch(() => {});
        }
      }
      syncLiveState();
      return;
    }

    const windowFeatures = `left=${left},top=${top},width=${width},height=${height},menubar=no,toolbar=no,location=no,status=no,resizable=yes`;
    const obsUrl = `obs.html?autofs=true&screenLeft=${left}&screenTop=${top}`;
    const popup = window.open(obsUrl, 'SundaySchoolPresenterWindow', windowFeatures);

    if (popup) {
      popup.focus();
      setTimeout(() => {
        try {
          popup.moveTo(left, top);
          popup.resizeTo(width, height);
        } catch (e) {}
      }, 100);
    }

    syncLiveState();
  }

  function makeDraggableCenterPivot() {
    const box = els.obsLowerThirdBox;
    if (!box) return;

    let isDragging = false;
    let startX, startY;
    const SNAP_THRESHOLD = 22;

    box.addEventListener('mousedown', (e) => {
      isDragging = true;
      startX = e.clientX;
      startY = e.clientY;
    });

    window.addEventListener('mousemove', (e) => {
      if (!isDragging) return;

      const screenW = window.innerWidth;
      const screenH = window.innerHeight;

      let currentXPixels = (state.dragPivot.xPct / 100) * screenW;
      let currentYPixels = (state.dragPivot.yPct / 100) * screenH;

      let targetX = currentXPixels + (e.clientX - startX);
      let targetY = currentYPixels + (e.clientY - startY);

      let snappedV = false;
      let snappedH = false;

      if (Math.abs(targetX - screenW / 2) < SNAP_THRESHOLD) {
        targetX = screenW / 2;
        snappedV = true;
      }

      if (Math.abs(targetY - screenH / 2) < SNAP_THRESHOLD) {
        targetY = screenH / 2;
        snappedH = true;
      } else if (Math.abs(targetY - (screenH * 0.75)) < SNAP_THRESHOLD) {
        targetY = screenH * 0.75;
        snappedH = true;
      }

      if (snappedV && els.snapGuideV) els.snapGuideV.classList.remove('hidden');
      else if (els.snapGuideV) els.snapGuideV.classList.add('hidden');

      if (snappedH && els.snapGuideH) els.snapGuideH.classList.remove('hidden');
      else if (els.snapGuideH) els.snapGuideH.classList.add('hidden');

      state.dragPivot.xPct = (targetX / screenW) * 100;
      state.dragPivot.yPct = (targetY / screenH) * 100;

      localStorage.setItem('sunday_school_taranim_drag_pivot', JSON.stringify(state.dragPivot));

      startX = e.clientX;
      startY = e.clientY;

      syncLiveState(true); // Explicit position change
    });

    window.addEventListener('mouseup', () => {
      if (isDragging) {
        isDragging = false;
        if (els.snapGuideV) els.snapGuideV.classList.add('hidden');
        if (els.snapGuideH) els.snapGuideH.classList.add('hidden');
      }
    });
  }

  function applyFont(fontFamily) {
    state.selectedFont = fontFamily;
    document.documentElement.style.setProperty('--font-family', fontFamily);
    saveUserSettings();
    syncLiveState();
  }

  async function loadInitialData() {
    fetch('arabic_dictionary.json')
      .then(r => r.json())
      .then(dict => { state.arabicDictionary = dict; })
      .catch(() => {});

    try {
      let res = await fetch('songs_catalog.json');
      if (!res.ok) res = await fetch('./songs_catalog.json');
      if (!res.ok) res = await fetch('/api/songs?limit=500');
      
      if (res.ok) {
        const data = await res.json();
        if (Array.isArray(data)) {
          state.allSongs = data;
        } else if (data && data.songs) {
          state.allSongs = data.songs;
        }
      }

      const realTotalCount = state.allSongs.length > 0 ? state.allSongs.length : 11611;
      
      if (els.totalSongsCount) {
        const formatted = Number(realTotalCount).toLocaleString('ar-EG');
        els.totalSongsCount.innerHTML = `<i class="fa-solid fa-music"></i> <span>${formatted} ترنيمة</span>`;
      }
    } catch (err) {
      if (els.totalSongsCount) {
        els.totalSongsCount.innerHTML = `<i class="fa-solid fa-music"></i> <span>١١٬٦١١ ترنيمة</span>`;
      }
    }
  }

  async function performIntelligentSearch(query) {
    if (!query || !query.trim()) {
      els.searchDropdown.classList.add('hidden');
      return;
    }

    const searchTarget = query.trim();
    let songsList = [];

    if (state.allSongs && state.allSongs.length > 0) {
      let qNorm = normalizeArabic(searchTarget);
      let qFrancoRaw = francoToArabic(searchTarget);
      let qFrancoCorrected = qFrancoRaw ? correctWithArabicDictionary(qFrancoRaw, state.arabicDictionary) : '';
      let qArabicCorrected = correctWithArabicDictionary(qNorm, state.arabicDictionary);

      songsList = state.allSongs.filter(song => {
        const tNorm = normalizeArabic(song.title || '');
        const nNorm = normalizeArabic(song.notes || '');

        return tNorm.includes(qNorm) || 
               nNorm.includes(qNorm) || 
               (qFrancoRaw && (tNorm.includes(qFrancoRaw) || nNorm.includes(qFrancoRaw))) ||
               (qFrancoCorrected && (tNorm.includes(qFrancoCorrected) || nNorm.includes(qFrancoCorrected))) ||
               (qArabicCorrected && (tNorm.includes(qArabicCorrected) || nNorm.includes(qArabicCorrected)));
      });
    }

    if (songsList.length < 5) {
      try {
        let res = await fetch(`/api/songs?q=${encodeURIComponent(searchTarget)}&limit=150`);
        if (res.ok) {
          let data = await res.json();
          if (data && data.songs && data.songs.length > 0) {
            songsList = songsList.concat(data.songs);
          }
        }
      } catch (err) {}
    }

    const uniqueMap = new Map();
    songsList.forEach(song => {
      if (song && (song.id !== undefined && song.id !== null)) {
        uniqueMap.set(String(song.id), song);
      }
    });

    const uniqueSongs = Array.from(uniqueMap.values());

    const scored = uniqueSongs.map(song => ({
      ...song,
      _score: getMatchScore(song, query)
    })).sort((a, b) => b._score - a._score);

    renderSearchDropdown(scored, query);
  }

  function renderSearchDropdown(songs, query) {
    let francoHeaderHtml = '';
    if (state.francoAutoTranslate && /[a-z0-9]/i.test(query)) {
      const rawTranslated = francoToArabic(query);
      if (rawTranslated) {
        const corrected = correctWithArabicDictionary(rawTranslated, state.arabicDictionary);
        francoHeaderHtml = `<div class="franco-translation-header"><i class="fa-solid fa-wand-magic-sparkles"></i> الترجمة الحية والمصححة: <strong>${escapeHtml(corrected)}</strong></div>`;
      }
    } else if (query && !/[a-z0-9]/i.test(query)) {
      const correctedAr = correctWithArabicDictionary(normalizeArabic(query), state.arabicDictionary);
      if (correctedAr && correctedAr !== normalizeArabic(query)) {
        francoHeaderHtml = `<div class="franco-translation-header"><i class="fa-solid fa-wand-magic-sparkles"></i> التصحيح الإملائي المقترح: <strong>${escapeHtml(correctedAr)}</strong></div>`;
      }
    }

    if (!songs || songs.length === 0) {
      els.searchDropdown.innerHTML = francoHeaderHtml + `<div class="search-item no-results-item"><span class="item-title">لم يتم العثور على ترنيمة أو شاهد كتابي</span></div>`;
    } else {
      const qClean = normalizeArabic(query);

      const itemsHtml = songs.slice(0, 14).map(s => {
        let snippetHtml = '';
        const rawNotes = s.notes || '';

        if (rawNotes) {
          try {
            const allLines = rawNotes.split(/[\n,]+/).map(l => l.trim()).filter(l => l.length > 0);
            
            if (allLines.length > 0) {
              let bestIdx = 0;
              if (qClean) {
                const matchedIdx = allLines.findIndex(line => normalizeArabic(line).includes(qClean));
                if (matchedIdx !== -1) bestIdx = matchedIdx;
              }

              const previewSlice = allLines.slice(bestIdx, bestIdx + 3);
              
              snippetHtml = previewSlice.map((line, idx) => {
                const lineNum = bestIdx + idx + 1;
                const formattedLine = escapeHtml(line);
                return `<span class="line-num-mini">(${lineNum})</span> ${formattedLine}`;
              }).join(' ');
            }
          } catch (err) {
            snippetHtml = escapeHtml(rawNotes.substring(0, 100));
          }
        }

        return `
          <div class="search-item" data-id="${s.id}">
            <div class="item-top">
              <span class="item-title">${escapeHtml(s.title)}</span>
              <span class="item-badge">${s.is_bible ? 'شاهد كتابي' : 'ترنيمة'}</span>
            </div>
            ${snippetHtml ? `<div class="item-preview-box">${snippetHtml}</div>` : ''}
          </div>
        `;
      }).join('');

      els.searchDropdown.innerHTML = francoHeaderHtml + itemsHtml;
    }

    els.searchDropdown.classList.remove('hidden');

    els.searchDropdown.querySelectorAll('.search-item').forEach(item => {
      item.addEventListener('click', () => {
        const id = item.dataset.id;
        if (!id) return;

        els.searchDropdown.classList.add('hidden');
        els.intelligentSearch.value = '';
        els.clearSearchBtn.classList.add('hidden');
        openAndPresentItem(id);
      });
    });
  }

  let availableObsScenes = [];

  function autoToggleObsSceneForRecentItem(songId) {
    if (!obsWsClient || !obsWsClient.isConnected || !availableObsScenes || availableObsScenes.length < 2) {
      return;
    }

    const recentIndex = state.sessionRecents.findIndex(r => String(r.id) === String(songId));
    if (recentIndex !== -1) {
      const sceneIndex = recentIndex % availableObsScenes.length;
      const targetScene = availableObsScenes[sceneIndex];
      if (targetScene) {
        obsWsClient.setCurrentScene(targetScene);
        if (els.obsSceneSelect) els.obsSceneSelect.value = targetScene;
      }
    }
  }

  async function openAndPresentItem(songId) {
    if (!songId) return;

    if (state.allSongs && state.allSongs.length > 0) {
      const localSong = state.allSongs.find(s => String(s.id) === String(songId));
      if (localSong) {
        state.activeSong = localSong;
        addToSessionRecents(localSong);
        autoToggleObsSceneForRecentItem(songId);
        loadSongIntoPresentation(localSong);
        return;
      }
    }

    try {
      const res = await fetch(`/api/song/${songId}`);
      if (res.ok) {
        const song = await res.json();
        if (song && song.title) {
          state.activeSong = song;
          addToSessionRecents(song);
          autoToggleObsSceneForRecentItem(songId);
          loadSongIntoPresentation(song);
          return;
        }
      }
    } catch (err) {
      showToast('تعذر تحميل بيانات الترنيمة.');
    }
  }

  function loadSongIntoPresentation(song) {
    if (!song) return;

    let linesList = [];

    if (song.verses && Array.isArray(song.verses) && song.verses.length > 0) {
      song.verses.forEach((verse, vIdx) => {
        if (verse.slides && Array.isArray(verse.slides)) {
          verse.slides.forEach((slide, sIdx) => {
            if (slide.lines && Array.isArray(slide.lines) && slide.lines.length > 0) {
              slide.lines.forEach(line => {
                if (line && line.trim()) {
                  linesList.push({
                    text: line.trim(),
                    label: state.presentationMode === 'oneline' ? `بيت ${vIdx + 1}` : `شريحة ${sIdx + 1}`
                  });
                }
              });
            } else if (slide.text && slide.text.trim()) {
              slide.text.split('\n').forEach(line => {
                if (line && line.trim()) {
                  linesList.push({
                    text: line.trim(),
                    label: state.presentationMode === 'oneline' ? `بيت ${vIdx + 1}` : `شريحة ${sIdx + 1}`
                  });
                }
              });
            }
          });
        }
      });
    }

    if (linesList.length === 0 && song.notes) {
      const rawNotes = String(song.notes);
      const splitLines = rawNotes.split('\n').map(l => l.trim()).filter(l => l.length > 0);
      linesList = splitLines.map((line, idx) => ({
        text: line,
        label: `بيت ${idx + 1}`
      }));
    }

    state.presentationLines = linesList;
    state.currentLineIndex = 0;
    state.isBlank = false;

    renderPresentationLinesList();
    syncLiveState();
  }

  function renderPresentationLinesList() {
    const { presentationLines, currentLineIndex } = state;
    els.currentLineCounter.textContent = `${presentationLines.length ? currentLineIndex + 1 : 0} / ${presentationLines.length}`;

    if (presentationLines.length === 0) {
      els.presentationLinesContainer.innerHTML = `
        <div class="empty-state">
          <i class="fa-solid fa-music"></i>
          <p>اختر ترنيمة أو إصحاحاً لعرض الأبيات مرتبة هنا.</p>
        </div>
      `;
      return;
    }

    els.presentationLinesContainer.innerHTML = presentationLines.map((l, idx) => `
      <div class="line-item ${idx === currentLineIndex ? 'active' : ''}" data-idx="${idx}">
        <span class="line-num">(${idx + 1})</span>
        <span class="line-content">${escapeHtml(l.text)}</span>
        <button class="copy-line-btn" data-text="${escapeHtml(l.text)}" title="نسخ هذا السطر للحافظة">
          <i class="fa-solid fa-copy"></i>
        </button>
      </div>
    `).join('');

    els.presentationLinesContainer.querySelectorAll('.copy-line-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const textToCopy = btn.dataset.text;
        navigator.clipboard.writeText(textToCopy);

        btn.innerHTML = `<i class="fa-solid fa-check"></i>`;
        btn.classList.add('copied');
        setTimeout(() => {
          btn.innerHTML = `<i class="fa-solid fa-copy"></i>`;
          btn.classList.remove('copied');
        }, 1200);
      });
    });

    els.presentationLinesContainer.querySelectorAll('.line-item').forEach(item => {
      item.addEventListener('click', (e) => {
        if (e.target.closest('.copy-line-btn')) return;
        state.currentLineIndex = parseInt(item.dataset.idx);
        renderPresentationLinesList();
        syncLiveState();
      });
    });

    const activeEl = els.presentationLinesContainer.querySelector('.line-item.active');
    if (activeEl) {
      activeEl.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
  }

  function addToSessionRecents(song) {
    const exists = state.sessionRecents.some(r => r.id === song.id);
    if (!exists) {
      state.sessionRecents.unshift({ id: song.id, title: song.title });
      sessionStorage.setItem('sunday_school_taranim_session_recents', JSON.stringify(state.sessionRecents));
    }
    renderRecentSession();
  }

  function renderRecentSession() {
    els.recentCount.textContent = state.sessionRecents.length;

    if (state.sessionRecents.length === 0) {
      els.recentSessionContainer.innerHTML = `
        <div class="empty-state">
          <i class="fa-solid fa-clock-rotate-left"></i>
          <p>سوف تظهر الترانيم والأصحاحات المفتوحة مؤخراً هنا لتنقل سريع خلال الخدمة.</p>
        </div>
      `;
      return;
    }

    els.recentSessionContainer.innerHTML = state.sessionRecents.map((r, idx) => `
      <div class="recent-item ${state.activeSong && String(state.activeSong.id) === String(r.id) ? 'active' : ''}" data-id="${r.id}" data-index="${idx}" draggable="true">
        <div class="recent-title-group">
          <i class="fa-solid fa-grip-vertical drag-handle-icon" title="سحب لإعادة الترتيب"></i>
          <span class="recent-title">${escapeHtml(r.title)}</span>
        </div>
        <button class="delete-recent-btn" data-index="${idx}" title="حذف الترنيمة من القائمة وإخفاء العرض">
          <i class="fa-solid fa-trash-can"></i> حذف
        </button>
      </div>
    `).join('');

    els.recentSessionContainer.querySelectorAll('.delete-recent-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const index = parseInt(btn.dataset.index);
        const songToDelete = state.sessionRecents[index];

        state.sessionRecents.splice(index, 1);
        sessionStorage.setItem('sunday_school_taranim_session_recents', JSON.stringify(state.sessionRecents));

        if (songToDelete && state.activeSong && String(state.activeSong.id) === String(songToDelete.id)) {
          state.activeSong = null;
          state.presentationLines = [];
          state.currentLineIndex = 0;
          state.isBlank = true;
          renderPresentationLinesList();
          syncLiveState();
        }

        renderRecentSession();
      });
    });

    els.recentSessionContainer.querySelectorAll('.recent-item').forEach(item => {
      item.addEventListener('click', (e) => {
        if (e.target.closest('.delete-recent-btn')) return;
        const id = item.dataset.id;
        openAndPresentItem(id);
      });
    });

    let draggedIndex = null;

    els.recentSessionContainer.querySelectorAll('.recent-item').forEach(item => {
      item.addEventListener('dragstart', (e) => {
        draggedIndex = parseInt(item.dataset.index);
        item.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
      });

      item.addEventListener('dragover', (e) => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
      });

      item.addEventListener('drop', (e) => {
        e.preventDefault();
        const dropIndex = parseInt(item.dataset.index);
        if (draggedIndex !== null && draggedIndex !== dropIndex) {
          const movedItem = state.sessionRecents.splice(draggedIndex, 1)[0];
          state.sessionRecents.splice(dropIndex, 0, movedItem);
          sessionStorage.setItem('sunday_school_taranim_session_recents', JSON.stringify(state.sessionRecents));
          renderRecentSession();
        }
      });

      item.addEventListener('dragend', () => {
        item.classList.remove('dragging');
        draggedIndex = null;
      });
    });
  }

  let activePostUrl = null;

  function sendLivePayload(postBody) {
    const headers = { 'Content-Type': 'application/json' };
    const targetUrl = activePostUrl || (getApiUrl() + '?action=live');

    fetch(targetUrl, { method: 'POST', headers, body: postBody, keepalive: true })
      .then(res => {
        if (res.ok && !activePostUrl) {
          activePostUrl = targetUrl;
        }
      })
      .catch(() => {
        activePostUrl = null;
        fetch('api.php?action=live', { method: 'POST', headers, body: postBody, keepalive: true })
          .then(res => { if (res.ok) activePostUrl = 'api.php?action=live'; })
          .catch(() => {});
      });
  }

  function syncLiveState(isExplicitPositionUpdate = false) {
    const currentLine = state.presentationLines[state.currentLineIndex];
    const text = currentLine ? currentLine.text : '';

    const payload = {
      type: 'PRESENT_LINE',
      text: text,
      songTitle: state.activeSong ? state.activeSong.title : '',
      font: state.selectedFont,
      fontSize: state.fontSize,
      chroma: state.chromaKey,
      isBlank: state.isBlank,
      textColor: state.styleOptions.textColor,
      strokeWidth: state.styleOptions.strokeWidth,
      strokeColor: state.styleOptions.strokeColor,
      shadowBlur: state.styleOptions.shadowBlur
    };

    // ONLY ATTACH POSITION IF IT WAS EXPLICITLY DRAGGED/MODIFIED
    if (isExplicitPositionUpdate) {
      payload.pos = state.dragPivot;
    }

    payload.updatedAt = Date.now();

    broadcastChannel.postMessage(payload);
    localStorage.setItem('sunday_school_taranim_live_presentation', JSON.stringify(payload));

    const postBody = JSON.stringify(payload);
    sendLivePayload(postBody);

    if (els.obsLineText) {
      els.obsLineText.style.fontFamily = state.selectedFont;
      els.obsLineText.style.color = state.styleOptions.textColor;
      els.obsLineText.textContent = text;
      
      let size = state.fontSize || 54;
      els.obsLineText.style.fontSize = `${size}px`;

      const maxW = window.innerWidth * 0.90;
      const maxH = window.innerHeight * 0.85;

      while ((els.obsLineText.scrollWidth > maxW || els.obsLineText.scrollHeight > maxH) && size > 18) {
        size -= 2;
        els.obsLineText.style.fontSize = `${size}px`;
      }
      
      const xPct = state.dragPivot.xPct !== undefined ? state.dragPivot.xPct : 50;
      const yPct = state.dragPivot.yPct !== undefined ? state.dragPivot.yPct : 75;

      els.obsLowerThirdBox.style.left = `${xPct}%`;
      els.obsLowerThirdBox.style.top = `${yPct}%`;
      els.obsLowerThirdBox.style.transform = 'translate(-50%, -50%)';
    }

    obsWsClient.sendLineTextToObsSource(text);
  }

  function nextLine() {
    if (state.currentLineIndex < state.presentationLines.length - 1) {
      state.currentLineIndex++;
      renderPresentationLinesList();
      syncLiveState();
    }
  }

  function prevLine() {
    if (state.currentLineIndex > 0) {
      state.currentLineIndex--;
      renderPresentationLinesList();
      syncLiveState();
    }
  }

  function toggleBlank() {
    state.isBlank = !state.isBlank;
    syncLiveState();
  }

  function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  // OBS WEBSOCKET POP-OVER & QR SCANNER CONTROLLER
  const obsWsClient = new OBSWSClient({
    onStatusChange: (connected, statusText) => {
      if (els.obsWsStatusBadge) {
        els.obsWsStatusBadge.className = `badge-status ${connected ? 'connected' : 'disconnected'}`;
      }
      if (els.obsConnectionStatusText) {
        els.obsConnectionStatusText.className = `status-pill ${connected ? 'online' : 'offline'}`;
        els.obsConnectionStatusText.textContent = statusText;
      }
      if (els.btnConnectObsWs) els.btnConnectObsWs.classList.toggle('hidden', connected);
      if (els.btnDisconnectObsWs) els.btnDisconnectObsWs.classList.toggle('hidden', !connected);
      if (els.obsCompactStrip) els.obsCompactStrip.classList.toggle('hidden', !connected);

      if (els.obsSceneSelect) els.obsSceneSelect.disabled = !connected;
      if (els.obsTransitionSelect) els.obsTransitionSelect.disabled = !connected;
      if (els.obsTransitionDurationRange) els.obsTransitionDurationRange.disabled = !connected;
      if (els.btnTriggerObsTransition) els.btnTriggerObsTransition.disabled = !connected;

      if (els.obsHttpsWarning) {
        if (window.location.protocol === 'https:' && !connected && statusText.includes('HTTPS')) {
          els.obsHttpsWarning.innerHTML = `
            <i class="fa-solid fa-triangle-exclamation"></i> يمنع المتصفح الاتصال بالشبكة المحلية <code>ws://</code> على صفحات HTTPS.<br>
            <a href="http://sunday-school.online/taranim/" style="color:#2563eb; font-weight:bold; text-decoration:underline;" target="_blank">انقر هنا لفتح الصفحة عبر HTTP للربط بـ OBS</a>
          `;
          els.obsHttpsWarning.classList.remove('hidden');
        } else {
          els.obsHttpsWarning.classList.add('hidden');
        }
      }
    },
    onScenesUpdated: (scenes, currentScene) => {
      availableObsScenes = scenes;
      if (!els.obsSceneSelect) return;
      els.obsSceneSelect.innerHTML = scenes.map(s => `<option value="${escapeHtml(s)}" ${s === currentScene ? 'selected' : ''}>${escapeHtml(s)}</option>`).join('');
    },
    onTransitionsUpdated: (transitions, currentTransition) => {
      if (!els.obsTransitionSelect) return;
      els.obsTransitionSelect.innerHTML = transitions.map(t => `<option value="${escapeHtml(t)}" ${t === currentTransition ? 'selected' : ''}>${escapeHtml(t)}</option>`).join('');
    }
  });

  const savedObsWsRaw = localStorage.getItem('sunday_school_taranim_obs_ws_config');
  if (savedObsWsRaw) {
    try {
      const conf = JSON.parse(savedObsWsRaw);
      if (conf.ip && els.obsWsIp) els.obsWsIp.value = conf.ip;
      if (conf.port && els.obsWsPort) els.obsWsPort.value = conf.port;
      if (conf.password !== undefined && els.obsWsPassword) els.obsWsPassword.value = conf.password;
    } catch(e) {}
  }

  if (els.btnMenuObsWs && els.popoverObsWs) {
    els.btnMenuObsWs.addEventListener('click', (e) => {
      e.stopPropagation();
      const willShow = els.popoverObsWs.classList.contains('hidden');
      closeAllPopovers(els.popoverObsWs);
      if (willShow) {
        els.popoverObsWs.classList.remove('hidden');
      } else {
        els.popoverObsWs.classList.add('hidden');
      }
    });

    els.popoverObsWs.addEventListener('click', (e) => e.stopPropagation());
  }

  if (els.btnConnectObsWs) {
    els.btnConnectObsWs.addEventListener('click', () => {
      const ip = els.obsWsIp ? els.obsWsIp.value.trim() : 'localhost';
      const port = els.obsWsPort ? parseInt(els.obsWsPort.value.trim()) || 4455 : 4455;
      const password = els.obsWsPassword ? els.obsWsPassword.value : '';

      localStorage.setItem('sunday_school_taranim_obs_ws_config', JSON.stringify({ ip, port, password }));
      obsWsClient.connect(ip, port, password);
    });
  }

  if (els.btnDisconnectObsWs) {
    els.btnDisconnectObsWs.addEventListener('click', () => obsWsClient.disconnect());
  }

  if (els.obsSceneSelect) {
    els.obsSceneSelect.addEventListener('change', (e) => obsWsClient.setCurrentScene(e.target.value));
  }

  if (els.obsTransitionSelect) {
    els.obsTransitionSelect.addEventListener('change', (e) => obsWsClient.setTransition(e.target.value));
  }

  if (els.obsTransitionDurationRange) {
    els.obsTransitionDurationRange.addEventListener('input', (e) => {
      const val = e.target.value;
      if (els.obsTransitionDurationBadge) els.obsTransitionDurationBadge.textContent = `${val}ms`;
      obsWsClient.setTransitionDuration(val);
    });
  }

  if (els.btnTriggerObsTransition) {
    els.btnTriggerObsTransition.addEventListener('click', () => obsWsClient.triggerTransition());
  }

  // QR CODE SCANNER CONTROLLER
  let html5QrCodeScanner = null;

  function parseObsQrPayload(qrText) {
    if (!qrText) return null;
    let s = String(qrText).trim();

    if (s.startsWith('{') && s.endsWith('}')) {
      try {
        const obj = JSON.parse(s);
        return {
          ip: obj.ip || obj.host || obj.server || 'localhost',
          port: obj.port || 4455,
          password: obj.password || obj.pass || obj.auth || ''
        };
      } catch(e) {}
    }

    s = s.replace(/^(obs-websocket:\/\/|ws:\/\/|wss:\/\/|http:\/\/|https:\/\/)/i, '');

    let password = '';
    if (s.includes('?auth=')) {
      const parts = s.split('?auth=');
      s = parts[0];
      password = parts[1] || '';
    } else if (s.includes('?password=')) {
      const parts = s.split('?password=');
      s = parts[0];
      password = parts[1] || '';
    } else if (s.includes('@')) {
      const parts = s.split('@');
      password = parts[0].replace(/^:/, '');
      s = parts[1];
    } else if (s.includes('/')) {
      const parts = s.split('/');
      s = parts[0];
      password = parts.slice(1).join('/');
    }

    const hostParts = s.split(':');
    const ip = hostParts[0] || 'localhost';
    const port = hostParts[1] ? parseInt(hostParts[1]) || 4455 : 4455;
    return { ip, port, password };
  }

  if (els.btnScanQr && els.obsQrModal) {
    els.btnScanQr.addEventListener('click', () => {
      els.obsQrModal.classList.remove('hidden');
      if (els.popoverObsWs) els.popoverObsWs.classList.add('hidden');

      if (window.Html5Qrcode) {
        if (!html5QrCodeScanner) {
          html5QrCodeScanner = new Html5Qrcode('qr-reader-viewport');
        }
        html5QrCodeScanner.start(
          { facingMode: 'environment' },
          { fps: 10, qrbox: { width: 220, height: 220 } },
          (decodedText) => {
            const parsed = parseObsQrPayload(decodedText);
            if (parsed) {
              if (els.obsWsIp) els.obsWsIp.value = parsed.ip;
              if (els.obsWsPort) els.obsWsPort.value = parsed.port;
              if (els.obsWsPassword) els.obsWsPassword.value = parsed.password;

              showToast('تم مسح بيانات OBS بنجاح!');
              stopQrScanner();
              els.obsQrModal.classList.add('hidden');
              localStorage.setItem('sunday_school_taranim_obs_ws_config', JSON.stringify(parsed));
              obsWsClient.connect(parsed.ip, parsed.port, parsed.password);
            }
          },
          () => {}
        ).catch(() => {
          if (els.qrScanResultMsg) {
            els.qrScanResultMsg.className = 'qr-status-msg error';
            els.qrScanResultMsg.textContent = 'تعذر فتح الكاميرا. يرجى السماح بإذن الكاميرا.';
            els.qrScanResultMsg.classList.remove('hidden');
          }
        });
      } else {
        alert('تعذر تحميل مكتبة مسح QR Code.');
      }
    });
  }

  function stopQrScanner() {
    if (html5QrCodeScanner) {
      try {
        html5QrCodeScanner.stop().catch(() => {});
      } catch(e) {}
    }
  }

  if (els.btnCloseQrModal && els.obsQrModal) {
    els.btnCloseQrModal.addEventListener('click', () => {
      stopQrScanner();
      els.obsQrModal.classList.add('hidden');
    });
  }

  // WAKE LOCK & VISIBILITY AUTO-RESYNC TO PREVENT STALE STATE WHEN UN-MINIMIZING ON MOBILE
  let wakeLock = null;
  async function requestWakeLock() {
    if ('wakeLock' in navigator) {
      try {
        wakeLock = await navigator.wakeLock.request('screen');
      } catch (err) {}
    }
  }
  requestWakeLock();

  document.addEventListener('visibilitychange', () => {
    if (document.visibilityState === 'visible') {
      if (wakeLock === null || (wakeLock && wakeLock.released)) {
        requestWakeLock();
      }
      syncLiveState();
    }
  });

  window.addEventListener('focus', () => syncLiveState());
  window.addEventListener('online', () => syncLiveState());
});
