<?php
declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Log\Log;

return new class implements InstallerScriptInterface {
    public function preflight(string $type, InstallerAdapter $adapter): bool { return true; }
    public function install(InstallerAdapter $adapter): bool { return true; }
    public function update(InstallerAdapter $adapter): bool { return true; }
    public function uninstall(InstallerAdapter $adapter): bool { return true; }

    public function postflight(string $type, InstallerAdapter $adapter): bool
    {
        // Rebuild Joomla's generated extension namespace map after all package
        // children are installed. Use the core API, not a hand-edited map.
        if (!in_array(strtolower($type), ['install', 'update', 'discover_install'], true)) {
            return true;
        }
        try {
            require_once JPATH_LIBRARIES . '/namespacemap.php';
            $map = new \JNamespacePsr4Map();
            $map->create();
            $map->load();
            return true;
        } catch (\Throwable $e) {
            Log::add('Opera+ Extensions: namespace map refresh failed. Regenerate the Joomla namespace cache before enabling new plugins.', Log::WARNING, 'jerror');
            return false;
        }
    }
};
