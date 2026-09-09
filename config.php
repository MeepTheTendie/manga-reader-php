<?php
/**
 * Manga Reader - Configuration
 */

require_once __DIR__ . '/lib/Access.php';
protectManga();

// Security headers
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline';");

define('MANGA_ROOT', getenv('MANGA_ROOT') ?: getenv('HOME') . '/Documents');
define('CACHE_DIR', getenv('CACHE_DIR') ?: __DIR__ . '/cache');
define('PROGRESS_FILE', CACHE_DIR . '/reading_progress.json');

// Supported image extensions
const IMAGE_EXTS = ['.jpg', '.jpeg', '.png', '.webp', '.gif', '.bmp', '.tiff'];

// Archive extensions
const ARCHIVE_EXTS = ['.cbz', '.zip', '.cbr', '.rar'];

// Ensure cache directory exists
if (!is_dir(CACHE_DIR)) {
    mkdir(CACHE_DIR, 0700, true);
}
