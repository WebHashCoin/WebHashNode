<?php

namespace WebHash\Helper;

class Serializer
{
    public static function serializeToClass(object $json, string $class = stdClass::class)
    {
        $count = strlen($class);
        $temp = serialize($json);
        $temp = preg_replace("@^O:8:\"stdClass\":@", "O:$count:\"$class\":", $temp);
        return unserialize($temp);  
    }
}