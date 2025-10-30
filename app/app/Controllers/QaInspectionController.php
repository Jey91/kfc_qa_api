<?php

namespace App\Controllers;

use Core\Request;
use Core\Response;
use Utils\Helper;
use App\Models\QaInspection;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemDetail;



class QaInspectionController {
    private $helper;
    private $common;
    private $qaInspection;
    private $item;
    private $itemCategory;
    private $itemDetail;

    public function __construct() 
    {
        $this->helper = new Helper();
        $this->common = \Core\Common::getInstance();
        $this->qaInspection = new QaInspection();
        $this->itemCategory = new ItemCategory();
        $this->item = new Item();
        $this->itemDetail = new ItemDetail();
    }
    
    public function updateQaItemSerialList(Request $request, Response $response){
        // Validate authorization
        $authInfo = $request->getData('user');

        if (!$authInfo['user_access']['master_data_setting']['write']) {
            return $response->unauthorized('Permission denied');
        }

        // Check if qa inspection exists
        $code = $request->getData('code');
        $qaInspection = $this->qaInspection->findByCode($code, 2);

        if (!$qaInspection) {
            return $response->notFound('Record not found');
        }

        // Validate request data
        $errors = $request->validate([
            'qaItemSerialList' => ['required']
        ]);

        if (!empty($errors)) {
            return $response->validationError($errors);
        }
        
        $qaItemSerialList = $request->getData('qaItemSerialList');

         // A more robust check to ensure there's data to process.
        if (empty($qaItemSerialList)) {
           return $response->error('Failed to update QA item serial list', 500);
        }

        $qaItemSerialList = json_decode($qaItemSerialList, true);

        // Check if JSON decoding was successful.
        if (json_last_error() !== JSON_ERROR_NONE) {
            return $response->error('Failed to update QA item serial list', 500);
        }

        $itemDataList = [];

        foreach ($qaItemSerialList as $item) {

            $data = [
                'qisl_db_code' => $this->helper->generateDbCode(),
                'qisl_qi_id' => $item['qa_inspection_id'],
                'qisl_qiil_id' => $item['qa_inspection_item_list_id'],
                'qisl_qiil_li_id' => $item['item_id'],
                'qisl_serial_number' => $item['serial_number'],
                'qisl_batch_number' => $item['batch_number'],
                'qisl_created_by' => $authInfo['lu_name'] ?? 'system',
                'qisl_created_datetime' => date('Y-m-d H:i:s'),
            ];

            $itemDataList[] = $data;

        }

        $this->qaInspection->getDb()->beginTransaction();

        try {
            $this->qaInspection->updateQaItemSerialListStatus($itemDataList);

            // IF SUCCESS
            // UPDATE QA INSPECTION STATUS TO COMPLETED
            // 3 = COMPLETE
            $this->qaInspection->updateQaInspectionStatus($code, 3);

            $qaItemList = [];
            foreach ($qaItemSerialList as $item) {

                $itemCode = $item['item_code'];
                $itemInfo = $this->item->findByItemCode($itemCode);
                $itemCategoryId = $itemInfo['li_class_category'];
                $itemCatWarrantyInfo = $this->itemCategory->findCatInfoById($itemCategoryId);

                $itemCatWarranty = (int) $itemCatWarrantyInfo['lc_warranty'];
                $itemCatExpiration = (int) $itemCatWarrantyInfo['lc_expiration'];

                $warrantyDate = date('Y-m-d H:i:s');
                $expirationDate = date('Y-m-d H:i:s');

                if($itemCatWarranty > 0){
                    $warrantyDate = date("Y-m-d H:i:s", strtotime("+$itemCatWarranty years"));
                }

                if($itemCatExpiration > 0){
                    $expirationDate = date("Y-m-d H:i:s", strtotime("+$itemCatExpiration years"));
                }


               $data = [
                    'lid_db_code' => $this->helper->generateDbCode(),
                    'lid_li_id' => $item['item_id'],
                    'lid_serial_number' => $item['serial_number'],
                    'lid_asset_id' => "AS" . $this->helper->generateDbCode(),
                    'lid_batch_no' => $item['batch_number'],
                    'lid_quantity' => 1,
                    'lid_uom_in_out' => $item['uom_inv'],
                    'lid_expired_date' =>  $expirationDate,
                    'lid_warranty_date' =>  $warrantyDate,
                    'lid_status' =>  1,
                    'lid_transfer_in_datetime' =>  date('Y-m-d H:i:s'),  
                    'lid_created_datetime' => date('Y-m-d H:i:s'),
                    'lid_created_by' => $authInfo['lu_name'] ?? 'system',
                ];
            
                $qaItemList[] = $data;
            }

            $this->itemDetail->createNewItemDetailAfterQaInspection($qaItemList);


            // UPDATE STOCK INTO INVENTORY SUMMARY


            $this->qaInspection->getDb()->commit();

        
        }catch (\PDOException $e) {
            // Rollback on database error
            $this->qaInspection->getDb()->rollBack();
            error_log("Failed to update QA: " . $e->getMessage());
            return $response->error('Failed to update QA: ' . $e->getMessage(), 500);
        } catch (\Exception $e) {
            // Rollback on other errors
            $this->qaInspection->getDb()->rollBack();
            error_log("Unexpected error: " . $e->getMessage());
            return $response->error('Unexpected error: ' . $e->getMessage(), 500);
        }

 
        return $response->success(null,  'Qa item serial list updated successfully');
    }

    public function updateQaItemList(Request $request, Response $response){
        // Validate authorization
        $authInfo = $request->getData('user');

        if (!$authInfo['user_access']['master_data_setting']['write']) {
            return $response->unauthorized('Permission denied');
        }

        // Check if qa inspection exists
        $code = $request->getData('code');
        $qaInspection = $this->qaInspection->findByCode($code, 2);

        if (!$qaInspection) {
            return $response->notFound('Record not found');
        }

        // Validate request data
        $errors = $request->validate([
            'qaItemList' => ['required']
        ]);

        if (!empty($errors)) {
            return $response->validationError($errors);
        }
        
        $qaItemList = $request->getData('qaItemList');

        $success = $this->qaInspection->updateQaItemListStatus($qaItemList);

        // INSERT GRN RECORD


        if (!$success) {
            return $response->error('Failed to update QA item list', 500);
        }

        return $response->success(null,  'Qa item list updated successfully');
    }

    public function getQaInfo(Request $request, Response $response){
        // Validate authorization
        $authInfo = $request->getData('user');

        if (!$authInfo['user_access']['master_data_setting']['write']) {
            return $response->unauthorized('Permission denied');
        }

        // Check if qa inspection exists
        $code = $request->getData('code');
        $qaInspection = $this->qaInspection->findByCode($code, 2);

        if (!$qaInspection) {
            return $response->notFound('Record not found');
        }

        $responseData = [
            "inspection" => $qaInspection,
        ];

        return $response->success($responseData, "Qa inspection info retrieved successfully");

    }
    
    public function getQaItemList(Request $request, Response $response)
    {
        // Validate authorization
        $authInfo = $request->getData('user');

        if (!$authInfo['user_access']['master_data_setting']['write']) {
            return $response->unauthorized('Permission denied');
        }
        
        // Check if qa inspection exists
        $code = $request->getData('code');
        $qaInspection = $this->qaInspection->findByCode($code, 2);

        if (!$qaInspection) {
            return $response->notFound('Record not found');
        }

        $qaRecordId = $qaInspection['id'];

        $result = $this->qaInspection->findQaItemList($qaRecordId);
        $inspectionItemList = $result["data"] ?? [];
        
        $itemIdList = [];

        foreach ($inspectionItemList as &$item) {
            $itemIdList[] = $item['li_id'];
        }

        $infoInfoList = $this->item->findItemBasicInfoByIdList($itemIdList);

        foreach ($inspectionItemList as &$item) {

            $liId = $item['li_id'];
            $item['li_code'] = $infoInfoList[$liId]['li_item_code'] ?? "";
            $item['li_name'] = $infoInfoList[$liId]['li_item_name'] ?? "";
        }


        $responseData = [
            "items" => $inspectionItemList,
        ];

        return $response->success($responseData, "Qa inspection item list retrieved successfully");

    }

    public function getAllQaList(Request $request, Response $response)
    {
        // Validate authorization
        $authInfo = $request->getData('user');

        if (!$authInfo['user_access']['master_data_setting']['write']) {
            return $response->unauthorized('Permission denied');
        }

        $result = $this->qaInspection->findAll();
        $inspectionList = $result["data"] ?? [];
        
        foreach ($inspectionList as &$inspection) {
            
            if($inspection['status'] == 1){
                $inspection['status'] = "Pending";
            }else if($inspection['status'] == 2){
                $inspection['status'] = "Reserved";
            }else if($inspection['status'] == 3){
                $inspection['status'] = "Completed";
            }else if($inspection['status'] == 4){
                $inspection['status'] = "Failed";
            }
        }

        // Step 5: Prepare and return response
        $responseData = [
            "inspections" => $inspectionList,
        ];

        return $response->success($responseData, "Qa inspection list retrieved successfully");
    
    }

    public function reserveQa(Request $request, Response $response)
    {
        // Validate authorization
        $authInfo = $request->getData('user');

        if (!$authInfo['user_access']['master_data_setting']['write']) {
            return $response->unauthorized('Permission denied');
        }
        
        // Check if qa inspection exists
        $code = $request->getData('code');
        $qaInspection = $this->qaInspection->findByCode($code);

        if (!$qaInspection) {
            return $response->notFound('Record not found');
        }

        // Prepare data for creation
        $data = [
            'qi_status' => 2,
            'qi_reserved_by' => $authInfo['lu_name'] ?? 'system',
            'qi_updated_by' => $authInfo['lu_name'] ?? 'system',
            'qi_updated_datetime' => date('Y-m-d H:i:s'),
        ];

        $success = $this->qaInspection->updateQaToReserve($code, $data);

        if (!$success) {
            return $response->error('Failed to update ', 500);
        }

        return $response->success(null, 'Reserved successfully');

    }


    /**
     * Update the specified qa inspection 
     */
    public function updateQaResult(Request $request, Response $response)
    {
        // Validate authorization
        $authInfo = $request->getData('user');

        if (!$authInfo['user_access']['master_data_setting']['write']) {
            return $response->unauthorized('Permission denied');
        }

        // Check if qa inspection exists
        $code = $request->getData('code');
        $location = $this->location->findByCode($code);

        if (!$location) {
            return $response->notFound('Location not found');
        }

        // Check if location code already exists
        if ($this->location->findByLocationCode($request->getData('locationCode'), $code)) {
            return $response->error('Location code already in use', 422);
        }

        // Validate request data
        $errors = $request->validate([
            'name' => ['required', 'length' => [1, 100]],
            'locationCode' => ['required', 'length' => [1, 50]],
            'type' => ['required'],
            'buildingId' => ['required'],
            'warehouseId' => ['required'],
            'status' => ['required']
        ]);


        if (!empty($errors)) {
            return $response->validationError($errors);
        }

        // Prepare data for creation
        $data = [
            'll_llt_id' => $request->getData('type'),
            'll_name' => $request->getData('name'),
            'll_code' => $request->getData('locationCode'),
            'll_system_description' => $request->getData('systemDescription'),
            'll_report_description' => $request->getData('reportDescription'),
            'll_lb_id' => $request->getData('buildingId'),
            'll_lw_id' => $request->getData('warehouseId'),
            'll_extra_info' =>  $request->getData('extraInfo'),
            'll_status' => $request->getData('status'),
            'll_updated_datetime' => date('Y-m-d H:i:s'),
            'll_updated_by' => $authInfo['lu_name'] ?? 'system'
        ];

        // Update the location
        $success = $this->location->update($code, $data);

        $messageHeader = "";
        if($request->getData('type') == "1"){
            $messageHeader = "Rack";
        }else if($request->getData('type') == "2"){
            $messageHeader = "Zone";
        }else if($request->getData('type') == "3"){
            $messageHeader = "Area";
        }


        if (!$success) {
            return $response->error('Failed to update ' . strtolower($messageHeader), 500);
        }

        return $response->success(null, $messageHeader . ' updated successfully');
    }

}