<?php

namespace App\Middleware;

use Core\Request;
use Core\Response;
use App\Models\Administration;
use App\Models\UserLogin;

class AuthMiddleware
{   
    private $administration;
    private $userLogin;

    public function __construct()
    {
        $this->administration = new Administration();
        $this->userLogin = new UserLogin();
    }

    public function handle(Request $request, Response $response, callable $next)
    {
        $token = $request->getData('accessToken');
        $username = $request->getData('accessUsername');
        $plantCode = $request->getData('globalPlantDbCode');

        if (empty($token) || empty($username)) {
            return $response->unauthorized('Authentication required');
        }
        
        // error_log('AuthMiddleware: Token: ' . $token);
        if (!$token || !$username ) {
            return $response->unauthorized('Authentication required');
        }
        
        // get data from user_login first
        $userLoginData = $this->userLogin->findByToken($token);
        if (!$userLoginData){
            return $response->unauthorized('Invalid token');
        }
       
        $credentials = [
            'accessUsername' => $username,
            'accessToken' => $token,
            'plDbCode' => $userLoginData['ul_pl_db_code'],
            'luDbCode' => $userLoginData['ul_lu_db_code'],
            'platform' => $this->administration->returnPlatform()
        ];
        //go admin api to check
        $apiResult = $this->administration-> authCheck($credentials);
      
        $userData = null;

        if ($apiResult['status_code']=='200') {
            $userData = $apiResult['data'];
        } else {
           // Handle error
           return $response->error($apiResult['message'], $apiResult['status_code']);
        }
        // Set user data in request
        $request->setData('user', $userData);

        // Continue to next middleware or route handler
        return $next($request, $response);
    }
}
