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

# Generate font metrics by rendering a test PDF (creates .ufm files)
php -r '
require "vendor/autoload.php";
$app = require "bootstrap/app.php";
$kernel = $app->make(\Illuminate\Contracts\Http\Kernel::class);
$kernel->bootstrap();
$html = "<html><head><style>@font-face { font-family: ipag; src: url(file:///var/www/html/storage/fonts/ipag.ttf); } body { font-family: ipag; }</style></head><body>テスト</body></html>";
try {
    $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)->setOption("isRemoteEnabled", true);
    $pdf->output();
    echo "DomPDF font metrics generated\n";
} catch (\Throwable $e) {
    echo "Font metrics generation skipped: " . $e->getMessage() . "\n";
}
' 2>/dev/null || true

# Execute the main command
exec "$@"
