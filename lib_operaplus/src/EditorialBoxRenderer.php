<?php
declare(strict_types=1);

namespace OperaPlus\EditorialBox;

defined('_JEXEC') or die;

final class Renderer
{
    public const TYPES = ['detail', 'opinion', 'note', 'context'];

    public static function validId(string $id): bool
    {
        return preg_match('/^[a-f0-9]{32}$/D', $id) === 1;
    }

    private static function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // Only standalone paragraphs are executable; never expand tokens in
    // attributes, comments, script/style, preformatted examples or links.
    public static function replace(string $html, callable $render): string
    {
        $parts = preg_split('~(<!--[\s\S]*?-->|<(?:script|style|textarea|pre|code|a)\b[^>]*>[\s\S]*?</(?:script|style|textarea|pre|code|a)\s*>|<(?:"[^"]*"|\'[^\']*\'|[^\'">])*>)~i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts)) {
            return $html;
        }
        $out = '';
        $seen = [];
        for ($i = 0, $n = count($parts); $i < $n; $i++) {
            if (preg_match('~^<p(?:\s[^<>]*)?>$~iD', $parts[$i])
                && isset($parts[$i + 1], $parts[$i + 2])
                && preg_match('~^</p>$~iD', $parts[$i + 2])
                && preg_match('~^\s*\{operabox:([a-f0-9]{32})\}\s*$~D', $parts[$i + 1], $match)) {
                $id = $match[1];
                if (!isset($seen[$id])) {
                    $out .= $render($id);
                    $seen[$id] = true;
                }
                $i += 2;
                continue;
            }
            $out .= $parts[$i];
        }
        return $out;
    }

    // Reconstruct only the approved editorial formatting. No author-defined
    // styles, events, media, embedded content or executable URL schemes.
    public static function sanitize(string $html): string
    {
        $doc = new \DOMDocument('1.0', 'UTF-8');
        if (!$doc->loadHTML('<!doctype html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>', LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
            return '';
        }
        $body = $doc->getElementsByTagName('body')->item(0);
        return $body ? self::children($body) : '';
    }

    private static function children(\DOMNode $parent): string
    {
        $out = '';
        foreach ($parent->childNodes as $node) {
            if ($node instanceof \DOMText) {
                $out .= self::esc($node->nodeValue ?? '');
                continue;
            }
            if (!$node instanceof \DOMElement) {
                continue;
            }
            $tag = strtolower($node->tagName);
            if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'form'], true)) {
                continue;
            }
            $inner = self::children($node);
            if ($tag === 'br') {
                $out .= '<br>';
            } elseif ($tag === 'a') {
                $href = trim($node->getAttribute('href'));
                // Reject control characters, backslashes and protocol-relative URLs.
                if ($href !== '' && !preg_match('/[\x00-\x20\x7f\\\\]/', $href)
                    && !str_starts_with($href, '//')
                    && (preg_match('~^(?:https?://|mailto:|/|#|\?|\.{1,2}/)~i', $href)
                        || !preg_match('~^[^/?#]*:~', $href))) {
                    $out .= '<a href="' . self::esc($href) . '">' . $inner . '</a>';
                } else {
                    $out .= $inner;
                }
            } elseif (in_array($tag, ['p','strong','b','em','i','u','s','ul','ol','li','blockquote','sup','sub','h3','h4','h5','h6'], true)) {
                $out .= '<' . $tag . '>' . $inner . '</' . $tag . '>';
            } else {
                $out .= $inner;
            }
        }
        return $out;
    }

    public static function box(array $row, array $labels): string
    {
        foreach (['id','type','title','body'] as $key) {
            if (!is_string($row[$key] ?? null)) {
                return '';
            }
        }
        if (!self::validId($row['id']) || !in_array($row['type'], self::TYPES, true)
            || trim($row['title']) === '' || strlen($row['body']) > 65536) {
            return '';
        }
        $body = self::sanitize($row['body']);
        if (trim(html_entity_decode(strip_tags($body), ENT_QUOTES | ENT_HTML5, 'UTF-8')) === '') {
            return '';
        }
        $title = self::esc(trim($row['title']));
        $signature = is_string($row['signature'] ?? null) ? trim($row['signature']) : '';
        return '<aside class="op-editorial-box" aria-label="' . $title . '">'
            . '<p class="op-editorial-box__type">' . self::esc($labels[$row['type']] ?? $row['type']) . '</p>'
            . '<h3 class="op-editorial-box__title">' . $title . '</h3>'
            . '<div class="op-editorial-box__body">' . $body . '</div>'
            . ($signature !== '' ? '<p class="op-editorial-box__signature">' . self::esc($signature) . '</p>' : '')
            . '</aside>';
    }

    // Duplicated identifiers are ambiguous; fail closed for both records.
    public static function index(array $rows): array
    {
        $result = [];
        $duplicates = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !is_string($row['id'] ?? null) || !self::validId($row['id'])) {
                continue;
            }
            $id = $row['id'];
            if (isset($result[$id])) {
                $duplicates[$id] = true;
            }
            $result[$id] = $row;
        }
        return array_diff_key($result, $duplicates);
    }
}
