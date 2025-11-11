<?php

namespace App\Middleware;

use Core\Request;
use Core\Response;
use Core\Context;
use Core\Database;
use App\Models\Administration;
use App\Models\UserLogin;
use Utils\Helper;

class PublicKeyAuthMiddleware
{   
    protected $administrationModel;

    public function __construct()
    {
        $this->administrationModel = new Administration();
    }
    public function handle(Request $request, Response $response, callable $next)
    {   
        $errors = $request->validate([
            "accessUsername" => ["required"],
            "publicAccessKey" => ["required"],
        ]);
        if (!empty($errors)) {
            return $response->validationError($errors);
        }
        
        $username = $request->getData('accessUsername');
        $publicAccessKey = $request->getData('publicAccessKey');

        $credentials = [
            'accessUsername' => $username,
            'publicAccessKey' => $publicAccessKey
        ];

        //go admin api to check
        $apiResult = $this->administrationModel-> verifyPublicAccessKey($credentials);
        
        if ($apiResult['status_code'] == '200') {
            
            $connectionName = $request->getData('connectionName');
            if ($connectionName){
                \Core\Context::setActiveConnection($username, $connectionName);

                $activeConn = Context::getActiveConnection($username);
                if (!empty($activeConn)) {
                    Database::selectConnection($activeConn);
                }
            }
            return $next($request, $response);
        }else{
            return $response->error($apiResult['message'], $apiResult['status_code']);
        }
    }
}

