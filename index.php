<?php
/**
 * Manga Reader - Main Entry Point
 * 
 * Routes:
 * GET /                    - Library browser
 * GET /read/<path>         - Reader page
 * GET /api/library         - Get all manga series
 * GET /api/manga/<path>/pages     - Get pages for a manga
 * GET /api/manga/<path>/page/<page> - Get specific page image
 * GET /api/manga/<path>/cover     - Get cover thumbnail
 * GET /api/progress        - Get reading progress
 * POST /api/progress       - Save reading progress
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/lib/MangaLibrary.php';
require_once __DIR__ . '/lib/ArchiveHandler.php';
require_once __DIR__ . '/lib/ThumbnailGenerator.php';
require_once __DIR__ . '/lib/ProgressTracker.php';

// Get request path
$requestUri = $_SERVER['REQUEST_URI'];
$scriptName = $_SERVER['SCRIPT_NAME'];
$path = parse_url($requestUri, PHP_URL_PATH);

// Remove script name from path if present
if (strpos($path, $scriptName) === 0) {
    $path = substr($path, strlen($scriptName));
}

// Remove leading slash
$path = ltrim($path, '/');

// Router
if ($path === '' || $path === 'index.php') {
    showLibrary();
} elseif (preg_match('/^read\/(.+)$/', $path, $matches)) {
    showReader(urldecode($matches[1]));
} elseif ($path === 'api/library') {
    apiLibrary();
} elseif (preg_match('/^api\/manga\/(.+?)\/pages$/', $path, $matches)) {
    apiMangaPages(urldecode($matches[1]));
} elseif (preg_match('/^api\/manga\/(.+?)\/page\/(.+)$/', $path, $matches)) {
    apiMangaPage(urldecode($matches[1]), urldecode($matches[2]));
} elseif (preg_match('/^api\/manga\/(.+?)\/cover$/', $path, $matches)) {
    apiMangaCover(urldecode($matches[1]));
} elseif ($path === 'api/progress') {
    apiProgress();
} else {
    http_response_code(404);
    echo json_encode(['error' => 'Not found']);
}

// === Page Controllers ===

function showLibrary(): void {
    include __DIR__ . '/templates/library.php';
}

function showReader(string $mangaPath): void {
    $decodedPath = urldecode($mangaPath);
    include __DIR__ . '/templates/reader.php';
}

// === API Controllers ===

function apiLibrary(): void {
    header('Content-Type: application/json');
    
    $library = MangaLibrary::scanLibrary();
    echo json_encode($library);
}

function apiMangaPages(string $mangaPath): void {
    header('Content-Type: application/json');
    
    $fullPath = MangaLibrary::validatePath($mangaPath);
    
    if ($fullPath === null || !file_exists($fullPath)) {
        http_response_code(404);
        echo json_encode(['error' => 'Manga not found']);
        return;
    }
    
    try {
        $pages = ArchiveHandler::getArchiveFiles($fullPath);
    } catch (ArchiveException $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
        return;
    }
    
    $nextVolume = MangaLibrary::findNextVolume($mangaPath);
    
    echo json_encode([
        'path' => $mangaPath,
        'pages' => $pages,
        'total' => count($pages),
        'next_volume' => $nextVolume,
    ]);
}

function apiMangaPage(string $mangaPath, string $pagePath): void {
    $fullPath = MangaLibrary::validatePath($mangaPath);
    
    if ($fullPath === null || !file_exists($fullPath)) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Manga not found']);
        return;
    }
    
    try {
        $imageData = ArchiveHandler::extractImage($fullPath, $pagePath);
    } catch (ArchiveException $e) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['error' => $e->getMessage()]);
        return;
    }
    
    if ($imageData === null) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Page not found']);
        return;
    }
    
    // Determine content type from file extension
    $ext = strtolower(pathinfo($pagePath, PATHINFO_EXTENSION));
    $contentTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'bmp' => 'image/bmp',
        'tiff' => 'image/tiff',
    ];
    $contentType = $contentTypes[$ext] ?? 'image/jpeg';
    
    header('Content-Type: ' . $contentType);
    header('Cache-Control: public, max-age=86400');
    header('Content-Length: ' . strlen($imageData));
    
    echo $imageData;
}

function apiMangaCover(string $mangaPath): void {
    $fullPath = MangaLibrary::validatePath($mangaPath);
    
    if ($fullPath === null || !file_exists($fullPath)) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Manga not found']);
        return;
    }
    
    $thumbData = ThumbnailGenerator::getCoverThumbnail($fullPath);
    
    if ($thumbData === null) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Cover not available']);
        return;
    }
    
    header('Content-Type: image/jpeg');
    header('Cache-Control: public, max-age=86400');
    header('Content-Length: ' . strlen($thumbData));
    
    echo $thumbData;
}

function apiProgress(): void {
    header('Content-Type: application/json');
    
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        echo json_encode(ProgressTracker::loadProgress());
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = file_get_contents('php://input');
        $data = json_decode($input, true);
        
        if ($data === null) {
            http_response_code(400);
            echo json_encode(['error' => 'No data provided']);
            return;
        }
        
        $mangaPath = $data['manga_path'] ?? null;
        $pageIndex = $data['page_index'] ?? null;
        
        if ($mangaPath !== null && $pageIndex !== null) {
            ProgressTracker::updateProgress($mangaPath, (int)$pageIndex);
            echo json_encode(['status' => 'saved']);
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid data']);
        }
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}
