<?php

namespace App\Models;

use Utils\Helper;

class Notification
{
    private $db;
    private $helper;
    private $common;

    public function __construct()
    {
        $this->db = \Core\Database::getInstance();
        $this->helper = new Helper();
        $this->common = \Core\Common::getInstance();
    }

    /**
     * Get notifications for a user with read status
     * 
     * @param string $luDbCode User db_code
     * @param int $page Current page number
     * @param int $limit Items per page
     * @param bool $unreadOnly Whether to show only unread notifications
     * @return array Notifications and pagination info
     */
    public function getNotificationsWithReadStatus($luDbCode, $selectedPlant = '', $userDepartment = '', $page = 1, $limit = 10, $unreadOnly = false)
    {
        $offset = ($page - 1) * $limit;

        // Select notification data and add is_read flag
        $baseQuery = "SELECT nc.nc_title, nc.nc_content, nc.nc_created_datetime, nc.nc_created_by,
                    nc.nc_db_code, nc.nc_type,
                    CASE WHEN nrs.nrs_id IS NULL THEN 0
                        WHEN nrs.nrs_status = 1 THEN 1
                        ELSE 0
                    END AS is_read
                    FROM notification_center nc
                    LEFT JOIN notification_read_status nrs ON 
                        nc.nc_db_code = nrs.nrs_nc_db_code AND 
                        nrs.nrs_lu_db_code = :lu_db_code
                    WHERE 
                        (nc.nc_recipient_list = '[]' OR 
                        nc.nc_recipient_list LIKE :recipient_pattern)
                    AND (nc.nc_lp_plant_db_code = :selected_plant OR
                        nc.nc_lp_plant_db_code = '' OR
                        nc.nc_lp_plant_db_code IS NULL)
                    AND (nc.nc_lu_department = :user_department OR
                        nc.nc_lu_department = '' OR
                        nc.nc_lu_department IS NULL)
                    AND nc.nc_status = 1";

        // Add filter for unread only if requested
        if ($unreadOnly) {
            $baseQuery .= " AND (nrs.nrs_id IS NULL OR nrs.nrs_status = 0)";
        }

        // Data query with pagination
        $dataQuery = $baseQuery . " 
             ORDER BY nc.nc_created_datetime DESC
             OFFSET " . (int) $offset . " ROWS
             FETCH NEXT " . (int) $limit . " ROWS ONLY";

        $this->db->prepare($dataQuery);
        $this->db->bind(':lu_db_code', $luDbCode);
        $this->db->bind(':recipient_pattern', '%' . $luDbCode . '%');
        $this->db->bind(':selected_plant', $selectedPlant);
        // $this->db->bind(':user_group', $userGroup);
        $this->db->bind(':user_department', $userDepartment);
        $this->db->execute();

        $data = $this->db->fetchAll();

        // Count query using the same base conditions but without pagination
        $countQuery = "SELECT COUNT(*) AS total,
            SUM(CASE WHEN nrs.nrs_nc_db_code IS NULL OR nrs.nrs_status = 0 THEN 1 ELSE 0 END) AS unread_count
            FROM notification_center nc
            LEFT JOIN notification_read_status nrs ON 
                nc.nc_db_code = nrs.nrs_nc_db_code AND
                nrs.nrs_lu_db_code = :lu_db_code
            WHERE 
                (nc.nc_recipient_list = '[]' OR 
                nc.nc_recipient_list LIKE :recipient_pattern)
            AND (nc.nc_lp_plant_db_code = :selected_plant OR
                nc.nc_lp_plant_db_code = '' OR
                nc.nc_lp_plant_db_code IS NULL)
            AND (nc.nc_lu_department = :user_department OR
                nc.nc_lu_department = '' OR
                nc.nc_lu_department IS NULL)
            AND nc.nc_status = 1";

        // Add same unread filter to count query if needed
        if ($unreadOnly) {
            $countQuery .= " AND (nrs.nrs_id IS NULL OR nrs.nrs_status = 0)";
        }

        $this->db->prepare($countQuery);
        $this->db->bind(':lu_db_code', $luDbCode);
        $this->db->bind(':recipient_pattern', '%' . $luDbCode . '%');
        $this->db->bind(':selected_plant', $selectedPlant);
        // $this->db->bind(':user_group', $userGroup);
        $this->db->bind(':user_department', $userDepartment);
        $this->db->execute();

        $count_data = $this->db->fetch();
        $total = $count_data["total"] ?? 0;
        $unread_count = $count_data["unread_count"] ?? 0;

        $data = $this->common->removePrefixFromKeys($data);

        return [
            "data" => $data,
            "total" => $total,
            "unread_count" => $unread_count,
            "page" => $page,
            "limit" => $limit
        ];
    }

    /**
     * Mark a notification as read for a user
     * 
     * @param string $ncDbCode Notification center db_code
     * @param string $luDbCode User db_code
     * @return bool Success status
     */
    public function markAsRead($ncDbCode, $luDbCode)
    {
        // First check if a record already exists
        $this->db->prepare("SELECT nrs_id, nrs_status FROM notification_read_status 
                       WHERE nrs_nc_db_code = :nrs_nc_db_code 
                       AND nrs_lu_db_code = :nrs_lu_db_code");
        $this->db->bind(':nrs_nc_db_code', $ncDbCode);
        $this->db->bind(':nrs_lu_db_code', $luDbCode);
        $this->db->execute();

        $existingRecord = $this->db->fetch();
        $currentDateTime = date('Y-m-d H:i:s');

        if ($existingRecord) {
            // Record exists, check if it needs updating
            if ($existingRecord['nrs_status'] != 1) {
                // Update the existing record to mark as read
                $this->db->prepare("UPDATE notification_read_status 
                               SET nrs_status = 1, 
                                   nrs_read_datetime = :nrs_read_datetime 
                               WHERE nrs_nc_db_code = :nrs_nc_db_code 
                               AND nrs_lu_db_code = :nrs_lu_db_code");
                $this->db->bind(':nrs_read_datetime', $currentDateTime);
                $this->db->bind(':nrs_nc_db_code', $ncDbCode);
                $this->db->bind(':nrs_lu_db_code', $luDbCode);

                return $this->db->execute();
            }

            // Already marked as read, no update needed
            return true;
        } else {
            // No record exists, create a new one
            // Generate a unique db_code for the new record
            $nrsDbCode = $this->helper->generateDbCode();

            $this->db->prepare("INSERT INTO notification_read_status (
                           nrs_db_code,
                           nrs_nc_db_code,
                           nrs_lu_db_code,
                           nrs_status,
                           nrs_read_datetime
                           ) VALUES (
                           :nrs_db_code,
                           :nrs_nc_db_code,
                           :nrs_lu_db_code,
                           1,
                           :nrs_read_datetime
                           )");

            $this->db->bind(':nrs_db_code', $nrsDbCode);
            $this->db->bind(':nrs_nc_db_code', $ncDbCode);
            $this->db->bind(':nrs_lu_db_code', $luDbCode);
            $this->db->bind(':nrs_read_datetime', $currentDateTime);

            return $this->db->execute();
        }
    }

    /**
     * Find a notification read status record by db_code
     * 
     * @param string $code Notification read status db_code
     * @return array|false Notification read status data or false if not found
     */
    public function getByCode($code)
    {
        $this->db->prepare("SELECT TOP 1 * FROM notification_center WHERE nc_db_code = :nc_db_code");
        $this->db->bind(':nc_db_code', $code);
        $this->db->execute();
        $data = $this->db->fetch();
        if ($data) {
            $data = $this->common->removeFirstPrefix($data);
            return $data;
        } else {
            return []; // or handle "no data" case
        }
        return $data;
    }
}
