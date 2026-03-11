<?php
/**
 * Manga Library Scanner and Manager
 */
require_once __DIR__ . '/../config.php';

class MangaLibrary {
    private static $cache = null;
    private static $cacheTime = 0;
    private static $cacheTtl = 60; // Cache for 60 seconds

    /**
     * Validate that path doesn't escape MANGA_ROOT (path traversal prevention)
     */
    public static function validatePath(string $mangaPath): ?string {
        $rootPath = realpath(MANGA_ROOT);
        if ($rootPath === false) {
            return null;
        }
        
        $fullPath = realpath(MANGA_ROOT . '/' . $mangaPath);
        
        if ($fullPath === false || strpos($fullPath, $rootPath) !== 0) {
            return null;
        }
        
        // Ensure the path is within MANGA_ROOT (prevent traversal with trailing slash issues)
        if (strlen($fullPath) < strlen($rootPath)) {
            return null;
        }
        
        return $fullPath;
    }

    /**
     * Natural sort for filenames like page_1.jpg, page_2.jpg, page_10.jpg
     */
    public static function naturalSortKey(string $s): array {
        return preg_split('/(\d+)/', strtolower($s), -1, PREG_SPLIT_DELIM_CAPTURE);
    }

    /**
     * Scan library for manga series and volumes
     */
    public static function scanLibrary(): array {
        // Simple in-memory caching
        if (self::$cache !== null && (time() - self::$cacheTime) < self::$cacheTtl) {
            return self::$cache;
        }

        $library = [];
        $root = MANGA_ROOT;

        if (!is_dir($root)) {
            return $library;
        }

        $items = scandir($root);
        
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            
            $itemPath = $root . '/' . $item;
            
            if (is_dir($itemPath)) {
                $seriesName = $item;
                $volumes = [];
                
                // Recursively find archive files
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($itemPath, RecursiveDirectoryIterator::SKIP_DOTS)
                );
                
                foreach ($iterator as $file) {
                    if ($file->isFile()) {
                        $ext = strtolower($file->getExtension());
                        // Handle compound extensions like .cbz, .cbr
                        $filename = $file->getFilename();
                        $hasArchiveExt = false;
                        foreach (ARCHIVE_EXTS as $archiveExt) {
                            if (stripos($filename, $archiveExt) !== false) {
                                $hasArchiveExt = true;
                                break;
                            }
                        }
                        
                        if ($hasArchiveExt) {
                            $relativePath = substr($file->getPathname(), strlen($root) + 1);
                            $volumes[] = [
                                'path' => $relativePath,
                                'name' => $file->getBasename('.' . $ext),
                                'filename' => $filename,
                                'size' => $file->getSize(),
                            ];
                        }
                    }
                }
                
                if (!empty($volumes)) {
                    // Sort volumes naturally
                    usort($volumes, function($a, $b) {
                        return self::naturalSortCompare($a['name'], $b['name']);
                    });
                    
                    $library[$seriesName] = [
                        'path' => $item,
                        'volumes' => $volumes,
                    ];
                }
            }
        }
        
        self::$cache = $library;
        self::$cacheTime = time();
        
        return $library;
    }

    /**
     * Compare two strings using natural sort
     */
    public static function naturalSortCompare(string $a, string $b): int {
        $aParts = self::naturalSortKey($a);
        $bParts = self::naturalSortKey($b);
        
        $max = max(count($aParts), count($bParts));
        
        for ($i = 0; $i < $max; $i++) {
            if (!isset($aParts[$i])) return -1;
            if (!isset($bParts[$i])) return 1;
            
            $aPart = $aParts[$i];
            $bPart = $bParts[$i];
            
            // If both parts are numeric, compare as numbers
            if (ctype_digit($aPart) && ctype_digit($bPart)) {
                $cmp = (int)$aPart <=> (int)$bPart;
            } else {
                $cmp = strcmp($aPart, $bPart);
            }
            
            if ($cmp !== 0) {
                return $cmp;
            }
        }
        
        return 0;
    }

    /**
     * Build path index for next volume lookup
     */
    public static function buildPathIndex(): array {
        $library = self::scanLibrary();
        $pathIndex = [];
        
        foreach ($library as $seriesName => $seriesData) {
            foreach ($seriesData['volumes'] as $idx => $volume) {
                $pathIndex[$volume['path']] = ['series' => &$library[$seriesName], 'index' => $idx];
            }
        }
        
        return $pathIndex;
    }

    /**
     * Find the next volume in the same series
     */
    public static function findNextVolume(string $mangaPath): ?array {
        $pathIndex = self::buildPathIndex();
        
        if (!isset($pathIndex[$mangaPath])) {
            return null;
        }
        
        $seriesData = $pathIndex[$mangaPath]['series'];
        $currentIdx = $pathIndex[$mangaPath]['index'];
        
        if ($currentIdx + 1 < count($seriesData['volumes'])) {
            return $seriesData['volumes'][$currentIdx + 1];
        }
        
        return null;
    }

    /**
     * Clear library cache
     */
    public static function invalidateCache(): void {
        self::$cache = null;
        self::$cacheTime = 0;
    }
}
