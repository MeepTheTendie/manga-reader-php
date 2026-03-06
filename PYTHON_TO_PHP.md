# Python to PHP Migration Notes

## Architecture Comparison

| Component | Python (Flask) | PHP |
|-----------|----------------|-----|
| **Entry Point** | `app.py` | `index.php` |
| **Routing** | Flask decorators | Manual path parsing in `index.php` |
| **Templates** | Jinja2 | Native PHP includes |
| **Static Files** | Flask `send_file()` | Direct output with headers |
| **JSON API** | Flask `jsonify()` | `json_encode()` |
| **Caching** | `@lru_cache` decorator | Manual static property caching |

## Code Pattern Changes

### 1. Route Handling

**Python:**
```python
@app.route("/api/library")
def api_library():
    library = scan_manga_library()
    return jsonify(library)
```

**PHP:**
```php
function apiLibrary(): void {
    header('Content-Type: application/json');
    $library = MangaLibrary::scanLibrary();
    echo json_encode($library);
}

// Routed via path matching in index.php
if ($path === 'api/library') {
    apiLibrary();
}
```

### 2. Archive Extraction

**Python:**
```python
with zipfile.ZipFile(archive_path, "r") as zf:
    return zf.read(image_path)
```

**PHP:**
```php
$zip = new ZipArchive();
$zip->open($archivePath);
$content = $zip->getFromName($imagePath);
$zip->close();
return $content;
```

### 3. Image Processing

**Python:**
```python
from PIL import Image
img = Image.open(io.BytesIO(image_data))
img.thumbnail(size, Image.Resampling.LANCZOS)
```

**PHP:**
```php
$source = imagecreatefromstring($imageData);
imagecopyresampled($thumbnail, $source, ...);
```

### 4. Caching

**Python:**
```python
@lru_cache(maxsize=1)
def _scan_manga_library_cached():
    ...
```

**PHP:**
```php
private static $cache = null;
private static $cacheTime = 0;
private static $cacheTtl = 60;

public static function scanLibrary(): array {
    if (self::$cache !== null && (time() - self::$cacheTime) < self::$cacheTtl) {
        return self::$cache;
    }
    // ... compute and cache
}
```

### 5. Progress Storage

**Python:**
```python
def load_progress():
    if PROGRESS_FILE.exists():
        with open(PROGRESS_FILE, "r") as f:
            return json.load(f)
    return {}
```

**PHP:**
```php
public static function loadProgress(): array {
    if (!file_exists(PROGRESS_FILE)) {
        return [];
    }
    $content = file_get_contents(PROGRESS_FILE);
    $data = json_decode($content, true);
    return $data ?? [];
}
```

## File Structure

```
manga-reader/               manga-reader-php/
├── app.py                  ├── index.php
├── requirements.txt        ├── config.php
├── launch.sh               ├── launch.sh
└── templates/              ├── .htaccess
    ├── library.html        ├── lib/
    └── reader.html         │   ├── MangaLibrary.php
                            │   ├── ArchiveHandler.php
                            │   ├── ThumbnailGenerator.php
                            │   ├── ProgressTracker.php
                            │   └── ArchiveException.php
                            ├── templates/
                            │   ├── library.php
                            │   └── reader.php
                            └── cache/
```

## Feature Parity

| Feature | Status |
|---------|--------|
| CBZ/ZIP reading | ✅ Full parity |
| CBR/RAR reading | ✅ Full parity (with unrar fallback) |
| Cover thumbnails | ✅ Full parity (GD/Imagick) |
| Progress tracking | ✅ Full parity |
| Next volume detection | ✅ Full parity |
| Natural sort | ✅ Full parity |
| Path traversal protection | ✅ Full parity |

## PHP-Specific Considerations

1. **RAR Support**: PHP has optional `rar` extension. If not available, falls back to `unrar` CLI command.

2. **Image Processing**: Uses GD (built-in) or Imagick (optional) extension. Thumbnails skip if neither available.

3. **URL Rewriting**: Apache `.htaccess` included. For Nginx, use:
   ```nginx
   location / {
       try_files $uri $uri/ /index.php?$query_string;
   }
   ```

4. **Development Server**: PHP has built-in server (`php -S localhost:8080`)—no Flask needed.
