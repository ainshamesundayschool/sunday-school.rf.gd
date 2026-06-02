<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

function readGitVersion(string $root): ?string
{
    $gitDir = $root . '/.git';
    if (!is_dir($gitDir)) {
        return null;
    }

    $head = @file_get_contents($gitDir . '/HEAD');
    if (!$head) {
        return null;
    }

    $head = trim($head);
    if (strncmp($head, 'ref: ', 5) === 0) {
        $ref = trim(substr($head, 5));
        $refFile = $gitDir . '/' . $ref;
        $hash = @file_get_contents($refFile);
        if ($hash) {
            return trim($hash);
        }

        $packed = @file_get_contents($gitDir . '/packed-refs');
        if ($packed) {
            foreach (preg_split('/\r\n|\r|\n/', $packed) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                if (substr($line, -strlen($ref) - 1) === ' ' . $ref) {
                    return trim(strtok($line, ' '));
                }
            }
        }
        return null;
    }

    return $head !== '' ? $head : null;
}

$root = __DIR__;
$version = readGitVersion($root);
$source = 'git';

if (!$version) {
    $watchFiles = [
        $root . '/index.html',
        $root . '/api.php',
        $root . '/sw.js',
        $root . '/login/index.html',
        $root . '/uncle/dashboard/index.php',
        $root . '/uncle/church/index.html',
    ];
    $latest = 0;
    foreach ($watchFiles as $file) {
        $mtime = @filemtime($file);
        if ($mtime && $mtime > $latest) {
            $latest = $mtime;
        }
    }
    $version = $latest > 0 ? (string) $latest : 'dev';
    $source = 'fallback';
}

echo json_encode([
    'success' => true,
    'version' => $version,
    'source' => $source,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
