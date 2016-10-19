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

if (!defined('ALBUMS_BASE_URL')) {
    die('Hacking attempt!');
}

// +-----------------------------------------------------------------------+
// |                          categories movement                          |
// +-----------------------------------------------------------------------+

if (isset($_POST['submit'])) {
    if (count($_POST['selection']) > 0) {
        // @TODO: tests
        move_categories($_POST['selection'], $_POST['parent']);
    } else {
        $page['errors'][] = l10n('Select at least one album');
    }
}

// +-----------------------------------------------------------------------+
// |                       template initialization                         |
// +-----------------------------------------------------------------------+

$template->assign(
    array(
        'U_HELP' => get_root_url().'admin/popuphelp.php?page=cat_move',
        'F_ACTION' => ALBUMS_BASE_URL.'&amp;section=move',
    )
);

// +-----------------------------------------------------------------------+
// |                          Categories display                           |
// +-----------------------------------------------------------------------+

$query = 'SELECT id,name,uppercats,global_rank FROM '.CATEGORIES_TABLE.' WHERE dir IS NULL;';
display_select_cat_wrapper(
    $query,
    array(),
    'category_to_move_options'
);

$query = 'SELECT id,name,uppercats,global_rank FROM '.CATEGORIES_TABLE;

display_select_cat_wrapper(
    $query,
    array(),
    'category_parent_options'
);

// +-----------------------------------------------------------------------+
// |                          sending html code                            |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('ADMIN_CONTENT', 'albums');
