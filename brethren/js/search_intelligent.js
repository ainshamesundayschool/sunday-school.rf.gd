/**
 * Standardized Intelligent Search Algorithm for Brethren Platform
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

function getMatchScore(item, query, matchFields) {
  if (!query || !item) return 0;
  const qNorm = normalizeArabic(query);
  const qFranco = francoToArabic(query);
  if (!qNorm && !qFranco) return 0;

  let totalScore = 0;

  matchFields.forEach(field => {
    const rawVal = field.val;
    if (!rawVal) return;
    const weight = field.weight || 1.0;
    const valNorm = normalizeArabic(rawVal);

    if (valNorm === qNorm) {
      totalScore += 100 * weight;
    } else if (valNorm.startsWith(qNorm)) {
      totalScore += 80 * weight;
    } else if (valNorm.includes(qNorm)) {
      totalScore += 50 * weight;
    }

    if (qFranco) {
      if (valNorm.startsWith(qFranco)) {
        totalScore += 60 * weight;
      } else if (valNorm.includes(qFranco)) {
        totalScore += 35 * weight;
      }
    }
  });

  return totalScore;
}
