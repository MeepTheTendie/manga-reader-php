<?php
/**
 * Archive Handler - Extract images from CBZ, ZIP, CBR, RAR archives
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/ArchiveException.php';

class ArchiveHandler {
    
    /**
     * Check if filename is an image
     */
    public static function isImage(string $filename): bool {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        // Check if extension is in the allowed list
        $allowedExts = array_map(fn($e) => ltrim($e, '.'), IMAGE_EXTS);
        return in_array($ext, $allowedExts, true);
    }

    /**
     * Get list of image files from an archive, sorted naturally
     */
    public static function getArchiveFiles(string $archivePath): array {
        if (!file_exists($archivePath)) {
            throw new ArchiveException("Archive not found: " . basename($archivePath));
        }

        $ext = strtolower(pathinfo($archivePath, PATHINFO_EXTENSION));
        
        // Handle compound extensions
        $filename = basename($archivePath);
        $fullExt = '';
        foreach (ARCHIVE_EXTS as $archiveExt) {
            if (stripos($filename, $archiveExt) !== false) {
                $fullExt = ltrim($archiveExt, '.');
                break;
            }
        }
        
        if (!$fullExt) {
            $fullExt = $ext;
        }

        try {
            if (in_array($fullExt, ['cbz', 'zip'])) {
                return self::getZipFiles($archivePath);
            } elseif (in_array($fullExt, ['cbr', 'rar'])) {
                return self::getRarFiles($archivePath);
            } else {
                throw new ArchiveException("Unsupported archive format: $fullExt");
            }
        } catch (Exception $e) {
            if ($e instanceof ArchiveException) throw $e;
            throw new ArchiveException("Error reading archive: " . $e->getMessage());
        }
    }

    /**
     * Get files from ZIP archive
     */
    private static function getZipFiles(string $archivePath): array {
        $zip = new ZipArchive();
        $result = $zip->open($archivePath);
        
        if ($result !== true) {
            throw new ArchiveException("Cannot open ZIP archive: " . basename($archivePath));
        }
        
        $files = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            // Skip directories and hidden files (starting with __)
            if (substr($name, -1) !== '/' && strpos($name, '__') !== 0 && self::isImage($name)) {
                $files[] = $name;
            }
        }
        
        $zip->close();
        
        // Natural sort
        usort($files, [MangaLibrary::class, 'naturalSortCompare']);
        
        return $files;
    }

    /**
     * Get files from RAR archive
     */
    private static function getRarFiles(string $archivePath): array {
        if (!extension_loaded('rar')) {
            // Fallback: try using command line unrar
            return self::getRarFilesCommandLine($archivePath);
        }
        
        $rar = RarArchive::open($archivePath);
        if ($rar === false) {
            throw new ArchiveException("Cannot open RAR archive: " . basename($archivePath));
        }
        
        $files = [];
        $entries = $rar->getEntries();
        
        if ($entries === false) {
            throw new ArchiveException("Error reading RAR entries: " . basename($archivePath));
        }
        
        foreach ($entries as $entry) {
            $name = $entry->getName();
            // Skip directories and hidden files
            if (!$entry->isDirectory() && strpos($name, '__') !== 0 && self::isImage($name)) {
                $files[] = $name;
            }
        }
        
        $rar->close();
        
        // Natural sort
        usort($files, [MangaLibrary::class, 'naturalSortCompare']);
        
        return $files;
    }

    /**
     * Fallback for RAR files using command line
     */
    private static function getRarFilesCommandLine(string $archivePath): array {
        $command = 'unrar vb ' . escapeshellarg($archivePath) . ' 2>/dev/null';
        $output = [];
        $returnVar = 0;
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0) {
            throw new ArchiveException("Cannot read RAR archive (unrar not installed?): " . basename($archivePath));
        }
        
        $files = [];
        foreach ($output as $name) {
            $name = trim($name);
            if (strpos($name, '__') !== 0 && self::isImage($name)) {
                $files[] = $name;
            }
        }
        
        // Natural sort
        usort($files, [MangaLibrary::class, 'naturalSortCompare']);
        
        return $files;
    }

    /**
     * Extract a single image from an archive
     */
    public static function extractImage(string $archivePath, string $imagePath): ?string {
        $ext = strtolower(pathinfo($archivePath, PATHINFO_EXTENSION));
        
        // Handle compound extensions
        $filename = basename($archivePath);
        $fullExt = '';
        foreach (ARCHIVE_EXTS as $archiveExt) {
            if (stripos($filename, $archiveExt) !== false) {
                $fullExt = ltrim($archiveExt, '.');
                break;
            }
        }
        
        if (!$fullExt) {
            $fullExt = $ext;
        }

        try {
            if (in_array($fullExt, ['cbz', 'zip'])) {
                return self::extractFromZip($archivePath, $imagePath);
            } elseif (in_array($fullExt, ['cbr', 'rar'])) {
                return self::extractFromRar($archivePath, $imagePath);
            }
        } catch (Exception $e) {
            if ($e instanceof ArchiveException) throw $e;
            throw new ArchiveException("Error extracting image: " . $e->getMessage());
        }
        
        return null;
    }

    /**
     * Extract from ZIP archive
     */
    private static function extractFromZip(string $archivePath, string $imagePath): ?string {
        $zip = new ZipArchive();
        $result = $zip->open($archivePath);
        
        if ($result !== true) {
            throw new ArchiveException("Cannot open ZIP archive");
        }
        
        $content = $zip->getFromName($imagePath);
        $zip->close();
        
        if ($content === false) {
            throw new ArchiveException("Image not found in archive: " . $imagePath);
        }
        
        return $content;
    }

    /**
     * Extract from RAR archive
     */
    private static function extractFromRar(string $archivePath, string $imagePath): ?string {
        if (!extension_loaded('rar')) {
            return self::extractFromRarCommandLine($archivePath, $imagePath);
        }
        
        $rar = RarArchive::open($archivePath);
        if ($rar === false) {
            throw new ArchiveException("Cannot open RAR archive");
        }
        
        $entry = $rar->getEntry($imagePath);
        if ($entry === false) {
            $rar->close();
            throw new ArchiveException("Image not found in archive: " . $imagePath);
        }
        
        $stream = $entry->getStream();
        if ($stream === false) {
            $rar->close();
            throw new ArchiveException("Cannot read image from archive");
        }
        
        $content = stream_get_contents($stream);
        fclose($stream);
        $rar->close();
        
        return $content;
    }

    /**
     * Fallback extraction from RAR using command line
     */
    private static function extractFromRarCommandLine(string $archivePath, string $imagePath): ?string {
        $tempFile = tempnam(sys_get_temp_dir(), 'manga_');
        if ($tempFile === false) {
            throw new ArchiveException("Cannot create temporary file");
        }
        
        $command = 'unrar p -ierr ' . escapeshellarg($archivePath) . ' ' . escapeshellarg($imagePath) . ' > ' . escapeshellarg($tempFile) . ' 2>/dev/null';
        
        exec($command, $output, $returnVar);
        
        if ($returnVar !== 0 || !file_exists($tempFile) || filesize($tempFile) === 0) {
            if (file_exists($tempFile)) unlink($tempFile);
            throw new ArchiveException("Cannot extract from RAR archive (unrar not installed?)");
        }
        
        $content = file_get_contents($tempFile);
        unlink($tempFile);
        
        if ($content === false) {
            throw new ArchiveException("Failed to read extracted content");
        }
        
        return $content;
    }
}
