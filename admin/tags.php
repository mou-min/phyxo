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

if (!defined('PHPWG_ROOT_PATH')) {
    die ('Hacking attempt!');
}

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');

define('TAGS_BASE_URL', get_root_url().'admin/index.php?page=tags');

use Phyxo\TabSheet\TabSheet;

$services['users']->checkStatus(ACCESS_ADMINISTRATOR);

if (!empty($_POST)) {
    check_pwg_token();
}

if (!empty($_GET['section'])) {
    $page['section'] = $_GET['section'];
} else {
    $page['section'] = 'all';
}

$tpl_tags = 'tags_'.$page['section'].'.tpl';

$tabsheet = new TabSheet();
$tabsheet->setId('tags');
$tabsheet->select($page['section']);
$tabsheet->assign($template);

include_once(PHPWG_ROOT_PATH.'admin/tags_'.$page['section'].'.php');

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template->set_filenames(array('tags' => $tpl_tags));

$template->assign(
    array(
        'F_ACTION' => get_root_url().'admin/index.php?page=tags&amp;section='.$page['section'],
        'PWG_TOKEN' => get_pwg_token(),
    )
);

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+

$template->assign_var_from_handle('ADMIN_CONTENT', 'tags');
