<?php

namespace Tree;

/*
 export interface Node {
    id:number;
    title: string;
    type: NodeType;
    level: number;
    bookInfo:BookInfo;
};

export enum NodeType {
    None = 0,
    Book = 1,
    Author = 2,
    Serie = 3
};

export interface BookInfo {
    title: string;
    size:number;
    serno:number;
    lang:string;
    del:boolean;
    path:string;
    file:number;
    date:string;
    ext:string;
    genre:string;
};
 */
class BookInfo
{

    public $size = 0;
    public $serno = null;
    public $series = "";
    public $lang = "";
    public $del = null;
    public $path = "";
    public $file = -1;
    public $date = "";
    public $ext = "";
    public $genre = "";

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