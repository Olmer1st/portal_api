<?php
namespace middleware;
class Configuration {

    private $configFile = "";
    private $items = array();

    function __construct() {
        $this->configFile = "config.ini";
        $this->parse();
    }

    function __get($id) {
//        if ($id == "root_path") {
//            return $_SERVER["DOCUMENT_ROOT"] . $this->items[$id];
//        }
        return $this->items[$id];
    }

    private function parse() {
        $this->items = parse_ini_file($this->configFile);
    }

}

$config = new Configuration();

