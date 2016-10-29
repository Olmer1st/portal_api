<?php
use \middleware\Configuration;

require "../middleware/config.php";


$config = new Configuration();

echo $config->servername;
echo $config->username;
echo $config->password;

