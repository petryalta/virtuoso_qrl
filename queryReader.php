<?php
namespace qrltool;

/**
 * Чтение запросов из файла
 * 
 * copyright Petr Ivanov (petr.yrs@gmail.com)
 */
class queryReader
{

    /**
     * имя файла
     */
    private $fileName;

    /**
     * Чтение запросов
     */
    public function __construct($fileName = 'out.log')
    {
        $this->fileName = $fileName;
    }

    /**
     * генератор возвращающий по одному запросу
     *
     * @return string;
     */
    public function getQuerys()
    {
        $f = fopen($this->fileName, 'r');
        $foundStart = false;
        $foundEnd = false;
        $res = '';
        while (!feof($f)) {
            $s = trim(fgets($f));
            if (substr($s, 0, 11) == '#StartQuery') {
                $foundStart = true;
                $dt = trim(fgets($f));
                $s = trim(fgets($f)); // first row of query
            }

            if (substr($s, 0, 9) == '#EndQuery') {
                $foundEnd = true;
            }

            if ($foundStart && !$foundEnd) {
                $res .= $s . " \n";
            }

            if ($foundEnd) {
                $foundStart = false;
                $foundEnd = false;
                $res2 = $res;
                $res = '';
                yield $res2;
            }
        }
        fclose($f);
    }
}

