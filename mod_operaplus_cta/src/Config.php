<?php
declare(strict_types=1);
namespace OperaPlus\Cta;

final class Config
{
    public static function safeUrl(string $url, bool $image = false): string
    {
        $url = trim($url);
        if ($url === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $url) || str_starts_with($url, '//')) {
            return '';
        }
        $parts = parse_url($url);
        if (!is_array($parts) || isset($parts['user']) || isset($parts['pass'])) {
            return '';
        }
        if (isset($parts['scheme'])) {
            return strtolower($parts['scheme']) === 'https' && !empty($parts['host']) ? $url : '';
        }
        if (isset($parts['host']) || ($image && preg_match('~(?:^|/)(?:\\.\\.?|%2e)(?:/|$)~i', $url)) || ($image && !preg_match('~^/?images/[^?#]+$~iD', $url))) {
            return '';
        }
        return $url;
    }

    public static function from(array $params): array
    {
        $variant = (string) ($params['variant'] ?? 'light');
        $icon = (string) ($params['icon'] ?? 'envelope');
        return [
            'variant' => in_array($variant, ['light','dark','horizontal','atlas'], true) ? $variant : 'light',
            'icon' => in_array($icon, ['none','envelope','book'], true) ? $icon : 'none',
            'watermark' => !empty($params['watermark']),
            'heading' => trim((string) ($params['heading'] ?? '')),
            'description' => trim((string) ($params['description'] ?? '')),
            'image' => self::safeUrl((string) ($params['image'] ?? ''), true),
            'image_alt' => (string) ($params['image_alt'] ?? ''),
            'button_text' => trim((string) ($params['button_text'] ?? '')),
            'button_url' => self::safeUrl((string) ($params['button_url'] ?? '')),
        ];
    }
}
