<?php
define('_JEXEC', 1);
require __DIR__ . '/../plg_mediaaction_operaplus_imageoptimizer/src/Optimizer.php';
use OperaPlus\Plugin\MediaAction\ImageOptimizer\Optimizer;
function check($ok, $msg) { if (!$ok) { throw new RuntimeException($msg); } }
check(function_exists('imagewebp') && function_exists('exif_read_data'), 'GD WebP and EXIF required');
$im = imagecreatetruecolor(2400, 1200);
imagealphablending($im, false); imagesavealpha($im, true);
imagefill($im, 0, 0, imagecolorallocatealpha($im, 0, 0, 0, 127));
imagefilledrectangle($im, 500, 300, 2000, 900, imagecolorallocate($im, 120, 40, 60));
ob_start(); imagepng($im, null, 0); $png = ob_get_clean();
$webp = Optimizer::convert($png, 'png');
check(is_string($webp), 'PNG converted');
$info = getimagesizefromstring($webp);
check($info[0] === 1920 && $info[1] === 960 && $info[2] === IMAGETYPE_WEBP, 'dimensions');
$out = imagecreatefromstring($webp);
check((imagecolorat($out, 10, 10) >> 24 & 0x7f) >= 120, 'alpha retained');
check(strlen($webp) < strlen($png), 'size reduction');
ob_start(); imagejpeg($im, null, 100); $jpg = ob_get_clean();
check(is_string(Optimizer::convert($jpg, 'jpg')), 'JPEG converted');
check(Optimizer::convert($png, 'jpg') === null, 'mismatched MIME');
check(Optimizer::convert('broken', 'png') === null, 'invalid bytes');
check(Optimizer::convert($png, 'gif') === null, 'GIF untouched');
check(Optimizer::convert($webp, 'webp') === null, 'WebP untouched');
check(Optimizer::excluded('/logos/sub', "images/logos\nimages/branding"), 'child exclusion');
check(!Optimizer::excluded('/logos-new', 'images/logos'), 'boundary exclusion');
echo "Image optimizer: conversion, resize, alpha, size, invalid input and exclusions passed.\n";
