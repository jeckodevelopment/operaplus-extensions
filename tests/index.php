<?php
declare(strict_types=1);
require __DIR__ . '/../lib_operaplus/src/Index.php';
use OperaPlus\Atlas\Index;

function check(bool $condition, string $message): void {
    if (!$condition) { throw new RuntimeException($message); }
}
$entity = ['type'=>'opera','id'=>'oa-w-1','title'=>'Test','url'=>'https://opera.plus/atlas/opere/test/','image'=>null];
$raw = json_encode(['schema_version'=>1,'source_revision'=>str_repeat('a',40),'entities'=>[$entity]], JSON_THROW_ON_ERROR);
$index = Index::decode($raw);
check(isset($index['opera:oa-w-1']), 'Valid entity missing');
check(count(Index::resolve($index, ['opera:missing','opera:oa-w-1'])) === 1, 'Resolution failed');
check(Index::resolve($index, ['opera:missing']) === [], 'Missing reference must hide');
check(!Index::validReference('opera:../test'), 'Unsafe identifier accepted');
foreach (['https://evil.example/atlas/test','https://opera.plus@evil.example/atlas/test','javascript:alert(1)'] as $url) {
    $bad = $entity;
    $bad['url'] = $url;
    $decoded = Index::decode(json_encode(['schema_version'=>1,'source_revision'=>str_repeat('a',40),'entities'=>[$bad]], JSON_THROW_ON_ERROR));
    check($decoded['opera:oa-w-1']['url'] === null, 'Unsafe link accepted');
}
foreach ([['schema_version'=>2,'source_revision'=>str_repeat('a',40),'entities'=>[$entity]],['schema_version'=>1,'source_revision'=>str_repeat('a',40),'entities'=>[$entity,$entity]]] as $bad) {
    $failed = false;
    try { Index::decode(json_encode($bad, JSON_THROW_ON_ERROR)); } catch (UnexpectedValueException) { $failed = true; }
    check($failed, 'Invalid schema or duplicate accepted');
}
$dir = sys_get_temp_dir() . '/operaplus-test-' . bin2hex(random_bytes(8));
mkdir($dir);
$file = $dir . '/cache.json';
try {
    check(Index::load($file, fn () => $raw) === $index, 'Initial fetch failed');
    check(file_get_contents($file) === $raw, 'Cache not saved');
    touch($file, time() - 7200);
    clearstatcache();
    check(Index::load($file, fn () => '{bad') === $index, 'Last known good lost');
    check(file_get_contents($file) === $raw, 'Invalid data overwritten cache');
    check(Index::load($file, fn () => throw new RuntimeException('Must not retry')) === $index, 'Cooldown lost cached content');
    $calls = 0;
    Index::load($file, function () use (&$calls, $raw) { $calls++; return $raw; });
    check($calls === 0, 'Failed refresh retried before cooldown');
    $lock = fopen($file . '.lock', 'c+');
    flock($lock, LOCK_EX);
    Index::load($file, function () use (&$calls, $raw) { $calls++; return $raw; });
    check($calls === 0, 'Concurrent refresh should not fetch');
    flock($lock, LOCK_UN);
    fclose($lock);
} finally {
    if (is_file($file)) { unlink($file); }
    if (is_file($file . '.lock')) { unlink($file . '.lock'); }
    rmdir($dir);
}
$second = $entity; $second['id'] = 'oa-w-2';
$two = Index::decode(json_encode(['schema_version'=>1,'source_revision'=>str_repeat('a',40),'entities'=>[$entity,$second]], JSON_THROW_ON_ERROR));
$refs = ['opera:oa-w-1','opera:oa-w-2'];
check(Index::resolve($two, $refs, false, 'opera:oa-w-2')[0]['id'] === 'oa-w-2', 'Primary not selected');
check(Index::resolve($two, ['opera:oa-w-1'], false, 'opera:oa-w-2')[0]['id'] === 'oa-w-1', 'Unassociated primary used');
check(Index::references('["opera:oa-w-1","bad","opera:oa-w-1"]') === ['opera:oa-w-1'], 'Invalid refs retained');
$portrait = ['url'=>'https://thumb.wikimedia.org/wikipedia/commons/thumb/c/c6/Giuseppi_Verdi_by_Ferdinand_Mulnier_c1870.jpg/960px-Giuseppi_Verdi_by_Ferdinand_Mulnier_c1870.jpg','alt'=>'Ritratto di Giuseppe Verdi','source_url'=>'https://commons.wikimedia.org/wiki/File:Giuseppi_Verdi_by_Ferdinand_Mulnier_c1870.jpg','author'=>'Ferdinand Mulnier','license'=>'Public Domain','license_url'=>'https://creativecommons.org/publicdomain/mark/1.0/','credit'=>'Ferdinand Mulnier — Wikimedia Commons — Public Domain'];
foreach ([$portrait['url'], 'https://thumb.wikimedia.org.evil.example/image.jpg', 'http://thumb.wikimedia.org/image.jpg', 'https://thumb.wikimedia.org@evil.example/image.jpg'] as $url) {
    $candidate = $entity;
    $candidate['image'] = array_replace($portrait, ['url'=>$url]);
    $decoded = Index::decode(json_encode(['schema_version'=>1,'source_revision'=>str_repeat('a',40),'entities'=>[$candidate]], JSON_THROW_ON_ERROR));
    check(($decoded['opera:oa-w-1']['image'] !== null) === ($url === $portrait['url']), 'Thumbnail origin policy failed');
}
echo "Atlas index tests passed\n";
