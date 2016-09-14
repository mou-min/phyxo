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

/**
 * @package functions\admin\install
 */


/**
 * Loads a SQL file and executes all queries.
 * Before executing a query, $replaced is... replaced by $replacing. This is
 * useful when the SQL file contains generic words. Drop table queries are
 * not executed.
 *
 * @param string $filepath
 * @param string $replaced
 * @param string $replacing
 */
function execute_sqlfile($filepath, $replaced, $replacing, $dblayer) {
    global $conn;

    $sql_lines = file($filepath);
    $query = '';
    foreach ($sql_lines as $sql_line) {
        $sql_line = trim($sql_line);
        if (preg_match('/(^--|^$)/', $sql_line)) {
            continue;
        }
        $query .= ' '.$sql_line;
        // if we reached the end of query, we execute it and reinitialize the
        // variable "query"
        if (preg_match('/;$/', $sql_line)) {
            $query = trim($query);
            $query = str_replace($replaced, $replacing, $query);
            // we don't execute "DROP TABLE" queries
            if (!preg_match('/^DROP TABLE/i', $query)) {
                if ('mysql' == $dblayer) {
                    if (preg_match('/^(CREATE TABLE .*)[\s]*;[\s]*/im', $query, $matches)) {
                        $query = $matches[1].' DEFAULT CHARACTER SET utf8'.';';
                    }
                }
                $conn->db_query($query);
            }
            $query = '';
        }
    }
}

/**
 * Search for database engines available
 *
 * We search for functions_DATABASE_ENGINE.inc.php
 * and we check if the connect function for that database exists
 *
 * @return array
 */
function available_engines() {
    $engines = array();

    $pattern = PHPWG_ROOT_PATH. 'src/Phyxo/DBLayer/%sConnection.php';
    include_once PHPWG_ROOT_PATH. 'include/dblayers.inc.php';

    foreach ($dblayers as $engine_name => $engine) {
        if (file_exists(sprintf($pattern, $engine_name)) && isset($engine['function_available'])
        && function_exists($engine['function_available'])) {
            $engines[$engine_name] = $engine['engine'];
        } elseif (file_exists(sprintf($pattern, $engine_name)) && isset($engine['class_available'])
	    && class_exists($engine['class_available'])) {
            $engines[$engine_name] = $engine['engine'];
        }
    }

    return $engines;
}
