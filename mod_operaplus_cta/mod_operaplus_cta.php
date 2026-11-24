<?php
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\HTML\HTMLHelper;
use OperaPlus\Cta\Config;

require_once __DIR__ . '/src/Config.php';
$config = Config::from($params->toArray());
if ($config['heading'] === '' && $config['description'] === '' && ($config['button_text'] === '' || $config['button_url'] === '')) {
    $module->showtitle = 0;
    return;
}
if ($config['image'] !== '') {
    $config['image'] = Config::safeUrl(HTMLHelper::cleanImageURL($config['image'])->url, true);
}
$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->registerAndUseStyle('mod_operaplus_cta', 'mod_operaplus_cta/cta.css');
require ModuleHelper::getLayoutPath('mod_operaplus_cta', $params->get('layout', 'default'));
