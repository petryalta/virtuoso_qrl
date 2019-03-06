<?php
/**
 * Получение всех данных через ODBC
 *
 * copyright by Petr Ivanov (c) petr.yrs@gmail.com
 */
namespace qrltool;

class qrlImportODBC
{

    private $qrlFileName;

    private $db;

    /**
     * Получение данных из QRL-лога через ODBC
     *
     * @param $qrlFileName string название QRL-файла
     * @param $params array параметры ODBC-подключения
     */
    public function __construct($qrlFileName = 'virtuoso.qrl', $params = null)
    {

        if (is_null($params)) {
            $params = [
                'dsn' => 'virtuoso',
                'user' => 'dba',
                'password' => 'dba',
            ];
        }

        echo "Use ODBC connection \n DSN=".$params['dsn']." \n User=".$params['user']." \n";
        $this->qrlFileName = $qrlFileName;
        $this->db = odbc_connect($params['dsn'], $params['user'], $params['password']);
        if (is_null($this->db)) {
            die(odbc_errormsg);
        } else {
            var_dump($this->db);
        }

    }

    public function __destruct()
    {
        odbc_close($this->db);
    }

    /**
     * Выполнение SQL запроса
     *
     * @param $sql string строка запроса
     * @param $params array параметры запроса
     * @return array|false
     */
    public function execSQL($sql, $params = null)
    {
        if (is_null($params)) {
            $params = [];
        }
        $prepated = odbc_prepare($this->db, $sql);
        if (!$prepated) {
            die("could not prepare statement " . $sql);
        }

        if (odbc_execute($prepated, $params)) {
            return odbc_fetch_array($prepated);
        } else {
            new Exception(odbc_errormsg, 1);
        }
    }

    /**
     * Получить кол-во записей в QRL-файле
     *
     * @return integer
     */
    public function getCount()
    {
        $sql = "select COUNT(*) from sys_query_log  WHERE qrl_file = '$this->qrlFileName'";
        $res = $this->execSQL($sql);
        if ($res) {
            return $res[0];
        } else {
            return 0;
        }
    }

  /**
     * Получить часть данных
     *
     * @param $count integer кол-во данных получаемых за один проход
     * @param $offet integer смещение от начала выборки
     * @return array
     */
    private function getPart($count, $offset = 0)
    {
        $sql .=" set blobs on; ";
        $sql .=" select TOP $offset,$count ";
        $sql .=" CONCAT('STARTQUERY:',ql_text,':ENDQUERY'), ";
        $sql .=" ql_start_dt  ";
        $sql .=" from sys_query_log  WHERE qrl_file = '$this->qrlFileName' ";
        return $this->execSQL($sql);
    }


    /**
     * Получить запросы
     * 
     * @param $count integer кол-во получаемых данных за один проход
     * @param $offet integer смещение от начала выборки
     */
    public function getData($count = 100, $offet = 0){
        echo "Total records: ";
        $totalCount = $this->getCount();
        echo "$count \n";
        $i = $offet;
        $res = [];
        while ($i < $totalCount) {
            $querys = $this->getPart($count, $i);
            foreach ($querys as $item) {
                $res[] = $item;
            }
            $i = $i + $count;
            echo "Done $i of $totalCount \n";
        }
        return $res;
    }
}

$test = new qrlImportODBC('virtuoso.qrl');
var_dump($test->getCount());
