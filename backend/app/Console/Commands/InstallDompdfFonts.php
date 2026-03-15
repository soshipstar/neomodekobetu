<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\File;

class InstallDompdfFonts extends Command
{
    protected $signature = 'dompdf:install-fonts';
    protected $description = 'Install IPA Gothic font into DomPDF font cache for Japanese PDF rendering';

    public function handle(): int
    {
        $fontCacheDir = storage_path('fonts');

        // Ensure font cache directory exists
        if (! File::isDirectory($fontCacheDir)) {
            File::makeDirectory($fontCacheDir, 0755, true);
            $this->info("Created font cache directory: {$fontCacheDir}");
        }

        // Search for IPA Gothic font in system
        $searchPaths = [
            '/usr/share/fonts/opentype/ipafont-gothic/ipag.ttf',
            '/usr/share/fonts/truetype/ipafont-gothic/ipag.ttf',
            '/usr/share/fonts/opentype/ipafont-gothic/ipagp.ttf',
            '/usr/share/fonts/truetype/ipafont-gothic/ipagp.ttf',
        ];

        $foundFont = null;
        foreach ($searchPaths as $path) {
            if (file_exists($path)) {
                $foundFont = $path;
                $this->info("Found IPA Gothic font at: {$path}");
                break;
            }
        }

        if (! $foundFont) {
            // Try glob search
            $dirs = [
                '/usr/share/fonts/opentype/ipafont-gothic/',
                '/usr/share/fonts/truetype/ipafont-gothic/',
                '/usr/share/fonts/opentype/',
                '/usr/share/fonts/truetype/',
            ];

            foreach ($dirs as $dir) {
                if (is_dir($dir)) {
                    $files = glob($dir . 'ipag*') ?: [];
                    if (! empty($files)) {
                        $foundFont = $files[0];
                        $this->info("Found IPA Gothic font at: {$foundFont}");
                        break;
                    }
                }
            }
        }

        if (! $foundFont) {
            $this->error('IPA Gothic font not found. Install with: apt-get install fonts-ipafont-gothic');
            return self::FAILURE;
        }

        // Initialize DomPDF with our config settings
        $options = new Options();
        $options->set('fontDir', $fontCacheDir);
        $options->set('fontCache', $fontCacheDir);
        $options->set('isRemoteEnabled', true);
        $options->set('chroot', base_path());

        $dompdf = new Dompdf($options);
        $fontMetrics = $dompdf->getFontMetrics();

        // Register font using file:// protocol (required by DomPDF's registerFont)
        $fontUri = 'file://' . $foundFont;

        $variants = [
            ['family' => 'ipag', 'style' => 'normal', 'weight' => 'normal'],
            ['family' => 'ipag', 'style' => 'normal', 'weight' => 'bold'],
            ['family' => 'ipag', 'style' => 'italic', 'weight' => 'normal'],
            ['family' => 'ipag', 'style' => 'italic', 'weight' => 'bold'],
        ];

        foreach ($variants as $variant) {
            $result = $fontMetrics->registerFont($variant, $fontUri);
            if ($result) {
                $this->info("Registered ipag ({$variant['weight']} {$variant['style']})");
            } else {
                $this->warn("Failed to register ipag ({$variant['weight']} {$variant['style']})");
            }
        }

        // Save the font families to the cache file
        $fontMetrics->saveFontFamilies();

        $this->info('IPA Gothic font installation complete.');
        $this->info("Font cache: {$fontCacheDir}");

        return self::SUCCESS;
    }
}
