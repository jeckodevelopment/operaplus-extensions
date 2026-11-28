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
    public const STAGE_TTL = 86400;
    public const PURGE_GRACE = 86400;
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
        if (array_key_exists('stage_until', $doc) && (!is_int($doc['stage_until']) || $doc['stage_until'] < 1)) {
            throw new \RuntimeException('Invalid gallery stage');
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

    public function create(int $article, ?int $stageUntil = null): string
    {
        $gallery = bin2hex(random_bytes(12));
        $folder = $this->folder($article, $gallery, true);
        $this->locked($folder, function () use ($folder, $stageUntil): void {
            if (file_exists($folder . '/gallery.json')) {
                throw new \RuntimeException('Gallery already exists');
            }
            $doc = ['schema' => 1, 'credit' => '', 'images' => []];
            if ($stageUntil !== null) { $doc['stage_until'] = $stageUntil; }
            $this->writeMetadata($folder, $doc);
        });
        return $gallery;
    }

    /** Copy a gallery for editing without mutating a published article revision. */
    public function fork(int $article, string $source, ?int $stageUntil = null): string
    {
        $origin = $this->folder($article, $source);
        $new = $this->create($article, $stageUntil);
        $target = $this->folder($article, $new);
        try {
            $this->locked($origin, function () use ($origin, $target, $stageUntil): void {
                $doc = $this->metadata($origin);
                if (isset($doc['stage_until'])) { throw new \RuntimeException('Cannot fork an unfinished stage'); }
                if ($stageUntil !== null) { $doc['stage_until'] = $stageUntil; }
                foreach ($doc['images'] as $photo) {
                    foreach (['.webp', '-thumb.webp'] as $suffix) {
                        $name = $photo['id'] . $suffix;
                        $from = $origin . '/' . $name;
                        $to = $target . '/' . $name;
                        if (is_link($from) || !is_file($from) || filesize($from) > 20 * 1024 * 1024) {
                            throw new \RuntimeException('Invalid source photo');
                        }
                        $info = @getimagesize($from);
                        if (!$info || $info[2] !== IMAGETYPE_WEBP || !copy($from, $to)) {
                            throw new \RuntimeException('Cannot copy gallery photo');
                        }
                    }
                }
                $this->writeMetadata($target, $doc);
            });
            return $new;
        } catch (\Throwable $e) {
            $this->discard($article, $new);
            throw $e;
        }
    }

    /** Only call for an authenticated, session-owned, uncommitted gallery. */
    public function discard(int $article, string $gallery, bool $requireStage = false, ?int $expiredBefore = null): void
    {
        $folder = $this->folder($article, $gallery);
        $this->locked($folder, function () use ($folder, $requireStage, $expiredBefore): void {
            if ($requireStage || $expiredBefore !== null) {
                $doc = $this->metadata($folder);
                if (!isset($doc['stage_until']) || ($expiredBefore !== null && $doc['stage_until'] > $expiredBefore)) {
                    throw new \RuntimeException('Gallery is not an eligible unfinished stage');
                }
            }
            foreach (new \FilesystemIterator($folder) as $entry) {
                if ($entry->getFilename() === '.gallery.lock') { continue; }
                if ($entry->isLink() || !$entry->isFile()
                    || !preg_match('/^(?:[a-f0-9]{24}(?:-thumb)?\.webp|gallery\.json|\.metadata-[a-f0-9]{24})$/D', $entry->getFilename())) {
                    throw new \RuntimeException('Unexpected gallery file; manual review required');
                }
            }
            foreach (new \FilesystemIterator($folder) as $entry) {
                if ($entry->getFilename() !== '.gallery.lock' && !unlink($entry->getPathname())) {
                    throw new \RuntimeException('Cannot discard gallery file');
                }
            }
        });
        if (!unlink($folder . '/.gallery.lock') || !rmdir($folder)) {
            throw new \RuntimeException('Cannot discard gallery folder');
        }
    }

    public function read(int $article, string $gallery): array
    {
        return $this->metadata($this->folder($article, $gallery));
    }

    /** Only committed/legacy revisions can be rendered by a content plugin. */
    public function readCommitted(int $article, string $gallery): array
    {
        $doc = $this->read($article, $gallery);
        if (isset($doc['stage_until'])) {
            throw new \RuntimeException('Gallery has not been inserted');
        }
        return $doc;
    }

    private static function assertStage(array $doc): void
    {
        if (!isset($doc['stage_until']) || $doc['stage_until'] < time()) {
            throw new \RuntimeException('Gallery stage expired or already committed');
        }
    }

    /**
     * Read-only filesystem inventory. Never infer that a committed gallery is
     * orphaned: article histories, backups or external links may reference it.
     * The caller must use an offline maintenance window for any purge.
     */
    public function inventory(?int $now = null): iterable
    {
        $now ??= time();
        $root = $this->root . '/galleries';
        if (!is_dir($root) || is_link($root)) { return; }
        foreach (new \FilesystemIterator($root) as $article) {
            if ($article->isLink() || !$article->isDir()
                || !preg_match('/^article-([1-9][0-9]{0,9})$/D', $article->getFilename(), $a)) { continue; }
            $articleId = (int) $a[1];
            foreach (new \FilesystemIterator($article->getPathname()) as $gallery) {
                if ($gallery->isLink() || !$gallery->isDir()
                    || !preg_match('/^gallery-([a-f0-9]{24})$/D', $gallery->getFilename(), $g)) { continue; }
                $row = ['article' => $articleId, 'gallery' => $g[1], 'status' => 'review', 'photos' => null, 'bytes' => null];
                try {
                    $folder = $this->folder($articleId, $g[1]);
                    $doc = $this->metadata($folder);
                    $row['status'] = !isset($doc['stage_until']) ? 'retained'
                        : ($doc['stage_until'] <= $now - self::PURGE_GRACE ? 'expired-stage' : 'stage');
                    $row['photos'] = count($doc['images']);
                    $bytes = 0;
                    foreach (new \FilesystemIterator($folder) as $file) {
                        if ($file->isLink() || !$file->isFile()
                            || !preg_match('/^(?:[a-f0-9]{24}(?:-thumb)?\.webp|gallery\.json|\.gallery\.lock|\.metadata-[a-f0-9]{24})$/D', $file->getFilename())) {
                            throw new \RuntimeException('Unexpected gallery file');
                        }
                        $bytes += $file->getSize();
                    }
                    $row['bytes'] = $bytes;
                } catch (\Throwable $e) { $row['status'] = 'review'; }
                yield $row;
            }
        }
    }

    private static function plainText(mixed $text, int $max = 1000): string
    {
        if (!is_string($text) || strlen($text) > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', $text)
            || !preg_match('//u', $text)) {
            throw new \InvalidArgumentException('Invalid gallery text');
        }
        return trim($text);
    }

    public function add(int $article, string $gallery, string $data, string $extension, int $quality = 85, bool $requireStage = false): array
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
        return $this->locked($folder, function () use ($folder, $large, $thumb, $requireStage): array {
            $doc = $this->metadata($folder);
            if ($requireStage) { self::assertStage($doc); }
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
    public function update(int $article, string $gallery, array $images, string $credit, array $expectedIds, bool $commitStage = false): array
    {
        if (!array_is_list($images) || count($images) > self::MAX_IMAGES || !array_is_list($expectedIds)) {
            throw new \InvalidArgumentException('Invalid gallery update');
        }
        $folder = $this->folder($article, $gallery);
        return $this->locked($folder, function () use ($folder, $images, $credit, $expectedIds, $commitStage): array {
            $doc = $this->metadata($folder);
            if ($commitStage) { self::assertStage($doc); }
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
            if ($commitStage) {
                // Only remove photos omitted from this private editing copy.
                // The previous committed revision and its files are untouched.
                foreach (array_keys($allowed) as $removed) {
                    foreach (['.webp', '-thumb.webp'] as $suffix) {
                        $file = $folder . '/' . $removed . $suffix;
                        if (is_link($file)) { throw new \RuntimeException('Invalid staged photo'); }
                    }
                }
                unset($doc['stage_until']);
            }
            $this->writeMetadata($folder, $doc);
            if ($commitStage) {
                foreach (array_keys($allowed) as $removed) {
                    foreach (['.webp', '-thumb.webp'] as $suffix) {
                        $file = $folder . '/' . $removed . $suffix;
                        if (is_file($file) && !is_link($file)) { @unlink($file); }
                    }
                }
            }
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
