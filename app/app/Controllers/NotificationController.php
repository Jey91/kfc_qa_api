<?php

namespace App\Controllers;

use Core\Request;
use Core\Response;
use App\Models\Notification;
use Utils\Helper;

class NotificationController
{
    private $notificationModel;
    private $helper;

    public function __construct()
    {
        $this->notificationModel = new Notification();
        $this->helper = new Helper();
    }

    /**  
     * Get all notification center records with pagination and search  
     */
    public function getAllNotification(Request $request, Response $response)
    {
        // Validate authorization
        $authInfo = $request->getData('user');
        $selectedPlant = $request->getData('globalPlantDbCode');
        // $userGroup = $authInfo['user_group']['db_code'];
        $userDepartment = $authInfo['user_department'];
        
        $page = (int) $request->getData("page");
        $limit = (int) $request->getData("limit");

        // Validate page and limit  
        if ($page < 1) {
            return $response->error("Page must be ≥ 1", 400);
        }
        if ($limit < 1 || $limit > 100) { // Prevent abuse  
            return $response->error("Limit must be between 1-100", 400);
        }

        // Fetch data with pagination  
        $result = $this->notificationModel->getNotificationsWithReadStatus($authInfo['lu_db_code'], $selectedPlant, $userDepartment, $page, $limit);

        foreach ($result["data"] as $key => $value) {
            $result["data"][$key]["timestamp"] = $this->helper->formatDate($value["created_datetime"], 'd/m/Y h:iA');
        }
        // Prepare response  
        $responseData = [
            "notifications" => $result["data"],
            "total" => intval($result["total"]),
            "unread_count" => intval($result["unread_count"]),
            "page" => intval($result["page"]),
            "limit" => intval($result["limit"]),
        ];

        return $response->success($responseData, "Notification records retrieved successfully");
    }

     public function getNotificationCount(Request $request, Response $response)
    {
        // Validate authorization
        $authInfo = $request->getData('user');
        $selectedPlant = $request->getData('globalPlantDbCode');
        // $userGroup = $authInfo['user_group']['db_code'];
        $userDepartment = $authInfo['user_department'];

        $page = 1;
        $limit = 99999;
        
        // Fetch data with pagination  
        $result = $this->notificationModel->getNotificationsWithReadStatus($authInfo['lu_db_code'], $selectedPlant, $userDepartment, $page, $limit);
        // Prepare response  
        $responseData = [
            "unread_count" => intval($result["unread_count"])
        ];

        return $response->success($responseData, "Notification unread records have been retrieved successfully.");
    }
    /**
     * Display the specified notification record
     */
    public function getNotification(Request $request, Response $response)
    {
        // Validate authorization
        $authInfo = $request->getData('user');

        $code = $request->getData('code');
        $notification = $this->notificationModel->getByCode($code);
        if (!$notification) {
            return $response->notFound('Notification record not found');
        }

        if ($notification) {
            // Mark as read
            $this->notificationModel->markAsRead($notification['db_code'], $authInfo['lu_db_code']);
            $notification['time_ago'] = $this->helper->formatDate($notification["created_datetime"], 'd/m/Y h:iA');
        }

        return $response->success(['notification' => $notification], 'Notification record found');
    }
}
