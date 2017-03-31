<?php

namespace middleware;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use \Administration\Users as Users;

//include $_SERVER['DOCUMENT_ROOT'] . '/modules/users.php';

class AuthenticationMiddleware
{
    private function check_token($module_name, $request)
    {
        if($request->isOptions()) return true;
        $tokenStr = $request->getHeaderLine("x-access-token");
        if ($module_name === "/v1/admin") {
            if (empty($tokenStr) || strlen($tokenStr) < 10) return false;
            $token = (new Parser())->parse((string) $tokenStr); // Parses from a string
            $role = $token->getClaim('role');
            return $role ==='admin';
        }
        elseif ($module_name === "/v1/public")
            return true;
        else{
            if (empty($tokenStr) || strlen($tokenStr) < 10) return false;
            $token = (new Parser())->parse((string) $tokenStr); // Parses from a string
            $role = $token->getClaim('role');
            if($role === 'admin') return true;
            $modules= $token->getClaim('modules');
            foreach ($modules as $module){
                if(strpos($module_name, $module) !== false) return true;
            }
            return false;
        }
    }

    public function authenticate($db, $tokenStr){
        if(empty($tokenStr) || strlen($tokenStr)< 10) return array("error"=> "Wrong user token length");
        try{
            $token = (new Parser())->parse((string) $tokenStr); // Parses from a string
            $uid = $token->getClaim('uid');
            return Users::get_user_by_uid($db, $uid);
        }
        catch (Exception $e) {
            return array("error"=> $e->getMessage());
        }

    }
    public function createToken($user)
    {
        global $config;
        $signer = new Sha256();
        $token = (new Builder())->issuedBy($user["email"])// Configures the issuer (iss claim)
        ->canOnlyBeUsedBy($user["email"])// Configures the audience (aud claim)
//        ->identifiedBy('4f1g23a12aa', true) // Configures the id (jti claim), replicating as a header item
        ->issuedAt(time())// Configures the time that the token was issue (iat claim)
//        ->canOnlyBeUsedAfter(time() + 60) // Configures the time that the token can be used (nbf claim)
//        ->expiresAt(time() + 3600) // Configures the expiration time of the token (nbf claim)
        ->with('uid', $user["uid"])// Configures a new claim, called "uid"
        ->with('role', $user["role"])// Configures a new claim, called "role"
        ->with('modules',$user["modules"] )// Configures a new claim, called "modules" join(",",$user["modules"])
        ->sign($signer, $config->secret_key)// creates a signature using "testing" as key
        ->getToken(); // Retrieves the generated token

        return (string)$token;

    }

    public function __invoke($request, $response, $next)
    {
        $uri = $request->getUri();
        $module_name = $uri->getBasePath();

        $authenticated = $this->check_token($module_name, $request);
        if ($authenticated)
            $response = $next($request, $response);
        else
            return $response->withStatus(401);
        return $response;
    }
}