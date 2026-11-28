<?php
declare(strict_types=1);
namespace OperaPlus\Plugin\System\Sitemap;

final class Sitemap
{
    public static function ids(string $value): array
    {
        return array_values(array_filter(array_map('intval', preg_split('/[\s,]+/', $value))));
    }

    public static function noindex(?string $json): bool
    {
        $data = json_decode($json ?? '', true);
        return self::robotsNoindex((string) ($data['robots'] ?? ''));
    }

    public static function robotsNoindex(string $robots): bool
    {
        return (bool) preg_match('/(?:^|[\s,])(?:noindex|none)(?:$|[\s,])/i', $robots);
    }

    public static function publicRow(object $row, array $levels, string $now): bool
    {
        return in_array((int) $row->access, $levels, true)
            && (int) ($row->published ?? $row->state ?? 0) === 1
            && !self::noindex($row->metadata ?? '')
            && !self::noindex($row->params ?? '')
            && !self::windowBlocked($row->publish_up ?? null, $now, true)
            && !self::windowBlocked($row->publish_down ?? null, $now, false);
    }

    private static function windowBlocked(?string $date, string $now, bool $start): bool
    {
        if (!$date || str_starts_with($date, '0000-00-00')) {
            return false;
        }
        return $start ? $date > $now : $date <= $now;
    }

    public static function allowedTree(int $id, array $rows, array $levels, array $exclude, string $now): bool
    {
        $seen = [];
        while ($id > 1) {
            if (isset($seen[$id]) || in_array($id, $exclude, true) || !isset($rows[$id])
                || !self::publicRow($rows[$id], $levels, $now)) {
                return false;
            }
            $seen[$id] = true;
            $id = (int) $rows[$id]->parent_id;
        }
        return $id === 1;
    }

    public static function origin(string $origin): string
    {
        $origin = rtrim(trim($origin), '/');
        if (!preg_match('~^https://[a-z0-9.-]+(?::[0-9]+)?$~iD', $origin)) {
            throw new \InvalidArgumentException('Invalid HTTPS canonical origin.');
        }
        return $origin;
    }

    public static function externalPaths(string $input): array
    {
        $paths = [];
        foreach (preg_split('/\R/', $input) as $path) {
            $path = trim($path);
            if ($path === '') {
                continue;
            }
            if (!preg_match('~^/(?:[a-z0-9_-]+/)*[a-z0-9_.-]+\.xml$~iD', $path)
                || in_array($path, ['/sitemap.xml', '/sitemap-content.xml'], true)) {
                throw new \InvalidArgumentException('Invalid external sitemap path.');
            }
            $paths[] = $path;
        }
        return array_values(array_unique($paths));
    }

    public static function lastmod(?string $date): ?string
    {
        if (!$date || str_starts_with($date, '0000-00-00')) {
            return null;
        }
        $time = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $date, new \DateTimeZone('UTC'));
        return $time && $time->format('Y-m-d H:i:s') === $date ? $time->format('Y-m-d\TH:i:s\Z') : null;
    }

    public static function xml(array $entries, bool $index = false): string
    {
        if (count($entries) > 50000) {
            throw new \LengthException('Sitemap requires pagination.');
        }
        $writer = new \XMLWriter();
        $writer->openMemory();
        $writer->startDocument('1.0', 'UTF-8');
        $writer->startElement($index ? 'sitemapindex' : 'urlset');
        $writer->writeAttribute('xmlns', 'http://www.sitemaps.org/schemas/sitemap/0.9');
        ksort($entries, SORT_STRING);
        foreach ($entries as $url => $lastmod) {
            $writer->startElement($index ? 'sitemap' : 'url');
            $writer->writeElement('loc', $url);
            if ($lastmod) {
                $writer->writeElement('lastmod', $lastmod);
            }
            $writer->endElement();
        }
        $writer->endElement();
        $writer->endDocument();
        $xml = $writer->outputMemory();
        if (strlen($xml) > 52428800) {
            throw new \LengthException('Sitemap exceeds XML size limit.');
        }
        return $xml;
    }
}
