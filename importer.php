<?php
/**
 * Получение всех данных через ODBC
 *
 * copyright by Petr Ivanov (c) petr.yrs@gmail.com
 */
namespace qrltool;

require_once 'adapter_odbc.php';
require_once 'adapter_pdo.php';

class Importer
{

    private $adapter;
    private $qrlFileName;

    /**
     * Получение данных из QRL-лога
     *
     * @param string $fileName название файла QRL-лога
     * @param array $params параметры подключения
     * @param string $adapterName название адаптера для подключения. Возможные варианты: odbc, pdo
     */
    public function __construct($fileName, $params = null, $adapterName = 'odbc')
    {
        echo "Use $adapterName adapter \n";
        $adapterName = '\\qrltool\\adapter_'.$adapterName;
        $this->adapter = new $adapterName($fileName, $params);
        $this->qrlFileName = $fileName;
    }

    /**
     * Получить кол-во записей в QRL-файле
     *
     * @return integer
     */
    public function getCount()
    {
        $sql = "select COUNT(*) from sys_query_log  WHERE qrl_file = '$this->qrlFileName'";
        $res = $this->adapter->execSQL($sql);
        if ($res) {
            return $res[0]['count'];
        } else {
            return 0;
        }
    }

  /**
     * Получить часть данных
     *
     * @param integer $count кол-во данных получаемых за один проход
     * @param integer $offet смещение от начала выборки
     * @param string $startDate дата и время начала выборки
     * @return array
     */
    public function getPart($count, $offset = 0, $startDate = false)
    {
        $sql =" select TOP $offset,$count ";
        //$sql .=" CONCAT('STARTQUERY:',ql_text,':ENDQUERY'), * ";
        $sql .= " * ";
        $sql .=" from sys_query_log  WHERE qrl_file = '$this->qrlFileName' ";
        if ($startDate) {
            $sql .=" and ql_start_dt >= CAST('$startDate' as datetime)";
        }
        $res = $this->adapter->execSQL($sql);
        return $res;
    }


    /**
     * Получить запросы
     * 
     * @param integer $count кол-во получаемых данных за один проход
     * @param integer $offet смещение от начала выборки
     * @param string $startDate дата и время начала выборки
     */
    public function getData($count = 100, $offet = 0, $startDate = false){
        echo "Total records: ";
        $totalCount = $this->getCount();
        echo "$count \n";

        $i = $offet;
        $res = [];

        while ($i < $totalCount) {
            $querys = $this->getPart($count, $i, $startDate);
            foreach ($querys as $item) {
                //$item['query']=$item['computed0'];
                $item['ql_start_dt']=substr($item['ql_start_dt'],0,22);
                $res[] = $item;
            }
            $i = $i + $count;
            echo "Done $i of $totalCount \n";
        }
        return $res;
    }
}
