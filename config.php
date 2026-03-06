<?php
/**
 * Manga Reader - Configuration
 */

define('MANGA_ROOT', getenv('MANGA_ROOT') ?: getenv('HOME') . '/Documents');
define('CACHE_DIR', getenv('CACHE_DIR') ?: __DIR__ . '/cache');
define('PROGRESS_FILE', CACHE_DIR . '/reading_progress.json');

// Supported image extensions
const IMAGE_EXTS = ['.jpg', '.jpeg', '.png', '.webp', '.gif', '.bmp', '.tiff'];

// Archive extensions
const ARCHIVE_EXTS = ['.cbz', '.zip', '.cbr', '.rar'];

// Ensure cache directory exists
if (!is_dir(CACHE_DIR)) {
    mkdir(CACHE_DIR, 0755, true);
}
