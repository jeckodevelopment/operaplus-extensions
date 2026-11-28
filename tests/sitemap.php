<?php
require __DIR__ . '/../plg_system_operaplus_sitemap/src/Sitemap.php';
use OperaPlus\Plugin\System\Sitemap\Sitemap;
function check(bool $ok): void { if (!$ok) { throw new RuntimeException('Sitemap assertion failed'); } }
$now = '2026-11-28 12:00:00';
$public = (object) ['access'=>1,'published'=>1,'parent_id'=>1];
$restricted = (object) ['access'=>2,'published'=>1,'parent_id'=>1];
$child = (object) ['access'=>1,'published'=>1,'parent_id'=>3];
$rows = [2=>$public,3=>$restricted,4=>$child];
check(Sitemap::allowedTree(2,$rows,[1],[],$now));
check(!Sitemap::allowedTree(4,$rows,[1],[],$now));
check(!Sitemap::allowedTree(2,$rows,[1],[2],$now));
check(!Sitemap::allowedTree(9,$rows,[1],[],$now));
check(!Sitemap::publicRow((object)['access'=>1,'state'=>1,'metadata'=>'{"robots":"noindex, follow"}'],[1],$now));
check(!Sitemap::publicRow((object)['access'=>1,'state'=>1,'publish_up'=>'2026-12-01 00:00:00'],[1],$now));
check(!Sitemap::publicRow((object)['access'=>1,'state'=>1,'publish_down'=>$now],[1],$now));
check(!Sitemap::publicRow((object)['access'=>1,'state'=>2],[1],$now));
check(Sitemap::lastmod('2026-11-28 10:00:00')==='2026-11-28T10:00:00Z');
check(Sitemap::lastmod('0000-00-00 00:00:00')===null);
check(Sitemap::externalPaths("/atlas/sitemap.xml\n/atlas/sitemap.xml")===['/atlas/sitemap.xml']);
foreach (['//example.org/x.xml','/sitemap.xml','/../x.xml'] as $bad) {
    try { Sitemap::externalPaths($bad); throw new RuntimeException('Accepted unsafe path'); } catch (InvalidArgumentException $e) {}
}
$xml = Sitemap::xml(['https://opera.plus/a&b'=>null]);
$parsed = simplexml_load_string($xml);
check((string) $parsed->url->loc==='https://opera.plus/a&b');
check(!isset($parsed->url->priority));
check(str_contains(Sitemap::xml(['https://opera.plus/atlas/sitemap.xml'=>null],true),'sitemapindex'));
echo "Sitemap policy and XML tests passed.\n";
