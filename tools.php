<?php
    require 'vendor/autoload.php';
    use OnlineRecord\Error;

    Error::init();
    Error::pdump((isset($_COOKIE['DEBUG']) ? 'Debug ON' : 'DebugOFF'));

    if ( isset($_SERVER['HTTP_X_REQUESTED_WITH'])// Проверяем пришел запрос Ajax или нет
        && !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        switch ($_REQUEST['act']){
            case 'encpass':
                    $html = password_hash($_REQUEST['pass'], PASSWORD_BCRYPT);
                break;
            default:
        }

    } else {
        $html = file_get_contents('inc/tools.html');
    }

    echo $html;
    phpinfo();