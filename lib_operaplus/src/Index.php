<?php
declare(strict_types=1);

namespace OperaPlus\Atlas;

final class Index
{
    public const ENDPOINT = 'https://raw.githubusercontent.com/jeckodevelopment/opera-atlas/main/public/integrations/v1/entities.json';
    public const MAX_BYTES = 4194304;
    private const RETRY_SECONDS = 300;

    public static function validReference(string $ref): bool
    {
        return preg_match('/^(opera|composer|theatre|festival|production):[a-zA-Z0-9_-]{1,128}$/D', $ref) === 1;
    }

    private static function https(mixed $url, array $hosts): bool
    {
        if (!is_string($url) || preg_match('/[\x00-\x20\\\\]/', $url)) {
            return false;
        }
        $p = parse_url($url);
        return is_array($p) && ($p['scheme'] ?? '') === 'https'
            && in_array($p['host'] ?? '', $hosts, true)
            && !isset($p['user']) && !isset($p['pass'])
            && (!isset($p['port']) || $p['port'] === 443);
    }

    private static function image(mixed $image): ?array
    {
        if (!is_array($image)) {
            return null;
        }
        foreach (['url','alt','source_url','author','license','license_url','credit'] as $key) {
            if (!is_string($image[$key] ?? null) || trim($image[$key]) === '') {
                return null;
            }
        }
        if (!in_array($image['license'], ['Public Domain','CC0-1.0','CC-BY-4.0','CC-BY-SA-4.0','CC-BY-3.0','CC-BY-SA-3.0'], true)
            || !self::https($image['url'], ['upload.wikimedia.org','commons.wikimedia.org','opera.plus'])
            || !self::https($image['source_url'], ['commons.wikimedia.org','opera.plus'])
            || !self::https($image['license_url'], ['creativecommons.org','rightsstatements.org','commons.wikimedia.org'])) {
            return null;
        }
        return $image;
    }

    public static function decode(string $raw): array
    {
        if (strlen($raw) > self::MAX_BYTES) {
            throw new \UnexpectedValueException('Index too large');
        }
        $doc = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($doc) || ($doc['schema_version'] ?? null) !== 1
            || !is_array($doc['entities'] ?? null) || !array_is_list($doc['entities']) || !$doc['entities']
            || !is_string($doc['source_revision'] ?? null)
            || !preg_match('/^[a-f0-9]{40}$/D', $doc['source_revision'])) {
            throw new \UnexpectedValueException('Invalid index');
        }
        $result = [];
        foreach ($doc['entities'] as $entity) {
            if (!is_array($entity) || !is_string($entity['type'] ?? null) || !is_string($entity['id'] ?? null)
                || !is_string($entity['title'] ?? null) || trim($entity['title']) === '') {
                throw new \UnexpectedValueException('Invalid entity');
            }
            $key = $entity['type'] . ':' . $entity['id'];
            if (!self::validReference($key) || isset($result[$key])) {
                throw new \UnexpectedValueException('Invalid or duplicate reference');
            }
            $url = $entity['url'] ?? null;
            $entity['url'] = self::https($url, ['opera.plus']) && str_starts_with($url, 'https://opera.plus/atlas/') ? $url : null;
            $entity['subtitle'] = is_string($entity['subtitle'] ?? null) ? $entity['subtitle'] : null;
            $entity['image'] = self::image($entity['image'] ?? null);
            $entity['fallback_image'] = self::image($entity['fallback_image'] ?? null);
            $result[$key] = $entity;
        }
        return $result;
    }

    public static function fetch(): string
    {
        $context = stream_context_create(['http' => [
            'method' => 'GET', 'timeout' => 5, 'follow_location' => 0,
            'max_redirects' => 0, 'header' => "Accept: application/json\r\nUser-Agent: OperaPlus-Joomla/1.0\r\n",
        ], 'ssl' => ['verify_peer' => true, 'verify_peer_name' => true]]);
        $raw = @file_get_contents(self::ENDPOINT, false, $context, 0, self::MAX_BYTES + 1);
        if (!is_string($raw) || !preg_match('/^HTTP\/\S+ 200\b/', $http_response_header[0] ?? '')) {
            throw new \RuntimeException('Atlas unavailable');
        }
        return $raw;
    }

    private static function cached(string $file): array
    {
        clearstatcache(true, $file);
        if (!is_file($file) || is_link($file)) { return []; }
        try {
            $raw = @file_get_contents($file, false, null, 0, self::MAX_BYTES + 1);
            return is_string($raw) ? self::decode($raw) : [];
        } catch (\Throwable) {
            return [];
        }
    }

    public static function load(string $cacheFile, ?callable $fetch = null, int $ttl = 3600): array
    {
        $previous = self::cached($cacheFile);
        if ($previous && filemtime($cacheFile) >= time() - max(0, $ttl)) {
            return $previous;
        }
        // Never hold up a page waiting for another request to refresh.
        $dir = dirname($cacheFile);
        $lock = is_dir($dir) && is_writable($dir) ? @fopen($cacheFile . '.lock', 'c+') : false;
        if (!$lock) {
            // Degrade without filesystem caching when the server cache is unavailable.
            try { return self::decode(($fetch ?? self::fetch(...))()); }
            catch (\Throwable) { return $previous; }
        }
        if (!flock($lock, LOCK_EX | LOCK_NB)) {
            fclose($lock);
            return $previous;
        }
        try {
            $latest = self::cached($cacheFile);
            if ($latest) { $previous = $latest; }
            if ($latest && filemtime($cacheFile) >= time() - max(0, $ttl)) {
                return $latest;
            }
            rewind($lock);
            $lastAttempt = (int) stream_get_contents($lock);
            if ($lastAttempt > time() - self::RETRY_SECONDS) {
                return $previous;
            }
            // A failed network call must not cause a retry on every page view.
            rewind($lock);
            ftruncate($lock, 0);
            fwrite($lock, (string) time());
            fflush($lock);
            try {
                $raw = ($fetch ?? self::fetch(...))();
                $index = self::decode($raw);
                $temporary = tempnam($dir, 'atlas-');
                if ($temporary !== false) {
                    try {
                        if (file_put_contents($temporary, $raw, LOCK_EX) === strlen($raw)) {
                            @rename($temporary, $cacheFile);
                        }
                    } finally {
                        if (is_file($temporary)) { @unlink($temporary); }
                    }
                }
                // Successful refresh does not need a failure cooldown.
                rewind($lock);
                ftruncate($lock, 0);
                fflush($lock);
                return $index;
            } catch (\Throwable) {
                return $previous;
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function references(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [$value];
        }
        if (!is_array($value)) { return []; }
        return array_values(array_unique(array_filter($value, static fn ($v) => is_string($v) && self::validReference($v))));
    }

    public static function resolve(array $index, array $refs, bool $all = false, ?string $primary = null): array
    {
        $refs = self::references($refs);
        if ($primary !== null && in_array($primary, $refs, true)) {
            $refs = array_merge([$primary], array_values(array_diff($refs, [$primary])));
        }
        $result = [];
        foreach ($refs as $ref) {
            if (is_string($ref) && self::validReference($ref) && isset($index[$ref])) {
                $result[$ref] = $index[$ref];
                if (!$all) { break; }
            }
        }
        return array_values($result);
    }
}
