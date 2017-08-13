<?php
/**
 * Created by PhpStorm.
 * User: olmer
 * Date: 25/03/17
 * Time: 17:53
 */

namespace modules\Administration;


class News
{


    public static function updateNewsFile($db, $newsfile)
    {
        if (!isset($newsfile)) return array("error" => "Wrong parameters");
//        $affected = $db->portal_modules()->where("mid", $module["mid"])
//            ->update(array("name" => $module["name"], "title" => $module["title"]));
        return $affected != false ? $newsfile : null;
    }

    public static function insertNewsFile($db, $newsfile)
    {
        if (!isset($newsfile)) return array("error" => "Wrong parameters");
//        $row = $db->portal_modules()->insert(array("name" => $module["name"], "title" => $module["title"]));
//        $insert_id = $db->portal_modules()->insert_id();
//        $row["mid"] = $insert_id;
        return $newsfile;
    }

    public static function deleteNewsFile($db, $newsfile_id)
    {
        if (!isset($newsfile_id)) return array("error" => "Wrong parameters");
//        $db->portal_module2user()->where("module", $module_id)->delete();
//        $affected = $db->portal_modules()->where("mid", $module_id)->delete();
        return $affected;
    }

}