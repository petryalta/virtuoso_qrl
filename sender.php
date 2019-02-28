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
    private $maxTheads;

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
     */
    public function __construct($dbParam, $maxThreads = 5, $threadPause = 0)
    {
        $this->useDocker = $dbParam['useDocker'] ?? false;
        $this->dockerContainerName = $dbParam['dockerContainerName'] ?? '';
        $this->dbUser = $dbParam['dbUser'] ?? 'dba';
        $this->dbPass = $dbParam['dbPass'] ?? 'dba';
        $this->pdPort = $dbParam['dbPort'] ?? '1111';
        $this->maxTheads = $maxThreads;
        $this->threadPause = $threadPause;

        $this->reader = new qrltool\queryReader();

        if ($this->useDocker) {
            echo "Sender use Docker container $this->dockerContainerName \n";
        }

    }

/**
 * Main function
 *
 * @input array
 */
    public function run($params = null): void
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
        for ($i = 0; $i < $this->maxTheads; $i++) {
            $pool[$i] = false;
        }

        foreach ($querys as $n => $query) {
            echo "Run $n query \n";
            if ($n > 2) {
                break;
            }
            // For TEST

            $query = str_replace('"', '\"', $query);

            $cmd = "isql-v $this->dbPort $this->dbUser $this->dbPass \"EXEC= $query\" ";
            if ($this->useDocker) {
                $cmd = "docker exec -t $this->dockerContainerName " . $cmd;
            }

            if ($this->threadPause) {
                $cmd = "sleep $this->threadPause" . 's && ' . $cmd;
            }

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
                            echo "Thread $i stoped \n";
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

}
