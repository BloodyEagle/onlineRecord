<?php

namespace OnlineRecord;

class Pager
{
    public const PG_START_END_COUNT = 3;
    private int $count;//Число страниц
    private int $current;//Текущая страница
    private int $startEndCount;//Количество номеров страниц в начале и конце

    public function __construct(int $count, int $current = null, int $startEndCount = null)
    {
        $this->count = $count;
        $this->current = $current ?? 1;
        $this->startEndCount = $startEndCount ?? self::PG_START_END_COUNT;
        Error::pdump($this,'Pager');
    }
    public function getPager(){
        return 'странички...';
    }
}