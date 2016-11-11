<?php

namespace Tree;
/*
export interface GenreInfo {
    gid: number;
    code: string;
    gdesc: string;
    edesc: string;
}
*/
class GenreInfo
{

    public $gid = -1;
    public $code = "";
    public $gdesc = "";
    public $edesc = "";

    public function __construct($parameters = array())
    {
        foreach ($parameters as $key => $value) {
            $this->$key = $value;
        }
    }

    public function toArray()
    {
        return (array)$this;
    }
}