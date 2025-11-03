<?php

namespace App\Models;

class Administration
{
    private $db;
    private $common;
    private $config;

    public function __construct()
    {
        $this->db = \Core\Database::getInstance();
        $this->common = \Core\Common::getInstance();
        $this->config = require APP_ROOT . '/config/app.php';
    }
    //return platform name
    public function returnPlatform()
    {
        return $this->config['platform'] ?? 'admin';
    }
    function adminAPI(string $endpoint, array $payload): array
    {
        $url = $this->config['administration_url'] . $endpoint;

        $postFields = http_build_query($payload);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            // handle error
            curl_close($curl);
            return false;
        }

        curl_close($curl);
        return json_decode($response, true); 
       
    }
    function wmsAPI(string $endpoint, array $payload): array
    {
        $url = $this->config['wms_api_url'] . $endpoint;

        $postFields = http_build_query($payload);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $postFields,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/x-www-form-urlencoded'
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false
        ]);

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            // handle error
            curl_close($curl);
            return false;
        }

        curl_close($curl);
        return json_decode($response, true); 
       
    }
    function warehouseListToSelect($credentials)
    {   
       // Get from wms controller
        $apiResult = $this->wmsAPI('/api/v1/warehouse/all-warehouse-list-to-select', $credentials);
        return $apiResult;
    }
    function itemInfoByIdList($credentials)
    {   
       // Get from wms controller
        $apiResult = $this->wmsAPI('/api/v1/item-external/item-info-by-id-list', $credentials);
        return $apiResult;
    }
    function plantListToSelect($credentials)
    {   
       // Get from admin controller
        $apiResult = $this->adminAPI('/api/v1/plant/plant-list-to-select', $credentials);
        return $apiResult;
    }
    function siteListToSelect($credentials)
    {   
       // Get from admin controller
        $apiResult = $this->adminAPI('/api/v1/site/site-list-to-select', $credentials);
        return $apiResult;
    }
    function userListToSelect($credentials)
    {   
       // Get from admin controller
        $apiResult = $this->adminAPI('/api/v1/user/user-list-to-select', $credentials);
        return $apiResult;
    }
    function buildingToSelect($credentials)
    {   
       // Get from admin controller
        $apiResult = $this->adminAPI('/api/v1/building/building-list-to-select', $credentials);
        return $apiResult;
    }
    function siteToSelect($credentials)
    {   
       // Get from admin controller
        $apiResult = $this->adminAPI('/api/v1/site/site-list-to-select', $credentials);
        return $apiResult;
    }
    function entityToSelect($credentials)
    {   
       // Get from admin controller
        $apiResult = $this->adminAPI('/api/v1/entity/entity-list-to-select', $credentials);
        return $apiResult;
    }
    function basicProfile($credentials)
    {   
       // Get from admin controller
        $apiResult = $this->adminAPI('/api/v1/user/get-user-profile', $credentials);
        return $apiResult;
    }
    function userPlantList($credentials)
    {   
       // Get from admin controller
        $apiResult = $this->adminAPI('/api/v1/user/get-user-plant-list', $credentials);
        return $apiResult;
    }
    function verifyAccount($credentials)
    {   
       // Get from admin controller
        $apiResult = $this->adminAPI('/api/v1/account/verify', $credentials);
        return $apiResult;
    }
    function authCheck($credentials)
    {   
       // Get from admin controller
        $apiResult = $this->adminAPI('/api/v1/account/me', $credentials);
        return $apiResult;
    }
    function verifySecondaryPassword($credentials)
    {   
       // Get from admin controller
        $apiResult = $this->adminAPI('/api/v1/account/verify-secondary-password', $credentials);
        return $apiResult;
    }
    function verifyPlatformAccessToken($credentials)
    {   
       // Get from admin controller
        $apiResult = $this->adminAPI('/api/v1/account/verify-platform-access-token', $credentials);
        return $apiResult;
    }
    function getPlatformAccessToken($credentials)
    {   
       // Get from admin controller
        $apiResult = $this->adminAPI('/api/v1/account/get-platform-access-token', $credentials);
        return $apiResult;
    }
    function getPlatformList($credentials)
    {   
       // Get from admin controller
        $apiResult = $this->adminAPI('/api/v1/user/get-platform-list', $credentials);
        return $apiResult;
    }
    function logout($credentials)
    {   
       // Get from admin controller
        $apiResult = $this->adminAPI('/api/v1/account/logout', $credentials);
        return $apiResult;
    }
    
}
