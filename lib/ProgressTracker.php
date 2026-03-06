<?php
/**
 * Progress Tracker - Save and load reading progress
 */
require_once __DIR__ . '/../config.php';

class ProgressTracker {
    
    /**
     * Load reading progress from file
     */
    public static function loadProgress(): array {
        if (!file_exists(PROGRESS_FILE)) {
            return [];
        }
        
        $content = file_get_contents(PROGRESS_FILE);
        if ($content === false) {
            return [];
        }
        
        $data = json_decode($content, true);
        if ($data === null) {
            return [];
        }
        
        return $data;
    }
    
    /**
     * Save reading progress to file
     */
    public static function saveProgress(array $progress): bool {
        $content = json_encode($progress, JSON_PRETTY_PRINT);
        if ($content === false) {
            return false;
        }
        
        return file_put_contents(PROGRESS_FILE, $content) !== false;
    }
    
    /**
     * Update progress for a specific manga
     */
    public static function updateProgress(string $mangaPath, int $pageIndex): bool {
        $progress = self::loadProgress();
        
        $progress[$mangaPath] = [
            'page_index' => $pageIndex,
            'timestamp' => date('c'),
        ];
        
        return self::saveProgress($progress);
    }
    
    /**
     * Get progress for a specific manga
     */
    public static function getProgress(string $mangaPath): ?array {
        $progress = self::loadProgress();
        return $progress[$mangaPath] ?? null;
    }
}
