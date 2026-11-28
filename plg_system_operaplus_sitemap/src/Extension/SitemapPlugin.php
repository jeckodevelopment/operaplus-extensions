<?php
declare(strict_types=1);
namespace OperaPlus\Plugin\System\Sitemap\Extension;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Event\SubscriberInterface;
use OperaPlus\Plugin\System\Sitemap\Sitemap;

final class SitemapPlugin extends CMSPlugin implements SubscriberInterface
{
    use DatabaseAwareTrait;

    public static function getSubscribedEvents(): array
    {
        return ['onAfterInitialise' => 'serve'];
    }

    public function serve(): void
    {
        $app = $this->getApplication();
        if (!$app->isClient('site')) {
            return;
        }
        $path = Uri::getInstance()->getPath();
        $root = rtrim(Uri::root(true), '/');
        if (!in_array($path, [$root . '/sitemap.xml', $root . '/sitemap-content.xml'], true)) {
            return;
        }
        $method = strtoupper($app->input->server->getString('REQUEST_METHOD', 'GET'));
        $status = 200;
        $body = '';
        try {
            if (!in_array($method, ['GET', 'HEAD'], true)) {
                $status = 405;
                $app->setHeader('Allow', 'GET, HEAD', true);
            } elseif ($app->get('offline') || $root !== '' || !$app->get('sef') || !$app->get('sef_rewrite')) {
                $status = 503;
            } elseif (!$app->getIdentity() || !$app->getIdentity()->guest) {
                $status = 403;
            } else {
                $origin = Sitemap::origin((string) $this->params->get('origin', 'https://opera.plus'));
                if ($path === '/sitemap.xml') {
                    $entries = [$origin . '/sitemap-content.xml' => null];
                    foreach (Sitemap::externalPaths((string) $this->params->get('external', '/atlas/sitemap.xml')) as $external) {
                        $entries[$origin . $external] = null;
                    }
                    $body = Sitemap::xml($entries, true);
                } else {
                    $body = Sitemap::xml($this->entries($origin));
                }
            }
        } catch (\Throwable $e) {
            Log::add('Opera+ Sitemap generation failed: ' . $e->getMessage(), Log::ERROR, 'jerror');
            $status = 503;
            $body = '';
        }
        $app->setHeader('Cache-Control', 'no-cache, must-revalidate', true);
        $app->setHeader('Content-Type', 'application/xml; charset=utf-8', true);
        $app->setHeader('X-Content-Type-Options', 'nosniff', true);
        if ($status === 200) {
            $etag = '"' . hash('sha256', $body) . '"';
            $app->setHeader('ETag', $etag, true);
            if (in_array($etag, array_map('trim', explode(',', $app->input->server->getString('HTTP_IF_NONE_MATCH', ''))), true)) {
                $status = 304;
            }
        } else {
            $app->setHeader('Cache-Control', 'no-store', true);
        }
        http_response_code($status);
        $app->sendHeaders();
        if ($method !== 'HEAD' && $status === 200) {
            echo $body;
        }
        $app->close();
    }

    private function entries(string $origin): array
    {
        $app = $this->getApplication();
        if (Sitemap::robotsNoindex((string) $app->get('robots', ''))) {
            return [];
        }
        $levels = array_map('intval', Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById(0)->getAuthorisedViewLevels());
        $now = gmdate('Y-m-d H:i:s');
        $db = $this->getDatabase();
        $load = static function (string $table, string $columns, string $condition = '') use ($db): array {
            $q = $db->getQuery(true)->select($columns)->from($db->quoteName($table));
            if ($condition !== '') {
                $q->where($condition);
            }
            return $db->setQuery($q)->loadObjectList('id');
        };
        $menus = $load('#__menu', 'id, parent_id, type, link, access, published, params, home', 'client_id = 0');
        $categories = $load('#__categories', 'id, parent_id, access, published, params, metadata, modified_time', "extension = 'com_content'");
        $articles = $load('#__content', 'id, alias, catid, access, state, metadata, publish_up, publish_down, modified, created', 'state = 1');
        $excludedMenus = Sitemap::ids((string) $this->params->get('exclude_menu', ''));
        $excludedCats = Sitemap::ids((string) $this->params->get('exclude_category', ''));
        $extra = Sitemap::ids((string) $this->params->get('extra_menu', ''));
        $categoryAllowed = static fn(int $id): bool => $id > 1 && Sitemap::allowedTree($id, $categories, $levels, $excludedCats, $now);
        $articleAllowed = static fn(object $article): bool => Sitemap::publicRow($article, $levels, $now) && $categoryAllowed((int) $article->catid);
        $entries = [];
        $direct = [];
        $blocked = [];
        $categoryMenus = [];
        ksort($menus, SORT_NUMERIC);
        foreach ($menus as $id => $menu) {
            if ((int) $id <= 1 || $menu->type !== 'component') {
                continue;
            }
            parse_str((string) parse_url($menu->link, PHP_URL_QUERY), $query);
            $option = $query['option'] ?? '';
            $view = $query['view'] ?? '';
            $target = (int) ($query['id'] ?? 0);
            $allowed = Sitemap::allowedTree((int) $id, $menus, $levels, $excludedMenus, $now);
            if ($option === 'com_content' && $view === 'article') {
                if (!$allowed || !isset($articles[$target]) || !$articleAllowed($articles[$target])) {
                    $blocked[$target] = true;
                    continue;
                }
                $direct[$target] ??= (int) $id;
                continue;
            }
            if (!$allowed) {
                continue;
            }
            if ($option === 'com_content' && in_array($view, ['category', 'categories'], true)) {
                if ($target > 1 && !$categoryAllowed($target)) {
                    continue;
                }
                if ($view === 'category') {
                    $categoryMenus[$target] ??= (int) $id;
                }
            } elseif (!($option === 'com_content' && $view === 'featured') && !in_array((int) $id, $extra, true) && !(int) $menu->home) {
                continue;
            }
            $url = $this->route('index.php?Itemid=' . (int) $id, $origin);
            if ($url !== null) {
                $entries[$url] = null;
            }
        }
        foreach ($categoryMenus as $id => $menuId) {
            if (!$categoryAllowed((int) $id)) {
                continue;
            }
            $url = $this->route('index.php?Itemid=' . $menuId, $origin);
            if ($url !== null) {
                $entries[$url] = Sitemap::lastmod($categories[$id]->modified_time ?? null);
            }
        }
        foreach ($articles as $id => $article) {
            if (!$articleAllowed($article) || isset($blocked[$id])) {
                continue;
            }
            if (isset($direct[$id])) {
                $link = 'index.php?Itemid=' . $direct[$id];
            } else {
                $catid = (int) $article->catid;
                $menuId = null;
                while ($catid > 1 && isset($categories[$catid])) {
                    if (isset($categoryMenus[$catid])) {
                        $menuId = $categoryMenus[$catid];
                        break;
                    }
                    $catid = (int) $categories[$catid]->parent_id;
                }
                if ($menuId === null) {
                    // No reliable public category route: omit rather than guess a canonical.
                    continue;
                }
                $link = 'index.php?option=com_content&view=article&id=' . (int) $id
                    . ':' . rawurlencode($article->alias) . '&catid=' . (int) $article->catid
                    . '&Itemid=' . $menuId;
            }
            $url = $this->route($link, $origin);
            if ($url !== null) {
                $entries[$url] = Sitemap::lastmod($article->modified ?? null) ?? Sitemap::lastmod($article->created ?? null);
            }
        }
        return $entries;
    }

    private function route(string $link, string $origin): ?string
    {
        $url = Route::link('site', $link, false, Route::TLS_IGNORE, false);
        if (!is_string($url) || preg_match('~[?#\x00-\x20]~', $url) || str_starts_with($url, '//')
            || preg_match('~^[a-z][a-z0-9+.-]*:~i', $url)) {
            return null;
        }
        return $origin . '/' . ltrim($url, '/');
    }
}
