<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
require_once "config.php";

function connect()
{   
    global $config;
    $dsn = "mysql:dbname=$config->dbname;host=$config->host;charset=$config->charset";
    $conn = new PDO($dsn,$config->username,$config->password);
    $db = new NotORM($conn,null,new NotORM_Cache_Session);
    return $db;
}
