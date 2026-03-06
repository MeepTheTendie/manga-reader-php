#!/bin/bash
# Manga Reader Launcher

cd "$(dirname "$0")"

echo "=============================================="
echo "           Manga Reader PHP"
echo "=============================================="
echo ""
echo "Manga folder: ${MANGA_ROOT:-$HOME/Documents}"
echo "Cache folder: ${CACHE_DIR:-$(pwd)/cache}"
echo ""

# Check PHP
if ! command -v php &> /dev/null; then
    echo "Error: PHP is not installed"
    exit 1
fi

PHP_VERSION=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
echo "PHP version: $PHP_VERSION"

# Check required extensions
MISSING=""

if ! php -m | grep -q "zip"; then
    MISSING="$MISSING zip"
fi

if ! php -m | grep -q "gd" && ! php -m | grep -q "imagick"; then
    echo "Warning: Neither GD nor Imagick extension found. Thumbnails will not be generated."
fi

if [ -n "$MISSING" ]; then
    echo "Error: Missing required PHP extensions:$MISSING"
    echo "Install them with your package manager (e.g., php-zip)"
    exit 1
fi

echo ""
echo "Required extensions: OK"
echo ""
echo "----------------------------------------------"
echo "Starting server on http://localhost:8080"
echo "Press Ctrl+C to stop"
echo "=============================================="
echo ""

php -S localhost:8080 index.php
