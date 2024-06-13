<?php

namespace OnlineRecord;

use OnlineRecord\Error as Error;
use Pixie\Connection;

class Report
{
    private int $reportID = 0;//id отчета
    private string $type = 'none';//Тип отчета 'html','base','php'
    private string $name;//название отчета
    private string $sql;//sql отчета
    private $param;//параметры отчета
    private string $html = '';// выводимый hlml


    public function __construct( int $id = 0)
    {
        if ($id != 0) {
            $this->reportID = $id;
            $query = \QB::table('reports')->where('id', $this->reportID);
            $result = $query->first();
            Error::pdump($result, 'result');
            $this->type = $result->type;
            $this->name = $result->name;
            $this->sql = str_replace('{{prefix}}', config['prefix'], $result->sql);
            $this->param = $result->param;
            Error::pdump($this->param, 'param');
            Error::pdump($result, 'отчет');
            $this->html .= '<h4 style="text-align: center">'.$this->name.'</h4>';

        } else {
            $query = \QB::table('reports');
            $result = $query->get();
            $this->html .= '<h4 style="text-align: center">Отчеты</h4>';
            $this->html .= '<ul class="reportlist">';
            Error::pdump($result, 'список отчетов');
            foreach ($result as $k => $v) {
                $this->html .= '<li class="repname"><a href="/?act=reports&id='.$v->id.'">'.$v->name.'</a></li>';
            }
            $this->html .= '</ul>';
        }
        Error::pdump($this,'Reports');
    }

    public function __destruct(){

    }

    public function showReportHtml(){
        return $this->html;
    }

    /**
     * @return string
     */
    public function getReport(): string
    {
        switch ($this->type){
            case 'html':
                if (!is_null($this->param)){
                    $this->param = str_replace('{%ID%}', $this->reportID,$this->param);
                }
                return $this->getHtmlReport();
            break;
            case 'base':

            break;
            case 'php':
                if (!isset($_REQUEST['params']) && file_exists('inc/reports/'.$this->reportID.'_param.html')){
                    return str_replace('{%ID%}', $this->reportID,file_get_contents('inc/reports/'.$this->reportID.'_param.html'));
                }
                Error::pdump(isset($_REQUEST['params']), 'params is set');
                Error::pdump(file_exists('inc/reports/'.$this->reportID.'_param.php'), 'params file exist');
                if (!isset($_REQUEST['params']) && file_exists('inc/reports/'.$this->reportID.'_param.php')){
                    require_once 'inc/reports/'.$this->reportID.'_param.php';
                } else {
                    if (file_exists('inc/reports/' . $this->reportID . '.php')) {
                        require_once 'inc/reports/' . $this->reportID . '.php';
                    }
                }
                return $GLOBALS['OR_REPORT'];
            break;
            default:
                return $this->showReportHtml();
            break;
        }
    }

    private function getHtmlReport()
    {
        if (!isset($_REQUEST['params']) && !is_null($this->param)) {
            return $this->param;
        } else {
            preg_match_all('/\{\{([a-zA-Z_0-9]*)\}\}/', $this->sql, $match, PREG_PATTERN_ORDER);
            Error::pdump($match, 'match');
            foreach ($match[0] as $k=>$v){
                $this->sql = str_replace($v, $_REQUEST['params'][$match[1][$k]],$this->sql);
            }
            Error::pdump($this->sql, 'sql');
            $query = \QB::query($this->sql);
            $result = $query->get();
            Error::pdump($result, 'отчет вернул');
            $tmpHtml = file_get_contents(dirname(__FILE__) . '/reports/' . $this->reportID . '_out.html');
            Error::pdump($tmpHtml, 'tmpHtml');
            preg_match_all('/\{%LOOP%\}([\s\d\w.S\D\W]*)\{%LOOP%\}/', $tmpHtml, $lmatch, PREG_PATTERN_ORDER);
            Error::pdump($lmatch, 'lmatch');
            preg_match_all('/\{\{([a-zA-Z_0-9]*)\}\}/', $tmpHtml, $match, PREG_PATTERN_ORDER);
            Error::pdump($match, 'match');
            $loopHtml = '';
            foreach ($result as $kkk => $vvv) {//перебираем строки результата из базы
                $tempHtml = $lmatch[1][0];
                Error::pdump($tempHtml, 'tempHTML_________');
                foreach ($vvv as $kk => $vv) {//перебираем поля строки
                    Error::pdump($kk . '-' . $vv, 'итер');
                    foreach ($match[1] as $k => $v) {
                        Error::pdump($kk, 'kk');
                        Error::pdump($k, 'k');
                        Error::pdump($v, 'v');
                        if ($v == $kk) {
                            $tempHtml = str_replace($match[0][$k], $vv, $tempHtml);
                            Error::pdump($tempHtml, 'tempHTML');
                            break;
                        }
                    }
                }
                $loopHtml .= $tempHtml;
            }
            $this->html = str_replace($lmatch[0][0], $loopHtml, $tmpHtml);
            Error::pdump($vvv, 'vvv');
            $this->html = str_replace($match[0], (array)$vvv, $this->html);

            Error::pdump($this->html, 'HTML-');
            return $this->html;
        }
    }
}