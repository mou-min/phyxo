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

if (!defined("THEMES_BASE_URL")) {
    die("Hacking attempt!");
}

use Phyxo\Theme\Themes;

$themes = new Themes($conn);

// +-----------------------------------------------------------------------+
// |                           setup check                                 |
// +-----------------------------------------------------------------------+

if (!is_writable(PHPWG_THEMES_PATH)) {
    $page['errors'][] = \Phyxo\Functions\Language::l10n('Add write access to the "%s" directory', 'themes');
}

// +-----------------------------------------------------------------------+
// |                       perform installation                            |
// +-----------------------------------------------------------------------+

if (isset($_GET['revision']) and isset($_GET['extension'])) {
    if (!$userMapper->isWebmaster()) {
        $page['errors'][] = \Phyxo\Functions\Language::l10n('Webmaster status is required.');
    } else {
        \Phyxo\Functions\Utils::check_token();

        try {
            $themes->extractThemeFiles('install', $_GET['revision'], $_GET['extension']);
            $install_status = 'ok';
        } catch (\Exception $e) {
            $page['errors'] = $e->getMessage();
        }

        \Phyxo\Functions\Utils::redirect(THEMES_BASE_URL . '&section=new&installstatus=' . $install_status);
    }
}

// +-----------------------------------------------------------------------+
// |                        installation result                            |
// +-----------------------------------------------------------------------+

if (isset($_GET['installstatus'])) {
    switch ($_GET['installstatus']) {
        case 'ok':
            $page['infos'][] = \Phyxo\Functions\Language::l10n('Theme has been successfully installed');
            break;

        case 'temp_path_error':
            $page['errors'][] = \Phyxo\Functions\Language::l10n('Can\'t create temporary file.');
            break;

        case 'dl_archive_error':
            $page['errors'][] = \Phyxo\Functions\Language::l10n('Can\'t download archive.');
            break;

        case 'archive_error':
            $page['errors'][] = \Phyxo\Functions\Language::l10n('Can\'t read or extract archive.');
            break;

        default:
            $page['errors'][] = \Phyxo\Functions\Language::l10n(
                'An error occured during extraction (%s).',
                htmlspecialchars($_GET['installstatus'])
            );
    }
}

// +-----------------------------------------------------------------------+
// |                          template output                              |
// +-----------------------------------------------------------------------+

foreach ($themes->getServerThemes(true) as $theme) {
    $url_auto_install = htmlentities(THEMES_BASE_URL)
        . '&amp;section=new'
        . '&amp;revision=' . $theme['revision_id']
        . '&amp;extension=' . $theme['extension_id']
        . '&amp;pwg_token=' . \Phyxo\Functions\Utils::get_token();

    $template->append(
        'new_themes',
        [
            'name' => $theme['extension_name'],
            'thumbnail' => PEM_URL . '/upload/extension-' . $theme['extension_id'] . '/thumbnail.jpg',
            'screenshot' => PEM_URL . '/upload/extension-' . $theme['extension_id'] . '/screenshot.jpg',
            'install_url' => $url_auto_install,
        ]
    );
}

$template->assign(
    'default_screenshot',
    \Phyxo\Functions\URL::get_root_url() . 'admin/theme/images/missing_screenshot.png'
);
