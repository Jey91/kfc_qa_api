<?php

namespace App\Models;

class SystemLogHistory
{
    private $db;
    private $common;

    public function __construct()
    {
        $this->db = \Core\Database::getInstance();
        $this->common = \Core\Common::getInstance();
       
    }

    /**  
     * Fetch logs with pagination and optional filters  
     *  
     * @param int $page Current page number  
     * @param int $limit Items per page  
     * @param array $filters Optional filters (plant_id, module, created_by, date_from, date_to, ip_address)  
     * @return array Pagination result with data, total, page, and limit  
     */
    public function findAllWithPagination($page, $limit, $filters = [], $plantData=[])
    {
        $offset = ($page - 1) * $limit;

        // Base query  
        $baseQuery = "SELECT * FROM system_log_history";
        
        // Build filter conditions  
        $conditions = [];
        $params = [];

        if (!empty($filters['plant_id'])) {
            $conditions[] = "slh_lp_plant_db_code = :plant_id";
            $params[':plant_id'] = $filters['plant_id'];
        }

        if (!empty($filters['module'])) {
            $conditions[] = "slh_module = :module";
            $params[':module'] = $filters['module'];
        }

        if (!empty($filters['created_by'])) {
            $conditions[] = "slh_created_by = :created_by";
            $params[':created_by'] = $filters['created_by'];
        }

        if (!empty($filters['date_from'])) {
            $conditions[] = "slh_created_datetime >= :date_from";
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }

        if (!empty($filters['date_to'])) {
            $conditions[] = "slh_created_datetime <= :date_to";
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        if (!empty($filters['search'])) {
            $conditions[] = "(slh_subject LIKE :search OR slh_content LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        // Add IP address filter
        if (!empty($filters['ip_address'])) {
            $conditions[] = "slh_ip_address LIKE :ip_address";
            $params[':ip_address'] = '%' . $filters['ip_address'] . '%';
        }

        // Build WHERE clause  
        $whereClause = !empty($conditions)
            ? " WHERE " . implode(" AND ", $conditions)
            : "";

        // Pagination query for data  
        $query = $baseQuery . $whereClause . "  
            ORDER BY slh_created_datetime DESC  
            OFFSET " . (int) $offset . " ROWS  
            FETCH NEXT " . (int) $limit . " ROWS ONLY";

        // Prepare and execute data query  
        $this->db->prepare($query);
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        $this->db->execute();
        $data = $this->db->fetchAll();

        $processedData = [];
        foreach ($data as $row) {
            $plant_db_code = $row["slh_lp_plant_db_code"];
            $matchedPlant = null;

            foreach ($plantData as $plant) {
                if ($plant['plant_db_code'] === $plant_db_code) {
                    $matchedPlant = $plant;
                    break;
                }
            }
            $row["slh_lp_plant_code"] = $matchedPlant ? $matchedPlant["plant_code"] : '';
            $processedData[] = $row;
        }

        $data = $this->common->removePrefixFromKeys($processedData);

        // Count query for total records  
        $countQuery = "SELECT COUNT(*) AS total FROM system_log_history" . $whereClause;
        $this->db->prepare($countQuery);
        foreach ($params as $key => $value) {
            $this->db->bind($key, $value);
        }
        $this->db->execute();
        $total = $this->db->fetch()["total"];
        return [
            "data" => $data,
            "total" => $total,
            "page" => $page,
            "limit" => $limit
        ];
    }

    /**
     * Create a new log entry
     * 
     * @param array $data Log data
     * @return int|bool The new log ID or false on failure
     */
    public function create(array $data)
    {    
        $this->db->prepare("INSERT INTO system_log_history (
            slh_lp_plant_db_code, 
            slh_ip_address, 
            slh_subject, 
            slh_content, 
            slh_module, 
            slh_created_datetime, 
            slh_created_by
        ) VALUES (
            :slh_lp_plant_db_code, 
            :slh_ip_address, 
            :slh_subject, 
            :slh_content, 
            :slh_module, 
            :slh_created_datetime, 
            :slh_created_by
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
     * Find a log entry by ID
     * 
     * @param int $id Log ID
     * @return array|false Log data or false if not found
     */
    public function findById($id)
    {
        $this->db->prepare("SELECT TOP 1 * FROM system_log_history WHERE slh_id = :id");
        $this->db->bind(':id', $id);
        $this->db->execute();

        return $this->db->fetch();
    }

    /**
     * Get unique modules
     * 
     * @return array List of unique modules
     */
    public function getUniqueModules()
    {
        $this->db->prepare("SELECT DISTINCT slh_module FROM system_log_history ORDER BY slh_module");
        $this->db->execute();

        return $this->db->fetchAll();
    }

    /**
     * Get unique users who created logs
     * 
     * @return array List of unique users
     */
    public function getUniqueUsers()
    {
        $this->db->prepare("SELECT DISTINCT slh_created_by FROM system_log_history ORDER BY slh_created_by");
        $this->db->execute();

        return $this->db->fetchAll();
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
