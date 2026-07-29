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
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

$requestUri = $_SERVER['REQUEST_URI'];
$parsedUrl = parse_url($requestUri, PHP_URL_PATH);

if (strpos($parsedUrl, '/api/songs') !== false) {
    $q = isset($_GET['q']) ? trim($_GET['q']) : '';
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;

    if (!empty($q)) {
        $stmt = $pdo->prepare("SELECT id, title, notes, is_bible FROM songs WHERE title LIKE :q OR notes LIKE :q GROUP BY id LIMIT :limit");
        $stmt->bindValue(':q', '%' . $q . '%', PDO::PARAM_STR);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("SELECT id, title, notes, is_bible FROM songs GROUP BY id LIMIT :limit");
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
    }

    $songs = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['songs' => $songs]);
    exit;
}

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

    $vStmt = $pdo->prepare("SELECT * FROM verses WHERE song_id = :id ORDER BY sort_order ASC");
    $vStmt->bindValue(':id', $songId, PDO::PARAM_INT);
    $vStmt->execute();
    $verses = $vStmt->fetchAll(PDO::FETCH_ASSOC);

    $versesData = [];
    foreach ($verses as $verse) {
        $sStmt = $pdo->prepare("SELECT * FROM slides WHERE verse_id = :vid ORDER BY sort_order ASC");
        $sStmt->bindValue(':vid', $verse['id'], PDO::PARAM_INT);
        $sStmt->execute();
        $slides = $sStmt->fetchAll(PDO::FETCH_ASSOC);

        $slidesData = [];
        foreach ($slides as $slide) {
            $lines = preg_split('/[\r\n]+/', $slide['text']);
            $slidesData[] = [
                'id' => $slide['id'],
                'text' => $slide['text'],
                'lines' => array_values(array_filter(array_map('trim', $lines)))
            ];
        }

        $versesData[] = [
            'id' => $verse['id'],
            'title' => $verse['title'],
            'slides' => $slidesData
        ];
    }

    $song['verses'] = $versesData;
    echo json_encode($song);
    exit;
}

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
