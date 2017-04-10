<?php
//use \middleware\Configuration;

//require "../middleware/config.php";
require "../middleware/utils.php";
require $_SERVER['DOCUMENT_ROOT'] . '/middleware/connection.php';

$notOrm = connect();
$row = $notOrm->pcloud()->insert(array("username"=>simple_encrypt($config->cloud_username),
"password"=>simple_encrypt($config->cloud_password),
"access_token"=>simple_encrypt($config->access_token),
"client_secret"=>simple_encrypt($config->client_secret),
"client_id"=>simple_encrypt($config->client_id)));
echo $row;
