<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2015 Nicolas Roudaire         http://www.phyxo.net/ |
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

$template->set_filenames(array('tail'=>'footer.tpl'));

trigger_notify('loc_begin_page_tail');

$template->assign(
    array(
        'VERSION' => $conf['show_version'] ? PHPWG_VERSION : '',
        'PHPWG_URL' => defined('PHPWG_URL') ? PHPWG_URL : '',
    )
);

//--------------------------------------------------------------------- contact

if (!$services['users']->isGuest()) {
    $template->assign(
        'CONTACT_MAIL', get_webmaster_mail_address()
    );
}

//------------------------------------------------------------- generation time
$debug_vars = array();

if ($conf['show_queries'] && !empty($conn)) {
    $debug_vars = array_merge(
        $debug_vars,
        array('QUERIES_LIST' => $conn->getQueries())
    );
}

if ($conf['show_gt']) {
    if (!isset($page['count_queries'])) {
        $page['count_queries'] = 0;
        $page['queries_time'] = 0;
    }
    $time = get_elapsed_time($t2, get_moment());

    if (!empty($conn)) {
        $debug_vars = array_merge(
            $debug_vars,
            array('TIME' => $time,
            'NB_QUERIES' => $conn->getQueriesCount(),
            'SQL_TIME' => number_format($conn->getQueriesTime(), 3, '.', ' ').' s')
        );
    }
}

$template->assign('debug', $debug_vars);

trigger_notify('loc_end_page_tail');

//
// Generate the page
//
$template->parse('tail');
$template->p();
