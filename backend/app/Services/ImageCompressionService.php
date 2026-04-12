<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * 画像を指定バイト数以下になるように圧縮する (PHP GD ベース)。
 *
 * 戦略:
 * 1. 元画像を GD リソースに読み込む (jpeg/png/webp)
 * 2. 最大幅 $maxWidth を超える場合はリサイズ (アスペクト維持)
 * 3. JPEG quality を 85 → 10 まで段階的に下げながら保存
 * 4. 目標以下になった時点で確定。それでも達成できない場合は
 *    最大幅をさらに縮めてリトライ
 */
class ImageCompressionService
{
    /**
     * @return array{path: string, size: int, width: int, height: int, mime: string}
     */
    public function compressToTarget(
        string $sourcePath,
        string $destPath,
        int $targetBytes = 102400,
        int $maxWidth = 1600,
    ): array {
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('GD extension is required for image compression.');
        }

        $info = @getimagesize($sourcePath);
        if ($info === false) {
            throw new \RuntimeException('Unable to read image metadata.');
        }
        [$origW, $origH] = $info;
        $mime = $info['mime'] ?? '';

        $img = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png'  => @imagecreatefrompng($sourcePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            'image/gif'  => @imagecreatefromgif($sourcePath),
            default => false,
        };
        if ($img === false) {
            throw new \RuntimeException("Unsupported image format: {$mime}");
        }

        // EXIF orientation 補正 (JPEG のみ)
        if ($mime === 'image/jpeg' && function_exists('exif_read_data')) {
            $exif = @exif_read_data($sourcePath);
            if ($exif && !empty($exif['Orientation'])) {
                $img = $this->applyExifOrientation($img, (int) $exif['Orientation']);
                $origW = imagesx($img);
                $origH = imagesy($img);
            }
        }

        // 可変の最大幅と quality で繰り返し試行
        $attempts = [
            ['width' => $maxWidth, 'quality' => 85],
            ['width' => $maxWidth, 'quality' => 70],
            ['width' => $maxWidth, 'quality' => 55],
            ['width' => $maxWidth, 'quality' => 40],
            ['width' => min($maxWidth, 1280), 'quality' => 55],
            ['width' => min($maxWidth, 1024), 'quality' => 55],
            ['width' => min($maxWidth, 800), 'quality' => 60],
            ['width' => min($maxWidth, 640), 'quality' => 60],
            ['width' => min($maxWidth, 480), 'quality' => 65],
        ];

        $finalSize = 0;
        $finalW = $origW;
        $finalH = $origH;

        foreach ($attempts as $att) {
            $resized = $this->resizeIfNeeded($img, $origW, $origH, $att['width']);
            $success = imagejpeg($resized, $destPath, $att['quality']);
            if ($resized !== $img) {
                imagedestroy($resized);
            }
            if ($success) {
                clearstatcache(true, $destPath);
                $finalSize = filesize($destPath);
                $finalW = imagesx($resized);
                $finalH = imagesy($resized);
                if ($finalSize <= $targetBytes) {
                    break;
                }
            }
        }

        imagedestroy($img);

        if ($finalSize === 0) {
            throw new \RuntimeException('Image compression failed.');
        }

        if ($finalSize > $targetBytes) {
            Log::warning('ImageCompressionService: unable to reach target size', [
                'target' => $targetBytes,
                'actual' => $finalSize,
                'path' => $destPath,
            ]);
        }

        return [
            'path' => $destPath,
            'size' => $finalSize,
            'width' => $finalW,
            'height' => $finalH,
            'mime' => 'image/jpeg',
        ];
    }

    /**
     * @param \GdImage $img
     */
    private function resizeIfNeeded($img, int $origW, int $origH, int $maxWidth)
    {
        if ($origW <= $maxWidth) {
            return $img;
        }
        $ratio = $maxWidth / $origW;
        $newW = $maxWidth;
        $newH = (int) round($origH * $ratio);
        $dst = imagecreatetruecolor($newW, $newH);
        // 白背景 (透過 PNG 対策)
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $newW, $newH, $white);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $newW, $newH, $origW, $origH);
        return $dst;
    }

    /**
     * @param \GdImage $img
     */
    private function applyExifOrientation($img, int $orientation)
    {
        return match ($orientation) {
            3 => imagerotate($img, 180, 0),
            6 => imagerotate($img, -90, 0),
            8 => imagerotate($img, 90, 0),
            default => $img,
        };
    }
}
