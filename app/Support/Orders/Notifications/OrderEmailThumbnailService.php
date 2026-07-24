<?php

namespace App\Support\Orders\Notifications;

use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class OrderEmailThumbnailService
{
    public const SIZE = 96;

    public const TARGET_MAX_BYTES = 25_000;

    private const MAX_SOURCE_BYTES = 10_000_000;

    private const REMOTE_CACHE_DIRECTORY = 'order-email-thumbnails';

    /** @var list<int> */
    private const JPEG_QUALITIES = [78, 70, 62];

    public function make(?string $snapshotPath): OrderEmailThumbnail
    {
        $candidates = array_values(array_unique(array_filter([
            trim((string) $snapshotPath),
            Product::DEFAULT_IMAGE,
        ])));

        foreach ($candidates as $candidate) {
            try {
                if ($this->isRemote($candidate)) {
                    $cached = $this->cachedRemoteThumbnail($candidate);

                    if ($cached !== null) {
                        return $this->thumbnail($cached);
                    }

                    $source = $this->remoteContents($candidate);
                } else {
                    $source = $this->localContents($candidate);
                }

                if ($source === null) {
                    continue;
                }

                $jpeg = $this->squareJpeg($source);

                if ($this->isRemote($candidate)) {
                    $this->cacheRemoteThumbnail($candidate, $jpeg);
                }

                return $this->thumbnail($jpeg);
            } catch (Throwable) {
                continue;
            }
        }

        return $this->thumbnail($this->neutralJpeg());
    }

    private function localContents(string $path): ?string
    {
        $path = str_replace('\\', '/', trim($path));

        if ($path === '' || $this->isRemote($path) || str_contains($path, '..')) {
            return null;
        }

        $normalized = ltrim($path, '/');

        if (Str::startsWith($normalized, 'images/')) {
            $absolutePath = public_path($normalized);

            if (! is_file($absolutePath)
                || filesize($absolutePath) === false
                || filesize($absolutePath) > self::MAX_SOURCE_BYTES) {
                return null;
            }

            $contents = file_get_contents($absolutePath);

            return is_string($contents) && $contents !== '' ? $contents : null;
        }

        $disk = Storage::disk('public');

        if (! $disk->exists($normalized)
            || $disk->size($normalized) > self::MAX_SOURCE_BYTES) {
            return null;
        }

        $contents = $disk->get($normalized);

        return $contents !== '' ? $contents : null;
    }

    private function remoteContents(string $url): ?string
    {
        if (! $this->isAllowedRemoteUrl($url)) {
            return null;
        }

        $response = Http::accept('image/avif,image/webp,image/png,image/jpeg')
            ->withHeaders(['User-Agent' => config('app.name').'/order-email'])
            ->connectTimeout(max(1, (int) config('mail.order_images.connect_timeout', 3)))
            ->timeout(max(1, (int) config('mail.order_images.timeout', 5)))
            ->withOptions(['allow_redirects' => false])
            ->get($url);

        if (! $response->successful()) {
            return null;
        }

        $contentType = Str::lower((string) $response->header('Content-Type'));
        $contentLength = (int) $response->header('Content-Length', 0);
        $contents = $response->body();

        if (! Str::startsWith($contentType, 'image/')
            || ($contentLength > 0 && $contentLength > self::MAX_SOURCE_BYTES)
            || $contents === ''
            || strlen($contents) > self::MAX_SOURCE_BYTES) {
            return null;
        }

        return $contents;
    }

    private function isAllowedRemoteUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (! is_array($parts)
            || Str::lower((string) ($parts['scheme'] ?? '')) !== 'https'
            || isset($parts['user'])
            || isset($parts['pass'])
            || (isset($parts['port']) && (int) $parts['port'] !== 443)) {
            return false;
        }

        $host = Str::lower((string) ($parts['host'] ?? ''));
        $allowedHosts = config('mail.order_images.remote_hosts', []);

        return $host !== ''
            && is_array($allowedHosts)
            && in_array($host, $allowedHosts, true);
    }

    private function cachedRemoteThumbnail(string $url): ?string
    {
        $disk = Storage::disk('local');
        $path = $this->remoteCachePath($url);

        if (! $disk->exists($path)
            || $disk->size($path) <= 0
            || $disk->size($path) > self::TARGET_MAX_BYTES) {
            return null;
        }

        $contents = $disk->get($path);
        $size = @getimagesizefromstring($contents);

        if (! is_array($size)
            || $size[0] !== self::SIZE
            || $size[1] !== self::SIZE
            || ($size['mime'] ?? null) !== 'image/jpeg') {
            $disk->delete($path);

            return null;
        }

        return $contents;
    }

    private function cacheRemoteThumbnail(string $url, string $contents): void
    {
        Storage::disk('local')->put($this->remoteCachePath($url), $contents);
    }

    private function remoteCachePath(string $url): string
    {
        return self::REMOTE_CACHE_DIRECTORY.'/'.hash('sha256', $url).'.jpg';
    }

    private function isRemote(string $path): bool
    {
        return Str::startsWith(Str::lower(trim($path)), ['http://', 'https://']);
    }

    private function squareJpeg(string $contents): string
    {
        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            throw new RuntimeException('No se pudo leer la imagen del producto.');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $cropSize = min($sourceWidth, $sourceHeight);
        $sourceX = (int) floor(($sourceWidth - $cropSize) / 2);
        $sourceY = (int) floor(($sourceHeight - $cropSize) / 2);
        $target = imagecreatetruecolor(self::SIZE, self::SIZE);

        if ($target === false) {
            imagedestroy($source);

            throw new RuntimeException('No se pudo preparar la miniatura del producto.');
        }

        try {
            $white = imagecolorallocate($target, 255, 255, 255);
            imagefill($target, 0, 0, $white);
            imagecopyresampled(
                $target,
                $source,
                0,
                0,
                $sourceX,
                $sourceY,
                self::SIZE,
                self::SIZE,
                $cropSize,
                $cropSize,
            );

            $lastJpeg = null;

            foreach (self::JPEG_QUALITIES as $quality) {
                ob_start();
                $written = imagejpeg($target, null, $quality);
                $jpeg = ob_get_clean();

                if (! $written || ! is_string($jpeg) || $jpeg === '') {
                    continue;
                }

                $lastJpeg = $jpeg;

                if (strlen($jpeg) <= self::TARGET_MAX_BYTES) {
                    return $jpeg;
                }
            }

            if ($lastJpeg !== null) {
                throw new RuntimeException('La miniatura supera el peso permitido.');
            }
        } finally {
            imagedestroy($source);
            imagedestroy($target);
        }

        throw new RuntimeException('No se pudo generar la miniatura del producto.');
    }

    private function neutralJpeg(): string
    {
        $image = imagecreatetruecolor(self::SIZE, self::SIZE);

        if ($image === false) {
            throw new RuntimeException('No se pudo generar el placeholder del correo.');
        }

        try {
            $background = imagecolorallocate($image, 242, 245, 238);
            $accent = imagecolorallocate($image, 77, 124, 58);
            imagefill($image, 0, 0, $background);
            imagefilledellipse($image, 48, 48, 34, 34, $accent);
            ob_start();
            $written = imagejpeg($image, null, 78);
            $jpeg = ob_get_clean();

            if (! $written || ! is_string($jpeg) || $jpeg === '') {
                throw new RuntimeException('No se pudo codificar el placeholder del correo.');
            }

            return $jpeg;
        } finally {
            imagedestroy($image);
        }
    }

    private function thumbnail(string $contents): OrderEmailThumbnail
    {
        $fingerprint = hash('sha256', $contents);

        return new OrderEmailThumbnail(
            contents: $contents,
            fingerprint: $fingerprint,
            filename: 'producto-'.substr($fingerprint, 0, 16).'.jpg',
        );
    }
}
