<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2016 Nicolas Roudaire         http://www.phyxo.net/ |
// +-----------------------------------------------------------------------+
// | Copyright(C) 2008-2014 Piwigo Team                  http://piwigo.org |
// | Copyright(C) 2003-2008 PhpWebGallery Team    http://phpwebgallery.net |
// | Copyright(C) 2002-2003 Pierrick LE GALL   http://le-gall.net/pierrick |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License as published by  |
// | the Free Software Foundation                                          |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, |
// | USA.                                                                  |
// +-----------------------------------------------------------------------+

if (!defined("LANGUAGES_BASE_URL")) {
    die ("Hacking attempt!");
}

use Phyxo\Language\Languages;

$languages = new Languages($conn);

// +-----------------------------------------------------------------------+
// |                           setup check                                 |
// +-----------------------------------------------------------------------+

$languages_dir = PHPWG_ROOT_PATH.'language';
if (!is_writable($languages_dir)) {
    $page['errors'][] = l10n('Add write access to the "%s" directory', 'language');
}

// +-----------------------------------------------------------------------+
// |                       perform installation                            |
// +-----------------------------------------------------------------------+

if (isset($_GET['revision'])) {
    if (!$services['users']->isWebmaster()) {
        $page['errors'][] = l10n('Webmaster status is required.');
    } else {
        check_pwg_token();

        try {
            $languages->extractLanguageFiles('install', $_GET['revision']);
            $install_status = 'ok';
        } catch (\Exception $e) {
            $page['errors'] = l10n($e->getMessage());
        }

        redirect(LANGUAGES_BASE_URL.'&section=new&installstatus='.$install_status);
    }
}

// +-----------------------------------------------------------------------+
// |                        installation result                            |
// +-----------------------------------------------------------------------+
if (isset($_GET['installstatus'])) {
    switch ($_GET['installstatus'])
    {
    case 'ok':
        $page['infos'][] = l10n('Language has been successfully installed');
        break;

    case 'temp_path_error':
        $page['errors'][] = l10n('Can\'t create temporary file.');
        break;

    case 'dl_archive_error':
        $page['errors'][] = l10n('Can\'t download archive.');
        break;

    case 'archive_error':
        $page['errors'][] = l10n('Can\'t read or extract archive.');
        break;

    default:
        $page['errors'][] = l10n('An error occured during extraction (%s).', htmlspecialchars($_GET['installstatus']));
    }
}

// +-----------------------------------------------------------------------+
// |                     start template output                             |
// +-----------------------------------------------------------------------+

foreach($languages->getServerLanguages(true) as $language) {
    list($date, ) = explode(' ', $language['revision_date']);

    $url_auto_install = LANGUAGES_BASE_URL.'&amp;section=new&amp;revision=' . $language['revision_id'].'&amp;pwg_token='.get_pwg_token();

    $template->append('languages', array(
        'EXT_NAME' => $language['extension_name'],
        'EXT_DESC' => $language['extension_description'],
        'EXT_URL' => PEM_URL.'/extension_view.php?eid='.$language['extension_id'],
        'VERSION' => $language['revision_name'],
        'VER_DESC' => $language['revision_description'],
        'DATE' => $date,
        'AUTHOR' => $language['author_name'],
        'URL_INSTALL' => $url_auto_install,
        'URL_DOWNLOAD' => $language['download_url'] . '&amp;origin=piwigo_download'));
}

$template->assign_var_from_handle('ADMIN_CONTENT', 'languages');
