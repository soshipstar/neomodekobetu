#!/bin/bash
set -e

# Ensure storage directories exist
mkdir -p storage/framework/sessions storage/framework/views storage/framework/cache
mkdir -p storage/logs bootstrap/cache storage/fonts
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

# Ensure storage symlink exists (survives container recreation)
php artisan storage:link --force 2>/dev/null || true

# Copy IPA Gothic font to DomPDF font cache if not present
if [ ! -f storage/fonts/ipag.ttf ]; then
    SRC="/usr/share/fonts/opentype/ipafont-gothic/ipag.ttf"
    if [ -f "$SRC" ]; then
        cp "$SRC" storage/fonts/ipag.ttf
        echo "IPA Gothic font copied to storage/fonts/"
    fi
fi

# Register font in DomPDF's installed-fonts.json
php -r '
$fontDir = "storage/fonts";
$jsonFile = "$fontDir/installed-fonts.json";
$fonts = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];
if (!isset($fonts["ipag"])) {
    $fonts["ipag"] = [
        "normal" => "$fontDir/ipag.ttf",
        "bold" => "$fontDir/ipag.ttf",
        "italic" => "$fontDir/ipag.ttf",
        "bold_italic" => "$fontDir/ipag.ttf",
    ];
    file_put_contents($jsonFile, json_encode($fonts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    echo "IPA Gothic registered in DomPDF installed-fonts.json\n";
}
' 2>/dev/null || true

# Execute the main command
exec "$@"
