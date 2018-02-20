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

if (!defined('PHPWG_ROOT_PATH')) {
    die ("Hacking attempt!");
}

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');

define('ALBUMS_OPTIONS_BASE_URL', get_root_url().'admin/index.php?page=albums_options');

use Phyxo\TabSheet\TabSheet;

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

// +-----------------------------------------------------------------------+
// |                                 Tabs                                  |
// +-----------------------------------------------------------------------+
if (isset($_GET['section'])) {
    $page['section'] = $_GET['section'];
} else {
    $page['section'] = 'status';
}

$tabsheet = new TabSheet();
$tabsheet->setId('albums_options');
$tabsheet->select($page['section']);
$tabsheet->assign($template);

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template_filename = 'albums_options';

// +-----------------------------------------------------------------------+
// |                       modification registration                       |
// +-----------------------------------------------------------------------+

if (isset($_POST['falsify']) && isset($_POST['cat_true']) && count($_POST['cat_true']) > 0) {
    switch ($_GET['section']) {
    case 'comments': {
        $query = 'UPDATE '.CATEGORIES_TABLE;
        $query .= ' SET commentable = \'false\'';
        $query .= ' WHERE id '.$conn->in($_POST['cat_true']);
        $conn->db_query($query);
        break;
    }
    case 'visible': {
        set_cat_visible($_POST['cat_true'], 'false');
        break;
    }
    case 'status': {
        set_cat_status($_POST['cat_true'], 'private');
        break;
    }
    case 'representative': {
        $query = 'UPDATE '.CATEGORIES_TABLE;
        $query .= ' SET representative_picture_id = NULL';
        $query .= ' WHERE id '.$conn->in($_POST['cat_true']);
        $conn->db_query($query);
      break;
    }
    }
} elseif (isset($_POST['trueify']) && isset($_POST['cat_false']) && count($_POST['cat_false']) > 0) {
    switch ($_GET['section']) {
    case 'comments': {
        $query = 'UPDATE '.CATEGORIES_TABLE;
        $query .= ' SET commentable = \''.$conn->boolean_to_db(true).'\'';
        $query .= ' WHERE id '.$conn->in($_POST['cat_false']);
        $conn->db_query($query);
        break;
    }
    case 'visible': {
        set_cat_visible($_POST['cat_false'], 'true');
        break;
    }
    case 'status': {
        set_cat_status($_POST['cat_false'], 'public');
        break;
    }
    case 'representative': {
        // theoretically, all categories in $_POST['cat_false'] contain at
        // least one element, so Phyxo can find a representant.
        set_random_representant($_POST['cat_false']);
        break;
    }
    }
}

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template->set_filenames(
    array(
        'cat_options' => 'cat_options.tpl',
        'double_select' => 'double_select.tpl'
    )
);

$template->assign(
    array(
        //'U_HELP' => get_root_url().'admin/popuphelp.php?page=cat_options',
        'F_ACTION' => ALBUMS_OPTIONS_BASE_URL.'&amp;section='.$page['section']
    )
);

// +-----------------------------------------------------------------------+
// |                              form display                             |
// +-----------------------------------------------------------------------+

// for each section, categories in the multiselect field can be :
//
// - true : commentable for comment section
// - false : un-commentable for comment section
// - NA : (not applicable) for virtual categories
//
// for true and false status, we associates an array of category ids,
// function display_select_categories will use the given CSS class for each
// option
$cats_true = array();
$cats_false = array();
switch ($page['section']) {
case 'comments': {
    $query_true = 'SELECT id,name,uppercats,global_rank FROM '.CATEGORIES_TABLE;
    $query_true .= ' WHERE commentable = \''.$conn->boolean_to_db(true).'\'';
    $query_false = 'SELECT id,name,uppercats,global_rank FROM '.CATEGORIES_TABLE;
    $query_false .= ' WHERE commentable = \''.$conn->boolean_to_db(false).'\'';
    $template->assign(
        array(
            'L_SECTION' => l10n('Authorize users to add comments on selected albums'),
            'L_CAT_OPTIONS_TRUE' => l10n('Authorized'),
            'L_CAT_OPTIONS_FALSE' => l10n('Forbidden'),
        )
    );
    break;
}
case 'visible': {
    $query_true = 'SELECT id,name,uppercats,global_rank FROM '.CATEGORIES_TABLE;
    $query_true .= ' WHERE visible = \''.$conn->boolean_to_db(true).'\'';
    $query_false = 'SELECT id,name,uppercats,global_rank FROM '.CATEGORIES_TABLE;
    $query_false .= ' WHERE visible = \''.$conn->boolean_to_db(false).'\'';
    $template->assign(
        array(
            'L_SECTION' => l10n('Lock albums'),
            'L_CAT_OPTIONS_TRUE' => l10n('Unlocked'),
            'L_CAT_OPTIONS_FALSE' => l10n('Locked'),
        )
    );
    break;
}
case 'status': {
    $query_true = 'SELECT id,name,uppercats,global_rank FROM '.CATEGORIES_TABLE.' WHERE status = \'public\';';
    $query_false = 'SELECT id,name,uppercats,global_rank FROM '.CATEGORIES_TABLE.' WHERE status = \'private\';';
    $template->assign(
        array(
            'L_SECTION' => l10n('Manage authorizations for selected albums'),
            'L_CAT_OPTIONS_TRUE' => l10n('Public'),
            'L_CAT_OPTIONS_FALSE' => l10n('Private'),
        )
    );
    break;
}
case 'representative': {
    $query_true = 'SELECT id,name,uppercats,global_rank FROM '.CATEGORIES_TABLE.' WHERE representative_picture_id IS NOT NULL;';
    $query_false = 'SELECT DISTINCT id,name,uppercats,global_rank FROM '.CATEGORIES_TABLE;
    $query_false .= ' LEFT JOIN '.IMAGE_CATEGORY_TABLE.' ON id=category_id WHERE representative_picture_id IS NULL;';
    $template->assign(
        array(
            'L_SECTION' => l10n('Representative'),
            'L_CAT_OPTIONS_TRUE' => l10n('singly represented'),
            'L_CAT_OPTIONS_FALSE' => l10n('randomly represented')
        )
    );
    break;
}
}
display_select_cat_wrapper($query_true,array(),'category_option_true');
display_select_cat_wrapper($query_false,array(),'category_option_false');

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('DOUBLE_SELECT', 'double_select');
