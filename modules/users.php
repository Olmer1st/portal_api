<?php
/**
 * Created by PhpStorm.
 * User: olmer
 * Date: 25/03/17
 * Time: 17:53
 */

namespace Administration;


class Users
{
    public static function isUserExist($db, $email)
    {
        return $db->portal_users[array("email" => $email)];

    }

    public static function createNewUser($db, $email, $display, $password, $role, $modules = null)
    {
        global $config;
        if (self::isUserExist($db, $email)) return array("error" => "Email already exist in the system");
        $pw_hash = password_hash($password, PASSWORD_BCRYPT, [
            'cost' => 12,
        ]);
        $user = $db->portal_users()->insert(array(
            "email" => $email,
            "display" => $display,
            "password" => $pw_hash,
            "role" => "$role"
        ));

        if ($role === $config->user_role[0] && isset($modules)) {   // 0 user, 1 admin
            foreach ($modules as $mid) {
                $db->portal_module2user()->insert(array("uid" => $user["id"], "mid" => $mid));
            }
        }
        unset($user["password"]);
        return $user;
    }

    public static function login($db, $auth, $email, $password)
    {
        if (!isset($email) || empty($email) || !isset($password) || empty($password)) return array("error" => "Wrong credentials, please check email/password");
        $user = $db->portal_users[array("email" => $email)];
        if (!isset($user) || !isset($user["uid"])) return array("error" => "Wrong credentials, please check email/password");
        $pw_hash = $user["password"];
        if (!password_verify($password, $pw_hash)) return array("error" => "Wrong credentials, please check email/password");
        unset($user["password"]);
        $user["modules"] = self::prepare_modules($db, $user);
        $user["token"] = $auth->createToken($user);
        return $user;

    }

    private static function prepare_modules($db, $user){
        global $config;
        $modules = array();
        if ($user["role"] === $config->user_role[0]) {
            $modules = array_map(function ($row) {
                return $row["module"];
            }, iterator_to_array($db->portal_module2user()->where("uid", $user["uid"])->select("module")));
        }
        return $modules;
    }
    public static function get_user_by_uid($db, $uid){
        if (!isset($uid)) return array("error" => "Wrong user information");
        $user = $db->portal_users[array("uid" => $uid)];
        if (!isset($user) || !isset($user["uid"])) return array("error" => "Wrong user information");
        $user["modules"] = self::prepare_modules($db, $user);
        return $user;
    }
}