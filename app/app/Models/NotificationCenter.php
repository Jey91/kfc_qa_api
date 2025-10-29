<?php

namespace App\Models;

class NotificationCenter
{
    private $db;
    private $common;

    public function __construct()
    {
        $this->db = \Core\Database::getInstance();
        $this->common = \Core\Common::getInstance();
    }

    /**  
     * Fetch notification center with pagination and optional search  
     *  
     * @param int $page Current page number  
     * @param int $limit Items per page  
     * @param string $orderBy Column to order by
     * @param string $orderDirection Order direction (ASC/DESC)
     * @param string|int $status Status filter
     * @param string|null $search Optional search term  
     * @return array Pagination result with data, total, page, and limit  
     */
    public function findAllWithPagination($page, $limit, $orderBy = 'nc_id', $orderDirection = 'DESC', $status = "*", $type = null, $search = null)
    {
        $offset = ($page - 1) * $limit;

        // Validate order direction
        $orderDirection = strtoupper($orderDirection);
        if (!in_array($orderDirection, ['ASC', 'DESC'])) {
            $orderDirection = 'DESC';
        }

        // Whitelist of allowed columns for ordering
        $allowedColumns = ['nc_id', 'nc_title', 'nc_status', 'nc_created_datetime', 'nc_created_by'];
        if (!in_array($orderBy, $allowedColumns)) {
            $orderBy = 'nc_id'; // Default if invalid column
        }

        // Base query  
        $baseQuery = "SELECT * FROM notification_center";

        // Build WHERE clause with placeholders
        $whereClause = $status !== "*" ? " WHERE nc_status = :status" : " WHERE nc_status > -1";

        // Add type condition if provided
        $whereClause .= $type ? " AND nc_type = :nc_type" : "";

        // Add search condition if search parameter exists
        if ($search) {
            $whereClause .= " AND (nc_title LIKE :search)";
        }

        // Pagination query for data  
        $query = $baseQuery . $whereClause . "  
        ORDER BY " . $orderBy . " " . $orderDirection . "  
        OFFSET " . (int) $offset . " ROWS  
        FETCH NEXT " . (int) $limit . " ROWS ONLY";

        // Prepare and execute data query  
        $this->db->prepare($query);

        // Bind parameters consistently for both queries
        if ($status !== "*") {
            $this->db->bind(":status", (int) $status);
        }

        if ($type) {
            $this->db->bind(":nc_type", $type);
        }

        if ($search) {
            $this->db->bind(":search", "%" . $search . "%");
        }

        $this->db->execute();
        $data = $this->db->fetchAll();

        // Count query for total records  
        $countQuery = "SELECT COUNT(*) AS total FROM notification_center" . $whereClause;
        $this->db->prepare($countQuery);

        // Bind the same parameters again for count query
        if ($status !== "*") {
            $this->db->bind(":status", (int) $status);
        }

        if ($type) {
            $this->db->bind(":nc_type", $type);
        }

        if ($search) {
            $this->db->bind(":search", "%" . $search . "%");
        }

        $this->db->execute();
        $total = $this->db->fetch()["total"];

        return [
            "data" => $data,
            "total" => $total,
            "page" => $page,
            "limit" => $limit,
            "orderBy" => $orderBy,
            "orderDirection" => $orderDirection,
            "status" => $status
        ];
    }

    public function findAllWithPaginationV2($page, $limit, $orderBy = 'nc_id', $orderDirection = 'DESC', $status = "*", $type = null, $search = null)
    {
        $offset = ($page - 1) * $limit;

        // Validate order direction
        $orderDirection = strtoupper($orderDirection);
        if (!in_array($orderDirection, ['ASC', 'DESC'])) {
            $orderDirection = 'DESC';
        }

        // Whitelist of allowed columns for ordering
        $allowedColumns = ['nc_id', 'nc_title', 'nc_status', 'nc_created_datetime', 'nc_created_by'];
        if (!in_array($orderBy, $allowedColumns)) {
            $orderBy = 'nc_id'; // Default if invalid column
        }

        // Base query  
        $baseQuery = "SELECT * FROM notification_center";

        // Build WHERE clause with placeholders
        $whereClause = $status !== "*" ? " WHERE nc_status = :status" : " WHERE nc_status > -1";

        // Add type condition if provided
        $whereClause .= $type ? " AND nc_type = :nc_type" : "";

        // Add search condition if search parameter exists
        if ($search) {
            $whereClause .= " AND (nc_title LIKE :search)";
        }

        // Pagination query for data  
        $query = $baseQuery . $whereClause . "  
        ORDER BY " . $orderBy . " " . $orderDirection . "  
        OFFSET " . (int) $offset . " ROWS  
        FETCH NEXT " . (int) $limit . " ROWS ONLY";

        // Prepare and execute data query  
        $this->db->prepare($query);

        // Bind parameters consistently for both queries
        if ($status !== "*") {
            $this->db->bind(":status", (int) $status);
        }

        if ($type) {
            $this->db->bind(":nc_type", $type);
        }

        if ($search) {
            $this->db->bind(":search", "%" . $search . "%");
        }

        $this->db->execute();
        $data = $this->db->fetchAll();

        // Count query for total records  
        $countQuery = "SELECT COUNT(*) AS total FROM notification_center" . $whereClause;
        $this->db->prepare($countQuery);

        // Bind the same parameters again for count query
        if ($status !== "*") {
            $this->db->bind(":status", (int) $status);
        }

        if ($type) {
            $this->db->bind(":nc_type", $type);
        }

        if ($search) {
            $this->db->bind(":search", "%" . $search . "%");
        }

        $this->db->execute();

        $data = $this->common->removePrefixFromKeys($data);

        $total = $this->db->fetch()["total"];

        return [
            "data" => $data,
            "total" => $total,
            "page" => $page,
            "limit" => $limit,
            "orderBy" => $orderBy,
            "orderDirection" => $orderDirection,
            "status" => $status
        ];
    }

    /**
     * Create a new notification center record
     * 
     * @param array $data Notification center data
     * @return int|bool The new notification center ID or false on failure
     */
    public function create(array $data)
    {
        $this->db->prepare("INSERT INTO notification_center (
            nc_db_code, 
            nc_type, 
            nc_title, 
            nc_content,  
            nc_recipient_list,
            nc_lu_department,
            nc_lp_plant_db_code,
            nc_status, 
            nc_created_datetime, 
            nc_created_by
        ) VALUES (
            :nc_db_code,
            :nc_type, 
            :nc_title, 
            :nc_content, 
            :nc_recipient_list,
            :nc_lu_department,
            :nc_lp_plant_db_code,
            :nc_status, 
            :nc_created_datetime, 
            :nc_created_by
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
     * Find a notification center record by ID
     * 
     * @param int $id Notification center ID
     * @return array|false Notification center data or false if not found
     */
    public function findById($id)
    {
        $this->db->prepare("SELECT * FROM notification_center WHERE nc_id = :nc_id");
        $this->db->bind(':nc_id', $id);
        $this->db->execute();
        return $this->db->fetch();
    }

    /**
     * Find a notification center record by db_code
     * 
     * @param int $code Notification center db_code
     * @return array|false Notification center data or false if not found
     */
 
    public function findByCode($code, $status = "*")
    {
        // Validate status
        // if ($status !== "*" && !is_numeric($status)) {
        //     return false; // Invalid status, return false
        // }

        // Build the query based on status
        if ($status !== "*") {
            $this->db->prepare("SELECT TOP 1 * FROM notification_center WHERE nc_db_code = :nc_db_code AND nc_status = :nc_status");
            $this->db->bind(':nc_status', (int) $status);
            $this->db->bind(':nc_db_code', $code);
            $this->db->execute();
        } else {
            $this->db->prepare("SELECT TOP 1 * FROM notification_center WHERE nc_db_code = :nc_db_code AND nc_status > -1");
            $this->db->bind(':nc_db_code', $code);
            $this->db->execute();
        }
        $data = $this->db->fetch();
        if ($data) {
            $data = $this->common->removeFirstPrefix($data);
            return $data;
        } else {
            return []; // or handle "no data" case
        }
    }

    /**
     * Update a notification center record
     * 
     * @param int $code Notification center code
     * @param array $data Updated notification center data
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
        $query = "UPDATE notification_center SET $setClause WHERE nc_db_code = :nc_db_code";
        $this->db->prepare($query);

        // Bind parameters
        foreach ($data as $key => $value) {
            $this->db->bind(":$key", $value);
        }
        $this->db->bind(':nc_db_code', $code);

        // Execute and return result
        return $this->db->execute();
    }

    /**
     * Delete a notification center record
     * 
     * @param int $code Notification center code to delete
     * @return bool Success status
     */
    public function delete($code)
    {
        // Soft delete - update status to inactive (-1)
        $this->db->prepare("UPDATE notification_center SET nc_status = -1 WHERE nc_db_code = :nc_db_code");
        $this->db->bind(':nc_db_code', $code);

        return $this->db->execute();
    }

    /**
     * Get the number of rows affected by the last database operation.
     *
     * @return int The number of rows.
     */
    public function rowCount()
    {
        return $this->db->rowCount();
    }
}
