<?php
class Variables{
    private static $AcceptableFormats = array(
        'Category Id: ?'                     , '$CategoryName'            , "Name of the category",
        );

    public static function VerifyFormat($name){
        for($j = 0; $j < sizeof(self::$AcceptableFormats); $j += 3){
            $search      = self::$AcceptableFormats[$j+0];
            $arg         = self::$AcceptableFormats[$j+1];
            $description = self::$AcceptableFormats[$j+2];

            $pattern = '/^'
                . ((strlen($arg) > 0) 
                ? str_replace('?','(.)+',$search)
                : $search)
                .'$/';

            if(preg_match($pattern, $name)){
                return;
            }
        }

        DebugEcho("WARNING: Remember() variable name '$name' does not match recorded standards.\n");
    }
}