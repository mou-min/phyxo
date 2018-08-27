<?php
/*
 * This file is part of Phyxo package
 *
 * Copyright(c) Nicolas Roudaire  https://www.phyxo.net/
 * Licensed under the GPL version 2.0 license.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Phyxo\Functions\Ws;

use Phyxo\Ws\Error;
use Phyxo\Plugin\Plugins;

class Plugin
{
    /**
     * API method
     * Returns the list of all plugins
     * @param mixed[] $params
     */
    public static function getList($params, $service)
    {
        $plugins = new Plugins($GLOBALS['conn']);
        $plugins->sortFsPlugins('name');
        $plugin_list = [];

        foreach ($plugins->getFsPlugins() as $plugin_id => $fs_plugin) {
            if (isset($plugins->getDbPlugins()[$plugin_id])) {
                $state = $plugins->getDbPlugins()[$plugin_id]['state'];
            } else {
                $state = 'uninstalled';
            }

            $plugin_list[] = [
                'id' => $plugin_id,
                'name' => $fs_plugin['name'],
                'version' => $fs_plugin['version'],
                'state' => $state,
                'description' => $fs_plugin['description'],
            ];
        }

        return $plugin_list;
    }

    /**
     * API method
     * Performs an action on a plugin
     * @param mixed[] $params
     *    @option string action
     *    @option string plugin
     *    @option string pwg_token
     */
    public static function performAction($params, $service)
    {
        global $template;

        if (\Phyxo\Functions\Utils::get_token() != $params['pwg_token']) {
            return new Error(403, 'Invalid security token');
        }

        define('IN_ADMIN', true);

        $plugins = new Plugins($GLOBALS['conn']);
        $errors = $plugins->performAction($params['action'], $params['plugin']);

        if (!empty($errors)) {
            return new Error(500, $errors);
        } else {
            if (in_array($params['action'], ['activate', 'deactivate'])) {
                $template->delete_compiled_templates();
            }
            return true;
        }
    }
}
