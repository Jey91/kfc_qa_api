<?php

namespace App\Models;

class QaInspection {
    private $db;
    private $common;

    /**
     * QaInspection constructor.
     * Initializes database and common utility instances.
     */
    public function __construct() {
        $this->db = \Core\Database::getInstance();
        $this->common = \Core\Common::getInstance();
    }

    public function getDb() {
        return $this->db;
    }

    public function findQaItemList($qaRecordId)
    {
         // Base data query
        $query = "
            SELECT * FROM qa_inspection_item_list
            WHERE qiil_qi_id = :qiil_qi_id
        ";

        $this->db->prepare($query);
        $this->db->bind(":qiil_qi_id", $qaRecordId);
        $this->db->execute();
        $data = $this->common->removePrefixFromKeys($this->db->fetchAll());

        return [
            "data" => $data
        ];
    }

    public function updateQaInspectionStatus($code, $status)
    {
        try {

            // Prepare the full query
            $query = "UPDATE qa_inspection SET qi_status = :qi_status WHERE qi_db_code = :qi_db_code";
            $this->db->prepare($query);

            $this->db->bind(':qi_status', $status);
            $this->db->bind(':qi_db_code', $code);

            $this->db->execute();

            return true;

        } catch (\Exception $e) {
            error_log("Database error: " . $e->getMessage());
            throw $e;  // Throw exception to be caught by controller
        }
    }

    public function updateQaItemSerialListStatus(array $qaItemSerialList)
    {

        try {
    
            foreach ($qaItemSerialList as $key => $item) {

                $sql = "INSERT INTO qa_inspection_serial_list
                        (qisl_db_code, qisl_qi_id, qisl_qiil_id, qisl_qiil_li_id, qisl_serial_number, qisl_batch_number, qisl_created_by, qisl_created_datetime)
                        VALUES (:qisl_db_code, :qisl_qi_id, :qisl_qiil_id, :qisl_qiil_li_id, :qisl_serial_number, :qisl_batch_number, :qisl_created_by, :qisl_created_datetime)";
                $this->db->prepare($sql);

                $this->db->bind(':qisl_db_code',       $item['qisl_db_code']);
                $this->db->bind(':qisl_qi_id',         $item['qisl_qi_id']);
                $this->db->bind(':qisl_qiil_id',       $item['qisl_qiil_id']);
                $this->db->bind(':qisl_qiil_li_id',    $item['qisl_qiil_li_id']);
                $this->db->bind(':qisl_serial_number', $item['qisl_serial_number']);
                $this->db->bind(':qisl_batch_number', $item['qisl_batch_number']);
                $this->db->bind(':qisl_created_by', $item['qisl_created_by']);
                $this->db->bind(':qisl_created_datetime', $item['qisl_created_datetime']);
                $this->db->execute();
        
            }

            return true;

        } catch (\Exception $e) {
            error_log("Database error: " . $e->getMessage());
            throw $e;  // Throw exception to be caught by controller
        }
        
    }


    public function updateQaItemListStatus($qaItemList)
    {
        // A more robust check to ensure there's data to process.
        if (empty($qaItemList)) {
            return false;
        }

        $qaItemList = json_decode($qaItemList, true);

        // Check if JSON decoding was successful.
        if (json_last_error() !== JSON_ERROR_NONE) {
            // Optionally log the JSON error: json_last_error_msg()
            return false;
        }

        // 1. Start the transaction.
        $this->db->beginTransaction();

        try {
            // Prepare the query once before the loop for better performance.
            $query = "UPDATE qa_inspection_item_list 
                    SET qiil_failed_quantity = :qiil_failed_quantity, 
                        qiil_status = :qiil_status,  
                        qiil_remark = :qiil_remark, 
                        qiil_updated_by = :qiil_updated_by, 
                        qiil_updated_datetime = :qiil_updated_datetime 
                    WHERE qiil_id = :qiil_id";
            
            $this->db->prepare($query);
            $currentDateTime = date('Y-m-d H:i:s'); // Get timestamp once.

            foreach ($qaItemList as $item) {
                // Bind new values for each item in the loop.
                $this->db->bind(":qiil_failed_quantity", $item['failedQuantity']);
                $this->db->bind(":qiil_status", $item['status']);
                $this->db->bind(":qiil_remark", $item['remark']);
                $this->db->bind(":qiil_updated_by", 'system'); // Assuming a static value
                $this->db->bind(":qiil_updated_datetime", $currentDateTime);
                $this->db->bind(":qiil_id", $item['id']);

                $updateResult = $this->db->execute();

                // 2. If any single update fails, throw an exception to trigger the rollback.
                if (!$updateResult) {
                    throw new \Exception("Failed to update item with ID: " . $item['id']);
                }
            }

            // 3. If all updates in the loop were successful, commit the transaction.
            $this->db->commit();
            return true;

        } catch (\Exception $e) {
            // 4. If any exception occurred, roll back all changes made during this transaction.
            $this->db->rollBack();
            error_log($e->getMessage());
            return false;
        }
    }


    /**
     * Update a QaInspection
     * 
     * @param string $code QaInspection code
     * @param array $data Updated QaInspection data
     * @return bool Success status
     */
    public function updateQaToReserve($code, $data)
    {

        // Build the SET part of the query
        $setParts = [];
        foreach ($data as $key => $value) {
            $setParts[] = "$key = :$key";
        }

        $setClause = implode(', ', $setParts);

        // Prepare the full query
        $query = "UPDATE qa_inspection SET $setClause WHERE qi_db_code = :qi_db_code";
        $this->db->prepare($query);

        // Bind parameters
        foreach ($data as $key => $value) {
            $this->db->bind(":$key", $value);
        }

        $this->db->bind(':qi_db_code', $code);

        // Execute and return result
        return $this->db->execute();
        
    }

    /**
     * Get all qa pending & reserved list
     */
   public function findAll()
   {
    
        // Base data query
        $query = "
            SELECT * FROM qa_inspection
            WHERE qi_status = 1 OR qi_status = 2
            ORDER BY qi_id DESC
        ";

        $this->db->prepare($query);
        $this->db->execute();
        $data = $this->common->removePrefixFromKeys($this->db->fetchAll());

        return [
            "data" => $data
        ];
    }

    

    /**
     * Find a QaInspection by db code
     * 
     * @param string $code db code
     * @param string $status Status filter (* for all)
     * @return array|false QaInspection data or false if not found
     */
    public function findByCode($code, $status = "*")
    {
        // Build the query based on status

        if ($status !== "*") {
            $this->db->prepare("SELECT TOP 1 * FROM qa_inspection WHERE qi_db_code = :qi_db_code AND qi_status = :qi_status");
            $this->db->bind(':qi_status', (int) $status);
            $this->db->bind(':qi_db_code', $code);
            $this->db->execute();
        } else {
            $this->db->prepare("SELECT TOP 1 * FROM qa_inspection WHERE qi_db_code = :qi_db_code AND qi_status = 1");
            $this->db->bind(':qi_db_code', $code);
            $this->db->execute();
        }
        $data = $this->db->fetch();
        if ($data) {
            $data = $this->common->removeFirstPrefix($data);
            return $data;
        } else {
            return []; // or handle "no data" case
        }
        return $this->db->fetch();
    }

    public function insertQaInspection(array $qaData){

         try {
            $data = [
                'qi_db_code' => $qaData['qi_db_code'],
                'qi_gl_no' => $qaData['qi_gl_no'],
                'qi_ig_no' => $qaData['qi_ig_no'],
                'qi_ig_no_by' => $qaData['qi_ig_no_by'],
                'qi_item_count' => $qaData['qi_item_count'],
                'qi_status' => $qaData['qi_status'],
                'qi_pdl_vendor_code' => $qaData['qi_pdl_vendor_code'],
                'qi_pdl_ph_purchase_no' => $qaData['qi_pdl_ph_purchase_no'],
                'qi_pdl_do_no' => $qaData['qi_pdl_do_no'],
                'qi_created_by' => $qaData['qi_created_by'],
                'qi_created_datetime' => $qaData['qi_created_datetime']
            ];

            $columns = array_keys($data);
            $placeholders = array_map(fn($col) => ':' . $col, $columns);
            $sql = "INSERT INTO qa_inspection (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $this->db->prepare($sql);
            foreach ($data as $key => $value) {
                $this->db->bind(':' . $key, $value);
            }

            // Execute and return result
            $this->db->execute();

            return $this->db->lastInsertId();


        } catch (\PDOException $e) {
            error_log("Database error in: " . $e->getMessage());
            throw $e;  // Throw exception to be caught by controller
        }
    } 

    public function insertQaInspectionItem(array $qaItemData){

         try {

            $data = [
                'qiil_db_code' => $qaItemData['qiil_db_code'],
                'qiil_qi_id' => $qaItemData['qiil_qi_id'],
                'qiil_li_id' => $qaItemData['qiil_li_id'],
                'qiil_received_quantity' => $qaItemData['qiil_received_quantity'],
                'qiil_failed_quantity' => $qaItemData['qiil_failed_quantity'],
                'qiil_status' => $qaItemData['qiil_status'],
                'qiil_condition' => $qaItemData['qiil_condition'],
                'qiil_has_serial_number' => $qaItemData['qiil_has_serial_number'],
                'qiil_icm_no' => $qaItemData['qiil_icm_no'],
                'qiil_created_by' => $qaItemData['qiil_created_by'],
                'qiil_created_datetime' => $qaItemData['qiil_created_datetime'],
                'qiil_inspection_area' => $qaItemData['qiil_inspection_area'],
                'qiil_inspection_department' => $qaItemData['qiil_inspection_department']

            ];

            $columns = array_keys($data);
            $placeholders = array_map(fn($col) => ':' . $col, $columns);
            $sql = "INSERT INTO qa_inspection_item_list (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $this->db->prepare($sql);
            foreach ($data as $key => $value) {
                $this->db->bind(':' . $key, $value);
            }

            // Execute and return result
            return $this->db->execute();

        } catch (\PDOException $e) {
            error_log("Database error in: " . $e->getMessage());
            throw $e;  // Throw exception to be caught by controller
        }
    }

}