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
| `CACHE_DIR` | Path for private thumbnails/progress | `./cache` |
| `MANGA_PASSWORD_HASH` | Password hash required for remote hosting | unset |

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

## Private hosting and reliable saves

The PHP development server remains passwordless only for requests from localhost with a localhost Host header. Remote hosting requires `MANGA_PASSWORD_HASH` and HTTPS; the browser prompts for HTTP Basic authentication (the username is ignored). Generate the hash from a securely entered password, and configure it in the server environment. Never put a plaintext password in source or shell history. Use the supplied Apache rules, which deny direct access to cache, internal code, and configuration files. Other servers need equivalent private-path restrictions. This app currently expects to be mounted at `/`.

Progress writes lock the full read/modify/write operation and replace the JSON file atomically. Corrupt files are preserved for recovery. The reader reports failed saves and offers a retry. Copy `CACHE_DIR/reading_progress.json` to a private backup location to back up progress; stop writes before restoring it.

Run `python3 tests/regression.py` with PHP and ZIP support to check traversal boundaries, remote access, cross-site writes, invalid pages, failed saves and concurrent updates.
