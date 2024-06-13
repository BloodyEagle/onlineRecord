<?php
declare(strict_types = 1);

namespace OnlineRecord;

use Tracy\Debugger;
//use \FirePHP;

//$firephp = NULL;

class Error
{

    const LOG = 'LOG';
    const INFO = 'INFO';
    const WARN = 'WARN';
    const ERROR = 'ERROR';
    const DUMP = 'DUMP';
    const TRACE = 'TRACE';
    //const TABLE = 'TABLE';
    
    /*    public function __construct()
    {
        if (DEBUG){
            global $firephp;
            
            Debugger::enable(Debugger::DEVELOPMENT);
            Debugger::$strictMode = true; // display all errors
            Debugger::$dumpTheme = 'dark';
            Debugger::$showLocation = true; // Shows all additional location information
            Debugger::$logSeverity = E_NOTICE | E_WARNING;
            Debugger::$showBar = true;
            
            $firephp = FirePHP::getInstance(true);
        }
    }*/

    public static function init()
    {
        if (DEBUG && !AJAX){
            //global $firephp;
            
            Debugger::enable(Debugger::DEVELOPMENT);//Debugger::PRODUCTION
            Debugger::$strictMode = true; // display all errors -
            Debugger::$dumpTheme = 'dark';
            Debugger::$showLocation = true; // Shows all additional location information
            Debugger::$logSeverity = E_NOTICE | E_WARNING;
            Debugger::$showBar = true;
            Debugger::$editor = '"C:\\Program Files\\JetBrains\\PhpStorm 2022.1.1\\bin\\phpstorm64.exe" --line %line% "%file%"';
            /*Debugger::$editorMapping = [
                'c:\\OpenServer\\domains\\dev.dev.ru\\' => 'd:\_Projects\Web\CourseRecord'
            ];
            asassdas*/
           
            //$firephp = FirePHP::getInstance(true);
        }
    }
    /**
     * @param mixed $data - данные для вывода в плавающую панель 
     */
    public static function pdump($data, $title = NULL) {
        if (DEBUG && !AJAX){
            bdump($data, $title);
        }
    }

}