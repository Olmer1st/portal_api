<?php
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

namespace Tree;
class Node
{
    public $id = "";
    public $title = "";
    public $type = 0;
    public $level = -1;
    public $parent = "";
    public $collapsed = false;
    public $hidden = false;
    public $bookInfo = null;

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