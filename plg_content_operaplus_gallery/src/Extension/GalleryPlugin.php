<?php
declare(strict_types=1);

namespace OperaPlus\Plugin\Content\Gallery\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Event\Content\ContentPrepareEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Uri\Uri;
use Joomla\Event\SubscriberInterface;
use OperaPlus\Gallery\Renderer;
use OperaPlus\Gallery\Store;

final class GalleryPlugin extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return ['onContentPrepare' => 'prepare'];
    }

    public function prepare(ContentPrepareEvent $event): void
    {
        $app = $this->getApplication();
        $context = $event->getContext();
        $item = $event->getItem();
        if (!$app->isClient('site') || !str_starts_with($context, 'com_content.')
            || !isset($item->text) || !is_string($item->text) || !str_contains($item->text, '{operaplusgallery')) {
            return;
        }
        require_once JPATH_LIBRARIES . '/operaplus/src/GalleryRenderer.php';
        // Do not expose shortcode text in category/list previews, search results,
        // or unrelated content contexts. A gallery belongs to its current article.
        if ($context !== 'com_content.article' || $app->getInput()->getCmd('view') !== 'article'
            || $event->getParams()->get('intro_only') || (int) ($item->id ?? 0) < 1) {
            $item->text = Renderer::replace($item->text, static fn () => '');
            return;
        }
        require_once JPATH_LIBRARIES . '/operaplus/src/GalleryStore.php';
        $this->loadLanguage();
        $labels = [];
        foreach (['gallery','open','close','previous','next','credit','counter'] as $key) {
            $labels[$key] = Text::_('PLG_CONTENT_OPGALLERY_' . strtoupper($key));
        }
        $store = new Store(JPATH_ROOT . '/images', static fn () => null);
        $article = (int) $item->id;
        $rendered = false;
        $item->text = Renderer::replace($item->text, static function (string $gallery, int $columns) use ($store, $article, $labels, &$rendered): string {
            try {
                $doc = $store->read($article, $gallery);
                $html = Renderer::gallery($article, $gallery, $columns, $doc, Uri::root(), $labels);
                $rendered = $rendered || $html !== '';
                return $html;
            } catch (\Throwable $e) {
                return '';
            }
        });
        if (!$rendered || $app->getDocument()->getType() !== 'html') {
            return;
        }
        $wa = $app->getDocument()->getWebAssetManager();
        $asset = 'plg_content_operaplus_gallery';
        if (!$wa->assetExists('script', $asset)) {
            $wa->registerScript($asset, 'plg_content_operaplus_gallery/lightbox.js', [], ['defer' => true]);
            $wa->registerStyle($asset, 'plg_content_operaplus_gallery/gallery.css');
        }
        $wa->useStyle($asset);
        if (!$app->getInput()->getBool('print')) {
            $wa->useScript($asset);
        }
    }
}
