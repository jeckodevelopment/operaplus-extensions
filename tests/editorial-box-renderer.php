<?php
declare(strict_types=1);
define('_JEXEC', 1);
require __DIR__ . '/../lib_operaplus/src/EditorialBoxRenderer.php';
use OperaPlus\EditorialBox\Renderer;
function check(bool $condition, string $name): void
{
    if (!$condition) {
        throw new RuntimeException($name);
    }
}
$id = str_repeat('a', 32);
$token = '<p>{operabox:' . $id . '}</p>';
$row = ['id'=>$id,'type'=>'detail','title'=>'<Title>','body'=>'<p><strong>Text</strong> <a href="https://example.com" onclick="bad()">link</a><a href="javascript:alert(1)">bad</a><script>alert(1)</script></p>','signature'=>'A & B'];
$html = Renderer::box($row, ['detail'=>'Context']);
check(str_contains($html, '<aside'), 'semantic aside');
check(str_contains($html, '&lt;Title&gt;'), 'title escape');
check(str_contains($html, '<strong>Text</strong>'), 'formatting');
check(str_contains($html, 'href="https://example.com"'), 'safe link');
check(!str_contains($html, 'onclick') && !str_contains($html, 'javascript:') && !str_contains($html, '<script'), 'unsafe input');
check(str_contains($html, 'A &amp; B'), 'signature escape');
check(Renderer::replace($token . $token, fn($id) => 'BOX') === 'BOX', 'duplicate placement');
check(Renderer::replace('<pre>' . $token . '</pre>', fn($id) => 'BOX') === '<pre>' . $token . '</pre>', 'literal example');
check(Renderer::replace('<!--' . $token . '-->', fn($id) => 'BOX') === '<!--' . $token . '-->', 'comment');
check(Renderer::replace($token, fn($id) => '') === '', 'orphan removal');
check(Renderer::index([$row,$row]) === [], 'duplicate identifiers');
check(isset(Renderer::index([$row])[$id]), 'stable index');
check(Renderer::box(array_replace($row,['type'=>'unknown']), []) === '', 'unknown type');
check(Renderer::box(array_replace($row,['body'=>'<p> </p>']), []) === '', 'empty body');
echo "Editorial box renderer OK\n";
