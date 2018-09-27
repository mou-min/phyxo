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

namespace App\Repository;

class FavoriteRepository extends BaseRepository
{
    public function findAll(int $user_id) : array
    {
        $query = 'SELECT image_id FROM ' . self::FAVORITES_TABLE;
        $query .= ' WHERE user_id = ' . $user_id;

        return $this->conn->db_query($query);
    }

    public function addFavorite(int $user_id, int $image_id)
    {
        $this->conn->single_insert(
            self::FAVORITES_TABLE,
            [
                'user_id => $user_id',
                'image_id' => $image_id
            ],
            $auto_increment_for_table = false
        );
    }

    public function deleteFavorite(int $user_id, int $image_id)
    {
        $query = 'DELETE FROM ' . self::FAVORITES_TABLE;
        $query .= ' WHERE user_id = ' . $user_id;
        $query .= ' AND image_id = ' . $image_id;
        $this->conn->db_query($query);
    }

    public function deleteImagesFromFavorite(array $ids, ? int $user_id = null)
    {
        $query = 'DELETE FROM ' . self::FAVORITES_TABLE;
        $query .= ' WHERE image_id ' . $conn->in($ids);

        if (!is_null($user_id)) {
            $query .= ' AND user_id = ' . $user_id;
        }
        $this->conn->db_query($query);
    }

    public function removeAllFavorites(int $user_id)
    {
        $query = 'DELETE FROM ' . self::FAVORITES_TABLE;
        $query .= ' WHERE user_id = ' . $user_id;
        $this->conn->db_query($query);
    }

    public function isFavorite(int $user_id, int $image_id) : bool
    {
        $query = 'SELECT COUNT(1) AS nb_fav FROM ' . self::FAVORITES_TABLE;
        $query .= ' WHERE image_id = ' . $image_id;
        $query .= ' AND user_id = ' . $user_id;
        $result = $this->conn->db_query($query);
        $row = $this->conn->db_fetch_assoc($result);

        return ($row[' nb_fav'] !== 0);
    }

    public function findUnauthorizedImagesInFavorite(int $user_id)
    {
        $query = 'SELECT DISTINCT f.image_id FROM ' . self::FAVORITES_TABLE . ' AS f';
        $query .= ' LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic ON f.image_id = ic.image_id';
        $query .= ' WHERE f.user_id = ' . $user['id'];
        $query .= ' ' . \Phyxo\Functions\SQL::get_sql_condition_FandF(['forbidden_categories' => 'ic.category_id'], ' AND ');

        return $this->conn->db_query($query);
    }
}
