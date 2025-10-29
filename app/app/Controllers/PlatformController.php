<?php

namespace App\Controllers;

use Core\Request;
use Core\Response;
use App\Models\Administration;
use App\Models\UserLogin;
use Utils\Helper;

class PlatformController
{   
    private $administration;
    private $userLogin;
    private $helper;
    private $common;

    public function __construct()
    {   
        $this->administration = new Administration();
        $this->userLogin = new UserLogin();
        $this->helper = new Helper();
        $this->common = \Core\Common::getInstance();
    }
    public function getPlantListToSelect(Request $request, Response $response)
    {   
        $credentials = [
            'accessUsername' => $request->getData('accessUsername'),
            'accessToken' => $request->getData('accessToken')
        ];
        //get admin api from Administration model
        $apiResult = $this->administration->plantListToSelect($credentials);
        $result = null;
        if ($apiResult['status_code']=='200') {
            $result = $apiResult['data'];
        } else {
           // Handle error
           return $response->error($apiResult['message'], $apiResult['status_code']);
        }
        return $response->success(
            $result,
            "Plants list retrieved successfully"
        );
    }
    public function getSiteListToSelect(Request $request, Response $response)
    {   
        $credentials = [
            'accessUsername' => $request->getData('accessUsername'),
            'accessToken' => $request->getData('accessToken')
        ];

        //get from admin controller
        $apiResult = $this->administration->siteListToSelect($credentials);

        $result = null;
        if ($apiResult['status_code']=='200') {
            $result = $apiResult['data'];
        } else {
           // Handle error
           return $response->error($apiResult['message'], $apiResult['status_code']);
        }
        return $response->success(
            $result,
            "Site information retrieved successfully"
        );
    }
    
    public function getUserListToSelect(Request $request, Response $response)
    {
      
        $credentials = [
            'accessUsername' => $request->getData('accessUsername'),
            'accessToken' => $request->getData('accessToken')
        ];

        //get from admin controller
        $apiResult = $this->administration->userListToSelect($credentials);

        $result = null;
        if ($apiResult['status_code']=='200') {
            $result = $apiResult['data'];
        } else {
           // Handle error
           return $response->error($apiResult['message'], $apiResult['status_code']);
        }
        return $response->success(
            $result,
            "User list retrieved successfully"
        );
    }

    public function getBuildingToSelect(Request $request, Response $response)
    {   
        $credentials = [
            'accessUsername' => $request->getData('accessUsername'),
            'accessToken' => $request->getData('accessToken')
        ];

        //get from admin controller
        $apiResult = $this->administration->buildingToSelect($credentials);

        $result = null;
        if ($apiResult['status_code']=='200') {
            $result = $apiResult['data'];
        } else {
           // Handle error
           return $response->error($apiResult['message'], $apiResult['status_code']);
        }
        return $response->success(
            $result,
            "Building list retrieved successfully"
        );
    }

    public function getSiteToSelect(Request $request, Response $response)
    {   
        $credentials = [
            'accessUsername' => $request->getData('accessUsername'),
            'accessToken' => $request->getData('accessToken')
        ];

        //get from admin controller
        $apiResult = $this->administration->siteToSelect($credentials);

        $result = null;
        if ($apiResult['status_code']=='200') {
            $result = $apiResult['data'];
        } else {
           // Handle error
           return $response->error($apiResult['message'], $apiResult['status_code']);
        }
        return $response->success(
            $result,
            "Site list retrieved successfully"
        );
    }

    public function getEntityToSelect(Request $request, Response $response)
    {   
        $credentials = [
            'accessUsername' => $request->getData('accessUsername'),
            'accessToken' => $request->getData('accessToken')
        ];

        //get from admin controller
        $apiResult = $this->administration->entityToSelect($credentials);

        $result = null;
        if ($apiResult['status_code']=='200') {
            $result = $apiResult['data'];
        } else {
           // Handle error
           return $response->error($apiResult['message'], $apiResult['status_code']);
        }
        return $response->success(
            $result,
            "Entity list retrieved successfully"
        );
    }

    public function getBasicProfile(Request $request, Response $response)
    {   
        $credentials = [
            'accessUsername' => $request->getData('accessUsername'),
            'accessToken' => $request->getData('accessToken')
        ];

       //get from admin controller
        $apiResult = $this->administration->basicProfile($credentials);
        $userDbCode =$apiResult['data']['user']['db_code'];

        // find existing record and update last login
        $findByRecord= $this->userLogin->findByRecord($userDbCode);

        $apiResult = $this->administration->basicProfile($credentials);

        $result = null;
        if ($apiResult['status_code']=='200') {
            $result = $apiResult['data'];
            $result['user']['last_login'] = $findByRecord['ul_last_login'];
        } else {
           // Handle error
           return $response->error($apiResult['message'], $apiResult['status_code']);
        }
        return $response->success(
            $result,
            "User found"
        );
    }

    public function getUserPlantList(Request $request, Response $response)
    {
        $credentials = [
            'accessUsername' => $request->getData('accessUsername'),
            'accessToken' => $request->getData('accessToken')
        ];

        //get from admin controller
        $apiResult = $this->administration->userPlantList($credentials);

        $result = null;
        if ($apiResult['status_code']=='200') {
            $result = $apiResult['data'];
        } else {
           // Handle error
           return $response->error($apiResult['message'], $apiResult['status_code']);
        }
        return $response->success(
            $result,
            "User Plant List found"
        );
    }
    public function getPlatformList(Request $request, Response $response)
    {
        // Validate current platform type
        $ValidationError = $request->validate([
            "currentPlatformType" => ["required"],
        ]);

        if (!empty($ValidationError)) {
            return $response->validationError($ValidationError);
        }

        $credentials = [
            'accessUsername' => $request->getData('accessUsername'),
            'accessToken' => $request->getData('accessToken'),
            'currentPlatformType' => $request->getData('currentPlatformType')
        ];

        //get from admin controller
        $apiResult = $this->administration->getPlatformList($credentials);

        $result = null;
        if ($apiResult['status_code']=='200') {
            $result = $apiResult['data'];
        } else {
           // Handle error
           return $response->error($apiResult['message'], $apiResult['status_code']);
        }
        return $response->success(
            $result,
            "User platform list found"
        );
    }
}