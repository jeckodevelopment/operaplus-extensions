<?php
namespace OperaPlus\Plugin\EditorsXtd\Templates\Extension;

defined('_JEXEC') or die;
use Joomla\CMS\Editor\Button\Button;
use Joomla\CMS\Event\Editor\EditorButtonsSetupEvent;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;

final class TemplatesPlugin extends CMSPlugin implements SubscriberInterface
{
    protected $autoloadLanguage = true;

    public static function getSubscribedEvents(): array
    {
        return ['onEditorButtonsSetup' => 'setupButtons'];
    }

    public function setupButtons(EditorButtonsSetupEvent $event): void
    {
        $app = $this->getApplication();
        if (!$app->isClient('administrator') || $app->getInput()->getCmd('option') !== 'com_content'
            || in_array($this->_name, $event->getDisabledButtons(), true)) {
            return;
        }
        $wa = $app->getDocument()->getWebAssetManager();
        $asset = 'editor-button.' . $this->_name;
        if (!$wa->assetExists('script', $asset)) {
            $wa->registerScript($asset, 'plg_editors-xtd_operaplus_templates/templates.js', [], ['type'=>'module'], ['editors']);
        }
        $wa->useScript($asset);
        $labels = [];
        foreach (['article','interview','review','longform','dossier','news','choose','insert','cancel','heading','intro','paragraph','question','answer','verdict','context','quote','sources'] as $key) {
            $labels[$key] = Text::_('PLG_EDITORSXTD_OPERAPLUS_TEMPLATES_' . strtoupper($key));
        }
        $event->getButtonsRegistry()->add(new Button($this->_name, [
            'action'=>'operaplus-insert-template',
            'text'=>Text::_('PLG_EDITORSXTD_OPERAPLUS_TEMPLATES_BUTTON'),
            'icon'=>'file-alt',
            'name'=>$this->_type . '_' . $this->_name,
        ], ['labels'=>$labels]));
    }
}
