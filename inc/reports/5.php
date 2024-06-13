<?php
namespace OnlineRecord;

use OnlineRecord\Error as Error;
use DateTime;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

use NCL\Core\NCL;
use NCL\NCLNameCaseRu;
use QB;
/*  $GLOBALS['OR_REPORT'] - глобальная переменная, содержащая контент страницы отчета
    $_REQUEST['params'] - параметры отчета, переданные со страницы параметров
 * */

$GLOBALS['OR_REPORT'] = '<<<123456>>>';
Error::pdump('!!!!!!!!!!!!!!');

if (isset($_REQUEST['params'])) {//Показываем параметры
Error::pdump($_REQUEST, '$_REQUESR');
    $GLOBALS['OR_REPORT'] .= '<br>Параметры: <br>';
    foreach ($_REQUEST['params'] as $k => $v){
        $GLOBALS['OR_REPORT'] .= $k.' = '.$v.'<br>';
    }
}

/*
$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$reader->setReadDataOnly(false);
$spreadsheet = $reader->load('inc/reports/1.xlsx');
$workSheet = $spreadsheet->getActiveSheet();*/
