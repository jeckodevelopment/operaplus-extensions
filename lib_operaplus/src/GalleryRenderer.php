<?php
declare(strict_types=1);

namespace OperaPlus\Gallery;

defined('_JEXEC') or die;

final class Renderer
{
    // Only standalone shortcode blocks; never substitute inside attributes,
    // links, scripts, or arbitrary inline text.
    public static function replace(string $html, callable $callback): string
    {
        $pattern = '~<p(?:\s[^<>]*)?>\s*\{operaplusgallery\s+id=(?:"|&quot;)([a-f0-9]{24})(?:"|&quot;)\s+columns=(?:"|&quot;)([234])(?:"|&quot;)\}\s*</p>~i';
        // Tokenise tags and comments so an apparent shortcode inside an
        // attribute, a script, or a comment is never interpreted.
        $parts = preg_split('~(<!--[\s\S]*?-->|<(?:script|style|textarea|pre|code)\b[^>]*>[\s\S]*?</(?:script|style|textarea|pre|code)\s*>|<(?:"[^"]*"|\'[^\']*\'|[^\'">])*>)~i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            return $html;
        }
        $out = '';
        $n = count($parts);
        for ($i = 0; $i < $n; $i++) {
            if (preg_match('~^<p(?:\s[^<>]*)?>$~iD', $parts[$i])
                && isset($parts[$i + 1], $parts[$i + 2])
                && preg_match('~^</p>$~iD', $parts[$i + 2])) {
                $block = $parts[$i] . $parts[$i + 1] . $parts[$i + 2];
                if (preg_match($pattern, $block, $m)) {
                    $out .= $callback(strtolower($m[1]), (int) $m[2]);
                    $i += 2;
                    continue;
                }
            }
            $out .= $parts[$i];
        }
        return $out;
    }

    private static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public static function gallery(int $article, string $gallery, int $columns, array $doc, string $root, array $labels): string
    {
        if (!in_array($columns, [2, 3, 4], true) || !$doc['images']) {
            return '';
        }
        $e = self::esc(...);
        $s = '<section class="op-photo-gallery" aria-label="' . $e($labels['gallery']) . '" data-op-gallery'
            . ' data-close="' . $e($labels['close']) . '" data-previous="' . $e($labels['previous'])
            . '" data-next="' . $e($labels['next']) . '" data-counter="' . $e($labels['counter']) . '">'
            . '<div class="op-photo-grid op-photo-cols-' . $columns . '">';
        $index = 0;
        foreach ($doc['images'] as $photo) {
            $index++;
            $full = $e($root . Store::relativePath($article, $gallery, $photo['id'], false));
            $thumb = $e($root . Store::relativePath($article, $gallery, $photo['id'], true));
            $credit = trim($photo['credit']) !== '' ? $photo['credit'] : $doc['credit'];
            $label = trim($photo['alt']) !== '' ? $photo['alt'] : $photo['caption'];
            $name = $labels['open'] . ' ' . $index . ($label !== '' ? ': ' . $label : '');
            $s .= '<figure class="op-photo"><a class="op-photo-link" href="' . $full . '" aria-label="' . $e($name)
                . '" data-caption="' . $e($photo['caption']) . '" data-credit="' . $e($credit === '' ? '' : $labels['credit'] . ': ' . $credit)
                . '"><img src="' . $thumb . '" alt="' . $e($photo['alt']) . '" loading="lazy" decoding="async" width="480" height="360"></a>';
            if ($photo['caption'] !== '' || $credit !== '') {
                $s .= '<figcaption>';
                if ($photo['caption'] !== '') {
                    $s .= '<span class="op-photo-caption">' . $e($photo['caption']) . '</span>';
                }
                if ($credit !== '') {
                    $s .= '<small class="op-photo-credit">' . $e($labels['credit'] . ': ' . $credit) . '</small>';
                }
                $s .= '</figcaption>';
            }
            $s .= '</figure>';
        }
        return $s . '</div></section>';
    }
}
