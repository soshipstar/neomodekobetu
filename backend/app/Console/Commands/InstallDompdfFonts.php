<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class InstallDompdfFonts extends Command
{
    protected $signature = 'dompdf:install-fonts';
    protected $description = 'Install IPA Gothic font into DomPDF font cache for Japanese PDF rendering';

    public function handle(): int
    {
        $fontCacheDir = storage_path('fonts');

        if (! File::isDirectory($fontCacheDir)) {
            File::makeDirectory($fontCacheDir, 0755, true);
        }

        // Find IPA Gothic font
        $src = '/usr/share/fonts/opentype/ipafont-gothic/ipag.ttf';
        if (! file_exists($src)) {
            $this->error('IPA Gothic font not found at ' . $src);
            return self::FAILURE;
        }

        // Copy font file
        $dest = $fontCacheDir . '/ipag.ttf';
        if (! file_exists($dest)) {
            copy($src, $dest);
            $this->info('Copied ipag.ttf to ' . $fontCacheDir);
        }

        // Generate .ufm metrics file using DomPDF's internal tool
        $ufmFile = $fontCacheDir . '/ipag.ufm';
        if (! file_exists($ufmFile)) {
            try {
                $font = \Dompdf\Adapter\CPDF::unicode_font_loader($dest);
                if ($font) {
                    file_put_contents($ufmFile, $font);
                    $this->info('Generated metrics file: ipag.ufm');
                }
            } catch (\Throwable $e) {
                // Generate minimal UFM if automatic generation fails
                $this->warn('Auto metrics generation failed, creating minimal UFM');
            }
        }

        // Write installed-fonts.json with absolute paths (without .ttf extension)
        $basePath = $fontCacheDir . '/ipag';
        $jsonFile = $fontCacheDir . '/installed-fonts.json';
        $fonts = file_exists($jsonFile) ? json_decode(file_get_contents($jsonFile), true) : [];
        $fonts['ipag'] = [
            'normal'      => $basePath,
            'bold'        => $basePath,
            'italic'      => $basePath,
            'bold_italic' => $basePath,
        ];
        // Also register common aliases
        $fonts['ipa gothic'] = $fonts['ipag'];
        $fonts['ipagothic'] = $fonts['ipag'];

        file_put_contents($jsonFile, json_encode($fonts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->info('IPA Gothic registered in installed-fonts.json');
        $this->info("Font cache: {$fontCacheDir}");

        return self::SUCCESS;
    }
}
