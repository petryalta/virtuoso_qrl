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

function getTimer()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float) $usec + (float) $sec);
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
    $help .= $argv[0] . " [--qrl_log=file.qrl] [--qf=query_file] [--play] [--mc=5] [--td=0] [--time] [--qn=0] [--rc=1] [--odbc] [--csv=file.csv]\n";
    $help .= "--qrl_log \t file name with QRL data \n";
    $help .= "--qf \t text file with querys. Default querys.dat \n";
    $help .= "--play \t send querys to server \n";
    $help .= "--mc \t max concurent connections. Default 5 \n";
    $help .= "--td \t thread delay in seconds. Default 0 sec \n";
    $help .= "--time \t calculate duration time \n";
    $help .= "--qn \t query number \n";
    $help .= "--rc \t count of repeate query \n";
    $help .= "--odbc \t use ODBC for connection. Params in odbc.conf \n";
    $help .= "--csv \t export querys to CSV file \n";
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

// read from QRL log file
if (isset($qrl_log)) {
    $outFile = $qf ?? 'querys.dat';
    if (isset($odbc)) {
        $importer = new qrltool\qrlImportODBC($qrl_log, $odbcParams);
    } else {
        $importer = new qrltool\qrlImporter($qrl_log, $db);
    }
    $data = $importer->getData();

    $f = fopen($outFile, 'wb');
    foreach ($data as $item) {
        fputs($f, "#StartQuery\n");
        fputs($f, "#ql_start_dt=" . $item['dt'] . "\n");
        $s = $item['query'];
        fwrite($f, $s);
        fputs($f, "\n#EndQuery\n\n");
    }
    fclose($f);
}

// export to CSV
if (isset($csv) && isset($data)) {
    $f = fopen($csv, 'w');
    foreach ($data as $item) {
        fputcsv($f, $item);
    }
    fclose($f);
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
