<?php

namespace OnlineRecord;
use OnlineRecord\Error as Error;
use Pixie\Connection;

class Constructor
{
    public function __construct(){

    }
    public function __destruct(){

    }

    public static function getFieldsList(array $tables) : array
    {
        $res = array();
        $sql = 'SHOW COLUMNS FROM ';
        foreach ($tables as $k => $v){
            Error::pdump($k, 'k');
            Error::pdump($v, 'v');
            $query = \QB::query('SHOW COLUMNS FROM '.config['prefix'].$v);
            $result = $query->get();
            foreach ($result as $kk => $vv){
                Error::pdump($kk, 'kk');
                Error::pdump($vv, 'vv');
                $res[$v][$vv->Field] = $vv->Type;
            }
        }
        Error::pdump($res, 'res');
        return $res;
    }

    public static function getHtmlTable(array ){

    }
}