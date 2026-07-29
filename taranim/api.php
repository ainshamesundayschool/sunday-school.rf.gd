<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

$dbPath = __DIR__ . '/database.sqlite';
$liveFile = __DIR__ . '/live.json';

try {
    $pdo = new PDO('sqlite:' . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo json_encode(['songs' => [], 'error' => 'Database connection failed']);
    exit;
}

$requestUri = $_SERVER['REQUEST_URI'];
$parsedUrl = parse_url($requestUri, PHP_URL_PATH);

// SEARCH SONGS ENDPOINT
if (strpos($parsedUrl, '/api/songs') !== false) {
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;

    $results = [];

    if (!empty($q)) {
        $stmt = $pdo->prepare("
            SELECT s.id, s.item_id, s.title, s.media_url, 
                   GROUP_CONCAT(DISTINCT sg.content) as notes
            FROM songs s
            LEFT JOIN verses v ON v.item_id = s.item_id
            LEFT JOIN slides sl ON sl.verse = v.id
            LEFT JOIN segments sg ON sg.slide = sl.id
            WHERE s.title LIKE :q OR sg.content LIKE :q
            GROUP BY s.id
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':q', '%' . $q . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("
            SELECT s.id, s.item_id, s.title, s.media_url, s.notes
            FROM songs s
            GROUP BY s.id
            ORDER BY s.id ASC
            LIMIT :limit OFFSET :offset
        ");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
    }

    $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['songs' => $songs, 'total' => count($songs)]);
    exit;
}

// GET SINGLE SONG BY ID ENDPOINT
if (preg_match('#/api/song/(\d+)#', $parsedUrl, $matches)) {
    $songId = (int)$matches[1];

    $stmt = $pdo->prepare("SELECT * FROM songs WHERE id = :id");
    $stmt->bindValue(':id', $songId, PDO::PARAM_INT);
    $stmt->execute();
    $song = $stmt->fetch(PDO::FETCH_ASSOC);

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

// LIVE PRESENTATION STATE SYNC
if (strpos($parsedUrl, '/api/live') !== false) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents('php://input');
        file_put_contents($liveFile, $input);
        echo json_encode(['status' => 'success']);
        exit;
    } else {
        if (file_exists($liveFile)) {
            echo file_get_contents($liveFile);
        } else {
            echo json_encode(['type' => 'PRESENT_LINE', 'text' => '', 'isBlank' => true]);
        }
        exit;
    }
}

echo json_encode(['status' => 'online', 'server' => 'PHP Sunday School Taranim API']);
