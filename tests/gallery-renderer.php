<?php
declare(strict_types=1);
define('_JEXEC', 1);
require __DIR__ . '/../lib_operaplus/src/GalleryStore.php';
require __DIR__ . '/../lib_operaplus/src/GalleryRenderer.php';
use OperaPlus\Gallery\Renderer;
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$id = str_repeat('a', 24);
$photo = str_repeat('b', 24);
$token = '{operaplusgallery id="' . $id . '" columns="4"}';
$block = '<p>' . $token . '</p>';
$labels = ['gallery'=>'Gallery','open'=>'Open','close'=>'Close','previous'=>'Previous','next'=>'Next','credit'=>'Credit','counter'=>'%current%/%total%'];
$doc = ['images'=>[['id'=>$photo,'caption'=>'<script>alert(1)</script>','alt'=>'A "photo"','credit'=>'']], 'credit'=>'Author & Co'];
$html = Renderer::replace('<p>Before</p>' . $block . '<p>After</p>', fn ($id,$cols) => Renderer::gallery(42,$id,$cols,$doc,'/',$labels));
check(str_contains($html,'op-photo-cols-4'), 'Column class');
check(str_contains($html,'article-42/gallery-'.$id.'/'.$photo.'-thumb.webp'), 'Article-bound thumbnail');
check(str_contains($html,'loading="lazy"') && str_contains($html,'href="/images/'), 'Lazy thumbnail and fallback link');
check(!str_contains($html,'<script>') && str_contains($html,'&lt;script&gt;'), 'Escaping');
check(str_contains($html,'Author &amp; Co'), 'Credit fallback');
check(str_contains($html,'<p>Before</p>') && str_contains($html,'<p>After</p>'), 'Adjacent content preserved');
check(Renderer::replace($block,fn()=> '') === '', 'Hidden preview');
$comment='<!-- '.$block.' -->';
$attr='<div title="'.htmlspecialchars($block,ENT_QUOTES).'">test</div>';
check(Renderer::replace($comment.$attr.'<pre>'.$block.'</pre>',fn()=> 'BAD') === $comment.$attr.'<pre>'.$block.'</pre>', 'Non-content contexts untouched');
echo "Gallery renderer: article binding, escaping, columns, thumbnail, fallback and safe shortcode contexts passed.\n";
