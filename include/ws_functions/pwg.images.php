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

// +-----------------------------------------------------------------------+
// | UTILITIES                                                             |
// +-----------------------------------------------------------------------+

use Phyxo\Ws\Server;

/**
 * Sets associations of an image
 * @param int $image_id
 * @param string $categories_string - "cat_id[,rank];cat_id[,rank]"
 * @param bool $replace_mode - removes old associations
 */
function ws_add_image_category_relations($image_id, $categories_string, $replace_mode = false)
{
    global $conn;

    // let's add links between the image and the categories
    //
    // $params['categories'] should look like 123,12;456,auto;789 which means:
    //
    // 1. associate with category 123 on rank 12
    // 2. associate with category 456 on automatic rank
    // 3. associate with category 789 on automatic rank
    $cat_ids = array();
    $rank_on_category = array();
    $search_current_ranks = false;

    $tokens = explode(';', $categories_string);
    foreach ($tokens as $token) {
        @list($cat_id, $rank) = explode(',', $token);

        if (!preg_match('/^\d+$/', $cat_id)) {
            continue;
        }

        $cat_ids[] = $cat_id;

        if (!isset($rank)) {
            $rank = 'auto';
        }
        $rank_on_category[$cat_id] = $rank;

        if ($rank == 'auto') {
            $search_current_ranks = true;
        }
    }

    $cat_ids = array_unique($cat_ids);

    if (count($cat_ids) == 0) {
        return new Phyxo\Ws\Error(
            500,
            '[ws_add_image_category_relations] there is no category defined in "' . $categories_string . '"'
        );
    }

    $query = 'SELECT id FROM ' . CATEGORIES_TABLE;
    $query .= ' WHERE id ' . $conn->in($cat_ids);
    $db_cat_ids = $conn->query2array($query, null, 'id');

    $unknown_cat_ids = array_diff($cat_ids, $db_cat_ids);
    if (count($unknown_cat_ids) != 0) {
        return new Phyxo\Ws\Error(
            500,
            '[ws_add_image_category_relations] the following categories are unknown: ' . implode(', ', $unknown_cat_ids)
        );
    }

    $to_update_cat_ids = array();

    // in case of replace mode, we first check the existing associations
    $query = 'SELECT category_id FROM ' . IMAGE_CATEGORY_TABLE . ' WHERE image_id = ' . $image_id . ';';
    $existing_cat_ids = $conn->query2array($query, null, 'category_id');

    if ($replace_mode) {
        $to_remove_cat_ids = array_diff($existing_cat_ids, $cat_ids);
        if (count($to_remove_cat_ids) > 0) {
            $query = 'DELETE FROM ' . IMAGE_CATEGORY_TABLE . ' WHERE image_id = ' . $image_id;
            $query .= ' AND category_id ' . $conn->in($to_remove_cat_ids);
            $conn->db_query($query);
            update_category($to_remove_cat_ids);
        }
    }

    $new_cat_ids = array_diff($cat_ids, $existing_cat_ids);
    if (count($new_cat_ids) == 0) {
        return true;
    }

    if ($search_current_ranks) {
        $query = 'SELECT category_id, MAX(rank) AS max_rank FROM ' . IMAGE_CATEGORY_TABLE;
        $query .= ' WHERE rank IS NOT NULL AND category_id ' . $conn->in($new_cat_ids);
        $query .= ' GROUP BY category_id;';
        $current_rank_of = $conn->query2array($query, 'category_id', 'max_rank');

        foreach ($new_cat_ids as $cat_id) {
            if (!isset($current_rank_of[$cat_id])) {
                $current_rank_of[$cat_id] = 0;
            }

            if ('auto' == $rank_on_category[$cat_id]) {
                $rank_on_category[$cat_id] = $current_rank_of[$cat_id] + 1;
            }
        }
    }

    $inserts = array();

    foreach ($new_cat_ids as $cat_id) {
        $inserts[] = array(
            'image_id' => $image_id,
            'category_id' => $cat_id,
            'rank' => $rank_on_category[$cat_id],
        );
    }

    $conn->mass_inserts(
        IMAGE_CATEGORY_TABLE,
        array_keys($inserts[0]),
        $inserts
    );

    include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');
    update_category($new_cat_ids);
}

/**
 * Merge chunks added by pwg.images.addChunk
 * @param string $output_filepath
 * @param string $original_sum
 * @param string $type
 */
function merge_chunks($output_filepath, $original_sum, $type)
{
    global $conf;

    ws_logfile('[merge_chunks] input parameter $output_filepath : ' . $output_filepath);

    if (is_file($output_filepath)) {
        unlink($output_filepath);

        if (is_file($output_filepath)) {
            return new Phyxo\Ws\Error(500, '[merge_chunks] error while trying to remove existing ' . $output_filepath);
        }
    }

    $upload_dir = $conf['upload_dir'] . '/buffer';
    $pattern = '/' . $original_sum . '-' . $type . '/';
    $chunks = array();

    if ($handle = opendir($upload_dir)) {
        while (false !== ($file = readdir($handle))) {
            if (preg_match($pattern, $file)) {
                ws_logfile($file);
                $chunks[] = $upload_dir . '/' . $file;
            }
        }
        closedir($handle);
    }

    sort($chunks);

    if (function_exists('memory_get_usage')) {
        ws_logfile('[merge_chunks] memory_get_usage before loading chunks: ' . memory_get_usage());
    }

    $i = 0;

    foreach ($chunks as $chunk) {
        $string = file_get_contents($chunk);

        if (function_exists('memory_get_usage')) {
            ws_logfile('[merge_chunks] memory_get_usage on chunk ' . ++$i . ': ' . memory_get_usage());
        }

        if (!file_put_contents($output_filepath, $string, FILE_APPEND)) {
            return new Phyxo\Ws\Error(500, '[merge_chunks] error while writting chunks for ' . $output_filepath);
        }

        unlink($chunk);
    }

    if (function_exists('memory_get_usage')) {
        ws_logfile('[merge_chunks] memory_get_usage after loading chunks: ' . memory_get_usage());
    }
}

/**
 * Deletes chunks added with pwg.images.addChunk
 * @param string $original_sum
 * @param string $type
 *
 * Function introduced for Piwigo 2.4 and the new "multiple size"
 * (derivatives) feature. As we only need the biggest sent photo as
 * "original", we remove chunks for smaller sizes. We can't make it earlier
 * in ws_images_add_chunk because at this moment we don't know which $type
 * will be the biggest (we could remove the thumb, but let's use the same
 * algorithm)
 */
function remove_chunks($original_sum, $type)
{
    global $conf;

    $upload_dir = $conf['upload_dir'] . '/buffer';
    $pattern = '/' . $original_sum . '-' . $type . '/';
    $chunks = array();

    if ($handle = opendir($upload_dir)) {
        while (false !== ($file = readdir($handle))) {
            if (preg_match($pattern, $file)) {
                $chunks[] = $upload_dir . '/' . $file;
            }
        }
        closedir($handle);
    }

    foreach ($chunks as $chunk) {
        unlink($chunk);
    }
}


// +-----------------------------------------------------------------------+
// | METHODS                                                               |
// +-----------------------------------------------------------------------+

/**
 * API method
 * Adds a comment to an image
 * @param mixed[] $params
 *    @option int image_id
 *    @option string author
 *    @option string content
 *    @option string key
 */
function ws_images_addComment($params, $service)
{
    global $conn, $services;

    $query = 'SELECT DISTINCT image_id  FROM ' . CATEGORIES_TABLE;
    $query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' ON category_id=id';
    $query .= ' WHERE commentable=\'' . $conn->boolean_to_db(true) . '\'';
    $query .= ' AND image_id=' . $params['image_id'];
    $query .= get_sql_condition_FandF(
        array(
            'forbidden_categories' => 'id',
            'visible_categories' => 'id',
            'visible_images' => 'image_id'
        ),
        ' AND'
    );

    if (!$conn->db_num_rows($conn->db_query($query))) {
        return new Phyxo\Ws\Error(Server::WS_ERR_INVALID_PARAM, 'Invalid image_id');
    }

    $comm = array(
        'author' => trim($params['author']),
        'content' => trim($params['content']),
        'image_id' => $params['image_id'],
    );

    $comment_action = $services['comments']->insertUserComment($comm, $params['key'], $infos);

    switch ($comment_action) {
        case 'reject':
            $infos[] = \Phyxo\Functions\Language::l10n('Your comment has NOT been registered because it did not pass the validation rules');
            return new Phyxo\Ws\Error(403, implode("; ", $infos));

        case 'validate':
        case 'moderate':
            $ret = array(
                'id' => $comm['id'],
                'validation' => $comment_action == 'validate',
            );
            return array('comment' => new Phyxo\Ws\NamedStruct($ret));
        default:
            return new Phyxo\Ws\Error(500, "Unknown comment action " . $comment_action);
    }
}

/**
 * API method
 * Returns detailed information for an element
 * @param mixed[] $params
 *    @option int image_id
 *    @option int comments_page
 *    @option int comments_per_page
 */
function ws_images_getInfo($params, $service)
{
    global $user, $conf, $conn, $services;

    $query = 'SELECT * FROM ' . IMAGES_TABLE;
    $query .= ' WHERE id=' . $conn->db_real_escape_string($params['image_id']);
    $query .= get_sql_condition_FandF(array('visible_images' => 'id'), ' AND ');
    $query .= ' LIMIT 1;';
    $result = $conn->db_query($query);

    if ($conn->db_num_rows($result) == 0) {
        return new Phyxo\Ws\Error(404, 'image_id not found');
    }

    $image_row = $conn->db_fetch_assoc($result);
    $image_row = array_merge($image_row, ws_std_get_urls($image_row));

    //-------------------------------------------------------- related categories
    $query = 'SELECT id, name, permalink, uppercats, global_rank, commentable FROM ' . CATEGORIES_TABLE;
    $query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' ON category_id = id';
    $query .= ' WHERE image_id = ' . $conn->db_real_escape_string($image_row['id']);
    $query .= get_sql_condition_FandF(array('forbidden_categories' => 'category_id'), ' AND ');
    $result = $conn->db_query($query);

    $is_commentable = false;
    $related_categories = array();
    while ($row = $conn->db_fetch_assoc($result)) {
        $is_commentable = $conn->get_boolean($row['commentable']);
        unset($row['commentable']);

        $row['url'] = \Phyxo\Functions\URL::make_index_url(array('category' => $row));
        $row['page_url'] = \Phyxo\Functions\URL::make_picture_url(
            array(
                'image_id' => $image_row['id'],
                'image_file' => $image_row['file'],
                'category' => $row
            )
        );

        $row['id'] = (int)$row['id'];
        $related_categories[] = $row;
    }
    usort($related_categories, 'global_rank_compare');

    if (empty($related_categories)) {
        return new Phyxo\Ws\Error(401, 'Access denied');
    }

  //-------------------------------------------------------------- related tags
    $related_tags = $services['tags']->getCommonTags(array($image_row['id']), -1);
    foreach ($related_tags as $i => $tag) {
        $tag['url'] = \Phyxo\Functions\URL::make_index_url(array('tags' => array($tag)));
        $tag['page_url'] = \Phyxo\Functions\URL::make_picture_url(
            array(
                'image_id' => $image_row['id'],
                'image_file' => $image_row['file'],
                'tags' => array($tag),
            )
        );

        unset($tag['counter']);
        $tag['id'] = (int)$tag['id'];
        $related_tags[$i] = $tag;
    }

    //------------------------------------------------------------- related rates
    $rating = array(
        'score' => $image_row['rating_score'],
        'count' => 0,
        'average' => null,
    );
    if (isset($rating['score'])) {
        $query = 'SELECT COUNT(rate) AS count, ROUND(AVG(rate),2) AS average FROM ' . RATE_TABLE;
        $query .= ' WHERE element_id = ' . $image_row['id'] . ';';
        $row = $conn->db_fetch_assoc($conn->db_query($query));

        $rating['score'] = (float)$rating['score'];
        $rating['average'] = (float)$row['average'];
        $rating['count'] = (int)$row['count'];
    }

    //---------------------------------------------------------- related comments
    $related_comments = array();

    $where_comments = 'image_id = ' . $image_row['id'];
    if (!$services['users']->isAdmin()) {
        $where_comments .= ' AND validated=\'' . $conn->boolean_to_db(true) . '\'';
    }

    $query = 'SELECT COUNT(id) AS nb_comments FROM ' . COMMENTS_TABLE . ' WHERE ' . $where_comments . ';';
    list($nb_comments) = $conn->query2array($query, null, 'nb_comments');
    $nb_comments = (int)$nb_comments;

    if ($nb_comments > 0 and $params['comments_per_page'] > 0) {
        $query = 'SELECT id, date, author, content FROM ' . COMMENTS_TABLE;
        $query .= ' WHERE ' . $where_comments . ' ORDER BY date';
        $query .= ' LIMIT ' . (int)$params['comments_per_page'];
        $query .= ' OFFSET ' . (int)($params['comments_per_page'] * $params['comments_page']) . ';';
        $result = $conn->db_query($query);

        while ($row = $conn->db_fetch_assoc($result)) {
            $row['id'] = (int)$row['id'];
            $related_comments[] = $row;
        }
    }

    $comment_post_data = null;
    if ($is_commentable && (!$services['users']->isGuest() || ($services['users']->isGuest() && $conf['comments_forall']))) {
        $comment_post_data['author'] = stripslashes($user['username']);
        $comment_post_data['key'] = get_ephemeral_key(2, $params['image_id']);
    }

    $ret = $image_row;
    foreach (array('id', 'width', 'height', 'hit', 'filesize') as $k) {
        if (isset($ret[$k])) {
            $ret[$k] = (int)$ret[$k];
        }
    }
    foreach (array('path', 'storage_category_id') as $k) {
        unset($ret[$k]);
    }

    $ret['rates'] = array(
        Server::WS_XML_ATTRIBUTES => $rating
    );
    $ret['categories'] = new Phyxo\Ws\NamedArray(
        $related_categories,
        'category',
        array('id', 'url', 'page_url')
    );
    $ret['tags'] = new Phyxo\Ws\NamedArray(
        $related_tags,
        'tag',
        ws_std_get_tag_xml_attributes()
    );
    if (isset($comment_post_data)) {
        $ret['comment_post'] = array(
            Server::WS_XML_ATTRIBUTES => $comment_post_data
        );
    }
    $ret['comments_paging'] = new Phyxo\Ws\NamedStruct(
        array(
            'page' => $params['comments_page'],
            'per_page' => $params['comments_per_page'],
            'count' => count($related_comments),
            'total_count' => $nb_comments,
        )
    );
    $ret['comments'] = new Phyxo\Ws\NamedArray(
        $related_comments,
        'comment',
        array('id', 'date')
    );

    if ($service->_responseFormat != 'rest') {
        return $ret; // for backward compatibility only
    } else {
        return array(
            'image' => new Phyxo\Ws\NamedStruct($ret, null, array('name', 'comment'))
        );
    }
}

/**
 * API method
 * Rates an image
 * @param mixed[] $params
 *    @option int image_id
 *    @option float rate
 */
function ws_images_rate($params, $service)
{
    global $conf, $conn;

    $query = 'SELECT DISTINCT id FROM ' . IMAGES_TABLE;
    $query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' ON id=image_id';
    $query .= ' WHERE id=' . $conn->db_real_escape_string($params['image_id']);
    $query .= get_sql_condition_FandF(
        array(
            'forbidden_categories' => 'category_id',
            'forbidden_images' => 'id',
        ),
        '    AND'
    );
    $query .= ' LIMIT 1;';
    if ($conn->db_num_rows($conn->db_query($query)) == 0) {
        return new Phyxo\Ws\Error(404, 'Invalid image_id or access denied');
    }

    include_once(PHPWG_ROOT_PATH . 'include/functions_rate.inc.php');
    $res = rate_picture($params['image_id'], (int)$params['rate']);

    if ($res == false) {
        return new Phyxo\Ws\Error(403, 'Forbidden or rate not in ' . implode(',', $conf['rate_items']));
    }
    return $res;
}

/**
 * API method
 * Returns a list of elements corresponding to a query search
 * @param mixed[] $params
 *    @option string query
 *    @option int per_page
 *    @option int page
 *    @option string order (optional)
 */
function ws_images_search($params, $service)
{
    global $conn;

    include_once(PHPWG_ROOT_PATH . 'include/functions_search.inc.php');

    $images = array();
    $where_clauses = ws_std_image_sql_filter($params, 'i.');
    $order_by = ws_std_image_sql_order($params, 'i.');

    $super_order_by = false;
    if (!empty($order_by)) {
        global $conf;
        $conf['order_by'] = 'ORDER BY ' . $order_by;
        $super_order_by = true; // quick_search_result might be faster
    }

    $search_result = get_quick_search_results(
        $params['query'],
        array(
            'super_order_by' => $super_order_by,
            'images_where' => implode(' AND ', $where_clauses)
        )
    );

    $image_ids = array_slice(
        $search_result['items'],
        $params['page'] * $params['per_page'],
        $params['per_page']
    );

    if (count($image_ids)) {
        $query = 'SELECT * FROM ' . IMAGES_TABLE;
        $query .= ' WHERE id ' . $conn->in($image_ids);
        $result = $conn->db_query($query);
        $image_ids = array_flip($image_ids);

        while ($row = $conn->db_fetch_assoc($result)) {
            $image = array();
            foreach (array('id', 'width', 'height', 'hit') as $k) {
                if (isset($row[$k])) {
                    $image[$k] = (int)$row[$k];
                }
            }
            foreach (array('file', 'name', 'comment', 'date_creation', 'date_available') as $k) {
                $image[$k] = $row[$k];
            }

            $image = array_merge($image, ws_std_get_urls($row));
            $images[$image_ids[$image['id']]] = $image;
        }
        ksort($images, SORT_NUMERIC);
        $images = array_values($images);
    }

    return array(
        'paging' => new Phyxo\Ws\NamedStruct(
            array(
                'page' => $params['page'],
                'per_page' => $params['per_page'],
                'count' => count($images),
                'total_count' => count($search_result['items']),
            )
        ),
        'images' => new Phyxo\Ws\NamedArray(
            $images,
            'image',
            ws_std_get_image_xml_attributes()
        )
    );
}

/**
 * API method
 * Sets the level of an image
 * @param mixed[] $params
 *    @option int image_id
 *    @option int level
 */
function ws_images_setPrivacyLevel($params, $service)
{
    global $conf, $conn;

    if (!in_array($params['level'], $conf['available_permission_levels'])) {
        return new Phyxo\Ws\Error(Server::WS_ERR_INVALID_PARAM, 'Invalid level');
    }

    $query = 'UPDATE ' . IMAGES_TABLE;
    $query .= ' SET level=' . (int)$params['level'];
    $query .= ' WHERE id ' . $conn->in($params['image_id']);
    $result = $conn->db_query($query);

    if ($affected_rows = $conn->db_changes($result)) {
        include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');
        invalidate_user_cache();
    }
    return $affected_rows;
}

/**
 * API method
 * Sets the rank of an image in a category
 * @param mixed[] $params
 *    @option int image_id
 *    @option int category_id
 *    @option int rank
 */
function ws_images_setRank($params, $service)
{
    global $conn;

    // does the image really exist?
    $query = 'SELECT COUNT(1) FROM ' . IMAGES_TABLE;
    $query .= ' WHERE id = ' . $conn->db_real_escape_string($params['image_id']);
    list($count) = $conn->db_fetch_row($conn->db_query($query));
    if ($count == 0) {
        return new Phyxo\Ws\Error(404, 'image_id not found');
    }

    // is the image associated to this category?
    $query = 'SELECT COUNT(1) FROM ' . IMAGE_CATEGORY_TABLE;
    $query .= ' WHERE image_id = ' . $conn->db_real_escape_string($params['image_id']);
    $query .= ' AND category_id = ' . $conn->db_real_escape_string($params['category_id']);
    list($count) = $conn->db_fetch_row($conn->db_query($query));
    if ($count == 0) {
        return new Phyxo\Ws\Error(404, 'This image is not associated to this category');
    }

    // what is the current higher rank for this category?
    $query = 'SELECT MAX(rank) AS max_rank FROM ' . IMAGE_CATEGORY_TABLE;
    $query .= ' WHERE category_id = ' . $conn->db_real_escape_string($params['category_id']);
    $row = $conn->db_fetch_assoc($conn->db_query($query));

    if (is_numeric($row['max_rank'])) {
        if ($params['rank'] > $row['max_rank']) {
            $params['rank'] = $row['max_rank'] + 1;
        }
    } else {
        $params['rank'] = 1;
    }

    // update rank for all other photos in the same category
    $query = 'UPDATE ' . IMAGE_CATEGORY_TABLE;
    $query .= ' SET rank = rank + 1';
    $query .= ' WHERE category_id = ' . $conn->db_real_escape_string($params['category_id']);
    $query .= ' AND rank IS NOT NULL AND rank >= ' . $params['rank'] . ';';
    $conn->db_query($query);

    // set the new rank for the photo
    $query = 'UPDATE ' . IMAGE_CATEGORY_TABLE . ' SET rank = ' . $params['rank'];
    $query .= ' WHERE image_id = ' . $conn->db_real_escape_string($params['image_id']);
    $query .= ' AND category_id = ' . $conn->db_real_escape_string($params['category_id']);
    $conn->db_query($query);

    // return data for client
    return array(
        'image_id' => $params['image_id'],
        'category_id' => $params['category_id'],
        'rank' => $params['rank'],
    );
}

/**
 * API method
 * Adds a file chunk
 * @param mixed[] $params
 *    @option string data
 *    @option string original_sum
 *    @option string type = 'file'
 *    @option int position
 */
function ws_images_add_chunk($params, $service)
{
    global $conf;

    foreach ($params as $param_key => $param_value) {
        if ('data' == $param_key) {
            continue;
        }
        ws_logfile(
            sprintf(
                '[ws_images_add_chunk] input param "%s" : "%s"',
                $param_key,
                is_null($param_value) ? 'NULL' : $param_value
            )
        );
    }

    $upload_dir = $conf['upload_dir'] . '/buffer';

    // create the upload directory tree if not exists
    if (!mkgetdir($upload_dir, MKGETDIR_DEFAULT & ~MKGETDIR_DIE_ON_ERROR)) {
        return new Phyxo\Ws\Error(500, 'error during buffer directory creation');
    }

    $filename = sprintf(
        '%s-%s-%05u.block',
        $params['original_sum'],
        $params['type'],
        $params['position']
    );

    ws_logfile('[ws_images_add_chunk] data length : ' . strlen($params['data']));

    $bytes_written = file_put_contents(
        $upload_dir . '/' . $filename,
        base64_decode($params['data'])
    );

    if (false === $bytes_written) {
        return new Phyxo\Ws\Error(
            500,
            'an error has occured while writting chunk ' . $params['position'] . ' for ' . $params['type']
        );
    }
}

/**
 * API method
 * Adds a file
 * @param mixed[] $params
 *    @option int image_id
 *    @option string type = 'file'
 *    @option string sum
 */
function ws_images_addFile($params, $service)
{
    global $conf, $conn;

    ws_logfile(__FUNCTION__ . ', input :  ' . var_export($params, true));

    // what is the path and other infos about the photo?
    $query = 'SELECT path, file, md5sum, width, height, filesize FROM ' . IMAGES_TABLE;
    $query .= ' WHERE id = ' . $conn->db_real_escape_string($params['image_id']);
    $result = $conn->db_query($query);

    if ($conn->db_num_rows($result) == 0) {
        return new Phyxo\Ws\Error(404, "image_id not found");
    }

    $image = $conn->db_fetch_assoc($result);

    // since Piwigo 2.4 and derivatives, we do not take the imported "thumb" into account
    if ('thumb' == $params['type']) {
        remove_chunks($image['md5sum'], $type);
        return true;
    }

    // since Piwigo 2.4 and derivatives, we only care about the "original"
    $original_type = 'file';
    if ('high' == $params['type']) {
        $original_type = 'high';
    }

    $file_path = $conf['upload_dir'] . '/buffer/' . $image['md5sum'] . '-original';

    merge_chunks($file_path, $image['md5sum'], $original_type);
    chmod($file_path, 0644);

    include_once(PHPWG_ROOT_PATH . 'admin/include/functions_upload.inc.php');

    // if we receive the "file", we only update the original if the "file" is
    // bigger than current original
    if ('file' == $params['type']) {
        $do_update = false;

        $infos = pwg_image_infos($file_path);

        foreach (array('width', 'height', 'filesize') as $image_info) {
            if ($infos[$image_info] > $image[$image_info]) {
                $do_update = true;
            }
        }

        if (!$do_update) {
            unlink($file_path);
            return true;
        }
    }

    $image_id = add_uploaded_file(
        $file_path,
        $image['file'],
        null,
        null,
        $params['image_id'],
        $image['md5sum'] // we force the md5sum to remain the same
    );
}

/**
 * API method
 * Adds an image
 * @param mixed[] $params
 *    @option string original_sum
 *    @option string original_filename (optional)
 *    @option string name (optional)
 *    @option string author (optional)
 *    @option string date_creation (optional)
 *    @option string comment (optional)
 *    @option string categories (optional) - "cat_id[,rank];cat_id[,rank]"
 *    @option string tags_ids (optional) - "tag_id,tag_id"
 *    @option int level
 *    @option bool check_uniqueness
 *    @option int image_id (optional)
 */
function ws_images_add($params, $service)
{
    global $conf, $user, $conn, $services;

    foreach ($params as $param_key => $param_value) {
        ws_logfile(
            sprintf(
                '[pwg.images.add] input param "%s" : "%s"',
                $param_key,
                is_null($param_value) ? 'NULL' : $param_value
            )
        );
    }

    if ($params['image_id'] > 0) {
        $query = 'SELECT COUNT(1) FROM ' . IMAGES_TABLE;
        $query .= ' WHERE id = ' . $conn->db_real_escape_string($params['image_id']);
        list($count) = $conn->db_fetch_row($conn->db_query($query));
        if ($count == 0) {
            return new Phyxo\Ws\Error(404, 'image_id not found');
        }
    }

    // does the image already exists ?
    if ($params['check_uniqueness']) {
        if ('md5sum' == $conf['uniqueness_mode']) {
            $where_clause = 'md5sum = \'' . $conn->db_real_escape_string($params['original_sum']) . '\'';
        }
        if ('filename' == $conf['uniqueness_mode']) {
            $where_clause = 'file = \'' . $conn->db_real_escape_string($params['original_filename']) . '\'';
        }

        $query = 'SELECT COUNT(1) FROM ' . IMAGES_TABLE . ' WHERE ' . $where_clause . ';';
        list($counter) = $conn->db_fetch_row($conn->db_query($query));
        if ($counter != 0) {
            return new Phyxo\Ws\Error(500, 'file already exists');
        }
    }

    // due to the new feature "derivatives" (multiple sizes) introduced for
    // Piwigo 2.4, we only take the biggest photos sent on
    // pwg.images.addChunk. If "high" is available we use it as "original"
    // else we use "file".
    remove_chunks($params['original_sum'], 'thumb');

    if (isset($params['high_sum'])) {
        $original_type = 'high';
        remove_chunks($params['original_sum'], 'file');
    } else {
        $original_type = 'file';
    }

    $file_path = $conf['upload_dir'] . '/buffer/' . $params['original_sum'] . '-original';

    merge_chunks($file_path, $params['original_sum'], $original_type);
    chmod($file_path, 0644);

    include_once(PHPWG_ROOT_PATH . 'admin/include/functions_upload.inc.php');

    $image_id = add_uploaded_file(
        $file_path,
        $params['original_filename'],
        null, // categories
        isset($params['level']) ? $params['level'] : null,
        $params['image_id'] > 0 ? $params['image_id'] : null,
        $params['original_sum']
    );

    $info_columns = array(
        'name',
        'author',
        'comment',
        'date_creation',
    );

    $update = array();
    foreach ($info_columns as $key) {
        if (isset($params[$key])) {
            $update[$key] = $params[$key];
        }
    }

    if (count(array_keys($update)) > 0) {
        $conn->single_update(
            IMAGES_TABLE,
            $update,
            array('id' => $image_id)
        );
    }

    $url_params = array('image_id' => $image_id);

    // let's add links between the image and the categories
    if (isset($params['categories'])) {
        ws_add_image_category_relations($image_id, $params['categories']);

        if (preg_match('/^\d+/', $params['categories'], $matches)) {
            $category_id = $matches[0];

            $query = 'SELECT id, name, permalink FROM ' . CATEGORIES_TABLE . ' WHERE id = ' . $category_id . ';';
            $result = $conn->db_query($query);
            $category = $conn->db_fetch_assoc($result);

            $url_params['section'] = 'categories';
            $url_params['category'] = $category;
        }
    }

    // and now, let's create tag associations
    if (!empty($params['tag_ids'])) {
        $services['tags']->setTags(explode(',', $params['tag_ids']), $image_id);
    }

    invalidate_user_cache();

    return array(
        'image_id' => $image_id,
        'url' => \Phyxo\Functions\URL::make_picture_url($url_params),
    );
}

/**
 * API method
 * Adds a image (simple way)
 * @param mixed[] $params
 *    @option int[] category
 *    @option string name (optional)
 *    @option string author (optional)
 *    @option string comment (optional)
 *    @option int level
 *    @option string|string[] tags
 *    @option int image_id (optional)
 */
function ws_images_addSimple($params, $service)
{
    global $conf, $conn, $services;

    if (!isset($_FILES['image'])) {
        return new Phyxo\Ws\Error(405, 'The image (file) is missing');
    }

    if ($params['image_id'] > 0) {
        $query = 'SELECT COUNT(1) FROM ' . IMAGES_TABLE;
        $query .= ' WHERE id = ' . $conn->db_real_escape_string($params['image_id']);
        list($count) = $conn->db_fetch_row($conn->db_query($query));
        if ($count == 0) {
            return new Phyxo\Ws\Error(404, 'image_id not found');
        }
    }

    include_once(PHPWG_ROOT_PATH . 'admin/include/functions_upload.inc.php');

    $image_id = add_uploaded_file(
        $_FILES['image']['tmp_name'],
        $_FILES['image']['name'],
        $params['category'],
        8,
        $params['image_id'] > 0 ? $params['image_id'] : null
    );

    $info_columns = array(
        'name',
        'author',
        'comment',
        'level',
        'date_creation',
    );

    $update = array();
    foreach ($info_columns as $key) {
        if (isset($params[$key])) {
            $update[$key] = $params[$key];
        }
    }

    $conn->single_update(
        IMAGES_TABLE,
        $update,
        array('id' => $image_id)
    );

    if (!empty($params['tags'])) {
        include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');

        $tag_ids = array();
        if (is_array($params['tags'])) {
            foreach ($params['tags'] as $tag_name) {
                $tag_ids[] = $services['tags']->tagIdFromTagName($tag_name);
            }
        } else {
            $tag_names = preg_split('~(?<!\\\),~', $params['tags']);
            foreach ($tag_names as $tag_name) {
                $tag_ids[] = $services['tags']->tagIdFromTagName(preg_replace('#\\\\*,#', ',', $tag_name));
            }
        }

        $services['tags']->addTags($tag_ids, array($image_id));
    }

    $url_params = array('image_id' => $image_id);

    if (!empty($params['category'])) {
        $query = 'SELECT id, name, permalink FROM ' . CATEGORIES_TABLE;
        $query .= ' WHERE id = ' . $conn->db_real_escape_string($params['category'][0]);
        $result = $conn->db_query($query);
        $category = $conn->db_fetch_assoc($result);

        $url_params['section'] = 'categories';
        $url_params['category'] = $category;
    }

    // update metadata from the uploaded file (exif/iptc), even if the sync
    // was already performed by add_uploaded_file().
    require_once(PHPWG_ROOT_PATH . 'admin/include/functions_metadata.php');
    sync_metadata(array($image_id));

    return array(
        'image_id' => $image_id,
        'url' => \Phyxo\Functions\URL::make_picture_url($url_params),
    );
}

/**
 * API method
 * Adds a image (simple way)
 * @param mixed[] $params
 *    @option int[] category
 *    @option string name (optional)
 *    @option string author (optional)
 *    @option string comment (optional)
 *    @option int level
 *    @option string|string[] tags
 *    @option int image_id (optional)
 */
function ws_images_upload($params, $service)
{
    global $conf, $conn;

    if (get_pwg_token() != $params['pwg_token']) {
        return new Phyxo\Ws\Error(403, 'Invalid security token');
    }

    $upload_dir = $conf['upload_dir'] . '/buffer';

    // create the upload directory tree if not exists
    if (!mkgetdir($upload_dir, MKGETDIR_DEFAULT & ~MKGETDIR_DIE_ON_ERROR)) {
        return new Phyxo\Ws\Error(500, 'error during buffer directory creation');
    }

    // Get a file name
    if (isset($_REQUEST["name"])) {
        $fileName = $_REQUEST["name"];
    } elseif (!empty($_FILES)) {
        $fileName = $_FILES["file"]["name"];
    } else {
        $fileName = uniqid("file_");
    }

    $filePath = $upload_dir . DIRECTORY_SEPARATOR . $fileName;

    // Chunking might be enabled
    $chunk = isset($_REQUEST["chunk"]) ? intval($_REQUEST["chunk"]) : 0;
    $chunks = isset($_REQUEST["chunks"]) ? intval($_REQUEST["chunks"]) : 0;

    // Open temp file
    if (!$out = @fopen("{$filePath}.part", $chunks ? "ab" : "wb")) {
        die('{"jsonrpc" : "2.0", "error" : {"code": 102, "message": "Failed to open output stream."}, "id" : "id"}');
    }

    if (!empty($_FILES)) {
        if ($_FILES["file"]["error"] || !is_uploaded_file($_FILES["file"]["tmp_name"])) {
            die('{"jsonrpc" : "2.0", "error" : {"code": 103, "message": "Failed to move uploaded file."}, "id" : "id"}');
        }

        // Read binary input stream and append it to temp file
        if (!$in = @fopen($_FILES["file"]["tmp_name"], "rb")) {
            die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');
        }
    } else {
        if (!$in = @fopen("php://input", "rb")) {
            die('{"jsonrpc" : "2.0", "error" : {"code": 101, "message": "Failed to open input stream."}, "id" : "id"}');
        }
    }

    while ($buff = fread($in, 4096)) {
        fwrite($out, $buff);
    }

    @fclose($out);
    @fclose($in);

    // Check if file has been uploaded
    if (!$chunks || $chunk == $chunks - 1) {
        // Strip the temp .part suffix off
        rename("{$filePath}.part", $filePath);

        include_once(PHPWG_ROOT_PATH . 'admin/include/functions_upload.inc.php');

        $image_id = add_uploaded_file(
            $filePath,
            $params['name'],
            $params['category'],
            $params['level'],
            null // image_id = not provided, this is a new photo
        );

        $query = 'SELECT id,name,representative_ext,path FROM ' . IMAGES_TABLE . ' WHERE id = ' . $image_id;
        $image_infos = $conn->db_fetch_assoc($conn->db_query($query));

        $query = 'SELECT COUNT(1) AS nb_photos FROM ' . IMAGE_CATEGORY_TABLE;
        $query .= ' WHERE category_id = ' . $conn->db_real_escape_string($params['category'][0]);
        $category_infos = $conn->db_fetch_assoc($conn->db_query($query));

        $category_name = get_cat_display_name_from_id($params['category'][0], null);

        return array(
            'image_id' => $image_id,
            'src' => \Phyxo\Image\DerivativeImage::thumb_url($image_infos),
            'name' => $image_infos['name'],
            'category' => array(
                'id' => $params['category'][0],
                'nb_photos' => $category_infos['nb_photos'],
                'label' => $category_name,
            )
        );
    }
}

/**
 * API method
 * Check if an image exists by it's name or md5 sum
 * @param mixed[] $params
 *    @option string md5sum_list (optional)
 *    @option string filename_list (optional)
 */
function ws_images_exist($params, $service)
{
    global $conf, $conn;

    ws_logfile(__FUNCTION__ . ' ' . var_export($params, true));

    $split_pattern = '/[\s,;\|]/';
    $result = array();

    if ('md5sum' == $conf['uniqueness_mode']) {
        // search among photos the list of photos already added, based on md5sum list
        $md5sums = preg_split(
            $split_pattern,
            $params['md5sum_list'],
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        $query = 'SELECT id, md5sum FROM ' . IMAGES_TABLE;
        $query .= ' WHERE md5sum ' . $conn->in($md5sums);
        $id_of_md5 = $conn->query2array($query, 'md5sum', 'id');

        foreach ($md5sums as $md5sum) {
            $result[$md5sum] = null;
            if (isset($id_of_md5[$md5sum])) {
                $result[$md5sum] = $id_of_md5[$md5sum];
            }
        }
    } elseif ('filename' == $conf['uniqueness_mode']) {
        // search among photos the list of photos already added, based on
        // filename list
        $filenames = preg_split(
            $split_pattern,
            $params['filename_list'],
            -1,
            PREG_SPLIT_NO_EMPTY
        );

        $query = 'SELECT id, file FROM ' . IMAGES_TABLE;
        $query .= ' WHERE file ' . $conn->in($filenames);
        $id_of_filename = $conn->query2array($query, 'file', 'id');

        foreach ($filenames as $filename) {
            $result[$filename] = null;
            if (isset($id_of_filename[$filename])) {
                $result[$filename] = $id_of_filename[$filename];
            }
        }
    }

    return $result;
}

/**
 * API method
 * Check is file has been update
 * @param mixed[] $params
 *    @option int image_id
 *    @option string file_sum
 */
function ws_images_checkFiles($params, $service)
{
    global $conn;

    ws_logfile(__FUNCTION__ . ', input :  ' . var_export($params, true));

    $query = 'SELECT path FROM ' . IMAGES_TABLE . ' WHERE id = ' . $conn->db_real_escape_string($params['image_id']);
    $result = $conn->db_query($query);

    if ($conn->db_num_rows($result) == 0) {
        return new Phyxo\Ws\Error(404, 'image_id not found');
    }

    list($path) = $conn->db_fetch_row($result);

    $ret = array();

    if (isset($params['thumbnail_sum'])) {
        // We always say the thumbnail is equal to create no reaction on the
        // other side. Since Piwigo 2.4 and derivatives, the thumbnails and web
        // sizes are always generated by Piwigo
        $ret['thumbnail'] = 'equals';
    }

    if (isset($params['high_sum'])) {
        $ret['file'] = 'equals';
        $compare_type = 'high';
    } elseif (isset($params['file_sum'])) {
        $compare_type = 'file';
    }

    if (isset($compare_type)) {
        ws_logfile(__FUNCTION__ . ', md5_file($path) = ' . md5_file($path));
        if (md5_file($path) != $params[$compare_type . '_sum']) {
            $ret[$compare_type] = 'differs';
        } else {
            $ret[$compare_type] = 'equals';
        }
    }

    ws_logfile(__FUNCTION__ . ', output :  ' . var_export($ret, true));

    return $ret;
}

/**
 * API method
 * Set list of related tags of an image
 * @param mixed[] $params
 *    @option bool sort_by_counter
 */
function ws_images_setRelatedTags($params, &$service)
{
    global $conf, $conn, $services;

    if (!$service->isPost()) {
        return new Phyxo\Ws\Error(405, "This method requires HTTP POST");
    }

    if ((empty($conf['tags_permission_add'])
        || !$services['users']->isAuthorizeStatus($services['users']->getAccessTypeStatus($conf['tags_permission_add']))) && (empty($conf['tags_permission_delete'])
        || !$services['users']->isAuthorizeStatus($services['users']->getAccessTypeStatus($conf['tags_permission_delete'])))) {
        return new Phyxo\Ws\Error(403, \Phyxo\Functions\Language::l10n('You are not allowed to add nor delete tags'));
    }

    include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');

    $message = '';
    if (empty($params['tags'])) {
        $params['tags'] = array();
    }

    $query = 'SELECT id FROM ' . TAGS_TABLE . ' AS t';
    $query .= ' LEFT JOIN ' . IMAGE_TAG_TABLE . ' AS it ON t.id = it.tag_id';
    $query .= ' WHERE image_id = ' . $conn->db_real_escape_string($params['image_id']);

    $removed_tags_ids = $new_tags_ids = array();
    $current_tags_ids = $conn->query2array($query, null, 'id');
    $current_tags = array_map(function ($id) {
        return '~~' . $id . '~~';
    }, $current_tags_ids);
    $removed_tags = array_diff($current_tags, $params['tags']);
    $new_tags = array_diff($params['tags'], $current_tags);

    if (count($removed_tags) > 0) {
        if (empty($conf['tags_permission_delete'])
            || !$services['users']->isAuthorizeStatus($services['users']->getAccessTypeStatus($conf['tags_permission_delete']))) {
            return new Phyxo\Ws\Error(403, \Phyxo\Functions\Language::l10n('You are not allowed to delete tags'));
        }
    }
    if (count($new_tags) > 0) {
        if (empty($conf['tags_permission_add'])
            || !$services['users']->isAuthorizeStatus($services['users']->getAccessTypeStatus($conf['tags_permission_add']))) {
            return new Phyxo\Ws\Error(403, \Phyxo\Functions\Language::l10n('You are not allowed to add tags'));
        }
    }

    try {
        if (empty($params['tags'])) { // remove all tags for an image
            if (isset($conf['delete_tags_immediately']) && $conf['delete_tags_immediately'] == 0) {
                $services['tags']->toBeValidatedTags(
                    $current_tags_ids,
                    $params['image_id'],
                    array('status' => 0, 'user_id' => $_SESSION['pwg_uid'])
                );
            } else {
                $query = 'DELETE FROM ' . IMAGE_TAG_TABLE;
                $query .= ' WHERE image_id = ' . $conn->db_real_escape_string($params['image_id']);
                $conn->db_query($query);
            }
        } else {
            // if publish_tags_immediately (or delete_tags_immediately) is not set we consider its value is 1
            if (count($removed_tags) > 0) {
                $removed_tags_ids = $services['tags']->getTagsIds($removed_tags);
                if (isset($conf['delete_tags_immediately']) && $conf['delete_tags_immediately'] == 0) {
                    $services['tags']->toBeValidatedTags(
                        $removed_tags_ids,
                        $params['image_id'],
                        array('status' => 0, 'user_id' => $_SESSION['pwg_uid'])
                    );
                } else {
                    $services['tags']->dissociateTags($removed_tags_ids, $params['image_id']);
                }
            }

            if (count($new_tags) > 0) {
                $new_tags_ids = $services['tags']->getTagsIds($new_tags);
                if (isset($conf['publish_tags_immediately']) && $conf['publish_tags_immediately'] == 0) {
                    $services['tags']->toBeValidatedTags(
                        $new_tags_ids,
                        $params['image_id'],
                        array('status' => 1, 'user_id' => $_SESSION['pwg_uid'])
                    );
                } else {
                    $services['tags']->associateTags($new_tags_ids, $params['image_id']);
                }
            }
        }
    } catch (Exception $e) {
        return new Phyxo\Ws\Error(500, '[ws_images_setRelatedTags]  Something went wrong when updating tags');
    }
}

/**
 * API method
 * Sets details of an image
 * @param mixed[] $params
 *    @option int image_id
 *    @option string file (optional)
 *    @option string name (optional)
 *    @option string author (optional)
 *    @option string date_creation (optional)
 *    @option string comment (optional)
 *    @option string categories (optional) - "cat_id[,rank];cat_id[,rank]"
 *    @option string tags_ids (optional) - "tag_id,tag_id"
 *    @option int level (optional)
 *    @option string single_value_mode
 *    @option string multiple_value_mode
 */
function ws_images_setInfo($params, $service)
{
    global $conn, $services;

    include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');

    $query = 'SELECT * FROM ' . IMAGES_TABLE;
    $query .= ' WHERE id = ' . $conn->db_real_escape_string($params['image_id']);
    $result = $conn->db_query($query);

    if ($conn->db_num_rows($result) == 0) {
        return new Phyxo\Ws\Error(404, 'image_id not found');
    }

    $image_row = $conn->db_fetch_assoc($result);

    // database registration
    $update = array();

    $info_columns = array(
        'name',
        'author',
        'comment',
        'level',
        'date_creation',
    );

    foreach ($info_columns as $key) {
        if (isset($params[$key])) {
            if ('fill_if_empty' == $params['single_value_mode']) {
                if (empty($image_row[$key])) {
                    $update[$key] = $params[$key];
                }
            } elseif ('replace' == $params['single_value_mode']) {
                $update[$key] = $params[$key];
            } else {
                return new Phyxo\Ws\Error(
                    500,
                    '[ws_images_setInfo]'
                        . ' invalid parameter single_value_mode "' . $params['single_value_mode'] . '"'
                        . ', possible values are {fill_if_empty, replace}.'
                );
            }
        }
    }

    if (isset($params['file'])) {
        if (!empty($image_row['storage_category_id'])) {
            return new Phyxo\Ws\Error(
                500,
                '[ws_images_setInfo] updating "file" is forbidden on photos added by synchronization'
            );
        }

        $update['file'] = $params['file'];
    }

    if (count(array_keys($update)) > 0) {
        $update['id'] = $params['image_id'];

        $conn->single_update(
            IMAGES_TABLE,
            $update,
            array('id' => $update['id'])
        );
    }

    if (isset($params['categories'])) {
        ws_add_image_category_relations(
            $params['image_id'],
            $params['categories'],
            ('replace' == $params['multiple_value_mode'] ? true : false)
        );
    }

    // and now, let's create tag associations
    if (isset($params['tag_ids'])) {
        $tag_ids = array();

        foreach (explode(',', $params['tag_ids']) as $candidate) {
            $candidate = trim($candidate);

            if (preg_match(PATTERN_ID, $candidate)) {
                $tag_ids[] = $candidate;
            }
        }

        if ('replace' == $params['multiple_value_mode']) {
            $services['tags']->setTags($tag_ids, $params['image_id']);
        } elseif ('append' == $params['multiple_value_mode']) {
            $services['tags']->addTags($tag_ids, array($params['image_id']));
        } else {
            return new Phyxo\Ws\Error(
                500,
                '[ws_images_setInfo]'
                    . ' invalid parameter multiple_value_mode "' . $params['multiple_value_mode'] . '"'
                    . ', possible values are {replace, append}.'
            );
        }
    }

    invalidate_user_cache();
}

/**
 * API method
 * Deletes an image
 * @param mixed[] $params
 *    @option int|int[] image_id
 *    @option string pwg_token
 */
function ws_images_delete($params, $service)
{
    if (get_pwg_token() != $params['pwg_token']) {
        return new Phyxo\Ws\Error(403, 'Invalid security token');
    }

    // @TODO: simplify !!!
    if (!is_array($params['image_id'])) {
        $params['image_id'] = preg_split(
            '/[\s,;\|]/',
            $params['image_id'],
            -1,
            PREG_SPLIT_NO_EMPTY
        );
    }
    $params['image_id'] = array_map('intval', $params['image_id']);

    $image_ids = array();
    foreach ($params['image_id'] as $image_id) {
        if ($image_id > 0) {
            $image_ids[] = $image_id;
        }
    }

    include_once(PHPWG_ROOT_PATH . 'admin/include/functions.php');
    $number_of_elements_deleted = delete_elements($image_ids, true);
    invalidate_user_cache();

    return $number_of_elements_deleted;
}

/**
 * API method
 * Checks if Piwigo is ready for upload
 * @param mixed[] $params
 */
function ws_images_checkUpload($params, $service)
{
    include_once(PHPWG_ROOT_PATH . 'admin/include/functions_upload.inc.php');

    $ret['message'] = ready_for_upload_message();
    $ret['ready_for_upload'] = true;
    if (!empty($ret['message'])) {
        $ret['ready_for_upload'] = false;
    }

    return $ret;
}
