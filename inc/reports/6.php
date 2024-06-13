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
if (isset($GLOBALS['OR_PARAMS'])){
    Error::pdump($GLOBALS['OR_PARAMS']);
}
$GLOBALS['OR_REPORT'] = '66666';
Error::pdump('!!!!!!!!!!!!!!');

if (isset($_REQUEST['params'])) {//Показываем параметры
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
