<?php
defined('_JEXEC') or die;
use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use OperaPlus\Plugin\System\Sitemap\Extension\SitemapPlugin;
require_once __DIR__ . '/../src/Sitemap.php';
require_once __DIR__ . '/../src/Extension/SitemapPlugin.php';
return new class implements ServiceProviderInterface {
    public function register(Container $container)
    {
        $container->set(PluginInterface::class, function (Container $container) {
            $plugin = new SitemapPlugin((array) PluginHelper::getPlugin('system', 'operaplus_sitemap'));
            $plugin->setApplication(Factory::getApplication());
            $plugin->setDatabase($container->get(DatabaseInterface::class));
            return $plugin;
        });
    }
};
