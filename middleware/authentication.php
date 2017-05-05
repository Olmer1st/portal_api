<?php

namespace middleware;

use Lcobucci\JWT\Builder;
use Lcobucci\JWT\Parser;
use Lcobucci\JWT\Signer\Hmac\Sha256;
use modules\Administration\Users as Users;

//require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/users.php';
use NotORM_Literal;

require_once $_SERVER['DOCUMENT_ROOT'] . "/middleware/utils.php";

class AuthenticationMiddleware
{
    private $notOrm = null;
    private $parser = null;

    function __construct($db)
    {
        $this->notOrm = $db;
        $this->parser = new Parser();
    }

    private function logSession($token)
    {
        $ip = getUserIP();
//        $this->notOrm->portal_navigation()->insert(array("token" => $token,"ip" => $ip, "time" => new NotORM_Literal("NOW()")));
        if(!isset($token)) $token = uniqid();
        $new = $this->notOrm->portal_navigation()->where("token", $token)->and("ip",$ip);
        if(isset($new) && $new->count()>=1){
            $row = $new->fetch();
            $cnt = $row["log_count"];
            $new->update(array("token" => $token, "ip" => $ip, "log_count"=>$cnt+1, "time" =>new NotORM_Literal("NOW()")));
        }else{
            $this->notOrm->portal_navigation()->insert(array("token" => $token,"ip" => $ip,"log_count"=>1, "time" => new NotORM_Literal("NOW()")));
        }

    }
    public function __get($property)
    {
        if (property_exists($this, $property)) {
            return $this->$property;
        }

        return null;
    }

    public function __set($property, $value)
    {
        if (property_exists($this, $property)) {
            $this->$property = $value;
        }

        return $this;
    }

    private function check_token($module_name, $request)
    {
        if ($request->isOptions()) return true;
        $tokenStr = $request->getHeaderLine("x-access-token");

        if ($module_name === "/v1/admin") {
            if (empty($tokenStr) || strlen($tokenStr) < 10) return false;
            $this->logSession($tokenStr);
            $token = $this->getTokenFromStr($tokenStr);
            $role = $token->getClaim('role');
            return $role === 'admin';
        } elseif ($module_name === "/v1/public"){
            $this->logSession($tokenStr);
            return true;
        }
        else {
            if (empty($tokenStr) || strlen($tokenStr) < 10) return false;
            $this->logSession($tokenStr);
            $token = $this->getTokenFromStr($tokenStr);
            $role = $token->getClaim('role');
            if ($role === 'admin') return true;
            $modules = $token->getClaim('modules');
            foreach ($modules as $module) {
                if (strpos($module_name, $module) !== false) return true;
            }
            return false;
        }
    }
    public function getTokenFromStr($tokenStr){
        if(empty($tokenStr)) return null;
        $token = $this->parser->parse((string)$tokenStr);
        return $token;
    }

    public function authenticate($tokenStr)
    {
        if (empty($tokenStr) || strlen($tokenStr) < 10) return array("error" => "Wrong user token length");
        try {
            // Parses from a string
            $token = $this->getTokenFromStr($tokenStr);
            $this->logSession($tokenStr);
            $uid = $token->getClaim('uid');
            return Users::get_user_by_uid($this->notOrm, $uid);
        } catch (Exception $e) {
            return array("error" => $e->getMessage());
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
        ->with('modules', $user["modules"])// Configures a new claim, called "modules" join(",",$user["modules"])
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