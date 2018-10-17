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

namespace Phyxo\Functions;

use Phyxo\Block\RegisteredBlock;
use Phyxo\DBLayer\DBLayer;
use App\Repository\CommentRepository;
use App\Repository\ImageCategory;
use App\Repository\ImageCategoryRepository;
use App\Repository\UserCacheRepository;
use App\Repository\UserCacheCategoriesRepository;
use App\Repository\FavoriteRepository;
use App\Repository\RateRepository;
use App\Repository\ImageTagRepository;
use App\Repository\CaddieRepository;
use App\Repository\SiteRepository;
use App\Repository\CategoryRepository;
use App\Repository\UserFeedRepository;
use App\Repository\HistoryRepository;
use App\Repository\ImageRepository;
use App\Repository\UserMailNotificationRepository;
use App\Repository\GroupRepository;
use App\Repository\UserRepository;
use App\Repository\UserInfosRepository;
use App\Repository\UserAccessRepository;
use App\Repository\UserGroupRepository;

class Utils
{
    public static function phyxoInstalled(DBLayer $conn, $prefix = 'phyxo_')
    {
        $tables = $conn->db_get_tables($prefix);

        return (count($tables) > 0);
    }

    /** no option for mkgetdir() */
    const MKGETDIR_NONE = 0;
    /** sets mkgetdir() recursive */
    const MKGETDIR_RECURSIVE = 1;
    /** sets mkgetdir() exit script on error */
    const MKGETDIR_DIE_ON_ERROR = 2;
    /** sets mkgetdir() add a index.htm file */
    const MKGETDIR_PROTECT_INDEX = 4;
    /** sets mkgetdir() add a .htaccess file*/
    const MKGETDIR_PROTECT_HTACCESS = 8;
    /** default options for mkgetdir() = MKGETDIR_RECURSIVE | MKGETDIR_DIE_ON_ERROR | MKGETDIR_PROTECT_INDEX */
    const MKGETDIR_DEFAULT = self::MKGETDIR_RECURSIVE | self::MKGETDIR_DIE_ON_ERROR | self::MKGETDIR_PROTECT_INDEX;

    /**
     * Returns the path to use for the Piwigo cookie.
     * If Phyxo is installed on :
     * http://domain.org/meeting/gallery/
     * it will return : "/meeting/gallery"
     *
     * @return string
     */
    public static function cookie_path()
    {
        if (!empty($_SERVER['REDIRECT_SCRIPT_NAME'])) {
            $scr = $_SERVER['REDIRECT_SCRIPT_NAME'];
        } elseif (!empty($_SERVER['REDIRECT_URL'])) {
            // mod_rewrite is activated for upper level directories. we must set the
            // cookie to the path shown in the browser otherwise it will be discarded.
            if (!empty($_SERVER['PATH_INFO']) && ($_SERVER['REDIRECT_URL'] !== $_SERVER['PATH_INFO'])
                && (substr($_SERVER['REDIRECT_URL'], -strlen($_SERVER['PATH_INFO'])) == $_SERVER['PATH_INFO'])) {
                $scr = substr($_SERVER['REDIRECT_URL'], 0, strlen($_SERVER['REDIRECT_URL']) - strlen($_SERVER['PATH_INFO']));
            } else {
                $scr = $_SERVER['REDIRECT_URL'];
            }
        } else {
            $scr = $_SERVER['SCRIPT_NAME'];
        }

        $scr = substr($scr, 0, strrpos($scr, '/'));

        // add a trailing '/' if needed
        if ((strlen($scr) == 0) or ($scr {
            strlen($scr) - 1} !== '/')) {
            $scr .= '/';
        }

        if (substr(PHPWG_ROOT_PATH, 0, 3) == '../') { // this is maybe a plugin inside pwg directory
            // @TODO - what if it is an external script outside PWG ?
            $scr = $scr . PHPWG_ROOT_PATH;
            while (1) {
                $new = preg_replace('#[^/]+/\.\.(/|$)#', '', $scr);
                if ($new == $scr) {
                    break;
                }
                $scr = $new;
            }
        }

        return $scr;
    }

    /**
     * returns a float value coresponding to the number of seconds since
     * the unix epoch (1st January 1970) and the microseconds are precised
     * e.g. 1052343429.89276600
     *
     * @deprecated since 1.9.0 and will be removed in 1.10 or 2.0
     * @return float
     */
    public static function get_moment()
    {
        trigger_error('get_moment function is deprecated. Use microtime instead.', E_USER_DEPRECATED);

        return microtime(true);
    }

    /**
     * returns the number of seconds (with 3 decimals precision)
     * between the start time and the end time given
     *
     * @param float $start
     * @param float $end
     * @return string "$TIME s"
     */
    public static function get_elapsed_time($start, $end)
    {
        return number_format(($end - $start) * 1000, 2, '.', ' ') . ' ms';
    }

    /**
     * returns the part of the string after the last "."
     *
     * @param string $filename
     * @return string
     */
    public static function get_extension($filename)
    {
        return substr(strrchr($filename, '.'), 1, strlen($filename));
    }

    /**
     * returns the part of the string before the last ".".
     * get_filename_wo_extension( 'test.tar.gz' ) = 'test.tar'
     *
     * @param string $filename
     * @return string
     */
    public static function get_filename_wo_extension($filename)
    {
        $pos = strrpos($filename, '.');
        return ($pos === false) ? $filename : substr($filename, 0, $pos);
    }

    /**
     * returns the element name from its filename.
     * removes file extension and replace underscores by spaces
     *
     * @param string $filename
     * @return string name
     */
    public static function get_name_from_file($filename)
    {
        return str_replace('_', ' ', self::get_filename_wo_extension($filename));
    }

    /**
     * Return $conf['filter_pages'] value for the current page
     *
     * @param string $value_name
     * @return mixed
     */
    public static function get_filter_page_value($value_name)
    {
        global $conf;

        $page_name = self::script_basename();

        if (isset($conf['filter_pages'][$page_name][$value_name])) {
            return $conf['filter_pages'][$page_name][$value_name];
        } elseif (isset($conf['filter_pages']['default'][$value_name])) {
            return $conf['filter_pages']['default'][$value_name];
        } else {
            return null;
        }
    }

    /**
     * Transforms an original path to its pwg representative
     *
     * @param string $path
     * @param string $representative_ext
     * @return string
     */
    public static function original_to_representative($path, $representative_ext)
    {
        $pos = strrpos($path, '/');
        $path = substr_replace($path, 'pwg_representative/', $pos + 1, 0);
        $pos = strrpos($path, '.');
        return substr_replace($path, $representative_ext, $pos + 1);
    }

    /**
     * get the full path of an image
     *
     * @param array $element_info element information from db (at least 'path')
     * @return string
     */
    public static function get_element_path($element_info)
    {
        $path = $element_info['path'];
        if (!\Phyxo\Functions\URL::url_is_remote($path)) {
            $path = PHPWG_ROOT_PATH . $path;
        }
        return $path;
    }

    /**
     * Returns webmaster mail address depending on $conf['webmaster_id']
     *
     * @return string
     */
    public static function get_webmaster_mail_address()
    {
        global $conf, $conn;

        $result = (new UserRepository($conn))->findById($conf['webmaster_id']);
        $row = $conn->db_fetch_assoc($result);
        $email = $row['mail_address'];
        $email = \Phyxo\Functions\Plugin::trigger_change('get_webmaster_mail_address', $email);

        return $email;
    }

    /**
     * Prepends and appends strings at each value of the given array.
     *
     * @param array $array
     * @param string $prepend_str
     * @param string $append_str
     * @return array
     */
    public static function prepend_append_array_items($array, $prepend_str, $append_str)
    {
        array_walk(
            $array,
            function (&$s) use ($prepend_str, $append_str) {
                $s = $prepend_str . $s . $append_str;
            }
        );

        return $array;
    }

    /**
     * return the character set used by Phyxo
     * @return string
     */
    public static function get_charset()
    {
        $pwg_charset = 'utf-8';
        if (defined('PWG_CHARSET')) {
            $pwg_charset = PWG_CHARSET;
        }

        return $pwg_charset;
    }

    /**
     * converts a string from a character set to another character set
     *
     * @param string $str
     * @param string $source_charset
     * @param string $dest_charset
     */
    public static function convert_charset($str, $source_charset, $dest_charset)
    {
        if ($source_charset == $dest_charset) {
            return $str;
        }
        if ($source_charset == 'iso-8859-1' and $dest_charset == 'utf-8') {
            return utf8_encode($str);
        }
        if ($source_charset == 'utf-8' and $dest_charset == 'iso-8859-1') {
            return utf8_decode($str);
        }
        if (function_exists('iconv')) {
            return iconv($source_charset, $dest_charset, $str);
        }
        if (function_exists('mb_convert_encoding')) {
            return mb_convert_encoding($str, $dest_charset, $source_charset);
        }

        return $str; // TODO
    }

    /**
     * Return the basename of the current script.
     * The lowercase case filename of the current script without extension
     *
     * @return string
     */
    public static function script_basename()
    {
        global $conf;

        foreach (['SCRIPT_NAME', 'SCRIPT_FILENAME', 'PHP_SELF'] as $value) {
            if (!empty($_SERVER[$value])) {
                $filename = strtolower($_SERVER[$value]);
                if ($conf['php_extension_in_urls'] and self::get_extension($filename) !== 'php') {
                    continue;
                }
                $basename = basename($filename, '.php');
                if (!empty($basename)) {
                    return $basename;
                }
            }
        }

        return '';
    }

    /**
     * returns a "secret key" that is to be sent back when a user posts a form
     *
     * @param int $valid_after_seconds - key validity start time from now
     * @param string $aditionnal_data_to_hash
     * @return string
     */
    public static function get_ephemeral_key($valid_after_seconds, $aditionnal_data_to_hash = '')
    {
        global $conf;

        $time = round(microtime(true), 1);
        return $time . ':' . $valid_after_seconds . ':'
            . hash_hmac(
            'md5',
            $time . substr($_SERVER['REMOTE_ADDR'], 0, 5) . $valid_after_seconds . $aditionnal_data_to_hash,
            $conf['secret_key']
        );
    }

    /**
     * verify a key sent back with a form
     *
     * @param string $key
     * @param string $aditionnal_data_to_hash
     * @return bool
     */
    public static function verify_ephemeral_key($key, $aditionnal_data_to_hash = '')
    {
        global $conf;

        $time = microtime(true);
        $key = explode(':', @$key);

        // page must have been retrieved more than X sec ago
        if (count($key) != 3 or $key[0] > $time - (float)$key[1] or $key[0] < $time - 3600
            or hash_hmac(
            'md5',
            $key[0] . substr($_SERVER['REMOTE_ADDR'], 0, 5) . $key[1] . $aditionnal_data_to_hash,
            $conf['secret_key']
        ) != $key[2]) {
            return false;
        }

        return true;
    }

    /**
     * check token comming from form posted or get params to prevent csrf attacks.
     * if pwg_token is empty action doesn't require token
     * else pwg_token is compare to server token
     *
     * @return void access denied if token given is not equal to server token
     */
    public static function check_token()
    {
        if (!empty($_REQUEST['pwg_token'])) {
            if (self::get_token() != $_REQUEST['pwg_token']) {
                \Phyxo\Functions\HTTP::access_denied();
            }
        } else {
            \Phyxo\Functions\HTTP::bad_request('missing token');
        }
    }

    /**
     * get pwg_token used to prevent csrf attacks
     *
     * @return string
     */
    public static function get_token()
    {
        global $conf;

        return hash_hmac('md5', session_id(), $conf['secret_key']);
    }

    /**
     * creates directory if not exists and ensures that directory is writable
     *
     * @param string $dir
     * @param int $flags combination of MKGETDIR_xxx
     * @return bool
     */
    public static function mkgetdir($dir, $flags = self::MKGETDIR_NONE)
    {
        global $conf;

        if (!is_dir($dir)) {
            if (substr(PHP_OS, 0, 3) == 'WIN') {
                $dir = str_replace('/', DIRECTORY_SEPARATOR, $dir);
            }
            $umask = umask(0);
            $mkd = @mkdir($dir, $conf['chmod_value'], ($flags & self::MKGETDIR_RECURSIVE) ? true : false);
            umask($umask);
            if ($mkd == false) {
                !($flags & self::MKGETDIR_DIE_ON_ERROR) || \Phyxo\Functions\HTTP::fatal_error("$dir " . \Phyxo\Functions\Language::l10n('no write access'));
                return false;
            }
            if ($flags & self::MKGETDIR_PROTECT_HTACCESS) {
                $file = $dir . '/.htaccess';
                file_exists($file) or @file_put_contents($file, 'deny from all');
            }
            if ($flags & self::MKGETDIR_PROTECT_INDEX) {
                $file = $dir . '/index.htm';
                file_exists($file) or @file_put_contents($file, 'Not allowed!');
            }
        }
        if (!is_writable($dir)) {
            !($flags & self::MKGETDIR_DIE_ON_ERROR) || \Phyxo\Functions\HTTP::fatal_error("$dir " . \Phyxo\Functions\Language::l10n('no write access'));
            return false;
        }

        return true;
    }

    /**
     * log the visit into history table
     *
     * @param int $image_id
     * @param string $image_type
     * @return bool
     */
    public static function log($image_id = null, $image_type = null)
    {
        global $conf, $user, $page, $conn, $services;

        $do_log = $conf['log'];
        if ($services['users']->isAdmin()) {
            $do_log = $conf['history_admin'];
        }
        if ($services['users']->isGuest()) {
            $do_log = $conf['history_guest'];
        }

        $do_log = \Phyxo\Functions\Plugin::trigger_change('pwg_log_allowed', $do_log, $image_id, $image_type);

        if (!$do_log) {
            return false;
        }

        $tags_string = null;
        if (!empty($page['section']) && $page['section'] == 'tags') {
            $tags_string = implode(',', $page['tag_ids']);
        }

        (new HistoryRepository($conn))->addHistory(
            [
                'date' => 'CURRENT_DATE',
                'time' => 'CURRENT_TIME',
                'user_id' => $user['id'],
                'IP' => $_SERVER['REMOTE_ADDR'],
                'section' => $page['section'] ?? null,
                'category_id' => $page['category']['id'] ?? '',
                'image_id' => $image_id ?? '',
                'image_type' => $image_type ?? '',
                'tag_ids' => $tags_string ?? ''
            ]
        );

        return true;
    }

    /**
     * return an array which will be sent to template to display navigation bar
     *
     * @param string $url base url of all links
     * @param int $nb_elements
     * @param int $start
     * @param int $nb_element_page
     * @param bool $clean_url
     * @param string $param_name
     * @return array
     */
    public static function create_navigation_bar($url, $nb_element, $start, $nb_element_page, $clean_url = false, $param_name = 'start')
    {
        global $conf;

        $navbar = [];
        $pages_around = $conf['paginate_pages_around'];
        $start_str = $clean_url ? '/' . $param_name . '-' : (strpos($url, '?') === false ? '?' : '&amp;') . $param_name . '=';

        if (!isset($start) or !is_numeric($start) or (is_numeric($start) and $start < 0)) {
            $start = 0;
        }

        // navigation bar useful only if more than one page to display !
        if ($nb_element > $nb_element_page) {
            $url_start = $url . $start_str;

            $cur_page = $navbar['CURRENT_PAGE'] = $start / $nb_element_page + 1;
            $maximum = ceil($nb_element / $nb_element_page);

            $start = $nb_element_page * round($start / $nb_element_page);
            $previous = $start - $nb_element_page;
            $next = $start + $nb_element_page;
            $last = ($maximum - 1) * $nb_element_page;

            // link to first page and previous page?
            if ($cur_page != 1) {
                $navbar['URL_FIRST'] = $url;
                $navbar['URL_PREV'] = $previous > 0 ? $url_start . $previous : $url;
            }
            // link on next page and last page?
            if ($cur_page != $maximum) {
                $navbar['URL_NEXT'] = $url_start . ($next < $last ? $next : $last);
                $navbar['URL_LAST'] = $url_start . $last;
            }

            // pages to display
            $navbar['pages'] = [];
            $navbar['pages'][1] = $url;
            for ($i = max(floor($cur_page) - $pages_around, 2), $stop = min(ceil($cur_page) + $pages_around + 1, $maximum); $i < $stop; $i++) {
                $navbar['pages'][$i] = $url . $start_str . (($i - 1) * $nb_element_page);
            }
            $navbar['pages'][$maximum] = $url_start . $last;
            $navbar['NB_PAGE'] = $maximum;
        }

        return $navbar;
    }

    /**
     * return an array which will be sent to template to display recent icon
     *
     * @param string $date
     * @param bool $is_child_date
     * @return array
     */
    public static function get_icon($date, $is_child_date = false)
    {
        global $cache, $user, $conn;

        if (empty($date)) {
            return false;
        }

        if (!isset($cache['get_icon']['title'])) {
            $cache['get_icon']['title'] = \Phyxo\Functions\Language::l10n(
                'photos posted during the last %d days',
                $user['recent_period']
            );
        }

        $icon = [
            'TITLE' => $cache['get_icon']['title'],
            'IS_CHILD_DATE' => $is_child_date,
        ];

        if (isset($cache['get_icon'][$date])) {
            return $cache['get_icon'][$date] ? $icon : [];
        }

        if (!isset($cache['get_icon']['sql_recent_date'])) {
        // Use MySql date in order to standardize all recent "actions/queries" ???
            $cache['get_icon']['sql_recent_date'] = $conn->db_get_recent_period($user['recent_period']);
        }

        $cache['get_icon'][$date] = $date > $cache['get_icon']['sql_recent_date'];

        return $cache['get_icon'][$date] ? $icon : [];
    }

    /*
     * breaks the script execution if the given value doesn't match the given
     * pattern. This should happen only during hacking attempts.
     *
     * @param string $param_name
     * @param array $param_array
     * @param boolean $is_array
     * @param string $pattern
     * @param boolean $mandatory
     */
    public static function check_input_parameter($param_name, $param_array, $is_array, $pattern, $mandatory = false)
    {
        $param_value = null;
        if (isset($param_array[$param_name])) {
            $param_value = $param_array[$param_name];
        }

        // it's ok if the input parameter is null
        if (empty($param_value)) {
            if ($mandatory) {
                \Phyxo\Functions\HTTP::fatal_error('[Hacking attempt] the input parameter "' . $param_name . '" is not valid');
            }
            return true;
        }

        if ($is_array) {
            if (!is_array($param_value)) {
                \Phyxo\Functions\HTTP::fatal_error('[Hacking attempt] the input parameter "' . $param_name . '" should be an array');
            }

            foreach ($param_value as $item_to_check) {
                if (!preg_match($pattern, $item_to_check)) {
                    \Phyxo\Functions\HTTP::fatal_error('[Hacking attempt] an item is not valid in input parameter "' . $param_name . '"');
                }
            }
        } else {
            if (!preg_match($pattern, $param_value)) {
                \Phyxo\Functions\HTTP::fatal_error('[Hacking attempt] the input parameter "' . $param_name . '" is not valid');
            }
        }
    }

    /**
     * get localized privacy level values
     *
     * @return string[]
     */
    public static function get_privacy_level_options()
    {
        global $conf;

        $options = [];
        $label = '';
        foreach (array_reverse($conf['available_permission_levels']) as $level) {
            if (0 == $level) {
                $label = \Phyxo\Functions\Language::l10n('Everybody');
            } else {
                if (strlen($label)) {
                    $label .= ', ';
                }
                $label .= \Phyxo\Functions\Language::l10n(sprintf('Level %d', $level));
            }
            $options[$level] = $label;
        }

        return $options;
    }

    /**
     * return the branch from the version. For example version 2.2.4 is for branch 2.2
     *
     * @param string $version
     * @return string
     */
    public static function get_branch_from_version($version)
    {
        return implode('.', array_slice(explode('.', $version), 0, 2));
    }

    // http

    /**
     * Redirects to the given URL (HTTP method).
     * once this function called, the execution doesn't go further
     * (presence of an exit() instruction.
     *
     * @param string $url
     * @return void
     */
    public static function redirect_http($url)
    {
        if (ob_get_length() !== false) {
            ob_clean();
        }

        // default url is on html format
        $url = html_entity_decode($url);
        header('Request-URI: ' . $url);
        header('Content-Location: ' . $url);
        header('Location: ' . $url);
        exit();
    }

    /**
     * Redirects to the given URL
     * once this function called, the execution doesn't go further
     * (presence of an exit() instruction.
     *
     * @param string $url
     * @param string $msg
     * @param integer $refresh_time
     * @return void
     */
    public static function redirect($url, $msg = '', $refresh_time = 0)
    {
        if (!headers_sent()) {
            self::redirect_http($url);
        }
    }

    /**
     * check url format
     *
     * @param string $url
     * @return bool
     */
    public static function url_check_format($url)
    {
        return filter_var($url, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED | FILTER_FLAG_HOST_REQUIRED) !== false;
    }

    /**
     * check email format
     *
     * @param string $mail_address
     * @return bool
     */
    public static function email_check_format($mail_address)
    {
        return filter_var($mail_address, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * returns the number of available comments for the connected user
     *
     * @return int
     */
    public static function get_nb_available_comments()
    {
        global $user, $conn, $services;

        if (!isset($user['nb_available_comments'])) {
            $user['nb_available_comments'] = (new ImageCategoryRepository($conn))->countAvailableComments($services['users']->isAdmin());

            (new UserCacheRepository($conn))->updateUserCache(
                ['nb_available_comments' => $user['nb_available_comments']],
                ['user_id' => $user['id']]
            );
        }

        return $user['nb_available_comments'];
    }

    /**
     * Compare two versions with version_compare after having converted
     * single chars to their decimal values.
     * Needed because version_compare does not understand versions like '2.5.c'.
     *
     * @param string $a
     * @param string $b
     * @param string $op
     *
     * @deprecated since 1.9.0 and will be removed in 1.10 or 2.0
     */
    public static function safe_version_compare($a, $b, $op = null)
    {
        trigger_error('safe_version_compare function is deprecated. Use version_compare instead.', E_USER_DEPRECATED);

        $replace_chars = function ($m) {
            return ord(strtolower($m[1]));
        };

        // add dot before groups of letters (version_compare does the same thing)
        $a = preg_replace('#([0-9]+)([a-z]+)#i', '$1.$2', $a);
        $b = preg_replace('#([0-9]+)([a-z]+)#i', '$1.$2', $b);

        // apply ord() to any single letter
        $a = preg_replace_callback('#\b([a-z]{1})\b#i', $replace_chars, $a);
        $b = preg_replace_callback('#\b([a-z]{1})\b#i', $replace_chars, $b);

        if (empty($op)) {
            return version_compare($a, $b);
        } else {
            return version_compare($a, $b, $op);
        }
    }

    /**
     * Callback used for sorting by global_rank
     */
    public static function global_rank_compare($a, $b)
    {
        return strnatcasecmp($a['global_rank'], $b['global_rank']);
    }

    /**
     * Callback used for sorting by rank
     */
    public static function rank_compare($a, $b)
    {
        return $a['rank'] - $b['rank'];
    }

    /**
     * Callback used for sorting by name.
     */
    public static function name_compare($a, $b)
    {
        return strcmp(strtolower($a['name']), strtolower($b['name']));
    }

    /**
     * Callback used for sorting by name (slug) with cache.
     */
    public static function tag_alpha_compare($a, $b)
    {
        global $cache;

        foreach ([$a, $b] as $tag) {
            if (!isset($cache[__FUNCTION__][$tag['name']])) {
                $cache[__FUNCTION__][$tag['name']] = \Phyxo\Functions\Language::transliterate($tag['name']);
            }
        }

        return strcmp($cache[__FUNCTION__][$a['name']], $cache[__FUNCTION__][$b['name']]);
    }

    /**
     * Is the category accessible to the connected user ?
     * If the user is not authorized to see this category, script exits
     *
     * @param int $category_id
     */
    public static function check_restrictions($category_id)
    {
        global $user;

        // $filter['visible_categories'] and $filter['visible_images']
       // are not used because it's not necessary (filter <> restriction)
        if (in_array($category_id, explode(',', $user['forbidden_categories']))) {
            \Phyxo\Functions\HTTP::access_denied();
        }
    }

    /**
     * Apply basic markdown formations to a text.
     * newlines becomes br tags
     * _word_ becomes underline
     * /word/ becomes italic
     * *word* becomes bolded
     * urls becomes a tags
     *
     * @param string $content
     * @return string
     */
    public static function render_comment_content($content)
    {
        $content = htmlspecialchars($content);
        $pattern = '/(https?:\/\/\S*)/';
        $replacement = '<a href="$1" rel="nofollow">$1</a>';
        $content = preg_replace($pattern, $replacement, $content);

        $content = nl2br($content);

        // replace _word_ by an underlined word
        $pattern = '/\b_(\S*)_\b/';
        $replacement = '<span style="text-decoration:underline;">$1</span>';
        $content = preg_replace($pattern, $replacement, $content);

        // replace *word* by a bolded word
        $pattern = '/\b\*(\S*)\*\b/';
        $replacement = '<span style="font-weight:bold;">$1</span>';
        $content = preg_replace($pattern, $replacement, $content);

        // replace /word/ by an italic word
        $pattern = "/\/(\S*)\/(\s)/";
        $replacement = '<span style="font-style:italic;">$1$2</span>';
        $content = preg_replace($pattern, $replacement, $content);

        // @TODO : add a trigger

        return $content;
    }

    /**
     * Returns the breadcrumb to be displayed above thumbnails on tag page.
     *
     * @return string
     */
    public static function get_tags_content_title()
    {
        global $page;

        $title = '<a href="' . \Phyxo\Functions\URL::get_root_url() . 'tags.php" title="' . \Phyxo\Functions\Language::l10n('display available tags') . '">';
        $title .= \Phyxo\Functions\Language::l10n(count($page['tags']) > 1 ? 'Tags' : 'Tag');
        $title .= '</a>&nbsp;';

        for ($i = 0; $i < count($page['tags']); $i++) {
            $title .= $i > 0 ? ' + ' : '';
            $title .= '<a href="' . \Phyxo\Functions\URL::make_index_url(['tags' => [$page['tags'][$i]]]) . '"';
            $title .= ' title="' . \Phyxo\Functions\Language::l10n('display photos linked to this tag') . '">';
            $title .= \Phyxo\Functions\Plugin::trigger_change('render_tag_name', $page['tags'][$i]['name'], $page['tags'][$i]);
            $title .= '</a>';

            if (count($page['tags']) > 2) {
                $other_tags = $page['tags'];
                unset($other_tags[$i]);
                $remove_url = \Phyxo\Functions\URL::make_index_url(['tags' => $other_tags]);

                $title .= '<a href="' . $remove_url . '" style="border:none;" title="';
                $title .= \Phyxo\Functions\Language::l10n('remove this tag from the list');
                $title .= '"><img src="';
                $title .= \Phyxo\Functions\URL::get_root_url() . \Phyxo\Functions\Theme::get_themeconf('icon_dir') . '/remove_s.png';
                $title .= '" alt="x" style="vertical-align:bottom;">';
                $title .= '</a>';
            }
        }

        return $title;
    }

    /**
     * Add known menubar blocks.
     * This method is called by a trigger_change()
     *
     * @param BlockManager[] $menu_ref_arr
     */
    public static function register_default_menubar_blocks($menu_ref_arr)
    {
        $menu = &$menu_ref_arr[0];
        if ($menu->get_id() != 'menubar') {
            return;
        }
        $menu->register_block(new RegisteredBlock('mbLinks', 'Links', 'core'));
        $menu->register_block(new RegisteredBlock('mbCategories', 'Albums', 'core'));
        $menu->register_block(new RegisteredBlock('mbTags', 'Related tags', 'core'));
        $menu->register_block(new RegisteredBlock('mbSpecials', 'Specials', 'core'));
        $menu->register_block(new RegisteredBlock('mbMenu', 'Menu', 'core'));
        $menu->register_block(new RegisteredBlock('mbIdentification', 'Identification', 'core'));
    }

    /**
     * Returns display name for an element.
     * Returns 'name' if exists of name from 'file'.
     *
     * @param array $info at least file or name
     * @return string
     */
    public static function render_element_name($info)
    {
        if (!empty($info['name'])) {
            return \Phyxo\Functions\Plugin::trigger_change('render_element_name', $info['name']);
        }

        return \Phyxo\Functions\Utils::get_name_from_file($info['file']);
    }

    /**
     * Returns display description for an element.
     *
     * @param array $info at least comment
     * @param string $param used to identify the trigger
     * @return string
     */
    public static function render_element_description($info, $param = '')
    {
        if (!empty($info['comment'])) {
            return \Phyxo\Functions\Plugin::trigger_change('render_element_description', $info['comment'], $param);
        }

        return '';
    }

    /**
     * Add info to the title of the thumbnail based on photo properties.
     *
     * @param array $info hit, rating_score, nb_comments
     * @param string $title
     * @param string $comment
     * @return string
     */
    public static function get_thumbnail_title($info, $title, $comment = '')
    {
        global $conf, $user;

        $details = [];

        if (!empty($info['hit'])) {
            $details[] = $info['hit'] . ' ' . strtolower(\Phyxo\Functions\Language::l10n('Visits'));
        }

        if ($conf['rate'] and !empty($info['rating_score'])) {
            $details[] = strtolower(\Phyxo\Functions\Language::l10n('Rating score')) . ' ' . $info['rating_score'];
        }

        if (isset($info['nb_comments']) and $info['nb_comments'] != 0) {
            $details[] = \Phyxo\Functions\Language::l10n_dec('%d comment', '%d comments', $info['nb_comments']);
        }

        if (count($details) > 0) {
            $title .= ' (' . implode(', ', $details) . ')';
        }

        if (!empty($comment)) {
            $comment = strip_tags($comment);
            $title .= ' ' . substr($comment, 0, 100) . (strlen($comment) > 100 ? '...' : '');
        }

        $title = htmlspecialchars(strip_tags($title));
        $title = \Phyxo\Functions\Plugin::trigger_change('get_thumbnail_title', $title, $info);

        return $title;
    }

    /**
     * Sends to the template all messages stored in $page and in the session.
     */
    public static function flush_page_messages()
    {
        global $template, $page;

        if ($template->get_template_vars('page_refresh') === null) {
            foreach (['errors', 'infos', 'warnings'] as $mode) {
                if (isset($_SESSION['page_' . $mode])) {
                    $page[$mode] = array_merge($page[$mode], $_SESSION['page_' . $mode]);
                    unset($_SESSION['page_' . $mode]);
                }

                if (count($page[$mode]) != 0) {
                    $template->assign($mode, $page[$mode]);
                }
            }
        }
    }

    /**
     * Returns an array associating element id (images.id) with its complete
     * path in the filesystem
     *
     * @param int $category_id
     * @param int $site_id
     * @param boolean $recursive
     * @param boolean $only_new
     * @return array
     */
    public static function get_filelist(? int $category_id = null, $site_id = 1, $recursive = false, $only_new = false)
    {
        global $conn;

        // filling $cat_ids : all categories required
        $cat_ids = [];

        $result = (new CategoryRepository($conn))->findPhysicalsBySiteAndIdOrUppercats($site_id, $category_id, $recursive);
        while ($row = $conn->db_fetch_assoc($result)) {
            $cat_ids[] = $row['id'];
        }

        if (count($cat_ids) == 0) {
            return [];
        }

        $result = (new ImageRepository($conn))->findByStorageCategoryId($cat_ids, $only_new);

        return $conn->result2array($result, 'id');
    }

    /**
     * Returns slideshow default params.
     * - period
     * - repeat
     * - play
     *
     * @return array
     */
    public static function get_default_slideshow_params()
    {
        global $conf;

        return [
            'period' => $conf['slideshow_period'],
            'repeat' => $conf['slideshow_repeat'],
            'play' => true,
        ];
    }

    /**
     * Checks and corrects slideshow params
     *
     * @param array $params
     * @return array
     */
    public static function correct_slideshow_params($params = [])
    {
        global $conf;

        if ($params['period'] < $conf['slideshow_period_min']) {
            $params['period'] = $conf['slideshow_period_min'];
        } elseif ($params['period'] > $conf['slideshow_period_max']) {
            $params['period'] = $conf['slideshow_period_max'];
        }

        return $params;
    }

    /**
     * Decodes slideshow string params into array
     *
     * @param string $encode_params
     * @return array
     */
    public static function decode_slideshow_params($encode_params = null)
    {
        global $conf, $conn;

        $result = self::get_default_slideshow_params();

        if (is_numeric($encode_params)) {
            $result['period'] = $encode_params;
        } else {
            $matches = [];
            if (preg_match_all('/([a-z]+)-(\d+)/', $encode_params, $matches)) {
                $matchcount = count($matches[1]);
                for ($i = 0; $i < $matchcount; $i++) {
                    $result[$matches[1][$i]] = $matches[2][$i];
                }
            }

            if (preg_match_all('/([a-z]+)-(true|false)/', $encode_params, $matches)) {
                $matchcount = count($matches[1]);
                for ($i = 0; $i < $matchcount; $i++) {
                    $result[$matches[1][$i]] = $conn->get_boolean($matches[2][$i]);
                }
            }
        }

        return self::correct_slideshow_params($result);
    }

    /**
     * Encodes slideshow array params into a string
     *
     * @param array $decode_params
     * @return string
     */
    public static function encode_slideshow_params($decode_params = [])
    {
        global $conf, $conn;

        $params = array_diff_assoc(self::correct_slideshow_params($decode_params), self::get_default_slideshow_params());
        $result = '';

        foreach ($params as $name => $value) {
            // boolean_to_string return $value, if it's not a bool
            $result .= '+' . $name . '-' . $conn->boolean_to_string($value);
        }

        return $result;
    }

    /**
     * Generates a pseudo random string.
     * Characters used are a-z A-Z and numerical values.
     *
     * @param int $size
     * @return string
     */
    public static function generate_key($size)
    {
        return substr(
            str_replace(
                ['+', '/'],
                '',
                base64_encode(openssl_random_pseudo_bytes($size))
            ),
            0,
            $size
        );
    }

    /**
     * Deletes a site and call delete_categories for each primary category of the site
     *
     * @param int $id
     */
    public static function delete_site($id)
    {
        global $conn;

        // destruction of the categories of the site
        $result = (new CategoryRepository($conn))->findByField('site_id', $id);
        $category_ids = $conn->result2array($result, null, 'id');
        \Phyxo\Functions\Category::delete_categories($category_ids);

        // destruction of the site
        (new SiteRepository($conn))->deleteSite($id);
    }

    /**
     * Deletes all files (on disk) related to given image ids.
     *
     * @param int[] $ids
     * @return 0|int[] image ids where files were successfully deleted
     */
    public static function delete_element_files($ids)
    {
        global $conf, $conn;

        if (count($ids) == 0) {
            return 0;
        }

        $new_ids = [];
        $result = (new ImageRepository($conn))->findByIds($ids);
        while ($row = $conn->db_fetch_assoc($result)) {
            if (\Phyxo\Functions\URL::url_is_remote($row['path'])) {
                continue;
            }

            $files = [];
            $files[] = \Phyxo\Functions\Utils::get_element_path($row);

            if (!empty($row['representative_ext'])) {
                $files[] = \Phyxo\Functions\Utils::original_to_representative($files[0], $row['representative_ext']);
            }

            $ok = true;
            if (!isset($conf['never_delete_originals'])) {
                foreach ($files as $path) {
                    if (is_file($path) and !unlink($path)) {
                        $ok = false;
                        trigger_error('"' . $path . '" cannot be removed', E_USER_WARNING);
                        break;
                    }
                }
            }

            if ($ok) {
                self::delete_element_derivatives($row);
                $new_ids[] = $row['id'];
            } else {
                break;
            }
        }

        return $new_ids;
    }

    /**
     * Deletes elements from database.
     * It also deletes :
     *    - all the comments related to elements
     *    - all the links between categories/tags and elements
     *    - all the favorites/rates associated to elements
     *    - removes elements from caddie
     *
     * @param int[] $ids
     * @param bool $physical_deletion
     * @return int number of deleted elements
     */
    public static function delete_elements($ids, $physical_deletion = false)
    {
        global $conn;

        if (count($ids) == 0) {
            return 0;
        }
        \Phyxo\Functions\Plugin::trigger_notify('begin_delete_elements', $ids);

        if ($physical_deletion) {
            $ids = self::delete_element_files($ids);
            if (count($ids) == 0) {
                return 0;
            }
        }

        // destruction of the comments on the image
        (new CommentRepository($conn))->deleteByImage($ids);

        // destruction of the links between images and categories
        (new ImageCategoryRepository($conn))->deleteBy('image_id', $ids);

        // destruction of the links between images and tags
        (new ImageTagRepository($conn))->deleteBy('image_id', $ids);

        // destruction of the favorites associated with the picture
        (new FavoriteRepository($conn))->deleteImagesFromFavorite($ids);

        // destruction of the rates associated to this element
        (new RateRepository($conn))->deleteByElementIds($ids);

        // destruction of the caddie associated to this element
        (new CaddieRepository($conn))->deleteElements($ids);

        // destruction of the image
        (new ImageRepository($conn))->deleteByElementIds($ids);

        // are the photo used as category representant?
        $result = (new CategoryRepository($conn))->findRepresentants($ids);
        $category_ids = $conn->query2array($query, null, 'id');
        if (count($category_ids) > 0) {
            \Phyxo\Functions\Category::update_category($category_ids);
        }

        \Phyxo\Functions\Plugin::trigger_notify('delete_elements', $ids);

        return count($ids);
    }

    /**
     * Deletes an user.
     * It also deletes all related data (accesses, favorites, permissions, etc.)
     * @todo : accept array input
     *
     * @param int $user_id
     */
    public static function delete_user($user_id)
    {
        global $conf, $conn;

        // destruction of the group links for this user
        (new UserGroupRepository($conn))->deleteByUserId($user_id);
        // destruction of the access linked to the user(new UserAccessRepository($conn))->deleteByUserId($user_id);
        // deletion of phyxo specific informations
        (new UserInfosRepository($conn))->deleteByUserId($user_id);
        // destruction of data notification by mail for this user
        (new UserMailNotificationRepository($conn))->deleteByUserId($user_id);
        // destruction of data RSS notification for this user
        (new UserFeedRepository($conn))->deleteUserOnes($user_id);
        // deletion of calculated permissions linked to the user
        (new UserCacheRepository($conn))->deleteUserCache($user_id);
        // deletion of computed cache data linked to the user
        (new UserCacheCategoriesRepository($conn))->deleteUserCacheCategories($user_id);
        // destruction of the favorites associated with the user
        (new FavoriteRepository($conn))->removeAllFavorites($user_id);
        // destruction of the caddie associated with the user
        (new CaddieRepository($conn))->emptyCaddie($user_id);

        // purge of sessions
        $query = 'DELETE FROM ' . SESSIONS_TABLE . ' WHERE data LIKE \'pwg_uid|i:' . (int)$user_id . ';%\';';
        $conn->db_query($query);

        // destruction of the user
        (new UserRepository($conn))->deleteById($user_id);

        \Phyxo\Functions\Plugin::trigger_notify('delete_user', $user_id);
    }

    /**
     * Checks and repairs IMAGE_CATEGORY_TABLE integrity.
     * Removes all entries from the table which correspond to a deleted image.
     */
    public static function images_integrity()
    {
        global $conn;

        $result = (new ImageRepository($conn))->findOrphanImages();
        $orphan_image_ids = $conn->result2array($result, null, 'image_id');

        if (count($orphan_image_ids) > 0) {
            (new ImageCategoryRepository($conn))->deleteByCategory([], $orphan_image_ids);
        }
    }

    /**
     * Returns an array containing sub-directories which are potentially
     * a category.
     * Directories named ".svn", "thumbnail", "pwg_high" or "pwg_representative"
     * are omitted.
     *
     * @param string $basedir (eg: ./galleries)
     * @return string[]
     */
    public static function get_fs_directories($path, $recursive = true)
    {
        global $conf;

        $dirs = [];
        $path = rtrim($path, '/');

        $exclude_folders = array_merge(
            $conf['sync_exclude_folders'],
            [
                '.', '..', '.svn',
                'thumbnail', 'pwg_high',
                'pwg_representative',
            ]
        );
        $exclude_folders = array_flip($exclude_folders);


        // @TODO: use glob !!!
        if (is_dir($path)) {
            if ($contents = opendir($path)) {
                while (($node = readdir($contents)) !== false) {
                    if (is_dir($path . '/' . $node) and !isset($exclude_folders[$node])) {
                        $dirs[] = $path . '/' . $node;
                        if ($recursive) {
                            $dirs = array_merge($dirs, self::get_fs_directories($path . '/' . $node));
                        }
                    }
                }
                closedir($contents);
            }
        }

        return $dirs;
    }

    /**
     * Orders categories (update categories.rank and global_rank database fields)
     * so that rank field are consecutive integers starting at 1 for each child.
     */
    public static function update_global_rank()
    {
        global $cat_map, $conn;

        $cat_map = [];
        $current_rank = 0;
        $current_uppercat = '';

        $result = (new CategoryRepository($conn))->findAll('id_uppercat, rank, name');
        while ($row = $conn->db_fetch_assoc($result)) {
            if ($row['id_uppercat'] != $current_uppercat) {
                $current_rank = 0;
                $current_uppercat = $row['id_uppercat'];
            }
            ++$current_rank;
            $cat = [
                'rank' => $current_rank,
                'rank_changed' => $current_rank != $row['rank'],
                'global_rank' => $row['global_rank'],
                'uppercats' => $row['uppercats'],
            ];
            $cat_map[$row['id']] = $cat;
        }

        $datas = [];

        $cat_map_callback = function ($m) use ($cat_map) {
            return $cat_map[$m[1]]['rank'];
        };

        foreach ($cat_map as $id => $cat) {
            $new_global_rank = preg_replace_callback(
                '/(\d+)/',
                $cat_map_callback,
                str_replace(',', '.', $cat['uppercats'])
            );

            if ($cat['rank_changed'] || $new_global_rank != $cat['global_rank']) {
                $datas[] = [
                    'id' => $id,
                    'rank' => $cat['rank'],
                    'global_rank' => $new_global_rank,
                ];
            }
        }

        unset($cat_map);

        (new CategoryRepository($conn))->massUpdatesCategories(
            [
                'primary' => ['id'],
                'update' => ['rank', 'global_rank']
            ],
            $datas
        );

        return count($datas);
    }

    /**
     * Set a new random representant to the categories.
     *
     * @param int[] $categories
     */
    public static function set_random_representant($categories)
    {
        global $conn;

        $datas = [];
        foreach ($categories as $category_id) {
            $result = (new ImageCategoryRepository($conn))->findRandomRepresentant($category_id);
            list($representative) = $conn->db_fetch_row($result);

            $datas[] = [
                'id' => $category_id,
                'representative_picture_id' => $representative,
            ];
        }

        (new CategoryRepository($conn))->massUpdatesCategories(
            [
                'primary' => ['id'],
                'update' => ['representative_picture_id']
            ],
            $datas
        );
    }

    /**
     * Returns an array with all file system files according to $conf['file_ext']
     *
     * @param string $path
     * @param bool $recursive
     * @return array
     */
    public static function get_fs($path, $recursive = true)
    {
        global $conf;

        // because isset is faster than in_array...
        if (!isset($conf['flip_picture_ext'])) {
            $conf['flip_picture_ext'] = array_flip($conf['picture_ext']);
        }
        if (!isset($conf['flip_file_ext'])) {
            $conf['flip_file_ext'] = array_flip($conf['file_ext']);
        }

        $fs['elements'] = [];
        $fs['thumbnails'] = [];
        $fs['representatives'] = [];
        $subdirs = [];

        // @TODO: use glob
        if (is_dir($path)) {
            if ($contents = opendir($path)) {
                while (($node = readdir($contents)) !== false) {
                    if ($node == '.' or $node == '..') {
                        continue;
                    }

                    if (is_file($path . '/' . $node)) {
                        $extension = \Phyxo\Functions\Utils::get_extension($node);

                        if (isset($conf['flip_picture_ext'][$extension])) {
                            if (basename($path) == 'thumbnail') {
                                $fs['thumbnails'][] = $path . '/' . $node;
                            } elseif (basename($path) == 'pwg_representative') {
                                $fs['representatives'][] = $path . '/' . $node;
                            } else {
                                $fs['elements'][] = $path . '/' . $node;
                            }
                        } elseif (isset($conf['flip_file_ext'][$extension])) {
                            $fs['elements'][] = $path . '/' . $node;
                        }
                    } elseif (is_dir($path . '/' . $node) and $node != 'pwg_high' and $recursive) {
                        $subdirs[] = $node;
                    }
                }
            }
            closedir($contents);

            foreach ($subdirs as $subdir) {
                $tmp_fs = self::get_fs($path . '/' . $subdir);
                $fs['elements'] = array_merge($fs['elements'], $tmp_fs['elements']);
                $fs['thumbnails'] = array_merge($fs['thumbnails'], $tmp_fs['thumbnails']);
                $fs['representatives'] = array_merge($fs['representatives'], $tmp_fs['representatives']);
            }
        }

        return $fs;
    }

    /**
     * Synchronize base users list and related users list.
     *
     * Compares and synchronizes base users table (USERS_TABLE) with its child
     * tables (USER_INFOS_TABLE, USER_ACCESS, USER_CACHE, USER_GROUP) : each
     * base user must be present in child tables, users in child tables not
     * present in base table must be deleted.
     */
    public static function sync_users()
    {
        global $conf, $conn, $services;

        $result = (new UserRepository($conn))->findAll();
        $base_users = $conn->result2array($result, null, 'id');

        $result = (new UserInfosRepository($conn))->findAll();
        $infos_users = $conn->result2array($result, null, 'user_id');

        // users present in $base_users and not in $infos_users must be added
        $to_create = array_diff($base_users, $infos_users);

        if (count($to_create) > 0) {
            $services['users']->createUserInfos($to_create);
        }

        // users present in user related tables must be present in the base user table
        $Repositories = [
            'UserInfosRepository', 'UserMailNotificationRepository', 'UserFeedRepository',
            'UserCacheRepository', 'UserCacheCategoriesRepository', 'UserAccessRepository', 'UserGroupRepository'
        ];

        foreach ($Repositories as $repository) {
            $to_delete = array_diff(
                $conn->result2array((new $repository($conn))->getDistinctUser(), null, 'user_id'),
                $base_users
            );
            if (count($to_delete) > 0) {
                (new $repository($conn))->deleteByUserIds($to_delete);
            }
        }
    }

    /**
     * Update images.path field base on images.file and storage categories fulldirs.
     */
    public static function update_path()
    {
        global $conn;

        $result = (new ImageRepository($conn))->findDistinctStorageId();
        $cat_ids = $conn->result2array($result, null, 'storage_category_id');
        $fulldirs = \Phyxo\Functions\Category::get_fulldirs($cat_ids);

        foreach ($cat_ids as $cat_id) { // @TODO : use mass_updates ?
            (new ImageRepository($conn))->updatePathByStorageId($fulldirs[$cat_id], $cat_id);
        }
    }

    /**
     * Invalidates cached data (permissions and category counts) for all users.
     */
    public static function invalidate_user_cache($full = true)
    {
        global $conn;

        if ($full) {
            (new UserCacheRepository($conn))->deleteUserCache();
            (new UserCacheCategoriesRepository($conn))->deleteUserCacheCategories();
        } else {
            (new UserCacheRepository($conn))->updateUserCache(['need_update' => true]);
        }
        \Phyxo\Functions\Plugin::trigger_notify('invalidate_user_cache', $full);
    }

    /**
     * Invalidates cached tags counter for all users.
     */
    public static function invalidate_user_cache_nb_tags()
    {
        global $user, $conn;

        unset($user['nb_available_tags']);

        (new UserCacheRepository($conn))->updateUserCache(['nb_available_tags' => null]);
    }

    /**
     * Returns the groupname corresponding to the given group identifier if exists.
     *
     * @param int $group_id
     * @return string|false
     */
    public static function get_groupname($group_id)
    {
        global $conn;

        $result = (new GroupRepository($conn))->findById($group_id);
        if ($conn->db_num_rows($result) > 0) {
            $row = $conn->db_fetch_assoc($result);

            return $row['name'];
        } else {
            return false;
        }
    }

    /**
     * Returns the username corresponding to the given user identifier if exists.
     *
     * @param int $user_id
     * @return string|false
     */
    public static function get_username($user_id)
    {
        global $conf, $conn;

        $result = (new UserRepository($conn))->findById($user_id);
        if ($conn->db_num_rows($result) > 0) {
            $row = $conn->db_fetch_assoc($result);

            return $row['username'];
        } else {
            return false;
        }
    }

    /**
     * Returns the argument_ids array with new sequenced keys based on related
     * names. Sequence is not case sensitive.
     * Warning: By definition, this function breaks original keys.
     *
     * @param int[] $elements_ids
     * @param string[] $name - names of elements, indexed by ids
     * @return int[]
     */
    public static function order_by_name($element_ids, $name)
    {
        $ordered_element_ids = [];
        foreach ($element_ids as $k_id => $element_id) {
            $key = strtolower($name[$element_id]) . '-' . $name[$element_id] . '-' . $k_id;
            $ordered_element_ids[$key] = $element_id;
        }
        ksort($ordered_element_ids);
        return $ordered_element_ids;
    }

    /**
     * Returns the list of admin users.
     *
     * @param boolean $include_webmaster
     * @return int[]
     */
    public static function get_admins($include_webmaster = true)
    {
        global $conn;

        $status_list = ['admin'];

        if ($include_webmaster) {
            $status_list[] = 'webmaster';
        }

        $result = (new UserInfosRepository($conn))->findByStatuses($status_list);

        return $conn->result2array($result, null, 'user_id');
    }

    /**
     * Delete all derivative files for one or several types
     *
     * @param 'all'|int[] $types
     */
    public static function clear_derivative_cache($types = 'all')
    {
        if ($types === 'all') {
            $types = \Phyxo\Image\ImageStdParams::get_all_types();
            $types[] = IMG_CUSTOM;
        } elseif (!is_array($types)) {
            $types = [$types];
        }

        for ($i = 0; $i < count($types); $i++) {
            $type = $types[$i];
            if ($type == IMG_CUSTOM) {
                $type = \Phyxo\Image\DerivativeParams::derivative_to_url($type) . '[a-zA-Z0-9]+';
            } elseif (in_array($type, \Phyxo\Image\ImageStdParams::get_all_types())) {
                $type = \Phyxo\Image\DerivativeParams::derivative_to_url($type);
            } else { //assume a custom type
                $type = \Phyxo\Image\DerivativeParams::derivative_to_url(IMG_CUSTOM) . '_' . $type;
            }
            $types[$i] = $type;
        }

        $pattern = '#.*-';
        if (count($types) > 1) {
            $pattern .= '(' . implode('|', $types) . ')';
        } else {
            $pattern .= $types[0];
        }
        $pattern .= '\.[a-zA-Z0-9]{3,4}$#';

        // @TODO: use glob
        if ($contents = @opendir(PHPWG_ROOT_PATH . PWG_DERIVATIVE_DIR)) {
            while (($node = readdir($contents)) !== false) {
                if ($node != '.' and $node != '..' and is_dir(PHPWG_ROOT_PATH . PWG_DERIVATIVE_DIR . $node)) {
                    self::clear_derivative_cache_rec(PHPWG_ROOT_PATH . PWG_DERIVATIVE_DIR . $node, $pattern);
                }
            }
            closedir($contents);
        }
    }

    /**
     * Used by clear_derivative_cache()
     * @ignore
     */
    public static function clear_derivative_cache_rec($path, $pattern)
    {
        $rmdir = true;
        $rm_index = false;

        // @TODO: use glob
        if ($contents = opendir($path)) {
            while (($node = readdir($contents)) !== false) {
                if ($node == '.' or $node == '..') {
                    continue;
                }
                if (is_dir($path . '/' . $node)) {
                    $rmdir = self::clear_derivative_cache_rec($path . '/' . $node, $pattern);
                } else {
                    if (preg_match($pattern, $node)) {
                        unlink($path . '/' . $node);
                    } elseif ($node == 'index.htm') {
                        $rm_index = true;
                    } else {
                        $rmdir = false;
                    }
                }
            }
            closedir($contents);

            if ($rmdir) {
                if ($rm_index) {
                    unlink($path . '/index.htm');
                }
                clearstatcache();
                @rmdir($path);
            }
            return $rmdir;
        }
    }

    /**
     * Deletes derivatives of a particular element
     *
     * @param array $infos ('path'[, 'representative_ext'])
     * @param 'all'|int $type
     */
    public static function delete_element_derivatives($infos, $type = 'all')
    {
        $path = $infos['path'];
        if (!empty($infos['representative_ext'])) {
            $path = \Phyxo\Functions\Utils::original_to_representative($path, $infos['representative_ext']);
        }
        if (substr_compare($path, '../', 0, 3) == 0) {
            $path = substr($path, 3);
        }
        $dot = strrpos($path, '.');
        if ($type == 'all') {
            $pattern = '-*';
        } else {
            $pattern = '-' . \Phyxo\Image\DerivativeParams::derivative_to_url($type) . '*';
        }
        $path = substr_replace($path, $pattern, $dot, 0);
        if (($glob = glob(PHPWG_ROOT_PATH . PWG_DERIVATIVE_DIR . $path)) !== false) {
            foreach ($glob as $file) {
                unlink($file);
            }
        }
    }

    /**
     * Recursively delete a directory.
     *
     * @param string $path
     * @param string $trash_path, try to move the directory to this path if it cannot be delete
     */
    public static function deltree($path, $trash_path = null)
    {
        if (is_dir($path)) {
            $fh = opendir($path);
            while ($file = readdir($fh)) {
                if ($file != '.' and $file != '..') {
                    $pathfile = $path . '/' . $file;
                    if (is_dir($pathfile)) {
                        self::deltree($pathfile, $trash_path);
                    } else {
                        @unlink($pathfile);
                    }
                }
            }
            closedir($fh);

            if (@rmdir($path)) {
                return true;
            } elseif (!empty($trash_path)) {
                if (!is_dir($trash_path)) {
                    @\Phyxo\Functions\Utils::mkgetdir(
                        $trash_path,
                        \Phyxo\Functions\Utils::MKGETDIR_RECURSIVE | \Phyxo\Functions\Utils::MKGETDIR_DIE_ON_ERROR | \Phyxo\Functions\Utils::MKGETDIR_PROTECT_HTACCESS
                    );
                }
                while ($r = $trash_path . '/' . md5(uniqid(rand(), true))) {
                    if (!is_dir($r)) {
                        @rename($path, $r);
                        break;
                    }
                }
            } else {
                return false;
            }
        }
    }

    /**
     * Returns keys to identify the state of main tables. A key consists of the
     * last modification timestamp and the total of items (separated by a _).
     * Additionally returns the hash of root path.
     * Used to invalidate LocalStorage cache on admin pages.
     *
     * @param string|string[] list of keys to retrieve (categories,groups,images,tags,users)
     * @return string[]
     */
    public static function get_admin_client_cache_keys($requested = [])
    {
        global $conn;

        $tables = [
            'categories' => CATEGORIES_TABLE,
            'groups' => GROUPS_TABLE,
            'images' => IMAGES_TABLE,
            'tags' => TAGS_TABLE,
            'users' => USER_INFOS_TABLE
        ];

        if (!is_array($requested)) {
            $requested = [$requested];
        }
        if (empty($requested)) {
            $requested = array_keys($tables);
        } else {
            $requested = array_intersect($requested, array_keys($tables));
        }

        $keys = [
            '_hash' => md5(\Phyxo\Functions\URL::get_absolute_root_url()),
        ];

        foreach ($requested as $item) {
           // @TODO : add _ between timestamp and count -> pwg_concat ??
            $query = 'SELECT ' . $conn->db_date_to_ts('MAX(lastmodified)') . ', COUNT(1)';
            $query .= ' FROM ' . $tables[$item] . ';';
            $result = $conn->db_query($query);
            $row = $conn->db_fetch_row($result);

            $keys[$item] = sprintf('%s_%s', $row[0], $row[1]);
        }

        return $keys;
    }
}
