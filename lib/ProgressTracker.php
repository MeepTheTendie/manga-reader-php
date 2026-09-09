<?php
require_once __DIR__ . '/../config.php';

class ProgressTracker {
    public static function loadProgress(): array {
        if (!file_exists(PROGRESS_FILE)) return [];
        $content = file_get_contents(PROGRESS_FILE);
        if ($content === false) throw new RuntimeException('Cannot read progress');
        $data = json_decode($content, true);
        if (!is_array($data)) throw new RuntimeException('Progress file is damaged; restore a backup before saving');
        return $data;
    }
    private static function locked(callable $operation): bool {
        $lock = fopen(PROGRESS_FILE . '.lock', 'c');
        if (!$lock) return false;
        try {
            if (!flock($lock, LOCK_EX)) return false;
            return $operation();
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }
    private static function atomicWrite(array $progress): bool {
        $content = json_encode($progress, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $temp = tempnam(dirname(PROGRESS_FILE), '.progress-');
        if ($temp === false) return false;
        try {
            if (file_put_contents($temp, $content) !== strlen($content)) return false;
            chmod($temp, 0600);
            return rename($temp, PROGRESS_FILE);
        } finally { if (file_exists($temp)) unlink($temp); }
    }
    public static function saveProgress(array $progress): bool {
        return self::locked(fn() => self::atomicWrite($progress));
    }
    public static function updateProgress(string $mangaPath, int $pageIndex): bool {
        return self::locked(function() use ($mangaPath, $pageIndex) {
            $progress = self::loadProgress();
            $progress[$mangaPath] = ['page_index'=>$pageIndex, 'timestamp'=>date('c')];
            return self::atomicWrite($progress);
        });
    }
    public static function getProgress(string $mangaPath): ?array { return self::loadProgress()[$mangaPath] ?? null; }
}
