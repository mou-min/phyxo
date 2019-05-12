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

use App\Repository\CommentRepository;
use App\Repository\FavoriteRepository;
use App\Repository\CategoryRepository;
use App\Repository\ImageCategoryRepository;
use App\Repository\ImageRepository;
use App\Repository\CaddieRepository;
use Phyxo\Image\ImageStdParams;

include_once(__DIR__ . '/../../include/common.inc.php');
include_once(__DIR__ . '/../../include/section_init.inc.php');

// access authorization check
if (isset($page['category'])) {
    \Phyxo\Functions\Utils::check_restrictions($page['category']['id']);
}

if (!empty($_GET['display'])) {
    if (array_key_exists($_GET['display'], \Phyxo\Image\ImageStdParams::get_defined_type_map())) {
        $_SESSION['picture_deriv'] = $_GET['display'];
    }
}

// direct call
if (isset($_GET['level'])) {
    if (in_array($_GET['level'], $conf['available_permission_levels'])) {
        (new ImageRepository($conn))->updateImage(['level' => $_GET['level']], $page['image_id']);
        \Phyxo\Functions\Utils::redirect(\Phyxo\Functions\URL::make_picture_url(['image_id' => $page['image_id']]));
    }
}

$page['rank_of'] = array_flip($page['items']);

// if this image_id doesn't correspond to this category, an error message is
// displayed, and execution is stopped
if (!isset($page['rank_of'][$page['image_id']])) {
    if ($page['image_id'] > 0) {
        $result = (new ImageRepository($conn))->findById($app_user, $filter, $page['image_id']);
    } else { // url given by file name
        $result = (new ImageRepository($conn))->searchByField('file', str_replace(['_', '%'], ['/_', '/%'], $page['image_file']) . '.%');
    }
    if (!($row = $conn->db_fetch_assoc($result))) { // element does not exist
        \Phyxo\Functions\HTTP::page_not_found(
            'The requested image does not exist',
            \Phyxo\Functions\URL::duplicate_index_url()
        );
    }
    if ($row['level'] > $user['level']) {
        \Phyxo\Functions\HTTP::access_denied();
    }

    $page['image_id'] = $row['id'];
    $page['image_file'] = $row['file'];
    if (!isset($page['rank_of'][$page['image_id']])) { // the image can still be non accessible (filter/cat perm) and/or not in the set
        if (!empty($filter['visible_images']) and !in_array($page['image_id'], explode(',', $filter['visible_images']))) {
            \Phyxo\Functions\HTTP::page_not_found(
                'The requested image is filtered',
                \Phyxo\Functions\URL::duplicate_index_url()
            );
        }
        if ('categories' == $page['section'] and !isset($page['category'])) { // flat view - all items
            \Phyxo\Functions\HTTP::access_denied();
        } else { // try to see if we can access it differently
            if (!(new ImageRepository($conn))->isImageAuthorized($app_user, $filter, $page['image_id'])) {
                \Phyxo\Functions\HTTP::access_denied();
            } else {
                if ('best_rated' == $page['section']) {
                    $page['rank_of'][$page['image_id']] = count($page['items']);
                    $page['items'][] = $page['image_id'];
                } else {
                    $url = \Phyxo\Functions\URL::make_picture_url(
                        [
                            'image_id' => $page['image_id'],
                            'image_file' => $page['image_file'],
                            'section' => 'categories',
                            'flat' => true,
                        ]
                    );
                    \Phyxo\Functions\HTTP::set_status_header('recent_pics' == $page['section'] ? 301 : 302);
                    \Phyxo\Functions\Utils::redirect($url);
                }
            }
        }
    }
}

// There is cookie, so we must handle it at the beginning
if (isset($_GET['metadata'])) {
    if (empty($_SESSION['show_metadata'])) {
        $_SESSION['show_metadata'] = 1;
    } else {
        unset($_SESSION['show_metadata']);
    }
}

// add default event handler for rendering element content
\Phyxo\Functions\Plugin::add_event_handler('render_element_content', 'default_picture_content');
// add default event handler for rendering element description
\Phyxo\Functions\Plugin::add_event_handler('render_element_description', 'nl2br');

\Phyxo\Functions\Plugin::trigger_notify('loc_begin_picture');

// this is the default handler that generates the display for the element
function default_picture_content($content, $element_info)
{
    global $conf, $page, $template;

    if (!empty($content)) { // someone hooked us - so we skip;
        return $content;
    }

    if (isset($_COOKIE['picture_deriv'])) {
        if (array_key_exists($_COOKIE['picture_deriv'], \Phyxo\Image\ImageStdParams::get_defined_type_map())) {
            $_SESSION['picture_deriv'] = $_COOKIE['picture_deriv'];
        }
        setcookie('picture_deriv', false, 0, \Phyxo\Functions\Utils::cookie_path());
    }
    $deriv_type = isset($_SESSION['picture_deriv']) ? $_SESSION['picture_deriv'] : $conf['derivative_default_size'];
    $selected_derivative = $element_info['derivatives'][$deriv_type];

    $unique_derivatives = [];
    $show_original = isset($element_info['element_url']);
    $added = [];
    foreach ($element_info['derivatives'] as $type => $derivative) {
        if ($type == ImageStdParams::IMG_SQUARE || $type == ImageStdParams::IMG_THUMB) {
            continue;
        }
        if (!array_key_exists($type, \Phyxo\Image\ImageStdParams::get_defined_type_map())) {
            continue;
        }
        $url = $derivative->get_url();
        if (isset($added[$url])) {
            continue;
        }
        $added[$url] = 1;
        $show_original &= !($derivative->same_as_source());
        $unique_derivatives[$type] = $derivative;
    }

    if ($show_original) {
        $template->assign('U_ORIGINAL', $element_info['element_url']);
    }

    $template->append('current', [
        'selected_derivative' => $selected_derivative,
        'unique_derivatives' => $unique_derivatives,
    ], true);

    $template->set_filenames(
        ['default_content' => 'picture_content.tpl']
    );

    $template->assign([
        'ALT_IMG' => $element_info['file'],
    ]);

    return $template->parse('default_content', true);
}

// +-----------------------------------------------------------------------+
// |                            initialization                             |
// +-----------------------------------------------------------------------+

// caching first_rank, last_rank, current_rank in the displayed
// section. This should also help in readability.
$page['first_rank'] = 0;
$page['last_rank'] = count($page['items']) - 1;
$page['current_rank'] = $page['rank_of'][$page['image_id']];

// caching current item : readability purpose
$page['current_item'] = $page['image_id'];

if ($page['current_rank'] != $page['first_rank']) {
    // caching first & previous item : readability purpose
    $page['previous_item'] = $page['items'][$page['current_rank'] - 1];
    $page['first_item'] = $page['items'][$page['first_rank']];
}

if ($page['current_rank'] != $page['last_rank']) {
    // caching next & last item : readability purpose
    $page['next_item'] = $page['items'][$page['current_rank'] + 1];
    $page['last_item'] = $page['items'][$page['last_rank']];
}

$url_up = \Phyxo\Functions\URL::duplicate_index_url(
    ['start' => floor($page['current_rank'] / $page['nb_image_page']) * $page['nb_image_page']],
    ['start']
);

$url_self = \Phyxo\Functions\URL::duplicate_picture_url();

// +-----------------------------------------------------------------------+
// |                                actions                                |
// +-----------------------------------------------------------------------+

/**
 * Actions are favorite adding, user comment deletion, setting the picture
 * as representative of the current category...
 *
 * Actions finish by a redirection
 */

if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'add_to_favorites':
            {
                (new FavoriteRepository($conn))->addFavorite($user['id'], $page['image_id']);
                \Phyxo\Functions\Utils::redirect($url_self);
                break;
            }
        case 'remove_from_favorites':
            {
                (new FavoriteRepository($conn))->deleteFavorite($user['id'], $page['image_id']);
                if ('favorites' == $page['section']) {
                    \Phyxo\Functions\Utils::redirect($url_up);
                } else {
                    \Phyxo\Functions\Utils::redirect($url_self);
                }
                break;
            }
        case 'set_as_representative':
            {
                if ($userMapper->isAdmin() and isset($page['category'])) {
                    (new CategoryRepository($conn))->updateCategory(['representative_picture_id' => $page['image_id']], $page['category']['id']);
                    $userMapper->invalidateUserCache();
                }
                \Phyxo\Functions\Utils::redirect($url_self);
                break;
            }
        case 'add_to_caddie':
            {
                (new CaddieRepository($conn))->fillCaddie($user['id'], [$page['image_id']]);
                \Phyxo\Functions\Utils::redirect($url_self);
                break;
            }
        case 'rate':
            {
                $rateMapper->ratePicture($page['image_id'], $_POST['rate']);
                \Phyxo\Functions\Utils::redirect($url_self);
            }
        case 'edit_comment':
            {
                \Phyxo\Functions\Utils::check_input_parameter('comment_to_edit', $_GET, false, PATTERN_ID);
                $author_id = (new CommentRepository($conn))->getCommentAuthorId($_GET['comment_to_edit']);

                if ($userMapper->canManageComment('edit', $author_id)) {
                    if (!empty($_POST['content'])) {
                        \Phyxo\Functions\Utils::check_token();
                        $comment_action = $commentMapper->updateUserComment(
                            [
                                'comment_id' => $_GET['comment_to_edit'],
                                'image_id' => $page['image_id'],
                                'content' => $_POST['content'],
                                'website_url' => @$_POST['website_url'],
                            ],
                            $_POST['key']
                        );

                        $perform_redirect = false;
                        switch ($comment_action) {
                            case 'moderate':
                                $_SESSION['page_infos'][] = \Phyxo\Functions\Language::l10n('An administrator must authorize your comment before it is visible.');
                            case 'validate':
                                $_SESSION['page_infos'][] = \Phyxo\Functions\Language::l10n('Your comment has been registered');
                                $perform_redirect = true;
                                break;
                            case 'reject':
                                $_SESSION['page_errors'][] = \Phyxo\Functions\Language::l10n('Your comment has NOT been registered because it did not pass the validation rules');
                                break;
                            default:
                                trigger_error('Invalid comment action ' . $comment_action, E_USER_WARNING);
                        }

                        if ($perform_redirect) {
                            \Phyxo\Functions\Utils::redirect($url_self);
                        }
                        unset($_POST['content']);
                    }

                    $edit_comment = $_GET['comment_to_edit'];
                }
                break;
            }
        case 'delete_comment':
            {
                \Phyxo\Functions\Utils::check_token();
                \Phyxo\Functions\Utils::check_input_parameter('comment_to_delete', $_GET, false, PATTERN_ID);
                $author_id = (new CommentRepository($conn))->getCommentAuthorId($_GET['comment_to_delete']);

                if ($userMapper->canManageComment('delete', $author_id)) {
                    $commentMapper->deleteUserComment($_GET['comment_to_delete']);
                }

                \Phyxo\Functions\Utils::redirect($url_self);
            }
        case 'validate_comment':
            {
                \Phyxo\Functions\Utils::check_token();
                \Phyxo\Functions\Utils::check_input_parameter('comment_to_validate', $_GET, false, PATTERN_ID);
                $author_id = (new CommentRepository($conn))->getCommentAuthorId($_GET['comment_to_validate']);

                if ($userMapper->canManageComment('validate', $author_id)) {
                    $commentMapper->validateUserComment($_GET['comment_to_validate']);
                }
                \Phyxo\Functions\Utils::redirect($url_self);
            }
    }
}


//---------- incrementation of the number of hits
$inc_hit_count = !isset($_POST['content']);
// don't increment counter if in the Mozilla Firefox prefetch
if (isset($_SERVER['HTTP_X_MOZ']) and $_SERVER['HTTP_X_MOZ'] == 'prefetch') {
    $inc_hit_count = false;
} else {
    // don't increment counter if comming from the same picture (actions)
    if (!empty($_SESSION['referer_image_id']) && $_SESSION['referer_image_id'] == $page['image_id']) {
        $inc_hit_count = false;
    }
    $_SESSION['referer_image_id'] = $page['image_id'];
}

// don't increment if adding a comment
if (\Phyxo\Functions\Plugin::trigger_change('allow_increment_element_hit_count', $inc_hit_count, $page['image_id'])) {
    // avoiding auto update of "lastmodified" field
    (new ImageRepository($conn))->updateImageHitAndLastModified($page['image_id']);
}

//---------------------------------------------------------- related categories
$result = (new ImageCategoryRepository($conn))->getRelatedCategory($app_user, $filter, $page['image_id']);
$related_categories = $conn->result2array($result);
usort($related_categories, '\Phyxo\Functions\Utils::global_rank_compare');
//-------------------------first, prev, current, next & last picture management
$picture = [];

$ids = [$page['image_id']];
if (isset($page['previous_item'])) {
    $ids[] = $page['previous_item'];
    $ids[] = $page['first_item'];
}
if (isset($page['next_item'])) {
    $ids[] = $page['next_item'];
    $ids[] = $page['last_item'];
}

$result = (new ImageRepository($conn))->findByIds($ids);
while ($row = $conn->db_fetch_assoc($result)) {
    if (isset($page['previous_item']) and $row['id'] == $page['previous_item']) {
        $i = 'previous';
    } elseif (isset($page['next_item']) and $row['id'] == $page['next_item']) {
        $i = 'next';
    } elseif (isset($page['first_item']) and $row['id'] == $page['first_item']) {
        $i = 'first';
    } elseif (isset($page['last_item']) and $row['id'] == $page['last_item']) {
        $i = 'last';
    } else {
        $i = 'current';
    }

    $row['src_image'] = new \Phyxo\Image\SrcImage($row, $conf['picture_ext']);
    $row['derivatives'] = \Phyxo\Image\DerivativeImage::get_all($row['src_image']);

    if ($i == 'current') {
        $row['element_path'] = \Phyxo\Functions\Utils::get_element_path($row);

        if ($row['src_image']->is_original()) { // we have a photo
            if (!empty(['enabled_high'])) {
                $row['element_url'] = $row['src_image']->get_url();
                $row['download_url'] = \Phyxo\Functions\URL::get_action_url($row['id'], 'e', true);
            }
        } else { // not a pic - need download link
            $row['download_url'] = $row['element_url'] = \Phyxo\Functions\URL::get_element_url($row);;
        }
    }

    $row['url'] = \Phyxo\Functions\URL::duplicate_picture_url(
        [
            'image_id' => $row['id'],
            'image_file' => $row['file'],
        ],
        ['start']
    );

    $picture[$i] = $row;
    $picture[$i]['TITLE'] = \Phyxo\Functions\Utils::render_element_name($row);
    $picture[$i]['TITLE_ESC'] = htmlspecialchars($picture[$i]['TITLE'], ENT_COMPAT, 'utf-8');

    if ('previous' == $i and $page['previous_item'] == $page['first_item']) {
        $picture['first'] = $picture[$i];
    }
    if ('next' == $i and $page['next_item'] == $page['last_item']) {
        $picture['last'] = $picture[$i];
    }
}

$slideshow_params = [];
$slideshow_url_params = [];

if (isset($_GET['slideshow'])) {
    $page['slideshow'] = true;

    $slideshow_params = \Phyxo\Functions\Utils::decode_slideshow_params($_GET['slideshow']);
    $slideshow_url_params['slideshow'] = \Phyxo\Functions\Utils::encode_slideshow_params($slideshow_params);

    if ($slideshow_params['play']) {
        $id_pict_redirect = '';
        if (isset($page['next_item'])) {
            $id_pict_redirect = 'next';
        } else {
            if ($slideshow_params['repeat'] and isset($page['first_item'])) {
                $id_pict_redirect = 'first';
            }
        }

        if (!empty($id_pict_redirect)) {
            // $refresh, $url_link and $title are required for creating
            // an automated refresh page in header.tpl
            $refresh = $slideshow_params['period'];
            $url_link = \Phyxo\Functions\URL::add_url_params(
                $picture[$id_pict_redirect]['url'],
                $slideshow_url_params
            );
        }
    }
} else {
    $page['slideshow'] = false;
}

$title = $picture['current']['TITLE'];
$title_nb = ($page['current_rank'] + 1) . '/' . count($page['items']);

// metadata
$url_metadata = \Phyxo\Functions\URL::duplicate_picture_url();
$url_metadata = \Phyxo\Functions\URL::add_url_params($url_metadata, ['metadata' => null]);

// do we have a plugin that can show metadata for something else than images?
$metadata_showable = \Phyxo\Functions\Plugin::trigger_change(
    'get_element_metadata_available',
    (($conf['show_exif'] or $conf['show_iptc'])
        and !$picture['current']['src_image']->is_mimetype()),
    $picture['current']
);

// allow plugins to change what we computed before passing data to template
$picture = \Phyxo\Functions\Plugin::trigger_change('picture_pictures_data', $picture);

//------------------------------------------------------- navigation management
foreach (['first', 'previous', 'next', 'last', 'current'] as $which_image) {
    if (isset($picture[$which_image])) {
        $template->assign(
            $which_image,
            array_merge(
                $picture[$which_image],
                [
                    // Params slideshow was transmit to navigation buttons
                    'U_IMG' => \Phyxo\Functions\URL::add_url_params($picture[$which_image]['url'], $slideshow_url_params),
                ]
            )
        );
    }
}

if ($conf['picture_download_icon'] and !empty($picture['current']['download_url'])) {
    $template->append('current', ['U_DOWNLOAD' => $picture['current']['download_url']], true);
}

if ($page['slideshow']) {
    $tpl_slideshow = [];

    //slideshow end
    $template->assign(
        [
            'U_SLIDESHOW_STOP' => $picture['current']['url'],
        ]
    );

    foreach (['repeat', 'play'] as $p) {
        $var_name = 'U_' . ($slideshow_params[$p] ? 'STOP_' : 'START_') . strtoupper($p);

        $tpl_slideshow[$var_name] = \Phyxo\Functions\URL::add_url_params(
            $picture['current']['url'],
            ['slideshow' => \Phyxo\Functions\Utils::encode_slideshow_params(array_merge($slideshow_params, [$p => !$slideshow_params[$p]]))]
        );
    }

    foreach (['dec', 'inc'] as $op) {
        $new_period = $slideshow_params['period'] + ((($op == 'dec') ? -1 : 1) * $conf['slideshow_period_step']);
        $new_slideshow_params = \Phyxo\Functions\Utils::correct_slideshow_params(
            array_merge(
                $slideshow_params,
                ['period' => $new_period]
            )
        );

        if ($new_slideshow_params['period'] === $new_period) {
            $var_name = 'U_' . strtoupper($op) . '_PERIOD';
            $tpl_slideshow[$var_name] = \Phyxo\Functions\URL::add_url_params(
                $picture['current']['url'],
                ['slideshow' => \Phyxo\Functions\Utils::encode_slideshow_params($new_slideshow_params)]
            );
        }
    }
    $template->assign('slideshow', $tpl_slideshow);
} elseif ($conf['picture_slideshow_icon']) {
    $template->assign(
        [
            'U_SLIDESHOW_START' => \Phyxo\Functions\URL::add_url_params($picture['current']['url'], ['slideshow' => ''])
        ]
    );
}

$template->assign(
    [
        'SECTION_TITLE' => $page['section_title'],
        'PHOTO' => $title_nb,
        'IS_HOME' => ('categories' == $page['section'] and !isset($page['category'])),
        'LEVEL_SEPARATOR' => $conf['level_separator'],
        'U_UP' => $url_up,
        'U_UP_SIZE_CSS' => $picture['current']['derivatives']['square']->get_size_css(),
        'DISPLAY_NAV_BUTTONS' => $conf['picture_navigation_icons'],
        'DISPLAY_NAV_THUMB' => $conf['picture_navigation_thumb']
    ]
);

if ($conf['picture_metadata_icon']) {
    $template->assign('U_METADATA', $url_metadata);
}

//------------------------------------------------------- upper menu management

// admin links
if ($userMapper->isAdmin()) {
    if (isset($page['category'])) {
        $template->assign(['U_SET_AS_REPRESENTATIVE' => \Phyxo\Functions\URL::add_url_params($url_self, ['action' => 'set_as_representative'])]);
    }

    $url_admin = \Phyxo\Functions\URL::get_root_url() . 'admin/index.php?page=photo&amp;image_id=' . $page['image_id'];
    $url_admin .= (isset($page['category']) ? '&amp;cat_id=' . $page['category']['id'] : '');

    $template->assign(
        [
            'U_CADDIE' => \Phyxo\Functions\URL::add_url_params($url_self, ['action' => 'add_to_caddie']),
            'U_PHOTO_ADMIN' => $url_admin,
        ]
    );

    $template->assign('available_permission_levels', \Phyxo\Functions\Utils::get_privacy_level_options());
}

// favorite manipulation
if (!$userMapper->isGuest() && $conf['picture_favorite_icon']) {
    // verify if the picture is already in the favorite of the user
    $is_favorite = (new FavoriteRepository($conn))->iSFavorite($user['id'], $page['image_id']);
    $template->assign(
        'favorite',
        [
            'IS_FAVORITE' => $is_favorite,
            'U_FAVORITE' => \Phyxo\Functions\URL::add_url_params(
                $url_self,
                ['action' => !$is_favorite ? 'add_to_favorites' : 'remove_from_favorites']
            )
        ]
    );
}

//--------------------------------------------------------- picture information
// legend
if (!empty($picture['current']['comment'])) {
    $template->assign(
        'COMMENT_IMG',
        \Phyxo\Functions\Plugin::trigger_change(
            'render_element_description',
            $picture['current']['comment'],
            'picture_page_element_description'
        )
    );
}

// author
if (!empty($picture['current']['author'])) {
    $infos['INFO_AUTHOR'] = $picture['current']['author'];
}

// creation date
if (!empty($picture['current']['date_creation'])) {
    $val = \Phyxo\Functions\DateTime::format_date($picture['current']['date_creation']);
    $url = \Phyxo\Functions\URL::make_index_url(
        [
            'section' => 'categories',
            'chronology_field' => 'created',
            'chronology_style' => 'monthly',
            'chronology_view' => 'list',
            'chronology_date' => explode('-', substr($picture['current']['date_creation'], 0, 10))
        ]
    );
    $infos['INFO_CREATION_DATE'] = '<a href="' . $url . '" rel="nofollow">' . $val . '</a>';
}

// date of availability
$val = \Phyxo\Functions\DateTime::format_date($picture['current']['date_available']);
$url = \Phyxo\Functions\URL::make_index_url(
    [
        'section' => 'categories',
        'chronology_field' => 'posted',
        'chronology_style' => 'monthly',
        'chronology_view' => 'list',
        'chronology_date' => explode(
            '-',
            substr($picture['current']['date_available'], 0, 10)
        )
    ]
);
$infos['INFO_POSTED_DATE'] = '<a href="' . $url . '" rel="nofollow">' . $val . '</a>';

// size in pixels
if ($picture['current']['src_image']->is_original() and isset($picture['current']['width'])) {
    $infos['INFO_DIMENSIONS'] = $picture['current']['width'] . '*' . $picture['current']['height'];
}

// filesize
if (!empty($picture['current']['filesize'])) {
    $infos['INFO_FILESIZE'] = \Phyxo\Functions\Language::l10n('%d Kb', $picture['current']['filesize']);
}

// number of visits
$infos['INFO_VISITS'] = $picture['current']['hit'];

// file
$infos['INFO_FILE'] = $picture['current']['file'];

$template->assign($infos);
$template->assign('display_info', json_decode($conf['picture_informations'], true));

// related tags
$tags = $tagMapper->getCommonTags($app_user, [$page['image_id']], -1);
if (count($tags)) {
    foreach ($tags as $tag) {
        $template->append(
            'related_tags',
            array_merge(
                $tag,
                [
                    'URL' => \Phyxo\Functions\URL::make_index_url(['tags' => [$tag]]),
                    'U_TAG_IMAGE' => \Phyxo\Functions\URL::duplicate_picture_url(
                        [
                            'section' => 'tags',
                            'tags' => [$tag]
                        ]
                    )
                ]
            )
        );
    }
}

if (!empty($conf['tags_permission_add'])) {
    $template->assign(
        'TAGS_PERMISSION_ADD',
        0 // (int)$userMapper->isAuthorizeStatus($services['users']->getAccessTypeStatus($conf['tags_permission_add'])) // @TODO: add Voter
    );
} else {
    $template->assign('TAGS_PERMISSION_ADD', 0);
}
if (!empty($conf['tags_permission_delete'])) {
    $template->assign(
        'TAGS_PERMISSION_DELETE',
        0 // (int)$services['users']->isAuthorizeStatus($services['users']->getAccessTypeStatus($conf['tags_permission_delete'])) // @TODO: add Voter
    );
} else {
    $template->assign('TAGS_PERMISSION_DELETE', 0);
}
if (isset($conf['tags_existing_tags_only'])) {
    $template->assign('TAGS_PERMISSION_ALLOW_CREATION', $conf['tags_existing_tags_only'] == 1 ? 0 : 1);
} else {
    $template->assign('TAGS_PERMISSION_ALLOW_CREATION', 1);
}
$template->assign('USER_TAGS_WS_GETLIST', \Phyxo\Functions\URL::get_root_url() . 'ws.php?method=pwg.tags.getFilteredList');
$template->assign('USER_TAGS_UPDATE_SCRIPT', \Phyxo\Functions\URL::get_root_url() . 'ws.php?method=pwg.images.setRelatedTags');

// related categories
if (count($related_categories) == 1 and isset($page['category']) and $related_categories[0]['id'] == $page['category']['id']) {
    // no need to go to db, we have all the info
    $template->append(
        'related_categories',
        $categoryMapper->getCatDisplayName($page['category']['upper_names'])
    );
} else { // use only 1 sql query to get names for all related categories
    $ids = [];
    foreach ($related_categories as $category) { // add all uppercats to $ids
        $ids = array_merge($ids, explode(',', $category['uppercats']));
    }
    $ids = array_unique($ids);
    $result = (new CategoryRepository($conn))->findByIds($ids);
    $cat_map = $conn->result2array($result, 'id');
    foreach ($related_categories as $category) {
        $cats = [];
        foreach (explode(',', $category['uppercats']) as $id) {
            $cats[] = $cat_map[$id];
        }
        $template->append('related_categories', $categoryMapper->getCatDisplayName($cats));
    }
}

// maybe someone wants a special display (call it before page_header so that
// they can add stylesheets)
$element_content = \Phyxo\Functions\Plugin::trigger_change(
    'render_element_content',
    '',
    $picture['current']
);
$template->assign('ELEMENT_CONTENT', $element_content);

if (isset($picture['next']) and $picture['next']['src_image']->is_original() and $template->get_template_vars('U_PREFETCH') == null
    and strpos(@$_SERVER['HTTP_USER_AGENT'], 'Chrome/') === false) {
    $template->assign(
        'U_PREFETCH',
        $picture['next']['derivatives'][isset($_SESSION['picture_deriv']) ? $_SESSION['picture_deriv'] : $conf['derivative_default_size']]->get_url()
    );
}

$template->assign(
    'U_CANONICAL',
    \Phyxo\Functions\URL::make_picture_url(
        [
            'image_id' => $picture['current']['id'],
            'image_file' => $picture['current']['file']
        ]
    )
);

// +-----------------------------------------------------------------------+
// |                               sub pages                               |
// +-----------------------------------------------------------------------+


include(__DIR__ . '/picture_rate.inc.php');
if ($conf['activate_comments']) {
    include(__DIR__ . '/picture_comment.inc.php');
}
if ($metadata_showable && !empty($_SESSION['show_metadata'])) {
    include(__DIR__ . '/picture_metadata.inc.php');
}

if (!isset($page['start'])) {
    $page['start'] = 0;
}

\Phyxo\Functions\Plugin::trigger_notify('loc_end_picture');
\Phyxo\Functions\Utils::flush_page_messages();
//------------------------------------------------------------ log informations
\Phyxo\Functions\Utils::log($picture['current']['id'], 'picture');
