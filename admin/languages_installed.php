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

//--------------------------------------------------perform requested actions
if (isset($_GET['action']) and isset($_GET['language'])) {
    $page['errors'] = $languages->performAction($_GET['action'], $_GET['language']);

    if (empty($page['errors'])) {
        redirect(LANGUAGES_BASE_URL.'&section=installed');
    }
}

// +-----------------------------------------------------------------------+
// |                     start template output                             |
// +-----------------------------------------------------------------------+
$default_language = $services['users']->getDefaultLanguage();

$tpl_languages = array();

foreach($languages->getFsLanguages() as $language_id => $language) {
    $language['u_action'] = add_url_params(LANGUAGES_BASE_URL.'&amp;section=installed', array('language' => $language_id));

    if (in_array($language_id, array_keys($languages->getDbLanguages()))) {
        $language['state'] = 'active';
        $language['deactivable'] = true;

        if (count($languages->getDbLanguages()) <= 1) {
            $language['deactivable'] = false;
            $language['deactivate_tooltip'] = l10n('Impossible to deactivate this language, you need at least one language.');
        }

        if ($language_id == $default_language) {
            $language['deactivable'] = false;
            $language['deactivate_tooltip'] = l10n('Impossible to deactivate this language, first set another language as default.');
        }
    } else {
        $language['state'] = 'inactive';
    }

    if ($language_id == $default_language) {
        $language['is_default'] = true;
        array_unshift($tpl_languages, $language);
    } else {
        $language['is_default'] = false;
        $tpl_languages[] = $language;
    }
}

$template->assign(array('languages' => $tpl_languages));
$template->append('language_states', 'active');
$template->append('language_states', 'inactive');

$missing_language_ids = array_diff(
    array_keys($languages->getDbLanguages()),
    array_keys($languages->getFsLanguages())
);

foreach($missing_language_ids as $language_id) {
    $query = 'UPDATE '.USER_INFOS_TABLE.' SET language = \''.$services['users']->getDefaultLanguage().'\'';
    $query .= ' WHERE language = \''.$language_id.'\';';
    $conn->db_query($query);

    $query = 'DELETE FROM '.LANGUAGES_TABLE.' WHERE id= \''.$language_id.'\';';
    $conn->db_query($query);
}

$template->assign_var_from_handle('ADMIN_CONTENT', 'languages');
