<?php
namespace qrltool;

/**
 * Выполнение запросов на сервере
 *
 * copyright Petr Ivanov (petr.yrs@gmail.com)
 */

require './queryReader.php';

class sender
{
/**
 * Virtuoso user name. Default 'dba'
 */
    private $dbUser;

    /**
     * Virtuoso user passsword. Default 'dba'
     */
    private $dbPass;

    /**
     * Virtuoso port. Default 1111
     */
    private $dbPort;

    /**
     * Virtuoso in docker container ? Default False
     */
    private $useDocker;

    /**
     * Virtuoso docker container name
     */
    private $dockerContainerName;

    /**
     * How much concurent connection use. Default 5
     */
    private $maxThreads;

    /**
     * Delay before send next query from thread. Default 0
     */
    private $threadPause;

    /**
     * Query reader
     */
    private $reader;

    /**
     * Send querys to Virtuoso server
     *
     * @input $dbParam array Params for connect to Virtuoso
     * @input $fileName string Querys file name
     * @input $maxThreads integer How much concurent connection use
     * @input $threadPause integer Delay between querys in thread
     */
    public function __construct($dbParam, $fileName, $maxThreads = 5, $threadPause = 0)
    {
        $this->useDocker = $dbParam['useDocker'] ?? false;
        $this->dockerContainerName = $dbParam['dockerContainerName'] ?? '';
        $this->dbUser = $dbParam['dbUser'] ?? 'dba';
        $this->dbPass = $dbParam['dbPass'] ?? 'dba';
        $this->dbPort = $dbParam['dbPort'] ?? '1111';
        $this->maxThreads = $maxThreads;
        $this->threadPause = $threadPause;

        $this->reader = new queryReader($fileName);

        if ($this->useDocker) {
            echo "Sender use Docker container $this->dockerContainerName \n";
        }

    }

/**
 * Основная процедура отправки данных
 *
 * @param $params array параметры выполенения процесса
 * @param $queryNum integer порядковый номер запроса
 * @param $countRepeat integer сколько раз повторить
 * @return none
 */
    public function run($params = null, $queryNum = false, $countRepeat = 1)
    {

        if (is_null($params)) {
            $params = array(
                0 => array("pipe", "r"), // stdin - канал, из которого дочерний процесс будет читать
                1 => array("file", './result.log', "a"), // stdout - канал, в который дочерний процесс будет записывать
                2 => array("file", "./error-output.txt", "a"), // stderr - файл для записи
            );

        }
        $querys = $this->reader->getQuerys();

        $pool = array();
        for ($i = 0; $i < $this->maxThreads; $i++) {
            $pool[$i] = false;
        }

        foreach ($querys as $n => $query) {

            if ($queryNum !== false && $queryNum != $n) {
                continue;
            }

            echo "Run $n query \n";

            $query = str_replace('"', '\"', $query);

            $query = str_replace("\n", '', $query);

            $tmpFile = "./q${n}.tmp";
            file_put_contents($tmpFile, $query . ";\n");

            $cmd = "isql-v $this->dbPort $this->dbUser $this->dbPass ";
            if ($this->useDocker) {
                $cmd = "docker exec -i $this->dockerContainerName " . $cmd;
            }
            $cmd = "cat $tmpFile | " . $cmd;

            if ($this->threadPause) {
                $cmd = "sleep $this->threadPause" . 's && ' . $cmd;
            }

            for ($r = 0; $r < $countRepeat; $r++) {
                echo "Repeat $r \n";
                $commande_lancee = false;
                while ($commande_lancee == false) {
                    usleep(10000); // pause 10 msec

                    for ($i = 0; $i < $this->maxThreads and $commande_lancee == false; $i++) {
                        if ($pool[$i] === false) {
                        echo "Run $i thread \n";
                            $pool[$i] = proc_open($cmd, $params, $foo);
                            $commande_lancee = true;
                        } else {
                            $etat = proc_get_status($pool[$i]);
                            if ($etat['running'] == false) {
                                //\unlink("./q");
                                //                            echo "Thread $i stoped \n";
                                proc_close($pool[$i]);
                            echo "Run $i thread \n";
                                $pool[$i] = proc_open($cmd, $params, $foo);
                                $commande_lancee = true;
                            }
                        }
                    }
                }
            }
        }
        system("rm -f *.tmp");
    }

}
