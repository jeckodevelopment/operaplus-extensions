<?php
namespace OperaPlus\Plugin\MediaAction\ImageOptimizer\Extension;
defined('_JEXEC') or die;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Event\Model\BeforeSaveEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use OperaPlus\Plugin\MediaAction\ImageOptimizer\Optimizer;

final class ImageOptimizerPlugin extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['onContentBeforeSave' => 'onContentBeforeSave'];
    }

    public function onContentBeforeSave(BeforeSaveEvent $event): void
    {
        if ($event->getContext() !== 'com_media.file' || !$event->getIsNew()) { return; }
        $item = $event->getItem();
        if (($item->adapter ?? '') !== 'local-images' || !is_string($item->data ?? null)
            || !is_string($item->name ?? null) || !is_string($item->path ?? null)) { return; }
        if ($this->getApplication()->getInput()->json->getBool('override', false)) { return; }
        if (!preg_match('/^([a-zA-Z0-9][a-zA-Z0-9 _-]{0,160})\.(jpe?g|png)$/iD', $item->name, $m)) { return; }
        $ext = strtolower($m[2]);
        if (!(bool) $this->params->get($ext === 'png' ? 'convert_png' : 'convert_jpeg', 1)) { return; }
        $path = $item->path;
        if (preg_match('~(?:^|/)\.{1,2}(?:/|$)|[\\\\\x00-\x1f]~', $path)
            || Optimizer::excluded($path, (string) $this->params->get('excluded_folders', "images/logos\nimages/branding"))) { return; }
        $root = realpath(JPATH_ROOT . '/images');
        $folder = $root !== false ? realpath($root . '/' . ltrim($path, '/')) : false;
        if (!$root || !$folder || ($folder !== $root && !str_starts_with($folder, $root . DIRECTORY_SEPARATOR))) { return; }
        // Do not bypass a configured media restriction.
        $media = ComponentHelper::getParams('com_media');
        foreach (['image_extensions', 'restrict_uploads_extensions'] as $key) {
            $allowed = array_map('trim', explode(',', strtolower((string) $media->get($key, 'jpg,jpeg,png,webp'))));
            if (!in_array($ext, $allowed, true) || !in_array('webp', $allowed, true)) { return; }
        }
        if ($media->get('check_mime', 1)) {
            $mimes = array_map('trim', explode(',', strtolower((string) $media->get('upload_mime', 'image/jpeg,image/png,image/webp'))));
            if (!in_array($ext === 'png' ? 'image/png' : 'image/jpeg', $mimes, true) || !in_array('image/webp', $mimes, true)) { return; }
        }
        // Joomla checks the old filename before the event; never target another
        // user's existing file. A random suffix also handles concurrent uploads.
        try {
            $converted = Optimizer::convert($item->data, $ext, (int) $this->params->get('quality', 90), (int) $this->params->get('max_edge', 1920));
            if ($converted === null) { return; }
            $stem = trim(preg_replace('/[ _]+/', '-', $m[1]), '-');
            do { $name = $stem . '-' . bin2hex(random_bytes(8)) . '.webp'; }
            while (file_exists($folder . '/' . $name) || is_link($folder . '/' . $name));
            $item->name = $name;
            $item->extension = 'webp';
            $item->data = $converted;
        } catch (\Throwable $e) {
            // Original unchanged unless conversion fully succeeds.
        }
    }
}
