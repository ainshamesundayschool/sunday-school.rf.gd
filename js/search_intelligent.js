/**
 * ATTENTION FUTURE DEVELOPERS & AI AGENTS:
 * ----------------------------------------------------
 * This file houses the standardized "Intelligent Search" algorithm for this workspace.
 * Any search feature added to this website in the future MUST be intelligent.
 * 
 * HOW TO INTEGRATE (FAST ADD):
 * 1. Link this script in your HTML/PHP:
 *    <script src="/js/search_intelligent.js"></script>
 * 
 * 2. Or, if the script environment is isolated/offline-first, copy all functions below
 *    directly into your page's script tag.
 * 
 * 3. Implement matching and sorting:
 *    const query = 'kirolos'; // Franco or Arabic query
 *    const scored = itemsList.map(item => ({
 *        ...item,
 *        _score: getMatchScore(item, query, [
 *            { val: item.name, weight: 1.0 },
 *            { val: item.phone, weight: 1.1 },
 *            { val: item.church_name, weight: 0.8 }
 *        ])
 *    })).filter(item => item._score > 0)
 *       .sort((a, b) => b._score - a._score);
 * ----------------------------------------------------
 */

function normalizeArabic(text) {
  if (!text) return "";
  return String(text)
    .replace(/[أإآٱ]/g, "ا")
    .replace(/[ىئ]/g, "ي")
    .replace(/ة/g, "ه")
    .replace(/ؤ/g, "و")
    .replace(/[\u064B-\u0652]/g, "") // Remove Harakat
    .toLowerCase()
    .trim();
}

function francoToArabic(text) {
  if (!text) return "";
  let s = text.toLowerCase().trim();
  if (!/[a-z0-9]/.test(s)) return "";

  // Common Franco mappings to Arabic
  const mappings = [
    { franco: "3a", arabic: "عا" },
    { franco: "3i", arabic: "عي" },
    { franco: "3u", arabic: "عو" },
    { franco: "3", arabic: "ع" },
    { franco: "7a", arabic: "حا" },
    { franco: "7i", arabic: "حي" },
    { franco: "7u", arabic: "حو" },
    { franco: "7", arabic: "ح" },
    { franco: "5", arabic: "خ" },
    { franco: "2", arabic: "ء" },
    { franco: "kh", arabic: "خ" },
    { franco: "sh", arabic: "ش" },
    { franco: "th", arabic: "ث" },
    { franco: "gh", arabic: "غ" },
    { franco: "ou", arabic: "و" },
    { franco: "oo", arabic: "و" },
    { franco: "ee", arabic: "ي" },
    { franco: "y", arabic: "ي" },
    { franco: "g", arabic: "ج" },
    { franco: "k", arabic: "ك" },
    { franco: "c", arabic: "ك" },
    { franco: "q", arabic: "ق" },
    { franco: "z", arabic: "ز" },
    { franco: "s", arabic: "س" },
    { franco: "t", arabic: "ت" },
    { franco: "d", arabic: "د" },
    { franco: "r", arabic: "ر" },
    { franco: "f", arabic: "ف" },
    { franco: "l", arabic: "ل" },
    { franco: "m", arabic: "م" },
    { franco: "n", arabic: "ن" },
    { franco: "h", arabic: "ه" },
    { franco: "w", arabic: "و" },
    { franco: "b", arabic: "ب" },
    { franco: "p", arabic: "ب" },
    { franco: "v", arabic: "ف" },
    { franco: "i", arabic: "ي" },
    { franco: "e", arabic: "ي" },
    { franco: "o", arabic: "و" },
    { franco: "u", arabic: "و" },
    { franco: "a", arabic: "ا" }
  ];

  mappings.forEach(m => {
    s = s.split(m.franco).join(m.arabic);
  });
  return s;
}

function arabicToLatin(text) {
  if (!text) return "";
  let s = normalizeArabic(text);
  const mappings = [
    { ar: "ش", lat: "sh" },
    { ar: "خ", lat: "kh" },
    { ar: "ث", lat: "th" },
    { ar: "غ", lat: "gh" },
    { ar: "ج", lat: "g" },
    { ar: "ح", lat: "h" },
    { ar: "ع", lat: "a" },
    { ar: "ء", lat: "a" },
    { ar: "ؤ", lat: "w" },
    { ar: "ئ", lat: "y" },
    { ar: "ة", lat: "h" },
    { ar: "ا", lat: "a" },
    { ar: "ب", lat: "b" },
    { ar: "ت", lat: "t" },
    { ar: "د", lat: "d" },
    { ar: "ر", lat: "r" },
    { ar: "ز", lat: "z" },
    { ar: "س", lat: "s" },
    { ar: "ف", lat: "f" },
    { ar: "ق", lat: "q" },
    { ar: "ك", lat: "k" },
    { ar: "ل", lat: "l" },
    { ar: "م", lat: "m" },
    { ar: "ن", lat: "n" },
    { ar: "ه", lat: "h" },
    { ar: "و", lat: "w" },
    { ar: "ي", lat: "y" }
  ];
  mappings.forEach(m => {
    s = s.split(m.ar).join(m.lat);
  });
  return s;
}

function phoneticClean(str) {
  if (!str) return "";
  let s = str.toLowerCase().replace(/[^a-z]/g, "");
  // Cleans similar sound groupings
  s = s.replace(/oo/g, 'u');
  s = s.replace(/ou/g, 'u');
  s = s.replace(/ee/g, 'i');
  s = s.replace(/y/g, 'i');
  s = s.replace(/ph/g, 'f');
  s = s.replace(/kh/g, 'k');
  s = s.replace(/q/g, 'k');
  s = s.replace(/j/g, 'g');
  s = s.replace(/z/g, 's');
  s = s.replace(/x/g, 'ks');
  s = s.replace(/[aeiouywh]/g, "");
  return s;
}

/**
 * Computes a weighted matching score for an item against a search query.
 * @param {Object} item The object to evaluate.
 * @param {string} query The search query string.
 * @param {Array<Object>} fields Array of objects defining fields to match: { val: string, weight: number }
 * @returns {number} Weighted match score (0 to 110+).
 */
function getMatchScore(item, query, fields = null) {
  if (!item || !query) return 0;
  const qNormalized = normalizeArabic(query);
  const qRaw = query.trim().toLowerCase();
  const qFranco = francoToArabic(query);
  const qLatin = arabicToLatin(query);
  const qPhonetic = phoneticClean(query.includes(" ") ? query : (qLatin || qRaw));

  if (!fields) {
    fields = [
      { val: item.name || item.title || item.label || "", weight: 1.0 }
    ];
  }

  let maxScore = 0;
  fields.forEach(field => {
    if (!field.val) return;
    const target = String(field.val);
    const tNormalized = normalizeArabic(target);
    const tRaw = target.toLowerCase();
    const tLatin = arabicToLatin(target);
    const tPhonetic = phoneticClean(tLatin || tRaw);
    let currentScore = 0;

    if (tRaw === qRaw || tNormalized === qNormalized) currentScore = 100;
    else if (tRaw.startsWith(qRaw) || tNormalized.startsWith(qNormalized)) currentScore = 80;
    else if (tRaw.includes(qRaw) || tNormalized.includes(qNormalized)) currentScore = 60;
    else if (qFranco && tNormalized === qFranco) currentScore = 92;
    else if (qFranco && tNormalized.startsWith(qFranco)) currentScore = 72;
    else if (qFranco && tNormalized.includes(qFranco)) currentScore = 52;
    else if (qLatin && tRaw === qLatin) currentScore = 92;
    else if (qLatin && tRaw.startsWith(qLatin)) currentScore = 72;
    else if (qLatin && tRaw.includes(qLatin)) currentScore = 52;
    else if (tLatin && tLatin === qRaw) currentScore = 90;
    else if (tLatin && tLatin.startsWith(qRaw)) currentScore = 70;
    else if (tLatin && tLatin.includes(qRaw)) currentScore = 50;
    else if (qPhonetic && tPhonetic && tPhonetic === qPhonetic) currentScore = 88;
    else if (qPhonetic && tPhonetic && tPhonetic.startsWith(qPhonetic)) currentScore = 68;
    else if (qPhonetic && tPhonetic && tPhonetic.includes(qPhonetic)) currentScore = 48;
    else {
      let score = 0, queryIdx = 0;
      for (let i = 0; i < tNormalized.length && queryIdx < qNormalized.length; i++) {
        if (tNormalized[i] === qNormalized[queryIdx]) { queryIdx++; score++; }
      }
      if (queryIdx === qNormalized.length) currentScore = (score / tNormalized.length) * 40;

      if (qFranco) {
        let fScore = 0, fIdx = 0;
        for (let i = 0; i < tNormalized.length && fIdx < qFranco.length; i++) {
          if (tNormalized[i] === qFranco[fIdx]) { fIdx++; fScore++; }
        }
        if (fIdx === qFranco.length) {
          currentScore = Math.max(currentScore, (fScore / tNormalized.length) * 38);
        }
      }
    }

    const weighted = currentScore * (field.weight || 1.0);
    if (weighted > maxScore) maxScore = weighted;
  });

  return maxScore;
}
