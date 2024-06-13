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

$GLOBALS['OR_REPORT'] = 'Показываем параметры';
$HTML = '<form action="/?act=reports&id='.$_REQUEST['id'].'" method="post" enctype="multipart/form-data">' .
            '<input type="text" name="params[param1]">' .
            '<input type="text" name="params[param2]">' .
            '<input type="submit" value=">>>>>">' .
        '</form>';
$GLOBALS['OR_REPORT']  .= $HTML;
Error::pdump('параметры');


/*
$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$reader->setReadDataOnly(false);
$spreadsheet = $reader->load('inc/reports/1.xlsx');
$workSheet = $spreadsheet->getActiveSheet();*/
