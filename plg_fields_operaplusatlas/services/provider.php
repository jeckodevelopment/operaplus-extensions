<?php
defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use OperaPlus\Plugin\Fields\Atlas\Extension\AtlasPlugin;

// The installer can boot a plugin before the generated PSR-4 map is refreshed.
require_once __DIR__ . '/../src/Extension/AtlasPlugin.php';
return new class implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->set(PluginInterface::class, function (Container $container) {
            $plugin = new AtlasPlugin((array) PluginHelper::getPlugin('fields', 'operaplusatlas'));
            $plugin->setApplication(Factory::getApplication());
            return $plugin;
        });
    }
};
