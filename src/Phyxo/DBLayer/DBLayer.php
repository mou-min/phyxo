<?php
// +-----------------------------------------------------------------------+
// | Phyxo - Another web based photo gallery                               |
// | Copyright(C) 2014-2016 Nicolas Roudaire         http://www.phyxo.net/ |
// +-----------------------------------------------------------------------+
// | This program is free software; you can redistribute it and/or modify  |
// | it under the terms of the GNU General Public License version 2 as     |
// | published by the Free Software Foundation                             |
// |                                                                       |
// | This program is distributed in the hope that it will be useful, but   |
// | WITHOUT ANY WARRANTY; without even the implied warranty of            |
// | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU      |
// | General Public License for more details.                              |
// |                                                                       |
// | You should have received a copy of the GNU General Public License     |
// | along with this program; if not, write to the Free Software           |
// | Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,            |
// | MA 02110-1301 USA.                                                    |
// +-----------------------------------------------------------------------+

namespace Phyxo\DBLayer;

class DBLayer
{
    protected $layer = null;
    protected $db_link = null;

    protected $queries = array();
    protected $queries_time = 0;

    public static function init($layer, $host, $user, $password, $database) {
		if (file_exists(__DIR__.'/'.$layer.'Connection.php')) {
			require_once __DIR__.'/'.$layer.'Connection.php';
            $className = sprintf('\Phyxo\DBLayer\%sConnection', $layer);
		} else {
			trigger_error('Unable to load DBLayer for '.$layer, E_USER_ERROR);
			exit(1);
		}

        return new $className($host, $user, $password, $database);
    }

    public function __construct($host, $user='', $password='', $database) {
        $this->db_link = $this->db_connect($host, $user, $password, $database);
    }

    protected function db_show_query($query, $result, $query_time) {
        $this->queries_time += $query_time;

        $query2show = false;
        if ($result!=null) {
            $this_query = array(
                'sql' => \SqlFormatter::format($query),
                'time' => $query_time
            );
            if (preg_match('/\s*SELECT\s+/i', $query)) {
                $this_query['rows'] = sprintf('(num rows: %d)', $this->db_num_rows($result));
                $query2show = true;
            } elseif (preg_match('/\s*INSERT|UPDATE|REPLACE|DELETE\s+/i', $query)) {
                $this_query['rows'] = sprintf('(affected rows: %d)', $this->db_changes($result));
                $query2show = true;
            }
            if ($query2show) {
                $this->queries[] = $this_query;
            }
        }
    }

    public function getQueries() {
        return $this->queries;
    }

    public function getQueriesCount() {
        return count($this->queries);
    }

    public function getQueriesTime() {
        return $this->queries_time;
    }

    /**
     * return an IN clause where @params are escaped
     */
    public function in($params) {
        if (empty($params)) {
            return '';
        }
        if (!is_array($params)) {
            if (strpos($params, ',')!==false) {
                $params = explode(',', $params);
            } else {
                $params = array($params);
            }
        }

        foreach ($params as &$param) {
            $param = $this->db_real_escape_string($param);
        }

        return ' IN(\''. implode('\',\'', $params) .'\') ';
    }

    /**
     * Builds an data array from a SQL query.
     * Depending on $key_name and $value_name it can return :
     *
     *    - an array of arrays of all fields (key=null, value=null)
     *        array(
     *          array('id'=>1, 'name'=>'DSC8956', ...),
     *          array('id'=>2, 'name'=>'DSC8957', ...),
     *          ...
     *          )
     *
     *    - an array of a single field (key=null, value='...')
     *        array('DSC8956', 'DSC8957', ...)
     *
     *    - an associative array of array of all fields (key='...', value=null)
     *        array(
     *          'DSC8956' => array('id'=>1, 'name'=>'DSC8956', ...),
     *          'DSC8957' => array('id'=>2, 'name'=>'DSC8957', ...),
     *          ...
     *          )
     *
     *    - an associative array of a single field (key='...', value='...')
     *        array(
     *          'DSC8956' => 1,
     *          'DSC8957' => 2,
     *          ...
     *          )
     *
     * @since 2.6
     *
     * @param string $query
     * @param string $key_name
     * @param string $value_name
     * @return array
     */
    public function query2array($query, $key_name=null, $value_name=null) {
        $result = $this->db_query($query);
        $data = array();

        if (isset($key_name)) {
            if (isset($value_name)) {
                while ($row = $this->db_fetch_assoc($result)) {
                    $data[$row[$key_name]] = $row[$value_name];
                }
            } else {
                while ($row = $this->db_fetch_assoc($result)) {
                    $data[$row[$key_name]] = $row;
                }
            }
        } else {
            if (isset($value_name)) {
                while ($row = $this->db_fetch_assoc($result)) {
                    $data[] = $row[$value_name];
                }
            } else {
                while ($row = $this->db_fetch_assoc($result)) {
                    $data[] = $row;
                }
            }
        }

        return $data;
    }

    /**
     * Inserts one line in a table.
     *
     * @param string $table_name
     * @param array $data
     */
    public function single_insert($table_name, $data) {
        if (count($data) != 0) {
            $query = 'INSERT INTO '.$table_name.' ('.implode(',', array_keys($data)).')';
            $query .= ' VALUES(';

            $is_first = true;
            foreach ($data as $key => $value) {
                if (!$is_first) {
                    $query .= ',';
                } else {
                    $is_first = false;
                }

                if (is_bool($value)) {
                    $query .= '\''.$this->boolean_to_db($value).'\'';
                } elseif ($value === '') {
                    $query .= 'NULL';
                } else {
                    $query .= '\''.$this->db_real_escape_string($value).'\'';
                }
            }
            $query .= ')';

            $this->db_query($query);
        }
    }

    /**
     * Updates one line in a table.
     *
     * @param string $tablename
     * @param array $datas
     * @param array $where
     * @param int $flags - if MASS_UPDATES_SKIP_EMPTY, empty values do not overwrite existing ones
     */
    public function single_update($tablename, $datas, $where, $flags=0) { // @TODO: need refactoring between mass_* and single_*
        if (count($datas) == 0) {
            return;
        }

        $is_first = true;

        $query = 'UPDATE '.$tablename.' SET ';

        foreach ($datas as $key => $value) {
            $separator = $is_first ? '' : ', ';

            if (isset($value) && $value !== '') {
                if (is_bool($value)) {
                    $query .= $separator.$key.' = \''.$this->boolean_to_db($value).'\'';
                } elseif ($value !== '') {
                    $query .= $separator.$key.' = \''.$this->db_real_escape_string($value).'\'';
                }
            } else {
                if ($flags & MASS_UPDATES_SKIP_EMPTY) {
                    continue; // next field
                }
                $query .= "$separator$key = NULL";
            }
            $is_first = false;
        }

        if (!$is_first) { // only if one field at least updated
            $is_first = true;

            $query.= ' WHERE ';

            foreach ($where as $key => $value) {
                if (!$is_first) {
                    $query.= ' AND ';
                }
                if (isset($value) && is_bool($value)) {
                    $query.= $key.' = \''.$this->boolean_to_db($value).'\'';
                } elseif (!isset($value) || $value === '') {
                    $query.= $key.' IS NULL';
                } else {
                    $query.= $key.' = \''.$this->db_real_escape_string($value).'\'';
                }
                $is_first = false;
            }

            $this->db_query($query);
        }
    }

    /**
     * deletes multiple lines in a table
     *
     * @param string table_name
     * @param array dbfields
     * @param array datas
     * @return void
     */
    public function mass_deletes($tablename, array $dbfields, array $datas) {
        if (empty($dbfields) || empty($datas)) {
            return;
        }
        $query = 'DELETE FROM '.$tablename;
        $query .= ' WHERE ('.implode(',', $dbfields).')';

        $rows = array();
        foreach ($datas as $data) {
            $elements = array();
            foreach ($dbfields as $dbfield) {
                if (isset($data[$dbfield]) && is_bool($data[$dbfield])) {
                    $elements[] = $this->boolean_to_db($data[$dbfield]);
                } elseif (!isset($data[$dbfield]) or $data[$dbfield] === '') {
                    $elements[] = 'NULL';
                } else {
                    $elements[] = '\''.$this->db_real_escape_string($data[$dbfield]).'\'';
                }
            }
            $rows[] = '('.implode(',', $elements).')';
        }
        if (empty($rows)) {
            return;
        }
        $query .= ' '.$this->in($rows);
        $this->db_query($query);
    }
}
