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
    die("Hacking attempt!");
}

include_once(PHPWG_ROOT_PATH.'admin/include/functions.php');

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+
$services['users']->checkStatus(ACCESS_ADMINISTRATOR);


include_once(PHPWG_ROOT_PATH.'admin/include/tabsheet.class.php');
$tabsheet = new tabsheet();
$tabsheet->set_id('rating');
$tabsheet->select('rating');
$tabsheet->assign();

// +-----------------------------------------------------------------------+
// |                            initialization                             |
// +-----------------------------------------------------------------------+
if (isset($_GET['start']) and is_numeric($_GET['start'])) {
    $start = $_GET['start'];
} else {
    $start = 0;
}

$elements_per_page=10;
if (isset($_GET['display']) and is_numeric($_GET['display'])) {
    $elements_per_page = $_GET['display'];
}

$order_by_index=0;
if (isset($_GET['order_by']) and is_numeric($_GET['order_by'])) {
    $order_by_index = $_GET['order_by'];
}

$page['user_filter'] = '';
if (isset($_GET['users'])) {
    if ($_GET['users'] == 'user') {
        $page['user_filter'] = ' AND r.user_id <> '.$conf['guest_id'];
    } elseif ($_GET['users'] == 'guest') {
        $page['user_filter'] = ' AND r.user_id = '.$conf['guest_id'];
    }
}

$users = array();
$query = 'SELECT '.$conf['user_fields']['username'].' as username, '.$conf['user_fields']['id'].' as id FROM '.USERS_TABLE;
$result = $conn->db_query($query);
while ($row = $conn->db_fetch_assoc($result)) {
    $users[$row['id']] = stripslashes($row['username']); // @TODO: remove
}


$query = 'SELECT COUNT(DISTINCT(r.element_id)) FROM '.RATE_TABLE.' AS r';
$query .= ' WHERE 1=1'. $page['user_filter'];
list($nb_images) = $conn->db_fetch_row($conn->db_query($query));


// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template->set_filename('rating', 'rating.tpl');

$template->assign(
    array(
        'navbar' => create_navigation_bar(
            get_root_url().'admin/index.php'.get_query_string_diff(array('start','del')),
            $nb_images,
            $start,
            $elements_per_page
        ),
        'F_ACTION' => get_root_url().'admin/index.php',
        'DISPLAY' => $elements_per_page,
        'NB_ELEMENTS' => $nb_images,
    )
);



$available_order_by= array(
    array(l10n('Rate date'), 'recently_rated DESC'),
    array(l10n('Rating score'), 'score DESC'),
    array(l10n('Average rate'), 'avg_rates DESC'),
    array(l10n('Number of rates'), 'nb_rates DESC'),
    array(l10n('Sum of rates'), 'sum_rates DESC'),
    array(l10n('File name'), 'file DESC'),
    array(l10n('Creation date'), 'date_creation DESC'),
    array(l10n('Post date'), 'date_available DESC'),
);

for ($i=0; $i<count($available_order_by); $i++) {
    $template->append(
        'order_by_options',
        $available_order_by[$i][0]
    );
}
$template->assign('order_by_options_selected', array($order_by_index) );

$user_options = array(
    'all'   => l10n('all'),
    'user'  => l10n('Users'),
    'guest' => l10n('Guests'),
);

$template->assign('user_options', $user_options );
$template->assign('user_options_selected', array(@$_GET['users']) );

$query = 'SELECT i.id,i.path,i.file,i.representative_ext,i.rating_score AS score,';
$query .= 'MAX(r.date) AS recently_rated,ROUND(AVG(r.rate),2) AS avg_rates,';
$query .= 'COUNT(r.rate) AS nb_rates,SUM(r.rate) AS sum_rates FROM '.RATE_TABLE.' AS r';
$query .= ' LEFT JOIN '.IMAGES_TABLE.' AS i ON r.element_id = i.id';
$query .= ' WHERE 1 = 1 '.$page['user_filter'];
$query .= ' GROUP BY i.id,i.path,i.file,i.representative_ext,i.rating_score,r.element_id';
$query .= ' ORDER BY ' . $available_order_by[$order_by_index][1];
$query .= ' LIMIT '.$elements_per_page.' OFFSET '.$start.';';

$images = array();
$result = $conn->db_query($query);
while ($row = $conn->db_fetch_assoc($result)) {
    $images[] = $row;
}

$template->assign('images', array() );
foreach ($images as $image) {
    $thumbnail_src = DerivativeImage::thumb_url($image);

    $image_url = get_root_url().'admin/index.php?page=photo-'.$image['id'];

    $query = 'SELECT * FROM '.RATE_TABLE.' AS r';
    $query .= ' WHERE r.element_id='.$image['id']. ' ORDER BY date DESC;';
    $result = $conn->db_query($query);
    $nb_rates = $conn->db_num_rows($result);

    $tpl_image =
        array(
            'id' => $image['id'],
            'U_THUMB' => $thumbnail_src,
            'U_URL' => $image_url,
            'SCORE_RATE' => $image['score'],
            'AVG_RATE' => $image['avg_rates'],
            'SUM_RATE' => $image['sum_rates'],
            'NB_RATES' => (int)$image['nb_rates'],
            'NB_RATES_TOTAL' => (int)$nb_rates,
            'FILE' => $image['file'],
            'rates'  => array()
        );

    while ($row = $conn->db_fetch_assoc($result)) {
        if (isset($users[$row['user_id']])) {
            $user_rate = $users[$row['user_id']];
        } else {
            $user_rate = '? '. $row['user_id'];
        }
        if (strlen($row['anonymous_id'])>0) {
            $user_rate .= '('.$row['anonymous_id'].')';
        }

        $row['USER'] = $user_rate;
        $tpl_image['rates'][] = $row;
    }
    $template->append('images', $tpl_image);
}

// +-----------------------------------------------------------------------+
// |                           sending html code                           |
// +-----------------------------------------------------------------------+
$template->assign_var_from_handle('ADMIN_CONTENT', 'rating');
