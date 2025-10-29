<?php

namespace App\Controllers;

use Core\Request;
use Core\Response;
use App\Models\UserLogin;
use App\Models\Administration;
use Utils\Helper;

class UserController
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

    /**
     * Authenticate a user
     */
    public function login(Request $request, Response $response)
    {
        // Validate request data
        $errors = $request->validate([
            'username' => ['required'],
            'password' => ['required']
        ]);

        if (!empty($errors)) {
            return $response->validationError($errors);
        }
        
        $credentials = [
            'username' => $request->getData('username'),
            'password' => $request->getData('password'),
            'platform' => $this->administration->returnPlatform(),
            'fromPlatform' => 'qa'
        ];
        
        //go admin api to check
        $apiResult = $this->administration->verifyAccount($credentials);
       
        $userData = null;

        if ($apiResult['status_code']=='200') {
            $userData = $apiResult['data'];

            // // Generate a new access token
            // $accessToken = $this->helper->generateToken(50);

            // Get from api
            $accessToken = $userData['access_token'];
            $userDbCode = $userData['db_code'];
            $platformLoginDbCode = $userData['pl_db_code'];
            
            // find existing record and update
            $findByRecord= $this->userLogin->findByRecord($userDbCode);
          
            if ($findByRecord) {
                //$newAccessToken = $this->helper->generateToken(50);
                // Prepare update data
                $updateData = [
                    'ul_pl_db_code' => $platformLoginDbCode,
                    'ul_access_token' => $accessToken,
                    'ul_last_login' => date('Y-m-d H:i:s')
                ];
                // Perform update
                $updateUserLoginSuccess = $this->userLogin->update($findByRecord['ul_id'], $updateData);

                if (!$updateUserLoginSuccess) {
                    return $response->error('Failed to update record', 500);
                }
            }else{
                // If record does not exist then create
                // Prepare data for creation
                $data = [
                    'ul_pl_db_code' => $platformLoginDbCode,
                    'ul_lu_db_code' => $userDbCode,
                    'ul_access_token'=> $accessToken,
                    'ul_last_login' => date('Y-m-d H:i:s')
                ];
                // insert record
                $insertUserLogin = $this->userLogin->create($data);
                
                if (!$insertUserLogin) {
                    return $response->error('Failed to create record', 401);
                }
            }
            
        } else {
           // Handle error
           return $response->error($apiResult['message'], $apiResult['status_code']);
        }
        
        return $response->success($userData, 'Login successful');
    }

    /**
     * Validate platform accesss token
     */
    public function validatePlatformAccessToken(Request $request, Response $response)
    {
        // Validate request data
        $errors = $request->validate([
            'platformAccessToken' => ['required'],
        ]);

        if (!empty($errors)) {
            return $response->validationError($errors);
        }
        
        // Validate authorization
        $longToken = $request->getData("platformAccessToken");
      
        $credentials = [
            'platformAccessToken' => $longToken,
            'platform' => $this->administration->returnPlatform()
        ];

        $apiResult = $this->administration->verifyPlatformAccessToken($credentials);
        
        $userData = null;
        
        if ($apiResult['status_code']=='200') {
            $userData = $apiResult['data'];
            // Get from api
            $accessToken = $userData['access_token'];
            $userDbCode = $userData['db_code'];
            $platformLoginDbCode = $userData['pl_db_code'] ?? null;
            
            // find existing record and update
            $findByRecord= $this->userLogin->findByRecord($userDbCode);
          
            if ($findByRecord) {
                //$newAccessToken = $this->helper->generateToken(50);
                // Prepare update data
                $updateData = [
                    'ul_pl_db_code' => $platformLoginDbCode,
                    'ul_access_token' => $accessToken,
                    'ul_last_login' => date('Y-m-d H:i:s')
                ];
                // Perform update
                $updateUserLoginSuccess = $this->userLogin->update($findByRecord['ul_id'], $updateData);

                if (!$updateUserLoginSuccess) {
                    return $response->error('Failed to update record', 500);
                }
            }else{
                // If record does not exist then create
                // Prepare data for creation
                $data = [
                    'ul_pl_db_code' => $platformLoginDbCode,
                    'ul_lu_db_code' => $userDbCode,
                    'ul_access_token'=> $accessToken,
                    'ul_last_login' => date('Y-m-d H:i:s')
                ];
                // insert record
                $insertUserLogin = $this->userLogin->create($data);
                
                if (!$insertUserLogin) {
                    return $response->error('Failed to create record', 401);
                }
            }
        }else{
           // Handle error
           return $response->error($apiResult['message'], $apiResult['status_code']);
        }
        return $response->success($userData, 'Access via token successful.');
    }

    /**
     * generate platform accesss from admin api
     */
    public function generatePlatformAccessToken(Request $request, Response $response)
    {
        
        $credentials = [
            'accessUsername' => $request->getData('accessUsername'),
            'accessToken' => $request->getData('accessToken')
        ];

        //get from admin controller
        $apiResult = $this->administration->getPlatformAccessToken($credentials);

        $result = null;
        if ($apiResult['status_code']=='200') {
            $result = $apiResult['data'];
        } else {
           // Handle error
           return $response->error($apiResult['message'], $apiResult['status_code']);
        }
        return $response->success(
            $result,
            "The platform access token was generated successfully."
        );
    }

    /**
     * Deauthenticate a user
     */
    public function logout(Request $request, Response $response)
    {

        $getToken = $request->getData('accessToken');
        $getUsername = $request->getData('accessUsername');
        // get data from user_login first
        $userLoginData = $this->userLogin->findByToken($getToken);
        if (!$userLoginData){
            return $response->unauthorized('Invalid token', 401);
        }

        $accessToken = $this->helper->generateToken(50);
        // Prepare update data
        $updateData = [
            'ul_access_token' => $accessToken
        ];
        $this->userLogin->update($userLoginData['ul_id'], $updateData);


        // If you don't want to call the administrator platform login to reset the token, you can remove this.

        // Only include this condition if you want to log the user out from all connected platforms, not just the current one.

        $credentials = [
            'accessUsername' => $getUsername,
            'accessToken' => $getToken
        ];

        // Call logout from the admin controller
        $apiResult = $this->administration->logout($credentials);
        $response->error($apiResult['message'], $apiResult['status_code']);

        // End of condition
        // /////////////////

        return $response->success(null, 'Logout successful');
    }
}