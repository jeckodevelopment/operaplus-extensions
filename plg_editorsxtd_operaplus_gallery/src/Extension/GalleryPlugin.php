<?php
declare(strict_types=1);

namespace OperaPlus\Plugin\EditorsXtd\Gallery\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Editor\Button\Button;
use Joomla\CMS\Event\Editor\EditorButtonsSetupEvent;
use Joomla\CMS\Event\Plugin\AjaxEvent;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\MediaHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Registry\Registry;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Event\SubscriberInterface;
use OperaPlus\Gallery\Store;
use OperaPlus\Gallery\UploadLimit;
use OperaPlus\Plugin\MediaAction\ImageOptimizer\Optimizer;

final class GalleryPlugin extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return ['onEditorButtonsSetup' => 'setupButtons', 'onAjaxOperaplusgallery' => 'ajax'];
    }

    private function canUse(): bool
    {
        $app = $this->getApplication();
        $user = $app->getIdentity();
        return $app->isClient('administrator') && !$user->guest
            && $user->authorise('core.manage', 'com_content') && $user->authorise('core.create', 'com_media');
    }

    private function assertArticle(int $id): void
    {
        if ($id < 1 || !$this->canUse()) {
            throw new \RuntimeException(Text::_('PLG_OPGALLERY_DENIED'), 403);
        }
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)->select($db->quoteName(['id', 'created_by', 'checked_out', 'state']))
            ->from($db->quoteName('#__content'))->where($db->quoteName('id') . ' = ' . $id);
        $article = $db->setQuery($query)->loadObject();
        $user = $this->getApplication()->getIdentity();
        $asset = 'com_content.article.' . $id;
        if (!$article || (int) $article->state === -2
            || ((int) $article->checked_out !== 0 && (int) $article->checked_out !== (int) $user->id)
            || !($user->authorise('core.edit', $asset)
                || ((int) $article->created_by === (int) $user->id && $user->authorise('core.edit.own', $asset)))) {
            throw new \RuntimeException(Text::_('PLG_OPGALLERY_DENIED'), 403);
        }
    }

    private function maxUploadBytes(): int
    {
        require_once JPATH_LIBRARIES . '/operaplus/src/GalleryUploadLimit.php';
        return UploadLimit::bytes(
            (float) ComponentHelper::getParams('com_media')->get('upload_maxsize', 0),
            (string) ini_get('upload_max_filesize'),
            (string) ini_get('post_max_size'),
            filter_var(ini_get('file_uploads'), FILTER_VALIDATE_BOOLEAN)
        );
    }

    public function setupButtons(EditorButtonsSetupEvent $event): void
    {
        $app = $this->getApplication();
        $input = $app->getInput();
        if (!$this->canUse() || $input->getCmd('option') !== 'com_content'
            || $input->getCmd('view') !== 'article' || $input->getCmd('layout') !== 'edit'
            || in_array($this->_name, $event->getDisabledButtons(), true)) {
            return;
        }
        $id = $input->getInt('id');
        if ($id > 0) {
            try { $this->assertArticle($id); } catch (\Throwable $e) { return; }
        }
        $wa = $app->getDocument()->getWebAssetManager();
        $asset = 'editor-button.' . $this->_name;
        if (!$wa->assetExists('script', $asset)) {
            $wa->registerScript($asset, 'plg_editors-xtd_operaplus_gallery/gallery.js', [], ['type' => 'module'], ['editors']);
            $wa->registerStyle($asset, 'plg_editors-xtd_operaplus_gallery/gallery.css');
        }
        $wa->useScript($asset)->useStyle($asset);
        require_once JPATH_LIBRARIES . '/operaplus/src/GalleryStore.php';
        $labels = [];
        foreach (['button','title','draft','drop','choose','columns','credit','alt','caption','individual','remove','up','down',
            'insert','cancel','busy','ready','failed','empty','limit','retry','confirmcancel','error','existing','new','edit','apply','changed','maxupload','toolarge','uploadunavailable'] as $key) {
            $labels[$key] = Text::_('PLG_OPGALLERY_' . strtoupper($key));
        }
        $event->getButtonsRegistry()->add(new Button($this->_name, [
            'action' => 'operaplus-insert-gallery', 'text' => $labels['button'],
            'icon' => 'images', 'name' => $this->_type . '_' . $this->_name,
        ], ['labels' => $labels, 'article' => $id, 'token' => Session::getFormToken(),
            'endpoint' => Uri::base() . 'index.php?option=com_ajax&group=editors-xtd&plugin=operaplusgallery&format=json',
            'root' => Uri::root(), 'limit' => Store::MAX_IMAGES, 'maxUploadBytes' => $this->maxUploadBytes()]));
    }

    private function store(): Store
    {
        // Explicitly use the installed shared converter. No native upload event
        // or original file is created by this dedicated gallery workflow.
        require_once JPATH_LIBRARIES . '/operaplus/src/GalleryStore.php';
        require_once JPATH_PLUGINS . '/media-action/operaplus_imageoptimizer/src/Optimizer.php';
        return new Store(JPATH_ROOT . '/images', Optimizer::convertGallery(...));
    }

    private function upload(Store $store, int $article, string $gallery): array
    {
        $file = $this->getApplication()->getInput()->files->get('photo', null, 'array');
        if (!is_array($file) || ($file['error'] ?? null) !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null)
            || !is_string($file['name'] ?? null) || !is_uploaded_file($file['tmp_name'])
            || !is_int($file['size'] ?? null) || $file['size'] < 1) {
            throw new \InvalidArgumentException(Text::_('PLG_OPGALLERY_INVALID_UPLOAD'));
        }
        if ($file['size'] > $this->maxUploadBytes()) {
            throw new \InvalidArgumentException(Text::_('PLG_OPGALLERY_TOOLARGE'));
        }
        if (!preg_match('/^[\p{L}\p{N}][\p{L}\p{M}\p{N} _-]{0,160}\.(jpe?g|png|webp)$/iuD', $file['name'])) {
            throw new \InvalidArgumentException(Text::_('PLG_OPGALLERY_INVALID_UPLOAD'));
        }
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $types = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        if (!isset($types[$ext])) {
            throw new \InvalidArgumentException(Text::_('PLG_OPGALLERY_INVALID_UPLOAD'));
        }
        $media = ComponentHelper::getParams('com_media');
        foreach (['image_extensions','restrict_uploads_extensions'] as $key) {
            $allowed = array_map('trim', explode(',', strtolower((string) $media->get($key, 'jpg,jpeg,png,webp'))));
            if (!in_array($ext, $allowed, true) || !in_array('webp', $allowed, true)) {
                throw new \RuntimeException(Text::_('PLG_OPGALLERY_MEDIA_POLICY') . ' (' . $key . ')');
            }
        }
        // Match the local filesystem provider's actual configured roots, including
        // its defaults when the subform is absent or has not been saved.
        $plugin = PluginHelper::getPlugin('filesystem', 'local');
        $local = $plugin ? new Registry($plugin->params) : null;
        $directories = $local?->get('directories', [(object) ['directory' => 'images'], (object) ['directory' => 'files']]);
        if (is_string($directories)) {
            $directories = json_decode($directories);
        }
        $hasImages = false;
        if (is_iterable($directories)) {
            foreach ($directories as $directory) {
                if ((((array) $directory)['directory'] ?? null) === 'images') {
                    $hasImages = true;
                }
            }
        }
        if (!$plugin || !$hasImages) {
            throw new \RuntimeException(Text::_('PLG_OPGALLERY_MEDIA_POLICY') . ' (local-images)');
        }
        $info = @getimagesize($file['tmp_name']);
        if (!$info || ($info['mime'] ?? '') !== $types[$ext]) {
            throw new \InvalidArgumentException(Text::_('PLG_OPGALLERY_INVALID_UPLOAD'));
        }
        if ($media->get('check_mime', 1)) {
            $allowed = array_map('trim', explode(',', strtolower(str_replace('\\', '', (string) $media->get('upload_mime', 'image/jpeg,image/png,image/webp')))));
            if (!in_array($types[$ext], $allowed, true) || !in_array('image/webp', $allowed, true)) {
                throw new \RuntimeException(Text::_('PLG_OPGALLERY_MEDIA_POLICY') . ' (upload_mime)');
            }
        }
        if (!MediaHelper::checkFileExtension($ext) || !MediaHelper::checkFileExtension('webp')) {
            throw new \RuntimeException(Text::_('PLG_OPGALLERY_MEDIA_POLICY') . ' (extension)');
        }
        if ($media->get('check_mime', 1) && MediaHelper::getMimeType($file['tmp_name'], true) !== $types[$ext]) {
            throw new \RuntimeException(Text::_('PLG_OPGALLERY_MEDIA_POLICY') . ' (image-mime)');
        }
        $bytes = file_get_contents($file['tmp_name'], false, null, 0, 20 * 1024 * 1024 + 1);
        if ($bytes === false || strlen($bytes) !== $file['size']) {
            throw new \RuntimeException(Text::_('PLG_OPGALLERY_INVALID_UPLOAD'));
        }
        try {
            return $store->add($article, $gallery, $bytes, $ext, max(50, min(100, (int) $this->params->get('quality', 85))), true);
        } catch (\Throwable $e) {
            // Only return known reason codes; filesystem paths and traces stay private.
            if (preg_match('/^(?:Image|Thumbnail) conversion failed: ([a-z-]+)$/D', $e->getMessage(), $matches)) {
                throw new \RuntimeException(Text::_('PLG_OPGALLERY_CONVERSION') . ' (' . $matches[1] . ')');
            }
            throw new \RuntimeException(Text::_('PLG_OPGALLERY_ERROR'));
        }
    }

    public function ajax(AjaxEvent $event): void
    {
        $app = $this->getApplication();
        $input = $app->getInput();
        if (!$this->canUse() || $input->getMethod() !== 'POST' || !Session::checkToken('post')) {
            throw new \RuntimeException(Text::_('PLG_OPGALLERY_DENIED'), 403);
        }
        $post = $input->post;
        $article = $post->getInt('article');
        $this->assertArticle($article);
        $action = $post->getCmd('action');
        $gallery = $post->getString('gallery');
        try { $store = $this->store(); } catch (\Throwable $e) { throw new \RuntimeException(Text::_('PLG_OPGALLERY_ERROR')); }
        $session = $app->getSession();
        $staged = $session->get('operaplus.gallery.staged', []);
        if (!is_array($staged)) { $staged = []; }
        $staged = array_filter($staged, static fn ($entry) => is_array($entry) && ($entry['expires'] ?? 0) >= time());
        try {
            if ($action === 'create' || $action === 'fork') {
                if (count($staged) >= 12) {
                    throw new \RuntimeException(Text::_('PLG_OPGALLERY_LIMIT'));
                }
                $expires = time() + Store::STAGE_TTL;
                $gallery = $action === 'fork' ? $store->fork($article, $gallery, $expires) : $store->create($article, $expires);
                $staged[$gallery] = ['article' => $article, 'expires' => $expires];
                $session->set('operaplus.gallery.staged', $staged);
                $event->addResult(['gallery' => $gallery] + $store->read($article, $gallery));
                return;
            }
            if (!Store::validId($gallery)) {
                throw new \InvalidArgumentException(Text::_('PLG_OPGALLERY_ERROR'));
            }
            if (in_array($action, ['upload', 'save', 'discard'], true)
                && (int) ($staged[$gallery]['article'] ?? 0) !== $article) {
                throw new \RuntimeException(Text::_('PLG_OPGALLERY_DENIED'));
            }
            if ($action === 'discard') {
                $store->discard($article, $gallery, true);
                unset($staged[$gallery]);
                $session->set('operaplus.gallery.staged', $staged);
                $event->addResult(['discarded' => true]);
                return;
            }
            if ($action === 'upload') {
                $photo = $this->upload($store, $article, $gallery);
                $event->addResult($photo);
                return;
            }
            if ($action === 'read') {
                $event->addResult((int) ($staged[$gallery]['article'] ?? 0) === $article
                    ? $store->read($article, $gallery) : $store->readCommitted($article, $gallery));
                return;
            }
            if ($action === 'save') {
                $raw = $post->get('metadata', '', 'RAW');
                if (!is_string($raw) || strlen($raw) > 524288) {
                    throw new \InvalidArgumentException(Text::_('PLG_OPGALLERY_ERROR'));
                }
                $metadata = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
                if (!is_array($metadata) || !is_array($metadata['images'] ?? null) || !$metadata['images']
                    || !is_array($metadata['expected'] ?? null) || !is_string($metadata['credit'] ?? null)) {
                    throw new \InvalidArgumentException(Text::_('PLG_OPGALLERY_EMPTY'));
                }
                $doc = $store->update($article, $gallery, $metadata['images'], $metadata['credit'], $metadata['expected'], true);
                unset($staged[$gallery]);
                $session->set('operaplus.gallery.staged', $staged);
                $event->addResult($doc);
                return;
            }
        } catch (\Throwable $e) {
            $message = $e->getMessage();
            if (in_array($message, [Text::_('PLG_OPGALLERY_INVALID_UPLOAD'), Text::_('PLG_OPGALLERY_MEDIA_POLICY'),
                Text::_('PLG_OPGALLERY_EMPTY'), Text::_('PLG_OPGALLERY_DENIED'), Text::_('PLG_OPGALLERY_LIMIT'), Text::_('PLG_OPGALLERY_TOOLARGE')], true) || str_starts_with($message, Text::_('PLG_OPGALLERY_MEDIA_POLICY') . ' (') || str_starts_with($message, Text::_('PLG_OPGALLERY_CONVERSION') . ' (')) {
                throw new \RuntimeException($message);
            }
            throw new \RuntimeException(Text::_('PLG_OPGALLERY_ERROR'));
        }
        throw new \InvalidArgumentException(Text::_('PLG_OPGALLERY_ERROR'));
    }
}
