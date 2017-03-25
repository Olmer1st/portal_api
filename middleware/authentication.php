<?php

namespace middleware;

use Lcobucci\JWT\Builder;

class AuthenticationMiddleware
{
    private function check_token($module_name, $request){
        if($module_name === "/v1/admin")
            return true;
        else
            return true;

    }
    public function createToken($user)
    {
        global $config;
        $token = (new Builder())->issuedBy($user["email"]) // Configures the issuer (iss claim)
        ->canOnlyBeUsedBy($user["email"]) // Configures the audience (aud claim)
        ->identifiedBy($config->secret_key, true) // Configures the id (jti claim), replicating as a header item
        ->issuedAt(time()) // Configures the time that the token was issue (iat claim)
//        ->canOnlyBeUsedAfter(time() + 60) // Configures the time that the token can be used (nbf claim)
//        ->expiresAt(time() + 3600) // Configures the expiration time of the token (nbf claim)
//        ->with('uid', 1) // Configures a new claim, called "uid"
        ->getToken(); // Retrieves the generated token

        return (string)$token;

    }
    public function __invoke($request, $response, $next)
    {
        $uri = $request->getUri();
        $module_name = $uri->getBasePath();

        $authenticated = $this->check_token( $module_name, $request);
        if($authenticated)
            $response = $next($request, $response);
        else
            return $response->withStatus(401);
        return $response;
    }
}