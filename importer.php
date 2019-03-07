<?php
/**
 * Класс для получения данных из QRL-лога
 *
 * copyright by Petr Ivanov (petr.yrs@gmail.com)
 */
namespace qrltool;

class qrlImporter
{
    /**
     * название файла с QRL логами
     */
    private $qrlFileName;

    /**
     * Virtuoso запущен в Docker-контейнере
     */
    private $useDocker = false;

    /**
     * Название Docker-контенера с Virtuoso
     */
    private $dockerContainerName;

    /**
     * Имя пользователя для доступа к Virtuoso
     */
    private $dbUser;

    /**
     * Пароль пользователя для доступа к Virtuoso
     */
    private $dbPass;

    /**
     * Порт на котором слушает Virtuoso
     */
    private $dbPort;

    /**
     * @input $fileName название qrl-файла
     * @input $dbParam array Параметры подключения к Virtuoso
     */

    public function __construct($fileName = 'virtuoso.qrl', $dbParam)
    {
        $this->qrlFileName = $fileName;

        $this->useDocker = $dbParam['useDocker'] ?? false;
        $this->dockerContainerName = $dbParam['dockerContainerName'] ?? '';
        $this->dbUser = $dbParam['dbUser'] ?? 'dba';
        $this->dbPass = $dbParam['dbPass'] ?? 'dba';
        $this->dbPort = $dbParam['dbPort'] ?? '1111';

        if ($this->useDocker) {
            echo "Importer use Docker container $this->dockerContainerName \n";
        }
    }

    /**
     * Получить кол-во записей
     *
     * @return integer
     */
    public function getCount()
    {
        $cmd = "isql-v $this->dbPort $this->dbUser $this->dbPass \"EXEC=set blobs on; select COUNT(*) from sys_query_log  WHERE qrl_file = '$this->qrlFileName'\"";
        if ($this->useDocker) {
            $cmd = "docker exec -t $this->dockerContainerName " . $cmd;
        }

        $res = [];
        exec($cmd, $res, $code);
        if ($code != 0) {
            die("cannt connect to Virtuoso\n");
        } else {
            return (int) $res[8];
        }
    }

    /**
     * Получить сырые данные
     *
     * @param $count integer кол-во данных получаемых за один проход
     * @param $offet integer смещение от начала выборки
     * @return array
     */
    private function getPart($count, $offset = 0)
    {
        $cmd = "isql-v $this->dbPort $this->dbUser $this->dbPass ";
        $sql .=" set blobs on; ";
        $sql .=" select TOP $offset,$count ";
        $sql .=" CONCAT('STARTQUERY:',ql_text,':ENDQUERY'), ";
        $sql .=" ql_start_dt  ";
        $sql .=" from sys_query_log  WHERE qrl_file = '$this->qrlFileName' ";
        $cmd .="\"EXEC= $sql \";";
        if ($this->useDocker) {
            $cmd = "docker exec -t $this->dockerContainerName " . $cmd;
        }

        $res = [];
        exec($cmd, $res, $code);
        if ($code != 0) {
            die("cannt connect to Virtuoso\n");
        } else {
            $res = array_slice($res, 7);
            return $res;
        }

    }

    /**
     * Преобразовываем входящий массив в массив запросов
     *
     * @param $inBuf array "Сырой" массив строк
     * @return array query - запрос, dt - дата выполнения запроса и его идентификатор
     */
    private function collectQuery($inBuf)
    {
        $s = implode("\n", $inBuf);

        $re = '/STARTQUERY:([\s\S]+?):ENDQUERY/m';
        preg_match_all($re, $s, $matches, PREG_SET_ORDER, 0);

        $re = '/:ENDQUERY[\s\S]+?(\d{4}.\d*.\d*\s\d*:\d*.\d*\s\d*)/m'; //получение ql_start_dt
        preg_match_all($re, $s, $matches2, PREG_SET_ORDER, 0);

        if (count($matches) != count($matches2)) {
            echo "WARNING: query count not mach dt counts \n";
        }
        $resQuery = [];
        foreach ($matches as $key => $item) {
            $resQuery[] = ['query' => $item[1], 'ql_start_dt' => $matches2[$key][1]];
        }
        return $resQuery;
    }

    /**
     * Получить запросы
     * 
     * @param $count integer кол-во получаемых данных за один проход
     * @param $offet integer смещение от начала выборки
     */
    public function getData($count = 100, $offet = 0)
    {
        echo "Total records: ";
        $totalCount = $this->getCount();
        echo "$count \n";
        $i = $offet;
        $res = [];
        while ($i < $totalCount) {
            $durtyData = $this->getPart($count, $i);
            $querys = $this->collectQuery($durtyData);
            foreach ($querys as $item) {
                $res[] = $item;
            }
            $i = $i + $count;
            echo "Done $i of $totalCount \n";
        }
        return $res;
    }
}

