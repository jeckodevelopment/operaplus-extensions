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
} finally {
    if (is_file($file)) { unlink($file); }
    rmdir($dir);
}
echo "Atlas index tests passed\n";
