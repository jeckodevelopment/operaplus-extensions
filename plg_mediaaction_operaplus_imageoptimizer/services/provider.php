<?php
defined('_JEXEC') or die;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use OperaPlus\Plugin\MediaAction\ImageOptimizer\Extension\ImageOptimizerPlugin;
// The installer can boot a plugin before the generated PSR-4 map is refreshed.
require_once __DIR__ . '/../src/Extension/ImageOptimizerPlugin.php';
require_once __DIR__ . '/../src/Optimizer.php';
return new class implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->set(PluginInterface::class, function (Container $container) {
            $plugin = new ImageOptimizerPlugin((array) PluginHelper::getPlugin('media-action', 'operaplus_imageoptimizer'));
            $plugin->setApplication(Factory::getApplication());
            return $plugin;
        });
    }
};
