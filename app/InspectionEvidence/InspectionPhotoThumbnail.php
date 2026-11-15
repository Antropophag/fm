<?php
declare(strict_types=1);

namespace FMonitor2\InspectionEvidence;

final class InspectionPhotoThumbnail
{
    private const MAX_SIDE = 640;
    private const MAX_PIXELS = 40_000_000;
    private const RENDERER = 'gd-640-jpeg82-v1';

    /** @return array{bytes:string,mime:string,width:int,height:int} */
    public function render(string $sourcePath, string $sourceSha256, string $cacheRoot): array
    {
        if (!extension_loaded('gd') || preg_match('/^[a-f0-9]{64}$/D', $sourceSha256) !== 1) {
            throw new \RuntimeException('Thumbnail renderer unavailable.');
        }
        if ($sourcePath === '' || is_link($sourcePath) || !is_file($sourcePath)) {
            throw new \RuntimeException('Invalid photo source.');
        }
        $source = file_get_contents($sourcePath);
        if (!is_string($source) || !hash_equals($sourceSha256, hash('sha256', $source))) {
            throw new \RuntimeException('Invalid photo source digest.');
        }
        $info = @getimagesizefromstring($source);
        if (!is_array($info) || !isset($info[0], $info[1], $info['mime'])) {
            throw new \RuntimeException('Invalid photo raster.');
        }
        $width = (int) $info[0];
        $height = (int) $info[1];
        if ($width < 1 || $height < 1 || $width > intdiv(self::MAX_PIXELS, $height)
            || !in_array($info['mime'], ['image/jpeg', 'image/png', 'image/webp'], true)) {
            throw new \RuntimeException('Photo raster exceeds thumbnail bounds.');
        }

        $scale = min(1.0, self::MAX_SIDE / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));
        $this->guardMemory($width, $height, $targetWidth, $targetHeight);
        $key = hash('sha256', self::RENDERER . "\0" . $sourceSha256 . "\0" . $info['mime']);
        $directory = rtrim($cacheRoot, '/') . '/checklist-thumbnails';
        $path = $directory . '/' . $key . '.bin';
        $metaPath = $directory . '/' . $key . '.json';
        $cached = $this->cached($path, $metaPath, $targetWidth, $targetHeight);
        if ($cached !== null) return $cached;

        $image = @imagecreatefromstring($source);
        if (!$image instanceof \GdImage) throw new \RuntimeException('Invalid photo raster.');
        try {
            $target = imagecreatetruecolor($targetWidth, $targetHeight);
            if (!$target instanceof \GdImage) throw new \RuntimeException('Thumbnail allocation failed.');
            try {
                imagealphablending($target, false);
                imagesavealpha($target, true);
                $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
                imagefill($target, 0, 0, $transparent);
                if (!imagecopyresampled($target, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height)) {
                    throw new \RuntimeException('Thumbnail resize failed.');
                }
                ob_start();
                $ok = match ($info['mime']) {
                    'image/jpeg' => imagejpeg($target, null, 82),
                    'image/png' => imagepng($target, null, 6),
                    'image/webp' => imagewebp($target, null, 82),
                };
                $bytes = ob_get_clean();
                if (!$ok || !is_string($bytes) || $bytes === '') throw new \RuntimeException('Thumbnail encode failed.');
            } finally {
                unset($target);
            }
        } finally {
            unset($image);
        }

        $this->publish($directory, $path, $bytes);
        $meta = json_encode(['mime' => $info['mime'], 'width' => $targetWidth, 'height' => $targetHeight], JSON_THROW_ON_ERROR);
        $this->publish($directory, $metaPath, $meta);
        return ['bytes' => $bytes, 'mime' => $info['mime'], 'width' => $targetWidth, 'height' => $targetHeight];
    }

    /** @return array{bytes:string,mime:string,width:int,height:int}|null */
    private function cached(string $path, string $metaPath, int $width, int $height): ?array
    {
        if (is_link($path) || is_link($metaPath) || !is_file($path) || !is_file($metaPath)) return null;
        $bytes = file_get_contents($path); $raw = file_get_contents($metaPath);
        if (!is_string($bytes) || $bytes === '' || !is_string($raw)) return null;
        try { $meta = json_decode($raw, true, 8, JSON_THROW_ON_ERROR); } catch (\JsonException) { return null; }
        if (!is_array($meta) || ($meta['width'] ?? null) !== $width || ($meta['height'] ?? null) !== $height
            || !in_array($meta['mime'] ?? null, ['image/jpeg', 'image/png', 'image/webp'], true)) return null;
        $actual = @getimagesizefromstring($bytes);
        if (!is_array($actual) || (int)($actual[0] ?? 0) !== $width || (int)($actual[1] ?? 0) !== $height
            || ($actual['mime'] ?? null) !== $meta['mime']) return null;
        return ['bytes' => $bytes, 'mime' => $meta['mime'], 'width' => $width, 'height' => $height];
    }

    private function publish(string $directory, string $path, string $bytes): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) throw new \RuntimeException('Thumbnail cache unavailable.');
        if (is_link($directory) || is_link($path)) throw new \RuntimeException('Unsafe thumbnail cache.');
        $temporary = $directory . '/.' . basename($path) . '.' . bin2hex(random_bytes(8));
        try {
            if (file_put_contents($temporary, $bytes, LOCK_EX) !== strlen($bytes) || !chmod($temporary, 0600)) throw new \RuntimeException('Thumbnail cache write failed.');
            if (!rename($temporary, $path)) throw new \RuntimeException('Thumbnail cache publish failed.');
        } finally {
            if (is_file($temporary)) unlink($temporary);
        }
    }

    private function guardMemory(int $width, int $height, int $targetWidth, int $targetHeight): void
    {
        $required = $width * $height * 5 + $targetWidth * $targetHeight * 4 + 8 * 1024 * 1024;
        $hardBudget = 96 * 1024 * 1024;
        $limit = $this->bytes((string) ini_get('memory_limit'));
        $available = $limit === null ? $hardBudget : max(0, $limit - memory_get_usage(true));
        if ($required > min($hardBudget, $available)) throw new \RuntimeException('Photo raster exceeds thumbnail memory budget.');
    }

    private function bytes(string $value): ?int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') return null;
        if (preg_match('/^([0-9]+)\s*([KMG]?)$/iD', $value, $match) !== 1) return 0;
        $multiplier = match (strtoupper($match[2])) {'K'=>1024,'M'=>1024**2,'G'=>1024**3,default=>1};
        $number = (int) $match[1];
        return $number > intdiv(PHP_INT_MAX, $multiplier) ? PHP_INT_MAX : $number * $multiplier;
    }
}
