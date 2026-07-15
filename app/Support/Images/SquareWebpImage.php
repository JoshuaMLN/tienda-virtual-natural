<?php

namespace App\Support\Images;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class SquareWebpImage
{
    public function store(
        string $contents,
        string $directory,
        string $filenamePrefix,
        int $size = 512,
        int $quality = 88
    ): string {
        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            throw new InvalidArgumentException('El archivo no contiene una imagen valida.');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $cropSize = min($sourceWidth, $sourceHeight);
        $sourceX = (int) floor(($sourceWidth - $cropSize) / 2);
        $sourceY = (int) floor(($sourceHeight - $cropSize) / 2);
        $target = imagecreatetruecolor($size, $size);

        if ($target === false) {
            imagedestroy($source);

            throw new RuntimeException('No se pudo preparar la imagen.');
        }

        try {
            imagealphablending($target, false);
            imagesavealpha($target, true);
            $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
            imagefill($target, 0, 0, $transparent);

            imagecopyresampled(
                $target,
                $source,
                0,
                0,
                $sourceX,
                $sourceY,
                $size,
                $size,
                $cropSize,
                $cropSize
            );

            ob_start();
            $written = imagewebp($target, null, $quality);
            $webp = ob_get_clean();

            if (! $written || ! is_string($webp) || $webp === '') {
                throw new RuntimeException('No se pudo convertir la imagen a WebP.');
            }
        } finally {
            imagedestroy($source);
            imagedestroy($target);
        }

        $path = trim($directory, '/').'/'.Str::slug($filenamePrefix).'-'.Str::random(12).'.webp';

        if (! Storage::disk('public')->put($path, $webp)) {
            throw new RuntimeException('No se pudo guardar la imagen.');
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
