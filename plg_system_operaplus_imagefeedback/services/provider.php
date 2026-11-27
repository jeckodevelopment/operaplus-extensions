<?php
defined('_JEXEC') or die;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use OperaPlus\Plugin\System\ImageFeedback\Extension\ImageFeedbackPlugin;
return new class implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->set(PluginInterface::class, function (Container $container) {
            $plugin = new ImageFeedbackPlugin((array) PluginHelper::getPlugin('system', 'operaplus_imagefeedback'));
            $plugin->setApplication(Factory::getApplication());
            return $plugin;
        });
    }
};
