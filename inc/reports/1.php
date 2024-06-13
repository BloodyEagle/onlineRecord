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

Error::pdump('!!!!!!!!!!!!!!');
/*
$reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
$reader->setReadDataOnly(false);
$spreadsheet = $reader->load('inc/reports/1.xlsx');
$workSheet = $spreadsheet->getActiveSheet();*/
