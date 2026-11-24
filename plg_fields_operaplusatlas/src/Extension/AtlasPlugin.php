<?php
namespace OperaPlus\Plugin\Fields\Atlas\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Form\Form;
use Joomla\Component\Fields\Administrator\Plugin\FieldsPlugin;
use Joomla\Event\SubscriberInterface;
use OperaPlus\Atlas\Index;

final class AtlasPlugin extends FieldsPlugin implements SubscriberInterface
{
    public function onCustomFieldsPrepareDom($field, \DOMElement $parent, Form $form)
    {
        $node = parent::onCustomFieldsPrepareDom($field, $parent, $form);
        if (!$node) { return null; }
        $node->setAttribute('type', 'list');
        $multiple = (bool) $field->fieldparams->get('multiple', 1);
        $node->setAttribute('multiple', $multiple ? 'true' : 'false');
        $node->setAttribute('layout', 'joomla.form.field.list-fancy-select');
        $node->setAttribute('filter', $multiple ? 'array' : 'string');
        $node->setAttribute('validate', 'options');
        if (!$multiple) {
            $option = $node->ownerDocument->createElement('option');
            $option->setAttribute('value', '');
            $option->appendChild($node->ownerDocument->createTextNode(\Joomla\CMS\Language\Text::_('JSELECT')));
            $node->appendChild($option);
        }
        $library = JPATH_LIBRARIES . '/operaplus/src/Index.php';
        $index = [];
        if (is_file($library)) {
            require_once $library;
            $index = Index::load(JPATH_CACHE . '/operaplus-atlas-v1.json');
        }
        $current = $field->rawvalue ?? $field->value ?? [];
        if (is_string($current)) {
            $decoded = json_decode($current, true);
            $current = is_array($decoded) ? $decoded : [$current];
        }
        // Preserve references unavailable during a temporary outage.
        foreach ((array) $current as $ref) {
            if (is_string($ref) && preg_match('/^(opera|composer|theatre|festival|production):[a-zA-Z0-9_-]{1,128}$/D', $ref) && !isset($index[$ref])) {
                $index[$ref] = ['title' => $ref, 'type' => 'unavailable', 'subtitle' => null];
            }
        }
        uasort($index, static fn ($a, $b) => strcasecmp($a['title'], $b['title']));
        foreach ($index as $ref => $entity) {
            $option = $node->ownerDocument->createElement('option');
            $option->setAttribute('value', $ref);
            $option->appendChild($node->ownerDocument->createTextNode($entity['title'] . ' — ' . $entity['type'] . ($entity['subtitle'] ? ' · ' . $entity['subtitle'] : '')));
            $node->appendChild($option);
        }
        return $node;
    }
}
