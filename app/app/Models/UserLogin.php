<?php

namespace App\Models;

class UserLogin
{
    private $db;
    private $common;

    public function __construct()
    {
        $this->db = \Core\Database::getInstance();
        $this->common = \Core\Common::getInstance();
    }
    /**
     * Create a new record
     * 
     * @param array $data user login data
     * @return int|bool The new user login or false on failure
    */
    public function create(array $data)
    {   
        $this->db->prepare("INSERT INTO user_login (
            ul_pl_db_code, 
            ul_lu_db_code, 
            ul_access_token,
            ul_last_login
        ) VALUES (
            :ul_pl_db_code, 
            :ul_lu_db_code, 
            :ul_access_token,
            :ul_last_login
        )");

        // Bind each parameter from the data array
        foreach ($data as $key => $value) {
            $this->db->bind(':' . $key, $value);
        }

        // Execute the query
        $this->db->execute();

        // Return the ID of the newly inserted record
        return $this->db->lastInsertId();
    }

     /**
     * Find a record by user db code and type
     * 
     * @param int $id User ID
     * @return array|false User data or false if not found
     */
    public function findByRecord($code)
    {
        $this->db->prepare("SELECT TOP 1 * FROM user_login WHERE ul_lu_db_code = :ul_lu_db_code");
        $this->db->bind(':ul_lu_db_code', $code);
        $this->db->execute();

        return $this->db->fetch();
    }

    /**
     * Find a record by user access token
     * 
     * @param int $id User ID
     * @return array|false User data or false if not found
     */
    public function findByToken($token)
    {
        $this->db->prepare("SELECT TOP 1 * FROM user_login WHERE ul_access_token = :ul_access_token");
        $this->db->bind(':ul_access_token', $token);
        $this->db->execute();

        return $this->db->fetch();
    }

    /**
     * Update a record
     * 
     * @param string $code User code
     * @param array $data Updated user data
     * @return bool Success status
     */
    public function update($code, array $data)
    {
        // Build the SET part of the query
        $setParts = [];
        foreach ($data as $key => $value) {
            $setParts[] = "$key = :$key";
        }
        $setClause = implode(', ', $setParts);

        // Prepare the full query
        $query = "UPDATE user_login SET $setClause WHERE ul_id = :ul_id";
        $this->db->prepare($query);

        // Bind parameters
        foreach ($data as $key => $value) {
            $this->db->bind(":$key", $value);
        }
        $this->db->bind(':ul_id', $code);

        // Execute and return result
        return $this->db->execute();
    }
}
