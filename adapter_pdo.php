<?php
/**
 * Adapter PDO-ODBC
 *
 * Copyright by Petr Ivanov (petr.yrs@gmail.com)
 */

namespace qrltool;

class adapter_pdo
{

    private $qrlFileName;

    private $db;

    /**
     * Получение данных из QRL-лога через PDO-ODBC
     *
     * @param string $fileName
     * @param array $params
     */
    public function __construct($fileName, $params = null)
    {

        $this->qrlFileName = $fileName;
        if (is_null($params)) {
            $params = [
                'dsn' => 'virtuoso',
                'user' => 'dba',
                'password' => 'dba',
            ];
        }
        $this->db = new \PDO('odbc:' . $params['dsn'], $params['user'], $params['password']);
    }

    /**
     * Выполнение запроса
     *
     * @param string $sql
     * @param array $params
     * @return array
     */
    public function execSQL($sql, $params = null)
    {
        $stmnt = $this->db->prepare($sql);
        $stmnt->execute();
        return $stmnt->fetchAll(\PDO::FETCH_ASSOC);
    }

}
