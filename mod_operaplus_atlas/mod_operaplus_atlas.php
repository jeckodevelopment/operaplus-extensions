<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Language\Text;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use OperaPlus\Atlas\Index;

$library = JPATH_LIBRARIES . '/operaplus/src/Index.php';
$entities = [];
$refs = [];
if (is_file($library)) {
    require_once $library;
    $app = Factory::getApplication();
    if ($params->get('mode', 'context') === 'manual') {
        $refs = preg_split('/[\s,]+/', trim((string) $params->get('references', '')), -1, PREG_SPLIT_NO_EMPTY);
    } elseif ($app->input->getCmd('option') === 'com_content' && $app->input->getCmd('view') === 'article') {
        $id = $app->input->getInt('id');
        if ($id > 0) {
            try {
                $model = $app->bootComponent('com_content')->getMVCFactory()->createModel('Article', 'Site', ['ignore_request' => true]);
                $item = $model->getItem($id);
                if ($item && in_array((int) $item->access, $app->getIdentity()->getAuthorisedViewLevels(), true)
                    && (int) $item->state === 1) {
                    foreach (FieldsHelper::getFields('com_content.article', $item, false) as $field) {
                        if ($field->type === 'operaplusatlas' && $field->name === $params->get('field_name', 'atlas-entities')
                            && in_array((int) $field->access, $app->getIdentity()->getAuthorisedViewLevels(), true)) {
                            $value = $field->rawvalue ?? $field->value ?? [];
                            if (is_string($value)) {
                                $decoded = json_decode($value, true);
                                $value = is_array($decoded) ? $decoded : [$value];
                            }
                            $refs = is_array($value) ? $value : [];
                            break;
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
        $entities = Index::resolve($index, $refs, (bool) $params->get('show_all', 0));
    }
}
if (!$entities && $params->get('hide_empty', 1)) {
    $module->showtitle = 0;
    $module->content = '';
    return;
}
require ModuleHelper::getLayoutPath('mod_operaplus_atlas', $params->get('layout', 'default'));
