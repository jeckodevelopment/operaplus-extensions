<?php
declare(strict_types=1);
require __DIR__ . '/../mod_operaplus_cta/src/Config.php';
use OperaPlus\Cta\Config;
function check(bool $ok, string $message): void { if (!$ok) { throw new RuntimeException($message); } }
foreach (['javascript:alert(1)', 'data:text/html,abc', '//evil.example', 'https://user@evil.example', "https://site.example/\nabc", 'http://site.example'] as $url) {
    check(Config::safeUrl($url) === '', 'Unsafe URL accepted: ' . $url);
}
check(Config::safeUrl('/newsletter') === '/newsletter', 'Internal link rejected');
check(Config::safeUrl('https://opera.plus/atlas/') !== '', 'HTTPS link rejected');
check(Config::safeUrl('images/portrait.jpg', true) !== '', 'Local image rejected');
check(Config::safeUrl('images/../test.jpg', true) === '', 'Traversal accepted');
check(Config::safeUrl('administrator/index.php', true) === '', 'Unexpected local image accepted');
foreach (['light','dark','horizontal','atlas'] as $variant) {
    check(Config::from(['variant'=>$variant])['variant'] === $variant, 'Variant rejected');
}
$config = Config::from(['variant'=>'<script>', 'icon'=>'<script>', 'button_url'=>'javascript:alert(1)']);
check($config['variant'] === 'light' && $config['icon'] === 'none' && $config['button_url'] === '', 'Unsafe configuration not normalized');
echo "CTA tests passed\n";
