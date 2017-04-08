<?php

namespace modules\pCloud;

class pCloudService
{
    private static function urlGenerator($methodName, $args)
    {
        global $config;
        $api_url = "$config->api_url/$methodName?access_token=$config->access_token";
        if (isset($args) && sizeof($args)) {
            $query = http_build_query($args);
            $api_url = $api_url . "&" . $query;
        }
        return $api_url;
    }

    public static function getDirectLink($folderName, $fileName)
    {
        if (empty($fileName) || empty($folderName)) return array("error" => "wrong parameters");
        $api_url = self::urlGenerator("getfilelink", array("path" => "/$folderName/$fileName"));
        $response = file_get_contents($api_url);
        return (isset($response))?json_decode($response, true): array("error"=>"something going wrong");
    }
}