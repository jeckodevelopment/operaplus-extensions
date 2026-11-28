<?php
declare(strict_types=1);

define('_JEXEC', 1);
require __DIR__ . '/../plg_mediaaction_operaplus_imageoptimizer/src/Optimizer.php';
require __DIR__ . '/../lib_operaplus/src/GalleryStore.php';

use OperaPlus\Plugin\MediaAction\ImageOptimizer\Optimizer;
use OperaPlus\Gallery\Store;

function check(bool $ok, string $message): void {
    if (!$ok) { throw new RuntimeException($message); }
}
function rejects(callable $call): void {
    try { $call(); } catch (Throwable $e) { return; }
    throw new RuntimeException('Expected rejection');
}
function cleanup(string $path): void {
    if (is_link($path) || !is_dir($path)) { unlink($path); return; }
    foreach (new FilesystemIterator($path) as $item) { cleanup($item->getPathname()); }
    rmdir($path);
}

$root = sys_get_temp_dir() . '/op-gallery-' . bin2hex(random_bytes(12));
mkdir($root);
try {
    $im = imagecreatetruecolor(2400, 1200);
    imagefilledrectangle($im, 100, 100, 2000, 1000, imagecolorallocate($im, 120, 40, 60));
    ob_start(); imagepng($im); $png = ob_get_clean();
    $store = new Store($root, Optimizer::convertGallery(...));
    rejects(fn () => $store->create(0));
    $gallery = $store->create(42);
    check(Store::validId($gallery), 'Generated gallery ID');
    check($store->read(42, $gallery)['images'] === [], 'Empty gallery');
    rejects(fn () => $store->read(43, $gallery));
    rejects(fn () => $store->read(42, '../x'));
    rejects(fn () => $store->add(42, $gallery, 'invalid', 'png'));
    check($store->read(42, $gallery)['images'] === [], 'No failed upload registered');
    $first = $store->add(42, $gallery, $png, 'png');
    $full = $root . '/' . substr(Store::relativePath(42, $gallery, $first['id'], false), 7);
    $thumb = $root . '/' . substr(Store::relativePath(42, $gallery, $first['id'], true), 7);
    $bigInfo = getimagesize($full); $thumbInfo = getimagesize($thumb);
    check($bigInfo[0] === 1920 && $bigInfo[1] === 960 && $bigInfo[2] === IMAGETYPE_WEBP, 'Full WebP');
    check($thumbInfo[0] === 480 && $thumbInfo[1] === 240 && $thumbInfo[2] === IMAGETYPE_WEBP, 'Thumbnail WebP');
    $webp = file_get_contents($full);
    check(Optimizer::convert($webp, 'webp') === null, 'Native Media Manager behavior unchanged');
    $second = $store->add(42, $gallery, $webp, 'webp');
    $ids = [$first['id'], $second['id']];
    $second['caption'] = 'Scena d’opera';
    $second['alt'] = 'Cantanti sul palco';
    $doc = $store->update(42, $gallery, [$second, $first], 'Fotografo', $ids);
    check($doc['images'][0]['id'] === $second['id'] && $doc['credit'] === 'Fotografo', 'Ordering and credits');
    rejects(fn () => $store->update(42, $gallery, [$first], '', $ids));
    rejects(fn () => $store->update(42, $gallery, [$first, $first], '', array_reverse($ids)));
    $doc = $store->update(42, $gallery, [$second], 'Fotografo', array_reverse($ids));
    check(count($doc['images']) === 1 && is_file($full), 'Removal preserves previous image file');
    check($doc === $store->read(42, $gallery), 'Metadata persisted');
    $fork = $store->fork(42, $gallery);
    $copy = $store->read(42, $fork);
    check($copy === $doc, 'Fork preserves metadata');
    $edit = $copy['images'][0]; $edit['caption'] = 'New caption';
    $store->update(42, $fork, [$edit], '', [$edit['id']]);
    check($store->read(42, $gallery) === $doc, 'Original revision unchanged');
    $store->discard(42, $fork);
    rejects(fn () => $store->read(42, $fork));
    check($store->read(42, $gallery) === $doc, 'Discard does not affect original');
    $now = time();
    $stage = $store->fork(42, $gallery, $now + Store::STAGE_TTL);
    rejects(fn () => $store->readCommitted(42, $stage));
    rejects(fn () => $store->fork(42, $stage, $now + Store::STAGE_TTL));
    $newPhoto = $store->add(42, $stage, $png, 'png', 85, true);
    $stagedPath = $root . '/' . substr(Store::relativePath(42, $stage, $newPhoto['id'], false), 7);
    check(is_file($stagedPath), 'Staged photo present');
    $store->update(42, $stage, $doc['images'], 'New credit', [$edit['id'], $newPhoto['id']], true);
    check($store->readCommitted(42, $stage)['credit'] === 'New credit', 'Committed stage rendered');
    check(!is_file($stagedPath), 'Unused staged photo deleted');
    rejects(fn () => $store->discard(42, $stage, true));
    rejects(fn () => $store->add(42, $stage, $png, 'png', 85, true));
    check($store->readCommitted(42, $gallery) === $doc, 'Committed original preserved');
    $expired = $store->create(42, $now - Store::PURGE_GRACE - 10);
    $recent = $store->create(42, $now - 10);
    $active = $store->create(42, $now + 3600);
    rejects(fn () => $store->update(42, $expired, [], '', [], true));
    $statuses = array_column(iterator_to_array($store->inventory($now)), 'status', 'gallery');
    check($statuses[$expired] === 'expired-stage' && $statuses[$recent] === 'stage'
        && $statuses[$active] === 'stage' && $statuses[$stage] === 'retained', 'Inventory classification');
    rejects(fn () => $store->discard(42, $recent, true, $now - Store::PURGE_GRACE));
    rejects(fn () => $store->discard(42, $stage, true, $now - Store::PURGE_GRACE));
    $store->discard(42, $expired, true, $now - Store::PURGE_GRACE);
    rejects(fn () => $store->read(42, $expired));
    check($store->read(42, $active)['stage_until'] > $now, 'Active stage preserved');
    symlink($root . '/galleries/article-42', $root . '/galleries/article-99');
    rejects(fn () => $store->read(99, $gallery));
    echo "Gallery foundation: WebP variants, storage, ordering, text, removal, validation and isolation passed.\n";
} finally {
    cleanup($root);
}
