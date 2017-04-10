<?php

namespace modules\pCloud;
require_once $_SERVER['DOCUMENT_ROOT'] . "/middleware/utils.php";

class pCloudService
{
    private $username = "";
    private $password = "";
    private $access_token = "";
    private $client_secret = "";
    private $client_id = "";

    private $pCloudConfig = array();

    function __construct($db)
    {

        $pCloudConfig = $db->pcloud[1];
        if(is_null($pCloudConfig)) throw new \Exception("no row in pcloud config");
        $this->username = utf8_encode(simple_decrypt($pCloudConfig["username"]));
        $this->password = utf8_encode(simple_decrypt($pCloudConfig["password"]));
        $this->access_token = utf8_encode(simple_decrypt($pCloudConfig["access_token"]));
        $this->client_secret = utf8_encode(simple_decrypt($pCloudConfig["client_secret"]));
        $this->client_id = utf8_encode(simple_decrypt($pCloudConfig["client_id"]));
//        global $config;
//        $this->username = $config->cloud_username;
//        $this->password = $config->cloud_password;
//        $this->access_token = $config->access_token;
//        $this->client_secret = $config->client_secret;
//        $this->client_id = $config->client_id;
     }

    private function urlGenerator($methodName, $args = [])
    {
        global $config;

        $api_url = "$config->api_url/$methodName?access_token=$this->access_token";
        if (isset($args) && sizeof($args)) {
            $query = http_build_query($args);
            $api_url = $api_url . "&" . $query;
        }
        return $api_url;
    }

    public function getDirectLink($folderName = "", $fileName = "")
    {
        if (empty($fileName) || empty($folderName)) return array("error" => "wrong parameters");
        $api_url = $this->urlGenerator("getfilelink", array("path" => "/$folderName/$fileName"));
        $response = file_get_contents($api_url);
        return (isset($response)) ? json_decode($response, true) : array("error" => "something going wrong");
    }

    private function getPasswordDigest($digest)
    {
        return sha1($this->password . sha1(strtolower($this->username)) . $digest);
    }

    private function getDigest()
    {
        $api_url = $this->urlGenerator("getdigest");
        $response = file_get_contents($api_url);
        $digestInfo = (isset($response)) ? json_decode($response, true) : null;
        return (!is_null($digestInfo)) ? $digestInfo["digest"] : "";
    }

    public function getZipDirectLink($bookPaths = [], $fileName = "")
    {
        if (!sizeof($bookPaths)) return array("error" => "wrong parameters");
        $fileIds = [];
        if(empty($fileName)) $fileName = uniqid("download_") . ".zip";
        foreach ($bookPaths as $path) {
            $api_url = $this->urlGenerator("checksumfile", array("path" => $path));
            $response = file_get_contents($api_url);
            $fileInfo = (isset($response)) ? json_decode($response, true) : null;
            if (!is_null($fileInfo)) $fileIds[] = $fileInfo["metadata"]["fileid"];
        }
        $digest = $this->getDigest();
        $zip_api_url = $this->urlGenerator("getziplink", array("filename" => $fileName,
            "fileids" => join(",", $fileIds),
            "username" => $this->username,
            "passworddigest" => $this->getPasswordDigest($digest),
            "digest" => $digest));
        $zip_response = file_get_contents($zip_api_url);
        return (isset($zip_response)) ? json_decode($zip_response, true) : array("error" => "something going wrong");
    }
}