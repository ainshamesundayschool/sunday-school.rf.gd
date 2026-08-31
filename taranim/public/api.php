<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// MYSQL CONFIGURATION (INFINITYFREE / ONLINE SERVER)
$mysqlHost = 'sql311.infinityfree.com';
$mysqlPort = 3306;
$mysqlDb   = 'if0_42112851_taranim';
$mysqlUser = 'if0_42112851';
$mysqlPass = 'MwfgtlTqep1';

$sqlitePath = __DIR__ . '/database.sqlite';
$liveFile   = __DIR__ . '/live.json';

$requestUri = $_SERVER['REQUEST_URI'];
$parsedUrl  = parse_url($requestUri, PHP_URL_PATH);

// LIVE PRESENTATION STATE SYNC ENDPOINT (INSTANT 0.001s RESPONSE WITHOUT WAITING FOR DATABASE)
if (strpos($parsedUrl, '/api/live') !== false || (isset($_GET['action']) && $_GET['action'] === 'live') || isset($_GET['live'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $inputRaw = file_get_contents('php://input');
        if (!empty($inputRaw)) {
            file_put_contents($liveFile, $inputRaw, LOCK_EX);
        }
        echo json_encode(['status' => 'success']);
        exit;
    } else {
        if (file_exists($liveFile) && filesize($liveFile) > 0) {
            echo file_get_contents($liveFile);
        } else {
            echo json_encode(['type' => 'PRESENT_LINE', 'text' => '', 'isBlank' => true]);
        }
        exit;
    }
}

// TEMPLATES DISCOVERY ENDPOINT
if (isset($_GET['action']) && $_GET['action'] === 'templates') {
    $jsonFile = file_exists(__DIR__ . '/templates.json') ? __DIR__ . '/templates.json' : (file_exists(__DIR__ . '/../templates.json') ? __DIR__ . '/../templates.json' : null);
    if ($jsonFile) {
        header('Content-Type: application/json; charset=utf-8');
        readfile($jsonFile);
        exit;
    }
    $baseDir = __DIR__ . '/Templates';
    $prefix = 'Templates/';
    if (!is_dir($baseDir) && is_dir(__DIR__ . '/../Templates')) {
        $baseDir = __DIR__ . '/../Templates';
        $prefix = '../Templates/';
    }
    $templates = [];
    $templates[] = [
        'id' => 'tmpl-shabahak-akon-2026',
        'name' => 'Shabahak Akon 2026',
        'category' => 'المؤتمرات',
        'categoryKey' => 'conferences',
        'desc' => 'حزمة مؤتمر شبابك أكون 2026 الكاملة: فيديو انتظار لوب + خلفية شرائح متمركزة + انتقال ستنجر بقناة ألفا.',
        'standby' => $prefix . 'Standby/Shabahak Akon 2026/Shabahak Akoon Loop.mp4',
        'slidesBg' => $prefix . 'SlidesBg/Shabahak Akon 2026/Shabahak Akoon Loop Empty Centered.mp4',
        'stringer' => $prefix . 'Stringer/Shabahak Akon 2026/Stringer 1.webm',
        'thumbnailType' => 'video',
        'thumbnailUrl' => $prefix . 'Standby/Shabahak Akon 2026/Shabahak Akoon Loop.mp4'
    ];

    $groupedFolders = [];
    if (is_dir($baseDir)) {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir, RecursiveDirectoryIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $ext = strtolower($file->getExtension());
                $filename = $file->getFilename();
                if (substr($filename, 0, 1) === '.' || $ext === 'xmp') continue;

                if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $rel = str_replace('\\', '/', substr($file->getPathname(), strlen(__DIR__ . '/')));
                    $nameNoExt = pathinfo($filename, PATHINFO_FILENAME);
                    $parentDir = basename(dirname($file->getPathname()));
                    $cleanVarName = trim(preg_replace('/^Paint\\s+Sweeps\\s+/i', '', $nameNoExt));
                    if (empty($cleanVarName)) $cleanVarName = $nameNoExt;

                    if (!isset($groupedFolders[$parentDir])) {
                        $groupedFolders[$parentDir] = [];
                    }
                    $groupedFolders[$parentDir][] = [
                        'name' => $cleanVarName,
                        'url' => $rel
                    ];
                }
            }
        }
    }

    foreach ($groupedFolders as $folderName => $vars) {
        $firstUrl = !empty($vars) ? $vars[0]['url'] : '';
        $templates[] = [
            'id' => 'tmpl-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', strtolower($folderName)),
            'name' => $folderName . ' (Paint Splash)',
            'category' => 'خلفيات وسلايدات',
            'categoryKey' => 'backgrounds',
            'desc' => 'مجموعة خلفيات وسلايدات فنية مميزة (' . $folderName . ') بألوان وتدرجات متنوعة فائقة الدقة لعرض الترانيم.',
            'thumbnailType' => 'image',
            'thumbnailUrl' => $firstUrl,
            'standby' => $firstUrl,
            'slidesBg' => $firstUrl,
            'varieties' => $vars
        ];
    }
    echo json_encode(['status' => 'success', 'templates' => $templates], JSON_UNESCAPED_UNICODE);
    exit;
}

$pdo = null;
$isMysql = false;

// 1. TRY MYSQL FIRST
try {
    $pdo = new PDO("mysql:host=$mysqlHost;port=$mysqlPort;dbname=$mysqlDb;charset=utf8mb4", $mysqlUser, $mysqlPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_TIMEOUT => 3,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);
    $isMysql = true;
} catch (PDOException $e) {
    // 2. FALLBACK TO SQLITE FOR LOCAL DESKTOP USE
    try {
        $pdo = new PDO('sqlite:' . $sqlitePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $isMysql = false;
    } catch (PDOException $ex) {
        // Continue even if database connection is pending
    }
}

function fetchExternalUrl($url) {
    if (function_exists('curl_init')) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 8);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_USERAGENT, 'SundaySchoolTaranim/2.0');
        $output = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        @curl_close($ch);
        if ($httpCode === 200 && !empty($output)) {
            return $output;
        }
    }
    $opts = [
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: SundaySchoolTaranim/2.0\r\n",
            'timeout' => 8
        ],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
    ];
    $context = stream_context_create($opts);
    return @file_get_contents($url, false, $context);
}

// SILENT BACKGROUND SYNC WITH ONLINE TASBE7NA REPOSITORY & LOCAL CATALOG
function syncOnlineTasbe7naDatabase($pdo, $force = false) {
    if (!$pdo) return;
    $syncLockFile = __DIR__ . '/.tasbe7na_sync.lock';
    if (!$force && file_exists($syncLockFile) && (time() - filemtime($syncLockFile) < 14400)) {
        return;
    }

    $datasets = [];

    // 1. Local catalog on server disk (11,611+ taranim)
    $localFile = __DIR__ . '/songs_catalog.json';
    if (file_exists($localFile)) {
        $localContent = @file_get_contents($localFile);
        if ($localContent) {
            $parsedLocal = json_decode($localContent, true);
            if (is_array($parsedLocal) && count($parsedLocal) > 0) {
                $datasets[] = $parsedLocal;
            }
        }
    }

    // 2. Online Tasbe7na repositories
    $sourceUrls = [
        'https://raw.githubusercontent.com/josephwasily/TasbehnaToOpenLyrics/main/tasbe7naDB.json',
        'https://raw.githubusercontent.com/josephwasily/TasbehnaToOpenLyrics/master/tasbe7naDB.json',
        'https://raw.githubusercontent.com/ainshamesundayschool/sunday-school.rf.gd/main/taranim/songs_catalog.json'
    ];

    foreach ($sourceUrls as $url) {
        $res = fetchExternalUrl($url);
        if ($res) {
            $testData = json_decode($res, true);
            if (is_array($testData) && count($testData) > 0) {
                $datasets[] = $testData;
            }
        }
    }

    if (!empty($datasets)) {
        try {
            $checkStmt  = $pdo->prepare("SELECT id FROM songs WHERE title = :title");
            $insertSong = $pdo->prepare("INSERT INTO songs (item_id, title, language, notes) VALUES (:itemId, :title, 1, :notes)");
            $updateNotes = $pdo->prepare("UPDATE songs SET notes = :notes WHERE id = :id");
            $insertItem = null;
            try {
                $insertItem = $pdo->prepare("INSERT INTO items (item_id, type) VALUES (:itemId, 0)");
            } catch (Exception $e) {}

            $addedCount = 0;
            $updatedCount = 0;

            foreach ($datasets as $data) {
                foreach ($data as $song) {
                    $rawTitle = isset($song['title']) ? trim($song['title']) : (isset($song['name']) ? trim($song['name']) : '');
                    if (empty($rawTitle)) continue;

                    $songNotes = '';
                    if (isset($song['notes']) && !empty($song['notes'])) {
                        $songNotes = is_string($song['notes']) ? $song['notes'] : json_encode($song['notes'], JSON_UNESCAPED_UNICODE);
                    } else if (isset($song['text']) && !empty($song['text'])) {
                        $songNotes = $song['text'];
                    } else if (isset($song['verses']) && is_array($song['verses'])) {
                        $verseTexts = [];
                        foreach ($song['verses'] as $v) {
                            if (isset($v['slides']) && is_array($v['slides'])) {
                                foreach ($v['slides'] as $sl) {
                                    if (isset($sl['lines']) && is_array($sl['lines'])) {
                                        $verseTexts[] = implode("\n", $sl['lines']);
                                    } else if (isset($sl['text'])) {
                                        $verseTexts[] = $sl['text'];
                                    }
                                }
                            }
                        }
                        $songNotes = implode("\n\n", $verseTexts);
                    }

                    $checkStmt->execute([':title' => $rawTitle]);
                    $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$existing) {
                        $newItemId = isset($song['id']) ? intval($song['id']) : (isset($song['item_id']) ? intval($song['item_id']) : rand(900000, 999999));
                        $insertSong->execute([
                            ':itemId' => $newItemId,
                            ':title'  => $rawTitle,
                            ':notes'  => $songNotes
                        ]);
                        if ($insertItem) {
                            try { $insertItem->execute([':itemId' => $newItemId]); } catch (Exception $ex) {}
                        }
                        $addedCount++;
                    } else if (empty($existing['notes']) && !empty($songNotes)) {
                        $updateNotes->execute([':notes' => $songNotes, ':id' => $existing['id']]);
                        $updatedCount++;
                    }
                }
            }
            @touch($syncLockFile);
            return ['added' => $addedCount, 'updated' => $updatedCount];
        } catch (Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }
    return ['added' => 0, 'updated' => 0];
}

function normalizeArabic($text) {
    if (empty($text)) return '';
    $text = preg_replace('/[أإآٱ]/u', 'ا', $text);
    $text = preg_replace('/[ىئ]/u', 'ي', $text);
    $text = preg_replace('/ة/u', 'ه', $text);
    $text = preg_replace('/ؤ/u', 'و', $text);
    $text = preg_replace('/[\x{064B}-\x{0652}]/u', '', $text);
    return trim(mb_strtolower($text, 'UTF-8'));
}

if (isset($_GET['action']) && ($_GET['action'] === 'sync' || $_GET['action'] === 'force_sync')) {
    // Clean duplicate rows if any exist in remote database
    if ($pdo) {
        try {
            $pdo->exec("DELETE s1 FROM songs s1 INNER JOIN songs s2 WHERE s1.id > s2.id AND s1.title = s2.title");
        } catch (Exception $e) {}
    }

    $res = syncOnlineTasbe7naDatabase($pdo, true);
    $totalCount = 11611;
    if ($pdo) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM songs");
            $c = (int)$stmt->fetchColumn();
            if ($c > 0 && $c <= 11650) $totalCount = $c;
        } catch (Exception $e) {}
    }
    echo json_encode([
        'status' => 'success',
        'message' => 'تمت مزامنة الترانيم بنجاح!',
        'syncResult' => $res,
        'total_songs' => $totalCount
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if ($pdo && rand(1, 3) === 1) {
    syncOnlineTasbe7naDatabase($pdo);
}

// SEARCH SONGS ENDPOINT
if (strpos($parsedUrl, '/api/songs') !== false || (isset($_GET['action']) && $_GET['action'] === 'songs') || isset($_GET['q'])) {
    if (!$pdo) {
        echo json_encode(['songs' => [], 'total_songs' => 11611]);
        exit;
    }

    $q      = isset($_GET['q']) ? trim($_GET['q']) : '';
    $limit  = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    $songs = [];
    $bibleChapters = [];

    if (!empty($q)) {
        $qNorm = normalizeArabic($q);
        
        $sql = "
            SELECT s.id, s.item_id, s.title, s.media_url, 
                   COALESCE(GROUP_CONCAT(DISTINCT sg.content), s.notes) as notes,
                   sc.scale as scale_id
            FROM songs s
            LEFT JOIN song_scales sc ON sc.song = s.id
            LEFT JOIN verses v ON v.item_id = s.item_id
            LEFT JOIN slides sl ON sl.verse = v.id
            LEFT JOIN segments sg ON sg.slide = sl.id
            WHERE s.title LIKE :q OR sg.content LIKE :q OR s.notes LIKE :q OR s.title LIKE :qNorm OR sg.content LIKE :qNorm OR s.notes LIKE :qNorm
            GROUP BY s.id
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset . "
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':q', '%' . $q . '%', PDO::PARAM_STR);
        $stmt->bindValue(':qNorm', '%' . $qNorm . '%', PDO::PARAM_STR);
        $stmt->execute();
        $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // SEARCH BIBLE CHAPTERS TABLE AS WELL
        try {
            $qClean = preg_replace('/[٠]/u', '0', $q);
            $qClean = preg_replace('/[١]/u', '1', $qClean);
            $qClean = preg_replace('/[٢]/u', '2', $qClean);
            $qClean = preg_replace('/[٣]/u', '3', $qClean);
            $qClean = preg_replace('/[٤]/u', '4', $qClean);
            $qClean = preg_replace('/[٥]/u', '5', $qClean);
            $qClean = preg_replace('/[٦]/u', '6', $qClean);
            $qClean = preg_replace('/[٧]/u', '7', $qClean);
            $qClean = preg_replace('/[٨]/u', '8', $qClean);
            $qClean = preg_replace('/[٩]/u', '9', $qClean);

            $bookNum = '';
            $bookText = '';
            $chNum = null;

            if (preg_match('/^([123])?\s*([^\d]+?)\s*(\d+)$/u', trim($qClean), $m)) {
                $bookNum = $m[1];
                $bookText = trim($m[2]);
                $chNum = (int)$m[3];
            } else {
                $bookText = trim(preg_replace('/\d+/', '', $qClean));
                if (preg_match('/(\d+)/', $qClean, $nm)) {
                    $chNum = (int)$nm[1];
                }
            }

            $cleanText = trim(preg_replace('/^(سفر|إنجيل|انجيل|رسالة|رساله)\s+/iu', '', $bookText));
            $textWithAl = 'ال' . preg_replace('/^ال/iu', '', $cleanText);
            $textNoAl = preg_replace('/^ال/iu', '', $cleanText);

            $q1 = '%' . $cleanText . '%';
            $q2 = '%' . $textWithAl . '%';
            $q3 = '%' . $textNoAl . '%';

            if ($chNum !== null) {
                $bStmt = $pdo->prepare("
                    SELECT c.id, c.item_id, 
                           (b.title || ' - الأصحاح ' || bc.number) as title,
                           b.title as book_title,
                           bc.number as chapter_number,
                           1 as is_bible
                    FROM chapters c
                    JOIN bible_chapters bc ON c.bible_chapter = bc.id
                    JOIN books b ON bc.book = b.id
                    WHERE (
                      b.title LIKE :q1 OR b.abbr LIKE :q1
                      OR b.title LIKE :q2 OR b.abbr LIKE :q2
                      OR b.title LIKE :q3 OR b.abbr LIKE :q3
                    )
                    AND bc.number = :chNum
                    LIMIT 15
                ");
                $bStmt->bindValue(':q1', $q1, PDO::PARAM_STR);
                $bStmt->bindValue(':q2', $q2, PDO::PARAM_STR);
                $bStmt->bindValue(':q3', $q3, PDO::PARAM_STR);
                $bStmt->bindValue(':chNum', $chNum, PDO::PARAM_INT);
                $bStmt->execute();
                $bibleChapters = $bStmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $bStmt = $pdo->prepare("
                    SELECT c.id, c.item_id, 
                           (b.title || ' - الأصحاح ' || bc.number) as title,
                           b.title as book_title,
                           bc.number as chapter_number,
                           1 as is_bible
                    FROM chapters c
                    JOIN bible_chapters bc ON c.bible_chapter = bc.id
                    JOIN books b ON bc.book = b.id
                    WHERE (
                      b.title LIKE :q1 OR b.abbr LIKE :q1
                      OR b.title LIKE :q2 OR b.abbr LIKE :q2
                      OR b.title LIKE :q3 OR b.abbr LIKE :q3
                    )
                    LIMIT 15
                ");
                $bStmt->bindValue(':q1', $q1, PDO::PARAM_STR);
                $bStmt->bindValue(':q2', $q2, PDO::PARAM_STR);
                $bStmt->bindValue(':q3', $q3, PDO::PARAM_STR);
                $bStmt->execute();
                $bibleChapters = $bStmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (Exception $ex) {}

    } else {
        $sql = "
            SELECT s.id, s.item_id, s.title, s.media_url, s.notes, sc.scale as scale_id
            FROM songs s
            LEFT JOIN song_scales sc ON sc.song = s.id
            GROUP BY s.id
            ORDER BY s.id ASC
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset . "
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    $allResults = array_merge($bibleChapters, $songs);

    $totalCount = 11611;
    try {
        $cStmt = $pdo->query("SELECT COUNT(*) FROM songs");
        $c = (int)$cStmt->fetchColumn();
        if ($c > 0 && $c <= 11650) $totalCount = $c;
    } catch (Exception $e) {}

    echo json_encode(['songs' => $allResults, 'total' => count($allResults), 'total_songs' => $totalCount, 'db_type' => $isMysql ? 'mysql' : 'sqlite']);
    exit;
}
// GET BIBLE CHAPTER ID BY BOOK ID AND CHAPTER NUMBER
if (isset($_GET['action']) && $_GET['action'] === 'bible_chapter') {
    if (!$pdo) {
        echo json_encode(['error' => 'No database']);
        exit;
    }
    $bookId = isset($_GET['book_id']) ? (int)$_GET['book_id'] : 0;
    $chNum = isset($_GET['chapter']) ? (int)$_GET['chapter'] : 0;

    if ($bookId > 0 && $chNum > 0) {
        try {
            $titleConcatSql = $isMysql ? "CONCAT(b.title, ' - الأصحاح ', bc.number)" : "(b.title || ' - الأصحاح ' || bc.number)";
            $stmt = $pdo->prepare("
                SELECT c.id, c.item_id, {$titleConcatSql} as title, 1 as is_bible
                FROM chapters c
                JOIN bible_chapters bc ON c.bible_chapter = bc.id
                JOIN books b ON bc.book = b.id
                WHERE (bc.book = :bookId OR b.id = :bookId) AND bc.number = :chNum
                LIMIT 1
            ");
            $stmt->bindValue(':bookId', $bookId, PDO::PARAM_INT);
            $stmt->bindValue(':chNum', $chNum, PDO::PARAM_INT);
            $stmt->execute();
            $chapter = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($chapter) {
                $vStmt = $pdo->prepare("SELECT id, type FROM verses WHERE item_id = :itemId OR item_id = :cId ORDER BY id ASC");
                $vStmt->bindValue(':itemId', $chapter['item_id'], PDO::PARAM_INT);
                $vStmt->bindValue(':cId', $chapter['id'], PDO::PARAM_INT);
                $vStmt->execute();
                $verses = $vStmt->fetchAll(PDO::FETCH_ASSOC);

                $versesData = [];
                foreach ($verses as $verse) {
                    $sStmt = $pdo->prepare("SELECT id, heading FROM slides WHERE verse = :vid ORDER BY id ASC");
                    $sStmt->bindValue(':vid', $verse['id'], PDO::PARAM_INT);
                    $sStmt->execute();
                    $slides = $sStmt->fetchAll(PDO::FETCH_ASSOC);

                    $slidesData = [];
                    foreach ($slides as $slide) {
                        $lineStmt = $pdo->prepare("SELECT id, content FROM segments WHERE slide = :sid ORDER BY id ASC");
                        $lineStmt->bindValue(':sid', $slide['id'], PDO::PARAM_INT);
                        $lineStmt->execute();
                        $segRows = $lineStmt->fetchAll(PDO::FETCH_ASSOC);

                        $lines = [];
                        foreach ($segRows as $seg) {
                            $lines[] = trim($seg['content']);
                        }

                        $slidesData[] = [
                            'id' => $slide['id'],
                            'heading' => $slide['heading'],
                            'lines' => $lines,
                            'text' => implode("\n", array_filter($lines))
                        ];
                    }

                    $versesData[] = [
                        'id' => $verse['id'],
                        'type' => $verse['type'],
                        'slides' => $slidesData
                    ];
                }

                $chapter['verses'] = $versesData;
                echo json_encode($chapter);
                exit;
            }
        } catch (Exception $ex) {}
    }
    http_response_code(404);
    echo json_encode(['error' => 'Chapter not found']);
    exit;
}

// GET SINGLE SONG OR BIBLE CHAPTER BY ID ENDPOINT
if (preg_match('#/api/song/(\d+)#', $parsedUrl, $matches) || (isset($_GET['action']) && $_GET['action'] === 'song')) {
    if (!$pdo) {
        echo json_encode(['error' => 'No database']);
        exit;
    }
    $songId = isset($matches[1]) ? (int)$matches[1] : (int)$_GET['id'];
    $isBibleReq = (isset($_GET['type']) && $_GET['type'] === 'bible') || (isset($_GET['is_bible']) && ($_GET['is_bible'] == '1' || $_GET['is_bible'] === 'true'));

    $song = null;
    $titleConcatSql = $isMysql ? "CONCAT(b.title, ' - الأصحاح ', bc.number)" : "(b.title || ' - الأصحاح ' || bc.number)";

    if ($isBibleReq) {
        try {
            $cStmt = $pdo->prepare("
                SELECT c.id, c.item_id, {$titleConcatSql} as title, b.abbr, bc.number as chapter_number, 1 as is_bible
                FROM chapters c
                JOIN bible_chapters bc ON c.bible_chapter = bc.id
                JOIN books b ON bc.book = b.id
                WHERE c.id = :id OR c.item_id = :id
            ");
            $cStmt->bindValue(':id', $songId, PDO::PARAM_INT);
            $cStmt->execute();
            $song = $cStmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $ex) {}
    }

    if (!$song) {
        $stmt = $pdo->prepare("SELECT s.*, sc.scale as scale_id FROM songs s LEFT JOIN song_scales sc ON sc.song = s.id WHERE s.id = :id OR s.item_id = :id");
        $stmt->bindValue(':id', $songId, PDO::PARAM_INT);
        $stmt->execute();
        $song = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    if (!$song) {
        try {
            $cStmt = $pdo->prepare("
                SELECT c.id, c.item_id, {$titleConcatSql} as title, b.abbr, bc.number as chapter_number, 1 as is_bible
                FROM chapters c
                JOIN bible_chapters bc ON c.bible_chapter = bc.id
                JOIN books b ON bc.book = b.id
                WHERE c.id = :id OR c.item_id = :id
            ");
            $cStmt->bindValue(':id', $songId, PDO::PARAM_INT);
            $cStmt->execute();
            $song = $cStmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $ex) {}
    }

    if (!$song) {
        http_response_code(404);
        echo json_encode(['error' => 'Song not found']);
        exit;
    }

    // Fetch all repetitions for this item from database
    $repMap = [];
    try {
        $repStmt = $pdo->prepare("
            SELECT r.start_segment, r.end_segment, r.opening_position, r.closing_position, r.repetitions
            FROM repetitions r
            JOIN segments sg ON sg.id = r.start_segment
            JOIN slides sl ON sl.id = sg.slide
            JOIN verses v ON v.id = sl.verse
            WHERE v.item_id = :itemId
        ");
        $repStmt->bindValue(':itemId', $song['item_id'], PDO::PARAM_INT);
        $repStmt->execute();
        $repRows = $repStmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($repRows as $rRow) {
            $sId = (int)$rRow['start_segment'];
            $eId = (int)$rRow['end_segment'];
            $op  = (int)$rRow['opening_position'];
            $cp  = (int)$rRow['closing_position'];
            $cnt = (int)$rRow['repetitions'];

            if (!isset($repMap[$sId])) $repMap[$sId] = ['starts' => [], 'ends' => []];
            if (!isset($repMap[$eId])) $repMap[$eId] = ['starts' => [], 'ends' => []];

            $repMap[$sId]['starts'][] = ['pos' => $op, 'cnt' => $cnt];
            $repMap[$eId]['ends'][] = ['pos' => $cp, 'cnt' => $cnt];
        }
    } catch (Exception $ex) {}

    $vStmt = $pdo->prepare("SELECT id, type FROM verses WHERE item_id = :itemId ORDER BY id ASC");
    $vStmt->bindValue(':itemId', $song['item_id'], PDO::PARAM_INT);
    $vStmt->execute();
    $verses = $vStmt->fetchAll(PDO::FETCH_ASSOC);

    $versesData = [];
    foreach ($verses as $verse) {
        $sStmt = $pdo->prepare("SELECT id, heading FROM slides WHERE verse = :vid ORDER BY id ASC");
        $sStmt->bindValue(':vid', $verse['id'], PDO::PARAM_INT);
        $sStmt->execute();
        $slides = $sStmt->fetchAll(PDO::FETCH_ASSOC);

        $slidesData = [];
        foreach ($slides as $slide) {
            $lineStmt = $pdo->prepare("SELECT id, content FROM segments WHERE slide = :sid ORDER BY id ASC");
            $lineStmt->bindValue(':sid', $slide['id'], PDO::PARAM_INT);
            $lineStmt->execute();
            $segRows = $lineStmt->fetchAll(PDO::FETCH_ASSOC);

            $lines = [];
            foreach ($segRows as $seg) {
                $segId = (int)$seg['id'];
                $origTxt = trim($seg['content']);
                $txt = $origTxt;
                $prefix = '';
                $suffix = '';
                if (isset($repMap[$segId])) {
                    if (!empty($repMap[$segId]['starts'])) {
                        foreach ($repMap[$segId]['starts'] as $st) {
                            $pos = $st['pos'];
                            if ($pos > 0 && $pos < mb_strlen($origTxt)) {
                                $txt = mb_substr($origTxt, 0, $pos) . '(' . mb_substr($origTxt, $pos);
                            } else {
                                $prefix .= '(';
                            }
                        }
                    }
                    if (!empty($repMap[$segId]['ends'])) {
                        foreach ($repMap[$segId]['ends'] as $en) {
                            $pos = $en['pos'];
                            $cnt = $en['cnt'];
                            if ($pos > 0 && $pos < mb_strlen($origTxt)) {
                                $txt = mb_substr($origTxt, 0, $pos) . ')' . $cnt . mb_substr($origTxt, $pos);
                            } else {
                                $suffix .= ')' . $cnt;
                            }
                        }
                    }
                }
                $lines[] = $prefix . $txt . $suffix;
            }

            $slidesData[] = [
                'id' => $slide['id'],
                'heading' => $slide['heading'],
                'lines' => $lines,
                'text' => implode("\n", array_filter($lines))
            ];
        }

        $versesData[] = [
            'id' => $verse['id'],
            'type' => $verse['type'],
            'slides' => $slidesData
        ];
    }

    $song['verses'] = $versesData;
    echo json_encode($song);
    exit;
}

echo json_encode(['status' => 'online', 'server' => 'PHP Sunday School Taranim API', 'db_type' => $isMysql ? 'mysql' : 'sqlite']);
