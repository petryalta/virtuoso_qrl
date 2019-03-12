<?php
/**
 * ODBC adapter
 *
 * Copyright by Petr Ivanov (petr.yrs@gmail.com)
 */
namespace qrltool;

class adapter_odbc
{

    private $qrlFileName;

    private $db;

    private $statement;

    /**
     * Получение данных из QRL-лога через ODBC
     *
     * @param string $qrlFileName название QRL-файла
     * @param array $params параметры ODBC-подключения
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

        echo "Use ODBC connection \n DSN=" . $params['dsn'] . " \n User=" . $params['user'] . " \n";
        $this->qrlFileName = $qrlFileName;
        $this->db = odbc_connect($params['dsn'], $params['user'], $params['password']);
        if (is_null($this->db)) {
            die(odbc_errormsg);
        }
    }

    /**
     * Закрываем соединение
     */
    public function __destruct()
    {
        if ($this->db) {
            odbc_close($this->db);
        }
    }
/**
 * Converts an ODBC result to an array.
 *
 * @param boolean $columnsAsKeys If true, column names are used as indices.
 * @param string $field Non-null values denote the only column name that is returned as the
 *        result for a row. If null, all column values are returned in an array.
 *
 * @return array
 */
    private function fetchToArray($columnsAsKeys = true, $field = null): array
    {
        // the result will be stored in here
        $resultArray = [];

        // get number of fields (columns)
        $numFields = odbc_num_fields($this->statement);

        // Return empty array on no results (0) or error (-1)
        if ($numFields < 1) {
            return $resultArray;
        }

        // for all rows
        while (odbc_fetch_row($this->statement)) {
            $resultRowNamed = [];

            // for all columns
            for ($i = 1; $i <= $numFields; ++$i) {
                $fieldName = odbc_field_name($this->statement, $i);
                $fieldType = odbc_field_type($this->statement, $i);

                // LONG VARCHAR or LONG VARBINARY
                if (stripos($fieldType, 'long var') === 0) {
                    // get the field value in parts
                    $value = '';
                    while ($segment = odbc_result($this->statement, $i)) {
                        $value .= trim($segment);
                    }
                } else {
                    // get the field value normally
                    $value = odbc_result($this->statement, $i);
                }

                if ($field) {
                    // add only requested field
                    if ($fieldName === $field) {
                        $resultRowNamed = $value;
                    }
                } elseif ($columnsAsKeys) {
                    $resultRowNamed[$fieldName] = $value;
                } else {
                    $resultRowNamed[] = $value;
                }
            }

            // add row to result array
            $resultArray[] = $resultRowNamed;
        }

        return $resultArray;
    }

    /**
     * Выполнение SQL запроса
     *
     * @param string $sql строка запроса
     * @param array $params параметры запроса
     * @return array|false
     */
    public function execSQL($sql, $params = null)
    {
        if (is_null($params)) {
            $params = [];
        }
        $this->statement = \odbc_exec($this->db, $sql);
        if ($this->statement === false) {
            new Exception(odbc_errormsg, 1);
        } else {
            return $this->fetchToArray();
        }
    }

}
