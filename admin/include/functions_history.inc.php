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
 * @package functions\admin\history
 */


// +-----------------------------------------------------------------------+
// | Functions                                                             |
// +-----------------------------------------------------------------------+

function get_summary($year=null, $month=null, $day=null) {
    global $conn;

    $query = 'SELECT year,month,day,hour,nb_pages FROM '.HISTORY_SUMMARY_TABLE;

    if (isset($day)) {
        $query .= ' WHERE year = '.$year.' AND month = '.$month;
        $query .= ' AND day = '.$day.' AND hour IS NOT NULL';
        $query .= ' ORDER BY year ASC,month ASC,day ASC,hour ASC;';
    } elseif (isset($month)) {
        $query .= ' WHERE year = '.$year.' AND month = '.$month;
        $query .= ' AND day IS NOT NULL AND hour IS NULL';
        $query .= ' ORDER BY year ASC,month ASC,day ASC;';
    } elseif (isset($year)) {
        $query .= ' WHERE year = '.$year.' AND month IS NOT NULL';
        $query .= ' AND day IS NULL';
        $query .= ' ORDER BY year ASC,month ASC;';
    } else {
        $query .= ' WHERE year IS NOT NULL';
        $query .= ' AND month IS NULL';
        $query .= ' ORDER BY year ASC;';
    }

    $result = $conn->db_query($query);

    $output = array();
    while ($row = $conn->db_fetch_assoc($result)) {
        $output[] = $row;
    }

    return $output;
}

/**
 * Callback used to sort history entries
 */
function history_compare($a, $b) {
    return strcmp($a['date'].$a['time'], $b['date'].$b['time']);
}

/**
 * Perform history search.
 *
 * @param array $data  - used in trigger_change
 * @param array $search
 * @param string[] $types
 * @param array
 */
function get_history($data, $search, $types) {
    global $conn;

    if (isset($search['fields']['filename'])) {
        $query = 'SELECT id FROM '.IMAGES_TABLE;
        $query .= ' WHERE file LIKE \''.$search['fields']['filename'].'\'';
        $search['image_ids'] = $conn->query2array($query, null, 'id');
    }

    $clauses = array();

    if (isset($search['fields']['date-after'])) {
        $clauses[] = "date >= '".$search['fields']['date-after']."'";
    }

    if (isset($search['fields']['date-before'])) {
        $clauses[] = "date <= '".$search['fields']['date-before']."'";
    }

    if (isset($search['fields']['types'])) {
        $local_clauses = array();

        foreach ($types as $type) {
            if (in_array($type, $search['fields']['types'])) {
                $clause = 'image_type ';
                if ($type == 'none') {
                    $clause .= 'IS NULL';
                } else {
                    $clause .= "= '".$type."'";
                }

                $local_clauses[] = $clause;
            }
        }

        if (count($local_clauses) > 0) {
            $clauses[] = implode(' OR ', $local_clauses);
        }
    }

    if (isset($search['fields']['user']) and $search['fields']['user'] != -1) {
        $clauses[] = 'user_id = '.$search['fields']['user'];
    }

    if (isset($search['fields']['image_id'])) {
        $clauses[] = 'image_id = '.$search['fields']['image_id'];
    }

    if (isset($search['fields']['filename'])) {
        if (count($search['image_ids']) == 0) {
            // a clause that is always false
            $clauses[] = '1 = 2 ';
        } else {
            $clauses[] = 'image_id '.$conn->in($search['image_ids']);
        }
    }

    if (isset($search['fields']['ip'])) {
        $clauses[] = 'ip LIKE \''.$search['fields']['ip'].'\'';
    }

    $clauses = prepend_append_array_items($clauses, '(', ')');

    $where_separator = implode(' AND ', $clauses);

    $query = 'SELECT date,time,user_id,ip,section,category_id,tag_ids,';
    $query .= 'image_id,image_type FROM '.HISTORY_TABLE;
    $query .= ' WHERE '.$where_separator;

    $result = $conn->db_query($query);

    while ($row = $conn->db_fetch_assoc($result)) {
        $data[] = $row;
    }

    return $data;
}

add_event_handler('get_history', 'get_history');
trigger_notify('functions_history_included');
