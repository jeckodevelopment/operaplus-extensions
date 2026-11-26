<?php
namespace OperaPlus\Plugin\System\Masthead\Extension;
defined('_JEXEC') or die;
use Joomla\CMS\Event\Model\PrepareFormEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
final class MastheadPlugin extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['onContentPrepareForm' => 'onContentPrepareForm'];
    }
    public function onContentPrepareForm(PrepareFormEvent $event): void
    {
        if (!$this->getApplication()->isClient('administrator')) {
            return;
        }
        $form = $event->getForm();
        if ($form->getName() !== 'com_menus.item') {
            return;
        }
        $this->loadLanguage();
        $form->loadFile(__DIR__ . '/../../forms/menu.xml', false);
    }
}
