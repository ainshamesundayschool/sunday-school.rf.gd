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

// ==========================================================================
// ENHANCED ARABIC NORMALIZATION
// Handles: harakat, hamzat forms, alef variants, Egyptian dialect substitutions,
// common prefix/suffix stripping (al-, wa-, bi-, etc.), ta-marbouta
// ==========================================================================
function normalizeArabic(text) {
  if (!text) return '';
  return String(text)
    // Alef variants
    .replace(/[أإآٱاٲٳ]/g, 'ا')
    // Ya variants
    .replace(/[ىئ]/g, 'ي')
    // Ta-marbouta → ha
    .replace(/ة/g, 'ه')
    // Waw variants
    .replace(/[ؤۇۈ]/g, 'و')
    // Remove all diacritics (harakat, shadda, sukun, maddah, etc.)
    .replace(/[\u064B-\u065F\u0670\u0610-\u061A]/g, '')
    // Tatweel (kashida)
    .replace(/\u0640/g, '')
    // Egyptian dialect: ق often pronounced/written as أ → normalize both to q base
    // Don't collapse ق/ء since they're distinct — but strip leading ال
    .replace(/^ال/g, '')
    // Normalize space sequences
    .replace(/\s+/g, ' ')
    .toLowerCase()
    .trim();
}

// Strip common Arabic prefixes AND suffixes for root matching
function arabicStem(word) {
  let w = normalizeArabic(word);
  // Strip prefixes: و ف ب ل ك ال
  w = w.replace(/^(وال|فال|بال|لل|ال|و|ف|ب|ل|ك)/, '');
  // Strip suffixes: ها هم هن كم ون ين ات ه
  w = w.replace(/(ها|هم|هن|كم|ون|ين|ات|ه|ي|ك|ا|وا)$/, '');
  return w.length > 1 ? w : normalizeArabic(word);
}

function francoToArabic(text) {
  if (!text) return '';
  let s = text.toLowerCase().trim();
  if (!/[a-z0-9]/.test(s)) return '';

  FRANCO_MAPPINGS.forEach(([f, a]) => {
    s = s.split(f).join(a);
  });
  return s;
}

// ==========================================================================
// MULTI-SIGNAL MATCH SCORER
// Returns a score 0–100 based on multiple match strategies
// ==========================================================================
function getMatchScore(song, query) {
  if (!song || !query) return 0;
  const title = song.title || '';
  const notes = song.notes || '';

  const qRaw    = query.trim().toLowerCase();
  const qNorm   = normalizeArabic(query);
  const qFranco = francoToArabic(query);
  const qWords  = qNorm.split(/\s+/).filter(Boolean);
  const qStems  = qWords.map(arabicStem);

  const tRaw   = title.toLowerCase();
  const tNorm  = normalizeArabic(title);
  const tWords = tNorm.split(/\s+/).filter(Boolean);
  const tStems = tWords.map(arabicStem);
  const nNorm  = normalizeArabic(notes);
  const nLines = notes.split(/[\n,]+/).map(l => normalizeArabic(l.trim())).filter(Boolean);

  // --- Tier 1: Exact or full-phrase ---
  if (tRaw === qRaw || tNorm === qNorm) return 100;
  if (qFranco && (tNorm === qFranco)) return 98;

  // --- Tier 2: Starts with query ---
  if (tNorm.startsWith(qNorm)) return 90;
  if (qFranco && tNorm.startsWith(qFranco)) return 88;

  // --- Tier 3: Substring match in title ---
  if (tNorm.includes(qNorm)) return 78;
  if (qFranco && tNorm.includes(qFranco)) return 74;

  // --- Tier 4: All query words appear somewhere in title ---
  if (qWords.length > 1) {
    const allWordsInTitle = qWords.every(w => tNorm.includes(w));
    if (allWordsInTitle) return 70;
    const allStemsInTitle = qStems.every(s => tStems.some(ts => ts.includes(s) || s.includes(ts)));
    if (allStemsInTitle) return 65;
  }

  // --- Tier 5: Any query word matches a title word (stem-aware) ---
  let wordHits = 0;
  for (const qStem of qStems) {
    if (qStem.length < 2) continue;
    for (const tStem of tStems) {
      if (tStem.includes(qStem) || qStem.includes(tStem)) {
        wordHits++;
        break;
      }
    }
  }
  if (wordHits > 0) {
    const wordScore = 40 + Math.round((wordHits / Math.max(qStems.length, 1)) * 20);
    // Word score is a fallback — but bump if majority matched
    if (wordHits >= qStems.length) return Math.max(wordScore, 62);
    return wordScore;
  }

  // --- Tier 6: Fuzzy per-word (Levenshtein) on title words ---
  if (qNorm.length >= 3) {
    for (const qW of qWords) {
      if (qW.length < 3) continue;
      for (const tW of tWords) {
        const maxDist = qW.length <= 4 ? 1 : 2;
        if (Math.abs(tW.length - qW.length) <= maxDist) {
          const dist = levenshteinDistance(qW, tW);
          if (dist <= maxDist) return 38;
        }
      }
    }
  }

  // --- Tier 7: Match in lyrics/notes ---
  if (qNorm.length >= 2) {
    // Check if any lyric line contains the full query
    const lineMatch = nLines.some(l => l.includes(qNorm));
    if (lineMatch) return 52;

    // Check if all words appear in any single lyric line
    if (qWords.length > 1) {
      const lineWordMatch = nLines.some(l => qWords.every(w => l.includes(w)));
      if (lineWordMatch) return 45;
    }

    // Any word in notes
    if (nNorm.includes(qNorm)) return 30;
    if (qFranco && nNorm.includes(qFranco)) return 25;
  }

  return 0;
}

// Find the best matching lyric line and its index for a given query
function findBestLyricMatch(linesRaw, qNorm, qWords) {
  if (!linesRaw || !linesRaw.length) return null;

  let bestScore = -1;
  let bestIdx = -1;

  for (let i = 0; i < linesRaw.length; i++) {
    const lineNorm = normalizeArabic(linesRaw[i]);
    let score = 0;
    if (lineNorm.includes(qNorm)) {
      score = 100;
    } else if (qWords.length > 1 && qWords.every(w => lineNorm.includes(w))) {
      score = 80;
    } else {
      for (const w of qWords) {
        if (w.length >= 2 && lineNorm.includes(w)) score += 30;
      }
    }
    if (score > bestScore) {
      bestScore = score;
      bestIdx = i;
    }
  }

  return bestScore > 0 ? { idx: bestIdx, score: bestScore } : null;
}

// Highlight matched text inside a string with <mark>
function highlightMatches(rawText, qNorm, qWords) {
  if (!rawText) return '';
  let escaped = rawText
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

  // Build array of match regions from the normalized version
  const normText = normalizeArabic(rawText);

  // Collect all ranges to highlight (from qNorm full phrase and each word)
  const toMark = [];

  function addMatchRanges(needle) {
    if (!needle || needle.length < 2) return;
    let start = 0;
    while (true) {
      const pos = normText.indexOf(needle, start);
      if (pos === -1) break;
      toMark.push({ start: pos, end: pos + needle.length });
      start = pos + 1;
    }
  }

  addMatchRanges(qNorm);
  for (const w of qWords) {
    if (w.length >= 2) addMatchRanges(w);
  }

  if (!toMark.length) return escaped;

  // Sort and merge overlapping regions
  toMark.sort((a, b) => a.start - b.start);
  const merged = [];
  for (const r of toMark) {
    if (merged.length && r.start <= merged[merged.length - 1].end) {
      merged[merged.length - 1].end = Math.max(merged[merged.length - 1].end, r.end);
    } else {
      merged.push({ ...r });
    }
  }

  // Build output: rawText characters with <mark> wrapping matched ranges
  // Since normalization may differ in length from rawText, we use rawText directly
  // but use normText positions as a guide (they're character-aligned for Arabic text)
  let result = '';
  let lastEnd = 0;
  for (const { start, end } of merged) {
    const before = rawText.substring(lastEnd, start)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    const match  = rawText.substring(start, end)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    result += before + `<mark class="search-highlight">${match}</mark>`;
    lastEnd = end;
  }
  result += rawText.substring(lastEnd)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

  return result;
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

function getApiUrl() {
  const loc = window.location;
  let path = loc.pathname;
  if (path.includes('/public/')) {
    return path.replace(/\/public\/.*$/, '/api.php');
  }
  if (path.endsWith('.html') || path.endsWith('.php')) {
    return path.substring(0, path.lastIndexOf('/') + 1) + 'api.php';
  }
  if (!path.endsWith('/')) path += '/';
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
  try { document.execCommand('copy'); } catch (err) {}
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

if ('serviceWorker' in navigator) {
  window.addEventListener('load', () => {
    navigator.serviceWorker.register('./sw.js').then((reg) => {
      reg.update();
    }).catch(() => {});

    let isRefreshing = false;
    navigator.serviceWorker.addEventListener('controllerchange', () => {
      if (!isRefreshing) {
        isRefreshing = true;
        window.location.reload();
      }
    });
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
    // AUTO-RECONNECT STATE
    this._reconnectTimer = null;
    this._userDisconnected = false;  // true only when user explicitly clicks disconnect
    this._reconnectAttempts = 0;
  }

  connect(ip, port, password) {
    if (ip) this.ip = String(ip).trim();
    if (port) this.port = port;
    if (password !== undefined) this.password = String(password);

    // Mark as user-initiated connect — clears the disconnect flag
    this._userDisconnected = false;
    this._reconnectAttempts = 0;
    clearTimeout(this._reconnectTimer);

    this.disconnect();
    // Restore flag after disconnect() sets it to true
    this._userDisconnected = false;

    let rawHost = this.ip;
    let scheme = 'ws://';

    if (/^wss:\/\//i.test(rawHost)) {
      scheme = 'wss://';
      rawHost = rawHost.replace(/^wss:\/\//i, '');
    } else {
      rawHost = rawHost.replace(/^[a-z0-9+\-.]+:\/\//i, '');
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
      let errMsg = 'تعذر الوصول لخادم OBS.';
      if (window.location.protocol === 'https:' && scheme === 'ws://') {
        errMsg = 'HTTPS يحظر الاتصال المحلي ws://. افتح الموقع عبر HTTP للاتصال بـ OBS.';
      }
      this.onStatusChange(false, errMsg);
    };

    this.ws.onclose = (e) => {
      this.isConnected = false;
      let reason = 'غير متصل بـ OBS';
      if (e) {
        if (e.code === 4008 || e.code === 4009) {
          reason = 'كلمة سر OBS مطلوبة أو غير صحيحة';
        } else if (e.code === 1006) {
          reason = 'لم يتم العثور على خادم OBS. تأكد من تفعيل WebSocket في OBS (Tools -> WebSocket Server Settings)';
        }
      }
      this.onStatusChange(false, reason);

      // AUTO-RECONNECT: retry every 3s unless user explicitly disconnected or wrong password
      if (!this._userDisconnected && e && e.code !== 4008 && e.code !== 4009) {
        this._reconnectAttempts++;
        const delay = Math.min(3000, 1000 + (this._reconnectAttempts - 1) * 500);
        clearTimeout(this._reconnectTimer);
        this._reconnectTimer = setTimeout(() => {
          if (!this._userDisconnected && !this.isConnected) {
            this.onStatusChange(false, `إعادة الاتصال... (محاولة ${this._reconnectAttempts})`);
            this.connect(this.ip, this.port, this.password);
          }
        }, delay);
      }
    };
  }

  disconnect() {
    this._userDisconnected = true;
    this._reconnectAttempts = 0;
    clearTimeout(this._reconnectTimer);
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
    textAnimation: savedSettings.textAnimation || "slide",
    
    styleOptions: {
      textColor: savedSettings.styleOptions?.textColor || "#ffffff",
      strokeWidth: savedSettings.styleOptions?.strokeWidth || 0,
      strokeColor: savedSettings.styleOptions?.strokeColor || "#000000",
      shadowBlur: savedSettings.styleOptions?.shadowBlur || 0,
      shadowColor: savedSettings.styleOptions?.shadowColor || "#000000",
      shadowStyle: savedSettings.styleOptions?.shadowStyle || "soft",
      fontWeight: savedSettings.styleOptions?.fontWeight || "800",
      fontStyle: savedSettings.styleOptions?.fontStyle || "normal",
      textDecoration: savedSettings.styleOptions?.textDecoration || "none",
      textAlign: savedSettings.styleOptions?.textAlign || "center",
      letterSpacing: savedSettings.styleOptions?.letterSpacing || 0,
      lineHeight: savedSettings.styleOptions?.lineHeight || 1.5,
      boxBgColor: savedSettings.styleOptions?.boxBgColor || "#000000",
      boxOpacity: savedSettings.styleOptions?.boxOpacity || 0,
      boxRadius: savedSettings.styleOptions?.boxRadius || 12,
      boxPadding: savedSettings.styleOptions?.boxPadding || 20
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

    btnMenuInstall: document.getElementById('btn-menu-install'),
    popoverInstall: document.getElementById('popover-install'),
    btnDropdownPrecache: document.getElementById('btn-dropdown-precache'),
    popoverProgressWrapper: document.getElementById('popover-progress-wrapper'),
    popoverProgressFill: document.getElementById('popover-progress-fill'),
    popoverProgressText: document.getElementById('popover-progress-text'),
    btnDropdownStartOffline: document.getElementById('btn-dropdown-start-offline'),
    btnDropdownPwaInstall: document.getElementById('btn-dropdown-pwa-install'),
    dropdownPwaInstallRow: document.getElementById('dropdown-pwa-install-row'),
    popoverOfflineStatusText: document.getElementById('popover-offline-status-text'),

    searchSuggestionsChips: document.getElementById('search-suggestions-chips'),
    obsShadowAngleRange: document.getElementById('obs-shadow-angle-range'),
    shadowAngleBadge: document.getElementById('shadow-angle-badge'),
    obsShadowDistanceRange: document.getElementById('obs-shadow-distance-range'),
    shadowDistanceBadge: document.getElementById('shadow-distance-badge'),
    btnAlignJustify: document.getElementById('btn-align-justify'),
    screensCastList: document.getElementById('screens-cast-list'),

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
    obsShadowColor: document.getElementById('obs-shadow-color'),
    obsShadowStyle: document.getElementById('obs-shadow-style'),
    obsTextAnimSelect: document.getElementById('obs-text-anim-select'),

    obsFontWeightSelect: document.getElementById('obs-font-weight-select'),
    btnAlignCenter: document.getElementById('btn-align-center'),
    btnAlignRight: document.getElementById('btn-align-right'),
    btnAlignLeft: document.getElementById('btn-align-left'),
    btnToggleBold: document.getElementById('btn-toggle-bold'),
    btnToggleItalic: document.getElementById('btn-toggle-italic'),
    btnToggleUnderline: document.getElementById('btn-toggle-underline'),
    obsLetterSpacingRange: document.getElementById('obs-letter-spacing-range'),
    obsLineHeightRange: document.getElementById('obs-line-height-range'),
    obsBoxBgColor: document.getElementById('obs-box-bg-color'),
    obsBoxOpacityRange: document.getElementById('obs-box-opacity-range'),
    obsBoxRadiusRange: document.getElementById('obs-box-radius-range'),
    obsBoxPaddingRange: document.getElementById('obs-box-padding-range'),
    btnResetDefaultTemplate: document.getElementById('btn-reset-default-template'),

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
    obsHttpsWarning: document.getElementById('obs-https-warning'),
    obsHttpFallback: document.getElementById('obs-http-fallback'),
    obsHttpLink: document.getElementById('obs-http-link'),
    obsPopoverControls: document.getElementById('obs-popover-controls'),
    popoverObsSceneSelect: document.getElementById('popover-obs-scene-select'),
    popoverObsTransitionSelect: document.getElementById('popover-obs-transition-select'),
    popoverObsDurationRange: document.getElementById('popover-obs-duration-range'),
    popoverObsDurationBadge: document.getElementById('popover-obs-duration-badge'),
    popoverBtnTriggerTransition: document.getElementById('popover-btn-trigger-transition')
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
    if (els.popoverInstall && els.popoverInstall !== exceptPopover) {
      els.popoverInstall.classList.add('hidden');
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
      textAnimation: state.textAnimation,
      styleOptions: state.styleOptions
    };
    localStorage.setItem('sunday_school_taranim_user_settings', JSON.stringify(settings));
  }

  function applyInitialUIState() {
    if (state.selectedFont) {
      document.documentElement.style.setProperty('--font-family', state.selectedFont);
      document.body.style.fontFamily = state.selectedFont;
    }
    if (els.fontSelect) els.fontSelect.value = state.selectedFont;
    if (els.obsFontSizeRange) els.obsFontSizeRange.value = state.fontSize;
    if (els.fontSizeValBadge) els.fontSizeValBadge.textContent = `${state.fontSize}px`;
    if (els.chromaSelect) els.chromaSelect.value = state.chromaKey;
    if (els.presModeSelect) els.presModeSelect.value = state.presentationMode;
    if (els.francoToggleBtn) els.francoToggleBtn.checked = state.francoAutoTranslate;
    if (els.obsTextAnimSelect) els.obsTextAnimSelect.value = state.textAnimation;

    if (els.obsTextColor) els.obsTextColor.value = state.styleOptions.textColor;
    if (els.obsStrokeRange) els.obsStrokeRange.value = state.styleOptions.strokeWidth;
    if (els.obsStrokeColor) els.obsStrokeColor.value = state.styleOptions.strokeColor;
    if (els.obsShadowRange) els.obsShadowRange.value = state.styleOptions.shadowBlur;
    if (els.obsShadowColor) els.obsShadowColor.value = state.styleOptions.shadowColor || '#000000';
    if (els.obsShadowAngleRange) els.obsShadowAngleRange.value = state.styleOptions.shadowAngle !== undefined ? state.styleOptions.shadowAngle : 90;
    if (els.shadowAngleBadge) els.shadowAngleBadge.textContent = `${state.styleOptions.shadowAngle !== undefined ? state.styleOptions.shadowAngle : 90}°`;
    if (els.obsShadowDistanceRange) els.obsShadowDistanceRange.value = state.styleOptions.shadowDistance !== undefined ? state.styleOptions.shadowDistance : 6;
    if (els.shadowDistanceBadge) els.shadowDistanceBadge.textContent = `${state.styleOptions.shadowDistance !== undefined ? state.styleOptions.shadowDistance : 6}px`;

    if (els.obsFontWeightSelect) els.obsFontWeightSelect.value = state.styleOptions.fontWeight;
    if (els.obsLetterSpacingRange) els.obsLetterSpacingRange.value = state.styleOptions.letterSpacing;
    if (els.obsLineHeightRange) els.obsLineHeightRange.value = state.styleOptions.lineHeight;
    if (els.obsBoxBgColor) els.obsBoxBgColor.value = state.styleOptions.boxBgColor;
    if (els.obsBoxOpacityRange) els.obsBoxOpacityRange.value = state.styleOptions.boxOpacity;
    if (els.obsBoxRadiusRange) els.obsBoxRadiusRange.value = state.styleOptions.boxRadius;
    if (els.obsBoxPaddingRange) els.obsBoxPaddingRange.value = state.styleOptions.boxPadding;

    if (els.btnToggleBold) els.btnToggleBold.classList.toggle('active', state.styleOptions.fontStyle === 'bold');
    if (els.btnToggleItalic) els.btnToggleItalic.classList.toggle('active', state.styleOptions.fontStyle === 'italic');
    if (els.btnToggleUnderline) els.btnToggleUnderline.classList.toggle('active', state.styleOptions.textDecoration === 'underline');

    if (els.btnAlignCenter) els.btnAlignCenter.classList.toggle('active', state.styleOptions.textAlign === 'center');
    if (els.btnAlignRight) els.btnAlignRight.classList.toggle('active', state.styleOptions.textAlign === 'right');
    if (els.btnAlignLeft) els.btnAlignLeft.classList.toggle('active', state.styleOptions.textAlign === 'left');
    if (els.btnAlignJustify) els.btnAlignJustify.classList.toggle('active', state.styleOptions.textAlign === 'justify');

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

    if (els.btnMenuInstall && els.popoverInstall) {
      els.btnMenuInstall.addEventListener('click', (e) => {
        e.stopPropagation();
        const willShow = els.popoverInstall.classList.contains('hidden');
        closeAllPopovers(els.popoverInstall);
        if (willShow) {
          els.popoverInstall.classList.remove('hidden');
        } else {
          els.popoverInstall.classList.add('hidden');
        }
      });

      els.popoverInstall.addEventListener('click', (e) => e.stopPropagation());
    }

    if (els.btnDropdownPrecache) {
      els.btnDropdownPrecache.addEventListener('click', async () => {
        els.btnDropdownPrecache.disabled = true;
        if (els.popoverProgressWrapper) els.popoverProgressWrapper.style.display = 'block';

        const ASSETS_TO_CACHE = [
          './',
          './index.html',
          './obs.html',
          './install.html',
          './app.js',
          './style.css',
          './logo.png',
          './manifest.webmanifest',
          './songs_catalog.json',
          './arabic_dictionary.json',
          './playlists.json'
        ];

        let loadedCount = 0;
        const total = ASSETS_TO_CACHE.length;

        try {
          const cache = await caches.open('sunday_school_taranim_v20260729_v16');

          for (let i = 0; i < total; i++) {
            const url = ASSETS_TO_CACHE[i];
            if (els.popoverProgressText) {
              els.popoverProgressText.textContent = `تحميل: ${url.replace('./', '')}...`;
            }
            try {
              const res = await fetch(url, { cache: 'no-cache' });
              if (res && res.ok) {
                await cache.put(url, res);
              }
            } catch (err) {}

            loadedCount++;
            const pct = Math.round((loadedCount / total) * 100);
            if (els.popoverProgressFill) els.popoverProgressFill.style.width = pct + '%';
            if (els.popoverProgressText) {
              els.popoverProgressText.textContent = `تم حفظ ${loadedCount} من ${total} ملفات (${pct}%)`;
            }
          }

          if (els.btnDropdownPrecache) els.btnDropdownPrecache.innerHTML = '<i class="fa-solid fa-circle-check"></i> تم حفظ جميع البيانات أوفلاين';
          if (els.popoverOfflineStatusText) els.popoverOfflineStatusText.textContent = '🟢 تم حفظ الكتالوج والبيانات بالكامل محلياً';
        } catch (err) {
          if (els.btnDropdownPrecache) {
            els.btnDropdownPrecache.disabled = false;
            els.btnDropdownPrecache.textContent = 'إعادة المحاولة';
          }
        }
      });
    }

    if (els.btnDropdownStartOffline) {
      els.btnDropdownStartOffline.addEventListener('click', () => {
        if (els.popoverInstall) {
          els.popoverInstall.classList.add('hidden');
        }
        showToast('تطبيق الترانيم جاهز للعمل أوفلاين بنجاح 🟢');
      });
    }

    let deferredPrompt = null;
    window.addEventListener('beforeinstallprompt', (e) => {
      e.preventDefault();
      deferredPrompt = e;
      if (els.dropdownPwaInstallRow) els.dropdownPwaInstallRow.classList.remove('hidden');
    });

    if (els.btnDropdownPwaInstall) {
      els.btnDropdownPwaInstall.addEventListener('click', async () => {
        if (!deferredPrompt) return;
        deferredPrompt.prompt();
        const { outcome } = await deferredPrompt.userChoice;
        if (outcome === 'accepted') {
          if (els.dropdownPwaInstallRow) els.dropdownPwaInstallRow.classList.add('hidden');
          showToast('تم تثبيت تطبيق الترانيم بنجاح! 🎉');
        }
        deferredPrompt = null;
      });
    }

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
      if (els.popoverInstall && !els.popoverInstall.contains(e.target) && e.target !== els.btnMenuInstall && !els.btnMenuInstall.contains(e.target)) {
        els.popoverInstall.classList.add('hidden');
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

    if (els.obsTextAnimSelect) {
      const handleAnimChange = (e) => {
        const val = e.target.value;
        if (state.textAnimation !== val) {
          state.textAnimation = val;
          saveUserSettings();
          syncLiveState();
        }
      };
      els.obsTextAnimSelect.addEventListener('change', handleAnimChange);
      els.obsTextAnimSelect.addEventListener('input', handleAnimChange);
      els.obsTextAnimSelect.addEventListener('blur', handleAnimChange);
    }

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

    if (els.obsShadowAngleRange) {
      els.obsShadowAngleRange.addEventListener('input', (e) => {
        state.styleOptions.shadowAngle = parseInt(e.target.value);
        if (els.shadowAngleBadge) els.shadowAngleBadge.textContent = `${state.styleOptions.shadowAngle}°`;
        saveUserSettings();
        syncLiveState();
      });
    }

    if (els.obsShadowDistanceRange) {
      els.obsShadowDistanceRange.addEventListener('input', (e) => {
        state.styleOptions.shadowDistance = parseInt(e.target.value);
        if (els.shadowDistanceBadge) els.shadowDistanceBadge.textContent = `${state.styleOptions.shadowDistance}px`;
        saveUserSettings();
        syncLiveState();
      });
    }

    if (els.btnAlignCenter && els.btnAlignRight && els.btnAlignLeft) {
      const setAlign = (align) => {
        state.styleOptions.textAlign = align;
        els.btnAlignCenter.classList.toggle('active', align === 'center');
        els.btnAlignRight.classList.toggle('active', align === 'right');
        els.btnAlignLeft.classList.toggle('active', align === 'left');
        if (els.btnAlignJustify) els.btnAlignJustify.classList.toggle('active', align === 'justify');
        saveUserSettings();
        syncLiveState();
      };
      els.btnAlignCenter.addEventListener('click', () => setAlign('center'));
      els.btnAlignRight.addEventListener('click', () => setAlign('right'));
      els.btnAlignLeft.addEventListener('click', () => setAlign('left'));
      if (els.btnAlignJustify) els.btnAlignJustify.addEventListener('click', () => setAlign('justify'));
    }

    if (els.btnToggleBold) {
      els.btnToggleBold.addEventListener('click', () => {
        state.styleOptions.fontStyle = state.styleOptions.fontStyle === 'bold' ? 'normal' : 'bold';
        els.btnToggleBold.classList.toggle('active', state.styleOptions.fontStyle === 'bold');
        saveUserSettings();
        syncLiveState();
      });
    }

    if (els.btnToggleItalic) {
      els.btnToggleItalic.addEventListener('click', () => {
        state.styleOptions.fontStyle = state.styleOptions.fontStyle === 'italic' ? 'normal' : 'italic';
        els.btnToggleItalic.classList.toggle('active', state.styleOptions.fontStyle === 'italic');
        saveUserSettings();
        syncLiveState();
      });
    }

    if (els.btnToggleUnderline) {
      els.btnToggleUnderline.addEventListener('click', () => {
        state.styleOptions.textDecoration = state.styleOptions.textDecoration === 'underline' ? 'none' : 'underline';
        els.btnToggleUnderline.classList.toggle('active', state.styleOptions.textDecoration === 'underline');
        saveUserSettings();
        syncLiveState();
      });
    }

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

      renderSearchWordSuggestions(query);

      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => {
        performIntelligentSearch(query);
      }, 50);
    });

    els.intelligentSearch.addEventListener('focus', () => {
      if (els.intelligentSearch.value.trim()) {
        renderSearchWordSuggestions(els.intelligentSearch.value);
        performIntelligentSearch(els.intelligentSearch.value);
      }
    });

    els.clearSearchBtn.addEventListener('click', () => {
      els.intelligentSearch.value = '';
      els.clearSearchBtn.classList.add('hidden');
      els.searchDropdown.classList.add('hidden');
      if (els.searchSuggestionsChips) {
        els.searchSuggestionsChips.classList.add('hidden');
        els.searchSuggestionsChips.innerHTML = '';
      }
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

  function getActiveWordAtCursor(input) {
    if (!input) return { word: '', start: 0, end: 0 };
    const text = input.value || '';
    const cursor = input.selectionStart !== null ? input.selectionStart : text.length;

    let start = text.lastIndexOf(' ', cursor - 1);
    if (start === -1) start = 0; else start = start + 1;

    let end = text.indexOf(' ', cursor);
    if (end === -1) end = text.length;

    const word = text.slice(start, end);
    return { word, start, end };
  }

  function renderSearchWordSuggestions(query) {
    if (!els.searchSuggestionsChips) return;
    if (!query || !query.trim()) {
      els.searchSuggestionsChips.classList.add('hidden');
      els.searchSuggestionsChips.innerHTML = '';
      return;
    }

    const { word, start, end } = getActiveWordAtCursor(els.intelligentSearch);
    if (!word || word.trim().length < 2) {
      els.searchSuggestionsChips.classList.add('hidden');
      els.searchSuggestionsChips.innerHTML = '';
      return;
    }

    const cleanWord = word.trim().toLowerCase();
    const suggestions = [];

    if (state.francoAutoTranslate && /[a-z0-9]/i.test(cleanWord)) {
      const rawTrans = francoToArabic(cleanWord);
      if (rawTrans) {
        const corrected = correctWithArabicDictionary(rawTrans, state.arabicDictionary) || rawTrans;
        suggestions.push({ original: cleanWord, text: corrected, type: 'ترجمة' });
      }
    }

    if (state.arabicDictionary && typeof state.arabicDictionary === 'object' && !/[a-z0-9]/i.test(cleanWord)) {
      const normW = normalizeArabic(cleanWord);
      const keys = Object.keys(state.arabicDictionary);
      for (let i = 0; i < keys.length; i++) {
        const k = keys[i];
        if (normalizeArabic(k).startsWith(normW) && k !== cleanWord) {
          suggestions.push({ original: cleanWord, text: k, type: 'مقترح' });
          if (suggestions.length >= 6) break;
        }
      }
    }

    if (suggestions.length === 0) {
      els.searchSuggestionsChips.classList.add('hidden');
      els.searchSuggestionsChips.innerHTML = '';
      return;
    }

    els.searchSuggestionsChips.innerHTML = suggestions.map(s => `
      <button class="suggestion-chip" type="button" data-replacement="${escapeHtml(s.text)}" data-start="${start}" data-end="${end}">
        <i class="fa-solid fa-wand-magic-sparkles"></i> ${escapeHtml(s.text)} <span class="chip-sub">(${s.type})</span>
      </button>
    `).join('');

    els.searchSuggestionsChips.classList.remove('hidden');

    els.searchSuggestionsChips.querySelectorAll('.suggestion-chip').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const rep = btn.dataset.replacement;
        const sPos = parseInt(btn.dataset.start);
        const ePos = parseInt(btn.dataset.end);

        const fullText = els.intelligentSearch.value;
        const newText = fullText.slice(0, sPos) + rep + fullText.slice(ePos);
        els.intelligentSearch.value = newText;

        const newCursor = sPos + rep.length;
        els.intelligentSearch.setSelectionRange(newCursor, newCursor);
        els.intelligentSearch.focus();

        renderSearchWordSuggestions(newText);
        performIntelligentSearch(newText);
      });
    });
  }

  async function detectConnectedScreens() {
    let screens = [];
    if ('getScreenDetails' in window) {
      try {
        screenDetails = await window.getScreenDetails();
        screens = screenDetails.screens || [];
        screenDetails.addEventListener('screenschange', () => {
          renderScreenOptions();
        });
      } catch (err) {
        renderFallbackScreenOptions();
      }
    } else {
      renderFallbackScreenOptions();
    }

    renderScreenOptions();
  }

  function renderScreenOptions() {
    let screens = [];
    if (screenDetails && screenDetails.screens && screenDetails.screens.length > 0) {
      screens = screenDetails.screens;
    } else {
      screens = [
        { label: 'الشاشة الرئيسية (هذا الجهاز)', width: window.screen.width || 1920, height: window.screen.height || 1080, isPrimary: true, val: 'primary' },
        { label: 'شاشة عرض خارجية 2 (TV / البروجيكتور)', width: 1920, height: 1080, isPrimary: false, val: 'external' }
      ];
    }

    if (els.connectedScreensSelect) {
      els.connectedScreensSelect.innerHTML = screens.map((s, idx) => {
        const type = s.isPrimary ? ' (الشاشة الحالية)' : ' (خارجية / TV)';
        const label = s.label || `شاشة ${idx + 1}`;
        const val = s.val !== undefined ? s.val : idx;
        return `<option value="${val}">${escapeHtml(label)} (${s.width} × ${s.height})${type}</option>`;
      }).join('');
    }

    if (els.screensCastList) {
      els.screensCastList.innerHTML = screens.map((s, idx) => {
        const name = s.label || `شاشة ${idx + 1}`;
        const badge = s.isPrimary ? ' (الرئيسية)' : ' (خارجية)';
        const val = s.val !== undefined ? s.val : idx;
        return `
          <div class="screen-cast-card" data-val="${val}">
            <div class="screen-info">
              <span class="screen-name"><i class="fa-solid fa-desktop"></i> ${escapeHtml(name)}${badge}</span>
              <span class="screen-res">${s.width} × ${s.height} px</span>
            </div>
            <i class="fa-solid fa-expand launch-btn-icon"></i>
          </div>
        `;
      }).join('');

      els.screensCastList.querySelectorAll('.screen-cast-card').forEach(card => {
        card.addEventListener('click', () => {
          const val = card.dataset.val;
          if (els.connectedScreensSelect) els.connectedScreensSelect.value = val;
          launchPresenterOnSelectedScreen();
        });
      });
    }
  }

  function renderFallbackScreenOptions() {
    renderScreenOptions();
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
    const qNorm  = normalizeArabic(query);
    const qWords = qNorm.split(/\s+/).filter(w => w.length >= 2);

    let francoHeaderHtml = '';
    if (state.francoAutoTranslate && /[a-z0-9]/i.test(query)) {
      const rawTranslated = francoToArabic(query);
      if (rawTranslated) {
        const corrected = correctWithArabicDictionary(rawTranslated, state.arabicDictionary);
        francoHeaderHtml = `<div class="franco-translation-header"><i class="fa-solid fa-wand-magic-sparkles"></i> الترجمة الحية والمصححة: <strong>${escapeHtml(corrected)}</strong></div>`;
      }
    } else if (query && !/[a-z0-9]/i.test(query)) {
      const correctedAr = correctWithArabicDictionary(qNorm, state.arabicDictionary);
      if (correctedAr && correctedAr !== qNorm) {
        francoHeaderHtml = `<div class="franco-translation-header"><i class="fa-solid fa-wand-magic-sparkles"></i> التصحيح الإملائي المقترح: <strong>${escapeHtml(correctedAr)}</strong></div>`;
      }
    }

    if (!songs || songs.length === 0) {
      els.searchDropdown.innerHTML = francoHeaderHtml + `<div class="search-item no-results-item"><i class="fa-solid fa-circle-exclamation" style="color:#94a3b8; margin-left:6px;"></i><span class="item-title">لم يتم العثور على ترنيمة أو شاهد كتابي</span></div>`;
    } else {
      const itemsHtml = songs.slice(0, 16).map(s => {
        const rawNotes = s.notes || '';
        const allLines = rawNotes.split(/[\n,]+/).map(l => l.trim()).filter(l => l.length > 0);

        // Highlighted title
        const titleHighlighted = highlightMatches(s.title || '', qNorm, qWords);

        // Is the match in the title or lyrics?
        const titleNorm = normalizeArabic(s.title || '');
        const matchIsInTitle = qNorm && (titleNorm.includes(qNorm) || qWords.some(w => titleNorm.includes(w)));
        const matchIsInLyrics = !matchIsInTitle && allLines.length > 0;

        // Build lyric preview snippet
        let snippetHtml = '';
        if (allLines.length > 0) {
          try {
            // Find the best-matching lyric line
            const bestMatch = findBestLyricMatch(allLines, qNorm, qWords);
            const startIdx = bestMatch ? Math.max(0, bestMatch.idx - 1) : 0;
            const previewSlice = allLines.slice(startIdx, startIdx + 3);

            snippetHtml = previewSlice.map((line, idx) => {
              const lineNum = startIdx + idx + 1;
              const isMatchedLine = bestMatch && (startIdx + idx) === bestMatch.idx;
              const formattedLine = matchIsInLyrics && isMatchedLine
                ? highlightMatches(line, qNorm, qWords)
                : escapeHtml(line);
              const numBadge = isMatchedLine
                ? `<span class="line-num-mini matched-line-badge">${lineNum}</span>`
                : `<span class="line-num-mini">${lineNum}</span>`;
              return `${numBadge} ${formattedLine}`;
            }).join('<span class="preview-sep">•</span>');
          } catch (err) {
            snippetHtml = escapeHtml(rawNotes.substring(0, 120));
          }
        }

        // Badge: ترنيمة / شاهد كتابي / matched-in-lyrics
        const typeBadge = s.is_bible
          ? `<span class="item-badge bible-badge"><i class="fa-solid fa-book-open"></i> شاهد كتابي</span>`
          : `<span class="item-badge"><i class="fa-solid fa-music"></i> ترنيمة</span>`;

        const lyricsMatchBadge = matchIsInLyrics
          ? `<span class="item-badge lyrics-match-badge"><i class="fa-solid fa-align-left"></i> كلمات</span>`
          : '';

        return `
          <div class="search-item" data-id="${s.id}">
            <div class="item-top">
              <span class="item-title">${titleHighlighted}</span>
              <div class="item-badges-group">${lyricsMatchBadge}${typeBadge}</div>
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

    const recentMatch = state.sessionRecents.find(r => String(r.id) === String(songId));
    if (recentMatch && (recentMatch.verses || recentMatch.notes)) {
      state.activeSong = recentMatch;
      addToSessionRecents(recentMatch);
      autoToggleObsSceneForRecentItem(songId);
      loadSongIntoPresentation(recentMatch);
      return;
    }

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
      let res = await fetch(`api.php?action=song&id=${songId}`);
      if (!res.ok) res = await fetch(`/api/song/${songId}`);
      if (!res.ok) res = await fetch(`../api.php?action=song&id=${songId}`);
      if (res.ok) {
        const song = await res.json();
        if (song && (song.title || song.id)) {
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
      activeEl.scrollIntoView({ block: 'nearest', behavior: 'auto' });
    }
  }

  function addToSessionRecents(song) {
    if (!song || (!song.id && song.id !== 0)) return;
    const existsIndex = state.sessionRecents.findIndex(r => String(r.id) === String(song.id));
    if (existsIndex !== -1) {
      const [existing] = state.sessionRecents.splice(existsIndex, 1);
      if (song.verses) existing.verses = song.verses;
      if (song.notes) existing.notes = song.notes;
      state.sessionRecents.unshift(existing);
    } else {
      state.sessionRecents.unshift({
        id: song.id,
        title: song.title,
        verses: song.verses || null,
        notes: song.notes || null,
        is_bible: song.is_bible || false
      });
    }
    sessionStorage.setItem('sunday_school_taranim_session_recents', JSON.stringify(state.sessionRecents));
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
  let lastHttpPushTime = 0;
  let pendingHttpPush = null;

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
        fetch('/api.php?action=live', { method: 'POST', headers, body: postBody, keepalive: true })
          .then(res => { if (res.ok) activePostUrl = '/api.php?action=live'; })
          .catch(() => {});
      });
  }

  // THROTTLED HTTP PUSH: fires at most once per 30ms to avoid hammering server
  // BroadcastChannel + localStorage still fire instantly on every call
  function throttledHttpPush(postBody) {
    const now = Date.now();
    if (pendingHttpPush) {
      clearTimeout(pendingHttpPush);
    }
    const delay = Math.max(0, 30 - (now - lastHttpPushTime));
    pendingHttpPush = setTimeout(() => {
      lastHttpPushTime = Date.now();
      pendingHttpPush = null;
      sendLivePayload(postBody);
    }, delay);
  }

  let currentPresenterAnim = null;

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
      anim: state.textAnimation,
      textColor: state.styleOptions.textColor,
      strokeWidth: state.styleOptions.strokeWidth,
      strokeColor: state.styleOptions.strokeColor,
      shadowBlur: state.styleOptions.shadowBlur,
      shadowColor: state.styleOptions.shadowColor || '#000000',
      shadowAngle: state.styleOptions.shadowAngle !== undefined ? state.styleOptions.shadowAngle : 90,
      shadowDistance: state.styleOptions.shadowDistance !== undefined ? state.styleOptions.shadowDistance : 6,
      shadowStyle: state.styleOptions.shadowStyle || 'soft',
      fontWeight: state.styleOptions.fontWeight,
      fontStyle: state.styleOptions.fontStyle,
      textDecoration: state.styleOptions.textDecoration,
      textAlign: state.styleOptions.textAlign,
      letterSpacing: state.styleOptions.letterSpacing,
      lineHeight: state.styleOptions.lineHeight,
      boxBgColor: state.styleOptions.boxBgColor,
      boxOpacity: state.styleOptions.boxOpacity,
      boxRadius: state.styleOptions.boxRadius,
      boxPadding: state.styleOptions.boxPadding
    };

    // ONLY ATTACH POSITION IF IT WAS EXPLICITLY DRAGGED/MODIFIED
    if (isExplicitPositionUpdate) {
      payload.pos = state.dragPivot;
    }

    payload.updatedAt = Date.now();

    // 1. INSTANT LOCAL BROADCAST & STORAGE SYNC (0ms)
    try { broadcastChannel.postMessage(payload); } catch(e) {}
    try { localStorage.setItem('sunday_school_taranim_live_presentation', JSON.stringify(payload)); } catch(e) {}

    // 2. INSTANT DIRECT OBS WEBSOCKET SYNC (~1ms LAN)
    try { obsWsClient.sendLineTextToObsSource(text); } catch(e) {}

    // 3. INSTANT PRESENTER PREVIEW SYNC (0ms)
    if (els.obsLineText) {
      els.obsLineText.style.fontFamily = state.selectedFont;
      els.obsLineText.style.color = state.styleOptions.textColor;
      els.obsLineText.style.fontWeight = state.styleOptions.fontWeight;
      els.obsLineText.style.fontStyle = state.styleOptions.fontStyle === 'bold' ? 'normal' : state.styleOptions.fontStyle;
      els.obsLineText.style.textDecoration = state.styleOptions.textDecoration;
      els.obsLineText.style.textAlign = state.styleOptions.textAlign;
      els.obsLineText.style.letterSpacing = `${state.styleOptions.letterSpacing}px`;
      els.obsLineText.style.lineHeight = state.styleOptions.lineHeight;

      if (state.styleOptions.boxOpacity > 0) {
        const hex = state.styleOptions.boxBgColor || '#000000';
        const r = parseInt(hex.slice(1, 3), 16) || 0;
        const g = parseInt(hex.slice(3, 5), 16) || 0;
        const b = parseInt(hex.slice(5, 7), 16) || 0;
        const alpha = state.styleOptions.boxOpacity / 100;
        els.obsLowerThirdBox.style.background = `rgba(${r}, ${g}, ${b}, ${alpha})`;
      } else {
        els.obsLowerThirdBox.style.background = 'transparent';
      }

      els.obsLowerThirdBox.style.borderRadius = `${state.styleOptions.boxRadius || 12}px`;
      els.obsLowerThirdBox.style.padding = `${state.styleOptions.boxPadding || 20}px`;

      // TEXT FIT: Set initial size immediately, refine on next animation frame to avoid layout-blocking reflow loop
      let size = state.fontSize || 54;
      els.obsLineText.style.fontSize = `${size}px`;

      if (els.obsLineText.textContent !== text || currentPresenterAnim !== state.textAnimation) {
        els.obsLineText.textContent = text;
        currentPresenterAnim = state.textAnimation;

        els.obsLineText.classList.remove('animate-appear-slide', 'animate-appear-drop', 'animate-appear-pop', 'animate-appear-flip', 'animate-appear-glow');
        if (state.textAnimation !== 'none' && text) {
          void els.obsLineText.offsetWidth;
          els.obsLineText.classList.add(`animate-appear-${state.textAnimation}`);
        }
      }

      // Defer text-fit reflow to next rAF so it doesn't block keyboard/click response
      const snapText = text;
      const snapSize = size;
      requestAnimationFrame(() => {
        if (els.obsLineText && els.obsLineText.textContent === snapText) {
          let fitSize = snapSize;
          const maxW = (els.obsOverlay ? els.obsOverlay.clientWidth : window.innerWidth) * 0.90;
          const maxH = (els.obsOverlay ? els.obsOverlay.clientHeight : window.innerHeight) * 0.85;
          while ((els.obsLineText.scrollWidth > maxW || els.obsLineText.scrollHeight > maxH) && fitSize > 18) {
            fitSize -= 2;
            els.obsLineText.style.fontSize = `${fitSize}px`;
          }
        }
      });

      const xPct = state.dragPivot.xPct !== undefined ? state.dragPivot.xPct : 50;
      const yPct = state.dragPivot.yPct !== undefined ? state.dragPivot.yPct : 75;

      els.obsLowerThirdBox.style.left = `${xPct}%`;
      els.obsLowerThirdBox.style.top = `${yPct}%`;
      els.obsLowerThirdBox.style.transform = 'translate(-50%, -50%)';
    }

    // 4. NON-BLOCKING INSTANT SERVER DISPATCH (throttled to 30ms to protect server)
    const postBody = JSON.stringify(payload);
    throttledHttpPush(postBody);
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
      if (els.obsPopoverControls) els.obsPopoverControls.classList.toggle('hidden', !connected);

      if (els.obsSceneSelect) els.obsSceneSelect.disabled = !connected;
      if (els.popoverObsSceneSelect) els.popoverObsSceneSelect.disabled = !connected;
      if (els.obsTransitionSelect) els.obsTransitionSelect.disabled = !connected;
      if (els.popoverObsTransitionSelect) els.popoverObsTransitionSelect.disabled = !connected;
      if (els.obsTransitionDurationRange) els.obsTransitionDurationRange.disabled = !connected;
      if (els.popoverObsDurationRange) els.popoverObsDurationRange.disabled = !connected;
      if (els.btnTriggerObsTransition) els.btnTriggerObsTransition.disabled = !connected;
      if (els.popoverBtnTriggerTransition) els.popoverBtnTriggerTransition.disabled = !connected;

      const isHttps = window.location.protocol === 'https:';

      if (els.obsHttpsWarning && els.obsHttpFallback && els.obsHttpLink) {
        if (isHttps && !connected && statusText && (statusText.includes('تعذر') || statusText.includes('HTTPS') || statusText.includes('لم يتم'))) {
          const ip = els.obsWsIp ? els.obsWsIp.value.trim() || '192.168.1.9' : '192.168.1.9';
          const port = els.obsWsPort ? els.obsWsPort.value || '4455' : '4455';
          const pass = els.obsWsPassword ? els.obsWsPassword.value || '' : '';
          const currentHost = window.location.host;
          const currentPath = window.location.pathname;
          
          const httpUrl = `http://${currentHost}${currentPath}?obs_ip=${encodeURIComponent(ip)}&obs_port=${encodeURIComponent(port)}&obs_password=${encodeURIComponent(pass)}&obs_autoconnect=1`;
          els.obsHttpLink.href = httpUrl;
          els.obsHttpFallback.classList.remove('hidden');
          els.obsHttpsWarning.innerHTML = `⚠️ المتصفح يمنع الاتصال بـ <code>ws://</code> على صفحات HTTPS.<br><strong>انقر زر HTTP بالأسفل للربط التلقائي بـ OBS</strong>`;
          els.obsHttpsWarning.classList.remove('hidden');
        } else if (connected) {
          els.obsHttpsWarning.classList.add('hidden');
          els.obsHttpFallback.classList.add('hidden');
        } else {
          els.obsHttpsWarning.classList.add('hidden');
        }
      }
    },
    onScenesUpdated: (scenes, currentScene) => {
      availableObsScenes = scenes;
      const optionsHtml = scenes.map(s => `<option value="${escapeHtml(s)}" ${s === currentScene ? 'selected' : ''}>${escapeHtml(s)}</option>`).join('');
      if (els.obsSceneSelect) els.obsSceneSelect.innerHTML = optionsHtml;
      if (els.popoverObsSceneSelect) els.popoverObsSceneSelect.innerHTML = optionsHtml;
    },
    onTransitionsUpdated: (transitions, currentTransition) => {
      const optionsHtml = transitions.map(t => `<option value="${escapeHtml(t)}" ${t === currentTransition ? 'selected' : ''}>${escapeHtml(t)}</option>`).join('');
      if (els.obsTransitionSelect) els.obsTransitionSelect.innerHTML = optionsHtml;
      if (els.popoverObsTransitionSelect) els.popoverObsTransitionSelect.innerHTML = optionsHtml;
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

  // AUTO-CONNECT FROM URL QUERY PARAMETERS (E.G. WHEN OPENED VIA HTTP FALLBACK LINK)
  try {
    const urlParams = new URLSearchParams(window.location.search);
    const urlObsIp = urlParams.get('obs_ip');
    const urlObsPort = urlParams.get('obs_port');
    const urlObsPass = urlParams.get('obs_password');
    const urlAutoConnect = urlParams.get('obs_autoconnect') === '1';

    if (urlObsIp) {
      if (els.obsWsIp) els.obsWsIp.value = urlObsIp;
      if (urlObsPort && els.obsWsPort) els.obsWsPort.value = urlObsPort;
      if (urlObsPass !== null && els.obsWsPassword) els.obsWsPassword.value = urlObsPass;

      const conf = {
        ip: urlObsIp,
        port: parseInt(urlObsPort) || 4455,
        password: urlObsPass || ''
      };
      localStorage.setItem('sunday_school_taranim_obs_ws_config', JSON.stringify(conf));

      if (urlAutoConnect || window.location.protocol === 'http:') {
        setTimeout(() => {
          obsWsClient.connect(conf.ip, conf.port, conf.password);
        }, 300);
      }
    }
  } catch(e) {}

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
    els.obsSceneSelect.addEventListener('change', (e) => {
      const val = e.target.value;
      if (els.popoverObsSceneSelect) els.popoverObsSceneSelect.value = val;
      obsWsClient.setCurrentScene(val);
    });
  }

  if (els.popoverObsSceneSelect) {
    els.popoverObsSceneSelect.addEventListener('change', (e) => {
      const val = e.target.value;
      if (els.obsSceneSelect) els.obsSceneSelect.value = val;
      obsWsClient.setCurrentScene(val);
    });
  }

  if (els.obsTransitionSelect) {
    els.obsTransitionSelect.addEventListener('change', (e) => {
      const val = e.target.value;
      if (els.popoverObsTransitionSelect) els.popoverObsTransitionSelect.value = val;
      obsWsClient.setTransition(val);
    });
  }

  if (els.popoverObsTransitionSelect) {
    els.popoverObsTransitionSelect.addEventListener('change', (e) => {
      const val = e.target.value;
      if (els.obsTransitionSelect) els.obsTransitionSelect.value = val;
      obsWsClient.setTransition(val);
    });
  }

  if (els.obsTransitionDurationRange) {
    els.obsTransitionDurationRange.addEventListener('input', (e) => {
      const val = e.target.value;
      if (els.obsTransitionDurationBadge) els.obsTransitionDurationBadge.textContent = `${val}ms`;
      if (els.popoverObsDurationBadge) els.popoverObsDurationBadge.textContent = `${val}ms`;
      if (els.popoverObsDurationRange) els.popoverObsDurationRange.value = val;
      obsWsClient.setTransitionDuration(val);
    });
  }

  if (els.popoverObsDurationRange) {
    els.popoverObsDurationRange.addEventListener('input', (e) => {
      const val = e.target.value;
      if (els.obsTransitionDurationBadge) els.obsTransitionDurationBadge.textContent = `${val}ms`;
      if (els.popoverObsDurationBadge) els.popoverObsDurationBadge.textContent = `${val}ms`;
      if (els.obsTransitionDurationRange) els.obsTransitionDurationRange.value = val;
      obsWsClient.setTransitionDuration(val);
    });
  }

  if (els.btnTriggerObsTransition) {
    els.btnTriggerObsTransition.addEventListener('click', () => obsWsClient.triggerTransition());
  }

  if (els.popoverBtnTriggerTransition) {
    els.popoverBtnTriggerTransition.addEventListener('click', () => obsWsClient.triggerTransition());
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

    s = s.replace(/^[a-z0-9+\-.]+:\/\//i, '');

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
      // Always stop and recreate scanner to avoid "already running" errors
      stopQrScanner();
      html5QrCodeScanner = null;

      els.obsQrModal.classList.remove('hidden');
      if (els.popoverObsWs) els.popoverObsWs.classList.add('hidden');

      // Clear previous messages
      if (els.qrScanResultMsg) {
        els.qrScanResultMsg.classList.add('hidden');
        els.qrScanResultMsg.textContent = '';
      }

      if (window.Html5Qrcode) {
        // Ensure clean DOM container
        const viewport = document.getElementById('qr-reader-viewport');
        if (viewport) viewport.innerHTML = '';

        html5QrCodeScanner = new Html5Qrcode('qr-reader-viewport');
        html5QrCodeScanner.start(
          { facingMode: 'environment' },
          { fps: 10, qrbox: { width: 220, height: 220 } },
          (decodedText) => {
            const parsed = parseObsQrPayload(decodedText);
            if (parsed) {
              if (els.obsWsIp) els.obsWsIp.value = parsed.ip;
              if (els.obsWsPort) els.obsWsPort.value = parsed.port;
              if (els.obsWsPassword) els.obsWsPassword.value = parsed.password;

              showToast('✅ تم مسح بيانات OBS بنجاح! جاري الاتصال...');
              stopQrScanner();
              html5QrCodeScanner = null;
              els.obsQrModal.classList.add('hidden');
              localStorage.setItem('sunday_school_taranim_obs_ws_config', JSON.stringify(parsed));
              obsWsClient.connect(parsed.ip, parsed.port, parsed.password);
            }
          },
          () => {}
        ).catch((err) => {
          if (els.qrScanResultMsg) {
            els.qrScanResultMsg.className = 'qr-status-msg error';
            els.qrScanResultMsg.textContent = 'تعذر فتح الكاميرا. تأكد من السماح بإذن الكاميرا في المتصفح ثم أعد المحاولة.';
            els.qrScanResultMsg.classList.remove('hidden');
          }
        });
      } else {
        if (els.qrScanResultMsg) {
          els.qrScanResultMsg.className = 'qr-status-msg error';
          els.qrScanResultMsg.textContent = 'تعذر تحميل مكتبة QR. تأكد من الاتصال بالإنترنت وأعد تحميل الصفحة.';
          els.qrScanResultMsg.classList.remove('hidden');
        }
      }
    });
  }

  function stopQrScanner() {
    if (html5QrCodeScanner) {
      try {
        html5QrCodeScanner.stop().catch(() => {});
      } catch(e) {}
      html5QrCodeScanner = null;
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
