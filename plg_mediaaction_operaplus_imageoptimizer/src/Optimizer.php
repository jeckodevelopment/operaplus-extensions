<?php
namespace OperaPlus\Plugin\MediaAction\ImageOptimizer;
defined('_JEXEC') or die;

/** Bounded conversion. Never writes an upload or mutates caller data. */
final class Optimizer
{
    public static function convert(string $data, string $extension, int $quality = 90, int $maxEdge = 1920): ?string
    {
        if (!function_exists('imagewebp') || !function_exists('imagecreatefromstring')
            || strlen($data) > 20 * 1024 * 1024 || !in_array($extension, ['jpg', 'jpeg', 'png'], true)) {
            return null;
        }
        $info = @getimagesizefromstring($data);
        if (!$info || $info[2] !== ($extension === 'png' ? IMAGETYPE_PNG : IMAGETYPE_JPEG)) {
            return null;
        }
        [$w, $h] = $info;
        if ($w < 1 || $h < 1 || $w > 10000 || $h > 10000 || $w * $h > 12000000
            || ($extension === 'png' && str_contains($data, 'acTL'))) {
            return null;
        }
        $limit = trim((string) ini_get('memory_limit'));
        if ($limit !== '-1' && preg_match('/^(\d+)\s*([KMG]?)$/i', $limit, $m)) {
            $bytes = (int) $m[1] * (1024 ** (['' => 0, 'K' => 1, 'M' => 2, 'G' => 3][strtoupper($m[2])]));
            if ($bytes - memory_get_usage(true) < 12 * $w * $h + 3 * strlen($data) + 16 * 1024 * 1024) {
                return null;
            }
        }
        try {
            $orientation = 1;
            if ($extension !== 'png') {
                if (!function_exists('exif_read_data')) { return null; }
                $stream = fopen('php://temp', 'w+b');
                if (!$stream) { return null; }
                try {
                    fwrite($stream, $data);
                    rewind($stream);
                    $exif = @exif_read_data($stream, 'IFD0');
                    $orientation = (int) ($exif['Orientation'] ?? 1);
                } finally { fclose($stream); }
            }
            $image = @imagecreatefromstring($data);
            if (!$image) { return null; }
            if (in_array($orientation, [2, 5, 7], true)) { imageflip($image, IMG_FLIP_HORIZONTAL); }
            if ($orientation === 4) { imageflip($image, IMG_FLIP_VERTICAL); }
            $angle = match ($orientation) { 3 => 180, 5, 8 => 90, 6, 7 => -90, default => 0 };
            if ($angle !== 0) {
                $rotated = imagerotate($image, $angle, 0);
                if (!$rotated) { return null; }
                $image = $rotated;
            }
            $w = imagesx($image); $h = imagesy($image);
            $edge = in_array($maxEdge, [0, 1200, 1600, 1920, 2560], true) ? $maxEdge : 1920;
            $scale = $edge > 0 ? min(1, $edge / max($w, $h)) : 1;
            $nw = max(1, (int) round($w * $scale)); $nh = max(1, (int) round($h * $scale));
            $target = imagecreatetruecolor($nw, $nh);
            if (!$target) { return null; }
            imagealphablending($target, false);
            imagesavealpha($target, true);
            imagefill($target, 0, 0, imagecolorallocatealpha($target, 0, 0, 0, 127));
            if (!imagecopyresampled($target, $image, 0, 0, 0, 0, $nw, $nh, $w, $h)) { return null; }
            ob_start();
            try {
                $ok = @imagewebp($target, null, max(50, min(100, $quality)));
                $webp = ob_get_contents();
            } finally { ob_end_clean(); }
            if (!$ok || !is_string($webp) || strlen($webp) >= strlen($data)) { return null; }
            $out = @getimagesizefromstring($webp);
            return $out && $out[2] === IMAGETYPE_WEBP && $out[0] === $nw && $out[1] === $nh ? $webp : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    public static function excluded(string $path, string $folders): bool
    {
        $path = rtrim(strtolower('images/' . trim($path, '/')), '/');
        foreach (preg_split('/[\r\n,]+/', $folders) ?: [] as $folder) {
            $folder = strtolower(trim(trim($folder), '/'));
            if ($folder !== '' && ($path === $folder || str_starts_with($path, $folder . '/'))) { return true; }
        }
        return false;
    }
}
