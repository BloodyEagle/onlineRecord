<?php
declare(strict_types = 1);

namespace OnlineRecord;
use OnlineRecord;
use Pixie\Connection;
use Pixie\QueryBuilder\QueryBuilderHandler;

const config = array( //конфиг БД
            'driver'    => 'mysql',
            'host'      => 'localhost',
            'database'  => 'base',
            'username'  => 'user',
            'password'  => 'password',
            'charset'   => 'utf8',
            'collation' => 'utf8_general_ci',
            'prefix'    => 'pre_'
        );

new \Pixie\Connection('mysql', config, 'QB');

Settings::init();

define('uploaddir','/home/dev/public_html/upload/');//директория с загрузками сканов

define('SITE', 'https://'.$_SERVER['SERVER_NAME']);//адрес сайта

define('ENCRYPTION_KEY', '468fd219fb68cd');//ключ шифрования данных куки

$dpass = 'vt%Rf4l*^ss5Q';
if (isset($_REQUEST['DEBUG_ON']) && $_REQUEST['DEBUG_ON'] == $dpass){
    setcookie('DEBUG', LK::encrypt($dpass), time()+99986400, '/', SITE);
}
if (isset($_COOKIE['DEBUG']) && LK::decrypt($_COOKIE['DEBUG']) == $dpass){
    define('DEBUG', true);//флаг  отладки     
} else {
    define('DEBUG', false);//флаг  отладки      
}

if ( isset($_SERVER['HTTP_X_REQUESTED_WITH'])// Проверяем пришел запрос Ajax или нет
     && !empty($_SERVER['HTTP_X_REQUESTED_WITH']) 
     && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    define('AJAX', true);
} else {
    define('AJAX', false);
}

