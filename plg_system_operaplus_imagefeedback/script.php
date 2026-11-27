<?php
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

/** Enable the UI companion on first installation, never change existing settings. */
class PlgSystemOperaplus_imagefeedbackInstallerScript
{
    public function postflight($type, $parent): bool
    {
        if ($type !== 'install') { return true; }
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->update($db->quoteName('#__extensions'))
            ->set($db->quoteName('enabled') . ' = 1')
            ->where($db->quoteName('type') . ' = ' . $db->quote('plugin'))
            ->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
            ->where($db->quoteName('element') . ' = ' . $db->quote('operaplus_imagefeedback'));
        $db->setQuery($query)->execute();
        return true;
    }
}
