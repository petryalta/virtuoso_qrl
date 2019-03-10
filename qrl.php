<?php
/**
 * Virtuoso QRL tool
 *
 * copyright Petr Ivanov (petr.yrs@gmail.com)
 */

include_once './param-helper.php';
require_once './importer.php';
require_once './sender.php';
require_once './import_odbc.php';

/**
 * Получаем временную метку
 */
function getTimer()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float) $usec + (float) $sec);
}

/**
 * Ощищаем строку от свец символов
 */
function stripString($s)
{
    $s = str_replace("\r\n", " ", $s);
    $s = str_replace("\r", " ", $s);
    $s = str_replace("\n", " ", $s);
    $s = str_replace("\t", " ", $s);
    return $s;
}

/**
 * Запись строки с запросами
 */
function writeQueryRow($f, $item)
{
    fputs($f, "#StartQuery\n");
    fputs($f, "#ql_start_dt=" . $item['ql_start_dt'] . "\n");
    $s = $item['query'];
    fwrite($f, $s);
    fputs($f, "\n#EndQuery\n\n");

}

/**
 * Запись строки в CSV файл
 */
function writeCSVRow($f, $item)
{
    $item['query'] = stripString($item['query']);
    $item['ql_text'] = stripString($item['ql_text']);
    $item['computed0'] = stripString($item['computed0']);
    $item['ql_messages'] = stripString($item['ql_messages']);
    $item['ql_plan'] = stripString($item['ql_plan']);
    fputcsv($f, $item);
}

if (file_exists('./db.conf')) {
    $db = include './db.conf';
} else {
    $db = [
        'dbUser' => 'dba',
        'dbPass' => 'dba',
        'dbPort' => '1111',
        'useDocker' => false,
    ];
}

if (isset($help)) {
    $help = "\nVirtuoso QRL tool\nRun: php ";
    $help .= $argv[0] . "[--time]\n";
    $help .= "\nImport params:\n";
    $help .= "\t [--qrl_log=file.qrl] [--odbc] [--start=0]\n";
    $help .= "\nExport params: \n";
    $help .= "\t [--qf=query_file] [--csv=file.csv] [--directly] \n";
    $help .= "\nReplay params: \n";
    $help .= "\t [--play] [--mc=5] [--td=0] [--qn=0] [--rc=1] \n";
    $help .= "\nDescriptions: \n";
    $help .= "--qrl_log \t file name with QRL data \n";
    $help .= "--qf \t\t text file with querys. Default querys.dat \n";
    $help .= "--play \t\t send querys to server \n";
    $help .= "--mc \t\t max concurent connections. Default 5 \n";
    $help .= "--td \t\t thread delay in seconds. Default 0 sec \n";
    $help .= "--time \t\t calculate duration time \n";
    $help .= "--qn \t\t query number \n";
    $help .= "--rc \t\t count of repeate query \n";
    $help .= "--odbc \t\t use ODBC for connection. Params in odbc.conf \n";
    $help .= "--start \t\t start position in qrl log \n";
    $help .= "--csv \t\t export querys to CSV file \n";
    $help .= "--directly \t directly write imported data \n";
    echo $help . "\n";
    exit(0);
}

if (isset($time)) {
    $start_time = getTimer();
} else {
    $start_time = false;
}

if (isset($odbc)) {
    if (file_exists('./odbc.conf')) {
        $odbcParams = include './odbc.conf';
    } else {
        die("File odbc.conf not found \n");
    }
}

$start = $start ?? 0;

// read from QRL log file
if (isset($qrl_log) && !isset($directly)) {
    $outFile = $qf ?? 'querys.dat';
    if (isset($odbc)) {
        $importer = new qrltool\qrlImportODBC($qrl_log, $odbcParams);
    } else {
        $importer = new qrltool\qrlImporter($qrl_log, $db);
    }
    $data = $importer->getData(100, $start);

    $f = fopen($outFile, 'wb');
    foreach ($data as $item) {
        writeQueryRow($f, $item);
    }
    fclose($f);
}

// export to CSV
if (!isset($directly) && isset($csv) && isset($data) && count($data) > 0) {
    $f = fopen($csv, 'w');
    fputcsv($f, array_keys($data[0])); // put headers
    foreach ($data as $item) {
        writeCSVRow($f, $item);
    }
    fclose($f);
}

// directly write imported data
if (isset($directly) && isset($qrl_log) && isset($csv)) {

    $countPeer = 10;
    $qf = $qf ?? 'querys.dat';

    $f_querys = fopen($qf, 'w');
    $f_csv = fopen($csv, 'w');
    fclose($f_querys);
    fclose($f_csv);

    if (isset($odbc)) {
        $importer = new qrltool\qrlImportODBC($qrl_log, $odbcParams);
    } else {
        $importer = new qrltool\qrlImporter($qrl_log, $db);
    }
    $totalCount = $importer->getCount();

    $i = $start;
    while ($i < $totalCount) {
        echo date('H:i:s',time()). " try $i of $totalCount ";
        $data = $importer->getPart($countPeer, $i);
        echo " ok ";

        $f_querys = fopen($qf, 'ab');
        $f_csv = fopen($csv, 'a');

        if ($i == 0) {
            fputcsv($f_csv, array_keys($data[0])); // put headers            
        }

        foreach ($data as $item) {
            $item['query']=$item['computed0'];
            writeQueryRow($f_querys, $item);
            writeCSVRow($f_csv, $item);
        }

        fclose($f_querys);
        fclose($f_csv);

        echo " write \n";
        $i = $i + $countPeer;
    }
}

// send querys to server
if (isset($play)) {

    $maxThreads = $mc ?? 10;
    $threadPause = $td ?? 0;
    $fileName = $qf ?? 'querys.dat';

    $sender = new qrltool\sender($db, $fileName, $maxThreads, $threadPause);
    $qn = $qn ?? false;
    $rc = $rc ?? 1;
    $sender->run(null, $qn, $rc);
}

if ($start_time) {
    $duration = round(getTimer() - $start_time, 3);
    echo "Duration: $duration sec \n";
}
