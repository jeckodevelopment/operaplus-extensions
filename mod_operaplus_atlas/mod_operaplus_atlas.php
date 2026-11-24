<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use OperaPlus\Atlas\Index;

$library = JPATH_LIBRARIES . '/operaplus/src/Index.php';
$entities = [];
$refs = [];
$primary = null;
if (is_file($library)) {
    require_once $library;
    $app = Factory::getApplication();
    if ($params->get('mode', 'context') === 'manual') {
        $refs = preg_split('/[\s,]+/', trim((string) $params->get('references', '')), -1, PREG_SPLIT_NO_EMPTY);
    } elseif ($app->getInput()->getCmd('option') === 'com_content' && $app->getInput()->getCmd('view') === 'article') {
        $id = $app->getInput()->getInt('id');
        if ($id > 0) {
            try {
                $model = $app->bootComponent('com_content')->getMVCFactory()->createModel('Article', 'Site', ['ignore_request' => false]);
                $item = $model->getItem($id);
                $now = Factory::getDate()->toSql();
                if ($item && $item->params->get('access-view', false)
                    && (int) $item->state === 1
                    && (empty($item->publish_up) || $item->publish_up <= $now)
                    && (empty($item->publish_down) || $item->publish_down >= $now)) {
                    foreach (FieldsHelper::getFields('com_content.article', $item, false) as $field) {
                        if ($field->type !== 'operaplusatlas'
                            || !in_array((int) $field->access, $app->getIdentity()->getAuthorisedViewLevels(), true)) {
                            continue;
                        }
                        $value = Index::references($field->rawvalue ?? $field->value ?? []);
                        if ($field->name === $params->get('field_name', 'atlas-entities')) {
                            $refs = $value;
                        } elseif ($field->name === $params->get('primary_field', 'atlas-primary')) {
                            $primary = $value[0] ?? null;
                        }
                    }
                }
            } catch (\Throwable) {
                $refs = [];
            }
        }
    }
    if ($refs) {
        $index = Index::load(JPATH_CACHE . '/operaplus-atlas-v1.json');
        $entities = Index::resolve($index, $refs, (bool) $params->get('show_all', 0), $primary);
    }
}
if (!$entities && $params->get('hide_empty', 1)) {
    $module->showtitle = 0;
    $module->content = '';
    return;
}
$wa = Factory::getApplication()->getDocument()->getWebAssetManager();
$wa->registerAndUseStyle('mod_operaplus_atlas', 'mod_operaplus_atlas/atlas.css');
require ModuleHelper::getLayoutPath('mod_operaplus_atlas', $params->get('layout', 'default'));
