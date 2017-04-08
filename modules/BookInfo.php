<?php

namespace modules\Books;

class BookInfo
{
    public function __construct($parameters = array())
    {
        foreach ($parameters as $key => $value) {
            $key = strtolower($key);
            $this->$key = $value;

        }
    }

    public function toArray()
    {
        return (array)$this;
    }
}