<?php

namespace App\Middleware;

use Core\Request;
use Core\Response;
use App\Models\Administration;

class VerifySecondaryPasswordMiddleware
{
    protected $administrationModel;

    public function __construct()
    {
        $this->administrationModel = new Administration();
    }

    public function handle(Request $request, Response $response, callable $next)
    {   
         $errors = $request->validate([
            "verifySecondaryPassword" => ["required"],
        ]);

        if (!empty($errors)) {
            return $response->validationError($errors);
        }

        $token = $request->getData('accessToken');
        $username = $request->getData('accessUsername');
        $verifySecondaryPassword = $request->getData('verifySecondaryPassword');

        $credentials = [
            'accessUsername' => $username,
            'accessToken' => $token,
            'verifySecondaryPassword' => $verifySecondaryPassword
        ];
      
        //go admin api to check
        $apiResult = $this->administrationModel-> verifySecondaryPassword($credentials);
        
        if ($apiResult['status_code'] == '200') {
             return $next($request, $response);
        }else{
            return $response->error($apiResult['message'], $apiResult['status_code']);
        }
    }
}
