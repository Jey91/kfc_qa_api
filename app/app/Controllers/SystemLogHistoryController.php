<?php

namespace App\Controllers;

use Core\Request;
use Core\Response;
use App\Models\SystemLogHistory;
use App\Models\Administration;

class SystemLogHistoryController
{
    private $logModel;
    private $administration;

    public function __construct()
    {
        $this->logModel = new SystemLogHistory();
        $this->administration = new Administration();
    }

    /**  
     * Get all logs with pagination and filters  
     */
    public function getAllLogs(Request $request, Response $response)
    {
        // Validate authurization
        $authInfo = $request->getData('user');

        if (!$authInfo['user_access']['mes_system_log']['read']) {
            return $response->unauthorized('Permission denied');
        }

        // Validate request data
        $page = (int) $request->getData('page') ?: 1;
        $limit = (int) $request->getData('limit') ?: 10;

        // Get filters from request
        $filters = [
            'plant_id' => $request->getData('plantId'),
            'module' => $request->getData('module'),
            'created_by' => $request->getData('userId'), // Changed from createdBy to userId to match the UI
            'date_from' => $request->getData('dateFrom'),
            'date_to' => $request->getData('dateTo'),
            'search' => $request->getData('search'),
            'lp_plant_db_code' => $request->getData('plantId'),
            'ip_address' => $request->getData('ipAddress') // Added IP address filter
        ];

        // Validate page and limit  
        if ($page < 1) {
            return $response->error("Page must be ≥ 1", 400);
        }
        if ($limit < 1 || $limit > 999999) { // Prevent abuse  
            return $response->error("Limit must be between 1-999999", 400);
        }

        $credentials = [
            'accessUsername' => $request->getData('accessUsername'),
            'accessToken' => $request->getData('accessToken')
        ];

        //get from admin controller
        $apiResult = $this->administration->plantListToSelect($credentials);
        $plantData = [];
        if ($apiResult['status_code']=='200') {
            $plantData = $apiResult['data'];
        };

        // Fetch data with pagination  
        $result = $this->logModel->findAllWithPagination(
            $page,
            $limit,
            $filters,
            $plantData
        );

        // Prepare response  
        $responseData = [
            "logs" => $result["data"],
            "total" => intval($result["total"]),
            "page" => intval($result["page"]),
            "limit" => intval($result["limit"]),
            "pages" => intval(ceil($result["total"] / $result["limit"]))
        ];

        return $response->success($responseData, "System logs retrieved successfully");
    }

    /**
     * Create a new log entry
     */
    public function createLog(Request $request, Response $response)
    {
        // Validate request data
        $errors = $request->validate([
            'plantId' => ['required', 'length' => [1, 2]],
            'ipAddress' => ['required', 'length' => [7, 100]],
            'subject' => ['required', 'length' => [3, 100]],
            'content' => ['required'],
            'module' => ['required', 'length' => [3, 30]]
        ]);

        if (!empty($errors)) {
            return $response->validationError($errors);
        }

        // Prepare data for creation
        $data = [
            'slh_lp_plant_db_code' => $request->getData('plantId'),
            'slh_ip_address' => $request->getData('ipAddress'),
            'slh_subject' => $request->getData('subject'),
            'slh_content' => $request->getData('content'),
            'slh_module' => $request->getData('module'),
            'slh_created_datetime' => date('Y-m-d H:i:s'),
            'slh_created_by' => $authInfo['lu_name'] ?? 'system'
        ];

        // Create log entry
        $logId = $this->logModel->create($data);

        if (!$logId) {
            return $response->error('Failed to create log entry', 500);
        }

        // Get the created log
        $log = $this->logModel->findById($logId);

        return $response->created(['log' => $log], 'Log entry created successfully');
    }

    /**
     * Display the specified log
     */
    public function getLog(Request $request, Response $response)
    {
        $id = $request->getData('id');
        $log = $this->logModel->findById($id);

        if (!$log) {
            return $response->notFound('Log entry not found');
        }

        return $response->success(['log' => $log], 'Log entry found');
    }

    /**
     * Get unique modules for filtering
     */
    public function getUniqueModules(Request $request, Response $response)
    {
        $modules = $this->logModel->getUniqueModules();

        return $response->success(['modules' => $modules], 'Modules retrieved successfully');
    }

    /**
     * Get unique users for filtering
     */
    public function getUniqueUsers(Request $request, Response $response)
    {
        $users = $this->logModel->getUniqueUsers();

        return $response->success(['users' => $users], 'Users retrieved successfully');
    }
}
