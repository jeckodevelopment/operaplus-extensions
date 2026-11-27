<?php
namespace OperaPlus\Plugin\MediaAction\ImageOptimizer\Extension;
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Model\BeforeSaveEvent;
use Joomla\CMS\Event\Model\AfterSaveEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use OperaPlus\Plugin\MediaAction\ImageOptimizer\Optimizer;

final class ImageOptimizerPlugin extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;
    private array $pending = [];
    private bool $loggerReady = false;

    public static function getSubscribedEvents(): array
    {
        return ['onContentBeforeSave' => 'onContentBeforeSave', 'onContentAfterSave' => 'onContentAfterSave'];
    }

    private function skip(object $item, string $reason): void
    {
        $this->pending[spl_object_id($item)] = [$reason, 0, 0];
    }

    public function onContentBeforeSave(BeforeSaveEvent $event): void
    {
        if ($event->getContext() !== 'com_media.file' || !$event->getIsNew()) { return; }
        $item = $event->getItem();
        if (!is_string($item->name ?? null) || !preg_match('/\.(jpe?g|png)$/iD', $item->name, $m)) {
            $this->skip($item, 'unsupported-format'); return;
        }
        $ext = strtolower($m[1]);
        if (($item->adapter ?? '') !== 'local-images' || !is_string($item->data ?? null) || !is_string($item->path ?? null)) {
            $this->skip($item, 'adapter'); return;
        }
        if ($this->getApplication()->getInput()->json->getBool('override', false)) {
            $this->skip($item, 'overwrite'); return;
        }
        if (!(bool) $this->params->get($ext === 'png' ? 'convert_png' : 'convert_jpeg', 1)) {
            $this->skip($item, 'format-disabled'); return;
        }
        // Accept ordinary editorial names with accented letters; reject double
        // extensions and path characters rather than bypassing core validation.
        $stem = substr($item->name, 0, -(strlen($ext) + 1));
        if (!preg_match('/^[\p{L}\p{N}][\p{L}\p{M}\p{N} _-]{0,160}$/uD', $stem)) {
            $this->skip($item, 'filename'); return;
        }
        $stem = trim(preg_replace('/[^a-zA-Z0-9_-]+/', '-', $stem), '-_');
        $stem = $stem === '' ? 'image' : substr($stem, 0, 120);
        $path = $item->path;
        if (preg_match('~(?:^|/)\.{1,2}(?:/|$)|[\\\\\x00-\x1f]~', $path)) {
            $this->skip($item, 'path'); return;
        }
        $excluded = (string) $this->params->get('excluded_folders', "images/logos\nimages/branding");
        if (Optimizer::excluded($path, $excluded)) { $this->skip($item, 'excluded-folder'); return; }
        $root = realpath(JPATH_ROOT . '/images');
        $folder = $root !== false ? realpath($root . '/' . ltrim($path, '/')) : false;
        if (!$root || !$folder || ($folder !== $root && !str_starts_with($folder, $root . DIRECTORY_SEPARATOR))) {
            $this->skip($item, 'path'); return;
        }
        if (Optimizer::excluded(substr($folder, strlen($root)), $excluded)) {
            $this->skip($item, 'excluded-folder'); return;
        }
        $media = ComponentHelper::getParams('com_media');
        foreach (['image_extensions', 'restrict_uploads_extensions'] as $key) {
            $allowed = array_map('trim', explode(',', strtolower((string) $media->get($key, 'jpg,jpeg,png,webp'))));
            if (!in_array($ext, $allowed, true) || !in_array('webp', $allowed, true)) {
                $this->skip($item, 'media-policy-' . $key); return;
            }
        }
        if ($media->get('check_mime', 1)) {
            $mimes = array_map('trim', explode(',', strtolower((string) $media->get('upload_mime', 'image/jpeg,image/png,image/webp'))));
            if (!in_array($ext === 'png' ? 'image/png' : 'image/jpeg', $mimes, true) || !in_array('image/webp', $mimes, true)) {
                $this->skip($item, 'media-policy-mime'); return;
            }
        }
        try {
            $reason = '';
            $before = strlen($item->data);
            $converted = Optimizer::convert($item->data, $ext, (int) $this->params->get('quality', 90), (int) $this->params->get('max_edge', 1920), $reason);
            if ($converted === null) { $this->skip($item, $reason ?: 'conversion-error'); return; }
            do { $name = $stem . '-' . bin2hex(random_bytes(8)) . '.webp'; }
            while (file_exists($folder . '/' . $name) || is_link($folder . '/' . $name));
            $this->pending[spl_object_id($item)] = ['converted', $before, strlen($converted)];
            $item->name = $name;
            $item->extension = 'webp';
            $item->data = $converted;
        } catch (\Throwable $e) {
            $this->skip($item, 'conversion-error');
        }
    }

    public function onContentAfterSave(AfterSaveEvent $event): void
    {
        if ($event->getContext() !== 'com_media.file' || !$event->getIsNew()) { return; }
        $item = $event->getItem();
        $id = spl_object_id($item);
        if (!isset($this->pending[$id])) { return; }
        [$reason, $before, $after] = $this->pending[$id];
        unset($this->pending[$id]);
        if ($reason === 'converted') {
            $message = Text::sprintf('PLG_OPIMG_RESULT_CONVERTED', number_format($before / 1024, 1), number_format($after / 1024, 1), (int) round((1 - $after / $before) * 100));
            $type = 'message';
        } else {
            $message = Text::sprintf('PLG_OPIMG_RESULT_SKIPPED', Text::_('PLG_OPIMG_REASON_' . strtoupper(str_replace('-', '_', $reason))));
            $type = in_array($reason, ['unsupported-format', 'excluded-folder', 'overwrite', 'format-disabled', 'not-smaller'], true) ? 'notice' : 'warning';
        }
        // Native JsonResponse includes this queue; a small system companion
        // displays it in the Media Manager, whose core success handler ignores it.
        $this->getApplication()->enqueueMessage('Opera+ Image Optimizer: ' . $message, $type);
        try {
            if (!$this->loggerReady) {
                Log::addLogger(['text_file' => 'operaplus-imageoptimizer.php', 'text_entry_format' => '{DATETIME} {PRIORITY} {MESSAGE}'], Log::ALL, ['operaplus.imageoptimizer']);
                $this->loggerReady = true;
            }
            Log::add($reason . ($before ? " $before -> $after bytes" : ''), $type === 'warning' ? Log::WARNING : Log::INFO, 'operaplus.imageoptimizer');
        } catch (\Throwable $e) {
            // A logging problem must not break a completed native upload.
        }
    }
}
