<?php
/**
 * Virtuoso QRL tool
 * 
 * copyright Petr Ivanov (petr.yrs@gmail.com)
 */

include_once './param-helper.php';
require_once './importer.php';
require_once './sender.php';

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
    $help .= $argv[0]." [--qrl_log=file.qrl] [--qf=query_file] [--play] [--mc=5] [--td=0] \n";
    $help .= "--qrl_log \t file name with QRL data \n";
    $help .= "--qf \t text file with querys. Default querys.dat \n";
    $help .= "--play \t send querys to server \n";
    $help .= "--mc \t max concurent connections. Default 5 \n";
    $help .= "--td \t thread delay in seconds. Default 0 sec \n";
    echo $help."\n";
    exit(0);
}

// read from QRL log file
if (isset($qrl_log)) {
    $outFile =   $qf ?? 'querys.dat';
    $importer = new qrltool\qrlImporter($qrl_log, $db);
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

// send querys to server
if (isset($play)) {

    $maxThreads = $mc ?? 10;
    $threadPause = $td ?? 0;

    $sender = new qrltool\sender($db, $maxThreads, $threadPause);
    $sender->run();
}

?>
