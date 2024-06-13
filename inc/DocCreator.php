<?php

namespace OnlineRecord;

use DateTime;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

use NCL\Core\NCL;
use NCL\NCLNameCaseRu;
use QB;

/**
 * Description of DocCreator
 *
 * @author Paranoic
 */
const DOC_TYPE_APP = 1;//заявление о приеме на курсы
const DOC_TYPE_POZ = 2;//приказ о зачислении на курсы
const DOC_TYPE_POO = 3;//приказ об отчислении с курсов
const DOC_TYPE_POE = 4;//приказ об окончании курсов
const DOC_TYPE_VED = 5;//ведомость
const DOC_TYPE_USERS = 6;//портянка со всеми данными слушателей группы

//ini_set('max_execution_time', 60);
//ini_set("memory_limit", 256);

class DocCreator {
        
    protected int $type;//тип документа
    protected string $tfile;//имя файла шаблона
    protected string $FileName;//Имя скачиваемого файла
    protected int $col;//ширина шаблона в столбцах
    protected int $row;//высота шаблона в строках
    protected int $rRow;//высота повторяемой части шаблона в столбцах
    protected int $studentCount;//количество слушателей
    protected String $copyRange;//диапазон шаблона A1:K64
    protected int $before;//Номер строки начала повторений шаблона
    protected int $after;//Номер строки конца повторений шаблона
    protected int $shift;//сдвиг ячеек после вставки новых строк
    
    protected array $student = array(); // массив с ID заявок
    protected String $date; // дата приказа
    protected String $vedDate;// дата ведомости
    protected int $group; // ID группы
    protected int $pnumber; // номер приказа
    protected bool $save = false; // флаг сохранения данных приказа
    protected string $reason;//причина отчисления

    private bool $newUser = false;//Флаг нового пользователя
    private int $regNumber;//Регномер последнего слушателя

    protected array $templates = array();//шаблоны для поиска
    
    public function __construct(array $data) {
        Error::pdump($data,'in param(REQUEST)');
        $this->type = (int)$data['doctype'];
        if (isset($data['student'])) {
            $json = $data['student'];
            $this->student = json_decode($json, true);
            $this->studentCount = count($this->student);
        }

//        $this->date = $data['date'] ?? $data['edate'] ?? $data['enddate'] ?? date('d.m.Y');
        $this->group = $data['group'] ?? 0;
//        $this->pnumber = (int) $data['pnumber'] ?? $data['eppnumber'] ?? $data['endpnumber'] ?? null;
//        $this->vedDate = $data['vdate'] ?? '';
//        $this->reason = $data['reason'] ?? '';
//        $this->save = (bool)$data['save'] ?? false;
        $this->tfile = Settings::getSetting('templateFile'.$data['doctype']);
        $this->shift = 0;
        switch ($this->type){
            case DOC_TYPE_APP:
                    $this->FileName = 'Заявление о зачислении на курс';
                    $this->date = date('d.m.Y');
                break;
            case DOC_TYPE_POZ:
                $this->FileName = 'Приказ о зачислении на курс';
                $this->date = $data['date'];
                $this->pnumber = (int) $data['pnumber'];
                $this->save = (bool)$data['save'];
                break;
            case DOC_TYPE_POO:
                $this->FileName = 'Приказ об отчислении';
                $this->date = $data['date'];
                $this->pnumber = (int) $data['pnumber'];
                $this->reason = $data['reason'] ?? '';
                $this->save = (bool)$data['save'];
                break;
            case DOC_TYPE_POE:
                $this->FileName = 'Приказ об окончании курса';
                $this->date = $data['date'];
                $this->pnumber = (int) $data['pnumber'];
                $this->vedDate = $data['vdate'] ?? '';
                $this->save = (bool)$data['save'];
                break;
            case DOC_TYPE_VED:
                $this->FileName = 'Итоговая ведомость';
                $this->date = $data['date'];
                $this->pnumber = (int) $data['pnumber'];
                $this->save = (bool)$data['save'];
                break;
            case DOC_TYPE_USERS:
                $this->FileName = 'Список группы';
                break;
        }
        Error::pdump($this, 'this');;
        $this->loadTemplate();
        $this->parseDoc();
    }
    
    public function __destruct() {
        $this->templates = [];
        //Error::pdump($this,'end');
    }
    
    /**
     * 
     * @param String $type - тип документа DOC_TYPE...
     */
    public function loadTemplate() {
        $nc = new NCLNameCaseRu();
        
        //Читаем данные
        $query = "SELECT 
                    app.id AS appID,
                    app.course AS courseID,
                    app.student AS studentID,
                    app.passport AS passID,
                    app.diplom AS diplomID,
                    app.`work` AS workID,
                    app.`group` AS groupID,
                    app.regNumber,
                    ( SELECT CONCAT(uu.lastname, ' ', uu.firstname, ' ', uu.fathername) 
                        FROM ". config['prefix']."users uu 
                        WHERE uu.id = app.student ) AS fio,
                    u.sex,
                    (SELECT CONCAT(ru.region, ', ', CASE WHEN u.distinctrm <> 0 THEN diu.`distinct` ELSE u.`distinct` END, ', ', u.city, ', ', u.address) 
                        FROM ". config['prefix']."users u, ". config['prefix']."regions ru, ". config['prefix']."distinct diu 
                        WHERE ru.id = u.region AND diu.id = u.distinctrm AND u.id = app.student) AS uaddress,
                    c.dpp,    
                    dpp.dppR,
                    c.`name` AS courseName,
                    c.hours,
                    (SELECT ff.formR 
                        FROM ". config['prefix']."form ff,". config['prefix']."course cc 
                        WHERE cc.id = app.course AND ff.id = cc.form) AS formR,
                    c.`dist`,
                    DATE_FORMAT(c.start,'%d.%m.%Y') AS start,
                    DATE_FORMAT(c.stop,'%d.%m.%Y') AS stop,
                    c.`mode`,
                    c.finance,
                    c.curator,
                    m.`mode` AS modeR,
                    sex.sex AS sexR,
                    DATE_FORMAT(u.birthday,'%d.%m.%Y') AS birthday,
                    (DATE_FORMAT(FROM_DAYS(TO_DAYS(now()) - TO_DAYS(u.birthday)), '%Y') + 0) as age,
                    d.almamatter AS almaMatter,
                    d.qualification,
                    DATE_FORMAT(d.datedoc,'%Y') AS ddate,
                    d.series AS dseries,
                    d.number AS dnumber,
                    d.regnumber AS dregnumber,
                    d.stepen AS stepen,
                    d.zvanie AS zvanie,
                    ( SELECT CONCAT(dd.f, ' ', dd.i, ' ', dd.o) 
                        FROM ". config['prefix']."diplom dd 
                        WHERE dd.parent = app.student AND dd.id = diplomID) AS dfio,
                    w.organisation,
                    w.waddress,
                    w.profession,
                    w.stage,
                    w.phone AS wphone,
                    u.phone,
                    u.email,
                    u.snils,
                    p.number AS pnumber,
                    p.series AS pseries,
                    DATE_FORMAT(p.datedoc,'%d.%m.%Y') AS pdate,
                    p.info AS pinfo,
                    pp.predmet,
                    g.id AS groupid,
                    g.orderAddNumber,
                    g.orderExpNumber,
                    g.reason,
                    g.orderEndNumber,
                    DATE_FORMAT(g.orderAddDate,'%d.%m.%Y') AS orderAddDate,
                    DATE_FORMAT(g.orderExpDate,'%d.%m.%Y') AS orderExpDate,
                    DATE_FORMAT(g.orderEndDate,'%d.%m.%Y') AS orderEndDate,
                    DATE_FORMAT(g.vedDate,'%d.%m.%Y') AS vedDate,
                    DATE_FORMAT(NOW(),'%d.%m.%Y') AS date,
                    (SELECT CASE WHEN w.distinctrm = '' THEN r.region ELSE drm.distinct END)  AS distinctrm
                FROM ". config['prefix']."applications app
                    LEFT JOIN `". config['prefix']."users` u ON `u`.`id` = `app`.`student`
                    LEFT JOIN `". config['prefix']."course` c ON `c`.`id` = `app`.`course`
                    LEFT JOIN `". config['prefix']."dpp` dpp ON `dpp`.`id` = `c`.`dpp`
                    LEFT JOIN `". config['prefix']."form` f ON `f`.`id` = `c`.`form`
                    LEFT JOIN `". config['prefix']."sex` sex ON `sex`.`id` = `u`.sex
                    LEFT JOIN `". config['prefix']."diplom` d ON `d`.`id` = `app`.diplom
                    LEFT JOIN `". config['prefix']."work` w ON w.id = app.`work`
                    LEFT JOIN `". config['prefix']."pass` p ON p.id = app.passport
                    LEFT JOIN `". config['prefix']."cmode` m ON m.id = c.mode
                    LEFT JOIN `". config['prefix']."predmet` pp ON pp.id = c.predmet
                    LEFT JOIN `". config['prefix']."distinct` drm ON drm.id = w.distinctrm
                    LEFT JOIN `". config['prefix']."distinct` diu ON diu.id = u.distinctrm
                    LEFT JOIN `". config['prefix']."regions` r ON r.id = w.region
                    LEFT JOIN `". config['prefix']."regions` ru ON ru.id = u.region
                    LEFT JOIN `". config['prefix']."coursegroup` g ON g.id = app.group
                WHERE app.id IN ". arrIn($this->student)
                ." AND app.status NOT IN (".APP_STATUS_REJECTED.", ".APP_STATUS_REVOKED.")"
                ." ORDER BY `fio`";
        Error::pdump($query, 'main SQL');
        $query = QB::query($query);
        $data = $query->get();
        Error::pdump($data,'data');
        
        //Читаем все шаблоны подстановки.
        $query = QB::table('doctemplates')->where('type', $this->type)->orderBy('id');
        $tmpl = $query->get();
        //Error::pdump($tmpl);
        Error::pdump($tmpl, 'template');
        
        $SQL = '';
        switch ($this->type) {
            case DOC_TYPE_POZ:
                    $SQL .= 'INSERT INTO ' . config['prefix'] . 'applications (`id`, `regnumber`) VALUES ';
                break;
        }

//        Error::pdump('1: '.$SQL, 'SQL');

        $newOrder = true;//флаг ненапечатанного приказа
            
        foreach ($data as $key => $val)  { //Крутим данные пользователей/курсов
            if (isset($val->fio))
                $val->fioRod = $nc->q($val->fio, NCL::$RODITLN);//Родительный падеж ФИО
            if ($this->type == DOC_TYPE_POO) {
                if (!is_null($this->reason))
                    $val->reason = $this->reason;
            }
            if (isset($val->hours))
                $val->hoursEnd = number($val->hours, array('час','часа','часов'));//Правильное окончание времени

            //$val->predmet = mb_strtolower($val->predmet);

//==============================================================================
            if ($this->type == DOC_TYPE_POZ || $this->type == DOC_TYPE_POO || $this->type == DOC_TYPE_POE){
                switch ($this->type) {
                    case DOC_TYPE_POZ:
                        if (is_null($val->orderAddDate))
                            $val->orderAddDate = $this->date;//Дата
                        break;
                    case DOC_TYPE_POO:
                        if (is_null($val->orderExpDate))
                            $val->orderExpDate = $this->date;//Дата
                        break;
                    case DOC_TYPE_POE:
                        $val->numberPP = $key+1;
                        if (is_null($val->orderEndNumber)) {
                            $val->orderEndDate = $this->date;//Дата
                            $val->orderEndNumber = $this->pnumber;
                            $val->vedDate = $this->vedDate;
                        } else {
                            $newOrder = false;
                        }
                        break;
                }
            }


            if ($this->type == DOC_TYPE_POZ) { //если это приказ о зачислении
                //$val->orderAddNumber = Settings::getSetting('orderAddNumber');//Номер приказа о зачислении
                $val->orderAddNumber = $this->pnumber;//Номер приказа о зачислении
                $val->numberPP = $key+1;

                if (is_null($val->regNumber)) { // если регномер не присвоен
                    Error::pdump('Нет регномера');
                    $this->newUser = true;
                    Error::pdump($this->newUser, 'newUser');
                    $val->regNumber = Settings::getSetting('regAddNumber')+$key;
                    $SQL .= ($key == 0 ? ' ' : ' ,').'('.$val->appID.', '.$val->regNumber.')';
                    $this->regNumber = $val->regNumber;
                } else {
                    $newOrder = false; // если регномер получен из базы, то приказ не новый
                }
            }
/*            if ($this->type == DOC_TYPE_POE) { //если это приказ об окончании
                $val->numberPP = $key+1;
                if (is_null($val->orderEndNumber)) {
                    //$val->orderEndNumber = Settings::getSetting('orderEndNumber');//Номер приказа об окончании курсов
                    $val->orderEndNumber = $this->pnumber;//Номер приказа об окончании курсов
                    //$val->orderEndDate = date('d.m.Y');//Дата
                    $val->orderEndDate = $this->date;
                    $val->vedDate = $this->vedDate;
                } else {
                    $newOrder = false;
                }
                Error::pdump($val, 'val');
                //$SQL .= ($key == 0 ? ' ' : ' ,').'('.$val->groupid.', '.$val->regNumber.')';
            }*/
//            Error::pdump('2: '.$SQL, 'SQL');
            if ($this->type == DOC_TYPE_POO) { //если это приказ об отчислении
                $val->numberPP = $key+1;
//                Error::pdump($val, 'VAL');
                if (is_null($val->orderExpNumber)) { //
                    //$val->regNumber = Settings::getSetting('regAddNumber')+$key;
                    $val->orderExpNumber = $this->pnumber;//Номер приказа об отчислении

                    //$SQL .= ($key == 0 ? ' ' : ' ,').'('.$val->appID.', '.$val->regNumber.')';
                } else {
                    $newOrder = false; //если номер приказа получен из базы, то приказ не новый
                    //$this->reason = $val->reason;
                }
            }

            if ($this->type == DOC_TYPE_USERS) { //если это портянка
                $val->numberPP = $key+1;
            }

            foreach ($tmpl as $k => $v) { //крутим шаблоны
//                Error::pdump($k,'K');
//                Error::pdump($v,'V');
                if ($v->replacetype != 'size') {
                    $this->templates[$key][$k]['id'] = $v->id;
                    $this->templates[$key][$k]['cell'] = $v->cell;
                    $this->templates[$key][$k]['search'] = $v->search;
                }
                switch ($v->replacetype) {
                    case 'size': 
                            $this->col = $v->search;
                            $this->row = $v->replace;
                            $this->copyRange = $v->cell;
                            preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', $v->cell, $match);
                            $this->before = (int)$match[2];
                            $this->after = (int)$match[4];
                            $this->rRow = $this->after - $this->before + 1;
                            //Error::pdump($match,'size');
                        break;

                    case 'text':
                            $this->templates[$key][$k]['replace'] = $v->replace;
                        break;

                    case 'field':
                            $this->templates[$key][$k]['replace'] = $val->{$v->replace};
                        break;

                    case 'json':
                            $start = strpos($v->replace, '{');
                            $value = $val->{trim(substr($v->replace, 0, $start))};
                            $ar = json_decode(substr($v->replace, $start), true);
                            $this->templates[$key][$k]['replace'] = $ar[$value];
                        break;

                    default:
                        break;
                }
                
                //рассчитываем где ячейка, до повторов, после или внутри
                preg_match('/^([A-Z]+)(\d+)/', $v->cell, $match);
//                Error::pdump($match, 'match');
//                Error::pdump($this->before.'x'.$this->after, 'ba');
                switch (true) {
                    case ($match[2] < $this->before && $v->replacetype != 'size'):
                            $this->templates[$key][$k]['type'] = 'before';
                        break;
                    case ($match[2] > $this->after && $v->replacetype != 'size'):
                            $this->templates[$key][$k]['type'] = 'after';
                        break;
                    case ($match[2] <= $this->after && $match[2] >= $this->before && $v->replacetype != 'size'):
                            $this->templates[$key][$k]['type'] = 'repeat';
                        break;
                    default:
                        break;
                }
                
            } //крутим шаблоны
        } //Крутим данные пользователей/курсов
        if ($this->type == DOC_TYPE_POZ) {
            $SQL .= ' ON DUPLICATE KEY UPDATE `regnumber` = VALUES(`regnumber`)';
        }
//        Error::pdump('3: '.$SQL, 'SQL');
        Error::pdump($newOrder, 'newOrder');
        Error::pdump($this->newUser, 'newUser');
        Error::pdump($this->save, 'save');
        //if (($newOrder && $this->save) || $this->newUser) {
        if (($newOrder && $this->save)) {
            //Error::pdump($SQL ?? '', 'SQL-');
            if ($SQL <> '') {
                Error::pdump($SQL, 'SQL-L');
                $query = QB::query($SQL);

                if ($this->newUser){
                    Settings::setSetting('regAddNumber', $this->regNumber);
                }
            }
            Error::pdump($query, 'query');
            $dt = DateTime::createFromFormat('d.m.Y', $this->date);
            switch ($this->type){
                case DOC_TYPE_APP:

                    break;
                case DOC_TYPE_POE:
                    $dt1 = DateTime::createFromFormat('d.m.Y', ($data[0]->orderEndDate ?? $_REQUEST['date']));
                    $dt2 = DateTime::createFromFormat('d.m.Y', ($data[0]->vedDate ?? $_REQUEST['vdate']));
                    $SQL .= 'INSERT INTO ' . config['prefix'] . 'coursegroup (`id`, `orderEndNumber`, `orderEndDate`, `vedDate`) ' .
                        'VALUES ('.$data[0]->groupid.',' .
                        ' \''.($data[0]->orderEndNumber ?? $_REQUEST['pnumber']).'\',' .
                        ' \''.$dt1->format('Y-m-d').'\',' .
                        ' \''.$dt2->format('Y-m-d').'\') '.
                        'ON DUPLICATE KEY UPDATE ' .
                        '`orderEndNumber` = \''.($data[0]->orderEndNumber ?? $_REQUEST['pnumber']).'\', ' .
                        '`orderEndDate` = \''.$dt1->format('Y-m-d').'\', ' .
                        '`vedDate` = \''.$dt2->format('Y-m-d').'\'';
                    Error::pdump($SQL, 'сохр о зав');
                    $query = QB::query($SQL);
                    break;
                case DOC_TYPE_POO:
                    $query = QB::table('coursegroup')->where('id', $this->group)->update(array('orderExpNumber' => $this->pnumber, 'orderExpDate' => $dt->format('Y-m-d'), 'reason' => $this->reason));
                    break;
                case DOC_TYPE_POZ:
                    $query = QB::table('coursegroup')->where('id', $this->group)->update(array('orderAddNumber' => $this->pnumber, 'orderAddDate' => $dt->format('Y-m-d')));
                    break;
            }
            //$query = \QB::table('coursegroup')->where('id', $this->group)->update(array('orderAddNumber' => $this->pnumber, 'orderAddDate' => $dt->format('Y-m-d')));

            Settings::setSetting('orderAddNumber', $this->pnumber+1);
            Settings::setSetting('orderExpNumber', $this->pnumber+1);
            Settings::setSetting('regAddNumber', $val->regNumber+1);
        }
        //if (isset());
        Error::pdump($this, 'DocCreator');
        
    }
    
    
    
    /**=========================================================================
     * Читает шаблон и заполняет его данными из базы
     */
    public function parseDoc() {

        $reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $reader->setReadDataOnly(false);
        $spreadsheet = $reader->load($this->tfile);
        $workSheet = $spreadsheet->getActiveSheet();
        Error::pdump($this->studentCount,'studentCount');
        
        if ($this->studentCount > 1) { //размножаем шаблон
            for($i = 0; $i < $this->studentCount-1; $i++){
                //Error::pdump($this->copyRange.'->A'.($this->row*($i+1)+1), 'бэкап');
                $workSheet->insertNewRowBefore($this->after+1, $this->rRow);
                $this->copyRange($workSheet, $this->copyRange, 'A'.($this->after+1));
                $this->shift += $this->rRow;
                //$this->copyRange($workSheet, $this->copyRange, 'A'.($this->row*($i+1)+1));
            }
        }
        Error::pdump($this->templates, 'templates');;
        //крутим студентов
        foreach ($this->templates as $k => $v) {
            //крутим шаблоны
            foreach ($v as $key => $val) {
//                Error::pdump($val, 'TMPL-'.$key);
                //Error::pdump('k='.$k.', type='.$val['type'].', :'.$val['replace']);
                preg_match('/^([A-Z]+)(\d+)$/', $val['cell'], $match);
                
                if ($k == 0 && $val['type'] == 'after'){
                    $val['cell'] = $match[1].(String)((int)$match[2] + $this->shift);
                }
                
                if ($k != 0){
                    $val['cell'] = $match[1].(String)((int)$match[2] + $this->rRow * $k);
                }
               
                if ($k != 0 && ($val['type'] == 'after' || $val['type'] == 'before')){
                    continue;
                }
                
                $cell = $workSheet->getCell($val['cell']);
                
                if ( is_null($val['search']) ){
                    $rec = $val['replace'];
                } else {
                    $rec = $cell->getValue();
                    $rec = str_replace($val['search'], $val['replace'], $rec);
                }
                $cell->setValue($rec);
            }
        }
/*
        $cc = Coordinate::indexesFromString($this->pageBreak);
        $col = $cc[0];//+1;
        Error::pdump($col,'col');
        $row = $cc[1]+$this->shift;// +1;
        Error::pdump($row,'row');
        $val['cell'] = Coordinate::stringFromColumnIndex($col).(String)($row);
        Error::pdump($val['cell'],'cell');
        //$workSheet->setBreak($val['cell'], \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::BREAK_COLUMN);
        //$workSheet->setBreak($val['cell'], \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::BREAK_ROW);
  */
//        die();
        $writer = new Xlsx($spreadsheet);
        $writer->setPreCalculateFormulas(false);
        $output =  'output.xlsx';
        if (Settings::getSetting('downloadXlsx') == 1){
            $output = 'php://output';
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="'.$this->FileName.'.xlsx"');
            header('Cache-Control: max-age=0');
        } 
        $writer->save($output);
        exit(); 
    }

    /** ========================================================================
     * Копирует диапазон ячеек со стилями в пределах листа
     * @param Worksheet $sheet
     * @param String $srcRange - копируемый диапазон 'A1:H23'
     * @param string $dstCell - адрес верхней левой ячейки куда копируем
     * @return bool 
     */
    function copyRange( Worksheet $sheet, $srcRange, $dstCell) {
        // Validate source range. Examples: A2:A3, A2:AB2, A27:B100
        if( !preg_match('/^([A-Z]+)(\d+):([A-Z]+)(\d+)$/', $srcRange, $srcRangeMatch) ) {
            // Wrong source range
            return false;
        }
        // Validate destination cell. Examples: A2, AB3, A27
        if( !preg_match('/^([A-Z]+)(\d+)$/', $dstCell, $destCellMatch) ) {
            // Wrong destination cell
            return false;
        }

        $srcColumnStart = $srcRangeMatch[1];
        $srcRowStart = $srcRangeMatch[2];
        $srcColumnEnd = $srcRangeMatch[3];
        $srcRowEnd = $srcRangeMatch[4];

        $destColumnStart = $destCellMatch[1];
        $destRowStart = $destCellMatch[2];

        // For looping purposes we need to convert the indexes instead
        // Note: We need to subtract 1 since column are 0-based and not 1-based like this method acts.

        $srcColumnStart = Coordinate::columnIndexFromString($srcColumnStart);
        $srcColumnEnd = Coordinate::columnIndexFromString($srcColumnEnd);
        $destColumnStart = Coordinate::columnIndexFromString($destColumnStart);

        $rowCount = 0;
        for ($row = $srcRowStart; $row <= $srcRowEnd; $row++) {
            $colCount = 0;
            for ($col = $srcColumnStart; $col <= $srcColumnEnd; $col++) {
                $cell = $sheet->getCellByColumnAndRow($col, $row);
                $style = $sheet->getStyleByColumnAndRow($col, $row);
                $dstCell = Coordinate::stringFromColumnIndex($destColumnStart + $colCount) . (string)($destRowStart + $rowCount);
                $sheet->setCellValue($dstCell, $cell->getValue());
                $sheet->duplicateStyle($style, $dstCell);

                // Set width of column, but only once per row
                if ($rowCount === 0) {
                    $w = $sheet->getColumnDimensionByColumn($col)->getWidth();
                    $sheet->getColumnDimensionByColumn ($destColumnStart + $colCount)->setAutoSize(false);
                    $sheet->getColumnDimensionByColumn ($destColumnStart + $colCount)->setWidth($w);
                }

                $colCount++;
            }

            $h = $sheet->getRowDimension($row)->getRowHeight();
            $sheet->getRowDimension($destRowStart + $rowCount)->setRowHeight($h);

            $rowCount++;
        }

        foreach ($sheet->getMergeCells() as $mergeCell) {
            $mc = explode(":", $mergeCell);
            $mergeColSrcStart = Coordinate::columnIndexFromString(preg_replace("/[0-9]*/", "", $mc[0]));
            $mergeColSrcEnd = Coordinate::columnIndexFromString(preg_replace("/[0-9]*/", "", $mc[1]));
            $mergeRowSrcStart = ((int)preg_replace("/[A-Z]*/", "", $mc[0]));
            $mergeRowSrcEnd = ((int)preg_replace("/[A-Z]*/", "", $mc[1]));

            $relativeColStart = $mergeColSrcStart - $srcColumnStart;
            $relativeColEnd = $mergeColSrcEnd - $srcColumnStart;
            $relativeRowStart = $mergeRowSrcStart - $srcRowStart;
            $relativeRowEnd = $mergeRowSrcEnd - $srcRowStart;

            if (0 <= $mergeRowSrcStart && $mergeRowSrcStart >= $srcRowStart && $mergeRowSrcEnd <= $srcRowEnd) {
                $targetColStart = Coordinate::stringFromColumnIndex($destColumnStart + $relativeColStart);
                $targetColEnd = Coordinate::stringFromColumnIndex($destColumnStart + $relativeColEnd);
                $targetRowStart = $destRowStart + $relativeRowStart;
                $targetRowEnd = $destRowStart + $relativeRowEnd;

                $merge = (string)$targetColStart . (string)($targetRowStart) . ":" . (string)$targetColEnd . (string)($targetRowEnd);
                //Merge target cells
                $sheet->mergeCells($merge);
            }
        }
        return true;
    }
}
