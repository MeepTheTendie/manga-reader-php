<?php
/**
 * Thumbnail Generator - Create cover thumbnails from archives
 */
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/ArchiveHandler.php';

class ThumbnailGenerator {
    
    private const THUMB_WIDTH = 200;
    private const THUMB_HEIGHT = 300;
    private const THUMB_QUALITY = 85;

    /**
     * Generate or retrieve cached cover thumbnail for an archive
     */
    public static function getCoverThumbnail(string $archivePath): ?string {
        $cacheKey = str_replace(['/', '\\', ':'], '_', substr($archivePath, strlen(MANGA_ROOT) + 1));
        $cacheFile = CACHE_DIR . '/thumb_' . $cacheKey . '.jpg';
        
        // Return cached thumbnail if exists
        if (file_exists($cacheFile)) {
            return file_get_contents($cacheFile);
        }
        
        // Get first image from archive
        try {
            $images = ArchiveHandler::getArchiveFiles($archivePath);
        } catch (Exception $e) {
            return null;
        }
        
        if (empty($images)) {
            return null;
        }
        
        $imageData = ArchiveHandler::extractImage($archivePath, $images[0]);
        if ($imageData === null) {
            return null;
        }
        
        // Generate thumbnail
        $thumbnail = self::generateThumbnail($imageData);
        
        if ($thumbnail !== null) {
            // Cache the thumbnail
            file_put_contents($cacheFile, $thumbnail);
        }
        
        return $thumbnail;
    }

    /**
     * Generate thumbnail from image data
     */
    private static function generateThumbnail(string $imageData): ?string {
        // Check for GD extension
        if (!extension_loaded('gd')) {
            // Try Imagick
            if (extension_loaded('imagick')) {
                return self::generateThumbnailImagick($imageData);
            }
            return null;
        }
        
        try {
            $source = @imagecreatefromstring($imageData);
            if ($source === false) {
                return null;
            }
            
            $origWidth = imagesx($source);
            $origHeight = imagesy($source);
            
            // Calculate aspect ratio preserving resize
            $ratio = min(self::THUMB_WIDTH / $origWidth, self::THUMB_HEIGHT / $origHeight);
            $newWidth = (int)($origWidth * $ratio);
            $newHeight = (int)($origHeight * $ratio);
            
            // Create thumbnail canvas
            $thumbnail = imagecreatetruecolor(self::THUMB_WIDTH, self::THUMB_HEIGHT);
            
            // Fill with dark background
            $bgColor = imagecolorallocate($thumbnail, 30, 30, 30);
            imagefill($thumbnail, 0, 0, $bgColor);
            
            // Calculate center position
            $x = (self::THUMB_WIDTH - $newWidth) / 2;
            $y = (self::THUMB_HEIGHT - $newHeight) / 2;
            
            // Resize and paste
            imagecopyresampled(
                $thumbnail, $source,
                (int)$x, (int)$y, 0, 0,
                $newWidth, $newHeight,
                $origWidth, $origHeight
            );
            
            // Output to string
            ob_start();
            imagejpeg($thumbnail, null, self::THUMB_QUALITY);
            $result = ob_get_clean();
            
            // Cleanup
            imagedestroy($source);
            imagedestroy($thumbnail);
            
            return $result;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Generate thumbnail using Imagick
     */
    private static function generateThumbnailImagick(string $imageData): ?string {
        try {
            $imagick = new Imagick();
            $imagick->readImageBlob($imageData);
            
            // Resize preserving aspect ratio
            $imagick->thumbnailImage(self::THUMB_WIDTH, self::THUMB_HEIGHT, true);
            
            // Create canvas for padding
            $canvas = new Imagick();
            $canvas->newImage(self::THUMB_WIDTH, self::THUMB_HEIGHT, '#1e1e1e');
            $canvas->setImageFormat('jpeg');
            
            // Calculate position to center
            $x = (self::THUMB_WIDTH - $imagick->getImageWidth()) / 2;
            $y = (self::THUMB_HEIGHT - $imagick->getImageHeight()) / 2;
            
            // Composite
            $canvas->compositeImage($imagick, Imagick::COMPOSITE_OVER, (int)$x, (int)$y);
            $canvas->setImageCompressionQuality(self::THUMB_QUALITY);
            
            $result = $canvas->getImageBlob();
            
            $imagick->clear();
            $canvas->clear();
            
            return $result;
        } catch (Exception $e) {
            return null;
        }
    }
}
