<?php
/**
 * Преобразовываем параметры коммандной строки в переменные и заполняем их значениями
 * 
 * copyright Petr Ivanov (petr.yrs@gmail.com)
 */
function getParams($buf){

    $res = [];

    foreach ($buf as $param) {
        if (substr($param,0,2) == '--') {
            $varFull = substr($param,2,strlen($param)-2);
            if (strpos($varFull, '=')) {
                list($varName,$varVal) = explode('=',$varFull);
            } else {
                $varName = $varFull;
                $varVal = true;
            }
            $res[$varName] = $varVal;
        }
    }

    return $res;
}

$res = getParams($argv);
extract($res);
?>