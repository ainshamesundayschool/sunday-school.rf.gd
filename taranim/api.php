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

$requestUri = $_SERVER['REQUEST_URI'];
$parsedUrl  = parse_url($requestUri, PHP_URL_PATH);

// LIVE PRESENTATION STATE SYNC ENDPOINT
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

// SILENT BACKGROUND SYNC WITH ONLINE TASBE7NA REPOSITORY
function syncOnlineTasbe7naDatabase($pdo) {
    if (!$pdo) return;
    $syncLockFile = __DIR__ . '/.tasbe7na_sync.lock';
    if (file_exists($syncLockFile) && (time() - filemtime($syncLockFile) < 21600)) {
        return;
    }
    @touch($syncLockFile);

    try {
        $opts = [
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: SundaySchoolTaranim/2.0\r\n",
                'timeout' => 4
            ],
            'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]
        ];
        $context = stream_context_create($opts);
        $json = @file_get_contents('https://raw.githubusercontent.com/tashbe7na/database/main/latest.json', false, $context);

        if ($json) {
            $data = json_decode($json, true);
            if (is_array($data)) {
                $checkStmt  = $pdo->prepare("SELECT id FROM songs WHERE title = :title");
                $insertStmt = $pdo->prepare("INSERT INTO songs (item_id, title, notes) VALUES (:itemId, :title, :notes)");

                foreach ($data as $song) {
                    if (!empty($song['title'])) {
                        $checkStmt->execute([':title' => $song['title']]);
                        if (!$checkStmt->fetch()) {
                            $newItemId = rand(900000, 999999);
                            $insertStmt->execute([
                                ':itemId' => $newItemId,
                                ':title'  => $song['title'],
                                ':notes'  => isset($song['notes']) ? $song['notes'] : ''
                            ]);
                        }
                    }
                }
            }
        }
    } catch (Exception $e) {}
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

if ($pdo && rand(1, 4) === 1) {
    syncOnlineTasbe7naDatabase($pdo);
}

// SEARCH SONGS ENDPOINT
if (strpos($parsedUrl, '/api/songs') !== false || (isset($_GET['action']) && $_GET['action'] === 'songs')) {
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
                   GROUP_CONCAT(DISTINCT sg.content) as notes,
                   sc.scale as scale_id
            FROM songs s
            LEFT JOIN song_scales sc ON sc.song = s.id
            LEFT JOIN verses v ON v.item_id = s.item_id
            LEFT JOIN slides sl ON sl.verse = v.id
            LEFT JOIN segments sg ON sg.slide = sl.id
            WHERE s.title LIKE :q OR sg.content LIKE :q OR s.title LIKE :qNorm OR sg.content LIKE :qNorm
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

            $qBookStr = trim($bookNum . ' ' . $bookText);
            $qBookNorm = normalizeArabic($qBookStr);
            $qSubText = '%' . trim($bookText) . '%';

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
                    WHERE (b.title LIKE :q OR b.abbr LIKE :q OR b.title LIKE :qSub OR b.abbr LIKE :qSub OR :qNorm LIKE ('%' || b.title || '%') OR :qNorm LIKE ('%' || b.abbr || '%'))
                      AND bc.number = :chNum
                    LIMIT 10
                ");
                $bStmt->bindValue(':q', '%' . $qBookStr . '%', PDO::PARAM_STR);
                $bStmt->bindValue(':qSub', $qSubText, PDO::PARAM_STR);
                $bStmt->bindValue(':qNorm', '%' . $qBookNorm . '%', PDO::PARAM_STR);
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
                    WHERE b.title LIKE :q OR b.abbr LIKE :q OR b.title LIKE :qSub OR b.abbr LIKE :qSub OR :qNorm LIKE ('%' || b.title || '%')
                    LIMIT 10
                ");
                $bStmt->bindValue(':q', '%' . $qBookStr . '%', PDO::PARAM_STR);
                $bStmt->bindValue(':qSub', $qSubText, PDO::PARAM_STR);
                $bStmt->bindValue(':qNorm', '%' . $qBookNorm . '%', PDO::PARAM_STR);
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
        $totalCount = (int)$cStmt->fetchColumn();
    } catch (Exception $e) {}

    echo json_encode(['songs' => $allResults, 'total' => count($allResults), 'total_songs' => $totalCount, 'db_type' => $isMysql ? 'mysql' : 'sqlite']);
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

    if ($isBibleReq) {
        try {
            $cStmt = $pdo->prepare("
                SELECT c.id, c.item_id, (b.title || ' - الأصحاح ' || bc.number) as title, 1 as is_bible
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
                SELECT c.id, c.item_id, (b.title || ' - الأصحاح ' || bc.number) as title, 1 as is_bible
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
            $lineStmt = $pdo->prepare("SELECT content FROM segments WHERE slide = :sid ORDER BY id ASC");
            $lineStmt->bindValue(':sid', $slide['id'], PDO::PARAM_INT);
            $lineStmt->execute();
            $lines = $lineStmt->fetchAll(PDO::FETCH_COLUMN);

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
