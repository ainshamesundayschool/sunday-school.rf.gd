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

document.addEventListener('DOMContentLoaded', () => {

  const broadcastChannel = new BroadcastChannel('sunday_school_taranim_obs_channel');
  let screenDetails = null;

  const savedPivotRaw = localStorage.getItem('sunday_school_taranim_drag_pivot');
  let initialPivot = { left: window.innerWidth / 2, top: window.innerHeight * 0.75 };
  if (savedPivotRaw) {
    try { initialPivot = JSON.parse(savedPivotRaw); } catch(e) {}
  }

  const savedSettingsRaw = localStorage.getItem('sunday_school_taranim_user_settings');
  let savedSettings = {};
  if (savedSettingsRaw) {
    try { savedSettings = JSON.parse(savedSettingsRaw); } catch(e) {}
  }

  const state = {
    allSongs: [],
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

    dragPivot: initialPivot
  };

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
    snapGuideV: document.getElementById('snap-guide-v')
  };

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
    window.addEventListener('online', () => {
      loadInitialData();
    });

    els.btnMenuStyle.addEventListener('click', (e) => {
      e.stopPropagation();
      els.popoverCast.classList.add('hidden');
      els.popoverStyle.classList.toggle('hidden');
    });

    els.btnMenuCast.addEventListener('click', (e) => {
      e.stopPropagation();
      els.popoverStyle.classList.add('hidden');
      els.popoverCast.classList.toggle('hidden');
      detectConnectedScreens();
    });

    document.addEventListener('click', (e) => {
      if (!els.popoverStyle.contains(e.target) && e.target !== els.btnMenuStyle) {
        els.popoverStyle.classList.add('hidden');
      }
      if (!els.popoverCast.contains(e.target) && e.target !== els.btnMenuCast) {
        els.popoverCast.classList.add('hidden');
      }
      if (!els.searchDropdown.contains(e.target) && e.target !== els.intelligentSearch) {
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

    els.btnCopyObsUrl.addEventListener('click', () => {
      const obsUrl = `${window.location.origin}/obs.html`;
      navigator.clipboard.writeText(obsUrl);

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
      const type = s.isPrimary ? ' (الشاشة الحالية)' : ' (خارية / TV)';
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
    const select = els.connectedScreensSelect;
    const val = select.value;

    let isExternalScreen = false;
    let targetScreen = null;

    if (val === 'external') {
      isExternalScreen = true;
    } else if (screenDetails && screenDetails.screens) {
      const idx = parseInt(val);
      if (!isNaN(idx) && screenDetails.screens[idx]) {
        targetScreen = screenDetails.screens[idx];
        if (!targetScreen.isPrimary) {
          isExternalScreen = true;
        }
      }
    }

    if (!isExternalScreen) {
      if (els.obsOverlay) {
        els.obsOverlay.classList.remove('hidden');
        const docEl = document.documentElement || els.obsOverlay;
        if (docEl.requestFullscreen) {
          docEl.requestFullscreen().catch(() => {});
        }
      }
    } else {
      let left = window.screen.width;
      let top = 0;
      let width = window.screen.width || 1920;
      let height = window.screen.height || 1080;

      if (targetScreen) {
        left = targetScreen.availLeft;
        top = targetScreen.availTop;
        width = targetScreen.availWidth;
        height = targetScreen.availHeight;
      }

      const features = `left=${left},top=${top},width=${width},height=${height},menubar=no,toolbar=no,location=no,status=no,resizable=yes`;
      const popup = window.open('obs.html?autofs=true', 'SundaySchoolPresenterWindow', features);
      if (popup) {
        popup.focus();
      }
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

      let targetX = state.dragPivot.left + (e.clientX - startX);
      let targetY = state.dragPivot.top + (e.clientY - startY);

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

      state.dragPivot.left = targetX;
      state.dragPivot.top = targetY;

      localStorage.setItem('sunday_school_taranim_drag_pivot', JSON.stringify(state.dragPivot));

      startX = e.clientX;
      startY = e.clientY;

      syncLiveState();
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

  // DYNAMICALLY LOAD CATALOG (LOCAL STATIC OR API) WITH MULTI-PATH FALLBACKS
  async function loadInitialData() {
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

  // FAST ULTRA-ROBUST DUAL HYBRID INTELLIGENT SEARCH ENGINE
  async function performIntelligentSearch(query) {
    if (!query || !query.trim()) {
      els.searchDropdown.classList.add('hidden');
      return;
    }

    const searchTarget = query.trim();
    let songsList = [];

    // 1. Instant local search if catalog loaded
    if (state.allSongs && state.allSongs.length > 0) {
      const qNorm = normalizeArabic(searchTarget);
      const qFranco = francoToArabic(searchTarget);

      songsList = state.allSongs.filter(song => {
        const tNorm = normalizeArabic(song.title || '');
        const nNorm = normalizeArabic(song.notes || '');
        return tNorm.includes(qNorm) || nNorm.includes(qNorm) || (qFranco && (tNorm.includes(qFranco) || nNorm.includes(qFranco)));
      });
    }

    // 2. Fetch API if local catalog empty or short
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

      if (songsList.length === 0 && state.francoAutoTranslate && /[a-z0-9]/i.test(searchTarget)) {
        const arTrans = francoToArabic(searchTarget);
        if (arTrans) {
          try {
            let res = await fetch(`/api/songs?q=${encodeURIComponent(arTrans)}&limit=150`);
            if (res.ok) {
              let data = await res.json();
              if (data && data.songs) songsList = songsList.concat(data.songs);
            }
          } catch (err) {}
        }
      }
    }

    // 3. Deduplicate
    const uniqueMap = new Map();
    songsList.forEach(song => {
      if (song && (song.id !== undefined && song.id !== null)) {
        uniqueMap.set(String(song.id), song);
      }
    });

    const uniqueSongs = Array.from(uniqueMap.values());

    // 4. Score & Sort
    const scored = uniqueSongs.map(song => ({
      ...song,
      _score: getMatchScore(song, query)
    })).sort((a, b) => b._score - a._score);

    renderSearchDropdown(scored, query);
  }

  function renderSearchDropdown(songs, query) {
    let francoHeaderHtml = '';
    if (state.francoAutoTranslate && /[a-z0-9]/i.test(query)) {
      const translated = francoToArabic(query);
      if (translated) {
        francoHeaderHtml = `<div class="franco-translation-header"><i class="fa-solid fa-language"></i> الترجمة الحية: <strong>${escapeHtml(translated)}</strong></div>`;
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

  async function openAndPresentItem(songId) {
    if (!songId) return;

    // 1. INSTANT LOCAL DISPLAY FROM CATALOG (ZERO-LATENCY)
    if (state.allSongs && state.allSongs.length > 0) {
      const localSong = state.allSongs.find(s => String(s.id) === String(songId));
      if (localSong) {
        state.activeSong = localSong;
        addToSessionRecents(localSong);
        loadSongIntoPresentation(localSong);
        return;
      }
    }

    // 2. FALLBACK TO SERVER API
    try {
      const res = await fetch(`/api/song/${songId}`);
      if (res.ok) {
        const song = await res.json();
        if (song && song.title) {
          state.activeSong = song;
          addToSessionRecents(song);
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

    // Mode A: Verses Array (Structured slides and lines)
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

    // Mode B: Plain Notes Fallback String
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
      <div class="recent-item ${state.activeSong && state.activeSong.id === r.id ? 'active' : ''}" data-id="${r.id}" data-index="${idx}" draggable="true">
        <div class="recent-title-group">
          <i class="fa-solid fa-grip-vertical drag-handle-icon" title="سحب لإعادة الترتيب"></i>
          <span class="recent-title">${escapeHtml(r.title)}</span>
        </div>
        <button class="delete-recent-btn" data-index="${idx}" title="حذف الترنيمة من القائمة">
          <i class="fa-solid fa-trash-can"></i> حذف
        </button>
      </div>
    `).join('');

    els.recentSessionContainer.querySelectorAll('.delete-recent-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const index = parseInt(btn.dataset.index);
        state.sessionRecents.splice(index, 1);
        sessionStorage.setItem('sunday_school_taranim_session_recents', JSON.stringify(state.sessionRecents));
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

  function syncLiveState() {
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
      shadowBlur: state.styleOptions.shadowBlur,
      pos: state.dragPivot
    };

    broadcastChannel.postMessage(payload);
    localStorage.setItem('sunday_school_taranim_live_presentation', JSON.stringify(payload));
    localStorage.setItem('sunday_school_taranim_drag_pivot', JSON.stringify(state.dragPivot));

    fetch('/api/live', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).catch(() => {});

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
      
      els.obsLowerThirdBox.style.left = `${state.dragPivot.left}px`;
      els.obsLowerThirdBox.style.top = `${state.dragPivot.top}px`;
      els.obsLowerThirdBox.style.transform = 'translate(-50%, -50%)';
    }
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
});
