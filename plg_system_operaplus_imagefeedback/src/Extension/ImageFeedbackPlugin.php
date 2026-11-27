<?php
namespace OperaPlus\Plugin\System\ImageFeedback\Extension;
defined('_JEXEC') or die;
use Joomla\CMS\Document\HtmlDocument;
use Joomla\CMS\Event\Application\BeforeCompileHeadEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Event\SubscriberInterface;

final class ImageFeedbackPlugin extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = false;

    public static function getSubscribedEvents(): array
    {
        return ['onBeforeCompileHead' => 'onBeforeCompileHead'];
    }

    public function onBeforeCompileHead(BeforeCompileHeadEvent $event): void
    {
        $app = $this->getApplication();
        if ($app->getInput()->getCmd('option') !== 'com_media'
            || $app->getInput()->getCmd('view', 'media') !== 'media'
            || !$app->getIdentity()->authorise('core.create', 'com_media')
            || !PluginHelper::isEnabled('media-action', 'operaplus_imageoptimizer')) { return; }
        $doc = $app->getDocument();
        if (!$doc instanceof HtmlDocument) { return; }
        $this->loadLanguage();
        $doc->addScriptOptions('operaplus.imagefeedback', [
            'missing' => Text::_('PLG_SYSTEM_OPERAPLUS_IMAGEFEEDBACK_MISSING'),
        ]);
        $doc->getWebAssetManager()->registerAndUseScript(
            'operaplus.imagefeedback',
            'media/plg_system_operaplus_imagefeedback/js/feedback.js',
            ['version' => 'auto'],
            ['defer' => true],
            ['core', 'messages']
        );
    }
}
