<?php
defined('_JEXEC') or die;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use OperaPlus\Plugin\System\ImageFeedback\Extension\ImageFeedbackPlugin;
// The newly enabled plugin may be booted before Joomla regenerates its namespace cache.
require_once __DIR__ . '/../src/Extension/ImageFeedbackPlugin.php';
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
