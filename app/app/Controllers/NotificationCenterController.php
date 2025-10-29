<?php

namespace App\Controllers;

use Core\Request;
use Core\Response;
use App\Models\NotificationCenter;
use Utils\Helper;

class NotificationCenterController
{
    private $notificationCenterModel;
    private $helper;

    public function __construct()
    {
        $this->notificationCenterModel = new NotificationCenter();
        $this->helper = new Helper();
    }

    /**  
     * Get all notification center records with pagination and search  
     */
    public function getAllNotificationCenter(Request $request, Response $response)
    {
        // Validate authorization
        $authInfo = $request->getData('user');

        if (!$authInfo['user_access']['notification_center']['read']) {
            return $response->unauthorized('Permission denied');
        }

        $page = (int) $request->getData("page");
        $limit = (int) $request->getData("limit");
        $orderBy = 'nc_created_datetime';
        $orderDirection = 'DESC';
        $status = $request->getData("status", "*");
        $type = $request->getData("type");
        $search = $request->getData("search");

        // Validate page and limit  
        if ($page < 1) {
            return $response->error("Page must be ≥ 1", 400);
        }
        if ($limit < 1 || $limit > 100) { // Prevent abuse  
            return $response->error("Limit must be between 1-100", 400);
        }

        // Fetch data with pagination  
        $result = $this->notificationCenterModel->findAllWithPaginationV2(
            $page,
            $limit,
            $orderBy,
            $orderDirection,
            $status,
            $type,
            $search
        );

        // Prepare response  
        $responseData = [
            "notificationCenter" => $result["data"],
            "total" => intval($result["total"]),
            "page" => intval($result["page"]),
            "limit" => intval($result["limit"]),
            "pages" => intval(ceil($result["total"] / $result["limit"]))
        ];

        return $response->success($responseData, "Notification center records retrieved successfully");
    }

    /**
     * Store a newly created notification center record
     */
    public function createNotificationCenter(Request $request, Response $response)
    {
        // Validate authorization
        $authInfo = $request->getData('user');

        if (!$authInfo['user_access']['notification_center']['write']) {
            return $response->unauthorized('Permission denied');
        }

        // Validate request data
        $errors = $request->validate(rules: [
            'type' => ['required', 'length' => [2, 20]],
            'title' => ['required'],
            'content' => ['required'],
            'recipientList' => ['required'],
            'luDepartment' => [],
            'lpPlantDbCode' => [],
        ]);

        if (!empty($errors)) {
            return $response->validationError($errors);
        }

        // Prepare data for creation
        $data = [
            'nc_db_code' => $this->helper->generateDbCode(),
            'nc_type' => $request->getData('type'),
            'nc_title' => $request->getData('title'),
            'nc_content' => $request->getData('content'),
            'nc_recipient_list' => $request->getData('recipientList'),
            'nc_lu_department' => $request->getData('luDepartment'),
            'nc_lp_plant_db_code' => $request->getData('lpPlantDbCode'),
            'nc_status' => 1,
            'nc_created_datetime' => date('Y-m-d H:i:s'),
            'nc_created_by' => $authInfo['lu_name'] ?? 'system'
        ];

        // Create notification center record
        $notificationCenterId = $this->notificationCenterModel->create($data);

        if (!$notificationCenterId) {
            return $response->error('Failed to create notification center record', 500);
        }

        // Get the created notification center record
        // $notificationCenter = $this->notificationCenterModel->findById($notificationCenterId);


        return $response->created(null, 'Notification center record created successfully');
    }

    /**
     * Display the specified notification center record
     */
    public function getNotificationCenter(Request $request, Response $response)
    {
        // Validate authorization
        $authInfo = $request->getData('user');

        if (!$authInfo['user_access']['notification_center']['read']) {
            return $response->unauthorized('Permission denied');
        }

        $code = $request->getData('code');
        $notificationCenter = $this->notificationCenterModel->findByCode($code);

        if (!$notificationCenter) {
            return $response->notFound('Notification center record not found');
        }

        return $response->success(['notificationCenter' => $notificationCenter], 'Notification center record found');
    }

    /**
     * Update the specified notification center record
     */
    public function updateNotificationCenter(Request $request, Response $response)
    {
        // Validate authorization
        $authInfo = $request->getData('user');

        if (!$authInfo['user_access']['notification_center']['write']) {
            return $response->unauthorized('Permission denied');
        }

        // Check if notification center record exists
        $code = $request->getData('code');
        $notificationCenter = $this->notificationCenterModel->findByCode($code);

        if (!$notificationCenter) {
            return $response->notFound('Notification center record not found');
        }

        // Convert status to integer before validation
        if ($request->has('status')) {
            $request->setData('status', intval($request->getData('ncStatus')));
        }

        // Validate request data
        $errors = $request->validate([
            'type' => ['required', 'length' => [2, 20]],
            'title' => ['required'],
            'content' => ['required'],
            'recipientList' => [],
            'luDepartment' => [],
            'lpPlantDbCode' => [],
            'status' => ['required', 'numeric']
        ]);

        if (!empty($errors)) {
            return $response->validationError($errors);
        }

        // Prepare update data
        $data = [];

        // Map camelCase request fields to snake_case database fields
        $fieldMappings = [
            'type' => 'nc_type',
            'title' => 'nc_title',
            'content' => 'nc_content',
            'recipientList' => 'nc_recipient_list',
            'luDepartment' => 'nc_lu_department',
            'lpPlantDbCode' => 'nc_lp_plant_db_code',
            'status' => 'nc_status'
        ];

        // Only include fields that are provided
        foreach ($fieldMappings as $requestField => $dbField) {
            if ($request->has($requestField)) {
                $data[$dbField] = $request->getData($requestField);
            }
        }

        // Update the notification center record
        $success = $this->notificationCenterModel->update($code, $data);

        if (!$success) {
            return $response->error('Failed to update notification center record', 500);
        }

        // Get the updated notification center record
        // $updatedNotificationCenter = $this->notificationCenterModel->findByCode($code);

         //insert action logs
        $request->setData('log_status', 'write');

        return $response->success(null, 'Notification center record updated successfully');
    }

    /**
     * Delete the specified notification center record
     */
    public function deleteNotificationCenter(Request $request, Response $response)
    {
        // Validate authorization
        $authInfo = $request->getData('user');

        if (!$authInfo['user_access']['notification_center']['write']) {
            return $response->unauthorized('Permission denied');
        }
        
        // Check if notification center record exists
        $code = $request->getData('code');
        $notificationCenter = $this->notificationCenterModel->findByCode($code);

        if (!$notificationCenter) {
            return $response->notFound('Notification center record not found');
        }

        // Delete the notification center record (soft delete)
        $success = $this->notificationCenterModel->delete($code);

        if (!$success) {
            return $response->error('Failed to delete notification center record', 500);
        }
        

        return $response->success(null, 'Notification center record deleted successfully');
    }
}
