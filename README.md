# Manga Reader PHP

A web-based manga reader supporting CBZ, CBR, ZIP, and RAR formats. PHP port of the original Python manga-reader.

## Features

- Browse manga library from local folders
- Read manga in CBZ, ZIP, CBR, and RAR archives
- Automatic cover thumbnail generation (requires GD or Imagick)
- Reading progress tracking
- Automatic next volume navigation
- Keyboard navigation (arrow keys, spacebar)
- Click-to-turn pages

## Requirements

- PHP 8.0+
- Extensions:
  - `zip` (required for CBZ/ZIP support)
  - `rar` (optional, for CBR support) OR `unrar` command-line tool
  - `gd` or `imagick` (optional, for thumbnail generation)

## Installation

```bash
cd manga-reader-php
./launch.sh
```

Or with a web server:

```bash
# Using PHP built-in server
php -S localhost:8080

# Or place in your web server document root
```

## Configuration

Environment variables:

| Variable | Description | Default |
|----------|-------------|---------|
| `MANGA_ROOT` | Path to manga directory | `~/Documents` |
| `CACHE_DIR` | Path for thumbnail cache | `./cache` |

Example:
```bash
MANGA_ROOT=/path/to/manga ./launch.sh
```

## Supported Formats

- **Archives**: CBZ, ZIP, CBR, RAR
- **Images**: JPG, JPEG, PNG, WebP, GIF, BMP, TIFF

## API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `GET /` | - | Library browser |
| `GET /read/<path>` | - | Reader page |
| `GET /api/library` | GET | Get all manga series |
| `GET /api/manga/<path>/pages` | GET | Get pages for a manga |
| `GET /api/manga/<path>/page/<page>` | GET | Get specific page image |
| `GET /api/manga/<path>/cover` | GET | Get cover thumbnail |
| `GET /api/progress` | GET | Get reading progress |
| `POST /api/progress` | POST | Save reading progress |

## Directory Structure

```
manga-reader-php/
├── index.php           # Main entry point
├── config.php          # Configuration
├── launch.sh           # Launcher script
├── .htaccess           # Apache rewrite rules
├── lib/
│   ├── MangaLibrary.php      # Library scanning
│   ├── ArchiveHandler.php    # Archive extraction
│   ├── ThumbnailGenerator.php # Cover thumbnails
│   └── ProgressTracker.php   # Reading progress
├── templates/
│   ├── library.php     # Library view
│   └── reader.php      # Reader view
└── cache/              # Thumbnails and progress
```

## Differences from Python Version

| Feature | Python | PHP |
|---------|--------|-----|
| Archive handling | `zipfile`/`rarfile` | Native `ZipArchive` + `unrar` CLI |
| Image processing | Pillow | GD/Imagick |
| Framework | Flask | Vanilla PHP |
| Caching | `@lru_cache` | In-memory + file cache |

## License

MIT
