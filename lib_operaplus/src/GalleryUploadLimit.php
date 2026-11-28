<?php
declare(strict_types=1);

namespace OperaPlus\Gallery;

defined('_JEXEC') or die;

final class UploadLimit
{
    public const MAX_BYTES = 20 * 1024 * 1024;

    /**
     * Each image is sent as a separate multipart POST. Reserve 64 KiB
     * for its headers, CSRF token and other form fields.
     */
    public static function bytes(float $mediaMiB, string $upload, string $post, bool $enabled = true): int
    {
        if (!$enabled) {
            return 0;
        }
        $limit = self::MAX_BYTES;
        if ($mediaMiB > 0) {
            $limit = min($limit, (int) floor($mediaMiB * 1024 * 1024));
        }
        $uploadBytes = ini_parse_quantity($upload);
        if ($uploadBytes <= 0) {
            return 0;
        }
        $limit = min($limit, $uploadBytes);
        $postBytes = ini_parse_quantity($post);
        if ($postBytes > 0) {
            $limit = min($limit, max(0, $postBytes - 64 * 1024));
        }
        return max(0, $limit);
    }
}
