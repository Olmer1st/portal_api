<?php
/**
 * Created by PhpStorm.
 * User: olmer
 * Date: 25/03/17
 * Time: 17:53
 */

namespace modules\Administration;


class Users
{
    public static function isUserExist($db, $email)
    {
        return $db->portal_users[array("email" => $email)];

    }
    private static function createPasswordHash($password){
        $pw_hash = password_hash($password, PASSWORD_BCRYPT, [
            'cost' => 12,
        ]);
        return $pw_hash;
    }
    public static function createNewUser($db, $email, $display, $password, $role, $modules = null)
    {
        global $config;
        if (self::isUserExist($db, $email)) return array("error" => "Email already exist in the system");

        $user = $db->portal_users()->insert(array(
            "email" => $email,
            "display" => $display,
            "password" => self::createPasswordHash($password),
            "role" => "$role"
        ));
        $insert_id = $db->portal_users()->insert_id();
        $user["uid"] = $insert_id;
        if ($role === $config->user_role[0] && isset($modules)) {   // 0 user, 1 admin
            foreach ($modules as $mid) {
                $db->portal_module2user()->insert(array("uid" => $user["uid"], "module" => $mid));
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

    private static function prepare_modules($db, $user)
    {
        global $config;
        $modules = array();
        if ($user["role"] === $config->user_role[0]) {
            $modules = array_map(function ($row) {
                return $row["module"];
            }, iterator_to_array($db->portal_module2user()->where("uid", $user["uid"])->select("module")));
        }
            return $modules;
    }

    public static function get_user_by_uid($db, $uid)
    {
        if (!isset($uid)) return array("error" => "Wrong user information");
        $user = $db->portal_users[array("uid" => $uid)];
        if (!isset($user) || !isset($user["uid"])) return array("error" => "Wrong user information");
        $user["modules"] = self::prepare_modules($db, $user);
        return $user;
    }

    public static function getUsers($db)
    {
        $users=$db->portal_users()->order("uid");
        foreach ($users as $user){
            $user["modules"] = self::prepare_modules($db, $user);
        }
        return $users;
    }

    public static function getModules($db)
    {
        return $db->portal_modules()->order("mid");
    }

    public static function updateModule($db, $module)
    {
        if (!isset($module)) return array("error" => "Wrong parameters");
        $affected = $db->portal_modules()->where("mid", $module["mid"])
            ->update(array("name" => $module["name"], "title" => $module["title"]));
        return $affected != false? $module : null;
    }

    public static function insertModule($db, $module)
    {
        if (!isset($module)) return array("error" => "Wrong parameters");
        $row = $db->portal_modules()->insert(array("name" => $module["name"], "title" => $module["title"]));
        $insert_id = $db->portal_modules()->insert_id();
        $row["mid"] = $insert_id;
        return $row;
    }

    public static function deleteModule($db, $module_id)
    {
        if (!isset($module_id)) return array("error" => "Wrong parameters");
        $db->portal_module2user()->where("module", $module_id)->delete();
        $affected = $db->portal_modules()->where("mid", $module_id)->delete();
        return $affected;
    }

    public static function updateUser($db, $user)
    {
        global $config;
        if (!isset($user)) return array("error" => "Wrong parameters");
        $role = $user["role"];
        $modules = $user["modules"];
        $db->portal_module2user()->where("uid", $user["uid"])->delete();
        $update = array("role" => $role, "display" => $user["display"]);
        if(isset($user["password"]) && !empty($user["password"])){
            $update["password"] = self::createPasswordHash($user["password"]);
        }
        $affectedUser = $db->portal_users()->where("uid", $user["uid"])->update($update);
        unset($user["password"]);
        if ($role === $config->user_role[0] && isset($modules)) {   // 0 user, 1 admin
            foreach ($modules as $mid) {
               $db->portal_module2user()->insert(array("uid" => $user["uid"], "module" => $mid));
            }
        }
        return $affectedUser != false ? $user : null;
    }

    public static function insertUser($db, $user)
    {
        if (!isset($user)) return array("error" => "Wrong parameters");
        return self::createNewUser($db, $user["email"], $user["display"], $user["password"], $user["role"], $user["modules"]);
    }

    public static function deleteUser($db, $user_id)
    {
        if (!isset($user_id)) return array("error" => "Wrong parameters");
        $db->portal_module2user()->where("uid", $user_id)->delete();
        $affected = $db->portal_users()->where("uid", $user_id)->delete();
        return $affected;
    }

    public static function lockUser($db, $user_id, $lock)
    {
        if (!isset($user_id)) return array("error" => "Wrong parameters");
        $affected = $db->portal_users()->where("uid", $user_id)->update(array("locked" => (int)$lock));
        return $affected;
    }
}