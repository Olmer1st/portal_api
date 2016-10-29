<?php

namespace middleware;
class AuthenticationMiddleware
{
    private function check_token($module_name, $request){
        if($module_name === "/v1/admin")
            return false;
        else
            return true;

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