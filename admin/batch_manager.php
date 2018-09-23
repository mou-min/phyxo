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

/**
 * Management of elements set. Elements can belong to a category or to the
 * user caddie.
 *
 */

if (!defined('PHPWG_ROOT_PATH')) {
    die('Hacking attempt!');
}

define('BATCH_MANAGER_BASE_URL', \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=batch_manager');

use Phyxo\TabSheet\TabSheet;
use App\Repository\TagRepository;
use App\Repository\CategoryRepository;

// +-----------------------------------------------------------------------+
// | Check Access and exit when user status is not ok                      |
// +-----------------------------------------------------------------------+

$services['users']->checkStatus(ACCESS_ADMINISTRATOR);
\Phyxo\Functions\Utils::check_input_parameter('selection', $_POST, true, PATTERN_ID);


// +-----------------------------------------------------------------------+
// |                                 Tabs                                  |
// +-----------------------------------------------------------------------+

if (isset($_GET['section'])) {
    $page['section'] = $_GET['section'];
} else {
    $page['section'] = 'global';
}

$tabsheet = new TabSheet();
$tabsheet->add('global', \Phyxo\Functions\Language::l10n('global mode'), BATCH_MANAGER_BASE_URL . '&amp;section=global');
$tabsheet->add('unit', \Phyxo\Functions\Language::l10n('unit mode'), BATCH_MANAGER_BASE_URL . '&amp;section=unit');
$tabsheet->select($page['section']);

$template->assign([
    'tabsheet' => $tabsheet,
    'U_PAGE' => BATCH_MANAGER_BASE_URL,
]);

// +-----------------------------------------------------------------------+
// | specific actions                                                      |
// +-----------------------------------------------------------------------+

if (isset($_GET['action'])) {
    if ('empty_caddie' == $_GET['action']) {
        $query = 'DELETE FROM ' . CADDIE_TABLE . ' WHERE user_id = ' . $conn->db_real_escape_string($user['id']);
        $conn->db_query($query);

        $_SESSION['page_infos'][] = \Phyxo\Functions\Language::l10n('Information data registered in database');
        \Phyxo\Functions\Utils::redirect(\Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=' . $_GET['page']);
    }
}

// +-----------------------------------------------------------------------+
// |                      initialize current set                           |
// +-----------------------------------------------------------------------+

// filters from form
if (isset($_POST['submitFilter'])) {
    unset($_REQUEST['start']); // new photo set must reset the page
    $_SESSION['bulk_manager_filter'] = [];

    if (isset($_POST['filter_prefilter_use'])) {
        $_SESSION['bulk_manager_filter']['prefilter'] = $_POST['filter_prefilter'];

        if ('duplicates' == $_POST['filter_prefilter']) {
            if (isset($_POST['filter_duplicates_date'])) {
                $_SESSION['bulk_manager_filter']['duplicates_date'] = true;
            }

            if (isset($_POST['filter_duplicates_dimensions'])) {
                $_SESSION['bulk_manager_filter']['duplicates_dimensions'] = true;
            }
        }
    }

    if (isset($_POST['filter_category_use'])) {
        $_SESSION['bulk_manager_filter']['category'] = $_POST['filter_category'];

        if (isset($_POST['filter_category_recursive'])) {
            $_SESSION['bulk_manager_filter']['category_recursive'] = true;
        }
    }

    if (isset($_POST['filter_tags_use'])) {
        $_SESSION['bulk_manager_filter']['tags'] = $services['tags']->getTagsIds($_POST['filter_tags']);

        if (isset($_POST['tag_mode']) and in_array($_POST['tag_mode'], ['AND', 'OR'])) {
            $_SESSION['bulk_manager_filter']['tag_mode'] = $_POST['tag_mode'];
        }
    }

    if (isset($_POST['filter_level_use'])) {
        if (in_array($_POST['filter_level'], $conf['available_permission_levels'])) {
            $_SESSION['bulk_manager_filter']['level'] = $_POST['filter_level'];

            if (isset($_POST['filter_level_include_lower'])) {
                $_SESSION['bulk_manager_filter']['level_include_lower'] = true;
            }
        }
    }

    if (isset($_POST['filter_dimension_use'])) {
        foreach (['min_width', 'max_width', 'min_height', 'max_height'] as $type) {
            if (filter_var($_POST['filter_dimension_' . $type], FILTER_VALIDATE_INT) !== false) {
                $_SESSION['bulk_manager_filter']['dimension'][$type] = $_POST['filter_dimension_' . $type];
            }
        }
        foreach (['min_ratio', 'max_ratio'] as $type) {
            if (filter_var($_POST['filter_dimension_' . $type], FILTER_VALIDATE_FLOAT) !== false) {
                $_SESSION['bulk_manager_filter']['dimension'][$type] = $_POST['filter_dimension_' . $type];
            }
        }
    }

    if (isset($_POST['filter_filesize_use'])) {
        foreach (['min', 'max'] as $type) {
            if (filter_var($_POST['filter_filesize_' . $type], FILTER_VALIDATE_FLOAT) !== false) {
                $_SESSION['bulk_manager_filter']['filesize'][$type] = $_POST['filter_filesize_' . $type];
            }
        }
    }

    if (isset($_POST['filter_search_use'])) {
        $_SESSION['bulk_manager_filter']['search']['q'] = $_POST['q'];
    }

    $_SESSION['bulk_manager_filter'] = \Phyxo\Functions\Plugin::trigger_change('batch_manager_register_filters', $_SESSION['bulk_manager_filter']);
} elseif (isset($_GET['filter'])) { // filters from url
    if (!is_array($_GET['filter'])) {
        $_GET['filter'] = explode(',', $_GET['filter']);
    }

    $_SESSION['bulk_manager_filter'] = [];

    foreach ($_GET['filter'] as $filter) {
        list($type, $value) = explode('-', $filter, 2);

        switch ($type) {
            case 'prefilter':
                $_SESSION['bulk_manager_filter']['prefilter'] = $value;
                break;

            case 'album':
            case 'category':
            case 'cat':
                if (is_numeric($value)) {
                    $_SESSION['bulk_manager_filter']['category'] = $value;
                }
                break;

            case 'tag':
                if (is_numeric($value)) {
                    $_SESSION['bulk_manager_filter']['tags'] = [$value];
                    $_SESSION['bulk_manager_filter']['tag_mode'] = 'AND';
                }
                break;

            case 'level':
                if (is_numeric($value) && in_array($value, $conf['available_permission_levels'])) {
                    $_SESSION['bulk_manager_filter']['level'] = $value;
                }
                break;

            case 'search':
                $_SESSION['bulk_manager_filter']['search']['q'] = $value;
                break;

            case 'dimension':
                $dim_map = ['w' => 'width', 'h' => 'height', 'r' => 'ratio'];
                foreach (explode('-', $value) as $part) {
                    $values = explode('..', substr($part, 1));
                    if (isset($dim_map[$part[0]])) {
                        $type = $dim_map[$part[0]];
                        list(
                            $_SESSION['bulk_manager_filter']['dimension']['min_' . $type],
                            $_SESSION['bulk_manager_filter']['dimension']['max_' . $type]
                        ) = $values;
                    }
                }
                break;

            case 'filesize':
                list(
                    $_SESSION['bulk_manager_filter']['filesize']['min'],
                    $_SESSION['bulk_manager_filter']['filesize']['max']
                ) = explode('..', $value);
                break;

            default:
                $_SESSION['bulk_manager_filter'] = \Phyxo\Functions\Plugin::trigger_change('batch_manager_url_filter', $_SESSION['bulk_manager_filter'], $filter);
                break;
        }
    }
}

if (empty($_SESSION['bulk_manager_filter'])) {
    $_SESSION['bulk_manager_filter'] = ['prefilter' => 'caddie'];
}

// depending on the current filter (in session), we find the appropriate photos
$filter_sets = [];
if (isset($_SESSION['bulk_manager_filter']['prefilter'])) {
    switch ($_SESSION['bulk_manager_filter']['prefilter']) {
        case 'caddie':
            $query = 'SELECT element_id FROM ' . CADDIE_TABLE;
            $query .= ' WHERE user_id = ' . $conn->db_real_escape_string($user['id']);
            $filter_sets[] = $conn->query2array($query, null, 'element_id');
            break;

        case 'favorites':
            $query = 'SELECT image_id FROM ' . FAVORITES_TABLE;
            $query .= ' WHERE user_id = ' . $conn->db_real_escape_string($user['id']);
            $filter_sets[] = $conn->query2array($query, null, 'image_id');
            break;

        case 'last_import':
            $query = 'SELECT MAX(date_available) AS date FROM ' . IMAGES_TABLE;
            $row = $conn->db_fetch_assoc($conn->db_query($query));
            if (!empty($row['date'])) {
                $query = 'SELECT id FROM ' . IMAGES_TABLE;
                $query .= ' WHERE date_available BETWEEN ';
                $query .= $conn->db_get_recent_period_expression(1, $row['date']) . ' AND \'' . $row['date'] . '\';';
                $filter_sets[] = $conn->query2array($query, null, 'id');
            }
            break;

        case 'no_virtual_album':
        // we are searching elements not linked to any virtual category
            $query = 'SELECT id FROM ' . IMAGES_TABLE;
            $all_elements = $conn->query2array($query, null, 'id');

            $query = 'SELECT id FROM ' . CATEGORIES_TABLE . ' WHERE dir IS NULL';
            $virtual_categories = $conn->query2array($query, null, 'id');
            if (!empty($virtual_categories)) {
                $query = 'SELECT DISTINCT(image_id) FROM ' . IMAGE_CATEGORY_TABLE;
                $query .= ' WHERE category_id ' . $conn->in($virtual_categories);
                $linked_to_virtual = $conn->query2array($query, null, 'image_id');
            }

            $filter_sets[] = array_diff($all_elements, $linked_to_virtual);
            break;

        case 'no_album':
            $query = 'SELECT id FROM ' . IMAGES_TABLE;
            $query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' ON id = image_id';
            $query .= ' WHERE category_id is null';
            $filter_sets[] = $conn->query2array($query, null, 'id');
            break;

        case 'no_tag':
            $query = 'SELECT id FROM ' . IMAGES_TABLE;
            $query .= ' LEFT JOIN ' . IMAGE_TAG_TABLE . ' ON id = image_id';
            $query .= ' WHERE tag_id is null';
            $filter_sets[] = $conn->query2array($query, null, 'id');
            break;

        case 'duplicates':
            $duplicates_on_fields = ['file'];
            if (isset($_SESSION['bulk_manager_filter']['duplicates_date'])) {
                $duplicates_on_fields[] = 'date_creation';
            }

            if (isset($_SESSION['bulk_manager_filter']['duplicates_dimensions'])) {
                $duplicates_on_fields[] = 'width';
                $duplicates_on_fields[] = 'height';
            }

            $query = 'SELECT ' . $conn->db_group_concat('id') . ' AS ids FROM ' . IMAGES_TABLE;
            $query .= ' GROUP BY ' . implode(',', $duplicates_on_fields);
            $query .= ' HAVING COUNT(*) > 1;';
            $array_of_ids_string = $conn->query2array($query, null, 'ids');

            $ids = [];

            foreach ($array_of_ids_string as $ids_string) {
                $ids = array_merge($ids, explode(',', $ids_string));
            }

            $filter_sets[] = $ids;
            break;

        case 'all_photos':
            if (count($_SESSION['bulk_manager_filter']) == 1) { // make the query only if this is the only filter
                $query = 'SELECT id FROM ' . IMAGES_TABLE;
                $query .= ' ' . $conf['order_by'];
                $filter_sets[] = $conn->query2array($query, null, 'id');
            }
            break;

        default:
            $filter_sets = \Phyxo\Functions\Plugin::trigger_change(
                'perform_batch_manager_prefilters',
                $filter_sets,
                $_SESSION['bulk_manager_filter']['prefilter']
            );
            break;
    }
}

if (isset($_SESSION['bulk_manager_filter']['category'])) {
    $categories = [];

    if (isset($_SESSION['bulk_manager_filter']['category_recursive'])) {
        $categories = (new CategoryRepository($conn))->getSubcatIds([$_SESSION['bulk_manager_filter']['category']]);
    } else {
        $categories = [$_SESSION['bulk_manager_filter']['category']];
    }

    $query = 'SELECT DISTINCT(image_id) FROM ' . IMAGE_CATEGORY_TABLE;
    $query .= ' WHERE category_id ' . $conn->in($categories);
    $filter_sets[] = $conn->query2array($query, null, 'image_id');
}

if (isset($_SESSION['bulk_manager_filter']['level'])) {
    $operator = '=';
    if (isset($_SESSION['bulk_manager_filter']['level_include_lower'])) {
        $operator = '<=';
    }

    $query = 'SELECT id FROM ' . IMAGES_TABLE;
    $query .= ' WHERE level ' . $operator . ' ' . $_SESSION['bulk_manager_filter']['level'];
    $query .= ' ' . $conf['order_by'];

    $filter_sets[] = $conn->query2array($query, null, 'id');
}

if (!empty($_SESSION['bulk_manager_filter']['tags'])) {
    $filter_sets[] = $conn->result2array(
        (new TagRepository($conn))->getImageIdsForTags(
            $_SESSION['bulk_manager_filter']['tags'],
            $_SESSION['bulk_manager_filter']['tag_mode'],
            null,
            $conf['order_by'],
            false // we don't apply permissions in administration screens
        ),
        null,
        'id'
    );
}

if (isset($_SESSION['bulk_manager_filter']['dimension'])) {
    $where_clauses = [];
    if (isset($_SESSION['bulk_manager_filter']['dimension']['min_width'])) {
        $where_clause[] = 'width >= ' . $_SESSION['bulk_manager_filter']['dimension']['min_width'];
    }
    if (isset($_SESSION['bulk_manager_filter']['dimension']['max_width'])) {
        $where_clause[] = 'width <= ' . $_SESSION['bulk_manager_filter']['dimension']['max_width'];
    }
    if (isset($_SESSION['bulk_manager_filter']['dimension']['min_height'])) {
        $where_clause[] = 'height >= ' . $_SESSION['bulk_manager_filter']['dimension']['min_height'];
    }
    if (isset($_SESSION['bulk_manager_filter']['dimension']['max_height'])) {
        $where_clause[] = 'height <= ' . $_SESSION['bulk_manager_filter']['dimension']['max_height'];
    }
    if (isset($_SESSION['bulk_manager_filter']['dimension']['min_ratio'])) {
        $where_clause[] = 'width/height >= ' . $_SESSION['bulk_manager_filter']['dimension']['min_ratio'];
    }
    if (isset($_SESSION['bulk_manager_filter']['dimension']['max_ratio'])) {
        // max_ratio is a floor value, so must be a bit increased
        $where_clause[] = 'width/height < ' . ($_SESSION['bulk_manager_filter']['dimension']['max_ratio'] + 0.01);
    }

    $query = 'SELECT id FROM ' . IMAGES_TABLE;
    $query .= ' WHERE ' . implode(' AND ', $where_clause);
    $query .= ' ' . $conf['order_by'];

    $filter_sets[] = $conn->query2array($query, null, 'id');
}

if (isset($_SESSION['bulk_manager_filter']['filesize'])) {
    $where_clauses = [];

    if (isset($_SESSION['bulk_manager_filter']['filesize']['min'])) {
        $where_clause[] = 'filesize >= ' . $_SESSION['bulk_manager_filter']['filesize']['min'] * 1024;
    }

    if (isset($_SESSION['bulk_manager_filter']['filesize']['max'])) {
        $where_clause[] = 'filesize <= ' . $_SESSION['bulk_manager_filter']['filesize']['max'] * 1024;
    }

    $query = 'SELECT id FROM ' . IMAGES_TABLE;
    $query .= ' WHERE ' . implode(' AND ', $where_clause);
    $query .= ' ' . $conf['order_by'];

    $filter_sets[] = $conn->query2array($query, null, 'id');
}

if (isset($_SESSION['bulk_manager_filter']['search']) && strlen($_SESSION['bulk_manager_filter']['search']['q'])) {
    $res = \Phyxo\Functions\Search::get_quick_search_results_no_cache($_SESSION['bulk_manager_filter']['search']['q'], ['permissions' => false]);
    if (!empty($res['items']) && !empty($res['qs']['unmatched_terms'])) {
        $template->assign('no_search_results', $res['qs']['unmatched_terms']);
    }
    $filter_sets[] = $res['items'];
}

$filter_sets = \Phyxo\Functions\Plugin::trigger_change('batch_manager_perform_filters', $filter_sets, $_SESSION['bulk_manager_filter']);

$current_set = array_shift($filter_sets);
if (empty($current_set)) {
    $current_set = [];
}
foreach ($filter_sets as $set) {
    $current_set = array_intersect($current_set, $set);
}
$page['cat_elements_id'] = $current_set;


// +-----------------------------------------------------------------------+
// |                       first element to display                        |
// +-----------------------------------------------------------------------+

// $page['start'] contains the number of the first element in its
// category. For exampe, $page['start'] = 12 means we must show elements #12
// and $page['nb_images'] next elements

if (!isset($_REQUEST['start']) or !is_numeric($_REQUEST['start'])
    or $_REQUEST['start'] < 0 or (isset($_REQUEST['display']) and 'all' == $_REQUEST['display'])) {
    $page['start'] = 0;
} else {
    $page['start'] = $_REQUEST['start'];
}




// +-----------------------------------------------------------------------+
// |                              dimensions                               |
// +-----------------------------------------------------------------------+

$widths = [];
$heights = [];
$ratios = [];
$dimensions = [];

// get all width, height and ratios
$query = 'SELECT DISTINCT width, height FROM ' . IMAGES_TABLE;
$query .= ' WHERE width IS NOT NULL AND height IS NOT NULL';
$result = $conn->db_query($query);

if ($conn->db_num_rows($result)) {
    while ($row = $conn->db_fetch_assoc($result)) {
        if ($row['width'] > 0 && $row['height'] > 0) {
            $widths[] = $row['width'];
            $heights[] = $row['height'];
            $ratios[] = floor($row['width'] / $row['height'] * 100) / 100;
        }
    }
}
if (empty($widths)) { // arbitrary values, only used when no photos on the gallery
    $widths = [600, 1920, 3500];
    $heights = [480, 1080, 2300];
    $ratios = [1.25, 1.52, 1.78];
}

foreach (['widths', 'heights', 'ratios'] as $type) {
    ${$type} = array_unique(${$type}); // @TODO : remove that ~ eval
    sort(${$type});
    $dimensions[$type] = implode(',', ${$type});
}

$dimensions['bounds'] = [
    'min_width' => $widths[0],
    'max_width' => end($widths),
    'min_height' => $heights[0],
    'max_height' => end($heights),
    'min_ratio' => $ratios[0],
    'max_ratio' => end($ratios),
];

// find ratio categories
$ratio_categories = [
    'portrait' => [],
    'square' => [],
    'landscape' => [],
    'panorama' => [],
];

foreach ($ratios as $ratio) {
    if ($ratio < 0.95) {
        $ratio_categories['portrait'][] = $ratio;
    } elseif ($ratio >= 0.95 and $ratio <= 1.05) {
        $ratio_categories['square'][] = $ratio;
    } elseif ($ratio > 1.05 and $ratio < 2) {
        $ratio_categories['landscape'][] = $ratio;
    } elseif ($ratio >= 2) {
        $ratio_categories['panorama'][] = $ratio;
    }
}

foreach (array_keys($ratio_categories) as $type) {
    if (count($ratio_categories[$type]) > 0) {
        $dimensions['ratio_' . $type] = [
            'min' => $ratio_categories[$type][0],
            'max' => end($ratio_categories[$type]),
        ];
    }
}

// selected=bound if nothing selected
foreach (array_keys($dimensions['bounds']) as $type) {
    $dimensions['selected'][$type] = isset($_SESSION['bulk_manager_filter']['dimension'][$type])
        ? $_SESSION['bulk_manager_filter']['dimension'][$type]
        : $dimensions['bounds'][$type];
}

$template->assign('dimensions', $dimensions);

// +-----------------------------------------------------------------------+
// | filesize                                                              |
// +-----------------------------------------------------------------------+

$filesizes = [];
$filesize = [];

$query = 'SELECT filesize FROM ' . IMAGES_TABLE;
$query .= ' WHERE filesize IS NOT NULL GROUP BY filesize';
$result = $conn->db_query($query);

while ($row = $conn->db_fetch_assoc($result)) {
    $filesizes[] = sprintf('%.1f', $row['filesize'] / 1024);
}

if (empty($filesizes)) { // arbitrary values, only used when no photos on the gallery
    $filesizes = [0, 1, 2, 5, 8, 15];
}

$filesizes = array_unique($filesizes);
sort($filesizes);

// add 0.1MB to the last value, to make sure the heavier photo will be in
// the result
$filesizes[count($filesizes) - 1] += 0.1;

$filesize['list'] = implode(',', $filesizes);

$filesize['bounds'] = [
    'min' => $filesizes[0],
    'max' => end($filesizes),
];

// selected=bound if nothing selected
foreach (array_keys($filesize['bounds']) as $type) {
    $filesize['selected'][$type] = isset($_SESSION['bulk_manager_filter']['filesize'][$type])
        ? $_SESSION['bulk_manager_filter']['filesize'][$type]
        : $filesize['bounds'][$type];
}

$template->assign('filesize', $filesize);

// +-----------------------------------------------------------------------+
// |                             template init                             |
// +-----------------------------------------------------------------------+

$template_filename = 'batch_manager_' . $page['section'];

include(PHPWG_ROOT_PATH . 'admin/batch_manager_' . $page['section'] . '.php');
