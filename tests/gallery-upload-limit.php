<?php
declare(strict_types=1);
define('_JEXEC', 1);
require __DIR__ . '/../lib_operaplus/src/GalleryUploadLimit.php';
use OperaPlus\Gallery\UploadLimit;
$mb = 1024 * 1024;
foreach ([
    [10, '32M', '64M', true, 10*$mb],
    [0, '32M', '64M', true, 20*$mb],
    [10, '2M', '8M', true, 2*$mb],
    [10, '8M', '2M', true, 2*$mb-65536],
    [0, '32M', '0', true, 20*$mb],
    [10, '0', '8M', true, 0],
    [10, '8M', '8M', false, 0],
    [10, '8M', '32K', true, 0],
    [0.5, '8M', '8M', true, $mb/2],
] as [$media,$upload,$post,$enabled,$expected]) {
    $actual = UploadLimit::bytes($media,$upload,$post,$enabled);
    if ($actual !== (int) $expected) {
        throw new RuntimeException("Upload limit mismatch: $actual != $expected");
    }
}
echo "Gallery upload limit: media, PHP, multipart overhead and disabled upload passed.\n";
