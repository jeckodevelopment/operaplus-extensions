<?php
declare(strict_types=1);

namespace OperaPlus\Gallery;

defined('_JEXEC') or die;

/**
 * Local gallery storage. The caller MUST authenticate, validate CSRF,
 * authorise the article and enforce Joomla media policy before calling writes.
 * No HTTP endpoint is exposed by this class.
 */
final class Store
{
    public const MAX_IMAGES = 60;
    private string $root;
    private \Closure $convert;

    public function __construct(string $imagesRoot, callable $convert)
    {
        $root = realpath($imagesRoot);
        if ($root === false || !is_dir($root)) {
            throw new \RuntimeException('Invalid images root');
        }
        $this->root = $root;
        $this->convert = \Closure::fromCallable($convert);
    }

    public static function validId(string $id): bool
    {
        return preg_match('/^[a-f0-9]{24}$/D', $id) === 1;
    }

    private function folder(int $article, string $gallery, bool $create = false): string
    {
        if ($article < 1 || !self::validId($gallery)) {
            throw new \InvalidArgumentException('Invalid gallery reference');
        }
        $path = $this->root;
        foreach (['galleries', 'article-' . $article, 'gallery-' . $gallery] as $segment) {
            $path .= DIRECTORY_SEPARATOR . $segment;
            if (is_link($path)) {
                throw new \RuntimeException('Invalid gallery path');
            }
            if ($create && !is_dir($path) && !@mkdir($path, 0755) && !is_dir($path)) {
                throw new \RuntimeException('Cannot create gallery folder');
            }
            if (!is_dir($path) || realpath($path) !== $path) {
                throw new \RuntimeException('Gallery folder unavailable');
            }
        }
        return $path;
    }

    private function metadata(string $folder): array
    {
        $file = $folder . '/gallery.json';
        if (is_link($file) || !is_file($file) || filesize($file) > 524288) {
            throw new \RuntimeException('Gallery metadata unavailable');
        }
        $raw = file_get_contents($file);
        if ($raw === false) {
            throw new \RuntimeException('Cannot read gallery');
        }
        $doc = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($doc) || ($doc['schema'] ?? null) !== 1
            || !is_array($doc['images'] ?? null) || !array_is_list($doc['images'])
            || count($doc['images']) > self::MAX_IMAGES || !is_string($doc['credit'] ?? null)) {
            throw new \RuntimeException('Invalid gallery metadata');
        }
        $seen = [];
        foreach ($doc['images'] as $photo) {
            if (!is_array($photo) || !is_string($photo['id'] ?? null)
                || !self::validId($photo['id']) || isset($seen[$photo['id']])) {
                throw new \RuntimeException('Invalid image reference');
            }
            $seen[$photo['id']] = true;
            foreach (['alt', 'caption', 'credit'] as $field) {
                if (!is_string($photo[$field] ?? null)) {
                    throw new \RuntimeException('Invalid gallery text');
                }
            }
        }
        return $doc;
    }

    private function writeMetadata(string $folder, array $doc): void
    {
        $json = json_encode($doc, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        if (strlen($json) > 524288) {
            throw new \RuntimeException('Gallery metadata too large');
        }
        $file = $folder . '/gallery.json';
        $temp = $folder . '/.metadata-' . bin2hex(random_bytes(12));
        try {
            if (file_put_contents($temp, $json, LOCK_EX) !== strlen($json)
                || is_link($file) || !rename($temp, $file)) {
                throw new \RuntimeException('Cannot save gallery metadata');
            }
        } finally {
            if (is_file($temp)) { @unlink($temp); }
        }
    }

    private function locked(string $folder, callable $operation): mixed
    {
        $file = $folder . '/.gallery.lock';
        if (is_link($file)) {
            throw new \RuntimeException('Invalid gallery lock');
        }
        $lock = fopen($file, 'c');
        if ($lock === false) {
            throw new \RuntimeException('Cannot open gallery lock');
        }
        try {
            if (!flock($lock, LOCK_EX)) {
                throw new \RuntimeException('Cannot lock gallery');
            }
            return $operation();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public function create(int $article): string
    {
        $gallery = bin2hex(random_bytes(12));
        $folder = $this->folder($article, $gallery, true);
        $this->locked($folder, function () use ($folder): void {
            if (file_exists($folder . '/gallery.json')) {
                throw new \RuntimeException('Gallery already exists');
            }
            $this->writeMetadata($folder, ['schema' => 1, 'credit' => '', 'images' => []]);
        });
        return $gallery;
    }

    public function read(int $article, string $gallery): array
    {
        return $this->metadata($this->folder($article, $gallery));
    }

    private static function plainText(mixed $text, int $max = 1000): string
    {
        if (!is_string($text) || strlen($text) > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $text)
            || !preg_match('//u', $text)) {
            throw new \InvalidArgumentException('Invalid gallery text');
        }
        return trim($text);
    }

    public function add(int $article, string $gallery, string $data, string $extension, int $quality = 85): array
    {
        if (strlen($data) > 20 * 1024 * 1024 || !in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            throw new \InvalidArgumentException('Invalid gallery image');
        }
        $folder = $this->folder($article, $gallery);
        // Conversion outside the lock: slow encoders cannot block other editors.
        $reason = null;
        $large = ($this->convert)($data, $extension, $quality, 1920, $reason);
        if (!is_string($large)) {
            throw new \RuntimeException('Image conversion failed: ' . ($reason ?? 'unknown'));
        }
        $thumb = ($this->convert)($data, $extension, $quality, 480, $reason);
        if (!is_string($thumb)) {
            throw new \RuntimeException('Thumbnail conversion failed: ' . ($reason ?? 'unknown'));
        }
        foreach ([1920 => $large, 480 => $thumb] as $max => $bytes) {
            $info = @getimagesizefromstring($bytes);
            if (!$info || $info[2] !== IMAGETYPE_WEBP || max($info[0], $info[1]) > $max) {
                throw new \RuntimeException('Invalid conversion result');
            }
        }
        return $this->locked($folder, function () use ($folder, $large, $thumb): array {
            $doc = $this->metadata($folder);
            if (count($doc['images']) >= self::MAX_IMAGES) {
                throw new \RuntimeException('Gallery image limit reached');
            }
            $id = bin2hex(random_bytes(12));
            $created = [];
            try {
                foreach ([$id . '.webp' => $large, $id . '-thumb.webp' => $thumb] as $name => $bytes) {
                    $file = $folder . '/' . $name;
                    $handle = @fopen($file, 'xb');
                    if ($handle === false) {
                        throw new \RuntimeException('Cannot create image');
                    }
                    $created[] = $file;
                    try {
                        if (fwrite($handle, $bytes) !== strlen($bytes)) {
                            throw new \RuntimeException('Cannot write image');
                        }
                    } finally { fclose($handle); }
                }
                $photo = ['id' => $id, 'alt' => '', 'caption' => '', 'credit' => ''];
                $doc['images'][] = $photo;
                $this->writeMetadata($folder, $doc);
                return $photo;
            } catch (\Throwable $e) {
                foreach ($created as $file) { @unlink($file); }
                throw $e;
            }
        });
    }

    /**
     * Replace the visible order and editorial text using existing photo IDs.
     * Omitted files are retained to avoid breaking another article revision.
     * New uploads must be included or the caller should refresh before saving.
     */
    public function update(int $article, string $gallery, array $images, string $credit, array $expectedIds): array
    {
        if (!array_is_list($images) || count($images) > self::MAX_IMAGES || !array_is_list($expectedIds)) {
            throw new \InvalidArgumentException('Invalid gallery update');
        }
        $folder = $this->folder($article, $gallery);
        return $this->locked($folder, function () use ($folder, $images, $credit, $expectedIds): array {
            $doc = $this->metadata($folder);
            $current = array_column($doc['images'], 'id');
            if ($current !== $expectedIds) {
                throw new \RuntimeException('Gallery changed; reload before editing');
            }
            $allowed = array_fill_keys($current, true);
            $updated = [];
            foreach ($images as $image) {
                $id = is_array($image) ? ($image['id'] ?? null) : null;
                if (!is_string($id) || !isset($allowed[$id])) {
                    throw new \InvalidArgumentException('Unknown or duplicate image');
                }
                unset($allowed[$id]);
                $updated[] = ['id' => $id, 'alt' => self::plainText($image['alt'] ?? ''),
                    'caption' => self::plainText($image['caption'] ?? ''), 'credit' => self::plainText($image['credit'] ?? '')];
            }
            $doc['images'] = $updated;
            $doc['credit'] = self::plainText($credit);
            $this->writeMetadata($folder, $doc);
            return $doc;
        });
    }

    public static function relativePath(int $article, string $gallery, string $image, bool $thumbnail): string
    {
        if ($article < 1 || !self::validId($gallery) || !self::validId($image)) {
            throw new \InvalidArgumentException('Invalid gallery path');
        }
        return 'images/galleries/article-' . $article . '/gallery-' . $gallery . '/' . $image
            . ($thumbnail ? '-thumb' : '') . '.webp';
    }
}
