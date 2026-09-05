<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class ProductImageOptimizer
{
    private const MAX_DIMENSION = 1200;

    private const WEBP_QUALITY = 82;

    private const MAX_SOURCE_DIMENSION = 6000;

    public function store(UploadedFile $file): string
    {
        $contents = file_get_contents($file->getRealPath());
        $source = $contents === false ? false : imagecreatefromstring($contents);

        if ($source === false) {
            throw new RuntimeException('The uploaded product image could not be processed.');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        if ($sourceWidth > self::MAX_SOURCE_DIMENSION || $sourceHeight > self::MAX_SOURCE_DIMENSION) {
            imagedestroy($source);

            throw new RuntimeException('Product images may not exceed 6000 × 6000 pixels.');
        }

        $canvasSize = min(self::MAX_DIMENSION, max($sourceWidth, $sourceHeight));
        $scale = min($canvasSize / $sourceWidth, $canvasSize / $sourceHeight, 1);
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));
        $x = (int) floor(($canvasSize - $width) / 2);
        $y = (int) floor(($canvasSize - $height) / 2);
        $canvas = imagecreatetruecolor($canvasSize, $canvasSize);

        if ($canvas === false) {
            imagedestroy($source);

            throw new RuntimeException('The product image canvas could not be created.');
        }

        $background = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $background);
        imagecopyresampled($canvas, $source, $x, $y, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

        ob_start();
        $encoded = imagewebp($canvas, null, self::WEBP_QUALITY);
        $optimized = ob_get_clean();
        imagedestroy($source);
        imagedestroy($canvas);

        if (! $encoded || ! is_string($optimized)) {
            throw new RuntimeException('The product image could not be converted to WebP.');
        }

        $path = 'products/'.Str::ulid().'.webp';
        Storage::disk('public')->put($path, $optimized, ['visibility' => 'public']);

        return $path;
    }
}
